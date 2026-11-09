<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Quote;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * In-memory implementation of RateRepositoryInterface for fast, isolated unit testing.
 * Manages collections of RateTier entities with auto-increment ID generation, overlap checking,
 * annual rate filtering, gap detection, and CSV parsing.
 */
final class InMemoryRateRepository extends InMemoryRateSource implements RateRepositoryInterface
{
    private readonly PropertyRatesConfig $ratesConfig;
    private int $autoIncrementId = 0;
    /** @var array<int, RateTier> */
    private array $byId = [];

    /**
     * @param array<int, RateTier> $initialTiers
     */
    public function __construct(
        array $initialTiers = [],
        ?PropertyRatesConfig $ratesConfig = null
    ) {
        $this->ratesConfig = $ratesConfig ?? PropertyRatesConfig::createDefault();
        parent::__construct();

        foreach ($initialTiers as $tier) {
            $this->save($tier);
        }
    }

    public function addTier(RateTier $tier): void
    {
        $this->save($tier);
    }

    public function findById(int $id): ?RateTier
    {
        return $this->byId[$id] ?? null;
    }

    public function hasOverlap(
        string $propertyId,
        string $startDate,
        string $endDate,
        ?int $excludeId = null
    ): bool {
        if ($startDate >= $endDate) {
            throw new InvalidArgumentException(
                sprintf('End date (%s) must be after start date (%s).', $endDate, $startDate)
            );
        }

        $tiers = $this->tiers[$propertyId] ?? [];
        foreach ($tiers as $tier) {
            if ($excludeId !== null && $tier->id === $excludeId) {
                continue;
            }
            if ($tier->startDate <= $endDate && $tier->endDate >= $startDate) {
                return true;
            }
        }

        return false;
    }

    public function save(RateTier $tier, ?int $adminUserId = null): RateTier
    {
        $id = $tier->id;
        if ($id === null) {
            $id = ++$this->autoIncrementId;
            $tier = new RateTier(
                propertyId: $tier->propertyId,
                startDate: $tier->startDate,
                endDate: $tier->endDate,
                nightlyRateCop: $tier->nightlyRateCop,
                minimumStay: $tier->minimumStay,
                id: $id,
                seasonName: $tier->seasonName,
                cleaningFeeCop: $tier->cleaningFeeCop,
                createdBy: $adminUserId ?? $tier->createdBy,
                createdByName: $tier->createdByName,
                createdAt: $tier->createdAt ?? date('Y-m-d H:i:s'),
                updatedAt: $tier->updatedAt ?? date('Y-m-d H:i:s'),
            );
        } else {
            if ($id > $this->autoIncrementId) {
                $this->autoIncrementId = $id;
            }
            $existing = $this->byId[$id] ?? null;
            $createdBy = $adminUserId ?? $tier->createdBy ?? ($existing !== null ? $existing->createdBy : null);
            $createdByName = $tier->createdByName ?? ($existing !== null ? $existing->createdByName : null);
            $createdAt = ($existing !== null && $existing->createdAt !== null)
                ? $existing->createdAt
                : ($tier->createdAt ?? date('Y-m-d H:i:s'));

            $tier = new RateTier(
                propertyId: $tier->propertyId,
                startDate: $tier->startDate,
                endDate: $tier->endDate,
                nightlyRateCop: $tier->nightlyRateCop,
                minimumStay: $tier->minimumStay,
                id: $id,
                seasonName: $tier->seasonName,
                cleaningFeeCop: $tier->cleaningFeeCop,
                createdBy: $createdBy,
                createdByName: $createdByName,
                createdAt: $createdAt,
                updatedAt: date('Y-m-d H:i:s'),
            );
        }

        $this->byId[$id] = $tier;
        $this->syncPropertyTiers($tier->propertyId);

        return $tier;
    }

    public function delete(int $id): bool
    {
        if (!isset($this->byId[$id])) {
            return false;
        }

        $propId = $this->byId[$id]->propertyId;
        unset($this->byId[$id]);
        $this->syncPropertyTiers($propId);

        return true;
    }

    /**
     * @return list<RateTier>
     */
    public function getRatesForProperty(string $propertyId, ?int $year = null): array
    {
        $tiers = $this->tiers[$propertyId] ?? [];
        if ($year === null) {
            return array_values($tiers);
        }

        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);

