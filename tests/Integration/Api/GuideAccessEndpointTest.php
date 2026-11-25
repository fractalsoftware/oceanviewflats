<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Integration\Api;

use PHPUnit\Framework\TestCase;

final class GuideAccessEndpointTest extends TestCase
{
    private function getSqliteSetupCode(): string
    {
        return <<<'PHP'
$_ENV['PROPERTY_1606_DOOR_CODE'] = '0111606#';
$_ENV['PROPERTY_1606_WIFI_PASSWORD'] = 'TestWifi1606!';
$_ENV['PROPERTY_1707_DOOR_CODE'] = '0111707#';
$_ENV['PROPERTY_1707_WIFI_PASSWORD'] = 'TestWifi1707!';
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
    source TEXT DEFAULT 'web',
    external_confirmation_code TEXT DEFAULT NULL,
    channel_block_uid TEXT DEFAULT NULL,
    refunded_amount REAL DEFAULT 0.0,
    lang TEXT,
    registry_completed INTEGER DEFAULT 0,
    registry_completed_at TEXT,
    door_code TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, check_in, check_out, total_price, status, registry_completed, created_at)
    VALUES ('ovf_pending_1', '1606', 'Pending Guest', 'pending@example.com', '2026-11-20', '2026-11-23', 1500000, 'pending_payment', 0, datetime('now'))");
$pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, check_in, check_out, total_price, status, registry_completed, created_at)
    VALUES ('ovf_unregistered_1', '1606', 'Unregistered Guest', 'unregistered@example.com', '2026-11-24', '2026-11-28', 1800000, 'confirmed', 0, datetime('now'))");
$pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, check_in, check_out, total_price, status, registry_completed, registry_completed_at, created_at)
    VALUES ('ovf_completed_1', '1707', 'Completed Guest', 'completed@example.com', '2026-12-01', '2026-12-05', 2200000, 'confirmed', 1, datetime('now'), datetime('now'))");
$pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, check_in, check_out, total_price, status, registry_completed, registry_completed_at, door_code, created_at)
    VALUES ('ovf_dynamic_1', '1606', 'Dynamic Guest', 'dynamic@example.com', '2026-12-10', '2026-12-15', 2500000, 'confirmed', 1, datetime('now'), '0876543#', datetime('now'))");
$pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, check_in, check_out, total_price, status, registry_completed, registry_completed_at, door_code, created_at)
    VALUES ('ovf_concluded_1', '1606', 'Concluded Past Guest', 'past@example.com', '2020-01-01', '2020-01-05', 1200000, 'confirmed', 1, datetime('now'), '0987654#', datetime('now'))");
$pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, check_in, check_out, total_price, status, registry_completed, created_at)
    VALUES ('ovf_concluded_unregistered', '1707', 'Unregistered Concluded Guest', 'unreg_past@example.com', '2020-02-01', '2020-02-05', 1300000, 'confirmed', 0, datetime('now'))");
