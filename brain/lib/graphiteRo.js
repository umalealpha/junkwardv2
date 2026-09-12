'use strict';
/**
 * graphiteRo.js — the read-only Graphite data pull for the daily sweep.
 *
 * READ-ONLY by construction: connects with a SELECT-only account (brain_ro) to
 * the read replica, runs SELECT queries only, and returns the exact record
 * shape `brains.sweep()` consumes (see fixtures/sample.json). It NEVER writes
 * to Graphite. Arms stay off — this only feeds the monitor.
 *
 * All five feeds are mapped against the live schema:
 *   claims       ← claims + policies
 *   collections  ← payment_transactions (RealPay/DPO/VCS failures) + products
 *   debtors      ← policy_ledger (invoices/payments) → fed raw to ageing.js
 *   kyc          ← customer_kyc (verdict/metadata ONLY — no raw PII)
 *   events       ← total_loss (write-offs) + payment_received (only the two that
 *                  derive reliably; others deferred — see DEFERRED_EVENTS)
 *
 * Documented data caveats (correct-data-over-speed):
 *  - claims.coverStart/estimate: null (not cleanly sourced) — quiets a couple of
 *    fraud sub-checks, never wrong values.
 *  - kyc.ocr.confidence/detectedType: null (OCR store empty); remindersSent 0;
 *    product null. Drives outstanding-doc chasing, not scan-quality review.
 *  - collections: this naive failed-debit feed is SUPERSEDED by collectionsRo.js
 *    (2-consecutive-months clock + GRA-0203 guard); the sweep no longer consumes
 *    this shape — swap to collectionsRo when wiring the live feed
 *    until a product→grace-class map is defined. remindersSent 0 (brain store).
 *  - debtors: payment ref = ledger row id (NOT the bank narrative — DPA).
 *  - events: total_loss + payment_received only; the 2-day window can re-surface
 *    rows across days — dedupe by claimNumber/reference before any arm is wired.
 *
 * mysql2 is lazy-required only inside fetchRecords() so the pure mappers stay
 * unit-testable without the driver installed.
 */

const CLAIMS_WINDOW_DAYS      = 60;
const COLLECTIONS_WINDOW_DAYS = 45;
const DEBTORS_WINDOW_DAYS     = 365;
const KYC_WINDOW_DAYS         = 400;
const EVENTS_WINDOW_DAYS      = 2;

// ── shared helpers ───────────────────────────────────────────────
const _d = (v) => {
  if (v == null) return null;
  if (v instanceof Date) return v.toISOString().slice(0, 10);
  const s = String(v).trim();
  return s ? s.slice(0, 10) : null;
};
const _num  = (v) => (v == null || v === '' ? null : Number(v));
const _str  = (v) => (v == null ? '' : String(v));
const _bool = (v) => v === 1 || v === true || v === '1';
const _gateway = (g) => {
  const s = String(g || '').trim().toUpperCase();
  if (s === 'REALPAY') return 'RealPay';
  if (s === 'DPO') return 'DPO';
  if (s === 'VCS') return 'VCS';
  return String(g || '');
};
const _daysSince = (dateVal, now) => {
  const d = _d(dateVal);
  if (!d) return 0;
  const then = Date.parse(d + 'T00:00:00Z');
  if (Number.isNaN(then)) return 0;
  const today = Date.parse((_d(now) || new Date().toISOString().slice(0, 10)) + 'T00:00:00Z');
  return Math.max(0, Math.floor((today - then) / 86_400_000));
};

// Feeds still not mapped to live tables (none — all five are live).
const PENDING_FEEDS = [];
// Events intentionally NOT derived yet (would fire wrong actions if guessed).
// claim_registered is now DERIVED (see CLAIM_REGISTERED_SQL). claim_paid stays
// deferred: no reliable payment-date column exists (only payment_amt with no
// date; the only dated payment log is for VOIDED payments), so any trigger would
// misfire and, once armed, wrongly notify customers "claim paid" (T6, CFO 27 Jul —
// held pending a real payment-date source).
const DEFERRED_EVENTS = ['refund_due', 'debit_order_failed', 'renewal_due',
  'assessment_booked', 'assessment_completed', 'claim_paid', 'complaint_logged'];

// ════════════════════ CLAIMS ════════════════════
const CLAIMS_SQL = `
  SELECT c.claim_number   AS claimNumber,
         c.claim_type     AS claimType,
         c.incident_date  AS dateOfLoss,
         c.reported_date  AS dateReported,
         p.sum_assured    AS sumInsured
  FROM claims c
  LEFT JOIN policies p ON p.id = c.policy_id
  WHERE c.reported_date >= (CURDATE() - INTERVAL ${CLAIMS_WINDOW_DAYS} DAY)
`;
function buildClaims(rows) {
  return (rows || []).map((r) => ({
    claimNumber:  _str(r.claimNumber),
    claimType:    _str(r.claimType),
    coverStart:   null,          // not yet sourced (documented)
    dateOfLoss:   _d(r.dateOfLoss),
    dateReported: _d(r.dateReported),
    estimate:     null,          // not yet sourced (documented)
    sumInsured:   _num(r.sumInsured),
  }));
}

// ════════════════════ COLLECTIONS ════════════════════
const COLLECTIONS_SQL = `
  SELECT f.policyNumber   AS policyNumber,
         pr.name          AS product,
         f.paymentMethod  AS gateway,
         f.amount         AS amount,
         DATE(f.created_at) AS failedAt
  FROM payment_transactions f
  LEFT JOIN policies  pol ON pol.policyNumber = f.policyNumber
  LEFT JOIN products  pr  ON pr.id            = pol.product_id
  WHERE f.status = 'Failed'
    AND f.paymentMethod IN ('RealPay','DPO','VCS')
    AND f.created_at >= (CURDATE() - INTERVAL ${COLLECTIONS_WINDOW_DAYS} DAY)
    AND NOT EXISTS (
      SELECT 1 FROM payment_transactions l
      WHERE l.policyNumber = f.policyNumber AND l.status = 'Failed'
        AND l.paymentMethod IN ('RealPay','DPO','VCS') AND l.created_at > f.created_at)
    AND NOT EXISTS (
      SELECT 1 FROM payment_transactions s
      WHERE s.policyNumber = f.policyNumber
        AND s.status IN ('Success','SUCCESSFUL','Paid') AND s.created_at >= f.created_at)
`;
function buildCollections(rows, now) {
  return (rows || []).map((r) => ({
    policyNumber:  _str(r.policyNumber),
    product:       _str(r.product),
    gateway:       _gateway(r.gateway),
    amount:        _num(r.amount),
    daysSinceFail: _daysSince(r.failedAt, now),
    remindersSent: 0, // no Graphite source — dunning counts live in the brain store
  }));
}

