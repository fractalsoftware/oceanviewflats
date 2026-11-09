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

use OceanViewFlats\Domain\Quote\CsvRateSource;
use OceanViewFlats\Domain\Quote\PdoRateRepository;
use OceanViewFlats\Domain\Quote\PropertyRatesConfig;
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
        $ratesConfig = PropertyRatesConfig::createDefault();
        if ($propertyId !== '' && !$ratesConfig->isValidProperty($propertyId)) {
            throw new InvalidArgumentException("Unknown property identifier: '{$propertyId}'");
        }

        $csvRateSource = new CsvRateSource();
        $rateRepository = new PdoRateRepository(
            pdo: $pdo ?? new PDO('sqlite::memory:'),
            ratesConfig: $ratesConfig,
            fallbackSource: $csvRateSource
        );

        $propertiesPayload = [];
        foreach (['1606', '1707'] as $pid) {
            $status = $rateRepository->getPropertyRateStatus($pid);
            $defaultRate = $ratesConfig->getDefaultNightlyRate($pid);
            $cleaningFee = $ratesConfig->getCleaningFee($pid);
            $defaultMinStay = $ratesConfig->getDefaultMinimumStay($pid);

            $propertiesPayload[$pid] = [
                'property_id' => $pid,
                'current_nightly_rate_cop' => (int) round($status->currentNightlyRate),
                'currentNightlyRate' => (int) round($status->currentNightlyRate),
                'default_nightly_rate_cop' => (int) round($defaultRate),
                'defaultNightlyRate' => (int) round($defaultRate),
                'cleaning_fee_cop' => (int) round($cleaningFee),
                'cleaningFeeCop' => (int) round($cleaningFee),
                'default_minimum_stay' => $defaultMinStay,
                'defaultMinimumStay' => $defaultMinStay,
                'is_seasonal_tier_active' => $status->isSeasonalTierActive,
                'isSeasonalTierActive' => $status->isSeasonalTierActive,
                'active_tier_name' => $status->activeTierName,
                'activeTierName' => $status->activeTierName,
                'active_tier_end_date' => $status->activeTierEndDate,
                'activeTierEndDate' => $status->activeTierEndDate,
            ];
        }

        $tierProperties = $propertyId !== '' ? [$propertyId] : ['1606', '1707'];
        $tiersPayload = [];
        foreach ($tierProperties as $pid) {
            foreach ($rateRepository->getTiersForProperty($pid) as $tier) {
                $tiersPayload[] = [
                    'property_id' => $tier->propertyId,
                    'start_date' => $tier->startDate,
                    'end_date' => $tier->endDate,
                    'nightly_rate_cop' => (int) round($tier->nightlyRateCop),
                    'minimum_stay' => $tier->minimumStay,
                    'season_name' => $tier->seasonName,
                ];
            }
        }

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => true,
            'data' => [
                'properties' => $propertiesPayload,
                'tiers' => $tiersPayload,
            ],
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
