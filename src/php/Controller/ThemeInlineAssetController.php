<?php
/**
 * Manages the loading and inline rendering of theme assets.
 *
 * This controller is designed to handle the registration and rendering of theme assets, such as styles and scripts,
 * directly within the HTML document head and footer. It leverages WordPress actions to insert inline assets,
 * optimizing performance and reducing the need for additional HTTP requests.
 *
 * File Path: src/php/Controller/ThemeInlineAssetController.php
 *
 * @package Enqueues
 */

namespace Enqueues\Controller;

use Enqueues\Base\Main\Controller;
use function Enqueues\render_asset_inline;

/**
 * Manages the loading and inline rendering of theme assets.
 */
class ThemeInlineAssetController extends Controller {

	/**
	 * Initializes the asset loader functionality.
	 *
	 * @return void
	 */
	public function set_up() {

		add_action( 'wp_head', [ $this, 'asset_render_wp_head_inline_asset' ], PHP_INT_MAX );
		add_action( 'wp_footer', [ $this, 'asset_render_wp_footer_inline_asset' ], PHP_INT_MAX );
	}

	/**
	 * Hooks into wp_head to render inline assets registered via the enqueues_wp_head_inline_asset filter.
	 *
	 * Loops through assets added to the 'enqueues_wp_head_inline_asset' filter and renders them
	 * as inline styles or scripts in the head of the document. This approach allows for direct
	 * control over asset rendering and is an alternative to using WordPress's enqueuing system.
	 *
	 * IMPORTANT: This feature does not have dependency support, however it is rendered as late as possible.
	 *
	 * @return void
	 */
	public function asset_render_wp_head_inline_asset(): void {

		$assets = apply_filters( 'enqueues_wp_head_inline_asset', [] );

		// Bail early.
		if ( ! $assets ) {
			return;
		}

		foreach ( $assets as $asset ) {
			render_asset_inline( $asset );
		}
	}

	/**
	 * Hooks into wp_footer to render inline assets registered via the enqueues_wp_footer_inline_asset filter.
	 *
	 * Loops through assets added to the 'enqueues_wp_footer_inline_asset' filter and renders them
	 * as inline styles or scripts in the footer of the document. This approach allows for direct
	 * control over asset rendering and is an alternative to using WordPress's enqueuing system.
	 *
	 * IMPORTANT: This feature does not have dependency support, however it is rendered as late as possible.
	 *
	 * @return void
	 */
	public function asset_render_wp_footer_inline_asset(): void {

		$assets = apply_filters( 'enqueues_wp_footer_inline_asset', [] );

		// Bail early.
		if ( ! $assets ) {
			return;
		}

		foreach ( $assets as $asset ) {
			render_asset_inline( $asset );
		}
	}
}
