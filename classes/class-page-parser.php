<?php
/**
 * Implements basic page parsing functions.
 *
 * @link https://ewww.io
 * @package EIO
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTML element and attribute parsing, replacing, etc.
 */
class Page_Parser extends Base {

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
		'svg',
		'webp',
	);

	/**
	 * Indicates if we are filtering ExactDN urls.
	 *
	 * @access protected
	 * @var bool $parsing_exactdn
	 */
	protected $parsing_exactdn = false;

	/**
	 * List of images that will be preloaded.
	 *
	 * @var array $preload_images
	 */
	public $preload_images = array();

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
	public function get_images_from_html( $content, $hyperlinks = true, $src_required = true ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$images          = array();
		$unquoted_images = array();

		if ( empty( $content ) ) {
			return $images;
		}
		$unquoted_pattern = '';
		$search_pattern   = '#(?P<img_tag><img\s[^\\\\>]*?>)#is';
		if ( $hyperlinks ) {
			$this->debug_message( 'using div/figure+hyperlink(a) patterns with src required' );
			$search_pattern   = '#(?:<div[^>]*?\s+?class\s*=\s*["\'](?P<div_class>[\w\s-]+?)["\'][^>]*?>\s*(?:<span[^>]*?></span>)?)?(?:<figure[^>]*?\s+?class\s*=\s*["\'](?P<figure_class>[\w\s-]+?)["\'][^>]*?>\s*)?(?:<a[^>]*?\s+?href\s*=\s*["\'](?P<link_url>[^\s]+?)["\'][^>]*?>\s*)?(?P<img_tag><img[^>]*?\s+?src\s*=\s*("|\')(?P<img_url>(?!\5)[^\\\\]+?)\5[^>]*?>){1}(?:\s*</a>)?#is';
			$unquoted_pattern = '#(?:<div[^>]*?\s+?class\s*=\s*(?P<div_class>[\w-]+?)[^>]*?>\s*(?:<span[^>]*?></span>)?)?(?:<figure[^>]*?\s+?class\s*=\s*(?P<figure_class>[\w-]+?)[^>]*?>\s*)?(?:<a[^>]*?\s+?href\s*=\s*(?P<link_url>[^"\'\\\\<>][^\s<>]+)[^>]*?>\s*)?(?P<img_tag><img[^>]*?\s+?src\s*=\s*(?P<img_url>[^"\'\\\\<>][^\s\\\\<>]+)(?:\s[^>]*?)?>){1}(?:\s*</a>)?#is';
		} elseif ( $src_required ) {
			$this->debug_message( 'using plain img pattern, src still required' );
			$search_pattern   = '#(?P<img_tag><img[^>]*?\s+?src\s*=\s*("|\')(?P<img_url>(?!\2)[^\\\\]+?)\2[^>]*?>)#is';
			$unquoted_pattern = '#(?P<img_tag><img[^>]*?\s+?src\s*=\s*(?P<img_url>[^"\'\\\\<>][^\s\\\\<>]+)(?:\s[^>]*?)?>)#is';
		}
		if ( \preg_match_all( $search_pattern, $content, $images ) ) {
			$this->debug_message( 'found ' . \count( $images[0] ) . ' image elements with quoted pattern' );
			foreach ( $images as $key => $unused ) {
				// Simplify the output as much as possible.
				if ( \is_numeric( $key ) && $key > 0 ) {
					unset( $images[ $key ] );
				}
			}
			/* $this->debug_message( print_r( $images, true ) ); */
		}
		$images = \array_filter( $images );
		if ( $unquoted_pattern && \preg_match_all( $unquoted_pattern, $content, $unquoted_images ) ) {
			$this->debug_message( 'found ' . \count( $unquoted_images[0] ) . ' image elements with unquoted pattern' );
			foreach ( $unquoted_images as $key => $unused ) {
				// Simplify the output as much as possible.
				if ( \is_numeric( $key ) && $key > 0 ) {
					unset( $unquoted_images[ $key ] );
				}
			}
			/* $this->debug_message( print_r( $unquoted_images, true ) ); */
		}
		$unquoted_images = \array_filter( $unquoted_images );
		if ( ! empty( $images ) && ! empty( $unquoted_images ) ) {
			$this->debug_message( 'both patterns found results, merging' );
			/* $this->debug_message( print_r( $images, true ) ); */
			$images = \array_merge_recursive( $images, $unquoted_images );
			/* $this->debug_message( print_r( $images, true ) ); */
			if ( ! empty( $images[0] ) && ! empty( $images[1] ) ) {
				$images[0] = \array_merge( $images[0], $images[1] );
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
	public function get_noscript_images_from_html( $content ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$images = array();

		if ( ! empty( $content ) && \preg_match_all( '#(?P<noscript_tag><noscript[^>]*?>\s*)(?P<img_tag><img[^>]*?\s+?src\s*=\s*["\'](?P<img_url>[^\s]+?)["\'][^>]*?>){1}(?:\s*</noscript>)?#is', $content, $images ) ) {
			foreach ( $images as $key => $unused ) {
				// Simplify the output as much as possible, mostly for confirming test results.
				if ( \is_numeric( $key ) && $key > 0 ) {
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
	public function get_picture_tags_from_html( $content ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$pictures = array();
		if ( ! empty( $content ) && \preg_match_all( '#(?:<picture[^>]*?>\s*)(?:<source[^>]*?>)+(?:.*?</picture>)?#is', $content, $pictures ) ) {
			return $pictures[0];
		}
		return array();
	}

	/**
	 * Match all <style> tags in a block of HTML.
	 *
	 * @param string $content Some HTML.
	 * @return array An array of $styles matches, containing full elements with ending tags.
	 */
	public function get_style_tags_from_html( $content ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$styles = array();
		if ( ! empty( $content ) && \preg_match_all( '#<style[^>]*?>.*?</style>#is', $content, $styles ) ) {
			return $styles[0];
		}
		return array();
	}

	/**
	 * Get a list of images that are going to be preloaded.
	 *
	 * @param string $content Some HTML.
	 */
	public function get_preload_images( $content ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( count( $this->preload_images ) ) {
			$this->debug_message( 'already got some' );
			return;
		}
		$links = $this->get_elements_from_html( $content, 'link' );
		foreach ( $links as $link ) {
			if ( 'preload' === $this->get_attribute( $link, 'rel' ) && 'image' === $this->get_attribute( $link, 'as' ) ) {
				$url = $this->get_attribute( $link, 'href' );
				if ( $url ) {
					$this->debug_message( "found preload for $url" );
					$path   = $this->parse_url( $url, PHP_URL_PATH );
					$srcset = $this->get_attribute( $link, 'imagesrcset' );
					if ( $path ) {
						$this->debug_message( "parsed it down to $path" );
						$this->preload_images[] = array(
							'tag'    => $link,
							'url'    => $url,
							'path'   => $path,
							'srcset' => $srcset,
							'found'  => false,
						);
					}
				}
			}
		}
	}

	/**
	 * Match all elements by tag name in a block of HTML. Does not retrieve contents or closing tags.
	 *
	 * @param string $content Some HTML.
	 * @param string $tag_name The name of the elements to retrieve.
	 * @return array An array of $elements.
	 */
	public function get_elements_from_html( $content, $tag_name ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! \ctype_alpha( str_replace( '-', '', $tag_name ) ) ) {
			return array();
		}
		if ( ! empty( $content ) && \preg_match_all( '#<' . $tag_name . '\s[^\\\\>]+?>#is', $content, $elements ) ) {
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
	public function get_dimensions_from_filename( $src, $use_params = false ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$width_height_string = array();
		$this->debug_message( "looking for dimensions in $src" );
		$width_param  = false;
		$height_param = false;
		if ( $use_params && \strpos( $src, '?' ) ) {
			$url_params = \urldecode( $this->parse_url( $src, PHP_URL_QUERY ) );
			if ( $url_params && false !== \strpos( $url_params, 'resize=' ) ) {
				\preg_match( '/resize=(\d+),(\d+)/', $url_params, $resize_matches );
				if ( \is_array( $resize_matches ) && ! empty( $resize_matches[1] ) && ! empty( $resize_matches[2] ) ) {
					$width_param  = (int) $resize_matches[1];
					$height_param = (int) $resize_matches[2];
				}
			} elseif ( false !== \strpos( $url_params, 'fit=' ) ) {
				\preg_match( '/fit=(\d+),(\d+)/', $url_params, $fit_matches );
				if ( \is_array( $fit_matches ) && ! empty( $fit_matches[1] ) && ! empty( $fit_matches[2] ) ) {
					$width_param  = (int) $fit_matches[1];
					$height_param = (int) $fit_matches[2];
				}
			}
		}
		if ( \preg_match( '#-(\d+)x(\d+)(@2x)?\.(?:' . \implode( '|', $this->extensions ) . '){1}(?:\?.+)?$#i', $src, $width_height_string ) ) {
			$width  = (int) $width_height_string[1];
			$height = (int) $width_height_string[2];

			if ( \strpos( $src, '@2x' ) ) {
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
	 * Get dimensions of a file from the URL.
	 *
	 * @param string $url The URL of the image.
	 * @return array The width and height, in pixels.
	 */
	public function get_image_dimensions_by_url( $url ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->debug_message( "getting dimensions for $url" );

		list( $width, $height ) = $this->get_dimensions_from_filename( $url, ! empty( $this->parsing_exactdn ) );
		if ( empty( $width ) || empty( $height ) ) {
			// Couldn't get it from the URL directly, see if we can get the actual filename.
			$file = false;
			if ( $this->allowed_urls && $this->allowed_domains ) {
				$file = $this->cdn_to_local( $url );
			}
			if ( ! $file ) {
				$file = $this->url_to_path_exists( $url );
			}
			if ( $file && $this->is_file( $file ) ) {
				list( $width, $height ) = \wp_getimagesize( $file );
			}
		}
		$width  = $width && \is_numeric( $width ) ? (int) $width : false;
		$height = $height && \is_numeric( $height ) ? (int) $height : false;

		return array( $width, $height );
	}

	/**
	 * Get the width from an image element.
	 *
	 * @param string $img The full image element.
	 * @return string The width found or an empty string.
	 */
	public function get_img_style_width( $img ) {
		// Check for an inline max-width directive.
		$style = $this->get_attribute( $img, 'style' );
		if ( $style && \preg_match( '#max-width:\s?(\d+)px#', $style, $max_width_string ) ) {
			if ( ! empty( $max_width_string[1] ) && \is_numeric( $max_width_string ) ) {
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
	 * @param string $img The full image element.
	 * @return string The height found or an empty string.
	 */
	public function get_img_style_height( $img ) {
		// Then check for an inline max-height directive.
		$style = $this->get_attribute( $img, 'style' );
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
	 * Get an attribute from an HTML element.
	 *
	 * @param string $element The HTML element to parse.
	 * @param string $name The name of the attribute to search for.
	 * @return string The value of the attribute, or an empty string if not found.
	 */
	public function get_attribute( $element, $name ) {
		// Don't forget, back references cannot be used in character classes.
		if ( \preg_match( '#\s' . $name . '\s*=\s*("|\')((?!\1).+?)\1#is', $element, $attr_matches ) ) {
			if ( ! empty( $attr_matches[2] ) ) {
				return $attr_matches[2];
			}
		}
		// If there were not any matches with quotes, look for unquoted attributes, no spaces or quotes allowed.
		if ( \preg_match( '#\s' . $name . '\s*=\s*([^"\'][^\s>]+)#is', $element, $attr_matches ) ) {
			if ( ! empty( $attr_matches[1] ) ) {
				return $attr_matches[1];
			}
		}
		return '';
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
						$urls[] = \trim( \html_entity_decode( $url, ENT_QUOTES | ENT_HTML401 ), "'\"\t\n\r " );
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
	 * Set an attribute on an HTML element.
	 *
	 * @param string $element The HTML element to modify. Passed by reference.
	 * @param string $name The name of the attribute to set.
	 * @param string $value The value of the attribute to set.
	 * @param bool   $replace Default false. True to replace, false to append.
	 */
	public function set_attribute( &$element, $name, $value, $replace = false ) {
		if ( 'class' === $name ) {
			$element = \preg_replace( "#\s$name\s+([^=])#", ' $1', $element );
		}
		// Remove empty attributes first.
		$element = \preg_replace( "#\s$name=\"\"#", ' ', $element );
		// Remove/escape double-quotes with the encoded version, so that we can safely enclose the value in double-quotes.
		$value = \str_replace( '"', '&#34;', $value );
		$value = \trim( $value );
		if ( $replace ) {
			// Don't forget, back references cannot be used in character classes.
			$new_element = \preg_replace( '#\s' . $name . '\s*=\s*("|\')(?!\1).*?\1#is', ' ' . $name . '="' . $value . '"', $element );
			if ( \strpos( $new_element, "$name=" ) && $new_element !== $element ) {
				$element = $new_element;
				return;
			}
			// Purge un-quoted attribute patterns, so the new value can be inserted further down.
			$new_element = \preg_replace( '#\s' . $name . '\s*=\s*[^"\'][^\s>]+#is', ' ', $element );
			// But if we couldn't purge the attribute, then bail out.
			if ( \preg_match( '#\s' . $name . '\s*=\s*#', $new_element ) && $new_element === $element ) {
				$this->debug_message( "$name replacement failed, still exists in $element" );
				return;
			}
			$element = $new_element;
		}
		$closing = ' />';
		if ( false === \strpos( $element, '/>' ) ) {
			$closing = '>';
		}
		if ( false === \strpos( $value, '"' ) ) { // This should always be true, since we escape double-quotes above.
			$element = \rtrim( $element, $closing ) . " $name=\"$value\"$closing";
			return;
		}
		// If we get here, something is kind of weird, since double-quotes were supposed to be escaped.
		$element = \rtrim( $element, $closing ) . " $name='$value'$closing";
	}

	/**
	 * Remove an attribute from an HTML element.
	 *
	 * @param string $element The HTML element to modify. Passed by reference.
	 * @param string $name The name of the attribute to remove.
	 */
	public function remove_attribute( &$element, $name ) {
		// Don't forget, back references cannot be used in character classes.
		$element = \preg_replace( '#\s' . $name . '\s*=\s*("|\')(?!\1).+?\1#is', ' ', $element );
		$element = \preg_replace( '#\s' . $name . '\s*=\s*[^"\'][^\s>]+#is', ' ', $element );
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
