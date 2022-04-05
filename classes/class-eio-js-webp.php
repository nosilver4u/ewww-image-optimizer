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
class EIO_JS_Webp extends EIO_Page_Parser {

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
	 * Base64-encoded placeholder image.
	 *
	 * @access protected
	 * @var string $placeholder_src
	 */
	protected $placeholder_src = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';

	/**
	 * The 'check webp' script contents.
	 *
	 * @access private
	 * @var string $check_webp_script
	 */
	private $check_webp_script = '';

	/**
	 * The 'load webp' script contents.
	 *
	 * @access private
	 * @var string $load_webp_script
	 */
	private $load_webp_script = '';

	/**
	 * Request URI.
	 *
	 * @var string $request_uri
	 */
	public $request_uri = '';

	/**
	 * Register (once) actions and filters for JS WebP.
	 */
	function __construct() {
		global $eio_js_webp;
		if ( is_object( $eio_js_webp ) ) {
			return 'you are doing it wrong';
		}
		parent::__construct();
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );

		$this->request_uri = add_query_arg( null, null );
		if ( false === strpos( $this->request_uri, 'page=ewww-image-optimizer-options' ) ) {
			$this->debug_message( "request uri is {$this->request_uri}" );
		} else {
			$this->debug_message( 'request uri is EWWW IO settings' );
		}

		add_filter( 'eio_do_js_webp', array( $this, 'should_process_page' ), 10, 2 );

		/**
		 * Allow pre-empting JS WebP by page.
		 *
		 * @param bool Whether to parse the page for images to rewrite for WebP, default true.
		 * @param string The URI/path of the page.
		 */
		if ( ! apply_filters( 'eio_do_js_webp', true, $this->request_uri ) ) {
			return;
		}

		// Hook into the output buffer callback function.
		add_filter( 'ewww_image_optimizer_filter_page_output', array( $this, 'filter_page_output' ), 20 );
		// Filter for NextGEN image urls within JSON.
		add_filter( 'ngg_pro_lightbox_images_queue', array( $this, 'ngg_pro_lightbox_images_queue' ), 11 );
		// Filter for WooCommerce product variations (individual items).
		add_filter( 'woocommerce_available_variation', array( $this, 'woocommerce_available_variation' ) );
		// Filter for FacetWP JSON responses.
		add_filter( 'facetwp_render_output', array( $this, 'filter_facetwp_json_output' ) );
		// Filter for LL when multiple background images are used--because it uses JSON, and the background image parser skips elements containing JSON.
		add_filter( 'eio_ll_multiple_bg_images_for_webp', array( $this, 'filter_image_url_array' ) );

		// Load up the minified check script.
		$this->check_webp_script = file_get_contents( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'includes/check-webp.min.js' );
		// Load up the minified script so we can inline it.
		$this->load_webp_script = file_get_contents( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'includes/load-webp.min.js' );

		$allowed_urls = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' );
		if ( $this->is_iterable( $allowed_urls ) ) {
			$this->allowed_urls = array_merge( $this->allowed_urls, $allowed_urls );
		}

		$this->get_allowed_domains();

		$this->allowed_urls    = apply_filters( 'webp_allowed_urls', $this->allowed_urls );
		$this->allowed_domains = apply_filters( 'webp_allowed_domains', $this->allowed_domains );
		$this->debug_message( 'checking any images matching these URLs/patterns for webp: ' . implode( ',', $this->allowed_urls ) );
		$this->debug_message( 'rewriting any images matching these domains to webp: ' . implode( ',', $this->allowed_domains ) );

