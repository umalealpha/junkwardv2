'use strict';
// Unit test for lib/cancellationNoticeEmail.js — run: node test/cancellationNoticeEmail.test.js
// Recipients come from settings (CFO pattern) — set BEFORE requiring the module.

process.env.BRAIN_CANCEL_NOTICE_RECIPIENTS =
  'pganesharajah@alphadirect.co.bw, finance-lead@alphadirect.co.bw';

const assert = require('node:assert');
const { buildDueList, maybeSendCancellationNotice, recipients } = require('../lib/cancellationNoticeEmail');

let pass = 0, fail = 0;
function check(name, fn) {
  try { fn(); pass++; console.log(`  ok  ${name}`); }
  catch (e) { fail++; console.log(`FAIL  ${name}\n      ${e.message}`); }
}

// asOf fixed so the notice-due math is deterministic.
const ASOF = '2026-09-12';

// A policy whose 5-day notice is DUE: in grace, notice not served, dueAt reached.
const dueGrace = {
  policyNumber: 'MISDUE1', product: 'Motor', billingType: 'MIS', monthsUnpaid: 2,
  amountOverdue: 1200, stage: 'grace', deactivatedAt: '2026-08-31',
  graceEndsAt: '2026-09-15', cancelNoticeDueAt: '2026-09-10',
  cancelNoticeIssuedAt: null, signalConfidence: 'clean',
};
// Also due, but sooner cancel + uncertain signal (should sort FIRST).
const dueSoonerUncertain = {
  policyNumber: 'MISDUE2', product: 'Motor', billingType: 'MIS', monthsUnpaid: 3,
  amountOverdue: 3000, stage: 'cancel_candidate', deactivatedAt: '2026-08-29',
  graceEndsAt: '2026-09-13', cancelNoticeDueAt: '2026-09-08',
  cancelNoticeIssuedAt: null, signalConfidence: 'uncertain',
};
// NOT due — only 2 months but still deactivate_candidate (not grace/cancel).
const notYet = { ...dueGrace, policyNumber: 'MISX1', stage: 'deactivate_candidate' };
// NOT due — notice already served.
const served = { ...dueGrace, policyNumber: 'MISX2', cancelNoticeIssuedAt: '2026-09-11' };
// NOT due — notice-due date has NOT arrived yet (dueAt in the future vs asOf).
const future = { ...dueGrace, policyNumber: 'MISX3', cancelNoticeDueAt: '2026-09-20' };

check('buildDueList includes a grace policy past its notice-due date, unserved', () => {
  const list = buildDueList([dueGrace], ASOF);
  assert.equal(list.length, 1);
  assert.equal(list[0].policyNumber, 'MISDUE1');
});

check('buildDueList excludes deactivate_candidate, served, and not-yet-due', () => {
  const list = buildDueList([notYet, served, future], ASOF);
  assert.equal(list.length, 0);
});

check('buildDueList sorts soonest cancel date first', () => {
  const list = buildDueList([dueGrace, dueSoonerUncertain], ASOF);
  assert.equal(list.length, 2);
  assert.equal(list[0].policyNumber, 'MISDUE2'); // graceEndsAt 09-13 < 09-15
  assert.equal(list[1].policyNumber, 'MISDUE1');
});

check('recipients come from settings (BRAIN_CANCEL_NOTICE_RECIPIENTS)', () => {
  const r = recipients();
  assert.equal(r.length, 2);
  assert.ok(r.includes('pganesharajah@alphadirect.co.bw'));
});

check('dryRun returns a plan addressed to the configured recipients + count', () => {
  const r = maybeSendCancellationNotice({ affected: [dueGrace, dueSoonerUncertain], asOf: ASOF, dryRun: true });
  assert.equal(r.status, 'planned');
  assert.equal(r.count, 2);
  assert.deepEqual(r.to, recipients());
  assert.ok(/STOP\/CONTINUE/.test(r.subject));
});

check('no due policies → skipped, no send', () => {
  const r = maybeSendCancellationNotice({ affected: [notYet, served], asOf: ASOF, dryRun: true });
  assert.equal(r.status, 'skipped');
  assert.equal(r.count, 0);
});

console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
