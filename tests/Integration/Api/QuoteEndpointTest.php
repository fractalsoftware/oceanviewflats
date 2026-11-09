<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Integration\Api;

use PHPUnit\Framework\TestCase;

final class QuoteEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        @unlink(sys_get_temp_dir() . '/ovf_quote_rate_limits.json');
    }

    protected function tearDown(): void
    {
        @unlink(sys_get_temp_dir() . '/ovf_quote_rate_limits.json');
        parent::tearDown();
    }

    public function testQuoteEndpointReturnsSuccessfulQuoteFor1606(): void
    {
        $res = $this->callEndpoint([
            'property_id' => '1606',
            'check_in' => '2026-06-01',
            'check_out' => '2026-06-04',
        ]);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);

        $data = $res['json']['data'];
        $this->assertSame('1606', $data['property_id']);
        $this->assertSame(3, $data['nights_count']);
        $this->assertTrue($data['is_valid']);
        $this->assertSame(1050000, $data['accommodation_total_cop']);
        $this->assertSame(80000, $data['cleaning_fee_cop']);
        $this->assertArrayNotHasKey('resort_fee_cop', $data);
        $this->assertSame(1130000, $data['total_cop']);
        $this->assertSame(2, $data['minimum_stay_required']);
    }

    public function testQuoteEndpointReturnsSuccessfulQuoteFor1707(): void
    {
        $res = $this->callEndpoint([
            'property_id' => '1707',
            'check_in' => '2026-06-01',
            'check_out' => '2026-06-04',
        ]);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);

        $data = $res['json']['data'];
        $this->assertSame('1707', $data['property_id']);
        $this->assertSame(3, $data['nights_count']);
        $this->assertTrue($data['is_valid']);
        $this->assertSame(1350000, $data['accommodation_total_cop']);
        $this->assertSame(100000, $data['cleaning_fee_cop']);
        $this->assertArrayNotHasKey('resort_fee_cop', $data);
        $this->assertSame(1450000, $data['total_cop']);
    }

    public function testQuoteEndpointReturns400OnMissingParameters(): void
    {
        $res = $this->callEndpoint([
            'property_id' => '1606',
            'check_in' => '2026-06-01',
        ]);

        $this->assertSame(0, $res['exitCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('Missing required parameters', $res['json']['error']);
    }

    public function testQuoteEndpointReturns400OnInvertedDateRange(): void
    {
        $res = $this->callEndpoint([
            'property_id' => '1606',
            'check_in' => '2026-06-10',
            'check_out' => '2026-06-05',
        ]);

        $this->assertSame(0, $res['exitCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('must be after check-in date', $res['json']['error']);
    }

    public function testQuoteEndpointReturns400OnInvalidProperty(): void
    {
        $res = $this->callEndpoint([
            'property_id' => 'invalid_prop',
            'check_in' => '2026-06-01',
            'check_out' => '2026-06-04',
        ]);

        $this->assertSame(0, $res['exitCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString("Unknown property identifier", $res['json']['error']);
    }

    public function testQuoteEndpointRejectsNonGetMethods(): void
    {
        $res = $this->callEndpoint([
            'property_id' => '1606',
            'check_in' => '2026-06-01',
            'check_out' => '2026-06-04',
        ], 'POST');

        $this->assertSame(0, $res['exitCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertSame('Method Not Allowed', $res['json']['error']);
    }

    /**
     * @param array<string, string> $params
     * @return array{exitCode: int, stdout: string, stderr: string, json: ?array<string, mixed>}
     */
    private function callEndpoint(array $params = [], string $method = 'GET'): array
    {
        $phpCode = sprintf(
            '$_SERVER["REQUEST_METHOD"] = %s; $_GET = %s; require %s;',
            var_export($method, true),
            var_export($params, true),
            var_export(dirname(__DIR__, 3) . '/public/api/quote.php', true)
        );

        $process = proc_open(
            ['php', '-r', $phpCode],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
            'json' => json_decode((string) $stdout, true),
        ];
    }
}
