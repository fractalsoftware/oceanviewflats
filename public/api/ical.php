<?php
/**
 * OceanViewFlats - Outbound iCal Calendar Export Endpoint
 * 
 * Generates real-time iCalendar (.ics) feed of confirmed and active pending holds
 * for import into the Airbnb Host Portal to prevent dual bookings.
 * 
 * Query: GET /api/ical.php?property=[1606|1707]
 */

// 1. Prevent direct web execution if included (Security Best Practice)
if (count(get_included_files()) === 1 && !defined('ALLOW_ICAL_RUN')) {
    define('ALLOW_ICAL_RUN', true);
}

// 2. Load Composer Autoloader & Shared Utilities
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/utils.php';

use OceanViewFlats\Domain\Reservation\PdoMaintenanceBlockSource;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;

// Enforce security headers & CORS policy dynamically
enforce_security_headers_and_cors(['GET', 'OPTIONS']);

// Enforce rate-limit to prevent DOS/scraping attacks on calendar exports
enforce_rate_limit('ovf_ical_rate_limits.json', 100, 600, 'Too many requests. Please wait a few minutes and try again.');

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    exit("Internal Server Error: Missing config.");
}
$config = require $configPath;

// 3. Extract and Validate Property ID
$propertyId = $_GET['property'] ?? $_GET['prop'] ?? null;
if (!in_array($propertyId, ['1606', '1707'])) {
    http_response_code(400);
    exit("Bad Request: Invalid or missing property parameter.");
}

// 4. Initialize PDO Connection using shared library
try {
    $pdo = $GLOBALS['TEST_PDO'] ?? get_db_connection($config['db']);
} catch (Throwable $e) {
    http_response_code(500);
    exit("Internal Server Error: Database offline.");
}

// 5. Fetch Active Direct Bookings & Maintenance Holds (ADR 0003 & ADR 0006)
try {
    $repository = new PdoReservationRepository($pdo);
    $activeReservations = $repository->findActiveByProperty($propertyId);
    $maintenanceBlockSource = new PdoMaintenanceBlockSource($pdo);
    $maintenanceBlocks = $maintenanceBlockSource->getBlocks($propertyId);
} catch (Throwable $e) {
    http_response_code(500);
    exit("Internal Server Error: Failed to fetch calendar records.");
}

// 6. Set proper iCalendar headers
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="oceanviewflats-' . $propertyId . '.ics"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// 7. Format Timestamp Helper
function formatICalDate($dateStr) {
    return date('Ymd', strtotime($dateStr));
}
function formatICalDateTime($dateTimeStr) {
    return date('Ymd\THis\Z', strtotime($dateTimeStr) - date('Z')); // Convert to UTC
}

// 8. Generate iCalendar Stream
echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//OceanViewFlats//Direct Booking Sync//EN\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";

foreach ($activeReservations as $res) {
    // Exclude external platform reservations (e.g. Airbnb) to prevent circular sync echoes back to Airbnb (ADR 0007)
    if ($res->isExternal()) {
        continue;
    }

    $uid = $res->reservationUid . '@oceanviewflats.com';
    $dtstamp = formatICalDateTime($res->createdAt->format('Y-m-d H:i:s'));
    $dtstart = formatICalDate($res->checkIn);
    $dtend = formatICalDate($res->checkOut);

    echo "BEGIN:VEVENT\r\n";
    echo "UID:" . $uid . "\r\n";
    echo "DTSTAMP:" . $dtstamp . "\r\n";
    echo "DTSTART;VALUE=DATE:" . $dtstart . "\r\n";
    echo "DTEND;VALUE=DATE:" . $dtend . "\r\n";
    echo "STATUS:CONFIRMED\r\n";
    echo "SUMMARY:Blocked - OceanViewFlats Direct Booking\r\n";
    echo "END:VEVENT\r\n";
}

foreach ($maintenanceBlocks as $block) {
    $uid = ($block->id !== null ? 'block-' . $block->id : 'block-' . md5($block->startDate . $block->endDate)) . '@oceanviewflats.com';
    $dtstamp = $block->createdAt !== null
        ? formatICalDateTime($block->createdAt->format('Y-m-d H:i:s'))
        : formatICalDateTime('now');
    $dtstart = formatICalDate($block->startDate);
    $dtend = formatICalDate($block->endDate);

    echo "BEGIN:VEVENT\r\n";
    echo "UID:" . $uid . "\r\n";
    echo "DTSTAMP:" . $dtstamp . "\r\n";
    echo "DTSTART;VALUE=DATE:" . $dtstart . "\r\n";
    echo "DTEND;VALUE=DATE:" . $dtend . "\r\n";
    echo "STATUS:CONFIRMED\r\n";
    echo "SUMMARY:Maintenance Hold\r\n";
    echo "END:VEVENT\r\n";
}

echo "END:VCALENDAR\r\n";
