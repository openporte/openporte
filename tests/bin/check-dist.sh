#!/usr/bin/env bash
#
# check-dist.sh — verify the dist archive contains only git-tracked files.
#
# wp dist-archive builds the release zip from the working tree filtered by
# .distignore, NOT from git (see docs/release-preparation.md, Phase 0). A
# stray local directory (a locally-installed tool's state dir, an editor
# artifact, anything not in .distignore) can leak into the published zip
# without ever showing up in `git status` or `git diff` — especially when
# it's only ignored by a *global* gitignore, which .distignore can't see.
#
# This accepts an existing archive or builds a throwaway archive and flags any file inside it that git
# doesn't track. On a clean tree it prints nothing else and exits 0.
#
# Caveat: this assumes everything shippable is git-tracked (true today —
# vendor/ is generated but already excluded via .distignore). If the repo
# ever stops tracking generated-but-shipped files (e.g. languages/*.mo, see
# https://github.com/openporte/openporte/issues/11), this script will need
# an allowlist for those paths.
#
# Usage: ./tests/bin/check-dist.sh [archive.zip]   (run from the repo root)

set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT
if (( $# > 1 )); then
  echo "Usage: $0 [archive.zip]" >&2
  exit 2
fi
if (( $# )); then
  zip=$1
else
  zip="$tmpdir/dist-check.zip"
  wp dist-archive . "$zip" >/dev/null
fi

unzip -tq "$zip" >/dev/null
unzip -Z1 "$zip" > "$tmpdir/entries"
prefix=$(sed -n '1s@/.*@@p' "$tmpdir/entries")
awk -v prefix="$prefix/" '
  index($0, prefix) != 1 { exit 1 }
  $0 !~ /\/$/ { print substr($0, length(prefix) + 1) }
' "$tmpdir/entries" > "$tmpdir/files"
test -s "$tmpdir/files"
export LC_ALL=C
sort "$tmpdir/files" > "$tmpdir/sorted"
git ls-files | sort > "$tmpdir/tracked"
extra=$(comm -23 "$tmpdir/sorted" "$tmpdir/tracked")

if [ -n "$extra" ]; then
  echo "Files in the dist archive that git doesn't track:" >&2
  while IFS= read -r path; do
    printf '  %s\n' "$path" >&2
  done <<<"$extra"
  exit 1
fi

echo "OK: dist archive contains only git-tracked files ($(wc -l < "$tmpdir/files") files)."
