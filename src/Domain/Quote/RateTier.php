<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Quote;

use ArrayAccess;
use BadMethodCallException;
use InvalidArgumentException;

/**
 * Value object and domain entity representing a seasonal rate tier for a property.
 * Supports administrative properties, array access for template views, and date coverage utilities.
 *
 * @implements ArrayAccess<string, mixed>
 */
final class RateTier implements ArrayAccess
{
    public function __construct(
        public readonly string $propertyId,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly float $nightlyRateCop,
        public readonly int $minimumStay = 1,
        public readonly ?int $id = null,
        public readonly string $seasonName = 'Standard Rate',
        public readonly float $cleaningFeeCop = 0.0,
        public readonly ?int $createdBy = null,
        public readonly ?string $createdByName = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null
    ) {
        if ($this->propertyId === '') {
            throw new InvalidArgumentException('Property ID cannot be empty.');
        }
        if ($this->startDate > $this->endDate) {
            throw new InvalidArgumentException(
                sprintf('End date (%s) must be on or after start date (%s).', $this->endDate, $this->startDate)
            );
        }
        if ($this->nightlyRateCop < 0.0) {
            throw new InvalidArgumentException('Nightly rate cannot be negative.');
        }
        if ($this->minimumStay < 1) {
            throw new InvalidArgumentException('Minimum stay must be at least 1.');
        }
    }

    /**
     * Determines whether a specific date (Y-m-d) falls within this seasonal tier.
     */
    public function coversDate(string $date): bool
    {
        return $date >= $this->startDate && $date <= $this->endDate;
    }

    /**
     * Determines whether a guest stay [checkIn, checkOut) overlaps with this seasonal tier [startDate, endDate].
     * Check-in on day after tier end or check-out on tier start does not overlap.
     */
    public function overlaps(string $checkIn, string $checkOut): bool
    {
        return $checkIn < $checkOut && $this->startDate < $checkOut && $this->endDate >= $checkIn;
    }

    /**
     * Serializes this tier into an associative array compatible with both domain and administrative views.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'property_id' => $this->propertyId,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'nightly_rate_cop' => $this->nightlyRateCop,
            'price_per_night' => $this->nightlyRateCop,
            'minimum_stay' => $this->minimumStay,
            'min_stay' => $this->minimumStay,
            'season_name' => $this->seasonName,
            'cleaning_fee_cop' => $this->cleaningFeeCop,
            'cleaning_fee' => $this->cleaningFeeCop,
            'created_by' => $this->createdBy,
            'created_by_name' => $this->createdByName,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * Serializes this tier into a compact public rate summary entry.
     *
     * @return array{
     *     property_id: string,
     *     start_date: string,
     *     end_date: string,
     *     nightly_rate_cop: int,
     *     minimum_stay: int,
     *     season_name: string
     * }
     */
    public function toSummaryArray(): array
    {
        return [
            'property_id' => $this->propertyId,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'nightly_rate_cop' => (int) round($this->nightlyRateCop),
            'minimum_stay' => $this->minimumStay,
            'season_name' => $this->seasonName,
        ];
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists((string) $offset, $this->toArray());
    }

    public function offsetGet(mixed $offset): mixed
    {
        $data = $this->toArray();
        return $data[(string) $offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('RateTier is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('RateTier is immutable.');
    }
}
