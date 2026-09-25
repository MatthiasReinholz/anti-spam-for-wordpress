<?php
declare(strict_types=1);

final class AtomicStateStoreTest extends AsfwPluginTestCase
{
    public function test_insert_and_compare_and_swap_are_authoritative_despite_stale_option_cache(): void
    {
        $store = new ASFW_Atomic_State_Store();
        update_option($store->option_name('one-use'), 'stale cached value');
        $this->assertTrue($store->create('one-use', array('count' => 1), 600));
        $this->assertFalse($store->create('one-use', array('count' => 99), 600));
        $first = $store->read('one-use');
        $this->assertSame(1, $first['value']['count']);
        $this->assertTrue($store->replace('one-use', $first, array('count' => 2)));
        $this->assertFalse($store->replace('one-use', $first, array('count' => 3)));
        $this->assertFalse($store->delete('one-use', $first));
        $latest = $store->read('one-use');
        $this->assertSame(2, $latest['value']['count']);
        $this->assertSame($first['expires_at'], $latest['expires_at']);
        $this->assertTrue($store->delete('one-use', $latest));
        $this->assertFalse($store->delete('one-use', $latest));
        $this->assertNull($store->read('one-use'));
    }

    public function test_expired_owner_cannot_release_a_successor_lease(): void
    {
        $store = new ASFW_Atomic_State_Store();
        $lease = $store->acquire_lease('list', 60);
        $this->assertIsArray($lease);
        $this->assertNull($store->acquire_lease('list', 60));
        $name = $store->option_name('list');
        $expiredRaw = preg_replace('/^[0-9]+/', (string) (time() - 1), $lease['raw']);
        $GLOBALS['asfw_test_atomic_rows']['wp_options'][$name] = $expiredRaw;
        $lease['raw'] = $expiredRaw;
        $successor = $store->acquire_lease('list', 60);
        $this->assertIsArray($successor);
        $this->assertFalse($store->release_lease('list', $lease));
        $this->assertSame($successor, $store->read('list'));
        $this->assertTrue($store->release_lease('list', $successor));
    }

    public function test_expiry_cleanup_is_bounded_and_preserves_live_state(): void
    {
        $store = new ASFW_Atomic_State_Store();
        foreach (array('expired-one', 'expired-two', 'live') as $key) {
            $store->create($key, array('key' => $key), 600);
            if ($key !== 'live') {
                $name = $store->option_name($key);
                $GLOBALS['asfw_test_atomic_rows']['wp_options'][$name] = preg_replace('/^[0-9]+/', (string) (time() - 1), $store->read($key)['raw']);
            }
        }
        $this->assertSame(1, $store->cleanup_expired(1));
        $this->assertSame(1, $store->cleanup_expired(100));
        $this->assertIsArray($store->read('live'));
        $this->assertSame(0, $store->cleanup_expired());
    }

    public function test_site_state_is_isolated_and_network_lease_can_use_explicit_main_site(): void
    {
        $main = new ASFW_Atomic_State_Store(1);
        $site = new ASFW_Atomic_State_Store(2);
        $this->assertTrue($main->create('same-key', array('site' => 1), 60));
        $this->assertNull($site->read('same-key'));
        $this->assertTrue($site->create('same-key', array('site' => 2), 60));
        $this->assertSame(1, $main->read('same-key')['value']['site']);
        $this->assertSame(2, $site->read('same-key')['value']['site']);
    }

    public function test_storage_failures_are_not_missing_state_or_successful_mutations(): void
    {
        $store = new ASFW_Atomic_State_Store();
        $store->create('failure', array('a' => 1), 60);
        $snapshot = $store->read('failure');
        $GLOBALS['asfw_test_atomic_failure'] = true;
        try {
            $this->assertInstanceOf(WP_Error::class, $store->read('failure'));
            $this->assertInstanceOf(WP_Error::class, $store->create('new', array(), 60));
            $this->assertInstanceOf(WP_Error::class, $store->replace('failure', $snapshot, array('a' => 2)));
            $this->assertInstanceOf(WP_Error::class, $store->delete('failure', $snapshot));
            $this->assertInstanceOf(WP_Error::class, $store->acquire_lease('lease'));
            $this->assertInstanceOf(WP_Error::class, $store->cleanup_expired());
        } finally {
            $GLOBALS['asfw_test_atomic_failure'] = false;
        }
        $this->assertSame($snapshot, $store->read('failure'));
    }

