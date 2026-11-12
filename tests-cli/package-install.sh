#!/usr/bin/env bash
# C19: build both ZIPs, install them into a CLEAN WordPress (real files, no symlinks), activate, smoke-test.
# Env: WP (wp binary), WP_SRC (a WordPress core directory to copy), NOBLEN_DB_NAME (empty disposable database).
set -u
WP="${WP:-wp}"; WP_SRC="${WP_SRC:?set WP_SRC}"
REPO="$(cd "$(dirname "$0")/.." && pwd)"; WORK="$(mktemp -d)"; SITE="$WORK/site"; DIST="$WORK/dist"
pass=0; fail=0
ok()  { pass=$((pass+1)); echo "ok   $1"; }
bad() { fail=$((fail+1)); echo "FAIL $1 :: ${2:-}"; }
eq()  { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1" "expected [$3] got [$2]"; fi; }
W()   { "$WP" --allow-root --path="$SITE" "$@" 2>/dev/null; }

"$REPO/scripts/build-packages.sh" "$DIST" >/dev/null && ok "C19-001 both ZIPs build" || bad "C19-001 build"
( cd "$DIST" && sha256sum -c SHA256SUMS >/dev/null 2>&1 ) && ok "C19-001 checksums verify" || bad "checksums"
cp -a "$WP_SRC" "$SITE"; rm -rf "$SITE/wp-content/plugins/noblen-corporate-core" "$SITE/wp-content/themes/noblen-corporate"
LOG="$WORK/debug.log"; : > "$LOG"; export WP_DEBUG_LOG_FILE="$LOG"
# Diagnostic only: when a PHP error-log check is about to run, show what is in the log (never changes a result).
php_errors() { grep 'PHP \(Fatal\|Warning\|Notice\|Deprecated\|Parse\)' "$LOG" 2>/dev/null | cut -c1-300 | sed 's/^/  | /' | head -40 || true; }
unzip -q "$DIST/noblen-corporate-core.zip" -d "$SITE/wp-content/plugins" && unzip -q "$DIST/noblen-corporate-theme.zip" -d "$SITE/wp-content/themes"
eq "ZIP excludes tests and dev files" "$(find "$SITE/wp-content/plugins/noblen-corporate-core" "$SITE/wp-content/themes/noblen-corporate" \( -name tests -o -name 'phpunit*' -o -name 'composer.*' -o -name node_modules -o -name '*.sh' \) | wc -l)" "0"
eq "packages contain no fixtures, test credentials or local paths" "$(grep -rIlE 'Dev Fixture|DEV FIXTURE|fx_(editor|officer|approver)|dev-only|/home/[a-z]+|/root/' "$SITE/wp-content/plugins/noblen-corporate-core" "$SITE/wp-content/themes/noblen-corporate" | wc -l)" "0"
eq "plugin file is a real file, not a symlink" "$([ -L "$SITE/wp-content/plugins/noblen-corporate-core" ] && echo link || echo real)" "real"
W core install --url=http://127.0.0.1:8099 --title=Pkg --admin_user=admin --admin_password=pkg-pass-only --admin_email=pkg@example.test --skip-email >/dev/null
# Canary: prove the PHP error log is wired up, otherwise "no errors" below would be vacuous.
W eval 'trigger_error("noblen-canary", E_USER_WARNING);' >/dev/null
eq "error log is wired (canary recorded)" "$(grep -c noblen-canary "$LOG" || true)" "1"
: > "$LOG"
W plugin activate noblen-corporate-core >/dev/null; eq "C19-002 plugin activates from ZIP" "$(W plugin get noblen-corporate-core --field=status)" "active"
W theme activate noblen-corporate >/dev/null;       eq "C19-002 theme activates from ZIP" "$(W theme get noblen-corporate --field=status)" "active"
eq "C19-003 schema at current version" "$(W eval 'echo Noblen\Core\Database\Migrator::installed_version();')" "$(W eval 'echo Noblen\Core\Database\Schema::VERSION;')"
eq "C19-003 front page renders from ZIP install" "$(W eval '$_SERVER["SERVER_NAME"]="localhost"; ob_start(); include get_template_directory()."/front-page.php"; echo strlen(ob_get_clean())>200?"rendered":"empty";')" "rendered"
eq "C19-003 empty site shows no section headings" "$(W eval '$_SERVER["SERVER_NAME"]="localhost"; ob_start(); include get_template_directory()."/front-page.php"; $h=ob_get_clean(); echo preg_match("/Business units|Industries|Featured projects|Products and platforms/",$h)?"sections":"none";')" "none"
php_errors
eq "C19-003 no PHP errors logged" "$(grep -c 'PHP \(Fatal\|Warning\|Notice\|Deprecated\)' "$LOG" || true)" "0"
echo "$pass passed, $fail failed"; rm -rf "$WORK"
[ "$fail" -eq 0 ]
