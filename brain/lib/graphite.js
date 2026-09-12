/**
 * Graphite integration adapter — wired off by default.
 *
 * ═══════════════════════════════════════════════════════
 * HOW TO ACTIVATE
 * ═══════════════════════════════════════════════════════
 * 1. Set GRAPHITE_ENABLED=true in your .env file
 * 2. Set GRAPHITE_HOST and GRAPHITE_PORT (defaults: localhost:2003)
 * 3. Optionally set GRAPHITE_PREFIX (default: alphadirect.claims)
 * 4. Restart the server
 *
 * Once enabled, call:
 *   pushToGraphite()          — send metrics via TCP to Graphite
 *   exportToFile(filePath)    — write plaintext file for manual import
 *
 * When GRAPHITE_ENABLED !== 'true', both functions are silent no-ops.
 * ═══════════════════════════════════════════════════════
 *
 * Graphite plaintext protocol:
 *   <metric_path> <value> <unix_timestamp>\n
 *
 * Example:
 *   alphadirect.claims.total 77 1712563200
 *   alphadirect.claims.by_type.glass 25 1712563200
 *   alphadirect.claims.by_handler.wangu_moses 12 1712563200
 */

const net  = require('net');
const fs   = require('fs');
const path = require('path');

const ENABLED = process.env.GRAPHITE_ENABLED === 'true';
const HOST    = process.env.GRAPHITE_HOST    || 'localhost';
const PORT    = parseInt(process.env.GRAPHITE_PORT || '2003', 10);
const PREFIX  = process.env.GRAPHITE_PREFIX  || 'alphadirect.claims';

/** Sanitise a string for use as a Graphite metric path segment. */
function sanitise(str) {
  return (str || 'unknown')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_|_$/g, '');
}

/** Collect all metrics from SQLite and return as an array of {path, value, timestamp}. */
function collectMetrics(db) {
  const ts = Math.floor(Date.now() / 1000);
  const metrics = [];

  // ── Total claims ─────────────────────────────────────
  const total = db.prepare('SELECT COUNT(*) AS c FROM claims').get().c;
  metrics.push({ path: `${PREFIX}.total`, value: total, ts });

  // ── Claims by type ───────────────────────────────────
  const byType = db.prepare(
    'SELECT claim_type, COUNT(*) AS c FROM claims WHERE claim_type != \'\' GROUP BY claim_type'
  ).all();
  for (const row of byType) {
    metrics.push({ path: `${PREFIX}.by_type.${sanitise(row.claim_type)}`, value: row.c, ts });
  }

  // ── Claims by handler ────────────────────────────────
  const byHandler = db.prepare(
    'SELECT claims_handler, COUNT(*) AS c FROM claims WHERE claims_handler != \'\' GROUP BY claims_handler'
  ).all();
  for (const row of byHandler) {
    metrics.push({ path: `${PREFIX}.by_handler.${sanitise(row.claims_handler)}`, value: row.c, ts });
  }

  // ── Claims by source ─────────────────────────────────
  const bySource = db.prepare(
    'SELECT source, COUNT(*) AS c FROM claims GROUP BY source'
  ).all();
  for (const row of bySource) {
    metrics.push({ path: `${PREFIX}.by_source.${sanitise(row.source)}`, value: row.c, ts });
  }

  // ── Financial totals ─────────────────────────────────
  const financials = db.prepare(
    'SELECT COALESCE(SUM(reserve_amount),0) AS reserve, COALESCE(SUM(claim_paid_amount),0) AS paid FROM claims'
  ).get();
  metrics.push({ path: `${PREFIX}.financials.total_reserve`, value: financials.reserve, ts });
  metrics.push({ path: `${PREFIX}.financials.total_paid`, value: financials.paid, ts });

  // ── Claims reported this month ───────────────────────
  const monthStart = new Date();
  monthStart.setDate(1);
  const monthStr = monthStart.toISOString().split('T')[0];
  const thisMonth = db.prepare(
    'SELECT COUNT(*) AS c FROM claims WHERE claim_reported_date >= ?'
  ).get(monthStr).c;
  metrics.push({ path: `${PREFIX}.this_month.reported`, value: thisMonth, ts });

  // ── User counts ──────────────────────────────────────
  const activeUsers = db.prepare('SELECT COUNT(*) AS c FROM users WHERE status = \'active\'').get().c;
  const totalUsers  = db.prepare('SELECT COUNT(*) AS c FROM users').get().c;
  metrics.push({ path: `${PREFIX}.users.active`, value: activeUsers, ts });
  metrics.push({ path: `${PREFIX}.users.total`, value: totalUsers, ts });

  // ── Audit volume ─────────────────────────────────────
  const auditTotal = db.prepare('SELECT COUNT(*) AS c FROM audit').get().c;
  metrics.push({ path: `${PREFIX}.audit.total`, value: auditTotal, ts });

  // ── Custom metric_key entries ────────────────────────
  const custom = db.prepare(
    'SELECT metric_key, COUNT(*) AS c FROM claims WHERE metric_key IS NOT NULL AND metric_key != \'\' GROUP BY metric_key'
  ).all();
  for (const row of custom) {
    metrics.push({ path: `${PREFIX}.custom.${sanitise(row.metric_key)}`, value: row.c, ts });
  }

  return metrics;
}

