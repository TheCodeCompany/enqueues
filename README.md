# Enqueues MU Plugin
## Overview
The Enqueues MU Plugin automates the process of loading CSS and JavaScript assets based on WordPress page types, custom page templates, and custom post types. It also provides utilities for dynamically managing Webpack entry points for theme assets like JS and SCSS.

## Features
* Automatic Asset Loading: Automatically enqueues CSS and JS files based on the page type, custom templates, and custom post types.
* Fallback Mechanism: If no specific file is found for a page type, the plugin falls back to default assets (e.g., `main.css` and `main.js`).
* Inline Asset Support: Supports rendering critical CSS/JS assets inline in wp_head or wp_footer.
* Webpack Utility: Includes a utility for dynamically generating Webpack entry points for your theme’s JavaScript and SCSS files.

## Directory Structure

```
mu-plugins/
└── enqueues/
    ├── build-tools/
    │   └── vendor/
    ├── src/
    │   ├── php/
    │   │   ├── Function/
    │   │   │   ├── Assets.php
    │   │   │   └── PageType.php
    │   │   ├── ThemeEnqueueMainController.php
    │   │   └── ThemeEnqueueJqueryController.php
    │   └── js/
    │       ├── enqueues-merge-theme-webpack-entries.js
    │       └── enqueues-theme-webpack-entries.js
    └── enqueues-bootstrap.php
```

## Installation
1. Place the Plugin in the MU-Plugins Directory:
    * Copy the enqueues directory to .
	* Add a bootsrap file to automaticaly load the plugin. This is necessary until this plugin has been moved to a package.
		```php
		$data_layer_loader_file = __DIR__ . '/enqueues/enqueues-bootstrap.php';

		if ( file_exists( $data_layer_loader_file ) ) {
			require_once $data_layer_loader_file;
		}
		```
    * The plugin will be automatically loaded by WordPress as it resides in the mu-plugins directory.

1. Composer:
    * Ensure the `vendor` directory is present and included in your repository, as the plugin depends on Composer packages.

## Usage
### Automatic Asset Loading
The plugin automatically detects the page type and loads the corresponding assets (CSS and JS) from the dist directory:
    * For page type matching, the plugin looks for assets like:
        - Homepage: homepage.css or homepage.js
        - Single Post: single.css or single.js
        - Archive Pages: archive.css or archive.js
        - And more...
    * For custom post types, it will load:
        - single-{post_type}.css or single-{post_type}.js if available.
    * For custom page templates, it scans for:
        - Template-specific assets (e.g., template-example.css and template-example.js).
    * If no specific asset is found, it will fall back to main.css and main.js.

### Webpack Utility for Assets
The Webpack utility dynamically generates entry points based on your theme's JavaScript and SCSS files. When merging additional entries, always use the enqueuesMergeThemeWebpackEntries function to combine dynamically generated and custom entries to prevent conflict errors.

### Example Usage in Webpack Config

```javascript
// Using CommonJS require
const enqueuesThemeWebpackEntries = require('../../../path/to/mu-plugins/enqueues/src/js/enqueues-theme-webpack-entries');
const enqueuesMergeThemeWebpackEntries = require('../../../path/to/mu-plugins/enqueues/src/js/enqueues-merge-theme-webpack-entries');
const path = require('path');
const glob = require('glob');

// OR using ES6 import
import enqueuesThemeWebpackEntries from '../../../path/to/mu-plugins/enqueues/src/js/enqueues-theme-webpack-entries';
import enqueuesMergeThemeWebpackEntries from '../../../path/to/mu-plugins/enqueues/src/js/enqueues-merge-theme-webpack-entries';
import path from 'path';
const glob = require('glob');


// Example of custom entries
const customEntries = {
    slick: ['./src/js/library/slick.js'],
};

module.exports = {
    entry: enqueuesMergeThemeWebpackEntries(
        enqueuesThemeWebpackEntries(path.resolve(__dirname), path, glob, 'src/js', 'src/scss'),
        customEntries,
    ),
    output: {
        filename: '[name].js',
        path: path.resolve(__dirname, 'dist'),
    },
};
```

