'use strict';
/**
 * highArrearsAlert.js — CFO directive 2026-07-09.
 *
 * When a claim whose client is MORE THAN 3 MONTHS in premium arrears is
 * nonetheless APPROVED by Finance, automatically alert the finance oversight
 * group (CFO + clearing/finance leads) for visibility.
 *
 * Internal oversight/audit alert only — never sent to the customer.
 * Reuses lib/mailer.js (house HTML + Mailgun send).
 */

const mailer = require('./mailer');
const config = require('../config');

// Strictly MORE THAN this many months triggers the alert (so 3 months exactly
// does not; 4+ does).
const HIGH_ARREARS_MONTHS = 3;

// Finance oversight group — moved OUT of code into settings (CFO 2026-07-13).
// Sourced at call-time from config.highArrearsRecipients
// (env BRAIN_HIGH_ARREARS_RECIPIENTS). No addresses are hardcoded here.
function recipients() {
  return config.highArrearsRecipients || [];
}

function shouldAlert({ arrearsMonths, financeApproved } = {}) {
  return financeApproved === true && Number(arrearsMonths) > HIGH_ARREARS_MONTHS;
}

function _rows(info) {
  const rows = [
    ['Claim', info.claimNumber || '—'],
    ['Policy', info.policyNumber || '—'],
    // DPA (CFO 2026-07-21): no customer name through the external mail relay —
    // the reviewer looks the client up by claim/policy number inside omni.
    ['Months in arrears', String(info.arrearsMonths)],
    ['Amount owing', info.amountOwing != null ? info.amountOwing : '—'],
    ['Approved by', (info.approvedBy || '—') + (info.approvedByRole ? ` (${info.approvedByRole})` : '')],
  ];
  return rows.map(([k, v], i) =>
    `<tr${i % 2 ? ' bgcolor="#EFF2F7"' : ''}>`
    + `<td style="padding:8px 12px;color:#6B7280;width:42%;font-size:12px">${k}</td>`
    + `<td style="padding:8px 12px;font-weight:600">${String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;')}</td></tr>`
  ).join('');
}

function _bodyHtml(info) {
  return `
    <p style="margin:0 0 14px">A claim has been <strong>approved by Finance</strong> even though the client is
    <strong>more than ${HIGH_ARREARS_MONTHS} months in arrears</strong> on premium. Sharing for your oversight.</p>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:13px;line-height:1.6">${_rows(info)}</table>
    <p style="margin:16px 0 0;font-size:12px;color:#6B7280">Sent automatically because the arrears exceed ${HIGH_ARREARS_MONTHS} months. No action needed unless you wish to review.</p>`;
}

/**
 * maybeSendHighArrearsAlert(info)
 *   info: { claimNumber, policyNumber, clientName, arrearsMonths, amountOwing,
 *           financeApproved, approvedBy, approvedByRole, dryRun }
 * -> { status: 'alerted' | 'planned' | 'skipped', to?, subject?, reason? }
 */
function maybeSendHighArrearsAlert(info = {}) {
  if (!shouldAlert(info)) {
    return { status: 'skipped', reason: 'arrears not > 3 months, or claim not finance-approved' };
  }

  const to = recipients();
  const subject = `High-arrears claim approved — ${info.claimNumber || '(claim)'} `
                + `(${info.arrearsMonths} months in arrears)`;
  const plan = { to: to.slice(), subject };
  if (info.dryRun) return { status: 'planned', ...plan };

  if (to.length === 0) {
    return { status: 'skipped', reason: 'no recipients configured (BRAIN_HIGH_ARREARS_RECIPIENTS)' };
  }

  const html = mailer.renderEmail({
    title: 'High-Arrears Claim Approved',
    eyebrow: 'Finance Oversight',
    accent: '#DC2626',
    preheader: subject,
    bodyHtml: _bodyHtml(info),
  });
  // CFO 2026-08-31: this is an INTERNAL oversight alert to Finance/CFO, so it goes
  // via sendInternalReport (gated by BRAIN_INTERNAL_REPORTS + the internal-domain
  // check) — NOT the customer arm. It fires while customer comms stay off.
  const r = mailer.sendInternalReport({ to, subject, html, tag: 'high-arrears-approval' });
  return { status: (r && r.ok) ? 'alerted' : 'blocked', ...plan, reason: r && r.reason };
}

module.exports = { maybeSendHighArrearsAlert, shouldAlert, recipients, HIGH_ARREARS_MONTHS };
