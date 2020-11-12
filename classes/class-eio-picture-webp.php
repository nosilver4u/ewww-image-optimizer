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
	 * Allowed paths for Picture WebP.
	 *
	 * @access protected
	 * @var array $webp_paths
	 */
	protected $webp_paths = array();

	/**
	 * Allowed domains for Picture WebP.
	 *
	 * @access protected
	 * @var array $webp_domains
	 */
	protected $webp_domains = array();

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

		// Make sure gallery block images crop properly.
		add_action( 'wp_head', array( $this, 'gallery_block_css' ) );
		// Hook onto the output buffer function.
		if ( function_exists( 'swis' ) && swis()->settings->get_option( 'lazy_load' ) ) {
			add_filter( 'swis_filter_page_output', array( $this, 'filter_page_output' ) );
		} else {
			add_filter( 'ewww_image_optimizer_filter_page_output', array( $this, 'filter_page_output' ), 10 );
		}

		$this->home_url = trailingslashit( get_site_url() );
		ewwwio_debug_message( "home url: $this->home_url" );
		$this->relative_home_url = preg_replace( '/https?:/', '', $this->home_url );
		ewwwio_debug_message( "relative home url: $this->relative_home_url" );
		$upload_dir        = wp_get_upload_dir();
		$this->content_url = trailingslashit( ! empty( $upload_dir['baseurl'] ) ? $upload_dir['baseurl'] : content_url( 'uploads' ) );
		ewwwio_debug_message( "content_url: $this->content_url" );
		$this->home_domain = $this->parse_url( $this->home_url, PHP_URL_HOST );
		ewwwio_debug_message( "home domain: $this->home_domain" );

		$this->webp_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' );
		if ( ! is_array( $this->webp_paths ) ) {
			$this->webp_paths = array();
		}

		// Find the WP Offload Media domain/path.
		if ( class_exists( 'Amazon_S3_And_CloudFront' ) ) {
			global $as3cf;
			$s3_scheme = $as3cf->get_url_scheme();
			$s3_bucket = $as3cf->get_setting( 'bucket' );
			$s3_region = $as3cf->get_setting( 'region' );
			if ( is_wp_error( $s3_region ) ) {
				$s3_region = '';
			}
			if ( ! empty( $s3_bucket ) && ! is_wp_error( $s3_bucket ) && method_exists( $as3cf, 'get_provider' ) ) {
				$s3_domain = $as3cf->get_provider()->get_url_domain( $s3_bucket, $s3_region, null, array(), true );
			} elseif ( ! empty( $s3_bucket ) && ! is_wp_error( $s3_bucket ) && method_exists( $as3cf, 'get_storage_provider' ) ) {
				$s3_domain = $as3cf->get_storage_provider()->get_url_domain( $s3_bucket, $s3_region );
			}
			if ( ! empty( $s3_domain ) && $as3cf->get_setting( 'serve-from-s3' ) ) {
				$this->debug_message( "found S3 domain of $s3_domain with bucket $s3_bucket and region $s3_region" );
				$this->webp_paths[] = $s3_scheme . '://' . $s3_domain . '/';
				if ( $as3cf->get_setting( 'enable-delivery-domain' ) && $as3cf->get_setting( 'delivery-domain' ) ) {
					$delivery_domain    = $as3cf->get_setting( 'delivery-domain' );
					$this->webp_paths[] = $s3_scheme . '://' . $delivery_domain . '/';
					$this->debug_message( "found WOM delivery domain of $delivery_domain" );
				}
				$this->s3_active = $s3_domain;
				if ( $as3cf->get_setting( 'enable-object-prefix' ) ) {
					$this->s3_object_prefix = $as3cf->get_setting( 'object-prefix' );
					$this->debug_message( $as3cf->get_setting( 'object-prefix' ) );
				} else {
					$this->debug_message( 'no WOM prefix' );
				}
				if ( $as3cf->get_setting( 'object-versioning' ) ) {
					$this->s3_object_version = true;
					$this->debug_message( 'object versioning enabled' );
				}
			}
		}

		if ( function_exists( 'swis' ) && swis()->settings->get_option( 'cdn_domain' ) ) {
			$this->webp_paths[] = swis()->settings->get_option( 'cdn_domain' );
		}
		foreach ( $this->webp_paths as $webp_path ) {
			$webp_domain = $this->parse_url( $webp_path, PHP_URL_HOST );
			if ( $webp_domain ) {
				$this->webp_domains[] = $webp_domain;
			}
		}
		ewwwio_debug_message( 'checking any images matching these patterns for webp: ' . implode( ',', $this->webp_paths ) );
		ewwwio_debug_message( 'rewriting any images matching these domains to webp: ' . implode( ',', $this->webp_domains ) );
		$this->validate_user_exclusions();
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
		if ( ewww_image_optimizer_iterable( $srcset_urls ) && count( $srcset_urls ) > 1 ) {
			ewwwio_debug_message( 'parsing srcset urls' );
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
		// If any of this is true, don't filter the page.
		$uri = add_query_arg( null, null );
		$this->debug_message( "request uri is $uri" );
		if (
			empty( $buffer ) ||
			is_admin() ||
			strpos( $uri, 'cornerstone=' ) !== false ||
			strpos( $uri, 'cornerstone-endpoint' ) !== false ||
			did_action( 'cornerstone_boot_app' ) || did_action( 'cs_before_preview_frame' ) ||
			'/print/' === substr( $uri, -7 ) ||
			strpos( $uri, 'elementor-preview=' ) !== false ||
			strpos( $uri, 'et_fb=' ) !== false ||
			strpos( $uri, 'tatsu=' ) !== false ||
			( ! empty( $_POST['action'] ) && 'tatsu_get_concepts' === sanitize_text_field( wp_unslash( $_POST['action'] ) ) ) || // phpcs:ignore WordPress.Security.NonceVerification
			is_embed() ||
			is_feed() ||
			is_preview() ||
			is_customize_preview() ||
			( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
			preg_match( '/^<\?xml/', $buffer ) ||
			strpos( $buffer, 'amp-boilerplate' ) ||
			$this->is_amp() ||
			ewww_image_optimizer_ce_webp_enabled()
		) {
			ewwwio_debug_message( 'picture WebP disabled' );
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
		ewwwio_debug_message( 'all done parsing page for picture webp' );
		return $buffer;
	}

	/**
	 * Attempts to reverse a CDN URL to a local path to test for file existence.
	 *
	 * Used for supporting pull-mode CDNs without forcing everything to WebP.
	 *
	 * @param string $url The image URL to mangle.
	 * @return bool True if a local file exists correlating to the CDN URL, false otherwise.
	 */
	function cdn_to_local( $url ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! is_array( $this->webp_domains ) || ! count( $this->webp_domains ) ) {
			return false;
		}
		foreach ( $this->webp_domains as $webp_domain ) {
			if ( $webp_domain === $this->home_domain ) {
				continue;
			}
			ewwwio_debug_message( "looking for $webp_domain in $url" );
			if (
				! empty( $this->s3_active ) &&
				false !== strpos( $url, $this->s3_active ) &&
				(
					( false !== strpos( $this->s3_active, '/' ) ) ||
					( ! empty( $this->s3_object_prefix ) && false !== strpos( $url, $this->s3_object_prefix ) )
				)
			) {
				// We will wait until the paths loop to fix this one.
				continue;
			}
			if ( false !== strpos( $url, $webp_domain ) ) {
				$local_url = str_replace( $webp_domain, $this->home_domain, $url );
				ewwwio_debug_message( "found $webp_domain, replaced with $this->home_domain to get $local_url" );
				if ( $this->url_to_path_exists( $local_url ) ) {
					return true;
				}
			}
		}
		foreach ( $this->webp_paths as $webp_path ) {
			if ( false === strpos( $webp_path, 'http' ) ) {
				continue;
			}
			ewwwio_debug_message( "looking for $webp_path in $url" );
			if (
				! empty( $this->s3_active ) &&
				false !== strpos( $url, $this->s3_active ) &&
				! empty( $this->s3_object_prefix ) &&
				0 === strpos( $url, $webp_path . $this->s3_object_prefix )
			) {
				$local_url = str_replace( $webp_path . $this->s3_object_prefix, $this->content_url, $url );
				ewwwio_debug_message( "found $webp_path (and $this->s3_object_prefix), replaced with $this->content_url to get $local_url" );
				if ( $this->url_to_path_exists( $local_url ) ) {
					return true;
				}
			}
			if ( false !== strpos( $url, $webp_path ) ) {
				$local_url = str_replace( $webp_path, $this->content_url, $url );
				ewwwio_debug_message( "found $webp_path, replaced with $this->content_url to get $local_url" );
				if ( $this->url_to_path_exists( $local_url ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Remove S3 object versioning from URL.
	 *
	 * @param string $url The image URL with a potential version string embedded.
	 * @return string The URL without a version string.
	 */
	function maybe_strip_object_version( $url ) {
		if ( ! empty( $this->s3_object_version ) ) {
			$possible_version = basename( dirname( $url ) );
			if (
				! empty( $possible_version ) &&
				8 === strlen( $possible_version ) &&
				ctype_digit( $possible_version )
			) {
				$url = str_replace( '/' . $possible_version . '/', '/', $url );
				ewwwio_debug_message( "removed version $possible_version from $url" );
			} elseif (
				! empty( $possible_version ) &&
				14 === strlen( $possible_version ) &&
				ctype_digit( $possible_version )
			) {
				$year  = substr( $possible_version, 0, 4 );
				$month = substr( $possible_version, 4, 2 );
				$url   = str_replace( '/' . $possible_version . '/', "/$year/$month/", $url );
				ewwwio_debug_message( "removed version $possible_version from $url" );
			}
		}
		return $url;
	}

	/**
	 * Converts a URL to a file-system path and checks if the resulting path exists.
	 *
	 * @param string $url The URL to mangle.
	 * @param string $extension An optional extension to append during is_file().
	 * @return bool True if a local file exists correlating to the URL, false otherwise.
	 */
	function url_to_path_exists( $url, $extension = '' ) {
		$url = $this->maybe_strip_object_version( $url );
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
		ewwwio_debug_message( __METHOD__ . "() webp validation for $image" );
		if (
			strpos( $image, 'base64,R0lGOD' ) ||
			strpos( $image, 'lazy-load/images/1x1' ) ||
			strpos( $image, '/assets/images/' )
		) {
			ewwwio_debug_message( 'lazy load placeholder' );
			return false;
		}
		$extension  = '';
		$image_path = $this->parse_url( $image, PHP_URL_PATH );
		if ( ! is_null( $image_path ) && $image_path ) {
			$extension = strtolower( pathinfo( $image_path, PATHINFO_EXTENSION ) );
		}
		if ( $extension && 'gif' === $extension && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_force_gif2webp' ) ) {
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
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) && $this->webp_paths ) {
			// Check the image for configured CDN paths.
			foreach ( $this->webp_paths as $webp_path ) {
				if ( strpos( $image, $webp_path ) !== false ) {
					ewwwio_debug_message( 'forced cdn image' );
					return true;
				}
			}
		} elseif ( $this->webp_paths && $this->webp_domains ) {
			if ( $this->cdn_to_local( $image ) ) {
				return true;
			}
		}
		return $this->url_to_path_exists( $image );
	}

	/**
	 * Generate a WebP url.
	 *
	 * Adds .webp to the end.
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
