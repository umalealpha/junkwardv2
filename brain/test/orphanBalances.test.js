'use strict';
// Unit test for lib/orphanBalances.js — run: node test/orphanBalances.test.js
//
// Proves Rule 3 (a dead policy must not carry a balance), the evidence-derived
// clock, the no-size-floor instruction, the reallocatable/stranded split, and
// the four data traps this rule had to avoid: the invoice triple-count, the
// dead policies.balance column, name-matching, and drifting onto the active book.

const assert = require('node:assert');
const fs = require('node:fs');
const path = require('node:path');
const ob = require('../lib/orphanBalances');
const ro = require('../lib/graphiteRo');

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

const AS_OF = '2026-09-07';

// Money equality to the cent. Totals are each rounded independently, so adding
// two of them back together drifts in floating point — see the reconciliation
// test below for the real-data case that proved it.
const cents = (a, b) => Math.abs(a - b) < 0.005;

// A dead policy carrying a balance, dormant well past the limit.
const orphan = (over = {}) => Object.assign({
  policy_id: 101,
  policy_number: 'COMG2024121103',
  policy_status: 2,          // Cancelled
  customer_id: 5001,
  has_active_policy: 0,
  balance: 1150026.27,
  last_movement: '2026-01-15',
}, over);

// ── THE SELECTOR GUARDS ─────────────────────────────────────────────────────
// Rule 3 is the MIRROR of the active-book selector. Each must stay on its own
// side of the line: debtorsAudit on status 1, this on status 0 and 2. If either
// drifts, one population gets audited twice and the other not at all.

check('orphan selector targets the DEAD book only — status IN (0,2)', () => {
  const sql = ro.ORPHAN_POLICY_SQL;
  assert.ok(sql, 'ORPHAN_POLICY_SQL not exported');
  assert.ok(/status\s+IN\s*\(\s*0\s*,\s*2\s*\)/i.test(sql),
    'the orphan selector must filter to status IN (0,2) — Inactive and Cancelled');
  assert.ok(!/\bp\.status\s*=\s*1\b/.test(sql),
    'the orphan selector must never target the active book — that is debtorsAudit territory');
});

