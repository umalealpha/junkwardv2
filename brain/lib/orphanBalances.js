'use strict';
/**
 * ORPHAN BALANCES — Rule 3 of the AI Internal Auditor for Debtors.
 *
 * ────────────────────────────────────────────────────────────────────────────
 *   WHY THIS EXISTS
 * ────────────────────────────────────────────────────────────────────────────
 * debtorsAudit.js audits the ACTIVE book. Its selector is deliberately pinned
 * to `policies.status = 1` and a guard test blocks any widening of it — that
 * filter is the fix for the 51,610 defect (PR #1791, CFO ruling 2026-08-04).
 *
 * The filter is right, and it leaves a hole. Nothing watches the balances
 * sitting on policies that are already dead. On 2026-09-07 a direct read of
 * the production ledger found 89,597 cancelled or inactive policies carrying
 * P58,566,761 net — 64% of the entire debtors book, on cover that no longer
 * exists. It accumulated unseen because the active-book audit cannot see it
 * by design, and no other report looks.
 *
 * This module is the mirror of that selector, not a change to it. Rule 3 runs
 * over `status IN (0, 2)` — Inactive and Cancelled — and the guard test below
 * blocks it from ever drifting onto the active book.
 *
 *   RULE 3 — AN ORPHAN BALANCE.  A policy that is cancelled or inactive must
 *   not carry a balance. Either the money was posted to the wrong policy, or
 *   the policy was closed without settling it. Both are defects. A balance
 *   here is never a normal position.
 *
 * Two directions, and they are not the same problem:
 *   • OWING   — we are carrying receivable against cover that does not exist.
 *               Overstates debtors until it is collected or written off.
 *   • CREDIT  — we are holding the client's money on a policy that is closed.
 *               That is a refund liability, and it is the worse of the two.
 *
 * Two populations, and they need different decisions:
 *   • REALLOCATABLE — the same customer still holds an active policy, so a
 *               misposted receipt can be moved to it. Provable, fixable today.
 *               7,972 policies / P1,688,611 as at 2026-09-07.
 *   • STRANDED  — the customer has no active policy. There is nowhere to move
 *               the money to. This needs a Board-approved write-off and refund
 *               rule, not a reallocation. 81,625 policies / P56,878,150.
 *
 * ────────────────────────────────────────────────────────────────────────────
 *   THE CLOCK IS DERIVED FROM THE LEDGER, NEVER FROM FIRST SIGHT
 * ────────────────────────────────────────────────────────────────────────────
 * Same design lesson as Rules 1 and 2. A watchdog that records "first seen =
 * today" reports nothing for its first window and resets every time the
 * container is replaced — the no-start-date defect found four times in the
 * 2026-07-21 audit. Here the age of a finding is
 * `today − MAX(policy_ledger.accounting_date)`, which is computable from the
 * ledger alone, so this audit is correct on run ONE. Snapshot history is used
 * for the trend line only and never to age a finding.
 *
 * Read-only. This module computes and renders. It posts no journals, changes
 * no status and moves no money.
 */

const brand = require('./brand');

// ── 1. Thresholds ───────────────────────────────────────────────────────────

// A dead policy's balance may not sit unresolved beyond this.
const DORMANT_MIN_DAYS = 30;

// Still unresolved beyond this = "on the list two months running" → CFO.
// Derived from the ledger clock, so it is correct on the first run.
const ESCALATE_DAYS = 60;

// CFO ruling 2026-08-04, carried over from Rules 1 and 2: flag everything.
// The 2026-09-07 read is the argument for it — 88,732 of the 89,597 orphans
// are under P5,000 and together they hold P54,200,878, which is 93% of the
// money. A size floor would hide almost all of the problem.
const MIN_AMOUNT = 0;

const DAY = 86400000;
const EPS = 0.005; // float noise guard — a 0.001 "balance" is not a balance

const STATUS_LABEL = { 0: 'Inactive', 1: 'Active', 2: 'Cancelled' };

function round2(n) { return Math.round((Number(n) || 0) * 100) / 100; }

