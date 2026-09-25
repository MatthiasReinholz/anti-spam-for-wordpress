<?php
/** Real WordPress/database integration tests; run only in the dedicated disposable environment. */

if (!defined('WP_CLI') || !WP_CLI || !defined('ASFW_INTEGRATION_TESTS') || !ASFW_INTEGRATION_TESTS) {
    throw new RuntimeException('The isolated integration environment is required.');
}

function asfw_integration_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    WP_CLI::log('PASS ' . $message);
}

/** Start workers with separate WordPress bootstraps and database connections. */
function asfw_integration_workers(array $input, int $count = 2): array
{
    $directory = sys_get_temp_dir() . '/asfw-workers-' . bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    $input['directory'] = $directory;
    $input['site_url'] = home_url('/');
    file_put_contents($directory . '/input.json', json_encode($input, JSON_THROW_ON_ERROR));
    $processes = array();
    try {
        for ($index = 0; $index < $count; $index++) {
            $process = proc_open(array(PHP_BINARY, __DIR__ . '/worker.php', ABSPATH . 'wp-load.php', $directory . '/input.json', (string) $index), array(0 => array('file', '/dev/null', 'r'), 1 => array('file', $directory . '/stdout-' . $index, 'w'), 2 => array('file', $directory . '/stderr-' . $index, 'w')), $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException('Could not start concurrency worker.');
            }
            $processes[$index] = $process;
        }
        $deadline = microtime(true) + 25;
        while (count(glob($directory . '/ready-*')) !== $count) {
            foreach ($processes as $index => $process) {
                if (!proc_get_status($process)['running']) {
                    throw new RuntimeException('Worker exited before barrier: ' . file_get_contents($directory . '/stderr-' . $index));
                }
            }
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Workers did not reach the concurrency barrier.');
            }
            usleep(10000);
        }
        touch($directory . '/release');
        $results = array();
        $deadline = microtime(true) + 25;
        while (count(glob($directory . '/result-*.json')) !== $count) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Workers did not complete after barrier release.');
            }
            usleep(10000);
        }
        foreach (array_keys($processes) as $index) {
            $results[] = json_decode(file_get_contents($directory . '/result-' . $index . '.json'), true, 512, JSON_THROW_ON_ERROR);
        }
        return $results;
    } finally {
        foreach ($processes as $process) {
            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            }
            proc_close($process);
        }
        foreach (glob($directory . '/*') as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
}

function asfw_integration_solve(array $challenge): string
{
    for ($number = 0; $number <= $challenge['maxnumber']; $number++) {
        if (hash('sha256', $challenge['salt'] . $number) === $challenge['challenge']) {
            return base64_encode(wp_json_encode(array('algorithm' => $challenge['algorithm'], 'challenge' => $challenge['challenge'], 'number' => $number, 'salt' => $challenge['salt'], 'signature' => $challenge['signature'])));
        }
    }
    throw new RuntimeException('Challenge could not be solved.');
}

global $wpdb;
$mode = $args[0] ?? 'database';
$store = new ASFW_Atomic_State_Store();
$plugin = asfw_plugin_instance();
$_SERVER['REMOTE_ADDR'] = '203.0.113.81';
$_SERVER['HTTP_USER_AGENT'] = 'ASFW integration parent';
update_option('asfw_min_submit_time', '0');
update_option('asfw_rate_limit_max_challenges', '30');
update_option('asfw_visitor_binding', 'ip');

if ($mode === 'uninstall') {
    $siteIds = get_sites(array('fields' => 'ids', 'number' => 0));
    foreach ($siteIds as $siteId) {
        switch_to_blog((int) $siteId);
        update_option('asfw_widget_appearance', 'dark');
        wp_schedule_single_event(time() + 60, 'asfw_initialize_network_batch', array(get_current_network_id(), 100 + (int) $siteId));
        wp_schedule_single_event(time() + 60, 'asfw_security_state_cleanup');
        (new ASFW_Atomic_State_Store())->create('uninstall-state', array('value' => 1), 600);
        restore_current_blog();
    }
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        define('WP_UNINSTALL_PLUGIN', plugin_basename(ASFW_FILE));
    }
    require dirname(__DIR__, 2) . '/uninstall.php';
    foreach ($siteIds as $siteId) {
        switch_to_blog((int) $siteId);
        global $wpdb;
        asfw_integration_assert(get_option('asfw_widget_appearance', null) === null && get_option('asfw_secret', null) === null, 'uninstall removes settings on site ' . $siteId);
        asfw_integration_assert((new ASFW_Atomic_State_Store())->read('uninstall-state') === null, 'uninstall removes atomic state on site ' . $siteId);
        asfw_integration_assert(!wp_next_scheduled('asfw_daily_maintenance'), 'uninstall clears maintenance on site ' . $siteId);
        asfw_integration_assert(!wp_next_scheduled('asfw_initialize_network_batch', array(get_current_network_id(), 100 + (int) $siteId)), 'uninstall clears argument-bearing network batches on site ' . $siteId);
        asfw_integration_assert(!wp_next_scheduled('asfw_security_state_cleanup'), 'uninstall clears security cleanup on site ' . $siteId);
        asfw_integration_assert($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'asfw_events'))) === null, 'uninstall removes event table on site ' . $siteId);
        restore_current_blog();
    }
    return;
}

