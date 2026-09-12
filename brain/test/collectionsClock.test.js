'use strict';
// collectionsClock.test.js — PURE tests for the non-payment clock (no DB / no mysql2).
// Run: node test/collectionsClock.test.js
const assert = require('node:assert');
const clock = require('../lib/collectionsClock');
const ro = require('../lib/collectionsRo');

let pass = 0, fail = 0;
function check(n, fn) { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } }

// helper: build monthly periods quickly
const M = (period, paid, amount = 100) => ({ period, due: `${period}-01`, amount, paid });

// Historic-scenario override: these fixtures predate the 2026-07-01 rule start,
// so stage/guard tests pass an explicit early ruleStart. Default-cutoff behaviour
// has its own tests below ("rule start" section).
const PRE_RULE = '2026-01-01';

// ── consecutive-unpaid detection ─────────────────────────────────────────────
check('DOM/COM: 2 consecutive unpaid months are detected from the tail (once both ENDED)', () => {
  const months = [M('2026-03', true), M('2026-04', false), M('2026-05', false)];
  const { count, streak } = clock.consecutiveUnpaidMonths(months, '2026-06-05');
  assert.equal(count, 2);
  assert.equal(streak[0].period, '2026-04');
  assert.equal(streak[1].period, '2026-05');
});

check('a paid month resets the consecutive streak', () => {
  const months = [M('2026-02', false), M('2026-03', true), M('2026-04', false)];
  const { count } = clock.consecutiveUnpaidMonths(months, '2026-05-03');
  assert.equal(count, 1); // only the trailing April is unpaid
});

check('the CURRENT month never counts — it has not ended yet (CFO 2026-07-21)', () => {
  // on 1 Aug, August is still collectable: only July counts as missed
  const months = [M('2026-07', false), M('2026-08', false)];
  const { count } = clock.consecutiveUnpaidMonths(months, '2026-08-01');
  assert.equal(count, 1);
});

check('future/not-yet-due months are ignored', () => {
  const months = [M('2026-04', false), M('2026-05', false), M('2099-01', false)];
  const { count } = clock.consecutiveUnpaidMonths(months, '2026-06-05');
  assert.equal(count, 2); // 2099 not due yet
});

// ── stage transitions: deactivate → grace → cancel ───────────────────────────
check('active policy with 2 unpaid → deactivate_candidate (suspend + email due)', () => {
  const r = clock.classifyPolicy({
    policyNumber: 'DOMG1', billingType: 'DOM', policyStatus: 'active', channel: 'ledger',
    months: [M('2026-03', true), M('2026-04', false), M('2026-05', false)],
    successEvents: [],
  }, '2026-06-05', PRE_RULE);
  assert.equal(r.stage, 'deactivate_candidate');
  assert.equal(r.monthsUnpaid, 2);
  assert.equal(r.deactivatedAt, '2026-05-31'); // close (last day) of the 2nd unpaid month
  assert.equal(r.graceEndsAt, '2026-06-15');   // +15 calendar days = cancel day
  assert.equal(r.signalConfidence, 'clean');
});

check('deactivated policy within 15 days → grace', () => {
  const r = clock.classifyPolicy({
    policyNumber: 'DOMG2', billingType: 'DOM', policyStatus: 'deactivated', channel: 'ledger',
    months: [M('2026-03', true), M('2026-04', false), M('2026-05', false)],
    successEvents: [],
  }, '2026-06-09', PRE_RULE); // deactivatedAt 2026-05-31, +9 days
  assert.equal(r.stage, 'grace');
  assert.equal(r.daysOverdue, 9);
});

check('deactivated policy past 15 days still unpaid → cancel_candidate', () => {
  const r = clock.classifyPolicy({
    policyNumber: 'DOMG3', billingType: 'DOM', policyStatus: 'deactivated', channel: 'ledger',
    months: [M('2026-03', true), M('2026-04', false), M('2026-05', false)],
    successEvents: [],
  }, '2026-06-16', PRE_RULE); // deactivatedAt 2026-05-31, +16 days > 15
  assert.equal(r.stage, 'cancel_candidate');
  assert.ok(r.daysOverdue > clock.GRACE_DAYS);
});

