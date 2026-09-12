'use strict';
/**
 * preIntimation.js — the SOFT-LAUNCH deliverable for the non-payment
 * collections rule (CFO 2026-07-18).
 *
 * The CFO rule is: 2 consecutive unpaid/failed months → DEACTIVATED (suspended)
 * at the end of the 2nd month → overdue email + 15 calendar days grace → still
 * unpaid → CANCELLED + notice.
 *
 * SOFT LAUNCH — arms stay OFF. Instead of auto-emailing customers or
 * auto-cancelling policies, Alpha Brain produces a PRE-INTIMATION dataset for
 * the Finance team to review, and RENDERS (does not send) the respectful
 * overdue email so a human can eyeball it first.
 *
 * Everything here is a PURE function of the affected-policy array — it renders
 * and returns content. It NEVER transmits. The one send-shaped helper
 * (sendPreIntimation) is gated by armed.refuseIfOff and no-ops when arms are
 * off; its default is to return the rendered content, never to transmit.
 *
 * GRA-0203 note: banks sometimes silently change a client's account, so a
 * "failed" signal can be wrong. Rows whose signalConfidence is 'uncertain' are
 * SEPARATED into their own "needs verification" bucket — acting on them blindly
 * risks suspending clients who are actually paying.
 *
 * DPA: customerName / agent are INTERNAL ONLY. Nothing here sends customer PII
 * to any external model or third party.
 */

const { refuseIfOff } = require('./armed');

// The stages we group by (from the canonical affected-policy shape).
const STAGES = ['deactivate_candidate', 'grace', 'cancel_candidate'];

// Human-facing status label per stage (the report's "status" column).
const STAGE_STATUS = {
  deactivate_candidate: 'DEACTIVATED',
  grace: 'DEACTIVATED (in grace period)',
  cancel_candidate: 'CANCELLED',
};