check('orphan selector still leaves the ACTIVE-book selector pinned to status = 1', () => {
  // Rule 3 was added ALONGSIDE the active-book selector, never by widening it.
  // Widening it is the 51,610 defect (PR #1791). This asserts the mirror change
  // did not disturb it.
  const sql = ro.DEBTOR_POLICY_SUBQUERY;
  assert.ok(/\.status\s*=\s*1/.test(sql), 'DEBTOR_POLICY_SUBQUERY must still filter to status = 1');
  assert.ok(!/status\s+IN\s*\(/i.test(sql), 'DEBTOR_POLICY_SUBQUERY must not have been widened to an IN list');
});

check('orphan selector counts each invoice ONCE — no premium/VAT triple-count', () => {
  const sql = ro.ORPHAN_POLICY_SQL;
  // Every invoice writes three lines: an 'Invoice' header plus 'Invoice Premium'
  // and 'Invoice VAT' components that sum to it. Including the components as
  // well as the header inflates the book by ~P450m.
  assert.ok(/'Invoice'/.test(sql), "must sum the 'Invoice' header line");
  assert.ok(!/'Invoice Premium'/.test(sql), "'Invoice Premium' is a component of the header — including it double counts");
  assert.ok(!/'Invoice VAT'/.test(sql), "'Invoice VAT' is a component of the header — including it double counts");
});

check('orphan selector never reads the dead policies.balance column', () => {
  const sql = ro.ORPHAN_POLICY_SQL;
  // policies.balance reads 0.00 on all 213,438 policies and is never populated.
  assert.ok(!/\bp\.balance\b/.test(sql), 'p.balance is always zero — the balance must be recomputed from the ledger');
  assert.ok(/SUM\s*\(/i.test(sql), 'the balance must be summed from the ledger lines');
});

check('orphan selector must not use Graphite\'s own ledger summary view', () => {
  const src = fs.readFileSync(path.join(__dirname, '..', 'lib', 'graphiteRo.js'), 'utf8');
  const m = src.match(/const ORPHAN_POLICY_SQL = `([\s\S]*?)`;/);
  assert.ok(m, 'ORPHAN_POLICY_SQL not found in source');
  // v_policy_ledger_summary sums every debit line indiscriminately, so it
  // carries the triple-count and cannot be used for a debtors figure.
  assert.ok(!/v_policy_ledger_summary/i.test(m[1]), 'that view triple-counts every invoice');
});

check('active-policy match is on customer_id, never on insured name', () => {
  const sql = ro.ORPHAN_POLICY_SQL;
  assert.ok(/act\.customer_id/.test(sql), 'the active-policy match must join on customer_id');
  // Name matching merges different people who share a name and misses the same
  // person spelled two ways.
  assert.ok(!/business_name|insured_name|customerName/i.test(sql),
    'never match a client by name — customer_id is the only safe key');
});

check('orphan selector excludes test and draft policies ON THE AUDITED POLICY', () => {
  const sql = ro.ORPHAN_POLICY_SQL;
  // Must assert the `p.`-prefixed filters specifically. A bare /is_test_policy = 0/
  // is satisfied by the active-policy subquery's own copy, so it stays green even
  // when the filter on the audited policy is removed — a mutation run proved that
  // exact false pass, which is why this test names the alias.
  assert.ok(/\bp\.is_test_policy\s*=\s*0/.test(sql), 'test policies must be excluded from the AUDITED set');
  assert.ok(/\bp\.is_draft\s*=\s*0/.test(sql), 'draft policies must be excluded from the AUDITED set');
  // and the active-policy match must not count a test/draft policy as "active"
  const inner = sql.slice(sql.indexOf('LEFT JOIN'));
  assert.ok(/is_test_policy\s*=\s*0/.test(inner) && /is_draft\s*=\s*0/.test(inner),
    'a test or draft policy must not make a client look like it still has active cover');
});

check('the balance filter is unambiguous — policy_ledger has its own balance column', () => {
  const sql = ro.ORPHAN_POLICY_SQL;
  // A bare `HAVING ABS(balance)` is ambiguous between the select alias and
  // policy_ledger.balance. MySQL happens to prefer the alias, so the query
  // returns the right rows and the bug stays invisible until someone
  // references the alias from an ORDER BY. Filter outside the grouped select
  // and the ambiguity cannot arise.
  assert.ok(/WHERE ABS\(\s*orph\.balance\s*\)/i.test(sql),
    'the balance filter must be qualified in an outer select, not a bare HAVING');
  assert.ok(!/HAVING\s+ABS\(\s*balance\s*\)/i.test(sql),
    'an unqualified HAVING on the alias is ambiguous against policy_ledger.balance');
});

check('orphan selector is as-at-today — no future-dated lines', () => {
  assert.ok(/accounting_date\s*<=\s*CURDATE\(\)/i.test(ro.ORPHAN_POLICY_SQL),
    'future-dated ledger lines must be excluded so the figure is a true as-at-today position');
});

check('orphan selector is SELECT-only and carries no client name (PII)', () => {
  const sql = ro.ORPHAN_POLICY_SQL;
  assert.match(sql, /select/i);
  assert.ok(!/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE)\b/i.test(sql), 'the audit must never write');
  assert.ok(!/customer_kyc|business_name|first_name|last_name|cellphone|omang/i.test(sql),
    'the orphan feed must carry no client name or personal detail');
});

// ── THE CLOCK IS DERIVED FROM THE LEDGER, NOT FROM FIRST SIGHT ──────────────
// The no-start-date defect found four times in the 2026-07-21 audit: a watchdog
// that records "first seen = today" reports nothing for its first window and
// resets whenever the container is replaced.

check('a long-dormant orphan is caught on run ONE, with no previous snapshot', () => {
  const audit = ob.runAudit({ rows: [orphan()], asOf: AS_OF, previous: null });
  assert.equal(audit.findings.length, 1, 'the finding must not wait for a second run');
  assert.equal(audit.findings[0].days, 235, 'age is measured from the last ledger movement');
  assert.equal(audit.trend, null, 'no previous audit means no trend, but the finding still stands');
});

check('the age comes from the ledger date, not from when the audit started', () => {
  const a = ob.assess(orphan({ last_movement: '2025-06-25' }), AS_OF);
  assert.equal(a.days, 439);
  assert.equal(a.since, '2025-06-25');
});

// ── RULE 3 ITSELF ───────────────────────────────────────────────────────────

check('a dead policy with a zero balance is clean', () => {
  assert.equal(ob.assess(orphan({ balance: 0 }), AS_OF), null);
});

check('float noise is not a balance', () => {
  assert.equal(ob.assess(orphan({ balance: 0.001 }), AS_OF), null);
  assert.equal(ob.assess(orphan({ balance: -0.004 }), AS_OF), null);
});

check('a policy cancelled recently is being worked, not yet a breach', () => {
  // Inside the 30-day window — Finance is presumed to be settling it.
  assert.equal(ob.assess(orphan({ last_movement: '2026-08-25' }), AS_OF), null);
});

check('past the window it becomes a breach', () => {
  const a = ob.assess(orphan({ last_movement: '2026-07-01' }), AS_OF);
  assert.ok(a, 'a balance dormant beyond the limit must be reported');
  assert.equal(a.rule, 'orphan_balance');
});

check('an ACTIVE policy is refused even if it reaches the rule', () => {
  // Belt and braces against selector drift: Rule 3 must never report on the
  // active book, which Rules 1 and 2 already audit.
  assert.equal(ob.assess(orphan({ policy_status: 1 }), AS_OF), null);
});

check('both dead statuses are in scope and labelled correctly', () => {
  assert.equal(ob.assess(orphan({ policy_status: 2 }), AS_OF).statusLabel, 'Cancelled');
  assert.equal(ob.assess(orphan({ policy_status: 0 }), AS_OF).statusLabel, 'Inactive');
});

// ── NO SIZE FLOOR (CFO ruling, carried over from Rules 1 and 2) ─────────────

check('there is NO size floor — a one-pula orphan is still reported', () => {
  assert.equal(ob.MIN_AMOUNT, 0, 'CFO ruled: flag everything');
  const a = ob.assess(orphan({ balance: 1 }), AS_OF);
  assert.ok(a, 'a small balance is still a defect');
});

check('the small balances are where the money actually is', () => {
  // 88,732 of the 89,597 orphans are under P5,000 and hold P54,200,878 between
  // them, which is 93% of the total. A floor would hide almost all of it.
  const rows = [];
  for (let i = 0; i < 500; i++) rows.push(orphan({ policy_id: i, policy_number: `P${i}`, balance: 100 }));
  const audit = ob.runAudit({ rows, asOf: AS_OF });
  assert.equal(audit.findings.length, 500);
  assert.equal(audit.totals.orphanValue, 50000);
});

// ── DIRECTION AND DISPOSITION ───────────────────────────────────────────────

check('a credit balance on a dead policy is CRITICAL — it is a refund liability', () => {
  const a = ob.assess(orphan({ balance: -5000 }), AS_OF);
  assert.equal(a.direction, 'credit');
  assert.equal(a.severity, 'critical', 'holding a client\'s money on closed cover outranks a receivable');
});

check('a reallocatable case is CRITICAL — it is provable and fixable today', () => {
  const a = ob.assess(orphan({ has_active_policy: 1, balance: 5000 }), AS_OF);
  assert.equal(a.disposition, 'reallocatable');
  assert.equal(a.severity, 'critical');
  assert.match(a.fix, /move it to the active policy/i);
});

check('a stranded receivable is WARN — it needs a decision, not a correction', () => {
  const a = ob.assess(orphan({ has_active_policy: 0, balance: 5000 }), AS_OF);
  assert.equal(a.disposition, 'stranded');
  assert.equal(a.severity, 'warn');
  assert.match(a.fix, /write it off/i);
});

check('a stranded credit tells the truth: refund it, there is nowhere to move it', () => {
  const a = ob.assess(orphan({ has_active_policy: 0, balance: -5000 }), AS_OF);
  assert.match(a.fix, /Refund the client/i);
  assert.ok(!/move it to the active policy/i.test(a.fix), 'there is no active policy to move it to');
});

// ── THE SPLIT THAT DECIDES WHAT CAN ACTUALLY BE FIXED ───────────────────────

check('the audit splits reallocatable from stranded, and they reconcile', () => {
  const rows = [
    orphan({ policy_id: 1, policy_number: 'A', has_active_policy: 1, balance: 1000 }),
    orphan({ policy_id: 2, policy_number: 'B', has_active_policy: 1, balance: -400 }),
    orphan({ policy_id: 3, policy_number: 'C', has_active_policy: 0, balance: 9000 }),
    orphan({ policy_id: 4, policy_number: 'D', has_active_policy: 0, balance: -600 }),
  ];
  const t = ob.runAudit({ rows, asOf: AS_OF }).totals;

  assert.equal(t.orphanCount, 4);
  assert.equal(t.reallocatableCount, 2);
  assert.equal(t.reallocatableValue, 600);
  assert.equal(t.strandedCount, 2);
  assert.equal(t.strandedValue, 8400);
  assert.equal(t.owingCount, 2);
  assert.equal(t.creditCount, 2);
  // The two dispositions must account for the whole book, with nothing lost.
  // Compared to the cent, not exactly: every total is independently rounded, so
  // adding two of them back together drifts in binary floating point. A run on
  // 60 real production rows produced 6064140.95 + -3975932.78 === 2088208.1700000003
  // against a stored 2088208.17. The invariant is "nothing is lost", which is a
  // cent-level claim, so assert it at that precision.
  assert.ok(cents(t.reallocatableValue + t.strandedValue, t.orphanValue),
    'the reallocatable/stranded split must account for the whole book');
  assert.ok(cents(t.owingValue + t.creditValue, t.orphanValue),
    'the owing/credit split must account for the whole book');
});

// ── EXPOSURE vs NET ─────────────────────────────────────────────────────────
// A credit on one client does not settle a debit on another. Netting them and
// calling the result "how much reallocation reaches" understates the job, and
// when credits outweigh debits it goes negative and the sentence breaks. A run
// on 60 real production rows rendered "reaches -BWP 611,413.69" before this.

check('exposure is the gross size of the job, net is what the book is worth', () => {
  const rows = [
    orphan({ policy_id: 1, policy_number: 'A', has_active_policy: 1, balance: 1000 }),
    orphan({ policy_id: 2, policy_number: 'B', has_active_policy: 1, balance: -1600 }),
  ];
  const t = ob.runAudit({ rows, asOf: AS_OF }).totals;
  assert.equal(t.reallocatableValue, -600, 'net offsets the two balances');
  assert.equal(t.reallocatableExposure, 2600, 'exposure adds them — two separate corrections');
  assert.equal(t.totalExposure, 2600);
});

check('exposure is never negative, even when credits dominate', () => {
  const rows = [
    orphan({ policy_id: 1, policy_number: 'A', has_active_policy: 1, balance: -5000 }),
    orphan({ policy_id: 2, policy_number: 'B', has_active_policy: 0, balance: -9000 }),
  ];
  const t = ob.runAudit({ rows, asOf: AS_OF }).totals;
  assert.ok(t.reallocatableExposure > 0, 'exposure must stay positive');
  assert.ok(t.strandedExposure > 0, 'exposure must stay positive');
  assert.ok(t.totalExposure > 0, 'exposure must stay positive');
  assert.ok(t.orphanValue < 0, 'while the net correctly stays negative');
});

check('the reach sentence never prints a negative amount', () => {
  // The bug this replaces: "It reaches -BWP 611,413.69 of the BWP 2,088,208.17".
  const rows = [
    orphan({ policy_id: 1, policy_number: 'A', has_active_policy: 1, balance: -900000 }),
    orphan({ policy_id: 2, policy_number: 'B', has_active_policy: 0, balance: 500000 }),
  ];
  const html = ob.renderHtml(ob.runAudit({ rows, asOf: AS_OF }));
  const m = html.match(/It reaches[^<]*/);
  assert.ok(m, 'the reach sentence must be present');
  assert.ok(!/-\s*BWP/.test(m[0]), `a negative reach is meaningless: "${m[0]}"`);
});

check('exposure totals reconcile across the two dispositions', () => {
  const rows = [
    orphan({ policy_id: 1, policy_number: 'A', has_active_policy: 1, balance: 1000 }),
    orphan({ policy_id: 2, policy_number: 'B', has_active_policy: 0, balance: -400 }),
    orphan({ policy_id: 3, policy_number: 'C', has_active_policy: 0, balance: 250 }),
  ];
  const t = ob.runAudit({ rows, asOf: AS_OF }).totals;
  assert.ok(cents(t.reallocatableExposure + t.strandedExposure, t.totalExposure),
    'exposure must split cleanly across the two groups');
});

check('the report explains the difference, so the two are not confused', () => {
  const html = ob.renderHtml(ob.runAudit({ rows: [orphan()], asOf: AS_OF }));
  assert.match(html, /In play/i);
  assert.match(html, /Nets to/i);
  assert.match(html, /does not settle a debit on another/i);
});

// ── ESCALATION ──────────────────────────────────────────────────────────────

check('dormant past the escalation window goes to the CFO', () => {
  assert.equal(ob.ESCALATE_DAYS, 60, 'two reporting cycles');
  assert.equal(ob.assess(orphan({ last_movement: '2026-08-01' }), AS_OF).escalateToCfo, false);
  assert.equal(ob.assess(orphan({ last_movement: '2026-05-01' }), AS_OF).escalateToCfo, true);
});

// ── AGEING ──────────────────────────────────────────────────────────────────

check('ageing buckets by dormancy, and every finding lands in exactly one', () => {
  const rows = [
    orphan({ policy_id: 1, policy_number: 'A', last_movement: '2026-08-01', balance: 100 }), // 37d
    orphan({ policy_id: 2, policy_number: 'B', last_movement: '2026-07-01', balance: 200 }), // 68d
    orphan({ policy_id: 3, policy_number: 'C', last_movement: '2026-01-01', balance: 300 }), // 249d
    orphan({ policy_id: 4, policy_number: 'D', last_movement: '2024-01-01', balance: 400 }), // >1y
  ];
  const audit = ob.runAudit({ rows, asOf: AS_OF });
  const by = Object.fromEntries(audit.ageing.map((b) => [b.key, b]));
  assert.equal(by['31-60'].count, 1);
  assert.equal(by['61-90'].count, 1);
  assert.equal(by['181-365'].count, 1);
  assert.equal(by['365+'].count, 1);
  const totalCount = audit.ageing.reduce((n, b) => n + b.count, 0);
  assert.equal(totalCount, audit.findings.length, 'no finding may be lost or counted twice');
});

// ── TREND (narrative only — never used to age a finding) ────────────────────

check('trend reads improving when the orphan book comes down', () => {
  const before = { totals: { orphanCount: 10, orphanValue: 5000, strandedValue: 4000 } };
  const now = ob.runAudit({ rows: [orphan({ balance: 100 })], asOf: AS_OF, previous: before });
  assert.equal(now.trend.direction, 'improving');
});

check('trend reads worsening when it grows', () => {
  const before = { totals: { orphanCount: 0, orphanValue: 0, strandedValue: 0 } };
  const now = ob.runAudit({ rows: [orphan({ balance: 100 })], asOf: AS_OF, previous: before });
  assert.equal(now.trend.direction, 'worsening');
});

// ── RENDER ──────────────────────────────────────────────────────────────────

check('the report renders branded HTML with the live figures', () => {
  const audit = ob.runAudit({ rows: [orphan(), orphan({ policy_id: 2, policy_number: 'B', has_active_policy: 1, balance: -900 })], asOf: AS_OF });
  const html = ob.renderHtml(audit);
  assert.match(html, /Orphan Balances/);
  assert.match(html, /#1D3270/i, 'Alpha Navy must be present');
  assert.match(html, /COMG2024121103/, 'the policy number must appear');
  assert.match(html, /Rule 3/);
});

check('the report is honest on the first run — it says there is no trend yet', () => {
  const html = ob.renderHtml(ob.runAudit({ rows: [orphan()], asOf: AS_OF }));
  assert.match(html, /First run/i);
  // and it must still explain the ages are valid, because the clock is derived
  assert.match(html, /last movement in the ledger/i);
});

check('an empty book renders cleanly and says so', () => {
  const html = ob.renderHtml(ob.runAudit({ rows: [], asOf: AS_OF }));
  assert.match(html, /No cancelled or inactive policy is carrying a balance/i);
});

check('the report states the balance is recomputed, not read from the policy', () => {
  const html = ob.renderHtml(ob.runAudit({ rows: [orphan()], asOf: AS_OF }));
  assert.match(html, /reads zero on every policy/i);
});

check('the report warns that reallocation alone cannot clear the book', () => {
  const audit = ob.runAudit({ rows: [orphan({ has_active_policy: 0, balance: 50000 })], asOf: AS_OF });
  assert.match(ob.renderHtml(audit), /Reallocation alone will not clear this book/i);
});

check('a long list is truncated in the email but complete in the CSV', () => {
  const rows = [];
  for (let i = 0; i < 120; i++) rows.push(orphan({ policy_id: i, policy_number: `P${i}`, balance: 1000 + i }));
  const audit = ob.runAudit({ rows, asOf: AS_OF });
  const html = ob.renderHtml(audit);
  assert.match(html, /Showing the 40 largest/i);
  const csv = ob.renderCsv(audit);
  assert.equal(csv.trim().split('\r\n').length, 123, 'header block + all 120 rows');
});

check('CSV escapes properly and carries the disposition', () => {
  const csv = ob.renderCsv(ob.runAudit({ rows: [orphan({ has_active_policy: 1 })], asOf: AS_OF }));
  assert.match(csv, /COMG2024121103/);
  assert.match(csv, /Client has an active policy/);
});

// ── TONE — this is a permanent Internal Audit record ───────────────────────

check('the report is firm but never abusive', () => {
  const html = ob.renderHtml(ob.runAudit({ rows: [orphan()], asOf: AS_OF })).toLowerCase();
  for (const word of ['incompetent', 'lazy', 'negligent', 'stupid', 'useless', 'idiot', 'blame']) {
    assert.ok(!html.includes(word), `"${word}" has no place in an audit record`);
  }
});

// ── QUEUE + RECIPIENTS ──────────────────────────────────────────────────────

check('exceptions are worklist items, never actions to approve', () => {
  const audit = ob.runAudit({ rows: [orphan()], asOf: AS_OF });
  const ex = ob.exceptions(audit);
  assert.equal(ex.length, 1);
  assert.equal(ex[0].needsApproval, false, 'an audit finding is work to do, not money to release');
  assert.equal(ex[0].team, 'Finance');
  assert.equal(ex[0].status, 'open');
  assert.ok(ex[0].priority >= 65, 'must rank above routine ageing (30-60)');
});

check('exceptions degrade safely on junk input', () => {
  assert.deepEqual(ob.exceptions(null), []);
  assert.deepEqual(ob.exceptions({}), []);
});

check('recipients are empty until deliberately armed — this cannot email anyone yet', () => {
  const r = ob.recipients({});
  assert.deepEqual(r.all, [], 'no recipients configured means the send no-ops');
});

check('recipients come from settings and are split and de-duplicated', () => {
  const r = ob.recipients({
    BRAIN_ORPHAN_BALANCES_FINANCE_RECIPIENTS: 'a@x.test, b@x.test',
    BRAIN_ORPHAN_BALANCES_IA_RECIPIENTS: 'b@x.test',
    BRAIN_ORPHAN_BALANCES_CFO_RECIPIENTS: 'c@x.test',
  });
  assert.deepEqual(r.finance, ['a@x.test', 'b@x.test']);
  assert.equal(r.all.length, 3, 'b@x.test must not be listed twice');
});

check('no real address is hardcoded in the module source', () => {
  const src = fs.readFileSync(path.join(__dirname, '..', 'lib', 'orphanBalances.js'), 'utf8');
  assert.ok(!/@alphadirect\.co\.bw/.test(src), 'a real address is hardcoded in orphanBalances.js');
});

// ── summary ─────────────────────────────────────────────────────────────────
console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
