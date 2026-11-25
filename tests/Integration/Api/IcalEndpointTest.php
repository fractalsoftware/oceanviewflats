<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Integration\Api;

use PHPUnit\Framework\TestCase;

final class IcalEndpointTest extends TestCase
{
    public function testIcalEndpointRejectsInvalidProperty(): void
    {
        $res = $this->callEndpoint(['property' => 'invalid_prop']);

        $this->assertSame(0, $res['exitCode']);
        $this->assertStringContainsString('Bad Request: Invalid or missing property parameter', $res['stdout']);
    }

    public function testIcalEndpointRejectsMissingProperty(): void
    {
        $res = $this->callEndpoint([]);

        $this->assertSame(0, $res['exitCode']);
        $this->assertStringContainsString('Bad Request: Invalid or missing property parameter', $res['stdout']);
    }

    public function testIcalEndpointExportsActiveConfirmedReservation(): void
    {
        $setupPdoCode = <<<'PHP'
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
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("CREATE TABLE admin_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE
)");
$pdo->exec("CREATE TABLE calendar_blocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id TEXT,
    start_date TEXT,
    end_date TEXT,
    reason TEXT,
    created_by INTEGER DEFAULT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, check_in, check_out, total_price, status, created_at)
    VALUES ('ovf_test_uid', '1606', 'John Doe', 'john@example.com', '2026-08-01', '2026-08-05', 1500000, 'confirmed', datetime('now'))");
$GLOBALS['TEST_PDO'] = $pdo;
PHP;

        $res = $this->callEndpoint(['property' => '1606'], $setupPdoCode);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $res['stdout']);
        // ADR 0014: Opaque HMAC-derived UID is emitted instead of raw reservation_uid
        $this->assertMatchesRegularExpression('/UID:ovf-evt-[a-f0-9]{32}@oceanviewflats\.com/', $res['stdout']);
        $this->assertStringNotContainsString('ovf_test_uid', $res['stdout']);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260801', $res['stdout']);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20260805', $res['stdout']);
        $this->assertStringContainsString('SUMMARY:Blocked - OceanViewFlats Direct Booking', $res['stdout']);
        $this->assertStringContainsString('END:VCALENDAR', $res['stdout']);
    }

    public function testIcalEndpointIgnoresCancelledReservations(): void
    {
        $setupPdoCode = <<<'PHP'
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
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("CREATE TABLE admin_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE
)");
$pdo->exec("CREATE TABLE calendar_blocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id TEXT,
    start_date TEXT,
    end_date TEXT,
    reason TEXT,
    created_by INTEGER DEFAULT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, check_in, check_out, total_price, status, created_at)
    VALUES ('ovf_cancelled_uid', '1606', 'Cancelled Guest', 'john@example.com', '2026-08-01', '2026-08-05', 1500000, 'cancelled', datetime('now'))");
$GLOBALS['TEST_PDO'] = $pdo;
PHP;

        $res = $this->callEndpoint(['property' => '1606'], $setupPdoCode);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $res['stdout']);
        $this->assertStringNotContainsString('ovf_cancelled_uid', $res['stdout']);
        $this->assertStringContainsString('END:VCALENDAR', $res['stdout']);
    }

    public function testIcalEndpointProjectsMaintenanceBlocksWithoutLeakingReasons(): void
    {
        $setupPdoCode = <<<'PHP'
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
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("CREATE TABLE admin_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE
)");
$pdo->exec("CREATE TABLE calendar_blocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id TEXT,
    start_date TEXT,
    end_date TEXT,
    reason TEXT,
    created_by INTEGER DEFAULT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("INSERT INTO calendar_blocks (id, property_id, start_date, end_date, reason, created_at)
    VALUES (99, '1606', '2026-09-10', '2026-09-15', 'Private Host Wedding and Plumbing', '2026-09-01 10:00:00')");
$GLOBALS['TEST_PDO'] = $pdo;
PHP;

        $res = $this->callEndpoint(['property' => '1606'], $setupPdoCode);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $res['stdout']);
        $this->assertStringContainsString('UID:block-99@oceanviewflats.com', $res['stdout']);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260910', $res['stdout']);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20260915', $res['stdout']);
        $this->assertStringContainsString('STATUS:CONFIRMED', $res['stdout']);
        $this->assertStringContainsString('SUMMARY:Maintenance Hold', $res['stdout']);
        // Crucial security invariant: private host operational reasons must NOT be exposed in public feeds
        $this->assertStringNotContainsString('Private Host Wedding and Plumbing', $res['stdout']);
        $this->assertStringContainsString('END:VCALENDAR', $res['stdout']);
    }

    public function testIcalEndpointFiltersOutExternalAirbnbReservations(): void
    {
        $setupPdoCode = <<<'PHP'
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
    source TEXT DEFAULT 'web',
    external_confirmation_code TEXT DEFAULT NULL,
    channel_block_uid TEXT DEFAULT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("CREATE TABLE admin_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE
)");
$pdo->exec("CREATE TABLE calendar_blocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id TEXT,
    start_date TEXT,
    end_date TEXT,
    reason TEXT,
    created_by INTEGER DEFAULT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
// 1. Direct confirmed booking (should be exported)
$pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, check_in, check_out, total_price, status, source, created_at)
    VALUES ('res-direct-101', '1606', 'Direct Guest', 'direct@example.com', '2026-08-01', '2026-08-05', 1500000, 'confirmed', 'web', datetime('now'))");
// 2. External Airbnb reservation (must be excluded from outbound feed per ADR 0007)
$pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, check_in, check_out, total_price, status, source, created_at)
    VALUES ('res-abnb-999', '1606', 'Airbnb Guest', 'airbnb@example.com', '2026-08-10', '2026-08-15', 2000000, 'confirmed', 'airbnb', datetime('now'))");
