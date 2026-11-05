<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Controller;

use InvalidArgumentException;
use OceanViewFlats\Admin\Audit\AuditLogger;
use OceanViewFlats\Admin\Controller\ChannelSyncController;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Http\Response;
use OceanViewFlats\Admin\Tests\Support\AdminDatabaseTestHelper;
use OceanViewFlats\Admin\Views\ViewRenderer;
use OceanViewFlats\Domain\Reservation\ChannelBlock;
use OceanViewFlats\Domain\Reservation\ChannelSyncResult;
use OceanViewFlats\Domain\Reservation\ChannelSyncStatus;
use OceanViewFlats\Domain\Reservation\InboundChannelSyncServiceInterface;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;
use OceanViewFlats\Domain\Reservation\ReservationLedgerInterface;
use PDO;
use PHPUnit\Framework\TestCase;

final class ChannelSyncControllerTest extends TestCase
{
    private PDO $pdo;
    private AuditLogger $auditLogger;
    private ViewRenderer $viewRenderer;

    protected function setUp(): void
    {
        $this->pdo = AdminDatabaseTestHelper::createDatabaseWithDefaultAdmin();
        $this->auditLogger = new AuditLogger($this->pdo);
        $viewsPath = dirname(__DIR__, 2) . '/src/Views';
        $this->viewRenderer = new ViewRenderer($viewsPath);
    }

