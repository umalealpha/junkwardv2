-- ============================================================
-- Graphite v2 — DB Maintenance Script
-- Run ONCE on production database: Graphite_live
-- mysql -u [user] -p Graphite_live < DB_MAINTENANCE.sql
-- Or run via: php artisan migrate
-- ============================================================

-- ─── 1. Python Engine Tables ────────────────────────────────
-- Run this if you haven't run pyengine/setup_db.sql already.

CREATE TABLE IF NOT EXISTS cron_runs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_key     VARCHAR(100)   NOT NULL,
    status      ENUM('ok','error','running') DEFAULT 'running',
    summary     JSON,
    output_file VARCHAR(500),
    elapsed     VARCHAR(50),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job_key  (job_key),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS report_stakeholders (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_type VARCHAR(100)  NOT NULL,
    name        VARCHAR(200),
    email       VARCHAR(200)  NOT NULL,
    active      TINYINT(1)    DEFAULT 1,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_report_type (report_type),
    INDEX idx_active      (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cron_jobs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_key         VARCHAR(100) NOT NULL UNIQUE,
    label           VARCHAR(200),
    schedule        VARCHAR(100) COMMENT 'cron expression override — NULL = use built-in default',
    enabled         TINYINT(1)   DEFAULT 1,
    email_enabled   TINYINT(1)   DEFAULT 1,
    recipients_override JSON     COMMENT 'if set, overrides report_stakeholders for this job',
    threshold_config    JSON     COMMENT 'job-specific threshold params',
    notes           TEXT,
    last_config_by  VARCHAR(200),
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_job_key (job_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 2. Performance Indexes on cron_status ──────────────────
-- cron_status currently has 196K+ rows with NO indexes.
-- These indexes fix:
--   • kernelJobs()        — GROUP BY name + MAX(start/end)
--   • showKernelJob()     — WHERE name=? ORDER BY start DESC
--   • kernelStatusSummary — WHERE start >= now()-24h

ALTER TABLE cron_status
    ADD INDEX IF NOT EXISTS idx_cron_status_name        (name),
    ADD INDEX IF NOT EXISTS idx_cron_status_name_start  (name, start),
    ADD INDEX IF NOT EXISTS idx_cron_status_start       (start),
    ADD INDEX IF NOT EXISTS idx_cron_status_end         (end);

-- ─── 3. Seed Report Stakeholders ────────────────────────────
-- Add your actual finance/IT email recipients here.
-- These receive the 10 scheduled Python finance reports.

INSERT IGNORE INTO report_stakeholders (report_type, name, email, active) VALUES
-- Written Premium (runs 1st of every month)
('written_premium', 'Finance Manager',   'finance@alphadirect.co.bw',  1),
('written_premium', 'CFO',              'cfo@alphadirect.co.bw',       1),
-- Debtors Ageing (every Monday)
('ageing',          'Credit Control',   'credit@alphadirect.co.bw',    1),
('ageing',          'Finance Manager',  'finance@alphadirect.co.bw',   1),
-- Finance Anomaly (daily)
('anomaly',         'IT Admin',         'it@alphadirect.co.bw',        1),
('anomaly',         'Finance Manager',  'finance@alphadirect.co.bw',   1),
-- Premium Anomaly (every Wednesday)
('premium_anomaly', 'Finance Manager',  'finance@alphadirect.co.bw',   1),
('premium_anomaly', 'IT Admin',         'it@alphadirect.co.bw',        1),
-- Payment Anomaly (daily)
('payment_anomaly', 'IT Admin',         'it@alphadirect.co.bw',        1),
('payment_anomaly', 'Finance Manager',  'finance@alphadirect.co.bw',   1),
-- KYC Compliance (every Tuesday)
('kyc_compliance_report', 'Compliance Officer', 'compliance@alphadirect.co.bw', 1),
('kyc_compliance_report', 'IT Admin',           'it@alphadirect.co.bw',        1),
-- Reinsurance Anomaly (every Friday)
('reinsurance_anomaly', 'Reinsurance Team',  'reinsurance@alphadirect.co.bw', 1),
('reinsurance_anomaly', 'Finance Manager',   'finance@alphadirect.co.bw',     1),
-- Policy Audit (daily)
('policy_audit',    'IT Admin',         'it@alphadirect.co.bw',         1),
('policy_audit',    'Operations',       'operations@alphadirect.co.bw', 1),
-- Collections Report (every Monday)
('collections_report', 'Finance Manager', 'finance@alphadirect.co.bw',  1),
('collections_report', 'Credit Control', 'credit@alphadirect.co.bw',   1),
-- Claims Anomaly (every Thursday)
('claims_anomaly',  'Claims Manager',   'claims@alphadirect.co.bw',     1),
('claims_anomaly',  'IT Admin',         'it@alphadirect.co.bw',         1);

-- ─── 4. Expand policy_actions.status enum ───────────────────
-- Add LAPSED status (used by lapsePolicy()) if not already present.
-- Safe to run multiple times — MySQL ignores a no-op ALTER.
ALTER TABLE policy_actions
    MODIFY COLUMN `status` ENUM(
        'QUOTE','ISSUED','IN_APPROVAL','APPROVED','REJECTED','LAPSED'
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'QUOTE';

-- ─── Done ────────────────────────────────────────────────────
-- After running this SQL, also run:
--   php artisan migrate     ← applies the 2026_04_05_* migrations
--   php artisan cache:clear ← flush any stale cache
