#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
VERSION="${1:-}"
PLUGIN_FILE="$ROOT_DIR/anti-spam-for-wordpress.php"
README_FILE="$ROOT_DIR/readme.txt"
POT_FILE="$ROOT_DIR/languages/anti-spam-for-wordpress.pot"

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
  perl -0pi -e "s/== Changelog ==\n\n/== Changelog ==\n\n= $VERSION =\n* TODO: finalize release notes.\n\n/" "$README_FILE"
fi

perl -0pi -e "s/Project-Id-Version: Anti Spam for WordPress [^\\\\n]*\\\\n/Project-Id-Version: Anti Spam for WordPress $VERSION\\\\n/" "$POT_FILE"

echo "Updated release metadata to $VERSION."