asfw_integration_assert((bool) wp_using_ext_object_cache() === ($mode === 'redis'), 'persistent object-cache mode matches ' . $mode);
update_option('asfw_feature_math_challenge_enabled', 1);
update_option('asfw_feature_math_challenge_mode', 'block');
update_option('asfw_feature_math_challenge_scope_mode', 'all');
$mathMarkup = asfw_render_context_guards('wordpress:login');
asfw_integration_assert(str_contains($mathMarkup, 'data-asfw-math-challenge-url=') && str_contains($mathMarkup, 'data-asfw-math-mode="block"') && str_contains($mathMarkup, 'class="asfw-math-question"'), 'real WordPress KSES preserves math endpoint, mode, and question label');
asfw_integration_assert(str_contains($mathMarkup, 'name="asfw_math_challenge" value=""'), 'cached math markup contains no issued visitor token');
update_option('asfw_feature_math_challenge_mode', 'log');
asfw_integration_assert(!str_contains(asfw_render_context_guards('wordpress:login'), ' required'), 'log-only math does not block browser constraint validation');
update_option('asfw_feature_math_challenge_enabled', 0);

if ($mode === 'redis') {
    $cacheKey = 'cache-' . bin2hex(random_bytes(8));
    wp_cache_set($cacheKey, 'persisted-between-processes', 'asfw-integration', 60);
    $cacheResult = asfw_integration_workers(array('operation' => 'cache-read', 'key' => $cacheKey), 1);
    asfw_integration_assert($cacheResult[0]['success'], 'Redis value is shared across independent PHP processes');
}

$key = 'consume-' . bin2hex(random_bytes(8));
asfw_integration_assert($store->create($key, array('value' => 1), 60) === true, 'atomic state persists in real SQL');
wp_cache_set($store->option_name($key), 'stale option cache', 'options');
$results = asfw_integration_workers(array('operation' => 'consume', 'key' => $key));
asfw_integration_assert(count(array_filter($results, static fn($result) => $result['success'])) === 1, 'two workers consuming the same SQL snapshot produce exactly one success');
asfw_integration_assert($store->read($key) === null, 'stale option caches cannot resurrect consumed state');

$plugin->clear_rate_limit('challenge', 'integration');
$challenge = $plugin->generate_challenge(null, 'low', 300, 'custom:integration');
asfw_integration_assert(is_array($challenge), 'proof issued on real WordPress');
parse_str(wp_parse_url($challenge['salt'], PHP_URL_QUERY), $salt);
$proofKey = $plugin->get_challenge_transient_key($salt['challenge_id']);
$results = asfw_integration_workers(array('operation' => 'verify', 'payload' => asfw_integration_solve($challenge), 'option_name' => $store->option_name($proofKey)));
asfw_integration_assert(count(array_filter($results, static fn($result) => $result['success'])) === 1, 'two complete proof verifiers produce exactly one acceptance');
asfw_integration_assert(count(array_filter($results, static fn($result) => $result['code'] === 'asfw_replay_locked')) === 1, 'the competing full verifier is rejected specifically as a replay');

$plugin->clear_rate_limit('challenge', 'integration');
update_option('asfw_rate_limit_max_challenges', '2');
update_option('asfw_visitor_binding', 'ip_ua');
$limiter = new ASFW_Rate_Limiter();
asfw_integration_assert(is_array($limiter->reserve('challenge', 'initial')), 'reserve the penultimate issuance slot');
$results = asfw_integration_workers(array('operation' => 'quota', 'option_name' => $store->option_name($limiter->get_rate_limit_key('challenge', 'any'))), 4);
asfw_integration_assert(count(array_filter($results, static fn($result) => $result['success'])) === 1, 'four contexts and user agents cannot over-reserve the final client quota slot');
asfw_integration_assert(count(array_filter($results, static fn($result) => $result['code'] === 'asfw_rate_limited')) === 3, 'all quota race losers are explicitly rate limited');
asfw_integration_assert($limiter->get_rate_limit_state('challenge', 'another')['count'] === 2, 'aggregate quota persists the exact accepted count');
update_option('asfw_rate_limit_max_challenges', '30');
update_option('asfw_visitor_binding', 'ip');
$plugin->clear_rate_limit('challenge', 'integration');

$challenge = $plugin->generate_challenge(null, 'low', 300, 'custom:integration');
$payload = asfw_integration_solve($challenge);
$failDelete = static fn($query) => str_starts_with($query, 'DELETE FROM') && str_contains($query, 'asfw_state_') ? 'DELETE FROM asfw_integration_missing_table' : $query;
$oldSuppress = $wpdb->suppress_errors(true);
add_filter('query', $failDelete);
try {
    $failed = $plugin->validate_solution($payload, null, 'custom:integration');
} finally {
    remove_filter('query', $failDelete);
    $wpdb->suppress_errors($oldSuppress);
}
asfw_integration_assert(is_wp_error($failed) && $failed->get_error_code() === 'asfw_state_unavailable', 'SQL failure during consumption fails closed');
asfw_integration_assert($plugin->validate_solution($payload, null, 'custom:integration') === true, 'failed SQL consumption leaves the proof usable for retry');

