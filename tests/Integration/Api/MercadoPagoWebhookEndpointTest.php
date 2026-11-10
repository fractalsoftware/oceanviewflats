<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Integration\Api;

use OceanViewFlats\Domain\Fulfillment\InMemoryEmailSender;
use OceanViewFlats\Domain\Payment\InMemoryPaymentGateway;
use OceanViewFlats\Domain\Payment\WebhookSettlementProcessor;
use OceanViewFlats\Domain\Payment\WebhookSettlementResult;
use PDO;
use PHPUnit\Framework\TestCase;

final class MercadoPagoWebhookEndpointTest extends TestCase
{
    private string $dbFile;
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dbFile = sys_get_temp_dir() . '/ovf_webhook_test_' . uniqid('', true) . '.sqlite';
        $this->pdo = new PDO('sqlite:' . $this->dbFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('
            CREATE TABLE reservations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_uid TEXT NOT NULL UNIQUE,
                property_id TEXT NOT NULL,
                guest_name TEXT NOT NULL,
                guest_email TEXT NOT NULL,
                guest_phone TEXT NOT NULL,
                check_in TEXT NOT NULL,
                check_out TEXT NOT NULL,
                total_price REAL NOT NULL,
                refunded_amount REAL NOT NULL DEFAULT 0.00,
                status TEXT NOT NULL,
                source TEXT NOT NULL DEFAULT "web",
                external_confirmation_code TEXT DEFAULT NULL,
                channel_block_uid TEXT DEFAULT NULL,
                mercadopago_preference_id TEXT DEFAULT NULL,
                mercadopago_payment_id TEXT DEFAULT NULL,
                payment_status TEXT DEFAULT NULL,
                payment_method_id TEXT DEFAULT NULL,
                payment_detail TEXT DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                lang TEXT DEFAULT "en",
                registry_completed INTEGER DEFAULT 0,
                door_code TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE reservation_refunds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_uid TEXT NOT NULL,
                mercadopago_refund_id TEXT UNIQUE DEFAULT NULL,
                mercadopago_payment_id TEXT NOT NULL,
                amount REAL NOT NULL,
                status TEXT NOT NULL DEFAULT "approved",
                reason TEXT DEFAULT NULL,
                source TEXT NOT NULL DEFAULT "mercadopago_webhook",
                admin_user_id INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE admin_audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_user_id INTEGER DEFAULT NULL,
                action TEXT NOT NULL,
                entity_type TEXT NOT NULL,
                entity_id TEXT NOT NULL,
                payload_before TEXT DEFAULT NULL,
                payload_after TEXT DEFAULT NULL,
                ip_address TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');

        $rateLimitFile = sys_get_temp_dir() . '/ovf_webhook_rate_limits.json';
        if (file_exists($rateLimitFile)) {
            @unlink($rateLimitFile);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbFile)) {
            @unlink($this->dbFile);
        }
        $rateLimitFile = sys_get_temp_dir() . '/ovf_webhook_rate_limits.json';
        if (file_exists($rateLimitFile)) {
            @unlink($rateLimitFile);
        }
        parent::tearDown();
    }

    /**
     * Rule 1: Resurrection Defense.
     * When a webhook payment approved event arrives for a reservation that is already 'cancelled',
     * the webhook must ignore it and refuse resurrection.
     */
    public function testResurrectionDefenseRule1CancelledReservationRefusesApprovalResurrection(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status, mercadopago_payment_id)
            VALUES ('ovf-resurrect-guard', '1606', 'Guarded Guest', 'guard@example.com', '+573001112233', '2026-11-20', '2026-11-25', 1500000.00, 'cancelled', 'cancelled', '99887766');
        ");

        $paymentPayload = [
            'id' => '99887766',
            'status' => 'approved',
            'status_detail' => 'accredited',
            'external_reference' => 'ovf-resurrect-guard',
            'transaction_amount' => 1500000.00,
            'transaction_amount_refunded' => 0.0,
            'payment_method_id' => 'visa',
        ];

