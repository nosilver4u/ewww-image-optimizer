<?php
/**
 * Implements Lazy Loading using page parsing and JS functionality.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enables EWWW IO to filter the page content and replace img elements with Lazy Load markup.
 */
class EWWWIO_Lazy_Load extends EWWWIO_Page_Parser {

	/**
	 * Base64 encoded placeholder image.
	 *
	 * @access protected
	 * @var string $placeholder_src
	 */
	protected $placeholder_src = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';

	/**
	 * Indicates if we are filtering ExactDN urls.
	 *
	 * @access protected
	 * @var bool $parsing_exactdn
	 */
	protected $parsing_exactdn = false;

	/**
	 * Register (once) actions and filters for Lazy Load.
	 */
	function __construct() {
		ewwwio_debug_message( 'firing up lazy load' );
		global $ewwwio_lazy_load;
		if ( is_object( $ewwwio_lazy_load ) ) {
			ewwwio_debug_message( 'you are doing it wrong' );
			return 'you are doing it wrong';
		}
		// Start an output buffer before any output starts.
		/* add_action( 'template_redirect', array( $this, 'buffer_start' ), 1 ); */
		add_filter( 'ewww_image_optimizer_filter_page_output', array( $this, 'filter_page_output' ), 15 );

		if ( class_exists( 'ExactDN' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
			global $exactdn;
			$this->exactdn_domain = $exactdn->get_exactdn_domain();
			if ( $this->exactdn_domain ) {
				$this->parsing_exactdn = true;
				ewwwio_debug_message( 'parsing an exactdn page' );
			}
		}

		// Filter early, so that others at the default priority take precendence.
		add_filter( 'ewww_image_optimizer_use_lqip', array( $this, 'maybe_lqip' ), 9 );

		// Load the appropriate JS.
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			// Load the non-minified and separate versions of the lazy load scripts.
			add_action( 'wp_enqueue_scripts', array( $this, 'debug_script' ) );
		} else {
			// Load the minified, combined version of the lazy load script.
			add_action( 'wp_enqueue_scripts', array( $this, 'min_script' ) );
		}
	}


	/**
	 * Starts an output buffer and registers the callback function to do WebP replacement.
	 */
	function buffer_start() {
		ob_start( array( $this, 'filter_page_output' ) );
	}

	/**
	 * Replaces images within a srcset attribute, just a placeholder at the moment.
	 *
	 * @param string $srcset A valid srcset attribute from an img element.
	 * @return bool|string False if no changes were made, or the new srcset if any WebP images replaced the originals.
	 */
	function srcset_replace( $srcset ) {
		return $srcset;
	}

