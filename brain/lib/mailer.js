/**
 * Email notifications via Mailgun REST API (fire-and-forget).
 *
 * All emails share a single branded layout (`renderEmail`) so the look stays
 * consistent across user-approval messages, backdate alerts, daily digests,
 * weekly executive reports, etc. Brand palette + typography matches Alpha
 * Direct's identity — Alpha Navy #1D3270 + Direct Orange #F47C20, Book Antiqua
 * ("Antica") headlines — sourced from lib/brand.js so there is only ever one.
 *
 * Configuration (read from process.env):
 *   MAILGUN_API_KEY      Mailgun API key
 *   MAILGUN_DOMAIN       Mailgun sending domain
 *   MAILGUN_FROM         "From" address
 *   MAILGUN_REGION       'eu' (default) or 'us'
 *   PUBLIC_BASE_URL      https://claims.alphadirect.co.bw — used for logo URLs + CTAs
 *
 * If MAILGUN_API_KEY or MAILGUN_DOMAIN is missing, send is silently skipped.
 * Failures are logged and never throw.
 */

// ── Brand constants ─────────────────────────────────────────────
// CANONICAL PALETTE (CFO 2026-08-04) — sourced from lib/brand.js so there is ONE
// Alpha Direct brand across every Alpha Brain report. These were #0D1B2A /
// #F4A623 (the legacy Finance pair) while fridayReport.js used a third set
// entirely; the company standard is Alpha Navy #1D3270 / Direct Orange #F47C20.
// The remaining greys/semantics below are shared with brand.js.
const _b = require('./brand').BRAND;
const BRAND = {
  navy:       _b.navy,       // #1D3270
  navyDeep:   _b.navyDeep,
  navyMid:    _b.navyMid,
  navyLine:   _b.navyLine,
  navyPale:   _b.navyTint,
  orange:     _b.orange,     // #F47C20
  orangeDp:   _b.orangeDeep,
  orangePale: _b.orangeTint,
  ok:         '#16A34A',
  okBg:       '#ECFDF5',
  err:        '#DC2626',
  errBg:      '#FEF2F2',
  warn:       '#D97706',
  warnBg:     '#FFFBEB',
  text:       '#1F2937',
  textMute:   '#6B7280',
  textFaint:  '#9CA3AF',
  border:     '#E5E7EB',
  bg:         '#F4F5F7',
};
const FONT_BODY = `'Inter', -apple-system, 'Helvetica Neue', Arial, sans-serif`;
const FONT_HEAD = `'Book Antiqua', 'Palatino Linotype', Georgia, 'Times New Roman', serif`;

function getMailgunConfig() {
  return {
    apiKey: process.env.MAILGUN_API_KEY || '',
    domain: process.env.MAILGUN_DOMAIN || '',
    from:   process.env.MAILGUN_FROM   || `Alpha Direct Claims <noreply@${process.env.MAILGUN_DOMAIN || 'alphadirect.co.bw'}>`,
    host:   process.env.MAILGUN_REGION === 'us' ? 'api.mailgun.net' : 'api.eu.mailgun.net',
  };
}

/**
 * Send an email via Mailgun. Fire-and-forget — does not throw, does not block.
 * @param {object} opts - { to, subject, html, text?, cc?, attachments?, tag? }
 *   - `to` / `cc` may be a string or an array of addresses
 *   - `attachments`: array of { filename, content?(Buffer) | path?(string) }.
 *     When present the request is sent as multipart/form-data (Mailgun files).
 */
function sendEmail(opts) {
  // ONE real OFF switch — no email leaves the building when live arms are off,
  // even if the Mailgun keys are present.
  if (require('./armed').refuseIfOff('email:send', { to: opts && opts.to, subject: opts && opts.subject })) {
    return { ok: false, blocked: true };
  }
  return _dispatch(opts);
}

/**
 * sendInternalReport(opts) — the ONLY send that does not go through live arms.
 *
 * For internal worklists and reports to our own staff (the weekly team extracts,
 * CFO 2026-07-28). Gated by its own narrower switch, BRAIN_INTERNAL_REPORTS, and
 * refused unless EVERY recipient is on an approved internal domain — so it can
 * never reach a customer even if a list is mis-typed. Customer-facing arms stay
 * off and untouched.
 */
function sendInternalReport(opts) {
  const armed = require('./armed');
  const listOf = (v) => (Array.isArray(v) ? v : [v]).map((s) => String(s || '').trim()).filter(Boolean);
  const to = listOf(opts && opts.to).concat(listOf(opts && opts.cc));
  const internal = require('./weeklyExtract').internalOnly(to);
  if (to.length === 0 || internal.length !== to.length) {
    const external = to.filter((a) => !internal.includes(a));
    console.warn(`[mailer] internal report REFUSED — non-internal recipient(s): ${external.join(', ') || 'none supplied'}`);
    return { ok: false, blocked: true, reason: 'external_recipient' };
  }
  if (armed.refuseInternalIfOff('email:internal-report', { to: opts.to, subject: opts.subject })) {
    return { ok: false, blocked: true, reason: 'internal_reports_off' };
  }
  return _dispatch(opts);
}

