<?php
declare(strict_types=1);

final class SubmittedFieldsTest extends AsfwPluginTestCase
{
    private function fields(string $context = 'custom:signup'): array
    {
        $this->seedPostedWidget($context);
        $fields = $_POST;
        $_POST = ['sentinel' => 'unchanged'];
        return $fields;
    }

    public function test_explicit_fields_are_verified_once_without_mutating_request_globals(): void
    {
        $fields = $this->fields();
        self::assertTrue(asfw_verify_submission($fields, 'custom:signup'));
        self::assertSame(['sentinel' => 'unchanged'], $_POST);
        self::assertFalse(asfw_verify_submission($fields, 'custom:signup'));
    }

    public function test_fields_cannot_select_a_different_expected_context(): void
    {
        $fields = $this->fields('custom:other');
        self::assertFalse(asfw_verify_submission($fields, 'custom:signup'));
    }

    /** @dataProvider ProofInputBoundaryTest::nonStringPayloads */
    public function test_explicit_proofs_reject_non_string_values($payload): void
    {
        $fields = $this->fields();
        $fields['asfw'] = $payload;
        self::assertFalse(asfw_verify_submission($fields, 'custom:signup'));
    }

    public function test_malformed_or_filled_honeypots_do_not_bypass_validation(): void
    {
        $fields = $this->fields();
        foreach (['bot', [], null, false] as $trap) {
            $fields['asfw_website'] = $trap;
            self::assertFalse(asfw_verify_submission($fields, 'custom:signup'));
        }
    }

    public function test_explicit_fields_honor_minimum_time_and_optional_context_guards(): void
    {
        update_option('asfw_min_submit_time', '3');
        self::assertFalse(asfw_verify_submission($this->fields(), 'custom:signup'));
        update_option('asfw_min_submit_time', '0');
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        self::assertFalse(asfw_verify_submission($this->fields('wordpress:comments'), 'wordpress:comments'));
    }

    public function test_disposable_email_block_and_event_logging_use_explicit_json_fields(): void
    {
        update_option('asfw_feature_event_logging_enabled', 1);
        update_option('asfw_feature_event_logging_mode', 'log');
        update_option('asfw_feature_disposable_email_enabled', 1);
        update_option('asfw_feature_disposable_email_mode', 'block');
        $fields = $this->fields();
        $fields['email'] = 'spam@trashmail.com';
        self::assertFalse(asfw_verify_submission($fields, 'custom:signup'));
        $events = ASFW_Control_Plane::store()->fetch_events(['type' => 'disposable_email_hit']);
        self::assertCount(1, $events);
        self::assertSame(64, strlen($events[0]['email_hash']));
        self::assertStringNotContainsString('spam@trashmail.com', json_encode($events));
    }

    public function test_json_heuristic_events_keep_only_the_hashed_email_identity(): void
    {
        foreach (['event_logging', 'disposable_email', 'content_heuristics'] as $feature) {
            update_option('asfw_feature_' . $feature . '_enabled', 1);
            update_option('asfw_feature_' . $feature . '_mode', 'log');
        }
        update_option('asfw_content_heuristics_strict', 1);
        $fields = $this->fields();
        $fields['email'] = 'spam@trashmail.com';
        self::assertTrue(asfw_verify_submission($fields, 'custom:signup'));
        $events = ASFW_Control_Plane::store()->fetch_events(['type' => 'disposable_email_hit']);
        self::assertCount(2, $events);
        foreach ($events as $event) {
            self::assertSame(64, strlen($event['email_hash']));
        }
        self::assertStringNotContainsString('spam@trashmail.com', json_encode($events));
    }

    public function test_success_signal_is_not_emitted_for_missing_proof_or_kill_switch_bypass(): void
    {
        $signals = [];
        $listener = static function ($context, $field) use (&$signals): void { $signals[] = [$context, $field]; };
        add_action('asfw_submission_verified', $listener, 10, 2);
        try {
            self::assertFalse(asfw_verify_submission([], 'custom:signup'));
            $fields = $this->fields();
            self::assertTrue(asfw_verify_submission($fields, 'custom:signup'));
            self::assertSame([['custom:signup', 'asfw']], $signals);
            update_option('asfw_kill_switch', 1);
            self::assertFalse(asfw_verify_submission($fields, 'custom:signup'));
            self::assertTrue(asfw_verify_posted_widget('custom:signup'));
            self::assertCount(1, $signals);
        } finally {
            remove_action('asfw_submission_verified', $listener, 10);
        }
    }
}
