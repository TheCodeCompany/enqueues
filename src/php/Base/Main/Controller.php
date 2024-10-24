<?php
/**
 * Base controller class which all controllers should extend.
 *
 * @package Enqueues
 */

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
	 * Static array to track initialized controllers and prevent duplicates.
	 *
	 * @var array
	 */
	protected static $initialized_controllers = [];

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

	/**
	 * Check if the controller has been initialized.
	 *
	 * @return bool
	 */
	protected function is_initialized() {
		return isset( self::$initialized_controllers[ static::class ] );
	}

	/**
	 * Mark the controller as initialized.
	 *
	 * @return void
	 */
	protected function mark_as_initialized() {
		self::$initialized_controllers[ static::class ] = true;
	}

	/**
	 * Initialize the controller.
	 *
	 * This method should be called in child controllers before executing setup logic.
	 * It prevents the controller from being initialized more than once.
	 *
	 * @return bool True if the controller was already initialized, False otherwise.
	 */
	protected function initialize() {
		if ( $this->is_initialized() ) {
			return false;
		}

		$this->mark_as_initialized();
		return true;
	}
}
