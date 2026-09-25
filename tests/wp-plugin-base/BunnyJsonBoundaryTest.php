<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BunnyJsonBoundaryTest extends TestCase
{
    /** @dataProvider responseCases */
    public function test_response_processing_is_bounded_before_json_allocation(string $fixture, string $expectedCode, int $expectedStatus): void
    {
        $directory = sys_get_temp_dir() . '/asfw-bunny-json-' . bin2hex(random_bytes(12));
        if (!mkdir($directory, 0700)) {
            throw new RuntimeException('Cannot create the isolated JSON test directory.');
        }

        try {
            $process = proc_open(
                array(PHP_BINARY, '-d', 'memory_limit=128M', '-d', 'display_errors=stderr', '-d', 'log_errors=0', '-d', 'zend.exception_ignore_args=1', '-r', $this->probeSource(), dirname(__DIR__, 2), $fixture),
                array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('file', $directory . '/stderr.log', 'w')),
                $pipes
            );
            if (!is_resource($process)) {
                throw new RuntimeException('Cannot start the isolated JSON test process.');
            }
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $status = proc_close($process);
            $errors = (string) file_get_contents($directory . '/stderr.log');

            self::assertSame(0, $status, $errors);
            self::assertSame('', $errors);
            $result = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('128M', $result['memory_limit']);
            self::assertSame($expectedCode, $result['code']);
            self::assertSame($expectedStatus, $result['status']);
            self::assertSame(array('GET'), $result['request_methods']);
            self::assertSame(8 * 1024 * 1024 + 1, $result['http_limit']);
            if ('' === $expectedCode) {
                self::assertSame($result['input_digest'], $result['decoded_digest']);
            }
            if ('flat-at-byte-limit' === $fixture) {
                self::assertSame(8 * 1024 * 1024, $result['input_bytes']);
            }
            if ('provider-error' === $fixture || 'http-error' === $fixture) {
                self::assertSame('Fixture rejected', $result['message']);
            }
        } finally {
            if (file_exists($directory . '/stderr.log')) {
                unlink($directory . '/stderr.log');
            }
            rmdir($directory);
        }
    }

    public static function responseCases(): array
    {
        return array(
            'dense objects below byte limit' => array('dense-objects', 'asfw_bunny_invalid_response', 200),
            'dense arrays below byte limit' => array('dense-arrays', 'asfw_bunny_invalid_response', 200),
            'many scalar array values' => array('dense-scalars', 'asfw_bunny_invalid_response', 200),
            'structure at allocation limit' => array('structure-limit', '', 200),
            'structure beyond allocation limit' => array('structure-over-limit', 'asfw_bunny_invalid_response', 200),
            'many unique object members' => array('dense-members', 'asfw_bunny_invalid_response', 200),
            'duplicate object members count too' => array('duplicate-members', 'asfw_bunny_invalid_response', 200),
            'nested beyond depth limit' => array('too-deep', 'asfw_bunny_invalid_response', 200),
            'nesting at depth limit' => array('depth-limit', '', 200),
            'oversized JSON string' => array('oversized', 'asfw_bunny_invalid_response', 200),
            'flat content at byte limit' => array('flat-at-byte-limit', '', 200),
            'punctuation and escapes inside strings' => array('escaped-string', '', 200),
            'unterminated string' => array('unterminated-string', 'asfw_bunny_invalid_response', 200),
            'invalid escape remains a syntax error' => array('invalid-escape', 'asfw_bunny_invalid_response', 200),
            'mismatched container remains a syntax error' => array('mismatched-container', 'asfw_bunny_invalid_response', 200),
            'normal provider error envelope' => array('provider-error', 'asfw_bunny_http_error', 200),
            'normal HTTP error envelope' => array('http-error', 'asfw_bunny_http_error', 429),
            'dense HTTP error envelope' => array('dense-http-error', 'asfw_bunny_http_error', 500),
        );
    }

    private function probeSource(): string
    {
        return <<<'PHP'
[$script, $root, $fixture] = $argv;
require $root . '/tests/support/wp-stubs.php';
define('ABSPATH', $root . '/');
require $root . '/includes/class-asfw-bunny-shield-client.php';
$status = 200;
switch ($fixture) {
    case 'dense-objects':
    case 'dense-http-error':
        $body = '{"data":{"id":77,"content":"8.8.8.8","extra":[' . str_repeat('{"x":0},', 400000) . '{}]}}';
        $status = 'dense-http-error' === $fixture ? 500 : 200;
        break;
    case 'dense-arrays':
        $body = '{"data":[' . str_repeat('["x"],', 400000) . '[]]}';
        break;
    case 'dense-scalars':
        $body = '{"data":[' . str_repeat('0,', 70000) . '0]}';
        break;
    case 'structure-limit':
    case 'structure-over-limit':
        // Root object + member colon + array account for three tokens.
        $commas = 65536 - 3 + ('structure-over-limit' === $fixture ? 1 : 0);
        $body = '{"data":[' . str_repeat('0,', $commas) . '0]}';
        break;
    case 'dense-members':
        $body = '{"data":{';
        for ($index = 0; $index < 40000; ++$index) {
            $body .= '"k' . $index . '":0,';
        }
        $body .= '"last":0}}';
        break;
    case 'duplicate-members':
        $body = '{"data":{' . str_repeat('"same":0,', 40000) . '"last":0}}';
        break;
    case 'too-deep':
    case 'depth-limit':
        $depth = 'too-deep' === $fixture ? 32 : 31;
        $body = '{"data":' . str_repeat('[', $depth) . '0' . str_repeat(']', $depth) . '}';
        break;
    case 'oversized':
    case 'flat-at-byte-limit':
        $prefix = '{"data":{"id":77,"content":"';
        $suffix = '"}}';
        $size = 8 * 1024 * 1024 + ('oversized' === $fixture ? 1 : 0);
        $body = $prefix . str_repeat('x', $size - strlen($prefix) - strlen($suffix)) . $suffix;
        break;
    case 'escaped-string':
        $body = wp_json_encode(array('data' => array('id' => 77, 'content' => str_repeat('"{},:[]\\' . "\u{00e9}", 10000))));
        break;
    case 'unterminated-string':
        $body = '{"data":"unfinished';
        break;
    case 'invalid-escape':
        $body = '{"data":"\\q"}';
        break;
    case 'mismatched-container':
        $body = '{"data":[0}}';
        break;
    case 'provider-error':
    case 'http-error':
        $body = wp_json_encode(array('error' => array('success' => false, 'message' => 'Fixture rejected')));
        $status = 'http-error' === $fixture ? 429 : 200;
        break;
    default:
        throw new RuntimeException('Unknown response fixture.');
}
asfw_test_queue_http_response(array('response' => array('code' => $status), 'body' => $body));
$result = (new ASFW_Bunny_Shield_Client('fixture-key', 42))->get_access_list(77);
$error = is_wp_error($result);
$summary = array(
    'memory_limit' => ini_get('memory_limit'),
    'code' => $error ? $result->get_error_code() : '',
    'status' => $error ? $result->get_error_data()['status'] : $result['status'],
    'message' => $error ? $result->get_error_message() : '',
    'request_methods' => array_column(array_column($GLOBALS['asfw_test_http_requests'], 'args'), 'method'),
    'http_limit' => $GLOBALS['asfw_test_http_requests'][0]['args']['limit_response_size'],
    'input_bytes' => strlen($body),
    'input_digest' => hash('sha256', $body),
    'decoded_digest' => $error ? '' : hash('sha256', wp_json_encode($result['body'])),
);
echo json_encode($summary, JSON_THROW_ON_ERROR);
PHP;
    }
}
