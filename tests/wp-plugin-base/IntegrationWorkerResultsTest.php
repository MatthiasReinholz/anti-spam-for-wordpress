<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/integration/worker-results.php';

final class IntegrationWorkerResultsTest extends TestCase
{
    public function test_visible_empty_result_is_not_read_while_worker_is_still_writing(): void
    {
        $this->withWorker('slow-write', static function ($process, string $directory): void {
            $deadline = microtime(true) + 5;
            while (!is_file($directory . '/opened')) {
                if (microtime(true) > $deadline) {
                    self::fail('Fixture did not open the result file.');
                }
                usleep(1000);
            }
            self::assertSame('', file_get_contents($directory . '/result-0.json'));
            touch($directory . '/release-write');
            // The old file-existence barrier decodes this empty file immediately.
            self::assertSame(array(array('success' => true, 'code' => null)), asfw_integration_worker_results(array($process), $directory));
        });
    }

    public function test_failure_after_writing_a_result_is_not_reported_as_success(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Worker 0 exited with status 7: fixture worker failed');
        $this->withWorker('failed', static function ($process, string $directory): void {
            asfw_integration_worker_results(array($process), $directory);
        });
    }

    public function test_successful_exit_without_result_is_reported(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Worker 0 exited without a result.');
        $this->withWorker('missing', static function ($process, string $directory): void {
            asfw_integration_worker_results(array($process), $directory);
        });
    }

    public function test_invalid_completed_result_has_worker_context(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Worker 0 returned invalid result JSON.');
        $this->withWorker('invalid', static function ($process, string $directory): void {
            asfw_integration_worker_results(array($process), $directory);
        });
    }

    public function test_completed_result_must_follow_the_worker_contract(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Worker 0 returned an invalid result shape.');
        $this->withWorker('wrong-shape', static function ($process, string $directory): void {
            asfw_integration_worker_results(array($process), $directory);
        });
    }

    public function test_nonterminating_worker_keeps_the_completion_deadline(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Workers did not complete after barrier release.');
        $this->withWorker('timeout', static function ($process, string $directory): void {
            asfw_integration_worker_results(array($process), $directory, 0.05);
        });
    }

    private function withWorker(string $mode, callable $assertions): void
    {
        $directory = sys_get_temp_dir() . '/asfw-worker-result-test-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $source = <<<'PHP'
[$script, $directory, $mode] = $argv;
$path = $directory . '/result-0.json';
if ($mode === 'missing') { exit(0); }
if ($mode === 'invalid') { file_put_contents($path, '{'); exit(0); }
if ($mode === 'wrong-shape') { file_put_contents($path, '{"success":"yes","code":null}'); exit(0); }
if ($mode === 'timeout') { sleep(5); exit(0); }
if ($mode === 'slow-write') {
    $output = fopen($path, 'wb');
    touch($directory . '/opened');
    $deadline = microtime(true) + 5;
    while (!is_file($directory . '/release-write')) {
        if (microtime(true) > $deadline) { throw new RuntimeException('Fixture release timed out.'); }
        usleep(1000);
    }
    usleep(300000);
    fwrite($output, '{"success":true,"code":null}');
    fclose($output);
} else {
    file_put_contents($path, '{"success":true,"code":null}');
    fwrite(STDERR, "fixture worker failed\n");
    exit(7);
}
PHP;
        $process = null;
        try {
            $process = proc_open(array(PHP_BINARY, '-r', $source, $directory, $mode), array(
                0 => array('file', '/dev/null', 'r'),
                1 => array('file', $directory . '/stdout-0', 'w'),
                2 => array('file', $directory . '/stderr-0', 'w'),
            ), $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException('Cannot start the worker result fixture.');
            }
            $assertions($process, $directory);
        } finally {
            if (is_resource($process)) {
                if (proc_get_status($process)['running']) { proc_terminate($process); }
                proc_close($process);
            }
            foreach (glob($directory . '/*') as $path) { unlink($path); }
            rmdir($directory);
        }
    }
}
