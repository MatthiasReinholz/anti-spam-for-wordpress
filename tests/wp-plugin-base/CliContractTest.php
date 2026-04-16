<?php
declare(strict_types=1);

final class CliContractTest extends AsfwPluginTestCase
{
    public function test_status_command_returns_feature_summary(): void
    {
        $cli = ASFW_Control_Plane::instance()['cli'];
        $status = $cli->status(array(), array());

        $this->assertArrayHasKey('store', $status);
        $this->assertArrayHasKey('features', $status);
        $this->assertNotEmpty($status['features']);
        $this->assertNotEmpty(WP_CLI::$logs);
        $this->assertSame(wp_json_encode($status), WP_CLI::$logs[count(WP_CLI::$logs) - 1]);
    }

    public function test_feature_list_command_returns_registry_rows(): void
    {
        $cli = ASFW_Control_Plane::instance()['cli'];
        $rows = $cli->feature(array('list'), array());

        $ids = array_map(static function (array $row): string {
            return (string) $row['id'];
        }, $rows);

        $this->assertContains('bunny_shield', $ids);
        $this->assertNotEmpty(WP_CLI::$logs);
        $this->assertSame(wp_json_encode($rows), WP_CLI::$logs[count(WP_CLI::$logs) - 1]);
    }

    public function test_events_purge_older_than_alias_prunes_rows(): void
    {
        $store = ASFW_Control_Plane::store();
        $table = $store->get_table_name();
        $GLOBALS['asfw_test_db_tables'][$table][] = array(
            'id' => 1,
            'event_type' => 'verify_failed',
            'event_status' => 'failed',
            'event_context' => 'contact-form-7',
            'module_name' => 'core',
            'actor_hash' => $store->hash_value('198.51.100.77', 'actor'),
            'subject_hash' => '',
            'email_hash' => '',
            'details' => '{}',
            'created_at_gmt' => gmdate('Y-m-d H:i:s', time() - (40 * DAY_IN_SECONDS)),
        );

        $deleted = ASFW_Control_Plane::instance()['cli']->events(array('purge'), array('older-than' => 30, 'yes' => true));

        $this->assertSame(1, $deleted);
        $this->assertSame(0, $store->count_events());
    }

	public function test_disposable_email_refresh_requires_yes(): void
    {
        $disposable_module = new class(ASFW_Control_Plane::store()) extends ASFW_Disposable_Email_Module {
            public function __construct(ASFW_Event_Store $store)
            {
                parent::__construct($store);
            }

            public function refresh_from_source($force_remote = true)
            {
                return array('trashmail.com');
            }
        };

        $cli = new ASFW_CLI_Command(
            ASFW_Control_Plane::store(),
            new ASFW_Maintenance(ASFW_Control_Plane::store(), $disposable_module),
            $disposable_module
        );

        $this->expectException(RuntimeException::class);
        $cli->disposable_email(array('refresh'), array());
    }

	public function test_disposable_legacy_refresh_command_requires_yes(): void
	{
		$disposable_module = new class(ASFW_Control_Plane::store()) extends ASFW_Disposable_Email_Module {
			public $forced = array();

			public function __construct(ASFW_Event_Store $store)
			{
				parent::__construct($store);
			}

			public function refresh_from_source($force_remote = true)
			{
				$this->forced[] = $force_remote;

				return array('trashmail.com', 'mailinator.com');
			}
		};

		$cli = new ASFW_CLI_Command(
			ASFW_Control_Plane::store(),
			new ASFW_Maintenance(ASFW_Control_Plane::store(), $disposable_module),
			$disposable_module
		);
		$this->expectException(RuntimeException::class);
		$cli->disposable(array('refresh'), array());
	}

    public function test_disposable_email_refresh_with_yes_invokes_refresh(): void
    {
        $disposable_module = new class(ASFW_Control_Plane::store()) extends ASFW_Disposable_Email_Module {
            public $forced = array();

            public function __construct(ASFW_Event_Store $store)
            {
                parent::__construct($store);
            }

            public function refresh_from_source($force_remote = true)
            {
                $this->forced[] = $force_remote;

                return array('trashmail.com', 'mailinator.com');
            }
        };

        $cli = new ASFW_CLI_Command(
            ASFW_Control_Plane::store(),
            new ASFW_Maintenance(ASFW_Control_Plane::store(), $disposable_module),
            $disposable_module
        );
        $domains = $cli->disposable_email(array('refresh'), array('yes' => true));

        $this->assertSame(array('trashmail.com', 'mailinator.com'), $domains);
        $this->assertSame(array(true), $disposable_module->forced);
    }
}
