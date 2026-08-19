<?php

namespace Chr0mX\PfSenseAutoForward\Console\Commands;

use Chr0mX\PfSenseAutoForward\Jobs\ReconcilePortForwardsJob;
use Illuminate\Console\Command;

/**
 * Runs synchronously (not queued) - this is invoked by the scheduler, which
 * is already a background process, so there's nothing to gain by queueing
 * it again, and running it inline means a failure is visible directly in
 * `artisan schedule:run` output/logs.
 */
class ReconcilePortForwards extends Command
{
    protected $signature = 'pfsense-autoforward:reconcile';

    protected $description = 'Sync pfSense NAT/pass rules to match current Pelican server allocations.';

    public function handle(): int
    {
        ReconcilePortForwardsJob::dispatchSync();

        $this->info('pfSense reconciliation complete.');

        return self::SUCCESS;
    }
}
