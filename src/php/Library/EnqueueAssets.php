<?php
/**
 * Class responsible for enqueuing theme assets (stylesheets and scripts) based on page type, template, and post type.
 *
 * File Path: src/php/Library/EnqueueAssets.php
 *
 * @package Enqueues
 */

namespace Enqueues\Library;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use function Enqueues\asset_find_file_path;
use function Enqueues\get_cache_ttl;
use function Enqueues\get_page_type;
use function Enqueues\is_cache_enabled;
use function Enqueues\string_slugify;

/**
 * Class responsible for enqueuing the theme's main stylesheet and scripts based on page type, template, or post type.
 * This class handles automatic asset loading and fallback mechanisms, with caching to improve performance.
 *
 * Example usage:
 * 1. Adding by Page Type:
 *    - Homepage: `homepage.css` and/or `homepage.js`
 *    - Page: `page.css` and/or `page.js`
 *    - Single Post: `single.css` and/or `single.js`
 *    - Category Archive: `category.css` and/or `category.js`
 *    - Tag Archive: `tag.css` and/or `tag.js`
 *    - Taxonomy Archive: `tax.css` and/or `tax.js`
 *    - Author Archive: `author.css` and/or `author.js`
 *    - General Archive: `archive.css` and/or `archive.js`
 *    - Search Results: `search.css` and/or `search.js`
 *    - 404 Page: `notfound.css` and/or `notfound.js`
 *    - Custom Page: `custom.css` and/or `custom.js`
 *
 * 2. Adding by Template:
 *    - For a specific page template `template-example.php`, the file should be `template-example.css` and/or `template-example.js`.
 *
 * 3. Adding by Post Type:
 *    - For a custom post type 'sta', the file should be `single-sta.css` and/or `single-sta.js`.
 *
 * If a specific file is not found, it falls back to the default file defined by the function `get_theme_default_enqueue_asset_filename()` (usually `main.css` and/or `main.js`).
 */
class EnqueueAssets {

	/**
	 * Determines the appropriate file name for assets based on the current page type or template.
	 *
	 * This function first checks if the current page type supports templates (e.g., 'page' or 'single').
	 * If so, it attempts to use the slug of the current page template as the file name, provided
	 * the template is in the list of allowed types or templates. For single posts, it then checks
	 * for a post name match, a child post match, and a post type match, in that order. If none are
	 * valid or available, it checks if the page type itself is in the list of allowed types. If
	 * neither condition is met, it defaults to using the main asset file name.
	 *
	 * Caching Strategy:
	 * - This method relies on the caching of allowed page types and templates to reduce repeated file lookups.
	 * - Cached data is stored using WordPress transients and is invalidated every 24 hours to ensure freshness.
	 *
	 * @return string The file name to be used for loading assets. This can be the slug of the current page template,
	 *                the current page type if it's in the list of allowed types, or the default main asset file name.
	 */
	public function get_page_or_template_type(): string {

		$file_name = $this->get_theme_default_enqueue_asset_filename();

		// Asset Page Type File Data variables.
		$page_type = get_page_type();

		$allowed_page_types_and_templates = $this->get_enqueues_theme_allowed_page_types_and_templates();

		// Template Support, which is used for page and single post types.
		if ( in_array( $page_type, [ 'page', 'single' ], true ) ) {

			$current_template_slug = basename( get_page_template_slug(), '.php' );

			// Update the file name if the current template is in the allowed list.
			if ( in_array( $current_template_slug, $allowed_page_types_and_templates, true ) ) {
				$file_name = $current_template_slug;
			}
		}

		// Post name support, if the file name is still the default and the page type is 'single'.
		if ( 'single' === $page_type &&
			$this->get_theme_default_enqueue_asset_filename() === $file_name ) {

			$post_name_filename = $this->get_single_post_name_asset_name();
			if ( $post_name_filename ) {
				$file_name = $post_name_filename;
			}
		}

		// Child post support, if the file name is still the default and the page type is 'single'.
		if ( 'single' === $page_type &&
			$this->get_theme_default_enqueue_asset_filename() === $file_name ) {

			$child_post_filename = $this->get_child_post_type_asset_name();
			if ( $child_post_filename ) {
				$file_name = $child_post_filename;
			}
		}

		// Post Type support, if the file name is still the default and the page type is 'single'.
		if ( 'single' === $page_type &&
			$this->get_theme_default_enqueue_asset_filename() === $file_name ) {

			$post_type_filename = $this->get_single_post_type_asset_name();
			if ( $post_type_filename ) {
				$file_name = $post_type_filename;
			}
		}

		// General support. If filename is unchanged, check if the page type is in the allowed list.
		if ( in_array( $page_type, $allowed_page_types_and_templates, true ) &&
			$this->get_theme_default_enqueue_asset_filename() === $file_name ) {
			$file_name = $page_type;
		}

		return $file_name;
	}

