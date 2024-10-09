/**
 * File Path: /src/js/enqueues-copy-plugin-config-pattern.js
 */

/**
 * Extracts relevant path segments based on the provided keyword.
 *
 * @param {Object} pathContext - The context object containing the absolute file path.
 * @param {string} keyword - The keyword to identify the relevant path segment.
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
 * @param {string} rootDir - The root directory path.
 * @param {string} distDir - The distribution directory path.
 * @param {string} from    - The file matching pattern to copy.
 * @returns {Object} A CopyPlugin pattern configuration for images.
 */
function getCopyPluginConfigImagePattern(rootDir, distDir, from = '**/src/images/**/*') {
	return {
		context: rootDir,
		from: from,
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
 * @param {string} rootDir - The root directory path.
 * @param {string} distDir - The distribution directory path.
 * @param {string} from    - The file matching pattern to copy.
 * @returns {Object} A CopyPlugin pattern configuration for fonts.
 */
function getCopyPluginConfigFontPattern(rootDir, distDir, from = '**/src/fonts/**/*') {
	return {
		context: rootDir,
		from: from,
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
 * @param {string} rootDir - The root directory path.
 * @param {string} distDir - The distribution directory path.
 * @param {string} from    - The file matching pattern to copy.
 * @returns {Object} A CopyPlugin pattern configuration for Gutenberg block JSON files.
 */
function getCopyPluginConfigBlockJsonPattern(rootDir, distDir, from = '**/src/gutenberg/blocks/**/block.json') {
	return {
		context: rootDir,
		from: from,
		to: (pathContext) => {
			const segments = pathContext.absoluteFilename.split('/');
			const blockName = segments[segments.length - 2];
			return `${distDir}/gutenberg/blocks/${blockName}/block.json`;
		},
		transform(content, absolutePath) {
			const contentStr = content.toString();
			const segments = absolutePath.split('/');
			return contentStr.replace('[1]', segments[segments.length - 2]);
		},
		noErrorOnMissing: true,
	};
}

/**
 * Generates a CopyPlugin configuration for copying Gutenberg block PHP render files.
 *
 * @param {string} rootDir - The root directory path.
 * @param {string} distDir - The distribution directory path.
 * @param {string} from    - The file matching pattern to copy.
 * @returns {Object} A CopyPlugin pattern configuration for Gutenberg block PHP render files.
 */
function getCopyPluginConfigRenderPhpPattern(rootDir, distDir, from = '**/src/gutenberg/blocks/**/render.php') {
	return {
		context: rootDir,
		from: from,
		to: (pathContext) => {
			const segments = pathContext.absoluteFilename.split('/');
			const blockName = segments[segments.length - 2];
			return `${distDir}/gutenberg/blocks/${blockName}/render.php`;
		},
		transform(content, absolutePath) {
			const contentStr = content.toString();
			const segments = absolutePath.split('/');
			return contentStr.replace('[1]', segments[segments.length - 2]);
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
function enqueuesGetCopyPluginConfigPattern(rootDir, distDir, context, from) {
	switch (context) {
		case 'images':
			return getCopyPluginConfigImagePattern(rootDir, distDir, from);
		case 'fonts':
			return getCopyPluginConfigFontPattern(rootDir, distDir, from);
		case 'block-json':
			return getCopyPluginConfigBlockJsonPattern(rootDir, distDir, from);
		case 'render-php':
			return getCopyPluginConfigRenderPhpPattern(rootDir, distDir, from);
		default:
			throw new Error(`Unknown context: ${context}`);
	}
}

module.exports = enqueuesGetCopyPluginConfigPattern;
