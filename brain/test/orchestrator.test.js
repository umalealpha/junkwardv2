'use strict';
// Unit test for lib/orchestrator.js — run: node test/orchestrator.test.js
// Injects stub capabilities + a recording intent sink. Asserts: local actions run,
// money/irreversible actions are QUEUED (never auto-done), cross-system actions
// emit correctly-targeted intents, MotoLink stays blocked until keys.
const assert = require('node:assert');
const { handleEvent, systemFor } = require('../lib/orchestrator');

function harness() {
  const notified = [], alerts = [], intents = [];
  return {
    notified, alerts, intents,
    deps: {
      notifyCustomer: async ({ customerMsg }) => notified.push(customerMsg),
      sendAlert: async (verb) => alerts.push(verb),
      emitIntent: (i) => intents.push(i),
    },
  };
}

let pass = 0, fail = 0;
async function check(n, fn) {
  try { await fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); }
}

(async () => {
  await check('systemFor routes actions to the right system', () => {
    assert.equal(systemFor('stop_premium_collection'), 'omni');
    assert.equal(systemFor('remove_asset_from_cover'), 'graphite');
    assert.equal(systemFor('draft_settlement_je'), 'omni');
    assert.equal(systemFor('open_salvage_file'), 'graphite');
  });

  await check('write-off: customer notified, money QUEUED not executed, arms emitted', async () => {
    const h = harness();
    const r = await handleEvent('total_loss', { claimNumber: 'G2026000123', policyNumber: 'COMG1', amount: 90000 }, h.deps);
    // customer told
    assert.ok(r.executed.includes('notify_customer'));
    assert.ok(h.notified.includes('agreement_of_loss_to_sign'));
    // money / irreversible queued for a human, NOT executed
    assert.ok(r.queuedForApproval.includes('draft_settlement_je'));
    assert.ok(r.queuedForApproval.includes('recover_outstanding_premium_from_settlement'));
    assert.ok(!r.executed.includes('draft_settlement_je'), 'JE must not auto-run');
    // cross-system arms emitted as intents
    const actions = r.intents.map((i) => i.action);
    assert.ok(actions.includes('stop_premium_collection'));
    assert.ok(actions.includes('remove_asset_from_cover'));
    // approval intents carry the right status
    const je = r.intents.find((i) => i.action === 'draft_settlement_je');
    assert.equal(je.status, 'awaiting_approval');
    // intent carries the internal reference the adapter needs
    assert.equal(je.payload.claimNumber, 'G2026000123');
  });

  await check('irreversible total-loss actions WAIT for approval (CFO 2026-07-13)', async () => {
    const h = harness();
    const r = await handleEvent('total_loss', { claimNumber: 'G1' }, h.deps);
    // stop-collection + remove-from-cover are irreversible → approval-gated now.
    assert.ok(r.queuedForApproval.includes('stop_premium_collection'));
    assert.ok(r.queuedForApproval.includes('remove_asset_from_cover'));
    const stop = r.intents.find((i) => i.action === 'stop_premium_collection');
    assert.equal(stop.system, 'omni');
    assert.equal(stop.status, 'awaiting_approval');
  });

  await check('simple event: just notifies the customer, no intents', async () => {
    const h = harness();
    const r = await handleEvent('claim_registered', { claimNumber: 'G2' }, h.deps);
    assert.deepEqual(r.executed, ['notify_customer']);
    assert.equal(r.intents.length, 0);
    assert.deepEqual(h.notified, ['claim_received']);
  });

  await check('missing capability → recorded as skipped, nothing silently lost', async () => {
    const r = await handleEvent('claim_registered', {}, { emitIntent: () => {} }); // no notifyCustomer
    assert.ok(r.skipped.includes('notify_customer'));
  });

  await check('unknown event → unknown flag, no actions', async () => {
    const r = await handleEvent('nonsense', {}, harness().deps);
    assert.equal(r.unknown, true);
    assert.equal(r.intents.length, 0);
  });

  console.log('\n' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})();
