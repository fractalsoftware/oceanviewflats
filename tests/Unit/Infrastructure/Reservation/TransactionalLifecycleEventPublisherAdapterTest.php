<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Infrastructure\Reservation;

use DateTimeImmutable;
use OceanViewFlats\Domain\Fulfillment\BookingFulfillmentInterface;
use OceanViewFlats\Domain\Fulfillment\FulfillmentResult;
use OceanViewFlats\Domain\Reservation\ActorContext;
use OceanViewFlats\Domain\Reservation\Event\ReservationConfirmedEvent;
use OceanViewFlats\Domain\Reservation\Port\AuditPort;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use OceanViewFlats\Infrastructure\Reservation\TransactionalLifecycleEventPublisherAdapter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TransactionalLifecycleEventPublisherAdapterTest extends TestCase
{
    private function createReservation(
        bool $registryCompleted = false,
        ?string $doorCode = '1234#*',
        ?string $paymentId = 'pay_mp_123',
        ?string $paymentStatus = 'approved'
    ): Reservation {
        return new Reservation(
            reservationUid: 'ovf_adapter_123',
            propertyId: '1707',
            guestName: 'Jane Doe',
            guestEmail: 'jane@example.com',
            guestPhone: '+57 300 123 4567',
            checkIn: '2026-11-10',
            checkOut: '2026-11-15',
            totalPrice: 1500000.0,
            status: ReservationStatus::CONFIRMED,
            paymentMethodId: 'card',
            mercadopagoPaymentId: $paymentId,
            paymentStatus: $paymentStatus,
            lang: 'en',
            createdAt: new DateTimeImmutable('2026-10-01 10:00:00'),
            registryCompleted: $registryCompleted,
            doorCode: $doorCode
        );
    }

    public function testPublishConfirmedMasksDoorCodeWhenRegistryCompletedIsFalse(): void
    {
        $bookingFulfillment = $this->createMock(BookingFulfillmentInterface::class);
        $bookingFulfillment->expects($this->once())
            ->method('fulfillConfirmation')
            ->with(
                $this->callback(function (Reservation $res): bool {
                    return $res->doorCode === null && $res->registryCompleted === false;
                }),
                $this->anything()
            )
            ->willReturn(new FulfillmentResult(true, true));

        $adapter = new TransactionalLifecycleEventPublisherAdapter(
            bookingFulfillment: $bookingFulfillment
        );

        $reservation = $this->createReservation(registryCompleted: false, doorCode: '9876#*');
        $event = new ReservationConfirmedEvent(
            reservation: $reservation,
            actorContext: ActorContext::system()
        );

        $adapter->publishConfirmed($event);
    }

    public function testPublishConfirmedPreservesDoorCodeWhenRegistryCompletedIsTrue(): void
    {
        $bookingFulfillment = $this->createMock(BookingFulfillmentInterface::class);
        $bookingFulfillment->expects($this->once())
            ->method('fulfillConfirmation')
            ->with(
                $this->callback(function (Reservation $res): bool {
                    return $res->doorCode === '9876#*' && $res->registryCompleted === true;
                }),
                $this->anything()
            )
            ->willReturn(new FulfillmentResult(true, true));

        $adapter = new TransactionalLifecycleEventPublisherAdapter(
            bookingFulfillment: $bookingFulfillment
        );

        $reservation = $this->createReservation(registryCompleted: true, doorCode: '9876#*');
        $event = new ReservationConfirmedEvent(
            reservation: $reservation,
            actorContext: ActorContext::system()
        );

        $adapter->publishConfirmed($event);
    }

    public function testPublishConfirmedForwardsPaymentMetadataInExtra(): void
    {
        $bookingFulfillment = $this->createMock(BookingFulfillmentInterface::class);
        $bookingFulfillment->expects($this->once())
            ->method('fulfillConfirmation')
            ->with(
                $this->isInstanceOf(Reservation::class),
                $this->identicalTo([
                    'payment_id' => 'pay_custom_789',
                    'payment_status' => 'approved',
                ])
            )
            ->willReturn(new FulfillmentResult(true, true));

        $adapter = new TransactionalLifecycleEventPublisherAdapter(
            bookingFulfillment: $bookingFulfillment
        );

        $reservation = $this->createReservation(
            paymentId: 'pay_custom_789',
            paymentStatus: 'approved'
        );
        $event = new ReservationConfirmedEvent(
            reservation: $reservation,
            actorContext: ActorContext::system()
        );

        $adapter->publishConfirmed($event);
    }

    public function testPublishConfirmedFiltersNullPaymentMetadataInExtra(): void
    {
        $bookingFulfillment = $this->createMock(BookingFulfillmentInterface::class);
        $bookingFulfillment->expects($this->once())
            ->method('fulfillConfirmation')
            ->with(
                $this->isInstanceOf(Reservation::class),
                $this->identicalTo([])
            )
            ->willReturn(new FulfillmentResult(true, true));

        $adapter = new TransactionalLifecycleEventPublisherAdapter(
            bookingFulfillment: $bookingFulfillment
        );

        $reservation = $this->createReservation(
            paymentId: null,
            paymentStatus: null
        );
        $event = new ReservationConfirmedEvent(
            reservation: $reservation,
            actorContext: ActorContext::system()
        );

        $adapter->publishConfirmed($event);
    }

    public function testPublishConfirmedSkipsFulfillmentWhenSendConfirmationEmailIsFalse(): void
    {
        $bookingFulfillment = $this->createMock(BookingFulfillmentInterface::class);

        $bookingFulfillment->expects($this->never())->method('fulfillConfirmation');

        $adapter = new TransactionalLifecycleEventPublisherAdapter(
            bookingFulfillment: $bookingFulfillment
        );

        $reservation = $this->createReservation();
        $event = new ReservationConfirmedEvent(
            reservation: $reservation,
            actorContext: ActorContext::system(),
            sendConfirmationEmail: false
        );

        $adapter->publishConfirmed($event);
    }

    public function testPublishConfirmedCatchesAndSuppressesBookingFulfillmentException(): void
    {
        $bookingFulfillment = $this->createMock(BookingFulfillmentInterface::class);
        $bookingFulfillment->expects($this->once())
            ->method('fulfillConfirmation')
            ->willThrowException(new RuntimeException('Downstream API connection timeout'));

        $adapter = new TransactionalLifecycleEventPublisherAdapter(
            bookingFulfillment: $bookingFulfillment
        );

        $reservation = $this->createReservation();
        $event = new ReservationConfirmedEvent(
            reservation: $reservation,
            actorContext: ActorContext::system()
        );

        // Should not throw and exception should be suppressed
        $adapter->publishConfirmed($event);
    }

    public function testPublishConfirmedNoOpsWhenNoFulfillmentServicesInjected(): void
    {
        $this->expectNotToPerformAssertions();

        $adapter = new TransactionalLifecycleEventPublisherAdapter();

        $reservation = $this->createReservation();
        $event = new ReservationConfirmedEvent(
            reservation: $reservation,
            actorContext: ActorContext::system()
        );

        $adapter->publishConfirmed($event);
    }

    public function testPublishConfirmedRecordsAuditEntryWhenFulfillmentFailsAndAuditPortPresent(): void
    {
        $reservation = $this->createReservation();
        $actorContext = ActorContext::system();
        $event = new ReservationConfirmedEvent(
            reservation: $reservation,
            actorContext: $actorContext
        );

        $failedResult = new FulfillmentResult(
            isGuestEmailSent: false,
            isHostEmailSent: true,
            errors: ['SMTP timeout error']
        );

        $bookingFulfillment = $this->createMock(BookingFulfillmentInterface::class);
        $bookingFulfillment->expects($this->once())
            ->method('fulfillConfirmation')
            ->willReturn($failedResult);

        $auditPort = $this->createMock(AuditPort::class);
        $auditPort->expects($this->once())
            ->method('record')
            ->with(
                'booking_fulfillment_failed',
                'reservation',
                $reservation->reservationUid,
                null,
                [
                    'errors' => ['SMTP timeout error'],
                    'guest_email_sent' => false,
                    'host_email_sent' => true,
                ],
                $actorContext
            );

        $adapter = new TransactionalLifecycleEventPublisherAdapter(
            bookingFulfillment: $bookingFulfillment,
            auditPort: $auditPort
        );

        $adapter->publishConfirmed($event);
    }
}
