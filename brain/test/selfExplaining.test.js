'use strict';
// selfExplaining.test.js — the "make this self explanatory" guard-rails
// (CFO 2026-07-28). These are not cosmetic tests: they are the reason the
// console can be trusted.
//
// Proves:
//   1. glossary — every number the page renders HAS a definition, and every
//      definition carries how-it-is-counted / what-is-excluded / who-acts.
//   2. featureRegistry — every entry names a REAL module file, so "27 features"
//      can never become a list of things that do not exist; and the held items
//      always say why.
//   3. healthcareCompliance — counts only, no PII columns, mutually-exclusive
//      buckets that add up, and the un-defined scheme rules stay listed as
//      pending rather than being invented.
//   4. xlsx — produces a real, parseable workbook (ZIP magic + required parts).
//   5. weeklyExtract — every dataset explains itself, row caps are STATED, and
//      an external recipient can never receive an extract.

const assert = require('node:assert');
const fs = require('node:fs');
const path = require('node:path');

const glossary = require('../lib/glossary');
const featureRegistry = require('../lib/featureRegistry');
const healthcare = require('../lib/healthcareCompliance');
const xlsx = require('../lib/xlsx');
const weeklyExtract = require('../lib/weeklyExtract');

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

// ── 1. GLOSSARY ────────────────────────────────────────────────────────────
// The exact metric ids the console asks for. If the page starts showing a new
// tile, its id goes here and the definition must exist — that is the point.
const RENDERED_METRIC_IDS = [
  'total_exceptions', 'finance', 'compliance', 'live_arms',
  'debtors', 'collections', 'high_arrears', 'fraud', 'event',
  'kyc', 'kyc_gap_active_book', 'by_category',
  // Healthcare has its OWN entries. Borrowing the whole-book ones printed
  // health-only figures under whole-book headings (the tile heading comes from
  // the definition's label) — two different numbers with the same name.
  'healthcare_non_compliance', 'healthcare_no_docs',
  'healthcare_kyc_not_approved', 'healthcare_active_policies',
  'sweep', 'priority',
];

check('glossary: every number the console renders has a definition', () => {
  const missing = glossary.missingDefinitions(RENDERED_METRIC_IDS);
  assert.deepEqual(missing, [], `undefined metrics on screen: ${missing.join(', ')}`);
});

check('glossary: every definition explains counting, exclusions and ownership', () => {
  for (const [id, m] of Object.entries(glossary.METRICS)) {
    assert.ok(m.label, `${id}: no label`);
    assert.ok(m.means && m.means.length > 20, `${id}: no plain-English meaning`);
    assert.ok(m.howCounted && m.howCounted.length > 20, `${id}: no howCounted`);
    assert.ok(m.excludes && m.excludes.length > 5, `${id}: no excludes — undocumented filters are how "the numbers do not add up" starts`);
    assert.ok(m.source, `${id}: no source`);
    assert.ok(m.whoActs, `${id}: nobody owns it`);
  }
});

check('glossary: no jargon in the plain-English text', () => {
  // The CFO is not a coder and neither are the teams reading this.
  const banned = [/\bAPI\b/, /\bendpoint/i, /\benv var/i, /\bJSON\b/, /\bHTTP\b/, /\bDSN\b/];
  for (const [id, m] of Object.entries(glossary.METRICS)) {
    for (const re of banned) {
      assert.ok(!re.test(m.means), `${id}.means contains jargon: ${re}`);
      assert.ok(!re.test(m.howCounted), `${id}.howCounted contains jargon: ${re}`);
    }
  }
});

check('glossary: the total is defined as the sum of the teams', () => {
  const t = glossary.metric('total_exceptions');
  assert.match(t.formula, /Finance/);
  assert.match(t.formula, /Compliance/);
});

check('glossary: the compliance definition names its filters', () => {
  const c = glossary.metric('compliance');
  assert.match(c.excludes, /400/, 'the 400-day window is the biggest cut and must be stated');
  assert.match(c.excludes, /in-force|active/i);
});

