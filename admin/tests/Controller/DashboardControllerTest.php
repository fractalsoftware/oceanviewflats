<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Controller;

use OceanViewFlats\Admin\Controller\DashboardController;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Http\Response;
use OceanViewFlats\Admin\Views\ViewRenderer;
use OceanViewFlats\Domain\Reservation\Dashboard\AlertSeverity;
use OceanViewFlats\Domain\Reservation\Dashboard\AlertType;
use OceanViewFlats\Domain\Reservation\Dashboard\DashboardHubViewData;
use OceanViewFlats\Domain\Reservation\Dashboard\DashboardQueryServiceInterface;
use OceanViewFlats\Domain\Reservation\Dashboard\DayDetailViewData;
use OceanViewFlats\Domain\Reservation\Dashboard\DayPropertyDetail;
use OceanViewFlats\Domain\Reservation\Dashboard\HeatmapDayCell;
use OceanViewFlats\Domain\Reservation\Dashboard\HeatmapWeekColumn;
use OceanViewFlats\Domain\Reservation\Dashboard\MovementType;
use OceanViewFlats\Domain\Reservation\Dashboard\OccupancyHeatmapViewData;
use OceanViewFlats\Domain\Reservation\Dashboard\OperationalAlert;
use OceanViewFlats\Domain\Reservation\Dashboard\OperationsEvent;
use OceanViewFlats\Domain\Reservation\Dashboard\PropertyRateStatus;
use OceanViewFlats\Domain\Reservation\MaintenanceBlock;
use PHPUnit\Framework\TestCase;

final class DashboardControllerTest extends TestCase
{
    private ViewRenderer $viewRenderer;
    private DashboardController $controller;

    protected function setUp(): void
    {
        $viewsPath = dirname(__DIR__, 2) . '/src/Views';
        $this->viewRenderer = new ViewRenderer($viewsPath);
        $this->controller = new DashboardController($this->viewRenderer);
    }

