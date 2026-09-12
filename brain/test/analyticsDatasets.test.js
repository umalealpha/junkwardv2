'use strict';
// analyticsDatasets.test.js — the read-only analytics feeds for the Alpha Brain
// Data-Analytics workbook tabs + the Graphite→Omni push. The heavy aggregation
// lives in SQL (Pramod verifies it on the replica), so here we (a) unit-test the
// pure JS builders and (b) guard the SQL's safety + DPA properties so they can't
// be silently dropped: SELECT-only, no writes, no PII columns, non-voided reserve
// rule, FY window, ex-VAT divisor, group/BONU exclusion, and KYC via the masked
// view only.
const assert = require('node:assert');
const ro = require('../lib/graphiteRo');

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

// ── builders ──────────────────────────────────────────────────────────────────
check('buildClaimsByGroup maps + rounds', () => {
  const g = ro.buildClaimsByGroup([{ group: 'MIS', reserve: 100.005, payment: 40 }]);
  assert.deepEqual(g[0], { group: 'MIS', reserve: 100.01, payment: 40 });
  assert.deepEqual(ro.buildClaimsByGroup(null), []);
});

check('buildPremiumByGroup maps + rounds', () => {
  assert.deepEqual(ro.buildPremiumByGroup([{ group: 'COMG', premium_ex_vat: 999.999 }])[0],
    { group: 'COMG', premium_ex_vat: 1000 });
});

check('buildClaimsByType keeps counts as integers, money rounded', () => {
  const t = ro.buildClaimsByType([{ claim_type: 'MOTOR', claim_count: '7', reserve: 12.3, payment: null }])[0];
  assert.deepEqual(t, { claim_type: 'MOTOR', claim_count: 7, reserve: 12.3, payment: 0 });
});

check('buildInforceByType maps product name + count', () => {
  assert.deepEqual(ro.buildInforceByType([{ claim_type: 'Motor Comprehensive', inforce_policies: '20' }])[0],
    { claim_type: 'Motor Comprehensive', inforce_policies: 20 });
});

check('buildPremiumByLine keeps motor/non_motor split', () => {
  const l = ro.buildPremiumByLine([{ product_line: 'X', premium_ex_vat: 100, motor: 100, non_motor: 0 }])[0];
  assert.equal(l.motor + l.non_motor, l.premium_ex_vat);
});

check('buildLossRatioDetail returns the four fixed lines; BONU is null (never invented)', () => {
  const d = ro.buildLossRatioDetail(
    [{ detail: 'Instant Insurance', premium_ex_vat: 500 }, { detail: 'Motor Comprehensive', premium_ex_vat: 300 }],
    [{ detail: 'Instant Insurance', reserve: 100, paid: 40 }]);
  assert.equal(d.length, 4);
  const inst = d.find((x) => x.detail === 'Instant Insurance');
  assert.deepEqual(inst, { detail: 'Instant Insurance', premium_ex_vat: 500, reserve: 100, paid: 40 });
  const mc = d.find((x) => x.detail === 'Motor Comprehensive');
  assert.deepEqual(mc, { detail: 'Motor Comprehensive', premium_ex_vat: 300, reserve: 0, paid: 0 });
  const bonu = d.find((x) => x.detail === 'Bonu Legal Insurance');
  assert.strictEqual(bonu.premium_ex_vat, null);
  assert.strictEqual(bonu.reserve, null);
  assert.strictEqual(bonu.paid, null);
});

check('buildMajorClaims maps status→open/closed + repudiated, no names', () => {
  const rows = ro.buildMajorClaims([
    { claim_no: 'G1', claim_type: 'FIRE', reserve: 400000, paid: 0, claim_status: 'Closed' },
    { claim_no: 'G2', claim_type: 'MOTOR', reserve: 500000, paid: 100, claim_status: 'Rejected' },
    { claim_no: 'G3', claim_type: 'MOTOR', reserve: 350000, paid: 0, claim_status: 'Pending' },
  ]);
  assert.equal(rows[0].status, 'closed');
  assert.equal(rows[0].repudiated, false);
  assert.equal(rows[1].status, 'open');       // a rejection is not 'Closed'
  assert.equal(rows[1].repudiated, true);
  assert.equal(rows[2].status, 'open');
  assert.equal(rows[2].repudiated, false);
  // no name-shaped keys leak
  assert.ok(!Object.keys(rows[0]).some((k) => /name|omang|dob|bank|address/i.test(k)));
});

