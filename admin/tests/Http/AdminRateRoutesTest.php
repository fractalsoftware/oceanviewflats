<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Http;

use OceanViewFlats\Admin\AdminApp;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Repository\AdminRateRepository;
use OceanViewFlats\Admin\Tests\Support\AdminDatabaseTestHelper;
use OceanViewFlats\Domain\Quote\PropertyRatesConfig;
use PDO;
use PHPUnit\Framework\TestCase;

final class AdminRateRoutesTest extends TestCase
{
    private PDO $pdo;
    private AdminApp $app;
    private AdminRateRepository $rateRepo;
    private string $tempCsvPath;
    private string $tempCacheDir;

    protected function setUp(): void
    {
        $this->pdo = AdminDatabaseTestHelper::createDatabaseWithDefaultAdmin();

        $this->tempCsvPath = sys_get_temp_dir() . '/test_prices_' . uniqid() . '.csv';
        file_put_contents($this->tempCsvPath, implode("\n", [
            'property_id,start_date,end_date,nightly_rate_cop,min_stay',
            '1606,2026-01-01,2026-06-30,350000,2',
            '1707,2026-01-01,2026-12-31,450000,2',
        ]));

        $this->tempCacheDir = sys_get_temp_dir() . '/test_admin_rate_cache_' . uniqid();
        mkdir($this->tempCacheDir, 0755, true);

        $ratesConfig = PropertyRatesConfig::createDefault();
        $this->rateRepo = new AdminRateRepository($this->pdo, $ratesConfig);

        $this->app = AdminApp::createDefault($this->pdo, [
            'csv_path' => $this->tempCsvPath,
            'cache_dir' => $this->tempCacheDir,
            'rates_config' => $ratesConfig,
        ]);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempCsvPath)) {
            unlink($this->tempCsvPath);
        }
        if (is_dir($this->tempCacheDir)) {
            foreach (glob($this->tempCacheDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->tempCacheDir);
        }
    }

    public function testUnauthenticatedRequestToRatesRedirectsToLogin(): void
    {
        $request = new Request('GET', '/rates');
        $session = [];

        $response = $this->app->handle($request, $session);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeader('Location'));
    }

    public function testAuthenticatedGetRatesReturns200(): void
    {
        $request = new Request('GET', '/rates', query: ['property_id' => '1606', 'year' => '2026']);
        $session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'admin_user_email' => 'admin@oceanviewflats.com',
            'admin_user_role' => 'admin',
            'csrf_token' => 'valid-csrf-token',
        ];

        $response = $this->app->handle($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('Seasonal Pricing & Rates', $body);
        $this->assertStringContainsString('Property:', $body);
        $this->assertStringNotContainsString('Unit:', $body);
        $this->assertStringContainsString('id="rates-view-container"', $body);
        $this->assertStringContainsString('id="rates-header-actions"', $body);

        // Active/inactive property and year pills
        $this->assertMatchesRegularExpression('/<a[^>]*aria-current="page"[^>]*class="[^"]*bg-indigo-600 text-white shadow-2xs[^"]*"[^>]*>\s*Apartment 1606\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*class="[^"]*bg-gray-100 text-gray-700 hover:bg-gray-200[^"]*"[^>]*>\s*Apartment 1707\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*aria-current="page"[^>]*class="[^"]*bg-gray-900 text-white font-semibold[^"]*"[^>]*>\s*2026\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*role="button"[^>]*>\s*Apartment 1606\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*role="button"[^>]*>\s*Apartment 1707\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*role="button"[^>]*>\s*2026\s*<\/a>/s', $body);
    }

    public function testHtmxGetRatesReturnsViewContainerAndOobHeaderActions(): void
    {
        $request = new Request(
            'GET',
            '/rates',
            query: ['property_id' => '1707', 'year' => '2027'],
            server: ['HTTP_HX_REQUEST' => 'true']
        );
        $session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'admin_user_email' => 'admin@oceanviewflats.com',
            'admin_user_role' => 'admin',
            'csrf_token' => 'valid-csrf-token',
        ];

        $response = $this->app->handle($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Must not contain full layout shell
        $this->assertStringNotContainsString('<!DOCTYPE html>', $body);

        // Must contain rates-view-container and OOB rates-header-actions
        $this->assertStringContainsString('id="rates-view-container"', $body);
        $this->assertStringContainsString('id="rates-header-actions" hx-swap-oob="outerHTML"', $body);

        // Active pills: 1707 and 2027
        $this->assertMatchesRegularExpression('/<a[^>]*aria-current="page"[^>]*class="[^"]*bg-indigo-600 text-white shadow-2xs[^"]*"[^>]*>\s*Apartment 1707\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*class="[^"]*bg-gray-100 text-gray-700 hover:bg-gray-200[^"]*"[^>]*>\s*Apartment 1606\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*aria-current="page"[^>]*class="[^"]*bg-gray-900 text-white font-semibold[^"]*"[^>]*>\s*2027\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*role="button"[^>]*>\s*Apartment 1707\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*role="button"[^>]*>\s*Apartment 1606\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*role="button"[^>]*>\s*2027\s*<\/a>/s', $body);

        // Sibling state preservation
        $this->assertStringContainsString('href="/rates?property_id=1606&year=2027"', $body);
        $this->assertStringContainsString('href="/rates?property_id=1707&year=2026"', $body);

        // Header actions sync
        $this->assertStringContainsString('/rates/new?property_id=1707&year=2027', $body);
        $this->assertStringContainsString('"property_id": "1707", "year": 2027', $body);
    }

    public function testHtmxGetRatesWithBodyTargetReturnsFullLayoutShell(): void
    {
        $request = new Request(
            'GET',
            '/rates',
            query: ['property_id' => '1606', 'year' => '2026'],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_TARGET' => 'body',
            ]
        );
        $session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'admin_user_email' => 'admin@oceanviewflats.com',
            'admin_user_role' => 'admin',
            'csrf_token' => 'valid-csrf-token',
        ];

        $response = $this->app->handle($request, $session);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Must contain full layout shell
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('<body', $body);
        $this->assertStringContainsString('Seasonal Pricing & Rates', $body);
        $this->assertStringContainsString('id="rates-view-container"', $body);
    }

    public function testPostRatesFailsWithoutCsrf(): void
    {
        $request = new Request('POST', '/rates', post: [
            'property_id' => '1606',
            'season_name' => 'Test Season',
        ]);
        $session = [
            'admin_user_id' => 1,
            'csrf_token' => 'valid-csrf-token',
        ];

        $response = $this->app->handle($request, $session);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('CSRF', $response->getBody());
    }

    public function testFullTierLifecycleThroughRouter(): void
    {
        $csrf = 'valid-csrf-token';
        $session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'admin_user_email' => 'admin@oceanviewflats.com',
            'admin_user_role' => 'admin',
            'csrf_token' => $csrf,
        ];

        // 1. GET /rates/new
        $reqNew = new Request('GET', '/rates/new', query: ['property_id' => '1606', 'year' => '2026']);
        $resNew = $this->app->handle($reqNew, $session);
        $this->assertSame(200, $resNew->getStatusCode());
        $this->assertStringContainsString('Create Seasonal Rate Tier', $resNew->getBody());

        // 2. POST /rates (Create)
        $reqCreate = new Request('POST', '/rates', post: [
            'csrf_token' => $csrf,
            'property_id' => '1606',
            'season_name' => 'Mid-Year Vacation',
            'start_date' => '2026-06-15',
            'end_date' => '2026-07-15',
            'price_per_night' => '420000',
            'min_stay' => '3',
            'year' => '2026',
        ]);
        $resCreate = $this->app->handle($reqCreate, $session);
        $this->assertSame(200, $resCreate->getStatusCode());
        $this->assertSame('rateUpdated', $resCreate->getHeader('HX-Trigger'));
        $this->assertStringContainsString('id="rates-view-container" hx-swap-oob="outerHTML"', $resCreate->getBody());
        $this->assertStringContainsString('id="rates-header-actions" hx-swap-oob="outerHTML"', $resCreate->getBody());
        $this->assertStringContainsString('<div id="modal-container" hx-swap-oob="innerHTML"></div>', $resCreate->getBody());

        $rates = $this->rateRepo->getRatesForProperty('1606', 2026);
        $this->assertCount(1, $rates);
        $tierId = (int) $rates[0]['id'];

        $cacheFile = $this->tempCacheDir . '/prices.json';
        $this->assertFileExists($cacheFile);
        $cachedAfterCreate = json_decode((string) file_get_contents($cacheFile), true);
        $this->assertCount(1, $cachedAfterCreate);
        $this->assertSame('1606', $cachedAfterCreate[0]['property_id']);
        $this->assertSame(420000, $cachedAfterCreate[0]['nightly_rate_cop']);
        $this->assertSame(3, $cachedAfterCreate[0]['minimum_stay']);
        $this->assertSame('Mid-Year Vacation', $cachedAfterCreate[0]['season_name']);

        // 3. GET /rates/{id}/edit
        $reqEdit = new Request('GET', "/rates/{$tierId}/edit");
        $resEdit = $this->app->handle($reqEdit, $session);
        $this->assertSame(200, $resEdit->getStatusCode());
        $this->assertStringContainsString('Edit Seasonal Rate Tier', $resEdit->getBody());
        $this->assertStringContainsString('Mid-Year Vacation', $resEdit->getBody());

        // 4. POST /rates/{id} (Update)
        $reqUpdate = new Request('POST', "/rates/{$tierId}", post: [
            'csrf_token' => $csrf,
            'season_name' => 'Mid-Year Vacation Renamed',
            'start_date' => '2026-06-15',
            'end_date' => '2026-07-20',
            'price_per_night' => '460000',
            'min_stay' => '4',
            'year' => '2026',
        ]);
        $resUpdate = $this->app->handle($reqUpdate, $session);
        $this->assertSame(200, $resUpdate->getStatusCode());
        $this->assertSame('rateUpdated', $resUpdate->getHeader('HX-Trigger'));
        $this->assertStringContainsString('id="rates-view-container" hx-swap-oob="outerHTML"', $resUpdate->getBody());
        $this->assertStringContainsString('id="rates-header-actions" hx-swap-oob="outerHTML"', $resUpdate->getBody());
        $this->assertStringContainsString('<div id="modal-container" hx-swap-oob="innerHTML"></div>', $resUpdate->getBody());

        $updatedTier = $this->rateRepo->findRateById($tierId);
        $this->assertNotNull($updatedTier);
        $this->assertSame('Mid-Year Vacation Renamed', $updatedTier['season_name']);

        $cachedAfterUpdate = json_decode((string) file_get_contents($cacheFile), true);
        $this->assertCount(1, $cachedAfterUpdate);
        $this->assertSame(460000, $cachedAfterUpdate[0]['nightly_rate_cop']);
        $this->assertSame(4, $cachedAfterUpdate[0]['minimum_stay']);
        $this->assertSame('Mid-Year Vacation Renamed', $cachedAfterUpdate[0]['season_name']);

        // 5. DELETE /rates/{id} with CSRF header
        $reqDelete = new Request(
            'DELETE',
            "/rates/{$tierId}?property_id=1606&year=2026",
            server: ['HTTP_X_CSRF_TOKEN' => $csrf]
        );
        $resDelete = $this->app->handle($reqDelete, $session);
        $this->assertSame(200, $resDelete->getStatusCode());
        $this->assertSame('rateUpdated', $resDelete->getHeader('HX-Trigger'));
        $this->assertStringContainsString('id="rates-view-container"', $resDelete->getBody());
        $this->assertStringContainsString('id="rates-header-actions" hx-swap-oob="outerHTML"', $resDelete->getBody());

        $this->assertNull($this->rateRepo->findRateById($tierId));

        $cachedAfterDelete = json_decode((string) file_get_contents($cacheFile), true);
        $this->assertSame([], $cachedAfterDelete);
    }

    public function testSeedFromCsvThroughRouter(): void
    {
        $csrf = 'valid-csrf-token';
        $session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'admin_user_email' => 'admin@oceanviewflats.com',
            'admin_user_role' => 'admin',
            'csrf_token' => $csrf,
        ];

        $reqSeed = new Request('POST', '/rates/seed-from-csv', post: [
            'csrf_token' => $csrf,
            'property_id' => '1606',
            'year' => '2026',
        ]);

        $resSeed = $this->app->handle($reqSeed, $session);
        $this->assertSame(200, $resSeed->getStatusCode());
        $this->assertSame('rateUpdated', $resSeed->getHeader('HX-Trigger'));
        $this->assertStringContainsString('id="rates-view-container"', $resSeed->getBody());
        $this->assertStringContainsString('id="rates-header-actions" hx-swap-oob="outerHTML"', $resSeed->getBody());
        $this->assertStringContainsString('Successfully imported 2 seasonal rate tiers', $resSeed->getBody());
    }
}
