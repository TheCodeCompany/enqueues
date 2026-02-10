# Webpack vs @wordpress/scripts Comparison

## Overview

This document compares the Enqueues webpack automation system with WordPress's official `@wordpress/scripts` tooling. Understanding these differences helps you choose the right approach for your project's needs and complexity.

## What Is @wordpress/scripts?

`@wordpress/scripts` is WordPress's official build tool for block development. It provides a standardized way to build blocks with minimal configuration.

**Key Characteristics:**
- Single `block.json` file as entry point
- Automatic dependency extraction for `@wordpress/*` packages
- Built-in webpack configuration optimised for blocks
- Outputs to `build/` directory by default
- Designed for individual block development

## What Is Enqueues Webpack Automation?

The Enqueues webpack automation provides advanced asset management with automatic discovery, conditional loading, and seamless PHP integration.

**Key Characteristics:**
- Multiple `block.json` files across organized directories
- Automatic discovery of blocks, plugins, and extensions
- Custom webpack configuration with Enqueues integration
- Context-aware asset loading based on content analysis
- Outputs to organized `dist/block-editor/` structure

---

## Detailed Comparison

### **Setup & Configuration**

| Aspect | @wordpress/scripts | Enqueues Webpack |
|--------|-------------------|------------------|
| **Initial Setup** | Simple, single `block.json` | More complex, organized structure |
| **Configuration** | Minimal, WordPress handles it | Custom webpack config with Enqueues utilities |
| **Entry Points** | Single block focus | Multiple blocks, plugins, extensions |
| **Directory Structure** | `src/` with single block | `source/block-editor/blocks/`, `plugins/`, `extensions/` |

### **Asset Discovery & Management**

| Aspect | @wordpress/scripts | Enqueues Webpack |
|--------|-------------------|------------------|
| **Asset Discovery** | Manual entry in `block.json` | Automatic file system scanning |
| **Theme Assets** | Manual webpack configuration | Automatic via `enqueuesWebpackEntries()` |
| **Block Organization** | Single directory | Organized by type (blocks/plugins/extensions) |
| **File Naming** | Standard WordPress conventions | Custom conventions with context awareness |

### **Performance & Loading**

| Aspect | @wordpress/scripts | Enqueues Webpack |
|--------|-------------------|------------------|
| **Asset Loading** | Loads all registered assets | Conditional loading based on content analysis |
| **Bundle Optimisation** | WordPress optimised | Custom optimisation with advanced features |
| **Caching** | Standard webpack caching | Enhanced caching with Enqueues system |
| **Inline Assets** | Manual implementation | Built-in inline asset support |

### **Development Experience**

| Aspect | @wordpress/scripts | Enqueues Webpack |
|--------|-------------------|------------------|
| **Learning Curve** | Low, WordPress standard | Higher, custom system |
| **Documentation** | Extensive WordPress docs | Custom documentation |
| **Community Support** | Large WordPress community | Smaller, specialized community |
| **Debugging** | Standard webpack tools | Enhanced debugging with Enqueues warnings |

---

## When to Use Each Approach

### **Choose @wordpress/scripts When:**

- **Simple Block Development**: Building individual blocks with minimal customisation
- **WordPress Standards**: Preferring official WordPress tooling and conventions
- **Small Teams**: Limited WordPress development experience
- **Quick Prototyping**: Need to get blocks working quickly
- **Maintenance Concerns**: Want WordPress team to handle updates and maintenance
- **Simple Projects**: Basic themes with few custom blocks

**Example Use Case:**
```json
// Simple block.json for @wordpress/scripts
{
  "name": "my-plugin/my-block",
  "editorScript": "file:./index.js",
  "editorStyle": "file:./index.css",
  "viewStyle": "file:./style-index.css"
}
```

### **Choose Enqueues Webpack When:**

- **Complex Theme Development**: Large themes with many blocks and page types
- **Performance Critical**: Need optimised asset loading and conditional loading
- **Advanced Features**: Require inline assets, caching, or custom optimisation
- **Scalability**: Building systems that need to scale with many blocks
- **Integration**: Want seamless PHP/JavaScript asset management integration
- **Custom Workflows**: Need advanced build automation and asset discovery

**Example Use Case:**
```javascript
// Advanced webpack config with Enqueues
const entries = enqueuesMergeWebpackEntries(
  {
    // Manual theme entries
    'main': ['./source/js/scripts.js'],
    'search': ['./source/js/search-app/index.js'],
  },
  // Automatic theme asset discovery
  enqueuesWebpackEntries(rootDir, path, glob),
  // Automatic block editor discovery
  enqueuesBlockEditorWebpackEntries(rootDir, path, glob, 'source/block-editor')
);
```

