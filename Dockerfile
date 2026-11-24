# syntax=docker/dockerfile:1

# Feedback Pilgrim production image.
# Base: serversideup/php (PHP-FPM + NGINX, runs as www-data, listens on 8080).
# Docs: https://serversideup.net/open-source/docker-php/

ARG PHP_IMAGE=serversideup/php:8.3-fpm-nginx-v5.1.2
ARG NODE_IMAGE=node:24-alpine

# ------------------------------------------------------------------------------
# Base: PHP with the extensions that the app needs.
# The image already has pdo_mysql, redis, pcntl, opcache and zip.
# ------------------------------------------------------------------------------
FROM ${PHP_IMAGE} AS base

USER root
RUN install-php-extensions gd exif
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

ENV PHP_OPCACHE_ENABLE=1 \
    HEALTHCHECK_PATH=/up \
    LOG_STACK=stderr

# The image entrypoint runs this script at start. It acts only in the web service.
COPY --chmod=755 deploy/docker/entrypoint.d/60-feedbackpilgrim-setup.sh /etc/entrypoint.d/60-feedbackpilgrim-setup.sh

COPY --chown=www-data:www-data --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
COPY --chown=www-data:www-data --from=assets /app/public/pilgrim.js ./public/pilgrim.js

RUN composer dump-autoload --no-dev --optimize --no-interaction
