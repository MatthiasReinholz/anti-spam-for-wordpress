<?php
/**
 * Settings operations for the admin UI.
 *
 * @package anti-spam-for-wordpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'asfw_rest_render_section_description' ) ) {
	/**
	 * Renders a section callback and returns plain text for UI descriptions.
	 *
	 * @param mixed $callback Section callback.
	 * @return string
	 */
	function asfw_rest_render_section_description( $callback ) {
		if ( ! is_callable( $callback ) ) {
			return '';
		}

		ob_start();
		call_user_func( $callback );
		$rendered = (string) ob_get_clean();

		$rendered = wp_strip_all_tags( $rendered );
		$rendered = preg_replace( '/\s+/', ' ', $rendered );

		return is_string( $rendered ) ? trim( $rendered ) : '';
	}
}

if ( ! function_exists( 'asfw_rest_normalize_field_type' ) ) {
	/**
	 * Resolves a UI type from a schema field.
	 *
	 * @param array<string,mixed> $field Field definition.
	 * @return string
	 */
	function asfw_rest_normalize_field_type( array $field ) {
		$callback = isset( $field['callback'] ) ? (string) $field['callback'] : '';
		$args     = isset( $field['args'] ) && is_array( $field['args'] ) ? $field['args'] : array();
		$type     = isset( $args['type'] ) ? (string) $args['type'] : 'text';

		if ( 'asfw_settings_select_callback' === $callback ) {
			return 'select';
		}

		if ( 'asfw_settings_textarea_callback' === $callback ) {
			return 'textarea';
		}

		if ( 'asfw_settings_privacy_target_callback' === $callback ) {
			return 'privacy_target';
		}

		if ( 'checkbox' === $type ) {
			return 'checkbox';
		}

		return $type;
	}
}

if ( ! function_exists( 'asfw_rest_privacy_target_options' ) ) {
	/**
	 * Returns selectable privacy target options.
	 *
	 * @return array<int,array<string,string>>
	 */
	function asfw_rest_privacy_target_options() {
		$options = array(
			array(
				'value' => '',
				'label' => __( 'No link', 'anti-spam-for-wordpress' ),
			),
			array(
				'value' => 'custom',
				'label' => __( 'Custom URL', 'anti-spam-for-wordpress' ),
			),
		);

		$pages = get_pages(
			array(
				'sort_column' => 'menu_order,post_title',
			)
		);

		foreach ( $pages as $page ) {
			if ( ! is_object( $page ) || ! isset( $page->ID ) ) {
				continue;
			}

			$options[] = array(
				'value' => (string) $page->ID,
				'label' => isset( $page->post_title ) ? (string) $page->post_title : (string) $page->ID,
			);
		}

		return $options;
	}
}

