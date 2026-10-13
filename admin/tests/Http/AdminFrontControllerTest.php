<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Http;

use OceanViewFlats\Admin\AdminApp;
use OceanViewFlats\Admin\Auth\AuthService;
use OceanViewFlats\Admin\Auth\InMemoryIpRateLimiter;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Tests\Support\AdminDatabaseTestHelper;
use OceanViewFlats\Domain\Reservation\InboundChannelSyncService;
use PDO;
use PHPUnit\Framework\TestCase;

final class AdminFrontControllerTest extends TestCase
{
    private PDO $pdo;
    private InMemoryIpRateLimiter $rateLimiter;
    private AuthService $authService;
    private AdminApp $app;
    private string $tempCacheDir;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        AdminDatabaseTestHelper::initializeSchema($this->pdo);

        $this->rateLimiter = new InMemoryIpRateLimiter(maxAttempts: 10, windowSeconds: 900);
        $this->authService = new AuthService($this->pdo, $this->rateLimiter);

        $this->tempCacheDir = sys_get_temp_dir() . '/ovf_test_cache_' . bin2hex(random_bytes(4));
        mkdir($this->tempCacheDir, 0777, true);
        $mockTransport = fn(string $url) => [
            'statusCode' => 200,
            'body' => "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nDTSTART;VALUE=DATE:20261101\r\nDTEND;VALUE=DATE:20261105\r\nSUMMARY:Reserved\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n",
            'error' => null,
        ];
        $channelSyncService = new InboundChannelSyncService(
            feedUrls: [
                '1606' => 'https://example.com/ical/1606.ics',
                '1707' => 'https://example.com/ical/1707.ics',
            ],
            cacheDir: $this->tempCacheDir,
            httpTransport: $mockTransport
        );

        $this->app = AdminApp::createDefault($this->pdo, [
            'rate_limiter' => $this->rateLimiter,
            'channel_sync_service' => $channelSyncService,
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->tempCacheDir) && is_dir($this->tempCacheDir)) {
            $files = glob($this->tempCacheDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            rmdir($this->tempCacheDir);
        }
    }

