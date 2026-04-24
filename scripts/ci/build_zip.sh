#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
bash "$ROOT_DIR/.wp-plugin-base/scripts/ci/build_zip.sh" "$@"

# shellcheck source=../../.wp-plugin-base/scripts/lib/load_config.sh
. "$ROOT_DIR/.wp-plugin-base/scripts/lib/load_config.sh"
wp_plugin_base_load_config "${1:-}"

ZIP_PATH="$ROOT_DIR/dist/$ZIP_FILE"
if command -v unzip >/dev/null 2>&1 && [ -f "$ZIP_PATH" ]; then
	forbidden_paths="$(
		unzip -Z1 "$ZIP_PATH" | grep -E "^${PLUGIN_SLUG}/(\\.wp-plugin-base-admin-ui|\\.wp-plugin-base-quality-pack|\\.wp-plugin-base-security-pack|node_modules|vendor)/" || true
	)"
	if [ -n "$forbidden_paths" ]; then
		echo "Package contains development-only dependency/tooling paths:" >&2
		printf '%s\n' "$forbidden_paths" >&2
		exit 1
	fi
fi
