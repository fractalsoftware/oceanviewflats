<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Finance;

use BadMethodCallException;
use DateTimeImmutable;
use InvalidArgumentException;
use OceanViewFlats\Domain\Database\MigrationRunner;
use OceanViewFlats\Domain\Finance\ExpenseCategory;
use OceanViewFlats\Domain\Finance\InMemoryOperationalExpenseRepository;
use OceanViewFlats\Domain\Finance\OperationalExpense;
use OceanViewFlats\Domain\Finance\OperationalExpenseRepositoryInterface;
use OceanViewFlats\Domain\Finance\PdoOperationalExpenseRepository;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OperationalExpenseRepositoryTest extends TestCase
{
    public function testExpenseCategoryTaxonomyAndMetadata(): void
    {
        $cases = ExpenseCategory::cases();
        $this->assertCount(8, $cases);

        $expected = [
            'cleaning' => 'Cleaning & Turnover',
            'hoa_admin' => 'Condominium Administration (HOA)',
            'utilities' => 'Utilities & Telecom',
            'maintenance' => 'Maintenance & Repairs',
            'supplies' => 'Guest Supplies & Consumables',
            'platform_fees' => 'Payment Gateway & Platform Fees',
            'taxes_insurance' => 'Taxes, Insurance & Statutory',
            'other' => 'Other Operational Expense',
        ];

        foreach ($expected as $value => $label) {
            $cat = ExpenseCategory::from($value);
            $this->assertSame($label, $cat->label());
            $this->assertNotEmpty($cat->badgeClass());
            $this->assertNotEmpty($cat->barClass());
        }
    }

    public function testOperationalExpenseEntityInvariantsAndAllocation(): void
    {
        $expense = new OperationalExpense(
            propertyId: 'shared',
            category: ExpenseCategory::SUPPLIES,
            title: 'Bulk Linens & Bath Amenities',
            amount: 640000.00,
            expenseDate: '2026-10-09',
            vendorName: 'Makro Santa Marta',
            referenceCode: 'FAC-99201',
            notes: 'Restock for Q4',
            createdBy: 1,
            createdByName: 'Manuel Admin',
            createdAt: '2026-10-09 10:00:00',
            updatedAt: new DateTimeImmutable('2026-10-09 10:05:00')
        );

        $this->assertStringStartsWith('exp_', $expense->expenseUid);
        $this->assertSame('shared', $expense->propertyId);
        $this->assertSame(ExpenseCategory::SUPPLIES, $expense->category);
        $this->assertSame(640000.0, $expense->amount);
        $this->assertSame(1.0, $expense->allocationFactorForProperty('all'));
        $this->assertSame(640000.0, $expense->allocatedAmountForProperty('all'));
        $this->assertSame(0.5, $expense->allocationFactorForProperty('1606'));
        $this->assertSame(320000.0, $expense->allocatedAmountForProperty('1606'));
        $this->assertSame(0.5, $expense->allocationFactorForProperty('1707'));
        $this->assertSame(320000.0, $expense->allocatedAmountForProperty('1707'));

        $arr = $expense->toArray();
        $rehydrated = OperationalExpense::fromArray($arr);
        $this->assertSame($expense->expenseUid, $rehydrated->expenseUid);
        $this->assertSame($expense->title, $rehydrated->title);
        $this->assertSame($expense->amount, $rehydrated['amount']);
        $this->assertTrue(isset($rehydrated['title']));
    }

    public function testOperationalExpenseIsImmutableViaArrayAccess(): void
    {
        $expense = new OperationalExpense(
            propertyId: '1606',
            category: 'cleaning',
            title: 'Turnover Cleaning',
            amount: 160000,
            expenseDate: '2026-10-11'
        );

        $this->expectException(BadMethodCallException::class);
        $expense['amount'] = 200000;
    }

    #[DataProvider('invalidExpenseProvider')]
    public function testOperationalExpenseRejectsInvalidArguments(
        string $propertyId,
        string $category,
        string $title,
        float $amount,
        string $expenseDate,
        ?string $reservationUid
    ): void {
        $this->expectException(InvalidArgumentException::class);

        new OperationalExpense(
            propertyId: $propertyId,
            category: $category,
            title: $title,
            amount: $amount,
            expenseDate: $expenseDate,
            reservationUid: $reservationUid
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: float, 4: string, 5: string|null}>
     */
    public static function invalidExpenseProvider(): array
    {
        return [
            'invalid property' => ['9999', 'cleaning', 'Cleaning', 100000.0, '2026-10-10', null],
            'invalid category' => ['1606', 'invalid_cat', 'Cleaning', 100000.0, '2026-10-10', null],
            'empty title' => ['1606', 'cleaning', '   ', 100000.0, '2026-10-10', null],
            'too long title' => ['1606', 'cleaning', str_repeat('A', 161), 100000.0, '2026-10-10', null],
            'zero amount' => ['1606', 'cleaning', 'Cleaning', 0.0, '2026-10-10', null],
            'negative amount' => ['1606', 'cleaning', 'Cleaning', -50.0, '2026-10-10', null],
            'invalid date' => ['1606', 'cleaning', 'Cleaning', 100000.0, '2026-02-30', null],
            'shared with reservation uid' => ['shared', 'cleaning', 'Cleaning', 100000.0, '2026-10-10', 'ovf_12345'],
        ];
    }

    /**
     * @return array<string, array{0: callable(): OperationalExpenseRepositoryInterface}>
     */
    public static function repositoryProvider(): array
    {
        return [
            'InMemory adapter' => [
                static fn (): OperationalExpenseRepositoryInterface => new InMemoryOperationalExpenseRepository(),
            ],
            'PDO SQLite adapter' => [
                static function (): OperationalExpenseRepositoryInterface {
                    $pdo = new PDO('sqlite::memory:', null, null, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                    MigrationRunner::run($pdo, 'memory_db');
                    $pdo->exec("INSERT INTO admin_users (id, email, password_hash, name, role) VALUES (1, 'admin@test.com', 'hash', 'Manuel Admin', 'admin')");
                    return new PdoOperationalExpenseRepository($pdo);
                },
            ],
        ];
    }

    /**
     * @param callable(): OperationalExpenseRepositoryInterface $factory
     */
    #[DataProvider('repositoryProvider')]
    public function testRepositoryCrudAndPeriodFiltering(callable $factory): void
    {
        $repo = $factory();

        $exp1 = $repo->save(new OperationalExpense(
            propertyId: '1606',
            category: ExpenseCategory::HOA_ADMIN,
            title: 'Administración Oct 2026',
            amount: 720000.0,
            expenseDate: '2026-10-02',
            vendorName: 'Edificio Salguero Sunset',
            referenceCode: 'ADM-1606-10',
            createdBy: 1
        ));

        $exp2 = $repo->save(new OperationalExpense(
            propertyId: '1707',
            category: ExpenseCategory::UTILITIES,
            title: 'Energía Air-e 1707',
            amount: 490000.0,
            expenseDate: '2026-10-06',
            vendorName: 'Air-e',
            referenceCode: 'UTIL-1707-10',
            createdBy: 1
        ));

        $expShared = $repo->save(new OperationalExpense(
            propertyId: 'shared',
            category: ExpenseCategory::SUPPLIES,
            title: 'Bulk Coffee & Linens',
            amount: 640000.0,
            expenseDate: '2026-10-09',
            vendorName: 'Makro',
            referenceCode: 'FAC-99201',
            notes: 'Shared supplies',
            createdBy: 1
        ));

        $expSept = $repo->save(new OperationalExpense(
            propertyId: '1606',
            category: ExpenseCategory::CLEANING,
            title: 'September Turnover',
            amount: 160000.0,
            expenseDate: '2026-09-25',
            createdBy: 1
        ));

        $this->assertNotNull($exp1->id);
        $foundExp1 = $repo->findById($exp1->id);
        $this->assertNotNull($foundExp1);
        $this->assertSame($exp1->expenseUid, $foundExp1->expenseUid);
        $foundExp2 = $repo->findByUid($exp2->expenseUid);
        $this->assertNotNull($foundExp2);
        $this->assertSame($exp2->id, $foundExp2->id);

        // Filter October 2026 for all properties -> 3 expenses (sorted desc by date)
        $octAll = $repo->listForPeriod('2026-10-01', '2026-11-01', 'all');
        $this->assertCount(3, $octAll);
        $this->assertSame($expShared->expenseUid, $octAll[0]->expenseUid);
        $this->assertSame($exp2->expenseUid, $octAll[1]->expenseUid);
        $this->assertSame($exp1->expenseUid, $octAll[2]->expenseUid);

        // Filter October 2026 for property 1606 -> includes direct 1606 AND shared
        $oct1606 = $repo->listForPeriod('2026-10-01', '2026-11-01', '1606');
        $this->assertCount(2, $oct1606);
        $this->assertSame($expShared->expenseUid, $oct1606[0]->expenseUid);
        $this->assertSame($exp1->expenseUid, $oct1606[1]->expenseUid);

        // Filter by category
        $octUtilities = $repo->listForPeriod('2026-10-01', '2026-11-01', 'all', 'utilities');
        $this->assertCount(1, $octUtilities);
        $this->assertSame($exp2->expenseUid, $octUtilities[0]->expenseUid);

        // Filter by search
        $searchFound = $repo->listForPeriod('2026-01-01', '2027-01-01', 'all', 'all', 'FAC-99201');
        $this->assertCount(1, $searchFound);
        $this->assertSame($expShared->expenseUid, $searchFound[0]->expenseUid);

        // Update existing expense
        $updatedExp1 = $repo->save(new OperationalExpense(
            propertyId: '1606',
            category: ExpenseCategory::HOA_ADMIN,
            title: 'Administración Oct 2026 (Adjusted)',
            amount: 750000.0,
            expenseDate: '2026-10-03',
            expenseUid: $exp1->expenseUid,
            id: $exp1->id,
            vendorName: 'Edificio Salguero Sunset',
            referenceCode: 'ADM-1606-10B',
            createdBy: 1
        ));
        $this->assertSame(750000.0, $updatedExp1->amount);
        $reloadedExp1 = $repo->findById($exp1->id);
        $this->assertNotNull($reloadedExp1);
        $this->assertSame('Administración Oct 2026 (Adjusted)', $reloadedExp1->title);

        // Delete expense
        $this->assertTrue($repo->delete((int) $expSept->id));
        $this->assertNull($repo->findById((int) $expSept->id));
        $this->assertFalse($repo->delete(999999));
    }
}
