<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASFW_Admin_Pages {

	protected $store;
	protected $disposable_module;
	protected $content_module;

	public function __construct( ASFW_Event_Store $store, ?ASFW_Disposable_Email_Module $disposable_module = null, ?ASFW_Content_Heuristics_Module $content_module = null ) {
		$this->store             = $store;
		$this->disposable_module = $disposable_module;
		$this->content_module    = $content_module;
	}

	public function register() {
		add_submenu_page(
			asfw_hidden_submenu_parent_slug(),
			__( 'Events', 'anti-spam-for-wordpress' ),
			__( 'Events', 'anti-spam-for-wordpress' ),
			'manage_options',
			'asfw_events',
			array( $this, 'render_events_page' )
		);

		add_submenu_page(
			asfw_hidden_submenu_parent_slug(),
			__( 'Analytics', 'anti-spam-for-wordpress' ),
			__( 'Analytics', 'anti-spam-for-wordpress' ),
			'manage_options',
			'asfw_analytics',
			array( $this, 'render_analytics_page' )
		);
	}

	protected function render_header( $title, $subtitle = '' ) {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<?php if ( '' !== $subtitle ) : ?>
				<p><?php echo esc_html( $subtitle ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	protected function render_event_table( array $events ) {
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Time', 'anti-spam-for-wordpress' ); ?></th>
					<th><?php echo esc_html__( 'Type', 'anti-spam-for-wordpress' ); ?></th>
					<th><?php echo esc_html__( 'Decision', 'anti-spam-for-wordpress' ); ?></th>
					<th><?php echo esc_html__( 'Context', 'anti-spam-for-wordpress' ); ?></th>
					<th><?php echo esc_html__( 'Feature', 'anti-spam-for-wordpress' ); ?></th>
					<th><?php echo esc_html__( 'Details', 'anti-spam-for-wordpress' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $events as $event ) : ?>
					<tr>
						<td><?php echo esc_html( isset( $event['created_at'] ) ? $event['created_at'] : ( isset( $event['created_at_gmt'] ) ? $event['created_at_gmt'] : '' ) ); ?></td>
						<td><?php echo esc_html( isset( $event['event_type'] ) ? $this->store->canonicalize_event_type( $event['event_type'] ) : '' ); ?></td>
						<td><?php echo esc_html( isset( $event['decision'] ) ? $event['decision'] : ( isset( $event['event_status'] ) ? $event['event_status'] : '' ) ); ?></td>
						<td><?php echo esc_html( isset( $event['context'] ) ? $event['context'] : ( isset( $event['event_context'] ) ? $event['event_context'] : '' ) ); ?></td>
						<td><?php echo esc_html( isset( $event['feature'] ) ? $event['feature'] : ( isset( $event['module_name'] ) ? $event['module_name'] : '' ) ); ?></td>
						<td><code><?php echo esc_html( isset( $event['details'] ) ? $event['details'] : '' ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	protected function event_logging_enabled() {
		return ASFW_Feature_Registry::is_enabled( 'event_logging' );
	}

	protected function render_logging_disabled_notice() {
		?>
		<div class="notice notice-warning">
			<p><?php echo esc_html__( 'Event logging is currently disabled. Enable the Event logging feature to collect and display runtime data.', 'anti-spam-for-wordpress' ); ?></p>
		</div>
		<?php
	}

	protected function sanitize_date_filter( $value ) {
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

	protected function get_events_filters_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Read-only admin filters do not change state.
		$raw = array(
			'date_from' => isset( $_GET['asfw_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['asfw_date_from'] ) ) : '',
			'date_to'   => isset( $_GET['asfw_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['asfw_date_to'] ) ) : '',
			'context'   => isset( $_GET['asfw_context'] ) ? sanitize_text_field( wp_unslash( $_GET['asfw_context'] ) ) : '',
			'type'      => isset( $_GET['asfw_event_type'] ) ? sanitize_text_field( wp_unslash( $_GET['asfw_event_type'] ) ) : '',
			'feature'   => isset( $_GET['asfw_feature'] ) ? sanitize_text_field( wp_unslash( $_GET['asfw_feature'] ) ) : '',
			'decision'  => isset( $_GET['asfw_decision'] ) ? sanitize_text_field( wp_unslash( $_GET['asfw_decision'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing

		return array(
			'date_from' => $this->sanitize_date_filter( $raw['date_from'] ),
			'date_to'   => $this->sanitize_date_filter( $raw['date_to'] ),
			'context'   => sanitize_text_field( (string) $raw['context'] ),
			'type'      => sanitize_key( (string) $raw['type'] ),
			'feature'   => sanitize_key( (string) $raw['feature'] ),
			'decision'  => sanitize_key( (string) $raw['decision'] ),
		);
	}

	protected function render_events_filter_form( array $filters ) {
		?>
		<form method="get" class="asfw-events-filters">
			<input type="hidden" name="page" value="asfw_events">
			<p>
				<label for="asfw_date_from"><?php echo esc_html__( 'Date from', 'anti-spam-for-wordpress' ); ?></label>
				<input id="asfw_date_from" type="date" name="asfw_date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
				<label for="asfw_date_to"><?php echo esc_html__( 'Date to', 'anti-spam-for-wordpress' ); ?></label>
				<input id="asfw_date_to" type="date" name="asfw_date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
				<label for="asfw_context"><?php echo esc_html__( 'Context', 'anti-spam-for-wordpress' ); ?></label>
				<input id="asfw_context" type="text" name="asfw_context" value="<?php echo esc_attr( $filters['context'] ); ?>">
				<label for="asfw_event_type"><?php echo esc_html__( 'Event type', 'anti-spam-for-wordpress' ); ?></label>
				<input id="asfw_event_type" type="text" name="asfw_event_type" value="<?php echo esc_attr( $filters['type'] ); ?>">
				<label for="asfw_feature"><?php echo esc_html__( 'Feature', 'anti-spam-for-wordpress' ); ?></label>
				<input id="asfw_feature" type="text" name="asfw_feature" value="<?php echo esc_attr( $filters['feature'] ); ?>">
				<label for="asfw_decision"><?php echo esc_html__( 'Decision', 'anti-spam-for-wordpress' ); ?></label>
				<input id="asfw_decision" type="text" name="asfw_decision" value="<?php echo esc_attr( $filters['decision'] ); ?>">
				<button type="submit" class="button button-primary"><?php echo esc_html__( 'Apply filters', 'anti-spam-for-wordpress' ); ?></button>
			</p>
		</form>
		<?php
	}

	protected function render_events_summary() {
		$retention = $this->store->get_retention_days();
		$last_run  = (string) get_option( ASFW_Maintenance::OPTION_LAST_RUN, '' );
		?>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: retention window in days. */
					__( 'Retention window: %d days.', 'anti-spam-for-wordpress' ),
					$retention
				)
			);
			?>
			<?php if ( '' !== $last_run ) : ?>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: last maintenance run time in UTC. */
						__( ' Last maintenance run: %s UTC.', 'anti-spam-for-wordpress' ),
						$last_run
					)
				);
				?>
			<?php else : ?>
				<?php echo esc_html__( ' Last maintenance run: not recorded yet.', 'anti-spam-for-wordpress' ); ?>
			<?php endif; ?>
		</p>
		<?php
	}

	public function render_events_page() {
		$filters = $this->get_events_filters_from_request();
		$events  = $this->store->fetch_events(
			array_merge(
				$filters,
				array( 'limit' => 50 )
			)
		);

		$this->render_header(
			__( 'Events', 'anti-spam-for-wordpress' ),
			__( 'Read-only event log with hashed identifiers only.', 'anti-spam-for-wordpress' )
		);
		$this->render_events_summary();
		$this->render_events_filter_form( $filters );

		if ( ! $this->event_logging_enabled() ) {
			$this->render_logging_disabled_notice();
		}

		$this->render_event_table( $events );
	}

	protected function render_summary_list( array $items ) {
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Label', 'anti-spam-for-wordpress' ); ?></th>
					<th><?php echo esc_html__( 'Count', 'anti-spam-for-wordpress' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $label => $count ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $label ); ?></td>
						<td><?php echo esc_html( (string) intval( $count, 10 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	public function render_analytics_page() {
		$filters          = $this->get_events_filters_from_request();
		$events           = $this->store->fetch_events(
			array_merge(
				$filters,
				array(
					'limit'  => PHP_INT_MAX,
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
			$event_type = isset( $event['event_type'] ) ? $this->store->canonicalize_event_type( $event['event_type'] ) : '';
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

		$this->render_header(
			__( 'Analytics', 'anti-spam-for-wordpress' ),
			__( 'Aggregated event metrics derived from the local event store.', 'anti-spam-for-wordpress' )
		);
		if ( ! $this->event_logging_enabled() ) {
			$this->render_logging_disabled_notice();
		}

		?>
		<h2><?php echo esc_html__( 'Challenges issued by day', 'anti-spam-for-wordpress' ); ?></h2>
		<?php $this->render_summary_list( $daily_challenges ); ?>

		<h2><?php echo esc_html__( 'Verify pass/fail by day', 'anti-spam-for-wordpress' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Day', 'anti-spam-for-wordpress' ); ?></th>
					<th><?php echo esc_html__( 'Pass', 'anti-spam-for-wordpress' ); ?></th>
					<th><?php echo esc_html__( 'Fail', 'anti-spam-for-wordpress' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $daily_verify as $day => $totals ) : ?>
					<tr>
						<td><?php echo esc_html( $day ); ?></td>
						<td><?php echo esc_html( (string) intval( $totals['pass'], 10 ) ); ?></td>
						<td><?php echo esc_html( (string) intval( $totals['fail'], 10 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php echo esc_html__( 'Top contexts', 'anti-spam-for-wordpress' ); ?></h2>
		<?php $this->render_summary_list( array_slice( $context_counts, 0, 10, true ) ); ?>

		<h2><?php echo esc_html__( 'Rate-limit totals', 'anti-spam-for-wordpress' ); ?></h2>
		<?php $this->render_summary_list( array( __( 'rate_limited', 'anti-spam-for-wordpress' ) => $rate_limit_total ) ); ?>

		<h2><?php echo esc_html__( 'Feature-hit totals', 'anti-spam-for-wordpress' ); ?></h2>
		<?php $this->render_summary_list( $feature_hits ); ?>
		<?php
	}
}
