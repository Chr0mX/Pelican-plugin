<?php

namespace App\Models;

/**
 * Minimal stand-in for the real Pelican Egg model, only exposing what
 * AllocationRepository needs to inspect (tags). Never shipped with the
 * plugin - used for unit tests only.
 */
class Egg
{
    /**
     * @param  string[]  $tags
     */
    public function __construct(
        public array $tags = [],
    ) {}
}
