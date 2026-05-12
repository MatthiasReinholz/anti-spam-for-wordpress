<?php
declare(strict_types=1);

final class WpPluginBaseAdminUiOperationsTest extends AsfwPluginTestCase
{
    public function test_admin_ui_is_registered_under_settings_menu(): void
    {
        WP_Plugin_Base_Admin_UI_Loader::register_page(
            array(
                'page_title' => 'Anti Spam for WordPress',
                'menu_title' => 'Anti Spam for WordPress',
                'capability' => 'manage_options',
                'parent_slug' => 'options-general.php',
                'menu_slug' => 'anti-spam-for-wordpress-admin-ui',
                'root_id' => 'anti-spam-for-wordpress-admin-ui-root',
                'plugin_slug' => 'anti-spam-for-wordpress',
                'text_domain' => 'anti-spam-for-wordpress',
                'script_handle' => 'anti-spam-for-wordpress-admin-ui',
                'style_handle' => 'anti-spam-for-wordpress-admin-ui',
                'rest_namespace' => 'anti-spam-for-wordpress/v1',
                'plugin_name' => 'Anti Spam for WordPress',
                'experimental_dataviews' => false,
            )
        );

        do_action('admin_menu');

        $topLevelMatches = array_values(
            array_filter(
                $GLOBALS['asfw_test_menu_pages'],
                static function (array $page): bool {
                    return $page['menu_slug'] === 'anti-spam-for-wordpress-admin-ui';
                }
            )
        );
        $submenuMatches = array_values(
            array_filter(
                $GLOBALS['asfw_test_submenu_pages'],
                static function (array $page): bool {
                    return $page['menu_slug'] === 'anti-spam-for-wordpress-admin-ui';
                }
            )
        );

        $this->assertSame(array(), $topLevelMatches);
        $this->assertNotSame(array(), $submenuMatches);
        $this->assertSame('options-general.php', $submenuMatches[0]['parent_slug']);
    }

    public function test_admin_urls_point_to_settings_menu(): void
    {
        $this->assertSame(
            'https://example.test/wp-admin/options-general.php?page=anti-spam-for-wordpress-admin-ui&tab=settings',
            asfw_get_admin_ui_url('settings')
        );
        $this->assertStringContainsString(
            'options-general.php?page=anti-spam-for-wordpress-admin-ui',
            asfw_settings_link(array())[0]
        );
    }

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

    public function test_settings_payload_orders_sections_and_groups_wordpress_placements_first(): void
    {
        $payload = asfw_rest_build_settings_payload();

        $this->assertSame(
            array(
                'Protection Placements',
                'Core Challenge',
                'Security Hardening',
                'Widget and Shortcode',
                'Observability and Policy',
                'Bunny Shield',
            ),
            array_column($payload['sections'], 'title')
        );

        $placements = $payload['sections'][0];
        $this->assertSame('asfw_integrations_settings_section', $placements['id']);
        $this->assertSame(
            array(
                'asfw_settings_wordpress_register_integration_field',
                'asfw_settings_wordpress_reset_password_integration_field',
                'asfw_settings_wordpress_login_integration_field',
                'asfw_settings_wordpress_comments_integration_field',
            ),
            array_slice(array_column($placements['fields'], 'id'), 0, 4)
        );
    }

    public function test_admin_ui_client_does_not_double_namespace_operation_paths(): void
    {
        $client = (string) file_get_contents(dirname(__DIR__, 2) . '/.wp-plugin-base-admin-ui/shared/api-client.js');

        $this->assertStringContainsString('function isNamespacedPath(path)', $client);
        $this->assertStringContainsString('return isNamespacedPath(path) ? normalizePath(path) : buildNamespacedPath(path);', $client);
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

    public function test_settings_payload_masks_secret_and_blank_update_keeps_current_secret(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_secret, 'current-secret-value-that-is-long-enough');
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key, 'current-bunny-api-key');

