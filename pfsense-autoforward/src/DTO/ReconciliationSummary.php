<?php

namespace Chr0mX\PfSenseAutoForward\DTO;

/**
 * Result of one reconciliation pass, cache-backed by the admin page so the
 * "Sync Now" button and the periodic scheduled run both feed the same
 * status display. Counts are per pfSense rule (NAT + pass are separate
 * entries), not per allocation, since that's what actually happened on the
 * pfSense side.
 */
final readonly class ReconciliationSummary
{
    /**
     * @param  string[]  $errors
     */
    public function __construct(
        public int $created = 0,
        public int $removed = 0,
        public int $unchanged = 0,
        public int $failed = 0,
        public bool $dryRun = false,
        public array $errors = [],
    ) {}

    public function hasFailures(): bool
    {
        return $this->failed > 0;
    }

    /**
     * @return array{created: int, removed: int, unchanged: int, failed: int, dry_run: bool, errors: string[]}
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'removed' => $this->removed,
            'unchanged' => $this->unchanged,
            'failed' => $this->failed,
            'dry_run' => $this->dryRun,
            'errors' => $this->errors,
        ];
    }

    /**
     * @param  array{created?: int, removed?: int, unchanged?: int, failed?: int, dry_run?: bool, errors?: string[]}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            created: $data['created'] ?? 0,
            removed: $data['removed'] ?? 0,
            unchanged: $data['unchanged'] ?? 0,
            failed: $data['failed'] ?? 0,
            dryRun: $data['dry_run'] ?? false,
            errors: $data['errors'] ?? [],
        );
    }
}
