#!/usr/bin/env bash
# Compare the files of a downloaded Classic Editor package with the plugin's official development repository at the
# commit recorded in the pin. Evidence for a human reviewer: it does not decide anything, and never edits the pin.
# Usage: scripts/compare-classic-editor-source.sh <package.zip> [pin-file]
# Exit 0: every compared file is identical. Exit 4: at least one differs. Exit 2: could not compare.
set -u
ZIP="${1:?package zip}"; PIN="${2:-$(dirname "$0")/../tests-e2e/wp-env/classic-editor.pin.json}"
j() { node -e "const p=JSON.parse(require('fs').readFileSync(process.argv[1],'utf8'));console.log(process.argv[2].split('.').reduce((o,k)=>o&&o[k],p)||'')" "$PIN" "$1"; }
REPO="$(j identified_from.repository)"; COMMIT="$(j identified_from.commit)"; SLUG="$(j slug)"
[ -n "$REPO" ] && [ -n "$COMMIT" ] || { echo "pin lacks identified_from.repository/commit"; exit 2; }
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
git init -q "$T/src" && git -C "$T/src" fetch -q --depth 1 "$REPO.git" "$COMMIT" 2>/dev/null || { echo "could not fetch $COMMIT from $REPO"; exit 2; }
unzip -q "$ZIP" -d "$T/pkg" || { echo "could not unzip $ZIP"; exit 2; }
diffs=0; compared=0
echo "Comparing package files with $REPO at $COMMIT"
for f in "$SLUG.php" readme.txt LICENSE.md js/block-editor-plugin.js scripts/post.js scripts/post.min.js; do
  a="$(git -C "$T/src" show "$COMMIT:$f" 2>/dev/null | sha256sum | cut -d' ' -f1)"
  [ -f "$T/pkg/$SLUG/$f" ] && b="$(sha256sum "$T/pkg/$SLUG/$f" | cut -d' ' -f1)" || b="(absent in package)"
  if [ "$a" = "$b" ]; then echo "identical  $f"; else echo "DIFFERENT  $f  repo=$a package=$b"; diffs=$((diffs+1)); fi
  compared=$((compared+1))
done
extra="$(cd "$T/pkg/$SLUG" && find . -type f | sed 's#^\./##' | sort | while read -r f; do git -C "$T/src" cat-file -e "$COMMIT:$f" 2>/dev/null || echo "$f"; done)"
[ -n "$extra" ] && echo "files in the package that are not in the repository at that commit:" && echo "$extra" | sed 's/^/  /'
echo "compared $compared files, $diffs differ"
[ "$diffs" -eq 0 ]; r=$?; [ "$r" -eq 0 ] && exit 0 || exit 4
