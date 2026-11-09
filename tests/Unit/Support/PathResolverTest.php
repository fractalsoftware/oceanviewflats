<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Support;

use OceanViewFlats\Domain\Support\PathResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PathResolverTest extends TestCase
{
    /** @var list<string> */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->removeDirectory($dir);
        }
        $this->tempDirs = [];
        parent::tearDown();
    }

    private function createTempDirectory(): string
    {
        $dir = sys_get_temp_dir() . '/ovf_path_test_' . bin2hex(random_bytes(8));
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->fail("Failed to create temporary directory: {$dir}");
        }
        $this->tempDirs[] = $dir;
        return $dir;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_link($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function testResolvesLocalPublicFileWhenPresent(): void
    {
        $root = $this->createTempDirectory();
        $file = $root . '/public/api/translations.php';
        mkdir(dirname($file), 0755, true);
        file_put_contents($file, "<?php return ['en' => ['ok' => true]];");

        $resolved = PathResolver::resolve('api/translations.php', $root);
        $this->assertSame($file, $resolved);
    }

    public function testResolvesCpanelPublicHtmlFileWhenPublicDoesNotExist(): void
    {
        $root = $this->createTempDirectory();
        $file = $root . '/public_html/api/translations.php';
        mkdir(dirname($file), 0755, true);
        file_put_contents($file, "<?php return ['en' => ['cpanel' => true]];");

        $resolved = PathResolver::resolve('api/translations.php', $root);
        $this->assertSame($file, $resolved);
    }

    public function testPrefersPublicHtmlOverPublicWhenBothExist(): void
    {
        $root = $this->createTempDirectory();
        $localFile = $root . '/public/cache/avail_1606.json';
        $cpanelFile = $root . '/public_html/cache/avail_1606.json';
        mkdir(dirname($localFile), 0755, true);
        mkdir(dirname($cpanelFile), 0755, true);
        file_put_contents($localFile, '["2026-11-01"]');
        file_put_contents($cpanelFile, '["2026-11-10"]');

        $this->assertSame($cpanelFile, PathResolver::resolve('cache/avail_1606.json', $root));
        $this->assertSame($root . '/public_html/cache', PathResolver::resolveDirectory('cache', $root));
    }

    public function testResolveDirectoryPrefersPublicHtmlWhenPublicHtmlRootExists(): void
    {
        $root = $this->createTempDirectory();
        mkdir($root . '/public/cache', 0755, true);
        mkdir($root . '/public_html', 0755, true);

        $this->assertSame($root . '/public_html/cache', PathResolver::resolveDirectory('cache', $root));
    }

    public function testStripsLeadingPublicAndPublicHtmlPrefixes(): void
    {
        $root = $this->createTempDirectory();
        $file = $root . '/public_html/data/prices.csv';
        mkdir(dirname($file), 0755, true);
        file_put_contents($file, "prop,date,price\n1606,2026-10-01,100");

        $resolvedFromPublic = PathResolver::resolve('public/data/prices.csv', $root);
        $resolvedFromPublicHtml = PathResolver::resolve('public_html/data/prices.csv', $root);
        $resolvedDirect = PathResolver::resolve('data/prices.csv', $root);

        $this->assertSame($file, $resolvedFromPublic);
        $this->assertSame($file, $resolvedFromPublicHtml);
        $this->assertSame($file, $resolvedDirect);
    }

    public function testThrowsRuntimeExceptionWhenNeitherLocationExists(): void
    {
        $root = $this->createTempDirectory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Resource 'data/missing.csv' not found. Checked: '{$root}/public_html/data/missing.csv' and '{$root}/public/data/missing.csv'.");

        PathResolver::resolve('data/missing.csv', $root);
    }

    public function testResolveIfExistsReturnsNullWhenNotFound(): void
    {
        $root = $this->createTempDirectory();
        $this->assertNull(PathResolver::resolveIfExists('data/missing.csv', $root));
    }

    public function testResolveDirectoryChoosesExistingDirectory(): void
    {
        $root = $this->createTempDirectory();
        $cpanelCache = $root . '/public_html/cache';
        mkdir($cpanelCache, 0755, true);

        $resolved = PathResolver::resolveDirectory('cache', $root);
        $this->assertSame($cpanelCache, $resolved);
    }

    public function testLoadTranslationsReturnsValidArray(): void
    {
        $root = $this->createTempDirectory();
        $file = $root . '/public_html/api/translations.php';
        mkdir(dirname($file), 0755, true);
        file_put_contents($file, "<?php return ['es' => ['welcome' => 'Hola']];");

        $translations = PathResolver::loadTranslations($root);
        $this->assertSame(['es' => ['welcome' => 'Hola']], $translations);
    }

    public function testDefaultRootDirResolvesRepositoryRoot(): void
    {
        $realFile = PathResolver::resolve('api/translations.php');
        $this->assertFileExists($realFile);
    }
}
