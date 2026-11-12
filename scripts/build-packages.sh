#!/usr/bin/env bash
# Build installable ZIPs for the theme and the core plugin. Excludes tests and dev files.
# Usage: scripts/build-packages.sh [output-dir]
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
out="${1:-$root/dist}"
mkdir -p "$out"
( cd "$root/wordpress-theme" && zip -qr "$out/noblen-corporate-theme.zip" noblen-corporate -x '*.DS_Store' )
( cd "$root/wordpress-plugin" && zip -qr "$out/noblen-corporate-core.zip" noblen-corporate-core -x 'noblen-corporate-core/tests/*' '*.DS_Store' )
( cd "$out" && sha256sum noblen-corporate-theme.zip noblen-corporate-core.zip > SHA256SUMS )
echo "Built in $out"; cat "$out/SHA256SUMS"