// ── small pure helpers ───────────────────────────────────────────
function escHtml(s) {
  if (s == null) return '';
  return String(s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// BWP money for display (HTML/email). Raw numbers stay raw in datasets.
function money(n) {
  const v = Number(n);
  if (!isFinite(v)) return 'BWP 0.00';
  return 'BWP ' + v.toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * toRow(policy) → the report-column view of one affected policy.
 * Columns are exactly the CFO's set, plus signalConfidence + stage so the UI
 * can bucket + flag them. Never throws on missing fields.
 */
function toRow(policy = {}) {
  const stage = policy.stage;
  return {
    policyNumber: policy.policyNumber || '',
    client: policy.customerName || '',
    product: policy.product || '',
    agent: policy.agent || '',
    amountOverdue: Number(policy.amountOverdue) || 0,
    daysOverdue: Number(policy.daysOverdue) || 0,
    status: STAGE_STATUS[stage] || 'UNKNOWN',
    signalConfidence: policy.signalConfidence === 'uncertain' ? 'uncertain' : 'clean',
    stage: stage || null,
    channel: policy.channel || null,
    monthsUnpaid: Number(policy.monthsUnpaid) || 0,
  };
}

/**
 * buildPreIntimation(affected) → a Finance-facing review dataset.
 *
 *   {
 *     generatedFor: 'Finance',
 *     groups: { deactivate_candidate:[row…], grace:[row…], cancel_candidate:[row…] },
 *     needsVerification: [row…],   // signalConfidence 'uncertain' — visually distinct
 *     counts: { deactivate_candidate, grace, cancel_candidate, needsVerification, total },
 *   }
 *
 * CLEAN rows are grouped by stage. UNCERTAIN rows are pulled OUT of the stage
 * groups entirely and placed in needsVerification (they keep their .stage so the
 * reviewer sees what would have happened) — so nobody suspends a paying client
 * off a bad "failed" signal.
 */
function buildPreIntimation(affected) {
  const rows = (Array.isArray(affected) ? affected : []).map(toRow);

  const groups = { deactivate_candidate: [], grace: [], cancel_candidate: [] };
  const needsVerification = [];

  for (const row of rows) {
    if (row.signalConfidence === 'uncertain') {
      needsVerification.push(row);
      continue; // never auto-grouped for action
    }
    if (groups[row.stage]) groups[row.stage].push(row);
  }

  const counts = {
    deactivate_candidate: groups.deactivate_candidate.length,
    grace: groups.grace.length,
    cancel_candidate: groups.cancel_candidate.length,
    needsVerification: needsVerification.length,
    total: rows.length,
  };

  return { generatedFor: 'Finance', groups, needsVerification, counts };
}

/**
 * renderOverdueEmail(policy) → the respectful customer overdue email.
 * Returns { subject, text, html }. RENDERS ONLY — does not send.
 *
 * Per the CFO wording: state payment is overdue and ask for prompt payment,
 * thank them for being our client, and ask them to contact their agent (whose
 * name is pulled from the policy listing).
 */
function renderOverdueEmail(policy = {}) {
  const client = policy.customerName || 'Valued Client';
  const agent = policy.agent || 'your Alpha Direct agent';
  const policyNumber = policy.policyNumber || '';
  const product = policy.product || 'your policy';
  const amount = money(policy.amountOverdue);
  const days = Number(policy.daysOverdue) || 0;

  const subject = `Payment overdue on policy ${policyNumber} — Alpha Direct Insurance`;

  const text = [
    `Dear ${client},`,
    ``,
    `Thank you for being a valued client of Alpha Direct Insurance.`,
    ``,
    `Our records show that a premium payment on your policy ${policyNumber} (${product}) is`,
    `currently overdue${days ? ` by ${days} day${days === 1 ? '' : 's'}` : ''}. The outstanding amount is ${amount}.`,
    ``,
    `We kindly ask that you arrange payment at your earliest convenience so that your`,
    `cover continues without interruption.`,
    ``,
    `If you have any questions, or if you have already made this payment, please contact`,
    `your agent, ${agent}, who will be glad to assist you.`,
    ``,
    `Thank you again for choosing Alpha Direct.`,
    ``,
    `Regards,`,
    `Alpha Direct Insurance`,
  ].join('\n');

  // Simple branded HTML. Header/footer colour cells carry a bgcolor ATTRIBUTE
  // (not only CSS) so the fills survive an Outlook paste. Navy #010066 /
  // orange #FE7F0C, Book Antiqua headings (Alpha Direct brand).
  const html = `<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>${escHtml(subject)}</title></head>
<body style="margin:0;padding:0;background-color:#F8F8F8;font-family:'Inter',Arial,sans-serif;color:#202020">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F8F8F8" style="background-color:#F8F8F8">
    <tr><td align="center" style="padding:28px 12px">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:100%;background-color:#FFFFFF;border-radius:8px;overflow:hidden">
        <tr><td bgcolor="#010066" style="background-color:#010066;padding:22px 28px">
          <div style="font-family:'Book Antiqua','Palatino Linotype',Georgia,serif;font-size:20px;font-weight:700;color:#FFFFFF">Alpha Direct Insurance</div>
          <div style="font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:#FE7F0C;font-weight:700;margin-top:3px">Premium Payment Reminder</div>
        </td></tr>
        <tr><td height="3" bgcolor="#FE7F0C" style="height:3px;background-color:#FE7F0C;line-height:3px;font-size:0">&nbsp;</td></tr>
        <tr><td style="padding:26px 28px 8px;font-size:14px;line-height:1.6;color:#202020">
          <p style="margin:0 0 14px">Dear <strong>${escHtml(client)}</strong>,</p>
          <p style="margin:0 0 14px">Thank you for being a valued client of Alpha Direct Insurance.</p>
          <p style="margin:0 0 14px">Our records show that a premium payment on your policy
            <strong>${escHtml(policyNumber)}</strong> (${escHtml(product)}) is currently overdue${days ? ` by <strong>${days} day${days === 1 ? '' : 's'}</strong>` : ''}.
            The outstanding amount is <strong>${escHtml(money(policy.amountOverdue))}</strong>.</p>
          <p style="margin:0 0 14px">We kindly ask that you arrange payment at your earliest convenience so that your cover continues without interruption.</p>
          <p style="margin:0 0 14px">If you have any questions, or if you have already made this payment, please contact your agent,
            <strong>${escHtml(agent)}</strong>, who will be glad to assist you.</p>
          <p style="margin:0 0 6px">Thank you again for choosing Alpha Direct.</p>
        </td></tr>
        <tr><td bgcolor="#010066" style="background-color:#010066;padding:16px 28px">
          <div style="font-family:'Book Antiqua','Palatino Linotype',Georgia,serif;font-size:13px;font-weight:700;color:#FFFFFF">Alpha Direct Insurance</div>
          <div style="font-size:11px;color:rgba(255,255,255,.6);margin-top:2px">Gaborone, Botswana</div>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>`;

  return { subject, text, html };
}

/**
 * sendPreIntimation(policy, deps) — send-SHAPED helper, fail-closed.
 *
 * SOFT LAUNCH: arms are OFF, so this NEVER transmits. It renders the overdue
 * email and returns it. The refuseIfOff gate makes any future transmit path
 * fail-closed: when arms are off it no-ops (zero network calls) and returns the
 * rendered content with blocked:true. Even when armed it only transmits through
 * an INJECTED deps.transmit (there is no built-in network call here), keeping
 * the module pure and testable.
 */
function sendPreIntimation(policy = {}, deps = {}) {
  const rendered = renderOverdueEmail(policy);

  if (refuseIfOff('preintimation:overdue-email', { policy: policy.policyNumber || null })) {
    return { blocked: true, sent: false, rendered };
  }

  const transmit = deps.transmit;
  if (typeof transmit === 'function') {
    transmit({ to: deps.to, subject: rendered.subject, text: rendered.text, html: rendered.html });
    return { blocked: false, sent: true, rendered };
  }
  // Default even when armed: return content, do not transmit.
  return { blocked: false, sent: false, rendered };
}

module.exports = {
  buildPreIntimation,
  renderOverdueEmail,
  sendPreIntimation,
  toRow,
  STAGES,
  STAGE_STATUS,
};
