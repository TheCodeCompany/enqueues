<?php
/**
 * String manipulation functions.
 *
 * File Path: src/php/Function/String.php
 *
 * @package Enqueues
 */

namespace Enqueues;

/**
 * Returns a slug friendly string.
 *
 * @param string $str  The string to convert.
 * @param string $glue The glue between each slug piece.
 *
 * @return  string
 */
function string_slugify( $str = '', $glue = '-' ) {

	$raw = $str;

	$slug = strtolower( remove_accents( $str ) );
	$slug = str_replace( [ '_', '-', '/', ' ' ], $glue, $slug );
	$slug = preg_replace( '/\s+/', '-', $slug ); // whitespace.
	$slug = preg_replace( '/[^A-Za-z0-9-]/', '', $slug ); // symbols.

	/**
	 * Filters the slug created by string_slugify().
	 *
	 * @param string $slug The newly created slug.
	 * @param string $raw  The original string.
	 * @param string $glue The separator used to join the string into a slug.
	 */
	return apply_filters( 'string_slugify', $slug, $raw, $glue );
}

/**
 * Returns a camelCase string.
 *
 * @param string $str The string to convert.
 *
 * @return string
 */
function string_camelcaseify( $str = '' ) {

	$raw = $str;

	$string_camelcase = strtolower( remove_accents( $str ) );
	$string_camelcase = preg_replace( '/[^A-Za-z0-9-_\s]/', '', $string_camelcase ); // Remove symbols, keep spaces, hyphens, and underscores.
	$string_camelcase = ucwords( str_replace( [ '-', '_', '/', ' ' ], ' ', $string_camelcase ) );
	$string_camelcase = lcfirst( str_replace( ' ', '', $string_camelcase ) );

	/**
	 * Filters the camelCased string created by string_camelcaseify().
	 *
	 * @param string $string_camelcase The newly created camelCased string.
	 * @param string $raw              The original string.
	 */
	return apply_filters( 'string_camelcaseify', $string_camelcase, $raw );
}
