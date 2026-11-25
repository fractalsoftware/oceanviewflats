<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Integration\Api;

use OceanViewFlats\Domain\Security\CaptchaChallengeService;
use PHPUnit\Framework\TestCase;

final class ContactProcessorTest extends TestCase
{
    private const TEST_CAPTCHA_SECRET = 'test-contact-captcha-secret-2026';

    private string $nonceStorePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nonceStorePath = sys_get_temp_dir() . '/ovf_test_contact_nonces_' . bin2hex(random_bytes(6)) . '/store.json';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach (['ovf_rate_limits.json', 'ovf_booking_rate_limits.json'] as $file) {
            $path = sys_get_temp_dir() . '/' . $file;
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        if (file_exists($this->nonceStorePath)) {
            @unlink($this->nonceStorePath);
        }
        $nonceDir = dirname($this->nonceStorePath);
        if (is_dir($nonceDir)) {
            @rmdir($nonceDir);
        }
    }

    public function testContactProcessorRejectsMissingPrivacyConsentWith400(): void
    {
        $res = $this->callContactProcessor([
            'name' => 'Ana Maria Lopez',
            'email' => 'ana@example.com',
            'phone_country_code' => '+57',
            'phone_number' => '3001234567',
            'message' => 'Hello, I would like to inquire about availability in November.',
            'lang' => 'en',
        ]);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(400, $res['httpCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('personal data processing policy', strtolower((string) ($res['json']['error'] ?? '')));
        $this->assertNull($res['mail']);
    }

    public function testContactProcessorRejectsFalsePrivacyConsentInSpanishWith400(): void
    {
        $res = $this->callContactProcessor([
            'name' => 'Ana Maria Lopez',
            'email' => 'ana@example.com',
            'phone_country_code' => '+57',
            'phone_number' => '3001234567',
            'message' => 'Hola, quisiera consultar disponibilidad para el mes de noviembre.',
            'privacy_consent' => '0',
            'lang' => 'es',
        ]);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(400, $res['httpCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('política de tratamiento de datos personales', mb_strtolower((string) ($res['json']['error'] ?? '')));
        $this->assertNull($res['mail']);
    }

    public function testContactProcessorAcceptsConsentedSubmissionAndAppendsConsentProofToEmail(): void
    {
        $res = $this->callContactProcessor([
            'name' => 'Ana Maria Lopez',
            'email' => 'ana@example.com',
            'phone_country_code' => '+57',
            'phone_number' => '3001234567',
            'check_in' => '2026-11-10',
            'check_out' => '2026-11-15',
            'message' => 'Hello, I would like to inquire about availability in November.',
            'privacy_consent' => '1',
            'privacy_policy_version' => '2026-10',
            'lang' => 'en',
        ], [
            'REMOTE_ADDR' => '181.55.102.44',
        ]);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(200, $res['httpCode']);
        $this->assertIsArray($res['json']);
        $this->assertTrue($res['json']['success']);
        $this->assertNotNull($res['mail']);
        $emailBody = (string) ($res['mail']['body'] ?? '');
        $this->assertStringContainsString('Privacy Policy Version: 2026-10', $emailBody);
        $this->assertStringContainsString('Privacy Consent At:', $emailBody);
        $this->assertStringContainsString('Privacy Consent IP:     181.55.102.44', $emailBody);
    }

    public function testContactProcessorRoutesToMaskedLogEmailSenderInNonProductionEnvironment(): void
    {
        $res = $this->callContactProcessorWithRealDispatcher(
            [
                'name' => 'Ana Maria Lopez',
                'email' => 'ana@example.com',
                'phone_country_code' => '+57',
                'phone_number' => '3001234567',
                'message' => 'Hello, I would like to inquire about availability in November.',
                'privacy_consent' => '1',
                'lang' => 'en',
            ],
            appEnv: 'local',
            recipientEmail: 'reservations@example.com'
        );

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(200, $res['httpCode'], $res['stdout'] . "\n" . $res['stderr']);
        $this->assertIsArray($res['json'], $res['stdout'] . "\n" . $res['stderr']);
        $this->assertTrue($res['json']['success']);
        $this->assertNull($res['phpMail']);
        $this->assertStringContainsString('[Local Mock Email] To: r****@example.com, Subject:', $res['errorLog']);
        $this->assertStringNotContainsString('reservations@example.com', $res['errorLog']);
    }

    public function testContactProcessorRoutesToPhpMailSenderInProductionPreservingPlainTextAndReplyTo(): void
    {
        $res = $this->callContactProcessorWithRealDispatcher(
            [
                'name' => 'Ana Maria%0ALopez',
                'email' => 'ana@example.com',
                'phone_country_code' => '+57',
                'phone_number' => '3001234567',
                'message' => 'Hello, I would like to inquire about availability in November.',
                'privacy_consent' => '1',
                'lang' => 'en',
            ],
            appEnv: 'production',
            recipientEmail: 'reservations@example.com'
        );

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(200, $res['httpCode'], $res['stdout'] . "\n" . $res['stderr']);
        $this->assertIsArray($res['json'], $res['stdout'] . "\n" . $res['stderr']);
        $this->assertTrue($res['json']['success']);
        $this->assertNotNull($res['phpMail'], 'Expected contact-processor.php to dispatch via PhpMailSender in production.');
        $this->assertSame('reservations@example.com', $res['phpMail']['to']);

        $headers = (string) ($res['phpMail']['headers'] ?? '');
        $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $headers);
        $this->assertStringContainsString('From: no-reply@oceanviewflats.com', $headers);
        $this->assertStringContainsString('Reply-To: Ana MariaLopez <ana@example.com>', $headers);
        $this->assertStringNotContainsString('text/html', $headers);
        $this->assertStringNotContainsString('%0a', strtolower($headers));
    }

    public function testBookRequestEndpointRejectsMissingPrivacyConsentWith400(): void
    {
        $res = $this->callBookRequestEndpoint([
            'property_id' => '1707',
            'check_in' => '2026-11-10',
            'check_out' => '2026-11-14',
            'guest_name' => 'Carlos Rodriguez',
            'guest_email' => 'carlos@example.com',
            'guest_phone' => '+573001234567',
            'lang' => 'en',
        ]);

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(400, $res['httpCode']);
        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('personal data processing policy', strtolower((string) ($res['json']['error'] ?? '')));
    }

    /**
     * @param array<string, string> $postData
     * @param array<string, string> $serverVars
     * @return array{exitCode: int, httpCode: int, stdout: string, stderr: string, json: ?array<string, mixed>, mail: ?array<string, mixed>}
     */
    private function callContactProcessor(array $postData, array $serverVars = []): array
    {
        $this->clearRateLimitFile('ovf_rate_limits.json');
        $mailCaptureFile = tempnam(sys_get_temp_dir(), 'ovf_mail_');
        $statusCaptureFile = tempnam(sys_get_temp_dir(), 'ovf_status_');

        $mergedPost = $this->withValidCaptcha($postData);
        $mergedServer = array_merge($this->defaultContactServerVars(), $serverVars);

        $callbackPreamble = sprintf(
            '$GLOBALS["__ovf_contact_mail_callback"] = function ($to, $subject, $body, $headers) { '
            . 'file_put_contents(%s, json_encode(["to" => $to, "subject" => $subject, "body" => $body, "headers" => $headers])); '
            . 'return true; }; ',
            var_export($mailCaptureFile, true)
        );
        $phpCode = $this->buildContactBootstrapPhp($mergedServer, $mergedPost, $statusCaptureFile, $callbackPreamble);

        $result = $this->runPhpSubprocess(['php', '-r', $phpCode], $statusCaptureFile);
        $mail = $this->readAndRemoveJsonFile($mailCaptureFile);

        return array_merge($result, ['mail' => $mail]);
    }

    /**
     * Invokes contact-processor.php without setting $__ovf_contact_mail_callback so
     * production PhpMailSender vs non-production MaskedLogEmailSender dispatch is exercised.
     *
     * @param array<string, string> $postData
     * @return array{exitCode: int, httpCode: int, stdout: string, stderr: string, json: ?array<string, mixed>, phpMail: ?array<string, mixed>, errorLog: string}
     */
    private function callContactProcessorWithRealDispatcher(
        array $postData,
        string $appEnv,
        string $recipientEmail
    ): array {
        $this->clearRateLimitFile('ovf_rate_limits.json');
        $phpMailCaptureFile = tempnam(sys_get_temp_dir(), 'ovf_phpmail_');
        $statusCaptureFile = tempnam(sys_get_temp_dir(), 'ovf_status_');
        $errorLogFile = tempnam(sys_get_temp_dir(), 'ovf_errlog_');

        $mergedPost = $this->withValidCaptcha($postData);
        $mergedServer = array_merge($this->defaultContactServerVars(), [
            'APP_ENV' => $appEnv,
            'RECIPIENT_EMAIL' => $recipientEmail,
        ]);

        $envPreamble = sprintf(
            'ini_set("error_log", %s); $_ENV["APP_ENV"] = %s; $_ENV["RECIPIENT_EMAIL"] = %s; ',
            var_export($errorLogFile, true),
            var_export($appEnv, true),
            var_export($recipientEmail, true)
        );
        $bootstrap = $this->buildContactBootstrapPhp($mergedServer, $mergedPost, $statusCaptureFile, $envPreamble);

        $phpCode = sprintf(
            'namespace OceanViewFlats\\Domain\\Fulfillment { '
            . 'function mail(string $to, string $subject, string $message, array|string $additional_headers = [], string $additional_params = ""): bool { '
            . 'file_put_contents(%s, json_encode(["to" => $to, "subject" => $subject, "body" => $message, "headers" => $additional_headers])); '
            . 'return true; } } '
            . 'namespace { %s }',
            var_export($phpMailCaptureFile, true),
            $bootstrap
        );

        $result = $this->runPhpSubprocess(['php', '-d', 'sendmail_path=/usr/bin/false', '-r', $phpCode], $statusCaptureFile);
        $phpMail = $this->readAndRemoveJsonFile($phpMailCaptureFile);

        $errorLog = '';
        if ($errorLogFile !== false && file_exists($errorLogFile)) {
            $errorLog = (string) file_get_contents($errorLogFile);
            @unlink($errorLogFile);
        }

        return array_merge($result, [
            'phpMail' => $phpMail,
            'errorLog' => $errorLog,
        ]);
    }

    /**
     * @param array<string, string> $mergedServer
     * @param array<string, string> $mergedPost
     */
    private function buildContactBootstrapPhp(
        array $mergedServer,
        array $mergedPost,
        string|false $statusCaptureFile,
        string $preamble = ''
    ): string {
        return sprintf(
            '%s$_ENV["CAPTCHA_SECRET"] = %s; $_SERVER["CAPTCHA_SECRET"] = %s; $_ENV["CAPTCHA_NONCE_STORE_PATH"] = %s; '
            . '$_SERVER = array_merge($_SERVER, %s); $_POST = %s; '
            . 'register_shutdown_function(function () { file_put_contents(%s, (string) (http_response_code() ?: 200)); }); '
            . 'require %s;',
            $preamble,
            var_export(self::TEST_CAPTCHA_SECRET, true),
            var_export(self::TEST_CAPTCHA_SECRET, true),
            var_export($this->nonceStorePath, true),
            var_export($mergedServer, true),
            var_export($mergedPost, true),
            var_export($statusCaptureFile, true),
            var_export(dirname(__DIR__, 3) . '/public/api/contact-processor.php', true)
        );
    }

    private function clearRateLimitFile(string $filename): void
    {
        $path = sys_get_temp_dir() . '/' . $filename;
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    /**
     * @param array<string, string> $postData
     * @return array<string, string>
     */
    private function withValidCaptcha(array $postData): array
    {
        $generated = CaptchaChallengeService::generate(self::TEST_CAPTCHA_SECRET);
        [$a, $b] = array_map('intval', explode('+', $generated['challenge']));

        return array_merge([
            'captcha_challenge' => $generated['challenge'],
            'captcha_signature' => $generated['signature'],
            'captcha_response' => (string) ($a + $b),
        ], $postData);
    }

    /**
     * @return array<string, string>
     */
    private function defaultContactServerVars(): array
    {
        return [
            'REQUEST_METHOD' => 'POST',
            'HTTP_HOST' => 'oceanviewflats.com',
            'HTTP_REFERER' => 'https://oceanviewflats.com/contact/index.html',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'REMOTE_ADDR' => '127.0.0.1',
        ];
    }

    /**
     * @param list<string> $command
     * @return array{exitCode: int, httpCode: int, stdout: string, stderr: string, json: ?array<string, mixed>}
     */
    private function runPhpSubprocess(array $command, string|false $statusCaptureFile): array
    {
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        if (!is_resource($process)) {
            $this->fail('Failed to spawn php process for endpoint test.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $httpCode = 200;
        if ($statusCaptureFile !== false && file_exists($statusCaptureFile)) {
            $rawStatus = trim((string) file_get_contents($statusCaptureFile));
            if ($rawStatus !== '' && is_numeric($rawStatus)) {
                $httpCode = (int) $rawStatus;
            }
            @unlink($statusCaptureFile);
        }

        $decoded = json_decode((string) $stdout, true);

        return [
            'exitCode' => $exitCode,
            'httpCode' => $httpCode,
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
            'json' => is_array($decoded) ? $decoded : null,
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    private function readAndRemoveJsonFile(string|false $path): ?array
    {
        if ($path === false || !file_exists($path)) {
            return null;
        }

        $raw = trim((string) file_get_contents($path));
        @unlink($path);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, string> $postData
     * @return array{exitCode: int, httpCode: int, stdout: string, stderr: string, json: ?array<string, mixed>}
     */
    private function callBookRequestEndpoint(array $postData): array
    {
        $rateLimitFile = sys_get_temp_dir() . '/ovf_booking_rate_limits.json';
        if (file_exists($rateLimitFile)) {
            @unlink($rateLimitFile);
        }

        $statusCaptureFile = tempnam(sys_get_temp_dir(), 'ovf_book_status_');
        $generated = CaptchaChallengeService::generate(self::TEST_CAPTCHA_SECRET);
        [$a, $b] = array_map('intval', explode('+', $generated['challenge']));

        $mergedPost = array_merge([
            'captcha_challenge' => $generated['challenge'],
            'captcha_signature' => $generated['signature'],
            'captcha_response' => (string) ($a + $b),
        ], $postData);

        $mergedServer = [
            'REQUEST_METHOD' => 'POST',
            'HTTP_HOST' => 'oceanviewflats.com',
            'HTTP_REFERER' => 'https://oceanviewflats.com/Oceanview1707/index.html',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        $phpCode = sprintf(
            '$_ENV["CAPTCHA_SECRET"] = %s; $_SERVER["CAPTCHA_SECRET"] = %s; $_ENV["CAPTCHA_NONCE_STORE_PATH"] = %s; '
            . '$_SERVER = array_merge($_SERVER, %s); $_POST = %s; '
            . 'register_shutdown_function(function () { file_put_contents(%s, (string) (http_response_code() ?: 200)); }); '
            . 'require %s;',
            var_export(self::TEST_CAPTCHA_SECRET, true),
            var_export(self::TEST_CAPTCHA_SECRET, true),
            var_export($this->nonceStorePath, true),
            var_export($mergedServer, true),
            var_export($mergedPost, true),
            var_export($statusCaptureFile, true),
            var_export(dirname(__DIR__, 3) . '/public/api/book-request.php', true)
        );

        $process = proc_open(
            ['php', '-r', $phpCode],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        if (!is_resource($process)) {
            $this->fail('Failed to spawn php process for book-request endpoint test.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $httpCode = 200;
        if ($statusCaptureFile !== false && file_exists($statusCaptureFile)) {
            $rawStatus = trim((string) file_get_contents($statusCaptureFile));
            if ($rawStatus !== '' && is_numeric($rawStatus)) {
                $httpCode = (int) $rawStatus;
            }
            @unlink($statusCaptureFile);
        }

        return [
            'exitCode' => $exitCode,
            'httpCode' => $httpCode,
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
            'json' => json_decode((string) $stdout, true),
        ];
    }
}
