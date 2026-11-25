<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Integration\Api;

use OceanViewFlats\Domain\Security\CaptchaChallengeService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integration security tests for issue #272 across all four CAPTCHA-protected public API endpoints:
 * - public/api/book-request.php
 * - public/api/contact-processor.php
 * - public/api/registry-processor.php
 * - public/api/payment.php
 *
 * Verifies:
 * 1. Expired challenges are rejected with HTTP 400 across all four endpoints.
 * 2. Replayed challenges (same-endpoint and cross-endpoint) are rejected with HTTP 400 across all four endpoints.
 * 3. Legacy stateless v1 HMAC signatures are rejected across all four endpoints.
 * 4. Missing CAPTCHA_SECRET fails closed with HTTP 500 outside explicit local/test environments (no 'securesaltsecret' fallback).
 * 5. book-request.php -> payment.php single-use stay-bound payment continuation token works once and rejects replay/expiry/mismatch.
 */
final class CaptchaSecurityEndpointTest extends TestCase
{
    private const TEST_SECRET = 'integration-test-captcha-secret-2026!';

    private string $tempDir;
    private string $nonceStorePath;
    private string $emptyConfigRoot;
    private string $dbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/ovf_captcha_sec_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0700, true);
        $this->nonceStorePath = $this->tempDir . '/nonces/store.json';
        $this->emptyConfigRoot = $this->tempDir . '/empty_root';
        mkdir($this->emptyConfigRoot, 0700, true);
        $this->dbPath = $this->tempDir . '/test.sqlite';

        $this->initSqliteDatabase();
        $this->clearRateLimitFiles();
    }

    protected function tearDown(): void
    {
        $this->clearRateLimitFiles();
        $this->removeDirectoryRecursive($this->tempDir);
        parent::tearDown();
    }

    public function testContactProcessorRejectsExpiredAndReplayedChallenges(): void
    {
        // 1. Expired challenge must be rejected with HTTP 400
        $expired = $this->createExpiredCaptcha();
        $expiredRes = $this->invokeContactPost($expired);
        $this->assertSame(0, $expiredRes['exitCode'], $expiredRes['stderr']);
        $this->assertSame(400, $expiredRes['httpCode']);
        $this->assertIsArray($expiredRes['json']);
        $this->assertFalse($expiredRes['json']['success']);
        $this->assertStringContainsString('expired', strtolower((string) ($expiredRes['json']['error'] ?? '')));

        // 2. Fresh challenge fetched from GET ?action=captcha succeeds on first POST
        $issued = $this->fetchCaptchaChallenge('contact-processor.php');
        $solved = $this->solveIssuedCaptcha($issued);

        $firstRes = $this->invokeContactPost($solved);
        $this->assertSame(0, $firstRes['exitCode'], $firstRes['stderr']);
        $this->assertSame(200, $firstRes['httpCode'], $firstRes['stdout']);
        $this->assertIsArray($firstRes['json']);
        $this->assertTrue($firstRes['json']['success']);

        // 3. Replaying the exact same tuple must be rejected with HTTP 400
        $replayRes = $this->invokeContactPost($solved);
        $this->assertSame(0, $replayRes['exitCode'], $replayRes['stderr']);
        $this->assertSame(400, $replayRes['httpCode']);
        $this->assertIsArray($replayRes['json']);
        $this->assertFalse($replayRes['json']['success']);
        $this->assertStringContainsString('already been used', strtolower((string) ($replayRes['json']['error'] ?? '')));
    }

    public function testBookRequestRejectsExpiredAndReplayedChallenges(): void
    {
        // 1. Expired challenge must be rejected with HTTP 400
        $expired = $this->createExpiredCaptcha();
        $expiredRes = $this->invokeBookRequestPost($expired);
        $this->assertSame(0, $expiredRes['exitCode'], $expiredRes['stderr']);
        $this->assertSame(400, $expiredRes['httpCode']);
        $this->assertIsArray($expiredRes['json']);
        $this->assertFalse($expiredRes['json']['success']);
        $this->assertStringContainsString('expired', strtolower((string) ($expiredRes['json']['error'] ?? '')));

        // 2. Fresh challenge fetched from GET ?action=captcha succeeds on first POST and emits payment_token
        $issued = $this->fetchCaptchaChallenge('book-request.php');
        $solved = $this->solveIssuedCaptcha($issued);

        $firstRes = $this->invokeBookRequestPost($solved);
        $this->assertSame(0, $firstRes['exitCode'], $firstRes['stderr']);
        $this->assertSame(200, $firstRes['httpCode'], $firstRes['stdout']);
        $this->assertIsArray($firstRes['json']);
        $this->assertTrue($firstRes['json']['success'], (string) ($firstRes['json']['error'] ?? ''));
        $this->assertNotEmpty($firstRes['json']['payment_token'] ?? '');

        // 3. Replaying the exact same CAPTCHA tuple must be rejected with HTTP 400
        $replayRes = $this->invokeBookRequestPost($solved, [
            'check_in' => '2027-12-10',
            'check_out' => '2027-12-14',
        ]);
        $this->assertSame(0, $replayRes['exitCode'], $replayRes['stderr']);
        $this->assertSame(400, $replayRes['httpCode']);
        $this->assertIsArray($replayRes['json']);
        $this->assertFalse($replayRes['json']['success']);
        $this->assertStringContainsString('already been used', strtolower((string) ($replayRes['json']['error'] ?? '')));
    }

    public function testRegistryProcessorRejectsExpiredAndReplayedChallenges(): void
    {
        // 1. Expired challenge must be rejected with HTTP 400
        $expired = $this->createExpiredCaptcha();
        $expiredRes = $this->invokeRegistryPost($expired);
        $this->assertSame(0, $expiredRes['exitCode'], $expiredRes['stderr']);
        $this->assertSame(400, $expiredRes['httpCode']);
        $this->assertIsArray($expiredRes['json']);
        $this->assertFalse($expiredRes['json']['success']);
        $this->assertStringContainsString('expired', strtolower((string) ($expiredRes['json']['error'] ?? '')));

        // 2. Fresh challenge succeeds on first POST
        $issued = $this->fetchCaptchaChallenge('contact-processor.php');
        $solved = $this->solveIssuedCaptcha($issued);

        $firstRes = $this->invokeRegistryPost($solved);
        $this->assertSame(0, $firstRes['exitCode'], $firstRes['stderr']);
        $this->assertSame(200, $firstRes['httpCode'], $firstRes['stdout']);
        $this->assertIsArray($firstRes['json']);
        $this->assertTrue($firstRes['json']['success'], (string) ($firstRes['json']['error'] ?? ''));

        // 3. Replaying the exact same CAPTCHA tuple must be rejected with HTTP 400
        $replayRes = $this->invokeRegistryPost($solved);
        $this->assertSame(0, $replayRes['exitCode'], $replayRes['stderr']);
        $this->assertSame(400, $replayRes['httpCode']);
        $this->assertIsArray($replayRes['json']);
        $this->assertFalse($replayRes['json']['success']);
        $this->assertStringContainsString('already been used', strtolower((string) ($replayRes['json']['error'] ?? '')));
    }

    public function testPaymentEndpointRejectsExpiredAndReplayedChallenges(): void
    {
        // 1. Expired challenge must be rejected with HTTP 400
        $expired = $this->createExpiredCaptcha();
        $expiredRes = $this->invokePaymentPost($expired);
        $this->assertSame(0, $expiredRes['exitCode'], $expiredRes['stderr']);
        $this->assertSame(400, $expiredRes['httpCode']);
        $this->assertIsArray($expiredRes['json']);
        $this->assertFalse($expiredRes['json']['success']);
        $this->assertStringContainsString('expired', strtolower((string) ($expiredRes['json']['error'] ?? '')));

        // 2. Fresh challenge succeeds on first POST
        $issued = $this->fetchCaptchaChallenge('book-request.php');
        $solved = $this->solveIssuedCaptcha($issued);

        $firstRes = $this->invokePaymentPost($solved, [
            'check_in' => '2027-11-20',
            'check_out' => '2027-11-24',
        ]);
        $this->assertSame(0, $firstRes['exitCode'], $firstRes['stderr']);
        $this->assertSame(200, $firstRes['httpCode'], $firstRes['stdout']);
        $this->assertIsArray($firstRes['json']);
        $this->assertTrue($firstRes['json']['success'], (string) ($firstRes['json']['error'] ?? ''));

        // 3. Replaying the exact same CAPTCHA tuple must be rejected with HTTP 400
        $replayRes = $this->invokePaymentPost($solved, [
            'check_in' => '2027-12-01',
            'check_out' => '2027-12-05',
        ]);
        $this->assertSame(0, $replayRes['exitCode'], $replayRes['stderr']);
        $this->assertSame(400, $replayRes['httpCode']);
        $this->assertIsArray($replayRes['json']);
        $this->assertFalse($replayRes['json']['success']);
        $this->assertStringContainsString('already been used', strtolower((string) ($replayRes['json']['error'] ?? '')));
    }

    public function testCrossEndpointReplayAndLegacyStatelessSignaturesAreRejected(): void
    {
        // Consume a challenge on contact-processor.php
        $issued = $this->fetchCaptchaChallenge('contact-processor.php');
        $solved = $this->solveIssuedCaptcha($issued);
        $contactOk = $this->invokeContactPost($solved);
        $this->assertSame(200, $contactOk['httpCode']);
        $this->assertTrue($contactOk['json']['success'] ?? false);

        // Attempting to replay that consumed challenge on the other 3 endpoints must fail with HTTP 400
        $bookReplay = $this->invokeBookRequestPost($solved);
        $this->assertSame(400, $bookReplay['httpCode']);
        $this->assertFalse($bookReplay['json']['success'] ?? true);

        $registryReplay = $this->invokeRegistryPost($solved);
        $this->assertSame(400, $registryReplay['httpCode']);
        $this->assertFalse($registryReplay['json']['success'] ?? true);

        $paymentReplay = $this->invokePaymentPost($solved);
        $this->assertSame(400, $paymentReplay['httpCode']);
        $this->assertFalse($paymentReplay['json']['success'] ?? true);

        // Legacy stateless v1 signature (plain HMAC over "$x + $y") must be rejected on all 4 endpoints
        $legacyV1 = [
            'captcha_challenge' => '4 + 5',
            'captcha_signature' => hash_hmac('sha256', '4 + 5', self::TEST_SECRET),
            'captcha_response' => '9',
        ];

        foreach ([
            'contact' => $this->invokeContactPost($legacyV1),
            'book-request' => $this->invokeBookRequestPost($legacyV1),
            'registry' => $this->invokeRegistryPost($legacyV1),
            'payment' => $this->invokePaymentPost($legacyV1),
        ] as $name => $res) {
            $this->assertSame(400, $res['httpCode'], "Legacy v1 signature must be rejected on {$name}");
            $this->assertFalse($res['json']['success'] ?? true, "Legacy v1 signature must return success=false on {$name}");
        }
    }

    public function testAllFourEndpointsFailClosedWithoutCaptchaSecretInProduction(): void
    {
        // Forge a token signed with the removed legacy fallback 'securesaltsecret'
        $forgedLegacySecret = CaptchaChallengeService::generate('securesaltsecret');
        $solvedForged = $this->solveIssuedCaptcha($forgedLegacySecret);

        // GET ?action=captcha on book-request.php and contact-processor.php must fail closed (HTTP 500)
        foreach (['book-request.php', 'contact-processor.php'] as $script) {
            $getRes = $this->runEndpointSubprocess(
                script: $script,
                method: 'GET',
                getParams: ['action' => 'captcha'],
                captchaSecret: null,
                appEnv: 'production'
            );
            $this->assertSame(0, $getRes['exitCode'], $getRes['stderr']);
            $this->assertSame(500, $getRes['httpCode'], "GET {$script}?action=captcha must fail closed without CAPTCHA_SECRET in production");
            $this->assertFalse($getRes['json']['success'] ?? true);
        }

        // POST across all four endpoints must fail closed (HTTP 500) in production without CAPTCHA_SECRET
        $contactFail = $this->invokeContactPost($solvedForged, [], captchaSecret: null, appEnv: 'production');
        $this->assertSame(500, $contactFail['httpCode'], 'contact-processor.php must fail closed in production');
        $this->assertFalse($contactFail['json']['success'] ?? true);

        $bookFail = $this->invokeBookRequestPost($solvedForged, [], captchaSecret: null, appEnv: 'production');
        $this->assertSame(500, $bookFail['httpCode'], 'book-request.php must fail closed in production');
        $this->assertFalse($bookFail['json']['success'] ?? true);

        $registryFail = $this->runEndpointSubprocess(
            script: 'registry-processor.php',
            method: 'POST',
            jsonBody: array_merge([
                'reservation_code' => 'ovf_reg_test_100',
                'property' => '1606',
                'check_in' => '2027-11-15',
                'check_out' => '2027-11-20',
                'privacy_consent' => '1',
                'lang' => 'en',
            ], $solvedForged),
            captchaSecret: null,
            appEnv: 'production'
        );
        $this->assertSame(0, $registryFail['exitCode'], $registryFail['stderr']);
        $this->assertSame(500, $registryFail['httpCode'], 'registry-processor.php must fail closed in production');
        $this->assertFalse($registryFail['json']['success'] ?? true);

        $paymentFail = $this->runEndpointSubprocess(
            script: 'payment.php',
            method: 'POST',
            jsonBody: array_merge([
                'property_id' => '1606',
                'check_in' => '2027-11-20',
                'check_out' => '2027-11-24',
                'privacy_consent' => true,
                'lang' => 'en',
            ], $solvedForged),
            captchaSecret: null,
            appEnv: 'production'
        );
        $this->assertSame(0, $paymentFail['exitCode'], $paymentFail['stderr']);
        $this->assertSame(500, $paymentFail['httpCode'], 'payment.php must fail closed in production');
        $this->assertFalse($paymentFail['json']['success'] ?? true);
    }

    public function testBookRequestToPaymentTokenHandoffIsSingleUseExpiringAndStayBound(): void
    {
        // Step 1: Complete book-request.php with a fresh math CAPTCHA
        $issued = $this->fetchCaptchaChallenge('book-request.php');
        $solved = $this->solveIssuedCaptcha($issued);

        $bookRes = $this->invokeBookRequestPost($solved, [
            'property_id' => '1606',
            'check_in' => '2027-12-10',
            'check_out' => '2027-12-14',
        ]);
        $this->assertSame(200, $bookRes['httpCode'], $bookRes['stdout']);
        $this->assertIsArray($bookRes['json']);
        $this->assertTrue($bookRes['json']['success'] ?? false);

        $reservationUid = (string) ($bookRes['json']['reservation_uid'] ?? '');
        $paymentToken = (string) ($bookRes['json']['payment_token'] ?? '');
        $this->assertNotEmpty($reservationUid);
        $this->assertMatchesRegularExpression('/^p1\.\d+\.[a-f0-9]{32}\.[a-f0-9]{64}$/', $paymentToken);

        // Step 2: Replaying the consumed math CAPTCHA directly to payment.php (without payment_token) fails with 400
        $replayMathRes = $this->invokePaymentPost($solved, [
            'reservation_uid' => $reservationUid,
            'property_id' => '1606',
            'check_in' => '2027-12-10',
            'check_out' => '2027-12-14',
        ]);
        $this->assertSame(400, $replayMathRes['httpCode']);
        $this->assertFalse($replayMathRes['json']['success'] ?? true);

        // Step 3: Using payment_token for a different property fails with 400 (stay-bound)
        $mismatchRes = $this->invokePaymentPost([], [
            'reservation_uid' => $reservationUid,
            'payment_token' => $paymentToken,
            'property_id' => '1707',
            'check_in' => '2027-12-10',
            'check_out' => '2027-12-14',
        ]);
        $this->assertSame(400, $mismatchRes['httpCode']);
        $this->assertFalse($mismatchRes['json']['success'] ?? true);

        // Step 4: Using an expired payment_token fails with 400
        $expiredPaymentToken = CaptchaChallengeService::signPaymentToken(
            $reservationUid,
            '1606',
            '2027-12-10',
            '2027-12-14',
            time() - 30,
            bin2hex(random_bytes(16)),
            self::TEST_SECRET
        );
        $expiredTokenRes = $this->invokePaymentPost([], [
            'reservation_uid' => $reservationUid,
            'payment_token' => $expiredPaymentToken,
            'property_id' => '1606',
            'check_in' => '2027-12-10',
            'check_out' => '2027-12-14',
        ]);
        $this->assertSame(400, $expiredTokenRes['httpCode']);
        $this->assertFalse($expiredTokenRes['json']['success'] ?? true);

        // Step 5: Valid payment_token succeeds once (HTTP 200)
        // Clear pending hold first so InMemoryPaymentGateway checkout in payment.php can acquire the clean dates
        $pdo = new PDO('sqlite:' . $this->dbPath);
        $pdo->exec("DELETE FROM reservations WHERE reservation_uid = " . $pdo->quote($reservationUid));

        $payOk = $this->invokePaymentPost([], [
            'reservation_uid' => $reservationUid,
            'payment_token' => $paymentToken,
            'property_id' => '1606',
            'check_in' => '2027-12-10',
            'check_out' => '2027-12-14',
        ]);
        $this->assertSame(200, $payOk['httpCode'], $payOk['stdout']);
        $this->assertTrue($payOk['json']['success'] ?? false);

        // Step 6: Replaying the same payment_token a second time must fail with 400 and never mint a replacement token
        $payReplay = $this->invokePaymentPost([], [
            'reservation_uid' => $reservationUid,
            'payment_token' => $paymentToken,
            'property_id' => '1606',
            'check_in' => '2027-12-10',
            'check_out' => '2027-12-14',
        ]);
        $this->assertSame(400, $payReplay['httpCode']);
        $this->assertIsArray($payReplay['json']);
        $this->assertFalse($payReplay['json']['success'] ?? true);
        $this->assertArrayNotHasKey('payment_token', $payReplay['json']);
        $this->assertStringContainsString('already been used', strtolower((string) ($payReplay['json']['error'] ?? '')));

        // Step 7: Failed payment using a valid payment_token must NOT self-renew a new payment_token
        $singleUseTokenForFailure = CaptchaChallengeService::issuePaymentToken(
            $reservationUid,
            '1606',
            '2027-12-20',
            '2027-12-24',
            self::TEST_SECRET
        );
        $failedPaymentRes = $this->invokePaymentPost([], [
            'reservation_uid' => $reservationUid,
            'payment_token' => $singleUseTokenForFailure,
            'property_id' => '1606',
            'check_in' => '2027-12-20',
            'check_out' => '2027-12-24',
            'guest_email' => 'not-an-email',
        ]);
        $this->assertSame(400, $failedPaymentRes['httpCode']);
        $this->assertIsArray($failedPaymentRes['json']);
        $this->assertFalse($failedPaymentRes['json']['success'] ?? true);
        $this->assertArrayNotHasKey('payment_token', $failedPaymentRes['json']);
    }

    /**
     * @return array{challenge: string, signature: string, expires_at: int}
     */
    private function fetchCaptchaChallenge(string $script): array
    {
        $res = $this->runEndpointSubprocess(
            script: $script,
            method: 'GET',
            getParams: ['action' => 'captcha']
        );

        $this->assertSame(0, $res['exitCode'], $res['stderr']);
        $this->assertSame(200, $res['httpCode'], $res['stdout']);
        $this->assertIsArray($res['json']);
        $this->assertIsString($res['json']['challenge'] ?? null);
        $this->assertIsString($res['json']['signature'] ?? null);
        $this->assertIsInt($res['json']['expires_at'] ?? null);

        return [
            'challenge' => $res['json']['challenge'],
            'signature' => $res['json']['signature'],
            'expires_at' => $res['json']['expires_at'],
        ];
    }

    /**
     * @param array{challenge: string, signature: string, expires_at: int} $issued
     * @return array{captcha_challenge: string, captcha_signature: string, captcha_response: string}
     */
    private function solveIssuedCaptcha(array $issued): array
    {
        [$a, $b] = array_map('intval', explode('+', $issued['challenge']));

        return [
            'captcha_challenge' => $issued['challenge'],
            'captcha_signature' => $issued['signature'],
            'captcha_response' => (string) ($a + $b),
        ];
    }

    /**
     * @return array{captcha_challenge: string, captcha_signature: string, captcha_response: string}
     */
    private function createExpiredCaptcha(string $secret = self::TEST_SECRET): array
    {
        $challenge = '6 + 3';
        $expiredAt = time() - 120;
        $nonce = bin2hex(random_bytes(16));

        return [
            'captcha_challenge' => $challenge,
            'captcha_signature' => CaptchaChallengeService::signChallenge($challenge, $expiredAt, $nonce, $secret),
            'captcha_response' => '9',
        ];
    }

    /**
     * @param array<string, string> $captchaFields
     * @param array<string, string> $overrides
     * @return array{exitCode: int, httpCode: int, stdout: string, stderr: string, json: ?array<string, mixed>}
     */
    private function invokeContactPost(
        array $captchaFields,
        array $overrides = [],
        ?string $captchaSecret = self::TEST_SECRET,
        string $appEnv = 'testing'
    ): array {
        $this->clearRateLimitFiles();
        $post = array_merge([
            'name' => 'Ana Maria Lopez',
            'email' => 'ana@example.com',
            'phone_country_code' => '+57',
            'phone_number' => '3001234567',
            'message' => 'Hello, checking availability for next month at OceanViewFlats.',
            'privacy_consent' => '1',
            'privacy_policy_version' => '2026-10',
            'lang' => 'en',
        ], $captchaFields, $overrides);

        $prepend = '$GLOBALS["__ovf_contact_mail_callback"] = static function (): bool { return true; };';

        return $this->runEndpointSubprocess(
            script: 'contact-processor.php',
            method: 'POST',
            postParams: $post,
            prependCode: $prepend,
            captchaSecret: $captchaSecret,
            appEnv: $appEnv
        );
    }

    /**
     * @param array<string, string> $captchaFields
     * @param array<string, string> $overrides
     * @return array{exitCode: int, httpCode: int, stdout: string, stderr: string, json: ?array<string, mixed>}
     */
    private function invokeBookRequestPost(
        array $captchaFields,
        array $overrides = [],
        ?string $captchaSecret = self::TEST_SECRET,
        string $appEnv = 'testing'
    ): array {
        $this->clearRateLimitFiles();
        $cacheDir = $this->tempDir . '/book_cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0700, true);
        }

        $post = array_merge([
            'property_id' => '1606',
            'check_in' => '2027-11-10',
            'check_out' => '2027-11-14',
            'guest_name' => 'Carlos Rodriguez',
            'guest_email' => 'carlos@example.com',
            'guest_phone' => '+573001234567',
            'privacy_consent' => '1',
            'privacy_policy_version' => '2026-10',
            'lang' => 'en',
        ], $captchaFields, $overrides);

        $autoloadPath = var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true);
        $dbPathExport = var_export($this->dbPath, true);
        $cacheDirExport = var_export($cacheDir, true);
        $prepend = <<<PHP
