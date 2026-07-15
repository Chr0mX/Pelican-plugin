<?php

namespace Chr0mX\ValheimModManager\Tests\Unit;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Chr0mX\ValheimModManager\DTO\InstalledModData;
use Chr0mX\ValheimModManager\Enums\ModSource;
use Chr0mX\ValheimModManager\Enums\ModStatus;
use Chr0mX\ValheimModManager\Games\ValheimProvider;
use Chr0mX\ValheimModManager\Services\ModMetadataStore;
use Chr0mX\ValheimModManager\Services\ModScanner;
use Chr0mX\ValheimModManager\Tests\TestCase;

class ModScannerTest extends TestCase
{
    public function test_scan_merges_managed_and_unmanaged_mods(): void
    {
        $repository = new DaemonFileRepository();
        $server = new Server();
        $provider = new ValheimProvider();
        $metadataStore = new ModMetadataStore($repository);

        // A properly managed + installed package.
        $metadataStore->put($server, $provider, 'ValheimModding', 'Jotunn', '2.17.0', 'BepInEx/plugins', ['Jotunn'], []);
        $repository->seedDirectory('BepInEx/plugins/Jotunn');
        $repository->seedFile('BepInEx/plugins/Jotunn/Jotunn.dll', 'binary');

        // A managed package whose files disappeared from disk.
        $metadataStore->put($server, $provider, 'Foo', 'Bar', '1.0.0', 'BepInEx/plugins', ['Bar.dll'], []);

        // An unmanaged, standalone dll the user dropped in manually.
        $repository->seedFile('BepInEx/plugins/UnmanagedMod.dll', 'binary');

        // An unmanaged folder with its own manifest.json.
        $repository->seedFile('BepInEx/plugins/UnmanagedFolder/manifest.json', json_encode([
            'name' => 'SomePackage',
            'version_number' => '3.0.0',
            'description' => 'desc',
            'dependencies' => [],
        ]));

        // An unmanaged, disabled standalone dll.
        $repository->seedFile('BepInEx/plugins/DisabledLoose.dll.disabled', 'binary');

        $repository->seedDirectory('BepInEx/patchers');

        $scanner = new ModScanner($repository, $metadataStore);
        $results = collect($scanner->scan($server, $provider))->keyBy('key');

        $jotunn = $results->get('valheimmodding-jotunn');
        $this->assertInstanceOf(InstalledModData::class, $jotunn);
        $this->assertSame(ModStatus::Installed, $jotunn->status);
        $this->assertTrue($jotunn->managed);

        $bar = $results->get('foo-bar');
        $this->assertSame(ModStatus::MissingFiles, $bar->status);

        $unmanagedDll = $results->get('dll:bepinex/plugins/unmanagedmod.dll');
        $this->assertSame(ModStatus::Unknown, $unmanagedDll->status);
        $this->assertSame(ModSource::Dll, $unmanagedDll->source);
        $this->assertFalse($unmanagedDll->managed);

        $unmanagedFolder = $results->get('folder:bepinex/plugins/unmanagedfolder');
        $this->assertSame(ModSource::ThunderstorePackage, $unmanagedFolder->source);
        $this->assertSame('SomePackage', $unmanagedFolder->name);
        $this->assertSame('3.0.0', $unmanagedFolder->version);

        $disabledDll = $results->get('dll:bepinex/plugins/disabledloose.dll.disabled');
        $this->assertSame(ModStatus::Disabled, $disabledDll->status);
        $this->assertSame('DisabledLoose.dll', $disabledDll->name);
    }

    public function test_scan_reads_icon_png_for_unmanaged_folders_but_never_for_managed_mods(): void
    {
        $repository = new DaemonFileRepository();
        $server = new Server();
        $provider = new ValheimProvider();
        $metadataStore = new ModMetadataStore($repository);

        // A managed package - ModInstaller never leaves an icon.png behind
        // for anything it installs, so even if one somehow ended up in a
        // managed mod's folder, the scanner should never expose it (no
        // point spending a daemon round trip that can never succeed).
        $metadataStore->put($server, $provider, 'ValheimModding', 'Jotunn', '2.17.0', 'BepInEx/plugins', ['Jotunn'], []);
        $repository->seedDirectory('BepInEx/plugins/Jotunn');
        $repository->seedFile('BepInEx/plugins/Jotunn/Jotunn.dll', 'binary');
        $repository->seedFile('BepInEx/plugins/Jotunn/icon.png', 'fake-png-bytes');

        // An unmanaged folder (e.g. dropped in by the egg's install script)
        // that kept its manifest.json *and* icon.png intact.
        $repository->seedFile('BepInEx/plugins/WithIcon/manifest.json', json_encode([
            'name' => 'WithIcon',
            'version_number' => '1.0.0',
            'description' => 'desc',
            'dependencies' => [],
        ]));
        $repository->seedFile('BepInEx/plugins/WithIcon/icon.png', 'fake-png-bytes');

        // An unmanaged folder with no icon.png at all.
        $repository->seedFile('BepInEx/plugins/NoIcon/manifest.json', json_encode([
            'name' => 'NoIcon',
            'version_number' => '1.0.0',
            'description' => 'desc',
            'dependencies' => [],
        ]));

        $repository->seedDirectory('BepInEx/patchers');

        $scanner = new ModScanner($repository, $metadataStore);
        $results = collect($scanner->scan($server, $provider))->keyBy('key');

        $jotunn = $results->get('valheimmodding-jotunn');
        $this->assertNull($jotunn->icon, 'Managed mods should never expose an icon, even if one happens to exist on disk.');

        $withIcon = $results->get('folder:bepinex/plugins/withicon');
        $this->assertSame('data:image/png;base64,' . base64_encode('fake-png-bytes'), $withIcon->icon);

        $noIcon = $results->get('folder:bepinex/plugins/noicon');
        $this->assertNull($noIcon->icon);
    }
}
