<?php
/**
 * WP-CLI commands for the Enqueues asset cache.
 *
 * File Path: src/php/Cli/CacheCommand.php
 *
 * @package Enqueues
 */

namespace Enqueues\Cli;

use function Enqueues\flush_enqueues_cache;
use function Enqueues\get_enqueues_build_signature;
use function Enqueues\enqueues_cache_mode;
use function Enqueues\enqueues_get_settings;
use function Enqueues\is_cache_enabled;
use function Enqueues\is_request_memo_enabled;
use function Enqueues\get_cache_ttl;

/**
 * Manage the Enqueues asset cache from the command line.
 *
 * Registered as `wp enqueues`. Gives deploy pipelines a scriptable, object-cache-backend-agnostic
 * way to invalidate and inspect the cache without the admin UI.
 */
class CacheCommand {

	/**
	 * Flushes the Enqueues persistent asset cache.
	 *
	 * Rotates the cache salt so every signature-namespaced entry is orphaned (and expires via its TTL).
	 * This is the recommended post-deploy step on hosts that deploy via git checkout / rsync, which do
	 * NOT fire the switch_theme / upgrader_process_complete auto-flush hooks. Safe to run every deploy.
	 *
	 * ## EXAMPLES
	 *
	 *     wp enqueues flush
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments (unused).
	 *
	 * @return void
	 */
	public function flush( $args, $assoc_args ) {
		flush_enqueues_cache();
		\WP_CLI::success( 'Enqueues cache flushed (salt rotated).' );
	}

	/**
	 * Prints the current Enqueues build signature.
	 *
	 * The signature namespaces every persistent cache entry, so a changed value after a deploy confirms
	 * the cache will invalidate. Useful for verifying deploy hooks / the enqueues_build_signature filter.
	 *
	 * ## EXAMPLES
	 *
	 *     wp enqueues signature
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments (unused).
	 *
	 * @return void
	 */
	public function signature( $args, $assoc_args ) {
		\WP_CLI::line( get_enqueues_build_signature() );
	}

	/**
	 * Gets or sets the asset cache mode.
	 *
	 * ## OPTIONS
	 *
	 * [<mode>]
	 * : The mode to set. One of: off, request, persistent. Omit to print the current effective mode.
	 *
	 * ## EXAMPLES
	 *
	 *     # Print the current mode.
	 *     wp enqueues cache-mode
	 *
	 *     # Switch to persistent (object cache) mode.
	 *     wp enqueues cache-mode persistent
	 *
	 * @subcommand cache-mode
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments: optional [0] = mode to set.
	 * @param array $assoc_args Associative arguments (unused).
	 *
	 * @return void
	 */
	public function cache_mode( $args, $assoc_args ) {
		$valid = [ 'off', 'request', 'persistent' ];

		if ( empty( $args[0] ) ) {
			\WP_CLI::line( enqueues_cache_mode() );
			return;
		}

		$mode = (string) $args[0];

		if ( ! in_array( $mode, $valid, true ) ) {
			\WP_CLI::error( "Invalid mode '{$mode}'. Use one of: " . implode( ', ', $valid ) . '.' );
		}

		// Persist the mode plus the derived legacy booleans, preserving all other settings.
		$settings                     = enqueues_get_settings();
		$settings['cache_mode']       = $mode;
		$settings['request_memo']     = ( 'off' !== $mode );
		$settings['persistent_cache'] = ( 'persistent' === $mode );
		update_option( 'enqueues_settings', $settings );

		\WP_CLI::success( "Cache mode set to '{$mode}'." );

		if ( defined( 'ENQUEUES_CACHE_MODE' ) || defined( 'ENQUEUES_CACHE_ENABLED' ) ) {
			\WP_CLI::warning( 'A constant (ENQUEUES_CACHE_MODE / ENQUEUES_CACHE_ENABLED) is defined and overrides this setting at runtime.' );
		}
	}

	/**
	 * Shows the effective Enqueues cache status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp enqueues status
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments (unused).
	 *
	 * @return void
	 */
	public function status( $args, $assoc_args ) {
		$rows = [
			[ 'setting' => 'cache_mode', 'value' => enqueues_cache_mode() ],
			[ 'setting' => 'request_memo_active', 'value' => is_request_memo_enabled() ? 'yes' : 'no' ],
			[ 'setting' => 'persistent_cache_active', 'value' => is_cache_enabled() ? 'yes' : 'no' ],
			[ 'setting' => 'external_object_cache', 'value' => wp_using_ext_object_cache() ? 'present' : 'absent' ],
			[ 'setting' => 'cache_ttl_seconds', 'value' => (string) get_cache_ttl() ],
			[ 'setting' => 'build_signature', 'value' => get_enqueues_build_signature() ],
		];

		\WP_CLI\Utils\format_items( 'table', $rows, [ 'setting', 'value' ] );
	}
}
