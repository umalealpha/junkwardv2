// HMAC-signed session cookie for the public /track/:token pages.
//
// After a recipient verifies their OTP, we set a short-lived signed
// cookie binding the browser session to a specific link_id. Subsequent
// data fetches on the same page check this cookie instead of forcing a
// re-OTP on every refresh. The cookie auto-expires after 30 min of
// inactivity (sliding) or 4h absolute (whichever comes first).
//
// The secret is auto-generated on first boot and persisted in the
// settings table. Rotating it (via SQL or admin tool) invalidates every
// in-flight session — useful in suspected-breach scenarios.

const crypto = require('crypto');
const { sql } = require('../db');

const COOKIE_NAME = 'ad_track_sess';
const SLIDING_TTL_MS  = 30 * 60 * 1000;       // 30 min idle
const ABSOLUTE_TTL_MS = 4 * 60 * 60 * 1000;   // 4 h hard cap

let _secretCache = null;
function _getSecret() {
  if (_secretCache) return _secretCache;
  const row = sql.getSetting.get('track_session_secret');
  if (row && row.value && row.value.length >= 32) {
    _secretCache = row.value;
    return _secretCache;
  }
  // First-boot generation. 32 bytes hex = 64 chars.
  const fresh = crypto.randomBytes(32).toString('hex');
  sql.upsertSetting.run('track_session_secret', fresh);
  _secretCache = fresh;
  console.log('[TrackSession] Generated new session secret (first boot or rotated)');
  return _secretCache;
}

function _b64url(buf) {
  return Buffer.from(buf).toString('base64')
    .replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');
}
function _b64urlDecode(s) {
  s = String(s || '').replace(/-/g,'+').replace(/_/g,'/');
  while (s.length % 4) s += '=';
  return Buffer.from(s, 'base64').toString('utf8');
}

function _sign(payload) {
  const body = _b64url(JSON.stringify(payload));
  const mac  = crypto.createHmac('sha256', _getSecret()).update(body).digest();
  return body + '.' + _b64url(mac);
}

function _verify(token) {
  if (!token || typeof token !== 'string') return null;
  const dot = token.indexOf('.');
  if (dot < 1 || dot === token.length - 1) return null;
  const body = token.slice(0, dot);
  const sig  = token.slice(dot + 1);
  const expected = _b64url(
    crypto.createHmac('sha256', _getSecret()).update(body).digest()
  );
  // Constant-time compare to avoid timing oracles
  const a = Buffer.from(sig);
  const b = Buffer.from(expected);
  if (a.length !== b.length) return null;
  if (!crypto.timingSafeEqual(a, b)) return null;
  try { return JSON.parse(_b64urlDecode(body)); } catch (_) { return null; }
}

// Build the Set-Cookie value for a fresh post-OTP session.
function buildSetCookie(linkId) {
  const now = Date.now();
  const payload = {
    lid: Number(linkId),         // link_id this session is bound to
    iat: now,                    // issued-at (for absolute TTL)
    exp: now + SLIDING_TTL_MS,   // sliding expiry; refreshed on each verified fetch
  };
  const token = _sign(payload);
  const maxAgeSec = Math.floor(ABSOLUTE_TTL_MS / 1000);
  return [
    `${COOKIE_NAME}=${encodeURIComponent(token)}`,
    'Path=/track',
    `Max-Age=${maxAgeSec}`,
    'HttpOnly',
    'Secure',
    'SameSite=Lax',
  ].join('; ');
}

// Build the Set-Cookie value to clear the session (logout / revoke).
function buildClearCookie() {
  return `${COOKIE_NAME}=; Path=/track; Max-Age=0; HttpOnly; Secure; SameSite=Lax`;
}

// Parse the inbound cookie header. Returns { ok: true, payload } on
// success or { ok: false, code } where code is one of:
// 'missing','bad_signature','sliding_expired','absolute_expired','wrong_link'.
// On success the caller should also refresh the cookie via buildSetCookie
// to extend the sliding window.
function verifyRequest(req, expectedLinkId) {
  const raw = String(req.headers.cookie || '');
  const m = raw.match(new RegExp('(?:^|;\\s*)' + COOKIE_NAME + '=([^;]+)'));
  if (!m) return { ok: false, code: 'missing' };
  let token;
  try { token = decodeURIComponent(m[1]); } catch(_) { return { ok: false, code: 'bad_signature' }; }
  const payload = _verify(token);
  if (!payload) return { ok: false, code: 'bad_signature' };
  const now = Date.now();
  if (typeof payload.exp !== 'number' || payload.exp < now) {
    return { ok: false, code: 'sliding_expired' };
  }
  if (typeof payload.iat !== 'number' || (now - payload.iat) > ABSOLUTE_TTL_MS) {
    return { ok: false, code: 'absolute_expired' };
  }
  if (expectedLinkId != null && Number(payload.lid) !== Number(expectedLinkId)) {
    return { ok: false, code: 'wrong_link' };
  }
  return { ok: true, payload };
}

module.exports = {
  COOKIE_NAME,
  SLIDING_TTL_MS,
  ABSOLUTE_TTL_MS,
  buildSetCookie,
  buildClearCookie,
  verifyRequest,
};
