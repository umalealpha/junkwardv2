/**
 * Graphite V2 ↔ Claims Tracker master-data sync.
 *
 * PULL  — GET  /api/claims-tracker/master-data
 *          Merges Graphite's lists into our master_data table.
 *          Called daily at midnight + on-demand via the admin UI.
 *
 * PUSH  — POST /api/claims-tracker/master-data/update
 *          Fires after any admin edit to a push-eligible category.
 *          Sends the full updated list (idempotent replace on Graphite's side).
 *
 * Both endpoints use the same api-key as the claims create endpoint.
 * Both are graceful no-ops when Graphite returns 404 (endpoint not yet live).
 */

'use strict';

const PULL_PATH     = '/api/claims-tracker/master-data';
const PUSH_PATH     = '/api/claims-tracker/master-data/update';
const TIMEOUT_MS    = 15_000;
const SYNC_META_KEY = 'graphite_master_sync_meta';

// Graphite field name → our masterData category key.
const PULL_MAP = {
  handlers:        'handlers',
  assessors:       'assessors',
  panel_beaters:   'panelBeaters',
  glass_suppliers: 'glassSuppliers',
  brokers:         'brokers',
  reinsurers:      'reinsurers',
  fac_clients:     'facClients',
  // claim_types handled separately below (needs slug → label rebuild)
};

// Our key → Graphite category name for push.
const PUSH_MAP = {
  handlers:      'handlers',
  assessors:     'assessors',
  panelBeaters:  'panel_beaters',
  glassSuppliers:'glass_suppliers',
  brokers:       'brokers',
  reinsurers:    'reinsurers',
  facClients:    'fac_clients',
};

const PUSH_ELIGIBLE = new Set(Object.keys(PUSH_MAP));

function _base() {
  // No legacy-host default (CFO 2026-07-13) — set GRAPHITE_API_BASE explicitly.
  const raw = process.env.GRAPHITE_API_BASE || '';
  return raw.replace(/\/$/, '');
}

function _apiKey() {
  return process.env.API_KEY_CLAIMS_TRACKER || '';
}

async function _fetch(method, path, body) {
  const ctrl  = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), TIMEOUT_MS);
  try {
    const opts = {
      method,
      headers: { 'Content-Type': 'application/json', 'api-key': _apiKey() },
      signal: ctrl.signal,
    };
    if (body !== undefined) opts.body = JSON.stringify(body);
    const res  = await fetch(_base() + path, opts);
    let data   = {};
    try { data = await res.json(); } catch (_) {}
    return { status: res.status, data };
  } catch (e) {
    if (e && e.name === 'AbortError') throw new Error(`Graphite master-sync request timed out after ${TIMEOUT_MS / 1000}s`);
    throw new Error(`Network error: ${e.message || 'unknown'}`);
  } finally {
    clearTimeout(timer);
  }
}