check('grace runs days 1-14; day 15 IS the cancel day (CFO 2026-07-21)', () => {
  const base = { policyNumber: 'D', billingType: 'DOM', policyStatus: 'deactivated', channel: 'ledger',
    months: [M('2026-04', false), M('2026-05', false)], successEvents: [] };
  // deactivatedAt = 2026-05-31 (close of 2nd month)
  assert.equal(clock.classifyPolicy(base, '2026-06-14', PRE_RULE).stage, 'grace');            // +14
  assert.equal(clock.classifyPolicy(base, '2026-06-15', PRE_RULE).stage, 'cancel_candidate'); // +15 = cancel day
});

check('fewer than 2 consecutive unpaid → not affected (null)', () => {
  const r = clock.classifyPolicy({
    policyNumber: 'X', billingType: 'DOM', policyStatus: 'active',
    months: [M('2026-04', true), M('2026-05', false)], successEvents: [],
  }, '2026-06-05', PRE_RULE);
  assert.strictEqual(r, null);
});

// ── signalConfidence guard (GRA-0203) ────────────────────────────────────────
check('a later SUCCESS on ANY channel → uncertain, NOT clean unpaid', () => {
  const r = clock.classifyPolicy({
    policyNumber: 'MIS1', billingType: 'MIS', policyStatus: 'active', channel: 'RealPay',
    months: [M('2026-03', true), M('2026-04', false), M('2026-05', false)],
    successEvents: [{ channel: 'RealPay', date: '2026-04-15' }], // succeeded DURING the "unpaid" streak
  }, '2026-06-05', PRE_RULE);
  assert.equal(r.signalConfidence, 'uncertain');
  assert.match(r.reason, /GRA-0203|verify/);
});

check('source conflict (RealPay-native vs ledger) → uncertain', () => {
  const c = clock.assessConfidence(
    [{ period: '2026-04', due: '2026-04-01' }, { period: '2026-05', due: '2026-05-01' }],
    [], { sourceConflict: true },
  );
  assert.equal(c.confidence, 'uncertain');
});

check('no success since streak began + sources agree → clean', () => {
  const c = clock.assessConfidence(
    [{ period: '2026-04', due: '2026-04-01' }, { period: '2026-05', due: '2026-05-01' }],
    [{ channel: 'RealPay', date: '2026-01-10' }], // success BEFORE the streak — does not contradict
    { sourceConflict: false },
  );
  assert.equal(c.confidence, 'clean');
});

// ── MIS multi-channel failed-debit detection (via the RO mapper) ─────────────
check('MIS: month is PAID if any channel succeeds; retries in a month = one month', () => {
  const policy = { premium: 49, billingStart: '2026-03-01' };
  const tx = [
    // March: two failed RealPay retries but then a DPO success → March PAID
    { mon: '2026-03', channel: 'RealPay', txStatus: 'Failed', txDate: '2026-03-02', amount: 49 },
    { mon: '2026-03', channel: 'RealPay', txStatus: 'Failed', txDate: '2026-03-05', amount: 49 },
    { mon: '2026-03', channel: 'DPO', txStatus: 'Success', txDate: '2026-03-06', amount: 49 },
    // April + May: only failed RealPay debits → both unpaid
    { mon: '2026-04', channel: 'RealPay', txStatus: 'Failed', txDate: '2026-04-02', amount: 49 },
    { mon: '2026-05', channel: 'VCS', txStatus: 'Failed', txDate: '2026-05-02', amount: 49 },
  ];
  const { months, channel } = ro.buildMisMonths(policy, tx, '2026-06-05');
  const byPeriod = Object.fromEntries(months.map((m) => [m.period, m.paid]));
  assert.equal(byPeriod['2026-03'], true);
  assert.equal(byPeriod['2026-04'], false);
  assert.equal(byPeriod['2026-05'], false);
  assert.equal(channel, 'VCS'); // most recent recurring failed-debit channel
});

check('MIS end-to-end: 2 failed months, active → deactivate_candidate MIS clean', () => {
  const policy = { policyNumber: 'MIS2X', productId: 4, product: 'Legal Insurance', premium: 49,
    billingStart: '2026-03-01', statusCode: 1, customerName: 'INTERNAL ONLY', agent: 'Broker A' };
  const tx = [
    { mon: '2026-03', channel: 'RealPay', txStatus: 'Success', txDate: '2026-03-03', amount: 49 },
    { mon: '2026-04', channel: 'RealPay', txStatus: 'Failed', txDate: '2026-04-03', amount: 49 },
    { mon: '2026-05', channel: 'RealPay', txStatus: 'Failed', txDate: '2026-05-03', amount: 49 },
  ];
  const inputs = ro.assemblePolicyInputs({
    policies: [policy],
    misTxByPolicy: new Map([['MIS2X', tx]]),
  }, '2026-06-05');
  const [r] = clock.classifyAll(inputs, '2026-06-05', PRE_RULE);
  assert.equal(r.billingType, 'MIS');
  assert.equal(r.stage, 'deactivate_candidate');
  assert.equal(r.monthsUnpaid, 2);
  assert.equal(r.channel, 'RealPay');
  assert.equal(r.amountOverdue, 98);
  assert.equal(r.signalConfidence, 'clean');
});

