<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Quote;

use DateTimeImmutable;
use InvalidArgumentException;
use OceanViewFlats\Domain\Reservation\Dashboard\PropertyRateStatus;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Database-backed implementation of RateRepositoryInterface.
 * Supports MySQL and SQLite, timeline gap detection, and CSV seeding.
 */
class PdoRateRepository implements RateRepositoryInterface
{
    private readonly PropertyRatesConfig $ratesConfig;

    public function __construct(
        protected readonly PDO $pdo,
        ?PropertyRatesConfig $ratesConfig = null,
        protected readonly ?RateSourceInterface $fallbackSource = null
    ) {
        $this->ratesConfig = $ratesConfig ?? PropertyRatesConfig::createDefault();
    }

    /**
     * @return array<int, RateTier>
     */
    public function getTiersForProperty(string $propertyId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT *
            FROM property_rates 
            WHERE property_id = :property_id 
            ORDER BY start_date ASC
        ');
        $stmt->execute(['property_id' => $propertyId]);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            if ($this->fallbackSource !== null) {
                return $this->fallbackSource->getTiersForProperty($propertyId);
            }
            return [];
        }

        $tiers = [];
        foreach ($rows as $row) {
            $tiers[] = $this->hydrateRateTier($row);
        }

        return $tiers;
    }

    public function findById(int $id): ?RateTier
    {
        $hasUsers = $this->hasAdminUsersTable();
        $userJoin = $hasUsers
            ? 'LEFT JOIN admin_users u ON pr.created_by = u.id'
            : '';
        $userSelect = $hasUsers
            ? 'pr.*, u.name AS created_by_name'
            : 'pr.*, NULL AS created_by_name';

        $stmt = $this->pdo->prepare("
            SELECT {$userSelect}
            FROM property_rates pr
            {$userJoin}
            WHERE pr.id = :id
        ");
        $stmt->execute(['id' => $id]);

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->hydrateRateTier($row);
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

        $sql = '
            SELECT COUNT(*) 
            FROM property_rates 
            WHERE property_id = :property_id 
              AND start_date <= :end_date 
              AND end_date >= :start_date
        ';
        $params = [
            'property_id' => $propertyId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function save(RateTier $tier, ?int $adminUserId = null): RateTier
    {
        if ($tier->id === null) {
            $stmt = $this->pdo->prepare('
                INSERT INTO property_rates (
                    property_id, start_date, end_date, season_name, price_per_night, min_stay, cleaning_fee, created_by
                ) VALUES (
                    :property_id, :start_date, :end_date, :season_name, :price_per_night, :min_stay, :cleaning_fee, :created_by
                )
            ');

            $stmt->execute([
                'property_id' => $tier->propertyId,
                'start_date' => $tier->startDate,
                'end_date' => $tier->endDate,
                'season_name' => $tier->seasonName,
                'price_per_night' => $tier->nightlyRateCop,
                'min_stay' => $tier->minimumStay,
                'cleaning_fee' => $tier->cleaningFeeCop,
                'created_by' => $adminUserId ?? $tier->createdBy,
            ]);

            $id = (int) $this->pdo->lastInsertId();
            $saved = $this->findById($id);
            if ($saved !== null) {
                return $saved;
            }

            return new RateTier(
                propertyId: $tier->propertyId,
                startDate: $tier->startDate,
                endDate: $tier->endDate,
                nightlyRateCop: $tier->nightlyRateCop,
                minimumStay: $tier->minimumStay,
                id: $id,
                seasonName: $tier->seasonName,
                cleaningFeeCop: $tier->cleaningFeeCop,
                createdBy: $adminUserId ?? $tier->createdBy,
            );
        }

        $stmt = $this->pdo->prepare('
            UPDATE property_rates
            SET property_id = :property_id,
                start_date = :start_date,
                end_date = :end_date,
                season_name = :season_name,
                price_per_night = :price_per_night,
                min_stay = :min_stay,
                cleaning_fee = :cleaning_fee,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');

        $stmt->execute([
            'id' => $tier->id,
            'property_id' => $tier->propertyId,
            'start_date' => $tier->startDate,
            'end_date' => $tier->endDate,
            'season_name' => $tier->seasonName,
            'price_per_night' => $tier->nightlyRateCop,
            'min_stay' => $tier->minimumStay,
            'cleaning_fee' => $tier->cleaningFeeCop,
        ]);

        $saved = $this->findById($tier->id);
        if ($saved !== null) {
            return $saved;
        }

        return $tier;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM property_rates WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @return list<RateTier>
     */
    public function getRatesForProperty(string $propertyId, ?int $year = null): array
    {
        $hasUsers = $this->hasAdminUsersTable();
        $userJoin = $hasUsers
            ? 'LEFT JOIN admin_users u ON pr.created_by = u.id'
            : '';
        $userSelect = $hasUsers
            ? 'pr.*, u.name AS created_by_name'
            : 'pr.*, NULL AS created_by_name';

        $sql = "
            SELECT {$userSelect}
            FROM property_rates pr
            {$userJoin}
            WHERE pr.property_id = :property_id
        ";
        $params = ['property_id' => $propertyId];

        if ($year !== null) {
            $sql .= ' AND pr.start_date <= :year_end AND pr.end_date >= :year_start';
            $params['year_start'] = sprintf('%04d-01-01', $year);
            $params['year_end'] = sprintf('%04d-12-31', $year);
        }

        $sql .= ' ORDER BY pr.start_date ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tiers = [];
        foreach ($rows as $row) {
            $tiers[] = $this->hydrateRateTier($row);
        }

        return $tiers;
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

            // Clamp tier start to year boundaries for analysis
            $effectiveStart = $tierStart < $cursorDate ? $cursorDate : $tierStart;

            if ($effectiveStart > $cursorDate) {
                // There is an unpriced gap between cursorDate and day before effectiveStart
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

            // Move cursor past tier end date (next day)
            $nextDay = $tierEnd->modify('+1 day');
            if ($nextDay > $cursorDate) {
                $cursorDate = $nextDay;
            }

            if ($cursorDate > $yearEnd) {
                break;
            }
        }

        // Check if there is remaining unpriced space until the end of the year
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
        $insertStmt = $this->pdo->prepare('
            INSERT INTO property_rates (
                property_id, start_date, end_date, season_name, price_per_night, min_stay, created_by
            ) VALUES (
                :property_id, :start_date, :end_date, :season_name, :price_per_night, :min_stay, :created_by
            )
        ');

        while (($row = fgetcsv($file)) !== false) {
            if (count($row) >= 5) {
                $propId = trim((string) $row[0]);
                $startDate = trim((string) $row[1]);
                $endDate = trim((string) $row[2]);
                $rateCop = (float) $row[3];
                $minStay = (int) $row[4];

                if (!$this->hasOverlap($propId, $startDate, $endDate)) {
                    $insertStmt->execute([
                        'property_id' => $propId,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'season_name' => 'Imported Standard Rate',
                        'price_per_night' => $rateCop,
                        'min_stay' => $minStay,
                        'created_by' => $createdBy,
                    ]);
                    $insertedCount++;
                }
            }
        }

        fclose($file);

        return $insertedCount;
    }

    private function hasAdminUsersTable(): bool
    {
        try {
            $stmt = $this->pdo->query('SELECT 1 FROM admin_users LIMIT 1');
            return $stmt !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public function getPropertyRateStatus(string $propertyId, ?DateTimeImmutable $now = null): PropertyRateStatus
    {
        $today = ($now ?? new DateTimeImmutable('today'))->format('Y-m-d');
        $tiers = $this->getTiersForProperty($propertyId);
        usort($tiers, static fn(RateTier $a, RateTier $b): int => strcmp($a->startDate, $b->startDate));

        $activeTier = null;
        foreach ($tiers as $tier) {
            if ($tier->startDate <= $today && $tier->endDate >= $today) {
                $activeTier = $tier;
                break;
            }
        }

        $defaultRate = $this->ratesConfig->getDefaultNightlyRate($propertyId);

        if ($activeTier !== null) {
            $currentNightlyRate = $activeTier->nightlyRateCop;
            $isSeasonalTierActive = true;
            $activeTierName = $activeTier->seasonName;
            $activeTierEndDate = $activeTier->endDate;
        } else {
            $currentNightlyRate = $defaultRate;
            $isSeasonalTierActive = false;
            $activeTierName = 'Baseline Rate';
            $activeTierEndDate = null;
        }

        $nextTier = null;
        foreach ($tiers as $tier) {
            if ($tier->startDate > $today) {
                if ($nextTier === null || $tier->startDate < $nextTier->startDate) {
                    $nextTier = $tier;
                }
            }
        }

        if ($nextTier !== null) {
            $nextTierRate = $nextTier->nightlyRateCop;
            $nextTierName = $nextTier->seasonName;
            $nextTierStartDate = $nextTier->startDate;
        } elseif ($activeTier !== null) {
            $nextTierRate = $defaultRate;
            $nextTierName = 'Baseline Rate';
            $nextTierStartDate = (new DateTimeImmutable($activeTier->endDate))->modify('+1 day')->format('Y-m-d');
        } else {
            $nextTierRate = null;
            $nextTierName = null;
            $nextTierStartDate = null;
        }

        return new PropertyRateStatus(
            propertyId: $propertyId,
            currentNightlyRate: $currentNightlyRate,
            isSeasonalTierActive: $isSeasonalTierActive,
            activeTierName: $activeTierName,
            activeTierEndDate: $activeTierEndDate,
            nextTierRate: $nextTierRate,
            nextTierName: $nextTierName,
            nextTierStartDate: $nextTierStartDate
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateRateTier(array $row): RateTier
    {
        return new RateTier(
            propertyId: (string) $row['property_id'],
            startDate: (string) $row['start_date'],
            endDate: (string) $row['end_date'],
            nightlyRateCop: (float) ($row['price_per_night'] ?? $row['nightly_rate_cop'] ?? 0.0),
            minimumStay: (int) ($row['min_stay'] ?? $row['minimum_stay'] ?? 1),
            id: isset($row['id']) ? (int) $row['id'] : null,
            seasonName: (string) ($row['season_name'] ?? 'Standard Rate'),
            cleaningFeeCop: (float) ($row['cleaning_fee'] ?? $row['cleaning_fee_cop'] ?? 0.0),
            createdBy: isset($row['created_by']) ? (int) $row['created_by'] : null,
            createdByName: isset($row['created_by_name']) ? (string) $row['created_by_name'] : null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }
}
