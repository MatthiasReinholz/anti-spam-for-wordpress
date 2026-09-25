<?php
declare(strict_types=1);

final class LifecyclePersistenceTest extends AsfwPluginTestCase
{
    public function test_missing_false_default_is_inserted_and_failed_insertion_remains_distinct(): void
    {
        $option = 'asfw_integration_false_fixture';
        $this->assertFalse(update_option($option, false), 'WordPress treats a missing false update as unchanged.');
        $this->assertNull(get_option($option, null));
        $GLOBALS['asfw_test_option_write_failures'][$option] = true;
        $this->assertFalse(asfw_persist_initial_option($option, false));
        $this->assertNull(get_option($option, null));
        unset($GLOBALS['asfw_test_option_write_failures'][$option]);
        $this->assertTrue(asfw_persist_initial_option($option, false));
        $this->assertSame(false, get_option($option, null));
    }

    public static function booleanRepresentations(): array
    {
        return array(
            'false boolean' => array(false, false, true),
            'false integer' => array(false, 0, true),
            'false database string' => array(false, '0', true),
            'false empty string' => array(false, '', true),
            'true boolean' => array(true, true, true),
            'true integer' => array(true, 1, true),
            'true database string' => array(true, '1', true),
            'wrong boolean' => array(false, true, false),
            'unrecognized truthy string' => array(true, 'unexpected', false),
        );
    }

    /** @dataProvider booleanRepresentations */
    public function test_verified_writes_accept_only_valid_boolean_storage_representations(bool $requested, $stored, bool $expected): void
    {
        $option = 'asfw_integration_boolean_fixture';
        add_option($option, 'previous');
        $normalize = static function ($name) use ($option, $stored): void {
            if ($name === $option) {
                $GLOBALS['asfw_test_options'][$name] = $stored;
            }
        };
        add_action('updated_option', $normalize, 10, 1);
        try {
            $this->assertSame($expected, asfw_persist_initial_option($option, $requested));
        } finally {
            remove_action('updated_option', $normalize, 10);
        }
    }

    public function test_one_failed_legacy_retention_write_does_not_replace_it_with_the_default(): void
    {
        delete_option(ASFW_Event_Store::OPTION_RETENTION_DAYS);
        update_option(ASFW_Event_Store::OPTION_RETENTION_DAYS_LEGACY, '90');
        $GLOBALS['asfw_test_option_write_failures'][ASFW_Event_Store::OPTION_RETENTION_DAYS] = 1;

        $this->assertFalse(asfw_initialize_site());
        $this->assertFalse(get_option(ASFW_Event_Store::OPTION_RETENTION_DAYS));
        $this->assertNotSame('1', get_option('asfw_site_initialized'));

        $this->assertTrue(asfw_initialize_site());
        $this->assertSame('90', get_option(ASFW_Event_Store::OPTION_RETENTION_DAYS));
        $this->assertSame('1', get_option('asfw_site_initialized'));
    }

    public function test_failed_legacy_migration_retries_before_defaults_can_replace_protection(): void
    {
        delete_option('asfw_migration_completed');
        delete_option('asfw_integration_wordpress_login');
        update_option('altcha_secret', 'legacy-secret');
        update_option('altcha_integration_wordpress_login', 'captcha');
        update_option('asfw_integration_wordpress_comments', '');
        $GLOBALS['asfw_test_option_write_failures']['asfw_integration_wordpress_login'] = true;

        $this->assertFalse(asfw_initialize_site());
        $this->assertFalse(get_option('asfw_migration_completed'));
        $this->assertFalse(get_option('asfw_integration_wordpress_login'));
        $this->assertNotSame('1', get_option('asfw_site_initialized'));

        $GLOBALS['asfw_test_option_write_failures'] = array();
        asfw_maybe_initialize_site();
        $this->assertSame('captcha', get_option('asfw_integration_wordpress_login'));
        $this->assertSame('', get_option('asfw_integration_wordpress_comments'));
        $this->assertSame('test-secret', get_option('asfw_secret'));
        $this->assertSame(ASFW_VERSION, get_option('asfw_migration_completed'));
        $this->assertSame('1', get_option('asfw_site_initialized'));
    }

