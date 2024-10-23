<?php
/**
 * SVG helper functions.
 *
 * File Path: src/php/Function/SVG.php
 *
 * @package Enqueues
 */

namespace Enqueues;

/**
 * Generate base64-encoded SVG icon.
 *
 * @param string $file_path Path to the SVG file.
 *
 * @return string Base64-encoded SVG data URL.
 */
function get_encoded_svg_icon( $file_path ) {
    if ( file_exists( $file_path ) ) {
        $svg_content = file_get_contents( $file_path ); // phpcs:ignore

        if ( $svg_content ) {
            return 'data:image/svg+xml;base64,' . base64_encode( $svg_content ); // phpcs:ignore
        }
    }

    return '';
}
