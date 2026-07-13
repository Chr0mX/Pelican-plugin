<?php

namespace Chr0mX\ValheimModManager\Tests\Unit;

use Chr0mX\ValheimModManager\DTO\ThunderstorePackageData;
use PHPUnit\Framework\TestCase;

class ThunderstoreDataTest extends TestCase
{
    private function samplePackage(): array
    {
        return [
            'name' => 'BepInExPack_Valheim',
            'full_name' => 'denikson-BepInExPack_Valheim',
            'owner' => 'denikson',
            'package_url' => 'https://thunderstore.io/package/denikson/BepInExPack_Valheim/',
            'is_deprecated' => false,
            'categories' => ['Libraries'],
            'versions' => [
                [
                    'name' => 'BepInExPack_Valheim',
                    'full_name' => 'denikson-BepInExPack_Valheim-5.4.2201',
                    'version_number' => '5.4.2201',
                    'description' => 'older',
                    'icon' => 'https://example.com/icon.png',
                    'download_url' => 'https://thunderstore.io/package/download/denikson/BepInExPack_Valheim/5.4.2201/',
                    'downloads' => 100,
                    'file_size' => 1000,
                    'dependencies' => [],
                ],
                [
                    'name' => 'BepInExPack_Valheim',
                    'full_name' => 'denikson-BepInExPack_Valheim-5.4.2202',
                    'version_number' => '5.4.2202',
                    'description' => 'newest',
                    'icon' => 'https://example.com/icon.png',
                    'download_url' => 'https://thunderstore.io/package/download/denikson/BepInExPack_Valheim/5.4.2202/',
                    'downloads' => 200,
                    'file_size' => 2000,
                    'dependencies' => [],
                ],
            ],
        ];
    }

    public function test_sorts_versions_newest_first_regardless_of_input_order(): void
    {
        $package = ThunderstorePackageData::fromArray($this->samplePackage());

        $this->assertSame('5.4.2202', $package->latestVersion()->versionNumber);
        $this->assertSame('newest', $package->description());
    }

    public function test_finds_a_specific_version(): void
    {
        $package = ThunderstorePackageData::fromArray($this->samplePackage());

        $version = $package->findVersion('5.4.2201');

        $this->assertNotNull($version);
        $this->assertSame('older', $version->description);
        $this->assertNull($package->findVersion('9.9.9'));
    }

    public function test_parses_dependencies_on_versions(): void
    {
        $data = $this->samplePackage();
        $data['versions'][1]['dependencies'] = ['ValheimModding-Jotunn-2.17.0'];

        $package = ThunderstorePackageData::fromArray($data);

        $dependencies = $package->latestVersion()->dependencies;

        $this->assertCount(1, $dependencies);
        $this->assertSame('ValheimModding', $dependencies[0]->namespace);
    }
}
