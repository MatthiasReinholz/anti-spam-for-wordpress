<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function asfw_get_settings_summary_rows() {
	$rows = array();

	foreach ( ASFW_Feature_Registry::definitions() as $feature_id => $definition ) {
		if ( ! is_array( $definition ) || empty( $definition['show_in_settings'] ) ) {
			continue;
		}

		$label        = isset( $definition['label'] ) ? (string) $definition['label'] : (string) $feature_id;
		$mode         = ASFW_Feature_Registry::mode( (string) $feature_id );
		$enabled      = ASFW_Feature_Registry::is_enabled( (string) $feature_id ) ? __( 'Enabled', 'anti-spam-for-wordpress' ) : __( 'Disabled', 'anti-spam-for-wordpress' );
		$background   = ASFW_Feature_Registry::background_enabled( (string) $feature_id ) ? __( 'Active', 'anti-spam-for-wordpress' ) : __( 'Inactive', 'anti-spam-for-wordpress' );
		$experimental = ( 'content_heuristics' === $feature_id ) ? __( 'Yes', 'anti-spam-for-wordpress' ) : __( 'No', 'anti-spam-for-wordpress' );

		$rows[] = array(
			'label'        => $label,
			'enabled'      => $enabled,
			'mode'         => $mode,
			'background'   => $background,
			'experimental' => $experimental,
		);
	}

	return $rows;
}

function asfw_control_plane_section_callback() {
	?>
	<p><?php echo esc_html__( 'Tune observability, retention, emergency bypass, runtime mode, and optional policy checks after placements and core protection are configured.', 'anti-spam-for-wordpress' ); ?></p>
	<?php
}

function asfw_general_section_callback() {
	?>
	<p><?php echo esc_html__( 'Core proof-of-work protection runs locally in your WordPress installation. Rotate the secret only when you are ready to invalidate outstanding challenges.', 'anti-spam-for-wordpress' ); ?></p>
	<?php
}

function asfw_widget_section_callback() {
	?>
	<p><?php echo esc_html__( 'Customize the widget and review the shortcode available for custom form templates.', 'anti-spam-for-wordpress' ); ?></p>
	<?php
}

function asfw_security_section_callback() {
	?>
	<p><?php echo esc_html__( 'Harden verification with short-lived challenges, rate limits, and low-friction bot traps.', 'anti-spam-for-wordpress' ); ?></p>
	<?php
}

function asfw_bunny_section_callback() {
	?>
	<p><?php echo esc_html__( 'Optionally forward repeated abuse signals to a Bunny Shield custom access list. Keep the feature in log mode to stay observational, or switch to block mode for automatic remote updates.', 'anti-spam-for-wordpress' ); ?></p>
	<?php
}

function asfw_integrations_section_callback() {
	?>
	<p><?php echo esc_html__( 'Choose where the widget is injected. Core WordPress placements are listed first, followed by supported form and commerce integrations.', 'anti-spam-for-wordpress' ); ?></p>
	<?php
}

function asfw_settings_field_callback( array $args ) {
	$type        = isset( $args['type'] ) ? $args['type'] : 'text';
	$name        = $args['name'];
	$hint        = isset( $args['hint'] ) ? $args['hint'] : null;
	$description = isset( $args['description'] ) ? $args['description'] : null;
	$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
	$setting     = get_option( $name );
	$value       = isset( $setting ) && empty( $args['write_only'] ) ? esc_attr( $setting ) : '';

	if ( 'checkbox' === $type ) {
		$value = 1;
	}
	?>
	<input autocomplete="off" class="regular-text" type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" <?php checked( 1, $setting, 'checkbox' === $type ); ?>>
	<?php if ( ! empty( $description ) ) { ?>
		<label class="description" for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $description ); ?></label>
	<?php } ?>
	<?php if ( $hint ) { ?>
		<div class="asfw-field-hint"><?php echo esc_html( $hint ); ?></div>
	<?php } ?>
	<?php
}

function asfw_settings_select_callback( array $args ) {
	$name        = $args['name'];
	$hint        = isset( $args['hint'] ) ? $args['hint'] : null;
	$disabled    = ! empty( $args['disabled'] );
	$description = isset( $args['description'] ) ? $args['description'] : null;
	$options     = isset( $args['options'] ) ? $args['options'] : array();
	$setting     = get_option( $name );
	$value       = isset( $setting ) ? esc_attr( $setting ) : '';
	?>
	<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" <?php echo $disabled ? 'disabled' : ''; ?>>
		<?php foreach ( $options as $opt_key => $opt_value ) { ?>
			<option value="<?php echo esc_attr( $opt_key ); ?>" <?php selected( $value, $opt_key ); ?>><?php echo esc_html( $opt_value ); ?></option>
		<?php } ?>
	</select>
	<?php if ( ! empty( $description ) ) { ?>
		<label class="description" for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $description ); ?></label>
	<?php } ?>
	<?php if ( $hint ) { ?>
		<div class="asfw-field-hint"><?php echo esc_html( $hint ); ?></div>
	<?php } ?>
	<?php
}

function asfw_settings_textarea_callback( array $args ) {
	$name        = $args['name'];
	$hint        = isset( $args['hint'] ) ? $args['hint'] : null;
	$description = isset( $args['description'] ) ? $args['description'] : null;
	$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
	$setting     = get_option( $name, array() );
	$value       = is_array( $setting ) ? implode( "\n", $setting ) : (string) $setting;
	?>
	<textarea class="large-text code" rows="4" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_html( $value ); ?></textarea>
	<?php if ( ! empty( $description ) ) { ?>
		<label class="description" for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $description ); ?></label>
	<?php } ?>
	<?php if ( $hint ) { ?>
		<div class="asfw-field-hint"><?php echo esc_html( $hint ); ?></div>
	<?php } ?>
	<?php
}

function asfw_settings_privacy_target_callback( array $args ) {
	$name     = $args['name'];
	$hint     = isset( $args['hint'] ) ? $args['hint'] : null;
	$selected = trim( (string) get_option( $name, '' ) );
	$pages    = get_pages(
		array(
			'sort_column' => 'menu_order,post_title',
		)
	);
	?>
	<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>">
		<option value=""><?php echo esc_html__( 'No link', 'anti-spam-for-wordpress' ); ?></option>
		<option value="custom" <?php selected( $selected, 'custom' ); ?>><?php echo esc_html__( 'Custom URL', 'anti-spam-for-wordpress' ); ?></option>
		<?php foreach ( $pages as $page ) { ?>
			<option value="<?php echo esc_attr( (string) $page->ID ); ?>" <?php selected( $selected, (string) $page->ID ); ?>>
				<?php echo esc_html( $page->post_title ); ?>
			</option>
		<?php } ?>
	</select>
	<?php if ( $hint ) { ?>
		<div class="asfw-field-hint"><?php echo esc_html( $hint ); ?></div>
	<?php } ?>
	<?php
}
