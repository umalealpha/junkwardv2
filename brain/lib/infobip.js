// Infobip SMS adapter.
//
// ENV required:
//   INFOBIP_BASE_URL       e.g. https://xxxxx.api.infobip.com
//   INFOBIP_API_KEY        the App API key from Infobip dashboard
//   INFOBIP_SENDER         from-field (alphanumeric 'AlphaDirect' or +267…)
//   INFOBIP_WEBHOOK_SECRET optional shared secret for delivery webhooks
//
// Public API:
//   isConfigured()           — env vars present? (boot can warn)
//   sendSms({ to, text })    — returns { ok, messageId?, status, error?, costUnits? }
//   parseWebhookReport(body) — normalises Infobip's delivery webhook
//
// Design notes:
//   - 10s timeout via AbortController
//   - No retry inside the adapter — orchestration layer (notifier.js)
//     decides whether to retry. Adapter is a thin pass-through.
//   - Cost estimation is best-effort: Infobip returns priceMicros in
//     some plans; we expose it as costUnits in BWP. Falls back to a
//     conservative P0.30/segment estimate if the field isn't present.

const REQUEST_TIMEOUT_MS = 10_000;

function _cfg() {
  let baseUrl = String(process.env.INFOBIP_BASE_URL || '').trim().replace(/\/+$/, '');
  // Auto-prepend https:// if the operator forgot the scheme. Infobip
  // never serves plain http so this is safe to default.
  if (baseUrl && !/^https?:\/\//i.test(baseUrl)) baseUrl = 'https://' + baseUrl;
  return {
    baseUrl,
    apiKey:    String(process.env.INFOBIP_API_KEY  || ''),
    sender:    String(process.env.INFOBIP_SENDER   || 'AlphaDirect'),
    secret:    String(process.env.INFOBIP_WEBHOOK_SECRET || ''),
    // Per-request notify URL — overrides any account-level webhook
    // setting in Infobip's dashboard so delivery reports for OUR sends
    // come back to OUR server regardless of how the shared account is
    // configured by other integrations.
    notifyUrl: String(process.env.INFOBIP_NOTIFY_URL || ''),
  };
}

function isConfigured() {
  const { baseUrl, apiKey } = _cfg();
  return !!(baseUrl && apiKey);
}

// Normalise to E.164. Infobip accepts the leading '+'.
function _normalisePhone(to) {
  const s = String(to || '').trim().replace(/[\s\-()]/g, '');
  if (!s) return '';
  return s.startsWith('+') ? s : ('+' + s.replace(/^00/, ''));
}

function _estimateSegments(text) {
  const len = String(text || '').length;
  // GSM-7: 160 chars first, 153 after. Conservative.
  if (len <= 160) return 1;
  return Math.ceil(len / 153);
}

async function sendSms({ to, text }) {
  // ONE real OFF switch — no SMS leaves the building when live arms are off,
  // even if the Infobip key is present.
  if (require('./armed').refuseIfOff('sms:send', { to })) {
    return { ok: false, error: 'live_arms_off', status: 'blocked' };
  }
  if (!isConfigured()) {
    return { ok: false, error: 'infobip_not_configured', status: 'failed' };
  }
  const phone = _normalisePhone(to);
  if (!phone || !/^\+\d{6,15}$/.test(phone)) {
    return { ok: false, error: 'invalid_phone', status: 'rejected' };
  }
  if (!text || typeof text !== 'string') {
    return { ok: false, error: 'empty_text', status: 'rejected' };
  }

  const { baseUrl, apiKey, sender, notifyUrl } = _cfg();
  const url = baseUrl + '/sms/2/text/advanced';
  const message = {
    destinations: [{ to: phone }],
    from: sender,
    text,
  };
  // Send-time notifyUrl: tells Infobip where to POST the delivery
  // report for THIS message, overriding any account-level setting.
  // notifyContentType ensures JSON so parseWebhookReport() can parse it.
  if (notifyUrl) {
    message.notifyUrl = notifyUrl;
    message.notifyContentType = 'application/json';
  }
  const body = { messages: [message] };

  const ctrl  = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), REQUEST_TIMEOUT_MS);
  let res, data = {};
  try {
    res = await fetch(url, {
      method:  'POST',
      headers: {
        'Authorization': 'App ' + apiKey,
        'Content-Type':  'application/json',
        'Accept':        'application/json',
      },
      body:   JSON.stringify(body),
      signal: ctrl.signal,
    });
    try { data = await res.json(); } catch (_) { data = {}; }
  } catch (e) {
    clearTimeout(timer);
    return { ok: false, error: 'network: ' + (e.message || 'unknown'), status: 'failed' };
  } finally {
    clearTimeout(timer);
  }

  // Infobip happy path: 200 with messages[].messageId and status.groupId
  // (1 = PENDING, 3 = DELIVERED, etc.). Non-2xx is a hard failure.
  if (res.status < 200 || res.status >= 300) {
    const msg = (data && (data.requestError && data.requestError.serviceException && data.requestError.serviceException.text)) || ('http ' + res.status);
    return { ok: false, error: String(msg).slice(0, 200), status: 'failed' };
  }

  const m = data && Array.isArray(data.messages) ? data.messages[0] : null;
  if (!m || !m.messageId) {
    return { ok: false, error: 'no_messageId_in_response', status: 'failed' };
  }

  // Status groupId: 1=PENDING, 3=DELIVERED, 2=UNDELIVERABLE, 4=EXPIRED, 5=REJECTED.
  const groupId = m.status && m.status.groupId;
  let status = 'sent';
  if (groupId === 3) status = 'delivered';
  if (groupId === 2 || groupId === 4) status = 'failed';
  if (groupId === 5) status = 'rejected';

  // Cost — Infobip exposes m.price.pricePerMessage in plan currency.
  // Convert nothing — we store provider-currency value. If not present,
  // estimate at 0.30 BWP per segment.
  let costUnits = 0;
  if (m.price && typeof m.price.pricePerMessage === 'number') {
    costUnits = m.price.pricePerMessage;
  } else {
    costUnits = 0.30 * _estimateSegments(text);
  }

  return {
    ok: status !== 'failed' && status !== 'rejected',
    messageId: String(m.messageId),
    status,
    costUnits,
    raw: m,
  };
}

