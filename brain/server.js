'use strict';
// server.js — the Alpha Brain internal dashboard. Zero-dependency HTTP
// (Node built-ins only: http, fs, path, crypto, url).
//
// Back-compat (unchanged behaviour):
//   GET  /health   — liveness + current counts + next sweep time
//   GET  /modules  — proves every bundled module loads in this image
//   GET  /inbox    — the ranked queue as JSON
//   POST /approve  — record a human approval via the legacy shared token
//
// Dashboard:
//   GET  /                       — single-file branded SPA (roles/CRUD/audit)
//   GET  /api/me                 — { user, role, permissions }
//   GET  /api/affected?stage=&format=csv
//   GET  /api/activity?limit=&offset=
//   GET  /api/queue
//   POST /api/approve            — { id, approver }  (RECORDS only; arms off)
//   admin: GET/POST/PUT/DELETE /api/admin/users
//   admin: GET/PUT /api/admin/settings
//
// EVERY /api route is enforced server-side via lib/rbac.can(). The SPA also
// hides controls, but that is cosmetic only — the server never trusts the UI.

const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const config = require('./config');
const store = require('./store');
const rbac = require('./lib/rbac');
const { runSweep } = require('./daily');
const preIntimation = require('./lib/preIntimation');
const fridayReport = require('./lib/fridayReport');
const mailer = require('./lib/mailer');
const omniIntel = require('./lib/omniIntel');
const omniPush = require('./lib/omniPush');
const weeklyExtract = require('./lib/weeklyExtract');
const healthcareCompliance = require('./lib/healthcareCompliance');
const { complianceSummary } = require('./compliance-summary');

// ── Daily sweep scheduler (in-container timer; Node built-ins only) ──
// Fixed 05:45 UTC = 07:45 Africa/Gaborone (UTC+2, no DST) — deliberately AFTER
// the 03:00–07:30 nightly debit/renewal window so arrears reflect the morning's
// actual debits, and the ranked list is ready before Finance/UW sit at ~08:00.
const SWEEP_UTC_HOUR = 5, SWEEP_UTC_MIN = 45;
let sweeping = false;
function msUntilNextSweep(now = new Date()) {
  const next = new Date(now);
  next.setUTCHours(SWEEP_UTC_HOUR, SWEEP_UTC_MIN, 0, 0);
  if (next <= now) next.setUTCDate(next.getUTCDate() + 1);
  return next - now;
}
async function fireSweep(reason) {
  if (sweeping) { console.log(`[brain] sweep skipped (${reason}) — one already running`); return; }
  sweeping = true;
  try { await runSweep(); console.log(`[brain] sweep done (${reason})`); }
  catch (e) { console.error(`[brain] scheduled sweep failed (${reason}):`, e.message); }
  finally { sweeping = false; }
}
function scheduleDaily() {
  const wait = msUntilNextSweep();
  setTimeout(async () => { await fireSweep('daily'); scheduleDaily(); }, wait).unref();
  console.log(`[brain] next daily sweep in ${(wait / 3.6e6).toFixed(2)}h (05:45 UTC / 07:45 Africa/Gaborone)`);
}

// ── Friday consolidated report scheduler — 16:00 UTC = 18:00 Africa/Gaborone ──
// The CFO's weekly non-payment report (Section 1 Deactivated / Section 2
// Cancelled) to UW/Finance/Claims/CFO. The report is BUILT + persisted on every
// fire; the email SEND is a gated arm (mailer.sendEmail → refuseIfOff), so with
// arms OFF nothing is emailed — Finance previews it via /api/friday-report.
const FRIDAY_UTC_HOUR = 16, FRIDAY_UTC_MIN = 0; // 18:00 Africa/Gaborone (UTC+2, no DST)
function msUntilNextFridayReport(now = new Date()) {
  const next = new Date(now);
  next.setUTCHours(FRIDAY_UTC_HOUR, FRIDAY_UTC_MIN, 0, 0);
  while (next <= now || next.getUTCDay() !== 5) next.setUTCDate(next.getUTCDate() + 1); // 5 = Friday
  return next - now;
}
async function fireFridayReport(reason) {
  try {
    const affected = (store.readAffected() || {}).affected || [];
    const report = fridayReport.buildFridayReport(affected);
    store.writeFridayReport({ ...report, builtReason: reason });
    const rec = fridayReport.reportRecipients();
    if (rec.all && rec.all.length) {
      // CFO 2026-08-31: the Friday report is an INTERNAL finance report, so it goes
      // via sendInternalReport (BRAIN_INTERNAL_REPORTS + internal-domain check), NOT
      // the customer arm — it fires while customer comms stay off.
      mailer.sendInternalReport({
        to: rec.all,
        subject: `${report.title} — ${report.generatedAt}`,
        html: fridayReport.renderFridayReportHtml(report),
        text: `Deactivated: ${report.counts.deactivated} · Cancelled: ${report.counts.cancelled}. Open the dashboard for the full list.`,
      });
    }
    console.log(`[brain] friday report built (${reason}) — deactivated ${report.counts.deactivated}, cancelled ${report.counts.cancelled}, recipients ${rec.all ? rec.all.length : 0}`);
  } catch (e) { console.error(`[brain] friday report failed (${reason}):`, e.message); }
}
function scheduleFridayReport() {
  const wait = msUntilNextFridayReport();
  setTimeout(async () => { await fireFridayReport('friday'); scheduleFridayReport(); }, wait).unref();
  console.log(`[brain] next Friday report in ${(wait / 3.6e6).toFixed(1)}h (16:00 UTC / 18:00 Gaborone, Fri)`);
}

