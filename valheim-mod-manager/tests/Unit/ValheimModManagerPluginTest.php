<?php

namespace Chr0mX\ValheimModManager\Tests\Unit;

use Chr0mX\ValheimModManager\Tests\TestCase;
use Chr0mX\ValheimModManager\ValheimModManagerPlugin;

class ValheimModManagerPluginTest extends TestCase
{
    /**
     * Guards against the exact class of bug that already happened once:
     * pelican-dev/panel's HasPluginSettings interface gained a new required
     * method (getSettingsFormData(), added in panel PR #2453 to pre-fill
     * the settings form via ->fillForm()) and this plugin didn't implement
     * it yet. Because the stub interface here mirrors the real one exactly,
     * simply declaring/autoloading ValheimModManagerPlugin already fails
     * loudly - a PHP fatal error, before any assertion runs - the moment
     * this plugin drifts out of sync with what the real interface requires
     * again in the future.
     */
    public function test_implements_the_full_has_plugin_settings_contract(): void
    {
        $plugin = new ValheimModManagerPlugin();

        $this->assertInstanceOf(\App\Contracts\Plugins\HasPluginSettings::class, $plugin);
        $this->assertIsArray($plugin->getSettingsForm());
    }

    public function test_settings_form_data_matches_the_settings_form_fields(): void
    {
        config()->set('valheim-mod-manager.thunderstore_api_url', 'https://thunderstore.io');
        config()->set('valheim-mod-manager.thunderstore_community', 'valheim');
        config()->set('valheim-mod-manager.default_game', 'valheim');
        config()->set('valheim-mod-manager.default_install_directory', 'BepInEx/plugins');
        config()->set('valheim-mod-manager.auto_update_check', true);
        config()->set('valheim-mod-manager.auto_refresh_after_install', false);
        config()->set('valheim-mod-manager.download_timeout', 60);
        config()->set('valheim-mod-manager.temporary_directory', 'BepInEx/.valheim-mod-manager-tmp');
        config()->set('valheim-mod-manager.required_tag', 'bepinex-mods');

        $plugin = new ValheimModManagerPlugin();
        $data = $plugin->getSettingsFormData();

        $this->assertSame([
            'thunderstore_api_url' => 'https://thunderstore.io',
            'thunderstore_community' => 'valheim',
            'default_game' => 'valheim',
            'default_install_directory' => 'BepInEx/plugins',
            'auto_update_check' => true,
            'auto_refresh_after_install' => false,
            'download_timeout' => 60,
            'temporary_directory' => 'BepInEx/.valheim-mod-manager-tmp',
            'required_tag' => 'bepinex-mods',
        ], $data);
    }
}
