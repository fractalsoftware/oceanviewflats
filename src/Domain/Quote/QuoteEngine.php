<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Quote;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Authoritative in-process quotation engine.
 * Computes night-by-night seasonal pricing, enforces strict multi-tier maximum minimum stays,
 * and incorporates centralized cleaning fees per ADR 0004.
 */
final class QuoteEngine implements QuoteEngineInterface
{
    private readonly PropertyRatesConfig $config;

    public function __construct(
        private readonly RateSourceInterface $rateSource,
        ?PropertyRatesConfig $config = null
    ) {
        $this->config = $config ?? PropertyRatesConfig::createDefault();
    }

    /**
     * Factory to instantiate engine with standard configuration and CSV rate table.
     */
    public static function createDefault(?string $csvPath = null): self
    {
        return new self(
            rateSource: new CsvRateSource($csvPath),
            config: PropertyRatesConfig::createDefault()
        );
    }

    /**
     * @inheritDoc
     */
    public function quote(string $propertyId, string $checkIn, string $checkOut): Quote
    {
        $propertyId = trim($propertyId);
        if (!$this->config->isValidProperty($propertyId)) {
            throw new InvalidArgumentException("Unknown property identifier: '{$propertyId}'");
        }

        $inDate = $this->parseDate($checkIn, 'Check-in');
        $outDate = $this->parseDate($checkOut, 'Check-out');

        if ($inDate >= $outDate) {
            throw new InvalidArgumentException("Check-out date ({$checkOut}) must be after check-in date ({$checkIn})");
        }

        $tiers = $this->rateSource->getTiersForProperty($propertyId);
        $defaultRate = $this->config->getDefaultNightlyRate($propertyId);
        $minimumStayRequired = $this->config->getDefaultMinimumStay($propertyId);

        $nights = [];
        $accommodationTotal = 0.0;
        $current = $inDate;

        while ($current < $outDate) {
            $dateStr = $current->format('Y-m-d');
            $matchingTier = null;

            foreach ($tiers as $tier) {
                if ($tier->coversDate($dateStr)) {
                    $matchingTier = $tier;
                    break;
                }
            }

            if ($matchingTier !== null) {
                $rate = $matchingTier->nightlyRateCop;
                $tierIdentifier = "{$matchingTier->startDate}_{$matchingTier->endDate}";
                // Enforce strict maximum minimum stay across all touched tiers per ADR 0004
                $minimumStayRequired = max($minimumStayRequired, $matchingTier->minimumStay);
            } else {
                $rate = $defaultRate;
                $tierIdentifier = null;
            }

            $accommodationTotal += $rate;
            $nights[] = [
                'date' => $dateStr,
                'rateCop' => $rate,
                'tier' => $tierIdentifier,
            ];

            $current = $current->modify('+1 day');
        }

        $nightsCount = count($nights);
        $cleaningFee = $this->config->getCleaningFee($propertyId);
        $totalCop = $accommodationTotal + $cleaningFee;

        $isValid = true;
        $violationReason = null;

        if ($nightsCount < $minimumStayRequired) {
            $isValid = false;
            $violationReason = 'min_stay';
        }

        return new Quote(
            propertyId: $propertyId,
            checkIn: $checkIn,
            checkOut: $checkOut,
            nights: $nights,
            nightsCount: $nightsCount,
            accommodationTotalCop: $accommodationTotal,
            cleaningFeeCop: $cleaningFee,
            totalCop: $totalCop,
            minimumStayRequired: $minimumStayRequired,
            isValid: $isValid,
            violationReason: $violationReason
        );
    }

    private function parseDate(string $dateStr, string $label): DateTimeImmutable
    {
        $dateStr = trim($dateStr);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateStr);

        if ($date === false || $date->format('Y-m-d') !== $dateStr) {
            throw new InvalidArgumentException("{$label} date '{$dateStr}' must be a valid date in Y-m-d format");
        }

        return $date;
    }
}
