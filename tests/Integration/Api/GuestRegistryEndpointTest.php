<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Integration\Api;

use PDO;
use PHPUnit\Framework\TestCase;

final class GuestRegistryEndpointTest extends TestCase
{
    private string $dbPath;
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbPath = sys_get_temp_dir() . '/test_registry_' . bin2hex(random_bytes(6)) . '.sqlite';
        $this->pdo = new PDO('sqlite:' . $this->dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->createSchema();
        $this->seedFixtures();

        $rateLimitFile = sys_get_temp_dir() . '/ovf_registry_rate_limits.json';
        if (file_exists($rateLimitFile)) {
            @unlink($rateLimitFile);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $rateLimitFile = sys_get_temp_dir() . '/ovf_registry_rate_limits.json';
        if (file_exists($rateLimitFile)) {
            @unlink($rateLimitFile);
        }

        if (isset($this->dbPath) && file_exists($this->dbPath)) {
            @unlink($this->dbPath);
        }
    }

    private function createSchema(): void
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
                max_guests INTEGER NOT NULL DEFAULT 6,
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

            CREATE TABLE admin_audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_user_id INTEGER,
                action TEXT,
                entity_type TEXT,
                entity_id TEXT,
                payload_before TEXT,
                payload_after TEXT,
                ip_address TEXT,
                user_agent TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE condominium_clearances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_uid TEXT UNIQUE NOT NULL,
                property_id TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                clearance_number TEXT DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                request_payload TEXT DEFAULT NULL,
                attempts INTEGER DEFAULT 0,
                last_attempt_at TEXT DEFAULT NULL,
                synced_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    private function seedFixtures(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, payment_status, registry_completed
            ) VALUES (
                'ovf_conf_100', '1606', 'Maria Gomez', 'maria@example.com', '+573001234567',
                '2026-11-15', '2026-11-20', 1600000.0, 'confirmed', 'approved', 0
            );

            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, payment_status, registry_completed
            ) VALUES (
                'ovf_canc_200', '1606', 'Cancelled Guest', 'canc@example.com', '+573009998877',
                '2026-12-01', '2026-12-05', 1200000.0, 'cancelled', 'refunded', 0
            );

            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, payment_status, registry_completed
            ) VALUES (
                'ovf_concluded_300', '1606', 'Concluded Guest', 'concluded@example.com', '+573009998877',
                '2020-01-01', '2020-01-05', 1200000.0, 'confirmed', 'approved', 0
            );
        ");
    }

    /**
     * @return array{captcha_challenge: string, captcha_signature: string, captcha_response: string}
     */
    private function createValidCaptcha(string $secret = 'securesaltsecret'): array
    {
        $a = 4;
        $b = 5;
        $challenge = "{$a} + {$b}";
        $signature = hash_hmac('sha256', $challenge, $secret);

        return [
            'captcha_challenge' => $challenge,
            'captcha_signature' => $signature,
            'captcha_response' => (string) ($a + $b),
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function createValidRegistryData(array $overrides = []): array
    {
        $captcha = $this->createValidCaptcha();

        return array_merge([
            'reservation_code' => 'ovf_conf_100',
            'property' => '1606',
            'check_in' => '2026-11-15',
            'check_out' => '2026-11-20',
            'guest_count' => 2,
            'guest_email_1' => 'maria.gomez@example.com',
            'guest_name_1' => 'Maria Gomez',
            'guest_age_1' => 34,
            'guest_doc_type_1' => 'Passport',
            'guest_doc_num_1' => 'US12345678',
            'guest_name_2' => 'Carlos Gomez',
            'guest_age_2' => 36,
            'guest_doc_type_2' => 'Passport',
            'guest_doc_num_2' => 'US87654321',
            'car_plates' => 'ABC-123',
            'car_model' => 'Mazda CX-5',
            'lang' => 'en',
        ], $captcha, $overrides);
    }

    public function testRegistryEndpointRejectsNonPostMethodWith405(): void
    {
        $res = $this->callRegistryEndpoint([], 'GET');

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(405, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertSame('Method Not Allowed', $res['json']['error']);
    }

    public function testRegistryEndpointEnforcesRefererCheckForDirectBrowserAccess(): void
    {
        $res = $this->callRegistryEndpoint(
            $this->createValidRegistryData(),
            'POST',
            [
                'HTTP_REFERER' => '',
                'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            ]
        );

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertSame('Direct access to this processing script is not permitted.', $res['json']['error']);
    }

    public function testRegistryEndpointRejectsUnauthorizedRefererHost(): void
    {
        $res = $this->callRegistryEndpoint(
            $this->createValidRegistryData(),
            'POST',
            [
                'HTTP_REFERER' => 'https://malicious-phishing.com/attack',
            ]
        );

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertSame('Unauthorized access origin.', $res['json']['error']);
    }

    public function testRegistryEndpointCatchesHoneypotSubmission(): void
    {
        $resWithHp = $this->callRegistryEndpoint($this->createValidRegistryData([
            'website_hp' => 'http://spam-bot.xyz',
        ]));

        $this->assertSame(0, $resWithHp['exitCode'], $resWithHp['stderr']);
        $this->assertSame(200, $resWithHp['statusCode']);
        $this->assertIsArray($resWithHp['json']);
        $this->assertTrue($resWithHp['json']['success']);
        $this->assertSame('Guest registration completed successfully.', $resWithHp['json']['message']);

        // Database must remain unaffected
        $regCount = (int) $this->pdo->query('SELECT COUNT(*) FROM guest_registries')->fetchColumn();
        $this->assertSame(0, $regCount);

        $resWithUrl = $this->callRegistryEndpoint($this->createValidRegistryData([
            'website_url' => 'http://spam-bot.xyz',
        ]));

        $this->assertSame(0, $resWithUrl['exitCode'], $resWithUrl['stderr']);
        $this->assertSame(200, $resWithUrl['statusCode']);
        $this->assertIsArray($resWithUrl['json']);
        $this->assertTrue($resWithUrl['json']['success']);
        $this->assertSame('Guest registration completed successfully.', $resWithUrl['json']['message']);

        $regCountAfter = (int) $this->pdo->query('SELECT COUNT(*) FROM guest_registries')->fetchColumn();
        $this->assertSame(0, $regCountAfter);
    }

    public function testRegistryEndpointRejectsInvalidCaptchaChallenge(): void
    {
        // 1. Wrong response calculation
        $wrongResponseData = $this->createValidRegistryData([
            'captcha_challenge' => '4 + 5',
            'captcha_signature' => hash_hmac('sha256', '4 + 5', 'securesaltsecret'),
            'captcha_response' => '99',
        ]);
        $resWrong = $this->callRegistryEndpoint($wrongResponseData);

        $this->assertSame(0, $resWrong['exitCode'], $resWrong['stderr']);
        $this->assertIsArray($resWrong['json']);
        $this->assertFalse($resWrong['json']['success']);
        $this->assertNotEmpty($resWrong['json']['error']);

        // 2. Tampered signature
        $badSignData = $this->createValidRegistryData([
            'captcha_challenge' => '4 + 5',
            'captcha_signature' => 'forged_invalid_signature',
            'captcha_response' => '9',
        ]);
        $resSign = $this->callRegistryEndpoint($badSignData);

        $this->assertSame(0, $resSign['exitCode'], $resSign['stderr']);
        $this->assertIsArray($resSign['json']);
        $this->assertFalse($resSign['json']['success']);
        $this->assertNotEmpty($resSign['json']['error']);

        // Ensure database was not altered
        $regCount = (int) $this->pdo->query('SELECT COUNT(*) FROM guest_registries')->fetchColumn();
        $this->assertSame(0, $regCount);
    }

    public function testValidSubmissionSucceedsEndToEnd(): void
    {
        $payload = $this->createValidRegistryData();

        $res = $this->callRegistryEndpoint($payload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(200, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);
        $this->assertSame('Guest registration completed successfully.', $res['json']['message']);

        // Validate Door PIN format per ADR 0001 & ADR 0012 (cryptographically random 0XXXXXX#)
        $doorCode = $res['json']['door_code'] ?? null;
        $this->assertIsString($doorCode);
        $this->assertStringEndsWith('#', $doorCode);
        $this->assertMatchesRegularExpression('/^0[1-9]\d{5}#$/', $doorCode);

        // Guide URL
        $guideUrl = $res['json']['guide_url'] ?? null;
        $this->assertIsString($guideUrl);
        $this->assertStringContainsString('ovf_conf_100', $guideUrl);
        $this->assertStringContainsString('/guide/index.html?code=ovf_conf_100', $guideUrl);
        $this->assertStringNotContainsString('lang=', $guideUrl);

        // Reservation code
        $this->assertSame('ovf_conf_100', $res['json']['reservation_code'] ?? null);

        // Verify Database updates on reservations table
        $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE reservation_uid = :uid');
        $stmt->execute([':uid' => 'ovf_conf_100']);
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame(1, (int) $row['registry_completed']);
        $this->assertSame($doorCode, $row['door_code']);
        $this->assertNotNull($row['registry_completed_at']);

        // Verify guest_registries table entry (encrypted at rest per ADR 0012)
        $stmtReg = $this->pdo->prepare('SELECT * FROM guest_registries WHERE reservation_uid = :uid');
        $stmtReg->execute([':uid' => 'ovf_conf_100']);
        $reg = $stmtReg->fetch();
        $this->assertIsArray($reg);
        $this->assertSame('1606', $reg['property_id']);
        $this->assertSame('2026-11-15', $reg['check_in']);
        $this->assertSame('2026-11-20', $reg['check_out']);
        $this->assertSame(2, (int) $reg['guest_count']);
        $this->assertSame('ABC-123', $reg['car_plates']);
        $this->assertSame('Mazda CX-5', $reg['car_model']);
        $this->assertSame('127.0.0.1', $reg['ip_address']);

        $this->assertTrue(\OceanViewFlats\Domain\Security\PayloadEncryptor::isEncryptedEnvelope((string) $reg['guests_payload']));
        $occupantsPayload = \OceanViewFlats\Domain\Security\PayloadEncryptor::decrypt((string) $reg['guests_payload']);
        $this->assertCount(2, $occupantsPayload);
        $this->assertSame('Maria Gomez', $occupantsPayload[0]['name']);
        $this->assertSame('US12345678', $occupantsPayload[0]['doc_num']);

        // Verify admin_audit_logs entry
        $stmtAudit = $this->pdo->prepare("SELECT * FROM admin_audit_logs WHERE action = 'guest_registry_submitted'");
        $stmtAudit->execute();
        $audit = $stmtAudit->fetch();
        $this->assertIsArray($audit);
        $this->assertSame('reservation', $audit['entity_type']);
        $this->assertSame('ovf_conf_100', $audit['entity_id']);
    }

    public function testValidSubmissionWithStructuredOccupantsPayloadSucceeds(): void
    {
        $captcha = $this->createValidCaptcha();
        $payload = array_merge([
            'reservation_code' => 'ovf_conf_100',
            'property_id' => '1606',
            'check_in' => '2026-11-15',
            'check_out' => '2026-11-20',
            'primary_guest_email' => 'maria.gomez@example.com',
            'occupants' => [
                [
                    'index' => 1,
                    'name' => 'Maria Gomez',
                    'age' => 34,
                    'doc_type' => 'Passport',
                    'doc_num' => 'US12345678',
                ],
            ],
            'lang' => 'es',
        ], $captcha);

        $res = $this->callRegistryEndpoint($payload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(200, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);
        $guideUrl = (string) $res['json']['guide_url'];
        $this->assertStringContainsString('/guide/es.html?code=ovf_conf_100', $guideUrl);
        $this->assertStringNotContainsString('lang=', $guideUrl);
    }

    public function testSubmitRegistryMatchesByPropertyAndStayDatesWhenCodeIsEmpty(): void
    {
        $payload = $this->createValidRegistryData([
            'reservation_code' => '',
            'property' => '1606',
            'check_in' => '2026-11-15',
            'check_out' => '2026-11-20',
        ]);

        $res = $this->callRegistryEndpoint($payload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(200, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);
        $this->assertSame('ovf_conf_100', $res['json']['reservation_code']);
        $this->assertMatchesRegularExpression('/^0[1-9]\d{5}#$/', (string) $res['json']['door_code']);
    }

    public function testInvalidSubmissionRejectsZeroOccupantsWith400(): void
    {
        $payload = $this->createValidRegistryData([
            'occupants' => [],
            'guest_count' => 0,
        ]);
        // Remove flat keys
        unset(
            $payload['guest_name_1'], $payload['guest_age_1'], $payload['guest_doc_type_1'], $payload['guest_doc_num_1'],
            $payload['guest_name_2'], $payload['guest_age_2'], $payload['guest_doc_type_2'], $payload['guest_doc_num_2']
        );

        $res = $this->callRegistryEndpoint($payload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(400, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertNotEmpty($res['json']['errors']);
        $this->assertContains('A reservation must register between 1 and 6 guests.', $res['json']['errors']);
    }

    public function testInvalidSubmissionRejectsAgeOver120With400(): void
    {
        $payload = $this->createValidRegistryData([
            'guest_age_1' => 135,
        ]);

        $res = $this->callRegistryEndpoint($payload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(400, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('age', strtolower((string) $res['json']['error']));
    }

    public function testInvalidSubmissionRejectsReversedDatesWith400(): void
    {
        $payload = $this->createValidRegistryData([
            'check_in' => '2026-11-20',
            'check_out' => '2026-11-15',
        ]);

        $res = $this->callRegistryEndpoint($payload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(400, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertNotEmpty($res['json']['errors']);
    }

    public function testInvalidSubmissionRejectsMalformedDateFormatWith400(): void
    {
        $payload = $this->createValidRegistryData([
            'check_in' => 'not-a-valid-date',
        ]);

        $res = $this->callRegistryEndpoint($payload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(400, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertNotEmpty($res['json']['errors']);
    }

    public function testInvalidOccupantDocumentRejectionReturns400(): void
    {
        $payload = $this->createValidRegistryData([
            'occupants' => [
                [
                    'index' => 1,
                    'name' => 'Maria Gomez',
                    'age' => 30,
                    'doc_type' => 'AlienID',
                    'doc_num' => '12345',
                ],
            ],
        ]);
        unset(
            $payload['guest_name_1'], $payload['guest_age_1'], $payload['guest_doc_type_1'], $payload['guest_doc_num_1'],
            $payload['guest_name_2'], $payload['guest_age_2'], $payload['guest_doc_type_2'], $payload['guest_doc_num_2']
        );

        $res = $this->callRegistryEndpoint($payload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(400, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('Invalid document type', (string) $res['json']['error']);
    }

    public function testUnmatchedReservationReturns400Error(): void
    {
        $payload = $this->createValidRegistryData([
            'reservation_code' => 'ovf_nonexistent_999',
            'property' => '1707',
            'check_in' => '2027-01-01',
            'check_out' => '2027-01-05',
        ]);

        $res = $this->callRegistryEndpoint($payload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(400, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('matches the provided booking details', (string) $res['json']['error']);

        $regCount = (int) $this->pdo->query('SELECT COUNT(*) FROM guest_registries')->fetchColumn();
        $this->assertSame(0, $regCount);
    }

    public function testCancelledReservationRejectionReturns400(): void
    {
        $payload = $this->createValidRegistryData([
            'reservation_code' => 'ovf_canc_200',
        ]);

        $res = $this->callRegistryEndpoint($payload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(400, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('cancelled', strtolower((string) $res['json']['error']));
    }

    public function testConcludedReservationRejectionReturns400(): void
    {
        $payload = $this->createValidRegistryData([
            'reservation_code' => 'ovf_concluded_300',
            'check_in' => '2020-01-01',
            'check_out' => '2020-01-05',
        ]);

        $res = $this->callRegistryEndpoint($payload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(400, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('concluded', strtolower((string) $res['json']['error']));
    }

    public function testRegistryEndpointRejectsMissingPrimaryGuestEmailWith400(): void
    {
        $payload = $this->createValidRegistryData();
        unset($payload['guest_email_1']);

        $res = $this->callRegistryEndpoint($payload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(400, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertSame('Please provide a valid email address for the primary guest.', $res['json']['error']);
        $this->assertContains('Please provide a valid email address for the primary guest.', $res['json']['errors']);
    }

    public function testRegistryEndpointRejectsInvalidPrimaryGuestEmailWith400(): void
    {
        $payload = $this->createValidRegistryData([
            'guest_email_1' => 'not-an-email-address',
        ]);

        $res = $this->callRegistryEndpoint($payload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(400, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertSame('Please provide a valid email address for the primary guest.', $res['json']['error']);
    }

    public function testRegistryEndpointRejectsEmailLongerThan100CharsWith400(): void
    {
        $payload = $this->createValidRegistryData([
            'guest_email_1' => str_repeat('a', 95) . '@example.com',
        ]);

        $res = $this->callRegistryEndpoint($payload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(400, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertSame('Please provide a valid email address for the primary guest.', $res['json']['error']);
    }

    public function testRegistryEndpointAcceptsFallbackEmailFields(): void
    {
        $payload = $this->createValidRegistryData();
        unset($payload['guest_email_1']);
        $payload['guest_email'] = 'fallback.guest@example.com';

        $res = $this->callRegistryEndpoint($payload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(200, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);
    }

    public function testSelectiveEmailEnrichmentForAirbnbVsWebReservations(): void
    {
        // 1. Airbnb reservation: initial relay email should be enriched
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, payment_status, registry_completed
            ) VALUES (
                'res-abnb-endpoint-test', '1606', 'Airbnb Guest', 'relay-encrypted@guest.airbnb.com', '+12025550199',
                '2026-11-22', '2026-11-25', 0.0, 'confirmed', 'airbnb', 'approved', 0
            );
        ");

        $abnbPayload = $this->createValidRegistryData([
            'reservation_code' => 'res-abnb-endpoint-test',
            'check_in' => '2026-11-22',
            'check_out' => '2026-11-25',
            'guest_email_1' => 'verified.traveler@example.com',
            'guest_name_1' => 'Verified Traveler',
        ]);
        $resAbnb = $this->callRegistryEndpoint($abnbPayload);
        $this->assertSame(0, $resAbnb['exitCode'], $resAbnb['stderr']);
        $this->assertSame(200, $resAbnb['statusCode']);
        $this->assertTrue($resAbnb['json']['success'] ?? false);

        $abnbEmail = $this->pdo->query("SELECT guest_email FROM reservations WHERE reservation_uid = 'res-abnb-endpoint-test'")->fetchColumn();
        $this->assertSame('verified.traveler@example.com', $abnbEmail);

        // 2. Direct web reservation with valid customer email: preserved, not overwritten
        $webPayload = $this->createValidRegistryData([
            'reservation_code' => 'ovf_conf_100',
            'check_in' => '2026-11-15',
            'check_out' => '2026-11-20',
            'guest_email_1' => 'companion.traveler@example.com',
            'guest_name_1' => 'Companion Traveler',
        ]);
        $resWeb = $this->callRegistryEndpoint($webPayload);
        $this->assertSame(0, $resWeb['exitCode'], $resWeb['stderr']);
        $this->assertSame(200, $resWeb['statusCode']);
        $this->assertTrue($resWeb['json']['success'] ?? false);

        $webEmail = $this->pdo->query("SELECT guest_email FROM reservations WHERE reservation_uid = 'ovf_conf_100'")->fetchColumn();
        $this->assertSame('maria@example.com', $webEmail);

        // 3. Direct web reservation with placeholder email: enriched
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, payment_status, registry_completed
            ) VALUES (
                'ovf_web_placeholder_test', '1606', 'Web Placeholder', 'guest@oceanviewflats.com', '+12025550199',
                '2026-11-26', '2026-11-30', 500000.0, 'confirmed', 'web', 'approved', 0
            );
        ");
        $placeholderPayload = $this->createValidRegistryData([
            'reservation_code' => 'ovf_web_placeholder_test',
            'check_in' => '2026-11-26',
            'check_out' => '2026-11-30',
            'guest_email_1' => 'actual.guest@example.com',
            'guest_name_1' => 'Actual Guest',
        ]);
        $resPlaceholder = $this->callRegistryEndpoint($placeholderPayload);
        $this->assertSame(0, $resPlaceholder['exitCode'], $resPlaceholder['stderr']);
        $this->assertSame(200, $resPlaceholder['statusCode']);
        $this->assertTrue($resPlaceholder['json']['success'] ?? false);

        $placeholderEmail = $this->pdo->query("SELECT guest_email FROM reservations WHERE reservation_uid = 'ovf_web_placeholder_test'")->fetchColumn();
        $this->assertSame('actual.guest@example.com', $placeholderEmail);
    }

    public function testValidSubmissionDispatchesAccessCredentialsEmailToPrimaryGuest(): void
    {
        $logFile = sys_get_temp_dir() . '/test_access_dispatch_emails_' . uniqid('', true) . '.json';
        if (file_exists($logFile)) {
            @unlink($logFile);
        }

        $payload = $this->createValidRegistryData();
        $escapedLogFile = addslashes($logFile);
        $autoloadPath = var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true);

        $prependCode = <<<PHP
            require_once {$autoloadPath};
            \$emailSender = new class implements \OceanViewFlats\Domain\Fulfillment\EmailSenderInterface {
                public function send(string \$to, string \$subject, string \$htmlBody, array \$headers = []): bool {
                    \$log = '{$escapedLogFile}';
                    \$messages = file_exists(\$log) ? json_decode((string) file_get_contents(\$log), true) : [];
                    \$messages[] = [
                        'to' => \$to,
                        'subject' => \$subject,
                        'htmlBody' => \$htmlBody,
                    ];
                    file_put_contents(\$log, json_encode(\$messages));
                    return true;
                }
            };
            \$GLOBALS['TEST_LIFECYCLE_SERVICE'] = \OceanViewFlats\Domain\Fulfillment\GuestLifecycleFulfillmentService::createDefault(
                \$GLOBALS['TEST_PDO'],
                [
                    'email_sender' => \$emailSender,
                    'clearance_sync' => null,
                ]
            );
PHP;

        $res = $this->callRegistryEndpoint($payload, 'POST', [], $prependCode);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(200, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);
        $doorCode = (string) ($res['json']['door_code'] ?? '');
        $this->assertMatchesRegularExpression('/^0[1-9]\d{5}#$/', $doorCode);

        $this->assertFileExists($logFile);
        $sentMessages = json_decode((string) file_get_contents($logFile), true);
        @unlink($logFile);

        $this->assertIsArray($sentMessages);
        $this->assertCount(2, $sentMessages);

        // Host Notification
        $this->assertSame('rentals@oceanviewflats.com', $sentMessages[0]['to']);
        $this->assertStringContainsString('OceanViewFlats Guest Registry Report', $sentMessages[0]['subject']);

        // Access Dispatch Email to Primary Guest
        $guestEmail = $sentMessages[1];
        $this->assertSame('maria.gomez@example.com', $guestEmail['to']);
        $this->assertStringContainsString('Access Credentials & Arrival Guide', $guestEmail['subject']);
        $this->assertStringContainsString($doorCode, $guestEmail['htmlBody']);
        $this->assertStringContainsString('Maria Gomez', $guestEmail['htmlBody']);
        $this->assertStringContainsString('/guide/index.html?code=ovf_conf_100', $guestEmail['htmlBody']);
    }

    public function testValidSubmissionSucceedsEvenIfEmailSendingFails(): void
    {
        $payload = $this->createValidRegistryData();
        $autoloadPath = var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true);

        $prependCode = <<<PHP
            require_once {$autoloadPath};
            \$emailSender = new class implements \OceanViewFlats\Domain\Fulfillment\EmailSenderInterface {
                public function send(string \$to, string \$subject, string \$htmlBody, array \$headers = []): bool {
                    throw new \RuntimeException('Outbound mail server unavailable');
                }
            };
            \$GLOBALS['TEST_LIFECYCLE_SERVICE'] = \OceanViewFlats\Domain\Fulfillment\GuestLifecycleFulfillmentService::createDefault(
                \$GLOBALS['TEST_PDO'],
                [
                    'email_sender' => \$emailSender,
                    'clearance_sync' => null,
                ]
            );
PHP;

        $res = $this->callRegistryEndpoint($payload, 'POST', [], $prependCode);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(200, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);
        $doorCode = (string) ($res['json']['door_code'] ?? '');
        $this->assertMatchesRegularExpression('/^0[1-9]\d{5}#$/', $doorCode);

        // Assert database was still updated
        $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE reservation_uid = :uid');
        $stmt->execute([':uid' => 'ovf_conf_100']);
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame(1, (int) $row['registry_completed']);
        $this->assertSame($doorCode, $row['door_code']);
    }

    public function testRegistryEndpointAcceptsStructuredGuestFields(): void
    {
        $captcha = $this->createValidCaptcha();
        $payload = array_merge([
            'reservation_code' => 'ovf_conf_100',
            'property' => '1606',
            'check_in' => '2026-11-15',
            'check_out' => '2026-11-20',
            'guest_count' => 2,
            'guest_email_1' => 'lucia.mendez@example.com',
            'guest_first_name_1' => 'Lucia',
            'guest_last_name_1' => 'Mendez',
            'guest_phone_1' => '+57 312 456 7890',
            'guest_country_1' => 'COLOMBIA',
            'guest_age_1' => 32,
            'guest_doc_type_1' => 'Cédula de Ciudadanía',
            'guest_doc_num_1' => 'CC52889900',
            'guest_first_name_2' => 'Mateo',
            'guest_last_name_2' => 'Mendez',
            'guest_phone_2' => '+57 312 111 2233',
            'guest_country_2' => 'COLOMBIA',
            'guest_age_2' => 8,
            'guest_doc_type_2' => 'Tarjeta de Identidad',
            'guest_doc_num_2' => 'TI11223344',
            'car_plates' => 'COL-999',
            'car_model' => 'Renault Duster',
            'lang' => 'es',
        ], $captcha);

        $res = $this->callRegistryEndpoint($payload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(200, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);
        $this->assertArrayHasKey('door_code', $res['json']);

        // Check occupants in SQLite database (encrypted at rest per ADR 0012)
        $stmt = $this->pdo->prepare('SELECT guests_payload FROM guest_registries WHERE reservation_uid = :uid');
        $stmt->execute([':uid' => 'ovf_conf_100']);
        $jsonStr = $stmt->fetchColumn();
        $this->assertIsString($jsonStr);
        $this->assertTrue(\OceanViewFlats\Domain\Security\PayloadEncryptor::isEncryptedEnvelope($jsonStr));
        $occupants = \OceanViewFlats\Domain\Security\PayloadEncryptor::decrypt($jsonStr);
        $this->assertCount(2, $occupants);
        $this->assertSame('Lucia Mendez', $occupants[0]['name']);
        $this->assertSame('+57 312 456 7890', $occupants[0]['phone']);
        $this->assertSame('COLOMBIA', $occupants[0]['country']);
    }

    public function testRegistryEndpointRejectsStructuredGuestWhenPrimaryPhoneMissing(): void
    {
        $captcha = $this->createValidCaptcha();
        $payload = array_merge([
            'reservation_code' => 'ovf_conf_100',
            'property' => '1606',
            'check_in' => '2026-11-15',
            'check_out' => '2026-11-20',
            'guest_count' => 1,
            'guest_email_1' => 'lucia.mendez@example.com',
            'guest_first_name_1' => 'Lucia',
            'guest_last_name_1' => 'Mendez',
            // Missing guest_phone_1!
            'guest_country_1' => 'COLOMBIA',
            'guest_age_1' => 32,
            'guest_doc_type_1' => 'Cédula de Ciudadanía',
            'guest_doc_num_1' => 'CC52889900',
            'lang' => 'en',
        ], $captcha);

        $res = $this->callRegistryEndpoint($payload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(400, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertSame('Please provide a valid phone number for the primary guest.', $res['json']['error']);
    }

    public function testRegistryEndpointRejectsInvalidCompanionPhone(): void
    {
        $captcha = $this->createValidCaptcha();
        $payload = array_merge([
            'reservation_code' => 'ovf_conf_100',
            'property' => '1606',
            'check_in' => '2026-11-15',
            'check_out' => '2026-11-20',
            'guest_count' => 2,
            'guest_email_1' => 'lucia.mendez@example.com',
            'guest_first_name_1' => 'Lucia',
            'guest_last_name_1' => 'Mendez',
            'guest_phone_1' => '+57 312 456 7890',
            'guest_country_1' => 'COLOMBIA',
            'guest_age_1' => 32,
            'guest_doc_type_1' => 'Passport',
            'guest_doc_num_1' => 'P12345',
            'guest_first_name_2' => 'Mateo',
            'guest_last_name_2' => 'Mendez',
            'guest_phone_2' => '12', // Invalid: too short (< 6 chars)
            'guest_country_2' => 'COLOMBIA',
            'guest_age_2' => 8,
            'guest_doc_type_2' => 'Tarjeta de Identidad',
            'guest_doc_num_2' => 'TI11223344',
            'lang' => 'en',
        ], $captcha);

        $res = $this->callRegistryEndpoint($payload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(400, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertSame('Please enter a valid phone number for Guest 2 (6-30 characters).', $res['json']['error']);
    }

    public function testSuccessfulRegistrySubmissionCreatesCondominiumClearanceRecord(): void
    {
        $payload = $this->createValidRegistryData();

        $autoloadPath = var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true);
        $prependCode = "
            require_once {$autoloadPath};
            class IntegrationTestClearanceTransport implements \\OceanViewFlats\\Domain\\Fulfillment\\HttpTransportInterface {
                public function post(string \$url, array|string \$data = [], array \$headers = [], array \$options = []): array {
                    if (strpos(\$url, 'reg_guest_owner_pre.php') !== false) {
                        return [
                            'statusCode' => 200,
                            'body' => json_encode(['status' => true, 'last_id' => 789]),
                            'headers' => [],
                            'cookies' => ['PHPSESSID' => 'test_sess_clearance'],
                            'error' => null,
                        ];
                    }
                    return [
                        'statusCode' => 200,
                        'body' => 'Registro exitoso',
                        'headers' => [],
                        'cookies' => [],
                        'error' => null,
                    ];
                }
            }
            \$GLOBALS['TEST_CLEARANCE_TRANSPORT'] = new IntegrationTestClearanceTransport();
        ";

        $res = $this->callRegistryEndpoint(
            $payload,
            'POST',
            [],
            $prependCode
        );

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(200, $res['statusCode']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);

        // Assert clearance record was persisted in condominium_clearances table
        $stmt = $this->pdo->prepare('SELECT * FROM condominium_clearances WHERE reservation_uid = :uid');
        $stmt->execute([':uid' => 'ovf_conf_100']);
        $clearanceRow = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($clearanceRow);
        $this->assertSame('ovf_conf_100', $clearanceRow['reservation_uid']);
        $this->assertSame('1606', $clearanceRow['property_id']);
        $this->assertSame('synced', $clearanceRow['status']);
        $this->assertSame('789', $clearanceRow['clearance_number']);
        $this->assertSame(1, (int) $clearanceRow['attempts']);
    }

    public function testOccupancyOverrideAllows8And12GuestsAndRejectsExceedingOrCompleted(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, payment_status, registry_completed, max_guests
            ) VALUES (
                'ovf_override_8', '1707', 'Group Host 8', 'group8@example.com', '+573001234567',
                '2026-12-01', '2026-12-05', 2500000.0, 'confirmed', 'approved', 0, 8
            ), (
                'ovf_override_12', '1707', 'Group Host 12', 'group12@example.com', '+573001234567',
                '2026-12-06', '2026-12-10', 3500000.0, 'confirmed', 'approved', 0, 12
            );
        ");

        // 1. Reject 7 guests on default max_guests = 6 reservation ('ovf_conf_100')
        $sevenData = $this->createValidCaptcha();
        $sevenData['reservation_code'] = 'ovf_conf_100';
        $sevenData['property'] = '1606';
        $sevenData['check_in'] = '2026-11-15';
        $sevenData['check_out'] = '2026-11-20';
        $sevenData['guest_count'] = 7;
        $sevenData['guest_email_1'] = 'maria@example.com';
        $sevenData['guest_phone_1'] = '+573001234567';
        for ($i = 1; $i <= 7; $i++) {
            $sevenData["guest_first_name_{$i}"] = "First{$i}";
            $sevenData["guest_last_name_{$i}"] = "Last{$i}";
            $sevenData["guest_age_{$i}"] = 25;
            $sevenData["guest_doc_type_{$i}"] = 'Passport';
            $sevenData["guest_doc_num_{$i}"] = "PA1000{$i}";
            $sevenData["guest_country_{$i}"] = 'COLOMBIA';
        }
        $res7On6 = $this->callRegistryEndpoint($sevenData);
        $this->assertSame(400, $res7On6['statusCode']);
        $this->assertFalse($res7On6['json']['success']);
        $this->assertContains('A reservation must register between 1 and 6 guests.', $res7On6['json']['errors']);

        // 2. Reject 9 guests on max_guests = 8 reservation ('ovf_override_8')
        $nineData = $this->createValidCaptcha();
        $nineData['reservation_code'] = 'ovf_override_8';
        $nineData['property'] = '1707';
        $nineData['check_in'] = '2026-12-01';
        $nineData['check_out'] = '2026-12-05';
        $nineData['guest_count'] = 9;
        $nineData['guest_email_1'] = 'group8@example.com';
        $nineData['guest_phone_1'] = '+573001234567';
        for ($i = 1; $i <= 9; $i++) {
            $nineData["guest_first_name_{$i}"] = "First{$i}";
            $nineData["guest_last_name_{$i}"] = "Last{$i}";
            $nineData["guest_age_{$i}"] = 25;
            $nineData["guest_doc_type_{$i}"] = 'Passport';
            $nineData["guest_doc_num_{$i}"] = "PA2000{$i}";
            $nineData["guest_country_{$i}"] = 'COLOMBIA';
        }
        $res9On8 = $this->callRegistryEndpoint($nineData);
        $this->assertSame(400, $res9On8['statusCode']);
        $this->assertFalse($res9On8['json']['success']);
        $this->assertContains('A reservation must register between 1 and 8 guests.', $res9On8['json']['errors']);

        // 3. Accept 12 guests on max_guests = 12 reservation ('ovf_override_12')
        $twelveData = $this->createValidCaptcha();
        $twelveData['reservation_code'] = 'ovf_override_12';
        $twelveData['property'] = '1707';
        $twelveData['check_in'] = '2026-12-06';
        $twelveData['check_out'] = '2026-12-10';
        $twelveData['guest_count'] = 12;
        $twelveData['guest_email_1'] = 'group12@example.com';
        $twelveData['guest_phone_1'] = '+573001234567';
        for ($i = 1; $i <= 12; $i++) {
            $twelveData["guest_first_name_{$i}"] = "First{$i}";
            $twelveData["guest_last_name_{$i}"] = "Last{$i}";
            $twelveData["guest_age_{$i}"] = 20 + $i;
            $twelveData["guest_doc_type_{$i}"] = 'Passport';
            $twelveData["guest_doc_num_{$i}"] = sprintf('PA3000%02d', $i);
            $twelveData["guest_country_{$i}"] = 'COLOMBIA';
        }
        $res12On12 = $this->callRegistryEndpoint($twelveData);
        $this->assertSame(200, $res12On12['statusCode']);
        $this->assertTrue($res12On12['json']['success']);

        $stmtReg12 = $this->pdo->prepare('SELECT guest_count, guests_payload FROM guest_registries WHERE reservation_uid = :uid');
        $stmtReg12->execute([':uid' => 'ovf_override_12']);
        $regRow12 = $stmtReg12->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($regRow12);
        $this->assertSame(12, (int) $regRow12['guest_count']);
        $decoded12 = \OceanViewFlats\Domain\Security\PayloadEncryptor::decrypt((string) $regRow12['guests_payload']);
        $this->assertCount(12, $decoded12);

        // 4. Reject re-submission once registry_completed = 1
        $resubmit = $this->callRegistryEndpoint($twelveData);
        $this->assertSame(400, $resubmit['statusCode']);
        $this->assertFalse($resubmit['json']['success']);
        $this->assertContains('Guest registry has already been completed for this reservation.', $resubmit['json']['errors']);
    }

    /**
     * Executes public/api/registry-processor.php via a sub-process to test HTTP guards in complete isolation.
     *
     * @param array<string, mixed> $bodyData
     * @param string $method
     * @param array<string, string> $serverVars
     * @param string $prependCode
     * @return array{exitCode: int, stdout: string, stderr: string, statusCode: int, json: ?array<string, mixed>}
     */
    private function callRegistryEndpoint(
        array $bodyData = [],
        string $method = 'POST',
        array $serverVars = [],
        string $prependCode = ''
    ): array {
        $rateLimitFile = sys_get_temp_dir() . '/ovf_registry_rate_limits.json';
        if (file_exists($rateLimitFile)) {
            @unlink($rateLimitFile);
        }

        $defaultServer = [
            'REQUEST_METHOD' => $method,
            'HTTP_HOST' => 'oceanviewflats.com',
            'HTTP_REFERER' => 'https://oceanviewflats.com/registry',
            'REMOTE_ADDR' => '127.0.0.1',
        ];
        $mergedServer = array_merge($defaultServer, $serverVars);

        $jsonInput = json_encode($bodyData);

        $dbInit = sprintf(
            '$GLOBALS["TEST_PDO"] = new PDO("sqlite:%s"); $GLOBALS["TEST_PDO"]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $GLOBALS["TEST_PDO"]->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);',
            $this->dbPath
        );

        $autoloadPath = var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true);
        $defaultClearanceTransport = sprintf(
            'require_once %s; $GLOBALS["TEST_CLEARANCE_TRANSPORT"] = new class implements \OceanViewFlats\Domain\Fulfillment\HttpTransportInterface { public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array { if (strpos($url, "reg_guest_owner_pre.php") !== false) { return ["statusCode" => 200, "body" => json_encode(["status" => true, "last_id" => 100]), "headers" => [], "cookies" => ["PHPSESSID" => "test_default_sess"], "error" => null]; } return ["statusCode" => 200, "body" => "Registro exitoso", "headers" => [], "cookies" => [], "error" => null]; } };',
            $autoloadPath
        );

        $fullPrepend = sprintf(
            'register_shutdown_function(function() { fwrite(STDERR, "[STATUS:" . http_response_code() . "]"); }); %s; %s; %s',
            $dbInit,
            $defaultClearanceTransport,
            $prependCode
        );

        $phpCode = sprintf(
            '%s; foreach (%s as $k => $v) { $_SERVER[$k] = $v; }; require %s;',
            $fullPrepend,
            var_export($mergedServer, true),
            var_export(dirname(__DIR__, 3) . '/public/api/registry-processor.php', true)
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
