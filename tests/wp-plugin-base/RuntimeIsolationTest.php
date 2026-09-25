<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Verifies coexistence with a foundation consumer using unprefixed classes. */
final class RuntimeIsolationTest extends TestCase
{
    public function test_actual_plugin_loaded_before_legacy_consumer_keeps_runtime_state_private(): void
    {
        $this->assertIndependentConsumers('plugin-first');
    }

    public function test_legacy_consumer_loaded_before_actual_plugin_keeps_runtime_state_private(): void
    {
        $this->assertIndependentConsumers('legacy-first');
    }

    private function assertIndependentConsumers(string $order): void
    {
        $directory = sys_get_temp_dir() . '/asfw-runtime-isolation-' . bin2hex(random_bytes(12));
        if (!mkdir($directory, 0700)) {
            throw new RuntimeException('Cannot create the isolated consumer directory.');
        }

        try {
            $process = proc_open(
                array(PHP_BINARY, '-d', 'display_errors=stderr', '-r', $this->probeSource(), dirname(__DIR__, 2), $directory, $order),
                array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('file', $directory . '/stderr.log', 'w')),
                $pipes
            );
            if (!is_resource($process)) {
                throw new RuntimeException('Cannot start the isolated PHP runtime.');
            }
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $status = proc_close($process);
            $errors = (string) file_get_contents($directory . '/stderr.log');

            self::assertSame(0, $status, $errors . (string) $output);
            self::assertSame('', $errors);
            $result = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($result);
            self::assertTrue($result['prefixed_registry_loaded']);
            self::assertTrue($result['legacy_registry_loaded']);
            self::assertSame('admin', $result['plugin_manifest']['visibility']);
            self::assertSame('public', $result['legacy_manifest']['visibility']);
            self::assertSame('/admin/settings', $result['plugin_manifest']['route']);
            self::assertSame('/legacy-settings', $result['legacy_manifest']['route']);
            self::assertSame(403, $result['plugin_permission']['status']);
            self::assertSame('wp_plugin_base_rest_forbidden', $result['plugin_permission']['code']);
            self::assertTrue($result['legacy_permission']);
            self::assertTrue($result['plugin_callback_contains_settings']);
            self::assertSame(array('source' => 'legacy-consumer'), $result['legacy_callback']);
            self::assertFalse($result['plugin_namespace_contains_legacy_route']);
            self::assertFalse($result['legacy_namespace_contains_plugin_route']);
            self::assertSame(array('settings_page_anti-spam-for-wordpress-admin-ui'), $result['plugin_admin_pages']);
            self::assertSame(array('settings_page_legacy-consumer-admin-ui'), $result['legacy_admin_pages']);
        } finally {
            $entries = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($entries as $entry) {
                if ($entry->isDir() && !$entry->isLink()) {
                    rmdir($entry->getPathname());
                } else {
                    unlink($entry->getPathname());
                }
            }
            rmdir($directory);
        }
    }

    private function probeSource(): string
    {
        return <<<'PHP'
[$script, $root, $temporary, $order] = $argv;
$legacy = $temporary . '/legacy-consumer';
$foundation = $root . '/.wp-plugin-base';
foreach (array(
    'PLUGIN_SLUG' => 'legacy-consumer',
    'PLUGIN_NAME' => 'Legacy Consumer',
    'MAIN_PLUGIN_FILE' => 'legacy-consumer.php',
    'REST_API_NAMESPACE' => 'legacy-consumer/v1',
    'REST_ABILITIES_ENABLED' => 'false',
    'ADMIN_UI_EXPERIMENTAL_DATAVIEWS' => 'false',
    'WP_PLUGIN_BASE_RUNTIME_TEMPLATE' => 'true',
    'RUNTIME_CLASS_PREFIX' => '',
) as $name => $value) {
    putenv($name . '=' . $value);
}

// Use the real foundation renderer and unprefixed runtime templates for the
// second consumer, rather than mocking its registry or permission classes.
$render = static function (string $source) use ($foundation): string {
    $argv = array('render_template.php', $source);
    ob_start();
    try {
        require $foundation . '/scripts/lib/render_template.php';
        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
};
foreach (array('rest-operations', 'admin-ui') as $pack) {
    $source = $foundation . '/templates/child/' . $pack . '-pack/lib/wp-plugin-base/' . $pack;
    $destination = $legacy . '/lib/wp-plugin-base/' . $pack;
    if (!mkdir($destination, 0700, true)) {
        throw new RuntimeException('Cannot create the legacy runtime directory.');
    }
    foreach (glob($source . '/*.php') as $template) {
        file_put_contents($destination . '/' . basename($template), $render($template));
    }
}
mkdir($legacy . '/includes/rest-operations', 0700, true);
file_put_contents($legacy . '/includes/rest-operations/bootstrap.php', <<<'MANIFEST'
<?php
return array(array(
    'id' => 'settings.read',
    'route' => '/legacy-settings',
    'methods' => 'GET',
    'visibility' => 'public',
    'callback' => static function () { return array('source' => 'legacy-consumer'); },
));
MANIFEST
);
mkdir($legacy . '/includes/admin-ui', 0700, true);
file_put_contents($legacy . '/includes/admin-ui/bootstrap.php', <<<'ADMIN'
<?php
WP_Plugin_Base_Admin_UI_Loader::register_page(array(
    'page_title' => 'Legacy Consumer', 'menu_title' => 'Legacy Consumer',
    'capability' => 'manage_options', 'parent_slug' => 'options-general.php',
    'menu_slug' => 'legacy-consumer-admin-ui', 'root_id' => 'legacy-consumer-root',
    'plugin_slug' => 'legacy-consumer', 'text_domain' => 'legacy-consumer',
    'script_handle' => 'legacy-consumer-admin-ui', 'style_handle' => 'legacy-consumer-admin-ui',
    'rest_namespace' => 'legacy-consumer/v1', 'plugin_name' => 'Legacy Consumer',
    'experimental_dataviews' => false,
));
ADMIN
);

define('ABSPATH', $root . '/tests/support/wordpress/');
define('WP_CLI', true); // Load actual admin bootstraps with the existing non-admin stubs.
require $root . '/tests/support/wp-stubs.php';
function esc_url($value) { return esc_url_raw($value); }
$GLOBALS['asfw_test_user_logged_in'] = true; // Authenticated, without manage_options.

$loadLegacy = static function () use ($legacy): void {
    require $legacy . '/lib/wp-plugin-base/rest-operations/bootstrap.php';
    require $legacy . '/lib/wp-plugin-base/admin-ui/bootstrap.php';
};
if ($order === 'legacy-first') {
    $loadLegacy();
    require $root . '/anti-spam-for-wordpress.php';
} else {
    require $root . '/anti-spam-for-wordpress.php';
    $loadLegacy();
}
do_action('rest_api_init');
do_action('admin_menu');

$routes = $GLOBALS['asfw_test_rest_routes'];
$pluginRoute = $routes['anti-spam-for-wordpress/v1/admin/settings'];
$legacyRoute = $routes['legacy-consumer/v1/legacy-settings'];
$request = new WP_REST_Request();
$pluginPermission = $pluginRoute['permission_callback']($request);
$pluginResult = $pluginRoute['callback']($request);
$legacyResult = $legacyRoute['callback']($request);
$findRead = static function (array $operations): array {
    foreach ($operations as $operation) {
        if ($operation['id'] === 'settings.read') { return $operation; }
    }
    throw new RuntimeException('The settings.read manifest is missing.');
};
$pageKeys = static function (string $class): array {
    $property = new ReflectionProperty($class, 'pages');
    // ReflectionProperty::setAccessible is unnecessary from PHP 8.1 onward.
    if (PHP_VERSION_ID < 80100) { $property->setAccessible(true); }
    $keys = array_keys($property->getValue());
    sort($keys);
    return $keys;
};
$pluginData = $pluginResult instanceof WP_REST_Response ? $pluginResult->get_data() : $pluginResult;
echo json_encode(array(
    'prefixed_registry_loaded' => class_exists('ASFW_WP_Plugin_Base_REST_Operations_Registry', false),
    'legacy_registry_loaded' => class_exists('WP_Plugin_Base_REST_Operations_Registry', false),
    'plugin_manifest' => $findRead(ASFW_WP_Plugin_Base_REST_Operations_Registry::all()),
    'legacy_manifest' => $findRead(WP_Plugin_Base_REST_Operations_Registry::all()),
    'plugin_permission' => $pluginPermission instanceof WP_Error
        ? array('code' => $pluginPermission->get_error_code(), 'status' => $pluginPermission->get_error_data()['status'])
        : array('code' => '', 'status' => 200),
    'legacy_permission' => $legacyRoute['permission_callback']($request),
    // The shared WordPress stub keeps the last registered method (POST) here.
    // Executing it directly checks callback ownership, independently of permission.
    'plugin_callback_contains_settings' => is_array($pluginData) && isset($pluginData['settings']['sections']),
    'legacy_callback' => $legacyResult instanceof WP_REST_Response ? $legacyResult->get_data() : $legacyResult,
    'plugin_namespace_contains_legacy_route' => isset($routes['anti-spam-for-wordpress/v1/legacy-settings']),
    'legacy_namespace_contains_plugin_route' => isset($routes['legacy-consumer/v1/admin/settings']),
    'plugin_admin_pages' => $pageKeys('ASFW_WP_Plugin_Base_Admin_UI_Loader'),
    'legacy_admin_pages' => $pageKeys('WP_Plugin_Base_Admin_UI_Loader'),
), JSON_THROW_ON_ERROR);
PHP;
    }
}
