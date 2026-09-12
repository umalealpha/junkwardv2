/**
 * SQLite database initialisation — single shared connection, WAL mode, FK enforced.
 *
 * Usage:
 *   const { db, sql } = require('./db');
 *   const row = db.prepare(sql.getClaim).get(id);
 */

const Database = require('better-sqlite3');
const path     = require('path');

const DB_PATH = process.env.DB_PATH || path.join(__dirname, 'claims.db');
const db      = new Database(DB_PATH);

// ── Pragmas ──────────────────────────────────────────────
db.pragma('journal_mode = WAL');      // readers never block writers
db.pragma('foreign_keys = ON');       // enforce FK constraints
db.pragma('busy_timeout = 5000');     // wait up to 5 s if DB is locked

// ── Schema ───────────────────────────────────────────────
db.exec(`

-- ════════════════════════════════════════════
-- CLAIMS
-- ════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS claims (
  id                          INTEGER PRIMARY KEY AUTOINCREMENT,
  claim_id                    TEXT    UNIQUE NOT NULL,          -- original timestamp ID from data.json

  -- core identifiers
  channel                     TEXT    DEFAULT '',
  client_name                 TEXT    DEFAULT '',
  policy_number               TEXT    DEFAULT '',
  claim_number                TEXT    DEFAULT '',
  claims_handler              TEXT    DEFAULT '',
  claim_reported_date         TEXT    DEFAULT '',               -- ISO date YYYY-MM-DD
  claim_type                  TEXT    DEFAULT '',               -- 'Glass', 'Motor Claim', 'Non-Motor Claim'
  plate_number                TEXT    DEFAULT '',

  -- financials
  reserve_amount              REAL    DEFAULT 0,
  claim_paid_amount           REAL    DEFAULT 0,
  contract_pricing_value      REAL    DEFAULT 0,
  cil_value                   REAL    DEFAULT 0,

  -- description
  claim_description           TEXT    DEFAULT '',

  -- stage 1: allotment & upload
  claim_docs_received         TEXT    DEFAULT '',
  assessor_allotment_date     TEXT    DEFAULT '',
  assessor_name               TEXT    DEFAULT '',
  file_uploaded_to_gt         TEXT    DEFAULT '',
  gt_number                   TEXT    DEFAULT '',
  stage1_comment              TEXT    DEFAULT '',
  distance                    TEXT    DEFAULT '',

  -- stage 2: physical assessment
  panel_beater_name           TEXT    DEFAULT '',
  panel_beater_other          TEXT    DEFAULT '',
  physical_assessment         TEXT    DEFAULT '',
  physical_assessment_comment TEXT    DEFAULT '',

  -- stage 3: quote
  quote_request_date          TEXT    DEFAULT '',
  quote_request_comment       TEXT    DEFAULT '',
  under_warranty              TEXT    DEFAULT '',               -- 'Yes' / 'No' / ''

  -- stage 4: quote finalisation & assessment report
  quote_finalisation          TEXT    DEFAULT '',
  assessment_report_date      TEXT    DEFAULT '',
  assessment_report_comment   TEXT    DEFAULT '',

  -- stage 5: PO
  po_generation_date          TEXT    DEFAULT '',
  po_issue                    TEXT    DEFAULT '',
  po_issue_other              TEXT    DEFAULT '',
  po_issue_date               TEXT    DEFAULT '',

  -- stage 6: parts & job
  parts_eta                   TEXT    DEFAULT '',
  parts_delivery_date         TEXT    DEFAULT '',
  confirmation_date           TEXT    DEFAULT '',
  mismatch_reported           TEXT    DEFAULT '',
  replacement_date            TEXT    DEFAULT '',
  job_end_date                TEXT    DEFAULT '',
  job_end_status              TEXT    DEFAULT '',

  -- customer / type specifics
  customer_type               TEXT    DEFAULT '',
  customer_type_other         TEXT    DEFAULT '',
  non_motor_sub_type          TEXT    DEFAULT '',
  non_motor_assessor          TEXT    DEFAULT '',
  non_motor_assessor_other    TEXT    DEFAULT '',
  glass_supplier              TEXT    DEFAULT '',
  glass_supplier_other        TEXT    DEFAULT '',

  -- comments
  claim_comment               TEXT    DEFAULT '',
  claim_comment_awaiting      TEXT    DEFAULT '',

  -- tracking
  source                      TEXT    DEFAULT 'live',           -- 'import' | 'live'
  imported_at                 TEXT,                              -- set only on imported rows
  metric_key                  TEXT,                              -- for Graphite (Phase 4)
  created_at                  TEXT    DEFAULT (datetime('now')),
  updated_at                  TEXT    DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_claims_handler       ON claims(claims_handler);
CREATE INDEX IF NOT EXISTS idx_claims_type          ON claims(claim_type);
CREATE INDEX IF NOT EXISTS idx_claims_reported_date ON claims(claim_reported_date);
CREATE INDEX IF NOT EXISTS idx_claims_number        ON claims(claim_number);
CREATE INDEX IF NOT EXISTS idx_claims_client        ON claims(client_name);

-- ════════════════════════════════════════════
-- AUDIT LOG
-- ════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS audit (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  audit_id    TEXT    UNIQUE NOT NULL,              -- original timestamp ID
  action      TEXT    DEFAULT '',
  claim_num   TEXT    DEFAULT '',
  detail      TEXT    DEFAULT '',
  actor       TEXT    DEFAULT 'system',             -- username of who performed the action ('system' for automated)
  actor_role  TEXT    DEFAULT '',                   -- role at time of action (audit trail context)
  ts          TEXT    DEFAULT '',                    -- ISO datetime from client
  source      TEXT    DEFAULT 'live',
  imported_at TEXT,
  created_at  TEXT    DEFAULT (datetime('now')),
  updated_at  TEXT    DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_audit_ts        ON audit(ts);
CREATE INDEX IF NOT EXISTS idx_audit_claim_num ON audit(claim_num);
-- idx_audit_actor created after ALTER TABLE migration below

-- ════════════════════════════════════════════
-- USERS
-- ════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS users (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id         TEXT    UNIQUE NOT NULL,           -- original ID like 'u-pbisen'
  username        TEXT    NOT NULL,
  password        TEXT    DEFAULT '',                 -- bcrypt hash or empty for SSO-only
  role            TEXT    NOT NULL DEFAULT 'claims-team',
  name            TEXT    DEFAULT '',
  active          INTEGER DEFAULT 1,                 -- 0/1 boolean
  status          TEXT    DEFAULT 'active',           -- 'active','pending','suspended'
  sso_provider    TEXT    DEFAULT '',                 -- 'azure' or ''
  pages           TEXT    DEFAULT '',                 -- JSON array of allowed pages (nullable)
  last_active_at  TEXT,
  source          TEXT    DEFAULT 'live',
  imported_at     TEXT,
  created_at      TEXT    DEFAULT (datetime('now')),
  updated_at      TEXT    DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_users_username ON users(username);

-- ════════════════════════════════════════════
-- SETTINGS (key-value store)
-- ════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS settings (
  key        TEXT PRIMARY KEY,
  value      TEXT    DEFAULT '',
  updated_at TEXT    DEFAULT (datetime('now'))
);

-- ════════════════════════════════════════════
-- MASTER DATA (one row per list type)
-- ════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS master_data (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  category    TEXT    UNIQUE NOT NULL,               -- 'handlers','assessors','panelBeaters', etc.
  items       TEXT    DEFAULT '[]',                  -- JSON array of strings
  source      TEXT    DEFAULT 'live',
  imported_at TEXT,
  created_at  TEXT    DEFAULT (datetime('now')),
  updated_at  TEXT    DEFAULT (datetime('now'))
);

-- ════════════════════════════════════════════
-- API ACCESS LOG (tracks every API call)
-- ════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS api_access_log (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  ip          TEXT    NOT NULL,
  username    TEXT    DEFAULT '',                     -- authenticated user, or '' for anonymous
  method      TEXT    NOT NULL,                       -- GET, POST, PUT, DELETE
  path        TEXT    NOT NULL,
  status_code INTEGER DEFAULT 0,
  user_agent  TEXT    DEFAULT '',
  created_at  TEXT    DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_api_log_ip         ON api_access_log(ip);
CREATE INDEX IF NOT EXISTS idx_api_log_username   ON api_access_log(username);
CREATE INDEX IF NOT EXISTS idx_api_log_created_at ON api_access_log(created_at);

-- ════════════════════════════════════════════
-- TRUSTED SOURCES (IP whitelist for API access)
-- ════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS trusted_sources (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  ip          TEXT    UNIQUE NOT NULL,                -- IP address or CIDR
  label       TEXT    DEFAULT '',                     -- friendly name e.g. "Finance Bot"
  added_by    TEXT    DEFAULT '',                     -- username of admin who added it
  active      INTEGER DEFAULT 1,                     -- 0=blocked, 1=allowed
  created_at  TEXT    DEFAULT (datetime('now')),
  updated_at  TEXT    DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_trusted_ip ON trusted_sources(ip);

-- ════════════════════════════════════════════
-- BACKDATE GRANTS (time-limited permission for claims-managers to backdate)
-- ════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS backdate_grants (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  target_user_id  TEXT    NOT NULL,                  -- specific user_id OR 'ALL_CLAIMS_MANAGERS'
  target_label    TEXT    DEFAULT '',                -- friendly display
  granted_by      TEXT    NOT NULL,                  -- admin/superadmin username
  reason          TEXT    DEFAULT '',                -- mandatory justification
  granted_at      TEXT    DEFAULT (datetime('now')),
  expires_at      TEXT    NOT NULL,                  -- ISO datetime
  revoked_at      TEXT,                              -- nullable — set if revoked early
  revoked_by      TEXT                               -- nullable — admin who revoked
);

CREATE INDEX IF NOT EXISTS idx_grants_expires ON backdate_grants(expires_at);
CREATE INDEX IF NOT EXISTS idx_grants_target  ON backdate_grants(target_user_id);

-- ════════════════════════════════════════════
-- BACKDATE EVENTS (every successful backdate change — feeds dashboard + alerts)
-- ════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS backdate_events (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  grant_id      INTEGER,                             -- FK to backdate_grants (NULL for admin bypass)
  claim_id      TEXT    NOT NULL,
  claim_number  TEXT    DEFAULT '',
  username      TEXT    NOT NULL,
  user_role     TEXT    DEFAULT '',
  changes_json  TEXT    DEFAULT '[]',                -- JSON array: [{field, old, new}]
  created_at    TEXT    DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_events_created_at ON backdate_events(created_at);
CREATE INDEX IF NOT EXISTS idx_events_username   ON backdate_events(username);
CREATE INDEX IF NOT EXISTS idx_events_claim      ON backdate_events(claim_id);

-- ════════════════════════════════════════════
-- BACKDATE REQUESTS (claims-team requests admin approval to backdate)
-- ════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS backdate_requests (
  id                INTEGER PRIMARY KEY AUTOINCREMENT,
  approve_token     TEXT    UNIQUE NOT NULL,         -- random token used in admin email link
  requester_id      TEXT    NOT NULL,
  requester_username TEXT   NOT NULL,
  requester_name    TEXT    DEFAULT '',
  requester_role    TEXT    DEFAULT '',
  claim_ids_json    TEXT    DEFAULT '[]',            -- JSON array of claim IDs to backdate
  claim_numbers     TEXT    DEFAULT '',              -- comma-separated list for display
  reason            TEXT    DEFAULT '',
  duration_hours    INTEGER DEFAULT 24,              -- requested approval window
  urgency           TEXT    DEFAULT 'normal',        -- normal | urgent
  status            TEXT    DEFAULT 'pending',       -- pending | approved | denied | expired
  created_at        TEXT    DEFAULT (datetime('now')),
  decided_at        TEXT,                            -- nullable
  decided_by        TEXT,                            -- nullable — admin username
  decision_note     TEXT    DEFAULT '',
  grant_id          INTEGER                          -- FK to backdate_grants when approved
);

CREATE INDEX IF NOT EXISTS idx_requests_status     ON backdate_requests(status);
CREATE INDEX IF NOT EXISTS idx_requests_requester  ON backdate_requests(requester_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_requests_token ON backdate_requests(approve_token);

-- ════════════════════════════════════════════
-- EMAIL SCHEDULES (admin-configurable recurring email jobs)
-- ════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS email_schedules (
  id                INTEGER PRIMARY KEY AUTOINCREMENT,
  name              TEXT    NOT NULL,                 -- "EXCO Weekly Brief"
  report_type       TEXT    NOT NULL,                 -- 'weekly' | 'daily' | 'monthly' | 'pending-digest'
  recipients        TEXT    NOT NULL,                 -- comma-separated emails
  frequency         TEXT    NOT NULL,                 -- 'daily' | 'weekly' | 'monthly'
  day_of_week       INTEGER,                          -- 0-6 (Sunday=0); used when frequency='weekly'
  day_of_month      INTEGER,                          -- 1-31; used when frequency='monthly' (clamped to last day)
  hour              INTEGER NOT NULL DEFAULT 8,       -- 0-23, server local time
  enabled           INTEGER NOT NULL DEFAULT 1,
  notes             TEXT    DEFAULT '',
  created_by        TEXT    DEFAULT '',
  created_at        TEXT    DEFAULT (datetime('now')),
  updated_at        TEXT    DEFAULT (datetime('now')),
  last_sent_at      TEXT,                             -- ISO datetime of last successful send
  last_sent_status  TEXT    DEFAULT '',               -- 'sent' | 'failed' | 'skipped:<reason>'
  last_run_at       TEXT                              -- last time scheduler tick processed this row (success or skip)
);

CREATE INDEX IF NOT EXISTS idx_schedules_enabled  ON email_schedules(enabled);
CREATE INDEX IF NOT EXISTS idx_schedules_freq     ON email_schedules(frequency, hour);

-- ════════════════════════════════════════════
-- POLICY WORDINGS (F4-b — admin-managed pointers to SharePoint PDFs)
-- ════════════════════════════════════════════
-- Files live in SharePoint (no upload to this server). We store metadata
-- only: title, branch/product/coverage tags for navigation, a list of roles
-- the admin intends to grant access to (descriptive — SharePoint enforces
-- actual access), version history via supersedes_id, soft-delete via active.
CREATE TABLE IF NOT EXISTS policy_wordings (
  id                INTEGER PRIMARY KEY AUTOINCREMENT,
  title             TEXT    NOT NULL,                 -- e.g. "Motor Comprehensive"
  sharepoint_url    TEXT    NOT NULL,                 -- full URL incl. signed token
  branch            TEXT    NOT NULL DEFAULT '',      -- 'Commercial' | 'Domestic' | 'Instant'
  products          TEXT    NOT NULL DEFAULT '[]',    -- JSON array of strings
  coverages         TEXT    NOT NULL DEFAULT '[]',    -- JSON array of strings
  visible_to_roles  TEXT    NOT NULL DEFAULT '[]',    -- JSON array of role keys
  version           TEXT    NOT NULL DEFAULT '',      -- free-text e.g. "v1.0" or "Rev 2"
  notes             TEXT    DEFAULT '',
  active            INTEGER NOT NULL DEFAULT 1,       -- 0 = superseded or deactivated
  supersedes_id     INTEGER,                          -- FK self-reference for version chain
  added_by          TEXT    DEFAULT '',
  created_at        TEXT    DEFAULT (datetime('now')),
  updated_at        TEXT    DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_pw_branch  ON policy_wordings(branch);
CREATE INDEX IF NOT EXISTS idx_pw_active  ON policy_wordings(active);
CREATE INDEX IF NOT EXISTS idx_pw_title   ON policy_wordings(title);

-- ════════════════════════════════════════════
-- CLAIM COMMENTS / MENTIONS (Batch J)
-- ════════════════════════════════════════════
-- Permanent record per Bharath's "no one deletes" rule — even on dispute we
-- want the original conversation history. Edits allowed only within 1 min of
-- posting (enforced server-side).
CREATE TABLE IF NOT EXISTS claim_messages (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  claim_id        TEXT    NOT NULL,                    -- FK by value to claims.claim_id
  sender_id       TEXT    NOT NULL,                    -- user_id
  sender_name     TEXT    DEFAULT '',
  sender_role     TEXT    DEFAULT '',
  body            TEXT    NOT NULL,                    -- max 2000 chars (server-validated)
  priority        TEXT    DEFAULT '',                  -- '' | 'Urgent' | 'High' | 'Normal' | 'Low'
  mentions        TEXT    DEFAULT '[]',                -- JSON array of {type:'user'|'email', value}
  edited_at       TEXT,
  created_at      TEXT    DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_msg_claim    ON claim_messages(claim_id);
CREATE INDEX IF NOT EXISTS idx_msg_created  ON claim_messages(created_at);
CREATE INDEX IF NOT EXISTS idx_msg_priority ON claim_messages(priority);

-- One row per (mention target) — supports both internal users (recipient_user_id)
-- and external Alpha Direct staff who don't have a claims-tracker account
-- (recipient_email). Tracks read state + email-reminder dedupe.
CREATE TABLE IF NOT EXISTS mention_reads (
  id                 INTEGER PRIMARY KEY AUTOINCREMENT,
  message_id         INTEGER NOT NULL,                  -- FK to claim_messages.id
  recipient_user_id  TEXT    DEFAULT '',                -- non-empty for internal users
  recipient_email    TEXT    DEFAULT '',                -- non-empty for external staff
  read_at            TEXT,
  reminder_sent_at   TEXT,
  created_at         TEXT    DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_mr_message   ON mention_reads(message_id);
CREATE INDEX IF NOT EXISTS idx_mr_user      ON mention_reads(recipient_user_id);
CREATE INDEX IF NOT EXISTS idx_mr_pending   ON mention_reads(read_at, reminder_sent_at);

-- ── Claimant status-link infrastructure (SMS/WhatsApp notifications) ──
-- A short-lived token issued to a claimant phone/email so they can view
-- their claim's status on a read-only page behind an OTP gate. Tokens
-- are stored HASHED (sha256) so a DB leak does not expose live links.
-- Tokens auto-revoke when claim is closed or after 90 days, whichever
-- comes first.
CREATE TABLE IF NOT EXISTS claim_links (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  claim_id        TEXT    NOT NULL,                            -- FK to claims.claim_id
  token_hash      TEXT    NOT NULL UNIQUE,                     -- sha256(token) hex
  contact_name    TEXT    DEFAULT '',
  contact_phone   TEXT    DEFAULT '',                          -- E.164 format
  contact_channel TEXT    DEFAULT '',                          -- 'sms' | 'whatsapp' | 'email'
  issued_by       TEXT    DEFAULT '',                          -- handler username
  issued_at       TEXT    DEFAULT (datetime('now')),
  expires_at      TEXT    NOT NULL,                            -- 90-day hard ceiling
  revoked_at      TEXT,                                        -- non-null = dead
  revoke_reason   TEXT    DEFAULT ''                           -- 'claim_closed','manual','otp_brute','superseded','expired'
);

CREATE INDEX IF NOT EXISTS idx_cl_claim   ON claim_links(claim_id);
CREATE INDEX IF NOT EXISTS idx_cl_active  ON claim_links(revoked_at, expires_at);

-- OTPs are short-lived (5 min) one-time codes. Hashed at rest. One row
-- per OTP issuance — recipients can re-request a fresh code if the
-- previous one expires. attempts is incremented on each verify attempt;
-- ≥3 wrong attempts revokes the parent link.
CREATE TABLE IF NOT EXISTS claim_link_otps (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  link_id     INTEGER NOT NULL,                                -- FK to claim_links.id
  otp_hash    TEXT    NOT NULL,                                -- sha256(otp) hex
  sent_via    TEXT    DEFAULT '',                              -- 'sms' | 'whatsapp' | 'email'
  sent_at     TEXT    DEFAULT (datetime('now')),
  expires_at  TEXT    NOT NULL,
  attempts    INTEGER DEFAULT 0,
  verified_at TEXT
);

CREATE INDEX IF NOT EXISTS idx_otp_link    ON claim_link_otps(link_id);
CREATE INDEX IF NOT EXISTS idx_otp_pending ON claim_link_otps(verified_at, expires_at);

-- Forensic audit log for every interaction with a claim link. Persisted
-- separately from api_access_log so we can retain it longer for
-- compliance (DPA — claim metadata access). Indexed by link for the
-- per-claim audit view; by ip for abuse pattern detection.
CREATE TABLE IF NOT EXISTS claim_link_access_log (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  link_id    INTEGER,                                          -- nullable: actions on invalid/revoked tokens still logged
  token_tail TEXT    DEFAULT '',                               -- last 8 chars of token (for "this looks like that link" tracing without exposing the secret)
  ip         TEXT    DEFAULT '',
  user_agent TEXT    DEFAULT '',
  action     TEXT    DEFAULT '',                               -- 'land','otp_request','otp_verify_ok','otp_verify_fail','status_view','expired','revoked','not_found'
  detail     TEXT    DEFAULT '',
  ts         TEXT    DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_cla_link ON claim_link_access_log(link_id, ts);
CREATE INDEX IF NOT EXISTS idx_cla_ip   ON claim_link_access_log(ip, ts);

-- Outbound notification log (SMS / WhatsApp / email triggered by claim
-- lifecycle events or OTP requests). One row per send attempt. Used for:
--   - Compliance audit (which claimant was contacted, when, about what)
--   - Cost tracking (sum cost_units per month)
--   - Per-claim hard cap enforcement (max N outbound msgs per claim)
--   - Deduplication (don't repeat the same template to the same recipient
--     within a short window)
--   - Delivery-status reconciliation via provider webhooks
CREATE TABLE IF NOT EXISTS notification_log (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  claim_id        TEXT    DEFAULT '',
  channel         TEXT    DEFAULT '',         -- 'sms' | 'whatsapp' | 'email'
  provider        TEXT    DEFAULT '',         -- 'infobip' | 'meta' | 'mailgun'
  trigger_key     TEXT    DEFAULT '',         -- 'claim_registered','otp_request',...
  recipient       TEXT    DEFAULT '',         -- E.164 phone or email
  template_key    TEXT    DEFAULT '',
  payload_hash    TEXT    DEFAULT '',         -- sha256 of message body
  provider_msg_id TEXT    DEFAULT '',         -- e.g. Infobip messageId
  status          TEXT    DEFAULT 'queued',   -- queued | sent | delivered | failed | rejected | suppressed
  cost_units      REAL    DEFAULT 0,          -- approximate cost in BWP
  error           TEXT    DEFAULT '',
  sent_at         TEXT    DEFAULT (datetime('now')),
  delivered_at    TEXT,
  updated_at      TEXT    DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_nl_claim         ON notification_log(claim_id, sent_at);
CREATE INDEX IF NOT EXISTS idx_nl_recipient_tr  ON notification_log(recipient, trigger_key, sent_at);
CREATE INDEX IF NOT EXISTS idx_nl_provider_msg  ON notification_log(provider, provider_msg_id);

`);

