<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Integration\Security;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Security regression tests for Issue #271:
 * - Local bundling of Admin Console Tailwind CSS + pinned HTMX 2.0.4 + extracted admin.js
 * - Zero inline <script> blocks, zero inline on* event handlers, and zero third-party CDN tags
 * - Enforcing strict CSP on Admin Console and Report-Only CSP rollout on public site
 */
final class AdminCspAndLocalAssetsTest extends TestCase
{
    private string $rootDir;
    private string $adminDir;

    protected function setUp(): void
    {
        $this->rootDir = dirname(__DIR__, 4);
        $this->adminDir = $this->rootDir . '/admin';
    }

    public function testLocalAdminAssetsExistAndVendorPinnedHtmx(): void
    {
        $adminCssPath = $this->adminDir . '/public/assets/admin.css';
        $htmxPath = $this->adminDir . '/public/assets/htmx.min.js';
        $adminJsPath = $this->adminDir . '/public/assets/admin.js';

        $this->assertFileExists($adminCssPath);
        $this->assertFileExists($htmxPath);
        $this->assertFileExists($adminJsPath);

        $adminCss = (string) file_get_contents($adminCssPath);
        $htmxJs = (string) file_get_contents($htmxPath);
        $adminJs = (string) file_get_contents($adminJsPath);

        $this->assertNotEmpty($adminCss, 'Compiled Admin Tailwind CSS must not be empty.');
        $this->assertStringContainsString('.htmx-indicator', $adminCss);
        $this->assertStringContainsString('@keyframes progress-shimmer', $adminCss);

        $this->assertStringContainsString('version:"2.0.4"', $htmxJs, 'Vendored htmx.min.js must be pinned to 2.0.4.');
        $this->assertNotEmpty($adminJs, 'Extracted admin.js must not be empty.');
    }

    public function testLayoutUsesLocalAssetsOnlyAndStrictHtmxConfig(): void
    {
        $layout = (string) file_get_contents($this->adminDir . '/src/Views/layout.php');

        $this->assertStringContainsString('<link rel="stylesheet" href="/assets/admin.css">', $layout);
        $this->assertStringContainsString('<script src="/assets/htmx.min.js"></script>', $layout);
        $this->assertStringContainsString('<script src="/assets/admin.js"></script>', $layout);
        $this->assertStringContainsString('"allowEval": false', $layout);
        $this->assertStringContainsString('"allowScriptTags": false', $layout);
        $this->assertStringContainsString('"includeIndicatorStyles": false', $layout);

        $this->assertStringNotContainsString('cdn.tailwindcss.com', $layout);
        $this->assertStringNotContainsString('unpkg.com', $layout);
        $this->assertStringNotContainsString('<style>', $layout);
    }

    public function testAdminViewsControllersAndSetupContainNoInlineScriptsOrHandlersOrThirdPartyCdns(): void
    {
        $filesToScan = [
            $this->adminDir . '/public/setup.php',
        ];

        foreach ([$this->adminDir . '/src/Views', $this->adminDir . '/src/Controller'] as $dir) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $filesToScan[] = $file->getPathname();
                }
            }
        }

        $this->assertGreaterThan(
            10,
            count($filesToScan),
            'Expected to scan setup.php plus all Admin Console views and controllers.'
        );

        foreach ($filesToScan as $filePath) {
            $relative = str_replace($this->rootDir . '/', '', $filePath);
            $content = (string) file_get_contents($filePath);

            // 1. No inline <script> blocks (only external /assets/*.js allowed in layout.php)
            preg_match_all('/<script\b[^>]*>/i', $content, $scriptMatches);
            foreach ($scriptMatches[0] as $scriptTag) {
                $this->assertMatchesRegularExpression(
                    '/^<script\s+src="\/assets\/(?:htmx\.min|admin)\.js"><\/script>$|^<script\s+src="\/assets\/(?:htmx\.min|admin)\.js">$/i',
                    $scriptTag,
                    "{$relative} contains a disallowed <script> tag: {$scriptTag}"
                );
            }

            // 2. No inline DOM event handler attributes (onclick=, onchange=, oninput=, onsubmit=, etc.)
            $this->assertDoesNotMatchRegularExpression(
                '/\bon(?:click|dblclick|change|input|submit|keydown|keyup|keypress|focus|blur|load|error|mouseover|mouseout|mouseenter|mouseleave)\s*=/i',
                $content,
                "{$relative} must not contain inline DOM event handler attributes (blocked by strict script-src 'self' CSP)."
            );

            // 3. No third-party CDN or external font URLs
            $this->assertDoesNotMatchRegularExpression(
                '/https?:\/\/(?:cdn\.tailwindcss\.com|unpkg\.com|fonts\.googleapis\.com|fonts\.gstatic\.com|cdn\.jsdelivr\.net|cdnjs\.cloudflare\.com)/i',
                $content,
                "{$relative} must not reference third-party CDN origins."
            );
        }
    }

    public function testAdminAndPublicHtaccessCspDirectivesAreConfigured(): void
    {
        $adminHtaccess = (string) file_get_contents($this->adminDir . '/public/.htaccess');
        $publicHtaccess = (string) file_get_contents($this->rootDir . '/public/.htaccess');

        // Admin Console enforces strict CSP without 'unsafe-inline' or 'unsafe-eval' in script-src
        $this->assertMatchesRegularExpression(
            '/Header\s+always\s+set\s+Content-Security-Policy\s+"default-src \'self\'; script-src \'self\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; form-action \'self\'; frame-ancestors \'none\'; base-uri \'self\'"/',
            $adminHtaccess
        );
        $this->assertMatchesRegularExpression('/^\s*Header\s+unset\s+Content-Security-Policy\s*$/m', $adminHtaccess);

        // Public site stages Report-Only CSP with validated Mercado Pago, Matomo, Google Fonts, and FX origins
        $this->assertMatchesRegularExpression(
            '/Header\s+always\s+set\s+Content-Security-Policy-Report-Only\s+"[^"]+"/',
            $publicHtaccess
        );
        foreach ([
            'https://sdk.mercadopago.com',
            'https://api.mercadopago.com',
            'https://api-static.mercadopago.com',
            'https://http2.mlstatic.com',
            'https://stats.fractalserver.com',
            'https://fonts.googleapis.com',
            'https://fonts.gstatic.com',
            'https://open.er-api.com',
        ] as $origin) {
            $this->assertStringContainsString(
                $origin,
                $publicHtaccess,
                "Public site CSP-Report-Only must allow required origin {$origin}."
            );
        }
    }
}
