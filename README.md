# Anti Spam for WordPress

Anti Spam for WordPress is a self-hosted anti-spam plugin for WordPress forms, maintained by [Matthias Reinholz](https://matthiasreinholz.com).

Repository: [github.com/MatthiasReinholz/anti-spam-for-wordpress](https://github.com/MatthiasReinholz/anti-spam-for-wordpress)

## Overview

The plugin serves a local proof-of-work challenge through the WordPress REST API, renders a first-party browser widget in supported forms, and verifies the response locally in PHP. Bunny Shield is an optional, off-by-default control-plane integration for operators who want to escalate repeated abuse signals into a remote access list.

## Operational Notes

The challenge endpoint must not be cached:

- `/wp-json/anti-spam-for-wordpress/v1/challenge`

The endpoint remains intentionally public so browsers can fetch challenges without authentication, but it is still rate-limited and marked with a no-cache response header. Requests classified as explicit cross-site are rejected with HTTP 403.

If you run a CDN, edge cache, or page cache, configure a bypass rule for that path.

If your site sends Content Security Policy headers, allow the domain serving this plugin's scripts in `script-src` and permit the widget styles in `style-src`.

If your site is behind Cloudflare, a load balancer, or another reverse proxy, add the proxy IPs or CIDR ranges to the **Trusted proxies** setting so the plugin can safely read forwarded client IP headers. For shared NAT environments, switch **Visitor binding** to **IP address + User Agent** to reduce false collisions in replay protection and rate limiting.

The default settings are intentionally conservative:

- **Kill switch** is off.
- **Bunny Shield** is off by default.
- **Dry run** is on by default for Bunny Shield.
- **Fail open** is on by default for Bunny Shield, so API failures never block form handling.

If you enable Bunny Shield, the module observes repeated abuse locally first, then mirrors those signals into a Bunny Shield custom access list only after the escalation threshold is reached.

If you place the widget manually with `[anti_spam_widget]` while the **Custom HTML** integration is disabled, pass `mode="captcha"` or `mode="shortcode"` explicitly so the shortcode still renders.

## Security matrix

The plugin now ships optional, context-scoped guard features in addition to proof-of-work:

- **Math challenge**: server-signed and server-validated arithmetic check (supports `log` and `block` modes).
- **Submit delay**: server-enforced minimum wait window (`1s`, `2.5s`, `5s`) with optional button-lock UX.

Supported contexts for both features:

- `wordpress:login`
- `wordpress:register`
- `wordpress:reset-password`
- `wordpress:comments`
- `wpdiscuz:comments`
- `woocommerce:login`
- `woocommerce:reset-password`

Important: client-only button disable behavior is **not** a security control. Protection only comes from server-side validation.

## Admin pages

After activation, review these settings/admin surfaces:

- `Anti Spam for WordPress` (top-level admin menu): tabbed admin app with `Settings`, `Events`, and `Analytics`.
- Legacy links (`Settings -> Anti Spam`, `Settings -> Events`, `Settings -> Analytics`) are preserved and redirect to the matching tab.

Event logging is disabled by default. Enable `Event logging` and set its mode to `log` or `block` before expecting Events/Analytics pages and event-based CLI reporting to populate.

## WP-CLI

Use these commands for operator workflows:

- `wp asfw events list [--limit=<n>] [--type=<type>] [--module=<module>] [--status=<status>]`
- `wp asfw status`
- `wp asfw feature list`
- `wp asfw events prune [--days=<n>] --yes`
- `wp asfw events purge --yes`
- `wp asfw events purge --older-than=<days> --yes`
- `wp asfw disposable status`
- `wp asfw disposable refresh --yes`
- `wp asfw disposable-email status`
- `wp asfw disposable-email refresh --yes`
- `wp asfw maintenance run --yes`
- `wp asfw bunny status`
- `wp asfw bunny revoke <ip> --yes`

Commands that mutate or delete data require `--yes` (`events prune`, `events purge`, `disposable refresh`, `disposable-email refresh`, `maintenance run`, `bunny revoke`). The disposable-domain refresh paths are operator-only actions, and daily maintenance uses the same refresh logic when disposable auto-refresh is enabled.

## Provenance

Originally based on [ALTCHA for WordPress](https://github.com/altcha-org/wordpress-plugin).

## Supported integrations

* CoBlocks
* Contact Form 7
* Elementor Pro Forms
* Enfold Theme
* Formidable Forms
* Forminator
* Gravity Forms
* HTML Forms
* WPDiscuz
* WPForms
* WordPress login, registration, password reset
* WordPress comments
* WooCommerce
* Optional Bunny Shield access-list escalation
* Custom HTML with `[anti_spam_widget]`

## Installation

1. Download the `.zip` from the [Releases](https://github.com/MatthiasReinholz/anti-spam-for-wordpress/releases).
2. Upload the `anti-spam-for-wordpress` folder to `/wp-content/plugins/`.
3. Activate the plugin through the Plugins menu in WordPress.
4. Review the settings and enable the integrations you need.

## Development

See [CONTRIBUTING.md](CONTRIBUTING.md) for the branch model, CI expectations, and release process.

Release preparation and release publishing can be driven from GitHub Actions through the `prepare-release`, `finalize-release`, and `release` workflows.

## REST API

This plugin requires the WordPress REST API. If you are using any plugin that disables the REST API, allow the endpoint `/anti-spam-for-wordpress/v1/challenge`.

## Bunny Shield

The Bunny Shield integration is intentionally conservative:

* It is disabled by default.
* It starts in dry run mode.
* It fails open if the Bunny API is unavailable or returns an error.
* It only considers repeated local verification failures and rate-limit events.
* It skips private, reserved, and loopback addresses.
* It deduplicates writes to Bunny Shield and backs off after API errors.

Use these commands for operator workflows:

* `wp asfw bunny status`
* `wp asfw bunny revoke <ip> --yes`

## Hooks

### Filters

**`asfw_widget_provider`** — Override the widget provider identifier rendered into the custom element.

```php
apply_filters('asfw_widget_provider', string $provider): string
```

**`asfw_widget_tag_name`** — Override the custom element tag name used for the widget.

```php
apply_filters('asfw_widget_tag_name', string $tag_name): string
```

**`asfw_challenge_url`** — Customize the REST API challenge endpoint URL.

```php
apply_filters('asfw_challenge_url', string $challenge_url, string|null $context): string
```

**`asfw_integrations`** — Override the list of active integration identifiers.

```php
apply_filters('asfw_integrations', array $integrations): array
```

**`asfw_plugin_active`** — Override plugin detection for integration availability checks.

```php
apply_filters('asfw_plugin_active', bool $active, string $plugin_name): bool
```

**`asfw_widget_attrs`** — Modify widget HTML attributes before rendering.

```php
apply_filters('asfw_widget_attrs', array $attrs, string $mode, string|null $language, string $field_name, string $context): array
```

**`asfw_widget_html`** — Modify the final widget HTML markup.

```php
apply_filters('asfw_widget_html', string $html, string $mode, string|null $language, string $field_name, string $context): string
```

**`asfw_translations`** — Override widget UI strings.

```php
apply_filters('asfw_translations', array $translations, string|null $language): array
```

**`asfw_widget_context`** — Override the normalized context identifier for a widget instance.

```php
apply_filters('asfw_widget_context', string $context, string $mode, string|null $name): string
```

**`asfw_trusted_proxies`** — Override the trusted reverse proxy IP or CIDR list used for forwarded client IP detection.

```php
apply_filters('asfw_trusted_proxies', array $trusted_proxies): array
```

**`asfw_client_ip`** — Override the resolved client IP after proxy handling.

```php
apply_filters('asfw_client_ip', string $client_ip, string $remote_addr, string $source_header): string
```

**`asfw_client_binding_components`** — Override the normalized components used to build the replay/rate-limit fingerprint.

```php
apply_filters('asfw_client_binding_components', array $components, string $binding_strategy): array
```

### Actions

**`asfw_rate_limited`** — Fires when challenge or verification throttling blocks a request.

```php
do_action('asfw_rate_limited', string $type, string $context, array $state)
```

**`asfw_verify_result`** — Fires after every verification attempt.

```php
do_action('asfw_verify_result', bool $success, true|WP_Error $result, string|null $context, string $field_name, string|null $resolved_context)
```

**`asfw_guard_result`** — Fires after each optional context guard check (math challenge or submit delay).

```php
do_action('asfw_guard_result', string $feature, string $context, bool $success, string $mode, string $error_code)
```

**`asfw_challenge_issued`** — Fires after a new challenge is generated.

```php
do_action('asfw_challenge_issued', array $challenge_data, string $context, string $challenge_id)
```

**`asfw_bunny_synced`** — Fires after Bunny Shield successfully accepts an access-list update.

```php
do_action('asfw_bunny_synced', string $ip, string $reason, array $state, array $result)
```

**`asfw_bunny_sync_failed`** — Fires after a Bunny Shield write fails and backoff is scheduled.

```php
do_action('asfw_bunny_sync_failed', string $ip, string $reason, array $state, WP_Error $error, array $failure)
```

**`asfw_settings_integrations`** — Fires inside the settings page to allow adding custom integration fields.

```php
do_action('asfw_settings_integrations')
```

## Notes

* The plugin now ships with a first-party browser widget.

## License

GPLv2 or later
