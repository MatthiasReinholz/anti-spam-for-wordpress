<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ASFW_Client_Identity', false ) ) {
	class ASFW_Client_Identity {

		private $options_service;

		private function options_service() {
			if ( ! $this->options_service instanceof ASFW_Options ) {
				$this->options_service = new ASFW_Options();
			}

			return $this->options_service;
		}

		public function normalize_ip( $ip_address ) {
			$ip_address = trim( (string) $ip_address, " \t\n\r\0\x0B\"'[]" );
			if ( '' === $ip_address ) {
				return '';
			}

			if ( stripos( $ip_address, 'for=' ) === 0 ) {
				$ip_address = trim( substr( $ip_address, 4 ), " \t\n\r\0\x0B\"'[]" );
			}

			if ( preg_match( '/^\[([^\]]+)\](?::\d+)?$/', $ip_address, $matches ) === 1 ) {
				$ip_address = $matches[1];
			} elseif ( preg_match( '/^[0-9.]+:\d+$/', $ip_address ) === 1 ) {
				$ip_address = preg_replace( '/:\d+$/', '', $ip_address );
			}

			return filter_var( $ip_address, FILTER_VALIDATE_IP ) ? $ip_address : '';
		}

		public function get_trusted_proxy_list() {
			$entries = preg_split( '/[\s,]+/', $this->options_service()->get_trusted_proxies(), -1, PREG_SPLIT_NO_EMPTY );
			if ( ! is_array( $entries ) ) {
				$entries = array();
			}

			$entries = array_values(
				array_filter(
					array_map(
						function ( $entry ) {
							$entry = trim( (string) $entry );
							if ( '' === $entry ) {
								return '';
							}

							if ( strpos( $entry, '/' ) === false ) {
								return $this->normalize_ip( $entry );
							}

							list($subnet, $prefix) = array_pad( explode( '/', $entry, 2 ), 2, '' );
							$subnet                = $this->normalize_ip( $subnet );
							if ( '' === $subnet || ! preg_match( '/^\d+$/', $prefix ) ) {
								return '';
							}

							return $subnet . '/' . $prefix;
						},
						$entries
					)
				)
			);

			return apply_filters( 'asfw_trusted_proxies', array_unique( $entries ) );
		}

		public function ip_matches_range( $ip_address, $range ) {
			if ( '' === $range ) {
				return false;
			}

			if ( strpos( $range, '/' ) === false ) {
				return hash_equals( $range, $ip_address );
			}

			list($subnet, $prefix) = array_pad( explode( '/', $range, 2 ), 2, '' );
			if ( ! preg_match( '/^\d+$/', $prefix ) ) {
				return false;
			}

			$ip_binary     = inet_pton( $ip_address );
			$subnet_binary = inet_pton( $subnet );
			if ( false === $ip_binary || false === $subnet_binary || strlen( $ip_binary ) !== strlen( $subnet_binary ) ) {
				return false;
			}

			$prefix_length = intval( $prefix, 10 );
			$max_bits      = strlen( $ip_binary ) * 8;
			if ( $prefix_length < 0 || $prefix_length > $max_bits ) {
				return false;
			}

			$full_bytes = intdiv( $prefix_length, 8 );
			if ( $full_bytes > 0 && substr( $ip_binary, 0, $full_bytes ) !== substr( $subnet_binary, 0, $full_bytes ) ) {
				return false;
			}

			$remaining_bits = $prefix_length % 8;
			if ( 0 === $remaining_bits ) {
				return true;
			}

			$mask = chr( ( 0xFF << ( 8 - $remaining_bits ) ) & 0xFF );

			return ( ord( $ip_binary[ $full_bytes ] ) & ord( $mask ) ) === ( ord( $subnet_binary[ $full_bytes ] ) & ord( $mask ) );
		}

		public function is_trusted_proxy_ip( $ip_address ) {
			foreach ( $this->get_trusted_proxy_list() as $range ) {
				if ( $this->ip_matches_range( $ip_address, $range ) ) {
					return true;
				}
			}

			return false;
		}

		public function extract_forwarded_for_ip( $header_value ) {
			$candidates = array_map( 'trim', explode( ',', (string) $header_value ) );

			return $this->extract_client_from_forward_chain( $candidates );
		}

		public function extract_forwarded_header_ip( $header_value ) {
			$segments   = explode( ',', (string) $header_value );
			$candidates = array();
			foreach ( $segments as $segment ) {
				$pairs = explode( ';', $segment );
				foreach ( $pairs as $pair ) {
					$pair = trim( $pair );
					if ( stripos( $pair, 'for=' ) !== 0 ) {
						continue;
					}

					$candidates[] = substr( $pair, 4 );
				}
			}

			return $this->extract_client_from_forward_chain( $candidates );
		}

		private function extract_client_from_forward_chain( array $candidates ) {
			$normalized = array_values(
				array_filter(
					array_map( array( $this, 'normalize_ip' ), $candidates )
				)
			);
			if ( empty( $normalized ) ) {
				return '';
			}

			for ( $index = count( $normalized ) - 1; $index >= 0; --$index ) {
				$candidate = $normalized[ $index ];
				if ( ! $this->is_trusted_proxy_ip( $candidate ) ) {
					return $candidate;
				}
			}

			return '';
		}

		public function get_client_ip_address() {
			$remote_address = isset( $_SERVER['REMOTE_ADDR'] ) ? $this->normalize_ip( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) : '';
			if ( '' === $remote_address || ! $this->is_trusted_proxy_ip( $remote_address ) ) {
				return '' !== $remote_address ? $remote_address : 'unknown';
			}

			$headers = array(
				'HTTP_CF_CONNECTING_IP',
				'HTTP_X_REAL_IP',
				'HTTP_FORWARDED',
				'HTTP_X_FORWARDED_FOR',
			);
			foreach ( $headers as $header_name ) {
				if ( empty( $_SERVER[ $header_name ] ) ) {
					continue;
				}

				$header_value = sanitize_text_field( wp_unslash( $_SERVER[ $header_name ] ) );
				if ( 'HTTP_FORWARDED' === $header_name ) {
					$candidate = $this->extract_forwarded_header_ip( $header_value );
				} elseif ( 'HTTP_X_FORWARDED_FOR' === $header_name ) {
					$candidate = $this->extract_forwarded_for_ip( $header_value );
				} else {
					$candidate = $this->normalize_ip( $header_value );
				}

				if ( '' !== $candidate ) {
					return apply_filters( 'asfw_client_ip', $candidate, $remote_address, $header_name );
				}
			}

			return apply_filters( 'asfw_client_ip', $remote_address, $remote_address, 'REMOTE_ADDR' );
		}

		public function get_client_binding_components() {
			$components = array(
				'ip:' . $this->get_client_ip_address(),
			);

			if ( $this->options_service()->get_visitor_binding() === 'ip_ua' ) {
				$user_agent   = isset( $_SERVER['HTTP_USER_AGENT'] )
					? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 512 )
					: '';
				$components[] = 'ua:' . $user_agent;
			}

			return apply_filters( 'asfw_client_binding_components', $components, $this->options_service()->get_visitor_binding() );
		}

		public function get_client_fingerprint() {
			$secret = $this->options_service()->get_secret();
			if ( '' === $secret ) {
				$secret = wp_salt( 'nonce' );
			}

			return hash_hmac( 'sha256', implode( '|', $this->get_client_binding_components() ), $secret );
		}
	}
}
