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

The thirteenth reviewer reproduced two additional defects: a one-shot retention migration failure could fall through to a different default, and duplicate-heavy Bunny list responses could exhaust a 128 MiB PHP process. Both received bounded fixes and regressions. A final release review also found that an upstream GitLab notes-output loop included its temporary authentication header. Publication was held while category-only output and credential-cleanup regressions were added; no real credentials were used in those tests. The foundation changes were developed upstream and adopted from the verified release described below. Plugin publication follows the separate managed release process.

## Prioritized engineering changes

| Priority | Task | Result |
| --- | --- | --- |
| P1 | Make proof consumption and quotas atomic | Database compare-and-swap state, exact one-time consumption, bounded contention retries, fail-closed storage errors, and one per-IP issuance quota across contexts, User Agents, math, delay, and proof requests. Invalid signatures cannot consume a legitimate challenge. |
| P1 | Bind authentication and comment policy to trusted dispatch | Native login/reset cannot be downgraded with WooCommerce-looking URLs or public nonces. WooCommerce login policy applies only to its actual validated handler and one matching authentication call. Account registration protection does not intercept checkout or programmatic customer creation. Native product reviews, wpDiscuz, and CoBlocks retain their intended policies. |
| P1 | Correct browser lifecycle and provider retries | Per-form cancellation and generation checks, bounded observers, explicit retries, multiple-widget coordination, and renewal after settled requests. wpDiscuz verifies before serialization. Lazy loading and dynamic field names honor their documented settings. |
| P1 | Recover incomplete initialization without replacing settings | Required writes and completion markers are verified. Legacy migration stops on persistence failure, including a one-shot retention failure. Schema version 4 rechecks historical version-3 installations. Multisite initialization uses bounded batches, lazy repair, and new-site support. |
| P1 | Keep credentials out of generated release notes | The upstream release-note generator outputs only changelog categories. Synthetic GitLab project/job-token regressions exercise actual notes and reject authentication-header disclosure while retaining protected transport and cleanup. |
| P1 | Isolate the foundation runtime | Explicit `RUNTIME_CLASS_PREFIX=ASFW_` isolates the generated REST and admin runtime classes. The coexistence regression loads the actual plugin and an unprefixed consumer in both orders, including overlapping operation IDs, and verifies independent permissions, callbacks, namespaces and admin pages. |
| P1 | Exercise real storage and supported versions | Independent workers race against WordPress/MariaDB, repeat with a verified Redis object cache, and exercise multisite repair/uninstall. CI covers WordPress 6.4 and 7.1.2 on PHP 8.3, browser engines Chromium/Firefox/WebKit, and strict PHP 8.0–8.5 runtime tests. |
| P2 | Report unavailable data and failed saves honestly | Event reads expose a checked error state while preserving public return types. Admin and CLI consumers return errors instead of empty logs. Settings saves verify persisted values, preserve JSON backslashes, retain drafts after failures/timeouts, and clamp pagination before querying. |
| P2 | Bound and validate remote data | Disposable feeds and Bunny IP lists parse incrementally with byte, entry and unique-value limits. Malformed or excessive data cannot replace a good list or trigger partial remote mutations. Disposable matching builds one fresh domain lookup per submission. |
| P2 | Make Bunny revocation idempotent and retryable | Owned network-wide leases, stale-list rediscovery, validated responses, explicit dry-run state and durable backoff. Confirmed remote absence clears local counters and deduplication state; failed local cleanup remains a retryable error. |
| P2 | Improve privacy boundaries | One selected forwarding header is trusted only behind configured proxies. Recursive event sanitation covers nested JSON and credential keys within shared limits. Heuristics exclude credentials. Generated privacy text accurately describes private expiring database options. |
| P2 | Correct provider and extension contracts | Forminator's one-argument PayPal rendering hook no longer fatals. Custom settings sections and new feature sections render correctly; explicit metadata and built-in ordering remain stable. |
| P2 | Keep shared infrastructure fixes upstream | Adopted provenance-verified foundation 1.9.0 with safe generation, authenticated source fetching, runtime isolation, immutable release recovery, action-pin policy/migration, dependency ownership/remediation, optional PHPCS overlays, and isolated tooling cleanup. Child-specific manifests, locks and application source remain project-owned. |
| P2 | Preserve complete validation input and actionable diagnostics | Managed-file manifests finish successfully before sync mutates the child; producer, interrupted/short-write and staging failures propagate. Plugin Check JSON and error summaries retain file/line locations. The disposable-domain feed has one documented `OffloadedContent` annotation; other offloading checks remain active. |
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

