<?php
/**
 * Implements WebP rewriting using page parsing and JS functionality.
 *
 * @link https://ewww.io
 * @package EIO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enables EWWW IO to filter the page content and replace img elements with WebP markup.
 */
class EIO_Alt_Webp extends EIO_Page_Parser {

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
	 * The Alt WebP inline script contents. Current length 11704.
	 *
	 * @access private
	 * @var string $inline_script
	 */
	private $inline_script = '';

	/**
	 * Indicates if we are filtering ExactDN urls.
	 *
	 * @access protected
	 * @var bool $parsing_exactdn
	 */
	protected $parsing_exactdn = false;

	/**
	 * Allowed paths for JS WebP.
	 *
	 * @access protected
	 * @var array $webp_paths
	 */
	protected $webp_paths = array();

	/**
	 * Allowed domains for JS WebP.
	 *
	 * @access protected
	 * @var array $webp_domains
	 */
	protected $webp_domains = array();

	/**
	 * Register (once) actions and filters for Alt WebP.
	 */
	function __construct() {
		global $eio_alt_webp;
		if ( is_object( $eio_alt_webp ) ) {
			return 'you are doing it wrong';
		}
		if ( ewww_image_optimizer_ce_webp_enabled() ) {
			return false;
		}
		parent::__construct();
		// Start an output buffer before any output starts.
		/* add_action( 'template_redirect', array( $this, 'buffer_start' ), 0 ); */
		add_filter( 'ewww_image_optimizer_filter_page_output', array( $this, 'filter_page_output' ), 20 );
		// Filter for NextGEN image urls within JS.
		add_filter( 'ngg_pro_lightbox_images_queue', array( $this, 'ngg_pro_lightbox_images_queue' ), 11 );

		// Load up the minified script so we can inline it.
		$this->inline_script = file_get_contents( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'includes/load_webp.min.js' );

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
				ewwwio_debug_message( "found S3 domain of $s3_domain with bucket $s3_bucket and region $s3_region" );
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
		$this->debug_message( 'checking any images matching these patterns for webp: ' . implode( ',', $this->webp_paths ) );
		$this->debug_message( 'rewriting any images matching these domains to webp: ' . implode( ',', $this->webp_domains ) );
		if ( class_exists( 'ExactDN' ) && $this->get_option( 'ewww_image_optimizer_exactdn' ) ) {
			global $exactdn;
			$this->exactdn_domain = $exactdn->get_exactdn_domain();
			if ( $this->exactdn_domain ) {
				$this->parsing_exactdn = true;
				$this->debug_message( 'parsing an exactdn page' );
			}
		}

		// Load the appropriate JS.
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			// Load the non-minified, non-inline version of the webp rewrite script.
			add_action( 'wp_enqueue_scripts', array( $this, 'debug_script' ) );
		} elseif ( defined( 'EWWW_IMAGE_OPTIMIZER_WEBP_EXTERNAL_SCRIPT' ) && EWWW_IMAGE_OPTIMIZER_WEBP_EXTERNAL_SCRIPT ) {
			// Load the minified, non-inline version of the webp rewrite script.
			add_action( 'wp_enqueue_scripts', array( $this, 'min_external_script' ) );
		} else {
			add_action( 'wp_head', array( $this, 'inline_script' ) );
		}
		$this->validate_user_exclusions();
	}


	/**
	 * Starts an output buffer and registers the callback function to do WebP replacement.
	 */
	function buffer_start() {
		ob_start( array( $this, 'filter_page_output' ) );
	}

	/**
	 * Copies attributes from the original img element to the noscript element.
	 *
	 * @param string $image The full text of the img element.
	 * @param string $nscript A noscript element that will be given all the (known) attributes of $image.
	 * @param string $prefix Optional. Value to prepend to all attribute names. Default 'data-'.
	 * @return string The modified noscript tag.
	 */
	function attr_copy( $image, $nscript, $prefix = 'data-' ) {
		if ( ! is_string( $image ) || ! is_string( $nscript ) ) {
			return $nscript;
		}
		$attributes = array(
			'accesskey',
			'align',
			'alt',
			'border',
			'class',
			'contenteditable',
			'contextmenu',
			'crossorigin',
			'dir',
			'draggable',
			'dropzone',
			'height',
			'hidden',
			'hspace',
			'id',
			'ismap',
			'lang',
			'longdesc',
			'sizes',
			'spellcheck',
			'style',
			'tabindex',
			'title',
			'translate',
			'usemap',
			'vspace',
			'width',
			'data-animation',
			'data-attachment-id',
			'data-auto-height',
			'data-caption',
			'data-comments-opened',
			'data-delay',
			'data-event-trigger',
			'data-flex_fx',
			'data-height',
			'data-hide-on-end',
			'data-highlight-color',
			'data-highlight-border-color',
			'data-highlight-border-opacity',
			'data-highlight-border-width',
			'data-highlight-opacity',
			'data-image-meta',
			'data-image-title',
			'data-image-description',
			'data-interval',
			'data-large_image_width',
			'data-large_image_height',
			'data-lazy',
			'data-lazy-type',
			'data-mode',
			'data-name',
			'data-no-lazy',
			'data-orig-size',
			'data-partial',
			'data-per-view',
			'data-permalink',
			'data-pin-description',
			'data-pin-id',
			'data-pin-media',
			'data-pin-url',
			'data-rel',
			'data-ride',
			'data-shadow',
			'data-shadow-direction',
			'data-slide',
			'data-slide-to',
			'data-target',
			'data-vc-zoom',
			'data-width',
			'data-wrap',
		);
		foreach ( $attributes as $attribute ) {
			$attr_value = $this->get_attribute( $image, $attribute );
			if ( $attr_value ) {
				$this->set_attribute( $nscript, $prefix . $attribute, $attr_value );
			}
		}
		return $nscript;
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
	 * Replaces images with the Jetpack data attributes with their .webp derivatives.
	 *
	 * @param string $image The full text of the img element.
	 * @param string $nscript A noscript element that will be assigned the jetpack data attributes.
	 * @return string The modified noscript tag.
	 */
	function jetpack_replace( $image, $nscript ) {
		$data_orig_file = $this->get_attribute( $image, 'data-orig-file' );
		if ( $data_orig_file ) {
			ewwwio_debug_message( "looking for data-orig-file: $data_orig_file" );
			if ( $this->validate_image_url( $data_orig_file ) ) {
				$this->set_attribute( $nscript, 'data-webp-orig-file', $this->generate_url( $data_orig_file ) );
				ewwwio_debug_message( "replacing $data_orig_file in data-orig-file" );
			}
			$this->set_attribute( $nscript, 'data-orig-file', $data_orig_file, true );
		}
		$data_medium_file = $this->get_attribute( $image, 'data-medium-file' );
		if ( $data_medium_file ) {
			ewwwio_debug_message( "looking for data-medium-file: $data_medium_file" );
			if ( $this->validate_image_url( $data_medium_file ) ) {
				$this->set_attribute( $nscript, 'data-webp-medium-file', $this->generate_url( $data_medium_file ) );
				ewwwio_debug_message( "replacing $data_medium_file in data-medium-file" );
			}
			$this->set_attribute( $nscript, 'data-medium-file', $data_medium_file, true );
		}
		$data_large_file = $this->get_attribute( $image, 'data-large-file' );
		if ( $data_large_file ) {
			ewwwio_debug_message( "looking for data-large-file: $data_large_file" );
			if ( $this->validate_image_url( $data_large_file ) ) {
				$this->set_attribute( $nscript, 'data-webp-large-file', $this->generate_url( $data_large_file ) );
				ewwwio_debug_message( "replacing $data_large_file in data-large-file" );
			}
			$this->set_attribute( $nscript, 'data-large-file', $data_large_file, true );
		}
		return $nscript;
	}

	/**
	 * Replaces images with the WooCommerce data attributes with their .webp derivatives.
	 *
	 * @param string $image The full text of the img element.
	 * @param string $nscript A noscript element that will be assigned the WooCommerce data attributes.
	 * @return string The modified noscript tag.
	 */
	function woocommerce_replace( $image, $nscript ) {
		$data_large_image = $this->get_attribute( $image, 'data-large_image' );
		if ( $data_large_image ) {
			ewwwio_debug_message( "looking for data-large_image: $data_large_image" );
			if ( $this->validate_image_url( $data_large_image ) ) {
				$this->set_attribute( $nscript, 'data-webp-large_image', $this->generate_url( $data_large_image ) );
				ewwwio_debug_message( "replacing $data_large_image in data-large_image" );
			}
			$this->set_attribute( $nscript, 'data-large_image', $data_large_image );
		}
		$data_src = $this->get_attribute( $image, 'data-src' );
		if ( $data_src ) {
			ewwwio_debug_message( "looking for data-src: $data_src" );
			if ( $this->validate_image_url( $data_src ) ) {
				$this->set_attribute( $nscript, 'data-webp-src', $this->generate_url( $data_src ) );
				ewwwio_debug_message( "replacing $data_src in data-src" );
			}
			$this->set_attribute( $nscript, 'data-src', $data_src );
		}
		return $nscript;
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
			ewwwio_debug_message( 'JS WebP disabled' );
			return $buffer;
		}

		if ( in_array( 'picture', $this->user_element_exclusions, true ) ) {
			$images = $this->get_images_from_html( preg_replace( '/<(picture|noscript).*?\/\1>/s', '', $buffer ), false );
		} else {
			$images = $this->get_images_from_html( preg_replace( '/<noscript.*?\/noscript>/s', '', $buffer ), false );
		}
		if ( ! empty( $images[0] ) && $this->is_iterable( $images[0] ) ) {
			foreach ( $images[0] as $index => $image ) {
				// Ignore 0-size Pinterest schema images.
				if ( strpos( $image, 'data-pin-description=' ) && strpos( $image, 'width="0" height="0"' ) ) {
					continue;
				}
				if ( ! $this->validate_tag( $image ) ) {
					continue;
				}
				$file = $images['img_url'][ $index ];
				ewwwio_debug_message( "parsing an image: $file" );
				if ( strpos( $image, 'jetpack-lazy-image' ) && $this->validate_image_url( $file ) ) {
					$new_image = $image;
					$new_image = $this->jetpack_replace( $image, $new_image );
					$real_file = $this->get_attribute( $new_image, 'data-lazy-src' );
					ewwwio_debug_message( 'checking webp for Jetpack Lazy Load data-lazy-src' );
					if ( $real_file && $this->validate_image_url( $real_file ) ) {
						ewwwio_debug_message( "found webp for Lazy Load: $real_file" );
						$this->set_attribute( $new_image, 'data-lazy-src-webp', $this->generate_url( $real_file ) );
					}
					$srcset = $this->get_attribute( $new_image, 'data-lazy-srcset' );
					if ( $srcset ) {
						$srcset_webp = $this->srcset_replace( $srcset );
						if ( $srcset_webp ) {
							$this->set_attribute( $new_image, 'data-lazy-srcset-webp', $srcset_webp );
						}
					}
					if ( $new_image !== $image ) {
						$this->set_attribute( $new_image, 'class', $this->get_attribute( $new_image, 'class' ) . ' ewww_webp_lazy_load', true );
						$buffer = str_replace( $image, $new_image, $buffer );
					}
				} elseif ( $this->validate_image_url( $file ) && false === strpos( $image, 'lazyload' ) ) {
					// If a CDN path match was found, or .webp image existence is confirmed, and this is not a lazy-load 'dummy' image.
					ewwwio_debug_message( 'found a webp image or forced path' );
					$nscript = '<noscript>';
					$this->set_attribute( $nscript, 'data-img', $file );
					$this->set_attribute( $nscript, 'data-webp', $this->generate_url( $file ) );
					$srcset = $this->get_attribute( $image, 'srcset' );
					if ( $srcset ) {
						$srcset_webp = $this->srcset_replace( $srcset );
						if ( $srcset_webp ) {
							$this->set_attribute( $nscript, 'data-srcset-webp', $srcset_webp );
						}
						$this->set_attribute( $nscript, 'data-srcset-img', $srcset );
					}
					if ( $this->get_attribute( $image, 'data-orig-file' ) && $this->get_attribute( $image, 'data-medium-file' ) && $this->get_attribute( $image, 'data-large-file' ) ) {
						$nscript = $this->jetpack_replace( $image, $nscript );
					}
					if ( $this->get_attribute( $image, 'data-large_image' ) && $this->get_attribute( $image, 'data-src' ) ) {
						$nscript = $this->woocommerce_replace( $image, $nscript );
					}
					$nscript = $this->attr_copy( $image, $nscript );
					$this->set_attribute( $nscript, 'class', 'ewww_webp' );
					$ns_img = $image;
					$this->set_attribute( $ns_img, 'data-eio', 'j', true );
					ewwwio_debug_message( "going to swap\n$image\nwith\n$nscript" . $ns_img . '</noscript>' );
					$buffer = str_replace( $image, $nscript . $ns_img . '</noscript>', $buffer );
				} elseif ( ! empty( $file ) && strpos( $image, 'data-lazy-src=' ) ) {
					// BJ Lazy Load & WP Rocket.
					$new_image = $image;
					$real_file = $this->get_attribute( $new_image, 'data-lazy-src' );
					ewwwio_debug_message( "checking webp for Lazy Load data-lazy-src: $real_file" );
					if ( $this->validate_image_url( $real_file ) ) {
						ewwwio_debug_message( "found webp for Lazy Load: $real_file" );
						$this->set_attribute( $new_image, 'data-lazy-src-webp', $this->generate_url( $real_file ) );
					}
					$srcset = $this->get_attribute( $new_image, 'data-lazy-srcset' );
					if ( $srcset ) {
						$srcset_webp = $this->srcset_replace( $srcset );
						if ( $srcset_webp ) {
							$this->set_attribute( $new_image, 'data-lazy-srcset-webp', $srcset_webp );
						}
					}
					if ( $new_image !== $image ) {
						$this->set_attribute( $new_image, 'class', $this->get_attribute( $new_image, 'class' ) . ' ewww_webp_lazy_load', true );
						$buffer = str_replace( $image, $new_image, $buffer );
					}
				} elseif ( ! empty( $file ) && strpos( $image, 'data-src=' ) && ( strpos( $image, 'data-lazy-type="image' ) || strpos( $image, 'lazyload' ) ) ) {
					// a3 Lazy Load.
					$new_image = $image;
					$real_file = $this->get_attribute( $new_image, 'data-src' );
					ewwwio_debug_message( "checking webp for Lazy Load data-src: $real_file" );
					if ( $this->validate_image_url( $real_file ) ) {
						ewwwio_debug_message( 'found webp for Lazy Load' );
						$this->set_attribute( $new_image, 'data-src-webp', $this->generate_url( $real_file ) );
					}
					$srcset = $this->get_attribute( $new_image, 'data-srcset' );
					if ( $srcset ) {
						$srcset_webp = $this->srcset_replace( $srcset );
						if ( $srcset_webp ) {
							$this->set_attribute( $new_image, 'data-srcset-webp', $srcset_webp );
						}
					}
					if ( $new_image !== $image ) {
						$this->set_attribute( $new_image, 'class', $this->get_attribute( $new_image, 'class' ) . ' ewww_webp_lazy_load', true );
						$buffer = str_replace( $image, $new_image, $buffer );
					}
				} elseif ( ! empty( $file ) && strpos( $image, 'data-lazysrc=' ) && strpos( $image, '/essential-grid' ) ) {
					// Essential Grid.
					$new_image = $image;
					$real_file = $this->get_attribute( $new_image, 'data-lazysrc' );
					ewwwio_debug_message( "checking webp for EG Lazy Load data-lazysrc: $real_file" );
					if ( $this->validate_image_url( $real_file ) ) {
						ewwwio_debug_message( "found webp for Lazy Load: $real_file" );
						$this->set_attribute( $new_image, 'data-lazysrc-webp', $this->generate_url( $real_file ) );
					}
					if ( $new_image !== $image ) {
						$this->set_attribute( $new_image, 'class', $this->get_attribute( $new_image, 'class' ) . ' ewww_webp_lazy_load', true );
						$buffer = str_replace( $image, $new_image, $buffer );
					}
				}
				// Rev Slider data-lazyload attribute on image elements.
				if ( $this->get_attribute( $image, 'data-lazyload' ) ) {
					$new_image = $image;
					$lazyload  = $this->get_attribute( $new_image, 'data-lazyload' );
					if ( $lazyload ) {
						if ( $this->validate_image_url( $lazyload ) ) {
							$this->set_attribute( $new_image, 'data-webp-lazyload', $this->generate_url( $lazyload ) );
							ewwwio_debug_message( "replacing with webp for data-lazyload: $lazyload" );
							$buffer = str_replace( $image, $new_image, $buffer );
						}
					}
				}
			} // End foreach().
		} // End if().
		// Now we will look for any lazy images that don't have a src attribute (this search returns ALL img elements though).
		$images = $this->get_images_from_html( preg_replace( '/<noscript.*?\/noscript>/s', '', $buffer ), false, false );
		if ( ! empty( $images[0] ) && $this->is_iterable( $images[0] ) ) {
			ewwwio_debug_message( 'parsing images without requiring src' );
			foreach ( $images[0] as $index => $image ) {
				if ( $this->get_attribute( $image, 'src' ) ) {
					continue;
				}
				if ( ! $this->validate_tag( $image ) ) {
					continue;
				}
				ewwwio_debug_message( 'found img without src' );
				if ( strpos( $image, 'data-src=' ) && strpos( $image, 'data-srcset=' ) && strpos( $image, 'lazyload' ) ) {
					// EWWW IO Lazy Load.
					$new_image = $image;
					$real_file = $this->get_attribute( $new_image, 'data-src' );
					ewwwio_debug_message( "checking webp for Lazy Load data-src: $real_file" );
					if ( $this->validate_image_url( $real_file ) ) {
						ewwwio_debug_message( 'found webp for Lazy Load' );
						$this->set_attribute( $new_image, 'data-src-webp', $this->generate_url( $real_file ) );
					}
					$srcset = $this->get_attribute( $new_image, 'data-srcset' );
					if ( $srcset ) {
						$srcset_webp = $this->srcset_replace( $srcset );
						if ( $srcset_webp ) {
							$this->set_attribute( $new_image, 'data-srcset-webp', $srcset_webp );
						}
					}
					if ( $new_image !== $image ) {
						$this->set_attribute( $new_image, 'class', $this->get_attribute( $new_image, 'class' ) . ' ewww_webp_lazy_load', true );
						$buffer = str_replace( $image, $new_image, $buffer );
					}
				}
			} // End foreach().
		} // End if().
		// Look for images to parse WP Retina Lazy Load.
		if ( class_exists( 'Meow_WR2X_Core' ) && strpos( $buffer, ' lazyload' ) ) {
			$images = $this->get_elements_from_html( $buffer, 'img' );
			if ( ewww_image_optimizer_iterable( $images ) ) {
				foreach ( $images as $index => $image ) {
					if ( ! $this->validate_tag( $image ) ) {
						continue;
					}
					$file = $this->get_attribute( $image, 'src' );
					if ( ( empty( $file ) || strpos( $image, 'R0lGODlhAQABAIAAAAAAAP' ) ) && strpos( $image, ' data-srcset=' ) && strpos( $this->get_attribute( $image, 'class' ), 'lazyload' ) ) {
						$new_image = $image;
						$srcset    = $this->get_attribute( $new_image, 'data-srcset' );
						ewwwio_debug_message( 'checking webp for Retina Lazy Load data-src' );
						if ( $srcset ) {
							$srcset_webp = $this->srcset_replace( $srcset );
							if ( $srcset_webp ) {
								$this->set_attribute( $new_image, 'data-srcset-webp', $srcset_webp );
							}
						}
						if ( $new_image !== $image ) {
							$this->set_attribute( $new_image, 'class', $this->get_attribute( $new_image, 'class' ) . ' ewww_webp_lazy_load', true );
							$buffer = str_replace( $image, $new_image, $buffer );
						}
					}
				}
			}
		}
		// Images listed as picture/source elements. Mostly for NextGEN, but should work anywhere.
		$pictures = $this->get_picture_tags_from_html( $buffer );
		if ( ewww_image_optimizer_iterable( $pictures ) ) {
			foreach ( $pictures as $index => $picture ) {
				if ( strpos( $picture, 'image/webp' ) ) {
					continue;
				}
				if ( ! $this->validate_tag( $picture ) ) {
					continue;
				}
				$sources = $this->get_elements_from_html( $picture, 'source' );
				if ( ewww_image_optimizer_iterable( $sources ) ) {
					foreach ( $sources as $source ) {
						ewwwio_debug_message( "parsing a picture source: $source" );
						$srcset_attr_name = 'srcset';
						if ( false !== strpos( $source, 'base64,R0lGOD' ) && false !== strpos( $source, 'data-srcset=' ) ) {
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
						ewwwio_debug_message( 'found webp for picture element' );
						$buffer = str_replace( $pictures[ $index ], $picture, $buffer );
					}
				}
			}
		}
		// NextGEN slides listed as 'a' elements and LL 'a' background images.
		$links = $this->get_elements_from_html( $buffer, 'a' );
		if ( ewww_image_optimizer_iterable( $links ) ) {
			foreach ( $links as $index => $link ) {
				ewwwio_debug_message( "parsing a link $link" );
				if ( ! $this->validate_tag( $link ) ) {
					continue;
				}
				$file  = $this->get_attribute( $link, 'data-src' );
				$thumb = $this->get_attribute( $link, 'data-thumbnail' );
				if ( $file && $thumb ) {
					ewwwio_debug_message( "checking webp for ngg data-src: $file" );
					if ( $this->validate_image_url( $file ) ) {
						$this->set_attribute( $link, 'data-webp', $this->generate_url( $file ) );
						ewwwio_debug_message( "found webp for ngg data-src: $file" );
					}
					ewwwio_debug_message( "checking webp for ngg data-thumbnail: $thumb" );
					if ( $this->validate_image_url( $thumb ) ) {
						$this->set_attribute( $link, 'data-webp-thumbnail', $this->generate_url( $thumb ) );
						ewwwio_debug_message( "found webp for ngg data-thumbnail: $thumb" );
					}
				}
				$bg_image   = $this->get_attribute( $link, 'data-bg' );
				$link_class = $this->get_attribute( $link, 'class' );
				if ( $link_class && $bg_image && false !== strpos( $link_class, 'lazyload' ) ) {
					ewwwio_debug_message( "checking a/link for LL data-bg: $bg_image" );
					if ( $this->validate_image_url( $bg_image ) ) {
						$this->set_attribute( $link, 'data-bg-webp', $this->generate_url( $bg_image ) );
						ewwwio_debug_message( 'found webp for LL data-bg' );
					}
				}
				if ( $link !== $links[ $index ] ) {
					$buffer = str_replace( $links[ $index ], $link, $buffer );
				}
			}
		}
		// Revolution Slider 'li' elements and LL li backgrounds.
		$listitems = $this->get_elements_from_html( $buffer, 'li' );
		if ( ewww_image_optimizer_iterable( $listitems ) ) {
			foreach ( $listitems as $index => $listitem ) {
				ewwwio_debug_message( 'parsing a listitem' );
				if ( ! $this->validate_tag( $listitem ) ) {
					continue;
				}
				if ( $this->get_attribute( $listitem, 'data-title' ) === 'Slide' && ( $this->get_attribute( $listitem, 'data-lazyload' ) || $this->get_attribute( $listitem, 'data-thumb' ) ) ) {
					$thumb = $this->get_attribute( $listitem, 'data-thumb' );
					ewwwio_debug_message( "checking webp for revslider data-thumb: $thumb" );
					if ( $this->validate_image_url( $thumb ) ) {
						$this->set_attribute( $listitem, 'data-webp-thumb', $this->generate_url( $thumb ) );
						ewwwio_debug_message( "found webp for revslider data-thumb: $thumb" );
					}
					$param_num = 1;
					while ( $param_num < 11 ) {
						$parameter = $this->get_attribute( $listitem, 'data-param' . $param_num );
						if ( $parameter ) {
							ewwwio_debug_message( "checking webp for revslider data-param$param_num: $parameter" );
							if ( strpos( $parameter, 'http' ) === 0 ) {
								ewwwio_debug_message( "looking for $parameter" );
								if ( $this->validate_image_url( $parameter ) ) {
									$this->set_attribute( $listitem, 'data-webp-param' . $param_num, $this->generate_url( $parameter ) );
									ewwwio_debug_message( "found webp for data-param$param_num: $parameter" );
								}
							}
						}
						$param_num++;
					}
					if ( $listitem !== $listitems[ $index ] ) {
						$buffer = str_replace( $listitems[ $index ], $listitem, $buffer );
					}
				}
				$bg_image = $this->get_attribute( $listitem, 'data-bg' );
				$li_class = $this->get_attribute( $listitem, 'class' );
				if ( $li_class && $bg_image && false !== strpos( $li_class, 'lazyload' ) ) {
					ewwwio_debug_message( "checking div for LL data-bg: $bg_image" );
					if ( $this->validate_image_url( $bg_image ) ) {
						$this->set_attribute( $listitem, 'data-bg-webp', $this->generate_url( $bg_image ) );
						ewwwio_debug_message( 'found webp for LL data-bg' );
						$buffer = str_replace( $listitems[ $index ], $listitem, $buffer );
					}
				}
			} // End foreach().
		} // End if().
		// WooCommerce thumbs listed as 'div' elements and LL div backgrounds.
		$divs = $this->get_elements_from_html( $buffer, 'div' );
		if ( ewww_image_optimizer_iterable( $divs ) ) {
			foreach ( $divs as $index => $div ) {
				ewwwio_debug_message( 'parsing a div' );
				if ( ! $this->validate_tag( $div ) ) {
					continue;
				}
				$thumb     = $this->get_attribute( $div, 'data-thumb' );
				$div_class = $this->get_attribute( $div, 'class' );
				if ( $div_class && $thumb && strpos( $div_class, 'woocommerce-product-gallery__image' ) !== false ) {
					ewwwio_debug_message( "checking webp for WC data-thumb: $thumb" );
					if ( $this->validate_image_url( $thumb ) ) {
						$this->set_attribute( $div, 'data-webp-thumb', $this->generate_url( $thumb ) );
						ewwwio_debug_message( 'found webp for WC data-thumb' );
						$buffer = str_replace( $divs[ $index ], $div, $buffer );
					}
				}
				$bg_image = $this->get_attribute( $div, 'data-bg' );
				if ( $div_class && $bg_image && false !== strpos( $div_class, 'lazyload' ) ) {
					ewwwio_debug_message( "checking div for LL data-bg: $bg_image" );
					if ( $this->validate_image_url( $bg_image ) ) {
						$this->set_attribute( $div, 'data-bg-webp', $this->generate_url( $bg_image ) );
						ewwwio_debug_message( 'found webp for LL data-bg' );
						$buffer = str_replace( $divs[ $index ], $div, $buffer );
					}
				}
			}
		}
		// Look for LL 'section' elements.
		$sections = $this->get_elements_from_html( $buffer, 'section' );
		if ( ewww_image_optimizer_iterable( $sections ) ) {
			foreach ( $sections as $index => $section ) {
				ewwwio_debug_message( 'parsing a section' );
				if ( ! $this->validate_tag( $section ) ) {
					continue;
				}
				$class    = $this->get_attribute( $section, 'class' );
				$bg_image = $this->get_attribute( $section, 'data-bg' );
				if ( $class && $bg_image && false !== strpos( $class, 'lazyload' ) ) {
					ewwwio_debug_message( "checking section for LL data-bg: $bg_image" );
					if ( $this->validate_image_url( $bg_image ) ) {
						$this->set_attribute( $section, 'data-bg-webp', $this->generate_url( $bg_image ) );
						ewwwio_debug_message( 'found webp for LL data-bg' );
						$buffer = str_replace( $sections[ $index ], $section, $buffer );
					}
				}
			}
		}
		// Look for LL 'span' elements.
		$spans = $this->get_elements_from_html( $buffer, 'span' );
		if ( ewww_image_optimizer_iterable( $spans ) ) {
			foreach ( $spans as $index => $span ) {
				ewwwio_debug_message( 'parsing a span' );
				if ( ! $this->validate_tag( $span ) ) {
					continue;
				}
				$class    = $this->get_attribute( $span, 'class' );
				$bg_image = $this->get_attribute( $span, 'data-bg' );
				if ( $class && $bg_image && false !== strpos( $class, 'lazyload' ) ) {
					ewwwio_debug_message( "checking span for LL data-bg: $bg_image" );
					if ( $this->validate_image_url( $bg_image ) ) {
						$this->set_attribute( $span, 'data-bg-webp', $this->generate_url( $bg_image ) );
						ewwwio_debug_message( 'found webp for LL data-bg' );
						$buffer = str_replace( $spans[ $index ], $span, $buffer );
					}
				}
			}
		}
		// Video elements, looking for poster attributes that are images.
		$videos = $this->get_elements_from_html( $buffer, 'video' );
		if ( ewww_image_optimizer_iterable( $videos ) ) {
			foreach ( $videos as $index => $video ) {
				ewwwio_debug_message( 'parsing a video element' );
				if ( ! $this->validate_tag( $video ) ) {
					continue;
				}
				$file = $this->get_attribute( $video, 'poster' );
				if ( $file ) {
					ewwwio_debug_message( "checking webp for video poster: $file" );
					if ( $this->validate_image_url( $file ) ) {
						$this->set_attribute( $video, 'data-poster-webp', $this->generate_url( $file ) );
						$this->set_attribute( $video, 'data-poster-image', $file );
						$this->remove_attribute( $video, 'poster' );
						ewwwio_debug_message( "found webp for video poster: $file" );
						$buffer = str_replace( $videos[ $index ], $video, $buffer );
					}
				}
			}
		}
		ewwwio_debug_message( 'all done parsing page for alt webp' );
		return $buffer;
	}

	/**
	 * Handle image urls within the NextGEN pro lightbox displays.
	 *
	 * @param array $images An array of NextGEN images and associate attributes.
	 * @return array The array of images with WebP versions added.
	 */
	function ngg_pro_lightbox_images_queue( $images ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ewww_image_optimizer_iterable( $images ) ) {
			foreach ( $images as $index => $image ) {
				if ( ! empty( $image['image'] ) && $this->validate_image_url( $image['image'] ) ) {
					$images[ $index ]['image-webp'] = $this->generate_url( $image['image'] );
				}
				if ( ! empty( $image['thumb'] ) && $this->validate_image_url( $image['thumb'] ) ) {
					$images[ $index ]['thumb-webp'] = $this->generate_url( $image['thumb'] );
				}
				if ( ! empty( $image['full_image'] ) && $this->validate_image_url( $image['full_image'] ) ) {
					$images[ $index ]['full_image_webp'] = $this->generate_url( $image['full_image'] );
				}
				if ( ewww_image_optimizer_iterable( $image['srcsets'] ) ) {
					foreach ( $image['srcsets'] as $size => $srcset ) {
						if ( $this->validate_image_url( $srcset ) ) {
							$images[ $index ]['srcsets'][ $size . '-webp' ] = $this->generate_url( $srcset );
						}
					}
				}
				if ( ewww_image_optimizer_iterable( $image['full_srcsets'] ) ) {
					foreach ( $image['full_srcsets'] as $size => $srcset ) {
						if ( $this->validate_image_url( $srcset ) ) {
							$images[ $index ]['full_srcsets'][ $size . '-webp' ] = $this->generate_url( $srcset );
						}
					}
				}
			}
		}
		return $images;
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
			ewwwio_debug_message( "looking for domain $webp_domain in $url" );
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
			ewwwio_debug_message( "looking for path $webp_path in $url" );
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
						$this->user_element_exclusions[] = $exclusion;
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
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Ignore 0-size Pinterest schema images.
		if ( strpos( $image, 'data-pin-description=' ) && strpos( $image, 'width="0" height="0"' ) ) {
			$this->debug_message( 'data-pin-description img skipped' );
			return false;
		}

		$test_tag = ltrim( substr( $image, 0, 10 ), '<' );
		foreach ( $this->user_element_exclusions as $element_exclusion ) {
			if ( 0 === strpos( $test_tag, $element_exclusion ) ) {
				$this->debug_message( "$element_exclusion tag skipped" );
				return;
			}
		}

		$exclusions = apply_filters(
			'ewwwio_js_webp_exclusions',
			array_merge(
				array(
					'timthumb.php?',
					'wpcf7_captcha/',
				),
				$this->user_exclusions
			),
			$image
		);
		foreach ( $exclusions as $exclusion ) {
			if ( false !== strpos( $image, $exclusion ) ) {
				$this->debug_message( "tag matched $exclusion" );
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
		ewwwio_debug_message( "webp validation for $image" );
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
	 * Adds .webp to the end, or adds a webp parameter for ExactDN urls.
	 *
	 * @param string $url The image url.
	 * @return string The WebP version of the image url.
	 */
	function generate_url( $url ) {
		if ( $this->parsing_exactdn && false !== strpos( $url, $this->exactdn_domain ) ) {
			return add_query_arg( 'webp', 1, $url );
		} else {
			$path_parts = explode( '?', $url );
			return $path_parts[0] . '.webp' . ( ! empty( $path_parts[1] ) && 'is-pending-load=1' !== $path_parts[1] ? '?' . $path_parts[1] : '' );
		}
		return $url;
	}

	/**
	 * Load full webp script when SCRIPT_DEBUG is enabled.
	 */
	function debug_script() {
		if ( $this->is_amp() ) {
			return;
		}
		if ( ! ewww_image_optimizer_ce_webp_enabled() ) {
			wp_enqueue_script( 'ewww-webp-load-script', plugins_url( '/includes/load_webp.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
		}
	}

	/**
	 * Load minified webp script when EWWW_IMAGE_OPTIMIZER_WEBP_EXTERNAL_SCRIPT is set.
	 */
	function min_external_script() {
		if ( $this->is_amp() ) {
			return;
		}
		if ( ! ewww_image_optimizer_ce_webp_enabled() ) {
			wp_enqueue_script( 'ewww-webp-load-script', plugins_url( '/includes/load_webp.min.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
		}
	}

	/**
	 * Load minified (jscompress.com) inline version of webp script.
	 */
	function inline_script() {
		if ( defined( 'EWWW_IMAGE_OPTIMIZER_NO_JS' ) && EWWW_IMAGE_OPTIMIZER_NO_JS ) {
			return;
		}
		if ( $this->is_amp() ) {
			return;
		}
		ewwwio_debug_message( 'loading webp script without wp_add_inline_script' );
		echo '<script data-cfasync="false" type="text/javascript">' . $this->inline_script . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

global $eio_alt_webp;
$eio_alt_webp = new EIO_Alt_Webp();
