<?php
declare(strict_types=1);

final class MaintenanceAndModulesTest extends AsfwPluginTestCase
{
    public function test_maintenance_is_scheduled_on_activation_and_unscheduled_on_deactivate(): void
    {
        $this->assertArrayHasKey(ASFW_Maintenance::HOOK, $GLOBALS['asfw_test_cron_events']);

        asfw_deactivate();

        $this->assertArrayNotHasKey(ASFW_Maintenance::HOOK, $GLOBALS['asfw_test_cron_events']);
    }

    public function test_maintenance_schedule_is_idempotent_and_recreated_on_reactivation(): void
    {
        $maintenance = ASFW_Control_Plane::maintenance();
        $this->assertInstanceOf(ASFW_Maintenance::class, $maintenance);

        $maintenance->maybe_schedule();
        $first = $GLOBALS['asfw_test_cron_events'][ASFW_Maintenance::HOOK];
        $maintenance->maybe_schedule();
        $second = $GLOBALS['asfw_test_cron_events'][ASFW_Maintenance::HOOK];

        $this->assertSame($first, $second);
        $this->assertSame('daily', $second['recurrence']);

        asfw_deactivate();
        $this->assertArrayNotHasKey(ASFW_Maintenance::HOOK, $GLOBALS['asfw_test_cron_events']);

        asfw_activate();
        $this->assertArrayHasKey(ASFW_Maintenance::HOOK, $GLOBALS['asfw_test_cron_events']);
        $this->assertSame('daily', $GLOBALS['asfw_test_cron_events'][ASFW_Maintenance::HOOK]['recurrence']);
    }

    public function test_maintenance_completed_action_emits_summary_payload(): void
    {
        $captured = null;
        $listener = static function (array $summary) use (&$captured): void {
            $captured = $summary;
        };
        add_action('asfw_maintenance_completed', $listener, 10, 1);

        try {
            $summary = ASFW_Control_Plane::maintenance()->run();
        } finally {
            remove_action('asfw_maintenance_completed', $listener, 10);
        }

        $this->assertIsArray($captured);
        $this->assertSame($summary, $captured);
        $this->assertArrayHasKey('pruned', $captured);
        $this->assertArrayHasKey('refreshed', $captured);
    }

    public function test_daily_maintenance_uses_bundled_disposable_list_by_default(): void
    {
        delete_option(ASFW_Disposable_Email_Module::OPTION_AUTO_REFRESH);
        delete_option(ASFW_Disposable_Email_Module::OPTION_LAST_REFRESH);

        $summary = ASFW_Control_Plane::maintenance()->run();

        $this->assertFalse((bool) get_option(ASFW_Disposable_Email_Module::OPTION_AUTO_REFRESH));
        $this->assertSame('', ASFW_Control_Plane::disposable_module()->get_last_refresh());
        $this->assertGreaterThan(0, $summary['refreshed']['disposable_domains']);
        $this->assertCount(0, $GLOBALS['asfw_test_http_requests']);
    }

    public function test_daily_maintenance_prunes_old_events_and_refreshes_disposable_list(): void
    {
        $store = ASFW_Control_Plane::store();
        $table = $store->get_table_name();

        $GLOBALS['asfw_test_db_tables'][$table][] = array(
            'id' => 1,
            'event_type' => 'verify_failed',
            'created_at' => gmdate('Y-m-d H:i:s', time() - (40 * 86400)),
            'context' => 'contact-form-7',
            'feature' => 'core',
            'decision' => 'failed',
            'ip_hash' => $store->hash_value('198.51.100.77', 'actor'),
            'email_hash' => '',
            'details' => '{}',
        );

		update_option('asfw_feature_disposable_email_background_enabled', 1);
		delete_option(ASFW_Disposable_Email_Module::OPTION_LAST_REFRESH);

        $summary = ASFW_Control_Plane::maintenance()->run();

        $this->assertSame(1, $summary['pruned']);
        $this->assertNotSame('', ASFW_Control_Plane::disposable_module()->get_last_refresh());
        $this->assertSame(0, $store->count_events(array('type' => 'verify_failed')));
        $this->assertGreaterThanOrEqual(1, $store->count_events(array('type' => 'disposable_list_refreshed')));
    }

    public function test_remote_disposable_refresh_failure_preserves_existing_cache_and_timestamp(): void
    {
        $module = ASFW_Control_Plane::disposable_module();
        $this->assertInstanceOf(ASFW_Disposable_Email_Module::class, $module);

        update_option(ASFW_Disposable_Email_Module::OPTION_DOMAINS, array('cached.example', 'trashmail.com'));
        update_option(ASFW_Disposable_Email_Module::OPTION_LAST_REFRESH, '2026-01-01 00:00:00');

        asfw_test_queue_http_response(new WP_Error('asfw_http_timeout', 'Timeout'));

        $refreshed = $module->refresh_from_source(true);

        $this->assertSame(array('cached.example', 'trashmail.com'), $refreshed);
        $this->assertSame(array('cached.example', 'trashmail.com'), get_option(ASFW_Disposable_Email_Module::OPTION_DOMAINS));
        $this->assertSame('2026-01-01 00:00:00', (string) get_option(ASFW_Disposable_Email_Module::OPTION_LAST_REFRESH));

        $events = ASFW_Control_Plane::store()->fetch_events(array('type' => 'disposable_list_refreshed'));
        $this->assertNotEmpty($events);
        $this->assertSame('failed', $events[0]['decision']);
        $details = json_decode((string) $events[0]['details'], true);
        $this->assertIsArray($details);
        $this->assertSame('remote_failed', $details['source']);
    }

