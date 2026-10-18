<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

/**
 * Canonical 8-category taxonomy for Operational Expenses in OceanViewFlats.
 */
enum ExpenseCategory: string
{
    case CLEANING = 'cleaning';
    case HOA_ADMIN = 'hoa_admin';
    case UTILITIES = 'utilities';
    case MAINTENANCE = 'maintenance';
    case SUPPLIES = 'supplies';
    case PLATFORM_FEES = 'platform_fees';
    case TAXES_INSURANCE = 'taxes_insurance';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CLEANING => 'Cleaning & Turnover',
            self::HOA_ADMIN => 'Condominium Administration (HOA)',
            self::UTILITIES => 'Utilities & Telecom',
            self::MAINTENANCE => 'Maintenance & Repairs',
            self::SUPPLIES => 'Guest Supplies & Consumables',
            self::PLATFORM_FEES => 'Payment Gateway & Platform Fees',
            self::TAXES_INSURANCE => 'Taxes, Insurance & Statutory',
            self::OTHER => 'Other Operational Expense',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::CLEANING => 'bg-sky-50 text-sky-700 border-sky-200',
            self::HOA_ADMIN => 'bg-purple-50 text-purple-700 border-purple-200',
            self::UTILITIES => 'bg-amber-50 text-amber-700 border-amber-200',
            self::MAINTENANCE => 'bg-orange-50 text-orange-700 border-orange-200',
            self::SUPPLIES => 'bg-teal-50 text-teal-700 border-teal-200',
            self::PLATFORM_FEES => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            self::TAXES_INSURANCE => 'bg-rose-50 text-rose-700 border-rose-200',
            self::OTHER => 'bg-gray-100 text-gray-700 border-gray-200',
        };
    }

    public function barClass(): string
    {
        return match ($this) {
            self::CLEANING => 'bg-sky-500',
            self::HOA_ADMIN => 'bg-purple-500',
            self::UTILITIES => 'bg-amber-500',
            self::MAINTENANCE => 'bg-orange-500',
            self::SUPPLIES => 'bg-teal-500',
            self::PLATFORM_FEES => 'bg-indigo-500',
            self::TAXES_INSURANCE => 'bg-rose-500',
            self::OTHER => 'bg-gray-500',
        };
    }
}