// ════════════════════ DEBTORS ════════════════════
// ACTIVE BOOK ONLY (CFO 2026-08-04). This selector had NO join to `policies` at
// all, so it swept every policy that had ever been invoiced in the window —
// including status 2 (Cancelled) and status 0 (quoted but never switched on).
// That is why the console reported 51,610 debtor exceptions against an active
// book of only 29,392 policies: the count was larger than the book it claimed to
// describe.
//
// This is the SAME defect the CFO already ruled on for the non-payment clock on
// 2026-08-03 (PR #1785, collectionsRo.js: status IN (0,1) → status = 1, dropping
// ~108k dead policies of which ~60.7k were MIS quotes that never started). That
// fix was applied to the clock ONLY — the debtors feed was missed. Same ruling,
// same filter, second location.
//
// `p.status = 1` = Active (29,392 as at 2026-08-03, ties exactly to the Graphite
// Sales Dashboard tile). Guard test: debtorsAudit.test.js blocks a regression to
// an unfiltered or IN(0,1) selector.
const DEBTOR_POLICY_SUBQUERY = `
  SELECT s.policy_id FROM policy_ledger s
  JOIN policies dp ON dp.id = s.policy_id AND dp.status = 1
  WHERE s.deleted_at IS NULL
    AND ( (s.trans_type = 'Invoice' AND s.invoice_date >= (CURDATE() - INTERVAL ${DEBTORS_WINDOW_DAYS} DAY))
       OR (s.trans_type = 'Payment' AND s.status = 'Paid' AND s.accounting_date >= (CURDATE() - INTERVAL ${DEBTORS_WINDOW_DAYS} DAY)) )
  GROUP BY s.policy_id
  HAVING SUM(CASE WHEN s.trans_type = 'Invoice' THEN s.invoice_amount ELSE 0 END)
       - SUM(CASE WHEN s.trans_type = 'Payment' AND s.status = 'Paid' THEN s.credit ELSE 0 END) > 0
`;

// ── ORPHAN BALANCES (Rule 3) — the MIRROR of the selector above ─────────────
//
// DEBTOR_POLICY_SUBQUERY is pinned to `status = 1` and must stay that way; the
// guard test in debtorsAudit.test.js blocks any widening because that filter is
// the fix for the 51,610 defect. Correct — and it leaves the dead book unwatched.
//
// This query is the other half, NOT a change to that one. It selects the
// policies that the active-book audit cannot see by design: status 0 (Inactive)
// and status 2 (Cancelled) still carrying a balance. Its own guard test blocks
// it from ever drifting onto status 1.
//
// Direct read of production on 2026-09-07: 89,597 dead policies carrying
// P58,566,761 net, which is 64% of the whole debtors book.
//
// THREE THINGS THIS QUERY GETS RIGHT AND A NAIVE ONE DOES NOT:
//
//  1. NO TRIPLE-COUNT. Every invoice writes THREE ledger lines — an 'Invoice'
//     header plus 'Invoice Premium' and 'Invoice VAT' components, where the
//     header equals the sum of the other two. Summing all three inflates the
//     book by ~P450m. Verified 2026-09-07: header P448,446,847.80 vs components
//     P449,957,102.07, agreeing to within P1 on all but 93 policies. Graphite's
//     own `v_policy_ledger_summary` view sums every debit line indiscriminately
//     and therefore CANNOT be used for a debtors figure. Header lines only here.
//
//  2. IT DOES NOT READ `policies.balance`. That column reads 0.00 on all
//     213,438 policies and is never populated — not null, zero. Any report
//     that trusts it shows no debtors at all. The book is recomputed from the
//     ledger every time, which is also what Rules 1 and 2 do.
//
//  3. THE ACTIVE-POLICY MATCH IS ON customer_id, NOT INSURED NAME. Name
//     matching merges different people who share a name and misses the same
//     person spelled two ways. `has_active_policy` is what splits the list into
//     the reallocatable cases (7,972 / P1,688,611) and the stranded ones
//     (81,625 / P56,878,150) that reallocation can never clear.
//
// No date window, deliberately: the oldest orphans are the point. Test and
// draft policies excluded. Future-dated lines excluded so the figure is a true
// as-at-today position. Returns policy number and customer id only — no client
// name, so the feed carries no PII.
//
// PERF: this GROUP BY over policy_ledger is the same shape as the selection
// above and carries the same risk against brain_ro's MAX_STATEMENT_TIME. runFeeds
// wraps every feed so a timeout degrades to empty and is logged rather than
// aborting the sweep. The durable fix is a policy_ledger (policy_id,
// accounting_date, trans_type) index — see the deploy note.
//
// The aggregate is filtered in an OUTER select, not in HAVING. `policy_ledger`
// has its own `balance` column, so a bare `HAVING ABS(balance) > 0.005` is
// ambiguous between that column and the select alias. MySQL resolves it to the
// alias and the query does return the right rows, but relying on that is
// fragile — a verification run on 2026-09-07 hit the same ambiguity as soon as
// the alias was referenced from an ORDER BY. Filtering outside the grouped
// select removes the ambiguity completely and keeps the column name the audit
// module expects.
const ORPHAN_POLICY_SQL = `
  SELECT * FROM (
  SELECT
    p.id            AS policy_id,
    p.policyNumber  AS policy_number,
    p.status        AS policy_status,
    p.customer_id   AS customer_id,
    CASE WHEN act.customer_id IS NULL THEN 0 ELSE 1 END AS has_active_policy,
    ROUND(SUM(
      CASE
        WHEN l.trans_type IN ('Invoice', 'Refund', 'Reverse Payment', 'Credit Note')
          THEN COALESCE(l.debit, 0)
        WHEN l.trans_type IN ('Payment', 'Reverse Invoice')
          THEN -COALESCE(l.credit, 0)
        ELSE 0
      END
    ), 2)           AS balance,
    MAX(l.accounting_date) AS last_movement
  FROM policy_ledger l
  JOIN policies p
    ON p.id = l.policy_id
   AND p.status IN (0, 2)
   AND p.is_test_policy = 0
   AND p.is_draft = 0
  LEFT JOIN (
    SELECT DISTINCT customer_id
    FROM policies
    WHERE status = 1 AND is_test_policy = 0 AND is_draft = 0
  ) act ON act.customer_id = p.customer_id
  WHERE l.deleted_at IS NULL
    AND (l.accounting_date <= CURDATE() OR l.accounting_date IS NULL)
  GROUP BY p.id, p.policyNumber, p.status, p.customer_id, act.customer_id
  ) orph
  WHERE ABS(orph.balance) > 0.005
`;

// PERF: the debtor-selection (net open balance, DEBTOR_POLICY_SUBQUERY) used to
// be inlined in BOTH the invoice and payment queries, so its expensive
// GROUP BY/HAVING scan over policy_ledger ran TWICE per sweep and tripped
// brain_ro's MAX_STATEMENT_TIME (the debtors feed timed out → empty every run).
// runFeeds now runs the selection ONCE and passes the resulting policy ids here
// as an explicit integer list. ids are DB-sourced and coerced with Number() in
// runFeeds, so the IN list is injection-safe. (Definitive fix for the residual
// scan cost is a policy_ledger index — see the deploy note.)
function debtorInvoicesSql(idList) {
  return `
  SELECT p.policyNumber AS account, pl.invoice_date AS invDate,
         pl.invoice_amount AS invAmount, pl.invoice_no AS invRef
  FROM policy_ledger pl JOIN policies p ON p.id = pl.policy_id
  WHERE pl.trans_type = 'Invoice' AND pl.deleted_at IS NULL
    AND pl.invoice_date >= (CURDATE() - INTERVAL ${DEBTORS_WINDOW_DAYS} DAY)
    AND pl.policy_id IN (${idList})
`;
}
// DPA: payment ref = ledger row id (pl.id), NOT pl.trans_ref (bank narratives can
// embed a payer name). ageing.js allocates FIFO and never needs the narrative.
function debtorPaymentsSql(idList) {
  return `
  SELECT p.policyNumber AS account, pl.accounting_date AS payDate,
         pl.credit AS payAmount, pl.id AS payRef
  FROM policy_ledger pl JOIN policies p ON p.id = pl.policy_id
  WHERE pl.trans_type = 'Payment' AND pl.status = 'Paid' AND pl.credit > 0
    AND pl.deleted_at IS NULL
    AND pl.accounting_date >= (CURDATE() - INTERVAL ${DEBTORS_WINDOW_DAYS} DAY)
    AND pl.policy_id IN (${idList})
`;
}
function buildDebtors(invoiceRows, paymentRows, asOf) {
  const asof = _d(asOf) || new Date().toISOString().slice(0, 10);
  const byAcct = new Map();
  const rec = (a) => {
    const key = _str(a);
    if (!byAcct.has(key)) byAcct.set(key, { account: key, asOf: asof, invoices: [], payments: [] });
    return byAcct.get(key);
  };
  for (const r of invoiceRows || []) {
    rec(r.account).invoices.push({ date: _d(r.invDate), amount: _num(r.invAmount), ref: r.invRef != null ? String(r.invRef) : null });
  }
  for (const r of paymentRows || []) {
    rec(r.account).payments.push({ date: _d(r.payDate), amount: _num(r.payAmount), ref: r.payRef != null ? String(r.payRef) : null });
  }
  return Array.from(byAcct.values());
}

