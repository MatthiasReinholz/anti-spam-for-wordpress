<?php
declare(strict_types=1);

final class ContractClassesTest extends AsfwPluginTestCase
{
	public function test_required_e3_contract_classes_and_interface_exist(): void
	{
		$this->assertTrue(class_exists(ASFW_Options::class, false));
		$this->assertTrue(class_exists(ASFW_Context_Helper::class, false));
		$this->assertTrue(class_exists(ASFW_Client_Identity::class, false));
		$this->assertTrue(class_exists(ASFW_Rate_Limiter::class, false));
		$this->assertTrue(class_exists(ASFW_Challenge_Manager::class, false));
		$this->assertTrue(class_exists(ASFW_Verifier::class, false));
		$this->assertTrue(class_exists(ASFW_Widget_Renderer::class, false));
		$this->assertTrue(interface_exists(ASFW_Integration_Adapter::class, false));
		$this->assertTrue(class_exists(ASFW_Integration_Adapter_Base::class, false));
		$this->assertTrue(class_exists(ASFW_Integration_Registry::class, false));
	}

	public function test_wrapper_methods_are_callable_without_fatal_errors(): void
	{
		$options = new ASFW_Options();
		$this->assertSame('test-secret', $options->get_secret());

		$context_helper = new ASFW_Context_Helper();
		$this->assertSame('contact-form-7', $context_helper->normalize_context('Contact Form 7'));

		$client_identity = new ASFW_Client_Identity();
		$this->assertSame('203.0.113.10', $client_identity->get_client_ip_address());

		$rate_limiter = new ASFW_Rate_Limiter();
		$this->assertGreaterThan(0, $rate_limiter->get_rate_limit_window_safe());

		$challenge_manager = new ASFW_Challenge_Manager();
		$this->assertIsArray($challenge_manager->generate_challenge(null, 'low', 300, 'contact-form-7', false));

		$this->seedPostedWidget('contact-form-7');

		$verifier = new ASFW_Verifier();
		$this->assertTrue($verifier->verify_solution(asfw_get_posted_payload('asfw'), null, 'contact-form-7'));

		$widget_renderer = new ASFW_Widget_Renderer();
		$this->assertStringContainsString('asfw-widget', $widget_renderer->render_widget('captcha', false, null, 'asfw', 'contact-form-7'));

		$adapter = new ASFW_Integration_Adapter_Base('contact-form-7', true);
		$this->assertSame('contact-form-7', $adapter->id());
		$this->assertTrue($adapter->is_available());
		$this->assertNull($adapter->register());

		$registry = new ASFW_Integration_Registry();
		$this->assertIsArray($registry->get_bootstrap_paths());
	}
}
