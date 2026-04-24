<?php
declare(strict_types=1);

final class DisposableEmailModuleTest extends AsfwPluginTestCase
{
    private function enableEventLogging(): void
    {
        update_option('asfw_feature_event_logging_enabled', 1);
        update_option('asfw_feature_event_logging_mode', 'log');
        update_option('asfw_feature_event_logging_scope_mode', 'all');
    }

    public function test_disposable_email_runtime_honors_wordpress_comments_scope_and_field_mapping(): void
    {
        $this->enableEventLogging();
        update_option('asfw_feature_disposable_email_enabled', 1);
        update_option('asfw_feature_disposable_email_mode', 'log');
        update_option('asfw_feature_disposable_email_scope_mode', 'selected');
        update_option('asfw_feature_disposable_email_contexts', array('wordpress:comments'));

        $this->seedPostedWidget('wordpress:comments');
        $_POST['email'] = 'spam@trashmail.com';

        $this->assertTrue($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, 'wordpress:comments'));

        $events = ASFW_Control_Plane::store()->fetch_events(array('type' => 'disposable_email_hit'));

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame('disposable-email', $event['feature']);
        $this->assertSame('matched', $event['decision']);
        $this->assertSame('wordpress:comments', $event['context']);
        $this->assertSame(64, strlen((string) $event['email_hash']));

