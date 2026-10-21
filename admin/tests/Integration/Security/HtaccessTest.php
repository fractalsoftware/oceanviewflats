<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Integration\Security;

use PHPUnit\Framework\TestCase;

final class HtaccessTest extends TestCase
{
    private string $htaccessPath;
    private string $htaccessContent;

    protected function setUp(): void
    {
        $this->htaccessPath = dirname(__DIR__, 3) . '/public/.htaccess';
        $content = file_get_contents($this->htaccessPath);
        $this->assertNotFalse($content, "Failed to read .htaccess at {$this->htaccessPath}");
        $this->htaccessContent = $content;
    }

    public function testHtaccessFileExistsAndIsReadable(): void
    {
        $this->assertFileExists($this->htaccessPath);
        $this->assertFileIsReadable($this->htaccessPath);
        $this->assertNotEmpty($this->htaccessContent);
    }

    public function testDirectoryBrowsingDisabledAndDirectoryIndexSet(): void
    {
        $this->assertMatchesRegularExpression(
            '/^Options\s+.*-Indexes/m',
            $this->htaccessContent,
            'Directory browsing must be explicitly disabled via "Options -Indexes".'
        );

        $this->assertMatchesRegularExpression(
            '/^DirectoryIndex\s+.*index\.php/m',
            $this->htaccessContent,
            'DirectoryIndex must specify index.php as the directory entry point.'
        );
    }

    public function testDotfileAccessIsProhibited(): void
    {
        $this->assertMatchesRegularExpression(
            '/<FilesMatch\s+"?\^\\\.+"?>/',
            $this->htaccessContent,
            'Must contain a <FilesMatch> directive targeting dotfiles (files starting with a period).'
        );

        $this->assertStringContainsString(
            'Require all denied',
            $this->htaccessContent,
            'Dotfile FilesMatch block must enforce "Require all denied".'
        );
    }

    public function testHttpSecurityHeadersArePresentAndEnforced(): void
    {
        $this->assertStringContainsString('<IfModule mod_headers.c>', $this->htaccessContent);
        $this->assertStringContainsString('</IfModule>', $this->htaccessContent);

        $this->assertMatchesRegularExpression(
            '/Header\s+(?:always\s+)?set\s+X-Frame-Options\s+"?DENY"?/i',
            $this->htaccessContent,
            'X-Frame-Options must be set to DENY to prevent clickjacking of admin forms.'
        );

        $this->assertMatchesRegularExpression(
            '/Header\s+(?:always\s+)?set\s+X-Content-Type-Options\s+"?nosniff"?/i',
            $this->htaccessContent,
            'X-Content-Type-Options must be set to nosniff to prevent MIME type sniffing.'
        );

        $this->assertMatchesRegularExpression(
            '/Header\s+(?:always\s+)?set\s+Referrer-Policy\s+"?strict-origin-when-cross-origin"?/i',
            $this->htaccessContent,
            'Referrer-Policy must be set to strict-origin-when-cross-origin.'
        );
    }

    public function testRewriteEngineAndFrontControllerRouting(): void
    {
        $this->assertStringContainsString('<IfModule mod_rewrite.c>', $this->htaccessContent);
        $this->assertMatchesRegularExpression(
            '/^\s*RewriteEngine\s+On/im',
            $this->htaccessContent,
            'RewriteEngine On must be enabled.'
        );

        $this->assertMatchesRegularExpression(
            '/^\s*RewriteCond\s+%{REQUEST_FILENAME}\s+!-f/m',
            $this->htaccessContent,
            'Must contain RewriteCond !-f to exclude existing files from rewriting.'
        );

        $this->assertMatchesRegularExpression(
            '/^\s*RewriteCond\s+%{REQUEST_FILENAME}\s+!-d/m',
            $this->htaccessContent,
            'Must contain RewriteCond !-d to exclude existing directories from rewriting.'
        );

        $this->assertMatchesRegularExpression(
            '/^\s*RewriteRule\s+.*index\.php\s+\[.*QSA.*L.*\]/m',
            $this->htaccessContent,
            'RewriteRule must dispatch requests to index.php with [QSA,L] flags.'
        );
    }

