<?php

namespace Chr0mX\ValheimModManager\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Chr0mX\ValheimModManager\Contracts\GameProviderInterface;
use Chr0mX\ValheimModManager\DTO\InstalledModData;
use Chr0mX\ValheimModManager\Enums\ModSource;
use Chr0mX\ValheimModManager\Enums\ModStatus;
use Chr0mX\ValheimModManager\Support\ModManifestParser;
use Chr0mX\ValheimModManager\Support\SafePath;
use Exception;

/**
 * Scans BepInEx/plugins and BepInEx/patchers on the daemon and returns a
 * merged view of everything installed: mods this plugin manages (tracked in
 * ModMetadataStore) as well as anything a user placed there manually.
 */
class ModScanner
{
    private const DISABLED_FOLDER = 'Disabled';

    public function __construct(
        protected DaemonFileRepository $fileRepository,
        protected ModMetadataStore $metadataStore,
    ) {}

    /**
     * @return InstalledModData[]
     */
    public function scan(Server $server, GameProviderInterface $provider): array
    {
        $this->fileRepository->setServer($server);

        $managed = $this->metadataStore->all($server, $provider);
        $results = [];
        $claimedFiles = [];

        $directories = array_merge($provider->getPluginsDirectories(), $provider->getPatchersDirectories());

        foreach ($directories as $directory) {
            $listing = $this->listDirectory($directory);

            foreach ($managed as $key => $entry) {
                if ($entry['directory'] !== $directory) {
                    continue;
                }

                $results[$key] = $this->buildManagedEntry($key, $entry, $listing);

                foreach ($entry['files'] as $file) {
                    $claimedFiles[$directory][] = strtolower($file);
                }
            }

            foreach ($listing as $item) {
                $name = $item['name'];

                if ($name === config('valheim-mod-manager.metadata_file', '.valheim-mod-manager.json')) {
                    continue;
                }

                if (strtolower($name) === strtolower(self::DISABLED_FOLDER)) {
                    foreach ($this->scanDisabledFolder($directory) as $key => $entry) {
                        $results[$key] ??= $entry;
                    }

                    continue;
                }

                if (in_array(strtolower($name), $claimedFiles[$directory] ?? [], true)) {
                    continue;
                }

                $entry = $this->buildUnmanagedEntry($directory, $item);
                if ($entry !== null) {
                    $results[$entry->key] = $entry;
                }
            }
        }

        ksort($results);

        return array_values($results);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function listDirectory(string $directory): array
    {
        try {
            $contents = $this->fileRepository->getDirectory($directory);

            return is_array($contents) ? array_values($contents) : [];
        } catch (Exception) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<int, array<string, mixed>>  $listing
     */
    protected function buildManagedEntry(string $key, array $entry, array $listing): InstalledModData
    {
        $listedNames = array_map(static fn (array $item): string => strtolower($item['name']), $listing);

        $foundCount = 0;
        foreach ($entry['files'] as $file) {
            if ($this->trackedFileExists($entry['directory'], $file, $listedNames)) {
                $foundCount++;
            }
        }

        $status = match (true) {
            $foundCount === 0 => ModStatus::MissingFiles,
            $entry['disabled'] => ModStatus::Disabled,
            default => ModStatus::Installed,
        };

        return new InstalledModData(
            key: $key,
            name: $entry['name'],
            namespace: $entry['namespace'],
            version: $entry['version'],
            author: $entry['namespace'],
            description: '',
            dependencies: array_values(array_filter(array_map(
                fn (string $dependency) => \Chr0mX\ValheimModManager\DTO\ThunderstoreDependency::fromString($dependency),
                $entry['dependencies'] ?? []
            ))),
            directory: $entry['directory'],
            files: $entry['files'],
            source: ModSource::ThunderstorePackage,
            status: $status,
            lastUpdated: $entry['updated_at'] ?? null,
            managed: true,
        );
    }

    /**
     * A tracked file is normally a direct child of the package's base
     * directory, but a disabled folder-type mod lives one level deeper
     * under "Disabled/", so its existence is checked there instead.
     *
     * @param  string[]  $topLevelListing
     */
    protected function trackedFileExists(string $directory, string $file, array $topLevelListing): bool
    {
        if (!str_contains($file, '/')) {
            return in_array(strtolower($file), $topLevelListing, true);
        }

        $parent = strtolower(dirname($file));
        $leaf = strtolower(basename($file));

        $nestedListing = array_map(
            static fn (array $item): string => strtolower($item['name']),
            $this->listDirectory(SafePath::join($directory, $parent))
        );

        return in_array($leaf, $nestedListing, true);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function buildUnmanagedEntry(string $directory, array $item): ?InstalledModData
    {
        $name = $item['name'];

        if (!SafePath::isSafeSegment($name)) {
            return null;
        }

        if (!empty($item['directory'])) {
            return $this->buildUnmanagedFolderEntry($directory, $name, disabled: false);
        }

        if (!empty($item['file']) && $this->isPluginFile($name)) {
            $disabled = str_ends_with(strtolower($name), '.disabled');
            $displayName = $disabled ? preg_replace('/\.disabled$/i', '', $name) : $name;

            return new InstalledModData(
                key: 'dll:' . strtolower(SafePath::join($directory, $name)),
                name: $displayName,
                namespace: null,
                version: null,
                author: null,
                description: '',
                dependencies: [],
                directory: $directory,
                files: [$name],
                source: ModSource::Dll,
                status: $disabled ? ModStatus::Disabled : ModStatus::Unknown,
                lastUpdated: isset($item['modified']) ? (string) $item['modified'] : null,
                managed: false,
            );
        }

        return null;
    }

    protected function buildUnmanagedFolderEntry(string $directory, string $name, bool $disabled): ?InstalledModData
    {
        $manifest = null;

        try {
            $manifestPath = SafePath::join($directory, $name, 'manifest.json');
            $content = $this->fileRepository->getContent($manifestPath);
            $manifest = ModManifestParser::parse($content);
        } catch (Exception) {
            // no manifest.json inside this folder, that's fine.
        }

        $fullNameInfo = ModManifestParser::parseFullName($name);

        return new InstalledModData(
            key: 'folder:' . strtolower(SafePath::join($directory, $name)),
            name: $manifest['name'] ?? $fullNameInfo['name'],
            namespace: $fullNameInfo['namespace'],
            version: $manifest['version_number'] ?? null,
            author: $fullNameInfo['namespace'],
            description: $manifest['description'] ?? '',
            dependencies: $manifest['dependencies'] ?? [],
            directory: $directory,
            files: [$name],
            source: $manifest !== null ? ModSource::ThunderstorePackage : ModSource::Folder,
            status: $disabled ? ModStatus::Disabled : ModStatus::Unknown,
            lastUpdated: null,
            managed: false,
        );
    }

    /**
     * @return array<string, InstalledModData>
     */
    protected function scanDisabledFolder(string $directory): array
    {
        $results = [];
        $disabledPath = SafePath::join($directory, self::DISABLED_FOLDER);

        foreach ($this->listDirectory($disabledPath) as $item) {
            if (empty($item['directory']) || !SafePath::isSafeSegment($item['name'])) {
                continue;
            }

            $entry = $this->buildUnmanagedFolderEntry($disabledPath, $item['name'], disabled: true);
            if ($entry !== null) {
                $results[$entry->key] = $entry;
            }
        }

        return $results;
    }

    protected function isPluginFile(string $name): bool
    {
        $lower = strtolower($name);

        return str_ends_with($lower, '.dll') || str_ends_with($lower, '.dll.disabled');
    }
}
