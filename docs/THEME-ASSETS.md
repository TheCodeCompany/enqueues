# Theme Asset Loading

## What Is Theme Asset Loading?
The Enqueues MU Plugin automatically loads CSS and JS files for each page type, template, or custom post type in your WordPress theme. This means you can organize your assets by context, and the plugin will ensure the right files are loaded for each page.

## How Fallback Works
- The plugin first looks for a file matching the current page type, template, or post type (e.g., `single-product.js`).
- If no specific file is found, it falls back to `main.js` and `main.css`.
- This ensures every page always has the necessary assets, even if you haven’t created a specific file for that context.

## Customizing Dependencies, Localization, and More
You can use filters to:
- Add or change script/style dependencies
- Localize data for your scripts
- Change the asset handle or version
- Control whether assets are enqueued or registered only

### Example: Adding Dependencies to Main JS
```php
add_filter( 'enqueues_theme_js_dependencies_main', function( $deps ) {
    $deps[] = 'wp-i18n';
    return $deps;
});
```

### Example: Localizing Data for Main JS
```php
add_filter( 'enqueues_theme_js_localized_data_main', function( $data ) {
    $data['myVar'] = 'Hello World!';
    return $data;
});
```

## Common Pitfalls
- **Filter Key Must Match the Asset Handle:** If the fallback to `main.js` is used, your filter should be for `main`, not the original page type or post type.
- **.asset.php Files:** If you use the WordPress dependency extraction plugin, dependencies and version will be read from `.asset.php` files automatically.

## Why Use This?
- Keeps your theme code clean and organized
- Ensures only the necessary assets are loaded for each page
- Makes it easy to extend or override behavior for specific pages or post types 

# FILTERS FOR THEME ASSET LOADING

Below are the most important filters you can use to customize theme asset loading. Each filter is named according to the asset handle (e.g., 'main', 'single-product', etc.).

## CSS Filters
- `enqueues_theme_css_handle_{handle}`: Customize the handle for the style.
- `enqueues_theme_css_register_style_{handle}`: Should the style be registered? Default: true.
- `enqueues_theme_css_dependencies_{handle}`: Alter the style dependencies.
- `enqueues_theme_css_version_{handle}`: Alter the style version.
- `enqueues_theme_css_enqueue_style_{handle}`: Should the style be enqueued? Default: true.
- `enqueues_theme_css_media_{handle}`: Alter the media attribute for the style. Default: 'all'.

### Example: Change CSS Media
```php
add_filter( 'enqueues_theme_css_media_main', function( $media ) {
    return 'print';
});
```

## JS Filters
- `enqueues_theme_js_handle_{handle}`: Customize the handle for the script.
- `enqueues_theme_js_register_script_{handle}`: Should the script be registered? Default: true.
- `enqueues_theme_js_dependencies_{handle}`: Alter the script dependencies. Default is from `.asset.php` if present.
- `enqueues_theme_js_version_{handle}`: Alter the script version. Default is from `.asset.php` if present.
- `enqueues_theme_js_args_{handle}`: Alter the script arguments (e.g., 'strategy', 'in_footer').
- `enqueues_theme_js_enqueue_script_{handle}`: Should the script be enqueued? Default: true.
- `enqueues_theme_js_localized_data_var_name_{handle}`: Customize the variable name for localized JS data.
- `enqueues_theme_js_localized_data_{handle}`: Customize the data array for localized JS variables.

### Example: Add a Dependency
```php
add_filter( 'enqueues_theme_js_dependencies_main', function( $deps ) {
    $deps[] = 'wp-api-fetch';
    return $deps;
});
```

### Example: Localize Data
```php
add_filter( 'enqueues_theme_js_localized_data_main', function( $data ) {
    $data['foo'] = 'bar';
    return $data;
});
``` 

# MORE FILTERS & ADVANCED OPTIONS

Below are additional filters for advanced customization and performance tuning.

## Inline Rendering Filters
- `enqueues_render_css_inline`: Should the CSS be rendered inline? Useful for critical CSS. Example:
```php
add_filter( 'enqueues_render_css_inline', function( $inline, $handle ) {
    return $handle === 'main'; // Only inline main CSS
}, 10, 2 );
```
- `enqueues_render_js_inline`: Should the JS be rendered inline? Useful for critical JS. Example:
```php
add_filter( 'enqueues_render_js_inline', function( $inline, $handle ) {
    return false; // Never inline JS
}, 10, 2 );
```

