<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Integration;

use DOMDocument;
use DOMElement;
use DOMXPath;
use OceanViewFlats\Admin\AdminApp;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Tests\Support\AdminDatabaseTestHelper;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Ticket #193 (Parent Spec #191):
 * Standalone PWA Web App Manifest & Pass-Through Service Worker for the Admin Console.
 */
final class AdminPwaSupportTest extends TestCase
{
    private string $rootPath;
    private string $adminPublicDir;
    private PDO $pdo;
    private AdminApp $app;

    /**
     * @var array<string, mixed>
     */
    private array $authenticatedAdminSession;

    protected function setUp(): void
    {
        $this->rootPath = dirname(__DIR__, 3);
        $this->adminPublicDir = $this->rootPath . '/admin/public';

        $this->pdo = AdminDatabaseTestHelper::createDatabaseWithDefaultAdmin();
        $this->app = AdminApp::createDefault($this->pdo);

        $this->authenticatedAdminSession = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'admin_user_email' => 'admin@oceanviewflats.com',
            'admin_user_role' => 'admin',
            'csrf_token' => 'secure_csrf_token_pwa_support_test',
        ];
    }

    public function testWebAppManifestContainsRequiredStandalonePwaMetadataAndIcons(): void
    {
        $manifestPath = $this->adminPublicDir . '/site.webmanifest';
        $this->assertFileExists($manifestPath, 'admin/public/site.webmanifest must exist');

        $rawJson = (string) file_get_contents($manifestPath);
        /** @var mixed $manifest */
        $manifest = json_decode($rawJson, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'site.webmanifest must be valid JSON');
        $this->assertIsArray($manifest);

        $this->assertSame('Admin Console - Ocean View Flats', $manifest['name'] ?? null);
        $this->assertSame('OVF Admin', $manifest['short_name'] ?? null);
        $this->assertSame('/', $manifest['start_url'] ?? null);
        $this->assertSame('/', $manifest['scope'] ?? null);
        $this->assertSame('standalone', $manifest['display'] ?? null);
        $this->assertSame('#c084fc', $manifest['theme_color'] ?? null);
        $this->assertSame('#f9fafb', $manifest['background_color'] ?? null);

        $this->assertArrayHasKey('icons', $manifest);
        $this->assertIsArray($manifest['icons']);
        $this->assertGreaterThanOrEqual(2, count($manifest['icons']));

        $iconsBySize = [];
        foreach ($manifest['icons'] as $icon) {
            $this->assertIsArray($icon);
            $sizes = (string) ($icon['sizes'] ?? '');
            $src = (string) ($icon['src'] ?? '');
            $type = (string) ($icon['type'] ?? '');
            $purpose = (string) ($icon['purpose'] ?? '');

            $this->assertSame('image/png', $type);
            $this->assertStringContainsString('any', $purpose);
            $this->assertStringContainsString('maskable', $purpose);

            $cleanFilePath = (string) parse_url($src, PHP_URL_PATH);
            $this->assertNotEmpty($cleanFilePath);
            $this->assertFileExists(
                $this->adminPublicDir . $cleanFilePath,
                "Manifest icon file {$cleanFilePath} must exist in admin/public"
            );

            $iconsBySize[$sizes] = $src;
        }

        $this->assertArrayHasKey('192x192', $iconsBySize);
        $this->assertStringStartsWith('/web-app-manifest-192x192.png', $iconsBySize['192x192']);

        $this->assertArrayHasKey('512x512', $iconsBySize);
        $this->assertStringStartsWith('/web-app-manifest-512x512.png', $iconsBySize['512x512']);
    }

    public function testHtaccessConfiguresWebmanifestMimeTypeAndStaticFileBypass(): void
    {
        $htaccessPath = $this->adminPublicDir . '/.htaccess';
        $this->assertFileExists($htaccessPath);

        $htaccess = (string) file_get_contents($htaccessPath);
        $this->assertStringContainsString('AddType application/manifest+json .webmanifest', $htaccess);
        $this->assertStringContainsString('RewriteCond %{REQUEST_FILENAME} !-f', $htaccess);
    }

    public function testBiomeConfigExcludesAdminWebmanifestMatchingPublicConvention(): void
    {
        $biomePath = $this->rootPath . '/biome.json';
        $this->assertFileExists($biomePath);

        /** @var mixed $biome */
        $biome = json_decode((string) file_get_contents($biomePath), true);
        $this->assertIsArray($biome);
        $this->assertIsArray($biome['files'] ?? null);
        $includes = $biome['files']['includes'] ?? [];
        $this->assertIsArray($includes);
        $this->assertContains('!admin/public/site.webmanifest', $includes);
    }

    public function testServiceWorkerIsPassThroughNetworkOnlyAndNeverUsesCacheStorage(): void
    {
        $swPath = $this->adminPublicDir . '/sw.js';
        $this->assertFileExists($swPath, 'admin/public/sw.js must exist');

        $swSource = (string) file_get_contents($swPath);

        // Must handle install (skipWaiting), activate (clients.claim), and fetch (network pass-through)
        $this->assertMatchesRegularExpression('/addEventListener\s*\(\s*[\'"]install[\'"]/', $swSource);
        $this->assertStringContainsString('skipWaiting()', $swSource);

        $this->assertMatchesRegularExpression('/addEventListener\s*\(\s*[\'"]activate[\'"]/', $swSource);
        $this->assertStringContainsString('clients.claim()', $swSource);

        $this->assertMatchesRegularExpression('/addEventListener\s*\(\s*[\'"]fetch[\'"]/', $swSource);
        $this->assertStringContainsString('fetch(', $swSource);

        // Must never invoke Cache Storage APIs to prevent stale availability or post-logout leakage
        $this->assertStringNotContainsString('caches.open', $swSource);
        $this->assertStringNotContainsString('cache.put', $swSource);
        $this->assertStringNotContainsString('cache.addAll', $swSource);
        $this->assertStringNotContainsString('cache.add(', $swSource);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function authenticatedAdminRoutesProvider(): array
    {
        return [
            'Dashboard Hub' => ['/'],
            'Reservations Ledger' => ['/reservations'],
            'Seasonal Rate Tiers' => ['/rates'],
            'Maintenance Blocks' => ['/calendar-blocks'],
            'Audit Log' => ['/audit-logs'],
        ];
    }

    #[DataProvider('authenticatedAdminRoutesProvider')]
    public function testAuthenticatedRoutesRenderPwaHeadTagsAndServiceWorkerRegistration(string $route): void
    {
        $request = new Request('GET', $route);
        $response = $this->app->handle($request, $this->authenticatedAdminSession);

        $this->assertSame(200, $response->getStatusCode(), "Expected HTTP 200 for GET {$route}");
        $this->assertHtmlContainsPwaMetadataAndRegistration($response->getBody(), "Authenticated route {$route}");
    }

    public function testUnauthenticatedLoginRouteRendersPwaHeadTagsAndServiceWorkerRegistration(): void
    {
        $emptySession = [];
        $request = new Request('GET', '/login');
        $response = $this->app->handle($request, $emptySession);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertHtmlContainsPwaMetadataAndRegistration($response->getBody(), 'Unauthenticated /login route');
    }

    public function testForbidden403ResponseRendersPwaHeadTagsAndServiceWorkerRegistration(): void
    {
        $viewerSession = [
            'admin_user_id' => 2,
            'admin_user_name' => 'Viewer Operator',
            'admin_user_email' => 'viewer@oceanviewflats.com',
            'admin_user_role' => 'viewer',
            'csrf_token' => 'secure_csrf_token_viewer_pwa_test',
        ];

        $request = new Request('GET', '/audit-logs');
        $response = $this->app->handle($request, $viewerSession);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertHtmlContainsPwaMetadataAndRegistration($response->getBody(), 'HTTP 403 Forbidden response');
    }

    private function assertHtmlContainsPwaMetadataAndRegistration(string $html, string $context): void
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // 1. <link rel="manifest" href="/site.webmanifest...">
        $manifestLinks = $xpath->query('//head/link[@rel="manifest"]');
        $this->assertNotFalse($manifestLinks);
        $this->assertSame(1, $manifestLinks->length, "{$context} must include <link rel=\"manifest\"> in <head>");
        $manifestNode = $manifestLinks->item(0);
        $this->assertInstanceOf(DOMElement::class, $manifestNode);
        $this->assertStringStartsWith('/site.webmanifest', $manifestNode->getAttribute('href'));

        // 2. <meta name="theme-color" content="#c084fc">
        $themeColorMeta = $xpath->query('//head/meta[@name="theme-color"]');
        $this->assertNotFalse($themeColorMeta);
        $this->assertSame(1, $themeColorMeta->length, "{$context} must include <meta name=\"theme-color\"> in <head>");
        $themeColorNode = $themeColorMeta->item(0);
        $this->assertInstanceOf(DOMElement::class, $themeColorNode);
        $this->assertSame('#c084fc', $themeColorNode->getAttribute('content'));

        // 3. Apple mobile web-app meta tags
        $capableMeta = $xpath->query('//head/meta[@name="apple-mobile-web-app-capable"]');
        $this->assertNotFalse($capableMeta);
        $this->assertSame(1, $capableMeta->length, "{$context} must include apple-mobile-web-app-capable meta tag");
        $capableNode = $capableMeta->item(0);
        $this->assertInstanceOf(DOMElement::class, $capableNode);
        $this->assertSame('yes', $capableNode->getAttribute('content'));

        $statusBarMeta = $xpath->query('//head/meta[@name="apple-mobile-web-app-status-bar-style"]');
        $this->assertNotFalse($statusBarMeta);
        $this->assertSame(1, $statusBarMeta->length, "{$context} must include apple-mobile-web-app-status-bar-style meta tag");
        $statusBarNode = $statusBarMeta->item(0);
        $this->assertInstanceOf(DOMElement::class, $statusBarNode);
        $this->assertSame('default', $statusBarNode->getAttribute('content'));

        $titleMeta = $xpath->query('//head/meta[@name="apple-mobile-web-app-title"]');
        $this->assertNotFalse($titleMeta);
        $this->assertSame(1, $titleMeta->length, "{$context} must include apple-mobile-web-app-title meta tag");
        $titleNode = $titleMeta->item(0);
        $this->assertInstanceOf(DOMElement::class, $titleNode);
        $this->assertSame('OVF Admin', $titleNode->getAttribute('content'));

        // 4. Unobtrusive Service Worker registration script
        $this->assertMatchesRegularExpression('/[\'"]serviceWorker[\'"]\s+in\s+navigator/', $html);
        $this->assertStringContainsString('navigator.serviceWorker.register', $html);
        $this->assertStringContainsString("'/sw.js'", $html);
    }
}
