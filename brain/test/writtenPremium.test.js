'use strict';
// writtenPremium.test.js — Gross Written Premium feed (T4).
// The dedup / CANCEL-negation / ISSUED-only logic lives in SQL (validated on the
// replica 2026-07-27), so here we (a) unit-test the JS mapper and (b) guard the
// SQL's safety properties so they can't be silently dropped: aggregate-only,
// dedup by MAX(id), ISSUED + non-deleted, CANCEL negated, and the KYC feed's
// active-book filter.
const assert = require('node:assert');
const { buildWrittenPremium, WRITTEN_PREMIUM_SQL, KYC_SQL } = require('../lib/graphiteRo');

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

check('maps months, rounds to 2dp, sums totals', () => {
  const wp = buildWrittenPremium([
    { ym: '2026-07', n: 3, gwp: 100.005, gwpAnnual: 50.1 },
    { ym: '2026-06', n: 2, gwp: 200, gwpAnnual: null },
  ]);
  assert.equal(wp.byMonth.length, 2);
  assert.equal(wp.byMonth[0].month, '2026-07');
  assert.equal(wp.byMonth[0].count, 3);
  assert.equal(wp.byMonth[0].gwp, 100.01);      // rounded
  assert.equal(wp.byMonth[0].gwpAnnual, 50.1);
  assert.equal(wp.byMonth[1].gwpAnnual, null);  // null preserved, not coerced to 0
  assert.equal(wp.totalGwp, 300.01);
  assert.equal(wp.totalCount, 5);
});

check('empty / null rows → zeros, no throw', () => {
  const a = buildWrittenPremium([]);
  const b = buildWrittenPremium(null);
  assert.deepEqual(a, { byMonth: [], totalGwp: 0, totalCount: 0 });
  assert.deepEqual(b, { byMonth: [], totalGwp: 0, totalCount: 0 });
});

check('SQL is aggregate-only + carries the dedup/ISSUED/CANCEL safety logic', () => {
  const s = WRITTEN_PREMIUM_SQL;
  assert.ok(/MAX\(id\)/i.test(s), 'must dedup superseded rows by MAX(id)');
  assert.ok(/GROUP BY policy_id, effective_from, effective_to/i.test(s), 'dedup key = policy term period');
  assert.ok(/status = 'ISSUED'/i.test(s), 'ISSUED only');
  assert.ok(/deleted_at IS NULL/i.test(s), 'exclude soft-deleted');
  assert.ok(/CANCEL.*-ABS/i.test(s), 'CANCEL must contribute negative (premium reversal)');
  assert.ok(/GROUP BY ym/i.test(s), 'aggregate by month');
  assert.match(s, /^\s*SELECT/i, 'SELECT-only');
  assert.ok(!/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE)\b/i.test(s), 'no write keywords');
  // PII-free: no customer/name/policy-number columns selected.
  for (const bad of ['policyNumber', 'customer_id', 'customerName', 'omang', 'msisdn']) {
    assert.ok(!s.includes(bad), 'GWP SQL must not select ' + bad);
  }
});

check('KYC feed is filtered to the active book (status=1) — the queue-drop fix', () => {
  assert.ok(/EXISTS\s*\(\s*SELECT 1 FROM policies p WHERE p\.customer_id = k\.customer_id AND p\.status = 1/i.test(KYC_SQL),
    'KYC_SQL must require an in-force (status=1) policy for the customer');
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