// ── Idempotent column migrations ─────────────────────────
// CREATE TABLE IF NOT EXISTS doesn't add columns to existing tables.
// Add new columns explicitly; ignore "duplicate column name" errors so re-runs are safe.
function addColumnIfMissing(table, column, ddl) {
  try {
    const cols = db.prepare(`PRAGMA table_info(${table})`).all();
    if (!cols.some(c => c.name === column)) {
      db.exec(`ALTER TABLE ${table} ADD COLUMN ${column} ${ddl}`);
      console.log(`[DB migration] Added ${table}.${column}`);
    }
  } catch (e) { console.warn(`[DB migration] ${table}.${column} skipped: ${e.message}`); }
}
addColumnIfMissing('audit', 'actor',      `TEXT DEFAULT 'system'`);
addColumnIfMissing('audit', 'actor_role', `TEXT DEFAULT ''`);

// Broker fields — added 2026-05-11 (commit 30aaa3e shipped the UI but missed the
// DB columns, so broker selections were silently dropped on save until this fix).
addColumnIfMissing('claims', 'broker_name',  `TEXT DEFAULT ''`);
addColumnIfMissing('claims', 'broker_other', `TEXT DEFAULT ''`);

// F4-a: FAC Reinsurer — 2026-05-18
// Only relevant when a claim has isFACClaim (existing client-side detection).
// Dropdown is populated from master_data category 'reinsurers' (admin-editable).
addColumnIfMissing('claims', 'reinsurer', `TEXT DEFAULT ''`);

