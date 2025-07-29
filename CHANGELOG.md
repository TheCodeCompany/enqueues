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

### Removed
- Removed unused `$block_editor_namespace` variable from `enqueue_assets()` method

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

## [1.0.0] - Initial Release

### Added
- Block editor registration functionality
- Asset enqueuing system for blocks, plugins, and extensions
- Block categories registration
- Frontend and editor asset management 