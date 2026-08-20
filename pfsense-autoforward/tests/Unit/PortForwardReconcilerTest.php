<?php

namespace Chr0mX\PfSenseAutoForward\Tests\Unit;

use Chr0mX\PfSenseAutoForward\DTO\PortForwardRule;
use Chr0mX\PfSenseAutoForward\Exceptions\PfSenseApiException;
use Chr0mX\PfSenseAutoForward\Services\AllocationRepository;
use Chr0mX\PfSenseAutoForward\Services\PfSenseApiClient;
use Chr0mX\PfSenseAutoForward\Services\PortForwardReconciler;
use Chr0mX\PfSenseAutoForward\Tests\TestCase;
use Illuminate\Support\Collection;

class PortForwardReconcilerTest extends TestCase
{
    private function rule(int $allocationId = 1, string $protocol = 'tcp/udp', bool $enabled = true): PortForwardRule
    {
        return new PortForwardRule(
            allocationId: $allocationId,
            serverUuid: 'aaaa',
            serverName: 'Survival',
            nodeName: 'Node 1',
            targetIp: '10.0.0.5',
            port: 25565,
            protocol: $protocol,
            serverActive: $enabled,
            enabled: $enabled,
        );
    }

    /**
     * @return array{id: int, descr: string, disabled: bool, protocol: string}
     */
    private function existingEntry(int $id, string $tag, bool $disabled = false, string $protocol = 'tcp/udp'): array
    {
        return ['id' => $id, 'descr' => $tag, 'disabled' => $disabled, 'protocol' => $protocol];
    }

    private function allocationsReturning(Collection $rules): AllocationRepository
    {
        $repository = $this->createMock(AllocationRepository::class);
        $repository->method('expectedRules')->willReturn($rules);

        return $repository;
    }

    public function test_creates_both_rules_for_a_new_allocation(): void
    {
        $client = $this->createMock(PfSenseApiClient::class);
        $client->method('listPortForwardRules')->willReturn([]);
        $client->method('listPassRules')->willReturn([]);
        $client->expects($this->once())->method('createPortForward');
        $client->expects($this->once())->method('createPassRule');
        $client->expects($this->once())->method('apply');

        $reconciler = new PortForwardReconciler(
            $this->allocationsReturning(collect([$this->rule()])),
            $client,
        );

        $summary = $reconciler->reconcile();

        $this->assertSame(2, $summary->created);
        $this->assertSame(0, $summary->removed);
        $this->assertSame(0, $summary->unchanged);
        $this->assertSame(0, $summary->failed);
        $this->assertSame([[
            'allocation_id' => 1,
            'node' => 'Node 1',
            'server' => 'Survival',
            'port' => 25565,
            'protocol' => 'tcp/udp',
            'server_active' => true,
            'rule_enabled' => true,
        ]], $summary->mappedRows);
    }

    public function test_leaves_an_allocation_alone_when_both_rules_already_exist_and_match(): void
    {
        $tag = $this->rule()->descrTag();

        $client = $this->createMock(PfSenseApiClient::class);
        $client->method('listPortForwardRules')->willReturn([$this->existingEntry(1, $tag)]);
        $client->method('listPassRules')->willReturn([$this->existingEntry(2, $tag)]);
        $client->expects($this->never())->method('createPortForward');
        $client->expects($this->never())->method('createPassRule');
        $client->expects($this->never())->method('updatePortForwardRule');
        $client->expects($this->never())->method('updatePassRule');
        $client->expects($this->never())->method('apply');

        $reconciler = new PortForwardReconciler(
            $this->allocationsReturning(collect([$this->rule()])),
            $client,
        );

        $summary = $reconciler->reconcile();

        $this->assertSame(0, $summary->created);
        $this->assertSame(0, $summary->toggled);
        $this->assertSame(1, $summary->unchanged);
        $this->assertCount(1, $summary->mappedRows);
    }

    public function test_disables_an_existing_rule_when_its_server_stops(): void
    {
        $tag = $this->rule()->descrTag();

        $client = $this->createMock(PfSenseApiClient::class);
        // Both currently enabled in pfSense, but the rule now says the
        // server is stopped (enabled: false) - both should be disabled.
        $client->method('listPortForwardRules')->willReturn([$this->existingEntry(1, $tag, disabled: false)]);
        $client->method('listPassRules')->willReturn([$this->existingEntry(2, $tag, disabled: false)]);
        $client->expects($this->once())->method('updatePortForwardRule')->with(1, true, 'tcp/udp');
        $client->expects($this->once())->method('updatePassRule')->with(2, true, 'tcp/udp');
        $client->expects($this->once())->method('apply');

        $reconciler = new PortForwardReconciler(
            $this->allocationsReturning(collect([$this->rule(enabled: false)])),
            $client,
        );

        $summary = $reconciler->reconcile();

        $this->assertSame(2, $summary->toggled);
        $this->assertSame(0, $summary->unchanged);
        $this->assertSame(0, $summary->failed);
        $this->assertFalse($summary->mappedRows[0]['rule_enabled']);
    }

