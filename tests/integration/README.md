# Real WordPress integration tests

Run `npm ci --ignore-scripts` and `npx --no-install playwright install chromium` once from the repository root, then run `bash scripts/test-wordpress-integration.sh` from any directory with Docker, Node.js, and npm available. On Linux, use `playwright install --with-deps chromium` to install the required system libraries too. The script installs the foundation's locked WordPress environment tooling, downloads the explicit WordPress version, verifies its official checksums, and creates a separate disposable multisite environment. Defaults are WordPress 7.1.2 and PHP 8.3; override them with `ASFW_WP_VERSION` and `ASFW_PHP_VERSION`. CI runs both WordPress 6.4 / PHP 8.3 (the advertised minimum core) and WordPress 7.1.2 / PHP 8.3. Update the current-version pair deliberately when reviewing new core releases. Its configuration, database volumes, WordPress files, and Redis container belong exclusively to this run. Cleanup preserves other Docker projects and shared images. Failed runs print the path to a retained container diagnostic log. If startup stops before wp-env initializes, cleanup falls back to this run’s own Compose files and checks for surviving owned resources. Failed cleanup retains the configuration and recovery log; it never removes unrelated projects or shared images. `python3 tests/integration/test-cleanup.py` tests these failure paths without Docker.

Before storage mutation tests, Chromium signs in with the disposable wp-env administrator and exercises the production admin bundle using WordPress core scripts and controls. It loads settings through the real authenticated REST API, changes only Footer text, verifies persistence after reload, opens Events and Analytics, and restores Footer text even when a later assertion fails. It also checks independent core script handles, loaded component styles, and uncaught browser errors. It does not change login protection settings: a fresh installation's default login policy permits the normal administrator login. The browser uses this run's actual Compose port mapping, including an auto-selected replacement port, and accepts only local bindings. Browser failure fails the entire version job and follows the same owned cleanup path.

The same atomic security tests run first against MariaDB without an external object cache and then with the official Redis Object Cache plugin (version 3.0.0, checked against WordPress.org checksums before activation) and a dedicated Redis 7.4.5 container pinned by image digest. An independent PHP process must read a cached value before the Redis test mode is considered valid. `ASFW_WP_ENV_TOOLS=/path/to/tools` can reuse an existing installation of the foundation's WordPress environment tooling.

The suite checks:

- Real WordPress admin controls, authenticated settings save/reload/restore, and REST-backed Events and Analytics tabs on both core versions.

- Two workers reading the same authoritative state, then attempting consumption concurrently.
- Two complete proof verifiers synchronized immediately before the final SQL delete; exactly one succeeds.
- Four issuance workers with different contexts and user agents racing for one remaining client quota slot.
- Stale option-cache values, failed consumption writes, retry after database recovery, and exact quota counts.
- Existing and newly created network sites, unique site secrets, preservation of explicit settings on reactivation, and restoration of blog context.
- A failed schema creation that leaves the version unchanged, followed by successful recovery.
- Removal of settings, private atomic state, scheduled maintenance, and event tables on every real test site during uninstall.

Workers have bounded barriers and completion deadlines; the runner terminates and reaps them on failure. The runner also requires the test-only `ASFW_INTEGRATION_TESTS` configuration constant. Never run it against an existing WordPress installation: the schema and uninstall checks intentionally delete plugin-owned test data. The fast PHPUnit suite separately exercises network pagination beyond 100 sites.

Concurrency workers publish a JSON result and then exit. The parent waits for successful process completion before reading results, so a visible but partially written file cannot produce a false failure or hide a failing worker. Unit regressions cover slow writes, nonzero exits, missing or malformed results, and the completion deadline.
