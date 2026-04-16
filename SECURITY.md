# Security Policy

Report security issues privately before opening a public issue.

## Operational Security Model

### Secret Rotation Expectations

- Rotate the plugin challenge secret on a regular schedule and immediately after any suspected credential exposure.
- Rotating the secret invalidates outstanding signed challenge/context artifacts and requires clients to fetch fresh challenge state.
- Treat the plugin secret and Bunny Shield credentials as sensitive operational secrets and never store them in public issue logs.

### Trusted Proxy Configuration

- Forwarded client-IP headers are only trusted when the immediate `REMOTE_ADDR` is configured as a trusted proxy.
- Reverse proxies and CDNs must overwrite forwarded headers before requests reach WordPress.
- Do not trust user-supplied forwarded headers from untrusted source addresses.

### Cross-Site Challenge Requests

- The challenge endpoint is intentionally public for first-party widget bootstrapping.
- Requests classified as explicit cross-site are rejected with HTTP 403 and do not mutate challenge state or challenge issuance quota.
- Headerless requests remain compatible but do not count against challenge issuance rate limits.

### `min_submit_time` Meaning

- `min_submit_time` is an anti-automation timing guard that requires a minimum delay between widget start and submission verification.
- It is not a standalone abuse defense and should be combined with challenge verification, context guards, and rate limiting.
- Client-side submit-delay UX controls are advisory only; enforcement is performed server-side.

### Disclosure Expectations for External Integrations

- Future external provider integrations must include explicit disclosure of transmitted data, defaults, failure behavior, and rollback controls.
- New integrations that can block submissions or run background work must be off by default on new installs and upgrades.
- External-integration failures must not silently weaken or bypass documented local protections without operator visibility.

Recommended disclosure path:

- use GitHub Security Advisories for this repository when available
- include impact, affected versions, and reproduction steps
- avoid publishing exploit details or secrets in issues or pull requests
- redact Bunny Shield API keys, Shield zone IDs, access list IDs, and any credentialed CLI output before sharing logs publicly

If this plugin is listed in WordPress.org, also consider coordinating disclosure with ecosystems that monitor WordPress plugin vulnerabilities.
