<?php
/**
 * OceanViewFlats - API Configuration
 * 
 * Contains secure credentials, API settings, and private Airbnb sync iCal feed URLs.
 */

// Prevent direct web access to config.php (Security Best Practice)
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access forbidden.");
}

$autoloader = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!class_exists(\OceanViewFlats\Domain\Support\EnvLoader::class) && file_exists($autoloader)) {
    require_once $autoloader;
}

if (class_exists(\OceanViewFlats\Domain\Support\EnvLoader::class)) {
    \OceanViewFlats\Domain\Support\EnvLoader::load();
}

return [
    'db' => [
        'host' => $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: 'db',
        'dbname' => $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?: 'oceanviewflats_db',
        'user' => $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?: 'root',
        'pass' => $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? getenv('DB_PASS') ?: ''
    ],
    'mercadopago' => [
        'webhook_secret' => $_ENV['MERCADOPAGO_WEBHOOK_SECRET'] ?? $_SERVER['MERCADOPAGO_WEBHOOK_SECRET'] ?? getenv('MERCADOPAGO_WEBHOOK_SECRET') ?: '',
    ],
    'mercadopago_webhook_secret' => $_ENV['MERCADOPAGO_WEBHOOK_SECRET'] ?? $_SERVER['MERCADOPAGO_WEBHOOK_SECRET'] ?? getenv('MERCADOPAGO_WEBHOOK_SECRET') ?: '',
    'ical_feeds' => array_filter([
        '1606' => (string) ($_ENV['ICAL_FEED_1606'] ?? $_SERVER['ICAL_FEED_1606'] ?? (getenv('ICAL_FEED_1606') ?: '')),
        '1707' => (string) ($_ENV['ICAL_FEED_1707'] ?? $_SERVER['ICAL_FEED_1707'] ?? (getenv('ICAL_FEED_1707') ?: '')),
    ], static fn (string $url): bool => trim($url) !== ''),
    'credentials' => [
        '1606' => [
            'door_code' => (string) ($_ENV['PROPERTY_1606_DOOR_CODE'] ?? $_SERVER['PROPERTY_1606_DOOR_CODE'] ?? (getenv('PROPERTY_1606_DOOR_CODE') ?: '')),
            'wifi_ssid' => (string) ($_ENV['PROPERTY_1606_WIFI_SSID'] ?? $_SERVER['PROPERTY_1606_WIFI_SSID'] ?? (getenv('PROPERTY_1606_WIFI_SSID') ?: 'APTO1606')),
            'wifi_password' => (string) ($_ENV['PROPERTY_1606_WIFI_PASSWORD'] ?? $_SERVER['PROPERTY_1606_WIFI_PASSWORD'] ?? (getenv('PROPERTY_1606_WIFI_PASSWORD') ?: '')),
            'parking_spot' => (string) ($_ENV['PROPERTY_1606_PARKING_SPOT'] ?? $_SERVER['PROPERTY_1606_PARKING_SPOT'] ?? (getenv('PROPERTY_1606_PARKING_SPOT') ?: '87')),
        ],
        '1707' => [
            'door_code' => (string) ($_ENV['PROPERTY_1707_DOOR_CODE'] ?? $_SERVER['PROPERTY_1707_DOOR_CODE'] ?? (getenv('PROPERTY_1707_DOOR_CODE') ?: '')),
            'wifi_ssid' => (string) ($_ENV['PROPERTY_1707_WIFI_SSID'] ?? $_SERVER['PROPERTY_1707_WIFI_SSID'] ?? (getenv('PROPERTY_1707_WIFI_SSID') ?: 'APTO1707')),
            'wifi_password' => (string) ($_ENV['PROPERTY_1707_WIFI_PASSWORD'] ?? $_SERVER['PROPERTY_1707_WIFI_PASSWORD'] ?? (getenv('PROPERTY_1707_WIFI_PASSWORD') ?: '')),
            'parking_spot' => (string) ($_ENV['PROPERTY_1707_PARKING_SPOT'] ?? $_SERVER['PROPERTY_1707_PARKING_SPOT'] ?? (getenv('PROPERTY_1707_PARKING_SPOT') ?: '95')),
        ],
    ],
    'privacy_policy_version' => '2026-10',
    'data_controller' => [
        'name' => 'OceanViewFlats — Manuel Enrique Herrera',
        'rnt_1707' => 'RNT #268781',
        'rnt_1606' => 'RNT #270413',
        'address' => 'Calle 26 # 2-80, Playa Salguero, Santa Marta, Magdalena, Colombia',
        'email' => 'rentals@oceanviewflats.com',
        'phone' => '+57 (310) 815-5234',
        'consulta_sla_business_days' => 10,
        'reclamo_sla_business_days' => 15,
    ],
];
