<?php

namespace App\Models;

/**
 * Minimal stand-in for the real Pelican Node model, only exposing what
 * AllocationRepository needs (the node's display name, for the admin
 * status page's "<node> | <server> | <port>" lines). Never shipped with
 * the plugin - used for unit tests only.
 */
class Node
{
    public function __construct(
        public int $id = 1,
        public string $name = 'Node 1',
    ) {}
}
