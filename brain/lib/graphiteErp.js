/**
 * Graphite ERP bridge — POST new claims to Alpha Direct's Graphite
 * server (https://graphite.alphadirect.co.bw/api/claims-tracker/create)
 * and return the Graphite-generated claim_number.
 *
 * NOT to be confused with `lib/graphite.js`, which is the legacy
 * StatsD-style metrics emitter (different system, same brand name).
 *
 * Fire-and-forget from the API request's perspective. Successes write
 * back via sql.markGraphiteLinked; failures via sql.markGraphiteError.
 * The retry sweeper in server.js picks up failed rows on a 5-min loop.
 *
 * Contract source-of-truth: Graphite repo's CLAIMS_TRACKER_API_README.
 */

const { sql } = require('../db');

const ENDPOINT_PATH = '/api/claims-tracker/create';
const POLICY_COVER_PATH = '/api/claims-tracker/policy-cover';
const REQUEST_TIMEOUT_MS = 10_000;
const TYPE_MAP_CACHE_TTL_MS = 60_000; // 60s — keeps SQLite hits sparse

function _base() {
  // No legacy-host default (CFO 2026-07-13): must be set explicitly, so a read
  // fails clearly instead of silently hitting the old graphite.alphadirect.co.bw.
  const raw = process.env.GRAPHITE_API_BASE || '';
  return raw.replace(/\/$/, '');
}

function _apiKey() {
  return process.env.API_KEY_CLAIMS_TRACKER || '';
}

// ── Type-mapping resolver ────────────────────────────────────
// graphiteClaimTypeMap lives in master_data as JSON-encoded list of
// "<our label>|<graphite slug>" strings. Admin edits via the UI.
let _typeMapCache = null;
let _typeMapCacheExpiry = 0;
function _getTypeMap() {
  if (_typeMapCache && Date.now() < _typeMapCacheExpiry) return _typeMapCache;
  const map = {};
  try {
    const row = sql.getMasterCategory.get('graphiteClaimTypeMap');
    if (row && row.items) {
      const list = JSON.parse(row.items);
      if (Array.isArray(list)) {
        list.forEach(entry => {
          const parts = String(entry).split('|');
          if (parts.length < 2) return;
          const label = parts[0].trim();
          const slug = parts[1].trim().toLowerCase();
          if (label && slug) map[label] = slug;
        });
      }
    }
  } catch (e) {
    console.warn('[GraphiteErp] type-map parse failed:', e.message);
  }
  _typeMapCache = map;
  _typeMapCacheExpiry = Date.now() + TYPE_MAP_CACHE_TTL_MS;
  return map;
}

function invalidateTypeMapCache() {
  _typeMapCache = null;
  _typeMapCacheExpiry = 0;
}

// Resolve our claim's type to a Graphite slug. Returns null if no
// mapping found — caller writes graphite_sync_error so admin can fix.
function resolveGraphiteSlug(claim) {
  const map = _getTypeMap();
  const ct = String(claim.claimType || '').trim();
  if (!ct) return null;
  if (ct === 'Non-Motor Claim') {
    const sub = String(claim.nonMotorSubType || '').trim();
    if (!sub) return null;
    const key = `Non-Motor Claim — ${sub}`;
    return map[key] || null;
  }
  return map[ct] || null;
}

// ── Body builder ─────────────────────────────────────────────
// claim is the camelCase object (output of rowToClaim()).
// handlerEmail is the email of the handler who created/last-edited
// the claim. Sweeper retries pass the stored graphite_handler_email
// so attribution survives.
function buildBody(claim, slug, handlerEmail) {
  // Description required by Graphite (decision A in build prompt).
  // Falls back to a deterministic placeholder if our local description
  // is empty — admin can edit on the local claim later; Graphite still
  // accepts the create.
  const raw = String(claim.claimDescription || '').trim();
  const description = raw || `Created from Claims Tracker — claim ${claim.claimNumber || claim.id}, policy ${claim.policyNumber || '(no policy)'}. Awaiting detailed description.`;

  const body = {
    policy_number: claim.policyNumber || '',
    claim_type: slug,
    date_of_loss: claim.claimReportedDate || '',
    description: description.slice(0, 2000),
    external_ref: String(claim.id),
  };
  if (handlerEmail) body.claim_handler_email = handlerEmail;
  return body;
}

