'use strict';
// Unit test for lib/refundEngine.js — run: node test/refundEngine.test.js
const assert = require('node:assert');
const { buildRefundPlan, completionGate } = require('../lib/refundEngine');

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

const policy = {
  policyNumber: 'TPMG-1',
  payments: [{ id: 'PAY-1', amount: 600 }, { id: 'PAY-2', amount: 600 }],
  invoices: [{ invoiceNo: 'INV-1', amount: 1200 }],
  termStart: '2026-01-01', termEnd: '2026-12-31', totalPremium: 1200,
};

check('mis-sold third-party motor → FULL refund + all invoices reversed', () => {
  const p = buildRefundPlan(policy, { reason: 'mis_sold' });
  assert.equal(p.method, 'full');
  assert.equal(p.refundAmount, 1200);
  assert.equal(p.invoicesToReverse[0].invoiceNo, 'INV-1');
  assert.equal(p.invoicesToReverse[0].amount, 1200);
});

check('plan always has BOTH legs, bound together', () => {
  const p = buildRefundPlan(policy, { reason: 'mis_sold' });
  const legs = p.legs.map((l) => l.leg);
  assert.ok(legs.includes('reverse_payment'));
  assert.ok(legs.includes('issue_credit_note'));
  assert.equal(p.requiresBoth, true);
});

check('mid-term cancellation → pro-rata unearned + matching credit note', () => {
  const p = buildRefundPlan(policy, { reason: 'cancellation', asOf: '2026-07-01' });
  assert.equal(p.method, 'pro_rata');
  assert.ok(p.refundAmount > 550 && p.refundAmount < 650, 'about half back, got ' + p.refundAmount);
  // credit note follows the same fraction as the refund
  assert.ok(Math.abs(p.invoicesToReverse[0].amount - p.refundAmount) < 1, 'credit note should match refund');
});

// ── THE CONTROL FIX ──────────────────────────────────────────────────────────
check('CONTROL: money refunded but invoice NOT reversed → BLOCKED (no inflated revenue)', () => {
  const g = completionGate({ paymentReversed: true, creditNoteIssued: false });
  assert.equal(g.status, 'blocked');
  assert.equal(g.allowClose, false);
  assert.ok(g.missing.includes('issue_credit_note'));
  assert.ok(/inflated/i.test(g.reason));
});

check('both legs done → complete, can close', () => {
  const g = completionGate({ paymentReversed: true, creditNoteIssued: true });
  assert.equal(g.status, 'complete');
  assert.equal(g.allowClose, true);
});

check('nothing done yet → pending, cannot close', () => {
  const g = completionGate({});
  assert.equal(g.status, 'pending');
  assert.equal(g.allowClose, false);
});

check('CFO 2026-07-21: refund can NEVER exceed what the customer paid (monthly payer)', () => {
  // paid 2 of 12 instalments (P200 of P1,200), cancels half-way: unearned share
  // of the annual premium is P600 — but only P200 ever came in.
  const plan = buildRefundPlan({
    policyNumber: 'DOMG-M', termStart: '2026-01-01', termEnd: '2026-12-31', totalPremium: 1200,
    payments: [{ id: 1, amount: 100 }, { id: 2, amount: 100 }],
    invoices: [{ invoiceNo: 'I1', amount: 100 }, { invoiceNo: 'I2', amount: 100 }],
  }, { reason: 'cancellation', asOf: '2026-07-01' });
  assert.ok(plan.refundAmount <= 200, `refund ${plan.refundAmount} exceeds premium received 200`);
});

check('ADVISORY: received−earned basis surfaces alongside (monthly arrears payer → 0)', () => {
  // Same monthly-payer case: paid 200, ~6 months elapsed → earned ~597. The
  // pro-rata-of-total refund is 200; the stricter received−earned basis is 0
  // (they consumed more cover than they paid for). Both are reported; the number
  // is NOT changed here — Finance chooses the governing basis.
  const plan = buildRefundPlan({
    policyNumber: 'DOMG-M', termStart: '2026-01-01', termEnd: '2026-12-31', totalPremium: 1200,
    payments: [{ id: 1, amount: 100 }, { id: 2, amount: 100 }],
    invoices: [{ invoiceNo: 'I1', amount: 100 }, { invoiceNo: 'I2', amount: 100 }],
  }, { reason: 'cancellation', asOf: '2026-07-01' });
  assert.equal(plan.unearnedFromReceived, 0);            // conservative basis
  assert.ok(plan.refundAmount > plan.unearnedFromReceived); // the two bases diverge
  assert.ok(/DECISION NEEDED/.test(plan.basisNote));
});

check('mis-sold: both refund bases agree (full paid back)', () => {
  const p = buildRefundPlan(policy, { reason: 'mis_sold' });
  assert.equal(p.unearnedFromReceived, p.refundAmount);
  assert.equal(p.basisNote, 'both refund bases agree');
});

check('fully-paid pro-rata refund is unchanged by the cap', () => {
  const plan = buildRefundPlan({
    policyNumber: 'COMG-F', termStart: '2026-01-01', termEnd: '2026-12-31', totalPremium: 1200,
    payments: [{ id: 1, amount: 1200 }], invoices: [{ invoiceNo: 'I1', amount: 1200 }],
  }, { reason: 'cancellation', asOf: '2026-07-02' });
  assert.ok(plan.refundAmount > 0 && plan.refundAmount < 1200);
});

check('credit note issued but money not reversed → blocked too', () => {
  const g = completionGate({ paymentReversed: false, creditNoteIssued: true });
  assert.equal(g.status, 'blocked');
  assert.ok(g.missing.includes('reverse_payment'));
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
