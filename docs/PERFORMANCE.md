# Performance: Asset Caching & the Settings Page

Enqueues resolves asset metadata (file paths, cache-busting versions, dependency arrays) from the
filesystem. To keep that cheap, it offers **two independent, toggleable layers** plus an admin page
to control them.

| Layer | What it does | Default | Risk |
|:------|:-------------|:--------|:-----|
| **O1 — Request memo** | Memoises asset lookups for the duration of a single request | **On** | None (in-process; dies with the request) |
| **O2 — Persistent cache** | Caches resolved metadata across requests in the object cache | **Off** | Cross-deploy staleness if not invalidated — see below |

## The Settings page

Go to **Settings → Enqueues** (`manage_options` capability). It exposes:

- **Request memo (O1)** — checkbox, on by default.
- **Persistent cache (O2)** — checkbox, off by default. Disabled (and ignored) if the
  `ENQUEUES_CACHE_ENABLED` constant is set.
- **Cache TTL** — how long persistent entries live (minimum 1 hour).
- **Flush cache now** — discards all persistent entries (rotates the cache salt). Run this after a deploy.
- **Status** — the *effective* state of each layer (after constants and filters), the active TTL, and
  the current build signature.

## O1 — Request memo

Memoises `asset_find_file_path()`, `get_asset_page_type_file_data()`, and the per-block
`get_block_asset_version()` for the lifetime of one request. This collapses the duplicate lookups the
asset and block systems make for the same files — notably the *2×-per-block* version computation that
runs once for each of the `block_type_metadata` and `block_type_metadata_settings` filters.

Because the memo lives in process memory and is discarded when the request ends, it can never serve
stale data across requests or deploys. **Leave it on.** It is the safe, recommended layer.

## O2 — Persistent cache

When enabled, caches the expensive cross-request work in the object cache (WordPress transients, which
use Redis/Memcached automatically where available):

- the theme template-directory scan and the page-type asset-existence scan, and
- the **block asset-version map** (every block's computed version in a single entry).

Every entry is namespaced by a **build signature** so a new build produces fresh keys and the old
entries are abandoned (and expire via TTL). One object-cache read per request then replaces the
filesystem storm.

> **Trade-off:** an object-cache `get` is a network round-trip (~0.1–0.5 ms). It only pays off when it
> replaces *more* filesystem work than that — i.e. on cold-cache, high-asset-count, or slow-disk
> requests. For a warm, OPcache-enabled host the win is modest. Measure before relying on it (see
> [Measuring impact in production](#measuring-impact-in-production)).

### The build signature & invalidation

`get_enqueues_build_signature()` fingerprints the **content hashes** baked into every compiled
`.asset.php` (the theme JS dir and each block). Because it is content-based, it:

- ✅ moves on any JS/asset change, **including a block-only deploy** that leaves the main bundle untouched, and
- ✅ is immune to the git-checkout "mtime is not bumped for unchanged files" pitfall.

**Known limitation:** a change that adds *no* compiled asset — a new template `.php`, or a CSS-only
change with no corresponding `.asset.php` — will not move the signature. On a host that deploys via
**git checkout** (e.g. WP Engine), `switch_theme` / `upgrader_process_complete` do **not** fire, so
their auto-flush hooks won't help either.

**Therefore, if you enable O2 in production, do one of:**

1. **Flush on deploy** — click *Flush cache now*, or call `\Enqueues\flush_enqueues_cache()` from your
   deploy step / a WP-CLI command. *(Simplest.)*
2. **Bind the signature to your deploy** — return your deploy hash / git SHA from the
   `enqueues_build_signature` filter so every deploy invalidates automatically:
   ```php
   add_filter( 'enqueues_build_signature', fn() => defined( 'WPE_DEPLOY_SHA' ) ? WPE_DEPLOY_SHA : 'local' );
   ```

The TTL is the automatic backstop for the residual window, which is why it is clamped to ≥ 1 hour.

## Constants, filters & functions

### Constants (override the settings page)

| Constant | Effect |
|:---------|:-------|
| `ENQUEUES_CACHE_ENABLED` | Force the persistent cache (O2) on/off; overrides the setting. |
| `ENQUEUES_REQUEST_MEMO_ENABLED` | Force the request memo (O1) on/off; overrides the setting. |
| `ENQUEUES_CACHE_TTL` | Force the TTL (seconds); overrides the setting. |

Precedence for every toggle: **constant → setting → filter** (the filter has the final say).

### Filters & actions

| Filter/Action | Summary |
|:--------------|:--------|
| `enqueues_is_cache_enabled` | Final say on whether the persistent cache (O2) is enabled (bool). |
| `enqueues_is_request_memo_enabled` | Final say on whether the request memo (O1) is enabled (bool). |
| `enqueues_cache_ttl` | Filter the persistent cache TTL in seconds (int). |
| `enqueues_build_signature` | Override the build signature — return a deploy hash / git SHA for guaranteed per-deploy invalidation (string). |
| `enqueues_cache_flushed` (action) | Fires after `flush_enqueues_cache()` completes. |

### Functions

| Function | Purpose |
|:---------|:--------|
| `is_request_memo_enabled(): bool` | Whether O1 is active. |
| `is_cache_enabled(): bool` | Whether O2 is active. |
| `get_cache_ttl(): int` | Effective TTL. |
| `get_enqueues_build_signature(): string` | Current build signature. |
| `enqueues_cache_key( string $key ): string` | Build a signature-namespaced transient key. |
| `flush_enqueues_cache(): void` | Invalidate all persistent entries (rotates the salt). |
| `enqueues_get_settings(): array` / `enqueues_setting( $key, $default )` | Read the settings (memoised). |

## Measuring impact in production

You do **not** need New Relic or a benchmark harness. The point of the toggle is that it makes a clean
A/B trivial on the live site. Remember two things first:

- **Anonymous traffic is full-page-cached** (e.g. WP Engine EverCache), so registration cost never
  touches it. The win only appears on **logged-in / editor / cache-miss** requests — so measure those.
- **Measure server time, not TTFB.** Bypass the page cache (be logged in, or add a unique query string
  per request) and take the **median of many samples** — a few ms of difference is easily lost in noise.

Practical methods, cheapest first:

1. **Query Monitor (zero extra tooling).** It's likely already installed. Log in, open a page, read
   **Page Generation Time**. Toggle O2 off → sample ~10 page loads → note the median; toggle O2 on →
   **Flush** → sample again. (QM adds its own overhead and only runs for admins — that's fine, it's a
   like-for-like A/B and admin requests are exactly the cache-miss path the change affects.)

2. **A `Server-Timing` header probe (one tiny mu-plugin).** Emit the real PHP generation time as a
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
   Run that with O2 off, then on (and flushed), and compare medians.

3. **WP-CLI over SSH (deterministic-ish).** With `wp-cli/profile-command` installed,
   `wp profile hook init` times the block-registration hook directly — run it with O2 off vs on.

If the medians don't separate cleanly after dozens of samples, that's a real result too: it means the
saving is below the noise floor on your hardware, and O1 alone is the right place to stop.
