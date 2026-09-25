<?php
declare(strict_types=1);

final class HardeningRegressionTest extends AsfwPluginTestCase
{
    public function test_reactivation_preserves_modern_values_and_off_policy(): void
    {
        update_option('altcha_secret', 'obsolete-secret');
        update_option('altcha_integration_custom', 'captcha');
        update_option('asfw_integration_custom', '');
        update_option('asfw_bunny_enabled', true);
        update_option('asfw_feature_bunny_shield_mode', 'off');
        update_option('asfw_bunny_fail_open', true);
        update_option('asfw_feature_bunny_shield_fail_open', false);
        asfw_activate();
        asfw_seed_control_plane_defaults();
        $this->assertSame('test-secret', get_option('asfw_secret'));
        $this->assertSame('', get_option('asfw_integration_custom'));
        $this->assertSame('off', get_option('asfw_feature_bunny_shield_mode'));
        $this->assertFalse($this->plugin()->get_bunny_fail_open());
        delete_option('asfw_feature_bunny_shield_fail_open');
        $this->assertTrue($this->plugin()->get_bunny_fail_open());
    }

    public function test_legacy_values_migrate_only_into_missing_targets(): void
    {
        delete_option('asfw_migration_completed');
        delete_option('asfw_complexity');
        update_option('altcha_secret', 'obsolete-secret');
        update_option('altcha_complexity', 'high');
        asfw_activate();
        $this->assertSame('test-secret', get_option('asfw_secret'));
        $this->assertSame('high', get_option('asfw_complexity'));
    }

    public function test_network_batches_initialize_unique_sites_and_restore_context(): void
    {
        $GLOBALS['asfw_test_multisite'] = true;
        $GLOBALS['asfw_test_sites'] = range(1, 205);
        asfw_activate(true);
        $this->assertSame(1, get_current_blog_id());
        $this->assertSame(array(1, 100), $GLOBALS['asfw_test_cron_events']['asfw_initialize_network_batch']['args']);
        asfw_initialize_network_batch(1, 100);
        asfw_initialize_network_batch(1, 200);
        $secrets = array();
        foreach ($GLOBALS['asfw_test_sites'] as $id) {
            switch_to_blog($id);
            try {
                $secrets[] = get_option('asfw_secret', '');
                $this->assertSame('1', get_option('asfw_site_initialized'));
                $this->assertNotFalse(wp_next_scheduled('asfw_daily_maintenance'));
            } finally { restore_current_blog(); }
        }
        $this->assertCount(205, array_unique($secrets));
        asfw_deactivate(true);
        $this->assertSame(1, get_current_blog_id());
        $this->assertFalse(wp_next_scheduled('asfw_initialize_network_batch', array(1, 200)));
        foreach ($GLOBALS['asfw_test_sites'] as $id) {
            switch_to_blog($id);
            try { $this->assertFalse(wp_next_scheduled('asfw_daily_maintenance')); }
            finally { restore_current_blog(); }
        }
    }

    public function test_site_iteration_restores_context_after_exception(): void
    {
        $GLOBALS['asfw_test_sites'] = array(2);
        try {
            asfw_visit_network_sites(1, 0, static function () { throw new RuntimeException('fixture'); });
            $this->fail('Expected fixture exception');
        } catch (RuntimeException $error) {
            $this->assertSame('fixture', $error->getMessage());
        }
        $this->assertSame(1, get_current_blog_id());
    }

