<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

abstract class AsfwPluginTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        asfw_test_reset_state();

        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $_SERVER['HTTP_USER_AGENT'] = 'ASFW PHPUnit';

        update_option(AntiSpamForWordPressPlugin::$option_secret, 'test-secret');
        update_option(AntiSpamForWordPressPlugin::$option_complexity, 'low');
        update_option(AntiSpamForWordPressPlugin::$option_expires, '300');
        update_option(AntiSpamForWordPressPlugin::$option_rate_limit_window, '600');
        update_option(AntiSpamForWordPressPlugin::$option_rate_limit_max_challenges, '30');
        update_option(AntiSpamForWordPressPlugin::$option_rate_limit_max_failures, '10');
        update_option(AntiSpamForWordPressPlugin::$option_honeypot, 1);
        update_option(AntiSpamForWordPressPlugin::$option_kill_switch, 0);
        update_option(AntiSpamForWordPressPlugin::$option_min_submit_time, '0');
        update_option(AntiSpamForWordPressPlugin::$option_visitor_binding, 'ip');
        update_option(AntiSpamForWordPressPlugin::$option_trusted_proxies, '');
    }

    protected function plugin(): AntiSpamForWordPressPlugin
    {
        return AntiSpamForWordPressPlugin::$instance;
    }

    protected function generateChallenge(string $context, bool $countAgainstRateLimit = true): array
    {
        $challenge = $this->plugin()->generate_challenge(null, 'low', 300, $context, $countAgainstRateLimit);

        self::assertIsArray($challenge);

        return $challenge;
    }

    protected function solveChallenge(array $challenge): string
    {
        for ($number = 0; $number <= (int) $challenge['maxnumber']; $number++) {
            $candidate = hash('sha256', $challenge['salt'] . $number);
            if (!hash_equals($candidate, $challenge['challenge'])) {
                continue;
            }

            return base64_encode(
                json_encode(
                    array(
                        'algorithm' => $challenge['algorithm'],
                        'challenge' => $challenge['challenge'],
                        'number' => $number,
                        'salt' => $challenge['salt'],
                        'signature' => $challenge['signature'],
                    )
                )
            );
        }

        self::fail('Could not solve generated challenge.');
    }

    protected function seedPostedWidget(string $context, string $fieldName = 'asfw', ?array $challenge = null): array
    {
        $challenge = $challenge ?? $this->generateChallenge($context);
        $payload = $this->solveChallenge($challenge);

        $_POST[$fieldName] = $payload;
        $_POST[$this->plugin()->get_started_field_name($fieldName)] = (string) (time() - 10);
        $_POST[$this->plugin()->get_context_field_name($fieldName)] = $this->plugin()->normalize_context($context);
        $_POST[$this->plugin()->get_context_signature_field_name($fieldName)] = $this->plugin()->sign_widget_context($context, $fieldName);
        $_POST[$this->plugin()->get_honeypot_field_name($fieldName)] = '';

        return $challenge;
    }

    protected function getChallengeId(array $challenge): string
    {
        $salt = wp_parse_url($challenge['salt']);
        parse_str($salt['query'] ?? '', $params);

        return (string) ($params['challenge_id'] ?? '');
    }
}
