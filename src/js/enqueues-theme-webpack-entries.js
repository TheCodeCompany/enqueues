/**
 * File Path: /enqueues/src/js/enqueues-theme-webpack-entries.js
 */
const enqueuesMergeThemeWebpackEntries = require('./enqueues-merge-theme-webpack-entries');

/**
 * @function enqueuesThemeWebpackEntries
 * @param {string} rootDir - The root directory path.
 * @param {object} pathModule - The `path` module to use for resolving paths.
 * @param {object} globModule - The `glob` module to use for matching file patterns.
 * @param {object} [options] - Optional configuration for custom directories.
 * @param {string} [options.jsSrcDir='src/js'] - Directory path for JavaScript files.
 * @param {string} [options.scssSrcDir='src/sass'] - Directory path for SCSS files.
 * @description A function to dynamically resolve and group entry points for Webpack configuration.
 */
const enqueuesThemeWebpackEntries = (rootDir, pathModule, globModule, srcDirJS = 'src/js', srcDirCSS = 'src/scss' ) => {

	const safeGlobSync = (pattern) => {
		try {
			return globModule.sync(pattern);
		} catch (error) {
			console.error(`Failed to resolve pattern: ${pattern}`, error);
			return [];
		}
	};

	// Use dynamic directories for JS and SCSS files
	const entriesJS = safeGlobSync(pathModule.join(rootDir, `${srcDirJS}/*.js`))
		.reduce((obj, el) => {
			const name = pathModule.parse(el).name;
			obj[name] = [el];
			return obj;
		}, {});

	const entriesCSS = safeGlobSync(pathModule.join(rootDir, `${srcDirCSS}/*.scss`))
		.reduce((obj, el) => {
			const name = pathModule.parse(el).name;
			obj[name] = [el];
			return obj;
		}, {});

	const entries = enqueuesMergeThemeWebpackEntries(entriesJS, entriesCSS);

	console.log('entriesJS', entriesJS);
	console.log('entriesCSS', entriesCSS);
	console.log('Generated entries:', entries);

	return entries;
};

module.exports = enqueuesThemeWebpackEntries;
