-- ============================================================
-- PyEngine DB Setup
-- Run once: mysql -u user -p Graphite_live < setup_db.sql
-- ============================================================

-- Cron run history
CREATE TABLE IF NOT EXISTS cron_runs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_key     VARCHAR(100)   NOT NULL,
    status      ENUM('ok','error','running') DEFAULT 'running',
    summary     JSON,
    output_file VARCHAR(500),
    elapsed     VARCHAR(50),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job_key   (job_key),
    INDEX idx_created   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Report stakeholder delivery list
CREATE TABLE IF NOT EXISTS report_stakeholders (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_type VARCHAR(100)  NOT NULL,
    name        VARCHAR(200),
    email       VARCHAR(200)  NOT NULL,
    active      TINYINT(1)    DEFAULT 1,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_report_type (report_type),
    INDEX idx_active      (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: seed stakeholders
-- INSERT INTO report_stakeholders (report_type, name, email) VALUES
-- ('written_premium', 'Finance Manager', 'finance@alphadirect.co.bw'),
-- ('ageing',          'Credit Control',  'credit@alphadirect.co.bw'),
-- ('anomaly',         'IT Admin',        'it@alphadirect.co.bw');

-- Per-job configuration (schedule overrides, email settings, thresholds)
CREATE TABLE IF NOT EXISTS cron_jobs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_key       VARCHAR(100) NOT NULL UNIQUE,
    label         VARCHAR(200),
    schedule      VARCHAR(100) COMMENT 'cron expression, NULL = use built-in default',
    enabled       TINYINT(1) DEFAULT 1,
    email_enabled TINYINT(1) DEFAULT 1,
    recipients_override JSON COMMENT 'if set, overrides report_stakeholders for this job',
    threshold_config    JSON COMMENT 'job-specific threshold params',
    notes         TEXT,
    last_config_by VARCHAR(200),
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_job_key (job_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Finance dashboard pre-computed cache (updated by dashboard cron)
CREATE TABLE IF NOT EXISTS finance_dashboard_cache (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cache_date   DATE NOT NULL,
    data         LONGTEXT NOT NULL COMMENT 'JSON payload',
    computed_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_date (cache_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
