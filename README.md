# Enqueues MU Plugin
## Overview
The Enqueues MU Plugin automates the process of loading CSS and JavaScript assets based on WordPress page types, custom page templates, and custom post types. It also provides utilities for dynamically managing Webpack entry points for theme assets like JS and SCSS.

## Features
* Automatic Asset Loading: Automatically enqueues CSS and JS files based on the page type, custom templates, and custom post types.
* Fallback Mechanism: If no specific file is found for a page type, the plugin falls back to default assets (e.g., `main.css` and `main.js`).
* Inline Asset Support: Supports rendering critical CSS/JS assets inline in wp_head or wp_footer.
* Webpack Utility: Includes a utility for dynamically generating Webpack entry points for your theme’s JavaScript and SCSS files.
* Inline Asset Registration: Easily register critical CSS and JS to be rendered inline within the `wp_head` or `wp_footer` via provided functions.
* Block Editor Integration: Offers hooks and features to manage block categories, enqueue block assets, and register custom blocks.

## Directory Structure

```
enqueues/
    ├── composer.json
    ├── composer.lock
    ├── enqueues-bootstrap.php
    ├── src/
    │   ├── js/
    │   │   ├── enqueues-merge-theme-webpack-entries.js
    │   │   └── enqueues-theme-webpack-entries.js
    │   ├── php/
    │   │   ├── Controller/
    │   │   │   ├── ThemeEnqueueJqueryController.php
    │   │   │   └── ThemeEnqueueMainController.php
    │   │   ├── Function/
    │   │   │   ├── Assets.php
    │   │   │   ├── AutoLoad.php
    │   │   │   ├── Cache.php
    │   │   │   ├── Env.php
    │   │   │   ├── PageType.php
    │   │   │   └── String.php
    │   │   └── Library/
    │   │       └── EnqueueAssets.php
    └── vendor/
```

## Installation

### Using Composer
1. Add the repository to your project's `composer.json` file:
    ```json
    {
      "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/TheCodeCompany/enqueues.git"
        }
      ],
      "require": {
        "enqueues/core": "dev-main"
      }
    }
    ```
1. Run `composer install` to pull in the package.
1. Ensure that `autoload.php` is being included in your project to automatically load the plugin:
    ```php
    require_once __DIR__ . '/vendor/autoload.php';
    ```

## Usage
### Controller Initialization with Context
The Enqueues plugin can be integrated into various parts of a WordPress site, such as themes or other plugins, by initializing its controllers conditionally, based on the context in which the package is loaded. This feature prevents conflicts in environments where the Enqueues package may be loaded multiple times (e.g., when included in both a theme and a plugin).

To support context-specific loading of the Enqueues controllers, the package offers flexibility through filters that allow you to control which controllers are initialized depending on the context.

Example: Initializing Controllers with Context
In your project, you can control which Enqueues controllers should be loaded by calling `enqueues_initialize_controllers()` and passing a context string to specify which controllers to load. For example, you might call this function within the `plugins_loaded` action.

Typically, when using WPMVC, this would be called directly within the `set_up()` method.

#### Example: Initializing Controllers with Context
In your project, you can control which Enqueues controllers should be loaded by calling enqueues_initialize_controllers() and passing a context string to specify which controllers to load. For example, you might call this function within the plugins_loaded action.

Typicaly when using WPMVC this would be used directly within the `set_up()` method.
```php
add_action( 'plugins_loaded', function() {
    // Initialize Enqueues in a specific context, e.g., 'infinite_scroll' vs 'default'.
    \Enqueues\enqueues_initialize_controllers( 'infinite_scroll' );
    \Enqueues\enqueues_initialize_controllers(); // Default context.
}, 10 );
```

#### Explanation:
* **Context-Sensitive Loading:** Context-Sensitive Loading: The function enqueues_initialize_controllers() accepts a context parameter (default is 'default') and uses the enqueues_load_controller filter to decide whether each controller should be initialized based on the provided context.
* **Customization via Filters:** By using the enqueues_load_controller filter, you can programmatically control which controllers to load for a given context. This allows you to disable certain features or controllers when Enqueues is used as a dependency within a larger package like Infinite Scroll. To do this properly place the filter before `enqueues_initialize_controllers()`.

