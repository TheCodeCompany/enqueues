# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2024-12-19

### Added
- Added 'minified' flag to `get_asset_page_type_file_data` to indicate if the loaded asset is minified.

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
- `get_asset_page_type_file_data` now returns an `asset_php` field for JS assets, containing dependency and version information from the corresponding .asset.php file.
- `ThemeEnqueueMainController` now uses the `asset_php` field for JS dependency and version data, removing duplicated logic and centralizing asset metadata handling.

### Removed
- Removed unused `$block_editor_namespace`