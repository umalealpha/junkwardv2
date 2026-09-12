// Claimant status-link helpers — token issuance, hashing, OTP, verification.
//
// Security model:
//   - Tokens are 32 bytes of crypto-random hex (256-bit entropy → unguessable)
//   - URLs carry the raw token; DB only stores sha256(token) so a DB leak
//     does not expose live tokens
//   - OTPs are 6-digit numeric, hashed at rest, 5-minute expiry, 3-attempt cap
//   - Tokens auto-revoke when the parent claim closes; hard 90-day ceiling
//   - Every page interaction is logged to claim_link_access_log with
//     truncated token tail for tracing (full token never logged)

const crypto = require('crypto');
const { sql } = require('../db');

const TOKEN_BYTES        = 32;             // 256-bit entropy
const TOKEN_MAX_AGE_DAYS = 90;
const OTP_LENGTH         = 6;
const OTP_TTL_MS         = 5 * 60 * 1000;  // 5 minutes
const OTP_MAX_ATTEMPTS   = 3;

function _sha256Hex(input) {
  return crypto.createHash('sha256').update(String(input)).digest('hex');
}

function _tokenTail(token) {
  const s = String(token || '');
  return s.length > 8 ? s.slice(-8) : s;
}

function _isoFromNowMs(ms) {
  return new Date(Date.now() + ms).toISOString();
}

// Issue a new status link for a claim. Returns { token, link } where
// `token` is the raw secret to embed in the SMS/WhatsApp URL and `link`
// is the DB row (without the secret). Caller is responsible for revoking
// any prior active links for the same claim before calling this (the
// helper revokeActiveLinksForClaim does that).
function issueLink({ claimId, contactName, contactPhone, contactChannel, issuedBy }) {
  if (!claimId) throw new Error('issueLink: claimId required');
  if (!contactPhone && contactChannel !== 'email') {
    throw new Error('issueLink: contactPhone required for sms/whatsapp');
  }
  const token = crypto.randomBytes(TOKEN_BYTES).toString('hex');
  const expiresAt = _isoFromNowMs(TOKEN_MAX_AGE_DAYS * 24 * 60 * 60 * 1000);
  sql.insertClaimLink.run({
    claim_id:        String(claimId),
    token_hash:      _sha256Hex(token),
    contact_name:    String(contactName || ''),
    contact_phone:   String(contactPhone || ''),
    contact_channel: String(contactChannel || ''),
    issued_by:       String(issuedBy || 'system'),
    expires_at:      expiresAt,
  });
  const link = sql.getLinkByHash.get(_sha256Hex(token));
  return { token, link };
}

// Revoke every active link for a claim. Use when claim closes, or before
// issuing a fresh link so the old one can't be used in parallel.
function revokeActiveLinksForClaim(claimId, reason) {
  if (!claimId) return 0;
  const r = sql.revokeAllLinksForClaim.run({
    claim_id: String(claimId),
    reason:   String(reason || 'manual'),
  });
  return r.changes;
}

// Resolve a raw token to its DB row, applying all the gating rules.
// Returns { ok: true, link } on success, or { ok: false, code } on
// failure where `code` is one of: 'not_found','revoked','expired'.
// Callers should log every outcome to claim_link_access_log.
function lookupToken(rawToken) {
  if (!rawToken || typeof rawToken !== 'string') return { ok: false, code: 'not_found' };
  const link = sql.getLinkByHash.get(_sha256Hex(rawToken));
  if (!link) return { ok: false, code: 'not_found' };
  if (link.revoked_at) return { ok: false, code: 'revoked', link };
  if (link.expires_at && new Date(link.expires_at).getTime() < Date.now()) {
    // Auto-revoke on first access after expiry so the row reflects reality.
    try { sql.revokeLink.run({ id: link.id, reason: 'expired' }); } catch(_) {}
    return { ok: false, code: 'expired', link };
  }
  return { ok: true, link };
}

// Generate a new OTP for a link and persist its hash. Returns the raw
// OTP for the caller to send via SMS/WhatsApp/email. The raw OTP is
// never logged or stored unhashed.
function issueOtp(linkId, sentVia) {
  if (!linkId) throw new Error('issueOtp: linkId required');
  // 6-digit numeric. Leading zeros preserved via padStart.
  const otp = String(crypto.randomInt(0, 10 ** OTP_LENGTH)).padStart(OTP_LENGTH, '0');
  sql.insertLinkOtp.run({
    link_id:    linkId,
    otp_hash:   _sha256Hex(otp),
    sent_via:   String(sentVia || ''),
    expires_at: _isoFromNowMs(OTP_TTL_MS),
  });
  return otp;
}

// Verify an OTP attempt. Returns { ok: true } on success, or
// { ok: false, code, attemptsRemaining } on failure where code is one of:
// 'no_pending','expired','wrong','too_many'.
// If attempts hits the cap, the parent link is auto-revoked.
function verifyOtp(linkId, submittedOtp) {
  if (!linkId) return { ok: false, code: 'no_pending' };
  const row = sql.getLatestPendingOtp.get(linkId);
  if (!row) return { ok: false, code: 'no_pending' };
  // Bump first so a malicious caller can't infinite-loop by aborting before write.
  sql.bumpOtpAttempts.run(row.id);
  const newAttempts = row.attempts + 1;
  if (new Date(row.expires_at).getTime() < Date.now()) {
    return { ok: false, code: 'expired', attemptsRemaining: 0 };
  }
  if (_sha256Hex(String(submittedOtp || '')) !== row.otp_hash) {
    const remaining = OTP_MAX_ATTEMPTS - newAttempts;
    if (remaining <= 0) {
      try { sql.revokeLink.run({ id: linkId, reason: 'otp_brute' }); } catch(_) {}
      return { ok: false, code: 'too_many', attemptsRemaining: 0 };
    }
    return { ok: false, code: 'wrong', attemptsRemaining: remaining };
  }
  sql.markOtpVerified.run(row.id);
  return { ok: true };
}

// Log an interaction to the link access log. Tolerant of missing fields
// — used from many call sites including the public unauthenticated path.
function logAccess({ link, rawToken, ip, userAgent, action, detail }) {
  try {
    sql.insertLinkAccess.run({
      link_id:    link ? link.id : null,
      token_tail: rawToken ? _tokenTail(rawToken) : '',
      ip:         String(ip || '').slice(0, 64),
      user_agent: String(userAgent || '').slice(0, 256),
      action:     String(action || '').slice(0, 32),
      detail:     String(detail || '').slice(0, 256),
    });
  } catch (e) {
    console.warn('[ClaimLink] access log write failed:', e.message);
  }
}

// Check whether an IP has flooded recent OTP failures. Used to short-
// circuit suspected brute-force attempts before they consume DB cycles.
function isIpThrottled(ip, threshold = 5) {
  if (!ip) return false;
  try {
    const row = sql.countLinkFailuresFromIp.get(String(ip));
    return (row && row.n >= threshold);
  } catch(_) { return false; }
}

module.exports = {
  issueLink,
  revokeActiveLinksForClaim,
  lookupToken,
  issueOtp,
  verifyOtp,
  logAccess,
  isIpThrottled,
  // Exposed for tests / admin tooling — do NOT use from request handlers.
  _sha256Hex,
  TOKEN_MAX_AGE_DAYS,
  OTP_TTL_MS,
  OTP_MAX_ATTEMPTS,
};
