<?php
/**
 * Cache utility functions for asset loading in the Enqueues MU Plugin.
 *
 * File Path: src/php/Function/Cache.php
 *
 * @package Enqueues
 */

namespace Enqueues;

/**
 * Returns the Enqueues settings array (from the Settings -> Enqueues page), memoised per request.
 *
 * @return array{request_memo: bool, persistent_cache: bool, cache_ttl: int}
 */
function enqueues_get_settings(): array {

	static $settings = null;

	if ( null !== $settings ) {
		return $settings;
	}

	// Caching is OFF out of the box (opt-in). request_memo=false + persistent_cache=false makes
	// enqueues_cache_mode() resolve to 'off' until a mode is chosen on Settings -> Enqueues, so a fresh
	// install behaves exactly like the pre-cache framework. (A site that explicitly saved the legacy
	// booleans still resolves to the matching mode for backward compatibility.)
	$defaults = [
		'request_memo'     => false,
		'persistent_cache' => false,
		'cache_ttl'        => DAY_IN_SECONDS,
		'profile'          => false,
	];

	$stored   = get_option( 'enqueues_settings', [] );
	$settings = is_array( $stored ) ? array_merge( $defaults, $stored ) : $defaults;

	return $settings;
}

/**
 * Returns a single Enqueues setting value.
 *
 * @param string $key           Setting key.
 * @param mixed  $default_value Default if the key is absent.
 *
 * @return mixed
 */
function enqueues_setting( string $key, $default_value = null ) {
	$settings = enqueues_get_settings();

	return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default_value;
}

/**
 * Returns the active asset cache mode: 'off', 'request' (in-process static memo), or 'persistent'
 * (also cached across requests in the object cache).
 *
 * This is the single source of truth behind is_request_memo_enabled() and is_cache_enabled().
 * Resolution order, memoised once per request:
 *  1. The explicit `cache_mode` setting (Settings -> Enqueues), if set.
 *  2. Backward-compat: derive from the legacy `persistent_cache` / `request_memo` booleans.
 *  3. The ENQUEUES_CACHE_MODE constant overrides the above.
 *  4. The 'enqueues_cache_mode' filter has the final say.
 *
 * @return string One of 'off', 'request', 'persistent'.
 */
function enqueues_cache_mode(): string {

	static $mode = null;

	if ( null !== $mode ) {
		return $mode;
	}

	$valid    = [ 'off', 'request', 'persistent' ];
	$settings = enqueues_get_settings();

	if ( isset( $settings['cache_mode'] ) && in_array( $settings['cache_mode'], $valid, true ) ) {
		$default = (string) $settings['cache_mode'];
	} elseif ( ! empty( $settings['persistent_cache'] ) ) {
		$default = 'persistent';
	} elseif ( ! empty( $settings['request_memo'] ) ) {
		$default = 'request';
	} else {
		$default = 'off';
	}

	if ( defined( 'ENQUEUES_CACHE_MODE' ) && in_array( (string) ENQUEUES_CACHE_MODE, $valid, true ) ) {
		$default = (string) ENQUEUES_CACHE_MODE;
	}

	/**
	 * Filters the Enqueues asset cache mode.
	 *
	 * @param string $mode One of 'off', 'request', 'persistent'.
	 */
	$mode = (string) apply_filters( 'enqueues_cache_mode', $default );

	if ( ! in_array( $mode, $valid, true ) ) {
		$mode = 'request';
	}

	return $mode;
}

/**
 * Determines whether caching is enabled for asset loading.
 *
 * Caching helps to improve performance by avoiding repetitive filesystem operations such as checking file existence.
 * It is enabled based on the Settings -> Enqueues page (`persistent_cache`), the
 * `ENQUEUES_CACHE_ENABLED` constant (which overrides the setting), and the
 * 'enqueues_is_cache_enabled' filter (which has the final say).
 *
 * @return bool True if caching is enabled, false otherwise.
 */
