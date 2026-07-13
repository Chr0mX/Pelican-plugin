<?php

namespace App\Repositories\Daemon;

use Exception;

/**
 * Stands in for Illuminate\Http\Client\Response just enough for the
 * ->failed()/->throw() calls this plugin makes against daemon responses.
 */
class FakeDaemonResponse
{
    public function __construct(protected bool $failedFlag = false) {}

    public function failed(): bool
    {
        return $this->failedFlag;
    }

    public function throw(): static
    {
        if ($this->failedFlag) {
            throw new Exception('Fake daemon request failed.');
        }

        return $this;
    }
}
