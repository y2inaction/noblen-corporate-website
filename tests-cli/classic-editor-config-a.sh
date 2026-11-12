#!/usr/bin/env bash
# Configuration A of criterion 21: install the PINNED official Classic Editor plugin into a DISPOSABLE test site,
# verify what the frozen plan requires, and record the settings used.
#   C21-021  "Classic Editor plugin version and source match the VERIFIED pin"
#   C21-022  "records the Classic Editor settings used for the run"
# Development and CI test environments only. Never part of a package. This script never edits the pin.
#
# Modes (first argument):
#   install            STRICT. Refuses to run while the pin is not verified, because C21-021 needs a verified pin.
#   install --capture  CAPTURE (first hosted run). Allowed only while the pin is 'unverified'. Downloads the package,
#                      records its SHA-256 and a source-parity report for a person to review, runs the checks, and
#                      reports C21-021 as NOT SATISFIED. The result is not verification and not acceptance evidence.
#   cleanup            Remove the plugin and its options (restores Configuration B).
#
# Env: WPCMD (how to run WP-CLI; default wp-env), DL_DIR, SITE_DL_DIR, RECORD_FILE, PIN_FILE.
set -u
REPO="$(cd "$(dirname "$0")/.." && pwd)"
PIN="${PIN_FILE:-$REPO/tests-e2e/wp-env/classic-editor.pin.json}"
WPCMD="${WPCMD:-$REPO/tests-e2e/node_modules/.bin/wp-env run cli wp}"
DL_DIR="${DL_DIR:-$REPO/tests-e2e/wp-env/downloads}"
SITE_DL_DIR="${SITE_DL_DIR:-wp-content/noblen-e2e/downloads}"
RECORD_FILE="${RECORD_FILE:-$DL_DIR/classic-editor-record.json}"
MODE="${1:-install}"; CAPTURE=0; [ "${2:-}" = "--capture" ] && CAPTURE=1
pass=0; fail=0
ok()   { pass=$((pass+1)); echo "ok   $1"; }
bad()  { fail=$((fail+1)); echo "FAIL $1 :: ${2:-}"; }
die()  { bad "$1" "${2:-}"; echo "$pass passed, $fail failed"; exit 1; }
warn() { echo "WARN $1"; [ -n "${GITHUB_ACTIONS:-}" ] && echo "::warning::$1"; }
# shellcheck disable=SC2086
Wq() { $WPCMD "$@" 2>/dev/null | tr -d '\r' | sed '/^$/d'; }
# shellcheck disable=SC2086
W()  { $WPCMD "$@" 2>&1 | tr -d '\r' | sed '/^$/d'; }
pin() { node -e "const p=JSON.parse(require('fs').readFileSync(process.argv[1],'utf8'));console.log(process.argv[2].split('.').reduce((o,k)=>o&&o[k],p)||'')" "$PIN" "$1"; }

# TEST-ONLY switch to exercise this script against a stand-in package on a local server. Skips the official-source and
# official-checksum checks, so its results are NOT valid evidence. Refused in CI.
TEST_ONLY="${TEST_ONLY_SKIP_OFFICIAL_CHECKS:-0}"
if [ "$TEST_ONLY" = "1" ]; then
  if [ -n "${CI:-}" ] || [ -n "${GITHUB_ACTIONS:-}" ]; then echo "FAIL TEST_ONLY_SKIP_OFFICIAL_CHECKS is refused in CI"; exit 2; fi
  echo "WARNING: TEST-ONLY MODE. Official-source and official-checksum checks are skipped. NOT VALID EVIDENCE."
fi

SLUG="$(pin slug)"; VERSION="$(pin version)"; SOURCE="$(pin source)"; PIN_SHA="$(pin zip_sha256)"; STATE="$(pin verification.state)"
[ -n "$SLUG" ] && [ -n "$VERSION" ] && [ -n "$SOURCE" ] || die "pin file is complete" "slug, version and source are required in $PIN"
if [ "$TEST_ONLY" != "1" ]; then
  [ "$SOURCE" = "https://downloads.wordpress.org/plugin/$SLUG.$VERSION.zip" ] && ok "pin uses the exact-version official WordPress.org URL (not 'latest')" || die "pin uses the exact-version official WordPress.org URL" "got $SOURCE"
fi

