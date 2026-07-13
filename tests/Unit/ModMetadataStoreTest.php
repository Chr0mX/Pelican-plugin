<?php

namespace Chr0mX\ValheimModManager\Tests\Unit;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Chr0mX\ValheimModManager\Games\ValheimProvider;
use Chr0mX\ValheimModManager\Services\ModMetadataStore;
use Chr0mX\ValheimModManager\Tests\TestCase;

class ModMetadataStoreTest extends TestCase
{
    public function test_put_find_and_forget_round_trip(): void
    {
        $store = new ModMetadataStore(new DaemonFileRepository());
        $server = new Server();
        $provider = new ValheimProvider();

        $this->assertSame([], $store->all($server, $provider));

        $ok = $store->put($server, $provider, 'ValheimModding', 'Jotunn', '2.17.0', 'BepInEx/plugins', ['Jotunn'], ['ValheimModding-Jotunn-2.17.0']);
        $this->assertTrue($ok);

        $key = ModMetadataStore::key('ValheimModding', 'Jotunn');
        $entry = $store->find($server, $provider, $key);

        $this->assertNotNull($entry);
        $this->assertSame('2.17.0', $entry['version']);
        $this->assertFalse($entry['disabled']);

        $store->setDisabled($server, $provider, $key, true);
        $this->assertTrue($store->find($server, $provider, $key)['disabled']);

        $store->forget($server, $provider, $key);
        $this->assertNull($store->find($server, $provider, $key));
    }

    public function test_put_preserves_installed_at_across_updates(): void
    {
        $store = new ModMetadataStore(new DaemonFileRepository());
        $server = new Server();
        $provider = new ValheimProvider();

        $store->put($server, $provider, 'A', 'B', '1.0.0', 'BepInEx/plugins', ['B'], []);
        $key = ModMetadataStore::key('A', 'B');
        $firstInstalledAt = $store->find($server, $provider, $key)['installed_at'];

        $store->put($server, $provider, 'A', 'B', '1.1.0', 'BepInEx/plugins', ['B'], []);
        $entry = $store->find($server, $provider, $key);

        $this->assertSame('1.1.0', $entry['version']);
        $this->assertSame($firstInstalledAt, $entry['installed_at']);
    }
}
