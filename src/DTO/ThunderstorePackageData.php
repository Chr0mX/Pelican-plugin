<?php

namespace Chr0mX\ValheimModManager\DTO;

final readonly class ThunderstorePackageData
{
    /**
     * @param  ThunderstoreVersionData[]  $versions
     * @param  string[]  $categories
     */
    public function __construct(
        public string $name,
        public string $fullName,
        public string $owner,
        public string $packageUrl,
        public bool $isDeprecated,
        public array $categories,
        public array $versions,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $versions = array_map(
            static fn (array $version): ThunderstoreVersionData => ThunderstoreVersionData::fromArray($version),
            $data['versions'] ?? []
        );

        // Thunderstore returns versions newest-first already, but don't rely on it.
        usort($versions, static fn (ThunderstoreVersionData $a, ThunderstoreVersionData $b): int => version_compare($b->versionNumber, $a->versionNumber));

        return new self(
            name: (string) ($data['name'] ?? ''),
            fullName: (string) ($data['full_name'] ?? ''),
            owner: (string) ($data['owner'] ?? ''),
            packageUrl: (string) ($data['package_url'] ?? ''),
            isDeprecated: (bool) ($data['is_deprecated'] ?? false),
            categories: $data['categories'] ?? [],
            versions: $versions,
        );
    }

    public function latestVersion(): ?ThunderstoreVersionData
    {
        return $this->versions[0] ?? null;
    }

    public function icon(): ?string
    {
        return $this->latestVersion()?->icon;
    }

    public function description(): string
    {
        return $this->latestVersion()?->description ?? '';
    }

    public function findVersion(string $versionNumber): ?ThunderstoreVersionData
    {
        foreach ($this->versions as $version) {
            if ($version->versionNumber === $versionNumber) {
                return $version;
            }
        }

        return null;
    }
}