#### Example: Disabling Controllers for a Specific Context
You can disable certain controllers for a given context by hooking into the `enqueues_load_controller` filter, returning `false` for the controllers you don't want to load. This is only necessary if `enqueues_initialize_controllers()` has initialized the controllers. Controllers that should only be initialized once already have protection against multiple initialization.

```php
add_filter( 'enqueues_load_controller', function( $load, $controller, $context ) {
    // Disable all controllers when the context is 'infinite_scroll'.
    if ( 'infinite_scroll' === $context ) {
        return false;
    }

    // Disable a specific controller in the 'infinite_scroll' context.
    if ( 'infinite_scroll' === $context && 'ThemeEnqueueJqueryController' === $controller ) {
        return false; // Disable jQuery controller for Infinite Scroll.
    }

    // Default behavior: return true to load the controller.
    return $load;
}, 10, 3 );
enqueues_initialize_controllers( 'theme' );
```

This approach allows you to conditionally load only the necessary Enqueues controllers based on the context in which the package is being used. This flexibility ensures that you can avoid conflicts or redundant loading of the same controllers in different parts of your application.

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

### Webpack Config

### Example Usage of Entries in Webpack Config
The following is within `/themes/<theme name>/build-tools/webpack.config.babel.js`.

```javascript
import path from 'path';
const glob = require('glob');
import webpack from 'webpack';
import MiniCssExtractPlugin from 'mini-css-extract-plugin';
import TerserPlugin from 'terser-webpack-plugin';
import CssMinimizerPlugin from 'css-minimizer-webpack-plugin';
import { CleanWebpackPlugin } from 'clean-webpack-plugin';
import DependencyExtractionWebpackPlugin from '@wordpress/dependency-extraction-webpack-plugin';
import CopyWebpackPlugin from 'copy-webpack-plugin'

// Enqueues specific helper fucntions.
import enqueuesMergeWebpackEntries from '../../../mu-plugins/sciencealert/build-tools/vendor/thecodeco/enqueues/src/js/enqueues-merge-webpack-entries.js';
import enqueuesThemeWebpackEntries from '../../../mu-plugins/sciencealert/build-tools/vendor/thecodeco/enqueues/src/js/enqueues-theme-webpack-entries.js';
import enqueuesBlockEditorWebpackEntries from '../../../mu-plugins/sciencealert/build-tools/vendor/thecodeco/enqueues/src/js/enqueues-block-editor-webpack-entries.js';
import enqueuesGetCopyPluginConfigPattern from '../../../mu-plugins/sciencealert/build-tools/vendor/thecodeco/enqueues/src/js/enqueues-copy-plugin-config-pattern.js';

// Detecting Dev Mode
const devMode = process.env.NODE_ENV !== 'production';

// Directory locations.
const rootDir = path.resolve(__dirname, '..'); // Project root.
const buildDir = path.resolve(rootDir, 'build-tools'); // The build tools.
const distDir = path.resolve(rootDir, 'dist'); // The compiled distribution assets.
const nodeModulesDir = path.resolve(rootDir, 'build-tools/node_modules'); // The node module dir.

console.log('Root directory:', rootDir);
console.log('Build directory:', buildDir);
console.log('Dist directory:', distDir);
console.log('Node Modules directory:', nodeModulesDir);

// All directories located within the /src/block-editor/ and /dist/block-editor/ which matches registration.
const blockeditorDirectories = {
	'blocks/': 'blocks',
	'plugins/': 'plugins',
	'extensions/': 'extensions',
};

const cssPlugin = new MiniCssExtractPlugin({
    filename: ({ chunk }) => {
		if (Object.keys(blockeditorDirectories).some(key => chunk.name.startsWith(key))) {
			if (['/index', '/style', '/view', '/editor'].some(suffix => chunk.name.includes(suffix))) {
				return `block-editor/${chunk.name}.css`;
			} else {
				// Default to `index.css` if no specific suffix is found
				return `block-editor/${chunk.name}/index.css`;
			}
		} else {
			const baseName = path.basename(chunk.name);
			return devMode ? `css/${baseName}.css` : `css/${baseName}.min.css`;
		}
	},
});
/**
 * BlockEditor files and folders to remove, which is created due to MiniCssExtractPlugin.
 *
 * Webpack generating empty JavaScript files for CSS-only entries. In Webpack, when you define an entry
 * point that points to a CSS file (or SASS/LESS file which ultimately compiles down to CSS), Webpack
 * still generates a JS file, even though you might not have any actual JavaScript code associated with that entry.
 *
 * Add the paths you want to remove after the build here.
 */
const cleanAfterEveryBuildPatterns = Object.values(blockeditorDirectories).flatMap((type) => [
	`block-editor/${type}/**/style`,
	`block-editor/${type}/**/editor`,
]);

// Keeping it clean and fresh, clean the dist dir before build and configuration to clean specific folders after building.
const cleanWebpackPlugin = new CleanWebpackPlugin({
	dry: false,
	cleanOnceBeforeBuildPatterns: ['**/*'], // Clean all files in dist folder.
	dangerouslyAllowCleanPatternsOutsideProject: true, // Dist dir is one step outside the project root.
	cleanAfterEveryBuildPatterns,
});

// Minify JS.
const optimizeJS = new TerserPlugin({
	terserOptions: {
		format: {
			comments: devMode, // Ensure comments are removed
		},
	},
	extractComments: devMode, // Prevents extracting comments to a separate file
});

// Minify CSS.
const optimizeCss = new CssMinimizerPlugin({
	minimizerOptions: {
		preset: [
			'default',
			{
				discardComments: { removeAll: ! devMode }, // Explicitly remove all comments
			},
		],
	},
});

// Added Jquery declaration into Webpack.
const jquery = new webpack.ProvidePlugin({
	$: 'jquery',
	jQuery: 'jquery',
	'window.jQuery': 'jquery',
});

// Copy Plugin Configuration.
const copyPlugin = new CopyWebpackPlugin({
	patterns: [
		enqueuesGetCopyPluginConfigPattern(rootDir, distDir, 'images'), // Copy all images from src to dist dir, all usage should point to images in the dist dir not src, src images should be excluded from the deploy.
		enqueuesGetCopyPluginConfigPattern(rootDir, distDir, 'fonts'), // Copy all fonts from src to dist dir, all usage should point to fonts in the dist dir not src, src fonts should be excluded from the deploy.
		enqueuesGetCopyPluginConfigPattern(rootDir, distDir, 'block-json'), // Copy the block.json file from the src dir to the dist dir for block registration.
		enqueuesGetCopyPluginConfigPattern(rootDir, distDir, 'render-php'), // Copy the render.php file from the src dir to the dist dir for block rendering.
	],
});

const plugins = [
	cleanWebpackPlugin,
	jquery,
	cssPlugin,
	copyPlugin,
	new DependencyExtractionWebpackPlugin(),
	new webpack.WatchIgnorePlugin({ paths: [distDir, buildDir] }),
];

const entries = enqueuesMergeWebpackEntries(
	enqueuesThemeWebpackEntries( rootDir, path, glob ),
	enqueuesBlockEditorWebpackEntries( rootDir, path, glob ),
);

export default {
	mode: devMode ? 'development' : 'production',
	stats: devMode ? 'verbose' : 'normal',
	context: rootDir,
	resolve: {
		modules: [nodeModulesDir, 'node_modules']
	},
	resolveLoader: {
		modules: [nodeModulesDir, 'node_modules'],
	},
	devtool: devMode ? 'source-map' : false,
	watchOptions: {
		aggregateTimeout: 500,
		poll: 1000,
		ignored: [`${distDir}/**`, `${buildDir}/**`],
	},
	entry: entries,
	output: {
        path: distDir,
		clean: true,
		filename: ({ chunk }) => {
			if (Object.keys(blockeditorDirectories).some(key => chunk.name.startsWith(key))) {
				if (['/index', '/script', '/view', '/editor'].some(suffix => chunk.name.includes(suffix))) {
					return `block-editor/${chunk.name}.js`;
				} else {
					return `block-editor/${chunk.name}/index.js`;
				}
			} else {
				const baseName = path.basename(chunk.name);
				return devMode ? `js/${baseName}.js` : `js/${baseName}.min.js`;
			}
		},
	},
	module: {
		rules: [
			{
				test: /\.(js|jsx)$/,
				exclude: /node_modules/,
				loader: 'babel-loader',
				options: {
					configFile: path.resolve(buildDir, '.babelrc'),
				},
			},
			{
				test: /\.(sa|sc|c)ss$/,
				use: [
					MiniCssExtractPlugin.loader,
					'css-loader',
					{
						loader: 'postcss-loader',
						options: {
							postcssOptions: {
								config: path.resolve(buildDir, 'postcss.config.js'),
							},
						},
					},
					{
						loader: 'sass-loader',
						options: {
							implementation: require('node-sass'),
						},
					},
				],
			},
			{
				test: /\.(woff(2)?|ttf|eot)$/, // New way to add fonts for Webpack 5.
				type: 'asset/resource',
				generator: {
					filename: './fonts/[name][ext]',
				},
			},
			{
				test: /\.(jpe?g|png|gif|ico|svg)$/i,
				type: 'asset/resource',
				generator: {
					filename: (pathData) => {
						const filePath = pathData.filename.replace('src/images/', '');
						return `images/${filePath}`;
					},
				},
			},
		],
	},
	externals: {
		jquery: 'jQuery'
	},
	optimization: {
		minimize: true,
		minimizer: [optimizeJS, optimizeCss],
	},
	plugins,
};
```

The following explains the individual functionality.

### Webpack Utility for Assets
The Webpack utility dynamically generates entry points based on your theme's JavaScript and SCSS files. When merging additional entries, always use the enqueuesMergeThemeWebpackEntries function to combine dynamically generated and custom entries to prevent conflict errors.

### Example Usage of Entries in Webpack Config

```javascript
// Using CommonJS require
import path from 'path';
const glob = require('glob');

import enqueuesMergeWebpackEntries from '../../../mu-plugins/sciencealert/build-tools/vendor/thecodeco/enqueues/src/js/enqueues-merge-webpack-entries.js';
import enqueuesThemeWebpackEntries from '../../../mu-plugins/sciencealert/build-tools/vendor/thecodeco/enqueues/src/js/enqueues-theme-webpack-entries.js';
import enqueuesBlockEditorWebpackEntries from '../../../mu-plugins/sciencealert/build-tools/vendor/thecodeco/enqueues/src/js/enqueues-block-editor-webpack-entries.js';

// Detecting Dev Mode
const devMode = process.env.NODE_ENV !== 'production';

// Directory locations.
const rootDir = path.resolve(__dirname, '..'); // Project root.
const buildDir = path.resolve(rootDir, 'build-tools'); // The build tools.
const distDir = path.resolve(rootDir, 'dist'); // The compiled distribution assets.
const nodeModulesDir = path.resolve(rootDir, 'build-tools/node_modules'); // The node module dir.

// Custom entries (e.g., third-party libraries or other entries not in the root directory of the src js dir).
const customEntries = {
    slick: ['./src/js/library/slick.js'],
};

const entries = enqueuesMergeWebpackEntries(
	enqueuesThemeWebpackEntries( rootDir, path, glob ),
	enqueuesBlockEditorWebpackEntries( rootDir, path, glob ),
	...customEntries,
);

module.exports = {
    entry: entries,
    // Other configurations...
};
```

#### Explanation:
1. enqueuesThemeWebpackEntries(): This function dynamically finds and groups all relevant JavaScript and SCSS files from the specified directories ('source/js' and 'source/sass' in this example).
1. enqueuesMergeThemeWebpackEntries(): This function merges the dynamically generated entries from enqueuesThemeWebpackEntries() with custom entries (e.g., slick: ['./src/js/library/slick.js']). Always use this function to merge entries.
1. Custom Entries: You can define your custom entries (e.g., for third-party libraries or custom logic) in the customEntries object, which will then be merged with the auto-generated entries.

#### Important Notes:
* You must always use enqueuesMergeThemeWebpackEntries() to merge dynamic and custom entries to ensure both sets of entries are handled correctly.
* This approach works with both JavaScript and SCSS (or CSS) entries, combining everything into the final Webpack build configuration.

### Example Usage of `enqueuesGetCopyPluginConfigPattern` in Webpack Config
To use the CopyPlugin patterns, import the enqueuesGetCopyPluginConfigPattern and define the patterns for different asset types like images and fonts. Here’s an example:

```javascript
// Importing the CopyPlugin config pattern utility.
import enqueuesGetCopyPluginConfigPattern from '../../../path/to/enqueues/src/js/enqueues-copy-plugin-config-pattern.js';

// Copy Plugin Configuration.
const CopyWebpackPlugin = require('copy-webpack-plugin');
const copyPlugin = new CopyWebpackPlugin({
    patterns: [
		
        // Pattern for images if it uses the default src/images directory
        enqueuesGetCopyPluginConfigPattern(rootDir, distDir, 'images'),

        // Pattern for images if its different from default src/images e.g. source
        enqueuesGetCopyPluginConfigPattern(rootDir, distDir, 'images', '**/source/images/**/*'),
		
        // Pattern for fonts if it uses the default src/fonts directory
        enqueuesGetCopyPluginConfigPattern(rootDir, distDir, 'fonts'),

        // Pattern for fonts if its different from default src/fonts e.g. source
        enqueuesGetCopyPluginConfigPattern(rootDir, distDir, 'fonts', '**/source/fonts/**/*'),
        
        // You can add additional patterns here for other contexts, e.g., block-json, render-php
    ],
});

