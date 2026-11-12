#!/usr/bin/env bash
# C11: home page with an EMPTY site (C11-001) and with PARTIAL data (C11-002): sections appear only when they have
# public content, and nothing renders empty. Runs inside the wp-env site (or any disposable site) through WE_RUN.
# Requires a pristine site: run it BEFORE seeding fixtures.
set -u
REPO="$(cd "$(dirname "$0")/.." && pwd)"
RUN="${WE_RUN:-$REPO/tests-e2e/node_modules/.bin/wp-env run cli}"
BASE="${BASE_URL:-http://localhost:8888}"
PROBES="wp-content/noblen-e2e/probes"
pass=0; fail=0
ok()  { pass=$((pass+1)); echo "ok   $1"; }
bad() { fail=$((fail+1)); echo "FAIL $1 :: ${2:-}"; }
# shellcheck disable=SC2086
W() { $RUN wp "$@" 2>/dev/null | tr -d '\r' | sed '/^$/d'; }
# shellcheck disable=SC2086
P() { $RUN wp eval-file "$PROBES/$1" 2>/dev/null | tr -d '\r' | sed '/^$/d' | tail -1; }
html() { curl -s "$BASE/"; }
has() { html | grep -q "id=\"$1-h\"" && echo yes || echo no; }
expect() { # expect <label> <companies> <industries> <solutions> <projects> <products>
  local label="$1"; shift; local names=(companies industries solutions projects products) i=0 got want all=1
  i=1
  for n in "${names[@]}"; do
    got=$(has "$n"); want="${!i}"; i=$((i+1))
    if [ "$got" != "$want" ]; then all=0; bad "$label: section '$n'" "expected $want, got $got"; fi
  done
  [ "$all" -eq 1 ] && ok "$label: sections present exactly as expected (companies=$1 industries=$2 solutions=$3 projects=$4 products=$5)"
  local empties; empties=$(html | grep -c 'class="grid"></div>' || true)
  [ "$empties" = "0" ] && ok "$label: no empty section containers" || bad "$label: empty section containers" "$empties"
  [ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/")" = "200" ] && ok "$label: home page returns 200" || bad "$label: home page status"
}
step() { W option update noblen_probe_step "$1" >/dev/null; P partial-data.php; }

P reset-log.php >/dev/null
existing=$(P count-records.php | cut -d' ' -f1)
if [ "$existing" != "0" ]; then echo "FAIL precondition: site must have no Noblen records (found $existing). Run before seeding."; exit 2; fi

expect "C11-001 empty site"                 no no no no no
step companies  >/dev/null; expect "C11-002 companies only"             yes no no no no
step industries >/dev/null; expect "C11-002 companies and industries"   yes yes no no no
step projects   >/dev/null; expect "C11-002 companies, industries, projects" yes yes no yes no
[ "$(P log-errors.php)" = "0" ] && ok "no PHP errors recorded across all states" || bad "PHP errors recorded" "$(P log-errors.php)"
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
