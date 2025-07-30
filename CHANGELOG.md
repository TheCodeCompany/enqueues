# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2024-12-19

### Changed
- **BREAKING CHANGE**: Refactored `BlockEditorRegistrationController::enqueue_assets()` method signature
  - Replaced confusing `$register_only` parameter with separate `$enqueue_style` and `$enqueue_script` parameters
  - Eliminated double negative logic (`!$register_only`) for better readability
  - Updated all method calls to use new parameter structure
  - Frontend assets now use `(false, false)` - register only, don't enqueue
  - Editor assets now use `(true, true)` - register and enqueue both styles and scripts
- **BREAKING CHANGE**: Added `$context` parameter to all block editor filters
  - All filters now receive `$context` as the second parameter for context-aware customization
  - Updated all filter examples in documentation to include the new parameter

### Removed
- Removed unused `$block_editor_namespace` variable from `enqueue_assets()` method

### Added
- Added 'minified' flag to `get_asset_page_type_file_data` to indicate if the loaded asset is minified.
- Updated JS dependency loading to prefer minified versions of dependencies when available.

### Migration Guide
If you're extending the `enqueue_assets()` method, update your calls:

**Before:**
```php
$this->enqueue_assets('blocks', 'editor', false);
```

**After:**
```php
$this->enqueue_assets('blocks', 'editor', true, true);
```

If you're using block editor filters, update your filter callbacks to include the `$context` parameter:

**Before:**
```php
add_filter( 'enqueues_block_editor_js_register_script_plugins_myplugin', function( $register ) {
    return is_user_logged_in();
});
```

**After:**
```php
add_filter( 'enqueues_block_editor_js_register_script_plugins_myplugin', function( $register, $context ) {
    return is_user_logged_in();
}, 10, 2 );
```

## [1.0.0] - Initial Release

### Added
- Block editor registration functionality
- Asset enqueuing system for blocks, plugins, and extensions
- Block categories registration
- Frontend and editor asset management 