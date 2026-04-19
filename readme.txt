=== Anti Spam for WordPress ===
Contributors: matthiasreinholz
Tags: spam, anti-spam, antispam, captcha, proof-of-work, gdpr, privacy
Author: Matthias Reinholz
Author URI: https://matthiasreinholz.com
Version: 0.4.3
Stable tag: 0.4.3
Requires at least: 5.0
Requires PHP: 8.0
Tested up to: 6.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-hosted spam protection for WordPress forms using a proof-of-work widget.

== Description ==

Anti Spam for WordPress is maintained by Matthias Reinholz.

The plugin keeps the local proof-of-work approach for core protection. Bunny Shield is an optional, off-by-default control-plane integration for operators who want to escalate repeated abuse into a remote access list.

Originally based on ALTCHA for WordPress:
https://github.com/altcha-org/wordpress-plugin

= Features =

* Self-hosted challenge generation and verification
* No external API required for core protection
* Privacy-friendly proof-of-work widget
* Protection for core WordPress screens and popular form plugins
* Optional Bunny Shield access-list escalation
* Custom shortcode support via `[anti_spam_widget]`

= Security matrix =

Optional context-scoped guard features are available in addition to proof-of-work:

* Math challenge: server-signed and server-validated arithmetic check (`log` or `block` mode).
* Submit delay: server-enforced wait window (`1s`, `2.5s`, `5s`) with optional button-lock UX.

Supported contexts:

* `wordpress:login`
* `wordpress:register`
* `wordpress:reset-password`
* `wordpress:comments`
* `wpdiscuz:comments`
* `woocommerce:login`
* `woocommerce:reset-password`

Important: client-only button disabling is cosmetic UX, not a standalone security control.

= Supported Integrations =

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
* WordPress Login, Register, Password reset
* WordPress Comments
* WooCommerce
* Optional Bunny Shield access-list escalation
* Custom HTML with `[anti_spam_widget]`

= Installation =

