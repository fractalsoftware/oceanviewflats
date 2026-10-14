<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Reservation;

use DateTimeImmutable;
use OceanViewFlats\Domain\Reservation\InMemoryReservationRepository;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use PDO;
use PHPUnit\Framework\TestCase;

final class ReservationRepositoryTest extends TestCase
{
    private function createSampleReservation(
        string $uid = 'ovf_sample_123',
        string $propertyId = '1606',
        string $checkIn = '2026-11-15',
        string $checkOut = '2026-11-20'
    ): Reservation {
        return new Reservation(
            reservationUid: $uid,
            propertyId: $propertyId,
            guestName: 'Jane Doe',
            guestEmail: 'jane@example.com',
            guestPhone: '+57 300 123 4567',
            checkIn: $checkIn,
            checkOut: $checkOut,
            totalPrice: 1500000.0,
            status: ReservationStatus::CONFIRMED,
            paymentMethodId: 'card_visa',
            mercadopagoPaymentId: 'mp_pay_12345',
            paymentStatus: 'approved',
            paymentDetail: 'accredited',
            lang: 'en',
            registryCompleted: false,
            registryCompletedAt: null,
            createdAt: new DateTimeImmutable('2026-10-01 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-10-01 10:00:00')
        );
    }

    private function createSqlitePdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE reservations (
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
            max_guests INTEGER NOT NULL DEFAULT 6,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
        return $pdo;
    }

    public function testInMemoryRepositoryMarkRegistryCompleted(): void
    {
        $repo = new InMemoryReservationRepository();
        $reservation = $this->createSampleReservation('ovf_inmem_1');
        $repo->save($reservation);

        $this->assertFalse($repo->findByUid('ovf_inmem_1')->registryCompleted);
        $this->assertNull($repo->findByUid('ovf_inmem_1')->registryCompletedAt);

        $now = new DateTimeImmutable('2026-11-10 14:30:00');
        $updated = $repo->markRegistryCompleted('ovf_inmem_1', $now);

        $this->assertNotNull($updated);
        $this->assertTrue($updated->registryCompleted);
        $fetched = $repo->findByUid('ovf_inmem_1');
        $this->assertNotNull($fetched);
        $this->assertTrue($fetched->registryCompleted);
        $this->assertSame($now->format('Y-m-d H:i:s'), $fetched->registryCompletedAt?->format('Y-m-d H:i:s'));
    }

    public function testInMemoryRepositoryFindByPropertyAndDates(): void
    {
        $repo = new InMemoryReservationRepository();
        $reservation = $this->createSampleReservation('ovf_inmem_2');
        $repo->save($reservation);

        $found = $repo->findByPropertyAndDates('1606', '2026-11-15', '2026-11-20');
        $this->assertNotNull($found);
        $this->assertSame('ovf_inmem_2', $found->reservationUid);

        $notFound = $repo->findByPropertyAndDates('1606', '2026-11-15', '2026-11-22');
        $this->assertNull($notFound);
    }

    public function testPdoRepositorySaveAndFind(): void
    {
        $pdo = $this->createSqlitePdo();
        $repo = new PdoReservationRepository($pdo);
        $reservation = $this->createSampleReservation('ovf_pdo_1');

        $repo->save($reservation);

        $fetched = $repo->findByUid('ovf_pdo_1');
        $this->assertNotNull($fetched);
        $this->assertSame('ovf_pdo_1', $fetched->reservationUid);
        $this->assertSame('Jane Doe', $fetched->guestName);
        $this->assertSame('1606', $fetched->propertyId);
        $this->assertFalse($fetched->registryCompleted);
        $this->assertNull($fetched->registryCompletedAt);
    }

    public function testPdoRepositoryMarkRegistryCompleted(): void
    {
        $pdo = $this->createSqlitePdo();
        $repo = new PdoReservationRepository($pdo);
        $reservation = $this->createSampleReservation('ovf_pdo_2');
        $repo->save($reservation);

        $now = new DateTimeImmutable('2026-11-12 09:15:00');
        $updated = $repo->markRegistryCompleted('ovf_pdo_2', $now, '0765432#');

        $this->assertNotNull($updated);
        $this->assertTrue($updated->registryCompleted);
        $this->assertSame('0765432#', $updated->doorCode);
        $fetched = $repo->findByUid('ovf_pdo_2');
        $this->assertNotNull($fetched);
        $this->assertTrue($fetched->registryCompleted);
        $this->assertSame($now->format('Y-m-d H:i:s'), $fetched->registryCompletedAt?->format('Y-m-d H:i:s'));
        $this->assertSame('0765432#', $fetched->doorCode);
    }