// Batch J: comment-thread resolution state. Lives on the claim so it's a
// single source of truth across views. comments_resolved_at IS NULL means
// the thread is open; non-null = marked resolved (any auth user can do it
// or reopen it).
addColumnIfMissing('claims', 'comments_resolved_at', `TEXT DEFAULT ''`);
addColumnIfMissing('claims', 'comments_resolved_by', `TEXT DEFAULT ''`);

// G1 — Graphite integration sync state per claim. New claims POSTed to
// Graphite's bridge endpoint (/api/claims-tracker/create) write the
// returned identifiers back here. Existing claims with handler-typed
// numbers are NOT touched. See CLAIMS_TRACKER_API_README in the Graphite
// repo for the contract.
addColumnIfMissing('claims', 'graphite_claim_id',      `INTEGER`);
addColumnIfMissing('claims', 'graphite_claim_number',  `TEXT DEFAULT ''`);
addColumnIfMissing('claims', 'graphite_synced_at',     `TEXT DEFAULT ''`);
addColumnIfMissing('claims', 'graphite_sync_error',    `TEXT DEFAULT ''`);
addColumnIfMissing('claims', 'graphite_sync_attempts', `INTEGER DEFAULT 0`);
addColumnIfMissing('claims', 'graphite_handler_email', `TEXT DEFAULT ''`);

