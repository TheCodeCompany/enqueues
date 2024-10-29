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

// bail early. Prevent duplicate loading if this package is already loaded.
if ( defined( 'Enqueues\\APP_NAME' ) ) {
	return;
}

/**
 * Load the package: check if this is a standalone or dependency setup.
 */
$autoload_file = __DIR__ . '/vendor/autoload.php';

// Check if autoload file exists. If not, check parent directory as it may be a dependency.
if ( ! file_exists( $autoload_file ) ) {
	$autoload_file = dirname( dirname( __DIR__ ) ) . '/autoload.php';
}

// Bail if the autoload file does not exist.
if ( ! file_exists( $autoload_file ) ) {
	return;
}

// Load the autoload file.
require_once $autoload_file;

// Define the APP_NAME constant to prevent further duplicate loads. Based of the folder name.
define( 'Enqueues\\APP_NAME', basename( __DIR__ ) );

/**
 * Initialize the Enqueues controllers.
 * This function initializes the controllers for the Enqueues application and must be called early within plugins_loaded hook.
 * Typicaly within the WPMVC application a controller set_up method is called within the plugins_loaded hook at priority of 10.
 *
 * @param string $context The context to load the controllers, default is 'default'.
 *
 * @return void
 */
function enqueues_initialize_controllers( $context = 'default' ) {

	$contollers = [
		apply_filters( 'enqueues_load_controller', true, 'ThemeEnqueueMainController', $context ) ? new \Enqueues\Controller\ThemeEnqueueMainController() : null,
		apply_filters( 'enqueues_load_controller', true, 'ThemeEnqueueJqueryController', $context ) ? new \Enqueues\Controller\ThemeEnqueueJqueryController() : null,
		apply_filters( 'enqueues_load_controller', true, 'ThemeInlineAssetController', $context ) ? new \Enqueues\Controller\ThemeInlineAssetController() : null,
		apply_filters( 'enqueues_load_controller', true, 'BlockEditorRegistrationController', $context ) ? new \Enqueues\Controller\BlockEditorRegistrationController() : null,
	];

	// Initialize the Enqueues application.
	$enqueues_app = new \Enqueues\Base\Main\Application(
		APP_NAME,
		__DIR__,
		array_filter( $contollers ), // Remove any falsy values (such as null) from the controllers array.
		9999 // Set the priority later so other controllers can hook into plugins_loaded before default priority of 10.
	);

	// Make application configuration globally available.
	global $enqueues_app_config;

	/**
	 * Variable Type Definition.
	 *
	 * @var \Enqueues\Base\Library\Config $enqueues_app_config The configuration for this app.
	 */
	$enqueues_app_config = $enqueues_app->get_config();
}
