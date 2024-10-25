/**
 * File Path: /src/js/css-filename-generator.js
 */

/**
 * @function cssFilenameGenerator
 * @param {object} chunk - The Webpack chunk object.
 * @param {boolean} devMode - Whether in development mode or not.
 * @param {object} pathModule - The `path` module to use for resolving paths.
 * @param {object} globModule - The `glob` module to use for matching file patterns.
 * @param {object} blockeditorDirectories - The directories map to determine block types.
 * @param {string} distCSSDir - The directory for general CSS files.
 * @param {string} distBlockEditorDir - The directory for block editor CSS files.
 * @description Generates the filename for the MiniCssExtractPlugin based on the chunk name.
 * @return {string} - The generated CSS filename.
 */
const cssFilenameGenerator = (
    chunk,
    devMode = false,
    pathModule = null,
    globModule = null,
    blockeditorDirectories = null,
    distCSSDir = 'css',
    distBlockEditorDir = 'block-editor'
) => {
    const name = chunk.name;
    const nameSegments = name.split('/');
    const blockName =
        nameSegments[nameSegments.length - 2] === 'blocks' ||
        nameSegments[nameSegments.length - 2] === 'plugins' ||
        nameSegments[nameSegments.length - 2] === 'extensions'
            ? nameSegments[nameSegments.length - 1]
            : nameSegments.slice(-2)[0];

    // Check if blockeditorDirectories is defined and valid
    if (typeof blockeditorDirectories !== 'undefined' && blockeditorDirectories) {
        for (const [key, value] of Object.entries(blockeditorDirectories)) {
            if (name.startsWith(key)) {
                if (name.includes('/editor')) {
                    return `${distBlockEditorDir}/${value}/${blockName}/index.css`;
                } else if (name.includes('/style')) {
                    return `${distBlockEditorDir}/${value}/${blockName}/style.css`;
                } else if (name.includes('/view')) {
                    return `${distBlockEditorDir}/${value}/${blockName}/view.css`;
                } else {
                    return `${distBlockEditorDir}/${value}/${blockName}/error.css`;
                }
            }
        }
    }

    // Fallback to default filename structure
    return devMode ? `${distCSSDir}/[name].css` : `${distCSSDir}/[name].min.css`;
};

module.exports = cssFilenameGenerator;
