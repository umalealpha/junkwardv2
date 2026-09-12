'use strict';
// store.js — the brain's own small store. A JSON snapshot of the current
// exception queue plus an append-only audit log. No external database, no
// native modules; it cannot touch or lock Graphite's data.

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const config = require('./config');
const rbac = require('./lib/rbac');

function ensure() { fs.mkdirSync(config.dataDir, { recursive: true }); }
function queuePath() { return path.join(config.dataDir, 'queue.json'); }
function auditPath() { return path.join(config.dataDir, 'audit.jsonl'); }
function usersPath() { return path.join(config.dataDir, 'users.json'); }
function settingsPath() { return path.join(config.dataDir, 'settings.json'); }

function writeQueue(q) {
  ensure();
  const tmp = queuePath() + '.tmp';
  fs.writeFileSync(tmp, JSON.stringify(q, null, 2));
  fs.renameSync(tmp, queuePath()); // atomic swap — a reader never sees half a file
}

function readQueue() {
  try { return JSON.parse(fs.readFileSync(queuePath(), 'utf8')); }
  catch { return { generatedAt: null, total: 0, counts: {}, teams: {} }; }
}

function affectedPath() { return path.join(config.dataDir, 'affected.json'); }
// The live collections affected-policies list, written by the daily sweep from
// collectionsRo. The dashboard /api/affected reads this; absent = fall back to
// the sample fixture (i.e. not yet run live).
function writeAffected(obj) {
  ensure();
  const tmp = affectedPath() + '.tmp';
  fs.writeFileSync(tmp, JSON.stringify(obj, null, 2));
  fs.renameSync(tmp, affectedPath());
}
function readAffected() {
  try { return JSON.parse(fs.readFileSync(affectedPath(), 'utf8')); }
  catch { return null; }
}

function preIntimationPath() { return path.join(config.dataDir, 'pre-intimation.json'); }
// The Finance pre-intimation grouping (buildPreIntimation over the affected list),
// written by the daily sweep. BUILD-ONLY — nothing is sent here (arms off). The
// dashboard /api/pre-intimation reads this so Finance can see the grouped list.
function writePreIntimation(obj) {
  ensure();
  const tmp = preIntimationPath() + '.tmp';
  fs.writeFileSync(tmp, JSON.stringify(obj, null, 2));
  fs.renameSync(tmp, preIntimationPath());
}
function readPreIntimation() {
  try { return JSON.parse(fs.readFileSync(preIntimationPath(), 'utf8')); }
  catch { return null; }
}

function censusPath() { return path.join(config.dataDir, 'census.json'); }
// The counts-only compliance census (complianceCensus.fetchCensus), written by
// the daily sweep. GET /compliance-summary reads this and merges it into the
// PII-free payload Omni pulls; absent = census fields stay empty ("awaiting live census").
function writeCensus(obj) {
  ensure();
  const tmp = censusPath() + '.tmp';
  fs.writeFileSync(tmp, JSON.stringify(obj, null, 2));
  fs.renameSync(tmp, censusPath());
}
function readCensus() {
  try { return JSON.parse(fs.readFileSync(censusPath(), 'utf8')); }
  catch { return null; }
}

function healthcarePath() { return path.join(config.dataDir, 'healthcare.json'); }
// Counts-only healthcare (MIS/ADH) non-compliance block (healthcareCompliance
// .fetchHealthcare), written by the daily sweep. Served by GET /api/healthcare
// and merged into /compliance-summary; absent = the feed has not produced yet.
function writeHealthcare(obj) {
  ensure();
  const tmp = healthcarePath() + '.tmp';
  fs.writeFileSync(tmp, JSON.stringify(obj, null, 2));
  fs.renameSync(tmp, healthcarePath());
}
function readHealthcare() {
  try { return JSON.parse(fs.readFileSync(healthcarePath(), 'utf8')); }
  catch { return null; }
}

function writtenPremiumPath() { return path.join(config.dataDir, 'written-premium.json'); }
// Gross Written Premium by month (graphiteRo.buildWrittenPremium), written by the
// daily sweep. Counts/totals only. Served by GET /api/written-premium; absent =
// the feed has not produced yet (empty snapshot, not an error).
function writeWrittenPremium(obj) {
  ensure();
  const tmp = writtenPremiumPath() + '.tmp';
  fs.writeFileSync(tmp, JSON.stringify(obj, null, 2));
  fs.renameSync(tmp, writtenPremiumPath());
}
function readWrittenPremium() {
  try { return JSON.parse(fs.readFileSync(writtenPremiumPath(), 'utf8')); }
  catch { return null; }
}

