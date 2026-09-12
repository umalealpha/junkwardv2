'use strict';
/**
 * debtorsAudit.js — the AI INTERNAL AUDITOR FOR DEBTORS (CFO 2026-08-04).
 *
 * WHY IT EXISTS. At 30 June 2026 the debtors book stood at ~P30m and had not been
 * reviewed before the final trial balance. Two specific diseases went unnoticed
 * for months, and neither is hard to detect — nobody was watching:
 *
 *   RULE 1 — A STANDING NEGATIVE.  A credit balance on a debtor account is a
 *   posting error, an unallocated receipt or an unmatched credit note. It is
 *   never a normal resting state. The CFO's rule: a negative must not remain
 *   negative in the age analysis for MORE THAN A MONTH. If it does, alert the
 *   whole finance team, name the owner, and escalate to Internal Audit.
 *
 *   RULE 2 — A STUCK 90-PLUS.  A balance that reaches the 90-day bucket and then
 *   simply SITS there is not being collected. Ageing past 90 days is a red flag;
 *   ageing past 90 days and not moving for another month is a failure of
 *   collection. Same alert, same escalation.
 *
 * THE CLOCK IS DERIVED FROM THE LEDGER, NOT FROM WHEN WE STARTED LOOKING.
 * This is the single most important design decision here. A naive watchdog
 * records "first seen negative = today" on its first run, so it cannot report
 * anything for 30 days and silently resets whenever the container is replaced.
 * That exact no-start-date defect was found FOUR times in the 2026-07-21 audit.
 * Instead:
 *   · a negative is dated from the EARLIEST UNALLOCATED RECEIPT (ageing.openPayments)
 *     — the day the credit actually arose;
 *   · a 90-plus is dated from INVOICE DATE + 91 DAYS — the day it actually
 *     crossed the line.
 * Both are computable from the invoices and payments themselves, so the audit is
 * correct and actionable on its FIRST run. Snapshot history is kept only as a
 * cross-check and to draw the trend line; it is never the source of the age.
 *
 * NO SIZE FLOOR. The CFO's explicit instruction (2026-08-04) was to flag
 * EVERYTHING — a P2 stuck credit is still a broken posting. minAmount defaults
 * to 0 and exists only so Finance can raise it later if the noise is real.
 *
 * SCOPE OF ACTION. This module DECIDES and REPORTS. It never posts a journal,
 * never writes to Graphite, never allocates a receipt. Every send goes through
 * the existing gates (mailer.sendInternalReport → BRAIN_INTERNAL_REPORTS +
 * internal-domain check). Pure and deterministic: `asOf` is always passed in;
 * nothing here calls Date.now().
 *
 * DPA. The DeepSeek call carries an OPAQUE INDEX and numbers only — no account
 * reference, no client name, no policy number. Results are mapped back to
 * accounts locally, in this process. See explainFindings().
 */

const ageing = require('./ageing');
const brand = require('./brand');
const ai = require('./ai');

// ── The CFO's thresholds ────────────────────────────────────────────────────
// A negative may stand for one month. Longer = a breach.
const NEGATIVE_MAX_DAYS = 30;
// Once past 90 days, a balance may sit for one more month before it is a breach.
// The CFO said "stays there for more than it should" without fixing a number;
// 30 days mirrors Rule 1 and is the assumption to confirm. Override per-run.
const OVER90_MAX_DAYS = 30;
// CFO 2026-08-04: flag everything. Present so Finance can raise it later.
const MIN_AMOUNT = 0;

const DAY = 86400000;
const EPS = 0.005; // float noise guard — a 0.001 "credit" is not a credit

function round2(n) { return Math.round((Number(n) || 0) * 100) / 100; }
function daysBetween(from, to) {
  const a = Date.parse(from), b = Date.parse(to);
  if (!isFinite(a) || !isFinite(b)) return 0;
  return Math.floor((b - a) / DAY);
}
function addDays(iso, n) {
  const t = Date.parse(iso);
  if (!isFinite(t)) return null;
  return new Date(t + n * DAY).toISOString().slice(0, 10);
}
function minDate(dates) {
  const valid = dates.filter((d) => d && isFinite(Date.parse(d)));
  if (!valid.length) return null;
  return valid.reduce((m, d) => (Date.parse(d) < Date.parse(m) ? d : m));
}

// ── 1. Per-account position ─────────────────────────────────────────────────

