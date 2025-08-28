# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2024-12-20

### Added
- **CRITICAL FIX**: CLS (Cumulative Layout Shift) prevention for dynamic blocks
  - Dynamic blocks (with `render.php` or `"render"` in `block.json`) now have their styles pre-enqueued early to prevent CLS
  - Added `preenqueue_dynamic_block_styles()` method to detect and enqueue dynamic block styles in `<head>`
  - Added Core Web Vitals optimization filters (`should_load_separate_core_block_assets`, `wp_should_inline_block_styles`)
  - Added `extract_block_style_handles()` method to determine exact style handles Core will use
  - Added support for custom style handles defined in `block.json`

### Changed
- **BREAKING CHANGE**: Block CSS registration now handled by Core
  - Removed duplicate registration of `blocks/*/(style|view).css` files
  - Core now owns block asset registration via `register_block_type_from_metadata()`
  - Dynamic block styles are pre-enqueued separately to prevent CLS
  - Plugin and extension asset registration remains unchanged
- Updated `register_blocks()` to detect dynamic blocks and track their style handles
- Enhanced documentation with comprehensive CLS fix explanation

### Fixed
- **CRITICAL**: Eliminated CLS caused by dynamic block styles loading late in footer
- **CRITICAL**: Improved Core Web Vitals scores by ensuring block styles load in `<head>`
- **CRITICAL**: Fixed poor user experience from visible content shifts during page load

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