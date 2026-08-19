<?php

namespace Chr0mX\PfSenseAutoForward\Filament\Admin\Pages;

use BackedEnum;
use Chr0mX\PfSenseAutoForward\Jobs\ReconcilePortForwardsJob;
use Chr0mX\PfSenseAutoForward\Support\ReconciliationStatus;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Poll;

/**
 * Status/control page: shows the outcome of the last reconciliation
 * (scheduled or manual) and offers a "Sync Now" button. Polls itself every
 * few seconds so a queued sync's result shows up without a manual refresh.
 */
#[Poll('5s')]
class PfSenseForwardingPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static ?string $slug = 'pfsense-forwarding';

    protected static ?int $navigationSort = 50;

    public static function getNavigationLabel(): string
    {
        return trans('pfsense-autoforward::strings.nav.label');
    }

    public function getTitle(): string
    {
        return trans('pfsense-autoforward::strings.page.heading');
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_now')
                ->label(trans('pfsense-autoforward::strings.actions.sync_now'))
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    ReconciliationStatus::markRunning();
                    ReconcilePortForwardsJob::dispatch();

                    Notification::make()
                        ->title(trans('pfsense-autoforward::strings.notifications.sync_queued'))
                        ->body(trans('pfsense-autoforward::strings.notifications.sync_queued_body'))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $status = ReconciliationStatus::get();

        return $schema->components([
            Section::make()
                ->schema([
                    ...(config('pfsense-autoforward.dry_run')
                        ? [TextEntry::make('dry_run_notice')
                            ->hiddenLabel()
                            ->state(trans('pfsense-autoforward::strings.page.dry_run_badge'))
                            ->color('warning')]
                        : []),
                    TextEntry::make('status')
                        ->hiddenLabel()
                        ->state($this->statusLabel($status['state']))
                        ->color($this->statusColor($status['state'])),
                    TextEntry::make('last_run')
                        ->label(trans('pfsense-autoforward::strings.page.last_run'))
                        ->state($status['ran_at']
                            ? Carbon::parse($status['ran_at'])->diffForHumans()
                            : trans('pfsense-autoforward::strings.page.never_run')),
                    TextEntry::make('summary')
                        ->hiddenLabel()
                        ->state($this->summaryLine($status['summary']))
                        ->visible($status['summary'] !== null),
                ]),
        ]);
    }

    private function statusLabel(string $state): string
    {
        return trans('pfsense-autoforward::strings.page.status.' . ($state === 'running' ? 'running' : ($state === 'failed' ? 'failed' : ($state === 'success' ? 'success' : 'idle'))));
    }

    private function statusColor(string $state): string
    {
        return match ($state) {
            'running' => 'info',
            'success' => 'success',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    /**
     * @param  array<string, mixed>|null  $summary
     */
    private function summaryLine(?array $summary): string
    {
        if ($summary === null) {
            return '';
        }

        return implode(' · ', array_filter([
            trans('pfsense-autoforward::strings.page.summary.created', ['count' => $summary['created'] ?? 0]),
            trans('pfsense-autoforward::strings.page.summary.removed', ['count' => $summary['removed'] ?? 0]),
            trans('pfsense-autoforward::strings.page.summary.unchanged', ['count' => $summary['unchanged'] ?? 0]),
            ($summary['failed'] ?? 0) > 0
                ? trans('pfsense-autoforward::strings.page.summary.failed', ['count' => $summary['failed']])
                : null,
        ]));
    }
}