        $res = $this->callWebhook('99887766', $paymentPayload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success'] ?? false);
        $this->assertSame('ignored_cancelled_terminal', $res['json']['status'] ?? null);

        // Verify database state remains cancelled
        $stmt = $this->pdo->query("SELECT status, payment_status FROM reservations WHERE reservation_uid = 'ovf-resurrect-guard'");
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame('cancelled', $row['status']);
    }

    /**
     * Rule 2: External Full Refund Synchronization.
     * When an external full refund webhook arrives for a 'confirmed' reservation,
     * the webhook must auto-cancel the reservation, update refunded_amount, record the refund row,
     * and log an audit trail event.
     */
    public function testExternalFullRefundRule2AutoCancelsConfirmedReservationAndReleasesDates(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status, mercadopago_payment_id)
            VALUES ('ovf-external-refund', '1707', 'Refunder Guest', 'ref@example.com', '+573001112233', '2026-12-01', '2026-12-05', 1200000.00, 'confirmed', 'approved', '88776655');
        ");

        $paymentPayload = [
            'id' => '88776655',
            'status' => 'refunded',
            'status_detail' => 'refunded',
            'external_reference' => 'ovf-external-refund',
            'transaction_amount' => 1200000.00,
            'transaction_amount_refunded' => 1200000.00,
            'payment_method_id' => 'pse',
            'refunds' => [
                [
                    'id' => 771122,
                    'payment_id' => 88776655,
                    'amount' => 1200000.00,
                    'status' => 'approved',
                ],
            ],
        ];

        $res = $this->callWebhook('88776655', $paymentPayload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success'] ?? false);
        $this->assertSame(WebhookSettlementResult::STATUS_REFUND_CANCELLED, $res['json']['status'] ?? null);
        $this->assertSame('cancelled', $res['json']['data']['reservation_status'] ?? ($res['json']['reservation_status'] ?? null));

        // Verify reservation updated to cancelled
        $stmt = $this->pdo->query("SELECT status, payment_status, refunded_amount, notes FROM reservations WHERE reservation_uid = 'ovf-external-refund'");
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame('cancelled', $row['status']);
        $this->assertSame('refunded', $row['payment_status']);
        $this->assertEquals(1200000.00, (float) $row['refunded_amount']);
        $this->assertStringContainsString('External Refund Sync', (string) $row['notes']);

        // Verify reservation_refunds record created
        $stmt = $this->pdo->query("SELECT * FROM reservation_refunds WHERE reservation_uid = 'ovf-external-refund'");
        $refundRow = $stmt->fetch();
        $this->assertIsArray($refundRow);
        $this->assertSame('771122', (string) $refundRow['mercadopago_refund_id']);
        $this->assertEquals(1200000.00, (float) $refundRow['amount']);
        $this->assertSame('mercadopago_webhook', $refundRow['source']);

        // Verify audit log created
        $stmt = $this->pdo->query("SELECT action FROM admin_audit_logs WHERE entity_id = 'ovf-external-refund' ORDER BY id ASC");
        $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertTrue(in_array('refund_cancellation', $actions, true) || in_array('external_refund_cancellation', $actions, true));
    }

    /**
     * External Full Refund triggers localized cancellation email notification and audit log.
     */
    public function testExternalFullRefundDispatchesCancellationEmailNotificationAndRecordsAuditLog(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status, mercadopago_payment_id, lang)
            VALUES ('ovf-ext-email-test', '1707', 'Maria Host', 'maria@example.com', '+573009998877', '2026-12-01', '2026-12-05', 1200000.00, 'confirmed', 'approved', '99881122', 'es');
        ");

        $paymentPayload = [
            'id' => '99881122',
            'status' => 'refunded',
            'status_detail' => 'refunded',
            'external_reference' => 'ovf-ext-email-test',
            'transaction_amount' => 1200000.00,
            'transaction_amount_refunded' => 1200000.00,
            'payment_method_id' => 'pse',
            'refunds' => [
                [
                    'id' => 881122,
                    'payment_id' => 99881122,
                    'amount' => 1200000.00,
                    'status' => 'approved',
                ],
            ],
        ];

