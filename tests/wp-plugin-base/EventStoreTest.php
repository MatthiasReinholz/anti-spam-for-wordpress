<?php
declare(strict_types=1);

final class EventStoreTest extends AsfwPluginTestCase
{
    public function test_activation_installs_the_events_schema_and_db_version(): void
    {
        $store = ASFW_Control_Plane::store();
        $schema = $store->get_schema_sql();

        $this->assertInstanceOf(ASFW_Event_Store::class, $store);
        $this->assertSame((string) ASFW_Event_Store::DB_VERSION, (string) get_option(ASFW_Event_Store::OPTION_DB_VERSION, '0'));
        $this->assertSame('30', (string) get_option(ASFW_Event_Store::OPTION_RETENTION_DAYS, ''));
        $this->assertNotEmpty($GLOBALS['asfw_test_dbdelta_queries']);
        $this->assertStringContainsString('CREATE TABLE', $GLOBALS['asfw_test_dbdelta_queries'][0]);
        $this->assertStringContainsString('asfw_events', $GLOBALS['asfw_test_dbdelta_queries'][0]);
        $this->assertStringContainsString('created_at datetime not null', strtolower($schema));
        $this->assertStringContainsString('event_type varchar(32) not null', strtolower($schema));
        $this->assertStringContainsString("context varchar(128) not null default ''", strtolower($schema));
        $this->assertStringContainsString("feature varchar(64) not null default ''", strtolower($schema));
        $this->assertStringContainsString("decision varchar(16) not null default ''", strtolower($schema));
        $this->assertStringContainsString('ip_hash char(64) null', strtolower($schema));
        $this->assertStringContainsString('email_hash char(64) null', strtolower($schema));
        $this->assertStringContainsString('details longtext null', strtolower($schema));
        $this->assertStringContainsString('key created_at (created_at)', strtolower($schema));
        $this->assertStringContainsString('key context (context(64))', strtolower($schema));
        $this->assertStringContainsString('key feature (feature)', strtolower($schema));
        $this->assertNotEmpty($GLOBALS['asfw_test_cron_events']);
        $this->assertArrayHasKey(ASFW_Maintenance::HOOK, $GLOBALS['asfw_test_cron_events']);
        $this->assertContains('disposable_list_refreshed', ASFW_Event_Store::CONTRACT_EVENT_TYPES);
        $this->assertContains('bunny_sync_success', ASFW_Event_Store::CONTRACT_EVENT_TYPES);
        $this->assertContains('bunny_sync_failed', ASFW_Event_Store::CONTRACT_EVENT_TYPES);
        $this->assertContains('bunny_dry_run', ASFW_Event_Store::CONTRACT_EVENT_TYPES);
        $this->assertContains('guard_check', ASFW_Event_Store::CONTRACT_EVENT_TYPES);
    }

    public function test_install_migrates_legacy_retention_setting_and_get_retention_days_falls_back_to_default(): void
    {
        delete_option(ASFW_Event_Store::OPTION_RETENTION_DAYS);
        update_option(ASFW_Event_Store::OPTION_RETENTION_DAYS_LEGACY, '14');

        $store = ASFW_Control_Plane::store();
        $store->install();
        $this->assertSame('14', (string) get_option(ASFW_Event_Store::OPTION_RETENTION_DAYS, ''));
        $this->assertSame(14, $store->get_retention_days());

        update_option(ASFW_Event_Store::OPTION_RETENTION_DAYS, 'bogus');
        $this->assertSame(30, $store->get_retention_days());
    }

    public function test_event_logger_hashes_pii_and_omits_raw_identity_values(): void
    {
        update_option('asfw_feature_event_logging_enabled', 1);
        update_option('asfw_feature_event_logging_mode', 'log');
        update_option(AntiSpamForWordPressPlugin::$option_visitor_binding, 'ip_ua');
        $_SERVER['REMOTE_ADDR'] = '198.51.100.77';
        $_SERVER['HTTP_USER_AGENT'] = 'ASFW PHPUnit Test Agent';

        $this->seedPostedWidget('contact-form-7');
        $this->assertTrue($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, 'contact-form-7'));

