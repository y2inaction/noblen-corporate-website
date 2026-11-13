#!/usr/bin/env bash
# Activate and VERIFY the intended plugin and theme in the wp-env site, before any WE acceptance test.
# wp-env 12.1.0 activates mapped PLUGINS but not themes (its source has no theme activation, despite a comment saying so).
# A test that passes because the intended theme is inactive is not valid evidence, so this runs first and fails loudly.
# Env: WE_RUN (default: wp-env run cli), BASE_URL (default http://localhost:8888).
set -u
REPO="$(cd "$(dirname "$0")/.." && pwd)"
RUN="${WE_RUN:-$REPO/tests-e2e/node_modules/.bin/wp-env run cli}"
BASE="${BASE_URL:-http://localhost:8888}"
THEME="${EXPECT_THEME:-noblen-corporate}"; PLUGIN="${EXPECT_PLUGIN:-noblen-corporate-core}"
fail=0
bad() { echo "FAIL $1 :: ${2:-}"; fail=1; }
ok()  { echo "ok   $1"; }
# shellcheck disable=SC2086
W() { $RUN wp "$@" 2>/dev/null | tr -d '\r' | sed '/^$/d' | tail -1; }

W theme activate "$THEME" >/dev/null
[ "$(W option get stylesheet)" = "$THEME" ] && [ "$(W option get template)" = "$THEME" ] && ok "theme $THEME is the active stylesheet and template" || bad "theme $THEME is active" "stylesheet='$(W option get stylesheet)' template='$(W option get template)'"
[ "$(W theme get "$THEME" --field=status)" = "active" ] && ok "WP-CLI reports the theme as active" || bad "WP-CLI reports the theme as active"
[ "$(W plugin get "$PLUGIN" --field=status)" = "active" ] && ok "plugin $PLUGIN is active" || bad "plugin $PLUGIN is active"
# Proof from the rendered site, not only from stored options: the theme's own stylesheet must be on the front page.
if curl -fsS "$BASE/" | grep -q "noblen-main-css"; then ok "the front page is rendered by the Noblen theme (its stylesheet is present)"; else bad "the front page is rendered by the Noblen theme" "stylesheet 'noblen-main' not found at $BASE/"; fi
[ "$fail" -eq 0 ] && echo "site state verified" || echo "SITE STATE WRONG: do not run acceptance tests"
exit "$fail"
