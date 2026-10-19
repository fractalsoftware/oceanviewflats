<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Payment;

use DateTimeImmutable;
use OceanViewFlats\Domain\Fulfillment\BookingFulfillment;
use OceanViewFlats\Domain\Fulfillment\ConfirmationEmailRenderer;
use OceanViewFlats\Domain\Fulfillment\InMemoryEmailSender;
use OceanViewFlats\Domain\Payment\BookingPaymentProcessor;
use OceanViewFlats\Domain\Payment\BookingPaymentRequest;
use OceanViewFlats\Domain\Payment\BookingPaymentResult;
use OceanViewFlats\Domain\Payment\InMemoryPaymentGateway;
use OceanViewFlats\Domain\Payment\PaymentGatewayException;
use OceanViewFlats\Domain\Payment\PaymentGatewayResult;
use OceanViewFlats\Domain\Quote\InMemoryRateSource;
use OceanViewFlats\Domain\Quote\PropertyRatesConfig;
use OceanViewFlats\Domain\Quote\QuoteEngine;
use OceanViewFlats\Domain\Quote\RateTier;
use OceanViewFlats\Domain\Reservation\ChannelBlock;
use OceanViewFlats\Domain\Reservation\InMemoryChannelBlockSource;
use OceanViewFlats\Domain\Reservation\InMemoryMaintenanceBlockSource;
use OceanViewFlats\Domain\Reservation\MaintenanceBlock;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationLedger;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use PDO;
use PHPUnit\Framework\TestCase;

final class BookingPaymentProcessorTest extends TestCase
{
    private PDO $pdo;
    private InMemoryPaymentGateway $gateway;
    private PdoReservationRepository $repository;
    private InMemoryChannelBlockSource $channelBlockSource;
    private InMemoryMaintenanceBlockSource $maintenanceBlockSource;
    private ReservationLedger $ledger;
    private InMemoryRateSource $rateSource;
    private QuoteEngine $quoteEngine;
    private InMemoryEmailSender $emailSender;
    private BookingFulfillment $fulfillment;
    private BookingPaymentProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. In-memory SQLite PDO
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec("CREATE TABLE reservations (
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
            lang TEXT,
            registry_completed INTEGER DEFAULT 0,
            registry_completed_at TEXT,
            door_code TEXT,
            source TEXT DEFAULT 'web',
            external_confirmation_code TEXT DEFAULT NULL,
            channel_block_uid TEXT DEFAULT NULL,
            notes TEXT,
            refunded_amount REAL DEFAULT 0.0,
            created_at TEXT,
            updated_at TEXT
        )");