        $events = ASFW_Control_Plane::store()->fetch_events(
            array(
                'type' => 'verify_passed',
            )
        );

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame('core', $event['feature']);
        $this->assertSame('core', $event['module_name']);
        $this->assertSame(64, strlen((string) $event['ip_hash']));
        $this->assertSame(64, strlen((string) $event['actor_hash']));
        $this->assertSame('contact-form-7', $event['context']);
        $this->assertSame('contact-form-7', $event['event_context']);
        $this->assertSame('success', $event['decision']);
        $this->assertSame('success', $event['event_status']);
        $this->assertStringNotContainsString('198.51.100.77', $event['details']);
        $this->assertStringNotContainsString('ASFW PHPUnit Test Agent', $event['details']);

        $details = json_decode((string) $event['details'], true);
        $this->assertIsArray($details);
        $this->assertSame('asfw', $details['field_name']);
        $this->assertTrue($details['success']);
    }

    public function test_event_logger_skips_writes_when_logging_disabled_or_kill_switch_enabled(): void
    {
        update_option('asfw_feature_event_logging_enabled', 0);
        update_option('asfw_feature_event_logging_mode', 'off');

        $this->seedPostedWidget('contact-form-7');
        $this->assertTrue($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, 'contact-form-7'));
        $this->assertSame(0, ASFW_Control_Plane::store()->count_events(array('type' => 'verify_passed')));

        update_option('asfw_feature_event_logging_enabled', 1);
        update_option('asfw_feature_event_logging_mode', 'log');
        update_option(AntiSpamForWordPressPlugin::$option_kill_switch, 1);

        $this->seedPostedWidget('contact-form-7');
        $this->assertTrue($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, 'contact-form-7'));
        $this->assertSame(0, ASFW_Control_Plane::store()->count_events(array('type' => 'verify_passed')));
    }

    public function test_canonical_type_filters_include_legacy_rows(): void
    {
        $store = ASFW_Control_Plane::store();
        $table = $store->get_table_name();

        $GLOBALS['asfw_test_db_tables'][$table][] = array(
            'id' => 1,
            'event_type' => 'verification_failed',
            'created_at' => gmdate('Y-m-d H:i:s'),
            'context' => 'contact-form-7',
            'feature' => 'core',
            'decision' => 'failed',
            'ip_hash' => $store->hash_value('198.51.100.77', 'actor'),
            'email_hash' => '',
            'details' => '{}',
        );

        $events = $store->fetch_events(
            array(
                'type'     => 'verify_failed',
                'feature'  => 'core',
                'decision' => 'failed',
                'context'  => 'contact-form-7',
            )
        );

        $this->assertCount(1, $events);
        $this->assertSame('verification_failed', $events[0]['event_type']);
        $this->assertSame('contact-form-7', $events[0]['context']);
        $this->assertSame('core', $events[0]['feature']);
        $this->assertSame('failed', $events[0]['decision']);
        $this->assertSame('contact-form-7', $events[0]['event_context']);
        $this->assertSame('core', $events[0]['module_name']);
        $this->assertSame(1, $store->count_events(array('type' => 'verify_failed')));
        $this->assertSame(array('verify_failed' => 1), $store->get_type_counts());
    }

    public function test_settings_change_logging_uses_contract_events_without_storing_secret_values(): void
    {
        update_option('asfw_feature_event_logging_enabled', 1);
        update_option('asfw_feature_event_logging_mode', 'log');

        do_action(
            'asfw_settings_changed',
            array(
                'asfw_secret' => array(
                    'old' => 'old-secret',
                    'new' => 'new-secret',
                ),
                'asfw_feature_content_heuristics_enabled' => array(
                    'old' => 1,
                    'new' => 0,
                ),
            ),
            42
        );

        $settingsEvents = ASFW_Control_Plane::store()->fetch_events(array('type' => 'settings_changed'));
        $featureEvents = ASFW_Control_Plane::store()->fetch_events(array('type' => 'feature_runtime_disabled'));

        $this->assertCount(1, $settingsEvents);
        $this->assertCount(1, $featureEvents);
        $this->assertSame('settings', $settingsEvents[0]['context']);
        $this->assertSame('core', $settingsEvents[0]['feature']);
        $this->assertSame('updated', $settingsEvents[0]['decision']);
        $this->assertStringNotContainsString('old-secret', $settingsEvents[0]['details']);
        $this->assertStringNotContainsString('new-secret', $settingsEvents[0]['details']);

        $details = json_decode((string) $settingsEvents[0]['details'], true);
        $this->assertSame(array('asfw_secret', 'asfw_feature_content_heuristics_enabled'), $details['options']);

        $this->assertSame('settings', $featureEvents[0]['context']);
        $this->assertSame('content_heuristics', $featureEvents[0]['feature']);
        $this->assertSame('disabled', $featureEvents[0]['decision']);

        $featureDetails = json_decode((string) $featureEvents[0]['details'], true);
        $this->assertSame('content_heuristics', $featureDetails['feature']);
        $this->assertSame('disabled', $featureDetails['reason']);
    }

    public function test_disposable_email_runtime_block_records_a_contract_event(): void
    {
        update_option('asfw_feature_disposable_email_enabled', 1);
        update_option('asfw_feature_disposable_email_mode', 'block');
        update_option('asfw_feature_disposable_email_scope_mode', 'all');

        $this->seedPostedWidget('woocommerce:register');
        $_POST['email'] = 'clean@example.com';
        $_POST['billing_email'] = 'spam@trashmail.com';

        $this->assertFalse($this->plugin()->verify(asfw_get_posted_payload('asfw'), null, 'woocommerce:register'));

        $events = ASFW_Control_Plane::store()->fetch_events(array('type' => 'disposable_email_hit'));

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame('disposable-email', $event['feature']);
        $this->assertSame('blocked', $event['decision']);
        $this->assertSame('woocommerce:register', $event['context']);
        $this->assertSame(64, strlen((string) $event['email_hash']));

        $details = json_decode((string) $event['details'], true);
        $this->assertIsArray($details);
        $this->assertSame(array('email', 'billing_email'), $details['candidate_fields']);
        $this->assertSame(array('billing_email'), $details['matched_fields']);
        $this->assertSame('block', $details['mode']);
    }

    public function test_fetch_events_and_count_events_support_date_range_filters(): void
    {
        $store = ASFW_Control_Plane::store();
        $table = $store->get_table_name();

        $GLOBALS['asfw_test_db_tables'][$table][] = array(
            'id' => 1,
            'event_type' => 'verify_passed',
            'created_at' => '2025-01-01 10:00:00',
            'context' => 'contact-form-7',
            'feature' => 'core',
            'decision' => 'success',
            'ip_hash' => null,
            'email_hash' => null,
            'details' => '{}',
        );
        $GLOBALS['asfw_test_db_tables'][$table][] = array(
            'id' => 2,
            'event_type' => 'verify_passed',
            'created_at' => '2026-01-01 10:00:00',
            'context' => 'contact-form-7',
            'feature' => 'core',
            'decision' => 'success',
            'ip_hash' => null,
            'email_hash' => null,
            'details' => '{}',
        );

        $events = $store->fetch_events(
            array(
                'type' => 'verify_passed',
                'date_from' => '2025-12-31',
                'date_to' => '2026-01-02',
                'limit' => 50,
                'offset' => 0,
            )
        );

        $this->assertCount(1, $events);
        $this->assertSame('2026-01-01 10:00:00', $events[0]['created_at']);
        $this->assertSame(
            1,
            $store->count_events(
                array(
                    'type' => 'verify_passed',
                    'date_from' => '2025-12-31',
                    'date_to' => '2026-01-02',
                )
            )
        );
    }
}