/**
 * assess(account, asOf) → the account's audited position.
 *   account: { account, invoices, payments, owner?, customerName?, product? }
 *
 * over90 is the 91+ exposure (b90 + b120plus) — NOT the >60 figure the sweep
 * uses elsewhere. negativeSince / over90Since are DERIVED from the ledger.
 */
function assess(account = {}, asOf) {
  const asof = asOf || account.asOf;
  const r = ageing.age(account.invoices || [], account.payments || [], asof);

  const over90 = round2(r.buckets.b90 + r.buckets.b120plus);
  const isNegative = r.balance < -EPS;

  // A negative is dated from the earliest receipt we could not allocate.
  const negativeSince = isNegative
    ? minDate((r.openPayments || []).map((p) => p.date))
    : null;

  // A 90-plus is dated from the day the oldest still-open 91+ invoice crossed 90.
  const crossings = (r.openInvoices || [])
    .filter((i) => i.ageDays > 90)
    .map((i) => addDays(i.date, 91));
  const over90Since = over90 > EPS ? minDate(crossings) : null;

  return {
    account: account.account,
    owner: account.owner || null,
    customerName: account.customerName || null,
    product: account.product || null,
    balance: r.balance,
    buckets: r.buckets,
    unappliedCredit: r.unappliedCredit,
    over90,
    isNegative,
    negativeSince,
    over90Since,
    daysNegative: negativeSince ? daysBetween(negativeSince, asof) : 0,
    daysOver90: over90Since ? daysBetween(over90Since, asof) : 0,
    openInvoiceCount: (r.openInvoices || []).length,
    openPaymentCount: (r.openPayments || []).length,
    asOf: r.asOf,
  };
}

// ── 2. Breach detection ─────────────────────────────────────────────────────

/**
 * findings(positions, { asOf, negativeMaxDays, over90MaxDays, minAmount })
 * → the breaches, worst first. One account can breach BOTH rules (two findings).
 */
function findings(positions = [], opts = {}) {
  const negMax = opts.negativeMaxDays != null ? opts.negativeMaxDays : NEGATIVE_MAX_DAYS;
  const o90Max = opts.over90MaxDays != null ? opts.over90MaxDays : OVER90_MAX_DAYS;
  const floor = opts.minAmount != null ? opts.minAmount : MIN_AMOUNT;
  const out = [];

  for (const p of positions) {
    if (p.isNegative && p.daysNegative > negMax && Math.abs(p.balance) >= floor) {
      out.push({
        rule: 'stuck_negative',
        account: p.account,
        owner: p.owner,
        amount: p.balance,               // negative — an amount we are holding
        days: p.daysNegative,
        since: p.negativeSince,
        limit: negMax,
        severity: p.daysNegative > negMax * 3 ? 'critical' : 'warn',
        escalateTo: 'internal_audit',
        openInvoiceCount: p.openInvoiceCount,
        openPaymentCount: p.openPaymentCount,
        buckets: p.buckets,
      });
    }
    if (p.over90 > EPS && p.daysOver90 > o90Max && p.over90 >= floor) {
      out.push({
        rule: 'stuck_over_90',
        account: p.account,
        owner: p.owner,
        amount: p.over90,
        days: p.daysOver90,
        since: p.over90Since,
        limit: o90Max,
        severity: p.daysOver90 > o90Max * 3 ? 'critical' : 'warn',
        escalateTo: 'internal_audit',
        openInvoiceCount: p.openInvoiceCount,
        openPaymentCount: p.openPaymentCount,
        buckets: p.buckets,
      });
    }
  }

  // Worst first: critical before warn, then longest-standing, then largest.
  const rank = (f) => (f.severity === 'critical' ? 0 : 1);
  out.sort((a, b) => rank(a) - rank(b) || b.days - a.days || Math.abs(b.amount) - Math.abs(a.amount));
  return out;
}

// ── 3. The audit ────────────────────────────────────────────────────────────

/**
 * runAudit({ accounts, asOf, previous, thresholds }) → the full audit object.
 *   previous: the last persisted audit (for the trend line). Optional.
 * Pure — no I/O, no clock. `asOf` is required for a meaningful result.
 */
