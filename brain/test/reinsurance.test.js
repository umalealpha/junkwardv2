'use strict';
// Unit test for lib/reinsurance.js — run: node test/reinsurance.test.js
const assert = require('node:assert');
const { assessRecovery, concentrationCheck } = require('../lib/reinsurance');

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

check('gross loss over retention → recover the excess (treaty)', () => {
  const r = assessRecovery({ grossLoss: 1000000 }, { retention: 500000 });
  assert.equal(r.layer, 'treaty');
  assert.equal(r.recoveryAmount, 500000);
  assert.ok(r.recoverable);
  assert.ok(r.notify.includes('Reinsurance team'));
});

check('treaty recovery capped at the treaty limit', () => {
  const r = assessRecovery({ grossLoss: 5000000 }, { retention: 500000, limit: 2000000 });
  assert.equal(r.recoveryAmount, 2000000);
});

check('facultative placement → recover its share', () => {
  const r = assessRecovery({ grossLoss: 1000000, facShare: 0.3 }, { retention: 500000 });
  assert.equal(r.layer, 'facultative');
  assert.equal(r.recoveryAmount, 300000);
});

check('loss within retention → nothing recoverable', () => {
  const r = assessRecovery({ grossLoss: 200000 }, { retention: 500000 });
  assert.equal(r.recoverable, false);
  assert.equal(r.recoveryAmount, 0);
  assert.equal(r.notify.length, 0);
});

check('missing treaty (no retention) → refuse, do NOT recover the full gross', () => {
  const r = assessRecovery({ grossLoss: 1000000 }, {}); // no retention on file
  assert.equal(r.recoverable, false);
  assert.equal(r.recoveryAmount, 0);
  assert.equal(r.layer, 'unknown');
  assert.equal(r.needsTreaty, true);
  assert.ok(r.notify.includes('Reinsurance team')); // nudge to supply the terms
});

check('explicit zero retention is still honoured (not treated as missing)', () => {
  const r = assessRecovery({ grossLoss: 1000000 }, { retention: 0 });
  assert.equal(r.layer, 'treaty');
  assert.equal(r.recoveryAmount, 1000000);
});

check('concentration: a reinsurer over the limit is flagged', () => {
  const r = concentrationCheck([
    { reinsurer: 'Grand Re', share: 0.25 },
    { reinsurer: 'Grand Re', share: 0.15 },   // 0.40 total
    { reinsurer: 'FMRE', share: 0.22 },
  ], 0.35);
  assert.equal(r.byReinsurer['Grand Re'], 40);
  assert.ok(r.flags.some((f) => f.reinsurer === 'Grand Re'));
  assert.ok(!r.flags.some((f) => f.reinsurer === 'FMRE')); // 22% under 35%
});

check('concentration: all within limit → no flags', () => {
  const r = concentrationCheck([{ reinsurer: 'A', share: 0.2 }, { reinsurer: 'B', share: 0.2 }], 0.35);
  assert.equal(r.flags.length, 0);
});

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
