<?php
/**
 * OceanViewFlats - Secure Guest Guide Access Endpoint
 * 
 * Authenticates reservation access tokens, gates property credentials per ADR 0001,
 * and releases physical door codes and Wi-Fi credentials only after Guest Registry
 * submission has been completed.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/utils.php';

use OceanViewFlats\Domain\Access\ConfigPropertyCredentialsProvider;
use OceanViewFlats\Domain\Access\GuideAccessService;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;

// Enforce security headers & CORS policy dynamically
enforce_security_headers_and_cors(['GET', 'POST', 'OPTIONS']);

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Rate limiting (60 requests per 10 minutes per IP)
enforce_rate_limit(
    'ovf_guide_access_rate_limits.json',
    60,
    600,
    'Too many requests. Please wait a few moments and try again.'
);

// Load configuration
$config = require __DIR__ . '/config.php';

// Extract input from POST JSON body or GET parameters
$code = '';
$lang = 'en';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $postData = json_decode($rawInput, true);
    if (!is_array($postData)) {
        $postData = $_POST;
    }
    $code = clean_input((string) ($postData['code'] ?? $postData['reservation_code'] ?? $postData['token'] ?? ''));
    $lang = get_validated_lang((string) ($postData['lang'] ?? ''));
} else {
    $code = clean_input((string) ($_GET['code'] ?? $_GET['reservation_code'] ?? $_GET['token'] ?? ''));
    $lang = get_validated_lang((string) ($_GET['lang'] ?? ''));
}

$all_translations = require __DIR__ . '/translations.php';
$t = $all_translations[$lang]['guide'] ?? $all_translations['en']['guide'];

if ($code === '') {
    http_response_code(400);
    send_json_response(false, $t['err_missing_code'], [
        'verified' => false,
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
        error_log('Guide access DB connection failure: ' . $e->getMessage());
    }
}

if ($pdo === null) {
    http_response_code(503);
    send_json_response(false, 'Database service is temporarily unavailable.', [
        'verified' => false,
        'status' => 'service_unavailable'
    ]);
}

try {
    $repository = new PdoReservationRepository($pdo);
    $credentialsProvider = new ConfigPropertyCredentialsProvider($config['credentials'] ?? []);
    $guideTranslations = [];
    foreach (['en', 'es', 'fr', 'it', 'de', 'ja'] as $loc) {
        if (isset($all_translations[$loc]['guide'])) {
            $guideTranslations[$loc] = $all_translations[$loc]['guide'];
        }
    }
    $guideAccessService = new GuideAccessService($repository, $credentialsProvider, '', $guideTranslations);

    $result = $guideAccessService->verifyAccess($code, $lang);
} catch (Throwable $e) {
    error_log('[Guide Access Error] ' . $e->getMessage());
    http_response_code(500);
    send_json_response(false, 'Internal server error verifying access credentials.', [
        'verified' => false,
        'status' => 'error'
    ]);
}

switch ($result->status) {
    case 'not_found':
        http_response_code(404);
        send_json_response(false, $result->message, $result->toArray());

    case 'concluded':
        http_response_code(403);
        send_json_response(false, $result->message, $result->toArray());

    case 'unauthorized':
        http_response_code(403);
        send_json_response(false, $result->message, $result->toArray());

    case 'registry_required':
        http_response_code(200);
        send_json_response(true, $result->message, $result->toArray());

    case 'verified':
    default:
        http_response_code(200);
        send_json_response(true, $result->message, $result->toArray());
}
