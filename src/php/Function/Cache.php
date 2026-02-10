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
 * Check if Enqueues debugging is enabled.
 *
 * @return bool True if debugging is enabled.
 */
function is_debug_enabled(): bool {
	if ( ! defined( 'ENQUEUES_DEBUG' ) ) {
		return false;
	}
	return (bool) ENQUEUES_DEBUG;
}

/**
 * Get or generate a unique request ID for grouping log entries.
 *
 * @return string Request ID.
 */
function get_request_id(): string {
	static $request_id = null;

	if ( null === $request_id ) {
		// Generate a short unique ID for this request (8 characters).
		$request_id = substr( md5( uniqid( (string) microtime( true ), true ) ), 0, 8 );
	}

	return $request_id;
}

/**
 * Get current page context for debugging.
 *
 * @return array Page context information.
 */
function get_page_context(): array {
	$context = [
		'request_id' => get_request_id(),
	];

	// Add URL if available (avoid calling too early in WordPress load).
	if ( function_exists( 'home_url' ) && isset( $_SERVER['REQUEST_URI'] ) ) {
		$context['url'] = home_url( $_SERVER['REQUEST_URI'] );
	} elseif ( isset( $_SERVER['REQUEST_URI'] ) ) {
		$context['uri'] = $_SERVER['REQUEST_URI'];
	}

	// Add page type if available.
	if ( function_exists( 'get_page_type' ) ) {
		$context['page_type'] = get_page_type();
	}

	return $context;
}

/**
 * Log debug message for Enqueues system.
 *
 * Includes request ID and page context to help group log entries by request.
 *
 * @param string $message Debug message.
 * @param array  $context Optional context data to include.
 * @return void
 */
function debug_log( string $message, array $context = [] ): void {
	if ( ! is_debug_enabled() ) {
		return;
	}

	// Get page context (request ID, URL, etc.).
	$page_context = get_page_context();

	// Merge page context with provided context.
	$full_context = array_merge( $page_context, $context );

	$log_message = '[ENQUEUES] [' . $page_context['request_id'] . '] ' . $message;
	if ( ! empty( $full_context ) ) {
		// Remove request_id from context since it's already in the message prefix.
		unset( $full_context['request_id'] );
		if ( ! empty( $full_context ) ) {
			$log_message .= ' | Context: ' . wp_json_encode( $full_context, JSON_PRETTY_PRINT );
		}
	}

	// Use error_log with message type 0 (system logger) to ensure it goes to the configured error log.
	error_log( $log_message, 0 );
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
	static $result = null;
	static $logged = false;

	// Memoize result per request to avoid repeated filter calls.
	if ( null !== $result ) {
		return $result;
	}

	// If the constant is set, respect it and skip further checks.
	if ( defined( 'ENQUEUES_CACHE_ENABLED' ) ) {
		$result = (bool) ENQUEUES_CACHE_ENABLED;

		if ( ! $logged && is_debug_enabled() ) {
			error_log( '[ENQUEUES] Debug logging is ENABLED - Enqueues system is active' );
			debug_log( 'is_cache_enabled() - Initialized', [ 'enabled' => $result, 'constant' => ENQUEUES_CACHE_ENABLED ] );
			$logged = true;
		}

		return $result;
	}

	// If the site is local, disable caching by default.
	$cache_enabled = is_local() ? false : true;

	/**
	 * Filters whether caching is enabled in the Enqueues plugin.
	 *
	 * @param bool $is_cache_enabled True if caching is enabled, false otherwise.
	 */
	$result = (bool) apply_filters( 'enqueues_is_cache_enabled', $cache_enabled );

	// Log once per request to verify debug logging is working.
	if ( ! $logged && is_debug_enabled() ) {
		error_log( '[ENQUEUES] Debug logging is ENABLED - Enqueues system is active' );
		debug_log( 'is_cache_enabled() - Initialized', [ 'enabled' => $result, 'constant' => 'not defined' ] );
		$logged = true;
	}

	return $result;
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
	$ttl = (int) apply_filters( 'enqueues_cache_ttl', defined( 'ENQUEUES_CACHE_TTL' ) ? ENQUEUES_CACHE_TTL : DAY_IN_SECONDS );

	debug_log( 'get_cache_ttl()', [ 'ttl_seconds' => $ttl, 'ttl_hours' => round( $ttl / 3600, 2 ) ] );

	return $ttl;
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
	static $signature = null;
	static $logged = false;

	// Memoize per request to avoid repeated transient lookups.
	if ( null !== $signature ) {
		return $signature;
	}

	$cache_key = 'enqueues_build_signature';
	$cached    = is_cache_enabled() ? get_transient( $cache_key ) : false;

	if ( false !== $cached && is_array( $cached ) ) {
		$signature = $cached['signature'] ?? '';
		$css_mtime = (int) ( $cached['css_mtime'] ?? 0 );
		$js_mtime  = (int) ( $cached['js_mtime'] ?? 0 );
		$filename  = (string) ( $cached['main_filename'] ?? '' );

		// If the cached data still matches current mtimes, return it.
		if ( $signature && $filename ) {
			$directory = get_template_directory();
			$main_css  = "{$directory}/dist/css/{$filename}.min.css";
			$main_js   = "{$directory}/dist/js/{$filename}.min.js";

			$current_css_mtime = file_exists( $main_css ) ? filemtime( $main_css ) : 0;
			$current_js_mtime  = file_exists( $main_js ) ? filemtime( $main_js ) : 0;

			if ( $current_css_mtime === $css_mtime && $current_js_mtime === $js_mtime ) {
				if ( ! $logged && is_debug_enabled() ) {
					debug_log( 'get_enqueues_build_signature()', [ 'source' => 'transient_cache', 'signature' => substr( $signature, 0, 8 ) . '...' ] );
					$logged = true;
				}
				return $signature;
			}
		}
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

	if ( is_debug_enabled() ) {
		debug_log( 'get_enqueues_build_signature()', [
			'source'        => 'generated',
			'signature'     => substr( $signature, 0, 8 ) . '...',
			'main_filename' => $main_filename,
			'css_mtime'     => $mtime_css,
			'js_mtime'      => $mtime_js,
			'css_exists'    => file_exists( $main_css ),
			'js_exists'     => file_exists( $main_js ),
		] );
	}

	// Cache the signature for 1 week.
	// The signature will change when files are rebuilt durring build process or TTL expires.
	if ( is_cache_enabled() ) {
		set_transient(
			$cache_key,
			[
				'signature'     => $signature,
				'main_filename' => $main_filename,
				'css_mtime'     => $mtime_css,
				'js_mtime'      => $mtime_js,
			],
			WEEK_IN_SECONDS,
		);
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
