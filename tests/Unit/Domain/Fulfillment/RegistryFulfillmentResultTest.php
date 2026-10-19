<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Fulfillment;

use OceanViewFlats\Domain\Fulfillment\RegistryFulfillmentResult;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use PHPUnit\Framework\TestCase;

final class RegistryFulfillmentResultTest extends TestCase
{
    private function createReservation(): Reservation
    {
        return new Reservation(
            reservationUid: 'ovf_res_test',
            propertyId: '1606',
            guestName: 'John Doe',
            guestEmail: 'john@example.com',
            guestPhone: '+1 555 123 4567',
            checkIn: '2026-11-20',
            checkOut: '2026-11-23',
            totalPrice: 1500000.0,
            status: ReservationStatus::CONFIRMED
        );
    }

    public function testSuccessNamedConstructor(): void
    {
        $res = $this->createReservation();
        $result = RegistryFulfillmentResult::success(
            reservation: $res,
            doorCode: '123456#',
            guideUrl: '/guide/?code=ovf_res_test',
            hostReportDispatched: true,
            accessDispatchDispatched: true
        );

        $this->assertTrue($result->success);
        $this->assertSame($res, $result->reservation);
        $this->assertSame('123456#', $result->doorCode);
        $this->assertSame('/guide/?code=ovf_res_test', $result->guideUrl);
        $this->assertTrue($result->hostReportDispatched);
        $this->assertTrue($result->accessDispatchDispatched);
        $this->assertEmpty($result->errors);

        $array = $result->toArray();
        $this->assertTrue($array['success']);
        $this->assertSame('ovf_res_test', $array['reservation_code']);
        $this->assertSame('123456#', $array['door_code']);
        $this->assertSame('/guide/?code=ovf_res_test', $array['guide_url']);
        $this->assertTrue($array['host_report_dispatched']);
        $this->assertTrue($array['access_dispatch_dispatched']);
        $this->assertArrayNotHasKey('spreadsheet_synced', $array);
        $this->assertSame([], $array['errors']);
    }

    public function testValidationFailureNamedConstructor(): void
    {
        $result = RegistryFulfillmentResult::validationFailure(['Invalid guest name', 'Age out of range']);

        $this->assertFalse($result->success);
        $this->assertNull($result->reservation);
        $this->assertNull($result->doorCode);
        $this->assertNull($result->guideUrl);
        $this->assertFalse($result->hostReportDispatched);
        $this->assertFalse($result->accessDispatchDispatched);
        $this->assertSame(['Invalid guest name', 'Age out of range'], $result->errors);
    }

    public function testNotFoundNamedConstructor(): void
    {
        $result = RegistryFulfillmentResult::notFound('non_existent_code');

        $this->assertFalse($result->success);
        $this->assertNull($result->reservation);
        $this->assertSame(['Reservation not found'], $result->errors);
    }

    public function testSystemErrorNamedConstructor(): void
    {
        $result = RegistryFulfillmentResult::systemError('Database connection lost');

        $this->assertFalse($result->success);
        $this->assertSame(['Database connection lost'], $result->errors);
    }
}