function daysBetween(from, to) {
  if (!from) return null;
  const a = new Date(`${String(from).slice(0, 10)}T00:00:00Z`).getTime();
  const b = new Date(`${String(to || new Date().toISOString()).slice(0, 10)}T00:00:00Z`).getTime();
  if (!Number.isFinite(a) || !Number.isFinite(b)) return null;
  return Math.max(0, Math.round((b - a) / DAY));
}

// ── 2. Assess one policy ────────────────────────────────────────────────────

/**
 * assess(row, asOf) → a finding, or null if the policy is clean.
 *
 * `row` is one row of ORPHAN_POLICY_SQL:
 *   { policy_number, policy_status, customer_id, has_active_policy,
 *     balance, last_movement }
 */
function assess(row = {}, asOf, thresholds = {}) {
  const dormantMin = Number.isFinite(thresholds.dormantMinDays) ? thresholds.dormantMinDays : DORMANT_MIN_DAYS;
  const escalateAt = Number.isFinite(thresholds.escalateDays) ? thresholds.escalateDays : ESCALATE_DAYS;
  const minAmount = Number.isFinite(thresholds.minAmount) ? thresholds.minAmount : MIN_AMOUNT;

  const balance = round2(row.balance);
  if (Math.abs(balance) <= EPS) return null;
  if (Math.abs(balance) < minAmount) return null;

  const status = Number(row.policy_status);
  // Guard: this rule is about DEAD policies. An active policy reaching this
  // function means the selector drifted — refuse it rather than mis-report.
  if (status !== 0 && status !== 2) return null;

  const days = daysBetween(row.last_movement, asOf);
  const direction = balance > 0 ? 'owing' : 'credit';
  const reallocatable = Number(row.has_active_policy) === 1;

  // Dormancy inside the allowed window is not yet a breach. A policy cancelled
  // last week with a balance is being worked; one untouched for months is not.
  const breached = days == null || days > dormantMin;
  if (!breached) return null;

  // A credit balance is a refund liability, so it outranks a receivable.
  // A reallocatable case is provable and fixable today, so it also outranks
  // the stranded ones, which need a policy decision rather than a correction.
  const severity = (direction === 'credit' || reallocatable) ? 'critical' : 'warn';

  return {
    rule: 'orphan_balance',
    account: String(row.policy_number || '').trim() || `policy#${row.policy_id}`,
    policyId: Number(row.policy_id) || null,
    customerRef: row.customer_id == null ? null : Number(row.customer_id),
    statusLabel: STATUS_LABEL[status] || String(status),
    direction,
    disposition: reallocatable ? 'reallocatable' : 'stranded',
    amount: balance,
    absAmount: Math.abs(balance),
    days,
    limit: dormantMin,
    since: row.last_movement ? String(row.last_movement).slice(0, 10) : null,
    severity,
    escalateToCfo: days != null && days > escalateAt,
    owner: null, // no collector is recorded against a dead policy — see report
    fix: reallocatable
      ? 'Trace the receipt, prove which policy it was meant for, then move it to the active policy.'
      : (direction === 'credit'
        ? 'No active policy to move it to. Refund the client or write it back under the approved rule.'
        : 'No active policy to collect against. Collect, or write it off under the approved rule.'),
  };
}

// ── 3. Assess the population ────────────────────────────────────────────────

function findings(rows = [], opts = {}) {
  const asOf = opts.asOf || new Date().toISOString().slice(0, 10);
  const out = [];
  for (const row of rows) {
    const f = assess(row, asOf, opts.thresholds || {});
    if (f) out.push(f);
  }
  // Worst first: credits before receivables, then by size.
  out.sort((a, b) => {
    if (a.severity !== b.severity) return a.severity === 'critical' ? -1 : 1;
    return b.absAmount - a.absAmount;
  });
  return out;
}

// ── 4. Ageing by dormancy ───────────────────────────────────────────────────

const BUCKETS = [
  { key: '0-30', label: '30 days or less', max: 30 },
  { key: '31-60', label: '31 to 60 days', max: 60 },
  { key: '61-90', label: '61 to 90 days', max: 90 },
  { key: '91-180', label: '91 to 180 days', max: 180 },
  { key: '181-365', label: '181 days to a year', max: 365 },
  { key: '365+', label: 'Over a year', max: Infinity },
];

