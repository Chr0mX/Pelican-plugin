# Changelog

All notable changes to the pfSense Auto NAT/Port-Forward plugin are documented in this file.

## [1.1.0] - 2026-08-19

### Added
- **Rules now follow their server's power state.** When on (the new default), a mapped rule is only enabled while its
  server is actually running - stopped servers get their port forward *disabled*, not removed, so it's back the
  instant the server starts again with no re-create needed. Server state is read live via `Server::retrieveStatus()`
  (the same Wings call/cache the panel's own console uses); if the daemon can't be reached, the rule is left as-is
  rather than guessed at (fails open, not closed). Turn off entirely with the new **Disable rule when server is
  stopped** setting (`PFSENSEAF_DISABLE_WHEN_OFFLINE`) to go back to pre-1.1 behaviour.
- **Redesigned "Currently mapped" section as a real table** (Node, Server, Port, Forward type, Server, Rule), with:
  - An inline **toggle** to force a rule enabled or disabled regardless of automatic server-state behaviour.
  - An inline **forward type select** (All/TCP/UDP) to override the protocol for one allocation, independent of the
    plugin-wide default.
  - A **"Reset to automatic"** row action to clear both overrides and return to fully automatic behaviour.
  - Both controls persist to a new `pfsense_autoforward_overrides` table (one row per allocation with a non-default
    setting) and immediately queue a sync so pfSense reflects the change without waiting for the next scheduled run.
- New migration `pfsense_autoforward_overrides` (auto-runs on install/update, same as any other plugin migration) -
  `allocation_id` (unique, cascades on the allocation's own deletion), nullable `protocol`, nullable
  `enabled_override`.

### Changed
- Existing rules are now also PATCHed (not just created/removed) whenever their desired `disabled` or `protocol`
  value drifts from what pfSense currently has - previously the reconciler only ever created missing rules or removed
  orphaned ones.
- Admin page summary line gains a "N enable/disable synced" segment alongside created/removed/unchanged/failed.

### Fixed
- **Failed pfSense API requests logged with no explanation.** `PfSenseApiException` always captured the HTTP status
  and response body, but nothing ever surfaced them - the logged error was just `pfSense API request failed: POST
  /api/v2/firewall/nat/port_forward`, with no way to tell *why* pfSense rejected it (wrong API key, REST API in
  read-only mode, a rejected field value, etc.) without separately inspecting pfSense itself. The exception message
  now includes the status and, when pfSense returns its usual JSON error envelope, its own `response_id`/`message`
  (e.g. `... (405 ENDPOINT_METHOD_NOT_ALLOWED_IN_READ_ONLY_MODE: This endpoint is not allowed while the REST API is
  in read-only mode.)`) - falling back to a truncated raw body for a non-JSON response.
- **Disabling/removing a rule "worked, but very slowly" (sometimes minutes) instead of immediately.** `apply()`
  wasn't passing pfSense-pkg-RESTAPI's `async` control parameter, which defaults to `true` on the apply endpoint -
  that only *schedules* a deferred `filter_configure()` reload rather than reloading immediately. Every create was
  masking this (a brand-new rule showing up "eventually" is far less noticeable than an existing port staying open
  after being disabled), but the effect was identical for every change this plugin makes. `apply()` now sends
  `async: false`, which pfSense-pkg-RESTAPI's own dispatcher maps directly to `filter_configure_sync()` - an
  immediate, blocking reload before the request returns. Safe to block on: this always runs inside the already-
  background `ReconcilePortForwardsJob`, never in a request a user is waiting on.

## [1.0.2] - 2026-08-19

### Added
- **The admin status page now shows what's actually mapped, not just counts.** Requested after the counts-only
  summary ("6 rule(s) created · 0 removed · 0 already in sync") left no way to see *which* ports were opened. Three
  new sections: "Currently mapped" lists every allocation now forwarded as `<node> | <server> | <port>/<protocol>`
  (covers both this run's creations and everything already in sync); "Removed this run" lists orphaned rules that
  were cleaned up; "Errors" surfaces any per-rule failures, previously logged but never shown on the page.
- `PortForwardRule` gained a `nodeName` field (from `Allocation::$node_id` via the `node` relation) and a
  `describe()` method producing that display line.

## [1.0.1] - 2026-08-19

### Fixed
- **A pfSense host with a self-signed certificate (the default for pfSense's webConfigurator/API cert) crashed the
  entire reconciliation instead of failing gracefully.** `PfSenseApiClient` only caught HTTP-level failures
  (`$response->failed()`) and wrapped those in `PfSenseApiException`; a TLS/DNS/timeout failure never reaches an HTTP
  response at all - Laravel's HTTP client throws `Illuminate\Http\Client\ConnectionException` directly. That
  exception wasn't caught anywhere, so it propagated all the way up through the reconciler and the queued job,
  failing the scheduled `pfsense-autoforward:reconcile` command outright instead of recording a failed sync in the
  admin status page. Connection-level failures are now caught and wrapped the same way as HTTP failures.

### Added
- **Verify TLS certificate** setting (`PFSENSEAF_VERIFY_TLS`, default `true`) - turn off for a pfSense box using a
  self-signed certificate you already trust the identity of, instead of every sync failing outright.
- **Allowed node IDs** setting (`PFSENSEAF_ALLOWED_NODE_IDS`) - comma-separated Pelican node IDs; only allocations on
  one of these nodes are forwarded. Useful when only some nodes actually sit behind this pfSense (e.g. a colo node
  vs. a home node behind a different router). Combines with **Required egg tag** using AND - both conditions must
  match. Leave blank (the default) to allow every node, same as before this setting existed.

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
