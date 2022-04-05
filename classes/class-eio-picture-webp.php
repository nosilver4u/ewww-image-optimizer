<?php
/**
 * Implements WebP rewriting using page parsing and <picture> tags.
 *
 * @link https://ewww.io
 * @package EIO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enables EWWW IO to filter the page content and replace img elements with WebP <picture> markup.
 */
class EIO_Picture_Webp extends EIO_Page_Parser {

	/**
	 * A list of user-defined exclusions, populated by validate_user_exclusions().
	 *
	 * @access protected
	 * @var array $user_exclusions
	 */
	protected $user_exclusions = array();

	/**
	 * A list of user-defined (element-type) exclusions, populated by validate_user_exclusions().
	 *
	 * @access protected
	 * @var array $user_exclusions
	 */
	protected $user_element_exclusions = array();

	/**
	 * Request URI.
	 *
	 * @var string $request_uri
	 */
	public $request_uri = '';

	/**
	 * Register (once) actions and filters for Picture WebP.
	 */
	function __construct() {
		global $eio_picture_webp;
		if ( is_object( $eio_picture_webp ) ) {
			return 'you are doing it wrong';
		}
		if ( ewww_image_optimizer_ce_webp_enabled() ) {
			return false;
		}
		parent::__construct();
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );

		$this->request_uri = add_query_arg( null, null );
		if ( false === strpos( $this->request_uri, 'page=ewww-image-optimizer-options' ) ) {
			$this->debug_message( "request uri is {$this->request_uri}" );
		} else {
			$this->debug_message( 'request uri is EWWW IO settings' );
		}

		add_filter( 'eio_do_picture_webp', array( $this, 'should_process_page' ), 10, 2 );

		/**
		 * Allow pre-empting <picture> WebP by page.
		 *
		 * @param bool Whether to parse the page for images to rewrite for WebP, default true.
		 * @param string The URI/path of the page.
		 */
		if ( ! apply_filters( 'eio_do_picture_webp', true, $this->request_uri ) ) {
			return;
		}

		// Make sure gallery block images crop properly.
		add_action( 'wp_head', array( $this, 'gallery_block_css' ) );
		// Hook onto the output buffer function.
		if ( function_exists( 'swis' ) && swis()->settings->get_option( 'lazy_load' ) ) {
			add_filter( 'swis_filter_page_output', array( $this, 'filter_page_output' ) );
		} else {
			add_filter( 'ewww_image_optimizer_filter_page_output', array( $this, 'filter_page_output' ), 10 );
		}
		// Filter for FacetWP JSON responses.
		add_filter( 'facetwp_render_output', array( $this, 'filter_facetwp_json_output' ) );

		$allowed_urls = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' );
		if ( $this->is_iterable( $allowed_urls ) ) {
			$this->allowed_urls = array_merge( $this->allowed_urls, $allowed_urls );
		}

		$this->get_allowed_domains();

