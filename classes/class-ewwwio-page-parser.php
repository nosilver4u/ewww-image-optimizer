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
/**
 * HTML element and attribute parsing, replacing, etc.
 */
class EWWWIO_Page_Parser {

	/**
	 * Match all images and any relevant <a> tags in a block of HTML.
	 *
	 * @param string $content Some HTML.
	 * @param bool   $hyperlinks Default true. Should we include encasing hyperlinks in our search.
	 * @return array An array of $images matches, where $images[0] is
	 *         an array of full matches, and the link_url, img_tag,
	 *         and img_url keys are arrays of those matches.
	 */
	function get_images_from_html( $content, $hyperlinks = true ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		$images = array();

		if ( $hyperlinks ) {
			$search_pattern = '#(?:<a[^>]+?href\s*=\s*["\'](?P<link_url>[^\s]+?)["\'][^>]*?>\s*)?(?P<img_tag><img[^>]*?\s+?src\s*=\s*["\'](?P<img_url>[^\s]+?)["\'].*?>){1}(?:\s*</a>)?#is';
		} else {
			$search_pattern = '#(?P<img_tag><img[^>]*?\s+?src\s*=\s*["\'](?P<img_url>[^\s]+?)["\'].*?>)#is';
		}
		if ( preg_match_all( $search_pattern, $content, $images ) ) {
			foreach ( $images as $key => $unused ) {
				// Simplify the output as much as possible.
				if ( is_numeric( $key ) && $key > 0 ) {
					unset( $images[ $key ] );
				}
			}
			return $images;
		}
		return array();
	}

	/**
	 * Match all images wrapped in <noscript> tags in a block of HTML.
	 *
	 * @param string $content Some HTML.
	 * @return array An array of $images matches, where $images[0] is
	 *         an array of full matches, and the noscript_tag, img_tag,
	 *         and img_url keys are arrays of those matches.
	 */
	function get_noscript_images_from_html( $content ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		$images = array();

		if ( preg_match_all( '#(?P<noscript_tag><noscript[^>]*?>\s*)(?P<img_tag><img[^>]*?\s+?src\s*=\s*["\'](?P<img_url>[^\s]+?)["\'].*?>){1}(?:\s*</noscript>)?#is', $content, $images ) ) {
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
	 * Match all sources wrapped in <picture> tags in a block of HTML.
	 *
	 * @param string $content Some HTML.
	 * @return array An array of $pictures matches, containing full elements with ending tags.
	 */
	function get_picture_tags_from_html( $content ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		$pictures = array();
		if ( preg_match_all( '#(?:<picture[^>]*?>\s*)(?:<source[^>]*?>)+(?:.*?</picture>)?#is', $content, $pictures ) ) {
			return $pictures[0];
		}
		return array();
	}

	/**
	 * Match all elements by tag name in a block of HTML. Does not retrieve contents or closing tags.
	 *
	 * @param string $content Some HTML.
	 * @param string $tag_name The name of the elements to retrieve.
	 * @return array An array of $elements.
	 */
	function get_elements_from_html( $content, $tag_name ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		if ( ! ctype_alpha( $tag_name ) ) {
			return array();
		}
		if ( preg_match_all( '#<' . $tag_name . '[^>]+?>#is', $content, $elements ) ) {
			return $elements[0];
		}
		return array();
	}

	/**
	 * Try to determine height and width from strings WP appends to resized image filenames.
	 *
	 * @param string $src The image URL.
	 * @return array An array consisting of width and height.
	 */
	function get_dimensions_from_filename( $src ) {
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
		if ( preg_match( '#' . $name . '\s*=\s*(["\'])([^\1]+?)\1#is', $element, $attr_matches ) ) {
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
	 * @param string $element The HTML element to modify. Passed by reference.
	 * @param string $name The name of the attribute to set.
	 * @param string $value The value of the attribute to set.
	 * @param bool   $replace Default false. True to replace, false to append.
	 */
	function set_attribute( &$element, $name, $value, $replace = false ) {
		if ( $replace ) {
			$new_element = preg_replace( '#' . $name . '\s*=\s*(["\'])([^\1]+?)\1#is', "$name=$1$value$1", $element );
			if ( $new_element !== $element ) {
				$element = $new_element;
				return;
			}
			$element = preg_replace( '#' . $name . '\s*=\s*([^\s]+?)#is', '', $element );
		}
		if ( false === strpos( $value, '"' ) ) {
			$element = rtrim( $element, '>' ) . " $name=\"$value\">";
			return;
		}
		$element = rtrim( $element, '>' ) . " $name='$value'>";
	}

	/**
	 * Remove an attribute from an HTML element.
	 *
	 * @param string $element The HTML element to modify. Passed by reference.
	 * @param string $name The name of the attribute to remove.
	 */
	function remove_attribute( &$element, $name ) {
		$element = preg_replace( '#' . $name . '\s*=\s*(["\'])([^\1]+?)\1#is', '', $element );
		$element = preg_replace( '#' . $name . '\s*=\s*([^\s]+?)#is', '', $element );
	}

	/**
	 * A wrapper for PHP's parse_url, prepending assumed scheme for network path
	 * URLs. PHP versions 5.4.6 and earlier do not correctly parse without scheme.
	 *
	 * @param string  $url The URL to parse.
	 * @param integer $component Retrieve specific URL component.
	 * @return mixed Result of parse_url.
	 */
	function parse_url( $url, $component = -1 ) {
		if ( 0 === strpos( $url, '//' ) ) {
			$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
		}
		if ( false === strpos( $url, 'http' ) && '/' !== substr( $url, 0, 1 ) ) {
			$url = ( is_ssl() ? 'https://' : 'http://' ) . $url;
		}
		// Because encoded ampersands in the filename break things.
		$url = str_replace( '&#038;', '&', $url );
		return parse_url( $url, $component );
	}
}
