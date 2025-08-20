<?php
/**
 * Block Editor setup.
 *
 * File Path: src/php/Controller/BlockEditorRegistrationController.php
 *
 * @package Enqueues
 */

// phpcs:disable WordPress.Files.FileName

namespace Enqueues\Controller;

use Enqueues\Base\Main\Controller;
use function Enqueues\asset_find_file_path;
use function Enqueues\get_encoded_svg_icon;
use function Enqueues\is_local;
use function Enqueues\get_translation_domain;
use function Enqueues\get_block_editor_namespace;
use function Enqueues\get_block_editor_dist_dir;
use function Enqueues\get_block_editor_categories;
use function Enqueues\string_camelcaseify;

/**
 * Controller responsible for Block Editor related functionality.
 */
class BlockEditorRegistrationController extends Controller {

	/**
	 * Register hooks and initialize properties.
	 */
	public function set_up() {

		// Prevent duplicate initialization.
		if ( ! $this->initialize() ) {
			return;
		}

		// Hooks to register blocks, categories, and plugins.
		add_action( 'init', [ $this, 'register_blocks' ] );
		add_filter( 'block_categories_all', [ $this, 'block_categories' ], 10, 2 );

		// Enqueue actions.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	/**
	 * Register Gutenberg blocks by scanning the block directory.
	 */
	public function register_blocks() {

		$directory                  = get_template_directory();
		$block_editor_dist_dir_path = get_block_editor_dist_dir();
		$block_editor_dist_dir      = "{$directory}{$block_editor_dist_dir_path}/blocks";

		// Bail early, there are no blocks on this site. There may be plugins or extensions.
		if ( ! is_dir( $block_editor_dist_dir ) ) {
			return;
		}

		$blocks_dirs            = array_filter( glob( "{$block_editor_dist_dir}/*" ), 'is_dir' );
		$block_editor_namespace = get_block_editor_namespace();

		foreach ( $blocks_dirs as $block_dir ) {
			$block_name    = basename( $block_dir );
			$metadata_file = "{$block_editor_dist_dir}/{$block_name}/block.json";

			if ( ! file_exists( $metadata_file ) ) {
				if ( is_local() ) {
					wp_die( sprintf( 'Block metadata file %s is missing.', $metadata_file ), E_USER_ERROR ); // phpcs:ignore
				}
				continue;
			}

			$result = register_block_type( $metadata_file );

			if ( ! $result && is_local() ) {
				wp_die( sprintf( 'Block %s failed to register.', "{$block_editor_namespace}/{$block_name}" ), E_USER_ERROR ); // phpcs:ignore
			}
		}
	}

	/**
	 * Register block categories.
	 *
	 * @param array  $categories Existing categories.
	 * @param object $post Current post object.
	 * 
	 * @return array Modified categories.
	 */
	public function block_categories( $categories, $post ) {

		$block_editor_categories = get_block_editor_categories();

		if ( ! $block_editor_categories ) {
			return $categories;
		}

		$directory = get_template_directory();

		foreach ( $block_editor_categories as $category ) {
			// Validate category fields.
			if ( ! isset( $category['slug'], $category['title'] ) ) {
				continue;
			}

			if ( isset( $category['icon'] ) ) {
				$icon_path        = "{$directory}{$category['icon']}";
				$category['icon'] = get_encoded_svg_icon( $icon_path );
			}

			$category['title'] = __( $category['title'], get_translation_domain() ); // phpcs:ignore
			$categories[]      = $category;
		}

		return $categories;
	}

	/**
	 * Enqueue assets for the block editor and frontend.
	 *
	 * @param string $type The asset type (blocks, plugins, or extensions).
	 * @param string $context The context (frontend, editor, view).
	 * @param bool   $enqueue_style Whether to enqueue styles after registration.
	 * @param bool   $enqueue_script Whether to enqueue scripts after registration.
	 *
	 * @return void
	 */
	private function enqueue_assets( $type, $context, $enqueue_style = false, $enqueue_script = false ): void {

		$directory                  = get_template_directory();
		$directory_uri              = get_template_directory_uri();
		$block_editor_dist_dir_path = get_block_editor_dist_dir();
		$block_editor_namespace     = get_block_editor_namespace();
		$block_editor_dist_dir      = "{$directory}{$block_editor_dist_dir_path}/{$type}";
		$enqueue_asset_dirs         = array_filter( glob( "{$block_editor_dist_dir}/*" ), 'is_dir' );

		foreach ( $enqueue_asset_dirs as $enqueue_asset_dir ) {
			$filename = basename( $enqueue_asset_dir );

			// Enqueue CSS bundle.
			$css_filetype = $this->get_filename_from_context( $context, 'css' );
			$css_path     = asset_find_file_path( "{$block_editor_dist_dir_path}/{$type}/{$filename}", $css_filetype, 'css', $directory );

			if ( $css_path ) {

				$handle = apply_filters( "enqueues_block_editor_handle_css_{$type}_{$filename}", "{$block_editor_namespace}-{$filename}-{$css_filetype}", $context );

				$register_style = apply_filters( "enqueues_block_editor_register_style_{$type}_{$filename}", true, $context );

				if ( $register_style ) {
					$css_deps = apply_filters( "enqueues_block_editor_css_dependencies_{$type}_{$filename}", [], $context );
					$css_ver  = apply_filters( "enqueues_block_editor_css_version_{$type}_{$filename}", filemtime( "{$directory}{$css_path}" ), $context );

					wp_register_style( $handle, "{$directory_uri}{$css_path}", $css_deps, $css_ver );

					$should_enqueue_style = apply_filters( "enqueues_block_editor_enqueue_style_{$type}_{$filename}", $enqueue_style, $context );

					if ( $should_enqueue_style ) {
						wp_enqueue_style( $handle );
					}
				}
			}

			// Enqueue JS bundle.
			$js_filetype = $this->get_filename_from_context( $context, 'js' );
			$js_path     = asset_find_file_path( "{$block_editor_dist_dir_path}/{$type}/{$filename}", $js_filetype, 'js', $directory );

			if ( $js_path ) {

				$handle = 'blocks' === $type && 'view' === $context ? "{$block_editor_namespace}-{$filename}-{$js_filetype}-script" : "{$filename}-{$js_filetype}";
				$handle = apply_filters( "enqueues_block_editor_js_handle_{$type}_{$filename}", $handle, $context );

				$args = [
					'strategy'  => 'async',
					'in_footer' => true,
				];

				$args = apply_filters( "enqueues_block_editor_js_args_{$type}_{$filename}", $args, $context );

				$enqueue_asset_path = "{$directory}/" . ltrim( str_replace( '.js', '.asset.php', $js_path ), '/' );
				$assets             = file_exists( $enqueue_asset_path ) ? include $enqueue_asset_path : [];

				$register_script = apply_filters( "enqueues_block_editor_js_register_script_{$type}_{$filename}", true, $context );

				if ( $register_script ) {
					$js_deps = apply_filters( "enqueues_block_editor_js_dependencies_{$type}_{$filename}", $assets['dependencies'] ?? [], $context );
					$js_ver  = apply_filters( "enqueues_block_editor_js_version_{$type}_{$filename}", $assets['version'] ?? filemtime( "{$directory}{$js_path}" ), $context );

					wp_register_script( $handle, "{$directory_uri}{$js_path}", $js_deps, $js_ver, $args );

					$should_enqueue_script = apply_filters( "enqueues_block_editor_js_enqueue_script_{$type}_{$filename}", $enqueue_script, $context );

					if ( $should_enqueue_script ) {
						wp_enqueue_script( $handle );
					}

					$localized_data     = apply_filters( "enqueues_block_editor_js_localized_data_{$type}_{$filename}", [], $context );
					$localized_var_name = apply_filters( "enqueues_block_editor_js_localized_data_var_name_{$type}_{$filename}", string_camelcaseify( "blockEditor {$type} {$filename} Config" ), $context );

					if ( $localized_data ) {
						wp_localize_script( $handle, $localized_var_name, $localized_data );
					}
				}
			}
		}
	}

	/**
	 * Enqueue assets for all asset types on the frontend.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {

		$this->enqueue_assets( 'blocks', 'frontend', false, false );
		$this->enqueue_assets( 'blocks', 'view', false, false );
		$this->enqueue_assets( 'plugins', 'frontend', false, false );
		$this->enqueue_assets( 'plugins', 'view', false, false );
		$this->enqueue_assets( 'extensions', 'frontend', false, false );
		$this->enqueue_assets( 'extensions', 'view', false, false );
	}

	/**
	 * Enqueue assets for all asset types in the editor.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {

		$this->enqueue_assets( 'blocks', 'editor', true, true );
		$this->enqueue_assets( 'plugins', 'editor', true, true );
		$this->enqueue_assets( 'extensions', 'editor', true, true );
	}

	/**
	 * Get the filename based on the context and type.
	 *
	 * @param string $context The context in which to enqueue the asset (frontend, editor, view).
	 * @param string $type The type of asset (plugins or extensions).
	 *
	 * @return string The filename for the asset.
	 */
	protected function get_filename_from_context( $context, $type ) {
		switch ( $context ) {
			case 'editor':
				return 'js' === $type ? 'index' : 'editor';
			case 'view':
				return 'view';
			default:
				return 'js' === $type ? 'script' : 'style';
		}
	}
}
