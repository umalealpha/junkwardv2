'use strict';
/**
 * orchestrator.js — the unifier. Turns a decision (a list of actions) into a
 * working flow: DOES the things the tracker can do itself (notify the customer,
 * raise alerts), QUEUES anything that needs a human's approval (money /
 * irreversible), and EMITS a structured intent for every cross-system action so
 * Pramod's adapters can carry it out in omni / Graphite / MotoLink. Capabilities
 * and the intent sink are injected, so the whole flow is testable now.
 *
 * `handleEvent` runs an eventEngine plan. `dispatchActions` runs any action list
 * (also used by the collection watchdog). Same dispatch, one system.
 *
 * Human-in-the-loop: an action with needsApproval is NEVER executed here — it is
 * emitted as an intent with status 'awaiting_approval'. CFO 2026-07-13: every
 * irreversible action (cancel policy, remove from pay-gate, stop a collection,
 * remove asset from cover) is approval-gated and waits in the inbox for a human.
 *
 * Intents travel between Alpha's OWN systems (internal), so they may carry the
 * claim/policy reference. External AI calls go through lib/ai.js (PII-stripped);
 * intents do not.
 */

const { planActions } = require('./eventEngine');

function systemFor(doVerb) {
  if (/motolink/i.test(doVerb)) return 'motolink';
  if (/paygate|realpay|dpo|vcs|premium|collection|refund|reconcile|receipt|lapse|_je$|settlement/i.test(doVerb)) return 'omni';
  if (/asset|cover|reinsurance|salvage|endorse|rerate|renewal_terms|cancel_policy/i.test(doVerb)) return 'graphite';
  return 'tracker';
}

function intentPayload(ctx) {
  const p = {};
  for (const k of ['claimNumber', 'policyNumber', 'dateOfLoss', 'amount', 'arrearsMonths', 'gateway', 'product']) {
    if (ctx[k] != null) p[k] = ctx[k];
  }
  return p;
}

/**
 * dispatchActions(actions, ctx, deps) -> { executed, queuedForApproval, intents, skipped }
 *   deps.notifyCustomer(async {customerMsg, ctx})
 *   deps.sendAlert(async (verb, ctx))       — internal alerts / reminders
 *   deps.emitIntent((intent) => {})         — cross-system + awaiting-approval sink
 */
async function dispatchActions(actions = [], ctx = {}, deps = {}) {
  const emitIntent = deps.emitIntent || (() => {});
  const out = { executed: [], queuedForApproval: [], intents: [], skipped: [] };

  for (const a of actions) {
    // 1) Human-in-the-loop — money / irreversible: queue, never auto-do.
    if (a.needsApproval) {
      const intent = { system: systemFor(a.do), action: a.do, dept: a.dept,
                       status: 'awaiting_approval', payload: intentPayload(ctx) };
      // Company payment standard (CFO 2026-07-21): anything that MOVES MONEY
      // needs TWO approvers — 1× Finance Manager/Financial Controller and
      // 1× CFO/CEO. Stamped on the intent so the inbox enforces it.
      if (/(^|_)(pay|refund|settlement|reverse|recover)(_|$)|_je$/.test(a.do)) {
        intent.approvals = { required: 2, roles: ['finance_manager_or_financial_controller', 'cfo_or_ceo'] };
      }
      emitIntent(intent); out.intents.push(intent); out.queuedForApproval.push(a.do);
      continue;
    }
    // 2) Customer notification — tracker capability.
    if (a.customerMsg) {
      if (deps.notifyCustomer) { await deps.notifyCustomer({ customerMsg: a.customerMsg, ctx }); out.executed.push(a.do); }
      else out.skipped.push(a.do);
      continue;
    }
    // 3) Internal alerts / accountant reminders — tracker capability.
    if (a.do.startsWith('alert_') || a.do.startsWith('remind_')) {
      if (deps.sendAlert) { await deps.sendAlert(a.do, { ...ctx, recipients: a.recipients }); out.executed.push(a.do); }
      else out.skipped.push(a.do);
      continue;
    }
    // 4) Everything else is cross-system → emit an intent for the adapter.
    const sys = systemFor(a.do);
    const intent = { system: sys, action: a.do, dept: a.dept,
                     status: sys === 'motolink' ? 'blocked_until_key' : 'queued',
                     payload: intentPayload(ctx) };
    emitIntent(intent); out.intents.push(intent);
  }
  return out;
}

/** handleEvent(event, ctx, deps) -> summary (adds event + unknown). */
async function handleEvent(event, ctx = {}, deps = {}) {
  const plan = planActions(event, ctx);
  const s = await dispatchActions(plan.actions, ctx, deps);
  return { event, ...s, unknown: !!plan.unknown };
}

module.exports = { handleEvent, dispatchActions, systemFor };