    public function testEnvironmentVariablesPlaceholderIsDocumented(): void
    {
        $this->assertStringContainsString(
            'Environment Variables (Injected during deployment)',
            $this->htaccessContent,
            'Must document the Environment Variables section for CI/CD deployment secret injection.'
        );

        $this->assertMatchesRegularExpression(
            '/^#\s*SetEnv\s+DB_HOST\b/m',
            $this->htaccessContent,
            'Must document sample SetEnv DB_HOST directive.'
        );

        $this->assertMatchesRegularExpression(
            '/^#\s*SetEnv\s+DB_NAME\b/m',
            $this->htaccessContent,
            'Must document sample SetEnv DB_NAME directive.'
        );

        $this->assertMatchesRegularExpression(
            '/^#\s*SetEnv\s+DB_USER\b/m',
            $this->htaccessContent,
            'Must document sample SetEnv DB_USER directive.'
        );

        $this->assertMatchesRegularExpression(
            '/^#\s*SetEnv\s+DB_PASS\b/m',
            $this->htaccessContent,
            'Must document sample SetEnv DB_PASS directive.'
        );

        $this->assertMatchesRegularExpression(
            '/^#\s*SetEnv\s+MERCADOPAGO_\w+/m',
            $this->htaccessContent,
            'Must document sample SetEnv MERCADOPAGO_* directive.'
        );

        $this->assertMatchesRegularExpression(
            '/^#\s*SetEnv\s+HUESPED_MANAGER_\w+/m',
            $this->htaccessContent,
            'Must document sample SetEnv HUESPED_MANAGER_* directive.'
        );
    }

    public function testDirectivesTagsAreBalanced(): void
    {
        $openIfModules = substr_count($this->htaccessContent, '<IfModule');
        $closeIfModules = substr_count($this->htaccessContent, '</IfModule>');
        $this->assertSame($openIfModules, $closeIfModules, 'All <IfModule> tags must be properly closed.');

        $openFilesMatch = substr_count($this->htaccessContent, '<FilesMatch');
        $closeFilesMatch = substr_count($this->htaccessContent, '</FilesMatch>');
        $this->assertSame($openFilesMatch, $closeFilesMatch, 'All <FilesMatch> tags must be properly closed.');
    }

    public function testCve2026109618And109619MitigationsAreEnforcedAcrossAllHtaccessFiles(): void
    {
        $rootDir = dirname(__DIR__, 4);
        $htaccessFiles = [
            'admin/public/.htaccess' => $this->htaccessContent,
            'public/api/.htaccess' => (string) file_get_contents($rootDir . '/public/api/.htaccess'),
            'public/.htaccess' => (string) file_get_contents($rootDir . '/public/.htaccess'),
        ];

        foreach ($htaccessFiles as $relativePath => $content) {
            $this->assertNotEmpty($content, "{$relativePath} must not be empty.");

            // 1. LimitRequestBody cap enforced at Apache level prior to PHP initialization
            $this->assertMatchesRegularExpression(
                '/^\s*LimitRequestBody\s+65536\b/m',
                $content,
                "{$relativePath} must enforce LimitRequestBody 65536 (64 KB) to prevent OOM corruption (CVE-2026-109618)."
            );

            // 2. Reject multipart/form-data requests before PHP startup via mod_rewrite
            $this->assertMatchesRegularExpression(
                '/RewriteCond\s+%\{CONTENT_TYPE\}\s+\^multipart\/form-data\s+\[NC\]/i',
                $content,
                "{$relativePath} must match multipart/form-data Content-Type via mod_rewrite."
            );
            $this->assertMatchesRegularExpression(
                '/RewriteRule\s+\^\s+-\s+\[F,L\]/i',
                $content,
                "{$relativePath} must reject multipart/form-data requests with HTTP 403 [F,L]."
            );

            // 3. Balanced <IfModule> blocks
            $this->assertSame(
                substr_count($content, '<IfModule'),
                substr_count($content, '</IfModule>'),
                "{$relativePath} must have balanced <IfModule> tags."
            );
        }

        // Verify guarded PHP engine flags in API and Admin .htaccess files
        foreach (['admin/public/.htaccess', 'public/api/.htaccess'] as $phpHtaccess) {
            $content = $htaccessFiles[$phpHtaccess];
            $this->assertStringContainsString('php_flag file_uploads Off', $content);
            $this->assertStringContainsString('php_flag session.upload_progress.enabled Off', $content);
            $this->assertStringContainsString('php_value post_max_size 64K', $content);
            $this->assertStringContainsString('php_value max_input_nesting_level 16', $content);
            $this->assertStringContainsString('php_value max_input_vars 200', $content);
        }
    }
}