1. Download the `.zip` from the [Releases](https://github.com/MatthiasReinholz/anti-spam-for-wordpress/releases).
2. Upload the `anti-spam-for-wordpress` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the Plugins menu in WordPress.
4. Review the settings and enable the integrations you need.

= REST API =

This plugin requires the WordPress REST API. If you use a plugin that disables the REST API, allow the endpoint `/anti-spam-for-wordpress/v1/challenge`.
When the optional submit-delay feature is enabled, also allow `/anti-spam-for-wordpress/v1/submit-delay-token`.

If you use a CDN or edge cache, bypass caching for `/wp-json/anti-spam-for-wordpress/v1/challenge` and `/wp-json/anti-spam-for-wordpress/v1/submit-delay-token` (when submit-delay is enabled).

The challenge endpoint stays public so the widget can fetch challenges without authentication, and the plugin sends a no-cache response header for the challenge response. Requests classified as explicit cross-site are rejected with HTTP 403.

If your site sends Content Security Policy headers, allow the domain serving the plugin scripts in `script-src` and permit the widget styles in `style-src`.

If your site is behind Cloudflare, a load balancer, or another reverse proxy, add the proxy IPs or CIDR ranges to the Trusted proxies setting so the plugin can safely read forwarded client IP headers.

The default settings are intentionally conservative:

* Kill switch is off.
* Bunny Shield is disabled by default.
* Bunny Shield starts in Dry run mode.
* Bunny Shield fails open if the Bunny API is unavailable or returns an error.

If you enable Bunny Shield, it only escalates repeated local abuse signals after the threshold is reached and skips private or reserved IPs.

Operator commands:

* `wp asfw events list [--limit=<n>] [--type=<type>] [--module=<module>] [--status=<status>]`
* `wp asfw status`
* `wp asfw feature list`
* `wp asfw events prune [--days=<n>] --yes`
* `wp asfw events purge --yes`
* `wp asfw events purge --older-than=<days> --yes`
* `wp asfw disposable status`
* `wp asfw disposable refresh --yes`
* `wp asfw disposable-email status`
* `wp asfw disposable-email refresh --yes`
* `wp asfw maintenance run --yes`
* `wp asfw bunny status`
* `wp asfw bunny revoke <ip> --yes`

Commands that mutate or delete data require `--yes` (`events prune`, `events purge`, `disposable refresh`, `disposable-email refresh`, `maintenance run`, `bunny revoke`). The disposable-domain refresh paths are operator-only actions, and the daily maintenance workflow uses the same refresh logic when disposable auto-refresh is enabled.

Admin pages:

* `Settings -> Anti Spam` for control-plane, security, integration, Bunny Shield, and context-catalog settings.
* `Settings -> Events` for read-only event logs (latest 50 entries).
* `Settings -> Analytics` for server-rendered aggregate metrics.

Event logging is disabled by default. Enable `Event logging` and set its mode to `log` or `block` before expecting Events/Analytics pages and event-based CLI reporting to populate.

= Source Code =

* Plugin: https://github.com/MatthiasReinholz/anti-spam-for-wordpress

== Frequently Asked Questions ==

= The widget shows an error or never loads =

The plugin requires the WordPress REST API. Make sure no security plugin is blocking the `/wp-json/anti-spam-for-wordpress/v1/challenge` endpoint. Check the browser console for network errors.

= I use a CDN or page cache and challenges fail =

Each challenge must be unique. Add a cache bypass rule for `/wp-json/anti-spam-for-wordpress/v1/challenge` in your CDN or caching plugin.

= The widget is blocked by Content Security Policy (CSP) headers =

If your site sends strict CSP headers, ensure that `script-src` allows the domain serving the plugin scripts and that `style-src` permits inline styles used by the widget.

= Users behind a shared IP or proxy are being blocked =

The plugin uses client fingerprinting to prevent challenge replay. Users behind a shared NAT gateway or corporate proxy may share an IP, which can cause false lockouts under heavy rate limiting. Add your reverse proxies to the Trusted proxies setting and consider switching Visitor binding to "IP address + User Agent" to reduce collisions. Lower the rate limit thresholds or disable rate limiting if this is still a problem.

= I use the shortcode manually and the widget disappeared =

If you disable the Custom HTML integration, pass `mode="captcha"` or `mode="shortcode"` in `[anti_spam_widget]` so the shortcode still renders explicitly.

= How does Bunny Shield behave? =

The Bunny Shield integration is disabled by default. When enabled, it runs in dry run mode first, deduplicates repeated writes, backs off after API errors, and skips private or reserved IP addresses. Use the Bunny Shield status command to review the current access-list state.

= How does proof-of-work differ from a CAPTCHA? =

Instead of asking the user to solve a visual puzzle, the widget asks the browser to perform a small computational task (hashing). This runs automatically and is invisible to the user.

== Screenshots ==

1. Settings page
2. Protection on the login page
3. Protection with WPForms
4. Custom shortcode usage
5. Floating UI example

== Changelog ==

= 0.4.3 =
* Fix - fix(ci): exclude quality and security vendor packs from js lint traversal (#31).
* Update - Release 0.4.2 (#30).
* Fix - fix(ci): exclude quality and security vendor packs from php lint traversal (#29).
* Update - Release 0.4.1 (#28).
* Update - chore: update wp-plugin-base to v1.7.1 and resync managed files (#27).
* Update - chore(deps): bump github/codeql-action from 4.35.1 to 4.35.2 (#25).
* Update - release: 0.4.0 with context-scoped math challenge and submit-delay hardening (#24).
* Update - chore: align plugin stable tag metadata.
* Update - chore: sync local workspace updates and integration refactors.
* Update - plugin base updates.
* Fix - Polish docs and compatibility fixes.
* Update - Harden anti-spam verification flow.


= 0.4.2 =
* Fix - fix(ci): exclude quality and security vendor packs from php lint traversal (#29).
* Update - Release 0.4.1 (#28).
* Update - chore: update wp-plugin-base to v1.7.1 and resync managed files (#27).
* Update - chore(deps): bump github/codeql-action from 4.35.1 to 4.35.2 (#25).
* Update - release: 0.4.0 with context-scoped math challenge and submit-delay hardening (#24).
* Update - chore: align plugin stable tag metadata.
* Update - chore: sync local workspace updates and integration refactors.
* Update - plugin base updates.
* Fix - Polish docs and compatibility fixes.
* Update - Harden anti-spam verification flow.


= 0.4.1 =
* Update - chore: update wp-plugin-base to v1.7.1 and resync managed files (#27).
* Update - chore(deps): bump github/codeql-action from 4.35.1 to 4.35.2 (#25).
* Update - release: 0.4.0 with context-scoped math challenge and submit-delay hardening (#24).
* Update - chore: align plugin stable tag metadata.
* Update - chore: sync local workspace updates and integration refactors.
* Update - plugin base updates.
* Fix - Polish docs and compatibility fixes.
* Update - Harden anti-spam verification flow.


= 0.4.0 =
* Feature - Add optional per-context math challenge and submit-delay guards for WordPress and WooCommerce auth/comments flows.
* Fix - Harden submit-delay token issuance with same-site enforcement, context scope validation, and rate limiting.
* Fix - Improve WooCommerce fallback context and route detection for pretty and plain permalink setups.

= 0.3.2 =
* Fix - Harden challenge payload validation and verification context reporting.
* Fix - Polish docs and compatibility fixes.
* Update - Harden anti-spam verification flow.


= 0.3.1 =
* Fix - fix(ci): disable persisted checkout credentials in prepare-release.
* Update - chore: update wp-plugin-base to v1.6.2.
* Update - chore: update wp-plugin-base to v1.2.3.


= 0.3.0 =
* Updated CI dependencies (shivammathur/setup-php, actions/checkout 6.0.2).
* Upgraded wp-plugin-base to v1.2.1.
* Hardened anti-spam verification flow.

= 0.2.0 =
* Hardened settings sanitization callbacks.
* Improved public documentation and README wording.
* Added automated release note generation and release PR validation.
* Refined release packaging workflow.

= 0.1.0 =
* Added semver controls to the release workflow.
* Stabilised branching and release model.

= 0.0.1 =
* Rebranded the plugin as Anti Spam for WordPress.
* Removed hosted API modes and remote spam classification.
* Added settings migration compatibility for existing installs.
* Replaced the shortcode with `[anti_spam_widget]`.
