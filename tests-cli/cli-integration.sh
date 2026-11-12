#!/usr/bin/env bash
# Genuine WP-CLI integration tests (not simulated). Runs against a FRESH, disposable site.
# Env: WP (wp binary), WP_PATH (WordPress dir), NOBLEN_DB_NAME must point at an empty disposable database.
set -u
WP="${WP:-wp}"; WP_PATH="${WP_PATH:?set WP_PATH}"
W() { "$WP" --allow-root --path="$WP_PATH" "$@" 2>/tmp/cli-stderr.txt; }
pass=0; fail=0
ok()   { pass=$((pass+1)); echo "ok   $1"; }
bad()  { fail=$((fail+1)); echo "FAIL $1 :: ${2:-}"; }
eq()   { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1" "expected [$3] got [$2]"; fi; }
nz()   { if [ -n "$2" ]; then ok "$1"; else bad "$1" "empty"; fi; }
REPO="$(cd "$(dirname "$0")/.." && pwd)"
# `wp plugin uninstall` DELETES the plugin directory, so it must be a disposable copy, never a symlink into the repository.
PLUG="$WP_PATH/wp-content/plugins/noblen-corporate-core"
[ -L "$PLUG" ] && rm "$PLUG"
install_plugin() { rm -rf "$PLUG"; mkdir -p "$PLUG"; cp -a "$REPO/wordpress-plugin/noblen-corporate-core/." "$PLUG/"; rm -rf "$PLUG/tests"; }
install_plugin
[ -L "$PLUG" ] && { echo "refusing to run: plugin dir is a symlink"; exit 2; }
LOG="${WP_DEBUG_LOG_FILE:-/tmp/noblen-cli-debug.log}"; : > "$LOG"

# ---- C01/C02: clean install, theme and plugin activation via WP-CLI.
W core install --url=http://127.0.0.1:8099 --title=CLI --admin_user=admin --admin_password=cli-pass-only --admin_email=cli@example.test --skip-email >/dev/null
# Canary: prove the PHP error log is wired up, otherwise "no errors" below would be vacuous.
W eval 'trigger_error("noblen-canary", E_USER_WARNING);' >/dev/null
eq "error log is wired (canary recorded)" "$(grep -c noblen-canary "$LOG" || true)" "1"
: > "$LOG"
W plugin activate noblen-corporate-core >/dev/null; eq "C02-001 plugin activates via WP-CLI" "$(W plugin get noblen-corporate-core --field=status)" "active"
W theme activate noblen-corporate >/dev/null;       eq "C01-001 theme activates via WP-CLI" "$(W theme get noblen-corporate --field=status)" "active"
eq "C02-003 schema version recorded" "$(W eval 'echo Noblen\Core\Database\Migrator::installed_version();')" "$(W eval 'echo Noblen\Core\Database\Schema::VERSION;')"
eq "C02-003 all five private tables exist" "$(W eval 'echo count(array_filter(array("clearance","media_rights","approvals","epochs","audit"), fn($t)=>Noblen\Core\Database\Schema::exists($t)));')" "5"
eq "C02-002 / C01-002 PHP error log empty after activation" "$(grep -c 'PHP \(Fatal\|Warning\|Notice\|Deprecated\)' "$LOG" || true)" "0"

# ---- C10 / C24 WP-CLI write paths with incomplete clearance: must remain unpublished.
ID=$(W post create --post_type=noblen_project --post_title="CLI Project" --post_status=publish --porcelain)
eq "C24-007 wp post create --post_status=publish is stored as draft" "$(W post get "$ID" --field=post_status)" "draft"
W post update "$ID" --post_status=publish >/dev/null
eq "C24-007 wp post update --post_status=publish stays unpublished" "$(W post get "$ID" --field=post_status)" "draft"
W eval "wp_publish_post($ID);" >/dev/null
eq "C24-008 wp_publish_post() via wp eval stays unpublished" "$(W post get "$ID" --field=post_status)" "draft"
FID=$(W post create --post_type=noblen_project --post_title="CLI Future" --post_status=future --post_date="2999-01-01 00:00:00" --porcelain)
eq "C24-004 scheduling via WP-CLI is refused" "$(W post get "$FID" --field=post_status)" "draft"
CID=$(W post create --post_type=noblen_company --post_title="CLI Company" --post_content=x --post_status=publish --porcelain)
eq "C24 company cannot be published through WP-CLI either" "$(W post get "$CID" --field=post_status)" "draft"
eq "C24-009 import-style creation (wp post generate/import path) stays draft" "$(W eval '$i=wp_insert_post(array("post_type"=>"noblen_project","post_title"=>"Imp","post_status"=>"publish","import_id"=>0)); echo get_post_status($i);')" "draft"

# ---- C03-003 / C26-002: deactivate plugin, theme still renders; data preserved; reactivate.
BEFORE=$(W post list --post_type=noblen_project,noblen_company --post_status=any --format=count)
W plugin deactivate noblen-corporate-core >/dev/null
W eval '$_SERVER["SERVER_NAME"]="localhost"; ob_start(); include get_template_directory()."/front-page.php"; $h=ob_get_clean(); echo strlen($h)>0?"rendered":"empty";' > /tmp/render.txt 2>>"$LOG"
eq "C03-003 theme front page renders with plugin inactive" "$(cat /tmp/render.txt)" "rendered"
eq "C26-002 database tables retained after deactivation" "$(mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${NOBLEN_DB_NAME:-noblen_cli}' AND table_name LIKE '%noblen\_%'")" "5"
W plugin activate noblen-corporate-core >/dev/null
eq "C26-002 records still present after reactivation" "$(W post list --post_type=noblen_project,noblen_company --post_status=any --format=count)" "$BEFORE"

# ---- C04 theme switch preserves content.
W theme activate twentytwentythree >/dev/null
eq "C04-001 records retained after switching theme" "$(W post list --post_type=noblen_project,noblen_company --post_status=any --format=count)" "$BEFORE"
W theme activate noblen-corporate >/dev/null

# ---- C26 uninstall retention: plugin uninstall keeps data by default.
W plugin deactivate noblen-corporate-core >/dev/null
W plugin uninstall noblen-corporate-core --deactivate >/dev/null 2>&1 || true
install_plugin  # wp plugin uninstall removes the plugin files
eq "C26 uninstall keeps private tables by default" "$(mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${NOBLEN_DB_NAME:-noblen_cli}' AND table_name LIKE '%noblen\_%'")" "5"
W plugin activate noblen-corporate-core >/dev/null
eq "C26 records survive uninstall" "$(W post list --post_type=noblen_project,noblen_company --post_status=any --format=count)" "$BEFORE"

# ---- C26/C25 opt-in removal actually removes (only when explicitly opted in).
W option update noblen_core_remove_data_on_uninstall 1 >/dev/null
W plugin deactivate noblen-corporate-core >/dev/null
W plugin uninstall noblen-corporate-core >/dev/null 2>&1 || true
eq "C26 opt-in uninstall removes private tables" "$(mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${NOBLEN_DB_NAME:-noblen_cli}' AND table_name LIKE '%noblen\_%'")" "0"

eq "no PHP errors logged during the whole run" "$(grep -c 'PHP \(Fatal\|Warning\|Notice\|Deprecated\)' "$LOG" || true)" "0"
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
