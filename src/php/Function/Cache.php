<?php
/**
 * Cache utility functions for asset loading in the Enqueues MU Plugin.
 *
 * File Path: enqueues/src/php/Function/Cache.php
 *
 * @package Enqueues
 */

namespace Enqueues;

/**
 * Determines whether caching is enabled for asset loading.
 *
 * Caching helps to improve performance by avoiding repetitive filesystem operations such as checking file existence.
 * It is enabled based on the `ENQUEUES_CACHE_ENABLED` constant or through the 'enqueues_is_cache_enabled' filter.
 *
 * @return bool True if caching is enabled, false otherwise.
 */
function is_cache_enabled(): bool {
	/**
	 * Filters whether caching is enabled in the Enqueues plugin.
	 *
	 * @param bool $is_cache_enabled True if caching is enabled, false otherwise.
	 */
	return (bool) apply_filters( 'enqueues_is_cache_enabled', defined( 'ENQUEUES_CACHE_ENABLED' ) && ENQUEUES_CACHE_ENABLED );
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