    public function test_content_heuristics_module_flags_spammy_content(): void
    {
		update_option('asfw_feature_content_heuristics_enabled', 1);
		update_option('asfw_feature_content_heuristics_mode', 'log');
		update_option('asfw_feature_event_logging_enabled', 1);
        update_option('asfw_feature_event_logging_mode', 'log');

        $_POST['message'] = 'Buy now cheap crypto backlinks https://spam.example https://spam2.example';
        $_POST['email'] = 'spam@trashmail.com';

        $this->seedPostedWidget('contact-form-7');
        $this->assertTrue($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, 'contact-form-7'));

        $events = ASFW_Control_Plane::store()->fetch_events(array('type' => 'content_heuristic_hit'));

        $this->assertNotEmpty($events);
        $this->assertSame('content-heuristics', $events[0]['feature']);
        $this->assertSame('flagged', $events[0]['decision']);
        $this->assertSame('contact-form-7', $events[0]['context']);
        $details = json_decode((string) $events[0]['details'], true);
        $this->assertIsArray($details);
        $this->assertGreaterThanOrEqual(4, (int) $details['score']);
        $this->assertContains('urls:message', $details['reasons']);
    }

    public function test_content_heuristics_does_not_record_events_when_event_logging_is_disabled(): void
    {
		update_option('asfw_feature_content_heuristics_enabled', 1);
		update_option('asfw_feature_content_heuristics_mode', 'log');
		update_option('asfw_feature_event_logging_enabled', 0);
        update_option('asfw_feature_event_logging_mode', 'off');

        $_POST['message'] = 'Buy now cheap crypto backlinks https://spam.example https://spam2.example';
        $_POST['email'] = 'spam@trashmail.com';

        $this->seedPostedWidget('contact-form-7');
        $this->assertTrue($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, 'contact-form-7'));

        $events = ASFW_Control_Plane::store()->fetch_events(array('type' => 'content_heuristic_hit'));
        $this->assertSame(array(), $events);
    }

    public function test_disposable_email_background_refresh_stays_explicit_and_separate_from_runtime_enablement(): void
    {
        update_option('asfw_feature_disposable_email_enabled', 1);
        update_option('asfw_feature_disposable_email_mode', 'log');
        update_option(ASFW_Disposable_Email_Module::OPTION_AUTO_REFRESH, 0);
        delete_option(ASFW_Disposable_Email_Module::OPTION_LAST_REFRESH);

        $count = ASFW_Control_Plane::disposable_module()->maybe_refresh();

        $this->assertSame(count(ASFW_Control_Plane::disposable_module()->get_domains()), $count);
        $this->assertSame('', ASFW_Control_Plane::disposable_module()->get_last_refresh());
        $this->assertCount(0, $GLOBALS['asfw_test_http_requests']);
    }

    public function test_content_heuristics_disposable_reason_is_bridged_into_event_log(): void
    {
        update_option('asfw_feature_event_logging_enabled', 1);
        update_option('asfw_feature_event_logging_mode', 'log');
        update_option('asfw_feature_event_logging_scope_mode', 'all');
        $_POST['email'] = 'spam@trashmail.com';

        do_action(
            'asfw_content_heuristics_flagged',
            array(
                'reasons' => array('disposable_email:email', 'urls:message'),
            ),
            'contact-form-7',
            'asfw'
        );

        $events = ASFW_Control_Plane::store()->fetch_events(array('type' => 'disposable_email_hit'));
        $this->assertCount(1, $events);
        $this->assertSame('content-heuristics', $events[0]['feature']);
        $this->assertSame('matched', $events[0]['decision']);
        $this->assertSame('contact-form-7', $events[0]['context']);
        $this->assertSame(64, strlen((string) $events[0]['email_hash']));
    }

    public function test_maintenance_run_without_disposable_module_still_emits_summary(): void
    {
        $store = ASFW_Control_Plane::store();
        $maintenance = new ASFW_Maintenance($store, null);

        $captured = null;
        $listener = static function (array $summary) use (&$captured): void {
            $captured = $summary;
        };
        add_action('asfw_maintenance_completed', $listener, 10, 1);

        try {
            $summary = $maintenance->run();
        } finally {
            remove_action('asfw_maintenance_completed', $listener, 10);
        }

        $this->assertSame(0, $summary['refreshed']['disposable_domains']);
        $this->assertSame($summary, $captured);
    }
}
