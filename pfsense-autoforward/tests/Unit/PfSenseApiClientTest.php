<?php

namespace Chr0mX\PfSenseAutoForward\Tests\Unit;

use Chr0mX\PfSenseAutoForward\DTO\PortForwardRule;
use Chr0mX\PfSenseAutoForward\Exceptions\PfSenseApiException;
use Chr0mX\PfSenseAutoForward\Services\PfSenseApiClient;
use Chr0mX\PfSenseAutoForward\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class PfSenseApiClientTest extends TestCase
{
    private function client(): PfSenseApiClient
    {
        return new PfSenseApiClient(
            baseUrl: 'https://pfsense.example.test',
            apiKey: 'secret-key',
            interface: 'wan',
        );
    }

    private function rule(): PortForwardRule
    {
        return new PortForwardRule(
            allocationId: 1,
            serverUuid: 'aaaa',
            serverName: 'Survival',
            targetIp: '10.0.0.5',
            port: 25565,
            protocol: 'tcp/udp',
        );
    }

    public function test_lists_port_forward_rules_from_the_plural_endpoint_and_sends_the_api_key_header(): void
    {
        Http::fake([
            'pfsense.example.test/api/v2/firewall/nat/port_forwards' => Http::response([
                'data' => [['id' => 1, 'descr' => 'pelican:aaaa:1']],
            ]),
        ]);

        $rules = $this->client()->listPortForwardRules();

        $this->assertSame([['id' => 1, 'descr' => 'pelican:aaaa:1']], $rules);
        Http::assertSent(fn ($request) => $request->hasHeader('X-API-Key', 'secret-key'));
    }

    public function test_lists_pass_rules_from_the_plural_endpoint(): void
    {
        Http::fake([
            'pfsense.example.test/api/v2/firewall/rules' => Http::response([
                'data' => [['id' => 2, 'descr' => 'pelican:aaaa:1']],
            ]),
        ]);

        $rules = $this->client()->listPassRules();

        $this->assertSame([['id' => 2, 'descr' => 'pelican:aaaa:1']], $rules);
    }

    public function test_creates_a_port_forward_against_the_singular_endpoint_with_correct_field_names(): void
    {
        Http::fake(['pfsense.example.test/*' => Http::response(['data' => []])]);

        $this->client()->createPortForward($this->rule());

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_ends_with(explode('?', $request->url())[0], '/api/v2/firewall/nat/port_forward')
                && $request['descr'] === 'pelican:aaaa:1'
                && $request['target'] === '10.0.0.5'
                && $request['destination_port'] === '25565'
                && $request['local_port'] === '25565'
                && $request['destination'] === 'wan:ip'
                && $request['source'] === 'any';
        });
    }

    public function test_creates_a_pass_rule_with_interface_as_an_array(): void
    {
        Http::fake(['pfsense.example.test/*' => Http::response(['data' => []])]);

        $this->client()->createPassRule($this->rule());

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_ends_with(explode('?', $request->url())[0], '/api/v2/firewall/rule')
                && $request['type'] === 'pass'
                && $request['interface'] === ['wan']
                && $request['destination'] === '10.0.0.5'
                && $request['destination_port'] === '25565'
                && $request['descr'] === 'pelican:aaaa:1';
        });
    }

    public function test_deletes_by_descr_query_filter_on_the_plural_endpoint_not_by_id(): void
    {
        Http::fake(['pfsense.example.test/*' => Http::response(['data' => []])]);

        $this->client()->deletePortForwardRule('pelican:aaaa:1');

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && str_starts_with($request->url(), 'https://pfsense.example.test/api/v2/firewall/nat/port_forwards')
                && str_contains($request->url(), 'descr=pelican');
        });
    }

    public function test_throws_on_a_failed_request(): void
    {
        Http::fake(['pfsense.example.test/*' => Http::response(['message' => 'nope'], 422)]);

        $this->expectException(PfSenseApiException::class);

        $this->client()->listPortForwardRules();
    }
}
