'use strict';
/**
 * ageing.js — CORRECT debtor age analysis. Fixes the defect where a payment is
 * aged by its OWN date instead of being applied to the invoice it settles.
 *
 * The bug (CFO example): invoice raised 1 Jan 2026, customer pays 30 Mar 2026.
 * As at 1 Apr the OLD system shows the invoice in "90 days" AND the payment in
 * "current" — so a fully-paid account looks both overdue and in-credit. Root
 * cause seen in Graphite Helper.php: `unallocated = NULL` — payments are never
 * allocated to invoices.
 *
 * The fix: ALLOCATE first (by reference, then oldest-invoice-first / FIFO), then
 * age only what is genuinely still open, by the ORIGINAL invoice date. A settled
 * invoice drops out entirely; a payment never sits on its own as "current".
 *
 * Pure + deterministic, numbers/dates only (no PII). Buckets are days overdue as
 * at `asOf`:  current 0–30 · b30 31–60 · b60 61–90 · b90 91–120 · b120plus >120.
 */

const DAY = 86400000;
function days(from, to) { return Math.floor((Date.parse(to) - Date.parse(from)) / DAY); }
function round2(n) { return Math.round((Number(n) || 0) * 100) / 100; }
function bucketFor(d) {
  if (d <= 30) return 'current';
  if (d <= 60) return 'b30';
  if (d <= 90) return 'b60';
  if (d <= 120) return 'b90';
  return 'b120plus';
}

/**
 * age(invoices, payments, asOf, opts) -> { balance, buckets, openInvoices, unappliedCredit, allocations }
 *   invoice: { date, amount, ref? }
 *   payment: { date, amount, ref? }
 */
function age(invoices = [], payments = [], asOf, opts = {}) {
  // missing as-of date defaults to TODAY — an undefined date used to NaN every
  // age and dump healthy accounts into the >120-day bucket (CFO 2026-07-21)
  const asof = asOf || opts.asOf || new Date().toISOString().slice(0, 10);
  // Work on copies with a running "open" balance, oldest first.
  const invs = invoices
    .map((i, idx) => ({ idx, date: i.date, ref: i.ref != null ? String(i.ref) : null, amount: Number(i.amount) || 0, open: Number(i.amount) || 0 }))
    .sort((a, b) => Date.parse(a.date) - Date.parse(b.date));
  const pays = payments
    .map((p) => ({ date: p.date, ref: p.ref != null ? String(p.ref) : null, amount: Number(p.amount) || 0, left: Number(p.amount) || 0 }))
    .sort((a, b) => Date.parse(a.date) - Date.parse(b.date));

  const allocations = [];

  // 1) allocate by matching reference first (payment.ref === invoice.ref)
  for (const p of pays) {
    if (!p.ref || p.left <= 0) continue;
    const inv = invs.find((i) => i.ref && i.ref === p.ref && i.open > 0);
    if (inv) {
      const amt = Math.min(p.left, inv.open);
      inv.open = round2(inv.open - amt); p.left = round2(p.left - amt);
      allocations.push({ paymentDate: p.date, invoiceRef: inv.ref, amount: amt, via: 'reference' });
    }
  }
  // 2) allocate the rest oldest-invoice-first (FIFO)
  for (const p of pays) {
    if (p.left <= 0) continue;
    for (const inv of invs) {
      if (p.left <= 0) break;
      if (inv.open <= 0) continue;
      const amt = Math.min(p.left, inv.open);
      inv.open = round2(inv.open - amt); p.left = round2(p.left - amt);
      allocations.push({ paymentDate: p.date, invoiceDate: inv.date, amount: amt, via: 'fifo' });
    }
  }

  // 3) age only what is still open, by the invoice's own date
  const buckets = { current: 0, b30: 0, b60: 0, b90: 0, b120plus: 0 };
  const openInvoices = [];
  for (const inv of invs) {
    if (inv.open <= 0) continue;
    const ageDays = days(inv.date, asof);
    const b = bucketFor(ageDays);
    buckets[b] = round2(buckets[b] + inv.open);
    openInvoices.push({ date: inv.date, ref: inv.ref, open: inv.open, ageDays, bucket: b });
  }

  const unappliedCredit = round2(pays.reduce((s, p) => s + Math.max(0, p.left), 0));
  const balance = round2(Object.values(buckets).reduce((s, v) => s + v, 0) - unappliedCredit);

  // Payments that could NOT be fully allocated, oldest first — i.e. WHEN the
  // credit on this account arose. debtorsAudit needs this to date a standing
  // negative from the evidence instead of from "the first day we looked", which
  // is the no-start-date defect that hit four other modules (audit 2026-07-21).
  // Additive: no existing field changes.
  const openPayments = pays
    .filter((p) => p.left > 0)
    .map((p) => ({ date: p.date, ref: p.ref, left: round2(p.left) }));

  return { balance, buckets, openInvoices, openPayments, unappliedCredit, allocations, asOf: asof };
}

module.exports = { age, bucketFor, days };
