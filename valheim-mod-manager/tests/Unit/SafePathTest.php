<?php

namespace Chr0mX\ValheimModManager\Tests\Unit;

use Chr0mX\ValheimModManager\Support\SafePath;
use Chr0mX\ValheimModManager\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class SafePathTest extends TestCase
{
    public function test_join_strips_slashes_and_skips_empty_segments(): void
    {
        $this->assertSame('BepInEx/plugins/MyMod', SafePath::join('/BepInEx/', '/plugins/', '', 'MyMod'));
    }

    public function test_valid_segment_passes(): void
    {
        $this->assertSame('MyMod.dll', SafePath::assertSafeSegment('MyMod.dll'));
        $this->assertTrue(SafePath::isSafeSegment('MyMod.dll'));
    }

    #[DataProvider('invalidSegmentProvider')]
    public function test_rejects_unsafe_segments(string $segment): void
    {
        $this->assertFalse(SafePath::isSafeSegment($segment));
        $this->expectException(RuntimeException::class);
        SafePath::assertSafeSegment($segment);
    }

    /** @return array<string, array{0: string}> */
    public static function invalidSegmentProvider(): array
    {
        return [
            'empty' => [''],
            'dot' => ['.'],
            'dot-dot' => ['..'],
            'slash' => ['foo/bar'],
            'backslash' => ['foo\\bar'],
            'null byte' => ["foo\0bar"],
        ];
    }

    public function test_rejects_relative_paths_with_traversal(): void
    {
        $this->expectException(RuntimeException::class);
        SafePath::assertSafeRelativePath('../../etc/passwd');
    }

    public function test_accepts_a_safe_nested_relative_path(): void
    {
        $this->assertSame('Disabled/MyMod', SafePath::assertSafeRelativePath('/Disabled/MyMod/'));
    }
}
