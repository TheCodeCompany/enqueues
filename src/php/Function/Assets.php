<?php
/**
 * Assist with asset loading.
 *
 * File Path: assets/src/php/Function/Assets.php
 *
 * @package Enqueues
 */

namespace Enqueues;

/**
 * Finds the file path for an asset based on the environment.
 *
 * Determines the correct file path for assets by checking for the existence of
 * minified versions and accounting for the environment (local development vs. production).
 * Defaults to the theme directory if no specific directory is provided.
 *
 * Caching Strategy:
 * - Caches asset file paths to avoid repeated file existence checks on every request.
 * - Cache is keyed based on the relative path and file name.
 * - Cached data is stored for 24 hours and automatically invalidated.
 *
 * @param string      $relative_path The path to the file relative to the specified directory.
 * @param string      $file_name     Name of the file without the extension.
 * @param string      $file_ext      File extension (typically 'js' or 'css').
 * @param string|null $directory     Base directory path. Defaults to theme directory if null.
 *
 * @return string The relative web-accessible path to the asset, or an empty string if not found.
 */
function asset_find_file_path( string $relative_path, string $file_name, string $file_ext, ?string $directory = null ): string {

	if ( ! $directory ) {
		$directory = get_template_directory();
	}

	$theme_relative_path_and_file_name = trim( $relative_path, '/' ) . '/' . trim( $file_name, '/' );

	$minified = "{$directory}/{$theme_relative_path_and_file_name}.min.{$file_ext}";
	$standard = "{$directory}/{$theme_relative_path_and_file_name}.{$file_ext}";

	$file_path = '';

	if ( function_exists( 'is_local' ) && is_local() && file_exists( $standard ) ) {
		$file_path = "/{$theme_relative_path_and_file_name}.{$file_ext}";
	} elseif ( file_exists( $minified ) ) {
		$file_path = "/{$theme_relative_path_and_file_name}.min.{$file_ext}";
	} elseif ( file_exists( $standard ) ) {
		$file_path = "/{$theme_relative_path_and_file_name}.{$file_ext}";
	}

	return $file_path;
}

/**
 * Displays a warning if an asset is missing in a local development environment.
 *
 * Intended for use in local development environments to alert developers when an
 * expected asset file is not found. It leverages wp_die() to display the message,
 * which is suppressed in non-local environments.
 *
 * @param string $path    The path to the missing asset.
 * @param string $message Warning message to display if the asset is missing.
 *
 * @return void
 */
function display_maybe_missing_local_warning( string $path, string $message ): void {

	if ( $path ) {
		return;
	}

	// Local development environment error.
	if ( function_exists( 'is_local' ) && is_local() ) {
		wp_die( $message ); // phpcs:ignore
	}
}

/**
 * Retrieves asset file data based on page type and environment conditions.
 *
 * This function checks for the existence of SASS and JS source files in the `src` directory
 * and ensures corresponding compiled CSS and JS files exist in the `dist` directory.
 * If a source file exists but the compiled file is missing, an error is thrown in a local
 * development environment. If no source file is found, it falls back to the default file.
 *
 * Caching Strategy:
 * - Caches asset file data to avoid repeated file existence checks and improve performance.
 * - Cache is keyed based on the file name and file extension.
 * - Cached data is stored for 24 hours and automatically invalidated.
 *
 * @param string      $directory            Directory path where the asset is located.
 * @param string      $directory_uri        URI of the directory for web access.
 * @param string      $directory_part       Relative path within the directory.
 * @param string      $file_name            Primary file name to search for.
 * @param string|null $fallback_file_name   Fallback file name if the primary file is not found.
 * @param string      $file_ext             File extension ('css' or 'js').
 * @param string      $missing_local_warning Warning message for missing assets in a local environment.
 *
 * @return bool|array False if the asset is not found, or an associative array of asset data if found.
 */
