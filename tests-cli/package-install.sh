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
# The complete bundle: both ZIPs (identical to the stand-alone ones), the guide and the build information, with verifiable checksums.
mkdir -p "$WORK/bundle" && unzip -q "$DIST/noblen-corporate-website-bundle.zip" -d "$WORK/bundle"
BD="$WORK/bundle/noblen-corporate-website"
eq "bundle holds exactly the two ZIPs, the guide, the build info and checksums" "$(cd "$BD" && ls | tr '\n' ' ')" "BUILD-INFO.txt INSTALL-GUIDE.md SHA256SUMS noblen-corporate-core.zip noblen-corporate-theme.zip "
( cd "$BD" && sha256sum -c SHA256SUMS >/dev/null 2>&1 ) && ok "bundle checksums verify" || bad "bundle checksums"
eq "bundle ZIPs are identical to the stand-alone ZIPs" "$(cmp -s "$BD/noblen-corporate-theme.zip" "$DIST/noblen-corporate-theme.zip" && cmp -s "$BD/noblen-corporate-core.zip" "$DIST/noblen-corporate-core.zip" && echo same || echo different)" "same"
eq "build info names versions and the commit" "$(grep -cE '^(Theme: .* 0\.2\.0|Plugin: .* 0\.2\.0|Built from commit: [0-9a-f]{40})' "$BD/BUILD-INFO.txt")" "3"
eq "ZIP root folders are the slugs WordPress expects" "$(unzip -Z1 "$DIST/noblen-corporate-theme.zip" | cut -d/ -f1 | sort -u | tr '\n' ' ')$(unzip -Z1 "$DIST/noblen-corporate-core.zip" | cut -d/ -f1 | sort -u | tr '\n' ' ')" "noblen-corporate noblen-corporate-core "
cp -a "$WP_SRC" "$SITE"; rm -rf "$SITE/wp-content/plugins/noblen-corporate-core" "$SITE/wp-content/themes/noblen-corporate"
LOG="$WORK/debug.log"; : > "$LOG"; export WP_DEBUG_LOG_FILE="$LOG"
# PHP error-log check. Counts every entry except the one exact, documented WordPress 6.4.3 / PHP 8.4 core deprecation
# (scripts/php-log-check.sh), and prints what was counted and what was set aside. Versions come from WordPress itself.
unexpected_php_errors() { "$REPO/scripts/php-log-check.sh" "$LOG" "$(W eval 'echo PHP_VERSION;' | tail -1)" "$(W eval 'global $wp_version; echo $wp_version;' | tail -1)" "$SITE"; }
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
# Website Setup from the packaged plugin. Prepare creates drafts only and changes nothing public; Quick start then installs the whole site.
W user create setup-admin setup-admin@example.test --role=administrator --user_pass=pkg-pass-only >/dev/null
W rewrite structure '/%postname%/' --hard >/dev/null
W noblen setup prepare --user=setup-admin >/dev/null
cnt() { W eval "echo count(get_posts(array('post_type'=>'page','post_status'=>'$1','meta_key'=>'_noblen_setup_key','posts_per_page'=>-1,'fields'=>'ids')));"; }
eq "setup: twelve draft pages created" "$(cnt draft)" "12"
eq "setup: three menus, none assigned" "$(W eval 'echo count(get_terms(array("taxonomy"=>"nav_menu","hide_empty"=>false,"meta_key"=>"_noblen_setup_key"))), "|", count(array_filter(get_nav_menu_locations()));')" "3|0"
eq "setup: records are drafts only" "$(W eval 'echo count(get_posts(array("post_type"=>Noblen\Core\Content\Types::all(),"post_status"=>"publish","meta_key"=>"_noblen_setup_key","posts_per_page"=>-1,"fields"=>"ids")));')" "0"
W noblen setup prepare --user=setup-admin >/dev/null
eq "setup: a second run creates no duplicates" "$(W eval 'echo count(get_posts(array("post_type"=>"page","post_status"=>"any","meta_key"=>"_noblen_setup_key","posts_per_page"=>-1,"fields"=>"ids")));')" "12"
eq "setup: the stylesheet is in the package" "$([ -f "$SITE/wp-content/plugins/noblen-corporate-core/assets/admin-setup.css" ] && echo yes || echo no)" "yes"
# Quick start: the whole site in one step.
W noblen setup quickstart --user=setup-admin >/dev/null
eq "quickstart: ten pages published" "$(cnt publish)" "10"
eq "quickstart: the two legal pages stay drafts" "$(cnt draft)" "2"
eq "quickstart: front page, posts page and menus are set" "$(W eval 'echo get_option("show_on_front"), "|", (int)(get_option("page_on_front")>0), "|", (int)(get_option("page_for_posts")>0), "|", count(array_filter(get_nav_menu_locations()));')" "page|1|1|2"
eq "quickstart: no record is published" "$(W eval 'echo count(get_posts(array("post_type"=>Noblen\Core\Content\Types::all(),"post_status"=>"publish","posts_per_page"=>-1,"fields"=>"ids")));')" "0"
W noblen setup quickstart --user=setup-admin >/dev/null
eq "quickstart: a repeat run creates no duplicates" "$(W eval 'echo count(get_posts(array("post_type"=>"page","post_status"=>"any","meta_key"=>"_noblen_setup_key","posts_per_page"=>-1,"fields"=>"ids")));')" "12"
# Serve the installed site and fetch it like a visitor: every page, every internal link and every asset must load.
cat > "$WORK/router.php" <<'PHP'
<?php
$p = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
if ( '/' !== $p && is_file( $_SERVER['DOCUMENT_ROOT'] . $p ) ) { return false; }
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
PHP
W option update home http://127.0.0.1:8099 >/dev/null; W option update siteurl http://127.0.0.1:8099 >/dev/null
php -S 127.0.0.1:8099 -t "$SITE" "$WORK/router.php" >"$WORK/server.log" 2>&1 & SRV=$!
sleep 2
pages_ok=0; pages_bad=""
for path in "" about/ our-businesses/ industries-we-serve/ solutions-and-services/ portfolio/ products-and-platforms/ partnerships/ insights/ contact/; do
  code="$(curl -s -o "$WORK/page.html" -w '%{http_code}' "http://127.0.0.1:8099/$path")"
  if [ "$code" = "200" ] && grep -q '<h1' "$WORK/page.html" && ! grep -qi 'critical error\|Fatal error\|Warning:\|Notice:' "$WORK/page.html"; then pages_ok=$((pages_ok+1)); else pages_bad="$pages_bad /$path($code)"; fi
