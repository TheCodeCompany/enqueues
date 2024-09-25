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
