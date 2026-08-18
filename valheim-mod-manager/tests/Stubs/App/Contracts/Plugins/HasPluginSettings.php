<?php

namespace App\Contracts\Plugins;

use Filament\Schemas\Components\Component;

/**
 * Mirrors the real Pelican Panel interface exactly (including
 * getSettingsFormData(), added upstream in pelican-dev/panel PR #2453) so
 * tests fail loudly - at class-declaration time, before any assertion even
 * runs - the moment this plugin drifts out of sync with what the real
 * interface requires. Never shipped with the plugin.
 */
interface HasPluginSettings
{
    /**
     * @return array<string, mixed>
     */
    public function getSettingsFormData(): array;

    /**
     * @return Component[]
     */
    public function getSettingsForm(): array;

    /**
     * @param  array<mixed, mixed>  $data
     */
    public function saveSettings(array $data): void;
}
