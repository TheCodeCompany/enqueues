<?php
/**
 * Application configuration manager.
 *
 * @package Enqueues
 */

// phpcs:disable WordPress.Files.FileName

namespace Enqueues\Base\Library;

/**
 * Application configuration manager.
 * Use like so, in a controller, model or view:
 *  $my_config = $this->config->get( 'my_config' );
 *  echo $my_config['some_value'];
 *  // Shorthand:
 *  echo $this->config->$this->config->get( 'my_config', 'some_value' );
 * All the application configuration is autoloaded from the /config/ and /config/local/ directories.
 */
class Config {

	/**
	 * The entire applications configuration.
	 * This contains all the application configs, by config name/slug. Like so:
	 * [
	 *   'my_config' => [
	 *     'key' => 'value',
	 *   ],
	 *   ...
	 * ]
	 *
	 * @var array
	 */
	protected $config = [];

	/**
	 * Application instance this config is for.
	 *
	 * @var \Enqueues\Base\Main\Application
	 */
	protected $app;

	/**
	 * Creates a new config instance for the given application
	 *
	 * @param \Enqueues\Base\Main\Application $app Application instance this config is for.
	 */
	public function __construct( \Enqueues\Base\Main\Application $app ) { // TODO pass name and directory so we could use this is in a theme or something.

		$this->app = $app;
	}

	/**
	 * Returns the given configuration item.
	 *
	 * @param string $name              Configuration file slug/name.
	 * @param string $key               Options item in the configuration item in the config file.
	 * @param mixed  $default_value The default item/value if none is found.
	 *
	 * @return array|mixed|string
	 */
	public function get( $name, $key = '', $default_value = [] ) {

		$return_value = $default_value;

		// Retrieve the single configuration array.
		$config_array = [];
		if ( isset( $this->config[ $name ] ) ) {
			$config_array = $this->config[ $name ];
		}

		// Return entire config array by default.
		$return_value = $config_array;

		// Return config item if key has been passed to us.
		if ( ! empty( $key ) ) {
			$config_scalar = isset( $config_array[ $key ] ) ? $config_array[ $key ] : $default_value;
			$return_value  = $config_scalar;
		}

		return $return_value;
	}

	/**
	 * Get the name/slug of the application.
	 *
	 * @return string
	 */
	public function get_app_name() {

		return $this->app->get_name();
	}

	/**
	 * Get the root directory of the application.
	 *
	 * @return string
	 */
	public function get_app_directory() {

		return $this->app->get_directory();
	}

	/**
	 * Get the root directory of the application.
	 *
	 * @return string
	 */
	public function get_app_directory_uri() {

		return $this->app->get_directory_uri();
	}

	/**
	 * Autoload the given application configuration from disk.
	 */
	public function autoload() {

		$app_config = [];
		$env_config = [];

		$dir = $this->app->get_directory();

		// Load the main configuration merging the default variables when required.
		$config_files = glob( "$dir/config/*.php" );

		foreach ( $config_files as $config_file ) {
			$config_name                = basename( $config_file, '.php' );
			$app_config[ $config_name ] = include $config_file;
		}

		// Select environment specific config files.
		$env_config_glob = "$dir/config/local/*.php";  // By default assume local dev.
		if ( defined( 'WP_ENV' ) ) {

			$env_config_glob = sprintf(
				'%s/config/%s/*.php',
				$dir,
				WP_ENV
			);

		}

		// Load each of the environment specific config files.
		$config_files = glob( $env_config_glob );
		foreach ( $config_files as $config_file ) {
			$config_name                = basename( $config_file, '.php' );
			$env_config[ $config_name ] = include $config_file;
		}

		// Merge the app configs with the environment specific overrides.
		$app_config_keys = array_keys( $app_config );
		$env_config_keys = array_keys( $env_config );
		$all_config_keys = array_merge( $app_config_keys, $env_config_keys );

		foreach ( $all_config_keys as $config_key ) {

			$app_config_value = isset( $app_config[ $config_key ] ) ? $app_config[ $config_key ] : [];
			$env_config_value = isset( $env_config[ $config_key ] ) ? $env_config[ $config_key ] : [];

			$this->config[ $config_key ] = array_replace_recursive( $app_config_value, $env_config_value );

		}
	}
}
