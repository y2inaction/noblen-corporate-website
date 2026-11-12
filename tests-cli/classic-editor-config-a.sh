#!/usr/bin/env bash
# Configuration A of criterion 21: install the PINNED official Classic Editor plugin into a DISPOSABLE test site,
# verify its provenance, set and record its settings. Mode "install" (default) or "cleanup" (restores Configuration B).
# Development and CI test environments only. Never part of a package.
#
# Env:
#   WPCMD       how to run WP-CLI against the site, e.g. "tests-e2e/node_modules/.bin/wp-env run cli wp"
#               or "vendor/bin/wp --path=/path/to/wordpress" (default: wp-env)
#   DL_DIR      host directory for the download (default tests-e2e/wp-env/downloads)
#   SITE_DL_DIR the same directory as the SITE sees it (default wp-content/noblen-e2e/downloads, the wp-env mapping)
#   RECORD_FILE where the C21-022 record is written (default $DL_DIR/classic-editor-record.json)
set -u
REPO="$(cd "$(dirname "$0")/.." && pwd)"
PIN="${PIN_FILE:-$REPO/tests-e2e/wp-env/classic-editor.pin.json}"
WPCMD="${WPCMD:-$REPO/tests-e2e/node_modules/.bin/wp-env run cli wp}"
DL_DIR="${DL_DIR:-$REPO/tests-e2e/wp-env/downloads}"
SITE_DL_DIR="${SITE_DL_DIR:-wp-content/noblen-e2e/downloads}"
RECORD_FILE="${RECORD_FILE:-$DL_DIR/classic-editor-record.json}"
MODE="${1:-install}"
# TEST-ONLY switch used to validate this script's logic against a stand-in package on a local server.
# It skips the official-source and official-checksum checks, so its results are NOT valid evidence. Refused in CI.
TEST_ONLY="${TEST_ONLY_SKIP_OFFICIAL_CHECKS:-0}"
if [ "$TEST_ONLY" = "1" ]; then
  if [ -n "${CI:-}" ] || [ -n "${GITHUB_ACTIONS:-}" ]; then echo "FAIL TEST_ONLY_SKIP_OFFICIAL_CHECKS is refused in CI"; exit 2; fi
  echo "WARNING: TEST-ONLY MODE. Official-source and official-checksum checks are skipped. NOT VALID EVIDENCE."
fi
pass=0; fail=0
ok()  { pass=$((pass+1)); echo "ok   $1"; }
bad() { fail=$((fail+1)); echo "FAIL $1 :: ${2:-}"; }
die() { bad "$1" "${2:-}"; echo "$pass passed, $fail failed"; exit 1; }
# shellcheck disable=SC2086
W() { $WPCMD "$@" 2>&1 | tr -d '\r' | sed '/^$/d'; }
# shellcheck disable=SC2086
Wq() { $WPCMD "$@" 2>/dev/null | tr -d '\r' | sed '/^$/d'; }
pin() { node -e "const p=JSON.parse(require('fs').readFileSync(process.argv[1],'utf8'));console.log(p[process.argv[2]]||'')" "$PIN" "$1"; }

SLUG="$(pin slug)"; VERSION="$(pin version)"; SOURCE="$(pin source)"; PIN_SHA="$(pin zip_sha256)"
[ -n "$SLUG" ] && [ -n "$VERSION" ] && [ -n "$SOURCE" ] || die "pin file is complete" "slug, version and source are required in $PIN"
if [ "$TEST_ONLY" = "1" ]; then ok "(test-only) source check skipped"; else case "$SOURCE" in "https://downloads.wordpress.org/plugin/$SLUG.$VERSION.zip") ok "pin uses the exact-version official WordPress.org URL (not 'latest')";; *) die "pin uses the exact-version official WordPress.org URL" "got $SOURCE";; esac; fi

if [ "$MODE" = "cleanup" ]; then
  Wq plugin deactivate "$SLUG" >/dev/null; Wq plugin delete "$SLUG" >/dev/null
  Wq option delete classic-editor-replace >/dev/null; Wq option delete classic-editor-allow-users >/dev/null
  [ -z "$(Wq plugin list --name="$SLUG" --field=name)" ] && ok "Configuration B restored: Classic Editor plugin removed" || bad "Configuration B restored" "plugin still present"
  echo "$pass passed, $fail failed"; [ "$fail" -eq 0 ]; exit
