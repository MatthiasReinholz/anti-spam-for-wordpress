<?php
declare(strict_types=1);

/** These exact pre-0.11 signatures also prove subclasses can still be declared. */
class LegacyHeuristicsCompatibilityProbe extends ASFW_Content_Heuristics_Module
{
    public array $legacyCalls = [];

    protected function collect_candidate_text()
    {
        $this->legacyCalls[] = 'collect';
        return parent::collect_candidate_text();
    }

    public function analyze_submission($context = null)
    {
        $this->legacyCalls[] = 'analyze';
        return parent::analyze_submission($context);
    }

    public function inspect_submission($success, $result, $context, $field_name, $resolved_context = null)
    {
        $this->legacyCalls[] = 'inspect';
        parent::inspect_submission($success, $result, $context, $field_name, $resolved_context);
    }
}

class LegacyDisposableCompatibilityProbe extends ASFW_Disposable_Email_Module
{
    public int $legacyCalls = 0;

    public function log_verify_result($success, $result, $context, $field_name, $resolved_context = null)
    {
        ++$this->legacyCalls;
        parent::log_verify_result($success, $result, $context, $field_name, $resolved_context);
    }
}

class LegacyVerifierCompatibilityProbe extends ASFW_Verifier
{
    public array $legacyCalls = [];

    public function validate_request($payload, $hmac_key = null, $context = null, $field_name = 'asfw', &$resolved_context = null)
    {
        $this->legacyCalls[] = 'validate';
        return parent::validate_request($payload, $hmac_key, $context, $field_name, $resolved_context);
    }

    public function resolve_expected_context($expected_context, $field_name = 'asfw')
    {
        $this->legacyCalls[] = 'context';
        return parent::resolve_expected_context($expected_context, $field_name);
    }

    public function validate_submission_guards($context, $field_name = 'asfw')
    {
        $this->legacyCalls[] = 'guards';
        return parent::validate_submission_guards($context, $field_name);
    }
}

class LegacyContextlessVerifierCompatibilityProbe extends ASFW_Verifier
{
    public function validate_request($payload, $hmac_key = null, $context = null, $field_name = 'asfw', &$resolved_context = null)
    {
        return true;
    }
}

final class LegacyModuleCompatibilityTest extends AsfwPluginTestCase
{
    private function enableSignals(): void
    {
        foreach (['event_logging', 'content_heuristics', 'disposable_email'] as $feature) {
            update_option('asfw_feature_' . $feature . '_enabled', 1);
            update_option('asfw_feature_' . $feature . '_mode', 'log');
        }
        update_option('asfw_content_heuristics_strict', 1);
    }

    public function test_native_hook_dispatch_preserves_all_legacy_module_override_points(): void
    {
        $this->enableSignals();
        $_POST = ['message' => 'crypto bitcoin casino loan', 'email' => 'person@trashmail.com'];
        $heuristics = new LegacyHeuristicsCompatibilityProbe(ASFW_Control_Plane::store());
        $disposable = new LegacyDisposableCompatibilityProbe(ASFW_Control_Plane::store());
        $heuristics->register_hooks();
        $disposable->register_hooks();
        try {
            do_action('asfw_verify_result', true, true, 'custom:signup', 'asfw', 'custom:signup', $_POST, false);
            self::assertSame(['inspect', 'analyze', 'collect'], $heuristics->legacyCalls);
            self::assertSame(1, $disposable->legacyCalls);

            // Older four/five-argument emitters and six-argument emitters that do
            // not opt into parsed transport retain native extension dispatch.
            $heuristics->legacyCalls = [];
            do_action('asfw_verify_result', true, true, 'custom:signup', 'asfw');
            do_action('asfw_verify_result', true, true, 'custom:signup', 'asfw', 'custom:signup');
            do_action('asfw_verify_result', true, true, 'custom:signup', 'asfw', 'custom:signup', $_POST);
            self::assertSame(array_merge(...array_fill(0, 3, ['inspect', 'analyze', 'collect'])), $heuristics->legacyCalls);
            self::assertSame(4, $disposable->legacyCalls);
        } finally {
            remove_action('asfw_verify_result', [$heuristics, 'handle_verify_result'], 20);
            remove_action('asfw_verify_result', [$disposable, 'handle_verify_result'], 15);
        }
    }

    public function test_explicit_module_analysis_does_not_dispatch_legacy_methods_or_read_post(): void
    {
        $this->enableSignals();
        $heuristics = new LegacyHeuristicsCompatibilityProbe(ASFW_Control_Plane::store());
        $disposable = new LegacyDisposableCompatibilityProbe(ASFW_Control_Plane::store());
        $_POST = (object) ['message' => 'ambient request must never be read'];
        try {
            self::assertSame(0, $heuristics->analyze_fields('custom:signup', ['message' => 'ordinary text'])['score']);
            $heuristics->handle_verify_result(true, true, 'custom:signup', 'asfw', 'custom:signup', ['message' => 'crypto bitcoin casino loan'], true);
            $disposable->handle_verify_result(true, true, 'custom:signup', 'asfw', 'custom:signup', ['email' => 'person@trashmail.com'], true);
            self::assertSame([], $heuristics->legacyCalls);
            self::assertSame(0, $disposable->legacyCalls);
            self::assertCount(1, ASFW_Control_Plane::store()->fetch_events(['type' => 'content_heuristic_hit']));
            $events = ASFW_Control_Plane::store()->fetch_events(['type' => 'disposable_email_hit']);
            self::assertCount(1, $events);
            self::assertSame(ASFW_Control_Plane::store()->hash_value('person@trashmail.com', 'email'), $events[0]['email_hash']);
        } finally {
            $_POST = [];
        }
    }