check('glossary: a healthcare metric never borrows a whole-book label', () => {
  // The regression this guards: a health-only tile headed "KYC gap on the active
  // book" or "Active book by product", so two different figures shared one name.
  const wholeBook = ['kyc_gap_active_book', 'by_category', 'kyc'];
  for (const id of ['healthcare_no_docs', 'healthcare_kyc_not_approved', 'healthcare_active_policies']) {
    const m = glossary.metric(id);
    assert.ok(m, `${id} must have its own definition, not reuse a whole-book one`);
    assert.match(m.label + ' ' + m.means, /[Hh]ealth/, `${id}: the label must say it is health-only`);
    for (const w of wholeBook) {
      assert.notEqual(m.label, glossary.metric(w).label, `${id} shares a label with ${w}`);
    }
  }
});

check('glossary: FAQ answers the "does it act by itself" question', () => {
  const joined = glossary.FAQ.map((f) => f.q + ' ' + f.a).join(' ').toLowerCase();
  assert.ok(joined.includes('arms are off') || joined.includes('live arms are off'));
  assert.ok(glossary.FAQ.length >= 5);
});

// ── 2. FEATURE REGISTRY ────────────────────────────────────────────────────
const LIB_DIR = path.join(__dirname, '..', 'lib');

check('features: every entry names a real module file', () => {
  for (const f of featureRegistry.FEATURES) {
    const p = path.join(LIB_DIR, `${f.module}.js`);
    assert.ok(fs.existsSync(p), `${f.id} → lib/${f.module}.js does not exist`);
  }
});

check('features: every entry has an honest status and says what it does', () => {
  const allowed = new Set(['live', 'ready', 'held', 'plumbing']);
  for (const f of featureRegistry.FEATURES) {
    assert.ok(allowed.has(f.status), `${f.id}: bad status "${f.status}"`);
    assert.ok(f.name && f.does, `${f.id}: missing name/does`);
    assert.ok(featureRegistry.STATUS_META[f.status], `${f.id}: status has no plain-English meaning`);
  }
});

check('features: a "ready" feature says what it takes to switch on', () => {
  for (const f of featureRegistry.byStatus('ready')) {
    assert.ok(f.toTurnOn, `${f.id}: "built, not switched on" with no explanation of what is missing`);
  }
});

check('features: a "held" feature says why, and what unblocks it', () => {
  const held = featureRegistry.byStatus('held');
  assert.ok(held.length >= 2, 'the known held items (claim_paid, auto-cancel) must be listed');
  for (const f of held) {
    assert.ok(f.heldBecause, `${f.id}: held with no reason — someone will "fix" it`);
    assert.ok(f.unblockedBy, `${f.id}: held with no way out`);
  }
});

check('features: the headline counts match the list', () => {
  const r = featureRegistry.registry();
  const summed = Object.values(r.counts).reduce((a, b) => a + b, 0);
  assert.equal(summed, r.total);
  assert.equal(r.total, featureRegistry.FEATURES.length);
  assert.ok(r.counts.ready > 0, 'the whole point: features exist that are not switched on');
});

check('features: ids are unique', () => {
  const ids = featureRegistry.FEATURES.map((f) => f.id);
  assert.equal(new Set(ids).size, ids.length, 'duplicate feature id');
});

// ── 3. HEALTHCARE ──────────────────────────────────────────────────────────
check('healthcare: buckets are exclusive and add up to non-compliant', () => {
  const b = healthcare.summarise({ active_policies: 1000, no_docs: 120, kyc_not_approved: 80 });
  assert.equal(b.non_compliant, 200);
  assert.equal(b.compliant, 800);
  assert.equal(b.compliant + b.non_compliant, b.active_policies);
});

check('healthcare: cannot report more non-compliant than the active book', () => {
  const b = healthcare.summarise({ active_policies: 100, no_docs: 90, kyc_not_approved: 90 });
  assert.equal(b.non_compliant, 100);
  assert.equal(b.compliant, 0);
});

check('healthcare: empty input is a clean zero, never a crash', () => {
  const b = healthcare.summarise();
  assert.equal(b.active_policies, 0);
  assert.equal(b.non_compliant, 0);
  assert.deepEqual(b._pending, healthcare.PENDING_RULES);
});

check('healthcare: undefined scheme rules stay PENDING, never invented', () => {
  const b = healthcare.summarise({ active_policies: 10 });
  assert.ok(b._pending.includes('waiting_period_served'));
  assert.ok(b._pending.includes('pre_existing_condition_declared'));
  assert.match(b._scope, /identity paperwork only/i);
});