check('buildBrokerLr merges sides; lr_on_reserve is a FRACTION and guards /0', () => {
  const rows = ro.buildBrokerLr(
    [{ broker: 'Acme', claim_count: 3, reserve: 700, payment: 200 },
     { broker: 'NoPrem', claim_count: 1, reserve: 50, payment: 0 }],
    [{ broker: 'Acme', premium_fy: 1000 }]);
  const acme = rows.find((r) => r.broker === 'Acme');
  assert.equal(acme.lr_on_reserve, 0.7);         // 700 / 1000 — fraction, not %
  const nop = rows.find((r) => r.broker === 'NoPrem');
  assert.strictEqual(nop.lr_on_reserve, null);   // premium_fy 0 → null, no divide-by-zero
  assert.equal(nop.premium_fy, 0);
});

check('buildKycCompleteness computes pct_complete fraction + guards /0', () => {
  const rows = ro.buildKycCompleteness([
    { branch: 'BranchA', agent: 42, policies: 4, complete: 3 },
    { branch: 'Empty', agent: null, policies: 0, complete: 0 },
  ]);
  assert.equal(rows[0].pct_complete, 0.75);
  assert.equal(rows[0].agent, '42');             // agent is a stringified id, never a name
  assert.strictEqual(rows[1].pct_complete, null);
  assert.strictEqual(rows[1].agent, null);
});

check('buildRenewalsTrigger keys on policy/gfs, carries NO premium field', () => {
  const r = ro.buildRenewalsTrigger([{ policy_no: 'MIS9', gfs_ref: 'GFS1', product_line: 'Legal', days_to_expiry: 12, expiry_date: '2026-08-18' }])[0];
  assert.equal(r.policy_no, 'MIS9');
  assert.equal(r.gfs_ref, 'GFS1');
  assert.equal(r.days_to_expiry, 12);
  assert.equal(r.expiry_date, '2026-08-18');
  assert.ok(!('premium' in r) && !('premium_ex_vat' in r), 'renewals trigger must carry NO summed premium');
});

check('buildDebtorsAging maps ageing buckets to the b61_90/b91_120/over_120 shape', () => {
  // invoice 200 days old, unpaid → lands in >120 bucket.
  const rows = ro.buildDebtorsAging([{ account: 'POL1', invoices: [{ date: '2025-11-02', amount: 1500, ref: 'INV1' }], payments: [] }], '2026-04-01');
  const r = rows[0];
  assert.equal(r.reference, 'POL1');
  assert.equal(r.over_120, 1500);
  assert.equal(r.b61_90, 0);
  assert.equal(r.b91_120, 0);
  assert.equal(r.overdue, 1500);
  assert.equal(r.balance, 1500);
});

// ── SQL safety + DPA guards ─────────────────────────────────────────────────────
const ALL_SQL = {
  CLAIMS_BY_GROUP_SQL: ro.CLAIMS_BY_GROUP_SQL,
  PREMIUM_BY_GROUP_SQL: ro.PREMIUM_BY_GROUP_SQL,
  CLAIMS_BY_TYPE_SQL: ro.CLAIMS_BY_TYPE_SQL,
  INFORCE_BY_TYPE_SQL: ro.INFORCE_BY_TYPE_SQL,
  PREMIUM_BY_LINE_SQL: ro.PREMIUM_BY_LINE_SQL,
  LOSS_RATIO_PREMIUM_SQL: ro.LOSS_RATIO_PREMIUM_SQL,
  LOSS_RATIO_CLAIMS_SQL: ro.LOSS_RATIO_CLAIMS_SQL,
  MAJOR_CLAIMS_SQL: ro.MAJOR_CLAIMS_SQL,
  BROKER_LR_CLAIMS_SQL: ro.BROKER_LR_CLAIMS_SQL,
  BROKER_LR_PREMIUM_SQL: ro.BROKER_LR_PREMIUM_SQL,
  KYC_COMPLETENESS_SQL: ro.KYC_COMPLETENESS_SQL,
  RENEWALS_TRIGGER_SQL: ro.RENEWALS_TRIGGER_SQL,
};

check('every analytics SQL is SELECT-only (no write keywords)', () => {
  for (const [name, sql] of Object.entries(ALL_SQL)) {
    assert.match(sql, /^\s*SELECT/i, name + ' must start with SELECT');
    assert.ok(!/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|REPLACE|MERGE)\b/i.test(sql), name + ' must not write');
  }
});

