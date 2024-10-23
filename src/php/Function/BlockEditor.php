<?php
/**
 * Block Editor helper functions.
 *
 * File Path: src/php/Function/BlockEditor.php
 *
 * @package Enqueues
 */

namespace Enqueues;

function is_block_editor_features_on() {
	return (bool) apply_filters( 'enqueues_is_block_editor_features_on', true );
}

/**
 * Retrieves the block editor namespace.
 *
 * @return string
 */
function get_block_editor_namespace(): string {
	return (string) apply_filters( 'enqueues_block_editor_namespace', 'custom' );
}

/**
 * Retrieves the block editor dist directory.
 *
 * @return string
 */
function get_block_editor_dist_dir(): string {
	return (string) apply_filters( 'enqueues_block_editor_dist_dir', '/dist/block-editor/blocks' );
}

/**
 * Retrieves the block editor categories.
 *
 * @return array
 */
function get_block_editor_categories(): array {
	return (array) apply_filters( 'enqueues_block_editor_categories', [] );
}
