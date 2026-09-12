'use strict';
// fridayReport.test.js — the consolidated Friday 6 p.m. non-payment report.
// Pure + deterministic (fixed `now`). Run: node test/fridayReport.test.js

const assert = require('node:assert');
const fr = require('../lib/fridayReport');

const NOW = new Date('2026-07-24T18:00:00Z'); // a Friday 18:00 UTC — deterministic

let pass = 0, fail = 0;
function check(n, fn) { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } }

function fixtures() {
  return [
    { policyNumber: 'MIS-1001', customerName: 'Kefilwe Moeng', product: 'Legal Insurance', agent: 'Tebogo A.', channel: 'RealPay', amountOverdue: 480, daysOverdue: 3, stage: 'deactivate_candidate', signalConfidence: 'clean' },
    { policyNumber: 'DOM-2002', customerName: 'Neo Pilane', product: 'Funeral Plan', agent: 'Boitumelo K.', channel: 'DPO', amountOverdue: 250, daysOverdue: 9, stage: 'grace', signalConfidence: 'clean' },
    { policyNumber: 'COM-3003', customerName: 'Lorato Sithole', product: 'Motor', agent: 'Mpho D.', channel: 'ledger', amountOverdue: 1520, daysOverdue: 18, stage: 'cancel_candidate', signalConfidence: 'clean' },
    { policyNumber: 'MIS-4004', customerName: 'Gaone Rams', product: 'Home', agent: 'Kabo T.', channel: 'RealPay', amountOverdue: 900, daysOverdue: 2, stage: 'deactivate_candidate', signalConfidence: 'uncertain' },
  ];
}

check('Section 1 DEACTIVATED = deactivate_candidate + grace (currently suspended)', () => {
  const r = fr.buildFridayReport(fixtures(), { now: NOW });
  const nums = r.sections.deactivated.rows.map((x) => x.policyNumber).sort();
  assert.deepEqual(nums, ['MIS-1001', 'MIS-4004', 'DOM-2002'].sort());
  assert.equal(r.counts.deactivated, 3);
});

check('Section 2 CANCELLED = cancel_candidate only', () => {
  const r = fr.buildFridayReport(fixtures(), { now: NOW });
  assert.deepEqual(r.sections.cancelled.rows.map((x) => x.policyNumber), ['COM-3003']);
  assert.equal(r.sections.cancelled.rows[0].status, 'CANCELLED');
  assert.equal(r.counts.cancelled, 1);
});

check('rows carry exactly the CFO columns', () => {
  const r = fr.buildFridayReport(fixtures(), { now: NOW });
  assert.deepEqual(fr.COLUMNS.map((c) => c.key),
    ['policyNumber', 'client', 'product', 'agent', 'amountOverdue', 'daysOverdue', 'status']);
  const row = r.sections.deactivated.rows[0];
  for (const c of fr.COLUMNS) assert.ok(Object.prototype.hasOwnProperty.call(row, c.key), 'missing ' + c.key);
});

check('deterministic timestamp comes from the passed `now` (no wall clock)', () => {
  const r = fr.buildFridayReport(fixtures(), { now: NOW });
  assert.equal(r.generatedAt, '2026-07-24 18:00');
});

check('totals sum the overdue amounts per section', () => {
  const r = fr.buildFridayReport(fixtures(), { now: NOW });
  assert.equal(r.totals.deactivatedOverdue, 480 + 250 + 900);
  assert.equal(r.totals.cancelledOverdue, 1520);
});

check('handles empty input', () => {
  const r = fr.buildFridayReport([], { now: NOW });
  assert.equal(r.counts.deactivated, 0);
  assert.equal(r.counts.cancelled, 0);
});

check('CSV has both sections, the column header, and quotes risky cells', () => {
  const r = fr.buildFridayReport(fixtures(), { now: NOW });
  const csv = fr.renderFridayReportCsv(r);
  assert.ok(csv.includes('DEACTIVATED'));
  assert.ok(csv.includes('CANCELLED'));
  assert.ok(csv.includes('Policy Number,Client,Product,Agent,Amount Overdue,Days Overdue,Status'));
  assert.ok(csv.includes('MIS-1001'));
  assert.ok(csv.includes('COM-3003'));
});

check('CSV escapes a comma inside a field', () => {
  const r = fr.buildFridayReport([
    { policyNumber: 'P,1', customerName: 'Doe, John', product: 'Motor', agent: 'A', amountOverdue: 10, daysOverdue: 1, stage: 'cancel_candidate' },
  ], { now: NOW });
  const csv = fr.renderFridayReportCsv(r);
  assert.ok(csv.includes('"P,1"'));
  assert.ok(csv.includes('"Doe, John"'));
});

check('HTML is branded (navy + orange), uses bgcolor attrs, and lists both sections', () => {
  const r = fr.buildFridayReport(fixtures(), { now: NOW });
  const html = fr.renderFridayReportHtml(r);
  // The CANONICAL brand (CFO 2026-08-04) — Alpha Navy / Direct Orange from
  // lib/brand.js. This test used to assert #010066/#FE7F0C, a palette that
  // matched neither the company standard nor mailer.js; asserting it was what
  // kept the third palette alive.
  assert.ok(html.includes('#1D3270'), 'Alpha Navy present');
  assert.ok(html.includes('#F47C20'), 'Direct Orange present');
  assert.ok(/bgcolor="#1D3270"/.test(html), 'bgcolor attribute (Outlook paste)');
  assert.ok(/Book Antiqua/.test(html), 'Book Antiqua ("Antica") headings');
  for (const dead of ['#010066', '#FE7F0C']) {
    assert.ok(!html.includes(dead), `retired brand colour ${dead} reappeared`);
  }
  assert.ok(html.includes('MIS-1001') && html.includes('COM-3003'));
  // Uncertain row visually flagged.
  assert.ok(html.includes('VERIFY'), 'uncertain row flagged in HTML');
});

check('recipients come from SETTINGS (env), not hardcoded; union de-dupes', () => {
  const env = {
    BRAIN_FRIDAY_UNDERWRITING_RECIPIENTS: 'uw@x.test, cfo@x.test',
    BRAIN_FRIDAY_FINANCE_RECIPIENTS: 'fin@x.test',
    BRAIN_FRIDAY_CLAIMS_RECIPIENTS: 'claims@x.test',
    BRAIN_FRIDAY_CFO_RECIPIENTS: 'cfo@x.test',
  };
  const rec = fr.reportRecipients(env);
  assert.deepEqual(rec.finance, ['fin@x.test']);
  assert.deepEqual(rec.all.sort(), ['cfo@x.test', 'claims@x.test', 'fin@x.test', 'uw@x.test']);
});

check('recipients empty when settings unset (no hardcoded addresses)', () => {
  const rec = fr.reportRecipients({});
  assert.deepEqual(rec.all, []);
  assert.deepEqual(rec.cfo, []);
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
