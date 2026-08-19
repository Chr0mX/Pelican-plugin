# pfSense Auto NAT/Port-Forward (for Pelican Panel)

Automatically creates and removes pfSense NAT port-forward + firewall pass rules to match your Pelican server port
allocations - including ports that were already assigned before this plugin was ever installed - via
[pfSense-pkg-RESTAPI](https://pfrest.org).

## Requirements

- Pelican Panel with the plugin system enabled.
- A pfSense firewall with [pfSense-pkg-RESTAPI](https://github.com/jaredhendrickson13/pfsense-api) v2 installed and
  an API key generated (`System -> REST API -> Keys`).
- Network reachability from the panel to pfSense's REST API endpoint.

## Installation

1. Copy (or clone) this `pfsense-autoforward/` folder - not the whole `Pelican-plugin` repo - into your panel's
   `plugins` directory:
   ```
   /var/www/pelican/plugins/pfsense-autoforward
   ```
2. From your panel directory, run:
   ```
   php artisan p:plugin:install
   ```
   and select `pfsense-autoforward` from the list.
3. Open the plugin's settings page from the admin plugin list and fill in your pfSense REST API base URL, API key,
   and interface (see [Settings](#settings)).
4. A **pfSense Forwarding** page appears in the admin sidebar - use **Sync Now** there to run an immediate
   reconciliation, or wait for the scheduled one.

Alternatively, from the admin plugin list you can use **Import from URL** with a link to a tagged release's
`pfsense-autoforward.zip` asset (e.g.
`https://github.com/Chr0mX/Pelican-plugin/releases/download/<tag>/pfsense-autoforward.zip`), or **Import from File**
with that zip downloaded locally.

> The asset must be named exactly `pfsense-autoforward.zip` - Pelican derives the plugin id from the URL/filename
> (the segment before `.zip`) and it must match the `id` in `plugin.json`.

### Auto-updates

`plugin.json` sets `update_url` to this folder's `update.json` (served raw from GitHub), so once installed the panel's
plugin list will detect newer releases automatically and offer an in-place update.

### Releasing a new version

This plugin shares a GitHub repo with the Valheim Mod Manager plugin, so its releases use a **prefixed tag**
(`pfsense-autoforward-X.Y.Z`) to keep the two plugins' tag/release namespaces from colliding.

1. Bump `version` in `plugin.json`.
2. Update `update.json`'s `version` and `download_url` to match the new tag.
3. From inside this `pfsense-autoforward/` folder, build a zip whose root contains `plugin.json` directly (no
   wrapping folder) - `config/`, `lang/`, `src/`, `README.md`, `LICENSE`, but not `composer.json/.lock`,
   `phpunit.xml`, `tests/` or `vendor/`.
4. Publish a GitHub Release tagged `pfsense-autoforward-X.Y.Z`, with that zip attached and named exactly
   `pfsense-autoforward.zip`.

## Settings

Available from the plugin list in the admin panel (this is where Pelican surfaces `HasPluginSettings` pages):

| Setting                    | Env var                                    | Default   |
|-----------------------------|---------------------------------------------|-----------|
| pfSense REST API base URL   | `PFSENSEAF_URL`                             | -         |
| pfSense REST API key        | `PFSENSEAF_API_KEY`                         | -         |
| pfSense interface           | `PFSENSEAF_INTERFACE`                       | `wan`     |
| Default protocol            | `PFSENSEAF_DEFAULT_PROTOCOL`                | `tcp/udp` |
| Required egg tag            | `PFSENSEAF_REQUIRED_EGG_TAG`                | -         |
| Reconcile interval (minutes)| `PFSENSEAF_RECONCILE_INTERVAL_MINUTES`      | `5`       |
| Dry run                     | `PFSENSEAF_DRY_RUN`                         | `false`   |
| Request timeout (seconds)   | `PFSENSEAF_REQUEST_TIMEOUT`                 | `15`      |

Leave **Required egg tag** blank to forward every assigned allocation on the panel - only do that if every node behind
it should genuinely be internet-reachable through pfSense.

## Features

- **Reconciliation, not events** - `App\Models\Allocation` has no lifecycle events in Pelican's core, so rather than
  trying to hook a create/delete event that doesn't exist, this plugin periodically diffs pfSense's current rules
  against Pelican's actual allocation table (create what's missing, remove what's stale). Both the scheduled run and
  the "Sync Now" button run the exact same logic.
- **Picks up pre-existing ports automatically.** Since reconciliation always starts from Pelican's real allocation
  data rather than a change log, an allocation that was assigned before this plugin was installed is just as
  "expected" as one created a minute ago - there's no separate import/discovery step.
- **Safe, stable rule tracking** - every rule this plugin manages is tagged via its `descr` field with
  `pelican:<server_uuid>:<allocation_id>`, and only rules carrying that prefix are ever touched; anything else in your
  pfSense ruleset is left alone. Deletions go through pfSense-pkg-RESTAPI's plural-endpoint, `descr`-filtered DELETE
  rather than by numeric id, since pfSense object ids are just array indices that shift when other rules are
  reordered or removed - unsafe to rely on across a multi-rule delete pass.
- **Correct multi-node target IP** - the NAT target is read from `Allocation::$ip` (the actual address Wings binds to
  for that allocation), not one global setting, so it's correct even when a panel spans multiple nodes with different
  IPs.
- **Dry run mode** - see exactly what would be created/removed, logged and shown on the admin page, without ever
  calling the pfSense API.
- **Admin status page** - last reconciliation outcome (created/removed/unchanged/failed), last-run time, dry-run
  indicator, and a "Sync Now" action. Polls itself so a queued sync's result appears without a manual refresh.
- **Background jobs** - reconciliation runs as a queued job (the connection's default queue - deliberately *not* a
  named queue, since Pelican's official Docker image starts its worker as `queue:work --tries=3` with no `--queue=`
  flag and would otherwise never pick it up) so "Sync Now" never blocks the request. The scheduled run executes
  synchronously instead, since the scheduler is already a background process.
- **Plugin settings page** (`HasPluginSettings`, including `getSettingsFormData()`) - everything above is configurable
  without touching `.env` by hand.

## Scope of v1

- **Single port per allocation, not ranges.** `App\Models\Allocation` is fundamentally one IP+port per row, so this
  plugin mirrors that: one NAT rule + one pass rule per assigned allocation. Port-range forwarding isn't modeled.
- **One protocol setting for everything.** Pelican doesn't track a protocol per allocation or per egg - games decide
  that at the application level, not the panel - so there's a single `default_protocol` setting (default `tcp/udp`)
  applied to every rule this plugin creates, rather than a per-egg override.

## Architecture

```
plugin.json                          Plugin metadata (id, namespace, class, panels, ...)
config/pfsense-autoforward.php       Defaults, all overridable via env/settings page
lang/en/strings.php                  All user-facing copy

src/
  PfSenseAutoForwardPlugin.php       Filament Plugin contract + HasPluginSettings
  Providers/                         Auto-discovered Laravel service provider (container bindings, scheduler hook)

  DTO/
    PortForwardRule                  One expected NAT+pass rule pair, derived from one assigned Allocation
    ReconciliationSummary            Result of one reconciliation pass (created/removed/unchanged/failed counts)

  Services/
    AllocationRepository             Reads Pelican's Allocation table, scopes by required egg tag, maps to DTOs
    PfSenseApiClient                 Thin pfSense-pkg-RESTAPI v2 client (list/create/delete/apply)
    PortForwardReconciler            Diffs expected vs. actual, creates/removes, applies once per batch

  Support/ReconciliationStatus       Cache-backed last-run status, read by the admin page

  Jobs/ReconcilePortForwardsJob      Queued wrapper around the reconciler (connection's default queue)
  Console/Commands/ReconcilePortForwards  Scheduler entry point (runs synchronously)

  Exceptions/PfSenseApiException     Thrown on any failed pfSense API request

  Filament/Admin/Pages/PfSenseForwardingPage.php  Status page + "Sync Now" action
```

### Why reconciliation instead of listening for allocation events?

The initial design considered hooking `AllocationCreated`/`AllocationDeleted`-style events. Checked against Pelican's
actual source, `App\Models\Allocation` has no `$dispatchesEvents` mapping and no dedicated allocation lifecycle events
exist anywhere in `app/Events` - so there's nothing to listen for. Reconciling from the real allocation table on a
schedule (and on demand) avoids depending on an event that doesn't exist, and - as a direct consequence - also
satisfies "detect ports that already exist" for free: a pre-existing allocation is simply as "expected" as any other
during the very first reconciliation pass, with no separate discovery step needed.

### Why delete by `descr`, not by id?

pfSense-pkg-RESTAPI documents that object ids are the item's array index in the underlying pfSense config, not a
persistent identifier - they shift whenever another object is reordered or removed. Deleting several orphaned rules
by id within the same reconciliation pass would be unsafe (deleting the first shifts the ids of the rest). This
plugin instead deletes via the plural endpoints' query-filtered `DELETE .../port_forwards?descr=...` /
`.../rules?descr=...`, which pfSense-pkg-RESTAPI explicitly documents as the way to delete "based on a field other
than the id" - sidestepping the reordering hazard entirely.

## Development / testing

This repository ships a `composer.json` purely for local static analysis/testing - it is **not** used by Pelican
Panel to autoload the plugin (the panel discovers/autoloads plugins itself per `plugin.json`, as documented at
https://pelican.dev/docs/panel/advanced/plugins/). Running the test suite does not require a real panel install or a
real pfSense instance:

```bash
composer install
composer test
```

Tests are plain PHPUnit + [Orchestra Testbench](https://packagist.org/packages/orchestra/testbench). Since this
plugin has no access to a real Pelican Panel/pfSense instance in isolation, `tests/Stubs/App` provides minimal
stand-ins for `App\Models\{Allocation,Server,Egg}` and `App\Contracts\Plugins\HasPluginSettings` (mirroring the real
interface exactly, so a drift like the one that broke the Valheim Mod Manager plugin fails loudly at class-declaration
time) so the actual plugin services (`AllocationRepository`, `PfSenseApiClient`, `PortForwardReconciler`,
`PfSenseAutoForwardPlugin`) can be exercised end-to-end. These stubs are dev-only (`autoload-dev`) and irrelevant once
the plugin is installed in a real panel, where the genuine `App\*` classes are used instead. `PfSenseApiClient` is
tested against real pfSense-pkg-RESTAPI v2 endpoint paths and field names (verified against that project's own
[source](https://github.com/jaredhendrickson13/pfsense-api)), using `Http::fake()` rather than a live pfSense.

What isn't (and can't reasonably be) covered here: actually talking to a live pfSense instance, and real Filament
Livewire rendering - those require a running Pelican Panel + pfSense to verify against.
