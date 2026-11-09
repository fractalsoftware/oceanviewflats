<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Quote;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

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
     * Factory to instantiate engine with standard configuration and database/CSV rate table.
     */
    public static function createDefault(?string $csvPath = null, ?PDO $pdo = null): self
    {
        $config = PropertyRatesConfig::createDefault();
        $csvRateSource = new CsvRateSource($csvPath);
        $rateSource = $pdo !== null
            ? new PdoRateSource($pdo, $csvRateSource, $config)
            : $csvRateSource;

        return new self(
            rateSource: $rateSource,
            config: $config
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

    /**
     * Computes active nightly rate summaries for Properties 1606 and 1707 alongside seasonal rate tiers.
     *
     * @return array{
     *     properties: array{
     *         1606: array{
     *             property_id: string,
     *             current_nightly_rate_cop: int,
     *             currentNightlyRate: int,
     *             default_nightly_rate_cop: int,
     *             defaultNightlyRate: int,
     *             cleaning_fee_cop: int,
     *             cleaningFeeCop: int,
     *             default_minimum_stay: int,
     *             defaultMinimumStay: int,
     *             is_seasonal_tier_active: bool,
     *             isSeasonalTierActive: bool,
     *             active_tier_name: string,
     *             activeTierName: string,
     *             active_tier_end_date: ?string,
     *             activeTierEndDate: ?string
     *         },
     *         1707: array{
     *             property_id: string,
     *             current_nightly_rate_cop: int,
     *             currentNightlyRate: int,
     *             default_nightly_rate_cop: int,
     *             defaultNightlyRate: int,
     *             cleaning_fee_cop: int,
     *             cleaningFeeCop: int,
     *             default_minimum_stay: int,
     *             defaultMinimumStay: int,
     *             is_seasonal_tier_active: bool,
     *             isSeasonalTierActive: bool,
     *             active_tier_name: string,
     *             activeTierName: string,
     *             active_tier_end_date: ?string,
     *             activeTierEndDate: ?string
     *         }
     *     },
     *     tiers: list<array{
     *         property_id: string,
     *         start_date: string,
     *         end_date: string,
     *         nightly_rate_cop: int,
     *         minimum_stay: int,
     *         season_name: string
     *     }>
     * }
     */
    public function getRateSummary(
        ?string $propertyId = null,
        ?DateTimeImmutable $now = null,
        bool $includeFallbackTiers = true
    ): array {
        $propertyId = $propertyId !== null ? trim($propertyId) : '';
        if ($propertyId !== '' && !$this->config->isValidProperty($propertyId)) {
            throw new InvalidArgumentException("Unknown property identifier: '{$propertyId}'");
        }

        $today = ($now ?? new DateTimeImmutable('today'))->format('Y-m-d');
        $propertiesPayload = [];

        foreach (['1606', '1707'] as $pid) {
            $tiers = (!$includeFallbackTiers && $this->rateSource instanceof RateRepositoryInterface)
                ? $this->rateSource->getRatesForProperty($pid)
                : $this->rateSource->getTiersForProperty($pid);
            usort($tiers, static fn(RateTier $a, RateTier $b): int => strcmp($a->startDate, $b->startDate));

            $activeTier = null;
            foreach ($tiers as $tier) {
                if ($tier->coversDate($today)) {
                    $activeTier = $tier;
                    break;
                }
            }

            $defaultRate = $this->config->getDefaultNightlyRate($pid);
            $cleaningFee = $this->config->getCleaningFee($pid);
            $defaultMinStay = $this->config->getDefaultMinimumStay($pid);

            $currentNightlyRate = $activeTier !== null ? $activeTier->nightlyRateCop : $defaultRate;
            $isSeasonalTierActive = $activeTier !== null;
            $activeTierName = $activeTier !== null ? $activeTier->seasonName : 'Baseline Rate';
            $activeTierEndDate = $activeTier?->endDate;

            $propertiesPayload[$pid] = [
                'property_id' => $pid,
                'current_nightly_rate_cop' => (int) round($currentNightlyRate),
                'currentNightlyRate' => (int) round($currentNightlyRate),
                'default_nightly_rate_cop' => (int) round($defaultRate),
                'defaultNightlyRate' => (int) round($defaultRate),
                'cleaning_fee_cop' => (int) round($cleaningFee),
                'cleaningFeeCop' => (int) round($cleaningFee),
                'default_minimum_stay' => $defaultMinStay,
                'defaultMinimumStay' => $defaultMinStay,
                'is_seasonal_tier_active' => $isSeasonalTierActive,
                'isSeasonalTierActive' => $isSeasonalTierActive,
                'active_tier_name' => $activeTierName,
                'activeTierName' => $activeTierName,
                'active_tier_end_date' => $activeTierEndDate,
                'activeTierEndDate' => $activeTierEndDate,
            ];
        }

        $tierProperties = $propertyId !== '' ? [$propertyId] : ['1606', '1707'];
        $tiersPayload = [];
        foreach ($tierProperties as $pid) {
            $propTiers = (!$includeFallbackTiers && $this->rateSource instanceof RateRepositoryInterface)
                ? $this->rateSource->getRatesForProperty($pid)
                : $this->rateSource->getTiersForProperty($pid);
            foreach ($propTiers as $tier) {
                $tiersPayload[] = $tier->toSummaryArray();
            }
        }

        return [
            'properties' => $propertiesPayload,
            'tiers' => $tiersPayload,
        ];
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
