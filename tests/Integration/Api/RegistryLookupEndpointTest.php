<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Integration\Api;

use PHPUnit\Framework\TestCase;

final class RegistryLookupEndpointTest extends TestCase
{
    private function getSqliteSetupCode(): string
    {
        return <<<'PHP'
$pdo = new PDO("sqlite::memory:");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec("CREATE TABLE reservations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reservation_uid TEXT UNIQUE,
    property_id TEXT,
    guest_name TEXT,
    guest_email TEXT,
    guest_phone TEXT,
    check_in TEXT,
    check_out TEXT,
    total_price REAL,
    status TEXT,
    payment_method_id TEXT,
    mercadopago_payment_id TEXT,
    payment_status TEXT,
    payment_detail TEXT,
    lang TEXT,
    registry_completed INTEGER DEFAULT 0,
    registry_completed_at TEXT,
    door_code TEXT,
    source TEXT,
    external_confirmation_code TEXT DEFAULT NULL,
    channel_block_uid TEXT DEFAULT NULL,
    refunded_amount REAL DEFAULT 0.0,
    notes TEXT,
    max_guests INTEGER NOT NULL DEFAULT 6,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, registry_completed, door_code, created_at)
    VALUES ('res-man-pending1', '1606', 'Pending User', 'pending@example.com', '+573001112233', '2026-11-20', '2026-11-23', 1500000, 'pending_payment', 0, '123456#', datetime('now'))");
$pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, registry_completed, door_code, max_guests, created_at)
    VALUES ('res-man-123456789abc', '1707', 'Manual Guest', 'manual@example.com', '+573009998877', '2026-11-24', '2026-11-28', 1800000, 'confirmed', 0, '998877#', 6, datetime('now'))");
$pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, registry_completed, door_code, max_guests, created_at)
    VALUES ('res-man-override10', '1707', 'Large Group Guest', 'group@example.com', '+573009998877', '2026-11-24', '2026-11-28', 2500000, 'confirmed', 0, '887766#', 10, datetime('now'))");
$pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, registry_completed, registry_completed_at, door_code, created_at)
    VALUES ('res-man-completed1', '1606', 'Registered Guest', 'completed@example.com', '+573004445566', '2026-12-01', '2026-12-05', 2200000, 'confirmed', 1, datetime('now'), '0160600#', datetime('now'))");
$pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, registry_completed, door_code, created_at)
    VALUES ('res-man-concluded1', '1606', 'Concluded Guest', 'concluded@example.com', '+573005556677', '2020-01-01', '2020-01-05', 1000000, 'confirmed', 0, '112233#', datetime('now'))");
