<?php
declare(strict_types=1);

final class RemoteReliabilityTest extends AsfwPluginTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        update_option('asfw_feature_bunny_shield_enabled', 1);
        update_option('asfw_feature_bunny_shield_mode', 'block');
        update_option('asfw_feature_bunny_shield_background_enabled', 1);
        update_option('asfw_feature_bunny_shield_api_key', 'test-api-key');
        update_option('asfw_feature_bunny_shield_zone_id', '42');
        update_option('asfw_feature_bunny_shield_access_list_id', '77');
        update_option('asfw_feature_bunny_shield_dry_run', false);
        update_option('asfw_feature_bunny_shield_threshold', '1');
    }

    private function response(array $body): array
    {
        return array('response' => array('code' => 200), 'body' => wp_json_encode($body));
    }

    /** @dataProvider invalidLists */
    public function test_invalid_list_payloads_never_trigger_remote_replacement(array $payload): void
    {
        $module = new AsfwRemoteReliabilityModule($this->plugin());
        asfw_test_queue_http_response($this->response($payload));
        $result = $module->block('8.8.8.8');
        $this->assertSame('asfw_bunny_invalid_response', $result['error']['code']);
        $this->assertCount(1, $GLOBALS['asfw_test_http_requests']);
        $this->assertSame('77', get_option('asfw_feature_bunny_shield_access_list_id'));
    }

    public static function invalidLists(): array
    {
        return array(
            array(array()),
            array(array('data' => array('id' => 77))),
            array(array('data' => array('id' => 78, 'content' => ''))),
            array(array('data' => array('id' => 77, 'content' => '', 'type' => 1))),
            array(array('data' => array('id' => 77, 'content' => '8.8.8.8', 'checksum' => 'incorrect'))),
            array(array('data' => array('id' => 77, 'content' => "8.8.8.8\nunknown"))),
        );
    }

    public function test_discovery_failure_is_propagated_without_creating_a_duplicate(): void
    {
        update_option('asfw_feature_bunny_shield_access_list_id', '0');
        asfw_test_queue_http_response(new WP_Error('remote_timeout', 'Timeout'));
        $result = (new AsfwRemoteReliabilityModule($this->plugin()))->block('8.8.8.8');
        $this->assertSame('remote_timeout', $result['error']['code']);
        $this->assertCount(1, $GLOBALS['asfw_test_http_requests']);
    }

    public function test_revoke_returns_discovery_error_without_an_array_access_fatal(): void
    {
        update_option('asfw_feature_bunny_shield_access_list_id', '0');
        asfw_test_queue_http_response(new WP_Error('remote_timeout', 'Timeout'));
        $result = (new AsfwRemoteReliabilityModule($this->plugin()))->revoke_ip('8.8.8.8');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('remote_timeout', $result->get_error_code());
        $this->assertCount(1, $GLOBALS['asfw_test_http_requests']);
    }

    public function test_semantic_api_failure_is_not_reported_as_success(): void
    {
        asfw_test_queue_http_response($this->response(array('error' => array('success' => false, 'message' => 'Rejected'))));
        $result = (new ASFW_Bunny_Shield_Client('test-api-key', 42))->list_access_lists();
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_bunny_http_error', $result->get_error_code());
    }

    public function test_backoff_history_survives_the_retry_deadline_and_resets_on_success(): void
    {
        $module = new AsfwRemoteReliabilityModule($this->plugin());
        $module->failAgain();
        $this->assertSame(60, (int) $module->backoff()['delay']);
        $store = new ASFW_Atomic_State_Store();
        $key = 'bunny:zone:42:backoff';
        $snapshot = $store->read($key);
        $this->assertGreaterThan(time() + 3600, $snapshot['expires_at']);
        $state = $snapshot['value'];
        $state['retry_at'] = time() - 1;
        $this->assertTrue($store->replace($key, $snapshot, $state));
        $module->failAgain();
        $this->assertSame(120, (int) $module->backoff()['delay']);
        $this->assertSame(2, $module->backoff()['attempts']);
        $module->resetBackoff();
        $this->assertSame(array(), $module->backoff());
    }

    public function test_concurrent_writers_retry_after_the_owner_and_preserve_both_entries(): void
    {
        $client = new AsfwRemoteReliabilityClient();
        $first = new AsfwRemoteReliabilityModule($this->plugin(), $client);
        $second = new AsfwRemoteReliabilityModule($this->plugin(), $client);
        $contender = null;
        $client->onRead = static function () use ($second, &$contender): void {
            $contender = $second->block('1.1.1.1');
        };
        $first->block('8.8.8.8');
        $this->assertInstanceOf(WP_Error::class, $contender);
        $this->assertSame('asfw_bunny_busy', $contender->get_error_code());
        $this->assertSame(1, $client->writes);
        $second->block('1.1.1.1');
        $this->assertSame("8.8.8.8\n1.1.1.1", $client->content);
        $this->assertSame(2, $client->writes);
        $this->assertNull((new ASFW_Atomic_State_Store())->read('bunny:zone:42:mutation'));
    }

    public function test_a_worker_that_lost_its_lease_does_not_replace_the_remote_list(): void
    {
        $client = new AsfwRemoteReliabilityClient();
        $client->onRead = function (): void {
            $store = new ASFW_Atomic_State_Store();
            $key = 'bunny:zone:42:mutation';
            $this->assertTrue($store->replace($key, $store->read($key), array('owner' => 'new-worker'), 60));
        };
        $result = (new AsfwRemoteReliabilityModule($this->plugin(), $client))->block('8.8.8.8');
        $this->assertSame('asfw_bunny_busy', $result['error']['code']);
        $this->assertSame(0, $client->writes);
        $this->assertSame('new-worker', (new ASFW_Atomic_State_Store())->read('bunny:zone:42:mutation')['value']['owner']);
    }

    public function test_legacy_client_entry_helpers_share_the_module_lease(): void
    {
        $store = new ASFW_Atomic_State_Store();
        $lease = $store->acquire_lease('bunny:zone:42:mutation');
        $client = new ASFW_Bunny_Shield_Client('test-api-key', 42);
        $this->assertSame('asfw_bunny_busy', $client->add_entry('77', '8.8.8.8', 'block', 60, '')->get_error_code());
        $this->assertSame('asfw_bunny_busy', $client->remove_entry('77', '8.8.8.8')->get_error_code());
        $this->assertCount(0, $GLOBALS['asfw_test_http_requests']);
        $this->assertTrue($store->release_lease('bunny:zone:42:mutation', $lease));
    }

    public function test_colon_context_is_preserved_for_bunny_and_event_logging(): void
    {
        update_option('asfw_feature_bunny_shield_scope_mode', 'selected');
        update_option('asfw_feature_bunny_shield_contexts', array('wordpress:comments'));
        update_option('asfw_feature_bunny_shield_dry_run', true);
        update_option('asfw_feature_event_logging_enabled', 1);
        update_option('asfw_feature_event_logging_mode', 'log');
        update_option('asfw_feature_event_logging_scope_mode', 'selected');
        update_option('asfw_feature_event_logging_contexts', array('wordpress:comments'));
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        do_action('asfw_verify_result', false, new WP_Error('rejected', 'Rejected'), 'wordpress:comments', 'asfw');
        $events = ASFW_Control_Plane::store()->fetch_events(array('type' => 'bunny_dry_run'));
        $this->assertCount(1, $events);
        $this->assertSame('wordpress:comments', $events[0]['context']);
    }
    public function test_malformed_write_acknowledgement_does_not_mark_the_ip_synchronized(): void
    {
        $module = new AsfwRemoteReliabilityModule($this->plugin());
        asfw_test_queue_http_response($this->response(array('data' => array('id' => 77, 'content' => ''))));
        asfw_test_queue_http_response($this->response(array('data' => array('id' => 77, 'content' => ''))));
        $result = $module->block('8.8.8.8');
        $this->assertSame('asfw_bunny_invalid_response', $result['error']['code']);
        $this->assertNull((new ASFW_Atomic_State_Store())->read('bunny:zone:42:asfw_bunny_banned_' . md5('8.8.8.8')));
        $this->assertCount(2, $GLOBALS['asfw_test_http_requests']);
    }

    public function test_dry_run_dedupe_never_suppresses_a_subsequent_live_block(): void
    {
        $client = new AsfwRemoteReliabilityClient();
        $module = new AsfwRemoteReliabilityModule($this->plugin(), $client);
        update_option('asfw_feature_bunny_shield_dry_run', true);
        $this->assertSame('dry_run', $module->block('8.8.8.8')['status']);
        $this->assertSame('deduped', $module->block('8.8.8.8')['status']);
        update_option('asfw_feature_bunny_shield_dry_run', false);
        $module->block('8.8.8.8');
        $this->assertSame('8.8.8.8', $client->content);
        $this->assertSame(1, $client->writes);
    }

    public function test_forced_revoke_clears_live_and_dry_run_dedupe_even_in_dry_run_mode(): void
    {
        $client = new AsfwRemoteReliabilityClient();
        $module = new AsfwRemoteReliabilityModule($this->plugin(), $client);
        $module->block('8.8.8.8');
        update_option('asfw_feature_bunny_shield_dry_run', true);
        $module->block('8.8.8.8');
        $this->assertSame('updated', $module->revoke_ip('8.8.8.8', true)['status']);
        $store = new ASFW_Atomic_State_Store();
        $this->assertNull($store->read('bunny:zone:42:asfw_bunny_banned_' . md5('8.8.8.8')));
        $this->assertNull($store->read('bunny:zone:42:asfw_bunny_dry_run_' . md5('8.8.8.8')));
    }

    public function test_network_sites_share_the_remote_zone_lease(): void
    {
        $GLOBALS['asfw_test_multisite'] = true;
        $store = new ASFW_Atomic_State_Store(1);
        $lease = $store->acquire_lease('bunny:zone:42:mutation');
        switch_to_blog(2);
        try {
            $client = new ASFW_Bunny_Shield_Client('test-api-key', 42);
            $result = $client->remove_entry('77', '8.8.8.8');
            $this->assertInstanceOf(WP_Error::class, $result);
            $this->assertSame('asfw_bunny_busy', $result->get_error_code());
            $this->assertCount(0, $GLOBALS['asfw_test_http_requests']);
        } finally {
            restore_current_blog();
            $store->release_lease('bunny:zone:42:mutation', $lease);
        }
    }

    /** @dataProvider confirmedAbsence */
    public function test_idempotent_revoke_clears_all_local_ip_state(bool $missingList): void
    {
        $store = new ASFW_Atomic_State_Store();
        $keys = array('counter', 'banned', 'dry_run');
        foreach ($keys as $kind) {
            $this->assertTrue($store->create('bunny:zone:42:asfw_bunny_' . $kind . '_' . md5('8.8.8.8'), array('count' => 10), 300));
        }
        if ($missingList) {
            asfw_test_queue_http_response(array('response' => array('code' => 404), 'body' => '{}'));
            asfw_test_queue_http_response($this->response(array('customLists' => array())));
        } else {
            asfw_test_queue_http_response($this->response(array('data' => array('id' => 77, 'content' => ''))));
        }
        $result = (new AsfwRemoteReliabilityModule($this->plugin()))->revoke_ip('8.8.8.8', true);
        $this->assertSame($missingList ? 'missing_list' : 'unchanged', $result['status']);
        foreach ($keys as $kind) {
            $this->assertNull($store->read('bunny:zone:42:asfw_bunny_' . $kind . '_' . md5('8.8.8.8')));
        }
        foreach ($GLOBALS['asfw_test_http_requests'] as $request) {
            $this->assertSame('GET', $request['args']['method']);
        }
    }

    public static function confirmedAbsence(): array
    {
        return array(array(false), array(true));
    }

    public function test_revoke_rediscovers_a_replaced_list_without_creating_one(): void
    {
        asfw_test_queue_http_response(array('response' => array('code' => 404), 'body' => '{}'));
        asfw_test_queue_http_response($this->response(array('customLists' => array(array('id' => 88, 'name' => ASFW_Bunny_Shield_Module::LIST_NAME)))));
        asfw_test_queue_http_response($this->response(array('data' => array('id' => 88, 'content' => "8.8.8.8\n1.1.1.1"))));
        asfw_test_queue_http_response($this->response(array('data' => array('id' => 88, 'content' => '1.1.1.1'))));
        $result = (new AsfwRemoteReliabilityModule($this->plugin()))->revoke_ip('8.8.8.8', true);
        $this->assertSame('updated', $result['status']);
        $this->assertSame(88, $result['list_id']);
        $this->assertSame('88', (string) get_option('asfw_feature_bunny_shield_access_list_id'));
        $this->assertSame(array('GET', 'GET', 'GET', 'PATCH'), array_column(array_column($GLOBALS['asfw_test_http_requests'], 'args'), 'method'));
    }

    public function test_revoke_preserves_local_state_and_cached_id_on_remote_failure(): void
    {
        $store = new ASFW_Atomic_State_Store();
        $key = 'bunny:zone:42:asfw_bunny_banned_' . md5('8.8.8.8');
        $store->create($key, array('count' => 1), 300);
        foreach (array(new WP_Error('timeout'), $this->response(array('data' => array('id' => 77)))) as $response) {
            asfw_test_queue_http_response($response);
            $this->assertInstanceOf(WP_Error::class, (new AsfwRemoteReliabilityModule($this->plugin()))->revoke_ip('8.8.8.8', true));
            $this->assertIsArray($store->read($key));
            $this->assertSame('77', get_option('asfw_feature_bunny_shield_access_list_id'));
        }
    }

    public function test_revoke_does_not_clear_state_after_losing_its_lease(): void
    {
        $store = new ASFW_Atomic_State_Store();
        $key = 'bunny:zone:42:asfw_bunny_banned_' . md5('8.8.8.8');
        $store->create($key, array('count' => 1), 300);
        $client = new AsfwRemoteReliabilityClient();
        $client->onRead = static function () use ($store): void {
            $leaseKey = 'bunny:zone:42:mutation';
            $store->replace($leaseKey, $store->read($leaseKey), array('owner' => 'replacement'), 60);
        };
        $result = (new AsfwRemoteReliabilityModule($this->plugin(), $client))->revoke_ip('8.8.8.8', true);
        $this->assertSame('asfw_bunny_busy', $result->get_error_code());
        $this->assertIsArray($store->read($key));
    }

    public function test_revoke_preserves_state_after_atomic_delete_failure_and_clears_it_on_retry(): void
    {
        $store = new ASFW_Atomic_State_Store();
        $snapshots = array();
        foreach (array('counter', 'banned', 'dry_run') as $kind) {
            $key = 'bunny:zone:42:asfw_bunny_' . $kind . '_' . md5('8.8.8.8');
            $this->assertTrue($store->create($key, array('count' => 5), 300));
            $snapshots[$key] = $store->read($key);
        }
        $counterKey = 'asfw_bunny_counter_' . md5('8.8.8.8');
        set_transient($counterKey, array('count' => 5), 300);
        $counterOption = $store->option_name('bunny:zone:42:' . $counterKey);
        $GLOBALS['asfw_test_atomic_before_query'] = static function ($query) use ($counterOption): void {
            // Fail the real counter DELETE; allow the independent lease release.
            $GLOBALS['asfw_test_atomic_failure'] = str_starts_with($query, 'DELETE') && str_contains($query, $counterOption);
        };
        asfw_test_queue_http_response($this->response(array('data' => array('id' => 77, 'content' => ''))));
        $module = new AsfwRemoteReliabilityModule($this->plugin());
        try {
            $result = $module->revoke_ip('8.8.8.8', true);
        } finally {
            $GLOBALS['asfw_test_atomic_failure'] = false;
            $GLOBALS['asfw_test_atomic_before_query'] = null;
        }
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_bunny_revoke_state_failed', $result->get_error_code());
        foreach ($snapshots as $key => $snapshot) {
            $this->assertSame($snapshot, $store->read($key));
        }
        $this->assertSame(array('count' => 5), get_transient($counterKey));
        $this->assertNull($store->read('bunny:zone:42:mutation'));
        $this->assertSame(array('GET'), array_column(array_column($GLOBALS['asfw_test_http_requests'], 'args'), 'method'));

        asfw_test_queue_http_response($this->response(array('data' => array('id' => 77, 'content' => ''))));
        $this->assertSame('unchanged', $module->revoke_ip('8.8.8.8', true)['status']);
        foreach (array_keys($snapshots) as $key) {
            $this->assertNull($store->read($key));
        }
        $this->assertFalse(get_transient($counterKey));
        $this->assertNull($store->read('bunny:zone:42:mutation'));
        $this->assertSame(array('GET', 'GET'), array_column(array_column($GLOBALS['asfw_test_http_requests'], 'args'), 'method'));
    }

}