if ($mode === 'database') {
    asfw_integration_assert(is_multisite(), 'the real environment is multisite');
    $originalBlog = get_current_blog_id();
    $siteIds = get_sites(array('fields' => 'ids', 'number' => 0));
    asfw_integration_assert(count($siteIds) >= 2, 'an existing subsite predates network activation');
    $newSite = wp_insert_site(array('domain' => wp_parse_url(home_url(), PHP_URL_HOST), 'path' => '/created-after-activation/', 'title' => 'New integration site'));
    asfw_integration_assert(!is_wp_error($newSite), 'a new subsite is provisioned by the real WordPress site lifecycle');
    $siteIds[] = (int) $newSite;
    $secrets = array();
    foreach ($siteIds as $siteId) {
        switch_to_blog((int) $siteId);
        $secret = get_option('asfw_secret', '');
        asfw_integration_assert(strlen($secret) === 64 && !in_array($secret, $secrets, true), 'site ' . $siteId . ' owns a unique persistent secret');
        $secrets[] = $secret;
        update_option('asfw_feature_bunny_shield_mode', 'off');
        update_option('asfw_bunny_enabled', true);
        update_option('asfw_widget_appearance', 'dark');
        asfw_initialize_site();
        asfw_integration_assert(get_option('asfw_secret') === $secret && get_option('asfw_widget_appearance') === 'dark' && get_option('asfw_feature_bunny_shield_mode') === 'off', 'site ' . $siteId . ' reinitialization preserves secret and explicit settings');
        restore_current_blog();
    }
    asfw_activate(true);
    asfw_integration_assert(get_current_blog_id() === $originalBlog, 'network reactivation restores the caller blog');
    foreach ($siteIds as $siteId) {
        switch_to_blog((int) $siteId);
        wp_schedule_single_event(time() + 60, 'asfw_initialize_network_batch', array(get_current_network_id(), 100 + (int) $siteId));
        restore_current_blog();
    }
    asfw_deactivate(true);
    asfw_integration_assert(get_current_blog_id() === $originalBlog, 'network deactivation restores the caller blog');
    foreach ($siteIds as $siteId) {
        switch_to_blog((int) $siteId);
        asfw_integration_assert(!wp_next_scheduled('asfw_initialize_network_batch', array(get_current_network_id(), 100 + (int) $siteId)), 'deactivation clears argument-bearing network batches on site ' . $siteId);
        restore_current_blog();
    }
    asfw_activate(true);


    $events = new ASFW_Event_Store();
    $wpdb->query($wpdb->prepare('DROP TABLE %i', $events->get_table_name()));
    // Older releases marked version 3 even when table creation failed.
    update_option(ASFW_Event_Store::OPTION_DB_VERSION, '3');
    delete_option('asfw_site_initialized');
    $failDdl = static fn($query) => preg_match('/^(CREATE|ALTER) TABLE/i', $query) && str_contains($query, 'asfw_events') ? 'CREATE TABLE asfw_integration_invalid (' : $query;
    $oldSuppress = $wpdb->suppress_errors(true);
    add_filter('query', $failDdl);
    try {
        $failed = asfw_initialize_site();
    } finally {
        remove_filter('query', $failDdl);
        $wpdb->suppress_errors($oldSuppress);
    }
    asfw_integration_assert($failed === false && get_option(ASFW_Event_Store::OPTION_DB_VERSION) === '3' && !get_option('asfw_site_initialized'), 'failed legacy schema repair preserves its marker and remains uninitialized');
    asfw_integration_assert(asfw_initialize_site() === true && (int) get_option(ASFW_Event_Store::OPTION_DB_VERSION) === ASFW_Event_Store::DB_VERSION && get_option('asfw_site_initialized') === '1', 'legacy schema repair recovers through site initialization on the next attempt');
    $wpdb->query($wpdb->prepare('ALTER TABLE %i MODIFY id bigint(20) unsigned NOT NULL', $events->get_table_name()));
    update_option(ASFW_Event_Store::OPTION_DB_VERSION, '0');
    asfw_integration_assert(is_wp_error($events->maybe_upgrade_schema()) && get_option(ASFW_Event_Store::OPTION_DB_VERSION) === '0', 'schema validation rejects a correctly typed ID without auto-increment');
    $wpdb->query($wpdb->prepare('ALTER TABLE %i MODIFY id bigint(20) unsigned NOT NULL AUTO_INCREMENT', $events->get_table_name()));
    asfw_integration_assert($events->maybe_upgrade_schema() === true, 'schema validation accepts the repaired auto-increment constraint');

}
WP_CLI::success('Real WordPress integration checks passed in ' . $mode . ' mode.');
