'use strict';
/**
 * refundEngine.js — generate a refund FROM the policy's own data and force the
 * two legs to move together: (1) reverse the money, (2) reverse the invoice
 * (credit note). Fixes the control gap where refunds are done in Excel + FNB and
 * the credit note is forgotten, inflating revenue.
 *
 * Graphite already has the pieces (RefundController, payment reversal fields,
 * CreditNoteSonali, pro-rata unearned in PolicyCoverage) but they are not linked.
 * This is the brain that (a) builds the plan — which payments to refund + which
 * invoices to reverse — and (b) GATES completion: a refund can only be closed
 * when BOTH the payment reversal and the credit note are done. Money moved but
 * no credit note → BLOCKED (revenue would be inflated). Applies to instant,
 * domestic and commercial.
 *
 * Pure + deterministic. Internal ids only (no external calls, no PII leak).
 */

const DAY = 86400000;
const days = (a, b) => Math.floor((Date.parse(b) - Date.parse(a)) / DAY);
const round2 = (n) => Math.round((Number(n) || 0) * 100) / 100;

// Reasons that void the sale entirely → full refund + full invoice reversal.
const FULL_REFUND_REASONS = new Set(['mis_sold', 'not_agreed', 'void', 'duplicate_sale']);

/**
 * buildRefundPlan(policy, opts) -> plan
 *   policy: { policyNumber, payments:[{id, amount}], invoices:[{invoiceNo, amount}],
 *             termStart, termEnd, totalPremium }
 *   opts:   { reason, asOf }   asOf = cancellation/refund date (for pro-rata)
 */
function buildRefundPlan(policy = {}, opts = {}) {
  const reason = opts.reason || 'cancellation';
  const payments = (policy.payments || []).map((p) => ({ id: p.id, amount: round2(p.amount) }));
  const invoices = (policy.invoices || []).map((i) => ({ invoiceNo: i.invoiceNo, amount: round2(i.amount) }));
  const paid = round2(payments.reduce((s, p) => s + p.amount, 0));

  let method, refundAmount, invoicesToReverse;
  // Advisory alternative basis (see basisNote below). Set in the pro_rata branch.
  let earnedToDate = null;         // premium earned for the elapsed period
  let unearnedFromReceived = null; // the conservative "received − earned" refund

  if (FULL_REFUND_REASONS.has(reason)) {
    // Mis-sold / not agreed → give it all back and reverse every invoice.
    method = 'full';
    refundAmount = paid;
    invoicesToReverse = invoices;
    earnedToDate = 0;                 // a voided sale earns nothing
    unearnedFromReceived = paid;      // → both bases agree: refund everything paid
  } else {
    // Mid-term cancellation → refund the unearned portion, credit-note the same.
    method = 'pro_rata';
    const termDays = policy.termStart && policy.termEnd ? days(policy.termStart, policy.termEnd) : 0;
    const asOf = opts.asOf || policy.termStart;
    const unearnedDays = policy.termEnd && asOf ? Math.max(0, days(asOf, policy.termEnd)) : 0;
    const frac = termDays > 0 ? Math.min(1, unearnedDays / termDays) : 0;
    const total = Number(policy.totalPremium) || paid;
    // CFO 2026-07-21: a refund can never exceed what the customer actually paid
    // (a monthly payer 2/12 instalments in must not get half the ANNUAL premium
    // back). Refund = the unearned share, capped at premium received.
    refundAmount = round2(Math.min(total * frac, paid));
    // credit-note each invoice by the same unearned fraction
    invoicesToReverse = invoices.map((i) => ({ invoiceNo: i.invoiceNo, amount: round2(i.amount * frac) }));
    // ADVISORY — the stricter "received − earned" basis. It NEVER over-refunds:
    // a monthly payer who has only paid for elapsed cover gets 0 back, whereas
    // the pro-rata-of-total basis above still refunds their full `paid`. Finance
    // must ratify WHICH basis governs (see basisNote). Not applied to the number.
    earnedToDate = round2(total * (1 - frac));
    unearnedFromReceived = round2(Math.max(0, paid - earnedToDate));
  }

  // The two mandatory legs, bound together.
  const legs = [
    { leg: 'reverse_payment', system: 'omni', amount: refundAmount, refs: payments.map((p) => p.id) },
    { leg: 'issue_credit_note', system: 'graphite', amount: round2(invoicesToReverse.reduce((s, i) => s + i.amount, 0)),
      refs: invoicesToReverse.map((i) => i.invoiceNo) },
  ];

  return {
    policyNumber: policy.policyNumber,
    reason, method, refundAmount,
    // Advisory alternative basis — Finance must ratify which one governs.
    earnedToDate, unearnedFromReceived,
    basisNote: unearnedFromReceived != null && unearnedFromReceived !== refundAmount
      ? `refundAmount uses pro-rata-of-total (capped at paid) = ${refundAmount}; the stricter received−earned basis = ${unearnedFromReceived}. DECISION NEEDED: which basis governs? (Finance sign-off)`
      : 'both refund bases agree',
    payments, invoicesToReverse, legs,
    requiresBoth: true,
    note: 'Refund is not complete until the money is reversed AND the invoice(s) credit-noted.',
  };
}

/**
 * completionGate(state) -> { status, allowClose, block, missing, reason }
 *   state: { paymentReversed, creditNoteIssued }
 * This is the control: you cannot close a refund half-done.
 */
function completionGate(state = {}) {
  const money = !!state.paymentReversed;
  const credit = !!state.creditNoteIssued;
  if (money && credit) return { status: 'complete', allowClose: true, block: false, missing: [], reason: 'both legs done' };
  if (!money && !credit) return { status: 'pending', allowClose: false, block: false, missing: ['reverse_payment', 'issue_credit_note'], reason: 'not started' };
  if (money && !credit) return { status: 'blocked', allowClose: false, block: true, missing: ['issue_credit_note'],
    reason: 'money refunded but invoice NOT reversed — revenue would be inflated. Blocked until the credit note is issued.' };
  return { status: 'blocked', allowClose: false, block: true, missing: ['reverse_payment'],
    reason: 'credit note issued but money not yet reversed — complete the payment reversal.' };
}

module.exports = { buildRefundPlan, completionGate, FULL_REFUND_REASONS };
