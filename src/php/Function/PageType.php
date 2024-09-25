<?php
/**
 * Page Type helper funcitons.
 *
 * File Path: src/php/Function/PageType.php
 *
 * @package Enqueues
 */

namespace Enqueues;

use WP_Query;

/**
 * Useful function to retrieve the page type.
 *
 * @return string
 */
function get_page_type(): string {
	global $wp_query;

	$page_type_map = [ 
		'home'     => 'homepage',
		'page'     => $wp_query->is_front_page() ? 'homepage' : 'page',
		'single'   => $wp_query->is_attachment() ? 'attachment' : 'single',
		'category' => 'category',
		'tag'      => 'tag',
		'tax'      => 'tax',
		'author'   => 'author',
		'archive'  => 'archive',
		'search'   => 'search',
		'404'      => 'notfound',
	];

	foreach ( $page_type_map as $query_check => $type ) {
		$method = "is_{$query_check}";
		if ( $wp_query->$method() ) {
			return $type;
		}
	}

	return 'custom';
}

/**
 * Checks if the given page type is an archive page.
 *
 * @param string $page_type The page type being checked.
 *
 * @return bool
 */
function is_page_type_archive( $page_type ): bool {
	$archive_types = [ 'category', 'tag', 'tax', 'author', 'archive' ];
	return in_array( $page_type, $archive_types, true );
}

/**
 * Checks if the current page is the first page in a paginated set.
 *
 * @return bool
 */
function is_first_page(): bool {
	global $wp_query;

	return $wp_query instanceof WP_Query && 0 === (int) $wp_query->query_vars['paged'];
}