## Caching & Performance

The Enqueues system includes comprehensive caching to minimize filesystem operations and improve performance, especially on high-traffic multisite installations.

### Caching Strategy

**Automatic Caching:**
- All asset lookups (`asset_find_file_path()`, `get_asset_page_type_file_data()`) are cached
- Template file scans are cached
- Asset file discovery is cached
- Block registry scans are cached
- Negative lookups (file not found) are also cached to avoid repeated checks

**Build Signature Auto-Invalidation:**
- Cache keys include a build signature derived from main asset file modification times
- When assets are rebuilt (new deployment), the signature changes and caches automatically invalidate
- No manual cache flushing required after deployments
- Build signature respects the `enqueues_theme_default_enqueue_asset_filename` filter

**Cache Configuration:**
```php
// Enable caching (default: true)
define( 'ENQUEUES_CACHE_ENABLED', true );

// Set cache TTL in seconds (default: 12 hours)
define( 'ENQUEUES_CACHE_TTL', 12 * HOUR_IN_SECONDS );
```

**Manual Cache Flush:**
```php
// Flush all Enqueues caches
\Enqueues\flush_enqueues_cache();
```

### Caching Filters
- `enqueues_is_cache_enabled`: Enable/disable caching for asset lookups. Example:
```php
add_filter( 'enqueues_is_cache_enabled', '__return_false' ); // Disable caching in dev
```
- `enqueues_cache_ttl`: Set cache time-to-live (TTL) in seconds. Example:
```php
add_filter( 'enqueues_cache_ttl', function() { return 3600; }); // 1 hour
```

### Performance Best Practices

1. **Always use Enqueues helpers**: `asset_find_file_path()` and `get_asset_page_type_file_data()` are cached and optimized
2. **Avoid direct filesystem calls**: Use Enqueues functions instead of `file_exists()`, `filemtime()`, etc.
3. **Leverage caching**: Results are automatically cached, so repeated calls are fast
4. **Respect build signatures**: Cache keys include build signatures, so caches auto-invalidate on deployments
5. **Use filters for customisation**: Don't bypass the system; use filters to modify behavior

## Directory & File Extension Filters
- `enqueues_theme_allowed_page_types_and_templates`: Control which page types/templates are scanned for assets.
- `enqueues_theme_skip_scan_directories`: Skip directories when scanning for templates. Example:
```php
add_filter( 'enqueues_theme_skip_scan_directories', function( $dirs ) {
    $dirs[] = '/custom-skip/';
    return $dirs;
});
```
- `enqueues_theme_css_src_dir`: Change the CSS source directory. Default: 'dist/css'.
- `enqueues_theme_js_src_dir`: Change the JS source directory. Default: 'dist/js'.
- `enqueues_asset_theme_src_directory`: Change the source directory for SCSS/JS. Default: 'src'.
- `enqueues_asset_theme_dist_directory`: Change the dist directory for compiled assets. Default: 'dist'.
- `enqueues_asset_theme_js_extension`: Change the JS file extension. Default: 'js'.
- `enqueues_asset_theme_css_extension`: Change the CSS file extension. Default: 'sass'.

## JS Config Filters
- `enqueues_js_config_name_{handle}`: Change the JS config variable name for a script. Example:
```php
add_filter( 'enqueues_js_config_name_main', function( $name ) {
    return 'MyConfig';
});
```
- `enqueues_js_config_data_{handle}`: Change the JS config data array for a script. Example:
```php
add_filter( 'enqueues_js_config_data_main', function( $data ) {
    $data['foo'] = 'bar';
    return $data;
});
```

## Controller Loading
- `enqueues_load_controller`: Control which controllers are loaded (prevents double-loading if Enqueues is included in multiple plugins/MU-plugins). Example:
```php
add_filter( 'enqueues_load_controller', function( $load, $controller, $context ) {
    if ( 'BlockEditorRegistrationController' === $controller && 'theme' !== $context ) {
        return false;
    }
    return $load;
}, 10, 3 );
``` 