// ════════════════════ KYC (verdict/metadata ONLY — no raw PII) ════════════════════
const KYC_DOC_CATALOG = [
  { type: 'omang_front',                p: 'omang_front_present',  s: 'omang_front_status',  e: 'omang_expiry' },
  { type: 'omang_back',                 p: 'omang_back_present',   s: 'omang_back_status',   e: null },
  { type: 'passport',                   p: 'passport_present',     s: 'passport_status',     e: 'passport_expiry' },
  { type: 'proof_of_income',            p: 'poi_present',          s: 'poi_status',          e: 'income_expiry' },
  { type: 'proof_of_residence',         p: 'por_present',          s: 'por_status',          e: 'residence_expiry' },
  { type: 'drivers_licence',            p: 'dl_present',           s: 'dl_status',           e: 'license_expiry' },
  { type: 'kyc_form',                   p: 'kyc_form_present',     s: 'kyc_form_status',     e: null },
  { type: 'certificate_of_incorporation', p: 'coi_present',       s: 'coi_status',          e: null },
  { type: 'directors_ids',             p: 'directors_id_present', s: 'directors_id_status', e: null },
];
// DPA: reads the MASKED VIEW `brain_customer_kyc` (presence booleans + status +
// expiry only), NOT the base customer_kyc table — so brain_ro never has access to
// the raw Omang/passport/proof-of-residence/proof-of-income document columns. The
// presence flags are precomputed in the view; this query just selects them. The
// view must expose exactly the KYC_DOC_CATALOG p/s/e column names (see the view
// DDL in D:\tmp\brain_ro_grant_plan_2026-07-23.md). Until the view exists +
// brain_ro is granted SELECT on it, this feed degrades to empty (per-feed
// resilience) — it never reads the raw table.
const KYC_SQL = `
  SELECT k.id AS kycId, NULL AS product,
         DATEDIFF(CURDATE(), k.created_at) AS firstRequestedDaysAgo,
         (SELECT COUNT(*) FROM kyc_reminder_log rl WHERE rl.customer_id = k.customer_id) AS remindersSent,
         k.omang_front_present, k.omang_front_status, k.omang_expiry,
         k.omang_back_present,  k.omang_back_status,
         k.passport_present,    k.passport_status, k.passport_expiry,
         k.poi_present,         k.poi_status, k.income_expiry,
         k.por_present,         k.por_status, k.residence_expiry,
         k.dl_present,          k.dl_status, k.license_expiry,
         k.kyc_form_present,    k.kyc_form_status,
         k.coi_present,         k.coi_status,
         k.directors_id_present, k.directors_id_status
  FROM brain_customer_kyc k
  WHERE k.created_at >= (CURDATE() - INTERVAL ${KYC_WINDOW_DAYS} DAY)
    AND k.status IN ('Unchecked','Unapprove','Recheck','Recheck(KYC Expired)')
    -- ACTIVE BOOK ONLY (CFO 26 Jul, same fix as the census status=1 change):
    -- only chase KYC for customers who hold an in-force policy. Without this the
    -- Compliance queue was dominated by never-activated / lapsed customers
    -- (verified on the replica 2026-07-27: 5,679 -> 564, ~90% fewer).
    AND EXISTS (SELECT 1 FROM policies p WHERE p.customer_id = k.customer_id AND p.status = 1)
  ORDER BY k.created_at DESC
`;
function buildKyc(rows) {
  return (rows || []).map((r) => {
    const docs = KYC_DOC_CATALOG.filter((d) => r[d.p] != null).map((d) => ({
      type: d.type,
      present: _bool(r[d.p]),
      ocr: {
        detectedType: null, // DPA: only inside raw ocr_extracted → excluded
        confidence: r[`${d.type}_confidence`] != null ? _num(r[`${d.type}_confidence`]) : null,
        expiry: d.e ? _d(r[d.e]) : null,
      },
    }));
    return {
      ref: r.kycId != null ? `KYC-${r.kycId}` : '',
      product: r.product != null ? String(r.product) : null,
      remindersSent: _num(r.remindersSent) || 0,
      firstRequestedDaysAgo: _num(r.firstRequestedDaysAgo),
      docs,
    };
  });
}

// ════════════════════ EVENTS (only the reliably-derivable two) ════════════════════
const TOTAL_LOSS_SQL = `
  SELECT c.claim_number AS claimNumber, p.policyNumber AS policyNumber, SUM(crc.balance) AS amount
  FROM claim_reserves_coverages crc
  JOIN claims c ON c.id = crc.claim_id
  LEFT JOIN policies p ON p.id = c.policy_id
  WHERE crc.write_off = 1 AND crc.write_off_at >= (CURDATE() - INTERVAL ${EVENTS_WINDOW_DAYS} DAY)
  GROUP BY c.claim_number, p.policyNumber
`;
const PAYMENT_RECEIVED_SQL = `
  SELECT pt.policyNumber AS policyNumber, pt.amount AS amount, pt.referenceNumber AS reference
  FROM payment_transactions pt
  WHERE pt.status = 'SUCCESS' AND pt.is_refund = 0 AND pt.is_ledger = 1 AND pt.reversal_date IS NULL
    AND pt.created_at >= (CURDATE() - INTERVAL ${EVENTS_WINDOW_DAYS} DAY)
    AND pt.policyNumber IS NOT NULL AND pt.policyNumber <> ''
`;
// claim_registered (T6): a new claim was booked in the last EVENTS_WINDOW_DAYS.
// created_at is the registration timestamp. Deduped by claim_number (one event per
// claim). Drives eventEngine's 'claim_received' customer notify (arms-off → recorded
// only). No amount context — a fresh claim has no settled figure.
const CLAIM_REGISTERED_SQL = `
  SELECT c.claim_number AS claimNumber, p.policyNumber AS policyNumber
  FROM claims c
  LEFT JOIN policies p ON p.id = c.policy_id
  WHERE c.created_at >= (CURDATE() - INTERVAL ${EVENTS_WINDOW_DAYS} DAY)
    AND c.claim_number IS NOT NULL AND c.claim_number <> ''
  GROUP BY c.claim_number, p.policyNumber
`;
function buildEvents(rowsByType = {}) {
  const events = [];
  for (const r of rowsByType.totalLoss || []) {
    const ctx = {};
    if (_str(r.claimNumber)) ctx.claimNumber = _str(r.claimNumber);
    if (_str(r.policyNumber)) ctx.policyNumber = _str(r.policyNumber);
    if (_num(r.amount) != null) ctx.amount = _num(r.amount);
    events.push({ event: 'total_loss', ctx });
  }
  for (const r of rowsByType.paymentReceived || []) {
    const ctx = {};
    if (_str(r.policyNumber)) ctx.policyNumber = _str(r.policyNumber);
    if (_num(r.amount) != null) ctx.amount = _num(r.amount);
    events.push({ event: 'payment_received', ctx });
  }
  for (const r of rowsByType.claimRegistered || []) {
    const ctx = {};
    if (_str(r.claimNumber)) ctx.claimNumber = _str(r.claimNumber);
    if (_str(r.policyNumber)) ctx.policyNumber = _str(r.policyNumber);
    events.push({ event: 'claim_registered', ctx });
  }
  return events;
}

