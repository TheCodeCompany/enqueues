/**
 * File Path: /src/js/enqueues-copy-plugin-config-pattern.js
 */

/**
 * Extracts relevant path segments based on the provided keyword.
 *
 * @param {Object} pathContext - The context object containing the absolute file path.
 * @param {string} keyword     - The keyword to identify the relevant path segment.
 * @returns {Array} The extracted segments from the absolute file path.
 */
function getSegments(pathContext, keyword) {
	const segments = pathContext.absoluteFilename.split('/');
	const index = segments.indexOf(keyword);
	return segments.slice(index);
}

/**
 * Generates a CopyPlugin configuration for copying image files.
 *
 * @param {string} rootDir       - The root directory path.
 * @param {string} distDir       - The distribution directory path.
 * @param {string} srcDirPattern - The file matching pattern to copy.
 * @returns {Object} A CopyPlugin pattern configuration for images.
 */
function getCopyPluginConfigImagePattern(rootDir, distDir, srcDirPattern = '**/src/images/**/*') {
	return {
		context: rootDir,
		from: srcDirPattern,
		to: (pathContext) => {
			const relevantSegments = getSegments(pathContext, 'images');
			return `${distDir}/${relevantSegments.join('/')}`;
		},
		noErrorOnMissing: true,
	};
}

/**
 * Generates a CopyPlugin configuration for copying font files.
 *
 * @param {string} rootDir       - The root directory path.
 * @param {string} distDir       - The distribution directory path.
 * @param {string} srcDirPattern - The file matching pattern to copy.
 * @returns {Object} A CopyPlugin pattern configuration for fonts.
 */
function getCopyPluginConfigFontPattern(rootDir, distDir, srcDirPattern = '**/src/fonts/**/*') {
	return {
		context: rootDir,
		from: srcDirPattern,
		to: (pathContext) => {
			const relevantSegments = getSegments(pathContext, 'fonts');
			return `${distDir}/${relevantSegments.join('/')}`;
		},
		noErrorOnMissing: true,
	};
}

/**
 * Generates a CopyPlugin configuration for copying Gutenberg block JSON files.
 *
 * @param {string} rootDir       - The root directory path.
 * @param {string} distDir       - The distribution directory path.
 * @param {string} srcDirPattern - The file matching pattern to copy.
 * @param {string} blockDir      - The directory containing the blocks within the block editor.
 * @returns {Object}             - A CopyPlugin pattern configuration for Gutenberg block JSON files.
 */
function getCopyPluginConfigBlockJsonPattern(rootDir, distDir, srcDirPattern = '**/src', blockDir = '/block-editor/blocks' ) {
	return {
		context: rootDir,
		from: `${srcDirPattern}/${blockDir}/**/block.json`,
		to: (pathContext) => {
			const segments = pathContext.absoluteFilename.split('/');
			const blockName = segments[segments.length - 2];
			// Replace the base src path with distDir for final destination
			return `${distDir}${blockDir}/${blockName}/block.json`;
		},
		transform(content, absolutePath) {
			const contentStr = content.toString();
			const segments = absolutePath.split('/');
			const blockName = segments[segments.length - 2];
			return contentStr.replace('[1]', blockName);
		},
		noErrorOnMissing: true,
	};
}


/**
 * Generates a CopyPlugin configuration for copying Gutenberg block PHP render files.
 *
 * @param {string} rootDir       - The root directory path.
 * @param {string} distDir       - The distribution directory path.
 * @param {string} srcDirPattern - The file matching pattern to copy.
 * @param {string} blockDir      - The directory containing the blocks within the block editor.
 * @returns {Object} A CopyPlugin pattern configuration for Gutenberg block PHP render files.
 */
function getCopyPluginConfigRenderPhpPattern(rootDir, distDir, srcDirPattern = '**/src', blockDir = '/block-editor/blocks' ) {
	return {
		context: rootDir,
		from: `${srcDirPattern}/${blockDir}/**/render.php`,
		to: (pathContext) => {
			const segments = pathContext.absoluteFilename.split('/');
			const blockName = segments[segments.length - 2];
			// Replace the base src path with distDir for final destination
			return `${distDir}${blockDir}/${blockName}/render.php`;
		},
		transform(content, absolutePath) {
			const contentStr = content.toString();
			const segments = absolutePath.split('/');
			const blockName = segments[segments.length - 2];
			return contentStr.replace('[1]', blockName);
		},
		noErrorOnMissing: true,
	};
}

/**
 * Generates a CopyPlugin configuration pattern based on the provided context.
 *
 * @param {string} rootDir - The root directory path.
 * @param {string} distDir - The distribution directory path.
 * @param {string} context - The context specifying the type of files to copy ('images', 'fonts', 'block-json', 'render-php').
 * @param {string} from    - The file matching pattern to copy. Defaults are provided within each pattern.
 * @returns {Object} A CopyPlugin pattern configuration based on the provided context.
 * @throws {Error} If the context is unknown.
 */
function enqueuesGetCopyPluginConfigPattern(rootDir, distDir, context, srcDirPattern) {
	switch (context) {
		case 'images':
			return getCopyPluginConfigImagePattern(rootDir, distDir, srcDirPattern);
		case 'fonts':
			return getCopyPluginConfigFontPattern(rootDir, distDir, srcDirPattern);
		case 'block-json':
			return getCopyPluginConfigBlockJsonPattern(rootDir, distDir, srcDirPattern);
		case 'render-php':
			return getCopyPluginConfigRenderPhpPattern(rootDir, distDir, srcDirPattern);
		default:
			throw new Error(`Unknown context: ${context}`);
	}
}

module.exports = enqueuesGetCopyPluginConfigPattern;
