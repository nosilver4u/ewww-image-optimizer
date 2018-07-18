<?php
/**
 * Implements basic page parsing functions.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// TODO: may remove content property.
/**
 * HTML element and attribute parsing, replacing, etc.
 */
class EWWWIO_Page_Parser {

	/**
	 * The content to parse.
	 *
	 * @access public
	 * @var string $content
	 */
	public $content = '';

	/**
	 * Match all images and any relevant <a> tags in a block of HTML.
	 *
	 * @param string $content Some HTML.
	 * @return array An array of $images matches, where $images[0] is
	 *         an array of full matches, and the link_url, img_tag,
	 *         and img_url keys are arrays of those matches.
	 */
	function parse_images_from_html( $content ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		$images = array();

		if ( preg_match_all( '#(?:<a[^>]+?href=["|\'](?P<link_url>[^\s]+?)["|\'][^>]*?>\s*)?(?P<img_tag><img[^>]*?\s+?src=["|\'](?P<img_url>[^\s]+?)["|\'].*?>){1}(?:\s*</a>)?#is', $content, $images ) ) {
			foreach ( $images as $key => $unused ) {
				// Simplify the output as much as possible, mostly for confirming test results.
				if ( is_numeric( $key ) && $key > 0 ) {
					unset( $images[ $key ] );
				}
			}
			return $images;
		}
		return array();
	}

	/**
	 * Try to determine height and width from strings WP appends to resized image filenames.
	 *
	 * @param string $src The image URL.
	 * @return array An array consisting of width and height.
	 */
	function parse_dimensions_from_filename( $src ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		$width_height_string = array();
		ewwwio_debug_message( "looking for dimensions in $src" );
		if ( preg_match( '#-(\d+)x(\d+)(@2x)?\.(?:' . implode( '|', $this->extensions ) . '){1}(?:\?.+)?$#i', $src, $width_height_string ) ) {
			$width  = (int) $width_height_string[1];
			$height = (int) $width_height_string[2];

			if ( strpos( $src, '@2x' ) ) {
				$width  = 2 * $width;
				$height = 2 * $height;
			}
			if ( $width && $height ) {
				ewwwio_debug_message( "found w$width h$height" );
				return array( $width, $height );
			}
		}
		return array( false, false );
	}

	/**
	 * Get an attribute from an HTML element.
	 *
	 * @param string $element The HTML element to parse.
	 * @param string $name The name of the attribute to search for.
	 * @return string The value of the attribute, or an empty string if not found.
	 */
	function get_attribute( $element, $name ) {
		if ( preg_match( '#' . $name . '\s*=\s*(["|\'])([^\1]+?)\1#is', $element, $attr_matches ) ) {
			if ( ! empty( $attr_matches[2] ) ) {
				return $attr_matches[2];
			}
		}
		// If there were not any matches with quotes, look for unquoted attributes, no spaces allowed.
		if ( preg_match( '#' . $name . '\s*=\s*([^\s]+?)#is', $element, $attr_matches ) ) {
			if ( ! empty( $attr_matches[1] ) ) {
				return $attr_matches[1];
			}
		}
		return '';
	}

	/**
	 * Set an attribute on an HTML element.
	 *
	 * @param string $element The HTML element to modify.
	 * @param string $name The name of the attribute to set.
	 * @param string $value The value of the attribute to set.
	 * @param bool   $replace Default false. True to replace, false to append.
	 * @return string The modified element.
	 */
	function set_attribute( $element, $name, $value, $replace = false ) {
		if ( $replace ) {
			$new_element = preg_replace( '#' . $name . '\s*=\s*(["|\'])([^\1]+?)\1#is', "$name=$1$value$1", $element );
			if ( $new_element !== $element ) {
				return $new_element;
			}
			$element = preg_replace( '#' . $name . '\s*=\s*([^\s]+?)#is', '', $element );
		}
		if ( false === strpos( $value '"' ) ) {
			return rtrim( $element, '>' ) . " $name=\"$value\">";
		}
		return rtrim( $element, '>' ) . " $name='$value'>";
	}
}