function ageing(list = []) {
  const out = BUCKETS.map((b) => ({ key: b.key, label: b.label, count: 0, value: 0 }));
  for (const f of list) {
    const d = f.days == null ? Infinity : f.days;
    const i = BUCKETS.findIndex((b) => d <= b.max);
    const slot = out[i === -1 ? out.length - 1 : i];
    slot.count += 1;
    slot.value = round2(slot.value + f.amount);
  }
  return out;
}

// ── 5. The audit ────────────────────────────────────────────────────────────

function runAudit({ rows = [], asOf, previous = null, thresholds = {} } = {}) {
  const when = asOf || new Date().toISOString().slice(0, 10);
  const th = {
    dormantMinDays: Number.isFinite(thresholds.dormantMinDays) ? thresholds.dormantMinDays : DORMANT_MIN_DAYS,
    escalateDays: Number.isFinite(thresholds.escalateDays) ? thresholds.escalateDays : ESCALATE_DAYS,
    minAmount: Number.isFinite(thresholds.minAmount) ? thresholds.minAmount : MIN_AMOUNT,
  };

  const list = findings(rows, { asOf: when, thresholds: th });

  const sum = (pred) => round2(list.filter(pred).reduce((t, f) => t + f.amount, 0));
  const count = (pred) => list.filter(pred).length;

  // EXPOSURE vs NET — both are needed and they answer different questions.
  //
  // NET is the balance-sheet number: what the book is actually worth.
  // EXPOSURE is the sum of absolute balances: how much money is in play and
  // how much work there is.
  //
  // They must not be used interchangeably. A credit on one client does not
  // cancel a debit on another — they are two separate corrections. Reporting
  // "reallocation reaches <net>" understates the job badly, and when credits
  // outweigh debits the net goes NEGATIVE and the sentence stops making sense
  // altogether (a real run on production rows produced "reaches -P611,413.69").
  // Use exposure wherever the claim is about reach or workload.
  const exposure = (pred) => round2(list.filter(pred).reduce((t, f) => t + Math.abs(f.amount), 0));

  const owing = (f) => f.direction === 'owing';
  const credit = (f) => f.direction === 'credit';
  const realloc = (f) => f.disposition === 'reallocatable';
  const stranded = (f) => f.disposition === 'stranded';

  const totals = {
    policiesScanned: rows.length,
    orphanCount: list.length,
    orphanValue: sum(() => true),

    owingCount: count(owing),
    owingValue: sum(owing),
    creditCount: count(credit),
    creditValue: sum(credit),

    reallocatableCount: count(realloc),
    reallocatableValue: sum(realloc),
    reallocatableExposure: exposure(realloc),
    strandedCount: count(stranded),
    strandedValue: sum(stranded),
    strandedExposure: exposure(stranded),
    totalExposure: exposure(() => true),

    escalatedCount: count((f) => f.escalateToCfo),
    criticalCount: count((f) => f.severity === 'critical'),
    oldestDormantDays: list.reduce((m, f) => Math.max(m, f.days == null ? 0 : f.days), 0),
  };

  const audit = {
    rule: 'orphan_balance',
    asOf: when,
    thresholds: th,
    totals,
    ageing: ageing(list),
    findings: list,
  };
  audit.trend = trend(audit, previous);
  return audit;
}

/** trend(now, before) → direction + deltas. Used for the narrative line ONLY. */
function trend(now, before) {
  if (!before || !before.totals) return null;
  const deltas = {
    orphanCount: (now.totals.orphanCount || 0) - (before.totals.orphanCount || 0),
    orphanValue: round2((now.totals.orphanValue || 0) - (before.totals.orphanValue || 0)),
    strandedValue: round2((now.totals.strandedValue || 0) - (before.totals.strandedValue || 0)),
  };
  const moved = deltas.orphanCount !== 0 || Math.abs(deltas.orphanValue) > 1;
  const direction = !moved ? 'flat' : (deltas.orphanCount < 0 || deltas.orphanValue < 0 ? 'improving' : 'worsening');
  return { direction, deltas };
}

// ── 6. Render — branded HTML + CSV ──────────────────────────────────────────

