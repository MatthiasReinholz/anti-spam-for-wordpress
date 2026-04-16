<?php
declare(strict_types=1);

final class AdminPagesTest extends AsfwPluginTestCase
{
    public function test_events_and_analytics_pages_render_read_only_data(): void
    {
        update_option('asfw_feature_event_logging_enabled', 1);
        update_option('asfw_feature_event_logging_mode', 'log');
        update_option('asfw_feature_event_logging_scope_mode', 'all');

        $store = ASFW_Control_Plane::store();
        $store->record_event(
            'challenge_issued',
            array(
                'event_status' => 'issued',
                'event_context' => 'contact-form-7',
                'module_name' => 'core',
                'details' => array(
                    'algorithm' => 'SHA-256',
                ),
            )
        );
        $store->record_event(
            'verify_passed',
            array(
                'event_status' => 'success',
                'event_context' => 'contact-form-7',
                'module_name' => 'core',
                'details' => array(
                    'field_name' => 'asfw',
                    'success' => true,
                ),
            )
        );

        $pages = new ASFW_Admin_Pages(
            $store,
            ASFW_Control_Plane::disposable_module(),
            ASFW_Control_Plane::content_module()
        );

        ob_start();
        $pages->render_events_page();
        $events_html = (string) ob_get_clean();

        ob_start();
        $pages->render_analytics_page();
        $analytics_html = (string) ob_get_clean();

		$this->assertStringContainsString('Events', $events_html);
		$this->assertStringContainsString('challenge_issued', $events_html);
		$this->assertStringContainsString('Apply filters', $events_html);
		$this->assertStringContainsString('Retention window', $events_html);
		$this->assertStringContainsString('Analytics', $analytics_html);
		$this->assertStringContainsString('Challenges issued by day', $analytics_html);
		$this->assertStringContainsString('Verify pass/fail by day', $analytics_html);
		$this->assertStringContainsString('Top contexts', $analytics_html);
    }

    public function test_events_page_displays_canonical_names_for_legacy_rows(): void
    {
        update_option('asfw_feature_event_logging_enabled', 1);
        update_option('asfw_feature_event_logging_mode', 'log');
        update_option('asfw_feature_event_logging_scope_mode', 'all');

        $store = ASFW_Control_Plane::store();
        $table = $store->get_table_name();

        $GLOBALS['asfw_test_db_tables'][$table][] = array(
            'id' => 1,
            'event_type' => 'verification_passed',
            'event_status' => 'success',
            'event_context' => 'contact-form-7',
            'module_name' => 'core',
            'actor_hash' => $store->hash_value('198.51.100.77', 'actor'),
            'subject_hash' => '',
            'email_hash' => '',
            'details' => '{"field_name":"asfw","success":true}',
            'created_at_gmt' => gmdate('Y-m-d H:i:s'),
        );

        $pages = new ASFW_Admin_Pages($store, ASFW_Control_Plane::disposable_module(), ASFW_Control_Plane::content_module());

        ob_start();
        $pages->render_events_page();
        $events_html = (string) ob_get_clean();

        $this->assertStringContainsString('verify_passed', $events_html);
        $this->assertStringNotContainsString('verification_passed', $events_html);
    }

    public function test_pages_show_logging_disabled_notice_when_event_logging_is_off(): void
    {
        update_option('asfw_feature_event_logging_enabled', 0);
        update_option('asfw_feature_event_logging_mode', 'off');

        $store = ASFW_Control_Plane::store();
        $pages = new ASFW_Admin_Pages($store, ASFW_Control_Plane::disposable_module(), ASFW_Control_Plane::content_module());

        ob_start();
        $pages->render_events_page();
        $events_html = (string) ob_get_clean();

        ob_start();
        $pages->render_analytics_page();
        $analytics_html = (string) ob_get_clean();

        $this->assertStringContainsString('Event logging is currently disabled', $events_html);
        $this->assertStringContainsString('Event logging is currently disabled', $analytics_html);
    }
}
