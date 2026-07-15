<?php

namespace Chr0mX\ValheimModManager\Filament\Server\Pages;

use App\Models\Server;
use BackedEnum;
use Chr0mX\ValheimModManager\Contracts\GameProviderInterface;
use Chr0mX\ValheimModManager\DTO\InstalledModData;
use Chr0mX\ValheimModManager\DTO\ThunderstoreDependency;
use Chr0mX\ValheimModManager\DTO\ThunderstorePackageData;
use Chr0mX\ValheimModManager\Enums\ModStatus;
use Chr0mX\ValheimModManager\Facades\ValheimModManager;
use Chr0mX\ValheimModManager\Jobs\BulkUpdateModsJob;
use Chr0mX\ValheimModManager\Jobs\InstallModJob;
use Chr0mX\ValheimModManager\Jobs\UpdateModJob;
use Chr0mX\ValheimModManager\Models\InstalledMod;
use Chr0mX\ValheimModManager\Models\ModActivityLog;
use Chr0mX\ValheimModManager\Services\GameProviderRegistry;
use Chr0mX\ValheimModManager\Services\ModMetadataStore;
use Chr0mX\ValheimModManager\Services\ModRemovalService;
use Chr0mX\ValheimModManager\Services\ModToggleService;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Concerns\HasTabs;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ModsPage extends Page implements HasTable
{
    use HasTabs;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $slug = 'mods';

    protected static ?int $navigationSort = 35;

    /** @var array<string, InstalledModData>|null */
    protected ?array $installedModsCache = null;

    public static function canAccess(): bool
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        if ($server->isInConflictState()) {
            return false;
        }

        return parent::canAccess() && app(GameProviderRegistry::class)->forServer($server) !== null;
    }

    public static function getNavigationLabel(): string
    {
        return trans('valheim-mod-manager::strings.nav.label');
    }

    public function getTitle(): string
    {
        return trans('valheim-mod-manager::strings.nav.label');
    }

    public function mount(): void
    {
        $this->loadDefaultActiveTab();
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'installed' => Tab::make(trans('valheim-mod-manager::strings.tabs.installed')),
            'browse' => Tab::make(trans('valheim-mod-manager::strings.tabs.browse')),
            'activity' => Tab::make(trans('valheim-mod-manager::strings.tabs.activity')),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getTabsContentComponent(),
            EmbeddedTable::make(),
        ]);
    }

    protected function server(): Server
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $server;
    }

    protected function provider(): GameProviderInterface
    {
        /** @var GameProviderInterface $provider */
        $provider = ValheimModManager::providerForServer($this->server());

        return $provider;
    }

    /**
     * @return array<string, InstalledModData>
     */
    protected function installedMods(): array
    {
        if ($this->installedModsCache === null) {
            $mods = ValheimModManager::installedMods($this->server(), $this->provider());
            $this->installedModsCache = collect($mods)->keyBy('key')->all();
        }

        return $this->installedModsCache;
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label(trans('valheim-mod-manager::strings.toolbar.refresh'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    $this->installedModsCache = null;
                    $this->resetTable();

                    Notification::make()
                        ->title(trans('valheim-mod-manager::strings.notifications.refresh_success'))
                        ->success()
                        ->send();
                }),
            Action::make('search_thunderstore')
                ->label(trans('valheim-mod-manager::strings.toolbar.search_thunderstore'))
                ->icon('heroicon-o-magnifying-glass')
                ->visible(fn () => $this->activeTab !== 'browse')
                ->action(function () {
                    $this->activeTab = 'browse';
                    $this->resetTable();
                }),
            Action::make('update_all')
                ->label(trans('valheim-mod-manager::strings.toolbar.update_all'))
                ->icon('heroicon-o-arrow-up-circle')
                ->color('warning')
                ->visible(fn () => $this->activeTab === 'installed')
                ->action(fn () => $this->updateAll()),
            Action::make('settings')
                ->label(trans('valheim-mod-manager::strings.toolbar.settings'))
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->visible(fn () => (bool) (user()?->root_admin ?? false))
                ->url(fn () => Filament::getPanel('admin')?->getUrl()),
        ];
    }

    public function table(Table $table): Table
    {
        return match ($this->activeTab) {
            'browse' => $this->browseTable($table),
            'activity' => $this->activityTable($table),
            default => $this->installedTable($table),
        };
    }

    protected function installedTable(Table $table): Table
    {
        $mods = $this->installedMods();

        return $table
            ->query(InstalledMod::forServer($this->server(), array_values($mods)))
            ->defaultSort('name')
            ->searchable()
            ->columns([
                TextColumn::make('name')
                    ->label(trans('valheim-mod-manager::strings.table.columns.name'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (InstalledMod $record) => $record->namespace),
                TextColumn::make('version')
                    ->label(trans('valheim-mod-manager::strings.table.columns.version'))
                    ->sortable(),
                TextColumn::make('latest_version')
                    ->label(trans('valheim-mod-manager::strings.table.columns.latest_version'))
                    ->toggleable()
                    ->color(fn (InstalledMod $record) => $record->update_available ? 'warning' : null),
                TextColumn::make('author')
                    ->label(trans('valheim-mod-manager::strings.table.columns.author'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(trans('valheim-mod-manager::strings.table.columns.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('last_updated')
                    ->label(trans('valheim-mod-manager::strings.table.columns.last_updated'))
                    ->formatStateUsing(fn (?string $state) => $state ? \Illuminate\Support\Carbon::parse($state)->diffForHumans() : '—')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('')
                    ->iconButton()
                    ->icon('heroicon-o-information-circle')
                    ->modalHeading(fn (InstalledMod $record) => $record->name)
                    ->modalSubmitAction(false)
                    ->schema(fn (InstalledMod $record) => $this->detailsSchema($record)),
                Action::make('update')
                    ->label(trans('valheim-mod-manager::strings.actions.update'))
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('warning')
                    ->visible(fn (InstalledMod $record) => $record->update_available)
                    ->requiresConfirmation()
                    ->modalHeading(trans('valheim-mod-manager::strings.modals.update_heading'))
                    ->modalDescription(fn (InstalledMod $record) => trans('valheim-mod-manager::strings.modals.update_description', [
                        'name' => $record->name,
                        'old_version' => $record->version,
                        'new_version' => $record->latest_version,
                    ]))
                    ->schema([
                        Toggle::make('overwrite_config')
                            ->label(trans('valheim-mod-manager::strings.modals.overwrite_config_label'))
                            ->helperText(trans('valheim-mod-manager::strings.modals.overwrite_config_helper'))
                            ->default(false),
                    ])
                    ->action(fn (InstalledMod $record, array $data) => $this->updateMod($record->key, (bool) ($data['overwrite_config'] ?? false))),
                Action::make('enable')
                    ->label(trans('valheim-mod-manager::strings.actions.enable'))
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->visible(fn (InstalledMod $record) => $record->status === ModStatus::Disabled)
                    ->action(fn (InstalledMod $record) => $this->toggleMod($record->key, false)),
                Action::make('disable')
                    ->label(trans('valheim-mod-manager::strings.actions.disable'))
                    ->icon('heroicon-o-pause-circle')
                    ->color('gray')
                    ->visible(fn (InstalledMod $record) => in_array($record->status, [ModStatus::Installed, ModStatus::UpdateAvailable], true))
                    ->requiresConfirmation()
                    ->action(fn (InstalledMod $record) => $this->toggleMod($record->key, true)),
                Action::make('remove')
                    ->label(trans('valheim-mod-manager::strings.actions.remove'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(trans('valheim-mod-manager::strings.modals.remove_heading'))
                    ->modalDescription(fn (InstalledMod $record) => trans('valheim-mod-manager::strings.modals.remove_description', ['name' => $record->name]))
                    ->action(fn (InstalledMod $record) => $this->removeMod($record->key)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('update_selected')
                        ->label(trans('valheim-mod-manager::strings.actions.update_selected'))
                        ->icon('heroicon-o-arrow-up-circle')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $this->bulkUpdate($records)),
                    BulkAction::make('remove_selected')
                        ->label(trans('valheim-mod-manager::strings.actions.remove_selected'))
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $this->bulkToggleOrRemove($records, 'remove')),
                    BulkAction::make('enable_selected')
                        ->label(trans('valheim-mod-manager::strings.actions.enable_selected'))
                        ->icon('heroicon-o-play-circle')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $this->bulkToggleOrRemove($records, 'enable')),
                    BulkAction::make('disable_selected')
                        ->label(trans('valheim-mod-manager::strings.actions.disable_selected'))
                        ->icon('heroicon-o-pause-circle')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $this->bulkToggleOrRemove($records, 'disable')),
                ]),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    protected function detailsSchema(InstalledMod $record): array
    {
        $dto = $record->toDto();

        $dependencies = $dto?->dependencies ?? [];
        $dependencyList = empty($dependencies)
            ? trans('valheim-mod-manager::strings.infolist.no_dependencies')
            : implode("\n", array_map(fn (ThunderstoreDependency $dependency) => '- ' . $dependency->toString(), $dependencies));

        return [
            TextEntry::make('description')
                ->label(trans('valheim-mod-manager::strings.infolist.description'))
                ->state($dto?->description ?: '—'),
            TextEntry::make('dependencies')
                ->label(trans('valheim-mod-manager::strings.infolist.dependencies'))
                ->state($dependencyList),
            TextEntry::make('installed_path')
                ->label(trans('valheim-mod-manager::strings.infolist.installed_path'))
                ->state($dto ? $dto->directory . '/' . implode(', ', $dto->files) : '—'),
            TextEntry::make('latest_release')
                ->label(trans('valheim-mod-manager::strings.infolist.latest_release'))
                ->state($record->latest_version ?? '—'),
        ];
    }

    protected function browseTable(Table $table): Table
    {
        $provider = $this->provider();
        $installed = $this->installedMods();

        return $table
            ->records(function (?string $search, int $page) use ($provider) {
                $result = ValheimModManager::browse($provider, $search, $page);

                return new LengthAwarePaginator(
                    array_map(static fn (ThunderstorePackageData $package): array => $package->toTableRow(), $result->items()),
                    $result->total(),
                    $result->perPage(),
                    $result->currentPage(),
                );
            })
            ->paginated([20])
            ->columns([
                ImageColumn::make('icon')
                    ->label(''),
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (array $record): string => Str::limit($record['description'], 120)),
                TextColumn::make('owner')
                    ->label(trans('valheim-mod-manager::strings.table.columns.author'))
                    ->url(fn (array $record): string => "https://thunderstore.io/package/{$record['owner']}/", true),
                TextColumn::make('downloads')
                    ->label(trans('valheim-mod-manager::strings.table.columns.downloads'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->numeric(),
                TextColumn::make('latest_version')
                    ->label(trans('valheim-mod-manager::strings.table.columns.latest_version'))
                    ->placeholder('—'),
            ])
            ->recordUrl(fn (array $record): string => $record['package_url'], true)
            ->recordActions([
                Action::make('install')
                    ->label(fn (array $record): string => $this->installButtonLabel($record, $installed))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->disabled(fn (array $record): bool => $this->isFullyUpToDate($record, $installed))
                    ->requiresConfirmation()
                    ->schema([
                        Toggle::make('overwrite_config')
                            ->label(trans('valheim-mod-manager::strings.modals.overwrite_config_label'))
                            ->helperText(trans('valheim-mod-manager::strings.modals.overwrite_config_helper'))
                            ->default(false),
                    ])
                    ->action(fn (array $record, array $data) => $this->installLatest($record, (bool) ($data['overwrite_config'] ?? false))),
            ]);
    }

    /**
     * @param  array{owner: string, name: string, latest_version: string}  $record
     * @param  array<string, InstalledModData>  $installed
     */
    protected function installButtonLabel(array $record, array $installed): string
    {
        return $this->isFullyUpToDate($record, $installed)
            ? trans('valheim-mod-manager::strings.status.installed')
            : trans('valheim-mod-manager::strings.actions.install');
    }

    /**
     * @param  array{owner: string, name: string, latest_version: string}  $record
     * @param  array<string, InstalledModData>  $installed
     */
    protected function isFullyUpToDate(array $record, array $installed): bool
    {
        $key = ModMetadataStore::key($record['owner'], $record['name']);
        $existing = $installed[$key] ?? null;

        return $existing !== null && $existing->version === $record['latest_version'];
    }

    protected function activityTable(Table $table): Table
    {
        return $table
            ->query(ModActivityLog::query()->where('server_id', $this->server()->id))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->since()
                    ->sortable(),
                TextColumn::make('summary')
                    ->label('Activity')
                    ->getStateUsing(fn (ModActivityLog $record) => $record->summary())
                    ->searchable(['mod_name']),
                TextColumn::make('action')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('from_version')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('to_version')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }

    protected function toggleMod(string $key, bool $disabled): void
    {
        try {
            app(ModToggleService::class)->toggle($this->server(), $this->provider(), $key, $disabled);

            Notification::make()
                ->title(trans($disabled ? 'valheim-mod-manager::strings.notifications.disable_success' : 'valheim-mod-manager::strings.notifications.enable_success'))
                ->success()
                ->send();
        } catch (Exception $exception) {
            Notification::make()
                ->title(trans('valheim-mod-manager::strings.notifications.toggle_failed'))
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }

        $this->installedModsCache = null;
        $this->resetTable();
    }

    protected function removeMod(string $key): void
    {
        $mod = $this->installedMods()[$key] ?? null;

        try {
            app(ModRemovalService::class)->remove($this->server(), $this->provider(), $key);

            Notification::make()
                ->title(trans('valheim-mod-manager::strings.notifications.remove_success'))
                ->body(trans('valheim-mod-manager::strings.notifications.remove_success_body', ['name' => $mod?->name ?? $key]))
                ->success()
                ->send();
        } catch (Exception $exception) {
            Notification::make()
                ->title(trans('valheim-mod-manager::strings.notifications.remove_failed'))
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }

        $this->installedModsCache = null;
        $this->resetTable();
    }

    protected function updateMod(string $key, bool $overwriteConfig): void
    {
        $mod = $this->installedMods()[$key] ?? null;
        $provider = $this->provider();

        if ($mod === null || $mod->namespace === null) {
            return;
        }

        $package = ValheimModManager::findPackage($provider, $mod->namespace, $mod->name);
        $latestVersion = $package?->latestVersion();
        $previousEntry = app(ModMetadataStore::class)->find($this->server(), $provider, $key);

        if ($package === null || $latestVersion === null || $previousEntry === null) {
            Notification::make()
                ->title(trans('valheim-mod-manager::strings.notifications.update_failed'))
                ->danger()
                ->send();

            return;
        }

        UpdateModJob::dispatch(
            $this->server(),
            $provider,
            $package,
            $latestVersion,
            $previousEntry,
            $overwriteConfig,
            "update:{$this->server()->id}:$key",
            user()?->id,
        );

        Notification::make()
            ->title(trans('valheim-mod-manager::strings.notifications.update_queued'))
            ->success()
            ->send();
    }

    /**
     * @param  array{owner: string, name: string}  $record
     */
    protected function installLatest(array $record, bool $overwriteConfig): void
    {
        $provider = $this->provider();
        $package = ValheimModManager::findPackage($provider, $record['owner'], $record['name']);
        $latestVersion = $package?->latestVersion();

        if ($package === null || $latestVersion === null) {
            Notification::make()
                ->title(trans('valheim-mod-manager::strings.notifications.install_failed'))
                ->danger()
                ->send();

            return;
        }

        $key = ModMetadataStore::key($package->owner, $package->name);
        $previousEntry = app(ModMetadataStore::class)->find($this->server(), $provider, $key);

        InstallModJob::dispatch(
            $this->server(),
            $provider,
            $package,
            $latestVersion,
            $overwriteConfig,
            "install:{$this->server()->id}:$key",
            $previousEntry,
            user()?->id,
        );

        Notification::make()
            ->title(trans('valheim-mod-manager::strings.notifications.install_queued'))
            ->body(trans('valheim-mod-manager::strings.notifications.install_queued_body', [
                'name' => $package->name,
                'version' => $latestVersion->versionNumber,
            ]))
            ->success()
            ->send();
    }

    protected function updateAll(): void
    {
        $keys = collect($this->installedMods())
            ->filter(fn (InstalledModData $mod) => $mod->updateAvailable())
            ->pluck('key')
            ->values()
            ->all();

        if (empty($keys)) {
            Notification::make()
                ->title(trans('valheim-mod-manager::strings.notifications.no_updates'))
                ->success()
                ->send();

            return;
        }

        BulkUpdateModsJob::dispatch(
            $this->server(),
            $this->provider(),
            $keys,
            false,
            "bulk-update:{$this->server()->id}",
            user()?->id,
        );

        Notification::make()
            ->title(trans('valheim-mod-manager::strings.notifications.update_all_queued'))
            ->success()
            ->send();
    }

    protected function bulkUpdate(Collection $records): void
    {
        $keys = $records->pluck('key')->values()->all();

        if (empty($keys)) {
            return;
        }

        BulkUpdateModsJob::dispatch(
            $this->server(),
            $this->provider(),
            $keys,
            false,
            "bulk-update:{$this->server()->id}",
            user()?->id,
        );

        Notification::make()
            ->title(trans('valheim-mod-manager::strings.notifications.update_all_queued'))
            ->success()
            ->send();
    }

    protected function bulkToggleOrRemove(Collection $records, string $action): void
    {
        $provider = $this->provider();
        $server = $this->server();

        foreach ($records as $record) {
            try {
                match ($action) {
                    'remove' => app(ModRemovalService::class)->remove($server, $provider, $record->key),
                    'enable' => app(ModToggleService::class)->toggle($server, $provider, $record->key, false),
                    'disable' => app(ModToggleService::class)->toggle($server, $provider, $record->key, true),
                };
            } catch (Exception $exception) {
                report($exception);
            }
        }

        $this->installedModsCache = null;
        $this->resetTable();
    }
}
