<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Unit;

use OceanViewFlats\Admin\Setup\SetupRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class SetupTest extends TestCase
{
    private PDO $pdo;
    private string $validToken = 'test-secret-token-12345';
    private SetupRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->runner = new SetupRunner($this->pdo, 'test_db', [$this->validToken]);
    }

    public function testRejectsRequestWithoutToken(): void
    {
        $response = $this->runner->handleRequest([], [], []);

        $this->assertSame(403, $response['status']);
        $this->assertFalse($response['data']['success']);
        $this->assertStringContainsString('Invalid or missing setup token', (string) $response['data']['error']);
    }

    public function testRejectsRequestWithInvalidToken(): void
    {
        $response = $this->runner->handleRequest([], ['token' => 'wrong-token'], []);

        $this->assertSame(403, $response['status']);
        $this->assertFalse($response['data']['success']);
    }

    public function testRejectsTokenPassedViaQueryParam(): void
    {
        // Security hardening: query-string tokens leak in access logs & Referer headers and must be rejected
        $response = $this->runner->handleRequest([], ['token' => $this->validToken], []);

        $this->assertSame(403, $response['status']);
        $this->assertFalse($response['data']['success']);
    }

    public function testAcceptsValidTokenViaHeader(): void
    {
        $server = ['HTTP_X_SETUP_TOKEN' => $this->validToken];
        $response = $this->runner->handleRequest($server, [], []);

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['data']['success']);
    }

    public function testExecutesMigrationsWithValidToken(): void
    {
        $response = $this->runner->handleRequest(
            ['REQUEST_METHOD' => 'POST', 'HTTP_X_SETUP_TOKEN' => $this->validToken],
            [],
            ['action' => 'migrate']
        );

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['data']['success']);
        $this->assertNotEmpty($response['data']['logs']);

        // Assert tables now exist in SQLite
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tables = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        $this->assertContains('admin_users', $tables);
        $this->assertContains('reservations', $tables);
    }

    public function testProvisionsAdminUserAndEnforcesPermanentLockout(): void
    {
        // First run migrations so admin_users table exists
        $this->runner->runMigrations();

        // Provision initial admin
        $postData = [
            'action' => 'create_admin',
            'name' => 'Initial Superadmin',
            'email' => 'superadmin@oceanviewflats.com',
            'password' => 'SecurePass#2026',
        ];

        $response = $this->runner->handleRequest(
            ['REQUEST_METHOD' => 'POST', 'HTTP_X_SETUP_TOKEN' => $this->validToken],
            [],
            $postData
        );

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['data']['success']);
        $this->assertSame('superadmin@oceanviewflats.com', $response['data']['user']['email']);

        // Verify password in DB is hashed using Argon2id
        $stmt = $this->pdo->prepare('SELECT password_hash FROM admin_users WHERE email = ?');
        $stmt->execute(['superadmin@oceanviewflats.com']);
        $hash = (string) $stmt->fetchColumn();
        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertTrue(password_verify('SecurePass#2026', $hash));

        // Attempting ANY subsequent setup action must immediately fail with HTTP 403 Locked
        $subsequentResponse = $this->runner->handleRequest(
            ['REQUEST_METHOD' => 'GET', 'HTTP_X_SETUP_TOKEN' => $this->validToken],
            [],
            []
        );

        $this->assertSame(403, $subsequentResponse['status']);
        $this->assertFalse($subsequentResponse['data']['success']);
        $this->assertStringContainsString('Setup is locked', (string) $subsequentResponse['data']['error']);
    }

    public function testCreateAdminRejectsInvalidData(): void
    {
        $this->runner->runMigrations();

        // 1. Invalid email
        $response = $this->runner->handleRequest(
            ['REQUEST_METHOD' => 'POST', 'HTTP_X_SETUP_TOKEN' => $this->validToken],
            [],
            ['action' => 'create_admin', 'name' => 'Admin', 'email' => 'invalid-email', 'password' => 'validPass123']
        );
        $this->assertSame(400, $response['status']);
        $this->assertStringContainsString('valid email', (string) $response['data']['error']);

        // 2. Short password
        $response = $this->runner->handleRequest(
            ['REQUEST_METHOD' => 'POST', 'HTTP_X_SETUP_TOKEN' => $this->validToken],
            [],
            ['action' => 'create_admin', 'name' => 'Admin', 'email' => 'admin@test.com', 'password' => 'short']
        );
        $this->assertSame(400, $response['status']);
        $this->assertStringContainsString('at least 8 characters', (string) $response['data']['error']);

        // 3. Invalid role rejected
        $response = $this->runner->handleRequest(
            ['REQUEST_METHOD' => 'POST', 'HTTP_X_SETUP_TOKEN' => $this->validToken],
            [],
            ['action' => 'create_admin', 'name' => 'Admin', 'email' => 'admin@test.com', 'password' => 'validPass123', 'role' => 'root_shell']
        );
        $this->assertSame(400, $response['status']);
        $this->assertStringContainsString('Invalid administrator role', (string) $response['data']['error']);
    }

    public function testCreateAdminRequiresPost(): void
    {
        $response = $this->runner->handleRequest(
            ['REQUEST_METHOD' => 'GET', 'HTTP_X_SETUP_TOKEN' => $this->validToken],
            ['action' => 'create_admin'],
            []
        );

        $this->assertSame(405, $response['status']);
        $this->assertStringContainsString('requires an HTTP POST', (string) $response['data']['error']);
    }

    public function testProductionRequiresNativeHttpsAndIgnoresSpoofedForwardedProto(): void
    {
        $spoofedResponse = $this->runner->handleRequest(
            [
                'APP_ENV' => 'production',
                'REQUEST_METHOD' => 'GET',
                'HTTP_X_SETUP_TOKEN' => $this->validToken,
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ],
            [],
            []
        );
        $this->assertSame(403, $spoofedResponse['status']);
        $this->assertStringContainsString('HTTPS is required', (string) $spoofedResponse['data']['error']);

        $nativeHttpsResponse = $this->runner->handleRequest(
            [
                'APP_ENV' => 'production',
                'HTTPS' => 'on',
                'REQUEST_METHOD' => 'GET',
                'HTTP_X_SETUP_TOKEN' => $this->validToken,
            ],
            [],
            []
        );
        $this->assertSame(200, $nativeHttpsResponse['status']);
    }
}
