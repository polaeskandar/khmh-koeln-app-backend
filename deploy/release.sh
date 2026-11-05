#!/usr/bin/env bash
set -euo pipefail

command="${1:?Usage: release.sh current | pull <sha> | migrate <sha> | rollout <sha>}"
directory="${DEPLOY_DIR:-/srv/khmk}"
repository="ghcr.io/polaeskandar/khmh-koeln-app-backend"
environment="$directory/.env"

image_of() {
  local sha="${1:?Give the image SHA.}"
  [[ "$sha" =~ ^[0-9a-f]{40}$ ]] || { printf 'Not an image SHA: %s\n' "$sha" >&2; exit 2; }
  printf '%s:%s' "$repository" "$sha"
}

compose() {
  docker compose --project-directory "$directory" "$@"
}

case "$command" in
  current)
    container="$(compose ps --quiet --status running api | head -n 1)"
    [[ -n "$container" ]] || exit 0
    docker inspect --format '{{.Config.Image}}' "$container" | sed -n "s|^$repository:||p"
    ;;
  pull)
    docker pull --quiet "$(image_of "${2:-}")" > /dev/null
    ;;
  migrate)
    API_IMAGE="$(image_of "${2:-}")" compose run --rm --no-deps api php bin/console app:migrations:migrate --no-interaction
    ;;
  rollout)
    image="$(image_of "${2:-}")"
    sed -i "s|^API_IMAGE=.*|API_IMAGE=$image|" "$environment"
    compose up --detach --wait --remove-orphans
    ;;
  *)
    printf 'Unknown command: %s\n' "$command" >&2
    exit 2
    ;;
esac
