<?php
/**
 * OceanViewFlats - MercadoPago Webhook / IPN Listener Endpoint
 * PHP 8 Compatible HTTP Adapter
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/utils.php';

use OceanViewFlats\Domain\Payment\WebhookSettlementProcessor;

// Enforce security headers & CORS policy dynamically
enforce_security_headers_and_cors(['GET', 'POST', 'OPTIONS']);

// Enforce rate-limit to prevent webhook flooding or IPN bruteforcing
enforce_rate_limit('ovf_webhook_rate_limits.json', 150, 600, 'Too many requests. Please wait a few minutes and try again.');

$config = require __DIR__ . '/config.php';

// Extract payment ID from raw JSON body or GET query parameters
$rawInput = file_get_contents('php://input');
if (($rawInput === false || $rawInput === '') && PHP_SAPI === 'cli' && defined('STDIN')) {
    $rawInput = @stream_get_contents(STDIN);
}
$data = !empty($rawInput) ? json_decode((string) $rawInput, true) : null;

$paymentId = is_array($data) ? ($data['data']['id'] ?? $data['id'] ?? null) : null;
if (empty($paymentId)) {
    $paymentId = $_GET['id'] ?? $_GET['data_id'] ?? null;
}

// Validate cryptographic webhook signature (x-signature & x-request-id) before opening DB connection
$headers = function_exists('getallheaders') ? array_change_key_case((array) getallheaders(), CASE_LOWER) : [];
$xSignature = trim((string) ($_SERVER['HTTP_X_SIGNATURE'] ?? $headers['x-signature'] ?? ''));
$xRequestId = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? $headers['x-request-id'] ?? ''));

if ($xSignature === '' || $xRequestId === '') {
    error_log('[MercadoPago Webhook Security] Missing required x-signature or x-request-id header.');
    http_response_code(403);
    send_json_response(false, 'Forbidden: Missing webhook signature headers.');
}

$ts = '';
$v1 = '';
foreach (explode(',', $xSignature) as $part) {
    $kv = explode('=', trim($part), 2);
    if (count($kv) === 2) {
        $key = trim($kv[0]);
        $value = trim($kv[1]);
        if ($key === 'ts') {
            $ts = $value;
        } elseif ($key === 'v1') {
            $v1 = $value;
        }
    }
}

$webhookSecret = (string) (
    $config['mercadopago']['webhook_secret']
    ?? $config['mercadopago_webhook_secret']
    ?? $_ENV['MERCADOPAGO_WEBHOOK_SECRET']
    ?? $_SERVER['MERCADOPAGO_WEBHOOK_SECRET']
    ?? getenv('MERCADOPAGO_WEBHOOK_SECRET')
    ?: ''
);

$resolvedPaymentId = (string) ($paymentId ?? '');
$manifest = "id:{$resolvedPaymentId};request-id:{$xRequestId};ts:{$ts};";
$expectedSignature = $webhookSecret !== '' ? hash_hmac('sha256', $manifest, $webhookSecret) : '';

if ($ts === '' || $v1 === '' || $webhookSecret === '' || !hash_equals($expectedSignature, $v1)) {
    error_log('[MercadoPago Webhook Security] Invalid webhook signature for payment ID: ' . $resolvedPaymentId);
    http_response_code(403);
    send_json_response(false, 'Forbidden: Invalid webhook signature.');
}

if (empty($paymentId)) {
    http_response_code(200);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => true, 'message' => 'Waiting for payment data.'], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo = $GLOBALS['TEST_PDO'] ?? get_db_connection($config['db']);

    $processorOptions = [];
    if (isset($GLOBALS['TEST_PAYMENT_GATEWAY'])) {
        $processorOptions['gateway'] = $GLOBALS['TEST_PAYMENT_GATEWAY'];
    }
    if (isset($GLOBALS['TEST_EMAIL_SENDER'])) {
        $processorOptions['email_sender'] = $GLOBALS['TEST_EMAIL_SENDER'];
    }

    $processor = WebhookSettlementProcessor::createDefault($pdo, $processorOptions);
    $result = $processor->settlePayment((string) $paymentId);

    http_response_code($result->httpStatusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($result->toArray(), JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    error_log('[MercadoPago Webhook Error] ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'error' => 'Internal server error processing webhook.'], JSON_UNESCAPED_SLASHES);
    exit;
}
