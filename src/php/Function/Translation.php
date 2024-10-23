<?php
/**
 * Translation helper functions.
 *
 * File Path: src/php/Function/Translation.php
 *
 * @package Enqueues
 */

namespace Enqueues;

/**
 * Retrieves the translation domain.
 *
 * @return string
 */
function get_translation_domain(): string {

	return (string) apply_filters( 'enqueues_translation_domain', 'custom' );
}