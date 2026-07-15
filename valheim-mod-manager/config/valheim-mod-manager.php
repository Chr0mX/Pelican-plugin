<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Thunderstore API
    |--------------------------------------------------------------------------
    |
    | Base URL of the Thunderstore instance and the community slug used to
    | scope package requests (e.g. "https://thunderstore.io" + "valheim" =>
    | https://thunderstore.io/c/valheim/api/v1/package/).
    |
    */
    'thunderstore_api_url' => env('VALHEIM_MOD_MANAGER_THUNDERSTORE_API_URL', 'https://thunderstore.io'),

    'thunderstore_community' => env('VALHEIM_MOD_MANAGER_THUNDERSTORE_COMMUNITY', 'valheim'),

    /*
    |--------------------------------------------------------------------------
    | Default game
    |--------------------------------------------------------------------------
    |
    | Slug of the GameProviderInterface implementation used when a server's
    | provider cannot otherwise be determined. Additional games can register
    | their own provider without changing this plugin, see src/Contracts/GameProviderInterface.php.
    |
    */
    'default_game' => env('VALHEIM_MOD_MANAGER_DEFAULT_GAME', 'valheim'),

    /*
    |--------------------------------------------------------------------------
    | Default install directory
    |--------------------------------------------------------------------------
    |
    | Relative to the server root. Used as the fallback plugins directory
    | when a game provider does not define its own.
    |
    */
    'default_install_directory' => env('VALHEIM_MOD_MANAGER_DEFAULT_INSTALL_DIRECTORY', 'BepInEx/plugins'),

    /*
    |--------------------------------------------------------------------------
    | Automatic update checking
    |--------------------------------------------------------------------------
    |
    | When enabled, the installed mods table will resolve the latest available
    | version for every installed mod (cached) so update badges stay current.
    |
    */
    'auto_update_check' => (bool) env('VALHEIM_MOD_MANAGER_AUTO_UPDATE_CHECK', true),

    /*
    |--------------------------------------------------------------------------
    | Auto-refresh after install
    |--------------------------------------------------------------------------
    |
    | Rescan the filesystem automatically once an install/update/removal job
    | finishes instead of requiring a manual "Refresh Installed Mods" click.
    |
    */
    'auto_refresh_after_install' => (bool) env('VALHEIM_MOD_MANAGER_AUTO_REFRESH_AFTER_INSTALL', true),

    /*
    |--------------------------------------------------------------------------
    | Download timeout
    |--------------------------------------------------------------------------
    |
    | Seconds to wait for the daemon to finish downloading a package before
    | the install/update job is considered failed.
    |
    */
    'download_timeout' => (int) env('VALHEIM_MOD_MANAGER_DOWNLOAD_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Temporary directory
    |--------------------------------------------------------------------------
    |
    | Relative to the server root. Used as a staging area while downloading
    | and extracting packages, so partially installed mods never land
    | directly inside the live plugins directory.
    |
    */
    'temporary_directory' => env('VALHEIM_MOD_MANAGER_TEMPORARY_DIRECTORY', 'BepInEx/.valheim-mod-manager-tmp'),

    /*
    |--------------------------------------------------------------------------
    | Required egg tag
    |--------------------------------------------------------------------------
    |
    | The plugin's "Mods" navigation item and pages are only available for
    | servers whose egg is tagged with this value.
    |
    */
    'required_tag' => env('VALHEIM_MOD_MANAGER_REQUIRED_TAG', 'bepinex-mods'),

    /*
    |--------------------------------------------------------------------------
    | Metadata file name
    |--------------------------------------------------------------------------
    |
    | Name of the JSON file this plugin stores inside the plugins directory
    | to keep track of everything it installed (namespace, version, files,
    | dependencies, enabled state).
    |
    */
    'metadata_file' => env('VALHEIM_MOD_MANAGER_METADATA_FILE', '.valheim-mod-manager.json'),
];