// ── Omni intel INBOUND pull — nightly 04:00 UTC (06:00 Africa/Gaborone) ──
// (T7, CFO 27 Jul.) Pulls Omni's counts-only compliance/collections summary and
// persists it for the dashboard (GET /api/intel). Independent of the Graphite
// sweep — a different source (Omni) at a different time. No-ops when unconfigured
// (OMNI_INTEL_URL/OMNI_INTEL_TOKEN unset); a failed pull leaves the prior snapshot.
function msUntilNextOmniIntel(now = new Date()) {
  const next = new Date(now);
  next.setUTCHours(config.omniIntelUtcHour, 0, 0, 0);
  if (next <= now) next.setUTCDate(next.getUTCDate() + 1);
  return next - now;
}
async function fireOmniIntel(reason) {
  if (!config.omniIntelUrl || !config.omniIntelToken) {
    console.log(`[brain] omni intel pull skipped (${reason}) — OMNI_INTEL_URL/TOKEN not set`);
    return;
  }
  try {
    const intel = await omniIntel.fetchIntel(config.omniIntelUrl, config.omniIntelToken);
    store.writeOmniIntel({ fetchedAt: new Date().toISOString(), source: 'omni', ...intel });
    console.log(`[brain] omni intel pull OK (${reason})`);
  } catch (e) {
    console.error(`[brain] omni intel pull failed (${reason}) — prior snapshot kept:`, e.message);
  }
}
function scheduleOmniIntel() {
  const wait = msUntilNextOmniIntel();
  setTimeout(async () => { await fireOmniIntel('scheduled'); scheduleOmniIntel(); }, wait).unref();
  console.log(`[brain] next omni intel pull in ${(wait / 3.6e6).toFixed(1)}h (${String(config.omniIntelUtcHour).padStart(2, '0')}:00 UTC)`);
}

// ── Graphite→Omni analytics PUSH (OUTBOUND) — daily 06:15 UTC (08:15 Gaborone) ──
// The one-way, gated counterpart of the intel pull: POST the brain's own PII-free
// analytics snapshot (store.readAnalytics) to Omni's graphite-ingest endpoint, one
// Appendix-A v1 envelope per dataset. DOUBLE LOCK (lib/omniPush): dead unless a key
// is set (OMNI_GRAPHITE_INGEST_KEY) AND live arms are ON (BRAIN_LIVE_ARMS==='true').
// Scheduled AFTER the 05:45 UTC sweep so it ships the freshest snapshot. Read-only,
// never writes back to Graphite. No-ops silently ("no_ingest_key") when unconfigured.
function msUntilNextOmniPush(now = new Date()) {
  const next = new Date(now);
  next.setUTCHours(config.omniPushUtcHour, config.omniPushUtcMin, 0, 0);
  if (next <= now) next.setUTCDate(next.getUTCDate() + 1);
  return next - now;
}
async function fireOmniPush(reason) {
  if (!config.omniGraphiteIngestUrl || !config.omniGraphiteIngestKey) {
    console.log(`[brain] omni push skipped (${reason}) — OMNI_GRAPHITE_INGEST_URL/KEY not set (no_ingest_key)`);
    return;
  }
  try {
    const snapshot = store.readAnalytics();
    const summary = await omniPush.pushAnalyticsToOmni({
      url: config.omniGraphiteIngestUrl,
      key: config.omniGraphiteIngestKey,
      snapshot,
      log: console.warn,
    });
    // A LOCK skip returns { skipped:true, reason } (boolean); a push that PROCEEDED
    // returns { sent:[], skipped:[], failed:[] } where `skipped` is an ARRAY (which
    // is truthy even when empty). Distinguish the two so a successful push logs its
    // real sent/failed counts instead of a misleading "no-op".
    if (summary.skipped === true) {
      console.log(`[brain] omni push no-op (${reason}) — ${summary.reason}`);
    } else {
      console.log(`[brain] omni push done (${reason}) — sent ${summary.sent.length}, skipped ${summary.skipped.length}, failed ${summary.failed.length}`);
    }
  } catch (e) {
    console.error(`[brain] omni push failed (${reason}):`, e && e.message);
  }
}
function scheduleOmniPush() {
  const wait = msUntilNextOmniPush();
  setTimeout(async () => { await fireOmniPush('scheduled'); scheduleOmniPush(); }, wait).unref();
  console.log(`[brain] next omni push in ${(wait / 3.6e6).toFixed(1)}h (${String(config.omniPushUtcHour).padStart(2, '0')}:${String(config.omniPushUtcMin).padStart(2, '0')} UTC)`);
}

