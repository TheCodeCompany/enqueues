<?php
/**
 * Load the Enqueues MU plugin.
 * This plugin commits the build files as its dependency is only psr-4.
 *
 * File Path: enqueues/enqueues-bootstrap.php
 *
 * @package Enqueues
 */

namespace Enqueues;

/**
 * Load package, first lets check if this is a standalone and then check if this is a dependency.
 */
$autoload_file = __DIR__ . '/vendor/autoload.php';

// Check if the file exists. If not, check the parent directory as this may be a dependency.
if ( ! file_exists( $autoload_file ) ) {
	$autoload_file = dirname( dirname( __DIR__ ) ) . '/autoload.php';
}

if ( file_exists( $autoload_file ) ) {

	require_once $autoload_file;

	if ( ! defined( '\\Enqueues\\APP_NAME' ) ) {
		define( 'Enqueues\\APP_NAME', basename( __FILE__, '.php' ) );
	}

	$enqueues_app = new \Enqueues\Base\Main\Application(
		APP_NAME,
		__DIR__,
		[
			new \Enqueues\Controller\ThemeEnqueueMainController(),
			new \Enqueues\Controller\ThemeEnqueueJqueryController(),
			new \Enqueues\Controller\ThemeInlineAssetController(),
			new \Enqueues\Controller\BlockEditorRegistrationController(),
		]
	);

	global $enqueues_app_config;

	/**
	 * Variable Type Definition.
	 *
	 * @param \Enqueues\Base\Library\Config $enqueues_app_config The config for this app.
	 */
	$enqueues_app_config = $enqueues_app->get_config();

} elseif ( function_exists( '\\Enqueues\\is_local' ) && \Enqueues\is_local() ) {

	// Trigger an error for local environment only.
	wp_die( 'Run composer for the "enqueues" MU Plugin.', E_USER_WARNING ); // phpcs:ignore
}
