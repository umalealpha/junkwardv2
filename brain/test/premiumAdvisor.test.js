'use strict';
// Unit test for lib/premiumAdvisor.js — run: node test/premiumAdvisor.test.js
const assert = require('node:assert');
const { decide, buildMaskedPayload, MASK_ALLOW } = require('../lib/premiumAdvisor');

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

check('0 months behind → GO', () => assert.equal(decide({ arrearsMonths: 0 }).decision, 'GO'));
check('exactly 1 month (grace) → GO', () => assert.equal(decide({ arrearsMonths: 1 }).decision, 'GO'));
check('2 months → REFER to Finance (not declined)', () => assert.equal(decide({ arrearsMonths: 2 }).decision, 'REFER'));

check('3 months exactly is NOT high-arrears (needs > 3)', () => {
  assert.equal(decide({ arrearsMonths: 3 }).highArrears, false);
});
check('4 months → high-arrears, and REFER', () => {
  const r = decide({ arrearsMonths: 4 });
  assert.equal(r.highArrears, true);
  assert.equal(r.decision, 'REFER');
});
check('4 months + Finance approves → alert oversight', () => {
  assert.equal(decide({ arrearsMonths: 4, financeApproved: true }).alertFinanceOversight, true);
});
check('4 months + NOT approved → no oversight alert', () => {
  assert.equal(decide({ arrearsMonths: 4, financeApproved: false }).alertFinanceOversight, false);
});

check('masking sends ONLY whitelisted numbers — no PII leaks', () => {
  const masked = buildMaskedPayload({
    arrearsMonths: 4, amountOwing: 3750, daysUnpaid: 99,
    contactName: 'Neo Moremi', policyNumber: 'COMG123', omang: '123456789',
    bankAccount: '620...', claimNumber: 'G2026000123',
  });
  assert.deepEqual(Object.keys(masked).sort(), ['amountOwing', 'arrearsMonths', 'daysUnpaid']);
  const s = JSON.stringify(masked);
  for (const leak of ['Neo', 'COMG123', '123456789', '620', 'G2026']) {
    assert.ok(!s.includes(leak), 'PII leaked: ' + leak);
  }
});

check('mask whitelist excludes any identity field', () => {
  for (const bad of ['contactName', 'policyNumber', 'claimNumber', 'omang', 'bankAccount', 'name']) {
    assert.ok(!MASK_ALLOW.includes(bad), bad + ' must not be sendable');
  }
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
