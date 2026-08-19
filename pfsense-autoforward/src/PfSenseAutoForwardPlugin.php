<?php

namespace Chr0mX\PfSenseAutoForward;

use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Panel;

class PfSenseAutoForwardPlugin implements HasPluginSettings, Plugin
{
    use EnvironmentWriterTrait;

    public function getId(): string
    {
        return 'pfsense-autoforward';
    }

    public function register(Panel $panel): void
    {
        $id = str($panel->getId())->title();

        $panel->discoverPages(plugin_path($this->getId(), "src/Filament/$id/Pages"), "Chr0mX\\PfSenseAutoForward\\Filament\\$id\\Pages");
    }

    public function boot(Panel $panel): void {}

    /**
     * Pre-fills the settings form (via Filament's ->fillForm()) with the
     * currently saved values. Implemented from the start, unlike the
     * Valheim Mod Manager plugin where it had to be retrofitted after
     * HasPluginSettings gained this as a required method upstream
     * (pelican-dev/panel#2453) - each field below also keeps its own
     * ->default() closure for panel versions that predate the method.
     *
     * @return array<string, mixed>
     */
    public function getSettingsFormData(): array
    {
        return [
            'pfsense_url' => config('pfsense-autoforward.pfsense_url'),
            'pfsense_api_key' => config('pfsense-autoforward.pfsense_api_key'),
            'pfsense_interface' => config('pfsense-autoforward.pfsense_interface'),
            'verify_tls' => config('pfsense-autoforward.verify_tls'),
            'default_protocol' => config('pfsense-autoforward.default_protocol'),
            'required_egg_tag' => config('pfsense-autoforward.required_egg_tag'),
            'allowed_node_ids' => config('pfsense-autoforward.allowed_node_ids'),
            'reconcile_interval_minutes' => config('pfsense-autoforward.reconcile_interval_minutes'),
            'dry_run' => config('pfsense-autoforward.dry_run'),
            'request_timeout' => config('pfsense-autoforward.request_timeout'),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function getSettingsForm(): array
    {
        return [
            TextInput::make('pfsense_url')
                ->label('pfSense REST API base URL')
                ->helperText('e.g. https://192.168.1.1')
                ->url()
                ->required()
                ->default(fn () => config('pfsense-autoforward.pfsense_url')),
            TextInput::make('pfsense_api_key')
                ->label('pfSense REST API key')
                ->password()
                ->revealable()
                ->required()
                ->default(fn () => config('pfsense-autoforward.pfsense_api_key')),
            TextInput::make('pfsense_interface')
                ->label('pfSense interface')
                ->helperText('The interface pfSense-pkg-RESTAPI identifies NAT/pass rules by, e.g. "wan".')
                ->required()
                ->default(fn () => config('pfsense-autoforward.pfsense_interface')),
            Toggle::make('verify_tls')
                ->label('Verify TLS certificate')
                ->helperText('Turn off only if pfSense is using a self-signed certificate you already trust the identity of - otherwise every sync fails with a TLS error.')
                ->inline(false)
                ->default(fn () => config('pfsense-autoforward.verify_tls')),
            Select::make('default_protocol')
                ->label('Default protocol')
                ->options([
                    'tcp/udp' => 'TCP & UDP',
                    'tcp' => 'TCP only',
                    'udp' => 'UDP only',
                ])
                ->required()
                ->default(fn () => config('pfsense-autoforward.default_protocol')),
            TextInput::make('required_egg_tag')
                ->label('Required egg tag')
                ->helperText('Only servers whose egg carries this tag are forwarded. Leave blank to forward every assigned allocation on the panel.')
                ->default(fn () => config('pfsense-autoforward.required_egg_tag')),
            TextInput::make('allowed_node_ids')
                ->label('Allowed node IDs')
                ->helperText('Comma-separated Pelican node IDs (e.g. "1,3"). Only allocations on these nodes are forwarded. Leave blank to allow every node.')
                ->default(fn () => config('pfsense-autoforward.allowed_node_ids')),
            TextInput::make('reconcile_interval_minutes')
                ->label('Reconcile interval (minutes)')
                ->numeric()
                ->minValue(1)
                ->maxValue(1440)
                ->required()
                ->default(fn () => config('pfsense-autoforward.reconcile_interval_minutes')),
            Toggle::make('dry_run')
                ->label('Dry run')
                ->helperText('Log what would change without actually calling the pfSense API.')
                ->inline(false)
                ->default(fn () => config('pfsense-autoforward.dry_run')),
            TextInput::make('request_timeout')
                ->label('Request timeout (seconds)')
                ->numeric()
                ->minValue(1)
                ->maxValue(120)
                ->required()
                ->default(fn () => config('pfsense-autoforward.request_timeout')),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveSettings(array $data): void
    {
        $this->writeToEnvironment([
            'PFSENSEAF_URL' => $data['pfsense_url'],
            'PFSENSEAF_API_KEY' => $data['pfsense_api_key'],
            'PFSENSEAF_INTERFACE' => $data['pfsense_interface'],
            'PFSENSEAF_VERIFY_TLS' => $data['verify_tls'] ? 'true' : 'false',
            'PFSENSEAF_DEFAULT_PROTOCOL' => $data['default_protocol'],
            'PFSENSEAF_REQUIRED_EGG_TAG' => $data['required_egg_tag'],
            'PFSENSEAF_ALLOWED_NODE_IDS' => $data['allowed_node_ids'],
            'PFSENSEAF_RECONCILE_INTERVAL_MINUTES' => $data['reconcile_interval_minutes'],
            'PFSENSEAF_DRY_RUN' => $data['dry_run'] ? 'true' : 'false',
            'PFSENSEAF_REQUEST_TIMEOUT' => $data['request_timeout'],
        ]);

        Notification::make()
            ->title(trans('pfsense-autoforward::strings.notifications.settings_saved'))
            ->success()
            ->send();
    }
}
