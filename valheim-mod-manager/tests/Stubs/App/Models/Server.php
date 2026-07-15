<?php

namespace App\Models;

/**
 * Minimal stand-in for the real Pelican Server model. Never shipped with
 * the plugin - used for unit tests only so services that type-hint
 * App\Models\Server can be exercised without a full panel installation.
 */
class Server
{
    public function __construct(
        public int $id = 1,
        public ?string $uuid = '11111111-1111-1111-1111-111111111111',
        public ?Egg $egg = null,
    ) {
        $this->egg ??= new Egg();
    }

    public function loadMissing(string $relation): static
    {
        return $this;
    }
}
