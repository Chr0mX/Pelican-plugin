<?php

namespace Chr0mX\PfSenseAutoForward\DTO;

/**
 * One row of the admin status page's "Currently mapped" table - a plain,
 * cache-serializable projection of a PortForwardRule plus what actually
 * happened to it this reconciliation. allocationId is carried through so
 * the table's inline enable/disable toggle and protocol select know which
 * allocation to write a PortForwardOverride for.
 */
final readonly class MappedPortRow
{
    public function __construct(
        public int $allocationId,
        public string $nodeName,
        public string $serverName,
        public int $port,
        public string $protocol,
        public bool $serverActive,
        public bool $ruleEnabled,
    ) {}

    public static function fromRule(PortForwardRule $rule, bool $ruleEnabled): self
    {
        return new self(
            allocationId: $rule->allocationId,
            nodeName: $rule->nodeName,
            serverName: $rule->serverName,
            port: $rule->port,
            protocol: $rule->protocol,
            serverActive: $rule->serverActive,
            ruleEnabled: $ruleEnabled,
        );
    }

    /**
     * @return array{allocation_id: int, node: string, server: string, port: int, protocol: string, server_active: bool, rule_enabled: bool}
     */
    public function toArray(): array
    {
        return [
            'allocation_id' => $this->allocationId,
            'node' => $this->nodeName,
            'server' => $this->serverName,
            'port' => $this->port,
            'protocol' => $this->protocol,
            'server_active' => $this->serverActive,
            'rule_enabled' => $this->ruleEnabled,
        ];
    }
}