/** The actual Mailgun dispatch. Never call directly — go through a gate above. */
function _dispatch(opts) {
  const cfg = getMailgunConfig();
  // Honest result contract (CFO 2026-07-21): the caller's log must never say
  // "sent" for an email that never left. Every exit returns a status object.
  if (!cfg.apiKey || !cfg.domain) return { ok: false, skipped: true, reason: 'no_mailgun_keys' };
  if (!opts || !opts.to || !opts.subject || !(opts.html || opts.text)) {
    console.warn('[mailer] Skipped — missing to/subject/body');
    return { ok: false, skipped: true, reason: 'missing_fields' };
  }

  const cleanAddrs = list => (Array.isArray(list) ? list : [list])
    .map(r => String(r || '').trim())
    .filter(r => r && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(r));

  const toList = cleanAddrs(opts.to);
  const ccList = cleanAddrs(opts.cc);
  if (toList.length === 0) {
    console.warn('[mailer] Skipped — no valid recipients');
    return { ok: false, skipped: true, reason: 'no_valid_recipients' };
  }

  const attachments = Array.isArray(opts.attachments) ? opts.attachments : [];
  let form;
  if (attachments.length) {
    // Multipart — required to carry file attachments (e.g. claim forms).
    form = new FormData();
    form.append('from', cfg.from);
    toList.forEach(r => form.append('to', r));
    ccList.forEach(r => form.append('cc', r));
    form.append('subject', opts.subject);
    if (opts.html) form.append('html', opts.html);
    if (opts.text) form.append('text', opts.text);
    if (opts.tag)  form.append('o:tag', opts.tag);
    for (const a of attachments) {
      const buf = a.content != null ? a.content : (a.path ? require('fs').readFileSync(a.path) : null);
      if (!buf) continue;
      form.append('attachment', new Blob([buf]), a.filename || 'attachment');
    }
  } else {
    form = new URLSearchParams();
    form.append('from',    cfg.from);
    toList.forEach(r => form.append('to', r));
    ccList.forEach(r => form.append('cc', r));
    form.append('subject', opts.subject);
    if (opts.html) form.append('html', opts.html);
    if (opts.text) form.append('text', opts.text);
    // Tag the email for tracking + grouping in Mailgun dashboard
    if (opts.tag) form.append('o:tag', opts.tag);
  }

  const url = `https://${cfg.host}/v3/${cfg.domain}/messages`;
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), 8000);

  fetch(url, {
    method:  'POST',
    headers: { Authorization: 'Basic ' + Buffer.from('api:' + cfg.apiKey).toString('base64') },
    body:    form,
    signal:  ctrl.signal,
  })
    .then(async res => {
      clearTimeout(timer);
      if (!res.ok) {
        const text = await res.text().catch(() => '');
        console.warn(`[mailer] Mailgun ${res.status}: ${text.slice(0, 200)}`);
      }
    })
    .catch(err => {
      clearTimeout(timer);
      console.warn('[mailer] Send failed:', err.message);
    });

  // dispatched to Mailgun but delivery not yet confirmed — 'queued', not 'sent'
  return { ok: true, status: 'queued', to: toList };
}

// ══════════════════════════════════════════════════════════
// SHARED EMAIL LAYOUT
// ══════════════════════════════════════════════════════════

