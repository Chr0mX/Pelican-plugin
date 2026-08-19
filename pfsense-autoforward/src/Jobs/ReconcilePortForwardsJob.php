<?php

namespace Chr0mX\PfSenseAutoForward\Jobs;

use Chr0mX\PfSenseAutoForward\DTO\ReconciliationSummary;
use Chr0mX\PfSenseAutoForward\Services\AllocationRepository;
use Chr0mX\PfSenseAutoForward\Services\PfSenseApiClient;
use Chr0mX\PfSenseAutoForward\Services\PortForwardReconciler;
use Chr0mX\PfSenseAutoForward\Support\ReconciliationStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Deliberately dispatched to the default queue only - never call
 * ->onQueue() here. Pelican's official Docker image runs
 * `queue:work --tries=3` with no --queue flag, so a job sent to a named
 * queue is silently never processed.
 */
class ReconcilePortForwardsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function handle(AllocationRepository $allocations): void
    {
        ReconciliationStatus::markRunning();

        $baseUrl = config('pfsense-autoforward.pfsense_url');
        $apiKey = config('pfsense-autoforward.pfsense_api_key');

        if (blank($baseUrl) || blank($apiKey)) {
            $summary = new ReconciliationSummary(
                failed: 1,
                errors: [trans('pfsense-autoforward::strings.errors.missing_config')],
            );

            ReconciliationStatus::markComplete($summary);
            Log::warning('pfsense-autoforward: sync skipped, missing pfSense URL/API key.');

            return;
        }

        $client = new PfSenseApiClient(
            baseUrl: $baseUrl,
            apiKey: $apiKey,
            interface: (string) config('pfsense-autoforward.pfsense_interface', 'wan'),
            timeout: (int) config('pfsense-autoforward.request_timeout', 15),
        );

        $reconciler = new PortForwardReconciler(
            allocations: $allocations,
            client: $client,
            dryRun: (bool) config('pfsense-autoforward.dry_run', false),
        );

        $summary = $reconciler->reconcile();

        ReconciliationStatus::markComplete($summary);

        if ($summary->hasFailures()) {
            Log::warning('pfsense-autoforward: sync completed with failures.', $summary->toArray());
        } else {
            Log::info('pfsense-autoforward: sync completed.', $summary->toArray());
        }
    }
}
