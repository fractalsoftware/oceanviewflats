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
        'host' => $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1',
        'dbname' => $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?: 'oceanviewflats_db',
        'user' => $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?: 'root',
        'pass' => $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? getenv('DB_PASS') ?: ''
    ],
    'ical_feeds' => [
        '1606' => 'https://www.airbnb.com/calendar/ical/1584825560087571592.ics?t=55e0ddced658497c89743275f7c3a9c9',
        '1707' => 'https://www.airbnb.com/calendar/ical/1500108514798091235.ics?t=0dc27b409a1a4e64be6445f5dc2efd39'
    ],
    'credentials' => [
        '1606' => [
            'door_code' => $_ENV['PROPERTY_1606_DOOR_CODE'] ?? $_SERVER['PROPERTY_1606_DOOR_CODE'] ?? getenv('PROPERTY_1606_DOOR_CODE') ?: '0160600#',
            'wifi_ssid' => $_ENV['PROPERTY_1606_WIFI_SSID'] ?? $_SERVER['PROPERTY_1606_WIFI_SSID'] ?? getenv('PROPERTY_1606_WIFI_SSID') ?: 'APTO1606',
            'wifi_password' => $_ENV['PROPERTY_1606_WIFI_PASSWORD'] ?? $_SERVER['PROPERTY_1606_WIFI_PASSWORD'] ?? getenv('PROPERTY_1606_WIFI_PASSWORD') ?: 'Invitado@1606@HN',
            'parking_spot' => $_ENV['PROPERTY_1606_PARKING_SPOT'] ?? $_SERVER['PROPERTY_1606_PARKING_SPOT'] ?? getenv('PROPERTY_1606_PARKING_SPOT') ?: '87',
        ],
        '1707' => [
            'door_code' => $_ENV['PROPERTY_1707_DOOR_CODE'] ?? $_SERVER['PROPERTY_1707_DOOR_CODE'] ?? getenv('PROPERTY_1707_DOOR_CODE') ?: '0170700#',
            'wifi_ssid' => $_ENV['PROPERTY_1707_WIFI_SSID'] ?? $_SERVER['PROPERTY_1707_WIFI_SSID'] ?? getenv('PROPERTY_1707_WIFI_SSID') ?: 'APTO1707',
            'wifi_password' => $_ENV['PROPERTY_1707_WIFI_PASSWORD'] ?? $_SERVER['PROPERTY_1707_WIFI_PASSWORD'] ?? getenv('PROPERTY_1707_WIFI_PASSWORD') ?: 'Invitado@1707@HN',
            'parking_spot' => $_ENV['PROPERTY_1707_PARKING_SPOT'] ?? $_SERVER['PROPERTY_1707_PARKING_SPOT'] ?? getenv('PROPERTY_1707_PARKING_SPOT') ?: '95',
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
