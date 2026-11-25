<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Integration\Api;

use PHPUnit\Framework\TestCase;

final class SecurityHeadersTest extends TestCase
{
    /** @var resource|null */
    private $serverProcess = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private string $tempDir = '';

    private int $port = 0;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ovf_api_hdr_test_' . bin2hex(random_bytes(4));
        mkdir($this->tempDir, 0777, true);

        $rootDir = dirname(__DIR__, 3);
        $apiDir = var_export($rootDir . '/public/api', true);

        $routerScript = <<<PHP
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

        $this->assertIsResource($process, 'Failed to start PHP built-in server for API header verification.');
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

        foreach (['ovf_ical_rate_limits.json', 'ovf_guide_access_rate_limits.json'] as $rateLimitFile) {
            $path = sys_get_temp_dir() . '/' . $rateLimitFile;
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    public function testApiEndpointsSuppressXPoweredByHeaderEvenWhenExposePhpIsOn(): void
    {
        // Control check: verify expose_php=On emits X-Powered-By when not explicitly removed
        $baseline = $this->httpGet('/__baseline');
        $this->assertSame(200, $baseline['status']);
        $this->assertArrayHasKey(
            'x-powered-by',
            $baseline['headers'],
            'Baseline endpoint must emit X-Powered-By under expose_php=On to validate test harness.'
        );

        // 1. /api/ical.php (enforce_security_headers_and_cors)
        $ical = $this->httpGet('/api/ical.php?property=invalid');
        $this->assertSame(400, $ical['status']);
        $this->assertArrayNotHasKey('x-powered-by', $ical['headers'], '/api/ical.php must not emit X-Powered-By.');
        $this->assertSame('DENY', $ical['headers']['x-frame-options'] ?? null);
        $this->assertSame('nosniff', $ical['headers']['x-content-type-options'] ?? null);
        $this->assertSame('Origin', $ical['headers']['vary'] ?? null);

        // 2. /api/guide-access.php (enforce_security_headers_and_cors)
        $guide = $this->httpGet('/api/guide-access.php');
        $this->assertSame(400, $guide['status']);
        $this->assertArrayNotHasKey('x-powered-by', $guide['headers'], '/api/guide-access.php must not emit X-Powered-By.');
        $this->assertSame('DENY', $guide['headers']['x-frame-options'] ?? null);
        $this->assertSame('nosniff', $guide['headers']['x-content-type-options'] ?? null);
        $this->assertSame('Origin', $guide['headers']['vary'] ?? null);

        // 3. /api/index.php (API directory shield)
        $index = $this->httpGet('/api/index.php');
        $this->assertSame(403, $index['status']);
        $this->assertArrayNotHasKey('x-powered-by', $index['headers'], '/api/index.php must not emit X-Powered-By.');
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
