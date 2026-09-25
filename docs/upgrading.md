# Updating an existing installation

Version 0.10.2 preserves existing settings and event data. Updating from 0.10.0 or 0.10.1 does not introduce a new database schema or reset configuration. Sites skipping older releases need the checks below: the supported platform, client-IP handling, challenge limits and some legacy settings have changed over the project's lifetime.

## Before updating

1. Confirm **WordPress 6.4 or later and PHP 8.0 or later**. PHP 8.0 became required in 0.3.2; WordPress 6.4 became required in 0.6.2. Upgrade an older platform before installing the current plugin.
2. Back up the database and plugin files, and test that the backup can be restored. Keep a copy of the currently installed plugin package.
3. Try the update on a staging copy with the same theme, form plugins, proxy and cache configuration. Disable staging's automatic Bunny writes or use a dedicated test list so testing cannot change a production access list.
4. Record the enabled placements and guards under **Settings → Anti Spam for WordPress**, including any custom form handlers or configuration scripts. Remain logged in to an administrator account during the production update, and keep hosting or command-line access available.

Current automated tests cover supported WordPress versions, storage behavior and integration contracts. They cannot establish compatibility with every historical installation, theme or commercial form-plugin version. Test the integrations your site actually uses.

## Review changes when skipping releases

### Fresh challenges and cached pages

When upgrading from 0.9.0 or earlier, an already-issued proof, math challenge or submit-delay token may no longer be valid. Visitors should reload the form or use its retry control to obtain fresh verification. Do not expect a form left open during deployment to retain its verification result.

Clear page and CDN caches after replacing the plugin so visitors receive matching markup and scripts. Keep these public challenge routes available and excluded from all page/CDN caching:

- `/wp-json/anti-spam-for-wordpress/v1/challenge`
- `/wp-json/anti-spam-for-wordpress/v1/math-challenge`
- `/wp-json/anti-spam-for-wordpress/v1/submit-delay-token`

The last two routes are used by the corresponding optional guards. Security plugins and proxy rules must allow visitors to request the routes used by your forms. Explicitly cross-site challenge requests are rejected.

### Reverse proxies and shared IP addresses

Under **Security Hardening**, review **Trusted proxies** and **Trusted proxy header**. The plugin now uses one selected forwarding header from a trusted proxy; the default is **X-Forwarded-For**. Other forwarding headers are ignored. If your setup depends on **CF-Connecting-IP**, **X-Real-IP** or **Forwarded**, select that header explicitly and confirm your proxy overwrites it. Trust only the proxy addresses or ranges you control or have verified.

Check that requests through the live proxy resolve to the visitor's IP. A wrong configuration can group visitors under a proxy IP, exhaust their shared quota and, if automatic Bunny escalation is enabled, submit a public proxy IP to the remote access list. Keep **Automatic Bunny sync** off or **Dry run** on until this is verified.

**Max challenges per window** and **Rate limit window** default to **30 requests per 600 seconds**. Proof-of-work, math and submit-delay issuance share that limit per client IP, across forms, contexts and User Agents. A form using all three can consume three requests. Offices, schools and mobile networks may have many visitors behind one public IP. Test that expected traffic fits the configured limit, then choose an appropriate limit deliberately. The upgrade does not automatically relax protection. **Visitor binding** does not divide the shared quota; choosing a limit of **Disabled** turns that quota off.

### Bunny Shield and older configuration scripts

Review **Bunny API key**, **Shield zone ID**, **Access list ID**, **Dry run**, **Fail open** and **Escalation threshold** if Bunny is enabled. Confirm the configured list and desired automatic-sync behavior before enabling remote writes. A blank API-key field in the admin screen means “keep the current key”; it does not reveal whether a key is stored.

Starting in 0.10.0, an existing modern `asfw_feature_bunny_shield_*` option takes precedence over its corresponding legacy `asfw_bunny_*` option, including empty, false and default values. Earlier code could use a conflicting legacy value when the modern value matched its default. For example, modern **Dry run** on with legacy dry-run off now honors the modern setting. Conflicting API keys, zone/list IDs, failure policy and thresholds can also change the effective configuration.

Sites configured through old WP-CLI commands, custom code or direct database updates should review those scripts and use the modern settings. Do not copy all legacy values over modern settings automatically: doing so could revive an obsolete credential or override an administrator's decision to disable remote writes. Verify and save the intended configuration explicitly.

### Native comments on very old installations

Since 0.8.0, native WordPress comment protection defaults to enabled when no comment-placement option has been saved. If upgrading from an older version, review **Protection Placements → Comments**, especially with a custom comment form or comment API. Save an explicit disabled choice if that is your intended policy. Existing explicitly disabled settings are preserved.

## Verify after updating

- Save and reload a harmless setting, then restore its original value. Confirm secrets and enabled placements remain configured as intended.
- In a separate logged-out browser, submit valid and invalid examples for each enabled login, registration, password-reset, comment, commerce and third-party form integration. Include any math or submit-delay guard, AJAX form and custom handler you use. Test repeated submissions and cached pages as well as a fresh page load.
- Keep WordPress scheduled jobs running. Daily maintenance removes expired security state and old events; large backlogs use follow-up batches. On multisite, check representative sites and network settings. Initialization fills missing state per site and also runs when a site is visited; network activation processes sites in batches.
- If event logging is enabled, inspect **Events** and **Analytics** for unexpected failures. With logging disabled, empty views are expected. For Bunny, verify the intended remote list and resolved visitor IP before resuming automatic writes.

## Recover without deleting configuration

If legitimate visitors cannot submit forms, use the **Kill switch** under **Observability and Policy** while investigating, or deactivate the plugin through WordPress or your hosting tools if the admin page is unavailable. Either action temporarily removes this plugin's protection. Use another suitable control during recovery and restore protection after testing.

Existing Bunny access-list entries remain in place after either action. Remove a mistakenly blocked IP through the Bunny dashboard, or run `wp asfw bunny revoke <ip> --yes` while the plugin is loaded. That command explicitly performs remote revocation even when dry run is enabled. Correct the proxy configuration before resuming automatic sync.

Do **not** uninstall or delete the plugin as a rollback step: uninstall removes its settings, event tables and security state across multisite. Deactivation retains the configuration. To roll back, deactivate, replace the plugin files with the retained package, clear caches and test before reactivating. If database restoration is necessary, use the backup and account for site activity since it was taken. A previous release may contain defects fixed by the update, and existing verification tokens may require a fresh attempt after rollback too.

For storage contracts, extension hooks and custom-form enforcement, see the [developer guide](development.md).
