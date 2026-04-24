<?php
/**
 * Analytics operations for the admin UI.
 *
 * @package anti-spam-for-wordpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'asfw_rest_operation_analytics_read' ) ) {
	/**
	 * Returns aggregated analytics from the event store.
	 *
	 * @param WP_REST_Request     $request Request.
	 * @param array<string,mixed> $operation Operation metadata.
	 * @return array<string,mixed>|WP_Error
	 */
	function asfw_rest_operation_analytics_read( $request, array $operation ) {
		unset( $operation );

		$store = asfw_rest_admin_store();
		if ( ! $store instanceof ASFW_Event_Store ) {
			return new WP_Error(
				'asfw_analytics_unavailable',
				__( 'Event store is unavailable.', 'anti-spam-for-wordpress' ),
				array( 'status' => 503 )
			);
		}

		$filters     = asfw_rest_events_filters_from_request( $request );
		$total_count = $store->count_events( $filters );
		$sample_cap  = 5000;
		$sample_size = min( $sample_cap, max( 0, intval( $total_count, 10 ) ) );

		$events = $store->fetch_events(
			array_merge(
				$filters,
				array(
					'limit'  => $sample_size,
					'offset' => 0,
				)
			)
		);

		$daily_challenges = array();
		$daily_verify     = array();
		$context_counts   = array();
		$rate_limit_total = 0;
		$feature_hits     = array();

		foreach ( $events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}

			$event_type = isset( $event['event_type'] ) ? $store->canonicalize_event_type( $event['event_type'] ) : '';
			$day        = isset( $event['created_at'] ) ? substr( (string) $event['created_at'], 0, 10 ) : '';
			$context    = isset( $event['context'] ) ? (string) $event['context'] : '';
			$feature    = isset( $event['feature'] ) && '' !== (string) $event['feature'] ? (string) $event['feature'] : 'core';

			if ( '' !== $context ) {
				$context_counts[ $context ] = isset( $context_counts[ $context ] ) ? $context_counts[ $context ] + 1 : 1;
			}

			if ( 'challenge_issued' === $event_type && '' !== $day ) {
				$daily_challenges[ $day ] = isset( $daily_challenges[ $day ] ) ? $daily_challenges[ $day ] + 1 : 1;
			}

			if ( in_array( $event_type, array( 'verify_passed', 'verify_failed' ), true ) && '' !== $day ) {
				if ( ! isset( $daily_verify[ $day ] ) ) {
					$daily_verify[ $day ] = array(
						'pass' => 0,
						'fail' => 0,
					);
				}

				$key = 'verify_passed' === $event_type ? 'pass' : 'fail';
				++$daily_verify[ $day ][ $key ];
			}

			if ( 'rate_limited' === $event_type ) {
				++$rate_limit_total;
			}

			if ( in_array( $event_type, array( 'disposable_email_hit', 'content_heuristic_hit', 'feature_runtime_disabled', 'bunny_sync_success', 'bunny_sync_failed', 'bunny_dry_run' ), true ) ) {
				$feature_hits[ $feature ] = isset( $feature_hits[ $feature ] ) ? $feature_hits[ $feature ] + 1 : 1;
			}
		}

		ksort( $daily_challenges );
		ksort( $daily_verify );
		arsort( $context_counts );
		arsort( $feature_hits );

		$daily_verify_rows = array();
		foreach ( $daily_verify as $day => $totals ) {
			$daily_verify_rows[] = array(
				'day'  => (string) $day,
				'pass' => intval( $totals['pass'], 10 ),
				'fail' => intval( $totals['fail'], 10 ),
			);
		}

		$context_rows = array();
		foreach ( array_slice( $context_counts, 0, 10, true ) as $label => $count ) {
			$context_rows[] = array(
				'label' => (string) $label,
				'count' => intval( $count, 10 ),
			);
		}

		$feature_rows = array();
		foreach ( $feature_hits as $label => $count ) {
			$feature_rows[] = array(
				'label' => (string) $label,
				'count' => intval( $count, 10 ),
			);
		}

		$challenge_rows = array();
		foreach ( $daily_challenges as $day => $count ) {
			$challenge_rows[] = array(
				'day'   => (string) $day,
				'count' => intval( $count, 10 ),
			);
		}

		return array(
			'filters'          => $filters,
			'logging_enabled'  => ASFW_Feature_Registry::is_enabled( 'event_logging' ),
			'sample'           => array(
				'total_events'    => intval( $total_count, 10 ),
				'analyzed_events' => count( $events ),
				'truncated'       => intval( $total_count, 10 ) > $sample_cap,
				'sample_cap'      => $sample_cap,
			),
			'cards'            => array(
				'rate_limit_total' => intval( $rate_limit_total, 10 ),
			),
			'daily_challenges' => $challenge_rows,
			'daily_verify'     => $daily_verify_rows,
			'top_contexts'     => $context_rows,
			'feature_hits'     => $feature_rows,
		);
	}
}

return array(
	array(
		'id'              => 'analytics.read',
		'route'           => '/admin/analytics',
		'methods'         => 'GET',
		'callback'        => 'asfw_rest_operation_analytics_read',
		'visibility'      => 'admin',
		'capability'      => 'manage_options',
		'required_scopes' => array( 'analytics.read' ),
	),
);
