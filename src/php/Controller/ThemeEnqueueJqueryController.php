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

		add_action( 'wp_enqueue_scripts', [ $this, 'move_jquery_to_footer' ], 100 );
	}

	/**
	 * Load jQuery in the footer.
	 *
	 * This method moves the jQuery script to the footer of the page. It checks a filter
	 * `enqueues_load_jquery_in_footer` to determine whether or not to move jQuery. If the filter
	 * returns `true`, jQuery is moved to the footer.
	 *
	 * @return void
	 */
	public function move_jquery_to_footer() {

		/**
		 * Filter to move jQuery to the footer.
		 *
		 * Allows developers to control whether jQuery should be moved to the footer or not.
		 *
		 * @param bool $move_jquery Whether to move jQuery to the footer. Default true.
		 */
		$move_jquery = apply_filters( 'enqueues_load_jquery_in_footer', true );

		if ( ! $move_jquery ) {
			return;
		}

		wp_scripts()->add_data( 'jquery', 'group', 1 );
		wp_scripts()->add_data( 'jquery-core', 'group', 1 );
		wp_scripts()->add_data( 'jquery-migrate', 'group', 1 );
	}
}
