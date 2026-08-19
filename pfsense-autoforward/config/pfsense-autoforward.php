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
