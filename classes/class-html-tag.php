<?php
/**
 * Implements HTML tag parsing object/methods.
 *
 * @link https://ewww.io
 * @package EIO
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTML tag and attribute parsing, replacing, etc.
 */
class HTML_Tag extends Base {

	/**
	 * The HTML of the tag being parsed.
	 *
	 * @access public
	 * @var string $html
	 */
	public $html = '';

	/**
	 * The name of the tag being parsed: img, div, link, etc.
	 *
	 * @access public
	 * @var string $name
	 */
	public $name = '';

	/**
	 * An associative array of attributes, their values, and positions.
	 *
	 * @access public
	 * @var bool|array $attributes
	 */
	public $attributes = false;

	/**
	 * Constructor, creates a new Tag_Parser object.
	 *
	 * @param string $html The HTML of the tag to parse.
	 */
	public function __construct( $html ) {
		if ( empty( $html ) || ! \is_string( $html ) ) {
			$this->debug_message( 'invalid HTML tag provided' );
			return;
		}
		$this->html = \trim( $html );
		if ( ! \str_starts_with( $this->html, '<' ) || ! \str_ends_with( $this->html, '>' ) ) {
			$this->debug_message( 'invalid HTML tag provided' );
			$this->html = '';
			return;
		}
	}

	/**
	 * Get the name of the tag being parsed.
	 *
	 * @return string The name of the tag being parsed.
	 */
	public function get_tag_name() {
		if ( empty( $this->name ) ) {
			\preg_match( '/^<(\w+)/', $this->html, $tag_name_matches );
			if ( ! empty( $tag_name_matches[1] ) ) {
				$this->name = $tag_name_matches[1];
			} else {
				$this->debug_message( 'no tag name found, cannot parse: ' . substr( $this->html, 0, 20 ) );
				$this->html = '';
			}
		}
		return $this->name;
	}

	/**
	 * Get attributes from an HTML element.
	 *
	 * Note that the values and such will be updated by successive operations, but the positions are not.
	 * Thus, position data should not be relied upon beyond the initial overlap check in $this->sort_attributes_by_position().
	 *
	 * @return array An associative array of attributes, their values, and positions.
	 */
	public function get_attributes() {
		// If we've already parsed (or attempted) to parse, then return the results.
		if ( is_array( $this->attributes ) ) {
			return $this->attributes;
		}
		// Set it to an array, so we don't try to parse again.
		$this->attributes = array();
		if ( \strlen( $this->html ) > 5000 ) {
			$this->debug_message( 'tag is too long to parse' );
			return $this->attributes;
		}
		$this->get_tag_name();
		if ( empty( $this->name ) ) {
			$this->debug_message( 'no tag name found, cannot parse' );
			return $this->attributes;
		}
		$leftovers = \preg_replace( '/^<(\w+)/', '', $this->html );
		$this->debug_message( "parsing {$this->name} tag for attributes" );
		if ( \preg_match_all( '#(?P<space>\s)(?P<full>(?P<name>[\w-]+)\s*=\s*("|\')(?P<value>(?!\4).+?)?\4)#s', $this->html, $attr_matches, PREG_OFFSET_CAPTURE ) ) {
			if ( ! empty( $attr_matches['full'] ) && ! empty( $attr_matches['name'] ) && isset( $attr_matches['value'] ) ) {
				foreach ( $attr_matches['full'] as $key => $full_match ) {
					if ( empty( $attr_matches['name'][ $key ][0] ) ) {
						$this->debug_message( 'no name match, strange?' );
						$this->attributes = array();
						return $this->attributes;
					}
					$name_match = strtolower( $attr_matches['name'][ $key ][0] );
					// Make sure we don't already have this attribute.
					if ( ! empty( $this->attributes[ $name_match ] ) ) {
						$this->debug_message( "duplicate attribute $name_match found, parse failure" );
						$this->attributes = array();
						return $this->attributes;
					}
					$this->attributes[ $name_match ] = array(
						'full'     => $full_match[0],
						'position' => $full_match[1],
						'length'   => \strlen( $full_match[0] ),
						'space'    => isset( $attr_matches['space'][ $key ][0] ) && strlen( $attr_matches['space'][ $key ][0] ) > 0 ? $attr_matches['space'][ $key ][0] : ' ',
						'value'    => ! empty( $attr_matches['value'][ $key ][0] ) ? trim( $attr_matches['value'][ $key ][0] ) : '',
					);
					// Strip this attribute from the leftovers, so we can find unquoted attributes later.
					$leftovers = \str_replace( $full_match[0], '', $leftovers );
				}
			}
		}
		$this->get_unquoted_attributes_from_string( $leftovers );
		if ( count( $this->attributes ) > 0 ) {
			// Sort the attributes by position, find the first one, and check for overlaps.
			$first_attr = $this->sort_attributes_by_position();
			// Finally, make sure the first attr we found is actually the first attribute of the element.
			if ( empty( $first_attr ) || ! \preg_match( '#<\w+\s+?' . $first_attr . '#', $this->html ) ) {
				if ( ! empty( $first_attr ) ) {
					$this->debug_message( "first attr ($first_attr) is not first in $this->html" );
				}
				$this->attributes = array();
			}
		}
		return $this->attributes;
	}

