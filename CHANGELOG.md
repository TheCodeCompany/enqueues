# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.0] - 2026-06-04

### Added
- **FEATURE**: Asset caching with admin toggles and a Settings → Enqueues page
  - **Request memo (O1)** — in-process memoisation of `asset_find_file_path()`, `get_asset_page_type_file_data()`, and per-block `get_block_asset_version()`, removing duplicate filesystem lookups within a request (including the 2×-per-block version computation across the `block_type_metadata` / `block_type_metadata_settings` filters). Active in the Per-request and Persistent modes (the framework defaults to Off); cannot serve stale data.
  - **Persistent cache (O2)** — caches the theme directory scans and the block asset-version map across requests in the object cache, keyed by the build signature. Off by default.
  - **Settings → Enqueues** admin page (`SettingsController`) with a single **Asset cache mode** selector — Off (recompute every request) / Per-request (in-process static memo) / Persistent (object cache, global across requests) — a TTL field (minimum 1 hour), a manual *Flush cache* button, and an effective-state status panel. `enqueues_cache_mode()` is the single source of truth behind `is_request_memo_enabled()` / `is_cache_enabled()`; the legacy `request_memo` / `persistent_cache` booleans still resolve for backward compatibility. **Caching defaults to Off** (opt-in) — a fresh install behaves exactly like the pre-cache framework until a mode is chosen.
  - **Build signature** (`get_enqueues_build_signature()`) — a content-hash fingerprint of every compiled `.asset.php` (theme JS dir + each block) that namespaces persistent entries, so a deploy invalidates them automatically, **including block-only deploys**; immune to the git-checkout "mtime not bumped" pitfall.
  - New functions: `flush_enqueues_cache()`, `enqueues_cache_key()`, `is_request_memo_enabled()`, `enqueues_get_settings()`, `enqueues_setting()`.
  - **Cache profiler** (default OFF, free when off) — a Settings → Enqueues "Cache profiler" panel that quantifies the cache value-add via the runtime counterfactual: each cached operation records its with-cache (hit) and without-cache (compute/miss) time, shown as per-operation hit rate, average with/without, and a SIGNED saving (a negative saving means the cache read costs more than recomputing on that backend — e.g. DB transients with no object cache), plus a ring buffer of the last 20 requests and a reset action. Toggle `profile`; constant `ENQUEUES_PROFILE_ENABLED`; filter `enqueues_is_profile_enabled`; functions `is_profile_enabled()`, `enqueues_profile_record()`, `enqueues_profile_persist()`, `enqueues_profile_reset()`. The profiler records **front-end page renders only** (cron / AJAX / admin / REST / CLI requests are skipped so background traffic does not pollute the data or add an options-table write), and it **clears its stored stats when switched off**.
  - **Access control** — the Settings → Enqueues page, its save handler, and both maintenance actions are gated on a single capability, **administrators only** by default (`manage_options`), filterable via `enqueues_settings_capability`.
  - **UX** — the Cache TTL shows a human-readable duration (e.g. `2592000s (30 days)`), the profiler's "Without cache" column shows its sample count (`n=`), and "Saved / hit" has an explanatory tooltip.
  - **Profiler log size** is configurable — 10 to 200 in steps of 10 (default 20) — via the `profile_log_max` setting, `enqueues_profile_log_max()`, and the `enqueues_profile_log_max` filter.
  - **Fixed** — "Saved / hit" and the total no longer read as a false negative for an operation with no miss baseline (`n=0`): such rows show `—` and are excluded from the total (an absent without-cache sample is unknown, not zero, so it is not counted as `0 − with`).
  - New filters: `enqueues_is_request_memo_enabled`, `enqueues_build_signature`, `enqueues_cache_mode`; new action `enqueues_cache_flushed`; new constant `ENQUEUES_CACHE_MODE` (the single config-as-code override).
  - The persistent cache key is computed lazily, so there is no build-signature cost when caching is off.
  - See [docs/PERFORMANCE.md](docs/PERFORMANCE.md), including guidance on measuring impact in production without New Relic.
  - **WP-CLI** (`wp enqueues`): `flush` (post-deploy cache invalidation for git/rsync deploys that don't fire the `switch_theme` / `upgrader_process_complete` auto-flush hooks), `signature` (print the build signature), `cache-mode [<mode>]` (get/set off|request|persistent), and `status` (effective state incl. object-cache presence). Registration is guarded by `WP_CLI`, so it adds zero front-end cost.
  - **Filter aliases & new hooks**: collision-safe, prefixed aliases `enqueues_string_slugify` / `enqueues_string_camelcaseify`, `enqueues_environment_type_matches_{env}` / `enqueues_environment_site_url_partial_matches_{env}`, and canonically-named `enqueues_theme_css_dist_dir` / `enqueues_theme_js_dist_dir`. New `enqueues_theme_css_args_{handle}` (CSS style-args, parity with the JS args filter) and `enqueues_block_editor_js_handle_{type}_{foldername}`.
  - **Build-time asset manifest** (opt-in) — `wp enqueues manifest build` snapshots the theme template scan into `{theme}/dist/enqueues-manifest.php`, so production requests read one opcache-cached file instead of walking the theme tree. It supersedes the `theme_template_files` scan/transient when present, is ignored in local dev and on a build-signature mismatch (stale → correct-but-slower, never stale output), and is fully opt-in (no file = prior behaviour). Filters `enqueues_manifest_enabled` / `enqueues_manifest_path` / `enqueues_manifest_validate_signature`; CLI `wp enqueues manifest build|clear|status`. See [docs/PERFORMANCE.md](docs/PERFORMANCE.md#build-time-manifest-deploy-generated-fastest).

### Changed
- **Config model** — the Settings → Enqueues **cache mode is the single source of truth**, resolved in one place (`enqueues_cache_mode()`). `is_cache_enabled()` and `is_request_memo_enabled()` now derive purely from that mode and no longer read any constant independently, so the settings page and the runtime can no longer disagree. When a constant pins the mode, **all three** radio options are locked (not just Persistent) and the page states which constant is in force and the effective mode.
- `ENQUEUES_CACHE_MODE` (`off` | `request` | `persistent`) is the single supported config-as-code override; when defined it overrides the saved option at read time.
- **Object-cache safety** — Persistent mode now only actually persists when an external object cache is present (`wp_using_ext_object_cache()`); without one (local dev, or a prod DB cloned to a host with no object cache) it transparently degrades to Per-request, since transients in the options table measured net-negative versus recomputing. The in-process memo still runs, the `enqueues_is_cache_enabled` filter can force the DB-transient path, and the Status panel reports whether an object cache is present and flags the downgrade.
- **Block editor (potentially breaking)**: the plugin/extension **non-view JS handle is now namespaced** (`{namespace}-{folder}-{filetype}` instead of the bare `{folder}-{filetype}`) to prevent cross-site/plugin collisions, matching the CSS and view-JS handles. A site relying on the old bare handle can restore it via the new `enqueues_block_editor_js_handle_{type}_{foldername}` filter.

### Deprecated
- **`ENQUEUES_CACHE_ENABLED`** (a boolean from before the mode selector existed) is deprecated in favour of `ENQUEUES_CACHE_MODE`. It is still honoured via a back-compat shim in `enqueues_cache_mode()` — `true` maps to Persistent, **`false` maps to Off** (preserving the "disable caching" intent) — and will be removed in the next major. The Settings → Enqueues page shows a deprecation notice while it is defined.
- Unprefixed hooks `string_slugify`, `string_camelcaseify`, `environment_type_matches_{env}`, `environment_site_url_partial_matches_{env}` (global-namespace collision risk) in favour of their `enqueues_`-prefixed aliases; the bare names still fire (last) for back-compat and will be removed in the next major.
- `enqueues_theme_css_src_dir` / `enqueues_theme_js_src_dir` in favour of the correctly-named `enqueues_theme_css_dist_dir` / `enqueues_theme_js_dist_dir` (they set the compiled/dist directory, not source); the legacy names still feed the default.

### Fixed
- Corrected the `asset_find_file_path()` and `get_asset_page_type_file_data()` docblocks, which described request caching the functions did not previously perform.
- **PERF**: `get_theme_template_files()` now prunes skip directories (`build-tools`, `dist`, `node_modules`, `vendor`) at the directory level via `RecursiveCallbackFilterIterator`, instead of recursing into them and skipping per file. On a dev checkout with a nested `node_modules` this cut the (uncached) theme-template scan from ~467ms to ~4.9ms with byte-identical output. This is the dominant per-request cost the O2 cache was masking; pruning helps every uncached request (dev, and the cold/post-deploy request in production) with no staleness trade-off.
- Persistent cache (O2) now serves empty results from cache. `get_theme_template_files()` and `get_enqueue_asset_files()` gated the cache hit on a truthy check, so a legitimately empty array (e.g. a known-files set with no compiled assets in `dist/`) was treated as a miss and re-scanned the filesystem — and re-wrote the transient — on every request. They now use `is_array()` to distinguish a miss from a cached empty array, matching `load_persistent_version_map()`.
- `is_local()` host-override guard checked its own namespaced name (`Enqueues\is_local`) — always true — then called a global `is_local()` the plugin never defines: a fatal on any site without one, and the `is_environment_match( 'local' )` fallback was unreachable. It now checks the global name like the other environment helpers.
- `is_environment_match()` now forwards `$env` when delegating to a site-defined global override; previously it called the global with no arguments (`ArgumentCountError` on PHP 8).
- `get_asset_page_type_file_data()` now resolves `.asset.php` files under the filtered dist directory and directory part (matching where the compiled JS was found), instead of a hardcoded `dist/js` path that ignored the `enqueues_asset_theme_dist_directory` filter.
- **Hardening**: `glob()` results are coalesced to `[]` before `array_filter()` / `foreach` (block registration, plugin/extension assets, the function autoloader), so a `glob()` failure (unreadable dir / open_basedir) can no longer throw an uncatchable `TypeError` and white-screen the site on `init`.
- **Hardening**: compiled `.asset.php` artifacts are read through a new `enqueues_read_asset_php()` that `try/catch (\Throwable)`es the `include` — a truncated/corrupt artifact mid-deploy degrades gracefully (falls back to `filemtime` / empty deps) instead of fataling every request.
- `get_cache_ttl()` now floors the TTL to 1 hour on the constant/filter paths too (not just the settings sanitiser), so a `0`/negative value can no longer create never-expiring transients that defeat the salt-rotation flush.
- **PERF**: `get_enqueue_asset_files()` pairs each dist dir with only its own extensions (minified first) and stops at the first hit, instead of testing all four extensions in both dirs — roughly halving the `file_exists()` calls per uncached request (the cross-dir combinations it dropped are structurally impossible in a real build).
- **Privacy**: the cache profiler stores only the request **path** (query string stripped, length-capped), so visitor PII in query parameters is no longer written to the `enqueues_profile_data` option.
- Documentation accuracy: corrected the stale "invalidated every 24 hours" caching docblocks (invalidation is by build signature + configurable TTL) and added `cache_mode` to the settings `@return` shape. Internal dedup: the three `single-*` asset resolvers share a `first_matching_asset()` helper, and `get_asset_page_type_file_data()`'s two branches share one asset-data builder (behaviour-identical).
- **Base MVC**: `Application::setup_controllers()` now writes filtered controllers back to the controller list, so the `base_pre_*` / `base_post_*` controller filters actually work as swap points. Previously the `base_post_*` return values were discarded (dead assignments) and a `base_pre_controller_set_config` swap was applied inconsistently (used for config but not set-up). The deliberate two-phase order (all configs, then all set-ups) is preserved.
- **Webpack helpers**: `console.log` diagnostics are now opt-in via `ENQUEUES_DEBUG` (silent by default, so CI logs stay clean and absolute paths aren't leaked), and `enqueuesWebpackEntries()` warns loudly on an entry-basename collision (two source files sharing a basename in different dirs) instead of silently dropping one asset from the build.

## [1.3.7] - 2026-05-19

### Added
- **ENHANCEMENT**: Frontend jQuery controls in `ThemeEnqueueJqueryController`
  - Introduced filter-driven behaviour so jQuery can be fully disabled, kept in the header, moved to the footer, or given a `defer` / `async` loading strategy without changing core enqueue logic
  - `enqueues_disable_jquery` — dequeue and deregister `jquery`, `jquery-core`, and `jquery-migrate` when true (default false)
  - `enqueues_load_jquery_in_footer` — control footer grouping via script `group` data (default true); loading strategy filters still apply when footer placement is disabled
  - `enqueues_jquery_loading_strategy` — optional `defer` or `async` strategy for the jQuery handles (default empty, no override)

## [1.3.6] - 2026-05-17

### Added
- **ENHANCEMENT**: Plugin block editor styles in Webpack block editor entries
  - Merges `getBlockEditorEntries('plugins', 'css-editor', 'scss')` with other block editor asset entries
  - Enables plugin `css-editor` SCSS to be built and loaded on the admin block editor side

## [1.3.5] - 2026-02-25

### Changed
- **ENHANCEMENT**: Extended early block style pre-enqueue coverage for CLS prevention
  - Renamed pre-enqueue logic to handle both static and dynamic blocks found in page content
  - Early enqueue now covers frontend block style and view style handles for all matched blocks
- **ENHANCEMENT**: Added generic block child directory copy context for Webpack copy patterns
  - Added `block-child-dirs` context to `enqueuesGetCopyPluginConfigPattern`
  - Supports custom source block directory, destination block directory, and multiple child directory names
- **ENHANCEMENT**: Added explicit control for head pre-enqueue behaviour
  - Added `enqueues_block_editor_preenqueue_block_styles` filter to control forced head pre-enqueue
  - Pre-enqueue defaults to enabled for static and dynamic blocks, and can be disabled per site to fall back to WordPress behaviour
- **ENHANCEMENT**: Extended post-type remap support to post-name asset matching
  - `enqueues_theme_post_type_asset_remap` now applies to `single-{post-type}-{post-name}` lookups
  - Default lookup order now checks original post type first, then remapped post type
  - Added `enqueues_theme_post_type_asset_candidates` filter to override candidate order per project

### Documentation
- Updated `BLOCK-EDITOR.md` with:
  - Core block style-loading defaults used by Enqueues
  - New `enqueues_block_editor_preenqueue_block_styles` filter details
- Updated `WEBPACK.md` with `block-child-dirs` copy pattern usage and examples
- Updated `FILTERS.md` to include `enqueues_block_editor_preenqueue_block_styles`
- Updated `THEME-ASSETS.md` and `FILTERS.md` to document remap behaviour for post-name matching and candidate ordering

## [1.3.4] - 2026-02-23

### Added
- **ENHANCEMENT**: Expanded single theme asset matching for more specific content contexts
  - Added post name matching support using `single-{post_type}-{post_name}` for single posts
  - Added hierarchical child post matching support using `single-{post_type}-child`
  - Introduced strict matching order for single contexts: template, post name, child post, post type, page type, then main fallback
  - Preserved fallback behaviour so `main` remains the default when no specific asset is found
- **ENHANCEMENT**: Added post type remapping filter for asset matching
  - Added `enqueues_theme_post_type_asset_remap` filter to remap CPT lookups for child and post type matching
  - Remapped post types are checked first, then the original post type
  - Supports shared asset bundles across related post types (for example, `camera` or `lens` remapped to `product`)

### Documentation
- Updated `THEME-ASSETS.md` with the new matching order and post type remapping behaviour
- Added `enqueues_theme_post_type_asset_remap` to `FILTERS.md` with usage context

## [1.3.3] - 2025-01-27

### Fixed
- **BUGFIX**: Fixed CSS dependencies default value in theme asset loading
  - Changed default value from string `'all'` to empty array `[]` for `enqueues_theme_css_dependencies_{handle}` filter
  - Ensures compatibility with WordPress `wp_register_style()` and `wp_enqueue_style()` functions
  - Prevents potential PHP warnings and unexpected behavior from invalid dependency format
  - Maintains backward compatibility while following WordPress coding standards

## [1.3.2] - 2025-09-23

### Added
- **ENHANCEMENT**: Post type slugification support for theme asset loading
  - Added support for post types with underscores (e.g., `film_simulation`) by checking both original and slugified formats
  - Enhanced `get_enqueues_theme_allowed_page_types_and_templates()` to include slugified post type versions
  - Improved compatibility between registered post type slugs and file naming conventions
  - Theme assets now load correctly for custom post types with non-standard slug formats

### Changed
- Updated post type detection logic to check both `single-{post_type}` and `single-{slugified_post_type}` formats
- Enhanced asset loading flexibility for custom post types with underscores or special characters

## [1.3.1] - 2025-09-03

### Fixed
- **BUGFIX**: Fixed incorrect file pattern routing in copy plugin configuration
  - Removed unnecessary `transform` function from `getCopyPluginConfigBlockJsonPattern`
  - Fixed issue where render-php files were incorrectly using block-json pattern with transforms
  - Eliminated dead code and improved copy operation performance
  - Render-php files now copy correctly without unwanted content transformations

### Changed
- Cleaned up `enqueues-copy-plugin-config-pattern.js` by removing unused transform logic
- Simplified copy operations to focus on file movement rather than content modification

## [1.3.0] - 2024-12-20

### Added
- **NEW FEATURE**: Block script localization support
  - Added `localize_block_scripts()` method to add localized parameters to registered block scripts
  - Added `enqueues_block_editor_js_localized_data_blocks_{block_slug}` filter for block localization
  - Added `enqueues_block_editor_js_localized_data_var_name_blocks_{block_slug}` filter for customizing variable names
  - Blocks can now have localized data even without registering their own scripts
  - Uses exact handles that WordPress Core registered from `block.json`
  - Supports both frontend and editor contexts
- Enhanced block handle tracking with improved data structure
  - Updated `$blocks` property to use `style_handles`, `view_style_handles`, etc. arrays
  - Better alignment with WordPress Core's handle structure
  - Improved type safety and documentation

### Changed
- **BREAKING CHANGE**: Refactored block editor asset management
  - Renamed `enqueue_assets()` to `enqueue_plugin_and_extension_assets()` for clarity
  - Method now only handles plugins and extensions (blocks managed by Core)
  - Removed block-specific registration logic to prevent conflicts with Core
  - Updated method documentation to clarify scope and purpose
- **BREAKING CHANGE**: Updated filter structure for better asset type separation
  - Block filters now use `{block_slug}` instead of `{foldername}` for clarity
  - Plugin/extension filters maintain `{type}_{foldername}` pattern
  - Clear separation between Core-managed blocks and Enqueues-managed plugins/extensions
- Improved code organization and readability
  - Updated variable names for better clarity (`$bt` → `$block_type`)
  - Enhanced method documentation with detailed explanations
  - Added comprehensive inline comments explaining complex logic

### Fixed
- **CRITICAL**: Fixed typo in method name (`enqueue_plugin_and_extenmsion_assets` → `enqueue_plugin_and_extension_assets`)
- **CRITICAL**: Ensured proper hook timing for block localization (priority 20)
- **CRITICAL**: Fixed potential issues with block handle extraction and tracking

### Documentation
- **MAJOR UPDATE**: Comprehensive documentation overhaul
  - Added "Order of Operations" section explaining WordPress Core vs Enqueues timing
  - Added "Block Localization Filters" section with examples
  - Updated filter documentation to clarify asset type separation
  - Added detailed explanations of why specific hook priorities are used
  - Removed outdated shim handle documentation
  - Updated FILTERS.md with asset type annotations for all block editor filters
  - Added explanatory notes about filter scope and usage

## [1.2.0] - 2024-12-20

### Added
- **CRITICAL FIX**: CLS (Cumulative Layout Shift) prevention for dynamic blocks
  - Dynamic blocks (with `render.php` or `"render"` in `block.json`) now have their styles pre-enqueued early to prevent CLS
  - Added `preenqueue_dynamic_block_styles()` method to detect and enqueue dynamic block styles in `<head>`
  - Added Core Web Vitals optimization filters (`should_load_separate_core_block_assets`, `wp_should_inline_block_styles`)
  - Added `extract_block_style_handles()` method to determine exact style handles Core will use
  - Added support for custom style handles defined in `block.json`

### Changed
- **BREAKING CHANGE**: Block CSS registration now handled by Core
  - Removed duplicate registration of `blocks/*/(style|view).css` files
  - Core now owns block asset registration via `register_block_type_from_metadata()`
  - Dynamic block styles are pre-enqueued separately to prevent CLS
  - Plugin and extension asset registration remains unchanged
- Updated `register_blocks()` to detect dynamic blocks and track their style handles
- Enhanced documentation with comprehensive CLS fix explanation

### Fixed
- **CRITICAL**: Eliminated CLS caused by dynamic block styles loading late in footer
- **CRITICAL**: Improved Core Web Vitals scores by ensuring block styles load in `<head>`
- **CRITICAL**: Fixed poor user experience from visible content shifts during page load

## [1.1.0] - 2024-12-19

### Added
- Added 'minified' flag to `get_asset_page_type_file_data` to indicate if the loaded asset is minified.

### Changed
- **BREAKING CHANGE**: Refactored `BlockEditorRegistrationController::enqueue_assets()` method signature
  - Replaced confusing `$register_only` parameter with separate `$enqueue_style` and `$enqueue_script` parameters
  - Eliminated double negative logic (`!$register_only`) for better readability
  - Updated all method calls to use new parameter structure
  - Frontend assets now use `(false, false)` - register only, don't enqueue
  - Editor assets now use `(true, true)` - register and enqueue both styles and scripts
- **BREAKING CHANGE**: Added `$context` parameter to all block editor filters
  - All filters now receive `$context` as the second parameter for context-aware customization
  - Updated all filter examples in documentation to include the new parameter
- `get_asset_page_type_file_data` now returns an `asset_php` field for JS assets, containing dependency and version information from the corresponding .asset.php file.
- `ThemeEnqueueMainController` now uses the `asset_php` field for JS dependency and version data, removing duplicated logic and centralizing asset metadata handling.

### Removed
- Removed unused `$block_editor_namespace`