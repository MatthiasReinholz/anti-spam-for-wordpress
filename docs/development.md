# Project development

The public `AntiSpamForWordPressPlugin` façade delegates to small services in `includes/`. Keep its existing method signatures stable when improving the implementation. Integrations translate provider events into the shared verification contract; security decisions belong on the server.

## Ownership and layout

- `includes/class-asfw-challenge-manager.php`, `class-asfw-verifier.php`, and `class-asfw-rate-limiter.php` issue and validate proof, math, and delay state.
- `includes/class-asfw-atomic-state-store.php` owns short-lived, non-autoloaded `asfw_state_*` database rows. Reads bypass WordPress caches. Mutations compare the exact observed revision; only a successful conditional deletion consumes a proof. A failed database write or exhausted contention retry must never authorize a request. Leases require conditional release by their owner. Daily maintenance removes expired rows in batches of 1,000 and schedules short follow-up batches until the backlog is drained. Production sites should run WordPress cron regularly.
- `includes/lifecycle.php` initializes missing per-site state. Network activation visits 100 sites per batch and schedules continuation; lazy initialization covers sites before a batch reaches them. New sites initialize only when the plugin is active on their network. Reactivation preserves existing settings and secrets. Initialization and legacy migration return a success boolean: every required write is verified before the completion marker advances. Failed writes leave initialization pending for a later retry; they must not cause legacy settings to be replaced by generic defaults.
- `includes/class-asfw-settings-schema.php` defines settings. Add owned settings and runtime options to `ASFW_Option_Inventory::names()` so uninstall removes them. Uninstall visits all sites in bounded pages.
- Event-store reads retain their array/integer return values. Extensions must inspect `ASFW_Event_Store::get_last_read_error()` immediately after a read before interpreting an empty result or zero count. The error resets at the next read. Admin REST and CLI consumers report failures rather than presenting unavailable data as an empty log.
- `public/asfw-widget.js` implements proof verification. `public/script.js` manages per-form guard requests, reset/completion, retries, and button ownership. Both files ship directly; no frontend bundler is required for them.
- `.wp-plugin-base-admin-ui/src/` contains the child-owned admin app. Build with `npm ci && npm run build` in that directory; committed `assets/admin-ui/` is the production output.
- `.wp-plugin-base/`, `lib/wp-plugin-base/`, managed workflows, and managed documentation come from the upstream foundation. Fix reusable behavior upstream, consume a verified release, and run its synchronization and validation scripts. Keep project guidance here rather than hand-editing managed `CONTRIBUTING.md`.

## Admin toolchain

Use Node.js 22.22.2 or later in the 22.x series, 24.15.0 or later in the 24.x series, or 26+, with npm 10.2.3 or newer. The exact supported range is declared in `.wp-plugin-base-admin-ui/package.json`. Hosted workflows select Node 22; local installations must also meet the patch-version requirement.

Run these commands from the project root:

```sh
npm ci
npm --prefix .wp-plugin-base-admin-ui ci --engine-strict
npm --prefix .wp-plugin-base-admin-ui run lint:js
npm --prefix .wp-plugin-base-admin-ui run build
npx playwright install chromium
npm run test:admin
```

The admin manifest and lockfile remain child-owned when the foundation is updated. Review corresponding starter updates, regenerate this project's lockfile, and preserve its application source and React 18 pins. Audit development dependencies with `npm --prefix .wp-plugin-base-admin-ui audit --include=dev` after dependency changes.

Keep dependency overrides scoped to the consuming major version. In particular, `minimatch` v3 consumers need its callable export while v10 consumers use its named export; forcing every consumer onto one major breaks typed linting. `tests/admin/dependency-contracts.test.cjs` exercises actual TypeScript project-service parsing to protect this contract. The YAML and SVGO overrides likewise preserve their consumers' major-version APIs.

## Context and extension contracts

Use `ASFW_Feature_Registry::normalize_context()` for policy and event contexts. Colons and periods are significant: `wordpress:login` and `custom:contact.v2` must survive normalization. Choose a nonempty resolved context before normalizing a fallback; an empty context normalizes to `generic`.

Register an `ASFW_Integration_Adapter` before the loader runs:

```php
add_action( 'asfw_register_integrations', function ( $registry ) {
    $registry->register( new My_Site_Integration() );
} );
```

Install this registration in an MU plugin or another bootstrap loaded before Anti Spam for WordPress. The loader runs while the plugin file is included; `plugins_loaded` is too late. Adapter IDs must be nonempty and unique. Existing priority order, alphabetical tie-breaking, and availability checks remain unchanged. Implement `id()`, `is_available()`, and `register()` or extend `ASFW_Integration_Adapter_Base`.

The legacy `asfw_integrations` filter contains placement **mode values** (`captcha`, `shortcode`, or disabled values), not integration IDs. Use the registration action for new adapters.

