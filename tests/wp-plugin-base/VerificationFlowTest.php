<?php
declare(strict_types=1);

final class VerificationFlowTest extends AsfwPluginTestCase
{
    public function test_generate_challenge_and_validate_request_successfully(): void
    {
        $this->seedPostedWidget('contact-form-7');

        $this->assertTrue($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, 'contact-form-7'));
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

    public function test_trusted_proxy_configuration_uses_forwarded_client_ip(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_trusted_proxies, '10.0.0.1');
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.25, 10.0.0.1';

        $this->assertSame('198.51.100.25', $this->plugin()->get_client_ip_address());
    }

    public function test_visitor_binding_can_include_user_agent(): void
    {
        $ipOnlyFingerprint = $this->plugin()->get_client_fingerprint();

        update_option(AntiSpamForWordPressPlugin::$option_visitor_binding, 'ip_ua');
        $ipAndUserAgentFingerprint = $this->plugin()->get_client_fingerprint();

        $this->assertNotSame($ipOnlyFingerprint, $ipAndUserAgentFingerprint);
    }
}
