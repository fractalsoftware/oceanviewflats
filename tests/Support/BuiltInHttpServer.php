<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Support;

use RuntimeException;

/**
 * Lightweight PHP built-in HTTP server harness for verifying SAPI-level
 * response headers (e.g. X-Powered-By suppression under expose_php=On).
 */
final class BuiltInHttpServer
{
    /** @var resource|null */
    private $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private string $tempDir = '';

    private int $port = 0;

    /**
     * @param list<string> $phpIniOverrides INI settings passed via -d (e.g. ['expose_php=On'])
     */
    public static function start(string $routerCode, string $workingDir, array $phpIniOverrides = ['expose_php=On']): self
    {
        $instance = new self();
        $instance->tempDir = sys_get_temp_dir() . '/ovf_http_test_' . bin2hex(random_bytes(4));
        mkdir($instance->tempDir, 0777, true);

        $routerFile = $instance->tempDir . '/router.php';
        file_put_contents($routerFile, $routerCode);

        $instance->port = self::findAvailablePort();

        $command = [PHP_BINARY];
        foreach ($phpIniOverrides as $ini) {
            $command[] = '-d';
            $command[] = $ini;
        }
        $command[] = '-S';
        $command[] = "127.0.0.1:{$instance->port}";
        $command[] = $routerFile;

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $instance->pipes, $workingDir);
        if (!is_resource($process)) {
            $instance->stop();
            throw new RuntimeException('Failed to start PHP built-in server for HTTP header verification.');
        }

        $instance->process = $process;
        $instance->waitUntilReady();

        return $instance;
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function request(string $method, string $path): array
    {
        $conn = stream_socket_client("tcp://127.0.0.1:{$this->port}", $errno, $errstr, 2.0);
        if (!is_resource($conn)) {
            throw new RuntimeException("HTTP connection failed: {$errstr} ({$errno})");
        }

        $rawRequest = "{$method} {$path} HTTP/1.1\r\nHost: 127.0.0.1:{$this->port}\r\nConnection: close\r\n\r\n";
        fwrite($conn, $rawRequest);
        $rawResponse = stream_get_contents($conn);
        fclose($conn);

        if (!is_string($rawResponse)) {
            throw new RuntimeException('Failed to read HTTP response from built-in server.');
        }

        $parts = explode("\r\n\r\n", $rawResponse, 2);
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

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function head(string $path): array
    {
        return $this->request('HEAD', $path);
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($this->process);
            $this->process = null;
            $this->pipes = [];
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
            $this->tempDir = '';
        }
    }

    private static function findAvailablePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!is_resource($socket)) {
            throw new RuntimeException("Unable to allocate free TCP port: {$errstr} ({$errno})");
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        if (!is_string($name)) {
            throw new RuntimeException('Unable to determine bound TCP port name.');
        }
        $parts = explode(':', $name);
        return (int) end($parts);
    }

    private function waitUntilReady(): void
    {
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $conn = @stream_socket_client("tcp://127.0.0.1:{$this->port}", $errno, $errstr, 0.2);
            if (is_resource($conn)) {
                fclose($conn);
                return;
            }
            usleep(50000);
        }
        throw new RuntimeException("PHP built-in server on port {$this->port} did not become ready within timeout.");
    }
}
