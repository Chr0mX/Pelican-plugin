<?php

namespace Chr0mX\PfSenseAutoForward\Services;

use Chr0mX\PfSenseAutoForward\Models\PortForwardOverride;

/**
 * Write side of the manual overrides the admin status page's "Currently
 * mapped" table lets an operator set per allocation - AllocationRepository
 * is the read side. Kept separate from the Eloquent model so the admin
 * page doesn't need to know the model's column names/casts directly.
 */
class PortForwardOverrideService
{
    public function setEnabledOverride(int $allocationId, ?bool $enabled): void
    {
        PortForwardOverride::query()->updateOrCreate(
            ['allocation_id' => $allocationId],
            ['enabled_override' => $enabled],
        );
    }

    public function setProtocol(int $allocationId, ?string $protocol): void
    {
        PortForwardOverride::query()->updateOrCreate(
            ['allocation_id' => $allocationId],
            ['protocol' => $protocol],
        );
    }

    /**
     * Clears both overrides for an allocation, returning it to fully
     * automatic behaviour.
     */
    public function clear(int $allocationId): void
    {
        PortForwardOverride::query()->where('allocation_id', $allocationId)->delete();
    }
}