check('healthcare: every query is an aggregate over the MASKED view only', () => {
  for (const sql of [healthcare.ACTIVE_SQL, healthcare.NO_DOCS_SQL, healthcare.KYC_NOT_APPROVED_SQL]) {
    assert.match(sql, /COUNT\(\*\)/, 'not an aggregate — could return customer rows');
    assert.ok(!/customer_kyc/.test(sql) || /brain_customer_kyc/.test(sql),
      'KYC must be read through the masked view brain_customer_kyc');
    // No raw PII column may appear anywhere.
    for (const col of ['omang_number', 'passport_number', 'id_number', 'ocr_extracted', 'account_number', 'first_name', 'surname']) {
      assert.ok(!sql.includes(col), `raw PII column "${col}" in a healthcare query`);
    }
    assert.ok(!/\bSELECT\s+\*/i.test(sql), 'SELECT * could leak columns');
  }
});

check('healthcare: only in-force policies are counted (status = 1)', () => {
  for (const sql of [healthcare.ACTIVE_SQL, healthcare.NO_DOCS_SQL, healthcare.KYC_NOT_APPROVED_SQL]) {
    assert.match(sql, /p\.status = 1/, 'never-activated and lapsed policies must be excluded');
  }
});

check('healthcare: the queue line is ONE summary, not a row per customer', () => {
  const b = healthcare.summarise({ active_policies: 1000, no_docs: 120, kyc_not_approved: 80 });
  const ex = healthcare.exceptions(b);
  assert.equal(ex.length, 1);
  assert.equal(ex[0].team, 'Compliance');
  assert.equal(ex[0].domain, 'healthcare');
  assert.equal(ex[0].ref, null, 'a summary line must not carry a customer reference');
  assert.match(ex[0].detail, /identity paperwork only/i);
  assert.deepEqual(healthcare.exceptions(healthcare.summarise()), [], 'no gap → no queue noise');
});

// ── 4. XLSX ────────────────────────────────────────────────────────────────
check('xlsx: produces a real workbook with every required part', () => {
  const buf = xlsx.build([{
    name: 'Data',
    columns: [{ key: 'a', label: 'Policy' }, { key: 'b', label: 'Amount' }, { key: 'c', label: 'Date' }],
    rows: [{ a: 'DOMG1', b: 1234.5, c: '2026-07-28' }, { a: 'MIS2', b: 0, c: null }],
  }]);
  assert.ok(Buffer.isBuffer(buf));
  assert.equal(buf.slice(0, 2).toString('latin1'), 'PK', 'not a ZIP — Excel will refuse it');
  const raw = buf.toString('latin1');
  for (const part of ['[Content_Types].xml', 'xl/workbook.xml', 'xl/worksheets/sheet1.xml', 'xl/styles.xml', '_rels/.rels']) {
    assert.ok(raw.includes(part), `missing part ${part}`);
  }
});

check('xlsx: escapes characters that would corrupt the file', () => {
  const sheet = { name: 'x', columns: [{ key: 'a', label: 'A' }], rows: [{ a: 'Smith & Sons <Ltd>' }] };
  // Round-trip through the sheet writer, not the zip, so we can assert on the XML.
  const csvOut = xlsx.toCsv(sheet);
  assert.ok(csvOut.includes('Smith & Sons <Ltd>'), 'CSV keeps the literal text');
  assert.equal(xlsx.xmlEsc('a & b < c > d "e"'), 'a &amp; b &lt; c &gt; d &quot;e&quot;');
});

check('xlsx: column letters are right past Z', () => {
  assert.equal(xlsx.colName(0), 'A');
  assert.equal(xlsx.colName(25), 'Z');
  assert.equal(xlsx.colName(26), 'AA');
  assert.equal(xlsx.colName(27), 'AB');
});

check('xlsx: refuses to build nothing', () => {
  assert.throws(() => xlsx.build([]), /at least one sheet/);
});

// ── 5. WEEKLY EXTRACT ──────────────────────────────────────────────────────
const QUEUE = {
  generatedAt: '2026-07-28T05:45:00.000Z', source: 'graphite-ro [live]', liveArms: false,
  total: 5, counts: { Finance: 3, Compliance: 2 },
  teams: {
    Finance: [
      { priority: 60, team: 'Finance', domain: 'debtors', ref: 'ACC-1', title: 'Overdue 500', detail: 'x', status: 'open' },
      { priority: 90, team: 'Finance', domain: 'collections', ref: 'DOMG1', title: 'Cancel candidate', detail: 'y', needsApproval: true, status: 'open' },
      { priority: 85, team: 'Finance', domain: 'high_arrears', ref: 'CLM-1', title: 'High arrears', detail: 'z', status: 'open' },
    ],
    Compliance: [
      { priority: 45, team: 'Compliance', domain: 'kyc', ref: 'KYC-1', title: 'Docs outstanding', detail: 'a', status: 'open' },
      { priority: 65, team: 'Compliance', domain: 'healthcare', ref: null, title: 'Healthcare gap', detail: 'b', status: 'open' },
    ],
  },
};

