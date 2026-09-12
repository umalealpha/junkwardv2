'use strict';
// Unit test for lib/renewals.js — run: node test/renewals.test.js
const assert = require('node:assert');
const { planRenewal } = require('../lib/renewals');
const verbs = (r) => r.actions.map((a) => a.do);
const msgs = (r) => r.actions.filter((a) => a.customerMsg).map((a) => a.customerMsg);

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

check('far from expiry (60d) → nothing due', () => {
  assert.equal(planRenewal({ daysToExpiry: 60 }).phase, 'waiting');
});

check('45 days out, good book → generate terms, but NO customer offer yet', () => {
  const r = planRenewal({ daysToExpiry: 45, lossRatio: 0.4 });
  assert.ok(verbs(r).includes('generate_renewal_terms'));
  assert.ok(!msgs(r).includes('renewal_due'), 'offer waits for T-30');
});

check('30 days out (one month prior — CFO 2026-07-21) → offer to customer', () => {
  const r = planRenewal({ daysToExpiry: 30, lossRatio: 0.4, termsGenerated: true });
  assert.ok(msgs(r).includes('renewal_due'));
  assert.ok(!verbs(r).includes('generate_renewal_terms'), 'terms already generated — no repeat');
});

check('bad loss ratio → re-rate first (UW approval), do NOT offer yet', () => {
  const r = planRenewal({ daysToExpiry: 40, lossRatio: 0.9 });
  const rr = r.actions.find((a) => a.do === 'flag_rerate');
  assert.ok(rr, 'no re-rate flagged');
  assert.equal(rr.needsApproval, true);
  assert.ok(!msgs(r).includes('renewal_due'), 'must not offer before re-rate');
});

check('14 days out, already offered, not renewed → reminder', () => {
  const r = planRenewal({ daysToExpiry: 14, renewalOffered: true, renewed: false });
  assert.ok(msgs(r).includes('renewal_due'));
});

check('expired after an offer → ONE agent escalation + final notice', () => {
  const r = planRenewal({ daysToExpiry: -1, renewed: false, renewalOffered: true });
  assert.equal(r.phase, 'expired');
  assert.ok(verbs(r).includes('alert_agent'));
});

check('expired but never offered (UW withheld for re-rate) → NOT invited back', () => {
  const r = planRenewal({ daysToExpiry: -1, renewed: false, renewalOffered: false });
  assert.equal(r.phase, 'expired');
  assert.equal(r.actions.length, 0);
});

check('already chased once after expiry → no daily repeats', () => {
  const r = planRenewal({ daysToExpiry: -5, renewed: false, renewalOffered: true, expiredChased: true });
  assert.equal(r.actions.length, 0);
});

check('long-expired (2 years) → closed_lost, never chased', () => {
  const r = planRenewal({ daysToExpiry: -730, renewed: false, renewalOffered: true });
  assert.equal(r.phase, 'closed_lost');
  assert.equal(r.actions.length, 0);
});

check('bad loss ratio already flagged → no repeat flag while UW decides', () => {
  const r = planRenewal({ daysToExpiry: 40, lossRatio: 0.9, termsGenerated: true, rerateFlagged: true });
  assert.equal(r.actions.length, 0);
});

check('renewed → done, nothing to do', () => {
  const r = planRenewal({ daysToExpiry: 5, renewed: true });
  assert.equal(r.phase, 'renewed');
  assert.equal(r.actions.length, 0);
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
