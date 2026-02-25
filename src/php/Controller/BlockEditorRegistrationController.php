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
 * 3. Pre-enqueue their style handles after priming dependencies so styles print in <head>.
 *    This happens on wp_enqueue_scripts after shim registration and dependency priming.
 * 4. Add localized parameters to registered block scripts using Core's handles
 * 5. Result: dynamic block CSS prints in <head>, preventing CLS
 * 
 * BENEFITS:
 * - Eliminates CLS from dynamic block styles
 * - No duplication: Core owns block asset registration
 * - Works with custom handles defined in block.json
 * - Performance-friendly: only loads CSS for blocks present on page
 * - Maintains existing plugin/extension asset handling
 * - Supports localized parameters for block scripts
 */

// phpcs:disable WordPress.Files.FileName

namespace Enqueues\Controller;

use WP_Block_Type_Registry;
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
 * Controller that integrates with the Block Editor (Gutenberg) to:
 * - Register blocks and categories.
 * - Track block handles for dependency management.
 * - Prime vendor dependencies via shim handles.
 * - Pre-enqueue dynamic block styles early to prevent CLS.
 * - Add localized parameters to registered block scripts.
 */
class BlockEditorRegistrationController extends Controller {

	/**
	 * Tracks all blocks and their asset handles for dependency management and CLS prevention.
	 * 
	 * We track both static and dynamic blocks to allow dependency management while
	 * still preventing CLS for dynamic blocks by pre-enqueueing their styles early.
	 * 
	 * Example:
	 * [
	 *   'dynamic' => [
	 *     'example/read-more-content' => [
	 *       'style_handles' => ['example-read-more-content-style'],
	 *       'view_style_handles' => ['example-read-more-content-view-style'],
	 *       'editor_style_handles' => ['example-read-more-content-editor-style'],
	 *       'script_handles' => ['example-read-more-content-script'],
	 *       'view_script_handles' => ['example-read-more-content-view-script'],
	 *       'editor_script_handles' => ['example-read-more-content-editor-script'],
	 *     ],
	 *   ],
	 *   'static' => [
	 *     'example/hero-block' => [
	 *       'style_handles' => ['example-hero-block-style'],
	 *       'view_style_handles' => ['example-hero-block-view-style'],
	 *       'editor_style_handles' => ['example-hero-block-editor-style'],
	 *     ],
	 *   ],
	 * ]
	 *
	 * @var array{dynamic: array<string, array>, static: array<string, array>}
	 */
	protected $blocks = [ 
		'dynamic' => [],
		'static'  => [],
	];

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
		 * - styles_inline_size_limit = 0: Prevent any block styles from being inlined
		 * 
		 * These filters ensure our pre-enqueued styles appear in <head> as cacheable <link> tags,
		 * preventing CLS and improving performance.
		 */
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );
		add_filter( 'wp_should_inline_block_styles', '__return_false' );
		add_filter( 'styles_inline_size_limit', '__return_zero' );

		// Hooks to register blocks, categories, and plugins.
		add_filter( 'block_type_metadata', [ $this, 'set_block_metadata_version' ], 99, 2 );
		add_filter( 'block_type_metadata_settings', [ $this, 'set_block_asset_version' ], 99, 2 );
		add_action( 'init', [ $this, 'register_blocks' ] );
		add_filter( 'block_categories_all', [ $this, 'block_categories' ], 10, 2 );

		// Pre-enqueue dynamic block styles so they print in <head>.
		add_action( 'wp_enqueue_scripts', [ $this, 'preenqueue_dynamic_block_styles' ], 1 );

		// Add localized parameters to registered block scripts.
		add_action( 'wp_enqueue_scripts', [ $this, 'localize_block_scripts' ], 20 );
		add_action( 'enqueue_block_editor_assets', [ $this, 'localize_block_scripts' ], 20 );

		// Enqueue actions (keep existing behavior for non-block bundles).
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	/**
	 * Set block metadata version using compiled asset versions.
	 *
	 * @param array  $metadata Block metadata parsed from block.json.
	 * @param string $file     Path to the block.json file.
	 *
	 * @return array
	 */
	public function set_block_metadata_version( array $metadata, string $file = '' ): array {
		if ( empty( $metadata['name'] ) ) {
			return $metadata;
		}

		$namespace = get_block_editor_namespace();
		if ( 0 !== strpos( $metadata['name'], "{$namespace}/" ) ) {
			return $metadata;
		}

		$block_parts = explode( '/', $metadata['name'] );
		$block_slug  = end( $block_parts );

		if ( $this->should_use_block_json_version( (string) $metadata['name'], $metadata ) ) {
			return $metadata;
		}

		$asset_version = $this->get_block_asset_version( $block_slug, $metadata );
		if ( $asset_version ) {
			$metadata['version'] = (string) $asset_version;
		}

		return $metadata;
	}

	/**
	 * Set block registration settings version using compiled asset versions.
	 *
	 * @param array $settings Block settings passed to registration.
	 * @param array $metadata Block metadata parsed from block.json.
	 *
	 * @return array
	 */
	public function set_block_asset_version( array $settings, array $metadata ): array {
		if ( empty( $metadata['name'] ) ) {
			return $settings;
		}

		$namespace = get_block_editor_namespace();
		if ( 0 !== strpos( $metadata['name'], "{$namespace}/" ) ) {
			return $settings;
		}

		$block_parts = explode( '/', $metadata['name'] );
		$block_slug  = end( $block_parts );

		if ( $this->should_use_block_json_version( (string) $metadata['name'], $metadata ) ) {
			return $settings;
		}

		$asset_version = $this->get_block_asset_version( $block_slug, $metadata );
		if ( $asset_version ) {
			$settings['version'] = (string) $asset_version;
		}

		return $settings;
	}

	/**
	 * Build a deterministic version hash from compiled block assets.
	 *
	 * @param string $block_slug Block folder name.
	 * @param array  $metadata   Block metadata parsed from block.json.
	 *
	 * @return string|int
	 */
	private function get_block_asset_version( string $block_slug, array $metadata ): string|int {
		$directory                  = get_template_directory();
		$block_editor_dist_dir_path = ltrim( get_block_editor_dist_dir(), '/' );
		$asset_keys                 = [ 'style', 'editorStyle', 'viewStyle', 'script', 'editorScript', 'viewScript' ];
		$version_parts              = [];

		foreach ( $asset_keys as $asset_key ) {
			if ( empty( $metadata[ $asset_key ] ) ) {
				continue;
			}

			$asset_value = $metadata[ $asset_key ];
			$asset_items = is_array( $asset_value ) ? $asset_value : [ $asset_value ];

			foreach ( $asset_items as $asset_item ) {
				if ( ! is_string( $asset_item ) || 0 !== strpos( $asset_item, 'file:' ) ) {
					continue;
				}

				$relative_file = ltrim( substr( $asset_item, 5 ), './' );
				if ( '' === $relative_file ) {
					continue;
				}

				$file_parts = pathinfo( $relative_file );
				$file_name  = $file_parts['filename'] ?? '';
				$file_ext   = $file_parts['extension'] ?? '';

				if ( '' === $file_name || '' === $file_ext ) {
					continue;
				}

				$compiled_file_path = asset_find_file_path( "{$block_editor_dist_dir_path}/blocks/{$block_slug}", $file_name, $file_ext, $directory );
				if ( ! $compiled_file_path ) {
					continue;
				}

				$compiled_file_mtime = filemtime( "{$directory}{$compiled_file_path}" );
				if ( false === $compiled_file_mtime ) {
					continue;
				}

				$version_parts[] = "{$asset_key}:{$asset_item}:{$compiled_file_mtime}";
			}
		}

		if ( empty( $version_parts ) ) {
			return 0;
		}

		return md5( implode( '|', $version_parts ) );
	}

	/**
	 * Determine if block.json version should be used for a block.
	 *
	 * Default is false (use compiled asset versions). This filter accepts:
	 * - bool: true for all blocks, false for none.
	 * - string: full block name (namespace/slug), slug only, or '*'/all.
	 * - array: list of block names/slugs or '*'/all.
	 *
	 * @param string $block_name Full block name (namespace/slug).
	 * @param array  $metadata   Block metadata parsed from block.json.
	 *
	 * @return bool
	 */
	private function should_use_block_json_version( string $block_name, array $metadata ): bool {
		$filter_value = apply_filters( 'enqueues_block_editor_use_block_json_version', false, $block_name, $metadata );
		$block_parts  = explode( '/', $block_name );
		$block_slug   = end( $block_parts );

		if ( is_bool( $filter_value ) ) {
			return $filter_value;
		}

		if ( is_string( $filter_value ) ) {
			$value = trim( $filter_value );

			return in_array( $value, [ '*', 'all', $block_name, $block_slug ], true );
		}

		if ( is_array( $filter_value ) ) {
			$values = array_map(
				static function ( $item ) {
					return is_string( $item ) ? trim( $item ) : $item;
				},
				$filter_value
			);

			return in_array( '*', $values, true )
				|| in_array( 'all', $values, true )
				|| in_array( $block_name, $values, true )
				|| in_array( $block_slug, $values, true );
		}

		return false;
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

			$full_name = "{$ns}/{$block_name}";

			// Let Core register default handles (style/viewStyle/editorStyle/viewScript/editorScript, etc.)
			$result = register_block_type_from_metadata( $metadata_file );

			if ( ! $result ) {
				if ( is_local() ) {
					wp_die( sprintf( 'Block %s failed to register.', $full_name ), E_USER_ERROR ); // phpcs:ignore
				}
				continue;
			}

			// Pull exact handles from the registry for perfect alignment with Core.
			$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $full_name );
			if ( ! $block_type ) {
				continue;
			}

			$is_dynamic = ( isset( $block_type->render_callback ) && $block_type->render_callback ) || file_exists( "{$block_dir}/render.php" );

			$handles = [ 
				'style_handles'         => isset( $block_type->style_handles ) ? (array) $block_type->style_handles : [],
				'view_style_handles'    => isset( $block_type->view_style_handles ) ? (array) $block_type->view_style_handles : [],
				'editor_style_handles'  => isset( $block_type->editor_style_handles ) ? (array) $block_type->editor_style_handles : [],
				'script_handles'        => isset( $block_type->script_handles ) ? (array) $block_type->script_handles : [],
				'view_script_handles'   => isset( $block_type->view_script_handles ) ? (array) $block_type->view_script_handles : [],
				'editor_script_handles' => isset( $block_type->editor_script_handles ) ? (array) $block_type->editor_script_handles : [],
			];

			// Track dynamic blocks for CLS prevention.
			$this->blocks[ $is_dynamic ? 'dynamic' : 'static' ][ $full_name ] = $handles;
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
		if ( is_admin() || empty( $this->blocks['dynamic'] ) ) {
			return;
		}

		$maybe_enqueue = function ( $content ) {
			if ( ! is_string( $content ) || '' === $content ) {
				return;
			}
			foreach ( $this->blocks['dynamic'] as $block_name => $handles ) {
				if ( has_block( $block_name, $content ) ) {
					// Enqueue all frontend style handles (style + viewStyle) so CSS prints in <head>.
					$style_handles      = isset( $handles['style_handles'] ) ? (array) $handles['style_handles'] : [];
					$view_style_handles = isset( $handles['view_style_handles'] ) ? (array) $handles['view_style_handles'] : [];
					foreach ( array_merge( $style_handles, $view_style_handles ) as $style_handle ) {
						if ( is_string( $style_handle ) && '' !== $style_handle ) {
							wp_enqueue_style( $style_handle );
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
	 * Add localized parameters to registered block scripts.
	 * 
	 * This allows blocks to have localized data even if they don't register their own scripts,
	 * using the handles that WordPress Core registered from block.json.
	 *
	 * @return void
	 */
	public function localize_block_scripts(): void {
		$context = is_admin() ? 'editor' : 'frontend';
		
		// Process both static and dynamic blocks for localization.
		foreach ( [ 'static', 'dynamic' ] as $block_type ) {
			foreach ( $this->blocks[ $block_type ] as $block_name => $handles ) {
				$block_parts = explode( '/', $block_name );
				$block_slug  = end( $block_parts );
				
				// Determine which script handles to localize based on context.
				$script_handles = [];
				if ( 'editor' === $context ) {
					$script_handles = array_merge(
						isset( $handles['script_handles'] ) ? (array) $handles['script_handles'] : [],
						isset( $handles['editor_script_handles'] ) ? (array) $handles['editor_script_handles'] : []
					);
				} else {
					$script_handles = array_merge(
						isset( $handles['script_handles'] ) ? (array) $handles['script_handles'] : [],
						isset( $handles['view_script_handles'] ) ? (array) $handles['view_script_handles'] : []
					);
				}

				foreach ( $script_handles as $script_handle ) {
					if ( ! is_string( $script_handle ) || '' === $script_handle ) {
						continue;
					}

					// Allow filtering of localized data for each block script.
					$localized_data = apply_filters( 
						"enqueues_block_editor_js_localized_data_blocks_{$block_slug}", 
						[], 
						$context, 
						$script_handle 
					);

					if ( ! empty( $localized_data ) ) {
						$localized_var_name = apply_filters( 
							"enqueues_block_editor_js_localized_data_var_name_blocks_{$block_slug}", 
							string_camelcaseify( "blockEditor blocks {$block_slug} Config" ), 
							$context, 
							$script_handle 
						);

						wp_localize_script( $script_handle, $localized_var_name, $localized_data );
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
		// Enqueue plugin and extension assets for the frontend.
		$this->enqueue_plugin_and_extension_assets( 'plugins', 'view', false, false );
		$this->enqueue_plugin_and_extension_assets( 'extensions', 'view', false, false );
	}

	/**
	 * Enqueue assets for all asset types in the editor.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		// Enqueue plugin and extension assets for the editor.
		$this->enqueue_plugin_and_extension_assets( 'plugins', 'editor', true, true );
		$this->enqueue_plugin_and_extension_assets( 'extensions', 'editor', true, true );
	}

	/**
	 * Enqueue plugin and extension assets for the block editor and frontend.
	 *
	 * @param string $type The asset type (plugins or extensions).
	 * @param string $context The context (frontend, editor, view).
	 * @param bool   $enqueue_style Whether to enqueue styles after registration.
	 * @param bool   $enqueue_script Whether to enqueue scripts after registration.
	 *
	 * IMPORTANT: This method only handles plugins and extensions. Block assets are managed by WordPress Core
	 * via block.json registration. This prevents:
	 * - Duplicate style handles
	 * - Confusion about which registration is authoritative
	 * - Potential conflicts between our registration and Core's
	 * 
	 * Dynamic block styles are handled separately via preenqueue_dynamic_block_styles()
	 * to ensure they load in <head> and prevent CLS.
	 *
	 * @return void
	 */
	private function enqueue_plugin_and_extension_assets( $type, $context, $enqueue_style = false, $enqueue_script = false ): void {

		$directory                  = get_template_directory();
		$directory_uri              = get_template_directory_uri();
		$block_editor_dist_dir_path = get_block_editor_dist_dir();
		$block_editor_namespace     = get_block_editor_namespace();
		$assets_root                = "{$directory}{$block_editor_dist_dir_path}/{$type}";
		$enqueue_asset_dirs         = is_dir( $assets_root ) ? array_filter( glob( "{$assets_root}/*" ), 'is_dir' ) : [];

		foreach ( $enqueue_asset_dirs as $enqueue_asset_dir ) {
			$foldername = basename( $enqueue_asset_dir );

			// Enqueue CSS bundle.
			$css_filetype = $this->get_filename_from_context( $context, 'css' );
			$css_path     = asset_find_file_path( "{$block_editor_dist_dir_path}/{$type}/{$foldername}", $css_filetype, 'css', $directory );

			// Register CSS for all asset types including blocks.
			// This allows dependency management while Core handles enqueueing for static blocks.
			if ( $css_path ) {

				$handle = ( 'view' === $context )
					? "{$block_editor_namespace}-{$foldername}-{$css_filetype}-style"
					: "{$block_editor_namespace}-{$foldername}-{$css_filetype}";

				$register_style = apply_filters( "enqueues_block_editor_register_style_{$type}_{$foldername}", true, $context, $handle );

				if ( $register_style && isset( $handle ) ) {
					$css_deps = apply_filters( "enqueues_block_editor_css_dependencies_{$type}_{$foldername}", [], $context, $handle );
					$css_ver  = apply_filters( "enqueues_block_editor_css_version_{$type}_{$foldername}", filemtime( "{$directory}{$css_path}" ), $context, $handle );

					wp_register_style( $handle, "{$directory_uri}{$css_path}", $css_deps, $css_ver );

					// Only enqueue for non-block types or if explicitly requested for blocks.
					// Static blocks are enqueued by Core, dynamic blocks are pre-enqueued separately.
					$should_enqueue_style = apply_filters( "enqueues_block_editor_enqueue_style_{$type}_{$foldername}", $enqueue_style, $context, $handle );

					if ( $should_enqueue_style ) {
						wp_enqueue_style( $handle );
					}
				}
			}

			// Enqueue JS bundle.
			$js_filetype = $this->get_filename_from_context( $context, 'js' );
			$js_path     = asset_find_file_path( "{$block_editor_dist_dir_path}/{$type}/{$foldername}", $js_filetype, 'js', $directory );

			if ( $js_path ) {

				$handle = ( 'view' === $context )
					? "{$block_editor_namespace}-{$foldername}-{$js_filetype}-script"
					: "{$foldername}-{$js_filetype}";

				$args = [ 
					'strategy'  => 'async',
					'in_footer' => true,
				];

				$args = apply_filters( "enqueues_block_editor_js_args_{$type}_{$foldername}", $args, $context, $handle );

				$enqueue_asset_path = "{$directory}/" . ltrim( str_replace( '.js', '.asset.php', $js_path ), '/' );
				$assets             = file_exists( $enqueue_asset_path ) ? include $enqueue_asset_path : [];

				$register_script = isset( $handle ) ? apply_filters( "enqueues_block_editor_js_register_script_{$type}_{$foldername}", true, $context, $handle ) : false;

				if ( $register_script ) {
					$js_deps = apply_filters( "enqueues_block_editor_js_dependencies_{$type}_{$foldername}", $assets['dependencies'] ?? [], $context, $handle );
					$js_ver  = apply_filters( "enqueues_block_editor_js_version_{$type}_{$foldername}", $assets['version'] ?? filemtime( "{$directory}{$js_path}" ), $context, $handle );

					wp_register_script( $handle, "{$directory_uri}{$js_path}", $js_deps, $js_ver, $args );

					$should_enqueue_script = apply_filters( "enqueues_block_editor_js_enqueue_script_{$type}_{$foldername}", $enqueue_script, $context, $handle );

					if ( $should_enqueue_script ) {
						wp_enqueue_script( $handle );
					}

					$localized_data     = apply_filters( "enqueues_block_editor_js_localized_data_{$type}_{$foldername}", [], $context, $handle );
					$localized_var_name = apply_filters( "enqueues_block_editor_js_localized_data_var_name_{$type}_{$foldername}", string_camelcaseify( "blockEditor {$type} {$foldername} Config" ), $context, $handle );

					if ( $localized_data ) {
						wp_localize_script( $handle, $localized_var_name, $localized_data );
					}
				}
			}
		}
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
