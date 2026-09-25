<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASFW_Content_Heuristics_Module {

	const OPTION_ENABLED = 'asfw_content_heuristics_enabled';

	const OPTION_STRICT = 'asfw_content_heuristics_strict';

	protected $store;
	protected $disposable_module;

	public function __construct( ASFW_Event_Store $store, ?ASFW_Disposable_Email_Module $disposable_module = null ) {
		$this->store             = $store;
		$this->disposable_module = $disposable_module;
	}

	public function set_disposable_module( ASFW_Disposable_Email_Module $disposable_module ) {
		$this->disposable_module = $disposable_module;
	}

	public function register_hooks() {
		add_action( 'asfw_verify_result', array( $this, 'inspect_submission' ), 20, 5 );
	}

	public function is_enabled( $context = null ) {
		return ASFW_Feature_Registry::is_enabled( 'content_heuristics', is_string( $context ) ? $context : null );
	}

	public function is_strict() {
		return (bool) get_option( self::OPTION_STRICT, 0 );
	}

	public function get_heuristic_terms() {
		$file = plugin_dir_path( ASFW_FILE ) . 'data/content-heuristics.php';
		if ( ! file_exists( $file ) ) {
			return array();
		}

		$terms = require $file;
		return is_array( $terms ) ? $terms : array();
	}

	protected function collect_candidate_text() {
		$candidates = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This is read-only heuristic analysis after verification.
		foreach ( (array) $_POST as $key => $value ) {
			$key = (string) $key;
			if ( false !== strpos( $key, 'asfw' ) || asfw_field_is_credential( $key ) ) {
				continue;
			}

			if ( is_array( $value ) || is_object( $value ) ) {
				continue;
			}

			$value = trim( (string) wp_unslash( $value ) );
			if ( '' !== $value ) {
				$candidates[ $key ] = $value;
			}
		}

		return $candidates;
	}

	public function analyze_submission( $context = null ) {
		$context           = ASFW_Feature_Registry::normalize_context( $context );
		$terms             = $this->get_heuristic_terms();
		$candidates        = $this->collect_candidate_text();
		$score             = 0;
		$reasons           = array();
		$matches           = array();
		$disposable_emails = array();
		if ( $this->disposable_module instanceof ASFW_Disposable_Email_Module && $this->disposable_module->is_enabled( $context ) ) {
			$email_candidates  = array_filter(
				$candidates,
				static function ( $field_name ) {
					return false !== strpos( (string) $field_name, 'email' );
				},
				ARRAY_FILTER_USE_KEY
			);
			$disposable_emails = $this->disposable_module->find_disposable_emails( $email_candidates );
		}

		foreach ( $candidates as $field_name => $value ) {
			$normalized = strtolower( $value );
			if ( preg_match_all( '/https?:\/\/[^\s]+/i', $value, $url_matches ) ) {
				$url_count = count( $url_matches[0] );
				if ( $url_count > 0 ) {
					$score    += $url_count;
					$reasons[] = 'urls:' . $field_name;
				}
			}

			if ( preg_match( '/(.)\1{6,}/', $value ) ) {
				$score    += 2;
				$reasons[] = 'repetition:' . $field_name;
			}

			foreach ( $terms['keywords'] ?? array() as $keyword ) {
				$keyword = strtolower( trim( (string) $keyword ) );
				if ( '' === $keyword ) {
					continue;
				}

				if ( false !== strpos( $normalized, $keyword ) ) {
					++$score;
					$matches[] = 'keyword:' . $keyword;
				}
			}

			if ( isset( $disposable_emails[ $field_name ] ) ) {
				$score    += 2;
				$reasons[] = 'disposable_email:' . $field_name;
			}
		}

		return array(
			'score'     => $score,
			'reasons'   => array_values( array_unique( $reasons ) ),
			'matches'   => array_values( array_unique( $matches ) ),
			'threshold' => $this->is_strict() ? 2 : 4,
			'fields'    => count( $candidates ),
		);
	}

	public function inspect_submission( $success, $result, $context, $field_name, $resolved_context = null ) {
		$event_context = ASFW_Feature_Registry::normalize_context( '' !== trim( (string) $resolved_context ) ? $resolved_context : $context );

		if ( ! $success || ! $this->is_enabled( $event_context ) ) {
			return;
		}

			$analysis = $this->analyze_submission( $event_context );
		if ( $analysis['score'] < $analysis['threshold'] ) {
			return;
		}
		if ( ! ASFW_Feature_Registry::is_enabled( 'event_logging', $event_context ) ) {
			return;
		}

			$this->store->record_event(
				'heuristic_flagged',
				array(
					'event_status'  => 'flagged',
					'event_context' => $event_context,
					'module_name'   => 'content-heuristics',
					'details'       => $analysis + array( 'field_name' => sanitize_key( (string) $field_name ) ),
				)
			);

		do_action( 'asfw_content_heuristics_flagged', $analysis, $event_context, $field_name );
	}
}