	/**
	 * Get the post name asset filename for single posts.
	 *
	 * @return string|null The asset name if it exists.
	 */
	protected function get_single_post_name_asset_name(): ?string {
		if ( 'single' !== get_page_type() ) {
			return null;
		}

		$post = get_post();

		if ( ! is_a( $post, 'WP_Post' ) ) {
			return null;
		}

		if ( '' === $post->post_name ) {
			return null;
		}

		$post_type = $post->post_type;

		if ( ! is_string( $post_type ) || '' === $post_type ) {
			return null;
		}

		$asset_name = "single-{$post_type}-{$post->post_name}";
		$matched    = $this->get_existing_asset_name( $asset_name );

		if ( $matched ) {
			return $matched;
		}

		$slugified = string_slugify( $asset_name );
		if ( $slugified !== $asset_name ) {
			return $this->get_existing_asset_name( $slugified );
		}

		return null;
	}

	/**
	 * Get the child post type asset filename for single posts.
	 *
	 * @return string|null The asset name if it exists.
	 */
	protected function get_child_post_type_asset_name(): ?string {
		if ( false === $this->is_child_single() ) {
			return null;
		}

		$post_type = get_post_type();

		if ( ! is_string( $post_type ) || '' === $post_type ) {
			return null;
		}

		$post_type_candidates = $this->get_post_type_asset_candidates( $post_type );
		foreach ( $post_type_candidates as $post_type_candidate ) {
			$asset_name = "single-{$post_type_candidate}-child";
			$matched    = $this->get_existing_asset_name( $asset_name );

			if ( $matched ) {
				return $matched;
			}

			$slugified = string_slugify( $asset_name );
			if ( $slugified !== $asset_name ) {
				$slugified_match = $this->get_existing_asset_name( $slugified );
				if ( $slugified_match ) {
					return $slugified_match;
				}
			}
		}

		return null;
	}

	/**
	 * Get the post type asset filename for single posts.
	 *
	 * @return string|null The asset name if it exists.
	 */
	protected function get_single_post_type_asset_name(): ?string {
		if ( 'single' !== get_page_type() ) {
			return null;
		}

		$post_type = get_post_type();

		if ( ! is_string( $post_type ) || '' === $post_type ) {
			return null;
		}

		$post_type_candidates = $this->get_post_type_asset_candidates( $post_type );
		foreach ( $post_type_candidates as $post_type_candidate ) {
			$asset_name = "single-{$post_type_candidate}";
			$matched    = $this->get_existing_asset_name( $asset_name );

			if ( $matched ) {
				return $matched;
			}

			$slugified = string_slugify( $asset_name );
			if ( $slugified !== $asset_name ) {
				$slugified_match = $this->get_existing_asset_name( $slugified );
				if ( $slugified_match ) {
					return $slugified_match;
				}
			}
		}

		return null;
	}

