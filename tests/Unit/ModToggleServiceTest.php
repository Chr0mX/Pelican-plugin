<?php

namespace Chr0mX\ValheimModManager\Tests\Unit;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Chr0mX\ValheimModManager\Games\ValheimProvider;
use Chr0mX\ValheimModManager\Services\ModActivityLogger;
use Chr0mX\ValheimModManager\Services\ModMetadataStore;
use Chr0mX\ValheimModManager\Services\ModToggleService;
use Chr0mX\ValheimModManager\Tests\TestCase;

class ModToggleServiceTest extends TestCase
{
    public function test_disabling_a_standalone_dll_appends_disabled_suffix(): void
    {
        $repository = new DaemonFileRepository();
        $server = new Server();
        $provider = new ValheimProvider();
        $metadataStore = new ModMetadataStore($repository);

        $metadataStore->put($server, $provider, 'Foo', 'SingleDll', '1.0.0', 'BepInEx/plugins', ['SingleDll.dll'], []);
        $repository->seedFile('BepInEx/plugins/SingleDll.dll', 'binary');

        $service = new ModToggleService($repository, $metadataStore, new ModActivityLogger());
        $key = ModMetadataStore::key('Foo', 'SingleDll');

        $service->toggle($server, $provider, $key, true);

        $this->assertTrue($repository->fileExists('BepInEx/plugins/SingleDll.dll.disabled'));
        $this->assertFalse($repository->fileExists('BepInEx/plugins/SingleDll.dll'));

        $entry = $metadataStore->find($server, $provider, $key);
        $this->assertTrue($entry['disabled']);
        $this->assertSame(['SingleDll.dll.disabled'], $entry['files']);

        $service->toggle($server, $provider, $key, false);
        $this->assertTrue($repository->fileExists('BepInEx/plugins/SingleDll.dll'));
        $this->assertFalse($metadataStore->find($server, $provider, $key)['disabled']);
    }

    public function test_disabling_a_folder_mod_moves_it_into_disabled_subfolder(): void
    {
        $repository = new DaemonFileRepository();
        $server = new Server();
        $provider = new ValheimProvider();
        $metadataStore = new ModMetadataStore($repository);

        $metadataStore->put($server, $provider, 'ValheimModding', 'Jotunn', '2.17.0', 'BepInEx/plugins', ['Jotunn'], []);
        $repository->seedDirectory('BepInEx/plugins/Jotunn');
        $repository->seedFile('BepInEx/plugins/Jotunn/Jotunn.dll', 'binary');

        $service = new ModToggleService($repository, $metadataStore, new ModActivityLogger());
        $key = ModMetadataStore::key('ValheimModding', 'Jotunn');

        $service->toggle($server, $provider, $key, true);

        $this->assertTrue($repository->fileExists('BepInEx/plugins/Disabled/Jotunn/Jotunn.dll'));
        $this->assertFalse($repository->fileExists('BepInEx/plugins/Jotunn'));

        $entry = $metadataStore->find($server, $provider, $key);
        $this->assertSame(['Disabled/Jotunn'], $entry['files']);

        $service->toggle($server, $provider, $key, false);
        $this->assertTrue($repository->fileExists('BepInEx/plugins/Jotunn/Jotunn.dll'));
    }
}