	/**
	 * Search for img elements and rewrite them for Lazy Load with fallback to noscript elements.
	 *
	 * @param string $buffer The full HTML page generated since the output buffer was started.
	 * @return string The altered buffer containing the full page with Lazy Load attributes.
	 */
	function filter_page_output( $buffer ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Don't lazy load in these cases...
		$uri = $_SERVER['REQUEST_URI'];
		if (
			empty( $buffer ) ||
			is_admin() ||
			! empty( $_GET['cornerstone'] ) ||
			strpos( $uri, 'cornerstone-endpoint' ) !== false ||
			! empty( $_GET['et_fb'] ) ||
			! empty( $_GET['tatsu'] ) ||
			( ! empty( $_POST['action'] ) && 'tatsu_get_concepts' === $_POST['action'] ) ||
			! apply_filters( 'ewww_image_optimizer_do_lazyload', true ) ||
			is_feed() ||
			is_preview() ||
			( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
			wp_script_is( 'twentytwenty-twentytwenty', 'enqueued' ) ||
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
			if ( ! apply_filters( 'ewww_image_optimizer_do_lazyload', true ) ) {
				ewwwio_debug_message( 'do_lazyload short-circuit' );
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
			if ( wp_script_is( 'twentytwenty-twentytwenty', 'enqueued' ) ) {
				ewwwio_debug_message( 'twentytwenty enqueued' );
			}
			if ( preg_match( '/<\?xml/', $buffer ) ) {
				ewwwio_debug_message( 'not html, xml tag found' );
			}
			if ( strpos( $buffer, 'amp-boilerplate' ) ) {
				ewwwio_debug_message( 'AMP page processing' );
			}
			return $buffer;
		}

		$above_the_fold   = apply_filters( 'ewww_image_optimizer_lazy_fold', 0 );
		$images_processed = 0;

		$images = $this->get_images_from_html( preg_replace( '/<noscript.*?\/noscript>/', '', $buffer ), false );
		if ( ewww_image_optimizer_iterable( $images[0] ) ) {
			foreach ( $images[0] as $index => $image ) {
				$images_processed++;
				if ( $images_processed <= $above_the_fold ) {
					continue;
				}
				$file = $images['img_url'][ $index ];
				ewwwio_debug_message( "parsing an image: $file" );
				if ( $this->validate_image_tag( $image ) ) {
					ewwwio_debug_message( 'found a valid image tag' );
					$orig_img = $image;
					$noscript = '<noscript>' . $orig_img . '</noscript>';
					$this->set_attribute( $image, 'data-src', $file, true );
					$srcset = $this->get_attribute( $image, 'srcset' );

					$placeholder_src = $this->placeholder_src;
					if ( false === strpos( $file, 'nggid' ) && apply_filters( 'ewww_image_optimizer_use_lqip', true ) && $this->parsing_exactdn && strpos( $file, $this->exactdn_domain ) ) {
						$placeholder_src = add_query_arg( array( 'lazy' => 1 ), $file );
						ewwwio_debug_message( "current placeholder is $placeholder_src" );
					}

					if ( $srcset ) {
						if ( strpos( $placeholder_src, '64,R0lGOD' ) ) {
							$this->set_attribute( $image, 'srcset', $placeholder_src, true );
							$this->remove_attribute( $image, 'src' );
						} else {
							$this->set_attribute( $image, 'src', $placeholder_src, true );
							$this->remove_attribute( $image, 'srcset' );
						}
						$this->set_attribute( $image, 'data-srcset', $srcset, true );
						$srcset_sizes = $this->get_attribute( $image, 'sizes' );
						// Return false on this filter to disable automatic sizes calculation,
						// or use the sizes value passed via the filter to conditionally disable it.
						if ( apply_filters( 'ewww_image_optimizer_lazy_responsive', $srcset_sizes ) ) {
							$this->set_attribute( $image, 'data-sizes', 'auto', true );
							$this->remove_attribute( $image, 'sizes' );
						}
					} else {
						$this->set_attribute( $image, 'src', $placeholder_src, true );
					}
					$this->set_attribute( $image, 'class', $this->get_attribute( $image, 'class' ) . ' lazyload', true );
					$buffer = str_replace( $orig_img, $image . $noscript, $buffer );
				}
			} // End foreach().
		} // End if().
		// Process background images on 'div' elements.
		$divs = $this->get_elements_from_html( $buffer, 'div' );
		if ( ewww_image_optimizer_iterable( $divs ) ) {
			$lazy_class = 'lazyload';
			foreach ( $divs as $index => $div ) {
				ewwwio_debug_message( 'parsing a div' );
				if ( false === strpos( $div, 'background:' ) && false === strpos( $div, 'background-image:' ) ) {
					continue;
				}
				if ( false !== strpos( $div, $lazy_class ) ) {
					continue;
				}
				if ( ! $this->validate_bgimage_tag( $div ) ) {
					continue;
				}
				$style = $this->get_attribute( $div, 'style' );
				if ( empty( $style ) ) {
					continue;
				}
				ewwwio_debug_message( "checking style attr for background-image: $style" );
				$bg_image_url = $this->get_background_image_url( $style );
				if ( $bg_image_url ) {
					$this->set_attribute( $div, 'class', $this->get_attribute( $div, 'class' ) . " $lazy_class", true );
					$this->set_attribute( $div, 'data-bg', $bg_image_url );
					$new_style = $this->remove_background_image( $style );
					if ( $style !== $new_style ) {
						$div = str_replace( $style, $new_style, $div );
					}
				}
				if ( $div !== $divs[ $index ] ) {
					$buffer = str_replace( $divs[ $index ], $div, $buffer );
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
						if ( false !== strpos( $source, 'data-src' ) ) {
							continue;
						}
						ewwwio_debug_message( "parsing a picture source: $source" );
						$srcset = $this->get_attribute( $source, 'srcset' );
						if ( $srcset ) {
							ewwwio_debug_message( 'found srcset in source' );
							$lazy_source = $source;
							$this->set_attribute( $lazy_source, 'data-srcset', $srcset );
							$this->set_attribute( $lazy_source, 'srcset', $this->placeholder_src, true );
							$picture = str_replace( $source, $lazy_source, $picture );
						}
					}
					if ( $picture != $pictures[ $index ] ) {
						ewwwio_debug_message( 'lazified sources for picture element' );
						$buffer = str_replace( $pictures[ $index ], $picture, $buffer );
					}
				}
			}
		}
		// Video elements, looking for poster attributes that are images.
		/* $videos = $this->get_elements_from_html( $buffer, 'video' ); */
		$videos = '';
		if ( ewww_image_optimizer_iterable( $videos ) ) {
			foreach ( $videos as $index => $video ) {
				ewwwio_debug_message( 'parsing a video element' );
				$file = $this->get_attribute( $video, 'poster' );
				if ( $file ) {
					ewwwio_debug_message( "checking webp for video poster: $file" );
					if ( $this->validate_image_tag( $file ) ) {
						$this->set_attribute( $video, 'data-poster-webp', $this->placeholder_src );
						$this->set_attribute( $video, 'data-poster-image', $file );
						$this->remove_attribute( $video, 'poster' );
						ewwwio_debug_message( "found webp for video poster: $file" );
						$buffer = str_replace( $videos[ $index ], $video, $buffer );
					}
				}
			}
		}
		ewwwio_debug_message( 'all done parsing page for lazy' );
		if ( true ) { // Set to true for extra logging.
			ewww_image_optimizer_debug_log();
		}
		return $buffer;
	}

	/**
	 * Checks if the tag is allowed to be lazy loaded.
	 *
	 * @param string $image The image (img) tag.
	 * @return bool True if the tag is allowed, false otherwise.
	 */
	function validate_image_tag( $image ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		if (
			strpos( $image, 'base64,R0lGOD' ) ||
			strpos( $image, 'lazy-load/images/1x1' ) ||
			strpos( $image, '/assets/images/' )
		) {
			ewwwio_debug_message( 'lazy load placeholder detected' );
			return false;
		}

		// Skip inline data URIs.
		if ( strpos( $image, "src='data:image" ) || strpos( $image, 'src="data:image' ) ) {
			return false;
		}
		// Ignore 0-size Pinterest schema images.
		if ( strpos( $image, 'data-pin-description=' ) && strpos( $image, 'width="0" height="0"' ) ) {
			return false;
		}
		$exclusions = apply_filters(
			'ewww_image_optimizer_lazy_exclusions',
			array(
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
				'ewww_webp_lazy_load',
				'fullurl=',
				'gazette-featured-content-thumbnail',
				'lazy-slider-img=',
				'skip-lazy',
				'timthumb.php?',
				'wpcf7_captcha/',
			),
			$image
		);
		foreach ( $exclusions as $exclusion ) {
			if ( false !== strpos( $image, $exclusion ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Checks if a tag with a background image is allowed to be lazy loaded.
	 *
	 * @param string $tag The tag.
	 * @return bool True if the tag is allowed, false otherwise.
	 */
	function validate_bgimage_tag( $tag ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		$exclusions = apply_filters(
			'ewww_image_optimizer_lazy_bg_image_exclusions',
			array(),
			$tag
		);
		foreach ( $exclusions as $exclusion ) {
			if ( false !== strpos( $image, $exclusion ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check if LQIP should be used, but allow filters to alter the option.
	 *
	 * @param bool $use_lqip Whether LL should use low-quality image placeholders.
	 * @return bool True to use LQIP, false to skip them.
	 */
	function maybe_lqip( $use_lqip ) {
		if ( defined( 'EWWW_IMAGE_OPTIMIZER_USE_LQIP' ) && ! EWWW_IMAGE_OPTIMIZER_USE_LQIP ) {
			return false;
		}
		return $use_lqip;
	}

	/**
	 * Load full lazysizes script when SCRIPT_DEBUG is enabled.
	 */
	function debug_script() {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		wp_enqueue_script( 'ewww-lazy-load-pre', plugins_url( '/includes/lazysizes-pre.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
		wp_enqueue_script( 'ewww-lazy-load', plugins_url( '/includes/lazysizes.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
		wp_enqueue_script( 'ewww-lazy-load-post', plugins_url( '/includes/lazysizes-post.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
		wp_enqueue_script( 'ewww-lazy-load-uvh', plugins_url( '/includes/ls.unveilhooks.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
		wp_localize_script(
			'ewww-lazy-load',
			'ewww_lazy_vars',
			array(
				'exactdn_domain' => ( $this->parsing_exactdn ? $this->exactdn_domain : '' ),
			)
		);
	}

	/**
	 * Load minified lazysizes script.
	 */
	function min_script() {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		wp_enqueue_script( 'ewww-lazy-load', plugins_url( '/includes/lazysizes.min.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
		wp_localize_script(
			'ewww-lazy-load',
			'ewww_lazy_vars',
			array(
				'exactdn_domain' => ( $this->parsing_exactdn ? $this->exactdn_domain : '' ),
			)
		);
	}
}

global $ewwwio_lazy_load;
$ewwwio_lazy_load = new EWWWIO_Lazy_Load();
