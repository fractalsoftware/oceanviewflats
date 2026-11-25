<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Security;

/**
 * Atomic single-use nonce store preventing CAPTCHA challenge and continuation token replays.
 */
interface CaptchaNonceStoreInterface
{
    /**
     * Atomically records a single-use nonce until $expiresAt if it has not already been consumed.
     *
     * @param string $nonce Namespaced nonce identifier.
     * @param int $expiresAt Unix timestamp (seconds) after which the nonce record may be pruned.
     * @param int $now Current Unix timestamp (seconds) used for pruning expired entries.
     * @return bool True if this call consumed the nonce for the first time; false if already consumed.
     */
    public function consume(string $nonce, int $expiresAt, int $now): bool;
}
