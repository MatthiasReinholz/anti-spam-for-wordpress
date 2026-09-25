<?php
declare(strict_types=1);

final class PrivacyNetworkBoundaryTest extends AsfwPluginTestCase
{
    public function test_nested_serialized_errors_cannot_persist_credentials(): void
    {
        $details = array('error_messages' => array(
            '{"password":"private-password","Authorization":"Bearer private-token","attempts":3}',
            '{"password":"malformed-secret",broken-json',
            json_encode(json_encode(array('api_key' => 'double-encoded-secret'))),
        ));
        $store = ASFW_Control_Plane::store();
        $this->assertTrue($store->record_event('fixture', array('details' => $details)));
        $events = $store->fetch_events(array('type' => 'fixture'));
        $stored = $events[0]['details'];
        foreach (array('private-password', 'private-token', 'malformed-secret', 'double-encoded-secret') as $secret) {
            $this->assertStringNotContainsString($secret, $stored);
        }
        $sanitized = json_decode($stored, true)['error_messages'];
        $this->assertSame(3, $sanitized[0]['attempts']);
        $this->assertSame('[truncated]', $sanitized[1]);
        $this->assertSame(asfw_hash_value('private-password', 'error_messages.0.password'), $sanitized[0]['password']);
        $this->assertSame(asfw_hash_value('double-encoded-secret', 'error_messages.2.api_key'), $sanitized[2]['api_key']);
    }

    public function test_nested_json_shares_node_and_depth_limits_with_containing_details(): void
    {
        $nested = json_encode(array('token' => 'deep-secret'));
        for ($depth = 0; $depth < 10; $depth++) {
            $nested = json_encode(array('nested' => $nested));
        }
        $result = asfw_sanitize_event_details(array('data' => $nested));
        $this->assertStringNotContainsString('deep-secret', json_encode($result));
        $this->assertStringContainsString('[truncated]', json_encode($result));

        $details = array('first' => json_encode(array_fill(0, 150, 'first batch')), 'second' => json_encode(array_fill(0, 150, 'second batch')));
        $result = asfw_sanitize_event_details($details);
        $this->assertCount(150, $result['first']);
        $this->assertLessThan(50, count($result['second']));
        $this->assertTrue($result['second']['_truncated']);
        $this->assertSame('[truncated]', asfw_sanitize_event_details(array('error' => str_repeat('x', 8193)))['error']);
    }

    public function test_credential_only_posts_do_not_generate_heuristic_events(): void
    {
        update_option('asfw_feature_content_heuristics_enabled', 1);
        update_option('asfw_feature_content_heuristics_mode', 'log');
        update_option('asfw_feature_event_logging_enabled', 1);
        update_option('asfw_feature_event_logging_mode', 'log');
        $module = new ASFW_Content_Heuristics_Module(ASFW_Control_Plane::store());
        foreach (array('pwd', 'user_pass', 'user_password', 'password', 'pass1', 'pass2', 'password_1', 'password_2', 'password_current', 'password_confirmation', 'auth_token', 'api_key', 'g-recaptcha-response', '_wpnonce') as $field) {
            $_POST = array($field => 'crypto-bitcoin-casino-loan');
            $analysis = $module->analyze_submission('wordpress:login');
            $this->assertSame(0, $analysis['score'], $field);
            $this->assertSame(0, $analysis['fields'], $field);
            $module->inspect_submission(true, true, 'wordpress:login', 'asfw');
        }
        $this->assertSame(0, ASFW_Control_Plane::store()->count_events(array('type' => 'content_heuristic_hit')));

        $_POST = array('message' => 'crypto-bitcoin-casino-loan', 'email' => 'spam@trashmail.com');
        $analysis = $module->analyze_submission('contact-form-7');
        $this->assertSame(4, $analysis['score']);
        $this->assertSame(2, $analysis['fields']);
        $this->assertFalse(asfw_field_is_credential('password_attempts'));
        $this->assertFalse(asfw_field_is_credential('api_key_count'));
    }

    public function test_comment_heavy_feed_is_rejected_without_replacing_last_good_data(): void
    {
        $body = str_repeat("#\n", 4 * 1024 * 1024);
        $this->assertRejectedFeed($body, 'asfw_disposable_too_many_lines');
    }

