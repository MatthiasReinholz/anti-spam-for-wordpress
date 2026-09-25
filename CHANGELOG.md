# Changelog

All notable changes to this project should be documented in this file.

The format follows a simple Keep a Changelog-style layout with one section per released version and concise bullet points for user-visible changes.

## [0.10.2] - 2026-09-25

- Fix admin settings errors from custom fields using closures, callable arrays or invokable renderers while preserving built-in field types.
- Add an operator upgrade checklist covering supported platforms, proxy headers, shared-IP quotas, legacy Bunny settings, cached forms and recovery without deleting configuration.
- Clarify extension hooks and field metadata, and record eight successful historical-package upgrades across the minimum and current tested WordPress versions.

## [0.10.1] - 2026-09-25

- Verify missing false, zero and empty settings are persisted before reporting a successful save; preserve literal-false extension values.
- Settle admin read deadlines independently of transport cancellation, permit explicit retry, and ignore late responses.
- Drain expired verification state before event-table maintenance so logging failures cannot block cleanup.
- Bound Bunny response bytes, JSON nesting and structural allocations before decoding while retaining valid large flat content.
- Reject malformed proof inputs and salt parameters without PHP warnings or consuming valid verification state.

## [0.10.0] - 2026-09-25

- Prevent concurrent proof reuse and quota races with atomic database state and a shared per-IP issuance limit.
- Bind authentication and comment protection to trusted WordPress and provider handlers while preserving checkout and programmatic account creation.
- Repair consumed-proof renewal, stale browser requests, lazy loading and dynamic widget fields across provider integrations.
- Recover failed initialization, legacy migrations and event schemas; preserve multisite configuration and clean up owned state on uninstall.
- Report failed admin saves and event reads, retain drafts for retry, and validate pagination before querying.
- Bound remote feed and Bunny list processing, preserve previously valid feeds, and make revocation and cleanup idempotent and retryable.
- Isolate REST and admin runtime classes using provenance-verified wp-plugin-base 1.9.0.
- Improve event redaction, credential exclusion, proxy handling, Forminator compatibility and custom settings sections.
- Refresh dependencies, translations and maintenance documentation; expand real WordPress, concurrency, browser and failure-path regression coverage.

Upgrade notes: outstanding verification tokens need a fresh attempt after upgrading. A quota of zero remains unlimited. Regular WordPress cron is required for background initialization and expired-state cleanup. Bunny coordination covers one WordPress network.

## [0.9.0] - 2026-09-12

- Added bundled widget translations for 15 languages, including regional variants, Brazilian Portuguese, and both Norwegian written forms.
- Use short, informal widget wording and keep English fallbacks for malformed translation values.
- Load bundled catalogs through WordPress and translate the default footer at render time; preserve custom footer text.
- Restore the previous locale correctly after widget language overrides, including when translation filters throw.

## [0.8.1] - 2026-08-21

- Limited native comment verification to genuine browser and anonymous remote comment submissions, preserving programmatic and authenticated API comment creation.
- Hardened plugin initialization across supported PHP runtimes and updated the development toolchain to Node.js 22.

## [0.8.0] - 2026-08-21

- Enabled proof-of-work protection for native WordPress comment forms by default, with an opt-out under Protection Placements.
- Declared compatibility with WordPress 7.1.

## [0.4.0] - 2026-04-16

- Added optional context-scoped `math_challenge` and `submit_delay` guards with `off|log|block` runtime modes.
- Added server-validated submit-delay token issuance and verification across supported WordPress and WooCommerce auth/comment contexts.
- Hardened submit-delay issuance with same-site origin requirements, context allowlisting/scope checks, and rate limiting.
- Improved WooCommerce route/context fallback handling for both pretty permalink and plain query-string account/lost-password flows.
- Added integration coverage for guard rendering/validation and REST endpoint behavior.
