<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use OceanViewFlats\Admin\Views\ViewRenderer;

/**
 * End-to-end integration test suite verifying the complete lifecycle of Airbnb reservations:
 * 1. Creation and initial state in database.
 * 2. ADR 0001 strict gating on public registry-lookup.php and guide-access.php before registration.
 * 3. Admin detail drawer Stage 1 dispatch presentation (withheld PIN, registration link).
 * 4. Guest registry submission via registry-processor.php.
 * 5. Algorithmic door code generation and atomic state transitions.
 * 6. ADR 0001 clearance on guide-access.php post-registration (credentials released).
 * 7. Admin detail drawer Stage 2 dispatch presentation (unlocked PIN, digital guide link).
 * 8. Pre-marked registration flow (immediate access dispatch).
 * 9. Outbound iCal echo prevention (excluding Airbnb reservations from iCal export).
 */
final class AirbnbGuestLifecycleE2ETest extends TestCase
{
    private string $dbPath;
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbPath = sys_get_temp_dir() . '/test_airbnb_lifecycle_' . bin2hex(random_bytes(6)) . '.sqlite';
        $this->pdo = new PDO('sqlite:' . $this->dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->createReservationTables();
        $this->createAdminTables();
        $this->cleanRateLimitFiles();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->cleanRateLimitFiles();

        if (isset($this->dbPath) && file_exists($this->dbPath)) {
            @unlink($this->dbPath);
        }
    }

    private function cleanRateLimitFiles(): void
    {
        $files = [
            sys_get_temp_dir() . '/ovf_registry_lookup_rate_limits.json',
            sys_get_temp_dir() . '/ovf_guide_access_rate_limits.json',
            sys_get_temp_dir() . '/ovf_registry_rate_limits.json',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    private function createReservationTables(): void
    {
        $this->pdo->exec("
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
        ");
    }

    private function createAdminTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE admin_audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_user_id INTEGER DEFAULT NULL,
                action TEXT,
                entity_type TEXT,
                entity_id TEXT,
                payload_before TEXT DEFAULT NULL,
                payload_after TEXT DEFAULT NULL,
                ip_address TEXT DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE admin_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE
            );

            CREATE TABLE calendar_blocks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                property_id TEXT,
                start_date TEXT,
                end_date TEXT,
                reason TEXT,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    /**
     * Complete Two-Stage lifecycle test:
     * - Stage 1: Reservation exists, ADR 0001 withholding credentials, Admin drawer shows Stage 1 dispatch.
     * - Transition: Guest registers via public API.
     * - Stage 2: Reservation marked complete, PIN generated, credentials released, Admin drawer shows Stage 2 dispatch.
     */
    public function testEndToEndAirbnbGuestLifecycleStage1ToStage2WithADR0001Gating(): void
    {
        $reservationUid = 'res-abnb-48291038';
        $propertyId = '1606';
        $guestName = 'Jane Doe';
        $confirmationCode = 'HM48291038';
        $channelBlockUid = 'ical-block-airbnb-4829';
        $checkIn = '2026-11-20';
        $checkOut = '2026-11-25';

        $this->seedAirbnbReservation([
            'uid' => $reservationUid,
            'propertyId' => $propertyId,
            'guestName' => $guestName,
            'confirmationCode' => $confirmationCode,
            'channelBlockUid' => $channelBlockUid,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
        ]);

        $this->assertStage1Adr0001Gated($reservationUid, $propertyId, $guestName, $confirmationCode, $channelBlockUid);

        $doorCode = $this->submitGuestRegistryAndAssertTransitions($reservationUid, $propertyId, $checkIn, $checkOut);

        $this->assertStage2Adr0001Cleared($reservationUid, $doorCode);
    }

    /**
     * @param array{
     *     uid: string,
     *     propertyId: string,
     *     guestName: string,
     *     confirmationCode: string,
     *     channelBlockUid: string,
     *     checkIn: string,
     *     checkOut: string
     * } $data
     */
    private function seedAirbnbReservation(array $data): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source,
                external_confirmation_code, channel_block_uid, payment_method_id,
                registry_completed, created_at
            ) VALUES (
                :uid, :property_id, :guest_name, :guest_email, :guest_phone,
                :check_in, :check_out, 0.0, 'confirmed', 'airbnb',
                :external_code, :channel_block_uid, 'external_ota',
                0, datetime('now')
            )
        ");
        $stmt->execute([
            'uid' => $data['uid'],
            'property_id' => $data['propertyId'],
            'guest_name' => $data['guestName'],
            'guest_email' => "airbnb-{$data['confirmationCode']}@guest.oceanviewflats.com",
            'guest_phone' => 'N/A',
            'check_in' => $data['checkIn'],
            'check_out' => $data['checkOut'],
            'external_code' => $data['confirmationCode'],
            'channel_block_uid' => $data['channelBlockUid'],
        ]);
    }