module.exports = {
    plugins: [copyPlugin],
};
```

#### Explanation:
1. `enqueuesGetCopyPluginConfigPattern()`: This function generates patterns for CopyPlugin based on the specified context (e.g., images, fonts, etc.). It supports various contexts, including block-json and render-php.
1. The example demonstrates how to use the CopyPlugin along with enqueuesGetCopyPluginConfigPattern() to manage image and font files, with the ability to extend for other file types.

##### Known Contexts for enqueuesGetCopyPluginConfigPattern
The `enqueuesGetCopyPluginConfigPattern` function supports the following contexts:

* 'images': Copies image files from src/images to the destination directory.
* 'fonts': Copies font files from src/fonts to the destination directory.
* 'block-json': Copies Gutenberg block JSON configuration files.
* 'render-php': Copies Gutenberg render PHP files.
Each pattern has a default from path which can be overridden as needed.

### Inline Asset Registration
The plugin allows you to register critical CSS or JS assets to be rendered directly within the `wp_head` or `wp_footer` tags using the following functions:

#### `add_inline_asset_to_wp_head()`
Registers an inline asset to be rendered in the head of the document. Useful for critical CSS or JS.

**Parameters**:
- `$type`: Asset type ('style' or 'script').
- `$handle`: Unique identifier for the asset.
- `$url`: Full URL of the asset.
- `$file`: Full path to the asset file.
- `$ver`: Version string for cache busting (optional).
- `$deps`: Array of dependencies (optional).

#### `add_inline_asset_to_wp_footer()`
Registers an inline asset to be rendered in the footer of the document.

**Parameters**:
Same as `add_inline_asset_to_wp_head()`.

#### Example Usage:
```php
// Adding a critical CSS to the head.
add_inline_asset_to_wp_head( 'style', 'critical-css', 'https://example.com/styles.css', '/path/to/styles.css', '1.0.0', [] );

