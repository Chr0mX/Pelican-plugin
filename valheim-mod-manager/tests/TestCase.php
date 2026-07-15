<?php

namespace Chr0mX\ValheimModManager\Tests;

use Chr0mX\ValheimModManager\Providers\ValheimModManagerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ValheimModManagerServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // The real `servers` table lives in the host panel; the fake table
        // here only exists so ModActivityLog's foreign key has somewhere to
        // point during tests.
        $app['config']->set('valheim-mod-manager', [
            'thunderstore_api_url' => 'https://thunderstore.io',
            'thunderstore_community' => 'valheim',
            'default_game' => 'valheim',
            'default_install_directory' => 'BepInEx/plugins',
            'auto_update_check' => true,
            'auto_refresh_after_install' => true,
            'download_timeout' => 60,
            'temporary_directory' => 'BepInEx/.valheim-mod-manager-tmp',
            'required_tag' => 'bepinex-mods',
            'metadata_file' => '.valheim-mod-manager.json',
        ]);
    }
}
