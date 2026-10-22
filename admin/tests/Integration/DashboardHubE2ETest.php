<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use OceanViewFlats\Admin\AdminApp;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Tests\Support\AdminDatabaseTestHelper;
use OceanViewFlats\Domain\Fulfillment\CondominiumClearance;
use OceanViewFlats\Domain\Fulfillment\PdoCondominiumClearanceRepository;
use OceanViewFlats\Domain\Reservation\ChannelBlock;
use OceanViewFlats\Domain\Reservation\ChannelSyncStatus;
use OceanViewFlats\Domain\Reservation\InboundChannelSyncServiceInterface;
use OceanViewFlats\Domain\Reservation\ReservationLedgerInterface;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end integration test suite verifying the complete Operational Dashboard Hub:
 * 1. Authentication & Security gating (unauthenticated redirects to /login).
 * 2. Full application kernel execution via AdminApp::createDefault().
 * 3. Seeded operational scenario:
 *    - In-house active stays
 *    - Same-day turnaround (check-out + check-in on same date & unit)
 *    - Imminent incomplete guest registries (today & tomorrow)
 *    - Un-onboarded external OTA channel holds
 *    - Real-time rates & seasonal tier transitions
 *    - Upcoming maintenance holds
 * 4. Multi-tenant Property Filter HTMX partial switching (all, 1606, 1707).
 * 5. Full page fallback for direct browser navigation.
 * 6. Quick action triggers (modals, drawers, clipboard copy).
 */
