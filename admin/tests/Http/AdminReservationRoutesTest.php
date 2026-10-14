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
}