function is_cache_enabled(): bool {

	static $enabled = null;

	if ( null !== $enabled ) {
		return $enabled;
	}

	// Precedence: an explicit ENQUEUES_CACHE_ENABLED constant overrides the admin setting; the
	// 'enqueues_is_cache_enabled' filter always has the final say.
	$default = defined( 'ENQUEUES_CACHE_ENABLED' ) ? (bool) ENQUEUES_CACHE_ENABLED : ( 'persistent' === enqueues_cache_mode() );

	/**
	 * Filters whether caching is enabled in the Enqueues plugin.
	 *
	 * @param bool $is_cache_enabled True if caching is enabled, false otherwise.
	 */
	$enabled = (bool) apply_filters( 'enqueues_is_cache_enabled', $default );

	return $enabled;
}

/**
 * Determines whether request-level memoisation (O1) is enabled.
 *
 * This memo is in-process only (it dies with the request), so it cannot serve stale data. It is enabled
 * whenever the cache mode is 'request' or 'persistent' (the default mode is 'off', so it is OFF until a
 * mode is chosen). Overridable by the ENQUEUES_REQUEST_MEMO_ENABLED constant and the
 * 'enqueues_is_request_memo_enabled' filter.
 *
 * @return bool True if request memoisation is enabled.
 */
function is_request_memo_enabled(): bool {

	static $enabled = null;

	if ( null !== $enabled ) {
		return $enabled;
	}

	$default = defined( 'ENQUEUES_REQUEST_MEMO_ENABLED' ) ? (bool) ENQUEUES_REQUEST_MEMO_ENABLED : ( 'off' !== enqueues_cache_mode() );

	/**
	 * Filters whether request-level memoisation is enabled.
	 *
	 * @param bool $enabled True if the request memo is enabled.
	 */
	$enabled = (bool) apply_filters( 'enqueues_is_request_memo_enabled', $default );

	return $enabled;
}

/**
 * Retrieves the time-to-live (TTL) value for cache entries.
 *
 * This value determines how long cache entries should be stored before they are invalidated.
 * The TTL is set using the `ENQUEUES_CACHE_TTL` constant or can be customized via the 'enqueues_cache_ttl' filter.
 *
 * @return int The TTL in seconds. Defaults to 1 day (DAY_IN_SECONDS).
 */
function get_cache_ttl(): int {

	$default = defined( 'ENQUEUES_CACHE_TTL' ) ? (int) ENQUEUES_CACHE_TTL : (int) enqueues_setting( 'cache_ttl', DAY_IN_SECONDS );

	/**
	 * Filters the cache TTL (time-to-live) value.
	 *
	 * @param int $cache_ttl The TTL in seconds. Defaults to 1 day (DAY_IN_SECONDS).
	 */
	return (int) apply_filters( 'enqueues_cache_ttl', $default );
}

/**
 * Returns a short signature that changes whenever the theme's compiled assets change.
 *
 * Every persistent cache entry is namespaced with this signature, so a deploy/rebuild that changes
 * any compiled asset invalidates the affected Enqueues caches without enumerating stored entries.
 * Computed once per request and memoised.
 *
 * The signature is a fingerprint of the content hashes in every compiled `.asset.php` (the theme JS
 * dir plus each block-editor block), so it moves on any JS/asset change -- including a block-only
 * deploy that leaves the main bundle untouched. Known limitation: a change that adds NO compiled
 * asset (a new template .php, or a CSS-only change with no corresponding .asset.php) will not move
 * the signature. For guaranteed invalidation on every deploy, flush via Settings -> Enqueues (or
 * call flush_enqueues_cache()) at deploy time, or override this with a deploy hash / git SHA via the
 * `enqueues_build_signature` filter.
 *
 * @return string A short hash representing the current build.
 */
