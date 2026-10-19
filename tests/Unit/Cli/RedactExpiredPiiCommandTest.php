<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Cli;

use DateTimeImmutable;
use DateTimeZone;
use OceanViewFlats\Domain\Database\MigrationRunner;
use OceanViewFlats\Domain\Privacy\Cli\RedactExpiredPiiCommand;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use PDO;
use PHPUnit\Framework\TestCase;

final class RedactExpiredPiiCommandTest extends TestCase
{
    /**
     * @param array<int, string> $args
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function executeCommand(array $args, ?PDO $pdo = null, ?DateTimeImmutable $now = null): array
    {
        $argv = array_merge(['scripts/redact-expired-pii.php'], $args);
        $stdoutStream = fopen('php://temp', 'w+');
        $stderrStream = fopen('php://temp', 'w+');

        $exitCode = RedactExpiredPiiCommand::run(
            argv: $argv,
            service: null,
            pdo: $pdo,
            now: $now,
            stdout: $stdoutStream,
            stderr: $stderrStream
        );

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

    public function testHelpDisplaysUsageAndExitsZero(): void
    {
        $res = $this->executeCommand(['--help']);
        $this->assertSame(0, $res['exitCode']);
        $this->assertStringContainsString('OceanViewFlats - Post-Checkout PII Redaction CLI', $res['stdout']);
        $this->assertStringContainsString('--days=<int>', $res['stdout']);
        $this->assertSame('', $res['stderr']);
    }

    public function testRejectsInvalidArguments(): void
    {
        $resInvalidFlag = $this->executeCommand(['--unknown']);
        $this->assertSame(1, $resInvalidFlag['exitCode']);
        $this->assertStringContainsString("Error: Unrecognized option '--unknown'.", $resInvalidFlag['stderr']);

        $resNegativeDays = $this->executeCommand(['--days=-5']);
        $this->assertSame(1, $resNegativeDays['exitCode']);
        $this->assertStringContainsString("Option '--days' requires a non-negative integer value.", $resNegativeDays['stderr']);
    }

    public function testRunsRetentionRedactionAndOutputsSummary(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        MigrationRunner::run($pdo, 'sqlite_test');

        $repo = new PdoReservationRepository($pdo);
        $tz = new DateTimeZone('America/Bogota');
        $now = new DateTimeImmutable('2026-10-19 10:00:00', $tz);

        $repo->save(Reservation::create(
            reservationUid: 'res-cli-old-1',
            propertyId: '1707',
            guestName: 'Old Guest 1',
            guestEmail: 'old1@example.com',
            guestPhone: '+573001112233',
            checkIn: '2026-05-01',
            checkOut: '2026-05-05',
            totalPrice: 1500000.0,
            status: ReservationStatus::CONFIRMED
        ));

        $repo->save(Reservation::create(
            reservationUid: 'res-cli-recent-1',
            propertyId: '1606',
            guestName: 'Recent Guest 1',
            guestEmail: 'recent1@example.com',
            guestPhone: '+573004445566',
            checkIn: '2026-10-01',
            checkOut: '2026-10-05',
            totalPrice: 1200000.0,
            status: ReservationStatus::CONFIRMED
        ));

        $res = $this->executeCommand(['--days=90'], $pdo, $now);
        $this->assertSame(0, $res['exitCode']);
        $this->assertSame('', $res['stderr']);
        $this->assertStringContainsString(
            '[OK] PII Redaction Completed: 1 expired concluded reservation(s) redacted (retention window: 90 days).',
            $res['stdout']
        );

        $this->assertSame('Redacted Guest', $repo->findByUid('res-cli-old-1')?->guestName);
        $this->assertSame('Recent Guest 1', $repo->findByUid('res-cli-recent-1')?->guestName);
    }

    public function testCliScriptSubprocessExitsZero(): void
    {
        $tmpDb = tempnam(sys_get_temp_dir(), 'ovf_pii_cli_');
        $this->assertNotFalse($tmpDb);

        try {
            $pdo = new PDO('sqlite:' . $tmpDb);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            MigrationRunner::run($pdo, 'sqlite_test');

            $repo = new PdoReservationRepository($pdo);
            $repo->save(Reservation::create(
                reservationUid: 'res-subproc-1',
                propertyId: '1707',
                guestName: 'Subprocess Guest',
                guestEmail: 'subproc@example.com',
                guestPhone: '+573009998877',
                checkIn: '2025-01-01',
                checkOut: '2025-01-05',
                totalPrice: 1000000.0,
                status: ReservationStatus::CONFIRMED
            ));
            unset($pdo);

            $projectRoot = dirname(__DIR__, 3);
            $scriptPath = $projectRoot . '/scripts/redact-expired-pii.php';
            $env = array_merge($_ENV, ['OVF_TEST_SQLITE_DB' => $tmpDb]);

            $process = proc_open(
                ['php', $scriptPath, '--days=90'],
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

            $this->assertSame(0, $exitCode);
            $this->assertSame('', $stderr);
            $this->assertStringContainsString('1 expired concluded reservation(s) redacted', $stdout);
        } finally {
            if (is_file($tmpDb)) {
                @unlink($tmpDb);
            }
        }
    }
}
