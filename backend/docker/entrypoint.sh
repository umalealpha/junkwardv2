#!/bin/sh
set -e

# New files/dirs created later (by artisan, by sync-static-pdfs, etc.) inherit
# group-write so www-data can modify them without needing to own them.
umask 0002

# ── Fix storage permissions ────────────────────────────────────────────────
# EFS/volume mounts reset ownership — www-data can't write without this.
# Own AND mode: chown so www-data is the owner (not just group member),
# chmod 2775 (setgid) so subdirs created by root later inherit www-data group
# and stay group-writable. Fixes:
#   "Unable to create lockable file: .../storage/framework/cache/data/XX/YY/..."
# Laravel's file cache + rate-limiter mkdir's sharded subdirs on demand; if
# www-data can't create those at runtime, login throttling / caching 500s.
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R u+rwX,g+rwX,o+rX  /var/www/html/storage /var/www/html/bootstrap/cache
find /var/www/html/storage /var/www/html/bootstrap/cache -type d -exec chmod g+s {} \;

# Ensure all required directories exist.
# The storage/app/public/* output dirs below are written to by the PDF
# generators via $pdf->save() (PDFMerger / Snappy) and file_put_contents(),
# neither of which auto-create parent dirs — so a cold container fails with
# "Permission denied" / "Failed to open stream" unless we pre-create here.
mkdir -p /var/www/html/storage/logs \
         /var/www/html/storage/app/cron-logs \
         /var/www/html/storage/app/public \
         /var/www/html/storage/app/public/quote_sheet \
         /var/www/html/storage/app/public/quote_sheet_engineering \
         /var/www/html/storage/app/public/quote_sheet_specialist_product \
         /var/www/html/storage/app/public/quote_sheet_professional_indemnity_pdf \
         /var/www/html/storage/app/public/quote_sheet_doc \
         /var/www/html/storage/app/public/policy_doc \
         /var/www/html/storage/app/public/invoice \
         /var/www/html/storage/app/public/cover_note \
         /var/www/html/storage/app/public/cancel_note \
         /var/www/html/storage/app/public/rate_sheet \
         /var/www/html/storage/app/public/account_statement \
         /var/www/html/storage/app/public/CoverageWiseMultimark \
         /var/www/html/storage/app/public/policy-wordings/professional-indemnity \
         /var/www/html/storage/app/public/policy-wordings/medical \
         /var/www/html/storage/app/public/policy-wordings/travel \
         /var/www/html/storage/app/quote_sheet \
         /var/www/html/storage/app/temp_policy_wordings \
         /var/www/html/storage/app/temp/pdf_merge \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache \
         /var/log/supervisor \
         /var/run/php

# Final post-mkdir ownership + perms pass so the directories we just made
# match www-data:www-data with setgid. Avoids the cache-write 500s under load.
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R u+rwX,g+rwX       /var/www/html/storage /var/www/html/bootstrap/cache
find /var/www/html/storage /var/www/html/bootstrap/cache -type d -exec chmod g+s {} \;

# ── Docroot writable for legacy PDFMerger sinks ───────────────────────────
# ~11 call sites in Admin/PolicyController.php + PDFController.php do
# `$pdf->save(public_path(time().'.pdf'))` (PDFMerger / Snappy wrappers).
# The docroot is root-owned in the image so www-data hits EACCES and the
# v2_pdf_jobs row fails "file_put_contents(/var/www/html/public/<ts>.pdf):
# Permission denied". Setgid + group-write lets www-data write into the
# docroot without touching any existing committed files. Accumulation is
# bounded by Kernel::schedule()'s hourly prune (docroot *.pdf > 24h).
chgrp www-data /var/www/html/public
chmod g+rwxs   /var/www/html/public