Settings extensions can add section metadata through `asfw_settings_schema_sections` and fields through `asfw_settings_schema_fields`. Registration follows the resolved section order. A feature registered with a new section but no section metadata receives a visible fallback section titled from its first feature; explicit section metadata takes precedence. Extensions own cleanup of their additional options.

## Custom form enforcement

A shortcode only renders browser controls. A custom server handler must validate the matching field and context **before any side effect**. It must separately validate fields, check a WordPress nonce, and apply authorization where its operation requires it. Proof verification is neither authentication nor authorization.

For a public contact form, render the following inside a POST form targeting `admin_url( 'admin-post.php' )`:

```php
<input type="hidden" name="action" value="my_site_contact">
<?php wp_nonce_field( 'my_site_contact', 'my_site_contact_nonce' ); ?>
<label>Your message <textarea name="message" required maxlength="5000"></textarea></label>
<?php echo do_shortcode( '[anti_spam_widget mode="captcha" context="custom:contact" name="asfw"]' ); ?>
<button type="submit">Send</button>
```

Then register the handler in your site plugin:

```php
add_action( 'admin_post_nopriv_my_site_contact', 'my_site_contact_submit' );
add_action( 'admin_post_my_site_contact', 'my_site_contact_submit' );
function my_site_contact_submit() {
    $nonce = isset( $_POST['my_site_contact_nonce'] ) && is_string( $_POST['my_site_contact_nonce'] )
        ? sanitize_text_field( wp_unslash( $_POST['my_site_contact_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'my_site_contact' ) ) {
        wp_die( 'Please reload the form and try again.', '', array( 'response' => 403 ) );
    }
    $message = isset( $_POST['message'] ) && is_string( $_POST['message'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
    if ( '' === $message || strlen( $message ) > 5000 ) {
        wp_die( 'Please enter a message of at most 5000 bytes.', '', array( 'response' => 400 ) );
    }
    if ( ! function_exists( 'asfw_verify_posted_widget' )
        || ! asfw_verify_posted_widget( 'custom:contact', 'asfw' ) ) {
        wp_die( 'Verification failed. Please try again.', '', array( 'response' => 403 ) );
    }
    if ( ! wp_mail( get_option( 'admin_email' ), 'Website contact form', $message ) ) {
        wp_die( 'The message could not be sent. Please try again.', '', array( 'response' => 503 ) );
    }
    wp_safe_redirect( home_url( '/?contact=sent' ) );
    exit;
}
```

This public example intentionally allows visitors. A handler modifying private content must also require the appropriate logged-in user and capability. Nonces on long-lived cached pages require their own refresh strategy.

## AJAX and cached pages

Lazy challenge loading defers proof-challenge requests until verification starts. Turning it off prefetches one challenge when the widget connects; manual widgets still require the visitor to start verification before solving or setting a proof. An explicit `auto="onload"` setting starts verification immediately and takes precedence over lazy loading. Reset, field changes, and disconnection cancel stale requests; expired prefetched challenges are refreshed before verification.

Never clear a proof in a submit handler before a provider has serialized it. For custom AJAX code, dispatch completion on the affected form in `finally`, after the request has settled:

```js
try {
    await sendForm(new FormData(form));
} finally {
    form.dispatchEvent(new CustomEvent('asfw:submission-complete', { bubbles: true }));
}
```

This renews consumed proofs and guard tokens after success, validation errors, or transport failures. Native uncanceled form reset also renews them. Built-in handlers recognize Contact Form 7, Forminator, Gravity Forms rendering, WPForms AJAX completion, Formidable validation errors, HTML Forms parsed responses, and wpDiscuz main and inline form completion. WPForms completion also renews verification after a server validation error or transport failure without clearing credentials while the request is in flight. Formidable supplies the affected form with its document-level `frmFormErrors` event; HTML Forms emits a non-bubbling `hf-submitted` event on the form, observed during capture. wpDiscuz click handlers verify before the provider serializes the request; inline completion matches the actual form identifier. Custom transports must emit the completion event when keeping a form open for another attempt, including failures for which a provider emits no completion event.

Comment policy follows the trusted server dispatch: native and anonymous API submissions use the native WordPress policy, and wpDiscuz submissions use the provider's effective policy, including its native fallback. Public signed context fields cannot downgrade either known route.

WooCommerce login uses its verified server handler to select the policy on account, checkout, and embedded login forms. The dispatch applies to one authentication call; native WordPress login retains its own policy. WooCommerce registration protection covers the account registration form, whose widget is rendered by this integration. Checkout and programmatic customer creation do not use that form and keep their own validation. Native product reviews use the WordPress comment policy, including WooCommerce's `review` comment type.

