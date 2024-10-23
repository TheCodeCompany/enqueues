<?php
/**
 * Base controller class which all controllers should extend.
 *
 * @package Enqueues
 */

// phpcs:disable WordPress.Files.FileName

namespace Enqueues\Base\Main;

use Enqueues\Base\Library\Config;

/**
 * Base controller class which all controllers should extend.
 * Controllers should be registered with the framework like so:
 * ```
 * $app = new Application(
 *  'example',
 *  [
 *    new MyController(),
 *    ...
 *  ]
 * );
 * ```
 */
abstract class Controller {

	/**
	 * The config helper instance.
	 * This is automatically set when the controller is booted.
	 *
	 * @var Config
	 */
	protected $config = null;

	/**
	 * Called automatically at `plugins_loaded`.
	 * This must be overridden by child controllers.
	 *
	 * @return void
	 */
	abstract public function set_up();

	/**
	 * Get the Config instance.
	 *
	 * @return Config
	 */
	public function get_config() {
		return $this->config;
	}

	/**
	 * Set the Config manager instance for the controller.
	 *
	 * @param Config $config Config manager instance the controller should use.
	 *
	 * @return void
	 */
	public function set_config_instance( Config $config ) {

		$this->config = $config;

	}
}
