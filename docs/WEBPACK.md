# WEBPACK AUTOMATION

## WHAT DOES THE WEBPACK AUTOMATION DO?
The Enqueues MU Plugin provides JavaScript utilities to help you automatically generate Webpack entry points for your theme and block assets. This means you don’t have to manually update your Webpack config every time you add a new JS or SCSS file.

It also helps with copying images, fonts, and other assets, and integrates with the WordPress Dependency Extraction Webpack Plugin for dependency management.

## HOW TO USE THE PROVIDED JS UTILITIES
- Use `enqueues-merge-webpack-entries.js` to merge dynamic and custom entries.
- Use `enqueues-theme-webpack-entries.js` (deprecated) or `enqueues-webpack-entries.js` for theme asset entry generation.
- Use `enqueues-block-editor-webpack-entries.js` for block editor asset entry generation.
- Use `enqueues-copy-plugin-config-pattern.js` to generate CopyWebpackPlugin patterns for images, fonts, block JSON, and PHP render files.

## EXAMPLE WEBPACK CONFIG
```js
import enqueuesMergeWebpackEntries from '.../enqueues-merge-webpack-entries.js';
import enqueuesThemeWebpackEntries from '.../enqueues-theme-webpack-entries.js';
import enqueuesBlockEditorWebpackEntries from '.../enqueues-block-editor-webpack-entries.js';

const entries = enqueuesMergeWebpackEntries(
  enqueuesThemeWebpackEntries(rootDir, path, glob),
  enqueuesBlockEditorWebpackEntries(rootDir, path, glob)
);

export default {
  entry: entries,
  // ...other config
};
```

## TROUBLESHOOTING & TIPS
- Always use the merge utility to combine dynamic and custom entries.
- Use the CopyWebpackPlugin helpers to keep your dist directory in sync with your source assets.
- If you see missing asset errors, make sure your entry points and copy patterns match your file structure.

## WHY USE THIS?
- Saves time and reduces manual errors
- Keeps your asset pipeline in sync with your codebase
- Makes it easy to scale asset management as your project grows 

# MORE FILTERS & ADVANCED OPTIONS

## Webpack-Related Filters
- `enqueues_theme_css_src_dir`: Change the CSS source directory for Webpack builds. Example:
```php
add_filter( 'enqueues_theme_css_src_dir', function() { return 'dist/css'; });
```
- `enqueues_theme_js_src_dir`: Change the JS source directory for Webpack builds. Example:
```php
add_filter( 'enqueues_theme_js_src_dir', function() { return 'dist/js'; });
```
- `enqueues_asset_theme_src_directory`: Change the source directory for SCSS/JS. Example:
```php
add_filter( 'enqueues_asset_theme_src_directory', function() { return 'source'; });
```
- `enqueues_asset_theme_dist_directory`: Change the dist directory for compiled assets. Example:
```php
add_filter( 'enqueues_asset_theme_dist_directory', function() { return 'dist'; });
```
- `enqueues_asset_theme_js_extension`: Change the JS file extension. Example:
```php
add_filter( 'enqueues_asset_theme_js_extension', function() { return 'js'; });
```
- `enqueues_asset_theme_css_extension`: Change the CSS file extension. Example:
```php
add_filter( 'enqueues_asset_theme_css_extension', function() { return 'scss'; });
```

## Block-Editor-Specific Folder/File Features
- The Enqueues system supports organizing block editor assets in dedicated folders for blocks, plugins, and extensions. In your Webpack config, you can define mappings like:
```js
const blockeditorDirectories = {
  'blocks/': 'blocks',
  'plugins/': 'plugins',
  'extensions/': 'extensions',
};
```
- This allows you to output block, plugin, and extension assets to their own subfolders in `dist/block-editor/`.
- Use naming conventions like `/editor`, `/style`, `/view` to control output file names (e.g., `block-editor/blocks/my-block/index.css`).
- Clean up empty JS files generated for CSS-only entries using `cleanAfterEveryBuildPatterns`.
- Copy block JSON and PHP render files for registration and server-side rendering.

