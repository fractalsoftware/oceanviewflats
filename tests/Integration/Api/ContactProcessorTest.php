<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Integration\Api;

use PHPUnit\Framework\TestCase;

final class ContactProcessorTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        foreach (['ovf_rate_limits.json', 'ovf_booking_rate_limits.json'] as $file) {
            $path = sys_get_temp_dir() . '/' . $file;
            if (file_exists($path)) {
                @unlink($path);
            }
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
        $rateLimitFile = sys_get_temp_dir() . '/ovf_rate_limits.json';
        if (file_exists($rateLimitFile)) {
            @unlink($rateLimitFile);
        }

        $mailCaptureFile = tempnam(sys_get_temp_dir(), 'ovf_mail_');
        $statusCaptureFile = tempnam(sys_get_temp_dir(), 'ovf_status_');

        $secret = 'securesaltsecret';
        $challenge = '4 + 5';
        $signature = hash_hmac('sha256', $challenge, $secret);

        $mergedPost = array_merge([
            'captcha_challenge' => $challenge,
            'captcha_signature' => $signature,
            'captcha_response' => '9',
        ], $postData);

        $mergedServer = array_merge([
            'REQUEST_METHOD' => 'POST',
            'HTTP_HOST' => 'oceanviewflats.com',
            'HTTP_REFERER' => 'https://oceanviewflats.com/contact/index.html',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'REMOTE_ADDR' => '127.0.0.1',
        ], $serverVars);

        $phpCode = sprintf(
            '$_SERVER = array_merge($_SERVER, %s); $_POST = %s; '
            . '$GLOBALS["__ovf_contact_mail_callback"] = function ($to, $subject, $body, $headers) { '
            . 'file_put_contents(%s, json_encode(["to" => $to, "subject" => $subject, "body" => $body, "headers" => $headers])); '
            . 'return true; }; '
            . 'register_shutdown_function(function () { file_put_contents(%s, (string) (http_response_code() ?: 200)); }); '
            . 'require %s;',
            var_export($mergedServer, true),
            var_export($mergedPost, true),
            var_export($mailCaptureFile, true),
            var_export($statusCaptureFile, true),
            var_export(dirname(__DIR__, 3) . '/public/api/contact-processor.php', true)
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

        $mail = null;
        if ($mailCaptureFile !== false && file_exists($mailCaptureFile)) {
            $rawMail = trim((string) file_get_contents($mailCaptureFile));
            if ($rawMail !== '') {
                $mail = json_decode($rawMail, true);
            }
            @unlink($mailCaptureFile);
        }

        return [
            'exitCode' => $exitCode,
            'httpCode' => $httpCode,
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
            'json' => json_decode((string) $stdout, true),
            'mail' => is_array($mail) ? $mail : null,
        ];
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
        $secret = 'securesaltsecret';
        $challenge = '3 + 6';
        $signature = hash_hmac('sha256', $challenge, $secret);

        $mergedPost = array_merge([
            'captcha_challenge' => $challenge,
            'captcha_signature' => $signature,
            'captcha_response' => '9',
        ], $postData);

        $mergedServer = [
            'REQUEST_METHOD' => 'POST',
            'HTTP_HOST' => 'oceanviewflats.com',
            'HTTP_REFERER' => 'https://oceanviewflats.com/Oceanview1707/index.html',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        $phpCode = sprintf(
            '$_SERVER = array_merge($_SERVER, %s); $_POST = %s; '
            . 'register_shutdown_function(function () { file_put_contents(%s, (string) (http_response_code() ?: 200)); }); '
            . 'require %s;',
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
