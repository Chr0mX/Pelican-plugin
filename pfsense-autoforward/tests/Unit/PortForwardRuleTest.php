<?php

namespace Chr0mX\PfSenseAutoForward\Tests\Unit;

use Chr0mX\PfSenseAutoForward\DTO\PortForwardRule;
use Chr0mX\PfSenseAutoForward\Tests\TestCase;

class PortForwardRuleTest extends TestCase
{
    public function test_descr_tag_identifies_the_allocation_not_the_port_or_ip(): void
    {
        $rule = new PortForwardRule(
            allocationId: 42,
            serverUuid: '11111111-1111-1111-1111-111111111111',
            serverName: 'Test Server',
            nodeName: 'Node 1',
            targetIp: '10.0.0.5',
            port: 25565,
            protocol: 'tcp/udp',
        );

        $this->assertSame('pelican:11111111-1111-1111-1111-111111111111:42', $rule->descrTag());

        $movedPort = new PortForwardRule(
            allocationId: 42,
            serverUuid: '11111111-1111-1111-1111-111111111111',
            serverName: 'Test Server',
            nodeName: 'Node 1',
            targetIp: '10.0.0.9',
            port: 30000,
            protocol: 'tcp/udp',
        );

        $this->assertSame($rule->descrTag(), $movedPort->descrTag());
    }
}
