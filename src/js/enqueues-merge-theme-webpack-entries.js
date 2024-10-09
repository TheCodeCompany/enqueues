/**
 * File Path: /src/js/enqueues-merge-theme-webpack-entries.js
 */

/**
 * @function enqueuesMergeThemeWebpackEntries
 * @param {...object} entriesObjects - Multiple entries objects to be merged.
 * @description Merges multiple entries objects into one. If a key is already present, it groups multiple entries into an array.
 * @return {object} - The merged entries object.
 */
const enqueuesMergeThemeWebpackEntries = (...entriesObjects) => {
    const mergedEntries = {};

    entriesObjects.forEach(entriesObj => {
        if (!entriesObj || Object.keys(entriesObj).length === 0) {
            // Skip empty or undefined entries objects
            return;
        }

        Object.keys(entriesObj).forEach((key) => {
            if (Array.isArray(mergedEntries[key])) {
                mergedEntries[key] = [...mergedEntries[key], ...entriesObj[key]];
            } else if (mergedEntries[key]) {
                mergedEntries[key] = [mergedEntries[key], ...entriesObj[key]];
            } else {
                mergedEntries[key] = entriesObj[key];
            }
        });
    });

    return mergedEntries;
};

module.exports = enqueuesMergeThemeWebpackEntries;