        $details = json_decode((string) $event['details'], true);
        $this->assertIsArray($details);
        $this->assertSame('asfw', $details['field_name']);
        $this->assertSame('log', $details['mode']);
        $this->assertSame(array('email'), $details['candidate_fields']);
        $this->assertSame(array('email'), $details['matched_fields']);
        $this->assertSame(1, (int) $details['candidate_count']);
        $this->assertSame(1, (int) $details['matched_count']);
    }

    public function test_disposable_email_runtime_skips_scope_mismatches(): void
    {
        update_option('asfw_feature_disposable_email_enabled', 1);
        update_option('asfw_feature_disposable_email_mode', 'log');
        update_option('asfw_feature_disposable_email_scope_mode', 'selected');
        update_option('asfw_feature_disposable_email_contexts', array('wordpress:comments'));

        $this->seedPostedWidget('wordpress:register');
        $_POST['user_email'] = 'spam@trashmail.com';

        $this->assertTrue($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, 'wordpress:register'));
        $this->assertSame(0, ASFW_Control_Plane::store()->count_events(array('type' => 'disposable_email_hit')));
    }

    public function test_disposable_email_candidate_filter_can_extend_runtime_checks(): void
    {
        $this->enableEventLogging();
        update_option('asfw_feature_disposable_email_enabled', 1);
        update_option('asfw_feature_disposable_email_mode', 'log');
        update_option('asfw_feature_disposable_email_scope_mode', 'all');

        $callback = static function (array $emails, $context, $post): array {
            unset($context);

            if ('custom-form' !== ($post['asfw_context'] ?? '')) {
                return $emails;
            }

            if (isset($post['custom_email'])) {
                $emails['custom_email'] = $post['custom_email'];
            }

            return $emails;
        };

        add_filter('asfw_candidate_emails', $callback, 10, 3);

        try {
            $this->seedPostedWidget('custom-form');
            $_POST['asfw_context'] = 'custom-form';
            $_POST['custom_email'] = 'spam@trashmail.com';

            $this->assertTrue($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, 'custom-form'));

            $events = ASFW_Control_Plane::store()->fetch_events(array('type' => 'disposable_email_hit'));

            $this->assertCount(1, $events);
            $details = json_decode((string) $events[0]['details'], true);
            $this->assertIsArray($details);
            $this->assertSame(array('custom_email'), $details['candidate_fields']);
            $this->assertSame(array('custom_email'), $details['matched_fields']);
        } finally {
            remove_filter('asfw_candidate_emails', $callback, 10);
        }
    }

    public function test_disposable_email_runtime_uses_fallback_email_field_detection_for_contact_form_7(): void
    {
        $this->enableEventLogging();
        update_option('asfw_feature_disposable_email_enabled', 1);
        update_option('asfw_feature_disposable_email_mode', 'log');
        update_option('asfw_feature_disposable_email_scope_mode', 'all');

        $this->seedPostedWidget('contact-form-7');
        $_POST['your-email'] = 'spam@trashmail.com';

        $this->assertTrue($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, 'contact-form-7'));

        $events = ASFW_Control_Plane::store()->fetch_events(array('type' => 'disposable_email_hit'));
        $this->assertCount(1, $events);
        $this->assertSame('contact-form-7', $events[0]['context']);

        $details = json_decode((string) $events[0]['details'], true);
        $this->assertIsArray($details);
        $this->assertTrue((bool) $details['fallback']);
        $this->assertSame(array('your-email'), $details['candidate_fields']);
        $this->assertSame(array('your-email'), $details['matched_fields']);
    }

    public function test_disposable_email_runtime_uses_fallback_email_field_detection_for_gravityforms(): void
    {
        $this->enableEventLogging();
        update_option('asfw_feature_disposable_email_enabled', 1);
        update_option('asfw_feature_disposable_email_mode', 'log');
        update_option('asfw_feature_disposable_email_scope_mode', 'all');

        $this->seedPostedWidget('gravityforms');
        $_POST['user_email'] = 'spam@trashmail.com';

        $this->assertTrue($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, 'gravityforms'));

        $events = ASFW_Control_Plane::store()->fetch_events(array('type' => 'disposable_email_hit'));
        $this->assertCount(1, $events);
        $this->assertSame('gravityforms', $events[0]['context']);

        $details = json_decode((string) $events[0]['details'], true);
        $this->assertIsArray($details);
        $this->assertTrue((bool) $details['fallback']);
        $this->assertSame(array('user_email'), $details['candidate_fields']);
        $this->assertSame(array('user_email'), $details['matched_fields']);
    }

    public function test_disposable_email_runtime_blocks_contact_form_7_via_fallback_and_counts_context_failure(): void
    {
        $this->enableEventLogging();
        update_option('asfw_feature_disposable_email_enabled', 1);
        update_option('asfw_feature_disposable_email_mode', 'block');
        update_option('asfw_feature_disposable_email_scope_mode', 'all');

        $this->assertSame(0, $this->plugin()->get_rate_limit_state('failure', 'contact-form-7')['count']);
        $this->assertSame(0, $this->plugin()->get_rate_limit_state('failure', 'generic')['count']);

        $this->seedPostedWidget('contact-form-7');
        $_POST['your-email'] = 'spam@trashmail.com';

        $this->assertFalse($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, 'contact-form-7'));
        $this->assertSame(1, $this->plugin()->get_rate_limit_state('failure', 'contact-form-7')['count']);
        $this->assertSame(0, $this->plugin()->get_rate_limit_state('failure', 'generic')['count']);

        $events = ASFW_Control_Plane::store()->fetch_events(array('type' => 'disposable_email_hit'));
        $this->assertCount(1, $events);
        $this->assertSame('blocked', $events[0]['decision']);
        $details = json_decode((string) $events[0]['details'], true);
        $this->assertIsArray($details);
        $this->assertTrue((bool) $details['fallback']);
        $this->assertSame(1, (int) $details['candidate_count']);
        $this->assertSame(1, (int) $details['matched_count']);
    }

    public function test_disposable_email_runtime_blocks_woocommerce_register_on_billing_email_fallback(): void
    {
        $this->enableEventLogging();
        update_option('asfw_feature_disposable_email_enabled', 1);
        update_option('asfw_feature_disposable_email_mode', 'block');
        update_option('asfw_feature_disposable_email_scope_mode', 'all');

        $this->assertSame(0, $this->plugin()->get_rate_limit_state('failure', 'woocommerce:register')['count']);
        $this->assertSame(0, $this->plugin()->get_rate_limit_state('failure', 'generic')['count']);

        $this->seedPostedWidget('woocommerce:register');
        $_POST['email'] = 'clean@example.com';
        $_POST['billing_email'] = 'spam@trashmail.com';

        $this->assertFalse($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, 'woocommerce:register'));
        $this->assertSame(1, $this->plugin()->get_rate_limit_state('failure', 'woocommerce:register')['count']);
        $this->assertSame(0, $this->plugin()->get_rate_limit_state('failure', 'generic')['count']);

        $events = ASFW_Control_Plane::store()->fetch_events(array('type' => 'disposable_email_hit'));

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame('blocked', $event['decision']);
        $this->assertSame('woocommerce:register', $event['context']);

        $details = json_decode((string) $event['details'], true);
        $this->assertIsArray($details);
        $this->assertSame(array('email', 'billing_email'), $details['candidate_fields']);
        $this->assertSame(array('billing_email'), $details['matched_fields']);
        $this->assertSame('block', $details['mode']);
    }

    public function test_disposable_email_logger_ignores_failed_verification_with_non_disposable_error(): void
    {
        update_option('asfw_feature_disposable_email_enabled', 1);
        update_option('asfw_feature_disposable_email_mode', 'log');
        update_option('asfw_feature_disposable_email_scope_mode', 'all');
        $_POST['email'] = 'spam@trashmail.com';

        do_action(
            'asfw_verify_result',
            false,
            new WP_Error('asfw_invalid_signature', 'invalid'),
            'contact-form-7',
            'asfw'
        );

        $this->assertSame(0, ASFW_Control_Plane::store()->count_events(array('type' => 'disposable_email_hit')));
    }

    public function test_disposable_email_hit_is_not_recorded_when_event_logging_is_disabled(): void
    {
        update_option('asfw_feature_disposable_email_enabled', 1);
        update_option('asfw_feature_disposable_email_mode', 'log');
        update_option('asfw_feature_disposable_email_scope_mode', 'all');
        update_option('asfw_feature_event_logging_enabled', 0);
        update_option('asfw_feature_event_logging_mode', 'off');
        $_POST['email'] = 'spam@trashmail.com';

        $this->seedPostedWidget('contact-form-7');

        $this->assertTrue($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, 'contact-form-7'));
        $this->assertSame(0, ASFW_Control_Plane::store()->count_events(array('type' => 'disposable_email_hit')));
    }
}
