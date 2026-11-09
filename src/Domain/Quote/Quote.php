<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Quote;

use JsonSerializable;

/**
 * Value object representing an authoritative calculated Quote.
 * Enforces CONTEXT.md and ADR 0004 specifications.
 */
final class Quote implements JsonSerializable
{
    /**
     * @param array<int, array{date: string, rateCop: float, tier: ?string}> $nights
     */
    public function __construct(
        public readonly string $propertyId,
        public readonly string $checkIn,
        public readonly string $checkOut,
        public readonly array $nights,
        public readonly int $nightsCount,
        public readonly float $accommodationTotalCop,
        public readonly float $cleaningFeeCop,
        public readonly float $totalCop,
        public readonly int $minimumStayRequired,
        public readonly bool $isValid,
        public readonly ?string $violationReason = null
    ) {
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function totalCop(): float
    {
        return $this->totalCop;
    }

    public function accommodationTotalCop(): float
    {
        return $this->accommodationTotalCop;
    }

    public function cleaningFeeCop(): float
    {
        return $this->cleaningFeeCop;
    }

    public function nightsCount(): int
    {
        return $this->nightsCount;
    }

    public function minimumStayRequired(): int
    {
        return $this->minimumStayRequired;
    }

    public function violationReason(): ?string
    {
        return $this->violationReason;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'property_id' => $this->propertyId,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'nights_count' => $this->nightsCount,
            'nights' => $this->nights,
            'accommodation_total_cop' => $this->accommodationTotalCop,
            'cleaning_fee_cop' => $this->cleaningFeeCop,
            'total_cop' => $this->totalCop,
            'minimum_stay_required' => $this->minimumStayRequired,
            'is_valid' => $this->isValid,
            'violation_reason' => $this->violationReason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
