'use strict';
// wireReports.test.js — proves the overdue-email (preIntimation) and Friday
// report (fridayReport) are wired into the runtime: store round-trip, the
// Friday 18:00-Gaborone schedule math, the read-only endpoints, and that the
// Friday SEND stays a no-op while arms are OFF.
const assert = require('node:assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const http = require('http');

process.env.BRAIN_DATA_DIR = fs.mkdtempSync(path.join(os.tmpdir(), 'brain-wire-'));
process.env.BRAIN_APPROVE_TOKEN = 'wire-test-token';
process.env.BRAIN_PORT = '18571';
process.env.BRAIN_LIVE_ARMS = 'false'; // arms MUST stay off

const store = require('../store');
const mailer = require('../lib/mailer');

let pass = 0, fail = 0;
async function check(n, fn) { try { await fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } }

function req(port, pathName, token) {
  return new Promise((resolve) => {
    http.request({ port, path: pathName, method: 'GET',
      headers: token ? { authorization: 'Bearer ' + token } : {} },
      (res) => { let b = ''; res.on('data', (c) => b += c); res.on('end', () => {
        let parsed = b; try { parsed = JSON.parse(b); } catch {}
        resolve({ status: res.statusCode, body: parsed, ctype: res.headers['content-type'] || '' });
      }); }).end();
  });
}

const AFFECTED = [
  { policyNumber: 'DOMG1', customerName: 'A', product: 'Domestic', agent: 'Agent X', billingType: 'DOM',
    stage: 'deactivate_candidate', amountOverdue: 200, daysOverdue: 3, monthsUnpaid: 2, graceEndsAt: '2026-09-15', signalConfidence: 'clean' },
  { policyNumber: 'MIS9', customerName: 'B', product: 'Instant', agent: 'Agent Y', billingType: 'MIS',
    stage: 'cancel_candidate', amountOverdue: 500, daysOverdue: 16, monthsUnpaid: 2, graceEndsAt: '2026-09-15', signalConfidence: 'clean', readyToCancel: true },
  { policyNumber: 'MIS-U', customerName: 'C', product: 'Instant', agent: null, billingType: 'MIS',
    stage: 'grace', amountOverdue: 100, daysOverdue: 5, monthsUnpaid: 2, graceEndsAt: '2026-09-15', signalConfidence: 'uncertain' },
];

(async () => {
  const { server: srv, msUntilNextFridayReport } = require('../server');
  await new Promise((r) => setTimeout(r, 150));
  const port = srv.address().port;
  const { token } = store.createUser({ name: 'wire-admin', role: 'admin' });
  // seed a live affected list the endpoints read from (no stored pre-intimation
  // / friday-report yet → the endpoints build them on-demand from this list).
  store.writeAffected({ generatedAt: 'T1', live: true, affected: AFFECTED });

  await check('Friday schedule math: next fire is a Friday at 16:00 UTC (18:00 Gaborone), in the future', () => {
    const from = new Date('2026-07-22T10:00:00Z'); // a Wednesday
    const ms = msUntilNextFridayReport(from);
    assert.ok(ms > 0, 'must be in the future');
    const when = new Date(from.getTime() + ms);
    assert.equal(when.getUTCDay(), 5, 'must land on a Friday');
    assert.equal(when.getUTCHours(), 16, 'must be 16:00 UTC = 18:00 Gaborone');
    assert.equal(when.getUTCMinutes(), 0);
  });

  await check('GET /api/pre-intimation → grouped counts (arms off)', async () => {
    const r = await req(port, '/api/pre-intimation', token);
    assert.equal(r.status, 200);
    assert.equal(r.body.counts.total, 3);
    assert.equal(r.body.counts.needsVerification, 1); // the uncertain one held back
  });

  await check('GET /api/pre-intimation?policy=DOMG1 → overdue-email preview (render only)', async () => {
    const r = await req(port, '/api/pre-intimation?policy=DOMG1', token);
    assert.equal(r.status, 200);
    assert.ok(r.body.preview && r.body.preview.subject && r.body.preview.html);
  });

  await check('GET /api/friday-report → both sections + numeric counts', async () => {
    const r = await req(port, '/api/friday-report', token);
    assert.equal(r.status, 200);
    assert.ok(r.body.sections.deactivated && r.body.sections.cancelled, 'both sections present');
    assert.equal(typeof r.body.counts.deactivated, 'number');
    assert.equal(typeof r.body.counts.cancelled, 'number');
    assert.ok(r.body.counts.deactivated + r.body.counts.cancelled >= 1, 'the 3-policy sample yields at least one reportable row');
  });

  await check('GET /api/friday-report?format=html → text/html', async () => {
    const r = await req(port, '/api/friday-report?format=html', token);
    assert.equal(r.status, 200);
    assert.ok(/text\/html/.test(r.ctype));
  });

  await check('ARMS OFF: mailer.sendEmail refuses (the Friday send is a no-op)', () => {
    const out = mailer.sendEmail({ to: ['x@y.z'], subject: 's', html: '<b>h</b>', text: 't' });
    assert.ok(out && out.blocked === true, 'send must be blocked with arms off');
    assert.equal(process.env.BRAIN_LIVE_ARMS, 'false');
  });

  await check('store round-trip: pre-intimation + friday report persist', () => {
    store.writePreIntimation({ generatedAt: 'T0', counts: { total: 3 } });
    assert.equal(store.readPreIntimation().counts.total, 3);
    store.writeFridayReport({ generatedAt: 'T0', counts: { deactivated: 1, cancelled: 1 } });
    assert.equal(store.readFridayReport().counts.cancelled, 1);
  });

  srv.close();
  console.log('\n' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})();