# ── Create SQLite Sanctum table if missing ─────────────────────────────────
# SQLite needs write permission on BOTH the file AND the directory (for WAL/journal)
SQLITE_DB="${DB_SQLITE_DATABASE:-/var/www/html/database/sanctum.sqlite}"
mkdir -p "$(dirname "$SQLITE_DB")"
chmod 777 "$(dirname "$SQLITE_DB")"
touch "$SQLITE_DB" && chmod 666 "$SQLITE_DB"
php -r "
\$db = new SQLite3('$SQLITE_DB');
\$db->exec('CREATE TABLE IF NOT EXISTS personal_access_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL,
    abilities TEXT,
    last_used_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)');
echo \"Sanctum SQLite ready: $SQLITE_DB\n\";
"

# ── Clear all Laravel caches ──────────────────────────────────────────────
# Clear application cache, config, routes, views to ensure fresh code is used
cd /var/www/html && php artisan cache:clear 2>&1 || true
cd /var/www/html && php artisan config:clear 2>&1 || true
cd /var/www/html && php artisan route:clear 2>&1 || true
cd /var/www/html && php artisan view:clear 2>&1 || true

# ── Run database migrations (FAIL-LOUD) ───────────────────────────────────
# Runs all pending migrations on every container start. A FAILED migration must
# NOT silently boot a stale-schema app — that "|| true" masked weeks of skipped
# migrations (the 2026-06 staging incident: help-desk tables never created, the
# detail page 500'd, and "migrate always fails" went unnoticed). On failure we
# log a clear marker and exit non-zero: the new task fails its health check, ECS
# keeps the previous (working) task serving, and the deploy VISIBLY fails instead
# of quietly running on an out-of-date schema. Fix the migration + redeploy —
# never re-add "|| true" here.
cd /var/www/html
if ! php artisan migrate --force --no-interaction 2>&1; then
    echo "============================================================"
    echo "FATAL: database migration FAILED — aborting container start."
    echo "ECS keeps the previous task serving (no stale-schema boot)."
    echo "Fix the migration and redeploy. DO NOT mask this with '|| true'."
    echo "============================================================"
    exit 1
fi

# ── Sync static PDF assets from S3 ─────────────────────────────────────────
# Legacy PDF generators (v2_quotationPdf*, account_statement, invoice) merge
# in templates under storage/app/CoverageWiseMultimark/. V2 containers ship
# without those files; we pull them from the 'documents' disk (dedicated
# af-south-1 bucket — see config/filesystems.php) on every boot. The
# command is idempotent — matching local/S3 sizes are skipped, so this
# costs ~1–2s once everything is in place. Failures here must NOT block
# container start (Puppeteer path still works without templates for
# non-legacy flows) so we run it with || true.
cd /var/www/html && php artisan storage:sync-static-pdfs down --disk=documents 2>&1 || true

# Canonical policy wordings (org-wide) — Wordings Manager writes here from
# the Graphite admin UI. Synced down on every boot so the PDF generators
# can merge product-specific wordings (funeral, legal, hospital cashback,
# motor 3rd party, etc.) without a runtime S3 round-trip. See
# D:\ADRisk\Wordings_Policy.html for the standard.
cd /var/www/html && php artisan storage:sync-static-pdfs down --disk=documents --prefix=policy-wordings 2>&1 || true

# ── Regenerate /dev/swagger OpenAPI spec from current routes ───────────────
# The spec is no longer committed (was prone to drift — ops would add a
# route without remembering to run dev:generate-docs, leaving Swagger UI
# stale and operationIds duplicated, which broke the expand/collapse UX).
# Regenerating on every boot guarantees the spec matches the routes the
# container actually serves. The command only inspects Route::getRoutes()
# — no DB calls — so it's cheap (<1s) and safe even if the DB is briefly
# unreachable on cold start. Falls back silently to the prior file if
# generation fails. Chown after so DevPortalController's `?refresh=1` web
# call (running as www-data) can still rewrite the file later.
mkdir -p /var/www/html/dev
cd /var/www/html && php artisan dev:generate-docs 2>&1 || true
chown -R www-data:www-data /var/www/html/dev

# ── Start all services via supervisord ─────────────────────────────────────
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
