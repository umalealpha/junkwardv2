'use strict';
// approve.test.js — PROOF the /approve endpoint is closed by default, opens
// only to the shared token, always records WHO approved, and that approvals
// survive a daily-sweep queue rebuild.
// Run: node test/approve.test.js

const assert = require('node:assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const http = require('http');

// Isolated data dir so tests never touch a real queue.
process.env.BRAIN_DATA_DIR = fs.mkdtempSync(path.join(os.tmpdir(), 'brain-approve-'));
process.env.BRAIN_APPROVE_TOKEN = 'test-secret-token';
process.env.BRAIN_PORT = '18497'; // before any require caches config

const store = require('../store');

let pass = 0, fail = 0;
async function check(n, fn) {
  try { await fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); }
}

function seedQueue() {
  store.writeQueue({
    generatedAt: 'T0', total: 2, counts: { Finance: 2 },
    teams: { Finance: [
      { id: 'collections:P1', title: 'a', priority: 90, status: 'open', needsApproval: true },
      { id: 'collections:P2', title: 'b', priority: 50, status: 'open', needsApproval: true },
    ] },
  });
}

function post(port, pathName, body, token) {
  return new Promise((resolve) => {
    const req = http.request({ port, path: pathName, method: 'POST',
      headers: { 'content-type': 'application/json', ...(token ? { authorization: 'Bearer ' + token } : {}) } },
      (res) => { let b = ''; res.on('data', (c) => b += c); res.on('end', () => resolve({ status: res.statusCode, body: b })); });
    req.end(JSON.stringify(body));
  });
}

(async () => {
  await check('markApproved without approver refuses', () => {
    seedQueue();
    assert.equal(store.markApproved('collections:P1'), null);
    assert.equal(store.readQueue().teams.Finance[0].status, 'open');
  });

  await check('markApproved records approver on item and in audit', () => {
    seedQueue();
    const item = store.markApproved('collections:P1', '  Arjun Iyer  ');
    assert.equal(item.status, 'approved');
    assert.equal(item.approvedBy, 'Arjun Iyer');
    const audit = fs.readFileSync(store.auditPath(), 'utf8').trim().split('\n').map(JSON.parse);
    const last = audit[audit.length - 1];
    assert.equal(last.action, 'approve');
    assert.equal(last.by, 'Arjun Iyer');
  });

  await check('DUAL CONTROL (CFO 2026-07-21): money items need TWO DISTINCT approvers', () => {
    store.writeQueue({
      generatedAt: 'T0', total: 1, counts: { Finance: 1 },
      teams: { Finance: [
        { id: 'event:refund_due:P9', title: 'refund', priority: 75, status: 'open',
          needsApproval: true, approvalsRequired: 2 },
      ] },
    });
    const first = store.markApproved('event:refund_due:P9', 'Kago T');
    assert.equal(first.status, 'partially_approved');
    // the same person signing again must NOT complete the approval
    const dup = store.markApproved('event:refund_due:P9', 'Kago T');
    assert.equal(dup.status, 'partially_approved');
    assert.equal(dup.approvers.length, 1);
    const second = store.markApproved('event:refund_due:P9', 'Prathap G');
    assert.equal(second.status, 'approved');
    assert.equal(second.approvedBy, 'Kago T + Prathap G');
  });

  await check('dual-control partial signature survives a sweep rebuild', () => {
    store.writeQueue({
      generatedAt: 'T0', total: 1, counts: { Finance: 1 },
      teams: { Finance: [
        { id: 'event:refund_due:P9', title: 'refund', priority: 75, status: 'open',
          needsApproval: true, approvalsRequired: 2 },
      ] },
    });
    store.markApproved('event:refund_due:P9', 'Kago T');
    const rebuilt = store.mergeApprovals({ generatedAt: 'T1', total: 1, counts: { Finance: 1 },
      teams: { Finance: [
        { id: 'event:refund_due:P9', title: 'refund', priority: 75, status: 'open',
          needsApproval: true, approvalsRequired: 2 },
      ] } });
    const item = rebuilt.teams.Finance[0];
    assert.equal(item.status, 'partially_approved');
    assert.deepEqual(item.approvers, ['Kago T']);
  });

  await check('mergeApprovals carries an approval across a sweep rebuild', () => {
    seedQueue();
    store.markApproved('collections:P1', 'Arjun Iyer');
    const rebuilt = { generatedAt: 'T1', total: 2, counts: { Finance: 2 },
      teams: { Finance: [
        { id: 'collections:P1', title: 'a', priority: 90, status: 'open', needsApproval: true },
        { id: 'collections:P2', title: 'b', priority: 50, status: 'open', needsApproval: true },
      ] } };
    store.mergeApprovals(rebuilt);
    assert.equal(rebuilt.teams.Finance[0].status, 'approved');
    assert.equal(rebuilt.teams.Finance[0].approvedBy, 'Arjun Iyer');
    assert.equal(rebuilt.teams.Finance[1].status, 'open');
  });

  // ── HTTP layer ──
  seedQueue();
  const { server: srv } = require('../server');
  await new Promise((r) => setTimeout(r, 150));
  const port = srv.address().port;

  await check('HTTP: no token → 401', async () => {
    const r = await post(port, '/approve', { id: 'collections:P2', approver: 'X' });
    assert.equal(r.status, 401);
  });

  await check('HTTP: wrong token → 401', async () => {
    const r = await post(port, '/approve', { id: 'collections:P2', approver: 'X' }, 'wrong');
    assert.equal(r.status, 401);
  });

  await check('HTTP: right token but no approver → 400', async () => {
    const r = await post(port, '/approve', { id: 'collections:P2' }, 'test-secret-token');
    assert.equal(r.status, 400);
  });

  await check('HTTP: right token + approver → 200 and recorded', async () => {
    const r = await post(port, '/approve', { id: 'collections:P2', approver: 'Kago T' }, 'test-secret-token');
    assert.equal(r.status, 200);
    const q = store.readQueue();
    const item = q.teams.Finance.find((i) => i.id === 'collections:P2');
    assert.equal(item.approvedBy, 'Kago T');
  });

  await check('HTTP legacy: dual-control money item → 409 (shared token cannot supply two distinct identities)', async () => {
    store.writeQueue({ generatedAt: 'T1', total: 1, counts: { Finance: 1 },
      teams: { Finance: [{ id: 'event:refund_due:P9', title: 'refund', priority: 80, status: 'open',
        needsApproval: true, approvalsRequired: 2 }] } });
    const r = await post(port, '/approve', { id: 'event:refund_due:P9', approver: 'Kago T' }, 'test-secret-token');
    assert.equal(r.status, 409);
    // nothing recorded — the item is still open (no partial signature via the shared token)
    const item = store.readQueue().teams.Finance.find((i) => i.id === 'event:refund_due:P9');
    assert.equal(item.status, 'open');
  });

  srv.close();
  console.log(`\n${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})();
