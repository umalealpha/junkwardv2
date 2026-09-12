'use strict';
/**
 * eventEngine.js — the heart of the CFO's idea: one lifecycle event fans out to
 * the right actions across departments. This is the DECISION layer — given an
 * event it returns the exact action plan (what fires, who owns it, who is told,
 * and whether a human must approve it). It does NOT execute the actions; the
 * live adapters (stop collection in omni, email Underwriting/Graphite, post the
 * JE) are wired + installed separately by Pramod. Keeping the decision pure
 * makes it fully testable here with no live systems.
 *
 * Rules baked in:
 *  - Anything that moves money or can't be undone → needsApproval: true.
 *  - Customer touchpoints resolve to lib/messageCatalogue.js (one voice).
 */

const { getMessage } = require('./messageCatalogue');

// Recipient groups (resolved to real addresses by the send adapters).
const GROUPS = {
  finance_oversight: ['CFO', 'Kago', 'Oprah', 'Keetile', 'Pako'],
  underwriting: ['Underwriting'],
  reinsurance: ['Reinsurance team'],
  claims_manager: ['Claims Manager'],
  finance: ['Finance'],
};

// Action plans per event. Each action:
//   do            machine verb the adapter will run
//   dept          owning department
//   needsApproval a human must approve before it happens (money / irreversible)
//   notify        recipient group told about it (optional)
//   customerMsg   messageCatalogue key sent to the customer (optional)
//   when          optional condition flag the caller passes in ctx.flags
//   note          plain-English explanation
const PLANS = {
  // ── Flagship: a car is written off ──────────────────────────────
  total_loss: [
    { do: 'stop_premium_collection', dept: 'Finance', needsApproval: true,
      note: 'cancel future debit orders / instalments — irreversible, waits for approval (CFO 2026-07-13)' },
    { do: 'recover_outstanding_premium_from_settlement', dept: 'Finance', needsApproval: true,
      note: 'net any premium owing off the payout' },
    { do: 'remove_asset_from_cover', dept: 'Underwriting', needsApproval: true, notify: 'underwriting',
      note: 'off-risk from date of loss — irreversible cover change, waits for approval (CFO 2026-07-13)' },
    { do: 'raise_reinsurance_recovery', dept: 'Reinsurance', needsApproval: false, notify: 'reinsurance',
      when: 'largeLoss', note: 'only when the loss touches treaty / facultative' },
    { do: 'open_salvage_file', dept: 'Salvage', needsApproval: false,
      note: 'settlement blocked until salvage in the yard + bank interest released first' },
    { do: 'notify_customer', dept: 'Claims', needsApproval: false, customerMsg: 'agreement_of_loss_to_sign',
      note: 'total-loss settlement — customer signs the Agreement of Loss' },
    { do: 'draft_settlement_je', dept: 'Accounting', needsApproval: true,
      note: 'reserve release + settlement entry — Finance approves, never auto-posted' },
    { do: 'alert_claims_manager', dept: 'Claims', needsApproval: false, notify: 'claims_manager' },
  ],

  // ── Billing / collections ───────────────────────────────────────
  debit_order_failed: [
    { do: 'schedule_retry', dept: 'Finance', needsApproval: false },
    { do: 'notify_customer', dept: 'Finance', needsApproval: false, customerMsg: 'payment_failed' },
    { do: 'start_lapse_process', dept: 'Finance', needsApproval: true, when: 'retriesExhausted',
      note: 'after set failures — lapsing cover is irreversible, waits for approval (CFO 2026-07-21)' },
    { do: 'notify_underwriting_off_risk', dept: 'Underwriting', needsApproval: false, notify: 'underwriting',
      when: 'retriesExhausted' },
  ],
  payment_received: [
    { do: 'reconcile_to_invoice', dept: 'Finance', needsApproval: false },
    { do: 'clear_arrears_flag', dept: 'Finance', needsApproval: false },
    { do: 'receipt_customer', dept: 'Finance', needsApproval: false },
  ],
  refund_due: [
    { do: 'calc_unearned_premium', dept: 'Finance', needsApproval: false },
    { do: 'pay_refund', dept: 'Finance', needsApproval: true, note: 'Finance approves the money + rail' },
    { do: 'notify_customer', dept: 'Finance', needsApproval: false, customerMsg: 'refund_processing',
      note: 'CFO 2026-07-21: say "being processed" — "paid" only after the money actually moves' },
  ],
  // fired by the adapter AFTER the approved refund has actually executed
  refund_completed: [
    { do: 'notify_customer', dept: 'Finance', needsApproval: false, customerMsg: 'refund_paid' },
  ],

  // ── Renewals ────────────────────────────────────────────────────
  renewal_due: [
    { do: 'generate_renewal_terms', dept: 'Underwriting', needsApproval: false },
    { do: 'flag_rerate_if_bad_loss_ratio', dept: 'Underwriting', needsApproval: false, when: 'highLossRatio' },
    { do: 'notify_customer', dept: 'Claims', needsApproval: false, customerMsg: 'renewal_due' },
    { do: 'escalate_to_agent_if_no_response', dept: 'Sales', needsApproval: false, when: 'noResponse' },
  ],

  // ── Claim lifecycle touchpoints ─────────────────────────────────
  claim_registered:    [{ do: 'notify_customer', dept: 'Claims', needsApproval: false, customerMsg: 'claim_received' }],
  assessment_booked:   [{ do: 'notify_customer', dept: 'Claims', needsApproval: false, customerMsg: 'assessment_booked' }],
  assessment_completed:[{ do: 'notify_customer', dept: 'Claims', needsApproval: false, customerMsg: 'assessment_done' },
                        { do: 'route_repair_or_writeoff', dept: 'Claims', needsApproval: true,
                          note: 'routing PROPOSAL only — repair spend is approved by the Claims Manager, never auto (CFO 2026-07-21)' }],
  claim_paid:          [{ do: 'notify_customer', dept: 'Claims', needsApproval: false, customerMsg: 'claim_paid' },
                        { do: 'close_claim', dept: 'Claims', needsApproval: true,
                          note: 'a human closes the claim in the inbox (CFO 2026-07-21)' }],
  complaint_logged:    [{ do: 'acknowledge_customer', dept: 'Compliance', needsApproval: false, customerMsg: 'complaint_acknowledged' },
                        { do: 'start_sla_clock', dept: 'Compliance', needsApproval: false },
                        { do: 'escalate_if_overdue', dept: 'Compliance', needsApproval: false, when: 'slaBreached' }],
};

