<?php
declare(strict_types=1);

final class ContextGuardsTest extends AsfwPluginTestCase
{
    public function test_render_context_guards_outputs_math_and_delay_fields_when_enabled_for_context(): void
    {
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'selected');
        update_option('asfw_feature_math_challenge_contexts', array('wordpress:login'));
        update_option('asfw_feature_submit_delay_enabled', 1);
        update_option('asfw_feature_submit_delay_mode', 'block');
        update_option('asfw_feature_submit_delay_scope_mode', 'selected');
        update_option('asfw_feature_submit_delay_contexts', array('wordpress:login'));
        update_option(AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms, '2500');

        $html = asfw_render_context_guards('wordpress:login');

        $this->assertStringContainsString('asfw_math_challenge', $html);
        $this->assertStringContainsString('asfw_submit_delay_token', $html);
        $this->assertStringContainsString('data-asfw-submit-delay-token-url', $html);
        $this->assertStringContainsString('data-asfw-submit-delay-mode="block"', $html);
        $this->assertStringNotContainsString('delay_ms=', $html);
    }

    public function test_math_challenge_blocks_when_missing_in_block_mode(): void
    {
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'all');

        $result = asfw_validate_context_guards('wordpress:login');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_math_missing', $result->get_error_code());
    }

    public function test_math_challenge_log_mode_does_not_block(): void
    {
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'log');
        update_option('asfw_feature_math_challenge_scope_mode', 'all');

        $result = asfw_validate_context_guards('wordpress:login');

        $this->assertTrue($result);
    }

    public function test_math_challenge_valid_submission_passes_validation(): void
    {
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'all');

        $challenge = $this->plugin()->issue_math_challenge('wordpress:login');
        $_POST[$this->plugin()->get_math_challenge_id_field_name()] = $challenge['challenge_id'];
        $_POST[$this->plugin()->get_math_challenge_signature_field_name()] = $challenge['signature'];
        $_POST[$this->plugin()->get_math_challenge_answer_field_name()] = (string) ($challenge['left'] + $challenge['right']);

        $result = asfw_validate_context_guards('wordpress:login');

        $this->assertTrue($result);
    }

    public function test_submit_delay_blocks_early_submissions_in_block_mode(): void
    {
        update_option('asfw_feature_submit_delay_enabled', 1);
        update_option('asfw_feature_submit_delay_mode', 'block');
        update_option('asfw_feature_submit_delay_scope_mode', 'all');
        update_option(AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms, '2500');

        $token = $this->plugin()->issue_submit_delay_token('wordpress:login', 2500);
        $_POST[$this->plugin()->get_submit_delay_token_field_name()] = $token['token_id'];
        $_POST[$this->plugin()->get_submit_delay_signature_field_name()] = $token['signature'];

        $result = asfw_validate_context_guards('wordpress:login');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_submit_delay_too_fast', $result->get_error_code());
    }

    public function test_submit_delay_passes_after_elapsed_threshold(): void
    {
        update_option('asfw_feature_submit_delay_enabled', 1);
        update_option('asfw_feature_submit_delay_mode', 'block');
        update_option('asfw_feature_submit_delay_scope_mode', 'all');
        update_option(AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms, '2500');

        $token = $this->plugin()->issue_submit_delay_token('wordpress:login', 2500);
        $state = get_transient($this->plugin()->get_submit_delay_transient_key($token['token_id']));
        $state['issued_at'] = (int) round(microtime(true) * 1000) - 3000;
        set_transient($this->plugin()->get_submit_delay_transient_key($token['token_id']), $state, 600);

        $_POST[$this->plugin()->get_submit_delay_token_field_name()] = $token['token_id'];
        $_POST[$this->plugin()->get_submit_delay_signature_field_name()] = $token['signature'];

        $result = asfw_validate_context_guards('wordpress:login');

        $this->assertTrue($result);
    }

    public function test_combined_math_and_submit_delay_allows_retry_after_wait_without_reissuing_math_challenge(): void
    {
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'all');
        update_option('asfw_feature_submit_delay_enabled', 1);
        update_option('asfw_feature_submit_delay_mode', 'block');
        update_option('asfw_feature_submit_delay_scope_mode', 'all');
        update_option(AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms, '2500');

        $challenge = $this->plugin()->issue_math_challenge('wordpress:login');
        $token = $this->plugin()->issue_submit_delay_token('wordpress:login', 2500);
        $_POST[$this->plugin()->get_math_challenge_id_field_name()] = $challenge['challenge_id'];
        $_POST[$this->plugin()->get_math_challenge_signature_field_name()] = $challenge['signature'];
        $_POST[$this->plugin()->get_math_challenge_answer_field_name()] = (string) ($challenge['left'] + $challenge['right']);
        $_POST[$this->plugin()->get_submit_delay_token_field_name()] = $token['token_id'];
        $_POST[$this->plugin()->get_submit_delay_signature_field_name()] = $token['signature'];

        $firstResult = asfw_validate_context_guards('wordpress:login');
        $this->assertInstanceOf(WP_Error::class, $firstResult);
        $this->assertSame('asfw_submit_delay_too_fast', $firstResult->get_error_code());

        $delayState = get_transient($this->plugin()->get_submit_delay_transient_key($token['token_id']));
        $delayState['issued_at'] = (int) round(microtime(true) * 1000) - 3000;
        set_transient($this->plugin()->get_submit_delay_transient_key($token['token_id']), $delayState, 600);

        $secondResult = asfw_validate_context_guards('wordpress:login');
        $this->assertTrue($secondResult);
    }

    public function test_submit_delay_scope_mismatch_skips_enforcement(): void
    {
        update_option('asfw_feature_submit_delay_enabled', 1);
        update_option('asfw_feature_submit_delay_mode', 'block');
        update_option('asfw_feature_submit_delay_scope_mode', 'selected');
        update_option('asfw_feature_submit_delay_contexts', array('wordpress:comments'));

        $this->assertTrue(asfw_validate_context_guards('wordpress:login'));
    }

    public function test_wpdiscuz_context_is_supported_by_context_guards(): void
    {
        $this->assertTrue(asfw_is_context_guard_supported('wpdiscuz:comments'));
    }

    public function test_woocommerce_reset_password_context_is_supported_by_context_guards(): void
    {
        $this->assertTrue(asfw_is_context_guard_supported('woocommerce:reset-password'));
    }

    public function test_submit_delay_validation_uses_issued_delay_even_if_setting_changes_after_render(): void
    {
        update_option('asfw_feature_submit_delay_enabled', 1);
        update_option('asfw_feature_submit_delay_mode', 'block');
        update_option('asfw_feature_submit_delay_scope_mode', 'all');
        update_option(AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms, '5000');

        $token = $this->plugin()->issue_submit_delay_token('wordpress:login', 5000);
        $state = get_transient($this->plugin()->get_submit_delay_transient_key($token['token_id']));
        $state['issued_at'] = (int) round(microtime(true) * 1000) - 3000;
        set_transient($this->plugin()->get_submit_delay_transient_key($token['token_id']), $state, 600);

        update_option(AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms, '1000');
        $_POST[$this->plugin()->get_submit_delay_token_field_name()] = $token['token_id'];
        $_POST[$this->plugin()->get_submit_delay_signature_field_name()] = $token['signature'];

        $result = asfw_validate_context_guards('wordpress:login');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_submit_delay_too_fast', $result->get_error_code());
    }

    public function test_math_challenge_validation_rejects_when_challenge_lock_is_already_held(): void
    {
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'all');

        $challenge = $this->plugin()->issue_math_challenge('wordpress:login');
        add_option($this->plugin()->get_challenge_lock_key('math_' . $challenge['challenge_id']), (string) (time() + 30), '', false);

        $_POST[$this->plugin()->get_math_challenge_id_field_name()] = $challenge['challenge_id'];
        $_POST[$this->plugin()->get_math_challenge_signature_field_name()] = $challenge['signature'];
        $_POST[$this->plugin()->get_math_challenge_answer_field_name()] = (string) ($challenge['left'] + $challenge['right']);

        $result = asfw_validate_context_guards('wordpress:login');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_math_replay_locked', $result->get_error_code());
    }

    public function test_math_challenge_invalid_answer_keeps_state_available_for_retry(): void
    {
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'all');

        $challenge = $this->plugin()->issue_math_challenge('wordpress:login');
        $_POST[$this->plugin()->get_math_challenge_id_field_name()] = $challenge['challenge_id'];
        $_POST[$this->plugin()->get_math_challenge_signature_field_name()] = $challenge['signature'];
        $_POST[$this->plugin()->get_math_challenge_answer_field_name()] = '99999';

        $firstResult = asfw_validate_context_guards('wordpress:login');
        $this->assertInstanceOf(WP_Error::class, $firstResult);
        $this->assertSame('asfw_math_incorrect', $firstResult->get_error_code());

        $_POST[$this->plugin()->get_math_challenge_answer_field_name()] = (string) ($challenge['left'] + $challenge['right']);
        $secondResult = asfw_validate_context_guards('wordpress:login');

        $this->assertTrue($secondResult);
    }

    public function test_submit_delay_validation_rejects_when_token_lock_is_already_held(): void
    {
        update_option('asfw_feature_submit_delay_enabled', 1);
        update_option('asfw_feature_submit_delay_mode', 'block');
        update_option('asfw_feature_submit_delay_scope_mode', 'all');
        update_option(AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms, '2500');

        $token = $this->plugin()->issue_submit_delay_token('wordpress:login', 2500);
        add_option($this->plugin()->get_challenge_lock_key('submit_delay_' . $token['token_id']), (string) (time() + 30), '', false);
        $_POST[$this->plugin()->get_submit_delay_token_field_name()] = $token['token_id'];
        $_POST[$this->plugin()->get_submit_delay_signature_field_name()] = $token['signature'];

        $result = asfw_validate_context_guards('wordpress:login');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_submit_delay_replay_locked', $result->get_error_code());
    }
}