require_once {$autoloadPath};
\$pdo = new PDO('sqlite:' . {$dbPathExport});
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
\$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
\$GLOBALS['TEST_PDO'] = \$pdo;
\$GLOBALS['TEST_CACHE_DIR'] = {$cacheDirExport};
\$GLOBALS['TEST_EMAIL_SENDER'] = new \\OceanViewFlats\\Domain\\Fulfillment\\InMemoryEmailSender();
\$GLOBALS['TEST_CHANNEL_SYNC_SERVICE'] = new \\OceanViewFlats\\Domain\\Reservation\\InboundChannelSyncService([], {$cacheDirExport});
PHP;

        return $this->runEndpointSubprocess(
            script: 'book-request.php',
            method: 'POST',
            postParams: $post,
            prependCode: $prepend,
            captchaSecret: $captchaSecret,
            appEnv: $appEnv
        );
    }

    /**
     * @param array<string, string> $captchaFields
     * @param array<string, mixed> $overrides
     * @return array{exitCode: int, httpCode: int, stdout: string, stderr: string, json: ?array<string, mixed>}
     */
    private function invokeRegistryPost(
        array $captchaFields,
        array $overrides = [],
        ?string $captchaSecret = self::TEST_SECRET,
        string $appEnv = 'testing'
    ): array {
        $this->clearRateLimitFiles();
        $body = array_merge([
            'reservation_code' => 'ovf_reg_test_100',
            'property' => '1606',
            'check_in' => '2027-11-15',
            'check_out' => '2027-11-20',
            'guest_count' => 1,
            'guest_email_1' => 'maria.gomez@example.com',
            'guest_name_1' => 'Maria Gomez',
            'guest_age_1' => 34,
            'guest_doc_type_1' => 'Passport',
            'guest_doc_num_1' => 'US12345678',
            'privacy_consent' => '1',
            'privacy_policy_version' => '2026-10',
            'lang' => 'en',
        ], $captchaFields, $overrides);

        $autoloadPath = var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true);
        $dbPathExport = var_export($this->dbPath, true);
        $prepend = <<<PHP
