'use strict';
// Unit test for lib/debtorsAudit.js — run: node test/debtorsAudit.test.js
//
// Proves the two CFO rules (2026-08-04), the evidence-derived clock, the
// no-size-floor instruction, the DPA guarantee on the DeepSeek prompt, the safe
// degradation when the AI is unavailable, and the active-book guard on the
// debtors feed.

const assert = require('node:assert');
const fs = require('node:fs');
const path = require('node:path');
const da = require('../lib/debtorsAudit');
const ageing = require('../lib/ageing');

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };
const acheck = (n, fn) => fn().then(() => { pass++; console.log('  ok  ' + n); })
  .catch((e) => { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); });

// ── ageing.js additive change ───────────────────────────────────────────────
check('ageing: openPayments exposes the unallocated receipt and its date', () => {
  const r = ageing.age(
    [{ date: '2026-03-01', amount: 200 }],
    [{ date: '2026-03-10', amount: 500 }],
    '2026-04-01'
  );
  assert.equal(r.unappliedCredit, 300);
  assert.equal(r.openPayments.length, 1);
  assert.equal(r.openPayments[0].date, '2026-03-10');
  assert.equal(r.openPayments[0].left, 300);
});

check('ageing: a fully allocated payment leaves openPayments empty', () => {
  const r = ageing.age([{ date: '2026-01-01', amount: 500 }], [{ date: '2026-03-30', amount: 500 }], '2026-04-01');
  assert.deepEqual(r.openPayments, []);
});

// ── THE CLOCK IS DERIVED FROM THE LEDGER, NOT FROM FIRST SIGHT ──────────────
// This is the defect that hit four other modules (audit 2026-07-21): a watchdog
// that dates a breach from "the first day we looked" reports nothing for 30 days
// and silently resets whenever the container is replaced.
check('negative is dated from the unallocated RECEIPT, so run one already breaches', () => {
  // Receipt landed 1 Mar; we are looking on 1 Jun. That negative is 92 days old
  // on the FIRST run — there is no snapshot history at all.
  const p = da.assess({
    account: 'ACC-1',
    invoices: [{ date: '2026-02-01', amount: 100 }],
    payments: [{ date: '2026-03-01', amount: 400 }],
  }, '2026-06-01');
  assert.equal(p.isNegative, true);
  assert.equal(p.negativeSince, '2026-03-01');
  assert.equal(p.daysNegative, 92);

  const f = da.findings([p], {});
  assert.equal(f.length, 1);
  assert.equal(f[0].rule, 'stuck_negative');
  assert.equal(f[0].days, 92);
});

check('90-plus is dated from invoice date + 91 days, not from first sight', () => {
  // Invoice 1 Jan 2026 → crosses 90 days on 2 Apr 2026. As at 1 Jun that is 60
  // days stuck in the 90-plus band.
  const p = da.assess({
    account: 'ACC-2',
    invoices: [{ date: '2026-01-01', amount: 5000 }],
    payments: [],
  }, '2026-06-01');
  assert.equal(p.over90, 5000);
  assert.equal(p.over90Since, '2026-04-02');
  assert.equal(p.daysOver90, 60);
});

// ── RULE 1 — standing negative ─────────────────────────────────────────────
check('Rule 1: a negative INSIDE the month does NOT breach', () => {
  const p = da.assess({
    account: 'ACC-3', invoices: [], payments: [{ date: '2026-05-20', amount: 900 }],
  }, '2026-06-01'); // 12 days
  assert.equal(p.isNegative, true);
  assert.equal(da.findings([p], {}).length, 0);
});

check('Rule 1: exactly 30 days does not breach; 31 does', () => {
  const at30 = da.assess({ account: 'A', invoices: [], payments: [{ date: '2026-05-02', amount: 100 }] }, '2026-06-01');
  assert.equal(at30.daysNegative, 30);
  assert.equal(da.findings([at30], {}).length, 0);

  const at31 = da.assess({ account: 'A', invoices: [], payments: [{ date: '2026-05-01', amount: 100 }] }, '2026-06-01');
  assert.equal(at31.daysNegative, 31);
  assert.equal(da.findings([at31], {}).length, 1);
});

