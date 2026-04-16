<?php
declare(strict_types=1);

final class AsfwRestRequestWithHeaders extends WP_REST_Request
{
    /** @var array<string, string> */
    private $headers = array();

    public function setHeader(string $name, string $value): void
    {
        $this->headers[strtolower($name)] = $value;
    }

    public function get_header($name)
    {
        $key = strtolower((string) $name);

        return $this->headers[$key] ?? '';
    }

    public function get_headers()
    {
        return $this->headers;
    }
}

final class VerificationFlowTest extends AsfwPluginTestCase
{
    public function test_public_challenge_endpoint_is_registered_as_a_public_get_route(): void
    {
        $this->assertArrayHasKey('anti-spam-for-wordpress/v1/challenge', $GLOBALS['asfw_test_rest_routes']);
        $this->assertArrayHasKey('anti-spam-for-wordpress/v1/submit-delay-token', $GLOBALS['asfw_test_rest_routes']);

        $route = $GLOBALS['asfw_test_rest_routes']['anti-spam-for-wordpress/v1/challenge'];
        $submitDelayRoute = $GLOBALS['asfw_test_rest_routes']['anti-spam-for-wordpress/v1/submit-delay-token'];

        $this->assertSame(WP_REST_Server::READABLE, $route['methods']);
        $this->assertSame('asfw_generate_challenge_endpoint', $route['callback']);
        $this->assertSame('__return_true', $route['permission_callback']);
        $this->assertArrayHasKey('context', $route['args']);
        $this->assertSame(WP_REST_Server::READABLE, $submitDelayRoute['methods']);
        $this->assertSame('asfw_generate_submit_delay_token_endpoint', $submitDelayRoute['callback']);
        $this->assertSame('__return_true', $submitDelayRoute['permission_callback']);
        $this->assertArrayHasKey('context', $submitDelayRoute['args']);
    }

    public function test_public_challenge_endpoint_returns_no_cache_headers(): void
    {
        $response = asfw_generate_challenge_endpoint(new WP_REST_Request(array('context' => 'contact-form-7')));

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame('no-cache, no-store, max-age=0', $response->get_headers()['Cache-Control']);
        $this->assertSame('SHA-256', $response->get_data()['algorithm']);
    }

    public function test_challenge_origin_classifier_uses_sec_fetch_site_then_origin_then_referer(): void
    {
        $sameSiteRequest = new AsfwRestRequestWithHeaders();
        $sameSiteRequest->setHeader('sec-fetch-site', 'same-site');
        $this->assertSame('same_site', asfw_classify_challenge_request_origin($sameSiteRequest));

        $crossSiteRequest = new AsfwRestRequestWithHeaders();
        $crossSiteRequest->setHeader('sec-fetch-site', 'cross-site');
        $this->assertSame('cross_site', asfw_classify_challenge_request_origin($crossSiteRequest));

        $originRequest = new AsfwRestRequestWithHeaders();
        $originRequest->setHeader('origin', 'https://example.test');
        $this->assertSame('same_site', asfw_classify_challenge_request_origin($originRequest));

        $refererRequest = new AsfwRestRequestWithHeaders();
        $refererRequest->setHeader('referer', 'https://example.test/path');
        $this->assertSame('same_site', asfw_classify_challenge_request_origin($refererRequest));

        $headerless = new AsfwRestRequestWithHeaders();
        $this->assertSame('headerless', asfw_classify_challenge_request_origin($headerless));
    }

