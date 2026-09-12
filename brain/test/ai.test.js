'use strict';
// Unit test for lib/ai.js — run: node test/ai.test.js
const assert = require('node:assert');
delete process.env.AI_GATEWAY_URL;           // unconfigured → graceful-skip path
const ai = require('../lib/ai');

let pass = 0, fail = 0;
async function check(n, fn) {
  try { await fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); }
}

(async () => {
  await check('PII guard flags an email', () => assert.equal(ai.containsLikelyPII('write to neo@example.com'), true));
  await check('PII guard flags a 9-digit Omang', () => assert.equal(ai.containsLikelyPII('id 481203456'), true));
  await check('PII guard flags a long account number', () => assert.equal(ai.containsLikelyPII('acct 62012345678'), true));
  await check('PII guard flags a spaced card number', () => assert.equal(ai.containsLikelyPII('4111 1111 1111 1111'), true));
  await check('PII guard passes safe masked numbers', () => assert.equal(ai.containsLikelyPII('arrearsMonths 4 amountOwing 3750 daysUnpaid 99'), false));
  await check('isConfigured false when no gateway set', () => assert.equal(ai.isConfigured(), false));
  await check('ask returns null when unconfigured (graceful skip)', async () => assert.equal(await ai.ask('repairCost 5000 sumInsured 50000'), null));
  await check('askJSON returns null when unconfigured', async () => assert.equal(await ai.askJSON('give me json'), null));

  // ── Botswana phone coverage (CFO 2026-07-21) ───────────────────────────────
  await check('BW mobile 8-digit local format is treated as PII', () => {
    assert.equal(ai.containsLikelyPII('call Thabo on 71234567'), true);
    assert.equal(ai.containsLikelyPII('call on 71 234 567'), true);
  });
  await check('+267 formats are treated as PII', () => {
    assert.equal(ai.containsLikelyPII('+267 71 234 567'), true);
    assert.equal(ai.containsLikelyPII('26771234567'), true);
  });
  await check('plain amounts and short numbers still pass', () => {
    assert.equal(ai.containsLikelyPII('repairCost=54000 sumInsured=300000 ratio=18%'), false);
  });

  console.log('\n' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})();