        $res = $this->callWebhook('99881122', $paymentPayload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success'] ?? false);

        // Verify audit logs
        $stmt = $this->pdo->query("SELECT action, payload_after FROM admin_audit_logs WHERE entity_id = 'ovf-ext-email-test' ORDER BY id ASC");
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $actions = array_column($logs, 'action');
        $this->assertTrue(in_array('refund_cancellation', $actions, true) || in_array('external_refund_cancellation', $actions, true));
        $this->assertContains('cancellation_email_sent', $actions);

        $emailLog = null;
        foreach ($logs as $log) {
            if ($log['action'] === 'cancellation_email_sent') {
                $emailLog = json_decode((string) $log['payload_after'], true);
                break;
            }
        }
        $this->assertNotNull($emailLog);
        $this->assertSame('maria@example.com', $emailLog['recipient']);
        $this->assertEquals(1200000.0, (float) $emailLog['refund_amount']);
        $this->assertEquals(0.0, (float) $emailLog['policy_retention']);
        $this->assertSame('mercadopago_webhook', $emailLog['trigger']);
    }

    /**
     * External Partial Refund Synchronization.
     * When a partial refund arrives for a 'confirmed' reservation,
     * status stays confirmed, but payment_status becomes partially_refunded and refund item is recorded.
     */
    public function testExternalPartialRefundUpdatesRefundedAmountAndRetainsConfirmedStay(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status, mercadopago_payment_id)
            VALUES ('ovf-partial-sync', '1606', 'Partial Guest', 'part@example.com', '+573001112233', '2026-12-10', '2026-12-15', 2000000.00, 'confirmed', 'approved', '66554433');
        ");

        $paymentPayload = [
            'id' => '66554433',
            'status' => 'approved',
            'status_detail' => 'partially_refunded',
            'external_reference' => 'ovf-partial-sync',
            'transaction_amount' => 2000000.00,
            'transaction_amount_refunded' => 500000.00,
            'payment_method_id' => 'visa',
            'refunds' => [
                [
                    'id' => 332211,
                    'payment_id' => 66554433,
                    'amount' => 500000.00,
                    'status' => 'approved',
                ],
            ],
        ];

        $res = $this->callWebhook('66554433', $paymentPayload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success'] ?? false);
        $this->assertSame(WebhookSettlementResult::STATUS_PARTIAL_REFUND_SYNCED, $res['json']['status'] ?? null);
        $this->assertSame('confirmed', $res['json']['data']['reservation_status'] ?? ($res['json']['reservation_status'] ?? null));

        // Verify reservation stayed confirmed
        $stmt = $this->pdo->query("SELECT status, payment_status, refunded_amount FROM reservations WHERE reservation_uid = 'ovf-partial-sync'");
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame('confirmed', $row['status']);
        $this->assertSame('partially_refunded', $row['payment_status']);
        $this->assertEquals(500000.00, (float) $row['refunded_amount']);

        // Verify refund recorded
        $stmt = $this->pdo->query("SELECT * FROM reservation_refunds WHERE reservation_uid = 'ovf-partial-sync'");
        $refundRow = $stmt->fetch();
        $this->assertIsArray($refundRow);
        $this->assertSame('332211', (string) $refundRow['mercadopago_refund_id']);
        $this->assertEquals(500000.00, (float) $refundRow['amount']);
    }

    /**
     * Partial refund on an already cancelled reservation does NOT resurrect it.
     */
    public function testPartialRefundOnCancelledReservationDoesNotResurrect(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status, mercadopago_payment_id)
            VALUES ('ovf-cancel-partial', '1606', 'Cancel Part Guest', 'cpart@example.com', '+573001112233', '2026-12-16', '2026-12-20', 1000000.00, 'cancelled', 'cancelled', '55443322');
        ");