// Infobip's delivery report webhook posts a JSON body shaped like:
//   { results: [ { messageId, status: { groupId, name }, doneAt, ... }, ... ] }
// We normalise to a list of { messageId, status, error, deliveredAt }.
function parseWebhookReport(body) {
  if (!body || !Array.isArray(body.results)) return [];
  return body.results.map(r => {
    const groupId = r.status && r.status.groupId;
    let status = 'sent';
    if (groupId === 3) status = 'delivered';
    if (groupId === 2 || groupId === 4) status = 'failed';
    if (groupId === 5) status = 'rejected';
    return {
      messageId:   String(r.messageId || ''),
      status,
      error:       (r.error && r.error.description) || (r.status && r.status.description) || '',
      deliveredAt: r.doneAt || '',
    };
  }).filter(x => x.messageId);
}

// Shared-secret check for the delivery webhook. Accepts the secret
// from any of three sources, in order of preference:
//   1. ?key=... query parameter on the URL (recommended for the per-
//      request notifyUrl flow — Infobip can't attach custom headers
//      to messages, but the URL is sent as-is, so embedding the
//      secret in it works)
//   2. X-Infobip-Secret header (when dashboard-configured webhook)
//   3. X-Webhook-Secret header (generic alias)
// If INFOBIP_WEBHOOK_SECRET is unset, we accept all (insecure — boot
// log warns about this).
function verifyWebhookHeader(req) {
  const { secret } = _cfg();
  if (!secret) return { ok: true, warn: 'no_secret_set' };
  const fromQuery  = (req.query && (req.query.key || req.query.secret)) || '';
  const fromHeader = req.headers['x-infobip-secret'] || req.headers['x-webhook-secret'] || '';
  if (String(fromQuery)  === secret) return { ok: true };
  if (String(fromHeader) === secret) return { ok: true };
  return { ok: false, error: 'bad_secret' };
}

module.exports = {
  isConfigured,
  sendSms,
  parseWebhookReport,
  verifyWebhookHeader,
  _normalisePhone,
};
