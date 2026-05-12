<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ASFW_Privacy_Policy_Text {

	const LEGAL_BASIS_REVIEW_REQUIRED = 'review_required';
	const LEGAL_BASIS_CONSENT         = 'consent';
	const LEGAL_BASIS_LEGITIMATE      = 'legitimate_interests';

	public static function legal_basis_options() {
		return array(
			self::LEGAL_BASIS_REVIEW_REQUIRED => __( 'Review required', 'anti-spam-for-wordpress' ),
			self::LEGAL_BASIS_CONSENT         => __( 'Consent (Article 6(1)(a) GDPR)', 'anti-spam-for-wordpress' ),
			self::LEGAL_BASIS_LEGITIMATE      => __( 'Legitimate interests (Article 6(1)(f) GDPR)', 'anti-spam-for-wordpress' ),
		);
	}

	public static function sanitize_legal_basis( $value ) {
		$value = trim( (string) $value );

		return array_key_exists( $value, self::legal_basis_options() ) ? $value : self::LEGAL_BASIS_REVIEW_REQUIRED;
	}

	public static function get_relevant_options() {
		$options = array(
			AntiSpamForWordPressPlugin::$option_privacy_legal_basis,
			AntiSpamForWordPressPlugin::$option_kill_switch,
			AntiSpamForWordPressPlugin::$option_auto,
			AntiSpamForWordPressPlugin::$option_lazy,
			AntiSpamForWordPressPlugin::$option_expires,
			AntiSpamForWordPressPlugin::$option_honeypot,
			AntiSpamForWordPressPlugin::$option_min_submit_time,
			AntiSpamForWordPressPlugin::$option_rate_limit_max_challenges,
			AntiSpamForWordPressPlugin::$option_rate_limit_max_failures,
			AntiSpamForWordPressPlugin::$option_rate_limit_window,
			AntiSpamForWordPressPlugin::$option_visitor_binding,
			AntiSpamForWordPressPlugin::$option_trusted_proxies,
			AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms,
			AntiSpamForWordPressPlugin::$option_feature_bunny_shield_dry_run,
			AntiSpamForWordPressPlugin::$option_feature_bunny_shield_threshold,
			AntiSpamForWordPressPlugin::$option_feature_bunny_shield_ttl_minutes,
			AntiSpamForWordPressPlugin::$option_feature_bunny_shield_action,
			'asfw_event_logging_retention_days',
		);

		foreach ( ASFW_Feature_Registry::get_integration_features() as $feature ) {
			if ( ! empty( $feature['option'] ) ) {
				$options[] = (string) $feature['option'];
			}
		}

		foreach ( ASFW_Feature_Registry::definitions() as $definition ) {
			foreach ( array( 'enabled_option', 'mode_option', 'scope_mode_option', 'contexts_option', 'background_option' ) as $key ) {
				if ( ! empty( $definition[ $key ] ) ) {
					$options[] = (string) $definition[ $key ];
				}
			}
		}

		return array_values( array_unique( array_filter( $options ) ) );
	}

	public static function payload() {
		$flags = self::flags();

		return array(
			'text'             => self::text( $flags ),
			'summary'          => self::summary( $flags ),
			'relevant_options' => self::get_relevant_options(),
			'flags'            => $flags,
		);
	}

	public static function text( ?array $flags = null ) {
		$flags      = is_array( $flags ) ? $flags : self::flags();
		$paragraphs = array(
			__( 'Use of Anti Spam for WordPress', 'anti-spam-for-wordpress' ),
			__( 'We use the Anti Spam for WordPress plugin on this website to protect forms and interactive website areas against spam, automated submissions, and abuse. The plugin replaces external CAPTCHA or Cloudflare Turnstile-style verification with self-hosted anti-spam checks that run in our WordPress installation.', 'anti-spam-for-wordpress' ),
			self::legal_basis_text( $flags['legal_basis'] ),
			self::core_processing_text( $flags ),
			self::storage_text( $flags ),
		);

		if ( ! empty( $flags['disposable_email'] ) ) {
			$paragraphs[] = self::disposable_email_text( $flags );
		}

		if ( ! empty( $flags['content_heuristics'] ) ) {
			$paragraphs[] = __( 'If content heuristics are active for a protected form, submitted form content may be inspected locally for spam indicators such as suspicious links, repeated characters, or configured spam terms.', 'anti-spam-for-wordpress' );
		}

		if ( ! empty( $flags['math_challenge'] ) ) {
			$paragraphs[] = __( 'If the math challenge feature is active, the plugin may display a simple arithmetic question and store a short-lived signed challenge token to validate the answer.', 'anti-spam-for-wordpress' );
		}

		if ( ! empty( $flags['submit_delay'] ) ) {
			$paragraphs[] = sprintf(
				/* translators: %s is the configured submit delay in seconds. */
				__( 'If the submit delay feature is active, the plugin may issue a signed delay token and reject submissions sent before the configured waiting period of approximately %s seconds has passed.', 'anti-spam-for-wordpress' ),
				self::format_seconds( $flags['submit_delay_seconds'] )
			);
		}

		$paragraphs[] = self::external_transfer_text( $flags );
		$paragraphs[] = __( 'This suggested text is not legal consultation. Please review and adjust it to the forms, purposes, retention periods, legal basis, and other legal requirements that apply to your specific website, and consult your lawyer before using it in your privacy policy.', 'anti-spam-for-wordpress' );

		return implode( "\n\n", array_values( array_filter( $paragraphs ) ) );
	}

	private static function flags() {
		$bunny_external_sync = (
			ASFW_Feature_Registry::is_enabled( 'bunny_shield' ) &&
			ASFW_Feature_Registry::background_enabled( 'bunny_shield' ) &&
			'block' === ASFW_Feature_Registry::active_mode( 'bunny_shield' ) &&
			'block' === trim( (string) get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_action, 'block' ) ) &&
			! (bool) get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_dry_run, true )
		);

		return array(
			'legal_basis'             => self::sanitize_legal_basis( get_option( AntiSpamForWordPressPlugin::$option_privacy_legal_basis, self::LEGAL_BASIS_REVIEW_REQUIRED ) ),
			'kill_switch'             => ASFW_Feature_Registry::kill_switch_active(),
			'active_integrations'     => self::active_integrations(),
			'ip_user_agent_binding'   => 'ip_ua' === trim( (string) get_option( AntiSpamForWordPressPlugin::$option_visitor_binding, 'ip' ) ),
			'trusted_proxies'         => '' !== trim( (string) get_option( AntiSpamForWordPressPlugin::$option_trusted_proxies, '' ) ),
			'honeypot'                => (bool) get_option( AntiSpamForWordPressPlugin::$option_honeypot, false ),
			'min_submit_time_seconds' => max( 0, intval( get_option( AntiSpamForWordPressPlugin::$option_min_submit_time, 0 ), 10 ) ),
			'rate_limits'             => intval( get_option( AntiSpamForWordPressPlugin::$option_rate_limit_max_challenges, 0 ), 10 ) > 0 || intval( get_option( AntiSpamForWordPressPlugin::$option_rate_limit_max_failures, 0 ), 10 ) > 0,
			'rate_limit_window'       => max( 60, intval( get_option( AntiSpamForWordPressPlugin::$option_rate_limit_window, 600 ), 10 ) ),
			'event_logging'           => ASFW_Feature_Registry::is_enabled( 'event_logging' ),
			'event_retention_days'    => max( 1, intval( get_option( 'asfw_event_logging_retention_days', '30' ), 10 ) ),
			'disposable_email'        => ASFW_Feature_Registry::is_enabled( 'disposable_email' ),
			'disposable_auto_refresh' => ASFW_Feature_Registry::background_enabled( 'disposable_email' ),
			'content_heuristics'      => ASFW_Feature_Registry::is_enabled( 'content_heuristics' ),
			'math_challenge'          => ASFW_Feature_Registry::is_enabled( 'math_challenge' ),
			'submit_delay'            => ASFW_Feature_Registry::is_enabled( 'submit_delay' ),
			'submit_delay_seconds'    => max( 1, intval( get_option( AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms, '2500' ), 10 ) / 1000 ),
			'bunny_external_sync'     => $bunny_external_sync,
			'bunny_threshold'         => max( 1, intval( get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_threshold, '10' ), 10 ) ),
			'bunny_ttl_minutes'       => max( 1, intval( get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_ttl_minutes, '60' ), 10 ) ),
		);
	}

	private static function active_integrations() {
		if ( ASFW_Feature_Registry::kill_switch_active() ) {
			return array();
		}

		$integrations = array();
		foreach ( ASFW_Feature_Registry::get_integration_features() as $feature ) {
			if ( empty( $feature['option'] ) || empty( $feature['label'] ) ) {
				continue;
			}

			$mode = trim( (string) get_option( $feature['option'], '' ) );
			if ( in_array( $mode, array( 'captcha', 'shortcode' ), true ) ) {
				$integrations[] = (string) $feature['label'];
			}
		}

		return array_values( array_unique( $integrations ) );
	}

	private static function legal_basis_text( $legal_basis ) {
		if ( self::LEGAL_BASIS_CONSENT === $legal_basis ) {
			return __( 'The processing is based on your consent pursuant to Article 6(1)(a) GDPR. You may withdraw your consent at any time without affecting the lawfulness of processing carried out based on consent before withdrawal.', 'anti-spam-for-wordpress' );
		}

		if ( self::LEGAL_BASIS_LEGITIMATE === $legal_basis ) {
			return __( 'The processing is based on our legitimate interests pursuant to Article 6(1)(f) GDPR in protecting our website, forms, and users from spam and abuse. You may object to processing based on legitimate interests where the legal requirements are met.', 'anti-spam-for-wordpress' );
		}

		return __( '[Review required: Please insert or verify the applicable legal basis for this anti-spam processing, for example consent under Article 6(1)(a) GDPR or legitimate interests under Article 6(1)(f) GDPR, depending on your website implementation.]', 'anti-spam-for-wordpress' );
	}

	private static function core_processing_text( array $flags ) {
		$parts = array();

		if ( ! empty( $flags['active_integrations'] ) ) {
			$parts[] = sprintf(
				/* translators: %s is a comma-separated list of protected form integrations. */
				__( 'Protection is currently configured for: %s.', 'anti-spam-for-wordpress' ),
				implode( ', ', $flags['active_integrations'] )
			);
		} elseif ( ! empty( $flags['kill_switch'] ) ) {
			$parts[] = __( 'The plugin kill switch is currently active, so protection is disabled until it is turned on again.', 'anti-spam-for-wordpress' );
		} else {
			$parts[] = __( 'No protected form integration is currently enabled.', 'anti-spam-for-wordpress' );
		}

		$identity = ! empty( $flags['ip_user_agent_binding'] )
			? __( 'IP address and user agent', 'anti-spam-for-wordpress' )
			: __( 'IP address', 'anti-spam-for-wordpress' );

		$parts[] = sprintf(
			/* translators: %s describes the visitor data used for local fingerprints. */
			__( 'To distinguish regular users from automated submissions, the plugin processes the visitor %s to create a local, server-side fingerprint.', 'anti-spam-for-wordpress' ),
			$identity
		);
		$parts[] = __( 'The plugin issues signed proof-of-work challenges and guard tokens that can include timestamps, request context, challenge identifiers, signatures, and short-lived verification state stored in WordPress transients.', 'anti-spam-for-wordpress' );

		if ( ! empty( $flags['trusted_proxies'] ) ) {
			$parts[] = __( 'When a request comes through a configured trusted proxy, forwarded client IP headers may be used to identify the visitor IP address for anti-spam checks.', 'anti-spam-for-wordpress' );
		}

		if ( ! empty( $flags['honeypot'] ) ) {
			$parts[] = __( 'A hidden honeypot field may be added to protected forms to detect simple automated submissions.', 'anti-spam-for-wordpress' );
		}

		if ( $flags['min_submit_time_seconds'] > 0 ) {
			$parts[] = sprintf(
				/* translators: %s is the configured minimum submit time in seconds. */
				__( 'Submissions may be rejected if they are completed in less than %s seconds.', 'anti-spam-for-wordpress' ),
				self::format_seconds( $flags['min_submit_time_seconds'] )
			);
		}

		if ( ! empty( $flags['rate_limits'] ) ) {
			$parts[] = sprintf(
				/* translators: %s is the rate-limit window in seconds. */
				__( 'Challenge requests and failed verifications may be rate-limited during a window of approximately %s seconds.', 'anti-spam-for-wordpress' ),
				self::format_seconds( $flags['rate_limit_window'] )
			);
		}

		return implode( ' ', $parts );
	}

	private static function storage_text( array $flags ) {
		if ( empty( $flags['event_logging'] ) ) {
			return __( 'Event logging is currently disabled. Apart from short-lived challenge, guard, and rate-limit state, the plugin does not keep a long-term local event log for these anti-spam checks.', 'anti-spam-for-wordpress' );
		}

		return sprintf(
			/* translators: %d is the local event log retention period in days. */
			__( 'When event logging is enabled, verification events, rate-limit events, guard checks, and settings changes are stored locally for approximately %d days. These logs may include hashed IP addresses, hashed user identifiers, hashed email addresses when email checks match, and technical metadata such as context, decision, feature, and error codes. Hashing reduces direct identifiability but may still qualify as personal data depending on the circumstances.', 'anti-spam-for-wordpress' ),
			intval( $flags['event_retention_days'], 10 )
		);
	}

	private static function disposable_email_text( array $flags ) {
		$text = __( 'If disposable email detection is active for a protected form, submitted email addresses may be checked locally against a disposable-domain list. Matching email addresses may be blocked or logged depending on the configured mode.', 'anti-spam-for-wordpress' );

		if ( ! empty( $flags['disposable_auto_refresh'] ) ) {
			$text .= ' ' . __( 'The disposable-domain list may be refreshed from the configured remote source during scheduled maintenance; submitted visitor data is not sent for that list refresh.', 'anti-spam-for-wordpress' );
		}

		return $text;
	}

	private static function external_transfer_text( array $flags ) {
		if ( empty( $flags['bunny_external_sync'] ) ) {
			return __( 'With the current plugin settings, this suggested text does not include an external anti-spam data transfer for CAPTCHA-style verification. Bunny Shield remote synchronization is not active.', 'anti-spam-for-wordpress' );
		}

		return sprintf(
			/* translators: 1: Bunny escalation threshold, 2: local dedupe TTL in minutes. */
			__( 'If repeated abuse signals are detected, IP addresses associated with failed or rate-limited attempts may be transmitted to Bunny Shield to update a remote access list. This can happen after approximately %1$d local abuse signals, and local deduplication state is kept for approximately %2$d minutes.', 'anti-spam-for-wordpress' ),
			intval( $flags['bunny_threshold'], 10 ),
			intval( $flags['bunny_ttl_minutes'], 10 )
		);
	}

	private static function summary( array $flags ) {
		$summary = __( 'Generated from the current anti-spam, logging, visitor identification, and external sync settings.', 'anti-spam-for-wordpress' );

		if ( ! empty( $flags['bunny_external_sync'] ) ) {
			return $summary . ' ' . __( 'It includes Bunny Shield external IP synchronization.', 'anti-spam-for-wordpress' );
		}

		return $summary . ' ' . __( 'It currently describes local/self-hosted anti-spam processing only.', 'anti-spam-for-wordpress' );
	}

	private static function format_seconds( $seconds ) {
		$seconds = (float) $seconds;
		if ( floor( $seconds ) === $seconds ) {
			return (string) intval( $seconds, 10 );
		}

		return rtrim( rtrim( number_format( $seconds, 1, '.', '' ), '0' ), '.' );
	}
}
