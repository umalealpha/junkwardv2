'use strict';
// Unit test for lib/commissionGuard.js — run: node test/commissionGuard.test.js
const assert = require('node:assert');
const { scan, checkAcquisition } = require('../lib/commissionGuard');

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

// The CFO's example: one card funding 5 unrelated customers under one agent.
const farm = [
  { cardToken: 'CARD-P', customerId: 'Motel',   agentId: 'AG-1', policyNumber: 'IIG-1', amount: 150 },
  { cardToken: 'CARD-P', customerId: 'Artsi',   agentId: 'AG-1', policyNumber: 'IIG-2', amount: 150 },
  { cardToken: 'CARD-P', customerId: 'Molifai', agentId: 'AG-1', policyNumber: 'IIG-3', amount: 150 },
  { cardToken: 'CARD-P', customerId: 'Pako',    agentId: 'AG-1', policyNumber: 'IIG-4', amount: 150 },
  { cardToken: 'CARD-P', customerId: 'Kago',    agentId: 'AG-1', policyNumber: 'IIG-5', amount: 150 },
  // a legitimate card: one payer, two policies for the SAME customer
  { cardToken: 'CARD-OK', customerId: 'Neo', agentId: 'AG-2', policyNumber: 'IIG-6', amount: 150 },
  { cardToken: 'CARD-OK', customerId: 'Neo', agentId: 'AG-2', policyNumber: 'IIG-7', amount: 150 },
];

check("THE CASE: one card → 5 customers → flagged, commission held, card masked", () => {
  const r = scan(farm);
  assert.equal(r.flaggedCards.length, 1);
  assert.equal(r.flaggedCards[0].distinctCustomers, 5);
  assert.ok(r.flaggedCards[0].cardToken.startsWith('…'), 'card token must be masked');
  assert.equal(r.commissionHold.length, 5);            // all 5 commissions held
});

check('the farming agent is flagged', () => {
  const r = scan(farm);
  assert.ok(r.flaggedAgents.some((a) => a.agentId === 'AG-1'));
});

check('legit card (one customer, two policies) is NOT flagged', () => {
  const r = scan(farm);
  assert.ok(!r.flaggedCards.some((c) => c.customers.includes('Neo')));
  assert.ok(!r.commissionHold.some((h) => h.policyNumber === 'IIG-6'));
});

check('cover is never blocked — only the commission is held', () => {
  const r = scan(farm);
  // the guard output holds commission; there is no "block cover" instruction
  assert.ok(r.commissionHold.every((h) => /commission/i.test(h.reason)));
});

check('real-time: 4th customer on a 3-limit card → hold commission, cover still allowed', () => {
  const history = [
    { cardToken: 'C1', customerId: 'a' }, { cardToken: 'C1', customerId: 'b' }, { cardToken: 'C1', customerId: 'c' },
  ];
  const r = checkAcquisition({ cardToken: 'C1', customerId: 'd' }, history);
  assert.equal(r.allowCover, true);
  assert.equal(r.holdCommission, true);
  assert.equal(r.distinctCustomersOnCard, 4);
});

check('real-time: same customer paying again on their card → ok', () => {
  const history = [{ cardToken: 'C2', customerId: 'x' }, { cardToken: 'C2', customerId: 'x' }];
  const r = checkAcquisition({ cardToken: 'C2', customerId: 'x' }, history);
  assert.equal(r.holdCommission, false);
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
