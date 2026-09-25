#!/usr/bin/env bash
# Real WordPress, MariaDB, concurrent workers, and Redis in an isolated environment.
set -euo pipefail

repo_root="${ASFW_REPO_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
source "$repo_root/.wp-plugin-base/scripts/lib/wordpress_tooling.sh"
work_dir="$(mktemp -d "${TMPDIR:-/tmp}/asfw-integration.XXXXXX")"
export WP_ENV_HOME="$work_dir/environment"
tools_dir="${ASFW_WP_ENV_TOOLS:-$work_dir/tools}"
config_file="$work_dir/.wp-env.json"
redis_name="asfw-integration-redis-$(basename "$work_dir" | tr '[:upper:]' '[:lower:]')"
redis_started=false
environment_started=false

cleanup() {
  local status=$?
  trap - EXIT INT TERM
  if [ "$status" -ne 0 ] && [ "$environment_started" = true ]; then
    local diagnostic_file
    diagnostic_file="$(mktemp "${TMPDIR:-/tmp}/asfw-integration-diagnostics.XXXXXX")"
    "$tools_dir/node_modules/.bin/wp-env" logs --config="$config_file" --no-watch >"$diagnostic_file" 2>&1 || true
    printf 'Integration failure diagnostics: %s\n' "$diagnostic_file" >&2
  fi
  local cleanup_failed=false
  if [ "$redis_started" = true ] && docker inspect "$redis_name" >/dev/null 2>&1; then
    docker stop "$redis_name" >/dev/null 2>&1 || cleanup_failed=true
    docker rm "$redis_name" >/dev/null 2>&1 || cleanup_failed=true
  fi
  if [ "$environment_started" = true ]; then
    "$tools_dir/node_modules/.bin/wp-env" cleanup --config="$config_file" --force >"$work_dir/cleanup.log" 2>&1 || cleanup_failed=true
  fi
  if [ "$cleanup_failed" = true ]; then
    printf 'Cleanup failed; retained this run’s configuration and logs at %s\n' "$work_dir" >&2
    if [ "$status" -eq 0 ]; then status=1; fi
  else
    rm -rf "$work_dir"
  fi
  exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [ ! -x "$tools_dir/node_modules/.bin/wp-env" ]; then
  mkdir -p "$tools_dir"
  wp_plugin_base_install_wordpress_env "$tools_dir"
fi
node - "$repo_root" "$config_file" <<'JS'
const fs = require('node:fs');
const [repo, config] = process.argv.slice(2);
fs.writeFileSync(config, JSON.stringify({
  plugins: [repo],
  multisite: true,
  testsEnvironment: false,
  port: 26000 + Math.floor(Math.random() * 3000),
  autoPort: true,
  config: { ASFW_INTEGRATION_TESTS: true, WP_DEBUG: true, WP_REDIS_HOST: 'asfw-integration-redis', WP_REDIS_TIMEOUT: 1, WP_REDIS_READ_TIMEOUT: 1, WP_REDIS_MAXTTL: 900 },
}, null, 2));
JS
wp_env() { "$tools_dir/node_modules/.bin/wp-env" "$@" --config="$config_file"; }
wp_cli() { "$tools_dir/node_modules/.bin/wp-env" run cli --config="$config_file" -- wp "$@"; }

# Mark ownership before startup so partial startup failures are cleaned up too.
environment_started=true
wp_env start
wp_cli plugin deactivate anti-spam-for-wordpress
wp_cli site create --slug=before-activation --title='Pre-existing integration site'
wp_cli plugin activate anti-spam-for-wordpress --network
wp_cli core version
runner='wp-content/plugins/anti-spam-for-wordpress/tests/integration/bootstrap.php'
wp_cli eval-file "$runner" database

# Attach a new Redis container only to this freshly-created wp-env network.
compose_file=''
for environment_dir in "$WP_ENV_HOME"/wp-env-*; do
  if [ -f "$environment_dir/docker-compose.yml" ]; then
    compose_file="$environment_dir/docker-compose.yml"
    break
  fi
done
if [ -z "$compose_file" ]; then
  echo 'Could not locate this isolated wp-env compose file.' >&2
  exit 1
fi
cli_id="$(docker compose -f "$compose_file" ps -q cli)"
network_name="$(docker inspect --format '{{range $name, $value := .NetworkSettings.Networks}}{{$name}}{{end}}' "$cli_id")"
redis_started=true
docker run -d --name "$redis_name" --network "$network_name" --network-alias asfw-integration-redis redis@sha256:bb186d083732f669da90be8b0f975a37812b15e913465bb14d845db72a4e3e08 >/dev/null
wp_cli plugin install redis-cache --version=3.0.0
wp_cli plugin verify-checksums redis-cache
wp_cli plugin activate redis-cache --network
wp_cli redis enable
wp_cli redis status
wp_cli eval-file "$runner" redis
wp_cli eval-file "$runner" uninstall
printf '%s\n' 'All real WordPress, concurrent database, Redis, multisite, schema recovery, and uninstall checks passed.'
