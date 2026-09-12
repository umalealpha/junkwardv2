'use strict';
// daily.js — the morning sweep. Runs every brain over a batch of records and
// writes ONE ranked exception queue per team. Meant to be run by cron.
//
// Tonight it runs against a safe synthetic fixture (no real data, nothing
// sent). When Pramod wires the read-only Graphite pull (from Mon 13 Jul), the
// same runner reads live records instead — read-only, still firing nothing
// until BRAIN_LIVE_ARMS=true.

const fs = require('fs');
const path = require('path');
const config = require('./config');
const store = require('./store');
const brains = require('./brains');
const graphiteRo = require('./lib/graphiteRo');
const collectionsRo = require('./lib/collectionsRo');
const collectionsLeak = require('./lib/collectionsLeak');
const preIntimation = require('./lib/preIntimation');
const cancellationNotice = require('./lib/cancellationNoticeEmail');
const debtorsAudit = require('./lib/debtorsAudit');
const complianceCensus = require('./lib/complianceCensus');
const healthcareCompliance = require('./lib/healthcareCompliance');

async function loadRecords() {
  const arg = process.argv.find((a) => a.startsWith('--fixture='));
  const fixture = arg ? arg.split('=')[1] : path.join(__dirname, 'fixtures', 'sample.json');

  if (config.enabled && config.graphiteRoDsn) {
    // Live read-only pull (SELECT-only, replica). On ANY error fall back to the
    // fixture so a bad pull can never break the arms-off container.
    try {
      const records = await graphiteRo.fetchRecords(config.graphiteRoDsn);
      // Collections uses its OWN purpose-built pull (the clock-input shape) via
      // collectionsRo — NOT graphiteRo's naive collections feed (wrong shape).
      // Attach the classified affected list so brains.sweep + /api/affected use
      // real data. Read-only; failure degrades to an empty collections queue,
      // never breaks the sweep.
      try {
        records.affected = await collectionsRo.fetchAffectedPolicies(config.graphiteRoDsn);
      } catch (e) {
        records.affected = [];
        console.warn('[brain] collections affected pull failed (collections queue empty this run):', e.message);
      }
      // Debit-on-a-dead-policy watch — the REVERSE of the clock above: MIS/ADH
      // policies that are Deactivated/Cancelled/Expired but whose RealPay mandate
      // is still taking successful debits. Its own read; soft-fails to an empty
      // leak queue so a bad pull never breaks the sweep. Read-only, arms off.
      try {
        records.leaking = await collectionsLeak.fetchLeakingMandates(config.graphiteRoDsn);
      } catch (e) {
        records.leaking = [];
        records.leakPullFailed = e.message; // surface the outage IN the sweep, not just a log line
        console.warn('[brain] collections leak pull failed (leak queue empty this run):', e.message);
      }
      // Counts-only compliance/AML census for the Omni feed. Its own aggregate
      // pull (through the masked view); soft-fails to no census so a bad/denied
      // query never breaks the sweep. PII-free by construction.
      try {
        records.census = await complianceCensus.fetchCensus(config.graphiteRoDsn);
      } catch (e) {
        records.census = null;
        console.warn('[brain] compliance census pull failed (census empty this run):', e.message);
      }
      // Healthcare (MIS/ADH) non-compliance — counts only, through the masked
      // view. Its own pull so one denied query cannot void the census, and vice
      // versa. Soft-fails to no healthcare block (CFO 28 Jul: health was invisible).
      try {
        records.healthcare = await healthcareCompliance.fetchHealthcare(config.graphiteRoDsn);
      } catch (e) {
        records.healthcare = null;
        console.warn('[brain] healthcare compliance pull failed (healthcare empty this run):', e.message);
      }
      // Read-only analytics datasets (Alpha Brain workbook + Omni push). Its own
      // aggregate pull, PII-free; each dataset soft-fails to [] inside
      // fetchAnalytics, and a connection-level failure degrades to no analytics
      // here — never breaks the sweep.
      try {
        records.analytics = await graphiteRo.fetchAnalytics(config.graphiteRoDsn);
      } catch (e) {
        records.analytics = null;
        console.warn('[brain] analytics datasets pull failed (analytics empty this run):', e.message);
      }
      // debtors_aging is derived from the debtors feed already pulled above (no
      // extra query), so it survives even if the analytics connection failed.
      try {
        records.analytics = records.analytics || {};
        records.analytics.debtors_aging = graphiteRo.buildDebtorsAging(
          records.debtors || [], new Date().toISOString().slice(0, 10));
      } catch (e) {
        if (records.analytics) records.analytics.debtors_aging = [];
        console.warn('[brain] debtors_aging build failed (empty this run):', e.message);
      }
      console.log(`[brain] live read-only Graphite pull OK — collections affected: ${records.affected.length}.`);
      return { records, source: 'graphite-ro [live]' };
    } catch (e) {
      console.warn('[brain] live pull failed — falling back to fixture:', e.message);
      return { records: JSON.parse(fs.readFileSync(fixture, 'utf8')), source: `${path.basename(fixture)} [live-failed-fallback]` };
    }
  }
  return { records: JSON.parse(fs.readFileSync(fixture, 'utf8')), source: path.basename(fixture) };
}

