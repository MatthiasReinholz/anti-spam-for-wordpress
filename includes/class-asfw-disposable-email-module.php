<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASFW_Disposable_Email_Module {

	const OPTION_DOMAINS = 'asfw_disposable_email_domains';

	const OPTION_LAST_REFRESH = 'asfw_disposable_email_last_refresh';

	const OPTION_AUTO_REFRESH = 'asfw_disposable_email_auto_refresh';

	// phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Server-side plain-text domain data; validated into local options, never enqueued or executed.
	const DEFAULT_REMOTE_URL = 'https://raw.githubusercontent.com/disposable/disposable-email-domains/master/domains.txt';

	const MAX_REMOTE_BYTES = 8 * 1024 * 1024;

	const MAX_REMOTE_LINES = 400000;

	const MAX_REMOTE_DOMAINS = 200000;

	protected $candidate_email_fields = array(
		'wordpress:register'   => array( 'user_email' ),
		'wordpress:comments'   => array( 'email' ),
		'woocommerce:register' => array( 'email', 'billing_email' ),
	);

	protected $store;

	public function __construct( ASFW_Event_Store $store ) {
		$this->store = $store;
	}

	public function register_hooks() {
		add_action( 'asfw_verify_result', array( $this, 'log_verify_result' ), 15, 5 );
	}

	public function is_enabled( $context = null ) {
		return ASFW_Feature_Registry::is_enabled( 'disposable_email', is_string( $context ) ? $context : null );
	}

	public function is_background_enabled() {
		return ASFW_Feature_Registry::background_enabled( 'disposable_email' );
	}

	public function get_mode() {
		return ASFW_Feature_Registry::mode( 'disposable_email' );
	}

	protected function normalize_email( $email ) {
		$email = strtolower( trim( (string) $email ) );
		if ( '' === $email || false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return '';
		}

		return $email;
	}

	protected function get_posted_value( array $post, $field_name ) {
		$field_name = sanitize_key( (string) $field_name );
		if ( '' === $field_name || ! array_key_exists( $field_name, $post ) ) {
			return '';
		}

		$value = $post[ $field_name ];
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		return trim( strtolower( (string) wp_unslash( $value ) ) );
	}

	public function get_candidate_email_fields( $context ) {
		$context = ASFW_Feature_Registry::normalize_context( $context );
		$fields  = isset( $this->candidate_email_fields[ $context ] ) ? $this->candidate_email_fields[ $context ] : array();

		return array_values(
			array_filter(
				array_map(
					'sanitize_key',
					is_array( $fields ) ? $fields : array()
				)
			)
		);
	}

	protected function get_fallback_candidate_emails( array $post ) {
		$emails = array();
		foreach ( $post as $field_name => $value ) {
			$field_name = sanitize_key( (string) $field_name );
			if ( '' === $field_name || false === strpos( $field_name, 'email' ) ) {
				continue;
			}

			if ( is_array( $value ) || is_object( $value ) ) {
				continue;
			}

			$email = $this->normalize_email( wp_unslash( (string) $value ) );
			if ( '' === $email ) {
				continue;
			}

			$emails[ $field_name ] = $email;
		}

		return $emails;
	}

	public function get_candidate_emails( $context, $post = null, &$used_fallback = false ) {
		$context = ASFW_Feature_Registry::normalize_context( $context );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Anti-spam email analysis intentionally reads the submitted payload during verification.
		$post                   = is_array( $post ) ? $post : $_POST;
		$emails                 = array();
		$used_fallback          = false;
		$candidate_email_fields = $this->get_candidate_email_fields( $context );

		foreach ( $candidate_email_fields as $field_name ) {
			$email = $this->get_posted_value( $post, $field_name );
			if ( '' === $email ) {
				continue;
			}

			$emails[ $field_name ] = $email;
		}

		if ( empty( $emails ) && empty( $candidate_email_fields ) ) {
			$emails        = $this->get_fallback_candidate_emails( $post );
			$used_fallback = ! empty( $emails );
		}

		$emails = apply_filters( 'asfw_candidate_emails', $emails, $context, $post );
		if ( ! is_array( $emails ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $emails as $field_name => $email ) {
			$field_name = is_int( $field_name ) ? 'candidate_' . $field_name : sanitize_key( (string) $field_name );
			$email      = $this->normalize_email( $email );
			if ( '' === $field_name || '' === $email ) {
				continue;
			}

			$normalized[ $field_name ] = $email;
		}

		return $normalized;
	}

	protected function get_actor_hash() {
		$plugin = AntiSpamForWordPressPlugin::$instance;
		if ( $plugin instanceof AntiSpamForWordPressPlugin ) {
			return $plugin->get_client_fingerprint();
		}

		return $this->store->hash_value( 'unknown', 'actor' );
	}

	public function analyze_submission( $context, $post = null ) {
		$context        = ASFW_Feature_Registry::normalize_context( $context );
		$mode           = $this->get_mode();
		$fallback_used  = false;
		$emails         = $this->get_candidate_emails( $context, $post, $fallback_used );
		$matched        = array();
		$matched_emails = array();

		$analysis = array(
			'context'          => $context,
			'mode'             => $mode,
			'enabled'          => $this->is_enabled( $context ),
			'candidate_fields' => array_keys( $emails ),
			'candidate_count'  => count( $emails ),
			'matched_fields'   => array(),
			'matched_emails'   => array(),
			'matched_count'    => 0,
			'hit'              => false,
			'blocked'          => false,
			'fallback'         => $fallback_used,
			'decision'         => 'ignored',
			'email_hash'       => '',
		);

		if ( ! $analysis['enabled'] || 'off' === $mode || empty( $emails ) ) {
			return $analysis;
		}

		foreach ( $this->find_disposable_emails( $emails ) as $field_name => $email ) {
			$matched[]        = sanitize_key( (string) $field_name );
			$matched_emails[] = $email;
		}

		$matched = array_values( array_unique( array_filter( $matched ) ) );
		if ( empty( $matched ) ) {
			return $analysis;
		}

		$analysis['hit']            = true;
		$analysis['blocked']        = 'block' === $mode;
		$analysis['decision']       = $analysis['blocked'] ? 'blocked' : 'matched';
		$analysis['matched_fields'] = $matched;
		$analysis['matched_emails'] = $matched_emails;
		$analysis['matched_count']  = count( $matched );
		$analysis['email_hash']     = $this->store->hash_value( $matched_emails[0], 'email' );

		return $analysis;
	}

	public function record_disposable_email_hit( array $analysis, $field_name = 'asfw' ) {
		if ( empty( $analysis['hit'] ) ) {
			return false;
		}
		$event_context = isset( $analysis['context'] ) ? ASFW_Feature_Registry::normalize_context( $analysis['context'] ) : 'generic';
		if ( ! ASFW_Feature_Registry::is_enabled( 'event_logging', $event_context ) ) {
			return false;
		}

		$this->store->record_event(
			'disposable_email_hit',
			array(
				'decision'   => isset( $analysis['decision'] ) ? sanitize_key( (string) $analysis['decision'] ) : 'matched',
				'context'    => $event_context,
				'feature'    => 'disposable-email',
				'ip_hash'    => $this->get_actor_hash(),
				'email_hash' => isset( $analysis['email_hash'] ) ? (string) $analysis['email_hash'] : '',
				'details'    => array(
					'field_name'       => sanitize_key( (string) $field_name ),
					'mode'             => isset( $analysis['mode'] ) ? sanitize_key( (string) $analysis['mode'] ) : 'log',
					'fallback'         => ! empty( $analysis['fallback'] ),
					'candidate_fields' => isset( $analysis['candidate_fields'] ) && is_array( $analysis['candidate_fields'] ) ? array_values( array_map( 'sanitize_key', $analysis['candidate_fields'] ) ) : array(),
					'matched_fields'   => isset( $analysis['matched_fields'] ) && is_array( $analysis['matched_fields'] ) ? array_values( array_map( 'sanitize_key', $analysis['matched_fields'] ) ) : array(),
					'candidate_count'  => isset( $analysis['candidate_count'] ) ? intval( $analysis['candidate_count'], 10 ) : 0,
					'matched_count'    => isset( $analysis['matched_count'] ) ? intval( $analysis['matched_count'], 10 ) : 0,
				),
			)
		);

		return true;
	}

	public function log_verify_result( $success, $result, $context, $field_name, $resolved_context = null ) {
		$event_context = '' !== trim( (string) $resolved_context ) ? $resolved_context : $context;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Logging uses the just-verified submission to record privacy-preserving spam signals.
		$analysis = $this->analyze_submission( $event_context, $_POST );

		if ( empty( $analysis['hit'] ) ) {
			return;
		}

		if ( ! $success ) {
			if ( ! ( $result instanceof WP_Error && 'asfw_disposable_email_blocked' === $result->get_error_code() ) ) {
				return;
			}
		}

		$this->record_disposable_email_hit( $analysis, $field_name );
	}

	public function get_bundled_domains() {
		$domains = array();
		$file    = plugin_dir_path( ASFW_FILE ) . 'data/disposable-email-domains.php';
		if ( file_exists( $file ) ) {
			$bundled = require $file;
			if ( is_array( $bundled ) ) {
				$domains = $bundled;
			}
		}

		return array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $domain ) {
							$domain = strtolower( trim( (string) $domain ) );
							$domain = preg_replace( '/^https?:\/\//', '', $domain );
							$domain = trim( $domain, " \t\n\r\0\x0B./" );
							return preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:[a-z]{2,63}|xn--[a-z0-9-]{2,59})$/', $domain ) ? $domain : '';
						},
						$domains
					)
				)
			)
		);
	}

	public function get_domains() {
		$domains = get_option( self::OPTION_DOMAINS, array() );
		if ( ! is_array( $domains ) || empty( $domains ) ) {
			$domains = $this->get_bundled_domains();
		}

		return array_values( array_unique( array_filter( $domains ) ) );
	}

	public function set_domains( array $domains ) {
		$domains = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $domain ) {
							$domain = strtolower( trim( (string) $domain ) );
							return preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:[a-z]{2,63}|xn--[a-z0-9-]{2,59})$/', $domain ) ? $domain : '';
						},
						$domains
					)
				)
			)
		);

		$this->last_refresh_error = null;
		update_option( self::OPTION_DOMAINS, $domains );
		// An unchanged option also returns false; verify the persisted value instead.
		if ( get_option( self::OPTION_DOMAINS, null ) !== $domains ) {
			$this->last_refresh_error = new WP_Error( 'asfw_disposable_persistence_failed', 'The domain feed could not be saved. Please retry the refresh.' );
			return $this->get_domains();
		}

		return $domains;
	}

	/** Validate the complete feed before allowing it to replace the last good list. */
	protected function parse_remote_domains( $body, array $current ) {
		if ( '' === trim( $body ) || strlen( $body ) > self::MAX_REMOTE_BYTES ) {
			return new WP_Error( 'asfw_disposable_invalid_size', 'Empty or oversized domain feed.' );
		}

		$domains    = array();
		$offset     = 0;
		$line_count = 0;
		$body_size  = strlen( $body );
		// Scan one line at a time: millions of tiny lines must not allocate an array.
		while ( $offset < $body_size ) {
			if ( ++$line_count > self::MAX_REMOTE_LINES ) {
				return new WP_Error( 'asfw_disposable_too_many_lines', 'The domain feed contains too many lines.' );
			}
			$line_length = strcspn( $body, "\r\n", $offset );
			$line        = substr( $body, $offset, $line_length );
			$offset     += $line_length;
			if ( $offset < $body_size && "\r" === $body[ $offset ] && $offset + 1 < $body_size && "\n" === $body[ $offset + 1 ] ) {
				++$offset;
			}
			++$offset;
			$line = strtolower( trim( $line ) );
			if ( '' === $line || '#' === substr( $line, 0, 1 ) ) {
				continue;
			}
			if ( strlen( $line ) > 253 || ! preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:[a-z]{2,63}|xn--[a-z0-9-]{2,59})$/', $line ) ) {
				return new WP_Error( 'asfw_disposable_invalid_domain', 'Malformed domain feed.' );
			}
			$domains[ $line ] = true;
			if ( count( $domains ) > self::MAX_REMOTE_DOMAINS ) {
				return new WP_Error( 'asfw_disposable_too_many_domains', 'The domain feed contains too many unique domains.' );
			}
		}

		$domains = array_keys( $domains );
		if ( empty( $domains ) ) {
			return new WP_Error( 'asfw_disposable_empty_feed', 'The domain feed contains no domains.' );
		}

		$minimum_ratio = max( 0.0, min( 1.0, (float) apply_filters( 'asfw_disposable_email_minimum_refresh_ratio', 0.5 ) ) );
		if ( count( $domains ) < count( $current ) * $minimum_ratio ) {
			return new WP_Error( 'asfw_disposable_feed_reduced', 'The domain feed unexpectedly lost more than the permitted proportion of entries.' );
		}

		return $domains;
	}

	private $last_refresh_error;

	public function get_last_refresh_error() {
		return $this->last_refresh_error;
	}

	public function refresh_from_source( $force_remote = false ) {
		$domains = $this->get_domains();
		$source  = 'bundled';
		$error   = null;

		if ( $force_remote ) {
			$remote_url = trim( (string) apply_filters( 'asfw_disposable_email_remote_url', self::DEFAULT_REMOTE_URL ) );
			if ( 'https' !== wp_parse_url( $remote_url, PHP_URL_SCHEME ) ) {
				$error = new WP_Error( 'asfw_disposable_invalid_source', 'Domain feeds require a valid HTTPS source.' );
			} else {
				$response = wp_safe_remote_get(
					$remote_url,
					array(
						'timeout'             => 5,
						'redirection'         => 0,
						'limit_response_size' => self::MAX_REMOTE_BYTES + 1,
					)
				);
				if ( is_wp_error( $response ) ) {
					$error = $response;
				} elseif ( 200 !== intval( wp_remote_retrieve_response_code( $response ), 10 ) ) {
					$error = new WP_Error( 'asfw_disposable_http_error', 'The domain feed request failed.' );
				} else {
					$candidate = $this->parse_remote_domains( (string) wp_remote_retrieve_body( $response ), $domains );
					if ( is_wp_error( $candidate ) ) {
						$error = $candidate;
					} else {
						$domains = $candidate;
						$source  = 'remote';
					}
				}
			}
		} else {
			$domains = $this->get_bundled_domains();
		}

		if ( ! is_wp_error( $error ) ) {
			$domains = $this->set_domains( $domains );
			$error   = $this->last_refresh_error;
			if ( ! is_wp_error( $error ) ) {
				$refreshed_at = gmdate( 'Y-m-d H:i:s' );
				update_option( self::OPTION_LAST_REFRESH, $refreshed_at );
				if ( get_option( self::OPTION_LAST_REFRESH, '' ) !== $refreshed_at ) {
					$error = new WP_Error( 'asfw_disposable_refresh_time_failed', 'The domain feed refresh time could not be saved. Please retry the refresh.' );
				}
			}
		}
		$this->last_refresh_error = $error;

		$this->store->record_event(
			'disposable_list_refreshed',
			array(
				'decision' => is_wp_error( $error ) ? 'failed' : 'complete',
				'context'  => 'disposable-email',
				'feature'  => 'disposable-email',
				'details'  => array(
					'count'      => count( $domains ),
					'source'     => is_wp_error( $error ) ? 'remote_failed' : $source,
					'error_code' => is_wp_error( $error ) ? $error->get_error_code() : '',
				),
			)
		);

		do_action( 'asfw_disposable_email_refreshed', $domains );

		return $domains;
	}

	/** Match a submission against one fresh domain lookup, preserving field names. */
	public function find_disposable_emails( array $emails ) {
		if ( empty( $emails ) ) {
			return array();
		}
		$domains = array_fill_keys( $this->get_domains(), true );
		$matched = array();
		foreach ( $emails as $field_name => $email ) {
			if ( ! is_scalar( $email ) ) {
				continue;
			}
			$email = trim( strtolower( (string) $email ) );
			$at    = strrpos( $email, '@' );
			if ( false !== $at && isset( $domains[ substr( $email, $at + 1 ) ] ) ) {
				$matched[ $field_name ] = $email;
			}
		}
		return $matched;
	}

	public function is_disposable_email( $email ) {
		return ! empty( $this->find_disposable_emails( array( $email ) ) );
	}

	public function get_last_refresh() {
		return trim( (string) get_option( self::OPTION_LAST_REFRESH, '' ) );
	}

	public function needs_refresh() {
		$last_refresh = $this->get_last_refresh();
		if ( '' === $last_refresh ) {
			return true;
		}

		$last_timestamp = strtotime( $last_refresh );
		if ( false === $last_timestamp ) {
			return true;
		}

		return ( time() - $last_timestamp ) >= ( 7 * DAY_IN_SECONDS );
	}

	public function maybe_refresh() {
		$this->last_refresh_error = null;
		if ( ! $this->is_background_enabled() ) {
			return count( $this->get_domains() );
		}

		if ( $this->needs_refresh() ) {
			return count( $this->refresh_from_source( true ) );
		}

		return count( $this->get_domains() );
	}
}