function analyticsPath() { return path.join(config.dataDir, 'analytics.json'); }
// The read-only analytics datasets (graphiteRo.fetchAnalytics + buildDebtorsAging),
// written by the daily sweep. Counts/totals only, PII-free — they feed the Alpha
// Brain Data-Analytics workbook tabs and double as the Graphite→Omni push payload
// rows (shapes match the Omni Appendix-A contract). Absent = the feed has not
// produced yet (empty snapshot, not an error). BUILD-ONLY: nothing is pushed here.
function writeAnalytics(obj) {
  ensure();
  const tmp = analyticsPath() + '.tmp';
  fs.writeFileSync(tmp, JSON.stringify(obj, null, 2));
  fs.renameSync(tmp, analyticsPath());
}
function readAnalytics() {
  try { return JSON.parse(fs.readFileSync(analyticsPath(), 'utf8')); }
  catch { return null; }
}

function omniIntelPath() { return path.join(config.dataDir, 'omni-intel.json'); }
// Omni's counts-only intel summary (omniIntel.fetchIntel), pulled nightly by the
// dedicated 04:00 job. Read by GET /api/intel for the dashboard. Absent = the
// pull has not run / is unconfigured; the endpoint reports "awaiting Omni intel".
function writeOmniIntel(obj) {
  ensure();
  const tmp = omniIntelPath() + '.tmp';
  fs.writeFileSync(tmp, JSON.stringify(obj, null, 2));
  fs.renameSync(tmp, omniIntelPath());
}
function readOmniIntel() {
  try { return JSON.parse(fs.readFileSync(omniIntelPath(), 'utf8')); }
  catch { return null; }
}

// ── Dashboard snapshot (SLIM) ───────────────────────────────────────────────
// The full queue.json can be 100MB+ (56k items each carrying nested aging/kyc/
// intent payloads). Serving that from /api/queue reads+parses+serialises the
// whole file synchronously, jamming the single Node event loop well past the
// 15s proxy timeout (and starving every other request behind it). So the sweep
// also writes this SLIM snapshot: full counts + top-N items per team with
// DISPLAY fields only (no nested blobs) + a per-team by-domain breakdown (full
// counts, for /compliance-summary). /api/queue and /compliance-summary read
// THIS, never the raw queue.
function dashboardPath() { return path.join(config.dataDir, 'dashboard.json'); }
const DASHBOARD_PER_TEAM = 100;
function slimQueue(queue, perTeam = DASHBOARD_PER_TEAM) {
  const q = queue || {};
  const teams = {};
  const byDomain = {};
  for (const [t, items] of Object.entries(q.teams || {})) {
    const list = items || [];
    // items are already priority-sorted by brains.sweep → slice = top-N.
    teams[t] = list.slice(0, perTeam).map((it) => ({
      id: it.id, domain: it.domain, team: it.team, priority: it.priority,
      title: it.title, detail: it.detail, ref: it.ref, needsApproval: !!it.needsApproval,
    }));
    const dmap = {};
    for (const it of list) { const d = (it && it.domain) || 'other'; dmap[d] = (dmap[d] || 0) + 1; }
    byDomain[t] = dmap;
  }
  return {
    generatedAt: q.generatedAt || null,
    source: q.source || null,
    liveArms: !!q.liveArms,
    total: q.total || 0,
    counts: q.counts || {},
    perTeamCap: perTeam,
    teams,
    byDomain,
  };
}
function writeDashboard(obj) {
  ensure();
  const tmp = dashboardPath() + '.tmp';
  fs.writeFileSync(tmp, JSON.stringify(obj, null, 2));
  fs.renameSync(tmp, dashboardPath());
}
function readDashboard() {
  try { return JSON.parse(fs.readFileSync(dashboardPath(), 'utf8')); }
  catch { return null; }
}

