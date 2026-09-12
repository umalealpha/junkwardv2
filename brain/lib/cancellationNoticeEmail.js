'use strict';
/**
 * cancellationNoticeEmail.js — the CFO 5-day cancellation-notice oversight email.
 *
 * CFO 2026-08-31 ("Alpha Brain — what is still pending"): before Alpha Brain
 * auto-cancels ANY policy, a 5-day warning email must go to the CFO listing the
 * policies about to be cancelled, so the CFO can say STOP or CONTINUE. This is the
 * human checkpoint that gates the first automatic cancellations (15 Sep).
 *
 * WHAT IT LISTS — the "notice-due" set straight from the collections clock:
 * policies in grace / past-grace whose 5-day cancellation notice has NOT yet been
 * served and whose notice-due date (collectionsClock.cancelNoticeDueAt = grace
 * day 10) has arrived. These are exactly the policies within ~5 days of
 * cancellation. Nothing is invented here — it reads the clock's own output.
 *
 * INTERNAL OVERSIGHT ONLY — sent to our own CFO/finance group via
 * mailer.sendInternalReport (gated by BRAIN_INTERNAL_REPORTS + an internal-domain
 * check), so it works in WATCH mode with live arms OFF: the CFO must see and
 * approve the list BEFORE act-mode is ever enabled. It never contacts a customer
 * and never writes, cancels or debits anything (arms-off safe by construction).
 *
 * DPA (CFO 2026-07-21): no customer name leaves via the mail relay — each row is
 * policy number + product + amounts + dates + signal confidence only; the
 * reviewer looks the client up by policy number inside Omni.
 */

const mailer = require('./mailer');
const config = require('../config');

// Recipients come from settings (env BRAIN_CANCEL_NOTICE_RECIPIENTS), never
// hardcoded — mirrors the high-arrears alert pattern (CFO 2026-07-13).
function recipients() {
  return config.cancelNoticeRecipients || [];
}

const _iso = (v) => {
  if (v == null) return null;
  const s = String(v).trim();
  return s ? s.slice(0, 10) : null;
};

/**
 * buildDueList(affected, asOf) — PURE. From the classified affected policies
 * (collectionsClock output), return the ones whose 5-day cancellation notice is
 * DUE NOW and NOT yet served:
 *   - stage is 'grace' or 'cancel_candidate'
 *   - no notice served yet (cancelNoticeIssuedAt is null/empty)
 *   - the notice-due date has arrived (asOf >= cancelNoticeDueAt)
 * Sorted most-urgent first: soonest grace end (cancel date), then biggest amount.
 */
function buildDueList(affected = [], asOf) {
  const today = _iso(asOf) || new Date().toISOString().slice(0, 10);
  const due = (affected || []).filter((p) => {
    if (!p) return false;
    if (p.stage !== 'grace' && p.stage !== 'cancel_candidate') return false;
    if (p.cancelNoticeIssuedAt) return false;           // notice already served
    const dueAt = _iso(p.cancelNoticeDueAt);
    return !!dueAt && today >= dueAt;                    // notice-due date reached
  });
  return due.slice().sort((a, b) => {
    const ga = _iso(a.graceEndsAt) || '9999-12-31';
    const gb = _iso(b.graceEndsAt) || '9999-12-31';
    if (ga !== gb) return ga < gb ? -1 : 1;             // soonest cancel first
    return (Number(b.amountOverdue) || 0) - (Number(a.amountOverdue) || 0);
  });
}

