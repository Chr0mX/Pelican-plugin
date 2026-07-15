<?php

namespace Chr0mX\ValheimModManager\DTO;

use Chr0mX\ValheimModManager\Enums\ModSource;
use Chr0mX\ValheimModManager\Enums\ModStatus;

/**
 * A single row in the "Installed" table. Built by ModScanner from a
 * filesystem scan merged with the metadata this plugin tracks for anything
 * it installed itself.
 */
final class InstalledModData
{
    /**
     * @param  string[]  $files  top level entries (relative to the plugins directory) belonging to this mod
     * @param  ThunderstoreDependency[]  $dependencies
     */
    public function __construct(
        public string $key,
        public string $name,
        public ?string $namespace,
        public ?string $version,
        public ?string $author,
        public string $description,
        public array $dependencies,
        public string $directory,
        public array $files,
        public ModSource $source,
        public ModStatus $status,
        public ?string $latestVersion = null,
        public ?string $lastUpdated = null,
        public bool $managed = false,
        public ?string $icon = null,
    ) {}

    public function updateAvailable(): bool
    {
        if ($this->version === null || $this->latestVersion === null) {
            return false;
        }

        return version_compare($this->latestVersion, $this->version, '>');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'namespace' => $this->namespace,
            'version' => $this->version,
            'author' => $this->author,
            'description' => $this->description,
            'dependencies' => array_map(fn (ThunderstoreDependency $dependency): string => $dependency->toString(), $this->dependencies),
            'directory' => $this->directory,
            'files' => $this->files,
            'source' => $this->source,
            'status' => $this->status,
            'latest_version' => $this->latestVersion,
            'last_updated' => $this->lastUpdated,
            'managed' => $this->managed,
            'icon' => $this->icon,
        ];
    }
}
