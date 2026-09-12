// brain/compliance-summary.js — PII-FREE compliance aggregate for Omni.
//
// Omni fetches this nightly (GET /compliance-summary) and lets its own AI read
// it. It must contain ZERO customer data — only counts. We deliberately do NOT
// include refs/details/names (those live in /inbox, which Omni does not fetch).
//
// Shape (stable contract with Omni's core.compliance_brain):
//   { generatedAt, total, counts:{team:n},
//     kyc: { by_domain:{domain:n},            // exception counts, PII-free
//            by_category:{MIS,DOM,COM},        // census — filled when read-only
//            by_broker:{},                     //   Graphite access is wired
//            claims_kyc:{compliant,non_compliant},
//            policies_no_docs:0,
//            monthly_checks:{under_18,duplicate_products,over_65_cashback,multiple_debits} } }
'use strict';

// Coarse, PII-free aggregation of the ranked queue for the Compliance team.
function complianceSummary(queue, census, healthcare) {
  queue = queue || {};
  const teams = queue.teams || {};
  // Prefer the precomputed FULL by-domain counts from the dashboard snapshot
  // (store.slimQueue.byDomain) — the snapshot's item lists are capped (top-N per
  // team), so counting its items would undercount. Fall back to counting items
  // when a full queue/fixture is passed (tests).
  let byDomain;
  if (queue.byDomain && queue.byDomain.Compliance) {
    byDomain = queue.byDomain.Compliance;
  } else {
    byDomain = {};
    for (const x of (teams.Compliance || [])) {
      const d = (x && x.domain) || 'other';
      byDomain[d] = (byDomain[d] || 0) + 1;
    }
  }
  // census = optional PII-free totals from the read-only Graphite KYC query,
  // injected once that grant is wired (Pramod). Until then it stays empty and
  // Omni shows the exception counts + "awaiting live census".
  const c = census || {};
  return {
    generatedAt: queue.generatedAt || null,
    total: queue.total || 0,
    counts: queue.counts || {},
    kyc: {
      by_domain: byDomain,
      by_category: c.by_category || {},
      by_broker: c.by_broker || {},
      claims_kyc: c.claims_kyc || {},
      policies_no_docs: c.policies_no_docs || 0,
      monthly_checks: c.monthly_checks || {},
    },
    // Healthcare (MIS/ADH) paperwork gap — counts only. Identity documents ONLY;
    // scheme rules (waiting periods, pre-existing conditions, dependant proof)
    // are listed in _pending and NOT counted. Empty until the sweep produces it.
    healthcare: healthcare || null,
  };
}

module.exports = { complianceSummary };