$GLOBALS['TEST_PDO'] = $pdo;
PHP;
    }

    public function testGuideAccessEndpointReturns400WhenCodeIsMissing(): void
    {
        $res = $this->callEndpoint([]);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertFalse($res['json']['verified']);
        $this->assertSame('not_found', $res['json']['status']);
        $this->assertSame('missing_code', $res['json']['reason']);
    }

    public function testGuideAccessEndpointReturns404WhenReservationNotFound(): void
    {
        $res = $this->callEndpoint(['code' => 'ovf_nonexistent_999'], $this->getSqliteSetupCode());

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertFalse($res['json']['verified']);
        $this->assertSame('not_found', $res['json']['status']);
    }

    public function testGuideAccessEndpointReturns403WhenReservationIsPending(): void
    {
        $res = $this->callEndpoint(['code' => 'ovf_pending_1'], $this->getSqliteSetupCode());

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertFalse($res['json']['verified']);
        $this->assertSame('unauthorized', $res['json']['status']);
        $this->assertArrayNotHasKey('credentials', $res['json']);
    }

    public function testGuideAccessEndpointReturnsRegistryRequiredAndWithholdsCredentials(): void
    {
        $res = $this->callEndpoint(['code' => 'ovf_unregistered_1', 'lang' => 'en'], $this->getSqliteSetupCode());

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);
        $this->assertFalse($res['json']['verified']);
        $this->assertSame('registry_required', $res['json']['status']);
        $this->assertSame('registry_required', $res['json']['reason']);
        $this->assertArrayNotHasKey('credentials', $res['json']);
        $this->assertArrayHasKey('registry_url', $res['json']);
        $this->assertStringContainsString('ovf_unregistered_1', $res['json']['registry_url']);
        $this->assertStringContainsString('1606', $res['json']['registry_url']);
    }

    public function testGuideAccessEndpointReturnsVerifiedAndReleasesCredentials(): void
    {
        $res = $this->callEndpoint(['code' => 'ovf_completed_1', 'lang' => 'es'], $this->getSqliteSetupCode());

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);
        $this->assertTrue($res['json']['verified']);
        $this->assertSame('verified', $res['json']['status']);
        $this->assertArrayHasKey('credentials', $res['json']);
        $this->assertSame('0111707#', $res['json']['credentials']['door_code']);
        $this->assertSame('APTO1707', $res['json']['credentials']['wifi_ssid']);
        $this->assertSame('TestWifi1707!', $res['json']['credentials']['wifi_password']);
        $this->assertSame('95', $res['json']['credentials']['parking_spot']);
        $this->assertArrayHasKey('reservation', $res['json']);
        $this->assertSame('Completed Guest', $res['json']['reservation']['guest_name']);
        $this->assertSame('1707', $res['json']['reservation']['property_id']);
    }

    public function testGuideAccessEndpointReturnsDynamicGuestSpecificDoorCode(): void
    {
        $res = $this->callEndpoint(['code' => 'ovf_dynamic_1', 'lang' => 'en'], $this->getSqliteSetupCode());

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);
        $this->assertTrue($res['json']['verified']);
        $this->assertSame('verified', $res['json']['status']);
        $this->assertArrayHasKey('credentials', $res['json']);
        $this->assertSame('0876543#', $res['json']['credentials']['door_code']);
        $this->assertSame('APTO1606', $res['json']['credentials']['wifi_ssid']);
        $this->assertSame('TestWifi1606!', $res['json']['credentials']['wifi_password']);
        $this->assertSame('87', $res['json']['credentials']['parking_spot']);
    }

    public function testGuideAccessEndpointHandlesCorsPreflightOptionsRequest(): void
    {
        $res = $this->callEndpoint([], '', 'OPTIONS');

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame('', trim($res['stdout']));
    }

    public function testGuideAccessEndpointReturnsLocalizedRegistryRequiredMessage(): void
    {
        $resEs = $this->callEndpoint(['code' => 'ovf_unregistered_1', 'lang' => 'es'], $this->getSqliteSetupCode());
        $this->assertSame(0, $resEs['exitCode'], $resEs['stderr']);
        $this->assertStringContainsString('registro', strtolower($resEs['json']['message']));

        $resJa = $this->callEndpoint(['code' => 'ovf_unregistered_1', 'lang' => 'ja'], $this->getSqliteSetupCode());
        $this->assertSame(0, $resJa['exitCode'], $resJa['stderr']);
        $this->assertStringContainsString('名簿登録', $resJa['json']['message']);
    }

    public function testGuideAccessEndpointReturns403AndConcludedStatusForConcludedReservation(): void
    {
        // 1. English localized response
        $resEn = $this->callEndpoint(['code' => 'ovf_concluded_1', 'lang' => 'en'], $this->getSqliteSetupCode());

        $this->assertSame(0, $resEn['exitCode'], $resEn['stderr']);
        $this->assertIsArray($resEn['json']);
        $this->assertFalse($resEn['json']['success']);
        $this->assertFalse($resEn['json']['verified']);
        $this->assertSame('concluded', $resEn['json']['status']);
        $this->assertSame('concluded', $resEn['json']['reason']);
        $this->assertSame('This reservation has concluded and its details are no longer accessible.', $resEn['json']['message']);

        // Zero credentials & zero reservation disclosure contract
        $this->assertArrayNotHasKey('credentials', $resEn['json']);
        $this->assertArrayNotHasKey('reservation', $resEn['json']);
        $this->assertArrayNotHasKey('registry_url', $resEn['json']);
        $this->assertStringNotContainsString('0987654#', $resEn['stdout']);
        $this->assertStringNotContainsString('APTO1606', $resEn['stdout']);
        $this->assertStringNotContainsString('TestWifi1606!', $resEn['stdout']);

        // 2. Spanish localized response
        $resEs = $this->callEndpoint(['code' => 'ovf_concluded_1', 'lang' => 'es'], $this->getSqliteSetupCode());
        $this->assertSame(0, $resEs['exitCode'], $resEs['stderr']);
        $this->assertIsArray($resEs['json']);
        $this->assertFalse($resEs['json']['success']);
        $this->assertSame('concluded', $resEs['json']['status']);
        $this->assertSame('Esta reservación ha concluido y sus detalles ya no se encuentran disponibles.', $resEs['json']['message']);
        $this->assertArrayNotHasKey('credentials', $resEs['json']);
        $this->assertArrayNotHasKey('reservation', $resEs['json']);

        // 3. Japanese localized response
        $resJa = $this->callEndpoint(['code' => 'ovf_concluded_1', 'lang' => 'ja'], $this->getSqliteSetupCode());
        $this->assertSame(0, $resJa['exitCode'], $resJa['stderr']);
        $this->assertIsArray($resJa['json']);
        $this->assertFalse($resJa['json']['success']);
        $this->assertSame('concluded', $resJa['json']['status']);
        $this->assertSame('この予約はすでに終了しており、詳細は表示されません。', $resJa['json']['message']);
        $this->assertArrayNotHasKey('credentials', $resJa['json']);
        $this->assertArrayNotHasKey('reservation', $resJa['json']);
    }

    public function testGuideAccessEndpointReturnsConcludedEvenWhenRegistryWasNeverCompleted(): void
    {
        $res = $this->callEndpoint(['code' => 'ovf_concluded_unregistered', 'lang' => 'en'], $this->getSqliteSetupCode());

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertFalse($res['json']['verified']);
        $this->assertSame('concluded', $res['json']['status']);
        $this->assertSame('concluded', $res['json']['reason']);
        $this->assertArrayNotHasKey('credentials', $res['json']);
        $this->assertArrayNotHasKey('reservation', $res['json']);
        $this->assertArrayNotHasKey('registry_url', $res['json']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $rateLimitFile = sys_get_temp_dir() . '/ovf_guide_access_rate_limits.json';
        if (file_exists($rateLimitFile)) {
            @unlink($rateLimitFile);
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return array{exitCode: int, stdout: string, stderr: string, json: mixed}
     */
    private function callEndpoint(array $params = [], string $prependCode = '', string $method = 'GET'): array
    {
        $rateLimitFile = sys_get_temp_dir() . '/ovf_guide_access_rate_limits.json';
        if (file_exists($rateLimitFile)) {
            @unlink($rateLimitFile);
        }

        $phpCode = sprintf(
            '%s; $_SERVER["REQUEST_METHOD"] = %s; $_GET = %s; require %s;',
            $prependCode,
            var_export($method, true),
            var_export($params, true),
            var_export(dirname(__DIR__, 3) . '/public/api/guide-access.php', true)
        );

        $process = proc_open(
            ['php', '-r', $phpCode],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        if (!is_resource($process)) {
            $this->fail('Failed to spawn php process for guide-access endpoint test.');
        }

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
