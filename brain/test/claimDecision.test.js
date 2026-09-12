'use strict';
// Unit test for lib/claimDecision.js — run: node test/claimDecision.test.js
const assert = require('node:assert');
const { build, renderBrief } = require('../lib/claimDecision');

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

const clean = {
  claim: { claimNumber: 'G2026000123', dateOfLoss: '2026-06-01', dateReported: '2026-06-02', claimType: 'Motor Accident', amount: 20000, estimate: 20000 },
  policy: { policyNumber: 'COMG-1', product: 'motor', insured: 'ACME', coverStart: '2026-01-01', coverEnd: '2026-12-31', sumInsured: 300000, statusAtLoss: 'active' },
  premium: { arrearsMonths: 0, financeApproved: false, amountOwing: 0, paymentDate: '2026-02-01' },
  extracted: { policeReport: true, incidentDate: '2026-06-01', amount: 20000, confidence: 0.9 },
};

check('clean, on risk, documents consistent → PAY', () => {
  const r = build(clean);
  assert.equal(r.verdict, 'PAY');
});

check('NEW BUSINESS: loss dated before the first payment → NO-PAY', () => {
  const r = build({ ...clean,
    policy: { ...clean.policy, coverStart: '2026-06-28' },
    claim: { ...clean.claim, dateOfLoss: '2026-07-01' },
    premium: { ...clean.premium, paymentDate: '2026-07-05' } });
  assert.equal(r.verdict, 'NO-PAY');
  assert.ok(r.reasons.join(' ').toLowerCase().includes('before'));
});

check('IN-FORCE monthly payer: loss before the latest debit is NOT a NO-PAY (CFO 2026-07-21)', () => {
  // customer since January; crash on the 1st, debit ran on the 5th
  const r = build({ ...clean, claim: { ...clean.claim, dateOfLoss: '2026-07-01', dateReported: '2026-07-02' }, premium: { ...clean.premium, paymentDate: '2026-07-05' } });
  assert.notEqual(r.verdict, 'NO-PAY');
});

check('NEW BUSINESS: police report predates the first payment (backdating) → NO-PAY', () => {
  const r = build({ ...clean,
    policy: { ...clean.policy, coverStart: '2026-07-01' },
    claim: { ...clean.claim, dateOfLoss: '2026-07-07' },
    premium: { ...clean.premium, paymentDate: '2026-07-05' },
    extracted: { policeReport: true, incidentDate: '2026-07-01', confidence: 0.9 } });
  assert.equal(r.verdict, 'NO-PAY');
});

check('policy lapsed at the loss → INVESTIGATE (Finance referral, never auto-decline — CFO 2026-07-21)', () => {
  const r = build({ ...clean, policy: { ...clean.policy, statusAtLoss: 'lapsed' } });
  assert.equal(r.verdict, 'INVESTIGATE');
  assert.ok(r.reasons.join(' ').includes('refer to Finance'));
});

check('formally cancelled BEFORE the loss (with effective date) → NO-PAY', () => {
  const r = build({ ...clean, policy: { ...clean.policy, statusAtLoss: 'cancelled', cancellationEffective: '2026-05-15' } });
  assert.equal(r.verdict, 'NO-PAY');
});

check('status "cancelled" but no effective date on record → INVESTIGATE, not NO-PAY', () => {
  const r = build({ ...clean, policy: { ...clean.policy, statusAtLoss: 'cancelled' } });
  assert.equal(r.verdict, 'INVESTIGATE');
});

check('premium 3 months in arrears → INVESTIGATE', () => {
  const r = build({ ...clean, premium: { ...clean.premium, arrearsMonths: 3, paymentDate: '2026-01-15' } });
  assert.equal(r.verdict, 'INVESTIGATE');
});

check('poor OCR read → INVESTIGATE (never auto-pay on a bad scan)', () => {
  const r = build({ ...clean, extracted: { policeReport: true, incidentDate: '2026-06-01', confidence: 0.4 } });
  assert.equal(r.verdict, 'INVESTIGATE');
});

check('poor OCR + bad-looking date → still only review + email Claims, never NO-PAY (CFO 2026-07-21)', () => {
  // misread date on a blurry scan LOOKS like backdating on a new policy
  const r = build({ ...clean,
    policy: { ...clean.policy, coverStart: '2026-07-01' },
    claim: { ...clean.claim, dateOfLoss: '2026-07-07' },
    premium: { ...clean.premium, paymentDate: '2026-07-05' },
    extracted: { policeReport: true, incidentDate: '2026-07-01', confidence: 0.3 } });
  assert.notEqual(r.verdict, 'NO-PAY');
  assert.ok(r.actions.some((a) => a.do === 'alert_claims_docs_review'), 'Claims must get the review email');
});

check('brief renders the verdict + the key sections', () => {
  const r = build(clean);
  const txt = renderBrief(r);
  assert.ok(txt.includes('VERDICT: PAY'));
  assert.ok(txt.includes('Policy'));
  assert.ok(txt.includes('Premium'));
  assert.ok(txt.includes('Documents (OCR)'));
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
