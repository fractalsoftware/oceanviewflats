<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Integration\Security;

use PHPUnit\Framework\TestCase;

final class DockerPhpSecurityConfigTest extends TestCase
{
    public function testDockerRuntimeDisablesExposePhp(): void
    {
        $rootDir = dirname(__DIR__, 3);

        $phpIniPath = $rootDir . '/docker/php-security.ini';
        $this->assertFileExists($phpIniPath, 'docker/php-security.ini must exist to harden container PHP runtime.');
        $phpIniContent = (string) file_get_contents($phpIniPath);
        $this->assertMatchesRegularExpression(
            '/^\s*expose_php\s*=\s*Off\b/im',
            $phpIniContent,
            'docker/php-security.ini must disable expose_php.'
        );

        $dockerfilePath = $rootDir . '/Dockerfile';
        $this->assertFileExists($dockerfilePath);
        $dockerfileContent = (string) file_get_contents($dockerfilePath);
        $this->assertStringContainsString(
            'COPY docker/php-security.ini /usr/local/etc/php/conf.d/',
            $dockerfileContent,
            'Dockerfile must install docker/php-security.ini into PHP conf.d.'
        );
    }
}