if ( ! function_exists( 'asfw_rest_normalize_field_value' ) ) {
	/**
	 * Normalizes option values for UI controls.
	 *
	 * @param array<string,mixed> $field Schema field.
	 * @param mixed               $value Raw option value.
	 * @return mixed
	 */
	function asfw_rest_normalize_field_value( array $field, $value ) {
		$type = asfw_rest_normalize_field_type( $field );

		if ( 'checkbox' === $type ) {
			return ! empty( $value );
		}

		if ( 'textarea' === $type && is_array( $value ) ) {
			return implode( "\n", array_map( 'strval', $value ) );
		}

		if ( is_array( $value ) ) {
			return implode( "\n", array_map( 'strval', $value ) );
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		if ( null === $value ) {
			return '';
		}

		return (string) $value;
	}
}

if ( ! function_exists( 'asfw_rest_field_options' ) ) {
	/**
	 * Returns normalized options for select-like fields.
	 *
	 * @param array<string,mixed> $field Schema field.
	 * @return array<int,array<string,string>>
	 */
	function asfw_rest_field_options( array $field ) {
		$type = asfw_rest_normalize_field_type( $field );
		$args = isset( $field['args'] ) && is_array( $field['args'] ) ? $field['args'] : array();

		if ( 'privacy_target' === $type ) {
			return asfw_rest_privacy_target_options();
		}

		if ( ! isset( $args['options'] ) || ! is_array( $args['options'] ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $args['options'] as $value => $label ) {
			$normalized[] = array(
				'value' => (string) $value,
				'label' => (string) $label,
			);
		}

		return $normalized;
	}
}

if ( ! function_exists( 'asfw_rest_registered_option_allowlist' ) ) {
	/**
	 * Returns settings option names eligible for admin UI updates.
	 *
	 * @return array<int,string>
	 */
	function asfw_rest_registered_option_allowlist() {
		$allowlist = array();

		foreach ( ASFW_Settings_Definitions::get_registered_settings() as $setting ) {
			if ( ! is_array( $setting ) || empty( $setting['option'] ) ) {
				continue;
			}
			$allowlist[] = (string) $setting['option'];
		}

		$external = apply_filters( 'asfw_settings_external_registered_settings', array() );
		if ( is_array( $external ) ) {
			foreach ( $external as $definition ) {
				if ( ! is_array( $definition ) || empty( $definition['option'] ) ) {
					continue;
				}
				$allowlist[] = (string) $definition['option'];
			}
		}

		$allowlist = array_values( array_unique( array_filter( $allowlist ) ) );
		sort( $allowlist );

		return $allowlist;
	}
}

if ( ! function_exists( 'asfw_rest_registered_sanitize_callbacks' ) ) {
	/**
	 * Returns sanitize callbacks keyed by option name.
	 *
	 * @return array<string,callable>
	 */
	function asfw_rest_registered_sanitize_callbacks() {
		$callbacks = array();

		foreach ( ASFW_Settings_Definitions::get_registered_settings() as $setting ) {
			if (
				! is_array( $setting ) ||
				empty( $setting['option'] ) ||
				empty( $setting['sanitize_callback'] ) ||
				! is_callable( $setting['sanitize_callback'] )
			) {
				continue;
			}

			$callbacks[ (string) $setting['option'] ] = $setting['sanitize_callback'];
		}

		$external = apply_filters( 'asfw_settings_external_registered_settings', array() );
		if ( is_array( $external ) ) {
			foreach ( $external as $definition ) {
				if (
					! is_array( $definition ) ||
					empty( $definition['option'] ) ||
					empty( $definition['sanitize_callback'] ) ||
					! is_callable( $definition['sanitize_callback'] )
				) {
					continue;
				}

				$callbacks[ (string) $definition['option'] ] = $definition['sanitize_callback'];
			}
		}

		return $callbacks;
	}
}

if ( ! function_exists( 'asfw_rest_sanitize_option_value' ) ) {
	/**
	 * Sanitizes an option value using WordPress sanitize callbacks when available.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Raw option value.
	 * @return mixed
	 */
	function asfw_rest_sanitize_option_value( $option, $value ) {
		if ( function_exists( 'sanitize_option' ) ) {
			return sanitize_option( $option, $value );
		}

		return $value;
	}
}

if ( ! function_exists( 'asfw_rest_build_settings_payload' ) ) {
	/**
	 * Builds the settings payload used by the admin app.
	 *
	 * @return array<string,mixed>
	 */
	function asfw_rest_build_settings_payload() {
		$sections          = ASFW_Settings_Schema::get_sections();
		$fields_by_section = ASFW_Settings_Schema::get_fields_by_section();
		$settings_sections = array();

		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) || empty( $section['id'] ) ) {
				continue;
			}

			$section_id = (string) $section['id'];
			$fields     = isset( $fields_by_section[ $section_id ] ) && is_array( $fields_by_section[ $section_id ] )
				? $fields_by_section[ $section_id ]
				: array();

			$normalized_fields = array();
			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) || empty( $field['option'] ) ) {
					continue;
				}

				$option_name  = (string) $field['option'];
				$current      = get_option( $option_name );
				$field_args   = isset( $field['args'] ) && is_array( $field['args'] ) ? $field['args'] : array();
				$normalized_fields[] = array(
					'id'          => isset( $field['id'] ) ? (string) $field['id'] : $option_name,
					'option'      => $option_name,
					'label'       => isset( $field['title'] ) ? (string) $field['title'] : $option_name,
					'type'        => asfw_rest_normalize_field_type( $field ),
					'value'       => asfw_rest_normalize_field_value( $field, $current ),
					'hint'        => isset( $field_args['hint'] ) ? (string) $field_args['hint'] : '',
					'description' => isset( $field_args['description'] ) ? (string) $field_args['description'] : '',
					'disabled'    => ! empty( $field_args['disabled'] ),
					'options'     => asfw_rest_field_options( $field ),
				);
			}

			$settings_sections[] = array(
				'id'          => $section_id,
				'title'       => isset( $section['title'] ) ? (string) $section['title'] : $section_id,
				'description' => isset( $section['callback'] ) ? asfw_rest_render_section_description( $section['callback'] ) : '',
				'fields'      => $normalized_fields,
			);
		}

		$summary_rows = array();
		if ( function_exists( 'asfw_get_settings_summary_rows' ) ) {
			$summary_rows = asfw_get_settings_summary_rows();
		}

		$contexts = ASFW_Context_Catalog::get_contexts();

		return array(
			'sections' => $settings_sections,
			'summary'  => array(
				'kill_switch' => ASFW_Feature_Registry::kill_switch_active() ? 'active' : 'inactive',
				'rows'        => array_values( is_array( $summary_rows ) ? $summary_rows : array() ),
			),
			'context_catalog' => array_values( array_map(
				static function ( $context, $entry ) {
					$group = isset( $entry['group'] ) ? (string) $entry['group'] : 'integrations';
					$description = isset( $entry['description'] ) ? (string) $entry['description'] : '';
					return array(
						'context'     => (string) $context,
						'group'       => $group,
						'group_label' => function_exists( 'asfw_context_catalog_group_label' ) ? (string) asfw_context_catalog_group_label( $group ) : $group,
						'description' => $description,
					);
				},
				array_keys( $contexts ),
				array_values( $contexts )
			) ),
		);
	}
}

