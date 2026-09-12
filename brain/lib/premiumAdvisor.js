'use strict';
/**
 * premiumAdvisor.js — the go / no-go brain for a claim, from the customer's
 * premium position.
 *
 * The RULE is the source of truth (CFO 2026-07-09, final):
 *   - within 1 month behind (the grace period) → GO (on risk, auto).
 *   - more than 1 month behind → REFER to Finance. Never auto-decline: a client
 *     may be on a payment plan, so Finance decides.
 *   - more than 3 months behind AND Finance approves → also alert the finance
 *     oversight group (see lib/highArrearsAlert.js).
 *
 * DeepSeek can add a plain-English recommendation on top — ADVISORY ONLY, it
 * never overrides the rule, runs off the Claude subscription (local gateway),
 * and only ever receives MASKED NUMBERS. No name / ID / policy number leaves.
 */

const GRACE_MONTHS = 1;
const HIGH_ARREARS_MONTHS = 3;

/** decide(ctx) → the authoritative rule result. Pure. */
function decide({ arrearsMonths = 0, financeApproved = false } = {}) {
  const m = Math.max(0, Number(arrearsMonths) || 0);
  const decision = m <= GRACE_MONTHS ? 'GO' : 'REFER';
  const highArrears = m > HIGH_ARREARS_MONTHS;
  return {
    decision,                 // 'GO' | 'REFER'
    arrearsMonths: m,
    graceMonths: GRACE_MONTHS,
    highArrears,              // more than 3 months
    alertFinanceOversight: highArrears && financeApproved === true,
    reason: decision === 'GO'
      ? `Within the ${GRACE_MONTHS}-month grace period — on risk.`
      : `${m} months in arrears — refer to Finance (a payment plan may apply).`,
  };
}

// Only these NUMERIC fields may ever be sent to the external model.
const MASK_ALLOW = ['arrearsMonths', 'amountOwing', 'daysUnpaid', 'graceMonths', 'dateOfLossOffsetDays', 'policyStatusCode'];

/** buildMaskedPayload(ctx) → object containing ONLY whitelisted numeric fields.
 *  Guarantees no PII (name / omang / policy number / bank) can leak to the model. */
function buildMaskedPayload(ctx = {}) {
  const out = {};
  for (const k of MASK_ALLOW) {
    if (ctx[k] != null && typeof ctx[k] !== 'object') out[k] = ctx[k];
  }
  return out;
}

/**
 * recommend(ctx, opts) → { recommendation, reason } | null
 * Advisory DeepSeek call via a configurable local gateway. Optional: returns null
 * if no endpoint configured or the call fails — the rule still governs.
 * NEVER call with raw PII; it masks internally before sending.
 */
async function recommend(ctx = {}, opts = {}) {
  const ai = opts.ai || require('./ai');            // shared client (PII-guarded); injectable for tests
  const masked = buildMaskedPayload(ctx);           // numbers only — no name/ID/policy
  const prompt =
    'You advise an insurance claims handler whether a claim can proceed given the ' +
    'customer premium position. Reply ONE line: "GO" or "REFER" then a short reason. ' +
    'Rule: within 1 month behind is fine; more than 1 month must go to Finance. ' +
    'Data (numbers only, no identity): ' + JSON.stringify(masked);
  const text = await ai.ask(prompt, opts);
  if (!text) return null;                            // unconfigured / refused / failed → rule still governs
  return { recommendation: /^go\b/i.test(text) ? 'GO' : 'REFER', reason: String(text).slice(0, 240) };
}

module.exports = { decide, recommend, buildMaskedPayload, GRACE_MONTHS, HIGH_ARREARS_MONTHS, MASK_ALLOW };
