<?php
/**
 * OceanViewFlats - CLI Guest Registry Payload Re-Encryption Utility
 * PHP 8.3 Compatible
 *
 * Re-encrypts guest_registries.guests_payload rows (plaintext or encrypted under a
 * legacy/previous key such as the pre-hardening fallback seed) using the currently
 * configured APP_ENCRYPTION_KEY (ADR 0012).
 *
 * Dry-run by default; pass --apply to persist changes inside a transaction.
 *
 * Usage:
 *   php scripts/reencrypt-guest-registries.php [--from-legacy-fallback | --old-key=VALUE] [--apply] [--help]
 */

declare(strict_types=1);

$sapi = (isset($_SERVER['PHP_SAPI_OVERRIDE']) && is_string($_SERVER['PHP_SAPI_OVERRIDE']))
    ? $_SERVER['PHP_SAPI_OVERRIDE']
    : (defined('PHP_SAPI') ? PHP_SAPI : php_sapi_name());

if ($sapi !== 'cli') {
    http_response_code(403);
    echo "This script must be run from the command line.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use OceanViewFlats\Admin\Config\ConfigPathResolver;
use OceanViewFlats\Domain\Database\MigrationRunner;
use OceanViewFlats\Domain\Security\PayloadEncryptor;
use OceanViewFlats\Domain\Support\EnvLoader;

$root = dirname(__DIR__);
EnvLoader::load($root);

$rawArgs = $argv ?? ($_SERVER['argv'] ?? []);
$cliArgs = is_array($rawArgs) ? array_values(array_filter($rawArgs, 'is_string')) : [];

$apply = false;
$oldKey = PayloadEncryptor::LEGACY_FALLBACK_SEED;

foreach (array_slice($cliArgs, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        echo "Usage: php scripts/reencrypt-guest-registries.php [--from-legacy-fallback | --old-key=VALUE] [--apply]\n";
        exit(0);
    }
    if ($arg === '--apply') {
        $apply = true;
    } elseif ($arg === '--from-legacy-fallback') {
        $oldKey = PayloadEncryptor::LEGACY_FALLBACK_SEED;
    } elseif (str_starts_with($arg, '--old-key=')) {
        $oldKey = substr($arg, strlen('--old-key='));
    }
}

if (!PayloadEncryptor::hasConfiguredKey()) {
    fwrite(STDERR, "ERROR: APP_ENCRYPTION_KEY must be configured in the environment before re-encrypting.\n");
    exit(1);
}

try {
    $configPath = ConfigPathResolver::resolveConfigPath($root);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}

/** @var array{db: array{host?: string, dbname?: string, user?: string, pass?: string}} $config */
$config = require $configPath;
$pdo = MigrationRunner::connect($config['db']);

$stmt = $pdo->query('SELECT id, reservation_uid, guests_payload FROM guest_registries ORDER BY id ASC');
if ($stmt === false) {
    fwrite(STDERR, "ERROR: Failed to query guest_registries.\n");
    exit(1);
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$alreadyCurrent = 0;
$migrated = 0;
$skippedEmpty = 0;
$unreadable = 0;

if ($apply) {
    $pdo->beginTransaction();
}

try {
    $updateStmt = $pdo->prepare('UPDATE guest_registries SET guests_payload = :payload WHERE id = :id');

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $uid = (string) ($row['reservation_uid'] ?? '');
        $stored = (string) ($row['guests_payload'] ?? '');

        if ($stored === '' || $stored === '[]') {
            $skippedEmpty++;
            continue;
        }

        if (PayloadEncryptor::isEncryptedEnvelope($stored)) {
            $currentDecrypted = PayloadEncryptor::decrypt($stored);
            if ($currentDecrypted !== []) {
                $alreadyCurrent++;
                continue;
            }

            $legacyDecrypted = PayloadEncryptor::decrypt($stored, $oldKey);
            if ($legacyDecrypted === []) {
                $unreadable++;
                fwrite(STDERR, "WARN: Could not decrypt row id={$id} (reservation={$uid}) with current or old key.\n");
                continue;
            }

            $reencrypted = PayloadEncryptor::encrypt($legacyDecrypted);
            if ($apply) {
                $updateStmt->execute([':payload' => $reencrypted, ':id' => $id]);
            }
            $migrated++;
            continue;
        }

        // Legacy plaintext JSON array
        $plaintextOccupants = PayloadEncryptor::decrypt($stored);
        if ($plaintextOccupants === []) {
            $skippedEmpty++;
            continue;
        }

        $reencrypted = PayloadEncryptor::encrypt($plaintextOccupants);
        if ($apply) {
            $updateStmt->execute([':payload' => $reencrypted, ':id' => $id]);
        }
        $migrated++;
    }

    if ($apply && $pdo->inTransaction()) {
        $pdo->commit();
    }
} catch (Throwable $e) {
    if ($apply && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'ERROR: Re-encryption failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$mode = $apply ? 'APPLIED' : 'DRY-RUN';
echo sprintf(
    "[%s] scanned=%d migrated=%d already_current=%d skipped_empty=%d unreadable=%d\n",
    $mode,
    count($rows),
    $migrated,
    $alreadyCurrent,
    $skippedEmpty,
    $unreadable
);

exit($unreadable > 0 ? 2 : 0);
