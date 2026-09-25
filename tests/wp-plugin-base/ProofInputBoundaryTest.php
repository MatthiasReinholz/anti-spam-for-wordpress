<?php
declare(strict_types=1);

final class ProofInputBoundaryTest extends AsfwPluginTestCase
{
    public static function nonStringPayloads(): array
    {
        return array(
            'array' => array(array('unexpected')),
            'nested array' => array(array('proof' => array('unexpected'))),
            'object' => array(new stdClass()),
            'null' => array(null),
            'boolean' => array(true),
            'integer' => array(123),
            'float' => array(1.5),
        );
    }

    /** @dataProvider nonStringPayloads */
    public function test_non_string_posted_proofs_are_rejected_without_warnings($payload): void
    {
        $challenge = $this->generateChallenge('custom:contact');
        $validPayload = $this->solveChallenge($challenge);
        $before = $GLOBALS['asfw_test_atomic_rows'];
        $_POST['asfw'] = $payload;

        $this->withoutWarnings(function () use ($before, $validPayload): void {
            $this->assertSame('', asfw_get_posted_payload('asfw'));
            $this->assertInstanceOf(WP_Error::class, $this->plugin()->validate_solution(asfw_get_posted_payload('asfw'), null, 'custom:contact'));
            $this->assertSame($before, $GLOBALS['asfw_test_atomic_rows']);
            $this->assertTrue($this->plugin()->validate_solution($validPayload, null, 'custom:contact'));
        });
    }

    /** @dataProvider nonStringPayloads */
    public function test_direct_verifier_calls_reject_non_string_proofs_without_warnings($payload): void
    {
        $before = $GLOBALS['asfw_test_atomic_rows'];
        $this->withoutWarnings(function () use ($payload, $before): void {
            $this->assertInstanceOf(WP_Error::class, $this->plugin()->decode_payload($payload));
            $this->assertInstanceOf(WP_Error::class, $this->plugin()->validate_solution($payload, null, 'custom:contact'));
            $this->assertFalse($this->plugin()->verify_solution($payload, null, 'custom:contact'));
            $this->assertSame($before, $GLOBALS['asfw_test_atomic_rows']);
        });
    }

    public static function arraySaltParameters(): array
    {
        return array(
            'context array' => array('context', '[]'),
            'nested context array' => array('context', '[nested][]'),
            'challenge ID array' => array('challenge_id', '[]'),
            'expiry array' => array('expires', '[]'),
        );
    }

    /** @dataProvider arraySaltParameters */
    public function test_array_salt_parameters_are_rejected_without_consuming_the_original_proof(string $field, string $suffix): void
    {
        $challenge = $this->generateChallenge('custom:contact');
        $validPayload = $this->solveChallenge($challenge);
        $data = json_decode(base64_decode($validPayload), true);
        $data['salt'] = str_replace($field . '=', $field . $suffix . '=', $data['salt']);
        $before = $GLOBALS['asfw_test_atomic_rows'];

        $this->withoutWarnings(function () use ($data, $before, $validPayload): void {
            $result = $this->plugin()->validate_solution(base64_encode(json_encode($data)), null, 'custom:contact');
            $this->assertInstanceOf(WP_Error::class, $result);
            $this->assertSame('asfw_invalid_salt', $result->get_error_code());
            $this->assertSame($before, $GLOBALS['asfw_test_atomic_rows']);
            $this->assertTrue($this->plugin()->validate_solution($validPayload, null, 'custom:contact'));
        });
    }

    public function test_legitimate_string_proofs_and_numeric_zero_remain_supported(): void
    {
        $challenge = $this->generateChallenge('custom:contact');
        $validPayload = $this->solveChallenge($challenge);
        $data = json_decode(base64_decode($validPayload), true);
        $_POST['asfw'] = '  ' . $validPayload . '  ';

        $this->withoutWarnings(function () use ($validPayload, $data): void {
            $this->assertSame($validPayload, asfw_get_posted_payload('asfw'));
            $this->assertTrue($this->plugin()->validate_solution(asfw_get_posted_payload('asfw'), null, 'custom:contact'));
            foreach (array(0, '0') as $number) {
                $data['number'] = $number;
                $decoded = $this->plugin()->decode_payload(base64_encode(json_encode($data)));
                $this->assertIsArray($decoded);
                $this->assertSame('0', $decoded['number']);
            }
        });
    }

    private function withoutWarnings(callable $callback): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): void {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $callback();
        } finally {
            restore_error_handler();
        }
    }
}
