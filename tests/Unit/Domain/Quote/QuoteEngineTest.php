<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Quote;

use InvalidArgumentException;
use OceanViewFlats\Domain\Quote\CsvRateSource;
use OceanViewFlats\Domain\Quote\InMemoryRateSource;
use OceanViewFlats\Domain\Quote\PropertyRatesConfig;
use OceanViewFlats\Domain\Quote\QuoteEngine;
use OceanViewFlats\Domain\Quote\RateTier;
use PHPUnit\Framework\TestCase;

final class QuoteEngineTest extends TestCase
{
    private InMemoryRateSource $rateSource;
    private PropertyRatesConfig $config;
    private QuoteEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateSource = new InMemoryRateSource([
            new RateTier(
                propertyId: '1606',
                startDate: '2026-01-01',
                endDate: '2026-12-14',
                nightlyRateCop: 350000.0,
                minimumStay: 2
            ),
            new RateTier(
                propertyId: '1606',
                startDate: '2026-12-15',
                endDate: '2027-01-15',
                nightlyRateCop: 550000.0,
                minimumStay: 4
            ),
            new RateTier(
                propertyId: '1707',
                startDate: '2026-01-01',
                endDate: '2026-12-31',
                nightlyRateCop: 450000.0,
                minimumStay: 2
            ),
        ]);

