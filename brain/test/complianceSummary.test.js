'use strict';
// complianceSummary.test.js — the /compliance-summary payload Omni pulls nightly
// must be COUNTS ONLY: zero customer rows, refs, names, policy numbers or details.
// (Those live in /inbox, which Omni does not fetch.) Also proves the census fills
// when injected and stays empty (never errors) when absent.
const assert = require('node:assert');
const { complianceSummary } = require('../compliance-summary');

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

const queue = {
  generatedAt: 'T', total: 3, counts: { Compliance: 2, Finance: 1 },
  teams: {
    Compliance: [
      { domain: 'kyc', ref: 'KYC-1', policyNumber: 'DOMG2024130581', customerName: 'Jane Doe', detail: 'Omang 123456789' },
      { domain: 'kyc', ref: 'KYC-2' },
    ],
    Finance: [{ domain: 'debtors', ref: 'ACC-9', policyNumber: 'MIS2026215205' }],
  },
};

check('shape: counts + kyc.by_domain from Compliance items', () => {
  const s = complianceSummary(queue, null);
  assert.equal(s.total, 3);
  assert.deepEqual(s.counts, { Compliance: 2, Finance: 1 });
  assert.equal(s.kyc.by_domain.kyc, 2);
});

check('PII-FREE: no refs / names / policy numbers / Omang / details leak into the payload', () => {
  const out = JSON.stringify(complianceSummary(queue, null));
  for (const leak of ['KYC-1', 'DOMG2024130581', 'MIS2026215205', 'Jane Doe', '123456789', 'ACC-9', 'customerName', 'policyNumber', 'detail']) {
    assert.ok(!out.includes(leak), 'payload must NOT contain: ' + leak);
  }
});

check('census injected → fills; absent → empty (never errors)', () => {
  const s0 = complianceSummary(queue, null);
  assert.deepEqual(s0.kyc.by_category, {});
  assert.equal(s0.kyc.policies_no_docs, 0);
  const s1 = complianceSummary(queue, { by_category: { MIS: 10, DOM: 5 }, policies_no_docs: 7, claims_kyc: { compliant: 3, non_compliant: 1 } });
  assert.equal(s1.kyc.by_category.MIS, 10);
  assert.equal(s1.kyc.policies_no_docs, 7);
  assert.equal(s1.kyc.claims_kyc.non_compliant, 1);
});

check('null / empty queue → safe zeros, no throw', () => {
  const s = complianceSummary(null, null);
  assert.equal(s.total, 0);
  assert.deepEqual(s.counts, {});
  assert.deepEqual(s.kyc.by_domain, {});
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
