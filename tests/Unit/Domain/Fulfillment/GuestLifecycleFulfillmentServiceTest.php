<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Fulfillment;

use DateTimeImmutable;
use OceanViewFlats\Domain\Fulfillment\AdminContext;
use OceanViewFlats\Domain\Fulfillment\CondominiumClearance;
use OceanViewFlats\Domain\Fulfillment\CondominiumClearanceSyncInterface;
use OceanViewFlats\Domain\Fulfillment\GuestLifecycleFulfillmentService;
use OceanViewFlats\Domain\Fulfillment\GuestRegistrySubmission;
use OceanViewFlats\Domain\Fulfillment\HostRegistryEmailRenderer;
use OceanViewFlats\Domain\Fulfillment\InMemoryEmailSender;
use OceanViewFlats\Domain\Fulfillment\InMemorySpreadsheetSync;
use OceanViewFlats\Domain\Fulfillment\OccupantDetails;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use PDO;
use PHPUnit\Framework\TestCase;

final class GuestLifecycleFulfillmentServiceTest extends TestCase
{
    private PDO $pdo;
    private PdoReservationRepository $reservationRepository;
    private HostRegistryEmailRenderer $hostRegistryRenderer;
    private InMemoryEmailSender $emailSender;
    private InMemorySpreadsheetSync $spreadsheetSync;
    private GuestLifecycleFulfillmentService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec("
            CREATE TABLE reservations (
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
            );

            CREATE TABLE guest_registries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_uid TEXT,
                property_id TEXT,
                check_in TEXT,
                check_out TEXT,
                guest_count INTEGER DEFAULT 1,
                guests_payload TEXT,
                car_plates TEXT,
                car_model TEXT,
                ip_address TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE admin_audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_user_id INTEGER,
                action TEXT,
                entity_type TEXT,
                entity_id TEXT,
                payload_before TEXT,
                payload_after TEXT,
                ip_address TEXT,
                user_agent TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE condominium_clearances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_uid TEXT UNIQUE NOT NULL,
                property_id TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                clearance_number TEXT DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                request_payload TEXT DEFAULT NULL,
                attempts INTEGER DEFAULT 0,
                last_attempt_at TEXT DEFAULT NULL,
                synced_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->reservationRepository = new PdoReservationRepository($this->pdo);
        $this->hostRegistryRenderer = new HostRegistryEmailRenderer(new DateTimeImmutable('2026-10-01 12:00:00'));
        $this->emailSender = new InMemoryEmailSender();
        $this->spreadsheetSync = new InMemorySpreadsheetSync();

        $this->service = new GuestLifecycleFulfillmentService(
            pdo: $this->pdo,
            reservationRepository: $this->reservationRepository,
            hostRegistryRenderer: $this->hostRegistryRenderer,
            emailSender: $this->emailSender,
            spreadsheetSync: $this->spreadsheetSync,
            hostNotificationEmail: 'rentals@oceanviewflats.com',
            publicSiteUrl: 'https://oceanviewflats.com'
        );
    }

    private function createSampleReservation(
        string $uid = 'ovf_sample_100',
        ReservationStatus $status = ReservationStatus::CONFIRMED,
        ?string $doorCode = null,
        bool $registryCompleted = false,
        string $checkIn = '2026-11-15',
        string $checkOut = '2026-11-20'
    ): Reservation {
        $reservation = new Reservation(
            reservationUid: $uid,
            propertyId: '1606',
            guestName: 'Jane Smith',
            guestEmail: 'jane.smith@example.com',
            guestPhone: '+57 300 123 4567',
            checkIn: $checkIn,
            checkOut: $checkOut,
            totalPrice: 1800000.0,
            status: $status,
            paymentMethodId: 'card_visa',
            mercadopagoPaymentId: 'mp_pay_99999',
            paymentStatus: 'approved',
            lang: 'en',
            createdAt: new DateTimeImmutable('2026-10-01 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-10-01 10:00:00'),
            registryCompleted: $registryCompleted,
            registryCompletedAt: $registryCompleted ? new DateTimeImmutable('2026-10-01 11:00:00') : null,
            doorCode: $doorCode
        );

        return $this->reservationRepository->save($reservation);
    }

    private function createSubmission(
        string $code = 'ovf_sample_100',
        string $propertyId = '1606',
        string $checkIn = '2026-11-15',
        string $checkOut = '2026-11-20',
        ?string $carPlates = 'XYZ-123',
        ?string $carModel = 'Mazda CX-5'
    ): GuestRegistrySubmission {
        $occupants = [
            new OccupantDetails(
                index: 1,
                name: 'Jane Smith',
                age: 35,
                docType: 'Passport',
                docNum: 'US12345678'
            ),
            new OccupantDetails(
                index: 2,
                name: 'Alice Smith',
                age: 10,
                docType: 'Registro Civil',
                docNum: 'BC987654'
            ),
        ];

        return new GuestRegistrySubmission(
            reservationCode: $code,
            propertyId: $propertyId,
            checkIn: $checkIn,
            checkOut: $checkOut,
            occupants: $occupants,
            primaryGuestEmail: 'jane.smith@example.com',
            carPlates: $carPlates,
            carModel: $carModel,
            ipAddress: '190.24.15.2',
            lang: 'en'
        );
    }

    // ==========================================
    // 1. submitRegistry Tests
    // ==========================================

