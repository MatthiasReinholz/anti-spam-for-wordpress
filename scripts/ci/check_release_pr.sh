#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

exec bash "$ROOT_DIR/.wp-plugin-base/scripts/ci/check_release_pr.sh" "$@"
