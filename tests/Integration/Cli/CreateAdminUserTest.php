<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Integration\Cli;

use OceanViewFlats\Admin\Auth\AuthService;
use OceanViewFlats\Admin\Cli\AdminUserProvisioner;
use OceanViewFlats\Admin\Tests\Support\AdminDatabaseTestHelper;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class CreateAdminUserTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = AdminDatabaseTestHelper::createDatabase();
    }

    /**
     * Invokes AdminUserProvisioner::run directly with clean dependency injection.
     *
     * @param array<int, string> $arguments
     * @param ?PDO $pdo
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function executeProvisioner(array $arguments, ?PDO $pdo = null): array
    {
        $activePdo = $pdo ?? $this->pdo;
        $argv = array_merge(['scripts/create-admin-user.php'], $arguments);

        $outStream = fopen('php://temp', 'w+');
        $errStream = fopen('php://temp', 'w+');
        $this->assertNotFalse($outStream);
        $this->assertNotFalse($errStream);

        $exitCode = AdminUserProvisioner::run($argv, $activePdo, $outStream, $errStream);

        rewind($outStream);
        $stdout = (string) stream_get_contents($outStream);
        fclose($outStream);

        rewind($errStream);
        $stderr = (string) stream_get_contents($errStream);
        fclose($errStream);

        return [
            'exitCode' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    /**
     * Executes scripts/create-admin-user.php in an actual CLI subprocess using proc_open
     * for true end-to-end testing without global variable pollution.
     *
     * @param array<int, string> $arguments
     * @param array<string, string> $environment
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function executeSubprocess(array $arguments, array $environment = []): array
    {
        $scriptPath = dirname(__DIR__, 3) . '/scripts/create-admin-user.php';
        $cmd = array_merge(['php', $scriptPath], $arguments);

        $env = array_merge($_ENV, $environment);

        $process = proc_open(
            $cmd,
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            dirname(__DIR__, 3),
            $env
        );

        $this->assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    public function testFailsValidationWhenEmailIsMissingOrInvalid(): void
    {
        // 1. Missing email
        $resMissing = $this->executeProvisioner(['--name=Admin', '--password=ValidPassword123!']);
        $this->assertSame(1, $resMissing['exitCode']);
        $this->assertStringContainsString('Invalid or missing email address', $resMissing['stderr']);

        // 2. Invalid email format
        $resInvalid = $this->executeProvisioner(['--email=not-an-email', '--name=Admin', '--password=ValidPassword123!']);
        $this->assertSame(1, $resInvalid['exitCode']);
        $this->assertStringContainsString('Invalid or missing email address', $resInvalid['stderr']);

        // Verify no user was inserted
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM admin_users');
        $this->assertNotFalse($stmt);
        $count = (int) $stmt->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testFailsValidationWhenPasswordIsTooShort(): void
    {
        $shortSecret = 'short7!';
        $res = $this->executeProvisioner([
            '--email=admin@oceanviewflats.com',
            '--name=Admin User',
            '--password=' . $shortSecret,
        ]);

        $this->assertSame(1, $res['exitCode']);
        $this->assertStringContainsString('Password must be at least 8 characters long', $res['stderr']);

        // Assert plaintext password does NOT leak in stdout or stderr
        $this->assertStringNotContainsString($shortSecret, $res['stdout']);
        $this->assertStringNotContainsString($shortSecret, $res['stderr']);

        $stmt = $this->pdo->query('SELECT COUNT(*) FROM admin_users');
        $this->assertNotFalse($stmt);
        $count = (int) $stmt->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testFailsValidationWhenNameIsEmpty(): void
    {
        $res = $this->executeProvisioner([
            '--email=admin@oceanviewflats.com',
            '--name=',
            '--password=ValidPassword123!',
        ]);

        $this->assertSame(1, $res['exitCode']);
        $this->assertStringContainsString('Name is required and cannot be empty', $res['stderr']);
    }

    public function testSuccessfulAdminUserCreationWithArgon2id(): void
    {
        $password = 'CorrectHorseBatteryStaple123!';
        $res = $this->executeProvisioner([
            '--email=provisioned@oceanviewflats.com',
            '--name=Super Administrator',
            '--password=' . $password,
            '--role=superadmin',
        ]);

        $this->assertSame(0, $res['exitCode'], 'Failed with stderr: ' . $res['stderr']);
        $this->assertStringContainsString('Admin user successfully created', $res['stdout']);
        $this->assertStringContainsString('provisioned@oceanviewflats.com', $res['stdout']);
        $this->assertStringContainsString('superadmin', $res['stdout']);

        // Security invariant: password must never appear in stdout or stderr
        $this->assertStringNotContainsString($password, $res['stdout']);
        $this->assertStringNotContainsString($password, $res['stderr']);

        // Inspect database row
        $stmt = $this->pdo->prepare('SELECT * FROM admin_users WHERE email = :email');
        $stmt->execute([':email' => 'provisioned@oceanviewflats.com']);
        /** @var array<string, mixed>|false $user */
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($user);
        $this->assertSame('provisioned@oceanviewflats.com', $user['email']);
        $this->assertSame('Super Administrator', $user['name']);
        $this->assertSame('superadmin', $user['role']);
        $this->assertSame(1, (int) $user['is_active']);
        $this->assertSame(0, (int) $user['failed_login_attempts']);
        $this->assertNull($user['locked_until']);

        // Verify Argon2id hash format and password verification
        $hash = (string) $user['password_hash'];
        if (defined('PASSWORD_ARGON2ID')) {
            $this->assertStringStartsWith('$argon2id$', $hash);
        }
        $this->assertTrue(password_verify($password, $hash));
        $this->assertFalse(password_verify('WrongPassword123!', $hash));

        // Verify AuthService can authenticate this newly provisioned admin
        $authService = new AuthService($this->pdo);
        $loginResult = $authService->authenticate('provisioned@oceanviewflats.com', $password);
        $this->assertTrue($loginResult->isSuccess());
        $this->assertSame('provisioned@oceanviewflats.com', $loginResult->getUser()['email'] ?? null);
    }

    public function testIdempotentUpdateOnExistingEmailResetsLockoutAndUpdatesDetails(): void
    {
        // Seed initial locked & inactive account
        $initialStmt = $this->pdo->prepare('
            INSERT INTO admin_users (email, password_hash, name, role, is_active, failed_login_attempts, locked_until)
            VALUES (:email, :hash, :name, :role, 0, 8, "2030-01-01 12:00:00")
        ');
        $initialStmt->execute([
            ':email' => 'manager@oceanviewflats.com',
            ':hash' => password_hash('OldPassword123!', PASSWORD_DEFAULT),
            ':name' => 'Old Manager Name',
            ':role' => 'editor',
        ]);

        $newPassword = 'NewRobustPassword2026!';

        // Run provisioning with the same email
        $res = $this->executeProvisioner([
            '--email=manager@oceanviewflats.com',
            '--name=Promoted Manager',
            '--password=' . $newPassword,
            '--role=admin',
        ]);

        $this->assertSame(0, $res['exitCode'], 'Failed with stderr: ' . $res['stderr']);
        $this->assertStringContainsString('Admin user successfully updated', $res['stdout']);
        $this->assertStringContainsString('manager@oceanviewflats.com', $res['stdout']);

        // Security invariant: password must never appear in stdout or stderr
        $this->assertStringNotContainsString($newPassword, $res['stdout']);
        $this->assertStringNotContainsString($newPassword, $res['stderr']);

        // Ensure exactly 1 user exists with this email
        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE email = :email');
        $countStmt->execute([':email' => 'manager@oceanviewflats.com']);
        $this->assertSame(1, (int) $countStmt->fetchColumn());

        // Check updated properties and reset lockout
        $stmt = $this->pdo->prepare('SELECT * FROM admin_users WHERE email = :email');
        $stmt->execute([':email' => 'manager@oceanviewflats.com']);
        /** @var array<string, mixed>|false $updatedUser */
        $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($updatedUser);
        $this->assertSame('Promoted Manager', $updatedUser['name']);
        $this->assertSame('admin', $updatedUser['role']);
        $this->assertSame(1, (int) $updatedUser['is_active']);
        $this->assertSame(0, (int) $updatedUser['failed_login_attempts']);
        $this->assertNull($updatedUser['locked_until']);

        // Verify password hash updated
        $hash = (string) $updatedUser['password_hash'];
        $this->assertTrue(password_verify($newPassword, $hash));
        $this->assertFalse(password_verify('OldPassword123!', $hash));

        // Verify AuthService can authenticate the newly unlocked account
        $authService = new AuthService($this->pdo);
        $loginResult = $authService->authenticate('manager@oceanviewflats.com', $newPassword);
        $this->assertTrue($loginResult->isSuccess());
    }

    public function testShortOptionsSupported(): void
    {
        $res = $this->executeProvisioner([
            '-e', 'shortopts@oceanviewflats.com',
            '-n', 'Short Options User',
            '-p', 'ShortOptionsPass123!',
            '-r', 'admin',
        ]);

        $this->assertSame(0, $res['exitCode'], 'Failed with stderr: ' . $res['stderr']);
        $this->assertStringContainsString('Admin user successfully created', $res['stdout']);

        $stmt = $this->pdo->prepare('SELECT * FROM admin_users WHERE email = :email');
        $stmt->execute([':email' => 'shortopts@oceanviewflats.com']);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($user);
        $this->assertSame('Short Options User', $user['name']);
        $this->assertSame('admin', $user['role']);
    }

    public function testResolvePdoReturnsInjectedPdoDirectly(): void
    {
        $resolved = AdminUserProvisioner::resolvePdo($this->pdo);
        $this->assertSame($this->pdo, $resolved);
    }

    public function testResolvePdoThrowsExceptionWhenConfigMissing(): void
    {
        $emptyDir = sys_get_temp_dir() . '/ovf_empty_' . uniqid();
        mkdir($emptyDir, 0777, true);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Configuration file not found');
            AdminUserProvisioner::resolvePdo(null, $emptyDir);
        } finally {
            @rmdir($emptyDir);
        }
    }

    public function testResolvePdoPropagatesDbConnectionExceptionWithoutSwallowing(): void
    {
        $tempDir = sys_get_temp_dir() . '/ovf_bad_db_' . uniqid();
        mkdir($tempDir . '/public/api', 0777, true);

        try {
            file_put_contents(
                $tempDir . '/public/api/config.php',
                '<?php return ["db" => ["host" => "127.0.0.1", "dbname" => "nonexistent_db_12345", "user" => "invalid_user", "pass" => "invalid_pass"]];'
            );

            $this->expectException(Throwable::class);
            // Must NOT throw "Configuration file not found"
            AdminUserProvisioner::resolvePdo(null, $tempDir);
        } finally {
            @unlink($tempDir . '/public/api/config.php');
            @rmdir($tempDir . '/public/api');
            @rmdir($tempDir);
        }
    }

    public function testSubprocessFailsWhenConfigurationFileNotFound(): void
    {
        $emptyDir = sys_get_temp_dir() . '/ovf_no_config_' . uniqid();
        mkdir($emptyDir, 0777, true);

        try {
            $res = $this->executeSubprocess(
                ['--email=test@test.com', '--name=Test', '--password=password123!'],
                ['OVF_CONFIG_ROOT' => $emptyDir]
            );

            $this->assertSame(1, $res['exitCode']);
            $this->assertStringContainsString('Configuration file not found', $res['stderr']);
        } finally {
            @rmdir($emptyDir);
        }
    }

    public function testSubprocessReportsDatabaseConnectionFailureAccurately(): void
    {
        $tempDir = sys_get_temp_dir() . '/ovf_subprocess_db_' . uniqid();
        mkdir($tempDir . '/public/api', 0777, true);

        try {
            file_put_contents(
                $tempDir . '/public/api/config.php',
                '<?php return ["db" => ["host" => "127.0.0.1", "dbname" => "nonexistent_db_9999", "user" => "invalid_user", "pass" => "invalid_pass"]];'
            );

            $res = $this->executeSubprocess(
                ['--email=test@test.com', '--name=Test', '--password=password123!'],
                ['OVF_CONFIG_ROOT' => $tempDir]
            );

            $this->assertSame(1, $res['exitCode']);
            // Verify actual DB error message is reported and NOT "Configuration file not found"
            $this->assertStringNotContainsString('Configuration file not found', $res['stderr']);
            $this->assertStringContainsString('Error: ', $res['stderr']);
        } finally {
            @unlink($tempDir . '/public/api/config.php');
            @rmdir($tempDir . '/public/api');
            @rmdir($tempDir);
        }
    }
}
