<?php

namespace Chr0mX\ValheimModManager\Tests\Unit;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Chr0mX\ValheimModManager\Games\ValheimProvider;
use Chr0mX\ValheimModManager\Services\ModActivityLogger;
use Chr0mX\ValheimModManager\Services\ModMetadataStore;
use Chr0mX\ValheimModManager\Services\ModRemovalService;
use Chr0mX\ValheimModManager\Tests\TestCase;

class ModRemovalServiceTest extends TestCase
{
    public function test_remove_only_deletes_tracked_files_and_leaves_config_and_siblings_alone(): void
    {
        $repository = new DaemonFileRepository();
        $server = new Server();
        $provider = new ValheimProvider();
        $metadataStore = new ModMetadataStore($repository);

        $metadataStore->put($server, $provider, 'ValheimModding', 'Jotunn', '2.17.0', 'BepInEx/plugins', ['Jotunn'], []);
        $repository->seedDirectory('BepInEx/plugins/Jotunn');
        $repository->seedFile('BepInEx/plugins/Jotunn/Jotunn.dll', 'binary');

        // An unrelated plugin that must survive the removal.
        $repository->seedFile('BepInEx/plugins/OtherMod.dll', 'binary');
        $repository->seedFile('BepInEx/config/Jotunn.cfg', 'user edited config');

        $service = new ModRemovalService($repository, $metadataStore, new ModActivityLogger());
        $key = ModMetadataStore::key('ValheimModding', 'Jotunn');

        $service->remove($server, $provider, $key);

        $this->assertFalse($repository->fileExists('BepInEx/plugins/Jotunn'));
        $this->assertFalse($repository->fileExists('BepInEx/plugins/Jotunn/Jotunn.dll'));
        $this->assertTrue($repository->fileExists('BepInEx/plugins/OtherMod.dll'));
        $this->assertTrue($repository->fileExists('BepInEx/config/Jotunn.cfg'));
        $this->assertNull($metadataStore->find($server, $provider, $key));
    }

    public function test_remove_throws_for_an_unknown_key(): void
    {
        $repository = new DaemonFileRepository();
        $server = new Server();
        $provider = new ValheimProvider();
        $metadataStore = new ModMetadataStore($repository);

        $service = new ModRemovalService($repository, $metadataStore, new ModActivityLogger());

        $this->expectException(\RuntimeException::class);
        $service->remove($server, $provider, 'does-not-exist');
    }
}
