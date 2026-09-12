'use strict';
// Unit test for lib/ageing.js — run: node test/ageing.test.js
const assert = require('node:assert');
const { age } = require('../lib/ageing');

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

// ── THE CFO's EXAMPLE (the bug we are fixing) ────────────────────────────────
check("CFO case: invoice 1 Jan paid 30 Mar → as at 1 Apr the account is CLEAR", () => {
  const r = age(
    [{ date: '2026-01-01', amount: 500 }],
    [{ date: '2026-03-30', amount: 500 }],
    '2026-04-01'
  );
  assert.equal(r.balance, 0);            // net zero
  assert.equal(r.buckets.b90, 0);        // NOT sitting in 90 days
  assert.equal(r.buckets.b60, 0);
  assert.equal(r.buckets.current, 0);    // payment is NOT a stray "current"
  assert.equal(r.unappliedCredit, 0);
  assert.equal(r.openInvoices.length, 0);
  assert.equal(r.allocations.length, 1); // the payment was applied to the invoice
});

check('partial payment: remainder stays aged by the INVOICE date', () => {
  const r = age([{ date: '2026-01-01', amount: 500 }], [{ date: '2026-03-30', amount: 200 }], '2026-04-01');
  assert.equal(r.balance, 300);
  assert.equal(r.buckets.b60, 300);      // 1 Jan → 1 Apr = 90 days → 61–90 band
  assert.equal(r.buckets.current, 0);
});

check('unpaid invoice ages correctly', () => {
  const r = age([{ date: '2026-01-01', amount: 500 }], [], '2026-04-01');
  assert.equal(r.balance, 500);
  assert.equal(r.buckets.b60, 500);
});

check('FIFO: one payment clears the oldest invoice first', () => {
  const r = age(
    [{ date: '2026-01-01', amount: 300 }, { date: '2026-02-01', amount: 400 }],
    [{ date: '2026-03-30', amount: 500 }],
    '2026-04-01'
  );
  // 500 clears Jan(300) + 200 of Feb → Feb open 200, aged by Feb date
  assert.equal(r.balance, 200);
  const feb = r.openInvoices.find((i) => i.date === '2026-02-01');
  assert.equal(feb.open, 200);
});

check('reference match beats FIFO', () => {
  const r = age(
    [{ date: '2026-01-01', amount: 300, ref: 'A' }, { date: '2026-02-01', amount: 400, ref: 'B' }],
    [{ date: '2026-03-30', amount: 400, ref: 'B' }],
    '2026-04-01'
  );
  // pays B specifically → A (older) still fully open
  const a = r.openInvoices.find((i) => i.ref === 'A');
  assert.equal(a.open, 300);
  assert.equal(r.balance, 300);
});

check('overpayment → unapplied credit, not a positive current bucket', () => {
  const r = age([{ date: '2026-03-01', amount: 200 }], [{ date: '2026-03-30', amount: 500 }], '2026-04-01');
  assert.equal(r.unappliedCredit, 300);
  assert.equal(r.balance, -300);         // in credit
  assert.equal(r.openInvoices.length, 0);
});

check('fresh invoice sits in current', () => {
  const r = age([{ date: '2026-03-20', amount: 100 }], [], '2026-04-01');
  assert.equal(r.buckets.current, 100);
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
