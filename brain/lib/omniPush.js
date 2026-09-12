'use strict';
// omniPush.js — the one-way, gated Graphite→Omni analytics PUSH (OUTBOUND).
//
// The mirror of omniIntel.js (which PULLS Omni's counts-only intel into us):
// here we POST our own PII-free analytics snapshot OUT to Omni's graphite-ingest
// endpoint, wrapped one Appendix-A v1 envelope per dataset. Read-only, arms-off:
// this never writes back to Graphite and never touches customer data — the rows
// come straight from the brain's own store.readAnalytics() snapshot, which is
// counts/totals only.
//
// DOUBLE LOCK — this is DEAD (a pure no-op) unless BOTH are true:
//   1. a key is present  (OMNI_GRAPHITE_INGEST_KEY, via config.omniGraphiteIngestKey)
//   2. the push is armed (BRAIN_OMNI_PUSH_ARMED==='true', armed.refuseOmniPushIfOff)
// No key → { ok:false, skipped:true, reason:'no_ingest_key' } (mirrors the mailer
// no-keys contract — never default-send). Disarmed → { skipped, reason:'push_disarmed' }.
// So a test env (no key) and an un-armed prod (key but push off) both no-op by
// construction, before a single byte leaves the process.
//
// DECOUPLED from BRAIN_LIVE_ARMS (CFO 2026-08-31): this outbound feed is PII-free,
// counts-only, and never touches a customer or writes back to Graphite, so it has
// its OWN narrow arm — the analytics push can go live to Omni while the customer-
// action arm (cancellations / SMS) stays OFF. See lib/armed.omniPushOn.

const armed = require('./armed');

const TIMEOUT_MS = 12_000;

// The analytics datasets we ship, in a fixed order. Each is an array-of-rows on
// the snapshot (store.readAnalytics). Anything not present / not an array on the
// snapshot is simply skipped for this run (the feed hasn't produced it yet).
const DATASETS = [
  'claims_by_group', 'premium_by_group', 'claims_by_type', 'inforce_by_type',
  'premium_by_line', 'loss_ratio_detail', 'major_claims', 'broker_lr',
  'kyc_completeness', 'debtors_aging', 'renewals_trigger',
];

