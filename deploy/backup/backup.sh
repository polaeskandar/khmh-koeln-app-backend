#!/usr/bin/env bash
set -euo pipefail

: "${DATABASE_URL:?Set DATABASE_URL}"
: "${BACKUP_REMOTE:?Set BACKUP_REMOTE, an rclone remote and path such as backups:khmk}"

if [ -z "${BACKUP_RECIPIENTS:-}" ]; then
  echo 'Refusing to back up: BACKUP_RECIPIENTS holds no age public key.' >&2
  exit 1
fi

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

printf '%s\n' "$BACKUP_RECIPIENTS" > "$work/recipients"
url="$(sed -E 's/(serverVersion|charset)=[^&]*//g; s/&+/\&/g; s/\?&/?/; s/[?&]$//' <<< "$DATABASE_URL")"
name="khmk-$(date -u +%Y%m%dT%H%M%SZ).dump.age"

if ! pg_dump --format=custom --no-owner --dbname="$url" | age --encrypt --recipients-file "$work/recipients" > "$work/$name"; then
  echo 'The dump failed; nothing was uploaded.' >&2
  exit 1
fi

rclone copyto "$work/$name" "$BACKUP_REMOTE/$name"
echo "Uploaded $name."

if [ -n "${BACKUP_HEARTBEAT_URL:-}" ]; then
  curl -fsS --max-time 10 --retry 3 "$BACKUP_HEARTBEAT_URL" > /dev/null
fi