    private function assertStage1Adr0001Gated(
        string $uid,
        string $propertyId,
        string $guestName,
        string $confirmationCode,
        string $channelBlockUid
    ): void {
        // 1. Query public registry lookup endpoint
        $lookupRes = $this->callEndpoint('registry-lookup.php', ['code' => $uid, 'lang' => 'en']);
        $this->assertSame(0, $lookupRes['exitCode'], $lookupRes['stderr']);
        $this->assertTrue($lookupRes['json']['success'] ?? false);
        $this->assertFalse($lookupRes['json']['reservation']['registry_completed'] ?? true);
        $this->assertSame($guestName, $lookupRes['json']['reservation']['guest_name'] ?? null);
        $this->assertSame($propertyId, $lookupRes['json']['reservation']['property_id'] ?? null);
        $this->assertArrayNotHasKey('door_code', $lookupRes['json']['reservation']);

        // 2. Query public guide access endpoint (ADR 0001 GATING)
        $guideRes = $this->callEndpoint('guide-access.php', ['code' => $uid, 'lang' => 'en']);
        $this->assertSame(0, $guideRes['exitCode'], $guideRes['stderr']);
        $this->assertSame('registry_required', $guideRes['json']['status'] ?? null);
        $this->assertFalse($guideRes['json']['verified'] ?? true);
        $this->assertArrayNotHasKey('credentials', $guideRes['json']);
        $this->assertStringContainsString('code=' . $uid, $guideRes['json']['registry_url'] ?? '');

        // 3. Render Admin Detail Drawer (Stage 1 Presentation)
        $drawerHtml = $this->renderDetailDrawer($uid);
        $this->assertStringContainsString('Stage 1: Registry Required', $drawerHtml);
        $this->assertStringContainsString('ADR 0001: Door PIN and Guide are locked until registry is completed.', $drawerHtml);
        $this->assertStringContainsString($confirmationCode, $drawerHtml);
        $this->assertStringContainsString($channelBlockUid, $drawerHtml);
        $this->assertStringContainsString('Not Assigned', $drawerHtml);
        $this->assertStringContainsString('https://oceanviewflats.com/registry/es.html?code=' . $uid, $drawerHtml);
        $this->assertStringContainsString('https://oceanviewflats.com/registry/index.html?code=' . $uid, $drawerHtml);
    }

