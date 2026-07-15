<?php

namespace Chr0mX\ValheimModManager\Tests\Unit;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Chr0mX\ValheimModManager\DTO\ThunderstorePackageData;
use Chr0mX\ValheimModManager\DTO\ThunderstoreVersionData;
use Chr0mX\ValheimModManager\Games\ValheimProvider;
use Chr0mX\ValheimModManager\Services\ModActivityLogger;
use Chr0mX\ValheimModManager\Services\ModInstaller;
use Chr0mX\ValheimModManager\Services\ModMetadataStore;
use Chr0mX\ValheimModManager\Tests\TestCase;
use RuntimeException;

class ModInstallerTest extends TestCase
{
    private function package(string $owner, string $name): ThunderstorePackageData
    {
        return ThunderstorePackageData::fromArray([
            'name' => $name,
            'full_name' => "$owner-$name",
            'owner' => $owner,
            'package_url' => "https://thunderstore.io/package/$owner/$name/",
            'is_deprecated' => false,
            'categories' => [],
            'versions' => [],
        ]);
    }

    private function version(string $downloadUrl, string $versionNumber = '1.0.0'): ThunderstoreVersionData
    {
        return ThunderstoreVersionData::fromArray([
            'name' => 'ignored',
            'full_name' => 'ignored',
            'version_number' => $versionNumber,
            'description' => 'a mod',
            'icon' => null,
            'download_url' => $downloadUrl,
            'downloads' => 1,
            'file_size' => 100,
            'dependencies' => [],
        ]);
    }

    public function test_installs_a_flat_package_wrapped_in_its_own_folder(): void
    {
        $repository = new DaemonFileRepository();
        $repository->registerZipFixture('https://thunderstore.io/download/flat.zip', [
            'manifest.json' => json_encode(['name' => 'FlatMod', 'version_number' => '1.0.0']),
            'icon.png' => 'binary-icon',
            'FlatMod.dll' => 'binary-dll',
        ]);

        $server = new Server();
        $provider = new ValheimProvider();
        $metadataStore = new ModMetadataStore($repository);
        $installer = new ModInstaller($repository, $metadataStore, new ModActivityLogger());

        $installer->install(
            $server,
            $provider,
            $this->package('TestOwner', 'FlatMod'),
            $this->version('https://thunderstore.io/download/flat.zip'),
        );

        $this->assertTrue($repository->fileExists('BepInEx/plugins/TestOwner-FlatMod/FlatMod.dll'));

        $entry = $metadataStore->find($server, $provider, ModMetadataStore::key('TestOwner', 'FlatMod'));
        $this->assertNotNull($entry);
        $this->assertSame('1.0.0', $entry['version']);
        $this->assertSame(['TestOwner-FlatMod'], $entry['files']);

        // The staging directory must be fully cleaned up (no leftover uuid subfolder).
        $this->assertSame([], $repository->getDirectory('BepInEx/.valheim-mod-manager-tmp'));

        // manifest.json/icon.png are kept alongside the mod (matching how
        // r2modman/Thunderstore Mod Manager lay out a profile) so ModScanner
        // can read the real description/icon straight from disk instead of
        // needing a live Thunderstore lookup.
        $this->assertTrue($repository->fileExists('BepInEx/plugins/TestOwner-FlatMod/manifest.json'));
        $this->assertTrue($repository->fileExists('BepInEx/plugins/TestOwner-FlatMod/icon.png'));

        // The downloaded zip itself must never end up in the live plugins
        // folder - it's extracted in place inside the (now-deleted) staging
        // directory, so it has to be excluded from the payload explicitly.
        $this->assertFalse($repository->fileExists('BepInEx/plugins/TestOwner-FlatMod/package.zip'));
    }

    public function test_installs_bepinex_layout_and_never_touches_config(): void
    {
        $repository = new DaemonFileRepository();
        $repository->registerZipFixture('https://thunderstore.io/download/bepinex.zip', [
            'manifest.json' => json_encode(['name' => 'SomeMod', 'version_number' => '2.0.0']),
            'BepInEx/plugins/SomeMod/SomeMod.dll' => 'binary-dll',
            'BepInEx/config/SomeMod.cfg' => 'default config from the zip',
        ]);

        // Pre-existing user config that must survive untouched.
        $repository->seedFile('BepInEx/config/SomeMod.cfg', 'user edited config');

        $server = new Server();
        $provider = new ValheimProvider();
        $metadataStore = new ModMetadataStore($repository);
        $installer = new ModInstaller($repository, $metadataStore, new ModActivityLogger());

        $installer->install(
            $server,
            $provider,
            $this->package('Someone', 'SomeMod'),
            $this->version('https://thunderstore.io/download/bepinex.zip', '2.0.0'),
        );

        $this->assertTrue($repository->fileExists('BepInEx/plugins/SomeMod/SomeMod.dll'));
        $this->assertSame('user edited config', $repository->readRaw('BepInEx/config/SomeMod.cfg'));
    }

    public function test_update_removes_stale_files_from_the_previous_version(): void
    {
        $repository = new DaemonFileRepository();
        $repository->registerZipFixture('https://thunderstore.io/download/v2.zip', [
            'manifest.json' => json_encode(['name' => 'Renaming', 'version_number' => '2.0.0']),
            'NewFileName.dll' => 'binary-v2',
        ]);

        $server = new Server();
        $provider = new ValheimProvider();
        $metadataStore = new ModMetadataStore($repository);
        $installer = new ModInstaller($repository, $metadataStore, new ModActivityLogger());

        // Simulate a previously installed v1 whose wrapper folder had a different name.
        $repository->seedDirectory('BepInEx/plugins/Old-Renaming');
        $repository->seedFile('BepInEx/plugins/Old-Renaming/OldFileName.dll', 'binary-v1');
        $metadataStore->put($server, $provider, 'Old', 'Renaming', '1.0.0', 'BepInEx/plugins', ['Old-Renaming'], []);
        $previousEntry = $metadataStore->find($server, $provider, ModMetadataStore::key('Old', 'Renaming'));

        $installer->install(
            $server,
            $provider,
            $this->package('Old', 'Renaming'),
            $this->version('https://thunderstore.io/download/v2.zip', '2.0.0'),
            previousEntry: $previousEntry,
        );

        // Same namespace/name => same wrapper folder name as before, but its
        // contents must be the new version's only - no merged leftovers.
        $this->assertFalse($repository->fileExists('BepInEx/plugins/Old-Renaming/OldFileName.dll'));
        $this->assertTrue($repository->fileExists('BepInEx/plugins/Old-Renaming/NewFileName.dll'));

        $entry = $metadataStore->find($server, $provider, ModMetadataStore::key('Old', 'Renaming'));
        $this->assertSame('2.0.0', $entry['version']);
    }

    public function test_throws_and_cleans_up_when_download_is_empty(): void
    {
        $repository = new DaemonFileRepository();
        // No fixture registered => pull() will "download" a zero-byte file.
        $server = new Server();
        $provider = new ValheimProvider();
        $metadataStore = new ModMetadataStore($repository);
        $installer = new ModInstaller($repository, $metadataStore, new ModActivityLogger());

        $this->expectException(RuntimeException::class);

        try {
            $installer->install(
                $server,
                $provider,
                $this->package('Broken', 'Mod'),
                $this->version('https://thunderstore.io/download/missing.zip'),
            );
        } finally {
            $this->assertSame([], $repository->getDirectory('BepInEx/.valheim-mod-manager-tmp'));
        }
    }
}
