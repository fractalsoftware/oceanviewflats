<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Http;

use OceanViewFlats\Admin\AdminApp;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Http\Response;
use OceanViewFlats\Admin\Service\InMemoryMercadoPagoRefundClient;
use OceanViewFlats\Admin\Tests\Support\AdminDatabaseTestHelper;
use OceanViewFlats\Domain\Fulfillment\HttpTransportInterface;
use OceanViewFlats\Domain\Fulfillment\InMemoryEmailSender;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class AdminReservationRoutesTest extends TestCase
{
    private PDO $pdo;
    private AdminApp $app;
    private InMemoryMercadoPagoRefundClient $refundClient;
    private InMemoryEmailSender $emailSender;
    /** @var list<array<string, mixed>> */
    private array $clearanceRequests = [];

    protected function setUp(): void
    {
        $this->pdo = AdminDatabaseTestHelper::createDatabase();
        $this->configureRouter();
    }

    private function configureRouter(): void
    {
        $this->refundClient = new InMemoryMercadoPagoRefundClient();
        $this->emailSender = new InMemoryEmailSender();
        $this->clearanceRequests = [];
        $requestsRef = &$this->clearanceRequests;

        $clearanceTransport = new class($requestsRef) implements HttpTransportInterface {
            /** @var list<array<string, mixed>> */
            public array $requests;

            /**
             * @param list<array<string, mixed>> $requests
             */
            public function __construct(array &$requests)
            {
                $this->requests = &$requests;
            }

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
                        'body' => (string) json_encode(['last_id' => 777, 'unit_apt' => '1606']),
                        'headers' => ['content-type' => 'application/json'],
                        'cookies' => ['PHPSESSID' => 'test-clearance-sess'],
                        'error' => null,
                    ];
                }

                if (str_contains($url, 'reg_hpds_pre.php')) {
                    return [
                        'statusCode' => 302,
                        'body' => '',
                        'headers' => ['location' => 'success.php?hpsucc=succ&book=777'],
                        'cookies' => [],
                        'error' => null,
                    ];
                }

                return [
                    'statusCode' => 404,
                    'body' => '',
                    'headers' => [],
                    'cookies' => [],
                    'error' => 'Not found',
                ];
            }
        };

        $this->app = AdminApp::createDefault($this->pdo, [
            'refund_client' => $this->refundClient,
            'email_sender' => $this->emailSender,
            'condominium_clearance_transport' => $clearanceTransport,
            'public_site_url' => 'https://oceanviewflats.com',
        ]);
    }

    private function dispatchAdmin(Request $request): Response
    {
        $session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Operator Manuel',
            'admin_user_email' => 'operator@test.com',
            'admin_user_role' => 'admin',
            'csrf_token' => 'test-csrf-token',
        ];
        return $this->app->handle($request, $session);
    }


    public function testReservationsRendersForAuthenticatedUser(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status)
            VALUES ('res-int-1', '1606', 'Maria Test', 'maria@test.com', '+573001234567', '2026-11-01', '2026-11-05', 1000000.00, 'confirmed');
        ");

        $response = $this->dispatchAdmin(new Request('GET', '/reservations'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Reservations Management', $response->getBody());
        $this->assertStringContainsString('Maria Test', $response->getBody());
    }

    public function testReservationShowRouteResolvesUidAttribute(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status)
            VALUES ('res-int-2', '1707', 'Pedro Test', 'pedro@test.com', '+573007654321', '2026-11-10', '2026-11-15', 1500000.00, 'confirmed');
        ");

        $response = $this->dispatchAdmin(new Request(
            'GET',
            '/reservations/res-int-2',
            server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_HX_TARGET' => 'drawer-container']
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('<!DOCTYPE html>', $response->getBody());
        $this->assertStringContainsString('Pedro Test', $response->getBody());
        $this->assertStringContainsString('res-int-2', $response->getBody());
    }

    public function testReservationShowRouteWithoutDrawerTargetRendersFullDashboard(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status)
            VALUES ('res-int-full', '1707', 'Full Dashboard Guest', 'fulldash@test.com', '+573007654322', '2026-11-10', '2026-11-15', 1500000.00, 'confirmed');
        ");

        $response = $this->dispatchAdmin(new Request(
            'GET',
            '/reservations/res-int-full',
            server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_HX_TARGET' => 'body']
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('<!DOCTYPE html>', $response->getBody());
        $this->assertStringContainsString('Reservations Management', $response->getBody());
        $this->assertStringContainsString('Full Dashboard Guest', $response->getBody());
    }

    public function testReservationRegistryRouteResolvesUidAttribute(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, registry_completed)
            VALUES ('res-int-3', '1606', 'Sofia Test', 'sofia@test.com', '+573009998888', '2026-12-01', '2026-12-05', 2000000.00, 'confirmed', 1);

            INSERT INTO guest_registries (reservation_uid, property_id, check_in, check_out, guest_count, guests_payload)
            VALUES ('res-int-3', '1606', '2026-12-01', '2026-12-05', 1, '[{\"full_name\":\"Sofia Test\",\"doc_type\":\"CC\",\"doc_number\":\"99999999\"}]');
        ");

        $response = $this->dispatchAdmin(new Request('GET', '/reservations/res-int-3/registry', server: ['HTTP_HX_REQUEST' => 'true']));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Guest Registry Dossier', $response->getBody());
        $this->assertStringContainsString('Sofia Test', $response->getBody());
    }

    public function testNewReservationRouteResolvesAndRendersModal(): void
    {
        $response = $this->dispatchAdmin(new Request('GET', '/reservations/new', server: ['HTTP_HX_REQUEST' => 'true']));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Create Manual Reservation', $response->getBody());
    }

    public function testQuotePreviewRouteComputesLivePricing(): void
    {
        $response = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/quote-preview',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-11-20',
                'check_out' => '2026-11-23',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Available (3 Nights)', $response->getBody());
    }

    public function testCreateManualReservationRouteStoresBookingAndEmitsOobDrawerAndPushUrl(): void
    {
        $response = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/create-manual',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-12-10',
                'check_out' => '2026-12-15',
                'source' => 'cash',
                'total_price' => '1500000',
                'guest_name' => 'Route Tester',
                'guest_email' => 'route@test.com',
                'guest_phone' => '+573001112233',
                'notes' => 'Route integration test',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));

        $this->assertSame(200, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertSame('reservationUpdated', $headers['HX-Trigger'] ?? null);
        $this->assertMatchesRegularExpression('#^/reservations/res-man-[a-f0-9]+$#', (string) ($headers['HX-Push-Url'] ?? ''));
        $this->assertStringContainsString('modal-container', $response->getBody());
        $this->assertStringContainsString('drawer-container', $response->getBody());
        $this->assertStringContainsString('Route Tester', $response->getBody());

        $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE guest_email = :email');
        $stmt->execute(['email' => 'route@test.com']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertSame('Route Tester', $row['guest_name']);
    }

    public function testOverrideDoorCodeRouteUpdatesDoorCode(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, door_code)
            VALUES ('res-int-override', '1606', 'Code Tester', 'code@test.com', '+573001112233', '2026-11-20', '2026-11-25', 1000000.00, 'confirmed', '1111#');
        ");

        $response = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-int-override/door-code/override',
            post: [
                'door_code' => '998877#',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('reservationUpdated', $response->getHeaders()['HX-Trigger'] ?? null);

        $stmt = $this->pdo->query("SELECT door_code FROM reservations WHERE reservation_uid = 'res-int-override'");
        $this->assertInstanceOf(PDOStatement::class, $stmt);
        $this->assertSame('998877#', $stmt->fetchColumn());
    }

    public function testRegenerateDoorCodeRouteUpdatesDoorCode(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, door_code)
            VALUES ('res-int-regen', '1707', 'Regen Tester', 'regen@test.com', '+573001112233', '2026-11-25', '2026-11-30', 1200000.00, 'confirmed', '2222#');
        ");

        $response = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-int-regen/door-code/regenerate',
            post: [
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('reservationUpdated', $response->getHeaders()['HX-Trigger'] ?? null);

        $stmt = $this->pdo->query("SELECT door_code FROM reservations WHERE reservation_uid = 'res-int-regen'");
        $this->assertInstanceOf(PDOStatement::class, $stmt);
        $doorCode = (string) $stmt->fetchColumn();
        $this->assertNotSame('2222#', $doorCode);
        $this->assertMatchesRegularExpression('/^0[0-9]{6}#$/', $doorCode);
    }

    public function testCompleteRegistryRouteMarksCompletedAndEmitsHxTrigger(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, registry_completed, door_code)
            VALUES ('res-int-comp', '1606', 'Comp Tester', 'comp@test.com', '+573001112233', '2026-12-05', '2026-12-10', 1000000.00, 'confirmed', 0, NULL);
        ");

        $response = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-int-comp/registry/complete',
            post: [
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('reservationUpdated', $response->getHeaders()['HX-Trigger'] ?? null);

        $stmt = $this->pdo->query("SELECT registry_completed, door_code FROM reservations WHERE reservation_uid = 'res-int-comp'");
        $this->assertInstanceOf(PDOStatement::class, $stmt);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $this->assertSame(1, (int) $row['registry_completed']);
        $this->assertMatchesRegularExpression('/^0[0-9]{6}#$/', (string) $row['door_code']);
    }

    public function testCancelModalRouteReturnsModalContent(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status)
            VALUES ('res-int-cancel-modal', '1606', 'Modal Tester', 'modal@test.com', '+573001112233', '2026-12-15', '2026-12-20', 800000.00, 'confirmed');
        ");

        $response = $this->dispatchAdmin(new Request(
            'GET',
            '/reservations/res-int-cancel-modal/cancel-modal',
            server: [
                'HTTP_HX_REQUEST' => 'true',
            ]
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Cancel Reservation', $response->getBody());
        $this->assertStringContainsString('res-int-cancel-modal', $response->getBody());
        $this->assertStringContainsString('800,000', $response->getBody());
    }

    public function testCancelRouteExecutesCancellationAndSwapsContainers(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, source)
            VALUES ('res-int-cancel-exec', '1707', 'Exec Tester', 'exec@test.com', '+573001112233', '2026-12-22', '2026-12-27', 950000.00, 'confirmed', 'phone');
        ");

        $response = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-int-cancel-exec/cancel',
            post: [
                'csrf_token' => 'test-csrf-token',
                'reason' => 'Guest family emergency',
                'refund_type' => 'none',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('reservationUpdated', $response->getHeaders()['HX-Trigger'] ?? null);
        $this->assertStringContainsString('modal-container', $response->getBody());
        $this->assertStringContainsString('drawer-container', $response->getBody());

        $stmt = $this->pdo->query("SELECT status, notes FROM reservations WHERE reservation_uid = 'res-int-cancel-exec'");
        $this->assertInstanceOf(PDOStatement::class, $stmt);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $this->assertSame('cancelled', $row['status']);
        $this->assertStringContainsString('Guest family emergency', (string) $row['notes']);
    }

    public function testCreateManualAndExternalReservationsPersistMaxGuestsAndValidateBounds(): void
    {
        // 1. Modal renders max_guests input with default 6, min 1, max 12
        $modalRes = $this->dispatchAdmin(new Request('GET', '/reservations/new', server: ['HTTP_HX_REQUEST' => 'true']));
        $this->assertSame(200, $modalRes->getStatusCode());
        $this->assertStringContainsString('name="max_guests"', $modalRes->getBody());
        $this->assertStringContainsString('min="1"', $modalRes->getBody());
        $this->assertStringContainsString('max="12"', $modalRes->getBody());
        $this->assertStringContainsString('value="6"', $modalRes->getBody());

        // 2. Create manual reservation with max_guests = 10
        $createManual = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/create-manual',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-12-16',
                'check_out' => '2026-12-20',
                'source' => 'bank_transfer',
                'total_price' => '1800000',
                'max_guests' => '10',
                'guest_name' => 'Large Group Host',
                'guest_email' => 'largegroup@test.com',
                'guest_phone' => '+573001112233',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(200, $createManual->getStatusCode());
        $stmtManual = $this->pdo->prepare('SELECT max_guests FROM reservations WHERE guest_email = :email');
        $stmtManual->execute(['email' => 'largegroup@test.com']);
        $this->assertSame(10, (int) $stmtManual->fetchColumn());

        // 3. Create Airbnb external reservation with max_guests = 8
        $createAirbnb = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/create-manual',
            post: [
                'property_id' => '1707',
                'check_in' => '2026-12-16',
                'check_out' => '2026-12-20',
                'source' => 'airbnb',
                'external_confirmation_code' => 'HM8GUESTS',
                'total_price' => '0',
                'max_guests' => '8',
                'guest_name' => 'Airbnb Group Host',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(200, $createAirbnb->getStatusCode());
        $stmtAirbnb = $this->pdo->prepare('SELECT max_guests FROM reservations WHERE external_confirmation_code = :code');
        $stmtAirbnb->execute(['code' => 'HM8GUESTS']);
        $this->assertSame(8, (int) $stmtAirbnb->fetchColumn());

        // 4. Reject invalid max_guests (> 12) with 422 and preserve form state
        $invalidRes = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/create-manual',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-12-21',
                'check_out' => '2026-12-25',
                'source' => 'cash',
                'total_price' => '1500000',
                'max_guests' => '15',
                'guest_name' => 'Too Many Guests',
                'guest_email' => 'toomany@test.com',
                'guest_phone' => '+573001112233',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(422, $invalidRes->getStatusCode());
        $this->assertStringContainsString('Maximum occupancy (max_guests) must be between 1 and 12.', $invalidRes->getBody());
        $this->assertStringContainsString('value="15"', $invalidRes->getBody());
        $this->assertStringContainsString('value="Too Many Guests"', $invalidRes->getBody());
    }

    public function testReservationEditorActionVisibilityAndLifecycleGuards(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (reservation_uid, property_id, guest_name, guest_email, guest_phone, check_in, check_out, total_price, status, source)
            VALUES
                ('res-edit-active', '1606', 'Active Confirmed', 'active@test.com', '+573001112233', '2026-12-01', '2026-12-05', 1400000.00, 'confirmed', 'web'),
                ('res-edit-pending', '1606', 'Pending Guest', 'pending@test.com', '+573001112233', '2026-12-10', '2026-12-14', 1100000.00, 'pending_payment', 'web'),
                ('res-edit-cancelled', '1606', 'Cancelled Guest', 'cancelled@test.com', '+573001112233', '2026-12-15', '2026-12-18', 900000.00, 'cancelled', 'web'),
                ('res-edit-concluded', '1606', 'Concluded Guest', 'concluded@test.com', '+573001112233', '2024-01-10', '2024-01-15', 1000000.00, 'confirmed', 'web');
        ");

        // 1. Reservations table shows Edit Reservation only for active confirmed reservations
        $tableRes = $this->dispatchAdmin(new Request('GET', '/reservations', server: ['HTTP_HX_REQUEST' => 'true']));
        $this->assertSame(200, $tableRes->getStatusCode());
        $tableHtml = $tableRes->getBody();
        $this->assertStringContainsString('hx-get="/reservations/res-edit-active/edit"', $tableHtml);
        $this->assertStringNotContainsString('hx-get="/reservations/res-edit-pending/edit"', $tableHtml);
        $this->assertStringNotContainsString('hx-get="/reservations/res-edit-cancelled/edit"', $tableHtml);
        $this->assertStringNotContainsString('hx-get="/reservations/res-edit-concluded/edit"', $tableHtml);

        // 2. Detail drawer shows Edit Reservation only for active confirmed reservations
        $drawerActive = $this->dispatchAdmin(new Request(
            'GET',
            '/reservations/res-edit-active',
            server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_HX_TARGET' => 'drawer-container']
        ));
        $this->assertStringContainsString('hx-get="/reservations/res-edit-active/edit"', $drawerActive->getBody());
        $this->assertStringContainsString('Edit Reservation', $drawerActive->getBody());

        $drawerPending = $this->dispatchAdmin(new Request(
            'GET',
            '/reservations/res-edit-pending',
            server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_HX_TARGET' => 'drawer-container']
        ));
        $this->assertStringNotContainsString('hx-get="/reservations/res-edit-pending/edit"', $drawerPending->getBody());

        $drawerCancelled = $this->dispatchAdmin(new Request(
            'GET',
            '/reservations/res-edit-cancelled',
            server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_HX_TARGET' => 'drawer-container']
        ));
        $this->assertStringNotContainsString('hx-get="/reservations/res-edit-cancelled/edit"', $drawerCancelled->getBody());

        $drawerConcluded = $this->dispatchAdmin(new Request(
            'GET',
            '/reservations/res-edit-concluded',
            server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_HX_TARGET' => 'drawer-container']
        ));
        $this->assertStringNotContainsString('hx-get="/reservations/res-edit-concluded/edit"', $drawerConcluded->getBody());

        // 3. GET /reservations/{uid}/edit and POST /reservations/{uid}/edit reject pending, cancelled, and concluded with 422
        foreach (['res-edit-pending', 'res-edit-cancelled', 'res-edit-concluded'] as $nonEditableUid) {
            $getRes = $this->dispatchAdmin(new Request(
                'GET',
                "/reservations/{$nonEditableUid}/edit",
                server: ['HTTP_HX_REQUEST' => 'true']
            ));
            $this->assertSame(422, $getRes->getStatusCode(), "GET edit should reject {$nonEditableUid} with 422");

            $postRes = $this->dispatchAdmin(new Request(
                'POST',
                "/reservations/{$nonEditableUid}/edit",
                post: [
                    'guest_name' => 'Attempted Mutation',
                    'csrf_token' => 'test-csrf-token',
                ],
                server: [
                    'HTTP_HX_REQUEST' => 'true',
                    'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
                ]
            ));
            $this->assertSame(422, $postRes->getStatusCode(), "POST edit should reject {$nonEditableUid} with 422");
        }

        // 4. GET /reservations/res-edit-active/edit succeeds with 200 and renders Reservation Editor modal + Apply Calculated Quote
        $editModalRes = $this->dispatchAdmin(new Request(
            'GET',
            '/reservations/res-edit-active/edit',
            server: ['HTTP_HX_REQUEST' => 'true']
        ));
        $this->assertSame(200, $editModalRes->getStatusCode());
        $modalBody = $editModalRes->getBody();
        $this->assertStringContainsString('Reservation Editor', $modalBody);
        $this->assertStringContainsString('value="Active Confirmed"', $modalBody);
        $this->assertStringContainsString('Apply Calculated Quote', $modalBody);
    }

    public function testEditReservationUpdatesContractualFieldsMaxGuestsAndRecordsAuditLog(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, lang,
                external_confirmation_code, notes, max_guests
            ) VALUES (
                'res-edit-contract', '1606', 'Original Name', 'orig@test.com', '+573001112233',
                '2026-12-01', '2026-12-05', 1400000.00, 'confirmed', 'bank_transfer', 'en',
                'ORIG-REF-1', 'Initial note', 6
            );
        ");

        $response = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-edit-contract/edit',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-12-01',
                'check_out' => '2026-12-05',
                'total_price' => '1650000',
                'max_guests' => '9',
                'guest_name' => 'Updated Primary Guest',
                'guest_email' => 'updated@test.com',
                'guest_phone' => '+573109998877',
                'lang' => 'es',
                'external_confirmation_code' => 'NEW-REF-99',
                'notes' => 'Updated operational notes',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
                'REMOTE_ADDR' => '10.20.30.40',
            ]
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('reservationUpdated', $response->getHeader('HX-Trigger'));
        $this->assertStringContainsString('drawer-container', $response->getBody());
        $this->assertStringContainsString('Updated Primary Guest', $response->getBody());

        $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE reservation_uid = :uid');
        $stmt->execute(['uid' => 'res-edit-contract']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $this->assertSame('Updated Primary Guest', $row['guest_name']);
        $this->assertSame('updated@test.com', $row['guest_email']);
        $this->assertSame('+573109998877', $row['guest_phone']);
        $this->assertSame('es', $row['lang']);
        $this->assertSame('NEW-REF-99', $row['external_confirmation_code']);
        $this->assertSame('Updated operational notes', $row['notes']);
        $this->assertEquals(1650000.0, (float) $row['total_price']);
        $this->assertSame(9, (int) $row['max_guests']);

        // Verify Audit Log entry (`reservation_updated`)
        $auditStmt = $this->pdo->prepare('SELECT * FROM admin_audit_logs WHERE action = :action AND entity_id = :uid ORDER BY id DESC LIMIT 1');
        $auditStmt->execute(['action' => 'reservation_updated', 'uid' => 'res-edit-contract']);
        $auditRow = $auditStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($auditRow);
        $this->assertSame(1, (int) $auditRow['admin_user_id']);
        $this->assertSame('10.20.30.40', $auditRow['ip_address']);

        $before = json_decode((string) $auditRow['payload_before'], true);
        $after = json_decode((string) $auditRow['payload_after'], true);
        $this->assertSame('Original Name', $before['guest_name']);
        $this->assertSame(6, $before['max_guests']);
        $this->assertSame('Updated Primary Guest', $after['guest_name']);
        $this->assertSame(9, $after['max_guests']);
        $this->assertEquals(1650000.0, (float) $after['total_price']);
    }

    public function testEditReservationRejectsLoweringMaxGuestsBelowRegisteredGuestCountAndPreservesInput(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, registry_completed, max_guests
            ) VALUES (
                'res-edit-cap-guard', '1707', 'Group Host', 'group@test.com', '+573001112233',
                '2026-12-01', '2026-12-05', 2200000.00, 'confirmed', 'web', 1, 8
            );

            INSERT INTO guest_registries (
                reservation_uid, property_id, check_in, check_out, guest_count, guests_payload
            ) VALUES (
                'res-edit-cap-guard', '1707', '2026-12-01', '2026-12-05', 5,
                '[{\"name\":\"G1\"},{\"name\":\"G2\"},{\"name\":\"G3\"},{\"name\":\"G4\"},{\"name\":\"G5\"}]'
            );
        ");

        // Attempt to lower max_guests to 4 (below 5 registered guests) while editing notes & name
        $res = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-edit-cap-guard/edit',
            post: [
                'property_id' => '1707',
                'check_in' => '2026-12-01',
                'check_out' => '2026-12-05',
                'total_price' => '2350000',
                'max_guests' => '4',
                'guest_name' => 'Preserved Operator Input Name',
                'guest_email' => 'preserved@test.com',
                'guest_phone' => '+573009991111',
                'lang' => 'en',
                'external_confirmation_code' => 'PRESERVED-CODE',
                'notes' => 'Preserved operator notes on 422',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));

        $this->assertSame(422, $res->getStatusCode());
        $body = $res->getBody();
        $this->assertStringContainsString('Cannot lower maximum occupancy (4) below the 5 currently registered guests', $body);
        // 100% of operator input preserved in-place
        $this->assertStringContainsString('value="Preserved Operator Input Name"', $body);
        $this->assertStringContainsString('value="preserved@test.com"', $body);
        $this->assertStringContainsString('value="+573009991111"', $body);
        $this->assertStringContainsString('value="2350000"', $body);
        $this->assertStringContainsString('value="4"', $body);
        $this->assertStringContainsString('value="PRESERVED-CODE"', $body);
        $this->assertStringContainsString('Preserved operator notes on 422', $body);

        // Setting max_guests to 5 (equal to registered guest count) succeeds
        $resValid = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-edit-cap-guard/edit',
            post: [
                'property_id' => '1707',
                'check_in' => '2026-12-01',
                'check_out' => '2026-12-05',
                'total_price' => '2350000',
                'max_guests' => '5',
                'guest_name' => 'Preserved Operator Input Name',
                'guest_email' => 'preserved@test.com',
                'guest_phone' => '+573009991111',
                'lang' => 'en',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(200, $resValid->getStatusCode());
    }

    public function testEditReservationReschedulesWithSelfConflictExclusionRejectsOtherConflictsAndCascadesToGuestRegistry(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, registry_completed
            ) VALUES
            (
                'res-edit-stay', '1606', 'Stay Mover', 'mover@test.com', '+573001112233',
                '2026-11-10', '2026-11-14', 1250000.00, 'confirmed', 'web', 1
            ),
            (
                'res-other-conflict', '1606', 'Blocking Neighbor', 'neighbor@test.com', '+573004445566',
                '2026-11-16', '2026-11-20', 1500000.00, 'confirmed', 'web', 0
            );

            INSERT INTO calendar_blocks (property_id, start_date, end_date, reason)
            VALUES ('1707', '2026-11-22', '2026-11-25', 'Deep HVAC Maintenance');

            INSERT INTO guest_registries (
                reservation_uid, property_id, check_in, check_out, guest_count, guests_payload
            ) VALUES (
                'res-edit-stay', '1606', '2026-11-10', '2026-11-14', 2,
                '[{\"name\":\"Stay Mover\"},{\"name\":\"Companion\"}]'
            );
        ");

        // 1. Quote preview in edit mode excludes self-conflict and renders Apply Calculated Quote without overwriting total_price
        $previewSelf = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/quote-preview',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-11-10',
                'check_out' => '2026-11-15',
                'source' => 'web',
                'exclude_reservation_uid' => 'res-edit-stay',
                'edit_mode' => '1',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(200, $previewSelf->getStatusCode());
        $this->assertStringContainsString('Available (5 Nights)', $previewSelf->getBody());
        $this->assertStringContainsString('Apply Calculated Quote', $previewSelf->getBody());
        $this->assertStringNotContainsString("document.getElementById('manual-total-price')", $previewSelf->getBody());

        // 2. Extending into res-other-conflict (2026-11-10..2026-11-18) fails with 422
        $conflictRes = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-edit-stay/edit',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-11-10',
                'check_out' => '2026-11-18',
                'total_price' => '1250000',
                'max_guests' => '6',
                'guest_name' => 'Stay Mover',
                'guest_email' => 'mover@test.com',
                'guest_phone' => '+573001112233',
                'lang' => 'es',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(422, $conflictRes->getStatusCode());
        $this->assertStringContainsString('res-other-conflict', $conflictRes->getBody());

        // 3. Relocating to 1707 overlapping maintenance block (2026-11-21..2026-11-24) fails with 422
        $maintConflictRes = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-edit-stay/edit',
            post: [
                'property_id' => '1707',
                'check_in' => '2026-11-21',
                'check_out' => '2026-11-24',
                'total_price' => '1250000',
                'max_guests' => '6',
                'guest_name' => 'Stay Mover',
                'guest_email' => 'mover@test.com',
                'guest_phone' => '+573001112233',
                'lang' => 'es',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(422, $maintConflictRes->getStatusCode());
        $this->assertStringContainsString('Deep HVAC Maintenance', $maintConflictRes->getBody());

        // 4. Extending stay on 1606 by 2 nights (2026-11-10..2026-11-16, adjacent to res-other-conflict) succeeds,
        // preserves custom total_price (1250000), and cascades dates/property to guest_registries!
        $extendSuccess = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-edit-stay/edit',
            post: [
                'property_id' => '1707',
                'check_in' => '2026-11-10',
                'check_out' => '2026-11-16',
                'total_price' => '1250000',
                'max_guests' => '6',
                'guest_name' => 'Stay Mover',
                'guest_email' => 'mover@test.com',
                'guest_phone' => '+573001112233',
                'lang' => 'es',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(200, $extendSuccess->getStatusCode());

        // Verify reservations table updated
        $stmtRes = $this->pdo->prepare('SELECT property_id, check_in, check_out, total_price FROM reservations WHERE reservation_uid = :uid');
        $stmtRes->execute(['uid' => 'res-edit-stay']);
        $updatedRes = $stmtRes->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($updatedRes);
        $this->assertSame('1707', $updatedRes['property_id']);
        $this->assertSame('2026-11-10', $updatedRes['check_in']);
        $this->assertSame('2026-11-16', $updatedRes['check_out']);
        $this->assertEquals(1250000.0, (float) $updatedRes['total_price']);

        // Verify guest_registries cascaded atomically
        $stmtReg = $this->pdo->prepare('SELECT property_id, check_in, check_out FROM guest_registries WHERE reservation_uid = :uid');
        $stmtReg->execute(['uid' => 'res-edit-stay']);
        $updatedReg = $stmtReg->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($updatedReg);
        $this->assertSame('1707', $updatedReg['property_id']);
        $this->assertSame('2026-11-10', $updatedReg['check_in']);
        $this->assertSame('2026-11-16', $updatedReg['check_out']);
    }

    public function testReservationEditorCreatesGuestRegistryFromScratchEnrichesPlaceholderEmailAndGeneratesOrPreservesDoorCode(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, lang,
                external_confirmation_code, registry_completed, door_code, max_guests
            ) VALUES
            (
                'res-abnb-scratch', '1606', 'Camila Vargas', 'camila.temp@airbnb-guest.local', '+573001234567',
                '2026-12-01', '2026-12-05', 1500000.00, 'confirmed', 'airbnb', 'es',
                'HMFIRST123', 0, NULL, 6
            ),
            (
                'res-existing-pin', '1707', 'Diego Herrera', 'diego@test.com', '+573109876543',
                '2026-12-06', '2026-12-10', 1800000.00, 'confirmed', 'bank_transfer', 'es',
                NULL, 0, '0765432#', 6
            );
        ");

        // 1. GET /reservations/res-abnb-scratch/edit renders empty Guest Registry state, vehicle inputs, 1..12 cards, and Guest #1 pre-fill logic
        $modalRes = $this->dispatchAdmin(new Request(
            'GET',
            '/reservations/res-abnb-scratch/edit',
            server: ['HTTP_HX_REQUEST' => 'true']
        ));
        $this->assertSame(200, $modalRes->getStatusCode());
        $modalHtml = $modalRes->getBody();
        $this->assertStringContainsString('name="car_plates"', $modalHtml);
        $this->assertStringContainsString('name="car_model"', $modalHtml);
        $this->assertStringContainsString('id="edit-guest-card-1"', $modalHtml);
        $this->assertStringContainsString('id="edit-guest-card-12"', $modalHtml);
        $this->assertStringContainsString('id="edit-add-guest-btn"', $modalHtml);
        $this->assertStringContainsString('id="edit-clear-registry-btn"', $modalHtml);
        $this->assertStringContainsString('name="sync_condominium_clearance"', $modalHtml);
        $this->assertStringContainsString('name="send_access_dispatch"', $modalHtml);
        // Verify JS pre-fill logic for Guest #1 from Primary Guest fields when starting from empty registry
        $this->assertStringContainsString("var fn1 = document.getElementById('edit-guest-first-name-1')", $modalHtml);
        $this->assertStringContainsString("var em1 = document.getElementById('edit-guest-email-1')", $modalHtml);
        $this->assertStringContainsString("var ph1 = document.getElementById('edit-guest-phone-1')", $modalHtml);

        // 2. POST /reservations/res-abnb-scratch/edit with 2 Guests & vehicle creates guest_registries,
        // marks registry_completed = 1, enriches @airbnb-guest.local email with Guest #1's email, and generates door_code
        $saveRes = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-abnb-scratch/edit',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-12-01',
                'check_out' => '2026-12-05',
                'total_price' => '1500000',
                'max_guests' => '6',
                'guest_name' => 'Camila Vargas',
                'guest_email' => 'camila.temp@airbnb-guest.local',
                'guest_phone' => '+573001234567',
                'lang' => 'es',
                'external_confirmation_code' => 'HMFIRST123',
                'car_plates' => 'KLM-987',
                'car_model' => 'Toyota Corolla Cross',
                'guest_count' => '2',
                'guest_first_name_1' => 'Camila',
                'guest_last_name_1' => 'Vargas',
                'guest_doc_type_1' => 'Cédula de Ciudadanía',
                'guest_doc_num_1' => '1020304050',
                'guest_age_1' => '31',
                'guest_country_1' => 'COLOMBIA',
                'guest_email_1' => 'camila.verified@gmail.com',
                'guest_phone_1' => '+573001234567',
                'guest_first_name_2' => 'Mateo',
                'guest_last_name_2' => 'Vargas',
                'guest_doc_type_2' => 'Passport',
                'guest_doc_num_2' => 'AB987654',
                'guest_age_2' => '29',
                'guest_country_2' => 'ESPANA',
                'guest_phone_2' => '+34600112233',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(200, $saveRes->getStatusCode());

        $stmtRes = $this->pdo->prepare('SELECT * FROM reservations WHERE reservation_uid = :uid');
        $stmtRes->execute(['uid' => 'res-abnb-scratch']);
        $savedRow = $stmtRes->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($savedRow);
        $this->assertSame(1, (int) $savedRow['registry_completed']);
        $this->assertNotEmpty($savedRow['registry_completed_at']);
        $this->assertSame('camila.verified@gmail.com', $savedRow['guest_email']);
        $this->assertMatchesRegularExpression('/^0[0-9]{6}#$/', (string) $savedRow['door_code']);

        $stmtReg = $this->pdo->prepare('SELECT * FROM guest_registries WHERE reservation_uid = :uid');
        $stmtReg->execute(['uid' => 'res-abnb-scratch']);
        $regRow = $stmtReg->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($regRow);
        $this->assertSame(2, (int) $regRow['guest_count']);
        $this->assertSame('KLM-987', $regRow['car_plates']);
        $this->assertSame('Toyota Corolla Cross', $regRow['car_model']);
        $this->assertTrue(\OceanViewFlats\Domain\Security\PayloadEncryptor::isEncryptedEnvelope((string) $regRow['guests_payload']));
        $payload = \OceanViewFlats\Domain\Security\PayloadEncryptor::decrypt((string) $regRow['guests_payload']);
        $this->assertCount(2, $payload);
        $this->assertSame('Camila Vargas', $payload[0]['name']);
        $this->assertSame('camila.verified@gmail.com', $payload[0]['email']);
        $this->assertSame('ESPANA', $payload[1]['country']);
        $this->assertSame(29, $payload[1]['age']);

        // Verify Audit Log before/after diff captures guest_registry and vehicle changes
        $stmtAudit = $this->pdo->prepare('SELECT * FROM admin_audit_logs WHERE action = :action AND entity_id = :uid ORDER BY id DESC LIMIT 1');
        $stmtAudit->execute(['action' => 'reservation_updated', 'uid' => 'res-abnb-scratch']);
        $auditRow = $stmtAudit->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($auditRow);
        $before = json_decode((string) $auditRow['payload_before'], true);
        $after = json_decode((string) $auditRow['payload_after'], true);
        $this->assertNull($before['guest_registry']);
        $this->assertFalse((bool) $before['registry_completed']);
        $this->assertTrue((bool) $after['registry_completed']);
        $this->assertSame('KLM-987', $after['car_plates']);
        $this->assertSame('Toyota Corolla Cross', $after['car_model']);
        $this->assertSame(2, $after['guest_registry']['guest_count']);
        $this->assertSame('KLM-987', $after['guest_registry']['car_plates']);

        // 3. Saving a Guest Registry on res-existing-pin preserves existing door_code ('0765432#')
        $preservePinRes = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-existing-pin/edit',
            post: [
                'property_id' => '1707',
                'check_in' => '2026-12-06',
                'check_out' => '2026-12-10',
                'total_price' => '1800000',
                'max_guests' => '6',
                'guest_name' => 'Diego Herrera',
                'guest_email' => 'diego@test.com',
                'guest_phone' => '+573109876543',
                'lang' => 'es',
                'guest_count' => '1',
                'guest_first_name_1' => 'Diego',
                'guest_last_name_1' => 'Herrera',
                'guest_doc_type_1' => 'Cédula de Ciudadanía',
                'guest_doc_num_1' => '80901020',
                'guest_age_1' => '40',
                'guest_country_1' => 'COLOMBIA',
                'guest_email_1' => 'diego@test.com',
                'guest_phone_1' => '+573109876543',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(200, $preservePinRes->getStatusCode());

        $stmtPin = $this->pdo->prepare('SELECT registry_completed, door_code FROM reservations WHERE reservation_uid = :uid');
        $stmtPin->execute(['uid' => 'res-existing-pin']);
        $pinRow = $stmtPin->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($pinRow);
        $this->assertSame(1, (int) $pinRow['registry_completed']);
        $this->assertSame('0765432#', $pinRow['door_code']);
    }

    public function testReservationEditorAutoBumpsMaxGuestsWhenAddingSevenToTwelveGuestsAndValidatesOccupantRules(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, lang, max_guests
            ) VALUES (
                'res-autobump-8', '1707', 'Familia Grande', 'familia@test.com', '+573001112233',
                '2026-12-10', '2026-12-15', 3200000.00, 'confirmed', 'bank_transfer', 'es', 6
            );
        ");

        // 1. Submitting 8 valid Guests while max_guests input is 6 automatically bumps max_guests to 8
        $post8 = [
            'property_id' => '1707',
            'check_in' => '2026-12-10',
            'check_out' => '2026-12-15',
            'total_price' => '3200000',
            'max_guests' => '6',
            'guest_name' => 'Familia Grande',
            'guest_email' => 'familia@test.com',
            'guest_phone' => '+573001112233',
            'lang' => 'es',
            'guest_count' => '8',
            'csrf_token' => 'test-csrf-token',
        ];
        for ($i = 1; $i <= 8; $i++) {
            $post8["guest_first_name_{$i}"] = "GuestFirst{$i}";
            $post8["guest_last_name_{$i}"] = "GuestLast{$i}";
            $post8["guest_doc_type_{$i}"] = 'Passport';
            $post8["guest_doc_num_{$i}"] = "PA10000{$i}";
            $post8["guest_age_{$i}"] = (string) (20 + $i);
            $post8["guest_country_{$i}"] = 'COLOMBIA';
            if ($i === 1) {
                $post8['guest_email_1'] = 'familia@test.com';
                $post8['guest_phone_1'] = '+573001112233';
            }
        }

        $resBump = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-autobump-8/edit',
            post: $post8,
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(200, $resBump->getStatusCode());

        $stmtRes = $this->pdo->prepare('SELECT max_guests, registry_completed FROM reservations WHERE reservation_uid = :uid');
        $stmtRes->execute(['uid' => 'res-autobump-8']);
        $resRow = $stmtRes->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($resRow);
        $this->assertSame(8, (int) $resRow['max_guests']);
        $this->assertSame(1, (int) $resRow['registry_completed']);

        $stmtReg = $this->pdo->prepare('SELECT guest_count, guests_payload FROM guest_registries WHERE reservation_uid = :uid');
        $stmtReg->execute(['uid' => 'res-autobump-8']);
        $regRow = $stmtReg->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($regRow);
        $this->assertSame(8, (int) $regRow['guest_count']);
        $guestsDecoded = \OceanViewFlats\Domain\Security\PayloadEncryptor::decrypt((string) $regRow['guests_payload']);
        $this->assertCount(8, $guestsDecoded);

        // 2. Submitting an invalid Guest row (e.g. invalid doc_num < 2 chars or invalid age > 120) returns 422 and preserves input
        $invalidPost = $post8;
        $invalidPost['guest_doc_num_3'] = 'X'; // Too short (< 2 chars)
        $invalidPost['car_plates'] = 'ERR-422';
        $resInvalid = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-autobump-8/edit',
            post: $invalidPost,
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(422, $resInvalid->getStatusCode());
        $this->assertStringContainsString('Guest #3: Document number must be between 2 and 50 characters.', $resInvalid->getBody());
        $this->assertStringContainsString('value="ERR-422"', $resInvalid->getBody());
        $this->assertStringContainsString('value="GuestFirst8"', $resInvalid->getBody());
    }

    public function testReservationEditorRemovesIndividualGuestsWithReindexingAndClearsAllGuestsToResetPending(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, lang,
                registry_completed, registry_completed_at, door_code, max_guests
            ) VALUES (
                'res-reindex-clear', '1606', 'Ana Perez', 'ana@test.com', '+573001112233',
                '2026-12-01', '2026-12-05', 1400000.00, 'confirmed', 'web', 'es',
                1, '2026-10-10 12:00:00', '0112233#', 6
            );

            INSERT INTO guest_registries (
                reservation_uid, property_id, check_in, check_out, guest_count, guests_payload, car_plates, car_model
            ) VALUES (
                'res-reindex-clear', '1606', '2026-12-01', '2026-12-05', 3,
                '[{\"index\":1,\"name\":\"Ana Perez\",\"first_name\":\"Ana\",\"last_name\":\"Perez\",\"doc_type\":\"Passport\",\"doc_num\":\"PA1111\",\"age\":35,\"country\":\"COLOMBIA\",\"email\":\"ana@test.com\",\"phone\":\"+573001112233\"},{\"index\":2,\"name\":\"Bruno Diaz\",\"first_name\":\"Bruno\",\"last_name\":\"Diaz\",\"doc_type\":\"Passport\",\"doc_num\":\"PA2222\",\"age\":32,\"country\":\"MEXICO\",\"email\":null,\"phone\":\"+525512345678\"},{\"index\":3,\"name\":\"Carla Soto\",\"first_name\":\"Carla\",\"last_name\":\"Soto\",\"doc_type\":\"Cédula de Ciudadanía\",\"doc_num\":\"CC3333\",\"age\":28,\"country\":\"CHILE\",\"email\":null,\"phone\":\"+56987654321\"}]',
                'OLD-111', 'Renault Duster'
            );
        ");

        // 1. Remove Guest #2 (Bruno) so remaining Guests (Ana and Carla) re-index to 1..2
        $removeMiddleRes = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-reindex-clear/edit',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-12-01',
                'check_out' => '2026-12-05',
                'total_price' => '1400000',
                'max_guests' => '6',
                'guest_name' => 'Ana Perez',
                'guest_email' => 'ana@test.com',
                'guest_phone' => '+573001112233',
                'lang' => 'es',
                'car_plates' => 'NEW-222',
                'car_model' => 'Mazda CX-5',
                'guest_count' => '2',
                'guest_first_name_1' => 'Ana',
                'guest_last_name_1' => 'Perez',
                'guest_doc_type_1' => 'Passport',
                'guest_doc_num_1' => 'PA1111',
                'guest_age_1' => '35',
                'guest_country_1' => 'COLOMBIA',
                'guest_email_1' => 'ana@test.com',
                'guest_phone_1' => '+573001112233',
                // Slot 2 removed; Carla is now Guest #2
                'guest_first_name_2' => 'Carla',
                'guest_last_name_2' => 'Soto',
                'guest_doc_type_2' => 'Cédula de Ciudadanía',
                'guest_doc_num_2' => 'CC3333',
                'guest_age_2' => '28',
                'guest_country_2' => 'CHILE',
                'guest_phone_2' => '+56987654321',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(200, $removeMiddleRes->getStatusCode());

        $stmtReg = $this->pdo->prepare('SELECT * FROM guest_registries WHERE reservation_uid = :uid');
        $stmtReg->execute(['uid' => 'res-reindex-clear']);
        $regRow = $stmtReg->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($regRow);
        $this->assertSame(2, (int) $regRow['guest_count']);
        $this->assertSame('NEW-222', $regRow['car_plates']);
        $this->assertSame('Mazda CX-5', $regRow['car_model']);
        $payload = \OceanViewFlats\Domain\Security\PayloadEncryptor::decrypt((string) $regRow['guests_payload']);
        $this->assertCount(2, $payload);
        $this->assertSame(1, $payload[0]['index']);
        $this->assertSame('Ana Perez', $payload[0]['name']);
        $this->assertSame(2, $payload[1]['index']);
        $this->assertSame('Carla Soto', $payload[1]['name']);

        // 2. Clear / Reset Guest Registry deletes guest_registries row and resets registry_completed = 0 and registry_completed_at = NULL
        $clearRes = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-reindex-clear/edit',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-12-01',
                'check_out' => '2026-12-05',
                'total_price' => '1400000',
                'max_guests' => '6',
                'guest_name' => 'Ana Perez',
                'guest_email' => 'ana@test.com',
                'guest_phone' => '+573001112233',
                'lang' => 'es',
                'reset_guest_registry' => '1',
                'guest_count' => '0',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(200, $clearRes->getStatusCode());

        $stmtResAfterClear = $this->pdo->prepare('SELECT registry_completed, registry_completed_at, door_code FROM reservations WHERE reservation_uid = :uid');
        $stmtResAfterClear->execute(['uid' => 'res-reindex-clear']);
        $resAfterClear = $stmtResAfterClear->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($resAfterClear);
        $this->assertSame(0, (int) $resAfterClear['registry_completed']);
        $this->assertNull($resAfterClear['registry_completed_at']);
        $this->assertSame('0112233#', $resAfterClear['door_code']);

        $stmtRegAfterClear = $this->pdo->prepare('SELECT COUNT(*) FROM guest_registries WHERE reservation_uid = :uid');
        $stmtRegAfterClear->execute(['uid' => 'res-reindex-clear']);
        $this->assertSame(0, (int) $stmtRegAfterClear->fetchColumn());
    }

    public function testReservationEditorOptInCondominiumClearanceSyncModifiedIndicatorAndAccessDispatchEmail(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, lang,
                registry_completed, door_code, max_guests
            ) VALUES (
                'res-optin-fulfill', '1606', 'Lucia Mendez', 'lucia@test.com', '+573005556677',
                '2026-12-10', '2026-12-14', 1600000.00, 'confirmed', 'web', 'es',
                0, NULL, 6
            );
        ");

        // 1. Save with 1 Guest WITHOUT checking opt-in checkboxes -> neither Clearance Sync nor Access Dispatch email runs
        $resNoOptIn = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-optin-fulfill/edit',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-12-10',
                'check_out' => '2026-12-14',
                'total_price' => '1600000',
                'max_guests' => '6',
                'guest_name' => 'Lucia Mendez',
                'guest_email' => 'lucia@test.com',
                'guest_phone' => '+573005556677',
                'lang' => 'es',
                'car_plates' => 'LUC-101',
                'car_model' => 'Kia Sportage',
                'guest_count' => '1',
                'guest_first_name_1' => 'Lucia',
                'guest_last_name_1' => 'Mendez',
                'guest_doc_type_1' => 'Cédula de Ciudadanía',
                'guest_doc_num_1' => '52111222',
                'guest_age_1' => '34',
                'guest_country_1' => 'COLOMBIA',
                'guest_email_1' => 'lucia@test.com',
                'guest_phone_1' => '+573005556677',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(200, $resNoOptIn->getStatusCode());
        $this->assertCount(0, $this->clearanceRequests);
        $this->assertCount(0, $this->emailSender->getSentMessages());

        // 2. Save WITH sync_condominium_clearance = 1 and send_access_dispatch = 1 -> executes both!
        $resWithOptIn = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-optin-fulfill/edit',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-12-10',
                'check_out' => '2026-12-14',
                'total_price' => '1600000',
                'max_guests' => '6',
                'guest_name' => 'Lucia Mendez',
                'guest_email' => 'lucia@test.com',
                'guest_phone' => '+573005556677',
                'lang' => 'es',
                'car_plates' => 'LUC-101',
                'car_model' => 'Kia Sportage',
                'guest_count' => '1',
                'guest_first_name_1' => 'Lucia',
                'guest_last_name_1' => 'Mendez',
                'guest_doc_type_1' => 'Cédula de Ciudadanía',
                'guest_doc_num_1' => '52111222',
                'guest_age_1' => '34',
                'guest_country_1' => 'COLOMBIA',
                'guest_email_1' => 'lucia@test.com',
                'guest_phone_1' => '+573005556677',
                'sync_condominium_clearance' => '1',
                'send_access_dispatch' => '1',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(200, $resWithOptIn->getStatusCode());
        $this->assertCount(2, $this->clearanceRequests); // Step 1 + Step 2
        $this->assertStringContainsString('#777', $resWithOptIn->getBody());

        $sentEmails = $this->emailSender->getSentMessages();
        $this->assertCount(1, $sentEmails);
        $this->assertSame('lucia@test.com', $sentEmails[0]['to']);
        $this->assertStringContainsString('/guide/es.html?code=res-optin-fulfill', $sentEmails[0]['htmlBody']);
        $this->assertStringContainsString('#87', $sentEmails[0]['htmlBody']); // Assigned Parking Space for 1606 when vehicle registered

        // 3. Immediately after sync, GET /reservations/res-optin-fulfill/edit has #clearance-modified-indicator hidden
        $editModalAfterSync = $this->dispatchAdmin(new Request(
            'GET',
            '/reservations/res-optin-fulfill/edit',
            server: ['HTTP_HX_REQUEST' => 'true']
        ));
        $this->assertMatchesRegularExpression('/id="clearance-modified-indicator"\s+class="hidden\b/', $editModalAfterSync->getBody());

        // 4. Modify Guest Registry (add 2nd Guest) without checking sync_condominium_clearance -> next GET /edit shows visual indicator unhidden
        $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-optin-fulfill/edit',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-12-10',
                'check_out' => '2026-12-14',
                'total_price' => '1600000',
                'max_guests' => '6',
                'guest_name' => 'Lucia Mendez',
                'guest_email' => 'lucia@test.com',
                'guest_phone' => '+573005556677',
                'lang' => 'es',
                'car_plates' => 'LUC-202',
                'car_model' => 'Kia Sportage',
                'guest_count' => '2',
                'guest_first_name_1' => 'Lucia',
                'guest_last_name_1' => 'Mendez',
                'guest_doc_type_1' => 'Cédula de Ciudadanía',
                'guest_doc_num_1' => '52111222',
                'guest_age_1' => '34',
                'guest_country_1' => 'COLOMBIA',
                'guest_email_1' => 'lucia@test.com',
                'guest_phone_1' => '+573005556677',
                'guest_first_name_2' => 'Tomas',
                'guest_last_name_2' => 'Mendez',
                'guest_doc_type_2' => 'Passport',
                'guest_doc_num_2' => 'PA998877',
                'guest_age_2' => '8',
                'guest_country_2' => 'COLOMBIA',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));

        $editModalAfterModify = $this->dispatchAdmin(new Request(
            'GET',
            '/reservations/res-optin-fulfill/edit',
            server: ['HTTP_HX_REQUEST' => 'true']
        ));
        $this->assertDoesNotMatchRegularExpression('/id="clearance-modified-indicator"\s+class="hidden\b/', $editModalAfterModify->getBody());
        $this->assertStringContainsString('Guest Registry modified after previous Condominium Clearance Sync (#777)', $editModalAfterModify->getBody());
    }

    public function testReadOnlyRegistryDossierDisplaysCountryAndAgeAndIncludesEditReservationAndGuestsButton(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, lang, registry_completed
            ) VALUES
            (
                'res-dossier-completed', '1606', 'Elena Gomez', 'elena@test.com', '+573001112233',
                '2026-12-01', '2026-12-05', 1400000.00, 'confirmed', 'web', 'es', 1
            ),
            (
                'res-dossier-pending', '1707', 'Marco Polo', 'marco@test.com', '+573004445566',
                '2026-12-10', '2026-12-15', 1800000.00, 'confirmed', 'web', 'en', 0
            );

            INSERT INTO guest_registries (
                reservation_uid, property_id, check_in, check_out, guest_count, guests_payload, car_plates, car_model
            ) VALUES (
                'res-dossier-completed', '1606', '2026-12-01', '2026-12-05', 2,
                '[{\"index\":1,\"name\":\"Elena Gomez\",\"first_name\":\"Elena\",\"last_name\":\"Gomez\",\"doc_type\":\"Cédula de Ciudadanía\",\"doc_num\":\"CC102030\",\"age\":37,\"country\":\"COLOMBIA\",\"email\":\"elena@test.com\",\"phone\":\"+573001112233\"},{\"index\":2,\"name\":\"Pierre Laurent\",\"first_name\":\"Pierre\",\"last_name\":\"Laurent\",\"doc_type\":\"Passport\",\"doc_num\":\"FR998877\",\"age\":41,\"country\":\"FRANCIA\",\"email\":null,\"phone\":null}]',
                'BCD-456', 'Chevrolet Tracker'
            );
        ");

        // 1. Completed dossier modal displays country and age for each Guest and includes "Edit Reservation & Guests" button
        $completedDossier = $this->dispatchAdmin(new Request(
            'GET',
            '/reservations/res-dossier-completed/registry',
            server: ['HTTP_HX_REQUEST' => 'true']
        ));
        $this->assertSame(200, $completedDossier->getStatusCode());
        $completedHtml = $completedDossier->getBody();
        $this->assertStringContainsString('Elena Gomez', $completedHtml);
        $this->assertStringContainsString('COLOMBIA', $completedHtml);
        $this->assertStringContainsString('37', $completedHtml);
        $this->assertStringContainsString('Pierre Laurent', $completedHtml);
        $this->assertStringContainsString('FRANCIA', $completedHtml);
        $this->assertStringContainsString('41', $completedHtml);
        $this->assertStringContainsString('Edit Reservation &amp; Guests', $completedHtml);
        $this->assertStringContainsString('hx-get="/reservations/res-dossier-completed/edit"', $completedHtml);
        $this->assertStringNotContainsString('/registry/complete', $completedHtml);

        // 2. Pending dossier modal includes "Edit Reservation & Guests" button and no legacy occupant-less complete button
        $pendingDossier = $this->dispatchAdmin(new Request(
            'GET',
            '/reservations/res-dossier-pending/registry',
            server: ['HTTP_HX_REQUEST' => 'true']
        ));
        $this->assertSame(200, $pendingDossier->getStatusCode());
        $pendingHtml = $pendingDossier->getBody();
        $this->assertStringContainsString('Edit Reservation &amp; Guests', $pendingHtml);
        $this->assertStringContainsString('hx-get="/reservations/res-dossier-pending/edit"', $pendingHtml);
        $this->assertStringNotContainsString('/registry/complete', $pendingHtml);

        // 3. Detail drawer for pending reservation also launches Reservation Editor instead of legacy occupant-less complete button
        $drawerPending = $this->dispatchAdmin(new Request(
            'GET',
            '/reservations/res-dossier-pending',
            server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_HX_TARGET' => 'drawer-container']
        ));
        $this->assertSame(200, $drawerPending->getStatusCode());
        $this->assertStringContainsString('Edit Reservation &amp; Guests', $drawerPending->getBody());
        $this->assertStringNotContainsString('/registry/complete', $drawerPending->getBody());
    }

    public function testReservationEditorPersistsVehicleOnPendingRegistryAndRejectsExplicitMaxGuestsLoweringOnRosterSubmit(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, lang, registry_completed, max_guests
            ) VALUES (
                'res-pending-vehicle', '1606', 'Pending Driver', 'driver@test.com', '+573001112233',
                '2026-12-01', '2026-12-05', 1400000.00, 'confirmed', 'web', 'es', 0, 6
            );
        ");

        // 1. Save car_plates and car_model on a pending reservation (0 guests) -> persists in guest_registries with guest_count = 0 while keeping registry_completed = 0
        $saveVehicleRes = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-pending-vehicle/edit',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-12-01',
                'check_out' => '2026-12-05',
                'total_price' => '1400000',
                'max_guests' => '6',
                'guest_name' => 'Pending Driver',
                'guest_email' => 'driver@test.com',
                'guest_phone' => '+573001112233',
                'lang' => 'es',
                'car_plates' => 'VHC-789',
                'car_model' => 'Mazda CX-30 White',
                'guest_count' => '0',
                'reset_guest_registry' => '0',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(200, $saveVehicleRes->getStatusCode());

        $stmtRes = $this->pdo->prepare('SELECT registry_completed, registry_completed_at FROM reservations WHERE reservation_uid = :uid');
        $stmtRes->execute(['uid' => 'res-pending-vehicle']);
        $resRow = $stmtRes->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($resRow);
        $this->assertSame(0, (int) $resRow['registry_completed']);
        $this->assertNull($resRow['registry_completed_at']);

        $stmtReg = $this->pdo->prepare('SELECT guest_count, guests_payload, car_plates, car_model FROM guest_registries WHERE reservation_uid = :uid');
        $stmtReg->execute(['uid' => 'res-pending-vehicle']);
        $regRow = $stmtReg->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($regRow);
        $this->assertSame(0, (int) $regRow['guest_count']);
        $this->assertSame('[]', $regRow['guests_payload']);
        $this->assertSame('VHC-789', $regRow['car_plates']);
        $this->assertSame('Mazda CX-30 White', $regRow['car_model']);

        // 2. _registry_modal.php and _edit_modal.php treat registry_completed = 0 as Pending while showing pre-registered vehicle info
        $regModal = $this->dispatchAdmin(new Request(
            'GET',
            '/reservations/res-pending-vehicle/registry',
            server: ['HTTP_HX_REQUEST' => 'true']
        ));
        $this->assertSame(200, $regModal->getStatusCode());
        $this->assertStringContainsString('Guest Registry Pending', $regModal->getBody());
        $this->assertStringContainsString('VHC-789 (Mazda CX-30 White)', $regModal->getBody());

        $editModal = $this->dispatchAdmin(new Request(
            'GET',
            '/reservations/res-pending-vehicle/edit',
            server: ['HTTP_HX_REQUEST' => 'true']
        ));
        $this->assertSame(200, $editModal->getStatusCode());
        $this->assertStringContainsString('Registry Pending', $editModal->getBody());
        $this->assertStringContainsString('value="VHC-789"', $editModal->getBody());
        $this->assertStringContainsString('value="Mazda CX-30 White"', $editModal->getBody());

        // 3. Explicitly lowering max_guests (e.g. from 6 to 1) while submitting 2 valid Guests returns 422 instead of auto-bumping
        $lowerCapRes = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-pending-vehicle/edit',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-12-01',
                'check_out' => '2026-12-05',
                'total_price' => '1400000',
                'max_guests' => '1',
                'guest_name' => 'Pending Driver',
                'guest_email' => 'driver@test.com',
                'guest_phone' => '+573001112233',
                'lang' => 'es',
                'car_plates' => 'VHC-789',
                'car_model' => 'Mazda CX-30 White',
                'guest_count' => '2',
                'guest_first_name_1' => 'Pending',
                'guest_last_name_1' => 'Driver',
                'guest_doc_type_1' => 'Passport',
                'guest_doc_num_1' => 'PA112233',
                'guest_age_1' => '33',
                'guest_country_1' => 'COLOMBIA',
                'guest_email_1' => 'driver@test.com',
                'guest_phone_1' => '+573001112233',
                'guest_first_name_2' => 'Second',
                'guest_last_name_2' => 'Guest',
                'guest_doc_type_2' => 'Passport',
                'guest_doc_num_2' => 'PA445566',
                'guest_age_2' => '30',
                'guest_country_2' => 'COLOMBIA',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(422, $lowerCapRes->getStatusCode());
        $this->assertStringContainsString('Cannot lower maximum occupancy (1) below the 2 currently registered guests', $lowerCapRes->getBody());
    }

    public function testReservationDetailDrawerClearanceDismissalAndReArmingRoutes(): void
    {
        $this->pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, lang, registry_completed, max_guests
            ) VALUES (
                'res-drawer-dismiss', '1606', 'Drawer Dismiss Guest', 'dismiss@test.com', '+573001112233',
                '2026-12-01', '2026-12-05', 1400000.00, 'confirmed', 'web', 'es', 1, 6
            );

            INSERT INTO guest_registries (
                reservation_uid, property_id, check_in, check_out, guest_count, guests_payload
            ) VALUES (
                'res-drawer-dismiss', '1606', '2026-12-01', '2026-12-05', 1,
                '[{\"index\":1,\"name\":\"Drawer Dismiss Guest\",\"first_name\":\"Drawer\",\"last_name\":\"Guest\",\"doc_type\":\"Passport\",\"doc_num\":\"PA9999\",\"age\":30,\"country\":\"COLOMBIA\",\"email\":\"dismiss@test.com\",\"phone\":\"+573001112233\"}]'
            );

            INSERT INTO condominium_clearances (
                reservation_uid, property_id, status, error_message, attempts, last_attempt_at
            ) VALUES (
                'res-drawer-dismiss', '1606', 'failed', 'Portal HTTP 503 Unavailable', 2, '2026-10-16 10:00:00'
            );
        ");

        $dismissalRepo = new \OceanViewFlats\Domain\Reservation\Dashboard\PdoOperationalAlertDismissalRepository($this->pdo);

        // 1. GET /reservations/res-drawer-dismiss renders Failed badge, error diagnostics, Retry button, and Dismiss Alert button
        $showRes = $this->dispatchAdmin(new Request(
            'GET',
            '/reservations/res-drawer-dismiss',
            server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_HX_TARGET' => 'drawer-container']
        ));
        $this->assertSame(200, $showRes->getStatusCode());
        $showBody = $showRes->getBody();
        $this->assertStringContainsString('Failed', $showBody);
        $this->assertStringContainsString('Portal HTTP 503 Unavailable', $showBody);
        $this->assertStringContainsString('Retry Condominium Clearance', $showBody);
        $this->assertStringContainsString('Dismiss Alert', $showBody);
        $this->assertStringNotContainsString('Dismissed', $showBody);

        // 2. POST /dashboard/alerts/dismiss with return_context=drawer dismisses alert, writes audit log, and re-renders drawer with Dismissed badge
        $dismissRes = $this->dispatchAdmin(new Request(
            'POST',
            '/dashboard/alerts/dismiss',
            post: [
                'csrf_token' => 'test-csrf-token',
                'return_context' => 'drawer',
                'alert_id' => 'clearance_res-drawer-dismiss',
                'alert_type' => 'failed_condominium_clearance',
                'property_id' => '1606',
                'reservation_uid' => 'res-drawer-dismiss',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(200, $dismissRes->getStatusCode());
        $this->assertSame('reservationUpdated', $dismissRes->getHeader('HX-Trigger'));
        $dismissBody = $dismissRes->getBody();
        $this->assertTrue($dismissalRepo->isDismissed('clearance_res-drawer-dismiss'));
        $this->assertStringContainsString('Dismissed', $dismissBody);
        $this->assertStringContainsString('Portal HTTP 503 Unavailable', $dismissBody);
        $this->assertStringContainsString('Retry Condominium Clearance', $dismissBody);
        $this->assertStringNotContainsString('Dismiss Alert', $dismissBody);

        // 3. POST /reservations/res-drawer-dismiss/clearance-retry clears dismissal and transitions to Synced
        $retryRes = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-drawer-dismiss/clearance-retry',
            post: ['csrf_token' => 'test-csrf-token'],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(200, $retryRes->getStatusCode());
        $this->assertFalse($dismissalRepo->isDismissed('clearance_res-drawer-dismiss'));
        $this->assertStringContainsString('Synced', $retryRes->getBody());
        $this->assertStringContainsString('#777', $retryRes->getBody());
        $this->assertStringNotContainsString('Dismissed', $retryRes->getBody());

        // 4. POST /reservations/res-drawer-dismiss/edit with reset_guest_registry=1 clears registry dismissal
        $dismissalRepo->dismiss('registry_res-drawer-dismiss', 'incomplete_guest_registry', '1606', 'res-drawer-dismiss', 1);
        $this->assertTrue($dismissalRepo->isDismissed('registry_res-drawer-dismiss'));

        $resetRes = $this->dispatchAdmin(new Request(
            'POST',
            '/reservations/res-drawer-dismiss/edit',
            post: [
                'property_id' => '1606',
                'check_in' => '2026-12-01',
                'check_out' => '2026-12-05',
                'total_price' => '1400000',
                'max_guests' => '6',
                'guest_name' => 'Drawer Dismiss Guest',
                'guest_email' => 'dismiss@test.com',
                'guest_phone' => '+573001112233',
                'lang' => 'es',
                'reset_guest_registry' => '1',
                'guest_count' => '0',
                'csrf_token' => 'test-csrf-token',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'test-csrf-token',
            ]
        ));
        $this->assertSame(200, $resetRes->getStatusCode());
        $this->assertFalse($dismissalRepo->isDismissed('registry_res-drawer-dismiss'));
    }
}