#### Explanation:
1. enqueuesThemeWebpackEntries(): This function dynamically finds and groups all relevant JavaScript and SCSS files from the specified directories ('source/js' and 'source/sass' in this example).
1. enqueuesMergeThemeWebpackEntries(): This function merges the dynamically generated entries from enqueuesThemeWebpackEntries() with custom entries (e.g., slick: ['./src/js/library/slick.js']). Always use this function to merge entries.
1. Custom Entries: You can define your custom entries (e.g., for third-party libraries or custom logic) in the customEntries object, which will then be merged with the auto-generated entries.

#### Important Notes:
* You must always use enqueuesMergeThemeWebpackEntries() to merge dynamic and custom entries to ensure both sets of entries are handled correctly.
* This approach works with both JavaScript and SCSS (or CSS) entries, combining everything into the final Webpack build configuration.

## Available Filters
The Enqueues MU Plugin provides several filters to customize its behavior, allowing you to fine-tune asset loading, directories, and other settings:
* `enqueues_move_jquery_to_footer`: Controls whether jQuery should be moved to the footer. Default: true.
* `enqueues_theme_default_enqueue_asset_filename`: Customize the default asset filename when no specific file is found. Default: 'main'.
* `enqueues_theme_allowed_page_types_and_templates`: Modify the array of allowed page types and templates for automatic asset loading. Default: array() (empty array).
* `enqueues_theme_css_src_dir`: Customize the directory path for CSS files relative to the theme root. Default: 'dist/css'.
* `enqueues_theme_js_src_dir`: Customize the directory path for JS files relative to the theme root. Default: 'dist/js'.
* `enqueues_render_css_inline`: Controls whether CSS assets should be rendered inline. Default: false.
* `enqueues_render_css_inline_{$css_handle}`: Controls whether CSS assets for a specific handle should be rendered inline. Default: value of `enqueues_render_css_inline` filter which is default false.
* `enqueues_render_js_inline`: Controls whether JS assets should be rendered inline. Default: false.
* `enqueues_render_js_inline_{$js_handle}`: Controls whether JS assets for a specific handle should be rendered inline. Default: value of `enqueues_render_js_inline` filter which is default false.
* `enqueues_asset_theme_src_directory`: Customize the source directory path for SCSS, CSS, and JS files relative to the theme root. Default: 'src'.
* `enqueues_asset_theme_dist_directory`: Customize the distribution directory path for compiled CSS and JS files relative to the theme root. Default: 'dist'.
* `enqueues_asset_theme_js_extension`: Change the file extension used for JavaScript files. Default: 'js'.
* `enqueues_asset_theme_css_extension`: Change the file extension used for SCSS/SASS/CSS files. Default: 'scss'.
* `enqueues_js_config_data_{$js_handle}`: Filter the JS config name for the given JS handle.
* `enqueues_js_config_name_{$js_handle}`: Filter the JS config data for the given JS handle.
* `enqueues_is_cache_enabled`: Controls whether caching is enabled for asset loading. By default, caching is enabled if the `ENQUEUES_CACHE_ENABLED` constant is set to `true`. This filter allows for runtime control of the caching mechanism.
* `enqueues_cache_ttl`: Modifies the time-to-live (TTL) for cached entries. By default, the TTL is set to 1 day (`DAY_IN_SECONDS`), but you can customize this via the `ENQUEUES_CACHE_TTL` constant or this filter.

## Default Behavior
* Default Assets: The plugin will default to main.css and main.js if no specific assets are found for a page type or template.
* jQuery: By default, jQuery is moved to the footer to enhance performance (see filter to change this global or per page).

## Extending the Plugin
You can also extend the plugin by adding custom filters to change the source directories, asset extensions, or inline behavior based on specific project needs.

## Troubleshooting
**Missing Assets:** If an asset is missing in a local development environment, the plugin will display a error. Ensure that you have run your build tools (e.g., npm, Webpack) to generate the necessary assets. If you dont see an error, make sure you have the is_local() function setup.

**Debugging:** During development, the plugin logs the generated entries to the console to assist with debugging. Make sure process.env.NODE_ENV is set appropriately.

## Contributing
If you find a bug or have a feature request, please open an issue or submit a pull request.

