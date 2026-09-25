# Real WordPress integration tests

Run `bash scripts/test-wordpress-integration.sh` from any directory with Docker, Node.js, and npm available. The script installs the foundation's locked WordPress environment tooling, downloads the current stable WordPress release, and creates a separate disposable multisite environment. Its configuration, database volumes, WordPress files, and Redis container belong exclusively to this run. Cleanup preserves other Docker projects and shared images. Failed runs print the path to a retained container diagnostic log.

The same atomic security tests run first against MariaDB without an external object cache and then with the official Redis Object Cache plugin (version 3.0.0, checked against WordPress.org checksums before activation) and a dedicated Redis 7.4.5 container pinned by image digest. An independent PHP process must read a cached value before the Redis test mode is considered valid. `ASFW_WP_ENV_TOOLS=/path/to/tools` can reuse an existing installation of the foundation's WordPress environment tooling.

The suite checks:

- Two workers reading the same authoritative state, then attempting consumption concurrently.
- Two complete proof verifiers synchronized immediately before the final SQL delete; exactly one succeeds.
- Four issuance workers with different contexts and user agents racing for one remaining client quota slot.
- Stale option-cache values, failed consumption writes, retry after database recovery, and exact quota counts.
- Existing and newly created network sites, unique site secrets, preservation of explicit settings on reactivation, and restoration of blog context.
- A failed schema creation that leaves the version unchanged, followed by successful recovery.
- Removal of settings, private atomic state, scheduled maintenance, and event tables on every real test site during uninstall.

Workers have bounded barriers and completion deadlines; the runner terminates and reaps them on failure. The runner also requires the test-only `ASFW_INTEGRATION_TESTS` configuration constant. Never run it against an existing WordPress installation: the schema and uninstall checks intentionally delete plugin-owned test data. The fast PHPUnit suite separately exercises network pagination beyond 100 sites.
