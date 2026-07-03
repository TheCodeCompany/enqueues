<?php
/**
 * WP-CLI commands for the Enqueues build-time asset manifest.
 *
 * File Path: src/php/Cli/ManifestCommand.php
 *
 * @package Enqueues
 */

namespace Enqueues\Cli;

use function Enqueues\enqueues_manifest;
use function Enqueues\enqueues_manifest_path;
use function Enqueues\enqueues_manifest_generate;
use function Enqueues\enqueues_manifest_write;
use function Enqueues\enqueues_manifest_clear;
use function Enqueues\get_enqueues_build_signature;

/**
 * Build, clear and inspect the Enqueues build-time asset manifest.
 *
 * Registered as `wp enqueues manifest`.
 */
class ManifestCommand {

	/**
	 * Builds the build-time asset manifest.
	 *
	 * Snapshots the theme template list into {theme}/dist/enqueues-manifest.php so production requests
	 * read one opcache-cached file instead of walking the theme tree on every uncached request. Run this
	 * in the deploy pipeline AFTER building assets (it also stamps the current build signature, so a
	 * later deploy that changes assets without rebuilding the manifest falls back to scanning).
	 *
	 * ## EXAMPLES
	 *
	 *     wp enqueues manifest build
	 *
	 * @subcommand build
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments (unused).
	 *
	 * @return void
	 */
	public function build( $args, $assoc_args ) {
		$data = enqueues_manifest_generate();

		if ( ! enqueues_manifest_write( $data ) ) {
			\WP_CLI::error( 'Could not write the manifest (is the dist directory present and writable?): ' . enqueues_manifest_path() );
		}

		\WP_CLI::success(
			sprintf(
				'Manifest written: %s (%d templates, signature %s).',
				enqueues_manifest_path(),
				count( (array) $data['templates'] ),
				$data['signature']
			)
		);
	}

	/**
	 * Removes the build-time asset manifest (runtime falls back to scanning).
	 *
	 * ## EXAMPLES
	 *
	 *     wp enqueues manifest clear
	 *
	 * @subcommand clear
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments (unused).
	 *
	 * @return void
	 */
	public function clear( $args, $assoc_args ) {
		if ( enqueues_manifest_clear() ) {
			\WP_CLI::success( 'Manifest cleared.' );
		} else {
			\WP_CLI::error( 'Could not remove the manifest: ' . enqueues_manifest_path() );
		}
	}

	/**
	 * Shows the manifest status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp enqueues manifest status
	 *
	 * @subcommand status
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments (unused).
	 *
	 * @return void
	 */
	public function status( $args, $assoc_args ) {
		$manifest = enqueues_manifest();

		\WP_CLI::line( 'Path: ' . enqueues_manifest_path() );
		\WP_CLI::line( 'File exists: ' . ( file_exists( enqueues_manifest_path() ) ? 'yes' : 'no' ) );

		if ( null === $manifest ) {
			\WP_CLI::line( 'Active: no (absent, disabled, local dev, or signature out of sync — runtime scanning in use).' );
			return;
		}

		$sig_now = get_enqueues_build_signature();

		\WP_CLI::line( 'Active: yes' );
		\WP_CLI::line( 'Templates: ' . ( is_array( $manifest['templates'] ?? null ) ? count( $manifest['templates'] ) : 0 ) );
		\WP_CLI::line( 'Signature (manifest): ' . ( $manifest['signature'] ?? '?' ) );
		\WP_CLI::line( 'Signature (current):  ' . $sig_now );
		\WP_CLI::line( 'In sync: ' . ( ( $manifest['signature'] ?? '' ) === $sig_now ? 'yes' : 'NO — run `wp enqueues manifest build`' ) );
	}
}
