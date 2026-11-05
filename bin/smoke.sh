#!/usr/bin/env bash
# Real-HTTP smoke test: login as each role, then GET every resolvable GET route.
set -u
BASE="${BASE:-http://127.0.0.1:8123}"
DIR="$(cd "$(dirname "$0")" && pwd)"
JAR="/tmp/opencode/vowspace-cookies-$$.txt"
OUT="/tmp/opencode/vowspace-smoke-$$.txt"
trap 'rm -f "$JAR" "$OUT"' EXIT

login() {
  local email="$1"
  rm -f "$JAR"
  local token
  token=$(curl -s -c "$JAR" -b "$JAR" "$BASE/login" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  if [ -z "$token" ]; then echo "LOGIN-PAGE-FAILED $email"; return 1; fi
  local code
  code=$(curl -s -o /dev/null -w "%{http_code}" -c "$JAR" -b "$JAR" -X POST "$BASE/login" \
    --data-urlencode "_token=$token" \
    --data-urlencode "email=$email" \
    --data-urlencode "password=password")
  if [ "$code" != "302" ]; then echo "LOGIN-FAILED $email (HTTP $code)"; return 1; fi
  return 0
}

check() {
  local label="$1" url="$2"
  local code loc
  read -r code loc < <(curl -s -o /dev/null -w "%{http_code} %{redirect_url}" -c "$JAR" -b "$JAR" "$BASE$url")
  if [ "$code" -ge 400 ] 2>/dev/null; then
    echo "FAIL  $code  $label  $url  -> $loc"
  elif [ "$code" -ge 300 ] 2>/dev/null && [[ "$loc" == *"/login"* ]]; then
    echo "AUTH-REDIRECT  $code  $label  $url  -> $loc"
  fi
}

echo "== Admin =="
login "admin@vowspace.test" || exit 1
php "$DIR/smoke_urls.php" | grep -E '^(admin\.|home\|)' | while IFS='|' read -r name url; do check "$name" "$url"; done

echo "== Owner =="
login "raka@vowspace.test" || exit 1
php "$DIR/smoke_urls.php" | grep -E '^owner\.' | while IFS='|' read -r name url; do check "$name" "$url"; done

echo "== Guest (public token routes) =="
rm -f "$JAR"
php "$DIR/smoke_urls.php" | grep -E '^(guest\.|live-wall\.)' | while IFS='|' read -r name url; do check "$name" "$url"; done

echo "== Guest (invalid token expected 404) =="
code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/invite/invalid-token")
echo "invalid-token -> $code"

echo "== done =="