// Status-link / notifications: primary claimant contact phone + extra
// contacts list. Both feed the notifier — primary fires
// claim_registered + stage triggers; extras get the same messages.
addColumnIfMissing('claims', 'contact_phone',  `TEXT DEFAULT ''`);
addColumnIfMissing('claims', 'extra_contacts', `TEXT DEFAULT '[]'`);
try {
  db.exec(`CREATE UNIQUE INDEX IF NOT EXISTS idx_claims_graphite_number
           ON claims(graphite_claim_number)
           WHERE graphite_claim_number IS NOT NULL AND graphite_claim_number != ''`);
}
catch (e) { console.warn('[DB migration] idx_claims_graphite_number skipped:', e.message); }
// Sweeper read path uses (sync_attempts, claim_number IS NULL) — partial
// index keeps the scan cheap as the table grows.
try {
  db.exec(`CREATE INDEX IF NOT EXISTS idx_claims_graphite_pending
           ON claims(graphite_sync_attempts, created_at)
           WHERE (graphite_claim_number IS NULL OR graphite_claim_number = '')`);
}
catch (e) { console.warn('[DB migration] idx_claims_graphite_pending skipped:', e.message); }

// F1: Approve / Repudiate decision tracking — 2026-05-18
// decision_status is empty for undecided claims; 'approved' or 'repudiated' otherwise.
// All other fields are populated only at decision time. Reversal sets *_reversed_*
// without touching the original decision fields (preserves the audit trail).
addColumnIfMissing('claims', 'decision_status',      `TEXT DEFAULT ''`);
addColumnIfMissing('claims', 'decision_by',          `TEXT DEFAULT ''`);
addColumnIfMissing('claims', 'decision_by_name',     `TEXT DEFAULT ''`);
addColumnIfMissing('claims', 'decision_by_role',     `TEXT DEFAULT ''`);
addColumnIfMissing('claims', 'decision_date',        `TEXT DEFAULT ''`);
addColumnIfMissing('claims', 'decision_note',        `TEXT DEFAULT ''`);
addColumnIfMissing('claims', 'decision_reversed_at', `TEXT DEFAULT ''`);
addColumnIfMissing('claims', 'decision_reversed_by', `TEXT DEFAULT ''`);
try { db.exec('CREATE INDEX IF NOT EXISTS idx_claims_decision ON claims(decision_status)'); }
catch (e) { console.warn('[DB migration] idx_claims_decision skipped:', e.message); }

// ── motolink.app assessment bridge — 2026-06-24 ───────────
// Inbound mirror of the G1 Graphite bridge: a scheduled job pulls vehicle
// assessments from motolink.app and writes them onto the matching claim
// (matched on claim_number / graphite_claim_number). These motolink_* columns
// are a dedicated mirror of the assessment so the integration NEVER overwrites
// a value the claims team typed by hand — the sync only fills a manual stage
// field when it is still empty (see js/motolink.js). See MOTOLINK_BRIDGE.md.
addColumnIfMissing('claims', 'motolink_assessment_id',  `TEXT DEFAULT ''`);   // ALPHA-0000002758
addColumnIfMissing('claims', 'motolink_status',         `TEXT DEFAULT ''`);   // New|Request Auth|Authorised|Completed|Cancelled|Total Loss
addColumnIfMissing('claims', 'motolink_final_cost',     `REAL DEFAULT 0`);    // authorised / final repair cost
addColumnIfMissing('claims', 'motolink_total_loss',     `INTEGER DEFAULT 0`); // 0/1
addColumnIfMissing('claims', 'motolink_write_off_alert',`TEXT DEFAULT ''`);   // "possible write-off" alert text/date
addColumnIfMissing('claims', 'motolink_vin',            `TEXT DEFAULT ''`);
addColumnIfMissing('claims', 'motolink_registration',   `TEXT DEFAULT ''`);
addColumnIfMissing('claims', 'motolink_make',           `TEXT DEFAULT ''`);
addColumnIfMissing('claims', 'motolink_model',          `TEXT DEFAULT ''`);
addColumnIfMissing('claims', 'motolink_updated_at',     `TEXT DEFAULT ''`);   // motolink's own updatedAt
addColumnIfMissing('claims', 'motolink_synced_at',      `TEXT DEFAULT ''`);   // last successful sync (our clock)
addColumnIfMissing('claims', 'motolink_sync_error',     `TEXT DEFAULT ''`);
try {
  db.exec(`CREATE INDEX IF NOT EXISTS idx_claims_motolink_assessment
           ON claims(motolink_assessment_id)
           WHERE motolink_assessment_id IS NOT NULL AND motolink_assessment_id != ''`);
}
catch (e) { console.warn('[DB migration] idx_claims_motolink_assessment skipped:', e.message); }

// Indexes that depend on migrated columns
try { db.exec('CREATE INDEX IF NOT EXISTS idx_audit_actor ON audit(actor)'); }
catch (e) { console.warn('[DB migration] idx_audit_actor skipped:', e.message); }

// ── Field mapping: camelCase (data.json) ↔ snake_case (SQLite) ──
// Used by migration script and API layer for zero-friction conversion.
const CLAIM_FIELD_MAP = {
  id:                         'claim_id',
  channel:                    'channel',
  brokerName:                 'broker_name',
  brokerOther:                'broker_other',
  clientName:                 'client_name',
  policyNumber:               'policy_number',
  claimNumber:                'claim_number',
  claimsHandler:              'claims_handler',
  claimReportedDate:          'claim_reported_date',
  claimType:                  'claim_type',
  plateNumber:                'plate_number',
  reserveAmount:              'reserve_amount',
  claimPaidAmount:            'claim_paid_amount',
  claimDescription:           'claim_description',
  claimDocsReceived:          'claim_docs_received',
  assessorAllotmentDate:      'assessor_allotment_date',
  assessorName:               'assessor_name',
  fileUploadedToGT:           'file_uploaded_to_gt',
  gtNumber:                   'gt_number',
  stage1Comment:              'stage1_comment',
  distance:                   'distance',
  panelBeaterName:            'panel_beater_name',
  panelBeaterOther:           'panel_beater_other',
  physicalAssessment:         'physical_assessment',
  physicalAssessmentComment:  'physical_assessment_comment',
  quoteRequestDate:           'quote_request_date',
  quoteRequestComment:        'quote_request_comment',
  underWarranty:              'under_warranty',
  quoteFinalisation:          'quote_finalisation',
  assessmentReportDate:       'assessment_report_date',
  assessmentReportComment:    'assessment_report_comment',
  poGenerationDate:           'po_generation_date',
  poIssue:                    'po_issue',
  poIssueOther:               'po_issue_other',
  contractPricingValue:       'contract_pricing_value',
  cilValue:                   'cil_value',
  poIssueDate:                'po_issue_date',
  partsETA:                   'parts_eta',
  partsDeliveryDate:          'parts_delivery_date',
  confirmationDate:           'confirmation_date',
  mismatchReported:           'mismatch_reported',
  replacementDate:            'replacement_date',
  jobEndDate:                 'job_end_date',
  jobEndStatus:               'job_end_status',
  customerType:               'customer_type',
  customerTypeOther:          'customer_type_other',
  nonMotorSubType:            'non_motor_sub_type',
  nonMotorAssessor:           'non_motor_assessor',
  nonMotorAssessorOther:      'non_motor_assessor_other',
  glassSupplier:              'glass_supplier',
  glassSupplierOther:         'glass_supplier_other',
  claimComment:               'claim_comment',
  claimCommentAwaiting:       'claim_comment_awaiting',
  reinsurer:                  'reinsurer',
  contactPhone:               'contact_phone',
  // F1 — decision fields (written only by /api/claims/:id/decide and
  // /api/claims/:id/reverse-decision; ignored by the normal PUT /api/claims/:id
  // update path so handlers can't bypass the audit by patching them directly).
  decisionStatus:             'decision_status',
  decisionBy:                 'decision_by',
  decisionByName:             'decision_by_name',
  decisionByRole:             'decision_by_role',
  decisionDate:               'decision_date',
  decisionNote:               'decision_note',
  decisionReversedAt:         'decision_reversed_at',
  decisionReversedBy:         'decision_reversed_by',
  // G1 — Graphite sync state. Read-only from the frontend's perspective:
  // these fields are written by the Graphite adapter (lib/graphite.js)
  // and the sweeper, NOT by normal PUT /api/claims/:id saves. They are
  // deliberately NOT in upsertClaim's column list (same protection
  // pattern as decision_*).
  graphiteClaimId:            'graphite_claim_id',
  graphiteClaimNumber:        'graphite_claim_number',
  graphiteSyncedAt:           'graphite_synced_at',
  graphiteSyncError:          'graphite_sync_error',
  graphiteSyncAttempts:       'graphite_sync_attempts',
};

