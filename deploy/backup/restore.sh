#!/usr/bin/env bash
set -euo pipefail

: "${BACKUP_REMOTE:?Set BACKUP_REMOTE, an rclone remote and path such as backups:khmk}"
: "${RESTORE_DATABASE_URL:?Set RESTORE_DATABASE_URL to an empty scratch database}"

object="${1:-$(rclone lsf --files-only "$BACKUP_REMOTE" | grep -E '^khmk-.*\.dump\.age$' | sort | tail -n 1)}"

if [ -z "$object" ]; then
  echo "No backup found in $BACKUP_REMOTE." >&2
  exit 1
fi

key="$(cat)"

if [ -z "$key" ]; then
  echo 'Pass the age private key on stdin.' >&2
  exit 1
fi

url="$(sed -E 's/(serverVersion|charset)=[^&]*//g; s/&+/\&/g; s/\?&/?/; s/[?&]$//' <<< "$RESTORE_DATABASE_URL")"

rclone cat "$BACKUP_REMOTE/$object" \
  | age --decrypt --identity <(printf '%s\n' "$key") \
  | pg_restore --no-owner --exit-on-error --dbname="$url"

echo "Restored $object. Rows per table:"
psql "$url" --no-psqlrc --tuples-only --no-align --command "
  SELECT format('%I.%I', schemaname, relname) || ' ' ||
         (xpath('/row/c/text()', query_to_xml(format('SELECT count(*) AS c FROM %I.%I', schemaname, relname), false, true, '')))[1]::text
  FROM pg_stat_user_tables ORDER BY 1"
