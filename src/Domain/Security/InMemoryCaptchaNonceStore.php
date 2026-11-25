<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Security;

/**
 * In-memory CAPTCHA nonce store for deterministic unit tests.
 */
final class InMemoryCaptchaNonceStore implements CaptchaNonceStoreInterface
{
    /**
     * @var array<string, int> Map of nonce => expiresAt timestamp
     */
    private array $consumed = [];

    public function consume(string $nonce, int $expiresAt, int $now): bool
    {
        foreach ($this->consumed as $key => $exp) {
            if ($exp < $now) {
                unset($this->consumed[$key]);
            }
        }

        if (isset($this->consumed[$nonce])) {
            return false;
        }

        $this->consumed[$nonce] = $expiresAt;

        return true;
    }

    /**
     * Returns the number of currently tracked unexpired nonces.
     */
    public function count(): int
    {
        return count($this->consumed);
    }
}
