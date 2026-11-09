<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Support;

use RuntimeException;

/**
 * Resilient path resolver for shared resources across local development and cPanel production hosting.
 *
 * In local development, public web assets and scripts reside under <root>/public/.
 * On cPanel hosting (Pattern A), the public site is deployed to <root>/public_html/.
 * This resolver transparently locates shared resources (translations, config, data files, cache).
 */
final class PathResolver
{
    /**
     * Resolves an absolute path to a resource by inspecting both public/ and public_html/.
     *
     * @param string $relativePath Relative path, e.g. 'api/translations.php' or 'data/prices.csv'
     * @param string|null $rootDir Base directory (defaults to repo root / cPanel home)
     * @return string Absolute file path
     * @throws RuntimeException If the resource is not found in either location
     */
    public static function resolve(string $relativePath, ?string $rootDir = null): string
    {
        $cleanRoot = rtrim($rootDir ?? (getenv('OVF_CONFIG_ROOT') ?: dirname(__DIR__, 3)), '/\\');
        $cleanRelative = (string) preg_replace('#^(public_html/|public/)#', '', ltrim($relativePath, '/\\'));

        $cpanelPath = $cleanRoot . '/public_html/' . $cleanRelative;
        if (file_exists($cpanelPath)) {
            return $cpanelPath;
        }

        $localPath = $cleanRoot . '/public/' . $cleanRelative;
        if (file_exists($localPath)) {
            return $localPath;
        }

        throw new RuntimeException(
            "Resource '{$relativePath}' not found. Checked: '{$cpanelPath}' and '{$localPath}'."
        );
    }

    /**
     * Resolves an absolute path if it exists, or returns null.
     *
     * @param string $relativePath
     * @param string|null $rootDir
     * @return string|null
     */
    public static function resolveIfExists(string $relativePath, ?string $rootDir = null): ?string
    {
        try {
            return self::resolve($relativePath, $rootDir);
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Resolves an absolute path to a directory, selecting public_html or public if existing.
     *
     * @param string $relativeDir E.g. 'cache' or 'data'
     * @param string|null $rootDir
     * @return string
     */
    public static function resolveDirectory(string $relativeDir, ?string $rootDir = null): string
    {
        $cleanRoot = rtrim($rootDir ?? (getenv('OVF_CONFIG_ROOT') ?: dirname(__DIR__, 3)), '/\\');
        $cleanRelative = (string) preg_replace('#^(public_html/|public/)#', '', ltrim($relativeDir, '/\\'));

        $cpanelDir = $cleanRoot . '/public_html/' . $cleanRelative;
        if (is_dir($cpanelDir) || is_dir($cleanRoot . '/public_html')) {
            return $cpanelDir;
        }

        $localDir = $cleanRoot . '/public/' . $cleanRelative;
        if (is_dir($localDir)) {
            return $localDir;
        }

        return $localDir;
    }

    /**
     * Loads and returns the multi-language translation dictionary array.
     *
     * @param string|null $rootDir
     * @return array<string, array<string, mixed>>
     */
    public static function loadTranslations(?string $rootDir = null): array
    {
        $path = self::resolve('api/translations.php', $rootDir);

        /** @var array<string, array<string, mixed>> $translations */
        $translations = require $path;

        return $translations;
    }
}