fi

mkdir -p "$DL_DIR"; ZIP="$DL_DIR/$SLUG.$VERSION.zip"; rm -f "$ZIP"
curl -fsSL --retry 3 -o "$ZIP" "$SOURCE" || die "download of the pinned package from WordPress.org" "curl failed for $SOURCE"
SHA="$(sha256sum "$ZIP" | cut -d' ' -f1)"
ok "downloaded $SLUG $VERSION from the pinned source (SHA-256 $SHA)"
if [ -n "$PIN_SHA" ]; then [ "$SHA" = "$PIN_SHA" ] && ok "package SHA-256 equals the pinned value" || die "package SHA-256 equals the pinned value" "pinned $PIN_SHA, got $SHA"; else echo "note: zip_sha256 is not yet pinned; record the value above in $PIN after this run"; fi
HDR="$(unzip -p "$ZIP" "$SLUG/$SLUG.php" 2>/dev/null | sed -n 's/^ \* Version:[[:space:]]*//p' | head -1 | tr -d '\r ')"
[ "$HDR" = "$VERSION" ] && ok "plugin header version equals the pin ($HDR)" || die "plugin header version equals the pin" "header '$HDR', pin '$VERSION'"

OUT="$(W plugin install "$SITE_DL_DIR/$SLUG.$VERSION.zip" --activate)"
echo "$OUT" | grep -qi "success" && ok "installed and activated from the verified package" || die "installed and activated from the verified package" "$OUT"
if [ "$TEST_ONLY" = "1" ]; then ok "(test-only) official checksum verification skipped"; else
OUT="$(W plugin verify-checksums "$SLUG")"
echo "$OUT" | grep -qi "success: verified" && ok "WP-CLI verified every file against WordPress.org's published checksums for $VERSION" || die "WP-CLI verified every file against WordPress.org's published checksums" "$OUT"
fi
INSTALLED="$(Wq plugin get "$SLUG" --field=version | tail -1)"
[ "$INSTALLED" = "$VERSION" ] && ok "C21-021 installed version ($INSTALLED) equals the pinned version" || die "C21-021 installed version equals the pinned version" "installed '$INSTALLED', pin '$VERSION'"
[ "$(Wq plugin get "$SLUG" --field=status | tail -1)" = "active" ] && ok "Classic Editor plugin is active" || die "Classic Editor plugin is active"

# Configuration A: the plugin's default editor is the block editor, so standard posts keep the block editor.
Wq option update classic-editor-replace block >/dev/null; Wq option update classic-editor-allow-users disallow >/dev/null
REPLACE="$(Wq option get classic-editor-replace | tail -1)"; ALLOW="$(Wq option get classic-editor-allow-users | tail -1)"
[ "$REPLACE" = "block" ] && [ "$ALLOW" = "disallow" ] && ok "C21-022 settings applied and read back (default editor: $REPLACE, users may switch: $ALLOW)" || die "C21-022 settings applied and read back" "replace=$REPLACE allow=$ALLOW"
WPV="$(Wq core version | tail -1)"
cat > "$RECORD_FILE" <<JSON
{"criterion":"C21-021/C21-022","configuration":"A","plugin":"$SLUG","pinned_version":"$VERSION","installed_version":"$INSTALLED","source":"$SOURCE","package_sha256":"$SHA","official_checksums_verified":$([ "$TEST_ONLY" = "1" ] && echo false || echo true),"test_only":$([ "$TEST_ONLY" = "1" ] && echo true || echo false),"settings":{"classic-editor-replace":"$REPLACE","classic-editor-allow-users":"$ALLOW"},"wordpress":"$WPV","recorded_utc":"$(date -u +%FT%TZ)"}
JSON
ok "C21-022 record written to $RECORD_FILE"
[ -n "${GITHUB_STEP_SUMMARY:-}" ] && { echo "### Classic Editor, Configuration A"; echo '```json'; cat "$RECORD_FILE"; echo '```'; } >> "$GITHUB_STEP_SUMMARY"
echo "$pass passed, $fail failed"; [ "$fail" -eq 0 ]