check('Rule 1: a cleared account produces nothing (the CFO ageing fix still holds)', () => {
  const p = da.assess({
    account: 'CLEAR',
    invoices: [{ date: '2026-01-01', amount: 500 }],
    payments: [{ date: '2026-03-30', amount: 500 }],
  }, '2026-04-01');
  assert.equal(p.balance, 0);
  assert.equal(p.isNegative, false);
  assert.equal(p.over90, 0);
  assert.equal(da.findings([p], {}).length, 0);
});

// ── RULE 2 — stalled 90-plus ───────────────────────────────────────────────
check('Rule 2: past 90 but only recently does NOT breach', () => {
  // Invoice 1 Mar → crosses 90 on 31 May. As at 5 Jun = 5 days. No breach.
  const p = da.assess({ account: 'B', invoices: [{ date: '2026-03-01', amount: 700 }], payments: [] }, '2026-06-05');
  assert.ok(p.over90 > 0, 'should be in the 90-plus band');
  assert.equal(da.findings([p], {}).length, 0);
});

check('Rule 2: stalled well past the limit escalates as critical', () => {
  // Crosses 90 on 2 Apr 2025; as at 1 Jun 2026 that is >3x the 30-day limit.
  const p = da.assess({ account: 'C', invoices: [{ date: '2025-01-01', amount: 12000 }], payments: [] }, '2026-06-01');
  const f = da.findings([p], {});
  assert.equal(f.length, 1);
  assert.equal(f[0].rule, 'stuck_over_90');
  assert.equal(f[0].severity, 'critical');
  assert.equal(f[0].escalateTo, 'internal_audit');
});

// INVARIANT, proven the hard way: the two rules are MUTUALLY EXCLUSIVE on a
// single account, and it is not a coincidence. ageing.js allocates leftover
// credit FIFO across every open invoice, so credit can only remain unapplied
// once every invoice is closed. Unapplied credit therefore implies no open
// invoice, which implies nothing in the 90-plus band.
//
// Consequence worth stating for whoever reads the report: an account is either
// holding our customer's money (Rule 1) or owing us aged money (Rule 2) — never
// both. A book that appears to show both on one account means the allocation did
// not run, which is the original Graphite defect ageing.js exists to fix.
check('the two rules are mutually exclusive on one account (FIFO exhausts credit)', () => {
  const cases = [
    // credit ref-matched to a newer invoice, an older invoice left open
    { invoices: [{ date: '2026-01-01', amount: 1000, ref: 'A' }, { date: '2026-05-25', amount: 5000, ref: 'B' }],
      payments: [{ date: '2026-01-10', amount: 6000, ref: 'B' }] },
    // big unmatched receipt against one old invoice
    { invoices: [{ date: '2026-01-01', amount: 1000, ref: 'INV-1' }],
      payments: [{ date: '2026-01-05', amount: 4000, ref: 'OTHER' }] },
    // pure credit, no invoices at all
    { invoices: [], payments: [{ date: '2026-01-05', amount: 4000 }] },
  ];
  for (const c of cases) {
    const p = da.assess({ account: 'X', ...c }, '2026-06-01');
    assert.ok(!(p.isNegative && p.over90 > 0),
      'an account showed unapplied credit AND an open 90-plus balance — allocation did not run');
    const rules = da.findings([p], {}).map((x) => x.rule);
    assert.ok(rules.length <= 1, 'one account produced both rule breaches: ' + rules.join(' + '));
  }
});

check('a stalled 90-plus and a standing negative both appear across DIFFERENT accounts', () => {
  const f = da.findings([
    da.assess({ account: 'NEG', invoices: [], payments: [{ date: '2026-01-05', amount: 4000 }] }, '2026-06-01'),
    da.assess({ account: 'OLD', invoices: [{ date: '2026-01-01', amount: 1000 }], payments: [] }, '2026-06-01'),
  ], {});
  assert.deepEqual(f.map((x) => x.rule).sort(), ['stuck_negative', 'stuck_over_90']);
});

