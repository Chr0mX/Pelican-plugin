<?php

return [
    'plugin_name' => 'Valheim Mod Manager',

    'nav' => [
        'label' => 'Mods',
    ],

    'tabs' => [
        'installed' => 'Installed',
        'browse' => 'Browse Thunderstore',
        'activity' => 'Activity Log',
    ],

    'toolbar' => [
        'refresh' => 'Refresh Installed Mods',
        'search_thunderstore' => 'Search Thunderstore',
        'update_all' => 'Update All',
        'settings' => 'Settings',
    ],

    'status' => [
        'installed' => 'Installed',
        'update_available' => 'Update Available',
        'missing_files' => 'Missing Files',
        'disabled' => 'Disabled',
        'unknown' => 'Unknown',
    ],

    'table' => [
        'columns' => [
            'name' => 'Name',
            'version' => 'Version',
            'author' => 'Author',
            'status' => 'Status',
            'last_updated' => 'Last Updated',
            'downloads' => 'Downloads',
            'latest_version' => 'Latest Version',
        ],
    ],

    'infolist' => [
        'description' => 'Description',
        'dependencies' => 'Dependencies',
        'installed_path' => 'Installed Path',
        'latest_release' => 'Latest Release',
        'no_dependencies' => 'No dependencies',
    ],

    'actions' => [
        'install' => 'Install',
        'update' => 'Update',
        'remove' => 'Remove',
        'enable' => 'Enable',
        'disable' => 'Disable',
        'update_selected' => 'Update Selected',
        'remove_selected' => 'Remove Selected',
        'enable_selected' => 'Enable Selected',
        'disable_selected' => 'Disable Selected',
    ],

    'modals' => [
        'remove_heading' => 'Remove Mod',
        'remove_description' => 'Are you sure you want to remove :name? Only files belonging to this package will be deleted.',
        'update_heading' => 'Update Mod',
        'update_description' => 'This will update :name from :old_version to :new_version. Files inside BepInEx/config are preserved.',
        'overwrite_config_label' => 'Overwrite existing config files',
        'overwrite_config_helper' => 'By default, files inside BepInEx/config are never touched during an install or update.',
    ],

    'notifications' => [
        'refresh_success' => 'Installed mods refreshed',
        'install_queued' => 'Install queued',
        'install_queued_body' => 'Installing :name :version in the background.',
        'install_success' => 'Installation completed',
        'install_success_body' => 'Successfully installed :name :version.',
        'install_failed' => 'Installation failed',
        'update_queued' => 'Update queued',
        'update_success' => 'Update completed',
        'update_success_body' => 'Successfully updated :name to :version.',
        'update_failed' => 'Update failed',
        'update_all_queued' => 'Updating all outdated mods in the background.',
        'remove_success' => 'Mod removed',
        'remove_success_body' => 'Successfully removed :name.',
        'remove_failed' => 'Removal failed',
        'enable_success' => 'Mod enabled',
        'disable_success' => 'Mod disabled',
        'toggle_failed' => 'Failed to change mod state',
        'settings_saved' => 'Settings saved',
        'no_updates' => 'Everything is already up to date',
    ],

    'errors' => [
        'invalid_zip' => 'The downloaded package is not a valid ZIP archive.',
        'download_failed' => 'Failed to download the package from Thunderstore.',
        'missing_manifest' => 'The package does not contain a manifest.json file.',
        'permission_denied' => 'The daemon denied this filesystem operation.',
        'filesystem_full' => 'The server ran out of disk space during this operation.',
        'already_installed' => 'This exact version is already installed.',
        'path_traversal' => 'Refusing to write outside of the server directory.',
    ],

    'progress' => [
        'downloading' => 'Downloading...',
        'verifying' => 'Verifying...',
        'extracting' => 'Extracting...',
        'installing' => 'Installing...',
        'cleaning_up' => 'Cleaning up...',
        'finished' => 'Finished',
        'failed' => 'Failed',
    ],
];