// ════════════════════ WRITTEN PREMIUM (Gross Written Premium by month) ════════
// COUNTS/TOTALS ONLY (no PII) — Finance intelligence feed from policy_actions.
//
// Dedup: within a policy TERM period (policy_id, effective_from, effective_to) keep
// only the LATEST action (MAX(id)), so an endorsed/re-issued period is counted once
// (its current version), never once per superseded row. Only status='ISSUED'
// (in-force) and not soft-deleted. CANCEL contributes NEGATIVE (premium reversal)
// via -ABS — robust regardless of how the sign is stored (verified on the replica:
// CANCEL.premium is already negative, CANCEL.annual_premium positive; -ABS makes
// both reduce GWP consistently). Keyed on transaction_date.
//
// Excluded from the monthly buckets by design: future-dated actions (~80,
// pre-issued renewals) and undated actions (~4,600, NULL transaction_date) — a
// month bucket needs a real transaction month. The window bounds the scan.
const WRITTEN_PREMIUM_WINDOW_DAYS = 730; // ~2 financial years
const WRITTEN_PREMIUM_SQL = `
  SELECT DATE_FORMAT(a.transaction_date, '%Y-%m') AS ym,
         COUNT(*) AS n,
         SUM(CASE WHEN a.transaction_type = 'CANCEL' THEN -ABS(a.premium)        ELSE a.premium END)        AS gwp,
         SUM(CASE WHEN a.transaction_type = 'CANCEL' THEN -ABS(a.annual_premium) ELSE a.annual_premium END) AS gwpAnnual
  FROM policy_actions a
  JOIN (
    SELECT MAX(id) AS id
    FROM policy_actions
    WHERE status = 'ISSUED' AND deleted_at IS NULL
      AND transaction_date >= (CURDATE() - INTERVAL ${WRITTEN_PREMIUM_WINDOW_DAYS} DAY)
      AND transaction_date <= CURDATE()
    GROUP BY policy_id, effective_from, effective_to
  ) keep ON keep.id = a.id
  GROUP BY ym
  ORDER BY ym DESC
`;
function buildWrittenPremium(rows) {
  const byMonth = (rows || []).map((r) => ({
    month:     _str(r.ym),
    count:     _num(r.n) || 0,
    gwp:       Math.round((_num(r.gwp) || 0) * 100) / 100,
    gwpAnnual: r.gwpAnnual == null ? null : Math.round(_num(r.gwpAnnual) * 100) / 100,
  }));
  const totalGwp   = byMonth.reduce((s, m) => s + (m.gwp || 0), 0);
  const totalCount = byMonth.reduce((s, m) => s + (m.count || 0), 0);
  return { byMonth, totalGwp: Math.round(totalGwp * 100) / 100, totalCount };
}

// ════════════════════ ANALYTICS DATASETS ════════════════════
// READ-ONLY, aggregate-only feeds for the Alpha Brain Data-Analytics workbook
// tabs and the Graphite→Omni push (shapes match the Omni Appendix-A contract, so
// they double as the push payload rows). SELECT-only, arms-off — this only READS
// Graphite and never writes.
//
// DPA HARD LINE. Every row keys ONLY on policy/claim number, GFS reference,
// broker (agency name), agent id, product/group/branch label and counts/money.
// NO customer name, Omang/ID, bank, address or DOB is selected anywhere. Any KYC
// read goes through the brain_customer_kyc MASKED VIEW (presence booleans), never
// the raw customer_kyc table — identical to complianceCensus.js.
//
// MONEY. All BWP. Premium is reported EX-VAT. ASSUMPTION (to verify on the
// replica): policy_actions.premium is stored VAT-INCLUSIVE — the same convention
// the domain doc records for policies.premium (VAT-incl) and the reason the
// existing GWP feed labels its SUM "gwp" (gross). Ex-VAT is therefore derived by
// dividing by 1.14 (the flat 14% the task fixes). This flat rate intentionally
// ignores policies.vat_percent (region-based) and the historical 12% Motor window
// (2021-03-31 → 2022-08-01); if a sample policy's action premium turns out to be
// stored NET, drop the /1.14. VAT_DIVISOR is named so it is a one-line change.
//
// RESERVE = the estimated cost of the claim NET of recoveries = SUM(reserve_amt)
// − SUM(subrogation_reserve) − SUM(salvage_reserve), over claim_reserves_coverages
// rows that are NOT voided (is_payment_voided NULL or 0) — matching the Graphite
// claim screen (Admin/ClaimsController.php:434) so the workbook and the screens
// agree (CFO/Babusi 8–9 Sep; salvage was ~P230k / 1.7% of the FY brokered book,
// previously overstated). PAID/payment = SUM(payment_amt) on the same non-voided
// rows (gross claim payment, as Babusi specified — recoveries are not netted from
// payments). The netting is applied via NET_RESERVE_ROW at every reserve site so
// broker_lr reconciles to the book totals. (Was gross SUM(reserve_amt).)
//
// FY = 1 July → 30 June (FY27 = Jul 2026 – Jun 2027). "YTD" = FY-to-date.
//
// CLAIM DATE BASIS (CFO 2026-09-02 — "wrong figures and stale"): claims.reported_date
// is NULL on ~97% of the book (only the FNOL/new-claim path sets it; the tracker
// sync does not), so an FY filter on reported_date silently dropped ~99% of claims
// (claims_by_type showed 53 of 4,502). Fix:
//   * claims_by_type + claims_by_group = ALL-TIME book totals (no date filter),
//     consistent with major_claims (also all-time). These are book/count cards.
//   * loss_ratio_detail + broker_lr stay FY-scoped for a like-with-like ratio vs
//     FY written premium, but scope on a RELIABLE date —
//     COALESCE(reported_date, incident_date, DATE(created_at)) — not the sparse
//     reported_date alone (which yielded 55 FY claims; the reliable date yields ~400).
// inforce_by_type / kyc_completeness are point-in-time (the active book as at today).
// All documented so Pramod/Babusi can retune.

// VERIFIED ON REPLICA 2026-08-06: stored premium is already NET (ex-VAT) —
// policies has a separate `vat` column (= premium x vat_percent) and a distinct
// `first_premium_wvat` (= net x 1.12), and a clean ISSUED policy_actions.premium
// exactly equals the policy's net premium (not 1.14x). So NO VAT division: the
// stored premium IS the ex-VAT figure. (Was 1.14; flipped to 1. Pending a final
// Finance/Babusi confirmation, but the data is unambiguous.)
const VAT_DIVISOR = 1; // premium stored ex-VAT already — see note above
const RESERVE_THRESHOLD = 300000; // major_claims floor (reserve > this)
const _round2 = (n) => Math.round((Number(n) || 0) * 100) / 100;

// FY-to-date start: the 1 July on or before today. Pure SQL, no bound params.
const FY_START_SQL = `(CASE WHEN MONTH(CURDATE()) >= 7
        THEN DATE(CONCAT(YEAR(CURDATE()),   '-07-01'))
        ELSE DATE(CONCAT(YEAR(CURDATE())-1, '-07-01')) END)`;