    public function test_explicit_adapter_with_missing_fields_never_falls_back_to_post(): void
    {
        $this->enableSignals();
        $heuristics = new LegacyHeuristicsCompatibilityProbe(ASFW_Control_Plane::store());
        $disposable = new LegacyDisposableCompatibilityProbe(ASFW_Control_Plane::store());
        $_POST = (object) ['email' => 'person@trashmail.com'];
        try {
            $heuristics->handle_verify_result(true, true, 'custom:signup', 'asfw', 'custom:signup', null, true);
            $disposable->handle_verify_result(true, true, 'custom:signup', 'asfw', 'custom:signup', null, true);
            self::assertSame([], $heuristics->legacyCalls);
            self::assertSame(0, $disposable->legacyCalls);
            self::assertSame(0, ASFW_Control_Plane::store()->count_events(['type' => 'content_heuristic_hit']));
            self::assertSame(0, ASFW_Control_Plane::store()->count_events(['type' => 'disposable_email_hit']));
        } finally {
            $_POST = [];
        }
    }

    public function test_legacy_verifier_dispatches_overridable_validation_methods(): void
    {
        $this->seedPostedWidget('custom:signup');
        $verifier = new LegacyVerifierCompatibilityProbe();
        self::assertTrue($verifier->verify($_POST['asfw'], null, 'custom:signup'));
        self::assertSame(['validate', 'context', 'guards'], $verifier->legacyCalls);
    }

    public function test_parsed_verifier_bypasses_legacy_transport_and_reports_its_source(): void
    {
        $this->enableSignals();
        $this->seedPostedWidget('custom:signup');
        $fields = $_POST + ['message' => 'crypto bitcoin casino loan'];
        $verifier = new LegacyVerifierCompatibilityProbe();
        $observed = [];
        $listener = static function ($success, $result, $context, $field, $resolved, $submission, $explicit) use (&$observed): void {
            $observed[] = [$submission, $explicit];
        };
        add_action('asfw_verify_result', $listener, 30, 7);
        $_POST = (object) ['sentinel' => 'must not be read'];
        try {
            self::assertTrue($verifier->verify_submission($fields, 'custom:signup'));
            self::assertSame([], $verifier->legacyCalls);
            self::assertSame([[$fields, true]], $observed);
        } finally {
            $_POST = [];
            remove_action('asfw_verify_result', $listener, 30);
        }
    }

    public function test_native_verifier_preserves_verified_fields_as_sixth_hook_argument(): void
    {
        $this->seedPostedWidget('custom:signup');
        $fields = $_POST;
        $observed = [];
        $listener = static function ($success, $result, $context, $field, $resolved, $submission, $explicit) use (&$observed): void {
            $observed[] = [$submission, $explicit];
        };
        add_action('asfw_verify_result', $listener, 30, 7);
        try {
            self::assertTrue((new ASFW_Verifier())->verify($fields['asfw'], null, 'custom:signup'));
            self::assertSame([[$fields, false]], $observed);
        } finally {
            remove_action('asfw_verify_result', $listener, 30);
        }
    }

    public function test_contextless_legacy_validator_keeps_its_result_without_emitting_a_verified_signal(): void
    {
        $legacyEvents = [];
        $signals = [];
        $observer = static function ($success, $result, $context, $field, $resolved) use (&$legacyEvents): void {
            $legacyEvents[] = [$success, $resolved];
        };
        $signal = static function (string $context, string $field) use (&$signals): void {
            $signals[] = [$context, $field];
        };
        add_action('asfw_verify_result', $observer, 30, 5);
        add_action('asfw_submission_verified', $signal, 30, 2);
        try {
            self::assertTrue((new LegacyContextlessVerifierCompatibilityProbe())->verify('custom-proof', null, 'custom:signup'));
            self::assertSame([[true, null]], $legacyEvents);
            self::assertSame([], $signals);
        } finally {
            remove_action('asfw_verify_result', $observer, 30);
            remove_action('asfw_submission_verified', $signal, 30);
        }
    }

    public function test_changing_the_kill_switch_during_a_bypassed_result_cannot_emit_protection_success(): void
    {
        update_option('asfw_kill_switch', 1);
        $signals = [];
        $restoreProtection = static function (): void { update_option('asfw_kill_switch', 0); };
        $signal = static function (string $context, string $field) use (&$signals): void { $signals[] = [$context, $field]; };
        add_action('asfw_verify_result', $restoreProtection, 1, 0);
        add_action('asfw_submission_verified', $signal, 30, 2);
        try {
            self::assertTrue((new ASFW_Verifier())->verify('not-a-proof', null, 'custom:signup'));
            self::assertSame(0, get_option('asfw_kill_switch'));
            self::assertSame([], $signals);
        } finally {
            remove_action('asfw_verify_result', $restoreProtection, 1);
            remove_action('asfw_submission_verified', $signal, 30);
        }
    }
}
