#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PLUGIN_FILE="$ROOT_DIR/anti-spam-for-wordpress.php"
README_FILE="$ROOT_DIR/readme.txt"
EXPECTED_TAG="${1:-}"

trim() {
  local value="$1"
  value="${value#"${value%%[![:space:]]*}"}"
  value="${value%"${value##*[![:space:]]}"}"
  printf '%s' "$value"
}

read_header_value() {
  local file="$1"
  local label="$2"
  local value

  value="$(sed -n "s/^ \\* ${label}: //p" "$file" | head -n 1)"
  if [ -z "$value" ]; then
    value="$(sed -n "s/^${label}: //p" "$file" | head -n 1)"
  fi

  trim "$value"
}

PLUGIN_VERSION="$(read_header_value "$PLUGIN_FILE" 'Version')"
PLUGIN_STABLE_TAG="$(read_header_value "$PLUGIN_FILE" 'Stable tag')"
README_VERSION="$(read_header_value "$README_FILE" 'Version')"
README_STABLE_TAG="$(read_header_value "$README_FILE" 'Stable tag')"
CONSTANT_VERSION="$(sed -n "s/^define('ASFW_VERSION', '\([^']*\)');$/\1/p" "$PLUGIN_FILE" | head -n 1)"

if [ -z "$PLUGIN_VERSION" ] || [ -z "$PLUGIN_STABLE_TAG" ] || [ -z "$README_VERSION" ] || [ -z "$README_STABLE_TAG" ] || [ -z "$CONSTANT_VERSION" ]; then
  echo "Unable to read one or more version values." >&2
  exit 1
fi

VALUES=(
  "$PLUGIN_VERSION"
  "$PLUGIN_STABLE_TAG"
  "$README_VERSION"
  "$README_STABLE_TAG"
  "$CONSTANT_VERSION"
)

REFERENCE_VERSION="${VALUES[0]}"

for value in "${VALUES[@]}"; do
  if [ "$value" != "$REFERENCE_VERSION" ]; then
    echo "Version mismatch detected:" >&2
    echo "  plugin Version:     $PLUGIN_VERSION" >&2
    echo "  plugin Stable tag:  $PLUGIN_STABLE_TAG" >&2
    echo "  readme Version:     $README_VERSION" >&2
    echo "  readme Stable tag:  $README_STABLE_TAG" >&2
    echo "  ASFW_VERSION:       $CONSTANT_VERSION" >&2
    exit 1
  fi
done

if [ -n "$EXPECTED_TAG" ] && [ "$EXPECTED_TAG" != "$REFERENCE_VERSION" ]; then
  echo "Tag ${EXPECTED_TAG} does not match plugin version ${REFERENCE_VERSION}." >&2
  exit 1
fi

echo "Verified version ${REFERENCE_VERSION}."
