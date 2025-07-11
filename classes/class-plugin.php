<?php
/**
 * Low-level plugin class.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The kitchen sink, for everything that doesn't fit somewhere else.
 * Ideally, these are things like plugin initialization, setting defaults, and checking compatibility. We'll see how that plays out!
 */
final class Plugin extends Base {
	/* Singleton */

	/**
	 * The one and only true EWWW\Plugin
	 *
	 * @var object|EWWW\Plugin $instance
	 */
	private static $instance;

	/**
	 * Async Key Verify object.
	 *
	 * @var object|EWWW\Async_Key_Verify $async_key_verify
	 */
	public $async_key_verify;

	/**
	 * Async Scan object.
	 *
	 * @var object|EWWW\Async_Scan $async_scan
	 */
	public $async_scan;

	/**
	 * Async Test Optimize object.
	 *
	 * @var object|EWWW\Async_Test_Optimize $async_test_optimize
	 */
	public $async_test_optimize;

	/**
	 * Async Test Request object.
	 *
	 * @var object|EWWW\Async_Test_Request $async_test_request
	 */
	public $async_test_request;

	/**
	 * Background Attachment Update object.
	 *
	 * @var object|EWWW\Background_Process_Attachment_Update $background_attachment_update
	 */
	public $background_attachment_update;

	/**
	 * Background Process Flag object.
	 *
	 * @var object|EWWW\Background_Process_Flag $background_flag
	 */
	public $background_flag;

	/**
	 * Background Process Image object.
	 *
	 * @var object|EWWW\Background_Process_Image $background_image
	 */
	public $background_image;

	/**
	 * Background Process Media object.
	 *
	 * @var object|EWWW\Background_Process_Media $background_media
	 */
	public $background_media;

	/**
	 * Background Process Ngg object.
	 *
	 * @var object|EWWW\Background_Process_Ngg $background_ngg
	 */
	public $background_ngg;

	/**
	 * Background Process Ngg2 object.
	 *
	 * @var object|EWWW\Background_Process_Ngg2 $background_ngg2
	 */
	public $background_ngg2;

	/**
	 * Helpscout Beacon object.
	 *
	 * @var object|EWWW\HS_Beacon $hs_beacon
	 */
	public $hs_beacon;

	/**
	 * EWWW\Local object for handling local optimization tools/functions.
	 *
	 * @var object|EWWW\Local $local
	 */
	public $local;

	/**
	 * EWWW\Admin_Notices object for handling notifications.
	 *
	 * @var object|EWWW\Admin_Notices $notices
	 */
	public $notices;

	/**
	 * EWWW\Tracking object for anonymous usage tracking.
	 *
	 * @var object|EWWW\Tracking $tracking
	 */
	public $tracking;

	/**
	 * Whether the plugin is using the API or local tools.
	 *
	 * @var bool $cloud_mode
	 */
	public $cloud_mode = false;

	/**
	 * Whether the plugin is allowed to use async mode for the API.
	 *
	 * @var bool $cloud_mode
	 */
	public $cloud_async_allowed = false;

	/**
	 * Whether deferral (async processing) of image optimization is allowed.
	 *
	 * Normally true, but if the plugin is already in processing an image
	 * in async mode, then it shouldn't be deferred endlessly.
	 *
	 * @var bool $defer
	 */
	public $defer = true;

	/**
	 * Whether forced re-optimization is enabled.
	 *
	 * @var bool $force
	 */
	public $force = false;

	/**
	 * Whether smart, forced re-optimization is enabled, to re-optimize
	 * images that were previously compressed at a different optimization level.
	 *
	 * @var bool $force
	 */
	public $force_smart = false;

	/**
	 * Whether WebP-only mode is enabled, so that other optimizations
	 * are disabled, and only WebP conversion is attempted.
	 *
	 * @var bool $webp_only
	 */
	public $webp_only = false;

	/**
	 * A list of errors reported when saving the EWWW IO settings.
	 *
	 * @var array $settings_errors
	 */
	protected $settings_errors = array();

	/**
	 * Did we already run tool_init()?
	 *
	 * @var bool $tools_initialized
	 */
	public $tools_initialized = false;

