<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation\Dashboard;

use DateTimeImmutable;

interface DashboardQueryServiceInterface
{
    /**
     * Aggregates all operational metrics, 7-day schedule, alerts, rate statuses, and blocks
     * into a single unified view model for the administrative dashboard hub.
     *
     * @param string $propertyId 'all' | '1606' | '1707'
     */
    public function getDashboardHubData(
        string $propertyId = 'all',
        ?DateTimeImmutable $now = null
    ): DashboardHubViewData;

    /**
     * Aggregates detailed property statuses, reservations, movements, rates,
     * and maintenance blocks for a specific calendar date into DayDetailViewData.
     *
     * @param string $date 'YYYY-MM-DD'
     * @param string $propertyFilter 'all' | '1606' | '1707'
     * @throws \InvalidArgumentException If date format or calendar date is invalid
     */
    public function getDaySummary(
        string $date,
        string $propertyFilter = 'all',
        ?DateTimeImmutable $now = null
    ): DayDetailViewData;
}
