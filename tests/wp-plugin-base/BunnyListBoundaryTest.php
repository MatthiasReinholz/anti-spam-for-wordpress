<?php
declare(strict_types=1);

final class BunnyListBoundaryTest extends AsfwPluginTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        update_option('asfw_feature_bunny_shield_api_key', 'test-api-key');
        update_option('asfw_feature_bunny_shield_zone_id', '42');
        update_option('asfw_feature_bunny_shield_access_list_id', '77');
    }

    public function test_streaming_parser_preserves_order_normalization_and_supported_separators(): void
    {
        $content = " 8.8.8.8\r\n2001:0DB8:0000:0000:0000:0000:0000:0001,\r8.8.8.8\n[2001:db8::1]:443\r\n1.1.1.1 ";
        $this->assertSame(array('8.8.8.8', '2001:db8::1', '1.1.1.1'), $this->parse($content));
        $this->assertSame(array(), $this->parse(" \t\r\n"));
        $this->assertSame(array(), $this->parse(",\r\n,,\n"));
        $this->assertSame(array('::1'), $this->parse(str_repeat("::1\r\n", ASFW_Bunny_List_Content::MAX_SEGMENTS)));
    }

    /** @dataProvider invalidInput */
    public function test_parser_rejects_the_complete_list_instead_of_discarding_invalid_entries($content, string $code): void
    {
        $result = $this->parse($content);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame($code, $result->get_error_code());
    }

    public static function invalidInput(): array
    {
        return array(
            'malformed entry' => array("8.8.8.8\nunknown\n1.1.1.1", 'asfw_bunny_invalid_response'),
            'non-string' => array(array('8.8.8.8'), 'asfw_bunny_invalid_response'),
            'oversized content' => array(str_repeat('x', ASFW_Bunny_List_Content::MAX_BYTES + 1), 'asfw_bunny_list_too_large'),
            'too many duplicate segments' => array(str_repeat('::1,', ASFW_Bunny_List_Content::MAX_SEGMENTS + 1), 'asfw_bunny_list_too_large'),
            'too many empty segments' => array(str_repeat(',', ASFW_Bunny_List_Content::MAX_SEGMENTS + 1), 'asfw_bunny_list_too_large'),
        );
    }

    public function test_unique_ip_limit_rejects_additional_entries(): void
    {
        $result = $this->parse($this->uniqueIps(ASFW_Bunny_List_Content::MAX_ENTRIES + 1));
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw_bunny_list_too_large', $result->get_error_code());
    }

    /** @dataProvider mutationPaths */
    public function test_oversized_remote_lists_never_trigger_mutation_or_local_state_cleanup(bool $legacy): void
    {
        // This body remains inside the HTTP client's 8 MiB envelope.
        $content = str_repeat('::1,', 2000000);
        $body = wp_json_encode(array('data' => array('id' => 77, 'content' => $content)));
        $this->assertLessThan(8 * 1024 * 1024, strlen($body));
        $store = new ASFW_Atomic_State_Store();
        $key = 'bunny:zone:42:asfw_bunny_banned_' . md5('8.8.8.8');
        $store->create($key, array('count' => 5), 300);
        asfw_test_queue_http_response(array('response' => array('code' => 200), 'body' => $body));
        $result = $legacy
            ? (new ASFW_Bunny_Shield_Client('test-api-key', 42))->remove_entry('77', '8.8.8.8')
            : (new ASFW_Bunny_Shield_Module($this->plugin()))->revoke_ip('8.8.8.8', true);
        $this->assertSame('asfw_bunny_list_too_large', $result->get_error_code());
        $this->assertSame(array('GET'), array_column(array_column($GLOBALS['asfw_test_http_requests'], 'args'), 'method'));
        $this->assertIsArray($store->read($key));
        $this->assertNull($store->read('bunny:zone:42:mutation'));
    }

    public static function mutationPaths(): array
    {
        return array('module' => array(false), 'legacy client' => array(true));
    }

    /** @dataProvider mutationPaths */
    public function test_mutation_cannot_grow_a_full_list_beyond_the_unique_limit(bool $legacy): void
    {
        $content = $this->uniqueIps(ASFW_Bunny_List_Content::MAX_ENTRIES);
        asfw_test_queue_http_response(array('response' => array('code' => 200), 'body' => wp_json_encode(array('data' => array('id' => 77, 'content' => $content)))));
        if ($legacy) {
            $result = (new ASFW_Bunny_Shield_Client('test-api-key', 42))->add_entry('77', '8.8.8.8', 'block', 10, '');
            $this->assertSame('asfw_bunny_list_too_large', $result->get_error_code());
        } else {
            update_option('asfw_feature_bunny_shield_enabled', 1);
            update_option('asfw_feature_bunny_shield_mode', 'block');
            update_option('asfw_feature_bunny_shield_background_enabled', 1);
            update_option('asfw_feature_bunny_shield_dry_run', false);
            update_option('asfw_feature_bunny_shield_threshold', '1');
            $module = new class($this->plugin()) extends ASFW_Bunny_Shield_Module {
                public function block() { return $this->maybe_block_ip('8.8.8.8', 'fixture', 'wordpress:comments'); }
            };
            $result = $module->block();
            $this->assertSame('asfw_bunny_list_too_large', $result['error']['code']);
        }
        $this->assertCount(1, $GLOBALS['asfw_test_http_requests']);
    }

    public function test_normal_revoke_keeps_all_other_unique_normalized_ips(): void
    {
        asfw_test_queue_http_response(array('response' => array('code' => 200), 'body' => wp_json_encode(array('data' => array('id' => 77, 'content' => "8.8.8.8\r\n2001:DB8::1,1.1.1.1\n2001:db8::1")))));
        asfw_test_queue_http_response(array('response' => array('code' => 200), 'body' => wp_json_encode(array('data' => array('id' => 77, 'content' => "2001:db8::1\n1.1.1.1")))));
        $result = (new ASFW_Bunny_Shield_Module($this->plugin()))->revoke_ip('8.8.8.8', true);
        $this->assertSame('updated', $result['status']);
        $body = json_decode($GLOBALS['asfw_test_http_requests'][1]['args']['body'], true);
        $this->assertSame("2001:db8::1\n1.1.1.1", $body['content']);
        $this->assertSame(hash('sha256', $body['content']), $body['checksum']);
    }

    private function parse($content)
    {
        return ASFW_Bunny_List_Content::parse($content, array(new ASFW_Client_Identity(), 'normalize_ip'));
    }

    private function uniqueIps(int $count): string
    {
        $content = '';
        for ($index = 0; $index < $count; ++$index) {
            $content .= '2001:db8:' . dechex(intdiv($index, 65536)) . ':' . dechex($index % 65536) . "::1\n";
        }
        return $content;
    }
}
