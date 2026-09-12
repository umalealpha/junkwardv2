'use strict';
// Unit test for lib/docCheck.js — run: node test/docCheck.test.js
const assert = require('node:assert');
const { crossCheck } = require('../lib/docCheck');
const codes = (r) => r.flags.map((f) => f.code);

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

check("TODAY'S CASE (new business): document shows the loss BEFORE the first payment → HOLD", () => {
  const r = crossCheck(
    { dateOfLoss: '2026-07-07', paymentDate: '2026-07-05', coverStart: '2026-07-01' },
    { incidentDate: '2026-07-01', confidence: 0.9 }  // police report says the event was the 1st
  );
  assert.ok(codes(r).includes('doc_predates_payment'));
  assert.equal(r.recommendation, 'hold');
});

check('IN-FORCE policy: document before the LATEST debit is not backdating (CFO 2026-07-21)', () => {
  const r = crossCheck(
    { dateOfLoss: '2026-07-07', paymentDate: '2026-07-05', coverStart: '2026-01-01' },
    { incidentDate: '2026-07-01', confidence: 0.9 }
  );
  assert.ok(!codes(r).includes('doc_predates_payment'));
  assert.notEqual(r.recommendation, 'hold');
});

check('low OCR confidence demotes every flag — a misread can never drive a hold (CFO 2026-07-21)', () => {
  const r = crossCheck(
    { dateOfLoss: '2026-07-07', paymentDate: '2026-07-05', coverStart: '2026-07-01' },
    { incidentDate: '2026-07-01', confidence: 0.3 } // same "backdating" picture, unreadable scan
  );
  assert.equal(r.recommendation, 'manual_review');
  assert.ok(r.flags.every((f) => f.severity === 'note'));
});

check('document matches the stated loss → ok', () => {
  const r = crossCheck(
    { dateOfLoss: '2026-07-07', paymentDate: '2026-06-01', amount: 5000 },
    { incidentDate: '2026-07-07', amount: 5000, confidence: 0.95 }
  );
  assert.equal(r.recommendation, 'ok');
  assert.equal(r.flags.length, 0);
});

check('stated loss date far from the document → mismatch → refer', () => {
  const r = crossCheck(
    { dateOfLoss: '2026-07-20', paymentDate: '2026-06-01' },
    { incidentDate: '2026-07-01', confidence: 0.9 }
  );
  assert.ok(codes(r).includes('loss_date_mismatch'));
  assert.equal(r.recommendation, 'refer');
});

check('amounts disagree beyond tolerance → mismatch', () => {
  const r = crossCheck(
    { dateOfLoss: '2026-07-07', paymentDate: '2026-06-01', amount: 5000 },
    { incidentDate: '2026-07-07', amount: 9000, confidence: 0.9 }
  );
  assert.ok(codes(r).includes('amount_mismatch'));
});

check('poor OCR read → manual_review, never auto-decide', () => {
  const r = crossCheck(
    { dateOfLoss: '2026-07-07', paymentDate: '2026-06-01' },
    { incidentDate: '2026-07-07', confidence: 0.4 }
  );
  assert.ok(codes(r).includes('low_ocr_confidence'));
  assert.equal(r.recommendation, 'manual_review');
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
