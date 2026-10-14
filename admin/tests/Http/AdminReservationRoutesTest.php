<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Http;

use OceanViewFlats\Admin\AdminApp;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Http\Response;
use OceanViewFlats\Admin\Service\InMemoryMercadoPagoRefundClient;
use OceanViewFlats\Admin\Tests\Support\AdminDatabaseTestHelper;
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

    protected function setUp(): void
    {
        $this->pdo = AdminDatabaseTestHelper::createDatabase();
        $this->configureRouter();
    }

    private function configureRouter(): void
    {
        $this->refundClient = new InMemoryMercadoPagoRefundClient();
        $this->emailSender = new InMemoryEmailSender();
        $this->app = AdminApp::createDefault($this->pdo, [
            'refund_client' => $this->refundClient,
            'email_sender' => $this->emailSender,
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
}
