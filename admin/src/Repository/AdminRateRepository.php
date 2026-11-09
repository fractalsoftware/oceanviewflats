<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Repository;

use OceanViewFlats\Domain\Quote\PdoRateRepository;
use OceanViewFlats\Domain\Quote\PropertyRatesConfig;
use OceanViewFlats\Domain\Quote\RateRepositoryInterface;
use OceanViewFlats\Domain\Quote\RateTier;
use OceanViewFlats\Domain\Reservation\Dashboard\PropertyRateStatus;
use PDO;

/**
 * Administrative repository for querying, mutating, and analyzing seasonal rate tiers.
 * Implements RateRepositoryInterface and forwards operations to PdoRateRepository,
 * maintaining backward compatibility with existing admin controllers and test suites.
 *
 * @deprecated Use \OceanViewFlats\Domain\Quote\RateRepositoryInterface instead.
 */
class AdminRateRepository implements RateRepositoryInterface
{
    private readonly RateRepositoryInterface $inner;
    private readonly PropertyRatesConfig $ratesConfig;

    public function __construct(
        private readonly PDO $pdo,
        ?PropertyRatesConfig $ratesConfig = null,
        ?RateRepositoryInterface $inner = null
    ) {
        $this->ratesConfig = $ratesConfig ?? PropertyRatesConfig::createDefault();
        $this->inner = $inner ?? new PdoRateRepository($pdo, $this->ratesConfig);
    }

    /**
     * @return array<int, RateTier>
     */
    public function getTiersForProperty(string $propertyId): array
    {
        return $this->inner->getTiersForProperty($propertyId);
    }

    public function findById(int $id): ?RateTier
    {
        return $this->inner->findById($id);
    }

    /**
     * Finds a single seasonal rate tier by ID with author details as an array.
     * Backward-compatibility helper.
     *
     * @return array<string, mixed>|null
     */
    public function findRateById(int $id): ?array
    {
        $tier = $this->inner->findById($id);
        return $tier !== null ? $tier->toArray() : null;
    }

    public function hasOverlap(
        string $propertyId,
        string $startDate,
        string $endDate,
        ?int $excludeId = null
    ): bool {
        return $this->inner->hasOverlap($propertyId, $startDate, $endDate, $excludeId);
    }

    public function save(RateTier $tier, ?int $adminUserId = null): RateTier
    {
        return $this->inner->save($tier, $adminUserId);
    }

    /**
     * Persists a new seasonal rate tier.
     * Backward-compatibility helper.
     *
     * @param array{
     *     property_id: string,
     *     start_date: string,
     *     end_date: string,
     *     season_name: string,
     *     price_per_night: float,
     *     min_stay?: int,
     *     cleaning_fee?: float
     * } $data
     */
    public function createRate(array $data, ?int $adminUserId = null): int
    {
        $tier = new RateTier(
            propertyId: $data['property_id'],
            startDate: $data['start_date'],
            endDate: $data['end_date'],
            nightlyRateCop: (float) $data['price_per_night'],
            minimumStay: (int) ($data['min_stay'] ?? 1),
            seasonName: $data['season_name'],
            cleaningFeeCop: (float) ($data['cleaning_fee'] ?? 0.0),
            createdBy: $adminUserId
        );

        $saved = $this->inner->save($tier, $adminUserId);
        return (int) $saved->id;
    }

    /**
     * Updates an existing seasonal rate tier.
     * Backward-compatibility helper.
     *
     * @param array{
     *     season_name: string,
     *     start_date: string,
     *     end_date: string,
     *     price_per_night: float,
     *     min_stay?: int,
     *     property_id?: string,
     *     cleaning_fee?: float
     * } $data
     */
    public function updateRate(int $id, array $data): bool
    {
        $existing = $this->inner->findById($id);
        $propertyId = (string) ($data['property_id'] ?? ($existing !== null ? $existing->propertyId : '1606'));
        $cleaningFee = (float) ($data['cleaning_fee'] ?? ($existing !== null ? $existing->cleaningFeeCop : 0.0));

        $tier = new RateTier(
            propertyId: $propertyId,
            startDate: (string) $data['start_date'],
            endDate: (string) $data['end_date'],
            nightlyRateCop: (float) $data['price_per_night'],
            minimumStay: (int) ($data['min_stay'] ?? 1),
            id: $id,
            seasonName: (string) $data['season_name'],
            cleaningFeeCop: $cleaningFee,
            createdBy: $existing?->createdBy
        );

        $this->inner->save($tier);
        return true;
    }

    public function delete(int $id): bool
    {
        return $this->inner->delete($id);
    }

    /**
     * Deletes a seasonal rate tier by ID.
     * Backward-compatibility helper.
     */
    public function deleteRate(int $id): bool
    {
        return $this->inner->delete($id);
    }

    /**
     * Retrieves all seasonal rate tiers for a property, optionally filtered by calendar year.
     *
     * @return list<RateTier>
     */
    public function getRatesForProperty(string $propertyId, ?int $year = null): array
    {
        return $this->inner->getRatesForProperty($propertyId, $year);
    }

    /**
     * Identifies all calendar gaps within a specified year that have no active seasonal rate tier,
     * falling back to the property's baseline nightly rate.
     *
     * @return list<array{
     *     start_date: string,
     *     end_date: string,
     *     nights: int,
     *     fallback_rate: float
     * }>
     */
    public function detectGaps(string $propertyId, int $year): array
    {
        return $this->inner->detectGaps($propertyId, $year);
    }

    public function seedFromCsv(string $csvFilePath, ?int $createdBy = null): int
    {
        return $this->inner->seedFromCsv($csvFilePath, $createdBy);
    }

    public function getPropertyRateStatus(string $propertyId, ?\DateTimeImmutable $now = null): PropertyRateStatus
    {
        if (method_exists($this->inner, 'getPropertyRateStatus')) {
            /** @var PropertyRateStatus */
            return $this->inner->getPropertyRateStatus($propertyId, $now);
        }

        $defaultRate = $this->ratesConfig->getDefaultNightlyRate($propertyId);
        return new PropertyRateStatus(
            propertyId: $propertyId,
            currentNightlyRate: $defaultRate,
            isSeasonalTierActive: false,
            activeTierName: 'Baseline Rate',
            activeTierEndDate: null
        );
    }
}
