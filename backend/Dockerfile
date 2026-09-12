# ARG must be declared before the first FROM to be usable across all stages
ARG BASE_IMAGE=623467544388.dkr.ecr.af-south-1.amazonaws.com/graphite-base:latest

# Pull wkhtmltopdf binary from surnet's pre-built Alpine image (multi-stage copy)
# surnet/alpine-wkhtmltopdf compiles wkhtmltopdf against musl/Alpine — no apk repo needed
FROM surnet/alpine-wkhtmltopdf:3.21.3-0.12.6-full AS wkhtmltopdf

# Use pre-built base image with PHP extensions (saves ~8 min per build)
# To rebuild base: docker build -f Dockerfile.base -t 623467544388.dkr.ecr.af-south-1.amazonaws.com/graphite-base:latest .
FROM ${BASE_IMAGE} AS base

# Copy wkhtmltopdf binary + install required fonts/libs
COPY --from=wkhtmltopdf /bin/wkhtmltopdf /usr/bin/wkhtmltopdf
RUN apk add --no-cache \
    ttf-dejavu fontconfig libxrender libx11 libxext \
    && wkhtmltopdf --version

# poppler-utils — `pdftotext -layout`, the only reader here that preserves a
# PDF's column geometry. smalot/pdfparser (the PHP fallback) flattens tables, so
# a broker schedule row comes out as "Buildings 3,120,0000.001013,151.20": sum
# insured, rate and premium glued into one unreadable number. Underwriting data
# cannot be extracted from that. ~4 MB, and it also revives OcrExtractor's
# long-dead `pdftotext` fallback (it has always shelled out to a binary that was
# never installed). Lives in this image, NOT the base, so a normal backend
# deploy picks it up without a base-image rebuild.
RUN apk add --no-cache poppler-utils && pdftotext -v 2>&1 | head -1

# App-specific configs
COPY docker/php.ini "$PHP_INI_DIR/conf.d/custom.ini"
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# ── Dependencies stage ──────────────────────────────────────
FROM base AS dependencies

COPY composer.json ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs --no-interaction

# ── Application stage ───────────────────────────────────────
FROM base AS app

# Copy vendor from dependencies stage
COPY --from=dependencies /var/www/html/vendor ./vendor

# Copy application code
COPY . .

# Ensure .env exists (required for Laravel to resolve paths)
RUN test -f .env || (test -f .env.example && cp .env.example .env || touch .env)

# Create Laravel directories and set permissions
RUN mkdir -p storage/framework/{sessions,views,cache} \
    storage/logs storage/app/public \
    storage/fonts \
    storage/app/quote_sheet \
    bootstrap/cache \
    && touch database/sanctum.sqlite \
    && chown -R www-data:www-data storage bootstrap/cache database/sanctum.sqlite \
    && chmod -R 775 storage bootstrap/cache

# Clear ALL stale bootstrap cache
RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php bootstrap/cache/routes-v7.php

# Disable classmap optimization to use runtime PSR-4 resolution
RUN composer config optimize-autoloader false

# Generate autoloader
RUN composer dump-autoload --no-dev --no-scripts --ignore-platform-reqs

# Clear Laravel caches to prevent stale model/config from persisting
RUN php artisan config:clear 2>/dev/null || true \
    && php artisan route:clear 2>/dev/null || true \
    && php artisan view:clear 2>/dev/null || true

# Set Laravel paths explicitly (bypasses realpath() which fails in containers)
ENV VIEW_COMPILED_PATH=/var/www/html/storage/framework/views
ENV SESSION_FILE_PATH=/var/www/html/storage/framework/sessions
ENV DB_SQLITE_DATABASE=/var/www/html/database/sanctum.sqlite

# Create log directories
RUN mkdir -p /var/log/supervisor /var/run/php

# Entrypoint: fixes EFS volume permissions before starting supervisord
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost/api/v1/health || exit 1

CMD ["/entrypoint.sh"]
