<?php
declare(strict_types=1);

final class ContactFormSubmissionTest extends AsfwPluginTestCase
{
    private array $hooks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hooks = $GLOBALS['asfw_test_hooks'];
        $GLOBALS['asfw_active_plugins'][] = 'contact-form-7/wp-contact-form-7.php';
        require dirname(__DIR__, 2) . '/integrations/contact-form-7.php';
        update_option('asfw_integration_contact_form_7', 'shortcode');
    }

    protected function tearDown(): void
    {
        $GLOBALS['asfw_test_hooks'] = $this->hooks;
        parent::tearDown();
    }

    public function test_missing_saved_policy_fails_closed_when_protection_is_enabled(): void
    {
        self::assertNull(ASFW_Contact_Form_Policy::current());
        $result = apply_filters('wpcf7_validate', new AsfwContactFormValidationResult());
        self::assertFalse($result->is_valid());
        self::assertArrayHasKey(ASFW_Contact_Form_7::VALIDATION_FIELD, $result->errors);
    }

    public function test_emergency_bypass_does_not_require_a_saved_policy_or_signal_verification(): void
    {
        update_option('asfw_kill_switch', 1);
        $verified = 0;
        add_action('asfw_submission_verified', static function () use (&$verified): void { $verified++; });

        self::assertNull(ASFW_Contact_Form_Policy::current());
        self::assertTrue(apply_filters('wpcf7_validate', new AsfwContactFormValidationResult())->is_valid());
        self::assertSame(0, $verified);
    }

    public function test_existing_validation_errors_do_not_consume_the_proof(): void
    {
        update_option('asfw_integration_contact_form_7', 'captcha');
        $this->seedPostedWidget('contact-form-7');
        $result = new AsfwContactFormValidationResult();
        $result->invalidate(['name' => 'your-email'], 'Invalid email');
        self::assertSame($result, apply_filters('wpcf7_validate', $result));
        self::assertSame(['your-email' => 'Invalid email'], $result->errors);
        self::assertTrue(asfw_verify_posted_widget('contact-form-7'));
    }

    public function test_validation_is_the_only_proof_consumption_boundary(): void
    {
        update_option('asfw_integration_contact_form_7', 'captcha');
        $this->seedPostedWidget('contact-form-7');
        $verified = 0;
        add_action('asfw_submission_verified', static function () use (&$verified): void { $verified++; });
        self::assertTrue(apply_filters('wpcf7_validate', new AsfwContactFormValidationResult())->is_valid());
        self::assertFalse(apply_filters('wpcf7_spam', false));
        self::assertSame(1, $verified);
        self::assertFalse(apply_filters('wpcf7_validate', new AsfwContactFormValidationResult())->is_valid());
    }

    public function test_other_spam_filters_retain_their_decision(): void
    {
        self::assertTrue(apply_filters('wpcf7_spam', true));
    }

    public function test_rendered_form_has_a_dedicated_provider_validation_error_target(): void
    {
        update_option('asfw_integration_contact_form_7', 'captcha');
        $markup = apply_filters('wpcf7_form_elements', '<button class="wpcf7-form-control wpcf7-submit has-spinner">Send</button>');
        self::assertStringContainsString('<asfw-widget', $markup);
        self::assertStringContainsString('data-name="asfw_verification"', $markup);
        self::assertLessThan(strpos($markup, '<button'), strpos($markup, '<asfw-widget'));
        update_option('asfw_kill_switch', 1);
        self::assertSame('Form contents', apply_filters('wpcf7_form_elements', 'Form contents'));
    }
}

/** Minimal provider result interface; the integration fixture uses the actual class. */
final class AsfwContactFormValidationResult
{
    public array $errors = [];

    public function is_valid(): bool { return $this->errors === []; }

    public function invalidate(array $control, string $message): void
    {
        $this->errors[$control['name']] = $message;
    }
}
