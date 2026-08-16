# Lumen API — production image for Coolify.
#
# Built on serversideup/php, which ships php-fpm + nginx already wired together
# and tuned for Laravel. Rolling our own would mean maintaining that plumbing
# for no gain.

FROM serversideup/php:8.4-fpm-nginx AS base

# pdo_pgsql for Postgres; intl so Slovak collation and dates behave.
USER root
RUN install-php-extensions pdo_pgsql pgsql intl
USER www-data

WORKDIR /var/www/html

# Dependencies first: this layer is cached unless the lock file moves, so a
# code-only deploy skips the whole composer install.
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader

COPY --chown=www-data:www-data . .

RUN composer dump-autoload --optimize --classmap-authoritative

# Config and routes are cached at boot rather than here — the cache would
# otherwise bake in build-time env vars instead of Coolify's runtime ones.

ENV PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    AUTORUN_LARAVEL_CONFIG_CACHE=true \
    AUTORUN_LARAVEL_ROUTE_CACHE=true \
    AUTORUN_LARAVEL_VIEW_CACHE=true

EXPOSE 8080
