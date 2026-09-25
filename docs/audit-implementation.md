# Audit implementation record

This work starts from plugin release 0.9.0, commit `8f28e4b66b857a91fceafb1efbbc47706a953e53`, checked against the latest `origin/main` on 25 September 2026. The shared foundation is `MatthiasReinholz/wp-plugin-base`.

The earlier ten-reviewer plan review was followed by thirteen deeper audits. Each implementation received a second review, targeted regressions, and combined validation. This record describes verified engineering improvements; no audit establishes that a project is defect-free.

## Review coverage

| Reviewer | Scope |
| --- | --- |
| 1 | Holistic completeness, documentation, supported versions, packaging |
| 2 | Atomic proof consumption, quotas, leases, database failure behavior |
| 3 | Native and WooCommerce authentication, registration, and reviews |
| 4 | Widget lifecycle, lazy loading, dynamic fields, browser races |
| 5 | Foundation integration, provenance, dependency and workflow policy |
| 6 | Admin request state, persistence, timeout, clipboard failures |
| 7 | Event queries, schema checks, migrations, initialization recovery |
| 8 | Bunny remote state, revocation, retries and local cleanup |
| 9 | Event privacy, credentials, feed memory and submission CPU bounds |
| 10 | Third-party integration hook contracts and provider compatibility |
| 11 | Runtime coexistence, settings extension contracts, option ownership |
| 12 | Real WordPress harness isolation, cleanup, packaging and release gates |
| 13 | Independent cross-review of security-sensitive fixes and failure paths |

The thirteenth reviewer reproduced two additional defects: a one-shot retention migration failure could fall through to a different default, and duplicate-heavy Bunny list responses could exhaust a 128 MiB PHP process. Both received bounded fixes and regressions. This record is a pre-release checkpoint; publication awaits the verified foundation update described below.

## Prioritized engineering changes

| Priority | Task | Result |
| --- | --- | --- |
| P1 | Make proof consumption and quotas atomic | Database compare-and-swap state, exact one-time consumption, bounded contention retries, fail-closed storage errors, and one per-IP issuance quota across contexts, User Agents, math, delay, and proof requests. Invalid signatures cannot consume a legitimate challenge. |
| P1 | Bind authentication and comment policy to trusted dispatch | Native login/reset cannot be downgraded with WooCommerce-looking URLs or public nonces. WooCommerce login policy applies only to its actual validated handler and one matching authentication call. Account registration protection does not intercept checkout or programmatic customer creation. Native product reviews, wpDiscuz, and CoBlocks retain their intended policies. |
| P1 | Correct browser lifecycle and provider retries | Per-form cancellation and generation checks, bounded observers, explicit retries, multiple-widget coordination, and renewal after settled requests. wpDiscuz verifies before serialization. Lazy loading and dynamic field names honor their documented settings. |
| P1 | Recover incomplete initialization without replacing settings | Required writes and completion markers are verified. Legacy migration stops on persistence failure, including a one-shot retention failure. Schema version 4 rechecks historical version-3 installations. Multisite initialization uses bounded batches, lazy repair, and new-site support. |
| P1 | Isolate the foundation runtime | **Pending upstream release adoption.** The upstream fix and isolated migration preflight use plugin-specific runtime classes. A prepared coexistence regression covers both load orders and overlapping operation identifiers; it will be added with the verified foundation update. |
| P1 | Exercise real storage and supported versions | Independent workers race against WordPress/MariaDB, repeat with a verified Redis object cache, and exercise multisite repair/uninstall. CI covers WordPress 6.4 and 7.1.2 on PHP 8.3, browser engines Chromium/Firefox/WebKit, and strict PHP 8.0–8.5 runtime tests. |
| P2 | Report unavailable data and failed saves honestly | Event reads expose a checked error state while preserving public return types. Admin and CLI consumers return errors instead of empty logs. Settings saves verify persisted values, preserve JSON backslashes, retain drafts after failures/timeouts, and clamp pagination before querying. |
| P2 | Bound and validate remote data | Disposable feeds and Bunny IP lists parse incrementally with byte, entry and unique-value limits. Malformed or excessive data cannot replace a good list or trigger partial remote mutations. Disposable matching builds one fresh domain lookup per submission. |
| P2 | Make Bunny revocation idempotent and retryable | Owned network-wide leases, stale-list rediscovery, validated responses, explicit dry-run state and durable backoff. Confirmed remote absence clears local counters and deduplication state; failed local cleanup remains a retryable error. |
| P2 | Improve privacy boundaries | One selected forwarding header is trusted only behind configured proxies. Recursive event sanitation covers nested JSON and credential keys within shared limits. Heuristics exclude credentials. Generated privacy text accurately describes private expiring database options. |
| P2 | Correct provider and extension contracts | Forminator's one-argument PayPal rendering hook no longer fatals. Custom settings sections and new feature sections render correctly; explicit metadata and built-in ordering remain stable. |
| P2 | Keep shared infrastructure fixes upstream | **Upstream release coordination in progress.** Reviewed foundation changes cover safe generation, authenticated source fetching, runtime isolation, immutable release recovery, action-pin policy/migration, dependency ownership/remediation, optional PHPCS overlays, and isolated tooling cleanup. The foundation task is conducting another audit before publication. |
| P3 | Improve maintenance documentation and packaging | Developer guidance covers ownership, custom server enforcement, provider completion events, cache exclusions, migrations, privacy, extension contracts and limits. Translation sources and admin production assets are rebuilt. PHPStan configuration remains outside the release ZIP. |