## Caching and Uniqueness in Enqueues MU Plugin
The Enqueues MU Plugin incorporates caching to improve performance by reducing filesystem operations, which can be expensive, especially in large WordPress installations with many assets. This section explains how caching is applied, its purpose, the uniqueness of cache keys, and how the caching approach does not interfere with development workflows (e.g., Webpack usage).

### How Caching Works

#### Cache Utility Functions
The Enqueues MU Plugin includes caching features to improve performance by reducing the number of filesystem operations required for asset loading, primarily for template lists and asset metadata. However, individual asset files (like `main.css` or `main.js`) are always checked for updates using `filemtime()` to ensure the latest version is served.

* `EnqueueAssets::get_theme_template_files()`:
    - Caches the list of theme template files found in the theme’s root directory and subdirectories. This avoids scanning the filesystem for template files on every request.
    - The cached template list is stored for 24 hours.

* `EnqueueAssets::et_enqueue_asset_files()`:
    - Caches the list of found asset files corresponding to known page types and templates. This reduces repeated file checks for known assets.
    - The cache is refreshed every 24 hours or when assets are modified.

**Example: Enabling or Disabling Caching Using Filters**
You can enable or disable caching based on specific conditions, such as the environment, using the enqueues_is_cache_enabled filter. For instance, you can disable caching on a local development environment:

```php
add_filter( 'enqueues_is_cache_enabled', function( $is_cache_enabled ) {
    return defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV ? false : $is_cache_enabled;
} );
```

**Constants:**
* `ENQUEUES_CACHE_ENABLED`: Controls whether caching is enabled globally. If set to `true`, caching will be enabled. If omitted or set to `false`, caching will be disabled. The default behavior can be overridden via the `enqueues_is_cache_enabled` filter.
* `ENQUEUES_CACHE_TTL`: Sets the time-to-live (TTL) for cached entries in seconds. If not set, the TTL defaults to `DAY_IN_SECONDS` (24 hours). This can also be customized via the `enqueues_cache_ttl` filter.

**Example: Setting Cache Constants in wp-config.php**
To enable caching and set the TTL globally, add the following constants to your wp-config.php file:

```php
define( 'ENQUEUES_CACHE_ENABLED', true ); // Enables caching.
define( 'ENQUEUES_CACHE_TTL', 3600 );     // Sets cache TTL to 1 hour (3600 seconds).
```

#### Uniqueness of Cache Keys:
* Cache keys are generated using md5() hashes of the asset’s relative path, file name, and extension, ensuring that each asset gets a unique cache entry.
* This prevents collisions between assets with similar names in different directories or with different file extensions (e.g., main.js vs main.css).

#### Cache Expiration:
* Cached entries for template files and asset lists are stored for 24 hours. After this period, the cache is automatically invalidated, and new filesystem lookups are performed.
* Individual asset files like `main.css` or `main.js` are always checked directly for updates using `filemtime()`, ensuring that any changes to assets are immediately reflected without relying on cache.

### Why Caching Is Important
Without caching, each request to load assets (CSS/JS) would require multiple file existence checks using `file_exists()` and `filemtime()`. These operations can be slow, particularly when dealing with many assets across different templates and post types. By caching the results of these checks, we reduce the number of I/O operations and speed up the page load time.

#### Impact on Development with Webpack
**Filemtime and Cache Busting**
During development, you typically run Webpack to compile and bundle your assets. The Enqueues MU Plugin uses `filemtime()` to generate the version number for each asset based on its last modified time. This ensures that when you recompile your assets, the plugin automatically updates the cache with the new version (because `filemtime()` changes).

**Why This Doesn’t Affect Development:**

* File Modification Time: Even with caching in place, the plugin checks the modification time of the asset (`filemtime()`) to ensure that any changes made during development are reflected in the browser. The cache automatically invalidates when the modification time changes.
* Caching is not applied to individual asset files (e.g., CSS or JS files), so their modification time is always checked to ensure updates are immediately available without relying on cache.
* Local Development: If the environment is local (detected by `is_local()`), the plugin prioritizes the non-minified versions of the files and checks the filesystem directly without relying solely on cache.
* Cache Busting: Since the version of the file is tied to its modification time, changes to assets during development will force the browser to fetch the updated version, bypassing the browser cache.