    public function testSubmitRegistrySuccessEndToEnd(): void
    {
        $this->createSampleReservation('ovf_sample_100');
        $submission = $this->createSubmission('ovf_sample_100');

        $result = $this->service->submitRegistry($submission);

        $this->assertTrue($result->success);
        $this->assertEmpty($result->errors);
        $this->assertNotNull($result->reservation);
        $this->assertTrue($result->reservation->registryCompleted);
        $this->assertTrue($result->hostReportDispatched);
        $this->assertTrue($result->spreadsheetSynced);

        // ADR 0001: Algorithmic PIN derived from primary doc "US12345678" -> last 6 digits "345678" -> "0345678#"
        $expectedDoorCode = '0345678#';
        $this->assertSame($expectedDoorCode, $result->doorCode);
        $this->assertSame($expectedDoorCode, $result->reservation->doorCode);
        $this->assertSame('https://oceanviewflats.com/guide/index.html?code=ovf_sample_100', $result->guideUrl);
        $this->assertTrue($result->hostReportDispatched);
        $this->assertTrue($result->spreadsheetSynced);
        $this->assertTrue($result->accessDispatchDispatched);

        // Verify DB update on reservations table
        $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE reservation_uid = :uid');
        $stmt->execute([':uid' => 'ovf_sample_100']);
        $row = $stmt->fetch();
        $this->assertNotEmpty($row);
        $this->assertSame(1, (int) $row['registry_completed']);
        $this->assertSame($expectedDoorCode, $row['door_code']);
        $this->assertNotNull($row['registry_completed_at']);

        // Verify guest_registries record
        $stmtReg = $this->pdo->prepare('SELECT * FROM guest_registries WHERE reservation_uid = :uid');
        $stmtReg->execute([':uid' => 'ovf_sample_100']);
        $regRow = $stmtReg->fetch();
        $this->assertNotEmpty($regRow);
        $this->assertSame('1606', $regRow['property_id']);
        $this->assertSame(2, (int) $regRow['guest_count']);
        $this->assertSame('XYZ-123', $regRow['car_plates']);
        $this->assertSame('Mazda CX-5', $regRow['car_model']);
        $this->assertSame('190.24.15.2', $regRow['ip_address']);

        $payload = json_decode((string) $regRow['guests_payload'], true);
        $this->assertIsArray($payload);
        $this->assertCount(2, $payload);
        $this->assertSame('Jane Smith', $payload[0]['name']);
        $this->assertSame('US12345678', $payload[0]['doc_num']);

        // Verify admin_audit_logs record
        $stmtAudit = $this->pdo->prepare("SELECT * FROM admin_audit_logs WHERE action = 'guest_registry_submitted'");
        $stmtAudit->execute();
        $auditRow = $stmtAudit->fetch();
        $this->assertNotEmpty($auditRow);
        $this->assertSame('reservation', $auditRow['entity_type']);
        $this->assertSame('ovf_sample_100', $auditRow['entity_id']);
        $this->assertSame('190.24.15.2', $auditRow['ip_address']);
        $this->assertStringContainsString('Jane Smith', (string) $auditRow['payload_after']);

        // Verify Dispatched Emails: Host Notification & Guest Access Dispatch
        $this->assertSame(2, $this->emailSender->count());
        $hostEmail = $this->emailSender->getSentMessages()[0];
        $this->assertSame('rentals@oceanviewflats.com', $hostEmail['to']);
        $this->assertStringContainsString('OceanViewFlats Guest Registry Report', $hostEmail['subject']);
        $this->assertStringContainsString($expectedDoorCode, $hostEmail['htmlBody']);
        $this->assertStringContainsString('Mazda CX-5', $hostEmail['htmlBody']);

        $guestEmail = $this->emailSender->getSentMessages()[1];
        $this->assertSame('jane.smith@example.com', $guestEmail['to']);
        $this->assertStringContainsString('Access Credentials & Arrival Guide', $guestEmail['subject']);
        $this->assertStringContainsString($expectedDoorCode, $guestEmail['htmlBody']);
        $this->assertStringContainsString('/guide/index.html?code=ovf_sample_100', $guestEmail['htmlBody']);
        $this->assertStringContainsString('Assigned Parking', $guestEmail['htmlBody']);
        $this->assertStringContainsString('#87', $guestEmail['htmlBody']);

        // Verify Spreadsheet Sync
        $this->assertSame(1, $this->spreadsheetSync->count());
        $sync = $this->spreadsheetSync->getSyncedRecords()[0];
        $this->assertSame('ovf_sample_100', $sync['reservation']->reservationUid);
        $this->assertSame('guest_registry', $sync['extra']['event']);
        $this->assertSame($expectedDoorCode, $sync['extra']['door_code']);
    }

    public function testSubmitRegistryMatchesByPropertyAndStayDatesWhenCodeIsEmpty(): void
    {
        $this->createSampleReservation('ovf_sample_dates');
        $submission = $this->createSubmission(
            code: '',
            propertyId: '1606',
            checkIn: '2026-11-15',
            checkOut: '2026-11-20'
        );

        $result = $this->service->submitRegistry($submission);

        $this->assertTrue($result->success);
        $this->assertSame('ovf_sample_dates', $result->reservation?->reservationUid);
        $this->assertSame('0345678#', $result->doorCode);
    }