    public function test_expired_security_state_drains_in_bounded_scheduled_batches(): void
    {
        $store = new ASFW_Atomic_State_Store();
        $rows = &$GLOBALS['asfw_test_atomic_rows']['wp_options'];
        for ($i = 0; $i < 2005; ++$i) {
            $rows[$store->option_name('expired-' . $i)] = (time() - 1) . '|revision|{}';
        }
        $store->create('live', array('keep' => true), 600);
        $maintenance = ASFW_Control_Plane::maintenance();
        $this->assertSame(1000, $maintenance->cleanup_state());
        $this->assertNotFalse(wp_next_scheduled(ASFW_Maintenance::STATE_CLEANUP_HOOK));
        wp_clear_scheduled_hook(ASFW_Maintenance::STATE_CLEANUP_HOOK);
        $this->assertSame(1000, $maintenance->cleanup_state());
        $this->assertNotFalse(wp_next_scheduled(ASFW_Maintenance::STATE_CLEANUP_HOOK));
        wp_clear_scheduled_hook(ASFW_Maintenance::STATE_CLEANUP_HOOK);
        $this->assertSame(5, $maintenance->cleanup_state());
        $this->assertFalse(wp_next_scheduled(ASFW_Maintenance::STATE_CLEANUP_HOOK));
        $this->assertSame(array('keep' => true), $store->read('live')['value']);
        wp_schedule_single_event(time() + 10, ASFW_Maintenance::STATE_CLEANUP_HOOK);
        wp_schedule_single_event(time() + 10, 'asfw_initialize_network_batch', array(1, 100));
        asfw_deactivate_site();
        $this->assertFalse(wp_next_scheduled(ASFW_Maintenance::STATE_CLEANUP_HOOK));
        $this->assertFalse(wp_next_scheduled('asfw_initialize_network_batch', array(1, 100)));
    }

    public function test_new_site_initializes_only_when_active_on_its_network(): void
    {
        $site = (object) array('blog_id' => 8, 'network_id' => 2);
        $GLOBALS['asfw_test_network_options'][2]['active_sitewide_plugins'] = array(plugin_basename(ASFW_FILE) => time());
        asfw_initialize_new_site($site);
        $this->assertSame(1, get_current_blog_id());
        switch_to_blog(8);
        try { $this->assertNotSame('', get_option('asfw_secret', '')); }
        finally { restore_current_blog(); }
    }

    public function test_failed_schema_install_does_not_advance_version_and_recovers(): void
    {
        $store = ASFW_Control_Plane::store();
        delete_option(ASFW_Event_Store::OPTION_DB_VERSION);
        unset($GLOBALS['asfw_test_schema'][$store->get_table_name()]);
        $GLOBALS['asfw_test_schema_failure'] = true;
        $this->assertInstanceOf(WP_Error::class, $store->install());
        $this->assertFalse(get_option(ASFW_Event_Store::OPTION_DB_VERSION));
        $this->assertFalse($store->record_event('fixture'));
        $GLOBALS['asfw_test_schema_failure'] = false;
        $this->assertTrue($store->install());
        $this->assertSame((string) ASFW_Event_Store::DB_VERSION, get_option(ASFW_Event_Store::OPTION_DB_VERSION));
    }

    /** @dataProvider legacySchemaStates */
    public function test_legacy_schema_marker_is_reverified_during_site_initialization(bool $partial): void
    {
        $store = ASFW_Control_Plane::store();
        $table = $store->get_table_name();
        if ($partial) {
            $store->install();
            $GLOBALS['asfw_test_schema'][$table]['indexes'] = array();
        } else {
            unset($GLOBALS['asfw_test_schema'][$table]);
        }
        update_option(ASFW_Event_Store::OPTION_DB_VERSION, '3');
        delete_option('asfw_site_initialized');

        asfw_maybe_initialize_site();

        $this->assertSame((string) ASFW_Event_Store::DB_VERSION, get_option(ASFW_Event_Store::OPTION_DB_VERSION));
        $this->assertSame('1', get_option('asfw_site_initialized'));
        $this->assertCount(9, $GLOBALS['asfw_test_schema'][$table]['columns']);
        $this->assertCount(5, $GLOBALS['asfw_test_schema'][$table]['indexes']);
        $this->assertFalse($store->maybe_upgrade_schema(), 'Verified schema should not run DDL on every request.');
    }

    public static function legacySchemaStates(): array
    {
        return array('missing table' => array(false), 'missing indexes' => array(true));
    }

    public function test_failed_legacy_schema_repair_remains_uninitialized_and_retries(): void
    {
        $store = ASFW_Control_Plane::store();
        update_option(ASFW_Event_Store::OPTION_DB_VERSION, '3');
        delete_option('asfw_site_initialized');
        unset($GLOBALS['asfw_test_schema'][$store->get_table_name()]);
        $GLOBALS['asfw_test_schema_failure'] = true;

        asfw_maybe_initialize_site();

        $this->assertSame('3', get_option(ASFW_Event_Store::OPTION_DB_VERSION));
        $this->assertNotSame('1', get_option('asfw_site_initialized'));
        $GLOBALS['asfw_test_schema_failure'] = false;
        asfw_maybe_initialize_site();
        $this->assertSame((string) ASFW_Event_Store::DB_VERSION, get_option(ASFW_Event_Store::OPTION_DB_VERSION));
        $this->assertSame('1', get_option('asfw_site_initialized'));
    }