	/**
	 * Get post type candidates for asset matching.
	 *
	 * Remapped post types are checked first, followed by the original post type.
	 *
	 * @param string $post_type The current post type.
	 *
	 * @return array Ordered list of post types to check.
	 */
	protected function get_post_type_asset_candidates( string $post_type ): array {
		/**
		 * Filter post type remapping for theme asset matching.
		 *
		 * @param array  $post_type_remap The post type remapping array.
		 * @param string $post_type       The current post type.
		 */
		$post_type_remap = apply_filters( 'enqueues_theme_post_type_asset_remap', [], $post_type );
		$candidates      = [];

		if ( is_array( $post_type_remap ) &&
			isset( $post_type_remap[ $post_type ] ) &&
			is_string( $post_type_remap[ $post_type ] ) &&
			'' !== $post_type_remap[ $post_type ] ) {
			$candidates[] = $post_type_remap[ $post_type ];
		}

		$candidates[] = $post_type;

		return array_values( array_unique( $candidates ) );
	}

	/**
	 * Check whether the current request is a child CPT single page.
	 *
	 * @return bool
	 */
	protected function is_child_single(): bool {
		if ( false === is_singular() ) {
			return false;
		}

		$post = get_post();

		if ( ! is_a( $post, 'WP_Post' ) ) {
			return false;
		}

		$post_type_object = get_post_type_object( $post->post_type );

		if ( ! $post_type_object ) {
			return false;
		}

		// Limit this behaviour to hierarchical custom post types.
		if ( true === (bool) $post_type_object->_builtin || false === (bool) $post_type_object->hierarchical ) {
			return false;
		}

		return 0 !== (int) $post->post_parent;
	}

	/**
	 * Check whether the asset exists for the configured CSS or JS directories.
	 *
	 * @param string $asset_name The asset name to check.
	 *
	 * @return string|null The asset name if it exists.
	 */
	protected function get_existing_asset_name( string $asset_name ): ?string {
		if ( '' === $asset_name ) {
			return null;
		}

		$theme_directory = get_template_directory();
		$css_dir         = '/' . ltrim( apply_filters( 'enqueues_theme_css_src_dir', 'dist/css' ), '/' );
		$js_dir          = '/' . ltrim( apply_filters( 'enqueues_theme_js_src_dir', 'dist/js' ), '/' );

		$css_path = asset_find_file_path( $css_dir, $asset_name, 'css', $theme_directory );
		$js_path  = asset_find_file_path( $js_dir, $asset_name, 'js', $theme_directory );

		if ( '' === $css_path && '' === $js_path ) {
			return null;
		}

		return $asset_name;
	}

	/**
	 * JS config used throughout the site.
	 *
	 * @param string $js_handle The handle of the JS file.
	 *
	 * @return array
	 */
	public function get_js_config( $js_handle ) {

		$name = 'mainConfig';

		// Default config.
		$data = [ 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ];

		/**
		 * Filter the name used in the JavaScript config for a given JS handle.
		 *
		 * @param string $name      The camel-cased name for the JS config.
		 * @param string $js_handle The JavaScript handle.
		 */
		$name = apply_filters( "enqueues_js_config_name_{$js_handle}", $name, $js_handle );

		/**
		 * Filter the data used in the JavaScript config for a given JS handle.
		 *
		 * @param array  $data      The default data for the JS config.
		 * @param string $js_handle The JavaScript handle.
		 */
		$data = apply_filters( "enqueues_js_config_data_{$js_handle}", $data, $js_handle );

		return [ $name, $data ];
	}

	/**
	 * =========================================================================
	 * Helpers.
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Get the default asset filename for the theme.
	 *
	 * @return string
	 */
	public function get_theme_default_enqueue_asset_filename(): string {

		/**
		 * Filter the default asset filename for the theme.
		 *
		 * @param string $filename The default asset filename.
		 */
		return apply_filters( 'enqueues_theme_default_enqueue_asset_filename', 'main' );
	}

