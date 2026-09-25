<?php
/**
 * Short-lived security state with database compare-and-swap semantics.
 *
 * These private, non-autoloaded rows deliberately bypass the options cache: an
 * object cache cannot arbitrate between concurrent PHP workers. Every mutation
 * compares the complete observed value, including a random revision. Callers
 * must treat a false mutation result as a lost race, never as success.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASFW_Atomic_State_Store {
	private $table;

	public function __construct( $blog_id = null ) {
		global $wpdb;
		$this->table = null === $blog_id ? $wpdb->options : $wpdb->get_blog_prefix( (int) $blog_id ) . 'options';
	}

	public function option_name( $key ) {
		return 'asfw_state_' . hash( 'sha256', (string) $key );
	}

	private function unavailable() {
		return new WP_Error( 'asfw_state_unavailable', __( 'Verification is temporarily unavailable. Please try again.', 'anti-spam-for-wordpress' ), array( 'status' => 503 ) );
	}

	private function encode( array $value, $expires_at ) {
		return (int) $expires_at . '|' . bin2hex( random_bytes( 16 ) ) . '|' . wp_json_encode( $value );
	}

	private function snapshot( $raw ) {
		$parts = explode( '|', $raw, 3 );
		$value = isset( $parts[2] ) ? json_decode( $parts[2], true ) : null;
		if ( 3 !== count( $parts ) || ! ctype_digit( $parts[0] ) || ! is_array( $value ) ) {
			return $this->unavailable();
		}

		return array(
			'raw'        => $raw,
			'value'      => $value,
			'expires_at' => (int) $parts[0],
		);
	}

	/** Return an authoritative snapshot, null when absent, or a storage error. */
	public function read( $key, $include_expired = false ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic security state must bypass option caches; table is derived only from wpdb.
		$raw = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $this->table, $this->option_name( $key ) ) );
		if ( '' !== $wpdb->last_error ) {
			return $this->unavailable();
		}
		if ( null === $raw ) {
			return null;
		}

		$snapshot = $this->snapshot( $raw );
		if ( $snapshot instanceof WP_Error || $include_expired || $snapshot['expires_at'] > time() ) {
			return $snapshot;
		}

		$deleted = $this->delete( $key, $snapshot );
		return $deleted instanceof WP_Error ? $deleted : null;
	}

	/** INSERT IGNORE never overwrites another worker's value. */
	public function create( $key, array $value, $ttl ) {
		global $wpdb;
		$raw = $this->encode( $value, time() + max( 1, (int) $ttl ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic insert requires direct SQL and intentionally bypasses option caches.
		$result = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s)', $this->table, $this->option_name( $key ), $raw, 'off' ) );
		return false === $result ? $this->unavailable() : 1 === $result;
	}

	/** Preserve the current fixed expiry unless the caller explicitly supplies a TTL. */
	public function replace( $key, array $snapshot, array $value, $ttl = null ) {
		global $wpdb;
		$expires_at = null === $ttl ? $snapshot['expires_at'] : time() + max( 1, (int) $ttl );
		$raw        = $this->encode( $value, $expires_at );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Exact-value CAS prevents stale workers overwriting newer security state.
		$result = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET option_value = %s WHERE option_name = %s AND BINARY option_value = %s', $this->table, $raw, $this->option_name( $key ), $snapshot['raw'] ) );
		return false === $result ? $this->unavailable() : 1 === $result;
	}

	/** Delete exactly the revision the caller validated; success consumes it once. */
	public function delete( $key, array $snapshot ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Exact-value CAS is the authoritative one-time consumption operation.
		$result = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s AND BINARY option_value = %s', $this->table, $this->option_name( $key ), $snapshot['raw'] ) );
		return false === $result ? $this->unavailable() : 1 === $result;
	}

	/** An ownership token must be retained by the caller until release. */
	public function acquire_lease( $key, $ttl = 30 ) {
		$owner = bin2hex( random_bytes( 16 ) );
		for ( $attempt = 0; $attempt < 3; ++$attempt ) {
			$current = $this->read( $key, true );
			if ( $current instanceof WP_Error ) {
				return $current;
			}
			if ( is_array( $current ) && $current['expires_at'] > time() ) {
				return null;
			}
			$acquired = null === $current ? $this->create( $key, array( 'owner' => $owner ), $ttl ) : $this->replace( $key, $current, array( 'owner' => $owner ), $ttl );
			if ( $acquired instanceof WP_Error ) {
				return $acquired;
			}
			if ( $acquired ) {
				$current = $this->read( $key );
				if ( $current instanceof WP_Error ) {
					return $current;
				}
				return is_array( $current ) && isset( $current['value']['owner'] ) && hash_equals( $owner, $current['value']['owner'] ) ? $current : null;
			}
		}
		return null;
	}

	public function release_lease( $key, array $lease ) {
		return $this->delete( $key, $lease );
	}

	/** Bound each cleanup query so abandoned challenges cannot grow indefinitely. */
	public function cleanup_expired( $limit = 100 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Only expired private state is removed, in a bounded batch.
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE option_name LIKE %s AND CAST(SUBSTRING_INDEX(option_value, '|', 1) AS UNSIGNED) <= %d LIMIT %d", $this->table, $wpdb->esc_like( 'asfw_state_' ) . '%', time(), max( 1, min( 1000, (int) $limit ) ) ) );
		return false === $result ? $this->unavailable() : $result;
	}
}
