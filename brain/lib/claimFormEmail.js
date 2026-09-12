'use strict';
/**
 * claimFormEmail.js — email the correct blank claim form(s) to the customer
 * when a claim is registered, and CC the claims department.
 *
 * Reuses:
 *   - lib/claimForms.js  → which form(s) + required docs for the claim type
 *   - lib/mailer.js      → Mailgun send (now supports cc + attachments) + house HTML
 *
 * Never sends a wrong/blank form: if the claim type has no form on record, or a
 * mapped form file is missing on disk, it returns { status:'needs_human' } so the
 * handler is prompted instead of a customer getting the wrong document.
 *
 * The blank form PDFs live outside the repo (uploaded per environment). Point
 * CLAIM_FORMS_DIR at that folder.
 */

const fs = require('fs');
const path = require('path');
const { resolveClaimForm } = require('./claimForms');
const mailer = require('./mailer');

const CLAIMS_DEPT = 'claimsdept@alphadirect.co.bw';

function formsDir() {
  return process.env.CLAIM_FORMS_DIR || '/opt/claims-tracker/claim-forms';
}

function _docsListHtml(docs) {
  if (!docs || !docs.length) return '';
  const items = docs
    .map(d => `<li style="margin:2px 0">${String(d).replace(/&/g, '&amp;').replace(/</g, '&lt;')}</li>`)
    .join('');
  return `<p style="margin:14px 0 6px"><strong>Please return the form together with these documents:</strong></p>`
       + `<ul style="margin:0 0 8px;padding-left:20px">${items}</ul>`;
}

function _bodyHtml({ contactName, claimNumber, forms, docs }) {
  const names = forms.map(f => f.replace(/\.pdf$/i, '')).join(', ');
  const many = forms.length > 1;
  return `
    <p style="margin:0 0 14px">Hi ${contactName ? String(contactName).replace(/</g, '&lt;') : 'there'},</p>
    <p style="margin:0 0 12px">Thank you for telling us about your claim${claimNumber ? ` (<strong>${claimNumber}</strong>)` : ''}. To get things moving, please complete the attached ${many ? 'forms' : 'form'}: <strong>${names}</strong>.</p>
    ${_docsListHtml(docs)}
    <p style="margin:12px 0 0">Fill in the form, gather the documents above, and reply to this email. Our claims team is copied in and happy to help if you have any questions.</p>
    <p style="margin:14px 0 0">Thank you,<br>Alpha Direct Claims Team</p>`;
}

/**
 * sendClaimForms({ claim, recipientEmail, contactName, dryRun })
 *   claim: { claimNumber, claimType, nonMotorSubType }
 * Returns:
 *   { status: 'sent',        to, cc, subject, attachments, formType }
 *   { status: 'planned', ... }  (dryRun — everything resolved, nothing sent)
 *   { status: 'needs_human', reason, ... }
 */
function sendClaimForms(opts = {}) {
  const claim = opts.claim || {};
  const resolved = resolveClaimForm(claim.claimType, claim.nonMotorSubType);
  if (resolved.status !== 'ok') {
    return { status: 'needs_human', reason: resolved.reason, claimType: claim.claimType };
  }

  // Verify every mapped form file exists on disk — never attach a missing form.
  const dir = formsDir();
  const attachments = [];
  const missing = [];
  for (const fname of resolved.forms) {
    const p = path.join(dir, fname);
    if (fs.existsSync(p)) attachments.push({ filename: fname, path: p });
    else missing.push(fname);
  }
  if (missing.length) {
    return { status: 'needs_human', claimType: resolved.type, missing,
             reason: 'form file(s) not found on server: ' + missing.join(', ') };
  }

  const subject = `Your Alpha Direct claim form${resolved.forms.length > 1 ? 's' : ''}`
                + (claim.claimNumber ? ` — ${claim.claimNumber}` : '');
  const plan = {
    to: opts.recipientEmail || null,
    cc: CLAIMS_DEPT,
    subject,
    attachments: attachments.map(a => a.filename),
    formType: resolved.type,
  };

  if (opts.dryRun) return { status: 'planned', ...plan };
  if (!opts.recipientEmail) return { status: 'needs_human', reason: 'no customer email on the claim' };

  const html = mailer.renderEmail({
    title: 'Your Claim Form',
    eyebrow: 'Claims',
    preheader: `Please complete and return your claim form${resolved.forms.length > 1 ? 's' : ''}.`,
    bodyHtml: _bodyHtml({ contactName: opts.contactName, claimNumber: claim.claimNumber,
                          forms: resolved.forms, docs: resolved.docs }),
  });

  // CFO 2026-07-21: report the mailer's REAL result — never claim 'sent' for
  // an email the mailer blocked (arms off) or skipped (no keys/recipients).
  const r = mailer.sendEmail({
    to: opts.recipientEmail,
    cc: CLAIMS_DEPT,
    subject,
    html,
    attachments,
    tag: 'claim-form-send',
  }) || { ok: false, skipped: true };
  const status = r.ok ? (r.status || 'queued') : (r.blocked ? 'blocked' : 'skipped');
  return { status, ...plan };
}

module.exports = { sendClaimForms, CLAIMS_DEPT, formsDir };