// ── Pull ─────────────────────────────────────────────────────
// Returns { ok, updated, claimTypesAdded, skipped, error }
async function pull(db, sql) {
  const apiKey = _apiKey();
  if (!apiKey) return { ok: false, error: 'API_KEY_CLAIMS_TRACKER not configured' };

  let res;
  try {
    res = await _fetch('GET', PULL_PATH);
  } catch (e) {
    return { ok: false, error: e.message };
  }

  if (res.status === 404) {
    return { ok: false, error: 'Graphite master-data endpoint not yet deployed (404) — waiting for Graphite team' };
  }
  if (res.status !== 200) {
    const msg = (res.data && res.data.message) || `HTTP ${res.status}`;
    return { ok: false, error: `Graphite returned ${res.status}: ${msg}` };
  }

  const payload   = res.data || {};
  const updated   = [];
  const skipped   = [];

  // ── Plain list categories ────────────────────────────────
  for (const [graphiteKey, ourKey] of Object.entries(PULL_MAP)) {
    const items = payload[graphiteKey];
    if (!Array.isArray(items)) { skipped.push(ourKey); continue; }
    const clean = items.map(v => String(v).trim()).filter(Boolean);
    try {
      sql.upsertMasterData.run({ category: ourKey, items: JSON.stringify(clean) });
      updated.push(ourKey);
    } catch (e) {
      console.warn(`[GraphiteMasterSync] Failed to save ${ourKey}:`, e.message);
      skipped.push(ourKey);
    }
  }

  // ── Claim types → graphiteClaimTypeMap ──────────────────
  // Only ADD new slug entries — never overwrite/remove existing ones
  // so admin customisations survive.
  let claimTypesAdded = 0;
  if (Array.isArray(payload.claim_types)) {
    try {
      const row    = sql.getMasterCategory.get('graphiteClaimTypeMap');
      const existing = row && row.items ? JSON.parse(row.items) : [];
      const existingSlugs = new Set(
        existing.map(e => {
          const parts = String(e).split('|');
          return parts.length >= 2 ? parts[1].trim().toLowerCase() : '';
        }).filter(Boolean)
      );
      const toAdd = [];
      for (const ct of payload.claim_types) {
        if (!ct || !ct.slug || !ct.label) continue;
        const slug = String(ct.slug).trim().toLowerCase();
        if (!slug || existingSlugs.has(slug)) continue;
        toAdd.push(`${String(ct.label).trim()}|${slug}`);
        claimTypesAdded++;
      }
      if (toAdd.length) {
        const merged = [...existing, ...toAdd];
        sql.upsertMasterData.run({ category: 'graphiteClaimTypeMap', items: JSON.stringify(merged) });
        // Bust the adapter cache so new slugs take effect immediately.
        try { require('./graphiteErp').invalidateTypeMapCache(); } catch (_) {}
      }
    } catch (e) {
      console.warn('[GraphiteMasterSync] graphiteClaimTypeMap update failed:', e.message);
    }
  }

  // ── Persist sync metadata ────────────────────────────────
  const meta = {
    lastPullAt:     new Date().toISOString(),
    lastPullStatus: 'ok',
    updatedCategories: updated,
    claimTypesAdded,
  };
  try {
    sql.upsertSetting.run(SYNC_META_KEY, JSON.stringify(meta));
  } catch (_) {}

  console.log(`[GraphiteMasterSync] Pull complete — updated: ${updated.join(', ') || 'none'}; claimTypesAdded: ${claimTypesAdded}`);
  return { ok: true, updated, claimTypesAdded, skipped };
}

// ── Push ─────────────────────────────────────────────────────
// changedData is the full body from POST /api/masterdata.
// Fires a push for every push-eligible category that was included.
async function pushAmendments(changedData) {
  // FENCED OUT of the read-only Alpha Brain build (CFO 2026-07-13): Alpha Brain
  // must never WRITE into Graphite. Master-data push is disabled here.
  console.log('[brain] Graphite master-data push is fenced out of the read-only build — no write sent');
  return;
  /* eslint-disable no-unreachable */
  const apiKey = _apiKey();
  if (!apiKey) return;

  const eligible = Object.keys(changedData).filter(k => PUSH_ELIGIBLE.has(k) && Array.isArray(changedData[k]));
  if (!eligible.length) return;

  for (const ourKey of eligible) {
    const graphiteCategory = PUSH_MAP[ourKey];
    const items = changedData[ourKey].map(v => String(v).trim()).filter(Boolean);
    try {
      const res = await _fetch('POST', PUSH_PATH, { category: graphiteCategory, items });
      if (res.status === 404) {
        console.log(`[GraphiteMasterSync] Push skipped — Graphite endpoint not yet deployed (404)`);
        return; // All categories will have the same result — stop early
      }
      if (res.status !== 200) {
        console.warn(`[GraphiteMasterSync] Push ${ourKey} → ${graphiteCategory} failed: HTTP ${res.status}`);
      } else {
        console.log(`[GraphiteMasterSync] Pushed ${ourKey} (${items.length} items) → Graphite`);
      }
    } catch (e) {
      console.warn(`[GraphiteMasterSync] Push ${ourKey} network error:`, e.message);
    }
  }
}

// ── Status ───────────────────────────────────────────────────
function getStatus(sql) {
  try {
    const row = sql.getSetting.get(SYNC_META_KEY);
    if (row && row.value) return JSON.parse(row.value);
  } catch (_) {}
  return null;
}

module.exports = { pull, pushAmendments, getStatus, PUSH_ELIGIBLE };