    public function testSubmitRegistryRejectsInvalidOccupantCount(): void
    {
        $this->createSampleReservation('ovf_sample_count');

        // Zero occupants
        $submissionZero = new GuestRegistrySubmission(
            reservationCode: 'ovf_sample_count',
            propertyId: '1606',
            checkIn: '2026-11-15',
            checkOut: '2026-11-20',
            occupants: [],
            primaryGuestEmail: 'jane.smith@example.com'
        );
        $resultZero = $this->service->submitRegistry($submissionZero);
        $this->assertFalse($resultZero->success);
        $this->assertContains('A reservation must register between 1 and 6 guests.', $resultZero->errors);

        // 7 occupants (over the limit of 6)
        $sevenOccupants = [];
        for ($i = 1; $i <= 7; $i++) {
            $sevenOccupants[] = new OccupantDetails(
                index: $i,
                name: 'Guest ' . $i,
                age: 30,
                docType: 'Passport',
                docNum: 'DOC' . $i
            );
        }
        $submissionSeven = new GuestRegistrySubmission(
            reservationCode: 'ovf_sample_count',
            propertyId: '1606',
            checkIn: '2026-11-15',
            checkOut: '2026-11-20',
            occupants: $sevenOccupants,
            primaryGuestEmail: 'jane.smith@example.com'
        );
        $resultSeven = $this->service->submitRegistry($submissionSeven);
        $this->assertFalse($resultSeven->success);
        $this->assertContains('A reservation must register between 1 and 6 guests.', $resultSeven->errors);

        // Reservation with Occupancy Override max_guests = 8 accepts 8 guests and rejects 9
        $overrideRes = $this->createSampleReservation('ovf_sample_max8')->withMaxGuests(8);
        $this->reservationRepository->save($overrideRes);

        $eightOccupants = $sevenOccupants;
        $eightOccupants[] = new OccupantDetails(
            index: 8,
            name: 'Guest 8',
            age: 30,
            docType: 'Passport',
            docNum: 'DOC888888'
        );
        $nineOccupants = $eightOccupants;
        $nineOccupants[] = new OccupantDetails(
            index: 9,
            name: 'Guest 9',
            age: 30,
            docType: 'Passport',
            docNum: 'DOC999999'
        );

        $resultNine = $this->service->submitRegistry(new GuestRegistrySubmission(
            reservationCode: 'ovf_sample_max8',
            propertyId: '1606',
            checkIn: '2026-11-15',
            checkOut: '2026-11-20',
            occupants: $nineOccupants,
            primaryGuestEmail: 'jane.smith@example.com'
        ));
        $this->assertFalse($resultNine->success);
        $this->assertContains('A reservation must register between 1 and 8 guests.', $resultNine->errors);

        $resultEight = $this->service->submitRegistry(new GuestRegistrySubmission(
            reservationCode: 'ovf_sample_max8',
            propertyId: '1606',
            checkIn: '2026-11-15',
            checkOut: '2026-11-20',
            occupants: $eightOccupants,
            primaryGuestEmail: 'jane.smith@example.com'
        ));
        $this->assertTrue($resultEight->success);

        // Once registry_completed = 1, re-submission is blocked
        $resubmitResult = $this->service->submitRegistry(new GuestRegistrySubmission(
            reservationCode: 'ovf_sample_max8',
            propertyId: '1606',
            checkIn: '2026-11-15',
            checkOut: '2026-11-20',
            occupants: [$eightOccupants[0]],
            primaryGuestEmail: 'jane.smith@example.com'
        ));
        $this->assertFalse($resubmitResult->success);
        $this->assertContains('Guest registry has already been completed for this reservation.', $resubmitResult->errors);
    }

    public function testSubmitRegistryRejectsInvalidOccupantDetails(): void
    {
        $this->createSampleReservation('ovf_sample_invalid_occ');

        // Use reflection to bypass constructor validation and test service defense-in-depth
        $refClass = new \ReflectionClass(OccupantDetails::class);
        $occupant = $refClass->newInstanceWithoutConstructor();
        $refClass->getProperty('index')->setValue($occupant, 1);
        $refClass->getProperty('name')->setValue($occupant, 'A'); // invalid: < 2 chars
        $refClass->getProperty('age')->setValue($occupant, 130); // invalid: > 120
        $refClass->getProperty('docType')->setValue($occupant, 'Passport');
        $refClass->getProperty('docNum')->setValue($occupant, 'X'); // invalid: < 2 chars
        $refClass->getProperty('email')->setValue($occupant, 'jane.smith@example.com');

        $submission = new GuestRegistrySubmission(
            reservationCode: 'ovf_sample_invalid_occ',
            propertyId: '1606',
            checkIn: '2026-11-15',
            checkOut: '2026-11-20',
            occupants: [$occupant],
            primaryGuestEmail: 'jane.smith@example.com'
        );

        $result = $this->service->submitRegistry($submission);
        $this->assertFalse($result->success);
        $this->assertContains('Guest 1 name must be between 2 and 100 characters.', $result->errors);
        $this->assertContains('Guest 1 age must be between 0 and 120.', $result->errors);
        $this->assertContains('Guest 1 document number must be between 2 and 50 characters.', $result->errors);
    }

    public function testSubmitRegistryReturnsNotFoundWhenNoReservationMatches(): void
    {
        $submission = $this->createSubmission('ovf_unknown_uid');

        $result = $this->service->submitRegistry($submission);

        $this->assertFalse($result->success);
        $this->assertNull($result->reservation);
        $this->assertContains('No confirmed reservation matches the provided booking details.', $result->errors);
    }

    public function testSubmitRegistryRejectsCancelledReservation(): void
    {
        $this->createSampleReservation('ovf_cancelled', ReservationStatus::CANCELLED);
        $submission = $this->createSubmission('ovf_cancelled');

        $result = $this->service->submitRegistry($submission);

        $this->assertFalse($result->success);
        $this->assertContains('Cannot register guests for a cancelled reservation.', $result->errors);
    }

    public function testSubmitRegistryRejectsConcludedReservation(): void
    {
        $this->createSampleReservation(
            uid: 'ovf_concluded',
            status: ReservationStatus::CONFIRMED,
            checkIn: '2020-01-01',
            checkOut: '2020-01-05'
        );
        $submission = $this->createSubmission(
            code: 'ovf_concluded',
            checkIn: '2020-01-01',
            checkOut: '2020-01-05'
        );

        $result = $this->service->submitRegistry($submission);

        $this->assertFalse($result->success);
        $this->assertContains('Cannot submit guest registry for a concluded reservation.', $result->errors);
    }