check('no analytics SQL selects a PII column', () => {
  // Raw-PII column names must never appear. The masked-view PRESENCE BOOLEANS
  // (omang_front_present, passport_present, …) are PII-SAFE — they are booleans in
  // brain_customer_kyc, not the underlying Omang/passport values — so strip those
  // known presence tokens before scanning. (agent_id is a numeric staff id, not a
  // name — explicitly allowed as a key.)
  const presence = ['omang_front_present', 'omang_back_present', 'passport_present', 'poi_present',
    'por_present', 'dl_present', 'kyc_form_present', 'coi_present', 'directors_id_present'];
  const banned = ['firstName', 'lastName', 'omang', 'passport_number', 'msisdn', 'date_of_birth',
    'dob', 'residential', 'bank_account', 'account_number', 'customerName', 'trans_ref'];
  for (const [name, sql] of Object.entries(ALL_SQL)) {
    let scrubbed = sql;
    for (const p of presence) scrubbed = scrubbed.split(p).join('');
    for (const bad of banned) assert.ok(!scrubbed.includes(bad), name + ' must not select ' + bad);
  }
});

check('claim reserve/payment SQL applies the non-voided rule', () => {
  for (const name of ['CLAIMS_BY_GROUP_SQL', 'CLAIMS_BY_TYPE_SQL', 'LOSS_RATIO_CLAIMS_SQL', 'MAJOR_CLAIMS_SQL', 'BROKER_LR_CLAIMS_SQL']) {
    assert.ok(/is_payment_voided IS NULL OR crc\.is_payment_voided = 0/i.test(ALL_SQL[name]),
      name + ' must exclude voided coverage rows');
  }
});

check('claim reserve SQL is NET of subrogation + salvage (matches the claim screen)', () => {
  // The Graphite claim screen reports reserve net of recoveries
  // (Admin/ClaimsController.php:434). Every reserve site must do the same so the
  // workbook reconciles to the screens (CFO/Babusi 9 Sep). COALESCE guards the
  // NULL-subtraction trap.
  for (const name of ['CLAIMS_BY_GROUP_SQL', 'CLAIMS_BY_TYPE_SQL', 'LOSS_RATIO_CLAIMS_SQL', 'MAJOR_CLAIMS_SQL', 'BROKER_LR_CLAIMS_SQL']) {
    assert.ok(/subrogation_reserve/i.test(ALL_SQL[name]), name + ' must net subrogation recoveries');
    assert.ok(/salvage_reserve/i.test(ALL_SQL[name]), name + ' must net salvage recoveries');
  }
});

check('premium SQL is FY-scoped, ex-VAT (VAT_DIVISOR), dedup MAX(id), CANCEL negated', () => {
  // Stored premium verified NET/ex-VAT on the replica 2026-08-06 (policies.premium
  // has a separate vat column; a clean ISSUED action premium equals the net policy
  // premium, not 1.14x), so VAT_DIVISOR = 1 (no division). The premium SQL must
  // still route through the named divisor so the basis is a one-line flip if
  // Finance ever confirms otherwise.
  for (const name of ['PREMIUM_BY_GROUP_SQL', 'PREMIUM_BY_LINE_SQL', 'LOSS_RATIO_PREMIUM_SQL', 'BROKER_LR_PREMIUM_SQL']) {
    const s = ALL_SQL[name];
    assert.ok(/\/ 1(\.\d+)?/.test(s), name + ' must divide by the named VAT_DIVISOR (1 = premium stored ex-VAT)');
    assert.ok(/MAX\(id\)/i.test(s), name + ' must dedup by MAX(id)');
    assert.ok(/GROUP BY policy_id, effective_from, effective_to/i.test(s), name + ' dedup key = term period');
    assert.ok(/status = 'ISSUED'/i.test(s), name + ' ISSUED only');
    assert.ok(/deleted_at IS NULL/i.test(s), name + ' exclude soft-deleted');
    assert.ok(/CANCEL.*-ABS/i.test(s), name + ' CANCEL must be negated');
    assert.ok(/07-01/.test(s), name + ' must use the 1-July FY start');
  }
});