    public function test_public_challenge_endpoint_counts_same_site_and_headerless_requests_and_rejects_cross_site(): void
    {
        $sameSiteRequest = new AsfwRestRequestWithHeaders(array('context' => 'contact-form-7'));
        $sameSiteRequest->setHeader('sec-fetch-site', 'same-site');

        $sameSiteResponse = asfw_generate_challenge_endpoint($sameSiteRequest);
        $this->assertInstanceOf(WP_REST_Response::class, $sameSiteResponse);
        $this->assertSame(1, $this->plugin()->get_rate_limit_state('challenge', 'contact-form-7')['count']);

        $headerlessRequest = new AsfwRestRequestWithHeaders(array('context' => 'contact-form-7'));
        $headerlessResponse = asfw_generate_challenge_endpoint($headerlessRequest);
        $this->assertInstanceOf(WP_REST_Response::class, $headerlessResponse);
        $this->assertSame(2, $this->plugin()->get_rate_limit_state('challenge', 'contact-form-7')['count']);

        $crossSiteRequest = new AsfwRestRequestWithHeaders(array('context' => 'contact-form-7'));
        $crossSiteRequest->setHeader('sec-fetch-site', 'cross-site');
        $crossSiteResponse = asfw_generate_challenge_endpoint($crossSiteRequest);
        $this->assertInstanceOf(WP_Error::class, $crossSiteResponse);
        $this->assertSame('asfw_cross_site_challenge_forbidden', $crossSiteResponse->get_error_code());
        $this->assertSame(2, $this->plugin()->get_rate_limit_state('challenge', 'contact-form-7')['count']);
    }

