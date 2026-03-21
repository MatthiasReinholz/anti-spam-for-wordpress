#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

while IFS= read -r file; do
  php -l "$file"
done < <(find "$ROOT_DIR" \
  -path "$ROOT_DIR/.git" -prune -o \
  -path "$ROOT_DIR/.github" -prune -o \
  -path "$ROOT_DIR/dist" -prune -o \
  -name '*.php' -print | sort)