// Claim group from the policy number prefix (COMG / DOMG / MIS). Reuses the exact
// prefix rules complianceCensus.BY_CATEGORY_SQL verified on PROD, re-labelled to
// the Omni group codes. BONU is NOT a prefix here → it can never enter a group
// total (task: BONU excluded from every automated total — no source, not invented).
const groupCaseSql = (col) => `CASE
    WHEN ${col} LIKE 'MIS%'  OR ${col} LIKE 'ADH%'  THEN 'MIS'
    WHEN ${col} LIKE 'DOMG%'                        THEN 'DOMG'
    WHEN ${col} LIKE 'COMG%' OR ${col} LIKE 'COMD%' THEN 'COMG'
    ELSE 'OTHER' END`;

// loss_ratio_detail business-line label from products.product_id. product_id 3 =
// Motor Comprehensive; 1,2,4,5,9 = the rest of the Instant book; 7,8,16–22 = the
// DomCom (Corporate & Personal) book (domain doc §1). 'Bonu Legal Insurance' is a
// SEPARATE line with NO automated source — handled in the builder, never in SQL.
const detailCaseSql = (col = 'p.product_id') => `CASE
    WHEN ${col} = 3                           THEN 'Motor Comprehensive'
    WHEN ${col} IN (1,2,4,5,9)                THEN 'Instant Insurance'
    WHEN ${col} IN (7,8,16,17,18,19,20,21,22) THEN 'Corporate & Personal Lines'
    ELSE 'Other' END`;

// Motor split: product_id 2 (Third Party Car) + 3 (Motor Comprehensive) = motor.
const MOTOR_PRODUCT_IDS = '2,3';
// Non-voided coverage rows only (the ClaimsController void rule).
const NON_VOIDED = '(crc.is_payment_voided IS NULL OR crc.is_payment_voided = 0)';
// Net reserve per row = reserve − subrogation recovery − salvage recovery, on
// non-voided rows only. COALESCE every term so a NULL recovery cannot void the
// whole row (the SUM(a − b) NULL trap); with all terms COALESCEd this is exactly
// equal to the screen's separate-sum form SUM(reserve) − SUM(subro) − SUM(salvage).
const NET_RESERVE_ROW = `CASE WHEN ${NON_VOIDED}
    THEN COALESCE(crc.reserve_amt, 0) - COALESCE(crc.subrogation_reserve, 0) - COALESCE(crc.salvage_reserve, 0)
    ELSE 0 END`;
const RESERVE_EXPR = `SUM(${NET_RESERVE_ROW})`;
const PAYMENT_EXPR = `SUM(CASE WHEN ${NON_VOIDED} THEN crc.payment_amt ELSE 0 END)`;
// Written-premium sign rule: CANCEL reverses premium (negated, robust to sign).
const NET_PREM = `CASE WHEN a.transaction_type = 'CANCEL' THEN -ABS(a.premium) ELSE a.premium END`;
// Dedup: within a policy TERM period keep only the latest ISSUED, non-deleted
// action (MAX(id)) transacted this FY — same dedup as WRITTEN_PREMIUM_SQL, scoped
// to the FY window instead of 730 days.
const PREMIUM_KEEP_SUBQUERY = `SELECT MAX(id) AS id
     FROM policy_actions
     WHERE status = 'ISSUED' AND deleted_at IS NULL
       AND transaction_date >= ${FY_START_SQL} AND transaction_date <= CURDATE()
     GROUP BY policy_id, effective_from, effective_to`;

// ── claims_by_group: [{group, reserve, payment}] (BONU excluded) ──────────────
const CLAIMS_BY_GROUP_SQL = `
  SELECT grp AS \`group\`, ROUND(SUM(reserve), 2) AS reserve, ROUND(SUM(payment), 2) AS payment
  FROM (
    SELECT ${groupCaseSql('p.policyNumber')} AS grp,
           ${NET_RESERVE_ROW} AS reserve,
           CASE WHEN ${NON_VOIDED} THEN crc.payment_amt ELSE 0 END AS payment
    FROM claim_reserves_coverages crc
    JOIN claims   c ON c.id = crc.claim_id
    JOIN policies p ON p.id = c.policy_id
  ) g
  WHERE grp IN ('COMG','DOMG','MIS')
  GROUP BY grp
`;
function buildClaimsByGroup(rows) {
  return (rows || []).map((r) => ({ group: _str(r.group), reserve: _round2(r.reserve), payment: _round2(r.payment) }));
}

// ── premium_by_group: [{group, premium_ex_vat}] ───────────────────────────────
const PREMIUM_BY_GROUP_SQL = `
  SELECT grp AS \`group\`, ROUND(SUM(net_prem) / ${VAT_DIVISOR}, 2) AS premium_ex_vat
  FROM (
    SELECT ${groupCaseSql('p.policyNumber')} AS grp, ${NET_PREM} AS net_prem
    FROM policy_actions a
    JOIN ( ${PREMIUM_KEEP_SUBQUERY} ) keep ON keep.id = a.id
    JOIN policies p ON p.id = a.policy_id
  ) x
  WHERE grp IN ('COMG','DOMG','MIS')
  GROUP BY grp
`;
function buildPremiumByGroup(rows) {
  return (rows || []).map((r) => ({ group: _str(r.group), premium_ex_vat: _round2(r.premium_ex_vat) }));
}

// ── claims_by_type: [{claim_type, claim_count, reserve, payment}] ─────────────
// claims.claim_type is a code string (e.g. MOTOR / FIRE / TRAVELINSURANCE) — a
// group-safe attribute, no PII. LEFT JOIN so a claim with no reserve row still
// counts; claim_count is DISTINCT claims (the crc join fans out per coverage).
const CLAIMS_BY_TYPE_SQL = `
  SELECT c.claim_type AS claim_type,
         COUNT(DISTINCT c.id) AS claim_count,
         ROUND(${RESERVE_EXPR}, 2) AS reserve,
         ROUND(${PAYMENT_EXPR}, 2) AS payment
  FROM claims c
  LEFT JOIN claim_reserves_coverages crc ON crc.claim_id = c.id
  WHERE c.claim_number IS NOT NULL AND c.claim_number <> ''
  GROUP BY c.claim_type
`;
function buildClaimsByType(rows) {
  return (rows || []).map((r) => ({
    claim_type: _str(r.claim_type),
    claim_count: Number(r.claim_count) || 0,
    reserve: _round2(r.reserve),
    payment: _round2(r.payment),
  }));
}

// ── inforce_by_type: [{claim_type, inforce_policies}] ─────────────────────────
// GAP/ASSUMPTION: policies do NOT carry a claim_type, and there is no canonical
// product→claim_type map. This returns the exposure base — the count of ACTIVE
// (status=1) policies grouped by PRODUCT NAME — placed in the claim_type field so
// it satisfies the Omni shape. Downstream can map product→claim_type. It is the
// in-force denominator that pairs with claims_by_type frequency. PII-free.
const INFORCE_BY_TYPE_SQL = `
  SELECT pr.name AS claim_type, COUNT(*) AS inforce_policies
  FROM policies p
  JOIN products pr ON pr.id = p.product_id
  WHERE p.status = 1
  GROUP BY pr.name
  ORDER BY inforce_policies DESC
`;
function buildInforceByType(rows) {
  return (rows || []).map((r) => ({ claim_type: _str(r.claim_type), inforce_policies: Number(r.inforce_policies) || 0 }));
}

