/**
 * File Path: /src/js/js-filename-generator.js
 */

/**
 * @function jsFilenameGenerator
 * @param {object} chunk - The Webpack chunk object.
 * @param {object} blockeditorDirectories - The directories map to determine block types.
 * @param {boolean} devMode - Whether in development mode or not.
 * @description Generates the filename for Webpack based on the chunk name.
 * @return {string} - The generated JS filename.
 */
const jsFilenameGenerator = (chunk, blockeditorDirectories, devMode, distJSDir = 'js', distBlockEditorDir = 'block-editor') => {
    const name = chunk.name;

    for (const [key, value] of Object.entries(blockeditorDirectories)) {
        if (name.startsWith(key)) {
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

    const baseName = path.basename(name);
    return devMode ? `${distJSDir}/${baseName}.js` : `${distJSDir}/${baseName}.min.js`;
};

module.exports = jsFilenameGenerator;
