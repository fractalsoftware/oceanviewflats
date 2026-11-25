<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Security;

use OceanViewFlats\Domain\Support\AppEnvironment;
use RuntimeException;

/**
 * Expiring, single-use HMAC-SHA256 mathematical CAPTCHA and payment continuation token service.
 *
 * Security invariants:
 * - Every challenge embeds an authenticated expiration timestamp (10-minute TTL by default)
 *   and a 128-bit cryptographic nonce: `v2.<exp>.<nonce>.<hmac>`.
 * - Legacy stateless v1 signatures (plain HMAC over `"$x + $y"`) are rejected unconditionally.
 * - Valid nonces are consumed atomically on first use via CaptchaNonceStoreInterface so
 *   intercepted `(challenge, signature, response)` tuples cannot be replayed across any endpoint.
 * - `CAPTCHA_SECRET` fails closed outside explicit local/development/testing environments.
 */
final class CaptchaChallengeService
{
    public const TOKEN_VERSION = 'v2';
    public const PAYMENT_TOKEN_VERSION = 'p1';
    public const DEFAULT_TTL_SECONDS = 600;
    public const LOCAL_DEV_FALLBACK_SECRET = 'ovf-local-dev-captcha-secret';

    /**
     * Resolves CAPTCHA_SECRET from environment variables and fails closed in production.
     *
     * Checks $_ENV, $_SERVER, and getenv() in order, skipping empty strings so an empty
     * placeholder in one superglobal cannot mask a populated secret in another.
     *
     * @throws RuntimeException When CAPTCHA_SECRET is missing outside explicit local/testing environments.
     */
    public static function resolveSecret(): string
    {
        $candidates = [
            $_ENV['CAPTCHA_SECRET'] ?? null,
            $_SERVER['CAPTCHA_SECRET'] ?? null,
            getenv('CAPTCHA_SECRET'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        if (AppEnvironment::isNonProduction(allowPhpUnit: false)) {
            return self::LOCAL_DEV_FALLBACK_SECRET;
        }

        throw new RuntimeException('CAPTCHA_SECRET must be configured outside local/testing environments.');
    }

    /**
     * Generates a new expiring single-use mathematical CAPTCHA challenge.
     *
     * @return array{challenge: string, signature: string, expires_at: int}
     */
    public static function generate(
        string $secret,
        ?int $now = null,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS
    ): array {
        if (trim($secret) === '') {
            throw new RuntimeException('CAPTCHA_SECRET cannot be empty when generating a challenge.');
        }

        $x = random_int(2, 9);
        $y = random_int(2, 9);
        $challenge = "{$x} + {$y}";
        $issuedAt = $now ?? time();
        $expiresAt = $issuedAt + max(1, $ttlSeconds);
        $nonce = bin2hex(random_bytes(16));

        return [
            'challenge' => $challenge,
            'signature' => self::signChallenge($challenge, $expiresAt, $nonce, $secret),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Produces a v2 signed CAPTCHA token binding challenge, expiration timestamp, and nonce.
     */
    public static function signChallenge(
        string $challenge,
        int $expiresAt,
        string $nonce,
        string $secret
    ): string {
        $mac = self::computeChallengeMac($challenge, $expiresAt, $nonce, $secret);

        return self::TOKEN_VERSION . '.' . $expiresAt . '.' . $nonce . '.' . $mac;
    }

    /**
     * Verifies signature authenticity, TTL expiration, arithmetic solution, and single-use nonce consumption.
     *
     * @return true|string True when valid and atomically consumed; error message string otherwise.
     */
    public static function verify(
        string $challenge,
        string $signature,
        string $response,
        string $secret,
        ?CaptchaNonceStoreInterface $nonceStore = null,
        ?int $now = null,
        string $errorSign = 'Security check failed. Please refresh the page and try again.',
        string $errorInvalid = 'Invalid verification challenge.',
        string $errorWrong = 'Incorrect answer.',
        ?string $errorExpired = null,
        ?string $errorReplayed = null
    ): bool|string {
        if (trim($secret) === '') {
            return $errorSign;
        }

        if (!preg_match('/^v2\.(\d+)\.([a-f0-9]{32})\.([a-f0-9]{64})$/', trim($signature), $tokenMatches)) {
            return $errorSign;
        }

        $expiresAt = (int) $tokenMatches[1];
        $nonce = $tokenMatches[2];
        $providedMac = $tokenMatches[3];

        $expectedMac = self::computeChallengeMac($challenge, $expiresAt, $nonce, $secret);
        if (!hash_equals($expectedMac, $providedMac)) {
            return $errorSign;
        }

        $currentTime = $now ?? time();
        if ($currentTime > $expiresAt) {
            return $errorExpired ?? $errorSign;
        }

        if (!preg_match('/^(\d+)\s*\+\s*(\d+)$/', trim($challenge), $mathMatches)) {
            return $errorInvalid;
        }

        $trimmedResponse = trim($response);
        if ($trimmedResponse === '' || !ctype_digit($trimmedResponse)) {
            return $errorWrong;
        }

        $expectedSum = (int) $mathMatches[1] + (int) $mathMatches[2];
        if ((int) $trimmedResponse !== $expectedSum) {
            return $errorWrong;
        }

        $store = $nonceStore ?? FileCaptchaNonceStore::default();
        if (!$store->consume('cap:' . $nonce, $expiresAt, $currentTime)) {
            return $errorReplayed ?? $errorSign;
        }

        return true;
    }

    /**
     * Issues a single-use, short-lived, stay-bound payment continuation token after book-request.php
     * verifies the guest's math CAPTCHA, allowing the immediate Checkout Brick payment submission
     * to authenticate once without reusing the consumed math CAPTCHA nonce.
     */
    public static function issuePaymentToken(
        string $reservationUid,
        string $propertyId,
        string $checkIn,
        string $checkOut,
        string $secret,
        ?int $now = null,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS
    ): string {
        if (trim($secret) === '') {
            throw new RuntimeException('CAPTCHA_SECRET cannot be empty when issuing a payment token.');
        }

        $issuedAt = $now ?? time();
        $expiresAt = $issuedAt + max(1, $ttlSeconds);
        $nonce = bin2hex(random_bytes(16));

        return self::signPaymentToken(
            $reservationUid,
            $propertyId,
            $checkIn,
            $checkOut,
            $expiresAt,
            $nonce,
            $secret
        );
    }

    /**
     * Produces a p1 signed payment continuation token bound to reservation and stay parameters.
     */
    public static function signPaymentToken(
        string $reservationUid,
        string $propertyId,
        string $checkIn,
        string $checkOut,
        int $expiresAt,
        string $nonce,
        string $secret
    ): string {
        $mac = self::computePaymentTokenMac(
            $reservationUid,
            $propertyId,
            $checkIn,
            $checkOut,
            $expiresAt,
            $nonce,
            $secret
        );

        return self::PAYMENT_TOKEN_VERSION . '.' . $expiresAt . '.' . $nonce . '.' . $mac;
    }

    /**
     * Verifies and atomically consumes a single-use stay-bound payment continuation token.
     *
     * @return true|string True when valid and consumed; error message string otherwise.
     */
    public static function verifyPaymentToken(
        string $token,
        string $reservationUid,
        string $propertyId,
        string $checkIn,
        string $checkOut,
        string $secret,
        ?CaptchaNonceStoreInterface $nonceStore = null,
        ?int $now = null,
        string $errorSign = 'Security check failed. Please refresh the page and try again.',
        ?string $errorExpired = null,
        ?string $errorReplayed = null
    ): bool|string {
        if (trim($secret) === '' || trim($reservationUid) === '') {
            return $errorSign;
        }

        if (!preg_match('/^p1\.(\d+)\.([a-f0-9]{32})\.([a-f0-9]{64})$/', trim($token), $tokenMatches)) {
            return $errorSign;
        }

        $expiresAt = (int) $tokenMatches[1];
        $nonce = $tokenMatches[2];
        $providedMac = $tokenMatches[3];

        $expectedMac = self::computePaymentTokenMac(
            $reservationUid,
            $propertyId,
            $checkIn,
            $checkOut,
            $expiresAt,
            $nonce,
            $secret
        );
        if (!hash_equals($expectedMac, $providedMac)) {
            return $errorSign;
        }

        $currentTime = $now ?? time();
        if ($currentTime > $expiresAt) {
            return $errorExpired ?? $errorSign;
        }

        $store = $nonceStore ?? FileCaptchaNonceStore::default();
        if (!$store->consume('pay:' . $nonce, $expiresAt, $currentTime)) {
            return $errorReplayed ?? $errorSign;
        }

        return true;
    }

    private static function computeChallengeMac(
        string $challenge,
        int $expiresAt,
        string $nonce,
        string $secret
    ): string {
        $payload = self::TOKEN_VERSION . '|' . $challenge . '|' . $expiresAt . '|' . $nonce;

        return hash_hmac('sha256', $payload, $secret);
    }

    private static function computePaymentTokenMac(
        string $reservationUid,
        string $propertyId,
        string $checkIn,
        string $checkOut,
        int $expiresAt,
        string $nonce,
        string $secret
    ): string {
        $payload = implode('|', [
            self::PAYMENT_TOKEN_VERSION,
            trim($reservationUid),
            trim($propertyId),
            trim($checkIn),
            trim($checkOut),
            (string) $expiresAt,
            $nonce,
        ]);

        return hash_hmac('sha256', $payload, $secret);
    }
}
