'use strict';
// Unit test for lib/kycChaser.js — run: node test/kycChaser.test.js
const assert = require('node:assert');
const { assess } = require('../lib/kycChaser');
const TODAY = '2026-07-09';
const ocr = (type, conf = 0.9, expiry = '2030-01-01') => ({ present: true, type, ocr: { detectedType: type, confidence: conf, expiry } });
const verbs = (r) => r.actions.map((a) => a.do);

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

check('motor comp with all 3 docs valid → COMPLIANT', () => {
  const r = assess({ product: 'motor_comprehensive',
    docs: [ocr('id'), ocr('drivers_licence'), ocr('proof_of_address')], remindersSent: 0 }, { today: TODAY });
  assert.equal(r.status, 'COMPLIANT');
  assert.equal(r.kycCompliant, true);
  assert.equal(r.actions.length, 0);
});

check('missing one of the three → INCOMPLETE + request', () => {
  const r = assess({ product: 'motor_comprehensive',
    docs: [ocr('id'), ocr('drivers_licence')], remindersSent: 0 }, { today: TODAY });
  assert.equal(r.status, 'INCOMPLETE');
  assert.ok(r.missing.includes('proof_of_address'));
  assert.ok(verbs(r).includes('request_documents'));
});

check('poor scan → REVIEW (manual), NOT rejected', () => {
  const r = assess({ product: 'instant', docs: [ocr('id', 0.4)], remindersSent: 0 }, { today: TODAY });
  assert.equal(r.status, 'REVIEW');
  assert.ok(verbs(r).includes('manual_review'));
  assert.equal(r.kycCompliant, false);
});

check('expired document → invalid + INCOMPLETE', () => {
  const r = assess({ product: 'instant', docs: [ocr('id', 0.9, '2025-01-01')], remindersSent: 0 }, { today: TODAY });
  assert.ok(r.invalid.some((i) => i.type === 'id' && i.reason === 'expired'));
  assert.equal(r.status, 'INCOMPLETE');
});

check('wrong document uploaded → invalid', () => {
  const r = assess({ product: 'instant',
    docs: [{ present: true, type: 'id', ocr: { detectedType: 'drivers_licence', confidence: 0.9 } }], remindersSent: 0 }, { today: TODAY });
  assert.ok(r.invalid.some((i) => i.reason === 'wrong_document'));
});

check('chase ladder: already reminded once → remind again', () => {
  const r = assess({ product: 'instant', docs: [], remindersSent: 1, firstRequestedDaysAgo: 5 }, { today: TODAY });
  assert.ok(verbs(r).includes('remind_customer'));
});

check('past grace + reminders exhausted → escalate + flag KYC hold', () => {
  const r = assess({ product: 'instant', docs: [], remindersSent: 3, firstRequestedDaysAgo: 30 }, { today: TODAY });
  assert.ok(verbs(r).includes('escalate_flag_kyc_hold'));
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
