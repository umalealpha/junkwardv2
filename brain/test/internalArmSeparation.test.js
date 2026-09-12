'use strict';
// internalArmSeparation.test.js — proves the CFO 2026-08-31 model: with the
// CUSTOMER arm OFF but INTERNAL reports + the brain ON, internal comms FIRE while
// customer comms stay locked. Guards the reclassification (high-arrears / Teams /
// AI onto the internal/enable arms; customer SMS + email stay on BRAIN_LIVE_ARMS).
// No real network: fetch is stubbed and counted.

process.env.BRAIN_LIVE_ARMS = 'false';         // customer arm OFF (locked)
process.env.BRAIN_INTERNAL_REPORTS = 'true';   // internal comms ON
process.env.BRAIN_ENABLED = 'true';            // brain features ON
process.env.BRAIN_INTERNAL_EMAIL_DOMAINS = 'alphadirect.co.bw';
process.env.MAILGUN_API_KEY = 'test-key';
process.env.MAILGUN_DOMAIN  = 'mg.test.example';
process.env.INFOBIP_BASE_URL = 'https://test.api.infobip.com';
process.env.INFOBIP_API_KEY  = 'test-key';
process.env.BRAIN_HIGH_ARREARS_RECIPIENTS = 'cfo@alphadirect.co.bw,fin@alphadirect.co.bw';
process.env.AI_GATEWAY_URL = 'https://gateway.test.example/v1/chat';
process.env.AI_GATEWAY_KEY = 'test-key';

const assert = require('node:assert');

let fetchCount = 0;
global.fetch = () => { fetchCount++; return Promise.resolve({
  ok: true, status: 200, text: async () => '',
  json: async () => ({ choices: [{ message: { content: 'ok' } }], messages: [{ messageId: 'x', status: { groupId: 1 } }] }),
}); };

const mailer = require('../lib/mailer');
const infobip = require('../lib/infobip');
const highArrears = require('../lib/highArrearsAlert');
const teams = require('../lib/teamsNotify');
const ai = require('../lib/ai');

let pass = 0, fail = 0;
async function check(n, fn) {
  try { await fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); }
}

(async () => {
  // ── CUSTOMER LOCKED — even to an internal-looking address, the customer arm blocks.
  await check('CUSTOMER: mailer.sendEmail stays BLOCKED (customer arm off)', async () => {
    const before = fetchCount;
    const r = mailer.sendEmail({ to: 'someone@alphadirect.co.bw', subject: 's', text: 't' });
    assert.ok(r && r.blocked === true, 'sendEmail must stay blocked');
    assert.equal(fetchCount, before, 'no customer email network call');
  });
  await check('CUSTOMER: infobip.sendSms stays BLOCKED', async () => {
    const before = fetchCount;
    const r = await infobip.sendSms({ to: '+26771000000', text: 'hi' });
    assert.equal(r.status, 'blocked');
    assert.equal(fetchCount, before, 'no customer sms network call');
  });

  // ── INTERNAL FIRES — the internal comms now go, with customer arm still off.
  await check('INTERNAL: high-arrears alert now FIRES (internal arm on)', async () => {
    const before = fetchCount;
    const r = highArrears.maybeSendHighArrearsAlert({ claimNumber: 'C1', arrearsMonths: 6, financeApproved: true });
    assert.equal(r.status, 'alerted');
    assert.ok(fetchCount > before, 'internal report must attempt the send');
  });
  await check('INTERNAL: Teams alert now POSTS (internal arm on)', async () => {
    const before = fetchCount;
    teams.sendBackdateAlert('https://outlook.office.com/webhook/test', { claim_number: 'C1', username: 't', user_role: 'admin', changes: [] });
    await new Promise((r) => setTimeout(r, 15)); // fire-and-forget
    assert.ok(fetchCount > before, 'teams must attempt the post');
  });
  await check('FEATURE: ai.ask now CALLS the model (brain enabled)', async () => {
    const before = fetchCount;
    await ai.ask('summarise the ranked queue');
    assert.ok(fetchCount > before, 'ai must attempt the model call');
  });

  console.log('\n' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})();
