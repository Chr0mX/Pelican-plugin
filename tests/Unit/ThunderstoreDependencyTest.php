<?php

namespace Chr0mX\ValheimModManager\Tests\Unit;

use Chr0mX\ValheimModManager\DTO\ThunderstoreDependency;
use PHPUnit\Framework\TestCase;

class ThunderstoreDependencyTest extends TestCase
{
    public function test_parses_a_valid_dependency_string(): void
    {
        $dependency = ThunderstoreDependency::fromString('denikson-BepInExPack_Valheim-5.4.2202');

        $this->assertNotNull($dependency);
        $this->assertSame('denikson', $dependency->namespace);
        $this->assertSame('BepInExPack_Valheim', $dependency->name);
        $this->assertSame('5.4.2202', $dependency->version);
        $this->assertSame('denikson-BepInExPack_Valheim', $dependency->fullName());
        $this->assertSame('denikson-BepInExPack_Valheim-5.4.2202', $dependency->toString());
    }

    public function test_returns_null_for_malformed_strings(): void
    {
        $this->assertNull(ThunderstoreDependency::fromString('not-enough'));
        $this->assertNull(ThunderstoreDependency::fromString(''));
    }

    public function test_handles_names_containing_hyphens(): void
    {
        $dependency = ThunderstoreDependency::fromString('Owner-Some-Hyphenated-Mod-1.0.0');

        $this->assertNotNull($dependency);
        $this->assertSame('Owner-Some-Hyphenated', $dependency->namespace);
        $this->assertSame('Mod', $dependency->name);
        $this->assertSame('1.0.0', $dependency->version);
    }
}
