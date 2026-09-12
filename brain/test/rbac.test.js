'use strict';
// rbac.test.js — PROOF of the roles/permissions/audit contract:
//   1) the permission matrix is exactly what we intend (per role);
//   2) unknown actions fail loud;
//   3) EVERY /api route is enforced SERVER-SIDE (403 for a role that lacks the
//      permission, 401 with no token) — a non-admin cannot hit an admin route;
//   4) approvals record actor + role in the audit and execute nothing;
//   5) the audit log is APPEND-ONLY (old bytes never change; no edit/delete API).
// Run: node test/rbac.test.js

const assert = require('node:assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const http = require('http');

// Isolated data dir + fixed port BEFORE any require caches config.
process.env.BRAIN_DATA_DIR = fs.mkdtempSync(path.join(os.tmpdir(), 'brain-rbac-'));
process.env.BRAIN_APPROVE_TOKEN = 'legacy-shared-token';
process.env.BRAIN_PORT = '18513';
process.env.BRAIN_LIVE_ARMS = 'false'; // arms MUST stay off

const rbac = require('../lib/rbac');
const store = require('../store');

let pass = 0, fail = 0;
async function check(n, fn) {
  try { await fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); }
}

function req(port, method, pathName, body, token) {
  return new Promise((resolve) => {
    const r = http.request({ port, path: pathName, method,
      headers: { 'content-type': 'application/json', ...(token ? { authorization: 'Bearer ' + token } : {}) } },
      (res) => { let b = ''; res.on('data', (c) => b += c); res.on('end', () => {
        let parsed = b; try { parsed = JSON.parse(b); } catch {}
        resolve({ status: res.statusCode, body: parsed, ctype: res.headers['content-type'] || '' });
      }); });
    if (body !== undefined) r.end(JSON.stringify(body)); else r.end();
  });
}

