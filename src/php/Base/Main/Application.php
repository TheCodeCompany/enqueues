<?php
/**
 * Framework application manager.
 *
 * @package Enqueues
 */

// phpcs:disable WordPress.Files.FileName

namespace Enqueues\Base\Main;

use Enqueues\Base\Library\Config;

/**
 * An application.
 * This is the core utility which manages an application which uses this framework.
 * An application should be defined like so:
 * ```
 * $app = new Application(
 *  'example',
 *  __DIR__,
 *  [
 *    new MyController(),
 *    ...
 *  ]
 * );
 * ```
 */
class Application {

	/**
	 * The slug/name of the application.
	 *
	 * @var string
	 */
	protected $name = '';

	/**
	 * The root directory of the application.
	 *
	 * @var string
	 */
	protected $directory = '';

	/**
	 * The root directory uri of the application.
	 *
	 * @var string
	 */
	protected $directory_uri = '';

	/**
	 * All the controllers defined in the application.
	 *
	 * @var array
	 */
	protected $controllers = [];

	/**
	 * The config helper instance.
	 *
	 * @var \Enqueues\Base\Library\Config
	 */
	protected $config = null;

	/**
	 * Constructor.
	 *
	 * @param string $name        The name/slug of the application.
	 * @param string $directory   The root directory of the application.
	 * @param array  $controllers All the applications controllers.
	 * @param int    $priority    The priority for this app.
	 */
	public function __construct( $name, $directory, $controllers, $priority = 10 ) {

		$this->name          = $name;
		$this->directory     = $directory;
		$this->directory_uri = plugins_url( basename( $directory ), $directory );
		$this->controllers   = $controllers;

		$this->load_config();

		add_action( 'plugins_loaded', [ $this, 'setup_controllers' ], $priority );
	}

	/**
	 * Set up the application controllers.
	 * This should be called on `plugins_loaded`
	 *
	 * @return void
	 */
	public function setup_controllers() {

		// Set config.
		foreach ( $this->controllers as $controller ) {

			/**
			 * Apply filters to the controller before we set the config.
			 */
			$controller = apply_filters( 'base_pre_controller_set_config', $controller );

			$controller->set_config_instance( $this->config );

			/**
			 * Apply filters to the controller after we set the config.
			 */
			$controller = apply_filters( 'base_post_controller_set_config', $controller );
		}

		// Set up each controller.
		foreach ( $this->controllers as $controller ) {

			/**
			 * Apply filters to the controller before we set it up.
			 */
			$controller = apply_filters( 'base_pre_controller_set_up', $controller );

			$controller->set_up();

			/**
			 * Apply filters to the controller after we set it up.
			 */
			$controller = apply_filters( 'base_post_controller_set_up', $controller );
		}
	}

	/**
	 * Get the application configuration object.
	 *
	 * @return Config
	 */
	public function get_config() {

		return $this->config;
	}

	/**
	 * Returns the root directory path of the application.
	 *
	 * @return string
	 */
	public function get_directory() {

		return $this->directory;
	}

	/**
	 * Returns the root directory uri of the application.
	 *
	 * @return string
	 */
	public function get_directory_uri() {

		return $this->directory_uri;
	}

	/**
	 * Returns the name/slug of the application.
	 *
	 * @return string
	 */
	public function get_name() {

		return $this->name;
	}

	/**
	 * Load the application config from file.
	 *
	 * @return void
	 */
	protected function load_config() {

		$this->config = new Config( $this );
		$this->config->autoload();
	}
}
