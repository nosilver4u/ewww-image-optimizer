<?php
/**
 * Class and methods to implement ExactDN (based on Photon implementation).
 *
 * @link https://ewww.io
 * @package EIO
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enables plugin to filter the page content and replace image urls with ExactDN urls.
 */
class ExactDN extends Page_Parser {

	/**
	 * A list of user-defined exclusions, populated by validate_user_exclusions().
	 *
	 * @access protected
	 * @var array $user_exclusions
	 */
	protected $user_exclusions = array();

	/**
	 * A list of user-defined page/URL exclusions, populated by validate_user_exclusions().
	 *
	 * @access protected
	 * @var array $user_page_exclusions
	 */
	protected $user_page_exclusions = array();

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
	 * Indicates if this is a full-width page that should ignore content_width < 1920.
	 *
	 * @access public
	 * @var bool $full_width
	 */
	public $full_width = false;

	/**
	 * List of permitted domains for ExactDN rewriting.
	 *
	 * @access public
	 * @var array $allowed_domains
	 */
	public $allowed_domains = array();

	/**
	 * Path portion to remove at beginning of URL, usually for path-style S3 domains.
	 *
	 * @access public
	 * @var string $remove_path
	 */
	public $remove_path = '';

	/**
	 * Folder name for the WP content directory (typically wp-content).
	 *
	 * @access public
	 * @var string $content_path
	 */
	public $content_path = 'wp-content';

	/**
	 * Folder name for the WP includes directory (typically wp-includes).
	 *
	 * @access public
	 * @var string $include_path
	 */
	public $include_path = 'wp-includes';

	/**
	 * Folder name for the WP uploads directory (typically 'uploads').
	 *
	 * @access public
	 * @var string $uploads_path
	 */
	public $uploads_path = 'uploads';

	/**
	 * The ExactDN domain/zone.
	 *
	 * @access private
	 * @var float $elapsed_time
	 */
	private $exactdn_domain = false;

	/**
	 * Is this a sub-folder network/multi-site install?
	 *
	 * @access private
	 * @var bool $sub_folder
	 */
	private $sub_folder = false;

	/**
	 * A list of domains (comma-separated) that can be delivered via the Easy IO domain.
	 *
	 * @access private
	 * @var string $asset_domains
	 */
	private $asset_domains = '';

	/**
	 * The Easy IO Plan/Tier ID
	 *
	 * @access private
	 * @var int $plan_id
	 */
	private $plan_id = 1;

	/**
	 * The detected site scheme (http/https).
	 *
	 * @access private
	 * @var string $scheme
	 */
	private $scheme = false;

	/**
	 * Allow us to track how much overhead ExactDN introduces.
	 *
	 * @access private
	 * @var float $elapsed_time
	 */
	private $elapsed_time = 0;

	/**
	 * Keep track of the attribute we use for srcset, in case a lazy load plugin is active.
	 *
	 * @access private
	 * @var string $srcset_attr
	 */
	private $srcset_attr = 'srcset';

	/**
	 * If we've replaced the Google Fonts this will be set to either 'easyio' or 'bunny'.
	 *
	 * @access private
	 * @var string $replaced_google_fonts
	 */
	private $replaced_google_fonts = '';

	/**
	 * Request URI.
	 *
	 * @var string $request_uri
	 */
	public $request_uri = '';

	/**
	 * Register (once) actions and filters for ExactDN. If you want to use this class, use the global.
	 */
	public function __construct() {
		parent::__construct();
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		global $exactdn;
		if ( \is_object( $exactdn ) ) {
			$this->debug_message( 'you are doing it wrong' );
			return;
		}
		$this->content_url();

		// Bail out on customizer.
		if ( \is_customize_preview() ) {
			return;
		}

		$this->request_uri = \add_query_arg( '', '' );
		if ( false === \strpos( $this->request_uri, 'page=ewww-image-optimizer-options' ) ) {
			$this->debug_message( "request uri is {$this->request_uri}" );
		} else {
			$this->debug_message( 'request uri is EWWW IO settings' );
		}

		if ( '/robots.txt' === $this->request_uri || '/sitemap.xml' === $this->request_uri ) {
			return;
		}

		\add_filter( 'exactdn_skip_page', array( $this, 'skip_page' ), 10, 2 );

		/**
		 * Allow pre-empting the parsers by page.
		 *
		 * @param bool Whether to skip parsing the page.
		 * @param string The URI/path of the page.
		 */
		if ( \apply_filters( 'exactdn_skip_page', false, $this->request_uri ) ) {
			return;
		}

		if ( ! $this->scheme ) {
			$scheme = 'http';
			if ( \strpos( $this->site_url, 'https://' ) !== false ) {
				$this->debug_message( "{$this->site_url} contains https" );
				$scheme = 'https';
			} elseif ( \strpos( \get_home_url(), 'https://' ) !== false ) {
				$this->debug_message( \get_home_url() . ' contains https' );
				$scheme = 'https';
			} elseif ( \strpos( $this->content_url, 'https://' ) !== false ) {
				$this->debug_message( $this->content_url . ' contains https' );
				$scheme = 'https';
			} elseif ( isset( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) {
				$this->debug_message( 'page requested over https' );
				$scheme = 'https';
			} else {
				$this->debug_message( 'using plain http' );
			}
			$this->scheme = $scheme;
		}

		if ( \is_multisite() && \defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL ) {
			$this->debug_message( 'working in sub-domain mode' );
		} elseif ( \is_multisite() ) {
			if ( \defined( 'EXACTDN_SUB_FOLDER' ) && EXACTDN_SUB_FOLDER ) {
				$this->sub_folder = true;
				$this->debug_message( 'working in sub-folder mode due to constant override' );
			} elseif ( \defined( 'EXACTDN_SUB_FOLDER' ) ) {
				$this->debug_message( 'working in sub-domain mode due to constant override' );
			} elseif ( \get_site_option( 'exactdn_sub_folder' ) ) {
				$this->sub_folder = true;
				$this->debug_message( 'working in sub-folder mode due to global option' );
			} elseif ( \get_current_blog_id() > 1 ) {
				$network_site_url = \network_site_url();
				$network_domain   = $this->parse_url( $network_site_url, PHP_URL_HOST );
				if ( $network_domain === $this->upload_domain ) {
					$this->sub_folder = true;
					$this->debug_message( 'working in sub-folder mode due to matching domain' );
				}
			}
		}

		// Make sure we have an ExactDN domain to use.
		if ( ! $this->setup() ) {
			return;
		}
		// Enables scheduled health checks via wp-cron.
		\add_action( 'easyio_verification_checkin', array( $this, 'health_check' ) );

		if ( empty( $this->asset_domains ) ) {
			$this->asset_domains = \apply_filters( 'exactdn_asset_domains', $this->get_exactdn_option( 'asset_domains' ) );
		}

		// Images in post content and galleries.
		\add_filter( 'the_content', array( $this, 'filter_the_content' ), 999999 );
		// Start an output buffer before any output starts.
		\add_filter( $this->prefix . 'filter_page_output', array( $this, 'filter_page_output' ), 5 );

		// Core image retrieval.
		if ( \defined( 'EIO_DISABLE_DEEP_INTEGRATION' ) && EIO_DISABLE_DEEP_INTEGRATION ) {
			$this->debug_message( 'deep (image_downsize) integration disabled' );
		} elseif ( ! \function_exists( '\aq_resize' ) ) {
			\add_filter( 'image_downsize', array( $this, 'filter_image_downsize' ), 10, 3 );
		} else {
			$this->debug_message( 'aq_resize detected, image_downsize filter disabled' );
		}
		// Disable image_downsize filter during themify_get_image().
		\add_action( 'themify_before_post_image', array( $this, 'disable_image_downsize' ) );
		if ( \defined( 'EXACTDN_IMAGE_DOWNSIZE_SCALE' ) && EXACTDN_IMAGE_DOWNSIZE_SCALE ) {
			\add_action( 'exactdn_image_downsize_array', array( $this, 'image_downsize_scale' ) );
		}

		// Check REST API requests to see if ExactDN should be running.
		\add_filter( 'rest_request_before_callbacks', array( $this, 'parse_restapi_maybe' ), 10, 3 );

		// Check to see if the OMGF plugin is active, and suppress our font rewriting if it is.
		if ( ( \defined( 'OMGF_PLUGIN_FILE' ) || \defined( 'OMGF_DB_VERSION' ) ) && ! \defined( 'EASYIO_REPLACE_GOOGLE_FONTS' ) ) {
			\define( 'EASYIO_REPLACE_GOOGLE_FONTS', false );
		}

		// Overrides for admin-ajax images.
		\add_filter( 'exactdn_admin_allow_image_downsize', array( $this, 'allow_admin_image_downsize' ), 10, 2 );
		\add_filter( 'exactdn_admin_allow_image_srcset', array( $this, 'allow_admin_image_downsize' ), 10, 2 );
		\add_filter( 'exactdn_admin_allow_plugin_url', array( $this, 'allow_admin_image_downsize' ), 10, 2 );
		// Overrides for "pass through" images.
		\add_filter( 'exactdn_pre_args', array( $this, 'exactdn_remove_args' ), 10, 3 );
		// Overrides for user exclusions.
		\add_filter( 'exactdn_skip_image', array( $this, 'exactdn_skip_user_exclusions' ), 9, 2 );
		\add_filter( 'exactdn_skip_for_url', array( $this, 'exactdn_skip_user_exclusions' ), 9, 2 );

		// Responsive image srcset substitution.
		\add_filter( 'wp_calculate_image_srcset', array( $this, 'filter_srcset_array' ), 1001, 5 );
		\add_filter( 'wp_calculate_image_sizes', array( $this, 'filter_sizes' ), 1, 2 ); // Early so themes can still filter.

		// Filter for generic use by other plugins/themes.
		\add_filter( 'exactdn_local_to_cdn_url', array( $this, 'plugin_get_image_url' ) );

		// Filter for Divi Pixel plugin SVG images.
		\add_filter( 'dipi_image_mask_image_url', array( $this, 'plugin_get_image_url' ) );

		// Filter to check for Elementor full_width layouts.
		\add_filter( 'elementor/frontend/builder_content_data', array( $this, 'elementor_builder_content_data' ) );

		// Filter for FacetWP JSON responses.
		\add_filter( 'facetwp_render_output', array( $this, 'filter_facetwp_json_output' ) );

		// Filter for Envira image URLs.
		\add_filter( 'envira_gallery_output_item_data', array( $this, 'envira_gallery_output_item_data' ) );
		\add_filter( 'envira_gallery_image_src', array( $this, 'plugin_get_image_url' ) );

		// Filters for BuddyBoss image URLs.
		\add_filter( 'bp_media_get_preview_image_url', array( $this, 'bp_media_get_preview_image_url' ), PHP_INT_MAX, 5 );
		\add_filter( 'bb_video_get_thumb_url', array( $this, 'bb_video_get_thumb_url' ), PHP_INT_MAX, 5 );
		\add_filter( 'bp_document_get_preview_url', array( $this, 'bp_document_get_preview_url' ), PHP_INT_MAX, 6 );
		\add_filter( 'bb_video_get_symlink', array( $this, 'bb_video_get_symlink' ), PHP_INT_MAX, 4 );
		// Filters for BuddyBoss URL symlinks.
		\add_filter( 'bb_media_do_symlink', array( $this, 'buddyboss_do_symlink' ), PHP_INT_MAX );
		\add_filter( 'bb_document_do_symlink', array( $this, 'buddyboss_do_symlink' ), PHP_INT_MAX );
		\add_filter( 'bb_video_do_symlink', array( $this, 'buddyboss_do_symlink' ), PHP_INT_MAX );
		\add_filter( 'bb_video_create_thumb_symlinks', array( $this, 'buddyboss_do_symlink' ), PHP_INT_MAX );
		// Filters for BuddyBoss media access checks.
		\add_filter( 'bb_media_check_default_access', '__return_true', PHP_INT_MAX );
		\add_filter( 'bb_media_settings_callback_symlink_direct_access', array( $this, 'buddyboss_media_directory_allow_access' ), PHP_INT_MAX, 2 );

		// Filter for NextGEN image URLs within JS.
		\add_filter( 'ngg_pro_lightbox_images_queue', array( $this, 'ngg_pro_lightbox_images_queue' ) );
		\add_filter( 'ngg_get_image_url', array( $this, 'plugin_get_image_url' ) );

		// Filter Slider Revolution 7 REST API JSON.
		if ( \defined( 'EXACTDN_ENABLE_JSON_FILTERS' ) ) {
			\add_filter( 'sr_get_full_slider_JSON', array( $this, 'sr7_slider_object' ) );
		}
		// This one is just to get at the slider background image, do not use it for anything else.
		\add_filter( 'revslider_add_slider_base', array( $this, 'sr7_slider_object' ) );
		// This is for the slide background image contained in a <noscript>.
		\add_filter( 'sr_add_slide_background_image_url', array( $this, 'plugin_get_image_url' ) );
		\add_filter( 'sr_get_image_lists', array( $this, 'filter_sr7_image_lists' ) );

		// Filter for Spotlight Social Media Feeds.
		\add_filter( 'spotlight/instagram/server/transform_item', array( $this, 'spotlight_instagram_response' ) );

		// Filter for legacy WooCommerce API endpoints.
		\add_filter( 'woocommerce_api_product_response', array( $this, 'woocommerce_api_product_response' ) );

		// DNS prefetching.
		\add_filter( 'wp_resource_hints', array( $this, 'resource_hints' ), 100, 2 );

		// Get all the script/css urls and rewrite them (if enabled).
		if ( $this->get_option( 'exactdn_all_the_things' ) ) {
			\add_filter( 'style_loader_src', array( $this, 'parse_enqueue' ), 9999 );
			\add_filter( 'script_loader_src', array( $this, 'parse_enqueue' ), 9999 );
		}
		if ( ! $this->get_option( 'exactdn_prevent_db_queries' ) ) {
			$this->set_option( 'exactdn_prevent_db_queries', true );
		}

		// Improve the default content_width for Twenty Nineteen.
		global $content_width;
		if ( \function_exists( '\twentynineteen_setup' ) && 640 === (int) $content_width ) {
			$content_width = 932;
		}

		// Configure Autoptimize with our CDN domain.
		\add_filter( 'autoptimize_filter_cssjs_multidomain', array( $this, 'add_cdn_domain' ) );

		if ( $this->is_as3cf_cname_active() ) {
			\add_action( 'admin_notices', $this->prefix . 'notice_exactdn_as3cf_cname_active' );
			return;
		}

		$upload_url_parts = $this->parse_url( $this->site_url );
		if ( empty( $upload_url_parts ) ) {
			$this->debug_message( "could not break down URL: $this->site_url" );
			return;
		}

		$stored_local_domain = $this->get_exactdn_option( 'local_domain' );
		if ( empty( $stored_local_domain ) ) {
			$this->set_exactdn_option( 'local_domain', \base64_encode( $this->upload_domain ) );
			$stored_local_domain = $this->upload_domain;
		} elseif ( false !== \strpos( $stored_local_domain, '.' ) ) {
			$this->set_exactdn_option( 'local_domain', \base64_encode( $stored_local_domain ) );
		} else {
			$stored_local_domain = \base64_decode( $stored_local_domain );
		}
		$this->debug_message( "saved domain is $stored_local_domain" );

		$this->debug_message( "allowing images from here: $this->upload_domain" );
		if (
			(
				false !== \strpos( $this->upload_domain, 'amazonaws.com' ) ||
				false !== \strpos( $this->upload_domain, 'digitaloceanspaces.com' ) ||
				false !== \strpos( $this->upload_domain, 'storage.googleapis.com' )
			)
			&& ! empty( $upload_url_parts['path'] )
		) {
			$this->remove_path = \rtrim( $upload_url_parts['path'], '/' );
			$this->debug_message( "removing this from urls: $this->remove_path" );
		}
		if (
			$stored_local_domain !== $this->upload_domain &&
			! $this->allow_image_domain( $stored_local_domain ) &&
			\is_admin()
		) {
			\add_action( 'admin_notices', $this->prefix . 'notice_exactdn_domain_mismatch' );
		}
		$this->allowed_domains[] = $this->exactdn_domain;
		$this->allowed_domains   = \apply_filters( 'exactdn_allowed_domains', $this->allowed_domains );
		$this->debug_message( 'allowed domains: ' . \implode( ',', $this->allowed_domains ) );
		$this->debug_message( 'asset domains: ' . $this->asset_domains );
		$this->get_allowed_paths();
		$this->validate_user_exclusions();
	}

	/**
	 * If ExactDN is enabled, validates and configures the ExactDN domain name.
	 */
	public function setup() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// If we don't have a domain yet, go grab one.
		$this->plan_id = (int) $this->get_exactdn_option( 'plan_id' );
		$new_site      = false;
		if ( ! $this->get_exactdn_domain() ) {
			$this->debug_message( 'attempting to activate exactDN' );
			$exactdn_domain = $this->activate_site();
			$new_site       = true;
		} else {
			$this->debug_message( 'grabbing existing exactDN domain' );
			$exactdn_domain = $this->get_exactdn_domain();
		}
		if ( ! $exactdn_domain ) {
			\delete_option( $this->prefix . 'exactdn' );
			\delete_site_option( $this->prefix . 'exactdn' );
			$this->cron_setup( false );
			return false;
		}
		$verified = true;
		// If we have a domain, verify it.
		if ( $new_site ) {
			$verified = $this->verify_domain( $exactdn_domain );
			if ( $verified ) {
				// When this is a new site that is verified, setup health check.
				$this->cron_setup();
			}
		}
		if ( $verified ) {
			$this->exactdn_domain = $exactdn_domain;
			$this->debug_message( 'exactdn_domain: ' . $exactdn_domain );
			$this->debug_message( 'exactdn_plan_id: ' . $this->plan_id );
			return true;
		}
		\delete_option( $this->prefix . 'exactdn_domain' );
		\delete_option( $this->prefix . 'exactdn_verified' );
		\delete_site_option( $this->prefix . 'exactdn_domain' );
		\delete_site_option( $this->prefix . 'exactdn_verified' );
		$this->cron_setup( false );
		return false;
	}

