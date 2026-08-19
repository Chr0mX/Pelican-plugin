<?php

namespace App\Models;

/**
 * Minimal stand-in for the real Pelican Allocation model (id, node_id, ip,
 * port, server_id, ip_alias, is_locked, server()/node() relations - see
 * app/Models/Allocation.php in pelican-dev/panel). Only what
 * AllocationRepository actually reads is reproduced here. Never shipped
 * with the plugin - used for unit tests only.
 */
class Allocation
{
    public function __construct(
        public int $id = 1,
        public int $node_id = 1,
        public string $ip = '10.0.0.5',
        public int $port = 25565,
        public ?int $server_id = null,
        public ?string $ip_alias = null,
        public ?Server $server = null,
        public ?Node $node = null,
    ) {}
}
