'use strict';
// Unit test for lib/claimForms.js — run: node test/claimForms.test.js
// Zero deps (node:assert). Asserts the Response-PDF mapping is honoured and that
// types with no official form route to a human instead of sending a wrong form.

const assert = require('node:assert');
const { resolveClaimForm } = require('../lib/claimForms');

let pass = 0, fail = 0;
function check(name, fn) {
  try { fn(); pass++; console.log(`  ok  ${name}`); }
  catch (e) { fail++; console.log(`FAIL  ${name}\n      ${e.message}`); }
}

// --- Straight mappings from the Response table ---
check('Motor Accident -> Motor form', () => {
  const r = resolveClaimForm('Motor Accident');
  assert.equal(r.status, 'ok');
  assert.deepEqual(r.forms, ['MOTOR ACCIDENT CLAIM FORM.pdf']);
});

check('Glass (Motor / Non-Motor) -> Glass form', () => {
  const r = resolveClaimForm('Glass (Motor / Non-Motor)');
  assert.equal(r.status, 'ok');
  assert.deepEqual(r.forms, ['GLASS CLAIM FORM.pdf']);
});

// --- The non-obvious ones the README warned about ---
check('Fire -> Property Loss form (NOT a "Fire" form)', () => {
  const r = resolveClaimForm('Fire');
  assert.equal(r.status, 'ok');
  assert.deepEqual(r.forms, ['PROPERTY LOSS CLAIM FORM.pdf']);
});

check('Money -> BOTH Burglary and Fidelity forms', () => {
  const r = resolveClaimForm('Money');
  assert.equal(r.status, 'ok');
  assert.deepEqual(r.forms, ['BURGLARY CLAIM FORM.pdf', 'FEDILITY CLAIM FORM.pdf']);
});

check('Theft/Burglary -> Burglary form', () => {
  const r = resolveClaimForm('Theft/Burglary');
  assert.equal(r.status, 'ok');
  assert.deepEqual(r.forms, ['BURGLARY CLAIM FORM.pdf']);
});

// --- Aliases the tracker/Graphite may send ---
check('alias "WCA" -> Workmen\'s Compensation form', () => {
  const r = resolveClaimForm('WCA');
  assert.equal(r.status, 'ok');
  assert.deepEqual(r.forms, ['WORKMEN_COMPENSATION_FORM.pdf']);
});

check('alias "GIT" -> GIT form', () => {
  assert.deepEqual(resolveClaimForm('GIT').forms, ['GIT CLAIM FORM.pdf']);
});

// --- Types listed in the table but with NO form file: must route to a human ---
for (const t of ['Bonu', 'Accidental Death Insurance', 'Hospital Cash Back']) {
  check(`"${t}" has no form -> needs_human`, () => {
    const r = resolveClaimForm(t);
    assert.equal(r.status, 'needs_human');
  });
}

// --- Unknown / blank never guesses ---
check('unknown type -> needs_human', () => {
  assert.equal(resolveClaimForm('Spaceship Damage').status, 'needs_human');
});
check('blank -> needs_human', () => {
  assert.equal(resolveClaimForm('').status, 'needs_human');
});

// --- Required-docs come through for a seeded type ---
check('Motor Accident carries required supporting docs', () => {
  const r = resolveClaimForm('Motor Accident');
  assert.ok(r.docs.includes('Police Report'));
});

console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
