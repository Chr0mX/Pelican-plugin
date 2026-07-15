<?php

namespace Chr0mX\ValheimModManager\DTO;

/**
 * A single dependency string as used by Thunderstore, e.g.
 * "denikson-BepInExPack_Valheim-5.4.2202".
 */
final readonly class ThunderstoreDependency
{
    public function __construct(
        public string $namespace,
        public string $name,
        public string $version,
    ) {}

    public static function fromString(string $dependency): ?self
    {
        $parts = explode('-', trim($dependency));

        if (count($parts) < 3) {
            return null;
        }

        $version = array_pop($parts);
        $name = array_pop($parts);
        $namespace = implode('-', $parts);

        if ($namespace === '' || $name === '' || $version === '') {
            return null;
        }

        return new self($namespace, $name, $version);
    }

    public function fullName(): string
    {
        return "{$this->namespace}-{$this->name}";
    }

    public function toString(): string
    {
        return "{$this->namespace}-{$this->name}-{$this->version}";
    }
}
