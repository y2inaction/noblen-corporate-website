#!/usr/bin/env bash
# Regression tests for scripts/php-log-check.sh: the single allowed upstream deprecation is narrow.
# Needs no WordPress, database or network.
set -u
REPO="$(cd "$(dirname "$0")/.." && pwd)"; CHK="$REPO/scripts/php-log-check.sh"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
pass=0; fail=0
ok()  { pass=$((pass+1)); echo "ok   $1"; }
bad() { fail=$((fail+1)); echo "FAIL $1 :: ${2:-}"; }
ROOT=/srv/wp
TS='[12-Nov-2026 18:24:10 UTC]'
MSG='wp_getimagesize(): Implicitly marking parameter $image_info as nullable is deprecated, the explicit nullable type must be used instead'
KNOWN="$TS PHP Deprecated:  $MSG in $ROOT/wp-includes/media.php on line 5481"
# expect NAME EXPECTED_UNEXPECTED PHP WP ROOT  <<< log text
expect() {
  local name="$1" want="$2" php="$3" wp="$4" root="$5"; printf '%s' "$(cat)" > "$T/log"; [ -s "$T/log" ] && echo >> "$T/log"
  local got; got="$("$CHK" "$T/log" "$php" "$wp" "$root" 2>/dev/null)"
  [ "$got" = "$want" ] && ok "$name" || bad "$name" "expected [$want] got [$got]"
}
# ---- Positive: the exact known message is set aside, only in the exact context.
expect "positive: exact message, PHP 8.4.3, WordPress 6.4.3, core path"          0 8.4.3 6.4.3 "$ROOT" <<< "$KNOWN"
expect "positive: PHP given as 8.4"                                               0 8.4   6.4.3 "$ROOT" <<< "$KNOWN"
expect "positive: many repeats of the exact message"                              0 8.4.3 6.4.3 "$ROOT" <<< "$(printf '%s\n' "$KNOWN" "$KNOWN" "$KNOWN")"
expect "positive: trailing slash on the root is tolerated"                        0 8.4.3 6.4.3 "$ROOT/" <<< "$KNOWN"
expect "empty log has no entries"                                                 0 8.4.3 6.4.3 "$ROOT" <<< ""
# ---- Negative: the version context must match.
expect "negative: same line on PHP 8.3 is counted"                                1 8.3.9 6.4.3 "$ROOT" <<< "$KNOWN"
expect "negative: same line on PHP 8.5 is counted (not assumed)"                  1 8.5.0 6.4.3 "$ROOT" <<< "$KNOWN"
expect "negative: same line on WordPress 6.4.4 is counted"                        1 8.4.3 6.4.4 "$ROOT" <<< "$KNOWN"
expect "negative: same line on WordPress 6.5 is counted"                          1 8.4.3 6.5   "$ROOT" <<< "$KNOWN"
# ---- Negative: the message, file and line must match exactly.
expect "negative: different line number"                                          1 8.4.3 6.4.3 "$ROOT" <<< "${KNOWN/line 5481/line 5482}"
expect "negative: different core file"                                            1 8.4.3 6.4.3 "$ROOT" <<< "${KNOWN/media.php/formatting.php}"
expect "negative: different function"                                             1 8.4.3 6.4.3 "$ROOT" <<< "${KNOWN/wp_getimagesize/wp_get_attachment_image}"
expect "negative: different parameter"                                            1 8.4.3 6.4.3 "$ROOT" <<< "${KNOWN/image_info/other_param}"
expect "negative: same text as a Warning, not a Deprecated"                       1 8.4.3 6.4.3 "$ROOT" <<< "${KNOWN/Deprecated/Warning}"
expect "negative: same text as a Fatal error"                                     1 8.4.3 6.4.3 "$ROOT" <<< "${KNOWN/Deprecated/Fatal error}"
expect "negative: extra text appended after the line number"                      1 8.4.3 6.4.3 "$ROOT" <<< "$KNOWN and more"
# ---- Negative: the location must be WordPress core itself, never other code.
expect "negative: same message from a plugin's media.php"                         1 8.4.3 6.4.3 "$ROOT" <<< "${KNOWN/$ROOT\/wp-includes/$ROOT\/wp-content\/plugins\/noblen-corporate-core\/wp-includes}"
expect "negative: same message from a theme's media.php"                          1 8.4.3 6.4.3 "$ROOT" <<< "${KNOWN/$ROOT\/wp-includes/$ROOT\/wp-content\/themes\/noblen-corporate\/wp-includes}"
expect "negative: same message from another WordPress root"                       1 8.4.3 6.4.3 "$ROOT" <<< "${KNOWN/$ROOT/\/elsewhere\/wp}"
# ---- Negative: plugin, theme and other core errors stay fatal, alone or mixed with the known one.
P="$ROOT/wp-content/plugins/noblen-corporate-core/includes/X.php"
H="$ROOT/wp-content/themes/noblen-corporate/functions.php"
expect "negative: plugin deprecation"                                             1 8.4.3 6.4.3 "$ROOT" <<< "$TS PHP Deprecated:  Something in $P on line 10"
expect "negative: plugin warning"                                                 1 8.4.3 6.4.3 "$ROOT" <<< "$TS PHP Warning:  Undefined variable \$x in $P on line 10"
expect "negative: plugin notice"                                                  1 8.4.3 6.4.3 "$ROOT" <<< "$TS PHP Notice:  Undefined index in $P on line 10"
expect "negative: plugin fatal error"                                             1 8.4.3 6.4.3 "$ROOT" <<< "$TS PHP Fatal error:  Uncaught Error in $P:10"
expect "negative: plugin parse error"                                             1 8.4.3 6.4.3 "$ROOT" <<< "$TS PHP Parse error:  syntax error in $P on line 3"
expect "negative: theme warning"                                                  1 8.4.3 6.4.3 "$ROOT" <<< "$TS PHP Warning:  Undefined variable in $H on line 7"
expect "negative: other core deprecation in the same file"                        1 8.4.3 6.4.3 "$ROOT" <<< "$TS PHP Deprecated:  Other thing in $ROOT/wp-includes/media.php on line 100"
expect "negative: other core deprecation elsewhere"                               1 8.4.3 6.4.3 "$ROOT" <<< "$TS PHP Deprecated:  Other thing in $ROOT/wp-includes/formatting.php on line 1"
expect "negative: the canary warning is counted (it is not hidden)"               1 8.4.3 6.4.3 "$ROOT" <<< "$TS PHP Warning:  noblen-canary in /x.php(1) : eval()'d code on line 1"
expect "mixed: known + one plugin warning = exactly 1"                            1 8.4.3 6.4.3 "$ROOT" <<< "$(printf '%s\n' "$KNOWN" "$TS PHP Warning:  x in $P on line 1" "$KNOWN")"
expect "mixed: known + theme + other core = exactly 2"                            2 8.4.3 6.4.3 "$ROOT" <<< "$(printf '%s\n' "$KNOWN" "$TS PHP Warning:  x in $H on line 1" "$TS PHP Deprecated:  y in $ROOT/wp-includes/post.php on line 2")"
# ---- The diagnostics name the exception explicitly.
printf '%s\n' "$KNOWN" > "$T/log"
"$CHK" "$T/log" 8.4.3 6.4.3 "$ROOT" 2> "$T/err" >/dev/null
grep -q 'informational upstream deprecation' "$T/err" && grep -q 'wp-includes/media.php:5481' "$T/err" && ok "diagnostics print the exception explicitly" || bad "diagnostics print the exception"
printf '%s\n' "$TS PHP Warning:  x in $P on line 1" > "$T/log"
"$CHK" "$T/log" 8.4.3 6.4.3 "$ROOT" 2> "$T/err" >/dev/null
grep -q 'UNEXPECTED entries: 1' "$T/err" && ok "diagnostics print unexpected entries" || bad "diagnostics print unexpected entries"
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
