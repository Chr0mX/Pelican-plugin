<?php

namespace Chr0mX\ValheimModManager\Support;

use Chr0mX\ValheimModManager\DTO\ThunderstoreDependency;

/**
 * Parses a Thunderstore package `manifest.json` file.
 *
 * @phpstan-type ParsedManifest array{name: string, version_number: string, website_url: string, description: string, dependencies: ThunderstoreDependency[]}
 */
final class ModManifestParser
{
    /**
     * Returns null when the content is not valid/parsable JSON or is missing
     * the required `name`/`version_number` fields, instead of throwing, so
     * callers can decide whether a missing manifest is fatal for their flow.
     *
     * @return array{name: string, version_number: string, website_url: string, description: string, dependencies: ThunderstoreDependency[]}|null
     */
    public static function parse(string $content): ?array
    {
        try {
            $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data) || empty($data['name']) || empty($data['version_number'])) {
            return null;
        }

        $dependencies = array_values(array_filter(array_map(
            static fn (mixed $dependency): ?ThunderstoreDependency => is_string($dependency)
                ? ThunderstoreDependency::fromString($dependency)
                : null,
            $data['dependencies'] ?? []
        )));

        return [
            'name' => (string) $data['name'],
            'version_number' => (string) $data['version_number'],
            'website_url' => (string) ($data['website_url'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'dependencies' => $dependencies,
        ];
    }

    /**
     * Best-effort extraction of "namespace" and "name" from a Thunderstore
     * full_name / extracted folder name such as "Owner-ModName" or
     * "Owner-ModName-1.2.3".
     *
     * @return array{namespace: ?string, name: string}
     */
    public static function parseFullName(string $fullName): array
    {
        $parts = explode('-', $fullName);

        if (count($parts) >= 3 && preg_match('/^\d+(\.\d+){0,3}$/', end($parts)) === 1) {
            array_pop($parts);
        }

        if (count($parts) < 2) {
            return ['namespace' => null, 'name' => $fullName];
        }

        $name = array_pop($parts);

        return ['namespace' => implode('-', $parts), 'name' => $name];
    }
}