// ── NO SIZE FLOOR (CFO: flag everything) ───────────────────────────────────
check('no size floor: a 2-thebe standing credit is still reported', () => {
  const p = da.assess({ account: 'TINY', invoices: [], payments: [{ date: '2026-01-01', amount: 0.02 }] }, '2026-06-01');
  const f = da.findings([p], {});
  assert.equal(f.length, 1, 'a tiny credit must NOT be silently dropped');
  assert.equal(da.MIN_AMOUNT, 0);
});

check('float noise is not mistaken for a credit', () => {
  const p = da.assess({
    account: 'NOISE',
    invoices: [{ date: '2026-01-01', amount: 100 }],
    payments: [{ date: '2026-01-02', amount: 100.001 }],
  }, '2026-06-01');
  assert.equal(p.isNegative, false, '0.001 is rounding, not a credit');
  assert.equal(da.findings([p], {}).length, 0);
});

// ── The audit object, totals and trend ─────────────────────────────────────
const ACCOUNTS = [
  { account: 'ACC-NEG', owner: 'K. Mokhendo', invoices: [], payments: [{ date: '2026-01-10', amount: 5000 }] },
  { account: 'ACC-90', owner: 'B. Makosha', invoices: [{ date: '2026-01-01', amount: 20000 }], payments: [] },
  { account: 'ACC-OK', owner: 'K. Mokhendo', invoices: [{ date: '2026-05-25', amount: 300 }], payments: [] },
  { account: 'ACC-NOOWNER', invoices: [], payments: [{ date: '2026-02-01', amount: 750 }] },
];

check('runAudit: totals are internally consistent and parts tie to the whole', () => {
  const a = da.runAudit({ accounts: ACCOUNTS, asOf: '2026-06-01' });
  assert.equal(a.totals.accounts, 4);
  assert.equal(a.totals.negativeCount, 2);              // ACC-NEG + ACC-NOOWNER
  assert.equal(a.totals.stuckNegativeCount, 2);
  assert.equal(a.totals.stuckOver90Count, 1);           // ACC-90
  assert.equal(a.findings.length, 3);
  assert.equal(a.totals.unassignedOwnerCount, 1);       // ACC-NOOWNER
  // stuck values must equal the sum of their own findings — no drift
  const negSum = a.findings.filter((f) => f.rule === 'stuck_negative').reduce((s, f) => s + f.amount, 0);
  assert.equal(Math.round(a.totals.stuckNegativeValue * 100), Math.round(negSum * 100));
});

check('runAudit: findings are ordered worst-first (critical before warn)', () => {
  const a = da.runAudit({ accounts: ACCOUNTS, asOf: '2026-06-01' });
  const sev = a.findings.map((f) => f.severity);
  const firstWarn = sev.indexOf('warn');
  const lastCrit = sev.lastIndexOf('critical');
  if (firstWarn !== -1 && lastCrit !== -1) assert.ok(lastCrit < firstWarn, 'a warn appeared before a critical');
});

check('trend: the FIRST run reports null, never a fake zero', () => {
  const a = da.runAudit({ accounts: ACCOUNTS, asOf: '2026-06-01' });
  assert.equal(a.trend, null);
});

check('trend: fewer stuck items AND less 90-plus money = improving', () => {
  const before = { totals: { stuckNegativeCount: 5, stuckNegativeValue: -9000, stuckOver90Count: 4, stuckOver90Value: 80000, over90Value: 80000, oldestBreachDays: 300 } };
  const a = da.runAudit({ accounts: ACCOUNTS, asOf: '2026-06-01', previous: before });
  assert.equal(a.trend.direction, 'improving');
});