    private function submitGuestRegistryAndAssertTransitions(
        string $uid,
        string $propertyId,
        string $checkIn,
        string $checkOut
    ): string {
        $captcha = $this->createValidCaptcha();
        $registryPayload = array_merge([
            'reservation_code' => $uid,
            'property' => $propertyId,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guest_count' => 2,
            'guest_email_1' => 'jane.doe@example.com',
            'guest_name_1' => 'Jane Doe',
            'guest_age_1' => 32,
            'guest_doc_type_1' => 'Passport',
            'guest_doc_num_1' => 'US987654321',
            'guest_name_2' => 'John Smith',
            'guest_age_2' => 35,
            'guest_doc_type_2' => 'Passport',
            'guest_doc_num_2' => 'US123456789',
            'car_plates' => 'XYZ-789',
            'car_model' => 'Toyota RAV4',
            'lang' => 'en',
        ], $captcha);

        $submitRes = $this->callEndpoint(
            'registry-processor.php',
            [],
            $registryPayload,
            [
                'HTTP_REFERER' => 'https://oceanviewflats.com/registry/',
                'CAPTCHA_SECRET' => 'securesaltsecret',
            ]
        );

        $this->assertSame(0, $submitRes['exitCode'], $submitRes['stderr']);
        $this->assertSame(200, $submitRes['statusCode'], $submitRes['stdout']);
        $this->assertTrue($submitRes['json']['success'] ?? false);
        $doorCode = (string) ($submitRes['json']['door_code'] ?? '');
        $this->assertNotEmpty($doorCode);
        $this->assertStringEndsWith('#', $doorCode);

        // Database Verification
        $updatedRes = $this->fetchReservationRow($uid);
        $this->assertSame(1, (int) ($updatedRes['registry_completed'] ?? 0));
        $this->assertNotEmpty($updatedRes['registry_completed_at']);
        $this->assertSame($doorCode, $updatedRes['door_code']);
        $this->assertSame('jane.doe@example.com', $updatedRes['guest_email']);

        $stmtReg = $this->pdo->prepare("SELECT * FROM guest_registries WHERE reservation_uid = :uid");
        $stmtReg->execute(['uid' => $uid]);
        $guestRegRow = $stmtReg->fetch();
        $this->assertIsArray($guestRegRow);
        $this->assertSame(2, (int) $guestRegRow['guest_count']);

        $stmtAudit = $this->pdo->prepare("SELECT * FROM admin_audit_logs WHERE entity_id = :uid");
        $stmtAudit->execute(['uid' => $uid]);
        $auditRow = $stmtAudit->fetch();
        $this->assertIsArray($auditRow);
        $this->assertSame('guest_registry_submitted', $auditRow['action']);

        return $doorCode;
    }

    private function assertStage2Adr0001Cleared(string $uid, string $doorCode): void
    {
        // 1. Registry lookup reports completed
        $lookupPostRes = $this->callEndpoint('registry-lookup.php', ['code' => $uid, 'lang' => 'en']);
        $this->assertSame(0, $lookupPostRes['exitCode']);
        $this->assertTrue($lookupPostRes['json']['reservation']['registry_completed'] ?? false);

        // 2. Guide access VERIFIES and RELEASES credentials
        $guidePostRes = $this->callEndpoint('guide-access.php', ['code' => $uid, 'lang' => 'en']);
        $this->assertSame(0, $guidePostRes['exitCode'], $guidePostRes['stderr']);
        $this->assertSame('verified', $guidePostRes['json']['status'] ?? null);
        $this->assertTrue($guidePostRes['json']['verified'] ?? false);
        $this->assertSame($doorCode, $guidePostRes['json']['credentials']['door_code'] ?? null);
        $this->assertNotEmpty($guidePostRes['json']['credentials']['wifi_ssid'] ?? '');
        $this->assertNotEmpty($guidePostRes['json']['credentials']['wifi_password'] ?? '');

        // 3. Admin Detail Drawer (Stage 2 Presentation & Enriched Email)
        $drawerPostHtml = $this->renderDetailDrawer($uid);
        $this->assertStringContainsString('Stage 2: Access Dispatched', $drawerPostHtml);
        $this->assertStringContainsString('ADR 0001: Registry complete. Door PIN and Guide are unlocked.', $drawerPostHtml);
        $this->assertStringContainsString($doorCode, $drawerPostHtml);
        $this->assertStringContainsString('jane.doe@example.com', $drawerPostHtml);
        $this->assertStringContainsString('https://oceanviewflats.com/guide/es.html?code=' . $uid, $drawerPostHtml);
        $this->assertStringContainsString('https://oceanviewflats.com/guide/index.html?code=' . $uid, $drawerPostHtml);
        $this->assertStringContainsString('Tu código digital de acceso para la cerradura inteligente es: ' . $doorCode, $drawerPostHtml);
        $this->assertStringContainsString('Your smart door lock access code is: ' . $doorCode, $drawerPostHtml);

        // 4. Admin Guest Registry Dossier Modal (Verified Primary Guest Email Presentation)
        $modalHtml = $this->renderRegistryModal($uid);
        $this->assertStringContainsString('jane.doe@example.com', $modalHtml);
        $this->assertStringContainsString('Verified', $modalHtml);
        $this->assertStringContainsString('Verified Primary Guest Email', $modalHtml);
    }

