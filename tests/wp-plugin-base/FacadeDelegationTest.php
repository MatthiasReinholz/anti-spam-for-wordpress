<?php
declare(strict_types=1);

class FacadeDelegationOptionsSpy extends ASFW_Options
{
	public array $calls = array();

	public function get_complexity()
	{
		$this->calls[] = array(__FUNCTION__);
		return 'delegated-low';
	}
}

class FacadeDelegationContextSpy extends ASFW_Context_Helper
{
	public array $calls = array();

	public function normalize_context($context)
	{
		$this->calls[] = array(__FUNCTION__, $context);
		return 'normalized:' . strtolower((string) $context);
	}

	public function get_started_field_name($field_name = 'asfw')
	{
		$this->calls[] = array(__FUNCTION__, $field_name);
		return 'started:' . $field_name;
	}
}

class FacadeDelegationClientSpy extends ASFW_Client_Identity
{
	public array $calls = array();

	public function get_client_ip_address()
	{
		$this->calls[] = array(__FUNCTION__);
		return '198.51.100.1';
	}
}

class FacadeDelegationRateSpy extends ASFW_Rate_Limiter
{
	public array $calls = array();

	public function get_rate_limit_state($type, $context)
	{
		$this->calls[] = array(__FUNCTION__, $type, $context);

		return array(
			'count' => 7,
			'limit' => 9,
			'window' => 11,
		);
	}
}

class FacadeDelegationChallengeSpy extends ASFW_Challenge_Manager
{
	public array $calls = array();

	public function random_secret()
	{
		$this->calls[] = array(__FUNCTION__);
		return 'abcdef1234567890';
	}

	public function generate_challenge($hmac_key = null, $complexity = null, $expires = null, $context = null, $count_against_rate_limit = true)
	{
		$this->calls[] = array(__FUNCTION__, $hmac_key, $complexity, $expires, $context, $count_against_rate_limit);

		return array(
			'algorithm' => 'SHA-256',
			'challenge' => 'challenge-token',
			'maxnumber' => 42,
			'salt' => 'salt-token',
			'signature' => 'signature-token',
		);
	}
}

class FacadeDelegationVerifierSpy extends ASFW_Verifier
{
	public array $calls = array();

	public function verify($payload, $hmac_key = null, $context = null, $field_name = 'asfw')
	{
		$this->calls[] = array(__FUNCTION__, $payload, $hmac_key, $context, $field_name);
		return true;
	}
}

class FacadeDelegationRendererSpy extends ASFW_Widget_Renderer
{
	public array $calls = array();

	public function render_widget($mode, $wrap = false, $language = null, $name = null, $context = null)
	{
		$this->calls[] = array(__FUNCTION__, $mode, $wrap, $language, $name, $context);
		return '<asfw-widget data-mode="' . esc_attr((string) $mode) . '"></asfw-widget>';
	}
}