    public function test_database_delete_failure_is_not_reported_as_successful_maintenance(): void
    {
        update_option(ASFW_Maintenance::OPTION_LAST_RUN, 'previous-success');
        $GLOBALS['asfw_test_event_delete_failure'] = true;
        $this->assertInstanceOf(WP_Error::class, ASFW_Control_Plane::store()->purge_all());
        $this->assertInstanceOf(WP_Error::class, ASFW_Control_Plane::maintenance()->run());
        $this->assertSame('previous-success', get_option(ASFW_Maintenance::OPTION_LAST_RUN));
    }

    public function test_bad_remote_feed_does_not_mark_maintenance_successful(): void
    {
        update_option('asfw_feature_disposable_email_background_enabled', 1);
        update_option(ASFW_Maintenance::OPTION_LAST_RUN, 'previous-success');
        update_option(ASFW_Disposable_Email_Module::OPTION_LAST_REFRESH, '2020-01-01 00:00:00');
        asfw_test_queue_http_response(array('response' => array('code' => 200), 'body' => '<html>unavailable</html>'));
        $this->assertInstanceOf(WP_Error::class, ASFW_Control_Plane::maintenance()->run());
        $this->assertSame('previous-success', get_option(ASFW_Maintenance::OPTION_LAST_RUN));
        $this->assertSame('2020-01-01 00:00:00', get_option(ASFW_Disposable_Email_Module::OPTION_LAST_REFRESH));
    }

    public function test_string_and_structured_event_details_have_identical_privacy(): void
    {
        $details = array('email' => 'person@example.org', 'token' => 'private-token', 'message' => 'peer 2001:db8::1', 'attempt' => 2);
        $this->assertSame(asfw_sanitize_event_details($details), asfw_sanitize_event_details(json_encode($details)));
        $store = ASFW_Control_Plane::store();
        $store->record_event('fixture', array('details' => json_encode($details)));
        $events = $store->fetch_events(array('type' => 'fixture'));
        $this->assertStringNotContainsString('person@example.org', $events[0]['details']);
        $this->assertStringNotContainsString('private-token', $events[0]['details']);
        $this->assertStringNotContainsString('2001:db8::1', $events[0]['details']);
        $this->assertSame(2, json_decode($events[0]['details'], true)['attempt']);
        $recursive = new stdClass(); $recursive->child = $recursive;
        $this->assertIsArray(asfw_sanitize_event_details($recursive));
        $store->record_event('large', array('details' => array_fill(0, 1000, str_repeat('ä', 800))));
        $large = $store->fetch_events(array('type' => 'large'));
        $this->assertIsArray(json_decode($large[0]['details'], true));
    }

    public function test_uninstall_inventory_covers_every_registered_setting(): void
    {
        foreach (ASFW_Settings_Schema::get_fields_by_section() as $fields) {
            foreach ($fields as $field) {
                $this->assertContains($field['option'], ASFW_Option_Inventory::names());
            }
        }
    }

    public function test_uninstall_cleans_more_than_one_hundred_sites(): void
    {
        if (!defined('WP_UNINSTALL_PLUGIN')) { define('WP_UNINSTALL_PLUGIN', true); }
        $GLOBALS['asfw_test_multisite'] = true;
        $GLOBALS['asfw_test_sites'] = range(1, 205);
        foreach ($GLOBALS['asfw_test_sites'] as $id) {
            switch_to_blog($id);
            try { update_option('asfw_widget_layout', 'extended'); update_option('asfw_disposable_email_last_refresh', 'old'); }
            finally { restore_current_blog(); }
        }
        include dirname(__DIR__, 2) . '/uninstall.php';
        $this->assertSame(1, get_current_blog_id());
        foreach ($GLOBALS['asfw_test_sites'] as $id) {
            switch_to_blog($id);
            try { $this->assertFalse(get_option('asfw_widget_layout')); $this->assertFalse(get_option('asfw_disposable_email_last_refresh')); }
            finally { restore_current_blog(); }
        }
    }
}