function get_enqueues_build_signature(): string {

	static $signature = null;

	if ( null !== $signature ) {
		return $signature;
	}

	$directory = get_template_directory();
	$parts     = [];

	// Build a fingerprint from the CONTENT hashes baked into every compiled .asset.php across the
	// theme JS dir AND the block-editor blocks tree. Each entry's 'version' is a content hash that
	// webpack rewrites whenever that entry's source changes, so this signature moves on ANY asset
	// change -- including a block-only deploy that leaves the main bundle byte-identical (the case a
	// main-only signature missed and served a stale block ?ver). Content hashes are also immune to
	// the git-checkout "mtime not bumped for unchanged files" problem.
	$js_dir         = trim( (string) apply_filters( 'enqueues_theme_js_src_dir', 'dist/js' ), '/' );
	$block_dist_dir = trim( get_block_editor_dist_dir(), '/' );

	$globs = [
		"{$directory}/{$js_dir}/*.asset.php",
		"{$directory}/{$block_dist_dir}/blocks/*/*.asset.php",
	];

	foreach ( $globs as $pattern ) {
		$files = glob( $pattern );
		if ( ! is_array( $files ) ) {
			continue;
		}
		sort( $files );
		foreach ( $files as $file ) {
			$asset    = include $file;
			$fragment = ( is_array( $asset ) && ! empty( $asset['version'] ) ) ? (string) $asset['version'] : (string) filemtime( $file );
			$parts[]  = basename( dirname( $file ) ) . '/' . basename( $file ) . ':' . $fragment;
		}
	}

	// Fall back to the theme version when no build assets are present (e.g. a fresh checkout before
	// the first build). Note: a template .php added without any new compiled asset will not move the
	// signature; flush manually or set the enqueues_build_signature filter to a deploy hash for that.
	$source = empty( $parts ) ? 'v:' . (string) wp_get_theme()->get( 'Version' ) : implode( '|', $parts );

	// Fold in the manual flush salt (see flush_enqueues_cache()).
	$source .= '|' . (string) get_option( 'enqueues_cache_salt', '' );

	/**
	 * Filters the Enqueues build signature used to namespace persistent caches.
	 *
	 * Override with a deploy hash / git SHA on sites where the main asset mtime does not
	 * reliably change on every deploy.
	 *
	 * @param string $signature The default signature (md5 of the build source).
	 */
	$signature = (string) apply_filters( 'enqueues_build_signature', md5( $source ) );

	return $signature;
}

/**
 * Builds a namespaced, length-safe cache key for an Enqueues persistent cache entry.
 *
 * The build signature is folded in so that every deploy/rebuild produces fresh keys, giving
 * automatic cache invalidation. The result is md5-hashed to stay within the transient key length
 * limit regardless of the identifier passed in.
 *
 * @param string $key A stable identifier for the cached value.
 *
 * @return string A transient-safe cache key.
 */
function enqueues_cache_key( string $key ): string {
	return 'enq_' . md5( get_enqueues_build_signature() . '|' . $key );
}

/**
 * Flushes Enqueues persistent caches by rotating the cache salt.
 *
 * Because cache keys are namespaced by the build signature (which includes this salt), rotating
 * the salt orphans all existing entries immediately; they expire naturally via their TTL. This
 * avoids having to enumerate transients, which is not portable across object cache backends.
 *
 * @return void
 */
function flush_enqueues_cache(): void {

	update_option( 'enqueues_cache_salt', (string) time(), true );

	/**
	 * Fires after the Enqueues caches have been flushed.
	 */
	do_action( 'enqueues_cache_flushed' );
}

/**
 * Determines whether the cache profiler (observability layer) is enabled.
 *
 * Default OFF and zero-cost when off (a single static-memoised bool). When on, the cached functions
 * record per-request hit/miss timings that are folded into cross-request stats at shutdown and shown
 * on Settings -> Enqueues. The profiler quantifies the cache's value via the runtime counterfactual:
 * a HIT measures the with-cache cost (the cache read), a MISS measures the without-cache cost (the
 * filesystem compute the previous system paid every request). Overridable by the
 * ENQUEUES_PROFILE_ENABLED constant and the 'enqueues_is_profile_enabled' filter.
 *
 * @return bool True if profiling is enabled.
 */
function is_profile_enabled(): bool {

	static $enabled = null;

	if ( null !== $enabled ) {
		return $enabled;
	}

	$default = defined( 'ENQUEUES_PROFILE_ENABLED' ) ? (bool) ENQUEUES_PROFILE_ENABLED : (bool) enqueues_setting( 'profile', false );

	/**
	 * Filters whether the Enqueues cache profiler is enabled.
	 *
	 * @param bool $enabled True if the profiler is enabled.
	 */
	$enabled = (bool) apply_filters( 'enqueues_is_profile_enabled', $default );

	return $enabled;
}

