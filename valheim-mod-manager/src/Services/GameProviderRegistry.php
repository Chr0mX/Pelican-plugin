<?php

namespace Chr0mX\ValheimModManager\Services;

use App\Models\Server;
use Chr0mX\ValheimModManager\Contracts\GameProviderInterface;

/**
 * Central registry of GameProviderInterface implementations. This plugin
 * registers ValheimProvider by default; other plugins (or a future version
 * of this one) can register additional providers for other Thunderstore
 * backed games without touching any existing code:
 *
 *     app(GameProviderRegistry::class)->register(new LethalCompanyProvider());
 */
class GameProviderRegistry
{
    /** @var array<int, GameProviderInterface> */
    protected array $providers = [];

    public function register(GameProviderInterface $provider): void
    {
        $this->providers[$provider->getSlug()] = $provider;
    }

    /**
     * @return array<int, GameProviderInterface>
     */
    public function all(): array
    {
        return array_values($this->providers);
    }

    public function get(string $slug): ?GameProviderInterface
    {
        return $this->providers[$slug] ?? null;
    }

    public function forServer(Server $server): ?GameProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->matchesServer($server)) {
                return $provider;
            }
        }

        return null;
    }
}