$GLOBALS['TEST_PDO'] = $pdo;
PHP;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $rateLimitFile = sys_get_temp_dir() . '/ovf_registry_lookup_rate_limits.json';
        if (file_exists($rateLimitFile)) {
            @unlink($rateLimitFile);
        }
    }

    public function testRegistryLookupReturns400WhenCodeIsMissing(): void
    {
        $res = $this->callEndpoint([]);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertSame('not_found', $res['json']['status']);
        $this->assertSame('missing_code', $res['json']['reason']);
    }

    public function testRegistryLookupReturns404WhenReservationNotFound(): void
    {
        $res = $this->callEndpoint(['code' => 'res-man-nonexistent'], $this->getSqliteSetupCode());

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertSame('not_found', $res['json']['status']);
    }

    public function testRegistryLookupReturns403WhenReservationNotConfirmed(): void
    {
        $res = $this->callEndpoint(['code' => 'res-man-pending1'], $this->getSqliteSetupCode());

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertSame('unauthorized', $res['json']['status']);
    }

    public function testRegistryLookupReturnsStayDataAndStrictlyWithholdsSensitiveFields(): void
    {
        $res = $this->callEndpoint(['code' => 'res-man-123456789abc', 'lang' => 'en'], $this->getSqliteSetupCode());

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);
        $this->assertSame('success', $res['json']['status']);
        $this->assertArrayHasKey('reservation', $res['json']);

        $reservation = $res['json']['reservation'];
        $this->assertSame('res-man-123456789abc', $reservation['reservation_uid']);
        $this->assertSame('1707', $reservation['property_id']);
        $this->assertSame('2026-11-24', $reservation['check_in']);
        $this->assertSame('2026-11-28', $reservation['check_out']);
        $this->assertSame('Manual Guest', $reservation['guest_name']);
        $this->assertSame('confirmed', $reservation['status']);
        $this->assertFalse($reservation['registry_completed']);
        $this->assertSame(6, $reservation['max_guests']);

        // Assert strictly zero leakage of credentials, price, phone, email, notes
        $this->assertArrayNotHasKey('door_code', $res['json']);
        $this->assertArrayNotHasKey('door_code', $reservation);
        $this->assertArrayNotHasKey('credentials', $res['json']);
        $this->assertArrayNotHasKey('wifi_ssid', $res['json']);
        $this->assertArrayNotHasKey('wifi_password', $res['json']);
        $this->assertArrayNotHasKey('total_price', $reservation);
        $this->assertArrayNotHasKey('guest_phone', $reservation);
        $this->assertArrayNotHasKey('guest_email', $reservation);
        $this->assertArrayNotHasKey('notes', $reservation);
    }

    public function testRegistryLookupReturnsMaxGuestsOverrideWithoutLeakingPii(): void
    {
        $res = $this->callEndpoint(['code' => 'res-man-override10', 'lang' => 'en'], $this->getSqliteSetupCode());

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);
        $reservation = $res['json']['reservation'];
        $this->assertSame(10, $reservation['max_guests']);
        $this->assertArrayNotHasKey('door_code', $reservation);
        $this->assertArrayNotHasKey('guest_email', $reservation);
        $this->assertArrayNotHasKey('guest_phone', $reservation);
        $this->assertArrayNotHasKey('total_price', $reservation);
        $this->assertArrayNotHasKey('notes', $reservation);
    }

    public function testRegistryLookupReportsCompletedStatusAndGuideUrl(): void
    {
        $res = $this->callEndpoint(['code' => 'res-man-completed1', 'lang' => 'es'], $this->getSqliteSetupCode());

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);
        $this->assertSame('success', $res['json']['status']);
        $this->assertTrue($res['json']['reservation']['registry_completed']);
        $this->assertArrayHasKey('guide_url', $res['json']);
        $this->assertStringContainsString('guide/es.html?code=res-man-completed1', $res['json']['guide_url']);
    }

    public function testRegistryLookupHandlesCorsPreflight(): void
    {
        $res = $this->callEndpoint([], '', 'OPTIONS');

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame('', trim($res['stdout']));
    }

    public function testRegistryLookupReturns403AndConcludedStatusForConcludedReservation(): void
    {
        // Test in English
        $resEn = $this->callEndpoint(['code' => 'res-man-concluded1', 'lang' => 'en'], $this->getSqliteSetupCode());

        $this->assertSame(0, $resEn['exitCode'], $resEn['stderr']);
        $this->assertIsArray($resEn['json']);
        $this->assertFalse($resEn['json']['success']);
        $this->assertSame('concluded', $resEn['json']['status']);
        $this->assertSame('This reservation has concluded and registration is closed.', $resEn['json']['message']);
        $this->assertArrayNotHasKey('reservation', $resEn['json']);
        $this->assertArrayNotHasKey('credentials', $resEn['json']);

        // Test in Spanish
        $resEs = $this->callEndpoint(['code' => 'res-man-concluded1', 'lang' => 'es'], $this->getSqliteSetupCode());

        $this->assertSame(0, $resEs['exitCode'], $resEs['stderr']);
        $this->assertIsArray($resEs['json']);
        $this->assertFalse($resEs['json']['success']);
        $this->assertSame('concluded', $resEs['json']['status']);
        $this->assertSame('Esta reserva ha concluido y el registro se encuentra cerrado.', $resEs['json']['message']);
        $this->assertArrayNotHasKey('reservation', $resEs['json']);
        $this->assertArrayNotHasKey('credentials', $resEs['json']);
    }

    private function callEndpoint(array $params = [], string $prependCode = '', string $method = 'GET'): array
    {
        $rateLimitFile = sys_get_temp_dir() . '/ovf_registry_lookup_rate_limits.json';
        if (file_exists($rateLimitFile)) {
            @unlink($rateLimitFile);
        }

        $phpCode = sprintf(
            '%s; $_SERVER["REQUEST_METHOD"] = %s; $_GET = %s; require %s;',
            $prependCode,
            var_export($method, true),
            var_export($params, true),
            var_export(dirname(__DIR__, 3) . '/public/api/registry-lookup.php', true)
        );

        $process = proc_open(
            ['php', '-r', $phpCode],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        if (!is_resource($process)) {
            $this->fail('Failed to spawn php process for registry-lookup endpoint test.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $json = json_decode((string)$stdout, true);

        return [
            'exitCode' => $exitCode,
            'stdout' => (string)$stdout,
            'stderr' => (string)$stderr,
            'json' => is_array($json) ? $json : null,
        ];
    }
}