    public function test_quota_reservation_rechecks_after_a_competing_worker_takes_last_slot(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_rate_limit_max_challenges, '2');
        $limiter = new ASFW_Rate_Limiter();
        $this->assertIsArray($limiter->reserve('challenge', 'first'));
        $otherResult = null;
        $GLOBALS['asfw_test_atomic_before_query'] = static function ($query) use ($limiter, &$otherResult) {
            if (str_starts_with($query, 'UPDATE')) {
                $GLOBALS['asfw_test_atomic_before_query'] = null;
                $otherResult = $limiter->reserve('challenge', 'attacker-context');
            }
        };
        try {
            $result = $limiter->reserve('challenge', 'legitimate-context');
        } finally {
            $GLOBALS['asfw_test_atomic_before_query'] = null;
        }
        $this->assertIsArray($otherResult);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_rate_limited', $result->get_error_code());
        $this->assertSame(2, $limiter->get_rate_limit_state('challenge', 'any')['count']);
    }

    public function test_changing_context_or_user_agent_cannot_bypass_issuance_quota(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_rate_limit_max_challenges, '1');
        update_option(AntiSpamForWordPressPlugin::$option_visitor_binding, 'ip_ua');
        $this->assertIsArray($this->plugin()->generate_challenge(null, null, null, 'first-context'));
        $_SERVER['HTTP_USER_AGENT'] = 'different browser';
        $result = $this->plugin()->issue_math_challenge('wordpress:login');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_rate_limited', $result->get_error_code());
        $result = $this->plugin()->issue_submit_delay_token('wordpress:login', 1000);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_rate_limited', $result->get_error_code());
        $this->assertSame(1, $this->plugin()->get_rate_limit_state('challenge', 'anything')['count']);
    }

    public function test_unlimited_quota_does_not_create_buckets(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_rate_limit_max_challenges, '0');
        $limiter = new ASFW_Rate_Limiter();
        $this->assertSame(0, $limiter->reserve('challenge', 'any')['limit']);
        $store = new ASFW_Atomic_State_Store();
        $this->assertNull($store->read($limiter->get_rate_limit_key('challenge', 'any')));
    }

    public function test_failed_persistence_never_returns_a_challenge(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_rate_limit_max_challenges, '0');
        $GLOBALS['asfw_test_atomic_failure'] = true;
        try {
            $this->assertInstanceOf(WP_Error::class, $this->plugin()->generate_challenge());
            $this->assertInstanceOf(WP_Error::class, $this->plugin()->issue_math_challenge('wordpress:login'));
            $this->assertInstanceOf(WP_Error::class, $this->plugin()->issue_submit_delay_token('wordpress:login', 1000));
        } finally {
            $GLOBALS['asfw_test_atomic_failure'] = false;
        }
    }

    public function test_invalid_signature_with_forged_expiry_does_not_revoke_a_real_challenge(): void
    {
        $challenge = $this->generateChallenge('custom:contact');
        $payload = $this->solveChallenge($challenge);
        $forged = json_decode(base64_decode($payload), true);
        $forged['salt'] = preg_replace('/expires=[0-9]+/', 'expires=1', $forged['salt']);
        $forged['challenge'] = hash('sha256', $forged['salt'] . $forged['number']);
        $result = $this->plugin()->validate_solution(base64_encode(json_encode($forged)), null, 'custom:contact');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_invalid_signature', $result->get_error_code());
        $this->assertTrue($this->plugin()->validate_solution($payload, null, 'custom:contact'));
    }

    public function test_failed_proof_consumption_never_reports_success_and_can_retry(): void
    {
        $challenge = $this->generateChallenge('custom:contact');
        $payload = $this->solveChallenge($challenge);
        $GLOBALS['asfw_test_atomic_before_query'] = static function ($query) {
            if (str_starts_with($query, 'DELETE FROM')) {
                $GLOBALS['asfw_test_atomic_failure'] = true;
            }
        };
        try {
            $result = $this->plugin()->validate_solution($payload, null, 'custom:contact');
        } finally {
            $GLOBALS['asfw_test_atomic_failure'] = false;
            $GLOBALS['asfw_test_atomic_before_query'] = null;
        }
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_state_unavailable', $result->get_error_code());
        $this->assertTrue($this->plugin()->validate_solution($payload, null, 'custom:contact'));
    }

    public function test_cached_math_markup_contains_no_visitor_state_and_endpoint_is_no_store(): void
    {
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'all');
        $before = $GLOBALS['asfw_test_atomic_rows'];
        $html = $this->plugin()->render_math_challenge_fields('wordpress:login');
        $this->assertSame($before, $GLOBALS['asfw_test_atomic_rows']);
        $this->assertStringContainsString('data-asfw-math-challenge-url=', $html);
        $this->assertStringContainsString('name="asfw_math_challenge" value=""', $html);
        $request = new AsfwRestRequestWithHeaders(array('context' => 'wordpress:login'));
        $request->setHeader('sec-fetch-site', 'same-site');
        $response = asfw_generate_math_challenge_endpoint($request);
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame('no-cache, no-store, max-age=0', $response->get_headers()['Cache-Control']);
        $this->assertGreaterThan(time(), $response->get_data()['expires_at']);
        $_SERVER['REMOTE_ADDR'] = '203.0.113.11';
        $other = asfw_generate_math_challenge_endpoint($request);
        $this->assertNotSame($response->get_data()['challenge_id'], $other->get_data()['challenge_id']);
    }
}
