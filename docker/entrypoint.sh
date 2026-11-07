#!/bin/sh
set -eu

for variable in APP_SECRET DATABASE_URL DEFAULT_URI MAILER_DSN TRUSTED_HOSTS; do
    eval "value=\${$variable:-}"
    if [ -z "$value" ]; then
        echo "Missing required environment variable $variable." >&2
        exit 1
    fi
done

if [ -z "${TRUSTED_PROXIES+set}" ]; then
    echo "Missing required environment variable TRUSTED_PROXIES (set it empty when no proxy forwards requests)." >&2
    exit 1
fi

exec "$@"
