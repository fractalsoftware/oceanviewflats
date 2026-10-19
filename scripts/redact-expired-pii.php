<?php
/**
 * OceanViewFlats - Scheduled CLI 90-Day Post-Checkout PII Retention Runner
 * PHP 8.3 Compatible
 *
 * Enforces the 90-day post-checkout retention window under Colombian Statutory
 * Law 1581 of 2012 and Decree 1377 of 2013 (Art. 11) per ADR 0012.
 * Atomically scrubs personal identification, contact details, door PINs,
 * vehicle data, clearance snapshots, and historical audit log PII while
 * preserving financial and occupancy reporting records.
 *
 * Usage:
 *   php scripts/redact-expired-pii.php [--days=90] [--help]
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

use OceanViewFlats\Domain\Privacy\Cli\RedactExpiredPiiCommand;
use OceanViewFlats\Domain\Support\EnvLoader;

$root = dirname(__DIR__);
EnvLoader::load($root);

$exitCode = RedactExpiredPiiCommand::run($argv);
exit($exitCode);