## Compatibility and operational limits

- Public facade signatures remain available. Names containing `transient` identify logical keys; callers must use the facade instead of assuming WordPress transient storage.
- Previously issued transient-based verification tokens need a fresh attempt after upgrade. Frontend retry/completion paths obtain replacements.
- A quota of zero explicitly means unlimited. Other issuance shares one per-IP bucket; shared public IPs need limits appropriate to legitimate traffic.
- WordPress cron must run regularly for large-network initialization and expired-state cleanup. Deactivation and uninstall remove continuation jobs as well as primary jobs.
- Bunny coordination covers one WordPress network. Separate installations and external list editors require dedicated lists or external coordination.
- Provider hook contracts were checked against available official source, with production-script browser regressions. This does not substitute for live testing of every supported commercial provider/version combination.
- The real WordPress suite uses PHP 8.3. The older PHP 8.0 container could not build locally because its Debian package sources failed; PHP 8.0 compatibility is covered by the separate strict runtime suite.
- Test cleanup is scoped to each run's owned containers, volumes, networks and configuration. Partial-startup fallback and Redis failures are covered by command-stub regressions; failed cleanup retains recovery files.

## Validation evidence for this checkpoint

The combined PHP suite passes **448 tests and 2,796 assertions**. An independent randomized-order run also passes under a 128 MiB memory limit (peak 101.69 MiB). Real WordPress, MariaDB and Redis validation passes **85 assertions on each of WordPress 6.4 and 7.1.2**, using PHP 8.3; both environments were removed by their owned cleanup routines. These real-runtime tests caught a missing-false option insertion defect that the initial stubs did not model; the implementation and stubs now cover it.

The production browser suite passes **45 Chromium cases**. The mounted admin suite covers **12 cases**. The isolated cleanup suite passes **15 regression cases**, including partial startup, ownership checks and Redis failures. Repository/build/package validation, coding standards, PHPStan, the security pack, and translation generation pass. The release ZIP excludes tests, development dependencies and PHPStan configuration. Root and child-admin npm audits report zero known vulnerabilities, including development dependencies.

Hosted CI passed all required checks on the preceding commit, including Firefox/WebKit and PHP 8.0–8.5. This checkpoint must pass those checks again at its own commit before merge. Local Firefox cannot start reliably in this macOS environment; hosted Linux CI provides its browser evidence. The previous Plugin Check run had zero errors and seven warnings (existing name/slug notices, deliberate bundled translations, and managed foundation runtime hooks); Plugin Check will be repeated after the final foundation migration.

## Pending foundation adoption and publication

The child currently consumes genuine, provenance-verified **wp-plugin-base v1.8.3**, commit `58a1aa68acababb303eea6760923c60cbf648f10`. Unreleased upstream source has not been relabeled as a release. This project intentionally retains one temporary managed PHPCS overlay include, documented in [development.md](development.md), until upstream overlay support is released.

The coordinated foundation work is tracked in [PR #343](https://github.com/MatthiasReinholz/wp-plugin-base/pull/343) and [PR #344](https://github.com/MatthiasReinholz/wp-plugin-base/pull/344). Its owner is reconciling the candidate with a further audit before publication. After a verified upstream release, the child must adopt the exact released source, select its isolated runtime class prefix, add the coexistence regression, remove the temporary overlay divergence, and rerun final release gates. The current vendored development tooling still needs that update; clean child-owned npm audits do not establish that every vendored development dependency is advisory-free.

The plugin release will follow the managed preparation pull request, required checks, protected merge, annotated tag and signed artifact publication. No plugin release is claimed by this checkpoint.
