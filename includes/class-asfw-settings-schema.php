<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

register_activation_hook( ASFW_FILE, 'asfw_seed_control_plane_defaults' );

final class ASFW_Settings_Schema {

	public static function get_sections() {
		return apply_filters(
			'asfw_settings_schema_sections',
			array(
				array(
					'id'       => 'asfw_control_plane_settings_section',
					'title'    => __( 'Control plane', 'anti-spam-for-wordpress' ),
					'callback' => 'asfw_control_plane_section_callback',
				),
				array(
					'id'       => 'asfw_general_settings_section',
					'title'    => __( 'General', 'anti-spam-for-wordpress' ),
					'callback' => 'asfw_general_section_callback',
				),
				array(
					'id'       => 'asfw_security_settings_section',
					'title'    => __( 'Security hardening', 'anti-spam-for-wordpress' ),
					'callback' => 'asfw_security_section_callback',
				),
				array(
					'id'       => 'asfw_bunny_settings_section',
					'title'    => __( 'Bunny Shield', 'anti-spam-for-wordpress' ),
					'callback' => 'asfw_bunny_section_callback',
				),
				array(
					'id'       => 'asfw_widget_settings_section',
					'title'    => __( 'Widget customization', 'anti-spam-for-wordpress' ),
					'callback' => 'asfw_widget_section_callback',
				),
				array(
					'id'       => 'asfw_integrations_settings_section',
					'title'    => __( 'Integrations', 'anti-spam-for-wordpress' ),
					'callback' => 'asfw_integrations_section_callback',
				),
				array(
					'id'       => 'asfw_wordpress_settings_section',
					'title'    => __( 'WordPress', 'anti-spam-for-wordpress' ),
					'callback' => 'asfw_wordpress_section_callback',
				),
				array(
					'id'       => 'asfw_context_catalog_section',
					'title'    => __( 'Context catalog', 'anti-spam-for-wordpress' ),
					'callback' => 'asfw_context_catalog_section_callback',
				),
			)
		);
	}