    /**
     * Tests the Admin fast-path where an operator pre-marks the guest registry as completed
     * upon reservation creation (e.g., identity confirmed beforehand).
     */
    public function testPreMarkedAirbnbReservationImmediatelyProvidesAccessCredentials(): void
    {
        $reservationUid = 'res-abnb-premarked88';
        $propertyId = '1707';
        $guestName = 'Carlos Rodriguez';
        $confirmationCode = 'HMPREMARK88';
        $doorCode = '0170799#';

        $stmt = $this->pdo->prepare("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source,
                external_confirmation_code, payment_method_id,
                registry_completed, registry_completed_at, door_code, created_at
            ) VALUES (
                :uid, :property_id, :guest_name, 'carlos@example.com', '+573009998877',
                '2026-12-01', '2026-12-05', 0.0, 'confirmed', 'airbnb',
                :external_code, 'external_ota',
                1, datetime('now'), :door_code, datetime('now')
            )
        ");
        $stmt->execute([
            'uid' => $reservationUid,
            'property_id' => $propertyId,
            'guest_name' => $guestName,
            'external_code' => $confirmationCode,
            'door_code' => $doorCode,
        ]);

        $guideRes = $this->callEndpoint('guide-access.php', ['code' => $reservationUid, 'lang' => 'en']);
        $this->assertSame(0, $guideRes['exitCode'], $guideRes['stderr']);
        $this->assertTrue($guideRes['json']['verified'] ?? false);
        $this->assertSame('verified', $guideRes['json']['status'] ?? null);
        $this->assertSame($doorCode, $guideRes['json']['credentials']['door_code'] ?? null);

        $drawerHtml = $this->renderDetailDrawer($reservationUid);
        $this->assertStringContainsString('Stage 2: Access Dispatched', $drawerHtml);
        $this->assertStringContainsString($doorCode, $drawerHtml);
        $this->assertStringContainsString('https://oceanviewflats.com/guide/es.html?code=' . $reservationUid, $drawerHtml);
        $this->assertStringContainsString('https://oceanviewflats.com/guide/index.html?code=' . $reservationUid, $drawerHtml);
    }

