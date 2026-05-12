# Admin Settings Reference

This document is the operator and agent reference for the Anti Spam for WordPress admin app.

## Navigation

The admin app is registered under `Settings -> Anti Spam for WordPress`.
The canonical URL is:

```text
/wp-admin/options-general.php?page=anti-spam-for-wordpress-admin-ui&tab=settings
```

Old page query slugs redirect to the matching tab for compatibility, but they are not registered as separate admin pages:

- `asfw_admin` -> `settings`
- `asfw_events` -> `events`
- `asfw_analytics` -> `analytics`

## Settings Order

The Settings tab follows the setup path an operator normally takes:

1. `Protection Placements`: WordPress placements first, then form and commerce integrations.
2. `Core Challenge`: proof-of-work secret, complexity, and expiration.
3. `Security Hardening`: lazy loading, rate limits, honeypot, submit timing, math challenge, and submit delay.
4. `Widget and Shortcode`: widget behavior, footer/privacy link, and shortcode usage.
5. `Observability and Policy`: kill switch, event logging, retention, disposable-email checks, and content heuristics.
6. `Bunny Shield`: optional remote access-list escalation.

## Shortcode

Use `[anti_spam_widget]` when automatic placement is not available in custom form markup.

```text
[anti_spam_widget mode="captcha" context="custom:contact" name="asfw"]
```

Supported attributes:

- `mode`: `captcha` or `shortcode`. Required when the Custom HTML placement is disabled.
- `context`: optional normalized context used for event logging, feature scoping, and verification.
- `name`: form field name. Defaults to `asfw`.
- `language`: optional widget language override.

## REST Routes

Security plugins that restrict the REST API must allow these routes:

- `/wp-json/anti-spam-for-wordpress/v1/challenge`
- `/wp-json/anti-spam-for-wordpress/v1/submit-delay-token` when Submit delay is enabled
- `/wp-json/anti-spam-for-wordpress/v1/admin/settings` for authenticated administrators
- `/wp-json/anti-spam-for-wordpress/v1/admin/events` for authenticated administrators
- `/wp-json/anti-spam-for-wordpress/v1/admin/analytics` for authenticated administrators

The admin routes are registered through the managed REST operations pack and require `manage_options`.

## Agent Notes

- Keep `asfw_integrations_settings_section` stable; it now owns all placement fields and is titled `Protection Placements`.
- The context catalog is an internal reference. Do not render it as a primary settings card unless a user explicitly asks for a developer/debug view.
- Rebuild `.wp-plugin-base-admin-ui` after editing UI sources: `npm run build`.