check('extract: there is a dataset for each team the CFO named', () => {
  for (const k of ['kyc', 'debtors', 'analytics', 'finance', 'accounts']) {
    assert.ok(weeklyExtract.DATASETS[k], `no dataset for ${k}`);
    assert.ok(weeklyExtract.DATASETS[k].plain, `${k}: no plain-English description`);
    assert.ok(weeklyExtract.DATASETS[k].recipientEnv, `${k}: no configurable recipient list`);
  }
});

check('extract: each dataset picks the right rows', () => {
  const kyc = weeklyExtract.buildDataset('kyc', { queue: QUEUE });
  assert.equal(kyc.meta.available, 2, 'KYC team gets the Compliance rows');
  const deb = weeklyExtract.buildDataset('debtors', { queue: QUEUE });
  assert.equal(deb.meta.available, 2, 'Debtors get debtors + collections, not high-arrears');
  const acc = weeklyExtract.buildDataset('accounts', { queue: QUEUE });
  assert.equal(acc.meta.available, 2, 'Accounts get debtors + high-arrears, not the clock');
  const all = weeklyExtract.buildDataset('analytics', { queue: QUEUE });
  assert.equal(all.meta.available, 5, 'Analytics get everything');
});

check('extract: every workbook opens with a Read me carrying the definitions', () => {
  const { sheets } = weeklyExtract.buildDataset('kyc', { queue: QUEUE });
  assert.equal(sheets[0].name, 'Read me');
  const text = sheets[0].rows.map((r) => `${r.a} ${r.b}`).join('\n');
  assert.match(text, /How it is counted/);
  assert.match(text, /What is left out/);
  assert.match(text, /DATA PROTECTION/);
  assert.match(text, /400/, 'the KYC filters must be spelled out in the file itself');
});

check('extract: the row cap is STATED, never silent', () => {
  const { sheets } = weeklyExtract.buildDataset('analytics', { queue: QUEUE });
  const readme = sheets[0].rows.map((r) => `${r.a}|${r.b}`).join('\n');
  assert.match(readme, /Rows in this file\|5/);
  assert.match(readme, /Rows that existed\|5/);
  assert.match(readme, /Rows left out\|none/);
});

check('extract: rows carry no nested objects — display columns only', () => {
  const row = weeklyExtract.toRow({
    priority: 90, team: 'Finance', domain: 'collections', ref: 'DOMG1', title: 't', detail: 'd',
    needsApproval: true, status: 'open',
    affected: { customerName: 'Jane Doe' }, aging: { balance: 1 }, fraud: { flags: [] },
  });
  assert.deepEqual(Object.keys(row).sort(),
    ['detail', 'domain', 'needsApproval', 'priority', 'ref', 'status', 'team', 'title']);
  assert.equal(JSON.stringify(row).includes('Jane Doe'), false);
});

check('extract: the workbook is a valid xlsx', () => {
  const { buffer, meta } = weeklyExtract.workbook('finance', { queue: QUEUE });
  assert.equal(buffer.slice(0, 2).toString('latin1'), 'PK');
  assert.equal(meta.written, 3);
  assert.equal(meta.label, 'Finance team');
});

check('extract: filenames are dated and dataset-specific', () => {
  assert.equal(weeklyExtract.filename('kyc', '2026-07-28T05:45:00Z'), 'alpha-brain-kyc-2026-07-28.xlsx');
  assert.equal(weeklyExtract.filename('kyc', null, 'csv'), 'alpha-brain-kyc-undated.csv');
});

check('extract: unknown dataset is refused, not silently emptied', () => {
  assert.throws(() => weeklyExtract.buildDataset('payroll', { queue: QUEUE }), /unknown dataset/);
});

