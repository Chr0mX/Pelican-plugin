<?php

namespace Chr0mX\PfSenseAutoForward\Tests;

use Chr0mX\PfSenseAutoForward\Providers\PfSenseAutoForwardServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PfSenseAutoForwardServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        // The real `allocations` table lives in the host panel; a bare
        // stand-in is created here only so PortForwardOverride's foreign
        // key has somewhere to point during tests.
        $this->app['db']->connection()->getSchemaBuilder()->create('allocations', function ($table) {
            $table->id();
        });

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

        $app['config']->set('pfsense-autoforward', [
            'pfsense_url' => 'https://pfsense.example.test',
            'pfsense_api_key' => 'test-api-key',
            'pfsense_interface' => 'wan',
            'verify_tls' => true,
            'default_protocol' => 'tcp/udp',
            'disable_when_offline' => true,
            'required_egg_tag' => null,
            'allowed_node_ids' => null,
            'reconcile_interval_minutes' => 5,
            'dry_run' => false,
            'request_timeout' => 15,
        ]);
    }
}
