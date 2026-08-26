<?php
declare(strict_types=1);

final class FormAwareAssetsTest extends AsfwPluginTestCase
{
	public function test_ordinary_frontend_enqueue_pass_requests_no_widget_assets(): void
	{
		$contexts = array();
		$observer = static function ( string $context ) use ( &$contexts ): void {
			$contexts[] = $context;
		};
		add_action( 'asfw_widget_assets_enqueued', $observer, 10, 1 );

		try {
			do_action( 'wp_enqueue_scripts' );
			$this->assertSame( array(), $contexts );
			$this->assertSame( array(), $GLOBALS['asfw_test_enqueued_scripts'] );
			$this->assertSame( array(), $GLOBALS['asfw_test_enqueued_styles'] );
		} finally {
			remove_action( 'asfw_widget_assets_enqueued', $observer, 10 );
		}
	}

	public function test_rendered_custom_shortcode_requests_complete_widget_assets(): void
	{
		$contexts = array();
		$observer = static function ( string $context ) use ( &$contexts ): void {
			$contexts[] = $context;
		};
		add_action( 'asfw_widget_assets_enqueued', $observer, 10, 1 );

		try {
			$shortcode = $GLOBALS['asfw_test_shortcodes']['anti_spam_widget'];
			$markup    = $shortcode(
				array(
					'mode'    => 'captcha',
					'context' => 'custom:contact',
				)
			);

			$this->assertStringContainsString( '<asfw-widget', $markup );
			$this->assertSame( array( 'custom:contact' ), $contexts );
			$this->assertSame( array( 'asfw-widget', 'asfw-widget-wp' ), array_keys( $GLOBALS['asfw_test_enqueued_scripts'] ) );
			$this->assertSame( array( 'asfw-widget' ), $GLOBALS['asfw_test_enqueued_scripts']['asfw-widget-wp']['deps'] );
			$this->assertArrayHasKey( 'ASFW_RUNTIME', $GLOBALS['asfw_test_localized_scripts']['asfw-widget-wp'] );
			$this->assertSame( array( 'asfw-widget-styles' ), array_keys( $GLOBALS['asfw_test_enqueued_styles'] ) );
		} finally {
			remove_action( 'asfw_widget_assets_enqueued', $observer, 10 );
		}
	}

	public function test_protected_integration_widget_requests_assets_for_its_context(): void
	{
		$contexts = array();
		$observer = static function ( string $context ) use ( &$contexts ): void {
			$contexts[] = $context;
		};
		add_action( 'asfw_widget_assets_enqueued', $observer, 10, 1 );

		try {
			$markup = asfw_render_widget_markup( 'captcha', 'contact-form-7', 'asfw' );

			$this->assertStringContainsString( '<asfw-widget', $markup );
			$this->assertSame( array( 'contact-form-7' ), $contexts );
			$this->assertSame( array( 'asfw-widget', 'asfw-widget-wp' ), array_keys( $GLOBALS['asfw_test_enqueued_scripts'] ) );
			$this->assertSame( array( 'asfw-widget-styles' ), array_keys( $GLOBALS['asfw_test_enqueued_styles'] ) );
		} finally {
			remove_action( 'asfw_widget_assets_enqueued', $observer, 10 );
		}
	}

	public function test_kill_switch_emits_neither_markup_nor_asset_request(): void
	{
		update_option( AntiSpamForWordPressPlugin::$option_kill_switch, 1 );
		$contexts = array();
		$observer = static function ( string $context ) use ( &$contexts ): void {
			$contexts[] = $context;
		};
		add_action( 'asfw_widget_assets_enqueued', $observer, 10, 1 );

		try {
			$this->assertSame( '', asfw_render_widget_markup( 'captcha', 'contact-form-7', 'asfw' ) );
			$this->assertSame( array(), $contexts );
			$this->assertSame( array(), $GLOBALS['asfw_test_enqueued_scripts'] );
			$this->assertSame( array(), $GLOBALS['asfw_test_enqueued_styles'] );
		} finally {
			remove_action( 'asfw_widget_assets_enqueued', $observer, 10 );
		}
	}
}
