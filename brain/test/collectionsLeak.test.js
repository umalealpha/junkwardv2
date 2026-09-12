'use strict';
// collectionsLeak.test.js — PURE tests for the debit-on-a-dead-policy watch
// (no DB / no mysql2). Run: node test/collectionsLeak.test.js
const assert = require('node:assert');
const leak = require('../lib/collectionsLeak');

let pass = 0, fail = 0;
function check(n, fn) { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } }

// a raw SQL-shaped row (what fetchLeakingMandates hands buildLeakRows)
const R = (policyNumber, statusCode, recentAmount, recentDebits = 1, over = {}) => ({
  policyNumber, statusCode, product: 'Motor Instant',
  recentDebits, recentAmount, lastDebit: '2026-08-01', firstDebit: '2026-06-01', ...over,
});

// ── scope: MIS + ADH are both Instant (reuses collectionsRo.billingTypeFor) ──
check('MIS and ADH prefixes both resolve to billingType MIS', () => {
  const rows = leak.buildLeakRows([R('MIS2026207745', 0, 79), R('ADH2025100200', 0, 120)]);
  assert.equal(rows.length, 2);
  assert.ok(rows.every((r) => r.billingType === 'MIS'));
});

// ── status → severity/priority ranking (the core of the fix) ─────────────────
check('Cancelled still-debiting = critical, priority 92 (explicit cancel ignored)', () => {
  const [r] = leak.buildLeakRows([R('MIS2025000001', 2, 50)]);
  assert.equal(r.statusLabel, 'Cancelled');
  assert.equal(r.severity, 'critical');
  assert.equal(r.priority, 92);
});
check('Deactivated still-debiting = critical, priority 88 (the CFO deactivate-path root cause)', () => {
  const [r] = leak.buildLeakRows([R('MIS2025000002', 0, 50)]);
  assert.equal(r.statusLabel, 'Deactivated');
  assert.equal(r.severity, 'critical');
  assert.equal(r.priority, 88);
});
check('Expired still-debiting = high, priority 80', () => {
  const [r] = leak.buildLeakRows([R('MIS2025000003', 3, 50)]);
  assert.equal(r.statusLabel, 'Expired');
  assert.equal(r.severity, 'high');
  assert.equal(r.priority, 80);
});

// ── ordering: control-failure severity first, then biggest money leaked ──────
check('a Cancelled leak outranks a bigger Deactivated leak (severity before amount)', () => {
  const rows = leak.buildLeakRows([R('MIS-DEACT-BIG', 0, 9999), R('MIS-CANC-SMALL', 2, 10)]);
  assert.equal(rows[0].policyNumber, 'MIS-CANC-SMALL'); // priority 92 > 88
  assert.equal(rows[1].policyNumber, 'MIS-DEACT-BIG');
});
check('within the same status, the bigger money leak comes first', () => {
  const rows = leak.buildLeakRows([R('MIS-SMALL', 0, 100), R('MIS-BIG', 0, 5000)]);
  assert.equal(rows[0].policyNumber, 'MIS-BIG');
  assert.equal(rows[1].policyNumber, 'MIS-SMALL');
});

// ── money + date hygiene ─────────────────────────────────────────────────────
check('recentAmount is rounded to 2dp', () => {
  const [r] = leak.buildLeakRows([R('MIS-ROUND', 0, 79.005)]);
  assert.equal(r.recentAmount, 79.01);
});
check('lastDebit / firstDebit are normalised to YYYY-MM-DD', () => {
  const [r] = leak.buildLeakRows([R('MIS-DATE', 0, 50, 3, {
    lastDebit: '2026-08-01T00:00:00.000Z', firstDebit: new Date('2026-06-01T00:00:00Z'),
  })]);
  assert.equal(r.lastDebit, '2026-08-01');
  assert.equal(r.firstDebit, '2026-06-01');
});

