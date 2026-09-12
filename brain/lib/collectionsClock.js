'use strict';
/**
 * collectionsClock.js — the collections "non-payment clock" engine (PURE logic).
 *
 * Implements the CFO's authoritative rule (2026-07-18), ROUND 1 scope:
 *   Monthly-pay DOMESTIC (DOM) + COMMERCIAL (COM) + INSTANT (MIS) policies.
 *   ANNUAL is out of scope (handled by the caller's SQL filter).
 *
 * "Missed payment":
 *   - DOM / COM : the monthly bill was not paid (read from the schedule-driven
 *     policy_ledger — one Invoice per month vs Payment credits posted).
 *   - MIS       : the scheduled monthly auto-debit did NOT succeed on ANY channel
 *     (RealPay / VCS / DPO). MIS is ~97% of the book.
 *
 * RULE START (CFO 2026-07-21): the clock only counts months due ON/AFTER
 * 1 July 2026 (RULE_START_DATE). Pre-July arrears — however old — never count:
 * those clients had different deals and must not be penalised for past issues.
 *
 * THE CLOCK (blanket — identical for DOM / COM / MIS):
 *   2 CONSECUTIVE unpaid/failed months (a month counts only once it has ENDED)
 *     -> policy becomes DEACTIVATED / suspended at the close (LAST DAY) of the
 *        2nd unpaid month (NOT cancelled)          => stage 'deactivate_candidate'
 *     -> overdue email + 15 CALENDAR days to pay   => stage 'grace'
 *     -> still unpaid ON day 15          => stage 'cancel_candidate'
 *   Worked example (CFO 2026-07-21): July + August unpaid -> deactivated
 *   31 Aug -> cancellation lands 15 Sep.
 *
 * ARMS OFF: this file only COMPUTES and RETURNS candidate rows. It never sends,
 * writes, cancels or debits anything. The SQL that feeds it (collectionsRo.js) is
 * SELECT-only against the read replica. Everything here is a pure function of its
 * inputs so it is fully unit-testable WITHOUT a database.
 *
 * ── DATA-RELIABILITY GUARD (CFO-mandated, ref GRA-0203) ──────────────────────
 * There is a known reconciliation gap: ~2,237 instalments SUCCEEDED on RealPay
 * but still read unpaid/'A' in Graphite. A naive "status says unpaid" read would
 * therefore wrongly suspend clients who actually paid. So for every policy we
 * would list we set:
 *   signalConfidence: 'clean'      only if the unpaid/failed signal is corroborated
 *                                  — no SUCCESS on ANY channel dated on/after the
 *                                    start of the unpaid streak, and no source
 *                                    disagreement for the streak months.
 *   signalConfidence: 'uncertain'  otherwise (a later/overlapping success, or the
 *                                  RealPay-native feed disagreeing with the ledger
 *                                  / payment_transactions read).
 * Uncertain rows STILL appear in the output, clearly flagged, so a human verifies
 * before any action is taken. We never silently drop a policy.
 */

const GRACE_DAYS = 15; // calendar days between deactivation and cancellation

// CFO cancellation-notice gate: a policy may only be CANCELLED once a formal
// cancellation notice has been served to the client AND this many clear days
// have elapsed. The notice is DUE 5 days before grace end (day 10 of the 15-day
// grace) so it sits INSIDE the grace window — the cancel date itself never moves
// (CFO worked example: cancel still lands 15 Sep). Arms are OFF, so this is the
// contract the live-arm / human MUST honour: never fire a cancel while
// readyToCancel is false.
const CANCEL_NOTICE_DAYS = 5;

// CFO 2026-07-21: the clock starts 1 July 2026. Months due BEFORE this date are
// invisible to the clock — legacy arrears (incl. old MIS/DOM/COM deals) must
// never be counted toward suspension/cancellation. Only misses from July 2026
// onward count, so the earliest possible deactivate_candidate is the close of
// August 2026 (July + August both unpaid).
const RULE_START_DATE = '2026-07-01';

