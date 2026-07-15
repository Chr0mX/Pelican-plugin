<?php

namespace Chr0mX\ValheimModManager\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Chr0mX\ValheimModManager\Contracts\GameProviderInterface;
use Chr0mX\ValheimModManager\DTO\ThunderstorePackageData;
use Chr0mX\ValheimModManager\DTO\ThunderstoreVersionData;
use Chr0mX\ValheimModManager\Support\ProgressReporter;
use Chr0mX\ValheimModManager\Support\SafePath;
use RuntimeException;

/**
 * Handles the full install/update lifecycle of a Thunderstore package:
 *
 *  1. Download the package zip to an isolated staging directory
 *  2. Verify the download actually landed and looks like a zip
 *  3. Extract it (still isolated from the live plugins directory)
 *  4. Work out which extracted entries are config files and leave those alone
 *  5. Move the remaining plugin/patcher files into place
 *  6. Delete stale files from a previous version (updates only)
 *  7. Delete the staging directory
 *  8. Persist metadata so ModScanner and the update checker can find it again
 */
class ModInstaller
{
    // manifest.json/icon.png are deliberately *kept* alongside the installed
    // files (matching how r2modman/Thunderstore Mod Manager lay out a
    // profile) so ModScanner can read a managed mod's real description/icon
    // straight from disk, the same way it already does for unmanaged
    // folders - no live Thunderstore lookup required. README/changelog/
    // license aren't read by anything, so there's no reason to keep them.
    private const METADATA_FILES = ['readme.md', 'changelog.md', 'changelog.txt', 'license'];

    /**
     * Public so ModScanner can clean up any package.zip left behind in a
     * mod's folder from before this filename was excluded from the payload
     * (see ModScanner::deleteResidualZipIfPresent()).
     */
    public const ZIP_NAME = 'package.zip';

    public function __construct(
        protected DaemonFileRepository $fileRepository,
        protected ModMetadataStore $metadataStore,
        protected ModActivityLogger $activityLogger,
    ) {}

    /**
     * @throws RuntimeException
     */
    public function install(
        Server $server,
        GameProviderInterface $provider,
        ThunderstorePackageData $package,
        ThunderstoreVersionData $version,
        bool $overwriteConfig = false,
        ?string $progressToken = null,
        ?array $previousEntry = null,
    ): void {
        $this->fileRepository->setServer($server);

        $stagingDir = SafePath::join(config('valheim-mod-manager.temporary_directory'), (string) str()->uuid());

        try {
            $this->progress($progressToken, 'downloading');
            $this->fileRepository->pull($version->downloadUrl, $stagingDir, [
                'filename' => self::ZIP_NAME,
                'foreground' => true,
            ])->throw();

            $this->progress($progressToken, 'verifying');
            $this->verifyDownload($stagingDir, self::ZIP_NAME);

            $this->progress($progressToken, 'extracting');
            $this->fileRepository->decompressFile($stagingDir, self::ZIP_NAME)->throw();

            // For an update, the new payload usually resolves to the exact same
            // wrapper folder/file names as the old one (same namespace + name).
            // Deleting the old tracked files up front - before the new ones are
            // moved into place - guarantees the old version's files can never
            // linger merged inside a same-named folder.
            if ($previousEntry !== null) {
                $this->deletePreviousFiles($previousEntry);
            }

            $this->progress($progressToken, 'installing');
            $installed = $this->movePayload($stagingDir, $provider, $package, $overwriteConfig);

            if (empty($installed['files'])) {
                throw new RuntimeException(trans('valheim-mod-manager::strings.errors.missing_manifest'));
            }

            $this->metadataStore->put(
                $server,
                $provider,
                $package->owner,
                $package->name,
                $version->versionNumber,
                $installed['directory'],
                $installed['files'],
                array_map(fn ($dependency) => $dependency->toString(), $version->dependencies),
            );

            $this->progress($progressToken, 'cleaning_up');
            $this->cleanup($stagingDir);

            $this->activityLogger->log(
                $server,
                $previousEntry !== null ? 'updated' : 'installed',
                "{$package->owner}/{$package->name}",
                $previousEntry['version'] ?? null,
                $version->versionNumber,
            );

            $this->progress($progressToken, 'finished');
        } catch (\Throwable $exception) {
            $this->cleanup($stagingDir);
            $this->progress($progressToken, 'failed', $exception->getMessage());

            throw $exception instanceof RuntimeException ? $exception : new RuntimeException($exception->getMessage(), previous: $exception);
        }
    }

    protected function verifyDownload(string $stagingDir, string $zipName): void
    {
        $listing = $this->fileRepository->getDirectory($stagingDir);
        $listing = is_array($listing) ? $listing : [];

        foreach ($listing as $item) {
            if (strcasecmp($item['name'], $zipName) === 0) {
                if (empty($item['file']) || (int) ($item['size'] ?? 0) <= 0) {
                    throw new RuntimeException(trans('valheim-mod-manager::strings.errors.invalid_zip'));
                }

                return;
            }
        }

        throw new RuntimeException(trans('valheim-mod-manager::strings.errors.download_failed'));
    }

