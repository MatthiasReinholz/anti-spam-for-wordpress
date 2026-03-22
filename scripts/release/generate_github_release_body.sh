#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
README_FILE="$ROOT_DIR/readme.txt"
VERSION="${1:-}"

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Usage: $0 x.y.z" >&2
  exit 1
fi

section="$(
  awk -v version="$VERSION" '
    $0 == "= " version " =" {
      in_section=1
      next
    }
    in_section && /^= .* =$/ {
      exit
    }
    in_section {
      print
    }
  ' "$README_FILE"
)"

if [ -z "$section" ]; then
  echo "Could not find changelog entry for version $VERSION in readme.txt." >&2
  exit 1
fi

cat <<EOF
# Anti Spam for WordPress ${VERSION}

## Changes

${section}

## Install

Use \`anti-spam-for-wordpress-plugin.zip\` below to install or update the plugin in WordPress.

GitHub also provides automatic source code archives for each release. Those archives are repository snapshots and are not the recommended plugin install package.
EOF
