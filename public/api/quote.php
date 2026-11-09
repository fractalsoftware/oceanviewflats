<?php
/**
 * OceanViewFlats - Authoritative Stay Quotation API
 * PHP 8 Compatible
 *
 * Provides real-time, authoritative stay quotations for apartments 1606 and 1707.
 * Computes night-by-night seasonal pricing, enforces strict multi-tier maximum minimum stays,
 * and incorporates centralized cleaning fees per ADR 0004.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/utils.php';
$config = require __DIR__ . '/config.php';

use OceanViewFlats\Domain\Quote\QuoteEngine;

// Enforce security headers & CORS policy dynamically
enforce_security_headers_and_cors(['GET', 'OPTIONS']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Enforce rate limiting to avoid denial-of-service or scraping abuse
enforce_rate_limit('ovf_quote_rate_limits.json', 60, 600, 'Too many requests. Please wait a few minutes and try again.');

$pdo = $GLOBALS['TEST_PDO'] ?? null;
if ($pdo === null && !empty($config['db']['host']) && !empty($config['db']['dbname'])) {
    try {
        $pdo = get_db_connection($config['db']);
    } catch (PDOException $e) {
        error_log('Quote API MySQL connection failed: ' . $e->getMessage());
    }
}

// Extract and sanitize query parameters
$action = clean_input($_GET['action'] ?? '');
$summary = clean_input($_GET['summary'] ?? '');
$propertyId = clean_input($_GET['property_id'] ?? $_GET['property'] ?? '');
$checkIn = clean_input($_GET['check_in'] ?? '');
$checkOut = clean_input($_GET['check_out'] ?? '');

if ($action === 'rates' || $summary === '1') {
    try {
        $quoteEngine = QuoteEngine::createDefault(pdo: $pdo);
        $summaryData = $quoteEngine->getRateSummary($propertyId !== '' ? $propertyId : null);

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => true,
            'data' => $summaryData,
        ]);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
        ]);
    } catch (Throwable) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => 'Internal server error resolving active rates.',
        ]);
    }
    exit;
}

if ($propertyId === '' || $checkIn === '' || $checkOut === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'error' => 'Missing required parameters: property_id, check_in, and check_out are required.'
    ]);
    exit;
}

try {
    $quoteEngine = QuoteEngine::createDefault(pdo: $pdo);
    $quote = $quoteEngine->quote($propertyId, $checkIn, $checkOut);

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => true,
        'data' => $quote->toArray()
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error calculating quotation.'
    ]);
}
