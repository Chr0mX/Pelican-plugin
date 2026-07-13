<?php

namespace Chr0mX\ValheimModManager\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ModStatus: string implements HasColor, HasIcon, HasLabel
{
    case Installed = 'installed';
    case UpdateAvailable = 'update_available';
    case MissingFiles = 'missing_files';
    case Disabled = 'disabled';
    case Unknown = 'unknown';

    public function getLabel(): string
    {
        return match ($this) {
            self::Installed => trans('valheim-mod-manager::strings.status.installed'),
            self::UpdateAvailable => trans('valheim-mod-manager::strings.status.update_available'),
            self::MissingFiles => trans('valheim-mod-manager::strings.status.missing_files'),
            self::Disabled => trans('valheim-mod-manager::strings.status.disabled'),
            self::Unknown => trans('valheim-mod-manager::strings.status.unknown'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Installed => 'success',
            self::UpdateAvailable => 'warning',
            self::MissingFiles => 'danger',
            self::Disabled => 'gray',
            self::Unknown => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Installed => 'tabler-circle-check-filled',
            self::UpdateAvailable => 'tabler-alert-triangle-filled',
            self::MissingFiles => 'tabler-circle-x-filled',
            self::Disabled => 'tabler-circle-minus',
            self::Unknown => 'tabler-help-circle',
        };
    }
}
