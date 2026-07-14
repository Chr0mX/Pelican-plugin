<?php

namespace Chr0mX\ValheimModManager\Tests\Unit;

use Chr0mX\ValheimModManager\Support\SafeCache;
use Chr0mX\ValheimModManager\Tests\TestCase;

class SafeCacheTest extends TestCase
{
    /**
     * Regression test for a real production crash: the "file" cache driver
     * throws an ErrorException instead of degrading when its storage
     * directory doesn't exist or isn't writable, which previously blanked
     * the entire Mods page. SafeCache must swallow that and just run the
     * callback directly instead of letting it propagate.
     */
    public function test_remember_falls_back_to_the_callback_when_the_cache_store_is_broken(): void
    {
        $this->app['config']->set('cache.default', 'file');
        $this->app['config']->set('cache.stores.file.path', '/this/path/does/not/exist/and/cannot/be/created');

        $result = SafeCache::remember('valheim-mod-manager:test', 60, fn () => 'fresh-value');

        $this->assertSame('fresh-value', $result);
    }

    public function test_remember_still_caches_when_the_store_is_healthy(): void
    {
        $this->app['config']->set('cache.default', 'array');

        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return 'value';
        };

        $this->assertSame('value', SafeCache::remember('valheim-mod-manager:healthy', 60, $callback));
        $this->assertSame('value', SafeCache::remember('valheim-mod-manager:healthy', 60, $callback));
        $this->assertSame(1, $calls);
    }

    public function test_lock_falls_back_to_running_without_mutual_exclusion_when_broken(): void
    {
        $this->app['config']->set('cache.default', 'file');
        $this->app['config']->set('cache.stores.file.path', '/this/path/does/not/exist/and/cannot/be/created');

        $result = SafeCache::lock('valheim-mod-manager:test-lock', 10, 5, fn () => 'ran-anyway');

        $this->assertSame('ran-anyway', $result);
    }
}
