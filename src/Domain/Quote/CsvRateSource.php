<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Quote;

use OceanViewFlats\Domain\Support\PathResolver;
use RuntimeException;

/**
 * Loads seasonal pricing rate tiers from the authoritative prices.csv file.
 */
final class CsvRateSource implements RateSourceInterface
{
    /** @var array<string, array<int, RateTier>>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly ?string $csvFilePath = null
    ) {
    }

    /**
     * @return array<int, RateTier>
     */
    public function getTiersForProperty(string $propertyId): array
    {
        $this->ensureLoaded();
        return $this->cache[$propertyId] ?? [];
    }

    private function ensureLoaded(): void
    {
        if ($this->cache !== null) {
            return;
        }

        $filePath = $this->csvFilePath ?? PathResolver::resolveIfExists('data/prices.csv') ?? (dirname(__DIR__, 3) . '/public/data/prices.csv');
        if (!file_exists($filePath)) {
            $this->cache = [];
            return;
        }

        $file = fopen($filePath, 'r');
        if ($file === false) {
            throw new RuntimeException("Unable to open prices CSV file at: {$filePath}");
        }

        $this->cache = [];
        // Skip header row
        fgetcsv($file, 0, ',', '"', '\\');

        while (($row = fgetcsv($file, 0, ',', '"', '\\')) !== false) {
            if (count($row) >= 5) {
                $propId = trim($row[0]);
                $startDate = trim($row[1]);
                $endDate = trim($row[2]);
                $rateCop = (float) $row[3];
                $minStay = (int) $row[4];

                $this->cache[$propId][] = new RateTier(
                    propertyId: $propId,
                    startDate: $startDate,
                    endDate: $endDate,
                    nightlyRateCop: $rateCop,
                    minimumStay: $minStay
                );
            }
        }

        fclose($file);
    }
}