	public static function get_fields_by_section() {
		$fields = array(
			'asfw_control_plane_settings_section' => array(
				self::checkbox_field(
					'asfw_settings_kill_switch_field',
					AntiSpamForWordPressPlugin::$option_kill_switch,
					__( 'Kill switch', 'anti-spam-for-wordpress' ),
					__( 'Leave this off unless you need to disable protection immediately.', 'anti-spam-for-wordpress' ),
					__( 'Bypass widget rendering and verification across the site.', 'anti-spam-for-wordpress' )
				),
				self::select_field(
					'asfw_settings_event_logging_retention_days_field',
					'asfw_event_logging_retention_days',
					__( 'Event logging retention', 'anti-spam-for-wordpress' ),
					__( 'How long event rows are kept before daily maintenance purges old data.', 'anti-spam-for-wordpress' ),
					array(
						'7'   => __( '7 days', 'anti-spam-for-wordpress' ),
						'14'  => __( '14 days', 'anti-spam-for-wordpress' ),
						'30'  => __( '30 days', 'anti-spam-for-wordpress' ),
						'60'  => __( '60 days', 'anti-spam-for-wordpress' ),
						'90'  => __( '90 days', 'anti-spam-for-wordpress' ),
						'180' => __( '180 days', 'anti-spam-for-wordpress' ),
						'365' => __( '365 days', 'anti-spam-for-wordpress' ),
					),
					'30'
				),
			),
			'asfw_general_settings_section'       => array(
				self::text_field(
					'asfw_settings_secret_field',
					AntiSpamForWordPressPlugin::$option_secret,
					__( 'Secret key', 'anti-spam-for-wordpress' ),
					__( 'Leave blank to keep the current secret. Enter at least 32 characters to rotate it.', 'anti-spam-for-wordpress' ),
					'asfw_sanitize_secret_option',
					'password',
					null,
					array(
						'write_only'  => true,
						'placeholder' => __( 'Unchanged', 'anti-spam-for-wordpress' ),
					)
				),
				self::select_field(
					'asfw_settings_complexity_field',
					AntiSpamForWordPressPlugin::$option_complexity,
					__( 'Complexity', 'anti-spam-for-wordpress' ),
					__( 'Select the proof-of-work complexity for new challenges.', 'anti-spam-for-wordpress' ),
					array(
						'low'    => __( 'Low', 'anti-spam-for-wordpress' ),
						'medium' => __( 'Medium', 'anti-spam-for-wordpress' ),
						'high'   => __( 'High', 'anti-spam-for-wordpress' ),
					),
					'medium'
				),
				self::select_field(
					'asfw_settings_expires_field',
					AntiSpamForWordPressPlugin::$option_expires,
					__( 'Expiration', 'anti-spam-for-wordpress' ),
					__( 'How long a challenge stays valid.', 'anti-spam-for-wordpress' ),
					array(
						'120'  => __( '2 minutes', 'anti-spam-for-wordpress' ),
						'300'  => __( '5 minutes', 'anti-spam-for-wordpress' ),
						'600'  => __( '10 minutes', 'anti-spam-for-wordpress' ),
						'1800' => __( '30 minutes', 'anti-spam-for-wordpress' ),
					),
					'300'
				),
			),
			'asfw_security_settings_section'      => array(
				self::checkbox_field(
					'asfw_settings_lazy_field',
					AntiSpamForWordPressPlugin::$option_lazy,
					__( 'Lazy challenge loading', 'anti-spam-for-wordpress' ),
					__( 'Load challenge data on first interaction instead of immediately on page load.', 'anti-spam-for-wordpress' )
				),
				self::select_field(
					'asfw_settings_rate_limit_window_field',
					AntiSpamForWordPressPlugin::$option_rate_limit_window,
					__( 'Rate limit window', 'anti-spam-for-wordpress' ),
					__( 'Window used for challenge and failure rate limits.', 'anti-spam-for-wordpress' ),
					array(
						'300' => __( '5 minutes', 'anti-spam-for-wordpress' ),
						'600' => __( '10 minutes', 'anti-spam-for-wordpress' ),
						'900' => __( '15 minutes', 'anti-spam-for-wordpress' ),
					),
					'600'
				),
				self::select_field(
					'asfw_settings_rate_limit_challenges_field',
					AntiSpamForWordPressPlugin::$option_rate_limit_max_challenges,
					__( 'Max challenges per window', 'anti-spam-for-wordpress' ),
					__( 'Limit repeated challenge fetches from the same visitor.', 'anti-spam-for-wordpress' ),
					array(
						'0'   => __( 'Disabled', 'anti-spam-for-wordpress' ),
						'15'  => '15',
						'30'  => '30',
						'60'  => '60',
						'120' => '120',
					),
					'30'
				),
				self::select_field(
					'asfw_settings_rate_limit_failures_field',
					AntiSpamForWordPressPlugin::$option_rate_limit_max_failures,
					__( 'Max failed verifications per window', 'anti-spam-for-wordpress' ),
					__( 'Throttle repeated bad submissions from the same visitor.', 'anti-spam-for-wordpress' ),
					array(
						'0'  => __( 'Disabled', 'anti-spam-for-wordpress' ),
						'5'  => '5',
						'10' => '10',
						'20' => '20',
						'50' => '50',
					),
					'10'
				),
				self::checkbox_field(
					'asfw_settings_honeypot_field',
					AntiSpamForWordPressPlugin::$option_honeypot,
					__( 'Honeypot field', 'anti-spam-for-wordpress' ),
					__( 'Add an off-screen trap field to catch simple bots.', 'anti-spam-for-wordpress' )
				),
				self::select_field(
					'asfw_settings_min_submit_time_field',
					AntiSpamForWordPressPlugin::$option_min_submit_time,
					__( 'Minimum submit time', 'anti-spam-for-wordpress' ),
					__( 'Reject submissions that complete too quickly.', 'anti-spam-for-wordpress' ),
					array(
						'0'  => __( 'Disabled', 'anti-spam-for-wordpress' ),
						'2'  => __( '2 seconds', 'anti-spam-for-wordpress' ),
						'3'  => __( '3 seconds', 'anti-spam-for-wordpress' ),
						'5'  => __( '5 seconds', 'anti-spam-for-wordpress' ),
						'10' => __( '10 seconds', 'anti-spam-for-wordpress' ),
					),
					'3'
				),
				self::select_field(
					'asfw_settings_feature_submit_delay_ms_field',
					AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms,
					__( 'Submit delay duration', 'anti-spam-for-wordpress' ),
					__( 'Delay duration used when the Submit delay feature is active for a context.', 'anti-spam-for-wordpress' ),
					array(
						'1000' => __( '1 second', 'anti-spam-for-wordpress' ),
						'2500' => __( '2.5 seconds', 'anti-spam-for-wordpress' ),
						'5000' => __( '5 seconds', 'anti-spam-for-wordpress' ),
					),
					'2500'
				),
				self::select_field(
					'asfw_settings_visitor_binding_field',
					AntiSpamForWordPressPlugin::$option_visitor_binding,
					__( 'Visitor binding', 'anti-spam-for-wordpress' ),
					__( 'Choose how challenges and rate limits identify a visitor. IP + User Agent reduces collisions on shared IPs but is more sensitive to browser changes.', 'anti-spam-for-wordpress' ),
					array(
						'ip'    => __( 'IP address', 'anti-spam-for-wordpress' ),
						'ip_ua' => __( 'IP address + User Agent', 'anti-spam-for-wordpress' ),
					),
					'ip'
				),
				self::text_field(
					'asfw_settings_trusted_proxies_field',
					AntiSpamForWordPressPlugin::$option_trusted_proxies,
					__( 'Trusted proxies', 'anti-spam-for-wordpress' ),
					__( 'Optional comma-separated IPs or CIDR ranges for reverse proxies. When a request comes from one of these proxies, the plugin will trust forwarded client IP headers.', 'anti-spam-for-wordpress' ),
					'asfw_sanitize_trusted_proxies_option',
					'text'
				),
			),
			'asfw_bunny_settings_section'         => array(
				self::text_field(
					'asfw_settings_bunny_api_key_field',
					AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key,
					__( 'Bunny API key', 'anti-spam-for-wordpress' ),
					__( 'Leave blank to keep the current Bunny API key. Enter a new key to rotate it.', 'anti-spam-for-wordpress' ),
					'asfw_sanitize_bunny_api_key_option',
					'password',
					null,
					array(
						'write_only'  => true,
						'placeholder' => __( 'Unchanged', 'anti-spam-for-wordpress' ),
					)
				),
				self::text_field(
					'asfw_settings_bunny_shield_zone_id_field',
					AntiSpamForWordPressPlugin::$option_feature_bunny_shield_zone_id,
					__( 'Shield zone ID', 'anti-spam-for-wordpress' ),
					__( 'The Shield zone that owns the custom access list.', 'anti-spam-for-wordpress' ),
					'asfw_sanitize_bunny_integer_option',
					'number'
				),
				self::text_field(
					'asfw_settings_bunny_access_list_id_field',
					AntiSpamForWordPressPlugin::$option_feature_bunny_shield_access_list_id,
					__( 'Access list ID', 'anti-spam-for-wordpress' ),
					__( 'Leave empty to let the plugin auto-create or discover a list named “Anti Spam for WordPress”.', 'anti-spam-for-wordpress' ),
					'asfw_sanitize_bunny_integer_option',
					'number'
				),
				self::checkbox_field(
					'asfw_settings_bunny_dry_run_field',
					AntiSpamForWordPressPlugin::$option_feature_bunny_shield_dry_run,
					__( 'Dry run', 'anti-spam-for-wordpress' ),
					__( 'Keep automatic hooks observational. The CLI can still manage the remote list explicitly.', 'anti-spam-for-wordpress' )
				),
				self::checkbox_field(
					'asfw_settings_bunny_fail_open_field',
					AntiSpamForWordPressPlugin::$option_feature_bunny_shield_fail_open,
					__( 'Fail open', 'anti-spam-for-wordpress' ),
					__( 'Treat Bunny API failures as operational warnings, keep local verification in control of submissions, and back off before retrying automatic syncs.', 'anti-spam-for-wordpress' )
				),
				self::select_field(
					'asfw_settings_bunny_threshold_field',
					AntiSpamForWordPressPlugin::$option_feature_bunny_shield_threshold,
					__( 'Escalation threshold', 'anti-spam-for-wordpress' ),
					__( 'How many local abuse signals must accumulate before a Bunny update is attempted.', 'anti-spam-for-wordpress' ),
					array(
						'1'  => '1',
						'2'  => '2',
						'3'  => '3',
						'5'  => '5',
						'10' => '10',
					),
					'10'
				),
				self::select_field(
					'asfw_settings_bunny_dedupe_window_field',
					AntiSpamForWordPressPlugin::$option_feature_bunny_shield_ttl_minutes,
					__( 'TTL (minutes)', 'anti-spam-for-wordpress' ),
					__( 'Used for the local Bunny dedupe window. Bunny list retention is managed by Bunny Shield access-list behavior.', 'anti-spam-for-wordpress' ),
					array(
						'15'   => __( '15 minutes', 'anti-spam-for-wordpress' ),
						'60'   => __( '1 hour', 'anti-spam-for-wordpress' ),
						'360'  => __( '6 hours', 'anti-spam-for-wordpress' ),
						'1440' => __( '24 hours', 'anti-spam-for-wordpress' ),
					),
					'60'
				),
				self::select_field(
					'asfw_settings_bunny_action_field',
					AntiSpamForWordPressPlugin::$option_feature_bunny_shield_action,
					__( 'Bunny action', 'anti-spam-for-wordpress' ),
					__( 'Current runtime uses block-style access-list updates only. Challenge remains reserved for future contract expansion.', 'anti-spam-for-wordpress' ),
					array(
						'block' => __( 'Block', 'anti-spam-for-wordpress' ),
					),
					'block',
					array( 'block', 'challenge' )
				),
			),
			'asfw_widget_settings_section'        => array(
				self::select_field(
					'asfw_settings_auto_field',
					AntiSpamForWordPressPlugin::$option_auto,
					__( 'Auto verification', 'anti-spam-for-wordpress' ),
					__( 'Choose when the widget should start verification.', 'anti-spam-for-wordpress' ),
					array(
						''         => __( 'Disabled', 'anti-spam-for-wordpress' ),
						'onload'   => __( 'On page load', 'anti-spam-for-wordpress' ),
						'onfocus'  => __( 'On form focus', 'anti-spam-for-wordpress' ),
						'onsubmit' => __( 'On form submit', 'anti-spam-for-wordpress' ),
					),
					''
				),
				self::checkbox_field(
					'asfw_settings_floating_field',
					AntiSpamForWordPressPlugin::$option_floating,
					__( 'Floating UI', 'anti-spam-for-wordpress' ),
					__( 'Enable the widget floating UI.', 'anti-spam-for-wordpress' )
				),
				self::checkbox_field(
					'asfw_settings_delay_field',
					AntiSpamForWordPressPlugin::$option_delay,
					__( 'Delay', 'anti-spam-for-wordpress' ),
					__( 'Add a 1.5 second delay before verification completes.', 'anti-spam-for-wordpress' )
				),
				self::checkbox_field(
					'asfw_settings_hidelogo_field',
					AntiSpamForWordPressPlugin::$option_hidelogo,
					__( 'Hide logo', 'anti-spam-for-wordpress' ),
					__( 'Hide the logo in the widget footer.', 'anti-spam-for-wordpress' )
				),
				self::text_field(
					'asfw_settings_footer_text_field',
					AntiSpamForWordPressPlugin::$option_footer_text,
					__( 'Footer text', 'anti-spam-for-wordpress' ),
					__( 'Shown in the widget footer when the footer is visible.', 'anti-spam-for-wordpress' ),
					'asfw_sanitize_footer_text_option'
				),
				self::privacy_target_field(
					'asfw_settings_privacy_target_field',
					AntiSpamForWordPressPlugin::$option_privacy_page,
					__( 'Privacy link', 'anti-spam-for-wordpress' ),
					__( 'Choose a page or switch to a custom URL for the footer privacy link.', 'anti-spam-for-wordpress' )
				),
				self::url_field(
					'asfw_settings_privacy_url_field',
					AntiSpamForWordPressPlugin::$option_privacy_url,
					__( 'Privacy URL', 'anti-spam-for-wordpress' ),
					__( 'Used when Custom URL is selected.', 'anti-spam-for-wordpress' ),
					'asfw_sanitize_privacy_url_option'
				),
				self::checkbox_field(
					'asfw_settings_privacy_new_tab_field',
					AntiSpamForWordPressPlugin::$option_privacy_new_tab,
					__( 'Open privacy link in new tab', 'anti-spam-for-wordpress' ),
					__( 'Open the privacy link in a new browser tab.', 'anti-spam-for-wordpress' )
				),
				self::checkbox_field(
					'asfw_settings_hidefooter_field',
					AntiSpamForWordPressPlugin::$option_hidefooter,
					__( 'Hide footer', 'anti-spam-for-wordpress' ),
					__( 'Hide the widget footer entirely.', 'anti-spam-for-wordpress' )
				),
			),
			'asfw_integrations_settings_section'  => array(),
			'asfw_wordpress_settings_section'     => array(),
			'asfw_context_catalog_section'        => array(),
		);

		foreach ( ASFW_Feature_Registry::get_integration_features() as $feature ) {
			$fields[ $feature['section'] ][] = self::integration_select_field( $feature );
		}

		foreach ( ASFW_Feature_Registry::get_settings_features() as $feature ) {
			$feature_fields = self::feature_fields( $feature );
			if ( empty( $feature_fields ) ) {
				continue;
			}

			if ( 'asfw_bunny_settings_section' === $feature['section'] ) {
				$fields[ $feature['section'] ] = array_merge( $feature_fields, $fields[ $feature['section'] ] );
				continue;
			}

			$fields[ $feature['section'] ] = array_merge( $fields[ $feature['section'] ], $feature_fields );
		}

		return apply_filters( 'asfw_settings_schema_fields', $fields );
	}

