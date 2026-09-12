'use strict';
/**
 * reinsurance.js — auto-raise reinsurance recoveries and watch concentration.
 * Replaces working out the reinsurers' share of a big claim by hand and the
 * manual facultative bordereaux.
 *
 * Pure + deterministic (numbers only, no PII):
 *   assessRecovery(claim, treaty):
 *     - a facultative placement → recover its share of the gross loss
 *     - else gross loss above the treaty retention → recover the excess
 *       (capped at the treaty limit if one is given)
 *     - otherwise → nothing recoverable
 *   concentrationCheck(placements, limit) → any single reinsurer over the limit
 *     (the Grand Re / FMRE concentration watch).
 */

function round2(n) { return Math.round((Number(n) || 0) * 100) / 100; }

/**
 * assessRecovery(claim, treaty) -> { recoverable, layer, recoveryAmount, retention, basis, notify }
 *   claim:  { grossLoss, facShare? }   facShare 0..1 for a facultative placement
 *   treaty: { retention, limit? }
 */
function assessRecovery(claim = {}, treaty = {}) {
  const gl = Number(claim.grossLoss) || 0;

  // A facultative placement is self-contained (share × gross loss) and needs no
  // treaty terms — assess it first, before the treaty guard below.
  if (claim.facShare != null && Number(claim.facShare) > 0) {
    const share = Math.min(1, Number(claim.facShare));
    const amt = round2(gl * share);
    return { recoverable: amt > 0, layer: 'facultative', recoveryAmount: amt,
             retention: null, basis: `facultative ${(share * 100).toFixed(0)}%`,
             notify: amt > 0 ? ['Reinsurance team'] : [] };
  }

  // Treaty path REQUIRES a retention on file. Previously a missing retention
  // coerced to 0, so the whole gross loss looked recoverable — a claim with no
  // treaty on file would report a 100% recovery and massively overstate the RI
  // asset. Refuse and ask Reinsurance to supply the terms instead of inventing
  // a number. (retention === 0 explicitly is still honoured — only a genuinely
  // absent/undefined retention is refused.)
  if (treaty.retention == null || treaty.retention === '') {
    return { recoverable: false, layer: 'unknown', recoveryAmount: 0, retention: null,
             basis: 'treaty terms unknown — retention not on file; recovery cannot be computed',
             notify: gl > 0 ? ['Reinsurance team'] : [], needsTreaty: true };
  }

  const retention = Number(treaty.retention) || 0;
  const limit = treaty.limit != null ? Number(treaty.limit) : Infinity;

  if (gl > retention) {
    const amt = round2(Math.min(gl - retention, limit));
    return { recoverable: amt > 0, layer: 'treaty', recoveryAmount: amt, retention,
             basis: `gross ${gl} over retention ${retention}${limit !== Infinity ? `, capped at ${limit}` : ''}`,
             notify: amt > 0 ? ['Reinsurance team'] : [] };
  }

  return { recoverable: false, layer: 'none', recoveryAmount: 0, retention,
           basis: `gross ${gl} within retention ${retention}`, notify: [] };
}

/**
 * concentrationCheck(placements, limitPct) -> { flags:[{reinsurer, sharePct}], byReinsurer }
 *   placements: [{ reinsurer, share }]  share 0..1
 */
function concentrationCheck(placements = [], limitPct = 0.35) {
  const totals = new Map();
  for (const p of placements) {
    if (!p || !p.reinsurer) continue;
    totals.set(p.reinsurer, (totals.get(p.reinsurer) || 0) + (Number(p.share) || 0));
  }
  const byReinsurer = {};
  const flags = [];
  for (const [reinsurer, share] of totals) {
    byReinsurer[reinsurer] = round2(share * 100);
    if (share > limitPct) flags.push({ reinsurer, sharePct: round2(share * 100) });
  }
  flags.sort((a, b) => b.sharePct - a.sharePct);
  return { flags, byReinsurer };
}

module.exports = { assessRecovery, concentrationCheck };
