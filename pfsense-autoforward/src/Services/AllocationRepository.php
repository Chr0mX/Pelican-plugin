<?php

namespace Chr0mX\PfSenseAutoForward\Services;

use App\Models\Allocation;
use Chr0mX\PfSenseAutoForward\DTO\PortForwardRule;
use Illuminate\Support\Collection;

/**
 * Reads the set of NAT/pass rules pfSense *should* have right now, straight
 * from Pelican's own Allocation table - which is also what naturally
 * "detects" ports that were assigned before this plugin was ever installed:
 * there's no separate discovery step, an assigned allocation the reconciler
 * hasn't seen before is just as much "expected" as one created five minutes
 * ago.
 */
class AllocationRepository
{
    /**
     * @return Collection<int, PortForwardRule>
     */
    public function expectedRules(): Collection
    {
        return Allocation::query()
            ->whereNotNull('server_id')
            ->with(['server.egg'])
            ->get()
            ->map(fn (Allocation $allocation) => $this->mapAllocation($allocation))
            ->filter()
            ->values();
    }

    /**
     * Split out from expectedRules() so the scoping/mapping logic can be
     * unit tested directly against stub models, without needing a database.
     */
    public function mapAllocation(Allocation $allocation): ?PortForwardRule
    {
        if (! $this->isInScope($allocation)) {
            return null;
        }

        return new PortForwardRule(
            allocationId: $allocation->id,
            serverUuid: $allocation->server->uuid,
            serverName: $allocation->server->name,
            // The real bind IP for this allocation, not the alias: the
            // alias is what's shown to players, but NAT needs to target
            // wherever Wings actually listens on this node.
            targetIp: $allocation->ip,
            port: $allocation->port,
            protocol: (string) config('pfsense-autoforward.default_protocol', 'tcp/udp'),
        );
    }

    private function isInScope(Allocation $allocation): bool
    {
        if ($allocation->server === null) {
            return false;
        }

        $requiredTag = config('pfsense-autoforward.required_egg_tag');

        if (blank($requiredTag)) {
            return true;
        }

        $tags = $allocation->server->egg->tags ?? [];

        return in_array($requiredTag, $tags, true);
    }
}
