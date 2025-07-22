# ENQUEUES MU PLUGIN

## OVERVIEW
Enqueues MU Plugin is a modern, flexible system for automating and customizing asset loading in WordPress themes and plugins. It is designed for maintainability, performance, and extensibility—supporting both classic and block-based development.

**Note:** All Webpack automation features are optional. You can use only the PHP asset loading/controllers, or only the Webpack utilities, or both together. Loading and initializing controllers is also optional and context-dependent—see Quick Start for details.

## FEATURES
- [Automatic Theme Asset Loading](docs/THEME-ASSETS.md): Load CSS/JS for each page type, template, or post type—automatically, with smart fallback.
- [Block Editor Integration](docs/BLOCK-EDITOR.md): Register custom blocks, block categories, and block editor plugins with fine-grained control.
- [Webpack Automation (Optional)](docs/WEBPACK.md): Dynamically generate Webpack entries, copy assets, and extract dependencies for a seamless build process. **All Webpack features are optional and can be used independently.**
- [Inline Asset Registration](docs/INLINE-ASSETS.md): Easily add critical CSS/JS directly to your page head or footer.
- [Extensive Filters & Extension Points](docs/THEME-ASSETS.md#filters-for-theme-asset-loading): Customize every aspect of asset loading and block integration.
- [Troubleshooting & Advanced Usage](docs/TROUBLESHOOTING.md): Solutions for common issues and advanced scenarios.

## QUICK START
1. **Install via Composer**
   ```bash
   composer require thecodeco/enqueues:dev-main
   ```
2. **Initialize in your theme or plugin (Optional)**
   ```php
   \Enqueues\enqueues_initialize_controllers('theme');
   ```
   *You only need to initialize controllers if you want to use the automated PHP asset loading features. You can use the Webpack utilities or other features independently.*
3. **Start customizing!**
   - See [Theme Asset Filters](docs/THEME-ASSETS.md#filters-for-theme-asset-loading) for examples.

## HOW IT WORKS
- **Automatic Asset Loading:**
  The plugin detects the current page type, template, or post type and loads the corresponding CSS/JS file. If no specific file is found, it falls back to `main.js`/`main.css`.
- **Block Editor Features:**
  Easily register and manage custom blocks, block categories, and block editor plugins. Use filters to control dependencies, localization, and more.
- **Webpack Automation (Optional):**
  Utilities help you keep your Webpack config in sync with your codebase, automatically generating entries and copying assets. **You can use these utilities without using the PHP controllers.**
- **Inline Asset Registration:**
  Register critical CSS/JS for inline output in `wp_head` or `wp_footer`.
- **Extensive Filters:**
  Fine-tune every aspect of asset loading, block registration, and build output. See the docs for a full list.

## WHERE TO GO NEXT
- [Theme Asset Loading →](docs/THEME-ASSETS.md)
- [Block Editor Features →](docs/BLOCK-EDITOR.md)
- [Webpack Automation →](docs/WEBPACK.md)
- [Inline Asset Registration →](docs/INLINE-ASSETS.md)
- [All Filters & Usage →](docs/THEME-ASSETS.md#filters-for-theme-asset-loading)
- [Troubleshooting →](docs/TROUBLESHOOTING.md)

## DETAILED FILTERS & ADVANCED OPTIONS
For a full list of filters, advanced configuration, and real-world usage, see the relevant sections in each doc in the `/docs/` folder. 