/**
 * File Path: /enqueues/src/js/enqueues-merge-theme-webpack-entries.js
 */
/**
 * @function enqueuesMergeThemeWebpackEntries
 * @param {object} entriesObj - The existing entries object.
 * @param {object} newEntries - New entries to be merged.
 * @description Merges new entries into an existing entries object. If a key is already present, it groups multiple entries into an array.
 * @return {object} - The modified entries object.
 */
const enqueuesMergeThemeWebpackEntries = (entriesObj, newEntries) => {
    Object.keys(newEntries).forEach((key) => {
        if (Array.isArray(entriesObj[key])) {
            entriesObj[key] = [...entriesObj[key], ...newEntries[key]];
        } else {
            entriesObj[key] = newEntries[key];
        }
    });
    return entriesObj; // Return the modified object
};

module.exports = enqueuesMergeThemeWebpackEntries;