function runAudit({ accounts = [], asOf, previous = null, thresholds = {} } = {}) {
  const positions = accounts.map((a) => assess(a, asOf));
  const breaches = findings(positions, { ...thresholds, asOf });

  const negatives = positions.filter((p) => p.isNegative);
  const over90s = positions.filter((p) => p.over90 > EPS);

  const totals = {
    accounts: positions.length,
    bookBalance: round2(positions.reduce((s, p) => s + p.balance, 0)),
    negativeCount: negatives.length,
    negativeValue: round2(negatives.reduce((s, p) => s + p.balance, 0)),
    over90Count: over90s.length,
    over90Value: round2(over90s.reduce((s, p) => s + p.over90, 0)),
    stuckNegativeCount: breaches.filter((f) => f.rule === 'stuck_negative').length,
    stuckNegativeValue: round2(breaches.filter((f) => f.rule === 'stuck_negative')
      .reduce((s, f) => s + f.amount, 0)),
    stuckOver90Count: breaches.filter((f) => f.rule === 'stuck_over_90').length,
    stuckOver90Value: round2(breaches.filter((f) => f.rule === 'stuck_over_90')
      .reduce((s, f) => s + f.amount, 0)),
    oldestBreachDays: breaches.reduce((m, f) => Math.max(m, f.days), 0),
    unassignedOwnerCount: breaches.filter((f) => !f.owner).length,
  };

  return {
    title: 'AI Internal Auditor — Debtors',
    asOf: asOf || null,
    thresholds: {
      negativeMaxDays: thresholds.negativeMaxDays != null ? thresholds.negativeMaxDays : NEGATIVE_MAX_DAYS,
      over90MaxDays: thresholds.over90MaxDays != null ? thresholds.over90MaxDays : OVER90_MAX_DAYS,
      minAmount: thresholds.minAmount != null ? thresholds.minAmount : MIN_AMOUNT,
    },
    totals,
    findings: breaches,
    trend: trend(totals, previous && previous.totals),
    aiExplained: false,
  };
}

/**
 * trend(now, before) → is the book getting better or worse?
 * Returns null on the first ever run (no previous snapshot) — never a fake zero.
 */
function trend(now, before) {
  if (!before) return null;
  const d = (k) => round2((Number(now[k]) || 0) - (Number(before[k]) || 0));
  const deltas = {
    stuckNegativeCount: d('stuckNegativeCount'),
    stuckNegativeValue: d('stuckNegativeValue'),
    stuckOver90Count: d('stuckOver90Count'),
    stuckOver90Value: d('stuckOver90Value'),
    over90Value: d('over90Value'),
    oldestBreachDays: d('oldestBreachDays'),
  };
  // "Improving" = fewer stuck items AND less 90-plus money. Both must hold, so a
  // drop in count achieved by letting balances grow is not reported as progress.
  const fewer = deltas.stuckNegativeCount + deltas.stuckOver90Count;
  const direction = (fewer < 0 && deltas.over90Value <= 0) ? 'improving'
    : (fewer > 0 || deltas.over90Value > 0) ? 'worsening'
      : 'flat';
  return { direction, deltas };
}

// ── 4. DeepSeek — root cause, not decoration ────────────────────────────────

// The fixed vocabulary. A free-text cause would be unusable in a report and
// impossible to test, so the model must choose from these.
const CAUSES = [
  'unallocated_receipt',   // money in, never matched to its invoice
  'unmatched_credit_note', // credit note raised, never applied
  'duplicate_posting',     // the same invoice or receipt captured twice
  'genuine_dispute',       // customer is withholding — a real collection matter
  'timing_difference',     // paid after the cut-off, matches next period
  'unknown',
];

/**
 * buildPrompt(findings) → { prompt, index }
 *
 * DPA-critical: the prompt carries an OPAQUE INDEX (1, 2, 3…) plus numbers and
 * counts ONLY. No account reference, no client name, no policy number, no dates
 * that could pin a person. `index` maps position → account, and stays in this
 * process. ai.js's PII guard is defence in depth behind this, not the control.
 */
