<?php

namespace Chr0mX\PfSenseAutoForward\Tests\Unit;

use Chr0mX\PfSenseAutoForward\PfSenseAutoForwardPlugin;
use Chr0mX\PfSenseAutoForward\Tests\TestCase;

class PfSenseAutoForwardPluginTest extends TestCase
{
    /**
     * Mirrors the exact lesson learned on the Valheim Mod Manager plugin:
     * pelican-dev/panel's HasPluginSettings interface requires
     * getSettingsFormData() (added in panel PR #2453). The stub interface
     * here mirrors the real one exactly, so simply declaring/autoloading
     * this class already fails loudly - a PHP fatal error, before any
     * assertion runs - the moment this plugin drifts out of sync with what
     * the real interface requires.
     */
    public function test_implements_the_full_has_plugin_settings_contract(): void
    {
        $plugin = new PfSenseAutoForwardPlugin();

        $this->assertInstanceOf(\App\Contracts\Plugins\HasPluginSettings::class, $plugin);
        $this->assertIsArray($plugin->getSettingsForm());
    }

    public function test_settings_form_data_matches_the_settings_form_fields(): void
    {
        config()->set('pfsense-autoforward.pfsense_url', 'https://pfsense.example.test');
        config()->set('pfsense-autoforward.pfsense_api_key', 'secret-key');
        config()->set('pfsense-autoforward.pfsense_interface', 'wan');
        config()->set('pfsense-autoforward.verify_tls', false);
        config()->set('pfsense-autoforward.default_protocol', 'tcp/udp');
        config()->set('pfsense-autoforward.required_egg_tag', 'internet-facing');
        config()->set('pfsense-autoforward.allowed_node_ids', '1,3');
        config()->set('pfsense-autoforward.reconcile_interval_minutes', 5);
        config()->set('pfsense-autoforward.dry_run', false);
        config()->set('pfsense-autoforward.request_timeout', 15);

        $plugin = new PfSenseAutoForwardPlugin();
        $data = $plugin->getSettingsFormData();

        $this->assertSame([
            'pfsense_url' => 'https://pfsense.example.test',
            'pfsense_api_key' => 'secret-key',
            'pfsense_interface' => 'wan',
            'verify_tls' => false,
            'default_protocol' => 'tcp/udp',
            'required_egg_tag' => 'internet-facing',
            'allowed_node_ids' => '1,3',
            'reconcile_interval_minutes' => 5,
            'dry_run' => false,
            'request_timeout' => 15,
        ], $data);
    }

    public function test_id_matches_the_plugin_manifest(): void
    {
        $plugin = new PfSenseAutoForwardPlugin();

        $this->assertSame('pfsense-autoforward', $plugin->getId());
    }
}