    public function testPdoRepositoryFindByPropertyAndDates(): void
    {
        $pdo = $this->createSqlitePdo();
        $repo = new PdoReservationRepository($pdo);
        $reservation = $this->createSampleReservation('ovf_pdo_3');
        $repo->save($reservation);

        $found = $repo->findByPropertyAndDates('1606', '2026-11-15', '2026-11-20');
        $this->assertNotNull($found);
        $this->assertSame('ovf_pdo_3', $found->reservationUid);

        $notFound = $repo->findByPropertyAndDates('1707', '2026-11-15', '2026-11-20');
        $this->assertNull($notFound);
    }

    public function testInMemoryRepositoryUpdateDoorCode(): void
    {
        $repo = new InMemoryReservationRepository();
        $reservation = $this->createSampleReservation('ovf_inmem_pin');
        $repo->save($reservation);

        $updated = $repo->updateDoorCode('ovf_inmem_pin', '0999888#');
        $this->assertNotNull($updated);
        $this->assertSame('0999888#', $updated->doorCode);

        $fetched = $repo->findByUid('ovf_inmem_pin');
        $this->assertNotNull($fetched);
        $this->assertSame('0999888#', $fetched->doorCode);

        $notFound = $repo->updateDoorCode('ovf_nonexistent', '0999888#');
        $this->assertNull($notFound);
    }

    public function testPdoRepositoryUpdateDoorCode(): void
    {
        $pdo = $this->createSqlitePdo();
        $repo = new PdoReservationRepository($pdo);
        $reservation = $this->createSampleReservation('ovf_pdo_pin');
        $repo->save($reservation);

        $updated = $repo->updateDoorCode('ovf_pdo_pin', '0111222#');
        $this->assertNotNull($updated);
        $this->assertSame('0111222#', $updated->doorCode);

        $fetched = $repo->findByUid('ovf_pdo_pin');
        $this->assertNotNull($fetched);
        $this->assertSame('0111222#', $fetched->doorCode);

        $notFound = $repo->updateDoorCode('ovf_nonexistent', '0111222#');
        $this->assertNull($notFound);
    }

    public function testInMemoryRepositoryRecordRefund(): void
    {
        $repo = new InMemoryReservationRepository();
        $repo->recordRefund([
            'reservation_uid' => 'ovf_refund_1',
            'amount' => 50000.0,
            'reason' => 'Guest cancelled early',
        ]);

        $refunds = $repo->getRefunds();
        $this->assertCount(1, $refunds);
        $this->assertSame('ovf_refund_1', $refunds[0]['reservation_uid']);
        $this->assertSame(50000.0, $refunds[0]['amount']);
        $this->assertSame('Guest cancelled early', $refunds[0]['reason']);
    }

    public function testPdoRepositoryRecordRefund(): void
    {
        $pdo = $this->createSqlitePdo();
        $pdo->exec("CREATE TABLE reservation_refunds (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reservation_uid TEXT NOT NULL,
            mercadopago_refund_id TEXT,
            mercadopago_payment_id TEXT NOT NULL DEFAULT 'offline',
            amount REAL NOT NULL DEFAULT 0.0,
            status TEXT NOT NULL DEFAULT 'approved',
            reason TEXT NOT NULL,
            source TEXT NOT NULL DEFAULT 'admin_pms',
            admin_user_id INTEGER,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");

        $repo = new PdoReservationRepository($pdo);
        $repo->recordRefund([
            'reservation_uid' => 'ovf_refund_pdo',
            'mercadopago_refund_id' => 'mp_ref_123',
            'mercadopago_payment_id' => 'mp_pay_456',
            'amount' => 120000.0,
            'status' => 'approved',
            'reason' => 'Duplicate booking',
            'source' => 'admin_pms',
            'admin_user_id' => 1,
        ]);

        $stmt = $pdo->query("SELECT * FROM reservation_refunds WHERE reservation_uid = 'ovf_refund_pdo'");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $rows);
        $this->assertSame('120000', (string) (int) $rows[0]['amount']);
        $this->assertSame('mp_ref_123', $rows[0]['mercadopago_refund_id']);
        $this->assertSame('Duplicate booking', $rows[0]['reason']);
    }

