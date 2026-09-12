'use strict';
// brains.js — turn a batch of records into ONE ranked exception queue per team.
//
// SAFETY: this uses the pure decision modules only, and aggregates every
// resulting action through the orchestrator with NO live dependencies ({}),
// so actions are COLLECTED as intents and NOTHING is ever sent or executed
// here. The queue is a to-do list for humans / the (separately wired) live
// arms — nothing more.

const { handleEvent } = require('./lib/orchestrator');
const collectionsClock = require('./lib/collectionsClock');
const kycChaser = require('./lib/kycChaser');
const ageing = require('./lib/ageing');
const debtorsAudit = require('./lib/debtorsAudit');
const fraud = require('./lib/fraud');
const healthcareCompliance = require('./lib/healthcareCompliance');
const { HIGH_ARREARS_MONTHS } = require('./config');

const NO_LIVE_DEPS = {}; // empty deps → orchestrator collects, fires nothing
const n2 = (x) => Math.round((Number(x) || 0) * 100) / 100;

async function sweep(records = {}) {
  const generatedAt = new Date().toISOString();
  const teams = {};
  const push = (team, ex) => { (teams[team] = teams[team] || []).push(ex); };

  // 1) Collections (Finance) — the 2-consecutive-months non-payment clock
  // (CFO rule 2026-07-18/21; the old 30-day collectionWatchdog is retired —
  // one rule, one start date). records.collections = normalised clock inputs
  // (collectionsRo.assemblePolicyInputs shape). Suspension/cancellation are
  // ALWAYS human-approved.
  // Live pulls attach records.affected (already classified by collectionsRo via
  // the correct clock-input shape); fixtures provide records.collections which
  // we classify here. Prefer the live list when present.
  const affectedRows = Array.isArray(records.affected)
    ? records.affected
    : collectionsClock.classifyAll(records.collections || []);
  for (const r of affectedRows) {
    // CFO 5-day cancellation-notice gate: a cancel_candidate that has not yet
    // served the 5-day notice is NOT a cancel action — it is a "serve the
    // notice" action. Never surface it as ready-to-cancel.
    const cancelBlocked = r.stage === 'cancel_candidate' && r.readyToCancel === false;
    push('Finance', {
      id: `collections:${r.policyNumber}`, domain: 'collections', team: 'Finance',
      priority: r.stage === 'cancel_candidate' ? 90 : (r.stage === 'deactivate_candidate' ? 80 : 55),
      title: r.stage === 'cancel_candidate'
        ? (cancelBlocked
          ? 'Cancellation notice due — serve 5-day notice (do NOT cancel yet)'
          : 'Cancel candidate — grace expired, 5-day notice served (awaiting approval)')
        : (r.stage === 'deactivate_candidate' ? 'Suspension candidate — 2 consecutive months unpaid (awaiting approval)'
          : `In 15-day grace (ends ${r.graceEndsAt}) — monitor`),
      detail: `Policy ${r.policyNumber} · ${r.billingType} · ${r.monthsUnpaid} months unpaid`
        + ` · ${r.amountOverdue.toFixed(2)} overdue · ${r.channel} · ${r.signalConfidence}`
        + (cancelBlocked ? ` · ${r.cancelBlockedReason}` : ''),
      ref: r.policyNumber, phase: r.stage,
      needsApproval: r.stage !== 'grace', status: 'open', affected: r,
    });
  }

  // 1b) Collections LEAK (Finance) — the REVERSE of the clock above: policies that
  // are Deactivated / Cancelled / Expired but whose RealPay mandate is STILL live
  // and STILL taking successful debits (money out on cover that no longer exists).
  // CFO 2026-08-11: the system FLAGS, a human checks why the policy is inactive and
  // stops the debit — never an automatic block (our own bad data must not cut off a
  // paying customer). One headline summary + one money-ranked item per policy.
  // If the leak pull itself failed, the control did NOT run — say so in the queue
  // it feeds (a watchdog that vanishes on its own outage is the very silent-gap
  // this control exists to catch). Never leave it to a log line nobody reads.
  if (records.leakPullFailed) {
    push('Finance', {
      id: 'collections-leak:unavailable', domain: 'collections', team: 'Finance', priority: 90,
      title: 'MIS leak watch could not read Graphite this run — coverage degraded',
      detail: `The debit-on-a-dead-policy check did not run this cycle (${records.leakPullFailed}).`
        + ' Treat the leak list as INCOMPLETE until the next clean run.',
      ref: null, status: 'open', needsApproval: false,
    });
  }
  const leaking = Array.isArray(records.leaking) ? records.leaking : [];
  if (leaking.length) {
    const leakTotal = n2(leaking.reduce((s, r) => s + (Number(r.recentAmount) || 0), 0));
    push('Finance', {
      id: 'collections-leak:summary', domain: 'collections', team: 'Finance', priority: 93,
      title: `MIS collections leak — ${leaking.length} dead policies still being debited`,
      detail: `${leaking.length} inactive MIS/ADH policies whose RealPay mandate is still live took a `
        + `successful debit in the last ${leaking[0].windowDays} days · ${leakTotal.toFixed(2)} collected on `
        + 'policies that no longer exist · each needs a person to check why it is inactive and stop the mandate',
      ref: null, status: 'open', needsApproval: false, count: leaking.length, amount: leakTotal,
    });
  }
  for (const r of leaking) {
    push('Finance', {
      id: `collections-leak:${r.policyNumber}`, domain: 'collections', team: 'Finance',
      priority: r.priority,
      title: `${r.statusLabel} policy still being debited — stop the mandate`,
      detail: `Policy ${r.policyNumber} · ${r.billingType} · ${r.statusLabel}`
        + ` · ${r.recentDebits} debit(s) ${r.recentAmount.toFixed(2)} in ${r.windowDays}d`
        + ` · last ${r.lastDebit} · mandate still active`,
      ref: r.policyNumber, status: 'open', needsApproval: true, leak: r,
    });
  }

  // 2) KYC (Compliance) — deep, per product (commercial goes to beneficial ownership).
  for (const k of records.kyc || []) {
    const a = kycChaser.assess(k);
    if (a.status === 'COMPLIANT') continue;
    const escalate = a.actions.some((x) => x.do === 'escalate_flag_kyc_hold');
    push('Compliance', {
      id: `kyc:${k.ref}`, domain: 'kyc', team: 'Compliance',
      priority: escalate ? 68 : (a.status === 'REVIEW' ? 40 : 45),
      title: escalate ? 'KYC hold — reminders exhausted, payout blocked'
        : (a.status === 'REVIEW' ? 'KYC manual review (poor scan)' : 'KYC documents outstanding'),
      detail: `${k.ref} · ${k.product} · missing: ${a.missing.join(', ') || '—'}`
        + ` · invalid: ${a.invalid.map((i) => i.type).join(', ') || '—'}`
        + (a.needsReview.length ? ` · review: ${a.needsReview.map((r) => r.type).join(', ')}` : ''),
      ref: k.ref, status: 'open', needsApproval: false, kyc: a,
    });
  }

  // 2b) Healthcare (Compliance) — ONE summary line for the MIS/ADH paperwork gap.
  // A summary, not one row per policy: the customer-level health list belongs in
  // the permission-gated extract, not in a queue whose counts are open to all
  // staff. (CFO 28 Jul: healthcare non-compliance was nowhere on the console.)
  for (const ex of healthcareCompliance.exceptions(records.healthcare)) push('Compliance', ex);

  // 3) Debtors (Finance) — CORRECT allocation-first ageing (fixes the Graphite bug).
  for (const d of records.debtors || []) {
    const r = ageing.age(d.invoices || [], d.payments || [], d.asOf);
    const overdue = n2(r.buckets.b60 + r.buckets.b90 + r.buckets.b120plus);
    if (overdue <= 0 && r.unappliedCredit <= 0) continue; // genuinely healthy → drops out
    push('Finance', {
      id: `debtor:${d.account}`, domain: 'debtors', team: 'Finance',
      priority: r.buckets.b120plus > 0 ? 60 : (r.buckets.b90 > 0 ? 50 : (r.buckets.b60 > 0 ? 40 : 30)),
      title: `Overdue balance ${overdue.toFixed(2)}`,
      detail: `${d.account} · balance ${r.balance.toFixed(2)} · 61-90: ${r.buckets.b60}`
        + ` · 91-120: ${r.buckets.b90} · >120: ${r.buckets.b120plus}`
        + (r.unappliedCredit ? ` · unapplied credit ${r.unappliedCredit}` : ''),
      ref: d.account, status: 'open', needsApproval: false, aging: r,
    });
  }

  // 3b) AI INTERNAL AUDITOR FOR DEBTORS (Finance, copied to Internal Audit) —
  // the CFO's two standing rules (2026-08-04) over the SAME ageing data: a
  // negative standing over a month, and a 90-plus that has stopped moving.
  // These are RULE BREACHES, not routine ageing, so they carry a higher priority
  // than the debtor items above and state an escalation.
  //
  // The audit itself is computed in daily.js — it needs the previous snapshot to
  // draw the trend line, and store access does not belong in this pure sweep.
  // Here we only SURFACE it. exceptions() returns [] when nothing is attached.
  for (const ex of debtorsAudit.exceptions(records.debtorsAudit)) push('Finance', ex);

  // 4) Claims (Claims + Finance) — fraud signals + high-arrears oversight.
  for (const cl of records.claims || []) {
    const f = fraud.assess(cl);
    if (f.recommendation !== 'proceed') {
      push('Claims', {
        id: `fraud:${cl.claimNumber}`, domain: 'fraud', team: 'Claims',
        priority: f.recommendation === 'hold' ? 95 : 70,
        title: f.recommendation === 'hold' ? 'HOLD — severe fraud signal' : 'Refer — multiple soft signals',
        detail: `${cl.claimNumber} · ${f.flags.map((x) => x.code).join(', ')}`,
        ref: cl.claimNumber, status: 'open', needsApproval: true, fraud: f,
      });
    }
    if (cl.financeApproved === true && Number(cl.arrearsMonths) > HIGH_ARREARS_MONTHS) {
      push('Finance', {
        id: `high-arrears:${cl.claimNumber}`, domain: 'high_arrears', team: 'Finance', priority: 85,
        title: `High-arrears claim approved (${cl.arrearsMonths} months)`,
        detail: `${cl.claimNumber} · approved despite > ${HIGH_ARREARS_MONTHS} months arrears — oversight alert`,
        ref: cl.claimNumber, status: 'open', needsApproval: false,
      });
    }
  }

  // 5) Lifecycle events (any dept) — the eventEngine plans.
  // The queue is for EXCEPTIONS needing human attention. Surface events
  // INDIVIDUALLY only when they need approval. Routine no-approval events (e.g.
  // ~50k daily payment_received → reconcile_to_invoice) are COLLAPSED into ONE
  // summary item per type — otherwise they flood the queue with tens of
  // thousands of no-op items, which ballooned queue.json and OOM-killed the
  // container. Nothing is silently dropped: the summary carries the count.
  const routineEvents = {}; // event → { count, team }
  for (const e of records.events || []) {
    const s = await handleEvent(e.event, e.ctx || {}, NO_LIVE_DEPS);
    if (s.unknown) continue;
    const needsApproval = s.queuedForApproval.length > 0;
    const team = (s.intents[0] && s.intents[0].dept) || 'Claims';
    const ref = (e.ctx && (e.ctx.claimNumber || e.ctx.policyNumber)) || null;
    if (!needsApproval) {
      const r = routineEvents[e.event] || (routineEvents[e.event] = { count: 0, team });
      r.count += 1;
      continue; // routine — summarised below, not queued individually
    }
    // dual control: a money intent on the item requires TWO distinct approvers
    const approvalsRequired = s.intents.some((i) => i.approvals && i.approvals.required > 1) ? 2 : 1;
    push(team, {
      id: `event:${e.event}:${ref || ''}`, domain: 'event', team,
      priority: 75,
      title: `Event: ${e.event.replace(/_/g, ' ')}`,
      detail: `${s.intents.length} intent(s) · ${s.queuedForApproval.length} awaiting approval · ${s.skipped.length} skipped`,
      ref, status: 'open', needsApproval, approvalsRequired, intents: s.intents,
    });
  }
  // One bounded summary per routine event type (keeps the count visible without
  // flooding — e.g. "payment received — 50,214 routine (no action needed)").
  for (const [event, r] of Object.entries(routineEvents)) {
    push(r.team, {
      id: `event-summary:${event}`, domain: 'event', team: r.team, priority: 15,
      title: `Event: ${event.replace(/_/g, ' ')} — ${r.count} routine (no action needed)`,
      detail: `${r.count} ${event.replace(/_/g, ' ')} event(s) auto-handled, no approval required — summarised, not listed individually.`,
      ref: null, status: 'open', needsApproval: false, count: r.count,
    });
  }

  for (const t of Object.keys(teams)) teams[t].sort((a, b) => b.priority - a.priority);
  const counts = Object.fromEntries(Object.entries(teams).map(([t, arr]) => [t, arr.length]));
  const total = Object.values(counts).reduce((s, n) => s + n, 0);
  return { generatedAt, total, counts, teams };
}

module.exports = { sweep };
