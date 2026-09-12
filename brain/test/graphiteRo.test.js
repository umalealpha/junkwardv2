'use strict';
// graphiteRo.test.js — the read-only pull's PURE mappers (no DB / no mysql2).
// Run: node test/graphiteRo.test.js
const assert = require('node:assert');
const ro = require('../lib/graphiteRo');

let pass = 0, fail = 0;
function check(n, fn) { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } }

check('buildClaims maps DB rows to the sweep claims shape', () => {
  const rows = [{
    claimNumber: 'G2026000001', claimType: 'Motor',
    dateOfLoss: new Date('2026-05-10T00:00:00Z'),
    dateReported: '2026-05-12 09:00:00', sumInsured: '50000',
  }];
  const c = ro.buildClaims(rows)[0];
  assert.equal(c.claimNumber, 'G2026000001');
  assert.equal(c.claimType, 'Motor');
  assert.equal(c.dateOfLoss, '2026-05-10');
  assert.equal(c.dateReported, '2026-05-12');
  assert.equal(c.sumInsured, 50000);
  assert.strictEqual(c.coverStart, null); // not yet sourced (documented)
  assert.strictEqual(c.estimate, null);
});

check('buildClaims tolerates nulls/empties', () => {
  const c = ro.buildClaims([{ claimNumber: null, claimType: null, dateOfLoss: null, dateReported: null, sumInsured: null }])[0];
  assert.equal(c.claimNumber, '');
  assert.strictEqual(c.sumInsured, null);
  assert.strictEqual(c.dateOfLoss, null);
});

check('buildClaims handles empty / missing input', () => {
  assert.deepEqual(ro.buildClaims(), []);
  assert.deepEqual(ro.buildClaims([]), []);
});

check('assembleRecords returns full shape; pending feeds empty + logged', () => {
  const logs = [];
  const rec = ro.assembleRecords({ claims: [{ claimNumber: 'X' }] }, (m) => logs.push(m));
  assert.deepEqual(Object.keys(rec).sort(), ['claims', 'collections', 'debtors', 'events', 'kyc', 'writtenPremium']);
  assert.equal(rec.claims.length, 1);
  for (const f of ['collections', 'kyc', 'debtors', 'events']) assert.deepEqual(rec[f], []);
  assert.equal(logs.length, ro.PENDING_FEEDS.length); // every gap is visible
});

check('parseDsn extracts host/port/user/db from a mysql URL', () => {
  const cfg = ro.parseDsn('mysql://brain_ro:s3cr3t@graphite-v2-prod-ro.example.rds.amazonaws.com:3306/Graphite_live');
  assert.equal(cfg.host, 'graphite-v2-prod-ro.example.rds.amazonaws.com');
  assert.equal(cfg.port, 3306);
  assert.equal(cfg.user, 'brain_ro');
  assert.equal(cfg.database, 'Graphite_live');
});

check('CLAIMS_SQL is SELECT-only (no writes)', () => {
  assert.match(ro.CLAIMS_SQL, /^\s*SELECT/i);
  assert.ok(!/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE)\b/i.test(ro.CLAIMS_SQL));
});

check('buildCollections maps + computes daysSinceFail + normalises gateway', () => {
  const NOW = new Date('2026-07-17T09:00:00Z');
  const c = ro.buildCollections([{ policyNumber: 'MISx', product: 'Legal Insurance', gateway: 'REALPAY', amount: '800', failedAt: '2026-07-04' }], NOW)[0];
  assert.equal(c.policyNumber, 'MISx');
  assert.equal(c.gateway, 'RealPay');
  assert.equal(c.amount, 800);
  assert.equal(c.daysSinceFail, 13);
  assert.equal(c.remindersSent, 0);
});

check('buildDebtors groups per account; payment ref is ledger id (DPA, not narrative)', () => {
  const d = ro.buildDebtors(
    [{ account: 'A', invDate: '2026-01-01', invAmount: '1000', invRef: 'INV-1' }],
    [{ account: 'A', payDate: '2026-03-30', payAmount: '400', payRef: 987654 }], '2026-04-01')[0];
  assert.equal(d.account, 'A');
  assert.equal(d.asOf, '2026-04-01');
  assert.deepEqual(d.invoices[0], { date: '2026-01-01', amount: 1000, ref: 'INV-1' });
  assert.equal(d.payments[0].amount, 400);
  assert.equal(d.payments[0].ref, '987654'); // ledger id, not a bank narrative
});

check('buildDebtors output feeds ageing.js buckets', () => {
  const { age } = require('../lib/ageing');
  const d = ro.buildDebtors([{ account: 'B', invDate: '2025-11-02', invAmount: 1500, invRef: 'INV-9' }], [], '2026-04-01')[0];
  assert.equal(age(d.invoices, d.payments, d.asOf).buckets.b120plus, 1500);
});

