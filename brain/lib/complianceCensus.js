'use strict';
// complianceCensus.js — COUNTS-ONLY compliance/AML census for the Omni feed.
//
// Every query is an aggregate (COUNT / GROUP BY) — it NEVER selects customer
// rows, refs, names or ID-document values. Any KYC read goes through the
// brain_customer_kyc MASKED VIEW (presence booleans only; no raw Omang/passport/
// proof docs), so brain_ro cannot reach raw PII even internally. The output is a
// small dict of numbers, persisted by the daily sweep and served (merged into the
// queue counts) by GET /compliance-summary.
//
// LIVE (verified against PROD 2026-07-24): by_category, policies_no_docs.
// PENDING Compliance definitions (left EMPTY until the rules are confirmed — see
// the deploy note; we do not invent AML logic):
//   - by_broker      : agent-level vs agency-level? which grouping?
//   - claims_kyc     : what status counts as "compliant"?
//   - monthly_checks : under_18 / over_65_cashback / duplicate_products /
//                      multiple_debits — exact AML rules; and under_18 / over_65
//                      need a DOB source (none on the customer table).

const { parseDsn } = require('./graphiteRo');

// ACTIVE policies grouped by product category (MIS / DOM / COM) from the policy
// number prefix. Pure counts, no PII.
// status semantics (verified vs read replica 2026-07-27): 1 = activated & in
// force, 0 = NEVER ACTIVATED (sold, never switched on), 2 = lapsed/inactive.
// We count ONLY status = 1 — the real active book (~61k). Including status 0
// (never-activated, ~77k) inflated the headline to ~139k and, via the same
// filter below, ballooned the KYC exception queue. (CFO correction, 26 Jul.)
const BY_CATEGORY_SQL = `
  SELECT CASE
           WHEN policyNumber LIKE 'MIS%' OR policyNumber LIKE 'ADH%' THEN 'MIS'
           WHEN policyNumber LIKE 'DOMG%'                            THEN 'DOM'
           WHEN policyNumber LIKE 'COMG%' OR policyNumber LIKE 'COMD%' THEN 'COM'
           ELSE 'OTHER' END        AS category,
         COUNT(*)                  AS n
  FROM policies
  WHERE status = 1
  GROUP BY category
`;

// ACTIVE (status = 1) policies whose customer has NO KYC document present — a
// real "missing KYC" gap. Read ENTIRELY through the masked view (presence
// booleans), so this measures DOCUMENT PRESENCE, not the customer_kyc
// compliance flag — the two are different and must not be conflated. Counts-only.
// On status = 1 this is ~3,650, vs ~15,700 when never-activated policies (which
// never collected KYC) were wrongly included.
const POLICIES_NO_DOCS_SQL = `
  SELECT COUNT(*) AS n
  FROM policies p
  WHERE p.status = 1
    AND NOT EXISTS (
      SELECT 1 FROM brain_customer_kyc k
      WHERE k.customer_id = p.customer_id
        AND (k.omang_front_present OR k.passport_present OR k.dl_present
             OR k.poi_present OR k.por_present OR k.kyc_form_present)
    )
`;

// The census contract consumed by compliance-summary.js. Definition-pending
// metrics stay empty; _pending lists them so we (not the payload) track the gap.
function emptyCensus() {
  return { by_category: {}, by_broker: {}, claims_kyc: {}, policies_no_docs: 0,
           monthly_checks: {}, _pending: ['by_broker', 'claims_kyc', 'monthly_checks'] };
}

async function fetchCensus(dsn, log = console.warn) {
  if (!dsn) throw new Error('GRAPHITE_RO_DSN not set');
  const mysql = require('mysql2/promise'); // lazy — keeps this unit-testable without a DB
  const conn = await mysql.createConnection({
    ...parseDsn(dsn),
    ssl: 'Amazon RDS',            // verify against the bundled RDS CA
    connectTimeout: 10_000,
    multipleStatements: false,     // defence in depth (account is SELECT-only anyway)
  });
  const census = emptyCensus();
  try {
    // Each metric soft-fails independently — one bad/denied query never voids the census.
    try {
      const [rows] = await conn.query(BY_CATEGORY_SQL);
      for (const r of rows) census.by_category[r.category] = Number(r.n) || 0;
    } catch (e) { log(`[brain] census by_category failed: ${e.message}`); }
    try {
      const [rows] = await conn.query(POLICIES_NO_DOCS_SQL);
      census.policies_no_docs = Number(rows[0] && rows[0].n) || 0;
    } catch (e) { log(`[brain] census policies_no_docs failed: ${e.message}`); }
    return census;
  } finally {
    await conn.end().catch(() => {});
  }
}

module.exports = { fetchCensus, emptyCensus, BY_CATEGORY_SQL, POLICIES_NO_DOCS_SQL };
