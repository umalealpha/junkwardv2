'use strict';
// dashboardSlim.test.js — the dashboard snapshot (store.slimQueue) must keep
// /api/queue + /compliance-summary SMALL and fast: cap items per team, strip
// nested payloads, but keep FULL counts and a FULL per-team by-domain breakdown.
// The raw queue.json is 100MB+; serving it jams the event loop past the proxy
// timeout — this snapshot is what prevents that.
const assert = require('node:assert');
const store = require('../store');
const { complianceSummary } = require('../compliance-summary');

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

// A "fat" queue: many items per team, each carrying heavy nested blobs.
const fatItem = (i, team, domain) => ({
  id: `${domain}:${i}`, domain, team, priority: i % 100,
  title: `t${i}`, detail: `d${i}`, ref: `r${i}`, needsApproval: false,
  aging: { blob: 'x'.repeat(1000) }, kyc: { blob: 'y'.repeat(1000) }, intents: [1, 2, 3],
});
const queue = {
  generatedAt: 'T', source: 'src', liveArms: false, total: 300,
  counts: { Finance: 200, Compliance: 100 },
  teams: {
    Finance: Array.from({ length: 200 }, (_, i) => fatItem(i, 'Finance', i < 120 ? 'debtors' : 'collections')),
    Compliance: Array.from({ length: 100 }, (_, i) => fatItem(i, 'Compliance', 'kyc')),
  },
};

check('caps items per team to top-N', () => {
  const s = store.slimQueue(queue, 50);
  assert.equal(s.teams.Finance.length, 50);
  assert.equal(s.teams.Compliance.length, 50);
  assert.equal(s.perTeamCap, 50);
});

check('strips nested payloads — display fields only', () => {
  const it = store.slimQueue(queue, 10).teams.Finance[0];
  assert.deepEqual(Object.keys(it).sort(),
    ['detail', 'domain', 'id', 'needsApproval', 'priority', 'ref', 'team', 'title']);
  assert.ok(!('aging' in it) && !('kyc' in it) && !('intents' in it));
});

check('keeps FULL counts + total (not capped)', () => {
  const s = store.slimQueue(queue, 10);
  assert.equal(s.total, 300);
  assert.deepEqual(s.counts, { Finance: 200, Compliance: 100 });
});

check('byDomain counts ALL items, not just the top-N slice', () => {
  const s = store.slimQueue(queue, 10);
  assert.equal(s.byDomain.Finance.debtors, 120);
  assert.equal(s.byDomain.Finance.collections, 80);
  assert.equal(s.byDomain.Compliance.kyc, 100);
});

check('snapshot is dramatically smaller than the raw queue', () => {
  const fat = JSON.stringify(queue).length;
  const slim = JSON.stringify(store.slimQueue(queue, 100)).length;
  assert.ok(slim < fat / 3, `slim ${slim} should be far smaller than fat ${fat}`);
});

check('complianceSummary uses snapshot byDomain (full count, not capped items)', () => {
  const snap = store.slimQueue(queue, 10); // only 10 Compliance items in the list
  const out = complianceSummary(snap, null);
  assert.equal(out.kyc.by_domain.kyc, 100); // full 100 via byDomain, not 10
  assert.equal(out.total, 300);
});

check('complianceSummary from snapshot stays PII-free (no refs/details/ids)', () => {
  const s = JSON.stringify(complianceSummary(store.slimQueue(queue, 10), null));
  for (const leak of ['r0', 'd0', 'kyc:0', 'debtors:0', 'collections:0']) {
    assert.ok(!s.includes(leak), 'payload must not leak: ' + leak);
  }
});

check('back-compat: a full queue with no byDomain still counts items', () => {
  const legacy = { total: 3, counts: { Compliance: 2 }, teams: { Compliance: [{ domain: 'kyc' }, { domain: 'kyc' }] } };
  assert.equal(complianceSummary(legacy, null).kyc.by_domain.kyc, 2);
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