final class FacadeDelegationTest extends AsfwPluginTestCase
{
	public function test_facade_methods_delegate_to_composed_services(): void
	{
		$plugin = $this->plugin();
		$original = $this->captureServices($plugin);

		$options = new FacadeDelegationOptionsSpy();
		$context = new FacadeDelegationContextSpy();
		$client = new FacadeDelegationClientSpy();
		$rate = new FacadeDelegationRateSpy();
		$challenge = new FacadeDelegationChallengeSpy();
		$verifier = new FacadeDelegationVerifierSpy();
		$renderer = new FacadeDelegationRendererSpy();

		try {
			$this->setService($plugin, 'options_service', $options);
			$this->setService($plugin, 'context_helper_service', $context);
			$this->setService($plugin, 'client_identity_service', $client);
			$this->setService($plugin, 'rate_limiter_service', $rate);
			$this->setService($plugin, 'challenge_manager_service', $challenge);
			$this->setService($plugin, 'verifier_service', $verifier);
			$this->setService($plugin, 'widget_renderer_service', $renderer);

			$this->assertSame('delegated-low', $plugin->get_complexity());
			$this->assertSame('normalized:contact-form-7', $plugin->normalize_context('Contact-Form-7'));
			$this->assertSame('198.51.100.1', $plugin->get_client_ip_address());
			$this->assertSame(array('count' => 7, 'limit' => 9, 'window' => 11), $plugin->get_rate_limit_state('failure', 'contact-form-7'));
			$this->assertSame('abcdef1234567890', $plugin->random_secret());
			$this->assertSame(
				array(
					'algorithm' => 'SHA-256',
					'challenge' => 'challenge-token',
					'maxnumber' => 42,
					'salt' => 'salt-token',
					'signature' => 'signature-token',
				),
				$plugin->generate_challenge(null, null, null, 'contact-form-7', false)
			);
			$this->assertTrue($plugin->verify('payload', null, 'contact-form-7', 'custom'));
			$this->assertSame('<asfw-widget data-mode="captcha"></asfw-widget>', $plugin->render_widget('captcha', false, null, 'asfw', 'contact-form-7'));

			$this->assertSame(array(array('get_complexity')), $options->calls);
			$this->assertSame(array(array('normalize_context', 'Contact-Form-7')), $context->calls);
			$this->assertSame(array(array('get_client_ip_address')), $client->calls);
			$this->assertSame(array(array('get_rate_limit_state', 'failure', 'contact-form-7')), $rate->calls);
			$this->assertSame(
				array(
					array('random_secret'),
					array('generate_challenge', null, null, null, 'contact-form-7', false),
				),
				$challenge->calls
			);
			$this->assertSame(array(array('verify', 'payload', null, 'contact-form-7', 'custom')), $verifier->calls);
			$this->assertSame(array(array('render_widget', 'captcha', false, null, 'asfw', 'contact-form-7')), $renderer->calls);
		} finally {
			$this->restoreServices($plugin, $original);
		}
	}

	public function test_facade_runtime_smoke_path_still_verifies_and_renders(): void
	{
		$this->seedPostedWidget('contact-form-7');

		$this->assertTrue($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, 'contact-form-7'));

		$challenge = $this->plugin()->generate_challenge(null, 'low', 300, 'contact-form-7', false);
		$this->assertIsArray($challenge);
		$this->assertSame('SHA-256', $challenge['algorithm']);

		$html = $this->plugin()->render_widget('captcha', false, null, 'asfw', 'contact-form-7');
		$this->assertStringContainsString('asfw-widget', $html);
		$this->assertStringContainsString('data-asfw-context', $html);
	}

	private function captureServices(AntiSpamForWordPressPlugin $plugin): array
	{
		return array(
			'options_service' => $this->readService($plugin, 'options_service'),
			'context_helper_service' => $this->readService($plugin, 'context_helper_service'),
			'client_identity_service' => $this->readService($plugin, 'client_identity_service'),
			'rate_limiter_service' => $this->readService($plugin, 'rate_limiter_service'),
			'challenge_manager_service' => $this->readService($plugin, 'challenge_manager_service'),
			'verifier_service' => $this->readService($plugin, 'verifier_service'),
			'widget_renderer_service' => $this->readService($plugin, 'widget_renderer_service'),
		);
	}

	private function setService(AntiSpamForWordPressPlugin $plugin, string $property, object $service): void
	{
		$reflection = new ReflectionProperty($plugin, $property);
		if (PHP_VERSION_ID < 80100) {
			$reflection->setAccessible(true);
		}
		$reflection->setValue($plugin, $service);
	}

	private function readService(AntiSpamForWordPressPlugin $plugin, string $property)
	{
		$reflection = new ReflectionProperty($plugin, $property);
		if (PHP_VERSION_ID < 80100) {
			$reflection->setAccessible(true);
		}

		return $reflection->getValue($plugin);
	}

	private function restoreServices(AntiSpamForWordPressPlugin $plugin, array $original): void
	{
		foreach ($original as $property => $service) {
			$this->setService($plugin, $property, $service);
		}
	}
}
