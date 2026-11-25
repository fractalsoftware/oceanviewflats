<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Http;

use PHPUnit\Framework\TestCase;

final class ResponseSecurityHeadersTest extends TestCase
{
    /** @var resource|null */
    private $serverProcess = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private string $tempDir = '';

    private int $port = 0;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ovf_admin_hdr_test_' . bin2hex(random_bytes(4));
        mkdir($this->tempDir, 0777, true);

        $rootDir = dirname(__DIR__, 3);
        $autoloadPath = var_export($rootDir . '/vendor/autoload.php', true);

        $routerScript = <<<PHP
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

if (\$path === '/__app-handle-only') {
    \$pdo = AdminDatabaseTestHelper::createDatabaseWithDefaultAdmin();
    \$app = AdminApp::createDefault(\$pdo);
    \$session = [];
    \$app->handle(new Request('GET', '/login'), \$session);
    echo 'handled';
    return true;
}

\$pdo = AdminDatabaseTestHelper::createDatabaseWithDefaultAdmin();
\$app = AdminApp::createDefault(\$pdo);
\$session = [];
\$response = \$app->handle(new Request(\$_SERVER['REQUEST_METHOD'] ?? 'GET', (string) \$path), \$session);
\$response->send();
return true;
PHP;

        $routerFile = $this->tempDir . '/router.php';
        file_put_contents($routerFile, $routerScript);

        $this->port = $this->findAvailablePort();
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            [PHP_BINARY, '-d', 'expose_php=On', '-S', "127.0.0.1:{$this->port}", $routerFile],
            $descriptorSpec,
            $this->pipes,
            $rootDir
        );

        $this->assertIsResource($process, 'Failed to start PHP built-in server for header verification.');
        $this->serverProcess = $process;
        $this->waitForServerReady($this->port);
    }

    protected function tearDown(): void
    {
        if (is_resource($this->serverProcess)) {
            proc_terminate($this->serverProcess);
            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($this->serverProcess);
            $this->serverProcess = null;
        }

        if ($this->tempDir !== '' && is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            @rmdir($this->tempDir);
        }
    }

    public function testAdminResponsesSuppressXPoweredByHeaderEvenWhenExposePhpIsOn(): void
    {
        // Control check: confirm expose_php=On actually emits X-Powered-By when not suppressed
        $baseline = $this->httpGet('/__baseline');
        $this->assertSame(200, $baseline['status']);
        $this->assertArrayHasKey(
            'x-powered-by',
            $baseline['headers'],
            'Baseline endpoint must emit X-Powered-By under expose_php=On to validate test harness.'
        );

        // 1. Direct Response::send() (also covers error fallback path in admin/public/index.php)
        $direct = $this->httpGet('/__direct-response');
        $this->assertSame(200, $direct['status']);
        $this->assertArrayNotHasKey('x-powered-by', $direct['headers'], 'Response::send() must remove X-Powered-By.');
        $this->assertSame('DENY', $direct['headers']['x-frame-options'] ?? null);
        $this->assertSame('nosniff', $direct['headers']['x-content-type-options'] ?? null);
        $this->assertSame('strict-origin-when-cross-origin', $direct['headers']['referrer-policy'] ?? null);

        // 2. AdminApp::handle() alone suppresses X-Powered-By before response dispatch
        $handleOnly = $this->httpGet('/__app-handle-only');
        $this->assertSame(200, $handleOnly['status']);
        $this->assertArrayNotHasKey('x-powered-by', $handleOnly['headers'], 'AdminApp::handle() must remove X-Powered-By.');

        // 3. Full AdminApp::handle() + Response::send() on GET /login
        $login = $this->httpGet('/login');
        $this->assertSame(200, $login['status']);
        $this->assertArrayNotHasKey('x-powered-by', $login['headers'], 'Admin Console /login response must not emit X-Powered-By.');
        $this->assertSame('DENY', $login['headers']['x-frame-options'] ?? null);
        $this->assertSame('nosniff', $login['headers']['x-content-type-options'] ?? null);
    }

    private function findAvailablePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertIsResource($socket, "Unable to allocate free TCP port: {$errstr} ({$errno})");
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->assertIsString($name);
        $parts = explode(':', $name);
        return (int) end($parts);
    }

    private function waitForServerReady(int $port): void
    {
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $conn = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 0.2);
            if (is_resource($conn)) {
                fclose($conn);
                return;
            }
            usleep(50000);
        }
        $this->fail("PHP built-in server on port {$port} did not become ready within timeout.");
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function httpGet(string $path): array
    {
        $conn = stream_socket_client("tcp://127.0.0.1:{$this->port}", $errno, $errstr, 2.0);
        $this->assertIsResource($conn, "HTTP connection failed: {$errstr} ({$errno})");

        $request = "GET {$path} HTTP/1.1\r\nHost: 127.0.0.1:{$this->port}\r\nConnection: close\r\n\r\n";
        fwrite($conn, $request);
        $raw = stream_get_contents($conn);
        fclose($conn);

        $this->assertIsString($raw);
        $parts = explode("\r\n\r\n", $raw, 2);
        $headerBlock = $parts[0];
        $body = $parts[1] ?? '';

        $lines = explode("\r\n", $headerBlock);
        $statusLine = array_shift($lines);
        preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $statusLine, $matches);
        $status = isset($matches[1]) ? (int) $matches[1] : 0;

        $headers = [];
        foreach ($lines as $line) {
            $colonPos = strpos($line, ':');
            if ($colonPos !== false) {
                $name = strtolower(trim(substr($line, 0, $colonPos)));
                $value = trim(substr($line, $colonPos + 1));
                $headers[$name] = $value;
            }
        }

        return [
            'status' => $status,
            'headers' => $headers,
            'body' => $body,
        ];
    }
}
