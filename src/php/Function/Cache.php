<?php
/**
 * Cache utility functions for asset loading in the Enqueues MU Plugin.
 *
 * File Path: src/php/Function/Cache.php
 *
 * @package Enqueues
 */

namespace Enqueues;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Determines whether caching is enabled for asset loading.
 *
 * Caching helps to improve performance by avoiding repetitive filesystem operations such as checking file existence.
 * It is enabled based on the `ENQUEUES_CACHE_ENABLED` constant or through the 'enqueues_is_cache_enabled' filter.
 *
 * @return bool True if caching is enabled, false otherwise.
 */
function is_cache_enabled(): bool {

	$cache_enabled = defined( 'ENQUEUES_CACHE_ENABLED' ) ? ENQUEUES_CACHE_ENABLED : true;

	/**
	 * Filters whether caching is enabled in the Enqueues plugin.
	 *
	 * @param bool $is_cache_enabled True if caching is enabled, false otherwise.
	 */
	return (bool) apply_filters( 'enqueues_is_cache_enabled', $cache_enabled );
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
	/**
	 * Filters the cache TTL (time-to-live) value.
	 *
	 * @param int $cache_ttl The TTL in seconds. Defaults to 1 day (DAY_IN_SECONDS).
	 */
	return (int) apply_filters( 'enqueues_cache_ttl', defined( 'ENQUEUES_CACHE_TTL' ) ? ENQUEUES_CACHE_TTL : DAY_IN_SECONDS );
}

/**
 * Gets the build signature based on main asset file modification times.
 *
 * This signature changes whenever the main CSS/JS files are rebuilt, allowing
 * cache entries to automatically invalidate on new deployments.
 *
 * Uses the enqueues_theme_default_enqueue_asset_filename filter to determine
 * the main asset filename, ensuring the signature reflects the actual theme configuration.
 *
 * @return string Build signature hash.
 */
function get_enqueues_build_signature(): string {
	$cache_key = 'enqueues_build_signature';
	$signature = is_cache_enabled() ? get_transient( $cache_key ) : false;

	if ( false !== $signature ) {
		return $signature;
	}

	$directory = get_template_directory();

	// Use the filter to get the actual main filename (respects theme/child site configuration).
	/**
	 * Filter the default asset filename for the theme.
	 *
	 * @param string $filename The default asset filename.
	 */
	$main_filename = apply_filters( 'enqueues_theme_default_enqueue_asset_filename', 'main' );

	$main_css = "{$directory}/dist/css/{$main_filename}.min.css";
	$main_js  = "{$directory}/dist/js/{$main_filename}.min.js";

	$mtime_css = file_exists( $main_css ) ? filemtime( $main_css ) : 0;
	$mtime_js  = file_exists( $main_js ) ? filemtime( $main_js ) : 0;

	// Create signature from both file modification times and main filename.
	$signature = md5( "{$main_filename}:{$mtime_css}:{$mtime_js}" );

	// Cache the signature for 1 hour (it will change when files are rebuilt).
	if ( is_cache_enabled() ) {
		set_transient( $cache_key, $signature, HOUR_IN_SECONDS );
	}

	return $signature;
}

/**
 * Flushes all Enqueues-related transients and cache entries.
 *
 * This function deletes all transients and cache entries that start with the Enqueues prefix,
 * allowing for manual cache invalidation when needed (e.g., after deployments).
 *
 * @return int Number of cache entries deleted.
 */
function flush_enqueues_cache(): int {
	global $wpdb;

	$deleted = 0;

	// Delete transients that match Enqueues cache keys.
	$transient_prefixes = [
		'_transient_enqueues_',
		'_transient_timeout_enqueues_',
	];

	foreach ( $transient_prefixes as $prefix ) {
		$like_pattern  = $wpdb->esc_like( $prefix ) . '%';
		$query         = $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$like_pattern,
		);
		$deleted      += $wpdb->query( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	// Also clear object cache if available.
	if ( function_exists( 'wp_cache_flush_group' ) ) {
		wp_cache_flush_group( 'enqueues' );
	}

	/**
	 * Fires after Enqueues cache has been flushed.
	 *
	 * @param int $deleted Number of cache entries deleted.
	 */
	do_action( 'enqueues_cache_flushed', $deleted );

	return $deleted;
}
