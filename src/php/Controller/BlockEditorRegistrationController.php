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
use function Enqueues\is_block_editor_features_on;
use function Enqueues\get_translation_domain;
use function Enqueues\get_block_editor_namespace;
use function Enqueues\get_block_editor_dist_dir;
use function Enqueues\get_block_editor_categories;

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

		if ( ! is_dir( $block_editor_dist_dir ) ) {
			if ( is_local() ) {
				wp_die( sprintf( 'Block Editor dist directory %s missing.', $block_editor_dist_dir ), E_USER_ERROR ); // phpcs:ignore
			}
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

			$category['title'] = __( $category['title'], get_translation_domain() );
			$categories[]      = $category;
		}

		return $categories;
	}

	/**
	 * Enqueue assets for the block editor and frontend.
	 *
	 * @param string $type The asset type (blocks, plugins, or extensions).
	 * @param string $context The context (frontend, editor, view).
	 * @param bool   $register_only Whether to only register assets or enqueue them.
	 *
	 * @return void
	 */
	private function enqueue_assets( $type, $context, $register_only = true ): void {

		$directory                  = get_template_directory();
		$directory_uri              = get_template_directory_uri();
		$block_editor_dist_dir_path = get_block_editor_dist_dir();
		$block_editor_dist_dir      = "{$directory}{$block_editor_dist_dir_path}/{$type}";
		$enqueue_asset_dirs         = array_filter( glob( "{$block_editor_dist_dir}/*" ), 'is_dir' );
		$block_editor_namespace     = get_block_editor_namespace();

		foreach ( $enqueue_asset_dirs as $enqueue_asset_dir ) {
			$filename = basename( $enqueue_asset_dir );

			// Enqueue CSS bundle.
			$css_filetype = $this->get_filename_from_context( $context, 'css' );
			$css_path     = asset_find_file_path( "{$block_editor_dist_dir_path}/{$type}/{$filename}", $css_filetype, 'css', $directory );

			if ( $css_path ) {

				$handle = apply_filters( "enqueues_block_editor_handle_css_{$type}_{$filename}", "{$filename}-{$css_filetype}" );
				wp_register_style( $handle, "{$directory_uri}{$css_path}", [], filemtime( "{$directory}{$css_path}" ) );

				if ( ! $register_only ) {
					wp_enqueue_style( $handle );
				}
			}

			// Enqueue JS bundle.
			$js_filetype = $this->get_filename_from_context( $context, 'js' );
			$js_path     = asset_find_file_path( "{$block_editor_dist_dir_path}/{$type}/{$filename}", $js_filetype, 'js', $directory );

			if ( $js_path ) {
				$enqueue_asset_path = "{$directory}/" . ltrim( str_replace( '.js', '.asset.php', $js_path ), '/' );

				if ( file_exists( $enqueue_asset_path ) ) {

					$handle = apply_filters( "enqueues_block_editor_handle_js_{$type}_{$filename}", "{$filename}-{$js_filetype}" );

					$args = [
						'strategy' => 'async',
						'in_footer' => true,
					];

					$args = apply_filters( "enqueues_block_editor_handle_js_args_{$type}_{$filename}", $args );

					$assets = include $enqueue_asset_path;

					wp_register_script( $handle, "{$directory_uri}{$js_path}", $assets['dependencies'], $assets['version'], $args );

					if ( ! $register_only ) {
						wp_enqueue_script( $handle );
					}

					$localized_data     = apply_filters( "enqueues_block_editor_localized_data_{$type}_{$filename}", [] );
					$localized_var_name = apply_filters( "enqueues_block_editor_localized_data_var_name_{$type}_{$filename}", 'customBlockEditor' . ucfirst( $type ) . 'Config' );

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

		$this->enqueue_assets( 'blocks', 'frontend' );
		$this->enqueue_assets( 'blocks', 'view' );
		$this->enqueue_assets( 'plugins', 'frontend' );
		$this->enqueue_assets( 'plugins', 'view' );
		$this->enqueue_assets( 'extensions', 'frontend' );
		$this->enqueue_assets( 'extensions', 'view' );
	}

	/**
	 * Enqueue assets for all asset types in the editor.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {

		$this->enqueue_assets( 'blocks', 'editor', false );
		$this->enqueue_assets( 'plugins', 'editor', false );
		$this->enqueue_assets( 'extensions', 'editor', false );
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