const DIRECTION_LABEL = { owing: 'Owed to us', credit: 'We hold their money' };
const DISPOSITION_LABEL = { reallocatable: 'Client has an active policy', stranded: 'No active policy' };

const COLUMNS = [
  { key: 'account', label: 'Policy', emphasis: true },
  { key: 'statusLabel', label: 'Status' },
  { key: 'directionLabel', label: 'Direction' },
  { key: 'dispositionLabel', label: 'Can it be moved?' },
  { key: 'amount', label: 'Balance', kind: 'money' },
  { key: 'days', label: 'Days Dormant', kind: 'num' },
  { key: 'since', label: 'Last Movement' },
];

function toRow(f) {
  return {
    account: f.account || '—',
    statusLabel: f.statusLabel || '—',
    directionLabel: DIRECTION_LABEL[f.direction] || '—',
    dispositionLabel: DISPOSITION_LABEL[f.disposition] || '—',
    amount: f.amount,
    days: f.days,
    since: f.since || '—',
    _highlight: f.severity === 'critical' ? 'critical' : 'warn',
    _note: f.fix ? `Fix: ${f.fix}` : null,
  };
}

function accountabilityLine(f) {
  const age = f.days == null ? 'an unknown period' : `${f.days} days`;
  const head = f.direction === 'credit'
    ? `Policy ${f.account} is ${f.statusLabel.toLowerCase()} and we are holding ${brand.money(Math.abs(f.amount))} of the client's money on it`
    : `Policy ${f.account} is ${f.statusLabel.toLowerCase()} and still carries ${brand.money(f.amount)} as owed to us`;
  const tail = f.disposition === 'reallocatable'
    ? 'The same client still holds an active policy, so if this is a misposted receipt it can be moved today.'
    : 'The client has no active policy, so there is nowhere to move it. This one needs a write-off or refund decision.';
  return `${head}. No movement for ${age}, against a limit of ${f.limit}. ${tail}`;
}

/** How many rows of detail go in the email before it becomes unreadable. */
const DETAIL_LIMIT = 40;

