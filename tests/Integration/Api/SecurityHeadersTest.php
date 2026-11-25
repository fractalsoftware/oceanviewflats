<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Integration\Api;

use OceanViewFlats\Tests\Support\BuiltInHttpServer;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersTest extends TestCase
{
    private ?BuiltInHttpServer $server = null;

    protected function setUp(): void
    {
        $rootDir = dirname(__DIR__, 3);
        $apiDir = var_export($rootDir . '/public/api', true);

        $routerCode = <<<PHP
<?php
declare(strict_types=1);

\$path = parse_url(\$_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (\$path === '/__baseline') {
    echo 'baseline';
    return true;
}

if (\$path === '/api/ical.php') {
    require {$apiDir} . '/ical.php';
    return true;
}

if (\$path === '/api/guide-access.php') {
    require {$apiDir} . '/guide-access.php';
    return true;
}

if (\$path === '/api/index.php') {
    require {$apiDir} . '/index.php';
    return true;
}

http_response_code(404);
echo 'Not found';
return true;
PHP;

        $this->server = BuiltInHttpServer::start($routerCode, $rootDir);
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
        $this->server = null;

        foreach (['ovf_ical_rate_limits.json', 'ovf_guide_access_rate_limits.json'] as $rateLimitFile) {
            $path = sys_get_temp_dir() . '/' . $rateLimitFile;
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    public function testApiEndpointsSuppressXPoweredByHeaderEvenWhenExposePhpIsOn(): void
    {
        $server = $this->server;
        $this->assertNotNull($server);

        // Control check: verify expose_php=On emits X-Powered-By when not explicitly removed
        $baseline = $server->get('/__baseline');
        $this->assertSame(200, $baseline['status']);
        $this->assertArrayHasKey(
            'x-powered-by',
            $baseline['headers'],
            'Baseline endpoint must emit X-Powered-By under expose_php=On to validate test harness.'
        );

        // 1. GET /api/ical.php (enforce_security_headers_and_cors)
        $ical = $server->get('/api/ical.php?property=invalid');
        $this->assertSame(400, $ical['status']);
        $this->assertArrayNotHasKey('x-powered-by', $ical['headers'], '/api/ical.php must not emit X-Powered-By.');
        $this->assertSame(['DENY'], $ical['headerValues']['x-frame-options'] ?? null);
        $this->assertSame(['nosniff'], $ical['headerValues']['x-content-type-options'] ?? null);
        $this->assertSame(['strict-origin-when-cross-origin'], $ical['headerValues']['referrer-policy'] ?? null);
        $this->assertSame(
            ['geolocation=(), microphone=(), camera=(), payment=(self)'],
            $ical['headerValues']['permissions-policy'] ?? null
        );
        $this->assertSame('Origin', $ical['headers']['vary'] ?? null);

        // 2. HEAD /api/ical.php?property=1606 (mirrors curl -sSI check from #288)
        $icalHead = $server->head('/api/ical.php?property=1606');
        $this->assertSame(405, $icalHead['status']);
        $this->assertArrayNotHasKey('x-powered-by', $icalHead['headers'], 'HEAD /api/ical.php must not emit X-Powered-By.');
        $this->assertSame(['DENY'], $icalHead['headerValues']['x-frame-options'] ?? null);

        // 3. GET /api/guide-access.php (enforce_security_headers_and_cors)
        $guide = $server->get('/api/guide-access.php');
        $this->assertSame(400, $guide['status']);
        $this->assertArrayNotHasKey('x-powered-by', $guide['headers'], '/api/guide-access.php must not emit X-Powered-By.');
        $this->assertSame(['DENY'], $guide['headerValues']['x-frame-options'] ?? null);
        $this->assertSame(['nosniff'], $guide['headerValues']['x-content-type-options'] ?? null);
        $this->assertSame(['strict-origin-when-cross-origin'], $guide['headerValues']['referrer-policy'] ?? null);
        $this->assertSame(
            ['geolocation=(), microphone=(), camera=(), payment=(self)'],
            $guide['headerValues']['permissions-policy'] ?? null
        );
        $this->assertSame('Origin', $guide['headers']['vary'] ?? null);

        // 4. GET /api/index.php (API directory shield)
        $index = $server->get('/api/index.php');
        $this->assertSame(403, $index['status']);
        $this->assertArrayNotHasKey('x-powered-by', $index['headers'], '/api/index.php must not emit X-Powered-By.');
    }

    public function testApiHtaccessOverridesParentAlwaysHeadersWithoutDuplication(): void
    {
        $rootDir = dirname(__DIR__, 3);

        $publicHtaccess = (string) file_get_contents($rootDir . '/public/.htaccess');
        $apiHtaccess = (string) file_get_contents($rootDir . '/public/api/.htaccess');

        $this->assertSame(1, preg_match('/<IfModule\s+mod_headers\.c>(.*?)<\/IfModule>/is', $publicHtaccess, $publicMatch));
        $this->assertSame(1, preg_match('/<IfModule\s+mod_headers\.c>(.*?)<\/IfModule>/is', $apiHtaccess, $apiMatch));

        $publicHeaders = $publicMatch[1];
        $apiHeaders = $apiMatch[1];

        // Static pages outside /api/ emit X-Frame-Options: SAMEORIGIN via always table
        $this->assertMatchesRegularExpression(
            '/^\s*Header\s+always\s+set\s+X-Frame-Options\s+"SAMEORIGIN"/im',
            $publicHeaders
        );

        // /api/* overrides parent always directives with DENY and unsets onsuccess copies from PHP header() calls
        $expectedApiAlwaysHeaders = [
            'Strict-Transport-Security' => '"max-age=15552000; includeSubDomains" "expr=%{HTTPS} == \'on\'"',
            'X-Frame-Options' => '"DENY"',
            'X-Content-Type-Options' => '"nosniff"',
            'Referrer-Policy' => '"strict-origin-when-cross-origin"',
            'Permissions-Policy' => '"geolocation=(), microphone=(), camera=(), payment=(self)"',
        ];

        foreach ($expectedApiAlwaysHeaders as $headerName => $expectedValue) {
            $quotedName = preg_quote($headerName, '/');
            $quotedValue = preg_quote($expectedValue, '/');

            $this->assertMatchesRegularExpression(
                '/^\s*Header\s+always\s+set\s+' . $quotedName . '\s+' . $quotedValue . '\s*$/im',
                $apiHeaders,
                "public/api/.htaccess must enforce '{$headerName}' using 'Header always set' to override parent public/.htaccess."
            );

            $this->assertDoesNotMatchRegularExpression(
                '/^\s*Header\s+set\s+' . $quotedName . '\b/im',
                $apiHeaders,
                "public/api/.htaccess must not use bare 'Header set' for {$headerName} (causes duplicate/conflicting headers)."
            );
        }

        foreach (['X-Frame-Options', 'X-Content-Type-Options', 'Referrer-Policy', 'Permissions-Policy'] as $phpHeader) {
            $quotedName = preg_quote($phpHeader, '/');
            $this->assertMatchesRegularExpression(
                '/^\s*Header\s+unset\s+' . $quotedName . '\s*$/im',
                $apiHeaders,
                "public/api/.htaccess must unset onsuccess '{$phpHeader}' to prevent duplication with public/api/utils.php."
            );
        }

        $this->assertStringNotContainsStringIgnoringCase('SAMEORIGIN', $apiHeaders);
    }
}
