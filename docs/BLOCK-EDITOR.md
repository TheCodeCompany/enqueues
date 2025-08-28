# Block Editor Features

## What Is Block Editor Integration?
The Enqueues MU Plugin makes it easy to register custom blocks, block categories, and block editor plugins for the WordPress block editor (Gutenberg). You can control which scripts and styles are loaded in the editor, localize data, and more.

## CLS (Cumulative Layout Shift) Fix for Dynamic Blocks

### The Problem
Dynamic blocks (those with `render.php` or `"render"` in `block.json`) have a critical issue: WordPress Core discovers their CSS during content rendering, which happens after `wp_head`. This causes the styles to be output via `print_late_styles()` near the footer, resulting in:

- **Cumulative Layout Shift (CLS)**: Content reflows as styles load late
- **Poor Core Web Vitals scores**: CLS negatively impacts performance metrics
- **Poor user experience**: Visible content jumps and layout shifts
- **Late paint of block styles**: Styles appear after initial page render

Static blocks don't have this issue because Core discovers their assets early and prints them in `<head>`.

### The Solution
The Enqueues system implements a comprehensive fix:

1. **Let Core own block registration**: Use `register_block_type_from_metadata()` so Core registers the correct handles from `block.json`
2. **Detect dynamic blocks**: Identify blocks with `render.php` or `"render"` in `block.json` during registration
3. **Pre-enqueue styles early**: On `wp_enqueue_scripts` (priority 1), check if dynamic blocks are present on the page and enqueue their styles
4. **Core Web Vitals optimization**: Use filters to ensure styles load as `<link>` tags in `<head>`

### Benefits
- ✅ **Eliminates CLS** from dynamic block styles
- ✅ **No duplication**: Single source of truth (Core's registration from `block.json`)
- ✅ **Works with custom handles**: Respects custom style handles defined in `block.json`
- ✅ **Performance-friendly**: Only loads CSS for blocks present on the page
- ✅ **Maintains existing functionality**: Plugin/extension asset handling unchanged

### Implementation Details
The fix is implemented in `BlockEditorRegistrationController`:

- **Dynamic block detection**: Scans for `render.php` or `"render"` in `block.json`
- **Style handle extraction**: Determines exact handles Core will use (supports custom handles)
- **Early pre-enqueue**: Uses `wp_enqueue_scripts` priority 1 to enqueue styles in `<head>`
- **Core Web Vitals filters**: 
  - `should_load_separate_core_block_assets = true`: Only load CSS for blocks on page
  - `wp_should_inline_block_styles = false`: Use `<link>` tags instead of inline `<style>`

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
You can use filters to:
- Add or change dependencies for block scripts/styles
- Localize data for block scripts
- Control whether a block plugin is registered or enqueued

### Example: Conditionally Register a Block Plugin
```php
add_filter( 'enqueues_block_editor_js_register_script_plugins_myplugin', function( $register, $context ) {
    return is_user_logged_in();
}, 10, 2 );
```

### Example: Localize Data for a Block Plugin
```php
add_filter( 'enqueues_block_editor_js_localized_data_plugins_myplugin', function( $data, $context ) {
    $data['foo'] = 'bar';
    return $data;
}, 10, 2 );
```

## Why Use This?
- Streamlines block development and registration
- Ensures compatibility with the block editor
- Gives you fine-grained control over the editor experience 

# FILTERS FOR BLOCK EDITOR INTEGRATION

Below are the most important filters for customizing block editor asset loading and registration. Each filter is named according to the asset type and handle (e.g., 'blocks_myblock', 'plugins_myplugin').

**Note**: All filters now include a `$context` parameter as the second parameter, allowing you to make context-aware decisions (e.g., 'editor', 'frontend', 'view').

## CSS Filters
- `enqueues_block_editor_css_handle_{type}_{handle}`: Customize the handle for the style.
- `enqueues_block_editor_css_register_style_{type}_{handle}`: Should the style be registered? Default: true.
- `enqueues_block_editor_css_dependencies_{type}_{handle}`: Alter the style dependencies.
- `enqueues_block_editor_css_version_{type}_{handle}`: Alter the style version.
- `enqueues_block_editor_css_enqueue_style_{type}_{handle}`: Should the style be enqueued? Default: true for editor context, false for frontend context.

### Example: Add a Dependency to a Block Style
```php
add_filter( 'enqueues_block_editor_css_dependencies_blocks_myblock', function( $deps, $context ) {
    $deps[] = 'wp-edit-blocks';
    return $deps;
}, 10, 2 );
```

## JS Filters
- `enqueues_block_editor_js_handle_{type}_{handle}`: Customize the handle for the script.
- `enqueues_block_editor_js_register_script_{type}_{handle}`: Should the script be registered? Default: true.
- `enqueues_block_editor_js_dependencies_{type}_{handle}`: Alter the script dependencies. Default is from `.asset.php` if present.
- `enqueues_block_editor_js_version_{type}_{handle}`: Alter the script version. Default is from `.asset.php` if present.
- `enqueues_block_editor_js_args_{type}_{handle}`: Alter the script arguments (e.g., 'strategy', 'in_footer').
- `enqueues_block_editor_js_enqueue_script_{type}_{handle}`: Should the script be enqueued? Default: true for editor context, false for frontend context.
- `enqueues_block_editor_js_localized_data_var_name_{type}_{handle}`: Customize the variable name for localized JS data.
- `enqueues_block_editor_js_localized_data_{type}_{handle}`: Customize the data array for localized JS variables.

### Example: Conditionally Register a Plugin Script
```php
add_filter( 'enqueues_block_editor_js_register_script_plugins_myplugin', function( $register, $context ) {
    return is_user_logged_in();
}, 10, 2 );
```

### Example: Localize Data for a Block Plugin
```php
add_filter( 'enqueues_block_editor_js_localized_data_plugins_myplugin', function( $data, $context ) {
    $data['foo'] = 'bar';
    return $data;
}, 10, 2 );
``` 

# MORE FILTERS & ADVANCED OPTIONS

## Block Editor Filters
- `enqueues_block_editor_namespace`: Change the block registration namespace. Example:
```php
add_filter( 'enqueues_block_editor_namespace', function() { return 'mytheme'; });
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