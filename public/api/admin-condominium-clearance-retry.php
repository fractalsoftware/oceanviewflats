<?php
/**
 * OceanViewFlats Admin Condominium Clearance Retry API
 * Authenticated Administrative Endpoint for 1-Click HOA Clearance Synchronization
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/utils.php';

use OceanViewFlats\Admin\Audit\AuditLogger;
use OceanViewFlats\Domain\Fulfillment\AdminContext;
use OceanViewFlats\Domain\Fulfillment\CondominiumClearanceRepositoryInterface;
use OceanViewFlats\Domain\Fulfillment\CondominiumClearanceSyncInterface;
use OceanViewFlats\Domain\Fulfillment\CurlHttpTransport;
use OceanViewFlats\Domain\Fulfillment\GuestRegistryRequiredException;
use OceanViewFlats\Domain\Fulfillment\HttpTransportInterface;
use OceanViewFlats\Domain\Fulfillment\HuespedManagerClearanceSync;
use OceanViewFlats\Domain\Fulfillment\PdoCondominiumClearanceRepository;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;
use OceanViewFlats\Domain\Reservation\ReservationNotFoundException;
use OceanViewFlats\Domain\Reservation\Search\PdoReservationSearchAdapter;

// 1. Enforce Security Headers, CORS, and Allowed Methods
enforce_security_headers_and_cors(['POST', 'OPTIONS']);

// 2. Manage Session State Safely
if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 28800; // 8 hours
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// 3. Authenticate Administrative Session
$adminUserId = $_SESSION['admin_user_id'] ?? null;
if ($adminUserId === null || (int) $adminUserId <= 0) {
    http_response_code(401);
    send_json_response(false, 'Unauthorized: Active administrative session required.');
}

// 4. Validate CSRF Token
$sessionCsrf = (string) ($_SESSION['csrf_token'] ?? '');
$headerCsrf = (string) (
    $_SERVER['HTTP_HX_CSRF_TOKEN']
    ?? $_SERVER['HTTP_X_CSRF_TOKEN']
    ?? ''
);

// Read input from request body with CLI STDIN support and $_POST fallback
$rawInput = file_get_contents('php://input');
if (($rawInput === false || $rawInput === '') && PHP_SAPI === 'cli' && defined('STDIN')) {
    $rawInput = @stream_get_contents(STDIN);
}
$input = !empty($rawInput) ? json_decode((string) $rawInput, true) : [];
if (!is_array($input)) {
    $input = [];
}
if (!empty($_POST)) {
    $input = array_merge($_POST, $input);
}

$bodyCsrf = (string) ($input['csrf_token'] ?? '');
$providedCsrf = $headerCsrf !== '' ? $headerCsrf : $bodyCsrf;

if ($sessionCsrf === '' || $providedCsrf === '' || !hash_equals($sessionCsrf, $providedCsrf)) {
    http_response_code(403);
    send_json_response(false, 'Forbidden: Invalid or missing CSRF token.');
}

// 5. Validate Required Input
$reservationUid = trim((string) ($input['reservation_uid'] ?? ''));
if ($reservationUid === '') {
    http_response_code(422);
    send_json_response(false, 'Missing required field: reservation_uid.');
}

// 6. Database Connection & Service Resolution
if (isset($GLOBALS['TEST_PDO']) && $GLOBALS['TEST_PDO'] instanceof PDO) {
    $pdo = $GLOBALS['TEST_PDO'];
} else {
    $config = require __DIR__ . '/config.php';
    $pdo = get_db_connection($config['db']);
}

$clearanceRepo = (isset($GLOBALS['TEST_CLEARANCE_REPO']) && $GLOBALS['TEST_CLEARANCE_REPO'] instanceof CondominiumClearanceRepositoryInterface)
    ? $GLOBALS['TEST_CLEARANCE_REPO']
    : new PdoCondominiumClearanceRepository($pdo);

if (isset($GLOBALS['TEST_CLEARANCE_SYNC']) && $GLOBALS['TEST_CLEARANCE_SYNC'] instanceof CondominiumClearanceSyncInterface) {
    $clearanceSync = $GLOBALS['TEST_CLEARANCE_SYNC'];
} else {
    /** @var HttpTransportInterface $clearanceTransport */
    $clearanceTransport = (isset($GLOBALS['TEST_CLEARANCE_TRANSPORT']) && $GLOBALS['TEST_CLEARANCE_TRANSPORT'] instanceof HttpTransportInterface)
        ? $GLOBALS['TEST_CLEARANCE_TRANSPORT']
        : new CurlHttpTransport();
    $clearanceSync = new HuespedManagerClearanceSync(
        repository: $clearanceRepo,
        reservationRepository: new PdoReservationRepository($pdo),
        searchAdapter: new PdoReservationSearchAdapter($pdo),
        transport: $clearanceTransport,
        auditLogger: new AuditLogger($pdo)
    );
}

// 7. Execute Condominium Clearance Synchronization
$currentUser = ['id' => (int) $adminUserId];
$adminContext = new AdminContext(
    adminUserId: $currentUser['id'],
    ipAddress: (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
    userAgent: (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
);

try {
    $clearance = $clearanceSync->syncForReservation($reservationUid, $adminContext);
} catch (ReservationNotFoundException) {
    http_response_code(404);
    send_json_response(false, 'Reservation not found.');
} catch (GuestRegistryRequiredException) {
    http_response_code(400);
    send_json_response(false, 'Guest registry must be submitted before condominium clearance can be synced.');
}

// 8. Return JSON Response
send_json_response(
    $clearance->isSynced(),
    $clearance->isSynced() ? 'Condominium clearance synchronization complete.' : ($clearance->errorMessage ?? 'Clearance synchronization failed.'),
    [
        'status' => $clearance->status,
        'clearance_number' => $clearance->clearanceNumber,
        'error' => $clearance->errorMessage,
        'attempts' => $clearance->attempts,
        'last_attempt_at' => $clearance->lastAttemptAt,
        'synced_at' => $clearance->syncedAt,
    ]
);