// ── date helpers (UTC, date-only — no PII, no timezone surprises) ────────────
const DAY_MS = 86_400_000;
function _iso(v) {
  if (v == null) return null;
  if (v instanceof Date) return v.toISOString().slice(0, 10);
  const s = String(v).trim();
  return s ? s.slice(0, 10) : null;
}
function _parse(dateStr) {
  const d = _iso(dateStr);
  if (!d) return NaN;
  return Date.parse(d + 'T00:00:00Z');
}
function _daysBetween(fromStr, toStr) {
  const a = _parse(fromStr);
  const b = _parse(toStr);
  if (Number.isNaN(a) || Number.isNaN(b)) return 0;
  return Math.floor((b - a) / DAY_MS);
}
function _addDays(dateStr, n) {
  const t = _parse(dateStr);
  if (Number.isNaN(t)) return null;
  return new Date(t + n * DAY_MS).toISOString().slice(0, 10);
}
// last calendar day of the month a date falls in (UTC, date-only)
function _monthEnd(dateStr) {
  const d = _iso(dateStr);
  if (!d) return null;
  let y = Number(d.slice(0, 4));
  let m = Number(d.slice(5, 7)) + 1; // next month, 1-12 → 2-13
  if (m > 12) { m = 1; y += 1; }
  const nextFirst = `${y}-${String(m).padStart(2, '0')}-01`;
  return new Date(_parse(nextFirst) - DAY_MS).toISOString().slice(0, 10);
}
function _round2(n) {
  return Math.round((Number(n) || 0) * 100) / 100;
}

/**
 * consecutiveUnpaidMonths(months, now) — walk the schedule from the most recent
 * COMPLETED month backwards, counting how many months in a row are unpaid. The
 * streak stops at the first paid month (or the start of the record).
 *
 * CFO 2026-07-21: a month can only count as "missed" once it has fully ENDED —
 * debits run mid/late month, so on the 5th of a month that month is still
 * collectable, not missed. (Jul + Aug unpaid → the 2-month mark is 31 Aug.)
 *
 * `months`: array of { period:'YYYY-MM', due:'YYYY-MM-DD', amount:number,
 *                       paid:boolean }  (order-independent; we sort by due).
 * Returns { count, streak } where `streak` is the unpaid months oldest→newest.
 */
function consecutiveUnpaidMonths(months = [], now) {
  const asOf = _iso(now) || new Date().toISOString().slice(0, 10);
  const due = (months || [])
    .filter((m) => m && m.due && _daysBetween(_monthEnd(m.due), asOf) >= 1) // only fully-ended months
    .slice()
    .sort((a, b) => _parse(a.due) - _parse(b.due));
  const streakDesc = [];
  for (let i = due.length - 1; i >= 0; i--) {
    if (due[i].paid) break;
    streakDesc.push(due[i]);
  }
  const streak = streakDesc.reverse(); // oldest → newest
  return { count: streak.length, streak };
}

/**
 * assessConfidence(streak, successEvents, opts) — the GRA-0203 reliability guard.
 *   streak         : unpaid months oldest→newest (from consecutiveUnpaidMonths)
 *   successEvents  : [{ channel, date }] — every SUCCESS seen on ANY channel
 *   opts.sourceConflict : true if the RealPay-native feed disagrees with the
 *                         ledger / payment_transactions read for a streak month
 * Returns { confidence, reason }.
 *
 * 'clean' requires: (a) no success dated on/after the streak's first due date and
 * (b) no source conflict. Anything else → 'uncertain'.
 */
