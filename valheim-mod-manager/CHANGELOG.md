# Changelog

All notable changes to the Valheim Mod Manager plugin are documented in this file.

## [1.0.14] - Unreleased

### Fixed
- **Residual `package.zip` left behind after install/update.** The flat-package install path's metadata-file
  exclusion list never excluded the downloaded zip itself (extracted in place alongside its contents), so it was
  swept up into the payload and moved into the live mod folder along with the actual files.

### Changed
- **Mods now keep `manifest.json`/`icon.png` on disk instead of stripping them**, matching how r2modman/
  Thunderstore Mod Manager lay out a profile. `ModScanner` reads a managed mod's real description/icon straight
  from disk the same way it already does for unmanaged folders, instead of needing a live Thunderstore lookup.
  Mods installed before this change get a one-time `manifest.json` backfilled automatically the next time they're
  scanned, using the same information the ledger and Thunderstore fallback already had.

## [1.0.13] - 2026-07-15

### Fixed
- **Install/Update jobs never actually running.** `InstallModJob`, `UpdateModJob`, and `BulkUpdateModsJob` were
  dispatched onto a queue named `valheim-mod-manager`, but Pelican's official Docker image runs its bundled queue
  worker as `queue:work --tries=3` with no `--queue=` flag - which only processes the connection's *default* queue.
  Jobs on any other named queue were silently never picked up: no error, no log entry, and no change to installed
  mods, because the job's `handle()` never executed. All three jobs now dispatch to the default queue instead.
- **Description missing for managed mods.** `ModMetadataStore`'s ledger never stored a description for anything
  this plugin installed itself, so the mod-details modal always showed nothing for managed mods. Now falls back to
  the description Thunderstore reports for that package, the same pattern used for the icon/version fallback.

### Added
- **Live progress while installing/updating.** The Installed tab's Status column now shows the current stage
  (Downloading.../Verifying.../Extracting.../Installing.../Finished) for any mod currently being installed or
  updated, and the "Update All" toolbar button reflects batch progress. The table polls every 2 seconds only while
  something is actually in flight.

## [1.0.12] - 2026-07-15

### Added
- Mods installed by this plugin itself now show an icon too, falling back to the icon Thunderstore reports for
  that package (managed mods never have an `icon.png` on disk - the installer strips it by design).
- "Last Updated" column added to the Browse Thunderstore table, sourced from Thunderstore's `date_created` field.

## [1.0.11] - 2026-07-15

### Fixed
- Browse Thunderstore / Installed tab navigation sometimes needing two clicks to register. The underlying cause:
  `installedMods()` (a live daemon filesystem scan plus one Thunderstore lookup per tracked mod) had no caching
  across requests, so it re-ran on every tab switch, search keystroke, sort, and pagination click. Now cached for
  15 seconds per server, with explicit invalidation on every action that changes disk state.

### Added
- Mod icons shown in the Installed tab for unmanaged folder-based mods (e.g. ones installed via the egg's own
  script) that still have their `icon.png` intact.

### Changed
- Repository restructured: the plugin now lives in a `valheim-mod-manager/` subfolder instead of the repo root,
  to make room for additional game providers/plugins later.

## [1.0.10] - 2026-07-15

### Fixed
- `ThunderstoreService` re-fetched and re-deserialized the entire cached Thunderstore package list once per
  installed mod (to resolve each one's "latest version"), instead of once per page load. Now memoized per request.

## [1.0.9] - 2026-07-14

### Fixed
- The Browse Thunderstore memory-limit workaround (raising `memory_limit` before fetching the package list) tried
  to restore the previous value afterwards, which threw every time current memory usage exceeded that value -
  causing every Browse tab visit to silently skip the cache and re-fetch/re-decode the full catalog. The restore
  attempt was removed; PHP resets `memory_limit` between requests on its own.

## [1.0.8] - 2026-07-14

### Fixed
- `TypeError` when browsing Thunderstore: Filament's non-Eloquent table records must be plain arrays, not DTO
  objects. Added `ThunderstorePackageData::toTableRow()` to project each package into the array shape the table
  actually needs.

## [1.0.7] - 2026-07-14

### Fixed
- Out-of-memory crash when browsing/searching Thunderstore. A community's full package list includes every
  historical version (with full changelog text) of every package, which was blowing through PHP's memory limit
  before the plugin ever got a chance to discard old versions. Now only the latest version of each package is
  parsed and retained, and the package list is cached for 30 minutes.

## [1.0.6] - 2026-07-14

Version bump only, published to keep the release history moving forward after 1.0.5.

## [1.0.5] - 2026-07-14

### Fixed
- `TypeError` when opening the Mods page: `InstalledMod::hydrate()` collided with Eloquent's own reserved
  `hydrate()` method, silently shadowing it and breaking table pagination. Renamed to `forServer()`.

## [1.0.4] - 2026-07-14

### Fixed
- Mods page rendering completely blank with no error: Filament v4 requires `HasTable` pages to implement
  `content(Schema $schema)` returning the tabs and embedded table; without it, nothing rendered at all.

## [1.0.3] - 2026-07-14

### Added
- Auto-update support via `plugin.json`'s `update_url`, pointing at this repo's `update.json` - once installed,
  the panel's plugin list detects new releases automatically and offers an in-place update.

## [1.0.2] - 2026-07-14

### Fixed
- Blank Mods page whenever the configured cache store couldn't write (e.g. an unwritable `storage/framework/cache`
  directory), since the uncaught exception from `cache()->remember()` broke the whole page. Added `SafeCache`, a
  wrapper that falls back to running the callback uncached instead of throwing.

## [1.0.0] - 2026-07-13

### Added
- Initial release: browse, install, update, and manage BepInEx/Thunderstore Valheim mods directly from a server's
  Pelican Panel client area. Installed mods table, Thunderstore browsing/search, safe install/update preserving
  `BepInEx/config`, safe uninstall, enable/disable, activity log, plugin settings page, and background install/
  update jobs.

[1.0.14]: https://github.com/Chr0mX/Pelican-plugin/compare/1.0.13...HEAD
[1.0.13]: https://github.com/Chr0mX/Pelican-plugin/compare/1.0.12...1.0.13
[1.0.12]: https://github.com/Chr0mX/Pelican-plugin/compare/1.0.11...1.0.12
[1.0.11]: https://github.com/Chr0mX/Pelican-plugin/compare/1.0.10...1.0.11
[1.0.10]: https://github.com/Chr0mX/Pelican-plugin/compare/1.0.9...1.0.10
[1.0.9]: https://github.com/Chr0mX/Pelican-plugin/compare/1.0.8...1.0.9
[1.0.8]: https://github.com/Chr0mX/Pelican-plugin/compare/1.0.7...1.0.8
[1.0.7]: https://github.com/Chr0mX/Pelican-plugin/compare/1.0.6...1.0.7
[1.0.6]: https://github.com/Chr0mX/Pelican-plugin/compare/1.0.5...1.0.6
[1.0.5]: https://github.com/Chr0mX/Pelican-plugin/compare/1.0.4...1.0.5
[1.0.4]: https://github.com/Chr0mX/Pelican-plugin/compare/1.0.3...1.0.4
[1.0.3]: https://github.com/Chr0mX/Pelican-plugin/compare/1.0.2...1.0.3
[1.0.2]: https://github.com/Chr0mX/Pelican-plugin/compare/1.0.0...1.0.2
[1.0.0]: https://github.com/Chr0mX/Pelican-plugin/releases/tag/1.0.0
