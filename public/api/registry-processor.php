<?php
/**
 * OceanViewFlats Secure Guest Registry Processor
 * PHP 8 Compatible HTTP Adapter
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/utils.php';

use OceanViewFlats\Domain\Fulfillment\CurlHttpTransport;
use OceanViewFlats\Domain\Fulfillment\GuestLifecycleFulfillmentService;
use OceanViewFlats\Domain\Fulfillment\GuestLifecycleFulfillmentServiceInterface;
use OceanViewFlats\Domain\Fulfillment\GuestRegistrySubmission;
use OceanViewFlats\Domain\Fulfillment\HttpTransportInterface;
use OceanViewFlats\Domain\Fulfillment\HuespedManagerClearanceSync;
use OceanViewFlats\Domain\Fulfillment\PdoCondominiumClearanceRepository;

// Enforce security headers & CORS policy dynamically
enforce_security_headers_and_cors(['POST', 'OPTIONS']);

// Reject non-POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    send_json_response(false, 'Method Not Allowed');
}

// Enforce referer check for actual submissions (prevent direct script browsing)
enforce_referer_check();

// Read input from request body with CLI STDIN support and $_POST fallback
$rawInput = file_get_contents('php://input');
if (($rawInput === false || $rawInput === '') && PHP_SAPI === 'cli' && defined('STDIN')) {
    $rawInput = @stream_get_contents(STDIN);
}
$data = !empty($rawInput) ? json_decode((string) $rawInput, true) : [];
if (!is_array($data)) {
    $data = [];
}
if (!empty($_POST)) {
    $data = array_merge($_POST, $data);
}
if (empty($data['ip_address']) && isset($_SERVER['REMOTE_ADDR'])) {
    $data['ip_address'] = $_SERVER['REMOTE_ADDR'];
}

// Sanitize input array
$sanitizedData = [];
foreach ($data as $key => $val) {
    if (is_string($val)) {
        $sanitizedData[$key] = clean_input($val);
    } elseif (is_array($val)) {
        $sanitizedData[$key] = array_map(function ($item) {
            if (is_array($item)) {
                return array_map(fn ($v) => is_string($v) ? clean_input($v) : $v, $item);
            }
            return is_string($item) ? clean_input($item) : $item;
        }, $val);
    } else {
        $sanitizedData[$key] = $val;
    }
}
$data = $sanitizedData;

// Validate language and load translations
$lang = get_validated_lang((string) ($data['lang'] ?? ''));
$allTranslations = require __DIR__ . '/translations.php';
$t = $allTranslations[$lang]['registry'] ?? $allTranslations['en']['registry'];
$successMessage = $t['msg_success'] ?? 'Registration successfully processed.';

// Honeypot check (Abuse prevention)
if (!empty($data['website_hp']) || !empty($data['website_url'])) {
    send_json_response(true, $successMessage);
}

// Rate Limiting (Abuse prevention)
const RATE_LIMIT_FILE = 'ovf_registry_rate_limits.json';
const MAX_SUBMISSIONS = 5;
const RATE_LIMIT_WINDOW = 600; // 10 minutes

enforce_rate_limit(RATE_LIMIT_FILE, MAX_SUBMISSIONS, RATE_LIMIT_WINDOW, $t['err_rate_limit'] ?? 'Too many requests. Please wait a few minutes and try again.');

// Mathematical CAPTCHA Verification
$captchaSecret = (string) ($_ENV['CAPTCHA_SECRET'] ?? $_SERVER['CAPTCHA_SECRET'] ?? getenv('CAPTCHA_SECRET') ?: 'securesaltsecret');
$captchaCheck = verify_captcha_challenge(
    clean_input((string) ($data['captcha_challenge'] ?? '')),
    clean_input((string) ($data['captcha_signature'] ?? '')),
    clean_input((string) ($data['captcha_response'] ?? '')),
    $captchaSecret,
    $allTranslations[$lang]['booking']['err_captcha_sign'] ?? 'Security check failed. Please refresh the page and try again.',
    $allTranslations[$lang]['booking']['err_captcha_invalid'] ?? 'Invalid verification challenge.',
    $allTranslations[$lang]['booking']['err_captcha_wrong'] ?? 'Incorrect answer to the security verification question.'
);
if ($captchaCheck !== true) {
    send_json_response(false, $captchaCheck);
}

// Extract & validate primary guest email
$primaryEmail = trim((string) (
    $data['guest_email_1']
    ?? $data['guest_email']
    ?? $data['primary_email']
    ?? $data['primary_guest_email']
    ?? ($data['occupants'][0]['email'] ?? '')
));

if ($primaryEmail === '' || !filter_var($primaryEmail, FILTER_VALIDATE_EMAIL) || strlen($primaryEmail) > 100) {
    http_response_code(400);
    $emailError = $t['err_guest_email'] ?? '';
    send_json_response(false, $emailError, [
        'errors' => [$emailError],
    ]);
}
$data['guest_email_1'] = $primaryEmail;
$data['primary_guest_email'] = $primaryEmail;

// Extract & validate primary guest phone for modern structured form submissions
$isStructuredSubmission = isset($data['guest_first_name_1']) || isset($data['guest_last_name_1']);
$primaryPhone = trim((string) (
    $data['guest_phone_1']
    ?? $data['guest_phone']
    ?? $data['phone']
    ?? ($data['occupants'][0]['phone'] ?? '')
));

if ($isStructuredSubmission) {
    if ($primaryPhone === '' || strlen($primaryPhone) < 6 || strlen($primaryPhone) > 30) {
        http_response_code(400);
        $phoneError = $t['err_guest_phone_primary'] ?? 'Please provide a valid phone number for the primary guest.';
        send_json_response(false, $phoneError, [
            'errors' => [$phoneError],
        ]);
    }
} elseif ($primaryPhone !== '') {
    if (strlen($primaryPhone) < 6 || strlen($primaryPhone) > 30) {
        http_response_code(400);
        $phoneError = sprintf($t['err_guest_phone'] ?? 'Please enter a valid phone number for Guest %d.', 1);
        send_json_response(false, $phoneError, [
            'errors' => [$phoneError],
        ]);
    }
}

if ($primaryPhone !== '') {
    $data['guest_phone_1'] = $primaryPhone;
    $data['phone'] = $primaryPhone;
}

// Validate companion phone numbers if provided
$guestCountRaw = $data['guest_count'] ?? 1;
$guestCount = min(12, max(1, (int)$guestCountRaw));
for ($i = 2; $i <= $guestCount; $i++) {
    $compPhone = trim((string)($data["guest_phone_{$i}"] ?? ''));
    if ($compPhone !== '' && (strlen($compPhone) < 6 || strlen($compPhone) > 30)) {
        http_response_code(400);
        $phoneError = sprintf($t['err_guest_phone'] ?? 'Please enter a valid phone number for Guest %d.', $i);
        send_json_response(false, $phoneError, [
            'errors' => [$phoneError],
        ]);
    }
}

// Database & Service Resolution
if (isset($GLOBALS['TEST_LIFECYCLE_SERVICE']) && $GLOBALS['TEST_LIFECYCLE_SERVICE'] instanceof GuestLifecycleFulfillmentServiceInterface) {
    $service = $GLOBALS['TEST_LIFECYCLE_SERVICE'];
} else {
    if (isset($GLOBALS['TEST_PDO']) && $GLOBALS['TEST_PDO'] instanceof PDO) {
        $pdo = $GLOBALS['TEST_PDO'];
    } else {
        $config = require __DIR__ . '/config.php';
        $pdo = get_db_connection($config['db']);
    }

    $clearanceRepo = new PdoCondominiumClearanceRepository($pdo);
    /** @var HttpTransportInterface $clearanceTransport */
    $clearanceTransport = (isset($GLOBALS['TEST_CLEARANCE_TRANSPORT']) && $GLOBALS['TEST_CLEARANCE_TRANSPORT'] instanceof HttpTransportInterface)
        ? $GLOBALS['TEST_CLEARANCE_TRANSPORT']
        : new CurlHttpTransport();
    $clearanceSync = new HuespedManagerClearanceSync(
        repository: $clearanceRepo,
        transport: $clearanceTransport
    );

    $service = GuestLifecycleFulfillmentService::createDefault($pdo, [
        'condominium_clearance_sync' => $clearanceSync,
    ]);
}

try {
    $submission = GuestRegistrySubmission::fromArray($data);
    $result = $service->submitRegistry($submission);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    send_json_response(false, $e->getMessage(), [
        'errors' => [$e->getMessage()],
    ]);
}

if (!$result->success) {
    $firstError = !empty($result->errors) ? $result->errors[0] : 'Validation failed';
    http_response_code(400);
    send_json_response(false, $firstError, [
        'errors' => $result->errors,
    ]);
}

http_response_code(200);
send_json_response(true, $successMessage, [
    'door_code' => $result->doorCode,
    'guide_url' => $result->guideUrl,
    'reservation_code' => $result->reservation->reservationUid ?? $submission->reservationCode,
]);
