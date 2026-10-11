<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Infrastructure\Reservation;

use OceanViewFlats\Domain\Fulfillment\BookingFulfillment;
use OceanViewFlats\Domain\Fulfillment\BookingFulfillmentInterface;
use OceanViewFlats\Domain\Fulfillment\FulfillmentResult;
use OceanViewFlats\Domain\Reservation\DraftPaymentDetails;
use OceanViewFlats\Domain\Reservation\Port\LifecycleEventPublisherPort;
use OceanViewFlats\Domain\Reservation\PrimaryGuest;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationDraft;
use OceanViewFlats\Domain\Reservation\ReservationLifecycleEngine;
use OceanViewFlats\Domain\Reservation\ReservationLifecycleEngineInterface;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use OceanViewFlats\Infrastructure\Reservation\ReservationLifecycleEngineFactory;
use OceanViewFlats\Infrastructure\Reservation\TransactionalLifecycleEventPublisherAdapter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ReservationLifecycleEngineFactoryTest extends TestCase
{
    public function testCreateReturnsEngineWithDefaultBookingFulfillment(): void
    {
        $engine = ReservationLifecycleEngineFactory::create();

        $this->assertInstanceOf(ReservationLifecycleEngineInterface::class, $engine);
        $this->assertInstanceOf(ReservationLifecycleEngine::class, $engine);

        $engineRef = new ReflectionClass($engine);
        $eventPublisherPortProp = $engineRef->getProperty('eventPublisherPort');
        $eventPublisher = $eventPublisherPortProp->getValue($engine);

        $this->assertInstanceOf(TransactionalLifecycleEventPublisherAdapter::class, $eventPublisher);

        $adapterRef = new ReflectionClass($eventPublisher);
        $bookingFulfillmentProp = $adapterRef->getProperty('bookingFulfillment');
        $bookingFulfillment = $bookingFulfillmentProp->getValue($eventPublisher);

        $this->assertInstanceOf(BookingFulfillmentInterface::class, $bookingFulfillment);
        $this->assertInstanceOf(BookingFulfillment::class, $bookingFulfillment);
    }

    public function testCreateWiresExplicitBookingFulfillmentWhenProvided(): void
    {
        $mockFulfillment = $this->createMock(BookingFulfillmentInterface::class);

        $engine = ReservationLifecycleEngineFactory::create(null, [
            'bookingFulfillment' => $mockFulfillment,
        ]);

        $this->assertInstanceOf(ReservationLifecycleEngine::class, $engine);

        $engineRef = new ReflectionClass($engine);
        $eventPublisher = $engineRef->getProperty('eventPublisherPort')->getValue($engine);

        $this->assertInstanceOf(TransactionalLifecycleEventPublisherAdapter::class, $eventPublisher);

        $adapterRef = new ReflectionClass($eventPublisher);
        $bookingFulfillment = $adapterRef->getProperty('bookingFulfillment')->getValue($eventPublisher);

        $this->assertSame($mockFulfillment, $bookingFulfillment);
    }

    public function testCreateUsesExplicitEventPublisherPortWhenProvided(): void
    {
        $mockPublisher = $this->createMock(LifecycleEventPublisherPort::class);

        $engine = ReservationLifecycleEngineFactory::create(null, [
            'eventPublisherPort' => $mockPublisher,
        ]);

        $this->assertInstanceOf(ReservationLifecycleEngine::class, $engine);

        $engineRef = new ReflectionClass($engine);
        $eventPublisher = $engineRef->getProperty('eventPublisherPort')->getValue($engine);

        $this->assertSame($mockPublisher, $eventPublisher);
    }

    public function testEngineConfirmOrRecordInvokesWiredBookingFulfillment(): void
    {
        $mockFulfillment = $this->createMock(BookingFulfillmentInterface::class);
        $mockFulfillment->expects($this->once())
            ->method('fulfillConfirmation')
            ->with(
                $this->callback(function (Reservation $reservation): bool {
                    return $reservation->guestEmail === 'guest@example.com'
                        && $reservation->status === ReservationStatus::CONFIRMED
                        && $reservation->doorCode === null; // Door code masked before registry
                }),
                $this->callback(function (array $extra): bool {
                    return ($extra['payment_id'] ?? null) === 'pay_mp_test_123'
                        && ($extra['payment_status'] ?? null) === 'approved';
                })
            )
            ->willReturn(new FulfillmentResult(true, true, true));

        $engine = ReservationLifecycleEngineFactory::create(null, [
            'bookingFulfillment' => $mockFulfillment,
        ]);

        $draft = ReservationDraft::direct(
            propertyId: '1606',
            checkIn: '2026-10-01',
            checkOut: '2026-10-05',
            primaryGuest: PrimaryGuest::create('Alice Test', 'guest@example.com', '+573001234567'),
            payment: new DraftPaymentDetails(
                paymentMethodId: 'credit_card',
                mercadopagoPaymentId: 'pay_mp_test_123',
                paymentStatus: 'approved'
            ),
            preMarkRegistry: false,
            sendConfirmationEmail: true
        );

        $result = $engine->confirmOrRecord($draft);

        $this->assertSame(ReservationStatus::CONFIRMED, $result->reservation->status);
        $this->assertSame('pay_mp_test_123', $result->reservation->mercadopagoPaymentId);
    }

    public function testDefaultEngineExecutesWithoutErrorsWhenBookingFulfillmentNotSupplied(): void
    {
        $engine = ReservationLifecycleEngineFactory::create();

        $draft = ReservationDraft::direct(
            propertyId: '1606',
            checkIn: '2026-10-10',
            checkOut: '2026-10-14',
            primaryGuest: PrimaryGuest::create('Bob Default', 'bob@example.com', '+573009998877'),
            payment: new DraftPaymentDetails(
                paymentMethodId: 'credit_card',
                mercadopagoPaymentId: 'pay_default_1',
                paymentStatus: 'approved'
            ),
            preMarkRegistry: false,
            sendConfirmationEmail: true
        );

        $result = $engine->confirmOrRecord($draft);

        $this->assertSame(ReservationStatus::CONFIRMED, $result->reservation->status);
    }
}
