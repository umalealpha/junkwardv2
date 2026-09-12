'use strict';
/**
 * Regression tests for the motolink.app assessment bridge (motolink.js).
 * Pure Node built-ins (assert) — no new deps. Uses MOCK fixtures + a throwaway
 * SQLite DB. Run: `npm test` (or `node test/motolink.test.js`).
 *
 * Covers the safety guarantees that matter: matching, write-off flagging,
 * fill-if-empty, NO-clobber of manual entry, graphite_claim_number fallback,
 * idempotent re-sync, and the unmatched control.
 */

process.env.MOTOLINK_MOCK = '1';
process.env.DB_PATH = `/tmp/motolink_test_${process.pid}.db`;

const assert = require('assert');
const fs = require('fs');
const clean = () => ['', '-shm', '-wal'].forEach(s => { try { fs.unlinkSync(process.env.DB_PATH + s); } catch (_) {} });
clean();

const { db } = require('../db');
const motolink = require('../motolink');

const insClaim = db.prepare(
  `INSERT INTO claims (claim_id, claim_number, graphite_claim_number, contract_pricing_value)
   VALUES (@id, @cn, @gcn, @cv)`
);
const reset = () => db.prepare('DELETE FROM claims').run();
const seed  = (id, cn, gcn = '', cv = 0) => insClaim.run({ id, cn, gcn, cv });
const get   = (cn) => db.prepare('SELECT * FROM claims WHERE claim_number = ? OR graphite_claim_number = ?').get(cn, cn);

let passed = 0, failed = 0;
async function t(name, fn) {
  try { await fn(); console.log('  ✓', name); passed++; }
  catch (e) { console.error('  ✗', name, '\n     ', e.message); failed++; process.exitCode = 1; }
}

(async () => {
  console.log('normalizeAssessment()');
  await t('maps snake_case keys and flags total loss', () => {
    const n = motolink.normalizeAssessment({ claim_number: 'G1', final_cost: '1200', status: 'Total Loss', reg: 'B1ABC' });
    assert.strictEqual(n.claimNumber, 'G1');
    assert.strictEqual(n.finalCost, 1200);
    assert.strictEqual(n.totalLoss, 1);
    assert.strictEqual(n.registration, 'B1ABC');
    assert.ok(n.writeOffAlert, 'write-off alert should be set for a total loss');
  });
  await t('maps camelCase keys, no false total loss', () => {
    const n = motolink.normalizeAssessment({ claimNumber: 'G2', finalCost: 50, status: 'Authorised' });
    assert.strictEqual(n.totalLoss, 0);
    assert.strictEqual(n.finalCost, 50);
    assert.strictEqual(n.writeOffAlert, '');
  });

  console.log('syncOnce() — mock fixtures');
  await t('matches claims and flags the write-off', async () => {
    reset(); seed('a', 'G2026004985'); seed('b', 'G2026004771');
    const r = await motolink.syncOnce();
    assert.ok(r.matched >= 2, `expected >=2 matched, got ${r.matched}`);
    assert.strictEqual(get('G2026004771').motolink_total_loss, 1);
    assert.strictEqual(get('G2026004985').motolink_status, 'Authorised');
  });
  await t('fill-if-EMPTY populates contract_pricing_value', async () => {
    reset(); seed('a', 'G2026004985', '', 0);
    await motolink.syncOnce();
    assert.strictEqual(get('G2026004985').contract_pricing_value, 18450);
  });
  await t('NO-clobber: a manual contract value is preserved', async () => {
    reset(); seed('a', 'G2026004985', '', 999);
    await motolink.syncOnce();
    const c = get('G2026004985');
    assert.strictEqual(c.contract_pricing_value, 999, 'manual value must NOT be overwritten');
    assert.strictEqual(c.motolink_final_cost, 18450, 'motolink mirror still records the assessment cost');
  });
  await t('matches on graphite_claim_number fallback', async () => {
    reset(); seed('a', '', 'G2026004771');
    await motolink.syncOnce();
    assert.strictEqual(get('G2026004771').motolink_total_loss, 1);
  });
  await t('idempotent re-sync (stable, no errors)', async () => {
    reset(); seed('a', 'G2026004985');
    const r1 = await motolink.syncOnce();
    const r2 = await motolink.syncOnce();
    assert.strictEqual(r1.errors, 0);
    assert.strictEqual(r2.errors, 0);
    assert.strictEqual(r2.matched, r1.matched, 're-sync should match the same count');
  });
  await t('unmatched assessments are reported, not matched', async () => {
    reset(); // no claims at all
    const r = await motolink.syncOnce();
    assert.strictEqual(r.matched, 0);
    assert.ok(r.unmatched.length >= 1, 'expected unmatched assessments');
  });

  clean();
  console.log(`\n${passed} passed, ${failed} failed`);
  if (failed) process.exit(1);
})();