	/**
	 * Get unquoted attributes from a string.
	 *
	 * @param string $fragment This will generally be a fragment of an HTML element.
	 */
	public function get_unquoted_attributes_from_string( $fragment ) {
		if ( empty( trim( $fragment, " \n\r\t\v\0/>" ) ) ) {
			return;
		}
		$this->debug_message( "checking '$fragment' for unquoted attributes" );
		if ( \preg_match_all( '#(?P<space>\s)(?P<full>(?P<name>[\w-]+)(?:\s*=\s*(?P<value>[^"\'`=<>\s]+))?)#s', $fragment, $attr_matches, PREG_OFFSET_CAPTURE ) ) {
			if ( ! empty( $attr_matches['full'] ) && ! empty( $attr_matches['name'] ) ) {
				foreach ( $attr_matches['full'] as $key => $full_match ) {
					if ( empty( $attr_matches['name'][ $key ][0] ) ) {
						$this->debug_message( 'no name match, strange?' );
						$this->attributes = array();
						break;
					}
					$name_match = strtolower( $attr_matches['name'][ $key ] );
					// Make sure we don't already have this attribute.
					if ( ! empty( $this->attributes[ $name_match[0] ] ) ) {
						$this->debug_message( "duplicate attribute {$name_match[0]} found, parse failure" );
						$this->attributes = array();
						break;
					}
					$this->attributes[ $name_match[0] ] = array(
						'full'     => $attr_matches[0][ $key ][0],
						'length'   => \strlen( $full_match[0] ),
						'position' => \strpos( $this->html, $full_match[0] ),
						'space'    => isset( $attr_matches['space'][ $key ][0] ) && \strlen( $attr_matches['space'][ $key ][0] ) > 0 ? $attr_matches['space'][ $key ][0] : ' ',
						'value'    => ! empty( $attr_matches['value'][ $key ][0] ) ? trim( $attr_matches['value'][ $key ][0] ) : '',
					);
				}
			}
		}
	}

	/**
	 * Get an attribute from an HTML tag.
	 *
	 * @param string $name The name of the attribute to search for.
	 * @return string The value of the attribute, or an empty string if not found.
	 */
	public function get_attribute( $name ) {
		$this->get_attributes();
		if ( ! empty( $this->attributes[ $name ] ) && isset( $this->attributes[ $name ]['value'] ) ) {
			return $this->attributes[ $name ]['value'];
		}
		return '';
	}

	/**
	 * Sort the attributes by position and make sure there are no overlaps.
	 *
	 * @return string The name of the first attribute found, or an empty string if there was a position/parsing conflict.
	 */
	public function sort_attributes_by_position() {
		$first_attr = '';
		\uasort(
			$this->attributes,
			function ( $a, $b ) {
				return $a['position'] - $b['position'];
			}
		);
		foreach ( $this->attributes as $name => $attr ) {
			if ( empty( $first_attr ) ) {
				$first_attr = $name;
				$this->debug_message( "first attribute is $first_attr" );
			}
			if ( isset( $last_attr ) && isset( $last_attr_name ) ) {
				if ( $attr['position'] < $last_attr['position'] + $last_attr['length'] ) {
					$this->debug_message( "attribute $name overlaps with $last_attr_name, parse failure" );
					$this->attributes = array();
					$first_attr       = '';
					break;
				}
			}
			$last_attr      = $attr;
			$last_attr_name = $name;
		}
		return $first_attr;
	}