done
eq "installed site: all ten public pages return 200 with a heading and no PHP errors${pages_bad:+ ($pages_bad)}" "$pages_ok" "10"
eq "installed site: legal drafts are not public" "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8099/privacy/)" "404"
bad_links=""; checked=0
for path in "" about/ our-businesses/ industries-we-serve/ solutions-and-services/ portfolio/ products-and-platforms/ partnerships/ insights/ contact/; do
  curl -s "http://127.0.0.1:8099/$path" > "$WORK/page.html"
  for u in $(grep -oE '(href|src)="(/[^"#]*|http://127\.0\.0\.1:8099[^"#]*)' "$WORK/page.html" | sed -E 's/^(href|src)="//; s#^http://127\.0\.0\.1:8099##' | sort -u); do
    case "$u" in //*) continue;; esac
    c="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:8099$u")"; checked=$((checked+1))
    case "$c" in 200|301|302) ;; *) bad_links="$bad_links $u($c)";; esac
  done
done
eq "installed site: every internal link and asset loads (checked $checked)${bad_links:+ ($bad_links)}" "$([ -z "$bad_links" ] && [ "$checked" -gt 50 ] && echo yes || echo no)" "yes"
eq "installed site: header and footer menus show the pages" "$(curl -s http://127.0.0.1:8099/ | grep -c 'href="http://127.0.0.1:8099/about/"' | awk '{print ($1>=2)?"yes":"no"}')" "yes"
kill "$SRV" 2>/dev/null
eq "C19-003 no PHP errors logged" "$(unexpected_php_errors)" "0"
echo "$pass passed, $fail failed"; rm -rf "$WORK"
[ "$fail" -eq 0 ]
