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
use Illuminate\Support\Facades\Http;

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

    /**
     * @return array<string, mixed>
     */
    private function fakeThunderstorePackage(string $owner, string $name, string $version, ?string $icon): array
    {
        return [
            'name' => $name,
            'full_name' => "$owner-$name",
            'owner' => $owner,
            'package_url' => "https://thunderstore.io/package/$owner/$name/",
            'is_deprecated' => false,
            'categories' => [],
            'versions' => [[
                'name' => $name,
                'full_name' => "$owner-$name-$version",
                'version_number' => $version,
                'description' => "$name description",
                'icon' => $icon,
                'download_url' => "https://thunderstore.io/package/download/$owner/$name/$version/",
                'downloads' => 0,
                'file_size' => 100,
                'dependencies' => [],
            ]],
        ];
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

    public function test_managed_mods_fall_back_to_the_thunderstore_icon_and_description(): void
    {
        // ModInstaller strips icon.png from anything it installs itself, and
        // the metadata ledger never stored a description at all, so a
        // managed mod can never find either on disk - both should fall back
        // to what Thunderstore reports for that package.
        Http::fake([
            'thunderstore.io/c/valheim/api/v1/package/' => Http::response([
                $this->fakeThunderstorePackage('ValheimModding', 'Jotunn', '2.17.0', 'https://thunderstore.io/icon/jotunn.png'),
            ]),
        ]);

        $repository = new DaemonFileRepository();
        $server = new Server();
        $server->id = 1;
        $provider = new ValheimProvider();
        $metadataStore = new ModMetadataStore($repository);

        $metadataStore->put($server, $provider, 'ValheimModding', 'Jotunn', '2.17.0', 'BepInEx/plugins', ['Jotunn'], []);
        $repository->seedDirectory('BepInEx/plugins/Jotunn');
        $repository->seedFile('BepInEx/plugins/Jotunn/Jotunn.dll', 'binary');

        $service = $this->service($repository);
        $mods = collect($service->installedMods($server, $provider))->keyBy('key');

        $jotunn = $mods->get('valheimmodding-jotunn');
        $this->assertNotNull($jotunn);
        $this->assertSame('https://thunderstore.io/icon/jotunn.png', $jotunn->icon);
        $this->assertSame('Jotunn description', $jotunn->description);
    }

    public function test_a_disk_icon_is_not_overridden_by_the_thunderstore_fallback(): void
    {
        // An unmanaged folder that already kept its own icon.png intact
        // (e.g. via the egg's Level 1 fix) should keep showing that one,
        // not get swapped out for whatever Thunderstore reports.
        Http::fake([
            'thunderstore.io/c/valheim/api/v1/package/' => Http::response([
                $this->fakeThunderstorePackage('Someone', 'WithIcon', '1.0.0', 'https://thunderstore.io/icon/remote.png'),
            ]),
        ]);

        $repository = new DaemonFileRepository();
        $server = new Server();
        $server->id = 1;
        $provider = new ValheimProvider();

        $repository->seedFile('BepInEx/plugins/Someone-WithIcon/manifest.json', json_encode([
            'name' => 'WithIcon',
            'version_number' => '1.0.0',
            'description' => 'desc',
            'dependencies' => [],
        ]));
        $repository->seedFile('BepInEx/plugins/Someone-WithIcon/icon.png', 'local-png-bytes');

        $service = $this->service($repository);
        $mods = collect($service->installedMods($server, $provider))->keyBy('key');

        $withIcon = $mods->get('folder:bepinex/plugins/someone-withicon');
        $this->assertNotNull($withIcon);
        $this->assertSame('data:image/png;base64,' . base64_encode('local-png-bytes'), $withIcon->icon);
    }
}
