<?php

namespace Chr0mX\PfSenseAutoForward\Tests\Unit;

use Chr0mX\PfSenseAutoForward\Models\PortForwardOverride;
use Chr0mX\PfSenseAutoForward\Services\PortForwardOverrideService;
use Chr0mX\PfSenseAutoForward\Tests\TestCase;

class PortForwardOverrideServiceTest extends TestCase
{
    public function test_set_enabled_override_creates_then_updates_a_row(): void
    {
        $service = new PortForwardOverrideService();

        $service->setEnabledOverride(1, false);
        $this->assertSame(false, PortForwardOverride::query()->where('allocation_id', 1)->value('enabled_override'));

        $service->setEnabledOverride(1, true);
        $this->assertSame(true, PortForwardOverride::query()->where('allocation_id', 1)->value('enabled_override'));
        $this->assertSame(1, PortForwardOverride::query()->count());
    }

    public function test_set_protocol_creates_then_updates_a_row(): void
    {
        $service = new PortForwardOverrideService();

        $service->setProtocol(2, 'udp');
        $this->assertSame('udp', PortForwardOverride::query()->where('allocation_id', 2)->value('protocol'));

        $service->setProtocol(2, 'tcp');
        $this->assertSame('tcp', PortForwardOverride::query()->where('allocation_id', 2)->value('protocol'));
    }

    public function test_setting_both_fields_shares_one_row(): void
    {
        $service = new PortForwardOverrideService();

        $service->setEnabledOverride(3, false);
        $service->setProtocol(3, 'udp');

        $this->assertSame(1, PortForwardOverride::query()->count());
        $row = PortForwardOverride::query()->where('allocation_id', 3)->first();
        $this->assertFalse($row->enabled_override);
        $this->assertSame('udp', $row->protocol);
    }

    public function test_clear_removes_the_row(): void
    {
        $service = new PortForwardOverrideService();
        $service->setEnabledOverride(4, false);

        $service->clear(4);

        $this->assertSame(0, PortForwardOverride::query()->where('allocation_id', 4)->count());
    }
}
