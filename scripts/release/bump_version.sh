#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
VERSION="${1:-}"
PLUGIN_FILE="$ROOT_DIR/anti-spam-for-wordpress.php"
README_FILE="$ROOT_DIR/readme.txt"
POT_FILE="$ROOT_DIR/languages/anti-spam-for-wordpress.pot"
NOTES_FILE="$(mktemp)"
README_TMP="$(mktemp)"

cleanup() {
  rm -f "$NOTES_FILE" "$README_TMP"
}

trap cleanup EXIT

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Usage: $0 x.y.z" >&2
  exit 1
fi

perl -0pi -e "s/^ \\* Version: .*\$/ * Version: $VERSION/m" "$PLUGIN_FILE"
perl -0pi -e "s/^ \\* Stable tag: .*\$/ * Stable tag: $VERSION/m" "$PLUGIN_FILE"
perl -0pi -e "s/^define\\('ASFW_VERSION', '[^']*'\\);/define('ASFW_VERSION', '$VERSION');/m" "$PLUGIN_FILE"

perl -0pi -e "s/^Version: .*\$/Version: $VERSION/m" "$README_FILE"
perl -0pi -e "s/^Stable tag: .*\$/Stable tag: $VERSION/m" "$README_FILE"

if ! grep -q "^= $VERSION =$" "$README_FILE"; then
  bash "$ROOT_DIR/scripts/release/generate_release_notes.sh" "$VERSION" > "$NOTES_FILE"

  while IFS= read -r line; do
    printf '%s\n' "$line" >> "$README_TMP"

    if [ "$line" = "== Changelog ==" ]; then
      printf '\n= %s =\n' "$VERSION" >> "$README_TMP"
      cat "$NOTES_FILE" >> "$README_TMP"
      printf '\n' >> "$README_TMP"
    fi
  done < "$README_FILE"

  mv "$README_TMP" "$README_FILE"
fi

perl -0pi -e "s/Project-Id-Version: Anti Spam for WordPress [^\\\\n]*\\\\n/Project-Id-Version: Anti Spam for WordPress $VERSION\\\\n/" "$POT_FILE"

echo "Updated release metadata to $VERSION."