	/**
	 * Main EWWW\Plugin instance.
	 *
	 * Ensures that only one instance of EWWW_Plugin exists in memory at any given time.
	 *
	 * @static
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Plugin ) ) {
			// Setup custom $wpdb attribute for our image-tracking table.
			global $wpdb;
			if ( ! isset( $wpdb->ewwwio_images ) ) {
				$wpdb->ewwwio_images = $wpdb->prefix . 'ewwwio_images';
			}
			if ( ! isset( $wpdb->ewwwio_queue ) ) {
				$wpdb->ewwwio_queue = $wpdb->prefix . 'ewwwio_queue';
			}

			self::$instance = new Plugin( true );
			self::$instance->debug_message( '<b>' . __METHOD__ . '()</b>' );
			// TODO: self::$instance->setup_constants()?

			// For classes we need everywhere, front-end and back-end. Others are only included on admin_init (below).
			self::$instance->requires();
			self::$instance->load_children();
			// Load async classes early, even though cron schedules use translations, and should not normally be loaded any earlier than init.
			// The async classes have been modified to not use translations any earlier than init.
			self::$instance->load_async_children();

			// Load plugin compatibility functions for S3 Uploads, NextGEN, FlaGallery, and Nextcellent.
			\add_action( 'plugins_loaded', array( self::$instance, 'plugins_compat' ) );
			// Initializes the plugin for admin interactions, like saving network settings and scheduling cron jobs.
			\add_action( 'admin_init', array( self::$instance, 'admin_init' ) );
			// We run this early, and then double-check after admin_init, once network settings have been saved/updated.
			self::$instance->cloud_init();
			// Runs other checks that need to run on 'init'.
			\add_action( 'init', array( self::$instance, 'init' ), 9 );

			// Registers various hooks for automatic optimization with core and other plugins.
			// NOTE: this may make sense to move elsewhere someday, but it is here for now!
			// TODO: the functions registered could (should?) become class members, which is why it may make more sense as a separate class.
			self::$instance->register_integration_hooks();

			// TODO: check PHP and WP compat here.
			// TODO: setup anything that needs to run on init/plugins_loaded.
			// TODO: add any custom option/setting hooks here (actions that need to be taken when certain settings are saved/updated).
			\add_action( 'update_option_ewww_image_optimizer_cloud_key', array( self::$instance, 'updated_cloud_key' ), 10, 2 );
		}

		return self::$instance;
	}

	/**
	 * Throw error on object clone.
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object. Therefore, we don't want the object to be cloned.
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden.
		\_doing_it_wrong( __METHOD__, \esc_html__( 'Cannot clone core object.', 'ewww-image-optimizer' ), \esc_html( EWWW_IMAGE_OPTIMIZER_VERSION ) );
	}

	/**
	 * Disable unserializing of the class.
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		\_doing_it_wrong( __METHOD__, \esc_html__( 'Cannot unserialize (wakeup) the core object.', 'ewww-image-optimizer' ), \esc_html( EWWW_IMAGE_OPTIMIZER_VERSION ) );
	}

	/**
	 * Include required files.
	 *
	 * @access private
	 */
	private function requires() {
		// Fall-back and convenience functions.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'functions.php';
		// Functions for bulk processing.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'bulk.php';
		// Functions for the images and queue db tables and bulk processing images outside the library.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'aux-optimize.php';
		// Require the various class extensions for background optimization.
		$this->async_requires();
		// EWWW_Image class for working with queued images and image records from the database.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewww-image.php';
		// EWWWW\Local class for optimization tool installation/validation.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-local.php';
		// EWWW\Admin_Notices class for managing admin notices.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-admin-notices.php';
		// EWWW\Backup class for managing image backups.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-backup.php';
		// EWWW\HS_Beacon class for integrated help/docs.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-hs-beacon.php';
		// EWWW\Tracking class for reporting anonymous site data.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-tracking.php';
		if ( 'done' !== get_option( 'ewww_image_optimizer_relative_migration_status' ) ) {
			require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewwwio-relative-migration.php';
		}
		// Used for manipulating exif info.
		if ( ! class_exists( '\lsolesen\pel\PelJpeg' ) ) {
			require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'vendor/autoload.php';
		}
	}

	/**
	 * Include required files for async/background processing.
	 *
	 * @access private
	 */
	private function async_requires() {
		/**
		 * The (grand)parent EWWW\Async_Request class file.
		 */
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-async-request.php';

		/**
		 * The parent EWWW\Background_Process class file.
		 */
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-background-process.php';

		// Async API Key verification.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-async-key-verify.php';
		// Async image scanning for scheduled opt.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-async-scan.php';
		// Async optimization test, used for debugging.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-async-test-optimize.php';
		// Async test request, used to make sure async works properly.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-async-test-request.php';
		// Background attachment updating.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-background-process-attachment-update.php';
		// Background optimization for GRAND FlaGallery.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-background-process-flag.php';
		// Background optimization for individual images.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-background-process-image.php';
		// Background optimization for the Media Library.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-background-process-media.php';
		// Background optimization for Nextcellent.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-background-process-ngg.php';
		// Background optimization for NextGEN Gallery.
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-background-process-ngg2.php';
	}

	/**
	 * Setup mandatory child classes.
	 */
	private function load_children() {
		self::$instance->local    = new Local();
		self::$instance->notices  = new Admin_Notices();
		self::$instance->tracking = new Tracking();
	}

	/**
	 * Setup mandatory async/background child classes (should not be done before 'init').
	 */
	private function load_async_children() {
		self::$instance->async_key_verify             = new Async_Key_Verify();
		self::$instance->async_scan                   = new Async_Scan();
		self::$instance->async_test_optimize          = new Async_Test_Optimize();
		self::$instance->async_test_request           = new Async_Test_Request();
		self::$instance->background_attachment_update = new Background_Process_Attachment_Update();
		self::$instance->background_image             = new Background_Process_Image();
		self::$instance->background_media             = new Background_Process_Media();
	}

