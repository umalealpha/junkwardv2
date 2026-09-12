// Outbound notification orchestration.
//
// One function in: send({ claimId, trigger, channel, recipient,
//                        templateData, providerHint }).
// All policy lives here: pilot-mode gate, dedup window, per-claim hard
// cap, template render, channel→adapter routing, notification_log
// write, cost accumulation. Adapters (infobip.js, mailer.js, soon
// metaWhatsapp.js) are dumb pass-throughs.

const crypto = require('crypto');
const { sql } = require('../db');
const infobip = require('./infobip');
const mailer  = require('./mailer');

// Per-claim hard cap (excluding OTP messages). Prevents runaway logic
// from blowing a phone-bill ceiling.
const PER_CLAIM_CAP = 8;

// Triggers eligible for the OTP-style fast path (not counted toward
// the per-claim cap, no dedup since each OTP is a one-shot need).
const OTP_LIKE_TRIGGERS = new Set(['otp_request']);

// ── Templates ────────────────────────────────────────────────
// Plain-text templates. Keep SMS ones under 160 chars where possible
// to stay in a single segment ≈ P0.30. {placeholders} are filled from
// the templateData arg.
const TEMPLATES = {
  claim_registered: {
    sms:      'AlphaDirect: Claim {claimNumber} has been registered. Track status: {link}',
    whatsapp: 'AlphaDirect: Claim {claimNumber} has been registered. Track status: {link}',
    email:    {
      subject: 'Your Alpha Direct claim has been registered',
      text:    'Hi {contactName},\n\nYour claim {claimNumber} ({claimType}) has been registered with Alpha Direct.\n\nTrack the status anytime: {link}\n\nThe link will require a one-time code sent to this phone/email for security.\n\n— Alpha Direct Claims Team',
    },
  },
  assessor_allocated: {
    sms:      'AlphaDirect: An assessor has been allocated to claim {claimNumber}. They will be in touch. Status: {link}',
    whatsapp: 'AlphaDirect: An assessor has been allocated to claim {claimNumber}. They will be in touch. Status: {link}',
    email:    {
      subject: 'Assessor allocated for claim {claimNumber}',
      text:    'Hi {contactName},\n\nAn assessor has been allocated to your claim {claimNumber}. They will be in touch directly.\n\nStatus: {link}\n\n— Alpha Direct Claims Team',
    },
  },
  decision_approved: {
    sms:      'AlphaDirect: Claim {claimNumber} has been approved. Next steps: {link}',
    whatsapp: 'AlphaDirect: Claim {claimNumber} has been approved. Next steps: {link}',
    email:    {
      subject: 'Your Alpha Direct claim has been approved',
      text:    'Hi {contactName},\n\nGood news — claim {claimNumber} has been approved.\n\nNext steps and details: {link}\n\n— Alpha Direct Claims Team',
    },
  },
  decision_repudiated: {
    // Neutral wording on purpose; handler should follow up by call.
    sms:      'AlphaDirect: An update is available on claim {claimNumber}. Please open: {link}',
    whatsapp: 'AlphaDirect: An update is available on claim {claimNumber}. Please open: {link}',
    email:    {
      subject: 'Update on your Alpha Direct claim',
      text:    'Hi {contactName},\n\nThere is an update on claim {claimNumber}. Please open: {link}\n\nYour claims handler will be in touch to discuss.\n\n— Alpha Direct Claims Team',
    },
  },
  payment_initiated: {
    sms:      'AlphaDirect: A payment has been initiated for claim {claimNumber}. Details: {link}',
    whatsapp: 'AlphaDirect: A payment has been initiated for claim {claimNumber}. Details: {link}',
    email:    {
      subject: 'Payment initiated for claim {claimNumber}',
      text:    'Hi {contactName},\n\nA payment has been initiated for your claim {claimNumber}.\n\nDetails: {link}\n\n— Alpha Direct Claims Team',
    },
  },
  claim_closed: {
    sms:      'AlphaDirect: Claim {claimNumber} has been closed. Thank you. Reference: {claimNumber}.',
    whatsapp: 'AlphaDirect: Claim {claimNumber} has been closed. Thank you. Reference: {claimNumber}.',
    email:    {
      subject: 'Claim {claimNumber} closed',
      text:    'Hi {contactName},\n\nYour claim {claimNumber} has been closed.\n\nThank you for choosing Alpha Direct.\n\n— Alpha Direct Claims Team',
    },
  },
  // ── Lifecycle stage messages (CFO 2026-07-09) — customer gets one at each
  //    step: assessment booked → assessment done → PO to supplier → AoL to sign
  //    → claim paid. Written to be professional, plain and reassuring.
  assessment_scheduled: {
    sms:      'AlphaDirect: An assessment for claim {claimNumber} is booked for {assessmentDate}. Track: {link}',
    whatsapp: 'Alpha Direct: Good news — the assessment for your claim {claimNumber} is booked for {assessmentDate}. Our assessor will inspect the damage and report back, and we will keep you updated. Track your claim: {link}',
    email:    {
      subject: 'Assessment booked for claim {claimNumber}',
      text:    'Hi {contactName},\n\nThe assessment for your claim {claimNumber} is booked for {assessmentDate}. Our assessor will inspect the damage and prepare a report.\n\nWe will update you as soon as it is done.\n\nTrack your claim anytime: {link}\n\n— Alpha Direct Claims Team',
    },
  },
  assessment_completed: {
    sms:      'AlphaDirect: The assessment for claim {claimNumber} is complete. We are reviewing it now. Track: {link}',
    whatsapp: 'Alpha Direct: The assessment for your claim {claimNumber} is complete. We are now reviewing the report and will be in touch with the next step shortly. Track your claim: {link}',
    email:    {
      subject: 'Assessment complete for claim {claimNumber}',
      text:    'Hi {contactName},\n\nThe assessment for your claim {claimNumber} is complete. We are reviewing the report and will contact you with the next step shortly.\n\nTrack your claim anytime: {link}\n\n— Alpha Direct Claims Team',
    },
  },
  po_sent_supplier: {
    sms:      'AlphaDirect: A repair order for claim {claimNumber} has been sent to {supplierName}. They will contact you. Track: {link}',
    whatsapp: 'Alpha Direct: We have sent a repair order for your claim {claimNumber} to {supplierName}. They will contact you directly to arrange the repair. Track your claim: {link}',
    email:    {
      subject: 'Repair order issued for claim {claimNumber}',
      text:    'Hi {contactName},\n\nWe have issued a repair order to {supplierName} for your claim {claimNumber}. They will be in touch to arrange the repair.\n\nTrack your claim anytime: {link}\n\n— Alpha Direct Claims Team',
    },
  },
  aol_sent_signature: {
    sms:      'AlphaDirect: An Agreement of Loss for claim {claimNumber} has been emailed to you to sign. Please check your email. Track: {link}',
    whatsapp: 'Alpha Direct: For your claim {claimNumber}, we have emailed you an Agreement of Loss to review and sign. Once you sign and return it, we can proceed with your settlement. Track your claim: {link}',
    email:    {
      subject: 'Please sign the Agreement of Loss — claim {claimNumber}',
      text:    'Hi {contactName},\n\nWe have prepared an Agreement of Loss for your claim {claimNumber}. Please review, sign, and return it so we can proceed with your settlement.\n\nTrack your claim anytime: {link}\n\n— Alpha Direct Claims Team',
    },
  },
  claim_paid: {
    sms:      'AlphaDirect: Your claim {claimNumber} has been settled and payment released. Thank you. Ref: {claimNumber}.',
    whatsapp: 'Alpha Direct: Your claim {claimNumber} has been settled and payment of {amount} has been released to {payeeName}. Thank you for insuring with Alpha Direct.',
    email:    {
      subject: 'Your claim {claimNumber} has been settled',
      text:    'Hi {contactName},\n\nGood news — your claim {claimNumber} has been settled and payment of {amount} has been released to {payeeName}.\n\nThank you for insuring with Alpha Direct.\n\n— Alpha Direct Claims Team',
    },
  },
  otp_request: {
    // Purpose-specific so recipients can distinguish from other Alpha
    // Direct OTPs (policy portal, payments, etc.) Kept under 160 chars
    // to stay in one SMS segment.
    sms:      'Alpha Direct Claims Tracker: code {otp} to view your claim status. Valid 5 min. Do not share.',
    whatsapp: 'Alpha Direct Claims Tracker: code {otp} to view your claim status. Valid 5 min. Do not share.',
    email:    {
      subject: 'Alpha Direct Claims Tracker — your code: {otp}',
      text:    'Your Alpha Direct Claims Tracker verification code is {otp}.\n\nUse this code to open your claim status page. It expires in 5 minutes.\n\nDo not share this code with anyone. Alpha Direct staff will never ask for it.\n\nIf you did not request access to a claim, please ignore this email.',
    },
  },
};