	public static function get_registered_settings() {
		$settings = array();

		foreach ( self::get_fields_by_section() as $section_fields ) {
			foreach ( $section_fields as $field ) {
				if ( empty( $field['option'] ) ) {
					continue;
				}

				$settings[] = array(
					'option'            => $field['option'],
					'sanitize_callback' => isset( $field['sanitize_callback'] ) ? $field['sanitize_callback'] : null,
				);
			}
		}

		$settings = array_merge( $settings, ASFW_Feature_Registry::get_registered_settings() );

		$unique_settings = array();
		foreach ( $settings as $setting ) {
			if ( empty( $setting['option'] ) ) {
				continue;
			}

			$unique_settings[ $setting['option'] ] = $setting;
		}

		return array_values( $unique_settings );
	}

	private static function checkbox_field( $field_id, $option, $title, $hint, $description = null ) {
		return array(
			'id'                => $field_id,
			'callback'          => 'asfw_settings_field_callback',
			'section'           => self::section_for_option( $option ),
			'title'             => $title,
			'option'            => $option,
			'sanitize_callback' => 'asfw_sanitize_checkbox_option',
			'args'              => array(
				'name'        => $option,
				'description' => $description,
				'hint'        => $hint,
				'type'        => 'checkbox',
			),
		);
	}

