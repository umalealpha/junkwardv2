'use strict';
// Unit test for lib/smartClaim.js — run: node test/smartClaim.test.js
// Injects a STUB ai client so the tests are deterministic and hit no network,
// and asserts (a) deterministic-first, (b) AI never overrides the rule,
// (c) no PII is ever put in an AI prompt.
const assert = require('node:assert');
const { classifyClaimType, assessmentRoute } = require('../lib/smartClaim');

const PII = [/@/, /\b\d{9}\b/, /\b\d{10,}\b/];
function stub(answer, jsonAnswer) {
  const calls = [];
  return {
    calls,
    ask: async (p) => { calls.push(p); return answer; },
    askJSON: async (p) => { calls.push(p); return jsonAnswer; },
  };
}
const noPII = (calls) => calls.every((p) => !PII.some((re) => re.test(String(p))));

let pass = 0, fail = 0;
async function check(n, fn) {
  try { await fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); }
}

(async () => {
  await check('known label → rule wins, AI not called', async () => {
    const ai = stub('glass');
    const r = await classifyClaimType('Motor Accident', { ai });
    assert.equal(r.source, 'rule');
    assert.equal(r.type, 'motor accident');
    assert.equal(ai.calls.length, 0);
  });

  await check('unknown label → AI fallback picks a VALID type', async () => {
    const ai = stub('glass');
    const r = await classifyClaimType('cracked windshield thing', { ai });
    assert.equal(r.source, 'ai');
    assert.equal(r.type, 'glass');
    assert.ok(noPII(ai.calls), 'PII in AI prompt');
  });

  await check('AI returns junk / NONE → no type (cannot invent)', async () => {
    assert.equal((await classifyClaimType('zxcv', { ai: stub('NONE') })).type, null);
    assert.equal((await classifyClaimType('zxcv', { ai: stub('totally made up type') })).type, null);
  });

  await check('AI unavailable (null) → graceful none', async () => {
    const r = await classifyClaimType('zxcv', { ai: stub(null) });
    assert.equal(r.type, null);
    assert.equal(r.source, 'none');
  });

  await check('route: 80% of SI → total_loss (deterministic)', async () => {
    const r = await assessmentRoute({ repairCost: 40000, sumInsured: 50000 }, { ai: stub(null, null) });
    assert.equal(r.route, 'total_loss');
  });

  await check('route: 30% of SI → repair', async () => {
    const r = await assessmentRoute({ repairCost: 15000, sumInsured: 50000 }, { ai: stub(null, null) });
    assert.equal(r.route, 'repair');
  });

  await check('route: no sum insured → refer', async () => {
    const r = await assessmentRoute({ repairCost: 15000, sumInsured: 0 }, { ai: stub(null, null) });
    assert.equal(r.route, 'refer');
  });

  await check('AI second opinion never overrides the rule', async () => {
    const ai = stub(null, { route: 'repair', reason: 'AI disagrees' });
    const r = await assessmentRoute({ repairCost: 45000, sumInsured: 50000 }, { ai });
    assert.equal(r.route, 'total_loss');        // deterministic still wins
    assert.equal(r.ai.route, 'repair');         // AI opinion recorded, not applied
    assert.ok(noPII(ai.calls), 'PII in AI prompt');
  });

  console.log('\n' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})();