function get_asset_page_type_file_data(
	string $directory,
	string $directory_uri,
	string $directory_part,
	string $file_name,
	?string $fallback_file_name,
	string $file_ext,
	string $missing_local_warning = 'Run the npm build for the asset files.',
): bool|array {

	/**
	 * Filters the source directory used for locating SCSS/SASS/CSS and JS files.
	 *
	 * @param string $src_directory_part The source directory path. Default is '{$directory}/src'.
	 */
	$src_directory_part = apply_filters( 'enqueues_asset_theme_src_directory', 'src' );

	/**
	 * Filters the distribution directory used for locating compiled CSS and JS files.
	 *
	 * @param string $dist_directory_part The distribution directory path. Default is '{$directory}/dist'.
	 */
	$dist_directory_part = apply_filters( 'enqueues_asset_theme_dist_directory', 'dist' );

	/**
	 * Filters the file extension for JavaScript files.
	 *
	 * @param string $js_ext The file extension for JavaScript files. Default is 'js'.
	 */
	$js_ext = apply_filters( 'enqueues_asset_theme_js_extension', 'js' );

	/**
	 * Filters the file extension for SCSS/SASS/CSS files.
	 *
	 * @param string $css_ext The file extension for SCSS files. Default is 'scss'.
	 */
	$css_ext = apply_filters( 'enqueues_asset_theme_css_extension', 'scss' );

	$src_file_path      = "{$directory}/{$src_directory_part}/{$directory_part}/{$file_name}." . ( 'css' === $file_ext ? $css_ext : $js_ext );
	$compiled_file_path = asset_find_file_path( "{$dist_directory_part}/{$directory_part}", $file_name, $file_ext, $directory );

	$source_file_exists = file_exists( $src_file_path );

	if ( $source_file_exists && empty( $compiled_file_path ) ) {
		display_maybe_missing_local_warning( '', $missing_local_warning );
	}

	if ( ! empty( $compiled_file_path ) ) {
		$data = [
			'handle' => sanitize_key( $file_name ),
			'url'    => esc_url( "{$directory_uri}{$compiled_file_path}" ),
			'file'   => esc_url( "{$directory}{$compiled_file_path}" ),
			'ver'    => filemtime( "{$directory}{$compiled_file_path}" ),
		];

		return $data;
	}

	// Fallback logic: attempt to load fallback file.
	if ( empty( $compiled_file_path ) && $fallback_file_name ) {
		$compiled_file_path = asset_find_file_path( "{$dist_directory_part}/{$directory_part}", $fallback_file_name, $file_ext, $directory );
	}

	if ( ! empty( $compiled_file_path ) ) {
		$data = [
			'handle' => sanitize_key( $fallback_file_name ),
			'url'    => esc_url( "{$directory_uri}{$compiled_file_path}" ),
			'file'   => esc_url( "{$directory}{$compiled_file_path}" ),
			'ver'    => filemtime( "{$directory}{$compiled_file_path}" ),
		];

		return $data;
	}

	return false;
}

/**
 * Registers an inline asset to be rendered in the wp_head action.
 *
 * This function allows the dynamic registration of inline assets (styles or scripts)
 * that are to be rendered directly within the HTML head, bypassing the standard
 * WordPress enqueuing system. Useful for critical path CSS or inline JavaScript.
 *
 * IMPORTANT: Does not have dependency support, however it is rendered in later priority 999.
 *
 * @param string          $type   The type of asset ('style' or 'script').
 * @param string          $handle Unique handle for the asset.
 * @param string          $url    Full URL of the asset.
 * @param string          $file   Full path to the CSS file.
 * @param null|string|int $ver    Version number for cache busting.
 * @param array           $deps   Optional. An array of registered asset handles this asset depends on.
 *
 * @return void
 */
function add_inline_asset_to_wp_head( string $type, string $handle, string $url, string $file, null|string|int $ver, array $deps = [] ): void {

	add_filter(
		'enqueues_asset_inline_asset_to_wp_head',
		function ( $assets ) use ( $type, $handle, $url, $file, $ver, $deps ) {
			$assets[] = [
				'type'   => $type,
				'handle' => $handle,
				'url'    => $url,
				'file'   => $file,
				'ver'    => $ver,
				'deps'   => $deps,
			];

			return $assets;
		}
	);
}

/**
 * Registers an inline asset to be rendered in the wp_footer action.
 *
 * This function allows the dynamic registration of inline assets (styles or scripts)
 * that are to be rendered directly within the HTML head, bypassing the standard
 * WordPress enqueuing system. Useful for critical path CSS or inline JavaScript.
 *
 * IMPORTANT: Does not have dependency support, however it is rendered in later priority 999.
 *
 * @param string          $type   The type of asset ('style' or 'script').
 * @param string          $handle Unique handle for the asset.
 * @param string          $url    Full URL of the asset.
 * @param string          $file   Full path to the CSS file.
 * @param null|string|int $ver    Version number for cache busting.
 * @param array           $deps   Optional. An array of registered asset handles this asset depends on.
 *
 * @return void
 */
function add_inline_asset_to_wp_footer( string $type, string $handle, string $url, string $file, null|string|int $ver, array $deps = [] ) {

	add_filter(
		'enqueues_asset_inline_asset_to_wp_footer',
		function ( $assets ) use ( $type, $handle, $url, $file, $ver, $deps ) {
			$assets[] = [
				'type'   => $type,
				'handle' => $handle,
				'url'    => $url,
				'file'   => $file,
				'ver'    => $ver,
				'deps'   => $deps,
			];

			return $assets;
		}
	);
}
