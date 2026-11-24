<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Unit\Domain\Access;

use OceanViewFlats\Domain\Access\ConfigPropertyCredentialsProvider;
use PHPUnit\Framework\TestCase;

final class ConfigPropertyCredentialsProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('PROPERTY_1606_PARKING_SPOT');
        putenv('PROPERTY_1707_PARKING_SPOT');
        unset($_ENV['PROPERTY_1606_PARKING_SPOT'], $_SERVER['PROPERTY_1606_PARKING_SPOT']);
        unset($_ENV['PROPERTY_1707_PARKING_SPOT'], $_SERVER['PROPERTY_1707_PARKING_SPOT']);
        parent::tearDown();
    }

    public function testFailsClosedWithoutConfiguredSecretsAndAppliesNonSecretMetadataDefaults(): void
    {
        $unconfiguredProvider = new ConfigPropertyCredentialsProvider([]);
        $this->assertNull($unconfiguredProvider->getCredentials('1606'));
        $this->assertNull($unconfiguredProvider->getCredentials('1707'));

        $provider = new ConfigPropertyCredentialsProvider([
            '1606' => ['door_code' => '0111606#', 'wifi_password' => 'TestPass1606'],
            '1707' => ['door_code' => '0111707#', 'wifi_password' => 'TestPass1707'],
        ]);

        $creds1606 = $provider->getCredentials('1606');
        $this->assertNotNull($creds1606);
        $this->assertSame('0111606#', $creds1606->doorCode);
        $this->assertSame('APTO1606', $creds1606->wifiSsid);
        $this->assertSame('TestPass1606', $creds1606->wifiPassword);
        $this->assertSame('87', $creds1606->parkingSpot);

        $creds1707 = $provider->getCredentials('1707');
        $this->assertNotNull($creds1707);
        $this->assertSame('0111707#', $creds1707->doorCode);
        $this->assertSame('APTO1707', $creds1707->wifiSsid);
        $this->assertSame('TestPass1707', $creds1707->wifiPassword);
        $this->assertSame('95', $creds1707->parkingSpot);
    }

    public function testUsesConfiguredParkingSpotWhenProvided(): void
    {
        $provider = new ConfigPropertyCredentialsProvider([
            '1606' => [
                'door_code' => '9999#',
                'wifi_ssid' => 'WIFI_CUSTOM',
                'wifi_password' => 'PassCustom',
                'parking_spot' => 'B-12',
            ],
        ]);

        $creds = $provider->getCredentials('1606');
        $this->assertNotNull($creds);
        $this->assertSame('B-12', $creds->parkingSpot);
        $this->assertSame('9999#', $creds->doorCode);
    }

    public function testEnvironmentVariableOverridesConfiguredParkingSpot(): void
    {
        putenv('PROPERTY_1606_PARKING_SPOT=ENV-88');
        $_ENV['PROPERTY_1606_PARKING_SPOT'] = 'ENV-88';

        $provider = new ConfigPropertyCredentialsProvider([
            '1606' => [
                'door_code' => '9999#',
                'wifi_ssid' => 'WIFI_CUSTOM',
                'wifi_password' => 'PassCustom',
                'parking_spot' => '87',
            ],
        ]);

        $creds = $provider->getCredentials('1606');
        $this->assertNotNull($creds);
        $this->assertSame('ENV-88', $creds->parkingSpot);
    }

    public function testReturnsNullForUnknownPropertyWithoutDefaults(): void
    {
        $provider = new ConfigPropertyCredentialsProvider([]);
        $creds = $provider->getCredentials('unknown_prop');
        $this->assertNull($creds);
    }
}