async function runSweep() {
  const { records, source } = await loadRecords();

  // AI INTERNAL AUDITOR FOR DEBTORS (CFO 2026-08-04). Runs over the same debtors
  // feed the sweep uses, BEFORE the sweep, so its findings can be surfaced on the
  // console in the same pass. It needs the previous snapshot to draw the trend
  // line, which is why it lives here (store access) and not inside brains.sweep.
  //
  // DeepSeek then attaches a likely root cause per exception. That call is
  // best-effort by design: no gateway, arms off, a timeout or bad JSON all return
  // the audit unchanged with aiExplained:false. The two rules are deterministic
  // and never depend on the model. Nothing is emailed here — the send is a
  // separate gated arm for Pramod to wire.
  try {
    const asOf = new Date().toISOString().slice(0, 10);
    let audit = debtorsAudit.runAudit({
      accounts: records.debtors || [],
      asOf,
      previous: store.readDebtorsAudit(),
    });
    audit = await debtorsAudit.explainFindings(audit);
    records.debtorsAudit = audit;
    store.writeDebtorsAudit(audit);
    console.log(`[brain] debtors audit — ${audit.findings.length} breach(es) of the CFO rules`
      + ` (${audit.totals.stuckNegativeCount} standing negatives, ${audit.totals.stuckOver90Count} stalled 90-plus)`
      + ` · cause analysis: ${audit.aiExplained ? 'on' : 'off'}`
      + ` · trend: ${audit.trend ? audit.trend.direction : 'first run'}`);
  } catch (e) {
    // An audit failure must never break the sweep — the rest of the brain runs.
    records.debtorsAudit = null;
    console.warn('[brain] debtors audit failed (no audit this run):', e.message);
  }

  const queue = await brains.sweep(records);
  queue.source = source;
  queue.liveArms = config.liveArms;
  store.mergeApprovals(queue); // recorded approvals survive the rebuild
  store.writeQueue(queue);
  // Also persist a SLIM dashboard snapshot (counts + top-N/team, display fields
  // only, + per-team by-domain full counts). /api/queue and /compliance-summary
  // serve THIS — never the raw queue.json, which can be 100MB+ and jams the
  // event loop past the proxy timeout. See store.slimQueue.
  store.writeDashboard(store.slimQueue(queue));
  // Persist the live affected list so the dashboard /api/affected shows real
  // data. Only on a live pull (records.affected present) — fixture runs leave
  // the sample fallback intact.
  if (Array.isArray(records.affected)) {
    store.writeAffected({ generatedAt: queue.generatedAt, live: true, affected: records.affected });
    // Also persist the Finance pre-intimation grouping (stage buckets + the
    // 'uncertain' set held back from action) so the dashboard can show it.
    // BUILD-ONLY — the overdue-email SEND is a gated arm, fired by nothing here.
    store.writePreIntimation({
      generatedAt: queue.generatedAt,
      ...preIntimation.buildPreIntimation(records.affected),
    });
    // CFO 5-day cancellation-notice oversight email (CFO 2026-08-31): lists the
    // policies within 5 days of auto-cancellation so the CFO can reply STOP /
    // CONTINUE. Internal-only, gated by BRAIN_INTERNAL_REPORTS + recipients — it
    // works in WATCH mode (live arms OFF) and cancels/writes nothing. Non-fatal.
    try {
      const r = cancellationNotice.maybeSendCancellationNotice({
        affected: records.affected, asOf: queue.generatedAt,
      });
      console.log(`[brain] 5-day cancellation notice: ${r.status}`
        + `${r.count != null ? ` (${r.count} due)` : ''}${r.reason ? ` — ${r.reason}` : ''}`);
    } catch (e) {
      console.warn('[brain] cancellation notice email failed (non-fatal):', e.message);
    }
  }
  // Persist the counts-only compliance census so /compliance-summary can merge
  // it into the PII-free payload Omni pulls. Only on a live pull; absent census
  // leaves the previous snapshot (or the "awaiting live census" empty).
  if (records.census) {
    store.writeCensus({ generatedAt: queue.generatedAt, ...records.census });
  }
  // Counts-only healthcare (MIS/ADH) non-compliance block. Same rule: only on a
  // live pull; absent leaves the previous snapshot. Served by GET /api/healthcare.
  if (records.healthcare) {
    store.writeHealthcare({ generatedAt: queue.generatedAt, ...records.healthcare });
  }
  // Gross Written Premium by month (counts/totals only). Only on a live pull;
  // absent leaves the previous snapshot. Served by GET /api/written-premium.
  if (records.writtenPremium) {
    store.writeWrittenPremium({ generatedAt: queue.generatedAt, ...records.writtenPremium });
  }
  // Read-only analytics datasets (workbook tabs + Omni push payload). Counts/
  // totals only, PII-free. Only on a live pull; absent leaves the previous
  // snapshot. Served/pushed downstream — nothing is sent from here (arms off).
  if (records.analytics) {
    store.writeAnalytics({ generatedAt: queue.generatedAt, ...records.analytics });
  }
  store.appendAudit({ at: queue.generatedAt, source, total: queue.total, counts: queue.counts });

  console.log(`\n[brain] daily sweep — ${queue.generatedAt}`);
  console.log(`[brain] source: ${source} · live arms: ${config.liveArms ? 'ON' : 'OFF (nothing sent or executed)'}`);
  console.log(`[brain] exceptions: ${queue.total}`);
  for (const [team, count] of Object.entries(queue.counts)) console.log(`   ${team.padEnd(12)} ${count}`);
  const top = Object.values(queue.teams).flat().sort((a, b) => b.priority - a.priority).slice(0, 8);
  if (top.length) {
    console.log('[brain] top items:');
    for (const x of top) console.log(`   [${String(x.priority).padStart(2)}] ${x.team.padEnd(10)} ${x.title} — ${x.ref || ''}`);
  }
  console.log(`[brain] queue written to ${store.queuePath()}`);
  return queue;
}

// CLI entry point (cron / manual). When required by server.js (the in-container
// scheduler) this guard prevents an auto-run — server.js calls runSweep itself.
if (require.main === module) {
  runSweep().catch((e) => { console.error('[brain] sweep failed:', e.message); process.exit(1); });
}

module.exports = { runSweep, loadRecords };
