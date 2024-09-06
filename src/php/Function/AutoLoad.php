<?php
/**
 * Autoload function files.
 *
 * This script is responsible for automatically loading function files located in the same directory.
 * You may specify a load order for specific files by adding their names to the `$specific_file_load_order` array.
 *
 * File Path: enqueues/src/php/Function/AutoLoad.php
 *
 * @package Enqueues
 */

// Disable the WordPress PHP CodeSniffer rule that checks the file name.
// phpcs:disable WordPress.Files.FileName

// Define an array of file names that need to be loaded in a specific order.
$specific_file_load_order = [];

// Get a list of all PHP files in the current directory.
$function_files = glob( __DIR__ . DIRECTORY_SEPARATOR . '*.php' );

// If there are files that need to be loaded in a specific order, load them first.
if ( $specific_file_load_order ) {
	// Iterate over each file name in the specific load order.
	foreach ( $specific_file_load_order as $function_file ) {
		// Append the directory path and the '.php' extension to the file name.
		$function_file = __DIR__ . DIRECTORY_SEPARATOR . $function_file . '.php';

		// If the file exists, load it.
		if ( file_exists( $function_file ) ) {
			require_once $function_file;
		}
	}
}

// Load all other PHP files in the directory that were not specified in the specific load order.
foreach ( $function_files as $function_file ) {
	// Get the base name of the file (i.e., the file name without the directory path).
	$base_name = basename( $function_file );

	// If the file name was not specified in the specific load order, load the file.
	if ( ! $specific_file_load_order || ! in_array( $base_name, $specific_file_load_order, true ) ) {
		require_once $function_file;
	}
}
