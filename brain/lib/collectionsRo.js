'use strict';
/**
 * collectionsRo.js — the READ-ONLY data pull that feeds the non-payment clock
 * (collectionsClock.js). Mirrors graphiteRo.js: mysql2 is lazy-required only
 * inside fetchAffectedPolicies() so the pure mappers stay unit-testable without
 * the driver or a database, all SQL is SELECT-only, and fetch* throws on any
 * error so the caller falls back to a fixture. ARMS STAY OFF — nothing here (or
 * downstream) sends, writes, cancels or debits. It only reads and computes.
 *
 * Scope (CFO 2026-07-18, ROUND 1): monthly-pay DOMESTIC + COMMERCIAL + INSTANT
 * (MIS). ANNUAL excluded via the SQL filters below.
 * Scope (CFO 2026-08-03, ACTIVE-BOOK): status = 1 only — see the POLICIES_SQL note.
 *
 * Data model discovered live (Graphite_live / MariaDB, read replica):
 *  - policies.status (tinyint): 1 = Active, 0 = Deactivated, 2 = Cancelled,
 *    3 = Expired (see ReconRealpayExceptions.php statusLabel). We scan status = 1
 *    ONLY (the active book); 0 = never-started/switched-off, 2/3 terminal.
 *  - Policy-number prefixes: MIS/ADH = Instant (MIS); DOMG = Domestic (DOM);
 *    COMG/COMD = Commercial (COM).
 *  - premium_freq (varchar): '1' = monthly, '3'/'annual' = annual (excluded),
 *    '2' = quarterly, '5'/'6' = other instalment plans. Instant policies usually
 *    store NULL and are monthly by default, so MIS accepts NULL/'1'/'monthly'.
 *  - policy_ledger: one 'Invoice' per month (invoice_date, invoice_amount) vs
 *    'Payment' credits — the schedule-driven ledger used for DOM/COM.
 *  - payment_transactions.status: 'Success'/'SUCCESSFUL'/'Paid' vs 'Failed';
 *    paymentMethod in RealPay/DPO/VCS (+ one-off Cash/N-Genius/orangeMoney/PayM8).
 *    NOTE: multiple 'Failed' rows in the SAME month are retries, not extra months
 *    — the mappers group by calendar month.
 *  - realpay_contract_installments.InstalmentStatus: S = success, F = failed,
 *    A = active/pending (the stale bucket behind GRA-0203), I = cancelled,
 *    W = processing, R = retry, E = error. Join to policies via
 *    realpay_client_contracts.contract_number (installments.policy_id is
 *    unreliable — see ReconRealpayExceptions.php). We pull the 'S' (success) rows
 *    ONLY, and fold them into successEvents so a RealPay-native success that the
 *    ledger / payment_transactions never recorded flips the policy to 'uncertain'
 *    (the GRA-0203 guard).
 *
 * DPA: customerName is sourced for internal display only and never leaves the box
 * to any external model. Ledger payment refs use the ledger row id, not the bank
 * narrative (which can embed a payer name).
 */

const clock = require('./collectionsClock');
const { age } = require('./ageing');
const { parseDsn } = require('./graphiteRo'); // reuse the shared DSN parser (not modified)

const WINDOW_DAYS = 400; // ~13 months: enough to establish 2-month streaks + a prior paid month

// PERF + CORRECTNESS: the clock ONLY counts months on/after RULE_START
// (classifyPolicy filters eligible months to >= ruleStart), so there is never any
// need to read transactions older than ~one month before the rule start. Right
// after go-live this shrinks the MIS pull from ~13 months (~1.3M rows, which
// tripped brain_ro's MAX_STATEMENT_TIME → the whole affected pull returned 0) to
// ~2 months. As `now` advances the SQL GREATEST() lets the window grow naturally,
// capped at WINDOW_DAYS for steady state — so this stays correct long-term.
const RULE_FLOOR = (() => {
  const t = Date.parse(clock.RULE_START_DATE + 'T00:00:00Z');
  return new Date(t - 35 * 86_400_000).toISOString().slice(0, 10); // ~1 month before RULE_START
})();
// The lower bound every windowed feed query uses: the LATER (tighter) of the
// steady-state window and the rule floor. GREATEST is evaluated server-side each
// run, so CURDATE() keeps moving while RULE_FLOOR is a fixed calendar date.
const WINDOW_FLOOR_SQL = `GREATEST(CURDATE() - INTERVAL ${WINDOW_DAYS} DAY, DATE('${RULE_FLOOR}'))`;

