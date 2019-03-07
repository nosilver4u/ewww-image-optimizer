<?php
/**
 * Implements WebP rewriting using page parsing and JS functionality.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enables EWWW IO to filter the page content and replace img elements with WebP markup.
 */
class EWWWIO_Alt_Webp extends EWWWIO_Page_Parser {

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
	 * Allowed paths for "forced" WebP.
	 *
	 * @access protected
	 * @var array $webp_paths
	 */
	protected $webp_paths = array();

	/**
	 * Register (once) actions and filters for Alt WebP.
	 */
	function __construct() {
		global $ewwwio_alt_webp;
		if ( is_object( $ewwwio_alt_webp ) ) {
			return 'you are doing it wrong';
		}
		if ( ewww_image_optimizer_ce_webp_enabled() ) {
			return false;
		}
		// Start an output buffer before any output starts.
		/* add_action( 'template_redirect', array( $this, 'buffer_start' ), 0 ); */
		add_filter( 'ewww_image_optimizer_filter_page_output', array( $this, 'filter_page_output' ), 20 );
		// Filter for NextGEN image urls within JS.
		add_filter( 'ngg_pro_lightbox_images_queue', array( $this, 'ngg_pro_lightbox_images_queue' ), 11 );

		// Load up the minified script so we can inline it.
		$this->inline_script = file_get_contents( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'includes/load_webp.min.js' );

		$this->home_url = trailingslashit( get_site_url() );
		ewwwio_debug_message( "home url: $this->home_url" );
		$this->relative_home_url = preg_replace( '/https?:/', '', $this->home_url );
		ewwwio_debug_message( "relative home url: $this->relative_home_url" );

		$this->webp_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' );
		if ( ! is_array( $this->webp_paths ) ) {
			$this->webp_paths = array();
		}
		ewwwio_debug_message( 'forcing any images matching these patterns to webp: ' . implode( ',', $this->webp_paths ) );
		if ( class_exists( 'ExactDN' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
			global $exactdn;
			$this->exactdn_domain = $exactdn->get_exactdn_domain();
			if ( $this->exactdn_domain ) {
				$this->parsing_exactdn = true;
				ewwwio_debug_message( 'parsing an exactdn page' );
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
			'align',
			'alt',
			'border',
			'crossorigin',
			'height',
			'hspace',
			'ismap',
			'longdesc',
			'usemap',
			'vspace',
			'width',
			'accesskey',
			'class',
			'contenteditable',
			'contextmenu',
			'dir',
			'draggable',
			'dropzone',
			'hidden',
			'id',
			'lang',
			'spellcheck',
			'style',
			'tabindex',
			'title',
			'translate',
			'sizes',
			'data-caption',
			'data-lazy-type',
			'data-attachment-id',
			'data-permalink',
			'data-orig-size',
			'data-comments-opened',
			'data-image-meta',
			'data-image-title',
			'data-image-description',
			'data-event-trigger',
			'data-highlight-color',
			'data-highlight-opacity',
			'data-highlight-border-color',
			'data-highlight-border-width',
			'data-highlight-border-opacity',
			'data-no-lazy',
			'data-lazy',
			'data-large_image_width',
			'data-large_image_height',
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
		$uri = $_SERVER['REQUEST_URI'];
		if (
			empty( $buffer ) ||
			is_admin() ||
			! empty( $_GET['cornerstone'] ) ||
			strpos( $uri, 'cornerstone-endpoint' ) !== false ||
			! empty( $_GET['et_fb'] ) ||
			! empty( $_GET['tatsu'] ) ||
			( ! empty( $_POST['action'] ) && 'tatsu_get_concepts' === $_POST['action'] ) ||
			is_feed() ||
			is_preview() ||
			( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
			preg_match( '/<\?xml/', $buffer )
		) {
			if ( empty( $buffer ) ) {
				ewwwio_debug_message( 'empty buffer' );
			}
			if ( is_admin() ) {
				ewwwio_debug_message( 'is_admin' );
			}
			if ( ! empty( $_GET['cornerstone'] ) || strpos( $uri, 'cornerstone-endpoint' ) !== false ) {
				ewwwio_debug_message( 'cornerstone editor' );
			}
			if ( ! empty( $_GET['et_fb'] ) ) {
				ewwwio_debug_message( 'et_fb' );
			}
			if ( ! empty( $_GET['tatsu'] ) || ( ! empty( $_POST['action'] ) && 'tatsu_get_concepts' === $_POST['action'] ) ) {
				ewwwio_debug_message( 'tatsu' );
			}
			if ( is_feed() ) {
				ewwwio_debug_message( 'is_feed' );
			}
			if ( is_preview() ) {
				ewwwio_debug_message( 'is_preview' );
			}
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				ewwwio_debug_message( 'rest request' );
			}
			if ( preg_match( '/<\?xml/', $buffer ) ) {
				ewwwio_debug_message( 'not html, xml tag found' );
			}
			if ( strpos( $buffer, 'amp-boilerplate' ) ) {
				ewwwio_debug_message( 'AMP page processing' );
			}
			if ( ewww_image_optimizer_ce_webp_enabled() ) {
				ewwwio_debug_message( 'Cache Enabler WebP enabled' );
			}
			return $buffer;
		}

		/* TODO: detect non-utf8 encoding and convert the buffer (if necessary). */

		$images = $this->get_images_from_html( preg_replace( '/<noscript.*?\/noscript>/', '', $buffer ), false );
		if ( ewww_image_optimizer_iterable( $images[0] ) ) {
			foreach ( $images[0] as $index => $image ) {
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
					ewwwio_debug_message( "going to swap\n$image\nwith\n$nscript" . $image . '</noscript>' );
					$buffer = str_replace( $image, $nscript . $image . '</noscript>', $buffer );
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
					// TODO: should we be using the class, or will that be moot point?
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
		$images = $this->get_images_from_html( preg_replace( '/<noscript.*?\/noscript>/', '', $buffer ), false, false );
		if ( ewww_image_optimizer_iterable( $images[0] ) ) {
			ewwwio_debug_message( 'parsing images without requiring src' );
			foreach ( $images[0] as $index => $image ) {
				if ( $this->get_attribute( $image, 'src' ) ) {
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
					if ( $picture != $pictures[ $index ] ) {
						ewwwio_debug_message( 'found webp for picture element' );
						$buffer = str_replace( $pictures[ $index ], $picture, $buffer );
					}
				}
			}
		}
		// NextGEN slides listed as 'a' elements.
		$links = $this->get_elements_from_html( $buffer, 'a' );
		if ( ewww_image_optimizer_iterable( $links ) ) {
			foreach ( $links as $index => $link ) {
				ewwwio_debug_message( "parsing a link $link" );
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
				if ( $link != $links[ $index ] ) {
					$buffer = str_replace( $links[ $index ], $link, $buffer );
				}
			}
		}
		// Revolution Slider 'li' elements.
		$listitems = $this->get_elements_from_html( $buffer, 'li' );
		if ( ewww_image_optimizer_iterable( $listitems ) ) {
			foreach ( $listitems as $index => $listitem ) {
				ewwwio_debug_message( 'parsing a listitem' );
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
					if ( $listitem != $listitems[ $index ] ) {
						$buffer = str_replace( $listitems[ $index ], $listitem, $buffer );
					}
				}
			} // End foreach().
		} // End if().
		// WooCommerce thumbs listed as 'div' elements.
		$divs = $this->get_elements_from_html( $buffer, 'div' );
		if ( ewww_image_optimizer_iterable( $divs ) ) {
			foreach ( $divs as $index => $div ) {
				ewwwio_debug_message( 'parsing a div' );
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
					ewwwio_debug_message( "checking webp for LL data-bg: $bg_image" );
					if ( $this->validate_image_url( $bg_image ) ) {
						$this->set_attribute( $div, 'data-bg-webp', $this->generate_url( $bg_image ) );
						ewwwio_debug_message( 'found webp for LL data-bg' );
						$buffer = str_replace( $divs[ $index ], $div, $buffer );
					}
				}
			}
		}
		// Video elements, looking for poster attributes that are images.
		$videos = $this->get_elements_from_html( $buffer, 'video' );
		if ( ewww_image_optimizer_iterable( $videos ) ) {
			foreach ( $videos as $index => $video ) {
				ewwwio_debug_message( 'parsing a video element' );
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
		if ( true ) { // Set to true for extra logging.
			ewww_image_optimizer_debug_log();
		}
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
	 * Checks if the path is a valid "forced" WebP image.
	 *
	 * @param string $image The image file.
	 * @return bool True if the file matches a forced path, false otherwise.
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
		if ( $this->parsing_exactdn && false !== strpos( $image, $this->exactdn_domain ) ) {
			ewwwio_debug_message( 'exactdn image' );
			return true;
		}
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) && $this->webp_paths ) {
			// Check the image for configured CDN paths.
			foreach ( $this->webp_paths as $webp_path ) {
				if ( strpos( $image, $webp_path ) !== false ) {
					ewwwio_debug_message( 'forced cdn image' );
					return true;
				}
			}
		}
		if ( 0 === strpos( $image, $this->relative_home_url ) ) {
			$imagepath = str_replace( $this->relative_home_url, ABSPATH, $image );
		} elseif ( 0 === strpos( $image, $this->home_url ) ) {
			$imagepath = str_replace( $this->home_url, ABSPATH, $image );
		} else {
			ewwwio_debug_message( 'not a valid local image' );
			return false;
		}
		$path_parts = explode( '?', $imagepath );
		if ( is_file( $path_parts[0] . '.webp' ) || is_file( $imagepath . '.webp' ) ) {
			ewwwio_debug_message( 'local .webp image found' );
			return true;
		}
		return false;
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
		if ( ! ewww_image_optimizer_ce_webp_enabled() ) {
			wp_enqueue_script( 'ewww-webp-load-script', plugins_url( '/includes/load_webp.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
		}
	}

	/**
	 * Load minified webp script when EWWW_IMAGE_OPTIMIZER_WEBP_EXTERNAL_SCRIPT is set.
	 */
	function min_external_script() {
		if ( ! ewww_image_optimizer_ce_webp_enabled() ) {
			wp_enqueue_script( 'ewww-webp-load-script', plugins_url( '/includes/load_webp.min.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
		}
	}

	/**
	 * Load minified inline version of webp script (from jscompress.com).
	 */
	function inline_script() {
		ewwwio_debug_message( 'loading webp script without wp_add_inline_script' );
		echo "<script>$this->inline_script</script>";
	}
}

global $ewwwio_alt_webp;
$ewwwio_alt_webp = new EWWWIO_Alt_Webp();
