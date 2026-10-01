# Pollaris - our own image, since upstream publishes no official one.
# Source: https://framagit.org/pollaris/pollaris (AGPL-3.0-or-later)

ARG PHP_VERSION=8.4

# ---------- shared base: the extensions composer.json requires ----------
FROM php:${PHP_VERSION}-fpm-bookworm AS base

# ctype and iconv are already built into the official image.
# Still needed: intl, pdo_pgsql, zip. Plus opcache for production.
RUN set -eux; \
    apt-get update; \
    # The runtime libraries stay installed: without them the compiled
    # extensions cannot load (libpq.so.5, libzip.so.4, libicu).
    apt-get install -y --no-install-recommends \
        libicu72 libpq5 libzip4 unzip; \
    # The -dev packages are only needed to compile, and are removed after.
    apt-get install -y --no-install-recommends \
        libicu-dev libpq-dev libzip-dev; \
    docker-php-ext-configure intl; \
    docker-php-ext-install -j"$(nproc)" intl pdo pdo_pgsql zip opcache; \
    apt-get purge -y --auto-remove libicu-dev libpq-dev libzip-dev; \
    # Check explicitly that the extensions load, rather than finding out late.
    php -m | grep -qx intl; \
    php -m | grep -qx pdo_pgsql; \
    php -m | grep -qx zip; \
    rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/pollaris.ini

WORKDIR /app

# ---------- build: fetch the source and install dependencies ----------
FROM base AS build

ARG POLLARIS_VERSION=1.2.3
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends curl ca-certificates; \
    curl -fsSL \
      "https://framagit.org/pollaris/pollaris/-/archive/${POLLARIS_VERSION}/pollaris-${POLLARIS_VERSION}.tar.gz" \
      -o /tmp/pollaris.tar.gz; \
    tar -xzf /tmp/pollaris.tar.gz --strip-components=1 -C /app; \
    rm /tmp/pollaris.tar.gz; \
    rm -rf /var/lib/apt/lists/*

# Dummy values for the build only. The real ones come from .env at runtime.
ENV APP_ENV=prod \
    APP_SECRET=build-only-not-a-real-secret \
    DATABASE_URL=postgresql://build:build@127.0.0.1:5432/build?serverVersion=16

# Local template overrides, before the cache warmup.
# Twig compiles templates at build time and does not reload them in
# production, so mounting them at runtime would have no effect. The
# directory is merged over templates/.
COPY custom/templates/ /app/templates/
# Our own translation domain. It does not replace the upstream files, it is
# added alongside them, so their keys stay intact.
COPY custom/translations/ /app/translations/
# Overridden configuration: who is allowed to create polls.
COPY custom/config/ /app/config/

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
RUN composer dump-autoload --optimize --classmap-authoritative \
 && php bin/console cache:clear --no-interaction \
 && php bin/console cache:warmup --no-interaction

# No Node here. The official production documentation states explicitly:
# "You don't need to install the NPM dependencies because the assets are
# already pre-built for production." The assets in public/assets ship
# pre-built with esbuild in the release archive.

# ---------- runtime ----------
FROM base AS runtime

COPY --from=build /app /app
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
 && mkdir -p /app/var/share /app/var/cache /app/var/log \
 && chown -R www-data:www-data /app/var

USER www-data

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------- web: Nginx serving the application's static assets ----------
# A separate image built from the same Dockerfile (target: web).
# Only public/ is copied, so Nginx serves the assets without shared volumes.
FROM nginx:1.27-alpine AS web

COPY --from=build /app/public /app/public
COPY nginx/pollaris.conf /etc/nginx/conf.d/default.conf
