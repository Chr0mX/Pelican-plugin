<?php

namespace Chr0mX\ValheimModManager\Tests\Unit;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Chr0mX\ValheimModManager\Games\ValheimProvider;
use Chr0mX\ValheimModManager\Services\ModActivityLogger;
use Chr0mX\ValheimModManager\Services\ModManagerService;
use Chr0mX\ValheimModManager\Services\ModMetadataStore;
use Chr0mX\ValheimModManager\Services\ModScanner;
use Chr0mX\ValheimModManager\Services\ThunderstoreService;
use Chr0mX\ValheimModManager\Tests\TestCase;

class ModManagerServiceTest extends TestCase
{
    private function service(DaemonFileRepository $repository): ModManagerService
    {
        return new ModManagerService(
            app(\Chr0mX\ValheimModManager\Services\GameProviderRegistry::class),
            new ModScanner($repository, new ModMetadataStore($repository)),
            new ThunderstoreService(),
            new ModActivityLogger(),
        );
    }

    public function test_installed_mods_is_cached_across_calls_within_the_ttl(): void
    {
        $repository = new DaemonFileRepository();
        $server = new Server();
        $server->id = 1;
        $provider = new ValheimProvider();

        $repository->seedFile('BepInEx/plugins/First.dll', 'binary');
        $repository->seedDirectory('BepInEx/patchers');

        $service = $this->service($repository);

        $first = $service->installedMods($server, $provider, withLatestVersions: false);
        $this->assertCount(1, $first);

        // Simulate a mod appearing on disk without going through the plugin
        // (or simply a second Livewire request/tab-switch/search keystroke
        // arriving within the cache TTL) - installedMods() should keep
        // serving the cached scan rather than re-hitting the daemon.
        $repository->seedFile('BepInEx/plugins/Second.dll', 'binary');

        $second = $service->installedMods($server, $provider, withLatestVersions: false);
        $this->assertCount(1, $second, 'Expected the cached scan to be reused instead of re-scanning.');

        $service->forgetInstalledModsCache($server, $provider);

        $third = $service->installedMods($server, $provider, withLatestVersions: false);
        $this->assertCount(2, $third, 'Expected forgetInstalledModsCache() to force a fresh scan.');
    }

    public function test_installed_mods_cache_is_scoped_per_server(): void
    {
        // The fake daemon repository is a single shared virtual filesystem
        // (it doesn't segment by server, unlike the real one), so this
        // proves cache *key* isolation rather than filesystem isolation:
        // server B's first-ever call must be its own cache miss (and see
        // the file added after server A's call was cached), while server
        // A's already-cached result must stay untouched by server B's call.
        $repository = new DaemonFileRepository();
        $provider = new ValheimProvider();

        $serverA = new Server();
        $serverA->id = 1;
        $serverB = new Server();
        $serverB->id = 2;

        $repository->seedFile('BepInEx/plugins/First.dll', 'binary');

        $service = $this->service($repository);

        $this->assertCount(1, $service->installedMods($serverA, $provider, withLatestVersions: false));

        $repository->seedFile('BepInEx/plugins/Second.dll', 'binary');

        $this->assertCount(
            2,
            $service->installedMods($serverB, $provider, withLatestVersions: false),
            "Expected server B's first call to be an independent cache miss reflecting current disk state.",
        );
        $this->assertCount(
            1,
            $service->installedMods($serverA, $provider, withLatestVersions: false),
            "Expected server A's cached result to be unaffected by server B's call.",
        );
    }
}