// Adding a critical JS to the footer.
add_inline_asset_to_wp_footer( 'script', 'custom-js', 'https://example.com/script.js', '/path/to/script.js', '1.0.0', [] );

// In a controller.
/**
 * Render print styles to header.
 *
 * @return void
 */
protected function load_print_assets(): void {

	// Asset Page Type File Data variables.
	$directory             = get_template_directory();
	$directory_uri         = get_template_directory_uri();
	$file_name             = 'print';
	$missing_local_warning = 'Run the npm build for the theme asset files. CSS, JS, fonts, and images etc.';

	/**
	 * Load the print style.
	 */
	$css_data = get_asset_page_type_file_data( $directory, $directory_uri, 'dist/css', $file_name, null, 'css', $missing_local_warning . " Missing {$file_name} CSS file." );

	if ( $css_data ) {

		$css_handle = $css_data['handle'];
		$css_src    = $css_data['url'];
		$css_file   = $css_data['file'];
		$css_deps   = [];
		$css_ver    = $css_data['ver'];
		$css_media  = 'all';

		if ( self::ASSET_RENDER_INLINE_PRINT_CSS ) {
			add_inline_asset_to_wp_head( 'style', $css_handle, $css_src, $css_file, $css_ver, $css_deps );
		} else {
			wp_register_style( $css_handle, $css_src, $css_deps, $css_ver, $css_media );
			wp_enqueue_style( $css_handle );
		}
	}
}
```

## Available Filters
The Enqueues MU Plugin provides several filters to customize its behavior, allowing you to fine-tune asset loading, directories, and other settings:
* `enqueues_move_jquery_to_footer`: Controls whether jQuery should be moved to the footer. Default: true.
* `enqueues_theme_default_enqueue_asset_filename`: Customize the default asset filename when no specific file is found. Default: 'main'.
* `enqueues_theme_allowed_page_types_and_templates`: Modify the array of allowed page types and templates for automatic asset loading. Default: `[]` (empty array).
* `enqueues_theme_skip_scan_directories`: Allows modification of the array of directories to skip during the template file scanning process. Default: `[ '/build-tools/', '/dist/', '/node_modules/', '/vendor/' ]`.
* `enqueues_theme_css_src_dir`: Customize the directory path for CSS files relative to the theme root. Default: 'dist/css'.
* `enqueues_theme_js_src_dir`: Customize the directory path for JS files relative to the theme root. Default: 'dist/js'.
* `enqueues_render_css_inline`: Controls whether CSS assets should be rendered inline. Default: false.
* `enqueues_render_js_inline`: Controls whether JS assets should be rendered inline. Default: false.
* `enqueues_asset_theme_src_directory`: Customize the source directory path for SCSS, CSS, and JS files relative to the theme root. Default: 'src'.
* `enqueues_asset_theme_dist_directory`: Customize the distribution directory path for compiled CSS and JS files relative to the theme root. Default: 'dist'.
* `enqueues_asset_theme_js_extension`: Change the file extension used for JavaScript files. Default: 'js'.
* `enqueues_asset_theme_css_extension`: Change the file extension used for SCSS/SASS/CSS files. Default: 'scss'.
* `enqueues_js_config_data_{$js_handle}`: Filter the JS config name for the given JS handle.
* `enqueues_js_config_name_{$js_handle}`: Filter the JS config data for the given JS handle.
* `enqueues_is_cache_enabled`: Controls whether caching is enabled for asset loading. By default, caching is enabled if the `ENQUEUES_CACHE_ENABLED` constant is set to `true`. This filter allows for runtime control of the caching mechanism.
* `enqueues_cache_ttl`: Modifies the time-to-live (TTL) for cached entries. By default, the TTL is set to 1 day (`DAY_IN_SECONDS`), but you can customize this via the `ENQUEUES_CACHE_TTL` constant or this filter.
* `enqueues_wp_head_inline_asset`: Filters the array of assets rendered inline in `wp_head`.
* `enqueues_wp_footer_inline_asset`: Filters the array of assets rendered inline in `wp_footer`.

### Block Editor Filters
The plugin provides hooks and filters to integrate with WordPress block editor (Gutenberg). Developers can disable or extend functionality by using these filters:

* `enqueues_is_block_editor_features_on`: Enable/disable block editor functionality for the theme or plugin. Default: true.
* `enqueues_block_editor_namespace`: Customize the namespace used for block registration. Default: 'custom'.
* `enqueues_block_editor_dist_dir`: Customize the block editor assets directory. Default: '/dist/block-editor/blocks'.
* `enqueues_block_editor_categories`: Add custom categories for Gutenberg blocks.
* `enqueues_block_editor_localized_data_{$type}_{$block}`: Filter localized data passed to block-specific scripts based on type (blocks, plugins, or extensions) and block name.
* `enqueues_block_editor_handle_css_{$type}_{$block}`: Customize the handle used for registering the stylesheet.
* `enqueues_block_editor_handle_js_{$type}_{$block}`: Customize the handle used for registering the script.
* `enqueues_block_editor_handle_js_args_{$type}_{$block}`: Customize the args used for registering the script.
* `enqueues_block_editor_localized_data_var_name_{$type}_{$block}`: Customize the variable name for localized block editor data passed to scripts.

## Default Behavior
* Default Assets: The plugin will default to main.css and main.js if no specific assets are found for a page type or template.
* jQuery: By default, jQuery is moved to the footer to enhance performance (see filter to change this global or per page).

## Extending the Plugin
You can also extend the plugin by adding custom filters to change the source directories, asset extensions, or inline behavior based on specific project needs.

### Example Usage of Block Editor Filters
The following are examples, please avoid anonymous functions.

#### Disabling Block Editor Features
```php
add_filter( 'enqueues_is_block_editor_features_on', '__return_false' );
```

#### Customizing Block Namespace
```php
add_filter( 'enqueues_block_editor_namespace', function( $namespace ) {
    return 'mytheme';
});
```

#### Modifying Block Categories
```php
add_filter( 'enqueues_block_editor_categories', function( $categories ) {
    return array_merge(
		$categories,
		[
			[
				'slug'  => 'mytheme-category',
				'title' => __( 'My Theme Blocks', 'mytheme' ),
				'icon'  => '/path/to/icon.svg',
			],
		]
	);
});
```

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

### Example Usage: Enqueuing a Custom JavaScript or CSS File independent of the automatic main file for each page.
```php

