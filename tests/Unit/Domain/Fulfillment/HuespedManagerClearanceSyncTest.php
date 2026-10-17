<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Fulfillment;

use OceanViewFlats\Admin\Audit\AuditLogger;
use OceanViewFlats\Domain\Database\MigrationRunner;
use OceanViewFlats\Domain\Fulfillment\AdminContext;
use OceanViewFlats\Domain\Fulfillment\CondominiumClearance;
use OceanViewFlats\Domain\Fulfillment\GuestRegistryRequiredException;
use OceanViewFlats\Domain\Fulfillment\GuestRegistrySubmission;
use OceanViewFlats\Domain\Fulfillment\HuespedManagerClearanceSync;
use OceanViewFlats\Domain\Fulfillment\HttpTransportInterface;
use OceanViewFlats\Domain\Fulfillment\OccupantDetails;
use OceanViewFlats\Domain\Fulfillment\PdoCondominiumClearanceRepository;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationNotFoundException;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use OceanViewFlats\Domain\Reservation\Search\PdoReservationSearchAdapter;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;


final class HuespedManagerClearanceSyncTest extends TestCase
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

    private function createSampleReservation(
        string $uid = 'ovf_res_sync_test',
        string $propertyId = '1707',
        string $checkIn = '2026-11-01',
        string $checkOut = '2026-11-05',
        ?string $notes = null
    ): Reservation {
        return Reservation::create(
            reservationUid: $uid,
            propertyId: $propertyId,
            guestName: 'Carlos Andres Mendoza Perez',
            guestEmail: 'carlos@example.com',
            guestPhone: '+34 600 123 456',
            checkIn: $checkIn,
            checkOut: $checkOut,
            totalPrice: 1000.0,
            status: ReservationStatus::CONFIRMED,
            notes: $notes
        );
    }

    /**
     * @param array<int, OccupantDetails> $occupants
     */
    private function createSampleSubmission(
        string $uid = 'ovf_res_sync_test',
        string $propertyId = '1707',
        string $checkIn = '2026-11-01',
        string $checkOut = '2026-11-05',
        array $occupants = [],
        ?string $carPlates = null,
        ?string $carModel = null,
        string $primaryGuestEmail = 'carlos@example.com'
    ): GuestRegistrySubmission {
        return new GuestRegistrySubmission(
            reservationCode: $uid,
            propertyId: $propertyId,
            checkIn: $checkIn,
            checkOut: $checkOut,
            occupants: $occupants,
            primaryGuestEmail: $primaryGuestEmail,
            carPlates: $carPlates,
            carModel: $carModel
        );
    }

    private function createSampleOccupant(
        int $index = 1,
        string $name = 'Carlos Perez',
        string $docType = 'Cédula de Ciudadanía',
        string $docNum = '12345',
        string $country = 'Colombia',
        string $phone = '+57 300 1234567',
        ?string $firstName = 'Carlos',
        ?string $lastName = 'Perez'
    ): OccupantDetails {
        return new OccupantDetails(
            index: $index,
            name: $name,
            age: 30,
            docType: $docType,
            docNum: $docNum,
            email: $index === 1 ? 'carlos@example.com' : null,
            firstName: $firstName,
            lastName: $lastName,
            phone: $phone,
            country: $country
        );
    }

    public function testSuccessfulTwoStepSyncWithCookiePreservation(): void
    {
        $requests = [];
        $mockTransport = new class($requests) implements HttpTransportInterface {
            /** @param array<int, array<string, mixed>> $requests */
            public function __construct(public array &$requests) {}

            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                $this->requests[] = [
                    'url' => $url,
                    'data' => $data,
                    'headers' => $headers,
                    'options' => $options,
                ];

                if (str_contains($url, 'reg_guest_owner_pre.php')) {
                    return [
                        'statusCode' => 200,
                        'body' => json_encode(['last_id' => 495, 'unit_apt' => '1707']),
                        'headers' => ['content-type' => 'application/json'],
                        'cookies' => ['PHPSESSID' => 'test-session-token-xyz'],
                        'error' => null,
                    ];
                }

                if (str_contains($url, 'reg_hpds_pre.php')) {
                    return [
                        'statusCode' => 302,
                        'body' => '',
                        'headers' => ['location' => 'success.php?hpsucc=succ&book=495'],
                        'cookies' => [],
                        'error' => null,
                    ];
                }

                return ['statusCode' => 404, 'body' => '', 'headers' => [], 'cookies' => [], 'error' => 'Not found'];
            }
        };

        $adapter = new HuespedManagerClearanceSync(
            repository: $this->repository,
            transport: $mockTransport
        );

        $guests = [
            new OccupantDetails(
                index: 1,
                name: 'Carlos Andres Mendoza Perez',
                age: 35,
                docType: 'Passport',
                docNum: 'PA987654',
                email: 'carlos@example.com',
                firstName: 'Carlos',
                lastName: 'Mendoza',
                phone: '+34 600 123 456',
                country: 'España',
                middleName: 'Andres',
                secondLastName: 'Perez'
            ),
            new OccupantDetails(
                index: 2,
                name: 'Maria Gomez',
                age: 30,
                docType: 'Cédula de Ciudadanía',
                docNum: '1098765432',
                email: null,
                firstName: 'Maria',
                lastName: 'Gomez',
                phone: '', // Companion without phone
                country: 'Colombia',
                middleName: null,
                secondLastName: null
            ),
        ];

        $res = $this->createSampleReservation(
            uid: 'ovf_res_sync_test',
            propertyId: '1707',
            checkIn: '2026-11-01',
            checkOut: '2026-11-05',
            notes: 'Late arrival at 6 PM'
        );
        $sub = $this->createSampleSubmission(
            uid: 'ovf_res_sync_test',
            propertyId: '1707',
            checkIn: '2026-11-01',
            checkOut: '2026-11-05',
            occupants: $guests,
            carPlates: 'XYZ-123'
        );

        $clearance = $adapter->syncSubmission($res, $sub);

        $this->assertTrue($clearance->isSynced());
        $this->assertSame('495', $clearance->clearanceNumber);
        $this->assertNull($clearance->errorMessage);
        $this->assertNotNull($clearance->syncedAt);
        $this->assertSame(1, $clearance->attempts);

        // Verify repository persistence
        $persisted = $this->repository->findByReservationUid('ovf_res_sync_test');
        $this->assertNotNull($persisted);
        $this->assertTrue($persisted->isSynced());
        $this->assertSame('495', $persisted->clearanceNumber);

        // Verify Step 1 call
        $this->assertCount(2, $requests);
        $step1 = $requests[0];
        $this->assertStringContainsString('reg_guest_owner_pre.php?unit=1707&check=ep92449222', $step1['url']);
        $this->assertSame('1707', $step1['data']['send_apt']);
        $this->assertSame('2026-11-01', $step1['data']['send_indate']);
        $this->assertSame('2026-11-05', $step1['data']['send_outdate']);
        $this->assertSame('15:00', $step1['data']['send_horain']);
        $this->assertSame('11:00', $step1['data']['send_horaout']);
        $this->assertSame('TRANSFERENCIA', $step1['data']['send_fpay']);
        $this->assertSame('2', $step1['data']['send_cant_hp']);
        $this->assertSame('XYZ-123', $step1['data']['send_car']);
        $this->assertSame('Late arrival at 6 PM', $step1['data']['send_observacion']);

        // Verify Step 2 call
        $step2 = $requests[1];
        $this->assertStringContainsString('reg_hpds_pre.php', $step2['url']);
        $this->assertSame(['PHPSESSID' => 'test-session-token-xyz'], $step2['options']['cookies']);
        $this->assertSame('495', $step2['data']['consecutivo']);

        // Verify form arrays
        $this->assertSame(['PA', 'CC'], $step2['data']['typeid']);
        $this->assertSame(['PA987654', '1098765432'], $step2['data']['ide']);
        $this->assertSame(['Carlos', 'Maria'], $step2['data']['name']);
        $this->assertSame(['Andres', ''], $step2['data']['sname']);
        $this->assertSame(['Mendoza', 'Gomez'], $step2['data']['lname']);
        $this->assertSame(['Perez', ''], $step2['data']['mname']);
        $this->assertSame(['ESPANA', 'COLOMBIA'], $step2['data']['country']);
        // Crucial: companion inherited primary guest phone!
        $this->assertSame(['+34 600 123 456', '+34 600 123 456'], $step2['data']['phone']);
        $this->assertSame(['TITULAR', 'ACOMPAÑANTE'], $step2['data']['nexo']);
        $this->assertSame(['0', '0'], $step2['data']['covid3']);
        $this->assertSame(['0', '0'], $step2['data']['covid10']);
    }

    public function testCompanionWithoutPhoneInheritsPrimaryGuestPhoneWhileIndependentRetainsOwn(): void
    {
        $requests = [];
        $mockTransport = new class($requests) implements HttpTransportInterface {
            public function __construct(public array &$requests) {}

            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                $this->requests[] = ['url' => $url, 'data' => $data];
                if (str_contains($url, 'reg_guest_owner_pre.php')) {
                    return [
                        'statusCode' => 200,
                        'body' => json_encode(['last_id' => 777]),
                        'headers' => [],
                        'cookies' => [],
                        'error' => null,
                    ];
                }
                return [
                    'statusCode' => 200,
                    'body' => '<html>Registro de huéspedes exitoso. Consecutivo 777</html>',
                    'headers' => [],
                    'cookies' => [],
                    'error' => null,
                ];
            }
        };

        $adapter = new HuespedManagerClearanceSync(
            repository: $this->repository,
            transport: $mockTransport
        );

        $occupants = [
            new OccupantDetails(
                index: 1,
                name: 'John Doe',
                firstName: 'John',
                lastName: 'Doe',
                docType: 'Passport',
                docNum: 'USA111',
                country: 'United States',
                phone: '+1 305 555 0100',
                email: 'john@example.com'
            ),
            new OccupantDetails(
                index: 2,
                name: 'Jane Doe',
                firstName: 'Jane',
                lastName: 'Doe',
                docType: 'Passport',
                docNum: 'USA222',
                country: 'United States',
                phone: '' // Empty phone -> inherits
            ),
            new OccupantDetails(
                index: 3,
                name: 'Bob Smith',
                firstName: 'Bob',
                lastName: 'Smith',
                docType: 'Driver License',
                docNum: 'DL999',
                country: 'Canada',
                phone: '+1 416 555 0200' // Own phone -> retained
            ),
        ];

        $res = $this->createSampleReservation(
            uid: 'ovf_res_phones',
            propertyId: '1606',
            checkIn: '2026-12-10',
            checkOut: '2026-12-15'
        );
        $sub = $this->createSampleSubmission(
            uid: 'ovf_res_phones',
            propertyId: '1606',
            checkIn: '2026-12-10',
            checkOut: '2026-12-15',
            occupants: $occupants,
            primaryGuestEmail: 'john@example.com'
        );

        $clearance = $adapter->syncSubmission($res, $sub);

        $this->assertTrue($clearance->isSynced());
        $this->assertSame('777', $clearance->clearanceNumber);

        $step2 = $requests[1];
        $this->assertSame(
            ['+1 305 555 0100', '+1 305 555 0100', '+1 416 555 0200'],
            $step2['data']['phone']
        );
        $this->assertSame(['TITULAR', 'ACOMPAÑANTE', 'ACOMPAÑANTE'], $step2['data']['nexo']);
    }

    public function testStep1FailsWithHttp500RecordsFailedClearance(): void
    {
        $mockTransport = new class implements HttpTransportInterface {
            public int $callCount = 0;
            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                $this->callCount++;
                return [
                    'statusCode' => 500,
                    'body' => 'Database connection failed on external server',
                    'headers' => [],
                    'cookies' => [],
                    'error' => null,
                ];
            }
        };

        $adapter = new HuespedManagerClearanceSync(
            repository: $this->repository,
            transport: $mockTransport
        );

        $res = $this->createSampleReservation('ovf_res_fail_step1', '1707', '2026-11-01', '2026-11-05');
        $sub = $this->createSampleSubmission('ovf_res_fail_step1', '1707', '2026-11-01', '2026-11-05', [$this->createSampleOccupant()]);

        $clearance = $adapter->syncSubmission($res, $sub);

        $this->assertTrue($clearance->isFailed());
        $this->assertFalse($clearance->isSynced());
        $this->assertStringContainsString('Step 1 returned unexpected HTTP status 500', $clearance->errorMessage ?? '');
        $this->assertSame(1, $mockTransport->callCount); // Step 2 must never be executed

        $persisted = $this->repository->findByReservationUid('ovf_res_fail_step1');
        $this->assertNotNull($persisted);
        $this->assertTrue($persisted->isFailed());
    }

    public function testStep1MissingLastIdRecordsFailedClearance(): void
    {
        $mockTransport = new class implements HttpTransportInterface {
            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                return [
                    'statusCode' => 200,
                    'body' => json_encode(['status' => 'error', 'message' => 'Invalid dates']),
                    'headers' => [],
                    'cookies' => [],
                    'error' => null,
                ];
            }
        };

        $adapter = new HuespedManagerClearanceSync(
            repository: $this->repository,
            transport: $mockTransport
        );

        $res = $this->createSampleReservation('ovf_res_bad_json', '1707', '2026-11-01', '2026-11-05');
        $sub = $this->createSampleSubmission('ovf_res_bad_json', '1707', '2026-11-01', '2026-11-05', [$this->createSampleOccupant()]);

        $clearance = $adapter->syncSubmission($res, $sub);

        $this->assertTrue($clearance->isFailed());
        $this->assertStringContainsString("Step 1 response missing valid 'last_id'", $clearance->errorMessage ?? '');
    }

    public function testStep2TransportExceptionRecordedWithoutUncaughtException(): void
    {
        $mockTransport = new class implements HttpTransportInterface {
            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                if (str_contains($url, 'reg_guest_owner_pre.php')) {
                    return [
                        'statusCode' => 200,
                        'body' => json_encode(['last_id' => 999]),
                        'headers' => [],
                        'cookies' => [],
                        'error' => null,
                    ];
                }
                throw new RuntimeException('Connection timed out after 15 seconds');
            }
        };

        $adapter = new HuespedManagerClearanceSync(
            repository: $this->repository,
            transport: $mockTransport
        );

        $res = $this->createSampleReservation('ovf_res_step2_exc', '1707', '2026-11-01', '2026-11-05');
        $sub = $this->createSampleSubmission('ovf_res_step2_exc', '1707', '2026-11-01', '2026-11-05', [$this->createSampleOccupant()]);

        $clearance = $adapter->syncSubmission($res, $sub);

        $this->assertTrue($clearance->isFailed());
        $this->assertStringContainsString('Step 2 transport exception: Connection timed out', $clearance->errorMessage ?? '');
    }

    public function testStep2RedirectsToErrorPageRecordsFailure(): void
    {
        $mockTransport = new class implements HttpTransportInterface {
            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                if (str_contains($url, 'reg_guest_owner_pre.php')) {
                    return [
                        'statusCode' => 200,
                        'body' => json_encode(['last_id' => 888]),
                        'headers' => [],
                        'cookies' => [],
                        'error' => null,
                    ];
                }
                return [
                    'statusCode' => 302,
                    'body' => '',
                    'headers' => ['location' => 'error.php?fallo=doc_duplicate'],
                    'cookies' => [],
                    'error' => null,
                ];
            }
        };

        $adapter = new HuespedManagerClearanceSync(
            repository: $this->repository,
            transport: $mockTransport
        );

        $res = $this->createSampleReservation('ovf_res_step2_err', '1707', '2026-11-01', '2026-11-05');
        $sub = $this->createSampleSubmission('ovf_res_step2_err', '1707', '2026-11-01', '2026-11-05', [$this->createSampleOccupant()]);

        $clearance = $adapter->syncSubmission($res, $sub);

        $this->assertTrue($clearance->isFailed());
        $this->assertStringContainsString('Step 2 redirected to error page: error.php?fallo=doc_duplicate', $clearance->errorMessage ?? '');
    }

    public function testUnconfiguredPropertyFailsGracefully(): void
    {
        $mockTransport = new class implements HttpTransportInterface {
            public int $called = 0;
            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                $this->called++;
                return ['statusCode' => 200, 'body' => '', 'headers' => [], 'cookies' => [], 'error' => null];
            }
        };

        $adapter = new HuespedManagerClearanceSync(
            repository: $this->repository,
            transport: $mockTransport
        );

        $res = $this->createSampleReservation('ovf_res_bad_prop', '9999', '2026-11-01', '2026-11-05');
        $sub = $this->createSampleSubmission('ovf_res_bad_prop', '9999', '2026-11-01', '2026-11-05', [$this->createSampleOccupant()]);

        $clearance = $adapter->syncSubmission($res, $sub);

        $this->assertTrue($clearance->isFailed());
        $this->assertStringContainsString('No check token configured for property ID: 9999', $clearance->errorMessage ?? '');
        $this->assertSame(0, $mockTransport->called);
    }

    public function testSyncSubmissionThrowsInvalidArgumentExceptionWhenOccupantsEmpty(): void
    {
        $mockTransport = new class implements HttpTransportInterface {
            public int $called = 0;
            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                $this->called++;
                return ['statusCode' => 200, 'body' => '', 'headers' => [], 'cookies' => [], 'error' => null];
            }
        };

        $adapter = new HuespedManagerClearanceSync(
            repository: $this->repository,
            transport: $mockTransport
        );

        $res = $this->createSampleReservation('ovf_res_empty', '1707', '2026-11-01', '2026-11-05');
        $sub = $this->createSampleSubmission('ovf_res_empty', '1707', '2026-11-01', '2026-11-05', []);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Guest registry submission must contain at least one occupant for condominium clearance sync.');

        $adapter->syncSubmission($res, $sub);
    }

    public function testSyncForReservationWithEmptyOccupantsRecordsFailure(): void
    {
        $resRepo = new PdoReservationRepository($this->pdo);
        $resRepo->save($this->createSampleReservation('ovf_res_empty_db_occ'));

        $stmt = $this->pdo->prepare('
            INSERT INTO guest_registries (reservation_uid, property_id, check_in, check_out, guest_count, guests_payload)
            VALUES (:uid, "1707", "2026-11-01", "2026-11-05", 0, :payload)
        ');
        $stmt->execute([
            ':uid' => 'ovf_res_empty_db_occ',
            ':payload' => json_encode([]),
        ]);

        $mockTransport = new class implements HttpTransportInterface {
            public int $called = 0;
            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                $this->called++;
                return ['statusCode' => 200, 'body' => '', 'headers' => [], 'cookies' => [], 'error' => null];
            }
        };

        $adapter = HuespedManagerClearanceSync::createDefault($this->pdo, $mockTransport);
        $clearance = $adapter->syncForReservation('ovf_res_empty_db_occ');

        $this->assertTrue($clearance->isFailed());
        $this->assertStringContainsString('Occupant list is empty', $clearance->errorMessage ?? '');
        $this->assertSame(0, $mockTransport->called);
    }

    public function testRetryIncrementsAttemptCount(): void
    {
        $attempt = 0;
        $mockTransport = new class($attempt) implements HttpTransportInterface {
            public function __construct(public int &$attempt) {}

            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                $this->attempt++;
                if ($this->attempt === 1) {
                    return ['statusCode' => 500, 'body' => 'Temporary network error', 'headers' => [], 'cookies' => [], 'error' => null];
                }
                if (str_contains($url, 'reg_guest_owner_pre.php')) {
                    return [
                        'statusCode' => 200,
                        'body' => json_encode(['last_id' => 505]),
                        'headers' => [],
                        'cookies' => ['PHPSESSID' => 's2'],
                        'error' => null,
                    ];
                }
                return [
                    'statusCode' => 302,
                    'body' => '',
                    'headers' => ['location' => 'success.php?hpsucc=succ'],
                    'cookies' => [],
                    'error' => null,
                ];
            }
        };

        $adapter = new HuespedManagerClearanceSync(
            repository: $this->repository,
            transport: $mockTransport
        );

        $res = $this->createSampleReservation('ovf_res_retry', '1707', '2026-11-01', '2026-11-05');
        $sub = $this->createSampleSubmission('ovf_res_retry', '1707', '2026-11-01', '2026-11-05', [$this->createSampleOccupant()]);

        // 1st attempt: fails
        $c1 = $adapter->syncSubmission($res, $sub);
        $this->assertTrue($c1->isFailed());
        $this->assertSame(1, $c1->attempts);

        // 2nd attempt: succeeds
        $c2 = $adapter->syncSubmission($res, $sub);
        $this->assertTrue($c2->isSynced());
        $this->assertSame(2, $c2->attempts);
        $this->assertSame('505', $c2->clearanceNumber);
    }

    public function testBaseUrlPathNormalizationDoesNotDuplicatePath(): void
    {
        $requests = [];
        $mockTransport = new class($requests) implements HttpTransportInterface {
            public function __construct(public array &$requests) {}
            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                $this->requests[] = $url;
                if (str_contains($url, 'reg_guest_owner_pre.php')) {
                    return ['statusCode' => 200, 'body' => json_encode(['last_id' => 101]), 'headers' => [], 'cookies' => [], 'error' => null];
                }
                return ['statusCode' => 302, 'body' => '', 'headers' => ['location' => 'success.php'], 'cookies' => [], 'error' => null];
            }
        };

        // Pass URL already containing /propietarios/production and trailing slash
        $adapter = new HuespedManagerClearanceSync(
            repository: $this->repository,
            transport: $mockTransport,
            baseUrl: 'https://salguerosunset.huespedmanager.com.co/propietarios/production/'
        );

        $res = $this->createSampleReservation('ovf_res_norm', '1606', '2026-11-01', '2026-11-05');
        $sub = $this->createSampleSubmission('ovf_res_norm', '1606', '2026-11-01', '2026-11-05', [$this->createSampleOccupant()]);

        $adapter->syncSubmission($res, $sub);

        $this->assertCount(2, $requests);
        $this->assertSame(
            'https://salguerosunset.huespedmanager.com.co/propietarios/production/reg_guest_owner_pre.php?unit=1606&check=ep24281580',
            $requests[0]
        );
        $this->assertSame(
            'https://salguerosunset.huespedmanager.com.co/propietarios/production/reg_hpds_pre.php',
            $requests[1]
        );
    }

    public function testSyncForReservationSucceedsAndRecordsAuditLogWithAdminContext(): void
    {
        // 1. Seed reservation
        $resRepo = new PdoReservationRepository($this->pdo);
        $resRepo->save(Reservation::create(
            reservationUid: 'ovf_res_deep_test',
            propertyId: '1707',
            guestName: 'Carlos Andres Mendoza Perez',
            guestEmail: 'carlos@example.com',
            guestPhone: '+34 600 123 456',
            checkIn: '2026-11-01',
            checkOut: '2026-11-05',
            totalPrice: 1250000.0,
            status: ReservationStatus::CONFIRMED,
            notes: 'Late arrival at 6 PM'
        ));

        // 2. Seed guest registry
        $stmt = $this->pdo->prepare('
            INSERT INTO guest_registries (
                reservation_uid, property_id, check_in, check_out,
                guest_count, guests_payload, car_plates, car_model, ip_address
            ) VALUES (
                :reservation_uid, :property_id, :check_in, :check_out,
                :guest_count, :guests_payload, :car_plates, :car_model, :ip_address
            )
        ');
        $stmt->execute([
            ':reservation_uid' => 'ovf_res_deep_test',
            ':property_id' => '1707',
            ':check_in' => '2026-11-01',
            ':check_out' => '2026-11-05',
            ':guest_count' => 1,
            ':guests_payload' => json_encode([
                [
                    'first_name' => 'Carlos',
                    'last_name' => 'Mendoza',
                    'doc_type' => 'Passport',
                    'doc_number' => 'PA987654',
                    'country' => 'España',
                    'phone' => '+34 600 123 456',
                ],
            ]),
            ':car_plates' => 'XYZ-123',
            ':car_model' => 'Toyota Fortuner',
            ':ip_address' => '192.168.1.100',
        ]);

        $requests = [];
        $mockTransport = new class($requests) implements HttpTransportInterface {
            public int $step1Count = 0;
            /** @param array<int, array<string, mixed>> $requests */
            public function __construct(public array &$requests) {}

            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                $this->requests[] = [
                    'url' => $url,
                    'data' => $data,
                    'headers' => $headers,
                    'options' => $options,
                ];

                if (str_contains($url, 'reg_guest_owner_pre.php')) {
                    $this->step1Count++;
                    $consecutivo = $this->step1Count === 1 ? 888 : 889;
                    return [
                        'statusCode' => 200,
                        'body' => json_encode(['last_id' => $consecutivo, 'unit_apt' => '1707']),
                        'headers' => ['content-type' => 'application/json'],
                        'cookies' => ['PHPSESSID' => 'test-session-xyz'],
                        'error' => null,
                    ];
                }

                if (str_contains($url, 'reg_hpds_pre.php')) {
                    return [
                        'statusCode' => 302,
                        'body' => '',
                        'headers' => ['location' => 'success.php?hpsucc=succ'],
                        'cookies' => [],
                        'error' => null,
                    ];
                }

                return ['statusCode' => 404, 'body' => '', 'headers' => [], 'cookies' => [], 'error' => 'Not found'];
            }
        };

        // Create via createDefault to ensure factory correctly configures all collaborators
        $adapter = HuespedManagerClearanceSync::createDefault($this->pdo, $mockTransport);

        $admin = new AdminContext(
            adminUserId: 42,
            ipAddress: '192.168.1.50',
            userAgent: 'Mozilla/5.0 AdminPanel/1.0'
        );

        // First sync
        $clearance = $adapter->syncForReservation('ovf_res_deep_test', $admin);

        $this->assertTrue($clearance->isSynced());
        $this->assertSame('888', $clearance->clearanceNumber);
        $this->assertNull($clearance->errorMessage);

        // Verify Step 1 payload formatted notes with vehicle info
        $this->assertCount(2, $requests);
        $step1 = $requests[0];
        $this->assertSame('Late arrival at 6 PM | Vehicle: Toyota Fortuner', $step1['data']['send_observacion']);
        $this->assertSame('XYZ-123', $step1['data']['send_car']);

        // Verify audit log entry
        $logs = AuditLogger::getLogsForEntity($this->pdo, 'reservation', 'ovf_res_deep_test');
        $this->assertCount(1, $logs);
        $log1 = $logs[0];
        $this->assertSame('condominium_clearance_sync', $log1['action']);
        $this->assertSame(42, (int) $log1['admin_user_id']);
        $this->assertSame('192.168.1.50', $log1['ip_address']);
        $this->assertSame('Mozilla/5.0 AdminPanel/1.0', $log1['user_agent']);
        $this->assertNull($log1['payload_before']);
        $after1 = json_decode((string) $log1['payload_after'], true);
        $this->assertSame('888', $after1['clearance_number']);
        $this->assertSame('synced', $after1['status']);

        // Retry sync (second attempt)
        $clearance2 = $adapter->syncForReservation('ovf_res_deep_test', $admin);
        $this->assertTrue($clearance2->isSynced());
        $this->assertSame('889', $clearance2->clearanceNumber);
        $this->assertSame(2, $clearance2->attempts);

        $logsUpdated = AuditLogger::getLogsForEntity($this->pdo, 'reservation', 'ovf_res_deep_test');
        $this->assertCount(2, $logsUpdated);
        $log2 = $logsUpdated[1];
        $this->assertSame('condominium_clearance_retry', $log2['action']);
        $before2 = json_decode((string) $log2['payload_before'], true);
        $after2 = json_decode((string) $log2['payload_after'], true);
        $this->assertSame('888', $before2['clearance_number']);
        $this->assertSame('889', $after2['clearance_number']);
    }

    public function testSyncForReservationFailedHandshakeRecordsAuditLogWhenAdminContextPassed(): void
    {
        $resRepo = new PdoReservationRepository($this->pdo);
        $resRepo->save(Reservation::create(
            reservationUid: 'ovf_res_fail_audit',
            propertyId: '1707',
            guestName: 'Carlos Fail',
            guestEmail: 'fail@example.com',
            guestPhone: '+34 600 000 000',
            checkIn: '2026-11-01',
            checkOut: '2026-11-05',
            totalPrice: 1000000.0,
            status: ReservationStatus::CONFIRMED
        ));

        $stmt = $this->pdo->prepare('
            INSERT INTO guest_registries (reservation_uid, property_id, check_in, check_out, guest_count, guests_payload)
            VALUES (:uid, "1707", "2026-11-01", "2026-11-05", 1, :payload)
        ');
        $stmt->execute([
            ':uid' => 'ovf_res_fail_audit',
            ':payload' => json_encode([['first_name' => 'Carlos', 'last_name' => 'Fail', 'doc_type' => 'CC', 'doc_number' => 'CC12345678']]),
        ]);

        $mockTransport = new class implements HttpTransportInterface {
            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                return ['statusCode' => 503, 'body' => 'Service Unavailable', 'headers' => [], 'cookies' => [], 'error' => null];
            }
        };

        $adapter = HuespedManagerClearanceSync::createDefault($this->pdo, $mockTransport);
        $admin = new AdminContext(adminUserId: 99, ipAddress: '10.0.0.1');

        $clearance = $adapter->syncForReservation('ovf_res_fail_audit', $admin);
        $this->assertTrue($clearance->isFailed());
        $this->assertStringContainsString('503', $clearance->errorMessage ?? '');

        $logs = AuditLogger::getLogsForEntity($this->pdo, 'reservation', 'ovf_res_fail_audit');
        $this->assertCount(1, $logs);
        $this->assertSame('condominium_clearance_sync', $logs[0]['action']);
        $after = json_decode((string) $logs[0]['payload_after'], true);
        $this->assertSame('failed', $after['status']);
    }

    public function testSyncForReservationThrowsLogicExceptionWhenReservationRepositoryNotConfigured(): void
    {
        $adapter = new HuespedManagerClearanceSync(
            repository: $this->repository,
            reservationRepository: null
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Reservation repository is not configured on HuespedManagerClearanceSync.');

        $adapter->syncForReservation('ovf_res_test');
    }

    public function testSyncForReservationThrowsLogicExceptionWhenSearchAdapterNotConfigured(): void
    {
        $resRepo = new PdoReservationRepository($this->pdo);
        $resRepo->save($this->createSampleReservation('ovf_res_no_adapter'));

        $adapter = new HuespedManagerClearanceSync(
            repository: $this->repository,
            reservationRepository: $resRepo,
            searchAdapter: null
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Reservation search adapter is not configured on HuespedManagerClearanceSync.');

        $adapter->syncForReservation('ovf_res_no_adapter');
    }

    public function testSyncForReservationThrowsReservationNotFoundExceptionWhenReservationMissing(): void
    {
        $mockTransport = new class implements HttpTransportInterface {
            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                return ['statusCode' => 200, 'body' => '', 'headers' => [], 'cookies' => [], 'error' => null];
            }
        };
        $adapter = HuespedManagerClearanceSync::createDefault($this->pdo, $mockTransport);

        $this->expectException(ReservationNotFoundException::class);
        $this->expectExceptionMessage('Reservation not found: ovf_nonexistent_123');

        $adapter->syncForReservation('ovf_nonexistent_123');
    }

    public function testSyncForReservationThrowsGuestRegistryRequiredExceptionWhenGuestRegistryMissing(): void
    {
        $resRepo = new PdoReservationRepository($this->pdo);
        $resRepo->save(Reservation::create(
            reservationUid: 'ovf_res_no_reg',
            propertyId: '1707',
            guestName: 'Jane Doe',
            guestEmail: 'jane@example.com',
            guestPhone: '+1 305 000 0000',
            checkIn: '2026-11-01',
            checkOut: '2026-11-05',
            totalPrice: 800000.0,
            status: ReservationStatus::CONFIRMED
        ));

        $mockTransport = new class implements HttpTransportInterface {
            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                return ['statusCode' => 200, 'body' => '', 'headers' => [], 'cookies' => [], 'error' => null];
            }
        };
        $adapter = HuespedManagerClearanceSync::createDefault($this->pdo, $mockTransport);

        $this->expectException(GuestRegistryRequiredException::class);
        $this->expectExceptionMessage('Guest registry must be submitted before condominium clearance can be synced for reservation: ovf_res_no_reg');

        $adapter->syncForReservation('ovf_res_no_reg');
    }

    public function testSyncSubmissionSucceedsWithInMemorySubmission(): void
    {
        $reservation = Reservation::create(
            reservationUid: 'ovf_res_in_memory',
            propertyId: '1606',
            guestName: 'Ana Gomez',
            guestEmail: 'ana@example.com',
            guestPhone: '+57 311 000 0000',
            checkIn: '2026-12-01',
            checkOut: '2026-12-06',
            totalPrice: 1500000.0,
            status: ReservationStatus::CONFIRMED,
            notes: 'Honeymoon stay'
        );

        $occupants = [
            new OccupantDetails(
                index: 1,
                name: 'Ana Gomez',
                age: 29,
                docType: 'Cédula de Ciudadanía',
                docNum: '1098765432',
                email: 'ana@example.com',
                firstName: 'Ana',
                lastName: 'Gomez',
                phone: '+57 311 000 0000',
                country: 'Colombia'
            ),
        ];

        $submission = new GuestRegistrySubmission(
            reservationCode: 'ovf_res_in_memory',
            propertyId: '1606',
            checkIn: '2026-12-01',
            checkOut: '2026-12-06',
            occupants: $occupants,
            primaryGuestEmail: 'ana@example.com',
            carPlates: 'ABC-789',
            carModel: 'Renault Duster'
        );

        $requests = [];
        $mockTransport = new class($requests) implements HttpTransportInterface {
            /** @param array<int, array<string, mixed>> $requests */
            public function __construct(public array &$requests) {}
            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                $this->requests[] = ['url' => $url, 'data' => $data];
                if (str_contains($url, 'reg_guest_owner_pre.php')) {
                    return ['statusCode' => 200, 'body' => json_encode(['last_id' => 912]), 'headers' => [], 'cookies' => ['c' => '1'], 'error' => null];
                }
                return ['statusCode' => 302, 'body' => '', 'headers' => ['location' => 'success.php'], 'cookies' => [], 'error' => null];
            }
        };

        $adapter = new HuespedManagerClearanceSync(
            repository: $this->repository,
            transport: $mockTransport
        );

        $clearance = $adapter->syncSubmission($reservation, $submission);

        $this->assertTrue($clearance->isSynced());
        $this->assertSame('912', $clearance->clearanceNumber);

        // Verify request payload formatted notes
        $this->assertCount(2, $requests);
        $step1 = $requests[0];
        $this->assertSame('Honeymoon stay | Vehicle: Renault Duster', $step1['data']['send_observacion']);
        $this->assertSame('ABC-789', $step1['data']['send_car']);

        // Verify persistence
        $saved = $this->repository->findByReservationUid('ovf_res_in_memory');
        $this->assertNotNull($saved);
        $this->assertSame('912', $saved->clearanceNumber);
    }

    public function testPortalLiveContractRequiresBtnCheckinAndStandardFieldNames(): void
    {
        $requests = [];
        $mockTransport = new class($requests) implements HttpTransportInterface {
            /** @param array<int, array<string, mixed>> $requests */
            public function __construct(public array &$requests) {}

            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                $this->requests[] = ['url' => $url, 'data' => $data];

                if (str_contains($url, 'reg_guest_owner_pre.php')) {
                    // Portal requires indate, outdate, car, cant_hp
                    if (!isset($data['indate']) || !isset($data['outdate']) || !isset($data['car']) || !isset($data['cant_hp'])) {
                        return [
                            'statusCode' => 200,
                            'body' => json_encode(['last_id' => 999]), // Step 1 still created ID in prod with defaults
                            'headers' => [],
                            'cookies' => ['PHPSESSID' => 'session123'],
                            'error' => null,
                        ];
                    }
                    return [
                        'statusCode' => 200,
                        'body' => json_encode(['last_id' => 1001]),
                        'headers' => [],
                        'cookies' => ['PHPSESSID' => 'session123'],
                        'error' => null,
                    ];
                }

                if (str_contains($url, 'reg_hpds_pre.php')) {
                    // Portal requires btn_checkin. If missing, it exits 200 with empty body!
                    if (!isset($data['btn_checkin'])) {
                        return [
                            'statusCode' => 200,
                            'body' => '',
                            'headers' => [],
                            'cookies' => [],
                            'error' => null,
                        ];
                    }

                    return [
                        'statusCode' => 302,
                        'body' => '',
                        'headers' => ['location' => 'success.php?hpsucc=succ&book=1001'],
                        'cookies' => [],
                        'error' => null,
                    ];
                }

                return ['statusCode' => 404, 'body' => '', 'headers' => [], 'cookies' => [], 'error' => null];
            }
        };

        $adapter = new HuespedManagerClearanceSync(
            repository: $this->repository,
            transport: $mockTransport
        );

        $occupants = [
            $this->createSampleOccupant(index: 1, name: 'Julian Robles', phone: '3146452425'),
        ];
        $reservation = $this->createSampleReservation(
            uid: 'ovf_res_prod_repro',
            propertyId: '1707',
            checkIn: '2026-10-10',
            checkOut: '2026-10-11'
        );
        $submission = $this->createSampleSubmission(
            uid: 'ovf_res_prod_repro',
            propertyId: '1707',
            checkIn: '2026-10-10',
            checkOut: '2026-10-11',
            occupants: $occupants,
            carPlates: 'UUY109'
        );

        $clearance = $adapter->syncSubmission($reservation, $submission);

        $this->assertTrue($clearance->isSynced());
        $this->assertSame('1001', $clearance->clearanceNumber);

        // Verify Step 1 authoritative fields
        $step1 = $requests[0]['data'];
        $this->assertSame('2026-10-10', $step1['indate']);
        $this->assertSame('2026-10-11', $step1['outdate']);
        $this->assertSame('UUY109', $step1['car']);
        $this->assertSame('1', $step1['cant_hp']);

        // Verify Step 2 submit token
        $step2 = $requests[1]['data'];
        $this->assertSame('0', $step2['btn_checkin']);
    }
}


