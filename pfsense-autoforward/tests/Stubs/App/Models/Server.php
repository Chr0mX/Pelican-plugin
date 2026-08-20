<?php

namespace App\Models;

use App\Enums\ContainerStatus;

/**
 * Minimal stand-in for the real Pelican Server model. Never shipped with
 * the plugin - used for unit tests only so services that type-hint
 * App\Models\Server can be exercised without a full panel installation.
 */
class Server
{
    public function __construct(
        public int $id = 1,
        public string $uuid = '11111111-1111-1111-1111-111111111111',
        public string $name = 'Test Server',
        public ?Egg $egg = null,
        public ContainerStatus $status = ContainerStatus::Running,
    ) {
        $this->egg ??= new Egg();
    }

    public function loadMissing(string $relation): static
    {
        return $this;
    }

    public function retrieveStatus(): ContainerStatus
    {
        return $this->status;
    }
}
