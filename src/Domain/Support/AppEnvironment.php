<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Support;

/**
 * Canonical runtime environment classifier shared across APIs, domain security services, and admin setup.
 */
final class AppEnvironment
{
    private const NON_PRODUCTION_VALUES = ['development', 'local', 'testing', 'test'];

    /**
     * Resolves the normalized APP_ENV string (defaults to 'production' when unset).
     *
     * @param array<string, mixed>|null $serverOverride Optional server map override (e.g. unit tests)
     */
    public static function current(?array $serverOverride = null): string
    {
        $raw = $_ENV['APP_ENV']
            ?? ($serverOverride !== null ? ($serverOverride['APP_ENV'] ?? null) : ($_SERVER['APP_ENV'] ?? null))
            ?? (getenv('APP_ENV') ?: 'production');

        $normalized = strtolower(trim((string) $raw));
        return $normalized !== '' ? $normalized : 'production';
    }

    /**
     * Returns true only inside explicit local/development/testing environments or active PHPUnit runs.
     *
     * @param array<string, mixed>|null $serverOverride
     */
    public static function isNonProduction(?array $serverOverride = null, bool $allowPhpUnit = true): bool
    {
        if ($allowPhpUnit && (defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__'))) {
            return true;
        }

        return in_array(self::current($serverOverride), self::NON_PRODUCTION_VALUES, true);
    }

    /**
     * Returns true when running in production (or any unspecified non-dev environment).
     *
     * @param array<string, mixed>|null $serverOverride
     */
    public static function isProduction(?array $serverOverride = null, bool $allowPhpUnit = true): bool
    {
        return !self::isNonProduction($serverOverride, $allowPhpUnit);
    }

    /**
     * Returns true when running in production (or when a request server map explicitly declares APP_ENV=production).
     *
     * @param array<string, mixed> $server
     */
    public static function isProductionForRequest(array $server): bool
    {
        if (isset($server['APP_ENV']) && strtolower(trim((string) $server['APP_ENV'])) === 'production') {
            return true;
        }

        return self::isProduction($server);
    }
}