    public function test_submit_delay_token_endpoint_returns_no_cache_headers_and_token_payload(): void
    {
        update_option('asfw_feature_submit_delay_enabled', 1);
        update_option('asfw_feature_submit_delay_mode', 'block');
        update_option('asfw_feature_submit_delay_scope_mode', 'selected');
        update_option('asfw_feature_submit_delay_contexts', array('wordpress:login'));
        update_option(AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms, '5000');
        $request = new AsfwRestRequestWithHeaders(array('context' => 'wordpress:login', 'delay_ms' => '1000'));
        $request->setHeader('sec-fetch-site', 'same-site');

        $response = asfw_generate_submit_delay_token_endpoint($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame('no-cache, no-store, max-age=0', $response->get_headers()['Cache-Control']);
        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('token_id', $data);
        $this->assertArrayHasKey('signature', $data);
        $this->assertArrayHasKey('issued_at_ms', $data);
        $this->assertSame(5000, $data['delay_ms']);
    }

    public function test_submit_delay_token_endpoint_uses_context_scoped_rate_limit(): void
    {
        update_option('asfw_feature_submit_delay_enabled', 1);
        update_option('asfw_feature_submit_delay_mode', 'block');
        update_option('asfw_feature_submit_delay_scope_mode', 'selected');
        update_option('asfw_feature_submit_delay_contexts', array('wordpress:login'));
        update_option(AntiSpamForWordPressPlugin::$option_rate_limit_max_challenges, '1');
        $request = new AsfwRestRequestWithHeaders(array('context' => 'wordpress:login'));
        $request->setHeader('sec-fetch-site', 'same-site');

        $first = asfw_generate_submit_delay_token_endpoint($request);
        $second = asfw_generate_submit_delay_token_endpoint($request);

        $this->assertInstanceOf(WP_REST_Response::class, $first);
        $this->assertInstanceOf(WP_Error::class, $second);
        $this->assertSame('asfw_rate_limited', $second->get_error_code());
        $this->assertSame(1, $this->plugin()->get_rate_limit_state('challenge', 'submit-delay-token:wordpress:login')['count']);
    }

    public function test_submit_delay_token_endpoint_rejects_headerless_requests(): void
    {
        update_option('asfw_feature_submit_delay_enabled', 1);
        update_option('asfw_feature_submit_delay_mode', 'block');
        update_option('asfw_feature_submit_delay_scope_mode', 'selected');
        update_option('asfw_feature_submit_delay_contexts', array('wordpress:login'));

        $request = new AsfwRestRequestWithHeaders(array('context' => 'wordpress:login'));
        $response = asfw_generate_submit_delay_token_endpoint($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame('asfw_cross_site_submit_delay_forbidden', $response->get_error_code());
    }

    public function test_submit_delay_token_endpoint_rejects_unsupported_contexts(): void
    {
        update_option('asfw_feature_submit_delay_enabled', 1);
        update_option('asfw_feature_submit_delay_mode', 'block');
        update_option('asfw_feature_submit_delay_scope_mode', 'all');

        $request = new AsfwRestRequestWithHeaders(array('context' => 'contact-form-7'));
        $request->setHeader('sec-fetch-site', 'same-site');
        $response = asfw_generate_submit_delay_token_endpoint($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame('asfw_submit_delay_context_unsupported', $response->get_error_code());
    }

    public function test_submit_delay_token_endpoint_rejects_inactive_contexts(): void
    {
        update_option('asfw_feature_submit_delay_enabled', 1);
        update_option('asfw_feature_submit_delay_mode', 'block');
        update_option('asfw_feature_submit_delay_scope_mode', 'selected');
        update_option('asfw_feature_submit_delay_contexts', array('wordpress:comments'));

        $request = new AsfwRestRequestWithHeaders(array('context' => 'wordpress:login'));
        $request->setHeader('sec-fetch-site', 'same-site');
        $response = asfw_generate_submit_delay_token_endpoint($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame('asfw_submit_delay_inactive', $response->get_error_code());
    }

    public function test_public_challenge_endpoint_returns_service_unavailable_when_plugin_instance_is_missing(): void
    {
        $plugin = AntiSpamForWordPressPlugin::$instance;
        AntiSpamForWordPressPlugin::$instance = null;

        try {
            $response = asfw_generate_challenge_endpoint(new WP_REST_Request(array('context' => 'contact-form-7')));
        } finally {
            AntiSpamForWordPressPlugin::$instance = $plugin;
        }

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame('asfw_unavailable', $response->get_error_code());
    }

    public function test_generate_challenge_and_validate_request_successfully(): void
    {
        $this->seedPostedWidget('contact-form-7');

        $this->assertTrue($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, 'contact-form-7'));
    }

    public function test_generate_challenge_can_skip_rate_limit_counting(): void
    {
        $challenge = $this->plugin()->generate_challenge(null, 'low', 300, 'contact-form-7', false);

        $this->assertIsArray($challenge);
        $this->assertSame(0, $this->plugin()->get_rate_limit_state('challenge', 'generic')['count']);
    }

    public function test_challenge_rate_limit_is_scoped_per_context(): void
    {
        $this->plugin()->generate_challenge(null, 'low', 300, 'wordpress:login', true);
        $this->plugin()->generate_challenge(null, 'low', 300, 'wordpress:comments', true);

        $this->assertSame(1, $this->plugin()->get_rate_limit_state('challenge', 'wordpress:login')['count']);
        $this->assertSame(1, $this->plugin()->get_rate_limit_state('challenge', 'wordpress:comments')['count']);
    }

    public function test_random_secret_returns_64_hex_characters(): void
    {
        $secret = $this->plugin()->random_secret();

        $this->assertSame(64, strlen($secret));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $secret);
    }

    public function test_validate_request_populates_resolved_context_by_reference(): void
    {
        $this->seedPostedWidget('contact-form-7');

        $resolved_context = null;
        $result = $this->plugin()->validate_request(asfw_get_posted_payload('asfw'), null, null, 'asfw', $resolved_context);

        $this->assertTrue($result);
        $this->assertSame('contact-form-7', $resolved_context);
    }

    public function test_verify_result_action_receives_resolved_context_as_a_trailing_argument(): void
    {
        $this->seedPostedWidget('contact-form-7');

        $captured = null;
        $listener = static function ($success, $result, $context, $field_name, $resolved_context) use (&$captured): void {
            $captured = array(
                'success' => $success,
                'result' => $result,
                'context' => $context,
                'field_name' => $field_name,
                'resolved_context' => $resolved_context,
            );
        };

        add_action(
            'asfw_verify_result',
            $listener,
            10,
            5
        );

        try {
            $this->assertTrue($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, null, 'asfw'));
            $this->assertIsArray($captured);
            $this->assertTrue($captured['success']);
            $this->assertSame('contact-form-7', $captured['resolved_context']);
            $this->assertNull($captured['context']);
            $this->assertSame('asfw', $captured['field_name']);
        } finally {
            remove_action('asfw_verify_result', $listener, 10);
        }
    }

    public function test_verify_result_action_remains_compatible_with_legacy_four_argument_listeners(): void
    {
        $this->seedPostedWidget('contact-form-7');

        $captured = null;
        $listener = static function ($success, $result, $context, $field_name) use (&$captured): void {
            $captured = array(
                'success' => $success,
                'result' => $result,
                'context' => $context,
                'field_name' => $field_name,
            );
        };

        add_action(
            'asfw_verify_result',
            $listener,
            10,
            4
        );

        try {
            $this->assertTrue($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, null, 'asfw'));
            $this->assertIsArray($captured);
            $this->assertTrue($captured['success']);
            $this->assertNull($captured['context']);
            $this->assertSame('asfw', $captured['field_name']);
        } finally {
            remove_action('asfw_verify_result', $listener, 10);
        }
    }

