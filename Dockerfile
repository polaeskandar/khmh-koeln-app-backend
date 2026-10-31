FROM dunglas/frankenphp:1-php8.5

RUN install-php-extensions pdo_pgsql intl opcache zip \
    && apt-get update && apt-get install -y --no-install-recommends git && rm -rf /var/lib/apt/lists/* \
    && git config --system --add safe.directory /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
