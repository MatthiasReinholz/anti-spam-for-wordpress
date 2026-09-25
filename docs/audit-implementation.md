# Audit implementation record

The implementation starts from release 0.9.0, commit `8f28e4b66b857a91fceafb1efbbc47706a953e53`, verified against the latest `origin/main` on 25 September 2026. The foundation was upgraded to the published, provenance-verified v1.8.3 release at `58a1aa68acababb303eea6760923c60cbf648f10`. Unreleased upstream changes are kept in a separate foundation branch.

Ten focused reviewers re-examined the plan. Implementation and a further cross-review prioritized exploitable policy gaps, concurrency failures, lifecycle correctness, and test coverage before organizational improvements. This record describes bounded engineering changes; an audit cannot establish that a project is defect-free.

## Prioritized changes

| Priority | Engineering task | Implemented result |
| --- | --- | --- |
| P1 | Make proof consumption and quotas atomic | Database compare-and-swap state, exact one-time consumption, bounded contention retries, fail-closed storage errors, and one per-IP issuance quota across contexts, User Agents, math, delay, and proof requests. Invalid signatures cannot consume legitimate challenges. |
| P1 | Bind protection to actual provider routes | CoBlocks interception runs only when its integration is enabled and restores native state afterward. Trusted native and wpDiscuz dispatch select their effective policy; signed public fields cannot downgrade a known route. Main and inline wpDiscuz click transports verify before serialization. |
| P1 | Repair browser request and completion lifecycle | Per-form state, cancellation and generation checks, bounded observers, explicit retry, multiple-widget coordination, and renewal only after reset or settled requests. Math markup is safe to cache because issuance happens separately. |
| P1 | Provision and clean multisite state reliably | Missing-only initialization preserves configured disabled values and secrets. Network initialization uses 100-site batches with lazy repair and new-site support. Deactivation removes argument-bearing jobs. Uninstall visits every site and removes the complete owned option inventory, event tables, and security state. |
| P1 | Verify behavior against real infrastructure | Independent PHP workers test concurrent proof use and quotas against WordPress and MariaDB. The same suite verifies an actual Redis object cache, multisite activation, failed schema installation and recovery, and uninstall. CI includes the integration harness, all three browser engines, and PHP 8.0–8.5, including the advertised minimum runtime. |
| P2 | Make admin requests predictable | Views, transport, resource state, and settings drafts are separate modules. Errors require explicit retry; stale reads cannot overwrite current state; duplicate saves are blocked; edits made during a save survive its response and tab changes. |
| P2 | Make remote operations preserve valid state | Disposable feeds validate size, format, domain content, and suspicious shrinkage before replacement. Bunny updates use owned network-wide leases and reread remote state, preserve backoff history, distinguish dry runs, and reject malformed or logically unsuccessful responses. |
| P2 | Harden identity, privacy, and persistence | One explicitly selected forwarding header is trusted only behind configured proxies. IPv6 forms normalize consistently. Context punctuation is preserved. Event details receive bounded recursive privacy sanitation, including structured strings and sensitive numeric fields. Schema versions advance only after verifying columns and indexes; database and feed errors reach maintenance and CLI callers. Expired security rows drain in bounded follow-up batches. |
| P2 | Keep shared infrastructure fixes upstream | The separate foundation change adds one action-pin policy, migrations for reviewed predecessor pins, staging of migrated child workflows, dependency-update ownership, optional child PHPCS overlays, and compatible fixes for vulnerable development dependencies. The child consumes released foundation source unchanged. |
| P3 | Document and expose extension contracts | A registration hook accepts unique integration IDs. Development guidance documents ownership, server-side custom form verification, completion events, cache exclusions, migrations, proxy configuration, privacy, and test boundaries. Translation source and production admin assets are regenerated. |

## Compatibility and operations

- Existing public facade signatures remain available. Names containing `transient` now identify logical keys; callers must use the facade rather than assume WordPress transient storage.
- Previously issued transient-based tokens need a fresh verification after upgrade. Frontend retry and completion paths obtain replacement tokens.
- Zero remains an explicit unlimited quota. All issuance otherwise shares one per-IP bucket. Shared public IPs should use limits appropriate to their legitimate traffic.
- WordPress cron must run regularly to initialize large networks and drain expired security rows. Deactivation and uninstall remove both maintenance and continuation jobs.
- Bunny coordination covers one WordPress network. Independent installations or external editors need dedicated remote lists or external coordination.
- New provider regressions use the production browser scripts and real jQuery. They do not constitute live end-to-end tests of every supported third-party plugin version.

## Validation evidence

Local validation passed the managed repository and package checks, the WordPress quality and security packs, PHPStan, PHP coding standards, translation-catalog checks, and clean admin production builds. The PHP suite has 329 tests and 2,298 assertions. The separate real WordPress suite passed 69 assertions using WordPress 7.1.2, MariaDB, and Redis, with its owned environment removed afterward. Root and child-admin npm audits reported zero known vulnerabilities, including development dependencies.

The public browser suite passed all 34 Chromium cases. WebKit passed the 33-case suite and the final added configuration regression separately. The eight mounted React admin tests passed after installing the updated dependency tree. Firefox could not start in the local macOS environment because its sandbox/framebuffer initialization failed; the repository CI runs it on Linux.

WordPress Plugin Check reported zero errors and seven warnings: three existing restricted-name/slug notices, the deliberate bundled-translation loader, and three warnings in managed foundation runtime code about shared prefixes or dynamic hooks. These are recorded rather than hidden. Renaming the published plugin or removing its bundled-translation behavior would change existing contracts and is not part of this hardening.

The quality-pack PHPStan process initially exhausted the host's 128 MB default. The project now explicitly allocates a 2 GB analysis limit; the complete quality pack passes with that setting.

## Shared foundation follow-up

The foundation fixes must be reviewed and released through its existing release process, then consumed through this project's verified updater. Do not relabel unpublished source as v1.8.3. Until that release, this project intentionally retains one managed PHPCS overlay include, documented in [development.md](development.md), and manually migrated its reviewed browser workflow pins. The published foundation's development-only WordPress environment dependency still needs the upstream patched release; the child-owned dependency locks have been updated separately.

Copyable handoff for the upstream maintainers:

> Please review the foundation hardening branch associated with this audit. It centralizes approved action pins, migrates only explicitly reviewed predecessor pins while preserving child workflow content, stages those exact migrated workflow paths, assigns foundation-owned action updates to the foundation and child npm updates to children, supports child PHPCS overlays, and patches compatible development dependencies. Please release it through the existing verified release process after CI passes, then update Anti Spam for WordPress through its verified foundation updater. The child currently uses genuine v1.8.3 source and documents its temporary PHPCS overlay include; no unpublished patch is disguised as that release.
