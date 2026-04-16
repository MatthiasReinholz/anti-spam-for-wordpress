<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ASFW_Options', false ) ) {
	class ASFW_Options {

		private function get_feature_or_legacy_option_value( $feature_option, $legacy_option, $default_value = '', $type = 'string' ) {
			$feature_value = get_option( $feature_option, null );
			$legacy_value  = '' !== $legacy_option ? get_option( $legacy_option, null ) : null;

			if ( ! $this->option_matches_default( $feature_value, $default_value, $type ) ) {
				return $feature_value;
			}

			if ( ! $this->option_matches_default( $legacy_value, $default_value, $type ) ) {
				return $legacy_value;
			}

			if ( null !== $feature_value ) {
				return $feature_value;
			}

			if ( null !== $legacy_value ) {
				return $legacy_value;
			}

			return $default_value;
		}

		private function option_matches_default( $value, $default_value, $type = 'string' ) {
			if ( null === $value ) {
				return true;
			}

			switch ( $type ) {
				case 'bool':
					return (bool) $value === (bool) $default_value;
				case 'int':
					return intval( $value, 10 ) === intval( $default_value, 10 );
				default:
					return trim( (string) $value ) === trim( (string) $default_value );
			}
		}

		public function get_complexity() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_complexity ) );
		}

		public function get_expires() {
			return get_option( AntiSpamForWordPressPlugin::$option_expires );
		}

		public function get_secret() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_secret ) );
		}

		public function get_hidelogo() {
			return get_option( AntiSpamForWordPressPlugin::$option_hidelogo );
		}

		public function get_footer_text() {
			$default_text = __( 'Protected by Anti Spam for WordPress', 'anti-spam-for-wordpress' );
			$footer_text  = trim( wp_strip_all_tags( (string) get_option( AntiSpamForWordPressPlugin::$option_footer_text, '' ) ) );

			if ( '' === $footer_text ) {
				return $default_text;
			}

			return $footer_text;
		}

		public function get_hidefooter() {
			return get_option( AntiSpamForWordPressPlugin::$option_hidefooter );
		}

		public function get_privacy_page_id() {
			$value = trim( (string) get_option( AntiSpamForWordPressPlugin::$option_privacy_page, '' ) );

			return ctype_digit( $value ) ? absint( $value ) : 0;
		}

		public function get_privacy_target() {
			return trim( (string) get_option( AntiSpamForWordPressPlugin::$option_privacy_page, '' ) );
		}

		public function get_privacy_custom_url() {
			return esc_url_raw( trim( (string) get_option( AntiSpamForWordPressPlugin::$option_privacy_url, '' ) ) );
		}

		public function get_privacy_new_tab() {
			return (bool) get_option( AntiSpamForWordPressPlugin::$option_privacy_new_tab );
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
			return trim( get_option( AntiSpamForWordPressPlugin::$option_auto ) );
		}

		public function get_floating() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_floating ) );
		}

		public function get_delay() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_delay ) );
		}

		public function get_lazy() {
			return (bool) get_option( AntiSpamForWordPressPlugin::$option_lazy );
		}

		public function get_rate_limit_max_challenges() {
			return intval( get_option( AntiSpamForWordPressPlugin::$option_rate_limit_max_challenges ), 10 );
		}

		public function get_rate_limit_max_failures() {
			return intval( get_option( AntiSpamForWordPressPlugin::$option_rate_limit_max_failures ), 10 );
		}

		public function get_rate_limit_window() {
			return intval( get_option( AntiSpamForWordPressPlugin::$option_rate_limit_window ), 10 );
		}

		public function get_honeypot() {
			return (bool) get_option( AntiSpamForWordPressPlugin::$option_honeypot );
		}

		public function get_min_submit_time() {
			return intval( get_option( AntiSpamForWordPressPlugin::$option_min_submit_time ), 10 );
		}

		public function get_feature_submit_delay_ms() {
			$value = trim( (string) get_option( AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms, '2500' ) );
			if ( ! in_array( $value, array( '1000', '2500', '5000' ), true ) ) {
				return 2500;
			}

			return intval( $value, 10 );
		}

		public function get_visitor_binding() {
			$binding = trim( (string) get_option( AntiSpamForWordPressPlugin::$option_visitor_binding, 'ip' ) );

			return in_array( $binding, array( 'ip', 'ip_ua' ), true ) ? $binding : 'ip';
		}

		public function get_trusted_proxies() {
			return trim( (string) get_option( AntiSpamForWordPressPlugin::$option_trusted_proxies, '' ) );
		}

		public function is_kill_switch_enabled() {
			return (bool) get_option( AntiSpamForWordPressPlugin::$option_kill_switch, false );
		}

		public function get_bunny_enabled() {
			return ASFW_Feature_Registry::is_enabled( 'bunny_shield' );
		}

		public function get_bunny_mode() {
			return ASFW_Feature_Registry::active_mode( 'bunny_shield' );
		}

		public function is_bunny_background_enabled() {
			return ASFW_Feature_Registry::background_enabled( 'bunny_shield' );
		}

		public function get_bunny_api_key() {
			return trim( (string) $this->get_feature_or_legacy_option_value( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key, AntiSpamForWordPressPlugin::$option_bunny_api_key, '' ) );
		}

		public function get_bunny_shield_zone_id() {
			$value = trim( (string) $this->get_feature_or_legacy_option_value( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_zone_id, AntiSpamForWordPressPlugin::$option_bunny_shield_zone_id, '' ) );

			return ctype_digit( $value ) ? absint( $value ) : 0;
		}

		public function get_bunny_access_list_id() {
			$value = trim( (string) $this->get_feature_or_legacy_option_value( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_access_list_id, AntiSpamForWordPressPlugin::$option_bunny_access_list_id, '' ) );

			return ctype_digit( $value ) ? absint( $value ) : 0;
		}

		public function get_bunny_dry_run() {
			return (bool) $this->get_feature_or_legacy_option_value( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_dry_run, AntiSpamForWordPressPlugin::$option_bunny_dry_run, true, 'bool' );
		}

		public function get_bunny_fail_open() {
			return (bool) $this->get_feature_or_legacy_option_value( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_fail_open, AntiSpamForWordPressPlugin::$option_bunny_fail_open, true, 'bool' );
		}

		public function get_bunny_threshold() {
			$value = intval( $this->get_feature_or_legacy_option_value( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_threshold, AntiSpamForWordPressPlugin::$option_bunny_threshold, '10', 'int' ), 10 );

			return max( 1, $value );
		}

		public function get_bunny_dedupe_window() {
			$feature_ttl_minutes = get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_ttl_minutes, null );
			$legacy_ttl_seconds  = get_option( AntiSpamForWordPressPlugin::$option_bunny_dedupe_window, null );

			if ( ! $this->option_matches_default( $feature_ttl_minutes, '60', 'int' ) ) {
				return max( 60, intval( $feature_ttl_minutes, 10 ) * 60 );
			}

			if ( ! $this->option_matches_default( $legacy_ttl_seconds, '3600', 'int' ) ) {
				return max( 60, intval( $legacy_ttl_seconds, 10 ) );
			}

			if ( null !== $feature_ttl_minutes && '' !== trim( (string) $feature_ttl_minutes ) ) {
				return max( 60, intval( $feature_ttl_minutes, 10 ) * 60 );
			}

			if ( null !== $legacy_ttl_seconds && '' !== trim( (string) $legacy_ttl_seconds ) ) {
				return max( 60, intval( $legacy_ttl_seconds, 10 ) );
			}

			return 3600;
		}

		public function get_bunny_action() {
			$action = strtolower( trim( (string) get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_action, 'block' ) ) );
			if ( ! in_array( $action, array( 'block', 'challenge' ), true ) ) {
				$action = 'block';
			}

			return $action;
		}

		public function get_integration_coblocks() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_integration_coblocks ) );
		}

		public function get_integration_contact_form_7() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_integration_contact_form_7 ) );
		}

		public function get_integration_custom() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_integration_custom ) );
		}

		public function get_integration_elementor() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_integration_elementor ) );
		}

		public function get_integration_enfold_theme() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_integration_enfold_theme ) );
		}

		public function get_integration_formidable() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_integration_formidable ) );
		}

		public function get_integration_forminator() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_integration_forminator ) );
		}

		public function get_integration_gravityforms() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_integration_gravityforms ) );
		}

		public function get_integration_woocommerce_register() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_integration_woocommerce_register ) );
		}

		public function get_integration_woocommerce_reset_password() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_integration_woocommerce_reset_password ) );
		}

		public function get_integration_woocommerce_login() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_integration_woocommerce_login ) );
		}

		public function get_integration_html_forms() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_integration_html_forms ) );
		}

		public function get_integration_wordpress_register() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_integration_wordpress_register ) );
		}

		public function get_integration_wordpress_reset_password() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_integration_wordpress_reset_password ) );
		}

		public function get_integration_wordpress_login() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_integration_wordpress_login ) );
		}

		public function get_integration_wordpress_comments() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_integration_wordpress_comments ) );
		}

		public function get_integration_wpdiscuz() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_integration_wpdiscuz ) );
		}

		public function get_integration_wpforms() {
			return trim( get_option( AntiSpamForWordPressPlugin::$option_integration_wpforms ) );
		}
	}
}