// 3. Maintenance block (should be exported)
$pdo->exec("INSERT INTO calendar_blocks (id, property_id, start_date, end_date, reason, created_at)
    VALUES (15, '1606', '2026-08-20', '2026-08-25', 'AC Maintenance', datetime('now'))");
$GLOBALS['TEST_PDO'] = $pdo;
PHP;

        $res = $this->callEndpoint(['property' => '1606'], $setupPdoCode);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $res['stdout']);

        // Direct Reservation is present with opaque HMAC-derived UID (ADR 0014: never expose raw reservation_uid)
        $this->assertMatchesRegularExpression('/UID:ovf-evt-[a-f0-9]{32}@oceanviewflats\.com/', $res['stdout']);
        $this->assertStringNotContainsString('res-direct-101', $res['stdout']);
        $this->assertStringContainsString('SUMMARY:Blocked - OceanViewFlats Direct Booking', $res['stdout']);

        // External Airbnb reservation is filtered out (ADR 0007 echo prevention)
        $this->assertStringNotContainsString('res-abnb-999', $res['stdout']);

        // Maintenance block is present
        $this->assertStringContainsString('UID:block-15@oceanviewflats.com', $res['stdout']);
        $this->assertStringContainsString('SUMMARY:Maintenance Hold', $res['stdout']);

        $this->assertStringContainsString('END:VCALENDAR', $res['stdout']);
    }

    /**
     * @param array<string, string> $params
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function callEndpoint(array $params = [], string $prependCode = ''): array
    {
        $phpCode = sprintf(
            '$_ENV["ICAL_UID_SECRET"] = "test_ical_hmac_secret"; %s; $_SERVER["REQUEST_METHOD"] = "GET"; $_GET = %s; require %s;',
            $prependCode,
            var_export($params, true),
            var_export(dirname(__DIR__, 3) . '/public/api/ical.php', true)
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
            $this->fail('Failed to spawn php process for ical endpoint test.');
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
        ];
    }
}