    private function createAdminUser(string $email, string $password, string $name = 'Super Admin'): int
    {
        $hash = $this->authService->hashPassword($password);
        $stmt = $this->pdo->prepare('
            INSERT INTO admin_users (email, password_hash, name, role, is_active)
            VALUES (:email, :hash, :name, "admin", 1)
        ');
        $stmt->execute([
            'email' => strtolower(trim($email)),
            'hash' => $hash,
            'name' => $name,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function testGetLoginRendersPageAndGeneratesCsrfToken(): void
    {
        $session = [];
        $request = new Request('GET', '/login');

        $response = $this->app->handle($request, $session);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($session['csrf_token']);
        $this->assertStringContainsString('Ocean View Flats Admin', $response->getBody());
        $this->assertStringContainsString((string) $session['csrf_token'], $response->getBody());
    }

    public function testGetLoginRedirectsToDashboardIfAlreadyAuthenticated(): void
    {
        $session = ['admin_user_id' => 1];
        $request = new Request('GET', '/login');

        $response = $this->app->handle($request, $session);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/', $response->getHeaders()['Location']);
    }

    public function testPostLoginFailsWithoutCsrfToken(): void
    {
        $session = ['csrf_token' => 'token_secret_123'];
        $request = new Request(
            method: 'POST',
            uri: '/login',
            post: ['email' => 'admin@test.com', 'password' => 'secret']
        );

        $response = $this->app->handle($request, $session);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('403 Forbidden', $response->getBody());
    }

    public function testPostLoginWithInvalidPasswordFailsAndLogsAudit(): void
    {
        $this->createAdminUser('admin@oceanviewflats.com', 'CorrectPass123!');
        $session = ['csrf_token' => 'valid_csrf_token'];

        $request = new Request(
            method: 'POST',
            uri: '/login',
            post: [
                'csrf_token' => 'valid_csrf_token',
                'email' => 'admin@oceanviewflats.com',
                'password' => 'WrongPassword',
            ],
            server: ['REMOTE_ADDR' => '192.168.1.5']
        );

        $response = $this->app->handle($request, $session);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('The email address or password entered is incorrect.', $response->getBody());
        $this->assertArrayNotHasKey('admin_user_id', $session);

        // Verify audit log entry
        $stmt = $this->pdo->query('SELECT action, entity_type, entity_id FROM admin_audit_logs');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $log = $stmt->fetch();
        $this->assertIsArray($log);
        $this->assertSame('login_failure', $log['action']);
        $this->assertSame('auth', $log['entity_type']);
        $this->assertSame('admin@oceanviewflats.com', $log['entity_id']);
    }

    public function testPostLoginSuccessEstablishesSessionAndRedirects(): void
    {
        $userId = $this->createAdminUser('manager@oceanviewflats.com', 'SecretPassword123!', 'Alice Manager');
        $session = ['csrf_token' => 'valid_csrf_token'];

        $request = new Request(
            method: 'POST',
            uri: '/login',
            post: [
                'csrf_token' => 'valid_csrf_token',
                'email' => 'manager@oceanviewflats.com',
                'password' => 'SecretPassword123!',
            ],
            server: ['REMOTE_ADDR' => '10.0.0.99', 'HTTP_USER_AGENT' => 'TestBrowser/1.0']
        );

        $response = $this->app->handle($request, $session);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/', $response->getHeaders()['Location']);

        $this->assertSame($userId, $session['admin_user_id']);
        $this->assertSame('Alice Manager', $session['admin_user_name']);
        $this->assertSame('manager@oceanviewflats.com', $session['admin_user_email']);
        $this->assertSame('admin', $session['admin_user_role']);
        $this->assertNotSame('valid_csrf_token', $session['csrf_token']);
        $this->assertSame(64, strlen((string) $session['csrf_token']));

        // Verify audit log
        $stmt = $this->pdo->prepare('SELECT action, entity_type, entity_id, admin_user_id FROM admin_audit_logs WHERE action = "login_success"');
        $stmt->execute();
        $log = $stmt->fetch();
        $this->assertIsArray($log);
        $this->assertSame((string) $userId, $log['entity_id']);
        $this->assertSame($userId, (int) $log['admin_user_id']);
    }

    public function testPostLoginRateLimitedReturns429WithRetryAfterHeader(): void
    {
        $this->createAdminUser('rate_limited@oceanviewflats.com', 'ValidPass123!');
        $session = ['csrf_token' => 'valid_csrf_token'];

        // Exhaust IP attempts (10 max)
        for ($i = 0; $i < 10; $i++) {
            $this->rateLimiter->recordFailure('198.51.100.5');
        }

        $request = new Request(
            method: 'POST',
            uri: '/login',
            post: [
                'csrf_token' => 'valid_csrf_token',
                'email' => 'rate_limited@oceanviewflats.com',
                'password' => 'ValidPass123!',
            ],
            server: ['REMOTE_ADDR' => '198.51.100.5']
        );

        $response = $this->app->handle($request, $session);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('900', $response->getHeaders()['Retry-After'] ?? null);
        $this->assertStringContainsString('Too many failed login attempts', $response->getBody());
    }

    public function testProtectedDashboardGatedByAuthMiddleware(): void
    {
        $session = []; // Unauthenticated
        $request = new Request('GET', '/');

        $response = $this->app->handle($request, $session);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaders()['Location']);
    }

    public function testProtectedDashboardRendersForAuthenticatedUser(): void
    {
        $session = [
            'admin_user_id' => 99,
            'admin_user_name' => 'Operator Bob',
            'admin_user_email' => 'bob@oceanviewflats.com',
            'admin_user_role' => 'admin',
        ];
        $request = new Request('GET', '/');

        $response = $this->app->handle($request, $session);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Operator Bob', $response->getBody());
        $this->assertStringContainsString('Sign Out', $response->getBody());
    }

    public function testLogoutClearsSessionAndRedirects(): void
    {
        $session = [
            'admin_user_id' => 99,
            'admin_user_name' => 'Operator Bob',
        ];
        $request = new Request('GET', '/logout');

        $response = $this->app->handle($request, $session);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login?reason=logged_out', $response->getHeaders()['Location']);
        $this->assertEmpty($session);

        // Verify audit log
        $stmt = $this->pdo->query('SELECT action, entity_id, admin_user_id FROM admin_audit_logs WHERE action = "logout"');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $log = $stmt->fetch();
        $this->assertIsArray($log);
        $this->assertSame('99', $log['entity_id']);
    }

    public function testNotFoundReturns404(): void
    {
        $session = ['admin_user_id' => 1];
        $request = new Request('GET', '/non-existent-page');

        $response = $this->app->handle($request, $session);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('404 Not Found', $response->getBody());
    }

    public function testMethodNotAllowedReturns405WithAllowHeader(): void
    {
        $session = [
            'admin_user_id' => 1,
            'csrf_token' => 'valid_csrf_token',
        ];
        $request = new Request(
            method: 'DELETE',
            uri: '/login',
            post: ['csrf_token' => 'valid_csrf_token']
        );

        $response = $this->app->handle($request, $session);

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET, POST', $response->getHeaders()['Allow']);
        $this->assertStringContainsString('405 Method Not Allowed', $response->getBody());
    }

    public function testEndToEndLoginFlowWithCsrfPersistenceOnLocalhost(): void
    {
        $userId = $this->createAdminUser('local_admin@oceanviewflats.com', 'LocalPass123!', 'Local Admin');

        // Step 1: GET /login on localhost
        $session = [];
        $getRequest = new Request(
            method: 'GET',
            uri: '/login',
            server: [
                'HTTP_HOST' => 'localhost:8080',
                'SERVER_NAME' => 'localhost',
                'REMOTE_ADDR' => '127.0.0.1',
            ]
        );

        $getResponse = $this->app->handle($getRequest, $session);

        $this->assertSame(200, $getResponse->getStatusCode());
        $this->assertNotEmpty($session['csrf_token']);
        $tokenOnPage = (string) $session['csrf_token'];

        // Verify cookie params on localhost are RFC 6265 compliant Host-Only cookies
        $cookieParams = \OceanViewFlats\Admin\Middleware\SessionMiddleware::resolveCookieParams('localhost', false);
        $this->assertSame('', $cookieParams['domain'], 'Localhost must use Host-Only cookies (empty domain)');
        $this->assertFalse($cookieParams['secure']);

        // Step 2: POST /login with retained session and CSRF token
        $postRequest = new Request(
            method: 'POST',
            uri: '/login',
            post: [
                'email' => 'local_admin@oceanviewflats.com',
                'password' => 'LocalPass123!',
                'csrf_token' => $tokenOnPage,
            ],
            server: [
                'HTTP_HOST' => 'localhost:8080',
                'SERVER_NAME' => 'localhost',
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_USER_AGENT' => 'Mozilla/5.0 Localhost Test',
            ]
        );

        $postResponse = $this->app->handle($postRequest, $session);

        // Crucial assertion: Must NOT be 403 Forbidden: Invalid CSRF Token
        $this->assertSame(302, $postResponse->getStatusCode());
        $this->assertSame('/', $postResponse->getHeaders()['Location']);
        $this->assertSame($userId, $session['admin_user_id']);
        $this->assertNotSame($tokenOnPage, $session['csrf_token'], 'CSRF token should rotate on login');

        // Step 3: GET / with authenticated session
        $dashboardRequest = new Request(
            method: 'GET',
            uri: '/',
            server: [
                'HTTP_HOST' => 'localhost:8080',
                'SERVER_NAME' => 'localhost',
                'REMOTE_ADDR' => '127.0.0.1',
            ]
        );

        $dashboardResponse = $this->app->handle($dashboardRequest, $session);
        $this->assertSame(200, $dashboardResponse->getStatusCode());
        $this->assertStringContainsString('Local Admin', $dashboardResponse->getBody());
        $this->assertStringContainsString((string) $session['csrf_token'], $dashboardResponse->getBody());
    }

    public function testEndToEndLoginFlowWithCsrfPersistenceBehindReverseProxy(): void
    {
        $userId = $this->createAdminUser('proxy_admin@oceanviewflats.com', 'ProxyPass123!', 'Proxy Admin');

        // Step 1: GET /login behind reverse proxy terminating SSL
        $session = [];
        $serverEnv = [
            'HTTP_HOST' => 'admin.oceanviewflats.com',
            'SERVER_NAME' => 'admin.oceanviewflats.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTPS' => 'off',
            'SERVER_PORT' => '80',
            'REMOTE_ADDR' => '10.0.1.20',
        ];

        $getRequest = new Request(
            method: 'GET',
            uri: '/login',
            server: $serverEnv
        );

        $getResponse = $this->app->handle($getRequest, $session);
        $this->assertSame(200, $getResponse->getStatusCode());
        $csrfToken = (string) $session['csrf_token'];

        // Step 2: POST /login with proxy HTTPS headers
        $postRequest = new Request(
            method: 'POST',
            uri: '/login',
            post: [
                'email' => 'proxy_admin@oceanviewflats.com',
                'password' => 'ProxyPass123!',
                'csrf_token' => $csrfToken,
            ],
            server: array_merge($serverEnv, ['HTTP_USER_AGENT' => 'Proxy Client'])
        );

        $postResponse = $this->app->handle($postRequest, $session);

        $this->assertSame(302, $postResponse->getStatusCode());
        $this->assertSame('/', $postResponse->getHeaders()['Location']);
        $this->assertSame($userId, $session['admin_user_id']);
    }

    public function testEndToEndLoginFlowWithCsrfPersistenceOnIpAddress(): void
    {
        $userId = $this->createAdminUser('ip_admin@oceanviewflats.com', 'IpPass123!', 'IP Admin');

        // Step 1: GET /login on raw IPv4 address
        $session = [];
        $serverEnv = [
            'HTTP_HOST' => '192.168.1.150:8000',
            'SERVER_NAME' => '192.168.1.150',
            'SERVER_PORT' => '8000',
            'REMOTE_ADDR' => '192.168.1.50',
        ];

        $getRequest = new Request(
            method: 'GET',
            uri: '/login',
            server: $serverEnv
        );

        $getResponse = $this->app->handle($getRequest, $session);
        $this->assertSame(200, $getResponse->getStatusCode());
        $csrfToken = (string) $session['csrf_token'];

        // Verify cookie params on IP address resolve to empty domain (Host-Only)
        $cookieParams = \OceanViewFlats\Admin\Middleware\SessionMiddleware::resolveCookieParams('192.168.1.150:8000', false);
        $this->assertSame('', $cookieParams['domain'], 'IP address must use empty domain per RFC 6265');
        $this->assertFalse($cookieParams['secure']);

        // Step 2: POST /login with retained session and CSRF token
        $postRequest = new Request(
            method: 'POST',
            uri: '/login',
            post: [
                'email' => 'ip_admin@oceanviewflats.com',
                'password' => 'IpPass123!',
                'csrf_token' => $csrfToken,
            ],
            server: array_merge($serverEnv, ['HTTP_USER_AGENT' => 'IP Browser Test'])
        );

        $postResponse = $this->app->handle($postRequest, $session);

        $this->assertSame(302, $postResponse->getStatusCode());
        $this->assertSame('/', $postResponse->getHeaders()['Location']);
        $this->assertSame($userId, $session['admin_user_id']);
    }

    public function testChannelSyncEndpointsRequireAuthentication(): void
    {
        $session = ['csrf_token' => 'valid_csrf_token'];

        // 1. Standard GET /channel-sync/card -> 302 redirect to /login
        $reqCard = new Request('GET', '/channel-sync/card');
        $respCard = $this->app->handle($reqCard, $session);
        $this->assertSame(302, $respCard->getStatusCode());
        $this->assertSame('/login', $respCard->getHeaders()['Location']);

        // 2. Standard POST /channel-sync (with valid CSRF token but unauthenticated) -> 302 redirect to /login
        $reqSync = new Request('POST', '/channel-sync', post: ['csrf_token' => 'valid_csrf_token']);
        $respSync = $this->app->handle($reqSync, $session);
        $this->assertSame(302, $respSync->getStatusCode());
        $this->assertSame('/login', $respSync->getHeaders()['Location']);

        // 3. HTMX GET /channel-sync/card -> 401 with HX-Redirect header
        $reqHtmx = new Request('GET', '/channel-sync/card', server: ['HTTP_HX_REQUEST' => 'true']);
        $respHtmx = $this->app->handle($reqHtmx, $session);
        $this->assertSame(401, $respHtmx->getStatusCode());
        $this->assertSame('/login', $respHtmx->getHeaders()['HX-Redirect']);

        // 4. Standard GET /channel-sync/panel -> 302 redirect to /login
        $reqPanel = new Request('GET', '/channel-sync/panel');
        $respPanel = $this->app->handle($reqPanel, $session);
        $this->assertSame(302, $respPanel->getStatusCode());
        $this->assertSame('/login', $respPanel->getHeaders()['Location']);
    }

    public function testChannelSyncPostRequiresValidCsrfToken(): void
    {
        $session = ['admin_user_id' => 1, 'csrf_token' => 'valid_secret_csrf'];

        // Request with missing or invalid CSRF token
        $request = new Request('POST', '/channel-sync', post: ['csrf_token' => 'wrong_token']);
        $response = $this->app->handle($request, $session);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('CSRF', $response->getBody());
    }

    public function testAuthenticatedChannelSyncEndpointsDispatchSuccessfully(): void
    {
        $session = ['admin_user_id' => 1, 'csrf_token' => 'valid_secret_csrf'];

        // 1. GET /channel-sync/card
        $getReq = new Request('GET', '/channel-sync/card');
        $getResp = $this->app->handle($getReq, $session);

        $this->assertSame(200, $getResp->getStatusCode());
        $this->assertStringContainsString('id="channel-card-container"', $getResp->getBody());
        $this->assertStringContainsString('Sync Now', $getResp->getBody());

        // 2. POST /channel-sync with HX-CSRF-Token header
        $postReq = new Request(
            method: 'POST',
            uri: '/channel-sync',
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'valid_secret_csrf',
                'REMOTE_ADDR' => '127.0.0.1',
            ]
        );
        $postResp = $this->app->handle($postReq, $session);

        $this->assertSame(200, $postResp->getStatusCode());
        $this->assertStringContainsString('id="channel-card-container"', $postResp->getBody());
        $this->assertStringContainsString('Sync Now', $postResp->getBody());

        // Verify audit log entry
        $stmt = $this->pdo->query('SELECT * FROM admin_audit_logs WHERE action = "channel_sync_manual"');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($log);
        $this->assertSame(1, (int) $log['admin_user_id']);
        $this->assertSame('channel_sync', $log['entity_type']);
    }

    public function testAuthenticatedChannelSyncPanelEndpointsDispatchSuccessfully(): void
    {
        $session = ['admin_user_id' => 1, 'admin_email' => 'admin@oceanviewflats.com', 'csrf_token' => 'valid_secret_csrf'];

        // 1. GET /channel-sync/panel
        $getPanelReq = new Request('GET', '/channel-sync/panel');
        $getPanelResp = $this->app->handle($getPanelReq, $session);

        $this->assertSame(200, $getPanelResp->getStatusCode());
        $this->assertStringContainsString('id="channel-sync-panel"', $getPanelResp->getBody());
        $this->assertStringContainsString('Property 1606', $getPanelResp->getBody());
        $this->assertStringContainsString('Property 1707', $getPanelResp->getBody());

        // 2. POST /channel-sync with property_id=1606&view=panel
        $postUnitReq = new Request(
            method: 'POST',
            uri: '/channel-sync',
            query: ['property_id' => '1606', 'view' => 'panel'],
            server: [
                'HTTP_HX_REQUEST' => 'true',
                'HTTP_HX_CSRF_TOKEN' => 'valid_secret_csrf',
                'REMOTE_ADDR' => '127.0.0.1',
            ]
        );
        $postUnitResp = $this->app->handle($postUnitReq, $session);

        $this->assertSame(200, $postUnitResp->getStatusCode());
        $this->assertStringContainsString('id="channel-sync-panel"', $postUnitResp->getBody());
        $this->assertStringContainsString('Feed synchronized successfully', $postUnitResp->getBody());

        // Verify audit log has entity_id = 1606
        $stmt = $this->pdo->query('SELECT * FROM admin_audit_logs WHERE action = "channel_sync_manual" AND entity_id = "1606"');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($log);
        $this->assertSame('1606', $log['entity_id']);

        // 3. GET /calendar-blocks includes channel sync panel
        $calendarReq = new Request('GET', '/calendar-blocks');
        $calendarResp = $this->app->handle($calendarReq, $session);

        $this->assertSame(200, $calendarResp->getStatusCode());
        $this->assertStringContainsString('id="channel-sync-panel"', $calendarResp->getBody());
        $this->assertStringContainsString('Inbound Channel Sync', $calendarResp->getBody());
    }
}

