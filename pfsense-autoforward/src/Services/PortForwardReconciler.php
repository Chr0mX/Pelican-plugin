<?php

namespace Chr0mX\PfSenseAutoForward\Services;

use Chr0mX\PfSenseAutoForward\DTO\PortForwardRule;
use Chr0mX\PfSenseAutoForward\DTO\ReconciliationSummary;
use Chr0mX\PfSenseAutoForward\Exceptions\PfSenseApiException;
use Illuminate\Support\Collection;

/**
 * Diffs the allocations Pelican currently expects to be forwarded against
 * the rules pfSense actually has tagged as ours, then creates what's
 * missing and removes what's stale. Run on a schedule and on demand via
 * "Sync Now" - there's no event to hook, and reconciling from scratch every
 * time is also exactly what makes pre-existing, manually-created
 * allocations get picked up automatically the first time this runs.
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
        $unchanged = 0;
        $failed = 0;
        $errors = [];
        $applyNeeded = false;

        foreach ($expected as $tag => $rule) {
            $needsNat = ! isset($existingNat[$tag]);
            $needsPass = ! isset($existingPass[$tag]);

            if (! $needsNat && ! $needsPass) {
                $unchanged++;

                continue;
            }

            if ($this->dryRun) {
                $created += ($needsNat ? 1 : 0) + ($needsPass ? 1 : 0);

                continue;
            }

            [$madeCreated, $madeFailed, $ruleErrors, $didApply] = $this->createMissing($rule, $needsNat, $needsPass);
            $created += $madeCreated;
            $failed += $madeFailed;
            $errors = [...$errors, ...$ruleErrors];
            $applyNeeded = $applyNeeded || $didApply;
        }

        [$removed, $removeFailed, $removeErrors, $didApply] = $this->removeOrphans($existingNat, $existingPass, $expected);
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
            unchanged: $unchanged,
            failed: $failed,
            dryRun: $this->dryRun,
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
     * @param  array<string, true>  $existingNat
     * @param  array<string, true>  $existingPass
     * @param  Collection<string, PortForwardRule>  $expected
     * @return array{0: int, 1: int, 2: string[], 3: bool}
     */
    private function removeOrphans(array $existingNat, array $existingPass, Collection $expected): array
    {
        $removed = 0;
        $failed = 0;
        $errors = [];
        $applied = false;

        foreach (array_keys($existingNat) as $tag) {
            if (! $this->isOrphaned($tag, $expected)) {
                continue;
            }

            if ($this->dryRun) {
                $removed++;

                continue;
            }

            try {
                $this->client->deletePortForwardRule($tag);
                $removed++;
                $applied = true;
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

                continue;
            }

            try {
                $this->client->deletePassRule($tag);
                $removed++;
                $applied = true;
            } catch (PfSenseApiException $e) {
                $failed++;
                $errors[] = "Delete pass rule failed for {$tag}: {$e->getMessage()}";
            }
        }

        return [$removed, $failed, $errors, $applied];
    }

    /**
     * @param  Collection<string, PortForwardRule>  $expected
     */
    private function isOrphaned(string $tag, Collection $expected): bool
    {
        return str_starts_with($tag, self::MANAGED_PREFIX) && ! $expected->has($tag);
    }

    /**
     * Indexes by `descr` alone - deletion goes through PfSenseApiClient's
     * query-filtered plural-endpoint DELETE (by descr), not by pfSense's
     * own numeric object id, so the id doesn't need to be tracked here.
     *
     * @param  array<int, array<string, mixed>>  $rules
     * @return array<string, true>
     */
    private function indexByDescr(array $rules): array
    {
        $indexed = [];

        foreach ($rules as $entry) {
            $descr = $entry['descr'] ?? null;

            if (! is_string($descr) || $descr === '') {
                continue;
            }

            $indexed[$descr] = true;
        }

        return $indexed;
    }
}
