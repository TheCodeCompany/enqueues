# Block Editor Features

## What Is Block Editor Integration?
The Enqueues MU Plugin makes it easy to register custom blocks, block categories, and block editor plugins for the WordPress block editor (Gutenberg). You can control which scripts and styles are loaded in the editor, localize data, and more.

## CLS (Cumulative Layout Shift) Fix for Block Styles

### The Problem
Some block styles can be discovered during content rendering after `wp_head`, which causes them to be output via `print_late_styles()` near the footer, resulting in:

- **Cumulative Layout Shift (CLS)**: Content reflows as styles load late
- **Poor Core Web Vitals scores**: CLS negatively impacts performance metrics
- **Poor user experience**: Visible content jumps and layout shifts
- **Late paint of block styles**: Styles appear after initial page render

### The Solution
The Enqueues system implements a comprehensive fix:

1. **Let Core own block registration**: Use `register_block_type_from_metadata()` so Core registers the correct handles from `block.json`
2. **Track all block handles**: Store style and script handles for static and dynamic blocks during registration
3. **Pre-enqueue styles early**: On `wp_enqueue_scripts` (priority 1), check if blocks are present on the page and enqueue style handles
4. **Core Web Vitals optimization**: Use filters to ensure styles load as `<link>` tags in `<head>`
5. **Add localized parameters**: Use Core's registered handles to localize data for block scripts