function renderHtml(audit) {
  const t = audit.totals;
  const critical = audit.findings.filter((f) => f.severity === 'critical');
  const shown = audit.findings.slice(0, DETAIL_LIMIT);

  const trendLine = !audit.trend
    ? 'First run — no previous audit to compare against, so no trend is shown yet. The ages below are still correct, because each one is measured from the last movement in the ledger and not from when this audit started watching.'
    : audit.trend.direction === 'improving'
      ? `The orphan population is COMING DOWN since the last audit: ${Math.abs(audit.trend.deltas.orphanCount)} fewer policies and ${brand.money(Math.abs(audit.trend.deltas.orphanValue))} less sitting on dead cover.`
      : audit.trend.direction === 'worsening'
        ? `The orphan population is GROWING since the last audit: ${audit.trend.deltas.orphanCount} more policies and ${brand.money(audit.trend.deltas.orphanValue)} more sitting on dead cover.`
        : 'No material change since the last audit.';

  const body = [
    brand.paragraph(
      'This is an automated audit of balances left on policies that are no longer live. '
      + 'It reports one failure: <strong>a cancelled or inactive policy that still carries a balance</strong>. '
      + 'Either a receipt was posted to the wrong policy, or the policy was closed without settling it. '
      + 'Both are defects, and a balance here is never a normal position. '
      + 'Every exception below is copied to Internal Audit.'
    ),

    brand.statRow([
      { label: 'Orphan Policies', value: brand.num(t.orphanCount), sub: brand.money(t.orphanValue) + ' net', accent: brand.BRAND.critical },
      { label: 'We Hold Their Money', value: brand.num(t.creditCount), sub: brand.money(Math.abs(t.creditValue)), accent: brand.BRAND.critical },
      { label: 'Can Be Moved', value: brand.num(t.reallocatableCount), sub: brand.money(t.reallocatableExposure) + ' in play', accent: brand.BRAND.warn },
      { label: 'Nowhere To Move It', value: brand.num(t.strandedCount), sub: brand.money(t.strandedExposure) + ' in play', accent: brand.BRAND.navy },
    ]),

    brand.callout(audit.trend && audit.trend.direction === 'improving' ? 'ok'
      : audit.trend && audit.trend.direction === 'worsening' ? 'critical' : 'info', brand.escHtml(trendLine)),

    brand.sectionHeading('The two groups need different decisions'),
    brand.paragraph(
      `<strong>${brand.num(t.reallocatableCount)} policies with ${brand.money(t.reallocatableExposure)} in play</strong> belong to clients who still have an active policy. `
      + 'If the money was posted to the wrong policy it can be moved, provided the receipt proves which policy it was meant for. '
      + `This is correction work and Finance can start on it now. It nets to ${brand.money(t.reallocatableValue)}.`
    ),
    brand.paragraph(
      `<strong>${brand.num(t.strandedCount)} policies with ${brand.money(t.strandedExposure)} in play</strong> belong to clients with no active policy at all. `
      + 'There is nowhere to move that money to. Reallocation can never clear it. '
      + 'Each balance needs a decision to collect, refund or write off, under a rule the Board has approved. '
      + `It nets to ${brand.money(t.strandedValue)}.`
    ),
    brand.paragraph(
      '<em>In play</em> is the sum of the balances themselves, which is the size of the job. '
      + '<em>Nets to</em> is what the book is worth once debits and credits offset. '
      + 'A credit on one client does not settle a debit on another, so the two figures answer different questions '
      + 'and the larger one is the workload.'
    ),

    t.strandedCount > 0
      ? brand.callout('warn',
        '<strong>Reallocation alone will not clear this book.</strong> '
        + `It reaches ${brand.money(t.reallocatableExposure)} of the ${brand.money(t.totalExposure)} in play. `
        + `The other ${brand.money(t.strandedExposure)} is a provisioning question, not a posting error.`)
      : '',

    brand.sectionHeading('How long these balances have been sitting'),
    brand.table({
      columns: [
        { key: 'label', label: 'Time since last movement', emphasis: true },
        { key: 'count', label: 'Policies', kind: 'num' },
        { key: 'value', label: 'Net balance', kind: 'money' },
      ],
      rows: audit.ageing.map((b) => ({ label: b.label, count: b.count, value: b.value })),
      empty: 'No orphan balances. Nothing to age.',
    }),

    brand.sectionHeading(`Rule 3 — Balances on dead policies, dormant over ${audit.thresholds.dormantMinDays} days (${audit.findings.length})`),
    brand.paragraph(
      audit.findings.length > DETAIL_LIMIT
        ? `Showing the ${DETAIL_LIMIT} largest of ${brand.num(audit.findings.length)}, worst first. The attached file carries the full list.`
        : 'Worst first. Money we are holding on closed cover ranks above money we say is owed to us.'
    ),
    brand.table({
      columns: COLUMNS,
      rows: shown.map(toRow),
      empty: 'No cancelled or inactive policy is carrying a balance. Nothing to escalate.',
    }),

    shown.length
      ? `<div style="margin:12px 0 4px;font-family:${brand.FONT_BODY};font-size:12px;line-height:1.7;color:${brand.BRAND.text}">`
        + shown.slice(0, 12).map((f) => `<div style="margin-bottom:7px">• ${brand.escHtml(accountabilityLine(f))}</div>`).join('')
        + '</div>'
      : '',

    t.escalatedCount > 0
      ? brand.callout('critical',
        `<strong>${brand.num(t.escalatedCount)} balance(s) have been dormant for more than ${audit.thresholds.escalateDays} days.</strong> `
        + 'Under the standing rule these are escalated to the CFO, because they have now been outstanding across two reporting cycles.')
      : '',

    brand.callout('info',
      '<strong>No collector is named on any line above.</strong> '
      + 'A dead policy carries no owner in the records, so accountability cannot be attributed from the data. '
      + 'Until Finance assigns an owner to the orphan list, the whole team carries it.'),

    brand.callout('info',
      'Balances are recomputed from the invoice and payment lines in the ledger. '
      + 'The stored balance field on the policy record is not used, because it reads zero on every policy. '
      + 'Invoice premium and VAT component lines are excluded so each invoice is counted once. '
      + 'This audit is read-only: it posts no journals and changes no policy.'),
  ].join('');

  return brand.reportShell({
    title: 'AI Internal Auditor — Orphan Balances',
    eyebrow: 'Alpha Brain · Internal Audit',
    generatedAt: audit.asOf,
    bodyHtml: body,
    footerNote: 'Automated audit of balances on cancelled and inactive policies. Escalations are copied to '
      + 'Internal Audit and retained as a permanent record. Thresholds are configurable by Finance; '
      + 'this report does not post journals or change any record. '
      + 'Not reconciled to the management accounts — reconcile before quoting any figure externally.',
  });
}

