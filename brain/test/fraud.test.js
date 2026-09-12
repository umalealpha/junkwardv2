'use strict';
// Unit test for lib/fraud.js — run: node test/fraud.test.js
const assert = require('node:assert');
const { assess } = require('../lib/fraud');
const codes = (r) => r.flags.map((f) => f.code);

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

check('loss dated before cover → HOLD', () => {
  const r = assess({ coverStart: '2026-02-01', dateOfLoss: '2026-01-20', dateReported: '2026-02-02' });
  assert.ok(codes(r).includes('loss_before_cover'));
  assert.equal(r.recommendation, 'hold');
});

check('loss days after cover starts → early_claim (soft)', () => {
  const r = assess({ coverStart: '2026-02-01', dateOfLoss: '2026-02-04', dateReported: '2026-02-05' });
  assert.ok(codes(r).includes('early_claim'));
});

check('duplicate: prior same-type claim within window → HOLD', () => {
  const r = assess({
    coverStart: '2026-01-01', dateOfLoss: '2026-03-10', dateReported: '2026-03-11', claimType: 'Motor Accident',
    priorClaims: [{ dateOfLoss: '2026-03-05', claimType: 'Motor Accident' }],
  });
  assert.ok(codes(r).includes('possible_duplicate'));
  assert.equal(r.recommendation, 'hold');
});

check('reported long after the loss → late_report (soft)', () => {
  const r = assess({ coverStart: '2026-01-01', dateOfLoss: '2026-02-01', dateReported: '2026-04-01' });
  assert.ok(codes(r).includes('late_report'));
});

check('estimate over sum insured → over_sum_insured (soft)', () => {
  const r = assess({ coverStart: '2026-01-01', dateOfLoss: '2026-06-01', dateReported: '2026-06-02', sumInsured: 50000, estimate: 70000 });
  assert.ok(codes(r).includes('over_sum_insured'));
});

check('two soft flags → REFER', () => {
  const r = assess({ coverStart: '2026-06-01', dateOfLoss: '2026-06-03', dateReported: '2026-08-01', sumInsured: 100, estimate: 999 });
  // early_claim + late_report + over_sum_insured
  assert.ok(r.flags.length >= 2);
  assert.equal(r.recommendation, 'refer');
});

check('clean, normal claim → PROCEED', () => {
  const r = assess({ coverStart: '2026-01-01', dateOfLoss: '2026-06-01', dateReported: '2026-06-02', sumInsured: 50000, estimate: 20000 });
  assert.equal(r.recommendation, 'proceed');
  assert.equal(r.flags.length, 0);
});

// The CFO's case today: paid, then claims two days later.
check("TODAY'S CASE: loss 2 days after payment → claim_soon_after_payment", () => {
  const r = assess({ coverStart: '2026-01-01', paymentDate: '2026-07-05', dateOfLoss: '2026-07-07', dateReported: '2026-07-07' });
  assert.ok(codes(r).includes('claim_soon_after_payment'));
});

check('loss dated BEFORE the FIRST premium → HOLD (backdating, new business)', () => {
  const r = assess({ coverStart: '2026-06-28', paymentDate: '2026-07-05', dateOfLoss: '2026-07-01', dateReported: '2026-07-07' });
  assert.ok(codes(r).includes('loss_before_payment'));
  assert.equal(r.recommendation, 'hold');
});

check('IN-FORCE monthly payer: loss before the LATEST debit is NOT fraud (CFO 2026-07-21)', () => {
  // customer since Jan; crashes on the 1st; monthly debit runs on the 5th
  const r = assess({ coverStart: '2026-01-01', paymentDate: '2026-07-05', dateOfLoss: '2026-07-01', dateReported: '2026-07-02' });
  assert.ok(!codes(r).includes('loss_before_payment'));
  assert.notEqual(r.recommendation, 'hold');
});

check('cover start unknown + loss before payment → soft verify flag, never auto-HOLD', () => {
  const r = assess({ paymentDate: '2026-07-05', dateOfLoss: '2026-07-01', dateReported: '2026-07-07' });
  assert.ok(codes(r).includes('loss_before_latest_payment'));
  assert.notEqual(r.recommendation, 'hold');
});

check('same-day pay-and-loss with datetimes is gap 0, not -1', () => {
  const r = assess({ coverStart: '2026-07-01', paymentDate: '2026-07-05T08:00:00Z', dateOfLoss: '2026-07-05T17:30:00Z', dateReported: '2026-07-06' });
  assert.ok(!codes(r).includes('loss_before_payment'));
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