/** Convert camelCase claim object (from frontend/data.json) to snake_case DB row. */
function claimToRow(obj) {
  const row = {};
  for (const [camel, snake] of Object.entries(CLAIM_FIELD_MAP)) {
    const val = obj[camel];
    // Convert numeric fields
    if (snake === 'reserve_amount' || snake === 'claim_paid_amount' ||
        snake === 'contract_pricing_value' || snake === 'cil_value') {
      row[snake] = parseFloat(val) || 0;
    } else {
      row[snake] = val != null ? String(val) : '';
    }
  }
  return row;
}

/** Convert snake_case DB row back to camelCase (for API responses). */
function rowToClaim(row) {
  const obj = {};
  for (const [camel, snake] of Object.entries(CLAIM_FIELD_MAP)) {
    const val = row[snake];
    // Convert numeric fields back to string (frontend expects strings)
    if (snake === 'reserve_amount' || snake === 'claim_paid_amount' ||
        snake === 'contract_pricing_value' || snake === 'cil_value') {
      obj[camel] = val ? String(val) : '';
    } else {
      obj[camel] = val != null ? val : '';
    }
  }
  return obj;
}

// ── User field mapping ───────────────────────────────────
const USER_FIELD_MAP = {
  id:           'user_id',
  username:     'username',
  password:     'password',
  role:         'role',
  name:         'name',
  active:       'active',
  status:       'status',
  ssoProvider:  'sso_provider',
  pages:        'pages',
  createdAt:    'created_at',
  lastActiveAt: 'last_active_at',
};

/** Convert camelCase user object to snake_case DB row. */
function userToRow(obj) {
  return {
    user_id:        obj.id   || ('u' + Date.now()),
    username:       (obj.username || '').toLowerCase(),
    password:       obj.password || '',
    role:           obj.role || 'claims-team',
    name:           obj.name || '',
    active:         obj.active === false ? 0 : 1,
    status:         obj.status || 'active',
    sso_provider:   obj.ssoProvider || '',
    pages:          obj.pages ? (typeof obj.pages === 'string' ? obj.pages : JSON.stringify(obj.pages)) : '',
    last_active_at: obj.lastActiveAt || null,
    source:         obj.source || 'live',
    imported_at:    obj.importedAt || null,
  };
}

/** Convert snake_case DB row back to camelCase user (for API responses). */
function rowToUser(row) {
  return {
    id:           row.user_id,
    username:     row.username,
    password:     row.password,
    role:         row.role,
    name:         row.name,
    active:       row.active === 1,
    status:       row.status,
    ssoProvider:  row.sso_provider || undefined,
    pages:        row.pages ? (function() { try { return JSON.parse(row.pages); } catch(_) { return row.pages; } })() : undefined,
    createdAt:    row.created_at,
    lastActiveAt: row.last_active_at,
  };
}

// ── Prepared statements ──────────────────────────────────
// Compiled once at startup — reused on every request.

