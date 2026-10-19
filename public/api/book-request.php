<?php
/**
 * OceanViewFlats - Secure Direct Booking Inquiry Processor
 * 
 * Validates request dates, prevents overlaps against cached Airbnb data,
 * computes night-by-night CSV rate sheets, logs to local MySQL database,
 * forwards to Google Sheet, and delivers details to host and guest.
 * Supports complete multi-language localization (EN, ES, FR, IT, DE, JA).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use OceanViewFlats\Domain\Fulfillment\PhpMailSender;
use OceanViewFlats\Domain\Reservation\ActorContext;
use OceanViewFlats\Domain\Reservation\DirectHoldRequest;
use OceanViewFlats\Domain\Reservation\PrimaryGuest;
use OceanViewFlats\Domain\Reservation\ReservationConflictException;
use OceanViewFlats\Domain\Reservation\ReservationValidationException;
use OceanViewFlats\Domain\Security\PiiMasker;
use OceanViewFlats\Infrastructure\Reservation\ReservationLifecycleEngineFactory;

// Load central utilities & configuration
require_once __DIR__ . '/utils.php';
$config = require __DIR__ . '/config.php';

// Configuration parameters
define('RECIPIENT_EMAIL', $_ENV['RECIPIENT_EMAIL'] ?? $_SERVER['RECIPIENT_EMAIL'] ?? getenv('RECIPIENT_EMAIL') ?: 'rentals@oceanviewflats.com');
define('CAPTCHA_SECRET', $_ENV['CAPTCHA_SECRET'] ?? $_SERVER['CAPTCHA_SECRET'] ?? getenv('CAPTCHA_SECRET') ?: 'securesaltsecret');

// Enforce security headers & CORS policy dynamically
enforce_security_headers_and_cors(['GET', 'POST', 'OPTIONS']);

// 1. Math Captcha Action (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'captcha') {
    $captcha = generate_captcha_challenge(CAPTCHA_SECRET);
    echo json_encode($captcha);
    exit;
}

// Reject non-POST submissions for checkout requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    send_json_response(false, 'Method Not Allowed');
}

// Enforce referer check for actual submissions (prevent direct script browsing)
enforce_referer_check();

// 2. Honeypot check (anti-spam)
$honeypot = $_POST['website_url'] ?? '';
if ($honeypot !== '') {
    send_json_response(true, 'Your reservation inquiry has been received (honeypot triggered).');
}

// 3. Multi-language Localization Dictionary Setup
$lang = get_validated_lang();

$all_translations = require __DIR__ . '/translations.php';
$t = $all_translations[$lang]['booking'] ?? $all_translations['en']['booking'];

// 4. Captcha Verification
$captcha_challenge = $_POST['captcha_challenge'] ?? '';
$captcha_signature = $_POST['captcha_signature'] ?? '';
$captcha_response = $_POST['captcha_response'] ?? '';

$captcha_check = verify_captcha_challenge(
    $captcha_challenge, 
    $captcha_signature, 
    $captcha_response, 
    CAPTCHA_SECRET,
    $t['err_captcha_sign'],
    $t['err_captcha_invalid'],
    $t['err_captcha_wrong']
);
if ($captcha_check !== true) {
    send_json_response(false, $captcha_check);
}

const RATE_LIMIT_FILE = 'ovf_booking_rate_limits.json';
const MAX_SUBMISSIONS = 3;
const RATE_LIMIT_WINDOW = 600; // 10 minutes (600 seconds)

// Record timestamp and enforce rate-limit
enforce_rate_limit(RATE_LIMIT_FILE, MAX_SUBMISSIONS, RATE_LIMIT_WINDOW, $t['err_rate_limit']);

// 5. Gather & Validate Core Input Details
$propertyId = clean_input($_POST['property_id'] ?? '');
$checkInStr = clean_input($_POST['check_in'] ?? '');
$checkOutStr = clean_input($_POST['check_out'] ?? '');
$guestName = clean_input($_POST['guest_name'] ?? '');
$guestEmail = clean_input($_POST['guest_email'] ?? '');
$guestPhone = clean_input($_POST['guest_phone'] ?? '');
$clientPriceCop = (float)($_POST['total_price_cop'] ?? 0);
$privacyConsentRaw = $_POST['privacy_consent'] ?? '';
$privacyPolicyVersionRaw = clean_input((string) ($_POST['privacy_policy_version'] ?? PRIVACY_POLICY_VERSION));

$hasPrivacyConsent = PiiMasker::isTruthyConsent($privacyConsentRaw);
if (!$hasPrivacyConsent) {
    http_response_code(400);
    send_json_response(false, $t['err_privacy_consent']);
}
$privacyPolicyVersion = $privacyPolicyVersionRaw !== '' ? $privacyPolicyVersionRaw : PRIVACY_POLICY_VERSION;
$privacyConsentAt = new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
$privacyConsentIp = clean_input((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
if ($privacyConsentIp === '') {
    $privacyConsentIp = '127.0.0.1';
}

if ($propertyId !== '1606' && $propertyId !== '1707') {
    send_json_response(false, $t['err_property']);
}
if (empty($guestName) || strlen($guestName) < 3) {
    send_json_response(false, $t['err_name']);
}
if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
    send_json_response(false, $t['err_email']);
}
if (empty($guestPhone) || strlen($guestPhone) < 6) {
    send_json_response(false, $t['err_phone']);
}

$checkIn = strtotime($checkInStr);
$checkOut = strtotime($checkOutStr);

if (!$checkIn || !$checkOut || $checkIn >= $checkOut) {
    send_json_response(false, $t['err_dates_invalid']);
}

if ($checkIn < strtotime(date('Y-m-d'))) {
    send_json_response(false, $t['err_dates_past']);
}

// 6. Overlap Booking Check, Quote Computation, and Atomic Hold via ReservationLifecycleEngine (ADR 0011)
$pdo = null;
if (!empty($config['db']['host']) && !empty($config['db']['dbname'])) {
    try {
        $pdo = get_db_connection($config['db']);
    } catch (PDOException $e) {
        error_log("Direct booking MySQL connection failed: " . $e->getMessage());
    }
}

$cacheDir = __DIR__ . '/../cache';
$lifecycleEngine = ReservationLifecycleEngineFactory::create($pdo, [
    'cacheDir' => $cacheDir,
]);

$holdRequest = DirectHoldRequest::create(
    propertyId: $propertyId,
    checkIn: $checkInStr,
    checkOut: $checkOutStr,
    primaryGuest: PrimaryGuest::create(
        name: $guestName,
        email: $guestEmail,
        phone: $guestPhone,
        lang: $lang
    ),
    actor: ActorContext::guest(ipAddress: $privacyConsentIp),
    privacyPolicyVersion: $privacyPolicyVersion,
    privacyConsentAt: $privacyConsentAt,
    privacyConsentIp: $privacyConsentIp
);

try {
    $holdResult = $lifecycleEngine->holdDirect($holdRequest);
} catch (ReservationValidationException $e) {
    if ($e->getErrorCode() === 'MINIMUM_STAY_VIOLATED') {
        send_json_response(false, sprintf($t['err_min_stay'], $e->getExpected() ?? 1, $e->getActual() ?? 1));
    }
    send_json_response(false, $t['err_dates_invalid']);
} catch (ReservationConflictException $e) {
    if ($e->isChannelBlock()) {
        $conflictDate = $e->getConflictDate() ?? $checkInStr;
        send_json_response(false, sprintf($t['err_overlap_airbnb'], $conflictDate));
    }
    send_json_response(false, $t['err_overlap_db']);
}

$reservation = $holdResult->reservation;
$quote = $holdResult->quote;
$uid = $reservation->reservationUid;

$datesCount = $quote->nightsCount();
$accommodationTotal = $quote->accommodationTotalCop();
$cleaningFee = $quote->cleaningFeeCop();
$resortFee = $quote->resortFeeCop();
$serverTotalCop = $quote->totalCop();

// Security verification: compare computed total against client total
if (abs($serverTotalCop - $clientPriceCop) > 1.0) {
    // Audit mismatch: log and enforce server resolution
    error_log("Direct booking pricing audit mismatch: Client: $clientPriceCop, Server: $serverTotalCop.");
}

$dbLogged = $pdo !== null;

// 10. Send Structured Emails (Host & Guest)
$copFormatter = "$ " . number_format($serverTotalCop, 0, ',', '.') . " COP";
$accommodationFormatted = "$ " . number_format($accommodationTotal, 0, ',', '.') . " COP";
$cleaningFormatted = "$ " . number_format($cleaningFee, 0, ',', '.') . " COP";
$resortFormatted = "$ " . number_format($resortFee, 0, ',', '.') . " COP";

$stayNightsLabel = sprintf($t['email_nights'], $datesCount);

// Email HTML content - fully localized for the guest!
$html_message = "
<html>
<head>
  <style>
    body { font-family: sans-serif; color: #333; line-height: 1.6; }
    .container { max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 12px; overflow: hidden; }
    .header { background-color: #f8fafc; padding: 24px; border-bottom: 1px solid #eee; text-align: center; }
    .body { padding: 24px; }
    .card { background-color: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e2e8f0; }
    .table { width: 100%; margin-top: 10px; border-collapse: collapse; }
    .table td { padding: 8px 0; border-bottom: 1px solid #edf2f7; }
    .table .bold { font-weight: bold; }
    .text-right { text-align: right; }
    .footer { font-size: 11px; color: #999; padding: 20px; text-align: center; border-top: 1px solid #eee; }
  </style>
</head>
<body>
  <div class='container'>
    <div class='header'>
      <h2 style='margin:0;color:#0f172a;'>{$t['email_title']}</h2>
      <p style='margin:5px 0 0 0;color:#64748b;'>OceanViewFlats Santa Marta</p>
    </div>
    <div class='body'>
      <p>" . sprintf($t['email_intro'], $guestName) . "</p>
      <p>{$t['email_received']}</p>
      
      <div class='card'>
        <h3 style='margin:0 0 10px 0;color:#0f172a;font-size:16px;'>{$t['email_summary']}</h3>
        <table width='100%' class='table'>
          <tr><td><strong>{$t['email_property']}:</strong></td><td class='text-right'>OceanViewFlats {$propertyId}</td></tr>
          <tr><td><strong>{$t['email_code']}:</strong></td><td class='text-right'><code style='background:#f1f5f9;padding:2px 6px;border-radius:4px;'>{$uid}</code></td></tr>
          <tr><td><strong>Check-In:</strong></td><td class='text-right'>{$checkInStr}</td></tr>
          <tr><td><strong>Check-Out:</strong></td><td class='text-right'>{$checkOutStr}</td></tr>
          <tr><td><strong>Estadía / Stay:</strong></td><td class='text-right'>{$stayNightsLabel}</td></tr>
        </table>
      </div>

      <div class='card'>
        <h3 style='margin:0 0 10px 0;color:#0f172a;font-size:16px;'>{$t['email_breakdown']}</h3>
        <table width='100%' class='table'>
          <tr><td>{$t['email_accommodation']}:</td><td class='text-right'>{$accommodationFormatted}</td></tr>
          <tr><td>{$t['email_cleaning']}:</td><td class='text-right'>{$cleaningFormatted}</td></tr>
          <tr><td>{$t['email_resort']}:</td><td class='text-right'>{$resortFormatted}</td></tr>
          <tr style='font-size:18px;font-weight:bold;'><td style='border-bottom:none;'>{$t['email_total']}:</td><td class='text-right' style='border-bottom:none;color:#059669;'>{$copFormatter}</td></tr>
        </table>
      </div>

      <p>{$t['email_footer']}</p>
      
      <p style='font-size:13px;color:#64748b;'><em>Inquiries automatically secured. DB storage: " . ($dbLogged ? 'YES' : 'NO') . ". Language Code: " . strtoupper($lang) . ".</em></p>
    </div>
    <div class='footer'>
      &copy; 2026 OceanViewFlats. Calle 26 # 2-80, Playa Salguero, Santa Marta, Colombia.
    </div>
  </div>
</body>
</html>
";

// Dispatch via PhpMailSender
$mailSender = new PhpMailSender();

// Send to host
$subjectHost = "NEW DIRECT BOOKING REQUEST: Prop $propertyId ($guestName) - [" . strtoupper($lang) . "]";
$mailSender->send(RECIPIENT_EMAIL, $subjectHost, $html_message);

// Send to guest as receipt (fully localized!)
$subjectGuest = sprintf($t['email_subject_guest'], $propertyId);
$mailSender->send($guestEmail, $subjectGuest, $html_message);

// Output successful response to client - fully localized!
$localizedMessage = "
  <div class='space-y-2'>
    <p class='font-bold text-base text-emerald-900'>{$t['msg_success_title']}</p>
    <p class='text-emerald-800 opacity-90 leading-relaxed'>" . sprintf($t['msg_success_desc1'], $propertyId, $uid) . "</p>
    <p class='text-emerald-800 opacity-90 leading-relaxed'>" . sprintf($t['msg_success_desc2'], $copFormatter, $guestEmail) . "</p>
  </div>
";

send_json_response(true, $localizedMessage, [
    'reservation_uid' => $uid,
    'total_price' => $serverTotalCop
]);
