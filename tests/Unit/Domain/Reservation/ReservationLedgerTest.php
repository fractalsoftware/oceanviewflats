<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Reservation;

use DateTimeImmutable;
use InvalidArgumentException;
use OceanViewFlats\Domain\Reservation\ChannelBlock;
use OceanViewFlats\Domain\Reservation\InMemoryChannelBlockSource;
use OceanViewFlats\Domain\Reservation\InMemoryMaintenanceBlockSource;
use OceanViewFlats\Domain\Reservation\InMemoryReservationRepository;
use OceanViewFlats\Domain\Reservation\MaintenanceBlock;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationConflictException;
use OceanViewFlats\Domain\Reservation\ReservationLedger;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use PHPUnit\Framework\TestCase;

final class ReservationLedgerTest extends TestCase
{
    private InMemoryReservationRepository $repository;
    private InMemoryChannelBlockSource $channelBlockSource;
    private InMemoryMaintenanceBlockSource $maintenanceBlockSource;
    private ReservationLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new InMemoryReservationRepository();
        $this->channelBlockSource = new InMemoryChannelBlockSource();
        $this->maintenanceBlockSource = new InMemoryMaintenanceBlockSource();
        $this->ledger = new ReservationLedger(
            repository: $this->repository,
            channelBlockSource: $this->channelBlockSource,
            maintenanceBlockSource: $this->maintenanceBlockSource
        );
    }

    public function testEmptyLedgerIsAvailable(): void
    {
        $this->assertTrue($this->ledger->isAvailable('1606', '2026-07-01', '2026-07-05'));
        $this->assertEmpty($this->ledger->getConflictReasons('1606', '2026-07-01', '2026-07-05'));
        $this->assertEmpty($this->ledger->getBlockedNights('1606'));
    }

    public function testInvertedDatesAreNotAvailable(): void
    {
        $this->assertFalse($this->ledger->isAvailable('1606', '2026-07-05', '2026-07-01'));
        $reasons = $this->ledger->getConflictReasons('1606', '2026-07-05', '2026-07-01');
        $this->assertCount(1, $reasons);
        $this->assertStringContainsString('must be after check-in date', $reasons[0]);
    }

    public function testEphemeralChannelBlocksBlockAvailabilityWithoutWritingToDatabase(): void
    {
        // Add ephemeral channel block (ADR 0002)
        $this->channelBlockSource->addBlock(new ChannelBlock(
            propertyId: '1606',
            startDate: '2026-07-10',
            endDate: '2026-07-15',
            source: 'airbnb'
        ));

        // Overlapping range is blocked
        $this->assertFalse($this->ledger->isAvailable('1606', '2026-07-12', '2026-07-14'));
        $reasons = $this->ledger->getConflictReasons('1606', '2026-07-12', '2026-07-14');
        $this->assertCount(1, $reasons);
        $this->assertStringContainsString('external airbnb channel block', $reasons[0]);

        // Adjacent stays are available (check-in on checkout date)
        $this->assertTrue($this->ledger->isAvailable('1606', '2026-07-07', '2026-07-10'));
        $this->assertTrue($this->ledger->isAvailable('1606', '2026-07-15', '2026-07-18'));

        // Different property is unaffected
        $this->assertTrue($this->ledger->isAvailable('1707', '2026-07-10', '2026-07-15'));

        // Crucial ADR 0002 invariant: Channel blocks are NEVER written to the reservations repository
        $this->assertCount(0, $this->repository->all());
    }

    public function testHoldCreationPersistsPendingReservationAndBlocksDates(): void
    {
        $now = new DateTimeImmutable('2026-07-01 10:00:00');

        $reservation = new Reservation(
            reservationUid: 'ovf_test_1',
            propertyId: '1606',
            guestName: 'Ana Gomez',
            guestEmail: 'ana@example.com',
            guestPhone: '+573001234567',
            checkIn: '2026-07-10',
            checkOut: '2026-07-13',
            totalPrice: 1150000.0,
            status: ReservationStatus::PENDING_PAYMENT,
            paymentMethodId: 'card',
            createdAt: $now
        );

        $saved = $this->ledger->hold($reservation, $now);
        $this->assertSame('ovf_test_1', $saved->reservationUid);
        $this->assertCount(1, $this->repository->all());

        // Now dates are occupied
        $this->assertFalse($this->ledger->isAvailable('1606', '2026-07-11', '2026-07-14', $now));

        // Attempting a second hold on overlapping dates throws ReservationConflictException
        $conflictingReservation = new Reservation(
            reservationUid: 'ovf_test_2',
            propertyId: '1606',
            guestName: 'Carlos Perez',
            guestEmail: 'carlos@example.com',
            guestPhone: '+573119876543',
            checkIn: '2026-07-12',
            checkOut: '2026-07-15',
            totalPrice: 1150000.0,
            status: ReservationStatus::PENDING_PAYMENT,
            paymentMethodId: 'card',
            createdAt: $now
        );

        $this->expectException(ReservationConflictException::class);
        $this->expectExceptionMessage('Calendar conflict for property 1606 from 2026-07-12 to 2026-07-15');
        $this->ledger->hold($conflictingReservation, $now);
    }

    public function testStandardHoldExpiresAfter30MinutesForCardPayments(): void
    {
        $createdAt = new DateTimeImmutable('2026-07-01 10:00:00');

        $reservation = new Reservation(
            reservationUid: 'ovf_card_hold',
            propertyId: '1606',
            guestName: 'Beatriz Silva',
            guestEmail: 'beatriz@example.com',
            guestPhone: '+573001112233',
            checkIn: '2026-07-10',
            checkOut: '2026-07-13',
            totalPrice: 1150000.0,
            status: ReservationStatus::PENDING_PAYMENT,
            paymentMethodId: 'card',
            createdAt: $createdAt
        );

        $this->ledger->hold($reservation, $createdAt);

        // At T = 29 minutes: hold is active
        $t29 = new DateTimeImmutable('2026-07-01 10:29:00');
        $this->assertFalse($this->ledger->isAvailable('1606', '2026-07-10', '2026-07-13', $t29));

        // At T = 31 minutes: standard hold has expired automatically!
        $t31 = new DateTimeImmutable('2026-07-01 10:31:00');
        $this->assertTrue($this->ledger->isAvailable('1606', '2026-07-10', '2026-07-13', $t31));

        // Another guest can now hold those dates
        $newReservation = new Reservation(
            reservationUid: 'ovf_new_guest',
            propertyId: '1606',
            guestName: 'David Lopez',
            guestEmail: 'david@example.com',
            guestPhone: '+573204445566',
            checkIn: '2026-07-10',
            checkOut: '2026-07-13',
            totalPrice: 1150000.0,
            status: ReservationStatus::PENDING_PAYMENT,
            paymentMethodId: 'card',
            createdAt: $t31
        );

        $held = $this->ledger->hold($newReservation, $t31);
        $this->assertSame('ovf_new_guest', $held->reservationUid);
    }

    public function testExtendedVoucherHoldPreservesDatesFor72HoursPerAdr0003(): void
    {
        $createdAt = new DateTimeImmutable('2026-07-01 10:00:00');

        $reservation = new Reservation(
            reservationUid: 'ovf_efecty_hold',
            propertyId: '1707',
            guestName: 'Elena Morales',
            guestEmail: 'elena@example.com',
            guestPhone: '+573155556677',
            checkIn: '2026-07-20',
            checkOut: '2026-07-25',
            totalPrice: 2470000.0,
            status: ReservationStatus::PENDING_PAYMENT,
            paymentMethodId: 'efecty', // Offline Cash Voucher
            createdAt: $createdAt
        );

        $this->ledger->hold($reservation, $createdAt);

        // At T = 31 minutes: still holding! (unlike card payments)
        $t31m = new DateTimeImmutable('2026-07-01 10:31:00');
        $this->assertFalse($this->ledger->isAvailable('1707', '2026-07-20', '2026-07-25', $t31m));

        // At T = 24 hours: still holding!
        $t24h = new DateTimeImmutable('2026-07-02 10:00:00');
        $this->assertFalse($this->ledger->isAvailable('1707', '2026-07-20', '2026-07-25', $t24h));

        // At T = 71 hours: still holding!
        $t71h = new DateTimeImmutable('2026-07-04 09:00:00');
        $this->assertFalse($this->ledger->isAvailable('1707', '2026-07-20', '2026-07-25', $t71h));

        // At T = 73 hours: expired!
        $t73h = new DateTimeImmutable('2026-07-04 11:00:00');
        $this->assertTrue($this->ledger->isAvailable('1707', '2026-07-20', '2026-07-25', $t73h));
    }

    public function testConfirmTransitionsStateAndHoldsIndefinitely(): void
    {
        $createdAt = new DateTimeImmutable('2026-07-01 10:00:00');

        $reservation = new Reservation(
            reservationUid: 'ovf_settled',
            propertyId: '1606',
            guestName: 'Fernando Ruiz',
            guestEmail: 'fernando@example.com',
            guestPhone: '+573187778899',
            checkIn: '2026-08-01',
            checkOut: '2026-08-05',
            totalPrice: 1500000.0,
            status: ReservationStatus::PENDING_PAYMENT,
            paymentMethodId: 'card',
            createdAt: $createdAt
        );

        $this->ledger->hold($reservation, $createdAt);

        // Confirm reservation
        $confirmed = $this->ledger->confirm(
            reservationUid: 'ovf_settled',
            paymentId: 'mp_pay_998877',
            paymentStatus: 'approved',
            paymentDetail: 'accredited'
        );

        $this->assertTrue($confirmed->status->isConfirmed());
        $this->assertSame('mp_pay_998877', $confirmed->mercadopagoPaymentId);
        $this->assertSame('approved', $confirmed->paymentStatus);

        // Even 30 days later, confirmed reservation holds indefinitely
        $t30d = new DateTimeImmutable('2026-07-31 10:00:00');
        $this->assertFalse($this->ledger->isAvailable('1606', '2026-08-01', '2026-08-05', $t30d));
    }

    public function testCancelReleasesDatesBackToAvailability(): void
    {
        $createdAt = new DateTimeImmutable('2026-07-01 10:00:00');

        $reservation = new Reservation(
            reservationUid: 'ovf_to_cancel',
            propertyId: '1606',
            guestName: 'Gabriela Ortiz',
            guestEmail: 'gabriela@example.com',
            guestPhone: '+573123334455',
            checkIn: '2026-08-10',
            checkOut: '2026-08-15',
            totalPrice: 1850000.0,
            status: ReservationStatus::PENDING_PAYMENT,
            createdAt: $createdAt
        );

        $this->ledger->hold($reservation, $createdAt);
        $this->assertFalse($this->ledger->isAvailable('1606', '2026-08-10', '2026-08-15', $createdAt));

        $cancelled = $this->ledger->cancel('ovf_to_cancel', 'Guest request');
        $this->assertTrue($cancelled->status->isCancelled());

        // Immediately available again
        $this->assertTrue($this->ledger->isAvailable('1606', '2026-08-10', '2026-08-15', $createdAt));
    }

    public function testGetBlockedNightsMergesChannelBlocksAndActiveReservations(): void
    {
        $now = new DateTimeImmutable('2026-07-01 10:00:00');

        // Ephemeral channel block: 2026-07-05 to 2026-07-07 (nights 07-05, 07-06)
        $this->channelBlockSource->addBlock(new ChannelBlock(
            propertyId: '1606',
            startDate: '2026-07-05',
            endDate: '2026-07-07',
            source: 'airbnb'
        ));

        // Active direct reservation: 2026-07-08 to 2026-07-10 (nights 07-08, 07-09)
        $this->ledger->hold(new Reservation(
            reservationUid: 'ovf_merged',
            propertyId: '1606',
            guestName: 'Hugo Boss',
            guestEmail: 'hugo@example.com',
            guestPhone: '+573101234567',
            checkIn: '2026-07-08',
            checkOut: '2026-07-10',
            totalPrice: 780000.0,
            status: ReservationStatus::CONFIRMED,
            createdAt: $now
        ), $now);

        $blockedNights = $this->ledger->getBlockedNights('1606', $now);

        $this->assertSame([
            '2026-07-05',
            '2026-07-06',
            '2026-07-08',
            '2026-07-09',
        ], $blockedNights);
    }

    public function testMaintenanceBlocksBlockAvailabilityAndPreventHolds(): void
    {
        $now = new DateTimeImmutable('2026-08-01 10:00:00');

        $this->maintenanceBlockSource->addBlock(new MaintenanceBlock(
            propertyId: '1606',
            startDate: '2026-08-10',
            endDate: '2026-08-14',
            reason: 'Painting and AC repair',
            id: 10
        ));

        // Overlapping range is blocked
        $this->assertFalse($this->ledger->isAvailable('1606', '2026-08-11', '2026-08-13'));
        $reasons = $this->ledger->getConflictReasons('1606', '2026-08-11', '2026-08-13');
        $this->assertCount(1, $reasons);
        $this->assertStringContainsString('Dates overlap maintenance hold (Painting and AC repair: 2026-08-10 to 2026-08-14)', $reasons[0]);

        // findMaintenanceConflict identifies the block
        $conflict = $this->ledger->findMaintenanceConflict('1606', '2026-08-12', '2026-08-15');
        $this->assertNotNull($conflict);
        $this->assertSame('Painting and AC repair', $conflict->reason);

        // Attempting to hold overlapping dates throws ReservationConflictException
        $this->expectException(ReservationConflictException::class);
        $this->expectExceptionMessage('Dates overlap maintenance hold (Painting and AC repair: 2026-08-10 to 2026-08-14)');

        $this->ledger->hold(new Reservation(
            reservationUid: 'ovf_blocked_by_maintenance',
            propertyId: '1606',
            guestName: 'Blocked Guest',
            guestEmail: 'guest@example.com',
            guestPhone: '+573100000000',
            checkIn: '2026-08-12',
            checkOut: '2026-08-16',
            totalPrice: 500000.0,
            status: ReservationStatus::PENDING_PAYMENT,
            createdAt: $now
        ), $now);
    }

    public function testGetBlockedNightsIncludesMaintenanceNights(): void
    {
        $now = new DateTimeImmutable('2026-08-01 10:00:00');

        $this->maintenanceBlockSource->addBlock(new MaintenanceBlock(
            propertyId: '1707',
            startDate: '2026-08-05',
            endDate: '2026-08-08',
            reason: 'Flooring'
        ));

        $nights = $this->ledger->getBlockedNights('1707', $now);
        $this->assertSame(['2026-08-05', '2026-08-06', '2026-08-07'], $nights);
    }

    public function testChannelBlockAbsorptionForAirbnbSource(): void
    {
        $this->channelBlockSource->addBlock(new ChannelBlock(
            propertyId: '1606',
            startDate: '2026-09-01',
            endDate: '2026-09-05',
            source: 'airbnb'
        ));

        // Without absorbing source: dates are blocked
        $this->assertFalse($this->ledger->isAvailable('1606', '2026-09-01', '2026-09-05'));
        $this->assertNotEmpty($this->ledger->getConflictReasons('1606', '2026-09-01', '2026-09-05'));
        $this->assertNotNull($this->ledger->findChannelConflict('1606', '2026-09-01', '2026-09-05'));

        // With different absorbing source (e.g. 'vrbo'): still blocked
        $this->assertFalse($this->ledger->isAvailable('1606', '2026-09-01', '2026-09-05', null, 'vrbo'));

        // With absorbing source 'airbnb' (case-insensitive): absorbed!
        $this->assertTrue($this->ledger->isAvailable('1606', '2026-09-01', '2026-09-05', null, 'airbnb'));
        $this->assertTrue($this->ledger->isAvailable('1606', '2026-09-01', '2026-09-05', null, 'Airbnb'));
        $this->assertEmpty($this->ledger->getConflictReasons('1606', '2026-09-01', '2026-09-05', null, 'airbnb'));
        $this->assertNull($this->ledger->findChannelConflict('1606', '2026-09-01', '2026-09-05', 'airbnb'));

        // getBlockedNights with absorbingSource excludes absorbed nights
        $nightsWithAbsorption = $this->ledger->getBlockedNights('1606', null, 'airbnb');
        $this->assertEmpty($nightsWithAbsorption);
    }

    public function testHoldAbsorbsOverlappingChannelBlockForAirbnbReservation(): void
    {
        $now = new DateTimeImmutable('2026-09-01 10:00:00');

        $this->channelBlockSource->addBlock(new ChannelBlock(
            propertyId: '1606',
            startDate: '2026-09-10',
            endDate: '2026-09-15',
            source: 'airbnb'
        ));

        $airbnbRes = new Reservation(
            reservationUid: 'res-abnb-test1234',
            propertyId: '1606',
            guestName: 'Carlos Vives',
            guestEmail: 'carlos@example.com',
            guestPhone: '+573001112233',
            checkIn: '2026-09-10',
            checkOut: '2026-09-15',
            totalPrice: 2500000.0,
            status: ReservationStatus::CONFIRMED,
            source: 'airbnb',
            externalConfirmationCode: 'HM3ABC1234',
            channelBlockUid: 'ical-block-1234',
            createdAt: $now
        );

        $this->assertTrue($airbnbRes->isExternal());
        $this->assertTrue($airbnbRes->isAirbnb());

        // Hold must succeed by absorbing the channel block
        $held = $this->ledger->hold($airbnbRes, $now);
        $this->assertSame('res-abnb-test1234', $held->reservationUid);
        $this->assertSame('airbnb', $held->source);

        // Attempting to hold another airbnb reservation for overlapping dates absorbs channel block
        // but fails due to the active reservation already held in repository
        $secondAirbnbRes = new Reservation(
            reservationUid: 'res-abnb-test5678',
            propertyId: '1606',
            guestName: 'Second Airbnb Guest',
            guestEmail: 'second@example.com',
            guestPhone: '+573009990000',
            checkIn: '2026-09-12',
            checkOut: '2026-09-14',
            totalPrice: 1000000.0,
            status: ReservationStatus::CONFIRMED,
            source: 'airbnb',
            createdAt: $now
        );

        $this->expectException(ReservationConflictException::class);
        $this->expectExceptionMessage('Dates overlap active direct reservation res-abnb-test1234');
        $this->ledger->hold($secondAirbnbRes, $now);
    }

    public function testHoldRejectsWebReservationWhenAirbnbChannelBlockExists(): void
    {
        $now = new DateTimeImmutable('2026-09-01 10:00:00');

        $this->channelBlockSource->addBlock(new ChannelBlock(
            propertyId: '1606',
            startDate: '2026-09-10',
            endDate: '2026-09-15',
            source: 'airbnb'
        ));

        $webRes = new Reservation(
            reservationUid: 'ovf_web_conflict',
            propertyId: '1606',
            guestName: 'Direct Guest',
            guestEmail: 'direct@example.com',
            guestPhone: '+573009990000',
            checkIn: '2026-09-12',
            checkOut: '2026-09-14',
            totalPrice: 1000000.0,
            status: ReservationStatus::PENDING_PAYMENT,
            source: 'web',
            createdAt: $now
        );

        $this->expectException(ReservationConflictException::class);
        $this->expectExceptionMessage('Dates overlap external airbnb channel block (2026-09-10 to 2026-09-15)');
        $this->ledger->hold($webRes, $now);
    }

    public function testAirbnbReservationStillConflictedByMaintenanceHold(): void
    {
        $now = new DateTimeImmutable('2026-09-01 10:00:00');

        // Add both an Airbnb channel block and a Maintenance hold
        $this->channelBlockSource->addBlock(new ChannelBlock(
            propertyId: '1606',
            startDate: '2026-09-20',
            endDate: '2026-09-25',
            source: 'airbnb'
        ));

        $this->maintenanceBlockSource->addBlock(new MaintenanceBlock(
            propertyId: '1606',
            startDate: '2026-09-22',
            endDate: '2026-09-24',
            reason: 'Balcony Painting'
        ));

        // Even with absorbingSource = 'airbnb', the maintenance hold blocks availability
        $this->assertFalse($this->ledger->isAvailable('1606', '2026-09-20', '2026-09-25', $now, 'airbnb'));
        $reasons = $this->ledger->getConflictReasons('1606', '2026-09-20', '2026-09-25', $now, 'airbnb');
        $this->assertCount(1, $reasons);
        $this->assertStringContainsString('Balcony Painting', $reasons[0]);

        $airbnbRes = new Reservation(
            reservationUid: 'res-abnb-conflict-maint',
            propertyId: '1606',
            guestName: 'Guest With Conflict',
            guestEmail: 'conflict@example.com',
            guestPhone: '+573000001111',
            checkIn: '2026-09-20',
            checkOut: '2026-09-25',
            totalPrice: 2000000.0,
            status: ReservationStatus::CONFIRMED,
            source: 'airbnb',
            createdAt: $now
        );

        $this->expectException(ReservationConflictException::class);
        $this->expectExceptionMessage('Dates overlap maintenance hold (Balcony Painting: 2026-09-22 to 2026-09-24)');
        $this->ledger->hold($airbnbRes, $now);
    }

    public function testSelfConflictExclusionAllowsExtendingOwnStayWhileBlockingOtherConflicts(): void
    {
        $now = new DateTimeImmutable('2026-10-01 10:00:00');

        $resA = new Reservation(
            reservationUid: 'res-self-1',
            propertyId: '1606',
            guestName: 'Self Guest',
            guestEmail: 'self@example.com',
            guestPhone: '+573001112233',
            checkIn: '2026-10-10',
            checkOut: '2026-10-15',
            totalPrice: 1500000.0,
            status: ReservationStatus::CONFIRMED,
            createdAt: $now
        );
        $resB = new Reservation(
            reservationUid: 'res-other-2',
            propertyId: '1606',
            guestName: 'Other Guest',
            guestEmail: 'other@example.com',
            guestPhone: '+573004445566',
            checkIn: '2026-10-18',
            checkOut: '2026-10-22',
            totalPrice: 1200000.0,
            status: ReservationStatus::CONFIRMED,
            createdAt: $now
        );

        $this->repository->save($resA);
        $this->repository->save($resB);

        // Extending res-self-1 to 2026-10-16 overlaps its own original stay (10-10..10-15)
        // Without exclusion: unavailable
        $this->assertFalse($this->ledger->isAvailable('1606', '2026-10-10', '2026-10-16', $now));
        // With exclusion of 'res-self-1': available!
        $this->assertTrue($this->ledger->isAvailable('1606', '2026-10-10', '2026-10-16', $now, null, 'res-self-1'));
        $this->assertEmpty($this->ledger->getConflictReasons('1606', '2026-10-10', '2026-10-16', $now, null, 'res-self-1'));
        $this->assertNull($this->ledger->findReservationConflict('1606', '2026-10-10', '2026-10-16', $now, 'res-self-1'));

        // Extending res-self-1 into res-other-2 (2026-10-10..2026-10-20) still conflicts with res-other-2
        $this->assertFalse($this->ledger->isAvailable('1606', '2026-10-10', '2026-10-20', $now, null, 'res-self-1'));
        $reasons = $this->ledger->getConflictReasons('1606', '2026-10-10', '2026-10-20', $now, null, 'res-self-1');
        $this->assertCount(1, $reasons);
        $this->assertStringContainsString('res-other-2', $reasons[0]);
    }
}
