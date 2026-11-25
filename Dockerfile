# syntax=docker/dockerfile:1

# Feedback Pilgrim production image.
# Base: serversideup/php (PHP-FPM + NGINX + s6-overlay, runs as www-data,
# listens on 8080). Docs: https://serversideup.net/open-source/docker-php/
#
# One container runs everything: NGINX, PHP-FPM, the queue worker, the
# scheduler and, when APP_DOMAIN is set, Caddy with automatic HTTPS.
# With no variables, the app uses SQLite in /data and http://localhost:8080.
#
#   docker run -d -p 8080:8080 -v feedbackpilgrim:/data ghcr.io/amedina068/feedbackpilgrim

ARG PHP_IMAGE=serversideup/php:8.3-fpm-nginx-v5.1.2
ARG NODE_IMAGE=node:24-alpine
ARG CADDY_IMAGE=caddy:2.10.2-alpine

FROM ${CADDY_IMAGE} AS caddy

# ------------------------------------------------------------------------------
# Base: PHP with the extensions that the app needs.
# The image already has pdo_mysql, pdo_sqlite, redis, pcntl, opcache and zip.
# Laravel loads a schema dump with the sqlite3 or the mysql client.
# deploy/docker/mysql-client.cnf lets the mysql client (MariaDB) load
# database/schema/mysql-schema.sql into the MySQL 8.4 of docker-compose.yml,
# which has a self-signed TLS certificate. deploy/README.md tells why.
# ------------------------------------------------------------------------------
FROM ${PHP_IMAGE} AS base

USER root
RUN install-php-extensions gd exif \
    && apt-get update \
    && apt-get install -y --no-install-recommends sqlite3 mariadb-client libcap2-bin \
    && rm -rf /var/lib/apt/lists/*
COPY deploy/docker/mysql-client.cnf /etc/mysql/conf.d/feedbackpilgrim-client.cnf
USER www-data

WORKDIR /var/www/html

# ------------------------------------------------------------------------------
# Composer dependencies (no dev packages).
# ------------------------------------------------------------------------------
FROM base AS vendor

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --no-autoloader

# ------------------------------------------------------------------------------
# Front-end build: public/build (dashboard) and public/pilgrim.js (widget).
# ------------------------------------------------------------------------------
FROM ${NODE_IMAGE} AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js vite.config.external-script.js ./
COPY resources ./resources
COPY public ./public
# Tailwind scans the pagination views of the framework.
COPY --from=vendor /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
    ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views

RUN npm run build && test -f public/pilgrim.js && test -f public/build/manifest.json

# ------------------------------------------------------------------------------
# Application image.
# ------------------------------------------------------------------------------
FROM base AS app

USER root

# Caddy binds ports 80 and 443 as www-data.
COPY --from=caddy /usr/bin/caddy /usr/local/bin/caddy
RUN setcap cap_net_bind_service=+ep /usr/local/bin/caddy

# 20-feedbackpilgrim-data.sh prepares /data before the Laravel automations.
COPY --chmod=755 deploy/docker/entrypoint.d/ /etc/entrypoint.d/

# s6 services: the queue worker, the scheduler, Caddy and the "ready" message.
COPY --chmod=755 deploy/docker/s6-rc.d/ /etc/s6-overlay/s6-rc.d/
COPY deploy/docker/user-bundle/ /etc/s6-overlay/user-bundles.d/user/contents.d/

# The folders exist in the image, so a new Docker volume on /data or on
# /data/storage/app gets the owner www-data.
RUN mkdir -p /data/storage/app/public /data/caddy \
    && chown -R www-data:www-data /data

# Laravel loads database/schema/sqlite-schema.sql with the sqlite3 client.
# The database uses WAL. With synchronous=NORMAL, the client does not wait
# for a disk sync after each statement: the load takes 0.2 s, not 7 s.
RUN echo "PRAGMA synchronous=NORMAL;" > /var/www/.sqliterc \
    && chown www-data:www-data /var/www/.sqliterc

USER www-data

# The defaults of a new install. Each one is a normal variable, so
# docker run -e or a compose file can change it.
ENV PHP_OPCACHE_ENABLE=1 \
    HEALTHCHECK_PATH=/up \
    LOG_STACK=stderr \
    SHOW_WELCOME_MESSAGE=false \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION_TIMEOUT=120 \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    CACHE_DRIVER=database \
    SESSION_DRIVER=database \
    QUEUE_CONNECTION=database \
    APP_KEY_FILE=/data/.secrets/app_key

COPY --chown=www-data:www-data --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
COPY --chown=www-data:www-data --from=assets /app/public/pilgrim.js ./public/pilgrim.js

# The uploads live in the /data volume.
RUN rm -rf storage/app \
    && ln -s /data/storage/app storage/app \
    && composer dump-autoload --no-dev --optimize --no-interaction

VOLUME /data

EXPOSE 8080 80 443

# A first install on MySQL runs all the migrations, so the start period is long.
HEALTHCHECK --interval=10s --timeout=3s --start-period=300s --start-interval=2s --retries=3 \
    CMD curl --silent --fail --output /dev/null http://127.0.0.1:8080/up || exit 1
