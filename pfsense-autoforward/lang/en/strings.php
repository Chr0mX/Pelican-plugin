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
            'unchanged' => ':count already in sync',
            'failed' => ':count failed',
        ],
        'dry_run_badge' => 'Dry run - no changes are being applied',
        'mapped_heading' => 'Currently mapped',
        'mapped_description' => 'Node | Server | Port opened',
        'removed_heading' => 'Removed this run',
        'errors_heading' => 'Errors',
    ],

    'actions' => [
        'sync_now' => 'Sync Now',
    ],

    'notifications' => [
        'sync_queued' => 'Sync queued',
        'sync_queued_body' => 'Reconciling pfSense rules against current server allocations in the background.',
        'settings_saved' => 'Settings saved',
    ],

    'errors' => [
        'missing_config' => 'pfSense URL and API key must be configured before syncing.',
        'request_failed' => 'pfSense API request failed (:status): :message',
    ],
];
