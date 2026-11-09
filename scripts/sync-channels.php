<?php
/**
 * OceanViewFlats - Background CLI Inbound Channel Synchronization Utility
 * PHP 8.3 Compatible
 *
 * Synchronizes external Online Travel Agency (Airbnb) iCalendar feeds
 * and updates local ephemeral calendar caches (avail_{property}.json)
 * and feed health status (channel_sync_status.json) without MySQL mutations (ADR 0002).
 *
 * Usage:
 *   php scripts/sync-channels.php [--force] [--property=1606|1707] [--help]
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

use OceanViewFlats\Domain\Reservation\Cli\SyncChannelsCommand;
use OceanViewFlats\Domain\Support\EnvLoader;

$root = dirname(__DIR__);
EnvLoader::load($root);

$rawArgs = $argv ?? ($_SERVER['argv'] ?? []);
$cliArgs = is_array($rawArgs) ? array_values(array_filter($rawArgs, 'is_string')) : [];

$exitCode = SyncChannelsCommand::run($cliArgs);
exit($exitCode);