// ── premium_by_line: [{product_line, premium_ex_vat, motor, non_motor}] ───────
// Each product line is wholly motor or non-motor, so motor + non_motor add back to
// premium_ex_vat per row (lets Omni sum either column). product_id lives on
// policies, so the FY dedup subquery yields policy_id, then we join to policies.
const PREMIUM_BY_LINE_SQL = `
  SELECT pr.name AS product_line,
         ROUND(SUM(x.net_prem) / ${VAT_DIVISOR}, 2) AS premium_ex_vat,
         ROUND(SUM(CASE WHEN p.product_id IN (${MOTOR_PRODUCT_IDS})     THEN x.net_prem ELSE 0 END) / ${VAT_DIVISOR}, 2) AS motor,
         ROUND(SUM(CASE WHEN p.product_id NOT IN (${MOTOR_PRODUCT_IDS}) THEN x.net_prem ELSE 0 END) / ${VAT_DIVISOR}, 2) AS non_motor
  FROM (
    SELECT a.policy_id, ${NET_PREM} AS net_prem
    FROM policy_actions a
    JOIN ( ${PREMIUM_KEEP_SUBQUERY} ) keep ON keep.id = a.id
  ) x
  JOIN policies p ON p.id = x.policy_id
  JOIN products pr ON pr.id = p.product_id
  GROUP BY pr.name
  ORDER BY premium_ex_vat DESC
`;
function buildPremiumByLine(rows) {
  return (rows || []).map((r) => ({
    product_line: _str(r.product_line),
    premium_ex_vat: _round2(r.premium_ex_vat),
    motor: _round2(r.motor),
    non_motor: _round2(r.non_motor),
  }));
}

// ── loss_ratio_detail ─────────────────────────────────────────────────────────
// [{detail, premium_ex_vat, reserve, paid}] over the four fixed business lines.
// Two aggregates (premium side, claims side) merged in JS on the detail label.
const LOSS_RATIO_DETAILS = ['Instant Insurance', 'Motor Comprehensive', 'Corporate & Personal Lines', 'Bonu Legal Insurance'];
const LOSS_RATIO_PREMIUM_SQL = `
  SELECT detail, ROUND(SUM(net_prem) / ${VAT_DIVISOR}, 2) AS premium_ex_vat
  FROM (
    SELECT ${detailCaseSql('p.product_id')} AS detail, ${NET_PREM} AS net_prem
    FROM policy_actions a
    JOIN ( ${PREMIUM_KEEP_SUBQUERY} ) keep ON keep.id = a.id
    JOIN policies p ON p.id = a.policy_id
  ) x
  WHERE detail <> 'Other'
  GROUP BY detail
`;
const LOSS_RATIO_CLAIMS_SQL = `
  SELECT detail, ROUND(SUM(reserve), 2) AS reserve, ROUND(SUM(paid), 2) AS paid
  FROM (
    SELECT ${detailCaseSql('p.product_id')} AS detail,
           ${NET_RESERVE_ROW} AS reserve,
           CASE WHEN ${NON_VOIDED} THEN crc.payment_amt ELSE 0 END AS paid
    FROM claim_reserves_coverages crc
    JOIN claims   c ON c.id = crc.claim_id
    JOIN policies p ON p.id = c.policy_id
    WHERE COALESCE(c.reported_date, c.incident_date, DATE(c.created_at)) >= ${FY_START_SQL}
      AND COALESCE(c.reported_date, c.incident_date, DATE(c.created_at)) <= CURDATE()
  ) y
  WHERE detail <> 'Other'
  GROUP BY detail
`;
function buildLossRatioDetail(premRows, claimRows) {
  const prem = new Map((premRows || []).map((r) => [_str(r.detail), _round2(r.premium_ex_vat)]));
  const clm = new Map((claimRows || []).map((r) => [_str(r.detail), { reserve: _round2(r.reserve), paid: _round2(r.paid) }]));
  return LOSS_RATIO_DETAILS.map((detail) => {
    if (detail === 'Bonu Legal Insurance') {
      // NO BONU source exists — figures intentionally null, never invented (task).
      return { detail, premium_ex_vat: null, reserve: null, paid: null };
    }
    const c = clm.get(detail) || { reserve: 0, paid: 0 };
    return { detail, premium_ex_vat: prem.has(detail) ? prem.get(detail) : 0, reserve: c.reserve, paid: c.paid };
  });
}

// ── major_claims: [{claim_no, claim_type, reserve, paid, status, repudiated}] ──
// reserve > 300000, non-voided rule, NO names. NOT FY-scoped (standing watch-list).
// status: 'closed' when claims.status = 'Closed', else 'open'. repudiated when the
// status is a rejection/repudiation. Both are literal maps — easy to retune.
const MAJOR_CLAIMS_SQL = `
  SELECT c.claim_number AS claim_no,
         c.claim_type   AS claim_type,
         ROUND(${RESERVE_EXPR}, 2) AS reserve,
         ROUND(${PAYMENT_EXPR}, 2) AS paid,
         c.status AS claim_status
  FROM claims c
  JOIN claim_reserves_coverages crc ON crc.claim_id = c.id
  WHERE c.claim_number IS NOT NULL AND c.claim_number <> ''
  GROUP BY c.claim_number, c.claim_type, c.status
  HAVING reserve > ${RESERVE_THRESHOLD}
  ORDER BY reserve DESC
`;
function buildMajorClaims(rows) {
  return (rows || []).map((r) => {
    const s = _str(r.claim_status);
    return {
      claim_no: _str(r.claim_no),
      claim_type: _str(r.claim_type),
      reserve: _round2(r.reserve),
      paid: _round2(r.paid),
      status: s.toLowerCase() === 'closed' ? 'closed' : 'open',
      repudiated: /reject|repudiat/i.test(s),
    };
  });
}

// ── broker_lr ─────────────────────────────────────────────────────────────────
// [{broker, claim_count, reserve, payment, premium_fy, lr_on_reserve}] — Graphite
// only, current FY (no Portal history — the summed cross-system figure is BLOCKED).
// broker = agency name via policies.agency_id → agencies.name (the brokerage FIRM
// ON THE POLICY, not derived from the selling agent — PII-free). Policies with a
// null/0 agency_id (direct/unbrokered business) fall out of the inner join and are
// intentionally excluded from broker rows.
// Changed 9 Sep 2026 per CFO: key off the policy's own agency, not the agent's, so
// brokers whose selling agent has no agency (Marsh, Minet, Spectrum, Botshabelo, UTL
// — all with live claims) are no longer dropped. Feed goes 22 → 43 brokers.
// lr_on_reserve = reserve ÷ premium_fy (FRACTION; null when premium_fy = 0).
const BROKER_LR_CLAIMS_SQL = `
  SELECT ag.name AS broker,
         COUNT(DISTINCT c.id) AS claim_count,
         ROUND(${RESERVE_EXPR}, 2) AS reserve,
         ROUND(${PAYMENT_EXPR}, 2) AS payment
  FROM claims c
  JOIN policies p ON p.id = c.policy_id
  JOIN agencies ag ON ag.id = p.agency_id
  LEFT JOIN claim_reserves_coverages crc ON crc.claim_id = c.id
  WHERE COALESCE(c.reported_date, c.incident_date, DATE(c.created_at)) >= ${FY_START_SQL}
    AND COALESCE(c.reported_date, c.incident_date, DATE(c.created_at)) <= CURDATE()
  GROUP BY ag.name
`;
const BROKER_LR_PREMIUM_SQL = `
  SELECT ag.name AS broker, ROUND(SUM(x.net_prem) / ${VAT_DIVISOR}, 2) AS premium_fy
  FROM (
    SELECT a.policy_id, ${NET_PREM} AS net_prem
    FROM policy_actions a
    JOIN ( ${PREMIUM_KEEP_SUBQUERY} ) keep ON keep.id = a.id
  ) x
  JOIN policies p ON p.id = x.policy_id
  JOIN agencies ag ON ag.id = p.agency_id
  GROUP BY ag.name
`;
function buildBrokerLr(claimRows, premRows) {
  const byBroker = new Map();
  const get = (name) => {
    const k = _str(name);
    if (!byBroker.has(k)) byBroker.set(k, { broker: k, claim_count: 0, reserve: 0, payment: 0, premium_fy: 0 });
    return byBroker.get(k);
  };
  for (const r of claimRows || []) {
    const b = get(r.broker);
    b.claim_count = Number(r.claim_count) || 0;
    b.reserve = _round2(r.reserve);
    b.payment = _round2(r.payment);
  }
  for (const r of premRows || []) get(r.broker).premium_fy = _round2(r.premium_fy);
  return Array.from(byBroker.values())
    .map((b) => ({ ...b, lr_on_reserve: b.premium_fy > 0 ? Math.round((b.reserve / b.premium_fy) * 10000) / 10000 : null }))
    .sort((a, b) => (b.lr_on_reserve == null ? -1 : b.lr_on_reserve) - (a.lr_on_reserve == null ? -1 : a.lr_on_reserve));
}

