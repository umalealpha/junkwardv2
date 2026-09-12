'use strict';
/**
 * collectionsLeak.js — the "debit-on-a-dead-policy" watch (the REVERSE of the
 * non-payment clock in collectionsClock.js).
 *
 * The non-payment clock (collectionsRo/collectionsClock) watches the ACTIVE book
 * (status = 1) for policies that stopped paying. This module watches the DEAD
 * book: policies that are Deactivated / Cancelled / Expired (status <> 1) whose
 * RealPay mandate is STILL live and STILL taking successful debits. That is money
 * leaving the company on cover that no longer exists — the leak the CFO's June MIS
 * report and Oprah's 2026-08-11 controls spec were about.
 *
 * Scope: INSTANT only (MIS / ADH prefixes). DOMG/COMG mandate-on-dead-policy is
 * already covered by the Graphite Finance "Exceptions" module (flags B/D); MIS is
 * the ~87%-of-book gap that control skips. Reuses collectionsRo's vetted building
 * blocks (billingTypeFor, the RealPay 'S' success join by contract_number, the
 * status legend, the DPA name-handling) rather than re-deriving them.
 *
 * SAFETY: READ-ONLY, SELECT-only, against the PROD read replica. Mirrors
 * collectionsRo/graphiteRo: mysql2 is lazy-required only inside the fetch, all
 * mappers are pure (unit-testable with no DB), and fetch throws on any error so
 * the caller (daily.js) degrades to an EMPTY leak queue — a bad pull can never
 * break the arms-off container.
 *
 * ARMS STAY OFF. This only COMPUTES and RETURNS candidate policies. The action —
 * stopping the mandate — is a HUMAN step by CFO decision (2026-08-11): the system
 * flags, a person checks WHY the policy is inactive, a person stops the debit. No
 * automatic block, because our own bad data (a wrongly-deactivated policy) must
 * never cut off a paying customer.
 *
 * DPA: this control deliberately carries NO customer name. The person actioning a
 * leak looks the customer up in Graphite when they stop the mandate, so the queue
 * item needs only the policy number + amounts. Nothing here is a new PII surface.
 */

const { billingTypeFor } = require('./collectionsRo'); // reuse the prefix→type map
const { parseDsn } = require('./graphiteRo');           // reuse the shared DSN parser

// The look-back window for "still being debited". A dead policy with a successful
// RealPay debit inside this many days is actively leaking money NOW (a mandate
// that last debited a year ago is dormant and is not the fire to put out). CFO/
// Oprah framed the leak on a 90-day (one quarter) window.
const WINDOW_DAYS = 90;

// policies.status legend (per ReconRealpayExceptions.php / collectionsRo):
//   1 = Active, 0 = Deactivated, 2 = Cancelled, 3 = Expired.
function _rank(statusCode) {
  const s = Number(statusCode);
  if (s === 2) return { statusLabel: 'Cancelled', severity: 'critical', priority: 92 };
  if (s === 0) return { statusLabel: 'Deactivated', severity: 'critical', priority: 88 };
  if (s === 3) return { statusLabel: 'Expired', severity: 'high', priority: 80 };
  return { statusLabel: `Status ${s}`, severity: 'high', priority: 78 };
}

const _iso = (v) => {
  if (v == null) return null;
  if (v instanceof Date) return v.toISOString().slice(0, 10);
  const s = String(v).trim();
  return s ? s.slice(0, 10) : null;
};
const _round2 = (n) => Math.round((Number(n) || 0) * 100) / 100;

// ── the read (SELECT-only, aggregated per policy) ────────────────────────────
// One row per dead MIS/ADH policy whose STILL-ACTIVE mandate took >=1 successful
// RealPay debit in the window. Join installments to the policy by contract_number
// (installments.policy_id is unreliable — same caveat collectionsRo documents).
const LEAK_SQL = `
  SELECT p.policyNumber                          AS policyNumber,
         p.status                                AS statusCode,
         pr.name                                 AS product,
         COUNT(*)                                AS recentDebits,
         ROUND(SUM(rci.InstalmentAmount), 2)     AS recentAmount,
         MAX(DATE(rci.InstalmentActionDate))     AS lastDebit,
         MIN(DATE(rci.InstalmentActionDate))     AS firstDebit
  FROM realpay_contract_installments rci
  JOIN realpay_client_contracts rcc ON rcc.contract_number = rci.contractNumber AND rcc.status = 1
  JOIN policies  p  ON p.id = rcc.policy_id
  JOIN products  pr ON pr.id = p.product_id
  WHERE (p.policyNumber LIKE 'MIS%' OR p.policyNumber LIKE 'ADH%')
    AND p.status <> 1
    AND rci.InstalmentStatus = 'S'
    AND rci.InstalmentActionDate >= (CURDATE() - INTERVAL ${WINDOW_DAYS} DAY)
  GROUP BY p.id, p.policyNumber, p.status, pr.name
`;

/**
 * buildLeakRows(rows) — PURE. Map raw SQL rows to canonical leak candidates,
 * ranked most-egregious first. A cancelled/deactivated policy still being debited
 * is a critical control failure; expired is high.
 */
function buildLeakRows(rows = []) {
  const out = (rows || []).map((r) => {
    const rank = _rank(r.statusCode);
    return {
      policyNumber: String(r.policyNumber || ''),
      billingType: billingTypeFor(r.policyNumber), // MIS/ADH → 'MIS'
      statusCode: Number(r.statusCode),
      statusLabel: rank.statusLabel,
      product: String(r.product || ''),
      recentDebits: Number(r.recentDebits) || 0,
      recentAmount: _round2(r.recentAmount),
      lastDebit: _iso(r.lastDebit),
      firstDebit: _iso(r.firstDebit),
      windowDays: WINDOW_DAYS,
      severity: rank.severity,
      priority: rank.priority,
      reason: `Policy is ${rank.statusLabel} but its RealPay mandate is still live and took `
        + `${Number(r.recentDebits) || 0} successful debit(s) totalling ${_round2(r.recentAmount).toFixed(2)} `
        + `in the last ${WINDOW_DAYS} days. A person must check why it is inactive and stop the mandate.`,
    };
  });
  // Rank: status severity first, then biggest money leaked, then most debits.
  out.sort((a, b) =>
    b.priority - a.priority
    || b.recentAmount - a.recentAmount
    || b.recentDebits - a.recentDebits);
  return out;
}

/**
 * fetchLeakingMandates(dsn) -> leak candidate rows, read live from the replica.
 * Throws on any failure so the caller falls back to an empty leak queue.
 */
async function fetchLeakingMandates(dsn) {
  if (!dsn) throw new Error('GRAPHITE_RO_DSN not set');
  const mysql = require('mysql2/promise'); // lazy — keeps the mappers test-only
  const conn = await mysql.createConnection({
    ...parseDsn(dsn),
    ssl: 'Amazon RDS', // verify against the bundled RDS CA — never skip cert checks (CFO 2026-07-21)
    connectTimeout: 15_000,
    multipleStatements: false,
  });
  try {
    const [rows] = await conn.query(LEAK_SQL);
    return buildLeakRows(rows);
  } finally {
    await conn.end().catch(() => {});
  }
}

module.exports = {
  WINDOW_DAYS,
  LEAK_SQL,
  buildLeakRows,
  fetchLeakingMandates,
};
