<?php

namespace App\Models;

/**
 * Minimal stand-in for the real Pelican Egg model, only exposing what
 * GameProviderInterface implementations need to inspect (tags/features).
 * Never shipped with the plugin - used for unit tests only.
 */
class Egg
{
    /**
     * @param  string[]  $tags
     * @param  string[]  $features
     */
    public function __construct(
        public array $tags = [],
        public array $features = [],
    ) {}
}
