<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Service;

use DateTimeImmutable;
use OceanViewFlats\Admin\Repository\AdminCalendarBlockRepository;
use OceanViewFlats\Admin\Repository\AdminRateRepository;
use OceanViewFlats\Admin\Repository\AdminReservationRepository;
use OceanViewFlats\Admin\Service\DashboardQueryService;
use InvalidArgumentException;
use OceanViewFlats\Domain\Fulfillment\CondominiumClearance;
use OceanViewFlats\Domain\Fulfillment\CondominiumClearanceRepositoryInterface;
use OceanViewFlats\Domain\Quote\PropertyRatesConfig;
use OceanViewFlats\Domain\Reservation\ChannelBlock;
use OceanViewFlats\Domain\Reservation\ChannelSyncStatus;
use OceanViewFlats\Domain\Reservation\Dashboard\AlertSeverity;
use OceanViewFlats\Domain\Reservation\Dashboard\AlertType;
use OceanViewFlats\Domain\Reservation\Dashboard\DayDetailViewData;
use OceanViewFlats\Domain\Reservation\Dashboard\DayPropertyDetail;
use OceanViewFlats\Domain\Reservation\Dashboard\MovementType;
use OceanViewFlats\Domain\Reservation\Dashboard\OperationalAlert;
use OceanViewFlats\Domain\Reservation\InboundChannelSyncServiceInterface;
use OceanViewFlats\Domain\Reservation\ReservationLedgerInterface;
use PDO;
use PHPUnit\Framework\TestCase;

final class DashboardQueryServiceTest extends TestCase
{
    private PDO $pdo;
    private AdminReservationRepository $reservationRepo;
    private AdminCalendarBlockRepository $calendarBlockRepo;
    private AdminRateRepository $rateRepo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec('
            CREATE TABLE admin_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE
            );

            CREATE TABLE reservations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_uid TEXT NOT NULL UNIQUE,
                property_id TEXT NOT NULL,
                guest_name TEXT NOT NULL,
                guest_email TEXT NOT NULL,
                guest_phone TEXT NOT NULL,
                check_in TEXT NOT NULL,
                check_out TEXT NOT NULL,
                total_price NUMERIC NOT NULL,
                status TEXT NOT NULL DEFAULT "pending_payment",
                source TEXT NOT NULL DEFAULT "web",
                external_confirmation_code TEXT DEFAULT NULL,
                channel_block_uid TEXT DEFAULT NULL,
                registry_completed INTEGER NOT NULL DEFAULT 0,
                door_code TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE calendar_blocks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                property_id TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                reason TEXT NOT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE property_rates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                property_id TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                season_name TEXT NOT NULL,
                price_per_night REAL NOT NULL,
                min_stay INTEGER NOT NULL DEFAULT 2,
                cleaning_fee REAL NOT NULL DEFAULT 0.0,
                resort_fee REAL NOT NULL DEFAULT 0.0,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            INSERT INTO admin_users (id, name, email) VALUES (1, "Manuel Admin", "admin@oceanviewflats.com");
        ');

