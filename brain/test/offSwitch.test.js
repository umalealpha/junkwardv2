'use strict';
// offSwitch.test.js — PROOF (a): with the OFF switch off, the brain sends
// NOTHING even when the provider keys are plugged in. Also proves the switch is
// real (turning it on lets a send attempt through). No real network: fetch is
// stubbed and counted. Run: node test/offSwitch.test.js

// Arrange the "keys are plugged in" world, switch OFF.
process.env.BRAIN_LIVE_ARMS = 'false';
process.env.MAILGUN_API_KEY = 'test-key';
process.env.MAILGUN_DOMAIN  = 'mg.test.example';
process.env.INFOBIP_BASE_URL = 'https://test.api.infobip.com';
process.env.INFOBIP_API_KEY  = 'test-key';
process.env.BRAIN_HIGH_ARREARS_RECIPIENTS = 'cfo@example.com,fin@example.com';
process.env.AI_GATEWAY_URL = 'https://gateway.test.example/v1/chat';
process.env.AI_GATEWAY_KEY = 'test-key';

const assert = require('node:assert');

// Count every outbound network call. If the OFF switch works, this stays 0.
let fetchCount = 0;
global.fetch = () => {
  fetchCount++;
  return Promise.resolve({
    ok: true, status: 200,
    text: async () => '',
    json: async () => ({ messages: [{ messageId: 'stub', status: { groupId: 1 } }] }),
  });
};

const mailer     = require('../lib/mailer');
const infobip    = require('../lib/infobip');
const highArrears = require('../lib/highArrearsAlert');
const teams      = require('../lib/teamsNotify');
const ai         = require('../lib/ai');

let pass = 0, fail = 0;
async function check(n, fn) {
  try { await fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); }
}

(async () => {
  await check('OFF: mailer.sendEmail sends nothing (keys present)', async () => {
    const r = mailer.sendEmail({ to: 'x@example.com', subject: 's', text: 't' });
    assert.equal(fetchCount, 0, 'no email network call');
    assert.ok(r && r.blocked === true, 'returns blocked');
  });

  await check('OFF: infobip.sendSms sends nothing (keys present)', async () => {
    const r = await infobip.sendSms({ to: '+26771000000', text: 'hi' });
    assert.equal(fetchCount, 0, 'no sms network call');
    assert.equal(r.status, 'blocked');
  });

  await check('OFF: high-arrears alert sends nothing (keys + recipients present)', async () => {
    const r = highArrears.maybeSendHighArrearsAlert({ claimNumber: 'C1', arrearsMonths: 6, financeApproved: true });
    assert.equal(fetchCount, 0, 'no alert network call');
    assert.equal(r.status, 'blocked');
  });

  await check('OFF: teams.sendBackdateAlert posts nothing (webhook present)', async () => {
    teams.sendBackdateAlert('https://outlook.office.com/webhook/test', {
      claim_number: 'C1', username: 'tester', user_role: 'admin', changes: [],
    });
    assert.equal(fetchCount, 0, 'no teams network call');
  });

  await check('OFF: ai.ask makes no model call and returns null (gateway present)', async () => {
    const r = await ai.ask('summarise this ranked queue');
    assert.equal(fetchCount, 0, 'no ai network call');
    assert.equal(r, null, 'ask returns null when off');
  });

  await check('OFF run made ZERO network calls in total', () => {
    assert.equal(fetchCount, 0);
  });

  // Prove the switch is REAL: flip it on and a send is attempted.
  await check('ON: infobip.sendSms now attempts the send (switch is real)', async () => {
    process.env.BRAIN_LIVE_ARMS = 'true';
    const before = fetchCount;
    await infobip.sendSms({ to: '+26771000000', text: 'hi' });
    assert.ok(fetchCount > before, 'expected a network attempt when armed');
  });

  console.log('\n' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})();
