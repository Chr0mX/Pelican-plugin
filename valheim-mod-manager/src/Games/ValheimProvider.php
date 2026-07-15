<?php

namespace Chr0mX\ValheimModManager\Games;

use App\Models\Server;
use Chr0mX\ValheimModManager\Contracts\GameProviderInterface;

class ValheimProvider implements GameProviderInterface
{
    public function getSlug(): string
    {
        return 'valheim';
    }

    public function getDisplayName(): string
    {
        return 'Valheim';
    }

    public function matchesServer(Server $server): bool
    {
        $server->loadMissing('egg');

        $tags = $server->egg->tags ?? [];

        return in_array(config('valheim-mod-manager.required_tag', 'bepinex-mods'), $tags, true);
    }

    public function getThunderstoreCommunity(): string
    {
        return config('valheim-mod-manager.thunderstore_community', 'valheim');
    }

    public function getPluginsDirectories(): array
    {
        return ['BepInEx/plugins'];
    }

    public function getPatchersDirectories(): array
    {
        return ['BepInEx/patchers'];
    }

    public function getConfigDirectory(): string
    {
        return 'BepInEx/config';
    }

    public function getDefaultInstallDirectory(): string
    {
        return config('valheim-mod-manager.default_install_directory', 'BepInEx/plugins');
    }
}
