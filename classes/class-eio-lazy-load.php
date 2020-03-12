<?php
/**
 * Implements Lazy Loading using page parsing and JS functionality.
 *
 * @link https://ewww.io
 * @package EIO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'EIO_Lazy_Load' ) ) {
	/**
	 * Enables plugin to filter the page content and replace img elements with Lazy Load markup.
	 */
	class EIO_Lazy_Load extends EIO_Page_Parser {

		/**
		 * A list of user-defined exclusions, populated by validate_user_exclusions().
		 *
		 * @access protected
		 * @var array $user_exclusions
		 */
		protected $user_exclusions = array();

		/**
		 * A list of user-defined element exclusions, populated by validate_user_exclusions().
		 *
		 * @access protected
		 * @var array $user_element_exclusions
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
		 * Indicates if we are filtering ExactDN urls.
		 *
		 * @access protected
		 * @var bool $parsing_exactdn
		 */
		protected $parsing_exactdn = false;

		/**
		 * The folder to store any PIIPs.
		 *
		 * @access protected
		 * @var string $piip_folder
		 */
		protected $piip_folder = '';

		/**
		 * Whether to allow PIIPs.
		 *
		 * @access public
		 * @var bool $allow_piip
		 */
		public $allow_piip = true;

		/**
		 * Register (once) actions and filters for Lazy Load.
		 */
		function __construct() {
			parent::__construct( __FILE__ );
			$this->piip_folder = $this->content_dir . 'lazy/';
			$this->debug_message( 'firing up lazy load' );
			global $eio_lazy_load;
			if ( is_object( $eio_lazy_load ) ) {
				$this->debug_message( 'you are doing it wrong' );
				return 'you are doing it wrong';
			}

			add_action( 'wp_head', array( $this, 'no_js_css' ) );

			add_filter( $this->prefix . 'filter_page_output', array( $this, 'filter_page_output' ), 15 );

			if ( class_exists( 'ExactDN' ) && $this->get_option( $this->prefix . 'exactdn' ) ) {
				global $exactdn;
				$this->exactdn_domain = $exactdn->get_exactdn_domain();
				if ( $this->exactdn_domain ) {
					$this->parsing_exactdn = true;
					$this->debug_message( 'parsing an exactdn page' );
				}
				$this->allow_lqip = false;
				if ( $exactdn->get_plan_id() > 1 ) {
					$this->allow_lqip = true;
				}
			}

			if ( $this->parsing_exactdn ) {
				$this->allow_piip = true;
			} elseif ( ! is_dir( $this->piip_folder ) ) {
				$this->allow_piip = wp_mkdir_p( $this->piip_folder ) && $this->gd_support();
			} else {
				$this->allow_piip = is_writable( $this->piip_folder ) && $this->gd_support();
			}

			// Filter early, so that others at the default priority take precendence.
			add_filter( 'eio_use_piip', array( $this, 'maybe_piip' ), 9 );
			add_filter( 'eio_use_siip', array( $this, 'maybe_siip' ), 9 );

			// Overrides for admin-ajax images.
			add_filter( 'eio_allow_admin_lazyload', array( $this, 'allow_admin_lazyload' ) );

			// Load the appropriate JS.
			if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
				// Load the non-minified and separate versions of the lazy load scripts.
				add_action( 'wp_enqueue_scripts', array( $this, 'debug_script' ) );
			} else {
				// Load the minified, combined version of the lazy load script.
				add_action( 'wp_enqueue_scripts', array( $this, 'min_script' ) );
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
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			// Don't foul up the admin side of things, unless a plugin needs to.
			if ( is_admin() &&
				/**
				 * Provide plugins a way of running Lazy Load for images in the WordPress Dashboard (wp-admin).
				 *
				 * @param bool false Allow Lazy Load to run on the Dashboard. Default to false.
				 */
				false === apply_filters( 'eio_allow_admin_lazyload', false )
			) {
				$this->debug_message( 'is_admin' );
				return $buffer;
			}
			// Don't lazy load in these cases...
			$uri = $_SERVER['REQUEST_URI'];
			if (
				empty( $buffer ) ||
				! empty( $_GET['cornerstone'] ) ||
				strpos( $uri, 'cornerstone-endpoint' ) !== false ||
				! empty( $_GET['ct_builder'] ) ||
				did_action( 'cornerstone_boot_app' ) || did_action( 'cs_before_preview_frame' ) ||
				! empty( $_GET['elementor-preview'] ) ||
				! empty( $_GET['et_fb'] ) ||
				! empty( $_GET['tatsu'] ) ||
				( ! empty( $_POST['action'] ) && 'tatsu_get_concepts' === $_POST['action'] ) ||
				! apply_filters( 'eio_do_lazyload', true ) ||
				is_feed() ||
				is_preview() ||
				( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
				wp_script_is( 'twentytwenty-twentytwenty', 'enqueued' ) ||
				preg_match( '/^<\?xml/', $buffer ) ||
				strpos( $buffer, 'amp-boilerplate' )
			) {
				if ( empty( $buffer ) ) {
					$this->debug_message( 'empty buffer' );
				}
				if ( ! empty( $_GET['cornerstone'] ) || strpos( $uri, 'cornerstone-endpoint' ) !== false ) {
					$this->debug_message( 'cornerstone editor' );
				}
				if ( ! empty( $_GET['ct_builder'] ) ) {
					$this->debug_message( 'oxygen builder' );
				}
				if ( did_action( 'cornerstone_boot_app' ) || did_action( 'cs_before_preview_frame' ) ) {
					$this->debug_message( 'cornerstone app/preview' );
				}
				if ( ! empty( $_GET['elementor-preview'] ) ) {
					$this->debug_message( 'elementor preview' );
				}
				if ( ! empty( $_GET['et_fb'] ) ) {
					$this->debug_message( 'et_fb' );
				}
				if ( ! empty( $_GET['tatsu'] ) || ( ! empty( $_POST['action'] ) && 'tatsu_get_concepts' === $_POST['action'] ) ) {
					$this->debug_message( 'tatsu' );
				}
				if ( ! apply_filters( 'eio_do_lazyload', true ) ) {
					$this->debug_message( 'do_lazyload short-circuit' );
				}
				if ( is_feed() ) {
					$this->debug_message( 'is_feed' );
				}
				if ( is_preview() ) {
					$this->debug_message( 'is_preview' );
				}
				if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
					$this->debug_message( 'rest request' );
				}
				if ( wp_script_is( 'twentytwenty-twentytwenty', 'enqueued' ) ) {
					$this->debug_message( 'twentytwenty enqueued' );
				}
				if ( preg_match( '/^<\?xml/', $buffer ) ) {
					$this->debug_message( 'not html, xml tag found' );
				}
				if ( strpos( $buffer, 'amp-boilerplate' ) ) {
					$this->debug_message( 'AMP page processing' );
				}
				return $buffer;
			}

			global $exactdn;
			$above_the_fold   = apply_filters( 'eio_lazy_fold', 0 );
			$images_processed = 0;

			// Clean the buffer of incompatible sections.
			$search_buffer = preg_replace( '/<div id="footer_photostream".*?\/div>/s', '', $buffer );
			$search_buffer = preg_replace( '/<(noscript|script).*?\/\1>/s', '', $search_buffer );

			$images = $this->get_images_from_html( $search_buffer, false );
			if ( ! empty( $images[0] ) && $this->is_iterable( $images[0] ) ) {
				foreach ( $images[0] as $index => $image ) {
					$images_processed++;
					if ( $images_processed <= $above_the_fold ) {
						continue;
					}
					$file = $images['img_url'][ $index ];
					$this->debug_message( "parsing an image: $file" );
					if ( $this->validate_image_tag( $image ) ) {
						$this->debug_message( 'found a valid image tag' );
						$this->debug_message( "original image tag: $image" );
						$orig_img = $image;
						$noscript = '<noscript>' . $orig_img . '</noscript>';
						$this->set_attribute( $image, 'data-src', $file, true );
						$srcset = $this->get_attribute( $image, 'srcset' );

						$width_attr      = $this->get_attribute( $image, 'width' );
						$height_attr     = $this->get_attribute( $image, 'height' );
						$placeholder_src = $this->placeholder_src;
						if ( false === strpos( $file, 'nggid' ) && ! preg_match( '#\.svg(\?|$)#', $file ) && $this->parsing_exactdn && strpos( $file, $this->exactdn_domain ) ) {
							$this->debug_message( 'using lqip' );
							list( $width, $height ) = $this->get_dimensions_from_filename( $file, true );
							if ( $width && $height && $width < 201 && $height < 201 ) {
								$placeholder_src = $exactdn->generate_url( $this->content_url . 'lazy/placeholder-' . $width . 'x' . $height . '.png' );
							} elseif ( $this->allow_lqip && apply_filters( 'eio_use_lqip', $this->get_option( $this->prefix . 'use_lqip' ), $file ) ) {
								$placeholder_src = add_query_arg( array( 'lazy' => 1 ), $file );
							} elseif ( $width && $height ) {
								$placeholder_src = $exactdn->generate_url( $this->content_url . 'lazy/placeholder-' . $width . 'x' . $height . '.png' );
							} else {
								$placeholder_src = add_query_arg( array( 'lazy' => 2 ), $file );
							}
						} elseif ( $this->allow_piip && $srcset && apply_filters( 'eio_use_piip', true, $file ) ) {
							$this->debug_message( 'trying piip' );
							// Get image dimensions for PNG placeholder.
							list( $width, $height ) = $this->get_dimensions_from_filename( $file, $this->parsing_exactdn );

							// Can't use a relative width or height, so unset the dimensions in favor of not breaking things.
							if ( false !== strpos( $width_attr, '%' ) || false !== strpos( $height_attr, '%' ) ) {
								$width_attr  = false;
								$height_attr = false;
							}

							if ( false === $width || false === $height ) {
								$width  = $width_attr;
								$height = $height_attr;
							}

							// Falsify them if empty.
							$width  = $width ? (int) $width : false;
							$height = $height ? (int) $height : false;
							if ( $width && $height ) {
								$this->debug_message( "creating piip of $width x $height" );
								$placeholder_src = $this->create_piip( $width, $height );
							}
						} elseif ( apply_filters( 'eio_use_siip', true, $file ) ) {
							$this->debug_message( 'trying siip' );
							$width  = $width_attr;
							$height = $height_attr;

							// Can't use a relative width or height, so unset the dimensions in favor of not breaking things.
							if ( false !== strpos( $width, '%' ) || false !== strpos( $height, '%' ) ) {
								$width  = false;
								$height = false;
							}

							// Falsify them if empty.
							$width  = $width ? (int) $width : false;
							$height = $height ? (int) $height : false;
							if ( $width && $height ) {
								$placeholder_src = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 $width $height'%3E%3C/svg%3E";
							}
						}
						$this->debug_message( "current placeholder is $placeholder_src" );

						if ( $srcset ) {
							$placeholder_src = apply_filters( 'eio_lazy_placeholder', $placeholder_src, $image );
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
							if ( false === strpos( $image, 'skip-autoscale' ) && apply_filters( 'eio_lazy_responsive', $srcset_sizes ) ) {
								$this->set_attribute( $image, 'data-sizes', 'auto', true );
								$this->remove_attribute( $image, 'sizes' );
							}
						} else {
							$this->set_attribute( $image, 'src', $placeholder_src, true );
						}
						$disable_native_lazy = false;
						// Ignore native lazy loading images.
						$loading_attr = $this->get_attribute( $image, 'loading' );
						if ( $loading_attr && in_array( trim( $loading_attr ), array( 'auto', 'eager', 'lazy' ), true ) ) {
							$disable_native_lazy = true;
						}

						if (
							( ! defined( 'EWWWIO_DISABLE_NATIVE_LAZY' ) || ! EWWWIO_DISABLE_NATIVE_LAZY ) &&
							( ! defined( 'EASYIO_DISABLE_NATIVE_LAZY' ) || ! EASYIO_DISABLE_NATIVE_LAZY ) &&
							! $disable_native_lazy
						) {
							$this->set_attribute( $image, 'loading', 'lazy' );
						}
						$this->set_attribute( $image, 'class', $this->get_attribute( $image, 'class' ) . ' lazyload', true );
						$buffer = str_replace( $orig_img, $image . $noscript, $buffer );
					}
				} // End foreach().
			} // End if().
			// Process background images on div elements.
			$buffer = $this->parse_background_images( $buffer, 'div' );
			// Process background images on li elements.
			$buffer = $this->parse_background_images( $buffer, 'li' );
			// Process background images on span elements.
			$buffer = $this->parse_background_images( $buffer, 'span' );
			// Process background images on section elements.
			$buffer = $this->parse_background_images( $buffer, 'section' );
			// Process background images on a/link elements.
			$buffer = $this->parse_background_images( $buffer, 'a' );
			// Images listed as picture/source elements. Mostly for NextGEN, but should work anywhere.
			$pictures = $this->get_picture_tags_from_html( $buffer );
			if ( $this->is_iterable( $pictures ) ) {
				foreach ( $pictures as $index => $picture ) {
					$sources = $this->get_elements_from_html( $picture, 'source' );
					if ( $this->is_iterable( $sources ) ) {
						foreach ( $sources as $source ) {
							if ( false !== strpos( $source, 'data-src' ) ) {
								continue;
							}
							$this->debug_message( "parsing a picture source: $source" );
							$srcset = $this->get_attribute( $source, 'srcset' );
							if ( $srcset ) {
								$this->debug_message( 'found srcset in source' );
								$lazy_source = $source;
								$this->set_attribute( $lazy_source, 'data-srcset', $srcset );
								$this->set_attribute( $lazy_source, 'srcset', $this->placeholder_src, true );
								$picture = str_replace( $source, $lazy_source, $picture );
							}
						}
						if ( $picture !== $pictures[ $index ] ) {
							$this->debug_message( 'lazified sources for picture element' );
							$buffer = str_replace( $pictures[ $index ], $picture, $buffer );
						}
					}
				}
			}
			// Video elements, looking for poster attributes that are images.
			/* $videos = $this->get_elements_from_html( $buffer, 'video' ); */
			$videos = '';
			if ( $this->is_iterable( $videos ) ) {
				foreach ( $videos as $index => $video ) {
					$this->debug_message( 'parsing a video element' );
					$file = $this->get_attribute( $video, 'poster' );
					if ( $file ) {
						$this->debug_message( "checking webp for video poster: $file" );
						if ( $this->validate_image_tag( $file ) ) {
							$this->set_attribute( $video, 'data-poster-webp', $this->placeholder_src );
							$this->set_attribute( $video, 'data-poster-image', $file );
							$this->remove_attribute( $video, 'poster' );
							$this->debug_message( "found webp for video poster: $file" );
							$buffer = str_replace( $videos[ $index ], $video, $buffer );
						}
					}
				}
			}
			$this->debug_message( 'all done parsing page for lazy' );
			return $buffer;
		}

		/**
		 * Parse elements of a given type for inline CSS background images.
		 *
		 * @param string $buffer The HTML content to parse.
		 * @param string $tag_type The type of HTML tag to look for.
		 * @return string The modified content with LL markup.
		 */
		function parse_background_images( $buffer, $tag_type ) {
			if ( in_array( $tag_type, $this->user_element_exclusions, true ) ) {
				return $buffer;
			}
			$elements = $this->get_elements_from_html( $buffer, $tag_type );
			if ( $this->is_iterable( $elements ) ) {
				foreach ( $elements as $index => $element ) {
					$this->debug_message( "parsing a $tag_type" );
					if ( false === strpos( $element, 'background:' ) && false === strpos( $element, 'background-image:' ) ) {
						continue;
					}
					$this->debug_message( 'element contains background/background-image:' );
					if ( ! $this->validate_bgimage_tag( $element ) ) {
						continue;
					}
					$this->debug_message( 'element is valid' );
					$style = $this->get_attribute( $element, 'style' );
					if ( empty( $style ) ) {
						continue;
					}
					$this->debug_message( "checking style attr for background-image: $style" );
					$bg_image_url = $this->get_background_image_url( $style );
					if ( $bg_image_url ) {
						$this->debug_message( 'bg-image url found' );
						$new_style = $this->remove_background_image( $style );
						if ( $style !== $new_style ) {
							$this->debug_message( 'style modified, continuing' );
							$this->set_attribute( $element, 'class', $this->get_attribute( $element, 'class' ) . ' lazyload', true );
							$this->set_attribute( $element, 'data-bg', $bg_image_url );
							$element = str_replace( $style, $new_style, $element );
						}
					}
					if ( $element !== $elements[ $index ] ) {
						$this->debug_message( "$tag_type modified, replacing in html source" );
						$buffer = str_replace( $elements[ $index ], $element, $buffer );
					}
				}
			}
			return $buffer;
		}

		/**
		 * Validate the user-defined exclusions.
		 */
		function validate_user_exclusions() {
			$user_exclusions = $this->get_option( $this->prefix . 'll_exclude' );
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
							'section' === $exclusion ||
							'span' === $exclusion
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
		 * Checks if the tag is allowed to be lazy loaded.
		 *
		 * @param string $image The image (img) tag.
		 * @return bool True if the tag is allowed, false otherwise.
		 */
		function validate_image_tag( $image ) {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			if (
				strpos( $image, 'base64,R0lGOD' ) ||
				strpos( $image, 'lazy-load/images/1x1' ) ||
				strpos( $image, '/assets/images/' )
			) {
				$this->debug_message( 'lazy load placeholder detected' );
				return false;
			}

			// Skip inline data URIs.
			$image_src = $this->get_attribute( $image, 'src' );
			if ( false !== strpos( $image_src, 'data:image' ) ) {
				$this->debug_message( 'data:image pattern detected in src' );
				return false;
			}
			if ( false !== strpos( $image, 'data:image' ) && false !== strpos( $image, 'lazyload' ) ) {
				$this->debug_message( 'data:image pattern detected with lazyload string' );
				return false;
			}
			// Ignore 0-size Pinterest schema images.
			if ( strpos( $image, 'data-pin-description=' ) && strpos( $image, 'width="0" height="0"' ) ) {
				$this->debug_message( 'data-pin-description img skipped' );
				return false;
			}

			$exclusions = apply_filters(
				'eio_lazy_exclusions',
				array_merge(
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
		 * Checks if a tag with a background image is allowed to be lazy loaded.
		 *
		 * @param string $tag The tag.
		 * @return bool True if the tag is allowed, false otherwise.
		 */
		function validate_bgimage_tag( $tag ) {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			$exclusions = apply_filters(
				'eio_lazy_bg_image_exclusions',
				array_merge(
					array(
						'data-no-lazy=',
						'header-gallery-wrapper ',
						'lazyload',
						'skip-lazy',
						'avia-bg-style-fixed',
					),
					$this->user_exclusions
				),
				$tag
			);
			foreach ( $exclusions as $exclusion ) {
				if ( false !== strpos( $tag, $exclusion ) ) {
					return false;
				}
			}
			return true;
		}

		/**
		 * Build a PNG inline image placeholder.
		 *
		 * @param int $width The width of the placeholder image.
		 * @param int $height The height of the placeholder image.
		 * @return string The PNG placeholder link.
		 */
		function create_piip( $width = 1, $height = 1 ) {
			$width  = (int) $width;
			$height = (int) $height;
			if ( ( 1 === $width && 1 === $height ) || ! $width || ! $height ) {
				return $this->placeholder_src;
			}

			if ( $width > 1920 ) {
				$ratio  = $height / $width;
				$width  = 1920;
				$height = round( 1920 * $ratio );
			}
			$height = min( $height, 1920 );

			$memory_required = 5 * $height * $width;
			if (
				! $this->get_option( 'ewww_image_optimizer_cloud_key' ) &&
				function_exists( 'ewwwio_check_memory_available' ) &&
				! ewwwio_check_memory_available( $memory_required + 500000 )
			) {
				return $this->placeholder_src;
			}

			$piip_path = $this->piip_folder . 'placeholder-' . $width . 'x' . $height . '.png';
			if ( $this->parsing_exactdn ) {
				global $exactdn;
				return $exactdn->generate_url( $this->content_url . 'lazy/placeholder-' . $width . 'x' . $height . '.png' );
			} elseif ( ! is_file( $piip_path ) ) {
				if ( $this->get_option( 'ewww_image_optimizer_cloud_key' ) && ! defined( 'EWWW_IMAGE_OPTIMIZER_DISABLE_API_PIP' ) ) {
					$piip_location = "http://optimize.exactlywww.com/resize/lazy.php?width=$width&height=$height";
					$piip_response = wp_remote_get( $piip_location );
					if ( ! is_wp_error( $piip_response ) && is_array( $piip_response ) && ! empty( $piip_response['body'] ) ) {
						file_put_contents( $piip_path, $piip_response['body'] );
						clearstatcache();
					}
				}
				if ( ! is_file( $piip_path ) && function_exists( 'ewwwio_check_memory_available' ) && ewwwio_check_memory_available( $memory_required + 500000 ) ) {
					$img   = imagecreatetruecolor( $width, $height );
					$color = imagecolorallocatealpha( $img, 0, 0, 0, 127 );
					imagefill( $img, 0, 0, $color );
					imagesavealpha( $img, true );
					imagecolortransparent( $img, imagecolorat( $img, 0, 0 ) );
					imagetruecolortopalette( $img, false, 1 );
					imagepng( $img, $piip_path, 9 );
					if ( function_exists( 'ewww_image_optimizer' ) ) {
						ewww_image_optimizer( $piip_path );
					}
				}
			}
			clearstatcache();
			if ( is_file( $piip_path ) ) {
				return $this->content_url . 'lazy/placeholder-' . $width . 'x' . $height . '.png';
			}
			return $this->placeholder_src;
		}
		/**
		 * Allow lazy loading of images for some admin-ajax requests.
		 *
		 * @param bool $allow Will normally be false, unless already modified by another function.
		 * @return bool True if it's an allowable admin-ajax request, false for all other admin requests.
		 */
		function allow_admin_lazyload( $allow ) {
			if ( ! wp_doing_ajax() ) {
				return $allow;
			}
			if ( ! empty( $_POST['action'] ) && 'vc_get_vc_grid_data' === $_POST['action'] ) {
				$this->debug_message( 'allowing lazy on vc grid' );
				return true;
			}
			if ( ! empty( $_POST['action'] ) && 'Essential_Grid_Front_request_ajax' === $_POST['action'] ) {
				/* return true; */
			}
			return $allow;
		}

		/**
		 * Check if PIIP should be used, but allow filters to alter the option.
		 *
		 * @param bool $use_piip Whether LL should use PNG inline image placeholders.
		 * @return bool True to use PIIP, false to skip them.
		 */
		function maybe_piip( $use_piip ) {
			if ( defined( 'EWWW_IMAGE_OPTIMIZER_USE_PIIP' ) && ! EWWW_IMAGE_OPTIMIZER_USE_PIIP ) {
				return false;
			}
			if ( defined( 'EASYIO_USE_PIIP' ) && ! EASYIO_USE_PIIP ) {
				return false;
			}
			if ( function_exists( 'ewwwio_check_memory_available' ) && ! ewwwio_check_memory_available( 15000000 ) ) {
				return false;
			}
			return $use_piip;
		}

		/**
		 * Check if SIIP should be used, but allow filters to alter the option.
		 *
		 * @param bool $use_siip Whether LL should use SVG inline image placeholders.
		 * @return bool True to use SIIP, false to skip them.
		 */
		function maybe_siip( $use_siip ) {
			if ( defined( 'EWWW_IMAGE_OPTIMIZER_USE_SIIP' ) && ! EWWW_IMAGE_OPTIMIZER_USE_SIIP ) {
				return false;
			}
			if ( defined( 'EASYIO_USE_SIIP' ) && ! EASYIO_USE_SIIP ) {
				return false;
			}
			return $use_siip;
		}

		/**
		 * Adds a small CSS block to hide lazyload elements for no-JS browsers.
		 */
		function no_js_css() {
			echo '<noscript><style>.lazyload[data-src]{display:none !important;}</style></noscript>';
		}

		/**
		 * Load full lazysizes script when SCRIPT_DEBUG is enabled.
		 */
		function debug_script() {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			if ( $this->is_amp() ) {
				return;
			}
			$plugin_file = constant( strtoupper( $this->prefix ) . 'PLUGIN_FILE' );
			wp_enqueue_script( 'eio-lazy-load-pre', plugins_url( '/includes/lazysizes-pre.js', $plugin_file ), array(), $this->version );
			wp_enqueue_script( 'eio-lazy-load', plugins_url( '/includes/lazysizes.js', $plugin_file ), array(), $this->version );
			wp_enqueue_script( 'eio-lazy-load-post', plugins_url( '/includes/lazysizes-post.js', $plugin_file ), array(), $this->version );
			wp_enqueue_script( 'eio-lazy-load-uvh', plugins_url( '/includes/ls.unveilhooks.js', $plugin_file ), array(), $this->version );
			if ( defined( strtoupper( $this->prefix ) . 'LAZY_PRINT' ) && constant( strtoupper( $this->prefix ) . 'LAZY_PRINT' ) ) {
				wp_enqueue_script( 'eio-lazy-load-print', plugins_url( '/includes/ls.print.js', $plugin_file ), array(), $this->version );
			}
			wp_localize_script(
				'eio-lazy-load',
				'eio_lazy_vars',
				array(
					'exactdn_domain' => ( $this->parsing_exactdn ? $this->exactdn_domain : '' ),
				)
			);
		}

		/**
		 * Load minified lazysizes script.
		 */
		function min_script() {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			if ( $this->is_amp() ) {
				return;
			}
			$plugin_file = constant( strtoupper( $this->prefix ) . 'PLUGIN_FILE' );
			wp_enqueue_script( 'eio-lazy-load', plugins_url( '/includes/lazysizes.min.js', $plugin_file ), array(), $this->version );
			if ( defined( strtoupper( $this->prefix ) . 'LAZY_PRINT' ) && constant( strtoupper( $this->prefix ) . 'LAZY_PRINT' ) ) {
				wp_enqueue_script( 'eio-lazy-load-print', plugins_url( '/includes/ls.print.min.js', $plugin_file ), array(), $this->version );
			}
			wp_localize_script(
				'eio-lazy-load',
				'eio_lazy_vars',
				array(
					'exactdn_domain' => ( $this->parsing_exactdn ? $this->exactdn_domain : '' ),
				)
			);
		}
	}

	global $eio_lazy_load;
	$eio_lazy_load = new EIO_Lazy_Load();
}