    public function testSubmitRegistryRollsBackTransactionOnDbFailure(): void
    {
        $this->createSampleReservation('ovf_rollback');
        $submission = $this->createSubmission('ovf_rollback');

        // Drop guest_registries table to force an execution exception during transaction
        $this->pdo->exec('DROP TABLE guest_registries');

        $result = $this->service->submitRegistry($submission);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Database transaction failed', $result->errors[0]);

        // Verify reservation was NOT marked completed due to rollback
        $stmt = $this->pdo->prepare('SELECT registry_completed, door_code FROM reservations WHERE reservation_uid = :uid');
        $stmt->execute([':uid' => 'ovf_rollback']);
        $row = $stmt->fetch();
        $this->assertSame(0, (int) $row['registry_completed']);
        $this->assertNull($row['door_code']);

        // Verify side-effects were not invoked
        $this->assertSame(0, $this->emailSender->count());
        $this->assertSame(0, $this->spreadsheetSync->count());
    }

    public function testSubmitRegistrySucceedsEvenIfSideEffectsFail(): void
    {
        $this->createSampleReservation('ovf_side_effects_fail');
        $submission = $this->createSubmission('ovf_side_effects_fail');

        $this->spreadsheetSync->setShouldFail(true);
        $this->emailSender->setShouldFail(true);

        $result = $this->service->submitRegistry($submission);

        // Core business transaction succeeded
        $this->assertTrue($result->success);
        $this->assertSame('0345678#', $result->doorCode);
        $this->assertFalse($result->spreadsheetSynced);
        $this->assertFalse($result->hostReportDispatched);
        $this->assertFalse($result->accessDispatchDispatched);

        // DB reservation must still be completed
        $res = $this->reservationRepository->findByUid('ovf_side_effects_fail');
        $this->assertNotNull($res);
        $this->assertTrue($res->registryCompleted);
        $this->assertSame('0345678#', $res->doorCode);
    }

    public function testSubmitRegistryDispatchesAccessDispatchToPrimaryGuest(): void
    {
        $this->createSampleReservation('ovf_dispatch_test');
        $submission = $this->createSubmission('ovf_dispatch_test');

        $result = $this->service->submitRegistry($submission);

        $this->assertTrue($result->success);
        $this->assertTrue($result->accessDispatchDispatched);
        $this->assertSame(2, $this->emailSender->count());

        $dispatchEmail = $this->emailSender->getSentMessages()[1];
        $this->assertSame('jane.smith@example.com', $dispatchEmail['to']);
        $this->assertStringContainsString('Access Credentials & Arrival Guide', $dispatchEmail['subject']);
        $this->assertStringContainsString('0345678#', $dispatchEmail['htmlBody']);
        $this->assertStringContainsString('https://oceanviewflats.com/guide/index.html?code=ovf_dispatch_test', $dispatchEmail['htmlBody']);
        $this->assertStringContainsString('Jane Smith', $dispatchEmail['htmlBody']);
    }

    public function testSubmitRegistrySoftFailsWhenAccessDispatchEmailThrowsException(): void
    {
        $this->createSampleReservation('ovf_dispatch_throw');
        $submission = $this->createSubmission('ovf_dispatch_throw');

        $failingSender = new class implements \OceanViewFlats\Domain\Fulfillment\EmailSenderInterface {
            private int $callCount = 0;
            public function send(string $to, string $subject, string $htmlBody, array $headers = []): bool
            {
                $this->callCount++;
                // Allow host email (call 1) to succeed, but throw on guest dispatch (call 2)
                if ($this->callCount === 2) {
                    throw new \RuntimeException('SMTP Connection timed out');
                }
                return true;
            }
        };

        $service = GuestLifecycleFulfillmentService::createDefault($this->pdo, [
            'email_sender' => $failingSender,
            'spreadsheet_sync' => $this->spreadsheetSync,
        ]);

        $result = $service->submitRegistry($submission);

        $this->assertTrue($result->success);
        $this->assertSame('0345678#', $result->doorCode);
        $this->assertTrue($result->hostReportDispatched);
        $this->assertFalse($result->accessDispatchDispatched);

        // Core business transaction still completes in DB
        $res = $this->reservationRepository->findByUid('ovf_dispatch_throw');
        $this->assertNotNull($res);
        $this->assertTrue($res->registryCompleted);
        $this->assertSame('0345678#', $res->doorCode);
    }

    // ==========================================
    // 2. completeRegistryManually Tests
    // ==========================================

    public function testCompleteRegistryManuallyGeneratesRandomPinWhenNoneExists(): void
    {
        $this->createSampleReservation('ovf_manual_new');
        $admin = new AdminContext(adminUserId: 15, ipAddress: '10.0.0.1', userAgent: 'Chrome/Admin');

        $result = $this->service->completeRegistryManually('ovf_manual_new', $admin);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->reservation);
        $this->assertTrue($result->reservation->registryCompleted);
        $this->assertMatchesRegularExpression('/^0[0-9]{6}#$/', $result->doorCode);
        $this->assertSame($result->doorCode, $result->reservation->doorCode);

        // Verify DB update
        $res = $this->reservationRepository->findByUid('ovf_manual_new');
        $this->assertNotNull($res);
        $this->assertTrue($res->registryCompleted);
        $this->assertSame($result->doorCode, $res->doorCode);

