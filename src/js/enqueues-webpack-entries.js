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
    // Verbose logging is opt-in (ENQUEUES_DEBUG) so builds/CI stay quiet and absolute paths are not
    // leaked into shared CI output. Build errors (safeGlobSync failures) still log unconditionally.
    const verbose = !!process.env.ENQUEUES_DEBUG;
    const log = (...args) => { if (verbose) console.log(...args); };

    log('rootDir:', rootDir);
    log('srcDirJS:', srcDirJS);
    log('srcDirCSS:', srcDirCSS);

    const safeGlobSync = (pattern) => {
        try {
            return globModule.sync(pattern);
        } catch (error) {
            console.error(`Failed to resolve pattern: ${pattern}`, error);
            return [];
        }
    };

    // Reduce a list of file paths to an entry map keyed by basename, warning LOUDLY on any collision so
    // a dropped asset (two files sharing a basename in different dirs) is visible at build time instead
    // of silently missing in production.
    const toEntries = (files, label) =>
        files.reduce((obj, el) => {
            const name = pathModule.parse(el).name;
            if (Object.prototype.hasOwnProperty.call(obj, name)) {
                console.warn(`[enqueues] ${label} entry name collision on "${name}": "${obj[name][0]}" is overwritten by "${el}". Rename one so both build.`);
            }
            obj[name] = [el];
            return obj;
        }, {});

    // Use dynamic directories for JS and SCSS files, including one level down
    const entriesJS = toEntries(
        safeGlobSync(pathModule.resolve(rootDir, srcDirJS, '*.js'))
            .concat(safeGlobSync(pathModule.resolve(rootDir, '*', srcDirJS, '*.js'))),
        'JS'
    );

    const entriesCSS = toEntries(
        safeGlobSync(pathModule.resolve(rootDir, srcDirCSS, `*.${cssFileExt}`))
            .concat(safeGlobSync(pathModule.resolve(rootDir, '*', srcDirCSS, `*.${cssFileExt}`))),
        'CSS'
    );

    const entries = enqueuesMergeThemeWebpackEntries(entriesJS, entriesCSS);

    log('Generated Entries from Enqueues Webpack Entries:', entries);

    return entries;
};

module.exports = enqueuesWebpackEntries;
