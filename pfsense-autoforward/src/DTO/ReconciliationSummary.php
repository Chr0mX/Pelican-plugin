<?php

namespace Chr0mX\PfSenseAutoForward\DTO;

/**
 * Result of one reconciliation pass, cache-backed by the admin page so the
 * "Sync Now" button and the periodic scheduled run both feed the same
 * status display. Counts are per pfSense rule (NAT + pass are separate
 * entries), not per allocation, since that's what actually happened on the
 * pfSense side.
 *
 * The *Lines fields carry the same information in human-readable form -
 * "<node> | <server> | <port>/<protocol>" per allocation (see
 * PortForwardRule::describe()) - so the admin page can show what's actually
 * mapped right now, not just a count. mappedLines covers everything
 * currently in sync (created this run + already unchanged), since from the
 * operator's point of view both are simply "currently forwarded".
 */
final readonly class ReconciliationSummary
{
    /**
     * @param  string[]  $mappedLines
     * @param  string[]  $removedLines
     * @param  string[]  $errors
     */
    public function __construct(
        public int $created = 0,
        public int $removed = 0,
        public int $unchanged = 0,
        public int $failed = 0,
        public bool $dryRun = false,
        public array $mappedLines = [],
        public array $removedLines = [],
        public array $errors = [],
    ) {}

    public function hasFailures(): bool
    {
        return $this->failed > 0;
    }

    /**
     * @return array{created: int, removed: int, unchanged: int, failed: int, dry_run: bool, mapped_lines: string[], removed_lines: string[], errors: string[]}
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'removed' => $this->removed,
            'unchanged' => $this->unchanged,
            'failed' => $this->failed,
            'dry_run' => $this->dryRun,
            'mapped_lines' => $this->mappedLines,
            'removed_lines' => $this->removedLines,
            'errors' => $this->errors,
        ];
    }

    /**
     * @param  array{created?: int, removed?: int, unchanged?: int, failed?: int, dry_run?: bool, mapped_lines?: string[], removed_lines?: string[], errors?: string[]}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            created: $data['created'] ?? 0,
            removed: $data['removed'] ?? 0,
            unchanged: $data['unchanged'] ?? 0,
            failed: $data['failed'] ?? 0,
            dryRun: $data['dry_run'] ?? false,
            mappedLines: $data['mapped_lines'] ?? [],
            removedLines: $data['removed_lines'] ?? [],
            errors: $data['errors'] ?? [],
        );
    }
}