### Benefits
- ✅ **Eliminates CLS** from late-loading block styles
- ✅ **No duplication**: Single source of truth (Core's registration from `block.json`)
- ✅ **Works with custom handles**: Respects custom style handles defined in `block.json`
- ✅ **Performance-friendly**: Only loads CSS for blocks present on the page
- ✅ **Maintains existing functionality**: Plugin/extension asset handling unchanged
- ✅ **Supports localized parameters**: Blocks can have localized data even without registering their own scripts

### Implementation Details
The fix is implemented in `BlockEditorRegistrationController`:

- **Block handle tracking**: Tracks static and dynamic block style handles from the registry
- **Style handle extraction**: Determines exact handles Core will use (supports custom handles)
- **Early pre-enqueue**: Uses `wp_enqueue_scripts` priority 1 to enqueue styles in `<head>`
- **Core Web Vitals filters**: 
  - `should_load_separate_core_block_assets = true`: Only load CSS for blocks on page
  - `should_load_block_assets_on_demand = false`: Disable on-demand loading to prefer head styles
  - `wp_should_inline_block_styles = false`: Use `<link>` tags instead of inline `<style>`
  - `styles_inline_size_limit = 0`: Prevent any block styles from being inlined
- **Localized parameters**: Uses Core's registered handles to add localized data to block scripts

### Order of Operations

#### What WordPress Core Does:
1. **`init` (priority 10)**: Registers blocks from `block.json` using `register_block_type_from_metadata()`
2. **Content rendering**: Discovers which blocks are used on the page
3. **Late enqueue path**: Some style handles can still be enqueued during render and output near the footer

#### What We Do:
1. **`init` (priority 10)**: Let Core register blocks, then extract handles from registry
2. **`wp_enqueue_scripts` (priority 1)**: Pre-enqueue block styles for static and dynamic blocks present on page
3. **`wp_enqueue_scripts` (priority 20)**: Add localized parameters to registered block scripts
4. **`enqueue_block_editor_assets` (priority 20)**: Add localized parameters to editor scripts

#### Why This Order Matters:
- **Priority 1**: Ensures block styles load before any content rendering
- **Priority 20**: Ensures localization happens after all scripts are registered
- **Result**: Block CSS prints in `<head>` as `<link>` tags, preventing CLS

### Rollout Checklist
When implementing this fix:

1. ✅ Remove/skip frontend registration of `blocks/*/(style|view).css`
2. ✅ Ensure blocks are registered via `register_block_type_from_metadata()`
3. ✅ Keep the Core Web Vitals optimization filters
4. ✅ Purge page cache and CDN
5. ✅ Test with DevTools "Disable cache" to verify block CSS loads in `<head>`

## Registering Blocks, Categories, and Plugins
- Blocks are registered by scanning your block directory for `block.json` files.
- You can add custom block categories or plugins using filters.
- Block editor assets (JS/CSS) are loaded automatically based on naming conventions.

## Using Filters for Block Assets

### Block Localization Filters
Blocks can have localized data even if they don't register their own scripts, using the handles that WordPress Core registered from `block.json`:

#### `enqueues_block_editor_js_localized_data_blocks_{block_slug}`
Filter the localized data for a block script.

**Parameters:**
- `$data` (array): The localized data array
- `$context` (string): 'frontend' or 'editor'
- `$script_handle` (string): The exact handle WordPress Core registered

**Example:**
```php
add_filter( 'enqueues_block_editor_js_localized_data_blocks_hero-block', function( $data, $context, $script_handle ) {
    return [
        'apiUrl' => rest_url( 'wp/v2/' ),
        'nonce'  => wp_create_nonce( 'wp_rest' ),
        'config' => [
            'someSetting' => 'value',
        ],
    ];
}, 10, 3 );
```

#### `enqueues_block_editor_js_localized_data_var_name_blocks_{block_slug}`
Filter the variable name for localized data.

**Parameters:**
- `$var_name` (string): The variable name
- `$context` (string): 'frontend' or 'editor'
- `$script_handle` (string): The exact handle WordPress Core registered

**Example:**
```php
add_filter( 'enqueues_block_editor_js_localized_data_var_name_blocks_hero-block', function( $var_name, $context, $script_handle ) {
    return 'heroBlockConfig';
}, 10, 3 );
```

### Plugin and Extension Filters
You can use filters to:
- Add or change dependencies for plugin/extension scripts/styles
- Localize data for plugin/extension scripts
- Control whether a plugin/extension is registered or enqueued

#### Example: Conditionally Register a Plugin Script
```php
add_filter( 'enqueues_block_editor_js_register_script_plugins_myplugin', function( $register, $context, $handle ) {
    return is_user_logged_in();
}, 10, 3 );
```

#### Example: Localize Data for a Plugin Script
```php
add_filter( 'enqueues_block_editor_js_localized_data_plugins_myplugin', function( $data, $context, $handle ) {
    $data['foo'] = 'bar';
    return $data;
}, 10, 3 );
```

## Why Use This?
- Streamlines block development and registration
- Ensures compatibility with the block editor
- Gives you fine-grained control over the editor experience
- Eliminates CLS issues with dynamic blocks
- Provides localized data support for all block types

# FILTERS FOR BLOCK EDITOR INTEGRATION

Below are the most important filters for customizing block editor asset loading and registration. Each filter is named according to the asset type and folder name (e.g., 'blocks_myblock', 'plugins_myplugin').

**Note**: All filters include a `$context` parameter as the second parameter and a `$handle` parameter as the third parameter, allowing you to make context-aware decisions and access the exact handle WordPress Core will use.

## Handle Alignment with WordPress Core

The system ensures perfect alignment with WordPress Core's handle generation from `block.json`. WordPress Core generates handles **without** `-css` or `-js` suffixes, but adds them when printing the HTML:

**CSS Handles:**
- `style` → `{namespace}-{block-name}-style` (becomes `id="{handle}-css"` in HTML)
- `viewStyle` → `{namespace}-{block-name}-view-style` (becomes `id="{handle}-css"` in HTML)
- `editorStyle` → `{namespace}-{block-name}-editor-style` (becomes `id="{handle}-css"` in HTML)

**JS Handles:**
- `script` → `{namespace}-{block-name}-script` (becomes `id="{handle}-js"` in HTML)
- `viewScript` → `{namespace}-{block-name}-view-script` (becomes `id="{handle}-js"` in HTML)
- `editorScript` → `{namespace}-{block-name}-editor-script` (becomes `id="{handle}-js"` in HTML)

**Example:** A block with `style: "file:./style.css"` in `block.json` will:
1. Generate handle: `example-read-more-content-style`
2. Print in HTML as: `<link rel="stylesheet" id="example-read-more-content-style-css" href="..." />`

**Custom Handles:** When you provide custom handles in `block.json`, the system uses those exact handles:
```json
{
  "style": ["file:./style.css", "my-custom-style-handle"],
  "script": ["file:./script.js", "my-custom-script-handle"]
}
```

## Block Localization Filters

These filters allow you to add localized data to block scripts using the handles that WordPress Core registered from `block.json`:

### `enqueues_block_editor_js_localized_data_blocks_{block_slug}`
Filter the localized data for a block script.

**Parameters:**
- `$data` (array): The localized data array (default: `[]`)
- `$context` (string): 'frontend' or 'editor'
- `$script_handle` (string): The exact handle WordPress Core registered

**Example:**
```php
add_filter( 'enqueues_block_editor_js_localized_data_blocks_hero-block', function( $data, $context, $script_handle ) {
    return [
        'apiUrl' => rest_url( 'wp/v2/' ),
        'nonce'  => wp_create_nonce( 'wp_rest' ),
    ];
}, 10, 3 );
```

### `enqueues_block_editor_js_localized_data_var_name_blocks_{block_slug}`
Filter the variable name for localized data.

**Parameters:**
- `$var_name` (string): The variable name (default: camelCase of "blockEditor blocks {block_slug} Config")
- `$context` (string): 'frontend' or 'editor'
- `$script_handle` (string): The exact handle WordPress Core registered

**Example:**
```php
add_filter( 'enqueues_block_editor_js_localized_data_var_name_blocks_hero-block', function( $var_name, $context, $script_handle ) {
    return 'heroBlockConfig';
}, 10, 3 );
```

## Plugin and Extension Filters

These filters work for plugins and extensions (not blocks, since blocks are managed by WordPress Core):

### CSS Filters
- `enqueues_block_editor_register_style_{type}_{foldername}`: Should the style be registered? Default: true.
- `enqueues_block_editor_css_dependencies_{type}_{foldername}`: Alter the style dependencies.
- `enqueues_block_editor_css_version_{type}_{foldername}`: Alter the style version.
- `enqueues_block_editor_enqueue_style_{type}_{foldername}`: Should the style be enqueued? Default: true for editor context, false for frontend context.

### JS Filters
- `enqueues_block_editor_js_register_script_{type}_{foldername}`: Should the script be registered? Default: true.
- `enqueues_block_editor_js_dependencies_{type}_{foldername}`: Alter the script dependencies. Default is from `.asset.php` if present.
- `enqueues_block_editor_js_version_{type}_{foldername}`: Alter the script version. Default is from `.asset.php` if present.
- `enqueues_block_editor_js_args_{type}_{foldername}`: Alter the script arguments (e.g., 'strategy', 'in_footer').
- `enqueues_block_editor_js_enqueue_script_{type}_{foldername}`: Should the script be enqueued? Default: true for editor context, false for frontend context.
- `enqueues_block_editor_js_localized_data_var_name_{type}_{foldername}`: Customize the variable name for localized JS data.
- `enqueues_block_editor_js_localized_data_{type}_{foldername}`: Customize the data array for localized JS variables.

**Note**: The filter name uses the folder name of the plugin/extension (e.g., `myplugin` for a folder named `myplugin`).

### Example: Conditionally Register a Plugin Script
```php
add_filter( 'enqueues_block_editor_js_register_script_plugins_myplugin', function( $register, $context, $handle ) {
    return is_user_logged_in();
}, 10, 3 );
```

### Example: Localize Data for a Plugin Script
```php
add_filter( 'enqueues_block_editor_js_localized_data_plugins_myplugin', function( $data, $context, $handle ) {
    $data['foo'] = 'bar';
    return $data;
}, 10, 3 );
```

# MORE FILTERS & ADVANCED OPTIONS

## Block Editor Filters
- `enqueues_block_editor_namespace`: Change the block registration namespace. Example:
```php
add_filter( 'enqueues_block_editor_namespace', function() { return 'mytheme'; });
```
- Core style-loading defaults are set in `BlockEditorRegistrationController` at priority 10:
```php
add_filter( 'should_load_separate_core_block_assets', '__return_true' );
add_filter( 'should_load_block_assets_on_demand', '__return_false' );
add_filter( 'wp_should_inline_block_styles', '__return_false' );
add_filter( 'styles_inline_size_limit', '__return_zero' );
```
- Site-level overrides should use a higher priority (e.g. `20`):
```php
add_filter( 'should_load_block_assets_on_demand', '__return_true', 20 );
```
- `enqueues_block_editor_preenqueue_block_styles`: Control whether Enqueues pre-enqueues block styles in the head. Defaults to enabled only when Enqueues style-loading defaults remain active.
```php
add_filter( 'enqueues_block_editor_preenqueue_block_styles', '__return_true' );
```
- `enqueues_block_editor_use_block_json_version`: Control block version source. Default: `false` (use compiled asset versions).
```php
// Use block.json version for all blocks.
add_filter( 'enqueues_block_editor_use_block_json_version', '__return_true' );

// Use block.json version for specific blocks only.
add_filter( 'enqueues_block_editor_use_block_json_version', function( $use_block_json_version, $block_name ) {
    return in_array( $block_name, [ 'custom/hero', 'custom/gallery' ], true );
}, 10, 2 );
```
- `enqueues_block_editor_dist_dir`: Change the block editor dist directory. Example:
```php
add_filter( 'enqueues_block_editor_dist_dir', function() { return '/dist/block-editor'; });
```
- `enqueues_block_editor_categories`: Add or modify block categories. Example:
```php
add_filter( 'enqueues_block_editor_categories', function( $cats ) {
    $cats[] = [ 'slug' => 'custom', 'title' => 'Custom Blocks' ];
    return $cats;
});
```
- `enqueues_is_block_editor_features_on`: Enable/disable all block editor features. Example:
```php
add_filter( 'enqueues_is_block_editor_features_on', '__return_false' );
```
- `enqueues_load_controller`: Control which controllers are loaded for block editor context. Example:
```php
add_filter( 'enqueues_load_controller', function( $load, $controller, $context ) {
    if ( 'BlockEditorRegistrationController' === $controller && 'theme' !== $context ) {
        return false;
    }
    return $load;
}, 10, 3 );
```

**Version handling note:** Block registrations use compiled asset versions derived from built files by default. Use `enqueues_block_editor_use_block_json_version` to opt back into `block.json` versions for all blocks or selected block names.

# BLOCK-EDITOR-SPECIFIC FOLDER/FILE FEATURES IN WEBPACK

The Enqueues system supports organizing block editor assets in dedicated folders for blocks, plugins, and extensions. In your Webpack config, you can define mappings like:

```js
const blockeditorDirectories = {
  'blocks/': 'blocks',
  'plugins/': 'plugins',
  'extensions/': 'extensions',
};
```

This allows you to:
- Output block, plugin, and extension assets to their own subfolders in `dist/block-editor/`.
- Use naming conventions like `/editor`, `/style`, `/view` to control output file names (e.g., `block-editor/blocks/my-block/index.css`).
- Clean up empty JS files generated for CSS-only entries using `cleanAfterEveryBuildPatterns`.
- Copy block JSON and PHP render files for registration and server-side rendering.

**Why use this?**
- Keeps block assets organized and scalable
- Makes it easy to register and load blocks, plugins, and extensions automatically
- Ensures compatibility with the Enqueues block registration system 