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

	public function test_custom_shortcode_mode_uses_configured_captcha_mode(): void
	{
		update_option( AntiSpamForWordPressPlugin::$option_integration_custom, 'captcha' );
		$shortcode = $GLOBALS['asfw_test_shortcodes']['anti_spam_widget'];

		$this->assertStringContainsString( '<asfw-widget', $shortcode( array() ) );
		$this->assertSame( array( 'asfw-widget', 'asfw-widget-wp' ), array_keys( $GLOBALS['asfw_test_enqueued_scripts'] ) );
		$this->assertSame( array( 'asfw-widget-styles' ), array_keys( $GLOBALS['asfw_test_enqueued_styles'] ) );
	}

	public function test_custom_shortcode_mode_uses_configured_shortcode_mode(): void
	{
		update_option( AntiSpamForWordPressPlugin::$option_integration_custom, 'shortcode' );
		$shortcode = $GLOBALS['asfw_test_shortcodes']['anti_spam_widget'];

		$this->assertStringContainsString( '<asfw-widget', $shortcode( array() ) );
		$this->assertSame( array( 'asfw-widget', 'asfw-widget-wp' ), array_keys( $GLOBALS['asfw_test_enqueued_scripts'] ) );
		$this->assertSame( array( 'asfw-widget-styles' ), array_keys( $GLOBALS['asfw_test_enqueued_styles'] ) );
	}

	public function test_disabled_custom_mode_renders_and_enqueues_nothing_without_explicit_mode(): void
	{
		update_option( AntiSpamForWordPressPlugin::$option_integration_custom, '' );
		$shortcode = $GLOBALS['asfw_test_shortcodes']['anti_spam_widget'];

		$this->assertSame( '', $shortcode( array() ) );
		$this->assertSame( array(), $GLOBALS['asfw_test_enqueued_scripts'] );
		$this->assertSame( array(), $GLOBALS['asfw_test_enqueued_styles'] );
	}

	public function test_duplicate_widgets_share_one_asset_queue(): void
	{
		$shortcode = $GLOBALS['asfw_test_shortcodes']['anti_spam_widget'];
		$attributes = array( 'mode' => 'captcha', 'context' => 'custom:contact' );

		$this->assertStringContainsString( '<asfw-widget', $shortcode( $attributes ) );
		$this->assertStringContainsString( '<asfw-widget', $shortcode( $attributes ) );
		$this->assertSame( array( 'asfw-widget', 'asfw-widget-wp' ), array_keys( $GLOBALS['asfw_test_enqueued_scripts'] ) );
		$this->assertSame( array( 'asfw-widget-styles' ), array_keys( $GLOBALS['asfw_test_enqueued_styles'] ) );
		$this->assertCount( 1, $GLOBALS['asfw_test_localized_scripts']['asfw-widget-wp'] );
	}

	public function test_widget_rendered_after_head_prints_complete_assets_before_footer(): void
	{
		// WordPress 6.4 prints the current style queue in the head and does not
		// automatically revisit it. No ASFW style exists at this point.
		wp_print_styles();
		$this->assertSame( array(), $GLOBALS['asfw_test_printed_styles'] );

		$markup = asfw_render_widget_markup( 'captcha', 'contact-form-7', 'asfw' );
		$this->assertStringContainsString( '<asfw-widget', $markup );
		$this->assertSame( array( 'asfw-widget', 'asfw-widget-wp' ), array_keys( $GLOBALS['asfw_test_enqueued_scripts'] ) );
		$this->assertArrayHasKey( 'ASFW_RUNTIME', $GLOBALS['asfw_test_localized_scripts']['asfw-widget-wp'] );
		$this->assertSame( array(), $GLOBALS['asfw_test_printed_styles'] );

		do_action( 'wp_footer' );
		$this->assertSame( array( 'asfw-widget-styles' ), $GLOBALS['asfw_test_printed_styles'] );

		// Repeated footer hooks or duplicate widgets cannot print the handle twice.
		do_action( 'wp_footer' );
		$this->assertSame( array( 'asfw-widget-styles' ), $GLOBALS['asfw_test_printed_styles'] );
	}

	public function test_login_footer_prints_a_late_login_widget_style(): void
	{
		wp_print_styles();
		asfw_render_widget_markup( 'captcha', 'wordpress:login', 'asfw' );

		do_action( 'login_footer' );
		$this->assertSame( array( 'asfw-widget-styles' ), $GLOBALS['asfw_test_printed_styles'] );
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
