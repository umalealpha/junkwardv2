'use strict';
// omniPush.test.js — the OUTBOUND Graphite→Omni analytics push (T-push) must be
// DEAD (a pure no-op, no network) unless BOTH locks are satisfied:
//   1. an ingest key is present, and
//   2. the push is armed (BRAIN_OMNI_PUSH_ARMED === 'true') — its OWN arm,
//      DECOUPLED from BRAIN_LIVE_ARMS (CFO 2026-08-31) so analytics can flow to
//      Omni while cancellations / SMS stay off.
// No network is ever exercised on the locked paths: the no-key and disarmed paths
// must return BEFORE any fetch. We assert global.fetch is never called there, that
// the push PROCEEDS when armed even with live arms off (the decouple), and that
// the Appendix-A v1 envelope shape + PII scrub are correct by construction.

const assert = require('node:assert');

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };
const acheck = async (n, fn) => { try { await fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

// Fail loudly if any code path under test tries to hit the network.
const origFetch = global.fetch;
let fetchCalls = 0;
global.fetch = async () => { fetchCalls++; throw new Error('fetch must NOT be called in this test'); };

const omniPush = require('../lib/omniPush');

const SAMPLE = {
  generatedAt: '2026-08-06T05:45:00Z',
  claims_by_group: [{ group: 'Motor', claims: 12, incurred: 340000 }],
  premium_by_group: [{ group: 'Motor', premium: 900000 }],
  renewals_trigger: [{ product: 'DOMG', due: 5 }],
};

(async () => {
  // LOCK 1 — no key → hard no-op, no fetch.
  await acheck('no key → skipped no_ingest_key (no network)', async () => {
    delete process.env.BRAIN_LIVE_ARMS; // even irrelevant here
    const r = await omniPush.pushAnalyticsToOmni({ url: 'https://omni.example/ingest', key: '', snapshot: SAMPLE });
    assert.equal(r.ok, false);
    assert.equal(r.skipped, true);
    assert.equal(r.reason, 'no_ingest_key');
    assert.equal(fetchCalls, 0, 'must not touch the network without a key');
  });

  // LOCK 1 — no url → hard no-op too.
  await acheck('no url → skipped no_ingest_key', async () => {
    const r = await omniPush.pushAnalyticsToOmni({ url: undefined, key: 'k', snapshot: SAMPLE });
    assert.equal(r.reason, 'no_ingest_key');
    assert.equal(fetchCalls, 0);
  });

  // LOCK 2 — key present but push DISARMED → skipped push_disarmed, still no fetch.
  await acheck('key present + push DISARMED → skipped push_disarmed (no network)', async () => {
    process.env.BRAIN_OMNI_PUSH_ARMED = 'false';
    const r = await omniPush.pushAnalyticsToOmni({ url: 'https://omni.example/ingest', key: 'secret', snapshot: SAMPLE });
    assert.equal(r.ok, false);
    assert.equal(r.skipped, true);
    assert.equal(r.reason, 'push_disarmed');
    assert.equal(fetchCalls, 0, 'disarmed must short-circuit before any send');
  });

  // LOCK 2 — push-arm unset (not exactly 'true') is also OFF.
  await acheck('push-arm unset → skipped push_disarmed', async () => {
    delete process.env.BRAIN_OMNI_PUSH_ARMED;
    const r = await omniPush.pushAnalyticsToOmni({ url: 'https://omni.example/ingest', key: 'secret', snapshot: SAMPLE });
    assert.equal(r.reason, 'push_disarmed');
    assert.equal(fetchCalls, 0);
  });

  // DECOUPLE — live arms OFF but the push is ARMED + key present → it PROCEEDS
  // and POSTs (proves the analytics push no longer depends on BRAIN_LIVE_ARMS).
  await acheck('DECOUPLED: live arms OFF + push ARMED + key → proceeds and POSTs', async () => {
    process.env.BRAIN_LIVE_ARMS = 'false';     // cancellations/SMS stay off
    process.env.BRAIN_OMNI_PUSH_ARMED = 'true'; // analytics push on
    const savedFetch = global.fetch;
    let posts = 0;
    global.fetch = async () => { posts++; return { ok: true, status: 202 }; };
    const r = await omniPush.pushAnalyticsToOmni({ url: 'https://omni.example/ingest', key: 'secret', snapshot: SAMPLE });
    global.fetch = savedFetch;                   // restore the throwing stub
    assert.ok(posts > 0, 'armed push + key must actually POST even with live arms off');
    assert.equal(r.ok, true);
    assert.ok(r.sent.length >= 1, 'at least one dataset should be sent');
    process.env.BRAIN_OMNI_PUSH_ARMED = 'false'; // reset so later tests keep no-fetch semantics
  });

  // Envelope shape (pure builder — no network): Appendix-A v1 contract.
  check('buildEnvelope emits the Appendix-A v1 contract', () => {
    const { envelope } = omniPush.buildEnvelope('claims_by_group', SAMPLE.claims_by_group, SAMPLE.generatedAt);
    assert.equal(envelope.schema_version, 'v1');
    assert.equal(envelope.dataset, 'claims_by_group');
    assert.equal(envelope.generated_at, '2026-08-06T05:45:00Z');
    assert.equal(envelope.fy, 'FY27');
    assert.deepEqual(envelope.basis, { currency: 'BWP', vat_pct: 14, premium_basis: 'ex_vat' });
    assert.ok(Array.isArray(envelope.rows));
    assert.deepEqual(envelope.rows[0], { group: 'Motor', claims: 12, incurred: 340000 });
  });

  // PII scrub — a stray identifier key is dropped defensively.
  check('scrubRow drops PII-named keys, keeps counts', () => {
    const { row, dropped } = omniPush.scrubRow({ group: 'Motor', claims: 3, customer_name: 'Jane', omang: '123', policy_number: 'DOMG1' });
    assert.equal(row.group, 'Motor');
    assert.equal(row.claims, 3);
    for (const k of ['customer_name', 'omang', 'policy_number']) {
      assert.ok(!(k in row), 'must drop PII key ' + k);
      assert.ok(dropped.includes(k), 'must report dropped key ' + k);
    }
  });

  // Omni-parity scrub — mirror the receiver's screen so a push can never 422.
  check('scrubRow mirrors Omni: drops banned names, nested values, and PII value-shapes', () => {
    const { row, dropped } = omniPush.scrubRow({
      branch: 'Gaborone',          // keep — plain label
      policies: 42,                // keep — count
      policyholder: 'ACME (Pty)',  // Omni banned NAME (our regex misses it)
      next_of_kin: 'x',            // Omni banned NAME
      detail: { a: 1 },            // nested — Omni rejects "flat rows only"
      ref: 'someone@x.co.bw',      // value-shape: email
      code: '123456789',           // value-shape: 9-digit Omang
      acc: '0123456789012',        // value-shape: long number
    });
    assert.equal(row.branch, 'Gaborone');
    assert.equal(row.policies, 42);
    for (const k of ['policyholder', 'next_of_kin', 'detail', 'ref', 'code', 'acc']) {
      assert.ok(!(k in row), 'must drop ' + k);
      assert.ok(dropped.includes(k), 'must report dropped ' + k);
    }
  });

  global.fetch = origFetch;
  console.log('\n' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})();