/**
 * Records one cache-layer timing sample into the per-request profiler accumulator.
 *
 * No-op-cheap when profiling is off (callers guard with is_profile_enabled() so this is not even
 * called). Buckets correspond to the cached operations, e.g. 'theme_template_files'.
 *
 * @param string $bucket The cached operation the sample belongs to.
 * @param string $kind   'hit' (served from cache) or 'miss' (computed = the without-cache cost).
 * @param int    $ns     Elapsed nanoseconds (a hrtime(true) delta).
 *
 * @return void
 */
function enqueues_profile_record( string $bucket, string $kind, int $ns ): void {

	if ( ! isset( $GLOBALS['enqueues_profile'] ) ) {
		$GLOBALS['enqueues_profile'] = [];
	}

	if ( ! isset( $GLOBALS['enqueues_profile'][ $bucket ] ) ) {
		$GLOBALS['enqueues_profile'][ $bucket ] = [
			'hit_n'   => 0,
			'hit_ns'  => 0,
			'miss_n'  => 0,
			'miss_ns' => 0,
		];
	}

	$ref = &$GLOBALS['enqueues_profile'][ $bucket ];

	if ( 'hit' === $kind ) {
		++$ref['hit_n'];
		$ref['hit_ns'] += $ns;
	} else {
		++$ref['miss_n'];
		$ref['miss_ns'] += $ns;
	}
}

/**
 * Returns the per-request profiler accumulator (empty if nothing was recorded this request).
 *
 * @return array<string, array{hit_n:int, hit_ns:int, miss_n:int, miss_ns:int}>
 */
function enqueues_profile_request(): array {
	return isset( $GLOBALS['enqueues_profile'] ) && is_array( $GLOBALS['enqueues_profile'] ) ? $GLOBALS['enqueues_profile'] : [];
}

/**
 * Folds the current request's profiler accumulator into the persisted stats + ring buffer.
 *
 * Hooked on 'shutdown' only when profiling is on. Writes a single autoload=off option once per
 * profiled request (never on the hot path), keeping the most recent ENQUEUES_PROFILE_LOG_MAX (20)
 * per-request records plus cumulative per-bucket totals.
 *
 * @return void
 */
function enqueues_profile_persist(): void {

	$request = enqueues_profile_request();

	if ( empty( $request ) ) {
		return;
	}

	$data  = get_option( 'enqueues_profile_data', [] );
	$data  = is_array( $data ) ? $data : [];
	$stats = isset( $data['stats'] ) && is_array( $data['stats'] ) ? $data['stats'] : [];
	$log   = isset( $data['log'] ) && is_array( $data['log'] ) ? $data['log'] : [];

	$req_hit_ns  = 0;
	$req_miss_ns = 0;

	foreach ( $request as $bucket => $sample ) {
		if ( ! isset( $stats[ $bucket ] ) ) {
			$stats[ $bucket ] = [
				'hit_n'   => 0,
				'hit_ns'  => 0,
				'miss_n'  => 0,
				'miss_ns' => 0,
			];
		}
		$stats[ $bucket ]['hit_n']   += $sample['hit_n'];
		$stats[ $bucket ]['hit_ns']  += $sample['hit_ns'];
		$stats[ $bucket ]['miss_n']  += $sample['miss_n'];
		$stats[ $bucket ]['miss_ns'] += $sample['miss_ns'];
		$req_hit_ns                  += $sample['hit_ns'];
		$req_miss_ns                 += $sample['miss_ns'];
	}

	$log[] = [
		't'       => time(),
		'url'     => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', // phpcs:ignore
		'hit_ns'  => $req_hit_ns,
		'miss_ns' => $req_miss_ns,
		'buckets' => $request,
	];

	if ( count( $log ) > 20 ) {
		$log = array_slice( $log, -20 );
	}

	update_option( 'enqueues_profile_data', [ 'stats' => $stats, 'log' => $log ], false );
}

/**
 * Clears the persisted profiler stats and ring buffer.
 *
 * @return void
 */
function enqueues_profile_reset(): void {
	delete_option( 'enqueues_profile_data' );
}
