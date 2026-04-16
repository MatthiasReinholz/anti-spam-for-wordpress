<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wpdiscuz_button_actions',
	function () {
		$plugin = asfw_plugin_instance();
		$wpdiscuz_mode = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_wpdiscuz() : '';
		$wordpress_mode = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_wordpress_comments() : '';
		$wpdiscuz_guard_enabled = ASFW_Feature_Registry::is_enabled( 'math_challenge', 'wpdiscuz:comments' )
			|| ASFW_Feature_Registry::is_enabled( 'submit_delay', 'wpdiscuz:comments' );
		$context = ( ! empty( $wpdiscuz_mode ) || $wpdiscuz_guard_enabled ) ? 'wpdiscuz:comments' : 'wordpress:comments';
		$mode = ! empty( $wpdiscuz_mode ) ? $wpdiscuz_mode : $wordpress_mode;
		$guards = asfw_render_context_guards( $context );
		$context_fields = '';
		if ( $plugin instanceof AntiSpamForWordPressPlugin ) {
			$context_fields .= '<input type="hidden" name="asfw_context" value="' . esc_attr( $context ) . '">';
			$context_fields .= '<input type="hidden" name="asfw_context_sig" value="' . esc_attr( $plugin->sign_widget_context( $context, 'asfw' ) ) . '">';
		}
		if ( ! empty( $mode ) ) {
			$output  = '<div class="asfw-widget-wrap-wpdiscuz">';
			$output .= $plugin->render_widget( $mode, false, null, 'asfw', $context );
			$output .= $context_fields;
			$output .= $guards;
			$output .= '</div>';
			echo wp_kses( $output, AntiSpamForWordPressPlugin::$html_allowed_tags );
		} elseif ( '' !== $guards ) {
			echo $context_fields . $guards; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Guard markup is sanitized in helper.
		}
	},
	10,
	0
);
