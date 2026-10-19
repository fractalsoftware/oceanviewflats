<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Payment;

use OceanViewFlats\Domain\Fulfillment\BookingFulfillmentInterface;
use OceanViewFlats\Domain\Fulfillment\CancellationEmailRenderer;
use OceanViewFlats\Domain\Fulfillment\FulfillmentResult;
use OceanViewFlats\Domain\Fulfillment\InMemoryEmailSender;
use OceanViewFlats\Domain\Payment\InMemoryPaymentGateway;
use OceanViewFlats\Domain\Payment\PaymentDetails;
use OceanViewFlats\Domain\Payment\PaymentGatewayException;
use OceanViewFlats\Domain\Payment\RefundLineItem;
use OceanViewFlats\Domain\Payment\WebhookSettlementProcessor;
use OceanViewFlats\Domain\Payment\WebhookSettlementResult;
use OceanViewFlats\Domain\Reservation\Reservation;
use PDO;
use PHPUnit\Framework\TestCase;

final class WebhookSettlementProcessorTest extends TestCase
{
    private PDO $pdo;
    private InMemoryPaymentGateway $gateway;
    private InMemoryEmailSender $emailSender;
    private CancellationEmailRenderer $cancellationRenderer;
    private SpyBookingFulfillment $fulfillmentSpy;
    private WebhookSettlementProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->createSchema();

        $this->gateway = new InMemoryPaymentGateway();
        $this->emailSender = new InMemoryEmailSender();
        $this->cancellationRenderer = new CancellationEmailRenderer('https://oceanviewflats.com');
        $this->fulfillmentSpy = new SpyBookingFulfillment();