---

## Migration Strategies

### **From @wordpress/scripts to Enqueues Webpack**

1. **Phase 1: Setup**
   ```bash
   # Install Enqueues dependencies
   composer require thecodeco/enqueues:dev-main
   ```

2. **Phase 2: Restructure**
   ```
   src/
   ├── block.json          # Move to source/block-editor/blocks/my-block/
   ├── index.js
   ├── style.scss
   └── render.php
   ```

3. **Phase 3: Configure**
   ```javascript
   // webpack.config.babel.js
   import enqueuesBlockEditorWebpackEntries from 'path/to/enqueues';
   
   const entries = enqueuesBlockEditorWebpackEntries(rootDir, path, glob);
   ```

4. **Phase 4: Update Scripts**
   ```json
   {
     "scripts": {
       "dev": "webpack --watch",
       "build": "webpack --mode=production"
     }
   }
   ```

### **From Enqueues Webpack to @wordpress/scripts**

1. **Phase 1: Consolidate**
   - Move all blocks to single `src/` directory
   - Create single `block.json` file

2. **Phase 2: Simplify**
   - Remove custom webpack configuration
   - Use standard `@wordpress/scripts` commands

3. **Phase 3: Manual Enqueuing**
   - Implement manual asset enqueuing system
   - Remove Enqueues PHP integration

---

## Performance Comparison

### **Asset Loading Efficiency**

**@wordpress/scripts:**
- Loads all registered assets on every page
- No conditional loading
- Standard webpack optimisation

**Enqueues Webpack:**
- Conditional loading based on content analysis
- Inline critical assets
- Advanced caching and optimisation

### **Build Time**

**@wordpress/scripts:**
- Faster initial builds
- Standard webpack compilation

**Enqueues Webpack:**
- Slightly longer builds due to scanning and analysis
- More efficient incremental builds

### **Runtime Performance**

**@wordpress/scripts:**
- Standard asset loading
- All assets loaded regardless of need

**Enqueues Webpack:**
- Optimised asset loading
- Reduced bundle sizes through conditional loading

---

## Maintenance Considerations

### **@wordpress/scripts Maintenance**

**Pros:**
- WordPress team handles updates
- Standard tooling with wide support
- Regular security updates

**Cons:**
- Limited to WordPress feature set
- No custom optimisations
- Dependent on WordPress release cycle

### **Enqueues Webpack Maintenance**

**Pros:**
- Full control over build process
- Custom optimisations and features
- Independent of WordPress release cycle

**Cons:**
- Self-maintained system
- Requires expertise to update
- Potential for breaking changes

---

## Decision Framework

### **Questions to Consider:**

1. **Project Complexity**
   - How many blocks/plugins/extensions do you need?
   - Is performance optimisation critical?

2. **Team Expertise**
   - What's your team's WordPress development experience?
   - Do you have resources to maintain custom tooling?

3. **Long-term Goals**
   - Are you building for scalability or simplicity?
   - Do you need advanced features like conditional loading?

4. **Performance Requirements**
   - Is page load speed critical for your users?
   - Do you need advanced caching and optimisation?

### **Scoring System:**

Rate each factor from 1-5 (1 = @wordpress/scripts better, 5 = Enqueues better):

- **Complexity**: How complex is your project?
- **Performance**: How critical is performance optimisation?
- **Team Expertise**: How experienced is your team with custom tooling?
- **Maintenance Resources**: Do you have resources to maintain custom systems?
- **Scalability**: Do you need to scale to many blocks/features?

**Scoring:**
- **15-25**: Choose @wordpress/scripts
- **26-35**: Consider Enqueues for specific features
- **36-50**: Enqueues is likely the better choice

---

## Conclusion

Both approaches have their place in WordPress development:

- **@wordpress/scripts** excels at simplicity, standards compliance, and ease of use
- **Enqueues Webpack** excels at performance, scalability, and advanced features

The choice depends on your project's specific needs, team expertise, and long-term goals. For simple projects, @wordpress/scripts provides everything you need. For complex, performance-critical projects, Enqueues webpack automation offers significant advantages.

Consider starting with @wordpress/scripts for simple projects and migrating to Enqueues webpack as your needs grow more complex.
