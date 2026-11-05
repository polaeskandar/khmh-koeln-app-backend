#!/usr/bin/env bash
set -euo pipefail

: "${BASE_URL:?Set BASE_URL, for example https://app.khmk-koeln.com}"
: "${SMOKE_EMAIL:?Set SMOKE_EMAIL}"
: "${SMOKE_PASSWORD:?Set SMOKE_PASSWORD}"
SMOKE_WEB="${SMOKE_WEB:-1}"
BASE_URL="${BASE_URL%/}"

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
jar="$work/cookies"
headers="$work/headers"
body="$work/body"
passed=0

pass() {
  passed=$((passed + 1))
  printf 'ok   %s\n' "$1"
}

fail() {
  printf 'FAIL %s: %s\n' "$1" "$2" >&2
  printf '%d check(s) passed before the failure.\n' "$passed" >&2
  exit 1
}

request() {
  local method="$1" path="$2"
  shift 2
  : > "$headers"
  curl -sS -o "$body" -D "$headers" -w '%{http_code}' -X "$method" "$@" "$BASE_URL$path" || true
}

header() {
  grep -i "^$1:" "$headers" | head -n 1 | cut -d: -f2- | tr -d '\r' | sed 's/^ *//'
}

expect_status() {
  local check="$1" want="$2" got="$3"
  [ "$got" = "$want" ] || fail "$check" "status $got, expected $want"
}

check_api() {
  expect_status 'health' 200 "$(request GET /api/health/ready)"
  pass 'health'

  expect_status 'unknown API route' 404 "$(request GET /api/smoke-test-unknown)"
  header 'content-type' | grep -q 'application/problem+json' || fail 'unknown API route' 'not a problem document'
  [ "$(jq -r .code "$body")" = 'notFound.route' ] || fail 'unknown API route' 'code is not notFound.route'
  pass 'unknown API route'

  if [[ "$BASE_URL" == https://* ]]; then
    local plain="http://${BASE_URL#https://}"
    local status
    status="$(curl -sS -o /dev/null -D "$headers" -w '%{http_code}' "$plain/api/health" || true)"
    [[ "$status" =~ ^30[178]$ ]] || fail 'https redirect' "status $status"
    header 'location' | grep -q '^https://' || fail 'https redirect' 'Location is not https'
    pass 'https redirect'
  fi
}

check_web_page() {
  local path="$1"
  expect_status "web $path" 200 "$(request GET "$path")"
  header 'strict-transport-security' | grep -q 'max-age' || fail "web $path" 'no HSTS'
  [ "$(header 'x-content-type-options')" = 'nosniff' ] || fail "web $path" 'no nosniff'
  [ -n "$(header 'referrer-policy')" ] || fail "web $path" 'no Referrer-Policy'
  header 'content-security-policy' | grep -q "frame-ancestors 'none'" || fail "web $path" "CSP lacks frame-ancestors 'none'"
  pass "web $path"
}

check_web() {
  check_web_page /
  check_web_page /de/
  expect_status 'web /de/index.txt' 200 "$(request GET /de/index.txt)"
  header 'content-type' | grep -q '^text/plain' || fail 'web /de/index.txt' 'not text/plain'
  pass 'web /de/index.txt'
  expect_status 'web unknown path' 404 "$(request GET /smoke-test-unknown)"
  pass 'web unknown path'
}

check_auth() {
  local credentials
  credentials="$(jq -cn --arg email "$SMOKE_EMAIL" --arg password "$SMOKE_PASSWORD" '{email: $email, password: $password}')"

  expect_status 'foreign origin' 403 "$(request POST /api/auth/sign-in -H 'Content-Type: application/json' -H 'Origin: https://evil.example' --data-binary '{}')"
  [ "$(jq -r .code "$body")" = 'forbidden' ] || fail 'foreign origin' 'code is not forbidden'
  pass 'foreign origin'

  expect_status 'sign-in' 204 "$(request POST /api/auth/sign-in -c "$jar" -H 'Content-Type: application/json' -H "Origin: $BASE_URL" --data-binary @- <<< "$credentials")"
  local cookie
  cookie="$(header 'set-cookie')"
  for flag in HttpOnly Secure 'SameSite=Lax'; do
    grep -qi "$flag" <<< "$cookie" || fail 'sign-in' "cookie lacks $flag"
  done
  pass 'sign-in'

  expect_status 'me' 200 "$(request GET /api/me -b "$jar")"
  [ "$(jq -r '.roles | length' "$body")" = 0 ] || fail 'me' 'the smoke account has roles'
  [ "$(jq -r '.today // empty' "$body")" != '' ] || fail 'me' 'no today'
  header 'cache-control' | grep -q 'no-store' || fail 'me' 'not no-store'
  pass 'me'

  cp "$jar" "$work/old-cookies"
  expect_status 'sign-out' 204 "$(request POST /api/auth/sign-out -b "$jar" -c "$jar" -H 'Content-Type: application/json' -H "Origin: $BASE_URL" --data-binary '{}')"
  pass 'sign-out'

  expect_status 'signed out' 401 "$(request GET /api/me -b "$work/old-cookies")"
  [ "$(jq -r .code "$body")" = 'auth.unauthenticated' ] || fail 'signed out' 'code is not auth.unauthenticated'
  pass 'signed out'
}

check_api
if [ "$SMOKE_WEB" != 0 ]; then
  check_web
fi
check_auth

printf 'All %d checks passed against %s.\n' "$passed" "$BASE_URL"