    public function test_re_enables_an_existing_rule_when_its_server_starts(): void
    {
        $tag = $this->rule()->descrTag();

        $client = $this->createMock(PfSenseApiClient::class);
        $client->method('listPortForwardRules')->willReturn([$this->existingEntry(1, $tag, disabled: true)]);
        $client->method('listPassRules')->willReturn([$this->existingEntry(2, $tag, disabled: true)]);
        $client->expects($this->once())->method('updatePortForwardRule')->with(1, false, 'tcp/udp');
        $client->expects($this->once())->method('updatePassRule')->with(2, false, 'tcp/udp');

        $reconciler = new PortForwardReconciler(
            $this->allocationsReturning(collect([$this->rule(enabled: true)])),
            $client,
        );

        $summary = $reconciler->reconcile();

        $this->assertSame(2, $summary->toggled);
        $this->assertTrue($summary->mappedRows[0]['rule_enabled']);
    }

    public function test_syncs_a_protocol_override_onto_an_existing_rule(): void
    {
        $tag = $this->rule()->descrTag();

        $client = $this->createMock(PfSenseApiClient::class);
        $client->method('listPortForwardRules')->willReturn([$this->existingEntry(1, $tag, protocol: 'tcp/udp')]);
        $client->method('listPassRules')->willReturn([$this->existingEntry(2, $tag, protocol: 'tcp/udp')]);
        $client->expects($this->once())->method('updatePortForwardRule')->with(1, false, 'udp');
        $client->expects($this->once())->method('updatePassRule')->with(2, false, 'udp');

        $reconciler = new PortForwardReconciler(
            $this->allocationsReturning(collect([$this->rule(protocol: 'udp')])),
            $client,
        );

        $summary = $reconciler->reconcile();

        $this->assertSame(2, $summary->toggled);
        $this->assertSame('udp', $summary->mappedRows[0]['protocol']);
    }

    public function test_removes_managed_rules_no_longer_expected(): void
    {
        $staleTag = 'pelican:aaaa:99';

        $client = $this->createMock(PfSenseApiClient::class);
        $client->method('listPortForwardRules')->willReturn([$this->existingEntry(10, $staleTag)]);
        $client->method('listPassRules')->willReturn([$this->existingEntry(11, $staleTag)]);
        $client->expects($this->once())->method('deletePortForwardRule')->with($staleTag);
        $client->expects($this->once())->method('deletePassRule')->with($staleTag);
        $client->expects($this->once())->method('apply');

        $reconciler = new PortForwardReconciler(
            $this->allocationsReturning(collect()),
            $client,
        );

        $summary = $reconciler->reconcile();

        $this->assertSame(2, $summary->removed);
        $this->assertSame(0, $summary->failed);
        $this->assertSame(
            ['Server aaaa | allocation #99 - no longer assigned, rule removed'],
            $summary->removedLines,
        );
    }

    public function test_never_touches_rules_outside_the_managed_prefix(): void
    {
        $client = $this->createMock(PfSenseApiClient::class);
        $client->method('listPortForwardRules')->willReturn([$this->existingEntry(1, 'someone-elses-rule')]);
        $client->method('listPassRules')->willReturn([]);
        $client->expects($this->never())->method('deletePortForwardRule');
        $client->expects($this->never())->method('deletePassRule');

        $reconciler = new PortForwardReconciler(
            $this->allocationsReturning(collect()),
            $client,
        );

        $summary = $reconciler->reconcile();

        $this->assertSame(0, $summary->removed);
    }

    public function test_dry_run_reports_intended_changes_without_calling_the_api(): void
    {
        $client = $this->createMock(PfSenseApiClient::class);
        $client->method('listPortForwardRules')->willReturn([]);
        $client->method('listPassRules')->willReturn([]);
        $client->expects($this->never())->method('createPortForward');
        $client->expects($this->never())->method('createPassRule');
        $client->expects($this->never())->method('apply');

        $reconciler = new PortForwardReconciler(
            $this->allocationsReturning(collect([$this->rule()])),
            $client,
            dryRun: true,
        );

        $summary = $reconciler->reconcile();

        $this->assertTrue($summary->dryRun);
        $this->assertSame(2, $summary->created);
        $this->assertCount(1, $summary->mappedRows);
    }

    public function test_dry_run_reports_a_toggle_without_calling_the_api(): void
    {
        $tag = $this->rule()->descrTag();

        $client = $this->createMock(PfSenseApiClient::class);
        $client->method('listPortForwardRules')->willReturn([$this->existingEntry(1, $tag, disabled: false)]);
        $client->method('listPassRules')->willReturn([$this->existingEntry(2, $tag, disabled: false)]);
        $client->expects($this->never())->method('updatePortForwardRule');
        $client->expects($this->never())->method('updatePassRule');
        $client->expects($this->never())->method('apply');

        $reconciler = new PortForwardReconciler(
            $this->allocationsReturning(collect([$this->rule(enabled: false)])),
            $client,
            dryRun: true,
        );

        $summary = $reconciler->reconcile();

        $this->assertSame(2, $summary->toggled);
    }

    public function test_a_partially_failed_rule_is_not_reported_as_currently_mapped(): void
    {
        $client = $this->createMock(PfSenseApiClient::class);
        $client->method('listPortForwardRules')->willReturn([]);
        $client->method('listPassRules')->willReturn([]);
        $client->method('createPortForward')->willThrowException(
            new PfSenseApiException('boom', 500),
        );

        $reconciler = new PortForwardReconciler(
            $this->allocationsReturning(collect([$this->rule()])),
            $client,
        );

        $summary = $reconciler->reconcile();

        $this->assertSame(1, $summary->failed);
        $this->assertSame([], $summary->mappedRows);
        $this->assertNotEmpty($summary->errors);
    }
}
