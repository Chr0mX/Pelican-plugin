<?php

return [
    'plugin_name' => 'pfSense Auto NAT/Port-Forward',

    'nav' => [
        'label' => 'pfSense Forwarding',
    ],

    'page' => [
        'heading' => 'pfSense Port Forwarding',
        'last_run' => 'Last sync',
        'never_run' => 'Never run yet',
        'status' => [
            'idle' => 'Idle',
            'running' => 'Syncing...',
            'success' => 'Last sync succeeded',
            'failed' => 'Last sync failed',
        ],
        'summary' => [
            'created' => ':count rule(s) created',
            'removed' => ':count rule(s) removed',
            'toggled' => ':count enable/disable synced',
            'unchanged' => ':count already in sync',
            'failed' => ':count failed',
        ],
        'dry_run_badge' => 'Dry run - no changes are being applied',
        'mapped_heading' => 'Currently mapped',
        'mapped_empty' => 'Nothing mapped yet - run a sync to populate this.',
        'removed_heading' => 'Removed this run',
        'errors_heading' => 'Errors',
        'table' => [
            'node' => 'Node',
            'server' => 'Server',
            'port' => 'Port',
            'protocol' => 'Forward type',
            'server_status' => 'Server',
            'rule_status' => 'Rule',
            'reset_override' => 'Reset to automatic',
        ],
        'protocol_options' => [
            'tcp/udp' => 'All (TCP & UDP)',
            'tcp' => 'TCP only',
            'udp' => 'UDP only',
        ],
    ],

    'actions' => [
        'sync_now' => 'Sync Now',
    ],

    'notifications' => [
        'sync_queued' => 'Sync queued',
        'sync_queued_body' => 'Reconciling pfSense rules against current server allocations in the background.',
        'settings_saved' => 'Settings saved',
        'override_saved' => 'Saved',
        'override_saved_body' => 'Syncing pfSense to match in the background.',
        'override_reset' => 'Reset to automatic',
    ],

    'errors' => [
        'missing_config' => 'pfSense URL and API key must be configured before syncing.',
        'request_failed' => 'pfSense API request failed (:status): :message',
    ],
];
