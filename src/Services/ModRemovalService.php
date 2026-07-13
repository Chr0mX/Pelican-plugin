<?php

namespace Chr0mX\ValheimModManager\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Chr0mX\ValheimModManager\Contracts\GameProviderInterface;
use Chr0mX\ValheimModManager\Support\SafePath;
use RuntimeException;

/**
 * Uninstalls a managed mod. Only files this plugin recorded as belonging to
 * the package are deleted - unrelated plugins (and BepInEx/config) are
 * never touched.
 */
class ModRemovalService
{
    public function __construct(
        protected DaemonFileRepository $fileRepository,
        protected ModMetadataStore $metadataStore,
        protected ModActivityLogger $activityLogger,
    ) {}

    /**
     * @throws RuntimeException
     */
    public function remove(Server $server, GameProviderInterface $provider, string $key): void
    {
        $this->fileRepository->setServer($server);

        $entry = $this->metadataStore->find($server, $provider, $key);

        if ($entry === null) {
            throw new RuntimeException("No managed mod found for key [$key].");
        }

        $paths = array_map(
            fn (string $file): string => SafePath::join($entry['directory'], $file),
            $entry['files']
        );

        if (!empty($paths)) {
            $this->fileRepository->deleteFiles('/', $paths)->throw();
        }

        $this->metadataStore->forget($server, $provider, $key);

        $this->activityLogger->log(
            $server,
            'removed',
            "{$entry['namespace']}/{$entry['name']}",
            $entry['version'],
            null,
        );
    }
}
