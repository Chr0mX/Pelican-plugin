<?php

namespace Chr0mX\PfSenseAutoForward\Services;

use Chr0mX\PfSenseAutoForward\DTO\PortForwardRule;
use Chr0mX\PfSenseAutoForward\Exceptions\PfSenseApiException;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for pfSense-pkg-RESTAPI v2 (https://pfrest.org). Endpoint
 * paths, field names and singular/plural semantics below are taken from
 * that project's actual Endpoint/Model source
 * (RESTAPI/Endpoints/FirewallNATPortForward{,s}Endpoint.inc,
 * FirewallRule{,s}Endpoint.inc, Models/PortForward.inc, Models/FirewallRule.inc)
 * rather than assumed - if a specific pfSense/RESTAPI version differs, this
 * is the only class that needs to change - nothing above it knows or cares
 * about the wire format.
 */
class PfSenseApiClient
{
    // Singular endpoints: single-object GET (by id)/POST create/PATCH update.
    private const NAT_ENDPOINT = '/api/v2/firewall/nat/port_forward';

    private const RULE_ENDPOINT = '/api/v2/firewall/rule';

    // Plural ("many") endpoints: list via GET, and - importantly - DELETE
    // by query filter instead of by id. pfSense object ids are the item's
    // array index in config, not a persistent identifier: they shift
    // whenever another object is reordered or removed, so deleting several
    // orphaned rules by id in one pass (each delete shifting what remains)
    // is unsafe. Deleting by our own stable `descr` tag on the plural
    // endpoint sidesteps that entirely.
    private const NAT_ENDPOINT_MANY = '/api/v2/firewall/nat/port_forwards';

    private const RULE_ENDPOINT_MANY = '/api/v2/firewall/rules';

    private const APPLY_ENDPOINT = '/api/v2/firewall/apply';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $interface,
        private readonly int $timeout = 15,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPortForwardRules(): array
    {
        return $this->request('GET', self::NAT_ENDPOINT_MANY)['data'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPassRules(): array
    {
        return $this->request('GET', self::RULE_ENDPOINT_MANY)['data'] ?? [];
    }

    public function createPortForward(PortForwardRule $rule): void
    {
        $this->request('POST', self::NAT_ENDPOINT, [
            'interface' => $this->interface,
            'ipprotocol' => 'inet',
            'protocol' => $rule->protocol,
            'source' => 'any',
            // ":ip" is pfSense-pkg-RESTAPI's interface-address modifier -
            // "only packets addressed to this interface's current IP",
            // i.e. the pfSense GUI's "WAN address" destination option.
            'destination' => "{$this->interface}:ip",
            'destination_port' => (string) $rule->port,
            'target' => $rule->targetIp,
            'local_port' => (string) $rule->port,
            'descr' => $rule->descrTag(),
        ]);
    }

    public function createPassRule(PortForwardRule $rule): void
    {
        $this->request('POST', self::RULE_ENDPOINT, [
            'type' => 'pass',
            // FirewallRule's `interface` field allows multiple interfaces
            // (many: true) even though a port forward only ever needs one -
            // it must be sent as an array.
            'interface' => [$this->interface],
            'ipprotocol' => 'inet',
            'protocol' => $rule->protocol,
            'source' => 'any',
            'destination' => $rule->targetIp,
            'destination_port' => (string) $rule->port,
            'descr' => $rule->descrTag(),
        ]);
    }

    /**
     * Deletes by `descr` tag via the plural endpoint's query-filtered
     * DELETE, not by (non-persistent) numeric id - see the class docblock.
     */
    public function deletePortForwardRule(string $descrTag): void
    {
        $this->request('DELETE', self::NAT_ENDPOINT_MANY, query: ['descr' => $descrTag]);
    }

    public function deletePassRule(string $descrTag): void
    {
        $this->request('DELETE', self::RULE_ENDPOINT_MANY, query: ['descr' => $descrTag]);
    }

    /**
     * Must be called after every create/delete batch - pfSense stages rule
     * changes and does not apply them to the live ruleset until this is
     * called. (The API does offer a per-request `apply` control parameter
     * for immediate application, but it has no effect on plural-endpoint
     * DELETE - the operation this client relies on for safe removal - so a
     * single explicit apply() after a batch is used uniformly for both
     * creates and deletes instead.)
     */
    public function apply(): void
    {
        $this->request('POST', self::APPLY_ENDPOINT);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws PfSenseApiException
     */
    private function request(string $method, string $path, array $body = [], array $query = []): array
    {
        $response = Http::withHeaders(['X-API-Key' => $this->apiKey])
            ->asJson()
            ->acceptJson()
            ->timeout($this->timeout)
            ->send($method, rtrim($this->baseUrl, '/') . $path, [
                'json' => $body,
                'query' => $query,
            ]);

        if ($response->failed()) {
            throw new PfSenseApiException(
                "pfSense API request failed: $method $path",
                $response->status(),
                $response->body(),
            );
        }

        return $response->json() ?? [];
    }
}
