'use strict';
// omniIntel.js — nightly INBOUND pull of Omni's compliance/collections intel
// summary (the mirror of our own /compliance-summary that Omni pulls from us).
//
// CONTRACT (Omni core, 27 Jul): GET {OMNI_INTEL_URL} with a Bearer token
// (OMNI_INTEL_TOKEN, sourced from SSM /graphite/OMNI_INTEL_TOKEN) returns a
// COUNTS-ONLY JSON summary — totals and grouped counts, no customer rows. We
// treat that as a promise, not a guarantee: stripToCounts() below defensively
// drops anything that looks like a customer row before we ever persist it, so a
// mis-shaped Omni response can never land PII in the brain's data dir.
//
// Soft-fail by design: any network / HTTP / parse error THROWS to the caller,
// which logs and leaves the previous snapshot intact — a bad pull never breaks
// the sweep or the container.

const MAX_BYTES = 512 * 1024;   // cap the body — a counts summary is tiny; anything large is suspect
const TIMEOUT_MS = 12_000;

// Keys that must never be stored even if Omni sends them by mistake.
const PII_KEYS = /(^|_)(omang|id_number|idno|passport|national_id|msisdn|phone|mobile|email|account|acct|iban|address|dob|birth|name|surname|firstname|lastname|policy_number|policyno|ref|reference)($|_)/i;

// Keep only counts/totals: primitives (numbers, booleans, short label strings)
// and ONE level of nested objects-of-primitives (the byDomain / byCategory maps).
// Arrays-of-objects (row-shaped) and PII-named keys are dropped. Returns a clean
// object plus the list of dropped keys (for the log — visibility, no silent loss).
function stripToCounts(input, depth = 0) {
  const out = {};
  const dropped = [];
  if (!input || typeof input !== 'object' || Array.isArray(input)) return { out, dropped };
  for (const [k, v] of Object.entries(input)) {
    if (PII_KEYS.test(k)) { dropped.push(k); continue; }
    if (v == null) { out[k] = v; continue; }
    const t = typeof v;
    if (t === 'number' || t === 'boolean') { out[k] = v; continue; }
    if (t === 'string') {
      // Allow only short, label-like strings (e.g. generatedAt, source). Long
      // strings could carry free-text PII — drop them.
      if (v.length <= 64) out[k] = v; else dropped.push(k);
      continue;
    }
    if (t === 'object' && !Array.isArray(v) && depth < 1) {
      const nested = stripToCounts(v, depth + 1);
      out[k] = nested.out;
      dropped.push(...nested.dropped.map((d) => `${k}.${d}`));
      continue;
    }
    // arrays, deep objects, functions — row-shaped or unexpected → drop.
    dropped.push(k);
  }
  return { out, dropped };
}

async function fetchIntel(url, token, log = console.warn) {
  if (!url) throw new Error('OMNI_INTEL_URL not set');
  if (!token) throw new Error('OMNI_INTEL_TOKEN not set');

  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), TIMEOUT_MS);
  let resp;
  try {
    resp = await fetch(url, {
      method: 'GET',
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
      signal: ctrl.signal,
    });
  } finally {
    clearTimeout(timer);
  }
  if (!resp.ok) throw new Error(`Omni intel HTTP ${resp.status}`);

  // Size guard before we buffer the whole body.
  const len = Number(resp.headers.get('content-length') || 0);
  if (len && len > MAX_BYTES) throw new Error(`Omni intel body too large (${len} bytes)`);
  const text = await resp.text();
  if (text.length > MAX_BYTES) throw new Error(`Omni intel body too large (${text.length} bytes)`);

  let parsed;
  try { parsed = JSON.parse(text); }
  catch (e) { throw new Error(`Omni intel not JSON: ${e.message}`); }

  const { out, dropped } = stripToCounts(parsed);
  if (dropped.length) log(`[brain] omni intel: dropped non-count field(s): ${dropped.join(', ')}`);
  return out;
}

module.exports = { fetchIntel, stripToCounts, MAX_BYTES, TIMEOUT_MS };