        $this->reservationRepo = new AdminReservationRepository($this->pdo);
        $this->calendarBlockRepo = new AdminCalendarBlockRepository($this->pdo);
        $this->rateRepo = new AdminRateRepository($this->pdo, PropertyRatesConfig::createDefault());
    }

    public function testGetDashboardHubDataAssemblesAllMetrics(): void
    {
        $now = new DateTimeImmutable('2026-10-10');

        // 1. Seed reservations:
        // - In-house stay: 2026-10-08 to 2026-10-12 (Apt 1606, registry completed)
        // - Turnover on 2026-10-10 (Apt 1707): Guest A departs, Guest B checks in (missing registry)
        // - Future check-in on 2026-10-11 (Apt 1606, missing registry)
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, registry_completed, door_code
            ) VALUES
            ('res-staying', '1606', 'Staying Guest', 'stay@test.com', '+573001', '2026-10-08', '2026-10-12', 100, 'confirmed', 'web', 1, '1111#'),
            ('res-dep-today', '1707', 'Departing Guest', 'dep@test.com', '+573002', '2026-10-05', '2026-10-10', 100, 'confirmed', 'web', 1, '2222#'),
            ('res-arr-today', '1707', 'Arriving Guest', 'arr@test.com', '+573003', '2026-10-10', '2026-10-14', 100, 'confirmed', 'web', 0, NULL),
            ('res-tomorrow', '1606', 'Tomorrow Guest', 'tomo@test.com', '+573004', '2026-10-11', '2026-10-15', 100, 'confirmed', 'airbnb', 0, NULL);
        ");

        // 2. Seed maintenance blocks:
        $this->calendarBlockRepo->createBlock('1606', '2026-10-13', '2026-10-16', 'AC Duct Cleaning', 1);

        // 3. Seed property rates:
        $this->rateRepo->createRate([
            'property_id' => '1707',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'season_name' => 'October High Tier',
            'price_per_night' => 550000.0,
            'min_stay' => 3,
        ]);

        // 4. Mock ChannelSyncService:
        $syncStatus1606 = new ChannelSyncStatus(
            propertyId: '1606',
            status: ChannelSyncStatus::STATUS_HEALTHY,
            lastSyncedAt: '2026-10-10T12:00:00+00:00',
            lastAttemptedAt: '2026-10-10T12:00:00+00:00',
            httpCode: 200,
            blockedNightsCount: 5
        );
        $syncService = $this->createMock(InboundChannelSyncServiceInterface::class);
        $syncService->method('getAllStatuses')->willReturn(['1606' => $syncStatus1606]);

        // 5. Mock Ledger with an un-onboarded channel block:
        $unonboardedBlock = new ChannelBlock('1707', '2026-10-12', '2026-10-15', 'airbnb', 'Airbnb (HM456)');
        $ledger = $this->createMock(ReservationLedgerInterface::class);
        $ledger->method('getChannelBlocks')->willReturnCallback(function (string $pid) use ($unonboardedBlock): array {
            return $pid === '1707' ? [$unonboardedBlock] : [];
        });

        $service = new DashboardQueryService(
            reservationRepo: $this->reservationRepo,
            calendarBlockRepo: $this->calendarBlockRepo,
            rateRepo: $this->rateRepo,
            channelSyncService: $syncService,
            ledger: $ledger
        );

        // Test Global "all" view
        $viewData = $service->getDashboardHubData('all', $now);

        // Assertions
        $this->assertSame('all', $viewData->selectedPropertyFilter);
        $this->assertSame(1, $viewData->todayArrivalsCount); // res-arr-today
        $this->assertSame(1, $viewData->todayDeparturesCount); // res-dep-today
        $this->assertSame(2, $viewData->activeStaysCount); // res-staying (1606) & res-arr-today (1707)

        // 7-day schedule keys
        $this->assertCount(7, $viewData->scheduleByDate);
        $this->assertArrayHasKey('2026-10-10', $viewData->scheduleByDate);
        $todayEvents = $viewData->scheduleByDate['2026-10-10'];
        $this->assertCount(2, $todayEvents);
        $this->assertSame(MovementType::CHECK_OUT, $todayEvents[0]->movementType);
        $this->assertSame(MovementType::TURNOVER, $todayEvents[1]->movementType);
        $this->assertSame('Departing Guest', $todayEvents[1]->departingGuestName);

        // Operational alerts
        // Alert 1: Arriving Guest check-in today without registry (CRITICAL)
        // Alert 2: Tomorrow Guest check-in tomorrow without registry (WARNING)
        // Alert 3: Un-onboarded Airbnb block starting 2026-10-12
        $this->assertNotEmpty($viewData->alerts);
        $this->assertSame(AlertSeverity::CRITICAL, $viewData->alerts[0]->severity);
        $this->assertSame('registry_res-arr-today', $viewData->alerts[0]->id);

        // Rate status
        $this->assertArrayHasKey('1707', $viewData->rateStatus);
        $this->assertSame(550000.0, $viewData->rateStatus['1707']->currentNightlyRate);
        $this->assertTrue($viewData->rateStatus['1707']->isSeasonalTierActive);
        $this->assertSame('October High Tier', $viewData->rateStatus['1707']->activeTierName);

        $this->assertArrayHasKey('1606', $viewData->rateStatus);
        $this->assertSame(350000.0, $viewData->rateStatus['1606']->currentNightlyRate); // baseline
        $this->assertFalse($viewData->rateStatus['1606']->isSeasonalTierActive);

        // Upcoming maintenance blocks
        $this->assertCount(1, $viewData->upcomingMaintenanceBlocks);
        $this->assertSame('AC Duct Cleaning', $viewData->upcomingMaintenanceBlocks[0]->reason);

        // Channel sync status
        $this->assertTrue($viewData->channelSyncData['is_healthy']);
        $this->assertArrayHasKey('1606', $viewData->channelSyncData['statuses']);

        // Test Property-Filtered "1606" view
        $viewData1606 = $service->getDashboardHubData('1606', $now);
        $this->assertSame('1606', $viewData1606->selectedPropertyFilter);
        $this->assertSame(0, $viewData1606->todayArrivalsCount);
        $this->assertSame(0, $viewData1606->todayDeparturesCount);
        $this->assertSame(1, $viewData1606->activeStaysCount);
        $this->assertCount(1, $viewData1606->rateStatus);
        $this->assertArrayHasKey('1606', $viewData1606->rateStatus);
    }

    public function testFailedClearanceAlertsAreIntegratedAndSortedInDashboardHubData(): void
    {
        $now = new DateTimeImmutable('2026-10-10');

        $syncService = $this->createMock(InboundChannelSyncServiceInterface::class);
        $syncService->method('getAllStatuses')->willReturn([]);

        $ledger = $this->createMock(ReservationLedgerInterface::class);
        $ledger->method('getChannelBlocks')->willReturn([]);

        $clearanceRepo = $this->createMock(CondominiumClearanceRepositoryInterface::class);
        $clearanceRepo->expects($this->once())
            ->method('getFailedClearanceAlerts')
            ->with('all', $now)
            ->willReturn([
                new OperationalAlert(
                    id: 'clearance_res_fail_critical',
                    type: AlertType::FAILED_CONDOMINIUM_CLEARANCE,
                    severity: AlertSeverity::CRITICAL,
                    propertyId: '1606',
                    title: 'Condominium Clearance Failed',
                    description: 'Condominium Administration Portal synchronization failed: Portal connection timed out',
                    dueDate: '2026-10-10',
                    reservationUid: 'res_fail_critical',
                    actionPayload: [
                        'reservationUid' => 'res_fail_critical',
                        'propertyId' => '1606',
                        'errorMessage' => 'Portal connection timed out',
                    ]
                ),
            ]);

        $service = new DashboardQueryService(
            reservationRepo: $this->reservationRepo,
            calendarBlockRepo: $this->calendarBlockRepo,
            rateRepo: $this->rateRepo,
            channelSyncService: $syncService,
            ledger: $ledger,
            clearanceRepo: $clearanceRepo
        );

        $viewData = $service->getDashboardHubData('all', $now);

        $clearanceAlerts = array_values(array_filter(
            $viewData->alerts,
            fn(OperationalAlert $a) => $a->type === AlertType::FAILED_CONDOMINIUM_CLEARANCE
        ));

        $this->assertCount(1, $clearanceAlerts);
        $this->assertSame('clearance_res_fail_critical', $clearanceAlerts[0]->id);
        $this->assertSame(AlertSeverity::CRITICAL, $clearanceAlerts[0]->severity);
        $this->assertSame('res_fail_critical', $clearanceAlerts[0]->actionPayload['reservationUid']);
    }

    public function testBuildOccupancyHeatmapGeneratesSevenMonthHorizonAndMondayFirstColumns(): void
    {
        $now = new DateTimeImmutable('2026-10-15');
        $syncService = $this->createMock(InboundChannelSyncServiceInterface::class);
        $syncService->method('getAllStatuses')->willReturn([]);

        $service = new DashboardQueryService(
            reservationRepo: $this->reservationRepo,
            calendarBlockRepo: $this->calendarBlockRepo,
            rateRepo: $this->rateRepo,
            channelSyncService: $syncService
        );

        $heatmap = $service->buildOccupancyHeatmap('all', $now);

        // 1. 7-month calendar horizon: 2026-10-01 to 2027-04-30
        $this->assertSame('2026-10-01', $heatmap->startDate);
        $this->assertSame('2027-04-30', $heatmap->endDate);
        $this->assertSame('all', $heatmap->propertyFilter);
        $this->assertSame(212, $heatmap->totalDaysCount); // Oct (31) + Nov (30) + Dec (31) + Jan (31) + Feb (28) + Mar (31) + Apr (30) = 212

        // 2. Monday-first columns (7 rows: 0=Mon, 6=Sun)
        $this->assertNotEmpty($heatmap->columns);
        $totalColSpan = 0;
        foreach ($heatmap->columns as $col) {
            $this->assertCount(7, $col->days);
            $this->assertArrayHasKey(0, $col->days);
            $this->assertArrayHasKey(6, $col->days);
        }

        // 3. First week column check (2026-10-01 is a Thursday -> row index 3)
        // Rows 0 (Mon), 1 (Tue), 2 (Wed) must be null padding
        $firstCol = $heatmap->columns[0];
        $this->assertNull($firstCol->days[0]);
        $this->assertNull($firstCol->days[1]);
        $this->assertNull($firstCol->days[2]);
        $this->assertNotNull($firstCol->days[3]);
        $this->assertSame('2026-10-01', $firstCol->days[3]->date);
        $this->assertSame(1, $firstCol->days[3]->dayOfMonth);
        $this->assertSame(4, $firstCol->days[3]->dayOfWeek);
        $this->assertSame('Oct', $firstCol->monthLabel);

        // 4. Last week column check (2027-04-30 is a Friday -> row index 4)
        // Rows 5 (Sat) and 6 (Sun) must be null padding
        $lastCol = $heatmap->columns[count($heatmap->columns) - 1];
        $this->assertNotNull($lastCol->days[4]);
        $this->assertSame('2027-04-30', $lastCol->days[4]->date);
        $this->assertNull($lastCol->days[5]);
        $this->assertNull($lastCol->days[6]);

        // 5. Month headers
        $this->assertCount(7, $heatmap->monthHeaders);
        $labels = array_column($heatmap->monthHeaders, 'label');
        $this->assertSame(['Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr'], $labels);

        // Sum of all month header spans must equal total number of columns
        $totalSpan = array_sum(array_column($heatmap->monthHeaders, 'span'));
        $this->assertSame(count($heatmap->columns), $totalSpan);
    }

    public function testBuildOccupancyHeatmapDynamicCapacityColoringAndScoping(): void
    {
        $now = new DateTimeImmutable('2026-10-15');

        // Seed reservations:
        // - Single occupancy on 2026-10-14 (1606 only)
        // - Double occupancy on 2026-10-15 (Today: both 1606 and 1707)
        // - Single occupancy on 2026-10-16 (1707 only)
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source
            ) VALUES
            ('res-1606', '1606', 'Guest 1606', 'g1@test.com', '+1', '2026-10-14', '2026-10-16', 200, 'confirmed', 'web'),
            ('res-1707', '1707', 'Guest 1707', 'g2@test.com', '+2', '2026-10-15', '2026-10-17', 200, 'confirmed', 'web');
        ");

        $syncService = $this->createMock(InboundChannelSyncServiceInterface::class);
        $syncService->method('getAllStatuses')->willReturn([]);

        $service = new DashboardQueryService(
            reservationRepo: $this->reservationRepo,
            calendarBlockRepo: $this->calendarBlockRepo,
            rateRepo: $this->rateRepo,
            channelSyncService: $syncService
        );

        // 1. ALL Properties View
        $heatmapAll = $service->buildOccupancyHeatmap('all', $now);

        // Find cells for 2026-10-01 (vacant past), 2026-10-14 (partial past), 2026-10-15 (full today), 2026-10-16 (partial future)
        $cellsByDate = [];
        foreach ($heatmapAll->columns as $col) {
            foreach ($col->days as $cell) {
                if ($cell !== null) {
                    $cellsByDate[$cell->date] = $cell;
                }
            }
        }

        // Vacant past date (2026-10-01)
        $cellOct1 = $cellsByDate['2026-10-01'];
        $this->assertSame('vacant', $cellOct1->getCapacityLevel());
        $this->assertStringContainsString('bg-gray-100', $cellOct1->getCapacityCssClass());
        $this->assertTrue($cellOct1->isPast);
        $this->assertFalse($cellOct1->isToday);
        $this->assertStringContainsString('opacity-40', $cellOct1->getCellCssClasses());

        // Partial past date (2026-10-14: 1 occupied out of 2)
        $cellOct14 = $cellsByDate['2026-10-14'];
        $this->assertSame(1, $cellOct14->occupiedUnits);
        $this->assertSame(2, $cellOct14->totalUnits);
        $this->assertSame('partial', $cellOct14->getCapacityLevel());
        $this->assertStringContainsString('bg-emerald-200', $cellOct14->getCapacityCssClass());
        $this->assertTrue($cellOct14->isPast);
        $this->assertStringContainsString('opacity-40', $cellOct14->getCellCssClasses());

        // Full today date (2026-10-15: 2 occupied out of 2)
        $cellOct15 = $cellsByDate['2026-10-15'];
        $this->assertSame(2, $cellOct15->occupiedUnits);
        $this->assertSame(2, $cellOct15->totalUnits);
        $this->assertSame('full', $cellOct15->getCapacityLevel());
        $this->assertStringContainsString('bg-emerald-600', $cellOct15->getCapacityCssClass());
        $this->assertFalse($cellOct15->isPast);
        $this->assertTrue($cellOct15->isToday);
        $this->assertStringContainsString('ring-2 ring-indigo-600 ring-offset-1', $cellOct15->getCellCssClasses());

        // Partial future date (2026-10-16: 1 occupied out of 2)
        $cellOct16 = $cellsByDate['2026-10-16'];
        $this->assertSame(1, $cellOct16->occupiedUnits);
        $this->assertSame(2, $cellOct16->totalUnits);
        $this->assertSame('partial', $cellOct16->getCapacityLevel());
        $this->assertStringContainsString('bg-emerald-200', $cellOct16->getCapacityCssClass());
        $this->assertFalse($cellOct16->isPast);
        $this->assertFalse($cellOct16->isToday);
        $this->assertStringNotContainsString('opacity-40', $cellOct16->getCellCssClasses());
        $this->assertStringNotContainsString('ring-2', $cellOct16->getCellCssClasses());

        // 2. Scoped to Property 1606
        $heatmap1606 = $service->buildOccupancyHeatmap('1606', $now);
        $this->assertSame('1606', $heatmap1606->propertyFilter);

        $cells1606 = [];
        foreach ($heatmap1606->columns as $col) {
            foreach ($col->days as $cell) {
                if ($cell !== null) {
                    $cells1606[$cell->date] = $cell;
                }
            }
        }

        // 2026-10-14 has 1606 occupied -> full (1/1)
        $this->assertSame(1, $cells1606['2026-10-14']->occupiedUnits);
        $this->assertSame(1, $cells1606['2026-10-14']->totalUnits);
        $this->assertSame('full', $cells1606['2026-10-14']->getCapacityLevel());
        $this->assertStringContainsString('bg-emerald-600', $cells1606['2026-10-14']->getCapacityCssClass());

        // 2026-10-16 has only 1707 occupied, so 1606 is vacant (0/1)
        $this->assertSame(0, $cells1606['2026-10-16']->occupiedUnits);
        $this->assertSame(1, $cells1606['2026-10-16']->totalUnits);
        $this->assertSame('vacant', $cells1606['2026-10-16']->getCapacityLevel());
        $this->assertStringContainsString('bg-gray-100', $cells1606['2026-10-16']->getCapacityCssClass());

        // 3. Scoped to Property 1707
        $heatmap1707 = $service->buildOccupancyHeatmap('1707', $now);
        $this->assertSame('1707', $heatmap1707->propertyFilter);

        $cells1707 = [];
        foreach ($heatmap1707->columns as $col) {
            foreach ($col->days as $cell) {
                if ($cell !== null) {
                    $cells1707[$cell->date] = $cell;
                }
            }
        }

        // 2026-10-14 has only 1606 occupied, so 1707 is vacant (0/1)
        $this->assertSame(0, $cells1707['2026-10-14']->occupiedUnits);
        $this->assertSame(1, $cells1707['2026-10-14']->totalUnits);
        $this->assertSame('vacant', $cells1707['2026-10-14']->getCapacityLevel());
        $this->assertStringContainsString('bg-gray-100', $cells1707['2026-10-14']->getCapacityCssClass());

        // 2026-10-16 has 1707 occupied -> full (1/1)
        $this->assertSame(1, $cells1707['2026-10-16']->occupiedUnits);
        $this->assertSame(1, $cells1707['2026-10-16']->totalUnits);
        $this->assertSame('full', $cells1707['2026-10-16']->getCapacityLevel());
        $this->assertStringContainsString('bg-emerald-600', $cells1707['2026-10-16']->getCapacityCssClass());
    }

    public function testBuildOccupancyHeatmapTurnoverWarningMaintenanceAndPendingHoldIndicators(): void
    {
        $now = new DateTimeImmutable('2026-10-15');

        // Clean tables
        $this->pdo->exec('DELETE FROM reservations; DELETE FROM calendar_blocks; DELETE FROM property_rates;');

        // 1. Coinciding Turnover on 1606 on 2026-10-18:
        // Departing res: 2026-10-15 -> 2026-10-18 (registry completed)
        // Arriving res: 2026-10-18 -> 2026-10-21 (registry completed)
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, registry_completed, external_confirmation_code
            ) VALUES
            ('res-turnover-out', '1606', 'Turnover Departing', 'out@test.com', '+1', '2026-10-15', '2026-10-18', 300, 'confirmed', 'web', 1, 'CONF-OUT'),
            ('res-turnover-in', '1606', 'Turnover Arriving', 'in@test.com', '+2', '2026-10-18', '2026-10-21', 300, 'confirmed', 'airbnb', 1, 'CONF-IN');
        ");

        // 2. Operational Warnings:
        // - Missing guest registry on 1707: 2026-10-22 -> 2026-10-25 (registry_completed = 0)
        // - Clearance failed on 1606: 2026-10-25 -> 2026-10-28 (registry_completed = 1, clearance failed)
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, registry_completed, external_confirmation_code
            ) VALUES
            ('res-unregistered', '1707', 'Unregistered Guest', 'unreg@test.com', '+3', '2026-10-22', '2026-10-25', 300, 'confirmed', 'web', 0, NULL),
            ('res-clearance-fail', '1606', 'Failed Clearance Guest', 'fail@test.com', '+4', '2026-10-25', '2026-10-28', 300, 'confirmed', 'direct', 1, 'CONF-FAIL');
        ");

        // 3. Maintenance Block on 1606: 2026-10-29 -> 2026-10-31
        $this->calendarBlockRepo->createBlock('1606', '2026-10-29', '2026-10-31', 'Plumbing Renovation', 1);

        // Also add confirmed reservation on 1707 on 2026-10-29 -> 2026-10-31 to test amber ring in 'all' view
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, registry_completed
            ) VALUES
            ('res-occupied-1707', '1707', 'Occupied Parallel', 'par@test.com', '+5', '2026-10-29', '2026-10-31', 200, 'confirmed', 'web', 1);
        ");

        // 4. Pending Payment Hold on 1707: 2026-11-05 -> 2026-11-08 (status = 'pending_payment')
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, registry_completed
            ) VALUES
            ('res-pending-hold', '1707', 'Pending Guest', 'pend@test.com', '+6', '2026-11-05', '2026-11-08', 300, 'pending_payment', 'web', 0);
        ");

        // 5. Property Rate for 1606
        $this->rateRepo->createRate([
            'property_id' => '1606',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'season_name' => 'High Season October',
            'price_per_night' => 175.0,
            'min_stay' => 2,
        ]);

        // Mock failed clearance repository
        $failedClearance = new CondominiumClearance(
            reservationUid: 'res-clearance-fail',
            propertyId: '1606',
            status: CondominiumClearance::STATUS_FAILED,
            errorMessage: 'Clearance API rejected invalid document'
        );
        $clearanceRepo = $this->createMock(CondominiumClearanceRepositoryInterface::class);
        $clearanceRepo->method('findFailedClearances')->willReturn([$failedClearance]);

        $syncService = $this->createMock(InboundChannelSyncServiceInterface::class);
        $syncService->method('getAllStatuses')->willReturn([]);

        $service = new DashboardQueryService(
            reservationRepo: $this->reservationRepo,
            calendarBlockRepo: $this->calendarBlockRepo,
            rateRepo: $this->rateRepo,
            channelSyncService: $syncService,
            clearanceRepo: $clearanceRepo
        );

        // --- Test 'all' property heatmap ---
        $heatmapAll = $service->buildOccupancyHeatmap('all', $now);

        $cellsAll = [];
        foreach ($heatmapAll->columns as $col) {
            foreach ($col->days as $cell) {
                if ($cell !== null) {
                    $cellsAll[$cell->date] = $cell;
                }
            }
        }

        // Test 1: Turnover cell on 2026-10-18
        $turnoverCell = $cellsAll['2026-10-18'];
        $this->assertTrue($turnoverCell->hasTurnover);
        $this->assertFalse($turnoverCell->hasWarning);
        $this->assertSame(1, $turnoverCell->occupiedUnits);
        $this->assertArrayHasKey('1606', $turnoverCell->flatDetails);
        $this->assertSame('Turnover', $turnoverCell->flatDetails['1606']['movement']);
        $this->assertStringContainsString('Turnover Arriving', $turnoverCell->flatDetails['1606']['guest']);
        $this->assertStringContainsString('Turnover Departing', $turnoverCell->flatDetails['1606']['guest']);

        // Test 2: Incomplete registry warning cell on 2026-10-23
        $warningRegistryCell = $cellsAll['2026-10-23'];
        $this->assertTrue($warningRegistryCell->hasWarning);
        $this->assertFalse($warningRegistryCell->hasTurnover);
        $this->assertNotEmpty($warningRegistryCell->warningMessages);
        $this->assertStringContainsString('Guest registry incomplete (Unregistered Guest)', $warningRegistryCell->warningMessages[0]);

        // Test 3: Failed clearance warning cell on 2026-10-26
        $warningClearanceCell = $cellsAll['2026-10-26'];
        $this->assertTrue($warningClearanceCell->hasWarning);
        $this->assertNotEmpty($warningClearanceCell->warningMessages);
        $this->assertStringContainsString('Condominium clearance failed (Failed Clearance Guest)', $warningClearanceCell->warningMessages[0]);

        // Test 4: Maintenance block combined with occupancy on 2026-10-29 ('all' view)
        $maintCellAll = $cellsAll['2026-10-29'];
        $this->assertTrue($maintCellAll->hasMaintenance);
        $this->assertSame(1, $maintCellAll->occupiedUnits); // 1707 occupied
        $this->assertSame('partial', $maintCellAll->getCapacityLevel());
        $this->assertStringContainsString('ring-2 ring-amber-400', $maintCellAll->getCellCssClasses());
        $this->assertSame('Maintenance', $maintCellAll->flatDetails['1606']['status']);
        $this->assertSame('Property 1606: Maintenance', $maintCellAll->flatDetails['1606']['label']);
        $this->assertSame('Hold: Plumbing Renovation', $maintCellAll->flatDetails['1606']['guest']);

        // Test 5: Pending hold cell on 2026-11-06 (no confirmed occupancy)
        $pendingCell = $cellsAll['2026-11-06'];
        $this->assertTrue($pendingCell->hasPendingHold);
        $this->assertSame(0, $pendingCell->occupiedUnits);
        $this->assertSame('vacant', $pendingCell->getCapacityLevel());
        $this->assertStringContainsString('border border-dashed border-slate-400', $pendingCell->getCellCssClasses());
        $this->assertSame('Reserved (Pending)', $pendingCell->flatDetails['1707']['status']);
        $this->assertSame('Property 1707: Reserved (Pending)', $pendingCell->flatDetails['1707']['label']);

        // Test 6: Aggregate counters
        $this->assertGreaterThanOrEqual(1, $heatmapAll->totalTurnoversCount);
        $this->assertGreaterThanOrEqual(2, $heatmapAll->totalAlertsCount);

        // --- Test '1606' scoped heatmap for maintenance level ---
        $heatmap1606 = $service->buildOccupancyHeatmap('1606', $now);
        $cells1606 = [];
        foreach ($heatmap1606->columns as $col) {
            foreach ($col->days as $cell) {
                if ($cell !== null) {
                    $cells1606[$cell->date] = $cell;
                }
            }
        }
        $maintCell1606 = $cells1606['2026-10-29'];
        $this->assertTrue($maintCell1606->hasMaintenance);
        $this->assertSame(0, $maintCell1606->occupiedUnits);
        $this->assertSame('maintenance', $maintCell1606->getCapacityLevel());
        $this->assertStringContainsString('bg-amber-100 border border-amber-300', $maintCell1606->getCapacityCssClass());
        $this->assertSame(175, $maintCell1606->effectiveRate); // Seasonal rate lookup
    }

    public function testGetDaySummaryVacantCalendarDay(): void
    {
        $this->pdo->exec('DELETE FROM reservations; DELETE FROM calendar_blocks; DELETE FROM property_rates;');

        $syncService = $this->createMock(InboundChannelSyncServiceInterface::class);
        $syncService->method('getAllStatuses')->willReturn([]);

        $service = new DashboardQueryService(
            reservationRepo: $this->reservationRepo,
            calendarBlockRepo: $this->calendarBlockRepo,
            rateRepo: $this->rateRepo,
            channelSyncService: $syncService
        );

        $date = '2026-11-20';
        $summary = $service->getDaySummary($date, 'all');

        $this->assertSame('2026-11-20', $summary->date);
        $this->assertSame('Friday, Nov 20, 2026', $summary->formattedDate);
        $this->assertSame('Friday', $summary->dayOfWeekName);
        $this->assertSame('all', $summary->propertyFilter);
        $this->assertSame(2, $summary->totalUnits);
        $this->assertSame(0, $summary->occupiedUnits);
        $this->assertSame('vacant', $summary->capacityLevel);
        $this->assertFalse($summary->hasTurnover);
        $this->assertFalse($summary->hasMaintenance);
        $this->assertFalse($summary->hasWarning);
        $this->assertSame(230, $summary->combinedRate); // Base rates: 110 (1606) + 120 (1707) = 230
        $this->assertEmpty($summary->movements);
        $this->assertEmpty($summary->maintenanceBlocks);
        $this->assertEmpty($summary->warnings);
        $this->assertEmpty($summary->reservationUids);

        $this->assertArrayHasKey('1606', $summary->properties);
        $this->assertArrayHasKey('1707', $summary->properties);

        $p1606 = $summary->properties['1606'];
        $this->assertSame('1606', $p1606->propertyId);
        $this->assertSame('Vacant', $p1606->status);
        $this->assertFalse($p1606->isOccupied);
        $this->assertSame(110, $p1606->nightlyRate);
        $this->assertNull($p1606->activeReservation);
        $this->assertNull($p1606->departingReservation);
        $this->assertNull($p1606->arrivingReservation);
        $this->assertNull($p1606->maintenanceBlock);
        $this->assertEmpty($p1606->movements);
        $this->assertEmpty($p1606->warnings);

        $p1707 = $summary->properties['1707'];
        $this->assertSame('1707', $p1707->propertyId);
        $this->assertSame('Vacant', $p1707->status);
        $this->assertFalse($p1707->isOccupied);
        $this->assertSame(120, $p1707->nightlyRate);
    }

    public function testGetDaySummaryPropertyFilter(): void
    {
        $this->pdo->exec('DELETE FROM reservations; DELETE FROM calendar_blocks; DELETE FROM property_rates;');

        $syncService = $this->createMock(InboundChannelSyncServiceInterface::class);
        $syncService->method('getAllStatuses')->willReturn([]);

        $service = new DashboardQueryService(
            reservationRepo: $this->reservationRepo,
            calendarBlockRepo: $this->calendarBlockRepo,
            rateRepo: $this->rateRepo,
            channelSyncService: $syncService
        );

        $summary1606 = $service->getDaySummary('2026-11-20', '1606');
        $this->assertSame('1606', $summary1606->propertyFilter);
        $this->assertSame(1, $summary1606->totalUnits);
        $this->assertCount(1, $summary1606->properties);
        $this->assertArrayHasKey('1606', $summary1606->properties);
        $this->assertArrayNotHasKey('1707', $summary1606->properties);
        $this->assertSame(110, $summary1606->combinedRate);

        $summary1707 = $service->getDaySummary('2026-11-20', '1707');
        $this->assertSame('1707', $summary1707->propertyFilter);
        $this->assertSame(1, $summary1707->totalUnits);
        $this->assertCount(1, $summary1707->properties);
        $this->assertArrayHasKey('1707', $summary1707->properties);
        $this->assertArrayNotHasKey('1606', $summary1707->properties);
        $this->assertSame(120, $summary1707->combinedRate);
    }

    public function testGetDaySummaryConfirmedReservation(): void
    {
        $this->pdo->exec('DELETE FROM reservations; DELETE FROM calendar_blocks; DELETE FROM property_rates;');

        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, registry_completed, door_code
            ) VALUES (
                'res-confirmed-xyz', '1606', 'Alice Wonder', 'alice@test.com', '+57300999',
                '2026-10-10', '2026-10-15', 500, 'confirmed', 'direct', 1, '9876#'
            );
        ");

        $clearance = new CondominiumClearance(
            reservationUid: 'res-confirmed-xyz',
            propertyId: '1606',
            status: CondominiumClearance::STATUS_SYNCED,
            clearanceNumber: 'CLEAR-777',
            errorMessage: null
        );
        $clearanceRepo = $this->createMock(CondominiumClearanceRepositoryInterface::class);
        $clearanceRepo->method('findByReservationUid')->with('res-confirmed-xyz')->willReturn($clearance);

        $syncService = $this->createMock(InboundChannelSyncServiceInterface::class);
        $syncService->method('getAllStatuses')->willReturn([]);

        $service = new DashboardQueryService(
            reservationRepo: $this->reservationRepo,
            calendarBlockRepo: $this->calendarBlockRepo,
            rateRepo: $this->rateRepo,
            channelSyncService: $syncService,
            clearanceRepo: $clearanceRepo
        );

        $summary = $service->getDaySummary('2026-10-12', 'all');

        $this->assertSame(1, $summary->occupiedUnits);
        $this->assertSame(2, $summary->totalUnits);
        $this->assertSame('partial', $summary->capacityLevel);
        $this->assertFalse($summary->hasTurnover);
        $this->assertFalse($summary->hasWarning);
        $this->assertEquals(['res-confirmed-xyz'], $summary->reservationUids);

        $p1606 = $summary->properties['1606'];
        $this->assertSame('Occupied', $p1606->status);
        $this->assertTrue($p1606->isOccupied);
        $this->assertNotNull($p1606->activeReservation);
        $this->assertSame('res-confirmed-xyz', $p1606->activeReservation['reservation_uid']);
        $this->assertSame('Alice Wonder', $p1606->activeReservation['guest_name']);
        $this->assertSame('9876#', $p1606->activeReservation['door_code']);
        $this->assertSame(CondominiumClearance::STATUS_SYNCED, $p1606->activeReservation['clearance_status']);
        $this->assertSame('CLEAR-777', $p1606->activeReservation['clearance_number']);
        $this->assertEmpty($p1606->warnings);
    }

    public function testGetDaySummarySameDayTurnover(): void
    {
        $this->pdo->exec('DELETE FROM reservations; DELETE FROM calendar_blocks; DELETE FROM property_rates;');

        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, registry_completed, door_code
            ) VALUES
            ('res-turnover-dep', '1606', 'Departing Guest', 'dep@test.com', '+1', '2026-10-14', '2026-10-18', 400, 'confirmed', 'web', 1, '1111#'),
            ('res-turnover-arr', '1606', 'Arriving Guest', 'arr@test.com', '+2', '2026-10-18', '2026-10-22', 400, 'confirmed', 'airbnb', 0, NULL);
        ");

        $syncService = $this->createMock(InboundChannelSyncServiceInterface::class);
        $syncService->method('getAllStatuses')->willReturn([]);

        $service = new DashboardQueryService(
            reservationRepo: $this->reservationRepo,
            calendarBlockRepo: $this->calendarBlockRepo,
            rateRepo: $this->rateRepo,
            channelSyncService: $syncService
        );

        $summary = $service->getDaySummary('2026-10-18', '1606');

        $this->assertTrue($summary->hasTurnover);
        $this->assertTrue($summary->hasWarning); // Arriving guest has incomplete registry
        $this->assertSame(1, $summary->occupiedUnits);
        $this->assertSame(1, $summary->totalUnits);
        $this->assertSame('full', $summary->capacityLevel);
        $this->assertContains('res-turnover-dep', $summary->reservationUids);
        $this->assertContains('res-turnover-arr', $summary->reservationUids);

        $p1606 = $summary->properties['1606'];
        $this->assertSame('Turnover', $p1606->status);
        $this->assertTrue($p1606->isOccupied);
        $this->assertNotNull($p1606->departingReservation);
        $this->assertNotNull($p1606->arrivingReservation);
        $this->assertSame('res-turnover-dep', $p1606->departingReservation['reservation_uid']);
        $this->assertSame('res-turnover-arr', $p1606->arrivingReservation['reservation_uid']);
        $this->assertContains('Guest registry incomplete (Arriving Guest)', $p1606->warnings);
    }

    public function testGetDaySummaryMaintenanceBlock(): void
    {
        $this->pdo->exec('DELETE FROM reservations; DELETE FROM calendar_blocks; DELETE FROM property_rates;');

        $this->calendarBlockRepo->createBlock('1707', '2026-10-25', '2026-10-28', 'Balcony Painting', 1);

        $syncService = $this->createMock(InboundChannelSyncServiceInterface::class);
        $syncService->method('getAllStatuses')->willReturn([]);

        $service = new DashboardQueryService(
            reservationRepo: $this->reservationRepo,
            calendarBlockRepo: $this->calendarBlockRepo,
            rateRepo: $this->rateRepo,
            channelSyncService: $syncService
        );

        $summary = $service->getDaySummary('2026-10-26', '1707');

        $this->assertTrue($summary->hasMaintenance);
        $this->assertSame(0, $summary->occupiedUnits);
        $this->assertSame('maintenance', $summary->capacityLevel);
        $this->assertTrue($summary->hasWarning);
        $this->assertCount(1, $summary->maintenanceBlocks);
        $this->assertSame('Balcony Painting', $summary->maintenanceBlocks[0]->reason);

        $p1707 = $summary->properties['1707'];
        $this->assertSame('Maintenance', $p1707->status);
        $this->assertFalse($p1707->isOccupied);
        $this->assertNotNull($p1707->maintenanceBlock);
        $this->assertSame('Balcony Painting', $p1707->maintenanceBlock->reason);
        $this->assertContains('Maintenance hold: Balcony Painting', $p1707->warnings);
    }

    public function testGetDaySummaryInvalidDateFormatThrowsException(): void
    {
        $syncService = $this->createMock(InboundChannelSyncServiceInterface::class);
        $service = new DashboardQueryService(
            reservationRepo: $this->reservationRepo,
            calendarBlockRepo: $this->calendarBlockRepo,
            rateRepo: $this->rateRepo,
            channelSyncService: $syncService
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date format: invalid-date');
        $service->getDaySummary('invalid-date');
    }

    public function testGetDaySummaryInvalidCalendarDateThrowsException(): void
    {
        $syncService = $this->createMock(InboundChannelSyncServiceInterface::class);
        $service = new DashboardQueryService(
            reservationRepo: $this->reservationRepo,
            calendarBlockRepo: $this->calendarBlockRepo,
            rateRepo: $this->rateRepo,
            channelSyncService: $syncService
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid calendar date: 2026-02-30');
        $service->getDaySummary('2026-02-30');
    }
}

