<?php
/**
 * Handles the enqueuing of jQuery in the footer for the theme.
 *
 * File Path: src/php/Controller/ThemeEnqueueJqueryController.php
 *
 * @package Enqueues
 */

namespace Enqueues\Controller;

use Enqueues\Base\Main\Controller;

/**
 * ThemeEnqueueJqueryController class.
 *
 * This controller manages the loading of jQuery in the footer of the theme. It hooks into
 * the WordPress enqueue system and moves the jQuery script from the header to the footer.
 */
class ThemeEnqueueJqueryController extends Controller {

	/**
	 * Boot the controller.
	 *
	 * This method sets up the necessary action hooks for moving jQuery to the footer.
	 *
	 * @return void
	 */
	public function set_up() {

		// Prevent duplicate initialization. There should only be once instance of this 
		// controllers features regardless of load context.
		if ( ! $this->initialize() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'move_jquery_to_footer' ], 100 );
	}

	/**
	 * Load jQuery in the footer.
	 *
	 * This method allows jQuery to be disabled, moved to the footer, and/or assigned
	 * an async/defer loading strategy by filters.
	 *
	 * @return void
	 */
	public function move_jquery_to_footer() {
		/**
		 * Filter to disable jQuery on the frontend.
		 *
		 * Allows developers to fully disable jQuery handles for pages that do not
		 * require it. Default false.
		 *
		 * @param bool $disable_jquery Whether to disable jQuery frontend scripts.
		 */
		$disable_jquery = apply_filters( 'enqueues_disable_jquery', false );

		if ( $disable_jquery ) {
			wp_dequeue_script( 'jquery' );
			wp_dequeue_script( 'jquery-core' );
			wp_dequeue_script( 'jquery-migrate' );

			wp_deregister_script( 'jquery' );
			wp_deregister_script( 'jquery-core' );
			wp_deregister_script( 'jquery-migrate' );

			return;
		}

		/**
		 * Filter to move jQuery to the footer.
		 *
		 * Allows developers to control whether jQuery should be moved to the footer or not.
		 *
		 * @param bool $move_jquery Whether to move jQuery to the footer. Default true.
		 */
		$move_jquery = apply_filters( 'enqueues_load_jquery_in_footer', true );

		if ( ! $move_jquery ) {
			// Continue to allow strategy filters even when footer placement is disabled.
		} else {
			wp_scripts()->add_data( 'jquery', 'group', 1 );
			wp_scripts()->add_data( 'jquery-core', 'group', 1 );
			wp_scripts()->add_data( 'jquery-migrate', 'group', 1 );
		}

		/**
		 * Filter to set a jQuery loading strategy.
		 *
		 * Supports 'defer' or 'async'. Any other value disables strategy changes.
		 * Default '' (no strategy override).
		 *
		 * @param string $strategy Desired loading strategy.
		 */
		$strategy = apply_filters( 'enqueues_jquery_loading_strategy', '' );

		if ( in_array( $strategy, [ 'defer', 'async' ], true ) ) {
			// 'jquery' is a WP alias (no src) — strategies must be set only on the concrete handles.
			// Passing strategy to the alias triggers a _doing_it_wrong notice in WP 6.3+.
			wp_scripts()->add_data( 'jquery-core', 'strategy', $strategy );
			wp_scripts()->add_data( 'jquery-migrate', 'strategy', $strategy );
		}
	}
}
