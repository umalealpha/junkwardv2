'use strict';
// Unit test for lib/eventEngine.js — run: node test/eventEngine.test.js
const assert = require('node:assert');
const { planActions } = require('../lib/eventEngine');

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };
const dos = p => p.actions.map(a => a.do);

check('CFO 2026-07-21: absent condition flags DROP the action (no flags → no lapse/RI)', () => {
  const p = planActions('debit_order_failed', {}); // no flags at all
  assert.ok(!dos(p).includes('start_lapse_process'), 'lapse must not start on missing info');
  const t = planActions('total_loss', {});
  assert.ok(!dos(t).includes('raise_reinsurance_recovery'), 'RI recovery needs explicit largeLoss:true');
});

check('CFO 2026-07-21: lapse, claim close and repair routing all wait for a human', () => {
  const lapse = planActions('debit_order_failed', { flags: { retriesExhausted: true } })
    .actions.find(a => a.do === 'start_lapse_process');
  assert.equal(lapse.needsApproval, true);
  const close = planActions('claim_paid', {}).actions.find(a => a.do === 'close_claim');
  assert.equal(close.needsApproval, true);
  const repair = planActions('assessment_completed', {}).actions.find(a => a.do === 'route_repair_or_writeoff');
  assert.equal(repair.needsApproval, true);
});

check('CFO 2026-07-21: refund_due tells the customer "being processed"; "paid" only after execution', () => {
  const due = planActions('refund_due', {}).actions.find(a => a.customerMsg);
  assert.equal(due.customerMsg, 'refund_processing');
  const done = planActions('refund_completed', {}).actions.find(a => a.customerMsg);
  assert.equal(done.customerMsg, 'refund_paid');
});

check('write-off (total_loss) fans out to every department', () => {
  const p = planActions('total_loss', { flags: { largeLoss: true } });
  const d = dos(p);
  for (const a of ['stop_premium_collection', 'remove_asset_from_cover', 'raise_reinsurance_recovery',
                   'open_salvage_file', 'notify_customer', 'draft_settlement_je']) {
    assert.ok(d.includes(a), 'missing ' + a);
  }
  const depts = new Set(p.actions.map(a => a.dept));
  for (const dept of ['Finance', 'Underwriting', 'Reinsurance', 'Salvage', 'Accounting']) {
    assert.ok(depts.has(dept), 'no action for ' + dept);
  }
});

check('write-off flags money moves for human approval', () => {
  const p = planActions('total_loss', {});
  assert.equal(p.needsHuman, true);
  const je = p.actions.find(a => a.do === 'draft_settlement_je');
  assert.equal(je.needsApproval, true);
  const recover = p.actions.find(a => a.do === 'recover_outstanding_premium_from_settlement');
  assert.equal(recover.needsApproval, true);
});

check('customer-facing actions resolve to a ready catalogue message', () => {
  const p = planActions('claim_registered', {});
  const n = p.actions.find(a => a.do === 'notify_customer');
  assert.equal(n.customerMsg, 'claim_received');
  assert.equal(n.messageReady, true);
});

check('condition flags drop actions when explicitly false', () => {
  const off = planActions('debit_order_failed', { flags: { retriesExhausted: false } });
  assert.ok(!dos(off).includes('start_lapse_process'), 'lapse should be dropped');
  const on = planActions('debit_order_failed', { flags: { retriesExhausted: true } });
  assert.ok(dos(on).includes('start_lapse_process'), 'lapse should fire');
  assert.ok(dos(on).includes('notify_underwriting_off_risk'), 'UW should be told');
});

check('reinsurance recovery only on a large loss', () => {
  const small = planActions('total_loss', { flags: { largeLoss: false } });
  assert.ok(!dos(small).includes('raise_reinsurance_recovery'));
});

check('notify actions carry a recipient group', () => {
  const p = planActions('total_loss', {});
  const uw = p.actions.find(a => a.do === 'remove_asset_from_cover');
  assert.ok(Array.isArray(uw.recipients) && uw.recipients.includes('Underwriting'));
});

check('unknown event returns unknown, no actions', () => {
  const p = planActions('nonsense_event', {});
  assert.equal(p.unknown, true);
  assert.equal(p.actions.length, 0);
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
