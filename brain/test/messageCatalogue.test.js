'use strict';
// Validation test for lib/messageCatalogue.js — run: node test/messageCatalogue.test.js
// No deps. Confirms all 14 events are present, well-formed, jargon-free, tagged,
// and use only approved placeholders.

const assert = require('node:assert');
const { catalogue, getMessage } = require('../lib/messageCatalogue');

const EXPECTED = [
  'claim_received', 'assessment_booked', 'assessment_done', 'repair_order_sent',
  'agreement_of_loss_to_sign', 'claim_paid', 'claim_declined', 'renewal_due',
  'payment_failed', 'lapse_warning', 'policy_welcome', 'refund_paid', 'refund_processing',
  'kyc_document_missing', 'complaint_acknowledged', 'policy_cancelled',
];
const ALLOWED = new Set(['contactName','claimNumber','policyNumber','link','amount','date','supplierName','assessorName']);

let pass = 0, fail = 0;
const check = (name, fn) => { try { fn(); pass++; console.log('  ok  ' + name); }
  catch (e) { fail++; console.log('FAIL  ' + name + '\n      ' + e.message); } };

check('all expected events present', () => {
  for (const ev of EXPECTED) assert.ok(catalogue[ev], 'missing ' + ev);
  assert.equal(Object.keys(catalogue).length, 16);
});

for (const ev of EXPECTED) {
  check(ev + ': well-formed, tagged, plain, safe placeholders', () => {
    const m = getMessage(ev);
    assert.ok(m.sms && m.whatsapp && m.email && m.email.subject && m.email.text, 'incomplete');
    const visible = [m.sms, m.whatsapp, m.email.subject, m.email.text].join(' ');
    // plain language — no "KYC" jargon leaked to the customer
    assert.ok(!/\bkyc\b/i.test(visible), 'contains KYC jargon');
    // only approved placeholders
    const used = [...visible.matchAll(/\{([a-zA-Z]+)\}/g)].map(x => x[1]);
    for (const p of used) assert.ok(ALLOWED.has(p), 'bad placeholder {' + p + '}');
    // whatsapp anchors to a claim or policy
    assert.ok(/\{(claimNumber|policyNumber)\}/.test(m.whatsapp), 'whatsapp missing claim/policy ref');
    // brand sign-off on the short channels
    assert.ok(m.sms.includes('— Alpha Direct'), 'sms missing sign-off');
    assert.ok(m.whatsapp.includes('— Alpha Direct'), 'whatsapp missing sign-off');
    // sms stays short-ish
    assert.ok(m.sms.length <= 200, 'sms too long (' + m.sms.length + ')');
  });
}

// The reworked compliance points, locked in as assertions:
check('claim_declined offers recourse (review / complaint / NBFIRA)', () => {
  const t = getMessage('claim_declined').email.text.toLowerCase();
  assert.ok(t.includes('review') || t.includes('complaint') || t.includes('nbfira'), 'no recourse path');
});
check('claim_paid says "sent to your bank" (not cleared funds)', () => {
  assert.ok(/sent to your bank/i.test(getMessage('claim_paid').whatsapp));
});
check('agreement_of_loss explains signing accepts the settlement', () => {
  assert.ok(/accept/i.test(getMessage('agreement_of_loss_to_sign').email.text));
});
for (const ev of ['renewal_due', 'lapse_warning']) {
  check(ev + ' states a date and an amount', () => {
    const t = getMessage(ev).email.text;
    assert.ok(t.includes('{date}') && t.includes('{amount}'), 'missing date/amount');
  });
}

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
