<?php

namespace Chr0mX\ValheimModManager\Providers;

use Chr0mX\ValheimModManager\Services\GameProviderRegistry;
use Illuminate\Support\ServiceProvider;

class ValheimModManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GameProviderRegistry::class);
    }
}