// Defensive PII guard — a FAITHFUL MIRROR of the Omni receiver's screen
// (alpha-finance integrations/graphite_ingest.py). Omni REJECTS the whole
// dataset (422) on the first row that trips its screen, so we drop the offending
// FIELD here (field-level) rather than let a whole push bounce. Three parts, all
// matching Omni exactly: (1) banned field NAMES, (2) nested values ("flat rows
// only"), (3) value SHAPES (email / Omang / long number). The snapshot is
// already counts-only; this guarantees a push can never be rejected.
const PII_KEYS = /(^|_)(omang|id_?number|idno|passport|national_id|msisdn|phone|mobile|email|account|acct|iban|address|dob|birth|name|surname|firstname|lastname|policy_?number|policyno)($|_)/i;
// Omni's exact _BANNED_FIELDS set (normalised) — the names its screen refuses.
const OMNI_BANNED_FIELDS = new Set([
  'omang', 'id_number', 'idnumber', 'national_id', 'passport', 'passport_no',
  'bank_account', 'account_number', 'accountnumber', 'account_no', 'iban',
  'card_number', 'cardnumber', 'residential_address', 'home_address',
  'physical_address', 'street_address', 'postal_address', 'date_of_birth',
  'dob', 'birth_date', 'next_of_kin', 'medical', 'diagnosis', 'icd10',
  'phone', 'mobile', 'cell', 'msisdn', 'email', 'email_address',
  'first_name', 'last_name', 'full_name', 'surname', 'member_name',
  'insured_name', 'client_name', 'policyholder', 'policyholder_name',
]);
// Omni's exact value-shape screens (applied to STRING values only — it skips
// int/float/bool, and so do we).
const _OMNI_EMAIL_RE = /[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/;
const _OMNI_OMANG_RE = /\b\d{9}\b/;      // Botswana national ID
const _OMNI_LONGNUM_RE = /\b\d{10,}\b/;  // bank / card / phone

function _valueTripsOmni(v) {
  if (v == null || typeof v === 'number' || typeof v === 'boolean') return false;
  const s = String(v);
  if (s.length > 4096) return true;
  return _OMNI_EMAIL_RE.test(s) || _OMNI_OMANG_RE.test(s) || _OMNI_LONGNUM_RE.test(s);
}

function scrubRow(row) {
  if (!row || typeof row !== 'object' || Array.isArray(row)) return { row, dropped: [] };
  const out = {};
  const dropped = [];
  for (const [k, v] of Object.entries(row)) {
    const norm = String(k).trim().toLowerCase().replace(/[\s-]/g, '_');
    // (1) banned name, (2) nested value, (3) value-shape — any of these would
    // make Omni bounce the dataset, so drop the field and keep the rest.
    if (PII_KEYS.test(k) || OMNI_BANNED_FIELDS.has(norm)
      || (v !== null && typeof v === 'object')   // nested dict / array / etc.
      || _valueTripsOmni(v)) {
      dropped.push(k);
      continue;
    }
    out[k] = v;
  }
  return { row: out, dropped };
}

// Build one Appendix-A v1 envelope for a single dataset.
function buildEnvelope(name, rows, generatedAt) {
  const cleanRows = [];
  const droppedKeys = new Set();
  for (const r of Array.isArray(rows) ? rows : []) {
    const { row, dropped } = scrubRow(r);
    dropped.forEach((d) => droppedKeys.add(d));
    cleanRows.push(row);
  }
  return {
    envelope: {
      schema_version: 'v1',
      dataset: name,
      generated_at: generatedAt || new Date().toISOString(),
      fy: 'FY27',
      basis: { currency: 'BWP', vat_pct: 14, premium_basis: 'ex_vat' },
      rows: cleanRows,
    },
    droppedKeys: [...droppedKeys],
  };
}

async function postEnvelope(url, key, envelope) {
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), TIMEOUT_MS);
  let resp;
  try {
    resp = await fetch(url, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${key}`,
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify(envelope),
      signal: ctrl.signal,
    });
  } finally {
    clearTimeout(timer);
  }
  if (!resp || !resp.ok) throw new Error(`Omni ingest HTTP ${resp ? resp.status : 'no-response'}`);
  return resp.status;
}

/**
 * pushAnalyticsToOmni({ url, key, snapshot, log }) -> summary
 *
 * Hard no-op unless keyed AND armed. Otherwise POSTs one Appendix-A v1 envelope
 * per dataset to `url`. A failed dataset is logged and recorded but does NOT
 * abort the others. Returns { ok, sent:[], skipped:[], failed:[] } (plus the
 * skipped-whole-run shape { ok:false, skipped:true, reason } when the locks bite).
 */
async function pushAnalyticsToOmni({ url, key, snapshot, log } = {}) {
  const warn = typeof log === 'function' ? log : console.warn;

  // LOCK 1 — no key or no url → dead. Never default-send (mirrors mailer).
  if (!key || !url) {
    return { ok: false, skipped: true, reason: 'no_ingest_key' };
  }

  // LOCK 2 — push disarmed → dead, even with a key present. refuseOmniPushIfOff
  // returns TRUE when the caller must NOT proceed (BRAIN_OMNI_PUSH_ARMED !== 'true').
  // This is the DEDICATED arm for the outbound analytics push — independent of
  // BRAIN_LIVE_ARMS (cancellations / SMS), which stays OFF.
  if (armed.refuseOmniPushIfOff('omni:graphite-ingest', { url, datasets: DATASETS.length })) {
    return { ok: false, skipped: true, reason: 'push_disarmed' };
  }

  const snap = snapshot || {};
  const generatedAt = snap.generatedAt || new Date().toISOString();
  const sent = [];
  const skipped = [];
  const failed = [];

  for (const name of DATASETS) {
    const rows = snap[name];
    if (!Array.isArray(rows)) { skipped.push(name); continue; } // not produced this run
    const { envelope, droppedKeys } = buildEnvelope(name, rows, generatedAt);
    if (droppedKeys.length) {
      warn(`[brain] omni push: dropped non-count field(s) from ${name}: ${droppedKeys.join(', ')}`);
    }
    try {
      const status = await postEnvelope(url, key, envelope);
      sent.push({ dataset: name, rows: envelope.rows.length, status });
    } catch (e) {
      warn(`[brain] omni push: dataset ${name} FAILED — ${e.message}`);
      failed.push({ dataset: name, error: e.message });
    }
  }

  return { ok: failed.length === 0, sent, skipped, failed };
}

module.exports = { pushAnalyticsToOmni, buildEnvelope, scrubRow, DATASETS, TIMEOUT_MS };
