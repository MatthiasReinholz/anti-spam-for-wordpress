<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'anti-spam-for-wordpress/v1',
			'challenge',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'asfw_generate_challenge_endpoint',
				'permission_callback' => '__return_true',
				'args'                => array(
					'context' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}
);

function asfw_generate_challenge_endpoint( WP_REST_Request $request ) {
	$context   = $request->get_param( 'context' );
	$challenge = AntiSpamForWordPressPlugin::$instance->generate_challenge( null, null, null, $context );
	if ( $challenge instanceof WP_Error ) {
		return $challenge;
	}

	$response = new WP_REST_Response( $challenge );
	$response->set_headers( array( 'Cache-Control' => 'no-cache, no-store, max-age=0' ) );

	return $response;
}
