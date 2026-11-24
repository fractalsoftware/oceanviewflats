<?php
/**
 * OceanViewFlats OpenAPI & Swagger-PHP Documentation Generator
 * PHP 8 Compatible
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script must be run from the command line.\n");
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use OpenApi\Generator;

echo "=== Generating OpenAPI Specification ===\n";

$apiDir = dirname(__DIR__) . '/public/api';
$jsonOutput = $apiDir . '/openapi.json';
$yamlOutput = $apiDir . '/openapi.yaml';

try {
    $openapi = (new Generator())->generate([$apiDir]);
    if ($openapi === null) {
        throw new \RuntimeException('Failed to generate OpenAPI specification: no OpenAPI root found.');
    }
    
    // Save JSON
    file_put_contents($jsonOutput, $openapi->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    echo "✓ Generated JSON: " . realpath($jsonOutput) . "\n";
    
    // Save YAML
    file_put_contents($yamlOutput, $openapi->toYaml());
    echo "✓ Generated YAML: " . realpath($yamlOutput) . "\n";
    
    echo "=== Documentation generated successfully! ===\n";
} catch (\Throwable $e) {
    echo "Error generating documentation: " . $e->getMessage() . "\n";
    exit(1);
}
