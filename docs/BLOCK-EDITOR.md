# Block Editor Features

## What Is Block Editor Integration?
The Enqueues MU Plugin makes it easy to register custom blocks, block categories, and block editor plugins for the WordPress block editor (Gutenberg). You can control which scripts and styles are loaded in the editor, localize data, and more.

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
add_filter( 'enqueues_block_editor_js_register_script_plugins_myplugin', function( $register ) {
    return is_user_logged_in();
});
```

### Example: Localize Data for a Block Plugin
```php
add_filter( 'enqueues_block_editor_js_localized_data_plugins_myplugin', function( $data ) {
    $data['foo'] = 'bar';
    return $data;
});
```

## Why Use This?
- Streamlines block development and registration
- Ensures compatibility with the block editor
- Gives you fine-grained control over the editor experience 

# FILTERS FOR BLOCK EDITOR INTEGRATION

Below are the most important filters for customizing block editor asset loading and registration. Each filter is named according to the asset type and handle (e.g., 'blocks_myblock', 'plugins_myplugin').

## CSS Filters
- `enqueues_block_editor_css_handle_{type}_{handle}`: Customize the handle for the style.
- `enqueues_block_editor_css_register_style_{type}_{handle}`: Should the style be registered? Default: true.
- `enqueues_block_editor_css_dependencies_{type}_{handle}`: Alter the style dependencies.
- `enqueues_block_editor_css_version_{type}_{handle}`: Alter the style version.
- `enqueues_block_editor_css_enqueue_style_{type}_{handle}`: Should the style be enqueued? Default: true for editor context, false for frontend context.

### Example: Add a Dependency to a Block Style
```php
add_filter( 'enqueues_block_editor_css_dependencies_blocks_myblock', function( $deps ) {
    $deps[] = 'wp-edit-blocks';
    return $deps;
});
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
add_filter( 'enqueues_block_editor_js_register_script_plugins_myplugin', function( $register ) {
    return is_user_logged_in();
});
```

### Example: Localize Data for a Block Plugin
```php
add_filter( 'enqueues_block_editor_js_localized_data_plugins_myplugin', function( $data ) {
    $data['foo'] = 'bar';
    return $data;
});
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