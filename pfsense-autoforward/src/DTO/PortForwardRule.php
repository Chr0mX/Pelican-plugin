<?php

namespace Chr0mX\PfSenseAutoForward\DTO;

/**
 * One expected NAT + pass rule pair, derived from a single Pelican
 * Allocation that's currently assigned to a server. Everything needed to
 * both create the pfSense rules and to recognise them again later (via
 * $descrTag) lives on this DTO - nothing about pfSense's own internal,
 * reshuffling-prone rule IDs is ever depended on.
 */
final readonly class PortForwardRule
{
    public function __construct(
        public int $allocationId,
        public string $serverUuid,
        public string $serverName,
        public string $targetIp,
        public int $port,
        public string $protocol,
    ) {}

    /**
     * Stable, greppable tag used to find/delete this rule later instead of
     * pfSense's own dynamic numeric rule IDs (which can shift on ruleset
     * reload). Deliberately does not include the port or IP - if either
     * changes for the same allocation, the reconciler still recognises
     * this as the same logical rule and updates it in place rather than
     * leaving an orphan under the old values.
     */
    public function descrTag(): string
    {
        return "pelican:{$this->serverUuid}:{$this->allocationId}";
    }
}