        // Verify audit log
        $stmtAudit = $this->pdo->prepare("SELECT * FROM admin_audit_logs WHERE action = 'registry_manual_complete'");
        $stmtAudit->execute();
        $auditRow = $stmtAudit->fetch();
        $this->assertNotEmpty($auditRow);
        $this->assertSame(15, (int) $auditRow['admin_user_id']);
        $this->assertSame('10.0.0.1', $auditRow['ip_address']);
        $this->assertSame('Chrome/Admin', $auditRow['user_agent']);
    }

    public function testCompleteRegistryManuallyPreservesExistingDoorCode(): void
    {
        $this->createSampleReservation('ovf_manual_preserve', doorCode: '0887766#');
        $admin = new AdminContext(adminUserId: 7, ipAddress: '127.0.0.1');

        $result = $this->service->completeRegistryManually('ovf_manual_preserve', $admin);

        $this->assertTrue($result->success);
        $this->assertSame('0887766#', $result->doorCode);
        $this->assertSame('0887766#', $result->reservation?->doorCode);
    }

    public function testCompleteRegistryManuallyWithSubmissionInsertsGuestRegistries(): void
    {
        $this->createSampleReservation('ovf_manual_sub');
        $submission = $this->createSubmission('ovf_manual_sub');
        $admin = new AdminContext(adminUserId: 9);

        $result = $this->service->completeRegistryManually('ovf_manual_sub', $admin, $submission);

        $this->assertTrue($result->success);

        $stmtReg = $this->pdo->prepare('SELECT * FROM guest_registries WHERE reservation_uid = :uid');
        $stmtReg->execute([':uid' => 'ovf_manual_sub']);
        $regRow = $stmtReg->fetch();
        $this->assertNotEmpty($regRow);
        $this->assertSame(2, (int) $regRow['guest_count']);
    }

    public function testCompleteRegistryManuallyReturnsNotFoundForUnknownUid(): void
    {
        $result = $this->service->completeRegistryManually('ovf_nonexistent');

        $this->assertFalse($result->success);
        $this->assertContains('Reservation not found', $result->errors);
    }

    public function testCompleteRegistryManuallyRollsBackOnDbFailure(): void
    {
        $this->createSampleReservation('ovf_manual_fail');

        // Break reservations table by dropping it
        $this->pdo->exec('DROP TABLE admin_audit_logs');

        $result = $this->service->completeRegistryManually('ovf_manual_fail');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Database transaction failed', $result->errors[0]);
    }

    public function testCompleteRegistryManuallyTriggersCondominiumClearanceSync(): void
    {
        $this->createSampleReservation('ovf_manual_clearance');
        $admin = new AdminContext(adminUserId: 12, ipAddress: '192.168.1.10', userAgent: 'Chrome/Admin');

        $mockSync = $this->createMock(CondominiumClearanceSyncInterface::class);
        $mockSync->expects($this->once())
            ->method('syncForReservation')
            ->with('ovf_manual_clearance', $admin)
            ->willReturn(
                new CondominiumClearance(
                    reservationUid: 'ovf_manual_clearance',
                    propertyId: '1606',
                    status: CondominiumClearance::STATUS_SYNCED,
                    clearanceNumber: 'CLEAR-777',
                    syncedAt: '2026-10-09 12:00:00'
                )
            );

        $service = new GuestLifecycleFulfillmentService(
            pdo: $this->pdo,
            reservationRepository: $this->reservationRepository,
            hostRegistryRenderer: $this->hostRegistryRenderer,
            emailSender: $this->emailSender,
            spreadsheetSync: $this->spreadsheetSync,
            clearanceSync: $mockSync
        );

        $result = $service->completeRegistryManually('ovf_manual_clearance', $admin);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->doorCode);
        $this->assertNotNull($result->reservation);
        $this->assertTrue($result->reservation->registryCompleted);
    }

    public function testCompleteRegistryManuallySucceedsWhenCondominiumClearanceSyncThrowsException(): void
    {
        $this->createSampleReservation('ovf_manual_clearance_fail');
        $admin = new AdminContext(adminUserId: 12, ipAddress: '192.168.1.10', userAgent: 'Chrome/Admin');

        $mockSync = $this->createMock(CondominiumClearanceSyncInterface::class);
        $mockSync->expects($this->once())
            ->method('syncForReservation')
            ->with('ovf_manual_clearance_fail', $admin)
            ->willThrowException(new \RuntimeException('Connection timed out to Huésped Manager'));

        $service = new GuestLifecycleFulfillmentService(
            pdo: $this->pdo,
            reservationRepository: $this->reservationRepository,
            hostRegistryRenderer: $this->hostRegistryRenderer,
            emailSender: $this->emailSender,
            spreadsheetSync: $this->spreadsheetSync,
            clearanceSync: $mockSync
        );

        // ADR 0001 & ADR 0008: External clearance synchronization failure must not block manual registry completion
        $result = $service->completeRegistryManually('ovf_manual_clearance_fail', $admin);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->doorCode);
        $this->assertNotNull($result->reservation);
        $this->assertTrue($result->reservation->registryCompleted);

        // Verify DB update succeeded despite clearance exception
        $res = $this->reservationRepository->findByUid('ovf_manual_clearance_fail');
        $this->assertNotNull($res);
        $this->assertTrue($res->registryCompleted);
    }

    public function testCreateDefaultAcceptsClearanceSyncOption(): void
    {
        $mockSync = $this->createMock(CondominiumClearanceSyncInterface::class);

        $service = GuestLifecycleFulfillmentService::createDefault($this->pdo, [
            'clearance_sync' => $mockSync,
        ]);

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('clearanceSync');
        $property->setAccessible(true);

        $this->assertSame($mockSync, $property->getValue($service));
    }

    // ==========================================
    // 3. overrideDoorCode Tests
    // ==========================================

    public function testOverrideDoorCodeSuccessWithTrailingHash(): void
    {
        $this->createSampleReservation('ovf_override_1', doorCode: '0111222#');
        $admin = new AdminContext(adminUserId: 3, ipAddress: '192.168.1.5', userAgent: 'Safari/Admin');

        $result = $this->service->overrideDoorCode('ovf_override_1', '749201#', $admin);

        $this->assertTrue($result->success);
        $this->assertSame('749201#', $result->doorCode);
        $this->assertNull($result->error);

        // Verify DB update
        $res = $this->reservationRepository->findByUid('ovf_override_1');
        $this->assertNotNull($res);
        $this->assertSame('749201#', $res->doorCode);

        // Verify audit log
        $stmtAudit = $this->pdo->prepare("SELECT * FROM admin_audit_logs WHERE action = 'pin_override'");
        $stmtAudit->execute();
        $auditRow = $stmtAudit->fetch();
        $this->assertNotEmpty($auditRow);
        $this->assertSame(3, (int) $auditRow['admin_user_id']);
        $this->assertStringContainsString('0111222#', (string) $auditRow['payload_before']);
        $this->assertStringContainsString('749201#', (string) $auditRow['payload_after']);
    }

    public function testOverrideDoorCodeAppendsTrailingHashWhenOmitted(): void
    {
        $this->createSampleReservation('ovf_override_2');
        $result = $this->service->overrideDoorCode('ovf_override_2', '654321');

        $this->assertTrue($result->success);
        $this->assertSame('654321#', $result->doorCode);

        $res = $this->reservationRepository->findByUid('ovf_override_2');
        $this->assertSame('654321#', $res?->doorCode);
    }

    public function testOverrideDoorCodeRejectsNonConfirmedReservation(): void
    {
        $this->createSampleReservation('ovf_override_pending', ReservationStatus::PENDING_PAYMENT);

        $result = $this->service->overrideDoorCode('ovf_override_pending', '123456#');

        $this->assertFalse($result->success);
        $this->assertSame('PIN modification is strictly restricted to confirmed reservations.', $result->error);
    }

    public function testOverrideDoorCodeRejectsInvalidPinFormat(): void
    {
        $this->createSampleReservation('ovf_override_fmt');

        // Short PIN (< 4 digits)
        $resShort = $this->service->overrideDoorCode('ovf_override_fmt', '12#');
        $this->assertFalse($resShort->success);
        $this->assertStringContainsString('Invalid PIN format', (string) $resShort->error);

        // Non-digit characters
        $resChars = $this->service->overrideDoorCode('ovf_override_fmt', '12AB56#');
        $this->assertFalse($resChars->success);
        $this->assertStringContainsString('Invalid PIN format', (string) $resChars->error);

        // Too long (> 10 digits)
        $resLong = $this->service->overrideDoorCode('ovf_override_fmt', '1234567890123#');
        $this->assertFalse($resLong->success);
        $this->assertStringContainsString('Invalid PIN format', (string) $resLong->error);
    }

    public function testOverrideDoorCodeReturnsFailureForNonExistentReservation(): void
    {
        $result = $this->service->overrideDoorCode('ovf_missing', '123456#');

        $this->assertFalse($result->success);
        $this->assertSame('Reservation not found', $result->error);
    }

    // ==========================================
    // 4. regenerateDoorCode Tests
    // ==========================================

    public function testRegenerateDoorCodeSuccess(): void
    {
        $this->createSampleReservation('ovf_regen_1', doorCode: '0111111#');
        $admin = new AdminContext(adminUserId: 11, ipAddress: '192.168.1.100');

        $result = $this->service->regenerateDoorCode('ovf_regen_1', $admin);

        $this->assertTrue($result->success);
        $this->assertMatchesRegularExpression('/^0[0-9]{6}#$/', (string) $result->doorCode);
        $this->assertNotSame('0111111#', $result->doorCode);

        // Verify DB update
        $res = $this->reservationRepository->findByUid('ovf_regen_1');
        $this->assertSame($result->doorCode, $res?->doorCode);

        // Verify audit log
        $stmtAudit = $this->pdo->prepare("SELECT * FROM admin_audit_logs WHERE action = 'pin_regenerate'");
        $stmtAudit->execute();
        $auditRow = $stmtAudit->fetch();
        $this->assertNotEmpty($auditRow);
        $this->assertSame(11, (int) $auditRow['admin_user_id']);
        $this->assertStringContainsString('0111111#', (string) $auditRow['payload_before']);
        $this->assertStringContainsString((string) $result->doorCode, (string) $auditRow['payload_after']);
    }

    public function testRegenerateDoorCodeRejectsNonConfirmedReservation(): void
    {
        $this->createSampleReservation('ovf_regen_cancelled', ReservationStatus::CANCELLED);

        $result = $this->service->regenerateDoorCode('ovf_regen_cancelled');

        $this->assertFalse($result->success);
        $this->assertSame('PIN modification is strictly restricted to confirmed reservations.', $result->error);
    }

    public function testRegenerateDoorCodeReturnsFailureForNonExistentReservation(): void
    {
        $result = $this->service->regenerateDoorCode('ovf_missing');

        $this->assertFalse($result->success);
        $this->assertSame('Reservation not found', $result->error);
    }

    public function testSubmitRegistryRejectsInvalidDates(): void
    {
        $occupant = new OccupantDetails(
            index: 1,
            name: 'Jane Doe',
            age: 30,
            docType: 'Passport',
            docNum: 'US123456'
        );

        $submission = new GuestRegistrySubmission(
            reservationCode: 'ovf_sample_dates',
            propertyId: '1606',
            checkIn: '2026-11-20',
            checkOut: '2026-11-10', // checkOut before checkIn
            occupants: [$occupant],
            primaryGuestEmail: 'jane.smith@example.com'
        );

        $result = $this->service->submitRegistry($submission);
        $this->assertFalse($result->success);
        $this->assertContains('Check-in and check-out dates are invalid or improperly ordered.', $result->errors);
    }

    public function testSubmitRegistryEnrichesAirbnbReservationEmail(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, external_confirmation_code,
                registry_completed, created_at
            ) VALUES (
                'res-abnb-testemail1', '1606', 'Airbnb Traveler', 'automated-relay@guest.airbnb.com', '+12025550199',
                '2026-11-15', '2026-11-20', 0.0, 'confirmed', 'airbnb', 'HMTEST123',
                0, datetime('now')
            )
        ");
        $stmt->execute();

        $submission = $this->createSubmission('res-abnb-testemail1');
        $result = $this->service->submitRegistry($submission);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->reservation);
        $this->assertSame('jane.smith@example.com', $result->reservation->guestEmail);

        $stmtCheck = $this->pdo->prepare("SELECT guest_email FROM reservations WHERE reservation_uid = 'res-abnb-testemail1'");
        $stmtCheck->execute();
        $this->assertSame('jane.smith@example.com', $stmtCheck->fetchColumn());
    }

    public function testSubmitRegistryEnrichesPlaceholderEmailOnWebReservation(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source,
                registry_completed, created_at
            ) VALUES (
                'ovf_placeholder_test', '1606', 'Placeholder Guest', 'guest@oceanviewflats.com', '+12025550199',
                '2026-11-15', '2026-11-20', 1000000.0, 'confirmed', 'web',
                0, datetime('now')
            )
        ");
        $stmt->execute();

        $submission = $this->createSubmission('ovf_placeholder_test');
        $result = $this->service->submitRegistry($submission);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->reservation);
        $this->assertSame('jane.smith@example.com', $result->reservation->guestEmail);

        $stmtCheck = $this->pdo->prepare("SELECT guest_email FROM reservations WHERE reservation_uid = 'ovf_placeholder_test'");
        $stmtCheck->execute();
        $this->assertSame('jane.smith@example.com', $stmtCheck->fetchColumn());
    }

    public function testSubmitRegistryPreservesDirectReservationEmail(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source,
                registry_completed, created_at
            ) VALUES (
                'ovf_direct_billing_test', '1606', 'Direct Buyer', 'buyer.billing@personal.com', '+12025550199',
                '2026-11-15', '2026-11-20', 1000000.0, 'confirmed', 'web',
                0, datetime('now')
            )
        ");
        $stmt->execute();

        $submission = $this->createSubmission('ovf_direct_billing_test');
        $result = $this->service->submitRegistry($submission);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->reservation);
        // Billing email must NOT be overwritten
        $this->assertSame('buyer.billing@personal.com', $result->reservation->guestEmail);

        $stmtCheck = $this->pdo->prepare("SELECT guest_email FROM reservations WHERE reservation_uid = 'ovf_direct_billing_test'");
        $stmtCheck->execute();
        $this->assertSame('buyer.billing@personal.com', $stmtCheck->fetchColumn());
    }

    public function testSubmitRegistryEnrichesResAbnbPrefixReservation(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source,
                registry_completed, created_at
            ) VALUES (
                'res-abnb-external99', '1606', 'OTA Guest', 'relay123@external.com', '+12025550199',
                '2026-11-15', '2026-11-20', 0.0, 'confirmed', 'channel_sync',
                0, datetime('now')
            )
        ");
        $stmt->execute();

        $submission = $this->createSubmission('res-abnb-external99');
        $result = $this->service->submitRegistry($submission);

        $this->assertTrue($result->success);
        $this->assertSame('jane.smith@example.com', $result->reservation->guestEmail);

        $stmtCheck = $this->pdo->prepare("SELECT guest_email FROM reservations WHERE reservation_uid = 'res-abnb-external99'");
        $stmtCheck->execute();
        $this->assertSame('jane.smith@example.com', $stmtCheck->fetchColumn());
    }

    public function testSubmitRegistryEnrichesEmptyOrNonePlaceholderEmail(): void
    {
        // 1. None@ placeholder
        $stmt1 = $this->pdo->prepare("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source,
                registry_completed, created_at
            ) VALUES (
                'ovf_none_placeholder', '1606', 'OTA Guest', 'none@domain.com', '+12025550199',
                '2026-11-15', '2026-11-20', 0.0, 'confirmed', 'channel_sync',
                0, datetime('now')
            )
        ");
        $stmt1->execute();

        $sub1 = $this->createSubmission('ovf_none_placeholder');
        $res1 = $this->service->submitRegistry($sub1);
        $this->assertTrue($res1->success);
        $this->assertSame('jane.smith@example.com', $res1->reservation->guestEmail);

        // 2. Empty email
        $stmt2 = $this->pdo->prepare("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source,
                registry_completed, created_at
            ) VALUES (
                'ovf_empty_email', '1606', 'No Email Guest', '', '+12025550199',
                '2026-11-15', '2026-11-20', 0.0, 'confirmed', 'direct_phone',
                0, datetime('now')
            )
        ");
        $stmt2->execute();

        $sub2 = $this->createSubmission('ovf_empty_email');
        $res2 = $this->service->submitRegistry($sub2);
        $this->assertTrue($res2->success);
        $this->assertSame('jane.smith@example.com', $res2->reservation->guestEmail);
    }

    // ==========================================
    // 6. createDefault Factory Tests
    // ==========================================

    public function testCreateDefaultFactoryCreatesValidService(): void
    {
        $service = GuestLifecycleFulfillmentService::createDefault($this->pdo, [
            'host_notification_email' => 'custom_host@example.com',
            'public_site_url' => 'https://custom.oceanviewflats.com',
            'email_sender' => $this->emailSender,
            'spreadsheet_sync' => $this->spreadsheetSync,
        ]);

        $this->assertInstanceOf(GuestLifecycleFulfillmentService::class, $service);
    }

    public function testSubmitRegistryInvokesCondominiumClearanceSyncWhenConfigured(): void
    {
        $mockSync = $this->createMock(CondominiumClearanceSyncInterface::class);
        $mockSync->expects($this->once())
            ->method('syncSubmission')
            ->with(
                $this->callback(fn (Reservation $r) => $r->reservationUid === 'ovf_clearance_success' && $r->propertyId === '1606'),
                $this->callback(fn (GuestRegistrySubmission $s) => $s->reservationCode === 'ovf_clearance_success' && $s->carPlates === 'XYZ-123')
            )
            ->willReturn(
                CondominiumClearance::createPending('ovf_clearance_success', '1606')
                    ->markSynced('CLEAR-999')
            );

        $service = new GuestLifecycleFulfillmentService(
            pdo: $this->pdo,
            reservationRepository: $this->reservationRepository,
            hostRegistryRenderer: $this->hostRegistryRenderer,
            emailSender: $this->emailSender,
            spreadsheetSync: $this->spreadsheetSync,
            clearanceSync: $mockSync
        );

        $stmt = $this->pdo->prepare("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source,
                registry_completed, created_at
            ) VALUES (
                'ovf_clearance_success', '1606', 'Jane Smith', 'jane.smith@example.com', '+12025550199',
                '2026-11-15', '2026-11-20', 450.0, 'confirmed', 'airbnb',
                0, datetime('now')
            )
        ");
        $stmt->execute();

        $submission = $this->createSubmission('ovf_clearance_success');
        $result = $service->submitRegistry($submission);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->doorCode);
        $this->assertNotNull($result->guideUrl);
    }

    public function testSubmitRegistrySucceedsWhenCondominiumClearanceSyncThrowsException(): void
    {
        $mockSync = $this->createMock(CondominiumClearanceSyncInterface::class);
        $mockSync->expects($this->once())
            ->method('syncSubmission')
            ->willThrowException(new \RuntimeException('Connection timed out to Huésped Manager'));

        $service = new GuestLifecycleFulfillmentService(
            pdo: $this->pdo,
            reservationRepository: $this->reservationRepository,
            hostRegistryRenderer: $this->hostRegistryRenderer,
            emailSender: $this->emailSender,
            spreadsheetSync: $this->spreadsheetSync,
            clearanceSync: $mockSync
        );

        $stmt = $this->pdo->prepare("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source,
                registry_completed, created_at
            ) VALUES (
                'ovf_clearance_throws', '1606', 'Jane Smith', 'jane.smith@example.com', '+12025550199',
                '2026-11-15', '2026-11-20', 450.0, 'confirmed', 'airbnb',
                0, datetime('now')
            )
        ");
        $stmt->execute();

        $submission = $this->createSubmission('ovf_clearance_throws');
        $result = $service->submitRegistry($submission);

        // ADR 0001: Access credentials must not be blocked by downstream external failure
        $this->assertTrue($result->success);
        $this->assertNotNull($result->doorCode);
        $this->assertNotNull($result->guideUrl);
    }

    public function testSubmitRegistrySucceedsWhenCondominiumClearanceReturnsFailedStatus(): void
    {
        $mockSync = $this->createMock(CondominiumClearanceSyncInterface::class);
        $mockSync->expects($this->once())
            ->method('syncSubmission')
            ->willReturn(
                CondominiumClearance::createPending('ovf_clearance_fails', '1606')
                    ->markFailed('Portal authentication error')
            );

        $service = new GuestLifecycleFulfillmentService(
            pdo: $this->pdo,
            reservationRepository: $this->reservationRepository,
            hostRegistryRenderer: $this->hostRegistryRenderer,
            emailSender: $this->emailSender,
            spreadsheetSync: $this->spreadsheetSync,
            clearanceSync: $mockSync
        );

        $stmt = $this->pdo->prepare("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source,
                registry_completed, created_at
            ) VALUES (
                'ovf_clearance_fails', '1606', 'Jane Smith', 'jane.smith@example.com', '+12025550199',
                '2026-11-15', '2026-11-20', 450.0, 'confirmed', 'airbnb',
                0, datetime('now')
            )
        ");
        $stmt->execute();

        $submission = $this->createSubmission('ovf_clearance_fails');
        $result = $service->submitRegistry($submission);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->doorCode);
        $this->assertNotNull($result->guideUrl);
    }

    public function testSubmitRegistryOmitsAssignedParkingWhenNoVehicleProvided(): void
    {
        $this->createSampleReservation('ovf_no_car_test');
        $submission = $this->createSubmission(
            code: 'ovf_no_car_test',
            carPlates: null,
            carModel: null
        );

        $result = $this->service->submitRegistry($submission);

        $this->assertTrue($result->success);
        $this->assertTrue($result->accessDispatchDispatched);
        $this->assertSame(2, $this->emailSender->count());

        $dispatchEmail = $this->emailSender->getSentMessages()[1];
        $this->assertStringContainsString('Access Credentials & Arrival Guide', $dispatchEmail['subject']);
        $this->assertStringNotContainsString('Assigned Parking', $dispatchEmail['htmlBody']);
        $this->assertStringNotContainsString('#87', $dispatchEmail['htmlBody']);
    }

    public function testSubmitRegistryIncludesAssignedParkingForProperty1707(): void
    {
        // Setup 1707 reservation
        $stmt = $this->pdo->prepare("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source,
                registry_completed, created_at
            ) VALUES (
                'ovf_1707_car_test', '1707', 'Maria Rodriguez', 'maria@example.com', '+573009876543',
                '2026-11-15', '2026-11-20', 500.0, 'confirmed', 'direct',
                0, datetime('now')
            )
        ");
        $stmt->execute();

        $submission = $this->createSubmission(
            code: 'ovf_1707_car_test',
            propertyId: '1707',
            carPlates: 'ABC-789',
            carModel: 'Toyota RAV4'
        );

        $result = $this->service->submitRegistry($submission);

        $this->assertTrue($result->success);
        $this->assertTrue($result->accessDispatchDispatched);
        $this->assertSame(2, $this->emailSender->count());

        $dispatchEmail = $this->emailSender->getSentMessages()[1];
        $this->assertStringContainsString('Assigned Parking', $dispatchEmail['htmlBody']);
        $this->assertStringContainsString('#95', $dispatchEmail['htmlBody']);
    }
}
