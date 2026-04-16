<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'class-asfw-feature-registry.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-context-catalog.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-settings-schema.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-options.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-context-helper.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-client-identity.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-rate-limiter.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-challenge-manager.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-verifier.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-widget-renderer.php';
require_once plugin_dir_path( __FILE__ ) . 'admin.php';

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

	public static $option_kill_switch = 'asfw_kill_switch';

	public static $option_bunny_enabled = 'asfw_bunny_enabled';

	public static $option_bunny_api_key = 'asfw_bunny_api_key';

	public static $option_bunny_shield_zone_id = 'asfw_bunny_shield_zone_id';

	public static $option_bunny_access_list_id = 'asfw_bunny_access_list_id';

	public static $option_bunny_dry_run = 'asfw_bunny_dry_run';

	public static $option_bunny_fail_open = 'asfw_bunny_fail_open';

	public static $option_bunny_threshold = 'asfw_bunny_threshold';

	public static $option_bunny_dedupe_window = 'asfw_bunny_dedupe_window';

	public static $option_feature_bunny_shield_enabled = 'asfw_feature_bunny_shield_enabled';

	public static $option_feature_bunny_shield_dry_run = 'asfw_feature_bunny_shield_dry_run';

	public static $option_feature_bunny_shield_fail_open = 'asfw_feature_bunny_shield_fail_open';

	public static $option_feature_bunny_shield_api_key = 'asfw_feature_bunny_shield_api_key';

	public static $option_feature_bunny_shield_zone_id = 'asfw_feature_bunny_shield_zone_id';

	public static $option_feature_bunny_shield_access_list_id = 'asfw_feature_bunny_shield_access_list_id';

	public static $option_feature_bunny_shield_threshold = 'asfw_feature_bunny_shield_threshold';

	public static $option_feature_bunny_shield_ttl_minutes = 'asfw_feature_bunny_shield_ttl_minutes';

	public static $option_feature_bunny_shield_action = 'asfw_feature_bunny_shield_action';

	public static $option_feature_submit_delay_ms = 'asfw_feature_submit_delay_ms';

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
			'inputmode'    => array(),
			'name'         => array(),
			'pattern'      => array(),
			'required'     => array(),
			'tabindex'     => array(),
			'type'         => array(),
			'value'        => array(),
			'style'        => array(),
		),
		'label'       => array(
			'class' => array(),
			'for'   => array(),
		),
		'p'           => array(
			'class' => array(),
			'id'    => array(),
		),
				'span'        => array(
					'aria-live'                    => array(),
					'class'                        => array(),
					'data-asfw-submit-delay-ms'    => array(),
					'data-asfw-submit-delay-mode'  => array(),
					'data-asfw-submit-delay-token-url' => array(),
					'data-asfw-submit-delay-until' => array(),
					'role'                         => array(),
				),
		'noscript'    => array(),
	);

	public static $hostname = null;

	private $options_service;
	private $context_helper_service;
	private $client_identity_service;
	private $rate_limiter_service;
	private $challenge_manager_service;
	private $verifier_service;
	private $widget_renderer_service;

	private function options_service() {
		if ( ! $this->options_service instanceof ASFW_Options ) {
			$this->options_service = new ASFW_Options();
		}

		return $this->options_service;
	}

	private function context_helper_service() {
		if ( ! $this->context_helper_service instanceof ASFW_Context_Helper ) {
			$this->context_helper_service = new ASFW_Context_Helper();
		}

		return $this->context_helper_service;
	}

	private function client_identity_service() {
		if ( ! $this->client_identity_service instanceof ASFW_Client_Identity ) {
			$this->client_identity_service = new ASFW_Client_Identity();
		}

		return $this->client_identity_service;
	}

	private function rate_limiter_service() {
		if ( ! $this->rate_limiter_service instanceof ASFW_Rate_Limiter ) {
			$this->rate_limiter_service = new ASFW_Rate_Limiter();
		}

		return $this->rate_limiter_service;
	}

	private function challenge_manager_service() {
		if ( ! $this->challenge_manager_service instanceof ASFW_Challenge_Manager ) {
			$this->challenge_manager_service = new ASFW_Challenge_Manager();
		}

		return $this->challenge_manager_service;
	}

	private function verifier_service() {
		if ( ! $this->verifier_service instanceof ASFW_Verifier ) {
			$this->verifier_service = new ASFW_Verifier();
		}

		return $this->verifier_service;
	}

	private function widget_renderer_service() {
		if ( ! $this->widget_renderer_service instanceof ASFW_Widget_Renderer ) {
			$this->widget_renderer_service = new ASFW_Widget_Renderer();
		}

		return $this->widget_renderer_service;
	}

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

		$this->options_service();
		$this->context_helper_service();
		$this->client_identity_service();
		$this->rate_limiter_service();
		$this->challenge_manager_service();
		$this->verifier_service();
		$this->widget_renderer_service();
	}

	public function get_complexity() {
		return $this->options_service()->get_complexity();
	}

	public function get_expires() {
		return $this->options_service()->get_expires();
	}

	public function get_secret() {
		return $this->options_service()->get_secret();
	}

	public function get_hidelogo() {
		return $this->options_service()->get_hidelogo();
	}

	public function get_footer_text() {
		return $this->options_service()->get_footer_text();
	}

	public function get_hidefooter() {
		return $this->options_service()->get_hidefooter();
	}

	public function get_privacy_page_id() {
		return $this->options_service()->get_privacy_page_id();
	}

	public function get_privacy_target() {
		return $this->options_service()->get_privacy_target();
	}

	public function get_privacy_custom_url() {
		return $this->options_service()->get_privacy_custom_url();
	}

	public function get_privacy_new_tab() {
		return $this->options_service()->get_privacy_new_tab();
	}

	public function get_privacy_url() {
		return $this->options_service()->get_privacy_url();
	}

	public function get_auto() {
		return $this->options_service()->get_auto();
	}

	public function get_floating() {
		return $this->options_service()->get_floating();
	}

	public function get_delay() {
		return $this->options_service()->get_delay();
	}

	public function get_lazy() {
		return $this->options_service()->get_lazy();
	}

	public function get_rate_limit_max_challenges() {
		return $this->options_service()->get_rate_limit_max_challenges();
	}

	public function get_rate_limit_max_failures() {
		return $this->options_service()->get_rate_limit_max_failures();
	}

	public function get_rate_limit_window() {
		return $this->options_service()->get_rate_limit_window();
	}

	public function get_honeypot() {
		return $this->options_service()->get_honeypot();
	}

	public function get_min_submit_time() {
		return $this->options_service()->get_min_submit_time();
	}

	public function get_feature_submit_delay_ms() {
		return $this->options_service()->get_feature_submit_delay_ms();
	}

	public function get_visitor_binding() {
		return $this->options_service()->get_visitor_binding();
	}

	public function get_trusted_proxies() {
		return $this->options_service()->get_trusted_proxies();
	}

	public function is_kill_switch_enabled() {
		return $this->options_service()->is_kill_switch_enabled();
	}

	public function get_bunny_enabled() {
		return $this->options_service()->get_bunny_enabled();
	}

	public function get_bunny_mode() {
		return $this->options_service()->get_bunny_mode();
	}

	public function is_bunny_background_enabled() {
		return $this->options_service()->is_bunny_background_enabled();
	}

	public function get_bunny_api_key() {
		return $this->options_service()->get_bunny_api_key();
	}

	public function get_bunny_shield_zone_id() {
		return $this->options_service()->get_bunny_shield_zone_id();
	}

	public function get_bunny_access_list_id() {
		return $this->options_service()->get_bunny_access_list_id();
	}

	public function get_bunny_dry_run() {
		return $this->options_service()->get_bunny_dry_run();
	}

	public function get_bunny_fail_open() {
		return $this->options_service()->get_bunny_fail_open();
	}

	public function get_bunny_threshold() {
		return $this->options_service()->get_bunny_threshold();
	}

	public function get_bunny_dedupe_window() {
		return $this->options_service()->get_bunny_dedupe_window();
	}

	public function get_bunny_action() {
		return $this->options_service()->get_bunny_action();
	}

	public function get_integration_coblocks() {
		return $this->options_service()->get_integration_coblocks();
	}

	public function get_integration_contact_form_7() {
		return $this->options_service()->get_integration_contact_form_7();
	}

	public function get_integration_custom() {
		return $this->options_service()->get_integration_custom();
	}

	public function get_integration_elementor() {
		return $this->options_service()->get_integration_elementor();
	}

	public function get_integration_enfold_theme() {
		return $this->options_service()->get_integration_enfold_theme();
	}

	public function get_integration_formidable() {
		return $this->options_service()->get_integration_formidable();
	}

	public function get_integration_forminator() {
		return $this->options_service()->get_integration_forminator();
	}

	public function get_integration_gravityforms() {
		return $this->options_service()->get_integration_gravityforms();
	}

	public function get_integration_woocommerce_register() {
		return $this->options_service()->get_integration_woocommerce_register();
	}

	public function get_integration_woocommerce_reset_password() {
		return $this->options_service()->get_integration_woocommerce_reset_password();
	}

	public function get_integration_woocommerce_login() {
		return $this->options_service()->get_integration_woocommerce_login();
	}

	public function get_integration_html_forms() {
		return $this->options_service()->get_integration_html_forms();
	}

	public function get_integration_wordpress_register() {
		return $this->options_service()->get_integration_wordpress_register();
	}

	public function get_integration_wordpress_reset_password() {
		return $this->options_service()->get_integration_wordpress_reset_password();
	}

	public function get_integration_wordpress_login() {
		return $this->options_service()->get_integration_wordpress_login();
	}

	public function get_integration_wordpress_comments() {
		return $this->options_service()->get_integration_wordpress_comments();
	}

	public function get_integration_wpdiscuz() {
		return $this->options_service()->get_integration_wpdiscuz();
	}

	public function get_integration_wpforms() {
		return $this->options_service()->get_integration_wpforms();
	}

	public function get_widget_provider() {
		return $this->widget_renderer_service()->get_widget_provider();
	}

	public function get_widget_tag_name() {
		return $this->widget_renderer_service()->get_widget_tag_name();
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
		return $this->context_helper_service()->normalize_context( $context );
	}

	public function get_widget_context( $mode, $name = null, $context = null ) {
		return $this->context_helper_service()->get_widget_context( $mode, $name, $context );
	}

	public function get_challenge_url( $context = null ) {
		return $this->widget_renderer_service()->get_challenge_url( $context );
	}

	public function get_translations( $language = null ) {
		return $this->widget_renderer_service()->get_translations( $language );
	}

	public function get_integrations() {
		if ( $this->is_kill_switch_enabled() ) {
			return array();
		}

		$integrations = array();

		foreach ( ASFW_Feature_Registry::get_integration_features() as $feature ) {
			$getter = isset( $feature['getter'] ) ? $feature['getter'] : null;
			if ( ! $getter || ! method_exists( $this, $getter ) ) {
				continue;
			}

			$integrations[] = $this->{$getter}();
		}

		return apply_filters( 'asfw_integrations', $integrations );
	}

	public function has_active_integrations() {
		if ( $this->is_kill_switch_enabled() ) {
			return false;
		}

		$integrations = $this->get_integrations();

		return in_array( 'captcha', $integrations, true ) || in_array( 'shortcode', $integrations, true );
	}

	public function random_secret() {
		return $this->challenge_manager_service()->random_secret();
	}

	public function get_challenge_transient_key( $challenge_id ) {
		return $this->challenge_manager_service()->get_challenge_transient_key( $challenge_id );
	}

	public function get_challenge_lock_key( $challenge_id ) {
		return $this->challenge_manager_service()->get_challenge_lock_key( $challenge_id );
	}

	public function get_started_field_name( $field_name = 'asfw' ) {
		return $this->context_helper_service()->get_started_field_name( $field_name );
	}

	public function get_honeypot_field_name( $field_name = 'asfw' ) {
		return $this->context_helper_service()->get_honeypot_field_name( $field_name );
	}

	public function get_context_field_name( $field_name = 'asfw' ) {
		return $this->context_helper_service()->get_context_field_name( $field_name );
	}

	public function get_context_signature_field_name( $field_name = 'asfw' ) {
		return $this->context_helper_service()->get_context_signature_field_name( $field_name );
	}

	public function normalize_ip( $ip_address ) {
		return $this->client_identity_service()->normalize_ip( $ip_address );
	}

	public function get_trusted_proxy_list() {
		return $this->client_identity_service()->get_trusted_proxy_list();
	}

	public function ip_matches_range( $ip_address, $range ) {
		return $this->client_identity_service()->ip_matches_range( $ip_address, $range );
	}

	public function is_trusted_proxy_ip( $ip_address ) {
		return $this->client_identity_service()->is_trusted_proxy_ip( $ip_address );
	}

	public function extract_forwarded_for_ip( $header_value ) {
		return $this->client_identity_service()->extract_forwarded_for_ip( $header_value );
	}

	public function extract_forwarded_header_ip( $header_value ) {
		return $this->client_identity_service()->extract_forwarded_header_ip( $header_value );
	}

	public function get_client_ip_address() {
		return $this->client_identity_service()->get_client_ip_address();
	}

	public function get_client_binding_components() {
		return $this->client_identity_service()->get_client_binding_components();
	}

	public function get_client_fingerprint() {
		return $this->client_identity_service()->get_client_fingerprint();
	}

	public function get_rate_limit_key( $type, $context ) {
		return $this->rate_limiter_service()->get_rate_limit_key( $type, $context );
	}

	public function sign_widget_context( $context, $field_name = 'asfw' ) {
		return $this->context_helper_service()->sign_widget_context( $context, $field_name );
	}

	public function sign_guard_token( $feature, $context, $token_id ) {
		return $this->challenge_manager_service()->sign_guard_token( $feature, $context, $token_id );
	}

	public function get_math_challenge_id_field_name() {
		return $this->challenge_manager_service()->get_math_challenge_id_field_name();
	}

	public function get_math_challenge_signature_field_name() {
		return $this->challenge_manager_service()->get_math_challenge_signature_field_name();
	}

	public function get_math_challenge_answer_field_name() {
		return $this->challenge_manager_service()->get_math_challenge_answer_field_name();
	}

	public function get_submit_delay_token_field_name() {
		return $this->challenge_manager_service()->get_submit_delay_token_field_name();
	}

	public function get_submit_delay_signature_field_name() {
		return $this->challenge_manager_service()->get_submit_delay_signature_field_name();
	}

	public function get_math_challenge_transient_key( $challenge_id ) {
		return $this->challenge_manager_service()->get_math_challenge_transient_key( $challenge_id );
	}

	public function get_submit_delay_transient_key( $token_id ) {
		return $this->challenge_manager_service()->get_submit_delay_transient_key( $token_id );
	}

	public function issue_math_challenge( $context ) {
		return $this->challenge_manager_service()->issue_math_challenge( $context );
	}

	public function render_math_challenge_fields( $context ) {
		return $this->challenge_manager_service()->render_math_challenge_fields( $context );
	}

	public function validate_math_challenge_submission( $context ) {
		return $this->challenge_manager_service()->validate_math_challenge_submission( $context );
	}

	public function issue_submit_delay_token( $context, $delay_ms ) {
		return $this->challenge_manager_service()->issue_submit_delay_token( $context, $delay_ms );
	}

	public function render_submit_delay_fields( $context, $delay_ms ) {
		return $this->challenge_manager_service()->render_submit_delay_fields( $context, $delay_ms );
	}

	public function validate_submit_delay_submission( $context, $delay_ms ) {
		return $this->challenge_manager_service()->validate_submit_delay_submission( $context, $delay_ms );
	}

	public function resolve_expected_context( $expected_context, $field_name = 'asfw' ) {
		return $this->verifier_service()->resolve_expected_context( $expected_context, $field_name );
	}

	public function get_rate_limit_limit( $type ) {
		return $this->rate_limiter_service()->get_rate_limit_limit( $type );
	}

	public function get_rate_limit_window_safe() {
		return $this->rate_limiter_service()->get_rate_limit_window_safe();
	}

	public function get_rate_limit_state( $type, $context ) {
		return $this->rate_limiter_service()->get_rate_limit_state( $type, $context );
	}

	public function is_rate_limited( $type, $context ) {
		return $this->rate_limiter_service()->is_rate_limited( $type, $context );
	}

	public function increment_rate_limit( $type, $context ) {
		return $this->rate_limiter_service()->increment_rate_limit( $type, $context );
	}

	public function clear_rate_limit( $type, $context ) {
		$this->rate_limiter_service()->clear_rate_limit( $type, $context );
	}

	public function acquire_challenge_lock( $challenge_id, $ttl = 30 ) {
		return $this->challenge_manager_service()->acquire_challenge_lock( $challenge_id, $ttl );
	}

	public function release_challenge_lock( $challenge_id ) {
		$this->challenge_manager_service()->release_challenge_lock( $challenge_id );
	}

	public function decode_payload( $payload ) {
		return $this->verifier_service()->decode_payload( $payload );
	}

	/**
	 * Check pre-verification guards such as rate limits and honeypot presence.
	 *
	 * @param string $context  Normalized context identifier.
	 * @param string $field_name Form field name prefix.
	 * @return true|WP_Error True if all guards pass, WP_Error otherwise.
	 */
	public function validate_submission_guards( $context, $field_name = 'asfw' ) {
		return $this->verifier_service()->validate_submission_guards( $context, $field_name );
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
		return $this->verifier_service()->validate_solution( $payload, $hmac_key, $expected_context );
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
	 * @param string|null &$resolved_context Resolved normalized context returned by reference.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function validate_request( $payload, $hmac_key = null, $context = null, $field_name = 'asfw', &$resolved_context = null ) {
		return $this->verifier_service()->validate_request( $payload, $hmac_key, $context, $field_name, $resolved_context );
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
		return $this->verifier_service()->verify( $payload, $hmac_key, $context, $field_name );
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
		return $this->verifier_service()->verify_solution( $payload, $hmac_key, $expected_context );
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
	 * @param bool        $count_against_rate_limit Whether challenge generation should count toward the challenge rate limit.
	 * @return array|WP_Error Challenge data array on success, WP_Error if rate limited.
	 */
	public function generate_challenge( $hmac_key = null, $complexity = null, $expires = null, $context = null, $count_against_rate_limit = true ) {
		return $this->challenge_manager_service()->generate_challenge( $hmac_key, $complexity, $expires, $context, $count_against_rate_limit );
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
		return $this->widget_renderer_service()->get_widget_attrs( $mode, $language, $name, $context );
	}

	/**
	 * Render hidden auxiliary form fields: timestamp, context, signature, and honeypot.
	 *
	 * @param string $field_name Form field name prefix.
	 * @param string $context    Normalized context identifier.
	 * @return string HTML markup for the hidden fields.
	 */
	public function render_widget_auxiliary_fields( $field_name = 'asfw', $context = 'generic' ) {
		return $this->widget_renderer_service()->render_widget_auxiliary_fields( $field_name, $context );
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
		return $this->widget_renderer_service()->render_widget( $mode, $wrap, $language, $name, $context );
	}
}

if ( ! isset( AntiSpamForWordPressPlugin::$instance ) ) {
	$asfw_plugin_instance = new AntiSpamForWordPressPlugin();
	$asfw_plugin_instance->init();
}

require_once plugin_dir_path( __FILE__ ) . 'admin.php';
require_once plugin_dir_path( __FILE__ ) . 'settings.php';