    /**
     * Outbound iCal echo prevention verification:
     * Confirms that reservations with source = 'airbnb' are strictly excluded from
     * public/api/ical.php to avoid calendar echo loops back to Airbnb.
     */
    public function testOutboundIcalFeedExcludesAirbnbReservations(): void
    {
        $propertyId = '1606';

        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, check_in, check_out,
                total_price, status, source, created_at
            ) VALUES (
                'res-web-direct-100', '{$propertyId}', 'Direct Guest', 'direct@example.com',
                '2026-11-10', '2026-11-15', 2500000.0, 'confirmed', 'web', datetime('now')
            );

            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, check_in, check_out,
                total_price, status, source, external_confirmation_code, created_at
            ) VALUES (
                'res-abnb-export-test', '{$propertyId}', 'Airbnb Guest', 'airbnb@example.com',
                '2026-11-16', '2026-11-20', 0.0, 'confirmed', 'airbnb', 'HMEXPORT99', datetime('now')
            );
        ");

        $icalRes = $this->callEndpoint('ical.php', ['property' => $propertyId]);
        $this->assertSame(0, $icalRes['exitCode'], $icalRes['stderr']);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $icalRes['stdout']);
        $this->assertStringContainsString('res-web-direct-100', $icalRes['stdout']);
        $this->assertStringNotContainsString('res-abnb-export-test', $icalRes['stdout']);
    }

    private function renderDetailDrawer(string $reservationUid): string
    {
        $reservationData = $this->fetchReservationRow($reservationUid);
        if (!$reservationData) {
            $this->fail("Reservation {$reservationUid} not found in database.");
        }

        $renderer = new ViewRenderer(dirname(__DIR__, 2) . '/admin/src/Views');

        return $renderer->renderPartial('reservations/_detail_drawer.php', [
            'reservation' => $reservationData,
            'auditLogs' => [],
            'refunds' => [],
            'csrfToken' => 'test_csrf_token',
            'publicSiteUrl' => 'https://oceanviewflats.com',
        ]);
    }

    private function renderRegistryModal(string $reservationUid): string
    {
        $reservationData = $this->fetchReservationRow($reservationUid);
        if (!$reservationData) {
            $this->fail("Reservation {$reservationUid} not found in database.");
        }

        $stmtReg = $this->pdo->prepare("SELECT * FROM guest_registries WHERE reservation_uid = :uid");
        $stmtReg->execute(['uid' => $reservationUid]);
        $registryRow = $stmtReg->fetch();

        $renderer = new ViewRenderer(dirname(__DIR__, 2) . '/admin/src/Views');

        return $renderer->renderPartial('reservations/_registry_modal.php', [
            'reservation' => $reservationData,
            'registry' => is_array($registryRow) ? $registryRow : null,
            'publicSiteUrl' => 'https://oceanviewflats.com',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchReservationRow(string $reservationUid): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM reservations WHERE reservation_uid = :uid");
        $stmt->execute(['uid' => $reservationUid]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{captcha_challenge: string, captcha_signature: string, captcha_response: string}
     */
    private function createValidCaptcha(string $secret = 'securesaltsecret'): array
    {
        $a = 3;
        $b = 4;
        $challenge = "{$a} + {$b}";
        $signature = hash_hmac('sha256', $challenge, $secret);

        return [
            'captcha_challenge' => $challenge,
            'captcha_signature' => $signature,
            'captcha_response' => (string) ($a + $b),
        ];
    }

    private function getDbInitPhp(): string
    {
        $autoloadPath = var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true);

        return sprintf(
            'require_once %s; $pdo = new PDO("sqlite:%s"); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); $GLOBALS["TEST_PDO"] = $pdo; $GLOBALS["TEST_CLEARANCE_TRANSPORT"] = new class implements \OceanViewFlats\Domain\Fulfillment\HttpTransportInterface { public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array { if (strpos($url, "reg_guest_owner_pre.php") !== false) { return ["statusCode" => 200, "body" => json_encode(["status" => true, "last_id" => 100]), "headers" => [], "cookies" => ["PHPSESSID" => "test_e2e_sess"], "error" => null]; } return ["statusCode" => 200, "body" => "Registro exitoso", "headers" => [], "cookies" => [], "error" => null]; } };',
            $autoloadPath,
            addslashes($this->dbPath)
        );
    }

    /**
     * @param array<string, string> $getParams
     * @param array<string, mixed>|null $postData
     * @param array<string, string> $serverOverrides
     * @return array{exitCode: int, stdout: string, stderr: string, statusCode: int, json: array<string, mixed>|null}
     */
    private function callEndpoint(
        string $script,
        array $getParams = [],
        ?array $postData = null,
        array $serverOverrides = []
    ): array {
        $method = $postData !== null ? 'POST' : 'GET';
        $serverVars = array_merge([
            'REQUEST_METHOD' => $method,
            'REMOTE_ADDR' => '127.0.0.1',
        ], $serverOverrides);

        $jsonInput = $postData !== null ? json_encode($postData) : null;
        if ($jsonInput !== null && !isset($serverVars['CONTENT_TYPE'])) {
            $serverVars['CONTENT_TYPE'] = 'application/json';
        }

        $scriptPath = dirname(__DIR__, 2) . '/public/api/' . $script;
        $phpCode = sprintf(
            'register_shutdown_function(function() { fwrite(STDERR, "[STATUS:" . http_response_code() . "]"); }); %s; $_GET = %s; foreach (%s as $k => $v) { $_SERVER[$k] = $v; }; require %s;',
            $this->getDbInitPhp(),
            var_export($getParams, true),
            var_export($serverVars, true),
            var_export($scriptPath, true)
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
            $this->fail("Failed to spawn process for endpoint {$script}");
        }

        if ($jsonInput !== null) {
            fwrite($pipes[0], $jsonInput);
        }
        fclose($pipes[0]);

        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $statusCode = 200;
        if (preg_match('/\[STATUS:(\d+)\]/', $stderr, $matches)) {
            $statusCode = (int) $matches[1];
        }

        $json = json_decode($stdout, true);

        return [
            'exitCode' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'statusCode' => $statusCode,
            'json' => is_array($json) ? $json : null,
        ];
    }
}