	/**
	 * Load plugin compat on the plugins_loaded hook, which is about as early as possible.
	 */
	public function plugins_compat() {
		$this->debug_message( '<b>' . __FUNCTION__ . '()</b>' );

		if ( $this->s3_uploads_enabled() ) {
			$this->debug_message( 's3-uploads detected, deferring resize_upload' );
			\add_filter( 'ewww_image_optimizer_defer_resizing', '__return_true' );
		}

		$active_plugins = \get_option( 'active_plugins' );
		if ( \is_multisite() && \is_array( $active_plugins ) ) {
			$sitewide_plugins = \get_site_option( 'active_sitewide_plugins' );
			if ( \is_array( $sitewide_plugins ) ) {
				$active_plugins = \array_merge( $active_plugins, \array_flip( $sitewide_plugins ) );
			}
		}
		if ( $this->is_iterable( $active_plugins ) ) {
			$this->debug_message( 'checking active plugins' );
			foreach ( $active_plugins as $active_plugin ) {
				if ( \strpos( $active_plugin, '/nggallery.php' ) || \strpos( $active_plugin, '\nggallery.php' ) ) {
					$ngg = ewww_image_optimizer_get_plugin_version( \trailingslashit( WP_PLUGIN_DIR ) . $active_plugin );
					// Include the file that loads the nextgen gallery optimization functions.
					$this->debug_message( 'Nextgen version: ' . $ngg['Version'] );
					if ( 1 < \intval( \substr( $ngg['Version'], 0, 1 ) ) ) { // For Nextgen 2+ support.
						$nextgen_major_version = \substr( $ngg['Version'], 0, 1 );
						$this->debug_message( "loading nextgen $nextgen_major_version support for $active_plugin" );
						// Initialize the nextgen async/background class.
						self::$instance->background_ngg2 = new Background_Process_Ngg2();
						require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewww-nextgen.php';
					} else {
						\preg_match( '/\d+\.\d+\.(\d+)/', $ngg['Version'], $nextgen_minor_version );
						if ( ! empty( $nextgen_minor_version[1] ) && $nextgen_minor_version[1] < 14 ) {
							$this->debug_message( "NOT loading nextgen legacy support for $active_plugin" );
						} elseif ( ! empty( $nextgen_minor_version[1] ) && $nextgen_minor_version[1] > 13 ) {
							$this->debug_message( "loading nextcellent support for $active_plugin" );
							// Initialize the nextcellent async/background class.
							self::$instance->background_ngg = new Background_Process_Ngg();
							require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewww-nextcellent.php';
						}
					}
				}
				if ( \strpos( $active_plugin, '/flag.php' ) || \strpos( $active_plugin, '\flag.php' ) ) {
					$this->debug_message( "loading flagallery support for $active_plugin" );
					// Initialize the flagallery async/background class.
					self::$instance->background_flag = new Background_Process_Flag();
					// Include the file that loads the grand flagallery optimization functions.
					require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewww-flag.php';
				}
			}
		}
	}

