<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Fulfillment;

final class FulfillmentResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public readonly bool $isGuestEmailSent,
        public readonly bool $isHostEmailSent,
        public readonly array $errors = []
    ) {}

    public function isSuccess(): bool
    {
        return $this->isGuestEmailSent && empty($this->errors);
    }
}