	private static function text_field( $field_id, $option, $title, $hint, $sanitize_callback, $type = 'text', $description = null, array $extra_args = array() ) {
		return array(
			'id'                => $field_id,
			'callback'          => 'asfw_settings_field_callback',
			'section'           => self::section_for_option( $option ),
			'title'             => $title,
			'option'            => $option,
			'sanitize_callback' => $sanitize_callback,
			'args'              => array_merge(
				array(
					'name'        => $option,
					'description' => $description,
					'hint'        => $hint,
					'type'        => $type,
				),
				$extra_args
			),
		);
	}

	private static function url_field( $field_id, $option, $title, $hint, $sanitize_callback ) {
		return self::text_field( $field_id, $option, $title, $hint, $sanitize_callback, 'url' );
	}

	private static function textarea_field( $field_id, $option, $title, $hint, $sanitize_callback, $description = null, $placeholder = '' ) {
		return array(
			'id'                => $field_id,
			'callback'          => 'asfw_settings_textarea_callback',
			'section'           => self::section_for_option( $option ),
			'title'             => $title,
			'option'            => $option,
			'sanitize_callback' => $sanitize_callback,
			'args'              => array(
				'name'        => $option,
				'description' => $description,
				'hint'        => $hint,
				'placeholder' => $placeholder,
			),
		);
	}