function _render(tpl, data) {
  if (!tpl) return '';
  let s = String(tpl).replace(/\{(\w+)\}/g, (_, k) => {
    return data && data[k] != null ? String(data[k]) : '';
  });
  // Clean up trailing label-with-no-value: e.g. "...in touch. Status: "
  // (happens on stage-transition SMS when no active link is in scope).
  s = s.replace(/\s+(Status|Track|Details|Next steps in|Status link|View status|Open):\s*$/i, '.');
  // Collapse repeated spaces left by empty substitutions.
  s = s.replace(/  +/g, ' ').trim();
  return s;
}

function _hash(s) {
  return crypto.createHash('sha256').update(String(s || '')).digest('hex').slice(0, 32);
}

// Read the master-data notification mode. Defaults to pilot mode if
// unset — fail-safe (no real claimants get messaged on fresh deploy).
function notificationMode() {
  try {
    const row = sql.getMasterCategory.get('notificationSettings');
    if (row && row.items) {
      const arr = JSON.parse(row.items);
      const m = (arr || []).find(s => String(s).startsWith('mode|'));
      if (m) {
        const v = String(m).split('|')[1] || '';
        if (['disabled','pilot_admin_only','live'].includes(v)) return v;
      }
    }
  } catch (_) {}
  return 'pilot_admin_only';
}

