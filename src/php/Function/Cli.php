<?php
/**
 * Registers the `wp enqueues` WP-CLI command namespace.
 *
 * Loaded by the function autoloader on every request; the guard makes it a no-op outside WP-CLI, so
 * there is zero front-end cost. The command class is PSR-4 and lazy-loaded by WP-CLI on invocation.
 *
 * File Path: src/php/Function/Cli.php
 *
 * @package Enqueues
 */

namespace Enqueues;

// phpcs:disable WordPress.Files.FileName

if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
	\WP_CLI::add_command( 'enqueues', \Enqueues\Cli\CacheCommand::class );
}
