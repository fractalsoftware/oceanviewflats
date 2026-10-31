<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Deterministic Colombian statutory calendar and FONTUR Parafiscal deadline calculator.
 *
 * Implements Colombian Law 51 of 1983 (Ley Emiliani) national holidays:
 * - Fixed-date holidays (Jan 1, May 1, Jul 20, Aug 7, Dec 8, Dec 25)
 * - Emiliani-moved Monday holidays (Jan 6, Mar 19, Jun 29, Aug 15, Oct 12, Nov 1, Nov 11)
 * - Easter-relative holidays (Holy Thursday, Good Friday, Ascension Monday, Corpus Christi Monday, Sacred Heart Monday)
 *
 * Under Colombian Law 1101 of 2006 & Law 2068 of 2020, quarterly FONTUR Parafiscal Contributions
 * are due on the 20th Colombian business day (Monday-Friday, excluding Colombian statutory holidays)
 * of the month following the close of each quarter:
 * - Q1 (Jan-Mar) -> 20th business day of April of the same year
 * - Q2 (Apr-Jun) -> 20th business day of July of the same year
 * - Q3 (Jul-Sep) -> 20th business day of October of the same year
 * - Q4 (Oct-Dec) -> 20th business day of January of the following year (Y+1)
 */
final class ColombianStatutoryCalendar
{
    public const STATUTORY_DUE_BUSINESS_DAY = 20;

    /** @var array<int, array<string, string>> */
    private static array $holidaysCache = [];

    /**
     * Parses a quarter key (YYYY-Q1..YYYY-Q4) into its half-open date window [periodStart, periodEnd)
     * and human-readable label.
     *
     * @return array{year: int, quarter: int, period_start: string, period_end: string, label: string}
     */
    public static function parseQuarterKey(string $periodKey): array
    {
        $normalized = strtoupper(trim($periodKey));
        if (!preg_match('/^(\d{4})-Q([1-4])$/', $normalized, $matches)) {
            throw new InvalidArgumentException(
                sprintf('Invalid Parafiscal period key "%s". Expected YYYY-Q1 through YYYY-Q4.', $periodKey)
            );
        }

        $year = (int) $matches[1];
        $quarter = (int) $matches[2];

        return match ($quarter) {
            1 => [
                'year' => $year,
                'quarter' => 1,
                'period_start' => sprintf('%04d-01-01', $year),
                'period_end' => sprintf('%04d-04-01', $year),
                'label' => sprintf('%04d Q1 (Jan–Mar)', $year),
            ],
            2 => [
                'year' => $year,
                'quarter' => 2,
                'period_start' => sprintf('%04d-04-01', $year),
                'period_end' => sprintf('%04d-07-01', $year),
                'label' => sprintf('%04d Q2 (Apr–Jun)', $year),
            ],
            3 => [
                'year' => $year,
                'quarter' => 3,
                'period_start' => sprintf('%04d-07-01', $year),
                'period_end' => sprintf('%04d-10-01', $year),
                'label' => sprintf('%04d Q3 (Jul–Sep)', $year),
            ],
            default => [
                'year' => $year,
                'quarter' => 4,
                'period_start' => sprintf('%04d-10-01', $year),
                'period_end' => sprintf('%04d-01-01', $year + 1),
                'label' => sprintf('%04d Q4 (Oct–Dec)', $year),
            ],
        };
    }

    /**
     * Resolves the quarter key (YYYY-Q#) containing the given YYYY-MM-DD date.
     */
    public static function quarterKeyForDate(string $date): string
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', substr(trim($date), 0, 10));
        if ($dt === false) {
            throw new InvalidArgumentException(sprintf('Invalid date "%s". Expected YYYY-MM-DD.', $date));
        }

        $year = (int) $dt->format('Y');
        $month = (int) $dt->format('n');
        $quarter = (int) ceil($month / 3);

