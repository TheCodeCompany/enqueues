/**
 * File Path: /src/js/enqueues-webpack-entries.js
 */

const enqueuesMergeThemeWebpackEntries = require('./enqueues-merge-webpack-entries');

/**
 * @function enqueuesWebpackEntries
 * @param {string} rootDir - The root directory path.
 * @param {object} pathModule - The `path` module to use for resolving paths.
 * @param {object} globModule - The `glob` module to use for matching file patterns.
 * @param {string} [srcDirJS='src/js'] - Directory path for JavaScript files.
 * @param {string} [srcDirCSS='src/sass'] - Directory path for SCSS files.
 * @param {string} [cssFileExt='scss'] - File extension for CSS files.
 * @description A function to dynamically resolve and group entry points for Webpack configuration.
 */
const enqueuesWebpackEntries = (rootDir, pathModule, globModule, srcDirJS = 'src/js', srcDirCSS = 'src/sass', cssFileExt = 'scss') => {
    console.log('rootDir:', rootDir);
    console.log('srcDirJS:', srcDirJS);
    console.log('srcDirCSS:', srcDirCSS);

    const safeGlobSync = (pattern) => {
        try {
            return globModule.sync(pattern);
        } catch (error) {
            console.error(`Failed to resolve pattern: ${pattern}`, error);
            return [];
        }
    };

    // Use dynamic directories for JS and SCSS files, including one level down
    const entriesJS = safeGlobSync(pathModule.resolve(rootDir, srcDirJS, '*.js'))
        .concat(safeGlobSync(pathModule.resolve(rootDir, '*', srcDirJS, '*.js')))
        .reduce((obj, el) => {
            const name = pathModule.parse(el).name;
            obj[name] = [el];
            return obj;
        }, {});

    const entriesCSS = safeGlobSync(pathModule.resolve(rootDir, srcDirCSS, `*.${cssFileExt}`))
        .concat(safeGlobSync(pathModule.resolve(rootDir, '*', srcDirCSS, `*.${cssFileExt}`)))
        .reduce((obj, el) => {
            const name = pathModule.parse(el).name;
            obj[name] = [el];
            return obj;
        }, {});

    const entries = enqueuesMergeThemeWebpackEntries(entriesJS, entriesCSS);

    console.log('Generated Entries from Enqueues Webpack Entries:', entries);

    return entries;
};

module.exports = enqueuesWebpackEntries;
