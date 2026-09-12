'use strict';
// preIntimation.test.js — the SOFT-LAUNCH pre-intimation dataset + the
// respectful overdue-email render. Pure; no network. Run: node test/preIntimation.test.js
//
// Arms OFF for the whole run (soft launch) and fetch stubbed + counted, so we
// can PROVE the send-shaped helper makes zero network calls.
process.env.BRAIN_LIVE_ARMS = 'false';

let fetchCount = 0;
global.fetch = () => { fetchCount++; return Promise.resolve({ ok: true, status: 200, text: async () => '', json: async () => ({}) }); };

const assert = require('node:assert');
const pi = require('../lib/preIntimation');

let pass = 0, fail = 0;
function check(n, fn) { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } }

// Canonical affected-policy fixtures (the shape produced by the sibling module).
function fixtures() {
  return [
    { policyNumber: 'MIS-1001', customerName: 'Kefilwe Moeng', productId: 5, product: 'Legal Insurance', agent: 'Tebogo A.', channel: 'RealPay', billingType: 'MIS', amountOverdue: 480, monthsUnpaid: 2, daysOverdue: 3, stage: 'deactivate_candidate', signalConfidence: 'clean', reason: '2 months failed' },
    { policyNumber: 'DOM-2002', customerName: 'Neo Pilane', productId: 1, product: 'Funeral Plan', agent: 'Boitumelo K.', channel: 'DPO', billingType: 'DOM', amountOverdue: 250, monthsUnpaid: 2, daysOverdue: 9, stage: 'grace', signalConfidence: 'clean', reason: 'overdue email sent' },
    { policyNumber: 'COM-3003', customerName: 'Lorato Sithole', productId: 3, product: 'Motor', agent: 'Mpho D.', channel: 'ledger', billingType: 'COM', amountOverdue: 1520, monthsUnpaid: 3, daysOverdue: 18, stage: 'cancel_candidate', signalConfidence: 'clean', reason: 'grace expired' },
    // UNCERTAIN — bank may have silently changed the account (GRA-0203). Must be
    // pulled OUT of the action groups and into needs-verification.
    { policyNumber: 'MIS-4004', customerName: 'Gaone Rams', productId: 2, product: 'Home', agent: 'Kabo T.', channel: 'RealPay', billingType: 'MIS', amountOverdue: 900, monthsUnpaid: 2, daysOverdue: 2, stage: 'deactivate_candidate', signalConfidence: 'uncertain', reason: 'signal unclear' },
  ];
}

check('groups clean rows by stage', () => {
  const ds = pi.buildPreIntimation(fixtures());
  assert.equal(ds.groups.deactivate_candidate.length, 1);
  assert.equal(ds.groups.grace.length, 1);
  assert.equal(ds.groups.cancel_candidate.length, 1);
  assert.equal(ds.groups.deactivate_candidate[0].policyNumber, 'MIS-1001');
});

check('each row carries the CFO report columns + status', () => {
  const row = pi.buildPreIntimation(fixtures()).groups.cancel_candidate[0];
  for (const k of ['policyNumber', 'client', 'product', 'agent', 'amountOverdue', 'daysOverdue', 'status']) {
    assert.ok(Object.prototype.hasOwnProperty.call(row, k), 'missing ' + k);
  }
  assert.equal(row.client, 'Lorato Sithole');
  assert.equal(row.status, 'CANCELLED');
  assert.equal(row.amountOverdue, 1520);
});

check('UNCERTAIN rows are SEPARATED into needs-verification, never in action groups (GRA-0203)', () => {
  const ds = pi.buildPreIntimation(fixtures());
  assert.equal(ds.needsVerification.length, 1);
  assert.equal(ds.needsVerification[0].policyNumber, 'MIS-4004');
  assert.equal(ds.needsVerification[0].signalConfidence, 'uncertain');
  // The uncertain deactivate_candidate must NOT appear in the deactivate group.
  assert.ok(!ds.groups.deactivate_candidate.some((r) => r.policyNumber === 'MIS-4004'));
});

check('counts reflect grouping + verification bucket', () => {
  const c = pi.buildPreIntimation(fixtures()).counts;
  assert.deepEqual(c, { deactivate_candidate: 1, grace: 1, cancel_candidate: 1, needsVerification: 1, total: 4 });
});

check('handles empty / missing input', () => {
  const ds = pi.buildPreIntimation();
  assert.equal(ds.counts.total, 0);
  assert.deepEqual(ds.needsVerification, []);
  assert.deepEqual(ds.groups.grace, []);
});

check('renderOverdueEmail includes the agent name + a "contact your agent" line', () => {
  const p = fixtures()[0];
  const { subject, text, html } = pi.renderOverdueEmail(p);
  assert.ok(subject.includes('MIS-1001'));
  assert.ok(text.includes('Tebogo A.'), 'agent name in plain text');
  assert.ok(/contact\s+your\s+agent/i.test(text), 'contact-your-agent line present');
  assert.ok(html.includes('Tebogo A.'), 'agent name in html');
  // Respectful wording: thanks the client for being a client.
  assert.ok(/thank you/i.test(text) && /valued client/i.test(text));
  // Outlook paste requirement: bgcolor attribute on branded cells.
  assert.ok(/bgcolor="#010066"/.test(html));
});

check('renderOverdueEmail falls back gracefully when agent/name missing', () => {
  const { text } = pi.renderOverdueEmail({ policyNumber: 'X', amountOverdue: 100, daysOverdue: 1 });
  assert.ok(text.includes('Valued Client'));
  assert.ok(text.includes('your Alpha Direct agent'));
});

check('renderOverdueEmail is pure — makes ZERO network calls', () => {
  const before = fetchCount;
  pi.renderOverdueEmail(fixtures()[0]);
  assert.equal(fetchCount, before, 'render must not touch the network');
});

check('sendPreIntimation with arms OFF: blocked, ZERO network calls, still returns rendered content', () => {
  const before = fetchCount;
  const r = pi.sendPreIntimation(fixtures()[0], { transmit: () => { global.fetch('https://x'); } });
  assert.equal(r.blocked, true);
  assert.equal(r.sent, false);
  assert.ok(r.rendered && r.rendered.subject.includes('MIS-1001'));
  assert.equal(fetchCount, before, 'OFF switch: nothing transmitted, no network call');
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
