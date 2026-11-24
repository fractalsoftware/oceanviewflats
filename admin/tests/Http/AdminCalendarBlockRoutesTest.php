<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Http;

use DateTimeImmutable;
use OceanViewFlats\Admin\AdminApp;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Repository\AdminCalendarBlockRepository;
use OceanViewFlats\Admin\Tests\Support\AdminDatabaseTestHelper;
use OceanViewFlats\Domain\Reservation\InMemoryChannelBlockSource;
use OceanViewFlats\Domain\Reservation\InMemoryReservationRepository;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationLedger;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use PDO;
use PHPUnit\Framework\TestCase;

final class AdminCalendarBlockRoutesTest extends TestCase
{
    private PDO $pdo;
    private AdminApp $app;
    private AdminCalendarBlockRepository $blockRepo;
    private InMemoryReservationRepository $reservationRepo;
    private ReservationLedger $ledger;

    protected function setUp(): void
    {
        $this->pdo = AdminDatabaseTestHelper::createDatabaseWithDefaultAdmin();

        $this->blockRepo = new AdminCalendarBlockRepository($this->pdo);
        $this->reservationRepo = new InMemoryReservationRepository();
        $channelBlockSource = new InMemoryChannelBlockSource();
        $this->ledger = new ReservationLedger(
            repository: $this->reservationRepo,
            channelBlockSource: $channelBlockSource
        );

        $this->app = AdminApp::createDefault($this->pdo, [
            'ledger' => $this->ledger,
        ]);
    }

