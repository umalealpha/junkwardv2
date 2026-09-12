# Use pre-built base image with PHP extensions (Alpine-based)
ARG BASE_IMAGE=623467544388.dkr.ecr.af-south-1.amazonaws.com/graphite-base:latest
FROM ${BASE_IMAGE} AS base

# PHP + Nginx config
COPY docker/php.ini "$PHP_INI_DIR/conf.d/custom.ini"
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# ── Install Python 3, Node.js, and crond ──────────────────
RUN apk add --no-cache \
        python3 py3-pip \
        nodejs npm \
        dcron \
    && ln -sf /usr/bin/python3 /usr/bin/python

# Supervisor config (PHP scheduler + crond)
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# ── Dependencies stage ──────────────────────────────────────
FROM base AS dependencies

COPY composer.json ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs --no-interaction

# Python packages in separate cached layer
COPY pyengine/requirements.txt /tmp/pyengine-requirements.txt
RUN pip3 install --no-cache-dir --break-system-packages -r /tmp/pyengine-requirements.txt

# Node packages for scripts/
COPY scripts/package.json scripts/package-lock.json* ./scripts/
RUN cd scripts && npm install --omit=dev --silent

# ── Application stage ───────────────────────────────────────
FROM base AS app

# Re-install Python + Node dependencies in final image
COPY pyengine/requirements.txt /tmp/pyengine-requirements.txt
RUN pip3 install --no-cache-dir --break-system-packages -r /tmp/pyengine-requirements.txt

COPY --from=dependencies /var/www/html/vendor ./vendor
COPY --from=dependencies /var/www/html/scripts/node_modules ./scripts/node_modules
COPY . .

# Ensure .env exists
RUN test -f .env || (test -f .env.example && cp .env.example .env || touch .env)

# Create Laravel + shared report/log directories
RUN mkdir -p storage/framework/{sessions,views,cache} \
    storage/logs storage/app/public storage/app/reports \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Clear stale bootstrap cache
RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php

# Disable classmap optimization
RUN composer config optimize-autoloader false

# Generate autoloader
RUN composer dump-autoload --no-dev --no-scripts --ignore-platform-reqs

# Set Laravel paths
ENV VIEW_COMPILED_PATH=/var/www/html/storage/framework/views
ENV SESSION_FILE_PATH=/var/www/html/storage/framework/sessions

# Install Python finance engine crontab
COPY docker/crontab /var/spool/cron/crontabs/root
RUN chmod 0600 /var/spool/cron/crontabs/root \
    && mkdir -p /var/log/pyengine

# Create log directories
RUN mkdir -p /var/log/supervisor /var/run/php

# Expose HTTP for ELB health checks and dashboard
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD wget -qO- http://localhost/health || exit 1

CMD ["/entrypoint.sh"]
