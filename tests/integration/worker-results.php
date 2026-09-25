<?php
/** Collect completed integration workers without racing their result writes. */
declare(strict_types=1);

/** @param array<int,resource> $processes Workers owned and reaped by the caller. */
function asfw_integration_worker_results(array $processes, string $directory, float $timeout = 25): array
{
    $pending = $processes;
    $deadline = microtime(true) + $timeout;
    while ($pending !== array()) {
        foreach ($pending as $index => $process) {
            $status = proc_get_status($process);
            if ($status['running']) {
                continue;
            }
            // Cache completion by removing the handle: PHP before 8.3 only
            // reports the actual exit code on the first status read after exit.
            unset($pending[$index]);
            if ($status['exitcode'] !== 0) {
                $diagnostics = '';
                foreach (array('stdout-', 'stderr-') as $prefix) {
                    $path = $directory . '/' . $prefix . $index;
                    if (is_file($path)) {
                        $diagnostics .= (string) file_get_contents($path, false, null, 0, 4096);
                    }
                }
                throw new RuntimeException('Worker ' . $index . ' exited with status ' . $status['exitcode'] . ': ' . trim($diagnostics));
            }
        }
        if ($pending !== array()) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Workers did not complete after barrier release.');
            }
            usleep(10000);
        }
    }

    $results = array();
    foreach (array_keys($processes) as $index) {
        $path = $directory . '/result-' . $index . '.json';
        if (!is_file($path)) {
            throw new RuntimeException('Worker ' . $index . ' exited without a result.');
        }
        try {
            $result = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Worker ' . $index . ' returned invalid result JSON.', 0, $error);
        }
        if (!is_array($result) || !isset($result['success']) || !is_bool($result['success'])
            || !array_key_exists('code', $result) || ($result['code'] !== null && !is_string($result['code']))) {
            throw new RuntimeException('Worker ' . $index . ' returned an invalid result shape.');
        }
        $results[] = $result;
    }
    return $results;
}
