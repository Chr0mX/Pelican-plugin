# Changelog

All notable changes to the pfSense Auto NAT/Port-Forward plugin are documented in this file.

## [1.0.0] - 2026-08-19

Initial release.

### Added
- **Automatic reconciliation** - a scheduled job (default every 5 minutes, configurable) and an on-demand "Sync Now"
  button both diff Pelican's currently-assigned server allocations against pfSense's tagged NAT port-forward and
  firewall pass rules, creating what's missing and removing what's stale.
- **Detects pre-existing ports automatically.** There's no event to hook - `App\Models\Allocation` has no
  `$dispatchesEvents` mapping in Pelican's core - so this plugin reconciles from Pelican's actual allocation table
  every run instead of listening for create/delete events. That means allocations that existed before this plugin was
  ever installed are picked up and forwarded the very first time it runs, with no separate "import" step needed.
- **Stable rule identification** - every rule this plugin creates is tagged via its `descr` field with
  `pelican:<server_uuid>:<allocation_id>`, and only rules carrying that prefix are ever touched. Deletion goes through
  pfSense-pkg-RESTAPI's plural-endpoint, query-filtered DELETE (by `descr`) rather than by numeric id - pfSense object
  ids are just array indices and shift when other rules are reordered or removed, so deleting several orphaned rules
  by id in one pass is unsafe once the first deletion has happened.
- **Per-allocation target IP** - the NAT target is read from `Allocation::$ip` (the real address Wings binds to for
  that allocation), not a single global config value, so a panel spanning multiple nodes with different IPs is
  handled correctly.
- **Dry run mode** - logs and reports what would be created/removed without calling the pfSense API at all.
- **Scoping** - an optional required egg tag restricts forwarding to only servers whose egg carries it (leave unset to
  forward every assigned allocation on the panel).
- **Admin status page** - shows the last reconciliation's outcome (created/removed/unchanged/failed counts), when it
  last ran, and a dry-run indicator, with a "Sync Now" action. Polls automatically so a queued sync's result appears
  without a manual refresh.
- **Plugin settings page** (`HasPluginSettings`, including `getSettingsFormData()` from day one) - pfSense REST API
  URL/key/interface, default protocol, required egg tag, reconcile interval, dry run, and request timeout are all
  configurable without touching `.env` by hand.
