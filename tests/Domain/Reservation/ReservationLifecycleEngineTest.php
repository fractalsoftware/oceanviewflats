<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Domain\Reservation;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use OceanViewFlats\Domain\Quote\QuoteEngine;
use OceanViewFlats\Domain\Reservation\ActorContext;
use OceanViewFlats\Domain\Reservation\CancellationRequest;
use OceanViewFlats\Domain\Reservation\ChannelBlock;
use OceanViewFlats\Domain\Reservation\DirectHoldRequest;
use OceanViewFlats\Domain\Reservation\DraftPaymentDetails;
use OceanViewFlats\Domain\Reservation\ExcessiveRefundException;
use OceanViewFlats\Domain\Reservation\GatewayRefundException;
use OceanViewFlats\Domain\Reservation\InMemoryChannelBlockSource;
use OceanViewFlats\Domain\Reservation\InMemoryMaintenanceBlockSource;
use OceanViewFlats\Domain\Reservation\InMemoryReservationRepository;
use OceanViewFlats\Domain\Reservation\InvalidReservationStateException;
use OceanViewFlats\Domain\Reservation\PrimaryGuest;
use OceanViewFlats\Domain\Reservation\RefundInstruction;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationConflictException;
use OceanViewFlats\Domain\Reservation\ReservationDraft;
use OceanViewFlats\Domain\Reservation\ReservationLedger;
use OceanViewFlats\Domain\Reservation\ReservationLifecycleEngine;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use OceanViewFlats\Domain\Reservation\ReservationValidationException;
use OceanViewFlats\Infrastructure\Reservation\InMemory\InMemoryAuditAdapter;
use OceanViewFlats\Infrastructure\Reservation\InMemory\InMemoryLifecycleEventPublisherAdapter;
use OceanViewFlats\Infrastructure\Reservation\InMemory\InMemoryPaymentRefundAdapter;
use OceanViewFlats\Infrastructure\Reservation\InMemory\InMemoryReservationPersistenceAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Authoritative unit test suite for ReservationLifecycleEngine (ADR 0011).
 * Tests all lifecycle invariants, hexagonal ports, and error boundaries in-memory.
 */
final class ReservationLifecycleEngineTest extends TestCase
{
    private InMemoryReservationRepository $repository;
    private InMemoryChannelBlockSource $channelBlockSource;
    private InMemoryMaintenanceBlockSource $maintenanceBlockSource;
    private ReservationLedger $ledger;
    private InMemoryReservationPersistenceAdapter $persistenceAdapter;
    private InMemoryPaymentRefundAdapter $paymentRefundAdapter;
    private InMemoryLifecycleEventPublisherAdapter $eventPublisherAdapter;
    private InMemoryAuditAdapter $auditAdapter;
    private QuoteEngine $quoteEngine;
    private ReservationLifecycleEngine $engine;
    private DateTimeZone $tz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tz = new DateTimeZone('America/Bogota');
        $this->repository = new InMemoryReservationRepository();
        $this->channelBlockSource = new InMemoryChannelBlockSource();
        $this->maintenanceBlockSource = new InMemoryMaintenanceBlockSource();

        $this->ledger = new ReservationLedger(
            repository: $this->repository,
            channelBlockSource: $this->channelBlockSource,
            maintenanceBlockSource: $this->maintenanceBlockSource
        );

        $this->persistenceAdapter = new InMemoryReservationPersistenceAdapter([], $this->repository);
        $this->paymentRefundAdapter = new InMemoryPaymentRefundAdapter();
        $this->eventPublisherAdapter = new InMemoryLifecycleEventPublisherAdapter();
        $this->auditAdapter = new InMemoryAuditAdapter();
        $this->quoteEngine = QuoteEngine::createDefault();

