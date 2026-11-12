#!/usr/bin/env bash
# C18-002: static scan of theme and plugin sources for external hosts in markup, stylesheets, scripts and fonts.
# Allowed: XML/schema namespace identifiers (w3.org) and the repository's own documentation links in headers.
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
hits=$(grep -rnoE "(https?:)?//[A-Za-z0-9.-]+\.[A-Za-z]{2,}[^\"' )]*" "$root/wordpress-theme" "$root/wordpress-plugin" \
  --include='*.php' --include='*.css' --include='*.js' --include='*.html' --include='*.svg' \
  --exclude-dir=tests --exclude-dir=node_modules \
  | grep -vE "w3\.org|github\.com/y2inaction/noblen-corporate-website|example\.(test|com)" || true)
if [ -n "$hits" ]; then echo "External hosts found:"; echo "$hits"; exit 1; fi
echo "PASS: no external hosts referenced in theme or plugin sources"
