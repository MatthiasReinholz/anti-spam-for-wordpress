"""Exercise the shell harness's failure cleanup without using Docker or a network."""

import json
import os
import shutil
from pathlib import Path
import subprocess
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[2]
STUB = r'''#!/usr/bin/env python3
import json
import os
from pathlib import Path
import re
import sys

root = Path(os.environ['ASFW_CLEANUP_FIXTURE'])
case = os.environ['ASFW_CLEANUP_CASE']
started = case.startswith('redis-') or case == 'admin-browser-failure'
arguments = sys.argv[1:]
command = Path(sys.argv[0]).name
environment = Path(os.environ['WP_ENV_HOME'])
name = 'wp-env-' + environment.parent.name + '-12345678'
project = re.sub(r'[^a-z0-9_-]', '', name.lower())
redis_name = 'asfw-integration-redis-' + environment.parent.name.lower()
with (root / 'commands.jsonl').open('a') as stream:
    stream.write(json.dumps({'command': command, 'arguments': arguments,
                            'environment': str(environment),
                            'compose_override': os.environ.get('COMPOSE_PROJECT_NAME'),
                            'compose_file': os.environ.get('COMPOSE_FILE')}) + '\n')

if command == 'node':
    if arguments[0] == '-':
        os.execv(os.environ['ASFW_CLEANUP_REAL_NODE'], [os.environ['ASFW_CLEANUP_REAL_NODE']] + arguments)
    if arguments[0].endswith('/tests/integration/admin-browser.cjs'):
        if arguments[1:] != ['0.0.0.0:26801', '7.1.2']:
            sys.exit(93)
        sys.exit(42 if case == 'admin-browser-failure' else 0)
    raise SystemExit('Unexpected node fixture invocation')
if command == 'wp-env':
    if arguments[0] == 'start':
        environment.mkdir(parents=True)
        if case not in ('before-compose', 'missing-compose-resources', 'docker-unavailable'):
            for suffix in ('', '-second') if case == 'one-down-fails' else ('',):
                directory = environment / (name + suffix)
                directory.mkdir()
                (directory / 'docker-compose.yml').write_text('services: {}\n')
            if case == 'symlink':
                (environment / (name + '-foreign')).symlink_to(root / 'foreign', target_is_directory=True)
            if case == 'foreign-project':
                unrelated = environment / 'wp-env-foreign-project'
                unrelated.mkdir()
                (unrelated / 'docker-compose.yml').write_text('services: {}\n')
        sys.exit(0 if started else 23)
    if arguments[0] == 'cleanup':
        if case == 'native-success' or started:
            sys.exit(0)
        print('Environment not initialized', file=sys.stderr)
        sys.exit(1)
    if arguments[0] == 'logs':
        print('fixture startup failure')
        sys.exit(0)
    if arguments[0] == 'run' and started:
        if 'redis-cache' in arguments and 'install' in arguments and case != 'redis-success-cleanup-failure':
            sys.exit(23)
        sys.exit(0)
elif command == 'docker':
    if arguments[:2] == ['container', 'ls'] and case.startswith('redis-'):
        if case in ('redis-enumeration-failure', 'redis-success-cleanup-failure'):
            print('Cannot connect to Docker', file=sys.stderr)
            sys.exit(1)
        print(redis_name + '-foreign')
        if case != 'redis-absent':
            print(redis_name)
        sys.exit(0)
    if case.startswith('redis-'):
        if arguments[0] == 'run':
            print('owned-redis-id')
            sys.exit(0)
        if arguments[0] == 'inspect' and '--format' in arguments:
            print(project + '_default')
            sys.exit(0)
        if arguments[0] in ('stop', 'rm'):
            if arguments[1:] != [redis_name]:
                sys.exit(92)
            if (case == 'redis-removal-failure' and arguments[0] == 'rm') or (case == 'redis-stop-failure' and arguments[0] == 'stop'):
                sys.exit(1)
            sys.exit(0)
    if arguments[0] == 'compose':
        if arguments[-3:] == ['port', 'wordpress', '80'] and started:
            compose = Path(arguments[arguments.index('-f') + 1])
            if environment not in compose.parents:
                sys.exit(94)
            print('0.0.0.0:26801')
            sys.exit(0)
        if arguments[-3:] == ['ps', '-q', 'cli'] and case.startswith('redis-'):
            print('owned-cli-id')
            sys.exit(0)
        if '--rmi' in arguments or arguments[-3:] != ['down', '--volumes', '--remove-orphans']:
            sys.exit(90)
        compose = Path(arguments[arguments.index('-f') + 1])
        expected = re.sub(r'[^a-z0-9_-]', '', compose.parent.name.lower())
        if environment not in compose.parents or arguments[arguments.index('--project-name') + 1] != expected:
            sys.exit(91)
        if case == 'one-down-fails' and not compose.parent.name.endswith('-second'):
            sys.exit(1)
        sys.exit(0)
    if arguments[0] == 'ps' or arguments[:2] in (['volume', 'ls'], ['network', 'ls']):
        if case == 'docker-unavailable':
            print('Cannot connect to Docker', file=sys.stderr)
            sys.exit(1)
        print('foreign-project')
        if case == 'missing-compose-resources' or (case == 'leftover-volume' and arguments[0] == 'volume'):
            print(project)
        sys.exit(0)
raise SystemExit('Unexpected fixture invocation: ' + repr(sys.argv))
'''


