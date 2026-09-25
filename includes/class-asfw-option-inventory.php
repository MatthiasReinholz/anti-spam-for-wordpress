<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Plugin-owned options, usable without booting the plugin during uninstall. */
final class ASFW_Option_Inventory {
	public static function names() {
		$options = array(
			'asfw_secret',
			'asfw_widget_appearance',
			'asfw_widget_layout',
			'asfw_content_heuristics_strict',
			'asfw_disposable_email_last_refresh',
			'asfw_trusted_proxy_header',
			'asfw_site_initialized',
			'asfw_complexity',
			'asfw_expires',
			'asfw_auto',
			'asfw_floating',
			'asfw_delay',
			'asfw_hidefooter',
			'asfw_hidelogo',
			'asfw_footer_text',
			'asfw_privacy_page',
			'asfw_privacy_url',
			'asfw_privacy_new_tab',
			'asfw_privacy_legal_basis',
			'asfw_lazy',
			'asfw_rate_limit_max_challenges',
			'asfw_rate_limit_max_failures',
			'asfw_rate_limit_window',
			'asfw_honeypot',
			'asfw_min_submit_time',
			'asfw_visitor_binding',
			'asfw_trusted_proxies',
			'asfw_kill_switch',
			'asfw_bunny_enabled',
			'asfw_bunny_api_key',
			'asfw_bunny_shield_zone_id',
			'asfw_bunny_access_list_id',
			'asfw_bunny_dry_run',
			'asfw_bunny_fail_open',
			'asfw_bunny_threshold',
			'asfw_bunny_dedupe_window',
			'asfw_feature_bunny_shield_enabled',
			'asfw_feature_bunny_shield_dry_run',
			'asfw_feature_bunny_shield_fail_open',
			'asfw_feature_bunny_shield_api_key',
			'asfw_feature_bunny_shield_zone_id',
			'asfw_feature_bunny_shield_access_list_id',
			'asfw_feature_bunny_shield_threshold',
			'asfw_feature_bunny_shield_ttl_minutes',
			'asfw_feature_bunny_shield_action',
			'asfw_feature_submit_delay_ms',
			'asfw_integration_coblocks',
			'asfw_integration_contact_form_7',
			'asfw_integration_custom',
			'asfw_integration_elementor',
			'asfw_integration_enfold_theme',
			'asfw_integration_formidable',
			'asfw_integration_forminator',
			'asfw_integration_gravityforms',
			'asfw_integration_woocommerce_login',
			'asfw_integration_woocommerce_register',
			'asfw_integration_woocommerce_reset_password',
			'asfw_integration_html_forms',
			'asfw_integration_wordpress_login',
			'asfw_integration_wordpress_register',
			'asfw_integration_wordpress_reset_password',
			'asfw_integration_wordpress_comments',
			'asfw_integration_wpdiscuz',
			'asfw_integration_wpforms',
			'asfw_events_db_version',
			'asfw_event_logging_retention_days',
			'asfw_event_retention_days',
			'asfw_last_maintenance_run',
			'asfw_migration_completed',
			'asfw_disposable_email_domains',
			'asfw_disposable_email_domains_refreshed_at',
			'asfw_disposable_email_auto_refresh',
			'asfw_content_heuristics_enabled',
		);

		foreach ( array( 'event_logging', 'disposable_email', 'content_heuristics', 'ip_feeds', 'bunny_shield', 'math_challenge', 'submit_delay' ) as $feature ) {
			$options[] = 'asfw_feature_' . $feature . '_enabled';
			$options[] = 'asfw_feature_' . $feature . '_mode';
			$options[] = 'asfw_feature_' . $feature . '_scope_mode';
			$options[] = 'asfw_feature_' . $feature . '_contexts';
			$options[] = 'asfw_feature_' . $feature . '_background_enabled';
		}

		return array_unique( $options );
	}
}
