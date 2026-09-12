'use strict';
// Unit test for lib/highArrearsAlert.js — run: node test/highArrearsAlert.test.js
// The oversight recipient list now comes from SETTINGS (CFO 2026-07-13), not
// hardcoded — set it here BEFORE requiring, then assert the plan uses it.

process.env.BRAIN_HIGH_ARREARS_RECIPIENTS =
  'pganesharajah@alphadirect.co.bw, ktshutlhedi@alphadirect.co.bw, omogomotsi@alphadirect.co.bw';

const assert = require('node:assert');
const { maybeSendHighArrearsAlert, shouldAlert, recipients } = require('../lib/highArrearsAlert');

let pass = 0, fail = 0;
function check(name, fn) {
  try { fn(); pass++; console.log(`  ok  ${name}`); }
  catch (e) { fail++; console.log(`FAIL  ${name}\n      ${e.message}`); }
}

check('4 months + finance approved → alert', () => {
  assert.equal(shouldAlert({ arrearsMonths: 4, financeApproved: true }), true);
});
check('exactly 3 months + approved → NO alert (needs > 3)', () => {
  assert.equal(shouldAlert({ arrearsMonths: 3, financeApproved: true }), false);
});
check('5 months but NOT finance-approved → NO alert', () => {
  assert.equal(shouldAlert({ arrearsMonths: 5, financeApproved: false }), false);
});

check('recipients come from settings (BRAIN_HIGH_ARREARS_RECIPIENTS), not hardcoded', () => {
  const r = recipients();
  assert.equal(r.length, 3);
  assert.ok(r.includes('pganesharajah@alphadirect.co.bw'));
  assert.ok(r.includes('omogomotsi@alphadirect.co.bw'));
});

check('alert plan (dryRun) is addressed to the configured recipients', () => {
  const r = maybeSendHighArrearsAlert({
    claimNumber: 'G2026000123', policyNumber: 'COMG123', clientName: 'ACME Ltd',
    arrearsMonths: 6, amountOwing: 'BWP 7,500', financeApproved: true,
    approvedBy: 'Oprah Mogomotsi', approvedByRole: 'Finance', dryRun: true,
  });
  assert.equal(r.status, 'planned');
  assert.deepEqual(r.to, recipients());
  assert.ok(r.subject.includes('6 months'));
});

check('non-triggering case returns skipped, no send', () => {
  const r = maybeSendHighArrearsAlert({ arrearsMonths: 2, financeApproved: true, dryRun: true });
  assert.equal(r.status, 'skipped');
});

console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
