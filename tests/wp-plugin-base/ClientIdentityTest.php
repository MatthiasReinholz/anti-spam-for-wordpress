<?php
declare(strict_types=1);

final class ClientIdentityTest extends AsfwPluginTestCase
{
    /** @dataProvider normalizedAddresses */
    public function test_addresses_have_one_canonical_identity(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->plugin()->normalize_ip($input));
    }

    public static function normalizedAddresses(): array
    {
        return array(
            array('[2001:0DB8:0:0:0:0:0:1]:443', '2001:db8::1'),
            array('for="[2001:db8::1]:443"', '2001:db8::1'),
            array('[2001:db8::1]', '2001:db8::1'),
            array('::ffff:198.51.100.25', '198.51.100.25'),
            array('198.51.100.25:443', '198.51.100.25'),
            array('[invalid]:443', ''),
            array('unknown', ''),
        );
    }

    public function test_unspecified_provider_headers_cannot_override_the_trusted_chain(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_trusted_proxies, '10.0.0.1');
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.123, 198.51.100.25';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '8.8.8.8';
        $_SERVER['HTTP_X_REAL_IP'] = '1.1.1.1';
        $_SERVER['HTTP_FORWARDED'] = 'for=9.9.9.9';
        $this->assertSame('198.51.100.25', $this->plugin()->get_client_ip_address());
    }

    public function test_selected_header_does_not_fall_back_to_attacker_controlled_alternatives(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_trusted_proxies, '10.0.0.1');
        update_option('asfw_trusted_proxy_header', 'HTTP_CF_CONNECTING_IP');
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '8.8.8.8';
        $this->assertSame('10.0.0.1', $this->plugin()->get_client_ip_address());
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.25';
        $this->assertSame('198.51.100.25', $this->plugin()->get_client_ip_address());
        $_SERVER['REMOTE_ADDR'] = '203.0.113.5';
        $this->assertSame('203.0.113.5', $this->plugin()->get_client_ip_address());
    }

    public function test_malformed_nearest_hop_invalidates_the_untrusted_chain(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_trusted_proxies, '10.0.0.1');
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '8.8.8.8, invalid, 10.0.0.1';
        $this->assertSame('10.0.0.1', $this->plugin()->get_client_ip_address());
    }

    public function test_equivalent_ipv6_notation_preserves_fingerprint_and_proxy_matching(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_trusted_proxies, '2001:0db8:0:0:0:0:0:1');
        $_SERVER['REMOTE_ADDR'] = '2001:db8::1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '2001:db8:1::2';
        $first = $this->plugin()->get_client_fingerprint();
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '[2001:0DB8:0001:0:0:0:0:2]:443';
        $this->assertSame('2001:db8:1::2', $this->plugin()->get_client_ip_address());
        $this->assertSame($first, $this->plugin()->get_client_fingerprint());
    }
    public function test_malformed_forwarded_segments_cannot_be_skipped_to_reach_a_spoofed_client(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_trusted_proxies, '10.0.0.1');
        update_option('asfw_trusted_proxy_header', 'HTTP_FORWARDED');
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        foreach (array('for=8.8.8.8, proto=https', 'for=8.8.8.8; for=1.1.1.1') as $header) {
            $_SERVER['HTTP_FORWARDED'] = $header;
            $this->assertSame('10.0.0.1', $this->plugin()->get_client_ip_address());
        }
    }

}