// ── shared helpers (kept local; mirror graphiteRo style) ─────────────────────
const _iso = (v) => {
  if (v == null) return null;
  if (v instanceof Date) return v.toISOString().slice(0, 10);
  const s = String(v).trim();
  return s ? s.slice(0, 10) : null;
};
const _num = (v) => (v == null || v === '' ? null : Number(v));
const _str = (v) => (v == null ? '' : String(v));
const _monthKey = (v) => { const d = _iso(v); return d ? d.slice(0, 7) : null; };
const _channel = (g) => {
  const s = String(g || '').trim().toUpperCase();
  if (s === 'REALPAY') return 'RealPay';
  if (s === 'DPO') return 'DPO';
  if (s === 'VCS') return 'VCS';
  return null; // one-off gateways (Cash/N-Genius/…) are not recurring debit channels
};
const SUCCESS_STATES = new Set(['success', 'successful', 'paid']);
const _isSuccess = (s) => SUCCESS_STATES.has(String(s || '').trim().toLowerCase());
const _isFailed = (s) => String(s || '').trim().toLowerCase() === 'failed';

/** billingType from the policy number prefix. */
function billingTypeFor(policyNumber) {
  const s = String(policyNumber || '').toUpperCase();
  if (s.startsWith('DOMG')) return 'DOM';
  if (s.startsWith('COMG') || s.startsWith('COMD')) return 'COM';
  return 'MIS'; // MIS* / ADH* and everything else in-scope is Instant
}

/**
 * scheduleMonths(billingStart, now) — the expected monthly billing periods from
 * the policy's billing start through `now` (capped to the pull window). Returns
 * [{ period:'YYYY-MM', due:'YYYY-MM-01' }] oldest→newest. We normalise every due
 * date to the 1st so a whole calendar month is one period regardless of the exact
 * debit day (retries/late debits within the month still count as that month).
 */
function scheduleMonths(billingStart, now) {
  const startIso = _iso(billingStart);
  const asOf = _iso(now) || new Date().toISOString().slice(0, 10);
  if (!startIso) return [];
  const windowStart = new Date(Date.parse(asOf + 'T00:00:00Z') - WINDOW_DAYS * 86_400_000)
    .toISOString().slice(0, 10);
  const effStart = startIso > windowStart ? startIso : windowStart;
  let y = Number(effStart.slice(0, 4));
  let m = Number(effStart.slice(5, 7)); // 1-12
  const endY = Number(asOf.slice(0, 4));
  const endM = Number(asOf.slice(5, 7));
  const out = [];
  while (y < endY || (y === endY && m <= endM)) {
    const period = `${y}-${String(m).padStart(2, '0')}`;
    out.push({ period, due: `${period}-01` });
    m += 1; if (m > 12) { m = 1; y += 1; }
  }
  return out;
}

// ════════════════════ IN-SCOPE POLICIES ════════════════════
const POLICY_SCOPE = `
      (
        ( (p.policyNumber LIKE 'MIS%' OR p.policyNumber LIKE 'ADH%')
            AND (p.premium_freq IS NULL OR p.premium_freq IN ('1','monthly')) )
        OR
        ( (p.policyNumber LIKE 'DOMG%' OR p.policyNumber LIKE 'COMG%' OR p.policyNumber LIKE 'COMD%')
            AND p.premium_freq = '1' )
      )`;

