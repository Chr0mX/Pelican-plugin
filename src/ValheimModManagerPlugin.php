<?php

namespace Chr0mX\ValheimModManager;

use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;
use Chr0mX\ValheimModManager\Games\ValheimProvider;
use Chr0mX\ValheimModManager\Services\GameProviderRegistry;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Panel;

class ValheimModManagerPlugin implements HasPluginSettings, Plugin
{
    use EnvironmentWriterTrait;

    public function getId(): string
    {
        return 'valheim-mod-manager';
    }

    public function register(Panel $panel): void
    {
        app(GameProviderRegistry::class)->register(new ValheimProvider());

        $id = str($panel->getId())->title();

        $panel->discoverPages(plugin_path($this->getId(), "src/Filament/$id/Pages"), "Chr0mX\\ValheimModManager\\Filament\\$id\\Pages");
    }

    public function boot(Panel $panel): void {}

    /**
     * @return array<int, mixed>
     */
    public function getSettingsForm(): array
    {
        return [
            TextInput::make('thunderstore_api_url')
                ->label('Thunderstore API endpoint')
                ->url()
                ->required()
                ->default(fn () => config('valheim-mod-manager.thunderstore_api_url')),
            TextInput::make('thunderstore_community')
                ->label('Thunderstore community slug')
                ->required()
                ->default(fn () => config('valheim-mod-manager.thunderstore_community')),
            TextInput::make('default_game')
                ->label('Default game')
                ->required()
                ->default(fn () => config('valheim-mod-manager.default_game')),
            TextInput::make('default_install_directory')
                ->label('Default install directory')
                ->required()
                ->default(fn () => config('valheim-mod-manager.default_install_directory')),
            Toggle::make('auto_update_check')
                ->label('Automatic update checking')
                ->inline(false)
                ->default(fn () => config('valheim-mod-manager.auto_update_check')),
            Toggle::make('auto_refresh_after_install')
                ->label('Auto-refresh after install')
                ->inline(false)
                ->default(fn () => config('valheim-mod-manager.auto_refresh_after_install')),
            TextInput::make('download_timeout')
                ->label('Download timeout (seconds)')
                ->numeric()
                ->minValue(5)
                ->maxValue(600)
                ->required()
                ->default(fn () => config('valheim-mod-manager.download_timeout')),
            TextInput::make('temporary_directory')
                ->label('Temporary directory')
                ->required()
                ->default(fn () => config('valheim-mod-manager.temporary_directory')),
            TextInput::make('required_tag')
                ->label('Required egg tag')
                ->helperText('Servers whose egg is not tagged with this value will never see the Mods page.')
                ->required()
                ->default(fn () => config('valheim-mod-manager.required_tag')),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveSettings(array $data): void
    {
        $this->writeToEnvironment([
            'VALHEIM_MOD_MANAGER_THUNDERSTORE_API_URL' => $data['thunderstore_api_url'],
            'VALHEIM_MOD_MANAGER_THUNDERSTORE_COMMUNITY' => $data['thunderstore_community'],
            'VALHEIM_MOD_MANAGER_DEFAULT_GAME' => $data['default_game'],
            'VALHEIM_MOD_MANAGER_DEFAULT_INSTALL_DIRECTORY' => $data['default_install_directory'],
            'VALHEIM_MOD_MANAGER_AUTO_UPDATE_CHECK' => $data['auto_update_check'] ? 'true' : 'false',
            'VALHEIM_MOD_MANAGER_AUTO_REFRESH_AFTER_INSTALL' => $data['auto_refresh_after_install'] ? 'true' : 'false',
            'VALHEIM_MOD_MANAGER_DOWNLOAD_TIMEOUT' => $data['download_timeout'],
            'VALHEIM_MOD_MANAGER_TEMPORARY_DIRECTORY' => $data['temporary_directory'],
            'VALHEIM_MOD_MANAGER_REQUIRED_TAG' => $data['required_tag'],
        ]);

        Notification::make()
            ->title(trans('valheim-mod-manager::strings.notifications.settings_saved'))
            ->success()
            ->send();
    }
}
