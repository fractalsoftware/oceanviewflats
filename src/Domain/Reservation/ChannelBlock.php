<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Ephemeral calendar unavailability imported from external booking channels (e.g., Airbnb).
 * As decreed in ADR 0002, Channel Blocks are strictly held in-memory and never written to the database.
 */
final class ChannelBlock
{
    public function __construct(
        public readonly string $propertyId,
        public readonly string $startDate, // YYYY-MM-DD
        public readonly string $endDate,   // YYYY-MM-DD
        public readonly string $source = 'airbnb',
        public readonly ?string $summary = null
    ) {
        if ($this->propertyId === '') {
            throw new InvalidArgumentException('Property ID cannot be empty');
        }
        if ($this->startDate >= $this->endDate) {
            throw new InvalidArgumentException(
                sprintf('End date (%s) must be after start date (%s)', $this->endDate, $this->startDate)
            );
        }
    }

    /**
     * Determines whether the given stay date range overlaps with this channel block.
     * Check-in on checkout date does not constitute an overlap.
     */
    public function overlaps(string $checkIn, string $checkOut): bool
    {
        return ($this->startDate < $checkOut) && ($this->endDate > $checkIn);
    }

    /**
     * Returns an array of ISO 8601 date strings for each night occupied by this block.
     *
     * @return list<string>
     */
    public function nights(): array
    {
        $start = new DateTimeImmutable($this->startDate);
        $end = new DateTimeImmutable($this->endDate);
        $period = new DatePeriod($start, new DateInterval('P1D'), $end);

        $nights = [];
        foreach ($period as $date) {
            $nights[] = $date->format('Y-m-d');
        }

        return $nights;
    }

    /**
     * Computes the deterministic MD5 identifier for this channel block.
     */
    public function uid(): string
    {
        return md5("{$this->propertyId}_{$this->startDate}_{$this->endDate}_{$this->source}");
    }

    /**
     * Determines whether an active (non-cancelled) reservation record absorbs/matches this channel block.
     *
     * @param array<string, mixed> $reservationRow
     */
    public function isAbsorbedByRow(array $reservationRow): bool
    {
        if ((string) ($reservationRow['property_id'] ?? '') !== $this->propertyId) {
            return false;
        }
        $status = (string) ($reservationRow['status'] ?? '');
        if ($status === 'cancelled') {
            return false;
        }
        $resBlockUid = (string) ($reservationRow['channel_block_uid'] ?? '');
        $cin = (string) ($reservationRow['check_in'] ?? '');
        $cout = (string) ($reservationRow['check_out'] ?? '');
        $source = (string) ($reservationRow['source'] ?? '');

        return ($resBlockUid !== '' && $resBlockUid === $this->uid())
            || ($cin === $this->startDate && $cout === $this->endDate)
            || ($cin < $this->endDate && $cout > $this->startDate && strtolower($source) === strtolower($this->source));
    }
}
