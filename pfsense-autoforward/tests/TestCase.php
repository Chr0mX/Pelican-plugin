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

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');

        $app['config']->set('pfsense-autoforward', [
            'pfsense_url' => 'https://pfsense.example.test',
            'pfsense_api_key' => 'test-api-key',
            'pfsense_interface' => 'wan',
            'default_protocol' => 'tcp/udp',
            'required_egg_tag' => null,
            'reconcile_interval_minutes' => 5,
            'dry_run' => false,
            'request_timeout' => 15,
        ]);
    }
}
