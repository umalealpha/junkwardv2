'use strict';
// Unit test for lib/payerGuard.js — run: node test/payerGuard.test.js
const assert = require('node:assert');
const { scanExceptions, checkPayment } = require('../lib/payerGuard');

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

check('husband pays for wife (2 customers) → NOT flagged (family allowance)', () => {
  const r = scanExceptions([
    { instrumentType: 'bank', instrumentToken: '62001234', customerId: 'Husband', policyNumber: 'D-1', product: 'domestic' },
    { instrumentType: 'bank', instrumentToken: '62001234', customerId: 'Wife',    policyNumber: 'D-2', product: 'domestic' },
  ]);
  assert.equal(r.exceptions.length, 0);
});

check('one bank account → 3 different customers → EXCEPTION for review (not blocked)', () => {
  const r = scanExceptions([
    { instrumentType: 'bank', instrumentToken: '62009999', customerId: 'A', policyNumber: 'I-1', product: 'instant' },
    { instrumentType: 'bank', instrumentToken: '62009999', customerId: 'B', policyNumber: 'I-2', product: 'instant' },
    { instrumentType: 'bank', instrumentToken: '62009999', customerId: 'C', policyNumber: 'C-1', product: 'commercial' },
  ]);
  assert.equal(r.exceptions.length, 1);
  const e = r.exceptions[0];
  assert.equal(e.distinctCustomers, 3);
  assert.equal(e.status, 'review');                 // exception, not a block
  assert.deepEqual(e.customers.sort(), ['A', 'B', 'C']);
});

check('bank account is masked to last 4 (PII never shown in full)', () => {
  const r = scanExceptions([
    { instrumentType: 'bank', instrumentToken: '620012345678', customerId: 'A', policyNumber: 'x' },
    { instrumentType: 'bank', instrumentToken: '620012345678', customerId: 'B', policyNumber: 'y' },
    { instrumentType: 'bank', instrumentToken: '620012345678', customerId: 'C', policyNumber: 'z' },
  ]);
  assert.ok(r.exceptions[0].instrument.startsWith('…'));
  assert.ok(!r.exceptions[0].instrument.includes('620012345678'));
});

check('spans products (domestic / commercial / instant)', () => {
  const r = scanExceptions([
    { instrumentType: 'bank', instrumentToken: 'ACC', customerId: 'A', policyNumber: '1', product: 'domestic' },
    { instrumentType: 'bank', instrumentToken: 'ACC', customerId: 'B', policyNumber: '2', product: 'commercial' },
    { instrumentType: 'bank', instrumentToken: 'ACC', customerId: 'C', policyNumber: '3', product: 'instant' },
  ]);
  assert.deepEqual(r.exceptions[0].products.sort(), ['commercial', 'domestic', 'instant']);
});

check('real-time: 3rd customer on an account → raise exception, collection still allowed', () => {
  const history = [{ instrumentToken: 'ACC', customerId: 'A' }, { instrumentToken: 'ACC', customerId: 'B' }];
  const r = checkPayment({ instrumentToken: 'ACC', customerId: 'C' }, history);
  assert.equal(r.allowCollection, true);
  assert.equal(r.raiseException, true);
  assert.equal(r.distinctCustomers, 3);
});

check('real-time: wife added to husband account (2) → no exception', () => {
  const history = [{ instrumentToken: 'ACC2', customerId: 'Husband' }];
  const r = checkPayment({ instrumentToken: 'ACC2', customerId: 'Wife' }, history);
  assert.equal(r.raiseException, false);
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
