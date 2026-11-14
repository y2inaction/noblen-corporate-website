#!/usr/bin/env bash
# Build installable ZIPs for the theme and the core plugin, plus the complete installation bundle. Excludes tests and dev files.
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
# Complete installation bundle: both ZIPs, the installation guide and build information, so one download is enough.
theme_version="$(sed -n 's/^Version: *//p' "$root/wordpress-theme/noblen-corporate/style.css" | head -1)"
core_version="$(sed -n 's/^ \* Version: *//p' "$root/wordpress-plugin/noblen-corporate-core/noblen-corporate-core.php" | head -1)"
commit="$(git -C "$root" rev-parse HEAD 2>/dev/null || echo unknown)"
git -C "$root" diff --quiet HEAD 2>/dev/null || commit="$commit (uncommitted changes present)"
rm -rf "$stage/bundle"; mkdir -p "$stage/bundle/noblen-corporate-website"
b="$stage/bundle/noblen-corporate-website"
cp "$out/noblen-corporate-theme.zip" "$out/noblen-corporate-core.zip" "$b/"
cp "$root/documentation/INSTALL-GUIDE.md" "$b/INSTALL-GUIDE.md"
cat > "$b/BUILD-INFO.txt" <<INFO
Noblen corporate website, installation bundle
Theme:  Noblen Corporate ${theme_version} (noblen-corporate-theme.zip)
Plugin: Noblen Corporate Core ${core_version} (noblen-corporate-core.zip)
Built from commit: ${commit}
Requires: WordPress 6.4 or later, PHP 8.3 or later.
Install: read INSTALL-GUIDE.md. Upload the theme ZIP under Appearance, Themes, then the plugin ZIP under Plugins, activate both, then open Noblen, Website Setup.
INFO
( cd "$b" && sha256sum noblen-corporate-theme.zip noblen-corporate-core.zip INSTALL-GUIDE.md BUILD-INFO.txt > SHA256SUMS )
find "$b" -exec touch -h -d "@$epoch" {} +
rm -f "$out/noblen-corporate-website-bundle.zip"
( cd "$stage/bundle" && find noblen-corporate-website -print | LC_ALL=C sort | zip -X -q "$out/noblen-corporate-website-bundle.zip" -@ )
cp "$root/documentation/INSTALL-GUIDE.md" "$out/INSTALL-GUIDE.md"
( cd "$out" && sha256sum noblen-corporate-theme.zip noblen-corporate-core.zip noblen-corporate-website-bundle.zip INSTALL-GUIDE.md > SHA256SUMS )
echo "Built in $out"; cat "$out/SHA256SUMS"