check('buildKyc maps verdict/metadata; ocr.confidence null (no OCR store)', () => {
  const k = ro.buildKyc([{ kycId: 42, product: null, firstRequestedDaysAgo: 20, remindersSent: 0,
    omang_front_present: 1, omang_front_status: 1, omang_expiry: '2030-01-01',
    passport_present: 0, passport_status: null }])[0];
  assert.equal(k.ref, 'KYC-42');
  const omang = k.docs.find((d) => d.type === 'omang_front');
  assert.equal(omang.present, true);
  assert.strictEqual(omang.ocr.confidence, null);
  assert.equal(omang.ocr.expiry, '2030-01-01');
  assert.strictEqual(omang.ocr.detectedType, null); // DPA: raw OCR text excluded
});

check('buildEvents maps total_loss + payment_received + claim_registered (deferred never emitted)', () => {
  const evs = ro.buildEvents({
    totalLoss: [{ claimNumber: 'G1', policyNumber: 'P1', amount: '90000' }],
    paymentReceived: [{ policyNumber: 'P2', amount: 500 }],
    claimRegistered: [{ claimNumber: 'G2026005207', policyNumber: 'DOMG1' }],
  });
  assert.equal(evs.length, 3);
  assert.equal(evs[0].event, 'total_loss');
  assert.equal(evs[0].ctx.claimNumber, 'G1');
  assert.equal(evs[0].ctx.amount, 90000);
  assert.equal(evs[1].event, 'payment_received');
  const cr = evs.find((e) => e.event === 'claim_registered');
  assert.ok(cr, 'claim_registered emitted');
  assert.equal(cr.ctx.claimNumber, 'G2026005207');
  assert.equal(cr.ctx.policyNumber, 'DOMG1');
  assert.ok(!evs.some((e) => ro.DEFERRED_EVENTS.includes(e.event)));
});

check('claim_registered is derived (out of deferred); claim_paid stays deferred (no payment-date)', () => {
  assert.ok(!ro.DEFERRED_EVENTS.includes('claim_registered'), 'claim_registered must be derived now');
  assert.ok(ro.DEFERRED_EVENTS.includes('claim_paid'), 'claim_paid must remain deferred');
  assert.match(ro.CLAIM_REGISTERED_SQL, /^\s*SELECT/i, 'SELECT-only');
  assert.ok(!/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE)\b/i.test(ro.CLAIM_REGISTERED_SQL), 'no writes');
  assert.match(ro.CLAIM_REGISTERED_SQL, /GROUP BY c\.claim_number/i, 'dedupe by claim number');
});

check('all feed SQL is SELECT-only (no writes)', () => {
  for (const sql of [ro.CLAIMS_SQL, ro.COLLECTIONS_SQL, ro.DEBTOR_POLICY_SUBQUERY,
    ro.debtorInvoicesSql('1,2'), ro.debtorPaymentsSql('1,2'), ro.KYC_SQL, ro.TOTAL_LOSS_SQL,
    ro.PAYMENT_RECEIVED_SQL, ro.CLAIM_REGISTERED_SQL, ro.WRITTEN_PREMIUM_SQL]) {
    assert.match(sql, /select/i);
    assert.ok(!/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE)\b/i.test(sql));
  }
});

check('KYC reads the MASKED VIEW, never the raw customer_kyc table (DPA)', () => {
  assert.match(ro.KYC_SQL, /FROM\s+brain_customer_kyc/i, 'must read the masked view');
  assert.ok(!/FROM\s+customer_kyc\b/i.test(ro.KYC_SQL), 'must NOT read the raw customer_kyc table');
  // presence is precomputed in the view — the query must not derive it from raw
  // document columns (omang/passport/…), which would require raw-column access.
  assert.ok(!/IS NOT NULL/i.test(ro.KYC_SQL), 'presence must come from the view, not raw columns');
  // the KYC_DOC_CATALOG aliases the view must supply are all selected
  assert.match(ro.KYC_SQL, /omang_front_present/);
  assert.match(ro.KYC_SQL, /directors_id_present/);
});

check('debtors payment SQL uses ledger id, not the bank narrative (DPA)', () => {
  const sql = ro.debtorPaymentsSql('1');
  assert.match(sql, /pl\.id\s+AS\s+payRef/i);
  assert.ok(!/trans_ref/i.test(sql));
});

