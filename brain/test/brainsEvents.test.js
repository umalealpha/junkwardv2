'use strict';
// brainsEvents.test.js — the events branch must NOT flood the queue. Thousands
// of ROUTINE (no-approval) events collapse to ONE summary item per type. This
// fixes the incident where ~50k daily payment_received events produced a 51k-item
// queue.json and OOM-killed the container. Nothing is dropped — the summary
// carries the full count.
const assert = require('node:assert');
const brains = require('../brains');

let pass = 0, fail = 0;
async function check(n, fn) { try { await fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } }

(async () => {
  await check('thousands of routine payment_received collapse to ONE summary (no flood)', async () => {
    const events = [];
    for (let i = 0; i < 3000; i++) events.push({ event: 'payment_received', ctx: { policyNumber: 'P' + i, amount: 100 } });
    const q = await brains.sweep({ events });
    const items = Object.values(q.teams).flat().filter((x) => x.domain === 'event');
    assert.equal(items.length, 1, 'expected a single summary item, got ' + items.length);
    assert.ok(String(items[0].id).startsWith('event-summary:payment_received'), 'must be the summary item');
    assert.equal(items[0].count, 3000, 'summary must preserve the full count (nothing dropped)');
    assert.equal(items[0].needsApproval, false);
  });

  await check('no events → no event items', async () => {
    const q = await brains.sweep({ events: [] });
    assert.equal(Object.values(q.teams).flat().filter((x) => x.domain === 'event').length, 0);
  });

  console.log('\n' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})();
