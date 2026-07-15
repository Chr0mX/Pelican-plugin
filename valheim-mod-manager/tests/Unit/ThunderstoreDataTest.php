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
                    'date_created' => '2026-07-01T00:00:00Z',
                    'dependencies' => [],
                ],
            ],
        ];
    }

    public function test_only_keeps_the_latest_version_regardless_of_input_order(): void
    {
        $package = ThunderstorePackageData::fromArray($this->samplePackage());

        $this->assertSame('5.4.2202', $package->latestVersion()->versionNumber);
        $this->assertSame('newest', $package->description());
    }

    public function test_a_package_with_no_versions_has_no_latest_version(): void
    {
        $data = $this->samplePackage();
        $data['versions'] = [];

        $package = ThunderstorePackageData::fromArray($data);

        $this->assertNull($package->latestVersion());
        $this->assertSame('', $package->description());
        $this->assertNull($package->icon());
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

    /**
     * Filament's non-Eloquent table records must be plain arrays or Eloquent
     * models - passing an object (even this readonly DTO) through
     * ->records() throws a TypeError deep inside Filament's table
     * internals. toTableRow() is the array shape actually handed to the
     * Browse Thunderstore table.
     */
    public function test_to_table_row_is_a_plain_array_with_the_expected_shape(): void
    {
        $package = ThunderstorePackageData::fromArray($this->samplePackage());

        $row = $package->toTableRow();

        $this->assertIsArray($row);
        $this->assertSame('denikson-bepinexpack_valheim', $row['key']);
        $this->assertSame('BepInExPack_Valheim', $row['name']);
        $this->assertSame('denikson', $row['owner']);
        $this->assertSame('https://thunderstore.io/package/denikson/BepInExPack_Valheim/', $row['package_url']);
        $this->assertSame('newest', $row['description']);
        $this->assertSame(200, $row['downloads']);
        $this->assertSame('5.4.2202', $row['latest_version']);
        $this->assertSame('2026-07-01T00:00:00Z', $row['last_updated']);
    }

    public function test_to_table_row_uses_null_latest_version_when_no_versions_exist(): void
    {
        $data = $this->samplePackage();
        $data['versions'] = [];

        $row = ThunderstorePackageData::fromArray($data)->toTableRow();

        $this->assertNull($row['latest_version']);
        $this->assertNull($row['icon']);
        $this->assertNull($row['last_updated']);
        $this->assertSame(0, $row['downloads']);
    }
}