Math markup is a stable placeholder: the browser obtains a per-visitor challenge from `/wp-json/anti-spam-for-wordpress/v1/math-challenge`. Exclude that route, `/challenge`, and `/submit-delay-token` from all CDN and page-cache rules. Every issuance shares the per-IP challenge quota, across contexts and User Agents. Zero explicitly disables the configured quota. Windows are fixed from the first attempt. Outstanding tokens from the previous transient-based implementation expire on upgrade; users should retry to obtain fresh tokens.

## External data and privacy

Configure both the proxy IP/CIDR list and one `asfw_trusted_proxy_header` setting. The default is `HTTP_X_FORWARDED_FOR`; Cloudflare deployments whose proxy overwrites `CF-Connecting-IP` can select `HTTP_CF_CONNECTING_IP`. Other supported values are `HTTP_FORWARDED` and `HTTP_X_REAL_IP`. Competing forwarding headers are ignored. The selected trusted proxy must overwrite or correctly append that header. Existing `asfw_client_ip` and proxy-list filters remain available.

Disposable refresh rejects malformed, empty, oversized, or unexpectedly reduced lists and preserves the previous list and refresh timestamp. Remote feeds are limited to 8 MiB, 400,000 physical lines, and 200,000 unique domains. Parsing is incremental, and each submission matches all candidate emails against one fresh domain lookup. Content heuristics exclude credential fields. `asfw_disposable_email_minimum_refresh_ratio` defaults to `0.5`; an operator-approved reduction can lower it (bounded from 0 to 1).

Bunny IP-list content is limited to 8 MiB, 400,000 comma/newline segments, and 200,000 unique normalized addresses. Invalid or excessive lists are rejected before mutation, including an addition that would exceed the limit. Bunny mutations coordinate through an ownership lease shared across this WordPress network and reread the remote list while holding it. Separate WordPress installations or external list editors do not share that lease; assign dedicated lists unless external coordination is available. Backoff history outlives its next retry deadline and clears on successful sync. Revocation rediscovers stale remote list IDs and clears local failure/deduplication state after confirming the IP or list is absent. Lost leases, uncertain remote responses, or failed local cleanup remain visible errors and can be retried.

Extensions should emit minimal structured event details. Arrays, objects, JSON strings, and plain strings all pass through bounded privacy sanitation before storage. Identity and sensitive values are hashed; these hashes are pseudonymous, not anonymous. Do not send secrets or personal data to logging hooks merely because sanitation is present.

## Validation

The existing `tests/wp-plugin-base/` suite uses WordPress stubs and runs through the managed PHPUnit bootstrap. It covers service and integration contracts, failures, migrations, policy, and 205-site pagination. It does not prove MySQL concurrency or object-cache behavior.

The separate `scripts/test-wordpress-integration.sh` harness starts an isolated real WordPress multisite environment, uses independent PHP workers sharing a database, and verifies consumption races, quotas, lifecycle, schema failures, and uninstall. Before storage mutations, a real browser verifies core admin controls, authenticated REST, settings save/reload/restoration, Events, and Analytics. Every run checks both database-only behavior and an actual Redis object cache, verified across independent PHP processes. CI tests the advertised minimum WordPress 6.4 on PHP 8.3 and the reviewed current WordPress 7.1.2 / PHP 8.3. Set `ASFW_WP_VERSION` and `ASFW_PHP_VERSION` to reproduce either pair locally. Core downloads are version-specific and checksum-verified. It never uses the stub bootstrap. Docker is required.

Run the frontend browser suite with `npm ci`, `npx playwright install`, and `npm run test:browser`; set `ASFW_BROWSER=firefox` or `webkit` for the other engines. Tests load both production scripts and real jQuery for provider lifecycle events. After installing and building the admin app, run `npm run test:admin` from the project root. Its 13 cases comprise 12 mounted React tests for requests, errors, cancellation, tab changes, and preservation of edits during saves, plus the dependency contract test described above.

After foundation updates run:

```sh
bash .wp-plugin-base/scripts/update/sync_child_repo.sh
bash .wp-plugin-base/scripts/ci/validate_project.sh
```

Use the quality-pack PHP checks and WordPress readiness checks described in `CONTRIBUTING.md`. Do not bypass an approved action-pin mismatch; migrate known pins and update the reusable migration upstream.

Release preparation takes user-facing notes from merged pull requests' `Changelog` sections, falling back to their titles when no section is present. Review the generated `readme.txt` entry and update the dated `CHANGELOG.md` entry in the release pull request before merging it.

## Temporary foundation integration note

The project consumes verified foundation 1.8.3. Its managed PHPCS file temporarily includes the child-owned `.wp-plugin-base-quality-pack/phpcs-child.xml` overlay to retain the existing generated-asset and compatibility-loader exceptions. This is an intentional one-line managed-file divergence while upstream synchronization support awaits release. A 1.8.3 resync removes that include; restore it until upgrading to the foundation release containing overlay support. The overlay does not suppress checks on implementation files.
