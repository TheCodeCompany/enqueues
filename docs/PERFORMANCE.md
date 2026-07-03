# Performance: Asset Caching & the Settings Page

Enqueues resolves asset metadata (file paths, cache-busting versions, dependency arrays) from the
filesystem. To keep that cheap it offers a single **asset cache mode** with three settings, plus an
admin page to control and measure it.

| Mode | What it does | Never stale? |
|:-----|:-------------|:-------------|
| **Off** | Resolve assets on every request — the pre-cache behaviour. | n/a |
| **Per-request** (O1) | In-process static memo: dedupes lookups within one page load, nothing stored across requests. | ✅ dies with the request |
| **Persistent** (O2) | Also caches across requests in the object cache, keyed by the build signature. | ⚠️ cross-deploy staleness if not invalidated — see below |

**Caching defaults to Off (opt-in).** A fresh install behaves exactly like the pre-cache framework
until a mode is chosen. `enqueues_cache_mode()` is the single source of truth; `is_request_memo_enabled()`
and `is_cache_enabled()` both derive from it, so the settings page and the runtime can never disagree.

> **Persistent needs a real object cache.** WordPress transients fall back to the options table when no
> external object cache (Redis/Memcached) is present. We measured that DB-transient persistence is
> *net-negative* versus recomputing, so Persistent mode **only actually persists when
> `wp_using_ext_object_cache()` is true**. Without one (local dev, or a prod DB cloned to a host with no
> object cache) Persistent transparently degrades to Per-request — the in-process memo still runs. The
> `enqueues_is_cache_enabled` filter can force the DB path if you want to test it.

## The Settings page

Go to **Settings → Enqueues** (administrators only by default — `manage_options`, filterable via
`enqueues_settings_capability`). It exposes:

- **Asset cache mode** — a single radio: **Off** / **Per-request (static)** / **Persistent (object
  cache)**. If a constant pins the mode (see below) all three options are locked and the page states
  which constant is in force and the effective mode.
