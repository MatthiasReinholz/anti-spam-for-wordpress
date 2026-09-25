<?php
declare(strict_types=1);

final class PrivacyHardeningTest extends AsfwPluginTestCase
{
    public function test_numeric_sensitive_values_are_redacted_but_metrics_keep_their_types(): void
    {
        $result = asfw_sanitize_event_details(array('phone' => 41795551234, 'token' => 123456, 'attempts' => 2, 'score' => 0.5, 'blocked' => true));
        $this->assertSame(asfw_hash_value('41795551234', 'phone'), $result['phone']);
        $this->assertSame(asfw_hash_value('123456', 'token'), $result['token']);
        $this->assertSame(2, $result['attempts']);
        $this->assertSame(0.5, $result['score']);
        $this->assertTrue($result['blocked']);
    }

    public function test_deep_json_cannot_fall_back_to_plaintext_secrets(): void
    {
        $details = array('token' => 'secret-from-deep-json');
        for ($depth = 0; $depth < 18; $depth++) {
            $details = array('nested' => $details);
        }
        $this->assertSame(array('_truncated' => true), asfw_sanitize_event_details(json_encode($details)));
        $this->assertStringNotContainsString('secret-from-deep-json', json_encode(asfw_sanitize_event_details($details)));
        $this->assertSame(array('_truncated' => true), asfw_sanitize_event_details('{"token":"private",broken-json'));
    }

    public function test_oversized_json_strings_and_keys_are_bounded_without_retaining_input(): void
    {
        $this->assertSame(array('_truncated' => true), asfw_sanitize_event_details(str_repeat('x', 65536)));
        $result = asfw_sanitize_event_details(array('message' => str_repeat('x', 8193), str_repeat('y', 8193) => 'secret'));
        $this->assertSame('[truncated]', $result['message']);
        $this->assertTrue($result['_truncated']);
        $this->assertStringNotContainsString('secret', json_encode($result));
    }

    public function test_failed_partial_schema_repair_does_not_advance_version(): void
    {
        $store = ASFW_Control_Plane::store();
        $table = $store->get_table_name();
        $original = $GLOBALS['asfw_test_schema'][$table];
        delete_option(ASFW_Event_Store::OPTION_DB_VERSION);
        $GLOBALS['asfw_test_schema_failure'] = true;
        foreach ($GLOBALS['asfw_test_schema'][$table]['columns'] as &$column) {
            if ($column['Field'] === 'id') {
                $column['Extra'] = '';
            }
        }
        unset($column);
        try {
            $this->assertInstanceOf(WP_Error::class, $store->install());
            $this->assertFalse(get_option(ASFW_Event_Store::OPTION_DB_VERSION));
            $GLOBALS['asfw_test_schema'][$table] = $original;
            foreach ($GLOBALS['asfw_test_schema'][$table]['columns'] as &$column) {
                if ($column['Field'] === 'created_at') {
                    $column['Null'] = 'YES';
                }
            }
            unset($column);
            $this->assertInstanceOf(WP_Error::class, $store->install());
            $this->assertFalse(get_option(ASFW_Event_Store::OPTION_DB_VERSION));
        } finally {
            $GLOBALS['asfw_test_schema_failure'] = false;
            $GLOBALS['asfw_test_schema'][$table] = $original;
        }
        $this->assertTrue($store->install());
    }
}
