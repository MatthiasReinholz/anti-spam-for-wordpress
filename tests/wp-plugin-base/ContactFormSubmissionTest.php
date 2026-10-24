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
        self::assertTrue(apply_filters('wpcf7_spam', false));
    }

    public function test_emergency_bypass_does_not_require_a_saved_policy_or_signal_verification(): void
    {
        update_option('asfw_kill_switch', 1);
        $verified = 0;
        add_action('asfw_submission_verified', static function () use (&$verified): void { $verified++; });

        self::assertNull(ASFW_Contact_Form_Policy::current());
        self::assertFalse(apply_filters('wpcf7_spam', false));
        self::assertSame(0, $verified);
    }

    public function test_emergency_bypass_preserves_another_spam_filters_rejection(): void
    {
        update_option('asfw_kill_switch', 1);
        self::assertTrue(apply_filters('wpcf7_spam', true));
    }
}
