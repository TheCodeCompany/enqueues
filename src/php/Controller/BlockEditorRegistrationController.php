<?php
/**
 * Block Editor setup.
 *
 * File Path: src/php/Controller/BlockEditorRegistrationController.php
 *
 * @package Enqueues
 *
 * SOLVES CRITICAL CLS (Cumulative Layout Shift) ISSUE:
 * 
 * PROBLEM:
 * Dynamic blocks (with render.php or "render" in block.json) have their CSS discovered
 * during content rendering, which happens after wp_head. WordPress then uses print_late_styles()
 * to output these styles near the footer, causing:
 * - Late paint of block styles
 * - Cumulative Layout Shift (CLS) as content reflows
 * - Poor Core Web Vitals scores
 * - Poor user experience with visible content jumps
 * 
 * Static blocks don't have this issue because Core discovers their assets early
 * and prints them in <head>.
 * 
 * SOLUTION:
 * 1. Let Core register block handles from block.json (single source of truth)
 * 2. Detect dynamic blocks at registration time
 * 3. Pre-enqueue their style handles early (wp_enqueue_scripts priority 1) 
 *    if the page contains those blocks
 * 4. Result: dynamic block CSS prints in <head>, preventing CLS
 * 
 * BENEFITS:
 * - Eliminates CLS from dynamic block styles
 * - No duplication: Core owns block asset registration
 * - Works with custom handles defined in block.json
 * - Performance-friendly: only loads CSS for blocks present on page
 * - Maintains existing plugin/extension asset handling
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

class BlockEditorRegistrationController extends Controller {

	/**
	 * Tracks dynamic blocks and their style handles for pre-enqueueing.
	 * 
	 * Dynamic blocks are those with render.php or "render" in block.json.
	 * We track their style handles so we can enqueue them early (in <head>)
	 * instead of letting Core discover them late during rendering.
	 * 
	 * Example:
	 * [
	 *   'fujifilm/read-more-content' => [
	 *     'style' => 'fujifilm-read-more-content-style',      // from "style" in block.json
	 *     'view'  => 'fujifilm-read-more-content-view-style', // from "viewStyle" in block.json
	 *   ],
	 *   ...
	 * ]
	 *
	 * @var array<string, array{style?:string, view?:string}>
	 */
	protected $dynamic_blocks = [];

	/**
	 * Register hooks and initialize properties.
	 * 
	 * Sets up Core Web Vitals optimizations and block registration.
	 */
	public function set_up() {

		// Prevent duplicate initialization.
		if ( ! $this->initialize() ) {
			return;
		}

		/**
		 * Core Web Vitals optimization: ensure block styles load as <link> tags in <head>.
		 * 
		 * - should_load_separate_core_block_assets = true: Only load CSS for blocks present on page
		 * - wp_should_inline_block_styles = false: Use <link> tags instead of inline <style>
		 * 
		 * These filters ensure our pre-enqueued styles appear in <head> as cacheable <link> tags,
		 * preventing CLS and improving performance.
		 */
		add_filter( 'should_load_separate_core_block_assets', '__return_true', 1 );
		add_filter( 'wp_should_inline_block_styles', '__return_false', 1 );

		// Hooks to register blocks, categories, and plugins.
		add_action( 'init', [ $this, 'register_blocks' ] );
		add_filter( 'block_categories_all', [ $this, 'block_categories' ], 10, 2 );

		/**
		 * IMPORTANT: pre-enqueue dynamic block styles EARLY so they print in <head>.
		 * We *only* enqueue handles for blocks detected on the page (via has_block()).
		 */
		add_action( 'wp_enqueue_scripts', [ $this, 'preenqueue_dynamic_block_styles' ], 1 );

		// Enqueue actions (keep existing behavior for non-block bundles).
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	/**
	 * Register Gutenberg blocks by scanning the block directory.
	 * We use Core's metadata registration so Core registers the correct handles from block.json.
	 * While doing that, we detect *dynamic* blocks and remember their style handles for pre-enqueue.
	 *
	 * @return void
	 */
	public function register_blocks() {

		$directory                  = get_template_directory();
		$block_editor_dist_dir_path = get_block_editor_dist_dir();
		$blocks_root                = "{$directory}{$block_editor_dist_dir_path}/blocks";

		if ( ! is_dir( $blocks_root ) ) {
			return;
		}

		$ns = get_block_editor_namespace();

		foreach ( array_filter( glob( "{$blocks_root}/*" ), 'is_dir' ) as $block_dir ) {
			$block_name    = basename( $block_dir );
			$metadata_file = "{$blocks_root}/{$block_name}/block.json";

			if ( ! file_exists( $metadata_file ) ) {
				if ( is_local() ) {
					wp_die( sprintf( 'Block metadata file %s is missing.', $metadata_file ), E_USER_ERROR ); // phpcs:ignore
				}
				continue;
			}

			// Let Core register default handles (style/viewStyle/editorStyle/viewScript/editorScript, etc.)
			$result = register_block_type_from_metadata( $metadata_file );

			if ( ! $result ) {
				if ( is_local() ) {
					wp_die( sprintf( 'Block %s failed to register.', "{$ns}/{$block_name}" ), E_USER_ERROR ); // phpcs:ignore
				}
				continue;
			}

			// Detect dynamic blocks: "render" in block.json or presence of render.php.
			$meta       = json_decode( file_get_contents( $metadata_file ) ?: '[]', true ) ?: []; // phpcs:ignore
			$is_dynamic = ! empty( $meta['render'] ) || file_exists( "{$block_dir}/render.php" );

			if ( $is_dynamic ) {
				$handles = $this->extract_block_style_handles( $meta, $ns, $block_name );
				if ( $handles ) {
					$this->dynamic_blocks[ "{$ns}/{$block_name}" ] = $handles;
				}
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
	 * Pre-enqueue dynamic block styles early to prevent CLS.
	 * 
	 * This is the core fix for the CLS issue. We detect which dynamic blocks are present
	 * on the current page and enqueue their styles early (priority 1) so they print in <head>
	 * instead of being discovered late during content rendering.
	 * 
	 * Without this, dynamic block styles would be discovered during render and output
	 * via print_late_styles() near the footer, causing visible content shifts.
	 *
	 * @return void
	 */
	public function preenqueue_dynamic_block_styles(): void {
		if ( is_admin() || empty( $this->dynamic_blocks ) ) {
			return;
		}

		$maybe_enqueue = function ( $content ) {
			if ( ! is_string( $content ) || '' === $content ) {
				return;
			}
			foreach ( $this->dynamic_blocks as $block_name => $handles ) {
				if ( has_block( $block_name, $content ) ) {
					foreach ( $handles as $handle ) {
						if ( is_string( $handle ) && $handle ) {
							wp_enqueue_style( $handle ); // enqueue early → prints in <head>
						}
					}
				}
			}
		};

		if ( is_singular() ) {
			$post = get_post();
			if ( $post && isset( $post->post_content ) ) {
				$maybe_enqueue( $post->post_content );
			}
			return;
		}

		// Archives / home: inspect main query posts (lightweight heuristic).
		global $wp_query;
		if ( isset( $wp_query->posts ) && is_array( $wp_query->posts ) ) {
			$blob = '';
			foreach ( $wp_query->posts as $p ) {
				if ( isset( $p->post_content ) && is_string( $p->post_content ) ) {
					$blob .= $p->post_content . "\n";
				}
			}
			$maybe_enqueue( $blob );
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
	 * Enqueue assets for the block editor and frontend.
	 *
	 * @param string $type The asset type (blocks, plugins, or extensions).
	 * @param string $context The context (frontend, editor, view).
	 * @param bool   $enqueue_style Whether to enqueue styles after registration.
	 * @param bool   $enqueue_script Whether to enqueue scripts after registration.
	 *
	 * IMPORTANT: We skip re-registering block CSS (blocks/{name}/style.css and blocks/{name}/view.css)
	 * because Core already registers these from block.json. This prevents:
	 * - Duplicate style handles
	 * - Confusion about which registration is authoritative
	 * - Potential conflicts between our registration and Core's
	 * 
	 * Dynamic block styles are handled separately via preenqueue_dynamic_block_styles()
	 * to ensure they load in <head> and prevent CLS.
	 * 
	 * Plugin and extension assets continue to be registered as before.
	 *
	 * @return void
	 */
	private function enqueue_assets( $type, $context, $enqueue_style = false, $enqueue_script = false ): void {

		$directory                  = get_template_directory();
		$directory_uri              = get_template_directory_uri();
		$block_editor_dist_dir_path = get_block_editor_dist_dir();
		$block_editor_namespace     = get_block_editor_namespace();
		$assets_root                = "{$directory}{$block_editor_dist_dir_path}/{$type}";
		$enqueue_asset_dirs         = is_dir( $assets_root ) ? array_filter( glob( "{$assets_root}/*" ), 'is_dir' ) : [];

		foreach ( $enqueue_asset_dirs as $enqueue_asset_dir ) {
			$filename = basename( $enqueue_asset_dir );

			// Enqueue CSS bundle.
			$css_filetype = $this->get_filename_from_context( $context, 'css' );
			$css_path     = asset_find_file_path( "{$block_editor_dist_dir_path}/{$type}/{$filename}", $css_filetype, 'css', $directory );

			// Skip block CSS registration - Core already handles this from block.json.
			// Dynamic block styles are pre-enqueued separately to prevent CLS.
			if ( $css_path && 'blocks' !== $type ) {

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

				$handle = ( 'blocks' === $type && 'view' === $context )
					? "{$block_editor_namespace}-{$filename}-{$js_filetype}-script"
					: "{$filename}-{$js_filetype}";

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
	 * Extract the exact style handles that Core will use for a block.
	 * 
	 * This method determines the precise style handle names that WordPress Core
	 * will register for a block based on its block.json metadata. We need this
	 * information to pre-enqueue the correct handles and prevent CLS.
	 * 
	 * Supports both default handles and custom handles:
	 * - "style": "file:./style.css"                        → "{$ns}-{$name}-style"
	 * - "style": ["file:./style.css", "custom-style-hdl"]  → "custom-style-hdl"
	 * - "viewStyle": "file:./view.css"                     → "{$ns}-{$name}-view-style"
	 * - "viewStyle": ["file:./view.css", "custom-view-hdl"]→ "custom-view-hdl"
	 *
	 * @param array  $meta Block metadata from block.json.
	 * @param string $ns   Block namespace.
	 * @param string $name Block name.
	 *
	 * @return array{style?:string, view?:string} Style handles that Core will register.
	 */
	protected function extract_block_style_handles( array $meta, string $ns, string $name ): array {
		$out = [];

		// viewStyle
		if ( array_key_exists( 'viewStyle', $meta ) ) {
			if ( is_array( $meta['viewStyle'] ) ) {
				// Core treats the last string in the array as the handle when provided alongside "file:*".
				$strings = array_values( array_filter( $meta['viewStyle'], 'is_string' ) );
				if ( $strings ) {
					$out['view'] = end( $strings );
				}
			} elseif ( is_string( $meta['viewStyle'] ) && '' !== $meta['viewStyle'] ) {
				$out['view'] = "{$ns}-{$name}-view-style";
			}
		}

		// style
		if ( array_key_exists( 'style', $meta ) ) {
			if ( is_array( $meta['style'] ) ) {
				$strings = array_values( array_filter( $meta['style'], 'is_string' ) );
				if ( $strings ) {
					$out['style'] = end( $strings );
				}
			} elseif ( is_string( $meta['style'] ) && '' !== $meta['style'] ) {
				$out['style'] = "{$ns}-{$name}-style";
			}
		}

		return $out;
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
