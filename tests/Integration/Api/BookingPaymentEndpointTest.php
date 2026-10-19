<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Integration\Api;

use OceanViewFlats\Domain\Fulfillment\InMemoryEmailSender;
use OceanViewFlats\Domain\Payment\BookingPaymentProcessor;
use OceanViewFlats\Domain\Payment\BookingPaymentRequest;
use OceanViewFlats\Domain\Payment\InMemoryPaymentGateway;
use OceanViewFlats\Domain\Payment\PaymentGatewayResult;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use PDO;
use PHPUnit\Framework\TestCase;

final class BookingPaymentEndpointTest extends TestCase
{
    private PDO $pdo;
    private InMemoryPaymentGateway $gateway;
    private InMemoryEmailSender $emailSender;
    private BookingPaymentProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. In-memory SQLite database
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->createSchema();

        // 2. Dependencies
        $this->gateway = new InMemoryPaymentGateway();
        $this->emailSender = new InMemoryEmailSender();

        $this->processor = BookingPaymentProcessor::createDefault($this->pdo, [
            'gateway' => $this->gateway,
            'emailSender' => $this->emailSender,
            'publicSiteUrl' => 'https://oceanviewflats.com',
            'hostNotificationEmail' => 'reservas@oceanviewflats.com',
        ]);
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
                refunded_amount REAL DEFAULT 0.0,
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
                lang TEXT,
                registry_completed INTEGER DEFAULT 0,
                registry_completed_at TEXT,
                door_code TEXT,
                privacy_policy_version TEXT DEFAULT NULL,
                privacy_consent_at TEXT DEFAULT NULL,
                privacy_consent_ip TEXT DEFAULT NULL,
                pii_redacted_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE payment_idempotency (
                idempotency_key TEXT PRIMARY KEY,
                payment_id TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE admin_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE
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
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE calendar_blocks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                property_id TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                reason TEXT NOT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    private function createValidRequest(array $overrides = []): BookingPaymentRequest
    {
        $data = array_merge([
            'property_id' => '1606',
            'check_in' => '2026-11-10',
            'check_out' => '2026-11-14',
            'guest_name' => 'Carlos Rodriguez',
            'guest_email' => 'carlos@example.com',
            'guest_phone' => '+57 300 987 6543',
            'payment_method_id' => 'visa',
            'token' => 'tok_test_card_123',
            'installments' => 1,
            'lang' => 'en',
            'client_ip' => '190.24.12.34',
            'idempotency_key' => 'idem_' . bin2hex(random_bytes(6)),
            'privacy_consent' => true,
            'privacy_policy_version' => '2026-10',
        ], $overrides);

        return BookingPaymentRequest::fromArray($data);
    }

    /**
     * Card payment approved confirms booking and dispatches fulfillment emails.
     */
    public function testApprovedCreditCardPaymentConfirmsBookingAndDispatchesFulfillmentEmails(): void
    {
        $this->gateway->stageCreateResult(new PaymentGatewayResult(
            success: true,
            paymentId: 'pay_card_approved_101',
            status: 'approved',
            statusDetail: 'accredited',
            paymentMethodId: 'visa',
            transactionAmount: 1610000.0,
            externalResourceUrl: null,
            barcode: null,
            verificationCode: null,
            rawResponse: ['id' => 'pay_card_approved_101', 'status' => 'approved']
        ));

        $idempotencyKey = 'idem_card_valid_123';
        $request = $this->createValidRequest([
            'idempotency_key' => $idempotencyKey,
            'lang' => 'en',
        ]);

        $result = $this->processor->processBookingPayment($request);

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->httpStatusCode);
        $this->assertSame('confirmed', $result->status);
        $this->assertNotNull($result->reservationUid);
        $this->assertSame('pay_card_approved_101', $result->extra['payment_id'] ?? null);
        $this->assertSame($result->reservationUid, $result->extra['reservation_code'] ?? null);

        // Verify gateway intent was recorded
        $intents = $this->gateway->getRecordedIntents();
        $this->assertCount(1, $intents);
        $this->assertSame('visa', $intents[0]->paymentMethodId);
        $this->assertSame('carlos@example.com', $intents[0]->payerEmail);

        // Verify reservation in database
        $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE reservation_uid = :uid');
        $stmt->execute(['uid' => $result->reservationUid]);
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame(ReservationStatus::CONFIRMED->value, $row['status']);
        $this->assertSame('approved', $row['payment_status']);
        $this->assertSame('pay_card_approved_101', $row['mercadopago_payment_id']);
        $this->assertSame('1606', $row['property_id']);
        $this->assertSame('Carlos Rodriguez', $row['guest_name']);
        $this->assertSame('2026-10', $row['privacy_policy_version']);
        $this->assertNotEmpty($row['privacy_consent_at']);
        $this->assertSame('190.24.12.34', $row['privacy_consent_ip']);

        // Verify idempotency was saved
        $stmt = $this->pdo->prepare('SELECT payment_id FROM payment_idempotency WHERE idempotency_key = :k');
        $stmt->execute(['k' => $idempotencyKey]);
        $this->assertSame('pay_card_approved_101', $stmt->fetchColumn());

        // Verify audit log
        $auditCount = (int) $this->pdo->query('SELECT COUNT(*) FROM admin_audit_logs WHERE action = "reservation_confirmed"')->fetchColumn();
        $this->assertSame(1, $auditCount);

        // Verify emails were sent (guest + host)
        $sentEmails = $this->emailSender->getSentMessages();
        $this->assertCount(2, $sentEmails);
        // ADR 0001: Door codes must NEVER appear in confirmation emails
        foreach ($sentEmails as $email) {
            $this->assertStringNotContainsString('door code', strtolower($email['htmlBody']));
            $this->assertStringNotContainsString('smart lock', strtolower($email['htmlBody']));
        }
    }

