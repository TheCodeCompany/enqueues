/**
 * File Path: /src/js/js-filename-generator.js
 */

/**
 * @function jsFilenameGenerator
 * @param {object} chunk - The Webpack chunk object.
 * @param {object} pathModule - The `path` module to use for resolving paths.
 * @param {object} globModule - The `glob` module to use for matching file patterns (optional).
 * @param {boolean} devMode - Whether in development mode or not.
 * @param {object} blockeditorDirectories - The directories map to determine block types.
 * @param {string} [distJSDir='js'] - The base directory for JavaScript files.
 * @param {string} [distBlockEditorDir='block-editor'] - The base directory for block editor assets.
 * @description Generates the filename for Webpack based on the chunk name.
 * @return {string} - The generated JS filename.
 */
const jsFilenameGenerator = (chunk, pathModule, globModule, devMode, blockeditorDirectories = {}, distJSDir = 'js', distBlockEditorDir = 'block-editor') => {
    const name = chunk.name;

    // Check if blockeditorDirectories exists and is a valid object.
    if (blockeditorDirectories && Object.keys(blockeditorDirectories).length > 0) {
        // Loop over blockeditorDirectories to match the chunk name.
        for (const [key, value] of Object.entries(blockeditorDirectories)) {
            if (name.startsWith(key)) {
                // Generate filenames based on specific keywords in the chunk name.
                if (name.includes('/script')) {
                    return `${distBlockEditorDir}/${name}.js`;
                } else if (name.includes('/index')) {
                    return `${distBlockEditorDir}/${name}.js`;
                } else if (name.includes('/view')) {
                    return `${distBlockEditorDir}/${name}.js`;
                } else {
                    return `${distBlockEditorDir}/${name}/index.js`;
                }
            }
        }
    }

    // Fallback to standard JS file naming if no chunk name match in blockeditorDirectories.
    const baseName = pathModule.basename(name);
    return devMode ? `${distJSDir}/${baseName}.js` : `${distJSDir}/${baseName}.min.js`;
};

module.exports = jsFilenameGenerator;
