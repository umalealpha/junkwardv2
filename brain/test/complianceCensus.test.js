'use strict';
// complianceCensus.test.js — the census that fills /compliance-summary must be
// SELECT-only and PII-free by construction: every query is an aggregate, reads
// KYC ONLY through the masked view (never a raw customer_kyc column), and the
// output contract is counts-only with the definition-pending metrics empty.
const assert = require('node:assert');
const cc = require('../lib/complianceCensus');

let pass = 0, fail = 0;
const check = (n, fn) => { try { fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };
const checkAsync = async (n, fn) => { try { await fn(); pass++; console.log('  ok  ' + n); }
  catch (e) { fail++; console.log('FAIL  ' + n + '\n      ' + e.message); } };

const SQLS = [cc.BY_CATEGORY_SQL, cc.POLICIES_NO_DOCS_SQL];

async function main() {
  check('every query is SELECT-only (no write/DDL verbs)', () => {
    for (const sql of SQLS) {
      assert.ok(/^\s*SELECT\b/i.test(sql), 'must start with SELECT');
      for (const verb of ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'GRANT', 'REPLACE', 'MERGE']) {
        assert.ok(!new RegExp('\\b' + verb + '\\b', 'i').test(sql), 'must not contain ' + verb);
      }
      assert.ok(!sql.includes(';'), 'single statement only (no ;)');
    }
  });

  check('KYC is read ONLY through the masked view, never the raw table', () => {
    const kyc = cc.POLICIES_NO_DOCS_SQL;
    assert.ok(/\bbrain_customer_kyc\b/i.test(kyc), 'must read the masked view');
    // strip the view name, then assert the raw table name is gone
    assert.ok(!/\bcustomer_kyc\b/i.test(kyc.replace(/brain_customer_kyc/gi, '')), 'must not touch raw customer_kyc');
  });

  check('no raw ID-document / PII value column appears (masked _present/_status/_expiry allowed)', () => {
    // Strip the masked-view columns (presence booleans + status/expiry metadata)
    // first — those are the safe, PII-free projections the whole design rests on.
    // Whatever remains must contain NO raw PII value column.
    const all = SQLS.join('\n').toLowerCase().replace(/\b\w+_(present|status|expiry)\b/g, '');
    for (const col of ['omang_front', 'omang_back', 'passport_no', 'passport_number',
                       'firstname', 'lastname', 'date_of_birth', 'account_num', 'residential', 'omang_number']) {
      assert.ok(!all.includes(col), 'must not select raw PII column: ' + col);
    }
  });

  check('every projected column is an aggregate or a derived label (no row detail)', () => {
    assert.ok(/count\(\*\)/i.test(cc.BY_CATEGORY_SQL) && /group by/i.test(cc.BY_CATEGORY_SQL));
    assert.ok(/count\(\*\)/i.test(cc.POLICIES_NO_DOCS_SQL));
  });

  check('emptyCensus contract: counts-only, definition-pending metrics empty', () => {
    const e = cc.emptyCensus();
    assert.deepEqual(e.by_category, {});
    assert.deepEqual(e.by_broker, {});
    assert.deepEqual(e.claims_kyc, {});
    assert.equal(e.policies_no_docs, 0);
    assert.deepEqual(e.monthly_checks, {});
    assert.deepEqual(e._pending, ['by_broker', 'claims_kyc', 'monthly_checks']);
  });

  await checkAsync('fetchCensus rejects on missing DSN (never silently no-ops)', async () => {
    await assert.rejects(() => cc.fetchCensus(''), /DSN not set/);
  });

  console.log('\n' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
}

main();