    /**
     * Determines the extracted package layout and moves the relevant
     * entries into the live plugins/patchers directory, skipping config
     * files unless explicitly requested.
     *
     * @return array{directory: string, files: string[]}
     */
    protected function movePayload(string $stagingDir, GameProviderInterface $provider, ThunderstorePackageData $package, bool $overwriteConfig): array
    {
        $entries = $this->fileRepository->getDirectory($stagingDir);
        $entries = is_array($entries) ? $entries : [];
        $entryNames = array_map(static fn (array $entry): string => $entry['name'], $entries);

        $bepInExFolder = $this->findEntry($entryNames, 'BepInEx');
        if ($bepInExFolder !== null) {
            return $this->moveBepInExLayout($stagingDir, $bepInExFolder, $provider, $overwriteConfig);
        }

        $pluginsFolder = $this->findEntry($entryNames, 'plugins');
        if ($pluginsFolder !== null) {
            $moved = $this->moveDirectoryContents(SafePath::join($stagingDir, $pluginsFolder), $provider->getPluginsDirectories()[0]);

            return ['directory' => $provider->getPluginsDirectories()[0], 'files' => $moved];
        }

        // Flat package: wrap every non-metadata entry in a single folder named after the package.
        // The downloaded zip itself is extracted in place alongside its own contents, so it must
        // be excluded here too - otherwise package.zip ends up moved into the live plugins folder
        // right next to the mod it belongs to.
        $payload = array_values(array_filter(
            $entryNames,
            fn (string $name): bool => !in_array(strtolower($name), self::METADATA_FILES, true)
                && strcasecmp($name, self::ZIP_NAME) !== 0
        ));

        if (empty($payload)) {
            return ['directory' => $provider->getPluginsDirectories()[0], 'files' => []];
        }

        $targetDir = $provider->getPluginsDirectories()[0];
        $wrapperName = SafePath::assertSafeSegment("{$package->owner}-{$package->name}");

        $this->fileRepository->createDirectory($wrapperName, $targetDir);

        foreach ($payload as $name) {
            SafePath::assertSafeSegment($name);
            $this->fileRepository->renameFiles('/', [[
                'from' => SafePath::join($stagingDir, $name),
                'to' => SafePath::join($targetDir, $wrapperName, $name),
            ]])->throw();
        }

        return ['directory' => $targetDir, 'files' => [$wrapperName]];
    }

    /**
     * @return array{directory: string, files: string[]}
     */
    protected function moveBepInExLayout(string $stagingDir, string $bepInExFolder, GameProviderInterface $provider, bool $overwriteConfig): array
    {
        $bepInExPath = SafePath::join($stagingDir, $bepInExFolder);
        $subEntries = $this->fileRepository->getDirectory($bepInExPath);
        $subEntries = is_array($subEntries) ? $subEntries : [];
        $subNames = array_map(static fn (array $entry): string => $entry['name'], $subEntries);

        $files = [];

        $pluginsSub = $this->findEntry($subNames, 'plugins');
        if ($pluginsSub !== null) {
            $files = array_merge($files, $this->moveDirectoryContents(SafePath::join($bepInExPath, $pluginsSub), $provider->getPluginsDirectories()[0]));
        }

        $patchersSub = $this->findEntry($subNames, 'patchers');
        if ($patchersSub !== null) {
            $this->moveDirectoryContents(SafePath::join($bepInExPath, $patchersSub), $provider->getPatchersDirectories()[0]);
        }

        if ($overwriteConfig) {
            $configSub = $this->findEntry($subNames, 'config');
            if ($configSub !== null) {
                $this->moveDirectoryContents(SafePath::join($bepInExPath, $configSub), $provider->getConfigDirectory());
            }
        }

        return ['directory' => $provider->getPluginsDirectories()[0], 'files' => $files];
    }

    /**
     * @return string[] top-level entry names moved into $targetDir
     */
    protected function moveDirectoryContents(string $sourceDir, string $targetDir): array
    {
        $entries = $this->fileRepository->getDirectory($sourceDir);
        $entries = is_array($entries) ? $entries : [];

        $moved = [];
        foreach ($entries as $entry) {
            $name = SafePath::assertSafeSegment($entry['name']);

            $this->fileRepository->renameFiles('/', [[
                'from' => SafePath::join($sourceDir, $name),
                'to' => SafePath::join($targetDir, $name),
            ]])->throw();

            $moved[] = $name;
        }

        return $moved;
    }

    /**
     * @param  array<string, mixed>  $previousEntry
     */
    protected function deletePreviousFiles(array $previousEntry): void
    {
        $paths = array_map(
            fn (string $file): string => SafePath::join($previousEntry['directory'], $file),
            $previousEntry['files'] ?? []
        );

        if (empty($paths)) {
            return;
        }

        try {
            $this->fileRepository->deleteFiles('/', $paths);
        } catch (\Throwable) {
            // Best effort: an update should still proceed even if the old
            // version's files were already partially missing.
        }
    }

    protected function cleanup(string $stagingDir): void
    {
        try {
            $this->fileRepository->deleteFiles('/', [$stagingDir]);
        } catch (\Throwable) {
            // Best effort cleanup; nothing else we can do if the daemon is unreachable here.
        }
    }

    /**
     * @param  string[]  $names
     */
    protected function findEntry(array $names, string $target): ?string
    {
        foreach ($names as $name) {
            if (strcasecmp($name, $target) === 0) {
                return $name;
            }
        }

        return null;
    }

    protected function progress(?string $token, string $stage, ?string $error = null): void
    {
        if ($token !== null) {
            ProgressReporter::report($token, $stage, $error);
        }
    }
}