	/**
	 * Setup wp_cron tasks for scheduled verification.
	 *
	 * @global object $wpdb
	 *
	 * @param bool $schedule True to add event, false to remove/unschedule it.
	 */
	public function cron_setup( $schedule = true ) {
		$this->debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		$event = 'easyio_verification_checkin';
		// Setup scheduled optimization if the user has enabled it, and it isn't already scheduled.
		if ( $schedule && ! \wp_next_scheduled( $event ) ) {
			$this->debug_message( "scheduling $event" );
			\wp_schedule_event( \time() + DAY_IN_SECONDS, \apply_filters( 'easyio_verification_schedule', 'daily', $event ), $event );
		} elseif ( $schedule ) {
			$this->debug_message( "$event already scheduled: " . \wp_next_scheduled( $event ) );
		} elseif ( \wp_next_scheduled( $event ) ) {
			$this->debug_message( "un-scheduling $event" );
			\wp_clear_scheduled_hook( $event );
			if ( ! \function_exists( '\is_plugin_active_for_network' ) && \is_multisite() ) {
				// Need to include the plugin library for the is_plugin_active function.
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if ( \is_multisite() && \is_plugin_active_for_network( \constant( \strtoupper( $this->prefix ) . 'PLUGIN_FILE_REL' ) ) ) {
				global $wpdb;
				$blogs = $wpdb->get_results( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs WHERE site_id = %d", $wpdb->siteid ), ARRAY_A );
				if ( $this->is_iterable( $blogs ) ) {
					foreach ( $blogs as $blog ) {
						\switch_to_blog( $blog['blog_id'] );
						\wp_clear_scheduled_hook( $event );
						\restore_current_blog();
					}
				}
			}
		}
	}

	/**
	 * Use the Site URL to get the zone domain.
	 */
	public function activate_site() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );

		if ( $this->is_as3cf_cname_active() ) {
			global $exactdn_activate_error;
			$exactdn_activate_error = 'as3cf_cname_active';
			\add_action( 'admin_notices', $this->prefix . 'notice_exactdn_as3cf_cname_active' );
			return false;
		}
		$site_url = $this->content_url();

		$url = 'http://optimize.exactlywww.com/exactdn/activate.php';
		$ssl = \wp_http_supports( array( 'ssl' ) );
		if ( $ssl ) {
			$url = \set_url_scheme( $url, 'https' );
		}
		\add_filter( 'http_headers_useragent', $this->prefix . 'cloud_useragent', PHP_INT_MAX );
		$result = \wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'body'    => array(
					'site_url' => $site_url,
					'home_url' => \home_url(),
				),
			)
		);
		if ( \is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			$this->debug_message( "exactdn activation request failed: $error_message" );
			global $exactdn_activate_error;
			$exactdn_activate_error = $error_message;
			\add_action( 'admin_notices', $this->prefix . 'notice_exactdn_activation_error' );
			return false;
		} elseif ( ! empty( $result['body'] ) && \strpos( $result['body'], 'domain' ) !== false ) {
			$response = \json_decode( $result['body'], true );
			if ( ! empty( $response['domain'] ) ) {
				if (
					false !== \strpos( $site_url, 'amazonaws.com' ) ||
					false !== \strpos( $site_url, 'digitaloceanspaces.com' ) ||
					false !== \strpos( $site_url, 'storage.googleapis.com' ) ||
					$this->s3_active
				) {
					$this->set_exactdn_option( 'verify_method', -1, false );
				}
				if ( ! empty( $response['plan_id'] ) ) {
					$this->set_exactdn_option( 'plan_id', (int) $response['plan_id'] );
					$this->plan_id = (int) $response['plan_id'];
				}
				if ( \get_option( 'exactdn_never_been_active' ) ) {
					$this->set_option( $this->prefix . 'lazy_load', true );
					$this->set_option( 'exactdn_lossy', true );
					$this->set_option( 'exactdn_all_the_things', true );
					\delete_option( 'exactdn_never_been_active' );
				}
				if ( \function_exists( '\envira_flush_all_cache' ) ) {
					\envira_flush_all_cache();
				}
				return $this->set_exactdn_domain( $response['domain'] );
			}
		} elseif ( ! empty( $result['body'] ) && false !== \strpos( $result['body'], 'error' ) ) {
			$response      = \json_decode( $result['body'], true );
			$error_message = $response['error'];
			$this->debug_message( "exactdn activation request failed: $error_message" );
			global $exactdn_activate_error;
			$exactdn_activate_error = $error_message;
			\add_action( 'admin_notices', $this->prefix . 'notice_exactdn_activation_error' );
			return false;
		}
		return false;
	}

	/**
	 * Do a health check to verify the Easy IO domain is still good.
	 */
	public function health_check() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->verify_domain( $this->exactdn_domain );
		$this->set_exactdn_option( 'checkin', time() - 60 );
	}

	/**
	 * Verify the ExactDN domain.
	 *
	 * @param string $domain The ExactDN domain to verify.
	 * @return bool Whether the domain is still valid.
	 */
	public function verify_domain( $domain ) {
		if ( empty( $domain ) ) {
			return false;
		}
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Check the time, to see how long it has been since we verified the domain.
		$last_checkin = (int) $this->get_exactdn_option( 'checkin' );
		if ( $this->get_exactdn_option( 'verified' ) && $last_checkin > time() ) {
			$this->debug_message( 'not time yet: ' . $this->human_time_diff( $last_checkin ) );
			return true;
		}

		$this->check_verify_method();
		$this->set_exactdn_option( 'checkin', \time() + HOUR_IN_SECONDS );

		// Set a default error.
		global $exactdn_activate_error;
		$exactdn_activate_error = 'zone not verified';
		// Primary check sends the test URL to the API for full verification.
		$api_url = 'http://optimize.exactlywww.com/exactdn/verify.php';
		$ssl     = \wp_http_supports( array( 'ssl' ) );
		if ( $ssl ) {
			$api_url = \set_url_scheme( $api_url, 'https' );
		}
		if ( ! \defined( 'EXACTDN_LOCAL_DOMAIN' ) && (int) $this->get_exactdn_option( 'verify_method' ) > 0 ) {
			// Test with an image file that should be available on the ExactDN zone.
			$test_url     = \plugins_url( '/images/test.png', \constant( \strtoupper( $this->prefix ) . 'PLUGIN_FILE' ) );
			$local_domain = $this->parse_url( $test_url, PHP_URL_HOST );
			$test_url     = \str_replace( $local_domain, $domain, $test_url );
			$this->debug_message( "test url is $test_url" );
			\add_filter( 'http_headers_useragent', $this->prefix . 'cloud_useragent', PHP_INT_MAX );
			$test_result = \wp_remote_post(
				$api_url,
				array(
					'timeout' => 10,
					'body'    => array(
						'alias'  => $domain,
						'url'    => $test_url,
						'origin' => $this->content_url(),
					),
				)
			);
			if ( \is_wp_error( $test_result ) ) {
				$error_message = $test_result->get_error_message();
				$this->debug_message( "exactdn (1) verification request failed: $error_message" );
				$exactdn_activate_error = $error_message;
				\add_action( 'admin_notices', $this->prefix . 'notice_exactdn_activation_error' );
				return false;
			} elseif ( ! empty( $test_result['body'] ) && false === \strpos( $test_result['body'], 'error' ) ) {
				$response = \json_decode( $test_result['body'], true );
				if ( ! empty( $response['success'] ) ) {
					$this->debug_message( 'exactdn (real-world) verification succeeded' );
					$this->set_exactdn_option( 'verified', 1, false );
					$this->set_exactdn_option( 'verify_method', -1, false ); // After initial activation, use simpler API verification.
					if ( ! empty( $response['asset_domains'] ) && \is_string( $response['asset_domains'] ) ) {
						$this->set_exactdn_option( 'asset_domains', $response['asset_domains'] );
						$this->asset_domains = $response['asset_domains'];
					}
					\add_action( 'admin_notices', $this->prefix . 'notice_exactdn_activation_success' );
					return true;
				}
			} elseif ( ! empty( $test_result['body'] ) ) {
				$response      = \json_decode( $test_result['body'], true );
				$error_message = $response['error'];
				$this->debug_message( "exactdn (1) verification request failed: $error_message" );
				$exactdn_activate_error = $error_message;
				if ( false !== \strpos( $error_message, 'not found' ) ) {
					\delete_option( $this->prefix . 'exactdn_domain' );
					\delete_site_option( $this->prefix . 'exactdn_domain' );
				}
				\add_action( 'admin_notices', $this->prefix . 'notice_exactdn_activation_error' );
				return false;
			}
			if ( ! empty( $test_result['response']['code'] ) && 200 !== (int) $test_result['response']['code'] ) {
				$this->debug_message( 'received response code: ' . $test_result['response']['code'] );
			}
			\add_action( 'admin_notices', $this->prefix . 'notice_exactdn_activation_error' );
			return false;
		}

		// Secondary test against the API db.
		\add_filter( 'http_headers_useragent', $this->prefix . 'cloud_useragent', PHP_INT_MAX );
		$result = \wp_remote_post(
			$api_url,
			array(
				'timeout' => 10,
				'body'    => array(
					'alias'  => $domain,
					'origin' => $this->content_url(),
				),
			)
		);
		if ( \is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			$this->debug_message( "exactdn verification request failed: $error_message" );
			$exactdn_activate_error = $error_message;
			\add_action( 'admin_notices', $this->prefix . 'notice_exactdn_activation_error' );
			return false;
		} elseif ( ! empty( $result['body'] ) && false === \strpos( $result['body'], 'error' ) ) {
			$response = \json_decode( $result['body'], true );
			if ( ! empty( $response['success'] ) ) {
				if ( ! empty( $response['plan_id'] ) ) {
					$this->set_exactdn_option( 'plan_id', (int) $response['plan_id'] );
					$this->plan_id = (int) $response['plan_id'];
				}
				if ( ! empty( $response['asset_domains'] ) && \is_string( $response['asset_domains'] ) ) {
					$this->set_exactdn_option( 'asset_domains', $response['asset_domains'] );
					$this->asset_domains = $response['asset_domains'];
				}
				$this->debug_message( 'exactdn verification via API succeeded' );
				$this->set_exactdn_option( 'verified', 1, false );
				if ( empty( $last_checkin ) ) {
					\add_action( 'admin_notices', $this->prefix . 'notice_exactdn_activation_success' );
				}
				return true;
			}
		} elseif ( ! empty( $result['body'] ) ) {
			$response      = \json_decode( $result['body'], true );
			$error_message = $response['error'];
			$this->debug_message( "exactdn verification request failed: $error_message" );
			$exactdn_activate_error = $error_message;
			if ( false !== \strpos( $error_message, 'not found' ) ) {
				\delete_option( $this->prefix . 'exactdn_domain' );
				\delete_site_option( $this->prefix . 'exactdn_domain' );
			}
			\add_action( 'admin_notices', $this->prefix . 'notice_exactdn_activation_error' );
			return false;
		}
		if ( ! empty( $result['response']['code'] ) && 200 !== (int) $result['response']['code'] ) {
			$this->debug_message( 'received response code: ' . $result['response']['code'] );
		}
		\add_action( 'admin_notices', $this->prefix . 'notice_exactdn_activation_error' );
		return false;
	}

	/**
	 * Run a simulation to decide which verification method to use.
	 */
	public function check_verify_method() {
		if ( ! $this->get_exactdn_option( 'verify_method' ) ) {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			// Prelim test with a known valid image to ensure http(s) connectivity.
			$sim_url = 'https://optimize.exactdn.com/exactdn/testorig.jpg';
			\add_filter( 'http_headers_useragent', $this->prefix . 'cloud_useragent', PHP_INT_MAX );
			$sim_result = \wp_remote_get( $sim_url );
			if ( \is_wp_error( $sim_result ) ) {
				$error_message = $sim_result->get_error_message();
				$this->debug_message( "exactdn (simulated) verification request failed: $error_message" );
			} elseif ( ! empty( $sim_result['body'] ) && \strlen( $sim_result['body'] ) > 300 ) {
				if ( 'ffd8ff' === \bin2hex( \substr( $sim_result['body'], 0, 3 ) ) ) {
					$this->debug_message( 'exactdn (simulated) verification succeeded' );
					$this->set_exactdn_option( 'verify_method', 1, false );
					return;
				}
			} else {
				$this->debug_message( 'exactdn (simulated) verification request failed, error unknown' );
			}
			$this->set_exactdn_option( 'verify_method', -1, false );
		}
	}

	/**
	 * Allow external classes/functions to check the Easy IO Plan ID (to customize UI).
	 *
	 * @return int The currently validated plan ID (1-3).
	 */
	public function get_plan_id() {
		return (int) $this->plan_id;
	}

	/**
	 * Validate the ExactDN domain.
	 *
	 * @param string $domain The unverified ExactDN domain.
	 * @return string The validated ExactDN domain.
	 */
	public function sanitize_domain( $domain ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! $domain ) {
			return;
		}
		$domain = \trim( $domain );
		if ( \strlen( $domain ) > 80 ) {
			$this->debug_message( "$domain too long" );
			return false;
		}
		if ( ! \preg_match( '#^[A-Za-z0-9\.\-]+$#', $domain ) ) {
			$this->debug_message( "$domain has bad characters" );
			return false;
		}
		return $domain;
	}

	/**
	 * Get the ExactDN domain name to use.
	 *
	 * @return string The ExactDN domain name for this site or network.
	 */
	public function get_exactdn_domain() {
		if ( \defined( 'EXACTDN_DOMAIN' ) && EXACTDN_DOMAIN ) {
			return $this->sanitize_domain( EXACTDN_DOMAIN );
		}
		if ( \is_multisite() ) {
			if ( $this->sub_folder ) {
				return $this->sanitize_domain( \get_site_option( $this->prefix . 'exactdn_domain' ) );
			}
		}
		return $this->sanitize_domain( \get_option( $this->prefix . 'exactdn_domain' ) );
	}

	/**
	 * Method to override the ExactDN domain at runtime, use with caution.
	 *
	 * @param string $domain The ExactDN domain to use instead.
	 */
	public function set_domain( $domain ) {
		if ( \is_string( $domain ) ) {
			$this->exactdn_domain = $domain;
		}
	}

	/**
	 * Get the ExactDN option.
	 *
	 * @param string $option_name The name of the ExactDN option.
	 * @return int The numerical value of the option.
	 */
	public function get_exactdn_option( $option_name ) {
		if ( \defined( 'EXACTDN_DOMAIN' ) && EXACTDN_DOMAIN ) {
			return \get_option( $this->prefix . 'exactdn_' . $option_name );
		}
		if ( \is_multisite() ) {
			if ( $this->sub_folder ) {
				return \get_site_option( $this->prefix . 'exactdn_' . $option_name );
			}
		}
		return \get_option( $this->prefix . 'exactdn_' . $option_name );
	}

	/**
	 * Set the ExactDN domain name to use.
	 *
	 * @param string $domain The ExactDN domain name for this site or network.
	 */
	public function set_exactdn_domain( $domain ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( \defined( 'EXACTDN_DOMAIN' ) && $this->sanitize_domain( EXACTDN_DOMAIN ) ) {
			return true;
		}
		$domain = $this->sanitize_domain( $domain );
		if ( ! $domain ) {
			return false;
		}
		if ( \is_multisite() ) {
			if ( $this->sub_folder ) {
				\update_site_option( $this->prefix . 'exactdn_domain', $domain );
				return $domain;
			}
		}
		\update_option( $this->prefix . 'exactdn_domain', $domain );
		return $domain;
	}

	/**
	 * Set an option for ExactDN.
	 *
	 * @param string $option_name The name of the ExactDN option.
	 * @param int    $option_value The value to set for the ExactDN option.
	 * @param bool   $autoload Optional. Whether to load the option when WordPress starts up.
	 */
	public function set_exactdn_option( $option_name, $option_value, $autoload = null ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( \defined( 'EXACTDN_DOMAIN' ) && EXACTDN_DOMAIN ) {
			return \update_option( $this->prefix . 'exactdn_' . $option_name, $option_value, $autoload );
		}
		if ( \is_multisite() ) {
			if ( $this->sub_folder ) {
				return \update_site_option( $this->prefix . 'exactdn_' . $option_name, $option_value );
			}
		}
		return \update_option( $this->prefix . 'exactdn_' . $option_name, $option_value, $autoload );
	}

	/**
	 * Check to see if a CNAME is configured in WP Offload Media.
	 *
	 * @return bool True if a CNAME is active, false otherwise.
	 */
	public function is_as3cf_cname_active() {
		// Find the WP Offload Media domain/path.
		global $as3cf;
		if ( \class_exists( '\Amazon_S3_And_CloudFront' ) && \is_object( $as3cf ) ) {
			if ( 'storage' !== $as3cf->get_setting( 'delivery-provider' ) ) {
				$this->debug_message( 'active delivery provider: ' . $as3cf->get_setting( 'delivery-provider' ) );
				if ( $as3cf->get_setting( 'enable-delivery-domain' ) && $as3cf->get_setting( 'delivery-domain' ) ) {
					$delivery_domain = $as3cf->get_setting( 'delivery-domain' );
					$this->debug_message( "found WOM CNAME domain: $delivery_domain" );
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Get the paths for wp-content, wp-includes, and the uploads directory.
	 * These are used to help determine which URLs are allowed to be rewritten for Easy IO.
	 */
	public function get_allowed_paths() {
		$wp_content_path = \trim( $this->parse_url( \content_url(), PHP_URL_PATH ), '/' );
		$wp_include_path = \trim( $this->parse_url( \includes_url(), PHP_URL_PATH ), '/' );
		$this->debug_message( "wp-content path: $wp_content_path" );
		$this->debug_message( "wp-includes path: $wp_include_path" );

		$this->content_path = \wp_basename( $wp_content_path );
		$this->include_path = \wp_basename( $wp_include_path );
		$this->uploads_path = \wp_basename( $wp_content_path );

		// NOTE: $this->uploads_path is not currently in use, so we'll see if anyone needs it.
		$uploads_info = \wp_get_upload_dir();
		if ( ! empty( $uploads_info['baseurl'] ) && ! empty( $wp_content_path ) && false === \strpos( $uploads_info['baseurl'], $wp_content_path ) ) {
			$uploads_path = \trim( $this->parse_url( $uploads_info['baseurl'], PHP_URL_PATH ), '/' );
			$this->debug_message( "wp uploads path: $uploads_path" );
			$this->uploads_path = \wp_basename( $uploads_path );
		}
	}

	/**
	 * Validate the user-defined exclusions for "all the things" rewriting.
	 */
	public function validate_user_exclusions() {
		$user_exclusions = $this->get_option( 'exactdn_exclude' );
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
					if ( $this->content_path && false !== \strpos( $exclusion, $this->content_path ) ) {
						$exclusion = \preg_replace( '#([^"\'?>]+?)?' . $this->content_path . '/#i', '', $exclusion );
					}
					$this->user_exclusions[] = \ltrim( $exclusion, '/' );
				}
			}
		}
		$this->user_exclusions[] = 'plugins/anti-captcha/';
	}

	/**
	 * Parse Elementor content data to check for full_width layouts.
	 *
	 * @param array $data The builder content data.
	 * @return array $data
	 */
	public function elementor_builder_content_data( $data ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( $this->is_iterable( $data ) ) {
			foreach ( $data as $section_data ) {
				if ( ! empty( $section_data['settings']['layout'] ) && 'full_width' === $section_data['settings']['layout'] ) {
					$this->debug_message( 'we have a winner (full_width container)!' );
					$this->full_width = true;
					break;
				}
			}
		}
		return $data;
	}

	/**
	 * Get $content_width, with a filter.
	 *
	 * @return bool|string The content width, if set. Default false.
	 */
	public function get_content_width() {
		$content_width = isset( $GLOBALS['content_width'] ) && \is_numeric( $GLOBALS['content_width'] ) && $GLOBALS['content_width'] > 100 ? $GLOBALS['content_width'] : 1920;
		if ( \function_exists( '\twentynineteen_setup' ) && 640 === (int) $content_width ) {
			$content_width = 932;
		}
		if ( \defined( 'EXACTDN_CONTENT_WIDTH' ) && EXACTDN_CONTENT_WIDTH ) {
			$content_width = EXACTDN_CONTENT_WIDTH;
		} elseif ( $this->full_width ) {
			$content_width = 1920;
		}
		/**
		 * Filter the Content Width value.
		 *
		 * @param string $content_width Content Width value.
		 */
		return (int) \apply_filters( 'exactdn_content_width', $content_width );
	}

	/**
	 * Get width within an ExactDN url.
	 *
	 * @param string $url The ExactDN url to parse.
	 * @return string The width, if found.
	 */
	public function get_exactdn_width_from_url( $url ) {
		$url_args = $this->parse_url( $url, PHP_URL_QUERY );
		if ( ! $url_args ) {
			return '';
		}
		$args = \explode( '&', $url_args );
		foreach ( $args as $arg ) {
			if ( \preg_match( '#w=(\d+)#', $arg, $width_match ) ) {
				return $width_match[1];
			}
			if ( \preg_match( '#resize=(\d+)#', $arg, $width_match ) ) {
				return $width_match[1];
			}
			if ( \preg_match( '#fit=(\d+)#', $arg, $width_match ) ) {
				return $width_match[1];
			}
		}
		return '';
	}

	/**
	 * Identify images in page content, and if images are local (uploaded to the current site), pass through ExactDN.
	 *
	 * @param string $content The page/post content.
	 * @return string The content with ExactDN image urls.
	 */
	public function filter_page_output( $content ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->filtering_the_page = true;

		$content = $this->filter_the_content( $content );

		/**
		 * Allow parsing the full page content after ExactDN is finished with it.
		 *
		 * @param string $content The fully-parsed HTML code of the page.
		 */
		$content = \apply_filters( 'exactdn_the_page', $content );

		$this->filtering_the_page = false;
		$this->debug_message( "parsing page took $this->elapsed_time seconds" );
		return $content;
	}

	/**
	 * Identify images in the content, and if images are local (uploaded to the current site), pass through ExactDN.
	 *
	 * @param string $content The page/post content.
	 * @return string The content with ExactDN image urls.
	 */
	public function filter_the_content( $content ) {
		if ( $this->is_json( $content ) ) {
			return $content;
		}
		if ( \apply_filters( 'exactdn_skip_page', false, $this->request_uri ) ) {
			return $content;
		}

		$started = \microtime( true );
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$images = $this->get_images_from_html( $content, true );

		if ( $this->filtering_the_page ) {
			$this->get_preload_images( $content );
		}

		if ( ! empty( $images ) ) {
			$this->debug_message( 'we have images to parse' );
			if ( false !== \strpos( $content, 'elementor-section-full_width' ) ) {
				$this->debug_message( 'elementor full_width section found!' );
				$this->full_width = true;
			}
			$content_width = false;
			if ( ! $this->filtering_the_page ) {
				$this->filtering_the_content = true;
				$this->debug_message( 'filtering the content' );
				$content_width = $this->get_content_width();
				$this->debug_message( "configured/filtered content_width: $content_width" );
			}
			$resize_existing = \defined( 'EXACTDN_RESIZE_EXISTING' ) && EXACTDN_RESIZE_EXISTING;

			$image_sizes = $this->image_sizes();

			foreach ( $images[0] as $index => $tag ) {
				// Default to resize, though fit may be used in certain cases where a dimension cannot be ascertained.
				$transform = 'resize';

				// Start with a clean slate each time.
				$attachment_id = false;
				$exactdn_url   = false;
				$width         = false;
				$lazy          = false;
				$srcset_fill   = false;

				// Flag if we need to munge a fullsize URL.
				$fullsize_url = false;

				// Identify image source.
				$src      = \trim( $images['img_url'][ $index ] );
				$src_orig = $images['img_url'][ $index ]; // Don't trim, because we'll use it for search/replacement later.
				if ( \is_string( $src ) ) {
					$this->debug_message( "starting img_url: $src" );
				} else {
					$this->debug_message( '$src is not a string?' );
				}

				/**
				 * Allow specific images to be skipped by ExactDN.
				 *
				 * @param bool false Should ExactDN ignore this image. Default false.
				 * @param string $src Image URL.
				 * @param string $tag Image HTML Tag.
				 */
				if ( \apply_filters( 'exactdn_skip_image', false, $src, $tag ) ) {
					continue;
				}

				$this->debug_message( 'made it passed the filters' );

				// Log 0-size Pinterest schema images.
				if ( \strpos( $tag, 'data-pin-description=' ) && \strpos( $tag, 'width="0" height="0"' ) ) {
					$this->debug_message( 'data-pin/Pinterest image' );
				}

				// Pre-empt srcset fill if the surrounding link has a background image or if there is a data-desktop attribute indicating a potential slider.
				if ( \strpos( $tag, 'background-image:' ) || \strpos( $tag, 'data-desktop=' ) ) {
					$srcset_fill = false;
				}
				/**
				 * Documented in generate_url, in this case used to detect images that should bypass srcset fill.
				 *
				 * @param array|string $args Array of ExactDN arguments.
				 * @param string $image_url Image URL.
				 * @param string|null $scheme Image scheme. Default to null.
				 */
				$args = \apply_filters( 'exactdn_pre_args', array( 'test' => 'lazy-test' ), $src, null );
				if ( empty( $args ) ) {
					$srcset_fill = false;
				}
				// Support Lazy Load plugins.
				// Don't modify $tag yet as we need unmodified version later.
				$lazy_load_src = \trim( $this->get_attribute( $images['img_tag'][ $index ], 'data-lazy-src' ) );
				if ( $lazy_load_src ) {
					$placeholder_src      = $src;
					$placeholder_src_orig = $src;
					$src                  = $lazy_load_src;
					$src_orig             = $lazy_load_src;
					$this->srcset_attr    = 'data-lazy-srcset';
					$lazy                 = true;
					$srcset_fill          = true;
				}
				// Must be a legacy Jetpack thing as far as I can tell, no matches found in any currently installed plugins.
				$lazy_load_src = \trim( $this->get_attribute( $images['img_tag'][ $index ], 'data-lazy-original' ) );
				if ( ! $lazy && $lazy_load_src ) {
					$placeholder_src      = $src;
					$placeholder_src_orig = $src;
					$src                  = $lazy_load_src;
					$src_orig             = $lazy_load_src;
					$lazy                 = true;
				}
				if ( ! $lazy && \strpos( $images['img_tag'][ $index ], 'a3-lazy-load/assets/images/lazy_placeholder' ) ) {
					$lazy_load_src = \trim( $this->get_attribute( $images['img_tag'][ $index ], 'data-src' ) );
				}
				if (
					! $lazy &&
					\strpos( $images['img_tag'][ $index ], ' data-src=' ) &&
					\strpos( $images['img_tag'][ $index ], 'lazyload' ) &&
					(
						\strpos( $images['img_tag'][ $index ], 'data:image/gif' ) ||
						\strpos( $images['img_tag'][ $index ], 'data:image/svg' )
					)
				) {
					$lazy_load_src = $this->get_attribute( $images['img_tag'][ $index ], 'data-src' );
					$this->debug_message( "found eio ll src: $lazy_load_src" );
				}
				if ( ! $lazy && $lazy_load_src ) {
					$placeholder_src      = $src;
					$placeholder_src_orig = $src;
					$src                  = $lazy_load_src;
					$src_orig             = $lazy_load_src;
					$this->srcset_attr    = 'data-srcset';
					$lazy                 = true;
					$srcset_fill          = true;
				}
				if ( ! $lazy && \strpos( $images['img_tag'][ $index ], 'revslider/admin/assets/images/dummy' ) ) {
					$lazy_load_src = \trim( $this->get_attribute( $images['img_tag'][ $index ], 'data-lazyload' ) );
				}
				if ( ! $lazy && \strpos( $images['img_tag'][ $index ], '/assets/dummy.png' ) ) {
					$lazy_load_src = \trim( $this->get_attribute( $images['img_tag'][ $index ], 'data-lazyload' ) );
				}
				if ( ! $lazy && $lazy_load_src ) {
					$placeholder_src      = $src;
					$placeholder_src_orig = $src;
					$src                  = $lazy_load_src;
					$src_orig             = $lazy_load_src;
					$lazy                 = true;
				}
				if ( $lazy ) {
					$this->debug_message( 'handling lazy image' );
				}

				$is_relative = false;
				// Check for relative URLs that start with a slash.
				if (
					'/' === \substr( $src, 0, 1 ) &&
					'/' !== \substr( $src, 1, 1 ) &&
					false === \strpos( $this->upload_domain, 'amazonaws.com' ) &&
					false === \strpos( $this->upload_domain, 'digitaloceanspaces.com' ) &&
					false === \strpos( $this->upload_domain, 'storage.googleapis.com' )
				) {
					$src         = '//' . $this->upload_domain . $src;
					$is_relative = true;
				}

				// Check if image URL should be used with ExactDN.
				if ( $this->validate_image_url( $src ) ) {
					$this->debug_message( 'url validated' );

					$srcset_attr = $this->get_attribute( $images['img_tag'][ $index ], $this->srcset_attr );
					// Find the width and height attributes.
					$width  = $this->get_attribute( $images['img_tag'][ $index ], 'width' );
					$height = $this->get_attribute( $images['img_tag'][ $index ], 'height' );

					// Can't pass both a relative width and height, so unset the dimensions in favor of not breaking the horizontal layout.
					if ( false !== \strpos( $width, '%' ) ) {
						$width = false;
					}
					if ( false !== \strpos( $height, '%' ) ) {
						$height = false;
					}

					// Falsify them if empty.
					$width  = $width && \is_numeric( $width ) ? $width : false;
					$height = $height && \is_numeric( $height ) ? $height : false;

					// Get width/height attributes from the URL/file if they are missing.
					$insert_dimensions = false;
					if ( \apply_filters( 'eio_add_missing_width_height_attrs', $this->get_option( $this->prefix . 'add_missing_dims' ) ) && ( empty( $width ) || empty( $height ) ) ) {
						$this->debug_message( 'missing width attr or height attr' );
						$insert_dimensions = true;
					}

					// See if there is a width/height set in the style attribute.
					$style_width  = $this->get_img_style_width( $images['img_tag'][ $index ] );
					$style_height = $this->get_img_style_height( $images['img_tag'][ $index ] );
					if ( $style_width && $style_height ) {
						$width  = \min( $style_width, $width );
						$height = \min( $style_height, $height );
					} elseif ( $style_width && $style_width < $width ) {
						$width     = $style_width;
						$transform = 'fit';
					} elseif ( $style_height && $style_height < $height ) {
						$height    = $style_height;
						$transform = 'fit';
					}

					// Detect WP registered image size from HTML class.
					if ( \preg_match( '#class=["|\']?[^"\']*size-([^"\'\s]+)[^"\']*["|\']?#i', $images['img_tag'][ $index ], $size ) ) {
						$size = \array_pop( $size );

						$this->debug_message( "detected $size" );
						if ( false === $width && false === $height && 'full' !== $size && \array_key_exists( $size, $image_sizes ) ) {
							$width     = (int) $image_sizes[ $size ]['width'];
							$height    = (int) $image_sizes[ $size ]['height'];
							$transform = $image_sizes[ $size ]['crop'] ? 'resize' : 'fit';
						}
					} else {
						unset( $size );
					}

					list( $filename_width, $filename_height ) = $this->get_dimensions_from_filename( $src );
					if ( false === $width && false === $height ) {
						$width  = $filename_width;
						$height = $filename_height;
					}
					// WP Attachment ID, if uploaded to this site.
					$attachment_id = $this->get_attribute( $images['img_tag'][ $index ], 'data-id' );
					if ( empty( $attachment_id ) ) {
						$this->debug_message( 'data-id not found, looking for wp-image-x in class' );
						\preg_match( '#class=["|\']?[^"\']*wp-image-([\d]+)[^"\']*["|\']?#i', $images['img_tag'][ $index ], $attachment_id );
					}
					if ( ! $srcset_attr && ! $this->get_option( 'exactdn_prevent_db_queries' ) && empty( $attachment_id ) ) {
						$this->debug_message( 'looking for attachment id' );
						$attachment_id = attachment_url_to_postid( $src );
					}
					if ( ! $srcset_attr && ! $this->get_option( 'exactdn_prevent_db_queries' ) && ! empty( $attachment_id ) ) {
						if ( \is_array( $attachment_id ) ) {
							$attachment_id = \intval( \array_pop( $attachment_id ) );
						}
						$this->debug_message( "using attachment id ($attachment_id) to get source image" );

						if ( $attachment_id ) {
							$this->debug_message( "detected attachment $attachment_id" );
							$attachment = get_post( $attachment_id );

							// Basic check on returned post object.
							if ( \is_object( $attachment ) && ! \is_wp_error( $attachment ) && 'attachment' === $attachment->post_type ) {
								$src_per_wp = \wp_get_attachment_image_src( $attachment_id, 'full' );

								if ( $src_per_wp && \is_array( $src_per_wp ) ) {
									$this->debug_message( "src retrieved from db: {$src_per_wp[0]}, checking for match" );
									$fullsize_url_path = $this->parse_url( $src_per_wp[0], PHP_URL_PATH );
									if ( \is_null( $fullsize_url_path ) ) {
										$src_per_wp = false;
									} elseif ( $fullsize_url_path ) {
										$fullsize_url_basename = \pathinfo( $fullsize_url_path, PATHINFO_FILENAME );
										$this->debug_message( "looking for $fullsize_url_basename in $src" );
										if ( \strpos( \wp_basename( $src ), $fullsize_url_basename ) === false ) {
											$this->debug_message( 'fullsize url does not match' );
											$src_per_wp = false;
										}
									} else {
										$src_per_wp = false;
									}
								}

								if ( $src_per_wp && $this->validate_image_url( $src_per_wp[0] ) ) {
									$this->debug_message( "detected $width filenamew $filename_width" );
									if ( $resize_existing || ( $width && (int) $filename_width !== (int) $width ) ) {
										$this->debug_message( 'resizing existing or width does not match' );
										$src = $src_per_wp[0];
									}
									$fullsize_url = true;

									// Prevent image distortion if a detected dimension exceeds the image's natural dimensions.
									if ( ( false !== $width && $width > $src_per_wp[1] ) || ( false !== $height && $height > $src_per_wp[2] ) ) {
										$width  = false === $width ? false : min( $width, $src_per_wp[1] );
										$height = false === $height ? false : min( $height, $src_per_wp[2] );
										$this->debug_message( "constrained to attachment dims, w=$width and h=$height" );
									}

									// If no width and height are found, max out at source image's natural dimensions.
									// Otherwise, respect registered image sizes' cropping setting.
									if ( false === $width && false === $height ) {
										$width     = $src_per_wp[1];
										$height    = $src_per_wp[2];
										$transform = 'fit';
										$this->debug_message( "no dims, using attachment dims, w=$width and h=$height" );
									} elseif ( isset( $size ) && \array_key_exists( $size, $image_sizes ) && isset( $image_sizes[ $size ]['crop'] ) ) {
										$transform = (bool) $image_sizes[ $size ]['crop'] ? 'resize' : 'fit';
										$this->debug_message( 'attachment size set to crop' );
									}
								}
							} else {
								unset( $attachment_id );
								unset( $attachment );
							}
						}
					}
					$constrain_width = (int) $content_width;
					if ( ! empty( $images['figure_class'][ $index ] ) && false !== \strpos( $images['figure_class'][ $index ], 'alignfull' ) && \current_theme_supports( 'align-wide' ) ) {
						$constrain_width = (int) \apply_filters( 'exactdn_full_align_image_width', \max( 1920, $content_width ) );
					} elseif ( ! empty( $images['figure_class'][ $index ] ) && false !== \strpos( $images['figure_class'][ $index ], 'alignwide' ) && \current_theme_supports( 'align-wide' ) ) {
						$constrain_width = (int) \apply_filters( 'exactdn_wide_align_image_width', \max( 1500, $content_width ) );
					}
					if ( ! empty( $images['div_class'][ $index ] ) && false !== \strpos( $images['div_class'][ $index ], 'alignfull' ) && \current_theme_supports( 'align-wide' ) ) {
						$constrain_width = (int) \apply_filters( 'exactdn_full_align_image_width', \max( 1920, $content_width ) );
					} elseif ( ! empty( $images['div_class'][ $index ] ) && false !== \strpos( $images['div_class'][ $index ], 'alignwide' ) && \current_theme_supports( 'align-wide' ) ) {
						$constrain_width = (int) \apply_filters( 'exactdn_wide_align_image_width', \max( 1500, $content_width ) );
					}
					// If width is available, constrain to $content_width.
					if ( false !== $width && false === \strpos( $width, '%' ) && \is_numeric( $constrain_width ) ) {
						if ( $width > $constrain_width && false !== $height && false === \strpos( $height, '%' ) ) {
							$this->debug_message( 'constraining to content width' );
							$height = \round( ( $constrain_width * $height ) / $width );
							$width  = $constrain_width;
						} elseif ( $width > $constrain_width ) {
							$this->debug_message( 'constraining to content width' );
							$width = $constrain_width;
						}
					}

					// Set a width if none is found and $content_width is available.
					// If width is set in this manner and height is available, use `fit` instead of `resize` to prevent skewing.
					if ( false === $width && \is_numeric( $constrain_width ) ) {
						$width = (int) $constrain_width;

						if ( false !== $height ) {
							$transform = 'fit';
						}
					}

					// Override fit by class/id/attr 'img-crop'.
					if ( 'fit' === $transform && \strpos( $images['img_tag'][ $index ], 'img-crop' ) ) {
						$transform = 'resize';
					}

					// Detect if image source is for a custom-cropped thumbnail and prevent further URL manipulation.
					if ( ! $fullsize_url && \preg_match_all( '#-e[a-z0-9]+(-\d+x\d+)?\.(' . \implode( '|', $this->extensions ) . '){1}$#i', \wp_basename( $src ), $filename ) ) {
						$fullsize_url = true;
					}

					// Build array of ExactDN args and expose to filter before passing to ExactDN URL function.
					$args = array();

					if ( false !== $width && false !== $height && false === \strpos( $width, '%' ) && false === \strpos( $height, '%' ) ) {
						$args[ $transform ] = $width . ',' . $height;
					} elseif ( false !== $width ) {
						$args['w'] = $width;
					} elseif ( false !== $height ) {
						$args['h'] = $height;
					}

					if ( ! empty( $srcset_attr ) ) {
						$this->debug_message( 'src resize not needed, srcset present' );
						$args = array();
					} elseif ( ! $resize_existing && ( ! $width || (int) $filename_width === (int) $width ) ) {
						$this->debug_message( 'preventing resize' );
						$args = array();
					} elseif ( ! $fullsize_url ) {
						// Build URL, first maybe removing WP's resized string so we pass the original image to ExactDN (for higher quality).
						$src = $this->strip_image_dimensions_maybe( $src );
					}

					if ( ! $this->get_option( 'exactdn_prevent_db_queries' ) && ! empty( $attachment_id ) ) {
						$this->debug_message( 'using attachment id to check smart crop' );
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
					$args = \apply_filters( 'exactdn_post_image_args', $args, \compact( 'tag', 'src', 'src_orig', 'width', 'height' ) );
					$this->debug_message( "width $width" );
					$this->debug_message( "height $height" );
					$this->debug_message( "transform $transform" );

					$exactdn_url = $this->generate_url( $src, $args );
					$this->debug_message( "new url $exactdn_url" );

					// Modify image tag if ExactDN function provides a URL
					// Ensure changes are only applied to the current image by copying and modifying the matched tag, then replacing the entire tag with our modified version.
					if ( $src !== $exactdn_url ) {
						$new_tag = $tag;

						// If present, replace the link href with an ExactDN URL for the full-size image.
						if ( \defined( 'EIO_PRESERVE_LINKED_IMAGES' ) && EIO_PRESERVE_LINKED_IMAGES && ! empty( $images['link_url'][ $index ] ) && $this->validate_image_url( $images['link_url'][ $index ] ) ) {
							$new_tag = \preg_replace(
								'#(href=["|\'])' . $images['link_url'][ $index ] . '(["|\'])#i',
								'\1' . $this->generate_url(
									$images['link_url'][ $index ],
									array(
										'lossy' => 0,
										'strip' => 'none',
									)
								) . '\2',
								$new_tag,
								1
							);
						} elseif ( ! empty( $images['link_url'][ $index ] ) && $this->validate_image_url( $images['link_url'][ $index ] ) ) {
							$new_tag = \preg_replace(
								'#(href=["|\'])' . $images['link_url'][ $index ] . '(["|\'])#i',
								'\1' . $this->generate_url( $images['link_url'][ $index ], array( 'w' => 2560 ) ) . '\2',
								$new_tag,
								1
							);
						}

						// Check if content width pushed the respimg sizes attribute too far down.
						if ( ! empty( $constrain_width ) && (int) $constrain_width !== (int) $content_width ) {
							$sizes_attr     = $this->get_attribute( $new_tag, 'sizes' );
							$new_sizes_attr = \str_replace( ' ' . $content_width . 'px', ' ' . $constrain_width . 'px', $sizes_attr );
							if ( $sizes_attr !== $new_sizes_attr ) {
								$new_tag = \str_replace( $sizes_attr, $new_sizes_attr, $new_tag );
							}
						}

						// Cleanup ExactDN URL.
						$exactdn_url = \str_replace( '&#038;', '&', \esc_url( \trim( html_entity_decode( $exactdn_url ) ) ) );
						// Supplant the original source value with our ExactDN URL.
						$this->debug_message( "replacing $src_orig with $exactdn_url" );
						if ( $is_relative ) {
							$this->set_attribute( $new_tag, 'src', $exactdn_url, true );
						} else {
							$new_tag = \str_replace( $src_orig, $exactdn_url, $new_tag );
						}

						$preload_image = $this->is_image_preloaded( $exactdn_url, $src_orig );
						if ( $preload_image ) {
							if ( $exactdn_url !== $preload_image['url'] ) {
								$this->debug_message( "replacing {$preload_image['url']} with $exactdn_url" );
								$new_preload_tag = $preload_image['tag'];
								$this->set_attribute( $new_preload_tag, 'href', \esc_url( $exactdn_url ), true );
								if ( $preload_image['tag'] !== $new_preload_tag ) {
									$content = \str_replace( $preload_image['tag'], $new_preload_tag, $content );
								}
							}
							$new_tag = $this->skip_lazyload_for_preload( $new_tag );
						}

						// If Lazy Load is in use, pass placeholder image through ExactDN.
						if ( isset( $placeholder_src ) && $this->validate_image_url( $placeholder_src ) ) {
							$placeholder_src = $this->generate_url( $placeholder_src );

							if ( $placeholder_src !== $placeholder_src_orig ) {
								$new_tag = \str_replace( $placeholder_src_orig, \str_replace( '&#038;', '&', \esc_url( \trim( html_entity_decode( $placeholder_src ) ) ) ), $new_tag );
							}

							unset( $placeholder_src );
						}

						if ( $insert_dimensions && $filename_width > 0 && $filename_height > 0 ) {
							$this->debug_message( "filling in width = $filename_width and height = $filename_height" );
							$this->set_attribute( $new_tag, 'width', $filename_width, true );
							$this->set_attribute( $new_tag, 'height', $filename_height, true );
						}

						// Replace original tag with modified version.
						$content = \str_replace( $tag, $new_tag, $content );
					}
				} elseif ( ! $lazy && ! $this->get_attribute( $images['img_tag'][ $index ], $this->srcset_attr ) && $this->validate_image_url( $src, true ) ) {
					$this->debug_message( "found a potential exactdn src url to wrangle: $src" );

					$args    = array();
					$new_tag = $tag;
					$width   = $this->get_attribute( $images['img_tag'][ $index ], 'width' );
					$height  = $this->get_attribute( $images['img_tag'][ $index ], 'height' );
					// Making sure the width/height are numeric.
					if ( false === \strpos( $new_tag, 'srcset' ) && \strpos( $src, '?' ) && (int) $width > 2 && (int) $height > 2 ) {
						$url_params = \urldecode( $this->parse_url( $src, PHP_URL_QUERY ) );
						if ( $url_params && false !== \strpos( $url_params, 'resize=' ) ) {
							$this->debug_message( 'existing resize param' );
						} elseif ( $url_params && false !== \strpos( $url_params, 'fit=' ) ) {
							$this->debug_message( 'existing fit param' );
						} elseif ( $url_params && false === \strpos( $url_params, 'w=' ) && false === \strpos( $url_params, 'h=' ) && false === \strpos( $url_params, 'crop=' ) ) {
							$this->debug_message( 'no size params, so add the width/height' );
							$args      = array();
							$transform = 'fit';
							// Or optionally as crop/resize.
							if ( \strpos( $new_tag, 'img-crop' ) ) {
								$transform = 'resize';
							}
							$args[ $transform ] = $width . ',' . $height;
						}
					}
					if ( $args ) {
						$args    = \apply_filters( 'exactdn_post_image_args', $args, \compact( 'new_tag', 'src', 'src', 'width', 'height' ) );
						$new_src = $this->generate_url( $src, $args );
						if ( $new_src && $src !== $new_src ) {
							$new_tag = \str_replace( $src, $new_src, $new_tag );
						}
					}

					if ( $new_tag && $new_tag !== $tag ) {
						// Replace original tag with modified version.
						$content = \str_replace( $tag, $new_tag, $content );
					}
				} elseif ( $lazy && ! empty( $placeholder_src ) && $this->validate_image_url( $placeholder_src ) ) {
					$this->debug_message( "parsing $placeholder_src for $src" );
					$new_tag = $tag;
					// If Lazy Load is in use, pass placeholder image through ExactDN.
					$placeholder_src = $this->generate_url( $placeholder_src );
					if ( $placeholder_src !== $placeholder_src_orig ) {
						$new_tag = \str_replace( $placeholder_src_orig, \str_replace( '&#038;', '&', \esc_url( \trim( html_entity_decode( $placeholder_src ) ) ) ), $new_tag );
						// Replace original tag with modified version.
						$content = \str_replace( $tag, $new_tag, $content );
					}
					unset( $placeholder_src );
				} else {
					$this->debug_message( "unparsed $src, srcset fill coming up" );
				} // End if().

				// At this point, we discard the original src in favor of the ExactDN url.
				if ( ! empty( $exactdn_url ) ) {
					$src = $exactdn_url;
				}
				// This is disabled by default, not much reason to do this with the lazy loader auto-scaling.
				if ( ! \is_feed() && $srcset_fill && \defined( 'EIO_SRCSET_FILL' ) && EIO_SRCSET_FILL && false !== \strpos( $src, $this->exactdn_domain ) ) {
					if ( ! $this->get_attribute( $images['img_tag'][ $index ], $this->srcset_attr ) && ! $this->get_attribute( $images['img_tag'][ $index ], 'sizes' ) ) {
						$this->debug_message( "srcset filling with $src" );
						$zoom = false;
						// If $width is empty, we'll search the url for a width param, then we try searching the img element, with fall back to the filename.
						if ( empty( $width ) || ! \is_numeric( $width ) ) {
							// This only searches for w, resize, or fit flags, others are ignored.
							$width = $this->get_exactdn_width_from_url( $src );
							if ( $width ) {
								$zoom = true;
							}
						}
						$width_attr = $this->get_attribute( $images['img_tag'][ $index ], 'width' );
						// Get width/height attributes from the URL/file if they are missing.
						$insert_dimensions = false;
						if ( \apply_filters( 'eio_add_missing_width_height_attrs', $this->get_option( $this->prefix . 'add_missing_dims' ) ) && empty( $width_attr ) ) {
							$this->debug_message( 'missing width attr or height attr' );
							$insert_dimensions = true;
						}
						if ( empty( $width ) || ! \is_numeric( $width ) ) {
							$width = $width_attr;
						}
						list( $filename_width, $filename_height ) = $this->get_dimensions_from_filename( $src );
						if ( empty( $width ) || ! \is_numeric( $width ) ) {
							$width = $filename_width;
						}
						if ( empty( $width ) || ! \is_numeric( $width ) ) {
							$width = $this->get_attribute( $images['img_tag'][ $index ], 'data-actual-width' );
						}
						if ( false !== \strpos( $src, 'crop=' ) || false !== \strpos( $src, '&h=' ) || false !== \strpos( $src, '?h=' ) ) {
							$width = false;
						}
						$new_tag = $images['img_tag'][ $index ];
						// Then add a srcset and sizes.
						if ( $width ) {
							$srcset = $this->generate_image_srcset( $src, $width, $zoom, $filename_width );
							if ( $srcset ) {
								$this->set_attribute( $new_tag, $this->srcset_attr, $srcset );
								$this->set_attribute( $new_tag, 'sizes', \sprintf( '(max-width: %1$dpx) 100vw, %1$dpx', $width ) );
							}
						}
						if ( $insert_dimensions && $filename_width > 0 && $filename_height > 0 ) {
							$this->debug_message( "filling in width = $filename_width and height = $filename_height" );
							$this->set_attribute( $new_tag, 'width', $filename_width, true );
							$this->set_attribute( $new_tag, 'height', $filename_height, true );
						}
						if ( $new_tag !== $images['img_tag'][ $index ] ) {
							// Replace original tag with modified version.
							$content = \str_replace( $images['img_tag'][ $index ], $new_tag, $content );
						}
					} // End if() -- no srcset or sizes attributes found.
				} // End if() -- not a feed and EIO_SRCSET_FILL enabled.
			} // End foreach() -- of all images found in the page.
		} // End if() -- we found images in the page at all.

		// Process <a> elements in the page for image URLs.
		$content = $this->filter_image_links( $content );

		// Process <picture> elements in the page.
		$content = $this->filter_picture_images( $content );

		// Process <video> elements with poster attributes.
		$content = $this->filter_video_elements( $content );

		// Process background images on HTML elements.
		$element_types = \apply_filters( 'eio_allowed_background_image_elements', array( 'div', 'li', 'span', 'section', 'a', 'rs-bg-elem' ) );
		foreach ( $element_types as $element_type ) {
			$content = $this->filter_bg_images( $content, $element_type );
		}
		if ( $this->filtering_the_page ) {
			$content = $this->filter_prz_thumb( $content );
			$content = $this->filter_style_blocks( $content );
			$content = $this->filter_sr6_slides( $content );
			if ( $this->get_option( 'exactdn_all_the_things' ) ) {
				$this->debug_message( 'rewriting all other wp-content/wp-includes urls' );
				$content = $this->filter_all_the_things( $content );
			}
		}
		$this->debug_message( 'done parsing page' );
		$this->filtering_the_content = false;

		foreach ( $this->preload_images as $preload_index => $preload_image ) {
			if ( ! empty( $preload_image['found'] ) ) {
				continue;
			}
			$this->debug_message( "never found matching img for image preload: {$preload_image['tag']}" );
		}

		$elapsed_time = \microtime( true ) - $started;
		$this->debug_message( "parsing the_content took $elapsed_time seconds" );
		$this->elapsed_time += \microtime( true ) - $started;
		$this->debug_message( "parsing the page took $this->elapsed_time seconds so far" );
		if ( ! $this->get_option( 'exactdn_prevent_db_queries' ) && $this->elapsed_time > .5 ) {
			$this->set_option( 'exactdn_prevent_db_queries', true );
		}
		if ( $this->filtering_the_page && $this->get_option( $this->prefix . 'debug' ) && 0 !== \strpos( $content, '{' ) && false === \strpos( '$content', '<loc>' ) ) {
			$content .= '<!-- Easy IO processing time: ' . $this->elapsed_time . ' seconds -->';
		}
		return $content;
	}

	/**
	 * Parse the HTML for a/link elements to rewrite.
	 *
	 * @param string $content The HTML content to parse.
	 * @return string The filtered HTML content.
	 */
	public function filter_image_links( $content ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$elements = $this->get_elements_from_html( $content, 'a' );
		if ( $this->is_iterable( $elements ) ) {
			$args = array( 'w' => 2560 );
			if ( \defined( 'EIO_PRESERVE_LINKED_IMAGES' ) && EIO_PRESERVE_LINKED_IMAGES ) {
				$args = array(
					'lossy' => 0,
					'strip' => 'none',
				);
			}
			foreach ( $elements as $index => $element ) {
				if ( false === \strpos( $element, 'href' ) ) {
					continue;
				}
				$this->debug_message( 'parsing a link for hrefs' );
				$link_url = $this->get_attribute( $element, 'href' );
				if ( empty( $link_url ) ) {
					continue;
				}
				/** This filter is already documented in class-exactdn.php */
				if ( \apply_filters( 'exactdn_skip_image', false, $link_url, $element ) ) {
					continue;
				}
				$full_link_url = $link_url;
				// Check for relative URLs that start with a slash.
				if (
					'/' === \substr( $link_url, 0, 1 ) &&
					'/' !== \substr( $link_url, 1, 1 ) &&
					false === \strpos( $this->upload_domain, 'amazonaws.com' ) &&
					false === \strpos( $this->upload_domain, 'digitaloceanspaces.com' ) &&
					false === \strpos( $this->upload_domain, 'storage.googleapis.com' )
				) {
					$full_link_url = '//' . $this->upload_domain . $link_url;
				}
				if ( $this->validate_image_url( $full_link_url ) ) {
					$exactdn_url = $this->generate_url( $full_link_url, $args );
					if ( $exactdn_url && $exactdn_url !== $link_url && false !== \strpos( $exactdn_url, $this->exactdn_domain ) ) {
						$this->debug_message( 'updating link URL in element' );
						$element = \str_replace( $link_url, $exactdn_url, $element );
						if ( $element && $element !== $elements[ $index ] ) {
							$this->debug_message( 'updating link element in content' );
							$content = \str_replace( $elements[ $index ], $element, $content );
						}
					}
				}
			}
		}
		return $content;
	}

	/**
	 * Parse page content for picture elements to rewrite.
	 *
	 * @param string $content The HTML content to parse.
	 * @return string The filtered HTML content.
	 */
	public function filter_picture_images( $content ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( false === \strpos( $content, '<picture' ) ) {
			$this->debug_message( 'no picture elements, done' );
			return $content;
		}
		// Images listed as picture/source elements.
		$pictures = $this->get_picture_tags_from_html( $content );
		if ( $this->is_iterable( $pictures ) ) {
			foreach ( $pictures as $index => $picture ) {
				$sources = $this->get_elements_from_html( $picture, 'source' );
				if ( $this->is_iterable( $sources ) ) {
					foreach ( $sources as $source ) {
						$this->debug_message( "parsing a picture source: $source" );
						$srcset = $this->get_attribute( $source, 'srcset' );
						if ( $srcset ) {
							$new_srcset = $this->srcset_replace( $srcset );
							if ( $new_srcset && $new_srcset !== $srcset ) {
								$new_source = \str_replace( $srcset, $new_srcset, $source );
								$picture    = \str_replace( $source, $new_source, $picture );
							}
						}
					}
					if ( $picture !== $pictures[ $index ] ) {
						$this->debug_message( 'rewrote source for picture element' );
						$content = \str_replace( $pictures[ $index ], $picture, $content );
					}
				}
			}
		}
		return $content;
	}

	/**
	 * Parse page content for video elements with poster attributes to rewrite.
	 *
	 * @param string $content The HTML content to parse.
	 * @return string The filtered HTML content.
	 */
	public function filter_video_elements( $content ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( false === \strpos( $content, '<video' ) ) {
			$this->debug_message( 'no video elements, done' );
			return $content;
		}
		// Video elements, looking for poster attributes that are images.
		$videos = $this->get_elements_from_html( $content, 'video' );
		if ( $this->is_iterable( $videos ) ) {
			foreach ( $videos as $index => $video ) {
				$this->debug_message( 'parsing a video element' );
				$poster = $this->get_attribute( $video, 'poster' );
				if ( $poster ) {
					$this->debug_message( "parsing a video poster: $poster" );
					if ( $this->validate_image_url( $poster ) ) {
						$this->debug_message( 'rewriting video poster...' );
						$this->set_attribute( $video, 'poster', $this->generate_url( $poster ), true );
						if ( $video !== $videos[ $index ] ) {
							$content = \str_replace( $videos[ $index ], $video, $content );
						}
					}
				}
			}
		}
		return $content;
	}

	/**
	 * Parse page content looking for elements with CSS background-image properties.
	 *
	 * @param string $content The HTML content to parse.
	 * @param string $tag_type The type of HTML tag to look for.
	 * @return string The filtered HTML content.
	 */
	public function filter_bg_images( $content, $tag_type ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$content_width = false;
		if ( ! $this->filtering_the_page ) {
			$content_width = $this->get_content_width();
		}
		$this->debug_message( "content width is $content_width" );
		// Process background images on elements.
		$elements = $this->get_elements_from_html( $content, $tag_type );
		if ( $this->is_iterable( $elements ) ) {
			foreach ( $elements as $index => $element ) {
				$this->debug_message( "parsing a $tag_type" );
				if ( false === \strpos( $element, 'background:' ) && false === strpos( $element, 'background-image:' ) ) {
					continue;
				}
				$style = $this->get_attribute( $element, 'style' );
				if ( empty( $style ) ) {
					continue;
				}
				$new_style = $style;
				$this->debug_message( "checking style attr for background-image: $style" );
				$bg_image_urls = $this->get_background_image_urls( $style );
				$bg_autoscale  = \apply_filters( 'easyio_background_image_autoscale', true );
				if ( \count( $bg_image_urls ) > 1 ) {
					$bg_autoscale = false;
				}
				$skip_autoscale = false;
				foreach ( $bg_image_urls as $bg_image_url ) {
					$orig_bg_url = $bg_image_url;

					// Check for relative URLs that start with a slash.
					if (
						'/' === \substr( $bg_image_url, 0, 1 ) &&
						'/' !== \substr( $bg_image_url, 1, 1 ) &&
						false === \strpos( $this->upload_domain, 'amazonaws.com' ) &&
						false === \strpos( $this->upload_domain, 'digitaloceanspaces.com' ) &&
						false === \strpos( $this->upload_domain, 'storage.googleapis.com' )
					) {
						$bg_image_url = '//' . $this->upload_domain . $bg_image_url;
					}

					if ( $this->validate_image_url( $bg_image_url ) ) {
						/** This filter is already documented in class-exactdn.php */
						if ( \apply_filters( 'exactdn_skip_image', false, $bg_image_url, $element ) ) {
							continue;
						}
						$args          = array();
						$element_class = $this->get_attribute( $element, 'class' );
						if ( false !== \strpos( $element_class, 'vce-asset-background-zoom-item' ) ) {
							// Don't constrain Visual Composer 'zoom' images AND disable auto-scaling.
							$skip_autoscale = true;
						} elseif ( false !== \strpos( $element_class, 'alignfull' ) && \current_theme_supports( 'align-wide' ) ) {
							$args['w'] = \apply_filters( 'exactdn_full_align_bgimage_width', 1920, $bg_image_url );
						} elseif ( false !== \strpos( $element_class, 'wp-block-cover' ) && false !== \strpos( $element_class, 'has-parallax' ) ) {
							$args['w'] = \apply_filters( 'exactdn_wp_cover_parallax_bgimage_width', 1920, $bg_image_url );
						} elseif ( false !== \strpos( $element_class, 'alignwide' ) && \current_theme_supports( 'align-wide' ) ) {
							$args['w'] = \apply_filters( 'exactdn_wide_align_bgimage_width', 1500, $bg_image_url );
						} elseif ( false !== \strpos( $element_class, 'et_parallax_bg' ) ) {
							$args['w'] = \apply_filters( 'exactdn_et_parallax_bgimage_width', 1920, $bg_image_url );
						} elseif ( 'div' === $tag_type && $content_width ) {
							$args['w'] = \apply_filters( 'exactdn_content_bgimage_width', $content_width, $bg_image_url );
						}
						if ( false !== \strpos( $element_class, 'wp-block-group' ) && false !== \strpos( $element, 'background-size:auto' ) ) {
							$skip_autoscale = true;
						}
						if ( ( isset( $args['w'] ) && empty( $args['w'] ) ) || ! $bg_autoscale ) {
							unset( $args['w'] );
						}
						$exactdn_bg_image_url = $this->generate_url( $bg_image_url, $args );
						if ( $bg_image_url !== $exactdn_bg_image_url ) {
							$new_style = \str_replace( $orig_bg_url, $exactdn_bg_image_url, $new_style );

							$preload_image = $this->is_image_preloaded( $exactdn_bg_image_url, $orig_bg_url );
							if ( $preload_image ) {
								if ( $exactdn_bg_image_url !== $preload_image['url'] ) {
									$this->debug_message( "replacing {$preload_image['url']} with $exactdn_bg_image_url" );
									$new_preload_tag = $preload_image['tag'];
									$this->set_attribute( $new_preload_tag, 'href', \esc_url( $exactdn_bg_image_url ), true );
									if ( $preload_image['tag'] !== $new_preload_tag ) {
										$content = \str_replace( $preload_image['tag'], $new_preload_tag, $content );
									}
								}
								$element        = $this->skip_lazyload_for_preload( $element );
								$skip_autoscale = false;
							}
						}
					}
				}
				if ( $style !== $new_style ) {
					$element = \str_replace( $style, $new_style, $element );
				}
				if ( $skip_autoscale ) {
					$new_class = 'skip-autoscale';
					if ( ! empty( $element_class ) ) {
						$new_class = $element_class . ' skip-autoscale';
					}
					$this->set_attribute( $element, 'class', $new_class, true );
				}
				if ( $element !== $elements[ $index ] ) {
					$content = \str_replace( $elements[ $index ], $element, $content );
				}
			}
		}
		return $content;
	}

	/**
	 * Parse page content looking for CSS blocks with background-image properties.
	 *
	 * @param string $content The HTML content to parse.
	 * @return string The filtered HTML content.
	 */
	public function filter_style_blocks( $content ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Process background images on elements.
		$elements = $this->get_style_tags_from_html( $content );
		if ( $this->is_iterable( $elements ) ) {
			foreach ( $elements as $eindex => $element ) {
				$this->debug_message( 'parsing a style block, starts with: ' . \str_replace( "\n", '', \substr( $element, 0, 50 ) ) );
				if ( false === \strpos( $element, 'background:' ) && false === \strpos( $element, 'background-image:' ) ) {
					continue;
				}
				$bg_images = $this->get_background_images( $element );
				if ( $this->is_iterable( $bg_images ) ) {
					foreach ( $bg_images as $bindex => $bg_image ) {
						$this->debug_message( "parsing a background CSS rule: $bg_image" );
						$bg_image_url = $this->get_background_image_url( $bg_image );
						$this->debug_message( "found potential background image url: $bg_image_url" );
						if ( $this->validate_image_url( $bg_image_url ) ) {
							/** This filter is already documented in class-exactdn.php */
							if ( \apply_filters( 'exactdn_skip_image', false, $bg_image_url, $element ) ) {
								continue;
							}
							$exactdn_bg_image_url = $this->generate_url( $bg_image_url );
							if ( $bg_image_url !== $exactdn_bg_image_url ) {
								$this->debug_message( "replacing $bg_image_url with $exactdn_bg_image_url" );
								$bg_image = \str_replace( $bg_image_url, $exactdn_bg_image_url, $bg_image );
								if ( $bg_image !== $bg_images[ $bindex ] ) {
									$this->debug_message( "replacing bg url with $bg_image" );
									$element = \str_replace( $bg_images[ $bindex ], $bg_image, $element );
								}
							}
						}
					}
				}
				if ( $element !== $elements[ $eindex ] ) {
					$this->debug_message( 'replacing style block' );
					$content = \str_replace( $elements[ $eindex ], $element, $content );
				}
			}
		}
		return $content;
	}

	/**
	 * Parse page content looking for thumburl from personalization.com.
	 *
	 * @param string $content The HTML content to parse.
	 * @return string The filtered HTML content.
	 */
	public function filter_prz_thumb( $content ) {
		if ( ! \class_exists( '\WooCommerce' ) || false === \strpos( $content, 'productDetailsForPrz' ) ) {
			return $content;
		}
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$prz_match = \preg_match( '#productDetailsForPrz=[^<]+?thumbnailUrl:\'([^\']+?)\'[^<]+?</script>#', $content, $prz_detail_matches );
		if ( $prz_match && ! empty( $prz_detail_matches[1] ) && $this->validate_image_url( $prz_detail_matches[1] ) ) {
			$prz_thumb = $this->generate_url( $prz_detail_matches[1], \apply_filters( 'exactdn_personalizationdotcom_thumb_args', '', $prz_detail_matches[1] ) );
			if ( $prz_thumb !== $prz_detail_matches ) {
				$content = \str_replace( "thumbnailUrl:'{$prz_detail_matches[1]}'", "thumbnailUrl:'$prz_thumb'", $content );
			}
		}
		return $content;
	}

	/**
	 * Parse page content looking for Slider Revolution 6 slides.
	 *
	 * @param string $content The HTML content to parse.
	 * @return string The filtered HTML content.
	 */
	public function filter_sr6_slides( $content ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( false === strpos( $content, 'REVOLUTION SLIDER 6' ) ) {
			return $content;
		}
		// Process data-thumb images on rs-slide elements.
		$elements = $this->get_elements_from_html( $content, 'rs-slide' );
		if ( $this->is_iterable( $elements ) ) {
			foreach ( $elements as $eindex => $element ) {
				$this->debug_message( 'parsing a slide' );
				$thumb = $this->get_attribute( $element, 'data-thumb' );
				if ( $thumb ) {
					$this->debug_message( "parsing a sr6 thumb: $thumb" );
					if ( $this->validate_image_url( $thumb ) ) {
						$this->debug_message( 'rewriting slide thumb...' );
						$this->set_attribute( $element, 'data-thumb', $this->generate_url( $thumb ), true );
						if ( $element !== $elements[ $eindex ] ) {
							$content = \str_replace( $elements[ $eindex ], $element, $content );
						}
					}
				}
			}
		}
		// Process data-poster images on rs-layer elements.
		$elements = $this->get_elements_from_html( $content, 'rs-layer' );
		if ( $this->is_iterable( $elements ) ) {
			foreach ( $elements as $eindex => $element ) {
				$this->debug_message( 'parsing a layer' );
				$poster = $this->get_attribute( $element, 'data-poster' );
				if ( $poster ) {
					$this->debug_message( "parsing a sr6 poster: $poster" );
					if ( $this->validate_image_url( $poster ) ) {
						$this->debug_message( 'rewriting layer poster...' );
						$this->set_attribute( $element, 'data-poster', $this->generate_url( $poster ), true );
						if ( $element !== $elements[ $eindex ] ) {
							$content = \str_replace( $elements[ $eindex ], $element, $content );
						}
					}
				}
			}
		}
		return $content;
	}

	/**
	 * Parse Slider Revolution 7 image lists and convert them to CDN URLs.
	 *
	 * @param array $images The list of images to rewrite.
	 * @return array The filtered image list.
	 */
	public function filter_sr7_image_lists( $images ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! $this->is_iterable( $images ) ) {
			return $images;
		}
		if ( ! empty( $images ) ) {
			$this->debug_message( 'we have SR7 images to parse' );
			foreach ( $images as $index => $image ) {
				if ( is_array( $image ) && ! empty( $image['src'] ) && $this->validate_image_url( $image['src'] ) ) {
					$images[ $index ]['src'] = $this->generate_url( $image['src'] );
				} elseif ( is_string( $image ) && $this->validate_image_url( $image ) ) {
					$images[ $index ] = $this->generate_url( $image );
				}
			} // End foreach() -- of more images found in the page.
		} // End if() -- we found more images in the page.
		return $images;
	}

	/**
	 * Parse page content looking for wp-content/wp-includes URLs to rewrite.
	 *
	 * @param string $content The HTML content to parse.
	 * @return string The filtered HTML content.
	 */
	public function filter_all_the_things( $content ) {
		if ( $this->exactdn_domain && $this->upload_domain && $this->content_path ) {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			$upload_domain = $this->upload_domain;
			if ( 0 === \strpos( $this->upload_domain, 'www.' ) ) {
				$upload_domain = \substr( $this->upload_domain, 4 );
			}
			$escaped_upload_domain = \str_replace( '.', '\.', $upload_domain );
			$this->debug_message( $escaped_upload_domain );
			if ( ! empty( $this->user_exclusions ) ) {
				$content = \preg_replace( '#(https?:)?//(?:www\.)?' . $escaped_upload_domain . '([^"\'?>]+?)?/' . $this->content_path . '/([^"\'?>]+?)?(' . \implode( '|', $this->user_exclusions ) . ')#i', '$1//' . $this->upload_domain . '$2/?wpcontent-bypass?/$3$4', $content );
			}
			if ( \strpos( $content, '<use ' ) ) {
				// Pre-empt rewriting of files within <use> tags, particularly to prevent security errors for SVGs.
				$this->debug_message( 'searching for use tags: #(<use.+?href=["\'])(https?:)?//(?:www\.)?' . $escaped_upload_domain . '([^"\'?>]+?)/' . $this->content_path . '/#is' );
				$content = \preg_replace( '#(<use\s+?(?>xlink:)?href=["\'])(https?:)?//(?>www\.)?' . $escaped_upload_domain . '([^"\'?>]+?)?/' . $this->content_path . '/#is', '$1$2//' . $this->upload_domain . '$3/?wpcontent-bypass?/', $content );
			}
			// Pre-empt rewriting of wp-includes and wp-content if the extension is not allowed by using a temporary placeholder.
			$content = \preg_replace( '#(https?:)?//(?:www\.)?' . $escaped_upload_domain . '([^"\'?>]+?)?/' . $this->content_path . '/([^"\'?>]+?)\.(htm|html|php|ashx|wvm|qt|ogv|mpg|mpeg|mpv)#i', '$1//' . $this->upload_domain . '$2/?wpcontent-bypass?/$3.$4', $content );
			// Pre-empt partial paths that are used by JS to build other URLs.
			$content = \str_replace( $this->content_path . '/themes/jupiter"', '?wpcontent-bypass?/themes/jupiter"', $content );
			$content = \str_replace( $this->content_path . '/plugins/onesignal-free-web-push-notifications/sdk_files/"', '?wpcontent-bypass?/plugins/onesignal-free-web-push-notifications/sdk_files/"', $content );
			$content = \str_replace( $this->content_path . '/plugins/u-shortcodes/shortcodes/monthview/"', '?wpcontent-bypass?/plugins/u-shortcodes/shortcodes/monthview/"', $content );
			if (
				false !== \strpos( $this->upload_domain, 'amazonaws.com' ) ||
				false !== \strpos( $this->upload_domain, 'digitaloceanspaces.com' ) ||
				false !== \strpos( $this->upload_domain, 'storage.googleapis.com' )
			) {
				$this->debug_message( 'searching for #(https?:)?//(?:www\.)?' . $escaped_upload_domain . $this->remove_path . '/#i and replacing with $1//' . $this->exactdn_domain . '/' );
				$content = \preg_replace( '#(https?:)?//(?:www\.)?' . $escaped_upload_domain . $this->remove_path . '/#i', '$1//' . $this->exactdn_domain . '/', $content );
			} else {
				$this->debug_message( 'searching for #(https?:)?//(?:www\.)?' . $escaped_upload_domain . '((?:/[^"\'?&>:/]+?){0,3})/(nextgen-image|' . $this->include_path . '|' . $this->content_path . ')/#i and replacing with $1//' . $this->exactdn_domain . '$2/$3/' );
				$content = \preg_replace( '#(https?:)?//(?:www\.)?' . $escaped_upload_domain . '((?:/[^"\'?&>:/]+?){0,3})/(nextgen-image|' . $this->include_path . '|' . $this->content_path . ')/#i', '$1//' . $this->exactdn_domain . '$2/$3/', $content );
			}
			if ( $this->asset_domains && \apply_filters( 'eio_rewrite_all_the_assets', true ) ) {
				$asset_domains = \explode( ',', $this->asset_domains );
				foreach ( $asset_domains as $asset_domain ) {
					$asset_domain          = \trim( $asset_domain );
					$escaped_upload_domain = \str_replace( '.', '\.', $asset_domain );
					if ( $asset_domain === $this->home_domain ) {
						$this->debug_message( 'searching (assets) for #(https?:)?//(?:www\.)?' . $escaped_upload_domain . '((?:/[^"\'?&>:/]+?){0,3})/(nextgen-image|' . $this->include_path . '|' . $this->content_path . ')/#i and replacing with $1//' . $this->exactdn_domain . '/easyio-assets/' . $asset_domain . '$2/$3/' );
						$content = \preg_replace( '#(https?:)?//(?:www\.)?' . $escaped_upload_domain . '((?:/[^"\'?&>:/]+?){0,3})/(nextgen-image|' . $this->include_path . '|' . $this->content_path . ')/#i', '$1//' . $this->exactdn_domain . '/easyio-assets/' . $asset_domain . '$2/$3/', $content );
					} else {
						$this->debug_message( 'searching (assets) for #(https?:)?//(?:www\.)?' . $escaped_upload_domain . '/#i and replacing with $1//' . $this->exactdn_domain . '/easyio-assets/' . $asset_domain . '/' );
						$content = \preg_replace( '#(https?:)?//(?:www\.)?' . $escaped_upload_domain . '/#i', '$1//' . $this->exactdn_domain . '/easyio-assets/' . $asset_domain . '/', $content );
					}
				}
			}
			$content = \str_replace( '?wpcontent-bypass?', $this->content_path, $content );
			$content = $this->replace_fonts( $content );
		}
		return $content;
	}

	/**
	 * Check an image URL for preload status.
	 *
	 * @param string $exactdn_url The CDN version of an image URL.
	 * @param string $original_url The pre-CDN version of an image URL. Optional.
	 * @return array|bool The preload array/details if the URL is being preloaded, false otherwise.
	 */
	protected function is_image_preloaded( $exactdn_url, $original_url = '' ) {
		if ( empty( $original_url ) ) {
			$original_url = $exactdn_url;
		}
		$original_path = $this->parse_url( $original_url, PHP_URL_PATH );
		foreach ( $this->preload_images as $preload_index => $preload_image ) {
			if ( ! empty( $preload_image['found'] ) ) {
				continue;
			}
			if ( $original_path === $preload_image['path'] ) {
				$this->debug_message( "found a preload match for $original_path" );
				$this->preload_images[ $preload_index ]['found'] = true;
				return $preload_image;
			}
		}
		return false;
	}

	/**
	 * Prevent an HTML element, like an img or a div with background image(s), from being lazyloaded or autoscaled.
	 *
	 * @param string $tag The HTML tag to be modified.
	 * @return string The modified HTML tag.
	 */
	protected function skip_lazyload_for_preload( $tag ) {
		if ( \defined( 'EIO_LAZY_PRELOAD' ) && EIO_LAZY_PRELOAD ) {
			if ( false === \strpos( $tag, 'skip-autoscale' ) ) {
				$this->set_attribute( $tag, 'data-skip-autoscale', '1' );
			}
		} else {
			if ( false === \strpos( $tag, 'skip-lazy' ) ) {
				$this->set_attribute( $tag, 'data-skip-lazy', '1' );
			}
		}
		return $tag;
	}

	/**
	 * Parse page content looking for (Google) font URLs to rewrite.
	 *
	 * @param string $content The HTML content to parse.
	 * @return string The filtered HTML content.
	 */
	public function replace_fonts( $content ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( \defined( 'EASYIO_REPLACE_GOOGLE_FONTS' ) && ! EASYIO_REPLACE_GOOGLE_FONTS ) {
			return $content;
		}
		if ( ! \defined( 'EASYIO_REPLACE_GOOGLE_FONTS' ) ) {
			foreach ( $this->user_exclusions as $exclusion ) {
				if (
					false !== strpos( $exclusion, 'googleapi' ) ||
					false !== strpos( $exclusion, 'googleapis' ) ||
					false !== strpos( $exclusion, 'gstatic' ) ||
					false !== strpos( $exclusion, 'googlefont' )
				) {
					\define( 'EASYIO_REPLACE_GOOGLE_FONTS', false );
				}
			}
		}
		$bunny_prefetch_exists    = false;
		$bunny_preconnect_exists  = false;
		$bunny_crossorigin_exists = false;
		if ( 'bunny' === $this->replaced_google_fonts ) {
			$bunny_prefetch_exists    = \preg_match( '#<link\s+rel=[\'"]?dns-prefetch[\'"]?\s+href=[\'"]?//fonts\.bunny\.net[\'"]?\s*/?>#is', $content );
			$bunny_preconnect_exists  = \preg_match( '#<link\s+rel=[\'"]?preconnect[\'"]?\s+href=[\'"]?//fonts\.bunny\.net[\'"]?\s*/?>#is', $content );
			$bunny_crossorigin_exists = \preg_match( '#<link\s+rel=[\'"]?preconnect[\'"]?\s+href=[\'"]?//fonts\.bunny\.net[\'"]?\s*crossorigin\s*/?>#is', $content );
		}
		if (
			\defined( 'EASYIO_REPLACE_GOOGLE_FONTS' ) && 'bunny' === EASYIO_REPLACE_GOOGLE_FONTS &&
			( ! \function_exists( '\swis' ) || ! \swis()->settings->get_option( 'optimize_fonts' ) )
		) {
			$content = \str_replace( '//fonts.googleapis.com/css', '//fonts.bunny.net/css', $content, $gfonts_replaced );
			if ( $gfonts_replaced ) {
				$this->replaced_google_fonts = 'bunny';
			}
			if ( 'bunny' === $this->replaced_google_fonts ) {
				if ( ! $bunny_preconnect_exists ) {
					$content = \preg_replace( '#<link\s+rel=[\'"]?preconnect[\'"]?\s+href=[\'"]?//fonts\.googleapis\.com[\'"]?\s*(crossorigin\s*)?/?>#is', "<link rel='preconnect' href='//fonts.bunny.net' $2/>", $content, 1, $bunny_preconnect_exists );
				}
				if ( ! $bunny_prefetch_exists ) {
					$content = \preg_replace( '#<link\s+rel=[\'"]?dns-prefetch[\'"]?\s+href=[\'"]?//fonts\.googleapis\.com[\'"]?\s*/?>#is', "<link rel='dns-prefetch' href='//fonts.bunny.net' />", $content, 1, $bunny_prefetch_exists );
				}
				if ( ! $bunny_crossorigin_exists ) {
					$content = \preg_replace( '#<link\s+rel=[\'"]?preconnect[\'"]?\s+href=[\'"]?//fonts\.gstatic\.com[\'"]?\s*?(crossorigin\s*)?/?>#is', "<link rel='preconnect' href='//fonts.bunny.net' crossorigin />", $content, 1, $bunny_crossorigin_exists );
				}
				if ( ! $bunny_prefetch_exists ) {
					$content = \preg_replace( '#<link\s+rel=[\'"]?dns-prefetch[\'"]?\s+href=[\'"]?//fonts\.gstatic\.com[\'"]?\s*/?>#is', "<link rel='dns-prefetch' href='//fonts.bunny.net' />", $content, 1, $bunny_prefetch_exists );
				}
			}
		} elseif ( ! \defined( 'EASYIO_REPLACE_GOOGLE_FONTS' ) || EASYIO_REPLACE_GOOGLE_FONTS ) {
			$content = \str_replace( '//fonts.googleapis.com/css', '//' . $this->exactdn_domain . '/easyio-fonts/css', $content, $gfontcss_replaced );
			$content = \str_replace( '//fonts.gstatic.com/s/', '//' . $this->exactdn_domain . '/easyio-gfont/s/', $content, $gfonts_replaced );
			if ( $gfontcss_replaced || $gfonts_replaced ) {
				$this->replaced_google_fonts = 'easyio';
			}
		}
		if ( 'bunny' === $this->replaced_google_fonts ) {
			// NOTE: Bunny Fonts cannot directly replace the actual font URLs, so we've only replaced the CSS URLs.
			// Thus we check to see if Google Fonts have been inlined directly. If not, then we nuke any remaining resource hints.
			if ( false === \strpos( $content, '//fonts.gstatic.com/s/' ) ) {
				$content = \preg_replace( '#<link\s+rel=[\'"]?(preconnect|dns-prefetch)[\'"]?\s+href=[\'"]?//fonts\.(googleapis|gstatic)\.com[\'"]?\s*(crossorigin\s*)?/?>\s*#is', '', $content );
			}
			$bunny_resource_hints = '';
			if ( ! $bunny_prefetch_exists && ! $bunny_preconnect_exists && ! $bunny_crossorigin_exists ) {
					$bunny_resource_hints = "\n<link rel='dns-prefetch' href='//fonts.bunny.net' />" .
						"\n<link rel='preconnect' href='//fonts.bunny.net' />" .
						"\n<link rel='preconnect' href='//fonts.bunny.net' crossorigin />";
			} else {
				if ( ! $bunny_prefetch_exists ) {
					$bunny_resource_hints .= "\n<link rel='dns-prefetch' href='//fonts.bunny.net' />";
				}
				if ( ! $bunny_preconnect_exists ) {
					$bunny_resource_hints .= "\n<link rel='preconnect' href='//fonts.bunny.net' />";
				}
				if ( ! $bunny_crossorigin_exists ) {
					$bunny_resource_hints .= "\n<link rel='preconnect' href='//fonts.bunny.net' crossorigin />";
				}
			}
			if ( $bunny_resource_hints ) {
				$escaped_exactdn_domain = \str_replace( '.', '\.', $this->exactdn_domain );
				// Now we find the preconnect directive for the *.exactdn.com domain and add the Bunny hints.
				$content = \preg_replace(
					'#<link\s+rel=[\'"]?preconnect[\'"]?\s+href=[\'"]?//' . $escaped_exactdn_domain . '[\'"]?\s*/?>#is',
					"\$0$bunny_resource_hints",
					$content
				);
			}
		} elseif ( 'easyio' === $this->replaced_google_fonts ) {
			$escaped_exactdn_domain = \str_replace( '.', '\.', $this->exactdn_domain );
			// First, remove any hints for Google Fonts.
			$content = \preg_replace( '#<link\s+rel=[\'"]?(preconnect|dns-prefetch)[\'"]?\s+href=[\'"]?//fonts\.(googleapis|gstatic)\.com[\'"]?\s*(crossorigin\s*)?/?>\s*#is', '', $content );
			// Then we find the preconnect directive for the *.exactdn.com domain and insert an extra crossorigin directive for fonts.
			if ( ! preg_match( '#<link\s+?rel=[\'"]?preconnect[\'"]?\s+?href=[\'"]?//' . $escaped_exactdn_domain . '[\'"]?\s*crossorigin#is', $content ) ) {
				$content = \preg_replace(
					'#<link\s+?rel=[\'"]?preconnect[\'"]?\s+?href=[\'"]?//' . $escaped_exactdn_domain . '[\'"]?\s*?/?>#is',
					"\$0\n<link rel='preconnect' href='//" . esc_attr( $this->exactdn_domain ) . "' crossorigin />",
					$content
				);
			}
		}
		return $content;
	}

	/**
	 * Allow resizing of images for some admin-ajax requests.
	 *
	 * @param bool  $allow Will normally be false, unless already modified by another function.
	 * @param array $image Bunch of information about the image, but we don't care about that here.
	 * @return bool True if it's an allowable admin-ajax request, false for all other admin requests.
	 */
	public function allow_admin_image_downsize( $allow, $image ) {
		if ( ! \wp_doing_ajax() ) {
			return $allow;
		}
		if ( ! empty( $_REQUEST['action'] ) && 'alm_get_posts' === $_REQUEST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}
		if ( ! empty( $_POST['action'] ) && 'do_filter_products' === $_POST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}
		if ( ! empty( $_POST['action'] ) && 'eddvbugm_viewport_downloads' === $_POST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}
		if ( ! empty( $_POST['action'] ) && 'Essential_Grid_Front_request_ajax' === $_POST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}
		if ( ! empty( $_POST['action'] ) && 'filter_listing' === $_POST['action'] && ! empty( $_POST['layout'] ) && ! empty( $_POST['paged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}
		if ( ! empty( $_POST['action'] ) && 'load_more_posts' === $_POST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}
		if ( ! empty( $_POST['action'] ) && 'mabel-rpn-getnew-purchased-products' === $_POST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}
		if ( ! empty( $_POST['action'] ) && 'um_activity_load_wall' === $_POST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}
		if ( ! empty( $_POST['action'] ) && 'vc_get_vc_grid_data' === $_POST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}
		return $allow;
	}

	/**
	 * Disable resizing of images during image_downsize().
	 *
	 * @param mixed $param Could be anything (or nothing), we just pass it along untouched.
	 * @return mixed Just the same value, going back out the door.
	 */
	public function disable_image_downsize( $param = false ) {
		\remove_filter( 'image_downsize', array( $this, 'filter_image_downsize' ) );
		\add_action( 'themify_after_post_image', array( $this, 'enable_image_downsize' ) );
		return $param;
	}

	/**
	 * Re-enable resizing of images during image_downsize().
	 */
	public function enable_image_downsize() {
		\add_filter( 'image_downsize', array( $this, 'filter_image_downsize' ), 10, 3 );
	}

	/**
	 * Change the default for processing an array of dimensions to scaling instead of cropping.
	 *
	 * @param array $exactdn_args The ExactDN args generated by filter_image_downsize() when $size is an array.
	 * @return array $exactdn_args The ExactDN args, with resize (crop) changed to fit (scale).
	 */
	public function image_downsize_scale( $exactdn_args ) {
		if ( ! is_array( $exactdn_args ) ) {
			return $exactdn_args;
		}
		if ( ! empty( $exactdn_args['resize'] ) ) {
			$exactdn_args['fit'] = $exactdn_args['resize'];
			unset( $exactdn_args['resize'] );
		}
		return $exactdn_args;
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
	public function filter_image_downsize( $image, $attachment_id, $size ) {
		$started = \microtime( true );
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );

		if ( \is_array( $attachment_id ) || \is_object( $attachment_id ) ) {
			return $image;
		}

		// Don't foul up the admin side of things, unless a plugin wants to.
		if ( \is_admin() &&
			/**
			 * Provide plugins a way of running ExactDN for images in the WordPress Dashboard (wp-admin).
			 *
			 * Note: enabling this will result in ExactDN URLs added to your post content, which could make migrations across domains (and off ExactDN) a bit more challenging.
			 *
			 * @param bool false Allow ExactDN to run on the Dashboard. Default to false.
			 * @param array $args {
			 *     Array of image details.
			 *
			 *     @type array|bool  $image Image URL or false.
			 *     @type int          $attachment_id Attachment ID of the image.
			 *     @type array|string $size Image size. Can be a string (name of the image size, e.g. full) or an array of height and width.
			 * }
			 */
			false === \apply_filters( 'exactdn_admin_allow_image_downsize', false, \compact( 'image', 'attachment_id', 'size' ) )
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
		if ( \apply_filters( 'exactdn_override_image_downsize', false, \compact( 'image', 'attachment_id', 'size' ) ) ) {
			return $image;
		}
		// Make it easier to skip all images by URI.
		if ( \apply_filters( 'exactdn_skip_page', false, $this->request_uri ) ) {
			return $image;
		}

		if ( \function_exists( '\aq_resize' ) ) {
			$this->debug_message( 'aq_resize detected, image_downsize filter bypassed' );
			return $image;
		}

		// BFI Thumb integration, usually Elementor, possibly others.
		if ( \is_array( $size ) && ! empty( $size['bfi_thumb'] ) ) {
			$this->debug_message( 'bfi_thumb detected, image_downsize filter bypassed' );
			return $image;
		}

		if ( $this->filtering_the_content || $this->filtering_the_page ) {
			$this->debug_message( 'end image_downsize early' );
			return $image;
		}

		// Get the image URL and proceed with ExactDN replacement if successful.
		$image_url = \wp_get_attachment_url( $attachment_id );
		/** This filter is already documented in class-exactdn.php */
		if ( \apply_filters( 'exactdn_skip_image', false, $image_url, null ) ) {
			return $image;
		}
		$this->debug_message( "image_url: $image_url" );
		$this->debug_message( "attachment_id: $attachment_id" );
		if ( \is_string( $size ) || \is_int( $size ) ) {
			$this->debug_message( $size );
		} elseif ( \is_array( $size ) ) {
			foreach ( $size as $dimension ) {
				$this->debug_message( 'dimension: ' . $dimension );
			}
		}
		// Set this to true later when we know we have size meta.
		$has_size_meta = false;
		// To indicate whether or not we've already tried to get meta from the db.
		$got_meta = false;

		if ( $image_url ) {
			// Check if image URL should be used with ExactDN.
			if ( ! $this->validate_image_url( $image_url ) ) {
				return $image;
			}

			$intermediate    = true; // For the fourth array item returned by the image_downsize filter.
			$resize_existing = \defined( 'EXACTDN_RESIZE_EXISTING' ) && EXACTDN_RESIZE_EXISTING;

			// If an image is requested with a size known to WordPress, use that size's settings with ExactDN.
			if ( \is_string( $size ) && \array_key_exists( $size, $this->image_sizes() ) ) {
				// Get all the size data.
				$image_args = $this->image_sizes();
				// Then retrieve just the one size from the full list stored in $image_args.
				$image_args = $image_args[ $size ];
				$this->debug_message( "image args for $size: " . $this->implode( ',', $image_args ) );

				$exactdn_args = array();

				$image_meta = \image_get_intermediate_size( $attachment_id, $size );

				// Tracking this separately, because the width/height from the $image_meta might get blown out.
				$full_width  = false;
				$full_height = false;

				// 'full' is a special case: We need consistent data regardless of the requested size.
				if ( 'full' === $size ) {
					$image_meta   = \wp_get_attachment_metadata( $attachment_id );
					$intermediate = false;
					$got_meta     = true;
					$full_width   = ! empty( $image_meta['width'] ) ? (int) $image_meta['width'] : false;
					$full_height  = ! empty( $image_meta['height'] ) ? (int) $image_meta['height'] : false;
				} elseif ( ! $image_meta ) {
					$this->debug_message( 'still do not have meta, getting it now' );
					// If we still don't have any image meta at this point, it's probably from a custom thumbnail size
					// for an image that was uploaded before the custom image was added to the theme. Try to determine the size manually.
					$image_meta = \wp_get_attachment_metadata( $attachment_id );
					$got_meta   = true;

					if ( isset( $image_meta['width'], $image_meta['height'] ) ) {
						$full_width  = ! empty( $image_meta['width'] ) ? (int) $image_meta['width'] : false;
						$full_height = ! empty( $image_meta['height'] ) ? (int) $image_meta['height'] : false;
						$this->debug_message( "original (full-size) dimensions (w,h): $full_width, $full_height" );
						$image_resized = \image_resize_dimensions( $image_meta['width'], $image_meta['height'], $image_args['width'], $image_args['height'], $image_args['crop'] );
						if ( $image_resized ) { // This could be false when the requested image size is larger than the full-size image.
							// The new dimensions here are what we'd use to crop/scale the image. If crop is truthy, then the dimensions
							// will not match the original dimensions. This is why we track $full_width/$full_height separately.
							$this->debug_message( 'new full-size parameters, 6 and 7 are the scaled dimensions:' );
							if ( \is_array( $image_resized ) ) {
								$this->debug_message( \implode( ', ', $image_resized ) );
							}
							$image_meta['width']  = $image_resized[6];
							$image_meta['height'] = $image_resized[7];
						}
					}
				}
				if ( isset( $image_meta['width'], $image_meta['height'] ) ) {
					// This might seem strange, but we'll constrain this by size name ($size) shortly.
					$image_args['width']  = $image_meta['width'];
					$image_args['height'] = $image_meta['height'];

					// Make the custom $content_width apply here.
					global $content_width;
					if ( ! empty( $content_width ) && $this->get_content_width() > $content_width ) {
						$real_content_width = $content_width;
						$content_width      = $this->get_content_width();
					}
					// NOTE: it will constrain an image to $content_width which is expected behavior in core, so far as I can see.
					list( $image_args['width'], $image_args['height'] ) = \image_constrain_size_for_editor( $image_args['width'], $image_args['height'], $size, 'display' );
					// And then set $content_width back to normal.
					if ( ! empty( $real_content_width ) ) {
						$content_width = $real_content_width;
					}

					$has_size_meta = true;
					$this->debug_message( 'image args constrained: ' . $this->implode( ',', $image_args ) );
				}

				$transform = $image_args['crop'] ? 'resize' : 'fit';

				// Check specified image dimensions and account for possible zero values; ExactDN fails to resize if a dimension is zero.
				if ( ! $image_args['width'] || ! $image_args['height'] ) {
					if ( ! $image_args['width'] && 0 < $image_args['height'] ) {
						$exactdn_args['h'] = $image_args['height'];
					} elseif ( ! $image_args['height'] && 0 < $image_args['width'] ) {
						$exactdn_args['w'] = $image_args['width'];
					}
				} else {
					if ( ! isset( $image_meta['sizes'] ) && empty( $got_meta ) ) {
						$size_meta = $image_meta;
						// Because we don't have the "real" meta, just the height/width for the specific size.
						$this->debug_message( 'getting attachment meta now' );
						$image_meta  = \wp_get_attachment_metadata( $attachment_id );
						$full_width  = ! empty( $image_meta['width'] ) ? (int) $image_meta['width'] : false;
						$full_height = ! empty( $image_meta['height'] ) ? (int) $image_meta['height'] : false;
					}
					if ( 'resize' === $transform && $image_meta && isset( $image_meta['width'], $image_meta['height'] ) ) {
						// Lets make sure that we don't upscale images since wp never upscales them as well.
						$smaller_width  = ( ( $image_meta['width'] < $image_args['width'] ) ? $image_meta['width'] : $image_args['width'] );
						$smaller_height = ( ( $image_meta['height'] < $image_args['height'] ) ? $image_meta['height'] : $image_args['height'] );

						$exactdn_args[ $transform ] = $smaller_width . ',' . $smaller_height;
					} else {
						$exactdn_args[ $transform ] = $image_args['width'] . ',' . $image_args['height'];
					}
				}

				if (
					$full_width && $full_height &&
					(int) $image_args['width'] === (int) $full_width &&
					(int) $image_args['height'] === (int) $full_height
				) {
					$this->debug_message( 'image args match size of original, just use that' );
					$size = 'full';
				}
				if ( empty( $image_meta['sizes'] ) && ! empty( $size_meta ) ) {
					$image_meta['sizes'][ $size ] = $size_meta;
				}
				if ( ! empty( $image_meta['sizes'] ) && 'full' !== $size && ! empty( $image_meta['sizes'][ $size ]['file'] ) ) {
					$image_url_basename = \wp_basename( $image_url );
					$intermediate_url   = \str_replace( $image_url_basename, $image_meta['sizes'][ $size ]['file'], $image_url );

					if ( empty( $image_meta['sizes'][ $size ]['width'] ) || empty( $image_meta['sizes'][ $size ]['height'] ) ) {
						list( $filename_width, $filename_height ) = $this->get_dimensions_from_filename( $intermediate_url );
					} else {
						$filename_width  = $image_meta['sizes'][ $size ]['width'];
						$filename_height = $image_meta['sizes'][ $size ]['height'];
					}
					if ( $filename_width && $filename_height && $image_args['width'] === $filename_width && $image_args['height'] === $filename_height ) {
						$this->debug_message( "changing $image_url to $intermediate_url" );
						$image_url = $intermediate_url;
					} else {
						$resize_existing = true;
					}
				} else {
					$resize_existing = true;
				}

				$exactdn_args = $resize_existing && 'full' !== $size ? $this->maybe_smart_crop( $exactdn_args, $attachment_id, $image_meta ) : array();

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
				$exactdn_args = \apply_filters( 'exactdn_image_downsize_string', $exactdn_args, \compact( 'image_args', 'image_url', 'attachment_id', 'size', 'transform' ) );

				// Generate ExactDN URL.
				$image = array(
					$this->generate_url( $image_url, $exactdn_args ),
					$has_size_meta ? $image_args['width'] : false,
					$has_size_meta ? $image_args['height'] : false,
					$intermediate,
				);
			} elseif ( \is_array( $size ) ) {
				// Pull width and height values from the provided array, if possible.
				$width  = isset( $size[0] ) && $size[0] < 9999 ? (int) $size[0] : false;
				$height = isset( $size[1] ) && $size[1] < 9999 ? (int) $size[1] : false;

				// Don't bother if necessary parameters aren't passed.
				if ( ! $width || ! $height ) {
					return $image;
				}
				$this->debug_message( "requested w$width by h$height" );

				$image_meta = \wp_get_attachment_metadata( $attachment_id );
				if ( isset( $image_meta['width'], $image_meta['height'] ) ) {
					$image_resized = \image_resize_dimensions( $image_meta['width'], $image_meta['height'], $width, $height, true );

					if ( $image_resized ) { // This could be false when the requested image size is larger than the full-size image.
						$width  = $image_resized[6];
						$height = $image_resized[7];
						$this->debug_message( "using resize dims w$width by h$height" );
					} else {
						$width  = $image_meta['width'];
						$height = $image_meta['height'];
						$this->debug_message( "using meta dims w$width by h$height" );
					}
					$has_size_meta = true;
				}

				global $content_width;
				if ( ! empty( $content_width ) && $this->get_content_width() > $content_width ) {
					$real_content_width = $content_width;
					$content_width      = $this->get_content_width();
				}
				list( $width, $height ) = \image_constrain_size_for_editor( $width, $height, $size );
				$this->debug_message( "constrained to w$width by h$height" );
				if ( ! empty( $real_content_width ) ) {
					$content_width = $real_content_width;
				}

				// Expose arguments to a filter before passing to ExactDN.
				$exactdn_args = array(
					'resize' => $width . ',' . $height,
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
				$exactdn_args = \apply_filters( 'exactdn_image_downsize_array', $exactdn_args, \compact( 'width', 'height', 'image_url', 'attachment_id' ) );

				// Generate ExactDN URL.
				$image = array(
					$this->generate_url( $image_url, $exactdn_args ),
					$has_size_meta ? $width : false,
					$has_size_meta ? $height : false,
					$intermediate,
				);
			}
		}
		if ( ! empty( $image[0] ) && \is_string( $image[0] ) ) {
			$this->debug_message( $image[0] );
		}
		$this->debug_message( 'end image_downsize' );
		$elapsed_time = \microtime( true ) - $started;
		$this->debug_message( "parsing image_downsize took $elapsed_time seconds" );
		$this->elapsed_time += \microtime( true ) - $started;
		return $image;
	}

	/**
	 * Replaces images within a srcset attribute with Easy IO URLs.
	 *
	 * @param string $srcset A valid srcset attribute from an img element.
	 * @return string The srcset attribute with Easy IO URLs.
	 */
	public function srcset_replace( $srcset ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$srcset_urls = \explode( ' ', $srcset );
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
				$new_srcurl = $srcurl;
				// Check for relative URLs that start with a slash.
				if (
					'/' === \substr( $srcurl, 0, 1 ) &&
					'/' !== \substr( $srcurl, 1, 1 ) &&
					false === \strpos( $this->upload_domain, 'amazonaws.com' ) &&
					false === \strpos( $this->upload_domain, 'digitaloceanspaces.com' ) &&
					false === \strpos( $this->upload_domain, 'storage.googleapis.com' )
				) {
					$new_srcurl = '//' . $this->upload_domain . $new_srcurl;
				}
				if ( $this->validate_image_url( $new_srcurl ) ) {
					$srcset = \str_replace( $srcurl . $trailing, $this->generate_url( $new_srcurl ) . $trailing, $srcset );
					$this->debug_message( "replaced $srcurl in srcset" );
				}
			}
		} elseif ( $this->validate_image_url( $srcset ) ) {
			return $this->generate_url( $srcset );
		}
		return $srcset;
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
		$started = \microtime( true );
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Don't foul up the admin side of things, unless a plugin wants to.
		if ( \is_admin() &&
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
			false === \apply_filters( 'exactdn_admin_allow_image_srcset', false, \compact( 'image_src', 'attachment_id' ) )
		) {
			return $sources;
		}
		if ( \apply_filters( 'exactdn_skip_page', false, $this->request_uri ) ) {
			return $sources;
		}

		if ( ! \is_array( $sources ) ) {
			return $sources;
		}

		$this->debug_message( "image_src = $image_src" );
		$resize_existing = \defined( 'EXACTDN_RESIZE_EXISTING' ) && EXACTDN_RESIZE_EXISTING;
		$w_descriptor    = true;

		foreach ( $sources as $i => $source ) {
			if ( 'x' === $source['descriptor'] ) {
				$w_descriptor = false;
			}
			if ( ! $this->validate_image_url( $source['url'] ) ) {
				continue;
			}

			/** This filter is already documented in class-exactdn.php */
			if ( \apply_filters( 'exactdn_skip_image', false, $source['url'], $source ) ) {
				continue;
			}

			$url = $source['url'];

			list( $width, $height ) = $this->get_dimensions_from_filename( $url );
			if ( ! $resize_existing && 'w' === $source['descriptor'] && (int) $source['value'] === (int) $width ) {
				$this->debug_message( "preventing further processing for $url" );
				$sources[ $i ]['url'] = $this->generate_url( $source['url'] );
				continue;
			}

			if ( $image_meta && ! empty( $image_meta['width'] ) ) {
				if ( ( $height && (int) $image_meta['height'] === (int) $height && $width && (int) $image_meta['width'] === (int) $width ) ||
					( ! $height && ! $width && (int) $image_meta['width'] === (int) $source['value'] )
				) {
					$this->debug_message( "preventing further processing for (detected) full-size $url" );
					$sources[ $i ]['url'] = $this->generate_url( $source['url'] );
					continue;
				}
			}

			$this->debug_message( 'continuing: ' . $width . ' vs. ' . $source['value'] );

			// It's quicker to get the full size with the data we have already, if available.
			if ( ! empty( $full_url ) ) {
				$url = $full_url;
			} elseif ( ! empty( $attachment_id ) ) {
				$full_url = \wp_get_attachment_url( $attachment_id );
				$url      = $full_url;
			} else {
				$full_url = $this->strip_image_dimensions_maybe( $url );
				if ( $full_url === $url ) {
					$full_url = '';
				}
			}
			$this->debug_message( "building srcs from $url" );

			$args = array();
			if ( 'w' === $source['descriptor'] ) {
				if ( $height && ( (int) $source['value'] === (int) $width ) ) {
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
		 */

		/**
		 * Filter the multiplier ExactDN uses to create new srcset items.
		 * Return false to short-circuit and bypass auto-generation.
		 *
		 * @param array|bool $multipliers Array of multipliers to use or false to bypass.
		 */
		$multipliers = \apply_filters( 'exactdn_srcset_multipliers', array( .2, .4, .6, .8, 1, 2, 3, 1920 ) );

		if ( empty( $url ) || empty( $multipliers ) ) {
			// No URL, or no multipliers, bail!
			return $sources;
		}

		if ( ! empty( $full_url ) ) {
			$this->debug_message( "already built url via db: $full_url" );
			$url = $full_url;
		} elseif ( 0 === \strpos( $image_meta['file'], '/' ) ) {
			$this->debug_message( 'full meta appears to be absolute path, retrieving URL via wp_get_attachment_url()' );
			$url = \wp_get_attachment_url( $attachment_id );
		} else {
			$base_dir  = \dirname( $url );
			$meta_file = $image_meta['file'];
			$this->debug_message( "checking to see if we can join $base_dir with $meta_file" );
			if ( false !== \strpos( $meta_file, '/' ) ) {
				$meta_dir = \dirname( $meta_file );
				if ( \str_ends_with( $base_dir, $meta_dir ) ) {
					$meta_file = \wp_basename( $meta_file );
					$this->debug_message( "trimmed file down to $meta_file" );
				} else {
					// This happens if there is object versioning, or thumbs in a sub-folder.
					$this->debug_message( "could not splice $base_dir and $meta_file" );
					$base_dir = false;
				}
			}
			if ( $base_dir ) {
				$this->debug_message( "building url from $base_dir and $meta_file" );
				$url = \trailingslashit( $base_dir ) . $meta_file;
			} else {
				$this->debug_message( 'splicing disabled previously, or empty base_dir, so retrieving URL via wp_get_attachment_url()' );
				$url = \wp_get_attachment_url( $attachment_id );
			}
		}

		if ( ! $w_descriptor ) {
			$this->debug_message( 'using x descriptors instead of w' );
			$multipliers = \array_filter( $multipliers, '\is_int' );
		}

		if (
			/** Short-circuit via exactdn_srcset_multipliers filter. */
			\is_array( $multipliers ) &&
			/** This isn't an SVG image. */
			'.svg' !== \strtolower( \substr( $image_meta['file'], -4 ) ) &&
			/** This filter is already documented in class-exactdn.php */
			! \apply_filters( 'exactdn_skip_image', false, $url, null ) &&
			/** The original url is valid/allowed. */
			$this->validate_image_url( $url ) &&
			/** Verify basic meta is intact. */
			isset( $image_meta['width'] ) && isset( $image_meta['height'] ) && isset( $image_meta['file'] ) &&
			/** Verify we have the requested width/height. */
			isset( $size_array[0] ) && isset( $size_array[1] )
		) {

			$fullwidth  = $image_meta['width'];
			$fullheight = $image_meta['height'];
			$reqwidth   = $size_array[0];
			$reqheight  = $size_array[1];
			$this->debug_message( "filling additional sizes with requested w $reqwidth h $reqheight full w $fullwidth full h $fullheight" );

			$constrained_size = \wp_constrain_dimensions( $fullwidth, $fullheight, $reqwidth );
			$expected_size    = array( $reqwidth, $reqheight );

			$this->debug_message( 'constrained w: ' . $constrained_size[0] );
			$this->debug_message( 'constrained h: ' . $constrained_size[1] );
			if ( \abs( $constrained_size[0] - $expected_size[0] ) <= 1 && \abs( $constrained_size[1] - $expected_size[1] ) <= 1 ) {
				$this->debug_message( 'soft cropping' );
				$crop = 'soft';
				$base = $this->get_content_width(); // Provide a default width if none set by the theme.
			} else {
				$this->debug_message( 'hard cropping' );
				$crop = 'hard';
				$base = $reqwidth;
			}
			$this->debug_message( "base width: $base" );

			$currentwidths = \array_keys( $sources );
			$newsources    = null;

			foreach ( $multipliers as $multiplier ) {

				$newwidth = \intval( $base * $multiplier );
				if ( 1920 === (int) $multiplier ) {
					$newwidth = 1920;
					if ( ! $w_descriptor || 1920 >= $reqwidth || 'soft' !== $crop ) {
						continue;
					}
				}
				if ( $newwidth < 50 ) {
					continue;
				}
				foreach ( $currentwidths as $currentwidth ) {
					// If a new width would be within 50 pixels of an existing one or larger than the full size image, skip.
					if ( \abs( $currentwidth - $newwidth ) < 50 || ( $newwidth > $fullwidth ) ) {
						continue 2; // Back to the foreach ( $multipliers as $multiplier ).
					}
				} // foreach ( $currentwidths as $currentwidth ){

				if ( 1 === $multiplier && \abs( $newwidth - $fullwidth ) < 5 ) {
					$args = array();
				} elseif ( 'soft' === $crop ) {
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
					'descriptor' => ( $w_descriptor ? 'w' : 'x' ),
					'value'      => ( $w_descriptor ? $newwidth : $multiplier ),
				);

				$currentwidths[] = $newwidth;
			} // foreach ( $multipliers as $multiplier )

			if ( \is_array( $newsources ) ) {
				$sources = \array_replace( $sources, $newsources );
			}
		} // if ( isset( $image_meta['width'] ) && isset( $image_meta['file'] ) )
		$elapsed_time = \microtime( true ) - $started;
		$this->debug_message( "parsing srcset took $elapsed_time seconds" );
		/* $this->debug_message( print_r( $sources, true ) ); */
		$this->elapsed_time += \microtime( true ) - $started;
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
		$started = \microtime( true );
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! \doing_filter( 'the_content' ) ) {
			return $sizes;
		}
		if ( \apply_filters( 'exactdn_skip_page', false, $this->request_uri ) ) {
			return $sizes;
		}
		$content_width = $this->get_content_width();

		if ( ( \is_array( $size ) && $size[0] < $content_width ) ) {
			return $sizes;
		}

		$elapsed_time = \microtime( true ) - $started;
		$this->debug_message( "parsing sizes took $elapsed_time seconds" );
		$this->elapsed_time += \microtime( true ) - $started;
		return \sprintf( '(max-width: %1$dpx) 100vw, %1$dpx', $content_width );
	}

	/**
	 * Creates an image `srcset` attribute based on the detected width.
	 *
	 * @param string $url The url of the image.
	 * @param int    $width Image width to use for calculations.
	 * @param bool   $zoom Whether to use zoom or w param.
	 * @param int    $filename_width The width derived from the filename, or false.
	 * @uses this::generate_url
	 * @return string A srcset attribute with ExactDN image urls and widths.
	 */
	public function generate_image_srcset( $url, $width, $zoom = false, $filename_width = false ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Don't foul up the admin side of things.
		if ( \is_admin() ) {
			return '';
		}

		if ( ! \is_numeric( $width ) ) {
			return '';
		}

		/**
		 * Filter the multiplier ExactDN uses to create new srcset items.
		 * Return false to short-circuit and bypass auto-generation.
		 *
		 * @param array|bool $multipliers Array of multipliers to use or false to bypass.
		 */
		$multipliers = \apply_filters( 'exactdn_srcset_multipliers', array( .2, .4, .6, .8, 1, 2, 3, 1920 ) );
		/**
		 * Filter the width ExactDN will use to create srcset attribute.
		 * Return a falsy value to short-circuit and bypass srcset fill.
		 *
		 * @param int|bool $width The max width for this $url, or false to bypass.
		 */
		$width = (int) \apply_filters( 'exactdn_srcset_fill_width', $width, $url );
		if ( ! $width ) {
			return '';
		}
		$srcset        = '';
		$currentwidths = array();

		if (
			/** Short-circuit via exactdn_srcset_multipliers filter. */
			\is_array( $multipliers )
			&& $width
			/** This filter is already documented in class-exactdn.php */
			&& ! \apply_filters( 'exactdn_skip_image', false, $url, null )
		) {
			$sources = null;

			foreach ( $multipliers as $multiplier ) {
				$newwidth = \intval( $width * $multiplier );
				if ( 1920 === (int) $multiplier ) {
					if ( $multiplier >= $width ) {
						continue;
					}
					$newwidth = 1920;
				}
				if ( $newwidth < 50 ) {
					continue;
				}
				foreach ( $currentwidths as $currentwidth ) {
					// If a new width would be within 50 pixels of an existing one or larger than the full size image, skip.
					if ( 1 !== $multiplier && \abs( $currentwidth - $newwidth ) < 50 ) {
						continue 2; // Back to the foreach ( $multipliers as $multiplier ).
					}
				} // foreach ( $currentwidths as $currentwidth ){
				if ( $filename_width && $newwidth > $filename_width ) {
					continue;
				}

				if ( 1 === $multiplier ) {
					$args = array();
				} elseif ( $zoom && $multiplier <= 10 ) {
					$args = array(
						'zoom' => $multiplier,
					);
				} else {
					$args = array(
						'w' => $newwidth,
					);
				}

				$sources[ $newwidth ] = array(
					'url'        => $this->generate_url( $url, $args ),
					'descriptor' => 'w',
					'value'      => $newwidth,
				);

				$currentwidths[] = $newwidth;
			}
		}
		if ( ! empty( $sources ) ) {
			foreach ( $sources as $source ) {
				$srcset .= \str_replace( ' ', '%20', $source['url'] ) . ' ' . $source['value'] . $source['descriptor'] . ', ';
			}
		}
		/* $this->debug_message( print_r( $sources, true ) ); */
		return \rtrim( $srcset, ', ' );
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
	public function maybe_smart_crop( $args, $attachment_id, $meta = false ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( empty( $args ) ) {
			return $args;
		}
		if ( ! empty( $args['crop'] ) ) {
			$this->debug_message( 'already cropped' );
			return $args;
		}
		// Doing something other than a hard crop, or we don't know what the ID is.
		if ( empty( $args['resize'] ) || empty( $attachment_id ) ) {
			$this->debug_message( 'not resizing, so no custom crop' );
			return $args;
		}
		// TST is not active.
		if ( ! \defined( 'TST_VERSION' ) && ! \defined( 'THEIA_SMART_THUMBNAILS_VERSION' ) ) {
			$this->debug_message( 'no TST plugin' );
			return $args;
		}
		if ( ! $meta || ! \is_array( $meta ) || empty( $meta['sizes'] ) ) {
			$meta = \wp_get_attachment_metadata( $attachment_id );
			if ( ! \is_array( $meta ) || empty( $meta ) ) {
				$this->debug_message( 'unusable meta retrieved' );
				return $args;
			}
		}
		if ( ! empty( $meta['tst_thumbnail_version'] ) ) {
			$args['theia_smart_thumbnails_file_version'] = (int) $meta['tst_thumbnail_version'];
			return $args;
		}
		$this->debug_message( 'no tst version in meta' );
		return $args;
	}

	/**
	 * Check if this is a REST API request that we should handle (or not).
	 *
	 * @param WP_HTTP_Response $response Result to send to the client. Usually a WP_REST_Response.
	 * @param WP_REST_Server   $handler  ResponseHandler instance (usually WP_REST_Server).
	 * @param WP_REST_Request  $request  Request used to generate the response.
	 * @return WP_HTTP_Response The result, unaltered.
	 */
	public function parse_restapi_maybe( $response, $handler, $request ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! \is_a( $request, '\WP_REST_Request' ) ) {
			$this->debug_message( 'oddball REST request or handler' );
			return $response; // Something isn't right, bail.
		}
		if ( \is_wp_error( $response ) ) {
			return $response;
		}
		$route = $request->get_route();
		if ( \is_string( $route ) ) {
			$this->debug_message( "current REST route is $route" );
		}
		if ( \is_string( $route ) && false !== \strpos( $route, 'wp/v2/media' ) && 'edit' === $request->get_param( 'context' ) ) {
			$this->debug_message( 'REST API media endpoint from post editor' );
			// We don't want ExactDN urls anywhere near the editor, so disable everything we can.
			\add_filter( 'exactdn_override_image_downsize', '__return_true', PHP_INT_MAX );
			\add_filter( 'exactdn_skip_image', '__return_true', PHP_INT_MAX ); // This skips existing srcset indices.
			\add_filter( 'exactdn_srcset_multipliers', '__return_false', PHP_INT_MAX ); // This one skips the additional multipliers.
		} elseif ( \is_string( $route ) && false !== \strpos( $route, 'wp/v2/media' ) && 'view' === $request->get_param( 'context' ) ) {
			$this->debug_message( 'REST API media endpoint, could be editor, we may never know...' );
			// We don't want ExactDN urls anywhere near the editor, so disable everything we can.
			\add_filter( 'exactdn_override_image_downsize', '__return_true', PHP_INT_MAX );
			\add_filter( 'exactdn_skip_image', '__return_true', PHP_INT_MAX ); // This skips existing srcset indices.
			\add_filter( 'exactdn_srcset_multipliers', '__return_false', PHP_INT_MAX ); // This one skips the additional multipliers.
		} elseif ( \is_string( $route ) && false !== \strpos( $route, 'wp/v2/media' ) && ! empty( $request['post'] ) && ! empty( $request->get_file_params() ) ) {
			$this->debug_message( 'REST API media endpoint (new upload)' );
			// We don't want ExactDN urls anywhere near the editor, so disable everything we can.
			\add_filter( 'exactdn_override_image_downsize', '__return_true', PHP_INT_MAX );
			\add_filter( 'exactdn_skip_image', '__return_true', PHP_INT_MAX ); // This skips existing srcset indices.
			\add_filter( 'exactdn_srcset_multipliers', '__return_false', PHP_INT_MAX ); // This one skips the additional multipliers.
		} elseif ( \is_string( $route ) && false !== \strpos( $route, '/ToolsetBlocks/' ) ) {
			$this->debug_message( 'REST API media endpoint (ToolsetBlocks)' );
			// We don't want ExactDN urls anywhere near the editor, so disable everything we can.
			add_filter( 'exactdn_override_image_downsize', '__return_true', PHP_INT_MAX );
			add_filter( 'exactdn_skip_image', '__return_true', PHP_INT_MAX ); // This skips existing srcset indices.
			add_filter( 'exactdn_srcset_multipliers', '__return_false', PHP_INT_MAX ); // This one skips the additional multipliers.
		} elseif ( \is_string( $route ) && false !== \strpos( $route, '/toolset-dynamic-sources/' ) ) {
			$this->debug_message( 'REST API media endpoint (toolset-dynamic-sources)' );
			// We don't want ExactDN urls anywhere near the editor, so disable everything we can.
			\add_filter( 'exactdn_override_image_downsize', '__return_true', PHP_INT_MAX );
			\add_filter( 'exactdn_skip_image', '__return_true', PHP_INT_MAX ); // This skips existing srcset indices.
			\add_filter( 'exactdn_srcset_multipliers', '__return_false', PHP_INT_MAX ); // This one skips the additional multipliers.
		}
		return $response;
	}

	/**
	 * Make sure the image domain is on the list of approved domains.
	 *
	 * @param string $domain The hostname to validate.
	 * @return bool True if the hostname is allowed, false otherwise.
	 */
	public function allow_image_domain( $domain ) {
		$domain = \trim( $domain );
		foreach ( $this->allowed_domains as $allowed ) {
			$allowed = \trim( $allowed );
			if ( $domain === $allowed ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Make sure the asset domain is on the list of approved domains.
	 *
	 * @param string $domain The hostname to validate.
	 * @return bool True if the hostname is allowed, false otherwise.
	 */
	public function allow_asset_domain( $domain ) {
		if ( empty( $this->asset_domains ) ) {
			return false;
		}
		$domain          = \trim( $domain );
		$allowed_domains = \explode( ',', $this->asset_domains );
		foreach ( $allowed_domains as $allowed ) {
			$allowed = \trim( $allowed );
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
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! \is_string( $url ) ) {
			$this->debug_message( 'cannot validate uri when variable is not a string' );
			return false;
		}
		if ( false !== \strpos( $url, 'data:image/' ) ) {
			$this->debug_message( "could not parse data uri: $url" );
			return false;
		}
		$parsed_url = $this->parse_url( $url );
		if ( ! $parsed_url ) {
			$this->debug_message( "could not parse: $url" );
			return false;
		}

		// Parse URL and ensure needed keys exist, since the array returned by `parse_url` only includes the URL components it finds.
		$url_info = \wp_parse_args(
			$parsed_url,
			array(
				'scheme' => null,
				'host'   => null,
				'port'   => null,
				'path'   => null,
			)
		);

		// Bail if scheme isn't http or port is set that isn't port 80.
		if (
			( 'http' !== $url_info['scheme'] || ( 80 !== (int) $url_info['port'] && ! \is_null( $url_info['port'] ) ) ) &&
			/**
			 * Tells ExactDN to ignore images that are served via HTTPS.
			 *
			 * @param bool $reject_https Should ExactDN ignore images using the HTTPS scheme. Default to false.
			 */
			\apply_filters( 'exactdn_reject_https', false )
		) {
			$this->debug_message( 'rejected https via filter' );
			return false;
		}

		// Bail if no host is found.
		if ( \is_null( $url_info['host'] ) ) {
			$this->debug_message( 'null host' );
			return false;
		}

		// Bail if the image already went through ExactDN.
		if ( ! $exactdn_is_valid && $this->exactdn_domain === $url_info['host'] ) {
			$this->debug_message( 'exactdn image' );
			return false;
		}

		// Bail if the image already went through Photon to avoid conflicts.
		if ( \preg_match( '#^i[\d]{1}.wp.com$#i', $url_info['host'] ) ) {
			$this->debug_message( 'photon/wp.com image' );
			return false;
		}

		// Bail if no path is found.
		if ( \is_null( $url_info['path'] ) ) {
			$this->debug_message( 'null path' );
			return false;
		}

		// Ensure image extension is acceptable, unless it's a dynamic NextGEN image.
		if ( ! \in_array( \strtolower( \pathinfo( $url_info['path'], PATHINFO_EXTENSION ) ), $this->extensions, true ) && false === \strpos( $url_info['path'], 'nextgen-image/' ) ) {
			$this->debug_message( 'invalid extension' );
			return false;
		}

		// Make sure this is an allowed image domain/hostname for ExactDN on this site.
		if ( ! $this->allow_image_domain( $url_info['host'] ) ) {
			$this->debug_message( 'invalid host for ExactDN' );
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
		return \apply_filters( 'exactdn_validate_image_url', true, $url, $parsed_url );
	}

	/**
	 * Checks if the file exists before it passes the file to ExactDN.
	 *
	 * @param string $src The image URL.
	 * @return string The possibly altered URL without dimensions.
	 **/
	protected function strip_image_dimensions_maybe( $src ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$stripped_src = $src;

		// Build URL, first removing WP's resized string so we pass the original image to ExactDN.
		if ( \preg_match( '#(-\d+x\d+)\.(' . \implode( '|', $this->extensions ) . '){1}(?:\?.+)?$#i', $src, $src_parts ) ) {
			$stripped_src = \str_replace( $src_parts[1], '', $src );
			$scaled_src   = \str_replace( $src_parts[1], '-scaled', $src );

			$file = false;
			if ( $this->allowed_urls && $this->allowed_domains ) {
				$file = $this->cdn_to_local( $src );
			}
			if ( ! $file ) {
				$file = $this->url_to_path_exists( $src );
			}
			if ( $file ) {
				// Extracts the file path to the image minus the base url.
				$file_path   = \str_replace( $src_parts[1], '', $file );
				$scaled_path = \str_replace( $src_parts[1], '-scaled', $file );

				if ( $this->is_file( $file_path ) ) {
					$src = $stripped_src;
					$this->debug_message( 'stripped dims to original' );
				} elseif ( $this->is_file( $scaled_path ) ) {
					$src = $scaled_src;
					$this->debug_message( 'stripped dims to scaled' );
				}
			}
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
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( \is_null( self::$image_sizes ) ) {
			global $_wp_additional_image_sizes;

			// Populate an array matching the data structure of $_wp_additional_image_sizes so we have a consistent structure for image sizes.
			$images = array(
				'thumb'  => array(
					'width'  => \intval( get_option( 'thumbnail_size_w' ) ),
					'height' => \intval( get_option( 'thumbnail_size_h' ) ),
					'crop'   => (bool) get_option( 'thumbnail_crop' ),
				),
				'medium' => array(
					'width'  => \intval( get_option( 'medium_size_w' ) ),
					'height' => \intval( get_option( 'medium_size_h' ) ),
					'crop'   => false,
				),
				'large'  => array(
					'width'  => \intval( get_option( 'large_size_w' ) ),
					'height' => \intval( get_option( 'large_size_h' ) ),
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
			if ( \is_array( $_wp_additional_image_sizes ) && ! empty( $_wp_additional_image_sizes ) ) {
				self::$image_sizes = array_merge( $images, $_wp_additional_image_sizes );
			} else {
				self::$image_sizes = $images;
			}
		}

		return \is_array( self::$image_sizes ) ? self::$image_sizes : array();
	}

	/**
	 * Handle image urls within the NextGEN pro lightbox displays.
	 *
	 * @param array $images An array of NextGEN images and associated attributes.
	 * @return array The ExactDNified array of images.
	 */
	public function ngg_pro_lightbox_images_queue( $images ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( $this->is_iterable( $images ) ) {
			foreach ( $images as $index => $image ) {
				if ( ! empty( $image['image'] ) && $this->validate_image_url( $image['image'] ) ) {
					$images[ $index ]['image'] = $this->generate_url( $image['image'] );
				}
				if ( ! empty( $image['thumb'] ) && $this->validate_image_url( $image['thumb'] ) ) {
					$images[ $index ]['thumb'] = $this->generate_url( $image['thumb'] );
				}
				if ( ! empty( $image['full_image'] ) && $this->validate_image_url( $image['full_image'] ) ) {
					$images[ $index ]['full_image'] = $this->generate_url( $image['full_image'] );
				}
				if ( $this->is_iterable( $image['srcsets'] ) ) {
					foreach ( $image['srcsets'] as $size => $srcset ) {
						if ( $this->validate_image_url( $srcset ) ) {
							$images[ $index ]['srcsets'][ $size ] = $this->generate_url( $srcset );
						}
					}
				}
				if ( $this->is_iterable( $image['full_srcsets'] ) ) {
					foreach ( $image['full_srcsets'] as $size => $srcset ) {
						if ( $this->validate_image_url( $srcset ) ) {
							$images[ $index ]['full_srcsets'][ $size ] = $this->generate_url( $srcset );
						}
					}
				}
			}
		}
		return $images;
	}

	/**
	 * Handle image urls within the Envira pro displays.
	 *
	 * @param array $image An Envira gallery image with associated attributes.
	 * @return array The ExactDNified array of data.
	 */
	public function envira_gallery_output_item_data( $image ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( $this->is_iterable( $image ) ) {
			foreach ( $image as $index => $attr ) {
				if ( \is_string( $attr ) && 0 === \strpos( $attr, 'http' ) && $this->validate_image_url( $attr ) ) {
					$image[ $index ] = $this->generate_url( $attr );
				}
			}
			if ( ! empty( $image['opts']['thumb'] ) && $this->validate_image_url( $image['opts']['thumb'] ) ) {
				$image['opts']['thumb'] = $this->generate_url( $image['opts']['thumb'] );
			}
		}
		return $image;
	}

	/**
	 * Parse template data from FacetWP that will be included in JSON response.
	 * https://facetwp.com/documentation/developers/output/facetwp_render_output/
	 *
	 * @param array $output The full array of FacetWP data.
	 * @return array The FacetWP data with Easy IO URLs.
	 */
	public function filter_facetwp_json_output( $output ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( empty( $output['template'] ) || ! \is_string( $output['template'] ) ) {
			return $output;
		}
		$this->filtering_the_page = true;

		$template = $this->filter_the_content( $output['template'] );
		if ( $template ) {
			$this->debug_message( 'template data modified' );
			$output['template'] = $template;
		}

		return $output;
	}

	/**
	 * Handle direct image URLs within Plugins.
	 *
	 * @param string $image A URL for an image.
	 * @return string The ExactDNified image URL.
	 */
	public function plugin_get_image_url( $image ) {
		// Don't foul up the admin side of things, unless a plugin wants to.
		if ( \is_admin() &&
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
			false === \apply_filters( 'exactdn_admin_allow_plugin_url', false, $image )
		) {
			return $image;
		}
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( $this->validate_image_url( $image ) ) {
			return $this->generate_url( $image );
		}
		return $image;
	}

	/**
	 * Handler for BuddyBoss media image URLs.
	 *
	 * @param string $attachment_url A URL for an image.
	 * @param int    $media_id BP/BB media ID.
	 * @param int    $attachment_id Core WP attachment ID.
	 * @param string $size Size (name) of the media.
	 * @param bool   $symlink Whether to use symlink or not.
	 * @return string The (possibly) ExactDNified image URL.
	 */
	public function bp_media_get_preview_image_url( $attachment_url, $media_id, $attachment_id, $size, $symlink ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( $media_id && ! $symlink ) {
			$media    = new \BP_Media( $media_id );
			$metadata = \wp_get_attachment_metadata( $media->attachment_id );
			if ( $size && ! empty( $metadata ) && $this->is_iterable( $metadata['sizes'] ) && isset( $metadata['sizes'][ $size ] ) ) {
				$attachment_url = \wp_get_attachment_image_url( $media->attachment_id, $size );
			} else {
				$attachment_url = \wp_get_attachment_url( $media->attachment_id );
			}
			if ( $this->validate_image_url( $attachment_url ) ) {
				return $this->generate_url( $attachment_url );
			}
		}
		return $attachment_url;
	}

	/**
	 * Handler for BuddyBoss video URLs.
	 *
	 * @param string $attachment_url A URL for an image.
	 * @param int    $video_id BB video ID.
	 * @param string $size Size (name) of the media.
	 * @param int    $attachment_id Attachment ID.
	 * @param bool   $symlink Whether to display symlink or not.
	 * @return string The ExactDNified image URL.
	 */
	public function bb_video_get_thumb_url( $attachment_url, $video_id, $size, $attachment_id, $symlink ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( $attachment_id && ! $symlink ) {
			$metadata = \wp_get_attachment_metadata( $attachment_id );
			if ( $size && ! empty( $metadata ) && $this->is_iterable( $metadata['sizes'] ) && isset( $metadata['sizes'][ $size ] ) ) {
				$attachment_url = \wp_get_attachment_image_url( $attachment_id, $size );
			} else {
				$attachment_url = \wp_get_attachment_url( $attachment_id );
			}
			if ( $this->validate_image_url( $attachment_url ) ) {
				return $this->generate_url( $attachment_url );
			}
		}
		return $attachment_url;
	}

	/**
	 * Handler for BuddyBoss media image URLs.
	 *
	 * @param string $attachment_url A URL for an image.
	 * @param int    $document_id BP/BB document ID.
	 * @param string $extension File extension of the document.
	 * @param string $size Size (name) of the document preview.
	 * @param int    $attachment_id Core WP attachment ID for the preview image.
	 * @param bool   $symlink Whether to use symlink or not.
	 * @return string The (possibly) ExactDNified image URL.
	 */
	public function bp_document_get_preview_url( $attachment_url, $document_id, $extension, $size, $attachment_id, $symlink ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if (
			$attachment_id && ! $symlink &&
			\function_exists( '\bp_get_document_preview_doc_extensions' ) && \in_array( $extension, \bp_get_document_preview_doc_extensions(), true )
		) {
			$this->debug_message( "$extension is allowed for $document_id" );
			$metadata = \wp_get_attachment_metadata( $attachment_id );
			if ( $size && ! empty( $metadata ) && $this->is_iterable( $metadata['sizes'] ) && isset( $metadata['sizes'][ $size ] ) ) {
				$attachment_url = \wp_get_attachment_image_url( $attachment_id, $size );
			} else {
				$attachment_url = \wp_get_attachment_image_url( $attachment_id, 'full' );
			}
			if ( ! $attachment_url ) {
				$this->debug_message( 'attachment URL does not yet exist' );
				if ( 'pdf' === \strtolower( $extension ) && \function_exists( '\bp_document_generate_document_previews' ) ) {
					\bp_document_generate_document_previews( $attachment_id );
				} elseif ( \function_exists( '\bb_document_regenerate_attachment_thumbnails' ) ) {
					\bb_document_regenerate_attachment_thumbnails( $attachment_id );
				}
				$metadata = \wp_get_attachment_metadata( $attachment_id );
				if ( $size && ! empty( $metadata ) && $this->is_iterable( $metadata['sizes'] ) && isset( $metadata['sizes'][ $size ] ) ) {
					$attachment_url = \wp_get_attachment_image_url( $attachment_id, $size );
				} else {
					$attachment_url = \wp_get_attachment_image_url( $attachment_id, 'full' );
				}
			}
			if ( $this->validate_image_url( $attachment_url ) ) {
				return $this->generate_url( $attachment_url );
			}
		}
		if (
			$attachment_id && ! $symlink &&
			\function_exists( '\bp_get_document_preview_music_extensions' ) && \in_array( $extension, \bp_get_document_preview_music_extensions(), true )
		) {
			$attachment_url = \wp_get_attachment_url( $attachment_id );
			if ( $this->validate_image_url( $attachment_url ) ) {
				return $this->generate_url( $attachment_url );
			}
		}
		return $attachment_url;
	}

	/**
	 * Handler for BuddyBoss video URLs.
	 *
	 * @param string $attachment_url A URL for an image.
	 * @param int    $video_id BB video ID.
	 * @param int    $attachment_id Attachment ID.
	 * @param bool   $symlink Whether to display symlink or not.
	 * @return string The ExactDNified image URL.
	 */
	public function bb_video_get_symlink( $attachment_url, $video_id, $attachment_id, $symlink ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( $attachment_id && ! $symlink ) {
			$attachment_url = \wp_get_attachment_url( $attachment_id );
			return $this->parse_enqueue( $attachment_url );
		}
		return $attachment_url;
	}

	/**
	 * Disable use of symlinks/media protection in BuddyBoss.
	 *
	 * @param bool $do_symlinks Defaults to true.
	 * @return bool False to disable symlinks.
	 */
	public function buddyboss_do_symlink( $do_symlinks ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		return false;
	}

	/**
	 * Allow access to BuddyBoss media on Easy IO.
	 *
	 * @param array $directories List of directories.
	 * @param array $media_ids Uploaded media IDs.
	 *
	 * @return array Modified list of diretories.
	 */
	public function buddyboss_media_directory_allow_access( $directories, $media_ids ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! empty( $directories ) ) {
			$this->debug_message( 'checking directory access' );
			foreach ( $media_ids as $id => $v ) {
				$this->debug_message( "checking $id" );
				$directories[] = $id;
			}
		}
		return $directories;
	}

	/**
	 * Handle image urls within Slider Revolution 7 objects.
	 *
	 * @param array|object $slider A Revolution Slider object, or an array prior to JSON conversion.
	 * @return array The ExactDNified slider object/array.
	 */
	public function sr7_slider_object( $slider ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->debug_message( 'RS7 object/array incoming:' );
		/* $this->debug_message( print_r( $slider, true ) ); */
		if ( ! is_array( $slider ) ) {
			if ( is_object( $slider ) ) {
				if ( ! empty( $slider->params['layout']['bg']['image'] ) && is_string( $slider->params['layout']['bg']['image'] ) && $this->validate_image_url( $slider->params['layout']['bg']['image'] ) ) {
					$slider->params['layout']['bg']['image'] = $this->generate_url( $slider->params['layout']['bg']['image'] );
				}
				if ( ! empty( $slider->params['bg']['image']['src'] ) && is_string( $slider->params['bg']['image']['src'] ) && $this->validate_image_url( $slider->params['bg']['image']['src'] ) ) {
					$slider->params['bg']['image']['src'] = $this->generate_url( $slider->params['bg']['image']['src'] );
				}
				// This one is disabled, so that we are only altering the overall slider background for now.
				if ( false && ! empty( $slider->params['imgs'] ) && $this->is_iterable( $slider->params['imgs'] ) ) {
					foreach ( $slider->params['imgs'] as $img_index => $slider_settings_img ) {
						if ( \is_string( $slider_settings_img ) && $this->validate_image_url( $slider_settings_img ) ) {
							$slider->params['imgs'][ $img_index ] = $this->generate_url( $slider_settings_img );
							continue;
						}
						if ( ! empty( $slider_settings_img['src'] ) && $this->validate_image_url( $slider_settings_img['src'] ) ) {
							$slider->params['imgs'][ $img_index ]['src'] = $this->generate_url( $slider_settings_img['src'] );
						}
					}
				}
			}
			return $slider;
		}
		if ( ! empty( $slider['settings']['bg']['image']['src'] ) ) {
			if ( $this->validate_image_url( $slider['settings']['bg']['image']['src'] ) ) {
				$slider['settings']['bg']['image']['src'] = $this->generate_url( $slider['settings']['bg']['image']['src'] );
			}
		}
		if ( ! empty( $slider['settings']['imgs'] ) && $this->is_iterable( $slider['settings']['imgs'] ) ) {
			foreach ( $slider['settings']['imgs'] as $img_index => $slider_settings_img ) {
				if ( \is_string( $slider_settings_img ) && $this->validate_image_url( $slider_settings_img ) ) {
					$slider['settings']['imgs'][ $img_index ] = $this->generate_url( $slider_settings_img );
					continue;
				}
				if ( ! empty( $slider_settings_img['src'] ) && $this->validate_image_url( $slider_settings_img['src'] ) ) {
					$slider['settings']['imgs'][ $img_index ]['src'] = $this->generate_url( $slider_settings_img['src'] );
				}
			}
		}
		if ( ! empty( $slider['slides'] ) && $this->is_iterable( $slider['slides'] ) ) {
			foreach ( $slider['slides'] as $slide_index => $slide ) {
				$slider['slides'][ $slide_index ] = $this->sr7_slider_slide( $slide );
			}
		}
		if ( ! empty( $slider['static_slide'] ) ) {
			$slider['static_slide'] = $this->sr7_slider_slide( $slider['static_slide'] );
		}
		return $slider;
	}

	/**
	 * Handle image urls within Slider Revolution 7 slides.
	 *
	 * @param array $slide A Revolution Slider slide array prior to JSON conversion.
	 * @return array The ExactDNified slide array.
	 */
	public function sr7_slider_slide( $slide ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( is_array( $slide ) ) {
			if ( $this->is_iterable( $slide['layers'] ) ) {
				foreach ( $slide['layers'] as $layer_index => $slide_layer ) {
					if ( ! empty( $slide_layer['bg']['image']['src'] ) && $this->validate_image_url( $slide_layer['bg']['image']['src'] ) ) {
						$slide['layers'][ $layer_index ]['bg']['image']['src'] = $this->generate_url( $slide_layer['bg']['image']['src'] );
					}
					if ( ! empty( $slide_layer['bg']['video']['poster']['src'] ) && $this->validate_image_url( $slide_layer['bg']['video']['poster']['src'] ) ) {
						$slide['layers'][ $layer_index ]['bg']['video']['poster']['src'] = $this->generate_url( $slide_layer['bg']['video']['poster']['src'] );
					}
					if ( ! empty( $slide_layer['content']['src'] ) && $this->validate_image_url( $slide_layer['content']['src'] ) ) {
						$slide['layers'][ $layer_index ]['content']['src'] = $this->generate_url( $slide_layer['content']['src'] );
					}
					if ( ! empty( $slide_layer['idle']['backgroundImage'] ) && $this->validate_image_url( $slide_layer['idle']['backgroundImage'] ) ) {
						$slide['layers'][ $layer_index ]['idle']['backgroundImage'] = $this->generate_url( $slide_layer['idle']['backgroundImage'] );
					}
					if ( ! empty( $slide_layer['media']['posterUrl'] ) && $this->validate_image_url( $slide_layer['media']['posterUrl'] ) ) {
						$slide['layers'][ $layer_index ]['media']['posterUrl'] = $this->generate_url( $slide_layer['media']['posterUrl'] );
					}
					if ( ! empty( $slide_layer['media']['imageUrl'] ) && $this->validate_image_url( $slide_layer['media']['imageUrl'] ) ) {
						$slide['layers'][ $layer_index ]['media']['imageUrl'] = $this->generate_url( $slide_layer['media']['imageUrl'] );
					}
					if ( ! empty( $slide_layer['svg']['source'] ) && $this->validate_image_url( $slide_layer['svg']['source'] ) ) {
						$slide['layers'][ $layer_index ]['svg']['source'] = $this->generate_url( $slide_layer['svg']['source'] );
					}
				}
			}
			if ( ! empty( $slide['params']['thumb']['customThumbSrc'] ) && $this->validate_image_url( $slide['params']['thumb']['customThumbSrc'] ) ) {
				$slide['params']['thumb']['customThumbSrc'] = $this->generate_url( $slide['params']['thumb']['customThumbSrc'] );
			}
			if ( ! empty( $slide['params']['bg']['image'] ) && $this->validate_image_url( $slide['params']['bg']['image'] ) ) {
				$slide['params']['bg']['image'] = $this->generate_url( $slide['params']['bg']['image'] );
			}
			if ( ! empty( $slide['slide']['thumb']['src'] ) && $this->validate_image_url( $slide['slide']['thumb']['src'] ) ) {
				$slide['slide']['thumb']['src'] = $this->generate_url( $slide['slide']['thumb']['src'] );
			}
		}
		return $slide;
	}

	/**
	 * Handle images in Spotlight's Instagram response/endpoint.
	 *
	 * @param array $data The Instagram item data.
	 * @return array The Instagram data with ExactDNified image urls.
	 */
	public function spotlight_instagram_response( $data ) {
		if ( \is_array( $data ) && ! empty( $data['thumbnails']['s'] ) ) {
			if ( $this->validate_image_url( $data['thumbnails']['s'] ) ) {
				$data['thumbnails']['s'] = $this->generate_url( $data['thumbnails']['s'] );
			}
		}
		if ( \is_array( $data ) && ! empty( $data['thumbnails']['m'] ) ) {
			if ( $this->validate_image_url( $data['thumbnails']['m'] ) ) {
				$data['thumbnails']['m'] = $this->generate_url( $data['thumbnails']['m'] );
			}
		}
		return $data;
	}

	/**
	 * Handle images in legacy WooCommerce API endpoints.
	 *
	 * @param array $product_data The product information that will be returned via the API.
	 * @return array The product information with ExactDNified image urls.
	 */
	public function woocommerce_api_product_response( $product_data ) {
		if ( \is_array( $product_data ) && ! empty( $product_data['featured_src'] ) ) {
			if ( $this->validate_image_url( $product_data['featured_src'] ) ) {
				$product_data['featured_src'] = $this->generate_url( $product_data['featured_src'] );
			}
		}
		return $product_data;
	}

	/**
	 * Suppress query args for certain files, typically for placholder images.
	 *
	 * @param array|string $args Array of ExactDN arguments.
	 * @param string       $image_url Image URL.
	 * @param string|null  $scheme Image scheme. Default to null.
	 * @return array Empty if it matches our search, otherwise just $args untouched.
	 */
	public function exactdn_remove_args( $args, $image_url, $scheme ) {
		if ( \strpos( $image_url, 'ewww/lazy/placeholder' ) ) {
			return array();
		}
		if ( \strpos( $image_url, 'easyio/lazy/placeholder' ) ) {
			return array();
		}
		if ( \strpos( $image_url, 'swis/lazy/placeholder' ) ) {
			return array();
		}
		if ( \strpos( $image_url, '/dummy.png' ) ) {
			return array();
		}
		if ( \strpos( $image_url, '/lazy.png' ) ) {
			return array();
		}
		if ( \strpos( $image_url, 'lazy_placeholder.gif' ) ) {
			return array();
		}
		if ( \strpos( $image_url, '/assets/images/' ) ) {
			return array();
		}
		if ( \strpos( $image_url, 'LayerSlider/static/img' ) ) {
			return array();
		}
		if ( \strpos( $image_url, 'lazy-load/images/' ) ) {
			return array();
		}
		if ( \strpos( $image_url, 'public/images/spacer.' ) ) {
			return array();
		}
		if ( \strpos( $image_url, '/images/default/blank.gif' ) ) {
			return array();
		}
		if ( '.svg' === \substr( $image_url, -4 ) ) {
			return array();
		}
		return $args;
	}

	/**
	 * Exclude pages from being processed for things like page builders.
	 *
	 * @since 6.1.9
	 *
	 * @param boolean $skip Whether ExactDN should skip processing.
	 * @param string  $uri The URI of the page (no domain or scheme included).
	 * @return boolean True to skip the page, unchanged otherwise.
	 */
	public function skip_page( $skip = false, $uri = '' ) {
		if ( $this->is_iterable( $this->user_page_exclusions ) ) {
			foreach ( $this->user_page_exclusions as $page_exclusion ) {
				if ( '/' === $page_exclusion && '/' === $uri ) {
					return true;
				} elseif ( '/' === $page_exclusion ) {
					continue;
				}
				if ( false !== \strpos( $uri, $page_exclusion ) ) {
					return true;
				}
			}
		}
		if ( false !== \strpos( $uri, 'bricks=run' ) ) {
			return true;
		}
		if ( false !== \strpos( $uri, '?brizy-edit' ) ) {
			return true;
		}
		if ( false !== \strpos( $uri, '?brizy_media' ) ) {
			return true;
		}
		if ( false !== \strpos( $uri, '&builder=true' ) ) {
			return true;
		}
		if ( false !== \strpos( $uri, 'cornerstone=' ) || false !== \strpos( $uri, 'cornerstone-endpoint' ) || false !== \strpos( $uri, 'cornerstone/edit/' ) ) {
			return true;
		}
		if ( \did_action( 'cs_element_rendering' ) || \did_action( 'cornerstone_before_boot_app' ) || \apply_filters( 'cs_is_preview_render', false ) ) {
			return true;
		}
		if ( false !== \strpos( $uri, 'ct_builder=' ) ) {
			return true;
		}
		if ( false !== \strpos( $uri, 'ct_render_shortcode=' ) || false !== strpos( $uri, 'action=oxy_render' ) ) {
			return true;
		}
		if ( false !== \strpos( $uri, 'elementor-preview=' ) ) {
			return true;
		}
		if ( false !== \strpos( $uri, 'et_fb=' ) ) {
			return true;
		}
		if ( false !== \strpos( $uri, 'fb-edit=' ) ) {
			return true;
		}
		if ( false !== \strpos( $uri, '?fl_builder' ) ) {
			return true;
		}
		if ( false !== \strpos( $uri, 'is-editor-iframe=' ) ) {
			return true;
		}
		if ( false !== \strpos( $uri, 'tatsu=' ) ) {
			return true;
		}
		if ( false !== \strpos( $uri, 'tve=true' ) ) {
			return true;
		}
		return $skip;
	}

	/**
	 * Exclude images and other resources from being processed based on user specified list.
	 *
	 * @since 4.6.0
	 *
	 * @param boolean $skip Whether ExactDN should skip processing.
	 * @param string  $url Resource URL.
	 * @return boolean True to skip the resource, unchanged otherwise.
	 */
	public function exactdn_skip_user_exclusions( $skip, $url ) {
		if ( $this->user_exclusions ) {
			foreach ( $this->user_exclusions as $exclusion ) {
				if ( false !== \strpos( $url, $exclusion ) ) {
					$this->debug_message( "user excluded $url via $exclusion" );
					return true;
				}
			}
		}
		return $skip;
	}

	/**
	 * Converts a local script/css url to use ExactDN.
	 *
	 * @param string $url URL to the resource being parsed.
	 * @return string The ExactDN version of the resource, if it was local.
	 */
	public function parse_enqueue( $url ) {
		if ( \is_admin() ) {
			return $url;
		}
		if ( \function_exists( '\affwp_is_affiliate_portal' ) && \affwp_is_affiliate_portal() ) {
			return $url;
		}
		if ( \apply_filters( 'exactdn_skip_page', false, $this->request_uri ) ) {
			return $url;
		}
		if ( \did_action( 'cornerstone_boot_app' ) || \did_action( 'cs_before_preview_frame' ) ) {
			return $url;
		}
		if ( \did_action( 'cs_element_rendering' ) || \did_action( 'cornerstone_before_boot_app' ) || \apply_filters( 'cs_is_preview_render', false ) ) {
			return $url;
		}
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$parsed_url = $this->parse_url( $url );

		if ( false !== \strpos( $url, 'wp-admin/' ) ) {
			return $url;
		}
		if ( false !== \strpos( $url, 'xmlrpc.php' ) ) {
			return $url;
		}

		/**
		 * Allow specific URLs to avoid going through ExactDN.
		 *
		 * @param bool false Should the URL be returned as is, without going through ExactDN. Default to false.
		 * @param string $url Resource URL.
		 * @param array|string $args Array of ExactDN arguments.
		 * @param string|null $scheme URL scheme. Default to null.
		 */
		if ( true === \apply_filters( 'exactdn_skip_for_url', false, $url, array(), null ) ) {
			return $url;
		}

		// Unable to parse.
		if ( ! $parsed_url || ! \is_array( $parsed_url ) || empty( $parsed_url['host'] ) || empty( $parsed_url['path'] ) ) {
			$this->debug_message( 'src url no good' );
			return $url;
		}

		// No PHP files shall pass.
		if ( \preg_match( '/\.php$/', $parsed_url['path'] ) ) {
			return $url;
		}

		// Make sure we have a CDN domain to use.
		if ( empty( $this->exactdn_domain ) ) {
			$this->debug_message( 'no exactdn domain configured' );
			return $url;
		}

		if ( ( ! \function_exists( '\swis' ) || ! \swis()->settings->get_option( 'optimize_fonts' ) ) && false !== \strpos( $url, '//fonts.googleapis.com/css' ) ) {
			if ( \defined( 'EASYIO_REPLACE_GOOGLE_FONTS' ) && 'bunny' === EASYIO_REPLACE_GOOGLE_FONTS ) {
				$url = \str_replace( '//fonts.googleapis.com/css', '//fonts.bunny.net/css', $url, $gfonts_replaced );
				if ( $gfonts_replaced ) {
					$this->replaced_google_fonts = 'bunny';
				}
			} elseif ( ! \defined( 'EASYIO_REPLACE_GOOGLE_FONTS' ) || EASYIO_REPLACE_GOOGLE_FONTS ) {
				$url = \str_replace( '//fonts.googleapis.com/css', '//' . $this->exactdn_domain . '/easyio-fonts/css', $url, $gfonts_replaced );
				if ( $gfonts_replaced ) {
					$this->replaced_google_fonts = 'easyio';
				}
			}
			return $url;
		}

		$scheme = $this->scheme;
		if ( isset( $parsed_url['scheme'] ) && 'https' === $parsed_url['scheme'] ) {
			$scheme = 'https';
		}

		// Make sure this is an allowed image domain/hostname for ExactDN on this site.
		if ( ! $this->allow_image_domain( $parsed_url['host'] ) && ! $this->allow_asset_domain( $parsed_url['host'] ) ) {
			$this->debug_message( "invalid host for ExactDN: {$parsed_url['host']}" );
			return $url;
		}

		// You can't run an ExactDN URL through again because query strings are stripped.
		// So if the image is already an ExactDN URL, append the new arguments to the existing URL.
		if ( $this->exactdn_domain === $parsed_url['host'] ) {
			$this->debug_message( 'url already has exactdn domain' );
			return $url;
		}

		global $wp_version;
		// If a resource doesn't have a version string, we add one to help with cache-busting.
		if (
			false !== \strpos( $url, $this->content_path . '/themes/' ) &&
			( empty( $parsed_url['query'] ) || 'ver=' . $wp_version === $parsed_url['query'] )
		) {
			$modified = $this->function_exists( '\filemtime' ) ? \filemtime( \get_template_directory() ) : '';
			if ( empty( $modified ) ) {
				$modified = $this->version;
			}
			/**
			 * Allows a custom version string for resources that are missing one.
			 *
			 * @param string Defaults to the modified time of the theme folder, and falls back to the plugin version.
			 */
			$parsed_url['query'] = \apply_filters( 'exactdn_version_string', "m=$modified" );
		} elseif (
			false !== \strpos( $url, $this->content_path . '/plugins/' ) &&
			( empty( $parsed_url['query'] ) || 'ver=' . $wp_version === $parsed_url['query'] )
		) {
			$parsed_url['query'] = '';
			$path                = $this->url_to_path_exists( $url );
			if ( $path ) {
				$modified = $this->function_exists( '\filemtime' ) ? \filemtime( \dirname( $path ) ) : '';
				if ( empty( $modified ) ) {
					$modified = $this->version;
				}
				/**
				 * Allows a custom version string for resources that are missing one.
				 *
				 * @param string Defaults to the modified time of the folder, and falls back to the plugin version.
				 */
				$parsed_url['query'] = \apply_filters( 'exactdn_version_string', "m=$modified" );
			}
		} elseif ( empty( $parsed_url['query'] ) ) {
			$parsed_url['query'] = \apply_filters( 'exactdn_version_string', 'm=' . $this->version );
		}

		$exactdn_url = $scheme . '://' . $this->exactdn_domain . '/' . \ltrim( $parsed_url['path'], '/' ) . '?' . $parsed_url['query'];
		if ( $this->allow_asset_domain( $parsed_url['host'] ) ) {
			$exactdn_url = $scheme . '://' . $this->exactdn_domain . '/easyio-assets/' . $parsed_url['host'] . '/' . \ltrim( $parsed_url['path'], '/' ) . '?' . $parsed_url['query'];
		}
		$this->debug_message( "exactdn css/script url: $exactdn_url" );
		return $this->url_scheme( $exactdn_url, $scheme );
	}

	/**
	 * Generates an ExactDN URL.
	 *
	 * @param string       $image_url URL to the publicly accessible image you want to manipulate.
	 * @param array|string $args An array of arguments, i.e. array( 'w' => '300', 'resize' => '123,456' ), or in string form (w=123&h=456).
	 * @param string       $scheme Indicates http or https, other schemes are invalid.
	 * @return string The raw final URL. You should run this through esc_url() before displaying it.
	 */
	public function generate_url( $image_url, $args = array(), $scheme = null ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$image_url = \trim( $image_url );
		$this->debug_message( "starting with $image_url" );

		if ( \is_null( $scheme ) ) {
			$scheme = $this->scheme;
		}
		if ( \is_string( $scheme ) ) {
			$this->debug_message( "starting scheme: $scheme" );
		} else {
			$this->debug_message( 'no starting scheme' );
		}

		/**
		 * Disables ExactDN URL processing for local development.
		 *
		 * @param bool false default
		 */
		if ( true === \apply_filters( 'exactdn_development_mode', false ) ) {
			$this->debug_message( 'skipping in dev mode' );
			return $image_url;
		}

		/**
		 * Allow specific URLs to avoid going through ExactDN.
		 *
		 * @param bool false Should the URL be returned as is, without going through ExactDN. Default to false.
		 * @param string $image_url Resource URL.
		 * @param array|string $args Array of ExactDN arguments.
		 * @param string|null $scheme URL scheme. Default to null.
		 */
		if ( true === \apply_filters( 'exactdn_skip_for_url', false, $image_url, $args, $scheme ) ) {
			$this->debug_message( 'skipping via filter' );
			return $image_url;
		}

		$jpg_quality  = \apply_filters( 'jpeg_quality', null, 'image_resize' );
		$webp_quality = \apply_filters( 'webp_quality', 75, 'image/webp' );
		$avif_quality = \apply_filters( 'avif_quality', 60, 'image/avif' );

		$more_args = array();
		if ( false === \strpos( $image_url, 'strip=all' ) && $this->get_option( $this->prefix . 'metadata_remove' ) ) {
			$more_args['strip'] = 'all';
		}
		if ( false !== \strpos( $image_url, 'lossy=1' ) && isset( $args['lossy'] ) && 0 === $args['lossy'] ) {
			$image_url = \str_replace( 'lossy=1', 'lossy=0', $image_url );
			unset( $args['lossy'] );
		} elseif ( isset( $args['lossy'] ) && false !== \strpos( $image_url, 'lossy=0' ) ) {
			unset( $args['lossy'] );
		} elseif ( false === \strpos( $image_url, 'lossy=' ) && ! $this->get_option( 'exactdn_lossy' ) ) {
			$more_args['lossy'] = 0;
		} elseif ( false === \strpos( $image_url, 'lossy=' ) && $this->get_option( 'exactdn_lossy' ) ) {
			$more_args['lossy'] = \is_numeric( $this->get_option( 'exactdn_lossy' ) ) ? (int) $this->get_option( 'exactdn_lossy' ) : 1;
		}
		if ( false === \strpos( $image_url, 'quality=' ) && ! \is_null( $jpg_quality ) && 82 !== (int) $jpg_quality ) {
			$more_args['quality'] = $jpg_quality;
		}
		if ( false === \strpos( $image_url, 'webp=' ) && 75 !== (int) $webp_quality ) {
			$more_args['webp'] = $webp_quality;
		}
		if ( false === \strpos( $image_url, 'avif=' ) && 60 !== (int) $avif_quality ) {
			$more_args['avif'] = $avif_quality;
		}
		if ( \defined( 'EIO_WEBP_SHARP_YUV' ) && EIO_WEBP_SHARP_YUV ) {
			$more_args['sharp'] = 1;
		} elseif ( $this->get_option( $this->prefix . 'sharpen' ) ) {
			$more_args['sharp'] = 1;
		}

		// Merge given args with the automatic (option-based) args, and also makes sure args is an array if it was previously a string.
		$args = \wp_parse_args( $args, $more_args );

		/**
		 * Filter the original image URL before it goes through ExactDN.
		 *
		 * @param string $image_url Image URL.
		 * @param array|string $args Array of ExactDN arguments.
		 * @param string|null $scheme Image scheme. Default to null.
		 */
		$image_url = \apply_filters( 'exactdn_pre_image_url', $image_url, $args, $scheme );
		$this->debug_message( "after exactdn_pre_image_url: $image_url" );

		if ( empty( $image_url ) ) {
			return $image_url;
		}

		$image_url_parts = $this->parse_url( $image_url );

		// Unable to parse.
		if ( ! \is_array( $image_url_parts ) || empty( $image_url_parts['host'] ) || empty( $image_url_parts['path'] ) ) {
			$this->debug_message( 'src url no good' );
			return $image_url;
		}

		if ( isset( $image_url_parts['scheme'] ) && 'https' === $image_url_parts['scheme'] ) {
			if ( \is_array( $args ) && false === \strpos( $image_url, 'ssl=' ) ) {
				$this->debug_message( 'adding ssl=1' );
				$args['ssl'] = 1;
			}
			$this->debug_message( 'setting scheme to https' );
			$scheme = 'https';
		}

		/**
		 * Filter the ExactDN image parameters before they are applied to an image.
		 *
		 * @param array|string $args Array of ExactDN arguments.
		 * @param string $image_url Image URL.
		 * @param string|null $scheme Image scheme. Default to null.
		 */
		$args = \apply_filters( 'exactdn_pre_args', $args, $image_url, $scheme );

		if ( \is_array( $args ) ) {
			// Convert values that are arrays into strings.
			foreach ( $args as $arg => $value ) {
				if ( \is_array( $value ) ) {
					$args[ $arg ] = \implode( ',', $value );
				}
			}

			// Encode argument values.
			$args = \rawurlencode_deep( $args );
		}

		$this->debug_message( $image_url_parts['host'] );
		$this->debug_message( $image_url_parts['path'] );

		// Figure out which CDN (sub)domain to use.
		if ( empty( $this->exactdn_domain ) ) {
			$this->debug_message( 'no exactdn domain configured' );
			return $image_url;
		}

		// You can't run an ExactDN URL through again because query strings are stripped.
		// So if the image is already an ExactDN URL, append the new arguments to the existing URL.
		if ( $this->exactdn_domain === $image_url_parts['host'] ) {
			$this->debug_message( 'url already has exactdn domain' );
			$exactdn_url = \add_query_arg( $args, $image_url );
			$exactdn_url = \str_replace( '&#038;', '&', $exactdn_url );
			$exactdn_url = \str_replace( '#038;', '&', $exactdn_url );
			return $this->url_scheme( $exactdn_url, $scheme );
		}

		// ExactDN doesn't support query strings so we ignore them and look only at the path.
		// However some source images are served via PHP so check the no-query-string extension.
		// For future proofing, this is a blacklist of common issues rather than a whitelist.
		$extension = \pathinfo( $image_url_parts['path'], PATHINFO_EXTENSION );
		if ( ( empty( $extension ) && false === \strpos( $image_url_parts['path'], 'nextgen-image/' ) ) || \in_array( $extension, array( 'php', 'ashx' ), true ) ) {
			$this->debug_message( 'bad extension' );
			return $image_url;
		}

		if ( $this->remove_path && 0 === \strpos( $image_url_parts['path'], $this->remove_path ) ) {
			$image_url_parts['path'] = \substr( $image_url_parts['path'], \strlen( $this->remove_path ) );
			$this->debug_message( "trimming $this->remove_path from " . $image_url_parts['path'] );
		}
		$domain      = 'http://' . $this->exactdn_domain . '/';
		$exactdn_url = $domain . \ltrim( $image_url_parts['path'], '/' );
		$this->debug_message( "bare exactdn url: $exactdn_url" );

		/**
		 * Add query strings to ExactDN URL.
		 * By default, ExactDN doesn't support query strings so we ignore them.
		 * This setting is ExactDN Server dependent.
		 *
		 * @param bool false Should query strings be added to the image URL. Default is false.
		 * @param string $image_url_parts['host'] Image URL's host.
		 */
		if ( isset( $image_url_parts['query'] ) && \apply_filters( 'exactdn_add_query_string_to_domain', false, $image_url_parts['host'] ) ) {
			$exactdn_url .= '?q=' . \rawurlencode( $image_url_parts['query'] );
		}

		// This makes sure we populate args with the existing TST image version.
		if ( ! empty( $image_url_parts['query'] ) && false !== \strpos( $image_url_parts['query'], 'theia_smart' ) ) {
			$args = wp_parse_args( $image_url_parts['query'], $args );
		}

		// Clear out args for some files (like videos) that might go through image_downsize.
		if ( ! empty( $extension ) && \in_array( $extension, array( 'mp4', 'm4v', 'mov', 'mpg', 'mpeg', 'mpv', 'ogv', 'qt', 'svg', 'webm', 'wmv', 'wvm' ), true ) ) {
			$args = array();
		}

		if ( $args ) {
			if ( is_array( $args ) ) {
				$exactdn_url = \add_query_arg( $args, $exactdn_url );
				$exactdn_url = \str_replace( '&#038;', '&', $exactdn_url );
				$exactdn_url = \str_replace( '#038;', '&', $exactdn_url );
			} else {
				// You can pass a query string for complicated requests, although this should have been converted to an array already.
				$exactdn_url .= '?' . $args;
			}
		}
		$this->debug_message( "exactdn url with args: $exactdn_url" );

		return $this->url_scheme( $exactdn_url, $scheme );
	}

	/**
	 * Prepends schemeless urls or replaces non-http scheme with a valid scheme, defaults to 'http'.
	 *
	 * @param string      $url The URL to parse.
	 * @param string|null $scheme Retrieve specific URL component.
	 * @return string Result of parse_url.
	 */
	public function url_scheme( $url, $scheme ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! \in_array( $scheme, array( 'http', 'https' ), true ) ) {
			$this->debug_message( 'not a valid scheme' );
			if ( \preg_match( '#^(https?:)?//#', $url ) ) {
				$this->debug_message( 'url has a valid scheme already' );
				return $url;
			}
			$this->debug_message( 'invalid scheme provided, and url sucks, defaulting to http' );
			$scheme = 'http';
		}
		$this->debug_message( "valid $scheme - $url" );
		return \preg_replace( '#^([a-z:]+)?//#i', "$scheme://", $url );
	}

	/**
	 * A wrapper for human_time_diff() that gives sub-minute times in seconds.
	 *
	 * @param int $from Unix timestamp from which the difference begins.
	 * @param int $to Optional. Unix timestamp to end the time difference. Default is time().
	 * @return string Human readable time difference.
	 */
	public function human_time_diff( $from, $to = '' ) {
		if ( empty( $to ) ) {
			$to = \time();
		}
		$diff = (int) \abs( $to - $from );
		if ( $diff < 60 ) {
			return "$diff sec";
		}
		return \human_time_diff( $from, $to );
	}

	/**
	 * Adds link to header which enables DNS prefetching and preconnect for faster speed.
	 *
	 * @param array  $hints A list of hints for a particular relationship type.
	 * @param string $relationship_type The type of hint being filtered: dns-prefetch, preconnect, etc.
	 * @return array The list of hints, potentially with the ExactDN domain added in.
	 */
	public function resource_hints( $hints, $relationship_type ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! $this->exactdn_domain ) {
			return $hints;
		}
		if ( 'dns-prefetch' === $relationship_type ) {
			$hints[] = '//' . $this->exactdn_domain;
		}
		if ( 'preconnect' === $relationship_type ) {
			$hints[] = '//' . $this->exactdn_domain;
		}
		return $hints;
	}

	/**
	 * Adds the ExactDN domain to the list of 'local' domains for Autoptimize.
	 *
	 * @param array $domains A list of domains considered 'local' by Autoptimize.
	 * @return array The same list, with the ExactDN domain appended.
	 */
	public function add_cdn_domain( $domains ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( \is_array( $domains ) && ! \in_array( $this->exactdn_domain, $domains, true ) ) {
			$this->debug_message( 'adding to CDN domain/host list: ' . $this->exactdn_domain );
			$domains[] = $this->exactdn_domain;
		}
		return $domains;
	}

	/**
	 * Checks the configured alias for savings information.
	 *
	 * @return array The original size of all images that have been compressed by Easy IO along with how much was saved.
	 */
	public function savings() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$url = 'http://optimize.exactlywww.com/exactdn/savings.php';
		$ssl = \wp_http_supports( array( 'ssl' ) );
		if ( $ssl ) {
			$url = \set_url_scheme( $url, 'https' );
		}
		\add_filter( 'http_headers_useragent', $this->prefix . 'cloud_useragent', PHP_INT_MAX );
		$result = \wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'body'    => array(
					'alias' => $this->exactdn_domain,
				),
			)
		);
		if ( \is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			$this->debug_message( "savings request failed: $error_message" );
		} elseif ( ! empty( $result['body'] ) ) {
			$this->debug_message( "savings data retrieved: {$result['body']}" );
			$response = \json_decode( $result['body'], true );
			if ( \is_array( $response ) && ! empty( $response['original'] ) && ! empty( $response['savings'] ) ) {
				return $response;
			}
		}
		return false;
	}
}

global $exactdn;
$exactdn = new ExactDN();
