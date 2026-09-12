'use strict';
/**
 * payerGuard.js — general "one payer instrument funds many customers" control,
 * for RealPay/DPO/VCS bank accounts (and cards). Applies to domestic, commercial
 * and instant. Sits alongside lib/commissionGuard.js (which is the agent/commission-
 * specific card version); this one is the broader payer-anomaly control feeding
 * the Finance exceptions review.
 *
 * CFO 2026-07-09: this must FLAG AN EXCEPTION for a human, not auto-block — a
 * husband genuinely pays for a wife. So we tolerate a small "family allowance"
 * and only raise an exception beyond it, handing the reviewer the customer list
 * to confirm the relationship or investigate. Collections are never blocked.
 *
 * Bank account / card token is MASKED to the last 4 (it is PII) and never leaves
 * the system. Deterministic, internal, no external calls.
 */

const DEFAULTS = { familyAllowance: 2 }; // husband + wife is fine; flag the 3rd+

const mask = (t) => { const s = String(t || ''); return s.length > 4 ? '…' + s.slice(-4) : s; };
const uniq = (a) => Array.from(new Set(a));

/**
 * scanExceptions(payments, opts) -> { exceptions, summary }
 *   payment: { instrumentType:'bank'|'card', instrumentToken, customerId, policyNumber, product, date }
 */
function scanExceptions(payments = [], opts = {}) {
  const c = { ...DEFAULTS, ...opts };
  const byInstrument = new Map();
  for (const p of payments) {
    if (!p || !p.instrumentToken) continue;
    const g = byInstrument.get(p.instrumentToken) || [];
    g.push(p); byInstrument.set(p.instrumentToken, g);
  }

  const exceptions = [];
  for (const [token, list] of byInstrument) {
    const customers = uniq(list.map((x) => x.customerId));
    if (customers.length > c.familyAllowance) {
      const type = list[0].instrumentType || 'bank';
      exceptions.push({
        instrument: mask(token),
        instrumentType: type,
        distinctCustomers: customers.length,
        customers,                                   // reviewer confirms relationship
        products: uniq(list.map((x) => x.product).filter(Boolean)),
        policies: uniq(list.map((x) => x.policyNumber)),
        status: 'review',                            // NOT blocked
        reason: `one ${type === 'card' ? 'card' : 'bank account'} pays for ${customers.length} different customers `
              + `(allowance ${c.familyAllowance}) — confirm relationship (family is fine) or investigate`,
      });
    }
  }
  exceptions.sort((a, b) => b.distinctCustomers - a.distinctCustomers);
  return { exceptions, summary: { instrumentsFlagged: exceptions.length } };
}

/**
 * checkPayment(payment, history, opts) — real-time. Collection is always allowed;
 * an exception is raised for review once the account crosses the family allowance.
 * -> { allowCollection, raiseException, distinctCustomers, reason }
 */
function checkPayment(payment = {}, history = [], opts = {}) {
  const c = { ...DEFAULTS, ...opts };
  const customers = uniq([
    ...history.filter((h) => h.instrumentToken === payment.instrumentToken).map((h) => h.customerId),
    payment.customerId,
  ]);
  const over = customers.length > c.familyAllowance;
  return {
    allowCollection: true,                           // never block a genuine payment
    raiseException: over,
    distinctCustomers: customers.length,
    reason: over
      ? `this account now pays for ${customers.length} different customers — raised for review (family OK, else investigate)`
      : 'ok',
  };
}

module.exports = { scanExceptions, checkPayment, DEFAULTS };
