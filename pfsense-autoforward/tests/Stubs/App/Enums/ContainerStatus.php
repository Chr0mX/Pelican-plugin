<?php

namespace App\Enums;

/**
 * Minimal stand-in for the real Pelican ContainerStatus enum - only the
 * cases and method ServerStatusResolver actually reads. Never shipped
 * with the plugin - used for unit tests only.
 */
enum ContainerStatus: string
{
    case Starting = 'starting';
    case Running = 'running';
    case Offline = 'offline';
    case Exited = 'exited';
    case Missing = 'missing';

    public function isStartingOrRunning(): bool
    {
        return in_array($this, [self::Starting, self::Running], true);
    }
}