if [ "$MODE" = "cleanup" ]; then
  Wq plugin deactivate "$SLUG" >/dev/null; Wq plugin delete "$SLUG" >/dev/null
  Wq option delete classic-editor-replace >/dev/null; Wq option delete classic-editor-allow-users >/dev/null
  [ -z "$(Wq plugin list --name="$SLUG" --field=name)" ] && ok "Configuration B restored: Classic Editor plugin removed" || bad "Configuration B restored" "plugin still present"
  echo "$pass passed, $fail failed"; [ "$fail" -eq 0 ]; exit
fi

# ---- Gate on the verification state of the pin (no trust on first use).
if [ "$CAPTURE" -eq 0 ]; then
  [ "$STATE" = "verified" ] && [ -n "$PIN_SHA" ] || die "C21-021 the pin is VERIFIED (strict mode)" "pin state is '$STATE' and zip_sha256 is '${PIN_SHA:-blank}'. C21-021 requires a verified pin. Follow documentation/CLASSIC-EDITOR-VERIFICATION-PROCEDURE.md; do not edit the pin from a script."
  ok "pin is verified (reviewed by $(pin verification.reviewed_by) on $(pin verification.reviewed_utc))"
else
  [ "$STATE" = "unverified" ] || die "capture mode is only for an unverified pin" "state is '$STATE'"
  [ -z "$PIN_SHA" ] || die "capture mode is only for a pin with no hash" "zip_sha256 is already set"
  echo "=== CAPTURE RUN: this records the package identity for REVIEW. It is not verification and not acceptance evidence. ==="
fi

# ---- Download, hardened: https only, TLS 1.2+, few redirects, size cap, host check on the final URL.
mkdir -p "$DL_DIR"; ZIP="$DL_DIR/$SLUG.$VERSION.zip"; rm -f "$ZIP"
FINAL="$(curl -fsSL --proto '=https' --tlsv1.2 --max-redirs 3 --max-filesize 5000000 --retry 3 -o "$ZIP" -w '%{url_effective}' "$SOURCE" 2>/dev/null)" || {
  if [ "$TEST_ONLY" = "1" ]; then FINAL="$(curl -fsSL --max-filesize 5000000 -o "$ZIP" -w '%{url_effective}' "$SOURCE")" || die "download of the pinned package" "curl failed for $SOURCE"; else die "download of the pinned package from WordPress.org" "curl failed for $SOURCE"; fi; }
if [ "$TEST_ONLY" != "1" ]; then
  case "$FINAL" in https://downloads.wordpress.org/*) ok "download came from the official host (final URL $FINAL)";; *) die "download came from the official host" "final URL $FINAL";; esac
fi
SHA="$(sha256sum "$ZIP" | cut -d' ' -f1)"; ok "downloaded $SLUG $VERSION (SHA-256 $SHA)"

# ---- Package safety: a valid zip whose every entry sits inside <slug>/ with no traversal or absolute path.
unzip -tq "$ZIP" >/dev/null 2>&1 && ok "package is a valid zip" || die "package is a valid zip"
BADENT="$(unzip -Z1 "$ZIP" | grep -vE "^$SLUG/" | head -3)$(unzip -Z1 "$ZIP" | grep -E '(^/|\.\./)' | head -3)"
[ -z "$BADENT" ] && ok "every package entry is inside $SLUG/ (no traversal, no absolute paths)" || die "package entries are confined to $SLUG/" "$BADENT"
HDR="$(unzip -p "$ZIP" "$SLUG/$SLUG.php" 2>/dev/null | sed -n 's/^ \* Version:[[:space:]]*//p' | head -1 | tr -d '\r ')"
[ "$HDR" = "$VERSION" ] && ok "plugin header version equals the pin ($HDR)" || die "plugin header version equals the pin" "header '$HDR', pin '$VERSION'"

if [ "$CAPTURE" -eq 0 ]; then
  [ "$SHA" = "$PIN_SHA" ] && ok "package SHA-256 equals the verified pin" || die "package SHA-256 equals the verified pin" "pinned $PIN_SHA, downloaded $SHA. Do NOT update the pin automatically; investigate."
else
  echo "---- source parity report (for the reviewer):"
  PARITY_OUT="$("$REPO/scripts/compare-classic-editor-source.sh" "$ZIP" "$PIN" 2>&1)"; PARITY_RC=$?; echo "$PARITY_OUT" | sed 's/^/     /'
  case "$PARITY_RC" in 0) PARITY="identical";; 4) PARITY="DIFFERENT (reviewer must inspect)";; *) PARITY="could not compare";; esac
  ok "source parity report produced: $PARITY"
