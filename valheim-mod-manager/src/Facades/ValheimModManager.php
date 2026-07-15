<?php

namespace Chr0mX\ValheimModManager\Facades;

use App\Models\Server;
use Chr0mX\ValheimModManager\Contracts\GameProviderInterface;
use Chr0mX\ValheimModManager\DTO\InstalledModData;
use Chr0mX\ValheimModManager\DTO\ThunderstorePackageData;
use Chr0mX\ValheimModManager\Services\ModManagerService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ?GameProviderInterface providerForServer(Server $server)
 * @method static InstalledModData[] installedMods(Server $server, GameProviderInterface $provider, bool $withLatestVersions = true)
 * @method static void forgetInstalledModsCache(Server $server, GameProviderInterface $provider)
 * @method static ?string latestVersionFor(GameProviderInterface $provider, string $namespace, string $name)
 * @method static LengthAwarePaginator browse(GameProviderInterface $provider, ?string $search, int $page = 1, int $perPage = 20)
 * @method static ?ThunderstorePackageData findPackage(GameProviderInterface $provider, string $namespace, string $name)
 * @method static ?ThunderstorePackageData findPackageByFullName(GameProviderInterface $provider, string $fullName)
 * @method static int updatesAvailableCount(array $mods)
 * @method static \Illuminate\Support\Collection recentActivity(Server $server, int $limit = 100)
 *
 * @see ModManagerService
 */
class ValheimModManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ModManagerService::class;
    }
}
