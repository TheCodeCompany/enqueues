<?php
/**
 * Controller responsible for enqueuing the themes main stylesheet for the page, additional seperata sheets are loaded elsewhere.
 *
 * File Path: src/php/Controller/ThemeEnqueueMainController.php
 *
 * @package Enqueues
 */

namespace Enqueues\Controller;

use Enqueues\Base\Main\Controller;
use Enqueues\Library\EnqueueAssets;
use function Enqueues\add_inline_asset_to_wp_footer;
use function Enqueues\add_inline_asset_to_wp_head;
use function Enqueues\get_asset_page_type_file_data;

/**
 * Controller responsible for enqueuing the theme's main stylesheet for the page. Additional separate stylesheets are loaded elsewhere.
 *
 * Example usage:
 *
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
 *    - For a specific page template `template-example.php`, name the file `template-example.css` and/or `template-example.js`.
 *
 * 3. Adding by Post Type:
 *    - For a custom post type 'sta', name the file `single-sta.css` and/or `single-sta.js`.
 *
 * If a specific file is not found, it falls back to the default file defined by get_theme_default_enqueue_asset_filename (`main.css` and/or `main.js`).
 */
class ThemeEnqueueMainController extends Controller {

	/**
	 * Boot the controller.
	 *
	 * @return void
	 */
	public function set_up() {

		// Prevent duplicate initialization. There should only be once instance of this 
		// controllers features regardless of load context.
		if ( ! $this->initialize() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'load_page_or_template_type_assets' ] );
	}

	/**
	 * Load assets by page type.
	 * This allos us to load asset files based on the page type. Where the page doesnt have a match the default assets are loaded.
	 */
	public function load_page_or_template_type_assets(): void {

		$enqueue_assets = new EnqueueAssets();

		$file_name          = $enqueue_assets->get_page_or_template_type();
		$fallback_file_name = $enqueue_assets->get_theme_default_enqueue_asset_filename();

		$directory             = get_template_directory();
		$directory_uri         = get_template_directory_uri();
		$missing_local_warning = 'Run the npm build for the theme asset files. CSS, JS, fonts, and images etc.';

		/**
		 * Load the main style.
		 */
		$css_data = get_asset_page_type_file_data( $directory, $directory_uri, 'css', $file_name, $fallback_file_name, 'css', "{$missing_local_warning} Missing $file_name CSS file." );

		if ( $css_data ) {

			$css_handle = apply_filters( "enqueues_theme_css_handle_{$css_data['handle']}", $css_data['handle'] );
			$css_src    = $css_data['url'];
			$css_file   = $css_data['file'];
			$css_deps   = apply_filters( "enqueues_theme_css_dependencies_{$css_data['handle']}", 'all' );
			$css_ver    = apply_filters( "enqueues_theme_css_version_{$css_data['handle']}", $css_data['ver'] );
			$css_media  = apply_filters( "enqueues_theme_css_media_{$css_data['handle']}", 'all' );

			$register_style = apply_filters( "enqueues_theme_css_register_style_{$css_data['handle']}", true );
			$enqueue_style  = apply_filters( "enqueues_theme_css_enqueue_style_{$css_data['handle']}", true );

			if ( $register_style ) {
				if ( $enqueue_assets->render_css_inline( $css_handle ) ) {
					add_inline_asset_to_wp_head( 'style', $css_handle, $css_src, $css_file, $css_ver, $css_deps );
				} else {
					wp_register_style( $css_handle, $css_src, $css_deps, $css_ver, $css_media );
					if ( $enqueue_style ) {
						wp_enqueue_style( $css_handle );
					}
				}
			}
		}

		/**
		 * Load the main script.
		 */
		$js_data = get_asset_page_type_file_data( $directory, $directory_uri, 'js', $file_name, $fallback_file_name, 'js', "{$missing_local_warning} Missing $file_name JS file." );

		if ( $js_data ) {

			$js_handle   = apply_filters( "enqueues_theme_js_handle_{$js_data['handle']}", $js_data['handle'] );
			$js_src      = $js_data['url'];
			$js_file     = $js_data['file'];

			// Use asset_php data from get_asset_page_type_file_data for dependencies and version.
			$asset_php    = $js_data['asset_php'] ?? [];
			$default_deps = $asset_php['dependencies'] ?? [];
			$default_ver  = $asset_php['version'] ?? $js_data['ver'];

			$js_deps = apply_filters( "enqueues_theme_js_dependencies_{$js_data['handle']}", $default_deps );
			$js_ver  = apply_filters( "enqueues_theme_js_version_{$js_data['handle']}", $default_ver );
			$js_args = apply_filters(
				"enqueues_theme_js_args_{$js_data['handle']}",
				[ 
					'in_footer' => true,
					'strategy'  => 'async',
				],
			);

			$register_script = apply_filters( "enqueues_theme_js_register_script_{$js_data['handle']}", true );
			$enqueue_script  = apply_filters( "enqueues_theme_js_enqueue_script_{$js_data['handle']}", true );

			if ( $register_script ) {
				if ( $enqueue_assets->render_js_inline( $js_handle ) ) {
					add_inline_asset_to_wp_footer( 'script', $js_handle, $js_src, $js_file, $js_ver, $js_deps );
				} else {
					wp_register_script( $js_handle, $js_src, $js_deps, $js_ver, $js_args );
					if ( $enqueue_script ) {
						wp_enqueue_script( $js_handle );
					}

					[ $name, $data ] = $enqueue_assets->get_js_config( $js_handle );

					// Allow filtering of localized data and var name.
					$localized_data     = apply_filters( "enqueues_theme_js_localized_data_{$js_data['handle']}", $data );
					$localized_var_name = apply_filters( "enqueues_theme_js_localized_data_var_name_{$js_data['handle']}", $name );

					// Localize the script with the data.
					if ( $localized_var_name && $localized_data ) {
						wp_localize_script( $js_handle, $localized_var_name, $localized_data );
					}
				}
			}
		}
	}
}
