<?php

namespace Chr0mX\PfSenseAutoForward\Tests\Unit;

use Chr0mX\PfSenseAutoForward\DTO\PortForwardRule;
use Chr0mX\PfSenseAutoForward\Exceptions\PfSenseApiException;
use Chr0mX\PfSenseAutoForward\Services\PfSenseApiClient;
use Chr0mX\PfSenseAutoForward\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
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

    private function rule(bool $enabled = true): PortForwardRule
    {
        return new PortForwardRule(
            allocationId: 1,
            serverUuid: 'aaaa',
            serverName: 'Survival',
            nodeName: 'Node 1',
            targetIp: '10.0.0.5',
            port: 25565,
            protocol: 'tcp/udp',
            serverActive: $enabled,
            enabled: $enabled,
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
                && $request['source'] === 'any'
                && $request['disabled'] === false;
        });
    }

    public function test_creates_a_disabled_port_forward_for_a_rule_that_should_not_be_enabled(): void
    {
        Http::fake(['pfsense.example.test/*' => Http::response(['data' => []])]);

        $this->client()->createPortForward($this->rule(enabled: false));

        Http::assertSent(fn ($request) => $request['disabled'] === true);
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
                && $request['descr'] === 'pelican:aaaa:1'
                && $request['disabled'] === false;
        });
    }

    public function test_updates_an_existing_port_forward_by_id_with_disabled_and_protocol(): void
    {
        Http::fake(['pfsense.example.test/*' => Http::response(['data' => []])]);

        $this->client()->updatePortForwardRule(42, true, 'udp');

        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && str_ends_with(explode('?', $request->url())[0], '/api/v2/firewall/nat/port_forward')
                && $request['id'] === 42
                && $request['disabled'] === true
                && $request['protocol'] === 'udp';
        });
    }

    public function test_updates_an_existing_pass_rule_by_id_with_disabled_and_protocol(): void
    {
        Http::fake(['pfsense.example.test/*' => Http::response(['data' => []])]);

        $this->client()->updatePassRule(43, false, 'tcp');

        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && str_ends_with(explode('?', $request->url())[0], '/api/v2/firewall/rule')
                && $request['id'] === 43
                && $request['disabled'] === false
                && $request['protocol'] === 'tcp';
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

    public function test_wraps_a_connection_failure_as_a_pfsense_api_exception(): void
    {
        // Simulates what actually happens against a pfSense box with a
        // self-signed cert (or any other TLS/DNS/timeout failure): Guzzle
        // never gets as far as an HTTP response, so Http::send() throws
        // ConnectionException directly rather than returning a failed
        // Response. Callers should only ever need to catch
        // PfSenseApiException, so the client must translate this too.
        Http::fake(function () {
            throw new ConnectionException('cURL error 60: SSL certificate problem: self-signed certificate');
        });

        $this->expectException(PfSenseApiException::class);

        $this->client()->listPortForwardRules();
    }

    public function test_verify_tls_false_still_completes_a_request(): void
    {
        // Mainly guards against a typo breaking the withOptions() call
        // itself (e.g. wrong option key) - Http::fake() bypasses the actual
        // TLS handshake either way, so this can't assert Guzzle's `verify`
        // option was set, only that passing verifyTls: false doesn't throw
        // or otherwise change request construction.
        Http::fake(['pfsense.example.test/*' => Http::response(['data' => []])]);

        $client = new PfSenseApiClient(
            baseUrl: 'https://pfsense.example.test',
            apiKey: 'secret-key',
            interface: 'wan',
            verifyTls: false,
        );

        $this->assertSame([], $client->listPortForwardRules());
    }
}
