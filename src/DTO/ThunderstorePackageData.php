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

    /**
     * Filament's non-Eloquent table records must be plain arrays or Eloquent
     * models - an arbitrary object (even a readonly DTO) fails internal
     * Filament type checks. This is the row shape the Browse Thunderstore
     * table renders; `findPackage()` is used to get back to the real DTO
     * when an action needs to act on it.
     *
     * @return array{key: string, name: string, owner: string, package_url: string, icon: ?string, description: string, downloads: int, latest_version: ?string}
     */
    public function toTableRow(): array
    {
        return [
            'key' => strtolower("{$this->owner}-{$this->name}"),
            'name' => $this->name,
            'owner' => $this->owner,
            'package_url' => $this->packageUrl,
            'icon' => $this->icon(),
            'description' => $this->description(),
            'downloads' => $this->latestVersion?->downloads ?? 0,
            'latest_version' => $this->latestVersion?->versionNumber,
        ];
    }
}
