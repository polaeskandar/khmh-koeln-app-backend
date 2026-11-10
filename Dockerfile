FROM dunglas/frankenphp:1-php8.5@sha256:6f86f003f1478bafa7befe6ec26db5575e703882171f785189de1e222df0e38e AS base

RUN install-php-extensions pdo_pgsql intl opcache

WORKDIR /app

FROM composer:2@sha256:8de2184c6de224f4689e8f80ed5f5edaf0030b6b8b7839a22a2bcb6722ac87d0 AS composer

FROM base AS dev

RUN install-php-extensions zip \
    && apt-get update && apt-get install -y --no-install-recommends git && rm -rf /var/lib/apt/lists/* \
    && git config --system --add safe.directory /app

COPY --from=composer /usr/bin/composer /usr/bin/composer
