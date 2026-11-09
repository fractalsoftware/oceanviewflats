<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Quote;

use BadMethodCallException;
use InvalidArgumentException;
use OceanViewFlats\Domain\Quote\InMemoryRateRepository;
use OceanViewFlats\Domain\Quote\PdoRateRepository;
use OceanViewFlats\Domain\Quote\PropertyRatesConfig;
use OceanViewFlats\Domain\Quote\QuoteEngine;
use OceanViewFlats\Domain\Quote\RateRepositoryInterface;
use OceanViewFlats\Domain\Quote\RateTier;
use PDO;
use PHPUnit\Framework\TestCase;

final class RateRepositoryTest extends TestCase
{
    private function createSqlitePdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec('
            CREATE TABLE admin_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                name TEXT NOT NULL
            );

            CREATE TABLE property_rates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                property_id TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                season_name TEXT NOT NULL,
                price_per_night REAL NOT NULL,
                min_stay INTEGER NOT NULL DEFAULT 2,
                cleaning_fee REAL NOT NULL DEFAULT 0.0,
                resort_fee REAL NOT NULL DEFAULT 0.0,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');

        return $pdo;
    }

    public function testRateTierAdminAttributesAndArrayAccess(): void
    {
        $tier = new RateTier(
            propertyId: '1606',
            startDate: '2026-06-01',
            endDate: '2026-08-31',
            nightlyRateCop: 450000.0,
            minimumStay: 3,
            id: 42,
            seasonName: 'Summer High',
            cleaningFeeCop: 50000.0,
            createdBy: 1,
            createdByName: 'Admin User',
            createdAt: '2026-01-01 10:00:00',
            updatedAt: '2026-01-02 12:00:00'
        );

        $this->assertSame(42, $tier->id);
        $this->assertSame('Summer High', $tier->seasonName);
        $this->assertSame(50000.0, $tier->cleaningFeeCop);
        $this->assertSame(1, $tier->createdBy);
        $this->assertSame('Admin User', $tier->createdByName);
        $this->assertSame('2026-01-01 10:00:00', $tier->createdAt);
        $this->assertSame('2026-01-02 12:00:00', $tier->updatedAt);

        // ArrayAccess getters (canonical + legacy aliases)
        $this->assertTrue(isset($tier['id']));
        $this->assertSame(42, $tier['id']);
        $this->assertSame('1606', $tier['property_id']);
        $this->assertSame('2026-06-01', $tier['start_date']);
        $this->assertSame('2026-08-31', $tier['end_date']);
        $this->assertSame(450000.0, $tier['nightly_rate_cop']);
        $this->assertSame(450000.0, $tier['price_per_night']);
        $this->assertSame(3, $tier['minimum_stay']);
        $this->assertSame(3, $tier['min_stay']);
        $this->assertSame(50000.0, $tier['cleaning_fee']);
        $this->assertSame('Summer High', $tier['season_name']);
        $this->assertSame('Admin User', $tier['created_by_name']);

        // toArray
        $array = $tier->toArray();
        $this->assertSame(42, $array['id']);
        $this->assertSame(450000.0, $array['price_per_night']);
        $this->assertSame(3, $array['min_stay']);

        // Immutability on ArrayAccess mutation
        $this->expectException(BadMethodCallException::class);
        $tier['season_name'] = 'Winter Low';
    }

    public function testRateTierOverlapsMethod(): void
    {
        $tier = new RateTier('1606', '2026-06-01', '2026-08-31', 400000.0, 2);

        $this->assertTrue($tier->overlaps('2026-06-01', '2026-08-31'));
        $this->assertTrue($tier->overlaps('2026-05-15', '2026-06-15'));
        $this->assertTrue($tier->overlaps('2026-08-15', '2026-09-15'));
        $this->assertTrue($tier->overlaps('2026-07-01', '2026-07-31'));
        $this->assertFalse($tier->overlaps('2026-01-01', '2026-05-31'));
        $this->assertFalse($tier->overlaps('2026-09-01', '2026-12-31'));
    }

    public function testInMemoryRateRepositoryCrudAndOverlap(): void
    {
        $repo = new InMemoryRateRepository();

        $tier = new RateTier(
            propertyId: '1606',
            startDate: '2026-07-01',
            endDate: '2026-07-31',
            nightlyRateCop: 500000.0,
            minimumStay: 3,
            seasonName: 'Peak July'
        );

        $saved = $repo->save($tier, adminUserId: 5);
        $this->assertNotNull($saved->id);
        $this->assertSame(1, $saved->id);
        $this->assertSame(5, $saved->createdBy);

        $fetched = $repo->findById(1);
        $this->assertNotNull($fetched);
        $this->assertSame('Peak July', $fetched->seasonName);

        // Overlap detection
        $this->assertTrue($repo->hasOverlap('1606', '2026-07-10', '2026-07-20'));
        $this->assertFalse($repo->hasOverlap('1606', '2026-07-10', '2026-07-20', excludeId: 1));
        $this->assertFalse($repo->hasOverlap('1606', '2026-08-01', '2026-08-10'));
        $this->assertFalse($repo->hasOverlap('1707', '2026-07-10', '2026-07-20'));

        // Update existing tier
        $updateTier = new RateTier(
            propertyId: '1606',
            startDate: '2026-07-01',
            endDate: '2026-07-31',
            nightlyRateCop: 550000.0,
            minimumStay: 3,
            id: 1,
            seasonName: 'Updated July Peak'
        );
        $updated = $repo->save($updateTier);
        $this->assertSame(1, $updated->id);
        $this->assertSame('Updated July Peak', $updated->seasonName);
        $this->assertSame(550000.0, $updated->nightlyRateCop);

        // Delete tier
        $this->assertTrue($repo->delete(1));
        $this->assertNull($repo->findById(1));
        $this->assertFalse($repo->delete(999));
    }

    public function testInMemoryRateRepositoryAnnualFilterAndGaps(): void
    {
        $repo = new InMemoryRateRepository([
            new RateTier('1606', '2025-12-15', '2026-01-15', 300000.0, 2, id: 1, seasonName: 'Cross New Year'),
            new RateTier('1606', '2026-06-01', '2026-06-30', 400000.0, 2, id: 2, seasonName: 'June'),
            new RateTier('1606', '2027-01-01', '2027-01-31', 450000.0, 2, id: 3, seasonName: '2027 January'),
        ]);

        $tiers2026 = $repo->getRatesForProperty('1606', 2026);
        $this->assertCount(2, $tiers2026);
        $this->assertSame('Cross New Year', $tiers2026[0]->seasonName);
        $this->assertSame('June', $tiers2026[1]->seasonName);

        $gaps = $repo->detectGaps('1606', 2026);
        $this->assertNotEmpty($gaps);
        // The first gap should start right after 2026-01-15: 2026-01-16 to 2026-05-31
        $this->assertSame('2026-01-16', $gaps[0]['start_date']);
        $this->assertSame('2026-05-31', $gaps[0]['end_date']);
    }

    public function testInMemoryRateRepositorySeedFromCsv(): void
    {
        $repo = new InMemoryRateRepository();
        $tempCsv = tempnam(sys_get_temp_dir(), 'test_rates_');
        file_put_contents(
            $tempCsv,
            "property_id,start_date,end_date,nightly_rate_cop,minimum_stay\n" .
            "1606,2026-01-01,2026-03-31,350000,2\n" .
            "1606,2026-04-01,2026-06-30,420000,2\n"
        );

        $seeded = $repo->seedFromCsv($tempCsv, createdBy: 10);
        unlink($tempCsv);

        $this->assertSame(2, $seeded);
        $tiers = $repo->getRatesForProperty('1606');
        $this->assertCount(2, $tiers);
        $this->assertSame(10, $tiers[0]->createdBy);
        $this->assertSame(350000.0, $tiers[0]->nightlyRateCop);
    }

    public function testPdoRateRepositoryCrudAndAuthorJoin(): void
    {
        $pdo = $this->createSqlitePdo();
        $pdo->exec("INSERT INTO admin_users (id, username, name) VALUES (7, 'alice', 'Alice Smith')");

        $repo = new PdoRateRepository($pdo);

        // 1. Save new tier
        $tier = new RateTier(
            propertyId: '1606',
            startDate: '2026-10-01',
            endDate: '2026-10-31',
            nightlyRateCop: 480000.0,
            minimumStay: 3,
            seasonName: 'October Festival',
            cleaningFeeCop: 60000.0,
            createdBy: 7
        );
        $saved = $repo->save($tier, adminUserId: 7);
        $this->assertNotNull($saved->id);
        $id = (int) $saved->id;

        // 2. Find by ID with joined author name
        $fetched = $repo->findById($id);
        $this->assertNotNull($fetched);
        $this->assertSame('October Festival', $fetched->seasonName);
        $this->assertSame(7, $fetched->createdBy);
        $this->assertSame('Alice Smith', $fetched->createdByName);
        $this->assertSame(480000.0, $fetched->nightlyRateCop);

        // 3. Update existing tier
        $updateTier = new RateTier(
            propertyId: '1606',
            startDate: '2026-10-01',
            endDate: '2026-10-31',
            nightlyRateCop: 520000.0,
            minimumStay: 4,
            id: $id,
            seasonName: 'Updated October Festival',
            cleaningFeeCop: 65000.0
        );
        $updated = $repo->save($updateTier);
        $this->assertSame($id, $updated->id);

        $fetchedAgain = $repo->findById($id);
        $this->assertNotNull($fetchedAgain);
        $this->assertSame('Updated October Festival', $fetchedAgain->seasonName);
        $this->assertSame(520000.0, $fetchedAgain->nightlyRateCop);
        $this->assertSame(4, $fetchedAgain->minimumStay);

        // 4. Overlap detection
        $this->assertTrue($repo->hasOverlap('1606', '2026-10-15', '2026-10-25'));
        $this->assertFalse($repo->hasOverlap('1606', '2026-10-15', '2026-10-25', excludeId: $id));
        $this->assertFalse($repo->hasOverlap('1606', '2026-11-01', '2026-11-15'));

        // 5. Delete
        $this->assertTrue($repo->delete($id));
        $this->assertNull($repo->findById($id));
        $this->assertFalse($repo->delete($id));
    }

    public function testPdoRateRepositoryAnnualFilterAndGaps(): void
    {
        $pdo = $this->createSqlitePdo();
        $pdo->exec("
            INSERT INTO property_rates (property_id, start_date, end_date, season_name, price_per_night, min_stay)
            VALUES ('1606', '2026-01-01', '2026-05-31', 'Low Season', 350000, 2),
                   ('1606', '2026-06-01', '2026-08-31', 'High Season', 500000, 3)
        ");

        $repo = new PdoRateRepository($pdo);
        $tiers = $repo->getRatesForProperty('1606', 2026);
        $this->assertCount(2, $tiers);

        $gaps = $repo->detectGaps('1606', 2026);
        $this->assertCount(1, $gaps);
        $this->assertSame('2026-09-01', $gaps[0]['start_date']);
        $this->assertSame('2026-12-31', $gaps[0]['end_date']);
        $this->assertSame(122, $gaps[0]['nights']);
    }

    public function testQuoteEngineSeamlesslyWorksWithRateRepository(): void
    {
        $repo = new InMemoryRateRepository([
            new RateTier('1606', '2026-07-01', '2026-07-31', 600000.0, 3),
        ]);

        $config = PropertyRatesConfig::createDefault();
        $engine = new QuoteEngine(rateSource: $repo, config: $config);

        $quote = $engine->quote('1606', '2026-07-10', '2026-07-15');
        $this->assertTrue($quote->isValid());
        $this->assertSame(5, $quote->nightsCount);
        $this->assertSame(3000000.0, $quote->accommodationTotalCop);
    }

    public function testPropertyRateStatusActiveSeasonalTierAndRevert(): void
    {
        $pdo = $this->createSqlitePdo();
        $pdo->exec("
            INSERT INTO property_rates (property_id, start_date, end_date, season_name, price_per_night, min_stay)
            VALUES ('1606', '2026-06-01', '2026-08-31', 'Summer High Season', 500000, 3),
                   ('1606', '2026-12-15', '2027-01-15', 'Holiday Season', 650000, 4)
        ");

        $repo = new PdoRateRepository($pdo);

        // Case 1: Today is in Summer High Season (2026-07-15)
        $statusActive = $repo->getPropertyRateStatus('1606', new \DateTimeImmutable('2026-07-15'));
        $this->assertSame(500000.0, $statusActive->currentNightlyRate);
        $this->assertTrue($statusActive->isSeasonalTierActive);
        $this->assertSame('Summer High Season', $statusActive->activeTierName);
        $this->assertSame('2026-08-31', $statusActive->activeTierEndDate);
        $this->assertSame(650000.0, $statusActive->nextTierRate);
        $this->assertSame('Holiday Season', $statusActive->nextTierName);
        $this->assertSame('2026-12-15', $statusActive->nextTierStartDate);

        // Case 2: Today is in baseline gap (2026-10-01)
        $statusBaseline = $repo->getPropertyRateStatus('1606', new \DateTimeImmutable('2026-10-01'));
        $this->assertSame(350000.0, $statusBaseline->currentNightlyRate); // default baseline for 1606
        $this->assertFalse($statusBaseline->isSeasonalTierActive);
        $this->assertSame('Baseline Rate', $statusBaseline->activeTierName);
        $this->assertNull($statusBaseline->activeTierEndDate);
        $this->assertSame(650000.0, $statusBaseline->nextTierRate);
        $this->assertSame('Holiday Season', $statusBaseline->nextTierName);
        $this->assertSame('2026-12-15', $statusBaseline->nextTierStartDate);
    }
}