check('MIS GRA-0203: RealPay-native success not in payment_transactions → uncertain + sourceConflict', () => {
  const policy = { policyNumber: 'MIS2Y', productId: 2, product: 'Third Party', premium: 49,
    billingStart: '2026-03-01', statusCode: 1 };
  const tx = [ // payment_transactions shows April/May failed, no success
    { mon: '2026-04', channel: 'RealPay', txStatus: 'Failed', txDate: '2026-04-03', amount: 49 },
    { mon: '2026-05', channel: 'RealPay', txStatus: 'Failed', txDate: '2026-05-03', amount: 49 },
  ];
  const inputs = ro.assemblePolicyInputs({
    policies: [policy],
    misTxByPolicy: new Map([['MIS2Y', tx]]),
    realpaySuccessByPolicy: new Map([['MIS2Y', [{ d: '2026-04-20', amount: 49 }]]]), // RealPay says it PAID
  }, '2026-06-05');
  const [r] = clock.classifyAll(inputs, '2026-06-05', PRE_RULE);
  assert.equal(r.signalConfidence, 'uncertain'); // must NOT be listed as clean unpaid
});

// ── DOM/COM ledger allocation (via the RO mapper) ────────────────────────────
check('DOM/COM: bulk catch-up payment clears earlier months (FIFO) → not unpaid', () => {
  const policy = { policyNumber: 'DOMG9', productId: 8, product: 'Domestic', statusCode: 1 };
  const invoices = [
    { invDate: '2026-03-01', invAmount: 500, invRef: 'INV-3' },
    { invDate: '2026-04-01', invAmount: 500, invRef: 'INV-4' },
    { invDate: '2026-05-01', invAmount: 500, invRef: 'INV-5' },
  ];
  // one bulk payment covering March + April; May still open
  const payments = [{ payDate: '2026-05-02', payAmount: 1000, payRef: 111 }];
  const { months } = ro.buildDomComMonths(policy, invoices, payments, '2026-06-05');
  const byPeriod = Object.fromEntries(months.map((m) => [m.period, m.paid]));
  assert.equal(byPeriod['2026-03'], true);
  assert.equal(byPeriod['2026-04'], true);
  assert.equal(byPeriod['2026-05'], false); // only one month open → not yet 2 consecutive
});

// ── canonical output shape ───────────────────────────────────────────────────
check('canonical AffectedPolicy shape has exactly the agreed keys', () => {
  const r = clock.classifyPolicy({
    policyNumber: 'DOMG1', customerName: 'X', productId: 8, product: 'Domestic', agent: null,
    billingType: 'DOM', policyStatus: 'active', channel: 'ledger',
    months: [M('2026-04', false), M('2026-05', false)], successEvents: [],
  }, '2026-06-05', PRE_RULE);
  assert.deepEqual(Object.keys(r).sort(), [
    'agent', 'amountOverdue', 'billingType', 'cancelBlockedReason', 'cancelNoticeDueAt',
    'cancelNoticeIssuedAt', 'channel', 'customerName', 'daysOverdue',
    'deactivatedAt', 'graceEndsAt', 'monthsUnpaid', 'policyNumber', 'product', 'productId',
    'readyToCancel', 'reason', 'signalConfidence', 'stage',
  ]);
  assert.strictEqual(r.agent, null);
});

// ── CFO 5-day cancellation-notice gate ───────────────────────────────────────
check('cancel_candidate WITHOUT a served notice → readyToCancel false, blocked', () => {
  const r = clock.classifyPolicy({
    policyNumber: 'DOMG3', billingType: 'DOM', policyStatus: 'deactivated', channel: 'ledger',
    months: [M('2026-03', true), M('2026-04', false), M('2026-05', false)],
    successEvents: [],
  }, '2026-06-16', PRE_RULE); // past 15-day grace, but no cancellation notice served
  assert.equal(r.stage, 'cancel_candidate');
  assert.equal(r.readyToCancel, false);
  assert.ok(/not yet served/.test(r.cancelBlockedReason));
  // notice is due 5 days before grace end (grace ends 2026-06-15 → notice due 2026-06-10)
  assert.equal(r.cancelNoticeDueAt, '2026-06-10');
});

