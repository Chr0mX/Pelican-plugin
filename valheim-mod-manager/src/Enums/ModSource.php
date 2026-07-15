<?php

namespace Chr0mX\ValheimModManager\Enums;

use Filament\Support\Contracts\HasLabel;

enum ModSource: string implements HasLabel
{
    /** Installed via this plugin from a Thunderstore package (manifest.json present). */
    case ThunderstorePackage = 'thunderstore_package';

    /** A folder found in the plugins/patchers directory without a manifest.json. */
    case Folder = 'folder';

    /** A standalone .dll file directly inside the plugins/patchers directory. */
    case Dll = 'dll';

    public function getLabel(): string
    {
        return match ($this) {
            self::ThunderstorePackage => 'Thunderstore Package',
            self::Folder => 'Folder',
            self::Dll => 'DLL',
        };
    }
}