    public function testReservationPersistsAndValidatesMaxGuestsInRepositories(): void
    {
        $pdo = $this->createSqlitePdo();
        $pdoRepo = new PdoReservationRepository($pdo);
        $inMemRepo = new InMemoryReservationRepository();

        $defaultRes = $this->createSampleReservation('ovf_default_max');
        $this->assertSame(6, $defaultRes->maxGuests);
        $pdoRepo->save($defaultRes);
        $inMemRepo->save($defaultRes);

        $pdoDefault = $pdoRepo->findByUid('ovf_default_max');
        $inMemDefault = $inMemRepo->findByUid('ovf_default_max');
        $this->assertNotNull($pdoDefault);
        $this->assertNotNull($inMemDefault);
        $this->assertSame(6, $pdoDefault->maxGuests);
        $this->assertSame(6, $inMemDefault->maxGuests);

        $overrideRes = $defaultRes->withMaxGuests(10);
        $this->assertSame(10, $overrideRes->maxGuests);
        $this->assertSame(10, $overrideRes->toArray()['max_guests']);

        $pdoRepo->save($overrideRes);
        $inMemRepo->save($overrideRes);

        $pdoUpdated = $pdoRepo->findByUid('ovf_default_max');
        $inMemUpdated = $inMemRepo->findByUid('ovf_default_max');
        $this->assertNotNull($pdoUpdated);
        $this->assertNotNull($inMemUpdated);
        $this->assertSame(10, $pdoUpdated->maxGuests);
        $this->assertSame(10, $inMemUpdated->maxGuests);

        $fromArray = Reservation::fromArray($overrideRes->toArray());
        $this->assertSame(10, $fromArray->maxGuests);
    }

    public function testReservationRejectsMaxGuestsOutsideRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Reservation max_guests must be between 1 and 12');

        $this->createSampleReservation('ovf_bad_max')->withMaxGuests(13);
    }

    public function testFindOverlappingActiveExcludesSelfAndSaveUpdatesDatesAndProperty(): void
    {
        $pdo = $this->createSqlitePdo();
        $pdoRepo = new PdoReservationRepository($pdo);
        $inMemRepo = new InMemoryReservationRepository();

        $res1 = $this->createSampleReservation('ovf_res_1', '1606', '2026-11-10', '2026-11-15');
        $res2 = $this->createSampleReservation('ovf_res_2', '1606', '2026-11-18', '2026-11-22');
        $pdoRepo->save($res1);
        $pdoRepo->save($res2);
        $inMemRepo->save($res1);
        $inMemRepo->save($res2);

        // Extending ovf_res_1 to 2026-11-16 excluding itself returns empty conflicts
        $this->assertEmpty($pdoRepo->findOverlappingActive('1606', '2026-11-10', '2026-11-16', null, 30, 72, 'ovf_res_1'));
        $this->assertEmpty($inMemRepo->findOverlappingActive('1606', '2026-11-10', '2026-11-16', null, 30, 72, 'ovf_res_1'));

        // Extending ovf_res_1 to 2026-11-20 excluding itself still detects ovf_res_2
        $pdoConflicts = $pdoRepo->findOverlappingActive('1606', '2026-11-10', '2026-11-20', null, 30, 72, 'ovf_res_1');
        $inMemConflicts = $inMemRepo->findOverlappingActive('1606', '2026-11-10', '2026-11-20', null, 30, 72, 'ovf_res_1');
        $this->assertCount(1, $pdoConflicts);
        $this->assertSame('ovf_res_2', $pdoConflicts[0]->reservationUid);
        $this->assertCount(1, $inMemConflicts);
        $this->assertSame('ovf_res_2', $inMemConflicts[0]->reservationUid);

        // Updating property_id, check_in, and check_out via save() persists in PDO
        $relocated = Reservation::fromArray(array_merge($res1->toArray(), [
            'property_id' => '1707',
            'check_in' => '2026-11-12',
            'check_out' => '2026-11-17',
        ]));
        $savedPdo = $pdoRepo->save($relocated);
        $this->assertSame('1707', $savedPdo->propertyId);
        $this->assertSame('2026-11-12', $savedPdo->checkIn);
        $this->assertSame('2026-11-17', $savedPdo->checkOut);
    }
}