	private static function privacy_target_field( $field_id, $option, $title, $hint ) {
		return array(
			'id'                => $field_id,
			'callback'          => 'asfw_settings_privacy_target_callback',
			'section'           => 'asfw_widget_settings_section',
			'title'             => $title,
			'option'            => $option,
			'sanitize_callback' => 'asfw_sanitize_privacy_target_option',
			'args'              => array(
				'name' => $option,
				'hint' => $hint,
			),
		);
	}

	private static function select_field( $field_id, $option, $title, $hint, array $options, $default_value = '', ?array $allowed_values = null ) {
		$allowed_values = is_array( $allowed_values ) ? $allowed_values : array_keys( $options );

		return array(
			'id'                => $field_id,
			'callback'          => 'asfw_settings_select_callback',
			'section'           => self::section_for_option( $option ),
			'title'             => $title,
			'option'            => $option,
			'sanitize_callback' => self::select_sanitize_callback( $allowed_values, $default_value ),
			'args'              => array(
				'name'    => $option,
				'hint'    => $hint,
				'options' => $options,
			),
		);
	}

	private static function integration_select_field( array $feature ) {
		$options = array(
			''        => __( 'Disable', 'anti-spam-for-wordpress' ),
			'captcha' => __( 'Captcha', 'anti-spam-for-wordpress' ),
		);

		if ( ! empty( $feature['allow_shortcode'] ) ) {
			$options['shortcode'] = __( 'Shortcode', 'anti-spam-for-wordpress' );
		}

		return array(
			'id'                => $feature['field_id'],
			'callback'          => 'asfw_settings_select_callback',
			'section'           => $feature['section'],
			'title'             => $feature['label'],
			'option'            => $feature['option'],
			'sanitize_callback' => self::select_sanitize_callback( array_keys( $options ), '' ),
			'args'              => array(
				'name'     => $feature['option'],
				'hint'     => $feature['hint'],
				'disabled' => ! empty( $feature['disabled'] ),
				'options'  => $options,
			),
		);
	}

