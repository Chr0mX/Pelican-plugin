<?php

namespace Chr0mX\ValheimModManager\Tests\Unit;

use App\Models\Egg;
use App\Models\Server;
use Chr0mX\ValheimModManager\Games\ValheimProvider;
use Chr0mX\ValheimModManager\Services\GameProviderRegistry;
use Chr0mX\ValheimModManager\Tests\TestCase;

class GameProviderTest extends TestCase
{
    public function test_valheim_provider_matches_servers_tagged_bepinex_mods(): void
    {
        $provider = new ValheimProvider();

        $tagged = new Server(egg: new Egg(tags: ['bepinex-mods']));
        $untagged = new Server(egg: new Egg(tags: ['vanilla']));

        $this->assertTrue($provider->matchesServer($tagged));
        $this->assertFalse($provider->matchesServer($untagged));
    }

    public function test_registry_resolves_the_first_matching_provider(): void
    {
        $registry = new GameProviderRegistry();
        $registry->register(new ValheimProvider());

        $server = new Server(egg: new Egg(tags: ['bepinex-mods']));
        $other = new Server(egg: new Egg(tags: []));

        $this->assertInstanceOf(ValheimProvider::class, $registry->forServer($server));
        $this->assertNull($registry->forServer($other));
        $this->assertSame('valheim', $registry->get('valheim')?->getSlug());
    }

    public function test_provider_exposes_bepinex_directories(): void
    {
        $provider = new ValheimProvider();

        $this->assertSame(['BepInEx/plugins'], $provider->getPluginsDirectories());
        $this->assertSame(['BepInEx/patchers'], $provider->getPatchersDirectories());
        $this->assertSame('BepInEx/config', $provider->getConfigDirectory());
    }
}
