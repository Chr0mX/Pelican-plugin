<?php

namespace Chr0mX\ValheimModManager\Services;

use App\Models\Server;
use Chr0mX\ValheimModManager\Contracts\GameProviderInterface;
use Chr0mX\ValheimModManager\DTO\InstalledModData;
use Chr0mX\ValheimModManager\DTO\ThunderstorePackageData;
use Chr0mX\ValheimModManager\Support\SafeCache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

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
     * Scanning the daemon's filesystem (plus, for every tracked mod, a
     * Thunderstore lookup for its latest version) has real cost: it used to
     * be paid fresh on every single Livewire request while the Installed or
     * Browse tab was open - every tab switch, search keystroke, sort click
     * and pagination click - since nothing cached it across requests. A
     * short-lived cache is enough to make repeated interactions within the
     * same browsing session cheap, while still self-healing quickly (and
     * being explicitly invalidated by every action that changes what's on
     * disk - see forgetInstalledModsCache()) so it never goes stale for long.
     *
     * @return InstalledModData[]
     */
    public function installedMods(Server $server, GameProviderInterface $provider, bool $withLatestVersions = true): array
    {
        return SafeCache::remember(
            $this->installedModsCacheKey($server, $provider, $withLatestVersions),
            now()->addSeconds(15),
            function () use ($server, $provider, $withLatestVersions) {
                $mods = $this->scanner->scan($server, $provider);

                if ($withLatestVersions && config('valheim-mod-manager.auto_update_check', true)) {
                    foreach ($mods as $mod) {
                        if ($mod->namespace === null) {
                            continue;
                        }

                        $package = $this->thunderstore->findPackage($provider, $mod->namespace, $mod->name);
                        $mod->latestVersion = $package?->latestVersion()?->versionNumber;

                        // Mods this plugin installed itself never have an
                        // icon.png on disk (the installer strips it), and a
                        // folder it doesn't manage might not either - fall
                        // back to Thunderstore's own icon for anything a
                        // disk read didn't already find one for.
                        if ($mod->icon === null) {
                            $mod->icon = $package?->icon();
                        }
                    }
                }

                return $mods;
            },
        );
    }

    /**
     * Must be called after anything that changes a server's mod files on
     * disk (install/update/remove/toggle, in both the synchronous Filament
     * actions and the background Jobs that actually perform them) so the
     * next installedMods() call reflects reality instead of serving a cached
     * pre-change scan for up to 15 seconds.
     */
    public function forgetInstalledModsCache(Server $server, GameProviderInterface $provider): void
    {
        Cache::forget($this->installedModsCacheKey($server, $provider, true));
        Cache::forget($this->installedModsCacheKey($server, $provider, false));
    }

    protected function installedModsCacheKey(Server $server, GameProviderInterface $provider, bool $withLatestVersions): string
    {
        return "valheim-mod-manager:installed:{$server->id}:{$provider->getSlug()}:" . ($withLatestVersions ? '1' : '0');
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