// Timing-safe LEGACY token check for POST /approve. No token configured →
// nothing ever matches (fail-closed).
function approveTokenOk(given) {
  if (!config.approveToken) return false;
  const a = crypto.createHash('sha256').update(String(given || '')).digest();
  const b = crypto.createHash('sha256').update(config.approveToken).digest();
  return crypto.timingSafeEqual(a, b);
}

const LIB = path.join(__dirname, 'lib');
function moduleHealth() {
  const out = {};
  for (const f of fs.readdirSync(LIB).filter((x) => x.endsWith('.js'))) {
    const name = f.replace(/\.js$/, '');
    try { require(path.join(LIB, f)); out[name] = 'ok'; }
    catch (e) { out[name] = `needs-live-dep: ${e.code || e.message}`; }
  }
  return out;
}

function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
function json(res, code, obj) { res.writeHead(code, { 'content-type': 'application/json' }); res.end(JSON.stringify(obj, null, 2)); }

// ── Request helpers ─────────────────────────────────────────────────────────
function bearer(req) {
  const auth = String(req.headers.authorization || '');
  return auth.startsWith('Bearer ') ? auth.slice(7).trim() : '';
}
function readBody(req) {
  return new Promise((resolve, reject) => {
    let body = '';
    req.on('data', (c) => { body += c; if (body.length > 1e5) { req.destroy(); reject(new Error('body too large')); } });
    req.on('end', () => resolve(body));
    req.on('error', reject);
  });
}
async function readJson(req) {
  const body = await readBody(req);
  if (!body) return {};
  return JSON.parse(body);
}

// Resolve the caller to a dashboard user (or null). Enforced per route below.
function authUser(req) { return store.getUserByToken(bearer(req)); }

// Guard: returns true when allowed; otherwise writes 401/403 and returns false.
function guard(res, user, action) {
  if (!user) { json(res, 401, { error: 'authentication required' }); return false; }
  if (!rbac.can(user.role, action)) { json(res, 403, { error: `forbidden: ${user.role} lacks ${action}` }); return false; }
  return true;
}

// ── Affected policies (SAMPLE feed until the live list is wired) ────────────
const CLAIMS_TEAMS = new Set(['Claims', 'claims']);
function loadAffected() {
  // Prefer the live affected list the daily sweep wrote (collectionsRo); fall
  // back to the sample fixture when the sweep hasn't run against live data yet.
  try {
    const live = store.readAffected();
    if (live && Array.isArray(live.affected)) {
      return { live: true, source: 'graphite-ro [live]', generatedAt: live.generatedAt || null, affected: live.affected };
    }
  } catch (_) { /* fall through to the sample */ }
  try {
    const p = path.join(__dirname, 'fixtures', 'affected.sample.json');
    const data = JSON.parse(fs.readFileSync(p, 'utf8'));
    return { live: false, source: 'affected.sample.json [SAMPLE — not live]', generatedAt: data.generatedAt || null, affected: Array.isArray(data.affected) ? data.affected : [] };
  } catch (e) {
    return { live: false, source: 'unavailable', generatedAt: null, affected: [], error: e.message };
  }
}

function affectedToCsv(rows) {
  const cols = ['policyNumber', 'customerName', 'product', 'productId', 'agent', 'channel', 'billingType', 'amountOverdue', 'monthsUnpaid', 'daysOverdue', 'stage', 'deactivatedAt', 'graceEndsAt', 'signalConfidence', 'reason'];
  const cell = (v) => { const s = String(v == null ? '' : v); return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; };
  return [cols.join(',')].concat(rows.map((r) => cols.map((c) => cell(r[c])).join(','))).join('\n');
}

// ── Extract data source ─────────────────────────────────────────────────────
// The extract must serve the FULL list, not the dashboard's top-N snapshot —
// otherwise the Debtors team gets 100 rows and thinks that is the book. The full
// queue.json can be very large, and a synchronous parse of it blocks the event
// loop (this is why /health never touches it). So: read the full queue when it is
// a sane size, otherwise fall back to the snapshot and SAY SO in the workbook.
const MAX_QUEUE_BYTES = Number(process.env.BRAIN_EXTRACT_MAX_BYTES) || 120 * 1024 * 1024;
function extractData() {
  let queue = null;
  let truncatedSource = null;
  try {
    const size = fs.statSync(store.queuePath()).size;
    if (size <= MAX_QUEUE_BYTES) queue = store.readQueue();
    else truncatedSource = `full queue is ${(size / 1048576).toFixed(0)}MB — served from the dashboard snapshot (top items per team) instead`;
  } catch (_) { /* no queue file yet */ }
  if (!queue) {
    queue = store.readDashboard() || { generatedAt: null, source: null, teams: {}, counts: {}, total: 0 };
    if (truncatedSource) queue = { ...queue, source: `${queue.source || 'snapshot'} · ${truncatedSource}` };
  }
  const affected = (loadAffected() || {}).affected || [];
  // The Data-Analytics workbook tabs read this counts/totals-only snapshot
  // (store.readAnalytics). Absent (feed not run yet) → null; buildDataset renders
  // the analytics tabs with headers + a "no data" state, never throwing.
  let analytics = null;
  try { analytics = store.readAnalytics(); } catch (_) { analytics = null; }
  return { queue, affected, analytics };
}

