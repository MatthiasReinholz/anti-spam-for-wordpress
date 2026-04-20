<?php
declare(strict_types=1);

final class WpPluginBaseAdminUiOperationsTest extends AsfwPluginTestCase
{
    public function test_admin_ui_rest_operations_are_registered_and_protected(): void
    {
        $this->assertArrayHasKey('anti-spam-for-wordpress/v1/admin/settings', $GLOBALS['asfw_test_rest_routes']);
        $this->assertArrayHasKey('anti-spam-for-wordpress/v1/admin/events', $GLOBALS['asfw_test_rest_routes']);
        $this->assertArrayHasKey('anti-spam-for-wordpress/v1/admin/analytics', $GLOBALS['asfw_test_rest_routes']);

        $settingsRoute = $GLOBALS['asfw_test_rest_routes']['anti-spam-for-wordpress/v1/admin/settings'];
        $permission = $settingsRoute['permission_callback'];
        $this->assertTrue(is_callable($permission));

        $permissionResult = $permission(new WP_REST_Request());
        $this->assertInstanceOf(WP_Error::class, $permissionResult);
        $this->assertSame('wp_plugin_base_rest_authentication_required', $permissionResult->get_error_code());
    }

    public function test_settings_update_operation_keeps_legacy_option_mirroring(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled, 0);
        update_option(AntiSpamForWordPressPlugin::$option_bunny_enabled, 0);

        $request = new WP_REST_Request(
            array(
                'values' => array(
                    AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled => 1,
                ),
            )
        );

        $response = asfw_rest_operation_settings_update($request, array());

        $this->assertIsArray($response);
        $this->assertSame(1, (int) get_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled, 0));
        $this->assertSame(1, (int) get_option(AntiSpamForWordPressPlugin::$option_bunny_enabled, 0));
    }

    public function test_events_and_analytics_operations_return_structured_payloads(): void
    {
        update_option('asfw_feature_event_logging_enabled', 1);
        update_option('asfw_feature_event_logging_mode', 'log');
        update_option('asfw_feature_event_logging_scope_mode', 'all');

        $store = ASFW_Control_Plane::store();
        $store->record_event(
            'challenge_issued',
            array(
                'event_status' => 'issued',
                'event_context' => 'contact-form-7',
                'module_name' => 'core',
                'details' => array('foo' => 'bar'),
            )
        );

        $eventsResponse = asfw_rest_operation_events_list(new WP_REST_Request(), array());
        $analyticsResponse = asfw_rest_operation_analytics_read(new WP_REST_Request(), array());

        $this->assertIsArray($eventsResponse);
        $this->assertArrayHasKey('items', $eventsResponse);
        $this->assertArrayHasKey('pagination', $eventsResponse);

        $this->assertIsArray($analyticsResponse);
        $this->assertArrayHasKey('sample', $analyticsResponse);
        $this->assertArrayHasKey('daily_challenges', $analyticsResponse);
    }
}