check('debtor invoice/payment queries take an explicit id list — NO nested subquery (perf)', () => {
  const inv = ro.debtorInvoicesSql('10,20,30');
  assert.match(inv, /pl\.policy_id IN \(10,20,30\)/);
  // the expensive GROUP BY/HAVING selection must NOT be inlined here anymore
  assert.ok(!/GROUP BY|HAVING/i.test(inv), 'selection subquery must not be inlined in the invoice query');
  assert.ok(!/GROUP BY|HAVING/i.test(ro.debtorPaymentsSql('10')), 'nor in the payment query');
  // the selection lives once, standalone
  assert.match(ro.DEBTOR_POLICY_SUBQUERY, /GROUP BY[\s\S]*HAVING/i);
});

// async resilience test — a denied/failed feed degrades to EMPTY and is logged,
// it must NOT abort the whole pull (regression: any denied table → fixture).
(async () => {
  const logs = [];
  const fakeConn = {
    // real mysql2 returns [rows, fields]; every granted feed → empty rows,
    // the KYC feed (only SQL that touches customer_kyc) is DENIED.
    query: async (sql) => {
      if (/customer_kyc/i.test(sql)) {
        throw new Error("SELECT command denied to user 'brain_ro'@'x' for table `Graphite_live`.`customer_kyc`");
      }
      return [[]];
    },
  };
  try {
    const rec = await ro.runFeeds(fakeConn, (m) => logs.push(m)); // resolves — does NOT throw
    assert.deepEqual(Object.keys(rec).sort(), ['claims', 'collections', 'debtors', 'events', 'kyc', 'writtenPremium']);
    for (const f of ['claims', 'collections', 'debtors', 'events', 'kyc']) assert.ok(Array.isArray(rec[f]), f + ' should be an array');
    assert.equal(rec.kyc.length, 0); // the denied feed is empty
    assert.equal(logs.filter((m) => /feed 'kyc' failed/i.test(m)).length, 1); // logged exactly once
    assert.equal(logs.filter((m) => /feed '(claims|collections|debtors|events)' failed/i.test(m)).length, 0); // no other feed affected
    pass++; console.log("  ok  runFeeds: a denied feed (customer_kyc) degrades to empty; other feeds unaffected");
  } catch (e) {
    fail++; console.log('FAIL  runFeeds resilience\n      ' + e.message);
  }

  // events sub-feed resilience — a denied claim_registered (brain_ro missing
  // claims.created_at, seen on prod 2026-07-29) must NOT take down total_loss +
  // payment_received. Regression guard for the T6 cascade.
  try {
    const evLogs = [];
    const conn = { query: async (sql) => {
      if (/claim_number IS NOT NULL/i.test(sql)) { // uniquely the claim_registered query
        throw new Error("SELECT command denied to user 'brain_ro'@'x' for column 'created_at' in table 'claims'");
      }
      if (/write_off/i.test(sql)) return [[{ claimNumber: 'G1', policyNumber: 'P1', amount: 100 }]]; // total_loss
      if (/payment_transactions/i.test(sql)) return [[{ policyNumber: 'P2', amount: 50 }]];            // payment_received
      return [[]];
    } };
    const rec = await ro.runFeeds(conn, (m) => evLogs.push(m));
    const kinds = rec.events.map((e) => e.event);
    assert.ok(kinds.includes('total_loss'), 'total_loss must survive a claim_registered denial');
    assert.ok(kinds.includes('payment_received'), 'payment_received must survive');
    assert.ok(!kinds.includes('claim_registered'), 'denied claim_registered omitted');
    assert.equal(evLogs.filter((m) => /event source 'claim_registered' failed/i.test(m)).length, 1, 'logged once');
    assert.equal(evLogs.filter((m) => /feed 'events' failed/i.test(m)).length, 0, 'the events feed itself must NOT fail');
    pass++; console.log('  ok  runFeeds: a denied event source degrades alone; sibling events survive');
  } catch (e) {
    fail++; console.log('FAIL  events sub-feed resilience\n      ' + e.message);
  }

  // debtors: the expensive net-balance selection must run ONCE (not once per query)
  try {
    const seen = [];
    const conn = { query: async (sql) => {
      seen.push(sql);
      if (/GROUP BY[\s\S]*HAVING/i.test(sql)) return [[{ policy_id: 7 }, { policy_id: 8 }]]; // the selection
      return [[]];
    } };
    await ro.runFeeds(conn, () => {});
    const selectionCalls = seen.filter((s) => /GROUP BY[\s\S]*HAVING/i.test(s)).length;
    assert.equal(selectionCalls, 1, 'debtor selection subquery must run exactly once (was twice — inlined in both queries)');
    assert.ok(seen.some((s) => /pl\.policy_id IN \(7,8\)/.test(s)), 'invoice/payment queries use the selected id list');
    pass++; console.log('  ok  runFeeds: debtor net-balance selection runs ONCE, then id-list queries');
  } catch (e) {
    fail++; console.log('FAIL  debtor selection run-once\n      ' + e.message);
  }

  console.log('\n' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})();
