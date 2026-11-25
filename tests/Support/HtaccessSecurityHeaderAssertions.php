<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * Shared PHPUnit assertions verifying Apache mod_headers security-header
 * configuration across public/.htaccess, public/api/.htaccess, and admin/public/.htaccess.
 */
final class HtaccessSecurityHeaderAssertions
{
    public const HSTS_VALUE = '"max-age=15552000; includeSubDomains" "expr=%{HTTPS} == \'on\'"';

    public const ADMIN_CSP_VALUE = '"default-src \'self\'; script-src \'self\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; form-action \'self\'; frame-ancestors \'none\'; base-uri \'self\'"';

    public const PUBLIC_CSP_REPORT_ONLY_VALUE = '"default-src \'self\'; script-src \'self\' https://sdk.mercadopago.com https://api-static.mercadopago.com https://http2.mlstatic.com https://stats.fractalserver.com; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com https://http2.mlstatic.com; font-src \'self\' https://fonts.gstatic.com https://http2.mlstatic.com data:; img-src \'self\' data: https://http2.mlstatic.com https://api-static.mercadopago.com https://stats.fractalserver.com; connect-src \'self\' https://api.mercadopago.com https://api-static.mercadopago.com https://sdk.mercadopago.com https://http2.mlstatic.com https://stats.fractalserver.com https://open.er-api.com; frame-src \'self\' https://sdk.mercadopago.com https://api-static.mercadopago.com https://www.mercadopago.com https://www.mercadopago.com.co; form-action \'self\'; frame-ancestors \'self\'; base-uri \'self\'; object-src \'none\'"';

    /** @var list<string> */
    public const PHP_EMITTED_SECURITY_HEADERS = [
        'X-Frame-Options',
        'X-Content-Type-Options',
        'Referrer-Policy',
        'Permissions-Policy',
    ];

    /** @var list<string> */
    public const ADMIN_PHP_EMITTED_SECURITY_HEADERS = [
        'X-Frame-Options',
        'X-Content-Type-Options',
        'Referrer-Policy',
        'Permissions-Policy',
        'Content-Security-Policy',
    ];

    /**
     * @param array<string, string> $expectedAlwaysHeaders Map of header name => expected quoted Apache directive value.
     * @param list<string> $expectedOnsuccessUnsets Header names that must be unset from the onsuccess table.
     */
    public static function assertModHeadersDirectives(
        string $relativePath,
        string $htaccessContent,
        array $expectedAlwaysHeaders,
        array $expectedOnsuccessUnsets = []
    ): void {
        $matched = preg_match('/<IfModule\s+mod_headers\.c>(.*?)<\/IfModule>/is', $htaccessContent, $matches);
        Assert::assertSame(1, $matched, "{$relativePath} must contain a <IfModule mod_headers.c> block.");
        $headersBlock = $matches[1];

        foreach ($expectedAlwaysHeaders as $headerName => $expectedValue) {
            $quotedName = preg_quote($headerName, '/');
            $quotedValue = preg_quote($expectedValue, '/');

            Assert::assertMatchesRegularExpression(
                '/^\s*Header\s+always\s+set\s+' . $quotedName . '\s+' . $quotedValue . '\s*$/im',
                $headersBlock,
                "{$relativePath} must set {$headerName} via 'Header always set' with expected value."
            );

            Assert::assertDoesNotMatchRegularExpression(
                '/^\s*Header\s+set\s+' . $quotedName . '\b/im',
                $headersBlock,
                "{$relativePath} must not use bare 'Header set' for {$headerName} (causes onsuccess/always duplication)."
            );
        }

        foreach ($expectedOnsuccessUnsets as $headerName) {
            $quotedName = preg_quote($headerName, '/');
            Assert::assertMatchesRegularExpression(
                '/^\s*Header\s+unset\s+' . $quotedName . '\s*$/im',
                $headersBlock,
                "{$relativePath} must unset onsuccess {$headerName} so PHP header() calls do not duplicate Apache headers."
            );
        }
    }

    /**
     * @return array<string, string>
     */
    public static function expectedPublicRootHeaders(): array
    {
        return [
            'Strict-Transport-Security' => self::HSTS_VALUE,
            'X-Frame-Options' => '"SAMEORIGIN"',
            'X-Content-Type-Options' => '"nosniff"',
            'Referrer-Policy' => '"strict-origin-when-cross-origin"',
            'Permissions-Policy' => '"geolocation=(), microphone=(), camera=(), payment=(self)"',
            'Content-Security-Policy-Report-Only' => self::PUBLIC_CSP_REPORT_ONLY_VALUE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function expectedPublicApiHeaders(): array
    {
        return [
            'Strict-Transport-Security' => self::HSTS_VALUE,
            'X-Frame-Options' => '"DENY"',
            'X-Content-Type-Options' => '"nosniff"',
            'Referrer-Policy' => '"strict-origin-when-cross-origin"',
            'Permissions-Policy' => '"geolocation=(), microphone=(), camera=(), payment=(self)"',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function expectedAdminHeaders(): array
    {
        return [
            'Strict-Transport-Security' => self::HSTS_VALUE,
            'X-Frame-Options' => '"DENY"',
            'X-Content-Type-Options' => '"nosniff"',
            'Referrer-Policy' => '"strict-origin-when-cross-origin"',
            'Permissions-Policy' => '"geolocation=(), microphone=(), camera=(), payment=()"',
            'Content-Security-Policy' => self::ADMIN_CSP_VALUE,
        ];
    }
}
