<?php
declare(strict_types=1);

final class ForminatorIntegrationTest extends AsfwPluginTestCase
{
    public function test_paypal_button_filter_accepts_the_providers_single_argument(): void
    {
        update_option('asfw_integration_forminator', 'captcha');
        $markup = '<div class="forminator-row forminator-row-last"><div class="forminator-button-paypal"></div></div>';

        // Forminator passes only the markup when rendering a PayPal button.
        $rendered = apply_filters('forminator_render_button_markup', $markup);

        $this->assertStringContainsString('<asfw-widget', $rendered);
        $this->assertStringContainsString('forminator', $rendered);
        $this->assertStringEndsWith($markup, $rendered);
    }

    public function test_disabled_integration_leaves_single_argument_button_markup_unchanged(): void
    {
        update_option('asfw_integration_forminator', '');
        $markup = '<div class="forminator-row forminator-row-last"><div class="forminator-button-paypal"></div></div>';

        $this->assertSame($markup, apply_filters('forminator_render_button_markup', $markup));
    }
}