	/**
	 * Check to see if we are running in "cloud" mode. That is, using the API and no local tools.
	 */
	public function cloud_init() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if (
			$this->get_option( 'ewww_image_optimizer_cloud_key' ) &&
			$this->get_option( 'ewww_image_optimizer_jpg_level' ) > 10 &&
			$this->get_option( 'ewww_image_optimizer_png_level' ) > 10
		) {
			$this->cloud_mode = true;
		}
	}

	/**
	 * Initializes settings for the local tools, and runs the checks for tools on select pages.
	 */
	public function exec_init() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		global $exactdn;

		// Initialize this, for if/when we setup JPG-only mode. If an API key is active, we'll toggle to false.
		$default_jpg_only_mode = true;

		// If cloud is fully enabled, we're going to skip all the checks related to the bundled tools.
		if ( $this->cloud_mode ) {
			$this->debug_message( 'cloud options enabled, shutting off binaries' );
			$this->local->skip_tools();
			$this->toggle_jpg_only_mode( false );
			return;
		} elseif ( $this->get_option( 'ewww_image_optimizer_cloud_key' ) ) {
			$default_jpg_only_mode = false;
			$this->toggle_jpg_only_mode( $default_jpg_only_mode );
		}
		if ( $this->local->hosting_requires_api() ) {
			$this->toggle_jpg_only_mode( $default_jpg_only_mode );
			$this->debug_message( 'WPE/wp.com/pantheon/flywheel site, disabling tools' );
			return;
		}
		if ( ! $this->local->os_supported() ) {
			$this->toggle_jpg_only_mode( $default_jpg_only_mode );
			// Turn off all the tools.
			$this->debug_message( 'unsupported OS, disabling tools: ' . PHP_OS );
			$this->local->skip_tools();
			return;
		}
		// Last check for JPG-only mode until we know whether jpegtran or optipng are functional.
		if ( ! $this->local->exec_check() ) {
			$this->toggle_jpg_only_mode( $default_jpg_only_mode );
		}
		$this->tool_init();
	}

	/**
	 * Check for binary installation and availability.
	 */
	public function tool_init() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->tools_initialized = true;
		// Make sure the bundled tools are installed.
		if ( ! $this->get_option( 'ewww_image_optimizer_skip_bundle' ) && $this->local->exec_check() ) {
			$this->local->install_tools();
		}
		if ( $this->cloud_mode ) {
			$this->debug_message( 'cloud options enabled, shutting off binaries' );
			$this->local->skip_tools();
		}
	}

	/**
	 * Setup plugin for wp-admin.
	 */
	public function admin_init() {
		$this->hs_beacon = new HS_Beacon();
		/**
		 * Require the files that migrate WebP images from extension replacement to extension appending.
		 */
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'mwebp.php';

		// Check if the plugin has been updated and any upgrade routines need to be run.
		\ewww_image_optimizer_upgrade();

		// Do settings validation for multi-site.
		\ewww_image_optimizer_save_network_settings();

		$this->register_settings();
		$this->cloud_init();
		$this->exec_init();

		// Setup the cron job for scheduled optimization.
		\ewww_image_optimizer_cron_setup( 'ewww_image_optimizer_auto' );

		// Adds scripts to ajaxify the one-click actions on the media library, and register tooltips for conversion links.
		\add_action( 'admin_enqueue_scripts', 'ewww_image_optimizer_media_scripts' );
		// Adds scripts for the EWWW IO settings page.
		\add_action( 'admin_enqueue_scripts', 'ewww_image_optimizer_settings_script' );
		// Queue the function that contains custom styling for our progressbars.
		\add_action( 'admin_enqueue_scripts', 'ewww_image_optimizer_progressbar_style' );

		if ( $this->get_option( 'ewww_image_optimizer_webp_force' ) && $this->get_option( 'ewww_image_optimizer_force_gif2webp' ) && ! $this->get_option( 'ewww_image_optimizer_cloud_key' ) ) {
			$this->set_option( 'ewww_image_optimizer_force_gif2webp', false );
		}
		if (
			! $this->get_option( 'ewww_image_optimizer_ludicrous_mode' ) &&
			! $this->get_option( 'ewww_image_optimizer_cloud_key' ) &&
			\ewww_image_optimizer_easy_active()
		) {
			// Suppress the custom column in the media library if Easy IO CDN is enabled without an API key and Easy Mode is active.
			\remove_filter( 'manage_media_columns', 'ewww_image_optimizer_columns' );
		}
		if ( \ewww_image_optimizer_easy_active() ) {
			$this->set_option( 'ewww_image_optimizer_webp', false );
			$this->set_option( 'ewww_image_optimizer_webp_force', false );
		}
		// Alert user if multiple re-optimizations detected.
		if ( false && ! \defined( 'EWWWIO_DISABLE_REOPT_NOTICE' ) ) {
			\add_action( 'network_admin_notices', 'ewww_image_optimizer_notice_reoptimization' );
			\add_action( 'admin_notices', 'ewww_image_optimizer_notice_reoptimization' );
		}
		if ( ! \defined( 'EIO_PHPUNIT' ) && ( ! \defined( 'WP_CLI' ) || ! WP_CLI ) ) {
			\ewww_image_optimizer_privacy_policy_content();
			\ewww_image_optimizer_ajax_compat_check();
		}
	}

	/**
	 * Runs early for checks that need to happen on init before anything else.
	 */
	public function init() {
		$this->debug_message( '<b>' . __FUNCTION__ . '()</b>' );

		// For the settings page, check for the enable-local param and take appropriate action.
		if ( ! empty( $_GET['enable-local'] ) && ! empty( $_REQUEST['_wpnonce'] ) && \wp_verify_nonce( \sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' ) ) {
			\update_option( 'ewww_image_optimizer_ludicrous_mode', true );
			\update_site_option( 'ewww_image_optimizer_ludicrous_mode', true );
		} elseif ( isset( $_GET['enable-local'] ) && ! (bool) $_GET['enable-local'] && ! empty( $_REQUEST['_wpnonce'] ) && \wp_verify_nonce( \sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' ) ) {
			\update_option( 'ewww_image_optimizer_ludicrous_mode', false );
			\update_site_option( 'ewww_image_optimizer_ludicrous_mode', false );
		}
		if ( ! empty( $_GET['complete_wizard'] ) && ! empty( $_REQUEST['_wpnonce'] ) && \wp_verify_nonce( \sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' ) ) {
			\update_option( 'ewww_image_optimizer_wizard_complete', true, false );
		}
		if ( ! empty( $_GET['uncomplete_wizard'] ) && ! empty( $_REQUEST['_wpnonce'] ) && \wp_verify_nonce( \sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' ) ) {
			\update_option( 'ewww_image_optimizer_wizard_complete', false, false );
		}

		if ( \defined( 'CROP_THUMBNAILS_VERSION' ) ) {
			\add_filter( 'ewwwio_use_original_for_webp_thumbs', '__return_false', 9 ); // Early, so folks can turn it back on if they want for some reason.
		}

		if ( $this->test_mode_active() ) {
			\add_filter( 'exactdn_skip_page', '__return_true' );
			\add_filter( 'eio_do_lazyload', '__return_false' );
			\add_filter( 'eio_do_js_webp', '__return_false' );
			\add_filter( 'eio_do_picture_webp', '__return_false' );
		}

		if ( \defined( 'DOING_WPLR_REQUEST' ) && DOING_WPLR_REQUEST ) {
			// Unhook all automatic processing, and save an option that (does not autoload) tells the user LR Sync regenerated their images and they should run the bulk optimizer.
			\remove_filter( 'wp_image_editors', 'ewww_image_optimizer_load_editor', 60 );
			\remove_filter( 'wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15 );
			\add_action( 'wplr_add_media', 'ewww_image_optimizer_lr_sync_update' );
			\add_action( 'wplr_update_media', 'ewww_image_optimizer_lr_sync_update' );
			\add_filter( 'ewww_image_optimizer_allowed_reopt', '__return_true' );
		}
	}

	/**
	 * If automatic optimization is enabled, register hooks to integrate with various core functions and plugins.
	 */
	public function register_integration_hooks() {
		// If automatic optimization is NOT disabled.
		if ( ! $this->get_option( 'ewww_image_optimizer_noauto' ) ) {
			if ( ! \defined( 'EWWW_IMAGE_OPTIMIZER_DISABLE_EDITOR' ) || ! EWWW_IMAGE_OPTIMIZER_DISABLE_EDITOR ) {
				// Turns off the ewwwio_image_editor during uploads.
				\add_action( 'add_attachment', 'ewww_image_optimizer_add_attachment' );
				// Turn off the editor when scaling down the original (core WP 5.3+).
				\add_filter( 'big_image_size_threshold', 'ewww_image_optimizer_image_sizes' );
				// Turns off ewwwio_image_editor during Enable Media Replace.
				\add_filter( 'emr_unfiltered_get_attached_file', 'ewww_image_optimizer_image_sizes' );
				// Checks to see if thumb regen or other similar operation is running via REST API.
				\add_action( 'rest_api_init', 'ewww_image_optimizer_restapi_compat_check' );
				// Detect WP/LR Sync when it starts.
				\add_action( 'wplr_presync_media', 'ewww_image_optimizer_image_sizes' );
				// Enables direct integration to the editor's save function.
				\add_filter( 'wp_image_editors', 'ewww_image_optimizer_load_editor', 60 );
				// Add missing Imagick data to Site Health.
				\add_filter( 'debug_information', array( $this, 'wp_media_debug_information' ) );
			}
			// Resizes and auto-rotates images.
			\add_filter( 'wp_handle_upload', 'ewww_image_optimizer_handle_upload' );
			// Processes an image via the metadata after upload.
			\add_filter( 'wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15, 2 );
			// Checks attachment for scaled version and updates metadata.
			\add_filter( 'wp_generate_attachment_metadata', 'ewww_image_optimizer_update_scaled_metadata', 8, 2 );
			\add_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_update_scaled_metadata', 8, 2 );
			// Add hook for PTE confirmation to make sure new resizes are optimized.
			\add_filter( 'wp_get_attachment_metadata', 'ewww_image_optimizer_pte_check' );
			// Resizes and auto-rotates MediaPress images.
			\add_filter( 'mpp_handle_upload', 'ewww_image_optimizer_handle_mpp_upload' );
			// Processes a MediaPress image via the metadata after upload.
			\add_filter( 'mpp_generate_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15, 2 );
			// Processes an attachment after IRSC has done a thumb regen.
			\add_filter( 'sirsc_attachment_images_ready', 'ewww_image_optimizer_resize_from_meta_data', 15, 2 );
			// Processes an attachment after Crop Thumbnails plugin has modified the images.
			\add_filter( 'crop_thumbnails_before_update_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15, 2 );
			// Process BuddyPress uploads from Vikinger theme.
			\add_action( 'vikinger_file_uploaded', 'ewww_image_optimizer' );
			// Process image after resize by Imsanity.
			\add_action( 'imsanity_post_process_attachment', 'ewww_image_optimizer_optimize_by_id', 10, 2 );
		}
	}

	/**
	 * Register all our options and santiation functions.
	 */
	public function register_settings() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Register all the common EWWW IO settings and their sanitation functions.
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_debug', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_test_mode', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_metadata_remove', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_level', 'intval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_png_level', 'intval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_gif_level', 'intval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_pdf_level', 'intval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_svg_level', 'intval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_webp_level', 'intval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_webp_conversion_method', 'sanitize_text_field' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_backup_files', 'sanitize_text_field' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_sharpen', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_quality', 'ewww_image_optimizer_jpg_quality' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_webp_quality', 'ewww_image_optimizer_webp_quality' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_avif_quality', 'ewww_image_optimizer_avif_quality' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_auto', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_include_media_paths', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_include_originals', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_aux_paths', 'ewww_image_optimizer_aux_paths_sanitize' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_exclude_paths', array( $this, 'exclude_paths_sanitize' ) );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_allow_tracking', array( $this->tracking, 'check_for_settings_optin' ) );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_enable_help', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'exactdn_all_the_things', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'exactdn_lossy', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'exactdn_hidpi', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'exactdn_exclude', array( $this, 'exclude_paths_sanitize' ) );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_add_missing_dims', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_lazy_load', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_ll_autoscale', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_ll_abovethefold', 'intval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_use_lqip', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_use_dcip', 'boolval' );
		// Using sanitize_text_field instead of textarea on purpose.
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_ll_all_things', 'sanitize_text_field' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_ll_exclude', array( $this, 'exclude_paths_sanitize' ) );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_resize_detection', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_maxmediawidth', 'intval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_maxmediaheight', 'intval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_resize_existing', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_resize_other_existing', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_preserve_originals', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_disable_resizes', 'ewww_image_optimizer_disable_resizes_sanitize' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_disable_resizes_opt', 'ewww_image_optimizer_disable_resizes_sanitize' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_disable_convert_links', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_delete_originals', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_to_png', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_png_to_jpg', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_gif_to_png', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_bmp_convert', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_background', 'ewww_image_optimizer_jpg_background' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_webp', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_webp_force', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_webp_paths', 'ewww_image_optimizer_webp_paths_sanitize' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_webp_for_cdn', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_picture_webp', 'boolval' );
		register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_webp_rewrite_exclude', array( $this, 'exclude_paths_sanitize' ) );
	}

	/**
	 * Set some default option values.
	 */
	public function set_defaults() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Set defaults for all options that need to be autoloaded.
		\add_option( 'ewww_image_optimizer_background_optimization', false );
		\add_option( 'ewww_image_optimizer_noauto', false ); // Disables auto-opt.
		\add_option( 'ewww_image_optimizer_auto', false ); // Scheduled opt (I know, poor naming).
		\add_option( 'ewww_image_optimizer_ludicrous_mode', false );
		\add_option( 'ewww_image_optimizer_jpg_only_mode', false );
		\add_option( 'ewww_image_optimizer_disable_editor', false );
		\add_option( 'ewww_image_optimizer_debug', false );
		\add_option( 'ewww_image_optimizer_test_mode', false );
		\add_option( 'ewww_image_optimizer_metadata_remove', true );
		\add_option( 'ewww_image_optimizer_maxmediawidth', 2560 );
		\add_option( 'ewww_image_optimizer_maxmediaheight', 2560 );
		\add_option( 'ewww_image_optimizer_cloud_key', false );
		\add_option( 'ewww_image_optimizer_jpg_level', '10' );
		\add_option( 'ewww_image_optimizer_png_level', '10' );
		\add_option( 'ewww_image_optimizer_gif_level', '10' );
		\add_option( 'ewww_image_optimizer_pdf_level', '0' );
		\add_option( 'ewww_image_optimizer_svg_level', '0' );
		\add_option( 'ewww_image_optimizer_webp_level', '0' );
		\add_option( 'ewww_image_optimizer_webp_conversion_method', 'local' );
		\add_option( 'ewww_image_optimizer_webp', false );
		\add_option( 'ewww_image_optimizer_jpg_quality', '' );
		\add_option( 'ewww_image_optimizer_webp_quality', '' );
		\add_option( 'ewww_image_optimizer_backup_files', '' );
		\add_option( 'ewww_image_optimizer_resize_existing', true );
		\add_option( 'ewww_image_optimizer_exactdn', false );
		\add_option( 'ewww_image_optimizer_exactdn_plan_id', 0 );
		\add_option( 'exactdn_all_the_things', true );
		\add_option( 'exactdn_lossy', true );
		\add_option( 'exactdn_hidpi', false );
		\add_option( 'exactdn_exclude', '' );
		\add_option( 'exactdn_sub_folder', false );
		\add_option( 'exactdn_prevent_db_queries', true );
		\add_option( 'exactdn_asset_domains', '' );
		\add_option( 'ewww_image_optimizer_lazy_load', false );
		\add_option( 'ewww_image_optimizer_add_missing_dims', false );
		\add_option( 'ewww_image_optimizer_use_siip', false );
		\add_option( 'ewww_image_optimizer_use_lqip', false );
		\add_option( 'ewww_image_optimizer_use_dcip', false );
		\add_option( 'ewww_image_optimizer_ll_exclude', '' );
		\add_option( 'ewww_image_optimizer_ll_all_things', '' );
		\add_option( 'ewww_image_optimizer_disable_pngout', true );
		\add_option( 'ewww_image_optimizer_disable_svgcleaner', true );
		\add_option( 'ewww_image_optimizer_optipng_level', 2 );
		\add_option( 'ewww_image_optimizer_pngout_level', 2 );
		\add_option( 'ewww_image_optimizer_webp_for_cdn', false );
		\add_option( 'ewww_image_optimizer_force_gif2webp', false );
		\add_option( 'ewww_image_optimizer_picture_webp', false );
		\add_option( 'ewww_image_optimizer_webp_rewrite_exclude', '' );

		// Set network defaults.
		\add_site_option( 'ewww_image_optimizer_background_optimization', false );
		\add_site_option( 'ewww_image_optimizer_metadata_remove', true );
		\add_site_option( 'ewww_image_optimizer_maxmediawidth', 2560 );
		\add_site_option( 'ewww_image_optimizer_maxmediaheight', 2560 );
		\add_site_option( 'ewww_image_optimizer_jpg_level', '10' );
		\add_site_option( 'ewww_image_optimizer_png_level', '10' );
		\add_site_option( 'ewww_image_optimizer_gif_level', '10' );
		\add_site_option( 'ewww_image_optimizer_pdf_level', '0' );
		\add_site_option( 'ewww_image_optimizer_svg_level', '0' );
		\add_site_option( 'ewww_image_optimizer_webp_level', '0' );
		\add_site_option( 'ewww_image_optimizer_webp_conversion_method', 'local' );
		\add_site_option( 'ewww_image_optimizer_jpg_quality', '' );
		\add_site_option( 'ewww_image_optimizer_webp_quality', '' );
		\add_site_option( 'ewww_image_optimizer_backup_files', '' );
		\add_site_option( 'ewww_image_optimizer_resize_existing', true );
		\add_site_option( 'ewww_image_optimizer_disable_pngout', true );
		\add_site_option( 'ewww_image_optimizer_disable_svgcleaner', true );
		\add_site_option( 'ewww_image_optimizer_optipng_level', 2 );
		\add_site_option( 'ewww_image_optimizer_pngout_level', 2 );
		\add_site_option( 'exactdn_all_the_things', true );
		\add_site_option( 'exactdn_lossy', true );
		\add_site_option( 'exactdn_hidpi', true );
		\add_site_option( 'exactdn_sub_folder', false );
		\add_site_option( 'exactdn_prevent_db_queries', true );
		\add_site_option( 'ewww_image_optimizer_ll_autoscale', true );
	}

	/**
	 * Check for settings errors and store them for future display.
	 *
	 * Removes EWWW IO settings errors from the global $wp_settings_errors to suppress standard error handling.
	 */
	public function get_settings_errors() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		global $wp_settings_errors;
		if ( empty( $wp_settings_errors ) || ! is_array( $wp_settings_errors ) ) {
			$stored_errors = get_settings_errors();
			if ( ! empty( $stored_errors ) && is_array( $stored_errors ) ) {
				$this->settings_errors = $stored_errors;
			}
			return;
		}
		foreach ( $wp_settings_errors as $key => $error_details ) {
			if ( ! empty( $error_details['setting'] ) && 0 === strpos( $error_details['setting'], 'ewww' ) ) {
				$this->debug_message( "stashing {$error_details['setting']} error" );
				$this->settings_errors[] = $error_details;
				unset( $wp_settings_errors[ $key ] );
			}
		}
	}

	/**
	 * Display any settings errors inside a div similar to the core settings_errors() function.
	 */
	public function settings_errors() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( empty( $this->settings_errors ) || ! is_array( $this->settings_errors ) ) {
			$this->debug_message( 'no errors!' );
			return;
		}
		$error_total = count( $this->settings_errors );
		$this->debug_message( "found $error_total errors to display, here we go!" );

		foreach ( $this->settings_errors as $key => $details ) {
			if ( empty( $details['type'] ) || empty( $details['code'] ) || empty( $details['message'] ) ) {
				continue;
			}
			if ( 'updated' === $details['type'] ) {
				$details['type'] = 'success';
			}

			if ( in_array( $details['type'], array( 'error', 'success', 'warning', 'info' ), true ) ) {
				$details['type'] = 'notice-' . $details['type'];
			}

			?>
			<div id='setting-error-<?php echo esc_attr( $details['code'] ); ?>' class='notice <?php echo \esc_attr( $details['type'] ); ?> is-dismissible inline'>
				<p><strong><?php echo esc_html( $details['message'] ); ?></strong></p>
			</div>
			<?php
		}
	}

	/**
	 * Fills in Imagick debug info for the Site Health screen, if core skips it.
	 *
	 * @param array $info All the Site Health Debug Info.
	 * @return array The Debug Info with Imagick info filled in.
	 */
	public function wp_media_debug_information( $info ) {
		if ( class_exists( '\Imagick' ) ) {
			$imagick = new \Imagick();
			if ( $imagick instanceof \Imagick ) {
				$this->debug_message( print_r( $info, true ) );
				if ( ! empty( $info['wp-media']['fields'] ) ) {
					$not_available = __( 'Not available' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					if ( empty( $info['wp-media']['fields']['imagick_limits'] ) ) {
						$limits = array(
							'area'   => ( defined( 'imagick::RESOURCETYPE_AREA' ) ? size_format( $imagick->getResourceLimit( \imagick::RESOURCETYPE_AREA ) ) : $not_available ),
							'disk'   => ( defined( 'imagick::RESOURCETYPE_DISK' ) ? $imagick->getResourceLimit( \imagick::RESOURCETYPE_DISK ) : $not_available ),
							'file'   => ( defined( 'imagick::RESOURCETYPE_FILE' ) ? $imagick->getResourceLimit( \imagick::RESOURCETYPE_FILE ) : $not_available ),
							'map'    => ( defined( 'imagick::RESOURCETYPE_MAP' ) ? size_format( $imagick->getResourceLimit( \imagick::RESOURCETYPE_MAP ) ) : $not_available ),
							'memory' => ( defined( 'imagick::RESOURCETYPE_MEMORY' ) ? size_format( $imagick->getResourceLimit( \imagick::RESOURCETYPE_MEMORY ) ) : $not_available ),
							'thread' => ( defined( 'imagick::RESOURCETYPE_THREAD' ) ? $imagick->getResourceLimit( \imagick::RESOURCETYPE_THREAD ) : $not_available ),
							'time'   => ( defined( 'imagick::RESOURCETYPE_TIME' ) ? $imagick->getResourceLimit( \imagick::RESOURCETYPE_TIME ) : $not_available ),
						);

						$limits_debug = array(
							'imagick::RESOURCETYPE_AREA'   => ( defined( 'imagick::RESOURCETYPE_AREA' ) ? size_format( $imagick->getResourceLimit( \imagick::RESOURCETYPE_AREA ) ) : 'not available' ),
							'imagick::RESOURCETYPE_DISK'   => ( defined( 'imagick::RESOURCETYPE_DISK' ) ? $imagick->getResourceLimit( \imagick::RESOURCETYPE_DISK ) : 'not available' ),
							'imagick::RESOURCETYPE_FILE'   => ( defined( 'imagick::RESOURCETYPE_FILE' ) ? $imagick->getResourceLimit( \imagick::RESOURCETYPE_FILE ) : 'not available' ),
							'imagick::RESOURCETYPE_MAP'    => ( defined( 'imagick::RESOURCETYPE_MAP' ) ? size_format( $imagick->getResourceLimit( \imagick::RESOURCETYPE_MAP ) ) : 'not available' ),
							'imagick::RESOURCETYPE_MEMORY' => ( defined( 'imagick::RESOURCETYPE_MEMORY' ) ? size_format( $imagick->getResourceLimit( \imagick::RESOURCETYPE_MEMORY ) ) : 'not available' ),
							'imagick::RESOURCETYPE_THREAD' => ( defined( 'imagick::RESOURCETYPE_THREAD' ) ? $imagick->getResourceLimit( \imagick::RESOURCETYPE_THREAD ) : 'not available' ),
							'imagick::RESOURCETYPE_TIME'   => ( defined( 'imagick::RESOURCETYPE_TIME' ) ? $imagick->getResourceLimit( \imagick::RESOURCETYPE_TIME ) : 'not available' ),
						);

						$info['wp-media']['fields']['imagick_limits'] = array(
							'label' => __( 'Imagick Resource Limits' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
							'value' => $limits,
							'debug' => $limits_debug,
						);
					}
					if ( empty( $info['wp-media']['fields']['imagemagick_file_formats'] ) ) {
						try {
							$formats = \Imagick::queryFormats( '*' );
						} catch ( Exception $e ) {
							$formats = array();
						}

						$info['wp-media']['fields']['imagemagick_file_formats'] = array(
							'label' => __( 'ImageMagick supported file formats' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
							'value' => ( empty( $formats ) ) ? __( 'Unable to determine' ) : implode( ', ', $formats ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
							'debug' => ( empty( $formats ) ) ? 'Unable to determine' : implode( ', ', $formats ),
						);
					}
					// Then re-sort things back to their proper order...
					if ( ! empty( $info['wp-media']['fields']['gd_version'] ) ) {
						$gd_version = $info['wp-media']['fields']['gd_version'];
						unset( $info['wp-media']['fields']['gd_version'] );
						$info['wp-media']['fields']['gd_version'] = $gd_version;
					}
					if ( ! empty( $info['wp-media']['fields']['gd_formats'] ) ) {
						$gd_formats = $info['wp-media']['fields']['gd_formats'];
						unset( $info['wp-media']['fields']['gd_formats'] );
						$info['wp-media']['fields']['gd_formats'] = $gd_formats;
					}
					if ( ! empty( $info['wp-media']['fields']['ghostscript_version'] ) ) {
						$ghostscript_version = $info['wp-media']['fields']['ghostscript_version'];
						unset( $info['wp-media']['fields']['ghostscript_version'] );
						$info['wp-media']['fields']['ghostscript_version'] = $ghostscript_version;
					}
				}
			}
		}
		return $info;
	}

	/**
	 * Sync the cloud_mode property with the cloud_key option.
	 *
	 * @param mixed $old_setting The old value.
	 * @param mixed $new_setting The new value.
	 */
	public function updated_cloud_key( $old_setting, $new_setting ) {
		$this->cloud_mode = ! empty( $new_setting );
	}

	/**
	 * Flip the ewww_image_optimizer_jpg_only_mode option, if it isn't already set to the desired config.
	 *
	 * @param bool $new_value The value that should be set for JPG-only mode.
	 */
	public function toggle_jpg_only_mode( $new_value ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$current_value = (bool) $this->get_option( 'ewww_image_optimizer_jpg_only_mode' );
		if ( $new_value && ! $current_value ) {
			$this->set_option( 'ewww_image_optimizer_jpg_only_mode', 1 );
		} elseif ( ! $new_value && $current_value ) {
			$this->set_option( 'ewww_image_optimizer_jpg_only_mode', '' );
		}
		// Otherwise, JPG mode is already set to what it ought to be.
	}
}