function fridayReportPath() { return path.join(config.dataDir, 'friday-report.json'); }
// The last-built Friday consolidated report (buildFridayReport). Written by the
// Friday-18:00-Gaborone schedule; the actual email SEND is a gated arm (mailer),
// so with arms off the report is built + persisted here but nothing is emailed.
function writeFridayReport(obj) {
  ensure();
  const tmp = fridayReportPath() + '.tmp';
  fs.writeFileSync(tmp, JSON.stringify(obj, null, 2));
  fs.renameSync(tmp, fridayReportPath());
}
function readFridayReport() {
  try { return JSON.parse(fs.readFileSync(fridayReportPath(), 'utf8')); }
  catch { return null; }
}

function debtorsAuditPath() { return path.join(config.dataDir, 'debtors-audit.json'); }
// The last AI Internal Auditor for Debtors run (CFO 2026-08-04). Written by the
// daily sweep. Read back on the NEXT run to draw the trend line (is the book
// getting better or worse), and served to the console. BUILD-ONLY — the email
// send is a separate gated arm, so with the switches off the audit is computed
// and persisted here and nothing is emailed.
function writeDebtorsAudit(obj) {
  ensure();
  const tmp = debtorsAuditPath() + '.tmp';
  fs.writeFileSync(tmp, JSON.stringify(obj, null, 2));
  fs.renameSync(tmp, debtorsAuditPath());
}
function readDebtorsAudit() {
  try { return JSON.parse(fs.readFileSync(debtorsAuditPath(), 'utf8')); }
  catch { return null; }
}

function appendAudit(entry) {
  ensure();
  fs.appendFileSync(auditPath(), JSON.stringify(entry) + '\n');
}

// ── Actions audit (append-only) ─────────────────────────────────────────────
// Every state-changing dashboard action (approve, user CRUD, settings/role
// change) lands here in the CANONICAL shape { at, actor, actorRole, action,
// target, meta }. It shares the same append-only audit.jsonl as the sweep log;
// action rows are distinguished by carrying an `actor` field.
//
// There is DELIBERATELY no update/delete of audit rows anywhere in this module.
// The log is grow-only: appendActionAudit only ever appends. (test/rbac.test.js
// proves earlier rows are never mutated or removed.)
function appendActionAudit({ actor, actorRole, action, target, meta }) {
  const entry = {
    at: new Date().toISOString(),
    actor: String(actor || '').slice(0, 120),
    actorRole: String(actorRole || '').slice(0, 40),
    action: String(action || '').slice(0, 60),
    target: target == null ? null : String(target).slice(0, 200),
    meta: meta || null,
  };
  ensure();
  fs.appendFileSync(auditPath(), JSON.stringify(entry) + '\n');
  return entry;
}

// Read the audit log back, newest-first, paged. Only rows that are valid JSON
// are returned; a torn last line (mid-append) is skipped rather than throwing.
function readAudit({ limit = 50, offset = 0 } = {}) {
  let lines = [];
  try { lines = fs.readFileSync(auditPath(), 'utf8').split('\n').filter(Boolean); }
  catch { return { total: 0, limit, offset, entries: [] }; }
  const parsed = [];
  for (const l of lines) { try { parsed.push(JSON.parse(l)); } catch { /* skip torn line */ } }
  parsed.reverse(); // newest first
  return { total: parsed.length, limit, offset, entries: parsed.slice(offset, offset + limit) };
}

// ── Users (dashboard auth: named user → token + role) ───────────────────────
// Tokens are NEVER stored in the clear — only a sha256 hash. The plaintext is
// returned exactly once (at create / rotate) for the admin to hand over.
function tokenHash(token) {
  return crypto.createHash('sha256').update(String(token || '')).digest('hex');
}

function readUsers() {
  try { return JSON.parse(fs.readFileSync(usersPath(), 'utf8')); }
  catch { return []; }
}

function writeUsers(users) {
  ensure();
  const tmp = usersPath() + '.tmp';
  fs.writeFileSync(tmp, JSON.stringify(users, null, 2), { mode: 0o600 });
  fs.renameSync(tmp, usersPath());
}

// Public view of a user — NEVER leaks the token hash.
function publicUser(u) {
  if (!u) return null;
  return { id: u.id, name: u.name, role: u.role, createdAt: u.createdAt, updatedAt: u.updatedAt || null, disabled: !!u.disabled };
}

function listUsers() { return readUsers().map(publicUser); }

