<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Integration\Deployment;

use PHPUnit\Framework\TestCase;

final class AdminDeploymentPayloadTest extends TestCase
{
    private string $rootPath;
    private string $adminDeployYmlPath;
    private string $adminCiYmlPath;

    protected function setUp(): void
    {
        $this->rootPath = dirname(__DIR__, 4);
        $this->adminDeployYmlPath = $this->rootPath . '/.github/workflows/admin-deploy.yml';
        $this->adminCiYmlPath = $this->rootPath . '/.github/workflows/admin-ci.yml';
    }

    public function testAdminDeployWorkflowStagesAllAutoloadedSrcNamespaces(): void
    {
        $this->assertFileExists($this->adminDeployYmlPath);
        $deployYml = (string) file_get_contents($this->adminDeployYmlPath);

        // Read composer.json autoload definitions
        $composerJsonPath = $this->rootPath . '/composer.json';
        $this->assertFileExists($composerJsonPath);
        $composerData = json_decode((string) file_get_contents($composerJsonPath), true);
        $this->assertIsArray($composerData);

        /** @var array<string, string> $psr4 */
        $psr4 = $composerData['autoload']['psr-4'] ?? [];

        foreach ($psr4 as $namespace => $dir) {
            if (str_starts_with($dir, 'src/')) {
                $srcSubdir = rtrim($dir, '/');
                $expectedCopy = 'cp -R ' . $srcSubdir . ' staging/src/';
                $this->assertStringContainsString(
                    $expectedCopy,
                    $deployYml,
                    "Admin deployment workflow must stage '{$srcSubdir}' to staging/src/ because namespace '{$namespace}' is mapped to it in composer.json."
                );
            }
        }
    }

    public function testWorkflowsTriggerOnInfrastructurePathChanges(): void
    {
        $deployYml = (string) file_get_contents($this->adminDeployYmlPath);
        $this->assertStringContainsString(
            "'src/Infrastructure/**'",
            $deployYml,
            "admin-deploy.yml must trigger on changes to 'src/Infrastructure/**'."
        );

        $ciYml = (string) file_get_contents($this->adminCiYmlPath);
        $this->assertStringContainsString(
            "'src/Infrastructure/**'",
            $ciYml,
            "admin-ci.yml must trigger on changes to 'src/Infrastructure/**'."
        );
    }

    public function testAdminAppBootstrapsWithStagedPayloadInIsolatedProcess(): void
    {
        $stagingDir = sys_get_temp_dir() . '/ovf_staging_test_' . uniqid();
        mkdir($stagingDir . '/src', 0777, true);

        try {
            // Replicate staging logic from admin-deploy.yml
            exec(sprintf('cp -R %s %s/admin', escapeshellarg($this->rootPath . '/admin'), escapeshellarg($stagingDir)));
            exec(sprintf('cp -R %s %s/src/', escapeshellarg($this->rootPath . '/src/Domain'), escapeshellarg($stagingDir)));
            
            // Check if workflow includes src/Infrastructure
            $deployYml = (string) file_get_contents($this->adminDeployYmlPath);
            if (str_contains($deployYml, 'cp -R src/Infrastructure staging/src/')) {
                exec(sprintf('cp -R %s %s/src/', escapeshellarg($this->rootPath . '/src/Infrastructure'), escapeshellarg($stagingDir)));
            }

            exec(sprintf('cp -R %s %s/vendor', escapeshellarg($this->rootPath . '/vendor'), escapeshellarg($stagingDir)));
            exec(sprintf('cp -R %s %s/scripts', escapeshellarg($this->rootPath . '/scripts'), escapeshellarg($stagingDir)));

            // Simulate cPanel hosting environment where public_html contains api translations
            mkdir($stagingDir . '/public_html/api', 0777, true);
            copy($this->rootPath . '/public/api/translations.php', $stagingDir . '/public_html/api/translations.php');

            $script = sprintf(
                'require %s; $pdo = new PDO("sqlite::memory:"); \OceanViewFlats\Admin\AdminApp::createDefault($pdo);',
                var_export($stagingDir . '/vendor/autoload.php', true)
            );

            $command = sprintf('php -r %s 2>&1', escapeshellarg($script));
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);

            $outputStr = implode("\n", $output);
            $this->assertSame(
                0,
                $exitCode,
                "AdminApp::createDefault() failed when booted from staged payload:\n" . $outputStr
            );
        } finally {
            exec(sprintf('rm -rf %s', escapeshellarg($stagingDir)));
        }
    }
}