check('trend: a count drop achieved while 90-plus money GREW is NOT improving', () => {
  // The gaming case: clear two small items, let the big balances swell.
  const before = { totals: { stuckNegativeCount: 3, stuckNegativeValue: -100, stuckOver90Count: 3, stuckOver90Value: 100, over90Value: 100, oldestBreachDays: 10 } };
  const a = da.runAudit({ accounts: ACCOUNTS, asOf: '2026-06-01', previous: before });
  assert.notEqual(a.trend.direction, 'improving');
});

check('thresholds are configurable and actually applied', () => {
  const a = da.runAudit({ accounts: ACCOUNTS, asOf: '2026-06-01', thresholds: { negativeMaxDays: 3650, over90MaxDays: 3650 } });
  assert.equal(a.findings.length, 0, 'a 10-year limit should silence everything');
  assert.equal(a.thresholds.negativeMaxDays, 3650);
});

// ── DPA: what leaves the building ──────────────────────────────────────────
check('DPA: the DeepSeek prompt carries NO account ref, name or policy number', () => {
  const a = da.runAudit({
    accounts: [{
      account: 'MIS2026215205', owner: 'K. Mokhendo', customerName: 'Boitumelo Serema',
      invoices: [], payments: [{ date: '2026-01-10', amount: 5000 }],
    }],
    asOf: '2026-06-01',
  });
  const { prompt, index } = da.buildPrompt(a.findings);
  assert.ok(!prompt.includes('MIS2026215205'), 'policy/account reference leaked into the prompt');
  assert.ok(!prompt.includes('Boitumelo'), 'client name leaked into the prompt');
  assert.ok(!prompt.includes('Serema'), 'client surname leaked into the prompt');
  assert.ok(!prompt.includes('Mokhendo'), 'staff name leaked into the prompt');
  assert.ok(prompt.includes('5000'), 'the amount should be shared — it is not PII');
  // the mapping back to the account stays in-process
  assert.deepEqual(index, ['MIS2026215205']);
});

check('DPA: the prompt survives the shared AI client\'s own PII guard', () => {
  const ai = require('../lib/ai');
  const a = da.runAudit({ accounts: ACCOUNTS, asOf: '2026-06-01' });
  const { prompt } = da.buildPrompt(a.findings);
  assert.equal(ai.containsLikelyPII(prompt), false,
    'the prompt trips ai.js PII guard — the call would silently return null');
});

// ── AI degradation: the rules must never depend on the model ───────────────
acheck('AI: unconfigured gateway returns the audit UNCHANGED, aiExplained false', async () => {
  const savedUrl = process.env.AI_GATEWAY_URL; const savedArms = process.env.BRAIN_LIVE_ARMS;
  delete process.env.AI_GATEWAY_URL; process.env.BRAIN_LIVE_ARMS = 'false';
  try {
    const a = da.runAudit({ accounts: ACCOUNTS, asOf: '2026-06-01' });
    const before = a.findings.length;
    const out = await da.explainFindings(a);
    assert.equal(out.aiExplained, false);
    assert.equal(out.findings.length, before, 'findings must survive an absent AI');
    assert.equal(out.findings[0].cause, undefined);
  } finally {
    if (savedUrl === undefined) delete process.env.AI_GATEWAY_URL; else process.env.AI_GATEWAY_URL = savedUrl;
    if (savedArms === undefined) delete process.env.BRAIN_LIVE_ARMS; else process.env.BRAIN_LIVE_ARMS = savedArms;
  }
});

acheck('AI: an out-of-vocabulary cause is coerced to "unknown", never echoed raw', async () => {
  const a = da.runAudit({ accounts: ACCOUNTS, asOf: '2026-06-01' });
  const ai = require('../lib/ai');
  const real = ai.askJSON;
  ai.askJSON = async () => ([{ i: 1, cause: 'the customer is a scoundrel', confidence: 'certain', why: 'w', fix: 'f' }]);
  try {
    const out = await da.explainFindings(a);
    assert.equal(out.aiExplained, true);
    assert.equal(out.findings[0].cause, 'unknown');
    assert.equal(out.findings[0].confidence, 'low', 'an invalid confidence must fall to low');
  } finally { ai.askJSON = real; }
});