// ── Weekly team extracts — Monday 00:00 Africa/Gaborone (Sun 22:00 UTC) ─────
// CFO 28 Jul: each team gets its own Excel workbook in its inbox, weekly, at
// midnight. Sending uses mailer.sendInternalReport — its own narrow switch
// (BRAIN_INTERNAL_REPORTS) and internal recipients only, so customer-facing arms
// stay off. No recipients configured for a team = that workbook is built and
// downloadable, and nothing is emailed.
const EXTRACT_UTC_HOUR = Number.isFinite(Number(process.env.BRAIN_EXTRACT_UTC_HOUR))
  ? Number(process.env.BRAIN_EXTRACT_UTC_HOUR) : 22;   // 22:00 UTC Sun = 00:00 Mon Gaborone
const EXTRACT_UTC_DAY = Number.isFinite(Number(process.env.BRAIN_EXTRACT_UTC_DAY))
  ? Number(process.env.BRAIN_EXTRACT_UTC_DAY) : 0;     // 0 = Sunday
function msUntilNextWeeklyExtract(now = new Date()) {
  const next = new Date(now);
  next.setUTCHours(EXTRACT_UTC_HOUR, 0, 0, 0);
  while (next <= now || next.getUTCDay() !== EXTRACT_UTC_DAY) next.setUTCDate(next.getUTCDate() + 1);
  return next - now;
}
function fireWeeklyExtract(reason) {
  const data = extractData();
  const rec = weeklyExtract.recipients();
  const results = [];
  for (const key of Object.keys(weeklyExtract.DATASETS)) {
    try {
      const { buffer, meta } = weeklyExtract.workbook(key, data);
      const to = weeklyExtract.internalOnly(rec[key] || []);
      const cc = weeklyExtract.internalOnly(rec.cfo || []);
      if (!to.length) { results.push(`${key}: built ${meta.written} rows, no recipients set`); continue; }
      const sent = mailer.sendInternalReport({
        to, cc,
        subject: `Alpha Brain — ${meta.title} — week to ${String(meta.generatedAt || '').slice(0, 10)}`,
        html: weeklyExtract.renderEmailHtml(meta),
        text: `${meta.written} item(s) for ${meta.label}. Data as at ${meta.generatedAt}. The first sheet of the attached workbook explains every column.`,
        attachments: [{ filename: weeklyExtract.filename(key, meta.generatedAt), content: buffer }],
        tag: 'brain-weekly-extract',
      });
      results.push(`${key}: ${meta.written} rows → ${sent.ok ? `queued to ${to.length}` : `NOT sent (${sent.reason || 'blocked'})`}`);
    } catch (e) { results.push(`${key}: FAILED ${e.message}`); }
  }
  console.log(`[brain] weekly extract (${reason}) — ${results.join(' | ')}`);
  return results;
}
function scheduleWeeklyExtract() {
  const wait = msUntilNextWeeklyExtract();
  setTimeout(() => { fireWeeklyExtract('weekly'); scheduleWeeklyExtract(); }, wait).unref();
  console.log(`[brain] next weekly extract in ${(wait / 3.6e6).toFixed(1)}h (${String(EXTRACT_UTC_HOUR).padStart(2, '0')}:00 UTC, day ${EXTRACT_UTC_DAY})`);
}

