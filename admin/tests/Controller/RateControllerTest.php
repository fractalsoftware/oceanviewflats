<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Controller;

use OceanViewFlats\Admin\Audit\AuditLogger;
use OceanViewFlats\Admin\Controller\RateController;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Repository\AdminRateRepository;
use OceanViewFlats\Admin\Tests\Support\AdminDatabaseTestHelper;
use OceanViewFlats\Admin\Views\ViewRenderer;
use OceanViewFlats\Domain\Quote\PropertyRatesConfig;
use PDO;
use PHPUnit\Framework\TestCase;

final class RateControllerTest extends TestCase
{
    private PDO $pdo;
    private AdminRateRepository $rateRepository;
    private ViewRenderer $viewRenderer;
    private AuditLogger $auditLogger;
    private PropertyRatesConfig $ratesConfig;
    private RateController $controller;
    private string $tempCsvPath;
    private string $tempCacheDir;

    /**
     * @var array<string, mixed>
     */
    private array $session;

    protected function setUp(): void
    {
        $this->pdo = AdminDatabaseTestHelper::createDatabaseWithDefaultAdmin();

        $this->ratesConfig = PropertyRatesConfig::createDefault();
        $this->rateRepository = new AdminRateRepository($this->pdo, $this->ratesConfig);
        $this->viewRenderer = new ViewRenderer(dirname(__DIR__, 2) . '/src/Views');
        $this->auditLogger = new AuditLogger($this->pdo);

        $this->tempCsvPath = sys_get_temp_dir() . '/test_prices_' . uniqid() . '.csv';
        file_put_contents($this->tempCsvPath, implode("\n", [
            'property_id,start_date,end_date,nightly_rate_cop,min_stay',
            '1606,2026-01-01,2026-06-30,350000,2',
            '1606,2026-12-15,2027-01-15,550000,4',
            '1707,2026-01-01,2026-12-31,450000,2',
        ]));

        $this->tempCacheDir = sys_get_temp_dir() . '/test_rate_cache_' . uniqid();
        mkdir($this->tempCacheDir, 0755, true);

        $this->controller = new RateController(
            rateRepository: $this->rateRepository,
            viewRenderer: $this->viewRenderer,
            auditLogger: $this->auditLogger,
            ratesConfig: $this->ratesConfig,
            csvPath: $this->tempCsvPath,
            cacheDir: $this->tempCacheDir
        );

        $this->session = [
            'admin_user_id' => 1,
            'admin_user_name' => 'Manuel Admin',
            'admin_user_email' => 'admin@oceanviewflats.com',
            'admin_user_role' => 'admin',
            'csrf_token' => 'test-csrf-token',
        ];
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

    public function testIndexRendersFullPageForDirectGet(): void
    {
        $request = new Request('GET', '/rates', query: ['property_id' => '1606', 'year' => '2026']);
        $response = $this->controller->index($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('Seasonal Pricing & Rates', $body);
        $this->assertStringContainsString('Property:', $body);
        $this->assertStringNotContainsString('Unit:', $body);
        $this->assertStringContainsString('id="rates-view-container"', $body);
        $this->assertStringContainsString('id="rates-header-actions"', $body);
        $this->assertStringContainsString('id="rates-content"', $body);
        $this->assertStringContainsString('id="modal-container"', $body);

        // Active/inactive property pills
        $this->assertMatchesRegularExpression('/<a[^>]*aria-current="page"[^>]*class="[^"]*bg-indigo-600 text-white shadow-2xs[^"]*"[^>]*>\s*Apartment 1606\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*class="[^"]*bg-gray-100 text-gray-700 hover:bg-gray-200[^"]*"[^>]*>\s*Apartment 1707\s*<\/a>/s', $body);
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*aria-current="page"[^>]*>\s*Apartment 1707\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*role="button"[^>]*>\s*Apartment 1606\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*role="button"[^>]*>\s*Apartment 1707\s*<\/a>/s', $body);

        // Active/inactive year pills
        $this->assertMatchesRegularExpression('/<a[^>]*aria-current="page"[^>]*class="[^"]*bg-gray-900 text-white font-semibold[^"]*"[^>]*>\s*2026\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*class="[^"]*bg-gray-100 text-gray-600 hover:bg-gray-200[^"]*"[^>]*>\s*2025\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*class="[^"]*bg-gray-100 text-gray-600 hover:bg-gray-200[^"]*"[^>]*>\s*2027\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*role="button"[^>]*>\s*2026\s*<\/a>/s', $body);

        // HTMX attributes on pills
        $this->assertStringContainsString('hx-target="#rates-view-container"', $body);
        $this->assertStringContainsString('hx-swap="outerHTML"', $body);
        $this->assertStringContainsString('hx-push-url="true"', $body);

        // Sibling filter URL state retention
        $this->assertStringContainsString('href="/rates?property_id=1707&year=2026"', $body);
        $this->assertStringContainsString('href="/rates?property_id=1606&year=2025"', $body);
        $this->assertStringContainsString('href="/rates?property_id=1606&year=2027"', $body);

        // Outer container attributes
        $this->assertStringContainsString('hx-get="/rates?property_id=1606&year=2026"', $body);
        $this->assertStringContainsString('hx-trigger="rateUpdated from:body"', $body);

        // Header actions synchronization
        $this->assertStringContainsString('/rates/new?property_id=1606&year=2026', $body);
        $this->assertStringContainsString('"property_id": "1606", "year": 2026', $body);
    }

    public function testIndexRendersPartialForHtmxRequest(): void
    {
        $request = new Request(
            'GET',
            '/rates',
            query: ['property_id' => '1707', 'year' => '2027'],
            server: ['HTTP_HX_REQUEST' => 'true']
        );
        $response = $this->controller->index($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Should NOT contain the layout navbar or outer shell
        $this->assertStringNotContainsString('<!DOCTYPE html>', $body);
        $this->assertStringNotContainsString('<body', $body);

        // Should contain rates-view-container and OOB rates-header-actions
        $this->assertStringContainsString('id="rates-view-container"', $body);
        $this->assertStringContainsString('id="rates-header-actions" hx-swap-oob="outerHTML"', $body);

        // Property 1707 active, Property 1606 inactive
        $this->assertMatchesRegularExpression('/<a[^>]*aria-current="page"[^>]*class="[^"]*bg-indigo-600 text-white shadow-2xs[^"]*"[^>]*>\s*Apartment 1707\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*class="[^"]*bg-gray-100 text-gray-700 hover:bg-gray-200[^"]*"[^>]*>\s*Apartment 1606\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*role="button"[^>]*>\s*Apartment 1707\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*role="button"[^>]*>\s*Apartment 1606\s*<\/a>/s', $body);

        // Year 2027 active
        $this->assertMatchesRegularExpression('/<a[^>]*aria-current="page"[^>]*class="[^"]*bg-gray-900 text-white font-semibold[^"]*"[^>]*>\s*2027\s*<\/a>/s', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*role="button"[^>]*>\s*2027\s*<\/a>/s', $body);

        // Sibling filter URL state retention
        $this->assertStringContainsString('href="/rates?property_id=1606&year=2027"', $body);
        $this->assertStringContainsString('href="/rates?property_id=1707&year=2026"', $body);

        // Header actions synchronization for 1707 and 2027
        $this->assertStringContainsString('/rates/new?property_id=1707&year=2027', $body);
        $this->assertStringContainsString('"property_id": "1707", "year": 2027', $body);

        // Should contain timeline and table
        $this->assertStringContainsString('Seasonal Timeline & Coverage', $body);
        $this->assertStringContainsString('Configured Seasonal Tiers', $body);
    }

    public function testIndexRendersFullPageWhenHtmxTargetIsBody(): void
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
        $response = $this->controller->index($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Must render the full layout shell rather than partials
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('<body', $body);
        $this->assertStringContainsString('Seasonal Pricing & Rates', $body);
        $this->assertStringContainsString('id="rates-view-container"', $body);
    }

    public function testNewTierRendersCreationModal(): void
    {
        $request = new Request('GET', '/rates/new', query: ['property_id' => '1606', 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $response = $this->controller->newTier($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        $this->assertStringContainsString('Create Seasonal Rate Tier', $body);
        $this->assertStringContainsString('name="season_name"', $body);
        $this->assertStringContainsString('2026-07-01', $body);
        $this->assertStringContainsString('2026-07-31', $body);
    }

    public function testCreateValidatesRequiredFieldsAndReturns422(): void
    {
        // Missing season name
        $request = new Request('POST', '/rates', post: [
            'property_id' => '1606',
            'season_name' => '',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'price_per_night' => '350000',
            'min_stay' => '2',
        ]);

        $response = $this->controller->create($request, $this->session);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Season name is required', $response->getBody());
        $this->assertSame('#modal-container', $response->getHeader('HX-Retarget'));
    }

    public function testCreateValidatesDateOrderAndReturns422(): void
    {
        // start_date >= end_date
        $request = new Request('POST', '/rates', post: [
            'property_id' => '1606',
            'season_name' => 'Inverted Dates',
            'start_date' => '2026-06-30',
            'end_date' => '2026-06-01',
            'price_per_night' => '350000',
            'min_stay' => '2',
        ]);

        $response = $this->controller->create($request, $this->session);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Start date must be strictly before end date', $response->getBody());
    }

    public function testCreateValidatesPriceAndMinStayAndReturns422(): void
    {
        // zero price
        $request = new Request('POST', '/rates', post: [
            'property_id' => '1606',
            'season_name' => 'Free Stay',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-15',
            'price_per_night' => '0',
            'min_stay' => '2',
        ]);

        $response = $this->controller->create($request, $this->session);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Nightly rate must be greater than zero', $response->getBody());

        // zero min_stay
        $request2 = new Request('POST', '/rates', post: [
            'property_id' => '1606',
            'season_name' => 'Zero Stay',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-15',
            'price_per_night' => '350000',
            'min_stay' => '0',
        ]);

        $response2 = $this->controller->create($request2, $this->session);
        $this->assertSame(422, $response2->getStatusCode());
        $this->assertStringContainsString('Minimum stay must be at least 1 night', $response2->getBody());
    }

    public function testCreateRejectsOverlappingIntervalWith422(): void
    {
        // Pre-create tier
        $this->rateRepository->createRate([
            'property_id' => '1606',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'season_name' => 'Existing April',
            'price_per_night' => 400000.0,
            'min_stay' => 2,
        ]);

        // Attempt overlap
        $request = new Request('POST', '/rates', post: [
            'property_id' => '1606',
            'season_name' => 'Colliding Easter',
            'start_date' => '2026-04-15',
            'end_date' => '2026-05-15',
            'price_per_night' => '500000',
            'min_stay' => '3',
        ]);

        $response = $this->controller->create($request, $this->session);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('overlaps with an existing seasonal rate tier', $response->getBody());
    }

    public function testCreatePersistsTierAndRecordsAuditLog(): void
    {
        $request = new Request('POST', '/rates', post: [
            'property_id' => '1606',
            'season_name' => 'Semana Santa',
            'start_date' => '2026-03-25',
            'end_date' => '2026-04-05',
            'price_per_night' => '480000',
            'min_stay' => '3',
            'year' => '2026',
        ]);

        $response = $this->controller->create($request, $this->session);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('rateUpdated', $response->getHeader('HX-Trigger'));
        $this->assertStringContainsString('id="rates-view-container" hx-swap-oob="outerHTML"', $response->getBody());
        $this->assertStringContainsString('id="rates-header-actions" hx-swap-oob="outerHTML"', $response->getBody());
        $this->assertStringContainsString('<div id="modal-container" hx-swap-oob="innerHTML"></div>', $response->getBody());
        $this->assertStringContainsString('Semana Santa', $response->getBody());

        // Verify tier in database
        $rates = $this->rateRepository->getRatesForProperty('1606', 2026);
        $this->assertCount(1, $rates);
        $this->assertSame('Semana Santa', $rates[0]['season_name']);
        $this->assertEquals(480000.0, (float) $rates[0]['price_per_night']);

        // Verify audit log
        $stmt = $this->pdo->query('SELECT * FROM admin_audit_logs WHERE action = "rate_tier_created"');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $log = $stmt->fetch();
        $this->assertNotFalse($log);
        $this->assertSame('property_rates', $log['entity_type']);
        $this->assertSame((string) $rates[0]['id'], $log['entity_id']);
        $this->assertSame(1, (int) $log['admin_user_id']);
        $after = json_decode((string) $log['payload_after'], true);
        $this->assertSame('Semana Santa', $after['season_name']);
    }

    public function testEditRendersPopulatedModalOrReturns404(): void
    {
        $id = $this->rateRepository->createRate([
            'property_id' => '1707',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'season_name' => 'Feria de Flores',
            'price_per_night' => 520000.0,
            'min_stay' => 3,
        ]);

        $request = new Request('GET', "/rates/{$id}/edit", attributes: ['id' => (string) $id]);
        $response = $this->controller->edit($request, $this->session);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Edit Seasonal Rate Tier', $response->getBody());
        $this->assertStringContainsString('Feria de Flores', $response->getBody());

        // 404 test
        $request404 = new Request('GET', '/rates/9999/edit', attributes: ['id' => '9999']);
        $response404 = $this->controller->edit($request404, $this->session);
        $this->assertSame(404, $response404->getStatusCode());
    }

    public function testUpdateModifiesTierAndChecksSelfOverlap(): void
    {
        $id = $this->rateRepository->createRate([
            'property_id' => '1606',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'season_name' => 'May Promo',
            'price_per_night' => 320000.0,
            'min_stay' => 2,
        ]);

        // Modifying within own range should not trigger self-overlap
        $request = new Request('POST', "/rates/{$id}", attributes: ['id' => (string) $id], post: [
            'season_name' => 'May Promo Extended',
            'start_date' => '2026-05-05',
            'end_date' => '2026-05-25',
            'price_per_night' => '340000',
            'min_stay' => '3',
            'year' => '2026',
        ]);

        $response = $this->controller->update($request, $this->session);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('rateUpdated', $response->getHeader('HX-Trigger'));
        $this->assertStringContainsString('id="rates-view-container" hx-swap-oob="outerHTML"', $response->getBody());
        $this->assertStringContainsString('id="rates-header-actions" hx-swap-oob="outerHTML"', $response->getBody());
        $this->assertStringContainsString('<div id="modal-container" hx-swap-oob="innerHTML"></div>', $response->getBody());
        $this->assertStringContainsString('May Promo Extended', $response->getBody());

        $updated = $this->rateRepository->findRateById($id);
        $this->assertNotNull($updated);
        $this->assertSame('May Promo Extended', $updated['season_name']);
        $this->assertEquals(340000.0, (float) $updated['price_per_night']);

        // Verify audit log
        $stmt = $this->pdo->query('SELECT * FROM admin_audit_logs WHERE action = "rate_tier_updated"');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $log = $stmt->fetch();
        $this->assertNotFalse($log);
        $this->assertSame((string) $id, $log['entity_id']);
    }

    public function testRateUpdatedSubsequentGetRetainsFlashMessage(): void
    {
        $createRequest = new Request('POST', '/rates', post: [
            'property_id' => '1606',
            'season_name' => 'High Summer',
            'start_date' => '2026-07-01',
            'end_date' => '2026-08-15',
            'price_per_night' => '500000',
            'min_stay' => '3',
            'year' => '2026',
        ]);

        $createResponse = $this->controller->create($createRequest, $this->session);
        $this->assertSame(200, $createResponse->getStatusCode());
        $this->assertArrayHasKey('rate_flash', $this->session);
        $this->assertSame("Seasonal tier 'High Summer' created successfully.", $this->session['rate_flash']['message']);

        // Subsequent GET /rates (triggered by HTMX rateUpdated from:body)
        $subsequentRequest = new Request(
            'GET',
            '/rates',
            query: ['property_id' => '1606', 'year' => '2026'],
            server: ['HTTP_HX_REQUEST' => 'true']
        );
        $subsequentResponse = $this->controller->index($subsequentRequest, $this->session);
        $this->assertSame(200, $subsequentResponse->getStatusCode());
        $body = $subsequentResponse->getBody();

        // Flash message should be displayed in the re-fetched view container
        $this->assertStringContainsString("Seasonal tier &#039;High Summer&#039; created successfully.", $body);
        // And the session flash message should be consumed (unset)
        $this->assertArrayNotHasKey('rate_flash', $this->session);
    }

    public function testDeleteRemovesTierAndRecordsAuditLog(): void
    {
        $id = $this->rateRepository->createRate([
            'property_id' => '1606',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'season_name' => 'October Fest',
            'price_per_night' => 390000.0,
            'min_stay' => 2,
        ]);

        $request = new Request(
            'DELETE',
            "/rates/{$id}",
            attributes: ['id' => (string) $id],
            query: ['property_id' => '1606', 'year' => '2026']
        );

        $response = $this->controller->delete($request, $this->session);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('rateUpdated', $response->getHeader('HX-Trigger'));
        $this->assertStringContainsString('id="rates-view-container"', $response->getBody());
        $this->assertStringContainsString('id="rates-header-actions" hx-swap-oob="outerHTML"', $response->getBody());
        $this->assertStringContainsString('deleted successfully', $response->getBody());

        // Verify removed
        $this->assertNull($this->rateRepository->findRateById($id));

        // Verify audit log
        $stmt = $this->pdo->query('SELECT * FROM admin_audit_logs WHERE action = "rate_tier_deleted"');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $log = $stmt->fetch();
        $this->assertNotFalse($log);
        $this->assertSame((string) $id, $log['entity_id']);
        $before = json_decode((string) $log['payload_before'], true);
        $this->assertSame('October Fest', $before['season_name']);
    }

    public function testSeedFromCsvImportsTiersAndRecordsAudit(): void
    {
        $request = new Request('POST', '/rates/seed-from-csv', post: [
            'property_id' => '1606',
            'year' => '2026',
        ]);

        $response = $this->controller->seedFromCsv($request, $this->session);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('rateUpdated', $response->getHeader('HX-Trigger'));
        $this->assertStringContainsString('id="rates-view-container"', $response->getBody());
        $this->assertStringContainsString('id="rates-header-actions" hx-swap-oob="outerHTML"', $response->getBody());
        $this->assertStringContainsString('Successfully imported 3 seasonal rate tiers from prices.csv', $response->getBody());

        // Verify tiers were imported
        $rates1606 = $this->rateRepository->getRatesForProperty('1606');
        $this->assertCount(2, $rates1606);

        $rates1707 = $this->rateRepository->getRatesForProperty('1707');
        $this->assertCount(1, $rates1707);

        // Verify audit log
        $stmt = $this->pdo->query('SELECT * FROM admin_audit_logs WHERE action = "rate_tiers_seeded"');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $log = $stmt->fetch();
        $this->assertNotFalse($log);
        $after = json_decode((string) $log['payload_after'], true);
        $this->assertSame(3, $after['seeded_count']);

        // Verify prices.json cache was atomically synchronized
        $cacheFile = $this->tempCacheDir . '/prices.json';
        $this->assertFileExists($cacheFile);
        $cachedPrices = json_decode((string) file_get_contents($cacheFile), true);
        $this->assertIsArray($cachedPrices);
        $this->assertCount(3, $cachedPrices);
        $this->assertSame('1606', $cachedPrices[0]['property_id']);
        $this->assertSame(350000, $cachedPrices[0]['nightly_rate_cop']);
        $this->assertSame(2, $cachedPrices[0]['minimum_stay']);
    }

    public function testModalTriggersAndFormExplicitlyDeclareInnerHTMLSwap(): void
    {
        $id = $this->rateRepository->createRate([
            'property_id' => '1606',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'season_name' => 'July High Season',
            'price_per_night' => 450000.0,
            'min_stay' => 2,
        ]);

        // 1. Check Full Rates Page
        $request = new Request('GET', '/rates', query: ['property_id' => '1606', 'year' => '2026']);
        $response = $this->controller->index($request, $this->session);
        $body = $response->getBody();

        // rates-content must specify innerHTML swap to avoid inheriting outerHTML from rates-view-container
        $this->assertStringContainsString('id="rates-content" hx-swap="innerHTML"', $body);

        // Every trigger targeting #modal-container must explicitly define hx-swap="innerHTML"
        preg_match_all('/<[^>]+hx-target="#modal-container"[^>]*>/i', $body, $matches);
        $this->assertNotEmpty($matches[0], 'Expected elements targeting #modal-container');
        foreach ($matches[0] as $tag) {
            $this->assertStringContainsString(
                'hx-swap="innerHTML"',
                $tag,
                "Element targeting #modal-container must explicitly declare hx-swap=\"innerHTML\": {$tag}"
            );
        }

        // 2. Check Edit Modal Form
        $editRequest = new Request('GET', "/rates/{$id}/edit", attributes: ['id' => (string) $id]);
        $editResponse = $this->controller->edit($editRequest, $this->session);
        $editBody = $editResponse->getBody();

        $this->assertMatchesRegularExpression(
            '/<form[^>]+id="rate-tier-form"[^>]+hx-swap="innerHTML"[^>]*>/i',
            $editBody,
            'Edit modal form must explicitly declare hx-swap="innerHTML"'
        );

        // 3. Check New Modal Form
        $newRequest = new Request('GET', '/rates/new', query: ['property_id' => '1606', 'year' => '2026']);
        $newResponse = $this->controller->newTier($newRequest, $this->session);
        $newBody = $newResponse->getBody();

        $this->assertMatchesRegularExpression(
            '/<form[^>]+id="rate-tier-form"[^>]+hx-swap="innerHTML"[^>]*>/i',
            $newBody,
            'New modal form must explicitly declare hx-swap="innerHTML"'
        );
    }
}