function escHtml(s) {
  if (s == null) return '';
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function _baseUrl(b) { return (b || process.env.PUBLIC_BASE_URL || '').replace(/\/+$/, ''); }

/**
 * Master email layout — every transactional email goes through this.
 * @param {object} opts
 *   title         - Email title (Book Antiqua, hero)
 *   eyebrow       - Small uppercase tag above title (e.g. "Executive Summary")
 *   accent        - Hex colour for header underline + section underlines (defaults to brand orange)
 *   preheader     - Inbox preview snippet (hidden in body)
 *   bodyHtml      - Main content (sections, tables, paragraphs)
 *   ctaPrimary    - { label, url } — orange filled button
 *   ctaSecondary  - { label, url } — navy outline button
 *   footerNote    - Optional override for the small line above the legal footer
 *   baseUrl       - Reconstructed app URL for logo + portal links
 */
function renderEmail(opts) {
  const baseUrl = _baseUrl(opts.baseUrl);
  const accent = opts.accent || BRAND.orange;
  const logoUrl = baseUrl ? `${baseUrl}/favicon.png` : '';
  const eyebrow = opts.eyebrow || '';
  const preheader = opts.preheader || '';
  const ctaP = opts.ctaPrimary;
  const ctaS = opts.ctaSecondary;
  const footerNote = opts.footerNote || 'This is an automated message from the Alpha Direct Claims Tracker.';
  const year = new Date().getFullYear();

  const cta = (ctaP || ctaS) ? `
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 8px">
      <tr>
        ${ctaP ? `<td bgcolor="${BRAND.orange}" style="border-radius:6px;mso-padding-alt:14px 26px"><a href="${escHtml(ctaP.url)}" target="_blank" style="display:inline-block;padding:14px 26px;font-family:${FONT_BODY};font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;letter-spacing:.02em">${escHtml(ctaP.label)}</a></td>${ctaS ? '<td width="10">&nbsp;</td>' : ''}` : ''}
        ${ctaS ? `<td bgcolor="#ffffff" style="border:1.5px solid ${BRAND.navy};border-radius:6px;mso-padding-alt:13px 24px"><a href="${escHtml(ctaS.url)}" target="_blank" style="display:inline-block;padding:13px 24px;font-family:${FONT_BODY};font-size:14px;font-weight:700;color:${BRAND.navy};text-decoration:none;letter-spacing:.02em">${escHtml(ctaS.label)}</a></td>` : ''}
      </tr>
    </table>` : '';

  return `<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light only">
  <meta name="supported-color-schemes" content="light">
  <title>${escHtml(opts.title || 'Claims Tracker')}</title>
  <!--[if mso]>
  <style>* { font-family: Arial, sans-serif !important; }</style>
  <![endif]-->
</head>
<body style="margin:0;padding:0;background-color:${BRAND.bg};font-family:${FONT_BODY};color:${BRAND.text};-webkit-font-smoothing:antialiased">
  <!-- Hidden preheader text — shows as inbox snippet -->
  <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:${BRAND.bg}">
    ${escHtml(preheader)}
  </div>
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" bgcolor="${BRAND.bg}" style="background-color:${BRAND.bg}">
    <tr>
      <td align="center" style="padding:32px 12px">
        <!-- Email shell -->
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="width:640px;max-width:100%;background-color:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 4px 14px rgba(13,27,42,.08)">
          <!-- Accent stripe -->
          <tr><td height="4" bgcolor="${accent}" style="height:4px;background-color:${accent};line-height:4px;font-size:0">&nbsp;</td></tr>
          <!-- Header -->
          <tr>
            <td bgcolor="${BRAND.navy}" style="background-color:${BRAND.navy};padding:22px 32px">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                  ${logoUrl ? `<td width="44" valign="middle" style="padding-right:14px">
                    <img src="${logoUrl}" width="36" height="36" alt="Alpha Direct" style="display:block;border:0;border-radius:6px">
                  </td>` : ''}
                  <td valign="middle">
                    <div style="font-family:${FONT_HEAD};font-size:20px;font-weight:700;color:#ffffff;letter-spacing:.01em;line-height:1.1">Alpha Direct</div>
                    <div style="font-family:${FONT_BODY};font-size:11px;color:${BRAND.orange};letter-spacing:.18em;text-transform:uppercase;font-weight:700;margin-top:3px">Claims Tracker</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Hero / title -->
          <tr>
            <td style="padding:32px 32px 8px">
              ${eyebrow ? `<div style="font-family:${FONT_BODY};font-size:11px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:${accent};margin-bottom:10px">${escHtml(eyebrow)}</div>` : ''}
              <h1 style="margin:0;font-family:${FONT_HEAD};font-size:26px;line-height:1.2;font-weight:700;color:${BRAND.navy}">${escHtml(opts.title || '')}</h1>
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:12px">
                <tr><td width="44" height="3" bgcolor="${accent}" style="height:3px;background-color:${accent};line-height:3px;font-size:0">&nbsp;</td></tr>
              </table>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:18px 32px 8px;font-family:${FONT_BODY};font-size:14px;line-height:1.6;color:${BRAND.text}">
              ${opts.bodyHtml || ''}
              ${cta}
            </td>
          </tr>

          <!-- Footer note -->
          <tr>
            <td style="padding:16px 32px 28px;font-family:${FONT_BODY};font-size:11px;line-height:1.6;color:${BRAND.textFaint}">
              ${escHtml(footerNote)}
            </td>
          </tr>

          <!-- Brand footer -->
          <tr>
            <td bgcolor="${BRAND.navyDeep}" style="background-color:${BRAND.navyDeep};padding:18px 32px;font-family:${FONT_BODY}">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                  <td valign="middle">
                    <div style="font-family:${FONT_HEAD};font-size:14px;font-weight:700;color:#ffffff;letter-spacing:.02em">Alpha Direct Insurance</div>
                    <div style="font-size:11px;color:rgba(255,255,255,.55);margin-top:3px">Claims Tracker · Gaborone, Botswana</div>
                  </td>
                  <td valign="middle" align="right">
                    <div style="font-size:10px;color:rgba(255,255,255,.45);letter-spacing:.05em">© ${year} Alpha Direct</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>`;
}

// ── Reusable building blocks for body content ────────────────────

function _section(title, contentHtml) {
  return `
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:28px 0 12px">
      <tr><td>
        <div style="font-family:${FONT_BODY};font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:${BRAND.navy};padding-bottom:6px;border-bottom:2px solid ${BRAND.orange}">${escHtml(title)}</div>
      </td></tr>
    </table>
    <div style="margin-bottom:8px">${contentHtml}</div>`;
}

function _kvTable(rows) {
  return `<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:13px;line-height:1.6">
    ${rows.map(([label, value], i) => `<tr ${i % 2 === 1 ? `bgcolor="${BRAND.navyPale}"` : ''} style="${i % 2 === 1 ? `background-color:${BRAND.navyPale};` : ''}">
      <td style="padding:8px 12px;color:${BRAND.textMute};width:40%;font-size:12px">${escHtml(label)}</td>
      <td style="padding:8px 12px;color:${BRAND.text};font-weight:600">${value}</td>
    </tr>`).join('')}
  </table>`;
}

function _calloutBox(level, html) {
  const colors = {
    info:    { bg: BRAND.navyPale, fg: BRAND.navy,  bar: BRAND.navy  },
    success: { bg: BRAND.okBg,     fg: BRAND.ok,    bar: BRAND.ok    },
    warn:    { bg: BRAND.warnBg,   fg: BRAND.warn,  bar: BRAND.warn  },
    error:   { bg: BRAND.errBg,    fg: BRAND.err,   bar: BRAND.err   },
  }[level] || { bg: BRAND.navyPale, fg: BRAND.navy, bar: BRAND.navy };
  return `<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" bgcolor="${colors.bg}" style="background-color:${colors.bg};border-left:3px solid ${colors.bar};margin:14px 0">
    <tr><td style="padding:12px 14px;font-size:13px;line-height:1.5;color:${colors.fg}">${html}</td></tr>
  </table>`;
}

// ══════════════════════════════════════════════════════════
// 1. BACKDATE EVENT ALERT (sent to admins after a backdate happens)
// ══════════════════════════════════════════════════════════
function backdateAlertHtml(ev) {
  const baseUrl = _baseUrl(ev.baseUrl);
  const grantLine = ev.grantInfo
    ? `Authorised by grant <strong>${escHtml(ev.grantInfo)}</strong>`
    : `<span style="color:${BRAND.err};font-weight:700">Admin override</span>`;

  const changeRows = (ev.changes || []).map(c => `
    <tr>
      <td style="padding:8px 12px;border-bottom:1px solid ${BRAND.border};font-family:'Courier New',monospace;font-weight:700;font-size:12px;color:${BRAND.navy}">${escHtml(c.field)}</td>
      <td style="padding:8px 12px;border-bottom:1px solid ${BRAND.border};color:${BRAND.textMute};font-size:12px">${escHtml(c.old || '(blank)')}</td>
      <td style="padding:8px 12px;border-bottom:1px solid ${BRAND.border};color:${BRAND.err};font-weight:700;font-size:12px">${escHtml(c.new || '(blank)')}</td>
    </tr>`).join('') || `<tr><td colspan="3" style="padding:12px;text-align:center;color:${BRAND.textFaint};font-size:12px">No changes recorded</td></tr>`;

  const body = `
    <p style="margin:0 0 14px">A backdate has just been recorded on the Claims Tracker. Details below for your audit trail.</p>
    ${_kvTable([
      ['Claim',     escHtml(ev.claim_number || ev.claim_id || '-')],
      ['User',      `${escHtml(ev.username)} <span style="color:${BRAND.textMute};font-weight:400">(${escHtml(ev.user_role || '')})</span>`],
      ['Timestamp', escHtml(ev.timestamp || new Date().toISOString())],
      ['Authority', grantLine],
    ])}
    ${_section('Field Changes', `
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border:1px solid ${BRAND.border};border-radius:6px;overflow:hidden">
        <thead><tr bgcolor="${BRAND.navy}" style="background-color:${BRAND.navy}">
          <th align="left" style="padding:10px 12px;color:#ffffff;font-size:11px;text-transform:uppercase;letter-spacing:.12em;font-weight:700">Field</th>
          <th align="left" style="padding:10px 12px;color:#ffffff;font-size:11px;text-transform:uppercase;letter-spacing:.12em;font-weight:700">Old</th>
          <th align="left" style="padding:10px 12px;color:#ffffff;font-size:11px;text-transform:uppercase;letter-spacing:.12em;font-weight:700">New</th>
        </tr></thead>
        <tbody>${changeRows}</tbody>
      </table>
    `)}
  `;

  return renderEmail({
    title:     'Backdate Alert',
    eyebrow:   'Audit Notification',
    accent:    BRAND.err,
    preheader: `Backdate recorded on claim ${ev.claim_number || ev.claim_id || '-'} by ${ev.username}`,
    bodyHtml:  body,
    ctaPrimary: baseUrl ? { label: 'Open Audit Log', url: baseUrl + '/?goto=audit' } : null,
    footerNote: 'Automated audit notification. View the full trail in Claims Tracker → Audit Log.',
    baseUrl,
  });
}

// ══════════════════════════════════════════════════════════
// 2. BACKDATE APPROVAL REQUEST (sent to admins; user requested permission)
// ══════════════════════════════════════════════════════════
function backdateRequestHtml(req) {
  const baseUrl = _baseUrl(req.baseUrl);
  const approveUrl = `${baseUrl}/api/backdate-requests/decide?token=${encodeURIComponent(req.approve_token)}&action=approve`;
  const denyUrl    = `${baseUrl}/api/backdate-requests/decide?token=${encodeURIComponent(req.approve_token)}&action=deny`;
  const portalUrl  = `${baseUrl}/?bdRequest=${req.id}#backdateControl`;
  const isUrgent = req.urgency === 'urgent';

  const body = `
    <p style="margin:0 0 14px">${escHtml(req.requester_name || req.requester_username)} has submitted a request to backdate ${escHtml(String((req.claim_numbers || '').split(',').filter(Boolean).length || 1))} claim record${(req.claim_numbers || '').split(',').filter(Boolean).length === 1 ? '' : 's'}. Review and decide below.</p>
    ${isUrgent ? _calloutBox('error', '<strong>Marked URGENT</strong> by the requester. Please review at your earliest convenience.') : ''}
    ${_kvTable([
      ['Requested by',     `<strong>${escHtml(req.requester_name || req.requester_username)}</strong> <span style="color:${BRAND.textMute};font-weight:400">${escHtml(req.requester_role || '')}</span>`],
      ['Username / email', `<span style="font-family:'Courier New',monospace;font-size:12px">${escHtml(req.requester_username)}</span>`],
      ['Submitted',        escHtml(req.created_at || new Date().toISOString())],
      ['Claims affected',  `<span style="font-family:'Courier New',monospace;font-size:12px">${escHtml(req.claim_numbers || '(none specified)')}</span>`],
      ['Window requested', `<strong>${escHtml(String(req.duration_hours || 24))} hours</strong>`],
      ['Urgency',          isUrgent ? `<span style="color:${BRAND.err};font-weight:700">URGENT</span>` : 'Normal'],
    ])}
    ${_section('Reason', `<div style="background-color:${BRAND.navyPale};border-left:3px solid ${BRAND.navy};padding:14px 16px;font-size:13px;line-height:1.6;white-space:pre-wrap">${escHtml(req.reason || '')}</div>`)}
    <p style="margin:24px 0 6px;font-size:12px;color:${BRAND.textMute}">Quick-decision links below require admin login.</p>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:8px">
      <tr>
        <td bgcolor="${BRAND.ok}" style="border-radius:6px;mso-padding-alt:12px 22px">
          <a href="${approveUrl}" target="_blank" style="display:inline-block;padding:12px 22px;font-family:${FONT_BODY};font-size:13px;font-weight:700;color:#ffffff;text-decoration:none">✓ Approve</a>
        </td>
        <td width="10">&nbsp;</td>
        <td bgcolor="${BRAND.err}" style="border-radius:6px;mso-padding-alt:12px 22px">
          <a href="${denyUrl}" target="_blank" style="display:inline-block;padding:12px 22px;font-family:${FONT_BODY};font-size:13px;font-weight:700;color:#ffffff;text-decoration:none">✗ Deny</a>
        </td>
        <td width="10">&nbsp;</td>
        <td bgcolor="#ffffff" style="border:1.5px solid ${BRAND.navy};border-radius:6px;mso-padding-alt:11px 20px">
          <a href="${portalUrl}" target="_blank" style="display:inline-block;padding:11px 20px;font-family:${FONT_BODY};font-size:13px;font-weight:700;color:${BRAND.navy};text-decoration:none">Open Portal</a>
        </td>
      </tr>
    </table>
  `;

  return renderEmail({
    title:     'Backdate Approval Requested',
    eyebrow:   `Request #${escHtml(String(req.id))}` + (isUrgent ? ' · Urgent' : ''),
    accent:    isUrgent ? BRAND.err : BRAND.orange,
    preheader: `${req.requester_username} requests a ${req.duration_hours}h backdate window for ${(req.claim_numbers || '').split(',').filter(Boolean).length} claim(s)`,
    bodyHtml:  body,
    footerNote: 'If you didn\'t expect this, you can ignore the email — pending requests expire.',
    baseUrl,
  });
}

// ══════════════════════════════════════════════════════════
// 3. BACKDATE DECISION (sent to requester after approve/deny)
// ══════════════════════════════════════════════════════════
function backdateDecisionHtml(req) {
  const isApproved = req.status === 'approved';
  const accent = isApproved ? BRAND.ok : BRAND.err;
  const baseUrl = _baseUrl(req.baseUrl);

  const body = `
    <p style="margin:0 0 14px">Your request to backdate <strong>${escHtml(req.claim_numbers || '')}</strong> has been <strong style="color:${accent}">${escHtml(req.status)}</strong> by ${escHtml(req.decided_by || 'admin')}.</p>
    ${isApproved
      ? _calloutBox('success', `You have a <strong>${escHtml(String(req.duration_hours || 24))}-hour</strong> window to update the affected claim dates. Sign in and edit the claims now.`)
      : _calloutBox('error', 'The request was declined. If you believe this needs review, contact your manager.')}
    ${req.decision_note ? _section('Note from admin', `<div style="background-color:${BRAND.navyPale};border-left:3px solid ${accent};padding:14px 16px;font-size:13px;line-height:1.6;white-space:pre-wrap">${escHtml(req.decision_note)}</div>`) : ''}
  `;

  return renderEmail({
    title:     `Request ${isApproved ? 'Approved' : 'Denied'}`,
    eyebrow:   `Request #${escHtml(String(req.id))}`,
    accent,
    preheader: `Your backdate request for ${req.claim_numbers || ''} was ${req.status}`,
    bodyHtml:  body,
    ctaPrimary: baseUrl ? { label: 'Open Claims Tracker', url: baseUrl + '/' } : null,
    footerNote: 'Automated decision notification.',
    baseUrl,
  });
}

// ══════════════════════════════════════════════════════════
// 4. PENDING USER ALERT (sent to admins when SSO auto-provisions a new user)
// ══════════════════════════════════════════════════════════
function pendingUserAlertHtml(u) {
  const baseUrl = _baseUrl(u.baseUrl);
  const body = `
    <p style="margin:0 0 14px">A new user has just signed in via Microsoft Entra and is awaiting your approval before they can use the Claims Tracker.</p>
    ${_kvTable([
      ['Name',           escHtml(u.name || '-')],
      ['Username',       `<span style="font-family:'Courier New',monospace;font-size:12px">${escHtml(u.username || '-')}</span>`],
      ['Default role',   `<span style="background-color:${BRAND.navyPale};color:${BRAND.navy};padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;letter-spacing:.04em">${escHtml(u.role || 'claims-team')}</span>`],
      ['Provider',       escHtml(u.ssoProvider || '-')],
      ['Submitted',      escHtml(u.createdAt || new Date().toISOString())],
    ])}
    <p style="margin:18px 0 0;font-size:13px;color:${BRAND.textMute}">Approving will activate their account and email them a welcome message.</p>
  `;
  return renderEmail({
    title:     'New Account Awaiting Approval',
    eyebrow:   'Action Required',
    accent:    BRAND.orange,
    preheader: `${u.username} is waiting for admin approval`,
    bodyHtml:  body,
    ctaPrimary: baseUrl ? { label: 'Review & Approve', url: baseUrl + '/?goto=users' } : null,
    footerNote: 'Pending users appear at the top of the User Management table.',
    baseUrl,
  });
}

// ══════════════════════════════════════════════════════════
// 5. DAILY APPROVAL DIGEST (sent to admins each morning when items are pending)
// ══════════════════════════════════════════════════════════
function pendingDigestHtml(d) {
  const baseUrl = _baseUrl(d.baseUrl);
  const users = d.pendingUsers || [];
  const requests = d.pendingBackdateRequests || [];
  const total = users.length + requests.length;

  const userRows = users.length === 0
    ? `<tr><td colspan="3" style="padding:14px;text-align:center;color:${BRAND.textFaint};font-size:12px">No pending users</td></tr>`
    : users.map((u, i) => {
        const age = Math.floor((Date.now() - new Date(u.created_at || u.createdAt || Date.now()).getTime()) / 86_400_000);
        return `<tr ${i % 2 === 1 ? `bgcolor="${BRAND.navyPale}" style="background-color:${BRAND.navyPale}"` : ''}>
          <td style="padding:8px 12px;font-weight:600;font-size:13px">${escHtml(u.name || '-')}</td>
          <td style="padding:8px 12px;font-family:'Courier New',monospace;font-size:12px;color:${BRAND.textMute}">${escHtml(u.username || '-')}</td>
          <td align="right" style="padding:8px 12px;font-size:12px;font-weight:700;color:${age >= 3 ? BRAND.err : BRAND.textMute}">${age} day${age === 1 ? '' : 's'}</td>
        </tr>`;
      }).join('');

  const reqRows = requests.length === 0
    ? `<tr><td colspan="4" style="padding:14px;text-align:center;color:${BRAND.textFaint};font-size:12px">No pending backdate requests</td></tr>`
    : requests.map((r, i) => {
        const age = Math.floor((Date.now() - new Date((r.created_at || '') + 'Z').getTime()) / 3_600_000);
        return `<tr ${i % 2 === 1 ? `bgcolor="${BRAND.navyPale}" style="background-color:${BRAND.navyPale}"` : ''}>
          <td style="padding:8px 12px;font-weight:600;font-size:13px">${escHtml(r.requester_username || '-')}</td>
          <td style="padding:8px 12px;font-family:'Courier New',monospace;font-size:12px;color:${BRAND.textMute}">${escHtml((r.claim_numbers || '').slice(0, 60))}</td>
          <td style="padding:8px 12px">${r.urgency === 'urgent' ? `<span style="background-color:${BRAND.err};color:#ffffff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:3px;letter-spacing:.05em">URGENT</span>` : `<span style="color:${BRAND.textMute};font-size:12px">Normal</span>`}</td>
          <td align="right" style="padding:8px 12px;font-size:12px;font-weight:700;color:${age >= 24 ? BRAND.err : BRAND.textMute}">${age}h</td>
        </tr>`;
      }).join('');

  const tableShell = (headers, rows) => `
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border:1px solid ${BRAND.border};border-radius:6px;overflow:hidden">
      <thead><tr bgcolor="${BRAND.navy}" style="background-color:${BRAND.navy}">
        ${headers.map(h => `<th align="${h.align || 'left'}" style="padding:10px 12px;color:#ffffff;font-size:11px;text-transform:uppercase;letter-spacing:.12em;font-weight:700">${escHtml(h.label)}</th>`).join('')}
      </tr></thead>
      <tbody>${rows}</tbody>
    </table>`;

  const body = `
    <p style="margin:0 0 14px">You have <strong>${total}</strong> item${total === 1 ? '' : 's'} awaiting review on the Claims Tracker.</p>
    ${_section(`Pending Users (${users.length})`, tableShell(
      [{label:'Name'},{label:'Email'},{label:'Waiting',align:'right'}],
      userRows,
    ))}
    ${_section(`Pending Backdate Requests (${requests.length})`, tableShell(
      [{label:'Requester'},{label:'Claims'},{label:'Urgency'},{label:'Waiting',align:'right'}],
      reqRows,
    ))}
  `;

  return renderEmail({
    title:     'Daily Approval Digest',
    eyebrow:   new Date().toLocaleDateString('en-GB', { weekday: 'long', day: '2-digit', month: 'long' }),
    accent:    BRAND.orange,
    preheader: `${total} item${total === 1 ? '' : 's'} awaiting your approval`,
    bodyHtml:  body,
    ctaPrimary: baseUrl ? { label: 'Open Portal', url: baseUrl + '/' } : null,
    footerNote: 'Sent only when there are pending items. Configure recipients on the Backdate Control page.',
    baseUrl,
  });
}

// ══════════════════════════════════════════════════════════
// 6. USER APPROVAL (sent to the user when admin approves their account)
// ══════════════════════════════════════════════════════════
function userApprovalHtml(u) {
  const baseUrl = _baseUrl(u.baseUrl);
  const body = `
    <p style="margin:0 0 14px">Hi <strong>${escHtml(u.name || u.username)}</strong>,</p>
    <p style="margin:0 0 14px">Welcome on board. Your access request to the <strong>Alpha Direct Claims Tracker</strong> has been approved by ${escHtml(u.approvedBy || 'admin')}, and your account is now active.</p>
    ${_kvTable([
      ['Your role',      `<span style="background-color:${BRAND.okBg};color:${BRAND.ok};padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700;letter-spacing:.04em">${escHtml(u.role || 'claims-team')}</span>`],
      ['Sign in with',   `<span style="font-family:'Courier New',monospace;font-size:12px">${escHtml(u.username)}</span>`],
    ])}
    <p style="margin:18px 0 4px">Sign in with your Microsoft Entra credentials to get started. If you didn't request access, please reply to this email so we can revoke it immediately.</p>
  `;
  return renderEmail({
    title:     'Welcome to the Claims Tracker',
    eyebrow:   'Account Activated',
    accent:    BRAND.ok,
    preheader: 'Your account has been approved — you can now sign in.',
    bodyHtml:  body,
    ctaPrimary: baseUrl ? { label: 'Open Claims Tracker', url: baseUrl + '/' } : null,
    footerNote: 'Trouble signing in? Contact your administrator or the IT team.',
    baseUrl,
  });
}

// ══════════════════════════════════════════════════════════
// 7. WEEKLY EXECUTIVE REPORT (sent to leadership/EXCO)
// ══════════════════════════════════════════════════════════
function weeklyReportHtml(d) {
  const baseUrl = _baseUrl(d.baseUrl);
  const { period, kpis, byType, topReserve, breaches, dailyBreakdown } = d;
  const periodType = period && period.type;
  const isDaily   = periodType === 'daily';
  const isMonthly = periodType === 'monthly';

  const fmtP = n => 'P&nbsp;' + (Number(n) || 0).toLocaleString('en-BW', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  const fmtN = n => Number(n || 0).toLocaleString('en-BW');
  const escTrim = (s, n) => escHtml(String(s || '').slice(0, n));

  const deltaArrow = (cur, prev) => {
    if (prev === 0 && cur === 0) return `<span style="color:${BRAND.textFaint}">—</span>`;
    if (prev === 0) return `<span style="color:${BRAND.ok};font-weight:700">▲ new</span>`;
    const pct = Math.round(((cur - prev) / prev) * 100);
    if (pct === 0) return `<span style="color:${BRAND.textFaint}">—</span>`;
    const arrow = pct > 0 ? '▲' : '▼';
    const color = pct > 0 ? BRAND.ok : BRAND.err;
    return `<span style="color:${color};font-weight:700">${arrow} ${Math.abs(pct)}%</span>`;
  };

  // KPI cards rendered as a 2×4 table grid (email-safe — no flex/grid)
  const kpiCard = (label, value, deltaHtml, sub, accent) => `
    <td valign="top" width="50%" style="padding:6px">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" bgcolor="#ffffff" style="background-color:#ffffff;border:1px solid ${BRAND.border};border-radius:8px">
        <tr><td height="3" bgcolor="${accent || BRAND.orange}" style="height:3px;background-color:${accent || BRAND.orange};line-height:3px;font-size:0">&nbsp;</td></tr>
        <tr><td style="padding:14px 16px 14px">
          <div style="font-family:${FONT_BODY};font-size:10px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:${BRAND.textMute}">${escHtml(label)}</div>
          <div style="font-family:${FONT_HEAD};font-size:24px;font-weight:700;color:${BRAND.navy};margin:6px 0 4px;line-height:1">${value}</div>
          <div style="font-family:${FONT_BODY};font-size:11px;color:${BRAND.textMute}">${deltaHtml}${sub ? ` · ${escHtml(sub)}` : ''}</div>
        </td></tr>
      </table>
    </td>`;

  const kpiGrid = `
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:8px 0 12px">
      <tr>
        ${kpiCard('New Claims', fmtN(kpis.newClaims), deltaArrow(kpis.newClaims, kpis.prevNewClaims), isDaily ? 'today' : isMonthly ? 'this month' : 'this week', BRAND.navy)}
        ${kpiCard('Completed', fmtN(kpis.completed), deltaArrow(kpis.completed, kpis.prevCompleted), isDaily ? 'today' : isMonthly ? 'this month' : 'this week', BRAND.ok)}
      </tr>
      <tr>
        ${kpiCard('Open Claims', fmtN(kpis.openClaims), `<span style="color:${BRAND.textFaint}">—</span>`, 'currently', BRAND.orange)}
        ${kpiCard('SLA Breaches', fmtN(kpis.breaches), deltaArrow(kpis.breaches, kpis.prevBreaches), 'currently', BRAND.err)}
      </tr>
      <tr>
        ${kpiCard('Total Reserve', fmtP(kpis.totalReserve), `<span style="color:${BRAND.textFaint}">—</span>`, 'open exposure', BRAND.navy)}
        ${kpiCard('Total Paid', fmtP(kpis.totalPaid), `<span style="color:${BRAND.textFaint}">—</span>`, 'all-time', BRAND.ok)}
      </tr>
      <tr>
        ${kpiCard('Major Claims', fmtN(kpis.majorClaims), `<span style="color:${BRAND.textFaint}">—</span>`, '> P300K reserve', BRAND.err)}
        ${kpiCard('FAC Claims', fmtN(kpis.facClaims), `<span style="color:${BRAND.textFaint}">—</span>`, 'facultative', BRAND.navy)}
      </tr>
    </table>`;

  const tableHead = labels => `<thead><tr bgcolor="${BRAND.navy}" style="background-color:${BRAND.navy}">
    ${labels.map(l => `<th align="${l.align || 'left'}" style="padding:10px 12px;color:#ffffff;font-size:10px;text-transform:uppercase;letter-spacing:.12em;font-weight:700">${escHtml(l.label)}</th>`).join('')}
  </tr></thead>`;

  const typeRows = (byType || []).map((r, i) => `<tr ${i % 2 === 1 ? `bgcolor="${BRAND.navyPale}" style="background-color:${BRAND.navyPale}"` : ''}>
    <td style="padding:9px 12px;font-weight:600;font-size:13px">${escHtml(r.type)}</td>
    <td align="right" style="padding:9px 12px;font-size:13px">${fmtN(r.count)}</td>
    <td align="right" style="padding:9px 12px;color:${BRAND.warn};font-weight:600;font-size:13px">${fmtN(r.inProgress)}</td>
    <td align="right" style="padding:9px 12px;color:${BRAND.ok};font-weight:600;font-size:13px">${fmtN(r.completed)}</td>
    <td align="right" style="padding:9px 12px;font-family:'Courier New',monospace;font-size:12px">${fmtP(r.totalReserve)}</td>
    <td align="right" style="padding:9px 12px;font-family:'Courier New',monospace;font-size:12px">${fmtP(r.totalPaid)}</td>
  </tr>`).join('') || `<tr><td colspan="6" style="padding:14px;text-align:center;color:${BRAND.textFaint};font-size:12px">No claims in scope</td></tr>`;

  const reserveRows = (topReserve || []).slice(0, 10).map((c, i) => `<tr ${i % 2 === 1 ? `bgcolor="${BRAND.navyPale}" style="background-color:${BRAND.navyPale}"` : ''}>
    <td style="padding:8px 10px;color:${BRAND.textFaint};font-size:12px;width:24px">${i + 1}</td>
    <td style="padding:8px 10px;font-family:'Courier New',monospace;font-weight:700;font-size:12px">${escHtml(c.claimNumber || '-')}</td>
    <td style="padding:8px 10px;font-size:12px">${escTrim(c.clientName, 28)}</td>
    <td style="padding:8px 10px;font-size:12px;color:${BRAND.textMute}">${escHtml(c.claimType || '-')}</td>
    <td style="padding:8px 10px;font-size:11px"><span style="background-color:${c.daysLate > 0 ? BRAND.errBg : BRAND.okBg};color:${c.daysLate > 0 ? BRAND.err : BRAND.ok};padding:2px 8px;border-radius:3px;font-weight:600">${escHtml(c.currentStage || '-')}</span></td>
    <td align="right" style="padding:8px 10px;font-family:'Courier New',monospace;font-weight:700;font-size:12px">${fmtP(c.reserveAmount)}</td>
  </tr>`).join('') || `<tr><td colspan="6" style="padding:14px;text-align:center;color:${BRAND.textFaint};font-size:12px">No claims with reserves</td></tr>`;

  const breachRows = (breaches || []).slice(0, 10).map((c, i) => `<tr ${i % 2 === 1 ? `bgcolor="${BRAND.errBg}" style="background-color:${BRAND.errBg}"` : ''}>
    <td style="padding:8px 10px;font-family:'Courier New',monospace;font-weight:700;font-size:12px">${escHtml(c.claimNumber || '-')}</td>
    <td style="padding:8px 10px;font-size:12px">${escTrim(c.clientName, 28)}</td>
    <td style="padding:8px 10px;font-size:12px;color:${BRAND.textMute}">${escHtml(c.currentStage || '-')}</td>
    <td align="right" style="padding:8px 10px;color:${BRAND.err};font-weight:700;font-size:12px">${fmtN(c.daysLate)} d</td>
    <td style="padding:8px 10px;font-size:12px">${escTrim(c.claimsHandler, 18)}</td>
  </tr>`).join('');

  const dailyRows = (dailyBreakdown || []).map((day, i) => `<tr ${i % 2 === 1 ? `bgcolor="${BRAND.navyPale}" style="background-color:${BRAND.navyPale}"` : ''}>
    <td style="padding:8px 12px;font-size:13px">${escHtml(day.day)}</td>
    <td align="right" style="padding:8px 12px;font-size:13px;font-weight:600">${fmtN(day.added)}</td>
    <td align="right" style="padding:8px 12px;font-size:13px;color:${BRAND.ok};font-weight:600">${fmtN(day.completed)}</td>
  </tr>`).join('');

  const wrapTable = inner => `<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border:1px solid ${BRAND.border};border-radius:6px;overflow:hidden">${inner}</table>`;

  const body = `
    <p style="margin:0 0 4px;color:${BRAND.textMute};font-size:13px">${escHtml(period.label)}</p>
    <p style="margin:0 0 6px">A consolidated view of claims activity for leadership review.</p>
    ${kpiGrid}

    ${_section('Breakdown by Claim Type', wrapTable(tableHead([
      {label:'Type'},{label:'Total',align:'right'},{label:'In Progress',align:'right'},
      {label:'Completed',align:'right'},{label:'Reserve',align:'right'},{label:'Paid',align:'right'},
    ]) + `<tbody>${typeRows}</tbody>`))}

    ${dailyBreakdown && dailyBreakdown.length ? _section('Daily Activity', wrapTable(tableHead([
      {label:'Day'},{label:'New Claims',align:'right'},{label:'Completed',align:'right'},
    ]) + `<tbody>${dailyRows}</tbody>`)) : ''}

    ${_section('Top 10 by Reserve Exposure', wrapTable(tableHead([
      {label:'#'},{label:'Claim'},{label:'Client'},{label:'Type'},{label:'Stage'},{label:'Reserve',align:'right'},
    ]) + `<tbody>${reserveRows}</tbody>`))}

    ${breaches && breaches.length
      ? _section('SLA Breaches — Action Required', wrapTable(tableHead([
          {label:'Claim'},{label:'Client'},{label:'Stage'},{label:'Days Late',align:'right'},{label:'Handler'},
        ]) + `<tbody>${breachRows}</tbody>`))
      : _calloutBox('success', '<strong>✓ No SLA breaches</strong> — all open claims are on schedule.')}
  `;

  const titleMap   = { daily: 'Daily Claims Report', monthly: 'Monthly Claims Report', weekly: 'Weekly Executive Report' };
  const eyebrowMap = { daily: 'Daily Summary',        monthly: 'Monthly Summary',        weekly: 'Executive Summary'   };
  const titleStr   = titleMap[periodType]   || titleMap.weekly;
  const eyebrowStr = eyebrowMap[periodType] || eyebrowMap.weekly;

  return renderEmail({
    title:     titleStr,
    eyebrow:   eyebrowStr,
    accent:    BRAND.orange,
    preheader: `${kpis.newClaims} new · ${kpis.completed} completed · ${kpis.breaches} SLA breaches`,
    bodyHtml:  body,
    ctaPrimary: baseUrl ? { label: 'Open Claims Tracker', url: baseUrl + '/' } : null,
    footerNote: `Generated ${new Date().toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })}. Configure recipients & schedule in Backdate Control → Weekly Executive Report.`,
    baseUrl,
  });
}

// Batch J — email shown to a tagged user who hasn't acknowledged within the
// priority window. Reminder body is intentionally short — purpose is just a
// nudge with a link back to the claim.
function commentMentionHtml(opts) {
  const claimNumber = escHtml(opts.claimNumber || '');
  const senderName  = escHtml(opts.senderName  || 'A teammate');
  const priority    = escHtml(opts.priority    || '');
  const body        = escHtml(opts.body        || '').replace(/\n/g, '<br>');
  const claimUrl    = opts.claimUrl || '';
  const priorityChip = priority
    ? `<span style="display:inline-block;background:${BRAND.orange};color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:3px;margin-left:6px">${priority.toUpperCase()}</span>`
    : '';
  const bodyHtml = `
    <p style="margin:0 0 12px;font-size:15px;color:${BRAND.navy}">
      <strong>${senderName}</strong> tagged you on claim <strong>${claimNumber}</strong>${priorityChip}.
    </p>
    <div style="margin:14px 0;padding:14px 16px;background:#f7f8fb;border-left:3px solid ${BRAND.navy};border-radius:4px;font-size:14px;color:#1a1a1a;line-height:1.5">
      ${body}
    </div>
    <p style="margin:14px 0 0;font-size:13px;color:#666">
      You're receiving this because the mention was not opened within the priority window. Open the claim to see the full thread and reply.
    </p>`;
  return renderEmail({
    eyebrow:    `Claim Comment Tag · ${priority || 'reminder'}`,
    preheader:  `${senderName} tagged you on claim ${claimNumber}`,
    bodyHtml,
    ctaPrimary: claimUrl ? { label: 'Open Claim', url: claimUrl } : null,
    baseUrl:    opts.baseUrl,
  });
}

// G4 — Daily summary of claims whose Graphite sync hit the 10-attempt
// cap and need manual attention. One email per day to admin/superadmin.
function graphiteFailedSummaryHtml(opts) {
  const items    = Array.isArray(opts.items) ? opts.items : [];
  const oldCount = parseInt(opts.oldCount, 10) || 0;
  const baseUrl  = opts.baseUrl;

  const rows = items.map(c => `
    <tr>
      <td style="padding:8px 10px;border-bottom:1px solid #eef0f5;font-size:13px;color:${BRAND.navy};font-weight:600">${escHtml(c.localClaimNumber || c.id)}</td>
      <td style="padding:8px 10px;border-bottom:1px solid #eef0f5;font-size:13px;color:#1a1a1a">${escHtml(c.clientName || '—')}</td>
      <td style="padding:8px 10px;border-bottom:1px solid #eef0f5;font-size:13px;color:#1a1a1a">${escHtml(c.policyNumber || '—')}</td>
      <td style="padding:8px 10px;border-bottom:1px solid #eef0f5;font-size:13px;color:#a4131e">${escHtml(String(c.lastError || '').slice(0, 140))}</td>
    </tr>`).join('');

  const oldNote = oldCount
    ? `<p style="margin:14px 0 0;font-size:13px;color:#555;background:#f7f8fb;border-left:3px solid ${BRAND.navy};padding:8px 12px;border-radius:3px">
        <strong>${oldCount} previously reported failure${oldCount === 1 ? '' : 's'}</strong> still unresolved — already flagged in an earlier alert. Open Claims Tracker to view and action them.
       </p>`
    : '';

  const bodyHtml = `
    <p style="margin:0 0 12px;font-size:15px;color:${BRAND.navy}">
      <strong>${items.length} new claim${items.length === 1 ? '' : 's'}</strong> failed to sync with Graphite after 10 retry attempts${oldCount ? ` — ${oldCount} additional from previous alerts still pending` : ''}.
    </p>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin:14px 0">
      <thead>
        <tr>
          <th style="text-align:left;padding:8px 10px;background:${BRAND.navy};color:#fff;font-size:11px;text-transform:uppercase;letter-spacing:.04em">Local #</th>
          <th style="text-align:left;padding:8px 10px;background:${BRAND.navy};color:#fff;font-size:11px;text-transform:uppercase;letter-spacing:.04em">Client</th>
          <th style="text-align:left;padding:8px 10px;background:${BRAND.navy};color:#fff;font-size:11px;text-transform:uppercase;letter-spacing:.04em">Policy</th>
          <th style="text-align:left;padding:8px 10px;background:${BRAND.navy};color:#fff;font-size:11px;text-transform:uppercase;letter-spacing:.04em">Last Error</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>
    <p style="margin:14px 0 0;font-size:13px;color:#666">
      Open each claim in Claims Tracker and click <strong>Retry now</strong> in the red banner. If retries keep failing, check the Graphite server logs.
    </p>
    ${oldNote}`;

  return renderEmail({
    eyebrow:    'Graphite Sync — New Failures',
    preheader:  `${items.length} new Graphite sync failure${items.length === 1 ? '' : 's'}${oldCount ? ` · ${oldCount} previously reported still pending` : ''}`,
    bodyHtml,
    ctaPrimary: baseUrl ? { label: 'Open Claims Tracker', url: baseUrl } : null,
    baseUrl:    opts.baseUrl,
  });
}

module.exports = {
  sendEmail,
  sendInternalReport,
  backdateAlertHtml,
  backdateRequestHtml,
  backdateDecisionHtml,
  pendingUserAlertHtml,
  pendingDigestHtml,
  userApprovalHtml,
  weeklyReportHtml,
  commentMentionHtml,
  graphiteFailedSummaryHtml,
  // exposed for tests / preview tooling
  renderEmail,
  BRAND,
};