const POLICIES_SQL = `
  SELECT p.id                         AS id,
         p.policyNumber               AS policyNumber,
         p.product_id                 AS productId,
         pr.name                      AS product,
         p.status                     AS statusCode,
         p.premium                    AS premium,
         COALESCE(NULLIF(p.billingStartDate,''), DATE(p.policyActivatedDate)) AS billingStart,
         CONCAT_WS(' ', u.firstName, u.lastName) AS agent,
         CONCAT_WS(' ', c.firstName, c.lastName) AS customerName
  FROM policies p
  JOIN products pr ON pr.id = p.product_id
  LEFT JOIN users    u ON u.id = p.agent_id
  LEFT JOIN customer c ON c.id = p.customer_id
  WHERE p.status = 1
    AND ${POLICY_SCOPE}
`;
// ACTIVE BOOK ONLY (CFO 2026-08-03). We pull status = 1 (activated & in force,
// ~29k across all products; ~26.6k monthly in-scope) — NOT status IN (0,1).
// status 0 = "never switched on OR switched off" and on the live book that is
// ~108k policies, of which ~60.7k MIS were quoted and NEVER activated (never paid
// a pula because they never started). Feeding those made the clock's scan the
// "50,000-plus" pool the CFO flagged against the Graphite Sales Dashboard, whose
// real Active tile is 29,392. Never-started policies are not non-payers.
//
// ⚠️ LIFECYCLE NOTE for the future live-arm (Pramod's item — not built yet):
// classifyStage() only advances a policy to grace/cancel_candidate once it is
// deactivated (status 0). Today deactivation is compute-only (nothing flips the
// DB status), so active-only is exactly right. WHEN the deactivation arm is built,
// a policy the ENGINE deactivates must be re-included by policyNumber (persist the
// numbers it deactivates and OR them back into this pull) — otherwise it drops out
// of scope the moment its status flips to 0 and never reaches cancellation. Do NOT
// widen this back to status IN (0,1): that re-admits the ~108k legacy/never-started
// book. Re-include the specific engine-deactivated numbers only.

// ════════════════════ MIS — payment_transactions (all channels) ════════════════════
// One row per (policy, month, channel, status-bucket). We aggregate in JS.
const MIS_TX_SQL = `
  SELECT pt.policyNumber                        AS policyNumber,
         DATE_FORMAT(pt.created_at,'%Y-%m')      AS mon,
         pt.paymentMethod                        AS channel,
         pt.status                               AS txStatus,
         DATE(pt.created_at)                     AS txDate,
         pt.amount                               AS amount
  FROM payment_transactions pt
  WHERE pt.created_at >= ${WINDOW_FLOOR_SQL}
    AND (pt.policyNumber LIKE 'MIS%' OR pt.policyNumber LIKE 'ADH%')
    AND pt.policyNumber IS NOT NULL AND pt.policyNumber <> ''
`;

// ════════════════════ MIS — RealPay-native successes (GRA-0203 guard) ════════════════════
const REALPAY_SUCCESS_SQL = `
  SELECT p.policyNumber                          AS policyNumber,
         DATE(rci.InstalmentActionDate)          AS d,
         rci.InstalmentAmount                     AS amount
  FROM realpay_contract_installments rci
  JOIN realpay_client_contracts rcc ON rcc.contract_number = rci.contractNumber
  JOIN policies p ON p.id = rcc.policy_id
  WHERE rci.InstalmentStatus = 'S'
    AND rci.InstalmentActionDate >= ${WINDOW_FLOOR_SQL}
    AND ${POLICY_SCOPE}
`;

// ════════════════════ DOM/COM — schedule-driven ledger ════════════════════
const DOMCOM_INVOICES_SQL = `
  SELECT p.policyNumber AS policyNumber, pl.invoice_date AS invDate,
         pl.invoice_amount AS invAmount, pl.invoice_no AS invRef
  FROM policy_ledger pl JOIN policies p ON p.id = pl.policy_id
  WHERE pl.trans_type = 'Invoice' AND pl.deleted_at IS NULL
    AND pl.invoice_date >= ${WINDOW_FLOOR_SQL}
    AND (p.policyNumber LIKE 'DOMG%' OR p.policyNumber LIKE 'COMG%' OR p.policyNumber LIKE 'COMD%')
    AND p.premium_freq = '1' AND p.status = 1
`;
// DPA: payment ref = ledger row id (pl.id), NOT the bank narrative (pl.trans_ref).
const DOMCOM_PAYMENTS_SQL = `
  SELECT p.policyNumber AS policyNumber, pl.accounting_date AS payDate,
         pl.credit AS payAmount, pl.id AS payRef
  FROM policy_ledger pl JOIN policies p ON p.id = pl.policy_id
  WHERE pl.trans_type = 'Payment' AND pl.status = 'Paid' AND pl.credit > 0
    AND pl.deleted_at IS NULL
    AND pl.accounting_date >= ${WINDOW_FLOOR_SQL}
    AND (p.policyNumber LIKE 'DOMG%' OR p.policyNumber LIKE 'COMG%' OR p.policyNumber LIKE 'COMD%')
    AND p.premium_freq = '1' AND p.status = 1
`;

// ── PURE MAPPERS ─────────────────────────────────────────────────────────────

