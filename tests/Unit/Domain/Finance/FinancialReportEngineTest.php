<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Finance;

use DateTimeImmutable;
use DateTimeZone;
use OceanViewFlats\Domain\Database\MigrationRunner;
use OceanViewFlats\Domain\Finance\ExpenseCategory;
use OceanViewFlats\Domain\Finance\FinancialReportEngine;
use OceanViewFlats\Domain\Finance\FinancialReportFilter;
use OceanViewFlats\Domain\Finance\InMemoryOperationalExpenseRepository;
use OceanViewFlats\Domain\Finance\OperationalExpense;
use OceanViewFlats\Domain\Finance\OperationalExpenseRepositoryInterface;
use OceanViewFlats\Domain\Finance\PdoOperationalExpenseRepository;
use OceanViewFlats\Domain\Reservation\InMemoryMaintenanceBlockSource;
use OceanViewFlats\Domain\Reservation\InMemoryReservationRepository;
use OceanViewFlats\Domain\Reservation\MaintenanceBlock;
use OceanViewFlats\Domain\Reservation\MaintenanceBlockSourceInterface;
use OceanViewFlats\Domain\Reservation\PdoMaintenanceBlockRepository;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationRepositoryInterface;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FinancialReportEngineTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function adapterModeProvider(): array
    {
        return [
            'InMemory adapters' => ['in_memory'],
            'PDO SQLite adapters' => ['pdo'],
        ];
    }

    /**
     * @return array{
     *   resRepo: ReservationRepositoryInterface,
     *   blockSource: MaintenanceBlockSourceInterface,
     *   expRepo: OperationalExpenseRepositoryInterface,
     *   engine: FinancialReportEngine,
     *   addBlock: callable(MaintenanceBlock): void
     * }
     */
    private function createEngineHarness(string $mode): array
    {
        if ($mode === 'pdo') {
            $pdo = new PDO('sqlite::memory:', null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            MigrationRunner::run($pdo, 'memory_db');

            $resRepo = new PdoReservationRepository($pdo);
            $blockRepo = new PdoMaintenanceBlockRepository($pdo);
            $expRepo = new PdoOperationalExpenseRepository($pdo);

            return [
                'resRepo' => $resRepo,
                'blockSource' => $blockRepo,
                'expRepo' => $expRepo,
                'engine' => new FinancialReportEngine($resRepo, $blockRepo, $expRepo),
                'addBlock' => static function (MaintenanceBlock $block) use ($blockRepo): void {
                    $blockRepo->save($block);
                },
            ];
        }

        $resRepo = new InMemoryReservationRepository();
        $blockSource = new InMemoryMaintenanceBlockSource();
        $expRepo = new InMemoryOperationalExpenseRepository();

        return [
            'resRepo' => $resRepo,
            'blockSource' => $blockSource,
            'expRepo' => $expRepo,
            'engine' => new FinancialReportEngine($resRepo, $blockSource, $expRepo),
            'addBlock' => static function (MaintenanceBlock $block) use ($blockSource): void {
                $blockSource->addBlock($block);
            },
        ];
    }

    #[DataProvider('adapterModeProvider')]
    public function testCrossMonthAccrualProrationVerifiedPolicyRetentionAndUnpaidHoldExclusion(string $mode): void
    {
        $harness = $this->createEngineHarness($mode);
        $resRepo = $harness['resRepo'];
        $engine = $harness['engine'];
        $refClock = new DateTimeImmutable('2026-10-17 12:00:00', new DateTimeZone('America/Bogota'));

        // 1. Cross-month active stay: Sep 28 -> Oct 03 (5 nights total: 3 in Sep, 2 in Oct)
        // Total price: 2,500,000 COP, Refunded: 500,000 COP => Net Contract: 2,000,000 COP
        // In Oct (2/5 = 0.4): Active Stay Revenue = 800,000, Refunded = 200,000, Gross Charged = 1,000,000
        $resRepo->save(Reservation::create(
            reservationUid: 'ovf_cross_active',
            propertyId: '1606',
            guestName: 'Camila Restrepo',
            guestEmail: 'camila@example.com',
            guestPhone: '+573001112233',
            checkIn: '2026-09-28',
            checkOut: '2026-10-03',
            totalPrice: 2500000.0,
            status: ReservationStatus::CONFIRMED,
            paymentStatus: 'approved',
            source: 'web',
            refundedAmount: 500000.0
        ));

        // 2. Cross-month settled cancelled reservation with Policy Retention:
        // Sep 29 -> Oct 03 (4 nights total: 2 in Sep, 2 in Oct)
        // Total price: 1,600,000 COP, Refunded: 800,000 COP => Net Retained: 800,000 COP
        // In Oct (2/4 = 0.5): Policy Retention = 400,000, Refunded = 400,000, Occupied Nights = 0
        $resRepo->save(Reservation::create(
            reservationUid: 'ovf_cross_cancelled_settled',
            propertyId: '1707',
            guestName: 'Carlos Mendoza',
            guestEmail: 'carlos@example.com',
            guestPhone: '+573004445566',
            checkIn: '2026-09-29',
            checkOut: '2026-10-03',
            totalPrice: 1600000.0,
            status: ReservationStatus::CANCELLED,
            paymentStatus: 'partially_refunded',
            source: 'web',
            refundedAmount: 800000.0
        ));

        // 3. Unpaid cancelled pending hold (payment_status = 'pending', refunded_amount = 0) -> MUST be excluded!
        $resRepo->save(Reservation::create(
            reservationUid: 'ovf_unpaid_cancelled_hold',
            propertyId: '1606',
            guestName: 'Ghost Hold',
            guestEmail: 'ghost@example.com',
            guestPhone: '+573009998877',
            checkIn: '2026-10-10',
            checkOut: '2026-10-15',
            totalPrice: 3500000.0,
            status: ReservationStatus::CANCELLED,
            paymentStatus: 'pending',
            source: 'web',
            refundedAmount: 0.0
        ));

        // 4. Unpaid cancelled rejected hold (payment_status = 'rejected') -> MUST be excluded!
        $resRepo->save(Reservation::create(
            reservationUid: 'ovf_rejected_cancelled',
            propertyId: '1707',
            guestName: 'Rejected Hold',
            guestEmail: 'rejected@example.com',
            guestPhone: '+573009998800',
            checkIn: '2026-10-16',
            checkOut: '2026-10-20',
            totalPrice: 2800000.0,
            status: ReservationStatus::CANCELLED,
            paymentStatus: 'rejected',
            source: 'web',
            refundedAmount: 0.0
        ));

        // 5. Unconfirmed pending_payment hold -> MUST be excluded!
        $resRepo->save(Reservation::create(
            reservationUid: 'ovf_pending_hold',
            propertyId: '1606',
            guestName: 'Pending Checkout',
            guestEmail: 'pending@example.com',
            guestPhone: '+573001110000',
            checkIn: '2026-10-21',
            checkOut: '2026-10-24',
            totalPrice: 1900000.0,
            status: ReservationStatus::PENDING_PAYMENT,
            paymentStatus: 'pending',
            source: 'web',
            refundedAmount: 0.0
        ));

        // Evaluate October 2026 ('this_month')
        $octReport = $engine->generate(new FinancialReportFilter(period: 'this_month'), $refClock);
        $oct = $octReport->current;

        $this->assertSame('2026-10-01', $oct->periodStart);
        $this->assertSame('2026-11-01', $oct->periodEnd);
        $this->assertSame(800000.0, $oct->activeStayRevenue);
        $this->assertSame(400000.0, $oct->policyRetention);
        $this->assertSame(1200000.0, $oct->grossRevenue);
        $this->assertSame(600000.0, $oct->refundedAmount); // 200k + 400k
        $this->assertSame(1800000.0, $oct->grossCharged);  // 1,000k + 800k
        $this->assertSame(2, $oct->occupiedNights);        // Cancelled reservation contributes 0 occupied nights
        $this->assertSame(2, $oct->pricedOccupiedNights);
        $this->assertSame(1, $oct->activeStaysCount);
        $this->assertSame(1, $oct->cancelledWithRetentionCount);
        // ADR excludes Policy Retention: 800,000 / 2 = 400,000 COP
        $this->assertSame(400000.0, $oct->adr);
        // RevPAR includes Gross Revenue (Active + Policy Retention): 1,200,000 / 62 = 19354.84
        $this->assertSame(62, $oct->availableNights);
        $this->assertSame(round(1200000.0 / 62, 2), $oct->revpar);
        $this->assertCount(2, $oct->attributedReservations);

        // Evaluate September 2026 ('last_month') and verify the complementary 60% / 50% shares reconcile to 100%
        $sepReport = $engine->generate(new FinancialReportFilter(period: 'last_month'), $refClock);
        $sep = $sepReport->current;

        $this->assertSame('2026-09-01', $sep->periodStart);
        $this->assertSame('2026-10-01', $sep->periodEnd);
        $this->assertSame(1200000.0, $sep->activeStayRevenue);
        $this->assertSame(400000.0, $sep->policyRetention);
        $this->assertSame(1600000.0, $sep->grossRevenue);
        $this->assertSame(700000.0, $sep->refundedAmount); // 300k + 400k
        $this->assertSame(3, $sep->occupiedNights);

        // Cross-month reconciliation: Sep + Oct = 100% of contract amounts
        $this->assertSame(2000000.0, $sep->activeStayRevenue + $oct->activeStayRevenue);
        $this->assertSame(800000.0, $sep->policyRetention + $oct->policyRetention);
        $this->assertSame(1300000.0, $sep->refundedAmount + $oct->refundedAmount);
    }

    #[DataProvider('adapterModeProvider')]
    public function testPerNightCapacityDeduplicationCommercialPrecedenceAndZeroAvailableNightsSafety(string $mode): void
    {
        $harness = $this->createEngineHarness($mode);
        $resRepo = $harness['resRepo'];
        $addBlock = $harness['addBlock'];
        $engine = $harness['engine'];
        $refClock = new DateTimeImmutable('2026-10-17 12:00:00', new DateTimeZone('America/Bogota'));

        // Two overlapping maintenance blocks on 1606:
        // Block 1: Oct 10 -> Oct 15 (nights: 10, 11, 12, 13, 14 = 5 nights)
        // Block 2: Oct 12 -> Oct 17 (nights: 12, 13, 14, 15, 16 = 5 nights)
        // Union of blocks = Oct 10..16 (7 distinct nights)
        $addBlock(new MaintenanceBlock(
            propertyId: '1606',
            startDate: '2026-10-10',
            endDate: '2026-10-15',
            reason: 'AC servicing'
        ));
        $addBlock(new MaintenanceBlock(
            propertyId: '1606',
            startDate: '2026-10-12',
            endDate: '2026-10-17',
            reason: 'Painting overlap'
        ));

        // Confirmed owner_stay on 1606 overlapping Oct 15..19 (nights: 15, 16, 17, 18 -> adds 17, 18 = 2 new nights; union = 9 nights: 10..18)
        $resRepo->save(Reservation::create(
            reservationUid: 'res_owner_1606',
            propertyId: '1606',
            guestName: 'Owner Stay',
            guestEmail: 'owner@oceanviewflats.com',
            guestPhone: '+573000000000',
            checkIn: '2026-10-15',
            checkOut: '2026-10-19',
            totalPrice: 0.0,
            status: ReservationStatus::CONFIRMED,
            paymentStatus: 'approved',
            source: 'owner_stay'
        ));

        // Commercial confirmed stay on 1606 covering Oct 10 -> Oct 12 (nights: 10, 11)
        // Commercial stay precedence overrides the maintenance block on Oct 10 and Oct 11!
        // So deducted unavailable nights on 1606 = 9 - 2 = 7 nights (Oct 12..18).
        // Available nights on 1606 = 31 - 7 = 24 nights. Occupied nights = 2.
        $resRepo->save(Reservation::create(
            reservationUid: 'ovf_comm_precedence',
            propertyId: '1606',
            guestName: 'Commercial Guest',
            guestEmail: 'guest@example.com',
            guestPhone: '+573001234567',
            checkIn: '2026-10-10',
            checkOut: '2026-10-12',
            totalPrice: 1000000.0,
            status: ReservationStatus::CONFIRMED,
            paymentStatus: 'approved',
            source: 'web'
        ));

        $report1606 = $engine->generate(
            new FinancialReportFilter(propertyId: '1606', period: 'this_month'),
            $refClock
        );
        $slice1606 = $report1606->current;

        $this->assertSame(31, $slice1606->totalCalendarNights);
        $this->assertSame(7, $slice1606->deductedNights);
        $this->assertSame(24, $slice1606->availableNights);
        $this->assertSame(2, $slice1606->occupiedNights);
        $this->assertSame(round((2 / 24) * 100.0, 2), $slice1606->occupancyPct);
        $this->assertGreaterThanOrEqual(0.0, $slice1606->occupancyPct);
        $this->assertLessThanOrEqual(100.0, $slice1606->occupancyPct);

        // Now block all 31 nights of October on Property 1707 and verify Available Nights === 0 handles division-by-zero gracefully
        $addBlock(new MaintenanceBlock(
            propertyId: '1707',
            startDate: '2026-10-01',
            endDate: '2026-11-01',
            reason: 'Full month remodel'
        ));

        $report1707 = $engine->generate(
            new FinancialReportFilter(propertyId: '1707', period: 'this_month'),
            $refClock
        );
        $slice1707 = $report1707->current;

        $this->assertSame(31, $slice1707->totalCalendarNights);
        $this->assertSame(31, $slice1707->deductedNights);
        $this->assertSame(0, $slice1707->availableNights);
        $this->assertSame(0, $slice1707->occupiedNights);
        $this->assertSame(0.0, $slice1707->occupancyPct);
        $this->assertSame(0.0, $slice1707->adr);
        $this->assertSame(0.0, $slice1707->revpar);
    }

    #[DataProvider('adapterModeProvider')]
    public function testZeroCopAirbnbExternalReservationCountsTowardOccupancyExcludesFromAdrAndTriggersWarning(string $mode): void
    {
        $harness = $this->createEngineHarness($mode);
        $resRepo = $harness['resRepo'];
        $engine = $harness['engine'];
        $refClock = new DateTimeImmutable('2026-10-17 12:00:00', new DateTimeZone('America/Bogota'));

        // Priced direct stay: 5 nights @ 500,000 COP/night = 2,500,000 COP
        $resRepo->save(Reservation::create(
            reservationUid: 'ovf_priced_1606',
            propertyId: '1606',
            guestName: 'Priced Guest',
            guestEmail: 'priced@example.com',
            guestPhone: '+573001112222',
            checkIn: '2026-10-05',
            checkOut: '2026-10-10',
            totalPrice: 2500000.0,
            status: ReservationStatus::CONFIRMED,
            paymentStatus: 'approved',
            source: 'web'
        ));

        // Unpriced ($0 COP) Airbnb External Reservation: 4 nights
        $resRepo->save(Reservation::create(
            reservationUid: 'res-abnb-unpriced01',
            propertyId: '1707',
            guestName: 'Sophie Laurent (HM4X9P102)',
            guestEmail: 'sophie@guest.oceanviewflats.com',
            guestPhone: '+33612345678',
            checkIn: '2026-10-22',
            checkOut: '2026-10-26',
            totalPrice: 0.0,
            status: ReservationStatus::CONFIRMED,
            paymentStatus: 'approved',
            source: 'airbnb'
        ));

        $report = $engine->generate(new FinancialReportFilter(period: 'this_month'), $refClock);
        $slice = $report->current;

        // Occupied Nights includes both (5 + 4 = 9 nights)
        $this->assertSame(9, $slice->occupiedNights);
        $this->assertSame(round((9 / 62) * 100.0, 2), $slice->occupancyPct);

        // Priced Occupied Nights excludes the $0 Airbnb stay (only 5 nights)
        $this->assertSame(5, $slice->pricedOccupiedNights);
        // ADR = 2,500,000 / 5 = 500,000 COP (not depressed to 2,500,000 / 9)
        $this->assertSame(500000.0, $slice->adr);

        // Warning metadata populated
        $this->assertSame(1, $slice->unpricedAirbnbCount);
        $this->assertSame(['res-abnb-unpriced01'], $slice->unpricedAirbnbUids);
    }

    #[DataProvider('adapterModeProvider')]
    public function testSharedOperationalExpense5050AllocationAndPortfolioReconciliation(string $mode): void
    {
        $harness = $this->createEngineHarness($mode);
        $resRepo = $harness['resRepo'];
        $expRepo = $harness['expRepo'];
        $engine = $harness['engine'];
        $refClock = new DateTimeImmutable('2026-10-17 12:00:00', new DateTimeZone('America/Bogota'));

        // Revenue on 1606: 3,000,000 COP; Revenue on 1707: 2,000,000 COP
        $resRepo->save(Reservation::create(
            reservationUid: 'ovf_rev_1606',
            propertyId: '1606',
            guestName: 'Guest 1606',
            guestEmail: 'g1606@example.com',
            guestPhone: '+573001111111',
            checkIn: '2026-10-02',
            checkOut: '2026-10-07',
            totalPrice: 3000000.0,
            status: ReservationStatus::CONFIRMED,
            paymentStatus: 'approved',
            source: 'web'
        ));
        $resRepo->save(Reservation::create(
            reservationUid: 'ovf_rev_1707',
            propertyId: '1707',
            guestName: 'Guest 1707',
            guestEmail: 'g1707@example.com',
            guestPhone: '+573002222222',
            checkIn: '2026-10-10',
            checkOut: '2026-10-14',
            totalPrice: 2000000.0,
            status: ReservationStatus::CONFIRMED,
            paymentStatus: 'approved',
            source: 'bank_transfer'
        ));

        // Direct 1606 expense: 700,000 COP
        $expRepo->save(new OperationalExpense(
            propertyId: '1606',
            category: ExpenseCategory::HOA_ADMIN,
            title: 'HOA 1606 Oct',
            amount: 700000.0,
            expenseDate: '2026-10-03'
        ));

        // Direct 1707 expense: 500,000 COP
        $expRepo->save(new OperationalExpense(
            propertyId: '1707',
            category: ExpenseCategory::UTILITIES,
            title: 'Utilities 1707 Oct',
            amount: 500000.0,
            expenseDate: '2026-10-05'
        ));

        // Shared portfolio expense: 600,000 COP (allocated 300,000 to 1606 and 300,000 to 1707)
        $expRepo->save(new OperationalExpense(
            propertyId: 'shared',
            category: ExpenseCategory::SUPPLIES,
            title: 'Shared Linens & Coffee Restock',
            amount: 600000.0,
            expenseDate: '2026-10-09'
        ));

        // 1. Portfolio ('all'): 100% of 1606 + 1707 + shared
        $allReport = $engine->generate(new FinancialReportFilter(propertyId: 'all', period: 'this_month'), $refClock);
        $allSlice = $allReport->current;
        $this->assertSame(5000000.0, $allSlice->grossRevenue);
        $this->assertSame(1200000.0, $allSlice->directExpenses);
        $this->assertSame(600000.0, $allSlice->allocatedSharedExpenses);
        $this->assertSame(1800000.0, $allSlice->totalExpenses);
        $this->assertSame(3200000.0, $allSlice->noi);
        $this->assertSame(64.0, $allSlice->noiMarginPct);
        $this->assertSame(36.0, $allSlice->expenseRatioPct);

        // 2. Property 1606: 100% direct (700k) + 50% shared (300k) = 1,000,000 COP
        $r1606 = $engine->generate(new FinancialReportFilter(propertyId: '1606', period: 'this_month'), $refClock)->current;
        $this->assertSame(3000000.0, $r1606->grossRevenue);
        $this->assertSame(700000.0, $r1606->directExpenses);
        $this->assertSame(300000.0, $r1606->allocatedSharedExpenses);
        $this->assertSame(600000.0, $r1606->rawSharedExpenses);
        $this->assertSame(1000000.0, $r1606->totalExpenses);
        $this->assertSame(2000000.0, $r1606->noi);
        $this->assertSame(round((2000000.0 / 3000000.0) * 100.0, 2), $r1606->noiMarginPct);

        // 3. Property 1707: 100% direct (500k) + 50% shared (300k) = 800,000 COP
        $r1707 = $engine->generate(new FinancialReportFilter(propertyId: '1707', period: 'this_month'), $refClock)->current;
        $this->assertSame(2000000.0, $r1707->grossRevenue);
        $this->assertSame(500000.0, $r1707->directExpenses);
        $this->assertSame(300000.0, $r1707->allocatedSharedExpenses);
        $this->assertSame(600000.0, $r1707->rawSharedExpenses);
        $this->assertSame(800000.0, $r1707->totalExpenses);
        $this->assertSame(1200000.0, $r1707->noi);
        $this->assertSame(60.0, $r1707->noiMarginPct);

        // 4. Verify 1606 + 1707 reconciles 100% with Portfolio Total
        $this->assertSame($allSlice->grossRevenue, $r1606->grossRevenue + $r1707->grossRevenue);
        $this->assertSame($allSlice->directExpenses, $r1606->directExpenses + $r1707->directExpenses);
        $this->assertSame($allSlice->allocatedSharedExpenses, $r1606->allocatedSharedExpenses + $r1707->allocatedSharedExpenses);
        $this->assertSame($allSlice->totalExpenses, $r1606->totalExpenses + $r1707->totalExpenses);
        $this->assertSame($allSlice->noi, $r1606->noi + $r1707->noi);
    }

    #[DataProvider('adapterModeProvider')]
    public function testComparativeDeltasPrevPeriodVsYoYAndSingleBookingSourceFiltering(string $mode): void
    {
        $harness = $this->createEngineHarness($mode);
        $resRepo = $harness['resRepo'];
        $expRepo = $harness['expRepo'];
        $engine = $harness['engine'];
        $refClock = new DateTimeImmutable('2026-10-17 12:00:00', new DateTimeZone('America/Bogota'));

        // Current period (Oct 2026): 3,000,000 web + 1,500,000 airbnb = 4,500,000 COP
        $resRepo->save(Reservation::create(
            reservationUid: 'ovf_oct26_web',
            propertyId: '1606',
            guestName: 'Oct Web Guest',
            guestEmail: 'octweb@example.com',
            guestPhone: '+573001112233',
            checkIn: '2026-10-05',
            checkOut: '2026-10-10',
            totalPrice: 3000000.0,
            status: ReservationStatus::CONFIRMED,
            paymentStatus: 'approved',
            source: 'web'
        ));
        $resRepo->save(Reservation::create(
            reservationUid: 'res-abnb-oct26',
            propertyId: '1707',
            guestName: 'Oct Airbnb Guest',
            guestEmail: 'octabnb@guest.oceanviewflats.com',
            guestPhone: '+573004445566',
            checkIn: '2026-10-12',
            checkOut: '2026-10-15',
            totalPrice: 1500000.0,
            status: ReservationStatus::CONFIRMED,
            paymentStatus: 'approved',
            source: 'airbnb'
        ));
        $expRepo->save(new OperationalExpense(
            propertyId: '1606',
            category: ExpenseCategory::CLEANING,
            title: 'Oct Cleaning',
            amount: 500000.0,
            expenseDate: '2026-10-10'
        ));

        // Previous 31-day period (2026-08-31 to 2026-10-01): 3,000,000 COP revenue
        $resRepo->save(Reservation::create(
            reservationUid: 'ovf_sep26_web',
            propertyId: '1606',
            guestName: 'Sep Web Guest',
            guestEmail: 'sepweb@example.com',
            guestPhone: '+573001112233',
            checkIn: '2026-09-10',
            checkOut: '2026-09-16',
            totalPrice: 3000000.0,
            status: ReservationStatus::CONFIRMED,
            paymentStatus: 'approved',
            source: 'web'
        ));

        // YoY period (Oct 2025): 2,250,000 COP revenue
        $resRepo->save(Reservation::create(
            reservationUid: 'ovf_oct25_web',
            propertyId: '1606',
            guestName: 'Oct 2025 Guest',
            guestEmail: 'oct25@example.com',
            guestPhone: '+573001112233',
            checkIn: '2025-10-05',
            checkOut: '2025-10-10',
            totalPrice: 2250000.0,
            status: ReservationStatus::CONFIRMED,
            paymentStatus: 'approved',
            source: 'web'
        ));

        // Compare against Previous Period
        $prevReport = $engine->generate(
            new FinancialReportFilter(period: 'this_month', compare: 'prev_period'),
            $refClock
        );
        $this->assertSame(1500000.0, $prevReport->deltas['gross_revenue']->deltaAmount);
        $this->assertSame(50.0, $prevReport->deltas['gross_revenue']->deltaPct);

        // Compare against Previous Year (YoY)
        $yoyReport = $engine->generate(
            new FinancialReportFilter(period: 'this_month', compare: 'yoy'),
            $refClock
        );
        $this->assertSame(2250000.0, $yoyReport->deltas['gross_revenue']->deltaAmount);
        $this->assertSame(100.0, $yoyReport->deltas['gross_revenue']->deltaPct);

        // Filter by single Booking Source ('airbnb'):
        // Scopes Gross Revenue (1,500,000), Occupied Nights (3), ADR (500,000), RevPAR (1,500,000 / 62)
        // while keeping Operational Expenses (500,000) and All-Sources NOI (4,000,000) at Portfolio level
        $airbnbReport = $engine->generate(
            new FinancialReportFilter(period: 'this_month', source: 'airbnb'),
            $refClock
        );
        $this->assertTrue($airbnbReport->isSingleChannelFiltered());
        $this->assertSame(1500000.0, $airbnbReport->current->grossRevenue);
        $this->assertSame(3, $airbnbReport->current->occupiedNights);
        $this->assertSame(500000.0, $airbnbReport->current->adr);
        $this->assertSame(4500000.0, $airbnbReport->current->allSourcesGrossRevenue);
        $this->assertSame(500000.0, $airbnbReport->current->totalExpenses);
        $this->assertSame(4000000.0, $airbnbReport->current->allSourcesNoi);
    }

    public function testAllTimePeriodPresetsAndCustomDateWindowsWithReferenceClock(): void
    {
        $harness = $this->createEngineHarness('in_memory');
        $engine = $harness['engine'];
        $refClock = new DateTimeImmutable('2026-10-17 15:30:00', new DateTimeZone('America/Bogota'));

        // this_month -> [2026-10-01, 2026-11-01) (31 days, 62 portfolio nights)
        $thisMonth = $engine->generate(new FinancialReportFilter(period: 'this_month'), $refClock);
        $this->assertSame('2026-10-01', $thisMonth->periodStart);
        $this->assertSame('2026-11-01', $thisMonth->periodEnd);
        $this->assertSame(62, $thisMonth->current->totalCalendarNights);

        // last_month -> [2026-09-01, 2026-10-01) (30 days, 60 portfolio nights)
        $lastMonth = $engine->generate(new FinancialReportFilter(period: 'last_month'), $refClock);
        $this->assertSame('2026-09-01', $lastMonth->periodStart);
        $this->assertSame('2026-10-01', $lastMonth->periodEnd);
        $this->assertSame(60, $lastMonth->current->totalCalendarNights);

        // this_quarter -> Q4 2026 [2026-10-01, 2027-01-01) (92 days, 184 portfolio nights)
        $thisQuarter = $engine->generate(new FinancialReportFilter(period: 'this_quarter'), $refClock);
        $this->assertSame('2026-10-01', $thisQuarter->periodStart);
        $this->assertSame('2027-01-01', $thisQuarter->periodEnd);
        $this->assertSame(184, $thisQuarter->current->totalCalendarNights);
        $this->assertTrue($thisQuarter->isMultiMonth());
        $this->assertCount(3, $thisQuarter->monthlyTrends);

        // ytd -> [2026-01-01, 2026-11-01) (304 days, 608 portfolio nights)
        $ytd = $engine->generate(new FinancialReportFilter(period: 'ytd'), $refClock);
        $this->assertSame('2026-01-01', $ytd->periodStart);
        $this->assertSame('2026-11-01', $ytd->periodEnd);
        $this->assertSame(608, $ytd->current->totalCalendarNights);
        $this->assertCount(10, $ytd->monthlyTrends);

        // last_12_months -> [2025-11-01, 2026-11-01) (365 days, 730 portfolio nights)
        $last12 = $engine->generate(new FinancialReportFilter(period: 'last_12_months'), $refClock);
        $this->assertSame('2025-11-01', $last12->periodStart);
        $this->assertSame('2026-11-01', $last12->periodEnd);
        $this->assertSame(730, $last12->current->totalCalendarNights);
        $this->assertCount(12, $last12->monthlyTrends);

        // last_year -> [2025-01-01, 2026-01-01) (365 days, 730 portfolio nights)
        $lastYear = $engine->generate(new FinancialReportFilter(period: 'last_year'), $refClock);
        $this->assertSame('2025-01-01', $lastYear->periodStart);
        $this->assertSame('2026-01-01', $lastYear->periodEnd);
        $this->assertSame(730, $lastYear->current->totalCalendarNights);

        // custom range -> [2026-10-05, 2026-10-15) (10 days, 20 portfolio nights)
        $custom = $engine->generate(
            new FinancialReportFilter(period: 'custom', dateFrom: '2026-10-05', dateTo: '2026-10-15'),
            $refClock
        );
        $this->assertSame('2026-10-05', $custom->periodStart);
        $this->assertSame('2026-10-15', $custom->periodEnd);
        $this->assertSame(20, $custom->current->totalCalendarNights);
        $this->assertFalse($custom->isMultiMonth());

        // Switching from custom back to an explicit preset via fromQuery ignores stale date_from/date_to
        $presetWithStaleDates = FinancialReportFilter::fromQuery([
            'period' => 'last_month',
            'date_from' => '2026-10-05',
            'date_to' => '2026-10-15',
            'property_id' => 'shared', // unrequested 'shared' falls back to 'all'
        ]);
        $this->assertSame('all', $presetWithStaleDates->propertyId);
        $this->assertSame('last_month', $presetWithStaleDates->period);
        $this->assertSame('', $presetWithStaleDates->dateFrom);
        $this->assertSame('', $presetWithStaleDates->dateTo);
        [$resolvedStart, $resolvedEnd] = $presetWithStaleDates->resolveDateWindow($refClock);
        $this->assertSame('2026-09-01', $resolvedStart);
        $this->assertSame('2026-10-01', $resolvedEnd);
    }

    #[DataProvider('adapterModeProvider')]
    public function testMultiPropertyComparisonMatrixAndMonthlyTrendsReconcileAcrossAllDimensions(string $mode): void
    {
        $harness = $this->createEngineHarness($mode);
        $resRepo = $harness['resRepo'];
        $addBlock = $harness['addBlock'];
        $expRepo = $harness['expRepo'];
        $engine = $harness['engine'];
        $refClock = new DateTimeImmutable('2026-10-17 12:00:00', new DateTimeZone('America/Bogota'));

        // Seed reservations across Q4 2026 (Oct, Nov, Dec) covering all 3 commercial Booking Sources + Policy Retention:
        // 1. Oct 1606 Direct Online: 6 nights, 3,000,000 COP
        $resRepo->save(Reservation::create(
            reservationUid: 'ovf_q4_oct_1606_web',
            propertyId: '1606',
            guestName: 'Markus Vance',
            guestEmail: 'markus@example.com',
            guestPhone: '+573001112233',
            checkIn: '2026-10-05',
            checkOut: '2026-10-11',
            totalPrice: 3000000.0,
            status: ReservationStatus::CONFIRMED,
            paymentStatus: 'approved',
            source: 'web'
        ));
        // 2. Oct-Nov Cross-Month 1707 Direct Manual: Oct 29 -> Nov 03 (5 nights: 3 in Oct, 2 in Nov), 2,500,000 COP
        $resRepo->save(Reservation::create(
            reservationUid: 'res-man-q4-1707',
            propertyId: '1707',
            guestName: 'Lucia Fernandez',
            guestEmail: 'lucia@example.com',
            guestPhone: '+573004445566',
            checkIn: '2026-10-29',
            checkOut: '2026-11-03',
            totalPrice: 2500000.0,
            status: ReservationStatus::CONFIRMED,
            paymentStatus: 'approved',
            source: 'bank_transfer'
        ));
        // 3. Nov 1606 External OTA Airbnb: 4 nights, 2,200,000 COP
        $resRepo->save(Reservation::create(
            reservationUid: 'res-abnb-q4-1606',
            propertyId: '1606',
            guestName: 'David Miller',
            guestEmail: 'david@guest.oceanviewflats.com',
            guestPhone: '+13055550199',
            checkIn: '2026-11-10',
            checkOut: '2026-11-14',
            totalPrice: 2200000.0,
            status: ReservationStatus::CONFIRMED,
            paymentStatus: 'approved',
            source: 'airbnb'
        ));
        // 4. Dec 1707 Settled Cancelled Policy Retention: 3 nights scheduled, 1,200,000 total, 600,000 refunded => 600,000 retained
        $resRepo->save(Reservation::create(
            reservationUid: 'ovf_q4_dec_1707_ret',
            propertyId: '1707',
            guestName: 'Carlos Mendoza',
            guestEmail: 'carlos@example.com',
            guestPhone: '+573007778899',
            checkIn: '2026-12-15',
            checkOut: '2026-12-18',
            totalPrice: 1200000.0,
            status: ReservationStatus::CANCELLED,
            paymentStatus: 'partially_refunded',
            source: 'web',
            refundedAmount: 600000.0
        ));

        // Maintenance block on 1606 in Oct (2 nights)
        $addBlock(new MaintenanceBlock(
            propertyId: '1606',
            startDate: '2026-10-20',
            endDate: '2026-10-22',
            reason: 'Preventative AC service'
        ));

        // Expenses across categories and months (1606, 1707, and shared):
        $expRepo->save(new OperationalExpense(
            propertyId: '1606',
            category: ExpenseCategory::HOA_ADMIN,
            title: 'HOA 1606 Oct',
            amount: 720000.0,
            expenseDate: '2026-10-02'
        ));
        $expRepo->save(new OperationalExpense(
            propertyId: '1707',
            category: ExpenseCategory::UTILITIES,
            title: 'Utilities 1707 Nov',
            amount: 480000.0,
            expenseDate: '2026-11-05'
        ));
        $expRepo->save(new OperationalExpense(
            propertyId: 'shared',
            category: ExpenseCategory::SUPPLIES,
            title: 'Shared Linens Dec',
            amount: 640000.0,
            expenseDate: '2026-12-08'
        ));

        $report = $engine->generate(
            new FinancialReportFilter(propertyId: 'all', period: 'this_quarter'),
            $refClock
        );

        $this->assertTrue($report->showMultiPropertyMatrix());
        $this->assertTrue($report->isMultiMonth());
        $this->assertCount(3, $report->monthlyTrends);

        $sAll = $report->portfolioSlice;
        $s1606 = $report->property1606Slice;
        $s1707 = $report->property1707Slice;

        // 1. Matrix horizontal reconciliation: Property 1606 + Property 1707 === Portfolio Total
        $this->assertSame($sAll->totalCalendarNights, $s1606->totalCalendarNights + $s1707->totalCalendarNights);
        $this->assertSame($sAll->availableNights, $s1606->availableNights + $s1707->availableNights);
        $this->assertSame($sAll->occupiedNights, $s1606->occupiedNights + $s1707->occupiedNights);
        $this->assertSame(
            $sAll->bySource['direct_online']['active_revenue'],
            $s1606->bySource['direct_online']['active_revenue'] + $s1707->bySource['direct_online']['active_revenue']
        );
        $this->assertSame(
            $sAll->bySource['direct_manual']['active_revenue'],
            $s1606->bySource['direct_manual']['active_revenue'] + $s1707->bySource['direct_manual']['active_revenue']
        );
        $this->assertSame(
            $sAll->bySource['airbnb']['active_revenue'],
            $s1606->bySource['airbnb']['active_revenue'] + $s1707->bySource['airbnb']['active_revenue']
        );
        $this->assertSame($sAll->policyRetention, $s1606->policyRetention + $s1707->policyRetention);
        $this->assertSame($sAll->grossRevenue, $s1606->grossRevenue + $s1707->grossRevenue);
        $this->assertSame($sAll->directExpenses, $s1606->directExpenses + $s1707->directExpenses);
        $this->assertSame($sAll->allocatedSharedExpenses, $s1606->allocatedSharedExpenses + $s1707->allocatedSharedExpenses);
        $this->assertSame($sAll->totalExpenses, $s1606->totalExpenses + $s1707->totalExpenses);
        $this->assertSame($sAll->noi, $s1606->noi + $s1707->noi);

        // 2. Matrix vertical revenue reconciliation: Direct Online + Direct Manual + Airbnb + Policy Retention === Gross Revenue
        foreach ([$sAll, $s1606, $s1707] as $slice) {
            $verticalRevenueSum = round(
                $slice->bySource['direct_online']['active_revenue']
                + $slice->bySource['direct_manual']['active_revenue']
                + $slice->bySource['airbnb']['active_revenue']
                + $slice->policyRetention,
                2
            );
            $this->assertSame($slice->grossRevenue, $verticalRevenueSum);
        }

        // 3. Monthly P&L Trend rows (2026-10, 2026-11, 2026-12) reconcile to Q4 Portfolio Total
        $this->assertSame('2026-10', $report->monthlyTrends[0]->periodLabel);
        $this->assertSame('2026-11', $report->monthlyTrends[1]->periodLabel);
        $this->assertSame('2026-12', $report->monthlyTrends[2]->periodLabel);

        // Oct: 3,000,000 (1606 web) + 1,500,000 (3/5 of 1707 manual) = 4,500,000 rev; 720,000 exp
        $this->assertSame(4500000.0, $report->monthlyTrends[0]->grossRevenue);
        $this->assertSame(720000.0, $report->monthlyTrends[0]->totalExpenses);
        $this->assertSame(3780000.0, $report->monthlyTrends[0]->noi);

        // Nov: 1,000,000 (2/5 of 1707 manual) + 2,200,000 (1606 airbnb) = 3,200,000 rev; 480,000 exp
        $this->assertSame(3200000.0, $report->monthlyTrends[1]->grossRevenue);
        $this->assertSame(480000.0, $report->monthlyTrends[1]->totalExpenses);
        $this->assertSame(2720000.0, $report->monthlyTrends[1]->noi);

        // Dec: 600,000 (1707 policy retention) rev; 640,000 exp => -40,000 NOI
        $this->assertSame(600000.0, $report->monthlyTrends[2]->grossRevenue);
        $this->assertSame(640000.0, $report->monthlyTrends[2]->totalExpenses);
        $this->assertSame(-40000.0, $report->monthlyTrends[2]->noi);

        $sumAvailable = 0;
        $sumOccupied = 0;
        $sumGrossRev = 0.0;
        $sumExpenses = 0.0;
        $sumNoi = 0.0;
        foreach ($report->monthlyTrends as $mSlice) {
            $sumAvailable += $mSlice->availableNights;
            $sumOccupied += $mSlice->occupiedNights;
            $sumGrossRev = round($sumGrossRev + $mSlice->grossRevenue, 2);
            $sumExpenses = round($sumExpenses + $mSlice->totalExpenses, 2);
            $sumNoi = round($sumNoi + $mSlice->noi, 2);
        }

        $this->assertSame($sAll->availableNights, $sumAvailable);
        $this->assertSame($sAll->occupiedNights, $sumOccupied);
        $this->assertSame($sAll->grossRevenue, $sumGrossRev);
        $this->assertSame($sAll->totalExpenses, $sumExpenses);
        $this->assertSame($sAll->noi, $sumNoi);
    }
}
