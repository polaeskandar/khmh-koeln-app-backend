FROM dunglas/frankenphp:1-php8.5@sha256:6f86f003f1478bafa7befe6ec26db5575e703882171f785189de1e222df0e38e AS base

RUN install-php-extensions pdo_pgsql intl opcache

WORKDIR /app

FROM base AS build

RUN install-php-extensions zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APP_ENV=prod APP_DEBUG=0 COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress --prefer-dist

COPY bin bin
COPY config config
COPY public public
COPY src src
COPY .env ./
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && composer dump-env prod \
    && APP_SECRET=build-only DATABASE_URL='postgresql://build:build@127.0.0.1:5432/build?serverVersion=18&charset=utf8' \
       php bin/console cache:warmup \
    && rm .env

FROM base AS prod

ENV APP_ENV=prod APP_DEBUG=0 SERVER_NAME=:8080

COPY docker/php/prod.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/app-entrypoint

RUN useradd --uid 10001 --user-group --no-create-home app \
    && mkdir -p /data/caddy /config/caddy \
    && chown -R app:app /data /config

COPY --from=build --chown=app:app /app /app

USER app

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/api/health || exit 1

ENTRYPOINT ["app-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

FROM base AS dev

RUN install-php-extensions zip \
    && apt-get update && apt-get install -y --no-install-recommends git && rm -rf /var/lib/apt/lists/* \
    && git config --system --add safe.directory /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