	/**
	 * Get the allowed page types and templates for theme assets.
	 *
	 * This method automatically discovers template files in the theme and looks for corresponding CSS/JS assets.
	 * It also checks registered post types and includes files for them if they exist.
	 *
	 * Caching Strategy:
	 * - Caches the allowed page types and templates for 24 hours using WordPress transients.
	 * - The cache is keyed based on the page types and templates to ensure uniqueness.
	 * - Cache improves performance by avoiding repeated file system lookups on every request.
	 *
	 * @return array List of allowed page types and templates.
	 */
	public function get_enqueues_theme_allowed_page_types_and_templates(): array {

		// Get theme directory path.
		$theme_directory = get_template_directory();

		// Search for template files in the theme's root and template parts directories.
		$template_files = $this->get_theme_template_files( $theme_directory );

		// Search for custom post types.
		$raw_post_types = get_post_types( [ 'public' => true ], 'names' );

		// Map custom post types to the 'single-{post_type}' format.
		if ( $raw_post_types ) {
			$post_types = array_values(
				array_map(
					fn( $post_type ) => "single-{$post_type}",
					$raw_post_types,
				),
			);

			// Add slugified versions of post types for compatibility.
			$slugified_post_types = array_values(
				array_map(
					function ( $post_type ) {
						$slugified = string_slugify( $post_type );
						return "single-{$slugified}";
					},
					$raw_post_types,
				),
			);

			// Merge original and slugified versions, removing duplicates.
			$post_types = array_unique( array_merge( $post_types, $slugified_post_types ) );
		}

		// Add the current page type by default, such as 'homepage', 'page', 'single', etc.
		// If no matching asset file is found for the page type, it will be filtered out later.
		// The order of merging prioritizes template files, followed by custom post types, and finally the page type.
		$allowed = array_merge( $template_files, $post_types, [ get_page_type() ] );

		// Search for asset files corresponding to known page types and templates.
		$enqueue_asset_files = $this->get_enqueue_asset_files( $theme_directory, $allowed );

		// Merge found templates and asset files into the allowed list.
		$allowed = array_unique( array_merge( $template_files, $enqueue_asset_files ) );
		sort( $allowed );

		/**
		 * Filter the allowed page types and templates for the theme.
		 *
		 * @param array $allowed The default allowed page types and templates.
		 */
		return apply_filters( 'enqueues_theme_allowed_page_types_and_templates', $allowed );
	}

	/**
	 * Get template files from the theme directory.
	 *
	 * Caching Strategy:
	 * - Results are cached for 24 hours to avoid repeated file system access.
	 * - The cache key is static (`enqueues_theme_template_files`) because the content doesn't change frequently.
	 * - This reduces I/O operations and improves page load performance.
	 *
	 * @param string $theme_directory The path to the theme directory.
	 *
	 * @return array List of template filenames (without extensions).
	 */
	protected function get_theme_template_files( string $theme_directory ): array {

		// Try to get the cached value first.
		$cache_key      = 'enqueues_theme_template_files';
		$template_files = is_cache_enabled() ? get_transient( $cache_key ) : false;
		if ( $template_files ) {
			return $template_files;
		}

		$template_files = [];

		// Look for .php files in the theme's root and subdirectories like 'template-parts', excluding build-tools and dist directories.
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $theme_directory, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY,
		);