// ── API router ──────────────────────────────────────────────────────────────
async function handleApi(req, res, url, user) {
  const p = url.pathname;
  const m = req.method;

  if (m === 'GET' && p === '/api/me') {
    if (!user) return json(res, 401, { error: 'authentication required' });
    return json(res, 200, { user: { id: user.id, name: user.name, role: user.role }, role: user.role, permissions: rbac.permissionsFor(user.role) });
  }

  if (m === 'GET' && p === '/api/queue') {
    // Serve the SLIM dashboard snapshot (store.slimQueue) — NEVER the raw
    // queue.json (100MB+ → jams the event loop past the proxy timeout). Counts
    // are full/accurate; item lists are top-N per team. Absent (before the first
    // sweep after boot) → empty snapshot, not an error.
    const q = store.readDashboard() || { generatedAt: null, source: null, liveArms: config.liveArms, total: 0, counts: {}, teams: {} };
    // full queue → view.queue; else claims-only slice → view.queue.claims
    if (user && rbac.can(user.role, 'view.queue')) {
      return json(res, 200, q);
    }
    if (guard(res, user, 'view.queue.claims')) {
      const teams = {}; const counts = {};
      for (const t of Object.keys(q.teams || {})) {
        // counts stay full/accurate (from the snapshot), items are the top-N slice.
        if (CLAIMS_TEAMS.has(t)) { teams[t] = q.teams[t]; counts[t] = (q.counts && q.counts[t]) || (q.teams[t] || []).length; }
      }
      return json(res, 200, { ...q, teams, counts, total: Object.values(counts).reduce((a, b) => a + b, 0), scope: 'claims-only' });
    }
    return; // guard already responded
  }

  // Omni's counts-only intel summary (T7). PII-free by construction
  // (omniIntel.stripToCounts), so any authenticated brain user may read it.
  // Absent → 200 with awaiting:true so the dashboard shows "awaiting Omni intel"
  // rather than an error.
  if (m === 'GET' && p === '/api/intel') {
    if (!user) return json(res, 401, { error: 'authentication required' });
    const intel = store.readOmniIntel();
    if (!intel) return json(res, 200, { awaiting: true, fetchedAt: null });
    return json(res, 200, intel);
  }

  // Gross Written Premium by month (T4). Counts/totals only, PII-free — any
  // authenticated brain user may read it. Absent → 200 awaiting (not an error).
  if (m === 'GET' && p === '/api/written-premium') {
    if (!user) return json(res, 401, { error: 'authentication required' });
    const wp = store.readWrittenPremium();
    if (!wp) return json(res, 200, { awaiting: true, generatedAt: null, byMonth: [] });
    return json(res, 200, wp);
  }

  if (m === 'GET' && p === '/api/affected') {
    const wantsCsv = url.searchParams.get('format') === 'csv';
    if (wantsCsv) { if (!guard(res, user, 'export')) return; }
    else if (!guard(res, user, 'view.affected')) return;
    const data = loadAffected();
    const stage = url.searchParams.get('stage');
    let rows = data.affected;
    if (stage) rows = rows.filter((r) => r.stage === stage);
    if (wantsCsv) {
      store.appendActionAudit({ actor: user.name, actorRole: user.role, action: 'export', target: 'affected', meta: { stage: stage || 'all', rows: rows.length } });
      res.writeHead(200, { 'content-type': 'text/csv; charset=utf-8', 'content-disposition': 'attachment; filename="affected-policies.csv"' });
      return res.end(affectedToCsv(rows));
    }
    return json(res, 200, { live: data.live, source: data.source, generatedAt: data.generatedAt, count: rows.length, affected: rows });
  }

  // Finance pre-intimation grouping (stage buckets + the 'uncertain' hold-back).
  // Persisted by the sweep; computed on-demand from the affected list otherwise.
  // ?policy=XXX previews the respectful overdue email for one policy (RENDER ONLY).
  if (m === 'GET' && p === '/api/pre-intimation') {
    if (!guard(res, user, 'view.affected')) return;
    const data = loadAffected();
    const policyNo = url.searchParams.get('policy');
    if (policyNo) {
      const pol = (data.affected || []).find((r) => String(r.policyNumber) === String(policyNo));
      if (!pol) return json(res, 404, { error: 'policy not in the current affected list' });
      return json(res, 200, { policyNumber: pol.policyNumber, preview: preIntimation.renderOverdueEmail(pol) });
    }
    const stored = store.readPreIntimation();
    const body = (stored && stored.counts) ? stored : { generatedAt: data.generatedAt, ...preIntimation.buildPreIntimation(data.affected) };
    return json(res, 200, { live: data.live, source: data.source, ...body });
  }

  // Friday consolidated report (Section 1 Deactivated / Section 2 Cancelled).
  // Persisted by the Friday schedule; computed on-demand otherwise.
  // ?format=html|csv for preview/download (csv needs export permission).
  if (m === 'GET' && p === '/api/friday-report') {
    const fmt = url.searchParams.get('format');
    if (fmt === 'csv') { if (!guard(res, user, 'export')) return; }
    else if (!guard(res, user, 'view.affected')) return;
    const data = loadAffected();
    const stored = store.readFridayReport();
    const report = (stored && stored.sections) ? stored : fridayReport.buildFridayReport(data.affected);
    if (fmt === 'csv') {
      store.appendActionAudit({ actor: user.name, actorRole: user.role, action: 'export', target: 'friday-report', meta: { deactivated: report.counts.deactivated, cancelled: report.counts.cancelled } });
      res.writeHead(200, { 'content-type': 'text/csv; charset=utf-8', 'content-disposition': 'attachment; filename="friday-report.csv"' });
      return res.end(fridayReport.renderFridayReportCsv(report));
    }
    if (fmt === 'html') {
      res.writeHead(200, { 'content-type': 'text/html; charset=utf-8' });
      return res.end(fridayReport.renderFridayReportHtml(report));
    }
    return json(res, 200, { live: data.live, source: data.source, ...report });
  }

  // Plain-English definition of every number on the console (lib/glossary.js).
  // Definitions only — no data, no PII — so any authenticated user may read it.
  // The console refuses to render a tile it cannot explain, so this is required
  // for the page to work at all, not a nice-to-have.
  if (m === 'GET' && p === '/api/glossary') {
    if (!user) return json(res, 401, { error: 'authentication required' });
    return json(res, 200, require('./lib/glossary').glossary());
  }

  // Every feature in the Brain with its HONEST wiring status (lib/featureRegistry).
  // Answers "where are the other features" without anyone reading the code.
  if (m === 'GET' && p === '/api/features') {
    if (!user) return json(res, 401, { error: 'authentication required' });
    return json(res, 200, require('./lib/featureRegistry').registry());
  }

  // Counts-only healthcare (MIS/ADH) non-compliance. PII-free — any authenticated
  // user. Absent → 200 awaiting (the sweep has not produced it yet), not an error.
  if (m === 'GET' && p === '/api/healthcare') {
    if (!user) return json(res, 401, { error: 'authentication required' });
    const hc = store.readHealthcare();
    if (!hc) return json(res, 200, { awaiting: true, generatedAt: null, ...healthcareCompliance.emptyHealthcare() });
    return json(res, 200, hc);
  }

  // THE DATA EXTRACT (CFO 28 Jul). Excel or CSV, per team, on demand.
  // ?dataset=kyc|debtors|finance|accounts|analytics &format=xlsx|csv
  // Gated by `export` — these rows carry policy numbers and amounts.
  if (m === 'GET' && p === '/api/extract') {
    const dataset = url.searchParams.get('dataset') || 'analytics';
    const format = (url.searchParams.get('format') || 'xlsx').toLowerCase();
    if (!weeklyExtract.DATASETS[dataset]) {
      return json(res, 400, { error: `unknown dataset "${dataset}"`, available: Object.keys(weeklyExtract.DATASETS) });
    }
    // ?list=1 → what can be extracted, no data. Any authenticated user, so the
    // console can show the menu even to someone who may not download.
    if (url.searchParams.get('list')) {
      if (!user) return json(res, 401, { error: 'authentication required' });
      return json(res, 200, {
        datasets: Object.entries(weeklyExtract.DATASETS).map(([k, d]) => ({
          key: k, label: d.label, title: d.title, plain: d.plain,
        })),
        formats: ['xlsx', 'csv'],
        maxRows: weeklyExtract.MAX_ROWS,
        canDownload: !!(user && rbac.can(user.role, 'export')),
      });
    }
    if (!guard(res, user, 'export')) return;
    const data = extractData();
    store.appendActionAudit({ actor: user.name, actorRole: user.role, action: 'export', target: `extract:${dataset}`, meta: { format } });
    if (format === 'csv') {
      const { text, meta } = weeklyExtract.csv(dataset, data);
      res.writeHead(200, {
        'content-type': 'text/csv; charset=utf-8',
        'content-disposition': `attachment; filename="${weeklyExtract.filename(dataset, meta.generatedAt, 'csv')}"`,
      });
      return res.end(text);
    }
    const { buffer, meta } = weeklyExtract.workbook(dataset, data);
    res.writeHead(200, {
      'content-type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'content-disposition': `attachment; filename="${weeklyExtract.filename(dataset, meta.generatedAt, 'xlsx')}"`,
      'content-length': buffer.length,
    });
    return res.end(buffer);
  }

  if (m === 'GET' && p === '/api/activity') {
    if (!guard(res, user, 'view.activity')) return;
    const limit = Math.min(Number(url.searchParams.get('limit')) || 50, 200);
    const offset = Math.max(Number(url.searchParams.get('offset')) || 0, 0);
    return json(res, 200, store.readAudit({ limit, offset }));
  }

  if (m === 'POST' && p === '/api/approve') {
    if (!guard(res, user, 'approve')) return;
    let payload; try { payload = await readJson(req); } catch (e) { return json(res, 400, { error: e.message }); }
    const id = payload.id;
    // DUAL CONTROL (CFO 2026-07-21): a signature is BOUND to the authenticated
    // identity — never a free-text body field. Otherwise one logged-in user
    // could clear a 2-approver money item alone by posting two different names.
    // The two signatures on a dual-control item are therefore two DISTINCT
    // logged-in users (dashboard names are unique). The role that may sign at
    // all is enforced by guard(... 'approve') above.
    const approver = user.name;
    // Arms are OFF: this RECORDS the decision only. It sends/writes/cancels nothing.
    const item = store.markApproved(id, approver);
    if (!item) return json(res, 404, { error: 'not found' });
    store.appendActionAudit({ actor: user.name, actorRole: user.role, action: 'approve', target: id, meta: { approver, boundToIdentity: true } });
    return json(res, 200, item);
  }

  // ── Admin: users ──
  if (p === '/api/admin/users') {
    if (!guard(res, user, 'admin.users')) return;
    if (m === 'GET') return json(res, 200, { users: store.listUsers() });
    if (m === 'POST') {
      let payload; try { payload = await readJson(req); } catch (e) { return json(res, 400, { error: e.message }); }
      try {
        const { user: created, token } = store.createUser({ name: payload.name, role: payload.role, token: payload.token });
        store.appendActionAudit({ actor: user.name, actorRole: user.role, action: 'user.create', target: created.id, meta: { name: created.name, role: created.role } });
        return json(res, 201, { user: created, token }); // plaintext token returned ONCE
      } catch (e) { return json(res, 400, { error: e.message }); }
    }
    if (m === 'PUT') {
      let payload; try { payload = await readJson(req); } catch (e) { return json(res, 400, { error: e.message }); }
      const id = payload.id;
      if (!id) return json(res, 400, { error: 'id required' });
      // Protect the last active admin from being demoted / disabled.
      const before = store.listUsers().find((u) => u.id === id);
      if (before && before.role === 'admin' && (payload.role && payload.role !== 'admin' || payload.disabled === true) && store.countAdmins() <= 1) {
        return json(res, 400, { error: 'cannot remove the last active admin' });
      }
      try {
        const result = store.updateUser(id, { name: payload.name, role: payload.role, disabled: payload.disabled, rotateToken: payload.rotateToken });
        if (!result) return json(res, 404, { error: 'not found' });
        store.appendActionAudit({ actor: user.name, actorRole: user.role, action: 'user.update', target: id, meta: { role: result.user.role, disabled: result.user.disabled, rotated: !!result.token } });
        return json(res, 200, { user: result.user, token: result.token || undefined });
      } catch (e) { return json(res, 400, { error: e.message }); }
    }
    if (m === 'DELETE') {
      let payload = {}; try { payload = await readJson(req); } catch { /* id may be in query */ }
      const id = payload.id || url.searchParams.get('id');
      if (!id) return json(res, 400, { error: 'id required' });
      const target = store.listUsers().find((u) => u.id === id);
      if (target && target.role === 'admin' && store.countAdmins() <= 1) {
        return json(res, 400, { error: 'cannot delete the last active admin' });
      }
      const removed = store.deleteUser(id);
      if (!removed) return json(res, 404, { error: 'not found' });
      store.appendActionAudit({ actor: user.name, actorRole: user.role, action: 'user.delete', target: id, meta: { name: removed.name, role: removed.role } });
      return json(res, 200, { deleted: removed });
    }
    return json(res, 405, { error: 'method not allowed' });
  }

  // ── Admin: settings ──
  if (p === '/api/admin/settings') {
    if (!guard(res, user, 'admin.settings')) return;
    if (m === 'GET') return json(res, 200, store.readSettings());
    if (m === 'PUT') {
      let payload; try { payload = await readJson(req); } catch (e) { return json(res, 400, { error: e.message }); }
      const next = store.writeSettings(payload || {});
      store.appendActionAudit({ actor: user.name, actorRole: user.role, action: 'settings.update', target: 'settings', meta: { keys: Object.keys(payload || {}) } });
      return json(res, 200, next);
    }
    return json(res, 405, { error: 'method not allowed' });
  }

  return json(res, 404, { error: 'not found' });
}