// ── window + reason are self-explaining ──────────────────────────────────────
check('every row carries the 90-day window and a plain-English reason to stop the mandate', () => {
  const [r] = leak.buildLeakRows([R('MIS-WHY', 0, 246.5, 3)]);
  assert.equal(r.windowDays, 90);
  assert.equal(leak.WINDOW_DAYS, 90);
  assert.match(r.reason, /Deactivated/);
  assert.match(r.reason, /stop the mandate/);
  assert.match(r.reason, /3 successful debit/);
});

// ── empty / safety ───────────────────────────────────────────────────────────
check('empty input returns an empty list (no throw)', () => {
  assert.deepEqual(leak.buildLeakRows([]), []);
  assert.deepEqual(leak.buildLeakRows(), []);
});

// ── the read is SELECT-only and scoped to the dead book ──────────────────────
check('the SQL is read-only and only looks at inactive MIS/ADH policies with live mandates', () => {
  const sql = leak.LEAK_SQL.toUpperCase();
  assert.ok(sql.trim().startsWith('SELECT'));
  assert.ok(!/\b(INSERT|UPDATE|DELETE|DROP|ALTER)\b/.test(sql), 'must not contain a write verb');
  assert.ok(leak.LEAK_SQL.includes('p.status <> 1'), 'dead book only');
  assert.ok(leak.LEAK_SQL.includes("rci.InstalmentStatus = 'S'"), 'successful debits only');
  assert.ok(leak.LEAK_SQL.includes('rcc.status = 1'), 'mandate still active');
  assert.ok(/MIS%|ADH%/.test(leak.LEAK_SQL), 'scoped to Instant');
});

// ── integration: the sweep turns leak rows into actionable Finance items ─────
// The pure mapper above is only half the control. A bad leak row would break the
// WHOLE nightly sweep (every team), so lock the wiring, not just the mapper.
const brains = require('../brains');
(async () => {
  const rows = leak.buildLeakRows([R('MIS2024000999', 0, 246.50, 3)]);
  const q = await brains.sweep({ leaking: rows });
  const fin = q.teams.Finance || [];
  const summary = fin.filter((x) => x.id === 'collections-leak:summary');
  const items = fin.filter((x) => x.id.startsWith('collections-leak:')
    && x.id !== 'collections-leak:summary' && x.id !== 'collections-leak:unavailable');
  check('sweep emits exactly one leak summary + one actionable item per policy', () => {
    assert.equal(summary.length, 1);
    assert.equal(items.length, 1);
    assert.equal(items[0].id, 'collections-leak:MIS2024000999');
    assert.equal(items[0].needsApproval, true); // a human must action it (stop the mandate)
  });

  // regression tripwire for the PII fix: even a name riding in on a row must
  // never reach a queue title/detail the console renders.
  const qPii = await brains.sweep({ leaking: [{
    policyNumber: 'MIS-PII', statusLabel: 'Deactivated', billingType: 'MIS',
    recentDebits: 1, recentAmount: 50, lastDebit: '2026-08-01', windowDays: 90,
    priority: 88, customerName: 'SENTINEL_NAME_XYZ',
  }] });
  check('a customer name on a leak row never reaches the queue title or detail', () => {
    for (const x of (qPii.teams.Finance || [])) {
      assert.ok(!String(x.title || '').includes('SENTINEL_NAME_XYZ'));
      assert.ok(!String(x.detail || '').includes('SENTINEL_NAME_XYZ'));
    }
  });

  // control-outage visibility: a failed pull must surface its OWN item, not vanish.
  const qFail = await brains.sweep({ leaking: [], leakPullFailed: 'connect ETIMEDOUT' });
  check('a failed leak pull surfaces a visible "coverage degraded" item, never silent', () => {
    const un = (qFail.teams.Finance || []).filter((x) => x.id === 'collections-leak:unavailable');
    assert.equal(un.length, 1);
    assert.equal(un[0].needsApproval, false);
  });

  console.log(`\ncollectionsLeak: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})();
