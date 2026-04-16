<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASFW_Event_Store {

	const DB_VERSION = 3;

	const OPTION_DB_VERSION = 'asfw_events_db_version';

	const OPTION_RETENTION_DAYS = 'asfw_event_logging_retention_days';
	const OPTION_RETENTION_DAYS_LEGACY = 'asfw_event_retention_days';

	const CONTRACT_EVENT_TYPES = array(
		'challenge_issued',
		'verify_passed',
		'verify_failed',
		'rate_limited',
		'settings_changed',
		'disposable_email_hit',
		'content_heuristic_hit',
		'feature_runtime_disabled',
		'disposable_list_refreshed',
		'bunny_sync_success',
		'bunny_sync_failed',
		'bunny_dry_run',
		'guard_check',
	);

	const EVENT_TYPE_ALIASES = array(
		'verify_passed'         => array( 'verification_passed' ),
		'verify_failed'         => array( 'verification_failed' ),
		'content_heuristic_hit' => array( 'heuristic_flagged' ),
	);

	public function get_table_name() {
		global $wpdb;

		if ( is_object( $wpdb ) && isset( $wpdb->prefix ) ) {
			return $wpdb->prefix . 'asfw_events';
		}

		return 'wp_asfw_events';
	}

	public function get_retention_days() {
		$retention_raw = get_option( self::OPTION_RETENTION_DAYS, null );
		if ( null === $retention_raw || '' === (string) $retention_raw ) {
			$retention_raw = get_option( self::OPTION_RETENTION_DAYS_LEGACY, '30' );
		}

		$retention_days = intval( $retention_raw, 10 );
		if ( $retention_days <= 0 ) {
			$retention_days = 30;
		}

		return (int) apply_filters( 'asfw_event_retention_days', $retention_days );
	}

	public function get_schema_sql() {
		global $wpdb;

		$collate = '';
		if ( is_object( $wpdb ) && method_exists( $wpdb, 'get_charset_collate' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$table = $this->get_table_name();

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			event_type varchar(32) NOT NULL,
			context varchar(128) NOT NULL DEFAULT '',
			feature varchar(64) NOT NULL DEFAULT '',
			decision varchar(16) NOT NULL DEFAULT '',
			ip_hash char(64) NULL,
			email_hash char(64) NULL,
			details longtext NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY event_type (event_type),
			KEY context (context(64)),
			KEY feature (feature)
		) {$collate};";
	}

	public function maybe_upgrade_schema() {
		$current_version = intval( get_option( self::OPTION_DB_VERSION, '0' ), 10 );
		if ( $current_version >= self::DB_VERSION ) {
			return false;
		}

		if ( ! function_exists( 'dbDelta' ) && defined( 'ABSPATH' ) ) {
			$upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
			if ( file_exists( $upgrade_file ) ) {
				require_once $upgrade_file;
			}
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			return false;
		}

		dbDelta( $this->get_schema_sql() );
		update_option( self::OPTION_DB_VERSION, (string) self::DB_VERSION );

		return true;
	}

	public function install() {
		$updated = $this->maybe_upgrade_schema();
		if ( '' === (string) get_option( self::OPTION_RETENTION_DAYS, '' ) ) {
			$legacy_retention = trim( (string) get_option( self::OPTION_RETENTION_DAYS_LEGACY, '' ) );
			if ( '' !== $legacy_retention ) {
				update_option( self::OPTION_RETENTION_DAYS, $legacy_retention );
			}
		}

		if ( '' === (string) get_option( self::OPTION_RETENTION_DAYS, '' ) ) {
			update_option( self::OPTION_RETENTION_DAYS, '30' );
		}

		return $updated;
	}

	public function hash_value( $value, $purpose = 'generic' ) {
		return asfw_hash_value( $value, $purpose );
	}

	protected function current_time_gmt() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	protected function normalize_string( $value, $max_length = 191 ) {
		$value = trim( (string) $value );
		if ( strlen( $value ) > $max_length ) {
			$value = substr( $value, 0, $max_length );
		}

		return $value;
	}

	protected function normalize_nullable_string( $value, $max_length = 191 ) {
		$value = $this->normalize_string( $value, $max_length );

		return '' === $value ? null : $value;
	}

	protected function get_row_value( array $row, array $keys, $default = '' ) {
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $row ) && null !== $row[ $key ] && '' !== $row[ $key ] ) {
				return $row[ $key ];
			}
		}

		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $row ) ) {
				return $row[ $key ];
			}
		}

		return $default;
	}

	protected function normalize_event_type( $event_type ) {
		$event_type = sanitize_key( (string) $event_type );

		if ( '' === $event_type ) {
			return 'event';
		}

		foreach ( self::EVENT_TYPE_ALIASES as $canonical => $aliases ) {
			if ( $canonical === $event_type || in_array( $event_type, $aliases, true ) ) {
				return $canonical;
			}
		}

		return $event_type;
	}

	public function canonicalize_event_type( $event_type ) {
		return $this->normalize_event_type( $event_type );
	}

	protected function get_event_type_variants( $event_type ) {
		$event_type = sanitize_key( (string) $event_type );
		if ( '' === $event_type ) {
			return array();
		}

		$canonical = $this->normalize_event_type( $event_type );
		$variants  = array( $canonical );

		if ( isset( self::EVENT_TYPE_ALIASES[ $canonical ] ) ) {
			$variants = array_merge( $variants, self::EVENT_TYPE_ALIASES[ $canonical ] );
		}

		return array_values( array_unique( array_filter( $variants ) ) );
	}

	protected function normalize_event_row( array $row ) {
		$details = isset( $row['details'] ) ? $row['details'] : array();
		if ( is_array( $details ) || is_object( $details ) ) {
			$details = asfw_sanitize_event_details( $details );
			$details = wp_json_encode( $details );
		} elseif ( '' !== trim( (string) $details ) ) {
			$details = (string) $details;
		} else {
			$details = '{}';
		}

		$normalized = array(
			'created_at' => $this->normalize_string( $this->get_row_value( $row, array( 'created_at', 'created_at_gmt' ), $this->current_time_gmt() ), 19 ),
			'event_type' => $this->normalize_event_type( $this->get_row_value( $row, array( 'event_type', 'type' ), 'event' ) ),
			'context'    => $this->normalize_string( $this->get_row_value( $row, array( 'context', 'event_context' ), '' ), 128 ),
			'feature'    => $this->normalize_string( $this->get_row_value( $row, array( 'feature', 'module_name', 'module' ), '' ), 64 ),
			'decision'   => $this->normalize_string( $this->get_row_value( $row, array( 'decision', 'event_status', 'status' ), '' ), 16 ),
			'ip_hash'    => $this->normalize_nullable_string( $this->get_row_value( $row, array( 'ip_hash', 'actor_hash' ), '' ), 64 ),
			'email_hash'  => $this->normalize_nullable_string( $this->get_row_value( $row, array( 'email_hash' ), '' ), 64 ),
			'details'    => $this->normalize_string( $details, 65535 ),
		);

		return $normalized;
	}

	protected function normalize_event_row_for_response( array $row ) {
		$created_at = (string) $this->get_row_value( $row, array( 'created_at', 'created_at_gmt' ), '' );
		$context    = (string) $this->get_row_value( $row, array( 'context', 'event_context' ), '' );
		$feature    = (string) $this->get_row_value( $row, array( 'feature', 'module_name', 'module' ), '' );
		$decision   = (string) $this->get_row_value( $row, array( 'decision', 'event_status', 'status' ), '' );
		$ip_hash    = $this->get_row_value( $row, array( 'ip_hash', 'actor_hash' ), null );
		$email_hash = $this->get_row_value( $row, array( 'email_hash' ), null );

		$row['created_at']     = $created_at;
		$row['created_at_gmt']  = $created_at;
		$row['context']        = $context;
		$row['event_context']  = $context;
		$row['feature']        = $feature;
		$row['module_name']    = $feature;
		$row['decision']       = $decision;
		$row['event_status']   = $decision;
		$row['ip_hash']        = null === $ip_hash || '' === $ip_hash ? null : (string) $ip_hash;
		$row['actor_hash']     = null === $ip_hash || '' === $ip_hash ? '' : (string) $ip_hash;
		$row['email_hash']     = null === $email_hash || '' === $email_hash ? null : (string) $email_hash;
		if ( ! array_key_exists( 'subject_hash', $row ) ) {
			$row['subject_hash'] = '';
		}

		return $row;
	}

	protected function normalize_event_rows_for_response( array $rows ) {
		return array_map(
			array( $this, 'normalize_event_row_for_response' ),
			$rows
		);
	}

	protected function get_filter_value( array $args, array $keys ) {
		foreach ( $keys as $key ) {
			if ( isset( $args[ $key ] ) && '' !== trim( (string) $args[ $key ] ) ) {
				return (string) $args[ $key ];
			}
		}

		return '';
	}

	protected function get_wpdb() {
		global $wpdb;

		return $wpdb;
	}

	protected function build_where_clause( array $args, array &$params ) {
		$clauses = array( '1=1' );

		$type_variants = $this->get_event_type_variants( $args['type'] );
		if ( ! empty( $type_variants ) ) {
			if ( 1 === count( $type_variants ) ) {
				$clauses[] = 'event_type = %s';
				$params[]  = $type_variants[0];
			} else {
				$placeholders = implode( ', ', array_fill( 0, count( $type_variants ), '%s' ) );
				$clauses[]    = "event_type IN ({$placeholders})";
				$params       = array_merge( $params, $type_variants );
			}
		}

		$feature = $this->get_filter_value( $args, array( 'feature', 'module', 'module_name' ) );
		if ( '' !== $feature ) {
			$clauses[] = 'feature = %s';
			$params[]  = $feature;
		}

		$decision = $this->get_filter_value( $args, array( 'decision', 'status', 'event_status' ) );
		if ( '' !== $decision ) {
			$clauses[] = 'decision = %s';
			$params[]  = $decision;
		}

		$context = $this->get_filter_value( $args, array( 'context', 'event_context' ) );
		if ( '' !== $context ) {
			$clauses[] = 'context = %s';
			$params[]  = $context;
		}

		$date_from = $this->normalize_date_input( isset( $args['date_from'] ) ? $args['date_from'] : '', false );
		if ( '' !== $date_from ) {
			$clauses[] = 'created_at >= %s';
			$params[]  = $date_from;
		}

		$date_to = $this->normalize_date_input( isset( $args['date_to'] ) ? $args['date_to'] : '', true );
		if ( '' !== $date_to ) {
			$clauses[] = 'created_at <= %s';
			$params[]  = $date_to;
		}

		return implode( ' AND ', $clauses );
	}

	protected function normalize_date_input( $value, $end_of_day = false ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( $end_of_day ? 'Y-m-d 23:59:59' : 'Y-m-d 00:00:00', $timestamp );
	}

	protected function apply_date_range_to_rows( array $rows, array $args ) {
		$date_from = $this->normalize_date_input( isset( $args['date_from'] ) ? $args['date_from'] : '', false );
		$date_to   = $this->normalize_date_input( isset( $args['date_to'] ) ? $args['date_to'] : '', true );
		if ( '' === $date_from && '' === $date_to ) {
			return $rows;
		}

		return array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $date_from, $date_to ) {
					$created_at = isset( $row['created_at'] ) ? (string) $row['created_at'] : ( isset( $row['created_at_gmt'] ) ? (string) $row['created_at_gmt'] : '' );
					if ( '' === $created_at ) {
						return false;
					}

					if ( '' !== $date_from && $created_at < $date_from ) {
						return false;
					}

					if ( '' !== $date_to && $created_at > $date_to ) {
						return false;
					}

					return true;
				}
			)
		);
	}

	protected function merge_events_by_id( array $events ) {
		$merged = array();

		foreach ( $events as $event ) {
			$key = '';
			if ( isset( $event['id'] ) ) {
				$key = 'id:' . (string) $event['id'];
			} elseif ( is_array( $event ) ) {
				$key = 'row:' . md5( wp_json_encode( $event ) );
			}

			if ( '' === $key || isset( $merged[ $key ] ) ) {
				continue;
			}

			$merged[ $key ] = $event;
		}

		$events = array_values( $merged );
		usort(
			$events,
			static function ( $left, $right ) {
				$left_id  = isset( $left['id'] ) ? intval( $left['id'], 10 ) : 0;
				$right_id = isset( $right['id'] ) ? intval( $right['id'], 10 ) : 0;
				if ( $left_id !== $right_id ) {
					return $right_id <=> $left_id;
				}

				$left_time  = isset( $left['created_at'] ) ? (string) $left['created_at'] : ( isset( $left['created_at_gmt'] ) ? (string) $left['created_at_gmt'] : '' );
				$right_time = isset( $right['created_at'] ) ? (string) $right['created_at'] : ( isset( $right['created_at_gmt'] ) ? (string) $right['created_at_gmt'] : '' );
				return strcmp( $right_time, $left_time );
			}
		);

		return $events;
	}

	protected function normalize_type_counts( array $counts ) {
		$normalized = array();

		foreach ( $counts as $event_type => $count ) {
			$canonical = $this->normalize_event_type( $event_type );
			$normalized[ $canonical ] = ( isset( $normalized[ $canonical ] ) ? $normalized[ $canonical ] : 0 ) + intval( $count, 10 );
		}

		arsort( $normalized );

		return $normalized;
	}

	public function record_event( $event_type, array $row = array() ) {
		$wpdb = $this->get_wpdb();
		$this->install();

		$normalized = $this->normalize_event_row(
			array_merge(
				array( 'event_type' => $event_type ),
				$row
			)
		);

		if ( is_object( $wpdb ) && method_exists( $wpdb, 'asfw_insert_event' ) ) {
			return (bool) $wpdb->asfw_insert_event( $this->get_table_name(), $normalized );
		}

		if ( is_object( $wpdb ) && method_exists( $wpdb, 'insert' ) ) {
			return false !== $wpdb->insert( $this->get_table_name(), $normalized );
		}

		return false;
	}

	public function fetch_events( array $args = array() ) {
		$wpdb = $this->get_wpdb();
		$this->install();

		$args = array_merge(
			array(
				'limit'   => 50,
				'offset'  => 0,
				'type'    => '',
				'module'  => '',
				'status'  => '',
				'date_from' => '',
				'date_to' => '',
			),
			$args
		);

		$type_variants = $this->get_event_type_variants( $args['type'] );
		$has_date_filter = '' !== $this->normalize_date_input( $args['date_from'], false ) || '' !== $this->normalize_date_input( $args['date_to'], true );
		if ( is_object( $wpdb ) && method_exists( $wpdb, 'asfw_fetch_events' ) && count( $type_variants ) <= 1 ) {
			$query_args = $args;
			if ( $has_date_filter ) {
				$query_args['limit']  = PHP_INT_MAX;
				$query_args['offset'] = 0;
			}

			$rows = (array) $wpdb->asfw_fetch_events( $this->get_table_name(), $query_args );
			$rows = $this->apply_date_range_to_rows( $rows, $args );
			if ( $has_date_filter ) {
				$offset = max( 0, intval( $args['offset'], 10 ) );
				$limit  = max( 0, intval( $args['limit'], 10 ) );
				$rows   = array_slice( $rows, $offset, $limit );
			}

			return $this->normalize_event_rows_for_response( $rows );
		}

		if ( is_object( $wpdb ) && method_exists( $wpdb, 'asfw_fetch_events' ) && count( $type_variants ) > 1 ) {
			$merged = array();

			foreach ( $type_variants as $variant ) {
				$variant_args           = $args;
				$variant_args['type']   = $variant;
				$variant_args['limit']  = PHP_INT_MAX;
				$variant_args['offset'] = 0;
				$merged                 = array_merge( $merged, $wpdb->asfw_fetch_events( $this->get_table_name(), $variant_args ) );
			}

			$merged = $this->merge_events_by_id( $merged );
			$merged = $this->apply_date_range_to_rows( $merged, $args );

			return $this->normalize_event_rows_for_response( array_slice( $merged, max( 0, intval( $args['offset'], 10 ) ), max( 0, intval( $args['limit'], 10 ) ) ) );
		}

		if ( is_object( $wpdb ) && method_exists( $wpdb, 'get_results' ) && method_exists( $wpdb, 'prepare' ) ) {
			$params = array();
			$where  = $this->build_where_clause( $args, $params );
			$limit  = max( 0, intval( $args['limit'], 10 ) );
			$offset = max( 0, intval( $args['offset'], 10 ) );
			$params[] = $limit;
			$params[] = $offset;

			$query = "SELECT id, created_at, event_type, context, feature, decision, ip_hash, email_hash, details, created_at AS created_at_gmt, context AS event_context, feature AS module_name, decision AS event_status, ip_hash AS actor_hash, '' AS subject_hash FROM {$this->get_table_name()} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
			$sql   = $wpdb->prepare( $query, $params );
			$rows  = $wpdb->get_results( $sql, ARRAY_A );

			return $this->normalize_event_rows_for_response( is_array( $rows ) ? $rows : array() );
		}

		return array();
	}

	public function count_events( array $args = array() ) {
		$wpdb = $this->get_wpdb();
		$this->install();

		$args = array_merge(
			array(
				'type'     => '',
				'feature'  => '',
				'module'   => '',
				'status'   => '',
				'decision' => '',
				'context'  => '',
				'date_from' => '',
				'date_to' => '',
			),
			$args
		);

		$has_date_filter = '' !== $this->normalize_date_input( $args['date_from'], false ) || '' !== $this->normalize_date_input( $args['date_to'], true );
		if ( $has_date_filter ) {
			return count(
				$this->fetch_events(
					array_merge(
						$args,
						array(
							'limit'  => PHP_INT_MAX,
							'offset' => 0,
						)
					)
				)
			);
		}

		$type_variants = $this->get_event_type_variants( $args['type'] );
		if ( is_object( $wpdb ) && method_exists( $wpdb, 'asfw_count_events' ) && count( $type_variants ) <= 1 ) {
			return (int) $wpdb->asfw_count_events( $this->get_table_name(), $args );
		}

		if ( is_object( $wpdb ) && method_exists( $wpdb, 'asfw_fetch_events' ) && count( $type_variants ) > 1 ) {
			$fetched = $this->fetch_events(
				array_merge(
					$args,
					array(
						'limit'  => PHP_INT_MAX,
						'offset' => 0,
					)
				)
			);

			return count( $fetched );
		}

		if ( is_object( $wpdb ) && method_exists( $wpdb, 'get_var' ) && method_exists( $wpdb, 'prepare' ) ) {
			$params = array();
			$where  = $this->build_where_clause( $args, $params );
			$query  = "SELECT COUNT(*) FROM {$this->get_table_name()} WHERE {$where}";
			$sql    = $wpdb->prepare( $query, $params );
			return (int) $wpdb->get_var( $sql );
		}

		return 0;
	}

	public function get_type_counts() {
		$wpdb = $this->get_wpdb();
		$this->install();

		if ( is_object( $wpdb ) && method_exists( $wpdb, 'asfw_type_counts' ) ) {
			return $this->normalize_type_counts( $wpdb->asfw_type_counts( $this->get_table_name() ) );
		}

		if ( is_object( $wpdb ) && method_exists( $wpdb, 'get_results' ) ) {
			$query = "SELECT event_type, COUNT(*) AS total FROM {$this->get_table_name()} GROUP BY event_type ORDER BY total DESC";
			$rows  = $wpdb->get_results( $query, ARRAY_A );
			$counts = array();
			foreach ( (array) $rows as $row ) {
				if ( isset( $row['event_type'] ) ) {
					$counts[ $row['event_type'] ] = isset( $row['total'] ) ? intval( $row['total'], 10 ) : 0;
				}
			}

			return $this->normalize_type_counts( $counts );
		}

		return array();
	}

	public function get_module_counts() {
		$wpdb = $this->get_wpdb();
		$this->install();

		if ( is_object( $wpdb ) && method_exists( $wpdb, 'asfw_module_counts' ) ) {
			return $wpdb->asfw_module_counts( $this->get_table_name() );
		}

		if ( is_object( $wpdb ) && method_exists( $wpdb, 'get_results' ) ) {
			$query = "SELECT COALESCE(NULLIF(feature, ''), 'core') AS feature_name, COUNT(*) AS total FROM {$this->get_table_name()} GROUP BY COALESCE(NULLIF(feature, ''), 'core') ORDER BY total DESC";
			$rows  = $wpdb->get_results( $query, ARRAY_A );
			$counts = array();
			foreach ( (array) $rows as $row ) {
				$feature = isset( $row['feature_name'] ) && '' !== $row['feature_name'] ? $row['feature_name'] : 'core';
				$counts[ $feature ] = isset( $row['total'] ) ? intval( $row['total'], 10 ) : 0;
			}

			return $counts;
		}

		return array();
	}

	public function get_daily_counts( $days = 7 ) {
		$wpdb = $this->get_wpdb();
		$this->install();

		$days = max( 1, intval( $days, 10 ) );
		if ( is_object( $wpdb ) && method_exists( $wpdb, 'asfw_daily_counts' ) ) {
			return $wpdb->asfw_daily_counts( $this->get_table_name(), $days );
		}

		if ( is_object( $wpdb ) && method_exists( $wpdb, 'get_results' ) ) {
			$since  = gmdate( 'Y-m-d H:i:s', time() - ( $days * 86400 ) );
			$query  = "SELECT DATE(created_at) AS day, COUNT(*) AS total FROM {$this->get_table_name()} WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY day ASC";
			$sql    = $wpdb->prepare( $query, $since );
			$rows   = $wpdb->get_results( $sql, ARRAY_A );
			$counts = array();

			for ( $offset = $days - 1; $offset >= 0; $offset-- ) {
				$counts[ gmdate( 'Y-m-d', time() - ( $offset * 86400 ) ) ] = 0;
			}

			foreach ( (array) $rows as $row ) {
				if ( isset( $row['day'] ) ) {
					$counts[ $row['day'] ] = isset( $row['total'] ) ? intval( $row['total'], 10 ) : 0;
				}
			}

			return $counts;
		}

		return array();
	}

	public function prune_older_than( $days ) {
		$wpdb = $this->get_wpdb();
		$this->install();

		$days   = max( 1, intval( $days, 10 ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * 86400 ) );

		if ( is_object( $wpdb ) && method_exists( $wpdb, 'asfw_prune_events' ) ) {
			return (int) $wpdb->asfw_prune_events( $this->get_table_name(), $cutoff );
		}

		if ( is_object( $wpdb ) && method_exists( $wpdb, 'query' ) && method_exists( $wpdb, 'prepare' ) ) {
			$query = "DELETE FROM {$this->get_table_name()} WHERE created_at < %s";
			$sql   = $wpdb->prepare( $query, $cutoff );

			return (int) $wpdb->query( $sql );
		}

		return 0;
	}

	public function purge_all() {
		$wpdb = $this->get_wpdb();
		$this->install();

		if ( is_object( $wpdb ) && method_exists( $wpdb, 'asfw_purge_events' ) ) {
			return (int) $wpdb->asfw_purge_events( $this->get_table_name() );
		}

		if ( is_object( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
			return (int) $wpdb->query( "DELETE FROM {$this->get_table_name()}" );
		}

		return 0;
	}
}
