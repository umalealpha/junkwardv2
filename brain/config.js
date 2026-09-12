'use strict';
// config.js — every switch the sidecar reads from the environment.
// Defaults are the SAFE state: brain off, live arms off. Graphite is never
// touched until these are deliberately turned on.

const path = require('path');

module.exports = {
  // Master switch. false → the daily runner does nothing against live data.
  enabled: process.env.BRAIN_ENABLED === 'true',

  // Live arms. false → the brain only DECIDES and QUEUES; it never sends a
  // message, moves money, or writes to any other system. Kept off until
  // Pramod wires the adapters (from Mon 13 Jul, with the MotoLink key).
  liveArms: process.env.BRAIN_LIVE_ARMS === 'true',

  // Inbox HTTP port (mapped to 127.0.0.1 only by the compose file).
  port: Number(process.env.BRAIN_PORT) || 8090,

  // Shared secret for POST /approve. UNSET = approvals are disabled (403) —
  // fail-closed, so the endpoint can never be used anonymously by accident.
  approveToken: process.env.BRAIN_APPROVE_TOKEN || '',

  // The brain's OWN storage — never Graphite's database.
  dataDir: process.env.BRAIN_DATA_DIR || path.join(__dirname, 'data'),

  // READ-ONLY MySQL DSN for Graphite (GRANT SELECT only). Set by Pramod when
  // live data is wired. Empty tonight → the runner uses the safe fixture.
  graphiteRoDsn: process.env.GRAPHITE_RO_DSN || '',

  // Mirrors lib/highArrearsAlert.HIGH_ARREARS_MONTHS (kept here so the runner
  // never imports the mailer live arm just to read one number).
  HIGH_ARREARS_MONTHS: 3,

  // Finance-oversight recipients for the high-arrears alert. Moved OUT of code
  // into settings (CFO 2026-07-13). Comma-separated env, e.g.
  //   BRAIN_HIGH_ARREARS_RECIPIENTS=cfo@…,kago@…,oprah@…
  // Empty → the alert has no recipients and no-ops until Operations sets it.
  highArrearsRecipients: String(process.env.BRAIN_HIGH_ARREARS_RECIPIENTS || '')
    .split(',').map((s) => s.trim()).filter(Boolean),

  // CFO 5-day cancellation-notice recipients (CFO 2026-08-31). The oversight
  // email that lists policies within 5 days of auto-cancellation for the CFO to
  // reply STOP/CONTINUE. Comma-separated env, e.g.
  //   BRAIN_CANCEL_NOTICE_RECIPIENTS=cfo@…,finance-lead@…
  // Empty → the notice builds but no-ops (no recipients) until Operations sets it.
  // Sent via sendInternalReport, so every address must be on an internal domain.
  cancelNoticeRecipients: String(process.env.BRAIN_CANCEL_NOTICE_RECIPIENTS || '')
    .split(',').map((s) => s.trim()).filter(Boolean),

  // Omni intel INBOUND pull (T7, CFO 27 Jul). The brain pulls Omni's counts-only
  // compliance/collections summary nightly and stores it for the dashboard. URL +
  // token sourced from SSM /graphite/OMNI_INTEL_URL and /graphite/OMNI_INTEL_TOKEN
  // (injected as env at deploy). BOTH empty → the pull no-ops (awaiting Omni), so
  // this ships safe and lights up the moment the SSM params are set.
  omniIntelUrl: process.env.OMNI_INTEL_URL || '',
  omniIntelToken: process.env.OMNI_INTEL_TOKEN || '',
  // Nightly pull hour, UTC. Default 04:00 UTC = 06:00 Africa/Gaborone (CFO: 04:00).
  omniIntelUtcHour: Number.isFinite(Number(process.env.OMNI_INTEL_UTC_HOUR))
    ? Number(process.env.OMNI_INTEL_UTC_HOUR) : 4,

  // Graphite→Omni analytics PUSH (OUTBOUND). The daily counterpart of the intel
  // pull: the brain POSTs its own PII-free analytics snapshot (store.readAnalytics)
  // to Omni's graphite-ingest endpoint, one Appendix-A v1 envelope per dataset.
  // URL + key sourced from SSM /graphite/OMNI_GRAPHITE_INGEST_URL and
  // /graphite/OMNI_GRAPHITE_INGEST_KEY (injected as env at deploy). Left UNDEFINED
  // when unset — NO default URL — so the push is a hard no-op ("no_ingest_key")
  // until the params are deliberately set. Even with a key it stays dead unless
  // BRAIN_LIVE_ARMS==='true' (the double lock, enforced in lib/omniPush).
  omniGraphiteIngestUrl: process.env.OMNI_GRAPHITE_INGEST_URL,
  omniGraphiteIngestKey: process.env.OMNI_GRAPHITE_INGEST_KEY,
  // Daily push hour, UTC. Default 06:15 UTC = 08:15 Africa/Gaborone — deliberately
  // AFTER the 05:45 UTC daily sweep so it ships the freshest snapshot.
  omniPushUtcHour: Number.isFinite(Number(process.env.OMNI_PUSH_UTC_HOUR))
    ? Number(process.env.OMNI_PUSH_UTC_HOUR) : 6,
  omniPushUtcMin: Number.isFinite(Number(process.env.OMNI_PUSH_UTC_MIN))
    ? Number(process.env.OMNI_PUSH_UTC_MIN) : 15,
};
