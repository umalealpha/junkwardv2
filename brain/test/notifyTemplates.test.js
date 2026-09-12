'use strict';
// Unit test for the new lifecycle stage messages in lib/notifier.js.
// run: node test/notifyTemplates.test.js
// Asserts each stage has whatsapp + email copy and that, once filled with real
// data, no unfilled {placeholder} is left behind.

const assert = require('node:assert');

// The notifier pulls in db.js (better-sqlite3), which isn't built in this bare
// clone. Templates are static data unrelated to the DB, so stub the native dep
// to load the module in isolation.
const Module = require('module');
const _load = Module._load;
Module._load = function (request, ...rest) {
  if (request === 'better-sqlite3') {
    return function () {
      const stmt = { get: () => undefined, run: () => ({}), all: () => [] };
      return { prepare: () => stmt, pragma: () => {}, exec: () => {}, function: () => {}, close: () => {} };
    };
  }
  return _load.call(this, request, ...rest);
};

const { TEMPLATES } = require('../lib/notifier');

let pass = 0, fail = 0;
function check(name, fn) {
  try { fn(); pass++; console.log(`  ok  ${name}`); }
  catch (e) { fail++; console.log(`FAIL  ${name}\n      ${e.message}`); }
}

// Sample data covering every placeholder used across the stage messages.
const DATA = {
  claimNumber: 'G2026000123', link: 'https://claims.alphadirect.co.bw/t/abc',
  contactName: 'Neo', assessmentDate: '11 Jul 2026', supplierName: 'Panelbeaters Ltd',
  amount: 'BWP 42,000', payeeName: 'Panelbeaters Ltd',
};
function fill(tpl) {
  return String(tpl).replace(/\{(\w+)\}/g, (_, k) => (DATA[k] != null ? String(DATA[k]) : `{${k}}`));
}

const STAGES = [
  'assessment_scheduled', 'assessment_completed',
  'po_sent_supplier', 'aol_sent_signature', 'claim_paid',
];

for (const key of STAGES) {
  check(`${key}: has whatsapp + email, no unfilled placeholders`, () => {
    const t = TEMPLATES[key];
    assert.ok(t, `missing template ${key}`);
    assert.ok(t.whatsapp && t.whatsapp.length > 20, 'whatsapp copy missing/short');
    assert.ok(t.email && t.email.subject && t.email.text, 'email subject/text missing');
    for (const s of [t.sms, t.whatsapp, t.email.subject, t.email.text]) {
      const left = fill(s).match(/\{[a-zA-Z]+\}/);
      assert.ok(!left, `unfilled placeholder ${left && left[0]} in: ${s.slice(0, 50)}`);
    }
    assert.ok(fill(t.whatsapp).includes('G2026000123'), 'claim number not in whatsapp copy');
  });
}

check('claim_paid whatsapp names the amount and payee', () => {
  const w = fill(TEMPLATES.claim_paid.whatsapp);
  assert.ok(w.includes('BWP 42,000'));
  assert.ok(w.includes('Panelbeaters Ltd'));
});

console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
