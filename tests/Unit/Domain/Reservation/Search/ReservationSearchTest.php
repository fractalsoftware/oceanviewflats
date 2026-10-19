<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Reservation\Search;

use OceanViewFlats\Domain\Reservation\InMemoryReservationRepository;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use OceanViewFlats\Domain\Reservation\Search\InMemoryReservationSearchAdapter;
use OceanViewFlats\Domain\Reservation\Search\PdoReservationSearchAdapter;
use OceanViewFlats\Domain\Reservation\Search\ReservationDossier;
use OceanViewFlats\Domain\Reservation\Search\ReservationSearchCriteria;
use OceanViewFlats\Domain\Reservation\Search\ReservationSearchResult;
use PDO;
use PHPUnit\Framework\TestCase;

final class ReservationSearchTest extends TestCase
{
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
            refunded_amount REAL DEFAULT 0.00,
            source TEXT DEFAULT 'web',
            external_confirmation_code TEXT DEFAULT NULL,
            channel_block_uid TEXT DEFAULT NULL,
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
            notes TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE guest_registries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reservation_uid TEXT UNIQUE,
            property_id TEXT,
            check_in TEXT,
            check_out TEXT,
            guest_count INTEGER DEFAULT 1,
            guests_payload TEXT,
            car_plates TEXT,
            car_model TEXT,
            ip_address TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            email TEXT
        )");

        $pdo->exec("CREATE TABLE admin_audit_logs (
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
        )");

        $pdo->exec("CREATE TABLE reservation_refunds (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reservation_uid TEXT,
            mercadopago_refund_id TEXT,
            mercadopago_payment_id TEXT,
            amount REAL,
            status TEXT,
            reason TEXT,
            source TEXT,
            admin_user_id INTEGER,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");

        return $pdo;
    }

    public function testCriteriaDefaultsAndAliases(): void
    {
        $criteria = ReservationSearchCriteria::fromArray([
            'search' => '  john doe  ',
            'per_page' => 15,
            'page' => 2,
            'sort_by' => 'total_price',
            'sort_dir' => 'asc',
            'registry_status' => 'completed',
            'source' => 'airbnb',
        ]);

        $this->assertSame('john doe', $criteria->query);
        $this->assertSame('all', $criteria->propertyId);
        $this->assertSame('all', $criteria->status);
        $this->assertSame(2, $criteria->page);
        $this->assertSame(15, $criteria->limit);
        $this->assertSame(15, $criteria->offset());
        $this->assertSame('total_price', $criteria->sortBy);
        $this->assertSame('asc', $criteria->sortDir);
        $this->assertSame('completed', $criteria->registryStatus);
        $this->assertSame('airbnb', $criteria->source);

        $arr = $criteria->toArray();
        $this->assertSame('john doe', $arr['query']);
        $this->assertSame('john doe', $arr['search']);
        $this->assertSame(15, $arr['limit']);
        $this->assertSame(15, $arr['per_page']);
    }

    public function testCriteriaSanitizesLimitsAndPages(): void
    {
        $criteria = ReservationSearchCriteria::fromArray([
            'page' => -5,
            'limit' => 500,
            'sort_by' => 'malicious_col',
            'sort_dir' => 'invalid_dir',
        ]);

        $this->assertSame(1, $criteria->page);
        $this->assertSame(100, $criteria->limit);
        $this->assertSame('check_in', $criteria->sortBy);
        $this->assertSame('desc', $criteria->sortDir);
    }

    public function testSearchResultDto(): void
    {
        $items = [['reservation_uid' => 'ovf_1'], ['reservation_uid' => 'ovf_2']];
        $result = ReservationSearchResult::create($items, 25, 1, 10);

        $this->assertSame(25, $result->totalCount);
        $this->assertSame(3, $result->totalPages);
        $this->assertSame(1, $result->page);
        $this->assertSame(10, $result->limit);
        $this->assertCount(2, $result->items);

        $array = $result->toArray();
        $this->assertSame(25, $array['total']);
        $this->assertSame(3, $array['totalPages']);
    }

    public function testDossierDto(): void
    {
        $res = ['reservation_uid' => 'ovf_dossier_1'];
        $reg = ['reservation_uid' => 'ovf_dossier_1', 'guest_count' => 2];
        $logs = [['action' => 'update', 'admin_user_name' => 'Admin Boss']];
        $refunds = [['amount' => 100000.0]];

        $dossier = new ReservationDossier($res, $reg, $logs, $refunds);

        $this->assertSame('ovf_dossier_1', $dossier->reservation['reservation_uid']);
        $this->assertSame(2, $dossier->guestRegistry['guest_count'] ?? null);
        $this->assertCount(1, $dossier->auditLogs);
        $this->assertCount(1, $dossier->refunds);

        $arr = $dossier->toArray();
        $this->assertSame('ovf_dossier_1', $arr['reservation']['reservation_uid']);
        $this->assertSame(2, $arr['guest_registry']['guest_count']);
        $this->assertSame('Admin Boss', $arr['audit_logs'][0]['admin_user_name']);
    }

    public function testInMemorySearchAdapterFilteringAndPagination(): void
    {
        $adapter = new InMemoryReservationSearchAdapter([
            [
                'id' => 1,
                'reservation_uid' => 'ovf_alpha',
                'property_id' => '1606',
                'guest_name' => 'Alice Walker',
                'guest_email' => 'alice@example.com',
                'guest_phone' => '+57 300 111 2233',
                'check_in' => '2026-11-01',
                'check_out' => '2026-11-05',
                'total_price' => 1000000.0,
                'status' => 'confirmed',
                'registry_completed' => 1,
                'source' => 'web',
                'created_at' => '2026-10-01 10:00:00',
            ],
            [
                'id' => 2,
                'reservation_uid' => 'ovf_beta',
                'property_id' => '1606',
                'guest_name' => 'Bob Marley',
                'guest_email' => 'bob@example.com',
                'guest_phone' => '+57 300 222 3344',
                'check_in' => '2026-11-10',
                'check_out' => '2026-11-15',
                'total_price' => 2000000.0,
                'status' => 'pending_payment',
                'registry_completed' => 0,
                'source' => 'whatsapp',
                'created_at' => '2026-10-02 10:00:00',
            ],
            [
                'id' => 3,
                'reservation_uid' => 'ovf_gamma',
                'property_id' => '1707',
                'guest_name' => 'Charlie Brown',
                'guest_email' => 'charlie@example.com',
                'guest_phone' => '+1 555 333 4455',
                'check_in' => '2026-11-20',
                'check_out' => '2026-11-25',
                'total_price' => 1500000.0,
                'status' => 'confirmed',
                'registry_completed' => 0,
                'source' => 'airbnb',
                'created_at' => '2026-10-03 10:00:00',
            ],
        ]);

        // 1. Query search
        $res = $adapter->search(ReservationSearchCriteria::fromArray(['query' => 'charlie']));
        $this->assertSame(1, $res->totalCount);
        $this->assertSame('ovf_gamma', $res->items[0]['reservation_uid']);

        // 2. Property filter
        $resProp = $adapter->search(ReservationSearchCriteria::fromArray(['property_id' => '1606']));
        $this->assertSame(2, $resProp->totalCount);

        // 3. Status filter
        $resStatus = $adapter->search(ReservationSearchCriteria::fromArray(['status' => 'pending_payment']));
        $this->assertSame(1, $resStatus->totalCount);
        $this->assertSame('ovf_beta', $resStatus->items[0]['reservation_uid']);

        // 4. Registry status filter
        $resReg = $adapter->search(ReservationSearchCriteria::fromArray(['registry_status' => 'completed']));
        $this->assertSame(1, $resReg->totalCount);
        $this->assertSame('ovf_alpha', $resReg->items[0]['reservation_uid']);

        // 5. Source filter
        $resSource = $adapter->search(ReservationSearchCriteria::fromArray(['source' => 'airbnb']));
        $this->assertSame(1, $resSource->totalCount);
        $this->assertSame('ovf_gamma', $resSource->items[0]['reservation_uid']);

        // 6. Date range
        $resDate = $adapter->search(ReservationSearchCriteria::fromArray([
            'check_in_from' => '2026-11-09',
            'check_in_to' => '2026-11-15',
        ]));
        $this->assertSame(1, $resDate->totalCount);
        $this->assertSame('ovf_beta', $resDate->items[0]['reservation_uid']);

        // 7. Sorting
        $resSort = $adapter->search(ReservationSearchCriteria::fromArray([
            'sort_by' => 'total_price',
            'sort_dir' => 'asc',
        ]));
        $this->assertSame('ovf_alpha', $resSort->items[0]['reservation_uid']);
        $this->assertSame('ovf_beta', $resSort->items[2]['reservation_uid']);
    }

    public function testInMemorySearchAdapterAuditTrailAndRegistries(): void
    {
        $adapter = new InMemoryReservationSearchAdapter(
            reservations: [
                [
                    'reservation_uid' => 'ovf_dossier_test',
                    'property_id' => '1606',
                    'guest_name' => 'Diana Prince',
                    'guest_email' => 'diana@amazon.com',
                ]
            ],
            auditLogs: [
                [
                    'id' => 1,
                    'entity_type' => 'reservation',
                    'entity_id' => 'ovf_dossier_test',
                    'action' => 'status_updated',
                    'created_at' => '2026-10-01 12:00:00',
                ],
                [
                    'id' => 2,
                    'entity_type' => 'reservation',
                    'entity_id' => 'ovf_dossier_test',
                    'action' => 'refund_processed',
                    'created_at' => '2026-10-02 15:30:00',
                ],
            ],
            guestRegistries: [
                'ovf_dossier_test' => [
                    'reservation_uid' => 'ovf_dossier_test',
                    'guest_count' => 2,
                    'guests_payload' => '[{"name":"Diana Prince","primary":true}]',
                ]
            ],
            refunds: [
                [
                    'id' => 1,
                    'reservation_uid' => 'ovf_dossier_test',
                    'amount' => 50000.0,
                    'created_at' => '2026-10-02 15:30:00',
                ]
            ]
        );

        $dossier = $adapter->findWithAuditTrail('ovf_dossier_test');
        $this->assertNotNull($dossier);
        $this->assertSame('ovf_dossier_test', $dossier->reservation['reservation_uid']);
        $this->assertNotNull($dossier->guestRegistry);
        $this->assertIsArray($dossier->guestRegistry['guests_payload']);
        $this->assertSame('Diana Prince', $dossier->guestRegistry['guests_payload'][0]['name']);

        // Check audit log order (created_at DESC)
        $this->assertCount(2, $dossier->auditLogs);
        $this->assertSame('refund_processed', $dossier->auditLogs[0]['action']);

        $this->assertCount(1, $dossier->refunds);
        $this->assertSame(50000.0, $dossier->refunds[0]['amount']);

        $notFound = $adapter->findWithAuditTrail('non_existent');
        $this->assertNull($notFound);
    }

    public function testInMemoryAdapterSyncsWithInMemoryReservationRepository(): void
    {
        $repo = new InMemoryReservationRepository();
        $adapter = new InMemoryReservationSearchAdapter(repository: $repo);

        $reservation = new Reservation(
            reservationUid: 'ovf_synced',
            propertyId: '1707',
            guestName: 'Synced Guest',
            guestEmail: 'sync@example.com',
            guestPhone: '+57 300 777 8899',
            checkIn: '2026-12-01',
            checkOut: '2026-12-05',
            totalPrice: 1800000.0,
            status: ReservationStatus::CONFIRMED
        );
        $repo->save($reservation);

        $search = $adapter->search(ReservationSearchCriteria::fromArray(['query' => 'Synced Guest']));
        $this->assertSame(1, $search->totalCount);
        $this->assertSame('ovf_synced', $search->items[0]['reservation_uid']);

        $dossier = $adapter->findWithAuditTrail('ovf_synced');
        $this->assertNotNull($dossier);
        $this->assertSame('Synced Guest', $dossier->reservation['guest_name']);
    }

    public function testPdoReservationSearchAdapterSearchAndPagination(): void
    {
        $pdo = $this->createSqlitePdo();
        $adapter = new PdoReservationSearchAdapter($pdo);

        // Seed 4 reservations
        $stmt = $pdo->prepare("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, refunded_amount, source, status,
                registry_completed, created_at
            ) VALUES (
                :uid, :prop, :name, :email, :phone,
                :in, :out, :price, :ref, :src, :status,
                :reg, :created
            )
        ");

        $stmt->execute([
            ':uid' => 'ovf_p1', ':prop' => '1606', ':name' => 'Maria Rodriguez',
            ':email' => 'maria@example.com', ':phone' => '+57 301 111 1111',
            ':in' => '2026-11-01', ':out' => '2026-11-05', ':price' => 1200000.0,
            ':ref' => 0.0, ':src' => 'web', ':status' => 'confirmed',
            ':reg' => 1, ':created' => '2026-10-01 10:00:00',
        ]);

        $stmt->execute([
            ':uid' => 'ovf_p2', ':prop' => '1606', ':name' => 'Juan Valdez',
            ':email' => 'juan@example.com', ':phone' => '+57 302 222 2222',
            ':in' => '2026-11-10', ':out' => '2026-11-15', ':price' => 2400000.0,
            ':ref' => 100000.0, ':src' => 'direct', ':status' => 'pending_payment',
            ':reg' => 0, ':created' => '2026-10-02 10:00:00',
        ]);

        $stmt->execute([
            ':uid' => 'ovf_p3', ':prop' => '1707', ':name' => 'Sofia Vergara',
            ':email' => 'sofia@hollywood.com', ':phone' => '+1 310 333 3333',
            ':in' => '2026-11-20', ':out' => '2026-11-25', ':price' => 3500000.0,
            ':ref' => 0.0, ':src' => 'airbnb', ':status' => 'confirmed',
            ':reg' => 1, ':created' => '2026-10-03 10:00:00',
        ]);

        $stmt->execute([
            ':uid' => 'ovf_p4', ':prop' => '1707', ':name' => 'Pedro Pascal',
            ':email' => 'pedro@hollywood.com', ':phone' => '+1 310 444 4444',
            ':in' => '2026-12-01', ':out' => '2026-12-06', ':price' => 2800000.0,
            ':ref' => 0.0, ':src' => 'web', ':status' => 'cancelled',
            ':reg' => 0, ':created' => '2026-10-04 10:00:00',
        ]);

        // 1. Search by name query
        $resName = $adapter->search(ReservationSearchCriteria::fromArray(['search' => 'Sofia']));
        $this->assertSame(1, $resName->totalCount);
        $this->assertSame('ovf_p3', $resName->items[0]['reservation_uid']);

        // 2. Search by phone query
        $resPhone = $adapter->search(ReservationSearchCriteria::fromArray(['search' => '+57 302']));
        $this->assertSame(1, $resPhone->totalCount);
        $this->assertSame('ovf_p2', $resPhone->items[0]['reservation_uid']);

        // 3. Search by property and registry_status
        $resProp = $adapter->search(ReservationSearchCriteria::fromArray([
            'property_id' => '1606',
            'registry_status' => 'completed',
        ]));
        $this->assertSame(1, $resProp->totalCount);
        $this->assertSame('ovf_p1', $resProp->items[0]['reservation_uid']);

        // 4. Search by source
        $resSrc = $adapter->search(ReservationSearchCriteria::fromArray(['source' => 'airbnb']));
        $this->assertSame(1, $resSrc->totalCount);
        $this->assertSame('ovf_p3', $resSrc->items[0]['reservation_uid']);

        // 5. Date range
        $resDates = $adapter->search(ReservationSearchCriteria::fromArray([
            'check_in_from' => '2026-11-05',
            'check_in_to' => '2026-11-25',
        ]));
        $this->assertSame(2, $resDates->totalCount);

        // 6. Sorting & Pagination
        $resPaged = $adapter->search(ReservationSearchCriteria::fromArray([
            'sort_by' => 'total_price',
            'sort_dir' => 'desc',
            'page' => 1,
            'limit' => 2,
        ]));
        $this->assertSame(4, $resPaged->totalCount);
        $this->assertSame(2, $resPaged->totalPages);
        $this->assertCount(2, $resPaged->items);
        $this->assertSame('ovf_p3', $resPaged->items[0]['reservation_uid']); // 3,500,000
        $this->assertSame('ovf_p4', $resPaged->items[1]['reservation_uid']); // 2,800,000
    }

    public function testPdoReservationSearchAdapterDossierAndJoins(): void
    {
        $pdo = $this->createSqlitePdo();
        $adapter = new PdoReservationSearchAdapter($pdo);

        // Seed admin user
        $pdo->exec("INSERT INTO admin_users (id, name, email) VALUES (10, 'Operations Manager', 'ops@ovf.com')");

        // Seed reservation
        $pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status
            ) VALUES (
                'ovf_dossier_sql', '1606', 'Shakira Mebarak', 'shakira@example.com', '+57 300 555 6677',
                '2026-12-15', '2026-12-20', 4500000.0, 'confirmed'
            )
        ");

        // Seed guest registry
        $pdo->exec("
            INSERT INTO guest_registries (
                reservation_uid, property_id, check_in, check_out, guest_count, guests_payload
            ) VALUES (
                'ovf_dossier_sql', '1606', '2026-12-15', '2026-12-20', 1, '[{\"name\":\"Shakira\",\"id\":\"123\"}]'
            )
        ");

        // Seed audit logs
        $pdo->exec("
            INSERT INTO admin_audit_logs (
                admin_user_id, action, entity_type, entity_id, created_at
            ) VALUES (
                10, 'custom_rate_override', 'reservation', 'ovf_dossier_sql', '2026-11-01 10:00:00'
            )
        ");

        // Seed refund
        $pdo->exec("
            INSERT INTO reservation_refunds (
                reservation_uid, amount, status, admin_user_id, created_at
            ) VALUES (
                'ovf_dossier_sql', 200000.0, 'approved', 10, '2026-11-02 11:00:00'
            )
        ");

        // Execute dossier retrieval
        $dossier = $adapter->findWithAuditTrail('ovf_dossier_sql');
        $this->assertNotNull($dossier);
        $this->assertSame('ovf_dossier_sql', $dossier->reservation['reservation_uid']);

        // Verify guest registry was hydrated and JSON decoded
        $this->assertNotNull($dossier->guestRegistry);
        $this->assertIsArray($dossier->guestRegistry['guests_payload']);
        $this->assertSame('Shakira', $dossier->guestRegistry['guests_payload'][0]['name']);

        // Verify audit log joined admin_users
        $this->assertCount(1, $dossier->auditLogs);
        $this->assertSame('custom_rate_override', $dossier->auditLogs[0]['action']);
        $this->assertSame('Operations Manager', $dossier->auditLogs[0]['admin_user_name']);
        $this->assertSame('ops@ovf.com', $dossier->auditLogs[0]['admin_user_email']);

        // Verify refunds joined admin_users
        $this->assertCount(1, $dossier->refunds);
        $this->assertSame('Operations Manager', $dossier->refunds[0]['admin_user_name']);

        // Direct helper checks
        $registryDirect = $adapter->findGuestRegistry('ovf_dossier_sql');
        $this->assertNotNull($registryDirect);
        $this->assertIsArray($registryDirect['guests_payload']);

        $refundsDirect = $adapter->findRefunds('ovf_dossier_sql');
        $this->assertCount(1, $refundsDirect);

        // Missing records
        $this->assertNull($adapter->findWithAuditTrail('non_existent'));
        $this->assertNull($adapter->findGuestRegistry('non_existent'));
        $this->assertSame([], $adapter->findRefunds('non_existent'));
    }

    public function testFindGuestRegistryTransparentlyDecryptsEncryptedEnvelopeAndReadsLegacyPlaintext(): void
    {
        $pdo = $this->createSqlitePdo();
        $adapter = new PdoReservationSearchAdapter($pdo);

        $encryptedPayload = \OceanViewFlats\Domain\Security\PayloadEncryptor::encrypt([
            [
                'index' => 1,
                'name' => 'Elena Gomez',
                'doc_type' => 'Passport',
                'doc_num' => 'US99887766',
                'age' => 34,
                'country' => 'COLOMBIA',
                'email' => 'elena@example.com',
                'phone' => '+57 300 111 2233',
            ],
        ]);

        $stmt = $pdo->prepare('
            INSERT INTO guest_registries (
                reservation_uid, property_id, check_in, check_out, guest_count, guests_payload
            ) VALUES (
                :uid, :prop, :in, :out, :cnt, :payload
            )
        ');
        $stmt->execute([
            ':uid' => 'ovf_encrypted_reg',
            ':prop' => '1606',
            ':in' => '2026-12-01',
            ':out' => '2026-12-05',
            ':cnt' => 1,
            ':payload' => $encryptedPayload,
        ]);

        // Verify raw SQL stores ciphertext envelope without plaintext doc_num
        $rawInDb = (string) $pdo->query("SELECT guests_payload FROM guest_registries WHERE reservation_uid = 'ovf_encrypted_reg'")->fetchColumn();
        $this->assertStringContainsString('"_encrypted":true', $rawInDb);
        $this->assertStringNotContainsString('US99887766', $rawInDb);

        // Verify findGuestRegistry transparently decrypts
        $found = $adapter->findGuestRegistry('ovf_encrypted_reg');
        $this->assertNotNull($found);
        $this->assertIsArray($found['guests_payload']);
        $this->assertCount(1, $found['guests_payload']);
        $this->assertSame('Elena Gomez', $found['guests_payload'][0]['name']);
        $this->assertSame('US99887766', $found['guests_payload'][0]['doc_num']);

        // Verify InMemoryReservationSearchAdapter also decrypts encrypted envelopes
        $inMem = new InMemoryReservationSearchAdapter(
            guestRegistries: [
                'ovf_encrypted_reg' => [
                    'reservation_uid' => 'ovf_encrypted_reg',
                    'guest_count' => 1,
                    'guests_payload' => $encryptedPayload,
                ],
            ]
        );
        $foundInMem = $inMem->findGuestRegistry('ovf_encrypted_reg');
        $this->assertNotNull($foundInMem);
        $this->assertSame('US99887766', $foundInMem['guests_payload'][0]['doc_num']);
    }

    public function testSearchByExternalConfirmationCode(): void
    {
        $pdo = $this->createSqlitePdo();
        $pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, external_confirmation_code, channel_block_uid
            ) VALUES (
                'res-abnb-test-1', '1606', 'Sofia Vergara', 'sofia@example.com', '+57 300 999 8888',
                '2026-12-01', '2026-12-05', 3000000.0, 'confirmed', 'airbnb', 'HM3XYZ1234', 'ical-vevent-999'
            )
        ");

        $pdoAdapter = new PdoReservationSearchAdapter($pdo);
        $result = $pdoAdapter->search(ReservationSearchCriteria::fromArray(['query' => 'HM3XYZ1234']));
        $this->assertSame(1, $result->totalCount);
        $this->assertSame('res-abnb-test-1', $result->items[0]['reservation_uid']);

        // Case-insensitive search
        $resultLower = $pdoAdapter->search(ReservationSearchCriteria::fromArray(['query' => 'hm3xyz1234']));
        $this->assertSame(1, $resultLower->totalCount);

        // Also test InMemoryReservationSearchAdapter
        $inMemoryAdapter = new InMemoryReservationSearchAdapter([
            [
                'id' => 1,
                'reservation_uid' => 'res-abnb-test-1',
                'property_id' => '1606',
                'guest_name' => 'Sofia Vergara',
                'guest_email' => 'sofia@example.com',
                'guest_phone' => '+57 300 999 8888',
                'check_in' => '2026-12-01',
                'check_out' => '2026-12-05',
                'total_price' => 3000000.0,
                'status' => 'confirmed',
                'source' => 'airbnb',
                'external_confirmation_code' => 'HM3XYZ1234',
                'channel_block_uid' => 'ical-vevent-999'
            ]
        ]);

        $inMemRes = $inMemoryAdapter->search(ReservationSearchCriteria::fromArray(['query' => 'hm3xyz1234']));
        $this->assertSame(1, $inMemRes->totalCount);
        $this->assertSame('res-abnb-test-1', $inMemRes->items[0]['reservation_uid']);
        $this->assertSame(6, $inMemRes->items[0]['max_guests']);
        $this->assertSame(6, $result->items[0]['max_guests']);
    }
}