	private static function select_sanitize_callback( array $allowed, $default_value ) {
		return function ( $value ) use ( $allowed, $default_value ) {
			return asfw_sanitize_enum_option( $value, $allowed, $default_value );
		};
	}

	private static function feature_fields( array $feature ) {
		$feature_id = isset( $feature['id'] ) ? (string) $feature['id'] : '';
		if ( '' === $feature_id ) {
			return array();
		}

		$fields = array();
		$label  = isset( $feature['label'] ) ? $feature['label'] : $feature_id;

		$fields[] = self::checkbox_field(
			'asfw_settings_' . $feature_id . '_enabled_field',
			$feature['enabled_option'],
			sprintf(
				/* translators: %s is a feature label. */
				__( 'Enable %s', 'anti-spam-for-wordpress' ),
				$label
			),
			isset( $feature['description'] ) ? $feature['description'] : ''
		);

		$fields[] = self::select_field(
			'asfw_settings_' . $feature_id . '_mode_field',
			$feature['mode_option'],
			sprintf(
				/* translators: %s is a feature label. */
				__( '%s mode', 'anti-spam-for-wordpress' ),
				$label
			),
			isset( $feature['mode_hint'] ) ? $feature['mode_hint'] : '',
			array(
				'off'   => __( 'Off', 'anti-spam-for-wordpress' ),
				'log'   => __( 'Log', 'anti-spam-for-wordpress' ),
				'block' => __( 'Block', 'anti-spam-for-wordpress' ),
			),
			isset( $feature['default_mode'] ) ? $feature['default_mode'] : 'off'
		);

		$fields[] = self::select_field(
			'asfw_settings_' . $feature_id . '_scope_mode_field',
			$feature['scope_mode_option'],
			sprintf(
				/* translators: %s is a feature label. */
				__( '%s scope', 'anti-spam-for-wordpress' ),
				$label
			),
			isset( $feature['scope_hint'] ) ? $feature['scope_hint'] : '',
			array(
				'all'      => __( 'All contexts', 'anti-spam-for-wordpress' ),
				'selected' => __( 'Selected contexts', 'anti-spam-for-wordpress' ),
			),
			isset( $feature['default_scope_mode'] ) ? $feature['default_scope_mode'] : 'all'
		);

		$fields[] = self::textarea_field(
			'asfw_settings_' . $feature_id . '_contexts_field',
			$feature['contexts_option'],
			sprintf(
				/* translators: %s is a feature label. */
				__( '%s selected contexts', 'anti-spam-for-wordpress' ),
				$label
			),
			isset( $feature['contexts_hint'] ) ? $feature['contexts_hint'] : '',
			'asfw_sanitize_feature_contexts_option',
			null,
			"contact-form-7\nwordpress:login"
		);

		if ( ! empty( $feature['background_option'] ) && ! empty( $feature['background_label'] ) ) {
			$fields[] = self::checkbox_field(
				'asfw_settings_' . $feature_id . '_background_field',
				$feature['background_option'],
				$feature['background_label'],
				isset( $feature['background_hint'] ) ? $feature['background_hint'] : ''
			);
		}

		foreach ( $fields as $index => $field ) {
			$fields[ $index ]['section'] = $feature['section'];
		}

		return $fields;
	}

