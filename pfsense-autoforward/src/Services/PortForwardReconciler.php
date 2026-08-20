<?php

namespace Chr0mX\PfSenseAutoForward\Services;

use Chr0mX\PfSenseAutoForward\DTO\MappedPortRow;
use Chr0mX\PfSenseAutoForward\DTO\PortForwardRule;
use Chr0mX\PfSenseAutoForward\DTO\ReconciliationSummary;
use Chr0mX\PfSenseAutoForward\Exceptions\PfSenseApiException;
use Illuminate\Support\Collection;

/**
 * Diffs the allocations Pelican currently expects to be forwarded against
 * the rules pfSense actually has tagged as ours, then creates what's
 * missing, syncs what's changed (enabled/disabled, protocol), and removes
 * what's stale. Run on a schedule and on demand via "Sync Now" - there's no
 * event to hook, and reconciling from scratch every time is also exactly
 * what makes pre-existing, manually-created allocations get picked up
 * automatically the first time this runs.
 */
class PortForwardReconciler
{
    private const MANAGED_PREFIX = 'pelican:';

    public function __construct(
        private readonly AllocationRepository $allocations,
        private readonly PfSenseApiClient $client,
        private readonly bool $dryRun = false,
    ) {}

    public function reconcile(): ReconciliationSummary
    {
        $expected = $this->allocations->expectedRules()->keyBy(
            fn (PortForwardRule $rule) => $rule->descrTag(),
        );

        try {
            $existingNat = $this->indexByDescr($this->client->listPortForwardRules());
            $existingPass = $this->indexByDescr($this->client->listPassRules());
        } catch (PfSenseApiException $e) {
            return new ReconciliationSummary(
                failed: 1,
                dryRun: $this->dryRun,
                errors: ["Could not read current pfSense rules: {$e->getMessage()}"],
            );
        }

        $created = 0;
        $toggled = 0;
        $unchanged = 0;
        $failed = 0;
        $errors = [];
        $mappedRows = [];
        $applyNeeded = false;

        foreach ($expected as $tag => $rule) {
            $natEntry = $existingNat[$tag] ?? null;
            $passEntry = $existingPass[$tag] ?? null;

            if ($natEntry !== null && $passEntry !== null) {
                // Both already exist - the id captured just above is still
                // valid here since nothing has been created or deleted yet
                // this pass (see PfSenseApiClient::updatePortForwardRule()).
                [$didToggle, $syncFailed, $syncErrors, $didApply] = $this->syncExisting($rule, $tag, $natEntry, $passEntry);
                $toggled += $didToggle;
                $failed += $syncFailed;
                $errors = [...$errors, ...$syncErrors];
                $applyNeeded = $applyNeeded || $didApply;

                if ($syncFailed === 0) {
                    $unchanged += $didToggle === 0 ? 1 : 0;
                    $mappedRows[] = MappedPortRow::fromRule($rule, $rule->enabled)->toArray();
                }

                continue;
            }

            $needsNat = $natEntry === null;
            $needsPass = $passEntry === null;

            if ($this->dryRun) {
                $created += ($needsNat ? 1 : 0) + ($needsPass ? 1 : 0);
                $mappedRows[] = MappedPortRow::fromRule($rule, $rule->enabled)->toArray();

                continue;
            }

            [$madeCreated, $madeFailed, $ruleErrors, $didApply] = $this->createMissing($rule, $needsNat, $needsPass);
            $created += $madeCreated;
            $failed += $madeFailed;
            $errors = [...$errors, ...$ruleErrors];
            $applyNeeded = $applyNeeded || $didApply;

            // Only counts as "currently mapped" if nothing about it failed -
            // a rule with a failed half (NAT created but pass rule create
            // errored, say) isn't fully forwarded yet, and the errors list
            // above already explains why.
            if ($madeFailed === 0) {
                $mappedRows[] = MappedPortRow::fromRule($rule, $rule->enabled)->toArray();
            }
        }

        [$removed, $removeFailed, $removeErrors, $didApply, $removedLines] = $this->removeOrphans($existingNat, $existingPass, $expected);
        $failed += $removeFailed;
        $errors = [...$errors, ...$removeErrors];
        $applyNeeded = $applyNeeded || $didApply;

        if ($applyNeeded && ! $this->dryRun) {
            try {
                $this->client->apply();
            } catch (PfSenseApiException $e) {
                $failed++;
                $errors[] = "Failed to apply staged pfSense changes: {$e->getMessage()}";
            }
        }

        return new ReconciliationSummary(
            created: $created,
            removed: $removed,
            toggled: $toggled,
            unchanged: $unchanged,
            failed: $failed,
            dryRun: $this->dryRun,
            mappedRows: $mappedRows,
            removedLines: $removedLines,
            errors: $errors,
        );
    }

    /**
     * @return array{0: int, 1: int, 2: string[], 3: bool}
     */
    private function createMissing(PortForwardRule $rule, bool $needsNat, bool $needsPass): array
    {
        $created = 0;
        $failed = 0;
        $errors = [];
        $applied = false;

        if ($needsNat) {
            try {
                $this->client->createPortForward($rule);
                $created++;
                $applied = true;
            } catch (PfSenseApiException $e) {
                $failed++;
                $errors[] = "Create NAT rule failed for {$rule->descrTag()}: {$e->getMessage()}";
            }
        }

        if ($needsPass) {
            try {
                $this->client->createPassRule($rule);
                $created++;
                $applied = true;
            } catch (PfSenseApiException $e) {
                $failed++;
                $errors[] = "Create pass rule failed for {$rule->descrTag()}: {$e->getMessage()}";
            }
        }

        return [$created, $failed, $errors, $applied];
    }