// ── Main entry ───────────────────────────────────────────────
// Returns { ok, claim_id, claim_number, error }.
// On ok=true: caller writes via sql.markGraphiteLinked.
// On ok=false: caller writes via sql.markGraphiteError.
// Does NOT touch the DB itself — keeps the adapter testable in isolation.
async function createGraphiteClaim(claim, options) {
  // FENCED OUT of the read-only Alpha Brain build (CFO 2026-07-13): Alpha Brain
  // must never WRITE into Graphite. This claim-create path is disabled here —
  // claim creation stays in Graphite / the Claims Tracker, not the brain.
  return { ok: false, fenced: true, error: 'disabled: Graphite writes are fenced out of the read-only Alpha Brain build' };

  /* eslint-disable no-unreachable */
  options = options || {};
  const apiKey = _apiKey();
  if (!apiKey) {
    return { ok: false, error: 'API_KEY_CLAIMS_TRACKER not configured on this server' };
  }

  const slug = resolveGraphiteSlug(claim);
  if (!slug) {
    const t = claim.claimType + (claim.nonMotorSubType ? ` — ${claim.nonMotorSubType}` : '');
    return { ok: false, error: `No Graphite mapping for claim type "${t}" — add it via Master Data > Graphite Claim Type Map` };
  }
  const rawPolicy = String(claim.policyNumber || '').trim().toUpperCase();
  const isPlaceholder = !rawPolicy || rawPolicy === 'TBA' || rawPolicy === 'N/A' || rawPolicy === '-' || rawPolicy === 'NONE';
  if (isPlaceholder) {
    return { ok: false, permanent: true, error: 'Policy number is TBA — enter the real policy number on this claim to enable Graphite sync' };
  }
  if (!claim.claimReportedDate) {
    return { ok: false, error: 'date_of_loss is required but claimReportedDate is empty' };
  }

  const url = _base() + ENDPOINT_PATH;
  const body = buildBody(claim, slug, options.handlerEmail);

  // 10s timeout via AbortController. Node 18+ has built-in fetch.
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), REQUEST_TIMEOUT_MS);
  let res, data = {};
  try {
    res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'api-key': apiKey,
      },
      body: JSON.stringify(body),
      signal: ctrl.signal,
    });
    try { data = await res.json(); } catch (_) { data = {}; }
  } catch (e) {
    clearTimeout(timer);
    if (e && e.name === 'AbortError') {
      return { ok: false, error: `Graphite request timed out after ${REQUEST_TIMEOUT_MS / 1000}s` };
    }
    return { ok: false, error: `Network error contacting Graphite: ${e.message || 'unknown'}` };
  }
  clearTimeout(timer);

  // 201 = new, 200 + status:true = idempotent retry (Graphite already
  // had this external_ref). Both success.
  if (res.status === 201 || (res.status === 200 && data && data.status === true)) {
    if (!data.claim_id || !data.claim_number) {
      return { ok: false, error: `Graphite returned ${res.status} but no claim_id/claim_number in body` };
    }
    return {
      ok: true,
      claim_id: data.claim_id,
      claim_number: data.claim_number,
      idempotent: !!data.idempotent,
    };
  }

  const message = (data && (data.message || data.error)) || `HTTP ${res.status}`;
  const permanent = res.status === 404;
  return { ok: false, permanent, error: `Graphite ${res.status}: ${message}` };
}

// ── Cover lookup (MotoLink assessment) ───────────────────────
// READ-ONLY. Calls Graphite GET /api/claims-tracker/policy-cover with the
// same api-key this adapter already uses, and returns just the two
// underwriting fields MotoLink needs: Sum Insured + the excess (First
// Amount Payable) schedule. Graphite returns NOTHING else (no customer
// name / ID / passport / bank).
//
// ident: { claimNumber } | { policyNumber } | { policyId }.
//   claimNumber is the Graphite claim number (G2026######) a MotoLink
//   assessment carries.
//
// Returns { ok, sum_insured, currency, excesses, excess_source,
//   missing_fields, policy_number, error }. Never throws. On failure
//   ok=false + error. Does NOT touch the DB — testable in isolation.
async function getPolicyCover(ident, options) {
  options = options || {};
  const apiKey = _apiKey();
  if (!apiKey) {
    return { ok: false, error: 'API_KEY_CLAIMS_TRACKER not configured on this server' };
  }
  if (!_base()) {
    return { ok: false, error: 'GRAPHITE_API_BASE not configured (no legacy-host default)' };
  }

  ident = ident || {};
  const params = new URLSearchParams();
  if (ident.claimNumber)       params.set('claim_number', String(ident.claimNumber).trim());
  else if (ident.policyId)     params.set('policy_id', String(ident.policyId).trim());
  else if (ident.policyNumber) params.set('policy_number', String(ident.policyNumber).trim().toUpperCase());
  else {
    return { ok: false, error: 'getPolicyCover requires claimNumber, policyNumber or policyId' };
  }

  const url = _base() + POLICY_COVER_PATH + '?' + params.toString();

  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), REQUEST_TIMEOUT_MS);
  let res, data = {};
  try {
    res = await fetch(url, {
      method: 'GET',
      headers: { 'api-key': apiKey },
      signal: ctrl.signal,
    });
    try { data = await res.json(); } catch (_) { data = {}; }
  } catch (e) {
    clearTimeout(timer);
    if (e && e.name === 'AbortError') {
      return { ok: false, error: `Graphite policy-cover request timed out after ${REQUEST_TIMEOUT_MS / 1000}s` };
    }
    return { ok: false, error: `Network error contacting Graphite: ${e.message || 'unknown'}` };
  }
  clearTimeout(timer);

  if (res.status === 200 && data && data.status === true) {
    return {
      ok: true,
      policy_number:  data.policy_number || null,
      sum_insured:    (typeof data.sum_insured === 'number') ? data.sum_insured : null,
      currency:       data.currency || 'BWP',
      excesses:       Array.isArray(data.excesses) ? data.excesses : [],
      excess_source:  data.excess_source || null,
      missing_fields: Array.isArray(data.missing_fields) ? data.missing_fields : [],
    };
  }

  const message = (data && (data.message || data.error)) || `HTTP ${res.status}`;
  const permanent = res.status === 404;
  return { ok: false, permanent, error: `Graphite ${res.status}: ${message}` };
}

module.exports = {
  createGraphiteClaim,
  getPolicyCover,
  resolveGraphiteSlug,
  invalidateTypeMapCache,
  buildBody,
  _getTypeMap,
};
