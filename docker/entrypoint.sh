#!/bin/sh
set -eu

for variable in APP_SECRET DATABASE_URL; do
    eval "value=\${$variable:-}"
    if [ -z "$value" ]; then
        echo "Missing required environment variable $variable." >&2
        exit 1
    fi
done

exec "$@"
