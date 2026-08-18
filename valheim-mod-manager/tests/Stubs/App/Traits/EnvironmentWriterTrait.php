<?php

namespace App\Traits;

/**
 * No-op stand-in for the real Pelican Panel trait (which writes to .env and
 * clears config cache) - just enough so ValheimModManagerPlugin's
 * `use EnvironmentWriterTrait;` resolves in tests without touching the real
 * filesystem or Artisan. Never shipped with the plugin.
 */
trait EnvironmentWriterTrait
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function writeToEnvironment(array $values = []): void {}
}