- **Cache TTL** — how long persistent entries live (minimum 1 hour); shown as a human-readable duration.
- **Profiler** — a checkbox (off by default) plus a **log size** selector; see [Profiler](#the-cache-profiler).
- **Flush cache now** — discards all persistent entries (rotates the cache salt). Run this after a deploy.
- **Status** — the *effective* state (mode, per-request memo active, persistent cache active, whether an
  external object cache is present, TTL, build signature).

## Per-request (O1)

Memoises `asset_find_file_path()`, `get_asset_page_type_file_data()`, and the per-block
`get_block_asset_version()` for the lifetime of one request. This collapses the duplicate lookups the
asset and block systems make for the same files — notably the *2×-per-block* version computation that
runs once for each of the `block_type_metadata` and `block_type_metadata_settings` filters.

Because the memo lives in process memory and is discarded when the request ends, it can never serve
stale data across requests or deploys. It is the safe, recommended layer — **Per-request** is the mode
to pick if you want a win with zero staleness risk. (Persistent includes this memo on top of the
cross-request cache.)

## Persistent (O2)

When enabled *and an external object cache is present*, caches the expensive cross-request work:

- the theme template-directory scan and the page-type asset-existence scan, and
- the **block asset-version map** (every block's computed version in a single entry).

Every entry is namespaced by a **build signature** so a new build produces fresh keys and the old
entries are abandoned (and expire via TTL). One object-cache read per request then replaces the
filesystem storm.

> **Trade-off:** an object-cache `get` is a network round-trip (~0.1–0.5 ms). It only pays off when it
> replaces *more* filesystem work than that — i.e. on cold-cache, high-asset-count, or slow-disk
> requests. For a warm, OPcache-enabled host the win is modest. Turn the [profiler](#the-cache-profiler)
> on to quantify it before relying on it.

### The build signature & invalidation

`get_enqueues_build_signature()` fingerprints the **content hashes** baked into every compiled
`.asset.php` (the theme JS dir and each block). Because it is content-based, it:

- ✅ moves on any JS/asset change, **including a block-only deploy** that leaves the main bundle untouched, and
- ✅ is immune to the git-checkout "mtime is not bumped for unchanged files" pitfall.

**Known limitation:** a change that adds *no* compiled asset — a new template `.php`, or a CSS-only
change with no corresponding `.asset.php` — will not move the signature. On a host that deploys via
**git checkout** (e.g. WP Engine), `switch_theme` / `upgrader_process_complete` do **not** fire, so
their auto-flush hooks won't help either.

**Therefore, if you run Persistent in production, do one of:**

1. **Flush on deploy** — click *Flush cache now*, or call `\Enqueues\flush_enqueues_cache()` from your
   deploy step / a WP-CLI command. *(Simplest.)*
2. **Bind the signature to your deploy** — return your deploy hash / git SHA from the
   `enqueues_build_signature` filter so every deploy invalidates automatically:
   ```php
   add_filter( 'enqueues_build_signature', fn() => defined( 'WPE_DEPLOY_SHA' ) ? WPE_DEPLOY_SHA : 'local' );
   ```

The TTL is the automatic backstop for the residual window, which is why it is clamped to ≥ 1 hour.

## The cache profiler

Off by default and free when off. Turn it on (Settings → Enqueues → **Profiler**) to quantify the
cache's value via a **runtime counterfactual**: every cached operation records both its with-cache
(hit) time and its without-cache (compute/miss) time. The panel then shows, per operation, the hit
rate, average with/without, and a **signed saving** — a *negative* saving means the cache read costs
more than recomputing on this backend (e.g. DB transients with no object cache).

- Records **front-end page renders only** — cron, AJAX, admin, REST and CLI requests are skipped so
  background traffic neither pollutes the data nor adds an options-table write.
- Keeps a ring buffer of the last **N** requests (**log size**, 10–200 in steps of 10, default 20).
- **Clears its stored stats when switched off** — it is a measure-then-disable tool, not something to
  leave running in normal production.

Toggle: the `profile` setting, the `ENQUEUES_PROFILE_ENABLED` constant, or the
`enqueues_is_profile_enabled` filter. Functions: `is_profile_enabled()`, `enqueues_profile_record()`,
`enqueues_profile_persist()`, `enqueues_profile_reset()`, `enqueues_profile_log_max()`.

> Caveat: "without cache" is sampled only from cache-fill events (cold start / post-deploy / TTL
> expiry), so its sample count is small and measured under a cold filesystem — treat the saving as a
> directional estimate. Totals are a lower bound under concurrency (each request writes its own samples,
> so on multi-worker hosts some are overwritten).

## Build-time manifest (deploy-generated, fastest)

The cache tiers above resolve asset metadata *at runtime* (scan, then cache the result). The manifest
resolves the most expensive piece — the recursive theme-tree scan for template files — **at
build/deploy time** instead, so production requests read one opcache-cached PHP file and never walk the
theme tree.

- **Generate it in your deploy pipeline**, after building assets: `wp enqueues manifest build`. It
  writes `{theme}/dist/enqueues-manifest.php` (the template list + the current build signature).
- **It supersedes the scan/transient** for `theme_template_files` when present; everything else is
  unchanged.
- **Safe by default**: ignored in local dev (`is_local()` — assets change live), and if its stamped
  build signature no longer matches the current build (a deploy changed assets without rebuilding the
  manifest) it is ignored and the runtime scan takes over. A stale manifest degrades to
  correct-but-slower, never to stale output.
- **Opt-in per site**: with no manifest file the framework behaves exactly as before. Filters:
  `enqueues_manifest_enabled` (default true), `enqueues_manifest_path`, and
  `enqueues_manifest_validate_signature` (default true — set false to skip the signature check for a
  little more speed on sites that always rebuild the manifest on deploy).
- CLI: `wp enqueues manifest build | clear | status`.

Recommended deploy-step order: build assets → `wp enqueues manifest build` → `wp enqueues flush`.

## Constants, filters & functions

### Constants

| Constant | Effect |
|:---------|:-------|
| `ENQUEUES_CACHE_MODE` | The single config-as-code override: `off` \| `request` \| `persistent`. Overrides the saved option at read time. |
| `ENQUEUES_CACHE_ENABLED` | **Deprecated.** A boolean from before the mode selector; mapped to a mode via a back-compat shim (`true` → `persistent`, `false` → `off`) and removed in the next major. Use `ENQUEUES_CACHE_MODE`. |
| `ENQUEUES_CACHE_TTL` | Force the TTL (seconds); overrides the setting. |
| `ENQUEUES_PROFILE_ENABLED` | Force the profiler on/off; overrides the setting. |

Mode resolution order: **option → `ENQUEUES_CACHE_ENABLED` (deprecated) → `ENQUEUES_CACHE_MODE` →
`enqueues_cache_mode` filter** (a bogus filter value falls back to the resolved mode). Persistent then
additionally requires an external object cache to actually persist.

### Filters & actions

| Filter/Action | Summary |
|:--------------|:--------|
| `enqueues_cache_mode` | Final say on the mode (`off` \| `request` \| `persistent`). |
| `enqueues_is_cache_enabled` | Final say on whether Persistent (O2) is active (bool). |
| `enqueues_is_request_memo_enabled` | Final say on whether the Per-request memo (O1) is active (bool). |
| `enqueues_cache_ttl` | Filter the persistent cache TTL in seconds (int). |
| `enqueues_build_signature` | Override the build signature — return a deploy hash / git SHA for guaranteed per-deploy invalidation (string). |
| `enqueues_is_profile_enabled` | Final say on whether the profiler is on (bool). |
| `enqueues_profile_log_max` | The profiler ring-buffer size (int, 10–200). |
| `enqueues_settings_capability` | The capability required to view/change the settings page (string, default `manage_options`). |
| `enqueues_cache_flushed` (action) | Fires after `flush_enqueues_cache()` completes. |

### Functions

| Function | Purpose |
|:---------|:--------|
| `enqueues_cache_mode(): string` | The resolved mode — single source of truth. |
| `is_request_memo_enabled(): bool` | Whether the Per-request memo (O1) is active. |
| `is_cache_enabled(): bool` | Whether Persistent (O2) is active (mode is persistent **and** an object cache is present). |
| `get_cache_ttl(): int` | Effective TTL. |
| `get_enqueues_build_signature(): string` | Current build signature. |
| `enqueues_cache_key( string $key ): string` | Build a signature-namespaced transient key. |
| `flush_enqueues_cache(): void` | Invalidate all persistent entries (rotates the salt). |
| `enqueues_get_settings(): array` / `enqueues_setting( $key, $default )` | Read the settings (memoised). |

## Measuring impact in production

The built-in [profiler](#the-cache-profiler) is the purpose-built tool — it gives you the signed
per-operation saving directly. The methods below are useful for an end-to-end page-time A/B, or when
you can't leave the profiler on. Remember two things first:

- **Anonymous traffic is full-page-cached** (e.g. WP Engine EverCache), so registration cost never
  touches it. The win only appears on **logged-in / editor / cache-miss** requests — so measure those.
- **Measure server time, not TTFB.** Bypass the page cache (be logged in, or add a unique query string
  per request) and take the **median of many samples** — a few ms of difference is easily lost in noise.

Practical methods, cheapest first:

1. **The built-in profiler.** Set the mode to Per-request for a window to bank "without cache" (compute)
   samples, then switch to Persistent and Flush; read the signed saving per operation. Turn it off when
   done (it clears its stats).

2. **Query Monitor (zero extra tooling).** Log in, open a page, read **Page Generation Time**. Set mode
   to Off → sample ~10 loads → note the median; set mode to Persistent → **Flush** → sample again. (QM
   adds its own overhead and only runs for admins — that's fine, it's a like-for-like A/B and admin
   requests are exactly the cache-miss path the change affects.)

3. **A `Server-Timing` header probe (one tiny mu-plugin).** Emit the real PHP generation time as a
   standard header you can read with `curl -sI` or in browser DevTools → Network → Timing:
   ```php
   // mu-plugins/enqueues-server-timing.php — gate behind a secret query arg so it's safe in prod.
   add_filter( 'wp_headers', function ( $h ) {
       if ( ( $_GET['st'] ?? '' ) === 'YOUR_SECRET' ) {
           $h['Server-Timing'] = sprintf( 'wp;dur=%.1f', ( microtime( true ) - $GLOBALS['timestart'] ) * 1000 );
       }
       return $h;
   } );
   ```
   Then loop with a **unique query string** to bypass the page cache:
   ```bash
   for i in $(seq 1 40); do curl -sI "https://example.com/?st=YOUR_SECRET&cb=$RANDOM" | grep -i server-timing; done \
     | sed 's/.*dur=//' | sort -n | awk '{a[NR]=$1} END{print "median", a[int(NR/2)]}'
   ```
   Run that with the mode Off, then Persistent (and flushed), and compare medians.

If the medians don't separate cleanly after dozens of samples, that's a real result too: it means the
saving is below the noise floor on your hardware, and **Per-request** is the right place to stop.
