<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ASFW_Feature_Registry {

	const FEATURE_MODES = array( 'off', 'log', 'block', 'challenge' );
	const ACTIVE_FEATURE_MODES = array( 'off', 'log', 'block' );
	const SCOPE_MODES = array( 'all', 'selected' );

	public static function definitions() {
		$definitions = array(
			'event_logging' => array(
				'id'                 => 'event_logging',
				'label'              => __( 'Event logging', 'anti-spam-for-wordpress' ),
				'section'            => 'asfw_control_plane_settings_section',
				'description'        => __( 'Write hashed challenge, verification, and rate-limit events to the local event store.', 'anti-spam-for-wordpress' ),
				'mode_hint'          => __( 'Use log mode for observability. Block is reserved for future enforcement paths.', 'anti-spam-for-wordpress' ),
				'scope_hint'         => __( 'Apply event logging everywhere or only for selected contexts.', 'anti-spam-for-wordpress' ),
				'contexts_hint'      => __( 'Enter one normalized context per line or use commas.', 'anti-spam-for-wordpress' ),
					'enabled_option'     => 'asfw_feature_event_logging_enabled',
					'scope_mode_option'  => 'asfw_feature_event_logging_scope_mode',
					'contexts_option'    => 'asfw_feature_event_logging_contexts',
					'mode_option'        => 'asfw_feature_event_logging_mode',
					'background_option'  => '',
					'default_enabled'    => true,
					'default_scope_mode' => 'all',
					'default_contexts'   => array(),
					'default_mode'       => 'log',
					'show_in_settings'   => true,
				),
			'disposable_email' => array(
				'id'                 => 'disposable_email',
				'label'              => __( 'Disposable email list', 'anti-spam-for-wordpress' ),
				'section'            => 'asfw_control_plane_settings_section',
				'description'        => __( 'Maintain the disposable-domain dataset used by runtime email policy checks.', 'anti-spam-for-wordpress' ),
				'mode_hint'          => __( 'Log mode records disposable-email hits. Block mode rejects matching submissions during verification.', 'anti-spam-for-wordpress' ),
				'scope_hint'         => __( 'Apply disposable-email detection everywhere or only for selected contexts.', 'anti-spam-for-wordpress' ),
				'contexts_hint'      => __( 'Enter one normalized context per line or use commas.', 'anti-spam-for-wordpress' ),
				'background_label'   => __( 'Auto-refresh disposable domain list', 'anti-spam-for-wordpress' ),
				'background_hint'    => __( 'Refresh the cached disposable-domain list during scheduled maintenance.', 'anti-spam-for-wordpress' ),
				'enabled_option'     => 'asfw_feature_disposable_email_enabled',
				'scope_mode_option'  => 'asfw_feature_disposable_email_scope_mode',
				'contexts_option'    => 'asfw_feature_disposable_email_contexts',
				'mode_option'        => 'asfw_feature_disposable_email_mode',
				'background_option'  => 'asfw_feature_disposable_email_background_enabled',
				'default_enabled'    => false,
				'default_scope_mode' => 'all',
				'default_contexts'   => array(),
				'default_mode'       => 'off',
				'default_background' => false,
				'legacy_background_option' => 'asfw_disposable_email_auto_refresh',
				'show_in_settings'   => true,
			),
			'content_heuristics' => array(
				'id'                 => 'content_heuristics',
				'label'              => __( 'Content heuristics', 'anti-spam-for-wordpress' ),
				'section'            => 'asfw_control_plane_settings_section',
				'description'        => __( 'Score submitted content for spam indicators after a verification succeeds.', 'anti-spam-for-wordpress' ),
				'mode_hint'          => __( 'Log mode records suspicious submissions. Block is reserved until a blocking path is implemented.', 'anti-spam-for-wordpress' ),
				'scope_hint'         => __( 'Apply heuristics everywhere or only for selected contexts.', 'anti-spam-for-wordpress' ),
				'contexts_hint'      => __( 'Enter one normalized context per line or use commas.', 'anti-spam-for-wordpress' ),
				'enabled_option'     => 'asfw_feature_content_heuristics_enabled',
				'scope_mode_option'  => 'asfw_feature_content_heuristics_scope_mode',
				'contexts_option'    => 'asfw_feature_content_heuristics_contexts',
				'mode_option'        => 'asfw_feature_content_heuristics_mode',
				'background_option'  => '',
				'default_enabled'    => false,
				'default_scope_mode' => 'all',
				'default_contexts'   => array(),
				'default_mode'       => 'off',
				'legacy_enabled_option' => 'asfw_content_heuristics_enabled',
				'legacy_active_mode' => 'log',
				'show_in_settings'   => true,
			),
			'ip_feeds' => array(
				'id'                 => 'ip_feeds',
				'label'              => __( 'IP feeds', 'anti-spam-for-wordpress' ),
				'section'            => '',
				'enabled_option'     => 'asfw_feature_ip_feeds_enabled',
				'scope_mode_option'  => 'asfw_feature_ip_feeds_scope_mode',
				'contexts_option'    => 'asfw_feature_ip_feeds_contexts',
				'mode_option'        => 'asfw_feature_ip_feeds_mode',
				'background_option'  => 'asfw_feature_ip_feeds_background_enabled',
				'default_enabled'    => false,
				'default_scope_mode' => 'all',
				'default_contexts'   => array(),
				'default_mode'       => 'off',
				'default_background' => false,
				'show_in_settings'   => false,
			),
				'bunny_shield' => array(
				'id'                 => 'bunny_shield',
				'label'              => __( 'Bunny Shield', 'anti-spam-for-wordpress' ),
				'section'            => 'asfw_bunny_settings_section',
				'description'        => __( 'Escalate repeated abuse signals into a Bunny Shield access list when this feature is in block mode.', 'anti-spam-for-wordpress' ),
				'mode_hint'          => __( 'Log mode keeps Bunny observational. Block mode allows automatic remote access-list updates.', 'anti-spam-for-wordpress' ),
				'scope_hint'         => __( 'Apply Bunny escalation everywhere or only for selected contexts.', 'anti-spam-for-wordpress' ),
				'contexts_hint'      => __( 'Enter one normalized context per line or use commas.', 'anti-spam-for-wordpress' ),
				'background_label'   => __( 'Automatic Bunny sync', 'anti-spam-for-wordpress' ),
				'background_hint'    => __( 'Run automatic Bunny Shield updates from verification failures and rate-limit events. The CLI remains available even when this is off.', 'anti-spam-for-wordpress' ),
				'enabled_option'     => 'asfw_feature_bunny_shield_enabled',
				'scope_mode_option'  => 'asfw_feature_bunny_shield_scope_mode',
				'contexts_option'    => 'asfw_feature_bunny_shield_contexts',
				'mode_option'        => 'asfw_feature_bunny_shield_mode',
				'background_option'  => 'asfw_feature_bunny_shield_background_enabled',
				'default_enabled'    => false,
				'default_scope_mode' => 'all',
					'default_contexts'   => array(),
					'default_mode'       => 'off',
					'default_background' => false,
					'legacy_active_mode' => 'block',
					'show_in_settings'   => true,
				),
			'math_challenge' => array(
				'id'                 => 'math_challenge',
				'label'              => __( 'Math challenge', 'anti-spam-for-wordpress' ),
				'section'            => 'asfw_security_settings_section',
				'description'        => __( 'Render and validate a server-signed arithmetic challenge on selected auth and comments contexts.', 'anti-spam-for-wordpress' ),
				'mode_hint'          => __( 'Log mode records failures without blocking. Block mode rejects invalid or missing challenge submissions.', 'anti-spam-for-wordpress' ),
				'scope_hint'         => __( 'Apply math challenge everywhere or only for selected contexts.', 'anti-spam-for-wordpress' ),
				'contexts_hint'      => __( 'Supported contexts: wordpress:login, wordpress:register, wordpress:reset-password, wordpress:comments, wpdiscuz:comments, woocommerce:login, woocommerce:reset-password.', 'anti-spam-for-wordpress' ),
				'enabled_option'     => 'asfw_feature_math_challenge_enabled',
				'scope_mode_option'  => 'asfw_feature_math_challenge_scope_mode',
				'contexts_option'    => 'asfw_feature_math_challenge_contexts',
				'mode_option'        => 'asfw_feature_math_challenge_mode',
				'background_option'  => '',
				'default_enabled'    => false,
				'default_scope_mode' => 'all',
				'default_contexts'   => array(),
				'default_mode'       => 'off',
				'show_in_settings'   => true,
			),
			'submit_delay' => array(
				'id'                 => 'submit_delay',
				'label'              => __( 'Submit delay', 'anti-spam-for-wordpress' ),
				'section'            => 'asfw_security_settings_section',
				'description'        => __( 'Enforce a minimum wait window before selected auth or comment forms may submit.', 'anti-spam-for-wordpress' ),
				'mode_hint'          => __( 'Log mode records early submissions without blocking. Block mode rejects submissions that arrive before the configured delay.', 'anti-spam-for-wordpress' ),
				'scope_hint'         => __( 'Apply submit delay everywhere or only for selected contexts.', 'anti-spam-for-wordpress' ),
				'contexts_hint'      => __( 'Supported contexts: wordpress:login, wordpress:register, wordpress:reset-password, wordpress:comments, wpdiscuz:comments, woocommerce:login, woocommerce:reset-password.', 'anti-spam-for-wordpress' ),
				'enabled_option'     => 'asfw_feature_submit_delay_enabled',
				'scope_mode_option'  => 'asfw_feature_submit_delay_scope_mode',
				'contexts_option'    => 'asfw_feature_submit_delay_contexts',
				'mode_option'        => 'asfw_feature_submit_delay_mode',
				'background_option'  => '',
				'default_enabled'    => false,
				'default_scope_mode' => 'all',
				'default_contexts'   => array(),
				'default_mode'       => 'off',
				'show_in_settings'   => true,
			),
		);

		return apply_filters( 'asfw_feature_registry_definitions', $definitions );
	}

	public static function kill_switch_active() {
		return (bool) get_option( AntiSpamForWordPressPlugin::$option_kill_switch, false );
	}

	public static function mode( string $feature ): string {
		$definition = self::get_definition( $feature );
		if ( null === $definition ) {
			return 'off';
		}

		$default_value = isset( $definition['default_mode'] ) ? (string) $definition['default_mode'] : 'off';
		$value         = strtolower( trim( (string) get_option( $definition['mode_option'], $default_value ) ) );
		if ( ! in_array( $value, self::FEATURE_MODES, true ) ) {
			$value = $default_value;
		}

		return $value;
	}

	public static function active_mode( string $feature ): string {
		$mode = self::mode( $feature );

		return in_array( $mode, self::ACTIVE_FEATURE_MODES, true ) ? $mode : 'off';
	}

	public static function scope_mode( string $feature ): string {
		$definition = self::get_definition( $feature );
		if ( null === $definition ) {
			return 'all';
		}

		$default_value = isset( $definition['default_scope_mode'] ) ? (string) $definition['default_scope_mode'] : 'all';
		$value         = strtolower( trim( (string) get_option( $definition['scope_mode_option'], $default_value ) ) );

		return in_array( $value, self::SCOPE_MODES, true ) ? $value : 'all';
	}

	public static function selected_contexts( string $feature ) {
		$definition = self::get_definition( $feature );
		if ( null === $definition ) {
			return array();
		}

		$selected_contexts = get_option( $definition['contexts_option'], $definition['default_contexts'] );
		if ( ! is_array( $selected_contexts ) ) {
			$selected_contexts = preg_split( '/[\r\n,]+/', (string) $selected_contexts, -1, PREG_SPLIT_NO_EMPTY );
		}

		if ( ! is_array( $selected_contexts ) ) {
			return array();
		}

		$selected_contexts = array_values(
			array_unique(
				array_filter(
					array_map(
						array( __CLASS__, 'sanitize_selected_context' ),
						$selected_contexts
					)
				)
			)
		);

		return $selected_contexts;
	}

	public static function background_enabled( string $feature ): bool {
		$definition = self::get_definition( $feature );
		if ( null === $definition || empty( $definition['background_option'] ) ) {
			return false;
		}

		$default_value = ! empty( $definition['default_background'] );
		$value         = get_option( $definition['background_option'], $default_value );

		return (bool) $value;
	}

	public static function scope_matches( string $feature, string $context ): bool {
		$definition = self::get_definition( $feature );
		if ( null === $definition ) {
			return false;
		}

		$scope_mode = self::scope_mode( $feature );
		if ( 'selected' !== $scope_mode ) {
			return true;
		}

		$selected_contexts = self::selected_contexts( $feature );
		if ( empty( $selected_contexts ) ) {
			return false;
		}

		$normalized_context = self::normalize_context( $context );
		foreach ( $selected_contexts as $selected_context ) {
			if ( $normalized_context === self::normalize_context( $selected_context ) ) {
				return true;
			}
		}

		return false;
	}

	public static function is_enabled( string $feature, ?string $context = null ): bool {
		if ( self::kill_switch_active() ) {
			return false;
		}

		$definition = self::get_definition( $feature );
		if ( null === $definition ) {
			return false;
		}

		$default_enabled = ! empty( $definition['default_enabled'] );
		$enabled         = (bool) get_option( $definition['enabled_option'], $default_enabled );

		if ( ! $enabled || 'off' === self::active_mode( $feature ) ) {
			return false;
		}

		if ( null !== $context && '' !== $context && ! self::scope_matches( $feature, $context ) ) {
			return false;
		}

		return true;
	}

	public static function get_settings_features() {
		$features = array();

		foreach ( self::definitions() as $definition ) {
			if ( ! is_array( $definition ) || empty( $definition['show_in_settings'] ) || empty( $definition['section'] ) ) {
				continue;
			}

			$features[] = $definition;
		}

		return $features;
	}

	public static function get_registered_settings() {
		$settings = array();

		foreach ( self::definitions() as $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			if ( ! empty( $definition['enabled_option'] ) ) {
				$settings[] = array(
					'option'            => $definition['enabled_option'],
					'sanitize_callback' => 'asfw_sanitize_checkbox_option',
				);
			}

			if ( ! empty( $definition['mode_option'] ) ) {
				$settings[] = array(
					'option'            => $definition['mode_option'],
					'sanitize_callback' => function ( $value ) {
						return asfw_sanitize_enum_option( $value, ASFW_Feature_Registry::ACTIVE_FEATURE_MODES, 'off' );
					},
				);
			}

			if ( ! empty( $definition['scope_mode_option'] ) ) {
				$settings[] = array(
					'option'            => $definition['scope_mode_option'],
					'sanitize_callback' => function ( $value ) {
						return asfw_sanitize_enum_option( $value, ASFW_Feature_Registry::SCOPE_MODES, 'all' );
					},
				);
			}

			if ( ! empty( $definition['contexts_option'] ) ) {
				$settings[] = array(
					'option'            => $definition['contexts_option'],
					'sanitize_callback' => 'asfw_sanitize_feature_contexts_option',
				);
			}

			if ( ! empty( $definition['background_option'] ) ) {
				$settings[] = array(
					'option'            => $definition['background_option'],
					'sanitize_callback' => 'asfw_sanitize_checkbox_option',
				);
			}
		}

		return $settings;
	}

	public static function get_control_plane_features() {
		$features = array(
			'kill_switch' => array(
				'id'          => 'kill_switch',
				'label'       => __( 'Kill switch', 'anti-spam-for-wordpress' ),
				'description' => __( 'Bypass widget rendering and verification across the site.', 'anti-spam-for-wordpress' ),
				'hint'        => __( 'Leave this off unless you need to disable protection immediately.', 'anti-spam-for-wordpress' ),
				'option'      => AntiSpamForWordPressPlugin::$option_kill_switch,
				'field_id'    => 'asfw_settings_kill_switch_field',
				'section'     => 'asfw_control_plane_settings_section',
				'type'        => 'checkbox',
				'default'     => 0,
			),
		);

		return apply_filters( 'asfw_feature_registry_control_plane', $features );
	}

	public static function get_integration_features() {
		$features = array(
			'coblocks' => self::build_integration_feature(
				'coblocks',
				__( 'CoBlocks', 'anti-spam-for-wordpress' ),
				AntiSpamForWordPressPlugin::$option_integration_coblocks,
				'get_integration_coblocks',
				'asfw_settings_coblocks_integration_field',
				'asfw_integrations_settings_section',
				'coblocks',
				false,
				! asfw_plugin_active( 'coblocks' )
			),
			'contact_form_7' => self::build_integration_feature(
				'contact_form_7',
				__( 'Contact Form 7', 'anti-spam-for-wordpress' ),
				AntiSpamForWordPressPlugin::$option_integration_contact_form_7,
				'get_integration_contact_form_7',
				'asfw_settings_contact_form_7_integration_field',
				'asfw_integrations_settings_section',
				'contact-form-7',
				true,
				! asfw_plugin_active( 'contact-form-7' )
			),
			'elementor' => self::build_integration_feature(
				'elementor',
				__( 'Elementor Pro Forms', 'anti-spam-for-wordpress' ),
				AntiSpamForWordPressPlugin::$option_integration_elementor,
				'get_integration_elementor',
				'asfw_settings_elementor_integration_field',
				'asfw_integrations_settings_section',
				'elementor',
				false,
				! asfw_plugin_active( 'elementor' )
			),
			'enfold_theme' => self::build_integration_feature(
				'enfold_theme',
				__( 'Enfold Theme', 'anti-spam-for-wordpress' ),
				AntiSpamForWordPressPlugin::$option_integration_enfold_theme,
				'get_integration_enfold_theme',
				'asfw_settings_enfold_theme_integration_field',
				'asfw_integrations_settings_section',
				'enfold-theme',
				false,
				! self::is_enfold_theme_available()
			),
			'formidable' => self::build_integration_feature(
				'formidable',
				__( 'Formidable Forms', 'anti-spam-for-wordpress' ),
				AntiSpamForWordPressPlugin::$option_integration_formidable,
				'get_integration_formidable',
				'asfw_settings_formidable_integration_field',
				'asfw_integrations_settings_section',
				'formidable',
				false,
				! asfw_plugin_active( 'formidable' )
			),
			'forminator' => self::build_integration_feature(
				'forminator',
				__( 'Forminator', 'anti-spam-for-wordpress' ),
				AntiSpamForWordPressPlugin::$option_integration_forminator,
				'get_integration_forminator',
				'asfw_settings_forminator_integration_field',
				'asfw_integrations_settings_section',
				'forminator',
				false,
				! asfw_plugin_active( 'forminator' )
			),
			'gravityforms' => self::build_integration_feature(
				'gravityforms',
				__( 'Gravity Forms', 'anti-spam-for-wordpress' ),
				AntiSpamForWordPressPlugin::$option_integration_gravityforms,
				'get_integration_gravityforms',
				'asfw_settings_gravityforms_integration_field',
				'asfw_integrations_settings_section',
				'gravityforms',
				false,
				! asfw_plugin_active( 'gravityforms' )
			),
			'html_forms' => self::build_integration_feature(
				'html_forms',
				__( 'HTML Forms', 'anti-spam-for-wordpress' ),
				AntiSpamForWordPressPlugin::$option_integration_html_forms,
				'get_integration_html_forms',
				'asfw_settings_html_forms_integration_field',
				'asfw_integrations_settings_section',
				'html-forms',
				true,
				! asfw_plugin_active( 'html-forms' )
			),
			'wpdiscuz' => self::build_integration_feature(
				'wpdiscuz',
				__( 'WPDiscuz', 'anti-spam-for-wordpress' ),
				AntiSpamForWordPressPlugin::$option_integration_wpdiscuz,
				'get_integration_wpdiscuz',
				'asfw_settings_wpdiscuz_integration_field',
				'asfw_integrations_settings_section',
				'wpdiscuz:comments',
				false,
				! asfw_plugin_active( 'wpdiscuz' )
			),
			'wpforms' => self::build_integration_feature(
				'wpforms',
				__( 'WPForms', 'anti-spam-for-wordpress' ),
				AntiSpamForWordPressPlugin::$option_integration_wpforms,
				'get_integration_wpforms',
				'asfw_settings_wpforms_integration_field',
				'asfw_integrations_settings_section',
				'wpforms',
				false,
				! asfw_plugin_active( 'wpforms' )
			),
			'woocommerce_register' => self::build_integration_feature(
				'woocommerce_register',
				__( 'WooCommerce register page', 'anti-spam-for-wordpress' ),
				AntiSpamForWordPressPlugin::$option_integration_woocommerce_register,
				'get_integration_woocommerce_register',
				'asfw_settings_woocommerce_register_integration_field',
				'asfw_integrations_settings_section',
				'woocommerce:register',
				false,
				! asfw_plugin_active( 'woocommerce' )
			),
			'woocommerce_reset_password' => self::build_integration_feature(
				'woocommerce_reset_password',
				__( 'WooCommerce reset password page', 'anti-spam-for-wordpress' ),
				AntiSpamForWordPressPlugin::$option_integration_woocommerce_reset_password,
				'get_integration_woocommerce_reset_password',
				'asfw_settings_woocommerce_reset_password_integration_field',
				'asfw_integrations_settings_section',
				'woocommerce:reset-password',
				false,
				! asfw_plugin_active( 'woocommerce' )
			),
			'woocommerce_login' => self::build_integration_feature(
				'woocommerce_login',
				__( 'WooCommerce login page', 'anti-spam-for-wordpress' ),
				AntiSpamForWordPressPlugin::$option_integration_woocommerce_login,
				'get_integration_woocommerce_login',
				'asfw_settings_woocommerce_login_integration_field',
				'asfw_integrations_settings_section',
				'woocommerce:login',
				false,
				! asfw_plugin_active( 'woocommerce' )
			),
			'custom' => self::build_integration_feature(
				'custom',
				__( 'Custom HTML', 'anti-spam-for-wordpress' ),
				AntiSpamForWordPressPlugin::$option_integration_custom,
				'get_integration_custom',
				'asfw_settings_custom_integration_field',
				'asfw_integrations_settings_section',
				'custom',
				true,
				false,
				sprintf(
					/* translators: %s is a shortcode tag. */
					__( 'Use the %s shortcode anywhere in your form markup.', 'anti-spam-for-wordpress' ),
					'[anti_spam_widget]'
				)
			),
			'wordpress_register' => self::build_integration_feature(
				'wordpress_register',
				__( 'Register page', 'anti-spam-for-wordpress' ),
				AntiSpamForWordPressPlugin::$option_integration_wordpress_register,
				'get_integration_wordpress_register',
				'asfw_settings_wordpress_register_integration_field',
				'asfw_wordpress_settings_section',
				'wordpress:register',
				false,
				false
			),
			'wordpress_reset_password' => self::build_integration_feature(
				'wordpress_reset_password',
				__( 'Reset password page', 'anti-spam-for-wordpress' ),
				AntiSpamForWordPressPlugin::$option_integration_wordpress_reset_password,
				'get_integration_wordpress_reset_password',
				'asfw_settings_wordpress_reset_password_integration_field',
				'asfw_wordpress_settings_section',
				'wordpress:reset-password',
				false,
				false
			),
			'wordpress_login' => self::build_integration_feature(
				'wordpress_login',
				__( 'Login page', 'anti-spam-for-wordpress' ),
				AntiSpamForWordPressPlugin::$option_integration_wordpress_login,
				'get_integration_wordpress_login',
				'asfw_settings_wordpress_login_integration_field',
				'asfw_wordpress_settings_section',
				'wordpress:login',
				false,
				false
			),
			'wordpress_comments' => self::build_integration_feature(
				'wordpress_comments',
				__( 'Comments', 'anti-spam-for-wordpress' ),
				AntiSpamForWordPressPlugin::$option_integration_wordpress_comments,
				'get_integration_wordpress_comments',
				'asfw_settings_wordpress_comments_integration_field',
				'asfw_wordpress_settings_section',
				'wordpress:comments',
				false,
				false
			),
		);

		return apply_filters( 'asfw_feature_registry_integrations', $features );
	}

	public static function get_runtime_feature_options() {
		$options = array();

		foreach ( self::get_control_plane_features() as $feature ) {
			$options[] = $feature['option'];
		}

		foreach ( self::get_integration_features() as $feature ) {
			$options[] = $feature['option'];
		}

		return array_values( array_unique( $options ) );
	}

	public static function get_context_catalog() {
		$catalog = array(
			'generic' => array(
				'context'     => 'generic',
				'label'       => __( 'Generic widget', 'anti-spam-for-wordpress' ),
				'group'       => 'core',
				'description' => __( 'Fallback context used when no explicit context is supplied.', 'anti-spam-for-wordpress' ),
			),
			'form:captcha' => array(
				'context'     => 'form:captcha',
				'label'       => __( 'Form widget', 'anti-spam-for-wordpress' ),
				'group'       => 'core',
				'description' => __( 'Automatic context used when the widget is rendered in captcha mode without an explicit context.', 'anti-spam-for-wordpress' ),
			),
			'form:shortcode' => array(
				'context'     => 'form:shortcode',
				'label'       => __( 'Shortcode widget', 'anti-spam-for-wordpress' ),
				'group'       => 'core',
				'description' => __( 'Automatic context used when the widget is rendered in shortcode mode without an explicit context.', 'anti-spam-for-wordpress' ),
			),
			'form:custom' => array(
				'context'     => 'form:custom',
				'label'       => __( 'Custom form widget', 'anti-spam-for-wordpress' ),
				'group'       => 'core',
				'description' => __( 'Automatic context used for custom mode before a named context is applied.', 'anti-spam-for-wordpress' ),
			),
		);

		foreach ( self::get_integration_features() as $feature ) {
			foreach ( $feature['contexts'] as $context ) {
				$catalog[ $context ] = array(
					'context'     => $context,
					'label'       => $feature['label'],
					'group'       => $feature['group'],
					'description' => $feature['description'],
				);
			}
		}

		$known_contexts = apply_filters( 'asfw_known_contexts', array_keys( $catalog ) );
		if ( is_array( $known_contexts ) ) {
			foreach ( $known_contexts as $known_context ) {
				$known_context = self::normalize_context( $known_context );
				if ( '' === $known_context || isset( $catalog[ $known_context ] ) ) {
					continue;
				}

				$catalog[ $known_context ] = array(
					'context'     => $known_context,
					'label'       => $known_context,
					'group'       => 'custom',
					'description' => __( 'Context added by an extension.', 'anti-spam-for-wordpress' ),
				);
			}
		}

		return apply_filters( 'asfw_context_catalog', $catalog );
	}

	public static function get_context_catalog_entry( $context ) {
		$catalog = self::get_context_catalog();
		$context = self::normalize_context( $context );

		return isset( $catalog[ $context ] ) ? $catalog[ $context ] : null;
	}

	public static function build_widget_context( $mode, $name = null, $context = null ) {
		if ( null === $context || '' === $context ) {
			$mode_context = $mode;
			if ( null === $mode_context || '' === $mode_context ) {
				$mode_context = 'custom';
			}

			$context = 'form:' . $mode_context;
			if ( ! empty( $name ) ) {
				$context .= ':' . asfw_sanitize_slug_option( $name );
			}
		}

		return self::normalize_context( $context );
	}

	public static function normalize_context( $context ) {
		$context = strtolower( (string) $context );
		$context = preg_replace( '/[^a-z0-9:._-]/', '-', $context );
		$context = substr( (string) $context, 0, 128 );
		$context = trim( (string) $context, '-' );

		if ( '' === $context ) {
			$context = 'generic';
		}

		return $context;
	}

	public static function sanitize_selected_context( $context ) {
		$context = trim( (string) $context );
		if ( '' === $context ) {
			return '';
		}

		$normalized = self::normalize_context( $context );
		if ( 'generic' === $normalized && 'generic' !== strtolower( $context ) ) {
			return '';
		}

		return $normalized;
	}

	private static function build_integration_feature( $id, $label, $option, $getter, $field_id, $section, $context, $allow_shortcode = false, $disabled = false, $description = '' ) {
		if ( '' === $description ) {
			$description = sprintf(
				/* translators: %s is a feature label. */
				__( 'Enable protection for %s.', 'anti-spam-for-wordpress' ),
				$label
			);
		}

		$hint = $allow_shortcode
			? sprintf(
				/* translators: %s is a feature label. */
				__( 'Toggle the %s integration between disable, captcha, and shortcode modes.', 'anti-spam-for-wordpress' ),
				$label
			)
			: sprintf(
				/* translators: %s is a feature label. */
				__( 'Toggle the %s integration between disable and captcha modes.', 'anti-spam-for-wordpress' ),
				$label
			);

		return array(
			'id'              => $id,
			'label'           => $label,
			'description'     => $description,
			'hint'            => $hint,
			'option'          => $option,
			'getter'          => $getter,
			'field_id'        => $field_id,
			'section'         => $section,
			'group'           => false !== strpos( $section, 'wordpress' ) ? 'wordpress' : 'integrations',
			'type'            => 'select',
			'allow_shortcode' => (bool) $allow_shortcode,
			'disabled'        => (bool) $disabled,
			'contexts'        => array( $context ),
			'context'         => $context,
		);
	}

	private static function is_enfold_theme_available() {
		return ! empty(
			array_filter(
				wp_get_themes(),
				function ( $theme ) {
					return stripos( $theme->get( 'Name' ), 'enfold' ) !== false;
				}
			)
		);
	}

	private static function get_definition( string $feature ) {
		$definitions = self::definitions();
		return isset( $definitions[ $feature ] ) && is_array( $definitions[ $feature ] ) ? $definitions[ $feature ] : null;
	}

}