## Disabling Features/Controllers
You can disable or enable specific features/controllers using the `enqueues_load_controller` filter. For example, to only enable the Block Editor Registration Controller:
```php
add_filter( 'enqueues_load_controller', function( $initialize, $controller_name, $context ) {
    if ( 'theme' === $context && 'BlockEditorRegistrationController' === $controller_name ) {
        return true;
    } elseif ( 'theme' === $context ) {
        return false; // Disable all other controllers
    }
    return $initialize;
}, 10, 3 );
``` 

# BLOCK EDITOR AND THEME ASSET AUTOMATION WITH WEBPACK

## BLOCK EDITOR ASSET ORGANIZATION

The Enqueues system supports organizing block editor assets in dedicated folders for blocks, plugins, and extensions. This is controlled by the `blockeditorDirectories` mapping in your Webpack config:

```js
const blockeditorDirectories = {
  'blocks/': 'blocks',
  'plugins/': 'plugins',
  'extensions/': 'extensions',
};
```

This mapping ensures that assets for each type (block, plugin, extension) are output to their own subfolders in `dist/block-editor/`, making your build output scalable and maintainable.

## CSS AND JS OUTPUT FILENAME LOGIC

The output filenames for CSS and JS are controlled by the `MiniCssExtractPlugin` and the `output.filename` function. This logic ensures that files are routed to the correct subfolders and named according to conventions:

```js
const cssPlugin = new MiniCssExtractPlugin({
  filename: ({ chunk }) => {
    const name = chunk.name;
    const nameSegments = name.split('/');
    const blockName =
      nameSegments[nameSegments.length - 2] === 'blocks' ||
      nameSegments[nameSegments.length - 2] === 'plugins' ||
      nameSegments[nameSegments.length - 2] === 'extensions'
        ? nameSegments[nameSegments.length - 1]
        : nameSegments.slice(-2)[0];

    for (const [key, value] of Object.entries(blockeditorDirectories)) {
      if (name.startsWith(key)) {
        if (name.includes('/editor')) {
          return `block-editor/${value}/${blockName}/index.css`;
        } else if (name.includes('/style')) {
          return `block-editor/${value}/${blockName}/style.css`;
        } else if (name.includes('/view')) {
          return `block-editor/${value}/${blockName}/view.css`;
        } else {
          return `block-editor/${value}/${blockName}/error.css`;
        }
      }
    }
    return devMode ? `css/[name].css` : `css/[name].min.css`;
  },
});

// JS output
output: {
  path: distDir,
  clean: true,
  filename: ({ chunk }) => {
    if (
      chunk.name.startsWith('blocks/') ||
      chunk.name.startsWith('plugins/') ||
      chunk.name.startsWith('extensions/')
    ) {
      if (chunk.name.includes('/script')) {
        return `block-editor/${chunk.name}.js`;
      } else if (chunk.name.includes('/index')) {
        return `block-editor/${chunk.name}.js`;
      } else if (chunk.name.includes('/view')) {
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
```

**Why does this matter?**
- This structure is required for Enqueues to auto-register and load block assets. See [BLOCK-EDITOR.md](BLOCK-EDITOR.md) for details on how asset output structure affects block registration.

## ENTRY GENERATION AUTOMATION

- Use `enqueuesThemeWebpackEntries` for theme asset entry generation.
- Use `enqueuesBlockEditorWebpackEntries` for block editor asset entry generation.
- Use `enqueuesMergeWebpackEntries` to combine both for a single build.

```js
const entries = enqueuesMergeWebpackEntries(
  enqueuesThemeWebpackEntries(rootDir, path, glob),
  enqueuesBlockEditorWebpackEntries(rootDir, path, glob),
);
```

This works for both main theme files and block editor files. See [THEME-ASSETS.md](THEME-ASSETS.md) for more on theme asset structure.

## COPYING BLOCK JSON AND PHP RENDER FILES

Use `enqueuesGetCopyPluginConfigPattern` with CopyWebpackPlugin to copy block JSON and PHP render files:

