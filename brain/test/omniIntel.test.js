'use strict';
// omniIntel.test.js — the INBOUND Omni intel pull (T7) must land COUNTS ONLY.
// stripToCounts() is the PII guard: even if Omni sends a mis-shaped body with
// customer rows / identifiers, nothing PII-shaped may survive into the brain's
// stored snapshot. Proves the whitelist, the PII-key drop, and the row drop.
const assert = require('node:assert');
const { stripToCounts } = require('../lib/omniIntel');

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

check('keeps counts/totals + one level of count maps', () => {
  const { out } = stripToCounts({
    generatedAt: '2026-07-27T04:00:00Z',
    total: 42,
    liveArms: false,
    byDomain: { arrears: 10, kyc: 5 },
  });
  assert.equal(out.total, 42);
  assert.equal(out.generatedAt, '2026-07-27T04:00:00Z');
  assert.equal(out.liveArms, false);
  assert.deepEqual(out.byDomain, { arrears: 10, kyc: 5 });
});

check('drops PII-named keys (omang, msisdn, email, policy_number, name, account)', () => {
  const { out, dropped } = stripToCounts({
    total: 3,
    omang: '123456789', msisdn: '26771000000', customer_email: 'a@b.c',
    policy_number: 'DOMG2024130581', surname: 'Doe', bank_account: '000123',
  });
  assert.equal(out.total, 3);
  for (const k of ['omang', 'msisdn', 'customer_email', 'policy_number', 'surname', 'bank_account']) {
    assert.ok(!(k in out), 'must drop PII key: ' + k);
    assert.ok(dropped.includes(k), 'must report dropped key: ' + k);
  }
});

check('drops row-shaped arrays (a customer list can never survive)', () => {
  const { out, dropped } = stripToCounts({
    total: 2,
    rows: [{ omang: '1', name: 'Jane' }, { omang: '2', name: 'John' }],
  });
  assert.equal(out.total, 2);
  assert.ok(!('rows' in out));
  assert.ok(dropped.includes('rows'));
});

check('drops long free-text strings (possible PII), keeps short labels', () => {
  const { out } = stripToCounts({
    source: 'omni',
    note: 'x'.repeat(200),
  });
  assert.equal(out.source, 'omni');
  assert.ok(!('note' in out));
});

check('nested PII inside a count map is dropped, siblings kept', () => {
  const { out } = stripToCounts({ byDomain: { arrears: 4, customer_name: 'Jane' } });
  assert.equal(out.byDomain.arrears, 4);
  assert.ok(!('customer_name' in out.byDomain));
});

check('non-object input → empty object, no throw', () => {
  assert.deepEqual(stripToCounts(null).out, {});
  assert.deepEqual(stripToCounts([{ a: 1 }]).out, {});
  assert.deepEqual(stripToCounts('nope').out, {});
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
