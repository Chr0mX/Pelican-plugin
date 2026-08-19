<?php

namespace Chr0mX\PfSenseAutoForward\Tests\Unit;

use App\Enums\ContainerStatus;
use App\Models\Server;
use Chr0mX\PfSenseAutoForward\Services\ServerStatusResolver;
use Chr0mX\PfSenseAutoForward\Tests\TestCase;

class ServerStatusResolverTest extends TestCase
{
    public function test_running_and_starting_servers_are_active(): void
    {
        $resolver = new ServerStatusResolver();

        $this->assertTrue($resolver->isActive(new Server(status: ContainerStatus::Running)));
        $this->assertTrue($resolver->isActive(new Server(status: ContainerStatus::Starting)));
    }

    public function test_offline_and_exited_servers_are_not_active(): void
    {
        $resolver = new ServerStatusResolver();

        $this->assertFalse($resolver->isActive(new Server(status: ContainerStatus::Offline)));
        $this->assertFalse($resolver->isActive(new Server(status: ContainerStatus::Exited)));
    }

    public function test_fails_open_when_the_daemon_call_throws(): void
    {
        $server = new class extends Server {
            public function retrieveStatus(): ContainerStatus
            {
                throw new \RuntimeException('Wings unreachable');
            }
        };

        $this->assertTrue((new ServerStatusResolver())->isActive($server));
    }
}
