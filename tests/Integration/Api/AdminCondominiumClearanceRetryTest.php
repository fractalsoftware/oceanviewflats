<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Integration\Api;

use PDO;
use PHPUnit\Framework\TestCase;

final class AdminCondominiumClearanceRetryTest extends TestCase
{
    private string $dbPath;
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbPath = sys_get_temp_dir() . '/test_admin_clearance_retry_' . bin2hex(random_bytes(6)) . '.sqlite';
        $this->pdo = new PDO('sqlite:' . $this->dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->createSchema();
        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->dbPath)) {
            @unlink($this->dbPath);
        }
    }

    private function createSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE admin_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                name TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'operator',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE reservations (
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
                mercadopago_preference_id TEXT,
                mercadopago_payment_id TEXT,
                payment_status TEXT,
                payment_detail TEXT,
                notes TEXT,
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
            );

            CREATE TABLE guest_registries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_uid TEXT,
                property_id TEXT,
                check_in TEXT,
                check_out TEXT,
                guest_count INTEGER DEFAULT 1,
                guests_payload TEXT,
                car_plates TEXT,
                car_model TEXT,
                ip_address TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE condominium_clearances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_uid TEXT NOT NULL UNIQUE,
                property_id TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                clearance_number TEXT DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                request_payload TEXT DEFAULT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                last_attempt_at TEXT DEFAULT NULL,
                synced_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE admin_audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_user_id INTEGER,
                action TEXT,
                entity_type TEXT,
                entity_id TEXT,
                payload_before TEXT,
                payload_after TEXT,
                ip_address TEXT,
                user_agent TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    private function seedFixtures(): void
    {
        $this->pdo->exec("
            INSERT INTO admin_users (id, email, password_hash, name, role)
            VALUES (1, 'operator@oceanviewflats.com', 'dummy_hash', 'Operator Manuel', 'admin');

            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, registry_completed, door_code, created_at
            ) VALUES 
            ('res-ready-1', '1606', 'Registered Guest', 'reg@example.com', '+573001112233', '2026-11-20', '2026-11-25', 1500000.0, 'confirmed', 1, '1234#', datetime('now')),
            ('res-no-registry', '1606', 'Unregistered Guest', 'unreg@example.com', '+573004445566', '2026-11-26', '2026-11-30', 1200000.0, 'confirmed', 0, NULL, datetime('now'));

            INSERT INTO guest_registries (reservation_uid, property_id, check_in, check_out, guest_count, guests_payload, car_plates, car_model, ip_address)
            VALUES (
                'res-ready-1',
                '1606',
                '2026-11-20',
                '2026-11-25',
                1,
                '[{\"full_name\":\"Carlos Mendoza\",\"first_name\":\"Carlos\",\"last_name\":\"Mendoza\",\"doc_type\":\"CC\",\"doc_num\":\"1098765432\",\"country\":\"Colombia\",\"phone\":\"+573001112233\"}]',
                'XYZ-789',
                'Mazda CX-30',
                '192.168.1.50'
            );
        ");
    }

    public function testEndpointRejectsNonPostMethodWith405(): void
    {
        $res = $this->callEndpoint([], 'GET');

        $this->assertSame(405, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertSame('Method Not Allowed', $res['json']['error']);
    }

    public function testEndpointRequiresActiveAdminSessionWith401(): void
    {
        $res = $this->callEndpoint(['reservation_uid' => 'res-ready-1'], 'POST', [], '', false);

        $this->assertSame(401, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('Active administrative session required', $res['json']['error']);
    }

    public function testEndpointRequiresValidCsrfTokenWith403(): void
    {
        // Session active, but no CSRF token provided
        $res = $this->callEndpoint(
            ['reservation_uid' => 'res-ready-1'],
            'POST',
            [],
            '',
            true,
            'wrong-or-missing-csrf'
        );

        $this->assertSame(403, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('Invalid or missing CSRF token', $res['json']['error']);
    }

    public function testEndpointValidatesReservationUidWith422(): void
    {
        $res = $this->callEndpoint(
            ['reservation_uid' => ''],
            'POST',
            ['HTTP_X_CSRF_TOKEN' => 'valid-csrf-token'],
            '',
            true,
            'valid-csrf-token'
        );

        $this->assertSame(422, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('Missing required field: reservation_uid', $res['json']['error']);
    }

    public function testEndpointReturns404WhenReservationNotFound(): void
    {
        $res = $this->callEndpoint(
            ['reservation_uid' => 'res-nonexistent-999'],
            'POST',
            ['HTTP_X_CSRF_TOKEN' => 'valid-csrf-token'],
            '',
            true,
            'valid-csrf-token'
        );

        $this->assertSame(404, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertSame('Reservation not found.', $res['json']['error']);
    }

    public function testEndpointReturns400WhenGuestRegistryNotSubmitted(): void
    {
        $res = $this->callEndpoint(
            ['reservation_uid' => 'res-no-registry'],
            'POST',
            ['HTTP_X_CSRF_TOKEN' => 'valid-csrf-token'],
            '',
            true,
            'valid-csrf-token'
        );

        $this->assertSame(400, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('Guest registry must be submitted', $res['json']['error']);
    }

    public function testEndpointExecutesClearanceSyncAndReturns200OnSuccess(): void
    {
        $prependCode = "
            class TestSuccessfulTransport implements \OceanViewFlats\Domain\Fulfillment\HttpTransportInterface {
                private int \$callCount = 0;
                public function post(string \$url, array|string \$data = [], array \$headers = [], array \$options = []): array {
                    \$this->callCount++;
                    if (\$this->callCount === 1) {
                        return [
                            'statusCode' => 200,
                            'body' => json_encode(['last_id' => '78910']),
                            'headers' => [],
                            'cookies' => ['PHPSESSID' => 'test-session-123'],
                            'error' => null,
                        ];
                    }
                    return [
                        'statusCode' => 302,
                        'body' => '',
                        'headers' => ['location' => 'https://example.com/ok.php'],
                        'cookies' => [],
                        'error' => null,
                    ];
                }
            }
            \$GLOBALS['TEST_CLEARANCE_TRANSPORT'] = new TestSuccessfulTransport();
        ";

        $res = $this->callEndpoint(
            ['reservation_uid' => 'res-ready-1'],
            'POST',
            ['HTTP_X_CSRF_TOKEN' => 'valid-csrf-token'],
            $prependCode,
            true,
            'valid-csrf-token'
        );

        $this->assertSame(200, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);
        $this->assertSame('synced', $res['json']['status']);
        $this->assertSame('78910', $res['json']['clearance_number']);
        $this->assertSame(1, (int) $res['json']['attempts']);

        // Check database clearance record
        $stmt = $this->pdo->prepare('SELECT * FROM condominium_clearances WHERE reservation_uid = :uid');
        $stmt->execute([':uid' => 'res-ready-1']);
        $clearance = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($clearance);
        $this->assertSame('synced', $clearance['status']);
        $this->assertSame('78910', $clearance['clearance_number']);

        // Check audit log
        $stmt = $this->pdo->prepare('SELECT * FROM admin_audit_logs WHERE action = "condominium_clearance_sync" AND entity_id = :uid');
        $stmt->execute([':uid' => 'res-ready-1']);
        $auditLog = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($auditLog);
        $this->assertSame(1, (int) $auditLog['admin_user_id']);
    }

    public function testEndpointExecutesClearanceRetryWhenPriorClearanceExistedAndRecordsRetryAuditLog(): void
    {
        $this->pdo->prepare("
            INSERT INTO condominium_clearances (reservation_uid, property_id, status, error_message, attempts)
            VALUES ('res-ready-1', '1606', 'failed', 'Previous timeout', 1)
        ")->execute();

        $prependCode = "
            class TestClearanceTransport implements \OceanViewFlats\Domain\Fulfillment\HttpTransportInterface {
                private int \$step = 1;
                public function post(string \$url, array|string \$data = [], array \$headers = [], array \$options = []): array {
                    if (str_contains(\$url, 'reg_guest_owner_pre.php')) {
                        return [
                            'statusCode' => 200,
                            'body' => json_encode(['last_id' => '99999']),
                            'headers' => [],
                            'cookies' => ['PHPSESSID' => 'test-session-123'],
                            'error' => null,
                        ];
                    }
                    return [
                        'statusCode' => 302,
                        'body' => '',
                        'headers' => ['location' => 'https://salguerosunset.huespedmanager.com.co/propietarios/production/control_hpds.php'],
                        'cookies' => [],
                        'error' => null,
                    ];
                }
            }
            \$GLOBALS['TEST_CLEARANCE_TRANSPORT'] = new TestClearanceTransport();
        ";

        $res = $this->callEndpoint(
            ['reservation_uid' => 'res-ready-1'],
            'POST',
            ['HTTP_X_CSRF_TOKEN' => 'valid-csrf-token'],
            $prependCode,
            true,
            'valid-csrf-token'
        );

        $this->assertSame(200, $res['statusCode']);
        $this->assertTrue($res['json']['success']);
        $this->assertSame('synced', $res['json']['status']);
        $this->assertSame('99999', $res['json']['clearance_number']);

        // Check audit log for retry action
        $stmt = $this->pdo->prepare('SELECT * FROM admin_audit_logs WHERE action = "condominium_clearance_retry" AND entity_id = :uid');
        $stmt->execute([':uid' => 'res-ready-1']);
        $auditLog = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($auditLog);
        $before = json_decode((string) $auditLog['payload_before'], true);
        $after = json_decode((string) $auditLog['payload_after'], true);
        $this->assertSame('failed', $before['status']);
        $this->assertSame('synced', $after['status']);
        $this->assertSame('99999', $after['clearance_number']);
    }

    public function testEndpointExecutesClearanceSyncAndReturns200OnSyncFailure(): void
    {
        $prependCode = "
            class TestFailingTransport implements \OceanViewFlats\Domain\Fulfillment\HttpTransportInterface {
                public function post(string \$url, array|string \$data = [], array \$headers = [], array \$options = []): array {
                    return [
                        'statusCode' => 500,
                        'body' => 'Internal Server Error on HOA Portal',
                        'headers' => [],
                        'cookies' => [],
                        'error' => null,
                    ];
                }
            }
            \$GLOBALS['TEST_CLEARANCE_TRANSPORT'] = new TestFailingTransport();
        ";

        $res = $this->callEndpoint(
            ['reservation_uid' => 'res-ready-1'],
            'POST',
            ['HTTP_X_CSRF_TOKEN' => 'valid-csrf-token'],
            $prependCode,
            true,
            'valid-csrf-token'
        );

        $this->assertSame(200, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertSame('failed', $res['json']['status']);
        $this->assertStringContainsString('Step 1 returned unexpected HTTP status 500', (string) $res['json']['error']);

        // Check database clearance record
        $stmt = $this->pdo->prepare('SELECT * FROM condominium_clearances WHERE reservation_uid = :uid');
        $stmt->execute([':uid' => 'res-ready-1']);
        $clearance = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($clearance);
        $this->assertSame('failed', $clearance['status']);

        // Check audit log
        $stmt = $this->pdo->prepare('SELECT * FROM admin_audit_logs WHERE action = "condominium_clearance_sync" AND entity_id = :uid');
        $stmt->execute([':uid' => 'res-ready-1']);
        $auditLog = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($auditLog);
    }

    /**
     * @param array<string, mixed> $bodyData
     * @param string $method
     * @param array<string, string> $serverVars
     * @param string $prependCode
     * @param bool $authenticated
     * @param string $sessionCsrf
     * @return array{exitCode: int, stdout: string, stderr: string, statusCode: int, json: ?array<string, mixed>}
     */
    private function callEndpoint(
        array $bodyData = [],
        string $method = 'POST',
        array $serverVars = [],
        string $prependCode = '',
        bool $authenticated = true,
        string $sessionCsrf = 'valid-csrf-token'
    ): array {
        $defaultServer = [
            'REQUEST_METHOD' => $method,
            'HTTP_HOST' => 'oceanviewflats.com',
            'REMOTE_ADDR' => '127.0.0.1',
        ];
        $mergedServer = array_merge($defaultServer, $serverVars);

        $jsonInput = json_encode($bodyData);

        $sessionInit = '';
        if ($authenticated) {
            $sessionInit = sprintf(
                'if (session_status() === PHP_SESSION_NONE) { session_start(); } $_SESSION["admin_user_id"] = 1; $_SESSION["csrf_token"] = %s;',
                var_export($sessionCsrf, true)
            );
        } else {
            $sessionInit = 'if (session_status() === PHP_SESSION_NONE) { session_start(); } $_SESSION = [];';
        }

        $dbInit = sprintf(
            '$GLOBALS["TEST_PDO"] = new PDO("sqlite:%s"); $GLOBALS["TEST_PDO"]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $GLOBALS["TEST_PDO"]->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);',
            $this->dbPath
        );

        $fullPrepend = sprintf(
            'require_once %s; register_shutdown_function(function() { fwrite(STDERR, "[STATUS:" . http_response_code() . "]"); }); %s; %s; %s',
            var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true),
            $sessionInit,
            $dbInit,
            $prependCode
        );

        $phpCode = sprintf(
            '%s; foreach (%s as $k => $v) { $_SERVER[$k] = $v; }; require %s;',
            $fullPrepend,
            var_export($mergedServer, true),
            var_export(dirname(__DIR__, 3) . '/public/api/admin-condominium-clearance-retry.php', true)
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
            $this->fail('Failed to spawn php process for admin-condominium-clearance-retry endpoint test.');
        }

        if ($jsonInput !== false) {
            fwrite($pipes[0], $jsonInput);
        }
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $statusCode = 200;
        if (preg_match('/\[STATUS:(\d+)\]/', (string) $stderr, $matches)) {
            $statusCode = (int) $matches[1];
        }

        return [
            'exitCode' => $exitCode,
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
            'statusCode' => $statusCode,
            'json' => json_decode((string) $stdout, true),
        ];
    }
}