final class DashboardHubE2ETest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = AdminDatabaseTestHelper::createDatabaseWithDefaultAdmin();
    }

    public function testUnauthenticatedAccessRedirectsToLogin(): void
    {
        $app = AdminApp::createDefault($this->pdo);
        $emptySession = [];

        // 1. GET /
        $requestRoot = new Request('GET', '/');
        $responseRoot = $app->handle($requestRoot, $emptySession);
        $this->assertSame(302, $responseRoot->getStatusCode());
        $this->assertSame('/login', $responseRoot->getHeader('Location'));

        // 2. GET /dashboard/hub
        $requestHub = new Request('GET', '/dashboard/hub');
        $responseHub = $app->handle($requestHub, $emptySession);
        $this->assertSame(302, $responseHub->getStatusCode());
        $this->assertSame('/login', $responseHub->getHeader('Location'));
    }

    public function testAuthenticatedFullDashboardHubRenderWithOperationalData(): void
    {
        $this->seedOperationalDatabase();

        $app = $this->createConfiguredApp();

        $session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'admin_user_email' => 'admin@oceanviewflats.com',
            'admin_user_role' => 'admin',
            'csrf_token' => 'secure_csrf_token_hub_test',
        ];

        $request = new Request('GET', '/');
        $response = $app->handle($request, $session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // 1. Master Layout & Shell
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('Manuel Admin', $body);
        $this->assertStringContainsString('(admin)', $body);
        $this->assertStringNotContainsString('Welcome back', $body);
        $this->assertStringNotContainsString('Reservations & Calendar', $body);
        $this->assertStringNotContainsString('Smart Lock Status', $body);
        $this->assertStringContainsString('id="mobile-menu-button"', $body);
        $this->assertStringContainsString('id="mobile-menu"', $body);
        $this->assertStringContainsString('href="/reservations"', $body);
        $this->assertStringContainsString('href="/rates"', $body);
        $this->assertStringContainsString('href="/calendar-blocks"', $body);

        // 2. Container Insertion Anchors for HTMX modals & drawers
        $this->assertStringContainsString('<div id="modal-container"></div>', $body);
        $this->assertStringContainsString('<div id="drawer-container"></div>', $body);
        $this->assertStringContainsString('id="dashboard-hub-content"', $body);

        // 3. Segmented Property Filter Pills
        $this->assertStringContainsString('role="button"', $body);
        $this->assertStringContainsString('hx-get="/dashboard/hub?property_id=all"', $body);
        $this->assertStringContainsString('hx-get="/dashboard/hub?property_id=1606"', $body);
        $this->assertStringContainsString('hx-get="/dashboard/hub?property_id=1707"', $body);
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>[\s\n]*All Properties/s', $body);

        // 4. Summary Metric Cards
        $this->assertStringContainsString("Today's Arrivals", $body);
        $this->assertStringContainsString("Today's Departures", $body);
        $this->assertStringContainsString('In-House Stays', $body);
        $this->assertStringContainsString('Operational Alerts', $body);

        // 5. 7-Day Operations Schedule & Same-Day Turnover Detection
        $this->assertStringContainsString('7-Day Operations Horizon', $body);
        $this->assertStringContainsString('Same-Day Turnover (Apartment 1606)', $body);
        $this->assertStringContainsString('Alice Departing', $body);
        $this->assertStringContainsString('Bob Arriving', $body);
        $this->assertStringContainsString('Charlie Pending', $body);
        $this->assertStringContainsString('4h Window', $body);
        $this->assertStringContainsString('IN ⚡', $body);

        // Horizon Boundary check: Far future reservation (+8 days) must NOT appear in 7-day feed
        $scheduleFeedHtml = strstr($body, '7-Day Operations Horizon') ?: '';
        $scheduleFeedHtml = strstr($scheduleFeedHtml, 'Operational Attention', true) ?: $scheduleFeedHtml;
        $this->assertStringNotContainsString('Far Future Guest', $scheduleFeedHtml);

        // 6. Prioritized Operational Alerts Card
        $this->assertStringContainsString('Operational Attention', $body);
        $this->assertStringContainsString('Check-in Today: Missing Guest Registry (Bob Arriving)', $body);
        $this->assertStringContainsString('Check-in Tomorrow: Guest Registry Required (Charlie Pending)', $body);
        $this->assertStringContainsString('Un-onboarded Airbnb Booking', $body);
        $this->assertStringContainsString('CRITICAL', $body);
        $this->assertStringContainsString('WARNING', $body);

        // Quick action buttons in alerts
        $this->assertStringContainsString('Copy Invite', $body);
        $this->assertStringContainsString('hx-target="#drawer-container"', $body);
        $this->assertStringContainsString('Onboard Airbnb Guest', $body);
        $this->assertStringContainsString('hx-target="#modal-container"', $body);

        // 7. Rates & Maintenance Blocks Card
        $this->assertStringContainsString("Today's Effective Rates", $body);
        $this->assertStringContainsString('Seasonal: Festive Peak', $body);
        $this->assertStringContainsString('$600.000 COP', $body);
        $this->assertStringContainsString('$350.000 COP', $body); // Apt 1606 base rate
        $this->assertStringContainsString('Upcoming Calendar Holds', $body);
        $this->assertStringContainsString('Plumbing Inspection', $body);
        $this->assertStringContainsString('+ Add Hold', $body);

        // 8. Integrated Channel Card
        $this->assertStringContainsString('Channel Feeds Active', $body);
        $this->assertStringContainsString('id="channel-card-container"', $body);
        $this->assertStringContainsString('Sync Now', $body);
    }

    public function testHtmxPartialSwitchingScopedToProperty1606(): void
    {
        $this->seedOperationalDatabase();
        $app = $this->createConfiguredApp();

        $session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'csrf_token' => 'csrf_token_test',
        ];

        // HTMX request targeting #dashboard-hub-content with property_id=1606
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

        $response = $app->handle($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // 1. Must be a clean partial without full HTML page wrapper
        $this->assertStringNotContainsString('<!DOCTYPE html>', $body);
        $this->assertStringNotContainsString('Welcome back, Manuel Admin', $body);
        $this->assertStringNotContainsString('Sign Out', $body);

        // 2. Starts with #dashboard-hub-content container
        $this->assertStringContainsString('id="dashboard-hub-content"', $body);

        // 3. Apartment 1606 pill is marked active
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>[\s\n]*Apartment 1606/s', $body);

        // 4. Scoped to Apartment 1606: contains turnover and Bob Arriving
        $this->assertStringContainsString('Same-Day Turnover (Apartment 1606)', $body);
        $this->assertStringContainsString('Bob Arriving', $body);
        $this->assertStringContainsString('Alice Departing', $body);

        // Must NOT contain Apt 1707 specific events or alerts
        $this->assertStringNotContainsString('Charlie Pending', $body);
        $this->assertStringNotContainsString('Un-onboarded Airbnb Booking', $body);

        // Rates scoped to Apt 1606
        $this->assertStringContainsString('$350.000 COP', $body);
        $this->assertStringNotContainsString('Seasonal: Festive Peak', $body);
    }

    public function testHtmxPartialSwitchingScopedToProperty1707(): void
    {
        $this->seedOperationalDatabase();
        $app = $this->createConfiguredApp();

        $session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'csrf_token' => 'csrf_token_test',
        ];

        // HTMX request targeting #dashboard-hub-content with property_id=1707
        $request = new Request(
            method: 'GET',
            uri: '/dashboard/hub',
            query: ['property_id' => '1707'],
            post: [],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_TARGET' => 'dashboard-hub-content',
            ]
        );

        $response = $app->handle($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Must be a partial
        $this->assertStringNotContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('id="dashboard-hub-content"', $body);

        // Apartment 1707 pill is marked active
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>[\s\n]*Apartment 1707/s', $body);

        // Scoped to Apartment 1707
        $this->assertStringContainsString('Charlie Pending', $body);
        $this->assertStringContainsString('Un-onboarded Airbnb Booking', $body);
        $this->assertStringContainsString('Seasonal: Festive Peak', $body);
        $this->assertStringContainsString('$600.000 COP', $body);

        // Must NOT contain Apt 1606 events or holds
        $this->assertStringNotContainsString('Same-Day Turnover', $body);
        $this->assertStringNotContainsString('Alice Departing', $body);
        $this->assertStringNotContainsString('Plumbing Inspection', $body);
    }

    public function testDirectBrowserNavigationToHubFallsBackToFullLayout(): void
    {
        $this->seedOperationalDatabase();
        $app = $this->createConfiguredApp();

        $session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'csrf_token' => 'csrf_token_test',
        ];

        // Non-HTMX request directly to /dashboard/hub (e.g. opened in new tab or bookmarked)
        $request = new Request('GET', '/dashboard/hub', query: ['property_id' => '1606']);

        $response = $app->handle($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Fallback: full page layout with shell
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('Manuel Admin', $body);
        $this->assertStringContainsString('id="dashboard-hub-content"', $body);
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>[\s\n]*Apartment 1606/s', $body);
    }

    public function testInvalidPropertyFilterFallsBackToAll(): void
    {
        $this->seedOperationalDatabase();
        $app = $this->createConfiguredApp();

        $session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'csrf_token' => 'csrf_token_test',
        ];

        // Malformed or unknown property_id should safely default to 'all'
        $request = new Request('GET', '/dashboard/hub', query: ['property_id' => 'malicious_input_9999']);

        $response = $app->handle($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Must display "All Properties" as active page
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>[\s\n]*All Properties/s', $body);
        // Contains elements from both 1606 and 1707
        $this->assertStringContainsString('Apartment 1606', $body);
        $this->assertStringContainsString('Apartment 1707', $body);
    }

    public function testIncompleteRegistryBeyondLookaheadWindowIsNotAlerted(): void
    {
        $this->seedOperationalDatabase();
        $app = $this->createConfiguredApp();

        $session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'csrf_token' => 'csrf_token_test',
        ];

        $request = new Request('GET', '/');
        $response = $app->handle($request, $session);
        $body = $response->getBody();

        // 'Far Future Alert Free' arrives in 5 days (> 3 days lookahead), must NOT appear in alerts
        $this->assertStringNotContainsString('Guest Registry Pending (Far Future Alert Free)', $body);
    }

    public function testMultiUnitTurnoversOnSameDayAreAggregatedCorrectly(): void
    {
        $today = $this->bogotaDate('today');
        $yesterday = $this->bogotaDate('-1 day');
        $inTwoDays = $this->bogotaDate('+2 days');

        // Seed simultaneous turnovers on Apt 1606 AND Apt 1707 on $today
        $stmt = $this->pdo->prepare('
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, registry_completed, door_code
            ) VALUES
            ("res_dep_1606", "1606", "Out Guest 1606", "out1606@test.com", "+571", :yesterday, :today, 100, "confirmed", "web", 1, "1606#"),
            ("res_arr_1606", "1606", "In Guest 1606", "in1606@test.com", "+572", :today, :inTwoDays, 100, "confirmed", "web", 1, "1607#"),
            ("res_dep_1707", "1707", "Out Guest 1707", "out1707@test.com", "+573", :yesterday, :today, 100, "confirmed", "web", 1, "1707#"),
            ("res_arr_1707", "1707", "In Guest 1707", "in1707@test.com", "+574", :today, :inTwoDays, 100, "confirmed", "web", 1, "1708#");
        ');
        $stmt->execute([
            ':yesterday' => $yesterday,
            ':today' => $today,
            ':inTwoDays' => $inTwoDays,
        ]);

        $app = AdminApp::createDefault($this->pdo);
        $session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'csrf_token' => 'csrf_token_test',
        ];

        $request = new Request('GET', '/');
        $response = $app->handle($request, $session);
        $body = $response->getBody();

        // Both turnover banners must be rendered
        $this->assertStringContainsString('Same-Day Turnover (Apartment 1606)', $body);
        $this->assertStringContainsString('Same-Day Turnover (Apartment 1707)', $body);
        $this->assertStringContainsString('Out Guest 1606', $body);
        $this->assertStringContainsString('In Guest 1606', $body);
        $this->assertStringContainsString('Out Guest 1707', $body);
        $this->assertStringContainsString('In Guest 1707', $body);
    }

    public function testCheckOutExpirationForClearanceAlertsHeatmapAndDaySummaryDrawer(): void
    {
        $cot = new DateTimeZone('America/Bogota');
        $todayObj = new DateTimeImmutable('today', $cot);
        $today = $todayObj->format('Y-m-d');
        $yesterday = $todayObj->modify('-1 day')->format('Y-m-d');
        $threeDaysAgo = $todayObj->modify('-3 days')->format('Y-m-d');
        $twoDaysForward = $todayObj->modify('+2 days')->format('Y-m-d');

        $stmt = $this->pdo->prepare('
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, registry_completed, door_code
            ) VALUES
            ("res_past_clearance", "1606", "Concluded Clearance Guest", "past@test.com", "+571", :threeDaysAgo, :yesterday, 400000, "confirmed", "web", 1, "1111#"),
            ("res_dep_clearance", "1707", "Departing Clearance Guest", "dep@test.com", "+572", :yesterday, :today, 400000, "confirmed", "web", 0, NULL),
            ("res_active_clearance", "1606", "Active Clearance Guest", "active@test.com", "+573", :yesterday, :twoDaysForward, 700000, "confirmed", "web", 1, "3333#");
        ');
        $stmt->execute([
            ':threeDaysAgo' => $threeDaysAgo,
            ':yesterday' => $yesterday,
            ':today' => $today,
            ':twoDaysForward' => $twoDaysForward,
        ]);

        $clearanceRepo = new PdoCondominiumClearanceRepository($this->pdo);
        $clearanceRepo->save(CondominiumClearance::createPending('res_past_clearance', '1606')->markFailed('Stale past clearance failure'));
        $clearanceRepo->save(CondominiumClearance::createPending('res_dep_clearance', '1707')->markFailed('Departing today clearance failure'));
        $clearanceRepo->save(CondominiumClearance::createPending('res_active_clearance', '1606')->markFailed('Active stay clearance failure'));

        $app = AdminApp::createDefault($this->pdo);
        $session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'csrf_token' => 'csrf_token_test',
        ];

        // 1. GET / Dashboard Hub: only Active Clearance Guest (check_out > today) appears in Operational Attention
        $hubResponse = $app->handle(new Request('GET', '/'), $session);
        $this->assertSame(200, $hubResponse->getStatusCode());
        $hubBody = $hubResponse->getBody();

        $this->assertStringContainsString('Active Clearance Guest', $hubBody);
        $this->assertStringContainsString('Active stay clearance failure', $hubBody);
        $this->assertStringNotContainsString('Stale past clearance failure', $hubBody);
        $this->assertStringNotContainsString('Departing today clearance failure', $hubBody);
        $this->assertStringNotContainsString('Condominium clearance failed (Concluded Clearance Guest)', $hubBody);
        $this->assertStringNotContainsString('Condominium clearance failed (Departing Clearance Guest)', $hubBody);

        // 2. GET /dashboard/day for yesterday (date < today): Operational Warnings banner must be suppressed
        $pastDayResponse = $app->handle(new Request('GET', '/dashboard/day', ['date' => $yesterday]), $session);
        $this->assertSame(200, $pastDayResponse->getStatusCode());
        $pastDayBody = $pastDayResponse->getBody();

        $this->assertStringNotContainsString('<span>Operational Warnings</span>', $pastDayBody);
        $this->assertStringNotContainsString('Condominium clearance failed', $pastDayBody);
        $this->assertStringNotContainsString('Guest registry incomplete', $pastDayBody);

        // 3. GET /dashboard/day for today: Departing Clearance Guest (check_out == today) does NOT emit warnings,
        //    while Active Clearance Guest (check_out > today) DOES emit warning
        $todayDayResponse = $app->handle(new Request('GET', '/dashboard/day', ['date' => $today]), $session);
        $this->assertSame(200, $todayDayResponse->getStatusCode());
        $todayDayBody = $todayDayResponse->getBody();

        $this->assertStringContainsString('<span>Operational Warnings</span>', $todayDayBody);
        $this->assertStringContainsString('Condominium clearance failed (Active Clearance Guest)', $todayDayBody);
        $this->assertStringNotContainsString('Condominium clearance failed (Departing Clearance Guest)', $todayDayBody);
        $this->assertStringNotContainsString('Guest registry incomplete (Departing Clearance Guest)', $todayDayBody);
    }

    public function testAlertDismissalEndpointEnforcesAuthAndCsrfAndDismissesAllAlertTypesEndToEnd(): void
    {
        $this->seedOperationalDatabase();

        $clearanceRepo = new PdoCondominiumClearanceRepository($this->pdo);
        $clearanceRepo->save(
            CondominiumClearance::createPending('res_inhouse_1606', '1606')
                ->markFailed('Portal HTTP 502 error')
        );

        $app = $this->createConfiguredApp();

        // 1. Unauthenticated POST to /dashboard/alerts/dismiss is rejected by AuthMiddleware (302 to /login)
        $unauthSession = ['csrf_token' => 'unauth_csrf_tok'];
        $unauthResp = $app->handle(
            new Request('POST', '/dashboard/alerts/dismiss', [], [
                'alert_id' => 'clearance_res_inhouse_1606',
                'alert_type' => 'failed_condominium_clearance',
                'property_id' => '1606',
                'csrf_token' => 'unauth_csrf_tok',
            ]),
            $unauthSession
        );
        $this->assertSame(302, $unauthResp->getStatusCode());
        $this->assertSame('/login', $unauthResp->getHeader('Location'));

        // 2. Authenticated POST with missing/invalid CSRF token is rejected with 403
        $session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'admin_user_email' => 'admin@oceanviewflats.com',
            'admin_user_role' => 'admin',
            'csrf_token' => 'valid_csrf_token_203',
        ];
        $badCsrfResp = $app->handle(
            new Request('POST', '/dashboard/alerts/dismiss', [], [
                'alert_id' => 'clearance_res_inhouse_1606',
                'alert_type' => 'failed_condominium_clearance',
                'property_id' => '1606',
                'csrf_token' => 'wrong_token',
            ]),
            $session
        );
        $this->assertSame(403, $badCsrfResp->getStatusCode());

        // Verify initial Hub state has 4 alerts:
        // - clearance_res_inhouse_1606 (1606)
        // - registry_res_arr_bob (1606)
        // - registry_res_tomo_charlie (1707)
        // - channel_block_{hash} (1707)
        $initialHub = $app->handle(new Request('GET', '/'), $session);
        $this->assertStringContainsString('4 items', $initialHub->getBody());
        $this->assertStringContainsString('Portal HTTP 502 error', $initialHub->getBody());
        $this->assertStringContainsString('Check-in Today: Missing Guest Registry (Bob Arriving)', $initialHub->getBody());

        // 3. Dismiss failed Condominium Clearance alert (clearance_res_inhouse_1606) with filter_property_id=1606
        $dismissClearanceResp = $app->handle(
            new Request(
                method: 'POST',
                uri: '/dashboard/alerts/dismiss',
                query: [],
                post: [
                    'alert_id' => 'clearance_res_inhouse_1606',
                    'alert_type' => 'failed_condominium_clearance',
                    'property_id' => '1606',
                    'reservation_uid' => 'res_inhouse_1606',
                    'filter_property_id' => '1606',
                    'csrf_token' => 'valid_csrf_token_203',
                ],
                server: ['HTTP_HX_REQUEST' => 'true']
            ),
            $session
        );
        $this->assertSame(200, $dismissClearanceResp->getStatusCode());
        $body1606 = $dismissClearanceResp->getBody();
        // Preserves 1606 filter and decrements 1606 alert count from 2 to 1
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>[\s\n]*Apartment 1606/s', $body1606);
        $this->assertStringNotContainsString('Portal HTTP 502 error', $body1606);
        $this->assertStringContainsString('1 item', $body1606);
        $this->assertStringContainsString('Check-in Today: Missing Guest Registry (Bob Arriving)', $body1606);
        // Heatmap tooltip no longer contains the dismissed clearance failure
        $this->assertStringNotContainsString('Condominium clearance failed (In-House Resident)', $body1606);

        // 4. Dismiss incomplete Guest Registry alert (registry_res_arr_bob)
        $dismissRegistryResp = $app->handle(
            new Request(
                method: 'POST',
                uri: '/dashboard/alerts/dismiss',
                query: [],
                post: [
                    'alert_id' => 'registry_res_arr_bob',
                    'alert_type' => 'incomplete_guest_registry',
                    'property_id' => '1606',
                    'reservation_uid' => 'res_arr_bob',
                    'filter_property_id' => '1606',
                    'csrf_token' => 'valid_csrf_token_203',
                ],
                server: ['HTTP_HX_REQUEST' => 'true']
            ),
            $session
        );
        $this->assertSame(200, $dismissRegistryResp->getStatusCode());
        $body1606Clear = $dismissRegistryResp->getBody();
        $this->assertStringContainsString('0 items', $body1606Clear);
        $this->assertStringContainsString('All Operations Clear', $body1606Clear);
        $this->assertStringNotContainsString('Guest registry incomplete (Bob Arriving)', $body1606Clear);

        // Day Summary Drawer for today on 1606 now has zero operational warnings
        $today = $this->bogotaDate('today');
        $dayResp = $app->handle(new Request('GET', '/dashboard/day', ['date' => $today, 'property_id' => '1606']), $session);
        $this->assertSame(200, $dayResp->getStatusCode());
        $this->assertStringNotContainsString('<span>Operational Warnings</span>', $dayResp->getBody());

        // 5. Dismiss un-onboarded Channel Block alert on 1707 and verify it stays suppressed across subsequent hub refreshes
        $tomorrow = $this->bogotaDate('+1 day');
        $inThreeDays = $this->bogotaDate('+3 days');
        $blockHash = md5("1707_{$tomorrow}_{$inThreeDays}_airbnb");
        $channelAlertId = 'channel_block_' . $blockHash;

        $dismissChannelResp = $app->handle(
            new Request(
                method: 'POST',
                uri: '/dashboard/alerts/dismiss',
                query: [],
                post: [
                    'alert_id' => $channelAlertId,
                    'alert_type' => 'unonboarded_channel_block',
                    'property_id' => '1707',
                    'filter_property_id' => 'all',
                    'csrf_token' => 'valid_csrf_token_203',
                ],
                server: ['HTTP_HX_REQUEST' => 'true']
            ),
            $session
        );
        $this->assertSame(200, $dismissChannelResp->getStatusCode());
        $bodyAll = $dismissChannelResp->getBody();
        $this->assertStringNotContainsString('Un-onboarded Airbnb Booking', $bodyAll);
        // Only Charlie Pending's registry alert remains across all properties
        $this->assertStringContainsString('1 item', $bodyAll);

        // Subsequent hub refresh still suppresses the channel block alert
        $subsequentHub = $app->handle(new Request('GET', '/dashboard/hub', ['property_id' => '1707'], [], ['HTTP_HX_REQUEST' => 'true']), $session);
        $this->assertStringNotContainsString('Un-onboarded Airbnb Booking', $subsequentHub->getBody());

        // 6. Verify Audit Log entries were written for all 3 dismissals
        $stmt = $this->pdo->query("SELECT * FROM admin_audit_logs WHERE action = 'alert_dismissed' ORDER BY id ASC");
        $auditRows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $this->assertCount(3, $auditRows);
        $this->assertSame('reservation', $auditRows[0]['entity_type']);
        $this->assertSame('res_inhouse_1606', $auditRows[0]['entity_id']);
        $this->assertSame('reservation', $auditRows[1]['entity_type']);
        $this->assertSame('res_arr_bob', $auditRows[1]['entity_id']);
        $this->assertSame('channel_block', $auditRows[2]['entity_type']);
        $this->assertSame($channelAlertId, $auditRows[2]['entity_id']);
    }

    public function testDrawerDismissalAndAutomaticReArmingOnClearanceRetryAndRegistryResetEndToEnd(): void
    {
        $this->seedOperationalDatabase();

        $today = $this->bogotaDate('today');
        $yesterday = $this->bogotaDate('-1 day');
        $inThreeDays = $this->bogotaDate('+3 days');

        // Seed guest_registries for res_inhouse_1606 and res_arr_bob
        $stmtReg = $this->pdo->prepare('
            INSERT INTO guest_registries (
                reservation_uid, property_id, check_in, check_out, guest_count, guests_payload
            ) VALUES (
                "res_inhouse_1606", "1606", :yesterday, :inThreeDays, 1,
                \'[{"index":1,"name":"In-House Resident","first_name":"In-House","last_name":"Resident","doc_type":"Passport","doc_num":"PA12345","age":35,"country":"COLOMBIA","email":"stay@test.com","phone":"+573001"}]\'
            )
        ');
        $stmtReg->execute([':yesterday' => $yesterday, ':inThreeDays' => $inThreeDays]);

        $clearanceRepo = new PdoCondominiumClearanceRepository($this->pdo);
        $clearanceRepo->save(
            CondominiumClearance::createPending('res_inhouse_1606', '1606')
                ->markFailed('Initial portal timeout')
        );

        $shouldPortalSucceed = false;
        $transport = new class($shouldPortalSucceed) implements \OceanViewFlats\Domain\Fulfillment\HttpTransportInterface {
            public function __construct(public bool &$shouldSucceed) {}

            public function post(string $url, array|string $data = [], array $headers = [], array $options = []): array
            {
                if (!$this->shouldSucceed) {
                    return [
                        'statusCode' => 502,
                        'body' => 'Bad Gateway from HOA Portal',
                        'headers' => [],
                        'cookies' => [],
                        'error' => null,
                    ];
                }

                if (str_contains($url, 'reg_guest_owner_pre.php')) {
                    return [
                        'statusCode' => 200,
                        'body' => (string) json_encode(['last_id' => 8844, 'unit_apt' => '1606']),
                        'headers' => ['content-type' => 'application/json'],
                        'cookies' => ['PHPSESSID' => 'sess-8844'],
                        'error' => null,
                    ];
                }

                return [
                    'statusCode' => 302,
                    'body' => '',
                    'headers' => ['location' => 'control_hpds.php?hpsucc=succ&book=8844'],
                    'cookies' => [],
                    'error' => null,
                ];
            }
        };

        $syncStatus1606 = new ChannelSyncStatus(
            propertyId: '1606',
            status: ChannelSyncStatus::STATUS_HEALTHY,
            lastSyncedAt: date('c'),
            lastAttemptedAt: date('c'),
            httpCode: 200,
            blockedNightsCount: 0
        );
        $syncService = $this->createMock(InboundChannelSyncServiceInterface::class);
        $syncService->method('getAllStatuses')->willReturn(['1606' => $syncStatus1606]);

        $app = AdminApp::createDefault($this->pdo, [
            'channel_sync_service' => $syncService,
            'condominium_clearance_transport' => $transport,
        ]);

        $session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'admin_user_email' => 'admin@oceanviewflats.com',
            'admin_user_role' => 'admin',
            'csrf_token' => 'csrf_token_204',
        ];

        // 1. Inspect res_inhouse_1606 in Reservation Detail Drawer -> shows Dismiss Alert and Retry buttons
        $drawerInitial = $app->handle(
            new Request('GET', '/reservations/res_inhouse_1606', server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_HX_TARGET' => 'drawer-container']),
            $session
        );
        $this->assertSame(200, $drawerInitial->getStatusCode());
        $this->assertStringContainsString('Initial portal timeout', $drawerInitial->getBody());
        $this->assertStringContainsString('Retry Condominium Clearance', $drawerInitial->getBody());
        $this->assertStringContainsString('Dismiss Alert', $drawerInitial->getBody());
        $this->assertStringNotContainsString('Dismissed', $drawerInitial->getBody());

        // 2. Dismiss alert directly from Reservation Detail Drawer (return_context=drawer)
        $drawerDismissResp = $app->handle(
            new Request(
                method: 'POST',
                uri: '/dashboard/alerts/dismiss',
                post: [
                    'csrf_token' => 'csrf_token_204',
                    'return_context' => 'drawer',
                    'alert_id' => 'clearance_res_inhouse_1606',
                    'alert_type' => 'failed_condominium_clearance',
                    'property_id' => '1606',
                    'reservation_uid' => 'res_inhouse_1606',
                ],
                server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_HX_CSRF_TOKEN' => 'csrf_token_204']
            ),
            $session
        );
        $this->assertSame(200, $drawerDismissResp->getStatusCode());
        $this->assertSame('reservationUpdated', $drawerDismissResp->getHeader('HX-Trigger'));
        $this->assertStringContainsString('Dismissed', $drawerDismissResp->getBody());
        $this->assertStringContainsString('Initial portal timeout', $drawerDismissResp->getBody());
        $this->assertStringContainsString('Retry Condominium Clearance', $drawerDismissResp->getBody());
        $this->assertStringNotContainsString('Dismiss Alert', $drawerDismissResp->getBody());

        // Verify Dashboard Hub no longer surfaces clearance_res_inhouse_1606
        $hubAfterDrawerDismiss = $app->handle(new Request('GET', '/dashboard/hub', ['property_id' => '1606'], [], ['HTTP_HX_REQUEST' => 'true']), $session);
        $this->assertStringNotContainsString('Initial portal timeout', $hubAfterDrawerDismiss->getBody());
        $this->assertStringNotContainsString('Condominium clearance failed (In-House Resident)', $hubAfterDrawerDismiss->getBody());

        // 3. Click "Retry Condominium Clearance" when portal still fails -> dismissal is cleared and alert re-arms on Hub & Heatmap
        $retryFailResp = $app->handle(
            new Request(
                method: 'POST',
                uri: '/reservations/res_inhouse_1606/clearance-retry',
                post: ['csrf_token' => 'csrf_token_204'],
                server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_HX_CSRF_TOKEN' => 'csrf_token_204']
            ),
            $session
        );
        $this->assertSame(200, $retryFailResp->getStatusCode());
        $this->assertStringNotContainsString('Dismissed', $retryFailResp->getBody());
        $this->assertStringContainsString('Dismiss Alert', $retryFailResp->getBody());

        $hubAfterFailedRetry = $app->handle(new Request('GET', '/dashboard/hub', ['property_id' => '1606'], [], ['HTTP_HX_REQUEST' => 'true']), $session);
        $this->assertStringContainsString('Condominium Clearance Failed', $hubAfterFailedRetry->getBody());
        $this->assertStringContainsString('Condominium clearance failed (In-House Resident)', $hubAfterFailedRetry->getBody());

        // 4. Dismiss again, then click "Retry Condominium Clearance" when portal succeeds -> transitions to Synced (#8844) and cleans up dismissal
        $app->handle(
            new Request(
                method: 'POST',
                uri: '/dashboard/alerts/dismiss',
                post: [
                    'csrf_token' => 'csrf_token_204',
                    'return_context' => 'drawer',
                    'alert_id' => 'clearance_res_inhouse_1606',
                    'alert_type' => 'failed_condominium_clearance',
                    'property_id' => '1606',
                    'reservation_uid' => 'res_inhouse_1606',
                ],
                server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_HX_CSRF_TOKEN' => 'csrf_token_204']
            ),
            $session
        );

        $shouldPortalSucceed = true;
        $retrySuccessResp = $app->handle(
            new Request(
                method: 'POST',
                uri: '/reservations/res_inhouse_1606/clearance-retry',
                post: ['csrf_token' => 'csrf_token_204'],
                server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_HX_CSRF_TOKEN' => 'csrf_token_204']
            ),
            $session
        );
        $this->assertSame(200, $retrySuccessResp->getStatusCode());
        $this->assertStringContainsString('Synced', $retrySuccessResp->getBody());
        $this->assertStringContainsString('#8844', $retrySuccessResp->getBody());
        $this->assertStringNotContainsString('Dismissed', $retrySuccessResp->getBody());

        $dismissalRepo = new \OceanViewFlats\Domain\Reservation\Dashboard\PdoOperationalAlertDismissalRepository($this->pdo);
        $this->assertFalse($dismissalRepo->isDismissed('clearance_res_inhouse_1606'));

        // 5. Saving Reservation Editor with sync_condominium_clearance=1 clears prior clearance dismissal and surfaces failed sync
        $dismissalRepo->dismiss('clearance_res_inhouse_1606', 'failed_condominium_clearance', '1606', 'res_inhouse_1606', 1);
        $shouldPortalSucceed = false;

        $editorSyncResp = $app->handle(
            new Request(
                method: 'POST',
                uri: '/reservations/res_inhouse_1606/edit',
                post: [
                    'property_id' => '1606',
                    'check_in' => $yesterday,
                    'check_out' => $inThreeDays,
                    'total_price' => '900000',
                    'max_guests' => '6',
                    'guest_name' => 'In-House Resident',
                    'guest_email' => 'stay@test.com',
                    'guest_phone' => '+573001112233',
                    'lang' => 'es',
                    'sync_condominium_clearance' => '1',
                    'csrf_token' => 'csrf_token_204',
                ],
                server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_HX_CSRF_TOKEN' => 'csrf_token_204']
            ),
            $session
        );
        $this->assertSame(200, $editorSyncResp->getStatusCode());
        $this->assertFalse($dismissalRepo->isDismissed('clearance_res_inhouse_1606'));

        $hubAfterEditorSync = $app->handle(new Request('GET', '/dashboard/hub', ['property_id' => '1606'], [], ['HTTP_HX_REQUEST' => 'true']), $session);
        $this->assertStringContainsString('Condominium Clearance Failed', $hubAfterEditorSync->getBody());

        // 6. Dismiss incomplete Guest Registry alert for res_arr_bob, then save Reservation Editor with reset_guest_registry=1 -> re-arms alert
        $dismissalRepo->dismiss('registry_res_arr_bob', 'incomplete_guest_registry', '1606', 'res_arr_bob', 1);
        $hubWithBobDismissed = $app->handle(new Request('GET', '/dashboard/hub', ['property_id' => '1606'], [], ['HTTP_HX_REQUEST' => 'true']), $session);
        $this->assertStringNotContainsString('Check-in Today: Missing Guest Registry (Bob Arriving)', $hubWithBobDismissed->getBody());

        $inTwoDays = $this->bogotaDate('+2 days');
        $editorResetResp = $app->handle(
            new Request(
                method: 'POST',
                uri: '/reservations/res_arr_bob/edit',
                post: [
                    'property_id' => '1606',
                    'check_in' => $today,
                    'check_out' => $inTwoDays,
                    'total_price' => '700000',
                    'max_guests' => '6',
                    'guest_name' => 'Bob Arriving',
                    'guest_email' => 'bob@test.com',
                    'guest_phone' => '+573003334455',
                    'lang' => 'es',
                    'reset_guest_registry' => '1',
                    'guest_count' => '0',
                    'csrf_token' => 'csrf_token_204',
                ],
                server: ['HTTP_HX_REQUEST' => 'true', 'HTTP_HX_CSRF_TOKEN' => 'csrf_token_204']
            ),
            $session
        );
        $this->assertSame(200, $editorResetResp->getStatusCode());
        $this->assertFalse($dismissalRepo->isDismissed('registry_res_arr_bob'));

        $hubAfterEditorReset = $app->handle(new Request('GET', '/dashboard/hub', ['property_id' => '1606'], [], ['HTTP_HX_REQUEST' => 'true']), $session);
        $this->assertStringContainsString('Check-in Today: Missing Guest Registry (Bob Arriving)', $hubAfterEditorReset->getBody());
        $this->assertStringContainsString('Guest registry incomplete (Bob Arriving)', $hubAfterEditorReset->getBody());
    }

    private function bogotaDate(string $modifier = 'today'): string
    {
        $cot = new DateTimeZone('America/Bogota');
        $today = new DateTimeImmutable('today', $cot);
        return ($modifier === 'today' ? $today : $today->modify($modifier))->format('Y-m-d');
    }

    private function seedOperationalDatabase(): void
    {
        $today = $this->bogotaDate('today');
        $yesterday = $this->bogotaDate('-1 day');
        $tomorrow = $this->bogotaDate('+1 day');
        $inTwoDays = $this->bogotaDate('+2 days');
        $inThreeDays = $this->bogotaDate('+3 days');
        $inFourDays = $this->bogotaDate('+4 days');
        $inFiveDays = $this->bogotaDate('+5 days');
        $inEightDays = $this->bogotaDate('+8 days');
        $inTenDays = $this->bogotaDate('+10 days');

        // 1. Reservations
        $stmt = $this->pdo->prepare('
            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, source, registry_completed, door_code
            ) VALUES
            (:res1, "1606", "In-House Resident", "stay@test.com", "+573001", :yesterday, :inThreeDays, 900000, "confirmed", "web", 1, "1111#"),
            (:res2, "1606", "Alice Departing", "alice@test.com", "+573002", :yesterday, :today, 350000, "confirmed", "airbnb", 1, "2222#"),
            (:res3, "1606", "Bob Arriving", "bob@test.com", "+573003", :today, :inTwoDays, 700000, "confirmed", "direct", 0, NULL),
            (:res4, "1707", "Charlie Pending", "charlie@test.com", "+573004", :tomorrow, :inFourDays, 1200000, "confirmed", "direct", 0, NULL),
            (:res5, "1707", "Far Future Alert Free", "safe@test.com", "+573005", :inFiveDays, :inEightDays, 1500000, "confirmed", "web", 0, NULL),
            (:res6, "1606", "Far Future Guest", "future@test.com", "+573006", :inEightDays, :inTenDays, 800000, "confirmed", "web", 1, "9999#");
        ');

        $stmt->execute([
            ':res1' => 'res_inhouse_1606',
            ':res2' => 'res_dep_alice',
            ':res3' => 'res_arr_bob',
            ':res4' => 'res_tomo_charlie',
            ':res5' => 'res_five_days',
            ':res6' => 'res_future_eight',
            ':yesterday' => $yesterday,
            ':today' => $today,
            ':tomorrow' => $tomorrow,
            ':inTwoDays' => $inTwoDays,
            ':inThreeDays' => $inThreeDays,
            ':inFourDays' => $inFourDays,
            ':inFiveDays' => $inFiveDays,
            ':inEightDays' => $inEightDays,
            ':inTenDays' => $inTenDays,
        ]);

        // 2. Calendar Maintenance Holds
        $this->pdo->exec("
            INSERT INTO calendar_blocks (property_id, start_date, end_date, reason, created_by)
            VALUES ('1606', '{$inTwoDays}', '{$inThreeDays}', 'Plumbing Inspection', 1);
        ");

        // 3. Seasonal Rate Tiers
        $this->pdo->exec("
            INSERT INTO property_rates (property_id, start_date, end_date, season_name, price_per_night, min_stay)
            VALUES ('1707', '{$today}', '{$inEightDays}', 'Festive Peak', 600000.0, 2);
        ");
    }

    private function createConfiguredApp(): AdminApp
    {
        $tomorrow = $this->bogotaDate('+1 day');
        $inThreeDays = $this->bogotaDate('+3 days');

        // Mock ChannelSyncStatus
        $syncStatus1606 = new ChannelSyncStatus(
            propertyId: '1606',
            status: ChannelSyncStatus::STATUS_HEALTHY,
            lastSyncedAt: date('c'),
            lastAttemptedAt: date('c'),
            httpCode: 200,
            blockedNightsCount: 2
        );
        $syncService = $this->createMock(InboundChannelSyncServiceInterface::class);
        $syncService->method('getAllStatuses')->willReturn(['1606' => $syncStatus1606]);

        // Mock Ledger with an un-onboarded channel block on Apt 1707
        $unonboardedBlock = new ChannelBlock('1707', $tomorrow, $inThreeDays, 'airbnb', 'Airbnb (HM-TEST)');
        $ledger = $this->createMock(ReservationLedgerInterface::class);
        $ledger->method('getChannelBlocks')->willReturnCallback(function (string $pid) use ($unonboardedBlock): array {
            return $pid === '1707' ? [$unonboardedBlock] : [];
        });

        return AdminApp::createDefault($this->pdo, [
            'channel_sync_service' => $syncService,
            'ledger' => $ledger,
        ]);
    }
}
