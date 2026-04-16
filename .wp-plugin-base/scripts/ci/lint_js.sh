#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/load_config.sh
. "$SCRIPT_DIR/../lib/load_config.sh"
# shellcheck source=../lib/require_tools.sh
. "$SCRIPT_DIR/../lib/require_tools.sh"

wp_plugin_base_require_commands "JavaScript syntax validation" node

wp_plugin_base_load_config "${1:-}"

while IFS= read -r file; do
  node --check "$file"
done < <(find "$ROOT_DIR" \
  -type d \( \
    -name '.git' -o \
    -name '.github' -o \
    -name '.wp-plugin-base' -o \
    -name '.wp-plugin-base-quality-pack' -o \
    -name '.wp-plugin-base-security-pack' -o \
    -name 'dist' -o \
    -name 'node_modules' -o \
    -name 'vendor' \
  \) -prune -o \
  -name '*.js' -print | sort)
