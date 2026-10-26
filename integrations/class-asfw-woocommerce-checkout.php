<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Protect anonymous classic and Store API checkout at their submission boundary.
 *
 * Do not hook the shared customer creator: imports, administrative creation and
 * authenticated integrations must not inherit a browser challenge requirement.
 */
final class ASFW_WooCommerce_Checkout {

	const CONTEXT   = 'woocommerce:checkout';
	const FIELD     = 'asfw_checkout';
	const NAMESPACE = 'anti-spam-for-wordpress';

	/** Native dispatch is separate from gateway pre-flight validation. */
	private static $classic_dispatch = false;
	private static $classic_verified = false;

	public static function register() {
		add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'render_classic' ) );
		add_action( 'woocommerce_checkout_process', array( __CLASS__, 'begin_classic' ), 0 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_classic' ), PHP_INT_MAX, 2 );
		add_filter( 'render_block_woocommerce/checkout', array( __CLASS__, 'render_blocks' ) );
		add_filter( 'rest_dispatch_request', array( __CLASS__, 'validate_store_api' ), 10, 4 );
		add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'register_schema' ) );
		add_action( 'init', array( __CLASS__, 'register_schema' ) );
	}

	/** The emergency kill switch consistently bypasses both native checkout paths. */
	public static function requires_protection() {
		$plugin = asfw_plugin_instance();
		return ! is_user_logged_in() && $plugin instanceof AntiSpamForWordPressPlugin
			&& 'captcha' === $plugin->get_integration_woocommerce_checkout()
			&& ! $plugin->is_kill_switch_enabled();
	}

	public static function render_classic() {
		if ( ! self::requires_protection() ) {
			return;
		}
		self::enqueue_assets( false );
		echo '<div data-asfw-checkout-classic>';
		echo wp_kses( self::markup(), AntiSpamForWordPressPlugin::$html_allowed_tags );
		echo '</div>';
	}

	/** Loading next to the block keeps the adapter off ordinary pages and carts. */
	public static function render_blocks( $content ) {
		if ( self::requires_protection() ) {
			if ( ! self::supports_blocks() ) {
				return '<div class="woocommerce-error" role="alert">' . esc_html( self::compatibility_message() ) . '</div>' . $content;
			}
			self::enqueue_assets( true );
		}
		return $content;
	}

	/** Check capabilities at block rendering, after Woo has registered its assets. */
	public static function supports_blocks() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) || ! function_exists( 'wp_script_is' ) ) {
			return false;
		}
		foreach ( self::blocks_dependencies() as $handle ) {
			if ( ! wp_script_is( $handle, 'registered' ) ) {
				return false;
			}
		}
		return true;
	}

	private static function blocks_dependencies() {
		return array( 'wc-blocks-checkout', 'wc-blocks-checkout-events', 'wc-blocks-data-store', 'wp-data', 'wp-element', 'wp-plugins' );
	}

	private static function compatibility_message() {
		return __( 'Checkout verification could not load. Please contact the store.', 'anti-spam-for-wordpress' );
	}

	private static function markup() {
		return asfw_render_widget_markup( 'captcha', self::CONTEXT, self::FIELD );
	}

	private static function enqueue_assets( $blocks ) {
		asfw_enqueue_widget_assets( self::CONTEXT );
		$handle       = $blocks ? 'asfw-checkout-blocks' : 'asfw-checkout';
		$dependencies = array( 'asfw-widget-wp', 'jquery' );
		if ( $blocks ) {
			$dependencies = array_merge( $dependencies, self::blocks_dependencies() );
		}
		wp_enqueue_script( $handle, plugin_dir_url( ASFW_FILE ) . 'assets/js/checkout.js', $dependencies, asfw_asset_version( 'assets/js/checkout.js' ), true );
		wp_localize_script(
			$handle,
			'ASFW_CHECKOUT',
			array(
				'namespace'            => self::NAMESPACE,
				'field'                => self::FIELD,
				'fields'               => self::field_names(),
				'blocks'               => (bool) $blocks,
				'markup'               => $blocks ? self::markup() : '',
				'label'                => __( 'Spam protection', 'anti-spam-for-wordpress' ),
				'message'              => self::error_message(),
				'compatibilityMessage' => self::compatibility_message(),
			)
		);
	}

	/** Core fires this only for actual checkout processing, after verifying its nonce. */
	public static function begin_classic() {
		self::$classic_dispatch = true;
		self::$classic_verified = false;
	}

	/** Gateway pre-flight validation never consumes the actual checkout's proof. */
	public static function validate_classic( $data, $errors ) {
		unset( $data );
		if ( ! self::$classic_dispatch || self::$classic_verified || ! self::requires_protection()
			|| ! $errors instanceof WP_Error || '' !== $errors->get_error_code() ) {
			return;
		}
		if ( ! asfw_verify_posted_widget( self::CONTEXT, self::FIELD ) ) {
			$errors->add( 'asfw_checkout_verification', self::error_message() );
		} else {
			self::$classic_verified = true;
		}
	}

	/**
	 * REST permissions have run; the provider callback has not created any account.
	 *
	 * Woo checks its Store nonce inside that callback, after this guard. An invalid
	 * nonce can therefore consume a proof but cannot create an account or order;
	 * the browser adapter renews verification after checkout failure.
	 *
	 * This also covers requests dispatched through the Store API batch endpoint.
	 * GET/PATCH checkout data and unrelated REST callbacks retain their policies.
	 */
	public static function validate_store_api( $response, $request, $route = '', $handler = array() ) {
		unset( $route, $handler );
		// WordPress REST routes are case-insensitive; equivalent paths need the same policy.
		if ( null !== $response || ! self::requires_protection() || ! $request instanceof WP_REST_Request
			|| 'POST' !== $request->get_method()
			|| 1 !== preg_match( '#^/wc/store/(?:v[0-9]+/)?checkout/?$#i', $request->get_route() ) ) {
			return $response;
		}

		$extensions = $request->get_param( 'extensions' );
		$submitted  = is_array( $extensions ) && isset( $extensions[ self::NAMESPACE ] ) && is_array( $extensions[ self::NAMESPACE ] )
			? $extensions[ self::NAMESPACE ] : array();
		$fields     = array_intersect_key( $submitted, array_fill_keys( self::field_names(), true ) );
		$billing    = $request->get_param( 'billing_address' );
		// Email analysis must use the address Woo will create the account/order for.
		$fields['billing_email'] = is_array( $billing ) && isset( $billing['email'] ) && is_string( $billing['email'] ) ? $billing['email'] : '';
		if ( ! function_exists( 'asfw_verify_submission' ) || ! asfw_verify_submission( $fields, self::CONTEXT, self::FIELD ) ) {
			return new WP_Error( 'asfw_checkout_verification', self::error_message(), array( 'status' => 422 ) );
		}
		return $response;
	}

	public static function register_schema() {
		static $registered = false;
		if ( $registered || ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'checkout',
				'namespace'       => self::NAMESPACE,
				'schema_callback' => array( __CLASS__, 'schema' ),
				'data_callback'   => static function () {
					return array();
				},
				'schema_type'     => ARRAY_A,
			)
		);
		$registered = true;
	}

	/** Proofs are request-only extension data; no proof is reflected in responses. */
	public static function schema() {
		$schema = array();
		foreach ( self::field_names() as $name ) {
			$schema[ $name ] = array(
				'type'      => 'string',
				'context'   => array( 'edit' ),
				'readonly'  => false,
				'maxLength' => self::FIELD === $name ? 8192 : 2048,
			);
		}
		return $schema;
	}

	public static function field_names() {
		return array( self::FIELD, self::FIELD . '_started', self::FIELD . '_context', self::FIELD . '_context_sig', self::FIELD . '_website' );
	}

	private static function error_message() {
		return __( 'Please complete spam verification before placing your order.', 'anti-spam-for-wordpress' );
	}
}
