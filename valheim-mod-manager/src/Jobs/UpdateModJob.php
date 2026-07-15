<?php

namespace Chr0mX\ValheimModManager\Jobs;

use App\Models\Server;
use App\Models\User;
use Chr0mX\ValheimModManager\Contracts\GameProviderInterface;
use Chr0mX\ValheimModManager\DTO\ThunderstorePackageData;
use Chr0mX\ValheimModManager\DTO\ThunderstoreVersionData;
use Chr0mX\ValheimModManager\Services\ModInstaller;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Semantically distinct from InstallModJob so queue dashboards and logs
 * clearly show "update" activity, but both delegate to the same
 * ModInstaller::install(), which already knows how to replace an existing
 * package's files while preserving BepInEx/config.
 */
class UpdateModJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $previousEntry
     */
    public function __construct(
        public Server $server,
        public GameProviderInterface $provider,
        public ThunderstorePackageData $package,
        public ThunderstoreVersionData $version,
        public array $previousEntry,
        public bool $overwriteConfig = false,
        public ?string $progressToken = null,
        public ?int $notifyUserId = null,
    ) {
        $this->onQueue('valheim-mod-manager');
    }

    public function handle(ModInstaller $installer): void
    {
        try {
            $installer->install(
                $this->server,
                $this->provider,
                $this->package,
                $this->version,
                $this->overwriteConfig,
                $this->progressToken,
                $this->previousEntry,
            );

            $this->notify(
                trans('valheim-mod-manager::strings.notifications.update_success'),
                trans('valheim-mod-manager::strings.notifications.update_success_body', [
                    'name' => $this->package->name,
                    'version' => $this->version->versionNumber,
                ]),
                'success',
            );
        } catch (\Throwable $exception) {
            report($exception);

            $this->notify(
                trans('valheim-mod-manager::strings.notifications.update_failed'),
                $exception->getMessage(),
                'danger',
            );
        }
    }

    protected function notify(string $title, string $body, string $color): void
    {
        if ($this->notifyUserId === null) {
            return;
        }

        $notification = Notification::make()->title($title)->body($body);

        match ($color) {
            'success' => $notification->success(),
            'danger' => $notification->danger(),
            default => $notification->info(),
        };

        $user = User::find($this->notifyUserId);

        if ($user !== null) {
            $notification->sendToDatabase($user);
        }
    }
}
