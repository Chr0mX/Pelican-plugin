<?php

namespace Chr0mX\ValheimModManager\Services;

use App\Models\Server;
use Chr0mX\ValheimModManager\Contracts\GameProviderInterface;
use Chr0mX\ValheimModManager\DTO\InstalledModData;
use Chr0mX\ValheimModManager\DTO\ThunderstorePackageData;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * High level orchestrator used by the Filament page (and exposed through the
 * ValheimModManager facade). Keeps business logic out of the Filament page
 * by composing the smaller, single-purpose services.
 */
class ModManagerService
{
    public function __construct(
        protected GameProviderRegistry $providerRegistry,
        protected ModScanner $scanner,
        protected ThunderstoreService $thunderstore,
        protected ModActivityLogger $activityLogger,
    ) {}

    public function providerForServer(Server $server): ?GameProviderInterface
    {
        return $this->providerRegistry->forServer($server);
    }

    /**
     * @return InstalledModData[]
     */
    public function installedMods(Server $server, GameProviderInterface $provider, bool $withLatestVersions = true): array
    {
        $mods = $this->scanner->scan($server, $provider);

        if ($withLatestVersions && config('valheim-mod-manager.auto_update_check', true)) {
            foreach ($mods as $mod) {
                if ($mod->namespace !== null) {
                    $mod->latestVersion = $this->latestVersionFor($provider, $mod->namespace, $mod->name);
                }
            }
        }

        return $mods;
    }

    public function latestVersionFor(GameProviderInterface $provider, string $namespace, string $name): ?string
    {
        return $this->thunderstore->findPackage($provider, $namespace, $name)?->latestVersion()?->versionNumber;
    }

    public function browse(GameProviderInterface $provider, ?string $search, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        return $this->thunderstore->search($provider, $search, $page, $perPage);
    }

    public function findPackage(GameProviderInterface $provider, string $namespace, string $name): ?ThunderstorePackageData
    {
        return $this->thunderstore->findPackage($provider, $namespace, $name);
    }

    public function findPackageByFullName(GameProviderInterface $provider, string $fullName): ?ThunderstorePackageData
    {
        return $this->thunderstore->findPackageByFullName($provider, $fullName);
    }

    /**
     * @param  InstalledModData[]  $mods
     */
    public function updatesAvailableCount(array $mods): int
    {
        return count(array_filter($mods, static fn (InstalledModData $mod): bool => $mod->updateAvailable()));
    }

    public function recentActivity(Server $server, int $limit = 100)
    {
        return $this->activityLogger->recent($server, $limit);
    }
}
