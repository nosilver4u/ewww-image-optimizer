<?php
/**
 * Class and methods to implement ExactDN (based on Photon implementation).
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enables EWWW IO to filter the page content and replace image urls with ExactDN urls.
 */
class ExactDN {

	/**
	 * Allowed extensions are currently only images. Might add PDF/CSS/JS at some point.
	 *
	 * @access private
	 * @var array $extensions
	 */
	private $extensions = array(
		'gif',
		'jpg',
		'jpeg',
		'jpe',
		'png',
	);

	/**
	 * A list of image sizes registered for attachments.
	 *
	 * @access protected
	 * @var array $image_sizes
	 */
	protected static $image_sizes = null;

	/**
	 * Indicates if we are in full-page filtering mode.
	 *
	 * @access public
	 * @var bool $filtering_the_page
	 */
	public $filtering_the_page = false;

	/**
	 * Indicates if we are in content filtering mode.
	 *
	 * @access public
	 * @var bool $filtering_the_content
	 */
	public $filtering_the_content = false;

	/**
	 * List of permitted domains for ExactDN rewriting.
	 *
	 * @access public
	 * @var array $allowed_domains
	 */
	public $allowed_domains = array();

	/**
	 * The ExactDN domain/zone.
	 *
	 * @access private
	 * @var float $elapsed_time
	 */
	private $exactdn_domain = false;

	/**
	 * Allow us to track how much overhead ExactDN introduces.
	 *
	 * @access private
	 * @var float $elapsed_time
	 */
	private $elapsed_time = 0;

