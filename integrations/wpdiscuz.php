<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Return the current provider policy, shared by rendering and enforcement. */
function asfw_wpdiscuz_comment_policy() {
	$plugin = asfw_plugin_instance();
	if ( ! $plugin instanceof AntiSpamForWordPressPlugin ) {
		return array(
			'context' => 'wordpress:comments',
			'mode'    => '',
		);
	}
	$mode       = $plugin->get_integration_wpdiscuz();
	$own_policy = '' !== $mode
		|| ASFW_Feature_Registry::is_enabled( 'math_challenge', 'wpdiscuz:comments' )
		|| ASFW_Feature_Registry::is_enabled( 'submit_delay', 'wpdiscuz:comments' );
	return array(
		'context' => $own_policy ? 'wpdiscuz:comments' : 'wordpress:comments',
		'mode'    => '' !== $mode ? $mode : $plugin->get_integration_wordpress_comments(),
	);
}

/** Shared markup for main and inline feedback forms. */
function asfw_wpdiscuz_comment_markup() {
	$plugin = asfw_plugin_instance();
	if ( ! $plugin instanceof AntiSpamForWordPressPlugin || $plugin->is_kill_switch_enabled() ) {
		return '';
	}
	$policy  = asfw_wpdiscuz_comment_policy();
	$context = $policy['context'];
	$guards  = asfw_render_context_guards( $context );
	if ( '' === $policy['mode'] && '' === $guards ) {
		return '';
	}
	$output = '<div class="asfw-widget-wrap-wpdiscuz">';
	if ( '' !== $policy['mode'] ) {
		$output .= $plugin->render_widget( $policy['mode'], false, null, 'asfw', $context );
	}
	$output .= '<input type="hidden" name="asfw_context" value="' . esc_attr( $context ) . '">';
	$output .= '<input type="hidden" name="asfw_context_sig" value="' . esc_attr( $plugin->sign_widget_context( $context, 'asfw' ) ) . '">';
	return wp_kses( $output . $guards . '</div>', AntiSpamForWordPressPlugin::$html_allowed_tags );
}

add_action(
	'wpdiscuz_button_actions',
	function () {
		echo asfw_wpdiscuz_comment_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared renderer sanitizes the entire markup.
	},
	10,
	0
);

add_filter(
	'wpdiscuz_after_feedback_form_fields',
	function ( $markup ) {
		return $markup . asfw_wpdiscuz_comment_markup();
	},
	10,
	1
);