        $this->processor = new WebhookSettlementProcessor(
            gateway: $this->gateway,
            pdo: $this->pdo,
            fulfillment: $this->fulfillmentSpy,
            cancellationRenderer: $this->cancellationRenderer,
            emailSender: $this->emailSender,
            publicSiteUrl: 'https://oceanviewflats.com',
            hostNotificationEmail: 'reservas@oceanviewflats.com'
        );
    }

    private function createSchema(): void
    {
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
    }

    /**
     * Rule 1: Resurrection Defense.
     * When an approval webhook arrives for a cancelled reservation, it must be ignored with HTTP 200,
     * maintaining terminal cancelled state and refusing resurrection.
     */
    public function testRule1ResurrectionDefenseRefusesApprovalOnCancelledReservation(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status, mercadopago_payment_id)
            VALUES ('ovf-resurrect-guard', '1606', 'Guarded Guest', 'guard@example.com', '+573001112233', '2026-11-20', '2026-11-25', 1500000.00, 'cancelled', 'cancelled', 'pay-111');
        ");

        $this->gateway->stagePayment(new PaymentDetails(
            paymentId: 'pay-111',
            status: 'approved',
            statusDetail: 'accredited',
            externalReference: 'ovf-resurrect-guard',
            transactionAmount: 1500000.00,
            totalRefundedAmount: 0.0,
            paymentMethodId: 'visa'
        ));

        $result = $this->processor->settlePayment('pay-111');

        $this->assertTrue($result->success);
        $this->assertSame(WebhookSettlementResult::STATUS_IGNORED_CANCELLED_TERMINAL, $result->status);
        $this->assertSame(200, $result->httpStatusCode);
        $this->assertSame('ovf-resurrect-guard', $result->reservationUid);

        // Verify DB reservation remained cancelled
        $stmt = $this->pdo->query("SELECT status, payment_status FROM reservations WHERE reservation_uid = 'ovf-resurrect-guard'");
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame('cancelled', $row['status']);
        $this->assertSame('cancelled', $row['payment_status']);

        // Verify fulfillment was not triggered
        $this->assertSame(0, $this->fulfillmentSpy->calls);
    }

    /**
     * Rule 2: Full Refund Synchronization.
     * When a full refund webhook arrives for an active reservation, it auto-cancels the stay,
     * updates refunded amount, appends sync notes, synchronizes refund line items, logs audit events,
     * and dispatches a localized cancellation email to the primary guest.
     */
    public function testRule2FullRefundAutoCancelsConfirmedReservationSyncsRefundRowsAuditLogsAndSendsEmail(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status, mercadopago_payment_id, lang)
            VALUES ('ovf-ext-full-refund', '1707', 'Maria Host', 'maria@example.com', '+573009998877', '2026-12-01', '2026-12-05', 1200000.00, 'confirmed', 'approved', 'pay-222', 'es');
        ");

        $this->gateway->stagePayment(new PaymentDetails(
            paymentId: 'pay-222',
            status: 'refunded',
            statusDetail: 'refunded',
            externalReference: 'ovf-ext-full-refund',
            transactionAmount: 1200000.00,
            totalRefundedAmount: 1200000.00,
            paymentMethodId: 'pse',
            refunds: [
                new RefundLineItem(
                    refundId: 'ref-881122',
                    paymentId: 'pay-222',
                    amount: 1200000.00,
                    status: 'approved',
                    createdAt: '2026-09-29T12:00:00Z'
                ),
            ]
        ));

        $result = $this->processor->settlePayment('pay-222');

        $this->assertTrue($result->success);
        $this->assertSame(WebhookSettlementResult::STATUS_REFUND_CANCELLED, $result->status);
        $this->assertSame(200, $result->httpStatusCode);
        $this->assertSame('ovf-ext-full-refund', $result->reservationUid);

        // Verify DB reservation updated to cancelled
        $stmt = $this->pdo->query("SELECT status, payment_status, refunded_amount, notes FROM reservations WHERE reservation_uid = 'ovf-ext-full-refund'");
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame('cancelled', $row['status']);
        $this->assertSame('refunded', $row['payment_status']);
        $this->assertEquals(1200000.00, (float) $row['refunded_amount']);
        $this->assertStringContainsString('External Refund Sync', (string) $row['notes']);

        // Verify reservation_refunds row created
        $stmt = $this->pdo->query("SELECT * FROM reservation_refunds WHERE reservation_uid = 'ovf-ext-full-refund'");
        $refundRow = $stmt->fetch();
        $this->assertIsArray($refundRow);
        $this->assertSame('ref-881122', $refundRow['mercadopago_refund_id']);
        $this->assertEquals(1200000.00, (float) $refundRow['amount']);

        // Verify admin_audit_logs created
        $stmt = $this->pdo->query("SELECT action, payload_after FROM admin_audit_logs WHERE entity_id = 'ovf-ext-full-refund' ORDER BY id ASC");
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $actions = array_column($logs, 'action');

        $this->assertContains('refund_cancellation', $actions);
        $this->assertContains('cancellation_email_sent', $actions);

        // Verify guest cancellation email was dispatched
        $sentMessages = $this->emailSender->getSentMessages();
        $this->assertCount(1, $sentMessages);
        $this->assertSame('maria@example.com', $sentMessages[0]['to']);
        $this->assertStringContainsString('Reserva Cancelada', $sentMessages[0]['subject']);
        $this->assertStringContainsString('ovf-ext-full-refund', $sentMessages[0]['htmlBody']);
    }

    /**
     * Rule 2 (Fallback): Full refund without itemized refund array creates single reconciliation row.
     */
    public function testRule2FullRefundWithoutItemizedArrayCreatesFallbackRefundRow(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status, mercadopago_payment_id)
            VALUES ('ovf-no-item-full', '1707', 'Fallback Guest', 'fb@example.com', '+573009998877', '2026-12-01', '2026-12-05', 800000.00, 'confirmed', 'approved', 'pay-223');
        ");

        $this->gateway->stagePayment(new PaymentDetails(
            paymentId: 'pay-223',
            status: 'refunded',
            statusDetail: 'refunded',
            externalReference: 'ovf-no-item-full',
            transactionAmount: 800000.00,
            totalRefundedAmount: 800000.00,
            paymentMethodId: 'card',
            refunds: []
        ));

        $result = $this->processor->settlePayment('pay-223');

        $this->assertTrue($result->success);
        $this->assertSame(WebhookSettlementResult::STATUS_REFUND_CANCELLED, $result->status);

        $stmt = $this->pdo->query("SELECT * FROM reservation_refunds WHERE reservation_uid = 'ovf-no-item-full'");
        $refundRow = $stmt->fetch();
        $this->assertIsArray($refundRow);
        $this->assertNull($refundRow['mercadopago_refund_id']);
        $this->assertEquals(800000.00, (float) $refundRow['amount']);
    }

    /**
     * Rule 2 (Terminal Re-entrant): Full refund on already cancelled reservation syncs refund items without sending duplicate emails.
     */
    public function testRule2FullRefundOnAlreadyCancelledReservationSyncsRefundRowsWithoutDuplicateCancellation(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status, mercadopago_payment_id)
            VALUES ('ovf-already-cancelled', '1707', 'Already Cancelled', 'ac@example.com', '+573001112233', '2026-12-22', '2026-12-26', 1500000.00, 'cancelled', 'cancelled', 'pay-224');
        ");

        $this->gateway->stagePayment(new PaymentDetails(
            paymentId: 'pay-224',
            status: 'refunded',
            statusDetail: 'refunded',
            externalReference: 'ovf-already-cancelled',
            transactionAmount: 1500000.00,
            totalRefundedAmount: 1500000.00,
            paymentMethodId: 'visa',
            refunds: [
                new RefundLineItem(
                    refundId: 'ref-556677',
                    paymentId: 'pay-224',
                    amount: 1500000.00,
                    status: 'approved',
                    createdAt: '2026-09-29T12:00:00Z'
                ),
            ]
        ));

        $result = $this->processor->settlePayment('pay-224');

        $this->assertTrue($result->success);
        $this->assertSame(WebhookSettlementResult::STATUS_REFUND_CANCELLED, $result->status);

        // Verify DB reservation remained cancelled with refunded payment status
        $stmt = $this->pdo->query("SELECT status, payment_status, refunded_amount FROM reservations WHERE reservation_uid = 'ovf-already-cancelled'");
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame('cancelled', $row['status']);
        $this->assertSame('refunded', $row['payment_status']);
        $this->assertEquals(1500000.00, (float) $row['refunded_amount']);

        // Verify refund row was inserted
        $stmt = $this->pdo->query("SELECT * FROM reservation_refunds WHERE reservation_uid = 'ovf-already-cancelled'");
        $refundRow = $stmt->fetch();
        $this->assertIsArray($refundRow);
        $this->assertSame('ref-556677', $refundRow['mercadopago_refund_id']);

        // No new emails sent
        $this->assertCount(0, $this->emailSender->getSentMessages());
    }

    /**
     * Rule 3: Partial Refund Synchronization.
     * When a partial refund arrives for a confirmed reservation, status stays confirmed,
     * refunded_amount is updated, line items are synchronized, and audit log is recorded.
     */
    public function testRule3PartialRefundUpdatesBalanceRecordsLineItemAndKeepsBookingConfirmed(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status, mercadopago_payment_id)
            VALUES ('ovf-partial-sync', '1606', 'Partial Guest', 'part@example.com', '+573001112233', '2026-12-10', '2026-12-15', 2000000.00, 'confirmed', 'approved', 'pay-333');
        ");

        $this->gateway->stagePayment(new PaymentDetails(
            paymentId: 'pay-333',
            status: 'approved',
            statusDetail: 'partially_refunded',
            externalReference: 'ovf-partial-sync',
            transactionAmount: 2000000.00,
            totalRefundedAmount: 500000.00,
            paymentMethodId: 'visa',
            refunds: [
                new RefundLineItem(
                    refundId: 'ref-332211',
                    paymentId: 'pay-333',
                    amount: 500000.00,
                    status: 'approved',
                    createdAt: '2026-09-29T14:00:00Z'
                ),
            ]
        ));

        $result = $this->processor->settlePayment('pay-333');

        $this->assertTrue($result->success);
        $this->assertSame(WebhookSettlementResult::STATUS_PARTIAL_REFUND_SYNCED, $result->status);
        $this->assertSame(200, $result->httpStatusCode);

        // Verify reservation remained confirmed
        $stmt = $this->pdo->query("SELECT status, payment_status, refunded_amount FROM reservations WHERE reservation_uid = 'ovf-partial-sync'");
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame('confirmed', $row['status']);
        $this->assertSame('partially_refunded', $row['payment_status']);
        $this->assertEquals(500000.00, (float) $row['refunded_amount']);

        // Verify refund row recorded
        $stmt = $this->pdo->query("SELECT * FROM reservation_refunds WHERE reservation_uid = 'ovf-partial-sync'");
        $refundRow = $stmt->fetch();
        $this->assertIsArray($refundRow);
        $this->assertSame('ref-332211', $refundRow['mercadopago_refund_id']);
        $this->assertEquals(500000.00, (float) $refundRow['amount']);

        // Verify audit log recorded
        $stmt = $this->pdo->query("SELECT action FROM admin_audit_logs WHERE entity_id = 'ovf-partial-sync'");
        $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('external_partial_refund', $actions);

        // Fulfillment was not re-triggered
        $this->assertSame(0, $this->fulfillmentSpy->calls);
    }

    /**
     * Rule 3 (Cancelled Retain): Partial refund on an already cancelled reservation does NOT resurrect it.
     */
    public function testRule3PartialRefundOnCancelledReservationDoesNotResurrect(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status, mercadopago_payment_id)
            VALUES ('ovf-cancel-partial', '1606', 'Cancel Part Guest', 'cpart@example.com', '+573001112233', '2026-12-16', '2026-12-20', 1000000.00, 'cancelled', 'cancelled', 'pay-334');
        ");

        $this->gateway->stagePayment(new PaymentDetails(
            paymentId: 'pay-334',
            status: 'partially_refunded',
            statusDetail: 'partially_refunded',
            externalReference: 'ovf-cancel-partial',
            transactionAmount: 1000000.00,
            totalRefundedAmount: 400000.00,
            paymentMethodId: 'card'
        ));

        $result = $this->processor->settlePayment('pay-334');

        $this->assertTrue($result->success);
        $this->assertSame(WebhookSettlementResult::STATUS_PARTIAL_REFUND_SYNCED, $result->status);

        $stmt = $this->pdo->query("SELECT status, payment_status, refunded_amount FROM reservations WHERE reservation_uid = 'ovf-cancel-partial'");
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame('cancelled', $row['status']);
        $this->assertSame('partially_refunded', $row['payment_status']);
        $this->assertEquals(400000.00, (float) $row['refunded_amount']);
    }

    /**
     * Rule 4: Idempotency Replay Guard.
     * When an approval webhook arrives for a booking that is already confirmed,
     * acknowledge safely without re-triggering fulfillment or creating duplicate mutations.
     */
    public function testRule4IdempotencyReplayGuardGracefullyAcknowledgesAlreadyConfirmedBooking(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status, mercadopago_payment_id)
            VALUES ('ovf-already-conf', '1707', 'Confirmed Guest', 'conf@example.com', '+573001112233', '2026-12-01', '2026-12-05', 900000.00, 'confirmed', 'approved', 'pay-444');
        ");

        $this->gateway->stagePayment(new PaymentDetails(
            paymentId: 'pay-444',
            status: 'approved',
            statusDetail: 'accredited',
            externalReference: 'ovf-already-conf',
            transactionAmount: 900000.00,
            totalRefundedAmount: 0.0,
            paymentMethodId: 'visa'
        ));

        $result = $this->processor->settlePayment('pay-444');

        $this->assertTrue($result->success);
        $this->assertSame(WebhookSettlementResult::STATUS_ALREADY_CONFIRMED, $result->status);
        $this->assertSame(200, $result->httpStatusCode);
        $this->assertSame('ovf-already-conf', $result->reservationUid);

        // Fulfillment was not invoked
        $this->assertSame(0, $this->fulfillmentSpy->calls);

        // No new audit logs were created
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM admin_audit_logs WHERE entity_id = 'ovf-already-conf'");
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    /**
     * Rule 5: Approved Confirmation and Fulfillment.
     * When an approval webhook arrives for a pending reservation, transition status to confirmed,
     * record mercadopago_payment_id and audit trail, and invoke fulfillment service
     * with door code strictly withheld per ADR 0001.
     */
    public function testRule5ApprovedPaymentConfirmsPendingReservationRecordsAuditAndRunsFulfillmentWithholdingDoorCode(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status, door_code)
            VALUES ('ovf-pending-approve', '1606', 'Pending Guest', 'pending@example.com', '+573001112233', '2026-12-28', '2026-12-31', 750000.00, 'pending_payment', 'pending', '9999');
        ");

        $this->gateway->stagePayment(new PaymentDetails(
            paymentId: 'pay-555',
            status: 'approved',
            statusDetail: 'accredited',
            externalReference: 'ovf-pending-approve',
            transactionAmount: 750000.00,
            totalRefundedAmount: 0.0,
            paymentMethodId: 'pse'
        ));

        $result = $this->processor->settlePayment('pay-555');

        $this->assertTrue($result->success);
        $this->assertSame(WebhookSettlementResult::STATUS_CONFIRMED, $result->status);
        $this->assertSame(200, $result->httpStatusCode);
        $this->assertSame('ovf-pending-approve', $result->reservationUid);

        // Verify DB reservation updated to confirmed
        $stmt = $this->pdo->query("SELECT status, payment_status, mercadopago_payment_id, payment_method_id FROM reservations WHERE reservation_uid = 'ovf-pending-approve'");
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame('confirmed', $row['status']);
        $this->assertSame('approved', $row['payment_status']);
        $this->assertSame('pay-555', $row['mercadopago_payment_id']);
        $this->assertSame('pse', $row['payment_method_id']);

        // Verify audit log
        $stmt = $this->pdo->query("SELECT action FROM admin_audit_logs WHERE entity_id = 'ovf-pending-approve'");
        $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('payment_approved_webhook', $actions);

        // Verify fulfillment was invoked
        $this->assertSame(1, $this->fulfillmentSpy->calls);
        $lastReservation = $this->fulfillmentSpy->lastReservation;
        $this->assertNotNull($lastReservation);
        $this->assertSame('ovf-pending-approve', $lastReservation->reservationUid);
        // ADR 0001: Door code MUST be withheld during payment confirmation fulfillment
        $this->assertNull($lastReservation->doorCode);
    }

    /**
     * Gateway error: Upstream failure returns 502 with gateway_error status.
     */
    public function testGatewayExceptionReturnsGatewayErrorResult(): void
    {
        $this->gateway->stageException(new PaymentGatewayException('Upstream cURL timeout', 504, 'network_timeout'));

        $result = $this->processor->settlePayment('pay-timeout');

        $this->assertFalse($result->success);
        $this->assertSame(WebhookSettlementResult::STATUS_GATEWAY_ERROR, $result->status);
        $this->assertSame(504, $result->httpStatusCode);
        $this->assertSame('Upstream cURL timeout', $result->message);
        $this->assertSame('network_timeout', $result->data['error_type'] ?? null);
    }

    /**
     * Input validation: Empty payment ID returns 400 gateway_error.
     */
    public function testEmptyPaymentIdReturnsGatewayError400(): void
    {
        $result = $this->processor->settlePayment('   ');

        $this->assertFalse($result->success);
        $this->assertSame(WebhookSettlementResult::STATUS_GATEWAY_ERROR, $result->status);
        $this->assertSame(400, $result->httpStatusCode);
    }

    /**
     * Non-existent reservation returns unknown_reservation with HTTP 200 (to prevent infinite webhook retry storms).
     */
    public function testUnknownReservationReturnsUnknownReservationResult200(): void
    {
        $this->gateway->stagePayment(new PaymentDetails(
            paymentId: 'pay-unknown',
            status: 'approved',
            statusDetail: 'accredited',
            externalReference: 'ovf-does-not-exist',
            transactionAmount: 500000.00,
            totalRefundedAmount: 0.0,
            paymentMethodId: 'visa'
        ));

        $result = $this->processor->settlePayment('pay-unknown');

        $this->assertFalse($result->success);
        $this->assertSame(WebhookSettlementResult::STATUS_UNKNOWN_RESERVATION, $result->status);
        $this->assertSame(200, $result->httpStatusCode);
        $this->assertSame('ovf-does-not-exist', $result->reservationUid);
    }

    /**
     * Payment without external_reference returns unknown_reservation with HTTP 200.
     */
    public function testMissingExternalReferenceReturnsUnknownReservationResult200(): void
    {
        $this->gateway->stagePayment(new PaymentDetails(
            paymentId: 'pay-no-ref',
            status: 'approved',
            statusDetail: 'accredited',
            externalReference: '',
            transactionAmount: 500000.00,
            totalRefundedAmount: 0.0,
            paymentMethodId: 'visa'
        ));

        $result = $this->processor->settlePayment('pay-no-ref');

        $this->assertFalse($result->success);
        $this->assertSame(WebhookSettlementResult::STATUS_UNKNOWN_RESERVATION, $result->status);
        $this->assertSame(200, $result->httpStatusCode);
        $this->assertNull($result->reservationUid);
    }

    /**
     * Unhandled payment status (e.g. in_process, pending) returns safe 200 without changing reservation.
     */
    public function testUnhandledPaymentStatusReturnsSafe200(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status)
            VALUES ('ovf-in-proc', '1606', 'Pending Guest', 'pending@example.com', '+573001112233', '2026-12-28', '2026-12-31', 750000.00, 'pending_payment', 'pending');
        ");

        $this->gateway->stagePayment(new PaymentDetails(
            paymentId: 'pay-in-proc',
            status: 'in_process',
            statusDetail: 'pending_review',
            externalReference: 'ovf-in-proc',
            transactionAmount: 750000.00,
            totalRefundedAmount: 0.0,
            paymentMethodId: 'pse'
        ));

        $result = $this->processor->settlePayment('pay-in-proc');

        $this->assertTrue($result->success);
        $this->assertSame('in_process', $result->status);
        $this->assertSame(200, $result->httpStatusCode);

        // Reservation was untouched
        $stmt = $this->pdo->query("SELECT status FROM reservations WHERE reservation_uid = 'ovf-in-proc'");
        $this->assertSame('pending_payment', $stmt->fetchColumn());
    }

    /**
     * Factory createDefault instantiates processor correctly and honors options.
     */
    public function testCreateDefaultFactoryProducesConfiguredInstance(): void
    {
        $processor = WebhookSettlementProcessor::createDefault($this->pdo, [
            'public_site_url' => 'https://test.oceanviewflats.com',
            'host_notification_email' => 'custom-host@oceanviewflats.com',
            'gateway' => $this->gateway,
        ]);

        $this->assertInstanceOf(WebhookSettlementProcessor::class, $processor);
        $this->assertSame('https://test.oceanviewflats.com', $processor->getPublicSiteUrl());
        $this->assertSame('custom-host@oceanviewflats.com', $processor->getHostNotificationEmail());
    }

    /**
     * Database error during transaction rolls back transaction and returns database_error with HTTP 500.
     */
    public function testDatabaseRollbackOnTransactionFailure(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, payment_status)
            VALUES ('ovf-db-fail', '1606', 'Fail Guest', 'fail@example.com', '+573001112233', '2026-12-28', '2026-12-31', 500000.00, 'pending_payment', 'pending');
        ");

        $this->gateway->stagePayment(new PaymentDetails(
            paymentId: 'pay-db-fail',
            status: 'approved',
            statusDetail: 'accredited',
            externalReference: 'ovf-db-fail',
            transactionAmount: 500000.00,
            totalRefundedAmount: 0.0,
            paymentMethodId: 'pse'
        ));

        // Create trigger that forces an abort during UPDATE
        $this->pdo->exec("
            CREATE TRIGGER abort_update BEFORE UPDATE ON reservations
            BEGIN
                SELECT RAISE(ABORT, 'Simulated fatal DB update failure');
            END;
        ");

        $result = $this->processor->settlePayment('pay-db-fail');

        $this->assertFalse($result->success);
        $this->assertSame(WebhookSettlementResult::STATUS_DATABASE_ERROR, $result->status);
        $this->assertSame(500, $result->httpStatusCode);
        $this->assertStringContainsString('Simulated fatal DB update failure', (string) $result->message);
        $this->assertFalse($this->pdo->inTransaction());

        // Verify fulfillment was not called
        $this->assertSame(0, $this->fulfillmentSpy->calls);
    }

    /**
     * WebhookSettlementResult serializes to array and JSON correctly.
     */
    public function testWebhookSettlementResultSerialization(): void
    {
        $result = WebhookSettlementResult::confirmed('ovf-12345', ['key' => 'val'], 'Confirmed test');

        $this->assertTrue($result->isSuccess());
        $array = $result->toArray();
        $this->assertTrue($array['success']);
        $this->assertSame('confirmed', $array['status']);
        $this->assertSame(200, $array['http_status_code']);
        $this->assertSame('ovf-12345', $array['reservation_uid']);
        $this->assertSame('Confirmed test', $array['message']);
        $this->assertSame(['key' => 'val'], $array['data']);

        $json = json_encode($result);
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertSame($array, $decoded);
    }
}

/**
 * Lightweight test spy for BookingFulfillmentInterface.
 */
final class SpyBookingFulfillment implements BookingFulfillmentInterface
{
    public int $calls = 0;
    public ?Reservation $lastReservation = null;
    /** @var array<string, mixed> */
    public array $lastExtra = [];

    /**
     * @param array<string, mixed> $extra
     */
    public function fulfillConfirmation(Reservation $reservation, array $extra = []): FulfillmentResult
    {
        $this->calls++;
        $this->lastReservation = $reservation;
        $this->lastExtra = $extra;

        return new FulfillmentResult(
            isGuestEmailSent: true,
            isHostEmailSent: true,
            errors: []
        );
    }
}
