#!/usr/bin/env bash
# C17-001: OWNER-RUN local naming scan. Never run by CI.
# The blocked-term list lives OUTSIDE the repository and is never printed. Output is PASS or FAIL only.
# Usage: NOBLEN_BLOCKLIST=/path/to/private/terms.txt scripts/naming-scan.sh [directory-to-scan ...]
set -u
list="${NOBLEN_BLOCKLIST:-}"
[ -n "$list" ] && [ -r "$list" ] || { echo "Set NOBLEN_BLOCKLIST to a readable private term list (one term per line)."; exit 2; }
root="$(cd "$(dirname "$0")/.." && pwd)"
targets=("$@"); [ ${#targets[@]} -eq 0 ] && targets=("$root")
if grep -rIiF -f "$list" --exclude-dir=.git --exclude-dir=node_modules --exclude-dir=vendor -q -- "${targets[@]}" 2>/dev/null; then
  echo "FAIL: a blocked term was found. Locate it with your own list; this tool does not print it."; exit 1
fi
echo "PASS: no blocked term found in the scanned files"
