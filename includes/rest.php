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

				register_rest_route(
					'anti-spam-for-wordpress/v1',
					'submit-delay-token',
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => 'asfw_generate_submit_delay_token_endpoint',
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
	$origin_classification = asfw_classify_challenge_request_origin( $request );
	if ( 'cross_site' === $origin_classification ) {
		return new WP_Error(
			'asfw_cross_site_challenge_forbidden',
			__( 'Cross-site challenge requests are not allowed.', 'anti-spam-for-wordpress' ),
			array( 'status' => 403 )
		);
	}

	$context            = $request->get_param( 'context' );
	$count_against_rate = true;
	$plugin             = asfw_plugin_instance();
	if ( ! $plugin instanceof AntiSpamForWordPressPlugin ) {
		return new WP_Error(
			'asfw_unavailable',
			__( 'Anti-spam service is unavailable.', 'anti-spam-for-wordpress' ),
			array( 'status' => 503 )
		);
	}

	$challenge = $plugin->generate_challenge( null, null, null, $context, $count_against_rate );
	if ( $challenge instanceof WP_Error ) {
		return $challenge;
	}

	$response = new WP_REST_Response( $challenge );
	$response->set_headers( array( 'Cache-Control' => 'no-cache, no-store, max-age=0' ) );

	return $response;
}

function asfw_generate_submit_delay_token_endpoint( WP_REST_Request $request ) {
	$origin_classification = asfw_classify_challenge_request_origin( $request );
	if ( 'same_site' !== $origin_classification ) {
		return new WP_Error(
			'asfw_cross_site_submit_delay_forbidden',
			__( 'Submit-delay requests must be same-site.', 'anti-spam-for-wordpress' ),
			array( 'status' => 403 )
		);
	}

	$plugin = asfw_plugin_instance();
	if ( ! $plugin instanceof AntiSpamForWordPressPlugin ) {
		return new WP_Error(
			'asfw_unavailable',
			__( 'Anti-spam service is unavailable.', 'anti-spam-for-wordpress' ),
			array( 'status' => 503 )
		);
	}

	$context = ASFW_Feature_Registry::normalize_context( $request->get_param( 'context' ) );
	if ( ! asfw_is_context_guard_supported( $context ) ) {
		return new WP_Error(
			'asfw_submit_delay_context_unsupported',
			__( 'Submit-delay is not available for this context.', 'anti-spam-for-wordpress' ),
			array( 'status' => 404 )
		);
	}
	if ( ! ASFW_Feature_Registry::is_enabled( 'submit_delay', $context ) ) {
		return new WP_Error(
			'asfw_submit_delay_inactive',
			__( 'Submit-delay is not enabled for this context.', 'anti-spam-for-wordpress' ),
			array( 'status' => 403 )
		);
	}

	$rate_limit_context = 'submit-delay-token:' . $context;
	$rate_limited       = $plugin->is_rate_limited( 'challenge', $rate_limit_context );
	if ( $rate_limited instanceof WP_Error ) {
		return $rate_limited;
	}
	$delay_ms = intval( $plugin->get_feature_submit_delay_ms(), 10 );
	if ( ! in_array( (string) $delay_ms, array( '1000', '2500', '5000' ), true ) ) {
		$delay_ms = 2500;
	}

	$token = $plugin->issue_submit_delay_token( $context, $delay_ms );
	$plugin->increment_rate_limit( 'challenge', $rate_limit_context );
	$token['delay_ms'] = $delay_ms;

	$response = new WP_REST_Response( $token );
	$response->set_headers( array( 'Cache-Control' => 'no-cache, no-store, max-age=0' ) );

	return $response;
}

function asfw_classify_challenge_request_origin( WP_REST_Request $request ) {
	$sec_fetch_site = strtolower( trim( (string) asfw_rest_request_header( $request, 'sec-fetch-site' ) ) );
	if ( '' !== $sec_fetch_site ) {
		if ( in_array( $sec_fetch_site, array( 'same-origin', 'same-site' ), true ) ) {
			return 'same_site';
		}

		if ( 'cross-site' === $sec_fetch_site ) {
			return 'cross_site';
		}

		return 'headerless';
	}

	$origin = trim( (string) asfw_rest_request_header( $request, 'origin' ) );
	if ( '' !== $origin ) {
		return asfw_compare_request_origin_to_site( $origin );
	}

	$referer = trim( (string) asfw_rest_request_header( $request, 'referer' ) );
	if ( '' !== $referer ) {
		return asfw_compare_request_origin_to_site( $referer );
	}

	return 'headerless';
}

function asfw_compare_request_origin_to_site( $origin_like_value ) {
	$site_host   = wp_parse_url( home_url(), PHP_URL_HOST );
	$origin_host = wp_parse_url( $origin_like_value, PHP_URL_HOST );

	if ( ! is_string( $site_host ) || '' === $site_host || ! is_string( $origin_host ) || '' === $origin_host ) {
		return 'headerless';
	}

	if ( asfw_hosts_same_site( $origin_host, $site_host ) ) {
		return 'same_site';
	}

	return 'cross_site';
}

function asfw_hosts_same_site( $candidate_host, $site_host ) {
	$candidate_host = strtolower( trim( (string) $candidate_host ) );
	$site_host      = strtolower( trim( (string) $site_host ) );

	if ( '' === $candidate_host || '' === $site_host ) {
		return false;
	}

	if ( $candidate_host === $site_host ) {
		return true;
	}

	return str_ends_with( $candidate_host, '.' . $site_host ) || str_ends_with( $site_host, '.' . $candidate_host );
}

function asfw_rest_request_header( WP_REST_Request $request, $name ) {
	$name = strtolower( trim( (string) $name ) );
	if ( '' === $name ) {
		return '';
	}

	if ( method_exists( $request, 'get_header' ) ) {
		return (string) $request->get_header( $name );
	}

	if ( method_exists( $request, 'get_headers' ) ) {
		$headers = $request->get_headers();
		if ( is_array( $headers ) ) {
			foreach ( $headers as $header_name => $value ) {
				if ( strtolower( (string) $header_name ) !== $name ) {
					continue;
				}
				if ( is_array( $value ) ) {
					return (string) reset( $value );
				}

				return (string) $value;
			}
		}
	}

	return '';
}