    /**
     * PSE / cash voucher pending payment issues voucher and sends instructions.
     */
    public function testPendingPaymentForPseOrCashVoucherIssuesVoucherAndDispatchesInstructions(): void
    {
        $this->gateway->stageCreateResult(new PaymentGatewayResult(
            success: true,
            paymentId: 'pay_pse_pending_202',
            status: 'pending',
            statusDetail: 'pending_waiting_transfer',
            paymentMethodId: 'pse',
            transactionAmount: 1610000.0,
            externalResourceUrl: 'https://mercadopago.com/pse/redirect/202',
            barcode: null,
            verificationCode: null,
            rawResponse: ['id' => 'pay_pse_pending_202', 'status' => 'pending']
        ));

        $idempotencyKey = 'idem_pse_pending_456';
        $request = $this->createValidRequest([
            'payment_method_id' => 'pse',
            'token' => null,
            'idempotency_key' => $idempotencyKey,
            'lang' => 'es',
        ]);

        $result = $this->processor->processBookingPayment($request);

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->httpStatusCode);
        $this->assertSame('pending_payment', $result->status);
        $this->assertNotNull($result->reservationUid);
        $this->assertSame('https://mercadopago.com/pse/redirect/202', $result->extra['external_resource_url'] ?? null);
        $this->assertSame('pay_pse_pending_202', $result->extra['payment_id'] ?? null);
        $this->assertSame($result->reservationUid, $result->extra['reservation_code'] ?? null);

        // Verify reservation in database is pending_payment
        $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE reservation_uid = :uid');
        $stmt->execute(['uid' => $result->reservationUid]);
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame(ReservationStatus::PENDING_PAYMENT->value, $row['status']);
        $this->assertSame('pending', $row['payment_status']);
        $this->assertSame('pay_pse_pending_202', $row['mercadopago_payment_id']);

        // Verify instructions emails were dispatched (host notification + guest instructions)
        $sentEmails = $this->emailSender->getSentMessages();
        $this->assertCount(2, $sentEmails);
        $toAddresses = array_column($sentEmails, 'to');
        $this->assertContains('carlos@example.com', $toAddresses);
        $this->assertContains('reservas@oceanviewflats.com', $toAddresses);

        $guestEmail = null;
        foreach ($sentEmails as $email) {
            if ($email['to'] === 'carlos@example.com') {
                $guestEmail = $email;
                break;
            }
        }
        $this->assertNotNull($guestEmail);
        $this->assertStringContainsString('https://mercadopago.com/pse/redirect/202', $guestEmail['htmlBody']);

