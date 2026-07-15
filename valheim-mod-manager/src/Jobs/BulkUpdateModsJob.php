<?php

namespace Chr0mX\ValheimModManager\Jobs;

use App\Models\Server;
use App\Models\User;
use Chr0mX\ValheimModManager\Contracts\GameProviderInterface;
use Chr0mX\ValheimModManager\Facades\ValheimModManager;
use Chr0mX\ValheimModManager\Services\ModInstaller;
use Chr0mX\ValheimModManager\Services\ModMetadataStore;
use Chr0mX\ValheimModManager\Services\ThunderstoreService;
use Chr0mX\ValheimModManager\Support\ProgressReporter;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Backs both the "Update All" toolbar button and the "Update Selected" bulk
 * action. Runs sequentially (not chained queue jobs) so a single progress
 * token can report "n / total" while it works through the list.
 */
class BulkUpdateModsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /**
     * @param  string[]  $keys  ModMetadataStore keys to update
     */
    public function __construct(
        public Server $server,
        public GameProviderInterface $provider,
        public array $keys,
        public bool $overwriteConfig = false,
        public ?string $progressToken = null,
        public ?int $notifyUserId = null,
    ) {
        // Deliberately NOT dispatched to a named queue: Pelican's official
        // Docker image runs its queue worker as `queue:work --tries=3` with
        // no `--queue=` flag, which only processes the connection's default
        // queue. A named queue here would silently never be picked up -
        // the job just sits forever with no error, log entry, or visible
        // effect, which is exactly what looked like "the button doesn't
        // work" with nothing in the logs.
    }

    public function handle(ModInstaller $installer, ModMetadataStore $metadataStore, ThunderstoreService $thunderstore): void
    {
        $total = count($this->keys);
        $updated = 0;
        $failed = [];

        try {
            foreach ($this->keys as $index => $key) {
                $this->reportBatchProgress($index + 1, $total);

                $entry = $metadataStore->find($this->server, $this->provider, $key);
                if ($entry === null) {
                    continue;
                }

                $package = $thunderstore->findPackage($this->provider, $entry['namespace'], $entry['name']);
                $latestVersion = $package?->latestVersion();

                if ($package === null || $latestVersion === null || $latestVersion->versionNumber === $entry['version']) {
                    continue;
                }

                try {
                    $installer->install($this->server, $this->provider, $package, $latestVersion, $this->overwriteConfig, previousEntry: $entry);
                    $updated++;
                } catch (\Throwable $exception) {
                    report($exception);
                    $failed[] = $entry['name'];
                }
            }

            if ($this->progressToken !== null) {
                ProgressReporter::report($this->progressToken, 'finished');
            }

            $this->notifySummary($updated, $failed);
        } finally {
            // Runs on a queue worker, possibly well after the request that
            // dispatched this job returned, so the page's own cache-forget
            // calls (made at dispatch time, before this ran) can't have
            // covered this - without this, the Installed tab could keep
            // serving a pre-update cached scan for up to 15 seconds.
            ValheimModManager::forgetInstalledModsCache($this->server, $this->provider);
        }
    }

    protected function reportBatchProgress(int $current, int $total): void
    {
        if ($this->progressToken !== null) {
            ProgressReporter::report($this->progressToken, "installing ($current/$total)");
        }
    }

    /**
     * @param  string[]  $failed
     */
    protected function notifySummary(int $updated, array $failed): void
    {
        if ($this->notifyUserId === null) {
            return;
        }

        $user = User::find($this->notifyUserId);
        if ($user === null) {
            return;
        }

        $body = "$updated mod(s) updated.";
        if (!empty($failed)) {
            $body .= ' Failed: ' . implode(', ', $failed);
        }

        Notification::make()
            ->title(trans('valheim-mod-manager::strings.notifications.update_success'))
            ->body($body)
            ->color(empty($failed) ? 'success' : 'warning')
            ->sendToDatabase($user);
    }
}
