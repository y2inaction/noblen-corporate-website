#!/usr/bin/env bash
# Counts the PHP errors, warnings, notices and deprecations in a WordPress debug log.
#
#   scripts/php-log-check.sh LOG PHP_VERSION WP_VERSION WP_ROOT
#
# stdout: the number of UNEXPECTED entries (the only thing a caller should compare against 0).
# stderr: a human-readable account, so CI shows exactly what was counted and what was set aside.
#
# Exactly ONE upstream message is treated as informational, and only when ALL of these hold:
#   - PHP is 8.4.x
#   - WordPress is exactly 6.4.3
#   - the entry is a "PHP Deprecated" with this exact text:
#       wp_getimagesize(): Implicitly marking parameter $image_info as nullable is deprecated,
#       the explicit nullable type must be used instead
#   - it is reported at WP_ROOT/wp-includes/media.php on line 5481 (the WordPress core file itself)
# Everything else is counted: other core errors, any other line or version, and every entry from the
# plugin, the theme or any other code. There is no directory-wide or file-wide exclusion.
# See documentation/UPSTREAM-PHP84-DEPRECATION.md.
set -u
LOG="${1:?log file}"; PHPV="${2:?php version}"; WPV="${3:?wordpress version}"; ROOT="${4:?wordpress root}"
ROOT="${ROOT%/}"

known_applies=0
case "$PHPV" in 8.4|8.4.*) [ "$WPV" = "6.4.3" ] && known_applies=1 ;; esac

# Extended regex, the whole line, anchored at both ends. Group 1 is the file path.
KNOWN_RE='^\[[^]]*\] PHP Deprecated:  wp_getimagesize\(\): Implicitly marking parameter [$]image_info as nullable is deprecated, the explicit nullable type must be used instead in (/[^[:space:]]+) on line 5481$'

total=0; allowed=0; unexpected=0
declare -a bad=()
if [ -r "$LOG" ]; then
  while IFS= read -r line; do
    total=$((total+1))
    if [ "$known_applies" = 1 ] && [[ "$line" =~ $KNOWN_RE ]] && [ "${BASH_REMATCH[1]}" = "$ROOT/wp-includes/media.php" ]; then
      allowed=$((allowed+1))
    else
      unexpected=$((unexpected+1)); bad+=("$line")
    fi
  done < <(grep -E 'PHP (Fatal error|Warning|Notice|Deprecated|Parse error)' "$LOG" 2>/dev/null || true)
fi

{
  echo "  PHP error-log check: PHP $PHPV, WordPress $WPV, $total entr$([ "$total" = 1 ] && echo y || echo ies)"
  if [ "$allowed" -gt 0 ]; then
    echo "  NOTE informational upstream deprecation (WordPress 6.4.3 core on PHP 8.4, not our code): $allowed x"
    echo "       wp_getimagesize(): Implicitly marking parameter \$image_info as nullable is deprecated (wp-includes/media.php:5481)"
  fi
  if [ "$unexpected" -gt 0 ]; then
    echo "  UNEXPECTED entries: $unexpected"
    printf '  | %s\n' "${bad[@]:0:40}" | cut -c1-300
  fi
} >&2
echo "$unexpected"
