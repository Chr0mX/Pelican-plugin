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
    private function rule(int $allocationId = 1): PortForwardRule
    {
        return new PortForwardRule(
            allocationId: $allocationId,
            serverUuid: 'aaaa',
            serverName: 'Survival',
            nodeName: 'Node 1',
            targetIp: '10.0.0.5',
            port: 25565,
            protocol: 'tcp/udp',
        );
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
        $this->assertSame(['Node 1 | Survival | 25565/tcp/udp'], $summary->mappedLines);
    }

    public function test_leaves_an_allocation_alone_when_both_rules_already_exist(): void
    {
        $tag = $this->rule()->descrTag();

        $client = $this->createMock(PfSenseApiClient::class);
        $client->method('listPortForwardRules')->willReturn([['id' => 1, 'descr' => $tag]]);
        $client->method('listPassRules')->willReturn([['id' => 2, 'descr' => $tag]]);
        $client->expects($this->never())->method('createPortForward');
        $client->expects($this->never())->method('createPassRule');
        $client->expects($this->never())->method('apply');

        $reconciler = new PortForwardReconciler(
            $this->allocationsReturning(collect([$this->rule()])),
            $client,
        );

        $summary = $reconciler->reconcile();

        $this->assertSame(0, $summary->created);
        $this->assertSame(1, $summary->unchanged);
        $this->assertSame(['Node 1 | Survival | 25565/tcp/udp'], $summary->mappedLines);
    }

    public function test_removes_managed_rules_no_longer_expected(): void
    {
        $staleTag = 'pelican:aaaa:99';

        $client = $this->createMock(PfSenseApiClient::class);
        $client->method('listPortForwardRules')->willReturn([['id' => 10, 'descr' => $staleTag]]);
        $client->method('listPassRules')->willReturn([['id' => 11, 'descr' => $staleTag]]);
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
        $client->method('listPortForwardRules')->willReturn([['id' => 1, 'descr' => 'someone-elses-rule']]);
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
        $this->assertSame(['Node 1 | Survival | 25565/tcp/udp'], $summary->mappedLines);
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
        $this->assertSame([], $summary->mappedLines);
        $this->assertNotEmpty($summary->errors);
    }
}