    public function test_verify_result_action_with_four_arguments_still_triggers_builtin_logging_listeners(): void
    {
        update_option('asfw_feature_event_logging_enabled', 1);
        update_option('asfw_feature_event_logging_mode', 'log');
        update_option('asfw_feature_event_logging_scope_mode', 'all');
        update_option('asfw_feature_disposable_email_enabled', 1);
        update_option('asfw_feature_disposable_email_mode', 'log');
        update_option('asfw_feature_disposable_email_scope_mode', 'all');
        $_POST['email'] = 'spam@trashmail.com';

        do_action(
            'asfw_verify_result',
            false,
            new WP_Error('asfw_disposable_email_blocked', 'blocked'),
            'contact-form-7',
            'asfw'
        );

        $store = ASFW_Control_Plane::store();
        $this->assertGreaterThanOrEqual(1, $store->count_events(array('type' => 'verify_failed')));
        $this->assertGreaterThanOrEqual(1, $store->count_events(array('type' => 'disposable_email_hit')));
    }

    public function test_validate_request_rejects_invalid_payload_and_counts_failure(): void
    {
        $_POST[$this->plugin()->get_context_field_name('asfw')] = 'contact-form-7';
        $_POST[$this->plugin()->get_context_signature_field_name('asfw')] = $this->plugin()->sign_widget_context('contact-form-7', 'asfw');
        $_POST[$this->plugin()->get_honeypot_field_name('asfw')] = '';

        $result = $this->plugin()->validate_request('not-base64', null, 'contact-form-7');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame(1, $this->plugin()->get_rate_limit_state('failure', 'contact-form-7')['count']);
    }

    public function test_validate_request_rejects_context_mismatch(): void
    {
        $challenge = $this->generateChallenge('contact-form-7');
        $this->seedPostedWidget('wordpress:login', 'asfw', $challenge);

        $result = $this->plugin()->validate_request(asfw_get_posted_payload('asfw'), null, null, 'asfw');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_context_mismatch', $result->get_error_code());
    }

    public function test_validate_request_rejects_when_posted_context_conflicts_with_explicit_context(): void
    {
        $challenge = $this->generateChallenge('contact-form-7');
        $this->seedPostedWidget('wordpress:login', 'asfw', $challenge);

        $result = $this->plugin()->validate_request(asfw_get_posted_payload('asfw'), null, 'contact-form-7', 'asfw');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_context_mismatch', $result->get_error_code());
    }

    public function test_validate_request_context_resolution_failure_does_not_increment_generic_failure_bucket(): void
    {
        $_POST[$this->plugin()->get_context_field_name('asfw')] = '';
        $_POST[$this->plugin()->get_context_signature_field_name('asfw')] = '';

        $result = $this->plugin()->validate_request('not-base64', null, null, 'asfw');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_missing_context', $result->get_error_code());
        $this->assertSame(0, $this->plugin()->get_rate_limit_state('failure', 'generic')['count']);
        $this->assertSame(1, $this->plugin()->get_rate_limit_state('failure', 'form:asfw')['count']);
    }