check('extract: recipients are configuration only — nothing is hardcoded', () => {
  const empty = weeklyExtract.recipients({});
  for (const k of Object.keys(weeklyExtract.DATASETS)) {
    assert.deepEqual(empty[k], [], `${k} has a hardcoded recipient — it could email by accident`);
  }
  const set = weeklyExtract.recipients({ BRAIN_EXTRACT_KYC_RECIPIENTS: ' a@alphadirect.co.bw , b@alphadirect.co.bw ' });
  assert.deepEqual(set.kyc, ['a@alphadirect.co.bw', 'b@alphadirect.co.bw']);
});

check('extract: an external address can never receive an extract', () => {
  const list = ['ok@alphadirect.co.bw', 'ok2@sub.alphadirect.co.bw', 'leak@gmail.com', 'evil@alphadirect.co.bw.attacker.com', 'nonsense'];
  assert.deepEqual(weeklyExtract.internalOnly(list), ['ok@alphadirect.co.bw', 'ok2@sub.alphadirect.co.bw']);
});

check('extract: sendInternalReport refuses an external recipient even with the switch on', () => {
  const prev = process.env.BRAIN_INTERNAL_REPORTS;
  process.env.BRAIN_INTERNAL_REPORTS = 'true';
  try {
    const mailer = require('../lib/mailer');
    const r = mailer.sendInternalReport({ to: ['leak@gmail.com'], subject: 's', html: '<p>x</p>' });
    assert.equal(r.ok, false);
    assert.equal(r.reason, 'external_recipient');
  } finally {
    if (prev === undefined) delete process.env.BRAIN_INTERNAL_REPORTS; else process.env.BRAIN_INTERNAL_REPORTS = prev;
  }
});

check('extract: sendInternalReport is OFF by default', () => {
  const prev = process.env.BRAIN_INTERNAL_REPORTS;
  delete process.env.BRAIN_INTERNAL_REPORTS;
  try {
    const mailer = require('../lib/mailer');
    const r = mailer.sendInternalReport({ to: ['ok@alphadirect.co.bw'], subject: 's', html: '<p>x</p>' });
    assert.equal(r.ok, false);
    assert.equal(r.reason, 'internal_reports_off');
  } finally {
    if (prev !== undefined) process.env.BRAIN_INTERNAL_REPORTS = prev;
  }
});

check('extract: the internal-report switch is separate from live arms', () => {
  const armed = require('../lib/armed');
  const prevArms = process.env.BRAIN_LIVE_ARMS;
  const prevInt = process.env.BRAIN_INTERNAL_REPORTS;
  try {
    process.env.BRAIN_LIVE_ARMS = 'false';
    process.env.BRAIN_INTERNAL_REPORTS = 'true';
    assert.equal(armed.liveArmsOn(), false, 'customer-facing arms must stay off');
    assert.equal(armed.internalReportsOn(), true, 'internal reports can be on independently');
  } finally {
    if (prevArms === undefined) delete process.env.BRAIN_LIVE_ARMS; else process.env.BRAIN_LIVE_ARMS = prevArms;
    if (prevInt === undefined) delete process.env.BRAIN_INTERNAL_REPORTS; else process.env.BRAIN_INTERNAL_REPORTS = prevInt;
  }
});

check('extract: the covering email never claims something was executed', () => {
  const { meta } = weeklyExtract.buildDataset('debtors', { queue: QUEUE });
  const html = weeklyExtract.renderEmailHtml(meta);
  assert.match(html, /has not sent, moved or cancelled anything/);
  assert.match(html, /Internal use only/);
});

// ── 6. THE WEEKLY SCHEDULE ─────────────────────────────────────────────────
check('schedule: the weekly extract fires Monday 00:00 Gaborone (Sun 22:00 UTC)', () => {
  const { msUntilNextWeeklyExtract } = require('../server');
  // Wed 2026-07-29 08:00 UTC → next fire is Sun 2026-08-02 22:00 UTC.
  const from = new Date('2026-07-29T08:00:00.000Z');
  const fire = new Date(from.getTime() + msUntilNextWeeklyExtract(from));
  assert.equal(fire.getUTCDay(), 0, 'must fire on a Sunday in UTC');
  assert.equal(fire.getUTCHours(), 22, '22:00 UTC = 00:00 Monday in Gaborone (UTC+2)');
  assert.equal(fire.toISOString().slice(0, 10), '2026-08-02');
});

console.log(`\nselfExplaining: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
