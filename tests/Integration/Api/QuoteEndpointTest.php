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

    public function testQuoteEndpointResolvesDatabaseSeasonalRateTiersAndMinimumStay(): void
    {
        $autoloadPath = var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true);
        $prependCode = <<<PHP
require_once {$autoloadPath};
\$pdo = new PDO('sqlite::memory:');
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
\$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
\$pdo->exec("
    CREATE TABLE property_rates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        property_id TEXT NOT NULL,
        start_date TEXT NOT NULL,
        end_date TEXT NOT NULL,
        season_name TEXT NOT NULL,
        price_per_night REAL NOT NULL,
        min_stay INTEGER NOT NULL DEFAULT 2,
        cleaning_fee REAL NOT NULL DEFAULT 0.0,
        created_by INTEGER DEFAULT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )
");
\$pdo->exec("
    INSERT INTO property_rates (property_id, start_date, end_date, season_name, price_per_night, min_stay)
    VALUES ('1606', '2026-07-01', '2026-07-31', 'July Peak DB Tier', 520000, 4)
");
\$GLOBALS['TEST_PDO'] = \$pdo;
PHP;

        // 1. 3-night stay violates the DB tier's 4-night minimum stay
        $resShort = $this->callEndpoint([
            'property_id' => '1606',
            'check_in' => '2026-07-10',
            'check_out' => '2026-07-13',
        ], 'GET', $prependCode);

        $this->assertSame(0, $resShort['exitCode'], $resShort['stderr']);
        $this->assertIsArray($resShort['json']);
        $this->assertTrue($resShort['json']['success']);
        $shortData = $resShort['json']['data'];
        $this->assertSame(3, $shortData['nights_count']);
        $this->assertSame(1560000, $shortData['accommodation_total_cop']);
        $this->assertSame(80000, $shortData['cleaning_fee_cop']);
        $this->assertSame(1640000, $shortData['total_cop']);
        $this->assertSame(4, $shortData['minimum_stay_required']);
        $this->assertFalse($shortData['is_valid']);
        $this->assertSame('min_stay', $shortData['violation_reason']);

        // 2. 4-night stay satisfies the DB tier's minimum stay
        $resValid = $this->callEndpoint([
            'property_id' => '1606',
            'check_in' => '2026-07-10',
            'check_out' => '2026-07-14',
        ], 'GET', $prependCode);

        $this->assertSame(0, $resValid['exitCode'], $resValid['stderr']);
        $this->assertIsArray($resValid['json']);
        $this->assertTrue($resValid['json']['success']);
        $validData = $resValid['json']['data'];
        $this->assertSame(4, $validData['nights_count']);
        $this->assertSame(2080000, $validData['accommodation_total_cop']);
        $this->assertSame(80000, $validData['cleaning_fee_cop']);
        $this->assertSame(2160000, $validData['total_cop']);
        $this->assertSame(4, $validData['minimum_stay_required']);
        $this->assertTrue($validData['is_valid']);
        $this->assertNull($validData['violation_reason']);
    }

    public function testQuoteEndpointActionRatesReturnsActivePropertyRateStatusAndTiers(): void
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $tierStart = (new \DateTimeImmutable('today'))->modify('-2 days')->format('Y-m-d');
        $tierEnd = (new \DateTimeImmutable('today'))->modify('+5 days')->format('Y-m-d');

        $autoloadPath = var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true);
        $tierStartExport = var_export($tierStart, true);
        $tierEndExport = var_export($tierEnd, true);

        $prependCode = <<<PHP
require_once {$autoloadPath};
\$pdo = new PDO('sqlite::memory:');
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
\$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
\$pdo->exec("
    CREATE TABLE property_rates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        property_id TEXT NOT NULL,
        start_date TEXT NOT NULL,
        end_date TEXT NOT NULL,
        season_name TEXT NOT NULL,
        price_per_night REAL NOT NULL,
        min_stay INTEGER NOT NULL DEFAULT 2,
        cleaning_fee REAL NOT NULL DEFAULT 0.0,
        created_by INTEGER DEFAULT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )
");
\$stmt = \$pdo->prepare("
    INSERT INTO property_rates (property_id, start_date, end_date, season_name, price_per_night, min_stay)
    VALUES ('1606', :start_date, :end_date, 'Active Promo Season', 410000, 3)
");
\$stmt->execute(['start_date' => {$tierStartExport}, 'end_date' => {$tierEndExport}]);
\$GLOBALS['TEST_PDO'] = \$pdo;
PHP;

        $res = $this->callEndpoint(['action' => 'rates'], 'GET', $prependCode);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);

        $data = $res['json']['data'];
        $this->assertArrayHasKey('properties', $data);
        $this->assertArrayHasKey('tiers', $data);

        $prop1606 = $data['properties']['1606'];
        $this->assertSame('1606', $prop1606['property_id']);
        $this->assertSame(410000, $prop1606['current_nightly_rate_cop']);
        $this->assertSame(350000, $prop1606['default_nightly_rate_cop']);
        $this->assertSame(80000, $prop1606['cleaning_fee_cop']);
        $this->assertSame(2, $prop1606['default_minimum_stay']);
        $this->assertTrue($prop1606['is_seasonal_tier_active']);
        $this->assertSame('Active Promo Season', $prop1606['active_tier_name']);
        $this->assertSame($tierEnd, $prop1606['active_tier_end_date']);

        $prop1707 = $data['properties']['1707'];
        $this->assertSame('1707', $prop1707['property_id']);
        $this->assertSame(450000, $prop1707['default_nightly_rate_cop']);
        $this->assertSame(100000, $prop1707['cleaning_fee_cop']);

        $this->assertNotEmpty($data['tiers']);
        $this->assertSame('1606', $data['tiers'][0]['property_id']);
        $this->assertSame(410000, $data['tiers'][0]['nightly_rate_cop']);
        $this->assertSame(3, $data['tiers'][0]['minimum_stay']);
        $this->assertSame('Active Promo Season', $data['tiers'][0]['season_name']);
        $this->assertTrue($today >= $tierStart && $today <= $tierEnd);
    }

    /**
     * @param array<string, string> $params
     * @return array{exitCode: int, stdout: string, stderr: string, json: ?array<string, mixed>}
     */
    private function callEndpoint(array $params = [], string $method = 'GET', string $prependCode = ''): array
    {
        $phpCode = sprintf(
            '%s; $_SERVER["REQUEST_METHOD"] = %s; $_GET = %s; require %s;',
            $prependCode,
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
