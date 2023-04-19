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
final class Plugin extends EIO_Base {
	/* Singleton */

	/**
	 * The one and only true EWWW_Plugin
	 *
	 * @var object|\EWWW\Plugin $instance
	 */
	private static $instance;

	/**
	 * SWIS GZIP object.
	 *
	 * @var object|\EWWW\HS_Beacon $hs_beacon
	 */
	public $hs_beacon;

	/**
	 * EWWW_Local object for handling local optimization tools/functions.
	 *
	 * @var object|\EWWW\Local $local
	 */
	public $local;

	/**
	 * Main EWWW_Plugin instance.
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

			self::$instance = new Plugin();
			self::$instance->debug_message( '<b>' . __METHOD__ . '()</b>' );
			// TODO: self::$instance->setup_constants()?

			self::$instance->requires();
			\add_action( 'admin_init', array( self::$instance, 'admin_init' ) );
			// TODO: check PHP and WP compat here.
			// TODO: include files here instead of in main plugin file?
			// TODO: setup anything that needs to run on init/plugins_loaded.
			// TODO: add any custom option hooks here.
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
		\_doing_it_wrong( __METHOD__, \esc_html__( 'Cannot clone core object.', 'ewww-image-optimizer' ), \esc_html( \EWWW_IMAGE_OPTIMIZER_VERSION ) );
	}

	/**
	 * Disable unserializing of the class.
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		\_doing_it_wrong( __METHOD__, \esc_html__( 'Cannot unserialize (wakeup) the core object.', 'ewww-image-optimizer' ), \esc_html( \EWWW_IMAGE_OPTIMIZER_VERSION ) );
	}

	/**
	 * Include required files.
	 *
	 * @access private
	 */
	private function requires() {
		// The various class extensions for background optimization.
		require_once( \EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewwwio-media-background-process.php' );
		// EWWW_Image class for working with queued images and image records from the database.
		require_once( \EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewww-image.php' );
		// EIO_Backup class for managing image backups.
		require_once( \EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-backup.php' );
		// HS_Beacon class for integrated help/docs.
		require_once( \EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-hs-beacon.php' );
		// EWWWIO_Tracking class for reporting anonymous site data.
		require_once( \EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewwwio-tracking.php' );
		// Used for manipulating exif info.
		if ( ! class_exists( '\lsolesen\pel\PelJpeg' ) ) {
			require_once( \EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'vendor/autoload.php' );
		}
	}

	/**
	 * Setup plugin for wp-admin.
	 */
	function admin_init() {
		self::$instance->hs_beacon = new HS_Beacon();
	}

	/**
	 * Set some default option values.
	 */
	function set_defaults() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Set defaults for all options that need to be autoloaded.
		\add_option( 'ewww_image_optimizer_noauto', false );
		\add_option( 'ewww_image_optimizer_disable_editor', false );
		\add_option( 'ewww_image_optimizer_debug', false );
		\add_option( 'ewww_image_optimizer_metadata_remove', true );
		\add_option( 'ewww_image_optimizer_jpg_level', '10' );
		\add_option( 'ewww_image_optimizer_png_level', '10' );
		\add_option( 'ewww_image_optimizer_gif_level', '10' );
		\add_option( 'ewww_image_optimizer_pdf_level', '0' );
		\add_option( 'ewww_image_optimizer_svg_level', '0' );
		\add_option( 'ewww_image_optimizer_jpg_quality', '' );
		\add_option( 'ewww_image_optimizer_webp_quality', '' );
		\add_option( 'ewww_image_optimizer_backup_files', '' );
		\add_option( 'ewww_image_optimizer_resize_existing', true );
		\add_option( 'ewww_image_optimizer_exactdn', false );
		\add_option( 'ewww_image_optimizer_exactdn_plan_id', 0 );
		\add_option( 'exactdn_all_the_things', true );
		\add_option( 'exactdn_lossy', true );
		\add_option( 'exactdn_exclude', '' );
		\add_option( 'exactdn_sub_folder', false );
		\add_option( 'exactdn_prevent_db_queries', true );
		\add_option( 'ewww_image_optimizer_lazy_load', false );
		\add_option( 'ewww_image_optimizer_use_siip', false );
		\add_option( 'ewww_image_optimizer_use_lqip', false );
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
		\add_site_option( 'ewww_image_optimizer_metadata_remove', true );
		\add_site_option( 'ewww_image_optimizer_jpg_level', '10' );
		\add_site_option( 'ewww_image_optimizer_png_level', '10' );
		\add_site_option( 'ewww_image_optimizer_gif_level', '10' );
		\add_site_option( 'ewww_image_optimizer_pdf_level', '0' );
		\add_site_option( 'ewww_image_optimizer_svg_level', '0' );
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
		\add_site_option( 'exactdn_sub_folder', false );
		\add_site_option( 'exactdn_prevent_db_queries', true );
		\add_site_option( 'ewww_image_optimizer_ll_autoscale', true );
	}

}

/**
 * Make sure the cloud constant is defined.
 *
 * Check to see if the cloud constant is defined (which would mean we've already run init) and set it properly if not.
 */
function ewww_image_optimizer_cloud_init() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if (
		! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) &&
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) &&
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 10 &&
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) > 10
	) {
		define( 'EWWW_IMAGE_OPTIMIZER_CLOUD', true );
	} elseif ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) ) {
		define( 'EWWW_IMAGE_OPTIMIZER_CLOUD', false );
	}
	ewwwio_memory( __FUNCTION__ );
}