// ── kyc_completeness: [{branch, agent, policies, complete, pct_complete}] ──────
// Counts only. "complete" = the customer has at least one KYC document present,
// read through the brain_customer_kyc MASKED VIEW (presence booleans) — the same
// completeness test as complianceCensus.POLICIES_NO_DOCS_SQL, inverted. branch =
// selling agency name; agent = the numeric agent_id (a staff id, NOT a name — DPA).
// pct_complete is a FRACTION. Direct/unbrokered policies excluded (inner joins).
const KYC_COMPLETENESS_SQL = `
  SELECT ag.name AS branch,
         p.agent_id AS agent,
         COUNT(*) AS policies,
         SUM(CASE WHEN EXISTS (
               SELECT 1 FROM brain_customer_kyc k
               WHERE k.customer_id = p.customer_id
                 AND (k.omang_front_present OR k.passport_present OR k.dl_present
                      OR k.poi_present OR k.por_present OR k.kyc_form_present)
             ) THEN 1 ELSE 0 END) AS complete
  FROM policies p
  JOIN users    u ON u.id = p.agent_id
  JOIN agencies ag ON ag.id = u.agency_id
  WHERE p.status = 1
  GROUP BY ag.name, p.agent_id
`;
function buildKycCompleteness(rows) {
  return (rows || []).map((r) => {
    const policies = Number(r.policies) || 0;
    const complete = Number(r.complete) || 0;
    return {
      branch: _str(r.branch),
      agent: r.agent == null ? null : String(r.agent),
      policies,
      complete,
      pct_complete: policies > 0 ? Math.round((complete / policies) * 10000) / 10000 : null,
    };
  });
}

// ── renewals_trigger ──────────────────────────────────────────────────────────
// The ALLOWED renewals feed: active policies with days-to-expiry ≤ 30, keyed on
// policy no. / GFS ref, NO premium. The Portal+Graphite SUMMED-PREMIUM renewals
// figure is BLOCKED (21% double-count risk) and is intentionally NOT built here or
// anywhere in this module — pending written proof. This is a trigger list only.
const RENEWALS_TRIGGER_SQL = `
  SELECT p.policyNumber   AS policy_no,
         p.gfs_policy_no  AS gfs_ref,
         pr.name          AS product_line,
         DATEDIFF(p.expiry_date, CURDATE()) AS days_to_expiry,
         DATE(p.expiry_date) AS expiry_date
  FROM policies p
  LEFT JOIN products pr ON pr.id = p.product_id
  WHERE p.status = 1
    AND p.expiry_date IS NOT NULL
    AND p.expiry_date >= CURDATE()
    AND p.expiry_date <= (CURDATE() + INTERVAL 30 DAY)
  ORDER BY p.expiry_date ASC
`;
function buildRenewalsTrigger(rows) {
  return (rows || []).map((r) => ({
    policy_no: _str(r.policy_no),
    gfs_ref: r.gfs_ref == null ? null : String(r.gfs_ref),
    product_line: r.product_line == null ? null : String(r.product_line),
    days_to_expiry: _num(r.days_to_expiry),
    expiry_date: _d(r.expiry_date),
    // summed premium DELIBERATELY OMITTED — see the BLOCKED note above.
  }));
}

// ── debtors_aging ─────────────────────────────────────────────────────────────
// [{reference, balance, b61_90, b91_120, over_120, overdue}]. Built from the SAME
// debtors feed the sweep already pulls (records.debtors → invoices/payments per
// policy), aged with the corrected allocator in ageing.js — NO extra DB query.
// ageing buckets: current 0–30 · b30 31–60 · b60 61–90 · b90 91–120 · b120plus
// >120. reference = policy number (allowed key). overdue = arrears past 30 days
// (b30+b60+b90+b120plus) — the balance genuinely beyond the current period.
function buildDebtorsAging(accounts = [], asOf) {
  const ageing = require('./ageing');
  const asof = asOf || new Date().toISOString().slice(0, 10);
  return (accounts || []).map((a) => {
    const r = ageing.age(a.invoices || [], a.payments || [], asof);
    const b = r.buckets;
    return {
      reference: _str(a.account),
      balance: _round2(r.balance),
      b61_90: _round2(b.b60),
      b91_120: _round2(b.b90),
      over_120: _round2(b.b120plus),
      overdue: _round2(b.b30 + b.b60 + b.b90 + b.b120plus),
    };
  });
}

/**
 * fetchAnalytics(dsn, log) → all SQL-sourced analytics datasets, read live from
 * the replica on its OWN connection (mirrors fetchCensus/fetchHealthcare).
 * PER-DATASET RESILIENCE: each dataset soft-fails to [] and is logged; one denied
 * table or a statement-timeout never voids the others or throws the sweep.
 * debtors_aging is NOT here — it is built from records.debtors in the sweep so it
 * needs no query. Connection-level failure throws (caught by daily.js).
 */
async function fetchAnalytics(dsn, log = console.warn) {
  if (!dsn) throw new Error('GRAPHITE_RO_DSN not set');
  const mysql = require('mysql2/promise'); // lazy — keeps the builders test-only
  const conn = await mysql.createConnection({
    ...parseDsn(dsn),
    ssl: 'Amazon RDS',
    connectTimeout: 10_000,
    multipleStatements: false,
  });
  const q = async (sql) => (await conn.query(sql))[0];
  const run = async (name, fn) => {
    try { return await fn(); }
    catch (e) { log(`[brain] analytics dataset '${name}' failed (empty this run): ${e.message}`); return []; }
  };
  try {
    return {
      claims_by_group:   await run('claims_by_group',   async () => buildClaimsByGroup(await q(CLAIMS_BY_GROUP_SQL))),
      premium_by_group:  await run('premium_by_group',  async () => buildPremiumByGroup(await q(PREMIUM_BY_GROUP_SQL))),
      claims_by_type:    await run('claims_by_type',    async () => buildClaimsByType(await q(CLAIMS_BY_TYPE_SQL))),
      inforce_by_type:   await run('inforce_by_type',   async () => buildInforceByType(await q(INFORCE_BY_TYPE_SQL))),
      premium_by_line:   await run('premium_by_line',   async () => buildPremiumByLine(await q(PREMIUM_BY_LINE_SQL))),
      loss_ratio_detail: await run('loss_ratio_detail', async () => buildLossRatioDetail(await q(LOSS_RATIO_PREMIUM_SQL), await q(LOSS_RATIO_CLAIMS_SQL))),
      major_claims:      await run('major_claims',      async () => buildMajorClaims(await q(MAJOR_CLAIMS_SQL))),
      broker_lr:         await run('broker_lr',         async () => buildBrokerLr(await q(BROKER_LR_CLAIMS_SQL), await q(BROKER_LR_PREMIUM_SQL))),
      kyc_completeness:  await run('kyc_completeness',  async () => buildKycCompleteness(await q(KYC_COMPLETENESS_SQL))),
      renewals_trigger:  await run('renewals_trigger',  async () => buildRenewalsTrigger(await q(RENEWALS_TRIGGER_SQL))),
    };
  } finally {
    await conn.end().catch(() => {});
  }
}

