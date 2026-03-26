# Anti Spam for WordPress

Anti Spam for WordPress is a self-hosted anti-spam plugin for WordPress forms, maintained by [Matthias Reinholz](https://matthiasreinholz.com).

Repository: [github.com/MatthiasReinholz/anti-spam-for-wordpress](https://github.com/MatthiasReinholz/anti-spam-for-wordpress)

## Overview

The plugin serves a local proof-of-work challenge through the WordPress REST API, renders a first-party browser widget in supported forms, and verifies the response locally in PHP. No external challenge API or remote spam classification is required.

## Operational Notes

The challenge endpoint must not be cached:

- `/wp-json/anti-spam-for-wordpress/v1/challenge`

If you run a CDN, edge cache, or page cache, configure a bypass rule for that path.

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

## Hooks

Filters:

* `apply_filters('asfw_challenge_url', $challenge_url)`
* `apply_filters('asfw_integrations', $integrations)`
* `apply_filters('asfw_plugin_active', false, $name)`
* `apply_filters('asfw_widget_attrs', $attrs, $mode, $language, $name)`
* `apply_filters('asfw_widget_html', $html, $mode, $language, $name)`
* `apply_filters('asfw_translations', $translations, $language)`

Actions:

* `do_action('asfw_verify_result', $result)`
* `do_action('asfw_settings_integrations')`

## Notes

* The plugin now ships with a first-party browser widget.

## License

GPLv2 or later
