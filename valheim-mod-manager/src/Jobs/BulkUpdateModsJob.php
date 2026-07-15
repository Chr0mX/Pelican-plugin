<?php

namespace Chr0mX\ValheimModManager\Jobs;

use App\Models\Server;
use App\Models\User;
use Chr0mX\ValheimModManager\Contracts\GameProviderInterface;
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
        $this->onQueue('valheim-mod-manager');
    }

    public function handle(ModInstaller $installer, ModMetadataStore $metadataStore, ThunderstoreService $thunderstore): void
    {
        $total = count($this->keys);
        $updated = 0;
        $failed = [];

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