		$this->allowed_urls    = apply_filters( 'webp_allowed_urls', $this->allowed_urls );
		$this->allowed_domains = apply_filters( 'webp_allowed_domains', $this->allowed_domains );
		$this->debug_message( 'checking any images matching these URLs/patterns for webp: ' . implode( ',', $this->allowed_urls ) );
		$this->debug_message( 'rewriting any images matching these domains to webp: ' . implode( ',', $this->allowed_domains ) );
		$this->validate_user_exclusions();
	}

	/**
	 * Check if pages should be processed, especially for things like page builders.
	 *
	 * @since 6.2.2
	 *
	 * @param boolean $should_process Whether <picture> WebP should process the page.
	 * @param string  $uri The URI of the page (no domain or scheme included).
	 * @return boolean True to process the page, false to skip.
	 */
	function should_process_page( $should_process = true, $uri = '' ) {
		// Don't foul up the admin side of things, unless a plugin needs to.
		if ( is_admin() &&
			/**
			 * Provide plugins a way of running <picture> WebP for images in the WordPress Admin, usually for admin-ajax.php.
			 *
			 * @param bool false Allow <picture> WebP to run on the Dashboard. Defaults to false.
			 */
			false === apply_filters( 'eio_allow_admin_picture_webp', false )
		) {
			$this->debug_message( 'is_admin' );
			return false;
		}
		if ( ewww_image_optimizer_ce_webp_enabled() ) {
			return false;
		}
		if ( empty( $uri ) ) {
			$uri = $this->request_uri;
		}
		if ( false !== strpos( $uri, '?brizy-edit' ) ) {
			return false;
		}
		if ( false !== strpos( $uri, '&builder=true' ) ) {
			return false;
		}
		if ( false !== strpos( $uri, 'cornerstone=' ) || false !== strpos( $uri, 'cornerstone-endpoint' ) ) {
			return false;
		}
		if ( false !== strpos( $uri, 'ct_builder=' ) ) {
			return false;
		}
		if ( false !== strpos( $uri, 'ct_render_shortcode=' ) || false !== strpos( $uri, 'action=oxy_render' ) ) {
			return false;
		}
		if ( did_action( 'cornerstone_boot_app' ) || did_action( 'cs_before_preview_frame' ) ) {
			return false;
		}
		if ( false !== strpos( $uri, 'elementor-preview=' ) ) {
			return false;
		}
		if ( false !== strpos( $uri, 'et_fb=' ) ) {
			return false;
		}
		if ( false !== strpos( $uri, 'fb-edit=' ) ) {
			return false;
		}
		if ( false !== strpos( $uri, '?fl_builder' ) ) {
			return false;
		}
		if ( '/print/' === substr( $uri, -7 ) ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( false !== strpos( $uri, 'tatsu=' ) ) {
			return false;
		}
		if ( false !== strpos( $uri, 'tve=true' ) ) {
			return false;
		}
		if ( ! empty( $_POST['action'] ) && 'tatsu_get_concepts' === sanitize_text_field( wp_unslash( $_POST['action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}
		if ( is_customize_preview() ) {
			$this->debug_message( 'is_customize_preview' );
			return false;
		}
		global $wp_query;
		if ( ! isset( $wp_query ) || ! ( $wp_query instanceof WP_Query ) ) {
			return $should_process;
		}
		if ( $this->is_amp() ) {
			return false;
		}
		if ( is_embed() ) {
			$this->debug_message( 'is_embed' );
			return false;
		}
		if ( is_feed() ) {
			$this->debug_message( 'is_feed' );
			return false;
		}
		if ( is_preview() ) {
			$this->debug_message( 'is_preview' );
			return false;
		}
		if ( wp_script_is( 'twentytwenty-twentytwenty', 'enqueued' ) ) {
			$this->debug_message( 'twentytwenty enqueued' );
			return false;
		}
		return $should_process;
	}

	/**
	 * Grant read-only access to allowed WebP domains.
	 *
	 * @return array A list of WebP domains.
	 */
	function get_webp_domains() {
		return $this->allowed_domains;
	}

	/**
	 * Replaces images within a srcset attribute with their .webp derivatives.
	 *
	 * @param string $srcset A valid srcset attribute from an img element.
	 * @return bool|string False if no changes were made, or the new srcset if any WebP images replaced the originals.
	 */
	function srcset_replace( $srcset ) {
		$srcset_urls = explode( ' ', $srcset );
		$found_webp  = false;
		if ( $this->is_iterable( $srcset_urls ) && count( $srcset_urls ) > 1 ) {
			$this->debug_message( 'parsing srcset urls' );
			foreach ( $srcset_urls as $srcurl ) {
				if ( is_numeric( substr( $srcurl, 0, 1 ) ) ) {
					continue;
				}
				$trailing = ' ';
				if ( ',' === substr( $srcurl, -1 ) ) {
					$trailing = ',';
					$srcurl   = rtrim( $srcurl, ',' );
				}
				ewwwio_debug_message( "looking for $srcurl from srcset" );
				if ( $this->validate_image_url( $srcurl ) ) {
					$srcset = str_replace( $srcurl . $trailing, $this->generate_url( $srcurl ) . $trailing, $srcset );
					ewwwio_debug_message( "replaced $srcurl in srcset" );
					$found_webp = true;
				}
			}
		} elseif ( $this->validate_image_url( $srcset ) ) {
			return $this->generate_url( $srcset );
		}
		if ( $found_webp ) {
			return $srcset;
		} else {
			return false;
		}
	}

	/**
	 * Search for img elements and rewrite them with noscript elements for WebP replacement.
	 *
	 * Any img elements or elements that may be used in place of img elements by JS are checked to see
	 * if WebP derivatives exist. The element is then wrapped within a noscript element for fallback,
	 * and noscript element receives a copy of the attributes from the img along with webp replacement
	 * values for those attributes.
	 *
	 * @param string $buffer The full HTML page generated since the output buffer was started.
	 * @return string The altered buffer containing the full page with WebP images inserted.
	 */
	function filter_page_output( $buffer ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		if (
			empty( $buffer ) ||
			preg_match( '/^<\?xml/', $buffer ) ||
			strpos( $buffer, 'amp-boilerplate' )
		) {
			$this->debug_message( 'picture WebP disabled' );
			return $buffer;
		}
		if ( $this->is_json( $buffer ) ) {
			return $buffer;
		}
		if ( ! $this->should_process_page() ) {
			$this->debug_message( 'picture WebP should not process page' );
			return $buffer;
		}
		if ( ! apply_filters( 'eio_do_picture_webp', true, $this->request_uri ) ) {
			return $buffer;
		}

		$images = $this->get_images_from_html( preg_replace( '/<(picture|noscript).*?\/\1>/s', '', $buffer ), false );
		if ( ! empty( $images[0] ) && $this->is_iterable( $images[0] ) ) {
			foreach ( $images[0] as $index => $image ) {
				if ( ! $this->validate_img_tag( $image ) ) {
					continue;
				}
				$file = $images['img_url'][ $index ];
				ewwwio_debug_message( "parsing an image: $file" );
				if ( $this->validate_image_url( $file ) ) {
					// If a CDN path match was found, or .webp image existence is confirmed.
					ewwwio_debug_message( 'found a webp image or forced path' );
					$srcset      = $this->get_attribute( $image, 'srcset' );
					$srcset_webp = '';
					if ( $srcset ) {
						$srcset_webp = $this->srcset_replace( $srcset );
					}
					$sizes_attr = '';
					if ( empty( $srcset_webp ) ) {
						$srcset_webp = $this->generate_url( $file );
					} else {
						$sizes = $this->get_attribute( $image, 'sizes' );
						if ( $sizes ) {
							$sizes_attr = "sizes='$sizes'";
						}
					}
					if ( empty( $srcset_webp ) || $srcset_webp === $file ) {
						continue;
					}
					$pic_img = $image;
					$this->set_attribute( $pic_img, 'data-eio', 'p', true );
					$picture_tag = "<picture><source srcset=\"$srcset_webp\" $sizes_attr type='image/webp'>$pic_img</picture>";
					ewwwio_debug_message( "going to swap\n$image\nwith\n$picture_tag" );
					$buffer = str_replace( $image, $picture_tag, $buffer );
				}
			} // End foreach().
		} // End if().
		// Images listed as picture/source elements.
		$pictures = $this->get_picture_tags_from_html( $buffer );
		if ( $this->is_iterable( $pictures ) ) {
			foreach ( $pictures as $index => $picture ) {
				if ( strpos( $picture, 'image/webp' ) ) {
					continue;
				}
				if ( ! $this->validate_tag( $picture ) ) {
					continue;
				}
				$sources = $this->get_elements_from_html( $picture, 'source' );
				if ( $this->is_iterable( $sources ) ) {
					foreach ( $sources as $source ) {
						$this->debug_message( "parsing a picture source: $source" );
						$srcset_attr_name = 'srcset';
						if ( false !== strpos( $source, 'base64,R0lGOD' ) && false !== strpos( $source, 'data-srcset=' ) ) {
							$srcset_attr_name = 'data-srcset';
						} elseif ( ! $this->get_attribute( $source, $srcset_attr_name ) && false !== strpos( $source, 'data-srcset=' ) ) {
							$srcset_attr_name = 'data-srcset';
						}
						$srcset = $this->get_attribute( $source, $srcset_attr_name );
						if ( $srcset ) {
							$srcset_webp = $this->srcset_replace( $srcset );
							if ( $srcset_webp ) {
								$source_webp = str_replace( $srcset, $srcset_webp, $source );
								$this->set_attribute( $source_webp, 'type', 'image/webp' );
								$picture = str_replace( $source, $source_webp . $source, $picture );
							}
						}
					}
					if ( $picture !== $pictures[ $index ] ) {
						$this->debug_message( 'found webp for picture element' );
						$buffer = str_replace( $pictures[ $index ], $picture, $buffer );
					}
				}
			}
		}
		$this->debug_message( 'all done parsing page for picture webp' );
		return $buffer;
	}

	/**
	 * Parse template data from FacetWP that will be included in JSON response.
	 * https://facetwp.com/documentation/developers/output/facetwp_render_output/
	 *
	 * @param array $output The full array of FacetWP data.
	 * @return array The FacetWP data with WebP images.
	 */
	function filter_facetwp_json_output( $output ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( empty( $output['template'] ) || ! is_string( $output['template'] ) ) {
			return $output;
		}

		$template = $this->filter_page_output( $output['template'] );
		if ( $template ) {
			$this->debug_message( 'template data modified' );
			$output['template'] = $template;
		}

		return $output;
	}

	/**
	 * Converts a URL to a file-system path and checks if the resulting path exists.
	 *
	 * @param string $url The URL to mangle.
	 * @param string $extension An optional extension to append during is_file().
	 * @return bool True if a local file exists correlating to the URL, false otherwise.
	 */
	function url_to_path_exists( $url, $extension = '' ) {
		return parent::url_to_path_exists( $url, '.webp' );
	}

	/**
	 * Validate the user-defined exclusions.
	 */
	function validate_user_exclusions() {
		$user_exclusions = $this->get_option( $this->prefix . 'webp_rewrite_exclude' );
		$this->debug_message( $this->prefix . 'webp_rewrite_exclude' );
		if ( ! empty( $user_exclusions ) ) {
			if ( is_string( $user_exclusions ) ) {
				$user_exclusions = array( $user_exclusions );
			}
			if ( is_array( $user_exclusions ) ) {
				foreach ( $user_exclusions as $exclusion ) {
					if ( ! is_string( $exclusion ) ) {
						continue;
					}
					if (
						'a' === $exclusion ||
						'div' === $exclusion ||
						'li' === $exclusion ||
						'picture' === $exclusion ||
						'section' === $exclusion ||
						'span' === $exclusion ||
						'video' === $exclusion
					) {
						continue;
					}
					$this->user_exclusions[] = $exclusion;
				}
			}
		}
	}

	/**
	 * Checks if the tag is allowed to be rewritten.
	 *
	 * @param string $image The HTML tag: img, span, etc.
	 * @return bool False if it flags a filter or exclusion, true otherwise.
	 */
	function validate_tag( $image ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// For now, only picture tags are allowed anyway, so just roll with it!
		return true;
	}

	/**
	 * Checks if the img tag is allowed to be rewritten.
	 *
	 * @param string $image The img tag.
	 * @return bool False if it flags a filter or exclusion, true otherwise.
	 */
	function validate_img_tag( $image ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Skip inline data URIs.
		if ( false !== strpos( $image, 'data:image' ) ) {
			$this->debug_message( 'data:image pattern detected in src' );
			return false;
		}
		// Ignore 0-size Pinterest schema images.
		if ( strpos( $image, 'data-pin-description=' ) && strpos( $image, 'width="0" height="0"' ) ) {
			$this->debug_message( 'data-pin-description img skipped' );
			return false;
		}

		$exclusions = apply_filters(
			'ewwwio_picture_webp_exclusions',
			array_merge(
				array(
					'lazyload',
					'class="ls-bg',
					'class="ls-l',
					'class="rev-slidebg',
					'data-bgposition=',
					'data-envira-src=',
					'data-lazy=',
					'data-lazy-original=',
					'data-lazy-src=',
					'data-lazy-srcset=',
					'data-lazyload=',
					'data-lazysrc=',
					'data-no-lazy=',
					'data-src=',
					'data-srcset=',
					'fullurl=',
					'gazette-featured-content-thumbnail',
					'jetpack-lazy-image',
					'lazy-slider-img=',
					'mgl-lazy',
					'skip-lazy',
					'timthumb.php?',
					'wpcf7_captcha/',
				),
				$this->user_exclusions
			),
			$image
		);
		foreach ( $exclusions as $exclusion ) {
			if ( false !== strpos( $image, $exclusion ) ) {
				$this->debug_message( "img matched $exclusion" );
				return false;
			}
		}
		return true;
	}

	/**
	 * Checks if the path is a valid WebP image, on-disk or forced.
	 *
	 * @param string $image The image URL.
	 * @return bool True if the file exists or matches a forced path, false otherwise.
	 */
	function validate_image_url( $image ) {
		$this->debug_message( __METHOD__ . "() webp validation for $image" );
		if ( $this->is_lazy_placeholder( $image ) ) {
			return false;
		}
		// Cleanup the image from encoded HTML characters.
		$image = str_replace( '&#038;', '&', $image );
		$image = str_replace( '#038;', '&', $image );

		$extension  = '';
		$image_path = $this->parse_url( $image, PHP_URL_PATH );
		if ( ! is_null( $image_path ) && $image_path ) {
			$extension = strtolower( pathinfo( $image_path, PATHINFO_EXTENSION ) );
		}
		if ( $extension && 'gif' === $extension && ! $this->get_option( 'ewww_image_optimizer_force_gif2webp' ) ) {
			return false;
		}
		if ( $extension && 'svg' === $extension ) {
			return false;
		}
		if ( $extension && 'webp' === $extension ) {
			return false;
		}
		if ( apply_filters( 'ewww_image_optimizer_skip_webp_rewrite', false, $image ) ) {
			return false;
		}
		if ( $this->get_option( 'ewww_image_optimizer_webp_force' ) && $this->is_iterable( $this->allowed_urls ) ) {
			// Check the image for configured CDN paths.
			foreach ( $this->allowed_urls as $allowed_url ) {
				if ( strpos( $image, $allowed_url ) !== false ) {
					$this->debug_message( 'forced cdn image' );
					return true;
				}
			}
		} elseif ( $this->allowed_urls && $this->allowed_domains ) {
			if ( $this->cdn_to_local( $image ) ) {
				return true;
			}
		}
		return $this->url_to_path_exists( $image );
	}

	/**
	 * Generate a WebP URL by appending .webp to the filename.
	 *
	 * @param string $url The image url.
	 * @return string The WebP version of the image url.
	 */
	function generate_url( $url ) {
		$path_parts = explode( '?', $url );
		return $path_parts[0] . '.webp' . ( ! empty( $path_parts[1] ) && 'is-pending-load=1' !== $path_parts[1] ? '?' . $path_parts[1] : '' );
	}

	/**
	 * Adds a small CSS block to make sure images in gallery blocks behave.
	 */
	function gallery_block_css() {
		echo '<style>.wp-block-gallery.is-cropped .blocks-gallery-item picture{height:100%;width:100%;}</style>';
	}
}

global $eio_picture_webp;
$eio_picture_webp = new EIO_Picture_Webp();
