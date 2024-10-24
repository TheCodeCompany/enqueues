<?php
/**
 * Environment helper functions.
 *
 * File Path: src/php/Function/Env.php
 *
 * @package Enqueues
 */

namespace Enqueues;

/**
 * Get the environment slug.
 *
 * @return string
 */
function get_environment_type() {

	// Bail early if this site has defined a custom function.
	if ( function_exists( 'get_environment_type' ) ) {
		return \get_environment_type();
	}

	// Custom/legacy environment variable.
	if ( defined( 'WP_ENV' ) ) {
		return WP_ENV;
	}

	// VIP specific environment variable.
	if ( defined( 'VIP_GO_APP_ENVIRONMENT' ) ) {
		return VIP_GO_APP_ENVIRONMENT;
	}

	// Local specific environment variable.
	if ( defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV ) {
		return 'local';
	}

	// Default, returns production.
	return function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
}

/**
 * Ensure each environment is filterable by the site that is using this plugin.
 *
 * @param string $env The environment slug. Known environments are 'production','uat','staging','development','local'.
 *
 * @return bool
 */
function is_environment_match( $env ) {

	// Bail early if this site has defined a custom function.
	if ( function_exists( 'is_environment_match' ) ) {
		return \is_environment_match();
	}

	$env_type = get_environment_type();

	$environment_type_matches = apply_filters( "environment_type_matches_{$env}", [] );

	if ( $environment_type_matches ) {

		foreach ( $environment_type_matches as $match ) {

			// Match found. Return.
			if ( $env_type === $match ) {
				return true;
			}
		}
	}

	// Bail early.
	// The correct constants were defined, so url matches should not matter.
	// It may be the case that these have been defined as another environment for testing purposes.
	// e.g. define( 'WP_ENVIRONMENT_TYPE', 'staging' ); Yet the domain is a .local, so we don't
	// want to test the url partial matches.
	if ( defined( 'WP_ENVIRONMENT_TYPE' ) ||
		defined( 'VIP_GO_APP_ENVIRONMENT' ) ||
		defined( 'WP_ENV' ) ) {

		return false;
	}

	// Last resort, match the site url.
	$site_url         = get_site_url();
	$site_url_matches = apply_filters( "environment_site_url_partial_matches_{$env}", [] );

	if ( $site_url_matches ) {

		foreach ( $site_url_matches as $match ) {

			// Match found. Return.
			if ( false !== strpos( $site_url, $match ) ) {
				return true;
			}
		}
	}

	return false;
}


/**
 * Generates a slug/label for the current environment.
 *
 * IMPORTANT: These are legacy slugs used for areas such as config directories.
 *
 * @return string production|uat|staging|development|local
 */
function get_environment_slug() {

	// Bail early if this site has defined a custom function.
	if ( function_exists( 'get_environment_slug' ) ) {
		return \get_environment_slug();
	}

	if ( is_local() ) {
		return 'local';

	} elseif ( is_development() ) {
		return 'development';

	} elseif ( is_staging() ) {
		return 'staging';

	} elseif ( is_uat() ) {
		return 'uat';
	}

	return 'production';
}

/**
 * Basic helper function to check if this is the production development environment.
 *
 * @return bool
 */
function is_production() {

	// Bail early if this site has defined a custom function.
	if ( function_exists( 'is_production' ) ) {
		return \is_production();
	}

	return is_environment_match( 'production' );
}

/**
 * Basic helper function to check if this is the uat development environment.
 *
 * @return bool
 */
function is_uat() {

	// Bail early if this site has defined a custom function.
	if ( function_exists( 'is_uat' ) ) {
		return \is_uat();
	}

	return is_environment_match( 'uat' );
}

/**
 * Basic helper function to check if this is the staging development environment.
 *
 * @return bool
 */
function is_staging() {

	// Bail early if this site has defined a custom function.
	if ( function_exists( 'is_staging' ) ) {
		return \is_staging();
	}

	return is_environment_match( 'staging' );
}

/**
 * Basic helper function to check if this is the staging development environment.
 *
 * @return bool
 */
function is_development() {

	// Bail early if this site has defined a custom function.
	if ( function_exists( 'is_development' ) ) {
		return \is_development();
	}

	return is_environment_match( 'development' );
}

/**
 * Basic helper function to check if this is the local development environment.
 *
 * @return bool
 */
function is_local() {

	// Bail early if this site has defined a custom function.
	if ( function_exists( 'Enqueues\\is_local' ) ) {
		return \is_local();
	}

	return is_environment_match( 'local' );
}
