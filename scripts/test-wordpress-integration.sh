#!/usr/bin/env bash
# Real WordPress, MariaDB, concurrent workers, and Redis in an isolated environment.
set -euo pipefail

repo_root="${ASFW_REPO_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
wordpress_version="${ASFW_WP_VERSION:-7.1.2}"
php_version="${ASFW_PHP_VERSION:-8.3}"
if ! [[ "$wordpress_version" =~ ^[0-9]+\.[0-9]+(\.[0-9]+)?$ ]] || ! [[ "$php_version" =~ ^[0-9]+\.[0-9]+$ ]]; then
  echo 'ASFW_WP_VERSION and ASFW_PHP_VERSION must identify explicit stable versions.' >&2
  exit 1
fi
# shellcheck source=../.wp-plugin-base/scripts/lib/wordpress_tooling.sh
source "$repo_root/.wp-plugin-base/scripts/lib/wordpress_tooling.sh"
work_dir="$(mktemp -d "${TMPDIR:-/tmp}/asfw-integration.XXXXXX")"
export WP_ENV_HOME="$work_dir/environment"
# These user overrides could otherwise attach this disposable run to another project.
unset COMPOSE_PROJECT_NAME COMPOSE_FILE
tools_dir="${ASFW_WP_ENV_TOOLS:-$work_dir/tools}"
config_file="$work_dir/.wp-env.json"
redis_name="asfw-integration-redis-$(basename "$work_dir" | tr '[:upper:]' '[:lower:]')"
redis_started=false
environment_started=false

cleanup_partial_environment() {
  local environment_dir compose_path project_name resource_projects resource_project resource_kind
  local fallback_failed=false
  local project_prefix
  project_prefix="wp-env-$(basename "$work_dir" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-z0-9_-')-"

  # wp-env cannot detect its runtime when startup failed before initialization.
  # Only use compose files inside this run's freshly-created private directory.
  for environment_dir in "$WP_ENV_HOME"/wp-env-*; do
    [ -e "$environment_dir" ] || [ -L "$environment_dir" ] || continue
    compose_path="$environment_dir/docker-compose.yml"
    if [ -L "$environment_dir" ] || [ -L "$compose_path" ]; then
      printf 'Refusing cleanup through a symlink: %s\n' "$environment_dir" >&2
      fallback_failed=true
      continue
    fi
    [ -f "$compose_path" ] || continue
    project_name="$(basename "$environment_dir" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-z0-9_-')"
    if [[ "$project_name" != "$project_prefix"* ]]; then
      printf 'Unexpected compose project in this run: %s\n' "$environment_dir" >&2
      fallback_failed=true
      continue
    fi
    docker compose --project-name "$project_name" --project-directory "$environment_dir" -f "$compose_path" \
      down --volumes --remove-orphans || fallback_failed=true
  done

  # This also handles failure before any compose file was written. Listing must
  # succeed: an unavailable Docker daemon is not evidence that resources are gone.
  for resource_kind in container volume network; do
    case "$resource_kind" in
      container) resource_projects="$(docker ps -a --filter label=com.docker.compose.project --format '{{.Label "com.docker.compose.project"}}')" || { fallback_failed=true; continue; } ;;
      volume) resource_projects="$(docker volume ls --filter label=com.docker.compose.project --format '{{.Label "com.docker.compose.project"}}')" || { fallback_failed=true; continue; } ;;
      network) resource_projects="$(docker network ls --filter label=com.docker.compose.project --format '{{.Label "com.docker.compose.project"}}')" || { fallback_failed=true; continue; } ;;
    esac
    while IFS= read -r resource_project; do
      if [[ "$resource_project" == "$project_prefix"* ]]; then
        printf 'Owned %s resources remain for %s\n' "$resource_kind" "$resource_project" >&2
        fallback_failed=true
      fi
    done <<< "$resource_projects"
  done
  [ "$fallback_failed" = false ]
}

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
  if [ "$redis_started" = true ]; then
    local redis_containers redis_container
    # Only a successful listing can establish absence; inspect also fails when
    # Docker is unavailable, which must preserve this run's recovery files.
    if redis_containers="$(docker container ls --all --filter "name=$redis_name" --format '{{.Names}}')"; then
      while IFS= read -r redis_container; do
        [ "$redis_container" = "$redis_name" ] || continue
        docker stop "$redis_name" >/dev/null 2>&1 || cleanup_failed=true
        docker rm "$redis_name" >/dev/null 2>&1 || cleanup_failed=true
      done <<< "$redis_containers"
    else
      cleanup_failed=true
    fi
  fi
  if [ "$environment_started" = true ]; then
    if ! "$tools_dir/node_modules/.bin/wp-env" cleanup --config="$config_file" --force >"$work_dir/cleanup.log" 2>&1; then
      cleanup_partial_environment >>"$work_dir/cleanup.log" 2>&1 || cleanup_failed=true
    fi
  fi
  if [ "$cleanup_failed" = true ]; then
    printf "Cleanup failed; retained this run's configuration and logs at %s\n" "$work_dir" >&2
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
node - "$repo_root" "$config_file" "$wordpress_version" "$php_version" <<'JS'
const fs = require('node:fs');
const [repo, config, wordpressVersion, phpVersion] = process.argv.slice(2);
fs.writeFileSync(config, JSON.stringify({
  core: `https://wordpress.org/wordpress-${wordpressVersion}.zip`,
  phpVersion,
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
wp_cli core verify-checksums --version="$wordpress_version"
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