acheck('AI: a junk index is ignored rather than mis-attributed to the wrong account', async () => {
  const a = da.runAudit({ accounts: ACCOUNTS, asOf: '2026-06-01' });
  const ai = require('../lib/ai');
  const real = ai.askJSON;
  // index 99 does not exist; must not land on anybody.
  ai.askJSON = async () => ([{ i: 99, cause: 'duplicate_posting', confidence: 'high', why: 'w', fix: 'f' }]);
  try {
    const out = await da.explainFindings(a);
    assert.equal(out.aiExplained, false);
    for (const f of out.findings) assert.equal(f.cause, undefined);
  } finally { ai.askJSON = real; }
});

acheck('AI: a cause is attached to the RIGHT account', async () => {
  const a = da.runAudit({ accounts: ACCOUNTS, asOf: '2026-06-01' });
  const target = a.findings[0].account;
  const ai = require('../lib/ai');
  const real = ai.askJSON;
  ai.askJSON = async () => ([{ i: 1, cause: 'unallocated_receipt', confidence: 'high', why: 'why', fix: 'Allocate the receipt.' }]);
  try {
    const out = await da.explainFindings(a);
    const hit = out.findings.find((f) => f.cause === 'unallocated_receipt');
    assert.equal(hit.account, target);
    assert.equal(hit.fix, 'Allocate the receipt.');
  } finally { ai.askJSON = real; }
});

// ── Accountability wording ─────────────────────────────────────────────────
check('the alert names the owner and the exact days, and says it is escalated', () => {
  const a = da.runAudit({ accounts: ACCOUNTS, asOf: '2026-06-01' });
  const f = a.findings.find((x) => x.owner);
  const line = da.accountabilityLine(f);
  assert.ok(line.includes(f.owner), 'the owner must be named');
  assert.ok(line.includes(String(f.days)), 'the elapsed days must be stated');
  assert.ok(/Internal Audit/i.test(line), 'the escalation must be stated');
});

check('an unassigned account says so instead of blaming the whole team by name', () => {
  const f = { rule: 'stuck_negative', owner: null, days: 60, limit: 30 };
  const line = da.accountabilityLine(f);
  assert.ok(/No owner assigned/i.test(line));
});

check('the wording is firm but carries no abuse (permanent Internal Audit record)', () => {
  const a = da.runAudit({ accounts: ACCOUNTS, asOf: '2026-06-01' });
  const all = a.findings.map(da.accountabilityLine).join(' ').toLowerCase();
  for (const banned of ['useless', 'incompetent', 'stupid', 'lazy', 'pathetic', 'disgrace', 'idiot']) {
    assert.ok(!all.includes(banned), `abusive word "${banned}" must not enter a permanent audit record`);
  }
});

// ── Render ─────────────────────────────────────────────────────────────────
check('renderHtml uses the canonical brand and Book Antiqua, and escapes input', () => {
  const a = da.runAudit({
    accounts: [{ account: '<script>x</script>', owner: 'O"wner', invoices: [], payments: [{ date: '2026-01-01', amount: 500 }] }],
    asOf: '2026-06-01',
  });
  const html = da.renderHtml(a);
  assert.ok(html.includes('#1D3270'), 'Alpha Navy missing');
  assert.ok(html.includes('#F47C20'), 'Direct Orange missing');
  assert.ok(html.includes('Book Antiqua'), 'Book Antiqua ("Antica") missing');
  // the retired palettes must not come back
  for (const dead of ['#010066', '#FE7F0C', '#0B1272', '#FF6600']) {
    assert.ok(!html.includes(dead), `retired brand colour ${dead} reappeared`);
  }
  assert.ok(!html.includes('<script>x</script>'), 'unescaped account reference — XSS in the report');
});

