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
function getCopyPluginConfigBlockJsonPattern(rootDir, distDir, srcDirPattern = '**/src', blockDir = 'block-editor/blocks' ) {
	return {
		context: rootDir,
		from: `${srcDirPattern}/${blockDir}/**/block.json`,
		to: (pathContext) => {
			const segments = pathContext.absoluteFilename.split('/');
			const blockName = segments[segments.length - 2];
			// Replace the base src path with distDir for final destination
			return `${distDir}/${blockDir}/${blockName}/block.json`;
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
function getCopyPluginConfigRenderPhpPattern(rootDir, distDir, srcDirPattern = '**/src', blockDir = 'block-editor/blocks' ) {
	return {
		context: rootDir,
		from: `${srcDirPattern}/${blockDir}/**/render.php`,
		to: (pathContext) => {
			const segments = pathContext.absoluteFilename.split('/');
			const blockName = segments[segments.length - 2];
			// Replace the base src path with distDir for final destination
			return `${distDir}/${blockDir}/${blockName}/render.php`;
		},
		noErrorOnMissing: true,
	};
}

/**
 * Normalise child directories for block child directory copying.
 *
 * @param {string|string[]} childDirs - Directory name or list of directory names.
 * @returns {string[]} Normalised list of child directories.
 */
function getNormalisedChildDirs(childDirs) {
	if (Array.isArray(childDirs)) {
		return childDirs
			.filter((childDir) => typeof childDir === 'string' && '' !== childDir.trim())
			.map((childDir) => childDir.trim());
	}

	if ('string' === typeof childDirs && '' !== childDirs.trim()) {
		return [childDirs.trim()];
	}

	return ['assets'];
}

/**
 * Generates a CopyPlugin configuration for copying block child directories recursively.
 *
 * @param {string} rootDir       - The root directory path.
 * @param {string} distDir       - The distribution directory path.
 * @param {string} srcDirPattern - The file matching pattern to copy.
 * @param {string} srcBlockDir   - The source directory containing blocks.
 * @param {string|string[]} childDirs - Child directory names to copy from each block.
 * @param {string} distBlockDir  - The destination directory to copy into.
 * @returns {Object} A CopyPlugin pattern configuration for block child directories.
 */
function getCopyPluginConfigBlockChildDirsPattern(
	rootDir,
	distDir,
	srcDirPattern = '**/src',
	srcBlockDir = 'block-editor/blocks',
	childDirs = ['assets'],
	distBlockDir = srcBlockDir
) {
	const normalisedChildDirs = getNormalisedChildDirs(childDirs);
	const childDirPattern = 1 === normalisedChildDirs.length
		? normalisedChildDirs[0]
		: `{${normalisedChildDirs.join(',')}}`;

	return {
		context: rootDir,
		from: `${srcDirPattern}/${srcBlockDir}/**/${childDirPattern}/**/*`,
		to: (pathContext) => {
			const blockDirFragment = `/${srcBlockDir}/`;
			const blockDirIndex = pathContext.absoluteFilename.lastIndexOf(blockDirFragment);

			if (-1 === blockDirIndex) {
				return `${distDir}/${pathContext.absoluteFilename.split('/').pop()}`;
			}

			const relativePath = pathContext.absoluteFilename.slice(blockDirIndex + 1);
			const sourceBlockDirParts = srcBlockDir.split('/').length;
			const relativeParts = relativePath.split('/').slice(sourceBlockDirParts).join('/');
			const destinationBlockDir = distBlockDir || srcBlockDir;

			return `${distDir}/${destinationBlockDir}/${relativeParts}`;
		},
		noErrorOnMissing: true,
	};
}

/**
 * Generates a CopyPlugin configuration pattern based on the provided context.
 *
 * @param {string} rootDir - The root directory path.
 * @param {string} distDir - The distribution directory path.
 * @param {string} context - The context specifying the type of files to copy ('images', 'fonts', 'block-json', 'render-php', 'block-child-dirs').
 * @param {string} srcDirPattern - The file matching pattern to copy. Defaults are provided within each pattern.
 * @param {string} srcBlockDir - The source directory containing blocks.
 * @param {string|string[]} childDirs - Child directory names to copy from each block.
 * @param {string} distBlockDir - The destination block directory.
 * @returns {Object} A CopyPlugin pattern configuration based on the provided context.
 * @throws {Error} If the context is unknown.
 */
function enqueuesGetCopyPluginConfigPattern(
	rootDir,
	distDir,
	context,
	srcDirPattern,
	srcBlockDir = 'block-editor/blocks',
	childDirs = ['assets'],
	distBlockDir = srcBlockDir
) {
	switch (context) {
		case 'images':
			return getCopyPluginConfigImagePattern(rootDir, distDir, srcDirPattern);
		case 'fonts':
			return getCopyPluginConfigFontPattern(rootDir, distDir, srcDirPattern);
		case 'block-json':
			return getCopyPluginConfigBlockJsonPattern(rootDir, distDir, srcDirPattern, srcBlockDir);
		case 'render-php':
			return getCopyPluginConfigRenderPhpPattern(rootDir, distDir, srcDirPattern, srcBlockDir);
		case 'block-child-dirs':
			return getCopyPluginConfigBlockChildDirsPattern(
				rootDir,
				distDir,
				srcDirPattern,
				srcBlockDir,
				childDirs,
				distBlockDir
			);
		default:
			throw new Error(`Unknown context: ${context}`);
	}
}

module.exports = enqueuesGetCopyPluginConfigPattern;