// ── assembly + connection ────────────────────────────────────────
function assembleRecords(parts = {}, log = console.log) {
  for (const f of PENDING_FEEDS) log(`[brain] live feed '${f}' not mapped yet — empty this sweep.`);
  return {
    collections:    parts.collections || [],
    kyc:            parts.kyc || [],
    debtors:        parts.debtors || [],
    claims:         parts.claims || [],
    events:         parts.events || [],
    // Aggregate (counts/totals) feed — object, not a queue array. null when the
    // feed failed/degraded this sweep.
    writtenPremium: parts.writtenPremium || null,
  };
}

function parseDsn(dsn) {
  const u = new URL(dsn);
  return {
    host:     u.hostname,
    port:     u.port ? Number(u.port) : 3306,
    user:     decodeURIComponent(u.username || ''),
    password: decodeURIComponent(u.password || ''),
    database: (u.pathname || '').replace(/^\//, '') || 'Graphite_live',
  };
}

/**
 * runFeeds(conn, log) -> the full record set, reading each feed INDEPENDENTLY.
 *
 * PER-FEED RESILIENCE: once the connection is open, a feed whose query fails
 * (e.g. a table not yet GRANTed to brain_ro, like customer_kyc pending its
 * masked view) degrades to EMPTY and is logged — it does NOT abort the whole
 * pull. Previously any single denied table threw, so daily.js fell every feed
 * back to the fixture and nothing read live. Now the priority feeds (collections
 * / claims / debtors / events) stay live even while another feed is blocked.
 * (Connection-level failure still throws from fetchRecords → fixture.)
 * Exported for unit testing with a fake connection.
 */
async function runFeeds(conn, log = console.warn) {
  const feed = async (name, fn) => {
    try { return await fn(); }
    catch (e) { log(`[brain] live feed '${name}' failed (empty this sweep): ${e.message}`); return null; }
  };
  const claims = await feed('claims', async () => buildClaims((await conn.query(CLAIMS_SQL))[0]));
  const collections = await feed('collections', async () => buildCollections((await conn.query(COLLECTIONS_SQL))[0]));
  const debtors = await feed('debtors', async () => {
    // Run the debtor-selection ONCE, then pull invoices + payments for just those
    // policies via an explicit id list (no nested subquery → the GROUP BY/HAVING
    // scan runs once, not once per query).
    const [idRows] = await conn.query(DEBTOR_POLICY_SUBQUERY);
    const ids = (idRows || []).map((r) => Number(r.policy_id)).filter((n) => Number.isInteger(n));
    if (!ids.length) return [];
    const idList = ids.join(',');
    const [inv] = await conn.query(debtorInvoicesSql(idList));
    const [pay] = await conn.query(debtorPaymentsSql(idList));
    return buildDebtors(inv, pay);
  });
  const kyc = await feed('kyc', async () => buildKyc((await conn.query(KYC_SQL))[0]));
  const events = await feed('events', async () => {
    // Each event source degrades INDEPENDENTLY — one denied table/column (e.g.
    // brain_ro missing SELECT on claims.created_at) must not take down the other
    // sources. Mirrors the per-feed resilience in runFeeds itself.
    const sub = async (name, sql) => {
      try { return (await conn.query(sql))[0]; }
      catch (e) { log(`[brain] event source '${name}' failed (skipped this sweep): ${e.message}`); return []; }
    };
    const tl = await sub('total_loss', TOTAL_LOSS_SQL);
    const pr = await sub('payment_received', PAYMENT_RECEIVED_SQL);
    const cr = await sub('claim_registered', CLAIM_REGISTERED_SQL);
    return buildEvents({ totalLoss: tl, paymentReceived: pr, claimRegistered: cr });
  });
  const writtenPremium = await feed('writtenPremium', async () =>
    buildWrittenPremium((await conn.query(WRITTEN_PREMIUM_SQL))[0]));
  return assembleRecords({ claims, collections, debtors, kyc, events, writtenPremium });
}

/**
 * fetchRecords(dsn) -> the full sweep record set, read live from the replica.
 * Connecting is fail-hard (throws → daily.js falls back to the fixture — a bad
 * connection must never half-run). Per-feed failures are soft (see runFeeds).
 */
async function fetchRecords(dsn, log = console.warn) {
  if (!dsn) throw new Error('GRAPHITE_RO_DSN not set');
  const mysql = require('mysql2/promise'); // lazy — keeps mappers test-only
  const conn = await mysql.createConnection({
    ...parseDsn(dsn),
    ssl: 'Amazon RDS', // verify against the bundled RDS CA — never skip cert checks (CFO 2026-07-21)
    connectTimeout: 10_000,
    multipleStatements: false,          // defence in depth (account is SELECT-only anyway)
  });
  try {
    return await runFeeds(conn, log);
  } finally {
    await conn.end().catch(() => {});
  }
}

module.exports = {
  fetchRecords, runFeeds, assembleRecords, parseDsn,
  buildClaims, buildCollections, buildDebtors, buildKyc, buildEvents,
  CLAIMS_SQL, COLLECTIONS_SQL, DEBTOR_POLICY_SUBQUERY, debtorInvoicesSql, debtorPaymentsSql, KYC_SQL,
  ORPHAN_POLICY_SQL,
  TOTAL_LOSS_SQL, PAYMENT_RECEIVED_SQL, CLAIM_REGISTERED_SQL,
  WRITTEN_PREMIUM_SQL, buildWrittenPremium, WRITTEN_PREMIUM_WINDOW_DAYS,
  PENDING_FEEDS, DEFERRED_EVENTS, KYC_DOC_CATALOG,
  // Analytics datasets (Alpha Brain workbook + Omni push)
  fetchAnalytics,
  buildClaimsByGroup, buildPremiumByGroup, buildClaimsByType, buildInforceByType,
  buildPremiumByLine, buildLossRatioDetail, buildMajorClaims, buildBrokerLr,
  buildKycCompleteness, buildRenewalsTrigger, buildDebtorsAging,
  CLAIMS_BY_GROUP_SQL, PREMIUM_BY_GROUP_SQL, CLAIMS_BY_TYPE_SQL, INFORCE_BY_TYPE_SQL,
  PREMIUM_BY_LINE_SQL, LOSS_RATIO_PREMIUM_SQL, LOSS_RATIO_CLAIMS_SQL, MAJOR_CLAIMS_SQL,
  BROKER_LR_CLAIMS_SQL, BROKER_LR_PREMIUM_SQL, KYC_COMPLETENESS_SQL, RENEWALS_TRIGGER_SQL,
  FY_START_SQL, VAT_DIVISOR, RESERVE_THRESHOLD, LOSS_RATIO_DETAILS, MOTOR_PRODUCT_IDS,
  groupCaseSql, detailCaseSql,
};
