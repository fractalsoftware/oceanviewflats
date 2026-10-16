<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Fulfillment;

use OceanViewFlats\Domain\Database\MigrationRunner;
use OceanViewFlats\Domain\Fulfillment\CondominiumClearance;
use OceanViewFlats\Domain\Fulfillment\PdoCondominiumClearanceRepository;
use OceanViewFlats\Domain\Reservation\Dashboard\AlertSeverity;
use OceanViewFlats\Domain\Reservation\Dashboard\AlertType;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoCondominiumClearanceRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoCondominiumClearanceRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        MigrationRunner::run($this->pdo, 'test_db');
        $this->repository = new PdoCondominiumClearanceRepository($this->pdo);
    }

    public function testSaveAndFindByReservationUid(): void
    {
        $clearance = CondominiumClearance::createPending(
            reservationUid: 'ovf_res_001',
            propertyId: '1707',
            requestPayload: ['step1' => ['apt' => '1707']]
        );

        $this->repository->save($clearance);

        $retrieved = $this->repository->findByReservationUid('ovf_res_001');
        $this->assertNotNull($retrieved);
        $this->assertSame('ovf_res_001', $retrieved->reservationUid);
        $this->assertSame('1707', $retrieved->propertyId);
        $this->assertSame(CondominiumClearance::STATUS_PENDING, $retrieved->status);
        $this->assertSame(['step1' => ['apt' => '1707']], $retrieved->requestPayload);
        $this->assertSame(1, $retrieved->attempts);
        $this->assertNull($retrieved->clearanceNumber);
        $this->assertNull($retrieved->errorMessage);
    }

    public function testSaveUpsertUpdatesExistingRecord(): void
    {
        $clearance = CondominiumClearance::createPending(
            reservationUid: 'ovf_res_002',
            propertyId: '1606',
            requestPayload: ['step1' => ['apt' => '1606']]
        );
        $this->repository->save($clearance);

        // Update to synced
        $synced = $clearance->markSynced('499', '2026-10-07 15:30:00');
        $this->repository->save($synced);

        $retrieved = $this->repository->findByReservationUid('ovf_res_002');
        $this->assertNotNull($retrieved);
        $this->assertSame(CondominiumClearance::STATUS_SYNCED, $retrieved->status);
        $this->assertSame('499', $retrieved->clearanceNumber);
        $this->assertSame('2026-10-07 15:30:00', $retrieved->syncedAt);
        $this->assertNull($retrieved->errorMessage);

        // Update to failed on retry
        $failed = $synced->recordAttempt(['retry' => 1])->markFailed('Portal connection timed out');
        $this->repository->save($failed);

        $retrievedFailed = $this->repository->findByReservationUid('ovf_res_002');
        $this->assertNotNull($retrievedFailed);
        $this->assertSame(CondominiumClearance::STATUS_FAILED, $retrievedFailed->status);
        $this->assertSame('Portal connection timed out', $retrievedFailed->errorMessage);
        $this->assertSame(2, $retrievedFailed->attempts);
        $this->assertSame(['retry' => 1], $retrievedFailed->requestPayload);
    }

    public function testFindByReservationUidReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByReservationUid('non_existent_uid');
        $this->assertNull($result);
    }

    public function testFindFailedClearancesFiltersAndLimits(): void
    {
        // 1. Synced clearance
        $c1 = CondominiumClearance::createPending('res_synced', '1707')->markSynced('100');
        $this->repository->save($c1);

        // 2. Failed clearance 1
        $c2 = CondominiumClearance::createPending('res_failed_1', '1707', null, '2026-10-07 10:00:00')
            ->markFailed('HTTP 500 error', '2026-10-07 10:00:00');
        $this->repository->save($c2);

        // 3. Failed clearance 2 (more recent)
        $c3 = CondominiumClearance::createPending('res_failed_2', '1606', null, '2026-10-07 11:00:00')
            ->markFailed('Timeout error', '2026-10-07 11:00:00');
        $this->repository->save($c3);

        // 4. Pending clearance
        $c4 = CondominiumClearance::createPending('res_pending', '1707');
        $this->repository->save($c4);

        $failed = $this->repository->findFailedClearances(10);
        $this->assertCount(2, $failed);
        $this->assertSame('res_failed_2', $failed[0]->reservationUid);
        $this->assertSame('res_failed_1', $failed[1]->reservationUid);

        // Test limit
        $limited = $this->repository->findFailedClearances(1);
        $this->assertCount(1, $limited);
        $this->assertSame('res_failed_2', $limited[0]->reservationUid);
    }

    public function testGetFailedClearanceAlertsSeverityFilteringAndResolution(): void
    {
        // 1. Seed reservations
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, status, total_price)
            VALUES 
                ('res_alert_past', '1606', 'Past Guest', 'past@example.com', '+573001234565', '2026-10-01', '2026-10-06', 'confirmed', 400.0),
                ('res_alert_departing_today', '1707', 'Departing Today Guest', 'dep@example.com', '+573001234566', '2026-10-04', '2026-10-07', 'confirmed', 450.0),
                ('res_alert_today', '1606', 'Today Guest', 'today@example.com', '+573001234567', '2026-10-07', '2026-10-10', 'confirmed', 500.0),
                ('res_alert_future', '1707', 'Future Guest', 'future@example.com', '+573001234568', '2026-10-15', '2026-10-20', 'confirmed', 800.0),
                ('res_alert_canc', '1606', 'Cancelled Guest', 'canc@example.com', '+573001234569', '2026-10-07', '2026-10-10', 'cancelled', 500.0),
                ('res_alert_synced', '1606', 'Synced Guest', 'synced@example.com', '+573001234570', '2026-10-07', '2026-10-10', 'confirmed', 500.0);
        ");

        // 2. Seed clearances
        $cPast = CondominiumClearance::createPending('res_alert_past', '1606')
            ->markFailed('Past portal failure');
        $this->repository->save($cPast);

        $cDepToday = CondominiumClearance::createPending('res_alert_departing_today', '1707')
            ->markFailed('Departing today portal failure');
        $this->repository->save($cDepToday);

        $cToday = CondominiumClearance::createPending('res_alert_today', '1606')
            ->markFailed('Portal connection timed out');
        $this->repository->save($cToday);

        $cFuture = CondominiumClearance::createPending('res_alert_future', '1707')
            ->markFailed('Portal HTTP 500 error');
        $this->repository->save($cFuture);

        $cCanc = CondominiumClearance::createPending('res_alert_canc', '1606')
            ->markFailed('Portal rejected');
        $this->repository->save($cCanc);

        $cSynced = CondominiumClearance::createPending('res_alert_synced', '1606')
            ->markSynced('CLEAR-777');
        $this->repository->save($cSynced);

        // 3. Test retrieving all alerts on 2026-10-07
        $alerts = $this->repository->getFailedClearanceAlerts('all', '2026-10-07');
        $this->assertCount(2, $alerts, 'Past (check_out < today), departing (check_out == today), cancelled, and synced reservations should not produce alerts');

        // Verify res_alert_today has CRITICAL severity (check_in <= today)
        $todayAlert = null;
        $futureAlert = null;
        foreach ($alerts as $a) {
            if ($a->actionPayload['reservationUid'] === 'res_alert_today') {
                $todayAlert = $a;
            } elseif ($a->actionPayload['reservationUid'] === 'res_alert_future') {
                $futureAlert = $a;
            }
        }

        $this->assertNotNull($todayAlert);
        $this->assertSame(AlertType::FAILED_CONDOMINIUM_CLEARANCE, $todayAlert->type);
        $this->assertSame(AlertSeverity::CRITICAL, $todayAlert->severity);
        $this->assertSame('Condominium Clearance Failed', $todayAlert->title);
        $this->assertSame('2026-10-07', $todayAlert->dueDate);
        $this->assertSame('Portal connection timed out', $todayAlert->actionPayload['errorMessage']);

        // Verify res_alert_future has WARNING severity (check_in > today)
        $this->assertNotNull($futureAlert);
        $this->assertSame(AlertSeverity::WARNING, $futureAlert->severity);
        $this->assertSame('2026-10-15', $futureAlert->dueDate);

        // 4. Test filtering by property
        $alerts1606 = $this->repository->getFailedClearanceAlerts('1606', '2026-10-07');
        $this->assertCount(1, $alerts1606);
        $this->assertSame('res_alert_today', $alerts1606[0]->actionPayload['reservationUid']);

        $alerts1707 = $this->repository->getFailedClearanceAlerts('1707', '2026-10-07');
        $this->assertCount(1, $alerts1707);
        $this->assertSame('res_alert_future', $alerts1707[0]->actionPayload['reservationUid']);

        // 5. Test resolution when clearance status transitions to 'synced'
        $resolvedToday = $cToday->markSynced('CLEAR-888');
        $this->repository->save($resolvedToday);

        $alertsAfterResolution = $this->repository->getFailedClearanceAlerts('all', '2026-10-07');
        $this->assertCount(1, $alertsAfterResolution);
        $this->assertSame('res_alert_future', $alertsAfterResolution[0]->actionPayload['reservationUid']);
    }
}
