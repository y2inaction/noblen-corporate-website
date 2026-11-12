#!/usr/bin/env bash
# WE: WP-CLI and HTTP integration tests that run INSIDE the wp-env site (plan environment "WE").
# The site must already be running with the Noblen plugin and theme active.
# Env: WE_RUN (default: wp-env run cli), BASE_URL (default http://localhost:8888).
# Arguments contain no spaces on purpose, and PHP logic lives in mapped probe files, so nothing depends on shell quoting.
set -u
REPO="$(cd "$(dirname "$0")/.." && pwd)"
RUN="${WE_RUN:-$REPO/tests-e2e/node_modules/.bin/wp-env run cli}"
BASE="${BASE_URL:-http://localhost:8888}"
PROBES="wp-content/noblen-e2e/probes"
pass=0; fail=0
ok()  { pass=$((pass+1)); echo "ok   $1"; }
bad() { fail=$((fail+1)); echo "FAIL $1 :: ${2:-}"; }
eq()  { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1" "expected [$3] got [$2]"; fi; }
# shellcheck disable=SC2086
W() { $RUN wp "$@" 2>/dev/null | tr -d '\r' | sed '/^$/d'; }
# shellcheck disable=SC2086
P() { $RUN wp eval-file "$PROBES/$1" 2>/dev/null | tr -d '\r' | sed '/^$/d' | tail -1; }
code() { curl -s -o /dev/null -w '%{http_code}' "$BASE$1"; }
errors() { P log-errors.php; }
# shellcheck disable=SC2086
PX() { $RUN wp eval-file "$PROBES/$1" 2>/dev/null | tr -d '\r' | sed '/^$/d'; }
# C26 logical dataset: all categories hashed; the audit hash covers only entries that existed at the snapshot.
snapshot() { W option update noblen_probe_audit_upto 0 >/dev/null; local max; max=$(PX logical-export.php | awk '/^audit_max/{print $2}'); W option update noblen_probe_audit_upto "$max" >/dev/null; SNAP_MAX=$max; PX logical-export.php | grep -v '^audit_max'; }
compare_now() { PX logical-export.php | grep -v '^audit_max'; }

# Canary: prove the PHP error log is wired, otherwise "no errors" would be vacuous.
P reset-log.php >/dev/null; P canary.php >/dev/null
eq "error log is wired (canary recorded)" "$(errors)" "1"
P reset-log.php >/dev/null

# C01 / C02: plugin and theme active in a clean wp-env site; schema complete.
eq "C02-001 plugin is active" "$(W plugin get noblen-corporate-core --field=status)" "active"
eq "C01-001 theme is active" "$(W theme get noblen-corporate --field=status)" "active"
eq "C02-003 five private tables exist" "$(P count-records.php | cut -d' ' -f2)" "5"
eq "C01-003 front end loads" "$(code /)" "200"
eq "C01-003 admin login page loads" "$(code /wp-login.php)" "200"

# C24 / C10: WP-CLI write paths with incomplete clearance stay unpublished.
ID=$(W post create --post_type=noblen_project --post_title=WEProject --post_status=publish --porcelain | tail -1)
eq "C24-007 wp post create with publish status is stored as draft" "$(W post get "$ID" --field=post_status)" "draft"
W post update "$ID" --post_status=publish >/dev/null
eq "C24-007 wp post update to publish stays unpublished" "$(W post get "$ID" --field=post_status)" "draft"
W option update noblen_probe_id "$ID" >/dev/null
eq "C24-008 wp_publish_post() from WP-CLI code stays unpublished" "$(P publish-probe.php)" "draft"
CID=$(W post create --post_type=noblen_company --post_title=WECompany --post_content=x --post_status=publish --porcelain | tail -1)
eq "C24 company cannot be published through WP-CLI" "$(W post get "$CID" --field=post_status)" "draft"
eq "C24 anonymous request for the draft is not found" "$(code "/?post_type=noblen_project&p=$ID")" "404"

# C26 sensitivity: the logical export must DETECT a change, otherwise the comparisons below would be vacuous.
SNAP0=$(snapshot)
W post meta update "$ID" _noblen_f_scope SENSITIVITYPROBE >/dev/null
SENS=$(compare_now)
W post meta delete "$ID" _noblen_f_scope >/dev/null
if [ "$SENS" != "$SNAP0" ]; then ok "C26 logical export detects a metadata change"; else bad "C26 logical export detects a metadata change" "hashes did not change"; fi
eq "C26 logical export returns to the original after reverting" "$(compare_now)" "$SNAP0"

# C03-003 / C26-002: plugin inactive gives a working front end and no PHP errors; data survives.
BEFORE=$(P count-records.php)
LOGICAL=$(snapshot)
P reset-log.php >/dev/null
W plugin deactivate noblen-corporate-core >/dev/null
eq "C03-003 front end renders with the plugin inactive" "$(code /)" "200"
eq "C03-003 no PHP errors with the plugin inactive" "$(errors)" "0"
eq "C26-002 records and private tables retained after deactivation" "$(P count-records.php)" "$BEFORE"
W plugin activate noblen-corporate-core >/dev/null
eq "C26-002 records and tables identical after reactivation" "$(P count-records.php)" "$BEFORE"
eq "C26-002 logical dataset (content hashes, metadata, relationships, clearance, approvals, existing audit entries) identical after deactivate and reactivate" "$(compare_now)" "$LOGICAL"
eq "C26-002 draft still not public after reactivation" "$(code "/?post_type=noblen_project&p=$ID")" "404"

# C04: theme switch keeps content and the site keeps working.
W theme activate twentytwentythree >/dev/null
eq "C04-001 records identical after switching to a default theme" "$(P count-records.php)" "$BEFORE"
eq "C26-001 logical dataset identical after switching to a default theme" "$(compare_now)" "$LOGICAL"
eq "C04-002 site still renders under the default theme" "$(code /)" "200"
W theme activate noblen-corporate >/dev/null
eq "C04-001 records identical after switching back" "$(P count-records.php)" "$BEFORE"
eq "C26-001 logical dataset identical after switching back to the Noblen theme" "$(compare_now)" "$LOGICAL"

eq "no PHP errors recorded during the whole run" "$(errors)" "0"
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
