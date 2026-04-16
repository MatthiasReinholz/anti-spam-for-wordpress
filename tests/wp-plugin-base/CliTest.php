<?php
declare(strict_types=1);

final class CliTest extends AsfwPluginTestCase
{
    public function test_events_list_returns_rows_and_logs_json(): void
    {
        $store = ASFW_Control_Plane::store();
        $table = $store->get_table_name();
        $GLOBALS['asfw_test_db_tables'][$table][] = array(
            'id' => 1,
            'event_type' => 'verification_failed',
            'event_status' => 'failed',
            'event_context' => 'contact-form-7',
            'module_name' => 'core',
            'actor_hash' => $store->hash_value('198.51.100.77', 'actor'),
            'subject_hash' => '',
            'email_hash' => '',
            'details' => '{}',
            'created_at_gmt' => gmdate('Y-m-d H:i:s'),
        );

        $events = ASFW_Control_Plane::instance()['cli']->events(array('list'), array('limit' => 1));

        $this->assertCount(1, $events);
        $this->assertSame('verification_failed', $events[0]['event_type']);
        $this->assertNotEmpty(WP_CLI::$logs);
        $this->assertSame(wp_json_encode($events), WP_CLI::$logs[count(WP_CLI::$logs) - 1]);
    }

    public function test_events_prune_requires_explicit_yes_flag(): void
    {
        $this->expectException(RuntimeException::class);

        ASFW_Control_Plane::instance()['cli']->events(array('prune'), array('days' => 30));
    }

    public function test_events_purge_deletes_rows_when_yes_is_provided(): void
    {
        $store = ASFW_Control_Plane::store();
        $table = $store->get_table_name();
        $GLOBALS['asfw_test_db_tables'][$table][] = array(
            'id' => 1,
            'event_type' => 'verification_failed',
            'event_status' => 'failed',
            'event_context' => 'contact-form-7',
            'module_name' => 'core',
            'actor_hash' => $store->hash_value('198.51.100.77', 'actor'),
            'subject_hash' => '',
            'email_hash' => '',
            'details' => '{}',
            'created_at_gmt' => gmdate('Y-m-d H:i:s'),
        );

        $deleted = ASFW_Control_Plane::instance()['cli']->events(array('purge'), array('yes' => true));

        $this->assertSame(1, $deleted);
        $this->assertSame(0, $store->count_events());
    }

    public function test_disposable_status_reports_cached_metadata(): void
    {
        $disposable_module = new class(ASFW_Control_Plane::store()) extends ASFW_Disposable_Email_Module {
            public function __construct(ASFW_Event_Store $store)
            {
                parent::__construct($store);
            }

            public function get_domains()
            {
                return array('trashmail.com', 'mailinator.com');
            }

            public function get_last_refresh()
            {
                return '2026-04-15 12:34:56';
            }
        };

        $cli = $this->buildCli($disposable_module);
        $status = $cli->disposable(array('status'), array());

        $this->assertSame(array('count' => 2, 'last_refresh' => '2026-04-15 12:34:56'), $status);
        $this->assertNotEmpty(WP_CLI::$logs);
        $this->assertSame(wp_json_encode($status), WP_CLI::$logs[count(WP_CLI::$logs) - 1]);
    }

    public function test_disposable_refresh_forces_remote_refresh(): void
    {
        $disposable_module = new class(ASFW_Control_Plane::store()) extends ASFW_Disposable_Email_Module {
            public $force_remote_values = array();

            public function __construct(ASFW_Event_Store $store)
            {
                parent::__construct($store);
            }

            public function refresh_from_source($force_remote = true)
            {
                $this->force_remote_values[] = $force_remote;

                return array('trashmail.com', 'mailinator.com', '10minutemail.com');
            }
        };

        $cli = $this->buildCli($disposable_module);
		$domains = $cli->disposable(array('refresh'), array('yes' => true));

        $this->assertSame(array('trashmail.com', 'mailinator.com', '10minutemail.com'), $domains);
        $this->assertSame(array(true), $disposable_module->force_remote_values);
        $this->assertNotEmpty(WP_CLI::$successes);
        $this->assertSame('Refreshed 3 disposable domains.', WP_CLI::$successes[count(WP_CLI::$successes) - 1]);
    }

