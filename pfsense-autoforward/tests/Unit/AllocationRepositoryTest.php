<?php

namespace Chr0mX\PfSenseAutoForward\Tests\Unit;

use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Server;
use Chr0mX\PfSenseAutoForward\Services\AllocationRepository;
use Chr0mX\PfSenseAutoForward\Tests\TestCase;

class AllocationRepositoryTest extends TestCase
{
    public function test_maps_an_assigned_allocation_to_a_rule_using_its_real_bind_ip(): void
    {
        $allocation = new Allocation(
            id: 7,
            node_id: 2,
            ip: '10.0.0.5',
            port: 25565,
            server_id: 3,
            server: new Server(id: 3, uuid: 'aaaa', name: 'Survival'),
            node: new Node(id: 2, name: 'Frankfurt'),
        );

        $rule = (new AllocationRepository())->mapAllocation($allocation);

        $this->assertNotNull($rule);
        $this->assertSame(7, $rule->allocationId);
        $this->assertSame('aaaa', $rule->serverUuid);
        $this->assertSame('Survival', $rule->serverName);
        $this->assertSame('Frankfurt', $rule->nodeName);
        $this->assertSame('10.0.0.5', $rule->targetIp);
        $this->assertSame(25565, $rule->port);
        $this->assertSame('tcp/udp', $rule->protocol);
        $this->assertSame('Frankfurt | Survival | 25565/tcp/udp', $rule->describe());
    }

    public function test_falls_back_to_a_node_id_label_when_the_node_relation_is_missing(): void
    {
        $allocation = new Allocation(id: 9, node_id: 4, server_id: 1, server: new Server(id: 1), node: null);

        $rule = (new AllocationRepository())->mapAllocation($allocation);

        $this->assertSame('Node #4', $rule->nodeName);
    }

    public function test_unassigned_allocations_are_out_of_scope(): void
    {
        $allocation = new Allocation(id: 8, server_id: null, server: null);

        $rule = (new AllocationRepository())->mapAllocation($allocation);

        $this->assertNull($rule);
    }

    public function test_required_egg_tag_scopes_out_untagged_servers(): void
    {
        config()->set('pfsense-autoforward.required_egg_tag', 'internet-facing');

        $tagged = new Allocation(
            id: 1,
            server_id: 1,
            server: new Server(id: 1, egg: new Egg(tags: ['internet-facing'])),
        );
        $untagged = new Allocation(
            id: 2,
            server_id: 2,
            server: new Server(id: 2, egg: new Egg(tags: ['vanilla'])),
        );

        $repository = new AllocationRepository();

        $this->assertNotNull($repository->mapAllocation($tagged));
        $this->assertNull($repository->mapAllocation($untagged));
    }

    public function test_allowed_node_ids_scopes_out_other_nodes(): void
    {
        config()->set('pfsense-autoforward.allowed_node_ids', '1, 3');

        $onAllowedNode = new Allocation(id: 1, node_id: 3, server_id: 1, server: new Server(id: 1));
        $onOtherNode = new Allocation(id: 2, node_id: 2, server_id: 2, server: new Server(id: 2));

        $repository = new AllocationRepository();

        $this->assertNotNull($repository->mapAllocation($onAllowedNode));
        $this->assertNull($repository->mapAllocation($onOtherNode));
    }

    public function test_blank_allowed_node_ids_allows_every_node(): void
    {
        config()->set('pfsense-autoforward.allowed_node_ids', null);

        $allocation = new Allocation(id: 1, node_id: 99, server_id: 1, server: new Server(id: 1));

        $this->assertNotNull((new AllocationRepository())->mapAllocation($allocation));
    }

    public function test_required_egg_tag_and_allowed_node_ids_combine_with_and(): void
    {
        config()->set('pfsense-autoforward.required_egg_tag', 'internet-facing');
        config()->set('pfsense-autoforward.allowed_node_ids', '1');

        $matchesBoth = new Allocation(
            id: 1,
            node_id: 1,
            server_id: 1,
            server: new Server(id: 1, egg: new Egg(tags: ['internet-facing'])),
        );
        $wrongNodeOnly = new Allocation(
            id: 2,
            node_id: 2,
            server_id: 2,
            server: new Server(id: 2, egg: new Egg(tags: ['internet-facing'])),
        );
        $wrongTagOnly = new Allocation(
            id: 3,
            node_id: 1,
            server_id: 3,
            server: new Server(id: 3, egg: new Egg(tags: ['vanilla'])),
        );

        $repository = new AllocationRepository();

        $this->assertNotNull($repository->mapAllocation($matchesBoth));
        $this->assertNull($repository->mapAllocation($wrongNodeOnly));
        $this->assertNull($repository->mapAllocation($wrongTagOnly));
    }
}
