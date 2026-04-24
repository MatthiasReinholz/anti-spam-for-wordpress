<?php
/**
 * Events operations for the admin UI.
 *
 * @package anti-spam-for-wordpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'asfw_rest_admin_store' ) ) {
	/**
	 * Returns the event store instance.
	 *
	 * @return ASFW_Event_Store|null
	 */
	function asfw_rest_admin_store() {
		if ( class_exists( 'ASFW_Control_Plane', false ) ) {
			$store = ASFW_Control_Plane::store();
			if ( $store instanceof ASFW_Event_Store ) {
				return $store;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'asfw_rest_sanitize_date_filter' ) ) {
	/**
	 * Sanitizes date filter values.
	 *
	 * @param mixed $value Raw date value.
	 * @return string
	 */
	function asfw_rest_sanitize_date_filter( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d', $timestamp );
	}
}

if ( ! function_exists( 'asfw_rest_events_filters_from_request' ) ) {
	/**
	 * Normalizes event filters from a REST request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,string>
	 */
	function asfw_rest_events_filters_from_request( WP_REST_Request $request ) {
		return array(
			'date_from' => asfw_rest_sanitize_date_filter( $request->get_param( 'date_from' ) ),
			'date_to'   => asfw_rest_sanitize_date_filter( $request->get_param( 'date_to' ) ),
			'context'   => sanitize_text_field( (string) $request->get_param( 'context' ) ),
			'type'      => sanitize_key( (string) $request->get_param( 'type' ) ),
			'feature'   => sanitize_key( (string) $request->get_param( 'feature' ) ),
			'decision'  => sanitize_key( (string) $request->get_param( 'decision' ) ),
		);
	}
}

if ( ! function_exists( 'asfw_rest_operation_events_list' ) ) {
	/**
	 * Returns paginated events for the admin tab.
	 *
	 * @param WP_REST_Request     $request Request.
	 * @param array<string,mixed> $operation Operation metadata.
	 * @return array<string,mixed>|WP_Error
	 */
	function asfw_rest_operation_events_list( $request, array $operation ) {
		unset( $operation );

		$store = asfw_rest_admin_store();
		if ( ! $store instanceof ASFW_Event_Store ) {
			return new WP_Error(
				'asfw_events_unavailable',
				__( 'Event store is unavailable.', 'anti-spam-for-wordpress' ),
				array( 'status' => 503 )
			);
		}

		$page     = max( 1, intval( $request->get_param( 'page_number' ), 10 ) );
		$per_page = intval( $request->get_param( 'per_page' ), 10 );
		if ( $per_page <= 0 ) {
			$per_page = 50;
		}
		$per_page = max( 1, min( 200, $per_page ) );

		$filters = asfw_rest_events_filters_from_request( $request );
		$offset  = ( $page - 1 ) * $per_page;

		$query_args = array_merge(
			$filters,
			array(
				'limit'  => $per_page,
				'offset' => $offset,
			)
		);

		$total = $store->count_events( $filters );
		$rows  = $store->fetch_events( $query_args );

		$items = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$event_type = isset( $row['event_type'] ) ? $store->canonicalize_event_type( $row['event_type'] ) : '';
			$items[]    = array(
				'id'         => isset( $row['id'] ) ? intval( $row['id'], 10 ) : 0,
				'created_at' => isset( $row['created_at'] ) ? (string) $row['created_at'] : ( isset( $row['created_at_gmt'] ) ? (string) $row['created_at_gmt'] : '' ),
				'event_type' => $event_type,
				'decision'   => isset( $row['decision'] ) ? (string) $row['decision'] : ( isset( $row['event_status'] ) ? (string) $row['event_status'] : '' ),
				'context'    => isset( $row['context'] ) ? (string) $row['context'] : ( isset( $row['event_context'] ) ? (string) $row['event_context'] : '' ),
				'feature'    => isset( $row['feature'] ) ? (string) $row['feature'] : ( isset( $row['module_name'] ) ? (string) $row['module_name'] : '' ),
				'details'    => isset( $row['details'] ) ? (string) $row['details'] : '',
			);
		}

		$types = array();
		foreach ( $store->get_type_counts() as $event_type => $count ) {
			$types[] = array(
				'value' => (string) $event_type,
				'label' => sprintf( '%s (%d)', (string) $event_type, intval( $count, 10 ) ),
			);
		}

		$features = array();
		foreach ( $store->get_module_counts() as $feature => $count ) {
			$features[] = array(
				'value' => (string) $feature,
				'label' => sprintf( '%s (%d)', (string) $feature, intval( $count, 10 ) ),
			);
		}

		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		if ( $page > $total_pages ) {
			$page = $total_pages;
		}

		$retention_days = $store->get_retention_days();
		$last_run       = (string) get_option( ASFW_Maintenance::OPTION_LAST_RUN, '' );

		return array(
			'items'                    => $items,
			'pagination'               => array(
				'page'        => $page,
				'per_page'    => $per_page,
				'total'       => intval( $total, 10 ),
				'total_pages' => $total_pages,
			),
			'filters'                  => $filters,
			'filter_options'           => array(
				'types'    => $types,
				'features' => $features,
			),
			'logging_enabled'          => ASFW_Feature_Registry::is_enabled( 'event_logging' ),
			'retention_days'           => intval( $retention_days, 10 ),
			'last_maintenance_run_utc' => $last_run,
		);
	}
}

return array(
	array(
		'id'              => 'events.list',
		'route'           => '/admin/events',
		'methods'         => 'GET',
		'callback'        => 'asfw_rest_operation_events_list',
		'visibility'      => 'admin',
		'capability'      => 'manage_options',
		'required_scopes' => array( 'events.read' ),
	),
);