    public static function requiredOptions(): array
    {
        return array(
            'challenge quota' => array('asfw_rate_limit_max_challenges', '30'),
            'minimum time' => array('asfw_min_submit_time', '3'),
            'integration default' => array('asfw_integration_wordpress_comments', 'captcha'),
            'schema version' => array(ASFW_Event_Store::OPTION_DB_VERSION, (string) ASFW_Event_Store::DB_VERSION),
            'retention' => array(ASFW_Event_Store::OPTION_RETENTION_DAYS, '30'),
        );
    }

    /** @dataProvider requiredOptions */
    public function test_required_initialization_writes_do_not_report_success_when_persistence_fails(string $option, string $expected): void
    {
        delete_option($option);
        update_option('asfw_site_initialized', '1');
        $GLOBALS['asfw_test_option_write_failures'][$option] = true;
        $this->assertFalse(asfw_initialize_site());
        $this->assertNotSame('1', get_option('asfw_site_initialized'));
        $this->assertFalse(get_option($option));

        $GLOBALS['asfw_test_option_write_failures'] = array();
        asfw_maybe_initialize_site();
        $this->assertSame($expected, get_option($option));
        $this->assertSame('1', get_option('asfw_site_initialized'));
    }

    public static function missingSecrets(): array
    {
        return array('absent' => array(null), 'blank' => array(''), 'whitespace' => array('   '));
    }

    /** @dataProvider missingSecrets */
    public function test_secret_write_failures_remain_retryable_and_recover($secret): void
    {
        if ($secret === null) {
            delete_option('asfw_secret');
        } else {
            update_option('asfw_secret', $secret);
        }
        $GLOBALS['asfw_test_option_write_failures']['asfw_secret'] = true;
        $this->assertFalse(asfw_initialize_site());
        $this->assertNotSame('1', get_option('asfw_site_initialized'));

        $GLOBALS['asfw_test_option_write_failures'] = array();
        asfw_maybe_initialize_site();
        $this->assertSame(64, strlen(get_option('asfw_secret')));
        $this->assertSame('1', get_option('asfw_site_initialized'));
    }

    public function test_failed_control_plane_legacy_write_cannot_fall_through_to_a_different_default(): void
    {
        update_option('asfw_bunny_enabled', true);
        delete_option('asfw_feature_bunny_shield_enabled');
        $GLOBALS['asfw_test_option_write_failures']['asfw_feature_bunny_shield_enabled'] = true;
        $this->assertFalse(asfw_initialize_site());
        $this->assertFalse(get_option('asfw_feature_bunny_shield_enabled'));
        $this->assertNotSame('1', get_option('asfw_site_initialized'));

        $GLOBALS['asfw_test_option_write_failures'] = array();
        asfw_maybe_initialize_site();
        $this->assertTrue(get_option('asfw_feature_bunny_shield_enabled'));
        $this->assertSame('off', get_option('asfw_feature_bunny_shield_mode'), 'An explicit existing mode must be preserved.');
        $this->assertSame('1', get_option('asfw_site_initialized'));
    }

    public function test_failed_migration_marker_does_not_complete_initialization(): void
    {
        delete_option('asfw_migration_completed');
        $GLOBALS['asfw_test_option_write_failures']['asfw_migration_completed'] = true;
        $this->assertFalse(asfw_initialize_site());
        $this->assertFalse(get_option('asfw_migration_completed'));
        $this->assertNotSame('1', get_option('asfw_site_initialized'));

        $GLOBALS['asfw_test_option_write_failures'] = array();
        $this->assertTrue(asfw_initialize_site());
    }

    public function test_failed_completion_marker_does_not_report_success(): void
    {
        $failCompletion = static function ($option, $oldValue, $newValue): void {
            if ($option === 'asfw_site_initialized' && $newValue === '0') {
                $GLOBALS['asfw_test_option_write_failures'][$option] = true;
            }
        };
        add_action('updated_option', $failCompletion, 10, 3);
        try {
            $this->assertFalse(asfw_initialize_site());
            $this->assertNotSame('1', get_option('asfw_site_initialized'));
        } finally {
            remove_action('updated_option', $failCompletion, 10);
        }
        $GLOBALS['asfw_test_option_write_failures'] = array();
        $this->assertTrue(asfw_initialize_site());
    }
}