```js
const copyPlugin = new CopyWebpackPlugin({
  patterns: [
    enqueuesGetCopyPluginConfigPattern(rootDir, distDir, 'images'),
    enqueuesGetCopyPluginConfigPattern(rootDir, distDir, 'fonts'),
    enqueuesGetCopyPluginConfigPattern(rootDir, distDir, 'block-json'),
    enqueuesGetCopyPluginConfigPattern(rootDir, distDir, 'render-php'),
    enqueuesGetCopyPluginConfigPattern(rootDir, distDir, 'block-assets'),
  ],
});
```

This ensures block registration and server-side rendering work as expected.

To customise block source and destination directories, pass `srcBlockDir` and `distBlockDir`. You can also copy a different block directory by setting `assetsDir`.

```js
const copyPlugin = new CopyWebpackPlugin({
  patterns: [
    enqueuesGetCopyPluginConfigPattern(
      rootDir,
      distDir,
      'block-assets',
      '**/source',
      'blockeditor/main-blocks',
      'icons',
      'block-editor/blocks'
    ),
    enqueuesGetCopyPluginConfigPattern(
      rootDir,
      distDir,
      'block-json',
      '**/source',
      'blockeditor/main-blocks',
      undefined,
      'block-editor/blocks'
    ),
    enqueuesGetCopyPluginConfigPattern(
      rootDir,
      distDir,
      'render-php',
      '**/source',
      'blockeditor/main-blocks',
      undefined,
      'block-editor/blocks'
    ),
  ],
});
```

## CLEANING UP BUILD OUTPUT

Use `cleanAfterEveryBuildPatterns` to remove empty JS files generated for CSS-only entries:

```js
const cleanAfterEveryBuildPatterns = Object.values(blockeditorDirectories).flatMap((type) => [
  `block-editor/${type}/**/style`,
  `block-editor/${type}/**/editor`,
]);
```

## OTHER FEATURES
- **BrowserSync integration** (if present)
- **DependencyExtractionWebpackPlugin** for WordPress dependencies
- **JQuery auto-provisioning**
- **Minification and optimization plugins**

## DISABLING/ENABLING FEATURES
You can enable or disable specific Enqueues features/controllers using the `enqueues_load_controller` filter:

```php
add_filter( 'enqueues_load_controller', function( $initialize, $controller_name, $context ) {
    if ( 'theme' === $context && 'BlockEditorRegistrationController' === $controller_name ) {
        return true;
    } elseif ( 'theme' === $context ) {
        return false; // Disable all other controllers
    }
    return $initialize;
}, 10, 3 );
```

## REAL-WORLD EXAMPLE

Below is a real-world Webpack config using all these features:

```js
// ... (insert your full config here, as provided above, with comments)
```

## CROSS-REFERENCES
- See [BLOCK-EDITOR.md](BLOCK-EDITOR.md) for block asset structure and registration details.
- See [THEME-ASSETS.md](THEME-ASSETS.md) for theme asset entry generation and structure. 

# FULL EXAMPLE WEBPACK CONFIG

Below is a real-world Webpack config that supports both theme and block editor asset automation, with detailed comments explaining each section and why it’s structured this way.

