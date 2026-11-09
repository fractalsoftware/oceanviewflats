<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use OceanViewFlats\Domain\Support\PathResolver;

/**
 * Domain engine managing inbound synchronization of external Online Travel Agency (Airbnb) iCalendar feeds.
 *
 * Adheres strictly to ADR 0002: Ingests external bookings purely as ephemeral channel blocks cached on disk,
 * maintaining real-time feed health metadata in channel_sync_status.json without MySQL database mutations.
 */
final class InboundChannelSyncService implements InboundChannelSyncServiceInterface
{
    public const DEFAULT_COOLDOWN_SECONDS = 60;
    public const DEFAULT_CACHE_LIFETIME_SECONDS = 300; // 5 minutes
    public const STATUS_FILENAME = 'channel_sync_status.json';

    private readonly HttpTransportInterface $httpTransport;

    /**
     * @param array<string|int, string> $feedUrls Map of property ID => iCalendar feed URL
     * @param string $cacheDir Directory where avail_{property}.json and channel_sync_status.json are stored
     * @param HttpTransportInterface|callable|null $httpTransport Optional HTTP transport or callable
     * @param int $cooldownSeconds Minimum seconds between upstream requests per feed (default 60)
     */
    public function __construct(
        private readonly array $feedUrls,
        private readonly string $cacheDir,
        HttpTransportInterface|callable|null $httpTransport = null,
        private readonly int $cooldownSeconds = self::DEFAULT_COOLDOWN_SECONDS,
    ) {
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }

        if ($httpTransport instanceof HttpTransportInterface) {
            $this->httpTransport = $httpTransport;
        } elseif (is_callable($httpTransport)) {
            $this->httpTransport = new class($httpTransport) implements HttpTransportInterface {
                /** @var callable */
                private $callable;

                public function __construct(callable $callable)
                {
                    $this->callable = $callable;
                }

                /**
                 * @param string $url
                 * @param array<string, mixed> $options
                 * @return array{statusCode: int, body: string, error: ?string}
                 */
                public function get(string $url, array $options = []): array
                {
                    /** @var array{statusCode: int, body: string, error: ?string} */
                    return ($this->callable)($url, $options);
                }
            };
        } else {
            $this->httpTransport = new CurlHttpTransport();
        }
    }

    /**
     * Factory creating a default configured instance resolving central config and cache paths.
     *
     * @param string|null $cacheDir
     * @param array<string|int, string>|null $feedUrls
     * @param HttpTransportInterface|callable|null $httpTransport
     * @param int $cooldownSeconds
     */
    public static function createDefault(
        ?string $cacheDir = null,
        ?array $feedUrls = null,
        HttpTransportInterface|callable|null $httpTransport = null,
        int $cooldownSeconds = self::DEFAULT_COOLDOWN_SECONDS,
    ): self {
        $resolvedCacheDir = $cacheDir ?? PathResolver::resolveDirectory('cache');

        if ($feedUrls === null) {
            $feedUrls = [];
            $configPath = PathResolver::resolveIfExists('api/config.php');
            if ($configPath !== null) {
                /** @var array<string, mixed> $config */
                $config = require $configPath;
                if (isset($config['ical_feeds']) && is_array($config['ical_feeds'])) {
                    /** @var array<string, string> $feedUrls */
                    $feedUrls = $config['ical_feeds'];
                }
            }
        }

        return new self($feedUrls, $resolvedCacheDir, $httpTransport, $cooldownSeconds);
    }

    /**
     * @inheritDoc
     */
    public function sync(string $propertyId, bool $force = false, string $initiatedBy = 'system'): ChannelSyncResult
    {
        if (!isset($this->feedUrls[$propertyId])) {
            throw new InvalidArgumentException("No feed configured for property ID: {$propertyId}");
        }

        $feedUrl = $this->feedUrls[$propertyId];
        $existingStatus = $this->getStatus($propertyId);

        // Check 60-second cooldown window unless force override is requested
        if (!$force && $existingStatus !== null) {
            $lastAttempted = strtotime($existingStatus->getLastAttemptedAt());
            $elapsed = time() - $lastAttempted;
            if ($lastAttempted > 0 && $elapsed >= 0 && $elapsed < $this->cooldownSeconds) {
                $cachedNights = $this->getCachedNights($propertyId) ?? [];
                return new ChannelSyncResult(
                    propertyId: $propertyId,
                    status: $existingStatus,
                    wasSkippedDueToCooldown: true,
                    blockedNights: $cachedNights,
                    message: "Cooldown active: attempted {$elapsed}s ago (minimum {$this->cooldownSeconds}s). Use force to bypass."
                );
            }
        }

        $attemptTime = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeImmutable::ATOM);

        // Execute upstream HTTP fetch
        $response = $this->httpTransport->get($feedUrl, ['timeout' => 10]);
        $statusCode = $response['statusCode'];
        $body = $response['body'];
        $transportError = $response['error'];

        $isHttpOk = ($statusCode === 200 && $transportError === null && $body !== '');
        $isIcalValid = $isHttpOk && (stripos($body, 'BEGIN:VCALENDAR') !== false);

        if ($isHttpOk && $isIcalValid) {
            // Successful fetch & valid iCalendar
            $blockedNights = $this->parseIcalToBlockedNights($body);

            // Atomically write avail_{property}.json
            $availFile = $this->getAvailFilePath($propertyId);
            $this->atomicWrite($availFile, json_encode($blockedNights, JSON_UNESCAPED_SLASHES));

            // Record healthy status
            $newStatus = new ChannelSyncStatus(
                propertyId: $propertyId,
                status: ChannelSyncStatus::STATUS_HEALTHY,
                lastSyncedAt: $attemptTime,
                lastAttemptedAt: $attemptTime,
                httpCode: 200,
                blockedNightsCount: count($blockedNights),
                errorMessage: null,
                initiatedBy: $initiatedBy,
                feedUrl: $feedUrl,
            );
            $this->saveStatus($newStatus);

            return new ChannelSyncResult(
                propertyId: $propertyId,
                status: $newStatus,
                wasSkippedDueToCooldown: false,
                blockedNights: $blockedNights,
                message: "Successfully synchronized " . count($blockedNights) . " blocked nights."
            );
        }

        // Upstream failure or invalid response: evaluate error diagnostics
        $diagnosticError = $this->buildDiagnosticErrorMessage($statusCode, $transportError, $body);

        // Graceful degradation: inspect existing cached blocks
        $existingNights = $this->getCachedNights($propertyId);

        if ($existingNights !== null) {
            // Degraded state: retain existing blocks on disk to avoid accidental calendar opening
            $degradedStatus = new ChannelSyncStatus(
                propertyId: $propertyId,
                status: ChannelSyncStatus::STATUS_DEGRADED,
                lastSyncedAt: $existingStatus?->getLastSyncedAt(),
                lastAttemptedAt: $attemptTime,
                httpCode: $statusCode > 0 ? $statusCode : null,
                blockedNightsCount: count($existingNights),
                errorMessage: $diagnosticError,
                initiatedBy: $initiatedBy,
                feedUrl: $feedUrl,
            );
            $this->saveStatus($degradedStatus);

            return new ChannelSyncResult(
                propertyId: $propertyId,
                status: $degradedStatus,
                wasSkippedDueToCooldown: false,
                blockedNights: $existingNights,
                message: "Upstream fetch failed ({$diagnosticError}); retained " . count($existingNights) . " cached nights."
            );
        }

        // Error state: no pre-existing cache file available
        $errorStatus = new ChannelSyncStatus(
            propertyId: $propertyId,
            status: ChannelSyncStatus::STATUS_ERROR,
            lastSyncedAt: null,
            lastAttemptedAt: $attemptTime,
            httpCode: $statusCode > 0 ? $statusCode : null,
            blockedNightsCount: 0,
            errorMessage: $diagnosticError,
            initiatedBy: $initiatedBy,
            feedUrl: $feedUrl,
        );
        $this->saveStatus($errorStatus);

        return new ChannelSyncResult(
            propertyId: $propertyId,
            status: $errorStatus,
            wasSkippedDueToCooldown: false,
            blockedNights: [],
            message: "Upstream fetch failed ({$diagnosticError}); no cached calendar available."
        );
    }

    /**
     * @inheritDoc
     */
    public function syncAll(bool $force = false, string $initiatedBy = 'system'): array
    {
        $results = [];
        foreach (array_keys($this->feedUrls) as $propertyId) {
            $results[$propertyId] = $this->sync((string) $propertyId, $force, $initiatedBy);
        }
        return $results;
    }

    /**
     * @inheritDoc
     */
    public function getStatus(string $propertyId): ?ChannelSyncStatus
    {
        $all = $this->readAllStatuses();
        return $all[$propertyId] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getAllStatuses(): array
    {
        return $this->readAllStatuses();
    }

    /**
     * @inheritDoc
     */
    public function parseIcalToBlockedNights(string $icalContent): array
    {
        if (stripos($icalContent, 'BEGIN:VCALENDAR') === false) {
            throw new InvalidArgumentException('Invalid iCalendar content: missing BEGIN:VCALENDAR marker.');
        }

        // Unfold multi-line RFC 5545 properties (continuation lines starting with space or tab)
        $unfolded = (string) preg_replace("/\r?\n[ \t]/", "", $icalContent);
        $lines = explode("\n", str_replace("\r", "", $unfolded));

        $blockedDates = [];
        $isEvent = false;
        $isCancelled = false;
        $dtStart = null;
        $dtEnd = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if ($trimmed === 'BEGIN:VEVENT') {
                $isEvent = true;
                $isCancelled = false;
                $dtStart = null;
                $dtEnd = null;
                continue;
            }

            if ($trimmed === 'END:VEVENT') {
                if ($isEvent && !$isCancelled && $dtStart !== null && $dtEnd !== null) {
                    $start = DateTimeImmutable::createFromFormat('!Ymd', $dtStart);
                    $end = DateTimeImmutable::createFromFormat('!Ymd', $dtEnd);

                    if ($start instanceof DateTimeImmutable && $end instanceof DateTimeImmutable && $start < $end) {
                        $curr = $start;
                        $guardDays = 0;
                        while ($curr < $end && $guardDays < 730) {
                            $blockedDates[] = $curr->format('Y-m-d');
                            $curr = $curr->modify('+1 day');
                            $guardDays++;
                        }
                    }
                }
                $isEvent = false;
                $isCancelled = false;
                $dtStart = null;
                $dtEnd = null;
                continue;
            }

            if ($isEvent) {
                if (stripos($trimmed, 'STATUS:CANCELLED') === 0) {
                    $isCancelled = true;
                } elseif (preg_match('/^DTSTART.*?:([0-9]{8})/i', $trimmed, $matches)) {
                    $dtStart = $matches[1];
                } elseif (preg_match('/^DTEND.*?:([0-9]{8})/i', $trimmed, $matches)) {
                    $dtEnd = $matches[1];
                }
            }
        }

        $unique = array_values(array_unique($blockedDates));
        sort($unique);

        return $unique;
    }

    /**
     * @inheritDoc
     */
    public function isCacheStale(string $propertyId, int $maxAgeSeconds = self::DEFAULT_CACHE_LIFETIME_SECONDS): bool
    {
        $availFile = $this->getAvailFilePath($propertyId);
        clearstatcache(true, $availFile);
        if (!file_exists($availFile)) {
            return true;
        }

        $status = $this->getStatus($propertyId);
        if ($status !== null && $status->getLastSyncedAt() !== null) {
            $syncedAt = strtotime($status->getLastSyncedAt());
            if ($syncedAt > 0) {
                return (time() - $syncedAt) >= $maxAgeSeconds;
            }
        }

        $mtime = filemtime($availFile);
        if ($mtime === false) {
            return true;
        }

        return (time() - $mtime) >= $maxAgeSeconds;
    }

    /**
     * @inheritDoc
     */
    public function hasCacheFile(string $propertyId): bool
    {
        return file_exists($this->getAvailFilePath($propertyId));
    }

    /**
     * @inheritDoc
     */
    public function getCachedNights(string $propertyId): ?array
    {
        $availFile = $this->getAvailFilePath($propertyId);
        if (!file_exists($availFile)) {
            return null;
        }

        $content = @file_get_contents($availFile);
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return null;
        }

        /** @var list<string> $nights */
        $nights = array_values(array_filter($decoded, 'is_string'));
        sort($nights);

        return $nights;
    }

    /**
     * @return array<string|int, string>
     */
    public function getFeedUrls(): array
    {
        return $this->feedUrls;
    }

    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    private function getAvailFilePath(string $propertyId): string
    {
        return rtrim($this->cacheDir, '/\\') . '/avail_' . $propertyId . '.json';
    }

    private function getStatusFilePath(): string
    {
        return rtrim($this->cacheDir, '/\\') . '/' . self::STATUS_FILENAME;
    }

    /**
     * @return array<string|int, ChannelSyncStatus>
     */
    private function readAllStatuses(): array
    {
        $statusFile = $this->getStatusFilePath();
        if (!file_exists($statusFile)) {
            return [];
        }

        $content = @file_get_contents($statusFile);
        if ($content === false || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return [];
        }

        $statuses = [];
        foreach ($decoded as $propertyId => $data) {
            if (is_array($data)) {
                $statuses[(string) $propertyId] = ChannelSyncStatus::fromArray($data);
            }
        }

        return $statuses;
    }

    private function saveStatus(ChannelSyncStatus $status): void
    {
        $all = $this->readAllStatuses();
        $all[$status->getPropertyId()] = $status;

        $serialized = [];
        foreach ($all as $propertyId => $item) {
            $serialized[$propertyId] = $item->toArray();
        }

        $json = json_encode($serialized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            $this->atomicWrite($this->getStatusFilePath(), $json);
        }
    }

    private function atomicWrite(string $filePath, string $content): bool
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $tempFile = tempnam($dir, 'ovf_tmp_');
        if ($tempFile === false) {
            $tempFile = $filePath . '.' . bin2hex(random_bytes(4)) . '.tmp';
        }

        if (file_put_contents($tempFile, $content) === false) {
            @unlink($tempFile);
            return false;
        }

        @chmod($tempFile, 0664);

        if (!@rename($tempFile, $filePath)) {
            @unlink($filePath);
            if (!@rename($tempFile, $filePath)) {
                @unlink($tempFile);
                return false;
            }
        }

        return true;
    }

    private function buildDiagnosticErrorMessage(int $statusCode, ?string $transportError, string $body): string
    {
        if ($transportError !== null && $transportError !== '') {
            return "HTTP transport error: {$transportError}";
        }

        if ($statusCode !== 200) {
            return "Upstream HTTP {$statusCode}";
        }

        if (stripos($body, 'BEGIN:VCALENDAR') === false) {
            return 'Invalid iCalendar feed response: missing BEGIN:VCALENDAR';
        }

        return 'Unknown sync error';
    }
}