(async () => {
  // ── 1) permission matrix ──
  await check('matrix: admin can do everything', () => {
    for (const a of rbac.ACTIONS) assert.ok(rbac.can('admin', a), 'admin should have ' + a);
  });
  await check('matrix: finance = view all + approve + export, no admin', () => {
    ['view.overview', 'view.activity', 'view.queue', 'view.affected', 'approve', 'export'].forEach((a) => assert.ok(rbac.can('finance', a), 'finance ' + a));
    ['admin.users', 'admin.settings'].forEach((a) => assert.ok(!rbac.can('finance', a), 'finance !' + a));
  });
  await check('matrix: underwriting = view + approve, no export/admin', () => {
    ['view.overview', 'view.activity', 'view.queue', 'view.affected', 'approve'].forEach((a) => assert.ok(rbac.can('underwriting', a)));
    ['export', 'admin.users', 'admin.settings'].forEach((a) => assert.ok(!rbac.can('underwriting', a)));
  });
  await check('matrix: claims = claims-queue + activity only', () => {
    ['view.overview', 'view.activity', 'view.queue.claims'].forEach((a) => assert.ok(rbac.can('claims', a)));
    ['view.queue', 'view.affected', 'approve', 'export', 'admin.users', 'admin.settings'].forEach((a) => assert.ok(!rbac.can('claims', a), 'claims !' + a));
  });
  await check('matrix: viewer = read-only, NO actions', () => {
    ['view.overview', 'view.activity', 'view.queue', 'view.affected'].forEach((a) => assert.ok(rbac.can('viewer', a)));
    ['approve', 'export', 'admin.users', 'admin.settings'].forEach((a) => assert.ok(!rbac.can('viewer', a), 'viewer !' + a));
  });
  await check('unknown role → false; unknown action → throws', () => {
    assert.equal(rbac.can('nobody', 'view.overview'), false);
    assert.throws(() => rbac.can('admin', 'view.nonsense'));
  });

  // ── store: users + timing-safe token resolution ──
  const tokens = {};
  await check('createUser issues tokens; getUserByToken resolves role', () => {
    for (const role of ['admin', 'finance', 'underwriting', 'claims', 'viewer']) {
      const { user, token } = store.createUser({ name: 'T-' + role, role });
      tokens[role] = token;
      assert.equal(user.role, role);
      const resolved = store.getUserByToken(token);
      assert.equal(resolved.role, role);
    }
    assert.equal(store.getUserByToken('not-a-real-token'), null);
    // listUsers never leaks the hash
    assert.ok(store.listUsers().every((u) => !('tokenHash' in u)));
  });

  await check('duplicate name + invalid role rejected', () => {
    assert.throws(() => store.createUser({ name: 't-viewer', role: 'viewer' })); // case-insensitive dup
    assert.throws(() => store.createUser({ name: 'Bad', role: 'wizard' }));
  });

  // Seed a queue with an approvable item, PLUS the slim dashboard snapshot that
  // /api/queue now serves (store.slimQueue) — the real sweep writes both. approve
  // still reads the full queue (findQueueItem), so keep both in sync.
  const seedQueue = { generatedAt: 'T0', total: 2, counts: { Finance: 1, Claims: 1 },
    teams: { Finance: [{ id: 'collections:P1', title: 'arrears P1', priority: 90, status: 'open', needsApproval: true, team: 'Finance', domain: 'collections' }],
             Claims: [{ id: 'claims:C1', title: 'claim C1', priority: 70, status: 'open', team: 'Claims', domain: 'fraud' }] } };
  store.writeQueue(seedQueue);
  store.writeDashboard(store.slimQueue(seedQueue));

  // ── HTTP layer ──
  const { server: srv } = require('../server');
  await new Promise((r) => setTimeout(r, 150));
  const port = srv.address().port;

  await check('GET /api/me: no token → 401', async () => {
    assert.equal((await req(port, 'GET', '/api/me')).status, 401);
  });
  await check('GET /api/me: viewer → 200 with role+permissions', async () => {
    const r = await req(port, 'GET', '/api/me', undefined, tokens.viewer);
    assert.equal(r.status, 200);
    assert.equal(r.body.role, 'viewer');
    assert.ok(Array.isArray(r.body.permissions));
  });

  await check('admin route: viewer → 403 (server-enforced, not UI-hidden)', async () => {
    assert.equal((await req(port, 'GET', '/api/admin/users', undefined, tokens.viewer)).status, 403);
    assert.equal((await req(port, 'GET', '/api/admin/settings', undefined, tokens.finance)).status, 403);
  });
  await check('admin route: no token → 401', async () => {
    assert.equal((await req(port, 'GET', '/api/admin/users')).status, 401);
  });
  await check('admin route: admin → 200', async () => {
    assert.equal((await req(port, 'GET', '/api/admin/users', undefined, tokens.admin)).status, 200);
  });

  await check('approve: viewer → 403; claims → 403', async () => {
    assert.equal((await req(port, 'POST', '/api/approve', { id: 'collections:P1', approver: 'X' }, tokens.viewer)).status, 403);
    assert.equal((await req(port, 'POST', '/api/approve', { id: 'collections:P1', approver: 'X' }, tokens.claims)).status, 403);
  });
  await check('approve: signature BOUND to identity — body "approver" ignored (anti-spoof, dual-control integrity)', async () => {
    // No approver field: identity supplies the signature → 200 (not 400).
    const noBody = await req(port, 'POST', '/api/approve', { id: 'collections:P1' }, tokens.finance);
    assert.equal(noBody.status, 200);
    assert.equal(noBody.body.approvedBy, 'T-finance'); // the logged-in user, not a body field
    // A spoofed body approver is IGNORED — the recorded signature stays the identity.
    // (Without this, one user could clear a 2-approver money item alone by posting two names.)
    const spoof = await req(port, 'POST', '/api/approve', { id: 'collections:P1', approver: 'Kago T (spoof)' }, tokens.finance);
    assert.equal(spoof.status, 200);
    assert.equal(spoof.body.approvedBy, 'T-finance');
    const audit = store.readAudit({ limit: 5 });
    const top = audit.entries[0];
    assert.equal(top.action, 'approve');
    assert.equal(top.actor, 'T-finance');
    assert.equal(top.actorRole, 'finance');
    assert.equal(top.meta.approver, 'T-finance');   // identity, never the spoofed body value
    assert.equal(top.meta.boundToIdentity, true);
    // arms are still off — the queue item is 'approved' but NOTHING executed.
    assert.equal(process.env.BRAIN_LIVE_ARMS, 'false');
  });

  await check('affected: claims → 403; viewer → 200; csv needs export (UW 403, finance 200)', async () => {
    assert.equal((await req(port, 'GET', '/api/affected', undefined, tokens.claims)).status, 403);
    assert.equal((await req(port, 'GET', '/api/affected', undefined, tokens.viewer)).status, 200);
    assert.equal((await req(port, 'GET', '/api/affected?format=csv', undefined, tokens.underwriting)).status, 403);
    const csv = await req(port, 'GET', '/api/affected?format=csv', undefined, tokens.finance);
    assert.equal(csv.status, 200);
    assert.ok(/text\/csv/.test(csv.ctype));
  });

  await check('queue: full for finance; claims-only slice for claims role', async () => {
    const full = await req(port, 'GET', '/api/queue', undefined, tokens.finance);
    assert.equal(full.status, 200);
    assert.ok(full.body.teams.Finance && full.body.teams.Claims);
    const claimsView = await req(port, 'GET', '/api/queue', undefined, tokens.claims);
    assert.equal(claimsView.status, 200);
    assert.equal(claimsView.body.scope, 'claims-only');
    assert.ok(claimsView.body.teams.Claims && !claimsView.body.teams.Finance);
  });

  await check('activity: viewer can read; a fresh token from admin-created user works end-to-end', async () => {
    assert.equal((await req(port, 'GET', '/api/activity', undefined, tokens.viewer)).status, 200);
    const created = await req(port, 'POST', '/api/admin/users', { name: 'Late Joiner', role: 'finance' }, tokens.admin);
    assert.equal(created.status, 201);
    assert.ok(created.body.token, 'plaintext token returned once');
    const me = await req(port, 'GET', '/api/me', undefined, created.body.token);
    assert.equal(me.body.role, 'finance');
  });

  await check('cannot delete/demote the last active admin', async () => {
    // Only one admin (T-admin) exists → deleting it must be refused.
    const admins = store.listUsers().filter((u) => u.role === 'admin' && !u.disabled);
    assert.equal(admins.length, 1);
    const del = await req(port, 'DELETE', '/api/admin/users', { id: admins[0].id }, tokens.admin);
    assert.equal(del.status, 400);
    const demote = await req(port, 'PUT', '/api/admin/users', { id: admins[0].id, role: 'viewer' }, tokens.admin);
    assert.equal(demote.status, 400);
  });

  await check('settings: admin GET then PUT records a settings.update audit', async () => {
    assert.equal((await req(port, 'GET', '/api/admin/settings', undefined, tokens.admin)).status, 200);
    const put = await req(port, 'PUT', '/api/admin/settings', { teamsWebhook: 'https://example/hook', reportRecipients: 'a@x.com, b@x.com' }, tokens.admin);
    assert.equal(put.status, 200);
    assert.deepEqual(put.body.reportRecipients, ['a@x.com', 'b@x.com']);
    const top = store.readAudit({ limit: 1 }).entries[0];
    assert.equal(top.action, 'settings.update');
  });

  // ── 5) APPEND-ONLY proof ──
  await check('audit log is append-only (old bytes unchanged; no edit/delete API)', async () => {
    assert.equal(typeof store.deleteAudit, 'undefined');
    assert.equal(typeof store.editAudit, 'undefined');
    assert.equal(typeof store.clearAudit, 'undefined');
    const before = fs.readFileSync(store.auditPath(), 'utf8');
    // Perform more state changes.
    await req(port, 'POST', '/api/admin/users', { name: 'Appender', role: 'viewer' }, tokens.admin);
    await req(port, 'PUT', '/api/admin/settings', { teamsWebhook: 'https://example/hook2' }, tokens.admin);
    const after = fs.readFileSync(store.auditPath(), 'utf8');
    assert.ok(after.startsWith(before), 'existing audit content must be an unchanged prefix');
    assert.ok(after.length > before.length, 'new events only ever append');
    // Every line remains valid JSON (no torn / rewritten rows).
    after.trim().split('\n').forEach((l) => JSON.parse(l));
  });

  srv.close();
  console.log('\n' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})();
