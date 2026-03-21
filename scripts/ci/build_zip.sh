#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PLUGIN_SLUG="anti-spam-for-wordpress"
DIST_DIR="$ROOT_DIR/dist"
STAGE_ROOT="$DIST_DIR/package"
STAGE_DIR="$STAGE_ROOT/$PLUGIN_SLUG"
ZIP_PATH="$DIST_DIR/$PLUGIN_SLUG.zip"

PACKAGE_ITEMS=(
  admin
  includes
  integrations
  languages
  public
  anti-spam-for-wordpress.php
  readme.txt
  README.md
  LICENSE
)

rm -rf "$STAGE_ROOT" "$ZIP_PATH"
mkdir -p "$STAGE_DIR"

for item in "${PACKAGE_ITEMS[@]}"; do
  if [ ! -e "$ROOT_DIR/$item" ]; then
    echo "Missing package item: $item" >&2
    exit 1
  fi

  cp -R "$ROOT_DIR/$item" "$STAGE_DIR/"
done

if [ ! -f "$STAGE_DIR/anti-spam-for-wordpress.php" ]; then
  echo "Package is missing the main plugin file." >&2
  exit 1
fi

if [ ! -f "$STAGE_DIR/public/asfw-widget.js" ] || [ ! -f "$STAGE_DIR/public/asfw-widget.css" ]; then
  echo "Package is missing required widget assets." >&2
  exit 1
fi

(cd "$STAGE_ROOT" && zip -qr "$ZIP_PATH" "$PLUGIN_SLUG")

if [ ! -f "$ZIP_PATH" ]; then
  echo "Failed to create package zip." >&2
  exit 1
fi

if command -v unzip >/dev/null 2>&1; then
  if ! unzip -Z1 "$ZIP_PATH" | grep -q "^$PLUGIN_SLUG/anti-spam-for-wordpress.php$"; then
    echo "Zip archive does not contain the expected plugin root structure." >&2
    exit 1
  fi
fi

echo "Created $ZIP_PATH"