function buildPrompt(breaches = []) {
  const index = breaches.map((f) => f.account);
  const rows = breaches.map((f, i) => ({
    i: i + 1,
    rule: f.rule,
    amount: round2(f.amount),
    daysStanding: f.days,
    openInvoices: f.openInvoiceCount,
    unallocatedReceipts: f.openPaymentCount,
  }));

  const prompt = [
    'You are auditing an insurance company\'s debtors (accounts receivable) ledger.',
    'Each row is one exception. Amounts are Pula; a NEGATIVE amount means the account is in credit (we are holding the customer\'s money).',
    '',
    'For each row decide the single most likely root cause and the concrete fix.',
    `Choose "cause" from exactly this list: ${CAUSES.join(', ')}.`,
    'Guidance: a negative with unallocatedReceipts > 0 is almost always unallocated_receipt.',
    'A negative with unallocatedReceipts = 0 is usually unmatched_credit_note or duplicate_posting.',
    'A stuck_over_90 with openInvoices > 0 and no receipts is usually genuine_dispute or a collection failure.',
    '',
    'Reply with JSON only — an array, one object per row, no prose, no code fence:',
    '[{"i":1,"cause":"unallocated_receipt","confidence":"high|medium|low","why":"one short sentence","fix":"one short imperative instruction"}]',
    '',
    'Rows:',
    JSON.stringify(rows),
  ].join('\n');

  return { prompt, index };
}

/**
 * explainFindings(audit, opts) → the audit with DeepSeek root causes attached.
 *
 * DEGRADES SAFELY AND SILENTLY. ai.ask returns null when live arms are off, when
 * no gateway is configured, on timeout, on a non-200, or if the PII guard trips.
 * In every one of those cases the audit is returned UNCHANGED with
 * aiExplained:false — the two rules are deterministic and stand on their own.
 * The AI makes the report smarter; it is never load-bearing.
 */
async function explainFindings(audit, opts = {}) {
  if (!audit || !Array.isArray(audit.findings) || audit.findings.length === 0) return audit;

  const { prompt, index } = buildPrompt(audit.findings);
  const parsed = await ai.askJSON(prompt, opts);
  if (!Array.isArray(parsed)) return audit; // unconfigured / blocked / bad JSON

  const byIndex = new Map();
  for (const row of parsed) {
    const i = Number(row && row.i);
    if (!Number.isInteger(i) || i < 1 || i > index.length) continue; // ignore junk
    byIndex.set(i, row);
  }
  if (byIndex.size === 0) return audit;

  let explained = 0;
  audit.findings = audit.findings.map((f, i) => {
    const row = byIndex.get(i + 1);
    if (!row) return f;
    const cause = CAUSES.includes(row.cause) ? row.cause : 'unknown';
    explained += 1;
    return {
      ...f,
      cause,
      confidence: ['high', 'medium', 'low'].includes(row.confidence) ? row.confidence : 'low',
      why: String(row.why || '').slice(0, 240),
      fix: String(row.fix || '').slice(0, 240),
    };
  });
  audit.aiExplained = explained > 0;
  audit.aiExplainedCount = explained;
  return audit;
}

// ── 5. Accountability wording ───────────────────────────────────────────────

const CAUSE_LABEL = {
  unallocated_receipt: 'Receipt never allocated',
  unmatched_credit_note: 'Credit note never applied',
  duplicate_posting: 'Duplicate posting',
  genuine_dispute: 'Customer dispute',
  timing_difference: 'Timing difference',
  unknown: 'Cause not established',
};

/**
 * accountabilityLine(finding) → the firm, pointed sentence naming the owner and
 * the exact number of days.
 *
 * TONE (CFO ruling 2026-08-04): FIRM AND POINTED, not abusive. The CFO asked for
 * language with teeth; this report is a permanent written record copied to
 * Internal Audit, so it states the failure and the elapsed days plainly and
 * attributes it by name. Insults in a standing audit record create an HR and
 * dignity exposure and weaken the finding — the days and the name do the work.
 */
function accountabilityLine(f) {
  const who = f.owner ? f.owner : 'No owner assigned to this account';
  const over = f.days - f.limit;
  if (f.rule === 'stuck_negative') {
    return `${who} — this account has been in credit for ${f.days} days, ${over} days beyond the ${f.limit}-day limit. `
      + `A credit balance is a posting error, not a resting state. It has not been cleared. Escalated to Internal Audit.`;
  }
  return `${who} — this balance has sat past 90 days for ${f.days} days, ${over} days beyond the ${f.limit}-day limit. `
    + `No collection movement has been recorded. Escalated to Internal Audit.`;
}

// ── 6. Render — branded HTML + CSV ──────────────────────────────────────────

const COLUMNS = [
  { key: 'account', label: 'Account', emphasis: true },
  { key: 'owner', label: 'Owner' },
  { key: 'causeLabel', label: 'Likely Cause' },
  { key: 'amount', label: 'Amount', kind: 'money' },
  { key: 'days', label: 'Days Standing', kind: 'num' },
  { key: 'since', label: 'Standing Since' },
];