        $paymentPayload = [
            'id' => '55443322',
            'status' => 'partially_refunded',
            'status_detail' => 'partially_refunded',
            'external_reference' => 'ovf-cancel-partial',
            'transaction_amount' => 1000000.00,
            'transaction_amount_refunded' => 400000.00,
            'refunds' => [
                [
                    'id' => 443322,
                    'amount' => 400000.00,
                ],
            ],
        ];

        $res = $this->callWebhook('55443322', $paymentPayload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $stmt = $this->pdo->query("SELECT status, payment_status, refunded_amount FROM reservations WHERE reservation_uid = 'ovf-cancel-partial'");
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame('cancelled', $row['status']);
        $this->assertSame('partially_refunded', $row['payment_status']);
        $this->assertEquals(400000.00, (float) $row['refunded_amount']);
    }

    /**
     * Full refund on an already cancelled reservation syncs refund line items without error.
     */
    public function testFullRefundOnAlreadyCancelledReservationSyncsRefundItems(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status, mercadopago_payment_id)
            VALUES ('ovf-already-cancel', '1707', 'Already Cancelled', 'ac@example.com', '+573001112233', '2026-12-22', '2026-12-26', 1500000.00, 'cancelled', 'cancelled', '44332211');
        ");

        $paymentPayload = [
            'id' => '44332211',
            'status' => 'refunded',
            'status_detail' => 'refunded',
            'external_reference' => 'ovf-already-cancel',
            'transaction_amount' => 1500000.00,
            'transaction_amount_refunded' => 1500000.00,
            'refunds' => [
                [
                    'id' => 556677,
                    'amount' => 1500000.00,
                ],
            ],
        ];

        $res = $this->callWebhook('44332211', $paymentPayload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $stmt = $this->pdo->query("SELECT status, payment_status, refunded_amount FROM reservations WHERE reservation_uid = 'ovf-already-cancel'");
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame('cancelled', $row['status']);
        $this->assertSame('refunded', $row['payment_status']);
        $this->assertEquals(1500000.00, (float) $row['refunded_amount']);

        $stmt = $this->pdo->query("SELECT * FROM reservation_refunds WHERE reservation_uid = 'ovf-already-cancel'");
        $refundRow = $stmt->fetch();
        $this->assertIsArray($refundRow);
        $this->assertSame('556677', (string) $refundRow['mercadopago_refund_id']);
    }

    /**
     * Initial approval confirms pending_payment reservation.
     */
    public function testApprovedWebhookConfirmsPendingPaymentReservation(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status)
            VALUES ('ovf-pending-approve', '1606', 'Pending Guest', 'pending@example.com', '+573001112233', '2026-12-28', '2026-12-31', 750000.00, 'pending_payment', 'pending');
        ");

        $paymentPayload = [
            'id' => '33445566',
            'status' => 'approved',
            'status_detail' => 'accredited',
            'external_reference' => 'ovf-pending-approve',
            'transaction_amount' => 750000.00,
            'transaction_amount_refunded' => 0.0,
            'payment_method_id' => 'pse',
        ];

        $res = $this->callWebhook('33445566', $paymentPayload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $stmt = $this->pdo->query("SELECT status, payment_status, mercadopago_payment_id FROM reservations WHERE reservation_uid = 'ovf-pending-approve'");
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame('confirmed', $row['status']);
        $this->assertSame('approved', $row['payment_status']);
        $this->assertSame('33445566', (string) $row['mercadopago_payment_id']);
    }

    /**
     * Testing FUND, SECU, CALL, EXPI, FORM, OTHE scenarios (Rejected payments).
     * The processor should return unhandledStatus and keep the reservation pending.
     */
    public function testRejectedPaymentWebhookLeavesReservationPending(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status)
            VALUES ('ovf-pending-rejected', '1606', 'Rejected Guest', 'rejected@example.com', '+573001112233', '2026-12-28', '2026-12-31', 750000.00, 'pending_payment', 'pending');
        ");

