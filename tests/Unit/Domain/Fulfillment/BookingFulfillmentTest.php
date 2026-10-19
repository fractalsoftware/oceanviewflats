<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Fulfillment;

use DateTimeImmutable;
use OceanViewFlats\Domain\Fulfillment\BookingFulfillment;
use OceanViewFlats\Domain\Fulfillment\ConfirmationEmailRenderer;
use OceanViewFlats\Domain\Fulfillment\InMemoryEmailSender;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use PHPUnit\Framework\TestCase;

final class BookingFulfillmentTest extends TestCase
{
    private InMemoryEmailSender $emailSender;
    private ConfirmationEmailRenderer $renderer;
    private BookingFulfillment $fulfillment;

    protected function setUp(): void
    {
        $this->emailSender = new InMemoryEmailSender();
        $this->renderer = new ConfirmationEmailRenderer('https://www.oceanviewflats.com');
        $this->fulfillment = new BookingFulfillment(
            emailSender: $this->emailSender,
            renderer: $this->renderer,
            hostEmail: 'host@oceanviewflats.com'
        );
    }

    private function createReservation(
        string $lang = 'en',
        string $email = 'guest@example.com'
    ): Reservation {
        return new Reservation(
            reservationUid: 'ovf_ful_12345',
            propertyId: '1707',
            guestName: 'Jane Smith',
            guestEmail: $email,
            guestPhone: '+57 300 123 4567',
            checkIn: '2026-12-01',
            checkOut: '2026-12-05',
            totalPrice: 2400000.0,
            status: ReservationStatus::CONFIRMED,
            paymentMethodId: 'card',
            mercadopagoPaymentId: 'pay_abc_456',
            paymentStatus: 'approved',
            lang: $lang,
            createdAt: new DateTimeImmutable('2026-09-27 10:00:00')
        );
    }

    public function testFulfillConfirmationSucceedsEndToEnd(): void
    {
        $reservation = $this->createReservation();
        $result = $this->fulfillment->fulfillConfirmation($reservation, [
            'payment_id' => 'pay_abc_456'
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->isGuestEmailSent);
        $this->assertTrue($result->isHostEmailSent);
        $this->assertEmpty($result->errors);

        // Verify sent emails (guest + host)
        $this->assertSame(2, $this->emailSender->count());
        $sent = $this->emailSender->getSentMessages();

        $guestMessage = $sent[0];
        $this->assertSame('guest@example.com', $guestMessage['to']);
        $this->assertStringContainsString('ovf_ful_12345', $guestMessage['htmlBody']);
        $this->assertStringContainsString('/registry/', $guestMessage['htmlBody']);
        $this->assertStringNotContainsString('/guide/', $guestMessage['htmlBody']);

        $hostMessage = $sent[1];
        $this->assertSame('host@oceanviewflats.com', $hostMessage['to']);
        $this->assertStringContainsString('Jane Smith', $hostMessage['htmlBody']);
        $this->assertStringContainsString('ovf_ful_12345', $hostMessage['htmlBody']);
    }

    public function testFulfillHandlesEmailDispatchFailure(): void
    {
        $this->emailSender->setShouldFail(true);

        $reservation = $this->createReservation();
        $result = $this->fulfillment->fulfillConfirmation($reservation);

        $this->assertFalse($result->isGuestEmailSent);
        $this->assertFalse($result->isSuccess());
        $this->assertNotEmpty($result->errors);
    }
}
