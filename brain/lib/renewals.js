'use strict';
/**
 * renewals.js — automate the renewal calendar. Replaces manually watching
 * expiries, building terms, sending links, chasing, and re-rating bad policies.
 *
 * Pure decision from days-to-expiry + state (CFO 2026-07-21: retention happens
 * BEFORE expiry — the customer offer goes out ONE MONTH prior to the lapse date):
 *   - > lead (45d)               → nothing due yet
 *   - <= 45d, not yet offered    → generate terms (Graphite, once). If the loss
 *                                  ratio is bad, flag a re-rate for Underwriting
 *                                  FIRST (needs approval — pricing is a human
 *                                  call) and do NOT offer until re-rated.
 *   - <= offer (30d), good book  → offer to the customer (renewal_due message).
 *   - <= reminder (14d), offered, not renewed → nudge the customer again
 *   - expired, offer was made    → ONE agent escalation + final notice, only
 *                                  within expiredChaseDays; a policy UW withheld
 *                                  for re-rate is never invited back, and a
 *                                  long-expired policy is closed_lost, not
 *                                  chased forever.
 *   - renewed                    → done
 */

const DEFAULTS = { leadDays: 45, offerDays: 30, reminderDays: 14, expiredChaseDays: 30, lossRatioThreshold: 0.7 };

/**
 * planRenewal(policy, cfg) -> { phase, actions, ... }
 *   policy: { daysToExpiry, renewalOffered, renewed, lossRatio, product,
 *             policyNumber, amount, date, termsGenerated, rerateFlagged,
 *             expiredChased }   (the *Generated/*Flagged/*Chased flags are the
 *             caller's state latches so a daily run never repeats an action)
 */
function planRenewal(policy = {}, cfg = {}) {
  const c = { ...DEFAULTS, ...cfg };
  const d = Number(policy.daysToExpiry);
  const lr = policy.lossRatio != null ? Number(policy.lossRatio) : null;
  const base = { leadDays: c.leadDays, offerDays: c.offerDays, reminderDays: c.reminderDays };

  if (policy.renewed) return { phase: 'renewed', actions: [], ...base };

  if (!Number.isFinite(d)) return { phase: 'waiting', actions: [], ...base };

  if (d <= 0) {
    // Bounded post-expiry handling — the retention push happened pre-expiry.
    if (d < -c.expiredChaseDays) return { phase: 'closed_lost', actions: [], ...base };
    if (!policy.renewalOffered || policy.expiredChased) {
      // never invite back a policy UW withheld for re-rate; never chase twice
      return { phase: 'expired', actions: [], ...base };
    }
    return { phase: 'expired', actions: [
      { do: 'alert_agent', dept: 'Sales', note: 'policy expired unpaid — chase the customer (final)' },
      { do: 'notify_customer', dept: 'Sales', customerMsg: 'renewal_due' },
    ], ...base };
  }

  const actions = [];
  if (d <= c.leadDays && !policy.renewalOffered) {
    if (!policy.termsGenerated) actions.push({ do: 'generate_renewal_terms', dept: 'Underwriting' });
    if (lr != null && lr > c.lossRatioThreshold) {
      // bad loss ratio → re-rate before we offer anything to the customer
      if (!policy.rerateFlagged) actions.push({ do: 'flag_rerate', dept: 'Underwriting', needsApproval: true, note: `loss ratio ${(lr * 100).toFixed(0)}%` });
    } else if (d <= c.offerDays) {
      // CFO 2026-07-21: the renewal offer goes out one month before lapse
      actions.push({ do: 'notify_customer', dept: 'Sales', customerMsg: 'renewal_due' });
    }
  } else if (d <= c.reminderDays && policy.renewalOffered && !policy.renewed) {
    actions.push({ do: 'notify_customer', dept: 'Sales', customerMsg: 'renewal_due' }); // reminder
  }

  return { phase: actions.length ? 'renewing' : 'waiting', actions, ...base };
}

module.exports = { planRenewal, DEFAULTS };
