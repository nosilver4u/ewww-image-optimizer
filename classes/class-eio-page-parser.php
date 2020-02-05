<?php
/**
 * Implements basic page parsing functions.
 *
 * @link https://ewww.io
 * @package EIO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'EIO_Page_Parser' ) ) {
	/**
	 * HTML element and attribute parsing, replacing, etc.
	 */
	class EIO_Page_Parser extends EIO_Base {

		/**
		 * Allowed image extensions.
		 *
		 * @access protected
		 * @var array $extensions
		 */
		protected $extensions = array(
			'gif',
			'jpg',
			'jpeg',
			'jpe',
			'png',
		);

		/**
		 * Match all images and any relevant <a> tags in a block of HTML.
		 *
		 * The hyperlinks param implies that the src attribute is required, but not the other way around.
		 *
		 * @param string $content Some HTML.
		 * @param bool   $hyperlinks Default true. Should we include encasing hyperlinks in our search.
		 * @param bool   $src_required Default true. Should we look only for images with src attributes.
		 * @return array An array of $images matches, where $images[0] is
		 *         an array of full matches, and the link_url, img_tag,
		 *         and img_url keys are arrays of those matches.
		 */
		function get_images_from_html( $content, $hyperlinks = true, $src_required = true ) {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			$images          = array();
			$unquoted_images = array();

			$unquoted_pattern = '';
			$search_pattern   = '#(?P<img_tag><img\s[^>]*?>)#is';
			if ( $hyperlinks ) {
				$this->debug_message( 'using figure+hyperlink(a) patterns with src required' );
				$search_pattern   = '#(?:<figure[^>]*?\s+?class\s*=\s*["\'](?P<figure_class>[\w\s-]+?)["\'][^>]*?>\s*)?(?:<a[^>]*?\s+?href\s*=\s*["\'](?P<link_url>[^\s]+?)["\'][^>]*?>\s*)?(?P<img_tag><img[^>]*?\s+?src\s*=\s*("|\')(?P<img_url>(?!\4).+?)\4[^>]*?>){1}(?:\s*</a>)?#is';
				$unquoted_pattern = '#(?:<figure[^>]*?\s+?class\s*=\s*(?P<figure_class>[\w-]+)[^>]*?>\s*)?(?:<a[^>]*?\s+?href\s*=\s*(?P<link_url>[^"\'][^\s>]+)[^>]*?>\s*)?(?P<img_tag><img[^>]*?\s+?src\s*=\s*(?P<img_url>[^"\'][^\s>]+)[^>]*?>){1}(?:\s*</a>)?#is';
			} elseif ( $src_required ) {
				$this->debug_message( 'using plain img pattern, src still required' );
				$search_pattern   = '#(?P<img_tag><img[^>]*?\s+?src\s*=\s*("|\')(?P<img_url>(?!\2).+?)\2[^>]*?>)#is';
				$unquoted_pattern = '#(?P<img_tag><img[^>]*?\s+?src\s*=\s*(?P<img_url>[^"\'][^\s>]+)[^>]*?>)#is';
			}
			if ( preg_match_all( $search_pattern, $content, $images ) ) {
				$this->debug_message( 'found ' . count( $images[0] ) . ' image elements with quoted pattern' );
				foreach ( $images as $key => $unused ) {
					// Simplify the output as much as possible.
					if ( is_numeric( $key ) && $key > 0 ) {
						unset( $images[ $key ] );
					}
				}
				/* $this->debug_message( print_r( $images, true ) ); */
			}
			$images = array_filter( $images );
			if ( $unquoted_pattern && preg_match_all( $unquoted_pattern, $content, $unquoted_images ) ) {
				$this->debug_message( 'found ' . count( $unquoted_images[0] ) . ' image elements with unquoted pattern' );
				foreach ( $unquoted_images as $key => $unused ) {
					// Simplify the output as much as possible.
					if ( is_numeric( $key ) && $key > 0 ) {
						unset( $unquoted_images[ $key ] );
					}
				}
				/* $this->debug_message( print_r( $unquoted_images, true ) ); */
			}
			$unquoted_images = array_filter( $unquoted_images );
			if ( ! empty( $images ) && ! empty( $unquoted_images ) ) {
				$this->debug_message( 'both patterns found results, merging' );
				/* $this->debug_message( print_r( $images, true ) ); */
				$images = array_merge_recursive( $images, $unquoted_images );
				/* $this->debug_message( print_r( $images, true ) ); */
				if ( ! empty( $images[0] ) && ! empty( $images[1] ) ) {
					$images[0] = array_merge( $images[0], $images[1] );
					unset( $images[1] );
				}
			} elseif ( empty( $images ) && ! empty( $unquoted_images ) ) {
				$this->debug_message( 'unquoted results only, subbing in' );
				$images = $unquoted_images;
			}
			/* $this->debug_message( print_r( $images, true ) ); */
			return $images;
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
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			$images = array();

			if ( preg_match_all( '#(?P<noscript_tag><noscript[^>]*?>\s*)(?P<img_tag><img[^>]*?\s+?src\s*=\s*["\'](?P<img_url>[^\s]+?)["\'][^>]*?>){1}(?:\s*</noscript>)?#is', $content, $images ) ) {
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
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
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
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			if ( ! ctype_alpha( $tag_name ) ) {
				return array();
			}
			if ( preg_match_all( '#<' . $tag_name . '\s[^>]+?>#is', $content, $elements ) ) {
				return $elements[0];
			}
			return array();
		}

		/**
		 * Try to determine height and width from strings WP appends to resized image filenames.
		 *
		 * @param string $src The image URL.
		 * @param bool   $use_params Check ExactDN image parameters for additional size information. Default to false.
		 * @return array An array consisting of width and height.
		 */
		function get_dimensions_from_filename( $src, $use_params = false ) {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			$width_height_string = array();
			$this->debug_message( "looking for dimensions in $src" );
			$width_param  = false;
			$height_param = false;
			if ( $use_params && strpos( $src, '?' ) ) {
				$url_params = urldecode( $this->parse_url( $src, PHP_URL_QUERY ) );
				if ( $url_params && false !== strpos( $url_params, 'resize=' ) ) {
					preg_match( '/resize=(\d+),(\d+)/', $url_params, $resize_matches );
					if ( is_array( $resize_matches ) && ! empty( $resize_matches[1] ) && ! empty( $resize_matches[2] ) ) {
						$width_param  = (int) $resize_matches[1];
						$height_param = (int) $resize_matches[2];
					}
				} elseif ( false !== strpos( $url_params, 'fit=' ) ) {
					preg_match( '/fit=(\d+),(\d+)/', $url_params, $fit_matches );
					if ( is_array( $fit_matches ) && ! empty( $fit_matches[1] ) && ! empty( $fit_matches[2] ) ) {
						$width_param  = (int) $fit_matches[1];
						$height_param = (int) $fit_matches[2];
					}
				}
			}
			if ( preg_match( '#-(\d+)x(\d+)(@2x)?\.(?:' . implode( '|', $this->extensions ) . '){1}(?:\?.+)?$#i', $src, $width_height_string ) ) {
				$width  = (int) $width_height_string[1];
				$height = (int) $width_height_string[2];

				if ( strpos( $src, '@2x' ) ) {
					$width  = 2 * $width;
					$height = 2 * $height;
				}
				if ( $width && $height ) {
					if ( $width_param && $width_param < $width ) {
						$width = $width_param;
					}
					if ( $height_param && $height_param < $height ) {
						$height = $height_param;
					}
					$this->debug_message( "found w$width h$height" );
					return array( $width, $height );
				}
			}
			return array( $width_param, $height_param ); // These may be false, unless URL parameters were found.
		}

		/**
		 * Get the width from an image element.
		 *
		 * @param string $img The full image element.
		 * @return string The width found or an empty string.
		 */
		public function get_img_width( $img ) {
			$width = $this->get_attribute( $img, 'width' );
			// Then check for an inline max-width directive.
			$style = $this->get_attribute( $img, 'style' );
			if ( $style && preg_match( '#max-width:\s?(\d+)px#', $style, $max_width_string ) ) {
				if ( $max_width_string[1] && ( ! $width || $max_width_string[1] < $width ) ) {
					$width = $max_width_string[1];
				}
			} elseif ( $style && preg_match( '#width:\s?(\d+)px#', $style, $width_string ) ) {
				if ( $width_string[1] && ( ! $width || $width_string[1] < $width ) ) {
					$width = $width_string[1];
				}
			}
			return $width;
		}

		/**
		 * Get the height from an image element.
		 *
		 * @param string $img The full image element.
		 * @return string The height found or an empty string.
		 */
		public function get_img_height( $img ) {
			$height = $this->get_attribute( $img, 'height' );
			// Then check for an inline max-height directive.
			$style = $this->get_attribute( $img, 'style' );
			if ( $style && preg_match( '#max-height:\s?(\d+)px#', $style, $max_height_string ) ) {
				if ( $max_height_string[1] && ( ! $height || $max_height_string[1] < $height ) ) {
					$height = $max_height_string[1];
				}
			} elseif ( $style && preg_match( '#height:\s?(\d+)px#', $style, $height_string ) ) {
				if ( $height_string[1] && ( ! $height || $height_string[1] < $height ) ) {
					$height = $height_string[1];
				}
			}
			return $height;
		}

		/**
		 * Get an attribute from an HTML element.
		 *
		 * @param string $element The HTML element to parse.
		 * @param string $name The name of the attribute to search for.
		 * @return string The value of the attribute, or an empty string if not found.
		 */
		function get_attribute( $element, $name ) {
			// Don't forget, back references cannot be used in character classes.
			if ( preg_match( '#\s' . $name . '\s*=\s*("|\')((?!\1).+?)\1#is', $element, $attr_matches ) ) {
				if ( ! empty( $attr_matches[2] ) ) {
					return $attr_matches[2];
				}
			}
			// If there were not any matches with quotes, look for unquoted attributes, no spaces or quotes allowed.
			if ( preg_match( '#\s' . $name . '\s*=\s*([^"\'][^\s>]+)#is', $element, $attr_matches ) ) {
				if ( ! empty( $attr_matches[1] ) ) {
					return $attr_matches[1];
				}
			}
			return '';
		}

		/**
		 * Get a CSS background-image URL.
		 *
		 * @param string $attribute An element's style attribute. Do not pass a full HTML element.
		 * @return string The URL from the background/background-image property.
		 */
		function get_background_image_url( $attribute ) {
			if ( ( false !== strpos( $attribute, 'background:' ) || false !== strpos( $attribute, 'background-image:' ) ) && false !== strpos( $attribute, 'url(' ) ) {
				if ( preg_match( '#url\(([^)]+)\)#', $attribute, $prop_match ) ) {
					return trim( html_entity_decode( $prop_match[1], ENT_QUOTES | ENT_HTML401 ), "'\"\t\n\r " );
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
			if ( 'class' === $name ) {
				$element = preg_replace( "#\s$name\s+[^=]#", ' ', $element );
			}
			$element = preg_replace( "#\s$name=\"\"#", ' ', $element );
			$value   = trim( $value );
			if ( $replace ) {
				// Don't forget, back references cannot be used in character classes.
				$new_element = preg_replace( '#\s' . $name . '\s*=\s*("|\')(?!\1).*?\1#is', " $name=$1$value$1", $element );
				if ( strpos( $new_element, "$name=" ) ) {
					$element = $new_element;
					return;
				}
				$element = preg_replace( '#\s' . $name . '\s*=\s*[^"\'][^\s>]+#is', ' ', $element );
			}
			$closing = ' />';
			if ( false === strpos( $element, '/>' ) ) {
				$closing = '>';
			}
			if ( false === strpos( $value, '"' ) ) {
				$element = rtrim( $element, $closing ) . " $name=\"$value\"$closing";
				return;
			}
			$element = rtrim( $element, $closing ) . " $name='$value'$closing";
		}

		/**
		 * Remove an attribute from an HTML element.
		 *
		 * @param string $element The HTML element to modify. Passed by reference.
		 * @param string $name The name of the attribute to remove.
		 */
		function remove_attribute( &$element, $name ) {
			// Don't forget, back references cannot be used in character classes.
			$element = preg_replace( '#\s' . $name . '\s*=\s*("|\')(?!\1).+?\1#is', ' ', $element );
			$element = preg_replace( '#\s' . $name . '\s*=\s*[^"\'][^\s>]+#is', ' ', $element );
		}

		/**
		 * Remove the background image URL from a style attribute.
		 *
		 * @param string $attribute The element's style attribute to modify.
		 * @return string The style attribute with any image url removed.
		 */
		function remove_background_image( $attribute ) {
			if ( false !== strpos( $attribute, 'background:' ) && false !== strpos( $attribute, 'url(' ) ) {
				$attribute = preg_replace( '#\s?url\([^)]+\)#', '', $attribute );
			}
			if ( false !== strpos( $attribute, 'background-image:' ) && false !== strpos( $attribute, 'url(' ) ) {
				$attribute = preg_replace( '#background-image:\s*url\([^)]+\);?#', '', $attribute );
			}
			return $attribute;
		}
	}
}
