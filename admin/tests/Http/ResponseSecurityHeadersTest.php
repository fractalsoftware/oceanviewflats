<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Http;

use OceanViewFlats\Tests\Support\BuiltInHttpServer;
use PHPUnit\Framework\TestCase;

final class ResponseSecurityHeadersTest extends TestCase
{
    private ?BuiltInHttpServer $server = null;

    protected function setUp(): void
    {
        $rootDir = dirname(__DIR__, 3);
        $autoloadPath = var_export($rootDir . '/vendor/autoload.php', true);
        $setupPath = var_export($rootDir . '/admin/public/setup.php', true);

        $routerCode = <<<PHP
<?php
declare(strict_types=1);

require_once {$autoloadPath};

use OceanViewFlats\Admin\AdminApp;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Http\Response;
use OceanViewFlats\Admin\Tests\Support\AdminDatabaseTestHelper;

\$path = parse_url(\$_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (\$path === '/__baseline') {
    echo 'baseline';
    return true;
}

if (\$path === '/__direct-response') {
    Response::html('<h1>Direct</h1>', 200)->send();
    return true;
}

if (\$path === '/setup.php') {
    require {$setupPath};
    return true;
}

\$pdo = AdminDatabaseTestHelper::createDatabaseWithDefaultAdmin();
\$app = AdminApp::createDefault(\$pdo);
\$session = [];
\$response = \$app->handle(new Request(\$_SERVER['REQUEST_METHOD'] ?? 'GET', (string) \$path), \$session);
\$response->send();
return true;
PHP;

        $this->server = BuiltInHttpServer::start($routerCode, $rootDir);
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
        $this->server = null;
    }

    public function testAdminResponsesSuppressXPoweredByHeaderEvenWhenExposePhpIsOn(): void
    {
        $server = $this->server;
        $this->assertNotNull($server);

        // Control check: confirm expose_php=On actually emits X-Powered-By when not suppressed
        $baseline = $server->get('/__baseline');
        $this->assertSame(200, $baseline['status']);
        $this->assertArrayHasKey(
            'x-powered-by',
            $baseline['headers'],
            'Baseline endpoint must emit X-Powered-By under expose_php=On to validate test harness.'
        );

        // 1. Direct Response::send() (covers standard responses and the 500 fallback in admin/public/index.php)
        $direct = $server->get('/__direct-response');
        $this->assertSame(200, $direct['status']);
        $this->assertArrayNotHasKey('x-powered-by', $direct['headers'], 'Response::send() must remove X-Powered-By.');
        $this->assertSame('DENY', $direct['headers']['x-frame-options'] ?? null);
        $this->assertSame('nosniff', $direct['headers']['x-content-type-options'] ?? null);
        $this->assertSame('strict-origin-when-cross-origin', $direct['headers']['referrer-policy'] ?? null);

        // 2. Full AdminApp::handle() + Response::send() on GET /login
        $login = $server->get('/login');
        $this->assertSame(200, $login['status']);
        $this->assertArrayNotHasKey('x-powered-by', $login['headers'], 'Admin Console /login response must not emit X-Powered-By.');
        $this->assertSame('DENY', $login['headers']['x-frame-options'] ?? null);
        $this->assertSame('nosniff', $login['headers']['x-content-type-options'] ?? null);

        // 3. admin/public/setup.php bypasses Response::send() and must also remove X-Powered-By
        $setup = $server->get('/setup.php?format=json');
        $this->assertContains($setup['status'], [403, 500]);
        $this->assertArrayNotHasKey('x-powered-by', $setup['headers'], 'admin/public/setup.php must not emit X-Powered-By.');
    }
}
