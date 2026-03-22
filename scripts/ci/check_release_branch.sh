#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
README_FILE="$ROOT_DIR/readme.txt"
BRANCH_NAME="${1:-}"

if [ -z "$BRANCH_NAME" ]; then
  echo "Usage: $0 branch-name" >&2
  exit 1
fi

if [[ ! "$BRANCH_NAME" =~ ^(release|hotfix)/([0-9]+\.[0-9]+\.[0-9]+)$ ]]; then
  echo "Skipping release branch validation for $BRANCH_NAME."
  exit 0
fi

VERSION="${BASH_REMATCH[2]}"

bash "$ROOT_DIR/scripts/ci/check_versions.sh" "$VERSION"

if ! grep -q "^= $VERSION =$" "$README_FILE"; then
  echo "readme.txt is missing a changelog section for version $VERSION." >&2
  exit 1
fi

if grep -q '^\* TODO: finalize release notes\.$' "$README_FILE"; then
  echo "readme.txt still contains the release-notes placeholder. Replace it before merging the release PR." >&2
  exit 1
fi

echo "Verified release branch ${BRANCH_NAME}."
