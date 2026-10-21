<?php
/**
 * OceanViewFlats - Availability Sync API
 * 
 * Fetches, parses, and caches public Airbnb iCal feeds for properties 1606 and 1707.
 * Prevents double bookings by disabling already-booked dates in the frontend calendar.
 */

// Load Composer autoloader & central utilities
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/utils.php';

use OceanViewFlats\Domain\Reservation\InboundChannelSyncService;
use OceanViewFlats\Domain\Reservation\InboundChannelSyncServiceInterface;
use OceanViewFlats\Domain\Reservation\ReservationLedger;

// Enforce security headers & CORS policy dynamically
enforce_security_headers_and_cors(['GET', 'OPTIONS']);

// Validate property parameter
$propertyId = isset($_GET['property']) ? $_GET['property'] : '1606';
if ($propertyId !== '1606' && $propertyId !== '1707') {
    http_response_code(400);
    echo json_encode(["error" => "Invalid property ID. Must be 1606 or 1707."]);
    exit();
}

// Enforce rate-limit for availability inquiries (avoids API scraping/abuse)
enforce_rate_limit('ovf_avail_rate_limits.json', 60, 600, 'Too many requests. Please wait a few minutes and try again.');

// Load configuration
$config = require __DIR__ . '/config.php';
$icalFeeds = isset($config['ical_feeds']) ? $config['ical_feeds'] : [];

if (!isset($icalFeeds[$propertyId])) {
    http_response_code(500);
    echo json_encode(["error" => "No feed configured for property ID: " . $propertyId]);
    exit();
}

// Define caching directory for upstream Airbnb calendar blocks (ADR 0002)
$cacheDir = __DIR__ . '/../cache';
if (!file_exists($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

try {
    /** @var InboundChannelSyncServiceInterface $syncService */
    $syncService = $GLOBALS['TEST_CHANNEL_SYNC_SERVICE'] ?? new InboundChannelSyncService($icalFeeds, $cacheDir);
    $cacheLifetime = 15 * 60; // 15 minutes (in seconds)

    if ($syncService->isCacheStale($propertyId, $cacheLifetime)) {
        $syncResult = $syncService->sync($propertyId, force: false, initiatedBy: 'public_traffic');

        // Fallback logic if feed fetch fails and no cached calendar exists
        if ($syncResult->getStatus()->isError() && !$syncService->hasCacheFile($propertyId)) {
            http_response_code(502);
            echo json_encode([
                "error" => "Failed to retrieve calendar feed from Airbnb.",
                "status_code" => $syncResult->getStatus()->getHttpCode() ?? 502,
            ]);
            exit();
        }
        // Stale cache will be read by FileCacheChannelBlockSource and ReservationLedger
    }

    // Establish database connection to evaluate active direct reservations (ADR 0003)
    $pdo = $GLOBALS['TEST_PDO'] ?? null;
    if ($pdo === null && !empty($config['db']['host']) && !empty($config['db']['dbname'])) {
        try {
            $pdo = get_db_connection($config['db']);
        } catch (PDOException $e) {
            error_log("Database connection failed in availability.php: " . $e->getMessage());
        }
    }

    // Query Authoritative Reservation Ledger: merges ephemeral channel blocks & active direct reservations
    $ledger = ReservationLedger::createDefault($pdo, $cacheDir);
    $allBlocked = $ledger->getBlockedNights($propertyId);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($allBlocked);
} catch (Throwable $e) {
    error_log('[Availability API Error] ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["error" => "Internal server error checking availability."]);
    exit();
}

