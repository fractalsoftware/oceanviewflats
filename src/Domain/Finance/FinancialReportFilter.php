<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Immutable filter specification for the Financial Report and Operational Expense engine.
 */
final class FinancialReportFilter
{
    public const VALID_PROPERTIES = ['all', '1606', '1707'];
    public const VALID_PERIODS = [
        'this_month',
        'last_month',
        'this_quarter',
        'ytd',
        'last_12_months',
        'last_year',
        'custom',
    ];
    public const VALID_COMPARISONS = ['prev_period', 'yoy'];
    public const VALID_SOURCES = ['all', 'direct_online', 'direct_manual', 'airbnb'];
    public const VALID_TABS = ['expenses', 'reservations'];

    public readonly string $propertyId;
    public readonly string $period;
    public readonly string $dateFrom;
    public readonly string $dateTo;
    public readonly string $compare;
    public readonly string $source;
    public readonly string $category;
    public readonly string $tab;
    public readonly string $search;
    public readonly ?string $explicitPeriodStart;
    public readonly ?string $explicitPeriodEnd;

    public function __construct(
        string $propertyId = 'all',
        string $period = 'this_month',
        string $dateFrom = '',
        string $dateTo = '',
        string $compare = 'prev_period',
        string $source = 'all',
        string $category = 'all',
        string $tab = 'expenses',
        string $search = '',
        ?string $periodStart = null,
        ?string $periodEnd = null
    ) {
        $normProp = trim($propertyId);
        $this->propertyId = in_array($normProp, self::VALID_PROPERTIES, true) ? $normProp : 'all';

        $normDateFrom = trim($dateFrom);
        $normDateTo = trim($dateTo);
        $validDateFrom = OperationalExpense::isValidDate($normDateFrom) ? $normDateFrom : '';
        $validDateTo = OperationalExpense::isValidDate($normDateTo) ? $normDateTo : '';

        $normPeriod = trim($period);
        if ($normPeriod === 'custom' || ($normPeriod === '' && ($validDateFrom !== '' || $validDateTo !== ''))) {
            $this->period = 'custom';
            $this->dateFrom = $validDateFrom;
            $this->dateTo = $validDateTo;
        } elseif ($normPeriod !== '' && $normPeriod !== 'this_month' && (in_array($normPeriod, self::VALID_PERIODS, true) || preg_match('/^\d{4}-\d{2}$/', $normPeriod))) {
            $this->period = $normPeriod;
            $this->dateFrom = '';
            $this->dateTo = '';
        } elseif ($validDateFrom !== '' || $validDateTo !== '') {
            $this->period = 'custom';
            $this->dateFrom = $validDateFrom;
            $this->dateTo = $validDateTo;
        } else {
            $this->period = 'this_month';
            $this->dateFrom = '';
            $this->dateTo = '';
        }

        $normCompare = trim($compare);
        $this->compare = in_array($normCompare, self::VALID_COMPARISONS, true) ? $normCompare : 'prev_period';

        $normSource = trim($source);
        $this->source = in_array($normSource, self::VALID_SOURCES, true) ? $normSource : 'all';

        $normCat = trim($category);
        $this->category = ($normCat === 'all' || ExpenseCategory::tryFrom($normCat) !== null) ? $normCat : 'all';

        $normTab = trim($tab);
        $this->tab = in_array($normTab, self::VALID_TABS, true) ? $normTab : 'expenses';

        $this->search = trim($search);

        $this->explicitPeriodStart = ($periodStart !== null && OperationalExpense::isValidDate(trim($periodStart)))
            ? trim($periodStart)
            : null;
        $this->explicitPeriodEnd = ($periodEnd !== null && OperationalExpense::isValidDate(trim($periodEnd)))
            ? trim($periodEnd)
            : null;
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function fromQuery(array $query): self
    {
        return self::fromArray($query);
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function fromArray(array $input): self
    {
        $rawPeriod = isset($input['period']) && is_string($input['period']) ? trim($input['period']) : '';
        $dateFrom = isset($input['date_from']) && is_string($input['date_from'])
            ? trim($input['date_from'])
            : (isset($input['start_date']) && is_string($input['start_date']) ? trim($input['start_date']) : '');
        $dateTo = isset($input['date_to']) && is_string($input['date_to'])
            ? trim($input['date_to'])
            : (isset($input['end_date']) && is_string($input['end_date']) ? trim($input['end_date']) : '');

        if ($rawPeriod !== '' && $rawPeriod !== 'custom' && in_array($rawPeriod, self::VALID_PERIODS, true)) {
            $dateFrom = '';
            $dateTo = '';
        } elseif ($rawPeriod === '' && ($dateFrom !== '' || $dateTo !== '')) {
            $rawPeriod = 'custom';
        }

        return new self(
            propertyId: isset($input['property_id']) && is_string($input['property_id']) ? $input['property_id'] : 'all',
            period: $rawPeriod !== '' ? $rawPeriod : 'this_month',
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            compare: isset($input['compare']) && is_string($input['compare']) ? $input['compare'] : 'prev_period',
            source: isset($input['source']) && is_string($input['source']) ? $input['source'] : 'all',
            category: isset($input['category']) && is_string($input['category']) ? $input['category'] : 'all',
            tab: isset($input['tab']) && is_string($input['tab']) ? $input['tab'] : 'expenses',
            search: isset($input['search']) && is_string($input['search']) ? $input['search'] : '',
            periodStart: isset($input['period_start']) && is_string($input['period_start']) ? $input['period_start'] : null,
            periodEnd: isset($input['period_end']) && is_string($input['period_end']) ? $input['period_end'] : null
        );
    }

    /**
     * Resolves the active half-open date window [periodStart, periodEnd) and human-readable label.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public function resolveDateWindow(?DateTimeImmutable $now = null): array
    {
        if ($this->explicitPeriodStart !== null && $this->explicitPeriodEnd !== null && $this->explicitPeriodEnd > $this->explicitPeriodStart) {
            return [
                $this->explicitPeriodStart,
                $this->explicitPeriodEnd,
                sprintf('%s to %s', $this->explicitPeriodStart, $this->explicitPeriodEnd),
            ];
        }

        $tz = new DateTimeZone('America/Bogota');
        $clock = ($now ?? new DateTimeImmutable('now', $tz))->setTimezone($tz);

        if ($this->period === 'custom') {
            $start = $this->dateFrom !== '' ? $this->dateFrom : $clock->format('Y-m-01');
            if ($this->dateTo !== '') {
                if ($this->dateTo > $start) {
                    $end = $this->dateTo;
                } elseif ($this->dateTo === $start) {
                    $end = (new DateTimeImmutable($this->dateTo, $tz))->modify('+1 day')->format('Y-m-d');
                } else {
                    // Swap if inverted
                    $end = $start;
                    $start = $this->dateTo;
                }
            } else {
                $nextMonth = $clock->modify('first day of next month')->format('Y-m-01');
                $end = $nextMonth > $start
                    ? $nextMonth
                    : (new DateTimeImmutable($start, $tz))->modify('+1 month')->format('Y-m-d');
            }

            return [
                $start,
                $end,
                sprintf('%s → %s', $start, $end),
            ];
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $this->period, $m)) {
            $monthStart = DateTimeImmutable::createFromFormat('!Y-m-d', sprintf('%s-%s-01', $m[1], $m[2]), $tz);
            if ($monthStart !== false) {
                $monthEnd = $monthStart->modify('first day of next month');
                return [
                    $monthStart->format('Y-m-d'),
                    $monthEnd->format('Y-m-d'),
                    $monthStart->format('M Y'),
                ];
            }
        }

        return match ($this->period) {
            'last_month' => (static function (DateTimeImmutable $d): array {
                $start = $d->modify('first day of last month');
                $end = $d->modify('first day of this month');
                return [$start->format('Y-m-01'), $end->format('Y-m-01'), $start->format('M Y')];
            })($clock),
            'this_quarter' => (static function (DateTimeImmutable $d): array {
                $month = (int) $d->format('n');
                $year = (int) $d->format('Y');
                $qStartMonth = (int) (floor(($month - 1) / 3) * 3 + 1);
                $start = $d->setDate($year, $qStartMonth, 1);
                $end = $start->modify('+3 months');
                $qNum = (int) ceil($month / 3);
                return [$start->format('Y-m-d'), $end->format('Y-m-d'), sprintf('Q%d %d', $qNum, $year)];
            })($clock),
            'ytd' => [
                $clock->format('Y-01-01'),
                $clock->modify('first day of next month')->format('Y-m-01'),
                sprintf('YTD %s', $clock->format('Y')),
            ],
            'last_12_months' => [
                $clock->modify('first day of this month')->modify('-11 months')->format('Y-m-01'),
                $clock->modify('first day of next month')->format('Y-m-01'),
                'Last 12 Months',
            ],
            'last_year' => [
                ((int) $clock->format('Y') - 1) . '-01-01',
                $clock->format('Y-01-01'),
                (string) ((int) $clock->format('Y') - 1),
            ],
            default => [
                $clock->format('Y-m-01'),
                $clock->modify('first day of next month')->format('Y-m-01'),
                $clock->format('M Y'),
            ],
        };
    }

    /**
     * Resolves the comparison half-open date window [compStart, compEnd) and comparison label.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public function resolveComparisonWindow(string $periodStart, string $periodEnd): array
    {
        $startDt = new DateTimeImmutable($periodStart);
        $endDt = new DateTimeImmutable($periodEnd);
        $days = max(1, (int) $startDt->diff($endDt)->days);

        if ($this->compare === 'yoy') {
            $compStart = $startDt->modify('-1 year')->format('Y-m-d');
            $compEnd = $endDt->modify('-1 year')->format('Y-m-d');
            return [$compStart, $compEnd, 'vs YoY'];
        }

        $compEnd = $periodStart;
        $compStart = $startDt->modify("-{$days} days")->format('Y-m-d');

        return [$compStart, $compEnd, 'vs prior period'];
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    public function toArray(?DateTimeImmutable $now = null, array $overrides = []): array
    {
        [$startDate, $endDate, $periodLabel] = $this->resolveDateWindow($now);

        return array_merge([
            'property_id' => $this->propertyId,
            'period' => $this->period,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'period_label' => $periodLabel,
            'compare' => $this->compare,
            'source' => $this->source,
            'category' => $this->category,
            'tab' => $this->tab,
            'search' => $this->search,
        ], $overrides);
    }
}
