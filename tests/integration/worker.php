<?php
/** Independent real-WordPress worker for the concurrency regression runner. */
declare(strict_types=1);

[$script, $bootstrap, $inputFile, $workerId] = $argv;
$input = json_decode(file_get_contents($inputFile), true, 512, JSON_THROW_ON_ERROR);
$siteUrl = parse_url($input['site_url']);
$_SERVER['HTTP_HOST'] = $siteUrl['host'] . (isset($siteUrl['port']) ? ':' . $siteUrl['port'] : '');
$_SERVER['REQUEST_URI'] = $siteUrl['path'] ?? '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_PORT'] = (string) ($siteUrl['port'] ?? 80);
if (($siteUrl['scheme'] ?? 'http') === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
require $bootstrap;
$_SERVER['REMOTE_ADDR'] = $input['ip'] ?? '203.0.113.81';
$_SERVER['HTTP_USER_AGENT'] = 'ASFW independent worker ' . $workerId;

function asfw_integration_barrier(array $input, string $workerId): void
{
    file_put_contents($input['directory'] . '/ready-' . $workerId, 'ready');
    $deadline = microtime(true) + 20;
    while (!file_exists($input['directory'] . '/release')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Worker barrier timed out.');
        }
        usleep(10000);
    }
}

try {
    $store = new ASFW_Atomic_State_Store();
    switch ($input['operation']) {
        case 'consume':
            $snapshot = $store->read($input['key']);
            asfw_integration_barrier($input, $workerId);
            $result = is_array($snapshot) ? $store->delete($input['key'], $snapshot) : false;
            break;
        case 'verify':
            $filter = static function ($query) use ($input, $workerId) {
                if (str_starts_with($query, 'DELETE FROM') && str_contains($query, $input['option_name'])) {
                    asfw_integration_barrier($input, $workerId);
                }
                return $query;
            };
            add_filter('query', $filter);
            $result = asfw_plugin_instance()->validate_solution($input['payload'], null, 'custom:integration');
            remove_filter('query', $filter);
            break;
        case 'quota':
            $waited = false;
            $filter = static function ($query) use ($input, $workerId, &$waited) {
                if (!$waited && str_starts_with($query, 'UPDATE') && str_contains($query, $input['option_name'])) {
                    $waited = true;
                    asfw_integration_barrier($input, $workerId);
                }
                return $query;
            };
            add_filter('query', $filter);
            $result = asfw_plugin_instance()->generate_challenge(null, 'low', 300, 'custom:worker-' . $workerId);
            remove_filter('query', $filter);
            break;
        case 'cache-read':
            $result = wp_using_ext_object_cache() && wp_cache_get($input['key'], 'asfw-integration') === 'persisted-between-processes';
            asfw_integration_barrier($input, $workerId);
            break;
        default:
            throw new RuntimeException('Unknown worker operation.');
    }
    file_put_contents($input['directory'] . '/result-' . $workerId . '.json', json_encode(array('success' => !is_wp_error($result) && $result !== false, 'code' => is_wp_error($result) ? $result->get_error_code() : null), JSON_THROW_ON_ERROR));
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
