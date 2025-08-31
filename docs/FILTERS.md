# Master Filter & Action Index

This page lists **all** filters and actions available in the Enqueues MU Plugin, grouped by feature. Use this as a quick reference—each filter/action links to its full documentation and usage examples in the relevant feature doc, or is marked as internal/advanced if not typically used directly.

**Tip:** All filters are also documented in context in the feature docs ([Theme Assets](THEME-ASSETS.md), [Block Editor](BLOCK-EDITOR.md), [Inline Assets](INLINE-ASSETS.md), [Webpack](WEBPACK.md)).

---

## Theme Asset Loading

| Filter/Action                                         | Summary                                                        | Docs                                                        |
|:------------------------------------------------------|:---------------------------------------------------------------|:------------------------------------------------------------|
| `enqueues_theme_css_handle_{handle}`                  | Filter the CSS handle for a theme asset.                       | [Theme CSS Filters](THEME-ASSETS.md#filters-for-theme-asset-loading) |
| `enqueues_theme_css_dependencies_{handle}`            | Filter CSS dependencies for a theme asset.                     | [Theme CSS Filters](THEME-ASSETS.md#filters-for-theme-asset-loading) |
| `enqueues_theme_css_version_{handle}`                 | Filter CSS version for a theme asset.                          | [Theme CSS Filters](THEME-ASSETS.md#filters-for-theme-asset-loading) |
| `enqueues_theme_css_media_{handle}`                   | Filter the media attribute for a theme CSS asset.              | [Theme CSS Filters](THEME-ASSETS.md#filters-for-theme-asset-loading) |
| `enqueues_theme_css_args_{handle}`                    | Filter CSS enqueue args (e.g. media).                          | [Theme CSS Filters](THEME-ASSETS.md#filters-for-theme-asset-loading) |
| `enqueues_theme_css_register_style_{handle}`          | Enable/disable CSS style registration.                         | [Theme CSS Filters](THEME-ASSETS.md#filters-for-theme-asset-loading) |
| `enqueues_theme_css_enqueue_style_{handle}`           | Enable/disable CSS style enqueue.                              | [Theme CSS Filters](THEME-ASSETS.md#filters-for-theme-asset-loading) |
| `enqueues_theme_js_handle_{handle}`                   | Filter the JS handle for a theme asset.                        | [Theme JS Filters](THEME-ASSETS.md#filters-for-theme-asset-loading) |
| `enqueues_theme_js_dependencies_{handle}`             | Filter JS dependencies for a theme asset.                      | [Theme JS Filters](THEME-ASSETS.md#filters-for-theme-asset-loading) |
| `enqueues_theme_js_version_{handle}`                  | Filter JS version for a theme asset.                           | [Theme JS Filters](THEME-ASSETS.md#filters-for-theme-asset-loading) |
| `enqueues_theme_js_args_{handle}`                     | Filter JS enqueue args (e.g. in_footer, strategy).             | [Theme JS Filters](THEME-ASSETS.md#filters-for-theme-asset-loading) |
| `enqueues_theme_js_register_script_{handle}`          | Enable/disable JS script registration.                         | [Theme JS Filters](THEME-ASSETS.md#filters-for-theme-asset-loading) |
| `enqueues_theme_js_enqueue_script_{handle}`           | Enable/disable JS script enqueue.                              | [Theme JS Filters](THEME-ASSETS.md#filters-for-theme-asset-loading) |
| `enqueues_theme_js_localized_data_{handle}`           | Filter localized data for a JS asset.                          | [Theme JS Filters](THEME-ASSETS.md#filters-for-theme-asset-loading) |
| `enqueues_theme_js_localized_data_var_name_{handle}`  | Filter the variable name for localized JS data.                | [Theme JS Filters](THEME-ASSETS.md#filters-for-theme-asset-loading) |
| `enqueues_theme_default_enqueue_asset_filename`        | Filter the default fallback asset filename (e.g., 'main').     | [Theme Asset Fallback](THEME-ASSETS.md#how-fallback-works) |
| `enqueues_theme_allowed_page_types_and_templates`      | Filter allowed page types/templates for asset loading.          | [Theme Asset Loading](THEME-ASSETS.md#customizing-dependencies-localization-and-more) |
| `enqueues_theme_skip_scan_directories`                | Filter directories to skip when scanning for assets.           | [Theme Asset Loading](THEME-ASSETS.md#customizing-dependencies-localization-and-more) |
| `enqueues_theme_css_src_dir`                          | Filter the source directory for theme CSS assets.              | [Theme Asset Loading](THEME-ASSETS.md#customizing-dependencies-localization-and-more) |
| `enqueues_theme_js_src_dir`                           | Filter the source directory for theme JS assets.               | [Theme Asset Loading](THEME-ASSETS.md#customizing-dependencies-localization-and-more) |
| `enqueues_render_css_inline`                          | Filter whether to render CSS inline (per handle).              | [Inline Asset Filters](INLINE-ASSETS.md#filters) |
| `enqueues_render_js_inline`                           | Filter whether to render JS inline (per handle).               | [Inline Asset Filters](INLINE-ASSETS.md#filters) |

---

## Theme Inline Assets

| Filter/Action                      | Summary                                         | Docs                                         |
|:-----------------------------------|:------------------------------------------------|:---------------------------------------------|
| `enqueues_wp_head_inline_asset`    | Filter assets rendered inline in `wp_head`.      | [Inline Asset Filters](INLINE-ASSETS.md#filters) |
| `enqueues_wp_footer_inline_asset`  | Filter assets rendered inline in `wp_footer`.    | [Inline Asset Filters](INLINE-ASSETS.md#filters) |

---

## ThemeEnqueueJqueryController

| Filter/Action                      | Summary                                         | Docs                                         |
|:-----------------------------------|:------------------------------------------------|:---------------------------------------------|
| `enqueues_load_jquery_in_footer`   | Filter whether to move jQuery to the footer.     | [Theme Asset Loading](THEME-ASSETS.md#controller-loading) |

---

## Block Editor

**Note**: Block editor filters are separated by asset type:
- **Blocks**: Managed by WordPress Core via `block.json`. Only localization filters are available.
- **Plugins/Extensions**: Fully managed by Enqueues with complete filter control.
- **All**: Apply to the entire block editor system.

| Filter/Action                                         | Summary                                                        | Asset Type | Docs                                                        |
|:------------------------------------------------------|:---------------------------------------------------------------|:-----------|:------------------------------------------------------------|
| `enqueues_block_editor_js_localized_data_blocks_{block_slug}` | Filter localized data for block scripts.                   | **Blocks** | [Block Editor Filters](BLOCK-EDITOR.md#block-localization-filters) |
| `enqueues_block_editor_js_localized_data_var_name_blocks_{block_slug}` | Filter variable name for block localized data.            | **Blocks** | [Block Editor Filters](BLOCK-EDITOR.md#block-localization-filters) |
| `enqueues_block_editor_register_style_{type}_{foldername}` | Enable/disable registration of plugin/extension CSS.       | **Plugins/Extensions** | [Block Editor Filters](BLOCK-EDITOR.md#plugin-and-extension-filters) |
| `enqueues_block_editor_css_dependencies_{type}_{foldername}` | Filter dependencies for plugin/extension CSS.              | **Plugins/Extensions** | [Block Editor Filters](BLOCK-EDITOR.md#plugin-and-extension-filters) |
| `enqueues_block_editor_css_version_{type}_{foldername}`  | Filter version for plugin/extension CSS.                      | **Plugins/Extensions** | [Block Editor Filters](BLOCK-EDITOR.md#plugin-and-extension-filters) |
| `enqueues_block_editor_enqueue_style_{type}_{foldername}` | Enable/disable enqueue for plugin/extension CSS.             | **Plugins/Extensions** | [Block Editor Filters](BLOCK-EDITOR.md#plugin-and-extension-filters) |
| `enqueues_block_editor_js_args_{type}_{foldername}`      | Filter args for plugin/extension JS.                          | **Plugins/Extensions** | [Block Editor Filters](BLOCK-EDITOR.md#plugin-and-extension-filters) |
| `enqueues_block_editor_js_register_script_{type}_{foldername}` | Enable/disable registration of plugin/extension JS.       | **Plugins/Extensions** | [Block Editor Filters](BLOCK-EDITOR.md#plugin-and-extension-filters) |
| `enqueues_block_editor_js_dependencies_{type}_{foldername}` | Filter dependencies for plugin/extension JS.               | **Plugins/Extensions** | [Block Editor Filters](BLOCK-EDITOR.md#plugin-and-extension-filters) |
| `enqueues_block_editor_js_version_{type}_{foldername}`   | Filter version for plugin/extension JS.                       | **Plugins/Extensions** | [Block Editor Filters](BLOCK-EDITOR.md#plugin-and-extension-filters) |
| `enqueues_block_editor_js_enqueue_script_{type}_{foldername}` | Enable/disable enqueue for plugin/extension JS.            | **Plugins/Extensions** | [Block Editor Filters](BLOCK-EDITOR.md#plugin-and-extension-filters) |
| `enqueues_block_editor_js_localized_data_{type}_{foldername}` | Filter localized data for plugin/extension JS.             | **Plugins/Extensions** | [Block Editor Filters](BLOCK-EDITOR.md#plugin-and-extension-filters) |
| `enqueues_block_editor_js_localized_data_var_name_{type}_{foldername}` | Filter variable name for plugin/extension localized data. | **Plugins/Extensions** | [Block Editor Filters](BLOCK-EDITOR.md#plugin-and-extension-filters) |
| `enqueues_block_editor_namespace`                      | Filter the block editor namespace.                             | **All** | [Block Editor Filters](BLOCK-EDITOR.md#more-filters--advanced-options) |
| `enqueues_block_editor_dist_dir`                       | Filter the block editor dist directory.                        | **All** | [Block Editor Filters](BLOCK-EDITOR.md#more-filters--advanced-options) |
| `enqueues_block_editor_categories`                     | Filter block editor categories.                                | **All** | [Block Editor Filters](BLOCK-EDITOR.md#more-filters--advanced-options) |

---

## JS Config/Localization

| Filter/Action                      | Summary                                         | Docs                                         |
|:-----------------------------------|:------------------------------------------------|:---------------------------------------------|
| `enqueues_js_config_name_{js_handle}` | Filter the JS config variable name.             | [Theme Asset Loading](THEME-ASSETS.md#js-config-filters) |
| `enqueues_js_config_data_{js_handle}` | Filter the JS config data.                      | [Theme Asset Loading](THEME-ASSETS.md#js-config-filters) |

---

## Caching & Performance

| Filter/Action                      | Summary                                         | Docs                                         |
|:-----------------------------------|:------------------------------------------------|:---------------------------------------------|
| `enqueues_is_cache_enabled`        | Filter whether caching is enabled.               | [Caching](THEME-ASSETS.md#caching)           |
| `enqueues_cache_ttl`               | Filter the cache time-to-live.                   | [Caching](THEME-ASSETS.md#caching)           |

---

## Controller/Config/Autoload

| Filter/Action                      | Summary                                         | Docs                                         |
|:-----------------------------------|:------------------------------------------------|:---------------------------------------------|
| `enqueues_load_controller`         | Filter whether to load a controller (by name/context). | [Theme Asset Loading](THEME-ASSETS.md#controller-loading) |
| `base_pre_controller_set_config`   | Filter controller before config is set.          | Internal/Advanced                            |
| `base_post_controller_set_config`  | Filter controller after config is set.           | Internal/Advanced                            |
| `base_pre_controller_set_up`       | Filter controller before setup.                  | Internal/Advanced                            |
| `base_post_controller_set_up`      | Filter controller after setup.                   | Internal/Advanced                            |

---

## Asset Path/Extension

| Filter/Action                      | Summary                                         | Docs                                         |
|:-----------------------------------|:------------------------------------------------|:---------------------------------------------|
| `enqueues_asset_theme_src_directory` | Filter the theme asset source directory.         | [Theme Asset Loading](THEME-ASSETS.md#directory-and-extension-filters) |
| `enqueues_asset_theme_dist_directory` | Filter the theme asset dist directory.           | [Theme Asset Loading](THEME-ASSETS.md#directory-and-extension-filters) |
| `enqueues_asset_theme_js_extension` | Filter the theme JS extension.                   | [Theme Asset Loading](THEME-ASSETS.md#directory-and-extension-filters) |
| `enqueues_asset_theme_css_extension` | Filter the theme CSS extension.                  | [Theme Asset Loading](THEME-ASSETS.md#directory-and-extension-filters) |

---

## Block Editor/Translation

| Filter/Action                      | Summary                                         | Docs                                         |
|:-----------------------------------|:------------------------------------------------|:---------------------------------------------|
| `enqueues_translation_domain`      | Filter the translation domain.                   | [Block Editor Filters](BLOCK-EDITOR.md#more-filters--advanced-options) |

---

## String/Utility

| Filter/Action                      | Summary                                         | Docs                                         |
|:-----------------------------------|:------------------------------------------------|:---------------------------------------------|
| `string_camelcaseify`              | Filter the result of the camelcase utility.      | Internal/Advanced                            |

---

## Environment

| Filter/Action                      | Summary                                         | Docs                                         |
|:-----------------------------------|:------------------------------------------------|:---------------------------------------------|
| `environment_type_matches_{env}`   | Filter environment type matches.                 | Internal/Advanced                            |
| `environment_site_url_partial_matches_{env}` | Filter environment site URL matches.         | Internal/Advanced                            |

---

**For a full description and usage examples, see the linked docs for each filter/action.**

*If you find a filter/action in the codebase that is not listed here, please open an issue or PR to help us keep this index up to date!* 