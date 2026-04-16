<?php
declare(strict_types=1);

final class BunnyShieldTest extends AsfwPluginTestCase
{
    public function test_bunny_defaults_are_seeded_on_activation(): void
    {
        $this->assertFalse((bool) get_option(AntiSpamForWordPressPlugin::$option_bunny_enabled));
        $this->assertSame('', (string) get_option(AntiSpamForWordPressPlugin::$option_bunny_api_key));
        $this->assertSame('', (string) get_option(AntiSpamForWordPressPlugin::$option_bunny_shield_zone_id));
        $this->assertSame('', (string) get_option(AntiSpamForWordPressPlugin::$option_bunny_access_list_id));
        $this->assertTrue((bool) get_option(AntiSpamForWordPressPlugin::$option_bunny_dry_run));
        $this->assertTrue((bool) get_option(AntiSpamForWordPressPlugin::$option_bunny_fail_open));
        $this->assertSame('10', (string) get_option(AntiSpamForWordPressPlugin::$option_bunny_threshold));
        $this->assertSame('3600', (string) get_option(AntiSpamForWordPressPlugin::$option_bunny_dedupe_window));
        $this->assertFalse((bool) get_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled));
        $this->assertSame('off', (string) get_option('asfw_feature_bunny_shield_mode'));
        $this->assertSame('all', (string) get_option('asfw_feature_bunny_shield_scope_mode'));
        $this->assertSame(array(), get_option('asfw_feature_bunny_shield_contexts'));
        $this->assertFalse((bool) get_option('asfw_feature_bunny_shield_background_enabled'));
        $this->assertSame('10', (string) get_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_threshold));
        $this->assertSame('60', (string) get_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_ttl_minutes));
    }

    public function test_bunny_feature_enablement_is_feature_registry_only_while_other_accessors_keep_legacy_fallbacks(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_bunny_enabled, true);
        update_option(AntiSpamForWordPressPlugin::$option_bunny_api_key, 'legacy-api-key');
        update_option(AntiSpamForWordPressPlugin::$option_bunny_shield_zone_id, '42');
        update_option(AntiSpamForWordPressPlugin::$option_bunny_access_list_id, '77');
        update_option(AntiSpamForWordPressPlugin::$option_bunny_dry_run, false);
        update_option(AntiSpamForWordPressPlugin::$option_bunny_fail_open, false);
        update_option(AntiSpamForWordPressPlugin::$option_bunny_threshold, '2');
        update_option(AntiSpamForWordPressPlugin::$option_bunny_dedupe_window, '7200');

        $this->assertFalse($this->plugin()->get_bunny_enabled());
        $this->assertSame('legacy-api-key', $this->plugin()->get_bunny_api_key());
        $this->assertSame(42, $this->plugin()->get_bunny_shield_zone_id());
        $this->assertSame(77, $this->plugin()->get_bunny_access_list_id());
        $this->assertFalse($this->plugin()->get_bunny_dry_run());
        $this->assertFalse($this->plugin()->get_bunny_fail_open());
        $this->assertSame(2, $this->plugin()->get_bunny_threshold());
        $this->assertSame(7200, $this->plugin()->get_bunny_dedupe_window());
    }

    public function test_bunny_feature_explicit_disable_does_not_fallback_to_legacy_enable_flag(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_bunny_enabled, 1);
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled, 0);
        update_option('asfw_feature_bunny_shield_mode', 'block');

        $this->assertFalse(ASFW_Feature_Registry::is_enabled('bunny_shield'));
        $this->assertFalse($this->plugin()->get_bunny_enabled());
    }

    public function test_bunny_feature_explicit_off_mode_does_not_fallback_to_legacy_mode(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_bunny_enabled, 1);
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled, 1);
        update_option('asfw_feature_bunny_shield_mode', 'off');

        $this->assertSame('off', ASFW_Feature_Registry::active_mode('bunny_shield'));
        $this->assertFalse(ASFW_Feature_Registry::is_enabled('bunny_shield'));
    }

    public function test_bunny_background_explicit_disable_does_not_fallback_to_legacy_enable_flag(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_bunny_enabled, 1);
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled, 1);
        update_option('asfw_feature_bunny_shield_mode', 'block');
        update_option('asfw_feature_bunny_shield_background_enabled', 0);

        $this->assertFalse(ASFW_Feature_Registry::background_enabled('bunny_shield'));
        $this->assertFalse($this->plugin()->is_bunny_background_enabled());
    }

    public function test_bunny_api_key_sanitizer_allows_operator_clear(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_bunny_api_key, 'legacy-key');
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key, 'feature-key');

        $this->assertSame('', asfw_sanitize_bunny_api_key_option(''));
    }

    public function test_bunny_client_uses_wp_http_api_with_timeout_and_user_agent(): void
    {
        $client = new ASFW_Bunny_Shield_Client('test-api-key', 42);
        asfw_test_queue_http_response(
            array(
                'response' => array(
                    'code'    => 200,
                    'message' => 'OK',
                ),
                'headers'  => array(),
                'body'     => wp_json_encode(array('customLists' => array())),
            )
        );

        $client->list_access_lists();

        $request = asfw_test_last_http_request();
        $this->assertIsArray($request);
        $this->assertSame('GET', $request['args']['method']);
        $this->assertSame(5, $request['args']['timeout']);
        $this->assertSame('test-api-key', $request['args']['headers']['AccessKey']);
        $this->assertStringContainsString('Anti Spam for WordPress/' . ASFW_VERSION, $request['args']['headers']['User-Agent']);
        $this->assertStringContainsString('Bunny Shield', $request['args']['headers']['User-Agent']);
    }

    public function test_bunny_client_treats_empty_2xx_response_body_as_invalid_response_error(): void
    {
        $client = new ASFW_Bunny_Shield_Client('test-api-key', 42);
        asfw_test_queue_http_response(
            array(
                'response' => array(
                    'code'    => 200,
                    'message' => 'OK',
                ),
                'headers'  => array(),
                'body'     => '',
            )
        );

        $result = $client->list_access_lists();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_bunny_invalid_response', $result->get_error_code());
        $this->assertCount(1, $GLOBALS['asfw_test_http_requests']);
        $this->assertStringContainsString('/shield/shield-zone/42/access-lists', $GLOBALS['asfw_test_http_requests'][0]['url']);
    }

    public function test_bunny_client_treats_non_json_2xx_response_body_as_invalid_response_error(): void
    {
        $client = new ASFW_Bunny_Shield_Client('test-api-key', 42);
        asfw_test_queue_http_response(
            array(
                'response' => array(
                    'code'    => 202,
                    'message' => 'Accepted',
                ),
                'headers'  => array(),
                'body'     => '<html>ok</html>',
            )
        );

        $result = $client->list_access_lists();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_bunny_invalid_response', $result->get_error_code());
        $this->assertCount(1, $GLOBALS['asfw_test_http_requests']);
        $this->assertStringContainsString('/shield/shield-zone/42/access-lists', $GLOBALS['asfw_test_http_requests'][0]['url']);
    }

    public function test_bunny_client_treats_non_json_non_2xx_response_as_http_error(): void
    {
        $client = new ASFW_Bunny_Shield_Client('test-api-key', 42);
        asfw_test_queue_http_response(
            array(
                'response' => array(
                    'code' => 502,
                    'message' => 'Bad Gateway',
                ),
                'headers' => array(),
                'body' => '<html>proxy error</html>',
            )
        );

        $result = $client->list_access_lists();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_bunny_http_error', $result->get_error_code());
    }

    public function test_bunny_module_skips_private_and_reserved_ips(): void
    {
        $this->enableBunnyFeature(array('dry_run' => false));

        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';

        do_action('asfw_verify_result', false, new WP_Error('asfw_test', 'failure'), 'contact-form-7', 'asfw');

        $this->assertCount(0, $GLOBALS['asfw_test_http_requests']);
    }

    public function test_bunny_module_does_not_call_remote_when_action_is_challenge(): void
    {
        $this->enableBunnyFeature(
            array(
                'dry_run' => false,
                'threshold' => '1',
            )
        );
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_action, 'challenge');
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        do_action('asfw_verify_result', false, new WP_Error('asfw_test', 'failure'), 'contact-form-7', 'asfw');

        $this->assertCount(0, $GLOBALS['asfw_test_http_requests']);
    }

    public function test_bunny_module_blocks_after_threshold_and_patches_existing_list(): void
    {
        $this->enableBunnyFeature(
            array(
                'dry_run' => false,
                'threshold' => '2',
            )
        );
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        asfw_test_queue_http_response(
            array(
                'response' => array(
                    'code'    => 200,
                    'message' => 'OK',
                ),
                'headers'  => array(),
                'body'     => wp_json_encode(
                    array(
                        'data' => array(
                            'id'      => 77,
                            'name'    => 'Anti Spam for WordPress',
                            'content' => '',
                            'checksum'=> '',
                        ),
                    )
                ),
            )
        );
        asfw_test_queue_http_response(
            array(
                'response' => array(
                    'code'    => 200,
                    'message' => 'OK',
                ),
                'headers'  => array(),
                'body'     => wp_json_encode(
                    array(
                        'data' => array(
                            'id'      => 77,
                            'name'    => 'Anti Spam for WordPress',
                            'content' => "8.8.8.8",
                            'checksum'=> hash('sha256', "8.8.8.8"),
                        ),
                    )
                ),
            )
        );

        do_action('asfw_verify_result', false, new WP_Error('asfw_test', 'failure'), 'contact-form-7', 'asfw');
        $this->assertCount(0, $GLOBALS['asfw_test_http_requests']);

        do_action('asfw_verify_result', false, new WP_Error('asfw_test', 'failure'), 'contact-form-7', 'asfw');

        $this->assertCount(2, $GLOBALS['asfw_test_http_requests']);
        $this->assertSame('GET', $GLOBALS['asfw_test_http_requests'][0]['args']['method']);
        $this->assertSame('PATCH', $GLOBALS['asfw_test_http_requests'][1]['args']['method']);
        $this->assertStringContainsString('/shield/shield-zone/42/access-lists/77', $GLOBALS['asfw_test_http_requests'][1]['url']);
        $this->assertStringContainsString('8.8.8.8', (string) $GLOBALS['asfw_test_http_requests'][1]['args']['body']);
    }

    public function test_bunny_module_records_fail_open_sync_failures_without_changing_submission_flow(): void
    {
        $this->enableBunnyFeature(
            array(
                'dry_run' => false,
                'fail_open' => true,
                'threshold' => '1',
            )
        );
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        asfw_test_queue_http_response(new WP_Error('asfw_bunny_timeout', 'Timed out.'));

        do_action('asfw_verify_result', false, new WP_Error('asfw_test', 'failure'), 'contact-form-7', 'asfw');

        $failure = get_transient(ASFW_Bunny_Shield_Module::TRANSIENT_LAST_FAILURE);

        $this->assertIsArray($failure);
        $this->assertSame('failed_open', $failure['status']);
        $this->assertSame('asfw_bunny_timeout', $failure['error']['code']);
        $this->assertSame('verification_failed', $failure['reason']);
        $this->assertIsArray(get_transient(ASFW_Bunny_Shield_Module::TRANSIENT_BACKOFF));
    }

    public function test_bunny_sync_failed_action_exposes_failure_payload(): void
    {
        $this->enableBunnyFeature(
            array(
                'dry_run' => false,
                'fail_open' => true,
                'threshold' => '1',
            )
        );
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        asfw_test_queue_http_response(new WP_Error('asfw_bunny_timeout', 'Timed out.'));

        $captured = array();
        $callback = static function ($ip, $reason, array $state, WP_Error $error, array $failure) use (&$captured): void {
            $captured = array(
                'ip' => $ip,
                'reason' => $reason,
                'state' => $state,
                'error' => $error,
                'failure' => $failure,
            );
        };
        add_action('asfw_bunny_sync_failed', $callback, 10, 5);

        try {
            do_action('asfw_verify_result', false, new WP_Error('asfw_test', 'failure'), 'contact-form-7', 'asfw');
        } finally {
            remove_action('asfw_bunny_sync_failed', $callback, 10);
        }

        $this->assertSame('8.8.8.8', $captured['ip']);
        $this->assertSame('verification_failed', $captured['reason']);
        $this->assertIsArray($captured['state']);
        $this->assertInstanceOf(WP_Error::class, $captured['error']);
        $this->assertSame('asfw_bunny_timeout', $captured['error']->get_error_code());
        $this->assertIsArray($captured['failure']);
        $this->assertSame('failed_open', $captured['failure']['status']);
    }

    public function test_bunny_module_handles_malformed_success_response_without_mutating_remote_state(): void
    {
        $this->enableBunnyFeature(
            array(
                'dry_run' => false,
                'fail_open' => true,
                'threshold' => '1',
            )
        );
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        asfw_test_queue_http_response(
            array(
                'response' => array(
                    'code' => 200,
                    'message' => 'OK',
                ),
                'headers' => array(),
                'body' => 'not-json',
            )
        );

        do_action('asfw_verify_result', false, new WP_Error('asfw_test', 'failure'), 'contact-form-7', 'asfw');

        $failure = get_transient(ASFW_Bunny_Shield_Module::TRANSIENT_LAST_FAILURE);
        $this->assertIsArray($failure);
        $this->assertSame('failed_open', $failure['status']);
        $this->assertSame('asfw_bunny_invalid_response', $failure['error']['code']);
        $this->assertCount(1, $GLOBALS['asfw_test_http_requests']);
        $this->assertSame('GET', $GLOBALS['asfw_test_http_requests'][0]['args']['method']);
    }

    public function test_bunny_dry_run_is_logged_when_event_logging_is_enabled(): void
    {
        update_option('asfw_feature_event_logging_enabled', 1);
        update_option('asfw_feature_event_logging_mode', 'log');

        $this->enableBunnyFeature(
            array(
                'dry_run' => true,
                'threshold' => '1',
            )
        );
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        do_action('asfw_verify_result', false, new WP_Error('asfw_test', 'failure'), 'contact-form-7', 'asfw');

        $events = ASFW_Control_Plane::store()->fetch_events(array('type' => 'bunny_dry_run'));
        $this->assertCount(1, $events);
        $this->assertSame('bunny-shield', $events[0]['feature']);
        $this->assertSame('dry_run', $events[0]['decision']);
        $this->assertSame('contact-form-7', $events[0]['context']);
        $this->assertSame(64, strlen((string) $events[0]['ip_hash']));
    }

    public function test_bunny_module_records_fail_closed_sync_failures_for_monitoring(): void
    {
        $this->enableBunnyFeature(
            array(
                'dry_run' => false,
                'fail_open' => false,
                'threshold' => '1',
            )
        );
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        asfw_test_queue_http_response(new WP_Error('asfw_bunny_timeout', 'Timed out.'));

        do_action('asfw_verify_result', false, new WP_Error('asfw_test', 'failure'), 'contact-form-7', 'asfw');

        $failure = get_transient(ASFW_Bunny_Shield_Module::TRANSIENT_LAST_FAILURE);

        $this->assertIsArray($failure);
        $this->assertSame('failed_closed', $failure['status']);
        $this->assertSame('asfw_bunny_timeout', $failure['error']['code']);
        $this->assertSame('verification_failed', $failure['reason']);
        $this->assertIsArray(get_transient(ASFW_Bunny_Shield_Module::TRANSIENT_BACKOFF));
    }

    public function test_bunny_module_records_fail_closed_for_malformed_success_response(): void
    {
        $this->enableBunnyFeature(
            array(
                'dry_run' => false,
                'fail_open' => false,
                'threshold' => '1',
            )
        );
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        asfw_test_queue_http_response(
            array(
                'response' => array(
                    'code' => 200,
                    'message' => 'OK',
                ),
                'headers' => array(),
                'body' => 'not-json',
            )
        );

        do_action('asfw_verify_result', false, new WP_Error('asfw_test', 'failure'), 'contact-form-7', 'asfw');

        $failure = get_transient(ASFW_Bunny_Shield_Module::TRANSIENT_LAST_FAILURE);
        $this->assertIsArray($failure);
        $this->assertSame('failed_closed', $failure['status']);
        $this->assertSame('asfw_bunny_invalid_response', $failure['error']['code']);
        $this->assertIsArray(get_transient(ASFW_Bunny_Shield_Module::TRANSIENT_BACKOFF));
    }

    public function test_bunny_cli_revoke_updates_the_remote_list(): void
    {
        $this->enableBunnyFeature(array('dry_run' => false));
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        asfw_test_queue_http_response(
            array(
                'response' => array(
                    'code'    => 200,
                    'message' => 'OK',
                ),
                'headers'  => array(),
                'body'     => wp_json_encode(
                    array(
                        'data' => array(
                            'id'      => 77,
                            'name'    => 'Anti Spam for WordPress',
                            'content' => "8.8.8.8\n1.1.1.1",
                            'checksum'=> hash('sha256', "8.8.8.8\n1.1.1.1"),
                        ),
                    )
                ),
            )
        );
        asfw_test_queue_http_response(
            array(
                'response' => array(
                    'code'    => 200,
                    'message' => 'OK',
                ),
                'headers'  => array(),
                'body'     => wp_json_encode(
                    array(
                        'data' => array(
                            'id'      => 77,
                            'name'    => 'Anti Spam for WordPress',
                            'content' => "1.1.1.1",
                            'checksum'=> hash('sha256', "1.1.1.1"),
                        ),
                    )
                ),
            )
        );

        $cli = ASFW_Control_Plane::instance()['cli'];
        $result = $cli->bunny(array('revoke', '8.8.8.8'), array('yes' => true));

        $this->assertIsArray($result);
        $this->assertSame('updated', $result['status']);
        $this->assertCount(2, $GLOBALS['asfw_test_http_requests']);
        $this->assertSame('PATCH', $GLOBALS['asfw_test_http_requests'][1]['args']['method']);
        $this->assertStringNotContainsString('8.8.8.8', (string) $GLOBALS['asfw_test_http_requests'][1]['args']['body']);
        $this->assertStringContainsString('1.1.1.1', (string) $GLOBALS['asfw_test_http_requests'][1]['args']['body']);
        $this->assertNotEmpty(WP_CLI::$successes);
    }

    private function enableBunnyFeature(array $overrides = array()): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled, 1);
        update_option('asfw_feature_bunny_shield_mode', $overrides['mode'] ?? 'block');
        update_option('asfw_feature_bunny_shield_background_enabled', $overrides['background_enabled'] ?? 1);
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key, $overrides['api_key'] ?? 'test-api-key');
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_zone_id, $overrides['zone_id'] ?? '42');
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_access_list_id, $overrides['access_list_id'] ?? '77');
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_dry_run, $overrides['dry_run'] ?? true);
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_fail_open, $overrides['fail_open'] ?? true);
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_threshold, $overrides['threshold'] ?? '10');
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_ttl_minutes, $overrides['ttl_minutes'] ?? '60');
    }
}