// ── HTTP server ──────────────────────────────────────────────────────────────
const server = http.createServer((req, res) => {
  const url = new URL(req.url, 'http://x');
  const p = url.pathname;

  // Back-compat plain routes (no dashboard auth).
  if (req.method === 'GET' && p === '/health') {
    // LIVENESS ONLY — must stay O(1). It must NOT read queue.json: once
    // collections produces real data that file is multi-MB, and a synchronous
    // fs.readFileSync + JSON.parse on every probe blew past the 5s health-check
    // timeout → the container was killed in a loop. Queue counts live on
    // /api/overview + /api/queue (authenticated) for the dashboard instead.
    return json(res, 200, { ok: true, service: 'alpha-brain', enabled: config.enabled, liveArms: config.liveArms, nextSweepUtc: new Date(Date.now() + msUntilNextSweep()).toISOString() });
  }
  if (req.method === 'GET' && p === '/modules') return json(res, 200, moduleHealth());
  // Compliance/AML aggregate for Omni's nightly pull. COUNTS ONLY — no customer
  // rows, no refs/names (compliance-summary.js). The census (KYC by category/
  // broker, claims KYC, no-doc policies, monthly checks) is filled by the daily
  // sweep once store.readCensus is wired; until then those fields are empty and
  // Omni shows "awaiting live census". Left open (PII-free); the network SG
  // restricts reach — a token can be added later if required.
  if (req.method === 'GET' && p === '/compliance-summary') {
    let census = null;
    try { if (typeof store.readCensus === 'function') census = store.readCensus(); } catch (_) { census = null; }
    // Read the SLIM dashboard snapshot (counts + per-team by-domain full counts),
    // NOT the 100MB+ raw queue — otherwise Omni's nightly pull times out.
    const snap = (typeof store.readDashboard === 'function' && store.readDashboard()) || {};
    let healthcare = null;
    try { healthcare = store.readHealthcare(); } catch (_) { healthcare = null; }
    return json(res, 200, complianceSummary(snap, census, healthcare));
  }
  // CFO 2026-07-21: the queue carries policy numbers, arrears and fraud flags —
  // never serve it unauthenticated. Accepts a dashboard user token or the
  // legacy approve token; no token configured + no user = fail closed.
  if (req.method === 'GET' && p === '/inbox') {
    const user = authUser(req);
    if (!user && !approveTokenOk(bearer(req))) return json(res, 401, { error: 'authentication required' });
    return json(res, 200, store.readQueue());
  }

  // Legacy approve (shared token). Kept working for existing callers/tests.
  if (req.method === 'POST' && p === '/approve') {
    readBody(req).then((body) => {
      try {
        if (!config.approveToken) return json(res, 403, { error: 'approvals disabled — BRAIN_APPROVE_TOKEN not set' });
        if (!approveTokenOk(bearer(req))) return json(res, 401, { error: 'bad token' });
        const { id, approver } = JSON.parse(body || '{}');
        if (!String(approver || '').trim()) return json(res, 400, { error: 'approver name required' });
        // The shared token is not a person, so it cannot supply the two DISTINCT
        // identities a dual-control money item requires. Refuse those here and
        // send the caller to a named dashboard login. (Single-approval items are
        // unaffected — the legacy path keeps working for existing callers/tests.)
        const peek = store.findQueueItem(id);
        if (peek && Number(peek.approvalsRequired) > 1) {
          return json(res, 409, { error: 'dual-control item — approve via a named dashboard login, not the shared token' });
        }
        const item = store.markApproved(id, approver);
        return json(res, item ? 200 : 404, item || { error: 'not found' });
      } catch (e) { return json(res, 400, { error: e.message }); }
    }).catch((e) => json(res, 400, { error: e.message }));
    return;
  }

  // Dashboard SPA.
  if (req.method === 'GET' && p === '/') {
    res.writeHead(200, { 'content-type': 'text/html; charset=utf-8' });
    return res.end(PAGE);
  }

  // RBAC-enforced JSON API.
  if (p.startsWith('/api/')) {
    const user = authUser(req);
    handleApi(req, res, url, user).catch((e) => json(res, 500, { error: e.message }));
    return;
  }

  json(res, 404, { error: 'not found' });
});

