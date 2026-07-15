<?php

namespace Chr0mX\ValheimModManager\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Chr0mX\ValheimModManager\Contracts\GameProviderInterface;
use Chr0mX\ValheimModManager\Support\SafePath;
use RuntimeException;

/**
 * Enables/disables a managed mod without deleting anything:
 *  - standalone .dll files are renamed to "*.dll.disabled" (BepInEx only
 *    loads files ending in .dll, so this alone stops it from loading)
 *  - folders are moved into a sibling "Disabled" folder, which removes them
 *    from BepInEx's recursive plugin scan entirely
 *
 * Both operations are reversible via toggle(..., disabled: false).
 */
class ModToggleService
{
    private const DISABLED_FOLDER = 'Disabled';

    public function __construct(
        protected DaemonFileRepository $fileRepository,
        protected ModMetadataStore $metadataStore,
        protected ModActivityLogger $activityLogger,
    ) {}

    /**
     * @throws RuntimeException
     */
    public function toggle(Server $server, GameProviderInterface $provider, string $key, bool $disabled): void
    {
        $this->fileRepository->setServer($server);

        $entry = $this->metadataStore->find($server, $provider, $key);

        if ($entry === null) {
            throw new RuntimeException("No managed mod found for key [$key].");
        }

        if ($entry['disabled'] === $disabled) {
            return;
        }

        $directory = $entry['directory'];
        $newFiles = [];

        foreach ($entry['files'] as $file) {
            $newFiles[] = $disabled
                ? $this->disableFile($directory, $file)
                : $this->enableFile($directory, $file);
        }

        $this->metadataStore->put(
            $server,
            $provider,
            $entry['namespace'],
            $entry['name'],
            $entry['version'],
            $directory,
            $newFiles,
            $entry['dependencies'],
            $disabled,
        );

        $this->activityLogger->log(
            $server,
            $disabled ? 'disabled' : 'enabled',
            "{$entry['namespace']}/{$entry['name']}",
            $entry['version'],
            $entry['version'],
        );
    }

    protected function disableFile(string $directory, string $file): string
    {
        if (str_ends_with(strtolower($file), '.dll')) {
            $newName = "$file.disabled";

            $this->fileRepository->renameFiles($directory, [[
                'from' => $file,
                'to' => $newName,
            ]])->throw();

            return $newName;
        }

        $this->ensureDisabledFolderExists($directory);

        $newName = SafePath::join(self::DISABLED_FOLDER, $file);

        $this->fileRepository->renameFiles($directory, [[
            'from' => $file,
            'to' => $newName,
        ]])->throw();

        return $newName;
    }

    protected function enableFile(string $directory, string $file): string
    {
        if (str_ends_with(strtolower($file), '.dll.disabled')) {
            $newName = preg_replace('/\.disabled$/i', '', $file);

            $this->fileRepository->renameFiles($directory, [[
                'from' => $file,
                'to' => $newName,
            ]])->throw();

            return $newName;
        }

        if (str_starts_with($file, self::DISABLED_FOLDER . '/')) {
            $newName = substr($file, strlen(self::DISABLED_FOLDER) + 1);

            $this->fileRepository->renameFiles($directory, [[
                'from' => $file,
                'to' => $newName,
            ]])->throw();

            return $newName;
        }

        return $file;
    }

    protected function ensureDisabledFolderExists(string $directory): void
    {
        try {
            $this->fileRepository->createDirectory(self::DISABLED_FOLDER, $directory);
        } catch (\Throwable) {
            // Already exists, that's fine.
        }
    }
}
