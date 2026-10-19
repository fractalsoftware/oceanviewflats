<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Privacy;

use DateTimeImmutable;
use DateTimeZone;
use OceanViewFlats\Domain\Database\MigrationRunner;
use OceanViewFlats\Domain\Finance\FinancialReportEngine;
use OceanViewFlats\Domain\Finance\FinancialReportFilter;
use OceanViewFlats\Domain\Finance\PdoOperationalExpenseRepository;
use OceanViewFlats\Domain\Fulfillment\AdminContext;
use OceanViewFlats\Domain\Privacy\PdoPiiRedactionService;
use OceanViewFlats\Domain\Privacy\PiiRedactionServiceInterface;
use OceanViewFlats\Domain\Reservation\InvalidReservationStateException;
use OceanViewFlats\Domain\Reservation\PdoMaintenanceBlockRepository;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationNotFoundException;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use OceanViewFlats\Domain\Reservation\Search\PdoReservationSearchAdapter;
use OceanViewFlats\Domain\Security\PayloadEncryptor;
use PDO;
use PHPUnit\Framework\TestCase;

final class PiiRedactionServiceTest extends TestCase
{
    private PDO $pdo;
    private PdoReservationRepository $reservationRepository;
    private PdoReservationSearchAdapter $searchAdapter;
    private PdoPiiRedactionService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        MigrationRunner::run($this->pdo, 'sqlite_test');

