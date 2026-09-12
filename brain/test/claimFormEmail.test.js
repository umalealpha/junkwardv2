'use strict';
// Unit test for lib/claimFormEmail.js — run: node test/claimFormEmail.test.js
// Uses dryRun so NOTHING is emailed. Points CLAIM_FORMS_DIR at the local pack so
// the "does the form file exist?" check runs against the real 25 PDFs.

const assert = require('node:assert');
const fs = require('fs');

// Local copy of the official forms (prat-skill claimsdoc). On the server this is
// CLAIM_FORMS_DIR=/opt/claims-tracker/claim-forms.
const LOCAL_FORMS = process.env.HOME + '/.claude/skills/prat-skill/claimsdoc/claim-forms/Claim forms';
process.env.CLAIM_FORMS_DIR = LOCAL_FORMS;

const { sendClaimForms, CLAIMS_DEPT } = require('../lib/claimFormEmail');

let pass = 0, fail = 0;
function check(name, fn) {
  try { fn(); pass++; console.log(`  ok  ${name}`); }
  catch (e) { fail++; console.log(`FAIL  ${name}\n      ${e.message}`); }
}

if (!fs.existsSync(LOCAL_FORMS)) {
  console.log(`SKIP — forms dir not found: ${LOCAL_FORMS}`);
  process.exit(0);
}

check('Motor claim → Motor form, CC claims dept, addressed to customer', () => {
  const r = sendClaimForms({
    claim: { claimNumber: 'G2026000123', claimType: 'Motor Accident' },
    recipientEmail: 'customer@example.com',
    dryRun: true,
  });
  assert.equal(r.status, 'planned');
  assert.deepEqual(r.attachments, ['MOTOR ACCIDENT CLAIM FORM.pdf']);
  assert.equal(r.cc, CLAIMS_DEPT);
  assert.equal(r.to, 'customer@example.com');
});

check('Money claim → BOTH Burglary + Fidelity forms attached, both exist', () => {
  const r = sendClaimForms({ claim: { claimType: 'Money' }, recipientEmail: 'c@e.com', dryRun: true });
  assert.equal(r.status, 'planned');
  assert.equal(r.attachments.length, 2);
  assert.ok(r.attachments.includes('BURGLARY CLAIM FORM.pdf'));
  assert.ok(r.attachments.includes('FEDILITY CLAIM FORM.pdf'));
});

check('Fire claim → Property Loss form', () => {
  const r = sendClaimForms({ claim: { claimType: 'Fire' }, recipientEmail: 'c@e.com', dryRun: true });
  assert.deepEqual(r.attachments, ['PROPERTY LOSS CLAIM FORM.pdf']);
});

check('Bonu (no form on record) → needs_human, nothing sent', () => {
  const r = sendClaimForms({ claim: { claimType: 'Bonu' }, recipientEmail: 'c@e.com', dryRun: true });
  assert.equal(r.status, 'needs_human');
});

check('CC address is exactly claimsdept@alphadirect.co.bw', () => {
  assert.equal(CLAIMS_DEPT, 'claimsdept@alphadirect.co.bw');
});

console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