/**
 * buildMisMonths(policy, txRows) — for a MIS policy, mark each SCHEDULED month
 * paid/unpaid. A month is PAID if any SUCCESS transaction (on ANY channel — incl.
 * one-off Cash/N-Genius) landed in that calendar month; otherwise it is a FAILED
 * auto-debit month (the scheduled debit did not succeed on any channel). Retries
 * within a month collapse to one month. amount = the fixed monthly premium.
 * Returns { months, successEvents, channel }.
 */
function buildMisMonths(policy, txRows = [], now) {
  const premium = _num(policy.premium) || 0;
  // CFO 2026-07-21: ZERO transaction rows in the window is NOT proof of
  // non-payment (no debit order set up / cash-paid / paid outside the window).
  // Flag it so the clock forces signalConfidence 'uncertain' — never 'clean'.
  const noPaymentData = !txRows || txRows.length === 0;
  const paidMonths = new Set();
  const successEvents = [];
  const failByMonth = new Map(); // month → {channel, date} most recent recurring failure
  for (const r of txRows) {
    const mon = _monthKey(r.mon) || _monthKey(r.txDate);
    if (!mon) continue;
    if (_isSuccess(r.txStatus)) {
      paidMonths.add(mon);
      successEvents.push({ channel: _channel(r.channel) || _str(r.channel), date: _iso(r.txDate) });
    } else if (_isFailed(r.txStatus)) {
      const ch = _channel(r.channel); // only recurring channels name the debit channel
      if (ch) {
        const prev = failByMonth.get(mon);
        const d = _iso(r.txDate);
        if (!prev || (d && d > prev.date)) failByMonth.set(mon, { channel: ch, date: d });
      }
    }
  }
  const months = scheduleMonths(policy.billingStart, now).map((s) => ({
    period: s.period,
    due: s.due,
    amount: premium,
    paid: paidMonths.has(s.period),
  }));
  // primary channel = most recent recurring failed-debit channel across the record
  let channel = null; let latest = null;
  for (const { channel: ch, date } of failByMonth.values()) {
    if (!latest || (date && date > latest)) { latest = date; channel = ch; }
  }
  return { months, successEvents, channel: channel || 'RealPay', noPaymentData };
}

/**
 * buildDomComMonths(policy, invoiceRows, paymentRows) — for a DOM/COM policy,
 * derive per-month paid/unpaid from the schedule-driven ledger. Payments are
 * irregular/bulk (domain doc), so we ALLOCATE them to invoices oldest-first via
 * ageing.age() and treat a monthly invoice as UNPAID only if it is still open.
 * Returns { months, successEvents }.
 */
function buildDomComMonths(policy, invoiceRows = [], paymentRows = [], now) {
  const invoices = invoiceRows.map((r) => ({ date: _iso(r.invDate), amount: _num(r.invAmount) || 0, ref: r.invRef != null ? String(r.invRef) : null }));
  const payments = paymentRows.map((r) => ({ date: _iso(r.payDate), amount: _num(r.payAmount) || 0, ref: r.payRef != null ? String(r.payRef) : null }));
  const asOf = _iso(now) || new Date().toISOString().slice(0, 10);
  const res = age(invoices, payments, asOf);
  const openByMonth = new Map();
  for (const oi of res.openInvoices) {
    const mon = _monthKey(oi.date);
    if (mon) openByMonth.set(mon, (openByMonth.get(mon) || 0) + oi.open);
  }
  // Build a period per invoiced month; paid iff nothing from that month is still open.
  const months = [];
  const seen = new Set();
  for (const inv of invoices) {
    const mon = _monthKey(inv.date);
    if (!mon || seen.has(mon)) continue;
    seen.add(mon);
    const open = openByMonth.get(mon) || 0;
    const monthAmt = invoices.filter((i) => _monthKey(i.date) === mon).reduce((s, i) => s + i.amount, 0);
    months.push({ period: mon, due: `${mon}-01`, amount: open > 0 ? open : monthAmt, paid: open <= 0 });
  }
  months.sort((a, b) => (a.due < b.due ? -1 : a.due > b.due ? 1 : 0));
  const successEvents = payments.map((p) => ({ channel: 'ledger', date: p.date }));
  return { months, successEvents };
}

/**
 * assemblePolicyInputs(parts) — join the per-policy pieces into the normalised
 * inputs consumed by collectionsClock.classifyAll. `parts`:
 *   { policies, misTxByPolicy, realpaySuccessByPolicy, invoicesByPolicy, paymentsByPolicy }
 */