    public function testCardRendersHealthyStateWhenStatusesAreHealthy(): void
    {
        $status1606 = new ChannelSyncStatus(
            propertyId: '1606',
            status: ChannelSyncStatus::STATUS_HEALTHY,
            lastSyncedAt: date('c', time() - 300), // 5 mins ago
            lastAttemptedAt: date('c', time() - 300),
            httpCode: 200,
            blockedNightsCount: 7,
            errorMessage: null
        );
        $status1707 = new ChannelSyncStatus(
            propertyId: '1707',
            status: ChannelSyncStatus::STATUS_HEALTHY,
            lastSyncedAt: date('c', time() - 240), // 4 mins ago
            lastAttemptedAt: date('c', time() - 240),
            httpCode: 200,
            blockedNightsCount: 5,
            errorMessage: null
        );

        $fakeService = new FakeInboundChannelSyncService([
            '1606' => $status1606,
            '1707' => $status1707,
        ]);

        $controller = new ChannelSyncController(
            syncService: $fakeService,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger
        );

        $session = ['admin_user_id' => 1, 'csrf_token' => 'token123'];
        $request = new Request('GET', '/channel-sync/card');

        $response = $controller->card($request, $session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('id="channel-card-container"', $body);
        $this->assertStringContainsString('Healthy', $body);
        $this->assertStringContainsString('Channel Feeds Active', $body);
        $this->assertStringContainsString('4', $body); // 2 + 2 active blocked nights
        $this->assertStringContainsString('blocked nights', $body);
        $this->assertStringContainsString('Synced 4 mins ago', $body);
        $this->assertStringContainsString('hx-post="/channel-sync"', $body);
        $this->assertStringContainsString('hx-target="#channel-card-container"', $body);
        $this->assertStringContainsString('hx-swap="outerHTML"', $body);
        $this->assertStringContainsString('hx-indicator="#channel-sync-spinner"', $body);
        $this->assertStringContainsString('id="channel-sync-spinner"', $body);
    }

    public function testCardRendersDegradedStateWhenFeedIsDegraded(): void
    {
        $status1606 = new ChannelSyncStatus(
            propertyId: '1606',
            status: ChannelSyncStatus::STATUS_DEGRADED,
            lastSyncedAt: date('c', time() - 600),
            lastAttemptedAt: date('c', time() - 30),
            httpCode: 504,
            blockedNightsCount: 3,
            errorMessage: 'Gateway Timeout'
        );

        $fakeService = new FakeInboundChannelSyncService([
            '1606' => $status1606,
        ]);

        $controller = new ChannelSyncController(
            syncService: $fakeService,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger
        );

        $session = ['admin_user_id' => 1, 'csrf_token' => 'token123'];
        $request = new Request('GET', '/channel-sync/card');

        $response = $controller->card($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('Degraded', $body);
        $this->assertStringContainsString('bg-amber-50', $body);
        $this->assertStringContainsString('3', $body);
    }

    public function testCardRendersErrorStateWhenFeedIsError(): void
    {
        $status1606 = new ChannelSyncStatus(
            propertyId: '1606',
            status: ChannelSyncStatus::STATUS_ERROR,
            lastSyncedAt: null,
            lastAttemptedAt: date('c', time() - 30),
            httpCode: 404,
            blockedNightsCount: 0,
            errorMessage: 'Feed not found'
        );

        $fakeService = new FakeInboundChannelSyncService([
            '1606' => $status1606,
        ]);

        $controller = new ChannelSyncController(
            syncService: $fakeService,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger
        );

        $session = ['admin_user_id' => 1, 'csrf_token' => 'token123'];
        $request = new Request('GET', '/channel-sync/card');

        $response = $controller->card($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('Error', $body);
        $this->assertStringContainsString('bg-rose-50', $body);
        $this->assertStringContainsString('Never synced', $body);
    }

    public function testRelativeTimeFormatting(): void
    {
        $now = 1760000000;

        $this->assertSame('Never synced', ChannelSyncController::formatRelativeTime(null, $now));
        $this->assertSame('Never synced', ChannelSyncController::formatRelativeTime('', $now));
        $this->assertSame('Never synced', ChannelSyncController::formatRelativeTime('invalid-date', $now));

        $justNow = date('c', $now - 20);
        $this->assertSame('Synced just now', ChannelSyncController::formatRelativeTime($justNow, $now));

        $oneMin = date('c', $now - 75);
        $this->assertSame('Synced 1 min ago', ChannelSyncController::formatRelativeTime($oneMin, $now));

        $fourMins = date('c', $now - 240);
        $this->assertSame('Synced 4 mins ago', ChannelSyncController::formatRelativeTime($fourMins, $now));

        $oneHour = date('c', $now - 3700);
        $this->assertSame('Synced 1 hour ago', ChannelSyncController::formatRelativeTime($oneHour, $now));

        $threeHours = date('c', $now - 10800);
        $this->assertSame('Synced 3 hours ago', ChannelSyncController::formatRelativeTime($threeHours, $now));

        $oneDay = date('c', $now - 90000);
        $this->assertSame('Synced 1 day ago', ChannelSyncController::formatRelativeTime($oneDay, $now));

        $fiveDays = date('c', $now - 432000);
        $this->assertSame('Synced 5 days ago', ChannelSyncController::formatRelativeTime($fiveDays, $now));
    }

    public function testSyncAllPerformsSyncLogsAuditAndReturnsUpdatedCardHtml(): void
    {
        $fakeService = new FakeInboundChannelSyncService();

        $controller = new ChannelSyncController(
            syncService: $fakeService,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger
        );

        $session = ['admin_user_id' => 42, 'csrf_token' => 'test_csrf'];
        $request = new Request(
            method: 'POST',
            uri: '/channel-sync',
            server: ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_USER_AGENT' => 'TestBrowser/1.0']
        );

        $response = $controller->sync($request, $session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Verifies returned HTML card
        $this->assertStringContainsString('id="channel-card-container"', $body);
        $this->assertStringContainsString('Healthy', $body);
        $this->assertStringContainsString('Sync Now', $body);
        $this->assertStringContainsString('All feeds synchronized successfully', $body);

        // Verifies audit log was recorded in admin_audit_logs
        $stmt = $this->pdo->query('SELECT * FROM admin_audit_logs WHERE action = "channel_sync_manual"');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($log);
        $this->assertSame(42, (int) $log['admin_user_id']);
        $this->assertSame('channel_sync_manual', $log['action']);
        $this->assertSame('channel_sync', $log['entity_type']);
        $this->assertSame('all', $log['entity_id']);
        $this->assertSame('10.0.0.1', $log['ip_address']);
        $this->assertSame('TestBrowser/1.0', $log['user_agent']);

        $payloadAfter = json_decode((string) $log['payload_after'], true);
        $this->assertIsArray($payloadAfter);
        $this->assertSame('all', $payloadAfter['scope']);
        $this->assertArrayHasKey('results', $payloadAfter);
    }

    public function testSyncSinglePropertyLogsSpecificAuditEntity(): void
    {
        $fakeService = new FakeInboundChannelSyncService();

        $controller = new ChannelSyncController(
            syncService: $fakeService,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger
        );

        $session = ['admin_user_id' => 7, 'csrf_token' => 'test_csrf'];
        $request = new Request(
            method: 'POST',
            uri: '/channel-sync',
            post: ['property_id' => '1606']
        );

        $response = $controller->sync($request, $session);

        $this->assertSame(200, $response->getStatusCode());

        $stmt = $this->pdo->query('SELECT * FROM admin_audit_logs WHERE action = "channel_sync_manual" ORDER BY id DESC LIMIT 1');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($log);
        $this->assertSame(7, (int) $log['admin_user_id']);
        $this->assertSame('1606', $log['entity_id']);
    }

    public function testSyncHandlesCooldownNotification(): void
    {
        $fakeService = new FakeInboundChannelSyncService();
        $fakeService->cooldownActive = true;

        $controller = new ChannelSyncController(
            syncService: $fakeService,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger
        );

        $session = ['admin_user_id' => 1];
        $request = new Request('POST', '/channel-sync');

        $response = $controller->sync($request, $session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('Cooldown active', $body);
    }

    public function testSyncHandlesUnknownPropertyReturns422(): void
    {
        $fakeService = new FakeInboundChannelSyncService();

        $controller = new ChannelSyncController(
            syncService: $fakeService,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger
        );

        $session = ['admin_user_id' => 1];
        $request = new Request('POST', '/channel-sync', post: ['property_id' => 'unknown_999']);

        $response = $controller->sync($request, $session);

        $this->assertSame(422, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('No feed configured for property ID: unknown_999', $body);
    }

    public function testPanelRendersChannelSyncPanelHtml(): void
    {
        $status1606 = new ChannelSyncStatus(
            propertyId: '1606',
            status: ChannelSyncStatus::STATUS_HEALTHY,
            lastSyncedAt: date('c', time() - 300),
            lastAttemptedAt: date('c', time() - 300),
            httpCode: 200,
            blockedNightsCount: 4,
            errorMessage: null
        );
        $status1707 = new ChannelSyncStatus(
            propertyId: '1707',
            status: ChannelSyncStatus::STATUS_HEALTHY,
            lastSyncedAt: date('c', time() - 600),
            lastAttemptedAt: date('c', time() - 600),
            httpCode: 200,
            blockedNightsCount: 2,
            errorMessage: null
        );

        $fakeService = new FakeInboundChannelSyncService([
            '1606' => $status1606,
            '1707' => $status1707,
        ]);

        $controller = new ChannelSyncController(
            syncService: $fakeService,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger
        );

        $session = ['admin_user_id' => 1, 'csrf_token' => 'token123'];
        $request = new Request('GET', '/channel-sync/panel');

        $response = $controller->panel($request, $session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('id="channel-sync-panel"', $body);
        $this->assertStringContainsString('Inbound Channel Sync', $body);
        $this->assertStringContainsString('Property 1606', $body);
        $this->assertStringContainsString('Property 1707', $body);
        $this->assertStringContainsString('Healthy', $body);
        $this->assertStringContainsString('hx-post="/channel-sync?view=panel"', $body);
        $this->assertStringContainsString('hx-post="/channel-sync?property_id=1606&view=panel"', $body);
        $this->assertStringContainsString('hx-post="/channel-sync?property_id=1707&view=panel"', $body);
        $this->assertStringContainsString('hx-target="#channel-sync-panel"', $body);
        $this->assertStringContainsString('hx-swap="outerHTML"', $body);
        $this->assertStringContainsString('hx-indicator="#sync-spinner-1606"', $body);
        $this->assertStringContainsString('hx-indicator="#sync-spinner-all"', $body);
    }

    public function testPostChannelSyncWithPropertyIdAndPanelViewReturnsPanelHtml(): void
    {
        $fakeService = new FakeInboundChannelSyncService();

        $controller = new ChannelSyncController(
            syncService: $fakeService,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger
        );

        $session = ['admin_user_id' => 2, 'csrf_token' => 'token_xyz'];
        $request = new Request(
            method: 'POST',
            uri: '/channel-sync',
            query: ['property_id' => '1606', 'view' => 'panel']
        );

        $response = $controller->sync($request, $session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('id="channel-sync-panel"', $body);
        $this->assertStringContainsString('Feed synchronized successfully', $body);
        $this->assertStringContainsString('Property 1606', $body);
    }

    public function testPostChannelSyncPerPropertyAuditLogging(): void
    {
        $fakeService = new FakeInboundChannelSyncService();

        $controller = new ChannelSyncController(
            syncService: $fakeService,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger
        );

        $session = ['admin_user_id' => 5, 'csrf_token' => 'token_xyz'];
        $request = new Request(
            method: 'POST',
            uri: '/channel-sync',
            query: ['property_id' => '1606', 'view' => 'panel']
        );

        $response = $controller->sync($request, $session);
        $this->assertSame(200, $response->getStatusCode());

        $stmt = $this->pdo->query('SELECT * FROM admin_audit_logs WHERE action = "channel_sync_manual" ORDER BY id DESC LIMIT 1');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($log);
        $this->assertSame(5, (int) $log['admin_user_id']);
        $this->assertSame('1606', $log['entity_id']);
    }

    public function testDegradedStateWithRetainedCacheRendersErrorCalloutBanner(): void
    {
        $status1606 = new ChannelSyncStatus(
            propertyId: '1606',
            status: ChannelSyncStatus::STATUS_DEGRADED,
            lastSyncedAt: date('c', time() - 3600),
            lastAttemptedAt: date('c', time() - 60),
            httpCode: 504,
            blockedNightsCount: 3,
            errorMessage: 'Gateway Timeout'
        );

        $fakeService = new FakeInboundChannelSyncService([
            '1606' => $status1606,
        ]);

        $controller = new ChannelSyncController(
            syncService: $fakeService,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger
        );

        $session = ['admin_user_id' => 1, 'csrf_token' => 'token123'];
        $request = new Request('GET', '/channel-sync/panel');

        $response = $controller->panel($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('id="channel-sync-panel"', $body);
        $this->assertStringContainsString('id="channel-sync-diagnostics"', $body);
        $this->assertStringContainsString('HTTP 504', $body);
        $this->assertStringContainsString('Gateway Timeout', $body);
        $this->assertStringContainsString('Previous cached blocks', $body);
        $this->assertStringContainsString('safely retained', $body);
    }

    public function testPostChannelSyncWithHxTargetHeaderReturnsPanelHtml(): void
    {
        $fakeService = new FakeInboundChannelSyncService();

        $controller = new ChannelSyncController(
            syncService: $fakeService,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger
        );

        $session = ['admin_user_id' => 1, 'csrf_token' => 'token_xyz'];
        $request = new Request(
            method: 'POST',
            uri: '/channel-sync',
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_TARGET' => 'channel-sync-panel',
            ]
        );

        $response = $controller->sync($request, $session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('id="channel-sync-panel"', $body);
        $this->assertStringNotContainsString('id="channel-card-container"', $body);
    }

    public function testPanelRendersDetectedExternalBookingsWithOnboardActionsAndBadges(): void
    {
        $status1606 = new ChannelSyncStatus(
            propertyId: '1606',
            status: ChannelSyncStatus::STATUS_HEALTHY,
            lastSyncedAt: date('c'),
            lastAttemptedAt: date('c'),
            httpCode: 200,
            blockedNightsCount: 4,
            errorMessage: null
        );

        $fakeService = new FakeInboundChannelSyncService(['1606' => $status1606]);

        /** @var \PHPUnit\Framework\MockObject\MockObject&ReservationLedgerInterface $ledger */
        $ledger = $this->createMock(ReservationLedgerInterface::class);
        $ledger->method('getChannelBlocks')
            ->willReturnCallback(function (string $propertyId) {
                if ($propertyId === '1606') {
                    return [
                        new ChannelBlock('1606', '2027-11-01', '2027-11-05', 'airbnb', 'Airbnb (Not available)')
                    ];
                }
                return [];
            });

        $repo = new PdoReservationRepository($this->pdo);

        $controller = new ChannelSyncController(
            syncService: $fakeService,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger,
            ledger: $ledger,
            reservationRepository: $repo
        );

        $session = ['admin_user_id' => 1, 'csrf_token' => 'token_xyz'];
        $request = new Request('GET', '/channel-sync/panel');

        $response = $controller->panel($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('Detected External Platform Bookings (Airbnb)', $body);
        $this->assertStringContainsString('Onboard Guest', $body);
        $this->assertStringContainsString('2027-11-01 &rarr; 2027-11-05', $body);
        $this->assertStringContainsString('/reservations/new?property_id=1606&check_in=2027-11-01&check_out=2027-11-05&source=airbnb', $body);
    }
}

/**
 * Test double implementing InboundChannelSyncServiceInterface.
 */
final class FakeInboundChannelSyncService implements InboundChannelSyncServiceInterface
{
    public bool $cooldownActive = false;

    /**
     * @param array<string|int, ChannelSyncStatus> $statuses
     */
    public function __construct(
        public array $statuses = []
    ) {
    }

    public function sync(string $propertyId, bool $force = false, string $initiatedBy = 'system'): ChannelSyncResult
    {
        if ($propertyId === 'unknown_999') {
            throw new InvalidArgumentException("No feed configured for property ID: {$propertyId}");
        }

        $status = new ChannelSyncStatus(
            propertyId: $propertyId,
            status: ChannelSyncStatus::STATUS_HEALTHY,
            lastSyncedAt: date('c'),
            lastAttemptedAt: date('c'),
            httpCode: 200,
            blockedNightsCount: 4,
            errorMessage: null,
            initiatedBy: $initiatedBy
        );

        $this->statuses[$propertyId] = $status;

        return new ChannelSyncResult(
            propertyId: $propertyId,
            status: $status,
            wasSkippedDueToCooldown: $this->cooldownActive,
            blockedNights: ['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04'],
            message: $this->cooldownActive ? 'Cooldown active: attempted 10s ago' : 'Sync succeeded'
        );
    }

    public function syncAll(bool $force = false, string $initiatedBy = 'system'): array
    {
        return [
            '1606' => $this->sync('1606', $force, $initiatedBy),
            '1707' => $this->sync('1707', $force, $initiatedBy),
        ];
    }

    public function getStatus(string $propertyId): ?ChannelSyncStatus
    {
        return $this->statuses[$propertyId] ?? null;
    }

    public function getAllStatuses(): array
    {
        return $this->statuses;
    }

    /**
     * @return array<string|int, string>
     */
    public function getFeedUrls(): array
    {
        return [
            '1606' => 'https://example.com/ical/1606.ics',
            '1707' => 'https://example.com/ical/1707.ics',
        ];
    }

    public function parseIcalToBlockedNights(string $icalContent): array
    {
        return [];
    }

    public function isCacheStale(string $propertyId, int $ttlSeconds = 900): bool
    {
        return false;
    }

    public function hasCacheFile(string $propertyId): bool
    {
        return isset($this->statuses[$propertyId]);
    }

    public function getCachedNights(string $propertyId): ?array
    {
        return isset($this->statuses[$propertyId]) ? ['2026-11-01', '2026-11-02'] : null;
    }
}