        $paymentPayload = [
            'id' => '11223344',
            'status' => 'rejected',
            'status_detail' => 'cc_rejected_insufficient_amount', // FUND simulation
            'external_reference' => 'ovf-pending-rejected',
            'transaction_amount' => 750000.00,
            'transaction_amount_refunded' => 0.0,
            'payment_method_id' => 'mastercard',
        ];

        $res = $this->callWebhook('11223344', $paymentPayload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame('rejected', $res['json']['status'] ?? null);

        // Assert reservation was NOT modified
        $stmt = $this->pdo->query("SELECT status, payment_status, mercadopago_payment_id FROM reservations WHERE reservation_uid = 'ovf-pending-rejected'");
        $row = $stmt->fetch();
        $this->assertSame('pending_payment', $row['status']);
        $this->assertSame('pending', $row['payment_status']);
        $this->assertNull($row['mercadopago_payment_id']); // Not confirmed
    }

    /**
     * Testing CONT scenario (Pending / in_process payment).
     * The processor should return unhandledStatus and keep the reservation pending without side-effects.
     */
    public function testInProcessPaymentWebhookLeavesReservationPending(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status)
            VALUES ('ovf-pending-cont', '1606', 'CONT Guest', 'cont@example.com', '+573001112233', '2026-12-28', '2026-12-31', 750000.00, 'pending_payment', 'pending');
        ");

        $paymentPayload = [
            'id' => '55667788',
            'status' => 'in_process',
            'status_detail' => 'pending_contingency', // CONT simulation
            'external_reference' => 'ovf-pending-cont',
            'transaction_amount' => 750000.00,
            'transaction_amount_refunded' => 0.0,
            'payment_method_id' => 'visa',
        ];

        $res = $this->callWebhook('55667788', $paymentPayload);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame('in_process', $res['json']['status'] ?? null);

