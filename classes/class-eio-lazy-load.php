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
		 * A list of user-defined inclusions to lazy load for "external" CSS background images.
		 *
		 * @access protected
		 * @var array $css_element_inclusions
		 */
		protected $css_element_inclusions = array();

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
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );

			$uri = add_query_arg( null, null );
			$this->debug_message( "request uri is $uri" );

			add_filter( 'eio_do_lazyload', array( $this, 'should_process_page' ), 10, 2 );

			/**
			 * Allow pre-empting Lazy Load by page.
			 *
			 * @param bool Whether to parse the page for images to lazy load, default true.
			 * @param string $uri The URL of the page.
			 */
			if ( ! apply_filters( 'eio_do_lazyload', true, $uri ) ) {
				return;
			}

			$this->piip_folder = $this->content_dir . 'lazy/';
			global $eio_lazy_load;
			if ( is_object( $eio_lazy_load ) ) {
				$this->debug_message( 'you are doing it wrong' );
				return 'you are doing it wrong';
			}

			add_action( 'wp_head', array( $this, 'no_js_css' ) );

			if ( method_exists( 'autoptimizeImages', 'imgopt_active' ) && autoptimizeImages::imgopt_active() ) {
				add_filter( 'autoptimize_filter_html_before_minify', array( $this, 'filter_page_output' ) );
			} else {
				add_filter( $this->prefix . 'filter_page_output', array( $this, 'filter_page_output' ), 15 );
			}

			add_filter( 'vc_get_vc_grid_data_response', array( $this, 'filter_page_output' ) );
			add_filter( 'woocommerce_prl_ajax_response_html', array( $this, 'filter_page_output' ) );

			// Filter for FacetWP JSON responses.
			add_filter( 'facetwp_render_output', array( $this, 'filter_facetwp_json_output' ) );

			if ( class_exists( 'ExactDN' ) && $this->get_option( $this->prefix . 'exactdn' ) ) {
				global $exactdn;
				$this->exactdn_domain = $exactdn->get_exactdn_domain();
				if ( $this->exactdn_domain ) {
					$this->parsing_exactdn = true;
					$this->debug_message( 'parsing an exactdn page' );
					$this->allowed_urls[] = 'https://' . $this->exactdn_domain;
					$this->allowed_urls[] = 'http://' . $this->exactdn_domain;
					$this->allowed_urls[] = '//' . $this->exactdn_domain;
				}
				$this->allow_lqip = false;
				if ( $exactdn->get_plan_id() > 1 ) {
					$this->allow_lqip = true;
				}
			}

			if ( ! is_dir( $this->piip_folder ) ) {
				$this->allow_piip = wp_mkdir_p( $this->piip_folder ) && ( $this->gd_support() || $this->imagick_support() );
			} else {
				$this->allow_piip = is_writable( $this->piip_folder ) && ( $this->gd_support() || $this->imagick_support() );
			}

			add_filter( 'wp_lazy_loading_enabled', array( $this, 'wp_lazy_loading_enabled' ), 10, 2 );

			if ( ! defined( 'EIO_LL_AUTOSCALE' ) && ! $this->get_option( $this->prefix . 'll_autoscale' ) ) {
				define( 'EIO_LL_AUTOSCALE', false );
			}

			// Override for number of images to consider "above the fold".
			add_filter( 'eio_lazy_fold', array( $this, 'override_lazy_fold' ), 9 );
			// Filter early, so that others at the default priority take precendence.
			add_filter( 'eio_use_piip', array( $this, 'maybe_piip' ), 9 );
			add_filter( 'eio_use_siip', array( $this, 'maybe_siip' ), 9 );

			// Overrides for admin-ajax images.
			add_filter( 'eio_allow_admin_lazyload', array( $this, 'allow_admin_lazyload' ) );

			// Load the appropriate JS.
			if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
				// Load the non-minified and separate versions of the lazy load scripts.
				add_action( 'wp_enqueue_scripts', array( $this, 'debug_script' ), 1 );
			} else {
				// Load the minified, combined version of the lazy load script.
				// add_action( 'wp_head', array( $this, 'inline_script' ), 1 );
				// Load the minified, combined version of the lazy load script.
				add_action( 'wp_enqueue_scripts', array( $this, 'min_script' ), 1 );
			}
			$this->validate_user_exclusions();
			$this->validate_css_element_inclusions();
			$this->get_allowed_domains();
		}

		/**
		 * Check if pages should be processed, especially for things like page builders.
		 *
		 * @since 6.2.2
		 *
		 * @param boolean $should_process Whether LL should process the page.
		 * @param string  $uri The URI of the page (no domain or scheme included).
		 * @return boolean True to process the page, false to skip.
		 */
		function should_process_page( $should_process = true, $uri = '' ) {
			// Don't foul up the admin side of things, unless a plugin needs to.
			if ( is_admin() &&
				/**
				 * Provide plugins a way of running Lazy Load for images in the WordPress Admin, usually for admin-ajax.php.
				 *
				 * @param bool false Allow Lazy Load to run on the Dashboard. Default to false.
				 */
				false === apply_filters( 'eio_allow_admin_lazyload', false )
			) {
				$this->debug_message( 'is_admin' );
				return false;
			}
			if ( empty( $uri ) ) {
				$uri = add_query_arg( null, null );
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
			if ( ! did_action( 'parse_query' ) ) {
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
		 * Disable native lazy load for img elements.
		 *
		 * @param bool   $default True if it is an img or iframe element. Should be false otherwise.
		 * @param string $tag_name The type of HTML tag/element being parsed.
		 * @return bool False for img elements, leave as-is for others.
		 */
		function wp_lazy_loading_enabled( $default, $tag_name = 'img' ) {
			if ( 'img' === $tag_name ) {
				if ( defined( 'EIO_ENABLE_NATIVE_LAZY' ) && EIO_ENABLE_NATIVE_LAZY ) {
					return true;
				}
				return false;
			}
			return $default;
		}

		/**
		 * Search for img elements and rewrite them for Lazy Load with fallback to noscript elements.
		 *
		 * @param string $buffer The full HTML page generated since the output buffer was started.
		 * @return string The altered buffer containing the full page with Lazy Load attributes.
		 */
		function filter_page_output( $buffer ) {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			if ( empty( $buffer ) ) {
				return $buffer;
			}
			if ( preg_match( '/^<\?xml/', $buffer ) ) {
				$this->debug_message( 'not html, xml tag found' );
				return $buffer;
			}
			if ( strpos( $buffer, 'amp-boilerplate' ) ) {
				$this->debug_message( 'AMP page processing' );
				return $buffer;
			}
			if ( $this->is_json( $buffer ) ) {
				return $buffer;
			}
			if ( ! $this->should_process_page() ) {
				return $buffer;
			}

			// If JS WebP isn't running, set ewww_webp_supported to false so we have something defined.
			if ( ! class_exists( 'EIO_JS_Webp' ) ) {
				$body_tags        = $this->get_elements_from_html( $buffer, 'body' );
				$body_webp_script = '<script data-cfasync="false">var ewww_webp_supported=false;</script>';
				if ( $this->is_iterable( $body_tags ) && ! empty( $body_tags[0] ) && false !== strpos( $body_tags[0], '<body' ) ) {
					// Add the WebP script right after the opening tag.
					$buffer = str_replace( $body_tags[0], $body_tags[0] . "\n" . $body_webp_script, $buffer );
				} else {
					$buffer = str_replace( '<body>', "<body>\n$body_webp_script", $buffer );
				}
			}

			$above_the_fold   = apply_filters( 'eio_lazy_fold', 0 );
			$images_processed = 0;
			$replacements     = array();

			// Clean the buffer of incompatible sections.
			$search_buffer = preg_replace( '/<div id="footer_photostream".*?\/div>/s', '', $buffer );
			$search_buffer = preg_replace( '/<(picture|noscript|script).*?\/\1>/s', '', $search_buffer );

			$images = $this->get_images_from_html( $search_buffer, false );
			if ( ! empty( $images[0] ) && $this->is_iterable( $images[0] ) ) {
				foreach ( $images[0] as $index => $image ) {
					$file = $images['img_url'][ $index ];
					$this->debug_message( "parsing an image: $file" );
					if ( $this->validate_image_tag( $image ) ) {
						$this->debug_message( 'found a valid image tag' );
						$this->debug_message( "original image tag: $image" );
						$orig_img = $image;
						$ns_img   = $image;
						$image    = $this->parse_img_tag( $image, $file );
						$this->set_attribute( $ns_img, 'data-eio', 'l', true );
						$noscript = '<noscript>' . $ns_img . '</noscript>';
						$position = strpos( $buffer, $orig_img );
						if ( $position && $orig_img !== $image ) {
							$replacements[ $position ] = array(
								'orig' => $orig_img,
								'lazy' => $image . $noscript,
							);
						}
						/* $buffer   = str_replace( $orig_img, $image . $noscript, $buffer ); */
					}
				} // End foreach().
			} // End if().
			$element_types = apply_filters( 'eio_allowed_background_image_elements', array( 'div', 'li', 'span', 'section', 'a' ) );
			foreach ( $element_types as $element_type ) {
				// Process background images on HTML elements.
				$css_replacements = $this->parse_background_images( $element_type, $buffer );
				if ( $this->is_iterable( $css_replacements ) ) {
					foreach ( $css_replacements as $position => $css_replacement ) {
						if ( $position ) {
							$replacements[ $position ] = $css_replacement;
						}
					}
				}
			}
			if ( in_array( 'picture', $this->user_element_exclusions, true ) ) {
				$pictures = '';
			} else {
				// Images listed as picture/source elements. Mostly for NextGEN, but should work anywhere.
				$pictures = $this->get_picture_tags_from_html( $buffer );
			}
			if ( $this->is_iterable( $pictures ) ) {
				foreach ( $pictures as $index => $picture ) {
					if ( ! $this->validate_image_tag( $picture ) ) {
						continue;
					}
					$pimages = $this->get_images_from_html( $picture, false );
					if ( ! empty( $pimages[0] ) && $this->is_iterable( $pimages[0] ) && ! empty( $pimages[0][0] ) ) {
						$image = $pimages[0][0];
						$file  = $pimages['img_url'][0];
						$this->debug_message( "parsing an image (inside picture): $file" );
						$this->debug_message( "the img tag: $image" );
						if ( $this->validate_image_tag( $image ) ) {
							$this->debug_message( 'found a valid image tag (inside picture)' );
							$orig_img = $image;
							$ns_img   = $image;
							$image    = $this->parse_img_tag( $image, $file );
							$this->set_attribute( $ns_img, 'data-eio', 'l', true );
							$noscript = '<noscript>' . $ns_img . '</noscript>';
							$picture  = str_replace( $orig_img, $image . $noscript, $picture );
						}
					} else {
						continue;
					}
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
								$this->remove_attribute( $lazy_source, 'srcset' );
								$picture = str_replace( $source, $lazy_source, $picture );
							}
						}
						$position = strpos( $buffer, $pictures[ $index ] );
						if ( $position && $picture !== $pictures[ $index ] ) {
							$this->debug_message( 'lazified sources for picture element' );
							$replacements[ $position ] = array(
								'orig' => $pictures[ $index ],
								'lazy' => $picture,
							);
							/* $buffer = str_replace( $pictures[ $index ], $picture, $buffer ); */
						}
					}
				}
			}
			// Iframe elements, looking for stuff like YouTube embeds.
			if ( in_array( 'iframe', $this->user_element_exclusions, true ) ) {
				$frames = '';
			} else {
				$frames = $this->get_elements_from_html( $search_buffer, 'iframe' );
			}
			if ( $this->is_iterable( $frames ) ) {
				foreach ( $frames as $index => $frame ) {
					$this->debug_message( 'parsing an iframe element' );
					$url = $this->get_attribute( $frame, 'src' );
					if ( $url && 0 === strpos( $url, 'http' ) && $this->validate_iframe_tag( $frame ) ) {
						$this->debug_message( "lazifying iframe for: $url" );
						$this->set_attribute( $frame, 'data-src', $url );
						$this->remove_attribute( $frame, 'src' );
						$this->set_attribute( $frame, 'class', trim( $this->get_attribute( $frame, 'class' ) . ' lazyload' ), true );
						if ( $frame !== $frames[ $index ] ) {
							$buffer = str_replace( $frames[ $index ], $frame, $buffer );
						}
					}
				}
			}
			if ( $this->is_iterable( $replacements ) ) {
				ksort( $replacements );
				foreach ( $replacements as $position => $replacement ) {
					$this->debug_message( "possible replacement at $position" );
					$images_processed++;
					if ( $images_processed <= $above_the_fold ) {
						continue;
					}
					if ( empty( $replacement['orig'] ) || empty( $replacement['lazy'] ) ) {
						continue;
					}
					if ( $replacement['orig'] === $replacement['lazy'] ) {
						continue;
					}
					$this->debug_message( "replacing {$replacement['orig']} with {$replacement['lazy']}" );
					$buffer = str_replace( $replacement['orig'], $replacement['lazy'], $buffer );
				}
			}
			$this->debug_message( 'all done parsing page for lazy' );
			return $buffer;
		}

		/**
		 * Parse img elements to insert lazyload markup.
		 *
		 * @param string $image The img tag to parse.
		 * @param string $file The URL from the src attribute. Optional.
		 * @return string The modified tag.
		 */
		function parse_img_tag( $image, $file = '' ) {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			global $exactdn;
			if ( ! $file ) {
				$file = $this->get_attribute( $image, 'src' );
			}
			$file = str_replace( '&#038;', '&', esc_url( $file ) );
			$this->set_attribute( $image, 'data-src', $file, true );
			$srcset = $this->get_attribute( $image, 'srcset' );

			if (
				! empty( $_POST['action'] ) && // phpcs:ignore WordPress.Security.NonceVerification
				! empty( $_POST['vc_action'] ) && // phpcs:ignore WordPress.Security.NonceVerification
				! empty( $_POST['tag'] ) && // phpcs:ignore WordPress.Security.NonceVerification
				'vc_get_vc_grid_data' === $_POST['action'] && // phpcs:ignore WordPress.Security.NonceVerification
				'vc_get_vc_grid_data' === $_POST['vc_action'] && // phpcs:ignore WordPress.Security.NonceVerification
				'vc_media_grid' === $_POST['tag'] // phpcs:ignore WordPress.Security.NonceVerification
			) {
				return $image;
			}

			// Check to see if they added img as an exclusion.
			if ( in_array( 'img', $this->user_element_exclusions, true ) ) {
				return $image;
			}

			$physical_width  = false;
			$physical_height = false;
			$width_attr      = $this->get_attribute( $image, 'width' );
			$height_attr     = $this->get_attribute( $image, 'height' );
			// Can't use a relative width or height, so unset the dimensions in favor of not breaking things.
			if ( false !== strpos( $width_attr, '%' ) || false !== strpos( $height_attr, '%' ) ) {
				$width_attr  = false;
				$height_attr = false;
			}
			list( $physical_width, $physical_height ) = $this->get_image_dimensions_by_url( $file );

			// Initialize the placeholder for this image.
			$placeholder_src = $this->placeholder_src;

			$insert_dimensions = false;
			$this->debug_message( "width attr: $width_attr and height attr: $height_attr" );
			if ( apply_filters( 'eio_add_missing_width_height_attrs', $this->get_option( $this->prefix . 'add_missing_dims' ) ) && ( empty( $width_attr ) || empty( $height_attr ) ) ) {
				$this->debug_message( 'missing width attr or height attr' );
				if ( $physical_width && is_numeric( $physical_width ) && $physical_height && is_numeric( $physical_height ) ) {
					$this->debug_message( "found $physical_width and/or $physical_height to insert (maybe)" );
					if ( $width_attr && is_numeric( $width_attr ) && $width_attr < $physical_width ) { // Then $height_attr is empty...
						$height_attr = round( ( $physical_height / $physical_width ) * $width_attr );
						$this->debug_message( "width was already $width_attr, height was empty, but now $height_attr" );
					} elseif ( $height_attr && is_numeric( $height_attr ) && $height_attr < $physical_height ) { // Or $width_attr is empty...
						$width_attr = round( ( $physical_width / $physical_height ) * $height_attr );
						$this->debug_message( "height was already $height_attr, width was empty, but now $width_attr" );
					} else {
						$width_attr  = $physical_width;
						$height_attr = $physical_height;
						$this->debug_message( 'both width and height were empty' );
					}
					$insert_dimensions = true;
				}
			}

			$use_native_lazy = false;

			$placeholder_types = array();
			if ( $this->parsing_exactdn && $this->allow_lqip && apply_filters( 'eio_use_lqip', $this->get_option( $this->prefix . 'use_lqip' ), $file ) ) {
				$placeholder_types[] = 'lqip';
			}
			if ( $this->parsing_exactdn && apply_filters( 'eio_use_piip', true, $file ) ) {
				$placeholder_types[] = 'epip';
			}
			if ( $this->allow_piip && apply_filters( 'eio_use_piip', true, $file ) ) {
				$placeholder_types[] = 'piip';
			}
			if ( apply_filters( 'eio_use_siip', $this->get_option( $this->prefix . 'use_siip' ), $file ) ) {
				$placeholder_types[] = 'siip';
			}

			if ( // This isn't super helpful. It makes PIIPs that don't help with auto-scaling.
				false && ( ! $physical_width || ! $physical_height ) &&
				$width_attr && is_numeric( $width_attr ) && $height_attr && is_numeric( $height_attr )
			) {
				$physical_width  = $width_attr;
				$physical_height = $height_attr;
			}
			foreach ( $placeholder_types as $placeholder_type ) {
				switch ( $placeholder_type ) {
					case 'lqip':
						$this->debug_message( 'using lqip, maybe' );
						if ( false === strpos( $file, 'nggid' ) && ! preg_match( '#\.svg(\?|$)#', $file ) && strpos( $file, $this->exactdn_domain ) ) {
							$placeholder_src = add_query_arg( array( 'lazy' => 1 ), $file );
							$use_native_lazy = true;
							break 2;
						}
						break;
					case 'siip':
						$this->debug_message( 'trying siip' );
						// Can't use a relative width or height, so unset the dimensions in favor of not breaking things.
						if ( false !== strpos( $width_attr, '%' ) || false !== strpos( $height_attr, '%' ) ) {
							break;
						}

						// Falsify them if empty.
						$width_attr  = (int) $width_attr ? (int) $width_attr : false;
						$height_attr = (int) $height_attr ? (int) $height_attr : false;
						if ( $width_attr && $height_attr ) {
							$placeholder_src = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 $width_attr $height_attr'%3E%3C/svg%3E";
							break 2;
						}
						break;
					case 'epip':
						$this->debug_message( 'using epip, maybe' );
						if ( false === strpos( $file, 'nggid' ) && ! preg_match( '#\.svg(\?|$)#', $file ) && strpos( $file, $this->exactdn_domain ) ) {
							if ( $physical_width && $physical_height && $this->allow_piip ) {
								$placeholder_src = $this->create_piip( $physical_width, $physical_height );
								if ( false === strpos( $placeholder_src, 'data:image' ) ) {
									$use_native_lazy = true;
								}
								break 2;
							} else {
								$placeholder_src = add_query_arg( array( 'lazy' => 2 ), $file );
								$use_native_lazy = true;
								break 2;
							}
						}
						break;
					case 'piip':
						$this->debug_message( 'trying piip' );

						if ( false === $physical_width || false === $physical_height ) {
							$physical_width  = $width_attr;
							$physical_height = $height_attr;
						}

						// Falsify them if empty.
						$physical_width  = (int) $physical_width ? (int) $physical_width : false;
						$physical_height = (int) $physical_height ? (int) $physical_height : false;
						if ( $physical_width && $physical_height ) {
							$this->debug_message( "creating piip of $physical_width x $physical_height" );
							$png_placeholder_src = $this->create_piip( $physical_width, $physical_height );
							if ( $png_placeholder_src ) {
								$placeholder_src = $png_placeholder_src;
								if ( false === strpos( $placeholder_src, 'data:image' ) ) {
									$use_native_lazy = true;
								}
								break 2;
							}
						}
						break;
					default:
						$this->debug_message( "what in the world is $placeholder_type?" );
				}
			}
			$this->debug_message( "current placeholder is $placeholder_src" );

			$placeholder_src = apply_filters( 'eio_lazy_placeholder', $placeholder_src, $image );

			// Check for native lazy loading images.
			$loading_attr = $this->get_attribute( $image, 'loading' );
			if ( ( ! defined( 'EIO_DISABLE_NATIVE_LAZY' ) || ! EIO_DISABLE_NATIVE_LAZY ) && ! $loading_attr && $use_native_lazy ) {
				$this->set_attribute( $image, 'loading', 'lazy' );
			}
			// Check for the decoding attribute.
			$decoding_attr = $this->get_attribute( $image, 'decoding' );
			if ( ( ! defined( 'EIO_DISABLE_DECODING_ATTR' ) || ! EIO_DISABLE_DECODING_ATTR ) && ! $decoding_attr ) {
				$this->set_attribute( $image, 'decoding', 'async' );
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
				if (
					false === strpos( $image, 'skip-autoscale' ) &&
					apply_filters( 'eio_lazy_responsive', $srcset_sizes ) &&
					( ! defined( 'EIO_LL_AUTOSCALE' ) || EIO_LL_AUTOSCALE )
				) {
					$this->set_attribute( $image, 'data-sizes', 'auto', true );
					$this->remove_attribute( $image, 'sizes' );
				}
			} else {
				$this->set_attribute( $image, 'src', $placeholder_src, true );
			}

			$existing_class = $this->get_attribute( $image, 'class' );
			if ( ! empty( $existing_class ) ) {
				$this->set_attribute( $image, 'class', trim( $existing_class . ' lazyload' ), true );
			} else {
				$this->set_attribute( $image, 'class', 'lazyload', true );
			}
			if ( $insert_dimensions ) {
				$this->debug_message( "setting width=$width_attr and height=$height_attr" );
				$this->set_attribute( $image, 'width', $width_attr, true );
				$this->set_attribute( $image, 'height', $height_attr, true );
			}
			if ( 0 === strpos( $placeholder_src, 'data:image/svg+xml' ) ) {
				$this->set_attribute( $image, 'data-eio-rwidth', $physical_width, true );
				$this->set_attribute( $image, 'data-eio-rheight', $physical_height, true );
			}
			$this->debug_message( 'lazified img element:' );
			$this->debug_message( trim( $image ) );
			return $image;
		}

		/**
		 * Parse elements of a given type for inline CSS background images.
		 *
		 * @param string $tag_type The type of HTML tag to look for.
		 * @param string $buffer The HTML content to parse (and possibly modify).
		 * @return array A list of replacements to make in $buffer.
		 */
		function parse_background_images( $tag_type, &$buffer ) {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			$replacements = array();
			if ( in_array( $tag_type, $this->user_element_exclusions, true ) ) {
				return $replacements;
			}
			$elements = $this->get_elements_from_html( preg_replace( '/<(noscript|script).*?\/\1>/s', '', $buffer ), $tag_type );
			if ( $this->is_iterable( $elements ) ) {
				foreach ( $elements as $index => $element ) {
					$this->debug_message( "parsing a $tag_type" );
					if ( false === strpos( $element, 'background:' ) && false === strpos( $element, 'background-image:' ) ) {
						$element = $this->lazify_element( $element );
						if ( $element !== $elements[ $index ] ) {
							$this->debug_message( "$tag_type lazified, replacing in html source" );
							$buffer = str_replace( $elements[ $index ], $element, $buffer );
						}
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
					$position = strpos( $buffer, $elements[ $index ] );
					if ( $position && $element !== $elements[ $index ] ) {
						$this->debug_message( "$tag_type modified, replacing in html source" );
						$replacements[ $position ] = array(
							'orig' => $elements[ $index ],
							'lazy' => $element,
						);
						/* $buffer = str_replace( $elements[ $index ], $element, $buffer ); */
					}
				}
			}
			return $replacements;
		}

		/**
		 * Add lazyload class to any element that doesn't have a direct-attached background image.
		 *
		 * @param string $element The HTML element/tag to parse.
		 * @return string The (maybe) modified element.
		 */
		function lazify_element( $element ) {
			if ( defined( 'EIO_EXTERNAL_CSS_LAZY_LOAD' ) && ! EIO_EXTERNAL_CSS_LAZY_LOAD ) {
				return $element;
			}
			if ( false === strpos( $element, 'background:' ) && false === strpos( $element, 'background-image:' ) && false === strpos( $element, 'style=' ) ) {
				if ( false !== strpos( $element, 'id=' ) || false !== strpos( $element, 'class=' ) ) {
					foreach ( $this->css_element_inclusions as $inclusion ) {
						if ( false !== strpos( $element, $inclusion ) && $this->validate_bgimage_tag( $element ) ) {
							$this->set_attribute( $element, 'class', $this->get_attribute( $element, 'class' ) . ' lazyload', true );
						}
					}
				}
			}
			return $element;
		}

		/**
		 * Parse template data from FacetWP that will be included in JSON response.
		 * https://facetwp.com/documentation/developers/output/facetwp_render_output/
		 *
		 * @param array $output The full array of FacetWP data.
		 * @return array The FacetWP data with lazy loaded images.
		 */
		function filter_facetwp_json_output( $output ) {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			if ( empty( $output['template'] ) || ! is_string( $output['template'] ) ) {
				$this->debug_message( 'no template data available' );
				if ( $this->function_exists( 'print_r' ) ) {
					$this->debug_message( print_r( $output, true ) );
				}
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
							'iframe' === $exclusion ||
							'img' === $exclusion ||
							'li' === $exclusion ||
							'picture' === $exclusion ||
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
		 * Validate the user-defined CSS element inclusions.
		 */
		function validate_css_element_inclusions() {
			$user_inclusions = $this->get_option( $this->prefix . 'll_all_things' );
			if ( ! empty( $user_inclusions ) ) {
				if ( ! is_string( $user_inclusions ) ) {
					return;
				}
				$user_inclusions = explode( ',', $user_inclusions );
				if ( is_array( $user_inclusions ) ) {
					foreach ( $user_inclusions as $inclusion ) {
						if ( ! is_string( $inclusion ) ) {
							continue;
						}
						$inclusion = trim( $inclusion );
						if ( empty( $inclusion ) ) {
							continue;
						}
						$this->css_element_inclusions[] = $inclusion;
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
			if ( $this->is_lazy_placeholder( $image ) ) {
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
						'data-mk-image-src',
						'data-no-lazy=',
						'data-src=',
						'data-srcset=',
						'ewww_webp_lazy_load',
						'fullurl=',
						'gazette-featured-content-thumbnail',
						'lazy-slider-img=',
						'mgl-lazy',
						'owl-lazy',
						'preload-me',
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
		 * Checks if an iframe tag is allowed to be lazy loaded.
		 *
		 * @param string $tag The tag.
		 * @return bool True if the tag is allowed, false otherwise.
		 */
		function validate_iframe_tag( $tag ) {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			$exclusions = apply_filters(
				'eio_lazy_iframe_exclusions',
				array_merge(
					array(
						'data-no-lazy=',
						'lazyload',
						'skip-lazy',
						'vimeo',
						'about:blank',
						'googletagmanager',
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

			if ( empty( $width ) || empty( $height ) ) {
				return $this->placeholder_src;
			}

			$piip_path = $this->piip_folder . 'placeholder-' . $width . 'x' . $height . '.png';
			// Keep this in case folks really want external Easy IO CDN placeholders.
			if ( defined( 'EIO_USE_EXTERNAL_PLACEHOLDERS' ) && EIO_USE_EXTERNAL_PLACEHOLDERS && $this->parsing_exactdn ) {
				global $exactdn;
				return $exactdn->generate_url( $this->content_url . 'lazy/placeholder-' . $width . 'x' . $height . '.png' );
			} elseif ( ! is_file( $piip_path ) ) {
				// First try PIP generation via Imagick, as it is pretty efficient.
				if ( $this->imagick_support() ) {
					$placeholder = new Imagick();
					$placeholder->newimage( $width, $height, 'transparent' );
					$placeholder->setimageformat( 'PNG' );
					$placeholder->stripimage();
					$placeholder->writeimage( $piip_path );
					$placeholder->clear();
				}
				// If that didn't work, and we have a premium service, use the API to generate the slimmest PIP available.
				/* if ( $this->get_option( 'ewww_image_optimizer_cloud_key' ) && ! defined( 'EWWW_IMAGE_OPTIMIZER_DISABLE_API_PIP' ) ) { */
				if (
					! is_file( $piip_path ) &&
					( $this->parsing_exactdn || $this->get_option( 'ewww_image_optimizer_cloud_key' ) ) &&
					! defined( 'EWWW_IMAGE_OPTIMIZER_DISABLE_API_PIP' )
				) {
					$piip_location = "http://optimize.exactlywww.com/resize/lazy.php?width=$width&height=$height";
					$piip_response = wp_remote_get( $piip_location );
					if ( ! is_wp_error( $piip_response ) && is_array( $piip_response ) && ! empty( $piip_response['body'] ) ) {
						$this->debug_message( "retrieved PIP from API, storing to $piip_path" );
						file_put_contents( $piip_path, $piip_response['body'] );
						clearstatcache();
					}
				}
				// Last shot, use GD and then optimize it with optipng/pngout if available.
				if (
					! is_file( $piip_path ) &&
					$this->gd_support() &&
					$this->check_memory_available( $width * $height * 4.8 ) // 4.8 = 24-bit or 3 bytes per pixel multiplied by a factor of 1.6 for extra wiggle room.
				) {
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
				if ( defined( 'EIO_USE_EXTERNAL_PLACEHOLDERS' ) && EIO_USE_EXTERNAL_PLACEHOLDERS ) {
					return $this->content_url . 'lazy/placeholder-' . $width . 'x' . $height . '.png';
				}
				return 'data:image/png;base64,' . base64_encode( file_get_contents( $piip_path ) );
			}
			return $this->placeholder_src;
		}

		/**
		 * Allow the user to override the number of images to consider "above the fold".
		 *
		 * Any images that are encountered before the above the fold threshold is reached
		 * will be skipped by the lazy loader. Only applies to img elements, not CSS backgrounds.
		 *
		 * @param int $images The number of images that are above the fold.
		 * @return int The (potentially overriden) number of images.
		 */
		function override_lazy_fold( $images ) {
			if ( defined( 'EIO_LAZY_FOLD' ) ) {
				return (int) constant( 'EIO_LAZY_FOLD' );
			}
			return $images;
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
			if ( ! empty( $_POST['action'] ) && 'vc_get_vc_grid_data' === $_POST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
				$this->debug_message( 'allowing lazy on vc grid' );
				return true;
			}
			if ( ! empty( $_POST['action'] ) && 'Essential_Grid_Front_request_ajax' === $_POST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
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
			if ( ! $this->should_process_page() ) {
				return;
			}
			echo '<noscript><style>.lazyload[data-src]{display:none !important;}</style></noscript>';
			// And this allows us to lazy load external/internal CSS background images.
			echo '<style>.lazyload{background-image:none !important;}.lazyload:before{background-image:none !important;}</style>';
		}

		/**
		 * Load full lazysizes script when SCRIPT_DEBUG is enabled.
		 */
		function debug_script() {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			if ( ! $this->should_process_page() ) {
				return;
			}
			if ( ! defined( 'EIO_LL_FOOTER' ) ) {
				define( 'EIO_LL_FOOTER', true );
			}
			$plugin_file = constant( strtoupper( $this->prefix ) . 'PLUGIN_FILE' );
			wp_enqueue_script( 'eio-lazy-load-pre', plugins_url( '/includes/lazysizes-pre.js', $plugin_file ), array(), $this->version, EIO_LL_FOOTER );
			wp_enqueue_script( 'eio-lazy-load-uvh', plugins_url( '/includes/ls.unveilhooks.js', $plugin_file ), array(), $this->version, EIO_LL_FOOTER );
			wp_enqueue_script( 'eio-lazy-load-post', plugins_url( '/includes/lazysizes-post.js', $plugin_file ), array(), $this->version, EIO_LL_FOOTER );
			wp_enqueue_script( 'eio-lazy-load', plugins_url( '/includes/lazysizes.js', $plugin_file ), array(), $this->version, EIO_LL_FOOTER );
			if ( defined( strtoupper( $this->prefix ) . 'LAZY_PRINT' ) && constant( strtoupper( $this->prefix ) . 'LAZY_PRINT' ) ) {
				wp_enqueue_script( 'eio-lazy-load-print', plugins_url( '/includes/ls.print.js', $plugin_file ), array(), $this->version, EIO_LL_FOOTER );
			}
			$threshold = defined( 'EIO_LL_THRESHOLD' ) && EIO_LL_THRESHOLD ? EIO_LL_THRESHOLD : 0;
			wp_add_inline_script(
				'eio-lazy-load-pre',
				'var eio_lazy_vars = ' .
					wp_json_encode(
						array(
							'exactdn_domain' => ( $this->parsing_exactdn ? $this->exactdn_domain : '' ),
							'skip_autoscale' => ( defined( 'EIO_LL_AUTOSCALE' ) && ! EIO_LL_AUTOSCALE ? 1 : 0 ),
							'threshold'      => (int) $threshold > 50 ? (int) $threshold : 0,
						)
					)
					. ';',
				'before'
			);
			return;
		}

		/**
		 * Load minified lazysizes script.
		 */
		function min_script() {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			if ( ! $this->should_process_page() ) {
				return;
			}
			if ( ! defined( 'EIO_LL_FOOTER' ) ) {
				define( 'EIO_LL_FOOTER', true );
			}
			$plugin_file = constant( strtoupper( $this->prefix ) . 'PLUGIN_FILE' );
			wp_enqueue_script( 'eio-lazy-load', plugins_url( '/includes/lazysizes.min.js', $plugin_file ), array(), $this->version, EIO_LL_FOOTER );
			if ( defined( strtoupper( $this->prefix ) . 'LAZY_PRINT' ) && constant( strtoupper( $this->prefix ) . 'LAZY_PRINT' ) ) {
				wp_enqueue_script( 'eio-lazy-load-print', plugins_url( '/includes/ls.print.min.js', $plugin_file ), array(), $this->version, EIO_LL_FOOTER );
			}
			$threshold = defined( 'EIO_LL_THRESHOLD' ) && EIO_LL_THRESHOLD ? EIO_LL_THRESHOLD : 0;
			wp_add_inline_script(
				'eio-lazy-load',
				'var eio_lazy_vars = ' .
					wp_json_encode(
						array(
							'exactdn_domain' => ( $this->parsing_exactdn ? $this->exactdn_domain : '' ),
							'skip_autoscale' => ( defined( 'EIO_LL_AUTOSCALE' ) && ! EIO_LL_AUTOSCALE ? 1 : 0 ),
							'threshold'      => (int) $threshold > 50 ? (int) $threshold : 0,
						)
					)
					. ';',
				'before'
			);
			return;
		}
		/**
		 * Load minified inline version of lazysizes script.
		 */
		function inline_script() {
			if ( ! $this->should_process_page() ) {
				return;
			}
			$this->debug_message( 'inlining lazysizes script' );
			// Load up the minified script.
			$lazysizes_file = constant( strtoupper( $this->prefix ) . 'PLUGIN_PATH' ) . 'includes/lazysizes.min.js';
			if ( ! $this->is_file( $lazysizes_file ) ) {
				return;
			}
			$threshold        = defined( 'EIO_LL_THRESHOLD' ) && EIO_LL_THRESHOLD ? EIO_LL_THRESHOLD : 0;
			$lazysizes_script = 'var eio_lazy_vars = ' .
					wp_json_encode(
						array(
							'exactdn_domain' => ( $this->parsing_exactdn ? $this->exactdn_domain : '' ),
							'skip_autoscale' => ( defined( 'EIO_LL_AUTOSCALE' ) && ! EIO_LL_AUTOSCALE ? 1 : 0 ),
							'threshold'      => (int) $threshold > 50 ? (int) $threshold : 0,
						)
					)
					. ';';

			$lazysizes_script .= file_get_contents( $lazysizes_file );
			echo '<script data-cfasync="false" type="text/javascript" id="eio-lazy-load">' . $lazysizes_script . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( defined( strtoupper( $this->prefix ) . 'LAZY_PRINT' ) && constant( strtoupper( $this->prefix ) . 'LAZY_PRINT' ) ) {
				$lsprint_file = constant( strtoupper( $this->prefix ) . 'PLUGIN_PATH' ) . 'includes/ls.print.min.js';
				if ( $this->is_file( $lsprint_file ) ) {
					$lsprint_script = file_get_contents( $lsprint_file );
					echo '<script data-cfasync="false" id="eio-lazy-load-print">' . $lsprint_script . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
		}
	}
}