function assessConfidence(streak = [], successEvents = [], opts = {}) {
  if (!streak.length) return { confidence: 'uncertain', reason: 'no unpaid streak' };
  const streakStart = _parse(streak[0].due);
  const laterSuccess = (successEvents || []).some((e) => {
    const t = _parse(e && e.date);
    return !Number.isNaN(t) && t >= streakStart;
  });
  if (laterSuccess) {
    return { confidence: 'uncertain', reason: 'a SUCCESS posted on/after the unpaid streak began (possible GRA-0203 recon gap) — verify before action' };
  }
  if (opts.sourceConflict) {
    return { confidence: 'uncertain', reason: 'payment sources disagree for the unpaid period — verify before action' };
  }
  return { confidence: 'clean', reason: 'no success on any channel since the streak began; sources consistent' };
}

/**
 * classifyStage(policyStatus, monthsUnpaid, daysOverdue) — map to the CFO ladder.
 *   policyStatus : 'active' | 'deactivated'
 * The policy STATE anchors the stage (arms are off, so a policy that SHOULD be
 * suspended but is still Active is a 'deactivate_candidate' regardless of how
 * many days have elapsed — the suspend + overdue email are the pending action):
 *   active,      >=2 unpaid          -> 'deactivate_candidate'
 *   deactivated, <15 days overdue    -> 'grace'
 *   deactivated, >=15 days overdue   -> 'cancel_candidate' (day 15 = cancel day)
 */
function classifyStage(policyStatus, monthsUnpaid, daysOverdue) {
  if (monthsUnpaid < 2) return null;
  if (policyStatus === 'deactivated') {
    // CFO 2026-07-21: cancellation lands ON day 15 (31 Aug + 15 = 15 Sep).
    return daysOverdue >= GRACE_DAYS ? 'cancel_candidate' : 'grace';
  }
  // active (or unknown) but arrears reached the threshold → propose deactivation
  return 'deactivate_candidate';
}

/**
 * classifyPolicy(input, now) — turn ONE normalised policy into a canonical
 * AffectedPolicy (or null if it is not affected, i.e. < 2 consecutive unpaid).
 *
 * `input` (built by collectionsRo.js from the DB, or by a test):
 * {
 *   policyNumber, customerName, productId, product, agent,
 *   billingType : 'DOM'|'COM'|'MIS',
 *   policyStatus: 'active'|'deactivated',
 *   channel     : 'RealPay'|'VCS'|'DPO'|'ledger',
 *   months      : [{ period, due, amount, paid }],
 *   successEvents: [{ channel, date }],   // any SUCCESS on any channel (guard)
 *   sourceConflict: boolean               // RealPay-native vs ledger disagreement
 * }
 */