		foreach ( $files as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$file_path = $file->getPathname();

				$directories = [
					'/build-tools/',
					'/dist/',
					'/node_modules/',
					'/vendor/',
				];

				/**
				 * Filters the array of directories to skip being scanned for template files.
				 *
				 * @param array $directories The array of directories to skip being scanned for template files.
				 */
				$directories = apply_filters( 'enqueues_theme_skip_scan_directories', $directories );
				
				// Skip files in the specified directories.
				foreach ( $directories as $dir ) {
					if ( strpos( $file_path, $dir ) !== false ) {
						continue 2; // 'continue 2' to skip the current iteration of the outer loop, if applicable.
					}
				}

				// Skip files ending with '.asset.php' or 'index.php'.
				if ( str_ends_with( $file->getBasename(), '.asset.php' ) || str_contains( $file->getBasename(), 'index.php' ) ) {
					continue;
				}

				// Add the template file basename (without extension).
				$template_files[] = $file->getBasename( '.php' );
			}
		}

		// Cache the results for 24 hours.
		if ( is_cache_enabled() ) {
			set_transient( $cache_key, $template_files, get_cache_ttl() );
		}

		return $template_files;
	}

	/**
	 * Get asset files from the theme directory corresponding to known page types and templates.
	 *
	 * Caching Strategy:
	 * - Results are cached for 24 hours.
	 * - A unique cache key is generated using the MD5 hash of the known files array to ensure the cache key is unique to the combination of page types and templates.
	 *
	 * @param string $theme_directory The path to the theme directory.
	 * @param array  $known_files     Array of known page types and template filenames.
	 * @return array List of found asset filenames (without extensions).
	 */
	protected function get_enqueue_asset_files( string $theme_directory, array $known_files ): array {

		// Cache key based on known files for uniqueness.
		$cache_key           = 'enqueues_asset_files_' . md5( wp_json_encode( $known_files ) );
		$enqueue_asset_files = is_cache_enabled() ? get_transient( $cache_key ) : false;
		if ( $enqueue_asset_files ) {
			return $enqueue_asset_files;
		}

		$enqueue_asset_files = [];

		/**
		 * The built CSS asset directory relative to the theme root directory.
		 *
		 * @param string $theme_css_dist_dir The built CSS asset directory relative to the theme root directory.
		 */
		$theme_css_dist_dir = apply_filters( 'enqueues_theme_css_src_dir', 'dist/css' );

		/**
		 * The built JS asset directory relative to the theme root directory.
		 *
		 * @param string $theme_js_dist_dir The built JS asset directory relative to the theme root directory.
		 */
		$theme_js_dist_dir = apply_filters( 'enqueues_theme_js_src_dir', 'dist/js' );

		foreach ( [ $theme_css_dist_dir, $theme_js_dist_dir ] as $theme_asset_src_dir ) {
			foreach ( $known_files as $file ) {
				foreach ( [ 'min.css', 'min.js', 'css', 'js' ] as $ext ) {
					$enqueue_asset_path = "{$theme_directory}/{$theme_asset_src_dir}/{$file}.{$ext}";
					if ( file_exists( $enqueue_asset_path ) ) {
						$enqueue_asset_files[] = $file;
					}
				}
			}
		}

		// Cache the results for 24 hours.
		if ( is_cache_enabled() ) {
			set_transient( $cache_key, $enqueue_asset_files, get_cache_ttl() );
		}

		return $enqueue_asset_files;
	}

	/**
	 * Should the CSS be rendered inline.
	 *
	 * @param string $css_handle The CSS handle.
	 *
	 * @return bool
	 */
	public function render_css_inline( $css_handle = '' ): bool {
		
		/**
		 * Filter to render CSS inline.
		 *
		 * @param bool   $render_inline Whether to render CSS inline.
		 * @param string $css_handle    The CSS handle.
		 */
		$render_inline = apply_filters( 'enqueues_render_css_inline', false, $css_handle );

		return $render_inline;
	}

	/**
	 * Should the JS be rendered inline.
	 *
	 * @param string $js_handle The JS handle.
	 *
	 * @return bool
	 */
	public function render_js_inline( $js_handle = '' ): bool {

		/**
		 * Filter to render JS inline.
		 *
		 * @param bool   $render_inline Whether to render JS inline.
		 * @param string $js_handle    The JS handle.
		 */
		$render_inline = apply_filters( 'enqueues_render_js_inline', false, $js_handle );

		return $render_inline;
	}
}