function toRow(f) {
  return {
    account: f.account || '—',
    owner: f.owner || 'UNASSIGNED',
    causeLabel: f.cause ? CAUSE_LABEL[f.cause] || CAUSE_LABEL.unknown : '—',
    amount: f.amount,
    days: f.days,
    since: f.since || '—',
    _highlight: f.severity === 'critical' ? 'critical' : 'warn',
    _note: f.fix ? `Fix: ${f.fix}` : null,
  };
}

/**
 * renderHtml(audit) → the branded report. Uses the ONE shared shell in brand.js
 * (Alpha Navy #1D3270 / Direct Orange #F47C20, Book Antiqua headings) so this
 * report looks identical to every other Alpha Brain report.
 */
function renderHtml(audit) {
  const t = audit.totals;
  const negs = audit.findings.filter((f) => f.rule === 'stuck_negative');
  const o90 = audit.findings.filter((f) => f.rule === 'stuck_over_90');

  const trendLine = !audit.trend
    ? 'First run — no previous audit to compare against, so no trend is shown yet.'
    : audit.trend.direction === 'improving'
      ? `The book is IMPROVING since the last audit: ${Math.abs(audit.trend.deltas.stuckNegativeCount + audit.trend.deltas.stuckOver90Count)} fewer stuck items and ${brand.money(Math.abs(audit.trend.deltas.over90Value))} less sitting past 90 days.`
      : audit.trend.direction === 'worsening'
        ? `The book is WORSENING since the last audit: stuck items moved by ${audit.trend.deltas.stuckNegativeCount + audit.trend.deltas.stuckOver90Count} and 90-plus exposure by ${brand.money(audit.trend.deltas.over90Value)}.`
        : 'No material change since the last audit.';

  const breachSection = (heading, rule, list, explain) => brand.sectionHeading(heading)
    + brand.paragraph(explain)
    + brand.table({
      columns: COLUMNS,
      rows: list.map(toRow),
      empty: rule === 'stuck_negative'
        ? 'No negative has stood beyond the limit. Nothing to escalate.'
        : 'No 90-plus balance has stalled beyond the limit. Nothing to escalate.',
    })
    + (list.length
      ? `<div style="margin:12px 0 4px;font-family:${brand.FONT_BODY};font-size:12px;line-height:1.7;color:${brand.BRAND.text}">`
        + list.map((f) => `<div style="margin-bottom:7px">• ${brand.escHtml(accountabilityLine(f))}</div>`).join('')
        + `</div>`
      : '');

  const body = [
    brand.paragraph(
      'This is an automated audit of the debtors book. It reports two failures the CFO has ruled must never go unnoticed: '
      + '<strong>a negative balance that stands for more than a month</strong>, and '
      + '<strong>a balance past 90 days that stops moving</strong>. '
      + 'Every exception below is copied to Internal Audit.'
    ),
    brand.statRow([
      { label: 'Stuck Negatives', value: brand.num(t.stuckNegativeCount), sub: brand.money(t.stuckNegativeValue), accent: brand.BRAND.critical },
      { label: 'Stalled 90-Plus', value: brand.num(t.stuckOver90Count), sub: brand.money(t.stuckOver90Value), accent: brand.BRAND.warn },
      { label: 'Total 90-Plus', value: brand.money(t.over90Value), sub: `${brand.num(t.over90Count)} accounts`, accent: brand.BRAND.navy },
      { label: 'Oldest Breach', value: `${brand.num(t.oldestBreachDays)} days`, sub: `${brand.num(t.accounts)} accounts audited`, accent: brand.BRAND.orange },
    ]),
    brand.callout(audit.trend && audit.trend.direction === 'worsening' ? 'critical'
      : audit.trend && audit.trend.direction === 'improving' ? 'ok' : 'info', brand.escHtml(trendLine)),

    breachSection(
      `Rule 1 — Negatives standing over ${audit.thresholds.negativeMaxDays} days (${negs.length})`,
      'stuck_negative',
      negs,
      'A credit balance on a debtor account means we are holding money that has not been matched to an invoice. '
      + 'It is an unallocated receipt, an unapplied credit note or a duplicate posting — never a normal position.'
    ),

    breachSection(
      `Rule 2 — Past 90 days and not moving for over ${audit.thresholds.over90MaxDays} days (${o90.length})`,
      'stuck_over_90',
      o90,
      'These balances crossed 90 days and have shown no collection movement since. They are the ageing that does not come down.'
    ),

    t.unassignedOwnerCount > 0
      ? brand.callout('warn',
        `<strong>${brand.num(t.unassignedOwnerCount)} exception(s) have no owner.</strong> `
        + 'Accountability cannot be attributed until each debtor account has a named collector. '
        + 'Until then these read as UNASSIGNED and the whole team carries them.')
      : '',

    audit.aiExplained
      ? brand.callout('info', 'Likely causes and fixes were assessed by the in-house AI reviewer (DeepSeek, over VPN). '
        + 'Amounts and counts only were shared — no client name, account reference or policy number left this system. '
        + 'Treat the cause as a lead to verify, not a conclusion.')
      : brand.callout('info', 'Cause analysis did not run this cycle, so the "Likely Cause" column is blank. '
        + 'The two rules above are calculated and stand on their own.'),
  ].join('');

  return brand.reportShell({
    title: 'AI Internal Auditor — Debtors',
    eyebrow: 'Alpha Brain · Internal Audit',
    generatedAt: audit.asOf,
    bodyHtml: body,
    footerNote: 'Automated audit. Escalations are copied to Internal Audit and retained as a permanent record. '
      + 'Thresholds are configurable by Finance; this report does not post journals or change any record.',
  });
}