    public function testIndexRendersDashboardForAuthenticatedUser(): void
    {
        $session = [
            'admin_user_id' => 99,
            'admin_user_name' => 'Operator Alice',
            'admin_user_email' => 'alice@oceanviewflats.com',
            'admin_user_role' => 'manager',
            'csrf_token' => 'session_csrf_token_xyz',
        ];
        $request = new Request('GET', '/');

        $response = $this->controller->index($request, $session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Check user identity & role in layout header, and absence of removed welcome card
        $this->assertStringContainsString('Operator Alice', $body);
        $this->assertStringContainsString('manager', $body);
        $this->assertStringContainsString('Sign Out', $body);
        $this->assertStringNotContainsString('Welcome back', $body);

        // Check canonical resource collection links in layout navigation bar
        $this->assertStringContainsString('href="/"', $body);
        $this->assertStringContainsString('Dashboard', $body);
        $this->assertStringContainsString('href="/reservations"', $body);
        $this->assertStringContainsString('Reservations', $body);
        $this->assertStringContainsString('href="/rates"', $body);
        $this->assertStringContainsString('Rates', $body);
        $this->assertStringContainsString('href="/calendar-blocks"', $body);
        $this->assertStringContainsString('Calendar Blocks', $body);
        $this->assertStringNotContainsString('href="/audit-logs"', $body);

        // Check mobile hamburger navigation & input zoom prevention in layout
        $this->assertStringContainsString('id="mobile-menu-button"', $body);
        $this->assertStringContainsString('id="mobile-menu"', $body);
        $this->assertStringContainsString('aria-controls="mobile-menu"', $body);
        $this->assertStringContainsString('maximum-scale=1.0', $body);
        $this->assertStringContainsString('font-size: 16px !important', $body);

        // Check dashboard UI sections & verify removed clutter cards
        $this->assertStringNotContainsString('Reservations & Calendar', $body);
        $this->assertStringNotContainsString('Smart Lock Status', $body);
        $this->assertStringNotContainsString('Keypad PIN Integrations Ready', $body);
        $this->assertStringNotContainsString('+ Manual Booking', $body);
        $this->assertStringNotContainsString('/bookings/manual', $body);
        $this->assertStringContainsString('Channel Feeds Active', $body);
        $this->assertStringContainsString('id="channel-card-container"', $body);
        $this->assertStringContainsString('Sync Now', $body);
        $this->assertStringContainsString('hx-post="/channel-sync"', $body);

        // Check CSRF token inclusion
        $this->assertStringContainsString('session_csrf_token_xyz', $body);
    }

    public function testIndexHandlesDefaultUserFallbackWhenSessionFieldsMissing(): void
    {
        $session = [];
        $request = new Request('GET', '/');

        $response = $this->controller->index($request, $session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Default fallbacks: 'Admin' and 'admin' in header, and 'Audit Logs' visible for 'admin' role
        $this->assertStringContainsString('Admin', $body);
        $this->assertStringContainsString('(admin)', $body);
        $this->assertStringContainsString('href="/audit-logs"', $body);
        $this->assertStringNotContainsString('Welcome back', $body);
    }

    public function testIndexAdheresToUniformCallableSignature(): void
    {
        $session = ['admin_user_id' => 1];
        $request = new Request('GET', '/');

        $invoker = function (callable $handler, Request $req, array &$sess): Response {
            return $handler($req, $sess);
        };

        $response = $invoker([$this->controller, 'index'], $request, $session);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testLayoutIncludesHtmxConfigFor422Swapping(): void
    {
        $session = ['admin_user_id' => 1];
        $request = new Request('GET', '/');

        $response = $this->controller->index($request, $session);
        $body = $response->getBody();

        // Meta tag for HTMX 2.x responseHandling permitting 422 swapping
        $this->assertStringContainsString('name="htmx-config"', $body);
        $this->assertStringContainsString('"code": "422"', $body);
        $this->assertStringContainsString('"swap": true', $body);

        // Global htmx:beforeSwap listener for resilient 422 swapping and error suppression
        $this->assertStringContainsString('htmx:beforeSwap', $body);
        $this->assertStringContainsString('422', $body);
        $this->assertStringContainsString('shouldSwap', $body);
    }

    public function testIndexRendersOperationalHubWidgetsAndData(): void
    {
        $today = date('Y-m-d');
        $hubData = $this->createSampleHubData($today);

        $queryService = $this->createMock(DashboardQueryServiceInterface::class);
        $queryService->expects($this->once())
            ->method('getDashboardHubData')
            ->with('all')
            ->willReturn($hubData);

        $controller = new DashboardController(
            viewRenderer: $this->viewRenderer,
            syncService: null,
            dashboardQueryService: $queryService
        );

        $session = ['admin_user_id' => 1, 'admin_user_name' => 'Operator Bob'];
        $request = new Request('GET', '/');

        $response = $controller->index($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Check master container anchors
        $this->assertStringContainsString('id="dashboard-hub-content"', $body);
        $this->assertStringContainsString('id="modal-container"', $body);
        $this->assertStringContainsString('id="drawer-container"', $body);

        // Check property filter pills with HTMX targets and accessibility roles
        $this->assertStringContainsString('role="button"', $body);
        $this->assertStringContainsString('hx-target="#dashboard-hub-content"', $body);
        $this->assertStringContainsString('hx-swap="outerHTML"', $body);
        $this->assertStringContainsString('All Properties', $body);
        $this->assertStringContainsString('Apartment 1606', $body);
        $this->assertStringContainsString('Apartment 1707', $body);
        $this->assertStringContainsString('aria-current="page"', $body);

        // Check operational metric cards
        $this->assertStringContainsString("Today's Arrivals", $body);
        $this->assertStringContainsString("Today's Departures", $body);
        $this->assertStringContainsString('In-House Stays', $body);
        $this->assertStringContainsString('Operational Alerts', $body);

        // Check schedule horizon and turnover alert
        $this->assertStringContainsString('7-Day Operations Horizon', $body);
        $this->assertStringContainsString('Same-Day Turnover (Apartment 1606)', $body);
        $this->assertStringContainsString('Bob Builder', $body);
        $this->assertStringContainsString('Charlie Brown', $body);
        $this->assertStringContainsString('David Copperfield', $body);

        // Check alerts card
        $this->assertStringContainsString('Operational Attention', $body);
        $this->assertStringContainsString('Guest Registration Incomplete', $body);
        $this->assertStringContainsString('Un-onboarded Airbnb Hold', $body);
        $this->assertStringContainsString('Copy Invite', $body);
        $this->assertStringContainsString('Onboard Airbnb Guest', $body);

        // Check rates and blocks
        $this->assertStringContainsString("Today's Effective Rates", $body);
        $this->assertStringContainsString('Seasonal: High Season', $body);
        $this->assertStringContainsString('Upcoming Calendar Holds', $body);
        $this->assertStringContainsString('Deep AC Maintenance', $body);
        $this->assertStringContainsString('+ Add Hold', $body);
    }

    public function testHubReturnsPartialWhenRequestedViaHtmx(): void
    {
        $today = date('Y-m-d');
        $hubData = $this->createSampleHubData($today);

        $queryService = $this->createMock(DashboardQueryServiceInterface::class);
        $queryService->expects($this->once())
            ->method('getDashboardHubData')
            ->with('1606')
            ->willReturn($hubData);

        $controller = new DashboardController(
            viewRenderer: $this->viewRenderer,
            syncService: null,
            dashboardQueryService: $queryService
        );

        $session = ['admin_user_id' => 1];
        $request = new Request(
            method: 'GET',
            uri: '/dashboard/hub',
            query: ['property_id' => '1606'],
            post: [],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_TARGET' => 'dashboard-hub-content',
            ]
        );

        $response = $controller->hub($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Must contain the hub content container
        $this->assertStringContainsString('id="dashboard-hub-content"', $body);
        // Must NOT contain the full master layout elements
        $this->assertStringNotContainsString('<!DOCTYPE html>', $body);
        $this->assertStringNotContainsString('Welcome back', $body);
        $this->assertStringNotContainsString('Sign Out', $body);

        // Apartment 1606 filter pill must be active with aria-current="page"
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>[\s\n]*Apartment 1606/s', $body);
    }

    public function testHubRendersFullLayoutWhenDirectBrowserNavigationOrTargetIsBody(): void
    {
        $today = date('Y-m-d');
        $hubData = $this->createSampleHubData($today);

        $queryService = $this->createMock(DashboardQueryServiceInterface::class);
        $queryService->expects($this->exactly(2))
            ->method('getDashboardHubData')
            ->with('all')
            ->willReturn($hubData);

        $controller = new DashboardController(
            viewRenderer: $this->viewRenderer,
            syncService: null,
            dashboardQueryService: $queryService
        );

        $session = ['admin_user_id' => 1, 'admin_user_name' => 'Direct Navigator'];

        // Case 1: Non-HTMX direct browser navigation
        $directRequest = new Request('GET', '/dashboard/hub');
        $response1 = $controller->hub($directRequest, $session);
        $this->assertSame(200, $response1->getStatusCode());
        $this->assertStringContainsString('Direct Navigator', $response1->getBody());
        $this->assertStringContainsString('<!DOCTYPE html>', $response1->getBody());

        // Case 2: HTMX request explicitly targeting body
        $bodyTargetRequest = new Request(
            method: 'GET',
            uri: '/dashboard/hub',
            query: [],
            post: [],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_TARGET' => 'body',
            ]
        );
        $response2 = $controller->hub($bodyTargetRequest, $session);
        $this->assertSame(200, $response2->getStatusCode());
        $this->assertStringContainsString('Direct Navigator', $response2->getBody());
        $this->assertStringContainsString('<!DOCTYPE html>', $response2->getBody());
    }

    public function testIndexReturnsPartialWhenHtmxTargetIsHubContent(): void
    {
        $today = date('Y-m-d');
        $hubData = $this->createSampleHubData($today);

        $queryService = $this->createMock(DashboardQueryServiceInterface::class);
        $queryService->expects($this->once())
            ->method('getDashboardHubData')
            ->with('1707')
            ->willReturn($hubData);

        $controller = new DashboardController(
            viewRenderer: $this->viewRenderer,
            syncService: null,
            dashboardQueryService: $queryService
        );

        $session = ['admin_user_id' => 1];
        $request = new Request(
            method: 'GET',
            uri: '/',
            query: ['property_id' => '1707'],
            post: [],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_TARGET' => '#dashboard-hub-content',
            ]
        );

        $response = $controller->index($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Must return the partial without layout shell
        $this->assertStringContainsString('id="dashboard-hub-content"', $body);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $body);
        $this->assertStringNotContainsString('Welcome back', $body);
    }

    public function testFilterScopingFallsBackToAllForInvalidProperty(): void
    {
        $queryService = $this->createMock(DashboardQueryServiceInterface::class);
        $queryService->expects($this->once())
            ->method('getDashboardHubData')
            ->with('all')
            ->willReturn($this->createSampleHubData(date('Y-m-d')));

        $controller = new DashboardController(
            viewRenderer: $this->viewRenderer,
            syncService: null,
            dashboardQueryService: $queryService
        );

        $session = ['admin_user_id' => 1];
        $request = new Request('GET', '/', query: ['property_id' => 'malicious_property']);

        $response = $controller->index($request, $session);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testIndexRendersFailedCondominiumClearanceAlert(): void
    {
        $today = date('Y-m-d');
        $hubData = new DashboardHubViewData(
            selectedPropertyFilter: 'all',
            alerts: [
                new OperationalAlert(
                    id: 'clearance_res_fail_999',
                    type: AlertType::FAILED_CONDOMINIUM_CLEARANCE,
                    severity: AlertSeverity::CRITICAL,
                    propertyId: '1606',
                    title: 'Condominium Clearance Failed',
                    description: 'Condominium Administration Portal synchronization failed: Portal connection timed out',
                    dueDate: $today,
                    reservationUid: 'res_fail_999',
                    channelBlockUid: null,
                    guestName: 'Stuck Guest',
                    actionPayload: [
                        'reservationUid' => 'res_fail_999',
                        'propertyId' => '1606',
                        'errorMessage' => 'Portal connection timed out',
                    ]
                ),
            ],
            scheduleByDate: [],
            rateStatus: [],
            upcomingMaintenanceBlocks: [],
            channelSyncData: [],
            todayArrivalsCount: 0,
            todayDeparturesCount: 0,
            activeStaysCount: 0
        );

        $queryService = $this->createMock(DashboardQueryServiceInterface::class);
        $queryService->expects($this->once())
            ->method('getDashboardHubData')
            ->with('all')
            ->willReturn($hubData);

        $controller = new DashboardController(
            viewRenderer: $this->viewRenderer,
            syncService: null,
            dashboardQueryService: $queryService
        );

        $session = ['admin_user_id' => 1];
        $request = new Request('GET', '/');

        $response = $controller->index($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('Condominium Clearance Failed', $body);
        $this->assertStringContainsString('Portal connection timed out', $body);
        $this->assertStringContainsString('href="/reservations/res_fail_999"', $body);
        $this->assertStringContainsString('hx-get="/reservations/res_fail_999"', $body);
        $this->assertStringContainsString('hx-target="#drawer-container"', $body);
    }

    public function testDashboardHubRendersOccupancyHeatmapCardAndLegend(): void
    {
        $today = date('Y-m-d');
        $sampleDay = new HeatmapDayCell(
            date: $today,
            dayOfMonth: (int) date('j'),
            dayOfWeek: (int) date('N'),
            isToday: true,
            isPast: false,
            totalUnits: 2,
            occupiedUnits: 1
        );
        $weekCol = new HeatmapWeekColumn(weekNumber: 1, days: [$sampleDay, null, null, null, null, null, null], monthLabel: 'Oct');
        $heatmap = new OccupancyHeatmapViewData(
            startDate: '2026-10-01',
            endDate: '2027-04-30',
            propertyFilter: 'all',
            columns: [$weekCol],
            monthHeaders: [['label' => 'Oct', 'startCol' => 0, 'span' => 1]],
            overallOccupancyPercentage: 50.0,
            totalDaysCount: 212,
            totalOccupiedDaysCount: 106
        );

        $hubData = new DashboardHubViewData(
            selectedPropertyFilter: 'all',
            alerts: [],
            scheduleByDate: [],
            rateStatus: [],
            upcomingMaintenanceBlocks: [],
            channelSyncData: [],
            todayArrivalsCount: 0,
            todayDeparturesCount: 0,
            activeStaysCount: 0,
            heatmap: $heatmap
        );

        $queryService = $this->createMock(DashboardQueryServiceInterface::class);
        $queryService->expects($this->once())
            ->method('getDashboardHubData')
            ->with('all')
            ->willReturn($hubData);

        $controller = new DashboardController(
            viewRenderer: $this->viewRenderer,
            syncService: null,
            dashboardQueryService: $queryService
        );

        $session = ['admin_user_id' => 1];
        $request = new Request('GET', '/');

        $response = $controller->index($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // 1. Heatmap card header & responsive wrapping
        $this->assertStringContainsString('Occupancy Heatmap', $body);
        $this->assertStringContainsString('(7-Month Horizon)', $body);
        $this->assertStringContainsString('50.0% Occupied', $body);
        $this->assertStringContainsString('106 of 212 days active', $body);
        $this->assertStringContainsString('flex flex-wrap sm:flex-nowrap items-start sm:items-center justify-between gap-3', $body);
        $this->assertStringContainsString('overflow-x-auto pb-2 -mx-2 px-2', $body);

        // 2. Heatmap stream placement: inside 8-column operations stream container and preceding 7-Day Operations Schedule
        $this->assertOperationsStreamOrdering($body, 'dashboard hub');

        // 3. Weekday indicators (all 7 weekdays: Mon through Sun in sequence)
        $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $lastOffset = 0;
        foreach ($weekdays as $day) {
            $this->assertStringContainsString($day, $body);
            $dayPos = strpos($body, $day, $lastOffset);
            $this->assertNotFalse($dayPos, "Expected weekday '{$day}' to appear in sequence in rendered dashboard output.");
            $lastOffset = $dayPos + strlen($day);
        }

        // Verify sticky weekday column light styling and absence of dark:bg-slate-900
        $this->assertStringContainsString('sticky left-0 bg-white border-r border-gray-100 pr-1.5', $body);
        $this->assertStringNotContainsString('dark:bg-slate-900', $body);

        // 4. Month label header
        $this->assertStringContainsString('Oct', $body);

        // 5. Capacity legend for 'all' properties view
        $this->assertStringContainsString('Capacity:', $body);
        $this->assertStringContainsString('Vacant (0/2)', $body);
        $this->assertStringContainsString('Partial (1/2)', $body);
        $this->assertStringContainsString('Full (2/2)', $body);
        $this->assertStringContainsString('Today', $body);
        $this->assertStringContainsString('Past Dates', $body);
        $this->assertStringContainsString('Maintenance', $body);
        $this->assertStringContainsString('Pending Hold', $body);
        $this->assertStringContainsString('Turnover', $body);
        $this->assertStringContainsString('Operational Warning', $body);
    }

    public function testDashboardHubRendersOccupancyHeatmapIndicatorsTooltipsAndExtendedLegend(): void
    {
        $cellDate = '2026-10-20';
        $cell = new HeatmapDayCell(
            date: $cellDate,
            dayOfMonth: 20,
            dayOfWeek: 2,
            isToday: false,
            isPast: false,
            totalUnits: 2,
            occupiedUnits: 1,
            propertyFilter: 'all',
            hasTurnover: true,
            hasMaintenance: false,
            hasPendingHold: false,
            hasRiskAlert: true,
            singleReservationUid: 'res-indicator-100',
            reservationUids: ['res-indicator-100'],
            flatDetails: [
                '1606' => [
                    'property_id' => '1606',
                    'status' => 'Reserved (Confirmed)',
                    'label' => 'Property 1606: Reserved (Confirmed)',
                    'rate' => 110,
                    'guest' => 'Indicator Test Guest',
                    'movement' => 'Turnover',
                ],
            ],
            effectiveRate: 110,
            warningMessages: ['Guest registry incomplete (Indicator Test Guest)'],
            hasWarning: true
        );

        $weekCol = new HeatmapWeekColumn(weekNumber: 42, days: [$cell, null, null, null, null, null, null], monthLabel: 'Oct');
        $heatmap = new OccupancyHeatmapViewData(
            startDate: '2026-10-01',
            endDate: '2027-04-30',
            propertyFilter: 'all',
            columns: [$weekCol],
            monthHeaders: [['label' => 'Oct', 'startCol' => 0, 'span' => 1]],
            overallOccupancyPercentage: 50.0,
            totalDaysCount: 212,
            totalOccupiedDaysCount: 106,
            totalTurnoversCount: 1,
            totalAlertsCount: 1
        );

        $hubData = new DashboardHubViewData(
            selectedPropertyFilter: 'all',
            alerts: [],
            scheduleByDate: [],
            rateStatus: [],
            upcomingMaintenanceBlocks: [],
            channelSyncData: [],
            todayArrivalsCount: 0,
            todayDeparturesCount: 0,
            activeStaysCount: 0,
            heatmap: $heatmap
        );

        $queryService = $this->createMock(DashboardQueryServiceInterface::class);
        $queryService->expects($this->once())
            ->method('getDashboardHubData')
            ->with('all')
            ->willReturn($hubData);

        $controller = new DashboardController(
            viewRenderer: $this->viewRenderer,
            syncService: null,
            dashboardQueryService: $queryService
        );

        $session = ['admin_user_id' => 1];
        $request = new Request('GET', '/');

        $response = $controller->index($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // 1. Turnover blue dot indicator
        $this->assertStringContainsString('bg-blue-500 ring-1 ring-white pointer-events-none', $body);

        // 2. Operational warning rose dot indicator
        $this->assertStringContainsString('bg-rose-500 ring-1 ring-white pointer-events-none', $body);

        // 3. Viewport-fixed non-clipping hover popover container outside scroll wrapper
        $this->assertStringContainsString('id="heatmap-popover"', $body);
        $this->assertStringContainsString('role="tooltip"', $body);

        // 4. Popover JSON payload on the cell
        $this->assertStringContainsString('data-heatmap-cell="true"', $body);
        $this->assertStringContainsString('data-date="2026-10-20"', $body);
        $this->assertStringContainsString('data-popover="', $body);
        $this->assertStringContainsString('Indicator Test Guest', $body);
        $this->assertStringContainsString('Guest registry incomplete', $body);
        $this->assertStringContainsString('Property 1606: Reserved (Confirmed)', $body);
    }

    public function testHtmxPropertyFilterRendersScopedHeatmap(): void
    {
        $today = date('Y-m-d');
        $sampleDay = new HeatmapDayCell(
            date: $today,
            dayOfMonth: (int) date('j'),
            dayOfWeek: (int) date('N'),
            isToday: true,
            isPast: false,
            totalUnits: 1,
            occupiedUnits: 1
        );
        $weekCol = new HeatmapWeekColumn(weekNumber: 1, days: [$sampleDay, null, null, null, null, null, null], monthLabel: 'Oct');
        $heatmap = new OccupancyHeatmapViewData(
            startDate: '2026-10-01',
            endDate: '2027-04-30',
            propertyFilter: '1606',
            columns: [$weekCol],
            monthHeaders: [['label' => 'Oct', 'startCol' => 0, 'span' => 1]],
            overallOccupancyPercentage: 100.0,
            totalDaysCount: 212,
            totalOccupiedDaysCount: 212
        );

        $hubData = new DashboardHubViewData(
            selectedPropertyFilter: '1606',
            alerts: [],
            scheduleByDate: [],
            rateStatus: [],
            upcomingMaintenanceBlocks: [],
            channelSyncData: [],
            todayArrivalsCount: 0,
            todayDeparturesCount: 0,
            activeStaysCount: 0,
            heatmap: $heatmap
        );

        $queryService = $this->createMock(DashboardQueryServiceInterface::class);
        $queryService->expects($this->once())
            ->method('getDashboardHubData')
            ->with('1606')
            ->willReturn($hubData);

        $controller = new DashboardController(
            viewRenderer: $this->viewRenderer,
            syncService: null,
            dashboardQueryService: $queryService
        );

        $session = ['admin_user_id' => 1];
        $request = new Request(
            method: 'GET',
            uri: '/dashboard/hub',
            query: ['property_id' => '1606'],
            post: [],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_TARGET' => 'dashboard-hub-content',
            ]
        );

        $response = $controller->hub($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // 1. Partial returned without full layout
        $this->assertStringNotContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('id="dashboard-hub-content"', $body);

        // 2. Heatmap header & scoped metrics
        $this->assertStringContainsString('Occupancy Heatmap', $body);
        $this->assertStringContainsString('100.0% Occupied', $body);
        $this->assertStringContainsString('flex flex-wrap sm:flex-nowrap items-start sm:items-center justify-between gap-3', $body);

        // 3. Single property legend (Vacant, Occupied - without ratios 0/2, 1/2, 2/2)
        $this->assertStringContainsString('Vacant', $body);
        $this->assertStringContainsString('Occupied', $body);
        $this->assertStringNotContainsString('(0/2)', $body);
        $this->assertStringNotContainsString('(1/2)', $body);
        $this->assertStringNotContainsString('(2/2)', $body);

        // 4. Verify 8-column operations stream layout structure and ordering under HTMX property filter
        $this->assertOperationsStreamOrdering($body, 'HTMX partial');
    }

    private function assertOperationsStreamOrdering(string $body, string $context = ''): void
    {
        $suffix = $context !== '' ? " in {$context}" : '';
        $gridPos = strpos($body, 'class="grid grid-cols-1 lg:grid-cols-12 gap-6"');
        $this->assertNotFalse($gridPos, "Two-column grid container must be present{$suffix}.");
        $streamPos = strpos($body, 'class="lg:col-span-8 space-y-6"', $gridPos);
        $this->assertNotFalse($streamPos, "8-column operations stream container must be present{$suffix}.");
        $heatmapPos = strpos($body, 'Occupancy Heatmap', $streamPos);
        $this->assertNotFalse($heatmapPos, "Occupancy Heatmap must be rendered inside the 8-column operations stream{$suffix}.");
        $schedulePos = strpos($body, '7-Day Operations Horizon', $heatmapPos);
        $this->assertNotFalse($schedulePos, "7-Day Operations Schedule must follow Occupancy Heatmap{$suffix}.");
        $firstHeatmapPos = strpos($body, 'Occupancy Heatmap');
        $this->assertGreaterThan($gridPos, $firstHeatmapPos, "Occupancy Heatmap must not appear outside or before the two-column grid{$suffix}.");
        $this->assertLessThan($schedulePos, $heatmapPos, "Occupancy Heatmap must precede 7-Day Operations Schedule inside operations stream{$suffix}.");
    }

    private function createSampleHubData(string $today): DashboardHubViewData
    {
        return new DashboardHubViewData(
            selectedPropertyFilter: 'all',
            alerts: [
                new OperationalAlert(
                    id: 'alert_reg_1',
                    type: AlertType::INCOMPLETE_GUEST_REGISTRY,
                    severity: AlertSeverity::CRITICAL,
                    propertyId: '1606',
                    title: 'Guest Registration Incomplete: Check-in Today',
                    description: 'Arrival is scheduled for today but guest details are missing.',
                    dueDate: $today,
                    reservationUid: 'res_test_123',
                    channelBlockUid: null,
                    guestName: 'Alice Springs',
                    actionPayload: [
                        'registryUrl' => 'https://oceanviewflats.com/guest/register/tok_123',
                    ]
                ),
                new OperationalAlert(
                    id: 'alert_ical_1',
                    type: AlertType::UNONBOARDED_CHANNEL_BLOCK,
                    severity: AlertSeverity::WARNING,
                    propertyId: '1707',
                    title: 'Un-onboarded Airbnb Hold: Oct 10 - Oct 14',
                    description: 'External iCal hold detected without reservation.',
                    dueDate: '2026-10-10',
                    reservationUid: null,
                    channelBlockUid: 'ical_airbnb_999',
                    guestName: null,
                    actionPayload: [
                        'startDate' => '2026-10-10',
                        'endDate' => '2026-10-14',
                        'source' => 'airbnb',
                    ]
                ),
            ],
            scheduleByDate: [
                $today => [
                    new OperationsEvent(
                        date: $today,
                        movementType: MovementType::TURNOVER,
                        propertyId: '1606',
                        reservationUid: 'res_in_1606',
                        guestName: 'Bob Builder',
                        guestPhone: '+573001234567',
                        status: 'confirmed',
                        registryCompleted: false,
                        doorCode: null,
                        source: 'direct',
                        externalConfirmationCode: null,
                        departingReservationUid: 'res_out_1606',
                        departingGuestName: 'Charlie Brown'
                    ),
                    new OperationsEvent(
                        date: $today,
                        movementType: MovementType::CHECK_OUT,
                        propertyId: '1707',
                        reservationUid: 'res_out_1707',
                        guestName: 'David Copperfield',
                        guestPhone: '+573007654321',
                        status: 'confirmed',
                        registryCompleted: true,
                        doorCode: '4455',
                        source: 'airbnb',
                        externalConfirmationCode: 'HM998877',
                        departingReservationUid: null,
                        departingGuestName: null
                    ),
                ]
            ],
            rateStatus: [
                '1606' => new PropertyRateStatus(
                    propertyId: '1606',
                    currentNightlyRate: 350000,
                    isSeasonalTierActive: true,
                    activeTierName: 'High Season',
                    activeTierEndDate: '2026-10-31',
                    nextTierName: 'Standard Low',
                    nextTierRate: 280000,
                    nextTierStartDate: '2026-11-01'
                ),
                '1707' => new PropertyRateStatus(
                    propertyId: '1707',
                    currentNightlyRate: 420000,
                    isSeasonalTierActive: false,
                    activeTierName: null,
                    activeTierEndDate: null,
                    nextTierName: null,
                    nextTierRate: null,
                    nextTierStartDate: null
                ),
            ],
            upcomingMaintenanceBlocks: [
                new MaintenanceBlock(
                    propertyId: '1606',
                    startDate: '2026-10-20',
                    endDate: '2026-10-22',
                    reason: 'Deep AC Maintenance',
                    id: 1,
                    createdBy: 1,
                    createdByName: 'Admin Manager',
                    createdAt: new \DateTimeImmutable('2026-10-01 00:00:00')
                )
            ],
            channelSyncData: [],
            todayArrivalsCount: 1,
            todayDeparturesCount: 1,
            activeStaysCount: 2
        );
    }

    public function testDayDrawerReturns400WhenDateMissingOrInvalid(): void
    {
        $session = ['admin_user_id' => 1];

        // 1. Missing date
        $requestNoDate = new Request('GET', '/dashboard/day');
        $responseNoDate = $this->controller->dayDrawer($requestNoDate, $session);
        $this->assertSame(400, $responseNoDate->getStatusCode());
        $this->assertStringContainsString('Invalid or missing date parameter', $responseNoDate->getBody());

        // 2. Malformed date
        $requestBadFormat = new Request('GET', '/dashboard/day', ['date' => 'invalid-date']);
        $responseBadFormat = $this->controller->dayDrawer($requestBadFormat, $session);
        $this->assertSame(400, $responseBadFormat->getStatusCode());
        $this->assertStringContainsString('Invalid or missing date parameter', $responseBadFormat->getBody());

        // 3. Invalid calendar day (service throws InvalidArgumentException)
        $queryService = $this->createMock(DashboardQueryServiceInterface::class);
        $queryService->method('getDaySummary')
            ->willThrowException(new \InvalidArgumentException('Invalid calendar date: 2026-02-30.'));

        $controllerWithMock = new DashboardController(
            viewRenderer: $this->viewRenderer,
            syncService: null,
            dashboardQueryService: $queryService
        );

        $requestInvalidDay = new Request('GET', '/dashboard/day', ['date' => '2026-02-30']);
        $responseInvalidDay = $controllerWithMock->dayDrawer($requestInvalidDay, $session);
        $this->assertSame(400, $responseInvalidDay->getStatusCode());
        $this->assertStringContainsString('Invalid calendar date: 2026-02-30', $responseInvalidDay->getBody());
    }

    public function testDayDrawerRendersDrawerPartialSuccessfully(): void
    {
        $detail1606 = new DayPropertyDetail(
            propertyId: '1606',
            propertyName: 'Property 1606',
            status: 'Occupied',
            isOccupied: true,
            nightlyRate: 350000,
            rateInfo: ['amount' => 350000, 'isSeasonal' => true, 'tierName' => 'High Season'],
            activeReservation: [
                'reservation_uid' => 'res_1606_active',
                'guest_name' => 'John Doe',
                'guest_email' => 'john@test.com',
                'guest_phone' => '+15551234',
                'total_price' => 700000,
                'door_code' => '1234#',
                'registry_completed' => 1,
                'clearance_status' => 'synced',
                'clearance_number' => 'CLEAR-1234',
            ],
            departingReservation: null,
            arrivingReservation: null,
            maintenanceBlock: null,
            movements: [],
            warnings: []
        );

        $detail1707 = new DayPropertyDetail(
            propertyId: '1707',
            propertyName: 'Property 1707',
            status: 'Vacant',
            isOccupied: false,
            nightlyRate: 120,
            rateInfo: ['amount' => 120, 'isSeasonal' => false, 'tierName' => 'Standard Rate'],
            activeReservation: null,
            departingReservation: null,
            arrivingReservation: null,
            maintenanceBlock: null,
            movements: [],
            warnings: []
        );

        $daySummary = new DayDetailViewData(
            date: '2026-10-15',
            formattedDate: 'Thursday, Oct 15, 2026',
            dayOfWeekName: 'Thursday',
            propertyFilter: 'all',
            totalUnits: 2,
            occupiedUnits: 1,
            capacityLevel: 'partial',
            isToday: false,
            isPast: false,
            hasTurnover: false,
            hasMaintenance: false,
            hasWarning: false,
            combinedRate: 350120,
            properties: ['1606' => $detail1606, '1707' => $detail1707],
            movements: [],
            maintenanceBlocks: [],
            warnings: [],
            reservationUids: ['res_1606_active']
        );

        $queryService = $this->createMock(DashboardQueryServiceInterface::class);
        $queryService->expects($this->once())
            ->method('getDaySummary')
            ->with('2026-10-15', 'all')
            ->willReturn($daySummary);

        $controller = new DashboardController(
            viewRenderer: $this->viewRenderer,
            syncService: null,
            dashboardQueryService: $queryService
        );

        $session = ['admin_user_id' => 1, 'csrf_token' => 'drawer_csrf_xyz'];
        $request = new Request('GET', '/dashboard/day', ['date' => '2026-10-15']);
        $response = $controller->dayDrawer($request, $session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('id="day-summary-drawer"', $body);
        $this->assertStringContainsString('Thursday, Oct 15, 2026', $body);
        $this->assertStringContainsString('Property 1606', $body);
        $this->assertStringContainsString('Property 1707', $body);
        $this->assertStringContainsString('John Doe', $body);
        $this->assertStringContainsString('res_1606_active', $body);
        $this->assertStringContainsString('1234#', $body);
        $this->assertStringContainsString('CLEAR-1234', $body);
        $this->assertStringContainsString('hx-get="/reservations/new?check_in=2026-10-15&property_id=1707"', $body);
    }

    public function testHeatmapCellsRenderAdaptiveClickTargets(): void
    {
        $vacantCell = new HeatmapDayCell(
            date: '2026-10-20',
            dayOfMonth: 20,
            dayOfWeek: 2,
            isToday: false,
            isPast: false,
            totalUnits: 1,
            occupiedUnits: 0,
            propertyFilter: '1606',
            reservationUids: []
        );

        $singleResCell = new HeatmapDayCell(
            date: '2026-10-21',
            dayOfMonth: 21,
            dayOfWeek: 3,
            isToday: false,
            isPast: false,
            totalUnits: 1,
            occupiedUnits: 1,
            propertyFilter: '1606',
            singleReservationUid: 'res_solo_123',
            reservationUids: ['res_solo_123']
        );

        $multiStayCell = new HeatmapDayCell(
            date: '2026-10-22',
            dayOfMonth: 22,
            dayOfWeek: 4,
            isToday: false,
            isPast: false,
            totalUnits: 2,
            occupiedUnits: 2,
            propertyFilter: 'all',
            reservationUids: ['res_1', 'res_2']
        );

        $weekCol = new HeatmapWeekColumn(
            weekNumber: 1,
            days: [null, $vacantCell, $singleResCell, $multiStayCell, null, null, null],
            monthLabel: 'Oct'
        );

        $heatmap = new OccupancyHeatmapViewData(
            startDate: '2026-10-19',
            endDate: '2026-10-25',
            propertyFilter: 'all',
            columns: [$weekCol],
            monthHeaders: [['label' => 'Oct', 'startCol' => 0, 'span' => 1]],
            overallOccupancyPercentage: 66.7,
            totalDaysCount: 3,
            totalOccupiedDaysCount: 2
        );

        $hubData = new DashboardHubViewData(
            selectedPropertyFilter: 'all',
            alerts: [],
            scheduleByDate: [],
            rateStatus: [],
            upcomingMaintenanceBlocks: [],
            channelSyncData: [],
            todayArrivalsCount: 0,
            todayDeparturesCount: 0,
            activeStaysCount: 0,
            heatmap: $heatmap
        );

        $queryService = $this->createMock(DashboardQueryServiceInterface::class);
        $queryService->expects($this->once())
            ->method('getDashboardHubData')
            ->with('all')
            ->willReturn($hubData);

        $controller = new DashboardController(
            viewRenderer: $this->viewRenderer,
            syncService: null,
            dashboardQueryService: $queryService
        );

        $session = ['admin_user_id' => 1];
        $request = new Request('GET', '/dashboard/hub');

        $response = $controller->hub($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // 1. Vacant cell button target
        $this->assertStringContainsString('data-date="2026-10-20"', $body);
        $this->assertStringContainsString('hx-get="/reservations/new?check_in=2026-10-20&property_id=1606"', $body);
        $this->assertStringContainsString('hx-target="#modal-container"', $body);

        // 2. Single reservation cell button target
        $this->assertStringContainsString('data-date="2026-10-21"', $body);
        $this->assertStringContainsString('hx-get="/reservations/res_solo_123"', $body);

        // 3. Multi-stay / day drawer button target
        $this->assertStringContainsString('data-date="2026-10-22"', $body);
        $this->assertStringContainsString('hx-get="/dashboard/day?date=2026-10-22&property_id=all"', $body);
        $this->assertStringContainsString('hx-target="#drawer-container"', $body);

        // 4. Rendered as HTML button
        $this->assertStringContainsString('<button type="button"', $body);
        $this->assertStringContainsString('data-heatmap-cell="true"', $body);
    }

    public function testAllOperationalAlertTypesRenderDismissButtonTargetingHubContent(): void
    {
        $today = date('Y-m-d');
        $hubData = new DashboardHubViewData(
            selectedPropertyFilter: '1606',
            alerts: [
                new OperationalAlert(
                    id: 'clearance_res_fail_1',
                    type: AlertType::FAILED_CONDOMINIUM_CLEARANCE,
                    severity: AlertSeverity::CRITICAL,
                    propertyId: '1606',
                    title: 'Condominium Clearance Failed',
                    description: 'Portal error',
                    dueDate: $today,
                    reservationUid: 'res_fail_1',
                    guestName: 'Clearance Guest'
                ),
                new OperationalAlert(
                    id: 'registry_res_reg_1',
                    type: AlertType::INCOMPLETE_GUEST_REGISTRY,
                    severity: AlertSeverity::WARNING,
                    propertyId: '1606',
                    title: 'Guest Registry Required',
                    description: 'Missing registry',
                    dueDate: $today,
                    reservationUid: 'res_reg_1',
                    guestName: 'Registry Guest'
                ),
                new OperationalAlert(
                    id: 'channel_block_abc123def456',
                    type: AlertType::UNONBOARDED_CHANNEL_BLOCK,
                    severity: AlertSeverity::INFO,
                    propertyId: '1606',
                    title: 'Un-onboarded Airbnb Booking',
                    description: 'External hold',
                    dueDate: $today,
                    channelBlockUid: 'abc123def456',
                    source: 'airbnb'
                ),
            ],
            scheduleByDate: [],
            rateStatus: [],
            upcomingMaintenanceBlocks: [],
            channelSyncData: [],
            todayArrivalsCount: 0,
            todayDeparturesCount: 0,
            activeStaysCount: 0
        );

        $queryService = $this->createMock(DashboardQueryServiceInterface::class);
        $queryService->expects($this->once())
            ->method('getDashboardHubData')
            ->with('1606')
            ->willReturn($hubData);

        $controller = new DashboardController(
            viewRenderer: $this->viewRenderer,
            syncService: null,
            dashboardQueryService: $queryService
        );

        $session = ['admin_user_id' => 1, 'csrf_token' => 'csrf_tok_dismiss'];
        $request = new Request('GET', '/', query: ['property_id' => '1606']);

        $response = $controller->index($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('hx-post="/dashboard/alerts/dismiss"', $body);
        $this->assertStringContainsString('hx-target="#dashboard-hub-content"', $body);
        $this->assertStringContainsString('name="alert_id" value="clearance_res_fail_1"', $body);
        $this->assertStringContainsString('name="alert_id" value="registry_res_reg_1"', $body);
        $this->assertStringContainsString('name="alert_id" value="channel_block_abc123def456"', $body);
        $this->assertStringContainsString('name="filter_property_id" value="1606"', $body);
        $this->assertSame(3, substr_count($body, "Dismiss\n"));
    }

    public function testDismissAlertValidatesParametersPersistsLogsAuditAndRendersHub(): void
    {
        $pdo = \OceanViewFlats\Admin\Tests\Support\AdminDatabaseTestHelper::createDatabaseWithDefaultAdmin();
        $dismissalRepo = new \OceanViewFlats\Domain\Reservation\Dashboard\PdoOperationalAlertDismissalRepository($pdo);
        $auditLogger = new \OceanViewFlats\Admin\Audit\AuditLogger($pdo);

        $emptyHubData = new DashboardHubViewData(
            selectedPropertyFilter: '1606',
            alerts: [],
            scheduleByDate: [],
            rateStatus: [],
            upcomingMaintenanceBlocks: [],
            channelSyncData: [],
            todayArrivalsCount: 0,
            todayDeparturesCount: 0,
            activeStaysCount: 0
        );

        $queryService = $this->createMock(DashboardQueryServiceInterface::class);
        $queryService->expects($this->once())
            ->method('getDashboardHubData')
            ->with('1606')
            ->willReturn($emptyHubData);

        $controller = new DashboardController(
            viewRenderer: $this->viewRenderer,
            syncService: null,
            dashboardQueryService: $queryService,
            dismissalRepo: $dismissalRepo,
            auditLogger: $auditLogger
        );

        $session = ['admin_user_id' => 1, 'csrf_token' => 'csrf_tok'];

        // 1. Invalid parameters return 400
        $badReq = new Request('POST', '/dashboard/alerts/dismiss', [], [
            'alert_id' => 'invalid_prefix_123',
            'alert_type' => 'failed_condominium_clearance',
            'property_id' => '1606',
        ]);
        $badResp = $controller->dismissAlert($badReq, $session);
        $this->assertSame(400, $badResp->getStatusCode());

        // 2. Valid HTMX request persists dismissal, logs audit, and returns partial scoped to filter_property_id
        $htmxReq = new Request(
            method: 'POST',
            uri: '/dashboard/alerts/dismiss',
            query: [],
            post: [
                'alert_id' => 'clearance_res_999',
                'alert_type' => 'failed_condominium_clearance',
                'property_id' => '1606',
                'reservation_uid' => 'res_999',
                'filter_property_id' => '1606',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'REMOTE_ADDR' => '10.0.0.42',
                'HTTP_USER_AGENT' => 'PHPUnit-Agent/1.0',
            ]
        );
        $htmxResp = $controller->dismissAlert($htmxReq, $session);
        $this->assertSame(200, $htmxResp->getStatusCode());
        $this->assertStringContainsString('id="dashboard-hub-content"', $htmxResp->getBody());
        $this->assertStringContainsString('All Operations Clear', $htmxResp->getBody());
        $this->assertTrue($dismissalRepo->isDismissed('clearance_res_999'));

        $auditLogs = \OceanViewFlats\Admin\Audit\AuditLogger::getLogsForEntity($pdo, 'reservation', 'res_999');
        $this->assertCount(1, $auditLogs);
        $this->assertSame('alert_dismissed', $auditLogs[0]['action']);
        $this->assertSame(1, (int) $auditLogs[0]['admin_user_id']);
        $afterPayload = json_decode((string) $auditLogs[0]['payload_after'], true);
        $this->assertSame('clearance_res_999', $afterPayload['alert_id']);
        $this->assertSame('failed_condominium_clearance', $afterPayload['alert_type']);
        $this->assertSame('1606', $afterPayload['property_id']);
        $this->assertSame('res_999', $afterPayload['reservation_uid']);

        // 3. Non-HTMX request redirects while preserving filter_property_id
        $nonHtmxReq = new Request(
            method: 'POST',
            uri: '/dashboard/alerts/dismiss',
            query: [],
            post: [
                'alert_id' => 'channel_block_hash123',
                'alert_type' => 'unonboarded_channel_block',
                'property_id' => '1707',
                'filter_property_id' => '1707',
            ]
        );
        $redirectResp = $controller->dismissAlert($nonHtmxReq, $session);
        $this->assertSame(302, $redirectResp->getStatusCode());
        $this->assertSame('/?property_id=1707', $redirectResp->getHeader('Location'));

        // 4. return_context=drawer renders _detail_drawer.php with Dismissed badge & HX-Trigger on HTMX, or redirects to /reservations/{uid} on non-HTMX
        $pdo->exec("
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, registry_completed
            ) VALUES (
                'res_drawer_999', '1606', 'Drawer Guest', 'drawer@test.com', '+573001112233',
                '2026-11-01', '2026-11-05', 900000.00, 'confirmed', 'web', 1
            );
            INSERT INTO condominium_clearances (
                reservation_uid, property_id, status, error_message, attempts
            ) VALUES (
                'res_drawer_999', '1606', 'failed', 'Portal connection reset', 2
            );
        ");
        $searchAdapter = new \OceanViewFlats\Domain\Reservation\Search\PdoReservationSearchAdapter($pdo);
        $clearanceRepo = new \OceanViewFlats\Domain\Fulfillment\PdoCondominiumClearanceRepository($pdo);
        $drawerController = new DashboardController(
            viewRenderer: $this->viewRenderer,
            syncService: null,
            dashboardQueryService: $queryService,
            dismissalRepo: $dismissalRepo,
            auditLogger: $auditLogger,
            reservationSearch: $searchAdapter,
            clearanceRepo: $clearanceRepo
        );

        $drawerHtmxReq = new Request(
            method: 'POST',
            uri: '/dashboard/alerts/dismiss',
            query: [],
            post: [
                'return_context' => 'drawer',
                'alert_id' => 'clearance_res_drawer_999',
                'alert_type' => 'failed_condominium_clearance',
                'property_id' => '1606',
                'reservation_uid' => 'res_drawer_999',
            ],
            server: ['HTTP_HX_REQUEST' => 'true']
        );
        $drawerHtmxResp = $drawerController->dismissAlert($drawerHtmxReq, $session);
        $this->assertSame(200, $drawerHtmxResp->getStatusCode());
        $this->assertSame('reservationUpdated', $drawerHtmxResp->getHeader('HX-Trigger'));
        $drawerBody = $drawerHtmxResp->getBody();
        $this->assertStringContainsString('Drawer Guest', $drawerBody);
        $this->assertStringContainsString('Dismissed', $drawerBody);
        $this->assertStringContainsString('Portal connection reset', $drawerBody);
        $this->assertStringContainsString('Retry Condominium Clearance', $drawerBody);
        $this->assertStringNotContainsString('Dismiss Alert', $drawerBody);

        $drawerNonHtmxReq = new Request(
            method: 'POST',
            uri: '/dashboard/alerts/dismiss',
            query: [],
            post: [
                'return_context' => 'drawer',
                'alert_id' => 'clearance_res_drawer_999',
                'alert_type' => 'failed_condominium_clearance',
                'property_id' => '1606',
                'reservation_uid' => 'res_drawer_999',
            ]
        );
        $drawerRedirectResp = $drawerController->dismissAlert($drawerNonHtmxReq, $session);
        $this->assertSame(302, $drawerRedirectResp->getStatusCode());
        $this->assertSame('/reservations/res_drawer_999', $drawerRedirectResp->getHeader('Location'));
    }

    public function testDashboardHubRendersAndDismissesPendingParafiscalSettlementAlert(): void
    {
        $pdo = \OceanViewFlats\Admin\Tests\Support\AdminDatabaseTestHelper::createDatabaseWithDefaultAdmin();
        $dismissalRepo = new \OceanViewFlats\Domain\Reservation\Dashboard\PdoOperationalAlertDismissalRepository($pdo);
        $auditLogger = new \OceanViewFlats\Admin\Audit\AuditLogger($pdo);

        $parafiscalAlert = new OperationalAlert(
            id: 'parafiscal_2026-Q3_1606',
            type: AlertType::PENDING_PARAFISCAL_SETTLEMENT,
            severity: AlertSeverity::WARNING,
            propertyId: '1606',
            title: 'Pending FONTUR Parafiscal Declaration: 2026-Q3 (Property 1606 · RNT #270413)',
            description: 'Quarter 2026-Q3 has closed for Property 1606 (RNT #270413). Statutory payment deadline is 2026-10-29.',
            dueDate: '2026-10-29',
            actionPayload: [
                'period_key' => '2026-Q3',
                'property_id' => '1606',
                'rnt_number' => '270413',
                'taxable_base' => 4000000.0,
                'calculated_amount' => 10000.0,
                'due_date' => '2026-10-29',
                'target_url' => '/financial-report#fontur-parafiscal-section',
            ]
        );

        $hubWithAlert = new DashboardHubViewData(
            selectedPropertyFilter: '1606',
            alerts: [$parafiscalAlert],
            scheduleByDate: [],
            rateStatus: [],
            upcomingMaintenanceBlocks: [],
            channelSyncData: [],
            todayArrivalsCount: 0,
            todayDeparturesCount: 0,
            activeStaysCount: 0
        );

        $emptyHub = new DashboardHubViewData(
            selectedPropertyFilter: '1606',
            alerts: [],
            scheduleByDate: [],
            rateStatus: [],
            upcomingMaintenanceBlocks: [],
            channelSyncData: [],
            todayArrivalsCount: 0,
            todayDeparturesCount: 0,
            activeStaysCount: 0
        );

        $queryService = $this->createMock(DashboardQueryServiceInterface::class);
        $queryService->expects($this->exactly(2))
            ->method('getDashboardHubData')
            ->with('1606')
            ->willReturnOnConsecutiveCalls($hubWithAlert, $emptyHub);

        $controller = new DashboardController(
            viewRenderer: $this->viewRenderer,
            syncService: null,
            dashboardQueryService: $queryService,
            dismissalRepo: $dismissalRepo,
            auditLogger: $auditLogger
        );

        $session = ['admin_user_id' => 1, 'csrf_token' => 'csrf_pf_tok'];

        // 1. Render Dashboard Hub with Parafiscal alert
        $getResp = $controller->index(new Request('GET', '/', query: ['property_id' => '1606']), $session);
        $this->assertSame(200, $getResp->getStatusCode());
        $body = $getResp->getBody();
        $this->assertStringContainsString('Pending FONTUR Parafiscal Declaration: 2026-Q3 (Property 1606 · RNT #270413)', $body);
        $this->assertStringContainsString('href="/financial-report#fontur-parafiscal-section"', $body);
        $this->assertStringContainsString('Review &amp; Settle in Financial Report &rarr;', $body);
        $this->assertStringContainsString('name="alert_id" value="parafiscal_2026-Q3_1606"', $body);
        $this->assertStringContainsString('name="alert_type" value="pending_parafiscal_settlement"', $body);

        // 2. Dismiss the Parafiscal alert via POST /dashboard/alerts/dismiss
        $dismissReq = new Request(
            method: 'POST',
            uri: '/dashboard/alerts/dismiss',
            query: [],
            post: [
                'alert_id' => 'parafiscal_2026-Q3_1606',
                'alert_type' => 'pending_parafiscal_settlement',
                'property_id' => '1606',
                'filter_property_id' => '1606',
            ],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'REMOTE_ADDR' => '10.0.0.55',
            ]
        );
        $dismissResp = $controller->dismissAlert($dismissReq, $session);
        $this->assertSame(200, $dismissResp->getStatusCode());
        $this->assertTrue($dismissalRepo->isDismissed('parafiscal_2026-Q3_1606'));

        $auditLogs = \OceanViewFlats\Admin\Audit\AuditLogger::getLogsForEntity($pdo, 'parafiscal_settlement', 'parafiscal_2026-Q3_1606');
        $this->assertCount(1, $auditLogs);
        $this->assertSame('alert_dismissed', $auditLogs[0]['action']);
        $after = json_decode((string) $auditLogs[0]['payload_after'], true);
        $this->assertSame('parafiscal_2026-Q3_1606', $after['alert_id']);
        $this->assertSame('pending_parafiscal_settlement', $after['alert_type']);
        $this->assertSame('1606', $after['property_id']);
    }
}