\$_ENV['APP_ENCRYPTION_KEY'] = 'test-captcha-sec-enc-key-32bytes';
\$_ENV['PROPERTY_1606_DOOR_CODE'] = '0111606#';
\$_ENV['PROPERTY_1606_WIFI_PASSWORD'] = 'TestWifi1606!';
\$_ENV['HUESPED_MANAGER_1606_CHECK'] = 'test-check-1606';
require_once {$autoloadPath};
\$pdo = new PDO('sqlite:' . {$dbPathExport});
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
\$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
\$GLOBALS['TEST_PDO'] = \$pdo;
\$GLOBALS['TEST_CLEARANCE_TRANSPORT'] = new class implements \\OceanViewFlats\\Domain\\Fulfillment\\HttpTransportInterface {
    public function post(string \$url, array|string \$data = [], array \$headers = [], array \$options = []): array {
        if (strpos(\$url, 'reg_guest_owner_pre.php') !== false) {
            return ['statusCode' => 200, 'body' => json_encode(['status' => true, 'last_id' => 100]), 'headers' => [], 'cookies' => ['PHPSESSID' => 'sess'], 'error' => null];
        }
        return ['statusCode' => 200, 'body' => 'OK', 'headers' => [], 'cookies' => [], 'error' => null];
    }
};
\$GLOBALS['TEST_LIFECYCLE_SERVICE'] = \\OceanViewFlats\\Domain\\Fulfillment\\GuestLifecycleFulfillmentService::createDefault(
    \$pdo,
    [
        'email_sender' => new \\OceanViewFlats\\Domain\\Fulfillment\\InMemoryEmailSender(),
        'clearance_sync' => null,
    ]
);
PHP;

        return $this->runEndpointSubprocess(
            script: 'registry-processor.php',
            method: 'POST',
            jsonBody: $body,
            prependCode: $prepend,
            captchaSecret: $captchaSecret,
            appEnv: $appEnv
        );
    }

    /**
     * @param array<string, string> $captchaFields
     * @param array<string, mixed> $overrides
     * @return array{exitCode: int, httpCode: int, stdout: string, stderr: string, json: ?array<string, mixed>}
     */
    private function invokePaymentPost(
        array $captchaFields,
        array $overrides = [],
        ?string $captchaSecret = self::TEST_SECRET,
        string $appEnv = 'testing'
    ): array {
        $this->clearRateLimitFiles();
        $body = array_merge([
            'property_id' => '1606',
            'check_in' => '2027-11-20',
            'check_out' => '2027-11-24',
            'guest_name' => 'Carlos Rodriguez',
            'guest_email' => 'carlos@example.com',
            'guest_phone' => '+573001234567',
            'payment_method_id' => 'visa',
            'token' => 'tok_test_card_123',
            'installments' => 1,
            'idempotency_key' => 'idem_' . bin2hex(random_bytes(6)),
            'privacy_consent' => true,
            'privacy_policy_version' => '2026-10',
            'lang' => 'en',
        ], $captchaFields, $overrides);

        $autoloadPath = var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true);
        $dbPathExport = var_export($this->dbPath, true);
        $prepend = <<<PHP