fi

# ---- Install from the verified local file, then check against WordPress.org's published per-file checksums.
OUT="$(W plugin install "$SITE_DL_DIR/$SLUG.$VERSION.zip" --activate)"
echo "$OUT" | grep -qi "success" && ok "installed and activated from the downloaded package" || die "installed and activated from the downloaded package" "$OUT"
if [ "$TEST_ONLY" = "1" ]; then ok "(test-only) official checksum verification skipped"; else
  OUT="$(W plugin verify-checksums "$SLUG")"
  echo "$OUT" | grep -qi "success: verified" && ok "WP-CLI verified every file against WordPress.org's published checksums for $VERSION" || die "WP-CLI verified every file against WordPress.org's published checksums" "$OUT"
fi
INSTALLED="$(Wq plugin get "$SLUG" --field=version | tail -1)"
[ "$INSTALLED" = "$VERSION" ] && ok "installed version ($INSTALLED) equals the pinned version" || die "installed version equals the pinned version" "installed '$INSTALLED', pin '$VERSION'"
[ "$(Wq plugin get "$SLUG" --field=status | tail -1)" = "active" ] && ok "Classic Editor plugin is active" || die "Classic Editor plugin is active"

# ---- C21-022. Configuration A means the plugin's default editor is the block editor. Only that setting is set;
# the plugin's other option is left at its own default and both are recorded exactly as read.
Wq option update classic-editor-replace block >/dev/null
REPLACE="$(Wq option get classic-editor-replace | tail -1)"
ALLOW="$(Wq option get classic-editor-allow-users | tail -1)"; [ -n "$ALLOW" ] || ALLOW="(not stored: the plugin's own default applies)"
[ "$REPLACE" = "block" ] && ok "C21-022 settings used: classic-editor-replace=$REPLACE; classic-editor-allow-users=$ALLOW" || die "C21-022 default editor is the block editor" "classic-editor-replace='$REPLACE'"
WPV="$(Wq core version | tail -1)"; PHPV="$(Wq eval 'echo PHP_VERSION;' | tail -1)"
if [ "$TEST_ONLY" = "1" ]; then C21_021="NOT VALID EVIDENCE: test-only mode against a stand-in package"; elif [ "$CAPTURE" -eq 1 ]; then C21_021="NOT SATISFIED: the pin is unverified. This capture run supplies material for review only."; else C21_021="satisfied by this run against a verified pin"; fi
cat > "$RECORD_FILE" <<JSON
{"criteria":"C21-021 and C21-022","mode":"$([ "$CAPTURE" -eq 1 ] && echo CAPTURE || echo STRICT)","test_only":$([ "$TEST_ONLY" = "1" ] && echo true || echo false),"c21_021":"$C21_021","plugin":"$SLUG","pinned_version":"$VERSION","installed_version":"$INSTALLED","pin_state":"$STATE","source":"$SOURCE","final_url":"$FINAL","package_sha256":"$SHA","pinned_sha256":"$PIN_SHA","source_parity":"${PARITY:-not run in strict mode}","official_checksums_verified":$([ "$TEST_ONLY" = "1" ] && echo false || echo true),"settings_used":{"classic-editor-replace":"$REPLACE","classic-editor-allow-users":"$ALLOW"},"wordpress":"$WPV","php":"$PHPV","run":{"id":"${GITHUB_RUN_ID:-local}","attempt":"${GITHUB_RUN_ATTEMPT:-local}","commit":"${GITHUB_SHA:-local}"},"recorded_utc":"$(date -u +%FT%TZ)"}
JSON
ok "C21-022 record written to $RECORD_FILE"
if [ -n "${GITHUB_STEP_SUMMARY:-}" ]; then
  { echo "### Classic Editor, Configuration A: $([ "$CAPTURE" -eq 1 ] && echo 'CAPTURE RUN (not verification)' || echo 'strict run')"; echo "- C21-021: $C21_021"; echo "- Package SHA-256: \`$SHA\`"; echo '```json'; cat "$RECORD_FILE"; echo '```'; } >> "$GITHUB_STEP_SUMMARY"
fi
[ "$CAPTURE" -eq 1 ] && warn "Capture run complete. Review the SHA-256 and the source-parity report, then follow documentation/CLASSIC-EDITOR-VERIFICATION-PROCEDURE.md. The pin has NOT been changed."
echo "$pass passed, $fail failed"; [ "$fail" -eq 0 ]