        return sprintf('%04d-Q%d', $year, $quarter);
    }

    /**
     * Generates a contiguous list of quarter keys from $startQuarterKey through $endQuarterKey inclusive.
     *
     * @return list<string>
     */
    public static function generateQuarterKeysBetween(string $startQuarterKey, string $endQuarterKey): array
    {
        $start = self::parseQuarterKey($startQuarterKey);
        $end = self::parseQuarterKey($endQuarterKey);

        $startIndex = ($start['year'] * 4) + ($start['quarter'] - 1);
        $endIndex = ($end['year'] * 4) + ($end['quarter'] - 1);

        if ($startIndex > $endIndex) {
            return [sprintf('%04d-Q%d', $end['year'], $end['quarter'])];
        }

        $keys = [];
        for ($idx = $startIndex; $idx <= $endIndex; $idx++) {
            $year = intdiv($idx, 4);
            $quarter = ($idx % 4) + 1;
            $keys[] = sprintf('%04d-Q%d', $year, $quarter);
        }

        return $keys;
    }

    /**
     * Computes the statutory FONTUR due date (20th Colombian business day following quarter close)
     * for a given quarter key (e.g. '2026-Q1' -> 20th business day of April 2026).
     */
    public static function calculateQuarterDueDate(string $periodKey): string
    {
        $parsed = self::parseQuarterKey($periodKey);
        $year = $parsed['year'];
        $quarter = $parsed['quarter'];

        [$dueYear, $dueMonth] = match ($quarter) {
            1 => [$year, 4],
            2 => [$year, 7],
            3 => [$year, 10],
            default => [$year + 1, 1],
        };

        return self::nthBusinessDayOfMonth($dueYear, $dueMonth, self::STATUTORY_DUE_BUSINESS_DAY);
    }

    /**
     * Computes the Nth Colombian business day (Monday-Friday, excluding Colombian statutory holidays)
     * starting from the 1st of the given year and month.
     */
    public static function nthBusinessDayOfMonth(int $year, int $month, int $nthBusinessDay = 20): string
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException(sprintf('Invalid month %d.', $month));
        }
        if ($nthBusinessDay < 1) {
            throw new InvalidArgumentException(sprintf('Business day index must be >= 1, got %d.', $nthBusinessDay));
        }

        $cursor = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $businessDaysCount = 0;

        while (true) {
            $dateStr = $cursor->format('Y-m-d');
            if (self::isBusinessDay($dateStr)) {
                $businessDaysCount++;
                if ($businessDaysCount === $nthBusinessDay) {
                    return $dateStr;
                }
            }
            $cursor = $cursor->modify('+1 day');
        }
    }

    /**
     * Returns true if the given YYYY-MM-DD date is a Colombian business day (Mon-Fri and not a statutory holiday).
     */
    public static function isBusinessDay(string $date): bool
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($dt === false) {
            return false;
        }

        $dayOfWeek = (int) $dt->format('N'); // 1 (Mon) .. 7 (Sun)
        if ($dayOfWeek >= 6) {
            return false;
        }

        return !self::isHoliday($date);
    }

    /**
     * Returns true if the given YYYY-MM-DD date is a Colombian statutory holiday.
     */
    public static function isHoliday(string $date): bool
    {
        $year = (int) substr($date, 0, 4);
        $holidays = self::getHolidaysForYear($year);

        return isset($holidays[$date]);
    }

    /**
     * Returns all 18 Colombian statutory holidays falling in the given year as a map of 'YYYY-MM-DD' => holiday name.
     *
     * @return array<string, string>
     */
    public static function getHolidaysForYear(int $year): array
    {
        if (isset(self::$holidaysCache[$year])) {
            return self::$holidaysCache[$year];
        }

        $holidays = [];

        // 1. Fixed-date holidays
        $holidays[sprintf('%04d-01-01', $year)] = 'Año Nuevo';
        $holidays[sprintf('%04d-05-01', $year)] = 'Día del Trabajo';
        $holidays[sprintf('%04d-07-20', $year)] = 'Día de la Independencia';
        $holidays[sprintf('%04d-08-07', $year)] = 'Batalla de Boyacá';
        $holidays[sprintf('%04d-12-08', $year)] = 'Inmaculada Concepción';
        $holidays[sprintf('%04d-12-25', $year)] = 'Navidad';

        // 2. Ley Emiliani holidays (moved to the following Monday if not already on Monday)
        $emilianiDates = [
            [1, 6, 'Reyes Magos'],
            [3, 19, 'San José'],
            [6, 29, 'San Pedro y San Pablo'],
            [8, 15, 'Asunción de la Virgen'],
            [10, 12, 'Día de la Raza'],
            [11, 1, 'Todos los Santos'],
            [11, 11, 'Independencia de Cartagena'],
        ];

        foreach ($emilianiDates as [$m, $d, $name]) {
            $observed = self::moveToFollowingMonday(new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $m, $d)));
            $holidays[$observed->format('Y-m-d')] = $name;
        }

        // 3. Easter-relative holidays
        $easter = self::computeEasterSunday($year);
        $holidays[$easter->modify('-3 days')->format('Y-m-d')] = 'Jueves Santo';
        $holidays[$easter->modify('-2 days')->format('Y-m-d')] = 'Viernes Santo';
        $holidays[$easter->modify('+43 days')->format('Y-m-d')] = 'Ascensión del Señor';
        $holidays[$easter->modify('+64 days')->format('Y-m-d')] = 'Corpus Christi';
        $holidays[$easter->modify('+71 days')->format('Y-m-d')] = 'Sagrado Corazón de Jesús';

        ksort($holidays);
        self::$holidaysCache[$year] = $holidays;

        return $holidays;
    }

    /**
     * Moves a date to the following Monday if it does not already fall on a Monday (Ley 51 de 1983).
     */
    private static function moveToFollowingMonday(DateTimeImmutable $date): DateTimeImmutable
    {
        $dow = (int) $date->format('N'); // 1 = Monday .. 7 = Sunday
        if ($dow === 1) {
            return $date;
        }

        $daysToAdd = 8 - $dow;
        return $date->modify("+{$daysToAdd} days");
    }

    /**
     * Anonymous Gregorian algorithm (Meeus/Jones/Butcher) for computing Easter Sunday for a given year.
     */
    public static function computeEasterSunday(int $year): DateTimeImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }
}
