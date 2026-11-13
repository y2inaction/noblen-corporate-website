#!/usr/bin/env bash
# Download a pinned WordPress release from wordpress.org and verify it against the published SHA-1 before extracting.
# Usage: scripts/fetch-wordpress.sh <version> <target-dir>
set -euo pipefail
version="${1:?version}"; dir="${2:?target dir}"
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
base="${WP_DOWNLOAD_BASE:-https://wordpress.org}"  # overridable only for testing this script
curl -fsSL -o "$tmp/wp.tar.gz" "${base}/wordpress-${version}.tar.gz"
expected="$(curl -fsSL "${base}/wordpress-${version}.tar.gz.sha1" | tr -d '[:space:]')"
actual="$(sha1sum "$tmp/wp.tar.gz" | cut -d' ' -f1)"
[ -n "$expected" ] && [ "$expected" = "$actual" ] || { echo "WordPress ${version} checksum mismatch (expected ${expected:-none}, got ${actual})"; exit 1; }
mkdir -p "$dir"; tar -xz --strip-components=1 -C "$dir" -f "$tmp/wp.tar.gz"
echo "WordPress ${version} verified (sha1 ${actual}) and extracted to ${dir}"