if ( ! function_exists( 'asfw_rest_operation_settings_read' ) ) {
	/**
	 * Reads the settings UI payload.
	 *
	 * @param WP_REST_Request     $request Request.
	 * @param array<string,mixed> $operation Operation metadata.
	 * @return array<string,mixed>
	 */
	function asfw_rest_operation_settings_read( $request, array $operation ) {
		unset( $request, $operation );

		return asfw_rest_build_settings_payload();
	}
}

if ( ! function_exists( 'asfw_rest_operation_settings_update' ) ) {
	/**
	 * Updates registered settings options.
	 *
	 * @param WP_REST_Request     $request Request.
	 * @param array<string,mixed> $operation Operation metadata.
	 * @return array<string,mixed>
	 */
	function asfw_rest_operation_settings_update( $request, array $operation ) {
		unset( $operation );

		$payload = method_exists( $request, 'get_json_params' )
			? $request->get_json_params()
			: $request->get_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		$values = array();
		if ( isset( $payload['values'] ) && is_array( $payload['values'] ) ) {
			$values = $payload['values'];
		} elseif ( isset( $payload['data'] ) && is_array( $payload['data'] ) ) {
			$values = $payload['data'];
		}

		$allowlist = array_fill_keys( asfw_rest_registered_option_allowlist(), true );
		$callbacks = asfw_rest_registered_sanitize_callbacks();
		$updated   = array();

		foreach ( $values as $option => $raw_value ) {
			$option = (string) $option;
			if ( '' === $option || ! isset( $allowlist[ $option ] ) ) {
				continue;
			}

			if ( is_string( $raw_value ) ) {
				$raw_value = wp_unslash( $raw_value );
			}

			if ( isset( $callbacks[ $option ] ) && is_callable( $callbacks[ $option ] ) ) {
				$sanitized = call_user_func( $callbacks[ $option ], $raw_value );
			} else {
				$sanitized = asfw_rest_sanitize_option_value( $option, $raw_value );
			}

			update_option( $option, $sanitized );
			if ( class_exists( 'ASFW_Settings_Registrar', false ) && method_exists( 'ASFW_Settings_Registrar', 'sync_legacy_feature_options' ) ) {
				ASFW_Settings_Registrar::sync_legacy_feature_options( $option );
			}
			$updated[] = $option;
		}

		return array(
			'updated'  => $updated,
			'settings' => asfw_rest_build_settings_payload(),
		);
	}
}

return array(
	array(
		'id'              => 'settings.read',
		'route'           => '/admin/settings',
		'methods'         => 'GET',
		'callback'        => 'asfw_rest_operation_settings_read',
		'visibility'      => 'admin',
		'capability'      => 'manage_options',
		'required_scopes' => array( 'settings.read' ),
	),
	array(
		'id'              => 'settings.update',
		'route'           => '/admin/settings',
		'methods'         => 'POST',
		'callback'        => 'asfw_rest_operation_settings_update',
		'visibility'      => 'admin',
		'capability'      => 'manage_options',
		'required_scopes' => array( 'settings.update' ),
	),
);
