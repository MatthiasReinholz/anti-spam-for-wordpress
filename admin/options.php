<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function asfw_options_page_html() {
	wp_enqueue_script(
		'asfw-admin-script',
		AntiSpamForWordPressPlugin::$admin_script_src,
		array(),
		asfw_asset_version( 'public/admin.js' ),
		true
	);
	wp_enqueue_style(
		'asfw-admin-styles',
		AntiSpamForWordPressPlugin::$admin_css_src,
		array(),
		asfw_asset_version( 'public/admin.css' ),
		'all'
	);
	?>
	<div class="asfw-head">
		<div class="asfw-logo" aria-hidden="true">AS</div>
		<div class="asfw-head-copy">
			<div class="asfw-title"><?php echo esc_html__( 'Anti Spam for WordPress', 'anti-spam-for-wordpress' ); ?></div>
			<div class="asfw-subtitle"><?php echo esc_html__( 'Self-hosted spam protection for WordPress forms.', 'anti-spam-for-wordpress' ); ?></div>
		</div>
	</div>

	<div class="wrap asfw-settings-page">
		<hr>
		<?php asfw_render_settings_summary_panel(); ?>

		<form action="options.php" method="post">
		<?php
		settings_errors();
		settings_fields( 'asfw_options' );
		do_settings_sections( 'asfw_admin' );
		submit_button();
		?>
		</form>

		<div class="asfw-page-meta">
			<p>
				<?php
				/* translators: 1: plugin version, 2: bundled widget version. */
				$version_summary = esc_html__(
					'Anti Spam for WordPress, plugin version %1$s, bundled widget version %2$s',
					'anti-spam-for-wordpress'
				);
				printf(
					esc_html( $version_summary ),
					esc_html( AntiSpamForWordPressPlugin::$version ),
					esc_html( AntiSpamForWordPressPlugin::$widget_version )
				);
				?>
			</p>
			<p>
				<a href="https://github.com/MatthiasReinholz/anti-spam-for-wordpress" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html__( 'View the source on GitHub', 'anti-spam-for-wordpress' ); ?>
				</a>
			</p>
		</div>
		</div>
		<?php
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

function asfw_render_settings_summary_panel() {
	$kill_switch = ASFW_Feature_Registry::kill_switch_active()
		? __( 'Active', 'anti-spam-for-wordpress' )
		: __( 'Inactive', 'anti-spam-for-wordpress' );
	$rows        = asfw_get_settings_summary_rows();
	?>
	<div class="asfw-summary-panel">
		<h2><?php echo esc_html__( 'Control plane summary', 'anti-spam-for-wordpress' ); ?></h2>
		<p class="asfw-summary-kill-switch">
			<strong><?php echo esc_html__( 'Kill switch:', 'anti-spam-for-wordpress' ); ?></strong>
			<?php echo esc_html( $kill_switch ); ?>
		</p>
		<table class="widefat striped asfw-summary-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Feature', 'anti-spam-for-wordpress' ); ?></th>
					<th><?php echo esc_html__( 'State', 'anti-spam-for-wordpress' ); ?></th>
					<th><?php echo esc_html__( 'Mode', 'anti-spam-for-wordpress' ); ?></th>
					<th><?php echo esc_html__( 'Background work', 'anti-spam-for-wordpress' ); ?></th>
					<th><?php echo esc_html__( 'Experimental', 'anti-spam-for-wordpress' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['label'] ); ?></td>
						<td><?php echo esc_html( $row['enabled'] ); ?></td>
						<td><code><?php echo esc_html( $row['mode'] ); ?></code></td>
						<td><?php echo esc_html( $row['background'] ); ?></td>
						<td><?php echo esc_html( $row['experimental'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function asfw_control_plane_section_callback() {
	?>
	<p><?php echo esc_html__( 'Emergency controls and first-wave control-plane features. Each feature exposes an enable flag, a runtime mode, and an optional context scope.', 'anti-spam-for-wordpress' ); ?></p>
	<?php
}

function asfw_general_section_callback() {
	?>
	<p><?php echo esc_html__( 'Core protection runs locally in your WordPress installation. External APIs are optional and only used when you enable integrations such as Bunny Shield.', 'anti-spam-for-wordpress' ); ?></p>
	<?php
}

function asfw_widget_section_callback() {
	?>
	<p><?php echo esc_html__( 'Customize the widget to fit your forms.', 'anti-spam-for-wordpress' ); ?></p>
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
	<p><?php echo esc_html__( 'Enable protection for these plugin integrations.', 'anti-spam-for-wordpress' ); ?></p>
	<?php
}

function asfw_wordpress_section_callback() {
	?>
	<p><?php echo esc_html__( 'Enable protection for core WordPress screens.', 'anti-spam-for-wordpress' ); ?></p>
	<?php
}

function asfw_context_catalog_section_callback() {
	$contexts = ASFW_Context_Catalog::get_contexts();
	?>
	<p><?php echo esc_html__( 'Normalized contexts are used to sign each widget instance and route verification to the right integration.', 'anti-spam-for-wordpress' ); ?></p>
	<p><?php echo esc_html__( 'When a widget name is supplied, it is appended to the base context after normalization.', 'anti-spam-for-wordpress' ); ?></p>
	<table class="widefat striped asfw-context-table">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Context', 'anti-spam-for-wordpress' ); ?></th>
				<th><?php echo esc_html__( 'Group', 'anti-spam-for-wordpress' ); ?></th>
				<th><?php echo esc_html__( 'Description', 'anti-spam-for-wordpress' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $contexts as $context => $entry ) { ?>
				<tr>
					<td class="asfw-context-key"><code><?php echo esc_html( $context ); ?></code></td>
					<td><?php echo esc_html( asfw_context_catalog_group_label( $entry['group'] ) ); ?></td>
					<td><?php echo esc_html( $entry['description'] ); ?></td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
	<?php
}

function asfw_context_catalog_group_label( $group ) {
	if ( 'core' === $group ) {
		return __( 'Core', 'anti-spam-for-wordpress' );
	}

	if ( 'WordPress' === $group ) {
		return __( 'WordPress', 'anti-spam-for-wordpress' );
	}

	return __( 'Integrations', 'anti-spam-for-wordpress' );
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