function classifyPolicy(input = {}, now, ruleStart = RULE_START_DATE) {
  const asOf = _iso(now) || new Date().toISOString().slice(0, 10);
  // Rule-start cutoff: months due before ruleStart never enter the clock.
  const startIso = _iso(ruleStart);
  const eligible = startIso
    ? (input.months || []).filter((m) => m && m.due && _iso(m.due) >= startIso)
    : input.months;
  const { count: monthsUnpaid, streak } = consecutiveUnpaidMonths(eligible, asOf);
  if (monthsUnpaid < 2) return null; // not affected — the clock has not started

  // deactivation anchors on the CLOSE (last day) of the 2nd consecutive unpaid
  // month — CFO 2026-07-21: Jul+Aug unpaid → deactivate 31 Aug → cancel 15 Sep.
  const secondMonth = streak[1];
  const deactivatedAt = _monthEnd(secondMonth.due);
  const graceEndsAt = _addDays(deactivatedAt, GRACE_DAYS);
  const daysOverdue = Math.max(0, _daysBetween(deactivatedAt, asOf));

  const policyStatus = input.policyStatus === 'deactivated' ? 'deactivated' : 'active';
  const stage = classifyStage(policyStatus, monthsUnpaid, daysOverdue);
  if (!stage) return null;

  const amountOverdue = _round2(streak.reduce((s, m) => s + (Number(m.amount) || 0), 0));

  // ── CFO 5-day cancellation-notice gate ──────────────────────────────────────
  // A cancel_candidate is past the 15-day grace BY DATE, but it may not actually
  // be cancelled until a formal cancellation notice has been served and 5 clear
  // days have elapsed. input.cancellationNoticeAt is set (by Finance / the future
  // live-arm) when that notice goes out. Until readyToCancel is true the only
  // permitted action is to ISSUE THE NOTICE — never to cancel.
  const cancelNoticeDueAt = _addDays(deactivatedAt, GRACE_DAYS - CANCEL_NOTICE_DAYS); // grace day 10
  const cancelNoticeIssuedAt = _iso(input.cancellationNoticeAt) || null;
  const cancelNoticeElapsedDays = cancelNoticeIssuedAt ? _daysBetween(cancelNoticeIssuedAt, asOf) : null;
  const readyToCancel = stage === 'cancel_candidate'
    && cancelNoticeIssuedAt != null
    && cancelNoticeElapsedDays >= CANCEL_NOTICE_DAYS;
  const cancelBlockedReason = stage === 'cancel_candidate' && !readyToCancel
    ? (cancelNoticeIssuedAt == null
      ? 'cancellation blocked — 5-day cancellation notice not yet served'
      : `cancellation blocked — only ${cancelNoticeElapsedDays} of ${CANCEL_NOTICE_DAYS} notice days elapsed`)
    : null;

  // CFO 2026-07-21: absence of payment data is NOT proof of non-payment — a
  // policy with zero transactions in the window must never be labelled 'clean'.
  const { confidence, reason: guardReason } = input.noPaymentData
    ? { confidence: 'uncertain', reason: 'no payment data in the pull window (no debit order / cash-paid / history outside window?) — verify before action' }
    : assessConfidence(streak, input.successEvents, { sourceConflict: !!input.sourceConflict });

  const missWord = input.billingType === 'MIS' ? 'failed auto-debit' : 'unpaid';
  const stageWord = stage === 'deactivate_candidate' ? 'due for suspension'
    : stage === 'grace' ? `in 15-day grace (ends ${graceEndsAt})`
      : 'past grace — due for cancellation';
  const reason = `${monthsUnpaid} consecutive months ${missWord}; ${stageWord}. ${guardReason}.`
    + (cancelBlockedReason ? ` ${cancelBlockedReason}.` : '');

  return {
    policyNumber: String(input.policyNumber || ''),
    customerName: String(input.customerName || ''), // internal display only — never sent to an external model
    productId: input.productId != null ? Number(input.productId) : null,
    product: String(input.product || ''),
    agent: input.agent != null && input.agent !== '' ? String(input.agent) : null,
    channel: input.channel || (input.billingType === 'MIS' ? 'RealPay' : 'ledger'),
    billingType: input.billingType,
    amountOverdue,
    monthsUnpaid,
    daysOverdue,
    stage,
    deactivatedAt,
    graceEndsAt,
    // CFO 5-day cancellation-notice gate (see CANCEL_NOTICE_DAYS above).
    cancelNoticeDueAt,
    cancelNoticeIssuedAt,
    readyToCancel,
    cancelBlockedReason,
    signalConfidence: confidence,
    reason,
  };
}

/**
 * classifyAll(inputs, now) — map a batch of normalised policies to the canonical
 * AffectedPolicy[], dropping the ones that are not affected (< 2 consecutive).
 */
function classifyAll(inputs = [], now, ruleStart = RULE_START_DATE) {
  return (inputs || [])
    .map((p) => classifyPolicy(p, now, ruleStart))
    .filter((r) => r !== null);
}

module.exports = {
  GRACE_DAYS,
  CANCEL_NOTICE_DAYS,
  RULE_START_DATE,
  consecutiveUnpaidMonths,
  assessConfidence,
  classifyStage,
  classifyPolicy,
  classifyAll,
  // exported for reuse/tests
  _helpers: { _iso, _daysBetween, _addDays, _round2 },
};