function assemblePolicyInputs(parts = {}, now) {
  const {
    policies = [], misTxByPolicy = new Map(), realpaySuccessByPolicy = new Map(),
    invoicesByPolicy = new Map(), paymentsByPolicy = new Map(),
  } = parts;
  const inputs = [];
  for (const p of policies) {
    const billingType = billingTypeFor(p.policyNumber);
    const policyStatus = Number(p.statusCode) === 0 ? 'deactivated' : 'active';
    const base = {
      policyNumber: _str(p.policyNumber),
      customerName: _str(p.customerName),
      productId: _num(p.productId),
      product: _str(p.product),
      agent: p.agent ? _str(p.agent) : null,
      billingType,
      policyStatus,
    };
    let months = []; let successEvents = []; let channel = 'ledger'; let sourceConflict = false;
    let noPaymentData = false;
    if (billingType === 'MIS') {
      const built = buildMisMonths(p, misTxByPolicy.get(p.policyNumber) || [], now);
      months = built.months; successEvents = built.successEvents; channel = built.channel;
      noPaymentData = built.noPaymentData;
      // GRA-0203 guard: fold RealPay-native successes in as success signals. If any
      // RealPay 'S' isn't reflected as a success in payment_transactions, it becomes
      // a later-success event → the policy is flagged 'uncertain', not silently listed.
      const rp = realpaySuccessByPolicy.get(p.policyNumber) || [];
      for (const s of rp) {
        successEvents.push({ channel: 'RealPay', date: _iso(s.d) });
        if (!built.successEvents.some((e) => e.date === _iso(s.d))) sourceConflict = true;
      }
    } else {
      const built = buildDomComMonths(p, invoicesByPolicy.get(p.policyNumber) || [], paymentsByPolicy.get(p.policyNumber) || [], now);
      months = built.months; successEvents = built.successEvents; channel = 'ledger';
    }
    inputs.push({ ...base, channel, months, successEvents, sourceConflict, noPaymentData });
  }
  return inputs;
}

function _groupBy(rows, key) {
  const m = new Map();
  for (const r of rows || []) {
    const k = r[key];
    if (!m.has(k)) m.set(k, []);
    m.get(k).push(r);
  }
  return m;
}

/**
 * fetchAffectedPolicies(dsn, now) -> AffectedPolicy[] (canonical shape), read live
 * from the replica. Throws on any failure so the caller falls back to a fixture —
 * a bad pull must never break the arms-off container.
 */
async function fetchAffectedPolicies(dsn, now) {
  if (!dsn) throw new Error('GRAPHITE_RO_DSN not set');
  const mysql = require('mysql2/promise'); // lazy — keeps mappers test-only
  const conn = await mysql.createConnection({
    ...parseDsn(dsn),
    ssl: 'Amazon RDS', // verify against the bundled RDS CA — never skip cert checks (CFO 2026-07-21)
    connectTimeout: 15_000,
    multipleStatements: false,
  });
  try {
    const [polRows] = await conn.query(POLICIES_SQL);
    const [misRows] = await conn.query(MIS_TX_SQL);
    const [rpRows] = await conn.query(REALPAY_SUCCESS_SQL);
    const [invRows] = await conn.query(DOMCOM_INVOICES_SQL);
    const [payRows] = await conn.query(DOMCOM_PAYMENTS_SQL);
    const inputs = assemblePolicyInputs({
      policies: polRows,
      misTxByPolicy: _groupBy(misRows, 'policyNumber'),
      realpaySuccessByPolicy: _groupBy(rpRows, 'policyNumber'),
      invoicesByPolicy: _groupBy(invRows, 'policyNumber'),
      paymentsByPolicy: _groupBy(payRows, 'policyNumber'),
    }, now);
    return clock.classifyAll(inputs, now);
  } finally {
    await conn.end().catch(() => {});
  }
}

module.exports = {
  fetchAffectedPolicies,
  assemblePolicyInputs,
  buildMisMonths,
  buildDomComMonths,
  billingTypeFor,
  scheduleMonths,
  parseDsn,
  POLICIES_SQL, MIS_TX_SQL, REALPAY_SUCCESS_SQL, DOMCOM_INVOICES_SQL, DOMCOM_PAYMENTS_SQL,
  WINDOW_DAYS,
};
