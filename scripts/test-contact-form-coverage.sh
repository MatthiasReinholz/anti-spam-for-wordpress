#!/usr/bin/env bash
# Actual Contact Form 7 lifecycle on an isolated temporary WordPress install.
set -euo pipefail
repo_root="$(cd "$(dirname "$0")/.." && pwd)"
provider_source="${ASFW_CF7_SOURCE:?Set ASFW_CF7_SOURCE to the reviewed Contact Form 7 plugin directory}"
[[ -f "$provider_source/wp-contact-form-7.php" ]] || { echo 'Contact Form 7 source is missing.' >&2; exit 1; }
provider_source="$(cd "$provider_source" && pwd)"
core_image="${ASFW_CORE_IMAGE:-wordpress@sha256:580c3954f0e099c41b993821b12eb86dcb9c693e3305d0adeea3dae8b2cbc016}"
database_image="${ASFW_DATABASE_IMAGE:-mariadb@sha256:250e1609e93c0e6aecf64bfbab2d84d5e89d2481540fe77447ff6c855c3b1873}"
owner="asfw-cf7-coverage-$$-$RANDOM"
network_id=""
database_id=""
test_id=""
test_log="$(mktemp /tmp/asfw-cf7-coverage.XXXXXX.log)"
cleanup_container() {
  local container_id="$1"
  [[ -z "$container_id" ]] && return 0
  local actual_owner
  actual_owner="$(docker inspect --format '{{index .Config.Labels "asfw.fixture.owner"}}' "$container_id")" || return 1
  [[ "$actual_owner" == "$owner" ]] || { echo "Refusing to remove an unowned fixture container: $container_id" >&2; return 1; }
  docker rm -fv "$container_id" >/dev/null
}
cleanup() {
  local status=$?
  trap - EXIT
  cleanup_container "$test_id" || status=1
  cleanup_container "$database_id" || status=1
  if [[ -n "$network_id" ]]; then
    local actual_owner
    actual_owner="$(docker network inspect --format '{{index .Labels "asfw.fixture.owner"}}' "$network_id")" || status=1
    if [[ "$actual_owner" == "$owner" ]]; then
      docker network rm "$network_id" >/dev/null || status=1
    else
      echo "Refusing to remove an unowned fixture network: $network_id" >&2
      status=1
    fi
  fi
  echo "Contact Form 7 coverage log preserved: $test_log" >&2
  exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
network_id="$(docker network create --internal --label "asfw.fixture.owner=$owner" "$owner")"
database_id="$(docker run -d --tmpfs /var/lib/mysql --label "asfw.fixture.owner=$owner" --network "$network_id" --network-alias asfw-cf7-db \
  -e MARIADB_ROOT_PASSWORD=disposable-asfw-cf7 -e MARIADB_DATABASE=asfw_cf7 "$database_image")"
ready=false
for attempt in {1..30}; do
  if docker exec "$database_id" mariadb -h127.0.0.1 -uroot -pdisposable-asfw-cf7 asfw_cf7 -e 'SELECT 1' >/dev/null 2>&1; then ready=true; break; fi
  sleep 1
done
[[ "$ready" == true ]] || { echo 'Disposable CF7 database failed to start.' >&2; exit 1; }
test_id="$(docker create --label "asfw.fixture.owner=$owner" --network "$network_id" -e ASFW_CF7_DISPOSABLE=true \
  -e WP_TEST_CORE_ROOT=/usr/src/wordpress -e ASFW_CF7_ROOT=/provider \
  -v "$repo_root:/plugin:ro" -v "$provider_source:/provider:ro" \
  --entrypoint php "$core_image" /plugin/tests/integration/contact-form-coverage.php)"
docker start -a "$test_id" 2>&1 | tee -a "$test_log"
test_exit="$(docker inspect --format '{{.State.ExitCode}}' "$test_id")"
[[ "$test_exit" == 0 ]] || { echo "The CF7 fixture exited with $test_exit." >&2; exit 1; }
grep -Fxq 'Real Contact Form 7 ASFW lifecycle coverage passed.' "$test_log" || {
  echo 'The CF7 fixture did not reach its completion assertion.' >&2; exit 1;
}