class AsfwRemoteReliabilityModule extends ASFW_Bunny_Shield_Module
{
    private $testClient;

    public function __construct($plugin, $client = null)
    {
        parent::__construct($plugin);
        $this->testClient = $client;
    }

    protected function get_client()
    {
        return $this->testClient ?? parent::get_client();
    }

    public function block(string $ip)
    {
        return $this->maybe_block_ip($ip, 'verification_failed', 'wordpress:comments');
    }

    public function failAgain(): void
    {
        $this->bump_backoff_state();
    }

    public function backoff(): array
    {
        return $this->get_backoff_state();
    }

    public function resetBackoff(): void
    {
        $this->reset_backoff_state();
    }
}

class AsfwRemoteReliabilityClient extends ASFW_Bunny_Shield_Client
{
    public $content = '';
    public $writes = 0;
    public $onRead;

    public function get_access_list($list_id, $shield_zone_id = null)
    {
        if (is_callable($this->onRead)) {
            $callback = $this->onRead;
            $this->onRead = null;
            $callback();
        }
        return array('body' => array('data' => array('id' => (int) $list_id, 'content' => $this->content)));
    }

    public function update_access_list($list_id, $content, $shield_zone_id = null, $name = null)
    {
        ++$this->writes;
        $this->content = $content;
        return $this->get_access_list($list_id);
    }
}
