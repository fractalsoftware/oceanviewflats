<?php
/**
 * OceanViewFlats - Secure Direct Booking & MercadoPago Checkout Processor
 * PHP 8 Compatible HTTP Adapter
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/utils.php';

use OceanViewFlats\Domain\Payment\BookingPaymentProcessor;
use OceanViewFlats\Domain\Payment\BookingPaymentRequest;

// Enforce security headers & CORS policy dynamically
enforce_security_headers_and_cors(['POST', 'OPTIONS']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    send_json_response(false, 'Method Not Allowed');
}

// Enforce valid referer origin (prevents direct address bar typing)
enforce_referer_check();

$config = require __DIR__ . '/config.php';

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

// Mathematical CAPTCHA Verification
$captchaSecret = (string) ($_ENV['CAPTCHA_SECRET'] ?? $_SERVER['CAPTCHA_SECRET'] ?? getenv('CAPTCHA_SECRET') ?: 'securesaltsecret');
$captchaCheck = verify_captcha_challenge(
    clean_input((string) ($data['captcha_challenge'] ?? '')),
    clean_input((string) ($data['captcha_signature'] ?? '')),
    clean_input((string) ($data['captcha_response'] ?? '')),
    $captchaSecret,
    $t['err_captcha_sign'],
    $t['err_captcha_invalid'],
    $t['err_captcha_wrong']
);

if ($captchaCheck !== true) {
    send_json_response(false, $captchaCheck);
}

// Connect to database and delegate to domain processor
try {
    $pdo = get_db_connection($config['db']);

    $request = BookingPaymentRequest::fromArray($data, $lang, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $processor = BookingPaymentProcessor::createDefault($pdo);
    $result = $processor->processBookingPayment($request);

    http_response_code($result->httpStatusCode);
    send_json_response($result->success, $result->message, $result->extra);
} catch (Throwable $e) {
    error_log('[Payment API Error] ' . $e->getMessage());
    http_response_code(500);
    send_json_response(false, 'Internal server error processing payment.');
}
