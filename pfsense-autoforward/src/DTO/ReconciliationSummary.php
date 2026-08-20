<?php

namespace Chr0mX\PfSenseAutoForward\DTO;

/**
 * Result of one reconciliation pass, cache-backed by the admin page so the
 * "Sync Now" button and the periodic scheduled run both feed the same
 * status display. Counts are per pfSense rule (NAT + pass are separate
 * entries), not per allocation, since that's what actually happened on the
 * pfSense side.
 *
 * mappedRows carries the "Currently mapped" table's data - one row per
 * allocation currently forwarded (created this run, already unchanged, or
 * synced), each a MappedPortRow::toArray(). removedLines/errors stay
 * simple human-readable strings since they don't need table treatment.
 */
final readonly class ReconciliationSummary
{
    /**
     * @param  array<int, array<string, mixed>>  $mappedRows
     * @param  string[]  $removedLines
     * @param  string[]  $errors
     */
    public function __construct(
        public int $created = 0,
        public int $removed = 0,
        public int $toggled = 0,
        public int $unchanged = 0,
        public int $failed = 0,
        public bool $dryRun = false,
        public array $mappedRows = [],
        public array $removedLines = [],
        public array $errors = [],
    ) {}

    public function hasFailures(): bool
    {
        return $this->failed > 0;
    }

    /**
     * @return array{created: int, removed: int, toggled: int, unchanged: int, failed: int, dry_run: bool, mapped_rows: array<int, array<string, mixed>>, removed_lines: string[], errors: string[]}
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'removed' => $this->removed,
            'toggled' => $this->toggled,
            'unchanged' => $this->unchanged,
            'failed' => $this->failed,
            'dry_run' => $this->dryRun,
            'mapped_rows' => $this->mappedRows,
            'removed_lines' => $this->removedLines,
            'errors' => $this->errors,
        ];
    }

    /**
     * @param  array{created?: int, removed?: int, toggled?: int, unchanged?: int, failed?: int, dry_run?: bool, mapped_rows?: array<int, array<string, mixed>>, removed_lines?: string[], errors?: string[]}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            created: $data['created'] ?? 0,
            removed: $data['removed'] ?? 0,
            toggled: $data['toggled'] ?? 0,
            unchanged: $data['unchanged'] ?? 0,
            failed: $data['failed'] ?? 0,
            dryRun: $data['dry_run'] ?? false,
            mappedRows: $data['mapped_rows'] ?? [],
            removedLines: $data['removed_lines'] ?? [],
            errors: $data['errors'] ?? [],
        );
    }
}
