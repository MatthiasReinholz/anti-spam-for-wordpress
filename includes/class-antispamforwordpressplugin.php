<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AntiSpamForWordPressPlugin {

	public static $instance;

	public static $language = '';

	public static $widget_script_src = '';

	public static $wp_script_src = '';

	public static $admin_script_src = '';

	public static $admin_css_src = '';

	public static $custom_script_src = '';

	public static $widget_style_src = '';

	public static $version = '0.0.0';

	public static $widget_version = '0.0.0';

	public static $option_secret = 'asfw_secret';

	public static $option_complexity = 'asfw_complexity';

	public static $option_expires = 'asfw_expires';

	public static $option_auto = 'asfw_auto';

	public static $option_floating = 'asfw_floating';

	public static $option_delay = 'asfw_delay';

	public static $option_hidefooter = 'asfw_hidefooter';

	public static $option_hidelogo = 'asfw_hidelogo';

	public static $option_footer_text = 'asfw_footer_text';

	public static $option_privacy_page = 'asfw_privacy_page';

	public static $option_privacy_url = 'asfw_privacy_url';

	public static $option_privacy_new_tab = 'asfw_privacy_new_tab';

	public static $option_lazy = 'asfw_lazy';

	public static $option_rate_limit_max_challenges = 'asfw_rate_limit_max_challenges';

	public static $option_rate_limit_max_failures = 'asfw_rate_limit_max_failures';

	public static $option_rate_limit_window = 'asfw_rate_limit_window';

	public static $option_honeypot = 'asfw_honeypot';

	public static $option_min_submit_time = 'asfw_min_submit_time';

	public static $option_visitor_binding = 'asfw_visitor_binding';

	public static $option_trusted_proxies = 'asfw_trusted_proxies';

	public static $option_integration_coblocks = 'asfw_integration_coblocks';

	public static $option_integration_contact_form_7 = 'asfw_integration_contact_form_7';

	public static $option_integration_custom = 'asfw_integration_custom';

	public static $option_integration_elementor = 'asfw_integration_elementor';

	public static $option_integration_enfold_theme = 'asfw_integration_enfold_theme';

	public static $option_integration_formidable = 'asfw_integration_formidable';

	public static $option_integration_forminator = 'asfw_integration_forminator';

	public static $option_integration_gravityforms = 'asfw_integration_gravityforms';

	public static $option_integration_woocommerce_login = 'asfw_integration_woocommerce_login';

	public static $option_integration_woocommerce_register = 'asfw_integration_woocommerce_register';

	public static $option_integration_woocommerce_reset_password = 'asfw_integration_woocommerce_reset_password';

	public static $option_integration_html_forms = 'asfw_integration_html_forms';

	public static $option_integration_wordpress_login = 'asfw_integration_wordpress_login';

	public static $option_integration_wordpress_register = 'asfw_integration_wordpress_register';

	public static $option_integration_wordpress_reset_password = 'asfw_integration_wordpress_reset_password';

	public static $option_integration_wordpress_comments = 'asfw_integration_wordpress_comments';

	public static $option_integration_wpdiscuz = 'asfw_integration_wpdiscuz';

	public static $option_integration_wpforms = 'asfw_integration_wpforms';

	public static $html_allowed_tags = array(
		'asfw-widget' => array(
			'challengeurl'              => array(),
			'strings'                   => array(),
			'auto'                      => array(),
			'floating'                  => array(),
			'delay'                     => array(),
			'hidelogo'                  => array(),
			'hidefooter'                => array(),
			'name'                      => array(),
			'data-asfw-challengeurl'    => array(),
			'data-asfw-context'         => array(),
			'data-asfw-field'           => array(),
			'data-asfw-lazy'            => array(),
			'data-asfw-min-submit-time' => array(),
			'data-asfw-privacy-new-tab' => array(),
			'data-asfw-privacy-url'     => array(),
			'data-asfw-provider'        => array(),
		),
		'div'         => array(
			'aria-hidden' => array(),
			'class'       => array(),
			'style'       => array(),
		),
		'input'       => array(
			'aria-hidden'  => array(),
			'autocomplete' => array(),
			'class'        => array(),
			'id'           => array(),
			'name'         => array(),
			'tabindex'     => array(),
			'type'         => array(),
			'value'        => array(),
			'style'        => array(),
		),
		'noscript'    => array(),
	);

	public static $hostname = null;

	public function init() {
		self::$instance = $this;
		self::$language = get_locale();

		if ( defined( 'ASFW_VERSION' ) ) {
			self::$version = ASFW_VERSION;
		}

		if ( defined( 'ASFW_WIDGET_VERSION' ) ) {
			self::$widget_version = ASFW_WIDGET_VERSION;
		}

		$url            = wp_parse_url( get_site_url() );
		self::$hostname = $url['host'] . ( isset( $url['port'] ) ? ':' . $url['port'] : '' );
	}

	public function get_complexity() {
		return trim( get_option( self::$option_complexity ) );
	}

	public function get_expires() {
		return get_option( self::$option_expires );
	}

	public function get_secret() {
		return trim( get_option( self::$option_secret ) );
	}

	public function get_hidelogo() {
		return get_option( self::$option_hidelogo );
	}

	public function get_footer_text() {
		$default_text = __( 'Protected by Anti Spam for WordPress', 'anti-spam-for-wordpress' );
		$footer_text  = trim( wp_strip_all_tags( (string) get_option( self::$option_footer_text, '' ) ) );

		if ( '' === $footer_text ) {
			return $default_text;
		}

		return $footer_text;
	}

	public function get_hidefooter() {
		return get_option( self::$option_hidefooter );
	}

	public function get_privacy_page_id() {
		$value = trim( (string) get_option( self::$option_privacy_page, '' ) );

		return ctype_digit( $value ) ? absint( $value ) : 0;
	}

	public function get_privacy_target() {
		return trim( (string) get_option( self::$option_privacy_page, '' ) );
	}

	public function get_privacy_custom_url() {
		return esc_url_raw( trim( (string) get_option( self::$option_privacy_url, '' ) ) );
	}

	public function get_privacy_new_tab() {
		return (bool) get_option( self::$option_privacy_new_tab );
	}

	public function get_privacy_url() {
		$page_id = $this->get_privacy_page_id();
		if ( $page_id > 0 ) {
			$page = get_post( $page_id );
			if ( $page instanceof WP_Post && 'page' === $page->post_type && 'publish' === $page->post_status ) {
				$permalink = get_permalink( $page_id );
				if ( is_string( $permalink ) && '' !== $permalink ) {
					return $permalink;
				}
			}
		}

		if ( 'custom' === $this->get_privacy_target() ) {
			return $this->get_privacy_custom_url();
		}

		return '';
	}

	public function get_auto() {
		return trim( get_option( self::$option_auto ) );
	}

	public function get_floating() {
		return trim( get_option( self::$option_floating ) );
	}

	public function get_delay() {
		return trim( get_option( self::$option_delay ) );
	}

	public function get_lazy() {
		return (bool) get_option( self::$option_lazy );
	}

	public function get_rate_limit_max_challenges() {
		return intval( get_option( self::$option_rate_limit_max_challenges ), 10 );
	}

	public function get_rate_limit_max_failures() {
		return intval( get_option( self::$option_rate_limit_max_failures ), 10 );
	}

	public function get_rate_limit_window() {
		return intval( get_option( self::$option_rate_limit_window ), 10 );
	}

	public function get_honeypot() {
		return (bool) get_option( self::$option_honeypot );
	}

	public function get_min_submit_time() {
		return intval( get_option( self::$option_min_submit_time ), 10 );
	}

	public function get_visitor_binding() {
		$binding = trim( (string) get_option( self::$option_visitor_binding, 'ip' ) );

		return in_array( $binding, array( 'ip', 'ip_ua' ), true ) ? $binding : 'ip';
	}

	public function get_trusted_proxies() {
		return trim( (string) get_option( self::$option_trusted_proxies, '' ) );
	}

	public function get_integration_coblocks() {
		return trim( get_option( self::$option_integration_coblocks ) );
	}

	public function get_integration_contact_form_7() {
		return trim( get_option( self::$option_integration_contact_form_7 ) );
	}

	public function get_integration_custom() {
		return trim( get_option( self::$option_integration_custom ) );
	}

	public function get_integration_elementor() {
		return trim( get_option( self::$option_integration_elementor ) );
	}

	public function get_integration_enfold_theme() {
		return trim( get_option( self::$option_integration_enfold_theme ) );
	}

	public function get_integration_formidable() {
		return trim( get_option( self::$option_integration_formidable ) );
	}

	public function get_integration_forminator() {
		return trim( get_option( self::$option_integration_forminator ) );
	}

	public function get_integration_gravityforms() {
		return trim( get_option( self::$option_integration_gravityforms ) );
	}

	public function get_integration_woocommerce_register() {
		return trim( get_option( self::$option_integration_woocommerce_register ) );
	}

	public function get_integration_woocommerce_reset_password() {
		return trim( get_option( self::$option_integration_woocommerce_reset_password ) );
	}

	public function get_integration_woocommerce_login() {
		return trim( get_option( self::$option_integration_woocommerce_login ) );
	}

	public function get_integration_html_forms() {
		return trim( get_option( self::$option_integration_html_forms ) );
	}

	public function get_integration_wordpress_register() {
		return trim( get_option( self::$option_integration_wordpress_register ) );
	}

	public function get_integration_wordpress_reset_password() {
		return trim( get_option( self::$option_integration_wordpress_reset_password ) );
	}

	public function get_integration_wordpress_login() {
		return trim( get_option( self::$option_integration_wordpress_login ) );
	}

	public function get_integration_wordpress_comments() {
		return trim( get_option( self::$option_integration_wordpress_comments ) );
	}

	public function get_integration_wpdiscuz() {
		return trim( get_option( self::$option_integration_wpdiscuz ) );
	}

	public function get_integration_wpforms() {
		return trim( get_option( self::$option_integration_wpforms ) );
	}

	public function get_widget_provider() {
		return apply_filters( 'asfw_widget_provider', 'asfw' );
	}

	public function get_widget_tag_name() {
		return apply_filters( 'asfw_widget_tag_name', 'asfw-widget' );
	}

	/**
	 * Normalize a context string to a safe, consistent format.
	 *
	 * Lowercases, strips non-alphanumeric characters (except colons, dots,
	 * and dashes), and limits to 128 characters. Returns 'generic' for empty input.
	 *
	 * @param string $context Raw context identifier.
	 * @return string Normalized context string.
	 */
	public function normalize_context( $context ) {
		$context = strtolower( (string) $context );
		$context = preg_replace( '/[^a-z0-9:._-]/', '-', $context );
		$context = substr( (string) $context, 0, 128 );
		$context = trim( (string) $context, '-' );

		if ( '' === $context ) {
			$context = 'generic';
		}

		return $context;
	}

	public function get_widget_context( $mode, $name = null, $context = null ) {
		if ( null === $context || '' === $context ) {
			$mode_context = $mode;
			if ( null === $mode_context || '' === $mode_context ) {
				$mode_context = 'custom';
			}

			$context = 'form:' . $mode_context;
			if ( ! empty( $name ) ) {
				$context .= ':' . sanitize_key( $name );
			}
		}

		return apply_filters(
			'asfw_widget_context',
			$this->normalize_context( $context ),
			$mode,
			$name
		);
	}

	public function get_challenge_url( $context = null ) {
		$challenge_url = get_rest_url( null, '/anti-spam-for-wordpress/v1/challenge' );
		if ( ! empty( $context ) ) {
			$challenge_url = add_query_arg(
				'context',
				$this->normalize_context( $context ),
				$challenge_url
			);
		}

		return apply_filters( 'asfw_challenge_url', $challenge_url, $context );
	}

	public function get_translations( $language = null ) {
		$original_language = null;

		if ( null !== $language ) {
			$original_language = get_locale();
			switch_to_locale( $language );
		}

		$translations = array(
			'error'     => __( 'Verification failed. Try again later.', 'anti-spam-for-wordpress' ),
			'footer'    => $this->get_footer_text(),
			'label'     => __( 'I\'m not a robot', 'anti-spam-for-wordpress' ),
			'privacy'   => __( 'Privacy', 'anti-spam-for-wordpress' ),
			'required'  => __( 'Please verify before submitting.', 'anti-spam-for-wordpress' ),
			'verified'  => __( 'Verified', 'anti-spam-for-wordpress' ),
			'verifying' => __( 'Verifying...', 'anti-spam-for-wordpress' ),
			'waitAlert' => __( 'Verifying... please wait.', 'anti-spam-for-wordpress' ),
		);

		$translations = apply_filters( 'asfw_translations', $translations, $language );

		if ( null !== $original_language ) {
			switch_to_locale( $original_language );
		}

		return $translations;
	}

	public function get_integrations() {
		$integrations = array(
			$this->get_integration_contact_form_7(),
			$this->get_integration_custom(),
			$this->get_integration_elementor(),
			$this->get_integration_enfold_theme(),
			$this->get_integration_forminator(),
			$this->get_integration_gravityforms(),
			$this->get_integration_html_forms(),
			$this->get_integration_woocommerce_register(),
			$this->get_integration_woocommerce_login(),
			$this->get_integration_woocommerce_reset_password(),
			$this->get_integration_wordpress_register(),
			$this->get_integration_wordpress_login(),
			$this->get_integration_wordpress_reset_password(),
			$this->get_integration_wordpress_comments(),
			$this->get_integration_wpforms(),
		);

		return apply_filters( 'asfw_integrations', $integrations );
	}

	public function has_active_integrations() {
		$integrations = $this->get_integrations();

		return in_array( 'captcha', $integrations, true ) || in_array( 'shortcode', $integrations, true );
	}

	public function random_secret() {
		return bin2hex( random_bytes( 12 ) );
	}

	public function get_challenge_transient_key( $challenge_id ) {
		return 'asfw_challenge_' . sanitize_key( $challenge_id );
	}

	public function get_challenge_lock_key( $challenge_id ) {
		return 'asfw_challenge_lock_' . sanitize_key( $challenge_id );
	}

	public function get_started_field_name( $field_name = 'asfw' ) {
		return sanitize_key( $field_name ) . '_started';
	}

	public function get_honeypot_field_name( $field_name = 'asfw' ) {
		return sanitize_key( $field_name ) . '_website';
	}

	public function get_context_field_name( $field_name = 'asfw' ) {
		return sanitize_key( $field_name ) . '_context';
	}

	public function get_context_signature_field_name( $field_name = 'asfw' ) {
		return sanitize_key( $field_name ) . '_context_sig';
	}

	public function normalize_ip( $ip_address ) {
		$ip_address = trim( (string) $ip_address, " \t\n\r\0\x0B\"'[]" );
		if ( '' === $ip_address ) {
			return '';
		}

		if ( stripos( $ip_address, 'for=' ) === 0 ) {
			$ip_address = trim( substr( $ip_address, 4 ), " \t\n\r\0\x0B\"'[]" );
		}

		if ( preg_match( '/^\[([^\]]+)\](?::\d+)?$/', $ip_address, $matches ) === 1 ) {
			$ip_address = $matches[1];
		} elseif ( preg_match( '/^[0-9.]+:\d+$/', $ip_address ) === 1 ) {
			$ip_address = preg_replace( '/:\d+$/', '', $ip_address );
		}

		return filter_var( $ip_address, FILTER_VALIDATE_IP ) ? $ip_address : '';
	}

	public function get_trusted_proxy_list() {
		$entries = preg_split( '/[\s,]+/', $this->get_trusted_proxies(), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $entries ) ) {
			$entries = array();
		}

		$entries = array_values(
			array_filter(
				array_map(
					function ( $entry ) {
						$entry = trim( (string) $entry );
						if ( '' === $entry ) {
							return '';
						}

						if ( strpos( $entry, '/' ) === false ) {
							return $this->normalize_ip( $entry );
						}

						list($subnet, $prefix) = array_pad( explode( '/', $entry, 2 ), 2, '' );
						$subnet                = $this->normalize_ip( $subnet );
						if ( '' === $subnet || ! preg_match( '/^\d+$/', $prefix ) ) {
							return '';
						}

						return $subnet . '/' . $prefix;
					},
					$entries
				)
			)
		);

		return apply_filters( 'asfw_trusted_proxies', array_unique( $entries ) );
	}

	public function ip_matches_range( $ip_address, $range ) {
		if ( '' === $range ) {
			return false;
		}

		if ( strpos( $range, '/' ) === false ) {
			return hash_equals( $range, $ip_address );
		}

		list($subnet, $prefix) = array_pad( explode( '/', $range, 2 ), 2, '' );
		if ( ! preg_match( '/^\d+$/', $prefix ) ) {
			return false;
		}

		$ip_binary     = inet_pton( $ip_address );
		$subnet_binary = inet_pton( $subnet );
		if ( false === $ip_binary || false === $subnet_binary || strlen( $ip_binary ) !== strlen( $subnet_binary ) ) {
			return false;
		}

		$prefix_length = intval( $prefix, 10 );
		$max_bits      = strlen( $ip_binary ) * 8;
		if ( $prefix_length < 0 || $prefix_length > $max_bits ) {
			return false;
		}

		$full_bytes = intdiv( $prefix_length, 8 );
		if ( $full_bytes > 0 && substr( $ip_binary, 0, $full_bytes ) !== substr( $subnet_binary, 0, $full_bytes ) ) {
			return false;
		}

		$remaining_bits = $prefix_length % 8;
		if ( 0 === $remaining_bits ) {
			return true;
		}

		$mask = chr( ( 0xFF << ( 8 - $remaining_bits ) ) & 0xFF );

		return ( ord( $ip_binary[ $full_bytes ] ) & ord( $mask ) ) === ( ord( $subnet_binary[ $full_bytes ] ) & ord( $mask ) );
	}

	public function is_trusted_proxy_ip( $ip_address ) {
		foreach ( $this->get_trusted_proxy_list() as $range ) {
			if ( $this->ip_matches_range( $ip_address, $range ) ) {
				return true;
			}
		}

		return false;
	}

	public function extract_forwarded_for_ip( $header_value ) {
		$candidates = array_map( 'trim', explode( ',', (string) $header_value ) );
		foreach ( $candidates as $candidate ) {
			$candidate = $this->normalize_ip( $candidate );
			if ( '' === $candidate ) {
				continue;
			}
			if ( ! $this->is_trusted_proxy_ip( $candidate ) ) {
				return $candidate;
			}
		}

		foreach ( $candidates as $candidate ) {
			$candidate = $this->normalize_ip( $candidate );
			if ( '' !== $candidate ) {
				return $candidate;
			}
		}

		return '';
	}

	public function extract_forwarded_header_ip( $header_value ) {
		$segments = explode( ',', (string) $header_value );
		foreach ( $segments as $segment ) {
			$pairs = explode( ';', $segment );
			foreach ( $pairs as $pair ) {
				$pair = trim( $pair );
				if ( stripos( $pair, 'for=' ) !== 0 ) {
					continue;
				}

					$candidate = $this->normalize_ip( substr( $pair, 4 ) );
				if ( '' === $candidate ) {
					continue;
				}
				if ( ! $this->is_trusted_proxy_ip( $candidate ) ) {
					return $candidate;
				}
			}
		}

		foreach ( $segments as $segment ) {
			$pairs = explode( ';', $segment );
			foreach ( $pairs as $pair ) {
				$pair = trim( $pair );
				if ( stripos( $pair, 'for=' ) !== 0 ) {
					continue;
				}

					$candidate = $this->normalize_ip( substr( $pair, 4 ) );
				if ( '' !== $candidate ) {
					return $candidate;
				}
			}
		}

		return '';
	}

	public function get_client_ip_address() {
		$remote_address = isset( $_SERVER['REMOTE_ADDR'] ) ? $this->normalize_ip( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( '' === $remote_address || ! $this->is_trusted_proxy_ip( $remote_address ) ) {
			return '' !== $remote_address ? $remote_address : 'unknown';
		}

		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_REAL_IP',
			'HTTP_FORWARDED',
			'HTTP_X_FORWARDED_FOR',
		);
		foreach ( $headers as $header_name ) {
			if ( empty( $_SERVER[ $header_name ] ) ) {
				continue;
			}

			$header_value = wp_unslash( $_SERVER[ $header_name ] );
			if ( 'HTTP_FORWARDED' === $header_name ) {
				$candidate = $this->extract_forwarded_header_ip( $header_value );
			} elseif ( 'HTTP_X_FORWARDED_FOR' === $header_name ) {
				$candidate = $this->extract_forwarded_for_ip( $header_value );
			} else {
				$candidate = $this->normalize_ip( $header_value );
			}

			if ( '' !== $candidate ) {
				return apply_filters( 'asfw_client_ip', $candidate, $remote_address, $header_name );
			}
		}

		return apply_filters( 'asfw_client_ip', $remote_address, $remote_address, 'REMOTE_ADDR' );
	}

	public function get_client_binding_components() {
		$components = array(
			'ip:' . $this->get_client_ip_address(),
		);

		if ( $this->get_visitor_binding() === 'ip_ua' ) {
			$user_agent   = isset( $_SERVER['HTTP_USER_AGENT'] )
				? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 512 )
				: '';
			$components[] = 'ua:' . $user_agent;
		}

		return apply_filters( 'asfw_client_binding_components', $components, $this->get_visitor_binding() );
	}

	public function get_client_fingerprint() {
		$secret = $this->get_secret();
		if ( '' === $secret ) {
			$secret = wp_salt( 'nonce' );
		}

		return hash_hmac( 'sha256', implode( '|', $this->get_client_binding_components() ), $secret );
	}

	public function get_rate_limit_key( $type, $context ) {
		$context_key = ( 'challenge' === $type ) ? 'global' : (string) $context;

		return 'asfw_rl_' . sanitize_key( $type ) . '_' . md5( $this->get_client_fingerprint() . '|' . $context_key );
	}

	public function sign_widget_context( $context, $field_name = 'asfw' ) {
		$secret = $this->get_secret();
		if ( '' === $secret ) {
			$secret = wp_salt( 'nonce' );
		}

		return hash_hmac(
			'sha256',
			$this->normalize_context( $context ) . '|' . sanitize_key( $field_name ),
			$secret
		);
	}

	public function resolve_expected_context( $expected_context, $field_name = 'asfw' ) {
		if ( null !== $expected_context && '' !== $expected_context ) {
			return $this->normalize_context( $expected_context );
		}

		$posted_context   = asfw_get_posted_value( $this->get_context_field_name( $field_name ) );
		$posted_signature = asfw_get_posted_value( $this->get_context_signature_field_name( $field_name ) );
		if ( '' === $posted_context || '' === $posted_signature ) {
			return new WP_Error( 'asfw_missing_context', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		$normalized_context = $this->normalize_context( $posted_context );
		$expected_signature = $this->sign_widget_context( $normalized_context, $field_name );
		if ( ! hash_equals( $expected_signature, $posted_signature ) ) {
			return new WP_Error( 'asfw_invalid_context_signature', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		return $normalized_context;
	}

	public function get_rate_limit_limit( $type ) {
		if ( 'challenge' === $type ) {
			return max( 0, $this->get_rate_limit_max_challenges() );
		}

		return max( 0, $this->get_rate_limit_max_failures() );
	}

	public function get_rate_limit_window_safe() {
		return max( 60, $this->get_rate_limit_window() );
	}

	public function get_rate_limit_state( $type, $context ) {
		$limit = $this->get_rate_limit_limit( $type );
		if ( $limit <= 0 ) {
			return array(
				'count'  => 0,
				'limit'  => 0,
				'window' => $this->get_rate_limit_window_safe(),
			);
		}

		$bucket = get_transient( $this->get_rate_limit_key( $type, $context ) );
		if ( ! is_array( $bucket ) ) {
			$bucket = array(
				'count' => 0,
			);
		}

		return array(
			'count'  => isset( $bucket['count'] ) ? intval( $bucket['count'], 10 ) : 0,
			'limit'  => $limit,
			'window' => $this->get_rate_limit_window_safe(),
		);
	}

	public function is_rate_limited( $type, $context ) {
		$state = $this->get_rate_limit_state( $type, $context );
		if ( $state['limit'] <= 0 ) {
			return false;
		}

		if ( $state['count'] >= $state['limit'] ) {
			do_action( 'asfw_rate_limited', $type, $context, $state );

			return new WP_Error(
				'asfw_rate_limited',
				__( 'Too many verification attempts. Please wait and try again.', 'anti-spam-for-wordpress' ),
				array( 'status' => 429 )
			);
		}

		return false;
	}

	public function increment_rate_limit( $type, $context ) {
		$state = $this->get_rate_limit_state( $type, $context );
		if ( $state['limit'] <= 0 ) {
			return $state;
		}

		++$state['count'];
		set_transient(
			$this->get_rate_limit_key( $type, $context ),
			array( 'count' => $state['count'] ),
			$state['window']
		);

		return $state;
	}

	public function clear_rate_limit( $type, $context ) {
		delete_transient( $this->get_rate_limit_key( $type, $context ) );
	}

	public function acquire_challenge_lock( $challenge_id, $ttl = 30 ) {
		$lock_key   = $this->get_challenge_lock_key( $challenge_id );
		$expires_at = time() + max( 1, intval( $ttl, 10 ) );
		if ( add_option( $lock_key, (string) $expires_at, '', false ) ) {
			return true;
		}

		$existing_expires = intval( (string) get_option( $lock_key, '0' ), 10 );
		if ( $existing_expires >= time() ) {
			return false;
		}

		delete_option( $lock_key );

		return add_option( $lock_key, (string) $expires_at, '', false );
	}

	public function release_challenge_lock( $challenge_id ) {
		delete_option( $this->get_challenge_lock_key( $challenge_id ) );
	}

	/**
	 * Decode and validate a base64-encoded proof-of-work payload.
	 *
	 * Performs base64 decoding, JSON parsing, field presence checks,
	 * type validation, and number range verification.
	 *
	 * @param string $payload Base64-encoded JSON payload from the widget.
	 * @return array|WP_Error Decoded payload array on success, WP_Error on failure.
	 */
	public function decode_payload( $payload ) {
		if ( '' === trim( $payload ) ) {
			return new WP_Error( 'asfw_empty_payload', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Proof-of-work payloads are base64-encoded JSON by design.
		$decoded = base64_decode( $payload, true );
		if ( false === $decoded ) {
			return new WP_Error( 'asfw_invalid_base64', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		$data = json_decode( $decoded, true );
		if ( ! is_array( $data ) || json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'asfw_invalid_json', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		$required = array( 'algorithm', 'challenge', 'number', 'salt', 'signature' );
		foreach ( $required as $field ) {
			if ( ! array_key_exists( $field, $data ) ) {
				return new WP_Error( 'asfw_missing_field', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}
		}

		if (
			! is_string( $data['algorithm'] ) ||
			! is_string( $data['challenge'] ) ||
			! is_string( $data['salt'] ) ||
			! is_string( $data['signature'] ) ||
			( ! is_int( $data['number'] ) && ! is_string( $data['number'] ) )
		) {
			return new WP_Error( 'asfw_invalid_field_type', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		if ( ! preg_match( '/^\d+$/', (string) $data['number'] ) ) {
			return new WP_Error( 'asfw_invalid_number', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		$number = intval( $data['number'], 10 );
		if ( $number < 0 || $number > 1000000 ) {
			return new WP_Error( 'asfw_invalid_number', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		$data['number'] = (string) $number;

		return $data;
	}

	/**
	 * Check pre-verification guards such as rate limits and honeypot presence.
	 *
	 * @param string $context  Normalized context identifier.
	 * @param string $field_name Form field name prefix.
	 * @return true|WP_Error True if all guards pass, WP_Error otherwise.
	 */
	public function validate_submission_guards( $context, $field_name = 'asfw' ) {
		$rate_limited = $this->is_rate_limited( 'failure', $context );
		if ( $rate_limited instanceof WP_Error ) {
			return $rate_limited;
		}

		if ( $this->get_honeypot() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Honeypot presence is part of the anti-spam verification flow, not a privileged state change.
			if ( ! array_key_exists( $this->get_honeypot_field_name( $field_name ), $_POST ) ) {
				$this->increment_rate_limit( 'failure', $context );

				return new WP_Error( 'asfw_missing_honeypot', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			$honeypot = asfw_get_posted_value( $this->get_honeypot_field_name( $field_name ) );
			if ( '' !== $honeypot ) {
				$this->increment_rate_limit( 'failure', $context );

				return new WP_Error( 'asfw_honeypot', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}
		}

		return true;
	}

	/**
	 * Validate a proof-of-work solution against the stored challenge state.
	 *
	 * Checks algorithm, expiration, transient state, client fingerprint,
	 * minimum submit time, replay protection, HMAC signature, and hash correctness.
	 *
	 * @param string      $payload          Base64-encoded JSON payload.
	 * @param string|null $hmac_key         HMAC secret key. Defaults to the stored secret.
	 * @param string|null $expected_context Expected context to match against the salt.
	 * @return true|WP_Error True on valid solution, WP_Error on failure.
	 */
	public function validate_solution( $payload, $hmac_key = null, $expected_context = null ) {
		if ( null === $hmac_key ) {
			$hmac_key = $this->get_secret();
		}

		if ( empty( $hmac_key ) ) {
			return new WP_Error( 'asfw_missing_secret', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		$data = $this->decode_payload( $payload );
		if ( $data instanceof WP_Error ) {
			return $data;
		}

		if ( 'SHA-256' !== $data['algorithm'] ) {
			return new WP_Error( 'asfw_invalid_algorithm', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		$salt_url = wp_parse_url( $data['salt'] );
		if ( ! is_array( $salt_url ) ) {
			return new WP_Error( 'asfw_invalid_salt', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		$salt_params = array();
		if ( ! empty( $salt_url['query'] ) ) {
			parse_str( $salt_url['query'], $salt_params );
		}

		$context      = isset( $salt_params['context'] ) ? $this->normalize_context( $salt_params['context'] ) : '';
		$challenge_id = isset( $salt_params['challenge_id'] ) ? sanitize_key( $salt_params['challenge_id'] ) : '';
		if ( '' === $context || '' === $challenge_id ) {
			return new WP_Error( 'asfw_missing_challenge_state', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		if ( null !== $expected_context && '' !== $expected_context && $this->normalize_context( $expected_context ) !== $context ) {
			return new WP_Error( 'asfw_context_mismatch', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		if ( ! empty( $salt_params['expires'] ) ) {
			$expires = intval( $salt_params['expires'], 10 );
			if ( $expires > 0 && $expires < time() ) {
				delete_transient( $this->get_challenge_transient_key( $challenge_id ) );

				return new WP_Error( 'asfw_expired', __( 'Verification expired.', 'anti-spam-for-wordpress' ) );
			}
		}

		$challenge_state = get_transient( $this->get_challenge_transient_key( $challenge_id ) );
		if ( ! is_array( $challenge_state ) ) {
			return new WP_Error( 'asfw_unknown_challenge', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		if ( ! empty( $challenge_state['context'] ) && $this->normalize_context( $challenge_state['context'] ) !== $context ) {
			return new WP_Error( 'asfw_transient_context_mismatch', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		if (
			! empty( $challenge_state['fingerprint'] ) &&
			! hash_equals( (string) $challenge_state['fingerprint'], $this->get_client_fingerprint() )
		) {
			return new WP_Error( 'asfw_client_mismatch', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		$min_submit_time = $this->get_min_submit_time();
		if ( $min_submit_time > 0 && ! empty( $challenge_state['issued_at'] ) ) {
			$issued_at = intval( $challenge_state['issued_at'], 10 );
			if ( $issued_at > 0 && ( time() - $issued_at ) < $min_submit_time ) {
				return new WP_Error( 'asfw_submitted_too_fast', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}
		}

		if ( ! $this->acquire_challenge_lock( $challenge_id ) ) {
			return new WP_Error( 'asfw_replay_locked', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		$calculated_challenge = hash( 'sha256', $data['salt'] . $data['number'] );
		if ( ! hash_equals( $calculated_challenge, $data['challenge'] ) ) {
			$this->release_challenge_lock( $challenge_id );

			return new WP_Error( 'asfw_invalid_challenge', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		$calculated_signature = hash_hmac( 'sha256', $data['challenge'], $hmac_key );
		if ( ! hash_equals( $calculated_signature, $data['signature'] ) ) {
			$this->release_challenge_lock( $challenge_id );

			return new WP_Error( 'asfw_invalid_signature', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
		}

		delete_transient( $this->get_challenge_transient_key( $challenge_id ) );
		$this->release_challenge_lock( $challenge_id );

		return true;
	}

	/**
	 * Full request validation: context resolution, submission guards, and solution verification.
	 *
	 * Combines resolve_expected_context(), validate_submission_guards(), and
	 * validate_solution() into a single call. Manages rate limit counters.
	 *
	 * @param string      $payload    Base64-encoded JSON payload.
	 * @param string|null $hmac_key   HMAC secret key. Defaults to the stored secret.
	 * @param string|null $context    Expected context. If null, resolved from POST data.
	 * @param string      $field_name Form field name prefix.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function validate_request( $payload, $hmac_key = null, $context = null, $field_name = 'asfw' ) {
		$context = $this->resolve_expected_context( $context, $field_name );
		if ( $context instanceof WP_Error ) {
			$this->increment_rate_limit( 'failure', 'generic' );

			return $context;
		}

		$guard_result = $this->validate_submission_guards( $context, $field_name );
		if ( $guard_result instanceof WP_Error ) {
			return $guard_result;
		}

		$result = $this->validate_solution( $payload, $hmac_key, $context );
		if ( $result instanceof WP_Error ) {
			$this->increment_rate_limit( 'failure', $context );

			return $result;
		}

		$this->clear_rate_limit( 'failure', $context );

		return true;
	}

	/**
	 * Verify a proof-of-work submission and fire the asfw_verify_result action.
	 *
	 * This is the primary entry point for integrations to verify form submissions.
	 *
	 * @param string      $payload    Base64-encoded JSON payload.
	 * @param string|null $hmac_key   HMAC secret key. Defaults to the stored secret.
	 * @param string|null $context    Expected context. If null, resolved from POST data.
	 * @param string      $field_name Form field name prefix.
	 * @return bool True if verification passed, false otherwise.
	 */
	public function verify( $payload, $hmac_key = null, $context = null, $field_name = 'asfw' ) {
		$result  = $this->validate_request( $payload, $hmac_key, $context, $field_name );
		$success = ! ( $result instanceof WP_Error );

		do_action( 'asfw_verify_result', $success, $result, $context, $field_name );

		return $success;
	}

	/**
	 * Validate a proof-of-work payload without triggering actions or rate-limit updates.
	 *
	 * @param string      $payload          Base64-encoded JSON payload.
	 * @param string|null $hmac_key         HMAC secret key. Defaults to the stored secret.
	 * @param string|null $expected_context Expected context to match against the salt.
	 * @return bool True on valid solution, false otherwise.
	 */
	public function verify_solution( $payload, $hmac_key = null, $expected_context = null ) {
		return ! ( $this->validate_solution( $payload, $hmac_key, $expected_context ) instanceof WP_Error );
	}

	/**
	 * Generate a new proof-of-work challenge.
	 *
	 * Creates a SHA-256 challenge with HMAC signature, stores state in a transient,
	 * and enforces rate limits. Difficulty ranges: low (25k-50k), medium (100k-200k),
	 * high (300k-600k) iterations.
	 *
	 * @param string|null $hmac_key   HMAC secret key. Defaults to the stored secret.
	 * @param string|null $complexity Difficulty level: 'low', 'medium', or 'high'.
	 * @param int|null    $expires    Challenge TTL in seconds. Defaults to configured value.
	 * @param string|null $context    Context identifier. Defaults to 'generic'.
	 * @return array|WP_Error Challenge data array on success, WP_Error if rate limited.
	 */
	public function generate_challenge( $hmac_key = null, $complexity = null, $expires = null, $context = null ) {
		if ( null === $hmac_key ) {
			$hmac_key = $this->get_secret();
		}

		if ( null === $complexity ) {
			$complexity = $this->get_complexity();
		}

		if ( null === $expires ) {
			$expires = intval( $this->get_expires(), 10 );
		}

		$context      = $this->normalize_context( $context );
		$rate_limited = $this->is_rate_limited( 'challenge', $context );
		if ( $rate_limited instanceof WP_Error ) {
			return $rate_limited;
		}

		$challenge_id  = $this->random_secret();
		$salt          = $this->random_secret();
		$transient_ttl = max( 60, $expires > 0 ? $expires : 300 );
		$salt         .= '?' . http_build_query(
			array(
				'challenge_id' => $challenge_id,
				'context'      => $context,
				'expires'      => time() + $transient_ttl,
			)
		);

		if ( ! str_ends_with( $salt, '&' ) ) {
			$salt .= '&';
		}

		switch ( $complexity ) {
			case 'low':
				$min_secret = 25000;
				$max_secret = 50000;
				break;
			case 'medium':
				$min_secret = 100000;
				$max_secret = 200000;
				break;
			case 'high':
				$min_secret = 300000;
				$max_secret = 600000;
				break;
			default:
				$min_secret = 25000;
				$max_secret = 50000;
		}

		$secret_number = random_int( $min_secret, $max_secret );
		$challenge     = hash( 'sha256', $salt . $secret_number );
		$signature     = hash_hmac( 'sha256', $challenge, $hmac_key );
		set_transient(
			$this->get_challenge_transient_key( $challenge_id ),
			array(
				'context'     => $context,
				'fingerprint' => $this->get_client_fingerprint(),
				'issued_at'   => time(),
			),
			$transient_ttl
		);
		$this->increment_rate_limit( 'challenge', $context );

		$challenge_data = array(
			'algorithm' => 'SHA-256',
			'challenge' => $challenge,
			'maxnumber' => $max_secret,
			'salt'      => $salt,
			'signature' => $signature,
		);

		do_action( 'asfw_challenge_issued', $challenge_data, $context, $challenge_id );

		return $challenge_data;
	}

	/**
	 * Build the HTML attribute array for a widget element.
	 *
	 * @param string      $mode     Integration mode identifier.
	 * @param string|null $language Locale override for widget strings.
	 * @param string|null $name     Custom field name prefix.
	 * @param string|null $context  Custom context identifier.
	 * @return array Associative array of HTML attribute key-value pairs.
	 */
	public function get_widget_attrs( $mode, $language = null, $name = null, $context = null ) {
		$floating   = $this->get_floating();
		$delay      = $this->get_delay();
		$field_name = 'asfw';
		if ( null !== $name && '' !== $name ) {
			$field_name = sanitize_key( $name );
		}
		$context = $this->get_widget_context( $mode, $field_name, $context );
		$strings = wp_json_encode( $this->get_translations( $language ) );
		$auto    = $this->get_auto();
		$lazy    = $this->get_lazy();
		$attrs   = array(
			'data-asfw-context'         => $context,
			'data-asfw-field'           => $field_name,
			'data-asfw-lazy'            => $lazy ? '1' : '0',
			'data-asfw-min-submit-time' => (string) max( 0, $this->get_min_submit_time() ),
			'data-asfw-provider'        => $this->get_widget_provider(),
			'strings'                   => $strings,
		);

		$privacy_url = $this->get_privacy_url();
		if ( '' !== $privacy_url ) {
			$attrs['data-asfw-privacy-url']     = $privacy_url;
			$attrs['data-asfw-privacy-new-tab'] = $this->get_privacy_new_tab() ? '1' : '0';
		}

		$challenge_url = $this->get_challenge_url( $context );
		if ( $lazy && 'onload' !== $auto ) {
			$attrs['data-asfw-challengeurl'] = $challenge_url;
		} else {
			$attrs['challengeurl'] = $challenge_url;
		}

		$attrs['name'] = $field_name;

		if ( $auto ) {
			$attrs['auto'] = $auto;
		}

		if ( $floating ) {
			$attrs['floating'] = 'auto';
		}

		if ( $delay ) {
			$attrs['delay'] = '1500';
		}

		if ( $this->get_hidelogo() ) {
			$attrs['hidelogo'] = '1';
		}

		if ( $this->get_hidefooter() ) {
			$attrs['hidefooter'] = '1';
		}

		return apply_filters( 'asfw_widget_attrs', $attrs, $mode, $language, $field_name, $context );
	}

	/**
	 * Render hidden auxiliary form fields: timestamp, context, signature, and honeypot.
	 *
	 * @param string $field_name Form field name prefix.
	 * @param string $context    Normalized context identifier.
	 * @return string HTML markup for the hidden fields.
	 */
	public function render_widget_auxiliary_fields( $field_name = 'asfw', $context = 'generic' ) {
		$html  = '<input type="hidden" name="' . esc_attr( $this->get_started_field_name( $field_name ) ) . '" value="">';
		$html .= '<input type="hidden" name="' . esc_attr( $this->get_context_field_name( $field_name ) ) . '" value="' . esc_attr( $context ) . '">';
		$html .= '<input type="hidden" name="' . esc_attr( $this->get_context_signature_field_name( $field_name ) ) . '" value="' . esc_attr( $this->sign_widget_context( $context, $field_name ) ) . '">';

		if ( $this->get_honeypot() ) {
			$html .= '<div class="asfw-honeypot" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">';
			$html .= '<input type="text" autocomplete="off" tabindex="-1" name="' . esc_attr( $this->get_honeypot_field_name( $field_name ) ) . '" value="">';
			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * Render the complete widget markup including the custom element and auxiliary fields.
	 *
	 * @param string      $mode     Integration mode identifier.
	 * @param bool        $wrap     Whether to wrap in a container div.
	 * @param string|null $language Locale override for widget strings.
	 * @param string|null $name     Custom field name prefix.
	 * @param string|null $context  Custom context identifier.
	 * @return string Sanitized HTML markup.
	 */
	public function render_widget( $mode, $wrap = false, $language = null, $name = null, $context = null ) {
		asfw_enqueue_scripts();
		asfw_enqueue_styles();

		$field_name = 'asfw';
		if ( null !== $name && '' !== $name ) {
			$field_name = sanitize_key( $name );
		}
		$normalized_context = $this->get_widget_context( $mode, $field_name, $context );
		$attrs              = $this->get_widget_attrs( $mode, $language, $field_name, $normalized_context );
		$signed_context     = $normalized_context;
		if ( isset( $attrs['data-asfw-context'] ) ) {
			$signed_context = $this->normalize_context( $attrs['data-asfw-context'] );
		}
		$attributes = join(
			' ',
			array_map(
				function ( $key ) use ( $attrs ) {
					if ( is_bool( $attrs[ $key ] ) ) {
						return $attrs[ $key ] ? $key : '';
					}

					return esc_attr( $key ) . '="' . esc_attr( $attrs[ $key ] ) . '"';
				},
				array_keys( $attrs )
			)
		);

		$tag_name = $this->get_widget_tag_name();
		$html     = '<' . $tag_name . ' ' . $attributes . '></' . $tag_name . '>';
		$html    .= $this->render_widget_auxiliary_fields( $field_name, $signed_context );
		$html    .= '<noscript><div class="asfw-no-javascript">';
		$html    .= esc_html__( 'This form requires JavaScript.', 'anti-spam-for-wordpress' );
		$html    .= '</div></noscript>';

		if ( $wrap ) {
			$html = '<div class="asfw-widget-wrap">' . $html . '</div>';
		}

		return apply_filters( 'asfw_widget_html', $html, $mode, $language, $field_name, $signed_context );
	}
}

if ( ! isset( AntiSpamForWordPressPlugin::$instance ) ) {
	$asfw_plugin_instance = new AntiSpamForWordPressPlugin();
	$asfw_plugin_instance->init();
}

require plugin_dir_path( __FILE__ ) . 'admin.php';
require plugin_dir_path( __FILE__ ) . 'settings.php';
