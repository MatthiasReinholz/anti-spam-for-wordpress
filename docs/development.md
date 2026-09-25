# Project development

The public `AntiSpamForWordPressPlugin` façade delegates to small services in `includes/`. Keep its existing method signatures stable when improving the implementation. Integrations translate provider events into the shared verification contract; security decisions belong on the server.

## Ownership and layout

- `includes/class-asfw-challenge-manager.php`, `class-asfw-verifier.php`, and `class-asfw-rate-limiter.php` issue and validate proof, math, and delay state.
- `includes/class-asfw-atomic-state-store.php` owns short-lived, non-autoloaded `asfw_state_*` database rows. Reads bypass WordPress caches. Mutations compare the exact observed revision; only a successful conditional deletion consumes a proof. A failed database write or exhausted contention retry must never authorize a request. Leases require conditional release by their owner. Daily maintenance removes expired rows in batches of 1,000 and schedules short follow-up batches until the backlog is drained. Production sites should run WordPress cron regularly.
- `includes/lifecycle.php` initializes missing per-site state. Network activation visits 100 sites per batch and schedules continuation; lazy initialization covers sites before a batch reaches them. New sites initialize only when the plugin is active on their network. Reactivation preserves existing settings and secrets.
- `includes/class-asfw-settings-schema.php` defines settings. Add owned settings and runtime options to `ASFW_Option_Inventory::names()` so uninstall removes them. Uninstall visits all sites in bounded pages.
- `public/asfw-widget.js` implements proof verification. `public/script.js` manages per-form guard requests, reset/completion, retries, and button ownership. Both files ship directly; no frontend bundler is required for them.
- `.wp-plugin-base-admin-ui/src/` contains the child-owned admin app. Build with `npm ci && npm run build` in that directory; committed `assets/admin-ui/` is the production output.
- `.wp-plugin-base/`, `lib/wp-plugin-base/`, managed workflows, and managed documentation come from the upstream foundation. Fix reusable behavior upstream, consume a verified release, and run its synchronization and validation scripts. Keep project guidance here rather than hand-editing managed `CONTRIBUTING.md`.

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

Never clear a proof in a submit handler before a provider has serialized it. For custom AJAX code, dispatch completion on the affected form in `finally`, after the request has settled:

```js
try {
    await sendForm(new FormData(form));
} finally {
    form.dispatchEvent(new CustomEvent('asfw:submission-complete', { bubbles: true }));
}
```

This renews consumed proofs and guard tokens after success, validation errors, or transport failures. Native uncanceled form reset also renews them. Built-in handlers recognize Contact Form 7, Forminator, Gravity Forms rendering, and wpDiscuz main and inline form completion. wpDiscuz click handlers verify before the provider serializes the request; inline completion matches the actual form identifier. Custom transports must emit the completion event when keeping a form open for another attempt.

Comment policy follows the trusted server dispatch: native and anonymous API submissions use the native WordPress policy, and wpDiscuz submissions use the provider's effective policy, including its native fallback. Public signed context fields cannot downgrade either known route.

Math markup is a stable placeholder: the browser obtains a per-visitor challenge from `/wp-json/anti-spam-for-wordpress/v1/math-challenge`. Exclude that route, `/challenge`, and `/submit-delay-token` from all CDN and page-cache rules. Every issuance shares the per-IP challenge quota, across contexts and User Agents. Zero explicitly disables the configured quota. Windows are fixed from the first attempt. Outstanding tokens from the previous transient-based implementation expire on upgrade; users should retry to obtain fresh tokens.

## External data and privacy

Configure both the proxy IP/CIDR list and one `asfw_trusted_proxy_header` setting. The default is `HTTP_X_FORWARDED_FOR`; Cloudflare deployments whose proxy overwrites `CF-Connecting-IP` can select `HTTP_CF_CONNECTING_IP`. Other supported values are `HTTP_FORWARDED` and `HTTP_X_REAL_IP`. Competing forwarding headers are ignored. The selected trusted proxy must overwrite or correctly append that header. Existing `asfw_client_ip` and proxy-list filters remain available.

Disposable refresh rejects malformed, empty, oversized, or unexpectedly reduced lists and preserves the previous list and refresh timestamp. `asfw_disposable_email_minimum_refresh_ratio` defaults to `0.5`; an operator-approved reduction can lower it (bounded from 0 to 1).

Bunny mutations coordinate through an ownership lease shared across this WordPress network and reread the remote list while holding it. Separate WordPress installations or external list editors do not share that lease; assign dedicated lists unless external coordination is available. Backoff history outlives its next retry deadline and clears on successful sync.

Extensions should emit minimal structured event details. Arrays, objects, JSON strings, and plain strings all pass through bounded privacy sanitation before storage. Identity and sensitive values are hashed; these hashes are pseudonymous, not anonymous. Do not send secrets or personal data to logging hooks merely because sanitation is present.

## Validation

The existing `tests/wp-plugin-base/` suite uses WordPress stubs and runs through the managed PHPUnit bootstrap. It covers service and integration contracts, failures, migrations, policy, and 205-site pagination. It does not prove MySQL concurrency or object-cache behavior.

The separate `scripts/test-wordpress-integration.sh` harness starts an isolated real WordPress multisite environment, uses independent PHP workers sharing a database, and verifies consumption races, quotas, lifecycle, schema failures, and uninstall. Every run checks both database-only behavior and an actual Redis object cache, verified across independent PHP processes. It never uses the stub bootstrap. Docker is required.

Run the frontend browser suite with `npm ci`, `npx playwright install`, and `npm run test:browser`; set `ASFW_BROWSER=firefox` or `webkit` for the other engines. Tests load both production scripts and real jQuery for provider lifecycle events. After installing and building the admin app, run `npm run test:admin` from the project root. These mounted React tests exercise requests, errors, cancellation, tab changes, and preservation of edits during saves.

After foundation updates run:

```sh
bash .wp-plugin-base/scripts/update/sync_child_repo.sh
bash .wp-plugin-base/scripts/ci/validate_project.sh
```

Use the quality-pack PHP checks and WordPress readiness checks described in `CONTRIBUTING.md`. Do not bypass an approved action-pin mismatch; migrate known pins and update the reusable migration upstream.

## Temporary foundation integration note

The project consumes verified foundation 1.8.3. Its managed PHPCS file temporarily includes the child-owned `.wp-plugin-base-quality-pack/phpcs-child.xml` overlay to retain the existing generated-asset and compatibility-loader exceptions. This is an intentional one-line managed-file divergence while upstream synchronization support awaits release. A 1.8.3 resync removes that include; restore it until upgrading to the foundation release containing overlay support. The overlay does not suppress checks on implementation files.