check('claims book feeds are all-time; ratio feeds FY-scoped on a RELIABLE date', () => {
  // CFO 2026-09-02: reported_date is NULL on ~97% of claims, so an FY filter on it
  // dropped ~99% (showed 53 of 4,502). claims_by_type + claims_by_group are now
  // all-time book/count cards (no date filter), consistent with major_claims.
  for (const name of ['CLAIMS_BY_TYPE_SQL', 'CLAIMS_BY_GROUP_SQL']) {
    assert.ok(!/reported_date\s*>=/i.test(ALL_SQL[name]),
      name + ' must be all-time (no reported_date FY filter)');
  }
  // loss_ratio_detail + broker_lr stay FY-scoped (like-with-like vs FY premium) but
  // on COALESCE(reported_date, incident_date, created_at) — not the sparse reported_date.
  for (const name of ['LOSS_RATIO_CLAIMS_SQL', 'BROKER_LR_CLAIMS_SQL']) {
    assert.ok(/COALESCE\(c\.reported_date, c\.incident_date, DATE\(c\.created_at\)\)\s*>=/i.test(ALL_SQL[name])
      && /07-01/.test(ALL_SQL[name]),
      name + ' must FY-scope claims on COALESCE(reported_date, incident_date, created_at)');
  }
});

check('group feeds exclude BONU/OTHER from every total', () => {
  for (const name of ['CLAIMS_BY_GROUP_SQL', 'PREMIUM_BY_GROUP_SQL']) {
    assert.ok(/IN \('COMG','DOMG','MIS'\)/.test(ALL_SQL[name]), name + " must total only COMG/DOMG/MIS");
    assert.ok(!/BONU/i.test(ALL_SQL[name]), name + ' must not reference a BONU source');
  }
});

check('KYC completeness reads the masked view, never raw customer_kyc (DPA)', () => {
  assert.match(ro.KYC_COMPLETENESS_SQL, /FROM brain_customer_kyc/i, 'must read the masked view');
  assert.ok(!/FROM\s+customer_kyc\b/i.test(ro.KYC_COMPLETENESS_SQL), 'must NOT read the raw table');
});

check('major_claims uses the 300000 reserve floor and carries no name', () => {
  assert.ok(/HAVING reserve > 300000/i.test(ro.MAJOR_CLAIMS_SQL), 'reserve > 300000 floor');
  assert.ok(!/name/i.test(ro.MAJOR_CLAIMS_SQL), 'no name column');
});

check('renewals_trigger selects days-to-expiry ≤ 30 and NO premium (double-count block)', () => {
  const s = ro.RENEWALS_TRIGGER_SQL;
  assert.ok(/INTERVAL 30 DAY/i.test(s), 'must window to 30 days to expiry');
  assert.ok(/expiry_date >= CURDATE\(\)/i.test(s), 'must exclude already-expired');
  assert.ok(!/premium/i.test(s), 'renewals trigger SQL must not select premium (summed-premium is BLOCKED)');
});

// ── fetchAnalytics resilience (mock connection) ─────────────────────────────────
(async () => {
  try {
    const logs = [];
    // major_claims query is DENIED; every other query returns empty rows.
    const conn = {
      query: async (sql) => {
        if (/HAVING reserve > 300000/i.test(sql)) throw new Error("SELECT command denied for table claim_reserves_coverages");
        return [[]];
      },
      end: async () => {},
    };
    // Patch mysql2 by injecting the fake connection through a shim: fetchAnalytics
    // lazy-requires mysql2/promise, so we stub the module cache.
    const Module = require('module');
    const orig = Module._load;
    Module._load = function (request, parent, isMain) {
      if (request === 'mysql2/promise') return { createConnection: async () => conn };
      return orig.apply(this, arguments);
    };
    let out;
    try {
      out = await ro.fetchAnalytics('mysql://u:p@h:3306/Graphite_live', (m) => logs.push(m));
    } finally {
      Module._load = orig;
    }
    const keys = Object.keys(out).sort();
    assert.deepEqual(keys, ['broker_lr', 'claims_by_group', 'claims_by_type', 'inforce_by_type',
      'kyc_completeness', 'loss_ratio_detail', 'major_claims', 'premium_by_group', 'premium_by_line', 'renewals_trigger']);
    assert.deepEqual(out.major_claims, [], 'a denied dataset degrades to []');
    // loss_ratio_detail still returns the four canonical rows even on empty data.
    assert.equal(out.loss_ratio_detail.length, 4);
    assert.ok(logs.some((m) => /major_claims/.test(m)), 'the denied dataset is logged');
    assert.ok(!logs.some((m) => /claims_by_group|premium_by_group/.test(m)), 'granted datasets are not logged as failed');
    pass++; console.log('  ok  fetchAnalytics: a denied dataset degrades to []; others unaffected');
  } catch (e) {
    fail++; console.log('FAIL  fetchAnalytics resilience\n      ' + e.message);
  }

  console.log('\n' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})();
