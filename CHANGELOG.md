# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.2] - 2025-09-23

### Added
- **ENHANCEMENT**: Post type slugification support for theme asset loading
  - Added support for post types with underscores (e.g., `film_simulation`) by checking both original and slugified formats
  - Enhanced `get_enqueues_theme_allowed_page_types_and_templates()` to include slugified post type versions
  - Improved compatibility between registered post type slugs and file naming conventions
  - Theme assets now load correctly for custom post types with non-standard slug formats

### Changed
- Updated post type detection logic to check both `single-{post_type}` and `single-{slugified_post_type}` formats
- Enhanced asset loading flexibility for custom post types with underscores or special characters

## [1.3.1] - 2025-09-03

### Fixed
- **BUGFIX**: Fixed incorrect file pattern routing in copy plugin configuration
  - Removed unnecessary `transform` function from `getCopyPluginConfigBlockJsonPattern`
  - Fixed issue where render-php files were incorrectly using block-json pattern with transforms
  - Eliminated dead code and improved copy operation performance
  - Render-php files now copy correctly without unwanted content transformations

### Changed
- Cleaned up `enqueues-copy-plugin-config-pattern.js` by removing unused transform logic
- Simplified copy operations to focus on file movement rather than content modification

## [1.3.0] - 2024-12-20

### Added
- **NEW FEATURE**: Block script localization support
  - Added `localize_block_scripts()` method to add localized parameters to registered block scripts
  - Added `enqueues_block_editor_js_localized_data_blocks_{block_slug}` filter for block localization
  - Added `enqueues_block_editor_js_localized_data_var_name_blocks_{block_slug}` filter for customizing variable names
  - Blocks can now have localized data even without registering their own scripts
  - Uses exact handles that WordPress Core registered from `block.json`
  - Supports both frontend and editor contexts
- Enhanced block handle tracking with improved data structure
  - Updated `$blocks` property to use `style_handles`, `view_style_handles`, etc. arrays
  - Better alignment with WordPress Core's handle structure
  - Improved type safety and documentation

### Changed
- **BREAKING CHANGE**: Refactored block editor asset management
  - Renamed `enqueue_assets()` to `enqueue_plugin_and_extension_assets()` for clarity
  - Method now only handles plugins and extensions (blocks managed by Core)
  - Removed block-specific registration logic to prevent conflicts with Core
  - Updated method documentation to clarify scope and purpose
- **BREAKING CHANGE**: Updated filter structure for better asset type separation
  - Block filters now use `{block_slug}` instead of `{foldername}` for clarity
  - Plugin/extension filters maintain `{type}_{foldername}` pattern
  - Clear separation between Core-managed blocks and Enqueues-managed plugins/extensions
- Improved code organization and readability
  - Updated variable names for better clarity (`$bt` → `$block_type`)
  - Enhanced method documentation with detailed explanations
  - Added comprehensive inline comments explaining complex logic

### Fixed
- **CRITICAL**: Fixed typo in method name (`enqueue_plugin_and_extenmsion_assets` → `enqueue_plugin_and_extension_assets`)
- **CRITICAL**: Ensured proper hook timing for block localization (priority 20)
- **CRITICAL**: Fixed potential issues with block handle extraction and tracking

### Documentation
- **MAJOR UPDATE**: Comprehensive documentation overhaul
  - Added "Order of Operations" section explaining WordPress Core vs Enqueues timing
  - Added "Block Localization Filters" section with examples
  - Updated filter documentation to clarify asset type separation
  - Added detailed explanations of why specific hook priorities are used
  - Removed outdated shim handle documentation
  - Updated FILTERS.md with asset type annotations for all block editor filters
  - Added explanatory notes about filter scope and usage

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