class CleanupTests(unittest.TestCase):
    def run_case(self, case, retained, expected_down=0, expected_status=23):
        with tempfile.TemporaryDirectory(prefix='asfw-cleanup-test-') as directory:
            fixture = Path(directory)
            tools = fixture / 'tools/node_modules/.bin'
            tools.mkdir(parents=True)
            temporary = fixture / 'tmp'
            temporary.mkdir()
            foreign = fixture / 'foreign'
            foreign.mkdir()
            (foreign / 'docker-compose.yml').write_text('services: {}\n')
            (foreign / 'keep').write_text('unrelated environment')
            for name in ('wp-env', 'docker', 'node'):
                target = tools / name
                target.write_text(STUB)
                target.chmod(0o755)
            environment = os.environ.copy()
            environment.update({
                'PATH': str(tools) + os.pathsep + environment['PATH'],
                'TMPDIR': str(temporary),
                'ASFW_REPO_ROOT': str(ROOT),
                'ASFW_WP_ENV_TOOLS': str(fixture / 'tools'),
                'ASFW_WP_VERSION': '7.1.2',
                'ASFW_PHP_VERSION': '8.3',
                'ASFW_CLEANUP_FIXTURE': str(fixture),
                'ASFW_CLEANUP_CASE': case,
                'ASFW_CLEANUP_REAL_NODE': shutil.which('node'),
                'COMPOSE_PROJECT_NAME': 'foreign-project',
                'COMPOSE_FILE': str(foreign / 'docker-compose.yml'),
            })
            result = subprocess.run(['bash', str(ROOT / 'scripts/test-wordpress-integration.sh')],
                                    env=environment, capture_output=True, text=True, timeout=30)
            self.assertEqual(expected_status, result.returncode, result.stderr)
            calls = [json.loads(line) for line in (fixture / 'commands.jsonl').read_text().splitlines()]
            owned = Path(calls[0]['environment']).parent
            self.assertEqual(retained, owned.exists(), result.stderr)
            self.assertEqual('unrelated environment', (foreign / 'keep').read_text())
            self.assertEqual([], list(foreign.glob('*diagnostic*')))
            self.assertTrue(all(call['compose_override'] is None and call['compose_file'] is None for call in calls))
            down = [call for call in calls if call['command'] == 'docker' and 'down' in call['arguments']]
            self.assertEqual(expected_down, len(down), calls)
            scans = [call for call in calls if call['command'] == 'docker' and 'label=com.docker.compose.project' in call['arguments']]
            self.assertEqual(0 if case == 'native-success' or case.startswith('redis-') or case == 'admin-browser-failure' else 3, len(scans), calls)
            if case.startswith('redis-'):
                browser = [index for index, call in enumerate(calls) if call['command'] == 'node' and call['arguments'][0].endswith('/admin-browser.cjs')]
                database = [index for index, call in enumerate(calls) if call['command'] == 'wp-env' and 'eval-file' in call['arguments']]
                self.assertEqual(1, len(browser), calls)
                self.assertTrue(database and browser[0] < database[0], calls)
                redis_scans = [call for call in calls if call['command'] == 'docker' and call['arguments'][:2] == ['container', 'ls']]
                self.assertEqual(1, len(redis_scans), calls)
                self.assertTrue(any(call['command'] == 'wp-env' and call['arguments'][0] == 'cleanup' for call in calls))
                removals = [call for call in calls if call['command'] == 'docker' and call['arguments'][0] in ('stop', 'rm')]
                self.assertEqual(0 if case in ('redis-enumeration-failure', 'redis-success-cleanup-failure', 'redis-absent') else 2, len(removals), calls)
                self.assertTrue(all(call['arguments'][1:] == ['asfw-integration-redis-' + owned.name.lower()] for call in removals))
            self.assertTrue(all('prune' not in call['arguments'] and '--rmi' not in call['arguments'] for call in calls))
            self.assertEqual(0 if case == 'redis-success-cleanup-failure' else 1,
                             len(list(temporary.glob('asfw-integration-diagnostics.*'))))
            if retained:
                self.assertTrue((owned / '.wp-env.json').exists())
                self.assertTrue((owned / 'cleanup.log').exists())
                self.assertIn('Cleanup failed', result.stderr)
            else:
                self.assertNotIn('Cleanup failed', result.stderr)
            return calls

    def test_partial_startup_cleans_only_the_owned_compose_project(self):
        self.run_case('partial', retained=False, expected_down=1)

    def test_failure_before_compose_is_clean_after_successful_resource_scan(self):
        self.run_case('before-compose', retained=False)

    def test_missing_compose_with_owned_resources_retains_configuration(self):
        self.run_case('missing-compose-resources', retained=True)

    def test_docker_outage_is_not_mistaken_for_absent_resources(self):
        self.run_case('docker-unavailable', retained=True)

    def test_cleanup_attempts_all_owned_projects_after_one_down_fails(self):
        self.run_case('one-down-fails', retained=True, expected_down=2)

    def test_surviving_owned_volume_retains_configuration(self):
        self.run_case('leftover-volume', retained=True, expected_down=1)

    def test_foreign_symlink_is_not_followed(self):
        self.run_case('symlink', retained=True, expected_down=1)

    def test_unexpected_project_directory_is_not_cleaned(self):
        self.run_case('foreign-project', retained=True, expected_down=1)

    def test_successful_native_cleanup_does_not_need_the_fallback(self):
        calls = self.run_case('native-success', retained=False)
        self.assertFalse(any(call['command'] == 'docker' for call in calls))

    def test_redis_enumeration_failure_retains_configuration_despite_native_success(self):
        self.run_case('redis-enumeration-failure', retained=True)

    def test_redis_removal_failure_retains_configuration(self):
        self.run_case('redis-removal-failure', retained=True)

    def test_redis_stop_failure_still_attempts_removal_and_retains_configuration(self):
        self.run_case('redis-stop-failure', retained=True)

    def test_successful_listing_can_prove_redis_is_absent(self):
        self.run_case('redis-absent', retained=False)

    def test_only_the_exact_owned_redis_name_is_stopped_and_removed(self):
        self.run_case('redis-clean', retained=False)

    def test_browser_failure_stops_before_storage_mutation_and_uses_owned_cleanup(self):
        calls = self.run_case('admin-browser-failure', retained=False, expected_status=42)
        self.assertFalse(any('eval-file' in call['arguments'] for call in calls))
        self.assertFalse(any(call['command'] == 'docker' and call['arguments'][0] == 'run' for call in calls))
        self.assertTrue(any(call['command'] == 'wp-env' and call['arguments'][0] == 'cleanup' for call in calls))

    def test_browser_origin_accepts_only_one_local_mapped_port(self):
        script = """
const assert = require('node:assert/strict');
const { originFromMappedPort } = require(process.argv[1]);
for (const mapping of ['0.0.0.0:26801', '127.0.0.1:26801', '[::1]:26801', '0.0.0.0:26801\\n[::]:26801']) {
  assert.equal(originFromMappedPort(mapping), 'http://localhost:26801');
}
for (const mapping of ['', 'example.com:26801', '192.0.2.1:26801', '0.0.0.0:0', '0.0.0.0:65536', '0.0.0.0:26801\\n[::]:26802']) {
  assert.throws(() => originFromMappedPort(mapping));
}
"""
        subprocess.run(['node', '-e', script, str(ROOT / 'tests/integration/admin-browser.cjs')], check=True, capture_output=True, text=True, timeout=10)

    def test_cleanup_failure_turns_an_otherwise_successful_run_into_failure(self):
        self.run_case('redis-success-cleanup-failure', retained=True, expected_status=1)


if __name__ == '__main__':
    unittest.main()
