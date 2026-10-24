<?php
declare(strict_types=1);

final class ContactFormPolicyTest extends AsfwPluginTestCase
{
    public static function templates(): array
    {
        return [
            'legacy default' => ['[anti_spam_widget]', ['name' => 'asfw', 'context' => 'form:captcha:asfw']],
            'explicit context and name' => ['[anti_spam_widget mode="captcha" name="contact_guard" context="contact:form-42"]', ['name' => 'contact_guard', 'context' => 'contact:form-42']],
            'normalized name' => ['[anti_spam_widget name="CONTACT_Guard"]', ['name' => 'contact_guard', 'context' => 'form:captcha:contact_guard']],
            'escaped literal ignored' => ['[[anti_spam_widget]] [anti_spam_widget]', ['name' => 'asfw', 'context' => 'form:captcha:asfw']],
            'literal only' => ['[[anti_spam_widget]]', null],
            'duplicate widgets' => ['[anti_spam_widget][anti_spam_widget]', null],
            'no widget' => ['[text your-name][submit Send]', null],
            'invalid field name' => ['[anti_spam_widget name="!!!"]', null],
            'invalid mode' => ['[anti_spam_widget mode="disabled"]', null],
        ];
    }

    /** @dataProvider templates */
    public function test_policy_comes_from_saved_template_using_wordpress_parser(string $template, ?array $expected): void
    {
        update_option('asfw_integration_custom', 'captcha');
        self::assertSame($expected, ASFW_Contact_Form_Policy::from_template($template));
    }

    public function test_policy_respects_the_same_documented_context_filter_as_rendering(): void
    {
        update_option('asfw_integration_custom', 'captcha');
        $filter = static fn($context) => 'site:' . $context;
        add_filter('asfw_widget_context', $filter);
        try {
            self::assertSame(['name' => 'asfw', 'context' => 'site:form:captcha:asfw'], ASFW_Contact_Form_Policy::from_template('[anti_spam_widget]'));
        } finally {
            remove_filter('asfw_widget_context', $filter);
        }
    }

    public function test_disabled_custom_mode_does_not_claim_a_bare_shortcode_is_rendered(): void
    {
        update_option('asfw_integration_custom', '');
        self::assertNull(ASFW_Contact_Form_Policy::from_template('[anti_spam_widget]'));
    }

    public function test_another_form_context_cannot_satisfy_a_saved_form_policy(): void
    {
        $policy = ASFW_Contact_Form_Policy::from_template('[anti_spam_widget mode="captcha" context="contact:form-42"]');
        $this->seedPostedWidget('custom:newsletter');
        self::assertFalse(asfw_verify_posted_widget($policy['context'], $policy['name']));
        $this->seedPostedWidget('contact:form-42');
        self::assertTrue(asfw_verify_posted_widget($policy['context'], $policy['name']));
    }
}