// Resolve a presented bearer token to a user, timing-safely. Every stored hash
// is compared even after a match is found, so lookup time doesn't leak which
// (or whether a) user matched.
function getUserByToken(token) {
  if (!token) return null;
  const given = Buffer.from(tokenHash(token), 'hex'); // 32 bytes
  let match = null;
  for (const u of readUsers()) {
    if (u.disabled || !u.tokenHash) continue;
    let stored;
    try { stored = Buffer.from(u.tokenHash, 'hex'); } catch { continue; }
    if (stored.length === given.length && crypto.timingSafeEqual(stored, given)) match = u;
  }
  return match ? publicUser(match) : null;
}

function newId(prefix) { return prefix + '_' + crypto.randomBytes(6).toString('hex'); }

// Create a user. Returns { user, token } — token is the one-time plaintext.
function createUser({ name, role, token }) {
  name = String(name || '').trim().slice(0, 120);
  if (!name) throw new Error('name required');
  if (!rbac.isRole(role)) throw new Error('invalid role');
  const users = readUsers();
  if (users.some((u) => u.name.toLowerCase() === name.toLowerCase())) throw new Error('name already exists');
  const plain = String(token || '').trim() || crypto.randomBytes(18).toString('base64url');
  const u = { id: newId('usr'), name, role, tokenHash: tokenHash(plain), createdAt: new Date().toISOString(), updatedAt: null, disabled: false };
  users.push(u);
  writeUsers(users);
  return { user: publicUser(u), token: plain };
}

// Update name / role / disabled and optionally rotate the token. Returns
// { user, token } where token is set only when a rotation happened.
function updateUser(id, patch = {}) {
  const users = readUsers();
  const u = users.find((x) => x.id === id);
  if (!u) return null;
  if (patch.name != null) {
    const name = String(patch.name).trim().slice(0, 120);
    if (!name) throw new Error('name required');
    if (users.some((x) => x.id !== id && x.name.toLowerCase() === name.toLowerCase())) throw new Error('name already exists');
    u.name = name;
  }
  if (patch.role != null) {
    if (!rbac.isRole(patch.role)) throw new Error('invalid role');
    u.role = patch.role;
  }
  if (patch.disabled != null) u.disabled = !!patch.disabled;
  let token = null;
  if (patch.rotateToken) { token = crypto.randomBytes(18).toString('base64url'); u.tokenHash = tokenHash(token); }
  u.updatedAt = new Date().toISOString();
  writeUsers(users);
  return { user: publicUser(u), token };
}

function deleteUser(id) {
  const users = readUsers();
  const idx = users.findIndex((x) => x.id === id);
  if (idx === -1) return null;
  const [removed] = users.splice(idx, 1);
  writeUsers(users);
  return publicUser(removed);
}

function countAdmins(users = readUsers()) { return users.filter((u) => u.role === 'admin' && !u.disabled).length; }

// ── Settings ────────────────────────────────────────────────────────────────
function defaultSettings() {
  return { teamsWebhook: '', reportRecipients: [], featureToggles: { showUncertain: true, dailyEmail: false } };
}

function readSettings() {
  try { return { ...defaultSettings(), ...JSON.parse(fs.readFileSync(settingsPath(), 'utf8')) }; }
  catch { return defaultSettings(); }
}

function writeSettings(patch) {
  const cur = readSettings();
  const next = { ...cur };
  if (patch.teamsWebhook != null) next.teamsWebhook = String(patch.teamsWebhook).slice(0, 500);
  if (patch.reportRecipients != null) {
    const arr = Array.isArray(patch.reportRecipients) ? patch.reportRecipients : String(patch.reportRecipients).split(',');
    next.reportRecipients = arr.map((s) => String(s).trim()).filter(Boolean).slice(0, 50);
  }
  if (patch.featureToggles != null && typeof patch.featureToggles === 'object') {
    next.featureToggles = { ...cur.featureToggles, ...patch.featureToggles };
  }
  ensure();
  const tmp = settingsPath() + '.tmp';
  fs.writeFileSync(tmp, JSON.stringify(next, null, 2));
  fs.renameSync(tmp, settingsPath());
  return next;
}