```js
import path from 'path';
const glob = require('glob');
import webpack from 'webpack';
import MiniCssExtractPlugin from 'mini-css-extract-plugin';
import TerserPlugin from 'terser-webpack-plugin';
import CssMinimizerPlugin from 'css-minimizer-webpack-plugin';
import { CleanWebpackPlugin } from 'clean-webpack-plugin';
import DependencyExtractionWebpackPlugin from '@wordpress/dependency-extraction-webpack-plugin';
import CopyWebpackPlugin from 'copy-webpack-plugin';

// Enqueues helper functions for entry generation and asset copying
import enqueuesMergeWebpackEntries from '.../enqueues-merge-webpack-entries.js';
import enqueuesThemeWebpackEntries from '.../enqueues-theme-webpack-entries.js';
import enqueuesBlockEditorWebpackEntries from '.../enqueues-block-editor-webpack-entries.js';
import enqueuesGetCopyPluginConfigPattern from '.../enqueues-copy-plugin-config-pattern.js';

const devMode = process.env.NODE_ENV !== 'production';
const rootDir = path.resolve(__dirname, '..');
const buildDir = path.resolve(rootDir, 'build-tools');
const distDir = path.resolve(rootDir, 'dist');
const nodeModulesDir = path.resolve(rootDir, 'build-tools/node_modules');

// Block editor asset organization
const blockeditorDirectories = {
  'blocks/': 'blocks',
  'plugins/': 'plugins',
  'extensions/': 'extensions',
};

const cssPlugin = new MiniCssExtractPlugin({
  filename: ({ chunk }) => {
    // Output block editor CSS to correct subfolders and filenames
    const name = chunk.name;
    const nameSegments = name.split('/');
    const blockName =
      nameSegments[nameSegments.length - 2] === 'blocks' ||
      nameSegments[nameSegments.length - 2] === 'plugins' ||
      nameSegments[nameSegments.length - 2] === 'extensions'
        ? nameSegments[nameSegments.length - 1]
        : nameSegments.slice(-2)[0];
    for (const [key, value] of Object.entries(blockeditorDirectories)) {
      if (name.startsWith(key)) {
        if (name.includes('/editor')) {
          return `block-editor/${value}/${blockName}/index.css`;
        } else if (name.includes('/style')) {
          return `block-editor/${value}/${blockName}/style.css`;
        } else if (name.includes('/view')) {
          return `block-editor/${value}/${blockName}/view.css`;
        } else {
          return `block-editor/${value}/${blockName}/error.css`;
        }
      }
    }
    return devMode ? `css/[name].css` : `css/[name].min.css`;
  },
});

// Clean up empty JS files generated for CSS-only entries
const cleanAfterEveryBuildPatterns = Object.values(blockeditorDirectories).flatMap((type) => [
  `block-editor/${type}/**/style`,
  `block-editor/${type}/**/editor`,
]);

const cleanWebpackPlugin = new CleanWebpackPlugin({
  dry: false,
  cleanOnceBeforeBuildPatterns: ['**/*'],
  dangerouslyAllowCleanPatternsOutsideProject: true,
  cleanAfterEveryBuildPatterns,
});

const optimizeJS = new TerserPlugin({
  terserOptions: {
    format: { comments: devMode },
  },
  extractComments: devMode,
});

const optimizeCss = new CssMinimizerPlugin({
  minimizerOptions: {
    preset: [
      'default',
      { discardComments: { removeAll: !devMode } },
    ],
  },
});

const jquery = new webpack.ProvidePlugin({
  $: 'jquery',
  jQuery: 'jquery',
  'window.jQuery': 'jquery',
});

// Copy block JSON, PHP render files, images, and fonts
const copyPlugin = new CopyWebpackPlugin({
  patterns: [
    enqueuesGetCopyPluginConfigPattern(rootDir, distDir, 'images'),
    enqueuesGetCopyPluginConfigPattern(rootDir, distDir, 'fonts'),
    enqueuesGetCopyPluginConfigPattern(rootDir, distDir, 'block-json'),
    enqueuesGetCopyPluginConfigPattern(rootDir, distDir, 'render-php'),
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

// Entry generation automation for both theme and block assets
const entries = enqueuesMergeWebpackEntries(
  enqueuesThemeWebpackEntries(rootDir, path, glob),
  enqueuesBlockEditorWebpackEntries(rootDir, path, glob),
);

export default {
  mode: devMode ? 'development' : 'production',
  stats: devMode ? 'verbose' : 'normal',
  context: rootDir,
  resolve: {
    modules: [nodeModulesDir, 'node_modules'],
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
      if (
        chunk.name.startsWith('blocks/') ||
        chunk.name.startsWith('plugins/') ||
        chunk.name.startsWith('extensions/')
      ) {
        if (chunk.name.includes('/script')) {
          return `block-editor/${chunk.name}.js`;
        } else if (chunk.name.includes('/index')) {
          return `block-editor/${chunk.name}.js`;
        } else if (chunk.name.includes('/view')) {
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
        test: /\.(woff(2)?|ttf|eot)$/,
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
    jquery: 'jQuery',
  },
  optimization: {
    minimize: true,
    minimizer: [optimizeJS, optimizeCss],
  },
  plugins,
};
```

**See also:**
- [Block Editor Asset Organization](#block-editor-asset-organization)
- [Theme Asset Entry Generation](THEME-ASSETS.md)
- [Block Registration Details](BLOCK-EDITOR.md) 