        $this->pdo->exec("CREATE TABLE payment_idempotency (
            idempotency_key TEXT PRIMARY KEY,
            payment_id TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE admin_audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_user_id INTEGER,
            action TEXT,
            entity_type TEXT,
            entity_id TEXT,
            payload_before TEXT,
            payload_after TEXT,
            ip_address TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");

        // 2. Dependencies
        $this->gateway = new InMemoryPaymentGateway();
        $this->repository = new PdoReservationRepository($this->pdo);
        $this->channelBlockSource = new InMemoryChannelBlockSource();
        $this->maintenanceBlockSource = new InMemoryMaintenanceBlockSource();
        $this->ledger = new ReservationLedger(
            repository: $this->repository,
            channelBlockSource: $this->channelBlockSource,
            maintenanceBlockSource: $this->maintenanceBlockSource
        );

        $this->rateSource = new InMemoryRateSource([
            new RateTier(
                propertyId: '1606',
                startDate: '2026-01-01',
                endDate: '2026-12-14',
                nightlyRateCop: 350000.0,
                minimumStay: 2
            ),
            new RateTier(
                propertyId: '1606',
                startDate: '2026-12-15',
                endDate: '2027-01-15',
                nightlyRateCop: 550000.0,
                minimumStay: 4
            ),
            new RateTier(
                propertyId: '1707',
                startDate: '2026-01-01',
                endDate: '2026-12-31',
                nightlyRateCop: 450000.0,
                minimumStay: 2
            ),
        ]);
        $ratesConfig = PropertyRatesConfig::createDefault();
        $this->quoteEngine = new QuoteEngine($this->rateSource, $ratesConfig);

        $this->emailSender = new InMemoryEmailSender();
        $renderer = new ConfirmationEmailRenderer('https://oceanviewflats.com');
        $this->fulfillment = new BookingFulfillment(
            emailSender: $this->emailSender,
            renderer: $renderer,
            hostEmail: 'reservas@oceanviewflats.com'
        );

        $this->processor = new BookingPaymentProcessor(
            gateway: $this->gateway,
            pdo: $this->pdo,
            ledger: $this->ledger,
            quoteEngine: $this->quoteEngine,
            repository: $this->repository,
            fulfillment: $this->fulfillment,
            emailSender: $this->emailSender,
            publicSiteUrl: 'https://oceanviewflats.com',
            hostNotificationEmail: 'reservas@oceanviewflats.com'
        );
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
            'idempotency_key' => 'idem_' . bin2hex(random_bytes(4)),
        ], $overrides);

        return BookingPaymentRequest::fromArray($data);
    }

    public function testInvalidPropertyReturnsError(): void
    {
        $request = $this->createValidRequest(['property_id' => '9999']);
        $result = $this->processor->processBookingPayment($request);

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->httpStatusCode);
        $this->assertSame('error', $result->status);
        $this->assertStringContainsString('property', strtolower($result->message));
    }

    public function testPastCheckInDateReturnsError(): void
    {
        $request = $this->createValidRequest([
            'check_in' => '2020-01-01',
            'check_out' => '2020-01-05',
        ]);
        $result = $this->processor->processBookingPayment($request);

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->httpStatusCode);
        $this->assertStringContainsString('past', strtolower($result->message));
    }

    public function testCheckOutBeforeCheckInReturnsError(): void
    {
        $request = $this->createValidRequest([
            'check_in' => '2026-11-15',
            'check_out' => '2026-11-10',
        ]);
        $result = $this->processor->processBookingPayment($request);

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->httpStatusCode);
    }

    public function testShortGuestNameReturnsError(): void
    {
        $request = $this->createValidRequest(['guest_name' => 'Jo']);
        $result = $this->processor->processBookingPayment($request);

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->httpStatusCode);
        $this->assertStringContainsString('name', strtolower($result->message));
    }

    public function testInvalidGuestEmailReturnsError(): void
    {
        $request = $this->createValidRequest(['guest_email' => 'invalid-email-address']);
        $result = $this->processor->processBookingPayment($request);

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->httpStatusCode);
        $this->assertStringContainsString('email', strtolower($result->message));
    }

    public function testShortGuestPhoneReturnsError(): void
    {
        $request = $this->createValidRequest(['guest_phone' => '123']);
        $result = $this->processor->processBookingPayment($request);

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->httpStatusCode);
        $this->assertStringContainsString('phone', strtolower($result->message));
    }

    public function testMissingPaymentMethodReturnsError(): void
    {
        $request = $this->createValidRequest(['payment_method_id' => '']);
        $result = $this->processor->processBookingPayment($request);

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->httpStatusCode);
        $this->assertStringContainsString('payment method', strtolower($result->message));
    }

    public function testChannelBlockConflictReturns409(): void
    {
        $this->channelBlockSource->addBlock(new ChannelBlock(
            propertyId: '1606',
            startDate: '2026-11-12',
            endDate: '2026-11-16',
            summary: 'Airbnb Reservation'
        ));

        $request = $this->createValidRequest([
            'check_in' => '2026-11-10',
            'check_out' => '2026-11-14',
        ]);
        $result = $this->processor->processBookingPayment($request);

        $this->assertFalse($result->success);
        $this->assertSame(409, $result->httpStatusCode);
        $this->assertStringContainsString('Airbnb booking', $result->message);
    }

    public function testMaintenanceBlockConflictReturns409(): void
    {
        $this->maintenanceBlockSource->addBlock(new MaintenanceBlock(
            id: 1,
            propertyId: '1606',
            startDate: '2026-11-11',
            endDate: '2026-11-13',
            reason: 'Painting balcony'
        ));

        $request = $this->createValidRequest([
            'check_in' => '2026-11-10',
            'check_out' => '2026-11-14',
        ]);
        $result = $this->processor->processBookingPayment($request);

        $this->assertFalse($result->success);
        $this->assertSame(409, $result->httpStatusCode);
        $this->assertStringContainsString('maintenance', strtolower($result->message));
    }

    public function testReservationHoldConflictReturns409(): void
    {
        $this->repository->save(new Reservation(
            reservationUid: 'ovf_existing_hold',
            propertyId: '1606',
            guestName: 'Prior Guest',
            guestEmail: 'prior@example.com',
            guestPhone: '+57 300 111 2233',
            checkIn: '2026-11-12',
            checkOut: '2026-11-15',
            totalPrice: 1500000.0,
            status: ReservationStatus::CONFIRMED,
            paymentMethodId: 'card',
            createdAt: new DateTimeImmutable()
        ));

        $request = $this->createValidRequest([
            'check_in' => '2026-11-10',
            'check_out' => '2026-11-14',
        ]);
        $result = $this->processor->processBookingPayment($request);

        $this->assertFalse($result->success);
        $this->assertSame(409, $result->httpStatusCode);
        $this->assertStringContainsString('already locked', $result->message);
    }

    public function testMinimumStayViolationReturns422(): void
    {
        // High season tier (2026-12-15 to 2027-01-15) requires minimum stay of 4 nights
        $request = $this->createValidRequest([
            'check_in' => '2026-12-20',
            'check_out' => '2026-12-22', // only 2 nights
        ]);
        $result = $this->processor->processBookingPayment($request);

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->httpStatusCode);
        $this->assertStringContainsString('minimum stay', strtolower($result->message));
    }

    public function testIdempotencyReplayReturnsConfirmedWithoutRecharging(): void
    {
        $idempotencyKey = 'idem_double_click_test';
        $this->pdo->exec("INSERT INTO payment_idempotency (idempotency_key, payment_id) VALUES ('{$idempotencyKey}', 'pay_already_charged_789')");

        $request = $this->createValidRequest([
            'idempotency_key' => $idempotencyKey,
        ]);

        $result = $this->processor->processBookingPayment($request);

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->httpStatusCode);
        $this->assertSame('Payment already processed.', $result->message);
        $this->assertSame('pay_already_charged_789', $result->extra['payment_id'] ?? null);
        $this->assertEmpty($this->gateway->getRecordedIntents());
    }

    public function testIdempotencyReplayRespectsConfirmedReservationStatus(): void
    {
        $idempotencyKey = 'idem_confirmed_test';
        $paymentId = 'pay_confirmed_123';
        $this->pdo->exec("INSERT INTO payment_idempotency (idempotency_key, payment_id) VALUES ('{$idempotencyKey}', '{$paymentId}')");
        $this->pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, mercadopago_payment_id)
            VALUES ('ovf_conf_1', '1606', 'John Doe', 'john@example.com', '+573001234567', '2026-11-10', '2026-11-14', 1610000, 'confirmed', '{$paymentId}')");

        $request = $this->createValidRequest([
            'idempotency_key' => $idempotencyKey,
        ]);

        $result = $this->processor->processBookingPayment($request);

        $this->assertTrue($result->success);
        $this->assertSame('confirmed', $result->status);
        $this->assertSame('Payment already processed.', $result->message);
        $this->assertSame($paymentId, $result->extra['payment_id'] ?? null);
        $this->assertSame('ovf_conf_1', $result->extra['reservation_code'] ?? null);
        $this->assertSame('confirmed', $result->extra['status'] ?? null);
        $this->assertEmpty($this->gateway->getRecordedIntents());
    }

    public function testIdempotencyReplayRespectsPendingPaymentReservationStatus(): void
    {
        $idempotencyKey = 'idem_pending_test';
        $paymentId = 'pay_pending_456';
        $this->pdo->exec("INSERT INTO payment_idempotency (idempotency_key, payment_id) VALUES ('{$idempotencyKey}', '{$paymentId}')");
        $this->pdo->exec("INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, mercadopago_payment_id)
            VALUES ('ovf_pend_1', '1606', 'John Doe', 'john@example.com', '+573001234567', '2026-11-10', '2026-11-14', 1610000, 'pending_payment', '{$paymentId}')");

        $request = $this->createValidRequest([
            'idempotency_key' => $idempotencyKey,
        ]);

        $result = $this->processor->processBookingPayment($request);

        $this->assertTrue($result->success);
        $this->assertSame('pending_payment', $result->status);
        $this->assertSame('Payment already processed.', $result->message);
        $this->assertSame($paymentId, $result->extra['payment_id'] ?? null);
        $this->assertSame('ovf_pend_1', $result->extra['reservation_code'] ?? null);
        $this->assertSame('pending_payment', $result->extra['status'] ?? null);
        $this->assertEmpty($this->gateway->getRecordedIntents());
    }

    public function testBookingPaymentRequestSanitizesXssInputs(): void
    {
        $request = BookingPaymentRequest::fromArray([
            'property_id' => '  1606  ',
            'check_in' => '2026-11-10',
            'check_out' => '2026-11-14',
            'guest_name' => '<script>alert("xss")</script>Carlos',
            'guest_email' => 'carlos@example.com',
            'guest_phone' => '+57 300 987 6543',
            'payment_method_id' => 'visa',
            'idempotency_key' => '  <tag>key</tag>  ',
        ]);

        $this->assertSame('1606', $request->propertyId);
        $this->assertStringNotContainsString('<script>', $request->guestName);
        $this->assertStringContainsString('&lt;script&gt;', $request->guestName);
        $this->assertSame('&lt;tag&gt;key&lt;/tag&gt;', $request->idempotencyKey);
    }

    public function testCustomPendingPaymentEmailRendererCanBeInjected(): void
    {
        $customRenderer = new class implements \OceanViewFlats\Domain\Payment\PendingPaymentEmailRendererInterface {
            public int $called = 0;
            public function renderPendingEmailHtml(\OceanViewFlats\Domain\Reservation\Reservation $reservation, \OceanViewFlats\Domain\Quote\Quote $quote, PaymentGatewayResult $gatewayResult): string {
                $this->called++;
                return '<html>custom</html>';
            }
        };

        $processor = new BookingPaymentProcessor(
            gateway: $this->gateway,
            pdo: $this->pdo,
            ledger: $this->ledger,
            quoteEngine: $this->quoteEngine,
            repository: $this->repository,
            fulfillment: $this->fulfillment,
            emailSender: $this->emailSender,
            translations: [],
            pendingEmailRenderer: $customRenderer
        );

        $this->gateway->stageCreateResult(new PaymentGatewayResult(
            success: true,
            paymentId: 'pay_efecty_test',
            status: 'pending',
            statusDetail: 'pending_waiting_payment',
            paymentMethodId: 'efecty',
            transactionAmount: 1610000.0,
            externalResourceUrl: 'https://mercadopago.com/voucher/123',
            barcode: '123456789',
            verificationCode: '987654',
            rawResponse: ['id' => 'pay_efecty_test', 'status' => 'pending']
        ));

        $result = $processor->processBookingPayment($this->createValidRequest([
            'payment_method_id' => 'efecty',
        ]));

        $this->assertTrue($result->success);
        $this->assertSame('pending_payment', $result->status);
        $this->assertSame(1, $customRenderer->called);
    }

    public function testApprovedCreditCardPaymentSucceedsEndToEnd(): void
    {
        $this->gateway->stageCreateResult(new PaymentGatewayResult(
            success: true,
            paymentId: 'mp_pay_approved_999',
            status: 'approved',
            statusDetail: 'accredited',
            paymentMethodId: 'visa',
            transactionAmount: 1610000.0,
            externalResourceUrl: null,
            barcode: null,
            verificationCode: null,
            rawResponse: ['id' => 'mp_pay_approved_999', 'status' => 'approved']
        ));

        $idempotencyKey = 'idem_card_test';
        $request = $this->createValidRequest([
            'idempotency_key' => $idempotencyKey,
            'lang' => 'en',
        ]);

        $result = $this->processor->processBookingPayment($request);

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->httpStatusCode);
        $this->assertSame('confirmed', $result->status);
        $this->assertNotNull($result->reservationUid);
        $this->assertSame('mp_pay_approved_999', $result->extra['payment_id']);
        $this->assertSame('approved', $result->extra['status']);

        // Verify gateway intent was recorded with correct amount and details
        $intents = $this->gateway->getRecordedIntents();
        $this->assertCount(1, $intents);
        $this->assertSame('visa', $intents[0]->paymentMethodId);
        $this->assertSame('carlos@example.com', $intents[0]->payerEmail);
        $this->assertGreaterThan(0, $intents[0]->transactionAmount);

        // Verify database reservation was persisted
        $persisted = $this->repository->findByUid($result->reservationUid);
        $this->assertNotNull($persisted);
        $this->assertSame(ReservationStatus::CONFIRMED, $persisted->status);
        $this->assertSame('approved', $persisted->paymentStatus);
        $this->assertSame('mp_pay_approved_999', $persisted->mercadopagoPaymentId);
        $this->assertSame('1606', $persisted->propertyId);
        $this->assertSame('Carlos Rodriguez', $persisted->guestName);

        // Verify idempotency was saved
        $stmt = $this->pdo->prepare('SELECT payment_id FROM payment_idempotency WHERE idempotency_key = :k');
        $stmt->execute(['k' => $idempotencyKey]);
        $this->assertSame('mp_pay_approved_999', $stmt->fetchColumn());

        // Verify audit log was recorded
        $auditCount = (int) $this->pdo->query('SELECT COUNT(*) FROM admin_audit_logs WHERE action = "reservation_confirmed"')->fetchColumn();
        $this->assertSame(1, $auditCount);

        // Verify fulfillment emails were sent (guest + host)
        $sentEmails = $this->emailSender->getSentMessages();
        $this->assertCount(2, $sentEmails);
        // ADR 0001: Door codes must NEVER appear in confirmation emails
        foreach ($sentEmails as $email) {
            $this->assertStringNotContainsString('door code', strtolower($email['htmlBody']));
            $this->assertStringNotContainsString('smart lock', strtolower($email['htmlBody']));
        }
    }

    public function testPendingPaymentSendsEmailsAndSavesPendingReservation(): void
    {
        $this->gateway->stageCreateResult(new PaymentGatewayResult(
            success: true,
            paymentId: 'mp_pay_efecty_888',
            status: 'pending',
            statusDetail: 'pending_waiting_payment',
            paymentMethodId: 'efecty',
            transactionAmount: 1610000.0,
            externalResourceUrl: 'https://mercadopago.com/tickets/voucher123',
            barcode: '123456789012',
            verificationCode: '987654',
            rawResponse: ['id' => 'mp_pay_efecty_888', 'status' => 'pending']
        ));

        $idempotencyKey = 'idem_efecty_test';
        $request = $this->createValidRequest([
            'payment_method_id' => 'efecty',
            'idempotency_key' => $idempotencyKey,
            'lang' => 'en',
        ]);

        $result = $this->processor->processBookingPayment($request);

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->httpStatusCode);
        $this->assertSame('pending_payment', $result->status);
        $this->assertNotNull($result->reservationUid);
        $this->assertSame('mp_pay_efecty_888', $result->extra['payment_id']);
        $this->assertSame('pending', $result->extra['status']);
        $this->assertSame('https://mercadopago.com/tickets/voucher123', $result->extra['external_resource_url']);
        $this->assertSame('https://mercadopago.com/tickets/voucher123', $result->extra['printable_voucher_url']);
        $this->assertSame('123456789012', $result->extra['barcode']);
        $this->assertSame('987654', $result->extra['verification_code']);

        // Verify pending reservation persisted in repository
        $persisted = $this->repository->findByUid($result->reservationUid);
        $this->assertNotNull($persisted);
        $this->assertSame(ReservationStatus::PENDING_PAYMENT, $persisted->status);
        $this->assertSame('pending', $persisted->paymentStatus);

        // Verify audit log
        $auditCount = (int) $this->pdo->query('SELECT COUNT(*) FROM admin_audit_logs WHERE action = "direct_hold_created"')->fetchColumn();
        $this->assertSame(1, $auditCount);

        // Verify pending emails were sent to both guest and host
        $sentEmails = $this->emailSender->getSentMessages();
        $this->assertCount(2, $sentEmails);
        $this->assertSame('reservas@oceanviewflats.com', $sentEmails[0]['to']);
        $this->assertSame('carlos@example.com', $sentEmails[1]['to']);
        $this->assertStringContainsString('BOOKING INQUIRY', $sentEmails[0]['subject']);

        // ADR 0001: Door codes must NEVER be in pending emails
        foreach ($sentEmails as $email) {
            $this->assertStringNotContainsString('door code', strtolower($email['htmlBody']));
            $this->assertStringNotContainsString('smart lock', strtolower($email['htmlBody']));
        }
    }

    public function testGatewayRejectionReturnsRejectedResult(): void
    {
        $this->gateway->stageCreateResult(new PaymentGatewayResult(
            success: false,
            paymentId: 'mp_pay_rejected_111',
            status: 'rejected',
            statusDetail: 'cc_rejected_insufficient_amount',
            paymentMethodId: 'visa',
            transactionAmount: 1610000.0,
            errorMessage: 'Insufficient funds on credit card'
        ));

        $request = $this->createValidRequest();
        $result = $this->processor->processBookingPayment($request);

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->httpStatusCode);
        $this->assertSame('rejected', $result->status);
        $this->assertSame('Insufficient funds on credit card', $result->message);
        $this->assertSame('cc_rejected_insufficient_amount', $result->extra['status_detail']);
        $this->assertSame('mp_pay_rejected_111', $result->extra['payment_id']);

        // Verify no reservation was created in database
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM reservations')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testGatewayExceptionReturnsErrorResult(): void
    {
        $this->gateway->stageException(new PaymentGatewayException('Card network timeout', 504, 'timeout'));

        $request = $this->createValidRequest();
        $result = $this->processor->processBookingPayment($request);

        $this->assertFalse($result->success);
        $this->assertSame(504, $result->httpStatusCode);
        $this->assertSame('error', $result->status);
        $this->assertStringContainsString('Card network timeout', $result->message);
    }

    public function testCreateDefaultFactoryInstantiation(): void
    {
        $processor = BookingPaymentProcessor::createDefault($this->pdo, [
            'gateway' => $this->gateway,
            'ledger' => $this->ledger,
            'quoteEngine' => $this->quoteEngine,
            'repository' => $this->repository,
            'fulfillment' => $this->fulfillment,
            'emailSender' => $this->emailSender,
        ]);

        $this->assertInstanceOf(BookingPaymentProcessor::class, $processor);
    }

    public function testRequestAndResultSerialization(): void
    {
        $request = $this->createValidRequest([
            'token' => 'tok_abc',
            'installments' => 2,
            'issuer_id' => '123',
            'identification_type' => 'CC',
            'identification_number' => '10203040',
            'financial_institution' => '1001',
        ]);

        $reqArray = $request->toArray();
        $this->assertSame('1606', $reqArray['property_id']);
        $this->assertSame('tok_abc', $reqArray['token']);
        $this->assertSame(2, $reqArray['installments']);
        $this->assertSame('CC', $reqArray['identification_type']);
        $this->assertSame('10203040', $reqArray['identification_number']);
        $this->assertSame('1001', $reqArray['financial_institution']);
        $this->assertSame($reqArray, $request->jsonSerialize());

        $result = BookingPaymentResult::confirmed('ovf_test_xyz', 'Booking confirmed', ['payment_id' => 'mp_123']);
        $resArray = $result->toArray();
        $this->assertTrue($resArray['success']);
        $this->assertSame('ovf_test_xyz', $resArray['reservation_code']);
        $this->assertSame('confirmed', $resArray['status']);
        $this->assertSame('mp_123', $resArray['payment_id']);
        $this->assertSame($resArray, $result->jsonSerialize());
    }
}