        // Verify audit log
        $auditCount = (int) $this->pdo->query('SELECT COUNT(*) FROM admin_audit_logs WHERE action = "direct_hold_created"')->fetchColumn();
        $this->assertSame(1, $auditCount);
    }

    /**
     * Availability collision returns 409 conflict and prevents gateway charge.
     */
    public function testAvailabilityCollisionReturns409Conflict(): void
    {
        // Pre-insert an active confirmed reservation covering overlapping dates
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status)
            VALUES ('ovf-exist-123', '1606', 'Existing Guest', 'exist@example.com', '+573001234567', '2026-11-12', '2026-11-16', 1500000.00, 'confirmed', 'approved');
        ");

        $request = $this->createValidRequest([
            'property_id' => '1606',
            'check_in' => '2026-11-10',
            'check_out' => '2026-11-14',
        ]);

        $result = $this->processor->processBookingPayment($request);

        $this->assertFalse($result->success);
        $this->assertSame(409, $result->httpStatusCode);
        $this->assertSame('error', $result->status);
        $this->assertStringContainsString('dates', strtolower($result->message));

        // Gateway should never have been invoked
        $this->assertEmpty($this->gateway->getRecordedIntents());
    }

    /**
     * Minimum stay violation returns 422 error.
     */
    public function testMinimumStayViolationReturns422Error(): void
    {
        // Property 1606 requires at least 2 nights. Request only 1 night.
        $request = $this->createValidRequest([
            'property_id' => '1606',
            'check_in' => '2026-11-10',
            'check_out' => '2026-11-11', // 1 night
        ]);

        $result = $this->processor->processBookingPayment($request);

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->httpStatusCode);
        $this->assertSame('error', $result->status);
        $this->assertStringContainsString('minimum stay', strtolower($result->message));

        // Gateway should never have been invoked
        $this->assertEmpty($this->gateway->getRecordedIntents());
    }

    /**
     * Invalid input validation rejects missing dates, short names, and invalid properties with 400.
     */
    public function testInvalidInputValidationRejectsInvalidParametersWith400(): void
    {
        // 1. Invalid property
        $resInvalidProp = $this->processor->processBookingPayment(
            $this->createValidRequest(['property_id' => '9999'])
        );
        $this->assertFalse($resInvalidProp->success);
        $this->assertSame(400, $resInvalidProp->httpStatusCode);

        // 2. Short guest name (< 3 characters)
        $resShortName = $this->processor->processBookingPayment(
            $this->createValidRequest(['guest_name' => 'Al'])
        );
        $this->assertFalse($resShortName->success);
        $this->assertSame(400, $resShortName->httpStatusCode);

        // 3. Invalid guest email
        $resBadEmail = $this->processor->processBookingPayment(
            $this->createValidRequest(['guest_email' => 'invalid-not-an-email'])
        );
        $this->assertFalse($resBadEmail->success);
        $this->assertSame(400, $resBadEmail->httpStatusCode);

        // 4. Missing / empty phone
        $resBadPhone = $this->processor->processBookingPayment(
            $this->createValidRequest(['guest_phone' => '12'])
        );
        $this->assertFalse($resBadPhone->success);
        $this->assertSame(400, $resBadPhone->httpStatusCode);

        // 5. Check-out date before or equal to check-in
        $resReversedDates = $this->processor->processBookingPayment(
            $this->createValidRequest(['check_in' => '2026-11-15', 'check_out' => '2026-11-10'])
        );
        $this->assertFalse($resReversedDates->success);
        $this->assertSame(400, $resReversedDates->httpStatusCode);

        // 6. Past check-in date
        $resPastDate = $this->processor->processBookingPayment(
            $this->createValidRequest(['check_in' => '2020-01-01', 'check_out' => '2020-01-05'])
        );
        $this->assertFalse($resPastDate->success);
        $this->assertSame(400, $resPastDate->httpStatusCode);

        // 7. Missing privacy consent (Ley 1581 of 2012)
        $resMissingConsent = $this->processor->processBookingPayment(
            $this->createValidRequest(['privacy_consent' => false])
        );
        $this->assertFalse($resMissingConsent->success);
        $this->assertSame(400, $resMissingConsent->httpStatusCode);
        $this->assertStringContainsString('personal data processing policy', strtolower($resMissingConsent->message));
    }

    /**
     * Replaying an already processed idempotency key returns existing payment without charging again.
     */
    public function testIdempotencyShieldPreventsDoubleChargingOnReplay(): void
    {
        // Stage pre-existing idempotency record
        $this->pdo->exec("
            INSERT INTO payment_idempotency (idempotency_key, payment_id)
            VALUES ('idem_replay_key_777', 'pay_already_processed_999');
        ");

        $request = $this->createValidRequest([
            'idempotency_key' => 'idem_replay_key_777',
        ]);

        $result = $this->processor->processBookingPayment($request);

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->httpStatusCode);
        $this->assertSame('Payment already processed.', $result->message);
        $this->assertSame('pay_already_processed_999', $result->extra['payment_id'] ?? null);
        $this->assertEmpty($this->gateway->getRecordedIntents());
    }

    public function testIdempotencyShieldRespectsConfirmedReservationStatus(): void
    {
        $idempotencyKey = 'idem_replay_conf_777';
        $paymentId = 'pay_conf_888';
        $this->pdo->exec("INSERT INTO payment_idempotency (idempotency_key, payment_id) VALUES ('{$idempotencyKey}', '{$paymentId}')");
        $this->pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, mercadopago_payment_id)
            VALUES ('ovf_int_conf_1', '1606', 'Maria Gomez', 'maria@example.com', '+573009998888', '2026-11-10', '2026-11-14', 1610000, 'confirmed', '{$paymentId}')");

        $request = $this->createValidRequest([
            'idempotency_key' => $idempotencyKey,
        ]);

        $result = $this->processor->processBookingPayment($request);

        $this->assertTrue($result->success);
        $this->assertSame('confirmed', $result->status);
        $this->assertSame('Payment already processed.', $result->message);
        $this->assertSame('ovf_int_conf_1', $result->extra['reservation_code'] ?? null);
        $this->assertSame('confirmed', $result->extra['status'] ?? null);
        $this->assertEmpty($this->gateway->getRecordedIntents());
    }

    public function testIdempotencyShieldRespectsPendingPaymentReservationStatus(): void
    {
        $idempotencyKey = 'idem_replay_pend_777';
        $paymentId = 'pay_pend_888';
        $this->pdo->exec("INSERT INTO payment_idempotency (idempotency_key, payment_id) VALUES ('{$idempotencyKey}', '{$paymentId}')");
        $this->pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, mercadopago_payment_id)
            VALUES ('ovf_int_pend_1', '1606', 'Maria Gomez', 'maria@example.com', '+573009998888', '2026-11-10', '2026-11-14', 1610000, 'pending_payment', '{$paymentId}')");

        $request = $this->createValidRequest([
            'idempotency_key' => $idempotencyKey,
        ]);

        $result = $this->processor->processBookingPayment($request);

        $this->assertTrue($result->success);
        $this->assertSame('pending_payment', $result->status);
        $this->assertSame('Payment already processed.', $result->message);
        $this->assertSame('ovf_int_pend_1', $result->extra['reservation_code'] ?? null);
        $this->assertSame('pending_payment', $result->extra['status'] ?? null);
        $this->assertEmpty($this->gateway->getRecordedIntents());
    }

    public function testPaymentEndpointRejectsNonPostMethodWith405(): void
    {
        $res = $this->callPaymentEndpoint([], 'GET');

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertSame('Method Not Allowed', $res['json']['error']);
    }

    public function testPaymentEndpointCatchesHoneypotSubmission(): void
    {
        $res = $this->callPaymentEndpoint(['website_url' => 'http://spam-bot.xyz']);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);
        $this->assertSame('Booking request received.', $res['json']['message']);
    }

    public function testPaymentEndpointEnforcesRefererCheckForDirectBrowserAccess(): void
    {
        $res = $this->callPaymentEndpoint(
            ['property_id' => '1606'],
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

    public function testPaymentEndpointRejectsUnauthorizedRefererHost(): void
    {
        $res = $this->callPaymentEndpoint(
            ['property_id' => '1606'],
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

    public function testPaymentEndpointRejectsInvalidCaptchaChallenge(): void
    {
        $res = $this->callPaymentEndpoint([
            'property_id' => '1606',
            'captcha_challenge' => '3 + 4',
            'captcha_signature' => 'invalid_signature_hash',
            'captcha_response' => '7',
        ]);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertNotEmpty($res['json']['error']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $rateLimitFile = sys_get_temp_dir() . '/ovf_payments_rate_limits.json';
        if (file_exists($rateLimitFile)) {
            @unlink($rateLimitFile);
        }
    }

    /**
     * Executes public/api/payment.php via a sub-process to test HTTP guards in complete isolation.
     *
     * @param array<string, mixed> $bodyData
     * @param string $method
     * @param array<string, string> $serverVars
     * @param string $prependCode
     * @return array{exitCode: int, stdout: string, stderr: string, json: ?array<string, mixed>}
     */
    private function callPaymentEndpoint(
        array $bodyData = [],
        string $method = 'POST',
        array $serverVars = [],
        string $prependCode = ''
    ): array {
        $rateLimitFile = sys_get_temp_dir() . '/ovf_payments_rate_limits.json';
        if (file_exists($rateLimitFile)) {
            @unlink($rateLimitFile);
        }

        $defaultServer = [
            'REQUEST_METHOD' => $method,
            'HTTP_HOST' => 'oceanviewflats.com',
            'HTTP_REFERER' => 'https://oceanviewflats.com/booking',
            'REMOTE_ADDR' => '127.0.0.1',
        ];
        $mergedServer = array_merge($defaultServer, $serverVars);

        $jsonInput = json_encode($bodyData);

        $phpCode = sprintf(
            '%s; foreach (%s as $k => $v) { $_SERVER[$k] = $v; }; require %s;',
            $prependCode,
            var_export($mergedServer, true),
            var_export(dirname(__DIR__, 3) . '/public/api/payment.php', true)
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

        return [
            'exitCode' => $exitCode,
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
            'json' => json_decode((string) $stdout, true),
        ];
    }
}
