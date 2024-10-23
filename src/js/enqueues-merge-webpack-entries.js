/**
 * File Path: /src/js/enqueues-merge-theme-webpack-entries.js
 */

/**
 * @function enqueuesMergeWebpackEntries
 * @param {...object} entriesObjects - Multiple entries objects to be merged.
 * @description Merges multiple entries objects into one. If a key is already present, it groups multiple entries into an array.
 * @return {object} - The merged entries object.
 */
const enqueuesMergeWebpackEntries = (...entriesObjects) => {
    const mergedEntries = {};

    entriesObjects.forEach(entriesObj => {
        if (!entriesObj || Object.keys(entriesObj).length === 0) {
            // Skip empty or undefined entries objects.
            return;
        }

        Object.keys(entriesObj).forEach((key) => {
            const currentEntry = entriesObj[key];

            // Ensure the merged entries are always arrays.
            if (Array.isArray(mergedEntries[key])) {
                mergedEntries[key] = mergedEntries[key].concat(currentEntry);
            } else if (mergedEntries[key]) {
                mergedEntries[key] = [mergedEntries[key]].concat(currentEntry);
            } else {
                // If it's the first time we're adding this entry, ensure it's an array.
                mergedEntries[key] = Array.isArray(currentEntry) ? currentEntry : [currentEntry];
            }
        });
    });

    return mergedEntries;
};

module.exports = enqueuesMergeWebpackEntries;
