'use strict';
// run-tests.js — one command to prove the whole brain is intact.
// Runs the 21 canonical suites (all pure, no external deps).
// motolink.test.js is intentionally excluded: it needs better-sqlite3 + the
// MotoLink key (from Mon 13 Jul) and fails the same way in the source repo.

const { execFileSync } = require('child_process');
const path = require('path');

const SUITES = [
  'claimForms', 'claimFormEmail', 'notifyTemplates', 'highArrearsAlert', 'messageCatalogue',
  'eventEngine', 'premiumAdvisor', 'ai', 'smartClaim', 'orchestrator',
  'renewals', 'reinsurance', 'ageing', 'fraud', 'docCheck', 'refundEngine', 'commissionGuard',
  'payerGuard', 'claimDecision', 'kycChaser',
  // Hardening (CFO 2026-07-13): proves the OFF switch blocks all sends.
  'offSwitch',
  // /approve hardening: fail-closed auth + approver attribution + persistence.
  'approve',
  // Dashboard RBAC: role→permission matrix, server-side 403/401 enforcement,
  // approval actor+role audit, and append-only audit-log proof.
  'rbac',
  // Live read-only pull: claims mapping + record assembly + DSN parse (mock DB).
  'graphiteRo',
  // Collections non-payment clock: consecutive-unpaid detection, stage ladder,
  // 15-day grace math, MIS multi-channel failed-debit, GRA-0203 confidence guard.
  'collectionsClock',
  // Soft-launch non-payment deliverable (CFO 2026-07-18): pre-intimation dataset
  // + respectful overdue-email render (no send) + consolidated Friday report.
  'preIntimation', 'fridayReport',
  // Overdue-email + Friday-report wired into the sweep/schedule + endpoints
  // (build/persist only; the sends stay gated arms).
  'wireReports',
  // CFO 5-day cancellation-notice oversight email (CFO 2026-08-31): notice-due
  // filter off the collections clock + gated internal send (STOP/CONTINUE).
  'cancellationNoticeEmail',
  // CFO 2026-08-31: internal comms fire while customer comms stay locked — the
  // reclassification of high-arrears / Teams / AI onto the internal/enable arms.
  'internalArmSeparation',
  // Events branch must not flood the queue — routine events collapse to one
  // summary per type (fixes the ~50k payment_received → OOM incident).
  'brainsEvents',
  // Compliance/AML aggregate for Omni's nightly pull — must be counts-only,
  // zero PII (no refs/names/policy numbers).
  'complianceSummary',
  // The census that FILLS that aggregate — must be SELECT-only, aggregate-only,
  // and read KYC only through the masked view (never a raw PII column).
  'complianceCensus',
  // The SLIM dashboard snapshot that keeps /api/queue + /compliance-summary fast
  // (the raw queue.json is 100MB+): caps items/team, strips nested blobs, keeps
  // full counts + a full by-domain breakdown.
  'dashboardSlim',
  // Inbound Omni intel pull (T7): stripToCounts must land counts-only — drops
  // PII-named keys, row-shaped arrays and long free-text before anything persists.
  'omniIntel',
  // Outbound Graphite→Omni analytics push (double-locked): DEAD no-op unless a key
  // is present AND arms are on — no key → no_ingest_key, key+arms-off → arms_off,
  // neither path touches the network; Appendix-A v1 envelope shape + PII scrub.
  'omniPush',
  // Written-premium feed (T4): mapper totals/rounding + SQL safety guards
  // (dedup MAX(id), ISSUED-only, CANCEL negated, PII-free) + KYC active-book filter.
  'writtenPremium',
  // "Make it self explanatory" guard-rails (CFO 2026-07-28): the console may not
  // render a number without a plain-English definition; the feature registry must
  // name real modules and say why a held item is held; healthcare stays
  // counts-only through the masked view; the Excel extract states its row cap and
  // can never email a non-internal address.
  'selfExplaining',
  // AI Internal Auditor for Debtors (CFO 2026-08-04): the two standing rules
  // (negative over a month · 90-plus that stopped moving), the clock derived from
  // the ledger rather than from first sight, no size floor, the DPA guarantee on
  // the DeepSeek prompt, safe degradation with no AI, and the active-book guard
  // that keeps the debtors feed off cancelled/never-started policies.
  'debtorsAudit',
  'orphanBalances',
  // Read-only analytics datasets (Alpha Brain workbook tabs + Omni push): the pure
  // builders (group/type/line/loss-ratio/broker-LR/KYC/renewals-trigger/aging) plus
  // SQL safety + DPA guards — SELECT-only, no PII columns, non-voided reserve rule,
  // FY window, ex-VAT /1.14, BONU excluded, KYC via the masked view, renewals
  // trigger carries no summed premium, and per-dataset soft-fail resilience.
  'analyticsDatasets',
  // Debit-on-a-dead-policy watch (CFO 2026-08-11): the reverse of the non-payment
  // clock — the pure mapper's status→severity ranking, money rounding, MIS/ADH
  // scope, and money-first ordering. SELECT-only pull is not unit-run (no DB).
  'collectionsLeak',
];

let pass = 0, fail = 0; const failed = [];
for (const s of SUITES) {
  try { execFileSync(process.execPath, [path.join(__dirname, 'test', `${s}.test.js`)], { stdio: 'ignore' }); pass++; }
  catch { fail++; failed.push(s); }
}
console.log(`brain suites: PASS=${pass} FAIL=${fail}${failed.length ? ' [' + failed.join(', ') + ']' : ''}`);
process.exit(fail ? 1 : 0);