// The single-file SPA. Inline CSS + JS, no CDNs, no build step (CSP-safe).
const PAGE = require('./lib/dashboardPage')();

server.listen(config.port, '0.0.0.0', async () => {
  console.log(`[brain] dashboard on :${config.port} · enabled=${config.enabled} · liveArms=${config.liveArms}`);
  if (require.main === module) {
    const seeded = store.ensureSeed();
    if (seeded) console.log(`[brain] seeded bootstrap admin "${seeded.name}" from BRAIN_APPROVE_TOKEN`);
    // Arm the timers first, then run the boot sweep DETACHED — never gate startup
    // or the health check on the sweep. The sweep does heavy work (live pulls +
    // large JSON writes) that must not delay the container reporting healthy.
    scheduleDaily();           // daily 05:45 UTC / 07:45 Gaborone
    scheduleFridayReport();    // weekly Friday 16:00 UTC / 18:00 Gaborone
    scheduleOmniIntel();       // nightly 04:00 UTC / 06:00 Gaborone (Omni intel pull)
    scheduleOmniPush();        // daily 06:15 UTC / 08:15 Gaborone (Graphite→Omni analytics push; double-locked)
    scheduleWeeklyExtract();   // weekly Sun 22:00 UTC = Mon 00:00 Gaborone (team Excel extracts)
    // Boot sweep, THEN the Omni push — chained so the boot push ships the fresh
    // snapshot the sweep just wrote (running them detached in parallel raced the
    // push ahead of the data, so the boot push always shipped an empty snapshot).
    fireSweep('boot')
      .then(() => fireOmniPush('boot'))
      .catch((e) => console.error('[brain] boot sweep/omni push failed:', e && e.message));
    // Pull Omni intel on boot too (detached) so the dashboard has data before the
    // first scheduled 04:00 pull. No-ops when unconfigured.
    fireOmniIntel('boot').catch((e) => console.error('[brain] boot omni intel failed:', e && e.message));
  }
});

module.exports = { server, msUntilNextFridayReport, msUntilNextWeeklyExtract, fireWeeklyExtract };
