# Changelog

All notable changes to this project should be documented in this file.

The format follows a simple Keep a Changelog-style layout with one section per released version and concise bullet points for user-visible changes.

## [0.4.0] - 2026-04-16

- Added optional context-scoped `math_challenge` and `submit_delay` guards with `off|log|block` runtime modes.
- Added server-validated submit-delay token issuance and verification across supported WordPress and WooCommerce auth/comment contexts.
- Hardened submit-delay issuance with same-site origin requirements, context allowlisting/scope checks, and rate limiting.
- Improved WooCommerce route/context fallback handling for both pretty permalink and plain query-string account/lost-password flows.
- Added integration coverage for guard rendering/validation and REST endpoint behavior.