check('cancel_candidate WITH notice served <5 days ago → still blocked', () => {
  const r = clock.classifyPolicy({
    policyNumber: 'DOMG3', billingType: 'DOM', policyStatus: 'deactivated', channel: 'ledger',
    months: [M('2026-03', true), M('2026-04', false), M('2026-05', false)],
    successEvents: [], cancellationNoticeAt: '2026-06-14', // served only 2 days before asOf
  }, '2026-06-16', PRE_RULE);
  assert.equal(r.readyToCancel, false);
  assert.ok(/of 5 notice days/.test(r.cancelBlockedReason));
});

check('cancel_candidate WITH notice served >=5 days ago → readyToCancel true, cancel permitted', () => {
  const r = clock.classifyPolicy({
    policyNumber: 'DOMG3', billingType: 'DOM', policyStatus: 'deactivated', channel: 'ledger',
    months: [M('2026-03', true), M('2026-04', false), M('2026-05', false)],
    successEvents: [], cancellationNoticeAt: '2026-06-10', // 6 clear days before asOf
  }, '2026-06-16', PRE_RULE);
  assert.equal(r.stage, 'cancel_candidate');
  assert.equal(r.readyToCancel, true);
  assert.equal(r.cancelBlockedReason, null);
});

check('the 5-day notice sits INSIDE grace — cancel date does not move (still day 15)', () => {
  const base = { policyNumber: 'DOMG9', billingType: 'DOM', policyStatus: 'deactivated', channel: 'ledger',
    months: [M('2026-06', true), M('2026-07', false), M('2026-08', false)], successEvents: [],
    cancellationNoticeAt: '2026-09-10' };
  assert.equal(clock.classifyPolicy(base, '2026-09-14').stage, 'grace');            // day 14
  assert.equal(clock.classifyPolicy(base, '2026-09-15').stage, 'cancel_candidate'); // day 15 unchanged
  assert.equal(clock.CANCEL_NOTICE_DAYS, 5);
});

// ── rule start: 1 July 2026 (CFO 2026-07-21) ─────────────────────────────────
check('rule start defaults to 2026-07-01', () => {
  assert.equal(clock.RULE_START_DATE, '2026-07-01');
});

check('legacy arrears are invisible: unpaid since Jan 2026 → NOT affected on 21 Jul', () => {
  // 7 straight unpaid months, but only July is on/after the rule start → 1 month → null
  const months = ['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07']
    .map((p) => M(p, false));
  const r = clock.classifyPolicy({
    policyNumber: 'MIS-LEGACY', billingType: 'MIS', policyStatus: 'active', channel: 'RealPay',
    months, successEvents: [],
  }, '2026-07-21');
  assert.strictEqual(r, null);
});

check('earliest candidate is 1 Sep 2026 (Jul+Aug ended): Jan–Aug unpaid counts as 2, not 8', () => {
  const months = ['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08']
    .map((p) => M(p, false));
  const r = clock.classifyPolicy({
    policyNumber: 'DOMG-LEGACY', billingType: 'DOM', policyStatus: 'active', channel: 'ledger',
    months, successEvents: [],
  }, '2026-09-01');
  assert.equal(r.stage, 'deactivate_candidate');
  assert.equal(r.monthsUnpaid, 2);             // July + August only
  assert.equal(r.deactivatedAt, '2026-08-31'); // close (last day) of the 2nd POST-rule month
  assert.equal(r.graceEndsAt, '2026-09-15');   // CFO: cancellation lands 15 Sep
  assert.equal(r.amountOverdue, 200);          // 2 months, not 8 — old debt not billed here
});

check('CFO worked example: Jul+Aug unpaid, deactivated → cancel_candidate ON 15 Sep', () => {
  const base = {
    policyNumber: 'MIS-SEP15', billingType: 'MIS', policyStatus: 'deactivated', channel: 'RealPay',
    months: [M('2026-07', false), M('2026-08', false)], successEvents: [],
  };
  assert.equal(clock.classifyPolicy(base, '2026-09-14').stage, 'grace');
  assert.equal(clock.classifyPolicy(base, '2026-09-15').stage, 'cancel_candidate');
});