    public function test_domain_cardinality_limit_preserves_last_good_data(): void
    {
        $body = '';
        for ($index = 0; $index <= ASFW_Disposable_Email_Module::MAX_REMOTE_DOMAINS; $index++) {
            $body .= 'd' . $index . ".example\n";
        }
        $this->assertRejectedFeed($body, 'asfw_disposable_too_many_domains');
    }

    public function test_incremental_feed_parser_supports_all_line_endings_and_duplicates(): void
    {
        $module = ASFW_Control_Plane::disposable_module();
        update_option(ASFW_Disposable_Email_Module::OPTION_DOMAINS, array('old.example'));
        asfw_test_queue_http_response(array('response' => array('code' => 200), 'body' => "# comment\r\n\rFIRST.EXAMPLE\nsecond.example\r\nfirst.example\rthird.example"));
        $this->assertSame(array('first.example', 'second.example', 'third.example'), $module->refresh_from_source(true));
        $this->assertNull($module->get_last_refresh_error());
    }

    public function test_generated_privacy_text_describes_current_security_state_storage(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_privacy_legal_basis, ASFW_Privacy_Policy_Text::LEGAL_BASIS_CONSENT);
        $text = ASFW_Privacy_Policy_Text::payload()['text'];
        $this->assertStringContainsString('private WordPress database options with expiration times', $text);
        $this->assertStringContainsString('maintenance removes expired records', $text);
        $this->assertStringContainsString('Uninstalling the plugin also removes legacy transient records', $text);
        $this->assertStringNotContainsString('state stored in WordPress transients', $text);
    }

    public function test_each_submission_builds_one_fresh_domain_lookup_for_all_candidates(): void
    {
        $module = new class(ASFW_Control_Plane::store()) extends ASFW_Disposable_Email_Module {
            public $domainReads = 0;

            public function get_domains() {
                ++$this->domainReads;
                return parent::get_domains();
            }
        };
        update_option('asfw_feature_disposable_email_enabled', 1);
        update_option('asfw_feature_disposable_email_mode', 'log');
        $module->set_domains(array('trashmail.com'));
        $post = array();
        for ($index = 0; $index < 1000; $index++) {
            $post['email_' . $index] = 'ordinary@example.com';
        }
        $post['email_999'] = 'person@trashmail.com';
        $analysis = $module->analyze_submission('contact-form-7', $post);
        $this->assertSame(1, $module->domainReads);
        $this->assertSame(array('email_999'), $analysis['matched_fields']);
        $this->assertSame(array('person@trashmail.com'), $analysis['matched_emails']);

        $_POST = $post;
        $module->domainReads = 0;
        $heuristics = new ASFW_Content_Heuristics_Module(ASFW_Control_Plane::store(), $module);
        $analysis = $heuristics->analyze_submission('contact-form-7');
        $this->assertSame(1, $module->domainReads);
        $this->assertSame(2, $analysis['score']);
        $this->assertSame(array('disposable_email:email_999'), $analysis['reasons']);

        $module->set_domains(array('example.com'));
        $module->domainReads = 0;
        $analysis = $module->analyze_submission('contact-form-7', $post);
        $this->assertSame(1, $module->domainReads);
        $this->assertSame(999, $analysis['matched_count']);
        $this->assertNotContains('email_999', $analysis['matched_fields']);
        $this->assertTrue($module->is_disposable_email(' PERSON@EXAMPLE.COM '));
        $this->assertFalse($module->is_disposable_email('person@sub.example.com'));
        $this->assertFalse($module->is_disposable_email('person@trashmail.com'));
    }

    private function assertRejectedFeed(string $body, string $errorCode): void
    {
        $module = ASFW_Control_Plane::disposable_module();
        update_option(ASFW_Disposable_Email_Module::OPTION_DOMAINS, array('last-good.example'));
        update_option(ASFW_Disposable_Email_Module::OPTION_LAST_REFRESH, '2020-01-01 00:00:00');
        asfw_test_queue_http_response(array('response' => array('code' => 200), 'body' => $body));
        $this->assertSame(array('last-good.example'), $module->refresh_from_source(true));
        $this->assertSame(array('last-good.example'), get_option(ASFW_Disposable_Email_Module::OPTION_DOMAINS));
        $this->assertSame('2020-01-01 00:00:00', $module->get_last_refresh());
        $this->assertSame($errorCode, $module->get_last_refresh_error()->get_error_code());
        $this->assertTrue($module->needs_refresh());
    }
}
