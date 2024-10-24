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

// Prevent duplicate loading if this package is already loaded.
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

if ( ! file_exists( $autoload_file ) ) {
	return;
}

require_once $autoload_file;

// Define the APP_NAME constant to prevent further duplicate loads.
if ( ! defined( 'Enqueues\\APP_NAME' ) ) {
	define( 'Enqueues\\APP_NAME', basename( __FILE__, '.php' ) );
}

// Initialize the Enqueues application.
$enqueues_app = new \Enqueues\Base\Main\Application(
	APP_NAME,
	__DIR__,
	[
		new \Enqueues\Controller\ThemeEnqueueMainController(),
		new \Enqueues\Controller\ThemeEnqueueJqueryController(),
		new \Enqueues\Controller\ThemeInlineAssetController(),
		new \Enqueues\Controller\BlockEditorRegistrationController(),
	],
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
