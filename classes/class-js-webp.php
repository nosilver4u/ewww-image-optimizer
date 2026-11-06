<?php
/**
 * Implements WebP rewriting using page parsing and JS functionality.
 *
 * @link https://ewww.io
 * @package EWWW
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enables EWWW IO to filter the page content and replace img elements with WebP markup.
 */
class JS_Webp extends Page_Parser {

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
	 * @var array $user_element_exclusions
	 */
	protected $user_element_exclusions = array();

	/**
	 * A list of user-defined page/URL exclusions, populated by validate_user_exclusions().
	 *
	 * @access protected
	 * @var array $user_page_exclusions
	 */
	protected $user_page_exclusions = array();

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
	 * A list of attributes to be added to the inline scripts.
	 *
	 * @access private
	 * @var array $inline_script_attrs
	 */
	private $inline_script_attrs = array(
		'data-cfasync'     => 'false',
		'data-no-defer'    => '1',
		'data-no-minify'   => '1',
		'data-no-optimize' => '1',
	);

	/**
	 * Register (once) actions and filters for JS WebP.
	 */
	public function __construct() {
		global $eio_js_webp;
		if ( \is_object( $eio_js_webp ) ) {
			return 'you are doing it wrong';
		}
		parent::__construct();
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->content_url();

		\add_filter( 'eio_do_js_webp', array( $this, 'should_process_page' ), 10, 2 );

		$do_webp = true;
		/**
		 * Allow pre-empting JS WebP by page.
		 *
		 * @param bool $do_webp Whether to parse the page for images to rewrite for WebP, default true.
		 * @param string parent::$request_uri The URI/path of the page.
		 */
		if ( ! \apply_filters( 'eio_do_js_webp', $do_webp, parent::$request_uri ) ) {
			return;
		}
		// WebP parsing for external use and third-party plugins.
		\add_filter( 'eio_image_url_to_webp', array( $this, 'filter_image_url' ) );
		// Hook into the output buffer callback function.
		\add_filter( 'ewww_image_optimizer_filter_page_output', array( $this, 'filter_page_output' ), 20 );
		// Generic filter for use by other plugins/themes.
		\add_filter( 'eio_parse_page_html', array( $this, 'filter_page_output' ), 20, 2 );
		// Filter for NextGEN image urls within JSON.
		\add_filter( 'ngg_pro_lightbox_images_queue', array( $this, 'ngg_pro_lightbox_images_queue' ), 11 );
		// Filter for WooCommerce product variations (individual items).
		\add_filter( 'woocommerce_available_variation', array( $this, 'woocommerce_available_variation' ) );
		// Filter for FacetWP JSON responses.
		\add_filter( 'facetwp_render_output', array( $this, 'filter_facetwp_json_output' ) );
		// Filter for LL when multiple background images are used--because it uses JSON, and the background image parser skips elements containing JSON.
		\add_filter( 'eio_ll_multiple_bg_images_for_webp', array( $this, 'filter_image_url_array' ) );

		// Load up the minified check script.
		$this->check_webp_script = \file_get_contents( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'includes/check-webp.min.js' );
		// Load up the minified script so we can inline it.
		$this->load_webp_script = \file_get_contents( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'includes/load-webp.min.js' );

		$allowed_urls = $this->get_option( 'ewww_image_optimizer_webp_paths' );
		if ( $this->is_iterable( $allowed_urls ) ) {
			$this->allowed_urls = \array_merge( $this->allowed_urls, $allowed_urls );
		}

		$this->get_allowed_domains();

		$this->allowed_urls    = \apply_filters( 'webp_allowed_urls', $this->allowed_urls );
		$this->allowed_domains = \apply_filters( 'webp_allowed_domains', $this->allowed_domains );
		$this->debug_message( 'checking any images matching these URLs/patterns for webp: ' . \implode( ',', $this->allowed_urls ) );
		$this->debug_message( 'rewriting any images matching these domains to webp: ' . \implode( ',', $this->allowed_domains ) );

		// Load the appropriate JS, in the footer, but as early as possible.
		if ( \defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			// Load the non-minified, non-inline version of the webp rewrite script.
			\add_action( 'wp_enqueue_scripts', array( $this, 'debug_script' ), -99 );
		} elseif ( \defined( 'EWWW_IMAGE_OPTIMIZER_WEBP_EXTERNAL_SCRIPT' ) && EWWW_IMAGE_OPTIMIZER_WEBP_EXTERNAL_SCRIPT ) {
			// Load the minified, non-inline version of the webp rewrite script.
			\add_action( 'wp_enqueue_scripts', array( $this, 'min_external_script' ), -99 );
		} else {
			\add_action( 'wp_head', array( $this, 'inline_check_script' ), -99 );
			if ( \defined( 'EWWW_IMAGE_OPTIMIZER_WEBP_FOOTER_SCRIPT' ) && EWWW_IMAGE_OPTIMIZER_WEBP_FOOTER_SCRIPT ) {
				\add_action( 'wp_footer', array( $this, 'inline_load_script' ), -99 );
			} else {
				\add_action( 'wp_head', array( $this, 'inline_load_script' ), -90 );
			}
		}
		$this->inline_script_attrs = (array) \apply_filters( 'ewwwio_inline_script_attrs', $this->inline_script_attrs );
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
	public function should_process_page( $should_process = true, $uri = '' ) {
		// Don't foul up the admin side of things, unless a plugin needs to.
		if ( \is_admin() &&
			/**
			 * Provide plugins a way of running JS WebP for images in the WordPress Admin, usually for admin-ajax.php.
			 *
			 * @param bool false Allow JS WebP to run on the Dashboard. Defaults to false.
			 */
			false === \apply_filters( 'eio_allow_admin_js_webp', false )
		) {
			$this->debug_message( 'is_admin' );
			return false;
		}
		if ( \ewww_image_optimizer_ce_webp_enabled() ) {
			return false;
		}
		if ( empty( $uri ) ) {
			$uri = parent::$request_uri;
		}
		if ( $this->is_iterable( $this->user_page_exclusions ) ) {
			foreach ( $this->user_page_exclusions as $page_exclusion ) {
				if ( '/' === $page_exclusion && '/' === $uri ) {
					return false;
				} elseif ( '/' === $page_exclusion ) {
					continue;
				}
				if ( false !== \strpos( $uri, $page_exclusion ) ) {
					return false;
				}
			}
		}
		if ( false !== \strpos( $uri, 'bricks=run' ) ) {
			return false;
		}
		if ( false !== \strpos( $uri, '?brizy-edit' ) ) {
			return false;
		}
		if ( false !== \strpos( $uri, '&builder=true' ) ) {
			return false;
		}
		if ( false !== \strpos( $uri, 'cornerstone=' ) || false !== \strpos( $uri, 'cornerstone-endpoint' ) || false !== \strpos( $uri, 'cornerstone/edit/' ) ) {
			return false;
		}
		if ( false !== \strpos( $uri, 'ct_builder=' ) ) {
			return false;
		}
		if ( false !== \strpos( $uri, 'ct_render_shortcode=' ) || false !== \strpos( $uri, 'action=oxy_render' ) ) {
			return false;
		}
		if ( \did_action( 'cornerstone_boot_app' ) || \did_action( 'cs_before_preview_frame' ) ) {
			return false;
		}
		if ( \did_action( 'cs_element_rendering' ) || \did_action( 'cornerstone_before_boot_app' ) || \apply_filters( 'cs_is_preview_render', false ) ) {
			return false;
		}
		if ( false !== \strpos( $uri, 'elementor-preview=' ) ) {
			return false;
		}
		if ( false !== \strpos( $uri, 'et_fb=' ) ) {
			return false;
		}
		if ( false !== \strpos( $uri, 'fb-edit=' ) ) {
			return false;
		}
		if ( false !== \strpos( $uri, '?fl_builder' ) ) {
			return false;
		}
		if ( false !== \strpos( $uri, '?giveDonationFormInIframe' ) ) {
			return false;
		}
		if ( false !== \strpos( $uri, 'is-editor-iframe=' ) ) {
			return false;
		}
		if ( '/print/' === \substr( $uri, -7 ) ) {
			return false;
		}
		if ( \defined( 'REST_REQUEST' ) && REST_REQUEST && ! \apply_filters( 'eio_allow_restapi_parsing', false, 'js_webp' ) ) {
			return false;
		}
		if ( false !== \strpos( $uri, 'tatsu=' ) ) {
			return false;
		}
		if ( false !== \strpos( $uri, 'tve=true' ) ) {
			return false;
		}
		if ( ! empty( $_POST['action'] ) && 'tatsu_get_concepts' === \sanitize_text_field( \wp_unslash( $_POST['action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}
		if ( \is_customize_preview() ) {
			$this->debug_message( 'is_customize_preview' );
			return false;
		}
		global $wp_query;
		if ( ! isset( $wp_query ) || ! ( $wp_query instanceof \WP_Query ) ) {
			return $should_process;
		}
		if ( $this->is_amp() ) {
			return false;
		}
		if ( \is_embed() ) {
			$this->debug_message( 'is_embed' );
			return false;
		}
		if ( \is_feed() ) {
			$this->debug_message( 'is_feed' );
			return false;
		}
		if ( \is_preview() ) {
			$this->debug_message( 'is_preview' );
			return false;
		}
		if ( \wp_script_is( 'twentytwenty-twentytwenty', 'enqueued' ) ) {
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
	public function get_webp_domains() {
		return $this->allowed_domains;
	}

	/**
	 * Replaces images within a srcset attribute with their .webp derivatives.
	 *
	 * @param string $srcset A valid srcset attribute from an img element.
	 * @return bool|string False if no changes were made, or the new srcset if any WebP images replaced the originals.
	 */
	public function srcset_replace( $srcset ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$srcset_urls = \explode( ' ', $srcset );
		$found_webp  = false;
		if ( $this->is_iterable( $srcset_urls ) && \count( $srcset_urls ) > 1 ) {
			$this->debug_message( 'parsing srcset urls' );
			foreach ( $srcset_urls as $srcurl ) {
				if ( \is_numeric( \substr( $srcurl, 0, 1 ) ) ) {
					continue;
				}
				$trailing = ' ';
				if ( ',' === \substr( $srcurl, -1 ) ) {
					$trailing = ',';
					$srcurl   = \rtrim( $srcurl, ',' );
				}
				$this->debug_message( "looking for $srcurl from srcset" );
				$validated_path = $this->validate_image_url( $srcurl );
				if ( $validated_path ) {
					$srcset = \str_replace( $srcurl . $trailing, $this->generate_url( $srcurl, $validated_path ) . $trailing, $srcset );
					$this->debug_message( "replaced $srcurl in srcset" );
					$found_webp = true;
				}
			}
		} else {
			$validated_path = $this->validate_image_url( $srcset );
			if ( $validated_path ) {
				return $this->generate_url( $srcset, $validated_path );
			}
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
	 * @param object $image An HTML_Tag object representing the img element.
	 */
	public function jetpack_replace( $image ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$data_orig_file = $image->get_attribute( 'data-orig-file' );
		if ( $data_orig_file ) {
			$this->debug_message( "looking for data-orig-file: $data_orig_file" );
			$validated_path = $this->validate_image_url( $data_orig_file );
			if ( $validated_path ) {
				$image->set_attribute( 'data-webp-orig-file', $this->generate_url( $data_orig_file, $validated_path ) );
				$this->debug_message( "replacing $data_orig_file via data-webp-orig-file" );
			}
		}
		$data_medium_file = $image->get_attribute( 'data-medium-file' );
		if ( $data_medium_file ) {
			$this->debug_message( "looking for data-medium-file: $data_medium_file" );
			$validated_path = $this->validate_image_url( $data_medium_file );
			if ( $validated_path ) {
				$image->set_attribute( 'data-webp-medium-file', $this->generate_url( $data_medium_file, $validated_path ) );
				$this->debug_message( "replacing $data_medium_file via data-webp-medium-file" );
			}
		}
		$data_large_file = $image->get_attribute( 'data-large-file' );
		if ( $data_large_file ) {
			$this->debug_message( "looking for data-large-file: $data_large_file" );
			$validated_path = $this->validate_image_url( $data_large_file );
			if ( $validated_path ) {
				$image->set_attribute( 'data-webp-large-file', $this->generate_url( $data_large_file, $validated_path ) );
				$this->debug_message( "replacing $data_large_file via data-webp-large-file" );
			}
		}
	}

	/**
	 * Replaces images with the WooCommerce data attributes with their .webp derivatives.
	 *
	 * @param object $image An HTML_Tag object representing the img element.
	 */
	public function woocommerce_replace( $image ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$data_large_image = $image->get_attribute( 'data-large_image' );
		if ( $data_large_image ) {
			$this->debug_message( "looking for data-large_image: $data_large_image" );
			$validated_path = $this->validate_image_url( $data_large_image );
			if ( $validated_path ) {
				$image->set_attribute( 'data-webp-large_image', $this->generate_url( $data_large_image, $validated_path ) );
				$this->debug_message( "replacing $data_large_image via data-webp-large_image" );
			}
		}
		$data_src = $image->get_attribute( 'data-src' );
		if ( $data_src ) {
			$this->debug_message( "looking for data-src: $data_src" );
			$validated_path = $this->validate_image_url( $data_src );
			if ( $validated_path ) {
				$image->set_attribute( 'data-webp-src', $this->generate_url( $data_src, $validated_path ) );
				$this->debug_message( "replacing $data_src via data-webp-src" );
			}
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
	 * @param string $context Indicate which parsers are allowed. Defaults to empty, but may be lazyload, js_webp, or picture_webp.
	 * @return string The altered buffer containing the full page with WebP images inserted.
	 */
	public function filter_page_output( $buffer, $context = '' ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! empty( $context ) && 'js_webp' !== $context ) {
			$this->debug_message( "3rd party context does not match: $context" );
			return $buffer;
		}
		if (
			empty( $buffer ) ||
			\preg_match( '/^<\?xml/', $buffer ) ||
			\strpos( $buffer, 'amp-boilerplate' )
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
		if ( ! \apply_filters( 'eio_do_js_webp', true, parent::$request_uri ) ) {
			return $buffer;
		}

		$body_tags        = $this->get_elements_from_html( $buffer, 'body' );
		$body_webp_script = '<script';
		foreach ( $this->inline_script_attrs as $attr_name => $attr_value ) {
			if ( empty( $attr_name ) || empty( $attr_value ) ) {
				continue;
			}
			if ( \preg_match( '/[^a-z0-9_-]/i', $attr_name ) ) {
				continue;
			}
			$body_webp_script .= ' ' . $attr_name . '="' . \esc_attr( $attr_value ) . '"';
		}
		$body_webp_script .= '>if(typeof ewww_webp_supported==="undefined"){var ewww_webp_supported=!1}if(ewww_webp_supported){document.body.classList.add("webp-support")}</script>';
		if (
			apply_filters( 'ewwwio_insert_body_webp_script', true ) &&
			$this->is_iterable( $body_tags ) &&
			! empty( $body_tags[0] ) &&
			false !== \strpos( $body_tags[0], '<body' ) &&
			false === strpos( $body_tags[0], 'x-init' ) &&
			false === strpos( $body_tags[0], 'x-data' ) &&
			\str_ends_with( $body_tags[0], '=>' )
		) {
			// Add the WebP script right after the opening tag.
			$buffer = \str_replace( $body_tags[0], $body_tags[0] . "\n" . $body_webp_script, $buffer );
		} elseif ( apply_filters( 'ewwwio_insert_body_webp_script', true ) ) {
			$buffer = \str_replace( '<body>', "<body>\n$body_webp_script", $buffer );
		}
		$images = $this->get_images_from_html( \preg_replace( '/<(picture|noscript).*?\/\1>/s', '', $buffer ), false );
		if ( ! empty( $images[0] ) && $this->is_iterable( $images[0] ) ) {
			foreach ( $images[0] as $index => $image ) {
				if ( \str_contains( $image, 'ewww_webp' ) ) {
					continue;
				}
				$image_tag = new HTML_Tag( $image );
				if ( ! $this->validate_tag( $image_tag ) ) {
					continue;
				}
				$file = $image_tag->get_attribute( 'src' );
				$this->debug_message( "parsing an image: $file" );
				$validated_file_path = $this->validate_image_url( $file );
				if ( $validated_file_path && ! $image_tag->attribute_contains( 'class', 'lazyload' ) ) {
					// If a CDN path match was found, or .webp image existence is confirmed, and this is not a lazy-load 'dummy' image.
					$this->debug_message( 'found a webp image or forced path' );
					$image_tag->set_attribute( 'data-src-img', $file );
					$image_tag->set_attribute( 'data-src-webp', $this->generate_url( $file, $validated_file_path ) );
					$srcset = $image_tag->get_attribute( 'srcset' );
					if ( $srcset ) {
						$srcset_webp = $this->srcset_replace( $srcset );
						if ( $srcset_webp ) {
							$this->debug_message( 'webp srcset generated, setting attrs' );
							$image_tag->set_attribute( 'data-srcset-webp', $srcset_webp );
							$image_tag->set_attribute( 'data-srcset-img', $srcset );
							$image_tag->set_attribute( 'srcset', $this->placeholder_src . ' 1w' );
						}
					}
					if ( $image_tag->get_attribute( 'data-orig-file' ) && $image_tag->get_attribute( 'data-medium-file' ) && $image_tag->get_attribute( 'data-large-file' ) ) {
						$this->jetpack_replace( $image_tag );
					}
					if ( $image_tag->get_attribute( 'data-large_image' ) && $image_tag->get_attribute( 'data-src' ) ) {
						$this->woocommerce_replace( $image_tag );
					}
					$image_tag->set_attribute( 'src', $this->placeholder_src );
					if ( $image_tag->html !== $image ) {
						$image_tag->set_attribute( 'data-eio', 'j' );
						$image_tag->set_attribute( 'class', 'ewww_webp', true );
						$this->debug_message( "going to swap\n$image\nwith\n$image_tag->html" );
						$noscript = \apply_filters( 'eio_webp_noscript', '<noscript>' . $image . '</noscript>', $image_tag->html, $file );
						$buffer   = \str_replace( $image, $image_tag->html . $noscript, $buffer );
					}
				} elseif ( ! empty( $file ) && $image_tag->get_attribute( 'data-lazy-src' ) ) {
					// BJ Lazy Load & WP Rocket.
					$real_url = $image_tag->get_attribute( 'data-lazy-src' );
					$this->debug_message( "checking webp for Lazy Load data-lazy-src: $real_url" );
					$validated_real_path = $this->validate_image_url( $real_url );
					if ( $validated_real_path ) {
						$this->debug_message( "found webp for Lazy Load: $real_url" );
						$image_tag->set_attribute( 'data-lazy-src-webp', $this->generate_url( $real_url, $validated_real_path ) );
					}
					$srcset = $image_tag->get_attribute( 'data-lazy-srcset' );
					if ( $srcset ) {
						$srcset_webp = $this->srcset_replace( $srcset );
						if ( $srcset_webp ) {
							$image_tag->set_attribute( 'data-lazy-srcset-webp', $srcset_webp );
						}
					}
					if ( $image_tag->html !== $image ) {
						$image_tag->set_attribute( 'class', 'ewww_webp_lazy_load', true );
						$buffer = \str_replace( $image, $image_tag->html, $buffer );
					}
				} elseif ( ! empty( $file ) && $image_tag->get_attribute( 'data-src' ) && ( 'image' === $image_tag->get_attribute( 'data-lazy-type' ) || $image_tag->attribute_contains( 'class', 'lazyload' ) ) ) {
					// a3 or EWWW IO Lazy Load.
					$real_url = $image_tag->get_attribute( 'data-src' );
					$this->debug_message( "checking webp for Lazy Load data-src: $real_url" );
					$validated_real_path = $this->validate_image_url( $real_url );
					if ( $validated_real_path ) {
						$this->debug_message( 'found webp for Lazy Load' );
						$image_tag->set_attribute( 'data-src-webp', $this->generate_url( $real_url, $validated_real_path ) );
					}
					$srcset = $image_tag->get_attribute( 'data-srcset' );
					if ( $srcset ) {
						$srcset_webp = $this->srcset_replace( $srcset );
						if ( $srcset_webp ) {
							$image_tag->set_attribute( 'data-srcset-webp', $srcset_webp );
						}
					}
					if ( $image_tag->html !== $image ) {
						$image_tag->set_attribute( 'class', 'ewww_webp_lazy_load', true );
						$buffer = \str_replace( $image, $image_tag->html, $buffer );
					}
				} elseif ( ! empty( $file ) && \strpos( $image, 'data-lazysrc=' ) && \strpos( $image, '/essential-grid' ) ) {
					// Essential Grid.
					$new_image = $image;
					$real_url  = $this->get_attribute( $new_image, 'data-lazysrc' );
					$this->debug_message( "checking webp for EG Lazy Load data-lazysrc: $real_url" );
					$validated_real_path = $this->validate_image_url( $real_url );
					if ( $validated_real_path ) {
						$this->debug_message( "found webp for Lazy Load: $real_url" );
						$this->set_attribute( $new_image, 'data-lazysrc-webp', $this->generate_url( $real_url, $validated_real_path ) );
					}
					if ( $new_image !== $image ) {
						$this->set_attribute( $new_image, 'class', $this->get_attribute( $new_image, 'class' ) . ' ewww_webp_lazy_load', true );
						$buffer = \str_replace( $image, $new_image, $buffer );
					}
				} elseif ( empty( $file ) && $image_tag->get_attribute( 'data-src' ) && $image_tag->get_attribute( 'data-srcset' ) && $image_tag->attribute_contains( 'class', 'lazyload' ) ) {
					$this->debug_message( 'found img without src' );
					// EWWW IO Lazy Load, but no src attribute.
					$real_url = \trim( $image_tag->get_attribute( 'data-src' ) );
					$this->debug_message( "checking webp for Lazy Load data-src: $real_url" );
					$validated_real_path = $this->validate_image_url( $real_url );
					if ( $validated_real_path ) {
						$this->debug_message( 'found webp for Lazy Load' );
						$image_tag->set_attribute( 'data-src-webp', $this->generate_url( $real_url, $validated_real_path ) );
					}
					$srcset = $image_tag->get_attribute( 'data-srcset' );
					if ( $srcset ) {
						$srcset_webp = $this->srcset_replace( $srcset );
						if ( $srcset_webp ) {
							$image_tag->set_attribute( 'data-srcset-webp', $srcset_webp );
						}
					}
					if ( $image_tag->html !== $image ) {
						$image_tag->set_attribute( 'class', 'ewww_webp_lazy_load', true );
						$buffer = \str_replace( $image, $image_tag->html, $buffer );
					}
				} elseif ( ( empty( $file ) || \str_contains( $image, 'R0lGODlhAQABAIAAAAAAAP' ) ) && $image_tag->get_attribute( 'data-srcset' ) && $image_tag->attribute_contains( 'class', 'lazyload' ) ) {
					$srcset = $image_tag->get_attribute( 'data-srcset' );
					$this->debug_message( 'checking webp for Retina Lazy Load data-src' );
					if ( $srcset ) {
						$srcset_webp = $this->srcset_replace( $srcset );
						if ( $srcset_webp ) {
							$image_tag->set_attribute( 'data-srcset-webp', $srcset_webp );
						}
					}
					if ( $image_tag->html !== $image ) {
						$image_tag->set_attribute( 'class', 'ewww_webp_lazy_load', true );
						$buffer = \str_replace( $image, $image_tag->html, $buffer );
					}
				}
			} // End foreach().
		}
		// Images listed as picture/source elements.
		$pictures = $this->get_picture_tags_from_html( $buffer );
		if ( $this->is_iterable( $pictures ) ) {
			foreach ( $pictures as $index => $picture ) {
				if ( \str_contains( $picture, 'image/webp' ) ) {
					continue;
				}
				$elements = $this->get_elements_from_html( $picture );
				if ( ! $this->is_iterable( $elements ) ) {
					continue;
				}
				$sources = array();
				foreach ( $elements as $element ) {
					$element_tag = new HTML_Tag( $element );
					if ( ! $this->validate_tag( $element_tag ) ) {
						continue 2;
					}
					if ( \str_starts_with( $element, '<source' ) ) {
						$sources[] = $element;
					}
				}
				if ( empty( $sources ) ) {
					$sources = $this->get_elements_from_html( $picture, 'source' );
				}
				if ( $this->is_iterable( $sources ) ) {
					foreach ( $sources as $source ) {
						$this->debug_message( "parsing a picture source: $source" );
						$source_tag       = new HTML_Tag( $source );
						$srcset_attr_name = 'srcset';
						if ( \str_contains( $source, 'base64,R0lGOD' ) && $source_tag->get_attribute( 'data-srcset' ) ) {
							$srcset_attr_name = 'data-srcset';
						} elseif ( ! $source_tag->get_attribute( $srcset_attr_name ) && $source_tag->get_attribute( 'data-srcset' ) ) {
							$srcset_attr_name = 'data-srcset';
						}
						$srcset = $source_tag->get_attribute( $srcset_attr_name );
						if ( $srcset ) {
							$srcset_webp = $this->srcset_replace( $srcset );
							if ( $srcset_webp ) {
								$source_tag->set_attribute( $srcset_attr_name, $srcset_webp );
								$source_tag->set_attribute( 'type', 'image/webp' );
								$picture = \str_replace( $source, $source_tag->html . $source, $picture );
							}
						}
					}
					if ( $picture !== $pictures[ $index ] ) {
						$this->debug_message( 'found webp for picture element' );
						$buffer = \str_replace( $pictures[ $index ], $picture, $buffer );
					}
				}
			}
		}
		// NextGEN slides listed as 'a' elements and LL 'a' background images.
		$links = $this->get_elements_from_html( $buffer, 'a' );
		if ( $this->is_iterable( $links ) ) {
			foreach ( $links as $index => $link ) {
				$this->debug_message( "parsing a link $link" );
				$link_tag = new HTML_Tag( $link );
				if ( ! $this->validate_tag( $link_tag ) ) {
					continue;
				}
				$file  = $link_tag->get_attribute( 'data-src' );
				$thumb = $link_tag->get_attribute( 'data-thumbnail' );
				if ( $file && $thumb ) {
					$this->debug_message( "checking webp for ngg data-src: $file" );
					$validated_file_path = $this->validate_image_url( $file );
					if ( $validated_file_path ) {
						$link_tag->set_attribute( 'data-webp', $this->generate_url( $file, $validated_file_path ) );
						$this->debug_message( "found webp for ngg data-src: $file" );
					}
					$this->debug_message( "checking webp for ngg data-thumbnail: $thumb" );
					$validated_file_path = $this->validate_image_url( $thumb );
					if ( $validated_file_path ) {
						$link_tag->set_attribute( 'data-webp-thumbnail', $this->generate_url( $thumb, $validated_file_path ) );
						$this->debug_message( "found webp for ngg data-thumbnail: $thumb" );
					}
				}
				$bg_image = $link_tag->get_attribute( 'data-back' );
				if ( $bg_image && $link_tag->attribute_contains( 'class', 'lazyload' ) ) {
					$this->debug_message( "checking a/link for LL data-back: $bg_image" );
					$validated_bg_path = $this->validate_image_url( $bg_image );
					if ( $validated_bg_path ) {
						$link_tag->set_attribute( 'data-back-webp', $this->generate_url( $bg_image, $validated_bg_path ) );
						$this->debug_message( 'found webp for LL data-back' );
					}
				}
				if ( $link_tag->html !== $links[ $index ] ) {
					$buffer = \str_replace( $links[ $index ], $link_tag->html, $buffer );
				}
			}
		}
		// WooCommerce thumbs listed as 'div' elements and LL div backgrounds.
		$divs = $this->get_elements_from_html( $buffer, 'div' );
		if ( $this->is_iterable( $divs ) ) {
			foreach ( $divs as $index => $div ) {
				$this->debug_message( 'parsing a div' );
				$div_tag = new HTML_Tag( $div );
				if ( ! $this->validate_tag( $div_tag ) ) {
					continue;
				}
				$thumb = $div_tag->get_attribute( 'data-thumb' );
				if ( $thumb && $div_tag->attribute_contains( 'class', 'woocommerce-product-gallery__image' ) ) {
					$this->debug_message( "checking webp for WC data-thumb: $thumb" );
					$validated_thumb_path = $this->validate_image_url( $thumb );
					if ( $validated_thumb_path ) {
						$div_tag->set_attribute( 'data-webp-thumb', $this->generate_url( $thumb, $validated_thumb_path ) );
						$this->debug_message( 'found webp for WC data-thumb' );
					}
				}
				$bg_image = $div_tag->get_attribute( 'data-back' );
				if ( $bg_image && $div_tag->attribute_contains( 'class', 'lazyload' ) ) {
					$this->debug_message( "checking div for LL data-back: $bg_image" );
					$validated_bg_path = $this->validate_image_url( $bg_image );
					if ( $validated_bg_path ) {
						$div_tag->set_attribute( 'data-back-webp', $this->generate_url( $bg_image, $validated_bg_path ) );
						$this->debug_message( 'found webp for LL data-back' );
					}
				}
				if ( $div_tag->html !== $div ) {
					$buffer = \str_replace( $div, $div_tag->html, $buffer );
				}
			}
		}
		// Video elements, looking for poster attributes that are images.
		$videos = $this->get_elements_from_html( $buffer, 'video' );
		if ( $this->is_iterable( $videos ) ) {
			foreach ( $videos as $index => $video ) {
				$this->debug_message( 'parsing a video element' );
				$video_tag = new HTML_Tag( $video );
				if ( ! $this->validate_tag( $video_tag ) ) {
					continue;
				}
				$file = $video_tag->get_attribute( 'poster' );
				if ( $file ) {
					$this->debug_message( "checking webp for video poster: $file" );
					$validated_file_path = $this->validate_image_url( $file );
					if ( $validated_file_path ) {
						$video_tag->set_attribute( 'data-poster-webp', $this->generate_url( $file, $validated_file_path ) );
						$video_tag->set_attribute( 'data-poster-image', $file );
						$video_tag->remove_attribute( 'poster' );
						$this->debug_message( "found webp for video poster: $file" );
						$buffer = \str_replace( $videos[ $index ], $video_tag->html, $buffer );
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
	public function ngg_pro_lightbox_images_queue( $images ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( $this->is_iterable( $images ) ) {
			foreach ( $images as $index => $image ) {
				if ( ! empty( $image['image'] ) ) {
					$validated_path = $this->validate_image_url( $image['image'] );
					if ( $validated_path ) {
						$images[ $index ]['image-webp'] = $this->generate_url( $image['image'], $validated_path );
					}
				}
				if ( ! empty( $image['thumb'] ) ) {
					$validated_thumb_path = $this->validate_image_url( $image['thumb'] );
					if ( $validated_thumb_path ) {
						$images[ $index ]['thumb-webp'] = $this->generate_url( $image['thumb'], $validated_thumb_path );
					}
				}
				if ( ! empty( $image['full_image'] ) ) {
					$validated_full_image_path = $this->validate_image_url( $image['full_image'] );
					if ( $validated_full_image_path ) {
						$images[ $index ]['full_image_webp'] = $this->generate_url( $image['full_image'], $validated_full_image_path );
					}
				}
				if ( $this->is_iterable( $image['srcsets'] ) ) {
					foreach ( $image['srcsets'] as $size => $srcset ) {
						$validated_path = $this->validate_image_url( $srcset );
						if ( $validated_path ) {
							$images[ $index ]['srcsets'][ $size . '-webp' ] = $this->generate_url( $srcset, $validated_path );
						}
					}
				}
				if ( $this->is_iterable( $image['full_srcsets'] ) ) {
					foreach ( $image['full_srcsets'] as $size => $srcset ) {
						$validated_path = $this->validate_image_url( $srcset );
						if ( $validated_path ) {
							$images[ $index ]['full_srcsets'][ $size . '-webp' ] = $this->generate_url( $srcset, $validated_path );
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
	public function woocommerce_available_variation( $variation ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( $this->is_iterable( $variation ) && $this->is_iterable( $variation['image'] ) ) {
			if ( ! empty( $variation['image']['src'] ) ) {
				$validated_path = $this->validate_image_url( $variation['image']['src'] );
				if ( $validated_path ) {
					$variation['image']['src_webp'] = $this->generate_url( $variation['image']['src'], $validated_path );
				}
			}
			if ( ! empty( $variation['image']['full_src'] ) ) {
				$validated_full_src_path = $this->validate_image_url( $variation['image']['full_src'] );
				if ( $validated_full_src_path ) {
					$variation['image']['full_src_webp'] = $this->generate_url( $variation['image']['full_src'], $validated_full_src_path );
				}
			}
			if ( ! empty( $variation['image']['gallery_thumbnail_src'] ) ) {
				$validated_gallery_thumbnail_src_path = $this->validate_image_url( $variation['image']['gallery_thumbnail_src'] );
				if ( $validated_gallery_thumbnail_src_path ) {
					$variation['image']['gallery_thumbnail_src_webp'] = $this->generate_url( $variation['image']['gallery_thumbnail_src'], $validated_gallery_thumbnail_src_path );
				}
			}
			if ( ! empty( $variation['image']['thumb_src'] ) ) {
				$validated_thumb_src_path = $this->validate_image_url( $variation['image']['thumb_src'] );
				if ( $validated_thumb_src_path ) {
					$variation['image']['thumb_src_webp'] = $this->generate_url( $variation['image']['thumb_src'], $validated_thumb_src_path );
				}
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
	public function filter_facetwp_json_output( $output ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( empty( $output['template'] ) || ! \is_string( $output['template'] ) ) {
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
	 *
	 * Mostly for our Lazy Loader at this point, since it uses JSON when multiple
	 * background images are used on a single element.
	 *
	 * @param array $image_urls An array of image URLs.
	 * @return array An array with WebP image URLs.
	 */
	public function filter_image_url_array( $image_urls ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( $this->is_iterable( $image_urls ) ) {
			foreach ( $image_urls as $index => $image_url ) {
				$this->debug_message( "checking $image_url for a WebP variant" );
				$validated_path = $this->validate_image_url( $image_url );
				if ( ! empty( $image_url ) && $validated_path ) {
					$image_urls[ $index ] = $this->generate_url( $image_url, $validated_path );
				}
			}
		}
		return $image_urls;
	}

	/**
	 * Parse an image URL and replace it with WebP.
	 *
	 * @param string $image_url An image URL.
	 * @return string|bool  A WebP image URL or false.
	 */
	public function filter_image_url( $image_url ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->debug_message( "checking $image_url for a WebP variant" );
		$validated_path = $this->validate_image_url( $image_url );
		if ( ! empty( $image_url ) && $validated_path ) {
			$image_url = $this->generate_url( $image_url, $validated_path );
		}
		return $image_url;
	}

	/**
	 * Converts a URL to a file-system path and checks if the resulting path exists.
	 *
	 * @param string $url The URL to mangle.
	 * @param string $extension An optional extension to append during is_file().
	 * @return bool True if a local file exists correlating to the URL, false otherwise.
	 */
	public function url_to_path_exists( $url, $extension = '' ) {
		return parent::url_to_path_exists( $url, '.webp' );
	}

	/**
	 * Validate the user-defined exclusions.
	 */
	public function validate_user_exclusions() {
		$user_exclusions = $this->get_option( $this->prefix . 'webp_rewrite_exclude' );
		$this->debug_message( $this->prefix . 'webp_rewrite_exclude' );
		if ( ! empty( $user_exclusions ) ) {
			if ( \is_string( $user_exclusions ) ) {
				$user_exclusions = array( $user_exclusions );
			}
			if ( \is_array( $user_exclusions ) ) {
				foreach ( $user_exclusions as $exclusion ) {
					if ( ! \is_string( $exclusion ) ) {
						continue;
					}
					$exclusion = \trim( $exclusion );
					if ( 0 === \strpos( $exclusion, 'page:' ) ) {
						$this->user_page_exclusions[] = \str_replace( 'page:', '', $exclusion );
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
	 * @param HTML_Tag $image_tag The HTML_Tag: img, span, etc.
	 * @return bool False if it flags a filter or exclusion, true otherwise.
	 */
	public function validate_tag( $image_tag ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );

		if ( ! $image_tag instanceof HTML_Tag ) {
			$this->debug_message( 'not an HTML_Tag' );
			return false;
		}

		$tag_name = $image_tag->get_tag_name();
		if ( \in_array( $tag_name, $this->user_element_exclusions, true ) ) {
			$this->debug_message( "$tag_name tag skipped" );
			return false;
		}

		$exclusions = \apply_filters(
			'ewwwio_js_webp_exclusions',
			\array_merge(
				array(
					'timthumb.php?',
					'wpcf7_captcha/',
				),
				$this->user_exclusions
			),
			$image_tag->html
		);
		foreach ( $exclusions as $exclusion ) {
			if ( \str_contains( $image_tag->html, $exclusion ) ) {
				$this->debug_message( "tag matched $exclusion" );
				return false;
			}
		}

		// Ignore 0-size Pinterest schema images.
		if ( $image_tag->get_attribute( 'data-pin-description' ) && '0' === $image_tag->get_attribute( 'width' ) && '0' === $image_tag->get_attribute( 'height' ) ) {
			$this->debug_message( 'data-pin-description img skipped' );
			return false;
		}

		return true;
	}

	/**
	 * Checks if the path has a valid WebP image copy, on-disk or forced.
	 *
	 * @param string $image The image URL.
	 * @return bool True if the file exists or matches a forced path, false otherwise.
	 */
	public function validate_image_url( $image ) {
		$this->debug_message( __METHOD__ . "() webp validation for $image" );
		if ( empty( $image ) ) {
			return false;
		}
		if ( $this->is_lazy_placeholder( $image ) ) {
			return false;
		}
		// Cleanup the image from encoded HTML characters.
		$image = \str_replace( '&#038;', '&', $image );
		$image = \str_replace( '#038;', '&', $image );

		$extension  = '';
		$image_path = $this->parse_url( $image, PHP_URL_PATH );
		if ( ! \is_null( $image_path ) && $image_path ) {
			$extension = \strtolower( \pathinfo( $image_path, PATHINFO_EXTENSION ) );
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
		if ( $extension && 'avif' === $extension ) {
			return false;
		}
		if ( \apply_filters( 'ewww_image_optimizer_skip_webp_rewrite', false, $image ) ) {
			return false;
		}
		if ( $this->get_option( 'ewww_image_optimizer_webp_force' ) && $this->is_iterable( $this->allowed_urls ) ) {
			if ( $extension && 'png' === $extension && $this->get_option( 'ewww_image_optimizer_jpg_only_mode' ) ) {
				return false;
			}
			// Check the image for configured CDN paths.
			foreach ( $this->allowed_urls as $allowed_url ) {
				if ( \strpos( $image, $allowed_url ) !== false ) {
					$this->debug_message( 'forced cdn image' );
					return true;
				}
			}
		} elseif ( $this->allowed_urls && $this->allowed_domains ) {
			$maybe_path = $this->cdn_to_local( $image );
			if ( $maybe_path ) {
				return $maybe_path;
			}
		}
		return $this->url_to_path_exists( $image );
	}

	/**
	 * Generate a WebP URL per the correct WebP naming pattern.
	 *
	 * @param string $url The image url.
	 * @param string $path The image path.
	 * @return string The WebP version of the image url.
	 */
	public function generate_url( $url, $path ) {
		$url_parts     = \explode( '?', $url );
		$queryless_url = $url_parts[0];
		if ( true === $path ) {
			$webp_url = ewww_image_optimizer_get_webp_path( $queryless_url );
			ewwwio_debug_message( "unknown path, used default pattern for $queryless_url to get $webp_url" );
		} else {
			$webp_url = ewww_image_optimizer_get_webp_url( $path, $queryless_url );
			ewwwio_debug_message( "path $path used for $queryless_url to get $webp_url" );
		}
		if ( ! empty( $url_parts[1] ) && ! str_contains( $url_parts[1], 'is-pending-load' ) ) {
			$webp_url .= '?' . $url_parts[1];
		}
		return apply_filters( 'ewwwio_generated_webp_image_url', $webp_url, $url );
	}

	/**
	 * Load full WebP script when SCRIPT_DEBUG is enabled.
	 */
	public function debug_script() {
		if ( ! $this->should_process_page() ) {
			return;
		}
		if ( ! \apply_filters( 'eio_do_js_webp', true, parent::$request_uri ) ) {
			return;
		}
		$check_args = array(
			'strategy'  => 'async',
			'in_footer' => false,
		);
		$load_args  = array(
			'strategy'  => 'async',
			'in_footer' => true,
		);
		if ( ! \ewww_image_optimizer_ce_webp_enabled() ) {
			\wp_enqueue_script( 'ewww-webp-check-script', \plugins_url( '/includes/check-webp.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), $this->version, $check_args );
			\wp_enqueue_script( 'ewww-webp-load-script', \plugins_url( '/includes/load-webp.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), $this->version, $load_args );
		}
	}

	/**
	 * Load minified WebP script when EWWW_IMAGE_OPTIMIZER_WEBP_EXTERNAL_SCRIPT is set.
	 */
	public function min_external_script() {
		if ( ! $this->should_process_page() ) {
			return;
		}
		if ( ! \apply_filters( 'eio_do_js_webp', true, parent::$request_uri ) ) {
			return;
		}
		$check_args = array(
			'strategy'  => 'async',
			'in_footer' => false,
		);
		$load_args  = array(
			'strategy'  => 'async',
			'in_footer' => true,
		);
		if ( ! \ewww_image_optimizer_ce_webp_enabled() ) {
			\wp_enqueue_script( 'ewww-webp-check-script', \plugins_url( '/includes/check-webp.min.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), $this->version, $check_args );
			\wp_enqueue_script( 'ewww-webp-load-script', \plugins_url( '/includes/load-webp.min.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), $this->version, $load_args );
		}
	}

	/**
	 * Load minified inline version of check WebP script.
	 */
	public function inline_check_script() {
		if ( ! $this->should_process_page() ) {
			return;
		}
		if ( ! \apply_filters( 'eio_do_js_webp', true, parent::$request_uri ) ) {
			return;
		}
		if ( \defined( 'EWWW_IMAGE_OPTIMIZER_NO_JS' ) && EWWW_IMAGE_OPTIMIZER_NO_JS ) {
			return;
		}
		$this->debug_message( 'inlining check webp script' );
		echo '<script';
		foreach ( $this->inline_script_attrs as $attr_name => $attr_value ) {
			if ( empty( $attr_name ) || empty( $attr_value ) ) {
				continue;
			}
			if ( \preg_match( '/[^a-z0-9_-]/i', $attr_name ) ) {
				continue;
			}
			echo ' ' . \esc_html( $attr_name ) . '="' . \esc_attr( $attr_value ) . '"';
		}
		echo '>' . $this->check_webp_script . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Load minified inline version of load WebP script.
	 */
	public function inline_load_script() {
		if ( \defined( 'EWWW_IMAGE_OPTIMIZER_NO_JS' ) && EWWW_IMAGE_OPTIMIZER_NO_JS ) {
			return;
		}
		if ( ! $this->should_process_page() ) {
			return;
		}
		if ( ! \apply_filters( 'eio_do_js_webp', true, parent::$request_uri ) ) {
			return;
		}
		$this->debug_message( 'inlining load webp script' );
		echo '<script';
		foreach ( $this->inline_script_attrs as $attr_name => $attr_value ) {
			if ( empty( $attr_name ) || empty( $attr_value ) ) {
				continue;
			}
			if ( \preg_match( '/[^a-z0-9_-]/i', $attr_name ) ) {
				continue;
			}
			echo ' ' . \esc_html( $attr_name ) . '="' . \esc_attr( $attr_value ) . '"';
		}
		echo '>' . $this->load_webp_script . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

global $eio_js_webp;
$eio_js_webp = new JS_Webp();