function _allowlist() {
  try {
    const row = sql.getMasterCategory.get('notificationPilotNumbers');
    if (row && row.items) {
      const arr = JSON.parse(row.items);
      return (arr || []).map(s => {
        const [name, phone, email] = String(s).split('|').map(x => (x || '').trim());
        return { name, phone, email };
      });
    }
  } catch (_) {}
  return [];
}

function isAllowlisted(recipient) {
  if (!recipient) return false;
  const r = String(recipient).trim();
  const ph = r.replace(/[^0-9+]/g, '');
  const em = r.toLowerCase();
  return _allowlist().some(a =>
    (a.phone && ph && a.phone.replace(/[^0-9+]/g,'') === ph) ||
    (a.email && em && a.email.toLowerCase() === em)
  );
}

// Main entry point.
// Returns { ok, status, suppressed?, code?, providerMsgId?, logId?, costUnits? }
async function send({ claimId, trigger, channel, recipient, templateData, providerHint }) {
  trigger      = String(trigger || '');
  channel      = String(channel || '').toLowerCase();
  recipient    = String(recipient || '').trim();
  templateData = templateData || {};

  if (!trigger || !channel || !recipient) {
    return { ok: false, code: 'missing_args' };
  }
  if (!['sms','whatsapp','email'].includes(channel)) {
    return { ok: false, code: 'bad_channel' };
  }

  // ONE real OFF switch — refuse every outbound notification when live arms are
  // off, before any provider is touched (even if keys are present).
  if (require('./armed').refuseIfOff('notify:' + trigger, { channel, recipient })) {
    _log({ claimId, channel, provider: '', trigger, recipient, status: 'blocked', error: 'live_arms_off' });
    return { ok: false, code: 'live_arms_off', blocked: true };
  }

  // Pilot-mode gate
  const mode = notificationMode();
  if (mode === 'disabled') {
    _log({ claimId, channel, provider: '', trigger, recipient, status: 'suppressed', error: 'mode_disabled' });
    return { ok: false, code: 'mode_disabled', suppressed: true };
  }
  if (mode === 'pilot_admin_only' && !isAllowlisted(recipient)) {
    _log({ claimId, channel, provider: '', trigger, recipient, status: 'suppressed', error: 'pilot_not_allowlisted' });
    return { ok: true, suppressed: true, code: 'pilot_not_allowlisted' };
  }

  // Dedup (skip for OTP-style triggers)
  if (!OTP_LIKE_TRIGGERS.has(trigger)) {
    try {
      const recent = sql.countRecentSimilar.get({ trigger_key: trigger, recipient });
      if (recent && recent.n > 0) {
        _log({ claimId, channel, provider: '', trigger, recipient, status: 'suppressed', error: 'duplicate_within_1h' });
        return { ok: true, suppressed: true, code: 'duplicate_within_1h' };
      }
    } catch (_) {}
  }

  // Per-claim hard cap (skip for OTPs and when claimId is absent)
  if (claimId && !OTP_LIKE_TRIGGERS.has(trigger)) {
    try {
      const cnt = sql.countNotificationsForClaim.get(String(claimId));
      if (cnt && cnt.n >= PER_CLAIM_CAP) {
        _log({ claimId, channel, provider: '', trigger, recipient, status: 'suppressed', error: 'per_claim_cap_reached' });
        return { ok: false, code: 'per_claim_cap_reached', suppressed: true };
      }
    } catch (_) {}
  }

  // Render template
  const tpl = TEMPLATES[trigger] && TEMPLATES[trigger][channel];
  if (!tpl) {
    return { ok: false, code: 'no_template' };
  }

  let body, subject = '';
  if (channel === 'email') {
    body    = _render(tpl.text, templateData);
    subject = _render(tpl.subject, templateData);
  } else {
    body = _render(tpl, templateData);
  }

  // Dispatch by channel → adapter
  if (channel === 'sms') {
    if (!infobip.isConfigured()) {
      _log({ claimId, channel, provider: 'infobip', trigger, recipient, status: 'failed', error: 'infobip_not_configured' });
      return { ok: false, code: 'infobip_not_configured' };
    }
    const r = await infobip.sendSms({ to: recipient, text: body });
    const logId = _log({
      claimId, channel, provider: 'infobip', trigger, recipient,
      templateKey:   trigger,
      payloadHash:   _hash(body),
      providerMsgId: r.messageId || '',
      status:        r.status     || (r.ok ? 'sent' : 'failed'),
      costUnits:     r.costUnits  || 0,
      error:         r.error      || '',
    });
    return { ...r, logId };
  }

  if (channel === 'whatsapp') {
    // Phase 3 — Meta Cloud API not yet wired. Suppress gracefully.
    _log({ claimId, channel, provider: 'meta', trigger, recipient, status: 'suppressed', error: 'whatsapp_phase3_not_built' });
    return { ok: false, code: 'whatsapp_not_implemented', suppressed: true };
  }

  if (channel === 'email') {
    // CFO 2026-07-21: log the mailer's REAL result — 'queued'/'blocked'/'skipped',
    // never an unconditional 'sent' for an email that may never have left.
    let r;
    try {
      r = mailer.sendEmail({
        to:      recipient,
        subject,
        text:    body,
        tag:     'claim-notify-' + trigger,
      }) || { ok: false, skipped: true, reason: 'no_result' };
    } catch (e) {
      _log({ claimId, channel, provider: 'mailgun', trigger, recipient, status: 'failed', error: 'send_threw' });
      return { ok: false, code: 'send_threw' };
    }
    const status = r.ok ? (r.status || 'queued') : (r.blocked ? 'blocked' : 'skipped');
    const logId = _log({
      claimId, channel, provider: 'mailgun', trigger, recipient,
      templateKey: trigger, payloadHash: _hash(body),
      status, costUnits: 0, error: r.reason || '',
    });
    return { ok: !!r.ok, status, logId };
  }

  return { ok: false, code: 'unreachable' };
}