// Specify the asset file name (without the extension) and type (either 'js' or 'css').
$filename       = 'custom-asset';
$directory      = get_template_directory();
$directory_uri  = get_template_directory_uri();

$css_path = asset_find_file_path( '/dist/css', $filename, 'css', $directory );

// Local development warning if the asset is missing.
display_maybe_missing_local_warning( $css_path, "Run npm build to generate the \"{$filename}\" file." );

if ( $css_path ) {
	// Enqueue theme CSS bundle.
	wp_enqueue_style(
		$filename,
		"{$directory_uri}{$css_path}",
		[],
		filemtime( "{$directory}{$css_path}" ),
		false
	);
}

// Find the asset file path dynamically, checking for the correct version (minified or not).
$js_path = asset_find_file_path( '/dist/js', $filename, 'js', $directory );

// Display a warning in local development if the asset is missing.
display_maybe_missing_local_warning( $js_path, "Run npm build to generate the \"{$filename}\" file." );

// If the asset exists, enqueue it in WordPress.
if ( $js_path ) {
			
	// Path to the generated asset.php file from the dependency extraction package.
	$asset_path = $directory . str_replace( '.js', '.asset.php', $js_path );

	// We want to throw an error if the asset file has not been generated.
	if ( ! file_exists( $asset_path ) ) {
		wp_die( "Run npm build for the Infinite Scroll asset files, \"{$filename}\" asset PHP file missing.", E_USER_ERROR ); // phpcs:ignore
	}

	$assets = require_once $asset_path;

	wp_register_script(
		$filename,
		$directory_uri . $js_path,
		$assets['dependencies'] ?? [],
		$assets['version'] ?? filemtime( "{$directory}{$js_path}" ),
		[
			'strategy'  => 'async',
			'in_footer' => true,
		]
	);

	wp_enqueue_script( $filename );
	
	wp_localize_script( $filename, 'infiniteScrollPoolAdminPage', $this->get_localized_params() );
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