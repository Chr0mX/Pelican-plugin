<?php

namespace Chr0mX\ValheimModManager\DTO;

final readonly class ThunderstorePackageData
{
    /**
     * @param  string[]  $categories
     */
    public function __construct(
        public string $name,
        public string $fullName,
        public string $owner,
        public string $packageUrl,
        public bool $isDeprecated,
        public array $categories,
        public ?ThunderstoreVersionData $latestVersion,
    ) {}

    /**
     * Only the latest version is parsed and retained. Thunderstore's package
     * list response includes every historical version (with full
     * description/changelog text) for every package in a community; for a
     * large community that is tens of thousands of version entries, and
     * building a DTO for each one is what was blowing through PHP's memory
     * limit while browsing/searching. Nothing in this plugin ever reads
     * anything but the latest version, so older versions are discarded
     * before they're ever turned into objects.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $rawVersions = $data['versions'] ?? [];

        $latestRaw = null;
        foreach ($rawVersions as $rawVersion) {
            if (!is_array($rawVersion) || !isset($rawVersion['version_number'])) {
                continue;
            }

            if ($latestRaw === null || version_compare((string) $rawVersion['version_number'], (string) $latestRaw['version_number'], '>')) {
                $latestRaw = $rawVersion;
            }
        }

        return new self(
            name: (string) ($data['name'] ?? ''),
            fullName: (string) ($data['full_name'] ?? ''),
            owner: (string) ($data['owner'] ?? ''),
            packageUrl: (string) ($data['package_url'] ?? ''),
            isDeprecated: (bool) ($data['is_deprecated'] ?? false),
            categories: $data['categories'] ?? [],
            latestVersion: $latestRaw !== null ? ThunderstoreVersionData::fromArray($latestRaw) : null,
        );
    }

    public function latestVersion(): ?ThunderstoreVersionData
    {
        return $this->latestVersion;
    }

    public function icon(): ?string
    {
        return $this->latestVersion?->icon;
    }

    public function description(): string
    {
        return $this->latestVersion?->description ?? '';
    }
}
