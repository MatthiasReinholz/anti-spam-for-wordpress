<?php
declare(strict_types=1);

final class EventReadReliabilityTest extends AsfwPluginTestCase
{
    private $originalDatabase;

    protected function tearDown(): void
    {
        if ($this->originalDatabase !== null) {
            $GLOBALS['wpdb'] = $this->originalDatabase;
        }
        parent::tearDown();
    }

    private function useSqlDatabase(string $failure, bool $emptyOnFailure = false): object
    {
        $this->originalDatabase = $GLOBALS['wpdb'];
        // Do not extend the unit-test wpdb: its asfw_* shortcuts bypass production SQL.
        $database = new class($GLOBALS['wpdb'], $failure, $emptyOnFailure) {
            public $prefix = 'wp_';
            public $last_error = '';
            public $failure;
            private $delegate;
            private $emptyOnFailure;

            public function __construct($delegate, $failure, $emptyOnFailure)
            {
                $this->delegate = $delegate;
                $this->failure = $failure;
                $this->emptyOnFailure = $emptyOnFailure;
            }

            public function prepare($query, ...$args)
            {
                return $this->delegate->prepare($query, ...$args);
            }

            private function fails($query): bool
            {
                $this->last_error = '';
                if ($this->failure !== '' && strpos($query, $this->failure) !== false) {
                    $this->last_error = 'Database connection failed with private diagnostic';
                    return true;
                }
                return false;
            }

            public function get_var($query)
            {
                return $this->fails($query) && !$this->emptyOnFailure ? null : '0';
            }

            public function get_results($query, $output = ARRAY_A)
            {
                return $this->fails($query) && !$this->emptyOnFailure ? null : array();
            }
        };
        $GLOBALS['wpdb'] = $database;
        return $database;
    }

    public static function reads(): array
    {
        return array(
            'events' => array('fetch_events', 'SELECT id,', array()),
            'count' => array('count_events', 'SELECT COUNT(*)', 0),
            'types' => array('get_type_counts', 'SELECT event_type,', array()),
            'modules' => array('get_module_counts', 'SELECT COALESCE(', array()),
            'daily' => array('get_daily_counts', 'SELECT DATE(', array()),
        );
    }

    /** @dataProvider reads */
    public function test_sql_failure_is_distinct_from_empty_results_and_success_clears_error(string $method, string $query, $empty): void
    {
        $store = ASFW_Control_Plane::store();
        $database = $this->useSqlDatabase($query);
        $this->assertSame($empty, $store->$method());
        $error = $store->get_last_read_error();
        $this->assertInstanceOf(WP_Error::class, $error);
        $this->assertSame('asfw_event_read_failed', $error->get_error_code());
        $this->assertSame(503, $error->get_error_data()['status']);
        $this->assertStringNotContainsString('private diagnostic', $error->get_error_message());

        $database->failure = '';
        $result = $store->$method();
        $this->assertNull($store->get_last_read_error());
        if ($method === 'get_daily_counts') {
            $this->assertCount(7, $result);
            $this->assertSame(array(0), array_values(array_unique($result)));
        } else {
            $this->assertSame($empty, $result);
        }
    }

    /** @dataProvider reads */
    public function test_database_error_is_checked_even_when_driver_returns_an_empty_result(string $method, string $query, $empty): void
    {
        $this->useSqlDatabase($query, true);
        $store = ASFW_Control_Plane::store();
        $this->assertSame($empty, $store->$method());
        $this->assertInstanceOf(WP_Error::class, $store->get_last_read_error());
    }

    /** @dataProvider reads */
    public function test_failed_schema_install_is_preserved_by_every_read(string $method, string $query, $empty): void
    {
        $store = ASFW_Control_Plane::store();
        delete_option(ASFW_Event_Store::OPTION_DB_VERSION);
        unset($GLOBALS['asfw_test_schema'][$store->get_table_name()]);
        $GLOBALS['asfw_test_schema_failure'] = true;
        $this->assertSame($empty, $store->$method());
        $this->assertSame('asfw_schema_unavailable', $store->get_last_read_error()->get_error_code());
        $this->assertSame(503, $store->get_last_read_error()->get_error_data()['status']);
    }

    public static function restFailures(): array
    {
        return array(
            'event total' => array('asfw_rest_operation_events_list', 'SELECT COUNT(*)'),
            'event rows' => array('asfw_rest_operation_events_list', 'SELECT id,'),
            'event type filters' => array('asfw_rest_operation_events_list', 'SELECT event_type,'),
            'event module filters' => array('asfw_rest_operation_events_list', 'SELECT COALESCE('),
            'analytics total' => array('asfw_rest_operation_analytics_read', 'SELECT COUNT(*)'),
            'analytics rows' => array('asfw_rest_operation_analytics_read', 'SELECT id,'),
        );
    }

    /** @dataProvider restFailures */
    public function test_rest_reports_unavailable_instead_of_successful_empty_data(string $callback, string $query): void
    {
        $this->useSqlDatabase($query);
        $result = $callback(new WP_REST_Request(), array());
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_event_read_failed', $result->get_error_code());
        $this->assertSame(503, $result->get_error_data()['status']);
    }

    public function test_cli_event_listing_does_not_print_success_json_after_a_failed_read(): void
    {
        $this->useSqlDatabase('SELECT id,');
        $logs = WP_CLI::$logs;
        try {
            ASFW_Control_Plane::instance()['cli']->events(array('list'), array());
            $this->fail('CLI should report the read failure.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Events could not be loaded.', $error->getMessage());
            $this->assertSame($logs, WP_CLI::$logs);
        }
    }

    public function test_cli_status_does_not_report_zero_events_after_a_failed_count(): void
    {
        $this->useSqlDatabase('SELECT COUNT(*)');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Events could not be loaded.');
        ASFW_Control_Plane::instance()['cli']->status(array(), array());
    }
}
