<?php

namespace Chr0mX\PfSenseAutoForward\Providers;

use Chr0mX\PfSenseAutoForward\Console\Commands\ReconcilePortForwards;
use Chr0mX\PfSenseAutoForward\Services\AllocationRepository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * Config, translations and migrations are loaded by the panel itself
 * (App\Services\Helpers\PluginService), purely by folder convention - this
 * provider only needs to bind services and hook the scheduler.
 */
class PfSenseAutoForwardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AllocationRepository::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ReconcilePortForwards::class]);
        }

        // The Schedule instance is a container singleton shared with the
        // panel's own Kernel::schedule(), so registering onto it here (via
        // the standard package-provider pattern) reaches the same
        // `artisan schedule:run` the panel already has running - no
        // separate cron entry needed.
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            $minutes = max(1, (int) config('pfsense-autoforward.reconcile_interval_minutes', 5));

            $schedule->command(ReconcilePortForwards::class)
                ->cron("*/{$minutes} * * * *")
                ->name('pfsense-autoforward-reconcile')
                ->withoutOverlapping();
        });
    }
}
