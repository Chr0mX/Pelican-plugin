<?php

namespace Chr0mX\ValheimModManager\DTO;

final readonly class ThunderstoreVersionData
{
    /**
     * @param  ThunderstoreDependency[]  $dependencies
     */
    public function __construct(
        public string $name,
        public string $fullName,
        public string $versionNumber,
        public string $description,
        public ?string $icon,
        public string $downloadUrl,
        public int $downloads,
        public int $fileSize,
        public ?string $dateCreated,
        public array $dependencies,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $dependencies = array_values(array_filter(array_map(
            static fn (mixed $dependency): ?ThunderstoreDependency => is_string($dependency)
                ? ThunderstoreDependency::fromString($dependency)
                : null,
            $data['dependencies'] ?? []
        )));

        return new self(
            name: (string) ($data['name'] ?? ''),
            fullName: (string) ($data['full_name'] ?? ''),
            versionNumber: (string) ($data['version_number'] ?? '0.0.0'),
            description: (string) ($data['description'] ?? ''),
            icon: $data['icon'] ?? null,
            downloadUrl: (string) ($data['download_url'] ?? ''),
            downloads: (int) ($data['downloads'] ?? 0),
            fileSize: (int) ($data['file_size'] ?? 0),
            dateCreated: $data['date_created'] ?? null,
            dependencies: $dependencies,
        );
    }
}
