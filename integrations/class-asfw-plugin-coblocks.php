<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// https://github.com/hCaptcha/hcaptcha-wordpress-plugin/blob/master/src/php/CoBlocks/Form.php

if ( asfw_plugin_active( 'coblocks' ) ) {
	add_filter( 'render_block', array( 'ASFW_Plugin_Coblocks', 'render_block' ), 10, 3 );
	add_filter( 'render_block_data', array( 'ASFW_Plugin_Coblocks', 'render_block_data' ), 10, 3 );

	class ASFW_Plugin_Coblocks {

		private const RECAPTCHA_DUMMY_TOKEN = 'asfw_dummy_token';

		private static $original_token;
		private static $intercepting = false;

		private static function is_enabled() {
			$plugin = asfw_plugin_instance();
			return $plugin instanceof AntiSpamForWordPressPlugin
				&& ! $plugin->is_kill_switch_enabled()
				&& 'captcha' === $plugin->get_integration_coblocks();
		}

		public static function cleanup(): void {
			remove_filter( 'pre_http_request', array( self::class, 'verify' ) );
			remove_filter( 'pre_option_coblocks_google_recaptcha_site_key', '__return_true' );
			remove_filter( 'pre_option_coblocks_google_recaptcha_secret_key', '__return_true' );
			if ( self::$intercepting ) {
				if ( null === self::$original_token ) {
					unset( $_POST['g-recaptcha-token'] );
				} else {
					$_POST['g-recaptcha-token'] = self::$original_token;
				}
			}
			self::$intercepting   = false;
			self::$original_token = null;
		}

		public static function render_block( $block_content, array $block, WP_Block $instance ): string {
			$block_content = (string) $block_content;
			if ( 'coblocks/form' !== ( $block['blockName'] ?? '' ) || ! self::is_enabled() ) {
				return $block_content;
			}

			$plugin = AntiSpamForWordPressPlugin::$instance;
			$mode   = $plugin->get_integration_coblocks();
			if ( 'captcha' === $mode ) {
				return str_replace(
					'<button type="submit"',
					asfw_render_widget_markup( $mode, 'coblocks' ) . '<button type="submit"',
					$block_content
				);
			}

			return $block_content;
		}

		public static function render_block_data( $parsed_block, array $source_block, $parent_block = null ): array {
			unset( $source_block, $parent_block );

			if ( ! self::is_enabled() ) {
				return $parsed_block;
			}

			$parsed_block = (array) $parsed_block;
			$block_name   = $parsed_block['blockName'] ?? '';

			if ( 'coblocks/form' !== $block_name ) {
				return $parsed_block;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The block integration only inspects the action field to detect CoBlocks submissions.
			$form_submission = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';

			if ( 'coblocks-form-submit' !== $form_submission ) {
				return $parsed_block;
			}

			add_action( 'coblocks_before_form_submit', array( 'ASFW_Plugin_Coblocks', 'before_form_submit' ), 10, 2 );

			return $parsed_block;
		}

		public static function before_form_submit( array $post, array $atts ): void {
			unset( $post, $atts );
			if ( ! self::is_enabled() || self::$intercepting ) {
				return;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Store only for byte-for-byte restoration to the provider, which validates it; never output or trust this token.
			self::$original_token = $_POST['g-recaptcha-token'] ?? null;
			self::$intercepting   = true;
			add_action( 'shutdown', array( self::class, 'cleanup' ) );

			add_filter( 'pre_option_coblocks_google_recaptcha_site_key', '__return_true' );
			add_filter( 'pre_option_coblocks_google_recaptcha_secret_key', '__return_true' );

			$_POST['g-recaptcha-token'] = self::RECAPTCHA_DUMMY_TOKEN;

			add_filter( 'pre_http_request', array( 'ASFW_Plugin_Coblocks', 'verify' ), 10, 3 );
		}

		public static function verify( $response, array $parsed_args, string $url ) {
			if (
				CoBlocks_Form::GCAPTCHA_VERIFY_URL !== $url ||
				! is_array( $parsed_args['body'] ?? null ) ||
				self::RECAPTCHA_DUMMY_TOKEN !== ( $parsed_args['body']['response'] ?? null )
			) {
				return $response;
			}

			self::cleanup();
			if ( ! self::is_enabled() ) {
				return $response;
			}
			$verified = asfw_verify_posted_widget( 'coblocks' );
			return array(
				'body'     => wp_json_encode( array( 'success' => true === $verified ) ),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
			);
		}
	}
}