	private static function section_for_option( $option ) {
		$section_map = array(
			AntiSpamForWordPressPlugin::$option_kill_switch => 'asfw_control_plane_settings_section',
			'asfw_event_logging_retention_days'            => 'asfw_control_plane_settings_section',
			AntiSpamForWordPressPlugin::$option_secret     => 'asfw_general_settings_section',
			AntiSpamForWordPressPlugin::$option_complexity => 'asfw_general_settings_section',
			AntiSpamForWordPressPlugin::$option_expires    => 'asfw_general_settings_section',
			AntiSpamForWordPressPlugin::$option_lazy       => 'asfw_security_settings_section',
			AntiSpamForWordPressPlugin::$option_rate_limit_window => 'asfw_security_settings_section',
			AntiSpamForWordPressPlugin::$option_rate_limit_max_challenges => 'asfw_security_settings_section',
			AntiSpamForWordPressPlugin::$option_rate_limit_max_failures => 'asfw_security_settings_section',
			AntiSpamForWordPressPlugin::$option_honeypot   => 'asfw_security_settings_section',
			AntiSpamForWordPressPlugin::$option_min_submit_time => 'asfw_security_settings_section',
			AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms => 'asfw_security_settings_section',
			AntiSpamForWordPressPlugin::$option_visitor_binding => 'asfw_security_settings_section',
			AntiSpamForWordPressPlugin::$option_trusted_proxies => 'asfw_security_settings_section',
			AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled => 'asfw_bunny_settings_section',
			AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key => 'asfw_bunny_settings_section',
			AntiSpamForWordPressPlugin::$option_feature_bunny_shield_zone_id => 'asfw_bunny_settings_section',
			AntiSpamForWordPressPlugin::$option_feature_bunny_shield_access_list_id => 'asfw_bunny_settings_section',
			AntiSpamForWordPressPlugin::$option_feature_bunny_shield_dry_run => 'asfw_bunny_settings_section',
			AntiSpamForWordPressPlugin::$option_feature_bunny_shield_fail_open => 'asfw_bunny_settings_section',
			AntiSpamForWordPressPlugin::$option_feature_bunny_shield_threshold => 'asfw_bunny_settings_section',
			AntiSpamForWordPressPlugin::$option_feature_bunny_shield_ttl_minutes => 'asfw_bunny_settings_section',
			AntiSpamForWordPressPlugin::$option_feature_bunny_shield_action => 'asfw_bunny_settings_section',
			AntiSpamForWordPressPlugin::$option_auto       => 'asfw_widget_settings_section',
			AntiSpamForWordPressPlugin::$option_floating   => 'asfw_widget_settings_section',
			AntiSpamForWordPressPlugin::$option_delay      => 'asfw_widget_settings_section',
			AntiSpamForWordPressPlugin::$option_hidelogo   => 'asfw_widget_settings_section',
			AntiSpamForWordPressPlugin::$option_footer_text => 'asfw_widget_settings_section',
			AntiSpamForWordPressPlugin::$option_privacy_page => 'asfw_widget_settings_section',
			AntiSpamForWordPressPlugin::$option_privacy_url => 'asfw_widget_settings_section',
			AntiSpamForWordPressPlugin::$option_privacy_new_tab => 'asfw_widget_settings_section',
			AntiSpamForWordPressPlugin::$option_hidefooter => 'asfw_widget_settings_section',
		);

		return isset( $section_map[ $option ] ) ? $section_map[ $option ] : 'asfw_widget_settings_section';
	}
}
