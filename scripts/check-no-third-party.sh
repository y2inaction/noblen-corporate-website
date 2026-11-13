#!/usr/bin/env bash
# C18-002: static scan of theme and plugin sources for external hosts in markup, stylesheets, scripts and fonts.
# Allowed: XML/schema namespace identifiers (w3.org), the exact bare string https://schema.org (the JSON-LD @context vocabulary
# name, never requested; any other schema.org URL is still flagged) and the repository's own documentation links in headers.
# The browser smoke tests separately fail on any request that leaves the site's own host.
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
hits=$(grep -rnoE "(https?:)?//[A-Za-z0-9.-]+\.[A-Za-z]{2,}[^\"' )]*" "$root/wordpress-theme" "$root/wordpress-plugin" \
  --include='*.php' --include='*.css' --include='*.js' --include='*.html' --include='*.svg' \
  --exclude-dir=tests --exclude-dir=node_modules \
  | grep -vE "w3\.org|github\.com/y2inaction/noblen-corporate-website|example\.(test|com)" \
  | grep -vE "^[^:]+:[0-9]+:https://schema\.org$" || true)
if [ -n "$hits" ]; then echo "External hosts found:"; echo "$hits"; exit 1; fi
echo "PASS: no external hosts referenced in theme or plugin sources"
