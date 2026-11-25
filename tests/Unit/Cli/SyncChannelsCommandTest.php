<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Cli;

use OceanViewFlats\Domain\Reservation\ChannelSyncResult;
use OceanViewFlats\Domain\Reservation\ChannelSyncStatus;
use OceanViewFlats\Domain\Reservation\Cli\SyncChannelsCommand;
use OceanViewFlats\Domain\Reservation\InboundChannelSyncService;
use OceanViewFlats\Domain\Reservation\InboundChannelSyncServiceInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SyncChannelsCommandTest extends TestCase
{
    /**
     * Helper to invoke SyncChannelsCommand::run with in-memory streams.
     *
     * @param array<int, string> $args
     * @param ?InboundChannelSyncServiceInterface $service
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function executeCommand(array $args, ?InboundChannelSyncServiceInterface $service = null): array
    {
        $argv = array_merge(['scripts/sync-channels.php'], $args);
        $stdoutStream = fopen('php://temp', 'w+');
        $stderrStream = fopen('php://temp', 'w+');
        $this->assertNotFalse($stdoutStream);
        $this->assertNotFalse($stderrStream);

        $exitCode = SyncChannelsCommand::run($argv, $service, $stdoutStream, $stderrStream);

        rewind($stdoutStream);
        $stdout = (string) stream_get_contents($stdoutStream);
        fclose($stdoutStream);

        rewind($stderrStream);
        $stderr = (string) stream_get_contents($stderrStream);
        fclose($stderrStream);

        return [
            'exitCode' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    /**
     * Helper to execute scripts/sync-channels.php in a real CLI subprocess.
     *
     * @param array<int, string> $args
     * @param array<string, string> $environment
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function executeSubprocess(array $args, array $environment = []): array
    {
        $projectRoot = dirname(__DIR__, 3);
        $scriptPath = $projectRoot . '/scripts/sync-channels.php';
        $cmd = array_merge(['php', $scriptPath], $args);
        $env = array_merge($_ENV, $environment);

        $process = proc_open(
            $cmd,
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $projectRoot,
            $env
        );

        $this->assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    public function testHelpDisplaysUsageAndExitsZero(): void
    {
        $resLong = $this->executeCommand(['--help']);
        $this->assertSame(0, $resLong['exitCode']);
        $this->assertStringContainsString('OceanViewFlats - Inbound Channel Synchronization CLI', $resLong['stdout']);
        $this->assertStringContainsString('Usage:', $resLong['stdout']);
        $this->assertStringContainsString('--property=<id>', $resLong['stdout']);
        $this->assertStringContainsString('--force', $resLong['stdout']);
        $this->assertSame('', $resLong['stderr']);

        $resShort = $this->executeCommand(['-h']);
        $this->assertSame(0, $resShort['exitCode']);
        $this->assertStringContainsString('OceanViewFlats - Inbound Channel Synchronization CLI', $resShort['stdout']);
    }

    public function testRejectsUnrecognizedOption(): void
    {
        $res = $this->executeCommand(['--invalid-flag']);
        $this->assertSame(1, $res['exitCode']);
        $this->assertStringContainsString("Error: Unrecognized option '--invalid-flag'.", $res['stderr']);
        $this->assertStringContainsString('Run with --help', $res['stderr']);
    }

    public function testRejectsUnexpectedPositionalArgument(): void
    {
        $res = $this->executeCommand(['unexpected-positional']);
        $this->assertSame(1, $res['exitCode']);
        $this->assertStringContainsString("Error: Unexpected argument 'unexpected-positional'.", $res['stderr']);
    }

    public function testRejectsMissingPropertyArgumentValue(): void
    {
        // 1. Missing value after space
        $res1 = $this->executeCommand(['--property']);
        $this->assertSame(1, $res1['exitCode']);
        $this->assertStringContainsString("Option '--property' requires a property identifier.", $res1['stderr']);

        // 2. Empty value with equals
        $res2 = $this->executeCommand(['--property=']);
        $this->assertSame(1, $res2['exitCode']);
        $this->assertStringContainsString("Option '--property' requires a non-empty property identifier.", $res2['stderr']);

        // 3. Short flag -p missing value
        $res3 = $this->executeCommand(['-p']);
        $this->assertSame(1, $res3['exitCode']);
        $this->assertStringContainsString("Option '-p' requires a property identifier.", $res3['stderr']);

        // 4. Short flag -p= empty
        $res4 = $this->executeCommand(['-p=']);
        $this->assertSame(1, $res4['exitCode']);
        $this->assertStringContainsString("Option '-p' requires a non-empty property identifier.", $res4['stderr']);
    }

    public function testRejectsUnknownPropertyWhenConfiguredFeedsProvided(): void
    {
        $service = $this->createMock(InboundChannelSyncServiceInterface::class);
        $service->method('getFeedUrls')
            ->willReturn(['1606' => 'https://example.com/1606.ics', '1707' => 'https://example.com/1707.ics']);

        $res = $this->executeCommand(['--property=9999'], $service);
        $this->assertSame(1, $res['exitCode']);
        $this->assertStringContainsString("Error: Unknown property '9999'. Configured properties: 1606, 1707.", $res['stderr']);
    }

    public function testSyncAllPropertiesSuccessfully(): void
    {
        $now = date('c');
        $service = $this->createMock(InboundChannelSyncServiceInterface::class);

        $status1606 = new ChannelSyncStatus(
            propertyId: '1606',
            status: ChannelSyncStatus::STATUS_HEALTHY,
            lastSyncedAt: $now,
            lastAttemptedAt: $now,
            httpCode: 200,
            blockedNightsCount: 18,
            initiatedBy: 'cron'
        );
        $result1606 = new ChannelSyncResult(
            propertyId: '1606',
            status: $status1606,
            wasSkippedDueToCooldown: false,
            blockedNights: array_fill(0, 18, '2026-11-01')
        );

        $status1707 = new ChannelSyncStatus(
            propertyId: '1707',
            status: ChannelSyncStatus::STATUS_HEALTHY,
            lastSyncedAt: $now,
            lastAttemptedAt: $now,
            httpCode: 200,
            blockedNightsCount: 12,
            initiatedBy: 'cron'
        );
        $result1707 = new ChannelSyncResult(
            propertyId: '1707',
            status: $status1707,
            wasSkippedDueToCooldown: false,
            blockedNights: array_fill(0, 12, '2026-11-01')
        );

        $service->expects($this->once())
            ->method('syncAll')
            ->with(false, 'cron')
            ->willReturn([
                '1606' => $result1606,
                '1707' => $result1707,
            ]);

        $res = $this->executeCommand([], $service);

        $this->assertSame(0, $res['exitCode']);
        $this->assertStringContainsString('[OK] Channel Sync Completed (source: cron)', $res['stdout']);
        $this->assertStringContainsString('- Property 1606: healthy (18 blocked nights)', $res['stdout']);
        $this->assertStringContainsString('- Property 1707: healthy (12 blocked nights)', $res['stdout']);
        $this->assertSame('', $res['stderr']);
    }

    public function testSyncSinglePropertyWithEqualsSyntax(): void
    {
        $now = date('c');
        $service = $this->createMock(InboundChannelSyncServiceInterface::class);
        $service->method('getFeedUrls')
            ->willReturn(['1606' => 'https://example.com/1606.ics', '1707' => 'https://example.com/1707.ics']);

        $status1606 = new ChannelSyncStatus(
            propertyId: '1606',
            status: ChannelSyncStatus::STATUS_HEALTHY,
            lastSyncedAt: $now,
            lastAttemptedAt: $now,
            httpCode: 200,
            blockedNightsCount: 18,
            initiatedBy: 'cron'
        );
        $result1606 = new ChannelSyncResult(
            propertyId: '1606',
            status: $status1606,
            wasSkippedDueToCooldown: false,
            blockedNights: array_fill(0, 18, '2026-11-01')
        );

        $service->expects($this->once())
            ->method('sync')
            ->with('1606', false, 'cron')
            ->willReturn($result1606);

        $res = $this->executeCommand(['--property=1606'], $service);

        $this->assertSame(0, $res['exitCode']);
        $this->assertStringContainsString('[OK] Channel Sync Completed (source: cron)', $res['stdout']);
        $this->assertStringContainsString('- Property 1606: healthy (18 blocked nights)', $res['stdout']);
        $this->assertStringNotContainsString('Property 1707', $res['stdout']);
    }

    public function testSyncSinglePropertyWithSpaceSyntaxAndShortFlag(): void
    {
        $now = date('c');
        $service = $this->createMock(InboundChannelSyncServiceInterface::class);
        $service->method('getFeedUrls')
            ->willReturn(['1606' => 'https://example.com/1606.ics', '1707' => 'https://example.com/1707.ics']);

        $status1707 = new ChannelSyncStatus(
            propertyId: '1707',
            status: ChannelSyncStatus::STATUS_HEALTHY,
            lastSyncedAt: $now,
            lastAttemptedAt: $now,
            httpCode: 200,
            blockedNightsCount: 14,
            initiatedBy: 'cron'
        );
        $result1707 = new ChannelSyncResult(
            propertyId: '1707',
            status: $status1707,
            wasSkippedDueToCooldown: false,
            blockedNights: array_fill(0, 14, '2026-11-01')
        );

        $service->expects($this->once())
            ->method('sync')
            ->with('1707', false, 'cron')
            ->willReturn($result1707);

        $res = $this->executeCommand(['-p', '1707'], $service);

        $this->assertSame(0, $res['exitCode']);
        $this->assertStringContainsString('[OK] Channel Sync Completed (source: cron)', $res['stdout']);
        $this->assertStringContainsString('- Property 1707: healthy (14 blocked nights)', $res['stdout']);
    }

    public function testSyncWithForceFlagPassesTrueToService(): void
    {
        $now = date('c');
        $service = $this->createMock(InboundChannelSyncServiceInterface::class);
        $service->method('getFeedUrls')
            ->willReturn(['1606' => 'https://example.com/1606.ics']);

        $status1606 = new ChannelSyncStatus(
            propertyId: '1606',
            status: ChannelSyncStatus::STATUS_HEALTHY,
            lastSyncedAt: $now,
            lastAttemptedAt: $now,
            httpCode: 200,
            blockedNightsCount: 10,
            initiatedBy: 'cron'
        );
        $result1606 = new ChannelSyncResult(
            propertyId: '1606',
            status: $status1606,
            wasSkippedDueToCooldown: false,
            blockedNights: array_fill(0, 10, '2026-11-01')
        );

        $service->expects($this->once())
            ->method('sync')
            ->with('1606', true, 'cron')
            ->willReturn($result1606);

        $res = $this->executeCommand(['--property=1606', '--force'], $service);

        $this->assertSame(0, $res['exitCode']);
        $this->assertStringContainsString('[OK] Channel Sync Completed (source: cron)', $res['stdout']);
        $this->assertStringContainsString('- Property 1606: healthy (10 blocked nights)', $res['stdout']);
    }

    public function testSyncWithShortForceFlagPassesTrue(): void
    {
        $now = date('c');
        $service = $this->createMock(InboundChannelSyncServiceInterface::class);

        $status1606 = new ChannelSyncStatus(
            propertyId: '1606',
            status: ChannelSyncStatus::STATUS_HEALTHY,
            lastSyncedAt: $now,
            lastAttemptedAt: $now,
            httpCode: 200,
            blockedNightsCount: 5,
            initiatedBy: 'cron'
        );
        $result1606 = new ChannelSyncResult(
            propertyId: '1606',
            status: $status1606,
            wasSkippedDueToCooldown: false,
            blockedNights: array_fill(0, 5, '2026-11-01')
        );

        $service->expects($this->once())
            ->method('syncAll')
            ->with(true, 'cron')
            ->willReturn(['1606' => $result1606]);

        $res = $this->executeCommand(['-f'], $service);

        $this->assertSame(0, $res['exitCode']);
        $this->assertStringContainsString('[OK] Channel Sync Completed (source: cron)', $res['stdout']);
    }

    public function testPropertySkippedDueToCooldownRendersNotice(): void
    {
        $now = date('c');
        $service = $this->createMock(InboundChannelSyncServiceInterface::class);

        $status1606 = new ChannelSyncStatus(
            propertyId: '1606',
            status: ChannelSyncStatus::STATUS_HEALTHY,
            lastSyncedAt: $now,
            lastAttemptedAt: $now,
            httpCode: 200,
            blockedNightsCount: 18,
            initiatedBy: 'cron'
        );
        $result1606 = new ChannelSyncResult(
            propertyId: '1606',
            status: $status1606,
            wasSkippedDueToCooldown: true,
            blockedNights: array_fill(0, 18, '2026-11-01')
        );

        $service->expects($this->once())
            ->method('syncAll')
            ->willReturn(['1606' => $result1606]);

        $res = $this->executeCommand([], $service);

        $this->assertSame(0, $res['exitCode']);
        $this->assertStringContainsString('[OK] Channel Sync Completed (source: cron)', $res['stdout']);
        $this->assertStringContainsString('- Property 1606: healthy (18 blocked nights, skipped due to cooldown)', $res['stdout']);
    }

    public function testDegradedPropertyWithRetainedCacheOutputsWarningAndExitsZero(): void
    {
        $now = date('c');
        $service = $this->createMock(InboundChannelSyncServiceInterface::class);

        $status1606 = new ChannelSyncStatus(
            propertyId: '1606',
            status: ChannelSyncStatus::STATUS_DEGRADED,
            lastSyncedAt: $now,
            lastAttemptedAt: $now,
            httpCode: 500,
            blockedNightsCount: 18,
            errorMessage: 'Upstream fetch failed (HTTP 500)',
            initiatedBy: 'cron'
        );
        $result1606 = new ChannelSyncResult(
            propertyId: '1606',
            status: $status1606,
            wasSkippedDueToCooldown: false,
            blockedNights: array_fill(0, 18, '2026-11-01'),
            message: 'Upstream fetch failed (HTTP 500); retained 18 cached nights.'
        );

        $status1707 = new ChannelSyncStatus(
            propertyId: '1707',
            status: ChannelSyncStatus::STATUS_HEALTHY,
            lastSyncedAt: $now,
            lastAttemptedAt: $now,
            httpCode: 200,
            blockedNightsCount: 12,
            initiatedBy: 'cron'
        );
        $result1707 = new ChannelSyncResult(
            propertyId: '1707',
            status: $status1707,
            wasSkippedDueToCooldown: false,
            blockedNights: array_fill(0, 12, '2026-11-01')
        );

        $service->expects($this->once())
            ->method('syncAll')
            ->willReturn([
                '1606' => $result1606,
                '1707' => $result1707,
            ]);

        $res = $this->executeCommand([], $service);

        // Degraded retains cached blocks, maintaining guest calendar integrity without failing cron
        $this->assertSame(0, $res['exitCode']);
        $this->assertStringContainsString('[WARNING] Channel Sync Completed with warnings (source: cron)', $res['stdout']);
        $this->assertStringContainsString('- Property 1606: degraded (18 blocked nights, retained from cache - Upstream fetch failed (HTTP 500))', $res['stdout']);
        $this->assertStringContainsString('- Property 1707: healthy (12 blocked nights)', $res['stdout']);
    }

    public function testErrorPropertyWithoutCacheOutputsErrorAndExitsOne(): void
    {
        $now = date('c');
        $service = $this->createMock(InboundChannelSyncServiceInterface::class);

        $status1606 = new ChannelSyncStatus(
            propertyId: '1606',
            status: ChannelSyncStatus::STATUS_ERROR,
            lastSyncedAt: null,
            lastAttemptedAt: $now,
            httpCode: 404,
            blockedNightsCount: 0,
            errorMessage: 'Upstream fetch failed (HTTP 404)',
            initiatedBy: 'cron'
        );
        $result1606 = new ChannelSyncResult(
            propertyId: '1606',
            status: $status1606,
            wasSkippedDueToCooldown: false,
            blockedNights: [],
            message: 'Upstream fetch failed (HTTP 404); no cached calendar available.'
        );

        $service->expects($this->once())
            ->method('syncAll')
            ->willReturn(['1606' => $result1606]);

        $res = $this->executeCommand([], $service);

        $this->assertSame(1, $res['exitCode']);
        $this->assertStringContainsString('[ERROR] Channel Sync Completed with errors (source: cron)', $res['stdout']);
        $this->assertStringContainsString('- Property 1606: error (0 blocked nights, no cached dates available - Upstream fetch failed (HTTP 404))', $res['stdout']);
    }

    public function testFatalSynchronizationExceptionOutputsErrorAndExitsOne(): void
    {
        $service = $this->createMock(InboundChannelSyncServiceInterface::class);
        $service->expects($this->once())
            ->method('syncAll')
            ->willThrowException(new RuntimeException('Network socket timeout'));

        $res = $this->executeCommand([], $service);

        $this->assertSame(1, $res['exitCode']);
        $this->assertStringContainsString('[ERROR] Fatal synchronization error: Network socket timeout', $res['stderr']);
    }

    public function testWarningWhenNoFeedsConfigured(): void
    {
        $service = $this->createMock(InboundChannelSyncServiceInterface::class);
        $service->expects($this->once())
            ->method('syncAll')
            ->willReturn([]);

        $res = $this->executeCommand([], $service);

        $this->assertSame(0, $res['exitCode']);
        $this->assertStringContainsString('[WARNING] No channel feeds configured for synchronization.', $res['stdout']);
    }

    public function testSubprocessHelpExecution(): void
    {
        $res = $this->executeSubprocess(['--help']);

        $this->assertSame(0, $res['exitCode']);
        $this->assertStringContainsString('OceanViewFlats - Inbound Channel Synchronization CLI', $res['stdout']);
        $this->assertStringContainsString('Usage:', $res['stdout']);
        $this->assertSame('', $res['stderr']);
    }

    public function testWebExecutionRejectionWhenSapiIsNotCli(): void
    {
        // When accessed via web server (simulated via PHP_SAPI_OVERRIDE), script must reject with 403 and exit 1
        $res = $this->executeSubprocess([], ['PHP_SAPI_OVERRIDE' => 'fpm-fcgi']);

        $this->assertSame(1, $res['exitCode']);
        $this->assertStringContainsString('This script must be run from the command line.', $res['stdout']);
    }

    public function testSubprocessWithIsolatedConfigRejectsUnknownProperty(): void
    {
        $tempDir = sys_get_temp_dir() . '/ovf_cli_sync_test_' . uniqid();
        mkdir($tempDir . '/public/api', 0777, true);
        mkdir($tempDir . '/public/cache', 0777, true);
        file_put_contents(
            $tempDir . '/public/api/config.php',
            '<?php return ["ical_feeds" => ["1606" => "http://127.0.0.1:9999/dummy.ics"]];'
        );

        try {
            $res = $this->executeSubprocess(
                ['--property=1707'],
                ['OVF_CONFIG_ROOT' => $tempDir]
            );

            $this->assertSame(1, $res['exitCode']);
            $this->assertStringContainsString("Error: Unknown property '1707'. Configured properties: 1606.", $res['stderr']);
        } finally {
            @unlink($tempDir . '/public/api/config.php');
            @rmdir($tempDir . '/public/api');
            @rmdir($tempDir . '/public/cache');
            @rmdir($tempDir . '/public');
            @rmdir($tempDir);
        }
    }
}
