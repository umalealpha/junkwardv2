'use strict';
/**
 * healthcareCompliance.js — COUNTS-ONLY healthcare (MIS / ADH) non-compliance.
 *
 * Why (CFO 2026-07-28): "where is the health care customer non compliance". It
 * was nowhere. Health customers were folded into the single Compliance number
 * with no way to see them, even though the census already knew MIS was a
 * separate product category.
 *
 * SCOPE — read this before quoting the number:
 *   This measures IDENTITY PAPERWORK on active health policies only. It does
 *   NOT measure health-scheme compliance. Waiting periods, pre-existing-
 *   condition declarations, dependant proof and scheme member cards are NOT
 *   counted, because Compliance has not defined those rules. We do not invent
 *   health rules — the same discipline as the AML monthly checks in
 *   complianceCensus.js, which are left empty for the same reason.
 *
 * DPA: every query is an aggregate. Any KYC read goes through the masked view
 * brain_customer_kyc (presence booleans + record status only), so this can
 * never reach a raw Omang, passport or scan. Output is a small dict of numbers.
 *
 * Built on the SQL shapes already verified against the replica in
 * complianceCensus.js (policies.status = 1 = activated and in force; product
 * category from the policy-number prefix; MIS/ADH = health). No new tables.
 */

const { parseDsn } = require('./graphiteRo');

// Health = the same prefixes complianceCensus maps to the 'MIS' category.
const HEALTH_PREFIX_SQL = "(p.policyNumber LIKE 'MIS%' OR p.policyNumber LIKE 'ADH%')";

// KYC record statuses that mean "not clean" — identical to graphiteRo.KYC_SQL,
// so the healthcare split can never disagree with the Compliance queue.
const NOT_APPROVED = "('Unchecked','Unapprove','Recheck','Recheck(KYC Expired)')";

// The active health book. Denominator for every percentage.
const ACTIVE_SQL = `
  SELECT COUNT(*) AS n
  FROM policies p
  WHERE p.status = 1 AND ${HEALTH_PREFIX_SQL}
`;

// Active health policies where the customer has NOT ONE document on file.
// Same test as complianceCensus.POLICIES_NO_DOCS_SQL, narrowed to health.
const NO_DOCS_SQL = `
  SELECT COUNT(*) AS n
  FROM policies p
  WHERE p.status = 1 AND ${HEALTH_PREFIX_SQL}
    AND NOT EXISTS (
      SELECT 1 FROM brain_customer_kyc k
      WHERE k.customer_id = p.customer_id
        AND (k.omang_front_present OR k.passport_present OR k.dl_present
             OR k.poi_present OR k.por_present OR k.kyc_form_present)
    )
`;

// Active health policies whose customer HAS a KYC record but it is not approved
// (documents present but rejected, expired, or awaiting a re-check). Distinct
// from NO_DOCS_SQL — a customer cannot be in both.
const KYC_NOT_APPROVED_SQL = `
  SELECT COUNT(*) AS n
  FROM policies p
  WHERE p.status = 1 AND ${HEALTH_PREFIX_SQL}
    AND EXISTS (
      SELECT 1 FROM brain_customer_kyc k
      WHERE k.customer_id = p.customer_id
        AND k.status IN ${NOT_APPROVED}
        AND (k.omang_front_present OR k.passport_present OR k.dl_present
             OR k.poi_present OR k.por_present OR k.kyc_form_present)
    )
`;

// Health-scheme rules Compliance has NOT defined. Named so the gap is tracked
// by US, not discovered by whoever reads the tile.
const PENDING_RULES = [
  'waiting_period_served',
  'pre_existing_condition_declared',
  'dependant_proof_on_file',
  'scheme_member_card_issued',
];

function emptyHealthcare() {
  return {
    active_policies: 0,
    no_docs: 0,
    kyc_not_approved: 0,
    non_compliant: 0,
    compliant: 0,
    _pending: PENDING_RULES,
    _scope: 'identity paperwork only — health-scheme rules not defined by Compliance',
  };
}

/**
 * summarise(raw) → the healthcare block, with the derived totals.
 * Pure — split out from the DB call so it is unit-testable without MySQL.
 */
function summarise(raw = {}) {
  const active = Number(raw.active_policies) || 0;
  const noDocs = Number(raw.no_docs) || 0;
  const notApproved = Number(raw.kyc_not_approved) || 0;
  // The two buckets are mutually exclusive by construction (no docs at all vs
  // has docs but not approved), so they add.
  const nonCompliant = Math.min(active, noDocs + notApproved);
  return {
    ...emptyHealthcare(),
    active_policies: active,
    no_docs: noDocs,
    kyc_not_approved: notApproved,
    non_compliant: nonCompliant,
    compliant: Math.max(0, active - nonCompliant),
  };
}

/**
 * fetchHealthcare(dsn) → the counts-only healthcare block from the replica.
 * Each metric soft-fails on its own, so one denied query never voids the block.
 */
async function fetchHealthcare(dsn, log = console.warn) {
  if (!dsn) throw new Error('GRAPHITE_RO_DSN not set');
  const mysql = require('mysql2/promise'); // lazy — keeps summarise() test-only
  const conn = await mysql.createConnection({
    ...parseDsn(dsn),
    ssl: 'Amazon RDS',
    connectTimeout: 10_000,
    multipleStatements: false,
  });
  const raw = {};
  try {
    for (const [key, sql] of [
      ['active_policies', ACTIVE_SQL],
      ['no_docs', NO_DOCS_SQL],
      ['kyc_not_approved', KYC_NOT_APPROVED_SQL],
    ]) {
      try {
        const [rows] = await conn.query(sql);
        raw[key] = Number(rows[0] && rows[0].n) || 0;
      } catch (e) { log(`[brain] healthcare ${key} failed: ${e.message}`); }
    }
    return summarise(raw);
  } finally {
    await conn.end().catch(() => {});
  }
}

/**
 * exceptions(block) → ONE Compliance queue item summarising the healthcare gap.
 * Deliberately a summary, not one row per policy: the customer-level health list
 * is exported through the extract (permission-gated), never spread across a
 * queue that summary-only viewers can see counts of.
 */
function exceptions(block) {
  const b = block || emptyHealthcare();
  if (!b.non_compliant) return [];
  return [{
    id: 'healthcare:non-compliance',
    domain: 'healthcare',
    team: 'Compliance',
    priority: 65,
    title: `Healthcare paperwork gap — ${b.non_compliant} active health policies`,
    detail: `${b.no_docs} with no documents at all · ${b.kyc_not_approved} with documents not approved`
      + ` · out of ${b.active_policies} active health policies`
      + ' · identity paperwork only (scheme rules not yet defined)',
    ref: null,
    status: 'open',
    needsApproval: false,
    healthcare: b,
  }];
}

module.exports = {
  fetchHealthcare, summarise, emptyHealthcare, exceptions, PENDING_RULES,
  ACTIVE_SQL, NO_DOCS_SQL, KYC_NOT_APPROVED_SQL,
};