    /**
     * Brings an already-existing NAT+pass pair in line with the rule's
     * currently-desired enabled state and protocol (server started/
     * stopped since last run, or an admin changed a manual override).
     * Sends both fields together whenever either differs, rather than
     * tracking them separately - simpler, and the unchanged field is just
     * re-sent as its current value.
     *
     * @param  array{id: int|string, disabled: bool, protocol: string}  $natEntry
     * @param  array{id: int|string, disabled: bool, protocol: string}  $passEntry
     * @return array{0: int, 1: int, 2: string[], 3: bool}
     */
    private function syncExisting(PortForwardRule $rule, string $tag, array $natEntry, array $passEntry): array
    {
        $toggled = 0;
        $failed = 0;
        $errors = [];
        $applied = false;

        $desiredDisabled = ! $rule->enabled;

        $natMismatch = $natEntry['disabled'] !== $desiredDisabled || $natEntry['protocol'] !== $rule->protocol;
        $passMismatch = $passEntry['disabled'] !== $desiredDisabled || $passEntry['protocol'] !== $rule->protocol;

        if ($natMismatch) {
            if ($this->dryRun) {
                $toggled++;
            } else {
                try {
                    $this->client->updatePortForwardRule($natEntry['id'], $desiredDisabled, $rule->protocol);
                    $toggled++;
                    $applied = true;
                } catch (PfSenseApiException $e) {
                    $failed++;
                    $errors[] = "Update NAT rule failed for {$tag}: {$e->getMessage()}";
                }
            }
        }

        if ($passMismatch) {
            if ($this->dryRun) {
                $toggled++;
            } else {
                try {
                    $this->client->updatePassRule($passEntry['id'], $desiredDisabled, $rule->protocol);
                    $toggled++;
                    $applied = true;
                } catch (PfSenseApiException $e) {
                    $failed++;
                    $errors[] = "Update pass rule failed for {$tag}: {$e->getMessage()}";
                }
            }
        }

        return [$toggled, $failed, $errors, $applied];
    }

    /**
     * @param  array<string, array{id: int|string, disabled: bool, protocol: string}>  $existingNat
     * @param  array<string, array{id: int|string, disabled: bool, protocol: string}>  $existingPass
     * @param  Collection<string, PortForwardRule>  $expected
     * @return array{0: int, 1: int, 2: string[], 3: bool, 4: string[]}
     */
    private function removeOrphans(array $existingNat, array $existingPass, Collection $expected): array
    {
        $removed = 0;
        $failed = 0;
        $errors = [];
        $applied = false;
        $removedTags = [];

        foreach (array_keys($existingNat) as $tag) {
            if (! $this->isOrphaned($tag, $expected)) {
                continue;
            }

            if ($this->dryRun) {
                $removed++;
                $removedTags[$tag] = true;

                continue;
            }

            try {
                $this->client->deletePortForwardRule($tag);
                $removed++;
                $applied = true;
                $removedTags[$tag] = true;
            } catch (PfSenseApiException $e) {
                $failed++;
                $errors[] = "Delete NAT rule failed for {$tag}: {$e->getMessage()}";
            }
        }

        foreach (array_keys($existingPass) as $tag) {
            if (! $this->isOrphaned($tag, $expected)) {
                continue;
            }

            if ($this->dryRun) {
                $removed++;
                $removedTags[$tag] = true;

                continue;
            }

            try {
                $this->client->deletePassRule($tag);
                $removed++;
                $applied = true;
                $removedTags[$tag] = true;
            } catch (PfSenseApiException $e) {
                $failed++;
                $errors[] = "Delete pass rule failed for {$tag}: {$e->getMessage()}";
            }
        }

        $removedLines = array_map(
            fn (string $tag) => $this->describeOrphan($tag),
            array_keys($removedTags),
        );

        return [$removed, $failed, $errors, $applied, $removedLines];
    }

    /**
     * @param  Collection<string, PortForwardRule>  $expected
     */
    private function isOrphaned(string $tag, Collection $expected): bool
    {
        return str_starts_with($tag, self::MANAGED_PREFIX) && ! $expected->has($tag);
    }

    /**
     * A removed rule's Pelican allocation is often already gone by the time
     * it's noticed as orphaned, so there's no PortForwardRule (and no node/
     * port) to describe it with - only the tag pfSense still has. Parses it
     * back ("pelican:<server_uuid>:<allocation_id>") into something readable
     * instead of showing the raw tag.
     */
    private function describeOrphan(string $tag): string
    {
        $parts = explode(':', $tag, 3);

        if (count($parts) !== 3) {
            return $tag;
        }

        [, $serverUuid, $allocationId] = $parts;

        return "Server {$serverUuid} | allocation #{$allocationId} - no longer assigned, rule removed";
    }

    /**
     * Indexes by `descr`, keeping the fields needed to detect drift
     * (disabled/protocol) and to PATCH by id - deletion still goes through
     * PfSenseApiClient's query-filtered plural-endpoint DELETE (by descr),
     * but there's no equivalent for PATCH (the plural endpoints only
     * support GET/PUT/DELETE), so updates need the id from this same
     * initial listing.
     *
     * @param  array<int, array<string, mixed>>  $rules
     * @return array<string, array{id: int|string, disabled: bool, protocol: string}>
     */
    private function indexByDescr(array $rules): array
    {
        $indexed = [];

        foreach ($rules as $entry) {
            $descr = $entry['descr'] ?? null;

            if (! is_string($descr) || $descr === '' || ! isset($entry['id'])) {
                continue;
            }

            $indexed[$descr] = [
                'id' => $entry['id'],
                'disabled' => (bool) ($entry['disabled'] ?? false),
                'protocol' => (string) ($entry['protocol'] ?? ''),
            ];
        }

        return $indexed;
    }
}
