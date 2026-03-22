=== Anti Spam for WordPress ===
Tags: spam, anti-spam, antispam, captcha, proof-of-work, gdpr, privacy
Author: Matthias Reinholz
Author URI: https://matthiasreinholz.com
Version: 0.1.0
Stable tag: 0.1.0
Requires at least: 5.0
Requires PHP: 7.3
Tested up to: 6.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-hosted spam protection for WordPress forms using a proof-of-work widget.

== Description ==

Anti Spam for WordPress is maintained by Matthias Reinholz.

The plugin keeps the local proof-of-work approach and removes external API regions, API keys, and remote spam classification. It now runs entirely inside your WordPress installation.

Originally based on ALTCHA for WordPress:
https://github.com/altcha-org/wordpress-plugin

= Features =

* Self-hosted challenge generation and verification
* No external API required
* Privacy-friendly proof-of-work widget
* Protection for core WordPress screens and popular form plugins
* Custom shortcode support via `[anti_spam_widget]`

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
* WP-Members
* WordPress Login, Register, Password reset
* WordPress Comments
* WooCommerce
* Custom HTML with `[anti_spam_widget]`

= Installation =

1. Download the `.zip` from the [Releases](https://github.com/MatthiasReinholz/anti-spam-for-wordpress/releases).
2. Upload the `anti-spam-for-wordpress` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the Plugins menu in WordPress.
4. Review the settings and enable the integrations you need.

= REST API =

This plugin requires the WordPress REST API. If you use a plugin that disables the REST API, allow the endpoint `/anti-spam-for-wordpress/v1/challenge`.

If you use a CDN or edge cache, bypass caching for `/wp-json/anti-spam-for-wordpress/v1/challenge`.

= Source Code =

* Plugin: https://github.com/MatthiasReinholz/anti-spam-for-wordpress

== Screenshots ==

1. Settings page
2. Protection on the login page
3. Protection with WPForms
4. Custom shortcode usage
5. Floating UI example

== Changelog ==

= 0.1.0 =
* TODO: finalize release notes.

= 0.0.1 =
* Rebranded the plugin as Anti Spam for WordPress.
* Removed hosted API modes and remote spam classification.
* Added settings migration compatibility for existing installs.
* Replaced the shortcode with `[anti_spam_widget]`.
