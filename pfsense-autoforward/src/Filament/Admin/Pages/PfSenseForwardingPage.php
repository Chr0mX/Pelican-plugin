<?php

namespace Chr0mX\PfSenseAutoForward\Filament\Admin\Pages;

use BackedEnum;
use Chr0mX\PfSenseAutoForward\Jobs\ReconcilePortForwardsJob;
use Chr0mX\PfSenseAutoForward\Services\PortForwardOverrideService;
use Chr0mX\PfSenseAutoForward\Support\ReconciliationStatus;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Poll;

/**
 * Status/control page: shows the outcome of the last reconciliation
 * (scheduled or manual), a "Currently mapped" table with an inline enable/
 * disable toggle and forward-type select per allocation, and a "Sync Now"
 * button. Polls itself every few seconds so a queued sync's result shows
 * up without a manual refresh.
 */
#[Poll('5s')]
class PfSenseForwardingPage extends Page implements HasTable
{
    use InteractsWithTable;

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
                ->action(fn () => $this->queueSync(
                    trans('pfsense-autoforward::strings.notifications.sync_queued'),
                    trans('pfsense-autoforward::strings.notifications.sync_queued_body'),
                )),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $status = ReconciliationStatus::get();
        $summary = $status['summary'];
        $removedLines = $summary['removed_lines'] ?? [];
        $errors = $summary['errors'] ?? [];

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
                        ->state($this->summaryLine($summary))
                        ->visible($summary !== null),
                ]),
            EmbeddedTable::make(),
            Section::make(trans('pfsense-autoforward::strings.page.removed_heading'))
                ->schema([
                    TextEntry::make('removed_lines')
                        ->hiddenLabel()
                        ->state($removedLines)
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->color('warning'),
                ])
                ->visible($removedLines !== []),
            Section::make(trans('pfsense-autoforward::strings.page.errors_heading'))
                ->schema([
                    TextEntry::make('errors')
                        ->hiddenLabel()
                        ->state($errors)
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->color('danger'),
                ])
                ->visible($errors !== []),
        ]);
    }

    public function table(Table $table): Table
    {
        $rows = collect(ReconciliationStatus::get()['summary']['mapped_rows'] ?? []);

        return $table
            ->heading(trans('pfsense-autoforward::strings.page.mapped_heading'))
            ->records(fn () => $rows)
            ->columns([
                TextColumn::make('node')
                    ->label(trans('pfsense-autoforward::strings.page.table.node'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('server')
                    ->label(trans('pfsense-autoforward::strings.page.table.server'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('port')
                    ->label(trans('pfsense-autoforward::strings.page.table.port'))
                    ->sortable(),
                SelectColumn::make('protocol')
                    ->label(trans('pfsense-autoforward::strings.page.table.protocol'))
                    ->options(trans('pfsense-autoforward::strings.page.protocol_options'))
                    ->selectablePlaceholder(false)
                    ->updateStateUsing(function (array $record, string $state) {
                        app(PortForwardOverrideService::class)->setProtocol((int) $record['allocation_id'], $state);
                        $this->queueSync(
                            trans('pfsense-autoforward::strings.notifications.override_saved'),
                            trans('pfsense-autoforward::strings.notifications.override_saved_body'),
                        );

                        return $state;
                    }),
                IconColumn::make('server_active')
                    ->label(trans('pfsense-autoforward::strings.page.table.server_status'))
                    ->boolean()
                    ->trueIcon('heroicon-o-play-circle')
                    ->falseIcon('heroicon-o-stop-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                ToggleColumn::make('rule_enabled')
                    ->label(trans('pfsense-autoforward::strings.page.table.rule_status'))
                    ->updateStateUsing(function (array $record, bool $state) {
                        app(PortForwardOverrideService::class)->setEnabledOverride((int) $record['allocation_id'], $state);
                        $this->queueSync(
                            trans('pfsense-autoforward::strings.notifications.override_saved'),
                            trans('pfsense-autoforward::strings.notifications.override_saved_body'),
                        );

                        return $state;
                    }),
            ])
            ->recordActions([
                Action::make('reset_override')
                    ->label(trans('pfsense-autoforward::strings.page.table.reset_override'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->action(function (array $record) {
                        app(PortForwardOverrideService::class)->clear((int) $record['allocation_id']);
                        $this->queueSync(
                            trans('pfsense-autoforward::strings.notifications.override_reset'),
                        );
                    }),
            ])
            ->emptyStateHeading(trans('pfsense-autoforward::strings.page.mapped_empty'));
    }

    /**
     * Shared by "Sync Now" and every inline table edit (enable/disable
     * toggle, forward-type select, reset-to-automatic) - all of them need
     * the same thing: persist, then queue a reconciliation so pfSense
     * actually reflects it. Runs on the queue (see ReconcilePortForwardsJob)
     * so a table edit never blocks the request.
     */
    private function queueSync(string $title, ?string $body = null): void
    {
        ReconciliationStatus::markRunning();
        ReconcilePortForwardsJob::dispatch();

        Notification::make()
            ->title($title)
            ->body($body)
            ->success()
            ->send();
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
            ($summary['toggled'] ?? 0) > 0
                ? trans('pfsense-autoforward::strings.page.summary.toggled', ['count' => $summary['toggled']])
                : null,
            trans('pfsense-autoforward::strings.page.summary.unchanged', ['count' => $summary['unchanged'] ?? 0]),
            ($summary['failed'] ?? 0) > 0
                ? trans('pfsense-autoforward::strings.page.summary.failed', ['count' => $summary['failed']])
                : null,
        ]));
    }
}
