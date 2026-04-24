<?php
declare(strict_types=1);

final class UninstallTest extends AsfwPluginTestCase
{
    public function test_uninstall_removes_plugin_options_events_table_and_scheduled_hook(): void
    {
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            define('WP_UNINSTALL_PLUGIN', true);
        }

        $store = ASFW_Control_Plane::store();
        $table = $store->get_table_name();
        update_option(AntiSpamForWordPressPlugin::$option_secret, 'secret-to-delete');
        update_option('asfw_feature_event_logging_enabled', 1);
        update_option('asfw_feature_event_logging_mode', 'log');
        update_option('asfw_rl_test_lock', (string) time());
        set_transient('asfw_test_transient', array('value' => true), 60);
        $store->record_event('challenge_issued', array('context' => 'contact-form-7'));

        $this->assertArrayHasKey($table, $GLOBALS['asfw_test_db_tables']);
        $this->assertArrayHasKey(ASFW_Maintenance::HOOK, $GLOBALS['asfw_test_cron_events']);

        include dirname(__DIR__, 2) . '/uninstall.php';

        $this->assertFalse(get_option(AntiSpamForWordPressPlugin::$option_secret, false));
        $this->assertFalse(get_option('asfw_feature_event_logging_enabled', false));
        $this->assertFalse(get_option('asfw_rl_test_lock', false));
        $this->assertArrayNotHasKey($table, $GLOBALS['asfw_test_db_tables']);
        $this->assertArrayNotHasKey(ASFW_Maintenance::HOOK, $GLOBALS['asfw_test_cron_events']);
    }
}