	/**
	 * Register (once) actions and filters for ExactDN. If you want to use this class, use the global.
	 */
	function __construct() {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		global $exactdn;
		if ( is_object( $exactdn ) ) {
			return 'you are doing it wrong';
		}

		// Make sure we have an ExactDN domain to use.
		if ( ! $this->setup() ) {
			return;
		}

		// Images in post content and galleries.
		add_filter( 'the_content', array( $this, 'filter_the_content' ), 999999 );
		// Start an output buffer before any output starts.
		add_action( 'template_redirect', array( $this, 'buffer_start' ), 1 );

		// Core image retrieval.
		if ( ! function_exists( 'aq_resize' ) ) {
			add_filter( 'image_downsize', array( $this, 'filter_image_downsize' ), 10, 3 );
		} else {
			ewwwio_debug_message( 'aq_resize detected, image_downsize filter disabled' );
		}

		// Overrides for admin-ajax images.
		add_filter( 'exactdn_admin_allow_image_downsize', array( $this, 'allow_admin_image_downsize' ), 10, 2 );
		// Overrides for "pass through" images.
		add_filter( 'exactdn_pre_args', array( $this, 'exactdn_remove_args' ), 10, 3 );

		// Responsive image srcset substitution.
		add_filter( 'wp_calculate_image_srcset', array( $this, 'filter_srcset_array' ), 1001, 5 );
		add_filter( 'wp_calculate_image_sizes', array( $this, 'filter_sizes' ), 1, 2 ); // Early so themes can still filter.

		// DNS prefetching.
		add_action( 'wp_head', array( $this, 'dns_prefetch' ) );

		// Helpers for manipulated images.
		if ( defined( 'EXACTDN_RECALC' ) && EXACTDN_RECALC ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'action_wp_enqueue_scripts' ), 9 );
		}

		// Find the "local" domain.
		$upload_dir          = wp_upload_dir( null, false );
		$this->upload_domain = defined( 'EXACTDN_LOCAL_DOMAIN' ) && EXACTDN_LOCAL_DOMAIN ? EXACTDN_LOCAL_DOMAIN : $this->parse_url( $upload_dir['baseurl'], PHP_URL_HOST );
		ewwwio_debug_message( "allowing images from here: $this->upload_domain" );
		$this->allowed_domains[] = $this->upload_domain;
		if ( strpos( $this->upload_domain, 'www' ) === false ) {
			$this->allowed_domains[] = 'www.' . $this->upload_domain;
		} else {
			$nonwww = ltrim( 'www.', $this->upload_domain );
			if ( $nonwww !== $this->upload_domain ) {
				$this->allowed_domains[] = $nonwww;
			}
		}
	}

	/**
	 * If ExactDN is enabled, validates and configures the ExactDN domain name.
	 */
	function setup() {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		// If we don't have a domain yet, go grab one.
		if ( ! $this->get_exactdn_domain() ) {
			ewwwio_debug_message( 'attempting to activate exactDN' );
			$exactdn_domain = $this->activate_site();
		} else {
			ewwwio_debug_message( 'grabbing existing exactDN domain' );
			$exactdn_domain = $this->get_exactdn_domain();
		}
		if ( ! $exactdn_domain ) {
			if ( get_option( 'ewww_image_optimizer_exactdn_failures' ) < 5 ) {
				$failures = (int) get_option( 'ewww_image_optimizer_exactdn_failures' );
				$failures++;
				ewwwio_debug_message( "could not activate ExactDN, failures: $failures" );
				update_option( 'ewww_image_optimizer_exactdn_failures', $failures );
				return false;
			}
			delete_option( 'ewww_image_optimizer_exactdn' );
			delete_site_option( 'ewww_image_optimizer_exactdn' );
			return false;
		}
		// If we have a domain, verify it.
		if ( $this->verify_domain( $exactdn_domain ) ) {
			ewwwio_debug_message( 'verified existing exactDN domain' );
			delete_option( 'ewww_image_optimizer_exactdn_failures' );
			$this->exactdn_domain = $exactdn_domain;
			ewwwio_debug_message( 'exactdn_domain: ' . $exactdn_domain );
			return true;
		} elseif ( get_option( 'ewww_image_optimizer_exactdn_failures' ) < 5 ) {
			$failures = (int) get_option( 'ewww_image_optimizer_exactdn_failures' );
			$failures++;
			ewwwio_debug_message( "could not verify existing exactDN domain, failures: $failures" );
			update_option( 'ewww_image_optimizer_exactdn_failures', $failures );
			$this->set_exactdn_checkin( time() + 3600 );
			return false;
		}
		delete_option( 'ewww_image_optimizer_exactdn_domain' );
		delete_site_option( 'ewww_image_optimizer_exactdn_domain' );
		return false;
	}

	/**
	 * Use the Site URL to get the zone domain.
	 */
	function activate_site() {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		$site_url = get_home_url();
		$url      = 'http://optimize.exactlywww.com/exactdn/activate.php';
		$ssl      = wp_http_supports( array( 'ssl' ) );
		if ( $ssl ) {
			$url = set_url_scheme( $url, 'https' );
		}
		$result = wp_remote_post( $url, array(
			'timeout' => 10,
			'body'    => array(
				'site_url' => $site_url,
			),
		) );
		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			ewwwio_debug_message( "exactdn activation request failed: $error_message" );
			return false;
		} elseif ( ! empty( $result['body'] ) && strpos( $result['body'], 'domain' ) !== false ) {
			$response = json_decode( $result['body'], true );
			if ( ! empty( $response['domain'] ) ) {
				return $this->set_exactdn_domain( $response['domain'] );
			}
		} elseif ( ! empty( $result['body'] ) && strpos( $result['body'], 'error' ) !== false ) {
			$response      = json_decode( $result['body'], true );
			$error_message = $response['error'];
			ewwwio_debug_message( "exactdn activation request failed: $error_message" );
			return false;
		}
		return false;
	}

	/**
	 * Verify the ExactDN domain.
	 *
	 * @param string $domain The ExactDN domain to verify.
	 * @return bool Whether the domain is still valid.
	 */
	function verify_domain( $domain ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		// Check the time, to see how long it has been since we verified the domain.
		$last_checkin = $this->get_exactdn_checkin();
		if ( ! empty( $last_checkin ) && $last_checkin > time() ) {
			ewwwio_debug_message( 'not time yet' );
			return true;
		}
		$url = 'http://optimize.exactlywww.com/exactdn/verify.php';
		$ssl = wp_http_supports( array( 'ssl' ) );
		if ( $ssl ) {
			$url = set_url_scheme( $url, 'https' );
		}
		$result = wp_remote_post( $url, array(
			'timeout' => 10,
			'body'    => array(
				'alias' => $domain,
			),
		) );
		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			ewwwio_debug_message( "exactdn verification request failed: $error_message" );
			return false;
		} elseif ( ! empty( $result['body'] ) && strpos( $result['body'], 'error' ) === false ) {
			$response = json_decode( $result['body'], true );
			if ( ! empty( $response['success'] ) ) {
				$this->set_exactdn_checkin( time() + 86400 );
				return true;
			}
		} elseif ( ! empty( $result['body'] ) ) {
			$response      = json_decode( $result['body'], true );
			$error_message = $response['error'];
			ewwwio_debug_message( "exactdn activation request failed: $error_message" );
			return false;
		}
		return false;
	}

	/**
	 * Validate the ExactDN domain.
	 *
	 * @param string $domain The unverified ExactDN domain.
	 * @return string The validated ExactDN domain.
	 */
	function sanitize_domain( $domain ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		if ( ! $domain ) {
			return;
		}
		if ( strlen( $domain ) > 80 ) {
			ewwwio_debug_message( "$domain too long" );
			return false;
		}
		if ( ! preg_match( '#^[A-Za-z0-9\.\-]+$#', $domain ) ) {
			ewwwio_debug_message( "$domain has bad characters" );
			return false;
		}
		return $domain;
	}

	/**
	 * Get the ExactDN domain name to use.
	 *
	 * @return string The ExactDN domain name for this site or network.
	 */
	function get_exactdn_domain() {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		if ( defined( 'EXACTDN_DOMAIN' ) && EXACTDN_DOMAIN ) {
			return $this->sanitize_domain( EXACTDN_DOMAIN );
		}
		if ( is_multisite() ) {
			if ( ! SUBDOMAIN_INSTALL ) {
				return $this->sanitize_domain( get_site_option( 'ewww_image_optimizer_exactdn_domain' ) );
			}
		}
		return $this->sanitize_domain( get_option( 'ewww_image_optimizer_exactdn_domain' ) );
	}

	/**
	 * Get the ExactDN last check-in time.
	 *
	 * @return int The last time we verified the ExactDN domain.
	 */
	function get_exactdn_checkin() {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		if ( defined( 'EXACTDN_DOMAIN' ) && EXACTDN_DOMAIN ) {
			return (int) get_option( 'ewww_image_optimizer_exactdn_validation' );
		}
		if ( is_multisite() ) {
			if ( ! SUBDOMAIN_INSTALL ) {
				return (int) get_site_option( 'ewww_image_optimizer_exactdn_validation' );
			}
		}
		return (int) get_option( 'ewww_image_optimizer_exactdn_validation' );
	}

	/**
	 * Set the ExactDN domain name to use.
	 *
	 * @param string $domain The ExactDN domain name for this site or network.
	 */
	function set_exactdn_domain( $domain ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		if ( defined( 'EXACTDN_DOMAIN' ) && $this->sanitize_domain( EXACTDN_DOMAIN ) ) {
			return true;
		}
		$domain = $this->sanitize_domain( $domain );
		if ( ! $domain ) {
			return false;
		}
		if ( is_multisite() ) {
			if ( ! SUBDOMAIN_INSTALL ) {
				update_site_option( 'ewww_image_optimizer_exactdn_domain', $domain );
				return $domain;
			}
		}
		update_option( 'ewww_image_optimizer_exactdn_domain', $domain );
		return $domain;
	}

	/**
	 * Set the last check-in time for ExactDN.
	 *
	 * @param int $time The last time we verified the ExactDN domain.
	 */
	function set_exactdn_checkin( $time ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		if ( defined( 'EXACTDN_DOMAIN' ) && EXACTDN_DOMAIN ) {
			return update_option( 'ewww_image_optimizer_exactdn_validation', $time );
		}
		if ( is_multisite() ) {
			if ( ! SUBDOMAIN_INSTALL ) {
				return update_site_option( 'ewww_image_optimizer_exactdn_validation', $time );
			}
		}
		return update_option( 'ewww_image_optimizer_exactdn_validation', $time );
	}

	/**
	 * Match all images and any relevant <a> tags in a block of HTML.
	 *
	 * @param string $content Some HTML.
	 * @return array An array of $images matches, where $images[0] is
	 *         an array of full matches, and the link_url, img_tag,
	 *         and img_url keys are arrays of those matches.
	 */
	function parse_images_from_html( $content ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		$images = array();

		if ( preg_match_all( '#(?:<a[^>]+?href=["|\'](?P<link_url>[^\s]+?)["|\'][^>]*?>\s*)?(?P<img_tag><img[^>]*?\s+?src=["|\'](?P<img_url>[^\s]+?)["|\'].*?>){1}(?:\s*</a>)?#is', $content, $images ) ) {
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
	 * Try to determine height and width from strings WP appends to resized image filenames.
	 *
	 * @param string $src The image URL.
	 * @return array An array consisting of width and height.
	 */
	function parse_dimensions_from_filename( $src ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		$width_height_string = array();
		ewwwio_debug_message( "looking for dimensions in $src" );
		if ( preg_match( '#-(\d+)x(\d+)(@2x)?\.(?:' . implode( '|', $this->extensions ) . '){1}(?:\?.+)?$#i', $src, $width_height_string ) ) {
			$width  = (int) $width_height_string[1];
			$height = (int) $width_height_string[2];

			if ( strpos( $src, '@2x' ) ) {
				$width  = 2 * $width;
				$height = 2 * $height;
			}
			if ( $width && $height ) {
				ewwwio_debug_message( "found w$width h$height" );
				return array( $width, $height );
			}
		}
		return array( false, false );
	}

	/**
	 * Get $content_width, with a filter.
	 *
	 * @return bool|string The content width, if set. Default false.
	 */
	function get_content_width() {
		$content_width = isset( $GLOBALS['content_width'] ) ? $GLOBALS['content_width'] : false;
		/**
		 * Filter the Content Width value.
		 *
		 * @param string $content_width Content Width value.
		 */
		return apply_filters( 'exactdn_content_width', $content_width );
	}

	/**
	 * Starts an output buffer and registers the callback function to do ExactDN url replacement.
	 */
	function buffer_start() {
		ob_start( array( $this, 'filter_the_page' ) );
	}

	/**
	 * Identify images in page content, and if images are local (uploaded to the current site), pass through ExactDN.
	 *
	 * @param string $content The page/post content.
	 * @return string The content with ExactDN image urls.
	 */
	function filter_the_page( $content ) {
		$this->filtering_the_page = true;

		$content = $this->filter_the_content( $content );

		$this->filtering_the_page = false;
		ewwwio_debug_message( "parsing page took $this->elapsed_time seconds" );
		return $content;
	}

	/**
	 * Identify images in the content, and if images are local (uploaded to the current site), pass through ExactDN.
	 *
	 * @param string $content The page/post content.
	 * @return string The content with ExactDN image urls.
	 */
	function filter_the_content( $content ) {
		$started = microtime( true );
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		$images = $this->parse_images_from_html( $content );

		if ( ! empty( $images ) ) {
			ewwwio_debug_message( 'we have images to parse' );
			$content_width = false;
			if ( ! $this->filtering_the_page ) {
				$this->filtering_the_content = true;
				ewwwio_debug_message( 'filtering the content' );
				$content_width = $this->get_content_width();
			}
			$resize_existing = defined( 'EXACTDN_RESIZE_EXISTING' ) && EXACTDN_RESIZE_EXISTING;

			$image_sizes = $this->image_sizes();

			foreach ( $images[0] as $index => $tag ) {
				// Default to resize, though fit may be used in certain cases where a dimension cannot be ascertained.
				$transform = 'resize';

				// Start with a clean attachment ID each time.
				$attachment_id = false;

				// Flag if we need to munge a fullsize URL.
				$fullsize_url = false;

				// Identify image source.
				$src      = $images['img_url'][ $index ];
				$src_orig = $images['img_url'][ $index ];
				ewwwio_debug_message( $src );

				/**
				 * Allow specific images to be skipped by ExactDN.
				 *
				 * @param bool false Should ExactDN ignore this image. Default false.
				 * @param string $src Image URL.
				 * @param string $tag Image HTML Tag.
				 */
				if ( apply_filters( 'exactdn_skip_image', false, $src, $tag ) ) {
					continue;
				}

				ewwwio_debug_message( 'made it passed the filters' );
				// Support Lazy Load plugins.
				// Don't modify $tag yet as we need unmodified version later.
				if ( preg_match( '#data-lazy-src=["|\'](.+?)["|\']#i', $images['img_tag'][ $index ], $lazy_load_src ) ) {
					$placeholder_src      = $src;
					$placeholder_src_orig = $src;
					$src                  = $lazy_load_src[1];
					$src_orig             = $lazy_load_src[1];
				} elseif ( preg_match( '#data-lazy-original=["|\'](.+?)["|\']#i', $images['img_tag'][ $index ], $lazy_load_src ) ) {
					$placeholder_src      = $src;
					$placeholder_src_orig = $src;
					$src                  = $lazy_load_src[1];
					$src_orig             = $lazy_load_src[1];
				}

				// Check if image URL should be used with ExactDN.
				if ( $this->validate_image_url( $src ) ) {
					ewwwio_debug_message( 'url validated' );
					// Find the width and height attributes.
					$width  = false;
					$height = false;

					// First, check the image tag.
					if ( preg_match( '#width=["|\']?([\d%]+)["|\']?#i', $images['img_tag'][ $index ], $width_string ) ) {
						$width = $width_string[1];
					}
					if ( preg_match( '#max-width:\s?(\d+)px#', $images['img_tag'][ $index ], $max_width_string ) ) {
						if ( $max_width_string[1] && ( ! $width || $max_width_string[1] < $width ) ) {
							$width = $max_width_string[1];
						}
					}
					if ( preg_match( '#height=["|\']?([\d%]+)["|\']?#i', $images['img_tag'][ $index ], $height_string ) ) {
						$height = $height_string[1];
					}

					// Can't pass both a relative width and height, so unset the dimensions in favor of not breaking the horizontal layout.
					if ( false !== strpos( $width, '%' ) && false !== strpos( $height, '%' ) ) {
						$width  = false;
						$height = false;
					}

					// Detect WP registered image size from HTML class.
					if ( preg_match( '#class=["|\']?[^"\']*size-([^"\'\s]+)[^"\']*["|\']?#i', $images['img_tag'][ $index ], $size ) ) {
						$size = array_pop( $size );

						ewwwio_debug_message( "detected $size" );
						if ( false === $width && false === $height && 'full' != $size && array_key_exists( $size, $image_sizes ) ) {
							$width     = (int) $image_sizes[ $size ]['width'];
							$height    = (int) $image_sizes[ $size ]['height'];
							$transform = $image_sizes[ $size ]['crop'] ? 'resize' : 'fit';
						}
					} else {
						unset( $size );
					}

					list( $filename_width, $filename_height ) = $this->parse_dimensions_from_filename( $src );
					// WP Attachment ID, if uploaded to this site.
					preg_match( '#class=["|\']?[^"\']*wp-image-([\d]+)[^"\']*["|\']?#i', $images['img_tag'][ $index ], $attachment_id );
					if ( ! ewww_image_optimizer_get_option( 'exactdn_prevent_db_queries' ) && empty( $attachment_id ) ) {
						ewwwio_debug_message( 'looking for attachment id' );
						$attachment_id = array( attachment_url_to_postid( $src ) );
					}
					if ( ! ewww_image_optimizer_get_option( 'exactdn_prevent_db_queries' ) && ! empty( $attachment_id ) ) {
						ewwwio_debug_message( 'using attachment id to get source image' );
						$attachment_id = intval( array_pop( $attachment_id ) );

						if ( $attachment_id ) {
							ewwwio_debug_message( "detected attachment $attachment_id" );
							$attachment = get_post( $attachment_id );

							// Basic check on returned post object.
							if ( is_object( $attachment ) && ! is_wp_error( $attachment ) && 'attachment' == $attachment->post_type ) {
								$src_per_wp = wp_get_attachment_image_src( $attachment_id, 'full' );

								if ( $this->validate_image_url( $src_per_wp[0] ) ) {
									ewwwio_debug_message( "detected $width filenamew $filename_width" );
									if ( $resize_existing || ( $width && $filename_width != $width ) ) {
										ewwwio_debug_message( 'resizing existing or width does not match' );
										$src = $src_per_wp[0];
									}
									$fullsize_url = true;

									// Prevent image distortion if a detected dimension exceeds the image's natural dimensions.
									if ( ( false !== $width && $width > $src_per_wp[1] ) || ( false !== $height && $height > $src_per_wp[2] ) ) {
										$width  = false === $width ? false : min( $width, $src_per_wp[1] );
										$height = false === $height ? false : min( $height, $src_per_wp[2] );
									}

									// If no width and height are found, max out at source image's natural dimensions.
									// Otherwise, respect registered image sizes' cropping setting.
									if ( false === $width && false === $height ) {
										$width     = $src_per_wp[1];
										$height    = $src_per_wp[2];
										$transform = 'fit';
									} elseif ( isset( $size ) && array_key_exists( $size, $image_sizes ) && isset( $image_sizes[ $size ]['crop'] ) ) {
										$transform = (bool) $image_sizes[ $size ]['crop'] ? 'resize' : 'fit';
									}
								}
							} else {
								unset( $attachment_id );
								unset( $attachment );
							}
						}
					}

					// If width is available, constrain to $content_width.
					if ( false !== $width && false === strpos( $width, '%' ) && is_numeric( $content_width ) ) {
						if ( $width > $content_width && false !== $height && false === strpos( $height, '%' ) ) {
							ewwwio_debug_message( 'constraining to content width' );
							$height = round( ( $content_width * $height ) / $width );
							$width  = $content_width;
						} elseif ( $width > $content_width ) {
							ewwwio_debug_message( 'constraining to content width' );
							$width = $content_width;
						}
					}

					// Set a width if none is found and $content_width is available.
					// If width is set in this manner and height is available, use `fit` instead of `resize` to prevent skewing.
					if ( false === $width && is_numeric( $content_width ) ) {
						$width = (int) $content_width;

						if ( false !== $height ) {
							$transform = 'fit';
						}
					}

					// Detect if image source is for a custom-cropped thumbnail and prevent further URL manipulation.
					if ( ! $fullsize_url && preg_match_all( '#-e[a-z0-9]+(-\d+x\d+)?\.(' . implode( '|', $this->extensions ) . '){1}$#i', basename( $src ), $filename ) ) {
						$fullsize_url = true;
					}

					// Build array of ExactDN args and expose to filter before passing to ExactDN URL function.
					$args = array();

					if ( false !== $width && false !== $height && false === strpos( $width, '%' ) && false === strpos( $height, '%' ) ) {
						$args[ $transform ] = $width . ',' . $height;
					} elseif ( false !== $width ) {
						$args['w'] = $width;
					} elseif ( false !== $height ) {
						$args['h'] = $height;
					}

					if ( ! $resize_existing && ( ! $width || $filename_width == $width ) ) {
						ewwwio_debug_message( 'preventing resize' );
						$args = array();
					} elseif ( ! $fullsize_url ) {
						// Build URL, first maybe removing WP's resized string so we pass the original image to ExactDN (for higher quality).
						$src = $this->strip_image_dimensions_maybe( $src );
					}

					if ( ! ewww_image_optimizer_get_option( 'exactdn_prevent_db_queries' ) && ! empty( $attachment_id ) ) {
						ewwwio_debug_message( 'using attachment id to check smart crop' );
						$args = $this->maybe_smart_crop( $args, $attachment_id );
					}

					/**
					 * Filter the array of ExactDN arguments added to an image.
					 * By default, only includes width and height values.
					 *
					 * @param array $args Array of ExactDN Arguments.
					 * @param array $args {
					 *     Array of image details.
					 *
					 *     @type $tag Image tag (Image HTML output).
					 *     @type $src Image URL.
					 *     @type $src_orig Original Image URL.
					 *     @type $width Image width.
					 *     @type $height Image height.
					 * }
					 */
					$args = apply_filters( 'exactdn_post_image_args', $args, compact( 'tag', 'src', 'src_orig', 'width', 'height' ) );
					ewwwio_debug_message( "width $width" );
					ewwwio_debug_message( "height $height" );
					ewwwio_debug_message( "transform $transform" );

					$exactdn_url = $this->generate_url( $src, $args );
					ewwwio_debug_message( "new url $exactdn_url" );

					// Modify image tag if ExactDN function provides a URL
					// Ensure changes are only applied to the current image by copying and modifying the matched tag, then replacing the entire tag with our modified version.
					if ( $src != $exactdn_url ) {
						$new_tag = $tag;

						// If present, replace the link href with an ExactDN URL for the full-size image.
						if ( ! empty( $images['link_url'][ $index ] ) && $this->validate_image_url( $images['link_url'][ $index ] ) ) {
							$new_tag = preg_replace( '#(href=["|\'])' . $images['link_url'][ $index ] . '(["|\'])#i', '\1' . $this->generate_url( $images['link_url'][ $index ] ) . '\2', $new_tag, 1 );
						}

						// Insert new image src into the srcset as well, if we have a width.
						if ( false !== $width && false === strpos( $width, '%' ) ) {
							ewwwio_debug_message( 'checking to see if srcset width already exists' );
							$srcset_url = $exactdn_url . ' ' . (int) $width . 'w, ';
							if ( false === strpos( $tag, $width . 'w' ) ) {
								// For double-quotes...
								$new_tag = str_replace( 'srcset="', 'srcset="' . $srcset_url, $new_tag );
								// and for single-quotes.
								$new_tag = str_replace( "srcset='", "srcset='" . $srcset_url, $new_tag );
							}
						}

						// Supplant the original source value with our ExactDN URL.
						$exactdn_url = esc_url( $exactdn_url );
						$new_tag     = str_replace( $src_orig, $exactdn_url, $new_tag );

						// If Lazy Load is in use, pass placeholder image through ExactDN.
						if ( isset( $placeholder_src ) && $this->validate_image_url( $placeholder_src ) ) {
							$placeholder_src = $this->generate_url( $placeholder_src );

							if ( $placeholder_src != $placeholder_src_orig ) {
								$new_tag = str_replace( $placeholder_src_orig, esc_url( $placeholder_src ), $new_tag );
							}

							unset( $placeholder_src );
						}

						// Enable image dimension recalculation via wp-config.php.
						if ( defined( 'EXACTDN_RECALC' ) && EXACTDN_RECALC ) {
							// Remove the width and height arguments from the tag to prevent distortion.
							$new_tag = preg_replace( '#(?<=\s)(width|height)=["|\']?[\d%]+["|\']?\s?#i', '', $new_tag );

							// Tag an image for dimension checking (via JS).
							$new_tag = preg_replace( '#(\s?/)?>(\s*</a>)?$#i', ' data-recalc-dims="1"\1>\2', $new_tag );
						}
						// Replace original tag with modified version.
						$content = str_replace( $tag, $new_tag, $content );
					}
				} elseif ( ! preg_match( '#data-lazy-(original|src)=#i', $images['img_tag'][ $index ] ) && $this->validate_image_url( $src, true ) ) {
					ewwwio_debug_message( 'found a potential exactdn src url to insert into srcset' );
					// Find the width attribute.
					$width = false;
					// First, check the image tag.
					if ( preg_match( '#width=["|\']?([\d%]+)["|\']?#i', $tag, $width_string ) ) {
						$width = $width_string[1];
						ewwwio_debug_message( 'found the width' );
						// Insert new image src into the srcset as well, if we have a width.
						if (
							false !== $width &&
							false === strpos( $width, '%' ) &&
							false !== strpos( $src, $width ) &&
							(
								false !== strpos( $src, 'exactdn.com' ) ||
								false !== strpos( $src, 'exactdn.net' ) ||
								false !== strpos( $src, 'exactcdn.com' ) ||
								false !== strpos( $src, 'exactcdn.net' )
							)
						) {
							$new_tag     = $tag;
							$exactdn_url = $src;
							ewwwio_debug_message( 'checking to see if srcset width already exists' );
							$srcset_url = $exactdn_url . ' ' . (int) $width . 'w, ';
							if ( false === strpos( $tag, $width . 'w' ) ) {
								ewwwio_debug_message( 'src not in srcset, adding' );
								// For double-quotes...
								$new_tag = str_replace( 'srcset="', 'srcset="' . $srcset_url, $new_tag );
								// and for single-quotes.
								$new_tag = str_replace( "srcset='", "srcset='" . $srcset_url, $new_tag );
								// Replace original tag with modified version.
								$content = str_replace( $tag, $new_tag, $content );
							}
						}
					}
				} // End if().
			} // End foreach().
			if ( $this->filtering_the_page && ewww_image_optimizer_get_option( 'exactdn_all_the_things' ) ) {
				ewwwio_debug_message( 'rewriting all other wp_content urls' );
				if ( $this->exactdn_domain && $this->upload_domain ) {
					$escaped_upload_domain = str_replace( '.', '\.', $this->upload_domain );
					ewwwio_debug_message( $escaped_upload_domain );
					// Pre-empt rewriting of wp-includes and wp-content if the extension is php/ashx by using a temporary placeholder.
					$content = preg_replace( '#(https?)://' . $escaped_upload_domain . '([^"\'?>]+?)?/wp-content/([^"\'?>]+?)\.(php|ashx)#i', '$1://' . $this->upload_domain . '$2/?wpcontent-bypass?/$3.$4', $content );
					$content = preg_replace( '#(https?)://' . $escaped_upload_domain . '/([^"\'?>]+?)?wp-(includes|content)#i', '$1://' . $this->exactdn_domain . '/$2wp-$3', $content );
					$content = str_replace( '?wpcontent-bypass?', 'wp-content', $content );
					$content = preg_replace( '#(concatemoji":"https?:\\\/\\\/)' . $escaped_upload_domain . '([^"\'?>]+?)wp-emoji-release.min.js\?ver=(\w)#', '$1' . $this->exactdn_domain . '$2wp-emoji-release.min.js?ver=$3', $content );
				}
			}
		} // End if();
		ewwwio_debug_message( 'done parsing page' );
		$this->filtering_the_content = false;

		$elapsed_time = microtime( true ) - $started;
		ewwwio_debug_message( "parsing the_content took $elapsed_time seconds" );
		$this->elapsed_time += microtime( true ) - $started;
		return $content;
	}

	/**
	 * Allow resizing of images for some admin-ajax requests.
	 *
	 * @param bool  $allow Will normally be false, unless already modified by another function.
	 * @param array $image Bunch of information about the image, but we don't care about that here.
	 * @return bool True if it's an allowable admin-ajax request, false for all other admin requests.
	 */
	function allow_admin_image_downsize( $allow, $image ) {
		if ( ! wp_doing_ajax() ) {
			return $allow;
		}
		if ( ! empty( $_POST['action'] ) && 'eddvbugm_viewport_downloads' == $_POST['action'] ) {
			return true;
		}
		if ( ! empty( $_POST['action'] ) && 'vc_get_vc_grid_data' == $_POST['action'] ) {
			return true;
		}
		if ( ! empty( $_POST['action'] ) && 'Essential_Grid_Front_request_ajax' == $_POST['action'] ) {
			return true;
		}
		return $allow;
	}

	/**
	 * Filter post thumbnail image retrieval, passing images through ExactDN.
	 *
	 * @param array|bool   $image Defaults to false, but may be a url if another plugin/theme has already filtered the value.
	 * @param int          $attachment_id The ID number for the image attachment.
	 * @param string|array $size The name of the image size or an array of width and height. Default 'medium'.
	 * @uses is_admin, apply_filters, wp_get_attachment_url, this::validate_image_url, this::image_sizes, this::generate_url
	 * @filter image_downsize
	 * @return string|bool
	 */
	function filter_image_downsize( $image, $attachment_id, $size ) {
		$started = microtime( true );
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		// Don't foul up the admin side of things, unless a plugin wants to.
		if ( is_admin() &&
			/**
			 * Provide plugins a way of running ExactDN for images in the WordPress Dashboard (wp-admin).
			 *
			 * Note: enabling this will result in ExactDN URLs added to your post content, which could make migrations across domains (and off ExactDN) a bit more challenging.
			 *
			 * @param bool false Stop ExactDN from being run on the Dashboard. Default to false.
			 * @param array $args {
			 *     Array of image details.
			 *
			 *     @type array|bool  $image Image URL or false.
			 *     @type int          $attachment_id Attachment ID of the image.
			 *     @type array|string $size Image size. Can be a string (name of the image size, e.g. full) or an array of height and width.
			 * }
			 */
			false === apply_filters( 'exactdn_admin_allow_image_downsize', false, compact( 'image', 'attachment_id', 'size' ) )
		) {
			return $image;
		}

		/**
		 * Provide plugins a way of preventing ExactDN from being applied to images retrieved from WordPress Core.
		 *
		 * @param bool false Stop ExactDN from being applied to the image. Default to false.
		 * @param array $args {
		 *     Array of image details.
		 *
		 *     @type string|bool  $image Image URL or false.
		 *     @type int          $attachment_id Attachment ID of the image.
		 *     @type array|string $size Image size. Can be a string (name of the image size, e.g. full) or an array of height and width.
		 * }
		 */
		if ( apply_filters( 'exactdn_override_image_downsize', false, compact( 'image', 'attachment_id', 'size' ) ) ) {
			return $image;
		}

		if ( function_exists( 'aq_resize' ) ) {
			ewwwio_debug_message( 'aq_resize detected, image_downsize filter disabled' );
			return $image;
		}

		if ( $this->filtering_the_content || $this->filtering_the_page ) {
			ewwwio_debug_message( 'end image_downsize early' );
			return $image;
		}

		// Get the image URL and proceed with ExactDN replacement if successful.
		$image_url = wp_get_attachment_url( $attachment_id );
		ewwwio_debug_message( $image_url );
		ewwwio_debug_message( $attachment_id );
		if ( is_string( $size ) || is_int( $size ) ) {
			ewwwio_debug_message( $size );
		} elseif ( is_array( $size ) ) {
			foreach ( $size as $dimension ) {
				ewwwio_debug_message( 'dimension: ' . $dimension );
			}
		}
		// Set this to true later when we know we have size meta.
		$has_size_meta = false;

		if ( $image_url ) {
			// Check if image URL should be used with ExactDN.
			if ( ! $this->validate_image_url( $image_url ) ) {
				return $image;
			}

			$intermediate    = true; // For the fourth array item returned by the image_downsize filter.
			$resize_existing = defined( 'EXACTDN_RESIZE_EXISTING' ) && EXACTDN_RESIZE_EXISTING;

			// If an image is requested with a size known to WordPress, use that size's settings with ExactDN.
			if ( is_string( $size ) && array_key_exists( $size, $this->image_sizes() ) ) {
				$image_args = $this->image_sizes();
				$image_args = $image_args[ $size ];

				$exactdn_args = array();

				$image_meta = image_get_intermediate_size( $attachment_id, $size );

				// 'full' is a special case: We need consistent data regardless of the requested size.
				if ( 'full' === $size ) {
					$image_meta   = wp_get_attachment_metadata( $attachment_id );
					$intermediate = false;
				} elseif ( ! $image_meta ) {
					// If we still don't have any image meta at this point, it's probably from a custom thumbnail size
					// for an image that was uploaded before the custom image was added to the theme. Try to determine the size manually.
					$image_meta = wp_get_attachment_metadata( $attachment_id );

					if ( isset( $image_meta['width'], $image_meta['height'] ) ) {
						$image_resized = image_resize_dimensions( $image_meta['width'], $image_meta['height'], $image_args['width'], $image_args['height'], $image_args['crop'] );
						if ( $image_resized ) { // This could be false when the requested image size is larger than the full-size image.
							$image_meta['width']  = $image_resized[6];
							$image_meta['height'] = $image_resized[7];
						}
					}
				}

				if ( isset( $image_meta['width'], $image_meta['height'] ) ) {
					$image_args['width']  = $image_meta['width'];
					$image_args['height'] = $image_meta['height'];

					list( $image_args['width'], $image_args['height'] ) = image_constrain_size_for_editor( $image_args['width'], $image_args['height'], $size, 'display' );

					$has_size_meta = true;
				}

				// Expose determined arguments to a filter before passing to ExactDN.
				$transform = $image_args['crop'] ? 'resize' : 'fit';

				// Check specified image dimensions and account for possible zero values; ExactDN fails to resize if a dimension is zero.
				if ( 0 == $image_args['width'] || 0 == $image_args['height'] ) {
					if ( 0 == $image_args['width'] && 0 < $image_args['height'] ) {
						$exactdn_args['h'] = $image_args['height'];
					} elseif ( 0 == $image_args['height'] && 0 < $image_args['width'] ) {
						$exactdn_args['w'] = $image_args['width'];
					}
				} else {
					$image_meta = wp_get_attachment_metadata( $attachment_id );
					if ( ( 'resize' === $transform ) && $image_meta ) {
						if ( isset( $image_meta['width'], $image_meta['height'] ) ) {
							// Lets make sure that we don't upscale images since wp never upscales them as well.
							$smaller_width  = ( ( $image_meta['width'] < $image_args['width'] ) ? $image_meta['width'] : $image_args['width'] );
							$smaller_height = ( ( $image_meta['height'] < $image_args['height'] ) ? $image_meta['height'] : $image_args['height'] );

							$exactdn_args[ $transform ] = $smaller_width . ',' . $smaller_height;
						}
					} else {
						$exactdn_args[ $transform ] = $image_args['width'] . ',' . $image_args['height'];
					}
				}

				if ( ! empty( $image_meta['sizes'] ) && 'full' !== $size && ! empty( $image_meta['sizes'][ $size ]['file'] ) ) {
					$image_url_basename = wp_basename( $image_url );
					$intermediate_url   = str_replace( $image_url_basename, $image_meta['sizes'][ $size ]['file'], $image_url );

					list( $filename_width, $filename_height ) = $this->parse_dimensions_from_filename( $intermediate_url );
					if ( $filename_width && $filename_height && $image_args['width'] === $filename_width && $image_args['height'] === $filename_height ) {
						$image_url = $intermediate_url;
					} else {
						$resize_existing = true;
					}
				} else {
					$resize_existing = true;
				}

				$exactdn_args = $this->maybe_smart_crop( $exactdn_args, $attachment_id, $image_meta );

				/**
				 * Filter the ExactDN arguments added to an image, when that image size is a string.
				 * Image size will be a string (e.g. "full", "medium") when it is known to WordPress.
				 *
				 * @param array $exactdn_args ExactDN arguments.
				 * @param array  $args {
				 *     Array of image details.
				 *
				 *     @type array  $image_args Image arguments (width, height, crop).
				 *     @type string $image_url Image URL.
				 *     @type int    $attachment_id Attachment ID of the image.
				 *     @type string $size Image size name.
				 *     @type string $transform Value can be resize or fit.
				 * }
				 */
				$exactdn_args = apply_filters( 'exactdn_image_downsize_string', $exactdn_args, compact( 'image_args', 'image_url', 'attachment_id', 'size', 'transform' ) );

				// Generate ExactDN URL.
				if ( ! $resize_existing ) {
					$image = array(
						$this->generate_url( $image_url ),
						$has_size_meta ? $image_args['width'] : false,
						$has_size_meta ? $image_args['height'] : false,
						$intermediate,
					);
				} else {
					$image = array(
						$this->generate_url( $image_url, $exactdn_args ),
						$has_size_meta ? $image_args['width'] : false,
						$has_size_meta ? $image_args['height'] : false,
						$intermediate,
					);
				}
			} elseif ( is_array( $size ) ) {
				// Pull width and height values from the provided array, if possible.
				$width  = isset( $size[0] ) ? (int) $size[0] : false;
				$height = isset( $size[1] ) ? (int) $size[1] : false;

				// Don't bother if necessary parameters aren't passed.
				if ( ! $width || ! $height ) {
					return $image;
				}

				$image_meta = wp_get_attachment_metadata( $attachment_id );
				if ( isset( $image_meta['width'], $image_meta['height'] ) ) {
					$image_resized = image_resize_dimensions( $image_meta['width'], $image_meta['height'], $width, $height );

					if ( $image_resized ) { // This could be false when the requested image size is larger than the full-size image.
						$width  = $image_resized[6];
						$height = $image_resized[7];
					} else {
						$width  = $image_meta['width'];
						$height = $image_meta['height'];
					}
					$has_size_meta = true;
				}

				list( $width, $height ) = image_constrain_size_for_editor( $width, $height, $size );

				// Expose arguments to a filter before passing to ExactDN.
				$exactdn_args = array(
					'fit' => $width . ',' . $height,
				);

				$exactdn_args = $this->maybe_smart_crop( $exactdn_args, $attachment_id, $image_meta );

				/**
				 * Filter the ExactDN arguments added to an image, when the image size is an array of height and width values.
				 *
				 * @param array $exactdn_args ExactDN arguments/parameters.
				 * @param array $args {
				 *     Array of image details.
				 *
				 *     @type int    $width Image width.
				 *     @type int    $height Image height.
				 *     @type string $image_url Image URL.
				 *     @type int    $attachment_id Attachment ID of the image.
				 * }
				 */
				$exactdn_args = apply_filters( 'exactdn_image_downsize_array', $exactdn_args, compact( 'width', 'height', 'image_url', 'attachment_id' ) );

				// Generate ExactDN URL.
				$image = array(
					$this->generate_url( $image_url, $exactdn_args ),
					$has_size_meta ? $width : false,
					$has_size_meta ? $height : false,
					$intermediate,
				);
			}
		}
		if ( ! empty( $image[0] ) && is_string( $image[0] ) ) {
			ewwwio_debug_message( $image[0] );
		}
		ewwwio_debug_message( 'end image_downsize' );
		$elapsed_time = microtime( true ) - $started;
		ewwwio_debug_message( "parsing image_downsize took $elapsed_time seconds" );
		$this->elapsed_time += microtime( true ) - $started;
		return $image;
	}

	/**
	 * Filters an array of image `srcset` values, replacing each URL with its ExactDN equivalent.
	 *
	 * @param array  $sources An array of image urls and widths.
	 * @param array  $size_array Array of width and height values in pixels.
	 * @param string $image_src The 'src' of the image.
	 * @param array  $image_meta The image metadata as returned by 'wp_get_attachment_metadata()'.
	 * @param int    $attachment_id Image attachment ID or 0.
	 * @uses this::validate_image_url, this::generate_url, this::parse_from_filename
	 * @uses this::strip_image_dimensions_maybe, this::get_content_width
	 * @return array An array of ExactDN image urls and widths.
	 */
	public function filter_srcset_array( $sources = array(), $size_array = array(), $image_src = '', $image_meta = array(), $attachment_id = 0 ) {
		$started = microtime( true );
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		// Don't foul up the admin side of things, unless a plugin wants to.
		if ( is_admin() &&
			/**
			 * Provide plugins a way of running ExactDN for images in the WordPress Dashboard (wp-admin).
			 *
			 * @param bool false Stop ExactDN from being run on the Dashboard. Default to false, use true to run in wp-admin.
			 * @param array $args {
			 *     Array of image details.
			 *
			 *     @type string|bool  $image Image URL or false.
			 *     @type int          $attachment_id Attachment ID of the image.
			 * }
			 */
			false === apply_filters( 'exactdn_admin_allow_image_srcset', false, compact( 'image_src', 'attachment_id' ) )
		) {
			return $sources;
		}
		if ( ! is_array( $sources ) ) {
			return $sources;
		}
		$upload_dir      = wp_get_upload_dir();
		$resize_existing = defined( 'EXACTDN_RESIZE_EXISTING' ) && EXACTDN_RESIZE_EXISTING;

		foreach ( $sources as $i => $source ) {
			if ( ! $this->validate_image_url( $source['url'] ) ) {
				continue;
			}

			/** This filter is already documented in class-exactdn.php */
			if ( apply_filters( 'exactdn_skip_image', false, $source['url'], $source ) ) {
				continue;
			}

			$url = $source['url'];

			list( $width, $height ) = $this->parse_dimensions_from_filename( $url );
			if ( ! $resize_existing && 'w' === $source['descriptor'] && $source['value'] == $width ) {
				ewwwio_debug_message( "preventing further processing for $url" );
				$sources[ $i ]['url'] = $this->generate_url( $source['url'] );
				continue;
			}
			ewwwio_debug_message( 'continuing: ' . $width . ' vs. ' . $source['value'] );

			// It's quicker to get the full size with the data we have already, if available.
			if ( ! empty( $attachment_id ) ) {
				$url = wp_get_attachment_url( $attachment_id );
			} else {
				$url = $this->strip_image_dimensions_maybe( $url );
			}
			ewwwio_debug_message( "building srcs from $url" );

			$args = array();
			if ( 'w' === $source['descriptor'] ) {
				if ( $height && ( $source['value'] == $width ) ) {
					$args['resize'] = $width . ',' . $height;
				} else {
					$args['w'] = $source['value'];
				}
			}

			$args = $this->maybe_smart_crop( $args, $attachment_id, $image_meta );

			$sources[ $i ]['url'] = $this->generate_url( $url, $args );
		}

		/**
		 * At this point, $sources is the original srcset with ExactDN URLs.
		 * Now, we're going to construct additional sizes based on multiples of the content_width.
		 * TODO: Then we will also insert additional sizes from the ExactDN feedback loop.
		 * This will reduce the gap between the largest defined size and the original image.
		 */

		/**
		 * Filter the multiplier ExactDN uses to create new srcset items.
		 * Return false to short-circuit and bypass auto-generation.
		 *
		 * @param array|bool $multipliers Array of multipliers to use or false to bypass.
		 */
		$multipliers = apply_filters( 'exactdn_srcset_multipliers', array( .2, .4, .6, .8, 1, 2, 3 ) );
		$url         = trailingslashit( $upload_dir['baseurl'] ) . $image_meta['file'];

		if (
			/** Short-circuit via exactdn_srcset_multipliers filter. */
			is_array( $multipliers )
			/** This filter is already documented in class-exactdn.php */
			&& ! apply_filters( 'exactdn_skip_image', false, $url, null )
			/** Verify basic meta is intact. */
			&& isset( $image_meta['width'] ) && isset( $image_meta['height'] ) && isset( $image_meta['file'] )
			/** Verify we have the requested width/height. */
			&& isset( $size_array[0] ) && isset( $size_array[1] )
			) {

			$fullwidth  = $image_meta['width'];
			$fullheight = $image_meta['height'];
			$reqwidth   = $size_array[0];
			$reqheight  = $size_array[1];
			ewwwio_debug_message( "requested w $reqwidth h $reqheight full w $fullwidth full h $fullheight" );

			$constrained_size = wp_constrain_dimensions( $fullwidth, $fullheight, $reqwidth );
			$expected_size    = array( $reqwidth, $reqheight );

			ewwwio_debug_message( $constrained_size[0] );
			ewwwio_debug_message( $constrained_size[1] );
			if ( abs( $constrained_size[0] - $expected_size[0] ) <= 1 && abs( $constrained_size[1] - $expected_size[1] ) <= 1 ) {
				$crop = 'soft';
				$base = $this->get_content_width() ? $this->get_content_width() : 1900; // Provide a default width if none set by the theme.
			} else {
				$crop = 'hard';
				$base = $reqwidth;
			}
			ewwwio_debug_message( "base width: $base" );

			$currentwidths = array_keys( $sources );
			$newsources    = null;

			foreach ( $multipliers as $multiplier ) {

				$newwidth = intval( $base * $multiplier );
				foreach ( $currentwidths as $currentwidth ) {
					// If a new width would be within 50 pixels of an existing one or larger than the full size image, skip.
					if ( abs( $currentwidth - $newwidth ) < 50 || ( $newwidth > $fullwidth ) ) {
						continue 2; // Back to the foreach ( $multipliers as $multiplier ).
					}
				} // foreach ( $currentwidths as $currentwidth ){

				if ( 'soft' == $crop ) {
					$args = array(
						'w' => $newwidth,
					);
				} else { // hard crop, e.g. add_image_size( 'example', 200, 200, true ).
					$args = array(
						'zoom'   => $multiplier,
						'resize' => $reqwidth . ',' . $reqheight,
					);
				}

				$args = $this->maybe_smart_crop( $args, $attachment_id, $image_meta );

				$newsources[ $newwidth ] = array(
					'url'        => $this->generate_url( $url, $args ),
					'descriptor' => 'w',
					'value'      => $newwidth,
				);
			} // foreach ( $multipliers as $multiplier )
			if ( is_array( $newsources ) ) {
				$sources = array_replace( $sources, $newsources );
			}
		} // if ( isset( $image_meta['width'] ) && isset( $image_meta['file'] ) )
		$elapsed_time = microtime( true ) - $started;
		ewwwio_debug_message( "parsing srcset took $elapsed_time seconds" );
		/* ewwwio_debug_message( print_r( $sources, true ) ); */
		$this->elapsed_time += microtime( true ) - $started;
		return $sources;
	}

	/**
	 * Filters an array of image `sizes` values, using $content_width instead of image's full size.
	 *
	 * @param array $sizes An array of media query breakpoints.
	 * @param array $size  Width and height of the image.
	 * @uses this::get_content_width
	 * @return array An array of media query breakpoints.
	 */
	public function filter_sizes( $sizes, $size ) {
		$started = microtime( true );
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		if ( ! doing_filter( 'the_content' ) ) {
			return $sizes;
		}
		$content_width = $this->get_content_width();
		if ( ! $content_width ) {
			$content_width = 1900;
		}

		if ( ( is_array( $size ) && $size[0] < $content_width ) ) {
			return $sizes;
		}

		$elapsed_time = microtime( true ) - $started;
		ewwwio_debug_message( "parsing sizes took $elapsed_time seconds" );
		$this->elapsed_time += microtime( true ) - $started;
		return sprintf( '(max-width: %1$dpx) 100vw, %1$dpx', $content_width );
	}

	/**
	 * Check for smart-cropping plugin to adjust cropping parameters.
	 * Currently supports Theia Smart Thumbnails using the theiaSmartThumbnails_position meta.
	 *
	 * @param array $args The arguments that have been generated so far.
	 * @param int   $attachment_id The ID number for the current image.
	 * @param array $meta Optional. The attachment (image) metadata. Default false.
	 * @return array The arguments, possibly altered for smart cropping.
	 */
	function maybe_smart_crop( $args, $attachment_id, $meta = false ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		if ( ! empty( $args['crop'] ) ) {
			ewwwio_debug_message( 'already cropped' );
			return $args;
		}
		// Doing something other than a hard crop, or we don't know what the ID is.
		if ( empty( $args['resize'] ) || empty( $attachment_id ) ) {
			ewwwio_debug_message( 'not resizing, so no custom crop' );
			return $args;
		}
		// TST is not active.
		if ( ! defined( 'TST_VERSION' ) ) {
			ewwwio_debug_message( 'no TST plugin' );
			return $args;
		}
		if ( ! class_exists( 'TstPostOptions' ) || ! defined( 'TstPostOptions::META_POSITION' ) ) {
			ewwwio_debug_message( 'no TstPostOptions class' );
			return $args;
		}
		if ( ! $meta || ! is_array( $meta ) || empty( $meta['sizes'] ) ) {
			// $focus_point = get_post_meta( $attachment_id, TstPostOptions::META_POSITION, true );
			$meta = wp_get_attachment_metadata( $attachment_id );
			if ( ! is_array( $meta ) || empty( $meta['width'] ) || empty( $meta['height'] ) ) {
				ewwwio_debug_message( 'unusable meta retrieved' );
				return $args;
			}
			$focus_point = TstPostOptions::get_meta( $attachment_id, $meta['width'], $meta['height'] );
		} elseif ( ! empty( $meta['tst_thumbnail_version'] ) ) {
			if ( empty( $meta['width'] ) || empty( $meta['height'] ) ) {
				ewwwio_debug_message( 'unusable meta passed' );
				return $args;
			}
			$focus_point = TstPostOptions::get_meta( $attachment_id, $meta['width'], $meta['height'] );
		} else {
			ewwwio_debug_message( 'unusable meta' );
			return $args;
		}
		if ( empty( $focus_point ) || ! is_array( $focus_point ) ) {
			ewwwio_debug_message( 'unusable focus point' );
			return $args;
		}

		$dimensions = explode( ',', $args['resize'] );

		$new_w = $dimensions[0];
		$new_h = $dimensions[1];
		ewwwio_debug_message( "full size dims: w{$meta['width']} h{$meta['height']}" );
		ewwwio_debug_message( "smart crop dims: w$new_w h$new_h" );
		if ( ! empty( $args['zoom'] ) ) {
			$new_w = round( $args['zoom'] * $new_w );
			$new_h = round( $args['zoom'] * $new_h );
			ewwwio_debug_message( "zooming: {$args['zoom']} w$new_w h$new_h" );
		}
		if ( ! $new_w || ! $new_h ) {
			ewwwio_debug_message( 'empty dimension, not cropping' );
			return $args;
		}
		$size_ratio = max( $new_w / $meta['width'], $new_h / $meta['height'] );
		$crop_w     = round( $new_w / $size_ratio );
		$crop_h     = round( $new_h / $size_ratio );
		$s_x        = floor( ( $meta['width'] - $crop_w ) * $focus_point[0] );
		$s_y        = floor( ( $meta['height'] - $crop_h ) * $focus_point[1] );
		ewwwio_debug_message( "doing the math with size_ratio of $size_ratio" );

		$args = array( 'crop' => $s_x . 'px,' . $s_y . 'px,' . $crop_w . 'px,' . $crop_h . 'px' ) + $args;
		ewwwio_debug_message( $args['crop'] );
		return $args;
	}

	/**
	 * Make sure the image domain is on the list of approved domains.
	 *
	 * @param string $domain The hostname to validate.
	 * @return bool True if the hostname is allowed, false otherwise.
	 */
	public function allow_image_domain( $domain ) {
		$domain = trim( $domain );
		foreach ( $this->allowed_domains as $allowed ) {
			$allowed = trim( $allowed );
			if ( $domain === $allowed ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Ensure image URL is valid for ExactDN.
	 * Though ExactDN functions address some of the URL issues, we should avoid unnecessary processing if we know early on that the image isn't supported.
	 *
	 * @param string $url The image url to be validated.
	 * @param bool   $exactdn_is_valid Optional. Whether an ExactDN URL should be considered valid. Default false.
	 * @uses wp_parse_args
	 * @return bool True if the url is considerd valid, false otherwise.
	 */
	protected function validate_image_url( $url, $exactdn_is_valid = false ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		$parsed_url = parse_url( $url );
		if ( ! $parsed_url ) {
			ewwwio_debug_message( 'could not parse' );
			return false;
		}

		// Parse URL and ensure needed keys exist, since the array returned by `parse_url` only includes the URL components it finds.
		$url_info = wp_parse_args( $parsed_url, array(
			'scheme' => null,
			'host'   => null,
			'port'   => null,
			'path'   => null,
		) );

		// Bail if scheme isn't http or port is set that isn't port 80.
		if (
			( 'http' != $url_info['scheme'] || ! in_array( $url_info['port'], array( 80, null ) ) ) &&
			/**
			 * Tells ExactDN to ignore images that are served via HTTPS.
			 *
			 * @param bool $reject_https Should ExactDN ignore images using the HTTPS scheme. Default to false.
			 */
			apply_filters( 'exactdn_reject_https', false )
		) {
			ewwwio_debug_message( 'rejected https via filter' );
			return false;
		}

		// Bail if no host is found.
		if ( is_null( $url_info['host'] ) ) {
			ewwwio_debug_message( 'null host' );
			return false;
		}

		// Bail if the image already went through ExactDN.
		if ( ! $exactdn_is_valid && strpos( $url_info['host'], '.exactdn.com' ) ) {
			ewwwio_debug_message( 'exactdn image' );
			return false;
		}
		if ( ! $exactdn_is_valid && strpos( $url_info['host'], '.exactdn.net' ) ) {
			ewwwio_debug_message( 'exactdn image' );
			return false;
		}
		if ( ! $exactdn_is_valid && strpos( $url_info['host'], '.exactcdn.com' ) ) {
			ewwwio_debug_message( 'exactdn image' );
			return false;
		}
		if ( ! $exactdn_is_valid && strpos( $url_info['host'], '.exactcdn.net' ) ) {
			ewwwio_debug_message( 'exactdn image' );
			return false;
		}

		// Bail if the image already went through Photon to avoid conflicts.
		if ( preg_match( '#^i[\d]{1}.wp.com$#i', $url_info['host'] ) ) {
			ewwwio_debug_message( 'photon/wp.com image' );
			return false;
		}

		// Bail if no path is found.
		if ( is_null( $url_info['path'] ) ) {
			ewwwio_debug_message( 'null path' );
			return false;
		}

		// Ensure image extension is acceptable.
		if ( ! in_array( strtolower( pathinfo( $url_info['path'], PATHINFO_EXTENSION ) ), $this->extensions ) ) {
			ewwwio_debug_message( 'invalid extension' );
			return false;
		}

		// Make sure this is an allowed image domain/hostname for ExactDN on this site.
		if ( ! $this->allow_image_domain( $url_info['host'] ) ) {
			ewwwio_debug_message( 'invalid host for ExactDN' );
			return false;
		}

		// If we got this far, we should have an acceptable image URL,
		// but let folks filter to decline if they prefer.
		/**
		 * Overwrite the results of the previous validation steps an image goes through to be considered valid for ExactDN.
		 *
		 * @param bool true Is the image URL valid and can it be used by ExactDN. Default to true.
		 * @param string $url Image URL.
		 * @param array $parsed_url Array of information about the image url.
		 */
		return apply_filters( 'exactdn_validate_image_url', true, $url, $parsed_url );
	}

	/**
	 * Checks if the file exists before it passes the file to ExactDN.
	 *
	 * @param string $src The image URL.
	 * @return string The possibly altered URL without dimensions.
	 **/
	protected function strip_image_dimensions_maybe( $src ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		$stripped_src = $src;

		// Build URL, first removing WP's resized string so we pass the original image to ExactDN.
		if ( preg_match( '#(-\d+x\d+)\.(' . implode( '|', $this->extensions ) . '){1}(?:\?.+)?$#i', $src, $src_parts ) ) {
			$stripped_src = str_replace( $src_parts[1], '', $src );
			$upload_dir   = wp_get_upload_dir();

			// Extracts the file path to the image minus the base url.
			$file_path = substr( $stripped_src, strlen( $upload_dir['baseurl'] ) );

			if ( file_exists( $upload_dir['basedir'] . $file_path ) ) {
				$src = $stripped_src;
			}
			ewwwio_debug_message( 'stripped dims' );
		}
		return $src;
	}

	/**
	 * Provide an array of available image sizes and corresponding dimensions.
	 * Similar to get_intermediate_image_sizes() except that it includes image sizes' dimensions, not just their names.
	 *
	 * @global $wp_additional_image_sizes
	 * @uses get_option
	 * @return array
	 */
	protected function image_sizes() {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		if ( null == self::$image_sizes ) {
			global $_wp_additional_image_sizes;

			// Populate an array matching the data structure of $_wp_additional_image_sizes so we have a consistent structure for image sizes.
			$images = array(
				'thumb'  => array(
					'width'  => intval( get_option( 'thumbnail_size_w' ) ),
					'height' => intval( get_option( 'thumbnail_size_h' ) ),
					'crop'   => (bool) get_option( 'thumbnail_crop' ),
				),
				'medium' => array(
					'width'  => intval( get_option( 'medium_size_w' ) ),
					'height' => intval( get_option( 'medium_size_h' ) ),
					'crop'   => false,
				),
				'large'  => array(
					'width'  => intval( get_option( 'large_size_w' ) ),
					'height' => intval( get_option( 'large_size_h' ) ),
					'crop'   => false,
				),
				'full'   => array(
					'width'  => null,
					'height' => null,
					'crop'   => false,
				),
			);

			// Compatibility mapping as found in wp-includes/media.php.
			$images['thumbnail'] = $images['thumb'];

			// Update class variable, merging in $_wp_additional_image_sizes if any are set.
			if ( is_array( $_wp_additional_image_sizes ) && ! empty( $_wp_additional_image_sizes ) ) {
				self::$image_sizes = array_merge( $images, $_wp_additional_image_sizes );
			} else {
				self::$image_sizes = $images;
			}
		}

		return is_array( self::$image_sizes ) ? self::$image_sizes : array();
	}

	/**
	 * Enqueue ExactDN helper script
	 */
	public function action_wp_enqueue_scripts() {
		wp_enqueue_script( 'exactdn', plugins_url( 'includes/exactdn.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION, true );
	}

	/**
	 * Suppress query args for certain files, typically for placholder images.
	 *
	 * @param array|string $args Array of ExactDN arguments.
	 * @param string       $image_url Image URL.
	 * @param string|null  $scheme Image scheme. Default to null.
	 * @return array Empty if it matches our search, otherwise just $args untouched.
	 */
	function exactdn_remove_args( $args, $image_url, $scheme ) {
		if ( strpos( $image_url, 'revslider/admin/assets/images/dummy.png' ) ) {
			return array();
		}
		if ( strpos( $image_url, 'lazy_placeholder.gif' ) ) {
			return array();
		}
		if ( strpos( $image_url, 'essential-grid/public/assets/images/' ) ) {
			return array();
		}
		if ( strpos( $image_url, 'LayerSlider/static/img' ) ) {
			return array();
		}
		return $args;
	}

	/**
	 * Generates an ExactDN URL.
	 *
	 * @param string       $image_url URL to the publicly accessible image you want to manipulate.
	 * @param array|string $args An array of arguments, i.e. array( 'w' => '300', 'resize' => array( 123, 456 ) ), or in string form (w=123&h=456).
	 * @param string       $scheme Indicates http or https, other schemes are invalid.
	 * @return string The raw final URL. You should run this through esc_url() before displaying it.
	 */
	function generate_url( $image_url, $args = array(), $scheme = null ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		$image_url = trim( $image_url );

		if ( is_null( $scheme ) ) {
			$site_url = get_home_url();
			$scheme   = 'http';
			if ( strpos( $site_url, 'https://' ) !== false ) {
				$scheme = 'https';
			}
		}

		/**
		 * Disables ExactDN URL processing for local development.
		 *
		 * @param bool false default
		 */
		if ( true === apply_filters( 'exactdn_development_mode', false ) ) {
			return $image_url;
		}

		/**
		 * Allow specific image URls to avoid going through ExactDN.
		 *
		 * @param bool false Should the image be returned as is, without going through ExactDN. Default to false.
		 * @param string $image_url Image URL.
		 * @param array|string $args Array of ExactDN arguments.
		 * @param string|null $scheme Image scheme. Default to null.
		 */
		if ( true === apply_filters( 'exactdn_skip_for_url', false, $image_url, $args, $scheme ) ) {
			return $image_url;
		}

		// TODO: Not differentiated yet, but it will be, so stay tuned!
		$jpg_quality  = apply_filters( 'jpeg_quality', null, 'image_resize' );
		$webp_quality = apply_filters( 'jpeg_quality', $jpg_quality, 'image/webp' );

		$more_args = array();
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpegtran_copy' ) ) {
			$more_args['strip'] = 'all';
		}
		if ( ewww_image_optimizer_get_option( 'exactdn_lossy' ) ) {
			$more_args['lossy'] = is_numeric( ewww_image_optimizer_get_option( 'exactdn_lossy' ) ) ? (int) ewww_image_optimizer_get_option( 'exactdn_lossy' ) : 80;
		}
		if ( ! is_null( $jpg_quality ) && 82 != $jpg_quality ) {
			$more_args['quality'] = $jpg_quality;
		}
		// Merge given args with the automatic (option-based) args, and also makes sure args is an array if it was previously a string.
		$args = wp_parse_args( $args, $more_args );

		/**
		 * Filter the original image URL before it goes through ExactDN.
		 *
		 * @param string $image_url Image URL.
		 * @param array|string $args Array of ExactDN arguments.
		 * @param string|null $scheme Image scheme. Default to null.
		 */
		$image_url = apply_filters( 'exactdn_pre_image_url', $image_url, $args, $scheme );

		/**
		 * Filter the ExactDN image parameters before they are applied to an image.
		 *
		 * @param array|string $args Array of ExactDN arguments.
		 * @param string $image_url Image URL.
		 * @param string|null $scheme Image scheme. Default to null.
		 */
		$args = apply_filters( 'exactdn_pre_args', $args, $image_url, $scheme );

		if ( empty( $image_url ) ) {
			return $image_url;
		}

		$image_url_parts = $this->parse_url( $image_url );

		// Unable to parse.
		if ( ! is_array( $image_url_parts ) || empty( $image_url_parts['host'] ) || empty( $image_url_parts['path'] ) ) {
			ewwwio_debug_message( 'src url no good' );
			return $image_url;
		}

		if ( is_array( $args ) ) {
			// Convert values that are arrays into strings.
			foreach ( $args as $arg => $value ) {
				if ( is_array( $value ) ) {
					$args[ $arg ] = implode( ',', $value );
				}
			}

			// Encode argument values.
			$args = rawurlencode_deep( $args );
		}

		ewwwio_debug_message( $image_url_parts['host'] );

		// Figure out which CDN (sub)domain to use.
		if ( empty( $this->exactdn_domain ) ) {
			ewwwio_debug_message( 'no exactdn domain configured' );
			return $image_url;
		}

		// You can't run an ExactDN URL through again because query strings are stripped.
		// So if the image is already an ExactDN URL, append the new arguments to the existing URL.
		if ( $this->exactdn_domain === $image_url_parts['host'] ) {
			ewwwio_debug_message( 'url already has exactdn domain' );
			$exactdn_url = add_query_arg( $args, $image_url );
			return $this->url_scheme( $exactdn_url, $scheme );
		}

		// ExactDN doesn't support query strings so we ignore them and look only at the path.
		// However some source images are served via PHP so check the no-query-string extension.
		// For future proofing, this is a blacklist of common issues rather than a whitelist.
		$extension = pathinfo( $image_url_parts['path'], PATHINFO_EXTENSION );
		if ( empty( $extension ) || in_array( $extension, array( 'php', 'ashx' ) ) ) {
			ewwwio_debug_message( 'bad extension' );
			return $image_url;
		}

		$domain      = 'http://' . $this->exactdn_domain . '/';
		$exactdn_url = $domain . ltrim( $image_url_parts['path'], '/' );
		ewwwio_debug_message( "bare exactdn url: $exactdn_url" );

		/**
		 * Add query strings to ExactDN URL.
		 * By default, ExactDN doesn't support query strings so we ignore them.
		 * This setting is ExactDN Server dependent.
		 *
		 * @param bool false Should query strings be added to the image URL. Default is false.
		 * @param string $image_url_parts['host'] Image URL's host.
		 */
		if ( isset( $image_url_parts['query'] ) && apply_filters( 'exactdn_add_query_string_to_domain', false, $image_url_parts['host'] ) ) {
			$exactdn_url .= '?q=' . rawurlencode( $image_url_parts['query'] );
		}
		// This is disabled, as I don't think we really need it.
		if ( false && ! empty( $image_url_parts['query'] ) && false !== strpos( $image_url_parts['query'], 'theia_smart' ) ) {
			$args = wp_parse_args( $image_url_parts['query'], $args );
		}

		if ( $args ) {
			if ( is_array( $args ) ) {
				$exactdn_url = add_query_arg( $args, $exactdn_url );
			} else {
				// You can pass a query string for complicated requests, although this should have been converted to an array already.
				$exactdn_url .= '?' . $args;
			}
		}
		ewwwio_debug_message( "exactdn url with args: $exactdn_url" );

		if ( isset( $image_url_parts['scheme'] ) && 'https' == $image_url_parts['scheme'] ) {
			$exactdn_url = add_query_arg(
				array(
					'ssl' => 1,
				),
				$exactdn_url
			);
			$scheme      = 'https';
		}

		return $this->url_scheme( $exactdn_url, $scheme );
	}

	/**
	 * Prepends schemeless urls or replaces non-http scheme with a valid scheme, defaults to 'http'.
	 *
	 * @param string      $url The URL to parse.
	 * @param string|null $scheme Retrieve specific URL component.
	 * @return string Result of parse_url.
	 */
	function url_scheme( $url, $scheme ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		if ( ! in_array( $scheme, array( 'http', 'https' ) ) ) {
			ewwwio_debug_message( 'not a valid scheme' );
			if ( preg_match( '#^(https?:)?//#', $url ) ) {
				ewwwio_debug_message( 'url has a valid scheme already' );
				return $url;
			}
			ewwwio_debug_message( 'invalid scheme provided, and url sucks, defaulting to http' );
			$scheme = 'http';
		}
		ewwwio_debug_message( "valid $scheme - $url" );
		return preg_replace( '#^([a-z:]+)?//#i', "$scheme://", $url );
	}

	/**
	 * A wrapper for PHP's parse_url, prepending assumed scheme for network path
	 * URLs. PHP versions 5.4.6 and earlier do not correctly parse without scheme.
	 *
	 * @param string  $url The URL to parse.
	 * @param integer $component Retrieve specific URL component.
	 * @return mixed Result of parse_url.
	 */
	function parse_url( $url, $component = -1 ) {
		if ( 0 === strpos( $url, '//' ) ) {
			$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
		}
		return parse_url( $url, $component );
	}

	/**
	 * Adds link to header which enables DNS prefetching for faster speed.
	 */
	function dns_prefetch() {
		if ( $this->exactdn_domain ) {
			echo "\r\n";
			printf( "<link rel='dns-prefetch' href='%s'>\r\n", '//' . esc_attr( $this->exactdn_domain ) );
		}
	}
}

global $exactdn;
$exactdn = new ExactDN();