const _esc = (s) => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;');
const _money = (n) => 'BWP ' + (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function _rows(list) {
  return list.map((p, i) => {
    const uncertain = p.signalConfidence !== 'clean';
    const cells = [
      _esc(p.policyNumber || '—'),
      _esc(p.product || p.billingType || '—'),
      String(p.monthsUnpaid == null ? '—' : p.monthsUnpaid),
      _money(p.amountOverdue),
      _esc(p.graceEndsAt || '—'),
      uncertain ? '⚠ verify' : 'clean',
    ];
    return `<tr${i % 2 ? ' bgcolor="#F8F8F8"' : ''}>`
      + cells.map((c, j) =>
        `<td style="padding:7px 10px;font-size:12px;${(j === 2 || j === 3) ? 'text-align:right;' : ''}`
        + `${(j === 5 && uncertain) ? 'color:#B25000;font-weight:600;' : ''}">${c}</td>`).join('')
      + '</tr>';
  }).join('');
}

function _bodyHtml(list) {
  const total = list.reduce((s, p) => s + (Number(p.amountOverdue) || 0), 0);
  const uncertainN = list.filter((p) => p.signalConfidence !== 'clean').length;
  const head = ['Policy', 'Product', 'Months unpaid', 'Amount overdue', 'Cancels on', 'Signal']
    .map((h, j) => `<td style="padding:7px 10px;color:#fff;font-weight:bold;${(j === 2 || j === 3) ? 'text-align:right;' : ''}">${h}</td>`)
    .join('');
  return `
    <p style="margin:0 0 12px">The following <strong>${list.length} ${list.length === 1 ? 'policy is' : 'policies are'} within 5 days of automatic cancellation</strong>
    (2+ consecutive months unpaid, past the 15-day grace, no cancellation notice served yet). Please reply
    <strong>STOP</strong> to hold, or <strong>CONTINUE</strong> to let the cancellations proceed on their grace-end date.</p>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;font-size:12px;line-height:1.5">
      <tr bgcolor="#010066">${head}</tr>
      ${_rows(list)}
    </table>
    <p style="margin:14px 0 0;font-size:12px;color:#6B7280">Total overdue on this list: <strong>${_money(total)}</strong>.
    ${uncertainN ? `${uncertainN} row(s) flagged <span style="color:#B25000">⚠ verify</span> — a payment signal is uncertain (possible reconciliation gap); confirm before cancelling. ` : ''}
    No customer names are included by design (data-protection) — look the client up by policy number in Omni.
    Nothing has been cancelled: Alpha Brain is watch-only and will not cancel while this notice stands unactioned.</p>`;
}

/**
 * maybeSendCancellationNotice({ affected, asOf, dryRun })
 *   -> { status: 'sent'|'planned'|'skipped'|'blocked', to?, subject?, count?, reason? }
 * dryRun returns the plan without sending (for tests / a preview). A live send
 * goes through mailer.sendInternalReport, so it is refused unless
 * BRAIN_INTERNAL_REPORTS is on AND every recipient is on an internal domain.
 */
function maybeSendCancellationNotice({ affected = [], asOf, dryRun = false } = {}) {
  const list = buildDueList(affected, asOf);
  if (list.length === 0) {
    return { status: 'skipped', count: 0, reason: 'no policies with a 5-day notice due' };
  }
  const to = recipients();
  const today = _iso(asOf) || new Date().toISOString().slice(0, 10);
  const subject = `Alpha Brain — ${list.length} ${list.length === 1 ? 'policy' : 'policies'} due for cancellation in 5 days (reply STOP/CONTINUE) — ${today}`;
  const plan = { to: to.slice(), subject, count: list.length };
  if (dryRun) return { status: 'planned', ...plan };
  if (to.length === 0) {
    return { status: 'skipped', count: list.length, reason: 'no recipients configured (BRAIN_CANCEL_NOTICE_RECIPIENTS)' };
  }

  const html = mailer.renderEmail({
    title: '5-Day Cancellation Notice',
    eyebrow: 'Finance Oversight — action requested',
    accent: '#FE7F0C',
    preheader: subject,
    bodyHtml: _bodyHtml(list),
  });
  const r = mailer.sendInternalReport({ to, subject, html, tag: 'cancellation-5day-notice' });
  return { status: (r && r.ok) ? 'sent' : 'blocked', ...plan, reason: r && r.reason };
}

module.exports = { maybeSendCancellationNotice, buildDueList, recipients };