    public function test_validate_request_rejects_honeypot_submissions(): void
    {
        $this->seedPostedWidget('contact-form-7');
        $_POST[$this->plugin()->get_honeypot_field_name('asfw')] = 'https://spam.test';

        $result = $this->plugin()->validate_request(asfw_get_posted_payload('asfw'), null, 'contact-form-7');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_honeypot', $result->get_error_code());
    }

    public function test_validate_request_rejects_submissions_that_are_too_fast(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_min_submit_time, '10');
        $this->seedPostedWidget('contact-form-7');

        $result = $this->plugin()->validate_request(asfw_get_posted_payload('asfw'), null, 'contact-form-7');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_submitted_too_fast', $result->get_error_code());
    }

    public function test_challenge_lock_blocks_concurrent_verification(): void
    {
        $challenge = $this->generateChallenge('contact-form-7');
        $challengeId = $this->getChallengeId($challenge);

        $this->assertNotSame('', $challengeId);
        add_option($this->plugin()->get_challenge_lock_key($challengeId), (string) (time() + 30), '', false);

        $result = $this->plugin()->validate_solution($this->solveChallenge($challenge), null, 'contact-form-7');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_replay_locked', $result->get_error_code());
    }

    public function test_failure_rate_limit_blocks_repeated_invalid_submissions(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_rate_limit_max_failures, '1');

        $_POST[$this->plugin()->get_context_field_name('asfw')] = 'contact-form-7';
        $_POST[$this->plugin()->get_context_signature_field_name('asfw')] = $this->plugin()->sign_widget_context('contact-form-7', 'asfw');
        $_POST[$this->plugin()->get_honeypot_field_name('asfw')] = '';

        $this->plugin()->validate_request('not-base64', null, 'contact-form-7');
        $result = $this->plugin()->validate_request('not-base64', null, 'contact-form-7');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_rate_limited', $result->get_error_code());
    }

    public function test_decode_payload_rejects_payloads_over_the_size_limit(): void
    {
        $result = $this->plugin()->decode_payload(base64_encode(str_repeat('a', 8193)));

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_payload_too_large', $result->get_error_code());
    }

    public function test_trusted_proxy_configuration_uses_forwarded_client_ip(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_trusted_proxies, '10.0.0.1');
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.25, 10.0.0.1';

        $this->assertSame('198.51.100.25', $this->plugin()->get_client_ip_address());
    }

    public function test_trusted_proxy_configuration_rejects_spoofed_leftmost_forwarded_for_entries(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_trusted_proxies, '10.0.0.1');
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.200, 198.51.100.25';

        $this->assertSame('198.51.100.25', $this->plugin()->get_client_ip_address());
    }

    public function test_trusted_proxy_configuration_rejects_spoofed_leftmost_forwarded_header_entries(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_trusted_proxies, '10.0.0.1');
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_FORWARDED'] = 'for=203.0.113.200, for=198.51.100.25';

        $this->assertSame('198.51.100.25', $this->plugin()->get_client_ip_address());
    }

    public function test_trusted_proxy_configuration_falls_back_to_remote_addr_when_forward_chain_is_only_trusted_proxies(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_trusted_proxies, '10.0.0.1, 10.0.0.2');
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.2, 10.0.0.1';

        $this->assertSame('10.0.0.1', $this->plugin()->get_client_ip_address());
    }

    public function test_visitor_binding_can_include_user_agent(): void
    {
        $ipOnlyFingerprint = $this->plugin()->get_client_fingerprint();

        update_option(AntiSpamForWordPressPlugin::$option_visitor_binding, 'ip_ua');
        $ipAndUserAgentFingerprint = $this->plugin()->get_client_fingerprint();

        $this->assertNotSame($ipOnlyFingerprint, $ipAndUserAgentFingerprint);
    }
}