        // Assert reservation was NOT modified
        $stmt = $this->pdo->query("SELECT status, payment_status, mercadopago_payment_id FROM reservations WHERE reservation_uid = 'ovf-pending-cont'");
        $row = $stmt->fetch();
        $this->assertSame('pending_payment', $row['status']);
        $this->assertSame('pending', $row['payment_status']);
        $this->assertNull($row['mercadopago_payment_id']); // Not confirmed
    }

    /**
     * Valid x-signature and x-request-id headers pass HTTP boundary verification and settle the payment.
     */
    public function testWebhookHttpEndpointAcceptsValidSignatureAndConfirmsReservation(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status)
            VALUES ('ovf-sig-valid', '1606', 'Signed Guest', 'signed@example.com', '+573001112233', '2026-12-28', '2026-12-31', 750000.00, 'pending_payment', 'pending');
        ");

        $paymentId = '77889900';
        $secret = 'test_mp_webhook_secret_key_2026';
        $requestId = 'req-valid-12345';
        $ts = '1704908010';
        $manifest = "id:{$paymentId};request-id:{$requestId};ts:{$ts};";
        $v1 = hash_hmac('sha256', $manifest, $secret);

        $paymentPayload = [
            'id' => $paymentId,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'external_reference' => 'ovf-sig-valid',
            'transaction_amount' => 750000.00,
            'transaction_amount_refunded' => 0.0,
            'payment_method_id' => 'visa',
        ];

        $res = $this->callWebhookHttpEndpoint(
            body: ['type' => 'payment', 'data' => ['id' => $paymentId]],
            query: [],
            server: [
                'HTTP_X_SIGNATURE' => "ts={$ts},v1={$v1}",
                'HTTP_X_REQUEST_ID' => $requestId,
                'MERCADOPAGO_WEBHOOK_SECRET' => $secret,
            ],
            stagedPayment: $paymentPayload,
            injectTestPdo: true
        );

        $this->assertSame(200, $res['statusCode'], $res['stderr']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success'] ?? false);
        $this->assertSame('confirmed', $res['json']['status'] ?? null);

        $stmt = $this->pdo->query("SELECT status, payment_status, mercadopago_payment_id FROM reservations WHERE reservation_uid = 'ovf-sig-valid'");
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame('confirmed', $row['status']);
        $this->assertSame('approved', $row['payment_status']);
        $this->assertSame($paymentId, (string) $row['mercadopago_payment_id']);
    }

    /**
     * Missing x-signature or x-request-id headers are rejected with 403 Forbidden and error_log before DB connection.
     */
    public function testWebhookHttpEndpointRejectsMissingSignatureOrRequestIdHeadersWith403(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status)
            VALUES ('ovf-sig-missing', '1606', 'Missing Header Guest', 'missing@example.com', '+573001112233', '2026-12-28', '2026-12-31', 750000.00, 'pending_payment', 'pending');
        ");

        $paymentId = '77889901';
        $secret = 'test_mp_webhook_secret_key_2026';
        $requestId = 'req-missing-12345';
        $ts = '1704908010';
        $manifest = "id:{$paymentId};request-id:{$requestId};ts:{$ts};";
        $v1 = hash_hmac('sha256', $manifest, $secret);

        // 1. Missing x-signature header (with unreachable DB_HOST and no TEST_PDO to prove DB connection is never opened)
        $resMissingSig = $this->callWebhookHttpEndpoint(
            body: ['type' => 'payment', 'data' => ['id' => $paymentId]],
            query: [],
            server: [
                'HTTP_X_REQUEST_ID' => $requestId,
                'MERCADOPAGO_WEBHOOK_SECRET' => $secret,
                'DB_HOST' => 'unreachable.invalid.local',
            ],
            stagedPayment: null,
            injectTestPdo: false
        );

        $this->assertSame(403, $resMissingSig['statusCode']);
        $this->assertIsArray($resMissingSig['json']);
        $this->assertFalse($resMissingSig['json']['success'] ?? true);
        $this->assertStringContainsString('[MercadoPago Webhook Security]', $resMissingSig['stderr']);

        // 2. Missing x-request-id header
        $resMissingReqId = $this->callWebhookHttpEndpoint(
            body: ['type' => 'payment', 'data' => ['id' => $paymentId]],
            query: [],
            server: [
                'HTTP_X_SIGNATURE' => "ts={$ts},v1={$v1}",
                'MERCADOPAGO_WEBHOOK_SECRET' => $secret,
                'DB_HOST' => 'unreachable.invalid.local',
            ],
            stagedPayment: null,
            injectTestPdo: false
        );

        $this->assertSame(403, $resMissingReqId['statusCode']);
        $this->assertIsArray($resMissingReqId['json']);
        $this->assertFalse($resMissingReqId['json']['success'] ?? true);
        $this->assertStringContainsString('[MercadoPago Webhook Security]', $resMissingReqId['stderr']);

        // Verify no database side-effects occurred
        $stmt = $this->pdo->query("SELECT status, payment_status, mercadopago_payment_id FROM reservations WHERE reservation_uid = 'ovf-sig-missing'");
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame('pending_payment', $row['status']);
        $this->assertSame('pending', $row['payment_status']);
        $this->assertNull($row['mercadopago_payment_id']);
    }

    /**
     * Invalid x-signature (tampered payment ID, wrong hash, or altered timestamp) returns 403 Forbidden
     * and logs an error before opening any database connection.
     */
    public function testWebhookHttpEndpointRejectsInvalidSignatureWith403AndNoDatabaseSideEffects(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status)
            VALUES ('ovf-sig-tampered', '1606', 'Tampered Guest', 'tampered@example.com', '+573001112233', '2026-12-28', '2026-12-31', 750000.00, 'pending_payment', 'pending');
        ");

        $paymentId = '77889902';
        $secret = 'test_mp_webhook_secret_key_2026';
        $requestId = 'req-tampered-12345';
        $ts = '1704908010';
        $validManifest = "id:{$paymentId};request-id:{$requestId};ts:{$ts};";
        $validV1 = hash_hmac('sha256', $validManifest, $secret);

        // 1. Tampered payment ID in payload (signed for 77889902, payload sends 99999999)
        $resTamperedId = $this->callWebhookHttpEndpoint(
            body: ['type' => 'payment', 'data' => ['id' => '99999999']],
            query: [],
            server: [
                'HTTP_X_SIGNATURE' => "ts={$ts},v1={$validV1}",
                'HTTP_X_REQUEST_ID' => $requestId,
                'MERCADOPAGO_WEBHOOK_SECRET' => $secret,
                'DB_HOST' => 'unreachable.invalid.local',
            ],
            stagedPayment: null,
            injectTestPdo: false
        );

        $this->assertSame(403, $resTamperedId['statusCode']);
        $this->assertIsArray($resTamperedId['json']);
        $this->assertFalse($resTamperedId['json']['success'] ?? true);
        $this->assertStringContainsString('[MercadoPago Webhook Security]', $resTamperedId['stderr']);

        // 2. Wrong HMAC hash (v1 forged with wrong secret)
        $wrongV1 = hash_hmac('sha256', $validManifest, 'wrong_attacker_secret');
        $resWrongHash = $this->callWebhookHttpEndpoint(
            body: ['type' => 'payment', 'data' => ['id' => $paymentId]],
            query: [],
            server: [
                'HTTP_X_SIGNATURE' => "ts={$ts},v1={$wrongV1}",
                'HTTP_X_REQUEST_ID' => $requestId,
                'MERCADOPAGO_WEBHOOK_SECRET' => $secret,
                'DB_HOST' => 'unreachable.invalid.local',
            ],
            stagedPayment: null,
            injectTestPdo: false
        );

        $this->assertSame(403, $resWrongHash['statusCode']);
        $this->assertIsArray($resWrongHash['json']);
        $this->assertFalse($resWrongHash['json']['success'] ?? true);
        $this->assertStringContainsString('[MercadoPago Webhook Security]', $resWrongHash['stderr']);

        // 3. Altered timestamp in x-signature header
        $resAlteredTs = $this->callWebhookHttpEndpoint(
            body: ['type' => 'payment', 'data' => ['id' => $paymentId]],
            query: [],
            server: [
                'HTTP_X_SIGNATURE' => "ts=1704999999,v1={$validV1}",
                'HTTP_X_REQUEST_ID' => $requestId,
                'MERCADOPAGO_WEBHOOK_SECRET' => $secret,
                'DB_HOST' => 'unreachable.invalid.local',
            ],
            stagedPayment: null,
            injectTestPdo: false
        );

        $this->assertSame(403, $resAlteredTs['statusCode']);
        $this->assertIsArray($resAlteredTs['json']);
        $this->assertFalse($resAlteredTs['json']['success'] ?? true);
        $this->assertStringContainsString('[MercadoPago Webhook Security]', $resAlteredTs['stderr']);

        // Verify reservation in database was never touched
        $stmt = $this->pdo->query("SELECT status, payment_status, mercadopago_payment_id FROM reservations WHERE reservation_uid = 'ovf-sig-tampered'");
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame('pending_payment', $row['status']);
        $this->assertSame('pending', $row['payment_status']);
        $this->assertNull($row['mercadopago_payment_id']);
    }

    /**
     * Executes public/api/mercadopago-webhook.php via an isolated PHP subprocess to test the HTTP boundary.
     *
     * @param array<string, mixed> $body
     * @param array<string, string> $query
     * @param array<string, string> $server
     * @param array<string, mixed>|null $stagedPayment
     * @param bool $injectTestPdo
     * @return array{exitCode: int, statusCode: int, stdout: string, stderr: string, json: ?array<string, mixed>}
     */
    private function callWebhookHttpEndpoint(
        array $body,
        array $query = [],
        array $server = [],
        ?array $stagedPayment = null,
        bool $injectTestPdo = true
    ): array {
        $rateLimitFile = sys_get_temp_dir() . '/ovf_webhook_rate_limits.json';
        if (file_exists($rateLimitFile)) {
            @unlink($rateLimitFile);
        }

        $statusFile = sys_get_temp_dir() . '/ovf_webhook_status_' . uniqid('', true) . '.txt';
        $errorLogFile = sys_get_temp_dir() . '/ovf_webhook_errlog_' . uniqid('', true) . '.log';

        $defaultServer = [
            'REQUEST_METHOD' => 'POST',
            'REMOTE_ADDR' => '127.0.0.1',
        ];
        $mergedServer = array_merge($defaultServer, $server);

        $prependLines = [
            'ini_set("log_errors", "1");',
            'ini_set("error_log", ' . var_export($errorLogFile, true) . ');',
            'register_shutdown_function(static function (): void {',
            '    $code = http_response_code();',
            '    file_put_contents(' . var_export($statusFile, true) . ', (string) (is_int($code) ? $code : 200));',
            '});',
            'require_once ' . var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true) . ';',
        ];

        foreach ($mergedServer as $k => $v) {
            $prependLines[] = sprintf('$_SERVER[%s] = %s;', var_export($k, true), var_export($v, true));
            if (str_starts_with($k, 'MERCADOPAGO_') || str_starts_with($k, 'DB_')) {
                $prependLines[] = sprintf('$_ENV[%s] = %s;', var_export($k, true), var_export($v, true));
                $prependLines[] = sprintf('putenv(%s);', var_export($k . '=' . $v, true));
            }
        }

        $prependLines[] = sprintf('$_GET = %s;', var_export($query, true));

        if ($injectTestPdo) {
            $prependLines[] = sprintf(
                '$GLOBALS["TEST_PDO"] = new PDO(%s, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);',
                var_export('sqlite:' . $this->dbFile, true)
            );
        }

        if ($stagedPayment !== null) {
            $prependLines[] = '$gateway = new \OceanViewFlats\Domain\Payment\InMemoryPaymentGateway();';
            $prependLines[] = sprintf('$gateway->stagePaymentArray(%s);', var_export($stagedPayment, true));
            $prependLines[] = '$GLOBALS["TEST_PAYMENT_GATEWAY"] = $gateway;';
            $prependLines[] = '$GLOBALS["TEST_EMAIL_SENDER"] = new \OceanViewFlats\Domain\Fulfillment\InMemoryEmailSender();';
        }

        $prependLines[] = sprintf('require %s;', var_export(dirname(__DIR__, 3) . '/public/api/mercadopago-webhook.php', true));
        $phpCode = implode("\n", $prependLines);

        $process = proc_open(
            ['php', '-r', $phpCode],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        $jsonInput = json_encode($body);
        if ($jsonInput !== false) {
            fwrite($pipes[0], $jsonInput);
        }
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $statusCode = file_exists($statusFile) ? (int) trim((string) file_get_contents($statusFile)) : 200;
        $loggedErrors = file_exists($errorLogFile) ? (string) file_get_contents($errorLogFile) : '';
        @unlink($statusFile);
        @unlink($errorLogFile);

        return [
            'exitCode' => $exitCode,
            'statusCode' => $statusCode,
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr . $loggedErrors,
            'json' => json_decode((string) $stdout, true),
        ];
    }

    /**
     * Helper to invoke settlement processor in memory without subprocesses.
     *
     * @param string $paymentId
     * @param array<string, mixed> $paymentData
     * @return array{exitCode: int, stdout: string, stderr: string, json: ?array<string, mixed>}
     */
    private function callWebhook(string $paymentId, array $paymentData): array
    {
        $gateway = new InMemoryPaymentGateway();
        $gateway->stagePaymentArray($paymentData);

        $processor = WebhookSettlementProcessor::createDefault($this->pdo, [
            'gateway' => $gateway,
            'email_sender' => new InMemoryEmailSender(),
        ]);
        $result = $processor->settlePayment($paymentId);

        return [
            'exitCode' => $result->httpStatusCode < 500 ? 0 : 1,
            'stdout' => (string) json_encode($result->toArray()),
            'stderr' => '',
            'json' => $result->toArray(),
        ];
    }
}
