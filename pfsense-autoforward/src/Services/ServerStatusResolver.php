<?php

namespace Chr0mX\PfSenseAutoForward\Services;

use App\Models\Server;
use Throwable;

/**
 * Thin wrapper around Server::retrieveStatus() (a live Wings daemon call,
 * cached by the panel itself for 15s) so AllocationRepository doesn't need
 * to know about App\Enums\ContainerStatus directly - keeps this swappable/
 * mockable in tests, and gives one place to change what "active" means
 * later (e.g. Running only, not Starting) without touching the repository.
 */
class ServerStatusResolver
{
    /**
     * Fails open (reports active) if the daemon can't be reached. A
     * transient Wings outage on one node shouldn't disable every port
     * behind it - the alternative (fail closed) risks silently cutting off
     * a server that was actually running fine, which is worse than a
     * port briefly staying open through an outage.
     */
    public function isActive(Server $server): bool
    {
        try {
            return $server->retrieveStatus()->isStartingOrRunning();
        } catch (Throwable) {
            return true;
        }
    }
}