        $this->reservationRepository = new PdoReservationRepository($this->pdo);
        $this->searchAdapter = new PdoReservationSearchAdapter($this->pdo);
        $this->service = new PdoPiiRedactionService($this->pdo, $this->reservationRepository);
    }

    public function testRedactReservationThrowsNotFoundWhenReservationDoesNotExist(): void
    {
        $this->expectException(ReservationNotFoundException::class);
        $this->service->redactReservation('res-nonexistent-999');
    }

    public function testRedactReservationRejectsActiveAndUpcomingConfirmedAndPendingStays(): void
    {
        $tz = new DateTimeZone('America/Bogota');
        $now = new DateTimeImmutable('2026-10-19 14:00:00', $tz);

        // 1. Active confirmed stay (check_out today 2026-10-19 is not concluded until 23:59:59 COT)
        $activeStay = Reservation::create(
            reservationUid: 'res-active-stay',
            propertyId: '1707',
            guestName: 'Active Guest',
            guestEmail: 'active@example.com',
            guestPhone: '+573001112233',
            checkIn: '2026-10-15',
            checkOut: '2026-10-19',
            totalPrice: 2000000.0,
            status: ReservationStatus::CONFIRMED,
            doorCode: '0123456#'
        );
        $this->reservationRepository->save($activeStay);

        // 2. Upcoming confirmed stay
        $upcomingStay = Reservation::create(
            reservationUid: 'res-upcoming-stay',
            propertyId: '1606',
            guestName: 'Upcoming Guest',
            guestEmail: 'upcoming@example.com',
            guestPhone: '+573004445566',
            checkIn: '2026-11-01',
            checkOut: '2026-11-05',
            totalPrice: 1800000.0,
            status: ReservationStatus::CONFIRMED,
            doorCode: '0654321#'
        );
        $this->reservationRepository->save($upcomingStay);

        // 3. Pending payment stay (even with past dates)
        $pendingStay = Reservation::create(
            reservationUid: 'res-pending-stay',
            propertyId: '1707',
            guestName: 'Pending Guest',
            guestEmail: 'pending@example.com',
            guestPhone: '+573007778899',
            checkIn: '2026-05-01',
            checkOut: '2026-05-05',
            totalPrice: 1500000.0,
            status: ReservationStatus::PENDING_PAYMENT
        );
        $this->reservationRepository->save($pendingStay);

        foreach (['res-active-stay', 'res-upcoming-stay', 'res-pending-stay'] as $uid) {
            try {
                $this->service->redactReservation($uid, null, $now);
                $this->fail("Expected InvalidReservationStateException for {$uid}");
            } catch (InvalidReservationStateException $e) {
                $this->assertSame($uid, $e->reservationUid);
                $this->assertSame('redact_pii', $e->attemptedTransition);
            }
        }

        // Verify active stay door_code and guest identity were untouched
        $reloadedActive = $this->reservationRepository->findByUid('res-active-stay');
        $this->assertNotNull($reloadedActive);
        $this->assertSame('Active Guest', $reloadedActive->guestName);
        $this->assertSame('0123456#', $reloadedActive->doorCode);
        $this->assertNull($reloadedActive->piiRedactedAt);
    }

    public function testRedactReservationAtomicallyScrubsConcludedAndCancelledStaysAcrossAllTables(): void
    {
        $tz = new DateTimeZone('America/Bogota');
        $now = new DateTimeImmutable('2026-10-19 15:30:00', $tz);
        $consentTime = new DateTimeImmutable('2026-06-01 10:00:00', $tz);

        // 1. Seed a Concluded Reservation with Guest Registry, Condominium Clearance, and Historical Audit Logs
        $concluded = Reservation::create(
            reservationUid: 'res-concluded-101',
            propertyId: '1707',
            guestName: 'Carlos Mendoza',
            guestEmail: 'carlos.mendoza@example.com',
            guestPhone: '+573109998877',
            checkIn: '2026-06-10',
            checkOut: '2026-06-15',
            totalPrice: 3250000.0,
            status: ReservationStatus::CONFIRMED,
            paymentMethodId: 'pse',
            mercadopagoPaymentId: 'mp-pay-901',
            paymentStatus: 'approved',
            registryCompleted: true,
            registryCompletedAt: new DateTimeImmutable('2026-06-09 12:00:00', $tz),
            doorCode: '0849201#',
            source: 'web',
            notes: 'Guest arriving late at 11pm with 2 suitcases',
            refundedAmount: 250000.0,
            privacyPolicyVersion: '2026-10',
            privacyConsentAt: $consentTime,
            privacyConsentIp: '190.24.10.55'
        );
        $this->reservationRepository->save($concluded);

        $encryptedPayload = PayloadEncryptor::encrypt([
            [
                'first_name' => 'Carlos',
                'last_name' => 'Mendoza',
                'doc_type' => 'CC',
                'doc_num' => '1020304050',
                'age' => 38,
                'country' => 'Colombia',
                'phone' => '+573109998877',
                'email' => 'carlos.mendoza@example.com',
            ],
        ]);

        $this->pdo->prepare("
            INSERT INTO guest_registries (
                reservation_uid, property_id, check_in, check_out, guest_count,
                guests_payload, car_plates, car_model, ip_address,
                privacy_policy_version, privacy_consent_at, privacy_consent_ip, minor_guardian_consent
            ) VALUES (
                :uid, '1707', '2026-06-10', '2026-06-15', 2,
                :payload, 'ABC-123', 'Mazda CX-5', '190.24.10.55',
                '2026-10', '2026-06-09 12:00:00', '190.24.10.55', 1
            )
        ")->execute([
            ':uid' => 'res-concluded-101',
            ':payload' => $encryptedPayload,
        ]);

        $this->pdo->prepare("
            INSERT INTO condominium_clearances (
                reservation_uid, property_id, status, clearance_number, request_payload, error_message, attempts, last_attempt_at, synced_at
            ) VALUES (
                'res-concluded-101', '1707', 'synced', 'CLR-8821', :req_payload, 'Previous transient timeout warning for Carlos Mendoza', 2, '2026-06-09 12:01:00', '2026-06-09 12:01:00'
            )
        ")->execute([
            ':req_payload' => json_encode(['step1' => ['guest' => 'Carlos Mendoza'], 'step2' => ['ide' => ['****4050']]]),
        ]);

        $this->pdo->prepare("
            INSERT INTO admin_audit_logs (
                admin_user_id, action, entity_type, entity_id, payload_before, payload_after, ip_address, user_agent
            ) VALUES (
                1, 'reservation_updated', 'reservation', 'res-concluded-101',
                :before, :after, '127.0.0.1', 'PHPUnit'
            )
        ")->execute([
            ':before' => json_encode([
                'guest_name' => 'Carlos M.',
                'guest_email' => 'old.carlos@example.com',
                'guest_phone' => '+573000000000',
                'total_price' => 3250000.0,
            ]),
            ':after' => json_encode([
                'guest_name' => 'Carlos Mendoza',
                'guest_email' => 'carlos.mendoza@example.com',
                'guest_phone' => '+573109998877',
                'door_code' => '0849201#',
                'notes' => 'Guest arriving late at 11pm with 2 suitcases',
                'total_price' => 3250000.0,
                'guest_registry' => [
                    'guest_count' => 2,
                    'car_plates' => 'ABC-123',
                    'car_model' => 'Mazda CX-5',
                    'occupants' => [
                        ['first_name' => 'Carlos', 'doc_num' => '****4050'],
                    ],
                ],
            ]),
        ]);

        // 2. Execute PII redaction as an Admin operator
        $admin = new AdminContext(adminUserId: 7, ipAddress: '10.0.0.42', userAgent: 'AdminConsole/1.0');
        $result = $this->service->redactReservation('res-concluded-101', $admin, $now);

        $this->assertTrue($result->success);
        $this->assertFalse($result->alreadyRedacted);
        $this->assertSame('res-concluded-101', $result->reservationUid);
        $this->assertSame('2026-10-19 15:30:00', $result->redactedAt);
        $this->assertSame(1, $result->registriesRedacted);
        $this->assertSame(1, $result->clearancesRedacted);
        $this->assertSame(1, $result->auditLogsScrubbed);
        $this->assertNotNull($result->auditLogId);

        // 3. Verify reservations table
        $redactedRes = $this->reservationRepository->findByUid('res-concluded-101');
        $this->assertNotNull($redactedRes);
        $this->assertSame(PiiRedactionServiceInterface::REDACTED_GUEST_NAME, $redactedRes->guestName);
        $this->assertSame(PiiRedactionServiceInterface::REDACTED_GUEST_EMAIL, $redactedRes->guestEmail);
        $this->assertSame(PiiRedactionServiceInterface::REDACTED_GUEST_PHONE, $redactedRes->guestPhone);
        $this->assertNull($redactedRes->doorCode);
        $this->assertNull($redactedRes->notes);
        $this->assertTrue($redactedRes->isPiiRedacted());
        $this->assertSame('2026-10-19 15:30:00', $redactedRes->piiRedactedAt?->format('Y-m-d H:i:s'));

        // Preserved financial, stay, and consent proof columns on reservations
        $this->assertSame(3250000.0, $redactedRes->totalPrice);
        $this->assertSame(250000.0, $redactedRes->refundedAmount);
        $this->assertSame('2026-06-10', $redactedRes->checkIn);
        $this->assertSame('2026-06-15', $redactedRes->checkOut);
        $this->assertSame('1707', $redactedRes->propertyId);
        $this->assertSame('web', $redactedRes->source);
        $this->assertSame(ReservationStatus::CONFIRMED, $redactedRes->status);
        $this->assertSame('approved', $redactedRes->paymentStatus);
        $this->assertSame('2026-10', $redactedRes->privacyPolicyVersion);
        $this->assertSame('2026-06-01 10:00:00', $redactedRes->privacyConsentAt?->format('Y-m-d H:i:s'));
        $this->assertSame('190.24.10.55', $redactedRes->privacyConsentIp);

        // 4. Verify guest_registries table
        $regRow = $this->pdo->query("SELECT * FROM guest_registries WHERE reservation_uid = 'res-concluded-101'")->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($regRow);
        $this->assertSame('[]', $regRow['guests_payload']);
        $this->assertNull($regRow['car_plates']);
        $this->assertNull($regRow['car_model']);
        $this->assertNull($regRow['ip_address']);
        $this->assertSame('2026-10-19 15:30:00', $regRow['pii_redacted_at']);
        $this->assertSame(2, (int) $regRow['guest_count']);
        $this->assertSame('1707', $regRow['property_id']);
        $this->assertSame('2026-06-10', $regRow['check_in']);
        $this->assertSame('2026-06-15', $regRow['check_out']);
        $this->assertSame('2026-10', $regRow['privacy_policy_version']);
        $this->assertSame('2026-06-09 12:00:00', $regRow['privacy_consent_at']);
        $this->assertSame('190.24.10.55', $regRow['privacy_consent_ip']);
        $this->assertSame(1, (int) $regRow['minor_guardian_consent']);

        // 5. Verify condominium_clearances table
        $clrRow = $this->pdo->query("SELECT * FROM condominium_clearances WHERE reservation_uid = 'res-concluded-101'")->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($clrRow);
        $this->assertNull($clrRow['request_payload']);
        $this->assertNull($clrRow['error_message']);
        $this->assertSame('synced', $clrRow['status']);
        $this->assertSame('CLR-8821', $clrRow['clearance_number']);

        // 6. Verify historical admin_audit_logs scrubbing and pii_redacted event
        $dossier = $this->searchAdapter->findWithAuditTrail('res-concluded-101');
        $this->assertNotNull($dossier);
        $this->assertSame('2026-10-19 15:30:00', $dossier->piiRedactedAt);
        $this->assertTrue($dossier->isPiiRedacted());
        $this->assertCount(2, $dossier->auditLogs);

        // Most recent log is pii_redacted
        $latestLog = $dossier->auditLogs[0];
        $this->assertSame('pii_redacted', $latestLog['action']);
        $this->assertSame(7, (int) $latestLog['admin_user_id']);
        $this->assertSame('10.0.0.42', $latestLog['ip_address']);

        // Historical log has PII scrubbed while preserving total_price and guest_count
        $historicalLog = $dossier->auditLogs[1];
        $this->assertSame('reservation_updated', $historicalLog['action']);
        $beforeDecoded = json_decode((string) $historicalLog['payload_before'], true);
        $afterDecoded = json_decode((string) $historicalLog['payload_after'], true);
        $this->assertIsArray($beforeDecoded);
        $this->assertIsArray($afterDecoded);
        $this->assertSame('Redacted Guest', $beforeDecoded['guest_name']);
        $this->assertSame('redacted@oceanviewflats.local', $beforeDecoded['guest_email']);
        $this->assertSame('REDACTED', $beforeDecoded['guest_phone']);
        $this->assertSame(3250000, $beforeDecoded['total_price']);

        $this->assertSame('Redacted Guest', $afterDecoded['guest_name']);
        $this->assertSame('redacted@oceanviewflats.local', $afterDecoded['guest_email']);
        $this->assertSame('REDACTED', $afterDecoded['guest_phone']);
        $this->assertNull($afterDecoded['door_code']);
        $this->assertNull($afterDecoded['notes']);
        $this->assertSame(3250000, $afterDecoded['total_price']);
        $this->assertSame(2, $afterDecoded['guest_registry']['guest_count']);
        $this->assertNull($afterDecoded['guest_registry']['car_plates']);
        $this->assertSame([], $afterDecoded['guest_registry']['occupants']);

        // 7. Also verify Cancelled Reservation with future check-out date can be immediately redacted
        $cancelledFuture = Reservation::create(
            reservationUid: 'res-cancelled-future',
            propertyId: '1606',
            guestName: 'Cancelled Guest',
            guestEmail: 'cancelled@example.com',
            guestPhone: '+573005554433',
            checkIn: '2026-12-10',
            checkOut: '2026-12-15',
            totalPrice: 2400000.0,
            status: ReservationStatus::CANCELLED,
            paymentStatus: 'approved',
            refundedAmount: 1920000.0,
            notes: 'Cancelled by guest'
        );
        $this->reservationRepository->save($cancelledFuture);

        $cancelledResult = $this->service->redactReservation('res-cancelled-future', null, $now);
        $this->assertTrue($cancelledResult->success);
        $reloadedCancelled = $this->reservationRepository->findByUid('res-cancelled-future');
        $this->assertNotNull($reloadedCancelled);
        $this->assertSame('Redacted Guest', $reloadedCancelled->guestName);
        $this->assertSame(2400000.0, $reloadedCancelled->totalPrice);
        $this->assertSame(1920000.0, $reloadedCancelled->refundedAmount);
        $this->assertSame(ReservationStatus::CANCELLED, $reloadedCancelled->status);
    }

    public function testRedactExpiredConcludedReservationsEnforces90DayBogotaRetentionWindow(): void
    {
        $tz = new DateTimeZone('America/Bogota');
        // Reference date: 2026-10-19 -> 90 days ago is 2026-07-21
        $now = new DateTimeImmutable('2026-10-19 10:00:00', $tz);

        // 1. Expired concluded stay (check_out = 2026-07-20 -> 91 days ago -> MUST BE REDACTED)
        $expired91 = Reservation::create(
            reservationUid: 'res-expired-91d',
            propertyId: '1707',
            guestName: 'Expired 91 Days',
            guestEmail: 'expired91@example.com',
            guestPhone: '+573001110091',
            checkIn: '2026-07-15',
            checkOut: '2026-07-20',
            totalPrice: 1800000.0,
            status: ReservationStatus::CONFIRMED
        );
        $this->reservationRepository->save($expired91);

        // 2. Boundary stay at exactly 90 days ago (check_out = 2026-07-21 -> <= 90 days -> MUST REMAIN UNTOUCHED)
        $boundary90 = Reservation::create(
            reservationUid: 'res-boundary-90d',
            propertyId: '1606',
            guestName: 'Boundary 90 Days',
            guestEmail: 'boundary90@example.com',
            guestPhone: '+573001110090',
            checkIn: '2026-07-18',
            checkOut: '2026-07-21',
            totalPrice: 1200000.0,
            status: ReservationStatus::CONFIRMED
        );
        $this->reservationRepository->save($boundary90);

        // 3. Recent concluded stay (check_out = 2026-10-01 -> 18 days ago -> MUST REMAIN UNTOUCHED)
        $recent18 = Reservation::create(
            reservationUid: 'res-recent-18d',
            propertyId: '1707',
            guestName: 'Recent 18 Days',
            guestEmail: 'recent18@example.com',
            guestPhone: '+573001110018',
            checkIn: '2026-09-28',
            checkOut: '2026-10-01',
            totalPrice: 1500000.0,
            status: ReservationStatus::CONFIRMED
        );
        $this->reservationRepository->save($recent18);

        $count = $this->service->redactExpiredConcludedReservations(90, $now);
        $this->assertSame(1, $count);

        $reloadedExpired = $this->reservationRepository->findByUid('res-expired-91d');
        $this->assertNotNull($reloadedExpired);
        $this->assertTrue($reloadedExpired->isPiiRedacted());
        $this->assertSame('Redacted Guest', $reloadedExpired->guestName);

        $reloadedBoundary = $this->reservationRepository->findByUid('res-boundary-90d');
        $this->assertNotNull($reloadedBoundary);
        $this->assertFalse($reloadedBoundary->isPiiRedacted());
        $this->assertSame('Boundary 90 Days', $reloadedBoundary->guestName);

        $reloadedRecent = $this->reservationRepository->findByUid('res-recent-18d');
        $this->assertNotNull($reloadedRecent);
        $this->assertFalse($reloadedRecent->isPiiRedacted());
        $this->assertSame('Recent 18 Days', $reloadedRecent->guestName);

        // Re-running is idempotent and returns 0 newly redacted stays
        $secondCount = $this->service->redactExpiredConcludedReservations(90, $now);
        $this->assertSame(0, $secondCount);
    }

    public function testFinancialReportEngineProducesBitForBitIdenticalMetricsBeforeAndAfterPiiRedaction(): void
    {
        $tz = new DateTimeZone('America/Bogota');
        $now = new DateTimeImmutable('2026-10-19 12:00:00', $tz);

        // Seed concluded direct, concluded Airbnb, and cancelled with policy retention reservations in June 2026
        $resDirect = Reservation::create(
            reservationUid: 'res-fin-direct',
            propertyId: '1707',
            guestName: 'Lucia Fernandez',
            guestEmail: 'lucia@example.com',
            guestPhone: '+573151112233',
            checkIn: '2026-06-02',
            checkOut: '2026-06-07',
            totalPrice: 2500000.0,
            status: ReservationStatus::CONFIRMED,
            paymentMethodId: 'pse',
            mercadopagoPaymentId: 'mp-111',
            paymentStatus: 'approved',
            doorCode: '0998877#',
            source: 'web',
            notes: 'VIP guest'
        );
        $this->reservationRepository->save($resDirect);

        $resAirbnb = Reservation::create(
            reservationUid: 'res-fin-airbnb',
            propertyId: '1606',
            guestName: 'John Smith',
            guestEmail: 'airbnb-hm123@guest.oceanviewflats.com',
            guestPhone: '+12025550199',
            checkIn: '2026-06-10',
            checkOut: '2026-06-14',
            totalPrice: 1960000.0,
            status: ReservationStatus::CONFIRMED,
            doorCode: '0445566#',
            source: 'airbnb',
            externalConfirmationCode: 'HM12345678'
        );
        $this->reservationRepository->save($resAirbnb);

        $resCancelled = Reservation::create(
            reservationUid: 'res-fin-cancelled',
            propertyId: '1707',
            guestName: 'Elena Gomez',
            guestEmail: 'elena@example.com',
            guestPhone: '+573123334455',
            checkIn: '2026-06-20',
            checkOut: '2026-06-24',
            totalPrice: 2000000.0,
            status: ReservationStatus::CANCELLED,
            paymentMethodId: 'visa',
            mercadopagoPaymentId: 'mp-222',
            paymentStatus: 'approved',
            source: 'web',
            refundedAmount: 1600000.0 // 400,000 COP policy retention
        );
        $this->reservationRepository->save($resCancelled);

        $blockRepo = new PdoMaintenanceBlockRepository($this->pdo);
        $expenseRepo = new PdoOperationalExpenseRepository($this->pdo);
        $engine = new FinancialReportEngine(
            reservationRepository: $this->reservationRepository,
            maintenanceBlockSource: $blockRepo,
            expenseRepository: $expenseRepo
        );

        $filter = FinancialReportFilter::fromArray([
            'preset' => 'custom',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'property_id' => 'all',
        ]);

        // Compute financial report BEFORE PII redaction
        $beforeReport = $engine->generate($filter, $now);

        // Redact all three reservations
        $this->service->redactReservation('res-fin-direct', null, $now);
        $this->service->redactReservation('res-fin-airbnb', null, $now);
        $this->service->redactReservation('res-fin-cancelled', null, $now);

        // Compute financial report AFTER PII redaction
        $afterReport = $engine->generate($filter, $now);

        foreach (['portfolioSlice', 'property1606Slice', 'property1707Slice', 'current'] as $sliceProp) {
            $beforeSlice = $beforeReport->{$sliceProp};
            $afterSlice = $afterReport->{$sliceProp};

            $this->assertSame($beforeSlice->grossRevenue, $afterSlice->grossRevenue, "{$sliceProp} Gross Revenue must match");
            $this->assertSame($beforeSlice->activeStayRevenue, $afterSlice->activeStayRevenue, "{$sliceProp} Active Stay Revenue must match");
            $this->assertSame($beforeSlice->policyRetention, $afterSlice->policyRetention, "{$sliceProp} Policy Retention must match");
            $this->assertSame($beforeSlice->refundedAmount, $afterSlice->refundedAmount, "{$sliceProp} Refunded Amount must match");
            $this->assertSame($beforeSlice->grossCharged, $afterSlice->grossCharged, "{$sliceProp} Gross Charged must match");
            $this->assertSame($beforeSlice->occupiedNights, $afterSlice->occupiedNights, "{$sliceProp} Occupied Nights must match");
            $this->assertSame($beforeSlice->availableNights, $afterSlice->availableNights, "{$sliceProp} Available Nights must match");
            $this->assertSame($beforeSlice->occupancyPct, $afterSlice->occupancyPct, "{$sliceProp} Occupancy Rate must match");
            $this->assertSame($beforeSlice->adr, $afterSlice->adr, "{$sliceProp} ADR must match");
            $this->assertSame($beforeSlice->revpar, $afterSlice->revpar, "{$sliceProp} RevPAR must match");
            $this->assertSame($beforeSlice->noi, $afterSlice->noi, "{$sliceProp} NOI must match");
            $this->assertSame($beforeSlice->bySource, $afterSlice->bySource, "{$sliceProp} Source Breakdown must match");
        }

        // Verify expected non-zero numbers on portfolio slice
        $this->assertSame(4860000.0, $afterReport->portfolioSlice->grossRevenue);
        $this->assertSame(9, $afterReport->portfolioSlice->occupiedNights);
        $this->assertSame(400000.0, $afterReport->portfolioSlice->policyRetention);
    }
}
