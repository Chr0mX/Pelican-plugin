<?php

namespace Chr0mX\ValheimModManager\Tests\Unit;

use Chr0mX\ValheimModManager\Support\ModManifestParser;
use PHPUnit\Framework\TestCase;

class ModManifestParserTest extends TestCase
{
    public function test_parses_a_valid_manifest(): void
    {
        $manifest = ModManifestParser::parse(json_encode([
            'name' => 'Jotunn',
            'version_number' => '2.17.0',
            'website_url' => 'https://example.com',
            'description' => 'A modding library',
            'dependencies' => ['denikson-BepInExPack_Valheim-5.4.2202', 'invalid'],
        ]));

        $this->assertNotNull($manifest);
        $this->assertSame('Jotunn', $manifest['name']);
        $this->assertSame('2.17.0', $manifest['version_number']);
        $this->assertCount(1, $manifest['dependencies']);
        $this->assertSame('denikson', $manifest['dependencies'][0]->namespace);
    }

    public function test_returns_null_for_invalid_json(): void
    {
        $this->assertNull(ModManifestParser::parse('{not json'));
    }

    public function test_returns_null_when_required_fields_are_missing(): void
    {
        $this->assertNull(ModManifestParser::parse(json_encode(['description' => 'no name or version'])));
    }

    public function test_parses_full_name_with_trailing_version(): void
    {
        $this->assertSame(
            ['namespace' => 'denikson', 'name' => 'BepInExPack_Valheim'],
            ModManifestParser::parseFullName('denikson-BepInExPack_Valheim-5.4.2202')
        );
    }

    public function test_parses_full_name_without_version(): void
    {
        $this->assertSame(
            ['namespace' => 'denikson', 'name' => 'BepInExPack_Valheim'],
            ModManifestParser::parseFullName('denikson-BepInExPack_Valheim')
        );
    }

    public function test_falls_back_when_no_namespace_present(): void
    {
        $this->assertSame(
            ['namespace' => null, 'name' => 'JustAName'],
            ModManifestParser::parseFullName('JustAName')
        );
    }
}
