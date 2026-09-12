#!/bin/sh
set -e

# ── Fix storage permissions ────────────────────────────────────────────────
# EFS/volume mounts don't preserve container ownership — fix on every start.
chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache

# Ensure log directories exist
# storage/app/cron-logs/ lives on the shared EFS — that's how the backend's
# admin Cron Logs page (CronLogsController) reads the cron's laravel log.
mkdir -p /var/www/html/storage/logs \
         /var/www/html/storage/app/reports \
         /var/www/html/storage/app/cron-logs \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache \
         /var/log/pyengine

chmod -R 777 /var/log/pyengine

# ── Run migrations ──────────────────────────────────────────────────────────
# Creates cron_runs table and any other pending migrations.
cd /var/www/html && php artisan migrate --force --no-interaction 2>&1 || true

# ── Clear stale schedule:run overlap locks ─────────────────────────────────
# withoutOverlapping() locks live in the local file cache. If a previous run
# crashed mid-flight without releasing the lock (e.g. OOM, container killed),
# every subsequent schedule:run skips the command silently — even though the
# previous container is gone. Clearing on startup guarantees a fresh task
# never inherits a stranded lock from a dead one.
cd /var/www/html && php artisan schedule:clear-cache 2>&1 || true

# ── Start all services via supervisord ─────────────────────────────────────
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