	/**
	 * Set an attribute on an HTML tag.
	 *
	 * @param string $name The name of the attribute to set.
	 * @param string $value The value of the attribute to set.
	 * @param bool   $append Default false. True to append, false to replace.
	 */
	public function set_attribute( $name, $value, $append = false ) {
		$this->get_attributes();
		if ( empty( $name ) || empty( $value ) ) {
			return;
		}
		$name = \strtolower( $name );
		// Remove/escape double-quotes with the encoded version, so that we can safely enclose the value in double-quotes.
		$value = \str_replace( '"', '&#34;', $value );
		$value = \trim( $value );
		if ( isset( $this->attributes[ $name ] ) ) {
			if ( $append && ! empty( $this->attributes[ $name ]['value'] ) ) {
				$value = $this->attributes[ $name ]['value'] . ' ' . $value;
			}
			$spacer = ' ';
			if ( isset( $this->attributes[ $name ]['space'] ) && \is_string( $this->attributes[ $name ]['space'] ) && \strlen( $this->attributes[ $name ]['space'] ) > 0 ) {
				$spacer = $this->attributes[ $name ]['space'];
			}
			// Update the source with the new attribute & value.
			$this->html = \str_replace( $spacer . $this->attributes[ $name ]['full'], " $name=\"$value\"", $this->html );
		} else {
			$closing = ' />';
			if ( ! \str_ends_with( $this->html, '/>' ) ) {
				$closing = '>';
			}
			$this->html = \rtrim( $this->html, $closing ) . " $name=\"$value\"$closing";
		}
		$this->attributes[ $name ]['value']  = $value;
		$this->attributes[ $name ]['full']   = $name . '="' . $value . '"';
		$this->attributes[ $name ]['length'] = \strlen( $this->attributes[ $name ]['full'] );
		$this->attributes[ $name ]['space']  = ' ';
	}

	/**
	 * Remove an attribute from an HTML element.
	 *
	 * @param string $name The name of the attribute to remove.
	 */
	public function remove_attribute( $name ) {
		$this->get_attributes();
		if ( empty( $name ) || ! \is_string( $name ) || ! isset( $this->attributes[ $name ]['full'] ) ) {
			return;
		}
		$spacer = ' ';
		if ( isset( $this->attributes[ $name ]['space'] ) && \is_string( $this->attributes[ $name ]['space'] ) && \strlen( $this->attributes[ $name ]['space'] ) > 0 ) {
			$spacer = $this->attributes[ $name ]['space'];
		}
		// Remove the attribute from the tag HTML.
		$this->html = \str_replace( $spacer . $this->attributes[ $name ]['full'], '', $this->html );
		// Lastly, forget this attr ever existed.
		unset( $this->attributes[ $name ] );
	}

	/**
	 * Get the width from an image tag.
	 *
	 * @return string The width found or an empty string.
	 */
	public function get_img_style_width() {
		// Check for an inline max-width directive.
		$style = $this->get_attribute( 'style' );
		if ( $style && \preg_match( '#max-width:\s?(\d+)px#', $style, $max_width_string ) ) {
			if ( ! empty( $max_width_string[1] ) && \is_numeric( $max_width_string[1] ) ) {
				return (int) $max_width_string[1];
			}
		} elseif ( $style && \preg_match( '#width:\s?(\d+)px#', $style, $width_string ) ) {
			if ( ! empty( $width_string[1] ) && \is_numeric( $width_string[1] ) ) {
				return (int) $width_string[1];
			}
		}
		return false;
	}

