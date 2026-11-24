<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Reservation;

use InvalidArgumentException;
use OceanViewFlats\Domain\Reservation\ChannelSyncResult;
use OceanViewFlats\Domain\Reservation\ChannelSyncStatus;
use OceanViewFlats\Domain\Reservation\HttpTransportInterface;
use OceanViewFlats\Domain\Reservation\InboundChannelSyncService;
use PHPUnit\Framework\TestCase;

final class InboundChannelSyncServiceTest extends TestCase
{
    private string $tempCacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempCacheDir = sys_get_temp_dir() . '/ovf_test_sync_' . uniqid();
        mkdir($this->tempCacheDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempCacheDir . '/*');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
        @rmdir($this->tempCacheDir);
        parent::tearDown();
    }

    public function testSuccessfulSyncAndDateParsing(): void
    {
        $icalSample = <<<'ICAL'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Airbnb Inc//Hosting Calendar 0.8.8//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
DTSTART;VALUE=DATE:20261110
DTEND;VALUE=DATE:20261113
SUMMARY:Airbnb (Not available)
UID:event-1@airbnb.com
END:VEVENT
BEGIN:VEVENT
DTSTART:20261120T120000Z
DTEND:20261122T100000Z
SUMMARY:Reserved
UID:event-2@airbnb.com
END:VEVENT
END:VCALENDAR
ICAL;

        $transportCalls = 0;
        $mockTransport = new class($icalSample, $transportCalls) implements HttpTransportInterface {
            public function __construct(
                private readonly string $body,
                public int &$calls,
            ) {}

            public function get(string $url, array $options = []): array
            {
                $this->calls++;
                return [
                    'statusCode' => 200,
                    'body' => $this->body,
                    'error' => null,
                ];
            }
        };

        $service = new InboundChannelSyncService(
            feedUrls: ['1606' => 'https://example.com/ical/1606.ics?t=secret_token_should_not_persist'],
            cacheDir: $this->tempCacheDir,
            httpTransport: $mockTransport
        );

        $result = $service->sync('1606', force: false, initiatedBy: 'admin');

        $this->assertSame(1, $transportCalls);
        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->wasSkippedDueToCooldown());
        $this->assertSame('1606', $result->getPropertyId());

        // Nights: Nov 10, 11, 12 (event 1: checkout 13) and Nov 20, 21 (event 2: checkout 22)
        $expectedNights = ['2026-11-10', '2026-11-11', '2026-11-12', '2026-11-20', '2026-11-21'];
        $this->assertSame($expectedNights, $result->getBlockedNights());
        $this->assertSame(5, $result->getBlockedNightsCount());

        // Verify disk cache avail_1606.json
        $availFile = $this->tempCacheDir . '/avail_1606.json';
        $this->assertFileExists($availFile);
        $cachedOnDisk = json_decode((string) file_get_contents($availFile), true);
        $this->assertSame($expectedNights, $cachedOnDisk);

        // Verify status metadata in channel_sync_status.json
        $status = $service->getStatus('1606');
        $this->assertNotNull($status);
        $this->assertSame('1606', $status->getPropertyId());
        $this->assertSame('healthy', $status->getStatus());
        $this->assertTrue($status->isHealthy());
        $this->assertFalse($status->isDegraded());
        $this->assertFalse($status->isError());
        $this->assertSame(200, $status->getHttpCode());
        $this->assertSame(5, $status->getBlockedNightsCount());
        $this->assertNull($status->getErrorMessage());
        $this->assertSame('admin', $status->getInitiatedBy());
        $this->assertNotNull($status->getLastSyncedAt());
        $this->assertNotEmpty($status->getLastAttemptedAt());
        $this->assertSame('https://example.com/ical/1606.ics', $status->getFeedUrl());
    }

    public function testDateParsingIgnoresCancelledEvents(): void
    {
        $icalSample = <<<'ICAL'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
DTSTART:20261201
DTEND:20261203
SUMMARY:Cancelled reservation
STATUS:CANCELLED
END:VEVENT
BEGIN:VEVENT
DTSTART:20261205
DTEND:20261207
SUMMARY:Active reservation
STATUS:CONFIRMED
END:VEVENT
END:VCALENDAR
ICAL;

        $service = new InboundChannelSyncService([], $this->tempCacheDir);
        $nights = $service->parseIcalToBlockedNights($icalSample);

        $this->assertSame(['2026-12-05', '2026-12-06'], $nights);
    }

    public function testDateParsingRejectsMissingVcalendar(): void
    {
        $service = new InboundChannelSyncService([], $this->tempCacheDir);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing BEGIN:VCALENDAR');
        $service->parseIcalToBlockedNights("<html><body>Error</body></html>");
    }

    public function testCooldownWindowEnforcement(): void
    {
        $icalSample = "BEGIN:VCALENDAR\nBEGIN:VEVENT\nDTSTART:20261101\nDTEND:20261103\nEND:VEVENT\nEND:VCALENDAR";

        $calls = 0;
        $mockTransport = function () use (&$calls, $icalSample) {
            $calls++;
            return ['statusCode' => 200, 'body' => $icalSample, 'error' => null];
        };

        $service = new InboundChannelSyncService(
            feedUrls: ['1606' => 'https://example.com/1606.ics'],
            cacheDir: $this->tempCacheDir,
            httpTransport: $mockTransport,
            cooldownSeconds: 60
        );

        // Initial sync -> network call executed
        $firstResult = $service->sync('1606', force: false, initiatedBy: 'cron');
        $this->assertSame(1, $calls);
        $this->assertFalse($firstResult->wasSkippedDueToCooldown());
        $this->assertTrue($firstResult->isSuccess());

        // Immediate secondary sync without force -> skipped due to cooldown
        $secondResult = $service->sync('1606', force: false, initiatedBy: 'public_traffic');
        $this->assertSame(1, $calls, 'HTTP transport should NOT have been invoked within cooldown window.');
        $this->assertTrue($secondResult->wasSkippedDueToCooldown());
        $this->assertTrue($secondResult->isSuccess());
        $this->assertSame(['2026-11-01', '2026-11-02'], $secondResult->getBlockedNights());
        $this->assertStringContainsString('Cooldown active', (string) $secondResult->getMessage());
    }

    public function testForceOverrideBypassesCooldown(): void
    {
        $icalSample = "BEGIN:VCALENDAR\nBEGIN:VEVENT\nDTSTART:20261101\nDTEND:20261102\nEND:VEVENT\nEND:VCALENDAR";

        $calls = 0;
        $mockTransport = function () use (&$calls, $icalSample) {
            $calls++;
            return ['statusCode' => 200, 'body' => $icalSample, 'error' => null];
        };

        $service = new InboundChannelSyncService(
            feedUrls: ['1606' => 'https://example.com/1606.ics'],
            cacheDir: $this->tempCacheDir,
            httpTransport: $mockTransport,
            cooldownSeconds: 60
        );

        // First sync
        $service->sync('1606', force: false);
        $this->assertSame(1, $calls);

        // Second sync WITH force = true -> bypasses cooldown
        $forcedResult = $service->sync('1606', force: true, initiatedBy: 'admin');
        $this->assertSame(2, $calls, 'HTTP transport should be called when force is true.');
        $this->assertFalse($forcedResult->wasSkippedDueToCooldown());
        $this->assertTrue($forcedResult->isSuccess());
    }

    public function testGracefulDegradationRetainsPreviousCacheOnHttpError(): void
    {
        $availFile = $this->tempCacheDir . '/avail_1606.json';
        $seededNights = ['2026-11-15', '2026-11-16', '2026-11-17'];
        file_put_contents($availFile, json_encode($seededNights));

        $mockTransport = fn() => ['statusCode' => 500, 'body' => 'Internal Server Error', 'error' => null];

        $service = new InboundChannelSyncService(
            feedUrls: ['1606' => 'https://example.com/1606.ics'],
            cacheDir: $this->tempCacheDir,
            httpTransport: $mockTransport
        );

        $result = $service->sync('1606', force: true, initiatedBy: 'cron');

        // Degraded state: preserves cached blocks on disk
        $this->assertFalse($result->wasSkippedDueToCooldown());
        $this->assertSame('degraded', $result->getStatus()->getStatus());
        $this->assertTrue($result->getStatus()->isDegraded());
        $this->assertSame(500, $result->getStatus()->getHttpCode());
        $this->assertSame(3, $result->getStatus()->getBlockedNightsCount());
        $this->assertStringContainsString('500', (string) $result->getStatus()->getErrorMessage());

        // Verify disk cache was NOT wiped
        $this->assertFileExists($availFile);
        $diskContent = json_decode((string) file_get_contents($availFile), true);
        $this->assertSame($seededNights, $diskContent);
        $this->assertSame($seededNights, $result->getBlockedNights());
    }

    public function testGracefulDegradationRetainsPreviousCacheOnNetworkTimeout(): void
    {
        $availFile = $this->tempCacheDir . '/avail_1707.json';
        $seededNights = ['2026-12-24', '2026-12-25'];
        file_put_contents($availFile, json_encode($seededNights));

        $mockTransport = fn() => ['statusCode' => 0, 'body' => '', 'error' => 'Connection timed out after 10002ms'];

        $service = new InboundChannelSyncService(
            feedUrls: ['1707' => 'https://example.com/1707.ics'],
            cacheDir: $this->tempCacheDir,
            httpTransport: $mockTransport
        );

        $result = $service->sync('1707', force: true, initiatedBy: 'admin');

        $this->assertTrue($result->getStatus()->isDegraded());
        $this->assertStringContainsString('Connection timed out', (string) $result->getStatus()->getErrorMessage());
        $this->assertSame($seededNights, $result->getBlockedNights());

        // Cache on disk retained
        $this->assertSame($seededNights, json_decode((string) file_get_contents($availFile), true));
    }

    public function testGracefulDegradationRetainsPreviousCacheOnInvalidIcalBody(): void
    {
        $availFile = $this->tempCacheDir . '/avail_1606.json';
        $seededNights = ['2026-10-10'];
        file_put_contents($availFile, json_encode($seededNights));

        // Upstream returns 200 OK but HTML error challenge page instead of valid iCalendar
        $mockTransport = fn() => ['statusCode' => 200, 'body' => '<html>Attention Required! | Cloudflare</html>', 'error' => null];

        $service = new InboundChannelSyncService(
            feedUrls: ['1606' => 'https://example.com/1606.ics'],
            cacheDir: $this->tempCacheDir,
            httpTransport: $mockTransport
        );

        $result = $service->sync('1606', force: true);

        $this->assertTrue($result->getStatus()->isDegraded());
        $this->assertStringContainsString('missing BEGIN:VCALENDAR', (string) $result->getStatus()->getErrorMessage());
        $this->assertSame($seededNights, $result->getBlockedNights());
        $this->assertSame($seededNights, json_decode((string) file_get_contents($availFile), true));
    }

    public function testErrorStatusWhenNoPreviousCacheExistsOnFailure(): void
    {
        $mockTransport = fn() => ['statusCode' => 502, 'body' => 'Bad Gateway', 'error' => null];

        $service = new InboundChannelSyncService(
            feedUrls: ['1606' => 'https://example.com/1606.ics'],
            cacheDir: $this->tempCacheDir,
            httpTransport: $mockTransport
        );

        $result = $service->sync('1606', force: true);

        $this->assertSame('error', $result->getStatus()->getStatus());
        $this->assertTrue($result->getStatus()->isError());
        $this->assertSame(502, $result->getStatus()->getHttpCode());
        $this->assertSame(0, $result->getBlockedNightsCount());
        $this->assertSame([], $result->getBlockedNights());

        $status = $service->getStatus('1606');
        $this->assertNotNull($status);
        $this->assertTrue($status->isError());
    }

    public function testSyncAllRefreshesAllConfiguredFeeds(): void
    {
        $ical1606 = "BEGIN:VCALENDAR\nBEGIN:VEVENT\nDTSTART:20261101\nDTEND:20261102\nEND:VEVENT\nEND:VCALENDAR";
        $ical1707 = "BEGIN:VCALENDAR\nBEGIN:VEVENT\nDTSTART:20261105\nDTEND:20261107\nEND:VEVENT\nEND:VCALENDAR";

        $mockTransport = function (string $url) use ($ical1606, $ical1707) {
            $body = str_contains($url, '1606') ? $ical1606 : $ical1707;
            return ['statusCode' => 200, 'body' => $body, 'error' => null];
        };

        $service = new InboundChannelSyncService(
            feedUrls: [
                '1606' => 'https://example.com/1606.ics',
                '1707' => 'https://example.com/1707.ics',
            ],
            cacheDir: $this->tempCacheDir,
            httpTransport: $mockTransport
        );

        $results = $service->syncAll(force: true, initiatedBy: 'admin');

        $this->assertCount(2, $results);
        $this->assertArrayHasKey('1606', $results);
        $this->assertArrayHasKey('1707', $results);

        $this->assertSame(['2026-11-01'], $results['1606']->getBlockedNights());
        $this->assertSame(['2026-11-05', '2026-11-06'], $results['1707']->getBlockedNights());

        $statuses = $service->getAllStatuses();
        $this->assertCount(2, $statuses);
        $this->assertSame('healthy', $statuses['1606']->getStatus());
        $this->assertSame('healthy', $statuses['1707']->getStatus());
    }

    public function testThrowsExceptionWhenPropertyNotConfigured(): void
    {
        $service = new InboundChannelSyncService(['1606' => 'https://example.com/1606.ics'], $this->tempCacheDir);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No feed configured for property ID: 9999');
        $service->sync('9999');
    }

    public function testIsCacheStaleAndHasCacheFile(): void
    {
        $service = new InboundChannelSyncService(['1606' => 'https://example.com/1606.ics'], $this->tempCacheDir);

        $this->assertFalse($service->hasCacheFile('1606'));
        $this->assertTrue($service->isCacheStale('1606', 900));

        // Create cache file
        $availFile = $this->tempCacheDir . '/avail_1606.json';
        file_put_contents($availFile, json_encode(['2026-11-10']));

        $this->assertTrue($service->hasCacheFile('1606'));
        $this->assertFalse($service->isCacheStale('1606', 900));
        $this->assertSame(['2026-11-10'], $service->getCachedNights('1606'));

        // Artificially age the cache file by 1000 seconds
        touch($availFile, time() - 1000);
        $this->assertTrue($service->isCacheStale('1606', 900));
    }

    public function testValueObjectSerialization(): void
    {
        $status = new ChannelSyncStatus(
            propertyId: '1606',
            status: 'healthy',
            lastSyncedAt: '2026-10-01T12:00:00+00:00',
            lastAttemptedAt: '2026-10-01T12:00:00+00:00',
            httpCode: 200,
            blockedNightsCount: 12,
            errorMessage: null,
            initiatedBy: 'admin',
            feedUrl: 'https://example.com/1606.ics'
        );

        $array = $status->toArray();
        $this->assertSame('1606', $array['property']);
        $this->assertSame('healthy', $array['status']);
        $this->assertSame(200, $array['http_code']);
        $this->assertSame(12, $array['blocked_nights_count']);

        $restored = ChannelSyncStatus::fromArray($array);
        $this->assertSame('1606', $restored->getPropertyId());
        $this->assertSame('healthy', $restored->getStatus());
        $this->assertSame('2026-10-01T12:00:00+00:00', $restored->getLastSyncedAt());
        $this->assertSame(200, $restored->getHttpCode());
        $this->assertSame(12, $restored->getBlockedNightsCount());
        $this->assertSame('admin', $restored->getInitiatedBy());
        $this->assertSame('https://example.com/1606.ics', $restored->getFeedUrl());

        $result = new ChannelSyncResult(
            propertyId: '1606',
            status: $status,
            wasSkippedDueToCooldown: false,
            blockedNights: ['2026-10-01', '2026-10-02'],
            message: 'Success'
        );

        $resArray = $result->toArray();
        $this->assertSame('1606', $resArray['property']);
        $this->assertFalse($resArray['was_skipped_due_to_cooldown']);
        $this->assertSame(2, $resArray['blocked_nights_count']);
        $this->assertSame('Success', $resArray['message']);
    }
}