function _log(opts) {
  try {
    const r = sql.insertNotification.run({
      claim_id:        String(opts.claimId || ''),
      channel:         String(opts.channel || ''),
      provider:        String(opts.provider || ''),
      trigger_key:     String(opts.trigger || ''),
      recipient:       String(opts.recipient || ''),
      template_key:    String(opts.templateKey || opts.trigger || ''),
      payload_hash:    String(opts.payloadHash || ''),
      provider_msg_id: String(opts.providerMsgId || ''),
      status:          String(opts.status || 'queued'),
      cost_units:      Number(opts.costUnits || 0),
      error:           String(opts.error || '').slice(0, 500),
    });
    return r.lastInsertRowid;
  } catch (e) {
    console.warn('[Notifier] log write failed:', e.message);
    return null;
  }
}

// Provider webhook → reconcile delivery status against notification_log.
function reconcileDeliveryReport(provider, reports) {
  if (!Array.isArray(reports)) return 0;
  let updated = 0;
  for (const r of reports) {
    try {
      const result = sql.updateNotificationByProviderId.run({
        provider,
        provider_msg_id: String(r.messageId || ''),
        status:          String(r.status || 'sent'),
        error:           String(r.error || ''),
        delivered_at:    r.deliveredAt || null,
      });
      if (result.changes > 0) updated++;
    } catch (_) {}
  }
  return updated;
}

module.exports = {
  send,
  notificationMode,
  isAllowlisted,
  reconcileDeliveryReport,
  TEMPLATES,
  PER_CLAIM_CAP,
};