/**
 * planActions(event, ctx)
 *  ctx.flags: optional set of condition flags (e.g. {largeLoss:true, retriesExhausted:false})
 * Returns { event, actions:[...], needsHuman:bool, unknown?:true }.
 * Actions with a `when` condition fire ONLY when the caller passes that flag as
 * explicitly true — an absent flag means "not established", so the action is
 * DROPPED (CFO 2026-07-21: missing information must never fire an action; one
 * missed debit with no flags must not start a lapse).
 */
function planActions(event, ctx = {}) {
  const plan = PLANS[event];
  if (!plan) return { event, actions: [], unknown: true };
  const flags = ctx.flags || {};
  const actions = plan
    .filter(a => !a.when || flags[a.when] === true)
    .map(a => {
      const out = {
        do: a.do, dept: a.dept, needsApproval: !!a.needsApproval,
        note: a.note || '',
      };
      if (a.notify) out.recipients = GROUPS[a.notify] || [a.notify];
      if (a.customerMsg) {
        out.customerMsg = a.customerMsg;
        out.messageReady = !!getMessage(a.customerMsg); // catalogue has the copy
      }
      if (a.when) out.condition = a.when;
      return out;
    });
  return { event, actions, needsHuman: actions.some(a => a.needsApproval) };
}

module.exports = { planActions, PLANS, GROUPS };