    public function testUnauthenticatedAccessRedirectsToLogin(): void
    {
        $session = [];
        $request = new Request(method: 'GET', uri: '/calendar-blocks');
        $response = $this->app->handle($request, $session);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeader('Location'));
    }

    public function testAuthenticatedAccessRendersCalendarBlocks(): void
    {
        $session = [
            'admin_user_id' => 1,
            'admin_user_role' => 'admin',
            'admin_email' => 'admin@oceanviewflats.com',
            'csrf_token' => 'route_test_csrf',
        ];
        $request = new Request(method: 'GET', uri: '/calendar-blocks');
        $response = $this->app->handle($request, $session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertStringContainsString('Maintenance & Calendar Holds', $body);
        $this->assertStringContainsString('Property:', $body);
        $this->assertStringNotContainsString('Unit:', $body);
        $this->assertStringContainsString('id="blocks-view-container"', $body);
        $this->assertStringContainsString('id="calendar-blocks-header-actions"', $body);
        $this->assertStringContainsString('id="channel-sync-panel"', $body);
        $this->assertStringContainsString('bg-indigo-600 text-white shadow-2xs', $body);
        $this->assertStringContainsString('aria-current="page"', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*role="button"[^>]*>\s*All Properties\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*role="button"[^>]*>\s*Apartment 1606\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*role="button"[^>]*>\s*Apartment 1707\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*role="button"[^>]*>\s*Active & Upcoming\s*<\/a>/s', $body);
    }

    public function testAuthenticatedHtmxAccessRendersPartialAndHeaderActionsOob(): void
    {
        $session = [
            'admin_user_id' => 1,
            'admin_user_role' => 'admin',
            'admin_email' => 'admin@oceanviewflats.com',
            'csrf_token' => 'route_test_csrf',
        ];
        $request = new Request(
            method: 'GET',
            uri: '/calendar-blocks',
            query: ['property_id' => '1707', 'filter' => 'past'],
            server: ['HTTP_HX_REQUEST' => 'true']
        );
        $response = $this->app->handle($request, $session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertStringContainsString('id="blocks-view-container"', $body);
        $this->assertStringContainsString('id="calendar-blocks-header-actions" hx-swap-oob="outerHTML"', $body);
        $this->assertStringContainsString('hx-get="/calendar-blocks/new?property_id=1707&filter=past"', $body);
        $this->assertStringNotContainsString('id="channel-sync-panel"', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*role="button"[^>]*>\s*Apartment 1707\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*role="button"[^>]*>\s*Concluded \(Past\)\s*<\/a>/s', $body);
    }

    public function testHtmxGetCalendarBlocksWithBodyTargetReturnsFullLayoutShell(): void
    {
        $session = [
            'admin_user_id' => 1,
            'admin_user_role' => 'admin',
            'admin_email' => 'admin@oceanviewflats.com',
            'csrf_token' => 'route_test_csrf',
        ];
        $request = new Request(
            method: 'GET',
            uri: '/calendar-blocks',
            query: ['property_id' => '1606', 'filter' => 'upcoming'],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_TARGET' => 'body',
            ]
        );
        $response = $this->app->handle($request, $session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Must contain full layout shell
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('<body', $body);
        $this->assertStringContainsString('Maintenance & Calendar Holds', $body);
        $this->assertStringContainsString('id="blocks-view-container"', $body);
        $this->assertStringContainsString('id="channel-sync-panel"', $body);
    }

    public function testGetNewHoldModalReturns200(): void
    {
        $session = [
            'admin_user_id' => 1,
            'admin_user_role' => 'admin',
            'csrf_token' => 'route_test_csrf',
        ];
        $request = new Request(method: 'GET', uri: '/calendar-blocks/new');
        $response = $this->app->handle($request, $session);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Add Maintenance Hold', $response->getBody());
    }

    public function testPostCalendarBlockWithoutCsrfTokenIsRejected(): void
    {
        $session = [
            'admin_user_id' => 1,
            'admin_user_role' => 'admin',
            'csrf_token' => 'route_test_csrf',
        ];
        $request = new Request(
            method: 'POST',
            uri: '/calendar-blocks',
            post: [
                'property_id' => '1606',
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-05',
                'reason' => 'Fix plumbing',
            ]
        );

        $response = $this->app->handle($request, $session);
        // CsrfMiddleware or Controller CSRF rejects
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testPostCalendarBlockWithValidCsrfCreatesBlock(): void
    {
        $session = [
            'admin_user_id' => 1,
            'admin_user_role' => 'admin',
            'admin_email' => 'admin@oceanviewflats.com',
            'csrf_token' => 'route_test_csrf',
        ];
        $request = new Request(
            method: 'POST',
            uri: '/calendar-blocks',
            post: [
                'csrf_token' => 'route_test_csrf',
                'property_id' => '1606',
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-05',
                'reason' => 'Deep tile cleaning',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
            ]
        );

        $response = $this->app->handle($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('blockSaved', $response->getHeader('HX-Trigger'));
        $body = $response->getBody();
        $this->assertStringContainsString('id="blocks-view-container"', $body);
        $this->assertStringContainsString('hx-swap-oob="outerHTML"', $body);
        $this->assertStringContainsString('id="calendar-blocks-header-actions"', $body);
        $this->assertStringContainsString('<div id="modal-container" hx-swap-oob="innerHTML"></div>', $body);
        $this->assertStringNotContainsString('<script>', $body);

        $blocks = $this->blockRepo->getBlocks('1606', 'all');
        $this->assertCount(1, $blocks);
        $this->assertSame('Deep tile cleaning', $blocks[0]['reason']);
    }

    public function testPostCalendarBlockCollidingWithReservationReturns422(): void
    {
        $now = new DateTimeImmutable('2026-10-01 10:00:00');
        $this->reservationRepo->save(Reservation::create(
            reservationUid: 'ovf_route_collision',
            propertyId: '1606',
            guestName: 'Route Test Guest',
            guestEmail: 'test@example.com',
            guestPhone: '+573001234567',
            checkIn: '2026-12-01',
            checkOut: '2026-12-05',
            totalPrice: 1500000.0,
            status: ReservationStatus::CONFIRMED,
            createdAt: $now
        ));

        $session = [
            'admin_user_id' => 1,
            'admin_user_role' => 'admin',
            'csrf_token' => 'route_test_csrf',
        ];
        $request = new Request(
            method: 'POST',
            uri: '/calendar-blocks',
            post: [
                'csrf_token' => 'route_test_csrf',
                'property_id' => '1606',
                'start_date' => '2026-12-02',
                'end_date' => '2026-12-04',
                'reason' => 'Conflicting hold',
            ]
        );

        $response = $this->app->handle($request, $session);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('#modal-container', $response->getHeader('HX-Retarget'));
        $this->assertSame('innerHTML', $response->getHeader('HX-Reswap'));
        $this->assertStringContainsString('Collides with active direct reservation ovf_route_collision', $response->getBody());
    }

    public function testDeleteCalendarBlockRouteRemovesBlock(): void
    {
        $futureStart = date('Y-m-d', strtotime('+3 days'));
        $futureEnd = date('Y-m-d', strtotime('+7 days'));
        $id = $this->blockRepo->createBlock('1606', $futureStart, $futureEnd, 'Temporary fixture repair', 1);

        $session = [
            'admin_user_id' => 1,
            'admin_user_role' => 'admin',
            'csrf_token' => 'route_test_csrf',
        ];
        $request = new Request(
            method: 'DELETE',
            uri: "/calendar-blocks/{$id}",
            post: ['csrf_token' => 'route_test_csrf'],
            server: ['HTTP_HX_REQUEST' => 'true']
        );

        $response = $this->app->handle($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($this->blockRepo->findById($id));
    }

    public function testPostReleaseRouteRemovesBlockAndReturnsHtmxResponse(): void
    {
        $futureStart = date('Y-m-d', strtotime('+4 days'));
        $futureEnd = date('Y-m-d', strtotime('+8 days'));
        $id = $this->blockRepo->createBlock('1606', $futureStart, $futureEnd, 'HVAC filter swap', 1);

        $session = [
            'admin_user_id' => 1,
            'admin_user_role' => 'admin',
            'csrf_token' => 'route_test_csrf',
        ];
        $request = new Request(
            method: 'POST',
            uri: "/calendar-blocks/{$id}/release",
            post: [
                'csrf_token' => 'route_test_csrf',
                'property_id' => '1606',
                'filter' => 'upcoming',
            ],
            server: ['HTTP_HX_REQUEST' => 'true']
        );

        $response = $this->app->handle($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('blockReleased', $response->getHeader('HX-Trigger'));
        $body = $response->getBody();
        $this->assertStringContainsString('id="blocks-view-container"', $body);
        $this->assertStringContainsString('id="calendar-blocks-header-actions" hx-swap-oob="outerHTML"', $body);
        $this->assertStringContainsString('hx-get="/calendar-blocks/new?property_id=1606&filter=upcoming"', $body);
        $this->assertNull($this->blockRepo->findById($id));
    }

    public function testPostDeleteFallbackRouteRemovesBlock(): void
    {
        $futureStart = date('Y-m-d', strtotime('+10 days'));
        $futureEnd = date('Y-m-d', strtotime('+14 days'));
        $id = $this->blockRepo->createBlock('1707', $futureStart, $futureEnd, 'Balcony repair', 1);

        $session = [
            'admin_user_id' => 1,
            'admin_user_role' => 'admin',
            'csrf_token' => 'route_test_csrf',
        ];
        $request = new Request(
            method: 'POST',
            uri: "/calendar-blocks/{$id}/delete",
            post: ['csrf_token' => 'route_test_csrf'],
            server: ['HTTP_HX_REQUEST' => 'true']
        );

        $response = $this->app->handle($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($this->blockRepo->findById($id));
    }

    public function testDeletePastConcludedBlockIsLockedAndReturns422(): void
    {
        $pastStart = date('Y-m-d', strtotime('-10 days'));
        $pastEnd = date('Y-m-d', strtotime('-5 days'));
        $id = $this->blockRepo->createBlock('1606', $pastStart, $pastEnd, 'Historic roof inspection', 1);

        $session = [
            'admin_user_id' => 1,
            'admin_user_role' => 'admin',
            'csrf_token' => 'route_test_csrf',
        ];
        $request = new Request(
            method: 'DELETE',
            uri: "/calendar-blocks/{$id}",
            post: ['csrf_token' => 'route_test_csrf']
        );

        $response = $this->app->handle($request, $session);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Concluded historical maintenance blocks cannot be deleted', $response->getBody());
        $this->assertNotNull($this->blockRepo->findById($id));
    }
}