## Validation evidence

The final adoption commit `d594e34c4ead22d870f857f428f101f3a5ca167f` passed all **17 reported hosted checks** before the protected merge of [PR #97](https://github.com/MatthiasReinholz/anti-spam-for-wordpress/pull/97). The local and hosted evidence below covers the released foundation source and final plugin implementation.

The final adopted tree passes **456 PHP tests and 2,844 assertions**, including both runtime coexistence load orders. The installed quality-tool bundle matches the committed lockfile. A randomized-order run with seed `20260925` and explicit `php -d memory_limit=128M` passes with a reported peak memory usage of 101.69 MB.

On the final adoption commit, hosted real WordPress, MariaDB and Redis validation passed **85 storage/lifecycle assertions on each of WordPress 6.4 and 7.1.2**, using PHP 8.3, plus a real admin browser check of core controls and script dependencies, authenticated settings save/readback/restoration, Events and Analytics. Owned cleanup removed each completed environment.

These real-runtime checks caught two gaps that stubbed tests alone did not expose: missing-false option insertion and a worker result-file race. Initialization and stubs now model WordPress's actual option behavior. The concurrency harness waits for a successful worker exit before decoding its result; failed, incomplete, malformed or timed-out workers fail explicitly.

On the final adopted tree, the production browser suite passes **45 Chromium cases**. The admin suite passes **13 cases**: 12 mounted React cases and a TypeScript dependency contract regression that catches incompatible major-version overrides. The isolated cleanup suite passes **17 regression cases**, including partial startup, ownership checks and Redis failures. Repository/build/package validation, PHP coding standards, PHPStan, admin JavaScript lint and POT generation pass. The release ZIP excludes tests, development dependencies and PHPStan configuration.

The final security pack passes coding-standard checks on **94 PHP files** and reports **zero findings** from four Semgrep rules across 153 PHP targets. Root and admin npm audits, including development dependencies, report zero known advisories; the audited PHP security-tool bundle also has none. Three public challenge-route authorization suppressions remain narrowly justified. Plugin Check **2.1.0** reports **zero errors and seven warnings**, with file/line diagnostics retained: three existing name/slug notices, one deliberate bundled-translation loader notice, and three generated-foundation bootstrap-variable/dynamic-hook naming notices. These warnings remain visible. The single feed annotation documents server-side plain-text data that is validated into local options and never enqueued or executed; a negative control confirms that remote executable content is still reported.

Final hosted runs [CI](https://github.com/MatthiasReinholz/anti-spam-for-wordpress/actions/runs/36138050669), [browser tests](https://github.com/MatthiasReinholz/anti-spam-for-wordpress/actions/runs/36138050664), and [WordPress integration](https://github.com/MatthiasReinholz/anti-spam-for-wordpress/actions/runs/36138050605) passed. They confirm **456 PHP tests / 2,844 assertions on every PHP version from 8.0 through 8.5**, **45 cases per browser engine**, all 13 admin tests, and both real WordPress versions. No browser cases were skipped. Local Firefox cannot start reliably in this macOS environment; hosted Linux CI supplies its browser evidence. The subsequent development-only [Playwright 1.63 update](https://github.com/MatthiasReinholz/anti-spam-for-wordpress/pull/98), commit `fb1b9cfb50c257584fed0fb493ef6f23ae538ce9`, also passed all 16 reported checks before protected merge. The versioned release preparation must pass its own required checks before merge and publication.

## Verified foundation adoption and maintenance ownership

The project consumes provenance-verified **[wp-plugin-base v1.9.0](https://github.com/MatthiasReinholz/wp-plugin-base/releases/tag/v1.9.0)**, commit `efa11cba9b980f400ddbbc3ca6115a3fb3aa2450`. The upstream feature work is consolidated in [PR #343](https://github.com/MatthiasReinholz/wp-plugin-base/pull/343); its managed release was prepared in [PR #345](https://github.com/MatthiasReinholz/wp-plugin-base/pull/345). Vendored source comes from the verified released commit; development snapshots are not relabeled as releases.

The child explicitly selects `ASFW_` for runtime class isolation and updates its owned admin bootstrap reference accordingly. The generated PHPCS configuration now includes the existing child-owned overlay through supported upstream synchronization. The temporary 1.8.3 manual include workaround is removed; implementation files remain subject to the quality checks.

The refreshed child admin graph passes clean installation, build, lint, all 13 tests and a zero-advisory audit. Its React 18 pins, custom source and scoped overrides are retained. Foundation sync preserves child-owned dependency manifests and locks; matching starter changes require deliberate child review and regeneration. Post-merge dependency proposals for jQuery 4 and standalone React DOM 19 were rejected because they break the oldest-core fixture and paired React/test contracts; the compatibility pins and upgrade requirements are documented in the developer guide. Foundation action pins belong to the upstream catalog, including reviewed migrations of custom workflows. The initial adoption from older update automation is performed manually so those migrated paths are included in the reviewed commit.

At the foundation maintenance checkpoint, there were zero open dependency advisories, all nine update handlers reported no available changes, and 38 superseded pull requests were closed. These are dated maintenance observations, not guarantees about future registry releases or security disclosures. Live GitLab publication remains a separate upstream operational check; this project publishes through GitHub.

Plugin publication requires its reviewed preparation pull request, required checks, protected merge, annotated tag and signed artifact publication. The published release and its verification evidence are authoritative; this audit record alone does not assert that a plugin release has been published.


## Post-release audit on 25 September 2026

A fresh fetch confirmed that `main` and the annotated **0.10.0** release tag point to `c4deadee6adc42c39ef354fd39ba585fc3e85533`. [Release preparation PR #101](https://github.com/MatthiasReinholz/anti-spam-for-wordpress/pull/101) passed all 25 reported checks; [publication](https://github.com/MatthiasReinholz/anti-spam-for-wordpress/actions/runs/36140046115) and [post-merge WordPress integration](https://github.com/MatthiasReinholz/anti-spam-for-wordpress/actions/runs/36140046133) succeeded. Independent verification confirmed the published ZIP signature, exact-commit build attestation, annotated tag, all three asset hashes, and version metadata/exclusions in the 202-entry package. The repository had no open dependency, code-scanning or secret-scanning alerts at this dated checkpoint.

Three additional focused reviewers and a root review found the following edge cases in 0.10.0. Each fix was reviewed independently; negative controls demonstrated the failure in the released implementation.

| Priority | Finding | Correction and regression |
| --- | --- | --- |
| P2 | A failed insertion of a missing false, zero or empty setting was mistaken for a successful save | Distinguish missing options with a unique sentinel, explicitly insert missing values, and verify readback. Tests cover failed insertion, successful retry and unchanged persisted falsy values, including extension settings. |
| P2 | An admin read could remain loading after its transport ignored cancellation | Settle the 30-second deadline independently, retain request-identity checks and require explicit retry. Mounted browser tests cover normal abort and late responses before and after retry. |
| P2 | Event-schema or pruning errors prevented expired verification-state cleanup | Run the bounded cleanup before event maintenance. Tests retain live rows, drain a backlog through scheduled continuation, and preserve failure reporting and the previous success timestamp. |
| P2 | A compact Bunny JSON response could exhaust memory before list validation | Check raw bytes, nesting and allocation-driving structural tokens before decoding. Isolated 128 MiB processes exercise dense arrays/objects, exact boundaries, escaping, flat strings and provider errors. |
| P3 | Array-shaped proof input generated PHP warnings before rejection | Reject non-string proof inputs and malformed salt-query fields before casting or normalization. Warning-escalating tests confirm controlled rejection without consuming the original valid proof. |

Local combined validation passes **499 PHP tests / 3,118 assertions**, including randomized order under an explicit 128 MiB memory limit, plus **16 admin/dependency cases**. Coding standards, static analysis, admin lint, security scans, dependency audits, translation generation and package validation pass. The follow-up change and its patch release must also pass their hosted checks before merge and publication.

These corrections are child-owned and require no foundation divergence. The review found no new replay bypass or privilege escalation. Existing commercial-provider live-test and deployment-environment limits remain applicable.
