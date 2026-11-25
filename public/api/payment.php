<?php
/**
 * OceanViewFlats - Secure Direct Booking & MercadoPago Checkout Processor
 * PHP 8 Compatible HTTP Adapter
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/utils.php';

use OceanViewFlats\Domain\Fulfillment\MaskedLogEmailSender;
use OceanViewFlats\Domain\Payment\BookingPaymentProcessor;
use OceanViewFlats\Domain\Payment\BookingPaymentRequest;
use OceanViewFlats\Domain\Support\AppEnvironment;

// Enforce security headers & CORS policy dynamically
enforce_security_headers_and_cors(['POST', 'OPTIONS']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    send_json_response(false, 'Method Not Allowed');
}

// Enforce valid referer origin (prevents direct address bar typing)
enforce_referer_check();

$config = require __DIR__ . '/config.php';

// Fail closed if CAPTCHA_SECRET is not configured outside explicit local/test environments
$captchaSecret = require_captcha_secret();

// Read JSON input from request body
$rawInput = file_get_contents('php://input');
if (($rawInput === false || $rawInput === '') && PHP_SAPI === 'cli' && defined('STDIN')) {
    $rawInput = @stream_get_contents(STDIN);
}
$data = !empty($rawInput) ? json_decode((string) $rawInput, true) : [];
if (!is_array($data)) {
    $data = [];
}

// Validate language and load translations
$lang = get_validated_lang($data['lang'] ?? '');
$allTranslations = require __DIR__ . '/translations.php';
$t = $allTranslations[$lang]['booking'] ?? $allTranslations['en']['booking'];

// Honeypot check
if (!empty($data['website_url'])) {
    send_json_response(true, 'Booking request received.');
}

// Enforce rate limiting
enforce_rate_limit('ovf_payments_rate_limits.json', 5, 600, $t['err_rate_limit']);

// Mathematical CAPTCHA or single-use stay-bound payment continuation token verification
$captchaErrors = ovf_captcha_error_messages($t);
$paymentToken = clean_input((string) ($data['payment_token'] ?? ''));
$reservationUidInput = clean_input((string) ($data['reservation_uid'] ?? ''));
$propertyIdInput = clean_input((string) ($data['property_id'] ?? ''));
$checkInInput = clean_input((string) ($data['check_in'] ?? ''));
$checkOutInput = clean_input((string) ($data['check_out'] ?? ''));

if ($paymentToken !== '') {
    $captchaCheck = verify_payment_captcha_token(
        $paymentToken,
        $reservationUidInput,
        $propertyIdInput,
        $checkInInput,
        $checkOutInput,
        $captchaSecret,
        $captchaErrors['sign'],
        $captchaErrors['expired'],
        $captchaErrors['replayed']
    );
} else {
    $captchaCheck = verify_captcha_challenge(
        clean_input((string) ($data['captcha_challenge'] ?? '')),
        clean_input((string) ($data['captcha_signature'] ?? '')),
        clean_input((string) ($data['captcha_response'] ?? '')),
        $captchaSecret,
        $captchaErrors['sign'],
        $captchaErrors['invalid'],
        $captchaErrors['wrong'],
        $captchaErrors['expired'],
        $captchaErrors['replayed']
    );
}
if ($captchaCheck !== true) {
    http_response_code(400);
    send_json_response(false, $captchaCheck);
}

// Connect to database and delegate to domain processor
try {
    $pdo = $GLOBALS['TEST_PDO'] ?? get_db_connection($config['db']);

    $request = BookingPaymentRequest::fromArray($data, $lang, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $processorOptions = [];
    if (isset($GLOBALS['TEST_PAYMENT_GATEWAY'])) {
        $processorOptions['gateway'] = $GLOBALS['TEST_PAYMENT_GATEWAY'];
    }

    if (isset($GLOBALS['TEST_EMAIL_SENDER'])) {
        $processorOptions['emailSender'] = $GLOBALS['TEST_EMAIL_SENDER'];
    } elseif (AppEnvironment::isNonProduction(allowPhpUnit: false)) {
        $processorOptions['emailSender'] = new MaskedLogEmailSender();
    }

    $processor = BookingPaymentProcessor::createDefault($pdo, $processorOptions);
    $result = $processor->processBookingPayment($request);

    http_response_code($result->httpStatusCode);
    send_json_response($result->success, $result->message, $result->extra);
} catch (Throwable $e) {
    error_log('[Payment API Error] ' . $e->getMessage());
    http_response_code(500);
    send_json_response(false, 'Internal server error processing payment.');
}
