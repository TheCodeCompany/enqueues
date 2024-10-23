/**
 * File Path: /src/js/enqueues-editor-webpack-entries.js
 */

const enqueuesMergeWebpackEntries = require('./enqueues-merge-webpack-entries');

/**
 * @function enqueuesBlockEditorWebpackEntries
 * @param {string} rootDir - The root directory path.
 * @param {object} pathModule - The `path` module to use for resolving paths.
 * @param {object} globModule - The `glob` module to use for matching file patterns.
 * @param {string} [srcDirJS='src/js'] - Directory path for JavaScript files.
 * @param {string} [srcDirCSS='src/sass'] - Directory path for SCSS files.
 * @param {string} [cssFileExt='scss'] - File extension for CSS files.
 * @description A function to dynamically resolve and group entry points for Webpack configuration.
 */
const enqueuesBlockEditorWebpackEntries = (rootDir, pathModule, globModule, srcDir = 'src/block-editor') => {
    // Log for debugging
    console.log('enqueuesBlockEditorWebpackEntries rootDir:', rootDir);
    console.log('enqueuesBlockEditorWebpackEntries srcDir:', srcDir);

    const safeGlobSync = (pattern) => {
        try {
            return globModule.sync(pattern);
        } catch (error) {
            console.error(`Failed to resolve pattern: ${pattern}`, error);
            return [];
        }
    };

    /**
     * @function getBlockEditorEntries
     * @param {string} type - The type of entries (blocks, plugins, extensions).
     * @param {string} fileType - The file type (js, js-script, css, css-view).
     * @param {string} fileExt - The file extension (js, scss).
     * @description Returns an object with the resolved entry points.
     */
    const getBlockEditorEntries = (type, fileType, fileExt) => {
        const fileGlob =
            fileType === 'js-editor'
                ? 'index'
                : fileType === 'js-script'
                ? 'script'
                : fileType === 'js-view'
                ? 'view'
                : fileType === 'css-editor'
                ? 'editor'
                : fileType === 'css-style'
                ? 'style'
                : fileType === 'css-view'
                ? 'view'
                : '';

        return safeGlobSync(`${rootDir}/${srcDir}/${type}/*/${fileGlob}.${fileExt}`)
            .reduce((obj, el) => {
                const name = pathModule.basename(pathModule.dirname(el));
                obj[`${type}/${name}/${fileGlob}`] = el;
                return obj;
            }, {});
    };

    // Merge all JS and CSS entries for blocks, plugins, and extensions.
    const mergedEntries = enqueuesMergeWebpackEntries(
        getBlockEditorEntries('blocks', 'js-editor', 'js'),
        getBlockEditorEntries('blocks', 'js-script', 'js'),
        getBlockEditorEntries('blocks', 'js-view', 'js'),
        getBlockEditorEntries('blocks', 'css-editor', 'scss'),
        getBlockEditorEntries('blocks', 'css-style', 'scss'),
        getBlockEditorEntries('blocks', 'css-view', 'scss'),

        getBlockEditorEntries('plugins', 'js-editor', 'js'),
        getBlockEditorEntries('plugins', 'js-script', 'js'),
        getBlockEditorEntries('plugins', 'js-view', 'js'),
        getBlockEditorEntries('plugins', 'css-style', 'scss'),
        getBlockEditorEntries('plugins', 'css-view', 'scss'),

        getBlockEditorEntries('extensions', 'js-editor', 'js'),
        getBlockEditorEntries('extensions', 'js-script', 'js'),
        getBlockEditorEntries('extensions', 'js-view', 'js'),
        getBlockEditorEntries('extensions', 'css-editor', 'scss'),
        getBlockEditorEntries('extensions', 'css-style', 'scss'),
        getBlockEditorEntries('extensions', 'css-view', 'scss')
    );

    console.log('Generated Entries from Enqueues Block Editor Webpack Entries:', mergedEntries);

    return mergedEntries;
};

module.exports = enqueuesBlockEditorWebpackEntries;