check('renderHtml states plainly when cause analysis did not run', () => {
  const a = da.runAudit({ accounts: ACCOUNTS, asOf: '2026-06-01' });
  const html = da.renderHtml(a);
  assert.ok(/did not run/i.test(html), 'a blank Likely Cause column must be explained, not left mysterious');
});

check('renderHtml on an empty book says nothing to escalate — not a broken page', () => {
  const a = da.runAudit({ accounts: [], asOf: '2026-06-01' });
  const html = da.renderHtml(a);
  assert.ok(/Nothing to escalate/i.test(html));
  assert.equal(a.findings.length, 0);
});

check('renderCsv emits one row per finding plus a header', () => {
  const a = da.runAudit({ accounts: ACCOUNTS, asOf: '2026-06-01' });
  const csv = da.renderCsv(a);
  const lines = csv.split('\r\n').filter(Boolean);
  assert.ok(csv.includes('Days Standing'));
  // title + header + 3 findings
  assert.equal(lines.length, 2 + a.findings.length);
});

// ── Sweep wiring ───────────────────────────────────────────────────────────
check('exceptions(): stable ids, Finance team, and NOT approval-gated', () => {
  const a = da.runAudit({ accounts: ACCOUNTS, asOf: '2026-06-01' });
  const ex = da.exceptions(a);
  assert.equal(ex.length, a.findings.length);
  for (const x of ex) {
    assert.equal(x.team, 'Finance');
    assert.equal(x.domain, 'debtors_audit');
    assert.equal(x.needsApproval, false, 'an audit finding is work to do, not an action to approve');
    assert.ok(x.id.startsWith('debtors-audit:'));
  }
  // ids must be stable across identical runs so recorded approvals survive a sweep
  const again = da.exceptions(da.runAudit({ accounts: ACCOUNTS, asOf: '2026-06-01' }));
  assert.deepEqual(ex.map((x) => x.id), again.map((x) => x.id));
});

check('recipients come from settings, never hardcoded addresses', () => {
  const r = da.recipients({});
  assert.deepEqual(r.all, [], 'with nothing configured the send must have no recipients');
  const r2 = da.recipients({
    BRAIN_DEBTORS_AUDIT_FINANCE_RECIPIENTS: 'a@alphadirect.co.bw, b@alphadirect.co.bw',
    BRAIN_DEBTORS_AUDIT_IA_RECIPIENTS: 'ia@alphadirect.co.bw',
  });
  assert.equal(r2.finance.length, 2);
  assert.deepEqual(r2.internalAudit, ['ia@alphadirect.co.bw']);
  assert.equal(r2.all.length, 3);
  // no address may be baked into the module source
  const src = fs.readFileSync(path.join(__dirname, '..', 'lib', 'debtorsAudit.js'), 'utf8');
  assert.ok(!/@alphadirect\.co\.bw/.test(src), 'a real address is hardcoded in debtorsAudit.js');
});

// ── ACTIVE-BOOK GUARD on the debtors feed (CFO 2026-08-04) ─────────────────
check('debtors feed selects the ACTIVE book only — blocks the 51,610 regression', () => {
  const src = fs.readFileSync(path.join(__dirname, '..', 'lib', 'graphiteRo.js'), 'utf8');
  const m = src.match(/const DEBTOR_POLICY_SUBQUERY = `([\s\S]*?)`;/);
  assert.ok(m, 'DEBTOR_POLICY_SUBQUERY not found');
  const sql = m[1];
  assert.ok(/JOIN\s+policies\b/i.test(sql), 'the debtor selector must join policies to know what is active');
  assert.ok(/\.status\s*=\s*1/.test(sql), 'the debtor selector must filter to status = 1 (Active)');
  assert.ok(!/status\s+IN\s*\(\s*0\s*,\s*1\s*\)/i.test(sql),
    'status IN (0,1) is the defect PR #1785 fixed for the clock — never widen back');
});

// ── async wrap-up ──────────────────────────────────────────────────────────
setTimeout(() => {
  console.log('\n' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
}, 250);