// Bootstrap: if there are no users yet, seed a single admin from the shared
// BRAIN_APPROVE_TOKEN (back-compat — that token already exists in prod). Returns
// the seeded user or null if nothing was seeded (users exist, or no token set).
function ensureSeed() {
  const users = readUsers();
  if (users.length) return null;
  if (!config.approveToken) return null;
  const u = { id: newId('usr'), name: 'bootstrap-admin', role: 'admin', tokenHash: tokenHash(config.approveToken), createdAt: new Date().toISOString(), updatedAt: null, disabled: false };
  writeUsers([u]);
  return publicUser(u);
}

// Find a single queue item by id (across all teams). Returns the item or null.
// Used to inspect an item (e.g. its approvalsRequired) before acting on it.
function findQueueItem(id) {
  const q = readQueue();
  for (const team of Object.keys(q.teams || {})) {
    for (const item of q.teams[team]) if (item.id === id) return item;
  }
  return null;
}

// A human marks a queued item approved. This ONLY records the decision — it
// does not execute anything. The live arm (wired by Pramod) acts on approvals.
// `approver` is required — an approval with no name attached is not an approval.
function markApproved(id, approver) {
  approver = String(approver || '').trim().slice(0, 120);
  if (!approver) return null;
  const q = readQueue();
  let found = null;
  for (const team of Object.keys(q.teams || {})) {
    for (const item of q.teams[team]) {
      if (item.id === id) {
        // Dual control (CFO 2026-07-21): items stamped approvalsRequired: 2
        // (money) only become 'approved' after TWO DISTINCT approvers sign;
        // the same person can never sign twice. Default 1 = old behaviour.
        const required = Number(item.approvalsRequired) || 1;
        const approvers = Array.isArray(item.approvers) ? item.approvers : [];
        if (!approvers.includes(approver)) approvers.push(approver);
        item.approvers = approvers;
        if (approvers.length >= required) {
          item.status = 'approved';
          item.approvedAt = new Date().toISOString();
          item.approvedBy = approvers.join(' + ');
        } else {
          item.status = 'partially_approved';
          item.approvedBy = null;
        }
        found = item;
      }
    }
  }
  if (found) {
    writeQueue(q);
    appendAudit({ at: new Date().toISOString(),
      action: found.status === 'approved' ? 'approve' : `approve_${found.approvers.length}of${found.approvalsRequired || 1}`,
      id, by: approver });
  }
  return found;
}

// Carry approvals across sweep rebuilds. The daily sweep regenerates every
// item with status 'open'; without this, a recorded approval silently
// disappears from the queue on the next run (audit.jsonl kept it, the queue
// lost it). Item ids are stable (e.g. "collections:POL123"), so match on id.
function mergeApprovals(newQueue) {
  const prev = readQueue();
  const approved = new Map();
  for (const team of Object.keys(prev.teams || {})) {
    for (const item of prev.teams[team]) {
      if (item.status === 'approved' || item.status === 'partially_approved') approved.set(item.id, item);
    }
  }
  if (!approved.size) return newQueue;
  for (const team of Object.keys(newQueue.teams || {})) {
    for (const item of newQueue.teams[team]) {
      const old = approved.get(item.id);
      if (old) {
        item.status = old.status;
        item.approvedAt = old.approvedAt;
        item.approvedBy = old.approvedBy;
        if (old.approvers) item.approvers = old.approvers; // partial signatures survive the sweep
      }
    }
  }
  return newQueue;
}

module.exports = {
  queuePath, auditPath, usersPath, settingsPath,
  writeQueue, readQueue, appendAudit, markApproved, mergeApprovals, findQueueItem,
  affectedPath, writeAffected, readAffected,
  writePreIntimation, readPreIntimation, writeFridayReport, readFridayReport,
  debtorsAuditPath, writeDebtorsAudit, readDebtorsAudit,
  writeCensus, readCensus,
  healthcarePath, writeHealthcare, readHealthcare,
  omniIntelPath, writeOmniIntel, readOmniIntel,
  writtenPremiumPath, writeWrittenPremium, readWrittenPremium,
  analyticsPath, writeAnalytics, readAnalytics,
  dashboardPath, slimQueue, writeDashboard, readDashboard,
  // dashboard additions
  appendActionAudit, readAudit,
  tokenHash, listUsers, getUserByToken, createUser, updateUser, deleteUser, countAdmins,
  readSettings, writeSettings, defaultSettings, ensureSeed,
};
