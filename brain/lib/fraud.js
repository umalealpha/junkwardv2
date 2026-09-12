'use strict';
/**
 * fraud.js — early-warning signals on a new claim, before any money moves.
 * Deterministic and PII-free (dates, amounts, claim type only — never names or
 * narratives), so it is reliable and safe to run on every claim. AI narrative
 * scoring can be added later, but only through lib/ai.js (masked).
 *
 * Signals:
 *   - loss_before_cover  : loss dated before cover started            (severe → hold)
 *   - possible_duplicate : a prior claim, same type, within N days     (severe → hold)
 *   - early_claim        : loss within N days of cover starting        (soft)
 *   - late_report        : reported long after the loss                (soft)
 *   - over_sum_insured   : estimate exceeds the sum insured            (soft)
 * Verdict: any severe → 'hold'; two or more soft → 'refer'; else 'proceed'.
 */

const DAY = 86400000;
// date-only diff: truncate to YYYY-MM-DD so a datetime on one side can never
// make "same day" compute as -1 (pay 08:00 / crash 17:00 must be gap 0)
const days = (a, b) => Math.floor((Date.parse(String(b).slice(0, 10)) - Date.parse(String(a).slice(0, 10))) / DAY);
const DEFAULTS = { earlyDays: 30, lateReportDays: 30, duplicateWindowDays: 14, sumInsuredTolerance: 0.01, paymentGapDays: 7, firstPaymentWindowDays: 31 };

/**
 * assess(claim, opts) -> { recommendation, score, flags }
 *   claim: { coverStart, dateOfLoss, dateReported, claimType, sumInsured, estimate, priorClaims? }
 *   priorClaims: [{ dateOfLoss, claimType }]
 */
function assess(claim = {}, opts = {}) {
  const c = { ...DEFAULTS, ...opts };
  const flags = [];
  const add = (code, severity, detail) => flags.push({ code, severity, detail });

  const { coverStart, dateOfLoss, dateReported, claimType, paymentDate } = claim;

  // The "pay then immediately claim" pattern (CFO 2026-07-09; scoped 2026-07-21).
  // SEVERE backdating only applies to the policy's FIRST premium (new business):
  // on an in-force monthly policy the latest debit routinely lands after a loss —
  // that is arrears (premiumAdvisor's job), never backdating fraud. The soft
  // "paid then claimed straight away" signal stays for any payment.
  if (paymentDate && dateOfLoss) {
    const gap = days(paymentDate, dateOfLoss);
    const firstPayment = claim.isFirstPayment === true ||
      (coverStart && Math.abs(days(coverStart, paymentDate)) <= c.firstPaymentWindowDays);
    if (gap < 0) {
      if (firstPayment) add('loss_before_payment', 'severe', 'the loss is dated before the first premium was paid');
      else if (!coverStart) add('loss_before_latest_payment', 'soft', 'loss predates the latest payment and cover start is unknown — verify payment history');
      // in-force policy: no flag — arrears are handled by the premium advisor
    } else if (gap <= c.paymentGapDays) {
      add('claim_soon_after_payment', 'soft', `loss ${gap} day(s) after the payment`);
    }
  }

  if (coverStart && dateOfLoss && days(coverStart, dateOfLoss) < 0) {
    add('loss_before_cover', 'severe', 'date of loss is before cover started');
  } else if (coverStart && dateOfLoss) {
    const sinceCover = days(coverStart, dateOfLoss);
    if (sinceCover >= 0 && sinceCover <= c.earlyDays) {
      add('early_claim', 'soft', `loss ${sinceCover} day(s) after cover started`);
    }
  }

  if (dateOfLoss && dateReported && days(dateOfLoss, dateReported) > c.lateReportDays) {
    add('late_report', 'soft', `reported ${days(dateOfLoss, dateReported)} days after the loss`);
  }

  const si = Number(claim.sumInsured);
  const est = Number(claim.estimate);
  if (Number.isFinite(si) && Number.isFinite(est) && si > 0 && est > si * (1 + c.sumInsuredTolerance)) {
    add('over_sum_insured', 'soft', `estimate ${est} exceeds sum insured ${si}`);
  }

  for (const prior of claim.priorClaims || []) {
    if (prior.claimType && claimType && prior.claimType === claimType &&
        prior.dateOfLoss && dateOfLoss &&
        Math.abs(days(prior.dateOfLoss, dateOfLoss)) <= c.duplicateWindowDays) {
      add('possible_duplicate', 'severe', 'a prior claim of the same type is within the duplicate window');
      break;
    }
  }

  const severe = flags.filter((f) => f.severity === 'severe').length;
  const soft = flags.filter((f) => f.severity === 'soft').length;
  const recommendation = severe > 0 ? 'hold' : (soft >= 2 ? 'refer' : 'proceed');
  return { recommendation, score: severe * 10 + soft, flags };
}

module.exports = { assess, DEFAULTS };
