# Valheim Mod Manager (for Pelican Panel)

Browse, install, update and manage [BepInEx](https://bepinex.dev)/[Thunderstore](https://thunderstore.io) Valheim mods
directly from a server's client area in [Pelican Panel](https://pelican.dev) - no manual SFTP/file-manager work required.

## Requirements

- Pelican Panel with the plugin system enabled.
- A Valheim egg whose **tag** list includes `bepinex-mods`. The "Mods" navigation item is completely hidden for any
  server whose egg does not carry this tag (configurable, see [Settings](#settings)).
- BepInEx already installed on the server (this plugin manages mods *inside* `BepInEx/plugins` and
  `BepInEx/patchers`, it does not bootstrap BepInEx itself - the `BepInExPack` package on Thunderstore can be
  installed like any other mod through this UI).

## Installation

1. Copy (or clone) this `valheim-mod-manager/` folder - not the whole `Pelican-plugin` repo - into your panel's
   `plugins` directory:
   ```
   /var/www/pelican/plugins/valheim-mod-manager
   ```
2. From your panel directory, run:
   ```
   php artisan p:plugin:install
   ```
   and select `valheim-mod-manager` from the list.
3. Tag the Valheim egg you want to manage mods on with `bepinex-mods` (Admin -> Eggs -> select egg -> Tags).
4. Open any server using that egg - a new **Mods** item appears in the server sidebar.

Alternatively, from the admin plugin list you can use **Import from URL** with a link to a tagged release's
`valheim-mod-manager.zip` asset (e.g. `https://github.com/Chr0mX/Pelican-plugin/releases/download/<tag>/valheim-mod-manager.zip`),
or **Import from File** with that zip downloaded locally.

> The asset must be named exactly `valheim-mod-manager.zip` - Pelican derives the plugin id from the URL/filename
> (the segment before `.zip`) and it must match the `id` in `plugin.json`.

### Auto-updates

`plugin.json` sets `update_url` to this folder's `update.json` (served raw from GitHub), so once a version
including that field is installed, the panel's plugin list will detect newer releases automatically and offer an
in-place update - no manual re-import needed from that point on.

### Releasing a new version

1. Bump `version` in `plugin.json`.
2. Update `update.json`'s `version` and `download_url` to match the new tag.
3. From inside this `valheim-mod-manager/` folder, build a zip whose root contains `plugin.json` directly (no
   wrapping folder) - `config/`, `lang/`, `database/`, `src/`, `README.md`, `LICENSE`, but not
   `composer.json/.lock`, `phpunit.xml`, `tests/` or `vendor/`.
4. Publish a GitHub Release tagged with the same version, with that zip attached and named exactly
   `valheim-mod-manager.zip`.

## Features

- **Sidebar page** ("Mods") added to the Server panel, only visible for servers whose egg is tagged `bepinex-mods`.
- **Installed mods table** - scans `BepInEx/plugins` and `BepInEx/patchers` for `.dll` files, plain folders, and
  Thunderstore packages (`manifest.json`), merged with metadata this plugin tracks for anything it installed itself.
  Columns: Name, Version, Latest Version, Author, Status, Last Updated. Searchable and sortable.
- **Thunderstore browsing** - a "Browse Thunderstore" tab lists/searches the community's package list with icon,
  name, author, downloads, latest version and description, each with an **Install** button.
- **Updates** - per-mod "Current version -> Latest version" comparison with an **Update** button, plus toolbar
  **Update All** and a table bulk action **Update Selected**.
- **Safe installs/updates** - packages are downloaded and extracted into an isolated staging directory first,
  verified, and only then moved into the live plugins directory. `BepInEx/config` is never touched unless you tick
  "Overwrite existing config files" on the install/update confirmation.
- **Uninstall** - removes only the files this plugin recorded for that package; everything else on disk is left
  alone. Confirmation required, also available as a bulk action.
- **Enable/disable** - standalone `.dll` files are renamed to `*.dll.disabled`; folder-based packages are moved into
  a sibling `Disabled/` folder. Both are fully reversible. Also available as bulk actions.
- **Status badges** - Installed, Update Available, Missing Files, Disabled, Unknown.
- **Activity log** - a third tab lists everything the plugin has done for that server (installed/updated/
  removed/enabled/disabled), stored in a small database table.
- **Background jobs** - installs and updates run as queued jobs (on the connection's default queue - deliberately
  *not* a named queue, since Pelican's official Docker image starts its worker as `queue:work --tries=3` with no
  `--queue=` flag and would otherwise never pick them up) so large downloads never block the request; progress
  ("Downloading... / Verifying... / Extracting... / Installing... / Finished") is tracked in cache and shown live
  in the Installed tab's Status column and the "Update All" button while polling every 2 seconds. Enable/disable/
  remove are fast filesystem renames and run inline.
- **Plugin settings page** (`HasPluginSettings`) - Thunderstore endpoint & community, default game, default install
  directory, automatic update checking, auto-refresh after install, download timeout, temporary directory, and the
  required egg tag are all configurable without touching `.env` by hand.

## Settings

Available from the plugin list in the admin panel (this is where Pelican surfaces `HasPluginSettings` pages):

| Setting                     | Env var                                             | Default                          |
| ---------------------------- | ---------------------------------------------------- | --------------------------------- |
| Thunderstore API endpoint    | `VALHEIM_MOD_MANAGER_THUNDERSTORE_API_URL`            | `https://thunderstore.io`         |
| Thunderstore community slug  | `VALHEIM_MOD_MANAGER_THUNDERSTORE_COMMUNITY`          | `valheim`                        |
| Default game                 | `VALHEIM_MOD_MANAGER_DEFAULT_GAME`                    | `valheim`                        |
| Default install directory    | `VALHEIM_MOD_MANAGER_DEFAULT_INSTALL_DIRECTORY`       | `BepInEx/plugins`                |
| Automatic update checking    | `VALHEIM_MOD_MANAGER_AUTO_UPDATE_CHECK`               | `true`                           |
| Auto-refresh after install    | `VALHEIM_MOD_MANAGER_AUTO_REFRESH_AFTER_INSTALL`      | `true`                           |
| Download timeout (seconds)   | `VALHEIM_MOD_MANAGER_DOWNLOAD_TIMEOUT`                | `60`                              |
| Temporary directory          | `VALHEIM_MOD_MANAGER_TEMPORARY_DIRECTORY`             | `BepInEx/.valheim-mod-manager-tmp` |
| Required egg tag             | `VALHEIM_MOD_MANAGER_REQUIRED_TAG`                    | `bepinex-mods`                   |

## Architecture

```
plugin.json                          Plugin metadata (id, namespace, class, panels, ...)
config/valheim-mod-manager.php       Defaults, all overridable via env/settings page
lang/en/strings.php                  All user-facing copy

database/migrations/                 Activity log table

src/
  ValheimModManagerPlugin.php        Filament Plugin contract + HasPluginSettings
  Providers/                         Auto-discovered Laravel service provider (container bindings)

  Contracts/GameProviderInterface.php  The extensibility seam - see "Adding another game" below
  Games/ValheimProvider.php            The only game shipped today

  Enums/          ModStatus (badge), ModSource (dll/folder/thunderstore package)
  DTO/            Immutable value objects for Thunderstore API + parsed manifests + installed mods
  Support/        SafePath (path-traversal guard), ModManifestParser, ProgressReporter (cache-backed job progress)

  Services/
    GameProviderRegistry    Resolves which GameProviderInterface a Server matches
    ThunderstoreService     Game-agnostic Thunderstore v1 API client (search/find/versions), cached
    ModScanner              Reads BepInEx/plugins + patchers via the daemon and classifies every entry
    ModMetadataStore        Persists what this plugin installed as a small JSON file on the server itself
    ModInstaller            Download -> verify -> extract -> place -> track (install AND update)
    ModRemovalService       Deletes only the tracked files for one package
    ModToggleService        Enable/disable via rename or Disabled/ folder
    ModActivityLogger       Writes to the activity log table
    ModManagerService       Thin orchestrator the Filament page/facade talk to (no business logic in the page)

  Facades/ValheimModManager.php      Facade over ModManagerService, mirrors the pattern used by other Pelican
                                      mod-manager plugins (e.g. minecraft-modrinth)

  Jobs/           InstallModJob, UpdateModJob, BulkUpdateModsJob (connection's default queue)
  Models/         InstalledMod (Sushi-backed virtual model for the Filament table), ModActivityLog (real Eloquent)
  Filament/Server/Pages/ModsPage.php  The single "Mods" sidebar page (Installed / Browse / Activity Log tabs)
```

### Why the daemon file API instead of direct filesystem access?

Pelican Panel never has direct filesystem access to a game server - all reads/writes go through Wings (the daemon)
over its HTTP API, exposed to plugins via `App\Repositories\Daemon\DaemonFileRepository`. Every service in this
plugin (`ModScanner`, `ModMetadataStore`, `ModInstaller`, `ModToggleService`, `ModRemovalService`) is written against
that repository, mirroring the pattern used by the official `minecraft-modrinth` plugin. Downloads use the daemon's
own `pull` endpoint (so multi-hundred-MB zips never pass through the panel process), and extraction uses the
daemon's `decompress` endpoint into an isolated staging directory before anything touches the live plugins folder.

### Adding another game

Everything except `Games/ValheimProvider.php` is already game-agnostic. To support, say, Lethal Company:

```php
class LethalCompanyProvider implements GameProviderInterface { /* ... */ }
```

```php
app(GameProviderRegistry::class)->register(new LethalCompanyProvider());
```

`ThunderstoreService`, `ModScanner`, `ModInstaller`, `ModRemovalService`, `ModToggleService` and `ModsPage` all take
a `GameProviderInterface` and never hardcode Valheim - none of them need to change.

### Design decisions worth knowing about

- **"Expandable rows"** are implemented as a modal (`details` row action showing Description, Dependencies,
  Installed Path, Latest Release) rather than true inline row expansion, since Filament tables don't support the
  latter - this matches the pattern used by comparable Pelican plugins.
- **Enable/disable**: standalone `.dll` files are renamed to `*.dll.disabled` (BepInEx only loads files ending in
  `.dll`); folder-based packages are moved into a sibling `Disabled/` folder, which removes them from BepInEx's
  recursive plugin scan entirely. Both are reversible.
- **Config preservation**: when a package's zip contains a `BepInEx/config` folder, its contents are left behind in
  the (deleted) staging directory unless "Overwrite existing config files" was explicitly ticked. Existing files
  under `BepInEx/config` on the server are never modified by an install/update.
- **Updates and stale files**: since an update to the same package typically resolves to the same wrapper
  folder/file name, `ModInstaller` deletes the previous version's tracked files *before* moving the new ones into
  place, rather than diffing file lists afterwards - this avoids ever merging old and new files together.
- **Remove/Enable/Disable run inline** (not queued) since they are fast renames/deletes; **Install/Update/Update
  All** run as queued jobs since they involve a network download and extraction.

## Development / testing

This repository ships a `composer.json` purely for local static analysis/testing - it is **not** used by Pelican
Panel to autoload the plugin (the panel discovers/autoloads plugins itself per `plugin.json`, as documented at
https://pelican.dev/docs/panel/advanced/plugins/). Running the test suite does not require a real panel install:

```bash
composer install
composer test
```

Tests are plain PHPUnit + [Orchestra Testbench](https://packagist.org/packages/orchestra/testbench) for the pieces
that need a Laravel container (translations, cache, the queue-less path of the services). Since this plugin has no
access to a real Pelican Panel/Wings daemon in isolation, `tests/Stubs/App` provides minimal stand-ins for
`App\Models\Server`, `App\Models\Egg` and `App\Repositories\Daemon\DaemonFileRepository` (an in-memory virtual
filesystem) so the actual plugin services (`ModScanner`, `ModInstaller`, `ModMetadataStore`, `ModToggleService`,
`ModRemovalService`, `ThunderstoreService`, manifest/path parsing) can be exercised end-to-end. These stubs are
dev-only (`autoload-dev`) and are irrelevant once the plugin is installed in a real panel, where the genuine `App\*`
classes are used instead.

Covered by tests: Thunderstore dependency/version/package parsing, the Thunderstore API client (search, pagination,
caching, failure handling), path-traversal guards, manifest.json parsing, game provider tag matching, the metadata
store, the filesystem scanner's classification logic (managed/unmanaged, dll/folder/Thunderstore package,
missing/disabled detection), enable/disable, uninstall, and the full install/update flow (flat packages, BepInEx-
layout packages, config preservation, and stale-file cleanup on update).

What isn't (and can't reasonably be) covered here: actually talking to a live Wings daemon, real Filament
Livewire rendering, and the queue worker itself - those require a running Pelican Panel + Wings instance to verify
against.
