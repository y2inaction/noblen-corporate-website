#!/usr/bin/env bash
# Build installable ZIPs for the theme and the core plugin. Excludes tests and dev files.
# Reproducible: file times are fixed to the last commit time and entries are sorted, so the same
# source always gives the same checksum.
# Usage: scripts/build-packages.sh [output-dir]
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
out="${1:-$root/dist}"
mkdir -p "$out"; out="$(cd "$out" && pwd)"
epoch="${SOURCE_DATE_EPOCH:-$(git -C "$root" log -1 --format=%ct 2>/dev/null || echo 1700000000)}"
stage="$(mktemp -d)"; trap 'rm -rf "$stage"' EXIT

build() { # build <zip-name> <parent-dir> <package-dir> [exclude-dir]
  local name="$1" parent="$2" pkg="$3" exclude="${4:-}"
  rm -rf "$stage/$pkg"; cp -a "$parent/$pkg" "$stage/$pkg"
  [ -n "$exclude" ] && rm -rf "$stage/$pkg/$exclude"
  find "$stage/$pkg" -name .DS_Store -delete
  find "$stage/$pkg" -exec touch -h -d "@$epoch" {} +
  rm -f "$out/$name"
  ( cd "$stage" && find "$pkg" -print | LC_ALL=C sort | zip -X -q "$out/$name" -@ )
}
build noblen-corporate-theme.zip "$root/wordpress-theme" noblen-corporate
build noblen-corporate-core.zip "$root/wordpress-plugin" noblen-corporate-core tests
( cd "$out" && sha256sum noblen-corporate-theme.zip noblen-corporate-core.zip > SHA256SUMS )
echo "Built in $out"; cat "$out/SHA256SUMS"