    public function test_maintenance_cli_requires_yes(): void
    {
        $this->expectException(RuntimeException::class);

        ASFW_Control_Plane::instance()['cli']->maintenance(array('run'), array());
    }

    public function test_maintenance_cli_run_prunes_events_and_refreshes_disposable_list_when_yes_is_provided(): void
    {
        $store = ASFW_Control_Plane::store();
        $table = $store->get_table_name();
        $GLOBALS['asfw_test_db_tables'][$table][] = array(
            'id' => 1,
            'event_type' => 'verification_failed',
            'event_status' => 'failed',
            'event_context' => 'contact-form-7',
            'module_name' => 'core',
            'actor_hash' => $store->hash_value('198.51.100.77', 'actor'),
            'subject_hash' => '',
            'email_hash' => '',
            'details' => '{}',
            'created_at_gmt' => gmdate('Y-m-d H:i:s', time() - (40 * DAY_IN_SECONDS)),
        );

        $disposable_module = new class($store) extends ASFW_Disposable_Email_Module {
            public function __construct(ASFW_Event_Store $store)
            {
                parent::__construct($store);
            }

            public function maybe_refresh()
            {
                return 4;
            }
        };

        $maintenance = new ASFW_Maintenance($store, $disposable_module);
        $cli = new ASFW_CLI_Command($store, $maintenance, $disposable_module);
        $summary = $cli->maintenance(array('run'), array('yes' => true));

        $this->assertSame(array('pruned' => 1, 'refreshed' => array('disposable_domains' => 4)), $summary);
        $this->assertNotEmpty(WP_CLI::$logs);
        $this->assertSame(wp_json_encode($summary), WP_CLI::$logs[count(WP_CLI::$logs) - 1]);
    }

    public function test_bunny_cli_status_reports_configuration(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled, 1);
        update_option('asfw_feature_bunny_shield_mode', 'block');
        update_option('asfw_feature_bunny_shield_background_enabled', 1);
        update_option('asfw_feature_bunny_shield_scope_mode', 'selected');
        update_option('asfw_feature_bunny_shield_contexts', array('contact-form-7'));
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key, 'test-api-key');
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_zone_id, '42');
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_access_list_id, '77');
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_dry_run, true);
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_fail_open, true);

        asfw_test_queue_http_response(
            array(
                'response' => array(
                    'code'    => 200,
                    'message' => 'OK',
                ),
                'headers'  => array(),
                'body'     => wp_json_encode(
                    array(
                        'customLists' => array(
                            array(
                                'id'   => 77,
                                'name' => 'Anti Spam for WordPress',
                            ),
                        ),
                        'customEntryCount' => 2,
                        'customListCount'   => 1,
                    )
                ),
            )
        );

        $status = ASFW_Control_Plane::instance()['cli']->bunny(array('status'), array());

        $this->assertTrue($status['enabled']);
        $this->assertSame('block', $status['mode']);
        $this->assertTrue($status['background_enabled']);
        $this->assertSame('selected', $status['scope_mode']);
        $this->assertSame(array('contact-form-7'), $status['contexts']);
        $this->assertTrue($status['configured']);
        $this->assertTrue($status['dry_run']);
        $this->assertTrue($status['fail_open']);
        $this->assertSame(77, $status['access_list_id']);
        $this->assertSame(1, $status['list']['custom_list_count']);
        $this->assertSame(2, $status['list']['custom_entry_count']);
        $this->assertNotEmpty(WP_CLI::$logs);
        $this->assertSame(wp_json_encode($status), WP_CLI::$logs[count(WP_CLI::$logs) - 1]);
    }

    private function buildCli(?ASFW_Disposable_Email_Module $disposable_module = null, ?ASFW_Bunny_Shield_Module $bunny_module = null): ASFW_CLI_Command
    {
        $store = ASFW_Control_Plane::store();
        $maintenance = $disposable_module instanceof ASFW_Disposable_Email_Module ? new ASFW_Maintenance($store, $disposable_module) : ASFW_Control_Plane::maintenance();

        return new ASFW_CLI_Command(
            $store,
            $maintenance,
            $disposable_module ?? ASFW_Control_Plane::disposable_module(),
            $bunny_module ?? ASFW_Control_Plane::bunny_module()
        );
    }
}
