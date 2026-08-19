<?php

return [
    /*
    |--------------------------------------------------------------------
    | pfSense connection
    |--------------------------------------------------------------------
    */
    'pfsense_url' => env('PFSENSEAF_URL'),
    'pfsense_api_key' => env('PFSENSEAF_API_KEY'),
    'pfsense_interface' => env('PFSENSEAF_INTERFACE', 'wan'),

    // pfSense's default webConfigurator/API certificate is self-signed
    // unless you've installed a trusted one - leave this on and every
    // request fails with a TLS handshake error. Turn it off only for a
    // pfSense box you already trust the identity of (e.g. reached over a
    // private/management network).
    'verify_tls' => (bool) env('PFSENSEAF_VERIFY_TLS', true),

    /*
    |--------------------------------------------------------------------
    | Rule contents
    |--------------------------------------------------------------------
    |
    | The NAT target IP is deliberately not a config value: Pelican's own
    | Allocation model already records the exact IP the Wings daemon binds
    | to for each allocation, and a panel can span multiple nodes with
    | different IPs - a single global target would be wrong for anything
    | but a single-node setup. See AllocationRepository.
    |
    | default_protocol applies to every rule: Pelican doesn't track a
    | protocol per allocation or per egg (games decide that at the
    | application level, not the panel), so there's no per-egg value to
    | read here. "tcp/udp" opens both, which is the safe default for an
    | unknown game; narrow it if you know every server behind this panel
    | only ever needs one.
    |
    */
    'default_protocol' => env('PFSENSEAF_DEFAULT_PROTOCOL', 'tcp/udp'),

    /*
    |--------------------------------------------------------------------
    | Scope
    |--------------------------------------------------------------------
    |
    | Only allocations belonging to a server whose egg carries this tag
    | are forwarded. Leave null to forward every assigned allocation on
    | the panel - only do that if you're certain every node behind it
    | should be internet-reachable through pfSense.
    |
    */
    'required_egg_tag' => env('PFSENSEAF_REQUIRED_EGG_TAG'),

    /*
    | Only allocations on one of these Pelican node ids are forwarded -
    | useful when only some nodes actually sit behind this pfSense (e.g. a
    | colo node vs. a home node behind a different router). Comma-separated,
    | e.g. "1,3". Leave null to forward allocations on every node.
    */
    'allowed_node_ids' => env('PFSENSEAF_ALLOWED_NODE_IDS'),

    /*
    |--------------------------------------------------------------------
    | Reconciliation
    |--------------------------------------------------------------------
    |
    | There's no native Pelican event for allocation create/update/delete
    | (checked against the panel's actual source - Allocation has no
    | $dispatchesEvents mapping), so this plugin doesn't try to hook one.
    | Instead it periodically reconciles pfSense's rules against Pelican's
    | current allocations (create what's missing, remove what's stale) -
    | which is also exactly what's needed to pick up ports that already
    | existed before this plugin was installed. A "Sync Now" button in
    | the admin page runs the same reconciliation immediately.
    |
    */
    'reconcile_interval_minutes' => (int) env('PFSENSEAF_RECONCILE_INTERVAL_MINUTES', 5),

    'dry_run' => (bool) env('PFSENSEAF_DRY_RUN', false),

    'request_timeout' => (int) env('PFSENSEAF_REQUEST_TIMEOUT', 15),
];
