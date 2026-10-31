<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Finance;

use DateTimeImmutable;
use InvalidArgumentException;
use OceanViewFlats\Domain\Database\MigrationRunner;
use OceanViewFlats\Domain\Finance\ColombianStatutoryCalendar;
use OceanViewFlats\Domain\Finance\InMemoryParafiscalSettlementRepository;
use OceanViewFlats\Domain\Finance\ParafiscalSettlement;
use OceanViewFlats\Domain\Finance\ParafiscalSettlementRepositoryInterface;
use OceanViewFlats\Domain\Finance\PdoParafiscalSettlementRepository;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ColombianStatutoryCalendarTest extends TestCase
{
    public function testComputesColombianHolidaysAndStatutoryQuarterDueDatesDeterministically(): void
    {
        // Verify 2026 Colombian statutory holidays in filing months (Jan, Apr, Jul, Oct)
        $holidays2026 = ColombianStatutoryCalendar::getHolidaysForYear(2026);

        // Fixed holidays
        $this->assertArrayHasKey('2026-01-01', $holidays2026);
        $this->assertArrayHasKey('2026-05-01', $holidays2026);
        $this->assertArrayHasKey('2026-07-20', $holidays2026);
        $this->assertArrayHasKey('2026-08-07', $holidays2026);
        $this->assertArrayHasKey('2026-12-08', $holidays2026);
        $this->assertArrayHasKey('2026-12-25', $holidays2026);

        // Emiliani moved holidays in 2026:
        // - Jan 6, 2026 is Tuesday -> moved to Monday Jan 12, 2026
        // - Jun 29, 2026 is Monday -> stays Monday Jun 29, 2026
        // - Oct 12, 2026 is Monday -> stays Monday Oct 12, 2026
        $this->assertArrayHasKey('2026-01-12', $holidays2026);
        $this->assertArrayHasKey('2026-06-29', $holidays2026);
        $this->assertArrayHasKey('2026-10-12', $holidays2026);

        // Easter-relative holidays in 2026 (Easter Sunday is April 5, 2026):
        // - Holy Thursday: April 2, 2026
        // - Good Friday: April 3, 2026
        $this->assertArrayHasKey('2026-04-02', $holidays2026);
        $this->assertArrayHasKey('2026-04-03', $holidays2026);

        // Statutory due dates (20th Colombian business day following quarter close):
        // 1. 2026-Q1 -> April 2026:
        //    Apr 2 (Holy Thu) & Apr 3 (Good Fri) are holidays; weekends skipped => 20th business day is 2026-04-30
        $this->assertSame('2026-04-30', ColombianStatutoryCalendar::calculateQuarterDueDate('2026-Q1'));

        // 2. 2026-Q2 -> July 2026:
        //    Jul 20 (Monday, Independence Day) is a holiday; weekends skipped => 20th business day is 2026-07-29
        $this->assertSame('2026-07-29', ColombianStatutoryCalendar::calculateQuarterDueDate('2026-Q2'));

        // 3. 2026-Q3 -> October 2026:
        //    Oct 12 (Monday, Día de la Raza) is a holiday; weekends skipped => 20th business day is 2026-10-29
        $this->assertSame('2026-10-29', ColombianStatutoryCalendar::calculateQuarterDueDate('2026-Q3'));

        // 4. 2026-Q4 -> January 2027 (Y+1):
        //    Jan 1, 2027 (Friday, New Year) & Jan 11, 2027 (Monday, Reyes Magos Emiliani from Wed Jan 6) are holidays.
        //    Because January 2027 has 19 business days total (Jan 30-31 are Sat-Sun), the 20th business day starting
        //    from January 1, 2027 falls on Monday February 1, 2027.
        $this->assertSame('2027-02-01', ColombianStatutoryCalendar::calculateQuarterDueDate('2026-Q4'));

        $due2025Q4 = ColombianStatutoryCalendar::calculateQuarterDueDate('2025-Q4');
        // In Jan 2026:
        // Jan 1 (Thu): Holiday
        // Jan 2 (Fri): 1
        // Jan 5-9 (Mon-Fri): 5 (total 6)
        // Jan 12 (Mon): Holiday (Reyes Magos)
        // Jan 13-16 (Tue-Fri): 4 (total 10)
        // Jan 19-23 (Mon-Fri): 5 (total 15)
        // Jan 26-30 (Mon-Fri): 5 (total 20) -> 2026-01-30!
        $this->assertSame('2026-01-30', $due2025Q4);

        // Also test late-June Emiliani spillover into July:
        // In 2027, Jun 29 is Tuesday -> Emiliani moves San Pedro y San Pablo to Monday July 5, 2027!
        // Plus Jul 20, 2027 is Tuesday (Independence Day).
        // Let's verify July 2027 has both 2027-07-05 and 2027-07-20 as holidays:
        $holidays2027 = ColombianStatutoryCalendar::getHolidaysForYear(2027);
        $this->assertArrayHasKey('2027-07-05', $holidays2027);
        $this->assertArrayHasKey('2027-07-20', $holidays2027);
        // July 2027 business days:
        // Jul 1-2 (Thu-Fri): 2
        // Jul 5 (Mon): Holiday (San Pedro y San Pablo)
        // Jul 6-9 (Tue-Fri): 4 (total 6)
        // Jul 12-16 (Mon-Fri): 5 (total 11)
        // Jul 19 (Mon): 1 (total 12)
        // Jul 20 (Tue): Holiday (Día de la Independencia)
        // Jul 21-23 (Wed-Fri): 3 (total 15)
        // Jul 26-30 (Mon-Fri): 5 (total 20) -> 2027-07-30!
        $this->assertSame('2027-07-30', ColombianStatutoryCalendar::calculateQuarterDueDate('2027-Q2'));
    }

    public function testQuarterKeyParsingAndContiguousRangeGeneration(): void
    {
        $q1 = ColombianStatutoryCalendar::parseQuarterKey('2026-Q1');
        $this->assertSame(2026, $q1['year']);
        $this->assertSame(1, $q1['quarter']);
        $this->assertSame('2026-01-01', $q1['period_start']);
        $this->assertSame('2026-04-01', $q1['period_end']);

        $q4 = ColombianStatutoryCalendar::parseQuarterKey('2026-Q4');
        $this->assertSame('2026-10-01', $q4['period_start']);
        $this->assertSame('2027-01-01', $q4['period_end']);

        $this->assertSame('2025-Q3', ColombianStatutoryCalendar::quarterKeyForDate('2025-08-19'));
        $this->assertSame('2026-Q4', ColombianStatutoryCalendar::quarterKeyForDate('2026-10-17'));

        $range = ColombianStatutoryCalendar::generateQuarterKeysBetween('2025-Q3', '2026-Q2');
        $this->assertSame(['2025-Q3', '2025-Q4', '2026-Q1', '2026-Q2'], $range);
    }

    public function testInvalidQuarterKeyThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ColombianStatutoryCalendar::parseQuarterKey('2026-Q5');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function repositoryAdapterProvider(): array
    {
        return [
            'in_memory' => ['in_memory'],
            'pdo_sqlite' => ['pdo'],
        ];
    }

    #[DataProvider('repositoryAdapterProvider')]
    public function testParafiscalSettlementRepositoryCrudAndUniquePropertyPeriodUpsert(string $mode): void
    {
        $repo = $this->createRepository($mode);

        $initial = new ParafiscalSettlement(
            propertyId: '1606',
            periodKey: '2026-Q1',
            periodStart: '2026-01-01',
            periodEnd: '2026-04-01',
            dueDate: '2026-04-30',
            rntNumber: '270413',
            status: ParafiscalSettlement::STATUS_SETTLED,
            settledTaxableBase: 4000000.0,
            settledCalculatedAmount: 10000.0,
            settledAmount: 10000.0,
            paymentDate: '2026-04-15',
            referenceCode: 'FONTUR-2026-Q1-1606',
            notes: 'Filed on time',
            operationalExpenseId: null,
            settledBy: 1,
            settledAt: new DateTimeImmutable('2026-04-15 14:30:00')
        );

        $saved = $repo->save($initial);
        $this->assertNotNull($saved->id);
        $this->assertSame('1606', $saved->propertyId);
        $this->assertSame('270413', $saved->rntNumber);
        $this->assertSame('2026-Q1', $saved->periodKey);
        $this->assertTrue($saved->isSettled());
        $this->assertSame(10000.0, $saved->settledAmount);
        $this->assertSame('FONTUR-2026-Q1-1606', $saved->referenceCode);

        // Lookup by ID, UID, and (propertyId, periodKey)
        $byId = $repo->findById($saved->id);
        $this->assertNotNull($byId);
        $this->assertSame($saved->settlementUid, $byId->settlementUid);

        $byUid = $repo->findByUid($saved->settlementUid);
        $this->assertNotNull($byUid);
        $this->assertSame($saved->id, $byUid->id);

        $byPropPeriod = $repo->findByPropertyAndPeriod('1606', '2026-Q1');
        $this->assertNotNull($byPropPeriod);
        $this->assertSame($saved->id, $byPropPeriod->id);

        // Upsert on same (property_id, period_key) updates in place without creating duplicate row
        $updatedInput = new ParafiscalSettlement(
            propertyId: '1606',
            periodKey: '2026-Q1',
            periodStart: '2026-01-01',
            periodEnd: '2026-04-01',
            dueDate: '2026-04-30',
            rntNumber: '270413',
            status: ParafiscalSettlement::STATUS_SETTLED,
            settledTaxableBase: 4000000.0,
            settledCalculatedAmount: 10000.0,
            settledAmount: 10500.0,
            paymentDate: '2026-04-16',
            referenceCode: 'FONTUR-2026-Q1-1606-REV',
            notes: 'Adjusted rounding',
            operationalExpenseId: 42,
            settledBy: 1,
            settledAt: new DateTimeImmutable('2026-04-16 10:00:00')
        );
        $upserted = $repo->save($updatedInput);
        $this->assertSame($saved->id, $upserted->id);
        $this->assertSame(10500.0, $upserted->settledAmount);
        $this->assertSame('FONTUR-2026-Q1-1606-REV', $upserted->referenceCode);
        $this->assertSame(42, $upserted->operationalExpenseId);

        $byExpenseId = $repo->findByOperationalExpenseId(42);
        $this->assertNotNull($byExpenseId);
        $this->assertSame($saved->id, $byExpenseId->id);

        // Save a second property row for 1707
        $repo->save(new ParafiscalSettlement(
            propertyId: '1707',
            periodKey: '2026-Q1',
            periodStart: '2026-01-01',
            periodEnd: '2026-04-01',
            dueDate: '2026-04-30'
        ));

        $all = $repo->listAll('all');
        $this->assertCount(2, $all);
        $this->assertCount(1, $repo->listAll('1606'));
        $this->assertCount(1, $repo->listAll('1707'));

        $this->assertTrue($repo->delete($saved->id));
        $this->assertNull($repo->findById($saved->id));
    }

    private function createRepository(string $mode): ParafiscalSettlementRepositoryInterface
    {
        if ($mode === 'in_memory') {
            return new InMemoryParafiscalSettlementRepository();
        }

        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        MigrationRunner::run($pdo, 'memory_db');

        $pdo->exec("
            INSERT INTO admin_users (id, email, password_hash, name, role, is_active)
            VALUES (1, 'admin@oceanviewflats.com', 'hash', 'Manuel Admin', 'admin', 1);
            INSERT INTO operational_expenses (id, expense_uid, property_id, category, title, amount, expense_date)
            VALUES (42, 'exp_test_42', '1606', 'taxes_insurance', 'FONTUR 2026-Q1', 10500, '2026-04-16');
        ");

        return new PdoParafiscalSettlementRepository($pdo);
    }
}