        $this->engine = new ReservationLifecycleEngine(
            persistencePort: $this->persistenceAdapter,
            paymentRefundPort: $this->paymentRefundAdapter,
            eventPublisherPort: $this->eventPublisherAdapter,
            auditPort: $this->auditAdapter,
            ledger: $this->ledger,
            quoteEngine: $this->quoteEngine
        );
    }

    // =========================================================================
    // 1. DIRECT HOLD INVARIANTS (ADR 0003 & ADR 0004)
    // =========================================================================

    public function testHoldDirectCreatesPendingPaymentWithCalculatedQuoteAndExpiresAt(): void
    {
        $guest = PrimaryGuest::create('Carlos Vives', 'carlos@example.com', '+573001234567', 'es');
        $request = DirectHoldRequest::create(
            propertyId: '1606',
            checkIn: '2026-08-10',
            checkOut: '2026-08-14',
            primaryGuest: $guest,
            paymentMethodId: 'credit_card',
            customHoldMinutes: 45,
            notes: 'Late check-in requested'
        );

        $result = $this->engine->holdDirect($request);

        $reservation = $result->reservation;
        $this->assertSame('1606', $reservation->propertyId);
        $this->assertSame('2026-08-10', $reservation->checkIn);
        $this->assertSame('2026-08-14', $reservation->checkOut);
        $this->assertSame(ReservationStatus::PENDING_PAYMENT, $reservation->status);
        $this->assertSame('credit_card', $reservation->paymentMethodId);
        $this->assertSame('web', $reservation->source);
        $this->assertNotEmpty($reservation->doorCode);
        $this->assertFalse($reservation->registryCompleted);
        $this->assertGreaterThan(0.0, $reservation->totalPrice);
        $this->assertSame($result->quote->totalCop(), $reservation->totalPrice);

        // Hold expiration check (approx 45 minutes)
        $now = new DateTimeImmutable('now', $this->tz);
        $diffMinutes = ($result->holdExpiresAt->getTimestamp() - $now->getTimestamp()) / 60;
        $this->assertEqualsWithDelta(45, $diffMinutes, 2);

        // Verify persistence & audit
        $saved = $this->persistenceAdapter->getReservation($reservation->reservationUid);
        $this->assertNotNull($saved);
        $this->assertSame($reservation->reservationUid, $saved->reservationUid);

        $auditRecords = $this->auditAdapter->getRecords();
        $this->assertCount(1, $auditRecords);
        $this->assertSame('direct_hold_created', $auditRecords[0]['action']);
        $this->assertSame($reservation->reservationUid, $auditRecords[0]['entityId']);
    }

    public function testHoldDirectEfectySets72HourHoldWindow(): void
    {
        $guest = PrimaryGuest::create('Elena Gomez', 'elena@example.com', '+573119876543');
        $request = DirectHoldRequest::create(
            propertyId: '1707',
            checkIn: '2026-09-01',
            checkOut: '2026-09-04',
            primaryGuest: $guest,
            paymentMethodId: 'efecty'
        );

        $result = $this->engine->holdDirect($request);

        $now = new DateTimeImmutable('now', $this->tz);
        $diffHours = ($result->holdExpiresAt->getTimestamp() - $now->getTimestamp()) / 3600;
        $this->assertEqualsWithDelta(72, $diffHours, 0.5);
    }

    public function testHoldDirectRejectsMinimumStayViolation(): void
    {
        $guest = PrimaryGuest::create('Short Stay Guest', 'short@example.com');
        $request = DirectHoldRequest::create(
            propertyId: '1606',
            checkIn: '2026-08-10',
            checkOut: '2026-08-11', // 1 night, but minimum stay is 2
            primaryGuest: $guest
        );

        $this->expectException(ReservationValidationException::class);
        $this->expectExceptionMessage('Seasonal minimum stay not met');

        try {
            $this->engine->holdDirect($request);
        } catch (ReservationValidationException $e) {
            $this->assertSame('MINIMUM_STAY_VIOLATED', $e->getErrorCode());
            $this->assertSame(2, $e->getExpected());
            $this->assertSame(1, $e->getActual());
            throw $e;
        }
    }

    public function testHoldDirectRejectsCalendarCollision(): void
    {
        // Place an active reservation on the dates
        $existing = Reservation::create(
            reservationUid: 'ovf_existing_123',
            propertyId: '1606',
            guestName: 'Existing Guest',
            guestEmail: 'existing@example.com',
            guestPhone: '',
            checkIn: '2026-08-10',
            checkOut: '2026-08-15',
            totalPrice: 1500000.0,
            status: ReservationStatus::CONFIRMED
        );
        $this->persistenceAdapter->save($existing);

        $guest = PrimaryGuest::create('Colliding Guest', 'colliding@example.com');
        $request = DirectHoldRequest::create(
            propertyId: '1606',
            checkIn: '2026-08-12',
            checkOut: '2026-08-14',
            primaryGuest: $guest
        );

        $this->expectException(ReservationConflictException::class);
        $this->expectExceptionMessage('Calendar conflict for property 1606');

        $this->engine->holdDirect($request);
    }

    public function testHoldDirectRejectsInvalidDates(): void
    {
        $guest = PrimaryGuest::create('Backwards Guest', 'backwards@example.com');
        $request = DirectHoldRequest::create(
            propertyId: '1606',
            checkIn: '2026-08-15',
            checkOut: '2026-08-10',
            primaryGuest: $guest
        );

        $this->expectException(ReservationValidationException::class);
        $this->expectExceptionMessage('must be strictly after check-in date');

        try {
            $this->engine->holdDirect($request);
        } catch (ReservationValidationException $e) {
            $this->assertSame('INVALID_DATES', $e->getErrorCode());
            throw $e;
        }
    }

    // =========================================================================
    // 2. CONFIRM OR RECORD INVARIANTS (ADR 0001, ADR 0007, ADR 0009, ADR 0011)
    // =========================================================================

    public function testConfirmOrRecordDirectCheckoutSuccess(): void
    {
        $guest = PrimaryGuest::create('Maria Santos', 'maria@example.com', '+573105554321', 'en');
        $payment = DraftPaymentDetails::create(
            paymentMethodId: 'credit_card',
            mercadopagoPaymentId: 'mp_pay_556677',
            paymentStatus: 'approved'
        );

        $draft = ReservationDraft::direct(
            propertyId: '1707',
            checkIn: '2026-10-01',
            checkOut: '2026-10-05',
            primaryGuest: $guest,
            payment: $payment,
            preMarkRegistry: false,
            sendConfirmationEmail: true
        );

        $result = $this->engine->confirmOrRecord($draft);

        $reservation = $result->reservation;
        $this->assertSame(ReservationStatus::CONFIRMED, $reservation->status);
        $this->assertSame('approved', $reservation->paymentStatus);
        $this->assertSame('mp_pay_556677', $reservation->mercadopagoPaymentId);
        $this->assertTrue($result->accessPinAllocated);
        // Statutory Requirement (ADR 0001): Pin withheld until statutory registry completed!
        $this->assertFalse($result->accessPinReleasedToGuest);
        $this->assertFalse($reservation->registryCompleted);
        $this->assertNotEmpty($result->doorCode);

        // Verify Domain Event dispatch
        $confirmedEvents = $this->eventPublisherAdapter->getConfirmedEvents();
        $this->assertCount(1, $confirmedEvents);
        $this->assertSame($reservation->reservationUid, $confirmedEvents[0]->reservation->reservationUid);
        $this->assertTrue($confirmedEvents[0]->sendConfirmationEmail);
        $this->assertFalse($confirmedEvents[0]->wasChannelBlockAbsorbed);

        // Verify Audit Log
        $records = $this->auditAdapter->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame('reservation_confirmed', $records[0]['action']);
        $this->assertSame($reservation->reservationUid, $records[0]['entityId']);
    }

    public function testConfirmOrRecordReleasesPinWhenPreMarkRegistryIsTrue(): void
    {
        $guest = PrimaryGuest::create('Verified Guest', 'verified@example.com', '+573100000000');
        $draft = ReservationDraft::direct(
            propertyId: '1606',
            checkIn: '2026-10-10',
            checkOut: '2026-10-13',
            primaryGuest: $guest,
            preMarkRegistry: true
        );

        $result = $this->engine->confirmOrRecord($draft);

        $this->assertTrue($result->accessPinAllocated);
        $this->assertTrue($result->accessPinReleasedToGuest);
        $this->assertTrue($result->reservation->registryCompleted);
        $this->assertNotNull($result->reservation->registryCompletedAt);
    }

    public function testConfirmOrRecordAbsorbsAirbnbChannelBlockWithoutCollision(): void
    {
        // 1. Add external ephemeral channel block from airbnb (ADR 0002 & ADR 0007)
        $channelBlock = new ChannelBlock(
            propertyId: '1606',
            startDate: '2026-11-01',
            endDate: '2026-11-06',
            source: 'airbnb',
            summary: 'abnb_block_999'
        );
        $this->channelBlockSource->addBlock($channelBlock);

        // 2. An external reservation arrives from Airbnb matching the blocked dates
        $guest = PrimaryGuest::create('Airbnb Traveler', 'airbnb.guest@example.com');
        $draft = ReservationDraft::external(
            propertyId: '1606',
            checkIn: '2026-11-01',
            checkOut: '2026-11-06',
            primaryGuest: $guest,
            source: 'airbnb',
            totalPrice: 1800000.0,
            externalConfirmationCode: 'HM99XYZ88',
            channelBlockUid: 'abnb_block_999'
        );

        $result = $this->engine->confirmOrRecord($draft);

        $this->assertTrue($result->wasChannelBlockAbsorbed);
        $this->assertSame('abnb_block_999', $result->absorbedChannelBlockUid);
        $this->assertSame(ReservationStatus::CONFIRMED, $result->reservation->status);
        $this->assertSame('airbnb', $result->reservation->source);
        $this->assertSame('HM99XYZ88', $result->reservation->externalConfirmationCode);

        // Verify event and audit
        $events = $this->eventPublisherAdapter->getConfirmedEvents();
        $this->assertCount(1, $events);
        $this->assertTrue($events[0]->wasChannelBlockAbsorbed);

        $records = $this->auditAdapter->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame('airbnb_reservation_created', $records[0]['action']);
    }

    public function testConfirmOrRecordManualDraftSuccess(): void
    {
        $guest = PrimaryGuest::create('VIP Friend', 'vip@example.com');
        $actor = ActorContext::admin(42, '192.168.1.100', 'AdminDesk/1.0');
        $draft = ReservationDraft::manual(
            propertyId: '1707',
            checkIn: '2026-11-10',
            checkOut: '2026-11-14',
            primaryGuest: $guest,
            totalPrice: 1200000.0,
            actor: $actor,
            notes: 'Comped stay authorized by owner'
        );

        $result = $this->engine->confirmOrRecord($draft);

        $this->assertSame('manual_override', $result->reservation->source);
        $this->assertSame(1200000.0, $result->reservation->totalPrice);
        $this->assertSame('Comped stay authorized by owner', $result->reservation->notes);

        $records = $this->auditAdapter->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame('manual_reservation_created', $records[0]['action']);
        $this->assertSame(42, $records[0]['actor']->adminUserId);
    }

    public function testConfirmOrRecordTransitionsExistingPendingHold(): void
    {
        // 1. Create a pending hold first
        $guest = PrimaryGuest::create('Hold Guest', 'hold@example.com', '+573009998877');
        $hold = $this->engine->holdDirect(DirectHoldRequest::create(
            propertyId: '1606',
            checkIn: '2026-12-01',
            checkOut: '2026-12-05',
            primaryGuest: $guest
        ));

        $uid = $hold->reservation->reservationUid;
        $originalDoorCode = $hold->reservation->doorCode;

        // 2. Confirm the pending hold
        $draft = ReservationDraft::direct(
            propertyId: '1606',
            checkIn: '2026-12-01',
            checkOut: '2026-12-05',
            primaryGuest: $guest,
            reservationUid: $uid,
            payment: DraftPaymentDetails::create('credit_card', 'mp_pay_9999', 'approved')
        );

        $result = $this->engine->confirmOrRecord($draft);

        $this->assertSame($uid, $result->reservation->reservationUid);
        $this->assertSame(ReservationStatus::CONFIRMED, $result->reservation->status);
        $this->assertSame('approved', $result->reservation->paymentStatus);
        $this->assertSame($originalDoorCode, $result->reservation->doorCode);
    }

    public function testConfirmOrRecordResurrectionDefenseRejectsCancelledReservation(): void
    {
        $existing = Reservation::create(
            reservationUid: 'ovf_cancelled_111',
            propertyId: '1606',
            guestName: 'Cancelled Guest',
            guestEmail: 'cancelled@example.com',
            guestPhone: '',
            checkIn: '2026-12-10',
            checkOut: '2026-12-14',
            totalPrice: 1400000.0,
            status: ReservationStatus::CANCELLED
        );
        $this->persistenceAdapter->save($existing);

        $guest = PrimaryGuest::create('Revive Guest', 'revive@example.com');
        $draft = ReservationDraft::direct(
            propertyId: '1606',
            checkIn: '2026-12-10',
            checkOut: '2026-12-14',
            primaryGuest: $guest,
            reservationUid: 'ovf_cancelled_111'
        );

        $this->expectException(InvalidReservationStateException::class);
        $this->expectExceptionMessage('Resurrection defense rejected transition "confirm"');

        try {
            $this->engine->confirmOrRecord($draft);
        } catch (InvalidReservationStateException $e) {
            $this->assertSame('RESURRECTION_REJECTED', $e->getErrorCode());
            $this->assertSame('cancelled', $e->getCurrentState());
            $this->assertSame('confirm', $e->getAttemptedTransition());
            throw $e;
        }
    }

    public function testConfirmOrRecordResurrectionDefenseRejectsConcludedReservation(): void
    {
        $existing = Reservation::create(
            reservationUid: 'ovf_concluded_222',
            propertyId: '1707',
            guestName: 'Past Guest',
            guestEmail: 'past@example.com',
            guestPhone: '',
            checkIn: '2020-01-01',
            checkOut: '2020-01-05',
            totalPrice: 1800000.0,
            status: ReservationStatus::CONFIRMED
        );
        $this->persistenceAdapter->save($existing);

        $guest = PrimaryGuest::create('Past Guest', 'past@example.com');
        $draft = ReservationDraft::direct(
            propertyId: '1707',
            checkIn: '2020-01-01',
            checkOut: '2020-01-05',
            primaryGuest: $guest,
            reservationUid: 'ovf_concluded_222'
        );

        $this->expectException(InvalidReservationStateException::class);
        $this->expectExceptionMessage('Resurrection defense rejected transition "confirm"');

        try {
            $this->engine->confirmOrRecord($draft);
        } catch (InvalidReservationStateException $e) {
            $this->assertSame('RESURRECTION_REJECTED', $e->getErrorCode());
            $this->assertSame('concluded', $e->getCurrentState());
            $this->assertSame('confirm', $e->getAttemptedTransition());
            throw $e;
        }
    }

    // =========================================================================
    // 3. PREVIEW CANCELLATION INVARIANTS (ADR 0011)
    // =========================================================================

    public function testPreviewCancellationCalculationsForVariousWindows(): void
    {
        $today = new DateTimeImmutable('today', $this->tz);

        // Case A: 20 days ahead (>= 14 days) -> 0% retention, 100% max refund
        $inA = $today->modify('+20 days')->format('Y-m-d');
        $outA = $today->modify('+24 days')->format('Y-m-d');
        $resA = Reservation::create(
            reservationUid: 'ovf_preview_a',
            propertyId: '1606',
            guestName: 'Guest A',
            guestEmail: 'a@example.com',
            guestPhone: '',
            checkIn: $inA,
            checkOut: $outA,
            totalPrice: 1000000.0,
            status: ReservationStatus::CONFIRMED,
            mercadopagoPaymentId: 'mp_pay_a'
        );
        $this->persistenceAdapter->save($resA);

        $previewA = $this->engine->previewCancellation('ovf_preview_a');
        $this->assertSame(1000000.0, $previewA->totalPrice);
        $this->assertSame(0.0, $previewA->alreadyRefundedCop);
        $this->assertSame(1000000.0, $previewA->refundableBalanceCop);
        $this->assertSame(20, $previewA->daysUntilCheckIn);
        $this->assertSame(0.0, $previewA->suggestedPolicyRetentionCop);
        $this->assertSame(1000000.0, $previewA->suggestedMaxRefundCop);
        $this->assertTrue($previewA->isOnlinePayment);
        $this->assertSame('mp_pay_a', $previewA->mercadopagoPaymentId);

        // Case B: 10 days ahead (7 to 13 days) -> 50% retention, 50% max refund
        $inB = $today->modify('+10 days')->format('Y-m-d');
        $outB = $today->modify('+14 days')->format('Y-m-d');
        $resB = Reservation::create(
            reservationUid: 'ovf_preview_b',
            propertyId: '1707',
            guestName: 'Guest B',
            guestEmail: 'b@example.com',
            guestPhone: '',
            checkIn: $inB,
            checkOut: $outB,
            totalPrice: 1000000.0,
            status: ReservationStatus::CONFIRMED
        );
        $this->persistenceAdapter->save($resB);

        $previewB = $this->engine->previewCancellation('ovf_preview_b');
        $this->assertSame(10, $previewB->daysUntilCheckIn);
        $this->assertSame(500000.0, $previewB->suggestedPolicyRetentionCop);
        $this->assertSame(500000.0, $previewB->suggestedMaxRefundCop);
        $this->assertFalse($previewB->isOnlinePayment);

        // Case C: 3 days ahead (< 7 days) -> 100% retention, 0% max refund
        $inC = $today->modify('+3 days')->format('Y-m-d');
        $outC = $today->modify('+7 days')->format('Y-m-d');
        $resC = Reservation::create(
            reservationUid: 'ovf_preview_c',
            propertyId: '1606',
            guestName: 'Guest C',
            guestEmail: 'c@example.com',
            guestPhone: '',
            checkIn: $inC,
            checkOut: $outC,
            totalPrice: 1000000.0,
            status: ReservationStatus::CONFIRMED
        );
        $this->persistenceAdapter->save($resC);

        $previewC = $this->engine->previewCancellation('ovf_preview_c');
        $this->assertSame(3, $previewC->daysUntilCheckIn);
        $this->assertSame(1000000.0, $previewC->suggestedPolicyRetentionCop);
        $this->assertSame(0.0, $previewC->suggestedMaxRefundCop);

        // Case D: Partial prior refund reduces balance
        $resD = Reservation::create(
            reservationUid: 'ovf_preview_d',
            propertyId: '1606',
            guestName: 'Guest D',
            guestEmail: 'd@example.com',
            guestPhone: '',
            checkIn: $inA,
            checkOut: $outA,
            totalPrice: 1000000.0,
            status: ReservationStatus::CONFIRMED,
            refundedAmount: 400000.0
        );
        $this->persistenceAdapter->save($resD);

        $previewD = $this->engine->previewCancellation('ovf_preview_d');
        $this->assertSame(400000.0, $previewD->alreadyRefundedCop);
        $this->assertSame(600000.0, $previewD->refundableBalanceCop);
        $this->assertSame(600000.0, $previewD->suggestedMaxRefundCop);
    }

    public function testPreviewCancellationTerminalStateDefense(): void
    {
        $cancelled = Reservation::create(
            reservationUid: 'ovf_prev_cancel',
            propertyId: '1606',
            guestName: 'Cancelled',
            guestEmail: 'c@example.com',
            guestPhone: '',
            checkIn: '2026-12-01',
            checkOut: '2026-12-05',
            totalPrice: 1000000.0,
            status: ReservationStatus::CANCELLED
        );
        $this->persistenceAdapter->save($cancelled);

        $this->expectException(InvalidReservationStateException::class);
        $this->expectExceptionMessage('Reservation ovf_prev_cancel is already cancelled');

        $this->engine->previewCancellation('ovf_prev_cancel');
    }

    public function testPreviewCancellationNotFoundThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reservation not found: unknown_uid');

        $this->engine->previewCancellation('unknown_uid');
    }

    // =========================================================================
    // 4. CANCELLATION & PRE-COMMIT REFUND DISPATCH (ADR 0009 & ADR 0011)
    // =========================================================================

    public function testCancelWithoutRefund(): void
    {
        $reservation = Reservation::create(
            reservationUid: 'ovf_cancel_norefund',
            propertyId: '1606',
            guestName: 'No Refund Guest',
            guestEmail: 'norefund@example.com',
            guestPhone: '',
            checkIn: '2026-12-10',
            checkOut: '2026-12-15',
            totalPrice: 1500000.0,
            status: ReservationStatus::CONFIRMED,
            notes: 'Existing special request'
        );
        $this->persistenceAdapter->save($reservation);

        $request = CancellationRequest::create(
            reason: 'Guest requested cancellation outside refund window',
            refundInstruction: RefundInstruction::none(),
            actor: ActorContext::admin(1, 'admin1')
        );

        $result = $this->engine->cancel('ovf_cancel_norefund', $request);

        $this->assertSame(ReservationStatus::CANCELLED, $result->reservation->status);
        $this->assertSame(0.0, $result->refundAmountCop);
        $this->assertSame(1500000.0, $result->policyRetentionCop);
        $this->assertNull($result->refundReceipt);
        $this->assertStringContainsString('Policy retention: No refund', (string) $result->reservation->notes);
        $this->assertStringContainsString('Existing special request', (string) $result->reservation->notes);

        // Payment gateway must NOT be called for none()
        $this->assertEmpty($this->paymentRefundAdapter->getRefundCalls());

        // Event Publisher
        $cancelledEvents = $this->eventPublisherAdapter->getCancelledEvents();
        $this->assertCount(1, $cancelledEvents);
        $this->assertSame('ovf_cancel_norefund', $cancelledEvents[0]->reservation->reservationUid);

        // Audit log
        $records = $this->auditAdapter->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame('reservation_cancelled', $records[0]['action']);
        $this->assertSame('ovf_cancel_norefund', $records[0]['entityId']);
    }

    public function testCancelWithFullRefundOnlinePaymentPreTransaction(): void
    {
        $reservation = Reservation::create(
            reservationUid: 'ovf_cancel_full',
            propertyId: '1707',
            guestName: 'Full Refund Guest',
            guestEmail: 'full@example.com',
            guestPhone: '',
            checkIn: '2026-12-20',
            checkOut: '2026-12-25',
            totalPrice: 2000000.0,
            status: ReservationStatus::CONFIRMED,
            paymentMethodId: 'credit_card',
            mercadopagoPaymentId: 'pay_online_789'
        );
        $this->persistenceAdapter->save($reservation);

        $request = CancellationRequest::create(
            reason: 'Emergency cancellation granted full refund',
            refundInstruction: RefundInstruction::full(),
            actor: ActorContext::admin(2, 'admin2')
        );

        $result = $this->engine->cancel('ovf_cancel_full', $request);

        // 1. Verify gateway refund dispatch
        $calls = $this->paymentRefundAdapter->getRefundCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('pay_online_789', $calls[0]['paymentId']);
        $this->assertSame(2000000.0, $calls[0]['amountCop']);
        $this->assertSame('ref_ovf_cancel_full_2000000', $calls[0]['idempotencyKey']);

        // 2. Verify cancellation result
        $this->assertNotNull($result->refundReceipt);
        $this->assertSame('pay_online_789', $result->refundReceipt->paymentId);
        $this->assertSame(2000000.0, $result->refundAmountCop);
        $this->assertSame(0.0, $result->policyRetentionCop);

        // 3. Verify reservation updated state
        $updated = $this->persistenceAdapter->getReservation('ovf_cancel_full');
        $this->assertNotNull($updated);
        $this->assertSame(ReservationStatus::CANCELLED, $updated->status);
        $this->assertSame('refunded', $updated->paymentStatus);
        $this->assertSame(2000000.0, $updated->refundedAmount);

        // 4. Verify refund table record in persistence port
        $refundRecords = $this->persistenceAdapter->getRefunds();
        $this->assertCount(1, $refundRecords);
        $this->assertSame('ovf_cancel_full', $refundRecords[0]['reservation_uid']);
        $this->assertSame('pay_online_789', $refundRecords[0]['mercadopago_payment_id']);
        $this->assertSame(2000000.0, $refundRecords[0]['amount']);
        $this->assertSame('admin_pms', $refundRecords[0]['source']);

        // 5. Verify audit logs: cancellation AND refund recorded
        $records = $this->auditAdapter->getRecords();
        $this->assertCount(2, $records);
        $this->assertSame('reservation_cancelled', $records[0]['action']);
        $this->assertSame('refund_issued', $records[1]['action']);
        $this->assertSame(2000000.0, $records[1]['payloadAfter']['refund_amount']);
    }

    public function testCancelWithPartialRefund(): void
    {
        $reservation = Reservation::create(
            reservationUid: 'ovf_cancel_partial',
            propertyId: '1606',
            guestName: 'Partial Refund Guest',
            guestEmail: 'partial@example.com',
            guestPhone: '',
            checkIn: '2026-12-20',
            checkOut: '2026-12-25',
            totalPrice: 1000000.0,
            status: ReservationStatus::CONFIRMED,
            mercadopagoPaymentId: 'pay_partial_123'
        );
        $this->persistenceAdapter->save($reservation);

        $request = CancellationRequest::create(
            reason: '50% policy retention applied',
            refundInstruction: RefundInstruction::partial(500000.0),
            actor: ActorContext::admin(3, 'admin3')
        );

        $result = $this->engine->cancel('ovf_cancel_partial', $request);

        $this->assertSame(500000.0, $result->refundAmountCop);
        $this->assertSame(500000.0, $result->policyRetentionCop);

        $updated = $this->persistenceAdapter->getReservation('ovf_cancel_partial');
        $this->assertNotNull($updated);
        $this->assertSame('partially_refunded', $updated->paymentStatus);
        $this->assertSame(500000.0, $updated->refundedAmount);
    }

    public function testCancelPreTransactionGatewayFailureLeavesDatabaseUnmutated(): void
    {
        $reservation = Reservation::create(
            reservationUid: 'ovf_gateway_failure',
            propertyId: '1707',
            guestName: 'Network Fail Guest',
            guestEmail: 'netfail@example.com',
            guestPhone: '',
            checkIn: '2026-12-20',
            checkOut: '2026-12-25',
            totalPrice: 1500000.0,
            status: ReservationStatus::CONFIRMED,
            paymentStatus: 'approved',
            mercadopagoPaymentId: 'pay_timeout_999'
        );
        $this->persistenceAdapter->save($reservation);

        // Instruct in-memory gateway adapter to simulate network 504 Gateway Timeout
        $this->paymentRefundAdapter->simulateFailure('MercadoPago API connection timeout (504)');

        $request = CancellationRequest::create(
            reason: 'Refund attempted during gateway outage',
            refundInstruction: RefundInstruction::full()
        );

        $this->expectException(GatewayRefundException::class);
        $this->expectExceptionMessage('MercadoPago API connection timeout (504)');

        try {
            $this->engine->cancel('ovf_gateway_failure', $request);
        } finally {
            // CRITICAL INVARIANT: Database must remain completely UNMUTATED!
            $unmutated = $this->persistenceAdapter->getReservation('ovf_gateway_failure');
            $this->assertNotNull($unmutated);
            $this->assertSame(ReservationStatus::CONFIRMED, $unmutated->status);
            $this->assertSame('approved', $unmutated->paymentStatus);
            $this->assertSame(0.0, $unmutated->refundedAmount);

            // No refunds recorded in persistence
            $this->assertEmpty($this->persistenceAdapter->getRefunds());

            // No cancellation audit logs recorded
            $this->assertEmpty($this->auditAdapter->getRecords());

            // No domain events published
            $this->assertEmpty($this->eventPublisherAdapter->getCancelledEvents());
        }
    }

    public function testCancelRejectsExcessiveRefundAmount(): void
    {
        $reservation = Reservation::create(
            reservationUid: 'ovf_excessive_refund',
            propertyId: '1606',
            guestName: 'Greedy Refund Guest',
            guestEmail: 'greedy@example.com',
            guestPhone: '',
            checkIn: '2026-12-20',
            checkOut: '2026-12-25',
            totalPrice: 1000000.0,
            status: ReservationStatus::CONFIRMED,
            refundedAmount: 600000.0 // Only 400000 refundable balance remaining!
        );
        $this->persistenceAdapter->save($reservation);

        $request = CancellationRequest::create(
            reason: 'Excessive refund request',
            refundInstruction: RefundInstruction::partial(500000.0) // 500000 > 400000
        );

        $this->expectException(ExcessiveRefundException::class);
        $this->expectExceptionMessage('exceeds remaining refundable balance');

        try {
            $this->engine->cancel('ovf_excessive_refund', $request);
        } catch (ExcessiveRefundException $e) {
            $this->assertSame('EXCESSIVE_REFUND', $e->getErrorCode());
            $this->assertSame('ovf_excessive_refund', $e->getReservationUid());
            $this->assertSame(500000.0, $e->getRequestedAmount());
            $this->assertSame(400000.0, $e->getRemainingBalance());
            throw $e;
        } finally {
            // Gateway must never have been called
            $this->assertEmpty($this->paymentRefundAdapter->getRefundCalls());
        }
    }

    public function testCancelTerminalStateDefenseRejectsAlreadyCancelled(): void
    {
        $reservation = Reservation::create(
            reservationUid: 'ovf_already_cancelled',
            propertyId: '1606',
            guestName: 'Cancelled Guest',
            guestEmail: 'canc@example.com',
            guestPhone: '',
            checkIn: '2026-12-20',
            checkOut: '2026-12-25',
            totalPrice: 1000000.0,
            status: ReservationStatus::CANCELLED
        );
        $this->persistenceAdapter->save($reservation);

        $request = CancellationRequest::create('Repeated cancel', RefundInstruction::none());

        $this->expectException(InvalidReservationStateException::class);
        $this->expectExceptionMessage('Reservation ovf_already_cancelled is already cancelled');

        $this->engine->cancel('ovf_already_cancelled', $request);
    }

    public function testCancelTerminalStateDefenseRejectsConcluded(): void
    {
        $reservation = Reservation::create(
            reservationUid: 'ovf_already_concluded',
            propertyId: '1606',
            guestName: 'Concluded Guest',
            guestEmail: 'conc@example.com',
            guestPhone: '',
            checkIn: '2020-01-01',
            checkOut: '2020-01-05',
            totalPrice: 1000000.0,
            status: ReservationStatus::CONFIRMED
        );
        $this->persistenceAdapter->save($reservation);

        $request = CancellationRequest::create('Cancel past stay', RefundInstruction::none());

        $this->expectException(InvalidReservationStateException::class);
        $this->expectExceptionMessage('Reservation ovf_already_concluded has already concluded');

        $this->engine->cancel('ovf_already_concluded', $request);
    }

    public function testCancelRejectsEmptyReason(): void
    {
        $reservation = Reservation::create(
            reservationUid: 'ovf_blank_reason',
            propertyId: '1606',
            guestName: 'Blank Reason Guest',
            guestEmail: 'blank@example.com',
            guestPhone: '',
            checkIn: '2026-12-20',
            checkOut: '2026-12-25',
            totalPrice: 1000000.0,
            status: ReservationStatus::CONFIRMED
        );
        $this->persistenceAdapter->save($reservation);

        $request = CancellationRequest::create('   ', RefundInstruction::none());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cancellation reason cannot be empty');

        $this->engine->cancel('ovf_blank_reason', $request);
    }

    public function testConfirmOrRecordPersistsDefaultAndOverriddenMaxGuests(): void
    {
        $guest = PrimaryGuest::create('Large Family Guest', 'family@example.com', '+573101112233');

        $manualDefault = ReservationDraft::manual(
            propertyId: '1606',
            checkIn: '2026-10-10',
            checkOut: '2026-10-14',
            primaryGuest: $guest,
            totalPrice: 1200000.0
        );
        $resDefault = $this->engine->confirmOrRecord($manualDefault);
        $this->assertSame(6, $resDefault->reservation->maxGuests);

        $manualOverride = ReservationDraft::manual(
            propertyId: '1707',
            checkIn: '2026-10-15',
            checkOut: '2026-10-20',
            primaryGuest: $guest,
            totalPrice: 2000000.0,
            maxGuests: 10
        );
        $resOverride = $this->engine->confirmOrRecord($manualOverride);
        $this->assertSame(10, $resOverride->reservation->maxGuests);
        $this->assertSame(10, $this->persistenceAdapter->getReservation($resOverride->reservation->reservationUid)?->maxGuests);

        $externalOverride = ReservationDraft::external(
            propertyId: '1606',
            checkIn: '2026-10-22',
            checkOut: '2026-10-26',
            primaryGuest: $guest,
            externalConfirmationCode: 'HM12GUESTS',
            maxGuests: 12
        );
        $resExt = $this->engine->confirmOrRecord($externalOverride);
        $this->assertSame(12, $resExt->reservation->maxGuests);
    }

    public function testReservationDraftRejectsOutOfRangeMaxGuests(): void
    {
        $guest = PrimaryGuest::create('Invalid Capacity Guest', 'cap@example.com', '+573101112233');

        $this->expectException(ReservationValidationException::class);
        $this->expectExceptionMessage('Maximum occupancy (max_guests) must be between 1 and 12');

        ReservationDraft::manual(
            propertyId: '1606',
            checkIn: '2026-10-10',
            checkOut: '2026-10-14',
            primaryGuest: $guest,
            totalPrice: 1200000.0,
            maxGuests: 15
        );
    }
}