/** Format metrics array into Graphite plaintext protocol. */
function formatPlaintext(metrics) {
  return metrics.map(m => `${m.path} ${m.value} ${m.ts}`).join('\n') + '\n';
}

/**
 * Push current metrics to a Graphite server via TCP.
 * No-op if GRAPHITE_ENABLED !== 'true'.
 * @param {string} [host] — override GRAPHITE_HOST
 * @param {number} [port] — override GRAPHITE_PORT
 * @returns {Promise<{sent: number}>}
 */
function pushToGraphite(host, port) {
  // FENCED OUT of the read-only Alpha Brain build (CFO 2026-07-13): no outbound
  // write, even if GRAPHITE_ENABLED is set.
  return Promise.resolve({ sent: 0, fenced: true });
  /* eslint-disable no-unreachable */
  if (!ENABLED) return Promise.resolve({ sent: 0, skipped: true });

  // Lazy-require db to avoid circular dependency
  const { db } = require(path.join(__dirname, '..', 'db'));
  const metrics = collectMetrics(db);
  const payload = formatPlaintext(metrics);
  const targetHost = host || HOST;
  const targetPort = port || PORT;

  return new Promise((resolve, reject) => {
    const socket = new net.Socket();
    socket.connect(targetPort, targetHost, () => {
      socket.write(payload, () => {
        socket.end();
        resolve({ sent: metrics.length });
      });
    });
    socket.on('error', (err) => {
      socket.destroy();
      reject(new Error(`Graphite TCP error (${targetHost}:${targetPort}): ${err.message}`));
    });
    socket.setTimeout(5000, () => {
      socket.destroy();
      reject(new Error(`Graphite TCP timeout (${targetHost}:${targetPort})`));
    });
  });
}

/**
 * Export current metrics to a plaintext file for manual Graphite import.
 * No-op if GRAPHITE_ENABLED !== 'true'.
 * @param {string} filePath — output file path
 * @returns {{written: number, file: string}}
 */
function exportToFile(filePath) {
  // FENCED OUT of the read-only Alpha Brain build (CFO 2026-07-13).
  return { written: 0, fenced: true };
  /* eslint-disable no-unreachable */
  if (!ENABLED) return { written: 0, skipped: true };

  const { db } = require(path.join(__dirname, '..', 'db'));
  const metrics = collectMetrics(db);
  const payload = formatPlaintext(metrics);
  const outPath = filePath || path.join(__dirname, '..', 'graphite-metrics.txt');
  fs.writeFileSync(outPath, payload);
  return { written: metrics.length, file: outPath };
}

module.exports = { pushToGraphite, exportToFile, collectMetrics, formatPlaintext, sanitise };
