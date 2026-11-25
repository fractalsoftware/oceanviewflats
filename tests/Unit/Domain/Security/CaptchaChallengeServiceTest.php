<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Security;

use OceanViewFlats\Domain\Security\CaptchaChallengeService;
use OceanViewFlats\Domain\Security\FileCaptchaNonceStore;
use OceanViewFlats\Domain\Security\InMemoryCaptchaNonceStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CaptchaChallengeServiceTest extends TestCase
{
    private const SECRET = 'unit-test-captcha-secret-key-2026';

    private ?string $originalAppEnvEnv = null;
    private ?string $originalAppEnvServer = null;
    private string|false $originalAppEnvGet = false;
    private ?string $originalSecretEnv = null;
    private ?string $originalSecretServer = null;
    private string|false $originalSecretGet = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalAppEnvEnv = isset($_ENV['APP_ENV']) && is_string($_ENV['APP_ENV']) ? $_ENV['APP_ENV'] : null;
        $this->originalAppEnvServer = isset($_SERVER['APP_ENV']) && is_string($_SERVER['APP_ENV']) ? $_SERVER['APP_ENV'] : null;
        $this->originalAppEnvGet = getenv('APP_ENV');
        $this->originalSecretEnv = isset($_ENV['CAPTCHA_SECRET']) && is_string($_ENV['CAPTCHA_SECRET']) ? $_ENV['CAPTCHA_SECRET'] : null;
        $this->originalSecretServer = isset($_SERVER['CAPTCHA_SECRET']) && is_string($_SERVER['CAPTCHA_SECRET']) ? $_SERVER['CAPTCHA_SECRET'] : null;
        $this->originalSecretGet = getenv('CAPTCHA_SECRET');
    }

    protected function tearDown(): void
    {
        $this->restoreEnvVar('APP_ENV', $this->originalAppEnvEnv, $this->originalAppEnvServer, $this->originalAppEnvGet);
        $this->restoreEnvVar('CAPTCHA_SECRET', $this->originalSecretEnv, $this->originalSecretServer, $this->originalSecretGet);
        parent::tearDown();
    }

    public function testGenerateProducesExpiringSignedV2TokenWithUniqueNonces(): void
    {
        $now = 1764000000;
        $first = CaptchaChallengeService::generate(self::SECRET, $now);
        $second = CaptchaChallengeService::generate(self::SECRET, $now);

        $this->assertMatchesRegularExpression('/^\d+ \+ \d+$/', $first['challenge']);
        $this->assertSame($now + CaptchaChallengeService::DEFAULT_TTL_SECONDS, $first['expires_at']);
        $this->assertMatchesRegularExpression('/^v2\.\d+\.[a-f0-9]{32}\.[a-f0-9]{64}$/', $first['signature']);
        $this->assertNotSame($first['signature'], $second['signature']);
    }

    public function testVerifyAcceptsFreshChallengeOnceAndRejectsReplay(): void
    {
        $store = new InMemoryCaptchaNonceStore();
        $now = 1764000000;
        $generated = CaptchaChallengeService::generate(self::SECRET, $now);
        $answer = $this->solveMath($generated['challenge']);

        $firstResult = CaptchaChallengeService::verify(
            challenge: $generated['challenge'],
            signature: $generated['signature'],
            response: $answer,
            secret: self::SECRET,
            nonceStore: $store,
            now: $now + 10,
            errorReplayed: 'REPLAYED'
        );
        $this->assertTrue($firstResult);
        $this->assertSame(1, $store->count());

        $replayResult = CaptchaChallengeService::verify(
            challenge: $generated['challenge'],
            signature: $generated['signature'],
            response: $answer,
            secret: self::SECRET,
            nonceStore: $store,
            now: $now + 11,
            errorReplayed: 'REPLAYED'
        );
        $this->assertSame('REPLAYED', $replayResult);
    }

    public function testVerifyEnforcesExpirationBoundary(): void
    {
        $store = new InMemoryCaptchaNonceStore();
        $now = 1764000000;

        $atBoundary = CaptchaChallengeService::generate(self::SECRET, $now, ttlSeconds: 600);
        $this->assertTrue(CaptchaChallengeService::verify(
            challenge: $atBoundary['challenge'],
            signature: $atBoundary['signature'],
            response: $this->solveMath($atBoundary['challenge']),
            secret: self::SECRET,
            nonceStore: $store,
            now: $now + 600,
            errorExpired: 'EXPIRED'
        ));

        $expired = CaptchaChallengeService::generate(self::SECRET, $now, ttlSeconds: 600);
        $expiredResult = CaptchaChallengeService::verify(
            challenge: $expired['challenge'],
            signature: $expired['signature'],
            response: $this->solveMath($expired['challenge']),
            secret: self::SECRET,
            nonceStore: $store,
            now: $now + 601,
            errorExpired: 'EXPIRED'
        );
        $this->assertSame('EXPIRED', $expiredResult);
    }

    public function testVerifyRejectsLegacyStatelessV1AndTamperedTokens(): void
    {
        $store = new InMemoryCaptchaNonceStore();
        $now = 1764000000;

        // Legacy v1 stateless signature (plain HMAC of "$x + $y")
        $legacySig = hash_hmac('sha256', '4 + 5', self::SECRET);
        $this->assertSame(
            'BAD_SIGN',
            CaptchaChallengeService::verify(
                challenge: '4 + 5',
                signature: $legacySig,
                response: '9',
                secret: self::SECRET,
                nonceStore: $store,
                now: $now,
                errorSign: 'BAD_SIGN'
            )
        );

        // Tampered expiration timestamp
        $generated = CaptchaChallengeService::generate(self::SECRET, $now);
        $parts = explode('.', $generated['signature']);
        $tamperedExp = $parts[0] . '.' . ((int) $parts[1] + 9999) . '.' . $parts[2] . '.' . $parts[3];
        $this->assertSame(
            'BAD_SIGN',
            CaptchaChallengeService::verify(
                challenge: $generated['challenge'],
                signature: $tamperedExp,
                response: $this->solveMath($generated['challenge']),
                secret: self::SECRET,
                nonceStore: $store,
                now: $now,
                errorSign: 'BAD_SIGN'
            )
        );

        // Wrong math answer
        $this->assertSame(
            'WRONG_ANSWER',
            CaptchaChallengeService::verify(
                challenge: $generated['challenge'],
                signature: $generated['signature'],
                response: '999',
                secret: self::SECRET,
                nonceStore: $store,
                now: $now,
                errorWrong: 'WRONG_ANSWER'
            )
        );

        // Non-numeric response
        $this->assertSame(
            'WRONG_ANSWER',
            CaptchaChallengeService::verify(
                challenge: $generated['challenge'],
                signature: $generated['signature'],
                response: 'not-a-number',
                secret: self::SECRET,
                nonceStore: $store,
                now: $now,
                errorWrong: 'WRONG_ANSWER'
            )
        );
    }

    public function testResolveSecretFailsClosedOutsideLocalOrTestingEnvironments(): void
    {
        $this->clearEnvVar('CAPTCHA_SECRET');
        $this->clearEnvVar('APP_ENV');

        // Unset APP_ENV defaults to production -> must fail closed
        try {
            CaptchaChallengeService::resolveSecret();
            $this->fail('Expected RuntimeException when CAPTCHA_SECRET and APP_ENV are unset.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('CAPTCHA_SECRET', $e->getMessage());
        }

        // Explicit production -> must fail closed
        $_ENV['APP_ENV'] = 'production';
        try {
            CaptchaChallengeService::resolveSecret();
            $this->fail('Expected RuntimeException when CAPTCHA_SECRET is unset in production.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('CAPTCHA_SECRET', $e->getMessage());
        }

        // Explicit local/testing -> returns non-hardcoded-legacy dev fallback
        $_ENV['APP_ENV'] = 'testing';
        $devSecret = CaptchaChallengeService::resolveSecret();
        $this->assertSame(CaptchaChallengeService::LOCAL_DEV_FALLBACK_SECRET, $devSecret);
        $this->assertNotSame('securesaltsecret', $devSecret);

        // Configured secret in production -> returned verbatim
        $_ENV['APP_ENV'] = 'production';
        $_ENV['CAPTCHA_SECRET'] = 'prod-rotated-secret-value';
        $this->assertSame('prod-rotated-secret-value', CaptchaChallengeService::resolveSecret());

        // Empty $_ENV placeholder must not mask a populated $_SERVER secret
        $_ENV['CAPTCHA_SECRET'] = '';
        $_SERVER['CAPTCHA_SECRET'] = 'apache-setenv-secret-value';
        $this->assertSame('apache-setenv-secret-value', CaptchaChallengeService::resolveSecret());
    }

    public function testFileCaptchaNonceStoreFailsClosedOnCorruptedJsonFile(): void
    {
        $baseDir = sys_get_temp_dir() . '/ovf_nonce_corrupt_' . bin2hex(random_bytes(6));
        mkdir($baseDir, 0700, true);
        $storeFile = $baseDir . '/consumed_nonces.json';
        file_put_contents($storeFile, '{corrupted-truncated-json');
        chmod($storeFile, 0600);

        try {
            $store = new FileCaptchaNonceStore($storeFile);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Corrupted CAPTCHA nonce store file.');
            $store->consume('cap:any_nonce', time() + 600, time());
        } finally {
            if (file_exists($storeFile)) {
                @unlink($storeFile);
            }
            if (is_dir($baseDir)) {
                @rmdir($baseDir);
            }
        }
    }

    public function testFileCaptchaNonceStoreEnforcesAtomicSingleUsePruningPermissionsAndSymlinkDefense(): void
    {
        $baseDir = sys_get_temp_dir() . '/ovf_nonce_unit_' . bin2hex(random_bytes(6));
        $storeFile = $baseDir . '/consumed_nonces.json';

        try {
            $storeA = new FileCaptchaNonceStore($storeFile);
            $storeB = new FileCaptchaNonceStore($storeFile);

            $now = 1764000000;
            $this->assertTrue($storeA->consume('cap:nonce_a', $now + 60, $now));
            $this->assertFalse($storeB->consume('cap:nonce_a', $now + 60, $now + 5));

            // Owner-only directory (0700) and file (0600) permissions
            $dirPerms = fileperms($baseDir);
            $filePerms = fileperms($storeFile);
            $this->assertNotFalse($dirPerms);
            $this->assertNotFalse($filePerms);
            $this->assertSame(0700, $dirPerms & 0777);
            $this->assertSame(0600, $filePerms & 0777);

            // Expired entry gets pruned on subsequent writes
            $this->assertTrue($storeA->consume('cap:nonce_b', $now + 600, $now + 120));
            $persisted = json_decode((string) file_get_contents($storeFile), true);
            $this->assertIsArray($persisted);
            $this->assertArrayNotHasKey('cap:nonce_a', $persisted);
            $this->assertArrayHasKey('cap:nonce_b', $persisted);

            // Symlinked store file must be rejected
            $symlinkPath = $baseDir . '/symlink_store.json';
            symlink($storeFile, $symlinkPath);
            $symlinkStore = new FileCaptchaNonceStore($symlinkPath);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('symlinked');
            $symlinkStore->consume('cap:nonce_c', $now + 600, $now + 120);
        } finally {
            if (is_link($baseDir . '/symlink_store.json')) {
                @unlink($baseDir . '/symlink_store.json');
            }
            if (file_exists($storeFile)) {
                @unlink($storeFile);
            }
            if (is_dir($baseDir)) {
                @rmdir($baseDir);
            }
        }
    }

    public function testPaymentContinuationTokenIsSingleUseExpiringAndStayBound(): void
    {
        $store = new InMemoryCaptchaNonceStore();
        $now = 1764000000;

        $token = CaptchaChallengeService::issuePaymentToken(
            reservationUid: 'ovf_20271110_abc123',
            propertyId: '1606',
            checkIn: '2027-11-10',
            checkOut: '2027-11-14',
            secret: self::SECRET,
            now: $now
        );

        $this->assertMatchesRegularExpression('/^p1\.\d+\.[a-f0-9]{32}\.[a-f0-9]{64}$/', $token);

        // Mismatched stay parameters must fail without consuming nonce
        $this->assertSame(
            'BAD_SIGN',
            CaptchaChallengeService::verifyPaymentToken(
                token: $token,
                reservationUid: 'ovf_20271110_abc123',
                propertyId: '1707',
                checkIn: '2027-11-10',
                checkOut: '2027-11-14',
                secret: self::SECRET,
                nonceStore: $store,
                now: $now + 5,
                errorSign: 'BAD_SIGN'
            )
        );

        // Expired token must fail
        $this->assertSame(
            'EXPIRED',
            CaptchaChallengeService::verifyPaymentToken(
                token: $token,
                reservationUid: 'ovf_20271110_abc123',
                propertyId: '1606',
                checkIn: '2027-11-10',
                checkOut: '2027-11-14',
                secret: self::SECRET,
                nonceStore: $store,
                now: $now + 601,
                errorExpired: 'EXPIRED'
            )
        );

        // Valid first use succeeds
        $this->assertTrue(CaptchaChallengeService::verifyPaymentToken(
            token: $token,
            reservationUid: 'ovf_20271110_abc123',
            propertyId: '1606',
            checkIn: '2027-11-10',
            checkOut: '2027-11-14',
            secret: self::SECRET,
            nonceStore: $store,
            now: $now + 20,
            errorReplayed: 'REPLAYED'
        ));

        // Second use (replay) must fail
        $this->assertSame(
            'REPLAYED',
            CaptchaChallengeService::verifyPaymentToken(
                token: $token,
                reservationUid: 'ovf_20271110_abc123',
                propertyId: '1606',
                checkIn: '2027-11-10',
                checkOut: '2027-11-14',
                secret: self::SECRET,
                nonceStore: $store,
                now: $now + 21,
                errorReplayed: 'REPLAYED'
            )
        );
    }

    private function solveMath(string $challenge): string
    {
        [$a, $b] = array_map('intval', explode('+', $challenge));

        return (string) ($a + $b);
    }

    private function clearEnvVar(string $key): void
    {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }

    private function restoreEnvVar(string $key, ?string $envVal, ?string $serverVal, string|false $getEnvVal): void
    {
        if ($envVal !== null) {
            $_ENV[$key] = $envVal;
        } else {
            unset($_ENV[$key]);
        }

        if ($serverVal !== null) {
            $_SERVER[$key] = $serverVal;
        } else {
            unset($_SERVER[$key]);
        }

        if ($getEnvVal !== false) {
            putenv("{$key}={$getEnvVal}");
        } else {
            putenv($key);
        }
    }
}