function csvCell(v) {
  const s = v == null ? '' : String(v);
  return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
}

function renderCsv(audit) {
  const header = ['Policy', 'Status', 'Direction', 'Can it be moved', 'Balance',
    'Days Dormant', 'Last Movement', 'Severity', 'Escalated to CFO', 'Fix'];
  const lines = [
    csvCell(`AI Internal Auditor — Orphan Balances,as at ${audit.asOf || ''}`),
    '',
    header.map(csvCell).join(','),
  ];
  if (audit.findings.length === 0) {
    lines.push(csvCell('(no exceptions)'));
  } else {
    for (const f of audit.findings) {
      lines.push([
        f.account, f.statusLabel,
        DIRECTION_LABEL[f.direction] || '', DISPOSITION_LABEL[f.disposition] || '',
        round2(f.amount), f.days == null ? '' : f.days, f.since || '',
        f.severity, f.escalateToCfo ? 'yes' : 'no', f.fix || '',
      ].map(csvCell).join(','));
    }
  }
  return lines.join('\r\n');
}

// ── 7. Wiring into the sweep + recipients ───────────────────────────────────

/**
 * exceptions(audit) → brain queue items for the Finance team.
 * needsApproval:false — an audit finding is work to do, not an action to approve.
 * Nothing here executes; the queue is a human worklist.
 */
function exceptions(audit) {
  if (!audit || !Array.isArray(audit.findings)) return [];
  return audit.findings.map((f) => ({
    id: `orphan-balance:${f.account}`,
    domain: 'debtors_audit',
    team: 'Finance',
    // Same band as Rules 1 and 2 (65/78) — a breach of a standing CFO rule
    // with an Internal Audit copy, above routine ageing at 30–60.
    priority: f.severity === 'critical' ? 78 : 65,
    title: f.direction === 'credit'
      ? `Holding ${brand.money(Math.abs(f.amount))} on a ${f.statusLabel.toLowerCase()} policy — dormant ${f.days} days`
      : `${brand.money(f.amount)} owed on a ${f.statusLabel.toLowerCase()} policy — dormant ${f.days} days`,
    detail: accountabilityLine(f) + ` Fix: ${f.fix}`,
    ref: f.account,
    status: 'open',
    needsApproval: false,
    audit: f,
  }));
}

// Recipients live in settings, never in code — same pattern as debtorsAudit,
// fridayReport and highArrearsAlert. Empty until Operations sets them, and the
// send then no-ops, so this cannot email anyone until it is deliberately armed.
const RECIPIENT_ENV = {
  finance: 'BRAIN_ORPHAN_BALANCES_FINANCE_RECIPIENTS',
  internalAudit: 'BRAIN_ORPHAN_BALANCES_IA_RECIPIENTS',
  cfo: 'BRAIN_ORPHAN_BALANCES_CFO_RECIPIENTS',
};

function recipients(env = process.env) {
  const split = (v) => String(v || '').split(',').map((s) => s.trim()).filter(Boolean);
  const out = {};
  for (const [k, key] of Object.entries(RECIPIENT_ENV)) out[k] = split(env[key]);
  out.all = [...new Set([].concat(out.finance, out.internalAudit, out.cfo))];
  return out;
}

module.exports = {
  runAudit, assess, findings, trend, ageing,
  renderHtml, renderCsv, accountabilityLine, exceptions,
  recipients, toRow,
  DORMANT_MIN_DAYS, ESCALATE_DAYS, MIN_AMOUNT,
  STATUS_LABEL, DIRECTION_LABEL, DISPOSITION_LABEL,
  COLUMNS, BUCKETS, RECIPIENT_ENV, DETAIL_LIMIT,
};