/**
 * Initializes settings for the local tools, and runs the checks for tools on select pages.
 */
function ewww_image_optimizer_exec_init() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $exactdn;
	// If cloud is fully enabled, we're going to skip all the checks related to the bundled tools.
	if ( EWWW_IMAGE_OPTIMIZER_CLOUD ) {
		ewwwio_debug_message( 'cloud options enabled, shutting off binaries' );
		ewww_image_optimizer_disable_tools();
		return;
	}
	if (
		defined( 'WPCOMSH_VERSION' ) ||
		! empty( $_ENV['PANTHEON_ENVIRONMENT'] ) ||
		defined( 'WPE_PLUGIN_VERSION' ) ||
		defined( 'FLYWHEEL_CONFIG_DIR' ) ||
		defined( 'KINSTAMU_VERSION' ) ||
		defined( 'WPNET_INIT_PLUGIN_VERSION' )
	) {
		if (
			! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) &&
			( ! is_object( $exactdn ) || ! $exactdn->get_exactdn_domain() ) &&
			ewww_image_optimizer_get_option( 'ewww_image_optimizer_wizard_complete' )
		) {
			add_action( 'network_admin_notices', 'ewww_image_optimizer_notice_hosting_requires_api' );
			add_action( 'admin_notices', 'ewww_image_optimizer_notice_hosting_requires_api' );
		}
		if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_NOEXEC' ) ) {
			define( 'EWWW_IMAGE_OPTIMIZER_NOEXEC', true );
		}
		ewwwio_debug_message( 'WPE/wp.com/pantheon/flywheel site, disabling tools' );
		ewww_image_optimizer_disable_tools();
		return;
	}
	// Check if this is an unsupported OS (not Linux or Mac OSX or FreeBSD or Windows or SunOS).
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_wizard_complete' ) ) {
		if (
			! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) &&
			( ! is_object( $exactdn ) || ! $exactdn->get_exactdn_domain() ) &&
			ewww_image_optimizer_os_supported()
		) {
			add_action( 'load-media_page_ewww-image-optimizer-bulk', 'ewww_image_optimizer_tool_init' );
		}
		return;
	}
	if ( ! ewww_image_optimizer_os_supported() ) {
		// Call the function to display a notice.
		add_action( 'network_admin_notices', 'ewww_image_optimizer_notice_os' );
		add_action( 'admin_notices', 'ewww_image_optimizer_notice_os' );
		// Turn off all the tools.
		ewwwio_debug_message( 'unsupported OS, disabling tools: ' . PHP_OS );
		ewww_image_optimizer_disable_tools();
		return;
	}
	add_action( 'load-upload.php', 'ewww_image_optimizer_tool_init', 9 );
	add_action( 'load-media-new.php', 'ewww_image_optimizer_tool_init' );
	add_action( 'load-media_page_ewww-image-optimizer-bulk', 'ewww_image_optimizer_tool_init' );
	add_action( 'load-settings_page_ewww-image-optimizer-options', 'ewww_image_optimizer_tool_init' );
	add_action( 'load-plugins.php', 'ewww_image_optimizer_tool_init' );
	add_action( 'load-ims_gallery_page_ewww-ims-optimize', 'ewww_image_optimizer_tool_init' );
}

/**
 * Check if free mode is supported on this operating system.
 *
 * @return bool True if the PHP_OS is supported, false otherwise.
 */