        $this->config = PropertyRatesConfig::createDefault();
        $this->engine = new QuoteEngine($this->rateSource, $this->config);
    }

    public function testStandardSingleTierQuoteComputesCorrectly(): void
    {
        $quote = $this->engine->quote('1606', '2026-06-01', '2026-06-04');

        $this->assertTrue($quote->isValid());
        $this->assertNull($quote->violationReason());
        $this->assertSame(3, $quote->nightsCount());
        $this->assertSame(2, $quote->minimumStayRequired());
        $this->assertSame(1050000.0, $quote->accommodationTotalCop());
        $this->assertSame(80000.0, $quote->cleaningFeeCop());
        $this->assertSame(1130000.0, $quote->totalCop());

        $this->assertCount(3, $quote->nights);
        $this->assertSame('2026-06-01', $quote->nights[0]['date']);
        $this->assertSame(350000.0, $quote->nights[0]['rateCop']);
        $this->assertSame('2026-01-01_2026-12-14', $quote->nights[0]['tier']);
    }

    public function testMultiTierSpanEnforcesStrictMaximumMinimumStayWhenSatisfied(): void
    {
        // 4 nights: 2 nights standard (Dec 13, 14) + 2 nights peak holiday (Dec 15, 16)
        $quote = $this->engine->quote('1606', '2026-12-13', '2026-12-17');

        $this->assertTrue($quote->isValid());
        $this->assertNull($quote->violationReason());
        $this->assertSame(4, $quote->nightsCount());
        $this->assertSame(4, $quote->minimumStayRequired()); // Strict max(2, 4) = 4 per ADR 0004

        // 2 nights @ 350k + 2 nights @ 550k = 700k + 1100k = 1,800,000 COP
        $this->assertSame(1800000.0, $quote->accommodationTotalCop());
        $this->assertSame(80000.0, $quote->cleaningFeeCop());
        $this->assertSame(1880000.0, $quote->totalCop());
    }

    public function testMultiTierSpanViolatingStrictMaximumMinimumStayFlagsInvalid(): void
    {
        // 2 nights crossing boundary: Dec 14 (standard) and Dec 15 (peak holiday requiring 4 nights)
        $quote = $this->engine->quote('1606', '2026-12-14', '2026-12-16');

        $this->assertFalse($quote->isValid());
        $this->assertSame('min_stay', $quote->violationReason());
        $this->assertSame(2, $quote->nightsCount());
        $this->assertSame(4, $quote->minimumStayRequired());

        // Rates are still computed accurately for preview display
        // 1 night @ 350k + 1 night @ 550k = 900,000 COP
        $this->assertSame(900000.0, $quote->accommodationTotalCop());
        $this->assertSame(80000.0, $quote->cleaningFeeCop());
        $this->assertSame(980000.0, $quote->totalCop());
    }

    public function testUnlistedDatesFallbackToPropertyDefaultBaseRate(): void
    {
        // Date range in 2028 with no tier in rateSource
        $quote = $this->engine->quote('1707', '2028-02-01', '2028-02-03');

        $this->assertTrue($quote->isValid());
        $this->assertSame(2, $quote->nightsCount());
        $this->assertSame(2, $quote->minimumStayRequired());

        // 1707 default base rate is 450,000 COP per night
        $this->assertSame(900000.0, $quote->accommodationTotalCop());
        $this->assertSame(100000.0, $quote->cleaningFeeCop());
        $this->assertSame(1000000.0, $quote->totalCop());

        $this->assertNull($quote->nights[0]['tier']);
        $this->assertSame(450000.0, $quote->nights[0]['rateCop']);
    }

    public function testPropertyFeeDistinctionBetween1707And1606(): void
    {
        $quote1707 = $this->engine->quote('1707', '2026-08-01', '2026-08-03');
        $quote1606 = $this->engine->quote('1606', '2026-08-01', '2026-08-03');

        // Cleaning fee differs by property
        $this->assertSame(100000.0, $quote1707->cleaningFeeCop());
        $this->assertSame(80000.0, $quote1606->cleaningFeeCop());
    }

    public function testUnknownPropertyThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown property identifier: '9999'");

        $this->engine->quote('9999', '2026-06-01', '2026-06-03');
    }

    public function testCheckOutBeforeCheckInThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Check-out date (2026-06-01) must be after check-in date (2026-06-03)");

        $this->engine->quote('1606', '2026-06-03', '2026-06-01');
    }

    public function testCheckOutSameDayAsCheckInThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Check-out date (2026-06-01) must be after check-in date (2026-06-01)");

        $this->engine->quote('1606', '2026-06-01', '2026-06-01');
    }

    public function testInvalidDateFormatThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Check-in date '01/06/2026' must be a valid date in Y-m-d format");

        $this->engine->quote('1606', '01/06/2026', '2026-06-03');
    }

    public function testCsvRateSourceWithRealPricesCsv(): void
    {
        $defaultEngine = QuoteEngine::createDefault();

        // Check property 1606 against real CSV
        $quote1606 = $defaultEngine->quote('1606', '2026-07-01', '2026-07-04');
        $this->assertTrue($quote1606->isValid());
        $this->assertSame(3, $quote1606->nightsCount());
        $this->assertSame(1050000.0, $quote1606->accommodationTotalCop());

        // Check property 1707 against real CSV
        $quote1707 = $defaultEngine->quote('1707', '2026-07-01', '2026-07-04');
        $this->assertTrue($quote1707->isValid());
        $this->assertSame(3, $quote1707->nightsCount());
        $this->assertSame(1350000.0, $quote1707->accommodationTotalCop());
    }

    public function testSerializationMatchesExpectedStructure(): void
    {
        $quote = $this->engine->quote('1606', '2026-06-01', '2026-06-03');
        $array = $quote->toArray();

        $this->assertArrayHasKey('property_id', $array);
        $this->assertArrayHasKey('check_in', $array);
        $this->assertArrayHasKey('check_out', $array);
        $this->assertArrayHasKey('nights_count', $array);
        $this->assertArrayHasKey('nights', $array);
        $this->assertArrayHasKey('accommodation_total_cop', $array);
        $this->assertArrayHasKey('cleaning_fee_cop', $array);
        $this->assertArrayNotHasKey('resort_fee_cop', $array);
        $this->assertArrayHasKey('total_cop', $array);
        $this->assertArrayHasKey('minimum_stay_required', $array);
        $this->assertArrayHasKey('is_valid', $array);
        $this->assertArrayHasKey('violation_reason', $array);

        $json = json_encode($quote);
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertEquals($array, $decoded);
    }

    public function testLongStayAccumulatesNightsLinearly(): void
    {
        // 30 nights in standard season
        $quote = $this->engine->quote('1606', '2026-06-01', '2026-07-01');

        $this->assertTrue($quote->isValid());
        $this->assertSame(30, $quote->nightsCount());
        $this->assertSame(30 * 350000.0, $quote->accommodationTotalCop());
        $this->assertSame(30 * 350000.0 + 80000.0, $quote->totalCop());
    }

    public function testCustomPropertyRatesConfigCanOverrideFeesAndBaselines(): void
    {
        $customConfig = new PropertyRatesConfig([
            '1707' => [
                'default_rate' => 500000.0,
                'cleaning_fee' => 120000.0,
                'default_min_stay' => 3,
            ],
        ]);

        $customEngine = new QuoteEngine(new InMemoryRateSource([]), $customConfig);
        $quote = $customEngine->quote('1707', '2028-05-01', '2028-05-04');

        $this->assertTrue($quote->isValid());
        $this->assertSame(3, $quote->nightsCount());
        $this->assertSame(3, $quote->minimumStayRequired());
        $this->assertSame(1500000.0, $quote->accommodationTotalCop());
        $this->assertSame(120000.0, $quote->cleaningFeeCop());
        $this->assertSame(1620000.0, $quote->totalCop());
    }

    public function testRateTierCoversDateBoundaryInclusivity(): void
    {
        $tier = new RateTier(
            propertyId: '1606',
            startDate: '2026-05-10',
            endDate: '2026-05-20',
            nightlyRateCop: 400000.0,
            minimumStay: 3
        );

        $this->assertFalse($tier->coversDate('2026-05-09'));
        $this->assertTrue($tier->coversDate('2026-05-10'));
        $this->assertTrue($tier->coversDate('2026-05-15'));
        $this->assertTrue($tier->coversDate('2026-05-20'));
        $this->assertFalse($tier->coversDate('2026-05-21'));
    }

    public function testGetRateSummaryComputesActivePropertyRatesAndTiers(): void
    {
        $now = new \DateTimeImmutable('2026-12-20');
        $summary = $this->engine->getRateSummary(null, $now);

        $this->assertArrayHasKey('properties', $summary);
        $this->assertArrayHasKey('tiers', $summary);

        // On 2026-12-20, 1606 is in the 550k tier (2026-12-15 to 2027-01-15)
        $this->assertSame(550000, $summary['properties']['1606']['current_nightly_rate_cop']);
        $this->assertSame(350000, $summary['properties']['1606']['default_nightly_rate_cop']);
        $this->assertSame(80000, $summary['properties']['1606']['cleaning_fee_cop']);
        $this->assertTrue($summary['properties']['1606']['is_seasonal_tier_active']);
        $this->assertSame('2027-01-15', $summary['properties']['1606']['active_tier_end_date']);

        // On 2026-12-20, 1707 is in the 450k tier (2026-01-01 to 2026-12-31)
        $this->assertSame(450000, $summary['properties']['1707']['current_nightly_rate_cop']);
        $this->assertSame(450000, $summary['properties']['1707']['default_nightly_rate_cop']);
        $this->assertSame(100000, $summary['properties']['1707']['cleaning_fee_cop']);

        $this->assertCount(3, $summary['tiers']);

        // Filtered by property 1707
        $summary1707 = $this->engine->getRateSummary('1707', $now);
        $this->assertCount(1, $summary1707['tiers']);
        $this->assertSame('1707', $summary1707['tiers'][0]['property_id']);
    }
}
