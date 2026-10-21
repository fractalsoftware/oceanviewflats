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
$data = !empty($rawInput) ? json_decode((string) $rawInput, true) : null;

$paymentId = is_array($data) ? ($data['data']['id'] ?? $data['id'] ?? null) : null;
if (empty($paymentId)) {
    $paymentId = $_GET['id'] ?? $_GET['data_id'] ?? null;
}

if (empty($paymentId)) {
    http_response_code(200);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => true, 'message' => 'Waiting for payment data.'], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo = get_db_connection($config['db']);

    $processor = WebhookSettlementProcessor::createDefault($pdo);
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