const sql = {
  // -- Claims --
  getAllClaims:    db.prepare('SELECT * FROM claims ORDER BY created_at DESC'),
  getClaimById:   db.prepare('SELECT * FROM claims WHERE claim_id = ?'),
  insertClaim:    db.prepare(`INSERT INTO claims (
    claim_id, channel, broker_name, broker_other, client_name, policy_number, claim_number,
    claims_handler, claim_reported_date, claim_type, plate_number,
    reserve_amount, claim_paid_amount, claim_description,
    claim_docs_received, assessor_allotment_date, assessor_name,
    file_uploaded_to_gt, gt_number, stage1_comment, distance,
    panel_beater_name, panel_beater_other, physical_assessment,
    physical_assessment_comment, quote_request_date, quote_request_comment,
    under_warranty, quote_finalisation, assessment_report_date,
    assessment_report_comment, po_generation_date, po_issue, po_issue_other,
    contract_pricing_value, cil_value, po_issue_date,
    parts_eta, parts_delivery_date, confirmation_date, mismatch_reported,
    replacement_date, job_end_date, job_end_status,
    customer_type, customer_type_other, non_motor_sub_type,
    non_motor_assessor, non_motor_assessor_other,
    glass_supplier, glass_supplier_other,
    claim_comment, claim_comment_awaiting, reinsurer,
    source, imported_at, metric_key
  ) VALUES (
    @claim_id, @channel, @broker_name, @broker_other, @client_name, @policy_number, @claim_number,
    @claims_handler, @claim_reported_date, @claim_type, @plate_number,
    @reserve_amount, @claim_paid_amount, @claim_description,
    @claim_docs_received, @assessor_allotment_date, @assessor_name,
    @file_uploaded_to_gt, @gt_number, @stage1_comment, @distance,
    @panel_beater_name, @panel_beater_other, @physical_assessment,
    @physical_assessment_comment, @quote_request_date, @quote_request_comment,
    @under_warranty, @quote_finalisation, @assessment_report_date,
    @assessment_report_comment, @po_generation_date, @po_issue, @po_issue_other,
    @contract_pricing_value, @cil_value, @po_issue_date,
    @parts_eta, @parts_delivery_date, @confirmation_date, @mismatch_reported,
    @replacement_date, @job_end_date, @job_end_status,
    @customer_type, @customer_type_other, @non_motor_sub_type,
    @non_motor_assessor, @non_motor_assessor_other,
    @glass_supplier, @glass_supplier_other,
    @claim_comment, @claim_comment_awaiting, @reinsurer,
    @source, @imported_at, @metric_key
  )`),
  upsertClaim:    db.prepare(`INSERT INTO claims (
    claim_id, channel, broker_name, broker_other, client_name, policy_number, claim_number,
    claims_handler, claim_reported_date, claim_type, plate_number,
    reserve_amount, claim_paid_amount, claim_description,
    claim_docs_received, assessor_allotment_date, assessor_name,
    file_uploaded_to_gt, gt_number, stage1_comment, distance,
    panel_beater_name, panel_beater_other, physical_assessment,
    physical_assessment_comment, quote_request_date, quote_request_comment,
    under_warranty, quote_finalisation, assessment_report_date,
    assessment_report_comment, po_generation_date, po_issue, po_issue_other,
    contract_pricing_value, cil_value, po_issue_date,
    parts_eta, parts_delivery_date, confirmation_date, mismatch_reported,
    replacement_date, job_end_date, job_end_status,
    customer_type, customer_type_other, non_motor_sub_type,
    non_motor_assessor, non_motor_assessor_other,
    glass_supplier, glass_supplier_other,
    claim_comment, claim_comment_awaiting, reinsurer, contact_phone
  ) VALUES (
    @claim_id, @channel, @broker_name, @broker_other, @client_name, @policy_number, @claim_number,
    @claims_handler, @claim_reported_date, @claim_type, @plate_number,
    @reserve_amount, @claim_paid_amount, @claim_description,
    @claim_docs_received, @assessor_allotment_date, @assessor_name,
    @file_uploaded_to_gt, @gt_number, @stage1_comment, @distance,
    @panel_beater_name, @panel_beater_other, @physical_assessment,
    @physical_assessment_comment, @quote_request_date, @quote_request_comment,
    @under_warranty, @quote_finalisation, @assessment_report_date,
    @assessment_report_comment, @po_generation_date, @po_issue, @po_issue_other,
    @contract_pricing_value, @cil_value, @po_issue_date,
    @parts_eta, @parts_delivery_date, @confirmation_date, @mismatch_reported,
    @replacement_date, @job_end_date, @job_end_status,
    @customer_type, @customer_type_other, @non_motor_sub_type,
    @non_motor_assessor, @non_motor_assessor_other,
    @glass_supplier, @glass_supplier_other,
    @claim_comment, @claim_comment_awaiting, @reinsurer, @contact_phone
  ) ON CONFLICT(claim_id) DO UPDATE SET
    channel=excluded.channel,
    broker_name=excluded.broker_name, broker_other=excluded.broker_other,
    client_name=excluded.client_name,
    policy_number=excluded.policy_number, claim_number=excluded.claim_number,
    claims_handler=excluded.claims_handler, claim_reported_date=excluded.claim_reported_date,
    claim_type=excluded.claim_type, plate_number=excluded.plate_number,
    reserve_amount=excluded.reserve_amount, claim_paid_amount=excluded.claim_paid_amount,
    claim_description=excluded.claim_description, claim_docs_received=excluded.claim_docs_received,
    assessor_allotment_date=excluded.assessor_allotment_date, assessor_name=excluded.assessor_name,
    file_uploaded_to_gt=excluded.file_uploaded_to_gt, gt_number=excluded.gt_number,
    stage1_comment=excluded.stage1_comment, distance=excluded.distance,
    panel_beater_name=excluded.panel_beater_name, panel_beater_other=excluded.panel_beater_other,
    physical_assessment=excluded.physical_assessment,
    physical_assessment_comment=excluded.physical_assessment_comment,
    quote_request_date=excluded.quote_request_date,
    quote_request_comment=excluded.quote_request_comment,
    under_warranty=excluded.under_warranty, quote_finalisation=excluded.quote_finalisation,
    assessment_report_date=excluded.assessment_report_date,
    assessment_report_comment=excluded.assessment_report_comment,
    po_generation_date=excluded.po_generation_date, po_issue=excluded.po_issue,
    po_issue_other=excluded.po_issue_other,
    contract_pricing_value=excluded.contract_pricing_value, cil_value=excluded.cil_value,
    po_issue_date=excluded.po_issue_date,
    parts_eta=excluded.parts_eta, parts_delivery_date=excluded.parts_delivery_date,
    confirmation_date=excluded.confirmation_date, mismatch_reported=excluded.mismatch_reported,
    replacement_date=excluded.replacement_date, job_end_date=excluded.job_end_date,
    job_end_status=excluded.job_end_status,
    customer_type=excluded.customer_type, customer_type_other=excluded.customer_type_other,
    non_motor_sub_type=excluded.non_motor_sub_type,
    non_motor_assessor=excluded.non_motor_assessor,
    non_motor_assessor_other=excluded.non_motor_assessor_other,
    glass_supplier=excluded.glass_supplier, glass_supplier_other=excluded.glass_supplier_other,
    claim_comment=excluded.claim_comment, claim_comment_awaiting=excluded.claim_comment_awaiting,
    reinsurer=excluded.reinsurer,
    contact_phone=excluded.contact_phone,
    updated_at=datetime('now')
  `),
  deleteClaim:    db.prepare('DELETE FROM claims WHERE claim_id = ?'),
  countClaims:    db.prepare('SELECT COUNT(*) AS count FROM claims'),

  // F1 — decision writes (separate from upsertClaim so a normal PUT can't
  // accidentally clear or change a decision).
  decideClaim: db.prepare(`UPDATE claims SET
    decision_status      = @decision_status,
    decision_by          = @decision_by,
    decision_by_name     = @decision_by_name,
    decision_by_role     = @decision_by_role,
    decision_date        = @decision_date,
    decision_note        = @decision_note,
    decision_reversed_at = '',
    decision_reversed_by = '',
    updated_at           = datetime('now')
    WHERE claim_id = @claim_id`),
  reverseDecision: db.prepare(`UPDATE claims SET
    decision_reversed_at = @decision_reversed_at,
    decision_reversed_by = @decision_reversed_by,
    updated_at           = datetime('now')
    WHERE claim_id = @claim_id`),

  // G2 — record handler email at create time so the sweeper can preserve
  // attribution on retries (no req.user in the sweeper context).
  setGraphiteHandlerEmail: db.prepare(`UPDATE claims SET
    graphite_handler_email = ?,
    updated_at = datetime('now')
    WHERE claim_id = ?`),

  // G1 — Graphite sync writes (isolated from upsertClaim so a normal PUT
  // can't clear or overwrite a successful link).
  markGraphiteLinked: db.prepare(`UPDATE claims SET
    graphite_claim_id     = @graphite_claim_id,
    graphite_claim_number = @graphite_claim_number,
    graphite_synced_at    = datetime('now'),
    graphite_sync_error   = '',
    claim_number          = @claim_number_override,
    updated_at            = datetime('now')
    WHERE claim_id = @claim_id`),
  markGraphiteError: db.prepare(`UPDATE claims SET
    graphite_sync_error    = @error_message,
    graphite_sync_attempts = graphite_sync_attempts + 1,
    updated_at             = datetime('now')
    WHERE claim_id = @claim_id`),
  markGraphitePermanentFail: db.prepare(`UPDATE claims SET
    graphite_sync_error    = @error_message,
    graphite_sync_attempts = 10,
    updated_at             = datetime('now')
    WHERE claim_id = @claim_id`),
  resetGraphiteAttempts: db.prepare(`UPDATE claims SET
    graphite_sync_attempts = 9,
    updated_at             = datetime('now')
    WHERE claim_id = ?`),
  resetGraphiteSyncForRetry: db.prepare(`UPDATE claims SET
    graphite_sync_attempts = 0,
    graphite_sync_error    = '',
    updated_at             = datetime('now')
    WHERE claim_id = ?`),
  getNewlyFailedGraphiteSyncs: db.prepare(`SELECT * FROM claims
    WHERE (graphite_claim_number IS NULL OR graphite_claim_number = '')
      AND graphite_sync_attempts >= 10
      AND updated_at > datetime('now', '-1 day')`),
  getPendingGraphiteRetries: db.prepare(`SELECT * FROM claims
    WHERE (graphite_claim_number IS NULL OR graphite_claim_number = '')
      AND graphite_sync_error != ''
      AND graphite_sync_attempts < 10
      AND created_at > datetime('now','-7 days')
    ORDER BY graphite_sync_attempts ASC, created_at DESC
    LIMIT 50`),
  getFailedGraphiteSyncs: db.prepare(`SELECT * FROM claims
    WHERE (graphite_claim_number IS NULL OR graphite_claim_number = '')
      AND graphite_sync_attempts >= 10`),

  // -- Audit --
  getAllAudit:     db.prepare('SELECT * FROM audit ORDER BY ts DESC'),
  insertAudit:    db.prepare(`INSERT OR IGNORE INTO audit (
    audit_id, action, claim_num, detail, actor, actor_role, ts, source, imported_at
  ) VALUES (@audit_id, @action, @claim_num, @detail,
            COALESCE(@actor, 'system'), COALESCE(@actor_role, ''),
            @ts, @source, @imported_at)`),
  deleteAllAudit: db.prepare('DELETE FROM audit'),
  countAudit:     db.prepare('SELECT COUNT(*) AS count FROM audit'),

  // -- Users --
  getAllUsers:     db.prepare('SELECT * FROM users ORDER BY created_at'),
  getUserById:    db.prepare('SELECT * FROM users WHERE user_id = ?'),
  getUserByName:  db.prepare('SELECT * FROM users WHERE LOWER(username) = LOWER(?)'),
  insertUser:     db.prepare(`INSERT INTO users (
    user_id, username, password, role, name, active, status,
    sso_provider, pages, last_active_at, source, imported_at
  ) VALUES (
    @user_id, @username, @password, @role, @name, @active, @status,
    @sso_provider, @pages, @last_active_at, @source, @imported_at
  )`),
  updateUser:     db.prepare(`UPDATE users SET
    username=@username, password=@password, role=@role, name=@name,
    active=@active, status=@status, sso_provider=@sso_provider,
    pages=@pages, last_active_at=@last_active_at, updated_at=datetime('now')
    WHERE user_id=@user_id`),
  deleteUser:     db.prepare('DELETE FROM users WHERE user_id = ?'),
  countUsers:     db.prepare('SELECT COUNT(*) AS count FROM users'),

  // -- Settings --
  getSetting:     db.prepare('SELECT value FROM settings WHERE key = ?'),
  upsertSetting:  db.prepare(`INSERT INTO settings (key, value, updated_at)
    VALUES (?, ?, datetime('now'))
    ON CONFLICT(key) DO UPDATE SET value=excluded.value, updated_at=datetime('now')`),

  // -- Master Data --
  getMasterData:     db.prepare('SELECT category, items FROM master_data'),
  getMasterCategory: db.prepare('SELECT items FROM master_data WHERE category = ?'),
  upsertMasterData:  db.prepare(`INSERT INTO master_data (category, items, updated_at)
    VALUES (@category, @items, datetime('now'))
    ON CONFLICT(category) DO UPDATE SET items=excluded.items, updated_at=datetime('now')`),

  // -- API Access Log --
  insertAccessLog:   db.prepare(`INSERT INTO api_access_log (ip, username, method, path, status_code, user_agent)
    VALUES (@ip, @username, @method, @path, @status_code, @user_agent)`),
  getAccessLogs:     db.prepare(`SELECT * FROM api_access_log ORDER BY created_at DESC LIMIT ?`),
  getAccessLogsByIp: db.prepare(`SELECT * FROM api_access_log WHERE ip = ? ORDER BY created_at DESC LIMIT ?`),
  getAccessStats:    db.prepare(`SELECT ip, username, COUNT(*) AS hits, MAX(created_at) AS last_seen
    FROM api_access_log WHERE created_at >= ? GROUP BY ip, username ORDER BY hits DESC`),
  purgeOldLogs:      db.prepare(`DELETE FROM api_access_log WHERE created_at < ?`),

  // -- Trusted Sources --
  getAllTrustedSources: db.prepare('SELECT * FROM trusted_sources ORDER BY created_at DESC'),
  getTrustedByIp:      db.prepare('SELECT * FROM trusted_sources WHERE ip = ?'),
  upsertTrustedSource: db.prepare(`INSERT INTO trusted_sources (ip, label, added_by, active, updated_at)
    VALUES (@ip, @label, @added_by, @active, datetime('now'))
    ON CONFLICT(ip) DO UPDATE SET label=excluded.label, added_by=excluded.added_by,
    active=excluded.active, updated_at=datetime('now')`),
  deleteTrustedSource: db.prepare('DELETE FROM trusted_sources WHERE id = ?'),

  // -- Policy Wordings (F4-b) --
  getAllPolicyWordings: db.prepare(`SELECT * FROM policy_wordings ORDER BY active DESC, branch, title`),
  getPolicyWordingById: db.prepare(`SELECT * FROM policy_wordings WHERE id = ?`),
  insertPolicyWording:  db.prepare(`INSERT INTO policy_wordings
    (title, sharepoint_url, branch, products, coverages, visible_to_roles,
     version, notes, supersedes_id, added_by)
    VALUES (@title, @sharepoint_url, @branch, @products, @coverages,
            @visible_to_roles, @version, @notes, @supersedes_id, @added_by)`),
  updatePolicyWording:  db.prepare(`UPDATE policy_wordings SET
    title           = @title,
    sharepoint_url  = @sharepoint_url,
    branch          = @branch,
    products        = @products,
    coverages       = @coverages,
    visible_to_roles= @visible_to_roles,
    version         = @version,
    notes           = @notes,
    updated_at      = datetime('now')
    WHERE id = @id`),
  deactivatePolicyWording: db.prepare(`UPDATE policy_wordings SET
    active = 0, updated_at = datetime('now') WHERE id = ?`),
  activatePolicyWording: db.prepare(`UPDATE policy_wordings SET
    active = 1, updated_at = datetime('now') WHERE id = ?`),

  // -- Claim Messages / Mentions (Batch J) --
  getMessagesByClaim:  db.prepare(`SELECT * FROM claim_messages
    WHERE claim_id = ? ORDER BY created_at ASC`),
  getMessageById:      db.prepare(`SELECT * FROM claim_messages WHERE id = ?`),
  insertMessage:       db.prepare(`INSERT INTO claim_messages
    (claim_id, sender_id, sender_name, sender_role, body, priority, mentions)
    VALUES (@claim_id, @sender_id, @sender_name, @sender_role, @body, @priority, @mentions)`),
  updateMessageBody:   db.prepare(`UPDATE claim_messages SET
    body = @body, mentions = @mentions, edited_at = datetime('now')
    WHERE id = @id AND sender_id = @sender_id`),
  countMessagesByClaim: db.prepare(`SELECT claim_id, COUNT(*) AS n
    FROM claim_messages GROUP BY claim_id`),

  // -- Mention reads --
  insertMentionRead:   db.prepare(`INSERT INTO mention_reads
    (message_id, recipient_user_id, recipient_email)
    VALUES (@message_id, @recipient_user_id, @recipient_email)`),
  getMentionReadsForMsg: db.prepare(`SELECT * FROM mention_reads WHERE message_id = ?`),
  getUnreadForUser:    db.prepare(`SELECT mr.id AS read_id, mr.message_id, mr.read_at,
                                          m.claim_id, m.sender_name, m.sender_role, m.body,
                                          m.priority, m.created_at
    FROM mention_reads mr
    JOIN claim_messages m ON m.id = mr.message_id
    WHERE mr.recipient_user_id = ? AND mr.read_at IS NULL
    ORDER BY m.created_at DESC`),
  countUnreadByClaimForUser: db.prepare(`SELECT m.claim_id, COUNT(*) AS n
    FROM mention_reads mr JOIN claim_messages m ON m.id = mr.message_id
    WHERE mr.recipient_user_id = ? AND mr.read_at IS NULL
    GROUP BY m.claim_id`),
  markMentionRead:     db.prepare(`UPDATE mention_reads
    SET read_at = COALESCE(read_at, datetime('now'))
    WHERE message_id = ? AND recipient_user_id = ?`),
  markMentionUnread:   db.prepare(`UPDATE mention_reads
    SET read_at = NULL
    WHERE message_id = ? AND recipient_user_id = ?`),
  // Reminder scheduler — pending mentions older than threshold minutes
  getPendingReminders: db.prepare(`SELECT mr.id AS read_id, mr.message_id,
                                          mr.recipient_user_id, mr.recipient_email,
                                          m.claim_id, m.sender_name, m.body,
                                          m.priority, m.created_at,
                                          (julianday('now') - julianday(m.created_at)) * 24 * 60 AS age_minutes
    FROM mention_reads mr JOIN claim_messages m ON m.id = mr.message_id
    WHERE mr.read_at IS NULL AND mr.reminder_sent_at IS NULL
      AND m.priority != '' `),
  markReminderSent:    db.prepare(`UPDATE mention_reads
    SET reminder_sent_at = datetime('now') WHERE id = ?`),

  // -- Claim comments resolution --
  resolveClaimComments:  db.prepare(`UPDATE claims SET
    comments_resolved_at = datetime('now'),
    comments_resolved_by = ?,
    updated_at = datetime('now')
    WHERE claim_id = ?`),
  reopenClaimComments:   db.prepare(`UPDATE claims SET
    comments_resolved_at = '',
    comments_resolved_by = '',
    updated_at = datetime('now')
    WHERE claim_id = ?`),
  getClaimCommentsResolution: db.prepare(`SELECT comments_resolved_at, comments_resolved_by
    FROM claims WHERE claim_id = ?`),

  // -- Backdate Grants --
  insertGrant:    db.prepare(`INSERT INTO backdate_grants
    (target_user_id, target_label, granted_by, reason, expires_at)
    VALUES (@target_user_id, @target_label, @granted_by, @reason, @expires_at)`),
  getAllGrants:   db.prepare(`SELECT * FROM backdate_grants ORDER BY granted_at DESC LIMIT ?`),
  getActiveGrants: db.prepare(`SELECT * FROM backdate_grants
    WHERE expires_at > datetime('now') AND revoked_at IS NULL
    ORDER BY granted_at DESC`),
  getGrantById:   db.prepare(`SELECT * FROM backdate_grants WHERE id = ?`),
  revokeGrant:    db.prepare(`UPDATE backdate_grants
    SET revoked_at = datetime('now'), revoked_by = ? WHERE id = ?`),

  // -- Backdate Events --
  insertEvent:    db.prepare(`INSERT INTO backdate_events
    (grant_id, claim_id, claim_number, username, user_role, changes_json)
    VALUES (@grant_id, @claim_id, @claim_number, @username, @user_role, @changes_json)`),
  getRecentEvents: db.prepare(`SELECT * FROM backdate_events ORDER BY created_at DESC LIMIT ?`),

  // -- Email Schedules --
  insertSchedule: db.prepare(`INSERT INTO email_schedules
    (name, report_type, recipients, frequency, day_of_week, day_of_month, hour, enabled, notes, created_by)
    VALUES (@name, @report_type, @recipients, @frequency, @day_of_week, @day_of_month, @hour, @enabled, @notes, @created_by)`),
  updateSchedule: db.prepare(`UPDATE email_schedules SET
    name=@name, report_type=@report_type, recipients=@recipients,
    frequency=@frequency, day_of_week=@day_of_week, day_of_month=@day_of_month,
    hour=@hour, enabled=@enabled, notes=@notes, updated_at=datetime('now')
    WHERE id=@id`),
  deleteSchedule: db.prepare(`DELETE FROM email_schedules WHERE id = ?`),
  getAllSchedules: db.prepare(`SELECT * FROM email_schedules ORDER BY enabled DESC, name COLLATE NOCASE`),
  getScheduleById: db.prepare(`SELECT * FROM email_schedules WHERE id = ?`),
  getEnabledSchedules: db.prepare(`SELECT * FROM email_schedules WHERE enabled = 1`),
  countSchedules:    db.prepare(`SELECT COUNT(*) AS n FROM email_schedules`),
  markScheduleSent:  db.prepare(`UPDATE email_schedules SET
    last_sent_at = datetime('now'), last_sent_status = @status, last_run_at = datetime('now')
    WHERE id = @id`),
  markScheduleRun:   db.prepare(`UPDATE email_schedules SET
    last_run_at = datetime('now'), last_sent_status = @status
    WHERE id = @id`),

  // -- Backdate Requests --
  insertRequest: db.prepare(`INSERT INTO backdate_requests
    (approve_token, requester_id, requester_username, requester_name, requester_role,
     claim_ids_json, claim_numbers, reason, duration_hours, urgency, status)
    VALUES (@approve_token, @requester_id, @requester_username, @requester_name, @requester_role,
     @claim_ids_json, @claim_numbers, @reason, @duration_hours, @urgency, 'pending')`),
  getRequestById:    db.prepare(`SELECT * FROM backdate_requests WHERE id = ?`),
  getRequestByToken: db.prepare(`SELECT * FROM backdate_requests WHERE approve_token = ?`),
  getPendingRequests: db.prepare(`SELECT * FROM backdate_requests WHERE status = 'pending' ORDER BY created_at DESC`),
  getRecentRequests:  db.prepare(`SELECT * FROM backdate_requests ORDER BY created_at DESC LIMIT ?`),
  getRequestsByUser:  db.prepare(`SELECT * FROM backdate_requests WHERE requester_id = ? ORDER BY created_at DESC LIMIT ?`),
  decideRequest:     db.prepare(`UPDATE backdate_requests
    SET status = @status, decided_at = datetime('now'), decided_by = @decided_by,
        decision_note = @decision_note, grant_id = @grant_id
    WHERE id = @id`),

  // -- Claim Status Links --
  insertClaimLink: db.prepare(`INSERT INTO claim_links
    (claim_id, token_hash, contact_name, contact_phone, contact_channel,
     issued_by, expires_at)
    VALUES (@claim_id, @token_hash, @contact_name, @contact_phone, @contact_channel,
            @issued_by, @expires_at)`),
  getLinkByHash:   db.prepare(`SELECT * FROM claim_links WHERE token_hash = ?`),
  getLinkById:     db.prepare(`SELECT * FROM claim_links WHERE id = ?`),
  getActiveLinksForClaim: db.prepare(`SELECT * FROM claim_links
    WHERE claim_id = ? AND revoked_at IS NULL AND expires_at > datetime('now')
    ORDER BY issued_at DESC`),
  getAllLinksForClaim: db.prepare(`SELECT * FROM claim_links
    WHERE claim_id = ? ORDER BY issued_at DESC`),
  revokeLink:      db.prepare(`UPDATE claim_links
    SET revoked_at = datetime('now'), revoke_reason = @reason
    WHERE id = @id AND revoked_at IS NULL`),
  revokeAllLinksForClaim: db.prepare(`UPDATE claim_links
    SET revoked_at = datetime('now'), revoke_reason = @reason
    WHERE claim_id = @claim_id AND revoked_at IS NULL`),

  // -- Claim Link OTPs --
  insertLinkOtp:   db.prepare(`INSERT INTO claim_link_otps
    (link_id, otp_hash, sent_via, expires_at)
    VALUES (@link_id, @otp_hash, @sent_via, @expires_at)`),
  getLatestPendingOtp: db.prepare(`SELECT * FROM claim_link_otps
    WHERE link_id = ? AND verified_at IS NULL AND expires_at > datetime('now')
    ORDER BY sent_at DESC LIMIT 1`),
  bumpOtpAttempts: db.prepare(`UPDATE claim_link_otps
    SET attempts = attempts + 1 WHERE id = ?`),
  markOtpVerified: db.prepare(`UPDATE claim_link_otps
    SET verified_at = datetime('now') WHERE id = ?`),

  // -- Claim Link Access Log --
  insertLinkAccess: db.prepare(`INSERT INTO claim_link_access_log
    (link_id, token_tail, ip, user_agent, action, detail)
    VALUES (@link_id, @token_tail, @ip, @user_agent, @action, @detail)`),
  getLinkAccessForLink: db.prepare(`SELECT * FROM claim_link_access_log
    WHERE link_id = ? ORDER BY ts DESC LIMIT ?`),
  countLinkFailuresFromIp: db.prepare(`SELECT COUNT(*) AS n FROM claim_link_access_log
    WHERE ip = ? AND action = 'otp_verify_fail' AND ts > datetime('now', '-10 minutes')`),

  // -- Notification Log --
  insertNotification:    db.prepare(`INSERT INTO notification_log
    (claim_id, channel, provider, trigger_key, recipient, template_key,
     payload_hash, provider_msg_id, status, cost_units, error)
    VALUES (@claim_id, @channel, @provider, @trigger_key, @recipient,
            @template_key, @payload_hash, @provider_msg_id, @status,
            @cost_units, @error)`),
  updateNotificationStatus: db.prepare(`UPDATE notification_log SET
    status = @status, error = @error, delivered_at = @delivered_at,
    updated_at = datetime('now')
    WHERE id = @id`),
  updateNotificationByProviderId: db.prepare(`UPDATE notification_log SET
    status = @status, error = @error, delivered_at = @delivered_at,
    updated_at = datetime('now')
    WHERE provider = @provider AND provider_msg_id = @provider_msg_id`),
  // Dedup: did we already send this trigger to this recipient recently?
  countRecentSimilar: db.prepare(`SELECT COUNT(*) AS n FROM notification_log
    WHERE trigger_key = @trigger_key AND recipient = @recipient
      AND status NOT IN ('failed','rejected','suppressed')
      AND sent_at > datetime('now', '-1 hour')`),
  // Per-claim hard cap (excluding OTP messages and suppressions)
  countNotificationsForClaim: db.prepare(`SELECT COUNT(*) AS n FROM notification_log
    WHERE claim_id = ? AND trigger_key != 'otp_request'
      AND status NOT IN ('failed','rejected','suppressed')`),
  getNotificationsForClaim: db.prepare(`SELECT * FROM notification_log
    WHERE claim_id = ? ORDER BY sent_at DESC LIMIT ?`),
  // Admin dashboard aggregations
  notificationDailyCounts: db.prepare(`
    SELECT date(sent_at) AS day,
           channel,
           status,
           COUNT(*)     AS n,
           SUM(cost_units) AS cost
    FROM notification_log
    WHERE sent_at >= datetime('now', ?)
    GROUP BY date(sent_at), channel, status
    ORDER BY day DESC`),
  notificationByTrigger: db.prepare(`
    SELECT trigger_key,
           COUNT(*)        AS n,
           SUM(cost_units) AS cost,
           SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) AS delivered,
           SUM(CASE WHEN status='failed'    THEN 1 ELSE 0 END) AS failed,
           SUM(CASE WHEN status='suppressed' THEN 1 ELSE 0 END) AS suppressed
    FROM notification_log
    WHERE sent_at >= datetime('now', ?)
    GROUP BY trigger_key
    ORDER BY n DESC`),
  notificationTotals: db.prepare(`
    SELECT COUNT(*)        AS total,
           SUM(CASE WHEN status='sent' OR status='delivered' THEN 1 ELSE 0 END) AS ok,
           SUM(CASE WHEN status='delivered'  THEN 1 ELSE 0 END) AS delivered,
           SUM(CASE WHEN status='failed'     THEN 1 ELSE 0 END) AS failed,
           SUM(CASE WHEN status='rejected'   THEN 1 ELSE 0 END) AS rejected,
           SUM(CASE WHEN status='suppressed' THEN 1 ELSE 0 END) AS suppressed,
           SUM(cost_units) AS cost
    FROM notification_log
    WHERE sent_at >= datetime('now', ?)`),
  notificationRecentFailures: db.prepare(`
    SELECT id, claim_id, channel, trigger_key, recipient, status, error, sent_at
    FROM notification_log
    WHERE status IN ('failed','rejected')
      AND sent_at >= datetime('now', ?)
    ORDER BY sent_at DESC LIMIT ?`),
  notificationRecentAll: db.prepare(`
    SELECT id, claim_id, channel, provider, trigger_key, recipient, status,
           cost_units, error, sent_at, delivered_at
    FROM notification_log
    ORDER BY sent_at DESC LIMIT ?`),
};

// ── Graceful shutdown ────────────────────────────────────
process.on('exit',    () => db.close());
process.on('SIGINT',  () => { db.close(); process.exit(0); });
process.on('SIGTERM', () => { db.close(); process.exit(0); });

module.exports = { db, sql, DB_PATH, CLAIM_FIELD_MAP, claimToRow, rowToClaim, USER_FIELD_MAP, userToRow, rowToUser };