require_once {$autoloadPath};
\$pdo = new PDO('sqlite:' . {$dbPathExport});
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
\$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
\$GLOBALS['TEST_PDO'] = \$pdo;
\$gateway = new \\OceanViewFlats\\Domain\\Payment\\InMemoryPaymentGateway();
\$gateway->stageCreateResult(new \\OceanViewFlats\\Domain\\Payment\\PaymentGatewayResult(
    success: true,
    paymentId: 'pay_sec_' . bin2hex(random_bytes(4)),
    status: 'approved',
    statusDetail: 'accredited',
    paymentMethodId: 'visa',
    transactionAmount: 1610000.0,
    externalResourceUrl: null,
    barcode: null,
    verificationCode: null,
    rawResponse: ['status' => 'approved']
));
\$GLOBALS['TEST_PAYMENT_GATEWAY'] = \$gateway;
\$GLOBALS['TEST_EMAIL_SENDER'] = new \\OceanViewFlats\\Domain\\Fulfillment\\InMemoryEmailSender();
PHP;

        return $this->runEndpointSubprocess(
            script: 'payment.php',
            method: 'POST',
            jsonBody: $body,
            prependCode: $prepend,
            captchaSecret: $captchaSecret,
            appEnv: $appEnv
        );
    }

    /**
     * @param array<string, string> $getParams
     * @param array<string, string> $postParams
     * @param array<string, mixed>|null $jsonBody
     * @return array{exitCode: int, httpCode: int, stdout: string, stderr: string, json: ?array<string, mixed>}
     */
    private function runEndpointSubprocess(
        string $script,
        string $method,
        array $getParams = [],
        array $postParams = [],
        ?array $jsonBody = null,
        string $prependCode = '',
        ?string $captchaSecret = self::TEST_SECRET,
        string $appEnv = 'testing'
    ): array {
        $statusFile = tempnam($this->tempDir, 'status_');

        $server = [
            'REQUEST_METHOD' => $method,
            'HTTP_HOST' => 'oceanviewflats.com',
            'HTTP_REFERER' => 'https://oceanviewflats.com/Oceanview1606/index.html',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'REMOTE_ADDR' => '127.0.0.1',
        ];
        if ($jsonBody !== null) {
            $server['CONTENT_TYPE'] = 'application/json';
        }

        $secretSetup = $captchaSecret !== null
            ? sprintf(
                '$_ENV["CAPTCHA_SECRET"] = %1$s; $_SERVER["CAPTCHA_SECRET"] = %1$s; putenv("CAPTCHA_SECRET=" . %1$s);',
                var_export($captchaSecret, true)
            )
            : sprintf(
                'putenv("OVF_CONFIG_ROOT=" . %s); unset($_ENV["CAPTCHA_SECRET"], $_SERVER["CAPTCHA_SECRET"]); putenv("CAPTCHA_SECRET");',
                var_export($this->emptyConfigRoot, true)
            );

        $phpCode = sprintf(
            '$_ENV["APP_ENV"] = %1$s; $_SERVER["APP_ENV"] = %1$s; putenv("APP_ENV=" . %1$s); '
            . '$_ENV["CAPTCHA_NONCE_STORE_PATH"] = %2$s; $_SERVER["CAPTCHA_NONCE_STORE_PATH"] = %2$s; putenv("CAPTCHA_NONCE_STORE_PATH=" . %2$s); '
            . '%3$s '
            . '%4$s; '
            . '$_GET = %5$s; $_POST = %6$s; $_SERVER = array_merge($_SERVER, %7$s); '
            . 'register_shutdown_function(function () { file_put_contents(%8$s, (string) (http_response_code() ?: 200)); }); '
            . 'require %9$s;',
            var_export($appEnv, true),
            var_export($this->nonceStorePath, true),
            $secretSetup,
            $prependCode,
            var_export($getParams, true),
            var_export($postParams, true),
            var_export($server, true),
            var_export($statusFile, true),
            var_export(dirname(__DIR__, 3) . '/public/api/' . $script, true)
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
            $this->fail("Failed to spawn subprocess for {$script}.");
        }

        if ($jsonBody !== null) {
            fwrite($pipes[0], (string) json_encode($jsonBody));
        }
        fclose($pipes[0]);

        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $httpCode = 200;
        if ($statusFile !== false && file_exists($statusFile)) {
            $raw = trim((string) file_get_contents($statusFile));
            if ($raw !== '' && ctype_digit($raw)) {
                $httpCode = (int) $raw;
            }
            @unlink($statusFile);
        }

        $decoded = json_decode($stdout, true);

        return [
            'exitCode' => $exitCode,
            'httpCode' => $httpCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'json' => is_array($decoded) ? $decoded : null,
        ];
    }

    private function initSqliteDatabase(): void
    {
        $pdo = new PDO('sqlite:' . $this->dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("
            CREATE TABLE reservations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_uid TEXT UNIQUE,
                property_id TEXT,
                guest_name TEXT,
                guest_email TEXT,
                guest_phone TEXT,
                check_in TEXT,
                check_out TEXT,
                total_price REAL,
                refunded_amount REAL DEFAULT 0.0,
                status TEXT,
                payment_method_id TEXT,
                mercadopago_preference_id TEXT,
                mercadopago_payment_id TEXT,
                payment_status TEXT,
                payment_detail TEXT,
                notes TEXT,
                source TEXT DEFAULT 'web',
                external_confirmation_code TEXT DEFAULT NULL,
                channel_block_uid TEXT DEFAULT NULL,
                lang TEXT,
                registry_completed INTEGER DEFAULT 0,
                registry_completed_at TEXT,
                door_code TEXT,
                max_guests INTEGER NOT NULL DEFAULT 6,
                privacy_policy_version TEXT DEFAULT NULL,
                privacy_consent_at TEXT DEFAULT NULL,
                privacy_consent_ip TEXT DEFAULT NULL,
                pii_redacted_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE guest_registries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_uid TEXT,
                property_id TEXT,
                check_in TEXT,
                check_out TEXT,
                guest_count INTEGER,
                guests_payload TEXT,
                car_plates TEXT,
                car_model TEXT,
                lang TEXT,
                ip_address TEXT,
                privacy_policy_version TEXT DEFAULT NULL,
                privacy_consent_at TEXT DEFAULT NULL,
                privacy_consent_ip TEXT DEFAULT NULL,
                minor_guardian_consent INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE payment_idempotency (
                idempotency_key TEXT PRIMARY KEY,
                payment_id TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE calendar_blocks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                property_id TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                reason TEXT NOT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE property_rates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                property_id TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                season_name TEXT NOT NULL,
                price_per_night REAL NOT NULL,
                min_stay INTEGER NOT NULL DEFAULT 2,
                cleaning_fee REAL NOT NULL DEFAULT 0.0,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE admin_audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_user_id INTEGER,
                action TEXT,
                entity_type TEXT,
                entity_id TEXT,
                payload_before TEXT,
                payload_after TEXT,
                ip_address TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE condominium_clearances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_uid TEXT NOT NULL,
                status TEXT NOT NULL,
                external_consecutivo TEXT DEFAULT NULL,
                request_payload TEXT,
                response_body TEXT,
                error_message TEXT,
                attempts INTEGER DEFAULT 1,
                last_attempt_at TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            INSERT INTO reservations (
                reservation_uid, property_id, guest_name, guest_email, guest_phone,
                check_in, check_out, total_price, status, payment_status, registry_completed
            ) VALUES (
                'ovf_reg_test_100', '1606', 'Maria Gomez', 'maria.gomez@example.com', '+573001234567',
                '2027-11-15', '2027-11-20', 1750000.0, 'confirmed', 'approved', 0
            );
        ");
    }

    private function clearRateLimitFiles(): void
    {
        foreach ([
            'ovf_rate_limits.json',
            'ovf_booking_rate_limits.json',
            'ovf_registry_rate_limits.json',
            'ovf_payments_rate_limits.json',
        ] as $file) {
            $path = sys_get_temp_dir() . '/' . $file;
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    private function removeDirectoryRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $entries = scandir($dir);
        if (!is_array($entries)) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectoryRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
