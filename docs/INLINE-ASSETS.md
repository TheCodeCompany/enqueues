# INLINE ASSET REGISTRATION

## WHAT IS INLINE ASSET REGISTRATION?
The Enqueues MU Plugin allows you to register critical CSS or JS assets to be rendered directly within the `<head>` or `<footer>` of your HTML. This is useful for critical path CSS or JS that must be inlined for performance or compatibility reasons.

## WHEN TO USE THIS?
- For critical CSS that should be inlined for faster rendering
- For small JS snippets that must run before other scripts
- When you want to avoid extra HTTP requests for small assets

## HOW TO REGISTER INLINE ASSETS
Use the provided functions:
- `add_inline_asset_to_wp_head( $type, $handle, $url, $file, $ver, $deps )` for the head
- `add_inline_asset_to_wp_footer( $type, $handle, $url, $file, $ver, $deps )` for the footer

### Example: Add Critical CSS to Head
```php
add_inline_asset_to_wp_head( 'style', 'critical-css', 'https://example.com/styles.css', '/path/to/styles.css', '1.0.0', [] );
```

### Example: Add Critical JS to Footer
```php
add_inline_asset_to_wp_footer( 'script', 'critical-js', 'https://example.com/script.js', '/path/to/script.js', '1.0.0', [] );
```

## WHY USE THIS?
- Improves performance by reducing render-blocking requests
- Gives you fine-grained control over asset loading order
- Useful for critical CSS/JS or compatibility fixes 

# MORE FILTERS & ADVANCED OPTIONS

## Inline Asset Filters
- `enqueues_wp_head_inline_asset`: Filter the array of assets rendered inline in wp_head. Example:
```php
add_filter( 'enqueues_wp_head_inline_asset', function( $assets ) {
    // Modify or add inline assets
    return $assets;
});
```
- `enqueues_wp_footer_inline_asset`: Filter the array of assets rendered inline in wp_footer. Example:
```php
add_filter( 'enqueues_wp_footer_inline_asset', function( $assets ) {
    // Modify or add inline assets
    return $assets;
});
```
- `enqueues_render_css_inline` and `enqueues_render_js_inline`: See THEME-ASSETS.md for details on inlining logic.

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