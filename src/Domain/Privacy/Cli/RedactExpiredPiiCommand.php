<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Privacy\Cli;

use DateTimeImmutable;
use InvalidArgumentException;
use OceanViewFlats\Admin\Config\ConfigPathResolver;
use OceanViewFlats\Domain\Database\MigrationRunner;
use OceanViewFlats\Domain\Privacy\PdoPiiRedactionService;
use OceanViewFlats\Domain\Privacy\PiiRedactionServiceInterface;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;
use OceanViewFlats\Domain\Support\EnvLoader;
use PDO;
use Throwable;

/**
 * Command-line runner for the 90-day post-checkout PII retention policy (ADR 0012 / Ley 1581 Art. 11).
 *
 * Usage:
 *   php scripts/redact-expired-pii.php [--days=90] [--help]
 */
final class RedactExpiredPiiCommand
{
    /**
     * Executes the CLI PII retention runner.
     *
     * @param array<int, string> $argv
     * @param ?PiiRedactionServiceInterface $service
     * @param ?PDO $pdo
     * @param ?DateTimeImmutable $now
     * @param mixed $stdout Stream resource or null for STDOUT
     * @param mixed $stderr Stream resource or null for STDERR
     * @return int Shell exit code (0 on success, 1 on error)
     */
    public static function run(
        array $argv,
        ?PiiRedactionServiceInterface $service = null,
        ?PDO $pdo = null,
        ?DateTimeImmutable $now = null,
        mixed $stdout = null,
        mixed $stderr = null
    ): int {
        $out = is_resource($stdout) ? $stdout : (defined('STDOUT') ? STDOUT : fopen('php://stdout', 'w'));
        $err = is_resource($stderr) ? $stderr : (defined('STDERR') ? STDERR : fopen('php://stderr', 'w'));

        try {
            $parsed = self::parseArguments($argv);
        } catch (InvalidArgumentException $e) {
            self::writeStream($err, 'Error: ' . $e->getMessage() . "\n");
            self::writeStream($err, "Run with --help for usage information.\n");
            return 1;
        }

        if ($parsed['help']) {
            self::printHelp($out);
            return 0;
        }

        $retentionDays = $parsed['days'];

        try {
            $activeService = $service ?? self::resolveDefaultService($pdo);
            $redactedCount = $activeService->redactExpiredConcludedReservations($retentionDays, $now);
        } catch (Throwable $e) {
            self::writeStream($err, '[ERROR] PII Redaction Failed: ' . $e->getMessage() . "\n");
            return 1;
        }

        self::writeStream(
            $out,
            sprintf(
                "[OK] PII Redaction Completed: %d expired concluded reservation(s) redacted (retention window: %d days).\n",
                $redactedCount,
                $retentionDays
            )
        );

        return 0;
    }

    /**
     * Parses CLI arguments into structured options.
     *
     * @param array<int, string> $argv
     * @return array{help: bool, days: int}
     * @throws InvalidArgumentException On invalid option or negative/non-integer days value
     */
    public static function parseArguments(array $argv): array
    {
        $help = false;
        $days = PiiRedactionServiceInterface::DEFAULT_RETENTION_DAYS;

        $count = count($argv);
        $start = (isset($argv[0]) && !str_starts_with($argv[0], '-')) ? 1 : 0;

        for ($i = $start; $i < $count; $i++) {
            $arg = trim($argv[$i]);
            if ($arg === '') {
                continue;
            }

            if ($arg === '--help' || $arg === '-h') {
                $help = true;
                continue;
            }

            if (str_starts_with($arg, '--days=')) {
                $val = trim(substr($arg, 7));
                $days = self::validateDays($val, '--days');
                continue;
            }

            if (str_starts_with($arg, '-d=')) {
                $val = trim(substr($arg, 3));
                $days = self::validateDays($val, '-d');
                continue;
            }

            if ($arg === '--days' || $arg === '-d') {
                if ($i + 1 >= $count) {
                    throw new InvalidArgumentException("Option '{$arg}' requires an integer number of days.");
                }
                $i++;
                $val = trim($argv[$i]);
                $days = self::validateDays($val, $arg);
                continue;
            }

            if (str_starts_with($arg, '-')) {
                throw new InvalidArgumentException("Unrecognized option '{$arg}'.");
            }

            throw new InvalidArgumentException("Unexpected argument '{$arg}'.");
        }

        return [
            'help' => $help,
            'days' => $days,
        ];
    }

    public static function printHelp(mixed $out): void
    {
        $help = <<<HELP
OceanViewFlats - Post-Checkout PII Redaction CLI (Ley 1581 / ADR 0012)

Usage:
  php scripts/redact-expired-pii.php [options]

Options:
  -d, --days=<int>   Retention window in days after check-out (default: 90)
  -h, --help         Display this help message

Examples:
  # Redact concluded stays older than 90 days
  php scripts/redact-expired-pii.php

  # Redact concluded stays older than 30 days
  php scripts/redact-expired-pii.php --days=30

HELP;

        self::writeStream($out, $help);
    }

    private static function validateDays(string $val, string $optionName): int
    {
        if ($val === '' || filter_var($val, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException("Option '{$optionName}' requires a non-negative integer value.");
        }

        $intVal = (int) $val;
        if ($intVal < 0) {
            throw new InvalidArgumentException("Option '{$optionName}' requires a non-negative integer value.");
        }

        return $intVal;
    }

    private static function resolveDefaultService(?PDO $injectedPdo = null): PiiRedactionServiceInterface
    {
        if ($injectedPdo instanceof PDO) {
            return new PdoPiiRedactionService($injectedPdo, new PdoReservationRepository($injectedPdo));
        }

        $sqliteTestDb = $_ENV['OVF_TEST_SQLITE_DB'] ?? $_SERVER['OVF_TEST_SQLITE_DB'] ?? getenv('OVF_TEST_SQLITE_DB');
        if (is_string($sqliteTestDb) && $sqliteTestDb !== '') {
            $pdo = new PDO('sqlite:' . $sqliteTestDb);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return new PdoPiiRedactionService($pdo, new PdoReservationRepository($pdo));
        }

        $root = getenv('OVF_CONFIG_ROOT') ?: dirname(__DIR__, 4);
        EnvLoader::load($root);

        $configPath = ConfigPathResolver::resolveConfigPath($root);
        /** @var mixed $config */
        $config = require $configPath;
        $dbConfig = is_array($config) && isset($config['db']) && is_array($config['db']) ? $config['db'] : [];

        $pdo = MigrationRunner::connect($dbConfig);

        return new PdoPiiRedactionService($pdo, new PdoReservationRepository($pdo));
    }

    private static function writeStream(mixed $stream, string $data): void
    {
        if (is_resource($stream)) {
            fwrite($stream, $data);
        } else {
            echo $data;
        }
    }
}