### Functions That Utilize Caching
* `get_theme_template_files()`:
	- Caches the list of theme template files found in the theme’s root directory and subdirectories. This avoids scanning the filesystem for template files on every request.
	- The cached template list is stored for 24 hours.

* `get_enqueue_asset_files()`:
	- Caches the list of found asset files corresponding to known page types and templates. This reduces repeated file checks for known assets.
	- The cache is refreshed every 24 hours or when assets are modified.

### Cache Conclusion
Caching in the Enqueues MU Plugin is designed to improve performance without sacrificing the developer experience. By caching asset file paths and data while using `filemtime()` to check for updates, the plugin ensures that developers always work with the most up-to-date assets, even during active development with Webpack. This approach minimizes filesystem operations, improving the efficiency of asset loading in production while maintaining flexibility during development.

## Additional Asset Loading
In addition to automatic asset loading for page types, custom templates, and post types, the Enqueues MU Plugin provides utility functions that can be used anywhere in your theme to load additional CSS or JavaScript files dynamically. These functions are located in `assets/src/php/Function/Assets.php` and offer an easy way to manage custom assets based on your environment.

### How to Use the Asset Loading Functions
The plugin offers two key functions for asset loading:

1. `asset_find_file_path()`: Finds the correct file path for an asset (either minified or standard) based on your environment.
1. `display_maybe_missing_local_warning()`: Displays a warning if an expected asset is missing during local development.

### Example Usage: Enqueuing a Custom JavaScript or CSS File
```php
$directory     = get_template_directory();
$directory_uri = get_template_directory_uri();

// Specify the asset file name (without the extension) and type (either 'js' or 'css').
$asset_filename = 'custom_asset';
$asset_type     = 'js'; // Could be 'css' for stylesheets.

// Find the asset file path dynamically, checking for the correct version (minified or not).
$asset_path = asset_find_file_path( "/dist/{$asset_type}", $asset_filename, $asset_type, $directory );

// Display a warning in local development if the asset is missing.
display_maybe_missing_local_warning( $asset_path, "Run npm build to generate the \"{$asset_filename}\" file." );

// If the asset exists, enqueue it in WordPress.
if ( $asset_path ) {
    if ( 'js' === $asset_type ) {
        wp_enqueue_script(
            $asset_filename, // Unique handle for the script.
            $directory_uri . $asset_path, // Full URL to the asset.
            [], // Optional dependencies (empty array means no dependencies).
            filemtime( $directory . $asset_path ), // Versioning based on the file modification time.
            true // Load the script in the footer.
        );
    } elseif ( 'css' === $asset_type ) {
        wp_enqueue_style(
            $asset_filename, // Unique handle for the style.
            $directory_uri . $asset_path, // Full URL to the asset.
            [], // Optional dependencies.
            filemtime( $directory . $asset_path ) // Versioning based on the file modification time.
        );
    }
}
```

### Explanation of Functions
* `asset_find_file_path()`:
	- This function dynamically locates the correct asset file, checking both minified and non-minified versions based on the environment (production or development).
	- It simplifies path management by allowing you to specify just the asset type (e.g., `js` or `css`) and the file name.
* `display_maybe_missing_local_warning()`:
	- During local development, this function will output a helpful error message if the expected asset file is missing, reminding developers to run build processes like npm build.
* Versioning with `filemtime()`:
	- The `filemtime()` function is used for versioning assets by appending a timestamp of the file's last modification. This ensures that browsers always load the latest version of the asset, bypassing cache issues.

### Benefits
Using these functions provides several key advantages:
* Environment-aware asset loading: Automatically loads the correct version of the asset based on whether you're in development (non-minified) or production (minified).
* Improved performance: By using `filemtime()` for versioning, browser caches are correctly invalidated, ensuring users receive the latest version of the asset.
* Error handling: Developers receive immediate feedback during development if an asset is missing, making the development process more efficient.

### Additional Asset Loading Conclusion
For any additional asset loading needs, these functions can be used throughout your theme. They simplify the process of enqueuing custom assets dynamically while ensuring that the correct version is loaded based on your environment.