function ewww_image_optimizer_os_supported() {
	$supported_oss = array(
		'Linux',
		'Darwin',
		'FreeBSD',
		'WINNT',
	);
	return in_array( PHP_OS, $supported_oss, true );
}

/**
 * Let the user know the plugin requires API/ExactDN to operate at their webhost.
 */
function ewww_image_optimizer_notice_hosting_requires_api() {
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		$settings_url = network_admin_url( 'settings.php?page=ewww-image-optimizer-options' );
	} else {
		$settings_url = admin_url( 'options-general.php?page=ewww-image-optimizer-options' );
	}
	if ( defined( 'WPCOMSH_VERSION' ) ) {
		$webhost = 'WordPress.com';
	} elseif ( ! empty( $_ENV['PANTHEON_ENVIRONMENT'] ) ) {
		$webhost = 'Pantheon';
	} elseif ( defined( 'WPE_PLUGIN_VERSION' ) ) {
		$webhost = 'WP Engine';
	} elseif ( defined( 'FLYWHEEL_CONFIG_DIR' ) ) {
		$webhost = 'Flywheel';
	} elseif ( defined( 'KINSTAMU_VERSION' ) ) {
		$webhost = 'Kinsta';
	} elseif ( defined( 'WPNET_INIT_PLUGIN_VERSION' ) ) {
		$webhost = 'WP NET';
	} else {
		return;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_dismiss_exec_notice' ) ) {
		return;
	}
	echo "<div id='ewww-image-optimizer-warning-exec' class='notice notice-warning is-dismissible'><p>" .
		/* translators: %s: Name of a web host, like WordPress.com or Pantheon. */
		sprintf( esc_html__( 'EWWW Image Optimizer cannot use server-based optimization on %s sites. Activate our premium service for 5x more compression, PNG/GIF/PDF compression, and image backups.', 'ewww-image-optimizer' ), esc_html( $webhost ) ) .
		'<br><strong>' .
		/* translators: %s: link to 'start your free trial' */
		sprintf( esc_html__( 'Dismiss this notice to continue with free cloud-based JPG compression or %s.', 'ewww-image-optimizer' ), "<a href='https://ewww.io/plans/'>" . esc_html__( 'start your premium trial', 'ewww-image-optimizer' ) . '</a>' );
	ewwwio_help_link( 'https://docs.ewww.io/article/29-what-is-exec-and-why-do-i-need-it', '592dd12d0428634b4a338c39' );
	echo '</strong></p></div>';
	?>
<script>
	jQuery(document).on('click', '#ewww-image-optimizer-warning-exec .notice-dismiss', function() {
		var ewww_dismiss_exec_data = {
			action: 'ewww_dismiss_exec_notice',
		};
		jQuery.post(ajaxurl, ewww_dismiss_exec_data, function(response) {
			if (response) {
				console.log(response);
			}
		});
	});
</script>
	<?php
}

/**
 * Tells the user they are on an unsupported operating system.
 */
function ewww_image_optimizer_notice_os() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_dismiss_exec_notice' ) ) {
		return;
	}
	if (
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ||
		ewww_image_optimizer_easy_active() ||
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_wizard_complete' )
	) {
		return;
	}
	echo "<div id='ewww-image-optimizer-warning-exec' class='notice notice-warning is-dismissible'><p>" .
		esc_html__( 'Free server-based compression with EWWW Image Optimizer is only supported on Linux, FreeBSD, Mac OSX, and Windows.', 'ewww-image-optimizer' ) .
		'<br><strong>' .
		/* translators: %s: link to 'start your free trial' */
		sprintf( esc_html__( 'Dismiss this notice to continue with free cloud-based JPG compression or %s.', 'ewww-image-optimizer' ), "<a href='https://ewww.io/plans/'>" . esc_html__( 'start your premium trial', 'ewww-image-optimizer' ) . '</a>' );
	ewwwio_help_link( 'https://docs.ewww.io/article/29-what-is-exec-and-why-do-i-need-it', '592dd12d0428634b4a338c39' );
	echo '</strong></p></div>';
	?>
<script>
	jQuery(document).on('click', '#ewww-image-optimizer-warning-exec .notice-dismiss', function() {
		var ewww_dismiss_exec_data = {
			action: 'ewww_dismiss_exec_notice',
		};
		jQuery.post(ajaxurl, ewww_dismiss_exec_data, function(response) {
			if (response) {
				console.log(response);
			}
		});
	});
</script>
	<?php
}