		// Load the appropriate JS, in the footer, but as early as possible.
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			// Load the non-minified, non-inline version of the webp rewrite script.
			add_action( 'wp_enqueue_scripts', array( $this, 'debug_script' ), -99 );
		} elseif ( defined( 'EWWW_IMAGE_OPTIMIZER_WEBP_EXTERNAL_SCRIPT' ) && EWWW_IMAGE_OPTIMIZER_WEBP_EXTERNAL_SCRIPT ) {
			// Load the minified, non-inline version of the webp rewrite script.
			add_action( 'wp_enqueue_scripts', array( $this, 'min_external_script' ), -99 );
		} else {
			add_action( 'wp_head', array( $this, 'inline_check_script' ), -99 );
			if ( defined( 'EWWW_IMAGE_OPTIMIZER_WEBP_FOOTER_SCRIPT' ) && EWWW_IMAGE_OPTIMIZER_WEBP_FOOTER_SCRIPT ) {
				add_action( 'wp_footer', array( $this, 'inline_load_script' ), -99 );
			} else {
				add_action( 'wp_head', array( $this, 'inline_load_script' ), -90 );
			}
		}
		$this->validate_user_exclusions();
	}

	/**
	 * Check if pages should be processed, especially for things like page builders.
	 *
	 * @since 6.2.2
	 *
	 * @param boolean $should_process Whether JS WebP should process the page.
	 * @param string  $uri The URI of the page (no domain or scheme included).
	 * @return boolean True to process the page, false to skip.
	 */
	function should_process_page( $should_process = true, $uri = '' ) {
		// Don't foul up the admin side of things, unless a plugin needs to.
		if ( is_admin() &&
			/**
			 * Provide plugins a way of running JS WebP for images in the WordPress Admin, usually for admin-ajax.php.
			 *
			 * @param bool false Allow JS WebP to run on the Dashboard. Defaults to false.
			 */
			false === apply_filters( 'eio_allow_admin_js_webp', false )
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
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
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
				$this->debug_message( "looking for $srcurl from srcset" );
				if ( $this->validate_image_url( $srcurl ) ) {
					$srcset = str_replace( $srcurl . $trailing, $this->generate_url( $srcurl ) . $trailing, $srcset );
					$this->debug_message( "replaced $srcurl in srcset" );
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
	 * Replaces images within the Jetpack data attributes with their .webp derivatives.
	 *
	 * @param string $image The full text of the img element.
	 * @return string The modified noscript tag.
	 */
	function jetpack_replace( $image ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$data_orig_file = $this->get_attribute( $image, 'data-orig-file' );
		if ( $data_orig_file ) {
			$this->debug_message( "looking for data-orig-file: $data_orig_file" );
			if ( $this->validate_image_url( $data_orig_file ) ) {
				$this->set_attribute( $image, 'data-webp-orig-file', $this->generate_url( $data_orig_file ), true );
				$this->debug_message( "replacing $data_orig_file via data-webp-orig-file" );
			}
		}
		$data_medium_file = $this->get_attribute( $image, 'data-medium-file' );
		if ( $data_medium_file ) {
			$this->debug_message( "looking for data-medium-file: $data_medium_file" );
			if ( $this->validate_image_url( $data_medium_file ) ) {
				$this->set_attribute( $image, 'data-webp-medium-file', $this->generate_url( $data_medium_file ), true );
				$this->debug_message( "replacing $data_medium_file via data-webp-medium-file" );
			}
		}
		$data_large_file = $this->get_attribute( $image, 'data-large-file' );
		if ( $data_large_file ) {
			$this->debug_message( "looking for data-large-file: $data_large_file" );
			if ( $this->validate_image_url( $data_large_file ) ) {
				$this->set_attribute( $image, 'data-webp-large-file', $this->generate_url( $data_large_file ), true );
				$this->debug_message( "replacing $data_large_file via data-webp-large-file" );
			}
		}
		return $image;
	}

	/**
	 * Replaces images with the WooCommerce data attributes with their .webp derivatives.
	 *
	 * @param string $image The full text of the img element.
	 * @return string The modified noscript tag.
	 */
	function woocommerce_replace( $image ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$data_large_image = $this->get_attribute( $image, 'data-large_image' );
		if ( $data_large_image ) {
			$this->debug_message( "looking for data-large_image: $data_large_image" );
			if ( $this->validate_image_url( $data_large_image ) ) {
				$this->set_attribute( $image, 'data-webp-large_image', $this->generate_url( $data_large_image ), true );
				$this->debug_message( "replacing $data_large_image via data-webp-large_image" );
			}
		}
		$data_src = $this->get_attribute( $image, 'data-src' );
		if ( $data_src ) {
			$this->debug_message( "looking for data-src: $data_src" );
			if ( $this->validate_image_url( $data_src ) ) {
				$this->set_attribute( $image, 'data-webp-src', $this->generate_url( $data_src ), true );
				ewwwio_debug_message( "replacing $data_src via data-webp-src" );
			}
		}
		return $image;
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
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if (
			empty( $buffer ) ||
			preg_match( '/^<\?xml/', $buffer ) ||
			strpos( $buffer, 'amp-boilerplate' )
		) {
			$this->debug_message( 'JS WebP disabled' );
			return $buffer;
		}
		if ( $this->is_json( $buffer ) ) {
			return $buffer;
		}
		if ( ! $this->should_process_page() ) {
			$this->debug_message( 'JS WebP should not process page' );
			return $buffer;
		}
		if ( ! apply_filters( 'eio_do_js_webp', true, $this->request_uri ) ) {
			return $buffer;
		}

		$body_tags        = $this->get_elements_from_html( $buffer, 'body' );
		$body_webp_script = '<script data-cfasync="false">if(ewww_webp_supported){document.body.classList.add("webp-support");}</script>';
		if ( $this->is_iterable( $body_tags ) && ! empty( $body_tags[0] ) && false !== strpos( $body_tags[0], '<body' ) ) {
			// Add the WebP script right after the opening tag.
			$buffer = str_replace( $body_tags[0], $body_tags[0] . "\n" . $body_webp_script, $buffer );
		} else {
			$buffer = str_replace( '<body>', "<body>\n$body_webp_script", $buffer );
		}
		$images = $this->get_images_from_html( preg_replace( '/<(picture|noscript).*?\/\1>/s', '', $buffer ), false );
		if ( ! empty( $images[0] ) && $this->is_iterable( $images[0] ) ) {
			foreach ( $images[0] as $index => $image ) {
				if ( false !== strpos( $image, 'ewww_webp' ) ) {
					continue;
				}
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
					$new_image = $this->jetpack_replace( $new_image );
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
				} elseif (
					$this->validate_image_url( $file ) &&
					( false === strpos( $image, 'lazyload' ) || false !== strpos( $image, 'lazyloaded' ) )
				) {
					// If a CDN path match was found, or .webp image existence is confirmed, and this is not a lazy-load 'dummy' image.
					$this->debug_message( 'found a webp image or forced path' );
					$new_image = $image;
					$this->set_attribute( $new_image, 'data-src-img', $file );
					$this->set_attribute( $new_image, 'data-src-webp', $this->generate_url( $file ) );
					$srcset = $this->get_attribute( $image, 'srcset' );
					if ( $srcset ) {
						$srcset_webp = $this->srcset_replace( $srcset );
						if ( $srcset_webp ) {
							$this->set_attribute( $new_image, 'data-srcset-webp', $srcset_webp );
						}
						$this->set_attribute( $new_image, 'data-srcset-img', $srcset );
						$this->remove_attribute( $new_image, 'srcset' );
					}
					if ( $this->get_attribute( $image, 'data-orig-file' ) && $this->get_attribute( $image, 'data-medium-file' ) && $this->get_attribute( $image, 'data-large-file' ) ) {
						$new_image = $this->jetpack_replace( $new_image );
					}
					if ( $this->get_attribute( $image, 'data-large_image' ) && $this->get_attribute( $image, 'data-src' ) ) {
						$new_image = $this->woocommerce_replace( $new_image );
					}
					$this->set_attribute( $new_image, 'src', $this->placeholder_src, true );
					if ( $new_image !== $image ) {
						$this->set_attribute( $new_image, 'data-eio', 'j', true );
						$this->set_attribute( $new_image, 'class', $this->get_attribute( $new_image, 'class' ) . ' ewww_webp', true );
						$this->debug_message( "going to swap\n$image\nwith\n$new_image" );
						$noscript = '<noscript>' . $image . '</noscript>';
						$buffer   = str_replace( $image, $new_image . $noscript, $buffer );
					}
				} elseif ( ! empty( $file ) && strpos( $image, ' data-lazy-src=' ) ) {
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
				} elseif ( ! empty( $file ) && strpos( $image, ' data-src=' ) && ( strpos( $image, ' data-lazy-type="image' ) || strpos( $image, 'lazyload' ) ) ) {
					// a3 or EWWW IO Lazy Load.
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
		$images = $this->get_images_from_html( preg_replace( '/<(picture|noscript).*?\/\1>/s', '', $buffer ), false, false );
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
			if ( $this->is_iterable( $images ) ) {
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
		// NextGEN slides listed as 'a' elements and LL 'a' background images.
		$links = $this->get_elements_from_html( $buffer, 'a' );
		if ( $this->is_iterable( $links ) ) {
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
		if ( $this->is_iterable( $listitems ) ) {
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
		if ( $this->is_iterable( $divs ) ) {
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
		if ( $this->is_iterable( $sections ) ) {
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
		if ( $this->is_iterable( $spans ) ) {
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
		if ( $this->is_iterable( $videos ) ) {
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
		$this->debug_message( 'all done parsing page for JS WebP' );
		return $buffer;
	}

	/**
	 * Handle image urls within the NextGEN pro lightbox displays.
	 *
	 * @param array $images An array of NextGEN images and associate attributes.
	 * @return array The array of images with WebP versions added.
	 */
	function ngg_pro_lightbox_images_queue( $images ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( $this->is_iterable( $images ) ) {
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
				if ( $this->is_iterable( $image['srcsets'] ) ) {
					foreach ( $image['srcsets'] as $size => $srcset ) {
						if ( $this->validate_image_url( $srcset ) ) {
							$images[ $index ]['srcsets'][ $size . '-webp' ] = $this->generate_url( $srcset );
						}
					}
				}
				if ( $this->is_iterable( $image['full_srcsets'] ) ) {
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
	 * Adds WebP URLs to the product variation data before it is JSON-encoded.
	 *
	 * @param array $variation The product variation with all associated data.
	 * @return array The product variation with WebP image URLs added.
	 */
	function woocommerce_available_variation( $variation ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( $this->is_iterable( $variation ) && $this->is_iterable( $variation['image'] ) ) {
			if ( ! empty( $variation['image']['src'] ) && $this->validate_image_url( $variation['image']['src'] ) ) {
				$variation['image']['src_webp'] = $this->generate_url( $variation['image']['src'] );
			}
			if ( ! empty( $variation['image']['full_src'] ) && $this->validate_image_url( $variation['image']['full_src'] ) ) {
				$variation['image']['full_src_webp'] = $this->generate_url( $variation['image']['full_src'] );
			}
			if ( ! empty( $variation['image']['gallery_thumbnail_src'] ) && $this->validate_image_url( $variation['image']['gallery_thumbnail_src'] ) ) {
				$variation['image']['gallery_thumbnail_src_webp'] = $this->generate_url( $variation['image']['gallery_thumbnail_src'] );
			}
			if ( ! empty( $variation['image']['thumb_src'] ) && $this->validate_image_url( $variation['image']['thumb_src'] ) ) {
				$variation['image']['thumb_src_webp'] = $this->generate_url( $variation['image']['thumb_src'] );
			}
			if ( ! empty( $variation['image']['srcset'] ) ) {
				$webp_srcset = $this->srcset_replace( $variation['image']['srcset'] );
				if ( $webp_srcset ) {
					$variation['image']['srcset_webp'] = $webp_srcset;
				}
			}
		}
		return $variation;
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
	 * Parse an array of image URLs and replace them with their WebP counterparts.
	 * Mostly for our Lazy Loader at this point, since it uses JSON when multiple
	 * background images are used on a single element.
	 *
	 * @param array $image_urls An array of image URLs.
	 * @return array An array with WebP image URLs.
	 */
	function filter_image_url_array( $image_urls ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( $this->is_iterable( $image_urls ) ) {
			foreach ( $image_urls as $index => $image_url ) {
				$this->debug_message( "checking $image_url for a WebP variant" );
				if ( ! empty( $image_url ) && $this->validate_image_url( $image_url ) ) {
					$image_urls[ $index ] = $this->generate_url( $image_url );
				}
			}
		}
		return $image_urls;
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
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
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
	 * Load full WebP script when SCRIPT_DEBUG is enabled.
	 */
	function debug_script() {
		if ( ! $this->should_process_page() ) {
			return;
		}
		if ( ! apply_filters( 'eio_do_js_webp', true, $this->request_uri ) ) {
			return;
		}
		if ( ! ewww_image_optimizer_ce_webp_enabled() ) {
			wp_enqueue_script( 'ewww-webp-check-script', plugins_url( '/includes/check-webp.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
			wp_enqueue_script( 'ewww-webp-load-script', plugins_url( '/includes/load-webp.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), EWWW_IMAGE_OPTIMIZER_VERSION, true );
		}
	}

	/**
	 * Load minified WebP script when EWWW_IMAGE_OPTIMIZER_WEBP_EXTERNAL_SCRIPT is set.
	 */
	function min_external_script() {
		if ( ! $this->should_process_page() ) {
			return;
		}
		if ( ! apply_filters( 'eio_do_js_webp', true, $this->request_uri ) ) {
			return;
		}
		if ( ! ewww_image_optimizer_ce_webp_enabled() ) {
			wp_enqueue_script( 'ewww-webp-check-script', plugins_url( '/includes/check-webp.min.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
			wp_enqueue_script( 'ewww-webp-load-script', plugins_url( '/includes/load-webp.min.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), EWWW_IMAGE_OPTIMIZER_VERSION, true );
		}
	}

	/**
	 * Load minified inline version of check WebP script.
	 */
	function inline_check_script() {
		if ( ! $this->should_process_page() ) {
			return;
		}
		if ( ! apply_filters( 'eio_do_js_webp', true, $this->request_uri ) ) {
			return;
		}
		if ( defined( 'EWWW_IMAGE_OPTIMIZER_NO_JS' ) && EWWW_IMAGE_OPTIMIZER_NO_JS ) {
			return;
		}
		$this->debug_message( 'inlining check webp script' );
		echo '<script data-cfasync="false" type="text/javascript">' . $this->check_webp_script . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Load minified inline version of load WebP script.
	 */
	function inline_load_script() {
		if ( defined( 'EWWW_IMAGE_OPTIMIZER_NO_JS' ) && EWWW_IMAGE_OPTIMIZER_NO_JS ) {
			return;
		}
		if ( ! $this->should_process_page() ) {
			return;
		}
		if ( ! apply_filters( 'eio_do_js_webp', true, $this->request_uri ) ) {
			return;
		}
		$this->debug_message( 'inlining load webp script' );
		echo '<script data-cfasync="false" type="text/javascript">' . $this->load_webp_script . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

global $eio_js_webp;
$eio_js_webp = new EIO_JS_Webp();