        $payload = asfw_rest_build_settings_payload();
        $secretField = null;
        $bunnyApiKeyField = null;
        foreach ($payload['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                if ($field['option'] === AntiSpamForWordPressPlugin::$option_secret) {
                    $secretField = $field;
                }

                if ($field['option'] === AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key) {
                    $bunnyApiKeyField = $field;
                }
            }
        }

        $this->assertIsArray($secretField);
        $this->assertSame('password', $secretField['type']);
        $this->assertSame('', $secretField['value']);
        $this->assertSame('Unchanged', $secretField['placeholder']);
        $this->assertIsArray($bunnyApiKeyField);
        $this->assertSame('password', $bunnyApiKeyField['type']);
        $this->assertSame('', $bunnyApiKeyField['value']);
        $this->assertSame('Unchanged', $bunnyApiKeyField['placeholder']);

        asfw_rest_operation_settings_update(
            new WP_REST_Request(
                array(
                    'values' => array(
                        AntiSpamForWordPressPlugin::$option_secret => '',
                        AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key => '',
                    ),
                )
            ),
            array()
        );

        $this->assertSame('current-secret-value-that-is-long-enough', get_option(AntiSpamForWordPressPlugin::$option_secret));
        $this->assertSame('current-bunny-api-key', get_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key));
    }

    public function test_settings_payload_includes_privacy_policy_text(): void
    {
        $payload = asfw_rest_build_settings_payload();

        $this->assertArrayHasKey('privacy_policy_text', $payload);
        $this->assertIsArray($payload['privacy_policy_text']);
        $this->assertSame('', $payload['privacy_policy_text']['text']);
        $this->assertStringNotContainsString('[Review required:', $payload['privacy_policy_text']['text']);
    }

    public function test_relevant_setting_changes_return_privacy_policy_text_update_flag(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_privacy_legal_basis, ASFW_Privacy_Policy_Text::LEGAL_BASIS_REVIEW_REQUIRED);

        $response = asfw_rest_operation_settings_update(
            new WP_REST_Request(
                array(
                    'values' => array(
                        AntiSpamForWordPressPlugin::$option_privacy_legal_basis => ASFW_Privacy_Policy_Text::LEGAL_BASIS_CONSENT,
                    ),
                )
            ),
            array()
        );

        $this->assertTrue($response['privacy_policy_text_updated']);
        $this->assertStringContainsString('Article 6(1)(a) GDPR', $response['settings']['privacy_policy_text']['text']);
    }

    public function test_irrelevant_setting_changes_do_not_return_privacy_policy_text_update_flag(): void
    {
        $response = asfw_rest_operation_settings_update(
            new WP_REST_Request(
                array(
                    'values' => array(
                        AntiSpamForWordPressPlugin::$option_footer_text => 'Protected locally',
                    ),
                )
            ),
            array()
        );

        $this->assertFalse($response['privacy_policy_text_updated']);
    }

    public function test_privacy_text_uses_sanitized_saved_values(): void
    {
        $response = asfw_rest_operation_settings_update(
            new WP_REST_Request(
                array(
                    'values' => array(
                        AntiSpamForWordPressPlugin::$option_privacy_legal_basis => 'invalid-basis',
                    ),
                )
            ),
            array()
        );

        $this->assertFalse($response['privacy_policy_text_updated']);
        $this->assertSame(
            ASFW_Privacy_Policy_Text::LEGAL_BASIS_REVIEW_REQUIRED,
            get_option(AntiSpamForWordPressPlugin::$option_privacy_legal_basis)
        );
        $this->assertSame('', $response['settings']['privacy_policy_text']['text']);
        $this->assertStringNotContainsString('[Review required:', $response['settings']['privacy_policy_text']['text']);
        $this->assertStringNotContainsString('invalid-basis', $response['settings']['privacy_policy_text']['text']);
    }

    public function test_secret_sanitizer_rejects_short_rotations_without_losing_current_secret(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_secret, 'current-secret-value-that-is-long-enough');

        $this->assertSame(
            'current-secret-value-that-is-long-enough',
            asfw_sanitize_secret_option('short')
        );
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