        $result = [];
        foreach ($tiers as $tier) {
            if ($tier->startDate <= $yearEnd && $tier->endDate >= $yearStart) {
                $result[] = $tier;
            }
        }

        return $result;
    }

    /**
     * @return list<array{
     *     start_date: string,
     *     end_date: string,
     *     nights: int,
     *     fallback_rate: float
     * }>
     */
    public function detectGaps(string $propertyId, int $year): array
    {
        $defaultRate = $this->ratesConfig->isValidProperty($propertyId)
            ? $this->ratesConfig->getDefaultNightlyRate($propertyId)
            : 350000.0;

        $yearStartStr = sprintf('%04d-01-01', $year);
        $yearEndStr = sprintf('%04d-12-31', $year);

        $tiers = $this->getRatesForProperty($propertyId, $year);

        $gaps = [];
        $cursorDate = new DateTimeImmutable($yearStartStr);
        $yearEnd = new DateTimeImmutable($yearEndStr);

        foreach ($tiers as $tier) {
            $tierStart = new DateTimeImmutable($tier->startDate);
            $tierEnd = new DateTimeImmutable($tier->endDate);

            $effectiveStart = $tierStart < $cursorDate ? $cursorDate : $tierStart;

            if ($effectiveStart > $cursorDate) {
                $gapEnd = $effectiveStart->modify('-1 day');
                if ($gapEnd >= $cursorDate) {
                    $nights = (int) $cursorDate->diff($effectiveStart)->days;
                    $gaps[] = [
                        'start_date' => $cursorDate->format('Y-m-d'),
                        'end_date' => $gapEnd->format('Y-m-d'),
                        'nights' => $nights,
                        'fallback_rate' => $defaultRate,
                    ];
                }
            }

            $nextDay = $tierEnd->modify('+1 day');
            if ($nextDay > $cursorDate) {
                $cursorDate = $nextDay;
            }

            if ($cursorDate > $yearEnd) {
                break;
            }
        }

        if ($cursorDate <= $yearEnd) {
            $nights = (int) $cursorDate->diff($yearEnd->modify('+1 day'))->days;
            $gaps[] = [
                'start_date' => $cursorDate->format('Y-m-d'),
                'end_date' => $yearEnd->format('Y-m-d'),
                'nights' => $nights,
                'fallback_rate' => $defaultRate,
            ];
        }

        return $gaps;
    }

    public function seedFromCsv(string $csvFilePath, ?int $createdBy = null): int
    {
        if (!file_exists($csvFilePath)) {
            throw new RuntimeException("Prices CSV file not found at: {$csvFilePath}");
        }

        $file = fopen($csvFilePath, 'r');
        if ($file === false) {
            throw new RuntimeException("Unable to open prices CSV file at: {$csvFilePath}");
        }

        // Skip header
        fgetcsv($file);

        $insertedCount = 0;
        while (($row = fgetcsv($file)) !== false) {
            if (count($row) >= 5) {
                $propId = trim((string) $row[0]);
                $startDate = trim((string) $row[1]);
                $endDate = trim((string) $row[2]);
                $rateCop = (float) $row[3];
                $minStay = (int) $row[4];

                if (!$this->hasOverlap($propId, $startDate, $endDate)) {
                    $this->save(
                        new RateTier(
                            propertyId: $propId,
                            startDate: $startDate,
                            endDate: $endDate,
                            nightlyRateCop: $rateCop,
                            minimumStay: $minStay,
                            seasonName: 'Imported Standard Rate',
                            createdBy: $createdBy
                        ),
                        adminUserId: $createdBy
                    );
                    $insertedCount++;
                }
            }
        }

        fclose($file);

        return $insertedCount;
    }

    private function syncPropertyTiers(string $propertyId): void
    {
        $filtered = [];
        foreach ($this->byId as $tier) {
            if ($tier->propertyId === $propertyId) {
                $filtered[] = $tier;
            }
        }
        usort($filtered, static fn(RateTier $a, RateTier $b) => strcmp($a->startDate, $b->startDate));
        $this->tiers[$propertyId] = $filtered;
    }
}