check('zero payment data in the window → uncertain, never a clean cancel (CFO 2026-07-21)', () => {
  const built = ro.buildMisMonths({ premium: 250, billingStart: '2026-07-01' }, [], '2026-09-05');
  assert.equal(built.noPaymentData, true);
  const r = clock.classifyPolicy({
    policyNumber: 'MIS-NODATA', billingType: 'MIS', policyStatus: 'active', channel: 'RealPay',
    months: built.months, successEvents: built.successEvents, noPaymentData: built.noPaymentData,
  }, '2026-09-05');
  assert.equal(r.signalConfidence, 'uncertain');
  assert.match(r.reason, /no payment data/);
});

check('a paid July resets the post-rule streak even with old arrears behind it', () => {
  const months = [M('2026-05', false), M('2026-06', false), M('2026-07', true),
    M('2026-08', false), M('2026-09', false)];
  const r = clock.classifyPolicy({
    policyNumber: 'COMG-LEGACY', billingType: 'COM', policyStatus: 'active', channel: 'ledger',
    months, successEvents: [],
  }, '2026-10-05');
  assert.equal(r.monthsUnpaid, 2); // Aug + Sep; May/June never enter the clock
});

// ── read-only / arms-off guardrails ──────────────────────────────────────────
check('windowed feed SQL is bounded by the rule floor, not just 400 days (perf)', () => {
  // The clock discards pre-RULE_START months, so the pull must not reach back the
  // full 400 days when the rule only just started — GREATEST(now-400d, ruleFloor)
  // keeps it tight right after go-live (was pulling ~1.3M MIS rows → timeout).
  for (const sql of [ro.MIS_TX_SQL, ro.REALPAY_SUCCESS_SQL, ro.DOMCOM_INVOICES_SQL, ro.DOMCOM_PAYMENTS_SQL]) {
    assert.match(sql, /GREATEST\(/i, 'query must clamp the window with GREATEST');
    assert.match(sql, /INTERVAL 400 DAY/i, 'keeps the steady-state 400-day cap');
    assert.match(sql, /DATE\('2026-0[56]-\d\d'\)/, 'floors at ~1 month before RULE_START 2026-07-01');
  }
});

check('policy pull is ACTIVE-BOOK only (status = 1, never IN (0,1)) — CFO 2026-08-03', () => {
  // Guards the ~108k never-started/switched-off book out of the clock; the Graphite
  // Sales Dashboard Active tile (29,392) is the truth, not the 213k total.
  assert.match(ro.POLICIES_SQL, /p\.status\s*=\s*1/i, 'must scope to status = 1');
  assert.ok(!/status\s+IN\s*\(\s*0\s*,\s*1\s*\)/i.test(ro.POLICIES_SQL),
    'must NOT re-admit status 0 (never-started/deactivated legacy book)');
  assert.ok(!/status\s+IN\s*\(\s*0\s*,\s*1\s*\)/i.test(ro.DOMCOM_INVOICES_SQL));
  assert.ok(!/status\s+IN\s*\(\s*0\s*,\s*1\s*\)/i.test(ro.DOMCOM_PAYMENTS_SQL));
});

check('all RO feed SQL is SELECT-only (no writes)', () => {
  for (const sql of [ro.POLICIES_SQL, ro.MIS_TX_SQL, ro.REALPAY_SUCCESS_SQL, ro.DOMCOM_INVOICES_SQL, ro.DOMCOM_PAYMENTS_SQL]) {
    assert.match(sql, /select/i);
    assert.ok(!/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|REPLACE|GRANT)\b/i.test(sql));
  }
});

check('DOM/COM payment SQL uses ledger id, not the bank narrative (DPA)', () => {
  assert.match(ro.DOMCOM_PAYMENTS_SQL, /pl\.id\s+AS\s+payRef/i);
  assert.ok(!/trans_ref/i.test(ro.DOMCOM_PAYMENTS_SQL));
});

check('billingTypeFor maps prefixes DOMG/COMG/COMD/MIS correctly', () => {
  assert.equal(ro.billingTypeFor('DOMG123'), 'DOM');
  assert.equal(ro.billingTypeFor('COMG123'), 'COM');
  assert.equal(ro.billingTypeFor('COMD123'), 'COM');
  assert.equal(ro.billingTypeFor('MIS2024'), 'MIS');
  assert.equal(ro.billingTypeFor('ADH2024'), 'MIS');
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