	/**
	 * Get the height from an image element.
	 *
	 * @return string The height found or an empty string.
	 */
	public function get_img_style_height() {
		// Then check for an inline max-height directive.
		$style = $this->get_attribute( 'style' );
		if ( $style && \preg_match( '#max-height:\s?(\d+)px#', $style, $max_height_string ) ) {
			if ( ! empty( $max_height_string[1] ) && \is_numeric( $max_height_string[1] ) ) {
				return (int) $max_height_string[1];
			}
		} elseif ( $style && \preg_match( '#height:\s?(\d+)px#', $style, $height_string ) ) {
			if ( ! empty( $height_string[1] ) && \is_numeric( $height_string[1] ) ) {
				return (int) $height_string[1];
			}
		}
		return false;
	}

	/**
	 * Get CSS background-image URLs.
	 *
	 * @param string $attribute An element's style attribute. Do not pass a full HTML element.
	 * @return array An array containing URL(s) from the background/background-image property.
	 */
	public function get_background_image_urls( $attribute ) {
		$urls = array();
		if ( ( false !== \strpos( $attribute, 'background:' ) || false !== \strpos( $attribute, 'background-image:' ) ) && false !== \strpos( $attribute, 'url(' ) ) {
			if ( \preg_match_all( '#url\((?P<bg_url>[^)]+)\)#', $attribute, $prop_matches ) ) {
				if ( $this->is_iterable( $prop_matches['bg_url'] ) ) {
					foreach ( $prop_matches['bg_url'] as $url ) {
						$urls[] = \trim( \html_entity_decode( $url, ENT_QUOTES | ENT_HTML5 ), "'\"\t\n\r " );
					}
				}
			}
		}
		return $urls;
	}

	/**
	 * Get a single CSS background-image URL. For backwords compat.
	 *
	 * @param string $attribute An element's style attribute. Do not pass a full HTML element.
	 * @return array An array containing URL(s) from the background/background-image property.
	 */
	public function get_background_image_url( $attribute ) {
		if ( ( false !== \strpos( $attribute, 'background:' ) || false !== \strpos( $attribute, 'background-image:' ) ) && false !== \strpos( $attribute, 'url(' ) ) {
			$background_urls = $this->get_background_image_urls( $attribute );
			if ( ! empty( $background_urls[0] ) ) {
				return $background_urls[0];
			}
		}
		return '';
	}

	/**
	 * Get CSS background-image rules from HTML.
	 *
	 * @param string $html The code containing potential background images.
	 * @return array The URLs with background/background-image properties.
	 */
	public function get_background_images( $html ) {
		if ( ( false !== \strpos( $html, 'background:' ) || false !== \strpos( $html, 'background-image:' ) ) && false !== \strpos( $html, 'url(' ) ) {
			if ( \preg_match_all( '#background(-image)?:\s*?[^;}]*?url\([^)]+\)#', $html, $matches ) ) {
				return $matches[0];
			}
		}
		return array();
	}

	/**
	 * Remove the background image URL from a style attribute.
	 *
	 * @param string $attribute The element's style attribute to modify.
	 * @return string The style attribute with any image url removed.
	 */
	public function remove_background_image( $attribute ) {
		if ( false !== \strpos( $attribute, 'background:' ) && false !== \strpos( $attribute, 'url(' ) ) {
			$new_attribute = \preg_replace( '#\s?url\([^)]+\)#', '', $attribute );
			if ( $new_attribute !== $attribute ) {
				return $new_attribute;
			}
		}
		if ( false !== \strpos( $attribute, 'background-image:' ) && false !== \strpos( $attribute, 'url(' ) ) {
			$new_attribute = \preg_replace( '#background-image:\s*(,?\s*url\([^)]+\))+;?\s*#', '', $attribute );
			if ( $new_attribute !== $attribute ) {
				$new_attribute = \preg_replace( '#background-image:\s*;?\s*$#', '', $new_attribute );
				return $new_attribute;
			}
		}
		if ( false !== \strpos( $attribute, 'background-image:' ) && false !== \strpos( $attribute, 'url(' ) ) {
			$new_attribute = \preg_replace( '#,?\s*url\([^)]+\)#', '', $attribute );
			if ( $new_attribute !== $attribute ) {
				$new_attribute = \preg_replace( '#background-image:\s*;?\s*$#', '', $new_attribute );
				return $new_attribute;
			}
		}
		return $attribute;
	}
}