function csvCell(v) {
  const s = v == null ? '' : String(v);
  return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
}

/** renderCsv(audit) → both rule sections, one CSV. */
function renderCsv(audit) {
  const header = ['Rule', 'Account', 'Owner', 'Likely Cause', 'Amount', 'Days Standing', 'Standing Since', 'Severity', 'Fix'];
  const lines = [
    csvCell(`AI Internal Auditor — Debtors,as at ${audit.asOf || ''}`),
    '',
    header.map(csvCell).join(','),
  ];
  if (audit.findings.length === 0) {
    lines.push(csvCell('(no exceptions)'));
  } else {
    for (const f of audit.findings) {
      lines.push([
        f.rule, f.account, f.owner || 'UNASSIGNED',
        f.cause ? CAUSE_LABEL[f.cause] || CAUSE_LABEL.unknown : '',
        round2(f.amount), f.days, f.since || '', f.severity, f.fix || '',
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
    id: `debtors-audit:${f.rule}:${f.account}`,
    domain: 'debtors_audit',
    team: 'Finance',
    // Above the plain overdue-debtor items (30–60) — these are breaches of a
    // standing CFO rule with an Internal Audit copy, not routine ageing.
    priority: f.severity === 'critical' ? 78 : 65,
    title: f.rule === 'stuck_negative'
      ? `Negative standing ${f.days} days (limit ${f.limit}) — escalated to Internal Audit`
      : `90-plus stalled ${f.days} days (limit ${f.limit}) — escalated to Internal Audit`,
    detail: accountabilityLine(f)
      + (f.cause ? ` Likely cause: ${CAUSE_LABEL[f.cause] || CAUSE_LABEL.unknown}.` : '')
      + (f.fix ? ` Fix: ${f.fix}` : ''),
    ref: f.account,
    status: 'open',
    needsApproval: false,
    audit: f,
  }));
}

// Recipients live in settings, never in code — same pattern as fridayReport and
// highArrearsAlert. Empty until Operations sets them, and the send then no-ops.
const RECIPIENT_ENV = {
  finance: 'BRAIN_DEBTORS_AUDIT_FINANCE_RECIPIENTS',
  internalAudit: 'BRAIN_DEBTORS_AUDIT_IA_RECIPIENTS',
  cfo: 'BRAIN_DEBTORS_AUDIT_CFO_RECIPIENTS',
};

function recipients(env = process.env) {
  const split = (v) => String(v || '').split(',').map((s) => s.trim()).filter(Boolean);
  const out = {};
  for (const [k, key] of Object.entries(RECIPIENT_ENV)) out[k] = split(env[key]);
  out.all = [...new Set([].concat(out.finance, out.internalAudit, out.cfo))];
  return out;
}

module.exports = {
  runAudit, assess, findings, trend,
  explainFindings, buildPrompt,
  renderHtml, renderCsv, accountabilityLine, exceptions,
  recipients, toRow,
  NEGATIVE_MAX_DAYS, OVER90_MAX_DAYS, MIN_AMOUNT, CAUSES, CAUSE_LABEL, COLUMNS, RECIPIENT_ENV,
};
