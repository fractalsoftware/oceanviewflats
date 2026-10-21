<?php
/**
 * OceanViewFlats - Public Guest Registry Stay Lookup Endpoint
 *
 * Asynchronously retrieves non-sensitive reservation stay metadata (property, dates, primary guest name, completion status)
 * to hydrate the public Guest Registry form when accessed with a reservation code query parameter.
 *
 * Adheres to backend-api-standards and ADR 0001 (least privilege, zero credential disclosure).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/utils.php';

use OceanViewFlats\Domain\Reservation\PdoReservationRepository;
use OceanViewFlats\Domain\Reservation\ReservationStatus;

// Enforce security headers & CORS policy strictly (GET and OPTIONS only)
enforce_security_headers_and_cors(['GET', 'OPTIONS']);

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Rate limiting (60 requests per 10 minutes per IP)
enforce_rate_limit(
    'ovf_registry_lookup_rate_limits.json',
    60,
    600,
    'Too many requests. Please wait a few moments and try again.'
);

// Load configuration
$config = require __DIR__ . '/config.php';

// Extract input from GET parameters
$code = clean_input((string) ($_GET['code'] ?? $_GET['reservation_code'] ?? $_GET['token'] ?? ''));
$lang = get_validated_lang((string) ($_GET['lang'] ?? ''));

$all_translations = require __DIR__ . '/translations.php';
$guideTrans = $all_translations[$lang]['guide'] ?? $all_translations['en']['guide'];
$regTrans = $all_translations[$lang]['registry'] ?? $all_translations['en']['registry'];

if ($code === '') {
    http_response_code(400);
    send_json_response(false, $guideTrans['err_missing_code'] ?? 'Reservation code is required.', [
        'status' => 'not_found',
        'reason' => 'missing_code'
    ]);
}

// Establish DB connection
$pdo = $GLOBALS['TEST_PDO'] ?? null;
if ($pdo === null && !empty($config['db']['host']) && !empty($config['db']['dbname'])) {
    try {
        $pdo = get_db_connection($config['db']);
    } catch (PDOException $e) {
        error_log('Registry lookup DB connection failure: ' . $e->getMessage());
    }
}

if ($pdo === null) {
    http_response_code(503);
    send_json_response(false, 'Database service is temporarily unavailable.', [
        'status' => 'service_unavailable'
    ]);
}

try {
    $repository = new PdoReservationRepository($pdo);
    $reservation = $repository->findByUid($code);
} catch (Throwable $e) {
    error_log('[Registry Lookup Error] ' . $e->getMessage());
    http_response_code(500);
    send_json_response(false, 'Internal server error looking up reservation.', [
        'status' => 'error'
    ]);
}

if ($reservation === null) {
    http_response_code(404);
    send_json_response(false, $guideTrans['err_not_found'] ?? 'No reservation found matching this code.', [
        'status' => 'not_found'
    ]);
}

if ($reservation->status !== ReservationStatus::CONFIRMED) {
    http_response_code(403);
    send_json_response(false, $guideTrans['err_unauthorized'] ?? 'Reservation is not active or has been cancelled.', [
        'status' => 'unauthorized',
        'reservation_status' => $reservation->status->value
    ]);
}

if ($reservation->isConcluded()) {
    http_response_code(403);
    $concludedMessage = $regTrans['msg_concluded'] ?? $guideTrans['msg_concluded'] ?? 'This reservation has concluded and registration is closed.';
    send_json_response(false, $concludedMessage, [
        'status' => 'concluded',
        'message' => $concludedMessage,
    ]);
}

// Build sanitized public stay payload (Principle of Least Privilege: NO credentials, NO pricing, NO notes, NO phone/email)
$guideUrl = ($lang === 'en' ? 'guide/index.html' : "guide/{$lang}.html") . '?code=' . rawurlencode($reservation->reservationUid);

http_response_code(200);
send_json_response(true, 'Reservation details retrieved successfully.', [
    'status' => 'success',
    'reservation' => [
        'reservation_uid' => $reservation->reservationUid,
        'property_id' => $reservation->propertyId,
        'check_in' => $reservation->checkIn,
        'check_out' => $reservation->checkOut,
        'guest_name' => $reservation->guestName,
        'status' => $reservation->status->value,
        'registry_completed' => $reservation->registryCompleted,
        'max_guests' => $reservation->maxGuests,
    ],
    'guide_url' => $guideUrl,
]);
