<?php
/**
 * Common functions for Standard and Cloud plugins
 *
 * This file contains functions that are shared by both the regular EWWW IO plugin, and the
 * Cloud version. Functions that differ between the two are stored in the main
 * ewww-image-optimizer(-cloud).php file.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EWWW_IMAGE_OPTIMIZER_VERSION', '640.0' );

// Initialize a couple globals.
$eio_debug  = '';
$ewww_defer = true;

if ( WP_DEBUG && function_exists( 'memory_get_usage' ) ) {
	$ewww_memory = 'plugin load: ' . memory_get_usage( true ) . "\n";
}

// Setup custom $wpdb attribute for our image-tracking table.
global $wpdb;
if ( ! isset( $wpdb->ewwwio_images ) ) {
	$wpdb->ewwwio_images = $wpdb->prefix . 'ewwwio_images';
}
if ( ! isset( $wpdb->ewwwio_queue ) ) {
	$wpdb->ewwwio_queue = $wpdb->prefix . 'ewwwio_queue';
}

if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_RELATIVE' ) ) {
	define( 'EWWW_IMAGE_OPTIMIZER_RELATIVE', true );
}

// Used for manipulating exif info.
if ( ! class_exists( '\lsolesen\pel\PelJpeg' ) ) {
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'vendor/autoload.php' );
}
use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelTag;

/*
 * Hooks
 */

// Loads the plugin translations.
add_action( 'plugins_loaded', 'ewww_image_optimizer_preinit' );
// Runs any checks that need to run everywhere and early.
add_action( 'init', 'ewww_image_optimizer_init', 9 );
// Load our front-end parsers for ExactDN, Lazy Load and WebP.
add_action( 'init', 'ewww_image_optimizer_parser_init', 99 );
// Initializes the plugin for admin interactions, like saving network settings and scheduling cron jobs.
add_action( 'admin_init', 'ewww_image_optimizer_admin_init' );
// Check the current screen ID to see if temp debugging should still be enabled.
add_action( 'current_screen', 'ewww_image_optimizer_current_screen', 10, 1 );
// If automatic optimization is NOT disabled.
if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_noauto' ) ) {
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_DISABLE_EDITOR' ) || ! EWWW_IMAGE_OPTIMIZER_DISABLE_EDITOR ) {
		// Turns off the ewwwio_image_editor during uploads.
		add_action( 'add_attachment', 'ewww_image_optimizer_add_attachment' );
		// Turn off the editor when scaling down the original (core WP 5.3+).
		add_filter( 'big_image_size_threshold', 'ewww_image_optimizer_image_sizes' );
		// Turns off ewwwio_image_editor during Enable Media Replace.
		add_filter( 'emr_unfiltered_get_attached_file', 'ewww_image_optimizer_image_sizes' );
		// Checks to see if thumb regen or other similar operation is running via REST API.
		add_action( 'rest_api_init', 'ewww_image_optimizer_restapi_compat_check' );
		// Detect WP/LR Sync when it starts.
		add_action( 'wplr_presync_media', 'ewww_image_optimizer_image_sizes' );
		// Enables direct integration to the editor's save function.
		add_filter( 'wp_image_editors', 'ewww_image_optimizer_load_editor', 60 );
	}
	// Resizes and auto-rotates images.
	add_filter( 'wp_handle_upload', 'ewww_image_optimizer_handle_upload' );
	// Processes an image via the metadata after upload.
	add_filter( 'wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15, 2 );
	// Add hook for PTE confirmation to make sure new resizes are optimized.
	add_filter( 'wp_get_attachment_metadata', 'ewww_image_optimizer_pte_check' );
	// Resizes and auto-rotates MediaPress images.
	add_filter( 'mpp_handle_upload', 'ewww_image_optimizer_handle_mpp_upload' );
	// Processes a MediaPress image via the metadata after upload.
	add_filter( 'mpp_generate_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15, 2 );
	// Processes an attachment after IRSC has done a thumb regen.
	add_filter( 'sirsc_attachment_images_ready', 'ewww_image_optimizer_resize_from_meta_data', 15, 2 );
	// Process BuddyPress uploads from Vikinger theme.
	add_action( 'vikinger_file_uploaded', 'ewww_image_optimizer' );
}
// Skips resizing for images with 'noresize' in the filename.
add_filter( 'ewww_image_optimizer_resize_dimensions', 'ewww_image_optimizer_noresize', 10, 2 );
// Makes sure the optimizer never optimizes it's own testing images.
add_filter( 'ewww_image_optimizer_bypass', 'ewww_image_optimizer_ignore_self', 10, 2 );
// Adds a column to the media library list view to display optimization results.
add_filter( 'manage_media_columns', 'ewww_image_optimizer_columns' );
// Outputs the actual column information for each attachment.
add_action( 'manage_media_custom_column', 'ewww_image_optimizer_custom_column', 10, 2 );
// Filters to set default permissions, admins can override these if they wish.
add_filter( 'ewww_image_optimizer_manual_permissions', 'ewww_image_optimizer_manual_permissions', 8 );
add_filter( 'ewww_image_optimizer_bulk_permissions', 'ewww_image_optimizer_admin_permissions', 8 );
add_filter( 'ewww_image_optimizer_admin_permissions', 'ewww_image_optimizer_admin_permissions', 8 );
add_filter( 'ewww_image_optimizer_superadmin_permissions', 'ewww_image_optimizer_superadmin_permissions', 8 );
// Add a link to the plugins page so the user can go straight to the settings page.
add_filter( 'plugin_action_links_' . EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL, 'ewww_image_optimizer_settings_link' );
// Filter the registered sizes so we can remove any that the user disabled.
add_filter( 'intermediate_image_sizes_advanced', 'ewww_image_optimizer_image_sizes_advanced' );
// Ditto for PDF files (or anything non-image).
add_filter( 'fallback_intermediate_image_sizes', 'ewww_image_optimizer_fallback_sizes' );
// Processes screenshots imported with MyArcadePlugin.
add_filter( 'myarcade_filter_screenshot', 'ewww_image_optimizer_myarcade_thumbnail' );
// Processes thumbnails created by MyArcadePlugin.
add_filter( 'myarcade_filter_thumbnail', 'ewww_image_optimizer_myarcade_thumbnail' );
// This filter turns off ewwwio_image_editor during save from the actual image editor and ensures that we parse the resizes list during the image editor save function.
add_filter( 'load_image_to_edit_path', 'ewww_image_optimizer_editor_save_pre' );
// Allows the user to override the default JPG quality used by WordPress.
add_filter( 'jpeg_quality', 'ewww_image_optimizer_set_jpg_quality', PHP_INT_MAX - 1 );
// Allows the user to override the default WebP quality used by EWWW IO.
add_filter( 'webp_quality', 'ewww_image_optimizer_set_webp_quality' );
// Prevent WP from over-riding EWWW IO's resize settings.
add_filter( 'big_image_size_threshold', 'ewww_image_optimizer_adjust_big_image_threshold', 10, 3 );
// Makes sure the plugin bypasses any files affected by the Folders to Ignore setting.
add_filter( 'ewww_image_optimizer_bypass', 'ewww_image_optimizer_ignore_file', 10, 2 );
// Ensure we populate the queue with webp images for WP Offload S3.
add_filter( 'as3cf_attachment_file_paths', 'ewww_image_optimizer_as3cf_attachment_file_paths', 10, 2 );
// Make sure to remove webp images from remote storage when an attachment is deleted.
add_filter( 'as3cf_remove_attachment_paths', 'ewww_image_optimizer_as3cf_remove_attachment_file_paths', 10, 2 );
// Fix the ContentType for WP Offload S3 on WebP images.
add_filter( 'as3cf_object_meta', 'ewww_image_optimizer_as3cf_object_meta' );
// Get admin color scheme and save it for later.
add_action( 'admin_head', 'ewww_image_optimizer_save_admin_colors' );
// Legacy (non-AJAX) action hook for manually optimizing an image.
add_action( 'admin_action_ewww_image_optimizer_manual_optimize', 'ewww_image_optimizer_manual' );
// Legacy (non-AJAX) action hook for manually restoring a converted image.
add_action( 'admin_action_ewww_image_optimizer_manual_restore', 'ewww_image_optimizer_manual' );
// Legacy (non-AJAX) action hook for manually restoring a backup from the API.
add_action( 'admin_action_ewww_image_optimizer_manual_cloud_restore', 'ewww_image_optimizer_manual' );
// Cleanup routine when an attachment is deleted.
add_action( 'delete_attachment', 'ewww_image_optimizer_delete' );
// Cleanup db records when Enable Media Replace replaces a file.
add_action( 'wp_handle_replace', 'ewww_image_optimizer_media_replace' );
// Cleanup db records when Phoenix Media Rename is finished.
add_action( 'pmr_renaming_successful', 'ewww_image_optimizer_media_rename', 10, 2 );
// Cleanup db records when Image Regenerate & Select Crop deletes a file.
add_action( 'sirsc_image_file_deleted', 'ewww_image_optimizer_file_deleted', 10, 2 );
// Adds the EWWW IO pages to the admin menu.
add_action( 'admin_menu', 'ewww_image_optimizer_admin_menu', 60 );
// Adds the EWWW IO settings to the network admin menu.
add_action( 'network_admin_menu', 'ewww_image_optimizer_network_admin_menu' );
// Handle the bulk actions from the media library.
add_filter( 'handle_bulk_actions-upload', 'ewww_image_optimizer_bulk_action_handler', 10, 3 );
// Adds scripts to ajaxify the one-click actions on the media library, and register tooltips for conversion links.
add_action( 'admin_enqueue_scripts', 'ewww_image_optimizer_media_scripts' );
// Adds scripts for the EWWW IO settings page.
add_action( 'admin_enqueue_scripts', 'ewww_image_optimizer_settings_script' );
// Enables scheduled optimization via wp-cron.
add_action( 'ewww_image_optimizer_auto', 'ewww_image_optimizer_auto' );
// Correct records for files renamed by MFR.
add_action( 'mfrh_path_renamed', 'ewww_image_optimizer_path_renamed', 10, 3 );
// Correct any records in the table created during retina generation.
add_action( 'wr2x_retina_file_added', 'ewww_image_optimizer_retina', 20, 2 );
// Hook onto creation of new sites in multi-site for Easy IO registration.
add_action( 'wp_initialize_site', 'ewww_image_optimizer_initialize_site', 101, 1 );
// AJAX action hook to verify an API key.
add_action( 'wp_ajax_ewww_cloud_key_verify', 'ewww_image_optimizer_cloud_key_verify_ajax' );
// AJAX action hook to activate Easy IO.
add_action( 'wp_ajax_ewww_exactdn_activate', 'ewww_image_optimizer_exactdn_activate_ajax' );
// AJAX action hook to activate Easy IO on a specific site.
add_action( 'wp_ajax_ewww_exactdn_activate_site', 'ewww_image_optimizer_exactdn_activate_site_ajax' );
// AJAX action hook to register a specific site with Easy IO.
add_action( 'wp_ajax_ewww_exactdn_register_site', 'ewww_image_optimizer_exactdn_register_site_ajax' );
// AJAX action hook for inserting WebP rewrite rules into .htaccess.
add_action( 'wp_ajax_ewww_webp_rewrite', 'ewww_image_optimizer_webp_rewrite' );
// AJAX action hook for removing WebP rewrite rules from .htaccess.
add_action( 'wp_ajax_ewww_webp_unwrite', 'ewww_image_optimizer_webp_unwrite' );
// AJAX action hook for manually optimizing/converting an image.
add_action( 'wp_ajax_ewww_manual_optimize', 'ewww_image_optimizer_manual' );
// AJAX action hook for manually restoring a converted image.
add_action( 'wp_ajax_ewww_manual_restore', 'ewww_image_optimizer_manual' );
// AJAX action hook for manually restoring an attachment from backups on the API.
add_action( 'wp_ajax_ewww_manual_cloud_restore', 'ewww_image_optimizer_manual' );
// AJAX action hook for manually restoring a single image backup from the API.
add_action( 'wp_ajax_ewww_manual_cloud_restore_single', 'ewww_image_optimizer_cloud_restore_single_image_handler' );
// AJAX action hook to dismiss the WooCommerce regen notice.
add_action( 'wp_ajax_ewww_dismiss_wc_regen', 'ewww_image_optimizer_dismiss_wc_regen' );
// AJAX action hook to dismiss the WP/LR Sync regen notice.
add_action( 'wp_ajax_ewww_dismiss_lr_sync', 'ewww_image_optimizer_dismiss_lr_sync' );
// AJAX action hook to disable the media library notice.
add_action( 'wp_ajax_ewww_dismiss_media_notice', 'ewww_image_optimizer_dismiss_media_notice' );
// AJAX action hook to disable the 'review request' notice.
add_action( 'wp_ajax_ewww_dismiss_review_notice', 'ewww_image_optimizer_dismiss_review_notice' );
// Adds script to highlight mis-sized images on the front-end (for logged in admins only).
add_action( 'wp_head', 'ewww_image_optimizer_resize_detection_script' );
// Adds a button on the admin bar to allow highlighting mis-sized images on-demand.
add_action( 'admin_bar_init', 'ewww_image_optimizer_admin_bar_init' );
// Non-AJAX handler to delete the API key, and reroute back to the settings page.
add_action( 'admin_action_ewww_image_optimizer_remove_cloud_key', 'ewww_image_optimizer_remove_cloud_key' );
// Non-AJAX handler to disable Easy IO and reroute back to the settings page.
add_action( 'admin_action_ewww_image_optimizer_remove_easyio', 'ewww_image_optimizer_remove_easyio' );
// Non-AJAX handler to disable Easy IO network-wide.
add_action( 'admin_action_ewww_image_optimizer_network_remove_easyio', 'ewww_image_optimizer_network_remove_easyio' );
// Non-AJAX handler to enable Force WebP for GIF files.
add_action( 'admin_action_ewww_image_optimizer_enable_force_gif2webp', 'ewww_image_optimizer_enable_force_gif2webp' );
// Non-AJAX handler to retest async/background mode.
add_action( 'admin_action_ewww_image_optimizer_retest_background_optimization', 'ewww_image_optimizer_retest_background_optimization' );
// Non-AJAX handler to view the debug log, and display it.
add_action( 'admin_action_ewww_image_optimizer_view_debug_log', 'ewww_image_optimizer_view_debug_log' );
// Non-AJAX handler to delete the debug log, and reroute back to the settings page.
add_action( 'admin_action_ewww_image_optimizer_delete_debug_log', 'ewww_image_optimizer_delete_debug_log' );
// Non-AJAX handler to apply 6.2 current_timestamp db upgrade.
add_action( 'admin_action_ewww_image_optimizer_620_upgrade', 'ewww_image_optimizer_620_upgrade' );
// Check if WebP option was turned off and is now enabled.
add_filter( 'pre_update_option_ewww_image_optimizer_webp', 'ewww_image_optimizer_webp_maybe_enabled', 10, 2 );
// Check Scheduled Opt option has just been disabled and clear the queues/stop the process.
add_filter( 'pre_update_option_ewww_image_optimizer_auto', 'ewww_image_optimizer_scheduled_optimizaton_changed', 10, 2 );
// Makes sure to flush out any scheduled jobs on deactivation.
register_deactivation_hook( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE, 'ewww_image_optimizer_network_deactivate' );
// add_action( 'shutdown', 'ewwwio_memory_output' );.
// Makes sure we flush the debug info to the log on shutdown.
add_action( 'shutdown', 'ewww_image_optimizer_debug_log' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewwwio-cli.php' );
}
if ( 'done' !== get_option( 'ewww_image_optimizer_relative_migration_status' ) ) {
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewwwio-relative-migration.php' );
}
if ( defined( 'EWWW_IMAGE_OPTIMIZER_ALT_WEBP' ) && EWWW_IMAGE_OPTIMIZER_ALT_WEBP ) {
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-eio-alt-webp-weglot-alt.php' );
}

/**
 * Setup page parsing classes after theme functions.php is loaded and plugins have run init routines.
 */
function ewww_image_optimizer_parser_init() {
	$buffer_start = false;
	// If ExactDN is enabled.
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) && false === strpos( add_query_arg( null, null ), 'exactdn_disable=1' ) ) {
		if ( ! ewww_image_optimizer_get_option( 'exactdn_all_the_things' ) ) {
			// Un-configure Autoptimize CDN domain.
			if ( defined( 'AUTOPTIMIZE_PLUGIN_DIR' ) && strpos( ewww_image_optimizer_get_option( 'autoptimize_cdn_url' ), 'exactdn' ) ) {
				ewww_image_optimizer_set_option( 'autoptimize_cdn_url', '' );
			}
		}
		$buffer_start = true;
		/**
		 * Page Parsing class for working with HTML content.
		 */
		require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-eio-page-parser.php' );
		/**
		 * ExactDN class for parsing image urls and rewriting them.
		 */
		require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-exactdn.php' );
	} else {
		// Un-configure Autoptimize CDN domain.
		if ( defined( 'AUTOPTIMIZE_PLUGIN_DIR' ) && strpos( ewww_image_optimizer_get_option( 'autoptimize_cdn_url' ), 'exactdn' ) ) {
			ewww_image_optimizer_set_option( 'autoptimize_cdn_url', '' );
		}
	}
	// If Lazy Load is enabled.
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ) {
		$buffer_start = true;
		/**
		 * Page Parsing class for working with HTML content.
		 */
		require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-eio-page-parser.php' );
		/**
		 * Lazy Load class for parsing image urls and rewriting them to defer off-screen images.
		 */
		require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-eio-lazy-load.php' );

		global $eio_lazy_load;
		$eio_lazy_load = new EIO_Lazy_Load();
	}
	// If JS WebP Rewriting is enabled.
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		$buffer_start = true;
		/**
		 * Page Parsing class for working with HTML content.
		 */
		require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-eio-page-parser.php' );
		if ( defined( 'EWWW_IMAGE_OPTIMIZER_ALT_WEBP' ) && EWWW_IMAGE_OPTIMIZER_ALT_WEBP ) {
			/**
			 * Alt WebP class for parsing image urls and rewriting them for WebP support.
			 */
			require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-eio-alt-webp.php' );
		} else {
			/**
			 * JS WebP class for parsing image urls and rewriting them for WebP support.
			 */
			require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-eio-js-webp.php' );
		}
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_picture_webp' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		$buffer_start = true;
		/**
		 * Page Parsing class for working with HTML content.
		 */
		require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-eio-page-parser.php' );
		/**
		 * Picture WebP class for parsing img elements and rewriting them with WebP URLs.
		 */
		require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-eio-picture-webp.php' );
	}
	if ( $buffer_start ) {
		// Start an output buffer before any output starts.
		add_action( 'template_redirect', 'ewww_image_optimizer_buffer_start', 0 );
	}
}

/**
 * Starts an output buffer and registers the callback function to do WebP replacement.
 */
function ewww_image_optimizer_buffer_start() {
	ob_start( 'ewww_image_optimizer_filter_page_output' );
}

/**
 * Run the page through any registered EWWW IO filters.
 *
 * @param string $buffer The full HTML page generated since the output buffer was started.
 * @return string The altered buffer containing the full page with WebP images inserted.
 */
function ewww_image_optimizer_filter_page_output( $buffer ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	return apply_filters( 'ewww_image_optimizer_filter_page_output', $buffer );
}

/**
 * Skips optimization when a file is within EWWW IO's own folder.
 *
 * @param bool   $skip Defaults to false.
 * @param string $filename The name of the file about to be optimized.
 * @return bool True if the file is within the EWWW IO folder, unaltered otherwise.
 */
function ewww_image_optimizer_ignore_self( $skip, $filename ) {
	if ( 0 === strpos( $filename, EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH ) ) {
		return true;
	}
	return $skip;
}

/**
 * Gets the version number from a plugin file.
 *
 * @param string $plugin_file The path to the plugin's main file.
 * @return array With a single index, 'Version'.
 */
function ewww_image_optimizer_get_plugin_version( $plugin_file ) {
	$default_headers = array(
		'Version' => 'Version',
	);

	$plugin_data = get_file_data( $plugin_file, $default_headers, 'plugin' );

	return $plugin_data;
}

/**
 * Checks to see if some other lazy loader is active.
 *
 * @return bool True if third-party lazy load detected, false otherwise.
 */
function ewwwio_other_lazy_detected() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( class_exists( 'RocketLazyLoadPlugin\Plugin' ) || defined( 'ROCKET_LL_VERSION' ) ) {
		$rocketll_settings = get_option( 'rocket_lazyload_options' );
		if ( is_array( $rocketll_settings ) && ! empty( $rocketll_settings['images'] ) ) {
			ewwwio_debug_message( 'rocket lazy detected (standalone free plugin)' );
			return true;
		}
	}
	if ( class_exists( 'WP_Rocket\Plugin' ) || defined( 'WP_ROCKET_VERSION' ) ) {
		$rocket_settings = get_option( 'wp_rocket_settings' );
		if ( is_array( $rocket_settings ) && ! empty( $rocket_settings['lazyload'] ) ) {
			ewwwio_debug_message( 'WP Rocket with lazy detected' );
			return true;
		}
	}
	if ( class_exists( 'autoptimizeExtra' ) || defined( 'AUTOPTIMIZE_PLUGIN_VERSION' ) ) {
		$ao_extra = get_option( 'autoptimize_imgopt_settings' );
		if ( ! empty( $ao_extra['autoptimize_imgopt_checkbox_field_3'] ) ) {
			ewwwio_debug_message( 'Autoptimize lazy detected' );
			return true;
		}
	}
	if ( class_exists( 'SiteGround_Optimizer\Helper\Helper' ) && get_option( 'siteground_optimizer_lazyload_images' ) ) {
		ewwwio_debug_message( 'SG Optimizer lazy detected' );
		return true;
	}
	if ( class_exists( '\A3Rev\LazyLoad' ) || defined( 'A3_LAZY_VERSION' ) ) {
		ewwwio_debug_message( 'A3 lazy detected' );
		return true;
	}
	if ( class_exists( 'WpFastestCache' ) || defined( 'WPFC_WP_PLUGIN_DIR' ) ) {
		if ( ! empty( $GLOBALS['wp_fastest_cache_options']->wpFastestCacheLazyLoad ) ) {
			ewwwio_debug_message( 'WPFC lazy detected' );
			return true;
		}
	}
	if ( class_exists( '\W3TC\Dispatcher' ) || defined( 'W3TC_VERSION' ) ) {
		if ( method_exists( '\W3TC\Dispatcher', 'config' ) ) {
			$w3tc_config = \W3TC\Dispatcher::config();
			if ( method_exists( $w3tc_config, 'get_boolean' ) && $w3tc_config->get_boolean( 'lazyload.enabled' ) ) {
				ewwwio_debug_message( 'W3TC lazy detected' );
				return true;
			}
		}
	}
	if ( class_exists( 'Jetpack' ) && method_exists( 'Jetpack', 'is_module_active' ) && Jetpack::is_module_active( 'lazy-images' ) ) {
		ewwwio_debug_message( 'Jetpack lazy detected' );
		return true;
	}
	if ( class_exists( '\Automattic\Jetpack_Boost\Jetpack_Boost' ) ) {
		$jetpack_boost_config = get_option( 'jetpack_boost_config' );
		if ( ! empty( $jetpack_boost_config['lazy-images']['enabled'] ) ) {
			ewwwio_debug_message( 'Jetpack Boost lazy detected' );
			return true;
		}
	}
	return false;
}

/**
 * Checks to see if the WebP option from the Cache Enabler plugin is enabled.
 *
 * @return bool True if the WebP option for CE is enabled.
 */
function ewww_image_optimizer_ce_webp_enabled() {
	if ( class_exists( 'Cache_Enabler' ) ) {
		$ce_options = get_option( 'cache_enabler', array() );
		if ( ! empty( $ce_options['convert_image_urls_to_webp'] ) || ! empty( $ce_options['webp'] ) ) {
			ewwwio_debug_message( 'Cache Enabler WebP option enabled' );
			return true;
		}
	}
	return false;
}

/**
 * Checks to see if the WebP option from the SWIS Performance plugin is enabled.
 *
 * @return bool True if the WebP option for SWIS is enabled.
 */
function ewww_image_optimizer_swis_webp_enabled() {
	if ( function_exists( 'swis' ) && class_exists( '\SWIS\Cache' ) ) {
		$cache_settings = swis()->cache->get_settings();
		if ( swis()->settings->get_option( 'cache' ) && ! empty( $cache_settings['webp'] ) ) {
			ewwwio_debug_message( 'SWIS WebP option enabled' );
			return true;
		}
	}
	return false;
}

/**
 * Checks to see if there is a method available for WebP conversion.
 *
 * @return bool True if a WebP Convertor is available.
 */
function ewww_image_optimizer_webp_available() {
	return true; // Because API mode is the fallback and is free for everyone.
	if (
		defined( 'EWWW_IMAGE_OPTIMIZER_NOEXEC' ) && EWWW_IMAGE_OPTIMIZER_NOEXEC &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) &&
		! ewww_image_optimizer_imagick_supports_webp() &&
		! ewww_image_optimizer_gd_supports_webp()
	) {
		return false;
	}
	return true;
}

/**
 * Checks to see if the WebP rules from WPFC are enabled.
 *
 * @return bool True if the WebP rules from WPFC are found.
 */
function ewww_image_optimizer_wpfc_webp_enabled() {
	if ( class_exists( 'WpFastestCache' ) ) {
		$wpfc_abspath = get_home_path() . '.htaccess';
		ewwwio_debug_message( "looking for WPFC rules in $wpfc_abspath" );
		$wpfc_rules = ewwwio_extract_from_markers( $wpfc_abspath, 'WEBPWpFastestCache' );
		if ( empty( $wpfc_rules ) ) {
			$wpfc_abspath = ABSPATH . '.htaccess';
			ewwwio_debug_message( "looking for WPFC rules in $wpfc_abspath" );
			$wpfc_rules = ewwwio_extract_from_markers( $wpfc_abspath, 'WEBPWpFastestCache' );
		}
		if ( ! empty( $wpfc_rules ) ) {
			ewwwio_debug_message( 'WPFC webp rules enabled' );
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
				ewwwio_debug_message( 'removing htaccess webp to prevent ExactDN problems' );
				insert_with_markers( $wpfc_abspath, 'WEBPWpFastestCache', '' );
				return false;
			}
			return true;
		}
	}
	return false;
}

/**
 * Set default permissions for manual operations (single image optimize, convert, restore).
 *
 * @param string $permissions A valid WP capability level.
 * @return string Either the original value, unchanged, or the default capability level.
 */
function ewww_image_optimizer_manual_permissions( $permissions ) {
	if ( empty( $permissions ) ) {
		return 'edit_others_posts';
	}
	return $permissions;
}

/**
 * Set default permissions for admin (configuration) and bulk operations.
 *
 * @param string $permissions A valid WP capability level.
 * @return string Either the original value, unchanged, or the default capability level.
 */
function ewww_image_optimizer_admin_permissions( $permissions ) {
	if ( empty( $permissions ) ) {
		return 'activate_plugins';
	}
	return $permissions;
}

/**
 * Set default permissions for multisite/network admin (configuration) operations.
 *
 * @param string $permissions A valid WP capability level.
 * @return string Either the original value, unchanged, or the default capability level.
 */
function ewww_image_optimizer_superadmin_permissions( $permissions ) {
	if ( empty( $permissions ) ) {
		return 'manage_network_options';
	}
	return $permissions;
}

if ( ! function_exists( 'wp_getimagesize' ) ) {
	/**
	 * Stub for WP prior to 5.7.
	 *
	 * @param string $filename The file path.
	 * @return array|false Array of image information or false on failure.
	 */
	function wp_getimagesize( $filename ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors
		return @getimagesize( $filename );
	}
}

/**
 * Wrapper around json_encode to handle non-utf8 characters.
 *
 * @param mixed $value The value to encode to JSON.
 * @return string The JSON-encoded version of the value.
 */
function ewwwio_json_encode( $value ) {
	if ( is_string( $value ) && function_exists( 'utf8_encode' ) && ! seems_utf8( $value ) ) {
		$value = utf8_encode( $value );
	} elseif ( is_string( $value ) && ! seems_utf8( $value ) ) {
		$value = '';
	} elseif ( is_array( $value ) ) {
		$parsed_value = array();
		foreach ( $value as $key => $data ) {
			if ( is_string( $data ) && function_exists( 'utf8_encode' ) && ! seems_utf8( $data ) ) {
				$data = utf8_encode( $data );
			} elseif ( is_string( $data ) && ! seems_utf8( $data ) ) {
				$data = '';
			}
			$parsed_value[ $key ] = $data;
		}
		$value = $parsed_value;
	}
	return json_encode( $value );
}

/**
 * Find out if set_time_limit() is allowed
 */
function ewww_image_optimizer_stl_check() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( defined( 'EWWW_IMAGE_OPTIMIZER_DISABLE_STL' ) && EWWW_IMAGE_OPTIMIZER_DISABLE_STL ) {
		ewwwio_debug_message( 'stl disabled by user' );
		return false;
	}
	if ( function_exists( 'wp_is_ini_value_changeable' ) && ! wp_is_ini_value_changeable( 'max_execution_time' ) ) {
		ewwwio_debug_message( 'max_execution_time not configurable' );
		return false;
	}
	return ewww_image_optimizer_function_exists( 'set_time_limit' );
}

/**
 * Checks if a function is disabled or does not exist.
 *
 * @param string $function The name of a function to test.
 * @param bool   $debug Whether to output debugging.
 * @return bool True if the function is available, False if not.
 */
function ewww_image_optimizer_function_exists( $function, $debug = false ) {
	if ( function_exists( 'ini_get' ) ) {
		$disabled = @ini_get( 'disable_functions' );
		if ( $debug ) {
			ewwwio_debug_message( "disable_functions: $disabled" );
		}
	}
	if ( extension_loaded( 'suhosin' ) && function_exists( 'ini_get' ) ) {
		$suhosin_disabled = @ini_get( 'suhosin.executor.func.blacklist' );
		if ( $debug ) {
			ewwwio_debug_message( "suhosin_blacklist: $suhosin_disabled" );
		}
		if ( ! empty( $suhosin_disabled ) ) {
			$suhosin_disabled = explode( ',', $suhosin_disabled );
			$suhosin_disabled = array_map( 'trim', $suhosin_disabled );
			$suhosin_disabled = array_map( 'strtolower', $suhosin_disabled );
			if ( function_exists( $function ) && ! in_array( $function, $suhosin_disabled, true ) ) {
				return true;
			}
			return false;
		}
	}
	return function_exists( $function );
}

/**
 * Save the multi-site settings, if this is the WP admin, and they've been POSTed.
 */
function ewww_image_optimizer_save_network_settings() {
	if ( ! is_admin() ) {
		return;
	}
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		ewwwio_debug_message( 'saving network settings' );
		// Set the common network settings if they have been POSTed.
		if (
			! empty( $_REQUEST['_wpnonce'] ) &&
			wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' ) &&
			isset( $_POST['option_page'] ) &&
			false !== strpos( sanitize_text_field( wp_unslash( $_POST['option_page'] ) ), 'ewww_image_optimizer_options' ) &&
			current_user_can( 'manage_network_options' ) &&
			! get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) &&
			false === strpos( wp_get_referer(), 'options-general' )
		) {
			ewwwio_debug_message( 'network-wide settings, no override' );
			$ewww_image_optimizer_debug = ( empty( $_POST['ewww_image_optimizer_debug'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_debug', $ewww_image_optimizer_debug );
			$ewww_image_optimizer_metadata_remove = ( empty( $_POST['ewww_image_optimizer_metadata_remove'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_metadata_remove', $ewww_image_optimizer_metadata_remove );
			$ewww_image_optimizer_jpg_level = empty( $_POST['ewww_image_optimizer_jpg_level'] ) ? '' : (int) $_POST['ewww_image_optimizer_jpg_level'];
			update_site_option( 'ewww_image_optimizer_jpg_level', $ewww_image_optimizer_jpg_level );
			$ewww_image_optimizer_png_level = empty( $_POST['ewww_image_optimizer_png_level'] ) ? '' : (int) $_POST['ewww_image_optimizer_png_level'];
			update_site_option( 'ewww_image_optimizer_png_level', $ewww_image_optimizer_png_level );
			$ewww_image_optimizer_gif_level = empty( $_POST['ewww_image_optimizer_gif_level'] ) ? '' : (int) $_POST['ewww_image_optimizer_gif_level'];
			update_site_option( 'ewww_image_optimizer_gif_level', $ewww_image_optimizer_gif_level );
			$ewww_image_optimizer_pdf_level = empty( $_POST['ewww_image_optimizer_pdf_level'] ) ? '' : (int) $_POST['ewww_image_optimizer_pdf_level'];
			update_site_option( 'ewww_image_optimizer_pdf_level', $ewww_image_optimizer_pdf_level );
			$ewww_image_optimizer_svg_level = empty( $_POST['ewww_image_optimizer_svg_level'] ) ? '' : (int) $_POST['ewww_image_optimizer_svg_level'];
			update_site_option( 'ewww_image_optimizer_svg_level', $ewww_image_optimizer_svg_level );
			$ewww_image_optimizer_delete_originals = ( empty( $_POST['ewww_image_optimizer_delete_originals'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_delete_originals', $ewww_image_optimizer_delete_originals );
			$ewww_image_optimizer_jpg_to_png = ( empty( $_POST['ewww_image_optimizer_jpg_to_png'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_jpg_to_png', $ewww_image_optimizer_jpg_to_png );
			$ewww_image_optimizer_png_to_jpg = ( empty( $_POST['ewww_image_optimizer_png_to_jpg'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_png_to_jpg', $ewww_image_optimizer_png_to_jpg );
			$ewww_image_optimizer_gif_to_png = ( empty( $_POST['ewww_image_optimizer_gif_to_png'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_gif_to_png', $ewww_image_optimizer_gif_to_png );
			$ewww_image_optimizer_webp = ( empty( $_POST['ewww_image_optimizer_webp'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_webp', $ewww_image_optimizer_webp );
			$ewww_image_optimizer_jpg_background = empty( $_POST['ewww_image_optimizer_jpg_background'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['ewww_image_optimizer_jpg_background'] ) );
			update_site_option( 'ewww_image_optimizer_jpg_background', ewww_image_optimizer_jpg_background( $ewww_image_optimizer_jpg_background ) );
			$ewww_image_optimizer_jpg_quality = empty( $_POST['ewww_image_optimizer_jpg_quality'] ) ? '' : (int) $_POST['ewww_image_optimizer_jpg_quality'];
			update_site_option( 'ewww_image_optimizer_jpg_quality', ewww_image_optimizer_jpg_quality( $ewww_image_optimizer_jpg_quality ) );
			$ewww_image_optimizer_webp_quality = empty( $_POST['ewww_image_optimizer_webp_quality'] ) ? '' : (int) $_POST['ewww_image_optimizer_webp_quality'];
			update_site_option( 'ewww_image_optimizer_webp_quality', ewww_image_optimizer_webp_quality( $ewww_image_optimizer_webp_quality ) );
			$ewww_image_optimizer_disable_convert_links = ( empty( $_POST['ewww_image_optimizer_disable_convert_links'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_disable_convert_links', $ewww_image_optimizer_disable_convert_links );
			$ewww_image_optimizer_backup_files = ( empty( $_POST['ewww_image_optimizer_backup_files'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_backup_files', $ewww_image_optimizer_backup_files );
			$ewww_image_optimizer_auto = ( empty( $_POST['ewww_image_optimizer_auto'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_auto', $ewww_image_optimizer_auto );
			$ewww_image_optimizer_aux_paths = empty( $_POST['ewww_image_optimizer_aux_paths'] ) ? '' : sanitize_textarea_field( wp_unslash( $_POST['ewww_image_optimizer_aux_paths'] ) );
			update_site_option( 'ewww_image_optimizer_aux_paths', ewww_image_optimizer_aux_paths_sanitize( $ewww_image_optimizer_aux_paths ) );
			$ewww_image_optimizer_exclude_paths = empty( $_POST['ewww_image_optimizer_exclude_paths'] ) ? '' : sanitize_textarea_field( wp_unslash( $_POST['ewww_image_optimizer_exclude_paths'] ) );
			update_site_option( 'ewww_image_optimizer_exclude_paths', ewww_image_optimizer_exclude_paths_sanitize( $ewww_image_optimizer_exclude_paths ) );
			$ewww_image_optimizer_enable_cloudinary = ( empty( $_POST['ewww_image_optimizer_enable_cloudinary'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_enable_cloudinary', $ewww_image_optimizer_enable_cloudinary );
			$exactdn_all_the_things = ( empty( $_POST['exactdn_all_the_things'] ) ? false : true );
			update_site_option( 'exactdn_all_the_things', $exactdn_all_the_things );
			$exactdn_lossy = ( empty( $_POST['exactdn_lossy'] ) ? false : true );
			update_site_option( 'exactdn_lossy', $exactdn_lossy );
			$exactdn_exclude = empty( $_POST['exactdn_exclude'] ) ? '' : sanitize_textarea_field( wp_unslash( $_POST['exactdn_exclude'] ) );
			update_site_option( 'exactdn_exclude', ewww_image_optimizer_exclude_paths_sanitize( $exactdn_exclude ) );
			$ewww_image_optimizer_add_missing_dims = ( empty( $_POST['ewww_image_optimizer_add_missing_dims'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_add_missing_dims', $ewww_image_optimizer_add_missing_dims );
			$ewww_image_optimizer_lazy_load = ( empty( $_POST['ewww_image_optimizer_lazy_load'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_lazy_load', $ewww_image_optimizer_lazy_load );
			$ewww_image_optimizer_ll_autoscale = ( empty( $_POST['ewww_image_optimizer_ll_autoscale'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_ll_autoscale', $ewww_image_optimizer_ll_autoscale );
			$ewww_image_optimizer_use_lqip = ( empty( $_POST['ewww_image_optimizer_use_lqip'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_use_lqip', $ewww_image_optimizer_use_lqip );
			$ewww_image_optimizer_ll_all_things = empty( $_POST['ewww_image_optimizer_ll_all_things'] ) ? '' : sanitize_textarea_field( wp_unslash( $_POST['ewww_image_optimizer_ll_all_things'] ) );
			update_site_option( 'ewww_image_optimizer_ll_all_things', ewww_image_optimizer_exclude_paths_sanitize( $ewww_image_optimizer_ll_all_things ) );
			$ewww_image_optimizer_ll_exclude = empty( $_POST['ewww_image_optimizer_ll_exclude'] ) ? '' : sanitize_textarea_field( wp_unslash( $_POST['ewww_image_optimizer_ll_exclude'] ) );
			update_site_option( 'ewww_image_optimizer_ll_exclude', ewww_image_optimizer_exclude_paths_sanitize( $ewww_image_optimizer_ll_exclude ) );
			$ewww_image_optimizer_maxmediawidth = empty( $_POST['ewww_image_optimizer_maxmediawidth'] ) ? 0 : (int) $_POST['ewww_image_optimizer_maxmediawidth'];
			update_site_option( 'ewww_image_optimizer_maxmediawidth', $ewww_image_optimizer_maxmediawidth );
			$ewww_image_optimizer_maxmediaheight = empty( $_POST['ewww_image_optimizer_maxmediaheight'] ) ? 0 : (int) $_POST['ewww_image_optimizer_maxmediaheight'];
			update_site_option( 'ewww_image_optimizer_maxmediaheight', $ewww_image_optimizer_maxmediaheight );
			$ewww_image_optimizer_resize_detection = ( empty( $_POST['ewww_image_optimizer_resize_detection'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_resize_detection', $ewww_image_optimizer_resize_detection );
			$ewww_image_optimizer_resize_existing = ( empty( $_POST['ewww_image_optimizer_resize_existing'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_resize_existing', $ewww_image_optimizer_resize_existing );
			$ewww_image_optimizer_resize_other_existing = ( empty( $_POST['ewww_image_optimizer_resize_other_existing'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_resize_other_existing', $ewww_image_optimizer_resize_other_existing );
			$ewww_image_optimizer_parallel_optimization = ( empty( $_POST['ewww_image_optimizer_parallel_optimization'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_parallel_optimization', $ewww_image_optimizer_parallel_optimization );
			$ewww_image_optimizer_include_media_paths = ( empty( $_POST['ewww_image_optimizer_include_media_paths'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_include_media_paths', $ewww_image_optimizer_include_media_paths );
			$ewww_image_optimizer_include_originals = ( empty( $_POST['ewww_image_optimizer_include_originals'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_include_originals', $ewww_image_optimizer_include_originals );
			$ewww_image_optimizer_webp_for_cdn = ( empty( $_POST['ewww_image_optimizer_webp_for_cdn'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_webp_for_cdn', $ewww_image_optimizer_webp_for_cdn );
			$ewww_image_optimizer_picture_webp = ( empty( $_POST['ewww_image_optimizer_picture_webp'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_picture_webp', $ewww_image_optimizer_picture_webp );
			$ewww_image_optimizer_webp_rewrite_exclude = empty( $_POST['ewww_image_optimizer_webp_rewrite_exclude'] ) ? '' : sanitize_textarea_field( wp_unslash( $_POST['ewww_image_optimizer_webp_rewrite_exclude'] ) );
			update_site_option( 'ewww_image_optimizer_webp_rewrite_exclude', ewww_image_optimizer_exclude_paths_sanitize( $ewww_image_optimizer_webp_rewrite_exclude ) );
			$ewww_image_optimizer_webp_force = ( empty( $_POST['ewww_image_optimizer_webp_force'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_webp_force', $ewww_image_optimizer_webp_force );
			$ewww_image_optimizer_webp_paths = ( empty( $_POST['ewww_image_optimizer_webp_paths'] ) ? '' : sanitize_textarea_field( wp_unslash( $_POST['ewww_image_optimizer_webp_paths'] ) ) );
			update_site_option( 'ewww_image_optimizer_webp_paths', ewww_image_optimizer_webp_paths_sanitize( $ewww_image_optimizer_webp_paths ) );
			$ewww_image_optimizer_allow_multisite_override = empty( $_POST['ewww_image_optimizer_allow_multisite_override'] ) ? false : true;
			update_site_option( 'ewww_image_optimizer_allow_multisite_override', $ewww_image_optimizer_allow_multisite_override );
			$ewww_image_optimizer_enable_help = empty( $_POST['ewww_image_optimizer_enable_help'] ) ? false : true;
			update_site_option( 'ewww_image_optimizer_enable_help', $ewww_image_optimizer_enable_help );
			global $ewwwio_tracking;
			$ewww_image_optimizer_allow_tracking = empty( $_POST['ewww_image_optimizer_allow_tracking'] ) ? false : $ewwwio_tracking->check_for_settings_optin( (bool) $_POST['ewww_image_optimizer_allow_tracking'] );
			update_site_option( 'ewww_image_optimizer_allow_tracking', $ewww_image_optimizer_allow_tracking );
			add_action( 'network_admin_notices', 'ewww_image_optimizer_network_settings_saved' );
		} elseif (
			isset( $_POST['ewww_image_optimizer_allow_multisite_override_active'] ) &&
			current_user_can( 'manage_network_options' ) &&
			isset( $_REQUEST['_wpnonce'] ) &&
			wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' )
		) {
			ewwwio_debug_message( 'network-wide settings, single-site overriding' );
			$ewww_image_optimizer_allow_multisite_override = empty( $_POST['ewww_image_optimizer_allow_multisite_override'] ) ? false : true;
			update_site_option( 'ewww_image_optimizer_allow_multisite_override', $ewww_image_optimizer_allow_multisite_override );
			global $ewwwio_tracking;
			$ewww_image_optimizer_allow_tracking = empty( $_POST['ewww_image_optimizer_allow_tracking'] ) ? false : $ewwwio_tracking->check_for_settings_optin( (bool) $_POST['ewww_image_optimizer_allow_tracking'] );
			update_site_option( 'ewww_image_optimizer_allow_tracking', $ewww_image_optimizer_allow_tracking );
			add_action( 'network_admin_notices', 'ewww_image_optimizer_network_settings_saved' );
		} // End if().
	} // End if().
	if ( is_multisite() && get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_svg_level' )
	) {
		ewww_image_optimizer_set_defaults();
		update_option( 'ewww_image_optimizer_disable_pngout', true );
		update_option( 'ewww_image_optimizer_disable_svgcleaner', true );
		update_option( 'ewww_image_optimizer_optipng_level', 2 );
		update_option( 'ewww_image_optimizer_pngout_level', 2 );
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_option( 'ewww_image_optimizer_jpg_level', '10' );
		update_option( 'ewww_image_optimizer_png_level', '10' );
		update_option( 'ewww_image_optimizer_gif_level', '10' );
		update_option( 'ewww_image_optimizer_svg_level', 0 );
	}
}

/**
 * Runs on 'plugins_loaded' to make make sure the language files are loaded early.
 */
function ewww_image_optimizer_preinit() {
	load_plugin_textdomain( 'ewww-image-optimizer', false, EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'languages/' );
}

/**
 * Runs early for checks that need to happen on init before anything else.
 */
function ewww_image_optimizer_init() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );

	// Check to see if this is the settings page and enable debugging temporarily if it is.
	global $ewwwio_temp_debug;
	$ewwwio_temp_debug = ! empty( $ewwwio_temp_debug ) ? $ewwwio_temp_debug : false;
	if ( is_admin() && ! wp_doing_ajax() ) {
		if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
			$ewwwio_temp_debug = true;
		}
	}

	// For the settings page, check for the enable-local param and take appropriate action.
	if ( ! empty( $_GET['enable-local'] ) && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' ) ) {
		update_option( 'ewww_image_optimizer_local_mode', true );
		update_site_option( 'ewww_image_optimizer_local_mode', true );
	} elseif ( isset( $_GET['enable-local'] ) && ! (bool) $_GET['enable-local'] && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' ) ) {
		delete_option( 'ewww_image_optimizer_local_mode', true );
		delete_site_option( 'ewww_image_optimizer_local_mode', true );
	}
	if ( ! empty( $_GET['complete_wizard'] ) && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' ) ) {
		update_option( 'ewww_image_optimizer_wizard_complete', true, false );
	}
	if ( ! empty( $_GET['uncomplete_wizard'] ) && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' ) ) {
		update_option( 'ewww_image_optimizer_wizard_complete', false, false );
	}
	if ( class_exists( 'S3_Uploads' ) || class_exists( 'S3_Uploads\Plugin' ) ) {
		ewwwio_debug_message( 's3-uploads detected, deferring resize_upload' );
		add_filter( 'ewww_image_optimizer_defer_resizing', '__return_true' );
	}

	$active_plugins = get_option( 'active_plugins' );
	if ( is_multisite() && is_array( $active_plugins ) ) {
		$sitewide_plugins = get_site_option( 'active_sitewide_plugins' );
		if ( is_array( $sitewide_plugins ) ) {
			$active_plugins = array_merge( $active_plugins, array_flip( $sitewide_plugins ) );
		}
	}
	if ( ewww_image_optimizer_iterable( $active_plugins ) ) {
		ewwwio_debug_message( 'checking active plugins' );
		foreach ( $active_plugins as $active_plugin ) {
			if ( strpos( $active_plugin, '/nggallery.php' ) || strpos( $active_plugin, '\nggallery.php' ) ) {
				$ngg = ewww_image_optimizer_get_plugin_version( trailingslashit( WP_PLUGIN_DIR ) . $active_plugin );
				// Include the file that loads the nextgen gallery optimization functions.
				ewwwio_debug_message( 'Nextgen version: ' . $ngg['Version'] );
				if ( 1 < intval( substr( $ngg['Version'], 0, 1 ) ) ) { // For Nextgen 2+ support.
					$nextgen_major_version = substr( $ngg['Version'], 0, 1 );
					ewwwio_debug_message( "loading nextgen $nextgen_major_version support for $active_plugin" );
					require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewww-nextgen.php' );
				} else {
					preg_match( '/\d+\.\d+\.(\d+)/', $ngg['Version'], $nextgen_minor_version );
					if ( ! empty( $nextgen_minor_version[1] ) && $nextgen_minor_version[1] < 14 ) {
						ewwwio_debug_message( "NOT loading nextgen legacy support for $active_plugin" );
					} elseif ( ! empty( $nextgen_minor_version[1] ) && $nextgen_minor_version[1] > 13 ) {
						ewwwio_debug_message( "loading nextcellent support for $active_plugin" );
						require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewww-nextcellent.php' );
					}
				}
			}
			if ( strpos( $active_plugin, '/flag.php' ) || strpos( $active_plugin, '\flag.php' ) ) {
				ewwwio_debug_message( "loading flagallery support for $active_plugin" );
				// Include the file that loads the grand flagallery optimization functions.
				require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewww-flag.php' );
			}
		}
	}
	if ( defined( 'DOING_WPLR_REQUEST' ) && DOING_WPLR_REQUEST ) {
		// Unhook all automatic processing, and save an option that (does not autoload) tells the user LR Sync regenerated their images and they should run the bulk optimizer.
		remove_filter( 'wp_image_editors', 'ewww_image_optimizer_load_editor', 60 );
		remove_filter( 'wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15 );
		add_action( 'wplr_add_media', 'ewww_image_optimizer_lr_sync_update' );
		add_action( 'wplr_update_media', 'ewww_image_optimizer_lr_sync_update' );
		add_filter( 'ewww_image_optimizer_allowed_reopt', '__return_true' );
	}
}

/**
 * Plugin upgrade function
 *
 * @global object $wpdb
 */
function ewww_image_optimizer_upgrade() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewwwio_upgrading;
	$ewwwio_upgrading = false;
	if ( get_option( 'ewww_image_optimizer_version' ) < EWWW_IMAGE_OPTIMIZER_VERSION ) {
		if ( wp_doing_ajax() ) {
			return;
		}
		$ewwwio_upgrading = true;
		ewww_image_optimizer_enable_background_optimization();
		ewww_image_optimizer_install_table();
		ewww_image_optimizer_set_defaults();
		// This will get re-enabled if things are too slow.
		ewww_image_optimizer_set_option( 'exactdn_prevent_db_queries', false );
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn_verify_method' ) > 0 ) {
			delete_option( 'ewww_image_optimizer_exactdn_verify_method' );
			delete_site_option( 'ewww_image_optimizer_exactdn_verify_method' );
		}
		if ( get_option( 'ewww_image_optimizer_version' ) < 297.5 ) {
			// Cleanup background test mess.
			wp_clear_scheduled_hook( 'wp_ewwwio_test_optimize_cron' );
			global $wpdb;

			if ( is_multisite() ) {
				$wpdb->query( "DELETE FROM $wpdb->sitemeta WHERE meta_key LIKE 'wp_ewwwio_test_optimize_batch_%'" );
			}

			$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'wp_ewwwio_test_optimize_batch_%'" );

		}
		if ( ! get_option( 'ewww_image_optimizer_version' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
			add_option( 'exactdn_never_been_active', true, '', false );
		}
		if ( get_option( 'ewww_image_optimizer_version' ) < 280 ) {
			ewww_image_optimizer_migrate_settings_to_levels();
		}
		if ( get_option( 'ewww_image_optimizer_version' ) > 0 && get_option( 'ewww_image_optimizer_version' ) < 434 && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpegtran_copy' ) ) {
			ewww_image_optimizer_set_option( 'ewww_image_optimizer_metadata_remove', false );
		}
		if ( get_option( 'ewww_image_optimizer_version' ) < 454 ) {
			update_option( 'ewww_image_optimizer_bulk_resume', '' );
			update_option( 'ewww_image_optimizer_aux_resume', '' );
			ewww_image_optimizer_delete_pending();

			// Make sure some of our options are not autoloaded (since they can be huge).
			$bulk_attachments = get_option( 'ewww_image_optimizer_flag_attachments', '' );
			delete_option( 'ewww_image_optimizer_flag_attachments' );
			add_option( 'ewww_image_optimizer_flag_attachments', $bulk_attachments, '', 'no' );
			$bulk_attachments = get_option( 'ewww_image_optimizer_ngg_attachments', '' );
			delete_option( 'ewww_image_optimizer_ngg_attachments' );
			add_option( 'ewww_image_optimizer_ngg_attachments', $bulk_attachments, '', 'no' );
		}
		if ( get_option( 'ewww_image_optimizer_version' ) < 530 ) {
			ewww_image_optimizer_migrate_option_queue_to_table();
		}
		if ( get_option( 'ewww_image_optimizer_version' ) < 550 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
			ewww_image_optimizer_set_option( 'ewww_image_optimizer_force_gif2webp', false );
		} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_force_gif2webp' ) ) {
			ewww_image_optimizer_set_option( 'ewww_image_optimizer_force_gif2webp', true );
		}
		if (
			get_option( 'ewww_image_optimizer_version' ) <= 601.0 &&
			PHP_OS !== 'WINNT' &&
			ewwwio_is_file( EWWW_IMAGE_OPTIMIZER_TOOL_PATH . '/pngout-static' ) &&
			is_writable( EWWW_IMAGE_OPTIMIZER_TOOL_PATH . '/pngout-static' )
		) {
			ewwwio_debug_message( 'removing old version of pngout' );
			ewwwio_delete_file( EWWW_IMAGE_OPTIMIZER_TOOL_PATH . '/pngout-static' );
		}
		if ( get_option( 'ewww_image_optimizer_version' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_review_time' ) ) {
			$review_time = rand( time(), time() + 51 * DAY_IN_SECONDS );
			add_option( 'ewww_image_optimizer_review_time', $review_time, '', false );
			add_site_option( 'ewww_image_optimizer_review_time', $review_time );
		} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_review_time' ) ) {
			$review_time = time() + 7 * DAY_IN_SECONDS;
			add_option( 'ewww_image_optimizer_review_time', $review_time, '', false );
			add_site_option( 'ewww_image_optimizer_review_time', $review_time );
		}
		if ( get_option( 'ewww_image_optimizer_version' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_wizard_complete' ) ) {
			add_option( 'ewww_image_optimizer_wizard_complete', true, '', false );
			add_site_option( 'ewww_image_optimizer_wizard_complete', true );
		}
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
			if ( 'external' === get_option( 'elementor_css_print_method' ) ) {
				update_option( 'elementor_css_print_method', 'internal' );
			}
			if ( function_exists( 'et_get_option' ) && function_exists( 'et_update_option' ) && 'on' === et_get_option( 'et_pb_static_css_file', 'on' ) ) {
				et_update_option( 'et_pb_static_css_file', 'off' );
				et_update_option( 'et_pb_css_in_footer', 'off' );
			}
		}
		ewww_image_optimizer_remove_obsolete_settings();
		update_option( 'ewww_image_optimizer_version', EWWW_IMAGE_OPTIMIZER_VERSION );
	}
	ewwwio_memory( __FUNCTION__ );
}

/**
 * Tests background optimization.
 *
 * Send a known packet to admin-ajax.php via the EWWWIO_Test_Async_Handler class.
 *
 * @global object An instance of the test async class.
 */
function ewww_image_optimizer_enable_background_optimization() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ewww_image_optimizer_detect_wpsf_location_lock() ) {
		return;
	}
	global $ewwwio_test_async;
	if ( ! class_exists( 'WP_Background_Process' ) ) {
		require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
	}
	if ( ! is_object( $ewwwio_test_async ) ) {
		$ewwwio_test_async = new EWWWIO_Test_Async_Handler();
	}
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_background_optimization', false );
	ewwwio_debug_message( 'running test async handler' );
	$ewwwio_test_async->data( array( 'ewwwio_test_verify' => '949c34123cf2a4e4ce2f985135830df4a1b2adc24905f53d2fd3f5df5b162932' ) )->dispatch();
	ewww_image_optimizer_debug_log();
}

/**
 * Re-tests background optimization at a user's request.
 */
function ewww_image_optimizer_retest_background_optimization() {
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	ewww_image_optimizer_enable_background_optimization();
	sleep( 10 );
	$sendback = wp_get_referer();
	wp_safe_redirect( $sendback );
	exit;
}

/**
 * Apply 6.2.0+ current_timestamp db upgrade.
 */
function ewww_image_optimizer_620_upgrade() {
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	delete_transient( 'ewww_image_optimizer_620_upgrade_needed' );
	global $wpdb;
	$suppress = $wpdb->suppress_errors();
	$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images MODIFY updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP" );
	$wpdb->suppress_errors( $suppress );
	wp_safe_redirect( wp_get_referer() );
	exit;
}

/**
 * Plugin initialization for admin area.
 *
 * Saves settings when run network-wide, registers all 'common' settings, schedules wp-cron tasks,
 * includes necessary files for bulk operations, runs tool initialization, and ensures
 * compatibility with AJAX calls from other media generation plugins.
 */
function ewww_image_optimizer_admin_init() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_memory( __FUNCTION__ );
	/**
	 * EWWWIO_HS_Beacon class for embedding the HelpScout Beacon.
	 */
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-eio-hs-beacon.php' );
	/**
	 * Require the file that does the bulk processing.
	 */
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'bulk.php' );
	/**
	 * Require the files that contain functions for the images table and bulk processing images outside the library.
	 */
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'aux-optimize.php' );
	/**
	 * Require the files that migrate WebP images from extension replacement to extension appending.
	 */
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'mwebp.php' );
	ewww_image_optimizer_cloud_init();
	ewww_image_optimizer_upgrade();

	// Do settings validation for multi-site.
	ewww_image_optimizer_save_network_settings();

	// Register all the common EWWW IO settings.
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_debug', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_metadata_remove', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_level', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_png_level', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_gif_level', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_pdf_level', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_svg_level', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_backup_files', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_enable_cloudinary', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_quality', 'ewww_image_optimizer_jpg_quality' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_webp_quality', 'ewww_image_optimizer_webp_quality' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_parallel_optimization', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_auto', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_include_media_paths', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_include_originals', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_aux_paths', 'ewww_image_optimizer_aux_paths_sanitize' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_exclude_paths', 'ewww_image_optimizer_exclude_paths_sanitize' );
	global $ewwwio_tracking;
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_allow_tracking', array( $ewwwio_tracking, 'check_for_settings_optin' ) );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_enable_help', 'boolval' );
	/* register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_exactdn', 'boolval' ); */
	register_setting( 'ewww_image_optimizer_options', 'exactdn_all_the_things', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'exactdn_lossy', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'exactdn_exclude', 'ewww_image_optimizer_exclude_paths_sanitize' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_add_missing_dims', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_lazy_load', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_ll_autoscale', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_use_lqip', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_ll_all_things', 'sanitize_text_field' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_ll_exclude', 'ewww_image_optimizer_exclude_paths_sanitize' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_resize_detection', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_maxmediawidth', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_maxmediaheight', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_resize_existing', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_resize_other_existing', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_disable_resizes', 'ewww_image_optimizer_disable_resizes_sanitize' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_disable_resizes_opt', 'ewww_image_optimizer_disable_resizes_sanitize' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_disable_convert_links', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_delete_originals', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_to_png', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_png_to_jpg', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_gif_to_png', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_background', 'ewww_image_optimizer_jpg_background' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_webp', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_webp_force', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_webp_paths', 'ewww_image_optimizer_webp_paths_sanitize' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_webp_for_cdn', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_picture_webp', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_webp_rewrite_exclude', 'ewww_image_optimizer_exclude_paths_sanitize' );
	ewww_image_optimizer_exec_init();
	ewww_image_optimizer_cron_setup( 'ewww_image_optimizer_auto' );
	// Queue the function that contains custom styling for our progressbars.
	add_action( 'admin_enqueue_scripts', 'ewww_image_optimizer_progressbar_style' );
	if ( false !== strpos( add_query_arg( null, null ), 'site-new.php' ) ) {
		if ( is_multisite() && is_network_admin() && isset( $_GET['update'] ) && 'added' === $_GET['update'] && ! empty( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			add_action( 'network_admin_notices', 'ewww_image_optimizer_easyio_site_initialized' );
			add_action( 'admin_notices', 'ewww_image_optimizer_easyio_site_initialized' );
		}
	}
	if ( defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD_KEY' ) && get_option( 'ewww_image_optimizer_cloud_key_invalid' ) ) {
		add_action( 'network_admin_notices', 'ewww_image_optimizer_notice_invalid_key' );
		add_action( 'admin_notices', 'ewww_image_optimizer_notice_invalid_key' );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_enabled' ) ) {
		add_action( 'admin_notices', 'ewww_image_optimizer_notice_webp_bulk' );
		if ( ewww_image_optimizer_cloud_based_media() ) {
			ewww_image_optimizer_set_option( 'ewww_image_optimizer_webp_force', true );
		}
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_auto' ) && ! ewww_image_optimizer_background_mode_enabled() ) {
		add_action( 'network_admin_notices', 'ewww_image_optimizer_notice_schedule_noasync' );
		add_action( 'admin_notices', 'ewww_image_optimizer_notice_schedule_noasync' );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_force_gif2webp' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_force_gif2webp', false );
	}
	// Prevent ShortPixel AIO messiness.
	remove_action( 'admin_notices', 'autoptimizeMain::notice_plug_imgopt' );
	if ( class_exists( 'autoptimizeExtra' ) || defined( 'AUTOPTIMIZE_PLUGIN_VERSION' ) ) {
		$ao_extra = get_option( 'autoptimize_imgopt_settings' );
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) && ! empty( $ao_extra['autoptimize_imgopt_checkbox_field_1'] ) ) {
			ewwwio_debug_message( 'detected ExactDN + SP conflict' );
			$ao_extra['autoptimize_imgopt_checkbox_field_1'] = 0;
			update_option( 'autoptimize_imgopt_settings', $ao_extra );
			add_action( 'admin_notices', 'ewww_image_optimizer_notice_exactdn_sp_conflict' );
		}
	}
	global $exactdn;
	if (
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_local_mode' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) &&
		ewww_image_optimizer_easy_active()
	) {
		// Suppress the custom column in the media library if local mode is disabled and easy mode is active.
		remove_filter( 'manage_media_columns', 'ewww_image_optimizer_columns' );
	} else {
		add_action( 'admin_notices', 'ewww_image_optimizer_notice_media_listmode' );
	}
	if ( ewww_image_optimizer_easy_active() ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_webp', false );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_webp_force', false );
	}

	// Alert user if multiple re-optimizations detected.
	if ( ! defined( 'EWWWIO_DISABLE_REOPT_NOTICE' ) ) {
		add_action( 'network_admin_notices', 'ewww_image_optimizer_notice_reoptimization' );
		add_action( 'admin_notices', 'ewww_image_optimizer_notice_reoptimization' );
	}
	// Let the admin know a db upgrade is needed.
	if ( is_super_admin() && get_transient( 'ewww_image_optimizer_620_upgrade_needed' ) ) {
		add_action( 'admin_notices', 'ewww_image_optimizer_620_upgrade_needed' );
	}
	if (
		is_super_admin() &&
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_review_time' ) &&
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_review_time' ) < time() &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_dismiss_review_notice' )
	) {
		add_action( 'admin_notices', 'ewww_image_optimizer_notice_review' );
		add_action( 'admin_footer', 'ewww_image_optimizer_notice_review_script' );
	}
	if ( ! empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'regenerate-thumbnails' === $_GET['page'] // phpcs:ignore WordPress.Security.NonceVerification
			|| 'force-regenerate-thumbnails' === $_GET['page'] // phpcs:ignore WordPress.Security.NonceVerification
			|| 'ajax-thumbnail-rebuild' === $_GET['page'] // phpcs:ignore WordPress.Security.NonceVerification
			|| 'regenerate_thumbnails_advanced' === $_GET['page'] // phpcs:ignore WordPress.Security.NonceVerification
			|| 'rta_generate_thumbnails' === $_GET['page'] // phpcs:ignore WordPress.Security.NonceVerification
		) {
			// Add a notice for thumb regeneration.
			add_action( 'admin_notices', 'ewww_image_optimizer_thumbnail_regen_notice' );
		}
	}
	if ( ! empty( $_GET['ewww_pngout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		add_action( 'admin_notices', 'ewww_image_optimizer_pngout_installed' );
		add_action( 'network_admin_notices', 'ewww_image_optimizer_pngout_installed' );
	}
	if ( ! empty( $_GET['ewww_svgcleaner'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		add_action( 'admin_notices', 'ewww_image_optimizer_svgcleaner_installed' );
		add_action( 'network_admin_notices', 'ewww_image_optimizer_svgcleaner_installed' );
	}
	if ( ! defined( 'EIO_PHPUNIT' ) && ( ! defined( 'WP_CLI' ) || ! WP_CLI ) ) {
		ewww_image_optimizer_privacy_policy_content();
		ewww_image_optimizer_ajax_compat_check();
	}
	if ( class_exists( 'WooCommerce' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_wc_regen' ) ) {
		add_action( 'admin_notices', 'ewww_image_optimizer_notice_wc_regen' );
		add_action( 'admin_footer', 'ewww_image_optimizer_wc_regen_script' );
	}
	if ( class_exists( 'Meow_WPLR_Sync_Core' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_lr_sync' ) ) {
		add_action( 'admin_notices', 'ewww_image_optimizer_notice_lr_sync' );
		add_action( 'admin_footer', 'ewww_image_optimizer_lr_sync_script' );
	}
	// Increase the version when the next bump is coming.
	if ( defined( 'PHP_VERSION_ID' ) && PHP_VERSION_ID < 70200 ) {
		add_action( 'network_admin_notices', 'ewww_image_optimizer_php72_warning' );
		add_action( 'admin_notices', 'ewww_image_optimizer_php72_warning' );
	}
	ewwwio_memory( __FUNCTION__ );
}

/**
 * Setup wp_cron tasks for scheduled optimization.
 *
 * @global object $wpdb
 *
 * @param string $event Name of cron hook to schedule.
 */
function ewww_image_optimizer_cron_setup( $event ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Setup scheduled optimization if the user has enabled it, and it isn't already scheduled.
	if ( ewww_image_optimizer_get_option( $event ) && ! wp_next_scheduled( $event ) ) {
		ewwwio_debug_message( "scheduling $event" );
		wp_schedule_event( time(), apply_filters( 'ewww_image_optimizer_schedule', 'hourly', $event ), $event );
	} elseif ( ewww_image_optimizer_get_option( $event ) ) {
		ewwwio_debug_message( "$event already scheduled: " . wp_next_scheduled( $event ) );
	} elseif ( wp_next_scheduled( $event ) ) {
		ewwwio_debug_message( "un-scheduling $event" );
		wp_clear_scheduled_hook( $event );
		if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
			// Need to include the plugin library for the is_plugin_active function.
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
			global $wpdb;
			$blogs = $wpdb->get_results( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs WHERE site_id = %d", $wpdb->siteid ), ARRAY_A );
			if ( ewww_image_optimizer_iterable( $blogs ) ) {
				foreach ( $blogs as $blog ) {
					switch_to_blog( $blog['blog_id'] );
					wp_clear_scheduled_hook( $event );
					restore_current_blog();
				}
			}
		}
	}
}

/**
 * Checks to see if this is an AJAX request, and whether the WP_Image_Editor hooks should be undone.
 *
 * @since 3.3.0
 */
function ewww_image_optimizer_ajax_compat_check() {
	if ( ! wp_doing_ajax() ) {
		return;
	}
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$action = ! empty( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( $action ) {
		ewwwio_debug_message( "doing $action" );
	}
	// Check for (Force) Regenerate Thumbnails action (includes MLP regenerate).
	if (
		'regeneratethumbnail' === $action ||
		'rta_regenerate_thumbnails' === $action ||
		'meauh_save_image' === $action ||
		'hotspot_save' === $action
	) {
		ewwwio_debug_message( 'doing regeneratethumbnail' );
		ewww_image_optimizer_image_sizes( false );
		add_filter( 'ewww_image_optimizer_allowed_reopt', '__return_true' );
		return;
	}
	if ( 'mic_crop_image' === $action ) {
		ewwwio_debug_message( 'doing Manual Image Crop' );
		if ( ! defined( 'EWWWIO_EDITOR_OVERWRITE' ) ) {
			define( 'EWWWIO_EDITOR_OVERWRITE', true );
		}
		add_filter( 'ewww_image_optimizer_allowed_reopt', '__return_true' );
		return;
	}
	if ( false !== strpos( $action, 'wc_regenerate_images' ) ) {
		// Unhook all automatic processing, and save an option that (does not autoload) tells the user WC regenerated their images and they should run the bulk optimizer.
		remove_filter( 'wp_image_editors', 'ewww_image_optimizer_load_editor', 60 );
		remove_filter( 'wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15 );
		update_option( 'ewww_image_optimizer_wc_regen', true, false );
		return;
	}
	// Check for Image Watermark plugin.
	$iwaction = ! empty( $_REQUEST['iw-action'] ) ? sanitize_key( $_REQUEST['iw-action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( $iwaction ) {
		ewwwio_debug_message( "doing $iwaction" );
		global $ewww_preempt_editor;
		$ewww_preempt_editor = true;
		if ( 'applywatermark' === $iwaction ) {
			remove_filter( 'wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15 );
			add_action( 'iw_after_apply_watermark', 'ewww_image_optimizer_single_size_optimize', 10, 2 );
		}
		add_filter( 'ewww_image_optimizer_allowed_reopt', '__return_true' );
		return;
	}
	// Check for other MLP actions, including multi-regen.
	if ( class_exists( 'MaxGalleriaMediaLib' ) && ( 'regen_mlp_thumbnails' === $action || 'move_media' === $action || 'copy_media' === $action || 'maxgalleria_rename_image' === $action ) ) {
		ewwwio_debug_message( 'doing regen_mlp_thumbnails' );
		ewww_image_optimizer_image_sizes( false );
		add_filter( 'ewww_image_optimizer_allowed_reopt', '__return_true' );
		return;
	}
	// Check for MLP upload.
	if ( class_exists( 'MaxGalleriaMediaLib' ) && ! empty( $_REQUEST['nonce'] ) && 'upload_attachment' === $action ) { // phpcs:ignore WordPress.Security.NonceVerification
		ewwwio_debug_message( 'doing maxgalleria upload' );
		ewww_image_optimizer_image_sizes( false );
		add_filter( 'ewww_image_optimizer_allowed_reopt', '__return_true' );
		return;
	}
	// Check for Image Regenerate and Select Crop (better way).
	if ( defined( 'DOING_SIRSC' ) && DOING_SIRSC ) {
		ewwwio_debug_message( 'IRSC action/regen' );
		ewww_image_optimizer_image_sizes( false );
		add_filter( 'ewww_image_optimizer_allowed_reopt', '__return_true' );
		return;
	} elseif ( 0 === strpos( $action, 'sirsc' ) ) {
		// Image Regenerate and Select Crop (old check).
		ewwwio_debug_message( 'IRSC action/regen (old)' );
		ewww_image_optimizer_image_sizes( false );
		add_filter( 'ewww_image_optimizer_allowed_reopt', '__return_true' );
		return;
	}
	// Check for Phoenix Media Rename action.
	if ( class_exists( 'Phoenix_Media_Rename' ) && 'phoenix_media_rename' === $action ) {
		ewwwio_debug_message( 'Phoenix Media Rename, verifying' );
		if ( check_ajax_referer( 'phoenix_media_rename', '_wpnonce', false ) ) {
			ewwwio_debug_message( 'PMR verified' );
			remove_filter( 'wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15 );
			ewww_image_optimizer_image_sizes( false );
			add_filter( 'ewww_image_optimizer_allowed_reopt', '__return_true' );
			return;
		}
	}
}

/**
 * Adds suggested privacy policy content for site admins.
 *
 * Note that this is just a suggestion, it should be customized for your site.
 */
function ewww_image_optimizer_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) || ! function_exists( 'wp_kses_post' ) ) {
		return;
	}
	$content = '<p class="privacy-policy-tutorial">';
	if ( ! defined( 'EWWW_IO_CLOUD_PLUGIN' ) || ! EWWW_IO_CLOUD_PLUGIN ) {
		$content .= wp_kses_post( __( 'By default, the EWWW Image Optimizer does not store any personal data nor share it with anyone.', 'ewww-image-optimizer' ) ) . '</p><p>';
	}
	$content .= wp_kses_post( __( 'If you accept user-submitted images and use the API or Easy IO, those images may be transmitted to third-party servers in foreign countries. If Backup Originals is enabled, images are stored for 30 days. Otherwise, no images are stored on the API for longer than 30 minutes.', 'ewww-image-optimizer' ) ) . '</p>';
	$content .= '<p><strong>' . wp_kses_post( __( 'Suggested API Text:' ) ) . '</strong> <i>' . wp_kses_post( __( 'User-submitted images may be transmitted to image compression servers in the United States and stored there for up to 30 days.' ) ) . '</i></p>';
	$content .= '<p><strong>' . wp_kses_post( __( 'Suggested Easy IO Text:' ) ) . '</strong> <i>' . wp_kses_post( __( 'User-submitted images that are displayed on this site will be transmitted and stored on a global network of third-party servers (a CDN).' ) ) . '</i></p>';
	wp_add_privacy_policy_content( 'EWWW Image Optimizer', $content );
}

/**
 * Check the current screen, currently used to temporarily enable debugging on settings page.
 *
 * @param object $screen Information about the page/screen currently being loaded.
 */
function ewww_image_optimizer_current_screen( $screen ) {
	global $ewwwio_temp_debug;
	global $eio_debug;
	if ( false === strpos( $screen->id, 'settings_page_ewww-image-optimizer' ) && false === strpos( $screen->id, 'settings_page_easy-image-optimizer' ) ) {
		$ewwwio_temp_debug = false;
		if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
			$eio_debug = '';
		}
	}
}

/**
 * Optimize a single image from an attachment, based on the size and ID.
 *
 * @param int    $id The attachment ID number.
 * @param string $size The slug/name of the image size.
 */
function ewww_image_optimizer_single_size_optimize( $id, $size ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	session_write_close();
	$meta = wp_get_attachment_metadata( $id );
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
	ewwwio_debug_message( "retrieved file path: $file_path" );
	$type            = ewww_image_optimizer_mimetype( $file_path, 'i' );
	$supported_types = array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'application/pdf',
		'image/svg+xml',
	);
	if ( ! in_array( $type, $supported_types, true ) ) {
		ewwwio_debug_message( "mimetype not supported: $id" );
		return;
	}
	if ( 'full' === $size ) {
		$ewww_image         = new EWWW_Image( $id, 'media', $file_path );
		$ewww_image->resize = 'full';

		// Run the optimization and store the results.
		ewww_image_optimizer( $file_path, 4, false, false, true );
		return;
	}
	// Resized version, continue.
	if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'][ $size ] ) ) {
		$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt', false, true );
		ewwwio_debug_message( "processing size: $size" );
		$base_dir = trailingslashit( dirname( $file_path ) );
		$data     = $meta['sizes'][ $size ];
		if ( strpos( $size, 'webp' ) === 0 ) {
			return;
		}
		if ( ! empty( $disabled_sizes[ $size ] ) ) {
			return;
		}
		if ( ! empty( $disabled_sizes['pdf-full'] ) && 'full' === $size ) {
			return;
		}
		if ( empty( $data['file'] ) ) {
			return;
		}
		// If this is a unique size.
		$resize_path = $base_dir . wp_basename( $data['file'] );
		if ( 'application/pdf' === $type && 'full' === $size ) {
			$size = 'pdf-full';
			ewwwio_debug_message( 'processing full size pdf preview' );
		}
		$ewww_image         = new EWWW_Image( $id, 'media', $resize_path );
		$ewww_image->resize = $size;
		// Run the optimization and store the results.
		ewww_image_optimizer( $resize_path );
		// Optimize retina images, if they exist.
		if ( function_exists( 'wr2x_get_retina' ) ) {
			$retina_path = wr2x_get_retina( $resize_path );
		} else {
			$retina_path = false;
		}
		if ( $retina_path && ewwwio_is_file( $retina_path ) ) {
			$ewww_image         = new EWWW_Image( $id, 'media', $retina_path );
			$ewww_image->resize = $size . '-retina';
			ewww_image_optimizer( $retina_path );
		} else {
			ewww_image_optimizer_hidpi_optimize( $resize_path );
		}
	} // End if().
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	/**
	 * Checks to see if this is an AJAX request.
	 *
	 * For backwards compatiblity with WordPress < 4.7.0.
	 *
	 * @since 3.3.0
	 *
	 * @return bool True if this is an AJAX request.
	 */
	function wp_doing_ajax() {
		return apply_filters( 'wp_doing_ajax', defined( 'DOING_AJAX' ) && DOING_AJAX );
	}
}

/**
 * Checks to see if this is a REST API request, and whether the WP_Image_Editor hooks should be undone.
 *
 * @since 4.0.6
 */
function ewww_image_optimizer_restapi_compat_check() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! empty( $GLOBALS['wp']->query_vars['rest_route'] ) && false !== strpos( $GLOBALS['wp']->query_vars['rest_route'], '/regenerate-thumbnails' ) ) {
		ewwwio_debug_message( 'doing regenerate-thumbnails via REST' );
		ewww_image_optimizer_image_sizes( false );
		add_filter( 'ewww_image_optimizer_allowed_reopt', '__return_true' );
		return;
	}
	if (
		! empty( $GLOBALS['wp']->query_vars['rest_route'] ) &&
		strpos( $GLOBALS['wp']->query_vars['rest_route'], '/media/' ) &&
		preg_match( '/media\/\d+\/edit$/', $GLOBALS['wp']->query_vars['rest_route'] )
	) {
		ewwwio_debug_message( 'image edited via REST' );
		global $ewww_preempt_editor;
		$ewww_preempt_editor = true;
	}
}

/**
 * Disables all the local tools by setting their constants to false.
 */
function ewww_image_optimizer_disable_tools() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_NOEXEC' ) ) {
		define( 'EWWW_IMAGE_OPTIMIZER_NOEXEC', true );
	}
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_JPEGTRAN' ) ) {
		define( 'EWWW_IMAGE_OPTIMIZER_JPEGTRAN', false );
	}
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_OPTIPNG' ) ) {
		define( 'EWWW_IMAGE_OPTIMIZER_OPTIPNG', false );
	}
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_PNGOUT' ) ) {
		define( 'EWWW_IMAGE_OPTIMIZER_PNGOUT', false );
	}
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_GIFSICLE' ) ) {
		define( 'EWWW_IMAGE_OPTIMIZER_GIFSICLE', false );
	}
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_PNGQUANT' ) ) {
		define( 'EWWW_IMAGE_OPTIMIZER_PNGQUANT', false );
	}
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CWEBP' ) ) {
		define( 'EWWW_IMAGE_OPTIMIZER_CWEBP', false );
	}
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_SVGCLEANER' ) ) {
		define( 'EWWW_IMAGE_OPTIMIZER_SVGCLEANER', false );
	}
	ewwwio_memory( __FUNCTION__ );
}

/**
 * Generates css include for progressbars to match admin style.
 */
function ewww_image_optimizer_progressbar_style() {
	wp_add_inline_style( 'jquery-ui-progressbar', '.ui-widget-header { background-color: ' . ewww_image_optimizer_admin_background() . '; }' );
	ewwwio_memory( __FUNCTION__ );
}

/**
 * Grabs the color scheme information from the current admin theme and saves it for later.
 *
 * @global $ewwwio_admin_color The color we want to use for theming.
 * @global array $_wp_admin_css_colors An array of available admin color/theme objects.
 */
function ewww_image_optimizer_save_admin_colors() {
	global $ewwwio_admin_color;
	global $_wp_admin_css_colors;
	if ( function_exists( 'wp_add_inline_style' ) ) {
		$user_info = wp_get_current_user();
		if (
			is_array( $_wp_admin_css_colors ) &&
			! empty( $user_info->admin_color ) &&
			isset( $_wp_admin_css_colors[ $user_info->admin_color ] ) &&
			is_object( $_wp_admin_css_colors[ $user_info->admin_color ] ) &&
			is_array( $_wp_admin_css_colors[ $user_info->admin_color ]->colors ) &&
			! empty( $_wp_admin_css_colors[ $user_info->admin_color ]->colors[2] ) &&
			preg_match( '/^\#([0-9a-fA-F]){3,6}$/', $_wp_admin_css_colors[ $user_info->admin_color ]->colors[2] )
		) {
			$ewwwio_admin_color = $_wp_admin_css_colors[ $user_info->admin_color ]->colors[2];
		}
	}
	if ( empty( $ewwwio_admin_color ) ) {
		$ewwwio_admin_color = '#0073aa';
	}
}

/**
 * Determines the background color to use based on the selected admin theme.
 */
function ewww_image_optimizer_admin_background() {
	global $ewwwio_admin_color;
	if ( ! empty( $ewwwio_admin_color ) && preg_match( '/^\#([0-9a-fA-F]){3,6}$/', $ewwwio_admin_color ) ) {
		return $ewwwio_admin_color;
	}
	if ( function_exists( 'wp_add_inline_style' ) ) {
		$user_info = wp_get_current_user();
		global $_wp_admin_css_colors;
		if (
			is_array( $_wp_admin_css_colors ) &&
			! empty( $user_info->admin_color ) &&
			isset( $_wp_admin_css_colors[ $user_info->admin_color ] ) &&
			is_object( $_wp_admin_css_colors[ $user_info->admin_color ] ) &&
			is_array( $_wp_admin_css_colors[ $user_info->admin_color ]->colors ) &&
			! empty( $_wp_admin_css_colors[ $user_info->admin_color ]->colors[2] ) &&
			preg_match( '/^\#([0-9a-fA-F]){3,6}$/', $_wp_admin_css_colors[ $user_info->admin_color ]->colors[2] )
		) {
			$ewwwio_admin_color = $_wp_admin_css_colors[ $user_info->admin_color ]->colors[2];
			return $ewwwio_admin_color;
		}
		switch ( $user_info->admin_color ) {
			case 'midnight':
				return '#e14d43';
			case 'blue':
				return '#096484';
			case 'light':
				return '#04a4cc';
			case 'ectoplasm':
				return '#a3b745';
			case 'coffee':
				return '#c7a589';
			case 'ocean':
				return '#9ebaa0';
			case 'sunrise':
				return '#dd823b';
			default:
				return '#0073aa';
		}
	}
	return '#0073aa';
}

/**
 * If a multisite is over 1000 sites, tells WP this is a 'large network' when querying image stats.
 *
 * @param bool   $large_network Normally only true with 10,000+ users or sites.
 * @param string $criteria The criteria for determining a large network, 'sites' or 'users'.
 * @param int    $count The number of sites/users.
 * @return bool True if this is a 'large network'.
 */
function ewww_image_optimizer_large_network( $large_network, $criteria, $count ) {
	if ( 'sites' === $criteria && $count > 1000 ) {
		return true;
	}
	return false;
}

/**
 * Adds/upgrades table in db for storing status of all images that have been optimized.
 *
 * @global object $wpdb
 */
function ewww_image_optimizer_install_table() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	$wpdb->ewwwio_images = $wpdb->prefix . 'ewwwio_images';
	$wpdb->ewwwio_queue  = $wpdb->prefix . 'ewwwio_queue';

	// Get the current wpdb charset and collation.
	$db_collation = $wpdb->get_charset_collate();
	ewwwio_debug_message( "current collation: $db_collation" );

	$primary_key_definition = 'PRIMARY KEY  (id),';
	// See if the path column exists, and what collation it uses to determine the column index size.
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$wpdb->ewwwio_images'" ) === $wpdb->ewwwio_images ) {
		ewwwio_debug_message( 'upgrading table and checking collation for path, table exists' );
		$mysql_version = 'unknown';
		if ( method_exists( $wpdb, 'db_server_info' ) ) {
			$mysql_version = strtolower( $wpdb->db_server_info() );
		}
		ewwwio_debug_message( $mysql_version );
		if ( false !== strpos( $mysql_version, 'maria' ) && false !== strpos( $mysql_version, '10.4.' ) ) {
			$primary_key_definition = 'UNIQUE KEY id (id),';
		}
		if ( false && false === strpos( $mysql_version, 'maria' ) || false === strpos( $mysql_version, '10.4.' ) ) {
			ewwwio_debug_message( 'checking primary/unique index' );
			if ( ! $wpdb->get_results( "SHOW INDEX FROM $wpdb->ewwwio_images WHERE Key_name = 'PRIMARY'", ARRAY_A ) ) {
				ewwwio_debug_message( 'adding primary index' );
				$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images ADD PRIMARY KEY(id)" );
			}
			if ( $wpdb->get_results( "SHOW INDEX FROM $wpdb->ewwwio_images WHERE Key_name = 'id'", ARRAY_A ) ) {
				ewwwio_debug_message( 'dropping unique index' );
				$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images DROP INDEX id" );
			}
		}
		// Check if the old path_image_size index exists, and drop it.
		if ( $wpdb->get_results( "SHOW INDEX FROM $wpdb->ewwwio_images WHERE Key_name = 'path_image_size'", ARRAY_A ) ) {
			ewwwio_debug_message( 'getting rid of path_image_size index' );
			$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images DROP INDEX path_image_size" );
		}
		// Make sure there are valid dates in updated column.
		$wpdb->query( "UPDATE $wpdb->ewwwio_images SET updated = '1971-01-01 00:00:00' WHERE updated < '1001-01-01 00:00:01'" );
		// Get the current table layout.
		$suppress    = $wpdb->suppress_errors();
		$tablefields = $wpdb->get_results( "DESCRIBE {$wpdb->ewwwio_images};" );
		$wpdb->suppress_errors( $suppress );
		$timestamp_upgrade_needed = false;
		if ( ewww_image_optimizer_iterable( $tablefields ) ) {
			foreach ( $tablefields as $tablefield ) {
				if ( 'updated' === $tablefield->Field && false === stripos( $tablefield->Default, 'current_timestamp' ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$timestamp_upgrade_needed = true;
					ewwwio_debug_message( 'updated timestamp upgrade needed' );
				}
			}
		}
		if (
			( false !== strpos( $mysql_version, '5.7.' ) || false !== strpos( $mysql_version, '10.1.' ) ) &&
			$timestamp_upgrade_needed
		) {
			if ( is_multisite() ) {
				// Just do the upgrade.
				$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images MODIFY updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP" );
			} else {
				// Do it later via user interaction.
				set_transient( 'ewww_image_optimizer_620_upgrade_needed', true );
			}
		} elseif ( $timestamp_upgrade_needed ) {
			$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images ALTER updated SET DEFAULT CURRENT_TIMESTAMP" );
		}
		// Check the current collation and adjust it if necessary.
		$column_collate = $wpdb->get_col_charset( $wpdb->ewwwio_images, 'path' );
		if ( ! empty( $column_collate ) && ! is_wp_error( $column_collate ) && 'utf8mb4' !== $column_collate ) {
			$path_index_size = 255;
			ewwwio_debug_message( "current column collation: $column_collate" );
			if ( strpos( $column_collate, 'utf8' ) === false ) {
				ewwwio_debug_message( 'converting path column to utf8' );
				$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images CHANGE path path BLOB" );
				if ( $wpdb->has_cap( 'utf8mb4_520' ) && strpos( $db_collation, 'utf8mb4' ) ) {
					ewwwio_debug_message( 'using mb4 version 5.20' );
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images DROP INDEX path_image_size" );
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images CONVERT TO CHARACTER SET utf8mb4, CHANGE path path TEXT" );
					unset( $path_index_size );
				} elseif ( $wpdb->has_cap( 'utf8mb4' ) && strpos( $db_collation, 'utf8mb4' ) ) {
					ewwwio_debug_message( 'using mb4 version 4' );
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images DROP INDEX path_image_size" );
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images CONVERT TO CHARACTER SET utf8mb4, CHANGE path path TEXT" );
					unset( $path_index_size );
				} else {
					ewwwio_debug_message( 'using plain old utf8' );
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images CONVERT TO CHARACTER SET utf8, CHANGE path path TEXT" );
				}
			} elseif ( strpos( $column_collate, 'utf8mb4' ) === false && strpos( $db_collation, 'utf8mb4' ) ) {
				if ( $wpdb->has_cap( 'utf8mb4_520' ) ) {
					ewwwio_debug_message( 'using mb4 version 5.20' );
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images DROP INDEX path_image_size" );
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images CONVERT TO CHARACTER SET utf8mb4, CHANGE path path TEXT" );
					unset( $path_index_size );
				} elseif ( $wpdb->has_cap( 'utf8mb4' ) ) {
					ewwwio_debug_message( 'using mb4 version 4' );
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images DROP INDEX path_image_size" );
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images CONVERT TO CHARACTER SET utf8mb4, CHANGE path path TEXT" );
					unset( $path_index_size );
				}
			}
		}
	} // End if().

	// If the path column doesn't yet exist, and the default collation is utf8mb4, then we need to lower the column index size.
	if ( empty( $path_index_size ) && strpos( $db_collation, 'utf8mb4' ) ) {
		$path_index_size = 191;
	} else {
		$path_index_size = 255;
	}
	ewwwio_debug_message( "path index size: $path_index_size" );

	/*
	 * Create a table with 15 columns:
	 * id: unique for each record/image,
	 * attachment_id: the unique id within the media library, nextgen, or flag
	 * gallery: 'media', 'nextgen', 'nextcell', or 'flag',
	 * resize: size of the image,
	 * path: filename of the image, potentially replaced with ABSPATH or WP_CONTENT_DIR,
	 * converted: filename of the image before conversion,
	 * results: human-readable savings message,
	 * image_size: optimized size of the image,
	 * orig_size: original size of the image,
	 * backup: hash where the image is stored on the API servers,
	 * level: the optimization level used on the image,
	 * pending: 1 if the image is queued for optimization,
	 * updates: how many times an image has been optimized,
	 * updated: when the image was last optimized,
	 * trace: tracelog from the last optimization if debugging was enabled.
	 */
	$sql = "CREATE TABLE $wpdb->ewwwio_images (
		id int unsigned NOT NULL AUTO_INCREMENT,
		attachment_id bigint unsigned,
		gallery varchar(10),
		resize varchar(75),
		path text NOT NULL,
		converted text NOT NULL,
		results varchar(75) NOT NULL,
		image_size int unsigned,
		orig_size int unsigned,
		backup varchar(100),
		level int unsigned,
		pending tinyint NOT NULL DEFAULT 0,
		updates int unsigned,
		updated timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		trace blob,
		$primary_key_definition
		KEY path (path($path_index_size)),
		KEY attachment_info (gallery(3),attachment_id)
	) $db_collation;";

	// Include the upgrade library to install/upgrade a table.
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	$updates = dbDelta( $sql );
	ewwwio_debug_message( 'images db upgrade results: ' . implode( '<br>', $updates ) );

	/*
	 * Create a table with 5 columns:
	 * id: unique for each record/image,
	 * attachment_id: the unique id within the media library, nextgen, or flag
	 * gallery: 'media', 'nextgen', 'nextcell', 'flag', plus -async variants.
	 * scanned: 1 if the image is queued for optimization, 0 if it still needs scanning.
	 * new: 1 if the image is a 'new' upload queued for optimization, 0 otherwise.
	 */
	$sql = "CREATE TABLE $wpdb->ewwwio_queue (
		id int unsigned NOT NULL AUTO_INCREMENT,
		attachment_id bigint unsigned,
		gallery varchar(20),
		scanned tinyint NOT NULL DEFAULT 0,
		new tinyint NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY attachment_info (gallery(3),attachment_id)
	) COLLATE utf8_general_ci;";

	// Include the upgrade library to install/upgrade a table.
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	$updates = dbDelta( $sql );
	ewwwio_debug_message( 'queue db upgrade results: ' . implode( '<br>', $updates ) );
}

/**
 * Migrates old cloud/compression settings to compression levels.
 */
function ewww_image_optimizer_migrate_settings_to_levels() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_jpegtran' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_level', 0 );
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_jpegtran' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_level', 10 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_jpg' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_lossy' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_level', 20 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_jpg' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_lossy' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_lossy_fast' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_level', 30 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_jpg' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_lossy' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_lossy_fast' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_level', 40 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_pngout' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_optipng' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_level', 0 );
	}
	if ( ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_pngout' ) || ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_optipng' ) ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_level', 10 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_png' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_lossy' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_png_compress' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_level', 20 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_png' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_lossy' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_png_compress' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_level', 30 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_png' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_lossy' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_lossy_fast' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_level', 40 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_png' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_lossy' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_lossy_fast' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_level', 50 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_gifsicle' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_gif_level', 0 );
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_gifsicle' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_gif_level', 10 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_pdf' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_lossy' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_pdf_level', 10 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_pdf' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_lossy' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_pdf_level', 20 );
	}
}

/**
 * Removes settings which are no longer used.
 */
function ewww_image_optimizer_remove_obsolete_settings() {
	delete_option( 'ewww_image_optimizer_disable_jpegtran' );
	delete_option( 'ewww_image_optimizer_cloud_jpg' );
	delete_option( 'ewww_image_optimizer_jpg_lossy' );
	delete_option( 'ewww_image_optimizer_lossy_fast' );
	delete_option( 'ewww_image_optimizer_cloud_png' );
	delete_option( 'ewww_image_optimizer_png_lossy' );
	delete_option( 'ewww_image_optimizer_cloud_png_compress' );
	delete_option( 'ewww_image_optimizer_disable_gifsicle' );
	delete_option( 'ewww_image_optimizer_cloud_gif' );
	delete_option( 'ewww_image_optimizer_cloud_pdf' );
	delete_option( 'ewww_image_optimizer_pdf_lossy' );
	delete_option( 'ewww_image_optimizer_skip_check' );
	delete_option( 'ewww_image_optimizer_disable_optipng' );
	delete_option( 'ewww_image_optimizer_interval' );
	delete_option( 'ewww_image_optimizer_jpegtran_path' );
	delete_option( 'ewww_image_optimizer_optipng_path' );
	delete_option( 'ewww_image_optimizer_gifsicle_path' );
	delete_option( 'ewww_image_optimizer_import_status' );
	delete_option( 'ewww_image_optimizer_bulk_image_count' );
	delete_option( 'ewww_image_optimizer_maxwidth' );
	delete_option( 'ewww_image_optimizer_maxheight' );
	delete_option( 'ewww_image_optimizer_exactdn_failures' );
	delete_option( 'ewww_image_optimizer_exactdn_suspended' );
	delete_option( 'ewww_image_optimizer_bulk_attachments' );
	delete_option( 'ewww_image_optimizer_aux_attachments' );
	delete_option( 'ewww_image_optimizer_defer_attachments' );
}

/**
 * Migrate any option-based queues to the queue table.
 */
function ewww_image_optimizer_migrate_option_queue_to_table() {
	global $wpdb;
	global $ewwwio_media_background;
	if ( ! class_exists( 'WP_Background_Process' ) ) {
		require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
	}
	if ( ! is_object( $ewwwio_media_background ) ) {
		$ewwwio_media_background = new EWWWIO_Media_Background_Process();
	}
	$key    = 'wp_ewwwio_media_optimize_batch_%';
	$queues = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT *
			FROM $wpdb->options
			WHERE option_name LIKE %s",
			$key
		),
		ARRAY_A
	);
	if ( ! empty( $queues ) ) {
		foreach ( $queues as $queue ) {
			$items = maybe_unserialize( $queue['option_value'] );
			if ( ewww_image_optimizer_iterable( $items ) ) {
				foreach ( $items as $item ) {
					$ewwwio_media_background->push_to_queue(
						array(
							'id'  => $item['id'],
							'new' => 0,
						)
					);
				}
			}
			$ewwwio_media_background->dispatch();
		}
	}
	// Clear all queues.
	$key = 'wp_ewwwio_%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", $key ) );
	$key = '%ewwwio-background-in-progress-%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", $key ) );
}

/**
 * Checks to see if the WebP conversion was just enabled.
 *
 * @param mixed $new_value The new value, in this case it will be boolean usually.
 * @param mixed $old_value The old value, also a boolean generally.
 * @return mixed The new value, unaltered.
 */
function ewww_image_optimizer_webp_maybe_enabled( $new_value, $old_value ) {
	if ( ! empty( $new_value ) && (bool) $new_value !== (bool) $old_value ) {
		update_option( 'ewww_image_optimizer_webp_enabled', true );
	}
	return $new_value;
}

/**
 * Checks if a plugin is offloading media to cloud storage and removing local copies.
 *
 * @return bool True if a plugin is removing local files, false otherwise..
 */
function ewww_image_optimizer_cloud_based_media() {
	if ( class_exists( 'Amazon_S3_And_CloudFront' ) ) {
		global $as3cf;
		if ( is_object( $as3cf ) && $as3cf->get_setting( 'serve-from-s3' ) && $as3cf->get_setting( 'remove-local-file' ) ) {
			return true;
		}
	}
	if ( class_exists( 'S3_Uploads' ) && function_exists( 's3_uploads_enabled' ) && s3_uploads_enabled() ) {
		return true;
	}
	if ( class_exists( 'S3_Uploads\Plugin' ) && function_exists( 'S3_Uploads\enabled' ) && \S3_Uploads\enabled() ) {
		return true;
	}
	if ( class_exists( 'wpCloud\StatelessMedia\EWWW' ) && function_exists( 'ud_get_stateless_media' ) ) {
		$sm = ud_get_stateless_media();
		if ( method_exists( $sm, 'get' ) ) {
			$sm_mode = $sm->get( 'sm.mode' );
			if ( 'disabled' !== $sm_mode ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Checks to see if Scheduled Optimization was just disabled.
 *
 * @param mixed $new_value The new value, in this case it will be boolean usually.
 * @param mixed $old_value The old value, also a boolean generally.
 * @return mixed The new value, unaltered.
 */
function ewww_image_optimizer_scheduled_optimizaton_changed( $new_value, $old_value ) {
	if ( empty( $new_value ) && (bool) $new_value !== (bool) $old_value ) {
		global $ewwwio_image_background;
		if ( ! class_exists( 'WP_Background_Process' ) ) {
			require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
		}
		$ewwwio_image_background->cancel_process();
		update_option( 'ewwwio_stop_scheduled_scan', true, false );
	}
	return $new_value;
}

/**
 * Display a notice that the user should run the bulk optimizer after WebP activation.
 */
function ewww_image_optimizer_notice_webp_bulk() {
	$already_done = ewww_image_optimizer_aux_images_table_count();
	if ( $already_done > 50 ) {
		echo "<div id='ewww-image-optimizer-pngout-success' class='notice notice-info'><p><a href='" .
			esc_url( admin_url( 'upload.php?page=ewww-image-optimizer-bulk&ewww_webp_only=1&ewww_force=1' ) ) .
			"'>" . esc_html__( 'It looks like you already started optimizing your images, you will need to generate WebP images via the Bulk Optimizer.', 'ewww-image-optimizer' ) . '</a></p></div>';
	} else {
		echo "<div id='ewww-image-optimizer-pngout-success' class='notice notice-info'><p><a href='" .
			esc_url( admin_url( 'upload.php?page=ewww-image-optimizer-bulk' ) ) .
			"'>" . esc_html__( 'Use the Bulk Optimizer to generate WebP images for existing uploads.', 'ewww-image-optimizer' ) . '</a></p></div>';
	}
	delete_option( 'ewww_image_optimizer_webp_enabled' );
}

/**
 * Display a success or failure message after PNGOUT installation.
 */
function ewww_image_optimizer_pngout_installed() {
	if ( ! empty( $_REQUEST['ewww_pngout'] ) && 'success' === $_REQUEST['ewww_pngout'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo "<div id='ewww-image-optimizer-pngout-success' class='notice notice-success fade'>\n" .
			'<p>' . esc_html__( 'Pngout was successfully installed.', 'ewww-image-optimizer' ) . "</p>\n" .
			"</div>\n";
	}
	if ( ! empty( $_REQUEST['ewww_pngout'] ) && 'failed' === $_REQUEST['ewww_pngout'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo "<div id='ewww-image-optimizer-pngout-failure' class='notice notice-error'>\n" .
			'<p>' . sprintf(
				/* translators: 1: An error message 2: The folder where pngout should be installed */
				esc_html__( 'Pngout was not installed: %1$s. Make sure this folder is writable: %2$s', 'ewww-image-optimizer' ),
				( ! empty( $_REQUEST['ewww_error'] ) ? esc_html( sanitize_text_field( wp_unslash( $_REQUEST['ewww_error'] ) ) ) : esc_html( 'unknown error', 'ewww-image-optimizer' ) ), // phpcs:ignore WordPress.Security.NonceVerification
				esc_html( EWWW_IMAGE_OPTIMIZER_TOOL_PATH )
			) . "</p>\n" .
			"</div>\n";
	}
}

/**
 * Display a success or failure message after SVGCLEANER installation.
 */
function ewww_image_optimizer_svgcleaner_installed() {
	if ( ! empty( $_REQUEST['ewww_svgcleaner'] ) && 'success' === $_REQUEST['ewww_svgcleaner'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo "<div id='ewww-image-optimizer-pngout-success' class='notice notice-success fade'>\n" .
			'<p>' . esc_html__( 'Svgcleaner was successfully installed.', 'ewww-image-optimizer' ) . "</p>\n" .
			"</div>\n";
	}
	if ( ! empty( $_REQUEST['ewww_svgcleaner'] ) && 'failed' === $_REQUEST['ewww_svgcleaner'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo "<div id='ewww-image-optimizer-pngout-failure' class='notice notice-error'>\n" .
			'<p>' . sprintf(
				/* translators: 1: An error message 2: The folder where svgcleaner should be installed */
				esc_html__( 'Svgcleaner was not installed: %1$s. Make sure this folder is writable: %2$s', 'ewww-image-optimizer' ),
				( ! empty( $_REQUEST['ewww_error'] ) ? esc_html( sanitize_text_field( wp_unslash( $_REQUEST['ewww_error'] ) ) ) : esc_html( 'unknown error', 'ewww-image-optimizer' ) ), // phpcs:ignore WordPress.Security.NonceVerification
				esc_html( EWWW_IMAGE_OPTIMIZER_TOOL_PATH )
			) . "</p>\n" .
			"</div>\n";
	}
}

/**
 * Display a notice that we could not activate an ExactDN domain.
 */
function ewww_image_optimizer_notice_exactdn_activation_error() {
	return;
	global $exactdn_activate_error;
	if ( empty( $exactdn_activate_error ) ) {
		$exactdn_activate_error = 'error unknown';
	}
	echo '<div id="ewww-image-optimizer-notice-exactdn-error" class="notice notice-error"><p>' .
		sprintf(
			/* translators: %s: A link to the documentation */
			esc_html__( 'Could not activate Easy IO, please try again in a few minutes. If this error continues, please see %s for troubleshooting steps.', 'ewww-image-optimizer' ),
			'https://docs.ewww.io/article/66-exactdn-not-verified'
		) .
		'<br><code>' . esc_html( $exactdn_activate_error ) . '</code>' .
		'</p></div>';
}

/**
 * Let the user know ExactDN setup was successful.
 */
function ewww_image_optimizer_notice_exactdn_activation_success() {
	return;
	?>
	<div id="ewww-image-optimizer-notice-exactdn-success" class="notice notice-success"><p>
		<strong><?php esc_html_e( 'Easy IO setup and verification is complete.', 'ewww-image-optimizer' ); ?></strong>
		<?php esc_html_e( 'If you have problems, try disabling Lazy Load and Include All Resources. Finally, disable Easy IO if problems remain.', 'ewww-image-optimizer' ); ?><br>
		<a class='ewww-contact-root' href='https://ewww.io/contact-us/'>
			<?php esc_html_e( 'Then, let us know so we can find a fix for the problem.', 'ewww-image-optimizer' ); ?>
		</a>
	</p></div>
	<?php
}

/**
 * Remind network admin to activate Easy IO on the new site.
 */
function ewww_image_optimizer_easyio_site_initialized() {
	if ( defined( 'EASYIO_NEW_SITE_AUTOREG' ) && EASYIO_NEW_SITE_AUTOREG ) {
		?>
		<div id="ewww-image-optimizer-notice-exactdn-success" class="notice notice-info"><p>
			<?php esc_html_e( 'Easy IO registration is complete. Visit the plugin settings to activate your new site.', 'ewww-image-optimizer' ); ?>
		</div>
		<?php
	} else {
		?>
		<div id="ewww-image-optimizer-notice-exactdn-success" class="notice notice-info"><p>
			<?php esc_html_e( 'Please visit the EWWW Image Optimizer plugin settings to activate Easy IO on your new site.', 'ewww-image-optimizer' ); ?>
		</div>
		<?php
	}
}

/**
 * Let the user know the local domain appears to have changed from what Easy IO has recorded in the db.
 */
function ewww_image_optimizer_notice_exactdn_domain_mismatch() {
	global $exactdn;
	if ( ! isset( $exactdn->upload_domain ) ) {
		return;
	}
	?>
	<div id="ewww-image-optimizer-notice-exactdn-domain-mismatch" class="notice notice-warning">
		<p>
	<?php
			printf(
				/* translators: 1: old domain name, 2: current domain name */
				esc_html__( 'Easy IO detected that the Site URL has changed since the initial activation (previously %1$s, currently %2$s).', 'ewww-image-optimizer' ),
				'<strong>' . esc_html( $exactdn->get_exactdn_option( 'local_domain' ) ) . '</strong>',
				'<strong>' . esc_html( $exactdn->upload_domain ) . '</strong>'
			);
	?>
			<br>
	<?php
			printf(
				/* translators: %s: settings page */
				esc_html__( 'Please visit the %s to refresh the Easy IO settings and verify activation status.', 'ewww-image-optimizer' ),
				'<a href="' . esc_url( ewww_image_optimizer_get_settings_link() ) . '">' . esc_html__( 'settings page', 'ewww-image-optimizer' ) . '</a>'
			);
	?>
		</p>
	</div>
	<?php
}

/**
 * Let the user know they need to disable the WP Offload Media CNAME.
 */
function ewww_image_optimizer_notice_exactdn_as3cf_cname_active() {
	?>
	<div id="ewww-image-optimizer-notice-exactdn-as3cf-cname-active" class="notice notice-error">
		<p>
			<?php esc_html_e( 'Easy IO cannot optimize your images while using a custom domain (CNAME) in WP Offload Media. Please disable the custom domain in the WP Offload Media settings.', 'ewww-image-optimizer' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Inform the user that we disabled SP AIO to prevent conflicts with ExactDN.
 */
function ewww_image_optimizer_notice_exactdn_sp_conflict() {
	echo "<div id='ewww-image-optimizer-exactdn-sp' class='notice notice-warning'><p>" . esc_html__( 'ShortPixel image optimization has been disabled to prevent conflicts with Easy IO (EWWW Image Optimizer).', 'ewww-image-optimizer' ) . '</p></div>';
}

/**
 * Display a notice that PHP version 7.2 will be required in a future version.
 */
function ewww_image_optimizer_php72_warning() {
	echo '<div id="ewww-image-optimizer-notice-php72" class="notice notice-info"><p><a href="https://docs.ewww.io/article/55-upgrading-php" target="_blank" data-beacon-article="5ab2baa6042863478ea7c2ae">' . esc_html__( 'The next release of EWWW Image Optimizer will require PHP 7.2 or greater. Newer versions of PHP are significantly faster and much more secure. If you are unsure how to upgrade to a supported version, ask your webhost for instructions.', 'ewww-image-optimizer' ) . '</a></p></div>';
}

/**
 * Lets the user know their network settings have been saved.
 */
function ewww_image_optimizer_network_settings_saved() {
	echo "<div id='ewww-image-optimizer-settings-saved' class='notice notice-success updated fade'><p><strong>" . esc_html__( 'Settings saved', 'ewww-image-optimizer' ) . '.</strong></p></div>';
}

/**
 * Warn the user that scheduled optimization will no longer work without background/async mode.
 */
function ewww_image_optimizer_notice_schedule_noasync() {
	global $ewwwio_upgrading;
	if ( $ewwwio_upgrading ) {
		return;
	}
	echo "<div id='ewww-image-optimizer-schedule-noasync' class='notice notice-warning'><p>" . esc_html__( 'Scheduled Optimization will not work without background/async ability. See the EWWW Image Optimizer Settings for further instructions.', 'ewww-image-optimizer' ) . '</p></div>';
}

/**
 * Informs the user about optimization during thumbnail regeneration.
 */
function ewww_image_optimizer_thumbnail_regen_notice() {
	echo "<div id='ewww-image-optimizer-thumb-regen-notice' class='notice notice-info is-dismissible'><p><strong>" . esc_html__( 'New thumbnails will be optimized by the EWWW Image Optimizer as they are generated. You may wish to disable the plugin and run a bulk optimize later to speed up the process.', 'ewww-image-optimizer' ) . '</strong>';
	echo '&nbsp;<a href="https://docs.ewww.io/article/49-regenerate-thumbnails" target="_blank" data-beacon-article="5a0f84ed2c7d3a272c0dc801">' . esc_html__( 'Learn more.', 'ewww-image-optimizer' ) . '</a></p></div>';
}

/**
 * Lets the user know WooCommerce has regenerated thumbnails and that they need to take action.
 */
function ewww_image_optimizer_notice_wc_regen() {
	echo "<div id='ewww-image-optimizer-wc-regen' class='notice notice-info is-dismissible'><p>" . esc_html__( 'EWWW Image Optimizer has detected a WooCommerce thumbnail regeneration. To optimize new thumbnails, you may run the Bulk Optimizer from the Media menu. This notice may be dismissed after the regeneration is complete.', 'ewww-image-optimizer' ) . '</p></div>';
}

/**
 * Loads the inline script to dismiss the WC regen notice.
 */
function ewww_image_optimizer_wc_regen_script() {
	echo
		"<script>\n" .
		"jQuery(document).on('click', '#ewww-image-optimizer-wc-regen .notice-dismiss', function() {\n" .
		"\tvar ewww_dismiss_wc_regen_data = {\n" .
		"\t\taction: 'ewww_dismiss_wc_regen',\n" .
		"\t};\n" .
		"\tjQuery.post(ajaxurl, ewww_dismiss_wc_regen_data, function(response) {\n" .
		"\t\tif (response) {\n" .
		"\t\t\tconsole.log(response);\n" .
		"\t\t}\n" .
		"\t});\n" .
		"});\n" .
		"</script>\n";
}

/**
 * Lets the user know LR Sync has regenerated thumbnails and that they need to take action.
 */
function ewww_image_optimizer_notice_lr_sync() {
	echo "<div id='ewww-image-optimizer-lr-sync' class='notice notice-info is-dismissible'><p>" . esc_html__( 'EWWW Image Optimizer has detected a WP/LR Sync process. To optimize new thumbnails, you may run the Bulk Optimizer from the Media menu. This notice may be dismissed after the Sync process is complete.', 'ewww-image-optimizer' ) . '</p></div>';
}

/**
 * Loads the inline script to dismiss the LR sync notice.
 */
function ewww_image_optimizer_lr_sync_script() {
	echo
		"<script>\n" .
		"jQuery(document).on('click', '#ewww-image-optimizer-lr-sync .notice-dismiss', function() {\n" .
		"\tvar ewww_dismiss_lr_sync_data = {\n" .
		"\t\taction: 'ewww_dismiss_lr_sync',\n" .
		"\t};\n" .
		"\tjQuery.post(ajaxurl, ewww_dismiss_lr_sync_data, function(response) {\n" .
		"\t\tif (response) {\n" .
		"\t\t\tconsole.log(response);\n" .
		"\t\t}\n" .
		"\t});\n" .
		"});\n" .
		"</script>\n";
}

/**
 * Let the user know they can view more options and stats in the Media Library's list mode.
 */
function ewww_image_optimizer_notice_media_listmode() {
	$current_screen = get_current_screen();
	if ( 'upload' === $current_screen->id && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_dismiss_media_notice' ) ) {
		$user_info = wp_get_current_user();
		if ( ! empty( $user_info->wp_media_library_mode ) && 'list' === $user_info->wp_media_library_mode ) {
			update_option( 'ewww_image_optimizer_dismiss_media_notice', true, false );
			update_site_option( 'ewww_image_optimizer_dismiss_media_notice', true );
			return;
		}
		?>
		<div id='ewww-image-optimizer-media-listmode' class='notice notice-info is-dismissible'>
			<p>
				<?php esc_html_e( 'Change the Media Library to List mode for additional image optimization information and actions.', 'ewww-image-optimizer' ); ?>
				<?php ewwwio_help_link( 'https://docs.ewww.io/article/62-power-user-options-in-list-mode', '5b61fdd32c7d3a03f89d41c4' ); ?>
			</p>
		</div>
		<?php
	}
}

/**
 * Instruct the user to run the db upgrade.
 */
function ewww_image_optimizer_620_upgrade_needed() {
	// $wpdb->query( "ALTER TABLE $wpdb->ewwwio_images MODIFY updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP" );
	echo "<div id='ewww-image-optimizer-upgrade-notice' class='notice notice-info'><p>" .
		esc_html__( 'EWWW Image Optimizer needs to upgrade the image log table.', 'ewww-image-optimizer' ) . '<br>' .
		'<a href="' . esc_url( admin_url( 'admin.php?action=ewww_image_optimizer_620_upgrade' ) ) . '" class="button-secondary">' .
		esc_html__( 'Upgrade', 'ewww-image-optimizer' ) . '</a>' .
		'</p></div>';
}

/**
 * Ask the user to leave a review for the plugin on wp.org.
 */
function ewww_image_optimizer_notice_review() {
	echo "<div id='ewww-image-optimizer-review' class='notice notice-info is-dismissible'><p>" .
		esc_html__( "Hi, you've been using the EWWW Image Optimizer for a while, and we hope it has been a big help for you.", 'ewww-image-optimizer' ) . '<br>' .
		esc_html__( 'If you could take a few moments to rate it on WordPress.org, we would really appreciate your help making the plugin better. Thanks!', 'ewww-image-optimizer' ) .
		'<br><a target="_blank" href="https://wordpress.org/support/plugin/ewww-image-optimizer/reviews/#new-post" class="button-secondary">' . esc_html__( 'Post Review', 'ewww-image-optimizer' ) . '</a>' .
		'</p></div>';
}

/**
 * Loads the inline script to dismiss the review notice.
 */
function ewww_image_optimizer_notice_review_script() {
	echo
		"<script>\n" .
		"jQuery(document).on('click', '#ewww-image-optimizer-review .notice-dismiss', function() {\n" .
		"\tvar ewww_dismiss_review_data = {\n" .
		"\t\taction: 'ewww_dismiss_review_notice',\n" .
		"\t};\n" .
		"\tjQuery.post(ajaxurl, ewww_dismiss_review_data, function(response) {\n" .
		"\t\tif (response) {\n" .
		"\t\t\tconsole.log(response);\n" .
		"\t\t}\n" .
		"\t});\n" .
		"});\n" .
		"</script>\n";
}

/**
 * Inform the user of our beacon function so that they can opt-in.
 */
function ewww_image_optimizer_notice_beacon() {
	$optin_url  = admin_url( 'admin.php?action=eio_opt_into_hs_beacon' );
	$optout_url = admin_url( 'admin.php?action=eio_opt_out_of_hs_beacon' );
	echo '<div id="ewww-image-optimizer-hs-beacon" class="notice notice-info"><p>' .
		esc_html__( 'Enable the EWWW I.O. support beacon, which gives you access to documentation and our support team right from your WordPress dashboard. To assist you more efficiently, we collect the current url, IP address, browser/device information, and debugging information.', 'ewww-image-optimizer' ) .
		'<br><a href="' . esc_url( $optin_url ) . '" class="button-secondary">' . esc_html__( 'Allow', 'ewww-image-optimizer' ) . '</a>' .
		'&nbsp;<a href="' . esc_url( $optout_url ) . '" class="button-secondary">' . esc_html__( 'Do not allow', 'ewww-image-optimizer' ) . '</a>' .
		'</p></div>';
}

/**
 * Alert the user when 5 images have been re-optimized more than 10 times.
 *
 * @global object $wpdb
 */
function ewww_image_optimizer_notice_reoptimization() {
	// Allows the user to reset all images back to 1 optimization, which clears the alert.
	if ( ! empty( $_GET['ewww_reset_reopt_nonce'] ) && wp_verify_nonce( sanitize_key( $_GET['ewww_reset_reopt_nonce'] ), 'reset_reoptimization_counters' ) ) {
		global $wpdb;
		$debug_images = $wpdb->query( "UPDATE $wpdb->ewwwio_images SET updates=1 WHERE updates > 1" );
		delete_transient( 'ewww_image_optimizer_images_reoptimized' );
	} else {
		$reoptimized = get_transient( 'ewww_image_optimizer_images_reoptimized' );
		if ( empty( $reoptimized ) ) {
			global $wpdb;
			$reoptimized = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->ewwwio_images WHERE updates > 5 AND path NOT LIKE '%wp-content/themes%' AND path NOT LIKE '%wp-content/plugins%'  LIMIT 10" );
			if ( empty( $reoptimized ) ) {
				set_transient( 'ewww_image_optimizer_images_reoptimized', 'zero', 12 * HOUR_IN_SECONDS );
			} else {
				set_transient( 'ewww_image_optimizer_images_reoptimized', $reoptimized, 12 * HOUR_IN_SECONDS );
			}
		} elseif ( 'zero' === $reoptimized ) {
			$reoptimized = 0;
		}
		// Do a check for 10+ optimizations on 5+ images.
		if ( ! empty( $reoptimized ) && $reoptimized > 5 ) {
			$debugging_page = admin_url( 'tools.php?page=ewww-image-optimizer-tools' );
			$reset_page     = wp_nonce_url( add_query_arg( null, null ), 'reset_reoptimization_counters', 'ewww_reset_reopt_nonce' );
			// Display an alert, and let the user reset the warning if they wish.
			echo "<div id='ewww-image-optimizer-warning-reoptimizations' class='error'><p>" .
				sprintf(
					/* translators: %s: A link to the EWWW IO Tools page */
					esc_html__( 'The EWWW Image Optimizer has detected excessive re-optimization of multiple images. Please use the %s page to Show Re-Optimized Images.', 'ewww-image-optimizer' ),
					"<a href='" . esc_url( $debugging_page ) . "'>" . esc_html__( 'Tools', 'ewww-image-optimizer' ) . '</a>'
				) .
				" <a href='" . esc_url( $reset_page ) . "'>" . esc_html__( 'Reset Counters' ) . '</a></p></div>';
		}
	}
}

/**
 * Loads the class to extend WP_Image_Editor for automatic optimization of generated images.
 *
 * @param array $editors List of image editors available to WordPress.
 * @return array Modified list of editors, with our custom class added at the top.
 */
function ewww_image_optimizer_load_editor( $editors ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( class_exists( 'S3_Uploads\Image_Editor_Imagick' ) ) {
		return $editors;
	}
	if ( ! class_exists( 'EWWWIO_GD_Editor' ) && ! class_exists( 'EWWWIO_Imagick_Editor' ) ) {
		if ( class_exists( 'WP_Image_Editor_GD' ) ) {
			require_once( plugin_dir_path( __FILE__ ) . '/classes/class-ewwwio-gd-editor.php' );
		}
		if ( class_exists( 'WP_Image_Editor_Imagick' ) ) {
			require_once( plugin_dir_path( __FILE__ ) . '/classes/class-ewwwio-imagick-editor.php' );
		}
		if ( class_exists( 'WP_Image_Editor_Gmagick' ) ) {
			require_once( plugin_dir_path( __FILE__ ) . '/classes/class-ewwwio-gmagick-editor.php' );
		}
	}
	if ( ! in_array( 'EWWWIO_GD_Editor', $editors, true ) ) {
		array_unshift( $editors, 'EWWWIO_GD_Editor' );
	}
	if ( ! in_array( 'EWWWIO_Imagick_Editor', $editors, true ) ) {
		array_unshift( $editors, 'EWWWIO_Imagick_Editor' );
	}
	if ( ! in_array( 'EWWWIO_Gmagick_Editor', $editors, true ) && class_exists( 'WP_Image_Editor_Gmagick' ) ) {
		array_unshift( $editors, 'EWWWIO_Gmagick_Editor' );
	}
	if ( is_array( $editors ) ) {
		ewwwio_debug_message( 'loading image editors: ' . implode( '<br>', $editors ) );
	}
	ewwwio_memory( __FUNCTION__ );
	return $editors;
}

/**
 * Registers the filter that will remove the image_editor hooks when an attachment is added.
 */
function ewww_image_optimizer_add_attachment() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	add_filter( 'intermediate_image_sizes_advanced', 'ewww_image_optimizer_image_sizes', 200 );
	add_filter( 'fallback_intermediate_image_sizes', 'ewww_image_optimizer_image_sizes', 200 );
}

/**
 * Removes the image editor filter, and adds a new filter that will restore it later.
 *
 * @param array $sizes A list of sizes to be generated by WordPress.
 * @return array The unaltered list of sizes to be generated.
 */
function ewww_image_optimizer_image_sizes( $sizes ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewww_preempt_editor;
	$ewww_preempt_editor = true;
	// This happens right after thumbs and meta are generated.
	add_filter( 'wp_generate_attachment_metadata', 'ewww_image_optimizer_restore_editor_hooks', 1 );
	add_filter( 'mpp_generate_metadata', 'ewww_image_optimizer_restore_editor_hooks', 1 );
	return $sizes;
}

/**
 * Restores the image editor filter after the resizes have been generated.
 *
 * Also removes the retina filter, and adds our own wrapper around the retina generation function.
 *
 * @param array $metadata The attachment metadata that has been generated.
 * @return array The unaltered attachment metadata.
 */
function ewww_image_optimizer_restore_editor_hooks( $metadata = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_noauto' ) && ( ! defined( 'EWWW_IMAGE_OPTIMIZER_DISABLE_EDITOR' ) || ! EWWW_IMAGE_OPTIMIZER_DISABLE_EDITOR ) ) {
		global $ewww_preempt_editor;
		$ewww_preempt_editor = false;
	}
	if ( function_exists( 'wr2x_wp_generate_attachment_metadata' ) ) {
		remove_filter( 'wp_generate_attachment_metadata', 'wr2x_wp_generate_attachment_metadata' );
		add_filter( 'wp_generate_attachment_metadata', 'ewww_image_optimizer_retina_wrapper' );
	}
	if ( class_exists( 'Meow_WR2X_Core' ) ) {
		global $wr2x_core;
		if ( is_object( $wr2x_core ) ) {
			ewwwio_debug_message( 'retina object found' );
			remove_filter( 'wp_generate_attachment_metadata', array( $wr2x_core, 'wp_generate_attachment_metadata' ) );
			add_filter( 'wp_generate_attachment_metadata', 'ewww_image_optimizer_retina_wrapper' );
		}
	}
	return $metadata;
}

/**
 * Removes image editor filter when an attachment is being saved, and adds a filter to restore it.
 *
 * This prevents resizes from being optimized prematurely when saving the new attachment.
 *
 * @param string $image The filename of the edited image.
 * @return string The unaltered filename.
 */
function ewww_image_optimizer_editor_save_pre( $image ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_noauto' ) && ( ! defined( 'EWWW_IMAGE_OPTIMIZER_DISABLE_EDITOR' ) || ! EWWW_IMAGE_OPTIMIZER_DISABLE_EDITOR ) ) {
		global $ewww_preempt_editor;
		$ewww_preempt_editor = true;
		add_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_restore_editor_hooks', 1 );
		add_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15, 2 );
	}
	add_filter( 'intermediate_image_sizes', 'ewww_image_optimizer_image_sizes_advanced' );
	return $image;
}

/**
 * Ensures that images saved with PTE are optimized.
 *
 * Checks for Post Thumbnail Editor's confirm, separate from crop&save, and registers a filter to
 * process any modified resizes.
 *
 * @param array $data The attachment metadata requested by PTE.
 * @return array The unaltered attachment metadata.
 */
function ewww_image_optimizer_pte_check( $data ) {
	if ( ! empty( $_GET['pte-action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'confirm-images' === $_GET['pte-action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			add_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15, 2 );
		}
	}
	return $data;
}

/**
 * Updates paths in the database after Media File Rename has run.
 *
 * @param array  $post The post information for the image attachment.
 * @param string $old_filepath The previous filename of the image.
 * @param string $new_filepath The new filename of the image.
 */
function ewww_image_optimizer_path_renamed( $post, $old_filepath, $new_filepath ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$optimized_query = ewww_image_optimizer_find_already_optimized( $old_filepath );
	if ( is_array( $optimized_query ) && ! empty( $optimized_query['id'] ) ) {
		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		ewwwio_debug_message( "$old_filepath changed to $new_filepath" );
		// Replace the 'temp' path in the database with the real path.
		$ewwwdb->update(
			$ewwwdb->ewwwio_images,
			array(
				'path' => ewww_image_optimizer_relativize_path( $new_filepath ),
			),
			array(
				'id' => $optimized_query['id'],
			)
		);
	}
	// Look for WebP variants and rename them.
	$old_webp = $old_filepath . '.webp';
	$new_webp = $new_filepath . '.webp';
	if ( ewwwio_is_file( $old_webp ) && ! ewwwio_is_file( $new_webp ) ) {
		ewwwio_debug_message( "renaming $old_webp to $new_webp" );
		ewwwio_rename( $old_webp, $new_webp );
	}
}

/**
 * Wraps around the retina generation function to prevent premature optimization.
 *
 * @since 3.3.0
 *
 * @param array $meta The attachment metadata.
 * @return array The unaltered metadata.
 */
function ewww_image_optimizer_retina_wrapper( $meta ) {
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_noauto' ) || ( defined( 'EWWW_IMAGE_OPTIMIZER_DISABLE_EDITOR' ) && EWWW_IMAGE_OPTIMIZER_DISABLE_EDITOR ) ) {
		return $meta;
	}
	global $ewww_preempt_editor;
	$ewww_preempt_editor = true;
	if ( class_exists( 'Meow_WR2X_Core' ) ) {
		global $wr2x_core;
		if ( is_object( $wr2x_core ) ) {
			$meta = $wr2x_core->wp_generate_attachment_metadata( $meta );
		}
	} else {
		$meta = wr2x_wp_generate_attachment_metadata( $meta );
	}
	$ewww_preempt_editor = false;
	return $meta;
}

/**
 * Filters image sizes generated by WordPress, themes, and plugins allowing users to disable sizes.
 *
 * @param array $sizes A list of sizes to be generated.
 * @return array A list of sizes, minus any the user wants disabled.
 */
function ewww_image_optimizer_image_sizes_advanced( $sizes ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes', false, true );
	$flipped        = false;
	if ( ! empty( $disabled_sizes ) ) {
		if ( ! empty( $sizes[0] ) ) {
			$sizes   = array_flip( $sizes );
			$flipped = true;
		}
		if ( ewww_image_optimizer_function_exists( 'print_r' ) ) {
			ewwwio_debug_message( print_r( $sizes, true ) );
		}
		if ( ewww_image_optimizer_iterable( $disabled_sizes ) ) {
			foreach ( $disabled_sizes as $size => $disabled ) {
				if ( ! empty( $disabled ) ) {
					ewwwio_debug_message( "size disabled: $size" );
					unset( $sizes[ $size ] );
				}
			}
		}
		if ( $flipped ) {
			$sizes = array_flip( $sizes );
		}
	}
	return $sizes;
}

/**
 * Filter the image previews generated for pdf files and other non-image types.
 *
 * @param array $sizes A list of sizes to be generated.
 * @return array A list of sizes, minus any the user wants disabled.
 */
function ewww_image_optimizer_fallback_sizes( $sizes ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes', false, true );
	$flipped        = false;
	if ( ! empty( $disabled_sizes ) ) {
		if ( ewww_image_optimizer_function_exists( 'print_r' ) ) {
			ewwwio_debug_message( print_r( $sizes, true ) );
		}
		if ( ewww_image_optimizer_iterable( $sizes ) && ewww_image_optimizer_iterable( $disabled_sizes ) ) {
			if ( ! empty( $disabled_sizes['pdf-full'] ) ) {
				return array();
			}
			foreach ( $sizes as $i => $size ) {
				if ( ! empty( $disabled_sizes[ $size ] ) ) {
					ewwwio_debug_message( "size disabled: $size" );
					unset( $sizes[ $i ] );
				}
			}
		}
	}
	return $sizes;
}

/**
 * Wrapper around the upload handler for MediaPress.
 *
 * @param array $params Parameters related to the file being uploaded.
 * @return array The unaltered parameters, we only need to pass them on.
 */
function ewww_image_optimizer_handle_mpp_upload( $params ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	add_filter( 'mpp_intermediate_image_sizes', 'ewww_image_optimizer_image_sizes', 200 );
	return ewww_image_optimizer_handle_upload( $params );
}

/**
 * During an upload, handles resizing, auto-rotation, and sets the 'new_image' global.
 *
 * @global bool $ewww_new_image True if there is a new image being uploaded.
 *
 * @param array $params Parameters related to the file being uploaded.
 * @return array The unaltered parameters, we only need to read them.
 */
function ewww_image_optimizer_handle_upload( $params ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ewww_image_optimizer_function_exists( 'print_r' ) ) {
		ewwwio_debug_message( print_r( $params, true ) );
	}
	global $ewww_new_image;
	$ewww_new_image = true;
	if ( empty( $params['file'] ) ) {
		if ( ! empty( $params['tmp_name'] ) ) {
			$file_path = $params['tmp_name'];
		} else {
			return $params;
		}
	} else {
		$file_path = $params['file'];
	}
	if ( ! ewwwio_is_file( $file_path ) || ! filesize( $file_path ) ) {
		clearstatcache();
		return $params;
	}

	$orig_size = ewww_image_optimizer_filesize( $file_path );

	ewww_image_optimizer_autorotate( $file_path );
	$new_image = ewww_image_optimizer_autoconvert( $file_path );
	if ( $new_image ) {
		if ( ! empty( $params['tmp_name'] ) && $params['tmp_name'] === $file_path ) {
			$params['tmp_name'] = $new_image;
		}
		if ( ! empty( $params['file'] ) && $params['file'] === $file_path ) {
			$params['file'] = $new_image;
		}
		if ( ! empty( $params['url'] ) && basename( $file_path ) === basename( $params['url'] ) ) {
			$params['url'] = trailingslashit( dirname( $params['url'] ) ) . basename( $new_image );
		}
		$params['type'] = ewww_image_optimizer_mimetype( $new_image, 'i' );
		if ( ewwwio_is_file( $file_path ) ) {
			unlink( $file_path );
		}
		$file_path = $new_image;
	}
	// Resize here unless the user chose to defer resizing or imsanity is enabled with a max size.
	if ( ! apply_filters( 'ewww_image_optimizer_defer_resizing', false ) && ! function_exists( 'imsanity_get_max_width_height' ) ) {
		if ( empty( $params['type'] ) ) {
			$mime_type = ewww_image_optimizer_mimetype( $file_path, 'i' );
		} else {
			$mime_type = $params['type'];
		}
		if ( ( ! is_wp_error( $params ) ) && ewwwio_is_file( $file_path ) && in_array( $mime_type, array( 'image/png', 'image/gif', 'image/jpeg', 'image/webp' ), true ) ) {
			ewww_image_optimizer_resize_upload( $file_path );
		}
	}
	clearstatcache();
	if ( ! empty( $orig_size ) && $orig_size > ewww_image_optimizer_filesize( $file_path ) ) {
		ewwwio_debug_message( "stashing $orig_size for $file_path" );
		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		$already_optimized = ewww_image_optimizer_find_already_optimized( $file_path );
		if ( empty( $already_optimized ) ) {
			// If the file didn't already get optimized (and it shouldn't), then just insert a dummy record to be updated shortly.
			ewwwio_debug_message( 'creating new record' );
			$dbinserted = $ewwwdb->insert(
				$ewwwdb->ewwwio_images,
				array(
					'path'      => ewww_image_optimizer_relativize_path( $file_path ),
					'orig_size' => $orig_size,
				)
			);
			if ( $dbinserted ) {
				ewwwio_debug_message( 'insert success' );
			}
		} else {
			// Update the existing record.
			ewwwio_debug_message( 'updating existing record' );
			$dbupdated = $ewwwdb->update(
				$ewwwdb->ewwwio_images,
				array(
					'orig_size' => $orig_size,
				),
				array(
					'path' => ewww_image_optimizer_relativize_path( $file_path ),
				)
			);
			if ( $dbupdated ) {
				ewwwio_debug_message( 'update success' );
			}
		}
	}
	return $params;
}

/**
 * Makes sure W3TC uploads all modified files to any configured CDNs.
 *
 * @global array $ewww_attachment {
 *     Stores the ID and meta for later use with W3TC.
 *
 *     @type int $id The attachment ID number.
 *     @type array $meta The attachment metadata from the postmeta table.
 * }
 *
 * @param array $files Files being updated by W3TC.
 * @return array Original array plus information about full-size image so that it also is updated.
 */
function ewww_image_optimizer_w3tc_update_files( $files ) {
	global $ewww_attachment;
	list( $file, $upload_path ) = ewww_image_optimizer_attachment_path( $ewww_attachment['meta'], $ewww_attachment['id'] );
	if ( function_exists( 'w3_upload_info' ) ) {
		$upload_info = w3_upload_info();
	} else {
		$upload_info = ewww_image_optimizer_upload_info();
	}
	$file_info = array();
	if ( $upload_info ) {
		$remote_file  = ltrim( $upload_info['baseurlpath'] . $ewww_attachment['meta']['file'], '/' );
		$home_url     = get_site_url();
		$original_url = $home_url . $file;
		$file_info[]  = array(
			'local_path'   => $file,
			'remote_path'  => $remote_file,
			'original_url' => $original_url,
		);
		$files        = array_merge( $files, $file_info );
	}
	return $files;
}

/**
 * Wrapper around wp_upload_dir that adds the base url to the uploads folder as 'baseurlpath' key.
 *
 * @return array|bool Information about the uploads dir, or false on failure.
 */
function ewww_image_optimizer_upload_info() {
	$upload_info = wp_get_upload_dir();

	if ( empty( $upload_info['error'] ) ) {
		$parse_url = parse_url( $upload_info['baseurl'] );
		if ( $parse_url ) {
			$baseurlpath = ( ! empty( $parse_url['path'] ) ? trim( $parse_url['path'], '/' ) : '' );
		} else {
			$baseurlpath = 'wp-content/uploads';
		}
		$upload_info['baseurlpath'] = '/' . $baseurlpath . '/';
	} else {
		$upload_info = false;
	}
	return $upload_info;
}

/**
 * Runs scheduled optimization of various images.
 *
 * Regularly compresses any preconfigured folders including Buddypress, the active theme,
 * metaslider, and WP Symposium. Also includes any user-configured folders, along with the last two
 * months of media uploads.
 *
 * @global bool $ewww_defer Gets set to false to make sure optimization happens inline.
 * @global object $wpdb
 * @global object $ewwwdb A clone of $wpdb unless it is lacking utf8 connectivity.
 */
function ewww_image_optimizer_auto() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( get_transient( 'ewww_image_optimizer_no_scheduled_optimization' ) ) {
		ewwwio_debug_message( 'detected bulk operation in progress, bailing' );
		return;
	}
	if ( ! class_exists( 'WP_Background_Process' ) ) {
		require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
	}
	global $ewww_defer;
	$ewww_defer = false;
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'bulk.php' );
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'aux-optimize.php' );
	global $ewwwio_image_background;
	if ( $ewwwio_image_background->is_process_running() || $ewwwio_image_background->count_queue() ) {
		return;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_auto' ) ) {
		ewwwio_debug_message( 'running scheduled optimization' );
		global $ewwwio_scan_async;
		$ewwwio_scan_async->data(
			array(
				'ewww_scan' => 'scheduled',
			)
		)->dispatch();
		return;
		ewww_image_optimizer_aux_images_script( 'ewww-image-optimizer-auto' );
		// Generate our own unique nonce value, because wp_create_nonce() will return the same value for 12-24 hours.
		$nonce = wp_hash( time() . '|' . 'ewww-image-optimizer-auto' );
		update_option( 'ewww_image_optimizer_aux_resume', $nonce );
		$delay = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' );
		$count = ewww_image_optimizer_aux_images_table_count_pending();
		if ( ! empty( $count ) ) {
			global $wpdb;
			if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
				ewww_image_optimizer_db_init();
				global $ewwwdb;
			} else {
				$ewwwdb = $wpdb;
			}
			$i          = 0;
			$attachment = $ewwwdb->get_row( "SELECT id,path FROM $ewwwdb->ewwwio_images WHERE pending=1 LIMIT 1", ARRAY_A );
			while ( $i < $count && $attachment ) {
				// If the nonce has changed since we started, bail out, since that means another aux scan/optimize is running.
				// Do a direct query using $wpdb, because get_option() is cached.
				$current_nonce = $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'ewww_image_optimizer_aux_resume'" );
				if ( $nonce !== $current_nonce ) {
					ewwwio_debug_message( 'detected another optimization, nonce changed, bailing' );
					ewww_image_optimizer_debug_log();
					return;
				} else {
					ewwwio_debug_message( "$nonce is fine, compared to $current_nonce" );
				}
				if ( ! empty( $attachment['path'] ) ) {
					$attachment['path'] = ewww_image_optimizer_absolutize_path( $attachment['path'] );
				}
				ewww_image_optimizer_aux_images_loop( $attachment, true );
				if ( ! empty( $delay ) && ewww_image_optimizer_function_exists( 'sleep' ) ) {
					sleep( $delay );
				}
				$attachment = $ewwwdb->get_row( "SELECT id,path FROM $ewwwdb->ewwwio_images WHERE pending=1 LIMIT 1", ARRAY_A );
				ewww_image_optimizer_debug_log();
				$i++;
			}
		}
		ewww_image_optimizer_aux_images_cleanup( true );
	} // End if().
	ewwwio_memory( __FUNCTION__ );
	return;
}

/**
 * Simulates regenerating a resize for an attachment.
 */
function ewww_image_optimizer_resize_dup_check() {
	$meta = wp_get_attachment_metadata( 34 );

	list( $file, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, 34 );

	$editor        = wp_get_image_editor( $file );
	$resized_image = $editor->resize( 150, 150, true );
	$new_file      = $editor->generate_filename();
	echo esc_html( $new_file );
	if ( ewwwio_is_file( $new_file ) ) {
		echo '<br>file already exists<br>';
	}
	$saved = $editor->save( $new_file );
}

/**
 * Clears scheduled jobs for multisite when the plugin is deactivated.
 *
 * @global object $wpdb
 *
 * @param bool $network_wide True if plugin was network-activated.
 */
function ewww_image_optimizer_network_deactivate( $network_wide ) {
	global $wpdb;
	wp_clear_scheduled_hook( 'ewww_image_optimizer_auto' );
	wp_clear_scheduled_hook( 'ewww_image_optimizer_defer' );
	// Un-configure Autoptimize CDN domain.
	if ( defined( 'AUTOPTIMIZE_PLUGIN_DIR' ) && strpos( ewww_image_optimizer_get_option( 'autoptimize_cdn_url' ), 'exactdn' ) ) {
		ewww_image_optimizer_set_option( 'autoptimize_cdn_url', '' );
	}
	if ( $network_wide ) {
		$blogs = $wpdb->get_results( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs WHERE site_id = %d", $wpdb->siteid ), ARRAY_A );
		if ( ewww_image_optimizer_iterable( $blogs ) ) {
			foreach ( $blogs as $blog ) {
				switch_to_blog( $blog['blog_id'] );
				wp_clear_scheduled_hook( 'ewww_image_optimizer_auto' );
				wp_clear_scheduled_hook( 'ewww_image_optimizer_defer' );
				// Un-configure Autoptimize CDN domain.
				if ( defined( 'AUTOPTIMIZE_PLUGIN_DIR' ) && strpos( ewww_image_optimizer_get_option( 'autoptimize_cdn_url' ), 'exactdn' ) ) {
					ewww_image_optimizer_set_option( 'autoptimize_cdn_url', '' );
				}
				restore_current_blog();
			}
		}
	}
}

/**
 * Adds a global settings page to the network admin settings menu.
 */
function ewww_image_optimizer_network_admin_menu() {
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		$permissions = apply_filters( 'ewww_image_optimizer_superadmin_permissions', '' );
		// Add options page to the settings menu.
		$ewww_network_options_page = add_submenu_page(
			'settings.php',                        // Slug of parent.
			'EWWW Image Optimizer',                // Page Title.
			'EWWW Image Optimizer',                // Menu title.
			$permissions,                          // Capability.
			'ewww-image-optimizer-options',        // Slug.
			'ewww_image_optimizer_network_options' // Function to call.
		);
	}
}

/**
 * Adds various items to the admin menu.
 */
function ewww_image_optimizer_admin_menu() {
	// Adds bulk optimize to the media library menu.
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if (
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ||
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ||
		! ewww_image_optimizer_exec_check()
	) {
		add_media_page( esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ), esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ), $permissions, 'ewww-image-optimizer-bulk', 'ewww_image_optimizer_bulk_preview' );
		// Adds Bulk Optimize to the media library bulk actions.
		add_filter( 'bulk_actions-upload', 'ewww_image_optimizer_add_bulk_media_actions' );
	}
	add_submenu_page( null, esc_html__( 'Migrate WebP Images', 'ewww-image-optimizer' ), esc_html__( 'Migrate WebP Images', 'ewww-image-optimizer' ), $permissions, 'ewww-image-optimizer-webp-migrate', 'ewww_image_optimizer_webp_migrate_preview' );

	// Add tools page.
	add_management_page(
		'EWWW Image Optimizer',                                                      // Page title.
		'EWWW Image Optimizer',                                                      // Menu title.
		apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' ), // Capability.
		'ewww-image-optimizer-tools',                                            // Slug.
		'ewww_image_optimizer_display_tools'                            // Function to call.
	);
	if ( ! function_exists( 'is_plugin_active' ) ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( ! is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		// Add options page to the settings menu.
		add_options_page(
			'EWWW Image Optimizer',                                                      // Page title.
			'EWWW Image Optimizer',                                                      // Menu title.
			apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' ), // Capability.
			'ewww-image-optimizer-options',                                              // Slug.
			'ewww_image_optimizer_options'                                               // Function to call.
		);
	} else {
		// Add options page to the single-site settings menu.
		add_options_page(
			'EWWW Image Optimizer',                                                      // Page title.
			'EWWW Image Optimizer',                                                      // Menu title.
			apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' ), // Capability.
			'ewww-image-optimizer-options',                                              // Slug.
			'ewww_image_optimizer_network_singlesite_options'                            // Function to call.
		);
	}
}

/**
 * Checks WP Retina images to fix filenames in the database.
 *
 * @param int    $id The attachment ID with which this retina image is associated.
 * @param string $retina_path The filename of the retina image that was generated.
 */
function ewww_image_optimizer_retina( $id, $retina_path ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$file_info = pathinfo( $retina_path );
	$extension = '.' . $file_info['extension'];
	preg_match( '/-(\d+x\d+)@2x$/', $file_info['filename'], $fileresize );
	$dimensions  = explode( 'x', $fileresize[1] );
	$no_ext_path = $file_info['dirname'] . '/' . preg_replace( '/\d+x\d+@2x$/', '', $file_info['filename'] ) . $dimensions[0] * 2 . 'x' . $dimensions[1] * 2 . '-tmp';
	$temp_path   = $no_ext_path . $extension;
	ewwwio_debug_message( "temp path: $temp_path" );
	// Check for any orphaned webp retina images, and fix their paths.
	ewwwio_debug_message( "retina path: $retina_path" );
	$webp_path = $temp_path . '.webp';
	ewwwio_debug_message( "retina webp path: $webp_path" );
	if ( ewwwio_is_file( $webp_path ) ) {
		ewwwio_rename( $webp_path, $retina_path . '.webp' );
	}
	$opt_size = ewww_image_optimizer_filesize( $retina_path );
	ewwwio_debug_message( "retina size: $opt_size" );
	$optimized_query = ewww_image_optimizer_find_already_optimized( $temp_path );
	if ( is_array( $optimized_query ) && $optimized_query['image_size'] === $opt_size ) {
		global $wpdb;
		if ( false === strpos( $wpdb->charset, 'utf8' ) ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		// Replace the 'temp' path in the database with the real path.
		$ewwwdb->update(
			$ewwwdb->ewwwio_images,
			array(
				'path'          => ewww_image_optimizer_relativize_path( $retina_path ),
				'attachment_id' => $id,
				'gallery'       => 'media',
			),
			array(
				'id' => $optimized_query['id'],
			)
		);
	}
	ewwwio_memory( __FUNCTION__ );
}

/**
 * Optimize MyArcade screenshots and thumbs.
 *
 * @param string $url The address of the image to be processed.
 * @return string The unaltered url/address.
 */
function ewww_image_optimizer_myarcade_thumbnail( $url ) {
	ewwwio_debug_message( "thumb url passed: $url" );
	if ( ! empty( $url ) ) {
		$thumb_path = str_replace( get_option( 'siteurl' ) . '/', ABSPATH, $url );
		ewwwio_debug_message( "myarcade thumb path generated: $thumb_path" );
		ewww_image_optimizer( $thumb_path );
	}
	return $url;
}

/**
 * Enqueue custom jquery stylesheet and scripts for the media library AJAX functions.
 *
 * @param string $hook The unique hook of the page being loaded in the WP admin.
 */
function ewww_image_optimizer_media_scripts( $hook ) {
	if ( 'upload.php' === $hook || 'ims_gallery_page_ewww-ims-optimize' === $hook ) {
		add_thickbox();
		wp_enqueue_script( 'jquery-ui-tooltip' );
		wp_enqueue_script( 'ewww-media-script', plugins_url( '/includes/media.js', __FILE__ ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION );
		wp_enqueue_style( 'jquery-ui-tooltip-custom', plugins_url( '/includes/jquery-ui-1.10.1.custom.css', __FILE__ ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
		// Submit a couple variables to the javascript to work with.
		$loading_image = plugins_url( '/images/spinner.gif', __FILE__ );
		wp_localize_script(
			'ewww-media-script',
			'ewww_vars',
			array(
				'optimizing' => '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' /></p>",
				'restoring'  => '<p>' . esc_html__( 'Restoring', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' /></p>",
			)
		);
	}
}

/**
 * Gets the link to the main EWWW IO settings.
 *
 * @return string The link to the main settings (network vs. single-site).
 */
function ewww_image_optimizer_get_settings_link() {
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	// Load the html for the settings link.
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) && ! get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) ) {
		return network_admin_url( 'settings.php?page=ewww-image-optimizer-options' );
	} else {
		return admin_url( 'options-general.php?page=ewww-image-optimizer-options' );
	}
}

/**
 * Adds a link on the Plugins page for the EWWW IO settings.
 *
 * @param array $links A list of links to display next to the plugin listing.
 * @return array The new list of links to be displayed.
 */
function ewww_image_optimizer_settings_link( $links ) {
	if ( ! is_array( $links ) ) {
		$links = array();
	}
	$settings_link = '<a href="' . ewww_image_optimizer_get_settings_link() . '">' . esc_html__( 'Settings', 'ewww-image-optimizer' ) . '</a>';
	// Load the settings link into the plugin links array.
	array_unshift( $links, $settings_link );
	// Send back the plugin links array.
	return $links;
}

/**
 * Check for GD support of both PNG and JPG.
 *
 * @param bool $cache Whether to use a cached result.
 * @return string The version of GD if full support is detected.
 */
function ewww_image_optimizer_gd_support( $cache = true ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( function_exists( 'gd_info' ) ) {
		$gd_support = gd_info();
		ewwwio_debug_message( 'GD found, supports:' );
		if ( ewww_image_optimizer_iterable( $gd_support ) ) {
			foreach ( $gd_support as $supports => $supported ) {
				ewwwio_debug_message( "$supports: $supported" );
			}
			ewwwio_memory( __FUNCTION__ );
			if ( ( ! empty( $gd_support['JPEG Support'] ) || ! empty( $gd_support['JPG Support'] ) ) && ! empty( $gd_support['PNG Support'] ) ) {
				return ! empty( $gd_support['GD Version'] ) ? $gd_support['GD Version'] : '1';
			}
		}
	}
	return false;
}

/**
 * Check for GD support of WebP format.
 *
 * @return bool True if proper WebP support is detected.
 */
function ewww_image_optimizer_gd_supports_webp() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$gd_version = ewww_image_optimizer_gd_support();
	if ( $gd_version ) {
		if (
			function_exists( 'imagewebp' ) &&
			function_exists( 'imagepalettetotruecolor' ) &&
			function_exists( 'imageistruecolor' ) &&
			function_exists( 'imagealphablending' ) &&
			function_exists( 'imagesavealpha' )
		) {
			if ( version_compare( $gd_version, '2.2.5', '>=' ) ) {
				ewwwio_debug_message( 'yes it does' );
				return true;
			}
		}
	}
	if ( ! function_exists( 'imagewebp' ) ) {
		ewwwio_debug_message( 'imagewebp() missing' );
	} elseif ( ! function_exists( 'imagepalettetotruecolor' ) ) {
		ewwwio_debug_message( 'imagepalettetotruecolor() missing' );
	} elseif ( function_exists( 'imageistruecolor' ) ) {
		ewwwio_debug_message( 'imageistruecolor() missing' );
	} elseif ( function_exists( 'imagealphablending' ) ) {
		ewwwio_debug_message( 'imagealphablending() missing' );
	} elseif ( function_exists( 'imagesavealpha' ) ) {
		ewwwio_debug_message( 'imagesavealpha() missing' );
	} elseif ( $gd_version ) {
		ewwwio_debug_message( "version: $gd_version" );
	}
	ewwwio_debug_message( 'sorry nope' );
	return false;
}

/**
 * Use GD to convert an image to WebP.
 *
 * @param string $file The original source image path.
 * @param string $type The mime-type of the original image.
 * @param string $webpfile The location to store the new WebP image.
 */
function ewww_image_optimizer_gd_create_webp( $file, $type, $webpfile ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$quality = (int) apply_filters( 'webp_quality', 75, 'image/webp' );
	if ( $quality < 50 || $quality > 100 ) {
		$quality = 75;
	}
	switch ( $type ) {
		case 'image/jpeg':
			$image = imagecreatefromjpeg( $file );
			if ( false === $image ) {
				return;
			}
			break;
		case 'image/png':
			$image = imagecreatefrompng( $file );
			if ( false === $image ) {
				return;
			}
			if ( ! imageistruecolor( $image ) ) {
				ewwwio_debug_message( 'converting to true color' );
				imagepalettetotruecolor( $image );
			}
			if ( ewww_image_optimizer_png_alpha( $file ) ) {
				ewwwio_debug_message( 'saving alpha and disabling alpha blending' );
				imagealphablending( $image, false );
				imagesavealpha( $image, true );
			}
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_LOSSY_PNG2WEBP' ) || ! EWWW_IMAGE_OPTIMIZER_LOSSY_PNG2WEBP ) {
				$quality = 100;
			}
			break;
		default:
			return;
	}
	ewwwio_debug_message( "creating $webpfile with quality $quality" );
	$result = imagewebp( $image, $webpfile, $quality );
	// Make sure to cleanup--if $webpfile is borked, that will be handled elsewhere.
	imagedestroy( $image );
}

/**
 * Check for IMagick support of both PNG and JPG.
 *
 * @return bool True if full Imagick support is detected.
 */
function ewww_image_optimizer_imagick_support() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
		$imagick = new Imagick();
		$formats = $imagick->queryFormats();
		ewwwio_debug_message( implode( ',', $formats ) );
		if ( in_array( 'PNG', $formats, true ) && in_array( 'JPG', $formats, true ) ) {
			return true;
		}
		ewwwio_debug_message( 'imagick found, but PNG or JPG not supported' );
	}
	return false;
}

/**
 * Check for IMagick support of WebP.
 *
 * @return bool True if WebP support is detected.
 */
function ewww_image_optimizer_imagick_supports_webp() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ewww_image_optimizer_imagick_support() ) {
		$imagick = new Imagick();
		$formats = $imagick->queryFormats();
		if ( in_array( 'WEBP', $formats, true ) ) {
			ewwwio_debug_message( 'yes it does' );
			return true;
		}
	}
	ewwwio_debug_message( 'sorry nope' );
	return false;
}

/**
 * Use IMagick to convert an image to WebP.
 *
 * @param string $file The original source image path.
 * @param string $type The mime-type of the original image.
 * @param string $webpfile The location to store the new WebP image.
 */
function ewww_image_optimizer_imagick_create_webp( $file, $type, $webpfile ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$sharp_yuv = defined( 'EIO_WEBP_SHARP_YUV' ) && EIO_WEBP_SHARP_YUV ? true : false;
	$quality   = (int) apply_filters( 'webp_quality', 75, 'image/webp' );
	if ( $quality < 50 || $quality > 100 ) {
		$quality = 75;
	}
	$profiles = array();
	switch ( $type ) {
		case 'image/jpeg':
			$image = new Imagick( $file );
			if ( false === $image ) {
				return;
			}
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ) {
				// Getting possible color profiles.
				$profiles = $image->getImageProfiles( 'icc', true );
			}
			$color = $image->getImageColorspace();
			ewwwio_debug_message( "color space is $color" );
			if ( Imagick::COLORSPACE_CMYK === $color ) {
				ewwwio_debug_message( 'found CMYK image' );
				if ( ewwwio_is_file( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'vendor/icc/sRGB2014.icc' ) ) {
					ewwwio_debug_message( 'adding icc profile' );
					$icc_profile = file_get_contents( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'vendor/icc/sRGB2014.icc' );
					$image->profileImage( 'icc', $icc_profile );
				}
				ewwwio_debug_message( 'attempting SRGB transform' );
				$image->transformImageColorspace( Imagick::COLORSPACE_SRGB );
				ewwwio_debug_message( 'removing icc profile' );
				$image->setImageProfile( '*', null );
				$profiles = array();
			}
			$image->setImageFormat( 'WEBP' );
			if ( $sharp_yuv ) {
				ewwwio_debug_message( 'enabling sharp_yuv' );
				$image->setOption( 'webp:use-sharp-yuv', 'true' );
			}
			ewwwio_debug_message( "setting quality to $quality" );
			$image->setImageCompressionQuality( $quality );
			break;
		case 'image/png':
			$image = new Imagick( $file );
			if ( false === $image ) {
				return;
			}
			$image->setImageFormat( 'WEBP' );
			if ( defined( 'EWWW_IMAGE_OPTIMIZER_LOSSY_PNG2WEBP' ) && EWWW_IMAGE_OPTIMIZER_LOSSY_PNG2WEBP ) {
				ewwwio_debug_message( 'doing lossy conversion' );
				if ( $sharp_yuv ) {
					ewwwio_debug_message( 'enabling sharp_yuv' );
					$image->setOption( 'webp:use-sharp-yuv', 'true' );
				}
				ewwwio_debug_message( "setting quality to $quality" );
				$image->setImageCompressionQuality( $quality );
			} else {
				ewwwio_debug_message( 'sticking to lossless' );
				$image->setOption( 'webp:lossless', true );
				$image->setOption( 'webp:alpha-quality', 100 );
			}
			break;
		default:
			return;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ) {
		ewwwio_debug_message( 'removing meta' );
		$image->stripImage();
		if ( ! empty( $profiles ) ) {
			ewwwio_debug_message( 'adding color profile to WebP' );
			$image->profileImage( 'icc', $profiles['icc'] );
		}
	}
	ewwwio_debug_message( 'getting blob' );
	$image_blob = $image->getImageBlob();
	ewwwio_debug_message( 'writing file' );
	file_put_contents( $webpfile, $image_blob );
}

/**
 * Check for GMagick support of both PNG and JPG.
 *
 * @return bool True if full Gmagick support is detected.
 */
function ewww_image_optimizer_gmagick_support() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( extension_loaded( 'gmagick' ) && class_exists( 'Gmagick' ) ) {
		$gmagick = new Gmagick();
		$formats = $gmagick->queryFormats();
		ewwwio_debug_message( implode( ',', $formats ) );
		if ( in_array( 'PNG', $formats, true ) && in_array( 'JPG', $formats, true ) ) {
			return true;
		}
		ewwwio_debug_message( 'gmagick found, but PNG or JPG not supported' );
	}
	return false;
}

/**
 * Filter the filename past any folders the user chose to ignore.
 *
 * @param bool   $bypass True to skip optimization, defaults to false.
 * @param string $filename The file about to be optimized.
 * @return bool True if the file matches any folders to ignore.
 */
function ewww_image_optimizer_ignore_file( $bypass, $filename ) {
	$ignore_folders = ewww_image_optimizer_get_option( 'ewww_image_optimizer_exclude_paths' );
	if ( ! ewww_image_optimizer_iterable( $ignore_folders ) ) {
		return $bypass;
	}
	foreach ( $ignore_folders as $ignore_folder ) {
		if ( strpos( $filename, $ignore_folder ) !== false ) {
			return true;
		}
	}
	return $bypass;
}

/**
 * Add a file to the exclusions list.
 *
 * @param string $file_path The path of the file to ignore.
 */
function ewww_image_optimizer_add_file_exclusion( $file_path ) {
	if ( ! is_string( $file_path ) ) {
		return;
	}
	// Add it to the files to ignore.
	$ignore_folders = ewww_image_optimizer_get_option( 'ewww_image_optimizer_exclude_paths' );
	if ( ! is_array( $ignore_folders ) ) {
		$ignore_folders = array();
	}
	$file_path        = str_replace( ABSPATH, '', $file_path );
	$file_path        = str_replace( WP_CONTENT_DIR, '', $file_path );
	$ignore_folders[] = $file_path;
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_exclude_paths', $ignore_folders );
}

/**
 * Sanitize the list of disabled resizes.
 *
 * @param array $disabled_resizes A list of sizes, like 'medium_large', 'thumb', etc.
 * @return array|string The sanitized list of sizes, or an empty string.
 */
function ewww_image_optimizer_disable_resizes_sanitize( $disabled_resizes ) {
	if ( is_array( $disabled_resizes ) ) {
		return $disabled_resizes;
	} else {
		return '';
	}
}

/**
 * Sanitize the list of folders to optimize.
 *
 * @param string $input A list of filesystem paths, from a textarea.
 * @return array The list of paths, validated, and converted to an array.
 */
function ewww_image_optimizer_aux_paths_sanitize( $input ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $input ) ) {
		return '';
	}
	$path_array = array();
	if ( is_array( $input ) ) {
		$paths = $input;
	} elseif ( is_string( $input ) ) {
		$paths = explode( "\n", $input );
	}
	$abspath     = false;
	$permissions = apply_filters( 'ewww_image_optimizer_superadmin_permissions', '' );
	if ( is_multisite() && current_user_can( $permissions ) ) {
		$abspath = true;
	} elseif ( ! is_multisite() ) {
		$abspath = true;
	}
	$blog_one = false;
	if ( 1 === get_current_blog_id() ) {
		$blog_one = true;
	}
	if ( ewww_image_optimizer_iterable( $paths ) ) {
		$i = 0;
		foreach ( $paths as $path ) {
			$i++;
			$path = sanitize_text_field( $path );
			ewwwio_debug_message( "validating auxiliary path: $path" );
			// Retrieve the location of the WordPress upload folder.
			$upload_dir = wp_get_upload_dir();
			// Retrieve the path of the upload folder from the array.
			$upload_path = trailingslashit( $upload_dir['basedir'] );
			if ( ! $abspath && $blog_one && false !== strpos( $path, $upload_path . 'sites' ) ) {
				add_settings_error(
					'ewww_image_optimizer_aux_paths',
					"ewwwio-aux-paths-$i",
					sprintf(
						/* translators: %s: A file system path */
						esc_html__( 'Could not save Folder to Optimize: %s. Access denied.', 'ewww-image-optimizer' ),
						esc_html( $path )
					)
				);
				continue;
			}
			if ( ( ( $abspath && strpos( $path, ABSPATH ) === 0 ) || strpos( $path, $upload_path ) === 0 ) && ewwwio_is_dir( $path ) ) {
				$path_array[] = $path;
				continue;
			}
			// If they put in a relative path.
			if ( $abspath && ewwwio_is_dir( ABSPATH . ltrim( $path, '/' ) ) ) {
				$path_array[] = ABSPATH . ltrim( $path, '/' );
				continue;
			}
			// Or a path relative to the upload dir?
			if ( ewwwio_is_dir( $upload_path . ltrim( $path, '/' ) ) ) {
				$path_array[] = $upload_path . ltrim( $path, '/' );
				continue;
			}
			// What if they put in a url?
			$pathabsurl = ABSPATH . ltrim( str_replace( get_site_url(), '', $path ), '/' );
			if ( $abspath && ewwwio_is_dir( $pathabsurl ) ) {
				$path_array[] = $pathabsurl;
				continue;
			}
			// Or a url in the uploads folder?
			$pathupurl = $upload_path . ltrim( str_replace( $upload_dir['baseurl'], '', $path ), '/' );
			if ( ewwwio_is_dir( $pathupurl ) ) {
				$path_array[] = $pathupurl;
				continue;
			}
			if ( ! empty( $path ) ) {
				add_settings_error(
					'ewww_image_optimizer_aux_paths',
					"ewwwio-aux-paths-$i",
					sprintf(
						/* translators: %s: A file system path */
						esc_html__( 'Could not save Folder to Optimize: %s. Please ensure that it is a valid location on the server.', 'ewww-image-optimizer' ),
						esc_html( $path )
					)
				);
			}
		} // End foreach().
	} // End if().
	ewwwio_memory( __FUNCTION__ );
	return $path_array;
}

/**
 * Sanitize the folders/patterns to exclude from optimization.
 *
 * @param string $input A list of filesystem paths, from a textarea.
 * @return array The sanitized list of paths/patterns to exclude.
 */
function ewww_image_optimizer_exclude_paths_sanitize( $input ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $input ) ) {
		return '';
	}
	$path_array = array();
	if ( is_array( $input ) ) {
		$paths = $input;
	} elseif ( is_string( $input ) ) {
		$paths = explode( "\n", $input );
	}
	if ( ewww_image_optimizer_iterable( $paths ) ) {
		$i = 0;
		foreach ( $paths as $path ) {
			$i++;
			ewwwio_debug_message( "validating path exclusion: $path" );
			$path = trim( sanitize_text_field( $path ), '*' );
			if ( ! empty( $path ) ) {
				$path_array[] = $path;
			}
		}
	}
	ewwwio_memory( __FUNCTION__ );
	return $path_array;
}

/**
 * Sanitize the url patterns used for WebP rewriting.
 *
 * @param string $paths A list of urls/patterns, from a textarea.
 * @return array The sanitize list of url patterns for WebP matching.
 */
function ewww_image_optimizer_webp_paths_sanitize( $paths ) {
	if ( empty( $paths ) ) {
		return '';
	}
	$paths_saved = array();
	if ( is_array( $paths ) ) {
		$paths_entered = $paths;
	} elseif ( is_string( $paths ) ) {
		$paths_entered = explode( "\n", $paths );
	}
	if ( ewww_image_optimizer_iterable( $paths_entered ) ) {
		$i = 0;
		foreach ( $paths_entered as $path ) {
			$i++;
			$original_path = esc_html( trim( $path, '*' ) );
			$path          = esc_url( $path, null, 'db' );
			if ( ! empty( $path ) ) {
				if ( ! substr_count( $path, '.' ) ) {
					add_settings_error(
						'ewww_image_optimizer_webp_paths',
						"ewwwio-webp-paths-$i",
						sprintf(
							/* translators: %s: A url or domain name */
							esc_html__( 'Could not save WebP URL: %s.', 'ewww-image-optimizer' ),
							esc_html( $original_path )
						) . ' ' . esc_html__( 'Please enter a valid URL including the domain name.', 'ewww-image-optimizer' )
					);
					continue;
				}
				$paths_saved[] = trailingslashit( str_replace( 'http://', '', $path ) );
			}
		}
	}
	return $paths_saved;
}

/**
 * Retrieves/sanitizes jpg background fill setting or returns null for png2jpg conversions.
 *
 * @param string $background The hexadecimal value entered by the user.
 * @return string The background color sanitized.
 */
function ewww_image_optimizer_jpg_background( $background = null ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( is_null( $background ) ) {
		// Retrieve the user-supplied value for jpg background color.
		$background = ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_background' );
	}
	// Verify that the supplied value is in hex notation.
	if ( preg_match( '/^\#*([0-9a-fA-F]){6}$/', $background ) ) {
		// We remove a leading # symbol, since we take care of it later.
		$background = ltrim( $background, '#' );
		// Send back the verified, cleaned-up background color.
		ewwwio_debug_message( "background: $background" );
		ewwwio_memory( __FUNCTION__ );
		return $background;
	} else {
		if ( ! empty( $background ) ) {
			add_settings_error( 'ewww_image_optimizer_jpg_background', 'ewwwio-jpg-background', esc_html__( 'Could not save the JPG background color, please enter a six-character, hexadecimal value.', 'ewww-image-optimizer' ) );
		}
		// Send back a blank value.
		ewwwio_memory( __FUNCTION__ );
		return null;
	}
}

/**
 * Retrieves/sanitizes the jpg quality setting for png2jpg conversion or returns null.
 *
 * @param int $quality The JPG quality level as set by the user.
 * @return int The sanitize JPG quality level.
 */
function ewww_image_optimizer_jpg_quality( $quality = null ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( is_null( $quality ) ) {
		// Retrieve the user-supplied value for jpg quality.
		$quality = ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_quality' );
	}
	// Verify that the quality level is an integer, 1-100.
	if ( preg_match( '/^(100|[1-9][0-9]?)$/', $quality ) ) {
		ewwwio_debug_message( "quality: $quality" );
		// Send back the valid quality level.
		ewwwio_memory( __FUNCTION__ );
		return $quality;
	} else {
		if ( ! empty( $quality ) ) {
			add_settings_error( 'ewww_image_optimizer_jpg_quality', 'ewwwio-jpg-quality', esc_html__( 'Could not save the JPG quality, please enter an integer between 1 and 100.', 'ewww-image-optimizer' ) );
		}
		// Send back nothing.
		ewwwio_memory( __FUNCTION__ );
		return null;
	}
}

/**
 * Overrides the default JPG quality for WordPress image editing operations.
 *
 * @param int $quality The default JPG quality level.
 * @return int The default quality, or the user configured level.
 */
function ewww_image_optimizer_set_jpg_quality( $quality ) {
	$new_quality = ewww_image_optimizer_jpg_quality();
	if ( ! empty( $new_quality ) ) {
		return min( 92, $new_quality );
	}
	return min( 92, $quality );
}

/**
 * Retrieves/sanitizes the WebP quality setting or returns null.
 *
 * @param int $quality The WebP quality level as set by the user.
 * @return int The sanitize WebP quality level.
 */
function ewww_image_optimizer_webp_quality( $quality = null ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( is_null( $quality ) ) {
		// Retrieve the user-supplied value for jpg quality.
		$quality = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_quality' );
	}
	// Verify that the quality level is an integer, 1-100.
	if ( preg_match( '/^(100|[1-9][0-9]?)$/', $quality ) ) {
		ewwwio_debug_message( "webp quality: $quality" );
		// Send back the valid quality level.
		return $quality;
	} else {
		if ( ! empty( $quality ) ) {
			add_settings_error( 'ewww_image_optimizer_webp_quality', 'ewwwio-webp-quality', esc_html__( 'Could not save the WebP quality, please enter an integer between 50 and 100.', 'ewww-image-optimizer' ) );
		}
		// Send back nothing.
		return null;
	}
}

/**
 * Overrides the default WebP quality (if a user-defined value is set).
 *
 * @param int $quality The default WebP quality level.
 * @return int The default quality, or the user configured level.
 */
function ewww_image_optimizer_set_webp_quality( $quality ) {
	$new_quality = ewww_image_optimizer_webp_quality();
	if ( ! empty( $new_quality ) ) {
		return min( 92, $new_quality );
	}
	return min( 92, $quality );
}

/**
 * Check default WP threshold and adjust to comply with normal EWWW IO behavior.
 *
 * @param int    $size The default WP scaling size, or whatever has been filtered by other plugins.
 * @param array  $imagesize     {
 *     Indexed array of the image width and height in pixels.
 *
 *     @type int $0 The image width.
 *     @type int $1 The image height.
 * }
 * @param string $file Full path to the uploaded image file.
 * @return int The proper size to use for scaling originals.
 */
function ewww_image_optimizer_adjust_big_image_threshold( $size, $imagesize = array(), $file = '' ) {
	if ( false !== strpos( $file, 'noresize' ) ) {
		return false;
	}
	$max_size = max(
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' ),
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' ),
		(int) $size
	);
	return $max_size;
}

/**
 * Setup the global filesystem class variable.
 */
function ewwwio_get_filesystem() {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
	require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );
	global $eio_filesystem;
	if ( ! defined( 'FS_CHMOD_DIR' ) ) {
		define( 'FS_CHMOD_DIR', ( fileperms( ABSPATH ) & 0777 | 0755 ) );
	}
	if ( ! defined( 'FS_CHMOD_FILE' ) ) {
		define( 'FS_CHMOD_FILE', ( fileperms( ABSPATH . 'index.php' ) & 0777 | 0644 ) );
	}
	if ( ! isset( $eio_filesystem ) || ! is_object( $eio_filesystem ) ) {
		$eio_filesystem = new WP_Filesystem_Direct( '' );
	}
}

/**
 * Check filesize, and prevent errors by ensuring file exists, and that the cache has been cleared.
 *
 * @param string $file The name of the file.
 * @return int The size of the file or zero.
 */
function ewww_image_optimizer_filesize( $file ) {
	$file = realpath( $file );
	if ( ewwwio_is_file( $file ) ) {
		global $eio_filesystem;
		ewwwio_get_filesystem();
		// Flush the cache for filesize.
		clearstatcache();
		// Find out the size of the new PNG file.
		return $eio_filesystem->size( $file );
	} else {
		return 0;
	}
}

/**
 * Check if open_basedir restriction is in effect, and that the path is allowed and exists.
 *
 * Note that when the EWWWIO_OPEN_BASEDIR constant is defined, is_file() will be skipped.
 *
 * @param string $file The path of the file to check.
 * @return bool False if open_basedir setting cannot be retrieved, or the file is "out of bounds", true if the file exists.
 */
function ewwwio_system_binary_exists( $file ) {
	if ( ! ewww_image_optimizer_function_exists( 'ini_get' ) && ! defined( 'EWWWIO_OPEN_BASEDIR' ) ) {
		return false;
	}
	if ( defined( 'EWWWIO_OPEN_BASEDIR' ) ) {
		$basedirs = EWWWIO_OPEN_BASEDIR;
	} else {
		$basedirs = ini_get( 'open_basedir' );
	}
	if ( empty( $basedirs ) ) {
		return defined( 'EWWWIO_OPEN_BASEDIR' ) ? true : is_file( $file );
	}
	$basedirs = explode( PATH_SEPARATOR, $basedirs );
	foreach ( $basedirs as $basedir ) {
		$basedir = trim( $basedir );
		if ( 0 === strpos( $file, $basedir ) ) {
			return defined( 'EWWWIO_OPEN_BASEDIR' ) ? true : is_file( $file );
		}
	}
	return false;
}

/**
 * Check if a file/directory is readable.
 *
 * @param string $file The path to check.
 * @return bool True if it is, false if it ain't.
 */
function ewwwio_is_readable( $file ) {
	global $eio_filesystem;
	ewwwio_get_filesystem();
	return $eio_filesystem->is_readable( $file );
}
/**
 * Check if directory exists, and that it is local rather than using a protocol like http:// or phar://
 *
 * @param string $dir The path of the directoy to check.
 * @return bool True if the directory exists and is local, false otherwise.
 */
function ewwwio_is_dir( $dir ) {
	if ( false !== strpos( $dir, '://' ) ) {
		return false;
	}
	if ( false !== strpos( $dir, 'phar://' ) ) {
		return false;
	}
	global $eio_filesystem;
	ewwwio_get_filesystem();
	$dir        = realpath( $dir );
	$wp_dir     = realpath( ABSPATH );
	$upload_dir = wp_get_upload_dir();
	$upload_dir = realpath( $upload_dir['basedir'] );

	$content_dir = realpath( WP_CONTENT_DIR );
	if ( empty( $content_dir ) ) {
		$content_dir = $wp_dir;
	}
	if ( empty( $upload_dir ) ) {
		$upload_dir = $content_dir;
	}
	$plugin_dir = realpath( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH );
	if (
		false === strpos( $dir, $upload_dir ) &&
		false === strpos( $dir, $content_dir ) &&
		false === strpos( $dir, $wp_dir ) &&
		false === strpos( $dir, $plugin_dir )
	) {
		return false;
	}
	return $eio_filesystem->is_dir( $dir );
}

/**
 * Check if file exists, and that it is local rather than using a protocol like http:// or phar://
 *
 * @param string $file The path of the file to check.
 * @return bool True if the file exists and is local, false otherwise.
 */
function ewwwio_is_file( $file ) {
	if ( false !== strpos( $file, '://' ) ) {
		return false;
	}
	if ( false !== strpos( $file, 'phar://' ) ) {
		return false;
	}
	global $eio_filesystem;
	ewwwio_get_filesystem();
	$file       = realpath( $file );
	$wp_dir     = realpath( ABSPATH );
	$upload_dir = wp_get_upload_dir();
	$upload_dir = realpath( $upload_dir['basedir'] );

	$content_dir = realpath( WP_CONTENT_DIR );
	if ( empty( $content_dir ) ) {
		$content_dir = $wp_dir;
	}
	if ( empty( $upload_dir ) ) {
		$upload_dir = $content_dir;
	}
	$plugin_dir = realpath( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH );
	if ( defined( 'EWWW_IMAGE_OPTIMIZER_TOOL_PATH' ) ) {
		$tool_dir = realpath( EWWW_IMAGE_OPTIMIZER_TOOL_PATH );
		$tool_dir = dirname( $tool_dir );
	}
	if ( empty( $tool_dir ) ) {
		$tool_dir = $content_dir;
	}
	if (
		false === strpos( $file, $upload_dir ) &&
		false === strpos( $file, $content_dir ) &&
		false === strpos( $file, $wp_dir ) &&
		false === strpos( $file, $plugin_dir ) &&
		false === strpos( $file, $tool_dir )
	) {
		return false;
	}
	return $eio_filesystem->is_file( $file );
}

/**
 * Check if destination is in an approved location and rename the original.
 *
 * @param string $src The path of the original file.
 * @param string $dst The destination file path.
 * @return bool True if the file was removed, false otherwise.
 */
function ewwwio_rename( $src, $dst ) {
	global $eio_filesystem;
	ewwwio_get_filesystem();
	$src = realpath( $src );
	if ( false !== strpos( $dst, WP_CONTENT_DIR ) ) {
		return $eio_filesystem->move( $src, $dst, true );
	}
	if ( false !== strpos( $dst, ABSPATH ) ) {
		return $eio_filesystem->move( $src, $dst, true );
	}
	$upload_dir = wp_get_upload_dir();
	if ( false !== strpos( $dst, $upload_dir['basedir'] ) ) {
		return $eio_filesystem->move( $src, $dst, true );
	}
	return false;
}

/**
 * Check if file is in an approved location and remove it.
 *
 * @param string $file The path of the file to check.
 * @param string $dir The path of the folder constraint. Optional.
 * @return bool True if the file was removed, false otherwise.
 */
function ewwwio_delete_file( $file, $dir = '' ) {
	$file = realpath( $file );
	if ( ! empty( $dir ) ) {
		return wp_delete_file_from_directory( $file, $dir );
	}

	$wp_dir      = realpath( ABSPATH );
	$upload_dir  = wp_get_upload_dir();
	$upload_dir  = realpath( $upload_dir['basedir'] );
	$content_dir = realpath( WP_CONTENT_DIR );

	if ( false !== strpos( $file, $upload_dir ) ) {
		return wp_delete_file_from_directory( $file, $upload_dir );
	}
	if ( false !== strpos( $file, $content_dir ) ) {
		return wp_delete_file_from_directory( $file, $content_dir );
	}
	if ( false !== strpos( $file, $wp_dir ) ) {
		return wp_delete_file_from_directory( $file, $wp_dir );
	}
	return false;
}

/**
 * Check if file is in an approved location and chmod it.
 *
 * @param string $file The path of the file to check.
 * @param string $mode The mode to apply to the file.
 */
function ewwwio_chmod( $file, $mode ) {
	global $eio_filesystem;
	ewwwio_get_filesystem();
	clearstatcache();
	$file       = realpath( $file );
	$upload_dir = wp_get_upload_dir();
	if ( false !== strpos( $file, $upload_dir['basedir'] ) && is_writable( $file ) ) {
		return $eio_filesystem->chmod( $file, $mode );
	}
	if ( false !== strpos( $file, WP_CONTENT_DIR ) && is_writable( $file ) ) {
		return $eio_filesystem->chmod( $file, $mode );
	}
	if ( false !== strpos( $file, ABSPATH ) && is_writable( $file ) ) {
		return $eio_filesystem->chmod( $file, $mode );
	}
	return false;
}
/**
 * Make sure an array/object can be parsed by a foreach().
 *
 * @param mixed $var A variable to test for iteration ability.
 * @return bool True if the variable is iterable.
 */
function ewww_image_optimizer_iterable( $var ) {
	return ! empty( $var ) && ( is_array( $var ) || $var instanceof Traversable );
}

/**
 * Manually process an image from the Media Library
 *
 * @global bool $ewww_defer True if the image optimization should be deferred.
 */
function ewww_image_optimizer_manual() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewww_force;
	global $ewww_convert;
	global $ewww_defer;
	$ewww_defer = false;
	add_filter( 'ewww_image_optimizer_allowed_reopt', '__return_true' );
	// Check permissions of current user.
	$permissions = apply_filters( 'ewww_image_optimizer_manual_permissions', '' );
	if ( ! current_user_can( $permissions ) ) {
		// Display error message if insufficient permissions.
		if ( ! wp_doing_ajax() ) {
			wp_die( esc_html__( 'You do not have permission to optimize images.', 'ewww-image-optimizer' ) );
		}
		ewwwio_ob_clean();
		wp_die( wp_json_encode( array( 'error' => esc_html__( 'You do not have permission to optimize images.', 'ewww-image-optimizer' ) ) ) );
	}
	// Make sure we didn't accidentally get to this page without an attachment to work on.
	if ( empty( $_REQUEST['ewww_attachment_ID'] ) || empty( $_REQUEST['action'] ) ) {
		// Display an error message since we don't have anything to work on.
		if ( ! wp_doing_ajax() ) {
			wp_die( esc_html__( 'Invalid request.', 'ewww-image-optimizer' ) );
		}
		ewwwio_ob_clean();
		wp_die( wp_json_encode( array( 'error' => esc_html__( 'Invalid request.', 'ewww-image-optimizer' ) ) ) );
	}
	session_write_close();
	if ( empty( $_REQUEST['ewww_manual_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_manual_nonce'] ), 'ewww-manual' ) ) {
		if ( ! wp_doing_ajax() ) {
			wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		ewwwio_ob_clean();
		wp_die( wp_json_encode( array( 'error' => esc_html__( 'Access denied.', 'ewww-image-optimizer' ) ) ) );
	}
	// Store the attachment ID value.
	$attachment_id = (int) $_REQUEST['ewww_attachment_ID'];
	$ewww_force    = ! empty( $_REQUEST['ewww_force'] ) ? true : false;
	$ewww_convert  = ! empty( $_REQUEST['ewww_convert'] ) ? true : false;
	// Retrieve the existing attachment metadata.
	$original_meta = wp_get_attachment_metadata( $attachment_id );
	// If the call was to optimize...
	if ( 'ewww_image_optimizer_manual_optimize' === $_REQUEST['action'] || 'ewww_manual_optimize' === $_REQUEST['action'] ) {
		// Call the optimize from metadata function and store the resulting new metadata.
		$new_meta = ewww_image_optimizer_resize_from_meta_data( $original_meta, $attachment_id );
	} elseif ( 'ewww_image_optimizer_manual_restore' === $_REQUEST['action'] || 'ewww_manual_restore' === $_REQUEST['action'] ) {
		$new_meta = ewww_image_optimizer_restore_from_meta_data( $original_meta, $attachment_id );
	} elseif ( 'ewww_image_optimizer_manual_cloud_restore' === $_REQUEST['action'] || 'ewww_manual_cloud_restore' === $_REQUEST['action'] ) {
		$new_meta = ewww_image_optimizer_cloud_restore_from_meta_data( $attachment_id, 'media', $original_meta );
	} else {
		if ( ! wp_doing_ajax() ) {
			wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		ewwwio_ob_clean();
		wp_die( wp_json_encode( array( 'error' => esc_html__( 'Access denied.', 'ewww-image-optimizer' ) ) ) );
	}
	$basename = '';
	if ( is_array( $new_meta ) && ! empty( $new_meta['file'] ) ) {
		$basename = basename( $new_meta['file'] );
	}
	// Update the attachment metadata in the database.
	$meta_saved = wp_update_attachment_metadata( $attachment_id, $new_meta );
	if ( ! $meta_saved ) {
		ewwwio_debug_message( 'failed to save meta, or no changes' );
	}
	if ( 'exceeded' === get_transient( 'ewww_image_optimizer_cloud_status' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_exceeded' ) > time() ) {
		if ( ! wp_doing_ajax() ) {
			wp_die( '<a href="https://ewww.io/buy-credits/" target="_blank">' . esc_html__( 'License exceeded', 'ewww-image-optimizer' ) . '</a>' );
		}
		ewwwio_ob_clean();
		wp_die(
			wp_json_encode(
				array(
					'error' => '<a href="https://ewww.io/buy-credits/" target="_blank">' . esc_html__( 'License exceeded', 'ewww-image-optimizer' ) . '</a>',
				)
			)
		);
	} elseif ( 'exceeded quota' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
		if ( ! wp_doing_ajax() ) {
			wp_die( '<a href="https://docs.ewww.io/article/101-soft-quotas-on-unlimited-plans" data-beacon-article="608ddf128996210f18bd95d3" target="_blank">' . esc_html__( 'Soft quota reached, contact us for more', 'ewww-image-optimizer' ) . '</a>' );
		}
		ewwwio_ob_clean();
		wp_die(
			wp_json_encode(
				array(
					'error' => '<a href="https://docs.ewww.io/article/101-soft-quotas-on-unlimited-plans" data-beacon-article="608ddf128996210f18bd95d3" target="_blank">' . esc_html__( 'Soft quota reached, contact us for more', 'ewww-image-optimizer' ) . '</a>',
				)
			)
		);
	}
	$success = ewww_image_optimizer_custom_column_capture( 'ewww-image-optimizer', $attachment_id, $new_meta );
	ewww_image_optimizer_debug_log();
	// Do a redirect, if this was called via GET.
	if ( ! wp_doing_ajax() ) {
		// Store the referring webpage location.
		$sendback = wp_get_referer();
		// Send the user back where they came from.
		wp_safe_redirect( $sendback );
		die;
	}
	ewwwio_memory( __FUNCTION__ );
	ewwwio_ob_clean();
	wp_die(
		wp_json_encode(
			array(
				'success'  => $success,
				'basename' => $basename,
			)
		)
	);
}

/**
 * Manually restore a converted image.
 *
 * @global object $wpdb
 * @global object $ewwwdb A clone of $wpdb unless it is lacking utf8 connectivity.
 *
 * @param array $meta The attachment metadata.
 * @param int   $id The attachment id number.
 * @return array The attachment metadata.
 */
function ewww_image_optimizer_restore_from_meta_data( $meta, $id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	$db_image = $ewwwdb->get_results( "SELECT id,path,converted FROM $ewwwdb->ewwwio_images WHERE attachment_id = $id AND gallery = 'media' AND resize = 'full'", ARRAY_A );
	if ( empty( $db_image ) || ! is_array( $db_image ) || empty( $db_image['path'] ) ) {
		// Get the filepath based on the meta and id.
		list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );

		$db_image = ewww_image_optimizer_find_already_optimized( $file_path );
		if ( empty( $db_image ) || ! is_array( $db_image ) || empty( $db_image['path'] ) ) {
			return $meta;
		}
	}
	$ewww_image = new EWWW_Image( $id, 'media', ewww_image_optimizer_absolutize_path( $db_image['path'] ) );
	return $ewww_image->restore_with_meta( $meta );
}

/**
 * Manually restore an attachment from the API
 *
 * @global object $wpdb
 * @global object $ewwwdb A clone of $wpdb unless it is lacking utf8 connectivity.
 *
 * @param int    $id The attachment id number.
 * @param string $gallery Optional. The gallery from whence we came. Default 'media'.
 * @param array  $meta Optional. The image metadata from the postmeta table.
 * @return array The altered meta (if size differs), or the original value passed along.
 */
function ewww_image_optimizer_cloud_restore_from_meta_data( $id, $gallery = 'media', $meta = array() ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	$images = $ewwwdb->get_results( "SELECT id,path,resize,backup FROM $ewwwdb->ewwwio_images WHERE attachment_id = $id AND gallery = '$gallery'", ARRAY_A );
	foreach ( $images as $image ) {
		if ( ! empty( $image['path'] ) ) {
			$image['path'] = ewww_image_optimizer_absolutize_path( $image['path'] );
		}
		ewww_image_optimizer_cloud_restore_single_image( $image );
		if ( 'media' === $gallery && 'full' === $image['resize'] && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
			list( $width, $height ) = wp_getimagesize( $image['path'] );
			if ( (int) $width !== (int) $meta['width'] || (int) $height !== (int) $meta['height'] ) {
				$meta['height'] = $height;
				$meta['width']  = $width;
			}
		}
	}
	if ( class_exists( 'S3_Uploads' ) || class_exists( 'S3_uploads\Plugin' ) ) {
		ewww_image_optimizer_remote_push( $meta, $id );
		ewwwio_debug_message( 're-uploading to S3(_Uploads)' );
	}
	return $meta;
}

/**
 * Handle the AJAX call for a single image restore.
 */
function ewww_image_optimizer_cloud_restore_single_image_handler() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Check permissions of current user.
	$permissions = apply_filters( 'ewww_image_optimizer_manual_permissions', '' );
	if ( false === current_user_can( $permissions ) ) {
		// Display error message if insufficient permissions.
		ewwwio_ob_clean();
		wp_die( wp_json_encode( array( 'error' => esc_html__( 'You do not have permission to optimize images.', 'ewww-image-optimizer' ) ) ) );
	}
	// Make sure we didn't accidentally get to this page without an attachment to work on.
	if ( empty( $_REQUEST['ewww_image_id'] ) ) {
		// Display an error message since we don't have anything to work on.
		ewwwio_ob_clean();
		wp_die( wp_json_encode( array( 'error' => esc_html__( 'No image ID was provided.', 'ewww-image-optimizer' ) ) ) );
	}
	if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-tools' ) ) {
		ewwwio_ob_clean();
		wp_die( wp_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) ) ) );
	}
	session_write_close();
	$image = (int) $_REQUEST['ewww_image_id'];
	if ( ewww_image_optimizer_cloud_restore_single_image( $image ) ) {
		ewwwio_ob_clean();
		wp_die( wp_json_encode( array( 'success' => 1 ) ) );
	}
	ewwwio_ob_clean();
	wp_die( wp_json_encode( array( 'error' => esc_html__( 'Unable to restore image.', 'ewww-image-optimizer' ) ) ) );
}

/**
 * Restores a single image from the API.
 *
 * @global object $wpdb
 * @global object $ewwwdb A clone of $wpdb unless it is lacking utf8 connectivity.
 *
 * @param string $image The filename of the image to restore.
 * @return bool True if the image was restored successfully.
 */
function ewww_image_optimizer_cloud_restore_single_image( $image ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	if ( ! is_array( $image ) && ! empty( $image ) && is_numeric( $image ) ) {
		$image = $ewwwdb->get_row( "SELECT id,path,backup FROM $ewwwdb->ewwwio_images WHERE id = $image", ARRAY_A );
	}
	if ( ! empty( $image['path'] ) ) {
		$image['path'] = ewww_image_optimizer_absolutize_path( $image['path'] );
	}
	$api_key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	$domain  = parse_url( get_site_url(), PHP_URL_HOST );
	$url     = 'http://optimize.exactlywww.com/backup/restore.php';
	$ssl     = wp_http_supports( array( 'ssl' ) );
	if ( $ssl ) {
		$url = set_url_scheme( $url, 'https' );
	}
	$result = wp_remote_post(
		$url,
		array(
			'timeout'   => 30,
			'sslverify' => false,
			'body'      => array(
				'api_key' => $api_key,
				'domain'  => $domain,
				'hash'    => $image['backup'],
			),
		)
	);
	if ( is_wp_error( $result ) ) {
		$error_message = $result->get_error_message();
		ewwwio_debug_message( "restore request failed: $error_message" );
		ewwwio_memory( __FUNCTION__ );
		return false;
	} elseif ( ! empty( $result['body'] ) && strpos( $result['body'], 'missing' ) === false ) {
		$enabled_types = array( 'image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'image/svg+xml' );
		if ( ! is_dir( dirname( $image['path'] ) ) ) {
			wp_mkdir_p( dirname( $image['path'] ) );
		}
		file_put_contents( $image['path'] . '.tmp', $result['body'] );
		$new_type = ewww_image_optimizer_mimetype( $image['path'] . '.tmp', 'i' );
		$old_type = '';
		if ( ewwwio_is_file( $image['path'] ) ) {
			$old_type = ewww_image_optimizer_mimetype( $image['path'], 'i' );
		}
		if ( ! in_array( $new_type, $enabled_types, true ) ) {
			return false;
		}
		if ( empty( $old_type ) || $old_type === $new_type ) {
			if ( ewwwio_rename( $image['path'] . '.tmp', $image['path'] ) ) {
				if ( ewwwio_is_file( $image['path'] . '.webp' ) && is_writable( $image['path'] . '.webp' ) ) {
					unlink( $image['path'] . '.webp' );
				}
				// Set the results to nothing.
				$ewwwdb->query( "UPDATE $ewwwdb->ewwwio_images SET results = '', image_size = 0, updates = 0, updated=updated, level = 0 WHERE id = {$image['id']}" );
				return true;
			}
		}
	}
	return false;
}

/**
 * Cleans up when an attachment is being deleted.
 *
 * Removes any .webp images, backups from conversion, and removes related database records.
 *
 * @global object $wpdb
 * @global object $ewwwdb A clone of $wpdb unless it is lacking utf8 connectivity.
 *
 * @param int $id The id number for the attachment being deleted.
 */
function ewww_image_optimizer_delete( $id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	$id = (int) $id;
	// Finds non-meta images to remove from disk, and from db, as well as converted originals.
	$optimized_images = $ewwwdb->get_results( "SELECT path,converted FROM $ewwwdb->ewwwio_images WHERE attachment_id = $id AND gallery = 'media'", ARRAY_A );
	if ( $optimized_images ) {
		if ( ewww_image_optimizer_iterable( $optimized_images ) ) {
			foreach ( $optimized_images as $image ) {
				if ( ! empty( $image['path'] ) ) {
					$image['path'] = ewww_image_optimizer_absolutize_path( $image['path'] );
				}
				if ( ! empty( $image['path'] ) ) {
					if ( ewwwio_is_file( $image['path'] ) ) {
						ewwwio_debug_message( 'removing: ' . $image['path'] );
						ewwwio_delete_file( $image['path'] );
					}
					if ( ewwwio_is_file( $image['path'] . '.webp' ) ) {
						ewwwio_debug_message( 'removing: ' . $image['path'] . '.webp' );
						ewwwio_delete_file( $image['path'] . '.webp' );
					}
					$webpfileold = preg_replace( '/\.\w+$/', '.webp', $image['path'] );
					if ( ewwwio_is_file( $webpfileold ) ) {
						ewwwio_debug_message( 'removing: ' . $webpfileold );
						ewwwio_delete_file( $webpfileold );
					}
				}
				if ( ! empty( $image['converted'] ) ) {
					$image['converted'] = ewww_image_optimizer_absolutize_path( $image['converted'] );
				}
				if ( ! empty( $image['converted'] ) && ewwwio_is_file( $image['converted'] ) ) {
					ewwwio_debug_message( 'removing: ' . $image['converted'] );
					ewwwio_delete_file( $image['converted'] );
					if ( ewwwio_is_file( $image['converted'] . '.webp' ) ) {
						ewwwio_debug_message( 'removing: ' . $image['converted'] . '.webp' );
						ewwwio_delete_file( $image['converted'] . '.webp' );
					}
				}
			}
		}
		$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'attachment_id' => $id ) );
	}
	$s3_path = false;
	$s3_dir  = false;
	if ( ( class_exists( 'S3_Uploads' ) || class_exists( 'S3_Uploads\Plugin' ) ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) {
		$s3_path = get_attached_file( $id );
		if ( 0 === strpos( $s3_path, 's3://' ) ) {
			ewwwio_debug_message( 'removing: ' . $s3_path . '.webp' );
			unlink( $s3_path . '.webp' );
		}
		$s3_dir = trailingslashit( dirname( $s3_path ) );
	}
	// Retrieve the image metadata.
	$meta = wp_get_attachment_metadata( $id );
	// If the attachment has an original file set.
	if ( ! empty( $meta['orig_file'] ) ) {
		// Get the filepath from the metadata.
		$file_path = $meta['orig_file'];
		// Get the base filename.
		$filename = wp_basename( $file_path );
		// Delete any residual webp versions.
		$webpfile    = $file_path . '.webp';
		$webpfileold = preg_replace( '/\.\w+$/', '.webp', $file_path );
		if ( ewwwio_is_file( $webpfile ) ) {
			ewwwio_debug_message( 'removing: ' . $webpfile );
			ewwwio_delete_file( $webpfile );
		}
		if ( ewwwio_is_file( $webpfileold ) ) {
			ewwwio_debug_message( 'removing: ' . $webpfileold );
			ewwwio_delete_file( $webpfileold );
		}
		// Retrieve any posts that link the original image.
		$esql = "SELECT ID, post_content FROM $ewwwdb->posts WHERE post_content LIKE '%$filename%' LIMIT 1";
		$rows = $ewwwdb->get_row( $esql );
		// If the original file still exists and no posts contain links to the image.
		if ( ewwwio_is_file( $file_path ) && empty( $rows ) ) {
			ewwwio_debug_message( 'removing: ' . $file_path );
			ewwwio_delete_file( $file_path );
			$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $file_path ) ) );
		}
	}
	$file_path = get_attached_file( $id );
	// If the attachment has an original file set.
	if ( ! empty( $meta['original_image'] ) ) {
		// One way or another, $file_path is now set, and we can get the base folder name.
		$base_dir = dirname( $file_path ) . '/';
		// Get the original filename from the metadata.
		$orig_path = $base_dir . wp_basename( $meta['original_image'] );
		// Delete any residual webp versions.
		$webpfile = $orig_path . '.webp';
		if ( ewwwio_is_file( $webpfile ) ) {
			ewwwio_debug_message( 'removing: ' . $webpfile );
			ewwwio_delete_file( $webpfile );
		}
		if ( $s3_path && $s3_dir && wp_basename( $meta['original_image'] ) ) {
			ewwwio_debug_message( 'removing: ' . $s3_dir . wp_basename( $meta['original_image'] ) . '.webp' );
			unlink( $s3_dir . wp_basename( $meta['original_image'] ) . '.webp' );
		}
		$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $orig_path ) ) );
	}
	// Remove the regular image from the ewwwio_images tables.
	$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $file_path ) ) );
	// Resized versions, so we can continue.
	if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
		// One way or another, $file_path is now set, and we can get the base folder name.
		$base_dir = dirname( $file_path ) . '/';
		foreach ( $meta['sizes'] as $size => $data ) {
			// Delete any residual webp versions.
			$webpfile    = $base_dir . wp_basename( $data['file'] ) . '.webp';
			$webpfileold = preg_replace( '/\.\w+$/', '.webp', $base_dir . wp_basename( $data['file'] ) );
			if ( ewwwio_is_file( $webpfile ) ) {
				ewwwio_debug_message( 'removing: ' . $webpfile );
				ewwwio_delete_file( $webpfile );
			}
			if ( ewwwio_is_file( $webpfileold ) ) {
				ewwwio_debug_message( 'removing: ' . $webpfileold );
				ewwwio_delete_file( $webpfileold );
			}
			if ( $s3_path && $s3_dir && wp_basename( $data['file'] ) ) {
				ewwwio_debug_message( 'removing: ' . $s3_dir . wp_basename( $data['file'] ) . '.webp' );
				unlink( $s3_dir . wp_basename( $data['file'] ) . '.webp' );
			}
			$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $base_dir . $data['file'] ) ) );
			// If the original resize is set, and still exists.
			if ( ! empty( $data['orig_file'] ) && ewwwio_is_file( $base_dir . $data['orig_file'] ) ) {
				unset( $srows );
				// Retrieve the filename from the metadata.
				$filename = $data['orig_file'];
				// Retrieve any posts that link the image.
				$esql  = "SELECT ID, post_content FROM $ewwwdb->posts WHERE post_content LIKE '%$filename%' LIMIT 1";
				$srows = $ewwwdb->get_row( $esql );
				// If there are no posts containing links to the original, delete it.
				if ( empty( $srows ) ) {
					ewwwio_debug_message( 'removing: ' . $base_dir . $data['orig_file'] );
					ewwwio_delete_file( $base_dir . $data['orig_file'] );
					$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $base_dir . $data['orig_file'] ) ) );
				}
			}
		}
	}
	if ( ewwwio_is_file( $file_path . '.webp' ) ) {
		ewwwio_debug_message( 'removing: ' . $file_path . '.webp' );
		ewwwio_delete_file( $file_path . '.webp' );
	}
	$webpfileold = preg_replace( '/\.\w+$/', '.webp', $file_path );
	if ( ewwwio_is_file( $webpfileold ) ) {
		ewwwio_debug_message( 'removing: ' . $webpfileold );
		ewwwio_delete_file( $webpfileold );
	}
}

/**
 * Cleans up when a file has been deleted.
 *
 * Removes any .webp images, backups from conversion, and removes related database records.
 *
 * @global object $wpdb
 * @global object $ewwwdb A clone of $wpdb unless it is lacking utf8 connectivity.
 *
 * @param int    $id The id number for the attachment being deleted.
 * @param string $file The file being deleted.
 */
function ewww_image_optimizer_file_deleted( $id, $file ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	$id = (int) $id;
	// Finds non-meta images to remove from disk, and from db, as well as converted originals.
	$maybe_relative_path = ewww_image_optimizer_relativize_path( $file );
	$query               = $ewwwdb->prepare( "SELECT * FROM $ewwwdb->ewwwio_images WHERE path = %s", $maybe_relative_path );
	$optimized_images    = $ewwwdb->get_results( $query, ARRAY_A );
	if ( ewww_image_optimizer_iterable( $optimized_images ) ) {
		foreach ( $optimized_images as $image ) {
			if ( ! empty( $image['path'] ) ) {
				$image['path'] = ewww_image_optimizer_absolutize_path( $image['path'] );
			}
			if ( strpos( $image['path'], WP_CONTENT_DIR ) === false ) {
				continue;
			}
			if ( ! empty( $image['converted'] ) ) {
				$image['converted'] = ewww_image_optimizer_absolutize_path( $image['converted'] );
			}
			if ( ! empty( $image['converted'] ) && ewwwio_is_file( $image['converted'] ) ) {
				ewwwio_delete_file( $image['converted'] );
				if ( ewwwio_is_file( $image['converted'] . '.webp' ) ) {
					ewwwio_delete_file( $image['converted'] . '.webp' );
				}
			}
			$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'id' => $image['id'] ) );
		}
	}
	if ( ewwwio_is_file( $file . '.webp' ) ) {
		ewwwio_delete_file( $file . '.webp' );
	}
}

/**
 * Cleans records from database when an image is about to be replaced.
 *
 * @param array $attachment An array with the attachment/image ID.
 */
function ewww_image_optimizer_media_replace( $attachment ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	$id = (int) $attachment['post_id'];
	// Finds non-meta images to remove from disk, and from db, as well as converted originals.
	$optimized_images = $ewwwdb->get_results( "SELECT path,converted FROM $ewwwdb->ewwwio_images WHERE attachment_id = $id AND gallery = 'media'", ARRAY_A );
	if ( $optimized_images ) {
		if ( ewww_image_optimizer_iterable( $optimized_images ) ) {
			foreach ( $optimized_images as $image ) {
				if ( ! empty( $image['path'] ) ) {
					$image['path'] = ewww_image_optimizer_absolutize_path( $image['path'] );
				}
				if ( false === strpos( $image['path'], WP_CONTENT_DIR ) ) {
					continue;
				}
				if ( ! empty( $image['path'] ) ) {
					if ( ewwwio_is_file( $image['path'] . '.webp' ) ) {
						ewwwio_delete_file( $image['path'] . '.webp' );
					}
				}
				if ( ! empty( $image['converted'] ) ) {
					$image['converted'] = ewww_image_optimizer_absolutize_path( $image['converted'] );
				}
				if ( ! empty( $image['converted'] ) && ewwwio_is_file( $image['converted'] ) ) {
					ewwwio_delete_file( $image['converted'] );
					if ( ewwwio_is_file( $image['converted'] . '.webp' ) ) {
						ewwwio_delete_file( $image['converted'] . '.webp' );
					}
				}
			}
		}
		$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'attachment_id' => $id ) );
	}
	// Retrieve the image metadata.
	$meta = wp_get_attachment_metadata( $id );
	// If the attachment has an original file set.
	if ( ! empty( $meta['orig_file'] ) ) {
		// Get the filepath from the metadata.
		$file_path = $meta['orig_file'];

		$webpfile    = $file_path . '.webp';
		$webpfileold = preg_replace( '/\.\w+$/', '.webp', $file_path );
		if ( ewwwio_is_file( $webpfile ) ) {
			ewwwio_delete_file( $webpfile );
		}
		if ( ewwwio_is_file( $webpfileold ) ) {
			ewwwio_delete_file( $webpfileold );
		}
		$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $file_path ) ) );
	}
	list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
	// If the attachment has an original file set.
	if ( ! empty( $meta['original_image'] ) ) {
		// One way or another, $file_path is now set, and we can get the base folder name.
		$base_dir = dirname( $file_path ) . '/';
		// Get the original filename from the metadata.
		$orig_path = $base_dir . wp_basename( $meta['original_image'] );
		// Delete any residual webp versions.
		$webpfile = $orig_path . '.webp';
		if ( ewwwio_is_file( $webpfile ) ) {
			ewwwio_delete_file( $webpfile );
		}
		$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $orig_path ) ) );
	}
	// Remove the regular image from the ewwwio_images tables.
	$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $file_path ) ) );
	// Resized versions, so we can continue.
	if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
		// One way or another, $file_path is now set, and we can get the base folder name.
		$base_dir = dirname( $file_path ) . '/';
		foreach ( $meta['sizes'] as $size => $data ) {
			// Delete any residual webp versions.
			$webpfile    = $base_dir . $data['file'] . '.webp';
			$webpfileold = preg_replace( '/\.\w+$/', '.webp', $base_dir . $data['file'] );
			if ( ewwwio_is_file( $webpfile ) ) {
				ewwwio_delete_file( $webpfile );
			}
			if ( ewwwio_is_file( $webpfileold ) ) {
				ewwwio_delete_file( $webpfileold );
			}
			$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $base_dir . $data['file'] ) ) );
			// If the original resize is set, and still exists.
			if ( ! empty( $data['orig_file'] ) ) {
				// Retrieve the filename from the metadata.
				$filename = $data['orig_file'];
				$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $base_dir . $data['orig_file'] ) ) );
			}
		}
	}
}

/**
 * Cleans records from database after an image has been renamed.
 *
 * @param string $old_name The filename of the original/old image.
 * @param string $new_name The filename of the new image.
 */
function ewww_image_optimizer_media_rename( $old_name, $new_name ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	if ( ! check_ajax_referer( 'phoenix_media_rename', '_wpnonce', false ) || empty( $_REQUEST['post_id'] ) ) {
		return;
	}
	$id = (int) $_REQUEST['post_id'];
	ewwwio_debug_message( "image renamed from $old_name to $new_name, looking for old records (id $id)" );
	// Finds images to remove from disk, and from db, as well as converted originals.
	$optimized_images = $ewwwdb->get_results( "SELECT id,path,resize,converted FROM $ewwwdb->ewwwio_images WHERE attachment_id = $id AND gallery = 'media'", ARRAY_A );
	if ( ewww_image_optimizer_iterable( $optimized_images ) ) {
		foreach ( $optimized_images as $image ) {
			if ( ! empty( $image['path'] ) ) {
				$image['path'] = ewww_image_optimizer_absolutize_path( $image['path'] );
			}
			ewwwio_debug_message( "checking to see if {$image['path']} is stale" );
			if ( false === strpos( $image['path'], WP_CONTENT_DIR ) ) {
				ewwwio_debug_message( 'not in ' . WP_CONTENT_DIR );
				continue;
			}
			if ( ewwwio_is_file( $image['path'] ) ) {
				ewwwio_debug_message( 'file still exists, skipping' );
				continue;
			}
			if ( ! empty( $image['path'] ) && ewwwio_is_file( $image['path'] . '.webp' ) ) {
				ewwwio_debug_message( 'removing WebP version' );
				ewwwio_delete_file( $image['path'] . '.webp' );
			}
			if ( ! empty( $image['converted'] ) ) {
				$image['converted'] = ewww_image_optimizer_absolutize_path( $image['converted'] );
			}
			if ( ! empty( $image['converted'] ) && ewwwio_is_file( $image['converted'] ) ) {
				ewwwio_debug_message( 'removing "converted" file' );
				ewwwio_delete_file( $image['converted'] );
				if ( ewwwio_is_file( $image['converted'] . '.webp' ) ) {
					ewwwio_debug_message( 'and WebP derivative' );
					ewwwio_delete_file( $image['converted'] . '.webp' );
				}
			}
			if ( 'full' === $image['resize'] ) {
				ewwwio_debug_message( "updating path for $id (full)" );
				$new_path = str_replace( wp_basename( $old_name ), wp_basename( $new_name ), $image['path'] );
				if ( ewwwio_is_file( $new_path ) ) {
					$new_path = ewww_image_optimizer_relativize_path( $new_path );
					$ewwwdb->update(
						$ewwwdb->ewwwio_images,
						array(
							'path' => $new_path,
						),
						array(
							'id' => $image['id'],
						)
					);
					continue;
				}
			}
			$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'id' => $image['id'] ) );
		}
	}
	// Retrieve the image metadata.
	$meta = wp_get_attachment_metadata( $id );
	// If the attachment has an original file set.
	if ( ! empty( $meta['orig_file'] ) && ! ewwwio_is_file( $meta['orig_file'] ) ) {
		// Get the filepath from the metadata.
		$file_path = $meta['orig_file'];

		$webpfile    = $file_path . '.webp';
		$webpfileold = preg_replace( '/\.\w+$/', '.webp', $file_path );
		if ( ewwwio_is_file( $webpfile ) ) {
			ewwwio_delete_file( $webpfile );
		}
		if ( ewwwio_is_file( $webpfileold ) ) {
			ewwwio_delete_file( $webpfileold );
		}
		$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $file_path ) ) );
	}
	ewww_image_optimizer_resize_from_meta_data( $meta, $id );
}

/**
 * Activates Easy IO via AJAX.
 */
function ewww_image_optimizer_exactdn_activate_ajax() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-settings' ) ) {
		die( wp_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) ) ) );
	}
	if ( is_multisite() && defined( 'EXACTDN_SUB_FOLDER' ) && EXACTDN_SUB_FOLDER ) {
		update_site_option( 'ewww_image_optimizer_exactdn', true );
	} elseif ( defined( 'EXACTDN_SUB_FOLDER' ) ) {
		update_option( 'ewww_image_optimizer_exactdn', true );
	} elseif ( is_multisite() && get_site_option( 'exactdn_sub_folder' ) ) {
		update_site_option( 'ewww_image_optimizer_exactdn', true );
	} else {
		update_option( 'ewww_image_optimizer_exactdn', true );
	}
	if ( ! class_exists( 'ExactDN' ) ) {
		/**
		 * Page Parsing class for working with HTML content.
		 */
		require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-eio-page-parser.php' );
		/**
		 * ExactDN class for parsing image urls and rewriting them.
		 */
		require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-exactdn.php' );
		global $exactdn;
		if ( $exactdn->get_exactdn_domain() ) {
			die( wp_json_encode( array( 'success' => esc_html__( 'Easy IO setup and verification is complete.', 'ewww-image-optimizer' ) ) ) );
		}
	}
	global $exactdn_activate_error;
	if ( empty( $exactdn_activate_error ) ) {
		$exactdn_activate_error = 'error unknown';
	}
	$error_message = sprintf(
		/* translators: 1: A link to the documentation 2: the error message/details */
		esc_html__( 'Could not activate Easy IO, please try again in a few minutes. If this error continues, please see %1$s for troubleshooting steps: %2$s', 'ewww-image-optimizer' ),
		'https://docs.ewww.io/article/66-exactdn-not-verified',
		'<code>' . esc_html( $exactdn_activate_error ) . '</code>'
	);
	if ( 'as3cf_cname_active' === $exactdn_activate_error ) {
		$error_message = esc_html__( 'Easy IO cannot optimize your images while using a custom domain (CNAME) in WP Offload Media. Please disable the custom domain in the WP Offload Media settings.', 'ewww-image-optimizer' );
	}
	die(
		wp_json_encode(
			array(
				'error' => $error_message,
			)
		)
	);
}

/**
 * Activates Easy IO via AJAX for a given blog on a multi-site install.
 */
function ewww_image_optimizer_exactdn_activate_site_ajax() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-settings' ) ) {
		die( wp_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) ) ) );
	}
	if ( empty( $_REQUEST['blog_id'] ) ) {
		die( wp_json_encode( array( 'error' => esc_html__( 'Blog ID not provided.', 'ewww-image-optimizer' ) ) ) );
	}
	$blog_id = (int) $_REQUEST['blog_id'];
	if ( get_current_blog_id() !== $blog_id ) {
		switch_to_blog( $blog_id );
	}
	ewwwio_debug_message( "activating site $blog_id" );
	if ( get_option( 'ewww_image_optimizer_exactdn' ) ) {
		die( wp_json_encode( array( 'success' => esc_html__( 'Easy IO setup and verification is complete.', 'ewww-image-optimizer' ) ) ) );
	}
	update_option( 'ewww_image_optimizer_exactdn', true );
	global $exactdn;
	if ( ! class_exists( 'ExactDN' ) ) {
		/**
		 * Page Parsing class for working with HTML content.
		 */
		require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-eio-page-parser.php' );
		/**
		 * ExactDN class for parsing image urls and rewriting them.
		 */
		require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-exactdn.php' );
	} elseif ( is_object( $exactdn ) ) {
		unset( $GLOBALS['exactdn'] );
		$exactdn = new ExactDN();
	}
	if ( $exactdn->get_exactdn_domain() ) {
		ewwwio_debug_message( 'activated site ' . $exactdn->content_url() . ' got domain ' . $exactdn->get_exactdn_domain() );
		die( wp_json_encode( array( 'success' => esc_html__( 'Easy IO setup and verification is complete.', 'ewww-image-optimizer' ) ) ) );
	}
	restore_current_blog();
	global $exactdn_activate_error;
	if ( empty( $exactdn_activate_error ) ) {
		$exactdn_activate_error = 'error unknown';
	}
	$error_message = sprintf(
		/* translators: 1: The blog URL 2: the error message/details */
		esc_html__( 'Could not activate Easy IO on %1$s: %2$s', 'ewww-image-optimizer' ),
		esc_url( get_home_url( $blog_id ) ),
		'<code>' . esc_html( $exactdn_activate_error ) . '</code>'
	);
	if ( 'as3cf_cname_active' === $exactdn_activate_error ) {
		$error_message = esc_html__( 'Easy IO cannot optimize your images while using a custom domain (CNAME) in WP Offload Media. Please disable the custom domain in the WP Offload Media settings.', 'ewww-image-optimizer' );
	}
	die(
		wp_json_encode(
			array(
				'error' => $error_message,
			)
		)
	);
}

/**
 * Registers Easy IO via AJAX for a given blog on a multi-site install.
 */
function ewww_image_optimizer_exactdn_register_site_ajax() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-settings' ) ) {
		die( wp_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) ) ) );
	}
	if ( empty( $_REQUEST['blog_id'] ) ) {
		die( wp_json_encode( array( 'error' => esc_html__( 'Blog ID not provided.', 'ewww-image-optimizer' ) ) ) );
	}
	$blog_id = (int) $_REQUEST['blog_id'];
	if ( get_current_blog_id() !== $blog_id ) {
		$switch = true;
		switch_to_blog( $blog_id );
	}
	ewwwio_debug_message( "registering site $blog_id" );
	if ( get_option( 'ewww_image_optimizer_exactdn' ) ) {
		if ( ! empty( $switch ) ) {
			restore_current_blog();
		}
		die( wp_json_encode( array( 'status' => 'active' ) ) );
	}

	$result = ewww_image_optimizer_register_site_post();
	if ( ! empty( $switch ) ) {
		restore_current_blog();
	}
	if ( is_wp_error( $result ) ) {
		$error_message   = $result->get_error_message();
		$easyio_site_url = get_home_url( $blog_id );
		ewwwio_debug_message( "registration failed for $easyio_site_url: $error_message" );
		die(
			wp_json_encode(
				array(
					'error' => sprintf(
						/* translators: %s: an HTTP error message */
						esc_html__( 'Could not register site, HTTP error: %s', 'ewww-image-optimizer' ),
						$error_message
					),
				)
			)
		);
	} elseif ( ! empty( $result['body'] ) ) {
		$response = json_decode( $result['body'], true );
		if ( ! empty( $response['error'] ) && false !== strpos( strtolower( $response['error'] ), 'duplicate site url' ) ) {
			die( wp_json_encode( array( 'status' => 'registered' ) ) );
		}
		die( wp_json_encode( $response ) );
	}
	$error_message = sprintf(
		/* translators: %s: The blog URL */
		esc_html__( 'Could not register Easy IO for %s: error unknown.', 'ewww-image-optimizer' ),
		esc_url( get_home_url( $blog_id ) )
	);
	die(
		wp_json_encode(
			array(
				'error' => $error_message,
			)
		)
	);
}

/**
 * Registers Easy IO for a new blog on a multi-site install.
 *
 * @param object $new_site WP_Site instace for the new site.
 */
function ewww_image_optimizer_initialize_site( $new_site ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $new_site->id ) ) {
		return;
	}
	if ( ! defined( 'EASYIO_NEW_SITE_AUTOREG' ) || ! EASYIO_NEW_SITE_AUTOREG ) {
		return;
	}
	if ( get_current_blog_id() !== $new_site->id ) {
		$switch = true;
		switch_to_blog( $new_site->id );
	}

	$result          = ewww_image_optimizer_register_site_post();
	$easyio_site_url = get_home_url( $new_site->id );
	if ( ! empty( $switch ) ) {
		restore_current_blog();
	}
	if ( is_wp_error( $result ) ) {
		$error_message = $result->get_error_message();
		ewwwio_debug_message( "registration failed for $easyio_site_url: $error_message" );
	} elseif ( ! empty( $result['body'] ) ) {
		$response = json_decode( $result['body'], true );
		if ( ! empty( $response['error'] ) ) {
			ewwwio_debug_message( "registration failed for $easyio_site_url: {$response['error']}" );
		}
	}
}

/**
 * POSTs the site URL to the API for Easy IO registration.
 *
 * @return array The results of the http POST request.
 */
function ewww_image_optimizer_register_site_post() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Get the site URL for a given blog.
	$eio_base = new EIO_Base();
	$site_url = $eio_base->content_url();

	$key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	if ( empty( $key ) ) {
		return new WP_Error( 'missing_key', __( 'No API key for Easy IO registration', 'ewww-image-optimizer' ) );
	}
	ewwwio_debug_message( "registering $site_url on Easy IO" );
	$url = 'https://optimize.exactlywww.com/exactdn/create.php';
	add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
	$result = wp_remote_post(
		$url,
		array(
			'timeout'   => 60,
			'sslverify' => false,
			'body'      => array(
				'key'      => $key,
				'token'    => $key,
				'site_url' => $site_url,
			),
		)
	);
	return $result;
}

/**
 * Sanitizes an API key for the cloud service.
 *
 * @param string $key An API key entered by the user.
 * @return string A sanitized API key.
 */
function ewww_image_optimizer_cloud_key_sanitize( $key ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$key = trim( $key );
	if ( ! empty( $key ) && strlen( $key ) < 200 && preg_match( '/^[a-zA-Z0-9]+$/', $key ) ) {
		return $key;
	}
	return '';
}

/**
 * Verifies an API key via AJAX.
 */
function ewww_image_optimizer_cloud_key_verify_ajax() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-settings' ) ) {
		die( wp_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) ) ) );
	}
	if ( empty( $_POST['compress_api_key'] ) ) {
		die( wp_json_encode( array( 'error' => esc_html__( 'Please enter your API key and try again.', 'ewww-image-optimizer' ) ) ) );
	}
	$api_key = trim( ewww_image_optimizer_cloud_key_sanitize( wp_unslash( $_POST['compress_api_key'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$url     = 'http://optimize.exactlywww.com/verify/';
	if ( wp_http_supports( array( 'ssl' ) ) ) {
		$url = set_url_scheme( $url, 'https' );
	}
	$result = ewww_image_optimizer_cloud_post_key( $url, $api_key );
	if ( is_wp_error( $result ) ) {
		$url           = set_url_scheme( $url, 'http' );
		$error_message = $result->get_error_message();
		ewwwio_debug_message( "verification failed: $error_message" );
		$result = ewww_image_optimizer_cloud_post_key( $url, $api_key );
	}
	if ( is_wp_error( $result ) ) {
		$error_message = $result->get_error_message();
		ewwwio_debug_message( "verification failed via $url: $error_message" );
		die(
			wp_json_encode(
				array(
					'error' => sprintf(
						/* translators: %s: an HTTP error message */
						esc_html__( 'Could not validate API key, HTTP error: %s', 'ewww-image-optimizer' ),
						$error_message
					),
				)
			)
		);
	} elseif ( ! empty( $result['body'] ) && preg_match( '/(great|exceeded)/', $result['body'] ) ) {
		$verified = $result['body'];
		if ( preg_match( '/exceeded/', $verified ) ) {
			die( wp_json_encode( array( 'error' => esc_html__( 'No credits remaining for API key.', 'ewww-image-optimizer' ) ) ) );
		}
		ewwwio_debug_message( "verification success via: $url" );
		delete_option( 'ewww_image_optimizer_cloud_key_invalid' );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_key', $api_key );
		set_transient( 'ewww_image_optimizer_cloud_status', $verified, HOUR_IN_SECONDS );
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) < 20 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) < 20 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) < 20 && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) ) {
			ewww_image_optimizer_cloud_enable();
		}
		ewwwio_debug_message( "verification body contents: {$result['body']}" );
		die( wp_json_encode( array( 'success' => esc_html__( 'Successfully validated API key, happy optimizing!', 'ewww-image-optimizer' ) ) ) );
	} else {
		ewwwio_debug_message( "verification failed via: $url" );
		if ( ewww_image_optimizer_function_exists( 'print_r' ) ) {
			ewwwio_debug_message( print_r( $result, true ) );
		}
		die( wp_json_encode( array( 'error' => esc_html__( 'Could not validate API key, please copy and paste your key to ensure it is correct.', 'ewww-image-optimizer' ) ) ) );
	}
}

/**
 * Checks to see if all images should be processed via the API.
 *
 * @return bool True if all 'cloud' options are enabled.
 */
function ewww_image_optimizer_full_cloud() {
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 10 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) > 10 ) {
		return true;
	} elseif ( ! defined( 'EWWW_IMAGE_OPTIMIZER_TOOL_PATH' ) ) {
		return true;
	}
	return false;
}

/**
 * Used to turn on the cloud settings when they are all disabled.
 */
function ewww_image_optimizer_cloud_enable() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_level', 30 );
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_level', 20 );
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_gif_level', 10 );
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_pdf_level', 10 );
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_svg_level', 10 );
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_backup_files', 1 );
}

/**
 * Adds the EWWW IO version to the useragent for http requests.
 *
 * @param string $useragent The current useragent used in http requests.
 * @return string The useragent with the EWWW IO version appended.
 */
function ewww_image_optimizer_cloud_useragent( $useragent ) {
	if ( strpos( $useragent, 'EWWW' ) === false ) {
		$useragent .= ' EWWW/' . EWWW_IMAGE_OPTIMIZER_VERSION . ' ';
	}
	return $useragent;
}

/**
 * Submits the api key for verification. Will retrieve the key option if parameter not provided.
 *
 * @global object $ewwwio_async_key_verification
 *
 * @param string $api_key The API key to verify. Default empty string.
 * @param bool   $cache Optional. True to return cached verification results. Default true.
 * @return string|bool False if verification fails, status message otherwise: great/exceeded.
 */
function ewww_image_optimizer_cloud_verify( $api_key, $cache = true ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$sanitize = false;
	if ( empty( $api_key ) ) {
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 10 ) {
			update_site_option( 'ewww_image_optimizer_jpg_level', 10 );
			update_option( 'ewww_image_optimizer_jpg_level', 10 );
		}
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) > 10 && 40 !== (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
			update_site_option( 'ewww_image_optimizer_png_level', 10 );
			update_option( 'ewww_image_optimizer_png_level', 10 );
		}
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) > 0 ) {
			update_site_option( 'ewww_image_optimizer_pdf_level', 0 );
			update_option( 'ewww_image_optimizer_pdf_level', 0 );
		}
		update_site_option( 'ewww_image_optimizer_backup_files', '' );
		update_option( 'ewww_image_optimizer_backup_files', '' );
		return false;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_exceeded' ) > time() ) {
		set_transient( 'ewww_image_optimizer_cloud_status', 'exceeded', HOUR_IN_SECONDS );
		ewwwio_debug_message( 'license exceeded notice has not expired' );
		return 'exceeded';
	}
	$ewww_cloud_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	if ( ! ewww_image_optimizer_detect_wpsf_location_lock() && $cache && preg_match( '/great/', $ewww_cloud_status ) ) {
		ewwwio_debug_message( 'using cached verification' );
		global $ewwwio_async_key_verification;
		if ( ! class_exists( 'WP_Background_Process' ) ) {
			require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
		}
		if ( ! is_object( $ewwwio_async_key_verification ) ) {
			$ewwwio_async_key_verification = new EWWWIO_Async_Key_Verification();
		}
		$ewwwio_async_key_verification->dispatch();
		return $ewww_cloud_status;
	}
	$url = 'http://optimize.exactlywww.com/verify/';
	$ssl = wp_http_supports( array( 'ssl' ) );
	if ( $ssl ) {
		$url = set_url_scheme( $url, 'https' );
	}
	$result = ewww_image_optimizer_cloud_post_key( $url, $api_key );
	if ( is_wp_error( $result ) ) {
		$url           = set_url_scheme( $url, 'http' );
		$error_message = $result->get_error_message();
		ewwwio_debug_message( "verification failed: $error_message" );
		$result = ewww_image_optimizer_cloud_post_key( $url, $api_key );
	}
	if ( is_wp_error( $result ) ) {
		$error_message = $result->get_error_message();
		ewwwio_debug_message( "verification failed via $url: $error_message" );
	} elseif ( ! empty( $result['body'] ) && preg_match( '/(great|exceeded)/', $result['body'] ) ) {
		$verified = $result['body'];
		if ( preg_match( '/exceeded/', $verified ) ) {
			ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_exceeded', time() + 300 );
		}
		if ( false !== strpos( $result['body'], 'expired' ) ) {
			ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_key', '' );
		}
		ewwwio_debug_message( "verification success via: $url" );
		delete_option( 'ewww_image_optimizer_cloud_key_invalid' );
	} else {
		update_option( 'ewww_image_optimizer_cloud_key_invalid', true, false );
		if ( ! empty( $result['body'] ) && false !== strpos( $result['body'], 'invalid' ) ) {
			ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_key', '' );
		}
		ewwwio_debug_message( "verification failed via: $url" );
		if ( ewww_image_optimizer_function_exists( 'print_r' ) ) {
			ewwwio_debug_message( print_r( $result, true ) );
		}
	}
	if ( empty( $verified ) ) {
		ewwwio_memory( __FUNCTION__ );
		return false;
	} else {
		set_transient( 'ewww_image_optimizer_cloud_status', $verified, HOUR_IN_SECONDS );
		ewwwio_debug_message( "verification body contents: {$result['body']}" );
		ewwwio_memory( __FUNCTION__ );
		return $verified;
	}
}

/**
 * POSTs the API key to the API for verification.
 *
 * @param string $url The address of the server to use.
 * @param string $key The API key to submit via POST.
 * @return array The results of the http POST request.
 */
function ewww_image_optimizer_cloud_post_key( $url, $key ) {
	add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
	$result = wp_remote_post(
		$url,
		array(
			'timeout'   => 5,
			'sslverify' => false,
			'body'      => array(
				'api_key' => $key,
			),
		)
	);
	return $result;
}

/**
 * Let the user know their key is invalid.
 */
function ewww_image_optimizer_notice_invalid_key() {
	echo "<div id='ewww-image-optimizer-invalid-key' class='notice notice-error'><p><strong>" . esc_html__( 'Could not validate EWWW Image Optimizer API key, please check your key to ensure it is correct.', 'ewww-image-optimizer' ) . '</strong></p></div>';
}

/**
 * Checks the configured API key for quota information.
 *
 * @param bool $raw True to return the usage array as-is.
 * @return string A message with how many credits they have used/left and possibly a renwal date.
 */
function ewww_image_optimizer_cloud_quota( $raw = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$api_key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	$url     = 'http://optimize.exactlywww.com/quota/v2/';
	$ssl     = wp_http_supports( array( 'ssl' ) );
	if ( $ssl ) {
		$url = set_url_scheme( $url, 'https' );
	}
	add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
	$result = wp_remote_post(
		$url,
		array(
			'timeout'   => 5,
			'sslverify' => false,
			'body'      => array(
				'api_key' => $api_key,
			),
		)
	);
	if ( is_wp_error( $result ) ) {
		$error_message = $result->get_error_message();
		ewwwio_debug_message( "quota request failed: $error_message" );
		ewwwio_memory( __FUNCTION__ );
		return '';
	} elseif ( ! empty( $result['body'] ) ) {
		ewwwio_debug_message( "quota data retrieved: {$result['body']}" );
		// $quota = explode( ' ', $result['body'] );.
		$quota = json_decode( $result['body'], true );
		if ( ! is_array( $quota ) ) {
			return '';
		}
		ewwwio_memory( __FUNCTION__ );
		if ( $raw ) {
			return $quota;
		}
		if ( ! empty( $quota['unlimited'] ) && $quota['consumed'] >= 0 ) {
			$consumed  = (int) $quota['consumed'];
			$soft_cap  = '<a title="Help" data-beacon-article="608ddf128996210f18bd95d3" href="https://docs.ewww.io/article/101-soft-quotas-on-unlimited-plans">' . (int) $quota['soft_cap'] . '</a>';
			$soft_cap .= ewwwio_get_help_link( 'https://docs.ewww.io/article/101-soft-quotas-on-unlimited-plans', '608ddf128996210f18bd95d3' );
			return sprintf(
				/* translators: 1: Number of images optimized, 2: image quota */
				__( 'optimized %1$d (of %2$s) images.', 'ewww-image-optimizer' ),
				$consumed,
				$soft_cap
			);
		} elseif ( ! $quota['licensed'] && $quota['consumed'] > 0 ) {
			return sprintf(
				/* translators: 1: Number of images 2: Number of days until renewal */
				_n( 'optimized %1$d images, renewal is in %2$d day.', 'optimized %1$d images, renewal is in %2$d days.', $quota['days'], 'ewww-image-optimizer' ),
				$quota['consumed'],
				$quota['days']
			);
		} elseif ( ! $quota['licensed'] && $quota['consumed'] < 0 ) {
			return sprintf(
				/* translators: 1: Number of image credits for the compression API */
				_n( '%1$d image credit remaining.', '%1$d image credits remaining.', abs( $quota['consumed'] ), 'ewww-image-optimizer' ),
				abs( $quota['consumed'] )
			);
		} elseif ( $quota['licensed'] > 0 && $quota['consumed'] <= 0 ) {
			$real_quota = (int) $quota['licensed'] - (int) $quota['consumed'];
			return sprintf(
				/* translators: 1: Number of image credits for the compression API */
				_n( '%1$d image credit remaining.', '%1$d image credits remaining.', $real_quota, 'ewww-image-optimizer' ),
				$real_quota
			);
		} elseif ( ! $quota['licensed'] && ! $quota['consumed'] && ! $quota['days'] && ! $quota['metered'] ) {
			return __( 'no credits remaining, please purchase more.', 'ewww-image-optimizer' );
		} else {
			return sprintf(
				/* translators: 1: Number of image credits used 2: Number of image credits available 3: days until subscription renewal */
				_n( 'used %1$d of %2$d, usage will reset in %3$d day.', 'used %1$d of %2$d, usage will reset in %3$d days.', $quota['days'], 'ewww-image-optimizer' ),
				$quota['consumed'],
				$quota['licensed'],
				$quota['days']
			);
		}
	}
}

/**
 * Submits an image to the cloud optimizer and saves the optimized image to disk.
 *
 * Returns an array of the $file, $converted, possibly a $msg, and the $new_size.
 *
 * @global object $ewww_image Contains more information about the image currently being processed.
 *
 * @param string $file Full absolute path to the image file.
 * @param string $type Mimetype of $file.
 * @param bool   $convert Optional. True if we want to attempt conversion of $file. Default false.
 * @param string $newfile Optional. Filename to be used if image is converted. Default null.
 * @param string $newtype Optional. Mimetype expected if image is converted. Default null.
 * @param bool   $fullsize Optional. True if this is an original upload. Default false.
 * @param array  $jpg_fill Optional. Fill color for PNG to JPG conversion in hex format.
 * @param int    $jpg_quality Optional. JPG quality level. Default null. Accepts 1-100.
 * @return array {
 *     Information about the cloud optimization.
 *
 *     @type string Filename of the optimized version.
 *     @type bool True if the image was converted.
 *     @type string Set to 'exceeded' if the API key is out of credits. Or 'exceeded quota' if soft quota is reached.
 *     @type int File size of the (new) image.
 * }
 */
function ewww_image_optimizer_cloud_optimizer( $file, $type, $convert = false, $newfile = null, $newtype = null, $fullsize = false, $jpg_fill = '', $jpg_quality = 82 ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! ewwwio_is_file( $file ) || ! is_writable( $file ) || false !== strpos( $file, '../' ) ) {
		return array( $file, false, 'invalid file', 0, '' );
	}
	if ( ! ewwwio_check_memory_available( filesize( $file ) * 2.2 ) ) { // 2.2 = upload buffer + download buffer (2) multiplied by a factor of 1.1 for extra wiggle room.
		$memory_required = filesize( $file ) * 2.2;
		ewwwio_debug_message( "possibly insufficient memory for cloud (optimize) operation: $memory_required" );
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			add_filter( 'image_memory_limit', 'ewww_image_optimizer_raise_memory_limit' );
			wp_raise_memory_limit( 'image' );
		}
	}
	$api_key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	if ( 'exceeded' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
		$started = microtime( true );
		if ( ! ewww_image_optimizer_cloud_verify( $api_key ) ) {
			return array( $file, false, 'key verification failed', 0, '' );
		}
		// Calculate how much time has elapsed since we started.
		$elapsed = microtime( true ) - $started;
		ewwwio_debug_message( "cloud verify took $elapsed seconds" );
	}
	if ( 'exceeded quota' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
		ewwwio_debug_message( 'soft quota reached, image not processed' );
		return array( $file, false, 'exceeded quota', 0, '' );
	}
	if ( 'exceeded' === get_transient( 'ewww_image_optimizer_cloud_status' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_exceeded' ) > time() ) {
		ewwwio_debug_message( 'license exceeded, image not processed' );
		return array( $file, false, 'exceeded', 0, '' );
	}
	global $ewww_force;
	global $ewww_force_smart;
	global $eio_filesystem;
	ewwwio_get_filesystem();
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_skip_full' ) && $fullsize ) {
		$metadata = 1;
	} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ) {
		// Don't copy metadata.
		$metadata = 0;
	} else {
		// Copy all the metadata.
		$metadata = 1;
	}
	if ( empty( $convert ) ) {
		$convert = 0;
	} else {
		$convert = 1;
	}
	$lossy_fast = 0;
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_lossy_skip_full' ) && $fullsize ) {
		$lossy = 0;
	} elseif ( 'image/png' === $type && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) >= 40 ) {
		$lossy = 1;
		if ( 40 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
			$lossy_fast = 1;
		}
	} elseif ( 'image/jpeg' === $type && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) >= 30 ) {
		$lossy = 1;
		if ( 30 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
			$lossy_fast = 1;
		}
	} elseif ( 'application/pdf' === $type && 20 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) ) {
		$lossy = 1;
	} else {
		$lossy = 0;
	}
	if ( strpos( $file, '/wp-admin/' ) || strpos( $file, '/wp-includes/' ) || strpos( $file, '/wp-content/themes/' ) || strpos( $file, '/wp-content/plugins/' ) ) {
		$lossy      = 0;
		$lossy_fast = 0;
	}
	$sharp_yuv = defined( 'EIO_WEBP_SHARP_YUV' ) && EIO_WEBP_SHARP_YUV ? 1 : 0;
	if ( 'image/webp' === $newtype ) {
		$webp        = 1;
		$jpg_quality = apply_filters( 'webp_quality', 75, 'image/webp' );
		if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_LOSSY_PNG2WEBP' ) || ! EWWW_IMAGE_OPTIMIZER_LOSSY_PNG2WEBP ) {
			$lossy = 0;
		}
		if ( defined( 'EWWW_IMAGE_OPTIMIZER_LOSSY_GIF2WEBP' ) && ! EWWW_IMAGE_OPTIMIZER_LOSSY_GIF2WEBP ) {
			$lossy = 1;
		}
	} else {
		$webp = 0;
	}
	if ( $jpg_quality < 50 ) {
		$jpg_quality = 75;
	}
	$png_compress = 0;
	if ( 'image/svg+xml' === $type && 10 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_svg_level' ) ) {
		$png_compress = 1;
	}
	if ( 'image/png' === $type && 30 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
		$png_compress = 1;
	}
	if ( ! $webp && ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' )
			&& strpos( $file, '/wp-admin/' ) === false
			&& strpos( $file, '/wp-includes/' ) === false
			&& strpos( $file, '/wp-content/themes/' ) === false
			&& strpos( $file, '/wp-content/plugins/' ) === false
			&& strpos( $file, '/cache/' ) === false
			&& strpos( $file, '/dynamic/' ) === false // Nextgen dynamic images.
	) {
		global $ewww_image;
		if ( is_object( $ewww_image ) && $ewww_image->file === $file && ! empty( $ewww_image->backup ) ) {
			$hash = $ewww_image->backup;
		}
		if ( empty( $hash ) && ( ! empty( $ewww_force ) || ! empty( $ewww_force_smart ) ) ) {
			$image = ewww_image_optimizer_find_already_optimized( $file );
			if ( ! empty( $image ) && is_array( $image ) && ! empty( $image['backup'] ) ) {
				$hash = $image['backup'];
			}
		}
		if ( empty( $hash ) ) {
			$hash = uniqid() . hash( 'sha256', $file );
		}
		$domain = parse_url( get_site_url(), PHP_URL_HOST );
	} else {
		$hash   = '';
		$domain = parse_url( get_site_url(), PHP_URL_HOST );
	}
	ewwwio_debug_message( "file: $file " );
	ewwwio_debug_message( "type: $type" );
	ewwwio_debug_message( "convert: $convert" );
	ewwwio_debug_message( "newfile: $newfile" );
	ewwwio_debug_message( "newtype: $newtype" );
	ewwwio_debug_message( "webp: $webp" );
	ewwwio_debug_message( "sharp_yuv: $sharp_yuv" );
	ewwwio_debug_message( "jpg fill: $jpg_fill" );
	ewwwio_debug_message( "jpg quality: $jpg_quality" );
	$free_exec = EWWW_IMAGE_OPTIMIZER_NOEXEC && 'image/jpeg' === $type;
	if (
		! $free_exec &&
		defined( 'EWWW_IMAGE_OPTIMIZER_JPEGTRAN' ) && ! EWWW_IMAGE_OPTIMIZER_JPEGTRAN &&
		'image/jpeg' === $type
	) {
		$free_exec = true;
	}
	if ( ! $free_exec && $webp ) {
		$free_exec = true;
	}
	if ( empty( $api_key ) && ! $free_exec ) {
		ewwwio_debug_message( 'no API key and free_exec mode inactive' );
		return array( $file, false, 'key verification failed', 0, '' );
	}
	$url = 'http://optimize.exactlywww.com/v2/';
	$ssl = wp_http_supports( array( 'ssl' ) );
	if ( $ssl ) {
		$url = set_url_scheme( $url, 'https' );
	}
	$boundary = wp_generate_password( 24, false );

	$headers = array(
		'content-type' => 'multipart/form-data; boundary=' . $boundary,
		'timeout'      => 300,
		'httpversion'  => '1.0',
		'blocking'     => true,
	);

	$post_fields = array(
		'filename'   => $file,
		'convert'    => $convert,
		'metadata'   => $metadata,
		'api_key'    => $api_key,
		'jpg_fill'   => $jpg_fill,
		'quality'    => $jpg_quality,
		'compress'   => $png_compress,
		'lossy'      => $lossy,
		'lossy_fast' => $lossy_fast,
		'webp'       => $webp,
		'sharp_yuv'  => $sharp_yuv,
		'backup'     => $hash,
		'domain'     => $domain,
	);

	$payload = '';
	foreach ( $post_fields as $name => $value ) {
		$payload .= '--' . $boundary;
		$payload .= "\r\n";
		$payload .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
		$payload .= $value;
		$payload .= "\r\n";
	}

	$payload .= '--' . $boundary;
	$payload .= "\r\n";
	$payload .= 'Content-Disposition: form-data; name="file"; filename="' . wp_basename( $file ) . '"' . "\r\n";
	$payload .= 'Content-Type: ' . $type . "\r\n";
	$payload .= "\r\n";
	$payload .= $eio_filesystem->get_contents( $file );
	$payload .= "\r\n";
	$payload .= '--' . $boundary;
	$payload .= 'Content-Disposition: form-data; name="submitHandler"' . "\r\n";
	$payload .= "\r\n";
	$payload .= "Upload\r\n";
	$payload .= '--' . $boundary . '--';

	add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
	$response = wp_remote_post(
		$url,
		array(
			'timeout'   => 300,
			'headers'   => $headers,
			'sslverify' => false,
			'body'      => $payload,
		)
	);
	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		ewwwio_debug_message( "optimize failed: $error_message" );
		return array( $file, false, 'cloud optimize failed', 0, '' );
	} elseif ( empty( $response['body'] ) ) {
		ewwwio_debug_message( 'cloud results: no savings' );
		return array( $file, false, '', filesize( $file ), $hash );
	} else {
		$tempfile = $file . '.tmp';
		file_put_contents( $tempfile, $response['body'] );
		$orig_size = filesize( $file );
		$newsize   = $orig_size;
		$converted = false;
		$msg       = '';
		if ( 100 > strlen( $response['body'] ) && strpos( $response['body'], 'invalid' ) ) {
			ewwwio_debug_message( 'License Invalid' );
			ewww_image_optimizer_remove_cloud_key( 'none' );
		} elseif ( 100 > strlen( $response['body'] ) && strpos( $response['body'], 'exceeded quota' ) ) {
			ewwwio_debug_message( 'Soft quota Exceeded' );
			set_transient( 'ewww_image_optimizer_cloud_status', 'exceeded quota', HOUR_IN_SECONDS );
			$msg = 'exceeded quota';
			ewwwio_delete_file( $tempfile );
		} elseif ( 100 > strlen( $response['body'] ) && strpos( $response['body'], 'exceeded' ) ) {
			ewwwio_debug_message( 'License Exceeded' );
			set_transient( 'ewww_image_optimizer_cloud_status', 'exceeded', HOUR_IN_SECONDS );
			$msg = 'exceeded';
			ewwwio_delete_file( $tempfile );
		} elseif ( ewww_image_optimizer_mimetype( $tempfile, 'i' ) === $type ) {
			$newsize = filesize( $tempfile );
			ewwwio_debug_message( "cloud results: $newsize (new) vs. $orig_size (original)" );
			ewwwio_rename( $tempfile, $file );
		} elseif ( ewww_image_optimizer_mimetype( $tempfile, 'i' ) === 'image/webp' ) {
			$newsize = filesize( $tempfile );
			ewwwio_debug_message( "cloud results: $newsize (new) vs. $orig_size (original)" );
			ewwwio_rename( $tempfile, $newfile );
		} elseif ( ! is_null( $newtype ) && ! is_null( $newfile ) && ewww_image_optimizer_mimetype( $tempfile, 'i' ) === $newtype ) {
			ewwwio_debug_message( "renaming file from $tempfile to $newfile" );
			if ( ewwwio_rename( $tempfile, $newfile ) ) {
				$converted = true;
				$newsize   = filesize( $newfile );
				$file      = $newfile;
				ewwwio_debug_message( "cloud results: $newsize (new) vs. $orig_size (original)" );
			}
		} else {
			ewwwio_delete_file( $tempfile );
		}
		ewwwio_memory( __FUNCTION__ );
		return array( $file, $converted, $msg, $newsize, $hash );
	} // End if().
}

/**
 * Automatically corrects JPG rotation using API servers.
 *
 * @param string $file Name of the file to fix.
 * @param string $type File type of the file.
 *
 * @return bool True if the rotation was successful.
 */
function ewww_image_optimizer_cloud_autorotate( $file, $type ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! ewwwio_check_memory_available( filesize( $file ) * 2.2 ) ) { // 2.2 = upload buffer + download buffer (2) multiplied by a factor of 1.1 for extra wiggle room.
		$memory_required = filesize( $file ) * 2.2;
		ewwwio_debug_message( "possibly insufficient memory for cloud (rotate) operation: $memory_required" );
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			add_filter( 'image_memory_limit', 'ewww_image_optimizer_raise_memory_limit' );
			wp_raise_memory_limit( 'image' );
		}
	}
	$api_key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	if ( empty( $api_key ) ) {
		return false;
	}
	$started = microtime( true );
	if ( 'exceeded' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
		if ( ! ewww_image_optimizer_cloud_verify( $api_key ) ) {
			ewwwio_debug_message( 'cloud verify failed, image not rotated' );
			return false;
		}
	}
	// Calculate how much time has elapsed since we started.
	$elapsed = microtime( true ) - $started;
	ewwwio_debug_message( "cloud verify took $elapsed seconds" );
	if ( 'exceeded' === get_transient( 'ewww_image_optimizer_cloud_status' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_exceeded' ) > time() ) {
		ewwwio_debug_message( 'license exceeded, image not rotated' );
		return false;
	}
	global $eio_filesystem;
	ewwwio_get_filesystem();
	ewwwio_debug_message( "file: $file " );
	ewwwio_debug_message( "type: $type" );
	$url = 'http://optimize.exactlywww.com/rotate/';
	$ssl = wp_http_supports( array( 'ssl' ) );
	if ( $ssl ) {
		$url = set_url_scheme( $url, 'https' );
	}
	$boundary = wp_generate_password( 24, false );

	$headers = array(
		'content-type' => 'multipart/form-data; boundary=' . $boundary,
		'timeout'      => 60,
		'httpversion'  => '1.0',
		'blocking'     => true,
	);

	$post_fields = array(
		'filename' => $file,
		'api_key'  => $api_key,
	);

	$payload = '';
	foreach ( $post_fields as $name => $value ) {
		$payload .= '--' . $boundary;
		$payload .= "\r\n";
		$payload .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
		$payload .= $value;
		$payload .= "\r\n";
	}

	$payload .= '--' . $boundary;
	$payload .= "\r\n";
	$payload .= 'Content-Disposition: form-data; name="file"; filename="' . wp_basename( $file ) . '"' . "\r\n";
	$payload .= 'Content-Type: ' . $type . "\r\n";
	$payload .= "\r\n";
	$payload .= $eio_filesystem->get_contents( $file );
	$payload .= "\r\n";
	$payload .= '--' . $boundary;
	$payload .= 'Content-Disposition: form-data; name="submitHandler"' . "\r\n";
	$payload .= "\r\n";
	$payload .= "Upload\r\n";
	$payload .= '--' . $boundary . '--';

	add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
	$response = wp_remote_post(
		$url,
		array(
			'timeout'   => 60,
			'headers'   => $headers,
			'sslverify' => false,
			'body'      => $payload,
		)
	);
	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		ewwwio_debug_message( "rotate failed: $error_message" );
		return false;
	} else {
		$tempfile = $file . '.tmp';
		file_put_contents( $tempfile, $response['body'] );
		$orig_size = filesize( $file );
		$newsize   = $orig_size;
		if ( preg_match( '/exceeded/', $response['body'] ) ) {
			ewwwio_debug_message( 'License Exceeded' );
			set_transient( 'ewww_image_optimizer_cloud_status', 'exceeded', HOUR_IN_SECONDS );
			ewwwio_delete_file( $tempfile );
		} elseif ( ewww_image_optimizer_mimetype( $tempfile, 'i' ) === $type ) {
			$newsize = filesize( $tempfile );
			ewwwio_debug_message( "cloud rotation success: $newsize (new) vs. $orig_size (original)" );
			ewwwio_rename( $tempfile, $file );
			return true;
		} else {
			ewwwio_delete_file( $tempfile );
		}
		ewwwio_memory( __FUNCTION__ );
		return false;
	}
}

/**
 * Backup an image using API servers.
 *
 * @since 4.8.0
 *
 * @param string $file Name of the file to backup.
 */
function ewww_image_optimizer_cloud_backup( $file ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$api_key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	if ( empty( $api_key ) ) {
		return false;
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ) {
		return false;
	}
	if ( ! ewwwio_is_file( $file ) || ! ewwwio_is_readable( $file ) ) {
		return false;
	}
	if ( ! ewwwio_check_memory_available( filesize( $file ) * 1.1 ) ) { // 1.1 = upload buffer (filesize) multiplied by a factor of 1.1 for extra wiggle room.
		$memory_required = filesize( $file ) * 1.1;
		ewwwio_debug_message( "possibly insufficient memory for cloud (backup) operation: $memory_required" );
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			add_filter( 'image_memory_limit', 'ewww_image_optimizer_raise_memory_limit' );
			wp_raise_memory_limit( 'image' );
		}
	}
	if ( ! ewww_image_optimizer_cloud_verify( $api_key ) ) {
		ewwwio_debug_message( 'cloud verify failed, image not backed up' );
		return false;
	}
	ewwwio_debug_message( "file: $file " );
	$url = 'http://optimize.exactlywww.com/backup/store.php';
	$ssl = wp_http_supports( array( 'ssl' ) );
	if ( $ssl ) {
		$url = set_url_scheme( $url, 'https' );
	}
	$boundary = wp_generate_password( 24, false );

	$headers = array(
		'content-type' => 'multipart/form-data; boundary=' . $boundary,
		'timeout'      => 20,
		'httpversion'  => '1.0',
		'blocking'     => true,
	);

	$post_fields = array(
		'filename' => $file,
		'api_key'  => $api_key,
	);

	global $ewww_image;
	global $eio_filesystem;
	ewwwio_get_filesystem();
	if ( is_object( $ewww_image ) && $ewww_image->file === $file && ! empty( $ewww_image->backup ) ) {
		$post_fields['backup'] = $ewww_image->backup;
	} elseif ( is_object( $ewww_image ) && $ewww_image->file === $file && empty( $ewww_image->backup ) ) {
		$post_fields['backup'] = uniqid() . hash( 'sha256', $file );
		$ewww_image->backup    = $post_fields['backup'];
	} else {
		ewwwio_debug_message( 'probably a new upload, not backing up yet' );
		return false;
	}
	$payload = '';
	foreach ( $post_fields as $name => $value ) {
		$payload .= '--' . $boundary;
		$payload .= "\r\n";
		$payload .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
		$payload .= $value;
		$payload .= "\r\n";
	}

	$payload .= '--' . $boundary;
	$payload .= "\r\n";
	$payload .= 'Content-Disposition: form-data; name="file"; filename="' . wp_basename( $file ) . '"' . "\r\n";
	$payload .= 'Content-Type: ' . ewww_image_optimizer_mimetype( $file, 'i' ) . "\r\n";
	$payload .= "\r\n";
	$payload .= $eio_filesystem->get_contents( $file );
	$payload .= "\r\n";
	$payload .= '--' . $boundary;
	$payload .= 'Content-Disposition: form-data; name="submitHandler"' . "\r\n";
	$payload .= "\r\n";
	$payload .= "Upload\r\n";
	$payload .= '--' . $boundary . '--';

	add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
	$response = wp_remote_post(
		$url,
		array(
			'timeout'   => 20,
			'headers'   => $headers,
			'sslverify' => false,
			'body'      => $payload,
		)
	);
	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		ewwwio_debug_message( "backup failed: $error_message" );
		return false;
	} else {
		if ( false !== strpos( $response['body'], 'error' ) ) {
			return false;
		} elseif ( false !== strpos( $response['body'], 'success' ) ) {
			ewwwio_debug_message( 'cloud backup success' );
			return true;
		} else {
			return false;
		}
	}
	ewwwio_memory( __FUNCTION__ );
	return false;
}

/**
 * Uses the API to resize images.
 *
 * @since 4.4.0
 *
 * @param string $file The file to resize.
 * @param string $type File type of the file.
 * @param int    $dst_x X-coordinate of destination image (usually 0).
 * @param int    $dst_y Y-coordinate of destination image (usually 0).
 * @param int    $src_x X-coordinate of source image (usually 0 unless cropping).
 * @param int    $src_y Y-coordinate of source image (usually 0 unless cropping).
 * @param int    $dst_w Desired image width.
 * @param int    $dst_h Desired image height.
 * @param int    $src_w Source width.
 * @param int    $src_h Source height.
 * @return string|WP_Error The image contents or the error message.
 */
function ewww_image_optimizer_cloud_resize( $file, $type, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! ewwwio_check_memory_available( filesize( $file ) * 2.2 ) ) { // 2.2 = upload buffer + download buffer (2) multiplied by a factor of 1.1 for extra wiggle room.
		$memory_required = filesize( $file ) * 2.2;
		ewwwio_debug_message( "possibly insufficient memory for cloud (resize) operation: $memory_required" );
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			add_filter( 'image_memory_limit', 'ewww_image_optimizer_raise_memory_limit' );
			wp_raise_memory_limit( 'image' );
		}
	}
	$api_key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	if ( empty( $api_key ) ) {
		return new WP_Error( 'invalid_key', __( 'Could not verify API key', 'ewww-image-optimizer' ) );
	}
	$started = microtime( true );
	if ( 'exceeded' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
		if ( ! ewww_image_optimizer_cloud_verify( $api_key ) ) {
			ewwwio_debug_message( 'cloud verify failed, image not resized' );
			return new WP_Error( 'invalid_key', __( 'Could not verify API key', 'ewww-image-optimizer' ) );
		}
	}
	// Calculate how much time has elapsed since we started.
	$elapsed = microtime( true ) - $started;
	ewwwio_debug_message( "cloud verify took $elapsed seconds" );
	if ( 'exceeded' === get_transient( 'ewww_image_optimizer_cloud_status' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_exceeded' ) > time() ) {
		ewwwio_debug_message( 'license exceeded, image not rotated' );
		return new WP_Error( 'invalid_key', __( 'License Exceeded', 'ewww-image-optimizer' ) );
	}
	global $eio_filesystem;
	ewwwio_get_filesystem();
	ewwwio_debug_message( "file: $file " );
	ewwwio_debug_message( "width: $dst_w" );
	ewwwio_debug_message( "height: $dst_h" );
	$url = 'http://optimize.exactlywww.com/resize/';
	$ssl = wp_http_supports( array( 'ssl' ) );
	if ( $ssl ) {
		$url = set_url_scheme( $url, 'https' );
	}
	$boundary = wp_generate_password( 24, false );

	$headers = array(
		'content-type' => 'multipart/form-data; boundary=' . $boundary,
		'timeout'      => 60,
		'httpversion'  => '1.0',
		'blocking'     => true,
	);

	$post_fields = array(
		'filename' => $file,
		'api_key'  => $api_key,
		'dst_x'    => (int) $dst_x,
		'dst_y'    => (int) $dst_y,
		'src_x'    => (int) $src_x,
		'src_y'    => (int) $src_y,
		'dst_w'    => (int) $dst_w,
		'dst_h'    => (int) $dst_h,
		'src_w'    => (int) $src_w,
		'src_h'    => (int) $src_h,
	);

	$payload = '';
	foreach ( $post_fields as $name => $value ) {
		$payload .= '--' . $boundary;
		$payload .= "\r\n";
		$payload .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
		$payload .= $value;
		$payload .= "\r\n";
	}

	$payload .= '--' . $boundary;
	$payload .= "\r\n";
	$payload .= 'Content-Disposition: form-data; name="file"; filename="' . wp_basename( $file ) . '"' . "\r\n";
	$payload .= 'Content-Type: ' . $type . "\r\n";
	$payload .= "\r\n";
	$payload .= $eio_filesystem->get_contents( $file );
	$payload .= "\r\n";
	$payload .= '--' . $boundary;
	$payload .= 'Content-Disposition: form-data; name="submitHandler"' . "\r\n";
	$payload .= "\r\n";
	$payload .= "Upload\r\n";
	$payload .= '--' . $boundary . '--';

	add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
	$response = wp_remote_post(
		$url,
		array(
			'timeout'   => 60,
			'headers'   => $headers,
			'sslverify' => false,
			'body'      => $payload,
		)
	);
	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		ewwwio_debug_message( "resize failed: $error_message" );
		return $response;
	} else {
		$tempfile = $file . '.tmp';
		file_put_contents( $tempfile, $response['body'] );
		$orig_size = filesize( $file );
		$newsize   = $orig_size;
		if ( false !== strpos( $response['body'], 'error' ) ) {
			$response = json_decode( $response['body'], true );
			ewwwio_debug_message( 'API resize error: ' . $response['error'] );
			ewwwio_delete_file( $tempfile );
			return new WP_Error( 'image_resize_error', $response['error'] );
		} elseif ( false !== strpos( ewww_image_optimizer_mimetype( $tempfile, 'i' ), 'image' ) ) {
			$newsize = filesize( $tempfile );
			ewww_image_optimizer_is_animated( $tempfile );
			ewwwio_debug_message( "API resize success: $newsize (new) vs. $orig_size (original)" );
			ewwwio_delete_file( $tempfile );
			return $response['body'];
		}
		ewwwio_delete_file( $tempfile );
		ewwwio_debug_message( 'API resize error: unknown' );
		return new WP_Error( 'image_resize_error', __( 'Unknown resize error', 'ewww-image-optimizer' ) );
	}
}

/**
 * Setup our own database connection with full utf8 capability.
 *
 * @global object $ewwwdb A new database connection with super powers.
 * @global string $table_prefix The table prefix for the WordPress database.
 */
function ewww_image_optimizer_db_init() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewwwdb, $table_prefix;
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewwwdb.php' );
	if ( ! defined( 'DB_USER' ) || ! defined( 'DB_PASSWORD' ) || ! defined( 'DB_NAME' ) || ! defined( 'DB_HOST' ) ) {
		global $wpdb;
		$ewwwdb = $wpdb;
		return;
	} elseif ( ! isset( $ewwwdb ) ) {
		$ewwwdb = new EwwwDB( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	}

	if ( ! empty( $ewwwdb->error ) ) {
		dead_db();
	}

	$ewwwdb->field_types = array(
		'post_author'      => '%d',
		'post_parent'      => '%d',
		'menu_order'       => '%d',
		'term_id'          => '%d',
		'term_group'       => '%d',
		'term_taxonomy_id' => '%d',
		'parent'           => '%d',
		'count'            => '%d',
		'object_id'        => '%d',
		'term_order'       => '%d',
		'ID'               => '%d',
		'comment_ID'       => '%d',
		'comment_post_ID'  => '%d',
		'comment_parent'   => '%d',
		'user_id'          => '%d',
		'link_id'          => '%d',
		'link_owner'       => '%d',
		'link_rating'      => '%d',
		'option_id'        => '%d',
		'blog_id'          => '%d',
		'meta_id'          => '%d',
		'post_id'          => '%d',
		'user_status'      => '%d',
		'umeta_id'         => '%d',
		'comment_karma'    => '%d',
		'comment_count'    => '%d',
		// multisite.
		'active'           => '%d',
		'cat_id'           => '%d',
		'deleted'          => '%d',
		'lang_id'          => '%d',
		'mature'           => '%d',
		'public'           => '%d',
		'site_id'          => '%d',
		'spam'             => '%d',
	);

	$prefix = $ewwwdb->set_prefix( $table_prefix );

	// Setup blog_id and prefix for multisite (and fallback).
	if ( is_wp_error( $prefix ) || is_multisite() ) {
		global $wpdb;
		$ewwwdb->prefix = $wpdb->prefix;
		$ewwwdb->blogid = $wpdb->blogid;
		// and just in case we need it...
		$ewwwdb->base_prefix = $wpdb->base_prefix;
	}

	if ( ! isset( $ewwwdb->ewwwio_images ) ) {
		$ewwwdb->ewwwio_images = $ewwwdb->prefix . 'ewwwio_images';
	}
	if ( ! isset( $ewwwdb->ewwwio_queue ) ) {
		$ewwwdb->ewwwio_queue = $ewwwdb->prefix . 'ewwwio_queue';
	}
}

/**
 * Inserts a single record into the table as pending, or marks it pending if it exists.
 *
 * @global object $ewwwdb A new database connection with super powers.
 *
 * @param string $path The filename of the image.
 * @param string $gallery The type (origin) of the image.
 * @param int    $attachment_id The attachment ID, if there is one.
 * @param string $size The name of the resize for the image.
 * @return int The row ID of the record updated/inserted.
 */
function ewww_image_optimizer_single_insert( $path, $gallery = '', $attachment_id = '', $size = '' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewww_image_optimizer_db_init();
	global $ewwwdb;

	$already_optimized = ewww_image_optimizer_find_already_optimized( $path );
	if ( is_array( $already_optimized ) && ! empty( $already_optimized ) ) {
		if ( ! empty( $already_optimized['pending'] ) ) {
			ewwwio_debug_message( "already pending record for $path - {$already_optimized['id']}" );
			return $already_optimized['id'];
		}
		$ewwwdb->update(
			$ewwwdb->ewwwio_images,
			array(
				'pending' => 1,
			),
			array(
				'id' => $already_optimized['id'],
			)
		);
		return $already_optimized['id'];
	} else {
		ewwwio_debug_message( "queuing $path" );
		$orig_size = ewww_image_optimizer_filesize( $path );
		$path      = ewww_image_optimizer_relativize_path( $path );
		if ( seems_utf8( $path ) ) {
			$utf8_file_path = $path;
		} else {
			$utf8_file_path = utf8_encode( $path );
		}
		$to_insert = array(
			'path'      => $utf8_file_path,
			'orig_size' => $orig_size,
			'pending'   => 1,
		);
		if ( $gallery ) {
			$to_insert['gallery'] = $gallery;
		}
		if ( $attachment_id ) {
			$to_insert['attachment_id'] = $attachment_id;
		}
		if ( $size ) {
			$to_insert['resize'] = $size;
		}
		$ewwwdb->insert( $ewwwdb->ewwwio_images, $to_insert );
		ewwwio_debug_message( "inserted pending record for $path - {$ewwwdb->insert_id}" );
		return $ewwwdb->insert_id;
	}
}


/**
 * Finds the path of a file from the ewwwio_images table.
 *
 * @param int $id The db record to retrieve for the file path.
 * @return string The full file path from the db.
 */
function ewww_image_optimizer_find_file_by_id( $id ) {
	if ( ! $id ) {
		return false;
	}
	ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	$id   = (int) $id;
	$file = $ewwwdb->get_var( $ewwwdb->prepare( "SELECT path FROM $ewwwdb->ewwwio_images WHERE id = %d", $id ) );
	if ( is_null( $file ) ) {
		return false;
	}
	$file = ewww_image_optimizer_absolutize_path( $file );
	ewwwio_debug_message( "found $file by id" );
	if ( ewwwio_is_file( $file ) ) {
		return $file;
	}
	return false;
}

/**
 * Inserts multiple records into the table at once.
 *
 * Each sub-array in $data should have the same number of items as $format.
 *
 * @global object $wpdb
 * @global object $ewwwdb A clone of $wpdb unless it is lacking utf8 connectivity.
 *
 * @param string $table The table to insert records into.
 * @param array  $data Can be any multi-dimensional array with records to insert.
 * @param array  $format A list of formats for the values in each record of $data.
 */
function ewww_image_optimizer_mass_insert( $table, $data, $format ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $table ) || ! ewww_image_optimizer_iterable( $data ) || ! ewww_image_optimizer_iterable( $format ) ) {
		return false;
	}
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}

	$multi_formats = array();
	$values        = array();
	foreach ( $data as $record ) {
		if ( ! ewww_image_optimizer_iterable( $record ) ) {
			continue;
		}

		foreach ( $record as $value ) {
			$values[] = $value;
		}
		$multi_formats[] = '(' . implode( ',', $format ) . ')';
	}
	$first         = reset( $data );
	$fields        = '`' . implode( '`, `', array_keys( $first ) ) . '`';
	$multi_formats = implode( ',', $multi_formats );

	return $ewwwdb->query( $ewwwdb->prepare( "INSERT INTO `$table` ($fields) VALUES $multi_formats", $values ) );
}

/**
 * Search the database to see if we've done this image before.
 *
 * @global object $wpdb
 *
 * @param string $file The filename of the image.
 * @param int    $orig_size The current filesize of the image.
 * @return string The image results from the table, if found.
 */
function ewww_image_optimizer_check_table( $file, $orig_size ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_debug_message( "checking for $file with size: $orig_size" );
	global $ewww_image;
	global $ewww_force_smart;
	$image = array();
	if ( ! is_object( $ewww_image ) || ! $ewww_image instanceof EWWW_Image || $ewww_image->file !== $file ) {
		$ewww_image = new EWWW_Image( 0, '', $file );
	}
	if ( ! empty( $ewww_image->record ) ) {
		$image = $ewww_image->record;
	} else {
		$image = false;
	}
	if ( is_array( $image ) && (int) $image['image_size'] === (int) $orig_size ) {
		$prev_string = ' - ' . __( 'Previously Optimized', 'ewww-image-optimizer' );
		if ( preg_match( '/' . __( 'License exceeded', 'ewww-image-optimizer' ) . '/', $image['results'] ) ) {
			return '';
		}
		$already_optimized = preg_replace( "/$prev_string/", '', $image['results'] );
		$already_optimized = $already_optimized . $prev_string;
		ewwwio_debug_message( "already optimized: {$image['path']} - $already_optimized" );
		ewwwio_memory( __FUNCTION__ );
		// Make sure the image isn't pending.
		if ( $image['pending'] && empty( $ewww_force_smart ) ) {
			global $wpdb;
			$wpdb->update(
				$wpdb->ewwwio_images,
				array(
					'pending' => 0,
				),
				array(
					'id' => $image['id'],
				)
			);
		}
		return $already_optimized;
	} elseif ( is_array( $image ) && ! empty( $image['updates'] ) && $image['updates'] > 5 ) {
		ewwwio_debug_message( "prevented excessive re-opt: {$image['path']}" );
		// Make sure the image isn't pending.
		if ( $image['pending'] ) {
			global $wpdb;
			$wpdb->update(
				$wpdb->ewwwio_images,
				array(
					'pending' => 0,
				),
				array(
					'id' => $image['id'],
				)
			);
		}
		return __( 'Re-optimize prevented', 'ewww-image-optimizer' );
	}
	return '';
}

/**
 * Updates the savings statistics cache.
 *
 * @param int $opt_size The new size of the image.
 * @param int $orig_size The original size of the image.
 */
function ewww_image_optimizer_update_savings( $opt_size, $orig_size ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! $opt_size || ! $orig_size ) {
		return;
	}
	$cache_savings = get_transient( 'ewww_image_optimizer_savings' );
	if ( ! empty( $cache_savings ) && is_array( $cache_savings ) && 2 === count( $cache_savings ) ) {
		$total_opt  = (int) $cache_savings[0];
		$total_orig = (int) $cache_savings[1];
		ewwwio_debug_message( "increasing $total_opt by $opt_size" );
		$total_opt += (int) $opt_size;
		ewwwio_debug_message( "increasing $total_orig by $orig_size" );
		$total_orig += (int) $orig_size;
		set_transient( 'ewww_image_optimizer_savings', array( $total_opt, $total_orig ), DAY_IN_SECONDS );
	}
	if ( is_multisite() ) {
		$cache_savings = get_site_transient( 'ewww_image_optimizer_savings' );
		if ( ! empty( $cache_savings ) && is_array( $cache_savings ) && 2 === count( $cache_savings ) ) {
			$total_opt  = (int) $cache_savings[0];
			$total_orig = (int) $cache_savings[1];
			ewwwio_debug_message( "increasing $total_opt by $opt_size" );
			$total_opt += (int) $opt_size;
			ewwwio_debug_message( "increasing $total_orig by $orig_size" );
			$total_orig += (int) $orig_size;
			set_site_transient( 'ewww_image_optimizer_savings', array( $total_opt, $total_orig ), DAY_IN_SECONDS );
		}
	}
}

/**
 * Inserts or updates an image record in the database.
 *
 * @global object $wpdb
 * @global object $ewwwdb A clone of $wpdb unless it is lacking utf8 connectivity.
 * @global object $ewww_image Contains more information about the image currently being processed.
 *
 * @param string $attachment The filename of the image.
 * @param int    $opt_size The new size of the image.
 * @param int    $orig_size The original size of the image.
 * @param string $original Optional. The name of the file before it was converted. Default ''.
 * @param string $backup_hash Optional. A unique identifier for this file. Default ''.
 */
function ewww_image_optimizer_update_table( $attachment, $opt_size, $orig_size, $original = '', $backup_hash = '' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	global $ewww_image;
	// First check if the image was converted, so we don't orphan records.
	if ( $original && $original !== $attachment ) {
		$already_optimized = ewww_image_optimizer_find_already_optimized( $original );
		$converted         = ewww_image_optimizer_relativize_path( $original );
	} else {
		$already_optimized = ewww_image_optimizer_find_already_optimized( $attachment );
		if ( is_array( $already_optimized ) && ! empty( $already_optimized['converted'] ) ) {
			$converted = $already_optimized['converted'];
		} else {
			$converted = '';
		}
	}
	if ( is_array( $already_optimized ) && ! empty( $already_optimized['updates'] ) && $opt_size >= $orig_size ) {
		$prev_string = ' - ' . __( 'Previously Optimized', 'ewww-image-optimizer' );
	} else {
		$prev_string = '';
	}
	if ( is_array( $already_optimized ) && ! empty( $already_optimized['orig_size'] ) && $already_optimized['orig_size'] > $orig_size ) {
		$orig_size = $already_optimized['orig_size'];
	}
	ewwwio_debug_message( "savings: $opt_size (new) vs. $orig_size (orig)" );
	// Calculate how much space was saved.
	$results_msg = ewww_image_optimizer_image_results( $orig_size, $opt_size, $prev_string );

	$updates = array(
		'path'       => ewww_image_optimizer_relativize_path( $attachment ),
		'converted'  => $converted,
		'level'      => 0,
		'image_size' => $opt_size,
		'results'    => $results_msg,
		'updates'    => 1,
		'backup'     => preg_replace( '/[^\w]/', '', $backup_hash ),
	);
	if ( ! seems_utf8( $updates['path'] ) ) {
		$updates['path'] = utf8_encode( $updates['path'] );
	}
	// Store info on the current image for future reference.
	if ( empty( $already_optimized ) || ! is_array( $already_optimized ) ) {
		ewwwio_debug_message( "creating new record, path: $attachment, size: $opt_size" );
		ewww_image_optimizer_update_savings( $opt_size, $orig_size );
		if ( is_object( $ewww_image ) && $ewww_image instanceof EWWW_Image && $ewww_image->gallery ) {
			$updates['gallery'] = $ewww_image->gallery;
		}
		if ( is_object( $ewww_image ) && $ewww_image instanceof EWWW_Image && $ewww_image->attachment_id ) {
			$updates['attachment_id'] = $ewww_image->attachment_id;
		}
		if ( is_object( $ewww_image ) && $ewww_image instanceof EWWW_Image && $ewww_image->resize ) {
			$updates['resize'] = $ewww_image->resize;
		}
		if ( is_object( $ewww_image ) && $ewww_image instanceof EWWW_Image && $ewww_image->level ) {
			$updates['level'] = $ewww_image->level;
		}
		$updates['orig_size'] = $orig_size;
		/* $updates['updated']   = gmdate( 'Y-m-d H:i:s' ); */
		$ewwwdb->insert( $ewwwdb->ewwwio_images, $updates );
	} else {
		if ( is_array( $already_optimized ) && empty( $already_optimized['orig_size'] ) ) {
			$updates['orig_size'] = $orig_size;
		}
		ewwwio_debug_message( "updating existing record ({$already_optimized['id']}), path: $attachment, size: $opt_size" );
		if ( $already_optimized['updates'] && apply_filters( 'ewww_image_optimizer_allowed_reopt', false ) ) {
			$updates['updates'] = $already_optimized['updates'];
		} elseif ( $already_optimized['updates'] ) {
			$updates['updates'] = $already_optimized['updates'] + 1;
		}
		$updates['pending'] = 0;
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && $already_optimized['updates'] > 1 ) {
			$updates['trace'] = ewwwio_debug_backtrace();
		}
		if ( empty( $already_optimized['gallery'] ) && is_object( $ewww_image ) && $ewww_image instanceof EWWW_Image && $ewww_image->gallery ) {
			$updates['gallery'] = $ewww_image->gallery;
		}
		if ( empty( $already_optimized['attachment_id'] ) && is_object( $ewww_image ) && $ewww_image instanceof EWWW_Image && $ewww_image->attachment_id ) {
			$updates['attachment_id'] = $ewww_image->attachment_id;
		}
		if ( empty( $already_optimized['resize'] ) && is_object( $ewww_image ) && $ewww_image instanceof EWWW_Image && $ewww_image->resize ) {
			$updates['resize'] = $ewww_image->resize;
		}
		if ( is_object( $ewww_image ) && $ewww_image instanceof EWWW_Image && $ewww_image->level ) {
			$updates['level'] = $ewww_image->level;
		}
		if ( ewww_image_optimizer_function_exists( 'print_r' ) ) {
			ewwwio_debug_message( print_r( $updates, true ) );
		}
		// Update information for the image.
		$record_updated = $ewwwdb->update(
			$ewwwdb->ewwwio_images,
			$updates,
			array(
				'id' => $already_optimized['id'],
			)
		);
		if ( false === $record_updated ) {
			if ( ewww_image_optimizer_function_exists( 'print_r' ) ) {
				ewwwio_debug_message( 'db error: ' . print_r( $wpdb->last_error, true ) );
			}
		} else {
			ewwwio_debug_message( "updated $record_updated records successfully" );
		}
	} // End if().
	ewwwio_memory( __FUNCTION__ );
	$ewwwdb->flush();
	ewwwio_memory( __FUNCTION__ );
	return $results_msg;
}

/**
 * Creates a human-readable message based on the original and optimized sizes.
 *
 * @param int    $orig_size The original size of the image.
 * @param int    $opt_size The new size of the image.
 * @param string $prev_string Optional. A message to append for previously optimized images.
 * @return string A message with the percentage and size savings.
 */
function ewww_image_optimizer_image_results( $orig_size, $opt_size, $prev_string = '' ) {
	if ( $opt_size >= $orig_size ) {
		ewwwio_debug_message( 'original and new file are same size (or something weird made the new one larger), no savings' );
		$results_msg = __( 'No savings', 'ewww-image-optimizer' );
	} else {
		// Calculate how much space was saved.
		$savings     = intval( $orig_size ) - intval( $opt_size );
		$savings_str = ewww_image_optimizer_size_format( $savings );
		// Determine the percentage savings.
		$percent = number_format_i18n( 100 - ( 100 * ( $opt_size / $orig_size ) ), 1 ) . '%';
		// Use the percentage and the savings size to output a nice message to the user.
		$results_msg = sprintf(
			/* translators: 1: Size of savings in bytes, kb, mb 2: Percentage savings */
			__( 'Reduced by %1$s (%2$s)', 'ewww-image-optimizer' ),
			$percent,
			$savings_str
		) . $prev_string;
		ewwwio_debug_message( "original and new file are different size: $results_msg" );
	}
	return $results_msg;
}

/**
 * Wrapper around size_format to remove the decimal from sizes in bytes.
 *
 * @param int $size A filesize in bytes.
 * @param int $precision Number of places after the decimal separator.
 * @return string Human-readable filesize.
 */
function ewww_image_optimizer_size_format( $size, $precision = 1 ) {
		// Convert it to human readable format.
		$size_str = size_format( $size, $precision );
		// Remove spaces and extra decimals when measurement is in bytes.
		return preg_replace( '/\.0+ B ?/', ' B', $size_str );
}

/**
 * Called to process each image during scheduled optimization.
 *
 * @global object $wpdb
 * @global object $ewwwdb A clone of $wpdb unless it is lacking utf8 connectivity.
 * @global bool   $ewww_defer Set to false to avoid deferring image optimization.
 * @global string $eio_debug Contains in-memory debug log.
 * @global object $ewww_image Contains more information about the image currently being processed.
 *
 * @param array $attachment {
 *     Optional. The file to optimize. Default null.
 *
 *     @type int $id The id number in the ewwwio_images table.
 *     @type string $path The filename of the image.
 * }
 * @param bool  $auto Optional. True if scheduled optimization is running. Default false.
 * @param bool  $cli Optional. True if WP-CLI is running. Default false.
 * @return string When called from WP-CLI, a message with compression results.
 */
function ewww_image_optimizer_aux_images_loop( $attachment = null, $auto = false, $cli = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	global $ewww_defer;
	$ewww_defer = false;
	$output     = array();
	// Verify that an authorized user has started the optimizer.
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! $auto && ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) ) {
		$output['error'] = esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' );
		wp_die( wp_json_encode( $output ) );
	}
	session_write_close();
	if ( ! empty( $_REQUEST['ewww_wpnonce'] ) ) {
		// Find out if our nonce is on it's last leg/tick.
		$tick = wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-bulk' );
		if ( 2 === $tick ) {
			ewwwio_debug_message( 'nonce on its last leg' );
			$output['new_nonce'] = wp_create_nonce( 'ewww-image-optimizer-bulk' );
		} else {
			ewwwio_debug_message( 'nonce still alive and kicking' );
			$output['new_nonce'] = '';
		}
	}
	// Retrieve the time when the optimizer starts.
	$started = microtime( true );
	if ( ewww_image_optimizer_stl_check() && ewww_image_optimizer_function_exists( 'ini_get' ) && ini_get( 'max_execution_time' ) < 60 ) {
		set_time_limit( 0 );
	}
	// Get the next image in the queue.
	if ( empty( $attachment ) ) {
		list( $id, $attachment ) = $ewwwdb->get_row( "SELECT id,path FROM $ewwwdb->ewwwio_images WHERE pending=1 LIMIT 1", ARRAY_N );
	} else {
		$id         = $attachment['id'];
		$attachment = $attachment['path'];
	}
	if ( $attachment ) {
		$attachment = ewww_image_optimizer_absolutize_path( $attachment );
	}
	global $ewww_image;
	$ewww_image = new EWWW_Image( 0, '', $attachment );
	// Resize the image, if possible.
	if ( empty( $ewww_image->resize ) && ewww_image_optimizer_should_resize_other_image( $ewww_image->file ) ) {
		$new_dimensions = ewww_image_optimizer_resize_upload( $attachment );
	}

	// Do the optimization for the current image.
	$results = ewww_image_optimizer( $attachment );
	if ( ! $results[0] && is_numeric( $id ) ) {
		$ewwwdb->delete(
			$ewwwdb->ewwwio_images,
			array(
				'id' => $id,
			),
			array(
				'%d',
			)
		);
	}
	if ( 'exceeded' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
		if ( ! $auto ) {
			$output['error'] = '<a href="https://ewww.io/buy-credits/" target="_blank">' . esc_html__( 'License Exceeded', 'ewww-image-optimizer' ) . '</a>';
			echo wp_json_encode( $output );
		}
		if ( $cli ) {
			WP_CLI::error( __( 'License Exceeded', 'ewww-image-optimizer' ) );
		}
		die();
	}
	if ( 'exceeded quota' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
		if ( ! $auto ) {
			$output['error'] = '<a href="https://docs.ewww.io/article/101-soft-quotas-on-unlimited-plans" data-beacon-article="608ddf128996210f18bd95d3" target="_blank">' . esc_html__( 'Soft quota reached, contact us for more', 'ewww-image-optimizer' ) . '</a>';
			echo wp_json_encode( $output );
		}
		if ( $cli ) {
			WP_CLI::error( __( 'Soft quota reached, contact us for more', 'ewww-image-optimizer' ) );
		}
		die();
	}
	if ( ! $auto ) {
		// Output the path.
		$output['results'] = '<p>' . esc_html__( 'Optimized', 'ewww-image-optimizer' ) . ' <strong>' . esc_html( $attachment ) . '</strong><br>';
		// Tell the user what the results were for the original image.
		$output['results'] .= $results[1] . '<br>';
		// Calculate how much time has elapsed since we started.
		$elapsed = microtime( true ) - $started;
		// Output how much time has elapsed since we started.
		$output['results'] .= sprintf(
			esc_html(
				/* translators: %s: number of seconds */
				_n( 'Elapsed: %s second', 'Elapsed: %s seconds', $elapsed, 'ewww-image-optimizer' )
			) . '</p>',
			number_format_i18n( $elapsed, 2 )
		);
		if ( get_site_option( 'ewww_image_optimizer_debug' ) ) {
			global $eio_debug;
			$output['results'] .= '<div style="background-color:#f1f1f1;">' . $eio_debug . '</div>';
		}
		$next_file = ewww_image_optimizer_absolutize_path( $wpdb->get_var( "SELECT path FROM $wpdb->ewwwio_images WHERE pending=1 LIMIT 1" ) );
		if ( ! empty( $next_file ) ) {
			$loading_image       = plugins_url( '/images/wpspin.gif', __FILE__ );
			$output['next_file'] = '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . ' <b>' . esc_html( $next_file ) . "</b>&nbsp;<img src='$loading_image' alt='loading' /></p>";
		}
		die( wp_json_encode( $output ) );
	}
	if ( $cli ) {
		return $results[1];
	}
	ewww_image_optimizer_debug_log();
	ewwwio_memory( __FUNCTION__ );
}

/**
 * Look for the retina version of a file.
 *
 * @param string $orig_path Filename of the image.
 * @param bool   $validate_file True verifies the file exists.
 * @return string The retina/hidpi file, or nothing.
 */
function ewww_image_optimizer_get_hidpi_path( $orig_path, $validate_file = true ) {
	$hidpi_suffix = apply_filters( 'ewww_image_optimizer_hidpi_suffix', '@2x' );
	$pathinfo     = pathinfo( $orig_path );
	if ( empty( $pathinfo['dirname'] ) || empty( $pathinfo['filename'] ) || empty( $pathinfo['extension'] ) ) {
		return '';
	}
	$hidpi_path = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . $hidpi_suffix . '.' . $pathinfo['extension'];
	if ( $validate_file && ! ewwwio_is_file( $hidpi_path ) ) {
		return '';
	}
	ewwwio_debug_message( "found retina at $hidpi_path" );
	return $hidpi_path;
}

/**
 * Looks for a retina version of the original file so that we can optimize that too.
 *
 * @global object $ewww_image Contains more information about the image currently being processed.
 *
 * @param string $orig_path Filename of the 'normal' image.
 * @return string The filename of the retina image.
 */
function ewww_image_optimizer_hidpi_optimize( $orig_path ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$hidpi_path = ewww_image_optimizer_get_hidpi_path( $orig_path );
	if ( ! $hidpi_path ) {
		return;
	}
	global $ewww_image;
	if ( is_object( $ewww_image ) && $ewww_image instanceof EWWW_Image ) {
		$id      = $ewww_image->attachment_id;
		$gallery = 'media';
		$size    = $ewww_image->resize;
	} else {
		$id      = 0;
		$gallery = '';
		$size    = null;
	}
	$ewww_image         = new EWWW_Image( $id, $gallery, $hidpi_path );
	$ewww_image->resize = $size . '-retina';
	ewww_image_optimizer( $hidpi_path );
}

/**
 * Checks the existence of a cloud storage stream wrapper.
 *
 * @return bool True if a supported stream wrapper is found, false otherwise.
 */
function ewww_image_optimizer_stream_wrapper_exists() {
	$wrappers = stream_get_wrappers();
	if ( ! ewww_image_optimizer_iterable( $wrappers ) ) {
		return false;
	}
	foreach ( $wrappers as $wrapper ) {
		if ( strpos( $wrapper, 's3' ) === 0 ) {
			return true;
		}
		if ( strpos( $wrapper, 'gs' ) === 0 ) {
			return true;
		}
	}
	return false;
}

/**
 * Checks the filename for an S3 or GCS stream wrapper.
 *
 * @param string $filename The filename to be searched.
 * @return bool True if a stream wrapper is found, false otherwise.
 */
function ewww_image_optimizer_stream_wrapped( $filename ) {
	if ( false !== strpos( $filename, '://' ) ) {
		if ( strpos( $filename, 's3' ) === 0 ) {
			return true;
		}
		if ( strpos( $filename, 'gs' ) === 0 ) {
			return true;
		}
	}
	return false;
}

/**
 * Pushes images from local storage back to S3 after optimization.
 *
 * @param array $meta The attachment metadata.
 * @param int   $id The attachment ID number.
 * @return array $meta Send the metadata back from whence it came.
 */
function ewww_image_optimizer_remote_push( $meta, $id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ( class_exists( 'S3_Uploads' ) || class_exists( 'S3_Uploads\Plugin' ) ) && ! empty( $meta['file'] ) ) {
		$s3_upload_dir = wp_get_upload_dir();
		$s3_upload_dir = trailingslashit( $s3_upload_dir['basedir'] );
		$s3_path       = get_attached_file( $id );
		if ( false === strpos( $s3_path, $s3_upload_dir ) ) {
			ewwwio_debug_message( "$s3_path not in $s3_upload_dir" );
			return $meta;
		}
		$upload_path = trailingslashit( WP_CONTENT_DIR ) . 'uploads/';
		$filename    = realpath( str_replace( $s3_upload_dir, $upload_path, $s3_path ) );
		ewwwio_debug_message( "S3 Uploads fullsize path: $s3_path" );
		ewwwio_debug_message( "unfiltered fullsize path: $filename" );
		if ( 0 === strpos( $s3_path, 's3://' ) && 0 === strpos( $filename, '/' ) && ewwwio_is_file( $filename ) ) {
			copy( $filename, $s3_path );
			unlink( $filename );
			if ( ewwwio_is_file( $filename . '.webp' ) ) {
				copy( $filename . '.webp', $s3_path . '.webp' );
				unlink( $filename . '.webp' );
			}
		}
		// Original image detected.
		if ( isset( $meta['original_image'] ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_originals' ) ) {
			// original_image doesn't contain a path, so we calculate one.
			$base_dir = trailingslashit( dirname( $filename ) );
			$base_s3  = trailingslashit( dirname( $s3_path ) );
			// Build the paths for an original pre-scaled image.
			$resize_path = $base_dir . wp_basename( $meta['original_image'] );
			$s3_rpath    = $base_s3 . wp_basename( $meta['original_image'] );
			ewwwio_debug_message( "pushing $resize_path to $s3_rpath" );
			if ( 0 === strpos( $s3_rpath, 's3://' ) && 0 === strpos( $resize_path, '/' ) && ewwwio_is_file( $resize_path ) ) {
				copy( $resize_path, $s3_rpath );
				unlink( $resize_path );
				if ( ewwwio_is_file( $resize_path . '.webp' ) ) {
					copy( $resize_path . '.webp', $s3_rpath . '.webp' );
					unlink( $resize_path . '.webp' );
				}
			}
		}
		// Resized versions, so we'll grab those too.
		if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
			$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt', false, true );
			ewwwio_debug_message( 'retrieving resizes' );
			// Meta sizes don't contain a path, so we calculate one.
			$base_dir = trailingslashit( dirname( $filename ) );
			$base_s3  = trailingslashit( dirname( $s3_path ) );
			foreach ( $meta['sizes'] as $size => $data ) {
				ewwwio_debug_message( "processing size: $size" );
				if ( preg_match( '/webp/', $size ) ) {
					continue;
				}
				if ( ! empty( $disabled_sizes[ $size ] ) ) {
					continue;
				}
				if ( empty( $data['file'] ) ) {
					continue;
				}
				$resize_path = $base_dir . wp_basename( $data['file'] );
				$s3_rpath    = $base_s3 . wp_basename( $data['file'] );
				if ( ! ewwwio_is_file( $resize_path ) ) {
					ewwwio_debug_message( "$resize_path does not exist" );
					continue;
				}
				ewwwio_debug_message( "pushing $resize_path to $s3_rpath" );
				if ( 0 === strpos( $s3_rpath, 's3://' ) && 0 === strpos( $resize_path, '/' ) ) {
					copy( $resize_path, $s3_rpath );
					unlink( $resize_path );
					if ( ewwwio_is_file( $resize_path . '.webp' ) ) {
						copy( $resize_path . '.webp', $s3_rpath . '.webp' );
						unlink( $resize_path . '.webp' );
					}
				}
			}
		} // End if().
	} // End if().
	return $meta;
}

/**
 * Fetches images from S3 or Azure storage so that they can be optimized locally.
 *
 * @global object $as3cf
 *
 * @param int   $id The attachment ID number.
 * @param array $meta The attachment metadata.
 * @return string|bool The filename of the image fetched, false on failure.
 */
function ewww_image_optimizer_remote_fetch( $id, $meta ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! function_exists( 'download_url' ) ) {
		require_once( ABSPATH . '/wp-admin/includes/file.php' );
	}
	$filename = false;
	if ( ( class_exists( 'S3_Uploads' ) || class_exists( 'S3_Uploads\Plugin' ) ) && ! empty( $meta['file'] ) ) {
		$s3_upload_dir = wp_get_upload_dir();
		$s3_upload_dir = trailingslashit( $s3_upload_dir['basedir'] );
		$s3_path       = get_attached_file( $id );
		if ( false === strpos( $s3_path, $s3_upload_dir ) ) {
			ewwwio_debug_message( "$s3_path not in $s3_upload_dir" );
			return false;
		}
		$upload_path = trailingslashit( WP_CONTENT_DIR ) . 'uploads/';
		$filename    = str_replace( $s3_upload_dir, $upload_path, $s3_path );
		if ( false === strpos( $filename, WP_CONTENT_DIR ) ) {
			ewwwio_debug_message( "$filename not in WP_CONTENT_DIR" );
			return false;
		}
		ewwwio_debug_message( "S3 Uploads fullsize path: $s3_path" );
		ewwwio_debug_message( "unfiltered fullsize path: $filename" );
		if ( is_dir( $upload_path ) && ! is_writable( $upload_path ) ) {
			return false;
		} elseif ( ! is_dir( $upload_path ) && ! is_writable( WP_CONTENT_DIR ) ) {
			return false;
		}
		if ( ! is_dir( dirname( $filename ) ) ) {
			wp_mkdir_p( dirname( $filename ) );
		}
		if ( 0 === strpos( $s3_path, 's3://' ) && 0 === strpos( $filename, '/' ) && ! ewwwio_is_file( $filename ) ) {
			copy( $s3_path, $filename );
		}
		// Original image detected.
		if ( isset( $meta['original_image'] ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_originals' ) ) {
			ewwwio_debug_message( 'processing original_image' );
			// original_image doesn't contain a path, so we calculate one.
			$base_dir = trailingslashit( dirname( $filename ) );
			$base_s3  = trailingslashit( dirname( $s3_path ) );
			// Build the paths for an original pre-scaled image.
			$resize_path = $base_dir . wp_basename( $meta['original_image'] );
			$s3_rpath    = $base_s3 . wp_basename( $meta['original_image'] );
			ewwwio_debug_message( "fetching $s3_rpath to $resize_path" );
			if ( 0 === strpos( $s3_rpath, 's3://' ) && 0 === strpos( $resize_path, '/' ) ) {
				copy( $s3_rpath, $resize_path );
			}
		}
		// Resized versions, so we'll grab those too.
		if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
			$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt', false, true );
			ewwwio_debug_message( 'retrieving resizes' );
			// Meta sizes don't contain a path, so we calculate one.
			$base_dir = trailingslashit( dirname( $filename ) );
			$base_s3  = trailingslashit( dirname( $s3_path ) );
			foreach ( $meta['sizes'] as $size => $data ) {
				ewwwio_debug_message( "processing size: $size" );
				if ( preg_match( '/webp/', $size ) ) {
					continue;
				}
				if ( ! empty( $disabled_sizes[ $size ] ) ) {
					continue;
				}
				if ( empty( $data['file'] ) ) {
					continue;
				}
				$resize_path = $base_dir . wp_basename( $data['file'] );
				$s3_rpath    = $base_s3 . wp_basename( $data['file'] );
				if ( ewwwio_is_file( $resize_path ) ) {
					ewwwio_debug_message( "$resize_path already exists" );
					continue;
				}
				ewwwio_debug_message( "fetching $s3_rpath to $resize_path" );
				if ( 0 === strpos( $s3_rpath, 's3://' ) && 0 === strpos( $resize_path, '/' ) ) {
					copy( $s3_rpath, $resize_path );
				}
			}
		} // End if().
	} // End if().
	if ( class_exists( 'wpCloud\StatelessMedia\EWWW' ) && ! empty( $meta['gs_link'] ) ) {
		$full_url = $meta['gs_link'];
		$filename = get_attached_file( $id, true );
		ewwwio_debug_message( "GSC (stateless) fullsize url: $full_url" );
		ewwwio_debug_message( "unfiltered fullsize path: $filename" );
		$temp_file = download_url( $full_url );
		if ( ! is_wp_error( $temp_file ) ) {
			if ( ! is_dir( dirname( $filename ) ) ) {
				wp_mkdir_p( dirname( $filename ) );
			}
			if ( ! ewwwio_is_file( $filename ) ) {
				ewwwio_rename( $temp_file, $filename );
			} else {
				unlink( $temp_file );
			}
		} elseif ( is_wp_error( $temp_file ) ) {
			ewwwio_debug_message( 'could not download: ' . $temp_file->get_error_message() );
		}
		// Resized versions, so we'll grab those too.
		if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
			$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt', false, true );
			ewwwio_debug_message( 'retrieving resizes' );
			// Meta sizes don't contain a path, so we calculate one.
			$base_dir  = trailingslashit( dirname( $filename ) );
			$processed = array();
			foreach ( $meta['sizes'] as $size => $data ) {
				ewwwio_debug_message( "processing size: $size" );
				if ( preg_match( '/webp/', $size ) ) {
					continue;
				}
				if ( ! empty( $disabled_sizes[ $size ] ) ) {
					continue;
				}
				if ( empty( $data['file'] ) ) {
					continue;
				}
				$dup_size = false;
				// Check through all the sizes we've processed so far.
				foreach ( $processed as $proc => $scan ) {
					// If a previous resize had identical dimensions.
					if ( $scan['height'] === $data['height'] && $scan['width'] === $data['width'] ) {
						// Found a duplicate resize.
						$dup_size = true;
					}
				}
				// If this is a unique size.
				if ( ! $dup_size ) {
					$resize_path = $base_dir . wp_basename( $data['file'] );
					$resize_url  = $data['gs_link'];
					if ( ewwwio_is_file( $resize_path ) ) {
						continue;
					}
					ewwwio_debug_message( "fetching $resize_url to $resize_path" );
					$temp_file = download_url( $resize_url );
					if ( ! is_wp_error( $temp_file ) ) {
						if ( ! is_dir( dirname( $resize_path ) ) ) {
							wp_mkdir_p( dirname( $resize_path ) );
						}
						ewwwio_rename( $temp_file, $resize_path );
					}
				}
				// Store info on the sizes we've processed, so we can check the list for duplicate sizes.
				$processed[ $size ]['width']  = $data['width'];
				$processed[ $size ]['height'] = $data['height'];
			}
		} // End if().
	} // End if().
	if ( class_exists( 'Amazon_S3_And_CloudFront' ) ) {
		global $as3cf;
		$full_url = get_attached_file( $id );
		if ( ewww_image_optimizer_stream_wrapped( $full_url ) ) {
			$full_url = $as3cf->get_attachment_url( $id, null, null, $meta );
		}
		$filename = get_attached_file( $id, true );
		ewwwio_debug_message( "amazon s3 fullsize url: $full_url" );
		ewwwio_debug_message( "unfiltered fullsize path: $filename" );
		$temp_file = download_url( $full_url );
		if ( ! is_wp_error( $temp_file ) ) {
			ewwwio_debug_message( "downloaded to $temp_file" );
			if ( ! is_dir( dirname( $filename ) ) ) {
				wp_mkdir_p( dirname( $filename ) );
			}
			if ( ! ewwwio_is_file( $filename ) && is_writable( dirname( $filename ) ) ) {
				ewwwio_debug_message( "renaming $temp_file to $filename" );
				ewwwio_rename( $temp_file, $filename );
			} elseif ( ! is_writable( dirname( $filename ) ) ) {
				ewwwio_debug_message( 'destination dir not writable' );
			} else {
				ewwwio_debug_message( 'file already found, nuking temp file' );
				unlink( $temp_file );
			}
			if ( ! ewwwio_is_file( $filename ) ) {
				ewwwio_debug_message( 'download failed' );
			}
		} elseif ( is_wp_error( $temp_file ) ) {
			ewwwio_debug_message( 'could not download: ' . $temp_file->get_error_message() );
		}
		$base_dir = trailingslashit( dirname( $filename ) );
		// Original image detected.
		if ( isset( $meta['original_image'] ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_originals' ) ) {
			ewwwio_debug_message( 'processing original_image' );
			$base_url = trailingslashit( dirname( $full_url ) );
			// Build the paths for an original pre-scaled image.
			$resize_path = $base_dir . wp_basename( $meta['original_image'] );
			$resize_url  = $base_url . wp_basename( $meta['original_image'] );
			if ( ! ewwwio_is_file( $resize_path ) ) {
				ewwwio_debug_message( "fetching $resize_url to $resize_path" );
				$temp_file = download_url( $resize_url );
				if ( ! is_wp_error( $temp_file ) ) {
					if ( ! is_dir( $base_dir ) ) {
						wp_mkdir_p( $base_dir );
					}
					ewwwio_rename( $temp_file, $resize_path );
				} elseif ( is_wp_error( $temp_file ) ) {
					ewwwio_debug_message( 'could not download: ' . $temp_file->get_error_message() );
				}
			}
		}
		// Resized versions, so we'll grab those too.
		if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
			$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt', false, true );
			ewwwio_debug_message( 'retrieving resizes' );
			// Meta sizes don't contain a path, so we calculate one.
			$processed = array();
			foreach ( $meta['sizes'] as $size => $data ) {
				ewwwio_debug_message( "processing size: $size" );
				if ( preg_match( '/webp/', $size ) ) {
					continue;
				}
				if ( ! empty( $disabled_sizes[ $size ] ) ) {
					continue;
				}
				if ( empty( $data['file'] ) ) {
					continue;
				}
				$dup_size = false;
				// Check through all the sizes we've processed so far.
				foreach ( $processed as $proc => $scan ) {
					// If a previous resize had identical dimensions.
					if ( $scan['height'] === $data['height'] && $scan['width'] === $data['width'] ) {
						// Found a duplicate resize.
						$dup_size = true;
					}
				}
				// If this is a unique size.
				if ( ! $dup_size ) {
					$resize_path = $base_dir . wp_basename( $data['file'] );
					$resize_url  = $as3cf->get_attachment_url( $id, null, $size, $meta );
					if ( ewwwio_is_file( $resize_path ) ) {
						continue;
					}
					ewwwio_debug_message( "fetching $resize_url to $resize_path" );
					$temp_file = download_url( $resize_url );
					if ( ! is_wp_error( $temp_file ) ) {
						if ( ! is_dir( dirname( $resize_path ) ) ) {
							wp_mkdir_p( dirname( $resize_path ) );
						}
						ewwwio_rename( $temp_file, $resize_path );
					}
				}
				// Store info on the sizes we've processed, so we can check the list for duplicate sizes.
				$processed[ $size ]['width']  = $data['width'];
				$processed[ $size ]['height'] = $data['height'];
			}
		} // End if().
	} // End if().
	if ( class_exists( 'WindowsAzureStorageUtil' ) && get_option( 'azure_storage_use_for_default_upload' ) ) {
		$full_url = $meta['url'];
		$filename = $meta['file'];
		$filename = get_attached_file( $id, true );
		ewwwio_debug_message( "azure fullsize url: $full_url" );
		ewwwio_debug_message( "fullsize path: $filename" );
		$temp_file = download_url( $full_url );
		if ( ! is_wp_error( $temp_file ) ) {
			if ( ! is_dir( dirname( $filename ) ) ) {
				wp_mkdir_p( dirname( $filename ) );
			}
			if ( ! ewwwio_is_file( $filename ) ) {
				ewwwio_rename( $temp_file, $filename );
			} else {
				unlink( $temp_file );
			}
		}
		$base_dir = trailingslashit( dirname( $filename ) );
		$base_url = trailingslashit( dirname( $full_url ) );
		// Original image detected.
		if ( isset( $meta['original_image'] ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_originals' ) ) {
			ewwwio_debug_message( 'processing original_image' );
			// Build the paths for an original pre-scaled image.
			$resize_path = $base_dir . wp_basename( $meta['original_image'] );
			$resize_url  = $base_url . wp_basename( $meta['original_image'] );
			if ( ! ewwwio_is_file( $resize_path ) ) {
				ewwwio_debug_message( "fetching $resize_url to $resize_path" );
				$temp_file = download_url( $resize_url );
				if ( ! is_wp_error( $temp_file ) ) {
					if ( ! is_dir( $base_dir ) ) {
						wp_mkdir_p( $base_dir );
					}
					ewwwio_rename( $temp_file, $resize_path );
				}
			}
		}
		// Resized versions, so we'll grab those too.
		if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
			$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt', false, true );
			ewwwio_debug_message( 'retrieving resizes' );
			// Process each resized version.
			$processed = array();
			foreach ( $meta['sizes'] as $size => $data ) {
				ewwwio_debug_message( "processing size: $size" );
				if ( preg_match( '/webp/', $size ) ) {
					continue;
				}
				if ( ! empty( $disabled_sizes[ $size ] ) ) {
					continue;
				}
				if ( empty( $data['file'] ) ) {
					continue;
				}
				$dup_size = false;
				// Check through all the sizes we've processed so far.
				foreach ( $processed as $proc => $scan ) {
					// If a previous resize had identical dimensions.
					if ( $scan['height'] === $data['height'] && $scan['width'] === $data['width'] ) {
						// Found a duplicate resize.
						$dup_size = true;
					}
				}
				// If this is a unique size.
				if ( ! $dup_size ) {
					$resize_path = $base_dir . wp_basename( $data['file'] );
					$resize_url  = $base_url . wp_basename( $data['file'] );
					if ( ewwwio_is_file( $resize_path ) ) {
						continue;
					}
					ewwwio_debug_message( "fetching $resize_url to $resize_path" );
					$temp_file = download_url( $resize_url );
					if ( ! is_wp_error( $temp_file ) ) {
						if ( ! is_dir( dirname( $resize_path ) ) ) {
							wp_mkdir_p( dirname( $resize_path ) );
						}
						ewwwio_rename( $temp_file, $resize_path );
					}
				}
				// Store info on the sizes we've processed, so we can check the list for duplicate sizes.
				$processed[ $size ]['width']  = $data['width'];
				$processed[ $size ]['height'] = $data['height'];
			}
		} // End if().
	} // End if().
	clearstatcache();
	if ( ! empty( $filename ) && ewwwio_is_file( $filename ) ) {
		ewwwio_debug_message( "$filename found, success!" );
		return $filename;
	} elseif ( ! empty( $filename ) ) {
		ewwwio_debug_message( "$filename not found, boo..." );
	}
	return false;
}

/**
 * Searches the database for a matching s3 path, and fixes it to use the local path.
 *
 * @global object $wpdb
 *
 * @param array  $meta The attachment metadata.
 * @param int    $id The attachment ID number.
 * @param string $s3_path The potential s3:// path.
 */
function ewww_image_optimizer_check_table_as3cf( $meta, $id, $s3_path ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$local_path = get_attached_file( $id, true );
	ewwwio_debug_message( "unfiltered local path: $local_path" );
	if ( $local_path !== $s3_path ) {
		ewww_image_optimizer_update_table_as3cf( $local_path, $s3_path );
	}
	if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
		ewwwio_debug_message( 'updating s3 resizes' );
		// Meta sizes don't contain a path, so we calculate one.
		$local_dir = trailingslashit( dirname( $local_path ) );
		$s3_dir    = trailingslashit( dirname( $s3_path ) );
		$processed = array();
		foreach ( $meta['sizes'] as $size => $data ) {
			if ( strpos( $size, 'webp' ) === 0 ) {
				continue;
			}
			// Check through all the sizes we've processed so far.
			foreach ( $processed as $proc => $scan ) {
				// If a previous resize had identical dimensions.
				if ( $scan['height'] === $data['height'] && $scan['width'] === $data['width'] ) {
					// Found a duplicate resize.
					continue;
				}
			}
			// If this is a unique size.
			$local_resize_path = $local_dir . $data['file'];
			$s3_resize_path    = $s3_dir . $data['file'];
			if ( $local_resize_path !== $s3_resize_path ) {
				ewww_image_optimizer_update_table_as3cf( $local_resize_path, $s3_resize_path );
			}
			// Store info on the sizes we've processed, so we can check the list for duplicate sizes.
			$processed[ $size ]['width']  = $data['width'];
			$processed[ $size ]['height'] = $data['height'];
		}
	}
	global $wpdb;
	$wpdb->flush();
}

/**
 * Given an S3 path, replaces it with the local path.
 *
 * @global object $wpdb
 * @global object $ewwwdb A clone of $wpdb unless it is lacking utf8 connectivity.
 *
 * @param string $local_path The local filesystem path to the image.
 * @param string $s3_path The remote S3 path to the image.
 */
function ewww_image_optimizer_update_table_as3cf( $local_path, $s3_path ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// First we need to see if anything matches the s3 path.
	$s3_image = ewww_image_optimizer_find_already_optimized( $s3_path );
	ewwwio_debug_message( "looking for $s3_path" );
	if ( is_array( $s3_image ) ) {
		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		ewwwio_debug_message( "found $s3_path in db" );
		// When we find a match by the s3 path, we need to find out if there are already records for the local path.
		$found_local_image = ewww_image_optimizer_find_already_optimized( $local_path );
		ewwwio_debug_message( "looking for $local_path" );
		// If we found records for both local and s3 paths, we delete the s3 record, but store the original size in the local record.
		if ( ! empty( $found_local_image ) && is_array( $found_local_image ) ) {
			ewwwio_debug_message( "found $local_path in db" );
			$ewwwdb->delete(
				$ewwwdb->ewwwio_images,
				array(
					'id' => $s3_image['id'],
				),
				array(
					'%d',
				)
			);
			if ( $s3_image['orig_size'] > $found_local_image['orig_size'] ) {
				$ewwwdb->update(
					$ewwwdb->ewwwio_images,
					array(
						'orig_size' => $s3_image['orig_size'],
						'results'   => $s3_image['results'],
					),
					array(
						'id' => $found_local_image['id'],
					)
				);
			}
		} else {
			// If we just found an s3 path and no local match, then we just update the path in the table to the local path.
			ewwwio_debug_message( 'just updating s3 to local' );
			$ewwwdb->update(
				$ewwwdb->ewwwio_images,
				array(
					'path' => ewww_image_optimizer_relativize_path( $local_path ),
				),
				array(
					'id' => $s3_image['id'],
				)
			);
		}
	} // End if().
}

/**
 * Raise the memory limit even higher (to 512M) than WP default of 256M if necessary.
 *
 * @param int|string $memory_limit The amount of memory to allocate.
 * @return int|string The new amount of memory to allocate, if it was only 256M or lower.
 */
function ewww_image_optimizer_raise_memory_limit( $memory_limit ) {
	if ( '256M' === $memory_limit || ( is_int( $memory_limit ) && 270000000 > $memory_limit ) ) {
		ewwwio_debug_message( 'raising the roof' );
		return '512M';
	} else {
		return $memory_limit;
	}
}

/**
 * Uses gifsicle or the API to resize an image.
 *
 * @since 4.4.0
 *
 * @param string $file The file to resize.
 * @param int    $dst_x X-coordinate of destination image (usually 0).
 * @param int    $dst_y Y-coordinate of destination image (usually 0).
 * @param int    $src_x X-coordinate of source image (usually 0 unless cropping).
 * @param int    $src_y Y-coordinate of source image (usually 0 unless cropping).
 * @param int    $dst_w Desired image width.
 * @param int    $dst_h Desired image height.
 * @param int    $src_w Source width.
 * @param int    $src_h Source height.
 * @return string|WP_Error The image contents or the error message.
 */
function ewww_image_optimizer_better_resize( $file, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_debug_message( "resizing to $dst_w and $dst_h" );
	if ( $dst_x || $dst_y ) {
		ewwwio_debug_message( 'cropping too' );
		$crop = true;
	}
	$type = ewww_image_optimizer_mimetype( $file, 'i' );
	if ( false === strpos( $type, 'image' ) ) {
		ewwwio_debug_message( 'not an image, no resizing possible' );
		return new WP_Error( 'invalid_image', __( 'File is not an image.' ), $file );
	}
	if ( 'image/gif' === $type && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && function_exists( 'ewww_image_optimizer_gifsicle_resize' ) ) {
		return ewww_image_optimizer_gifsicle_resize( $file, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h );
	}
	return ewww_image_optimizer_cloud_resize( $file, $type, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h );
}

/**
 * If a JPG image is using the EXIF orientiation, correct the rotation, and reset the Orientation.
 *
 * @param string $file The file to check for rotation.
 */
function ewww_image_optimizer_autorotate( $file ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( defined( 'EWWW_IMAGE_OPTIMIZER_DISABLE_AUTOROTATE' ) && EWWW_IMAGE_OPTIMIZER_DISABLE_AUTOROTATE ) {
		return;
	}
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'image' );
	}
	$type = ewww_image_optimizer_mimetype( $file, 'i' );
	if ( 'image/jpeg' !== $type ) {
		ewwwio_debug_message( 'not a JPG, no rotation needed' );
		return;
	}
	$orientation = (int) ewww_image_optimizer_get_orientation( $file, $type );
	if ( ! $orientation || 1 === $orientation ) {
		return;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) < 20 && function_exists( 'ewww_image_optimizer_jpegtran_autorotate' ) ) {
		// Read the exif, if it fails, we won't autorotate.
		try {
			$jpeg = new PelJpeg( $file );
			$exif = $jpeg->getExif();
		} catch ( PelDataWindowOffsetException $pelerror ) {
			ewwwio_debug_message( 'pel exception: ' . $pelerror->getMessage() );
			$exif = null;
		} catch ( PelDataWindowOffsetException $pelerror ) {
			ewwwio_debug_message( 'pel exception: ' . $pelerror->getMessage() );
			$exif = null;
		} catch ( Exception $pelerror ) {
			ewwwio_debug_message( 'pel exception: ' . $pelerror->getMessage() );
			$exif = null;
		}
		if ( is_null( $exif ) ) {
			ewwwio_debug_message( 'could not work with PelJpeg object, no rotation happening here' );
		} elseif ( ewww_image_optimizer_jpegtran_autorotate( $file, $type, $orientation ) ) {
			// Use PEL to correct the orientation flag when metadata was preserved.
			$jpeg = new PelJpeg( $file );
			$exif = $jpeg->getExif();
			if ( ! is_null( $exif ) ) {
				$tiff        = $exif->getTiff();
				$ifd0        = $tiff->getIfd();
				$orientation = $ifd0->getEntry( PelTag::ORIENTATION );
				if ( ! is_null( $orientation ) ) {
					ewwwio_debug_message( 'orientation being adjusted' );
					$orientation->setValue( 1 );
				}
				$jpeg->saveFile( $file );
			}
		}
		return;
	}
	ewww_image_optimizer_cloud_autorotate( $file, $type );
}

/**
 * If a PNG image is over the threshold, see if we can make it smaller as a JPG.
 *
 * @param string $file The file to check for conversion.
 */
function ewww_image_optimizer_autoconvert( $file ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( defined( 'EWWW_IMAGE_OPTIMIZER_DISABLE_AUTOCONVERT' ) && EWWW_IMAGE_OPTIMIZER_DISABLE_AUTOCONVERT ) {
		return;
	}
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'image' );
	}
	$type = ewww_image_optimizer_mimetype( $file, 'i' );
	if ( 'image/png' !== $type && 'image/gif' !== $type ) {
		ewwwio_debug_message( 'not a PNG, no conversion needed' );
		return;
	}
	$orig_size = ewww_image_optimizer_filesize( $file );
	if ( 'image/png' === $type && $orig_size < apply_filters( 'ewww_image_optimizer_autoconvert_threshold', 250000 ) ) {
		ewwwio_debug_message( 'not a large PNG (size or dimensions), skipping' );
		return;
	}
	if ( 'image/png' === $type && ewww_image_optimizer_png_alpha( $file ) && ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_to_jpg' ) || ! ewww_image_optimizer_jpg_background() ) ) {
		ewwwio_debug_message( 'alpha detected, skipping' );
		return;
	}
	if ( 'image/gif' === $type && ewww_image_optimizer_is_animated( $file ) ) {
		ewwwio_debug_message( 'animation detected, skipping' );
		return;
	}
	$ewww_image = new EWWW_Image( 0, '', $file );
	// Pass the filename, false for db search/replace, and true for filesize comparison.
	return $ewww_image->convert( $file, false, true );
}

/**
 * Skips resizing for any image with 'noresize' in the filename.
 *
 * @param array  $dimensions The configured dimensions for resizing.
 * @param string $filename The filename of the uploaded image.
 * @return array The new dimensions for resizing.
 */
function ewww_image_optimizer_noresize( $dimensions, $filename ) {
	if ( strpos( $filename, 'noresize' ) !== false ) {
		add_filter( 'big_image_size_threshold', '__return_false' );
		return array( 0, 0 );
	}
	$ignore_folders = ewww_image_optimizer_get_option( 'ewww_image_optimizer_exclude_paths' );
	if ( ! ewww_image_optimizer_iterable( $ignore_folders ) ) {
		return $dimensions;
	}
	foreach ( $ignore_folders as $ignore_folder ) {
		if ( strpos( $filename, $ignore_folder ) !== false ) {
			return array( 0, 0 );
		}
	}
	return $dimensions;
}

/**
 * Check non-Media Library filenames to see if the image is eligible for resizing.
 *
 * This function specifically checks to be sure the filename does not preclude an image.
 * It does not check the dimensions, that is done by ewww_image_optimizer_should_resize().
 *
 * @param string $file The image filename.
 * @return bool True to allow resizing, false otherwise.
 */
function ewww_image_optimizer_should_resize_other_image( $file ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_other_existing' ) ) {
		return false;
	}
	$extensions = array(
		'gif',
		'jpg',
		'jpeg',
		'jpe',
		'png',
	);
	if ( preg_match( '#-(\d+)x(\d+)(@2x)?\.(?:' . implode( '|', $extensions ) . ')$#i', $file ) ) {
		return false;
	}
	if ( strpos( $file, '/wp-includes/' ) ) {
		return false;
	}
	if ( strpos( $file, '/wp-admin/' ) ) {
		return false;
	}
	if ( false !== strpos( $file, get_theme_root() ) ) {
		return false;
	}
	if ( false !== strpos( $file, WP_PLUGIN_DIR ) ) {
		return false;
	}
	ewwwio_debug_message( "allowing resize for $file" );
	return true;
}

/**
 * Resizes Media Library uploads based on the maximum dimensions specified by the user.
 *
 * @global object $wpdb
 * @global object $ewwwdb A clone of $wpdb unless it is lacking utf8 connectivity.
 *
 * @param string $file The file to check for rotation.
 * @return array|bool The new height and width, or false if no resizing was done.
 */
function ewww_image_optimizer_resize_upload( $file ) {
	// Parts adapted from Imsanity (THANKS Jason!).
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewww_webp_only;
	global $ewwwio_resize_status;
	$ewwwio_resize_status = '';
	if ( ! $file ) {
		return false;
	}
	if ( ! empty( $ewww_webp_only ) ) {
		return false;
	}
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'image' );
	}
	if (
		! empty( $_REQUEST['post_id'] ) || // phpcs:ignore WordPress.Security.NonceVerification
		( ! empty( $_REQUEST['action'] ) && 'upload-attachment' === $_REQUEST['action'] ) || // phpcs:ignore WordPress.Security.NonceVerification
		strpos( wp_get_referer(), 'media-new.php' )
	) {
		ewwwio_debug_message( 'resizing image from media library or attached to post' );
		$maxwidth  = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' );
		$maxheight = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' );
	} elseif ( strpos( wp_get_referer(), '/post.php' ) ) {
		ewwwio_debug_message( 'resizing image from the post/page editor' );
		$maxwidth  = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' );
		$maxheight = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' );
	} else {
		ewwwio_debug_message( 'resizing images from somewhere else' );
		$maxwidth  = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherwidth' );
		$maxheight = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherheight' );
		if ( ! $maxwidth && ! $maxheight ) {
			ewwwio_debug_message( 'other dimensions not set, overriding with media dimensions' );
			$maxwidth  = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' );
			$maxheight = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' );
		}
	}
	if ( wp_get_referer() ) {
		ewwwio_debug_message( 'uploaded from: ' . wp_get_referer() );
	}

	/**
	 * Filters the dimensions to used for resizing uploaded images.
	 *
	 * @param array $args {
	 *     The dimensions to be used in resizing.
	 *
	 *     @type int $maxwidth The maximum width of the image.
	 *     @type int $maxheight The maximum height of the image.
	 * }
	 * @param string $file The name of the file being resized.
	 */
	list( $maxwidth, $maxheight ) = apply_filters( 'ewww_image_optimizer_resize_dimensions', array( $maxwidth, $maxheight ), $file );

	$maxwidth  = (int) $maxwidth;
	$maxheight = (int) $maxheight;
	// Check that options are not both set to zero.
	if ( 0 === $maxwidth && 0 === $maxheight ) {
		return false;
	}
	$maxwidth  = $maxwidth ? $maxwidth : 999999;
	$maxheight = $maxheight ? $maxheight : 999999;

	if ( ! ewwwio_is_file( $file ) ) {
		return false;
	}
	// Check the file type.
	$type = ewww_image_optimizer_mimetype( $file, 'i' );
	if ( strpos( $type, 'image' ) === false ) {
		ewwwio_debug_message( 'not an image, cannot resize' );
		return false;
	}
	// Check file size (dimensions).
	list( $oldwidth, $oldheight ) = wp_getimagesize( $file );
	if ( $oldwidth <= $maxwidth && $oldheight <= $maxheight ) {
		ewwwio_debug_message( 'image too small for resizing' );
		/* translators: 1: width in pixels 2: height in pixels */
		$ewwwio_resize_status = sprintf( __( 'Resize not required, image smaller than %1$s x %2$s', 'ewww-image-optimizer' ), $maxwidth . 'w', $maxheight . 'h' );
		if ( $oldwidth && $oldheight ) {
			return array( $oldwidth, $oldheight );
		}
		return false;
	}
	$crop = false;
	if ( $oldwidth >= $maxwidth && $maxwidth && $oldheight >= $maxheight && $maxheight && apply_filters( 'ewww_image_optimizer_crop_image', false ) ) {
		$crop      = true;
		$newwidth  = $maxwidth;
		$newheight = $maxheight;
	} else {
		list( $newwidth, $newheight ) = wp_constrain_dimensions( $oldwidth, $oldheight, $maxwidth, $maxheight );
	}
	if ( ! ewwwio_check_memory_available( ( $oldwidth * $oldheight + $newwidth * $newheight ) * 4.8 ) ) { // 4.8 = 24-bit or 3 bytes per pixel multiplied by a factor of 1.6 for extra wiggle room.
		$memory_required = ( $oldwidth * $oldheight + $newwidth * $newheight ) * 4.8;
		ewwwio_debug_message( "possibly insufficient memory for resizing operation: $memory_required" );
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			add_filter( 'image_memory_limit', 'ewww_image_optimizer_raise_memory_limit' );
			wp_raise_memory_limit( 'image' );
		}
	}
	if ( ! function_exists( 'wp_get_image_editor' ) ) {
		ewwwio_debug_message( 'no image editor function' );
		$ewwwio_resize_status = __( 'wp_get_image_editor function is missing', 'ewww-image-optimizer' );
		return false;
	}

	// From here...
	global $ewww_preempt_editor;
	$ewww_preempt_editor = true;

	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) {
		$error_message = $editor->get_error_message();
		ewwwio_debug_message( "could not get image editor: $error_message" );
		/* translators: %s: a WP error message, translated elsewhere */
		$ewwwio_resize_status = sprintf( __( 'Unable to load resize function: %s', 'ewww-image-optimizer' ), $error_message );
		return false;
	}
	// Rotation only happens on existing media here, so we need to swap dimension when we rotate by 90.
	$orientation = ewww_image_optimizer_get_orientation( $file, $type );
	$rotated     = false;
	switch ( $orientation ) {
		case 3:
			$editor->rotate( 180 );
			$rotated = true;
			break;
		case 6:
			$editor->rotate( -90 );
			$new_newwidth = $newwidth;
			$newwidth     = $newheight;
			$newheight    = $new_newwidth;
			$rotated      = true;
			break;
		case 8:
			$editor->rotate( 90 );
			$new_newwidth = $newwidth;
			$newwidth     = $newheight;
			$newheight    = $new_newwidth;
			$rotated      = true;
			break;
	}
	$resized_image = $editor->resize( $newwidth, $newheight, $crop );
	if ( is_wp_error( $resized_image ) ) {
		$error_message = $resized_image->get_error_message();
		ewwwio_debug_message( "error during resizing: $error_message" );
		/* translators: %s: a WP error message, translated elsewhere */
		$ewwwio_resize_status = sprintf( __( 'Resizing error: %s', 'ewww-image-optimizer' ), $error_message );
		return false;
	}
	$new_file  = $editor->generate_filename( 'tmp' );
	$orig_size = filesize( $file );
	ewwwio_debug_message( "before resizing: $orig_size" );
	$saved = $editor->save( $new_file );
	if ( is_wp_error( $saved ) ) {
		$error_message = $saved->get_error_message();
		ewwwio_debug_message( "error saving resized image: $error_message" );
		/* translators: %s: a WP error message, translated elsewhere */
		$ewwwio_resize_status = sprintf( __( 'Could not save resized image: %s', 'ewww-image-optimizer' ), $error_message );
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_noauto' ) && ( ! defined( 'EWWW_IMAGE_OPTIMIZER_DISABLE_EDITOR' ) || ! EWWW_IMAGE_OPTIMIZER_DISABLE_EDITOR ) ) {
		$ewww_preempt_editor = false;
	}
	// to here is replaced by cloud/API function.
	$new_size = ewww_image_optimizer_filesize( $new_file );
	if ( ( $new_size && (int) $new_size !== (int) $orig_size && apply_filters( 'ewww_image_optimizer_resize_filesize_ignore', false ) ) || ( $new_size && $new_size < $orig_size ) ) {
		// Use this action to perform any operations on the original file before it is overwritten with the new, smaller file.
		do_action( 'ewww_image_optimizer_image_resized', $file, $new_file );
		ewwwio_debug_message( "after resizing: $new_size" );
		// Use PEL to get the exif (if Remove Meta is unchecked) and GD is in use, so we can save it to the new image.
		if ( 'image/jpeg' === $type && ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_skip_full' ) || ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ) && ! ewww_image_optimizer_imagick_support() ) {
			ewwwio_debug_message( 'manually copying metadata for GD' );
			try {
				$old_jpeg = new PelJpeg( $file );
				$old_exif = $old_jpeg->getExif();
				$new_jpeg = new PelJpeg( $new_file );
			} catch ( PelDataWindowOffsetException $pelerror ) {
				ewwwio_debug_message( 'pel exception: ' . $pelerror->getMessage() );
				$old_exif = null;
			} catch ( PelDataWindowOffsetException $pelerror ) {
				ewwwio_debug_message( 'pel exception: ' . $pelerror->getMessage() );
				$old_exif = null;
			} catch ( Exception $pelerror ) {
				ewwwio_debug_message( 'pel exception: ' . $pelerror->getMessage() );
				$old_exif = null;
			}
			if ( ! is_null( $old_exif ) ) {
				if ( $rotated ) {
					$tiff        = $old_exif->getTiff();
					$ifd0        = $tiff->getIfd();
					$orientation = $ifd0->getEntry( PelTag::ORIENTATION );
					if ( ! is_null( $orientation ) ) {
						$orientation->setValue( 1 );
					}
				}
				$new_jpeg->setExif( $old_exif );
			}
			$new_jpeg->saveFile( $new_file );
		}
		// backup the file to the API, right before we replace the original.
		ewww_image_optimizer_cloud_backup( $file );
		$new_type = (string) ewww_image_optimizer_mimetype( $new_file, 'i' );
		if ( $type === $new_type ) {
			ewwwio_rename( $new_file, $file );
		} else {
			ewwwio_debug_message( "resizing did not create a valid image: $new_type" );
			/* translators: %s: the mime type of the new file */
			$ewwwio_resize_status = sprintf( __( 'Resizing resulted in an invalid file type: %s', 'ewww-image-optimizer' ), $new_type );
			unlink( $new_file );
			return false;
		}
		// Store info on the current image for future reference.
		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		// Delete the record created from optimizing the resized file (if it exists, which it shouldn't).
		$temp_optimized = ewww_image_optimizer_find_already_optimized( $new_file );
		if ( is_array( $temp_optimized ) && ! empty( $temp_optimized['id'] ) ) {
			$ewwwdb->delete(
				$ewwwdb->ewwwio_images,
				array(
					'id' => $temp_optimized['id'],
				),
				array(
					'%d',
				)
			);
		}
		/* translators: 1: width in pixels 2: height in pixels */
		$ewwwio_resize_status = sprintf( __( 'Resized to %1$s x %2$s', 'ewww-image-optimizer' ), $newwidth . 'w', $newheight . 'h' );
		return array( $newwidth, $newheight );
	} // End if().
	if ( ewwwio_is_file( $new_file ) ) {
		ewwwio_debug_message( "resizing did not create a smaller image: $new_size" );
		$ewwwio_resize_status = __( 'Resizing did not reduce the file size, result discarded', 'ewww-image-optimizer' );
		unlink( $new_file );
	}
	return false;
}

/**
 * Gets the orientation/rotation of a JPG image using the EXIF data.
 *
 * @param string $file Name of the file.
 * @param string $type Mime type of the file.
 * @return int|bool The orientation value or false.
 */
function ewww_image_optimizer_get_orientation( $file, $type ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( function_exists( 'exif_read_data' ) && 'image/jpeg' === $type && ewwwio_is_readable( $file ) ) {
		$exif = @exif_read_data( $file );
		if ( ewww_image_optimizer_function_exists( 'print_r' ) ) {
			ewwwio_debug_message( print_r( $exif, true ) );
		}
		if ( is_array( $exif ) && array_key_exists( 'Orientation', $exif ) ) {
			return $exif['Orientation'];
		}
	}
	return false;
}

/**
 * Searches the images table for a file.
 *
 * If more than one record is found, verifies case and calls duplicate removal if needed.
 *
 * @global object $wpdb
 * @global object $ewwwdb A clone of $wpdb unless it is lacking utf8 connectivity.
 *
 * @param string $attachment The name of the file.
 * @return array|bool If found, information about the image, false otherwise.
 */
function ewww_image_optimizer_find_already_optimized( $attachment ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	$maybe_return_image  = false;
	$maybe_relative_path = ewww_image_optimizer_relativize_path( $attachment );
	$query               = $ewwwdb->prepare( "SELECT * FROM $ewwwdb->ewwwio_images WHERE path = %s", $maybe_relative_path );
	$optimized_query     = $ewwwdb->get_results( $query, ARRAY_A );
	if ( empty( $optimized_query ) && $attachment !== $maybe_relative_path ) {
		$query           = $ewwwdb->prepare( "SELECT * FROM $ewwwdb->ewwwio_images WHERE path = %s", $attachment );
		$optimized_query = $ewwwdb->get_results( $query, ARRAY_A );
	}
	if ( ewww_image_optimizer_iterable( $optimized_query ) ) {
		foreach ( $optimized_query as $image ) {
			$image['path']          = ewww_image_optimizer_absolutize_path( $image['path'] );
			$image['image_size']    = (int) $image['image_size'];
			$image['orig_size']     = (int) $image['orig_size'];
			$image['attachment_id'] = (int) $image['attachment_id'];
			$image['level']         = (int) $image['level'];
			if ( $image['path'] !== $attachment ) {
				ewwwio_debug_message( "{$image['path']} does not match $attachment, continuing our search" );
			} elseif ( ! $maybe_return_image ) {
				ewwwio_debug_message( "found a match for $attachment" );
				$maybe_return_image = $image;
			} else {
				if ( empty( $duplicates ) ) {
					$duplicates = array( $maybe_return_image, $image );
				} else {
					$duplicates[] = $image;
				}
			}
		}
	}
	// Do something with duplicates.
	if ( ! empty( $duplicates ) && is_array( $duplicates ) ) {
		$keeper = ewww_image_optimizer_remove_duplicate_records( $duplicates );
		if ( ! empty( $keeper ) && is_array( $keeper ) ) {
			$maybe_return_image = $keeper;
		}
	}
	return $maybe_return_image;
}

/**
 * Check if an attachment was previously optimized on a higher compression level and should be restored before continuing.
 *
 * @global object $wpdb
 *
 * @param int    $id The attachment to check for potential restoration.
 * @param string $type The mime-type of the attachment.
 * @param array  $meta The attachment metadata.
 * @return array The attachment meta, potentially altered.
 */
function ewww_image_optimizer_attachment_check_variant_level( $id, $type, $meta ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewww_force;
	if ( empty( $ewww_force ) ) {
		return $meta;
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) || ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ) {
		return $meta;
	}
	if ( 'image/jpeg' === $type && (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 20 ) {
		return $meta;
	}
	if ( 'image/png' === $type && (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) > 20 ) {
		return $meta;
	}
	if ( 'application/pdf' === $type && 10 !== (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) ) {
		return $meta;
	}
	$compression_level = ewww_image_optimizer_get_level( $type );
	// Retrieve any records for this image.
	global $wpdb;
	$optimized_images = $wpdb->get_results( $wpdb->prepare( "SELECT image_size,orig_size,resize,level,backup,updated FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'media' AND image_size <> 0 ORDER BY orig_size DESC", $id ), ARRAY_A );
	foreach ( $optimized_images as $optimized_image ) {
		if ( 'full' === $optimized_image['resize'] && $compression_level < $optimized_image['level'] ) {
			$updated_time = strtotime( $optimized_image['updated'] );
			if ( DAY_IN_SECONDS * 30 + $updated_time > time() ) {
				return ewww_image_optimizer_cloud_restore_from_meta_data( $id, 'media', $meta );
			}
		}
	}
	return $meta;
}

/**
 * Merge duplicate records from the images table and remove any extras.
 *
 * @global object $wpdb
 * @global object $ewwwdb A clone of $wpdb unless it is lacking utf8 connectivity.
 *
 * @param array $duplicates An array of records referencing the same image.
 * @return array|bool A single image record or false if something unexpected happens.
 */
function ewww_image_optimizer_remove_duplicate_records( $duplicates ) {
	if ( empty( $duplicates ) ) {
		return false;
	}
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	if ( ! is_array( $duplicates[0] ) ) {
		// Retrieve records for the ID #s passed.
		$duplicate_ids = implode( ',', array_map( 'intval', $duplicates ) );
		$duplicates    = $ewwwdb->get_results( "SELECT * FROM $ewwwdb->ewwwio_images WHERE id IN ($duplicate_ids)" );
	}
	if ( ! is_array( $duplicates ) || ! is_array( $duplicates[0] ) ) {
		return false;
	}
	$image_size = ewww_image_optimizer_filesize( $duplicates[0]['path'] );
	$discard    = array();
	ewwwio_debug_message( 'looking for duplicates of: ' . $duplicates[0]['path'] . " filesize = $image_size" );
	// First look for an image size match.
	foreach ( $duplicates as $duplicate ) {
		if ( empty( $keeper ) && ! empty( $duplicate['image_size'] ) && $image_size === $duplicate['image_size'] ) {
			$keeper = $duplicate;
		} else {
			$discard[] = $duplicate;
		}
	}
	// Then look for the first record with an image_size (that means it has been optimized).
	if ( empty( $keeper ) ) {
		$discard = array();
		foreach ( $duplicates as $duplicate ) {
			if ( empty( $keeper ) && ! empty( $duplicate['image_size'] ) ) {
				$keeper = $duplicate;
			} else {
				$discard[] = $duplicate;
			}
		}
	}
	// If we still have nothing, mark the 0 record as the primary and pull it off the stack.
	if ( empty( $keeper ) ) {
		$keeper  = array_shift( $duplicates );
		$discard = $duplicates;
	}
	if ( is_array( $keeper ) && is_array( $discard ) ) {
		$delete_ids = array();
		foreach ( $discard as $record ) {
			foreach ( $record as $key => $value ) {
				if ( empty( $keeper[ $key ] ) && ! empty( $value ) ) {
					$keeper[ $key ] = $value;
				}
			}
			$delete_ids[] = (int) $record['id'];
		}
		if ( ! empty( $delete_ids ) && is_array( $delete_ids ) ) {
			$query_ids = implode( ',', $delete_ids );
			$ewwwdb->query( "DELETE FROM $ewwwdb->ewwwio_images WHERE id IN ($query_ids)" );
		}
		return $keeper;
	}
	return false;
}

/**
 * See if background mode is allowed/enabled.
 *
 * @return bool True if it is, false if it isn't.
 */
function ewww_image_optimizer_background_mode_enabled() {
	if ( defined( 'EWWW_DISABLE_ASYNC' ) && EWWW_DISABLE_ASYNC ) {
		ewwwio_debug_message( 'background disabled by admin' );
		return false;
	}
	if ( ! ewww_image_optimizer_function_exists( 'sleep' ) ) {
		ewwwio_debug_message( 'background disabled by lack of sleep' );
		return false;
	}
	if ( ewww_image_optimizer_detect_wpsf_location_lock() ) {
		ewwwio_debug_message( 'background disabled by shield location lock' );
		return false;
	}
	return (bool) ewww_image_optimizer_get_option( 'ewww_image_optimizer_background_optimization' );
}

/**
 * Checks to see if we should use background optimization for an image.
 *
 * Uses the mimetype and current configuration to determine if background mode should be used.
 *
 * @global bool $ewww_defer True to defer optimization.
 *
 * @param string $type Optional. Mime type of image being processed. Default ''.
 * @return bool True if background mode should be used.
 */
function ewww_image_optimizer_test_background_opt( $type = '' ) {
	if ( ! ewww_image_optimizer_background_mode_enabled() ) {
		return false;
	}
	if ( 'image/type' === $type && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_to_png' ) ) {
		return apply_filters( 'ewww_image_optimizer_defer_conversion', false );
	}
	if ( 'image/png' === $type && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_to_jpg' ) ) {
		return apply_filters( 'ewww_image_optimizer_defer_conversion', false );
	}
	if ( 'image/gif' === $type && ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_to_png' ) ) {
		return apply_filters( 'ewww_image_optimizer_defer_conversion', false );
	}
	global $ewww_defer;
	return (bool) apply_filters( 'ewww_image_optimizer_background_optimization', $ewww_defer );
}

/**
 * Checks to see if we should use parallel optimization for an image.
 *
 * Uses the mimetype and current configuration to determine if parallel mode should be used.
 *
 * @param string $type Optional. Mime type of image being processed. Default ''.
 * @param int    $id Optional. Attachment ID number. Default 0.
 * @return bool True if parallel mode should be used.
 */
function ewww_image_optimizer_test_parallel_opt( $type = '', $id = 0 ) {
	if ( defined( 'EWWW_DISABLE_ASYNC' ) && EWWW_DISABLE_ASYNC ) {
		return false;
	}
	if ( ! ewww_image_optimizer_function_exists( 'sleep' ) ) {
		return false;
	}
	if ( ewww_image_optimizer_detect_wpsf_location_lock() ) {
		return false;
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_parallel_optimization' ) ) {
		return false;
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_background_optimization' ) ) {
		return false;
	}
	if ( empty( $id ) ) {
		return true;
	}
	global $ewww_convert;
	if ( ! empty( $ewww_convert ) ) {
		return false;
	}
	if ( empty( $type ) ) {
		$type = get_post_mime_type( $id );
	}
	if ( 'image/jpeg' === $type && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_to_png' ) ) {
		return false;
	}
	if ( 'image/png' === $type && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_to_jpg' ) ) {
		return false;
	}
	if ( 'image/gif' === $type && ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_to_png' ) ) {
		return false;
	}
	if ( 'application/pdf' === $type ) {
		return false;
	}
	if ( 'image/svg+xml' === $type ) {
		return false;
	}
	return true;
}

/**
 * Rebuilds metadata and regenerates thumbs for an attachment.
 *
 * @param int $attachment_id The ID number of the attachment.
 * @return array Attachment metadata, if the rebuild was successful.
 */
function ewww_image_optimizer_rebuild_meta( $attachment_id ) {
	$file = get_attached_file( $attachment_id );
	if ( ewwwio_is_file( $file ) ) {
		global $ewww_preempt_editor;
		$ewww_preempt_editor = true;
		remove_all_filters( 'wp_generate_attachment_metadata' );
		ewwwio_debug_message( "generating new meta for $attachment_id" );
		$meta = wp_generate_attachment_metadata( $attachment_id, $file );
		ewwwio_debug_message( "generated new meta for $attachment_id" );
		$updated = update_post_meta( $attachment_id, '_wp_attachment_metadata', $meta );
		if ( $updated ) {
			ewwwio_debug_message( "updated meta for $attachment_id" );
		} else {
			ewwwio_debug_message( "failed meta update for $attachment_id" );
		}
		return $meta;
	}
}

/**
 * Find the path to a backed-up original (not the full-size version like the core WP function).
 *
 * @param int    $id The attachment ID number.
 * @param string $image_file The path to a scaled image file.
 * @param array  $meta The attachment metadata. Optional, default to null.
 * @return bool True on success, false on failure.
 */
function ewwwio_get_original_image_path( $id, $image_file = '', $meta = null ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$id = (int) $id;
	if ( empty( $id ) ) {
		return false;
	}
	if ( ! wp_attachment_is_image( $id ) ) {
		return false;
	}
	if ( is_null( $meta ) ) {
		$meta = wp_get_attachment_metadata( $id );
	}
	if ( empty( $image_file ) ) {
		$image_file = get_attached_file( $id, true );
	}
	if ( ! $image_file || ! ewww_image_optimizer_iterable( $meta ) || empty( $meta['original_image'] ) ) {
		if ( $image_file && apply_filters( 'ewwwio_find_original_image_no_meta', false, $image_file ) && strpos( $image_file, '-scaled.' ) ) {
			ewwwio_debug_message( "constructing path with $image_file alone" );
			$original_image = trailingslashit( dirname( $image_file ) ) . wp_basename( str_replace( '-scaled.', '.', $original_image ) );
			if ( $original_image !== $image_file ) {
				ewwwio_debug_message( "found $original_image" );
				return $original_image;
			}
		}
		return false;
	}
	ewwwio_debug_message( "constructing path with $image_file and " . $meta['original_image'] );

	return trailingslashit( dirname( $image_file ) ) . wp_basename( $meta['original_image'] );
}

/**
 * Remove the backed-up original_image stored by WP 5.3+.
 *
 * @param int   $id The attachment ID number.
 * @param array $meta The attachment metadata. Optional, default to null.
 * @return bool|array Returns meta if modified, false otherwise (even if an "unlinked" original is removed).
 */
function ewwwio_remove_original_image( $id, $meta = null ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$id = (int) $id;
	if ( empty( $id ) ) {
		return false;
	}
	if ( is_null( $meta ) ) {
		ewwwio_debug_message( "getting meta for $id" );
		$meta = wp_get_attachment_metadata( $id );
	}

	if (
		$meta && is_array( $meta ) &&
		! empty( $meta['original_image'] ) && function_exists( 'wp_get_original_image_path' )
	) {
		$original_image = ewwwio_get_original_image_path( $id, '', $meta );
		if ( $original_image && is_file( $original_image ) && is_writable( $original_image ) ) {
			ewwwio_debug_message( "removing $original_image" );
			unlink( $original_image );
		}
		clearstatcache();
		if ( empty( $original_image ) || ! is_file( $original_image ) ) {
			ewwwio_debug_message( 'cleaning meta' );
			unset( $meta['original_image'] );
			return $meta;
		}
	}
	return false;
}

/**
 * Find image paths from an attachment's meta data and process each image.
 *
 * Called after `wp_generate_attachment_metadata` is completed, it also searches for retina images,
 * and a few custom theme resizes. When a new image is uploaded, it is added to the queue, if
 * possible, and then this same function is run in the background. Optionally, it will use parallel
 * (async) requests to speed up the process.
 *
 * @global object $wpdb
 * @global object $ewwwdb A clone of $wpdb unless it is lacking utf8 connectivity.
 * @global bool $ewww_new_image True if this is a newly uploaded image.
 * @global object $ewww_image Contains more information about the image currently being processed.
 * @global object $ewwwio_media_background;
 * @global object $ewwwio_async_optimize_media;
 * @global array $ewww_attachment {
 *     Stores the ID and meta for later use with W3TC.
 *
 *     @type int $id The attachment ID number.
 *     @type array $meta The attachment metadata from the postmeta table.
 * }
 * @global object $as3cf For working with the WP Offload S3 plugin.
 * @global object $dreamspeed For working with the Dreamspeed CDN plugin.
 *
 * @param array $meta The attachment metadata generated by WordPress.
 * @param int   $id Optional. The attachment ID number. Default null. Accepts any non-negative integer.
 * @param bool  $log Optional. True to flush debug info to the log at the end of the function.
 * @param bool  $background_new Optional. True indicates this is a new image processed in the background.
 * @return array $meta Send the metadata back from whence it came.
 */
function ewww_image_optimizer_resize_from_meta_data( $meta, $id = null, $log = true, $background_new = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! is_array( $meta ) && empty( $meta ) ) {
		$meta = array();
	} elseif ( ! is_array( $meta ) ) {
		ewwwio_debug_message( 'attachment meta is not a usable array' );
		return $meta;
	}
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	global $ewww_new_image;
	global $ewww_image;
	global $ewww_force;
	global $eio_filesystem;
	ewwwio_get_filesystem();
	$gallery_type = 1;
	ewwwio_debug_message( "attachment id: $id" );

	session_write_close();
	if ( ! empty( $ewww_new_image ) ) {
		ewwwio_debug_message( 'this is a newly uploaded image with no metadata yet' );
		$new_image = true;
	} elseif ( $background_new ) {
		ewwwio_debug_message( 'this is a newly uploaded image from the async queue' );
		$new_image = true;
	} else {
		ewwwio_debug_message( 'this image already has metadata, so it is not new' );
		$new_image = false;
	}

	list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );

	/**
	 * Allow altering the metadata or performing other actions before the plugin processes an attachement.
	 *
	 * @param array  $meta The attachment metadata.
	 * @param string $file_path The file path to the image.
	 * @param bool   $new_image True if this is a newly uploaded image, false otherwise.
	 */
	$meta = apply_filters( 'ewww_image_optimizer_resize_from_meta_data', $meta, $file_path, $new_image );

	// If the attachment has been uploaded via the image store plugin.
	if ( 'ims_image' === get_post_type( $id ) ) {
		$gallery_type = 6;
	}
	if ( ! $new_image && class_exists( 'Amazon_S3_And_CloudFront' ) && ewww_image_optimizer_stream_wrapped( $file_path ) ) {
		ewww_image_optimizer_check_table_as3cf( $meta, $id, $file_path );
	}
	if ( ! ewwwio_is_file( $file_path ) && class_exists( 'wpCloud\StatelessMedia\EWWW' ) && ! empty( $meta['gs_link'] ) ) {
		$file_path = ewww_image_optimizer_remote_fetch( $id, $meta );
	}
	// If the local file is missing and we have valid metadata, see if we can fetch via CDN.
	if ( ! ewwwio_is_file( $file_path ) || ewww_image_optimizer_stream_wrapped( $file_path ) ) {
		$file_path = ewww_image_optimizer_remote_fetch( $id, $meta );
		if ( ! $file_path ) {
			ewwwio_debug_message( 'could not retrieve path' );
			return $meta;
		}
	}
	ewwwio_debug_message( "retrieved file path: $file_path" );
	$type            = ewww_image_optimizer_mimetype( $file_path, 'i' );
	$supported_types = array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'application/pdf',
		'image/svg+xml',
	);
	if ( ! in_array( $type, $supported_types, true ) ) {
		ewwwio_debug_message( "mimetype not supported: $id" );
		return $meta;
	}

	$meta = ewww_image_optimizer_attachment_check_variant_level( $id, $type, $meta );

	$fullsize_size = ewww_image_optimizer_filesize( $file_path );

	// Initialize an EWWW_Image object for the full-size image so that the original size will be tracked before any potential resizing operations.
	$ewww_image         = new EWWW_Image( $id, 'media', $file_path );
	$ewww_image->resize = 'full';

	// Resize here so long as this is not a new image AND resize existing is enabled, and imsanity isn't enabled with a max size.
	if ( ( empty( $new_image ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_existing' ) ) && ! function_exists( 'imsanity_get_max_width_height' ) ) {
		ewwwio_debug_message( 'not a new image, resize existing enabled, and Imsanity not detected' );
		$new_dimensions = ewww_image_optimizer_resize_upload( $file_path );
		if ( is_array( $new_dimensions ) ) {
			$meta['width']  = $new_dimensions[0];
			$meta['height'] = $new_dimensions[1];
		}
	}
	if ( ewww_image_optimizer_test_background_opt( $type ) ) {
		add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
		if ( defined( 'EWWW_IMAGE_OPTIMIZER_DEFER_S3' ) && EWWW_IMAGE_OPTIMIZER_DEFER_S3 ) {
			ewwwio_debug_message( 's3 upload deferred' );
			add_filter( 'as3cf_pre_update_attachment_metadata', '__return_true' );
		}
		global $ewwwio_media_background;
		if ( ! class_exists( 'WP_Background_Process' ) ) {
			require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
		}
		if ( ! is_object( $ewwwio_media_background ) ) {
			$ewwwio_media_background = new EWWWIO_Media_Background_Process();
		}
		ewwwio_debug_message( "backgrounding optimization for $id" );
		$ewwwio_media_background->push_to_queue(
			array(
				'id'  => $id,
				'new' => $new_image,
			)
		);
		if ( 5 > $ewwwio_media_background->count_queue() ) {
			$ewwwio_media_background->dispatch();
			ewwwio_debug_message( 'small queue, dispatching post-haste' );
		}
		if ( $log ) {
			ewww_image_optimizer_debug_log();
		}
		return $meta;
	}

	// Resize here if the user has used the filter to defer resizing, we have a new image OR resize existing is enabled, and imsanity isn't enabled with a max size.
	if ( apply_filters( 'ewww_image_optimizer_defer_resizing', false ) && ( ! empty( $new_image ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_existing' ) ) && ! function_exists( 'imsanity_get_max_width_height' ) ) {
		ewwwio_debug_message( 'resizing defered and ( new image or resize existing enabled ) and Imsanity not detected' );
		$new_dimensions = ewww_image_optimizer_resize_upload( $file_path );
		if ( is_array( $new_dimensions ) ) {
			$meta['width']  = $new_dimensions[0];
			$meta['height'] = $new_dimensions[1];
		}
	}
	// Run in parallel if it's enabled+safe (test_parallel_opt), and there are enough resizes to make it worthwhile or if the API is enabled.
	if (
		ewww_image_optimizer_test_parallel_opt( $type, $id ) &&
		isset( $meta['sizes'] ) &&
		( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) || count( $meta['sizes'] ) > 5 )
	) {
		ewwwio_debug_message( 'running in parallel' );
		$parallel_opt = true;
	} else {
		ewwwio_debug_message( 'running in sequence' );
		$parallel_opt = false;
	}
	$parallel_sizes = array();
	if ( $parallel_opt ) {
		add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
		$parallel_sizes['full'] = $file_path;
		global $ewwwio_async_optimize_media;
		if ( ! class_exists( 'WP_Background_Process' ) ) {
			require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
		}
		if ( ! is_object( $ewwwio_async_optimize_media ) ) {
			$ewwwio_async_optimize_media = new EWWWIO_Async_Request();
		}
	} else {
		// Run the optimization and store the results.
		list( $file, $msg, $conv, $original ) = ewww_image_optimizer( $file_path, $gallery_type, false, $new_image, true );

		// If the file was converted.
		if ( false !== $conv && $file ) {
			if ( $conv ) {
				$ewww_image->increment = $conv;
			}
			$ewww_image->file      = $file;
			$ewww_image->converted = $original;
			$meta['file']          = _wp_relative_upload_path( $file );
			$ewww_image->update_converted_attachment( $meta );
			$meta = $ewww_image->convert_sizes( $meta );
			ewwwio_debug_message( 'image was converted' );
		} else {
			remove_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment', 10 );
		}
		ewww_image_optimizer_hidpi_optimize( $file );
	}

	// See if we are forcing re-optimization per the user's request.
	if ( ! empty( $ewww_force ) ) {
		$force = true;
	} else {
		$force = false;
	}

	$base_dir = trailingslashit( dirname( $file_path ) );
	// Resized versions, so we can continue.
	if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
		$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt', false, true );
		ewwwio_debug_message( 'processing resizes' );
		// Meta sizes don't contain a path, so we calculate one.
		if ( 6 === $gallery_type ) {
			$base_ims_dir = trailingslashit( dirname( $file_path ) ) . '_resized/';
		}
		// Process each resized version.
		$processed = array();
		foreach ( $meta['sizes'] as $size => $data ) {
			ewwwio_debug_message( "processing size: $size" );
			if ( strpos( $size, 'webp' ) === 0 ) {
				continue;
			}
			if ( ! empty( $disabled_sizes[ $size ] ) ) {
				continue;
			}
			if ( ! empty( $disabled_sizes['pdf-full'] ) && 'full' === $size ) {
				continue;
			}
			if ( empty( $data['file'] ) ) {
				continue;
			}
			if ( 6 === $gallery_type ) {
				// We reset base_dir, because base_dir potentially gets overwritten with base_ims_dir.
				$base_dir   = trailingslashit( dirname( $file_path ) );
				$image_path = $base_dir . $data['file'];
				$ims_path   = $base_ims_dir . $data['file'];
				if ( ewwwio_is_file( $ims_path ) ) {
					ewwwio_debug_message( 'ims resize already exists, wahoo' );
					ewwwio_debug_message( "ims path: $ims_path" );
					$image_size        = ewww_image_optimizer_filesize( $ims_path );
					$already_optimized = ewww_image_optimizer_find_already_optimized( $image_path );
					if ( is_array( $already_optimized ) ) {
						ewwwio_debug_message( "updating existing record, path: $ims_path, size: " . $image_size );
						// Store info on the current image for future reference.
						$ewwwdb->update(
							$ewwwdb->ewwwio_images,
							array(
								'path' => ewww_image_optimizer_relativize_path( $ims_path ),
							),
							array(
								'id' => $already_optimized['id'],
							)
						);
					}
					$base_dir = $base_ims_dir;
				}
			}
			// Check through all the sizes we've processed so far.
			foreach ( $processed as $proc => $scan ) {
				// If a previous resize had identical dimensions.
				if ( $scan['height'] === $data['height'] && $scan['width'] === $data['width'] ) {
					// We found a duplicate resize, so...
					// Point this resize at the same image as the previous one.
					$meta['sizes'][ $size ]['file']      = $meta['sizes'][ $proc ]['file'];
					$meta['sizes'][ $size ]['mime-type'] = $meta['sizes'][ $proc ]['mime-type'];
					continue( 2 );
				}
			}
			// If this is a unique size.
			$resize_path = str_replace( wp_basename( $file_path ), $data['file'], $file_path );
			if ( empty( $resize_path ) ) {
				ewwwio_debug_message( 'strange... $resize_path was empty' );
				continue;
			}
			$resize_path = path_join( $upload_path, $resize_path );
			if ( 'application/pdf' === $type && 'full' === $size ) {
				$size = 'pdf-full';
				ewwwio_debug_message( 'processing full size pdf preview' );
			}
			// Because some SVG plugins populate the resizes with the original path (since SVG is "scalable", of course).
			// Though it could happen for other types perhaps...
			if ( $resize_path === $file_path ) {
				continue;
			}
			if ( $parallel_opt && ewwwio_is_file( $resize_path ) ) {
				$parallel_sizes[ $size ] = $resize_path;
			} else {
				$ewww_image         = new EWWW_Image( $id, 'media', $resize_path );
				$ewww_image->resize = $size;
				// Run the optimization and store the results.
				ewww_image_optimizer( $resize_path );
			}
			// Optimize retina images, if they exist.
			if ( function_exists( 'wr2x_get_retina' ) ) {
				$retina_path = wr2x_get_retina( $resize_path );
			} else {
				$retina_path = false;
			}
			if ( $retina_path && ewwwio_is_file( $retina_path ) ) {
				if ( $parallel_opt ) {
					$ewwwio_async_optimize_media->data(
						array(
							'ewwwio_id'            => ewww_image_optimizer_single_insert( $retina_path, 'media', $id ),
							'ewwwio_attachment_id' => $id,
							'ewww_force'           => $force,
						)
					)->dispatch();
				} else {
					$ewww_image         = new EWWW_Image( $id, 'media', $retina_path );
					$ewww_image->resize = $size . '-retina';
					ewww_image_optimizer( $retina_path );
				}
			} elseif ( ! $parallel_opt ) {
				ewww_image_optimizer_hidpi_optimize( $resize_path );
			}
			// Store info on the sizes we've processed, so we can check the list for duplicate sizes.
			$processed[ $size ]['width']  = $data['width'];
			$processed[ $size ]['height'] = $data['height'];
		} // End foreach().
	} // End if().

	// Original image detected.
	if ( isset( $meta['original_image'] ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_originals' ) ) {
		ewwwio_debug_message( 'processing original_image' );
		// Meta sizes don't contain a path, so we calculate one.
		$resize_path = trailingslashit( dirname( $file_path ) ) . $meta['original_image'];
		if ( $parallel_opt && ewwwio_is_file( $resize_path ) ) {
			$parallel_sizes['original_image'] = $resize_path;
		} else {
			$ewww_image         = new EWWW_Image( $id, 'media', $resize_path );
			$ewww_image->resize = 'original_image';
			// Run the optimization and store the results (gallery type 5 and fullsize=true to obey lossy/metadata exclusions).
			ewww_image_optimizer( $resize_path, 5, false, false, true );
		}
	} // End if().

	// Process size from a custom theme.
	if ( isset( $meta['image_meta']['resized_images'] ) && ewww_image_optimizer_iterable( $meta['image_meta']['resized_images'] ) ) {
		$imagemeta_resize_pathinfo = pathinfo( $file_path );
		$imagemeta_resize_path     = '';
		foreach ( $meta['image_meta']['resized_images'] as $imagemeta_resize ) {
			$imagemeta_resize_path = $imagemeta_resize_pathinfo['dirname'] . '/' . $imagemeta_resize_pathinfo['filename'] . '-' . $imagemeta_resize . '.' . $imagemeta_resize_pathinfo['extension'];
			if ( $parallel_opt && ewwwio_is_file( $imagemeta_resize_path ) ) {
				$ewwwio_async_optimize_media->data(
					array(
						'ewwwio_id'            => ewww_image_optimizer_single_insert( $imagemeta_resize_path, 'media', $id ),
						'ewwwio_attachment_id' => $id,
						'ewww_force'           => $force,
					)
				)->dispatch();
			} else {
				$ewww_image = new EWWW_Image( $id, 'media', $imagemeta_resize_path );
				ewww_image_optimizer( $imagemeta_resize_path );
			}
		}
	}

	// And another custom theme.
	if ( isset( $meta['custom_sizes'] ) && ewww_image_optimizer_iterable( $meta['custom_sizes'] ) ) {
		$custom_sizes_pathinfo = pathinfo( $file_path );
		$custom_size_path      = '';
		foreach ( $meta['custom_sizes'] as $custom_size ) {
			$custom_size_path = $custom_sizes_pathinfo['dirname'] . '/' . $custom_size['file'];
			if ( $parallel_opt && file_exists( $custom_size_path ) ) {
				$ewwwio_async_optimize_media->data(
					array(
						'ewwwio_id'            => ewww_image_optimizer_single_insert( $custom_size_path, 'media', $id ),
						'ewwwio_attachment_id' => $id,
						'ewww_force'           => $force,
					)
				)->dispatch();
			} else {
				$ewww_image = new EWWW_Image( $id, 'media', $custom_size_path );
				ewww_image_optimizer( $custom_size_path );
			}
		}
	}

	if ( $parallel_opt && count( $parallel_sizes ) > 0 ) {
		$max_threads      = (int) apply_filters( 'ewww_image_optimizer_max_parallel_threads', 5 );
		$processing       = true;
		$timer            = (int) apply_filters( 'ewww_image_optimizer_background_timer_init', 1 );
		$increment        = (int) apply_filters( 'ewww_image_optimizer_background_timer_increment', 1 );
		$timer_max        = (int) apply_filters( 'ewww_image_optimizer_background_timer_max', 20 );
		$processing_sizes = array();
		global $ewwwio_async_optimize_media;
		if ( ! class_exists( 'WP_Background_Process' ) ) {
			require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
		}
		if ( ! is_object( $ewwwio_async_optimize_media ) ) {
			$ewwwio_async_optimize_media = new EWWWIO_Async_Request();
		}
		while ( $parallel_opt && count( $parallel_sizes ) > 0 ) {
			$threads = $max_threads;
			ewwwio_debug_message( 'sizes left to queue: ' . count( $parallel_sizes ) );
			// Phase 1, add $max_threads items to the queue and dispatch.
			foreach ( $parallel_sizes as $size => $filename ) {
				if ( $threads < 1 ) {
					continue;
				}
				if ( ! file_exists( $filename ) ) {
					unset( $parallel_sizes[ $size ] );
					continue;
				}
				ewwwio_debug_message( "queueing size $size - $filename" );
				$processing_sizes[ $size ] = $filename;
				unset( $parallel_sizes[ $size ] );
				$eio_filesystem->touch( $filename . '.processing' );
				ewwwio_debug_message( "sending off $filename via parallel/async" );
				$ewwwio_async_optimize_media->data(
					array(
						'ewwwio_id'            => ewww_image_optimizer_single_insert( $filename, 'media', $id, $size ),
						'ewwwio_attachment_id' => $id,
						'ewwwio_size'          => $size,
						'ewww_force'           => $force,
					)
				)->dispatch();
				$threads--;
				ewwwio_debug_message( 'sizes left to queue: ' . count( $parallel_sizes ) );
				$processing = true;
			}
			// In phase 2, we start checking to see what sizes are done, until they all finish.
			while ( $parallel_opt && $processing ) {
				$processing = false;
				foreach ( $processing_sizes as $size => $filename ) {
					if ( ewwwio_is_file( $filename . '.processing' ) ) {
						ewwwio_debug_message( "still processing $size" );
						$processing = true;
						continue;
					}
					$image = ewww_image_optimizer_find_already_optimized( $filename );
					unset( $processing_sizes[ $size ] );
					ewwwio_debug_message( "got results for $size size" );
				}
				if ( $processing ) {
					ewwwio_debug_message( "sleeping for $timer seconds" );
					sleep( $timer );
					$timer += $increment;
					clearstatcache();
				}
				if ( $timer > $timer_max ) {
					break;
				}
				if ( $log ) {
					ewww_image_optimizer_debug_log();
				}
			}
			if ( $timer > $timer_max ) {
				foreach ( $processing_sizes as $filename ) {
					if ( ewwwio_is_file( $filename . '.processing' ) ) {
						ewwwio_delete_file( $filename . '.processing' );
					}
				}
				$meta['processing'] = 1;
				ewwwio_debug_message( "$timer_max is up ($timer), will come back later" );
				return $meta;
			}
		} // End while().
	} // End if().
	unset( $meta['processing'] );

	global $ewww_attachment;
	$ewww_attachment['id']   = $id;
	$ewww_attachment['meta'] = $meta;
	add_filter( 'w3tc_cdn_update_attachment_metadata', 'ewww_image_optimizer_w3tc_update_files' );

	// In case we used parallel opt, $file might not be set.
	if ( empty( $file ) ) {
		$file = $file_path;
	}
	$fullsize_opt_size = ewww_image_optimizer_filesize( $file );

	// Done optimizing, do whatever you need with the attachment from here.
	do_action( 'ewww_image_optimizer_after_optimize_attachment', $id, $meta );

	if ( $fullsize_opt_size && $fullsize_opt_size < $fullsize_size && class_exists( 'Amazon_S3_And_CloudFront' ) ) {
		global $as3cf;
		if ( method_exists( $as3cf, 'wp_update_attachment_metadata' ) ) {
			ewwwio_debug_message( 'deferring to normal S3 hook' );
		} elseif ( method_exists( $as3cf, 'wp_generate_attachment_metadata' ) ) {
			$as3cf->wp_generate_attachment_metadata( $meta, $id );
			ewwwio_debug_message( 'uploading to Amazon S3' );
		}
	}
	if ( class_exists( 'S3_Uploads' ) || class_exists( 'S3_Uploads\Plugin' ) ) {
		ewww_image_optimizer_remote_push( $meta, $id );
		ewwwio_debug_message( 're-uploading to S3(_Uploads)' );
	}
	if ( class_exists( 'Windows_Azure_Helper' ) && function_exists( 'windows_azure_storage_wp_generate_attachment_metadata' ) ) {
		$meta = windows_azure_storage_wp_generate_attachment_metadata( $meta, $id );
		if ( Windows_Azure_Helper::delete_local_file() && function_exists( 'windows_azure_storage_delete_local_files' ) ) {
			windows_azure_storage_delete_local_files( $meta, $id );
		}
	}
	if ( class_exists( 'Cloudinary' ) && Cloudinary::config_get( 'api_secret' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_cloudinary' ) && ! empty( $new_image ) ) {
		try {
			$result = CloudinaryUploader::upload(
				$file,
				array( 'use_filename' => true )
			);
		} catch ( Exception $e ) {
			$error = $e->getMessage();
		}
		if ( ! empty( $error ) ) {
			ewwwio_debug_message( "Cloudinary error: $error" );
		} else {
			ewwwio_debug_message( 'successfully uploaded to Cloudinary' );
			// Register the attachment in the database as a cloudinary attachment.
			$old_url = wp_get_attachment_url( $id );
			wp_update_post(
				array(
					'ID'   => $id,
					'guid' => $result['url'],
				)
			);
			update_attached_file( $id, $result['url'] );
			$meta['cloudinary'] = true;
			$errors             = array();
			// Update the image location for the attachment.
			CloudinaryPlugin::update_image_src_all( $id, $result, $old_url, $result['url'], true, $errors );
			if ( count( $errors ) > 0 ) {
				ewwwio_debug_message( 'Cannot migrate the following posts:' );
				foreach ( $errors as $error ) {
					ewwwio_debug_message( $error );
				}
			}
		}
	}
	if ( $log ) {
		ewww_image_optimizer_debug_log();
	}
	ewwwio_memory( __FUNCTION__ );
	// Send back the updated metadata.
	return $meta;
}

/**
 * Only runs during WP/LR Sync to check if an attachment has been updated.
 *
 * @param int $id The attachment ID number.
 * @return array $meta Send the metadata back from whence it came.
 */
function ewww_image_optimizer_lr_sync_update( $id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $id ) ) {
		return;
	}
	$meta = wp_get_attachment_metadata( $id );

	list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
	if ( ewww_image_optimizer_stream_wrapped( $file_path ) || ! ewwwio_is_file( $file_path ) ) {
		ewwwio_debug_message( "bailing early since no local file or stream wrapped $file_path" );
		// Still want to fire off the optimization.
		if ( defined( 'EWWWIO_WPLR_AUTO' ) && EWWWIO_WPLR_AUTO ) {
			ewwwio_debug_message( "auto optimizing $file_path" );
			$meta = ewww_image_optimizer_resize_from_meta_data( $meta, $id );
		}
		return;
	}
	ewwwio_debug_message( "retrieved file path for lr sync image: $file_path" );
	$type            = ewww_image_optimizer_mimetype( $file_path, 'i' );
	$supported_types = array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'application/pdf',
		'image/svg+xml',
	);
	if ( ! in_array( $type, $supported_types, true ) ) {
		ewwwio_debug_message( "mimetype not supported: $id" );
		return;
	}

	// Get a list of all the image files optimized for this attachment.
	global $wpdb;
	$optimized_images = $wpdb->get_results( $wpdb->prepare( "SELECT id,path,image_size FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'media' AND image_size <> 0 ORDER BY orig_size DESC", $id ), ARRAY_A );
	if ( ewww_image_optimizer_iterable( $optimized_images ) ) {
		foreach ( $optimized_images as $optimized_image ) {
			$image_path = ewww_image_optimizer_absolutize_path( $optimized_image['path'] );
			$file_size  = ewww_image_optimizer_filesize( $image_path );
			if ( $file_size === (int) $optimized_image['image_size'] ) {
				ewwwio_debug_message( "not resetting $image_path for lr sync" );
				continue;
			}
			if ( ewwwio_is_file( $image_path . '.webp' ) ) {
				ewwwio_debug_message( "removing WebP version of $image_path for lr sync" );
				ewwwio_delete_file( $image_path . '.webp' );
			}
			$wpdb->update(
				$wpdb->ewwwio_images,
				array(
					'image_size' => 0,
				),
				array(
					'id' => $optimized_image['id'],
				)
			);
		}
	}
	if ( defined( 'EWWWIO_WPLR_AUTO' ) && EWWWIO_WPLR_AUTO ) {
		ewwwio_debug_message( "auto optimizing $file_path" );
		$meta = ewww_image_optimizer_resize_from_meta_data( $meta, $id );
		return;
	}
	ewwwio_debug_message( 'no auto-opt, will show notice' );
	update_option( 'ewww_image_optimizer_lr_sync', true, false );
}

/**
 * Check to see if Shield's location lock option is enabled.
 *
 * @return bool True if the IP location lock is enabled.
 */
function ewww_image_optimizer_detect_wpsf_location_lock() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( function_exists( 'icwp_wpsf_init' ) ) {
		ewwwio_debug_message( 'Shield Security detected' );
		$shield_user_man = ewww_image_optimizer_get_option( 'icwp_wpsf_user_management_options' );
		if ( ewww_image_optimizer_function_exists( 'print_r' ) ) {
			ewwwio_debug_message( print_r( $shield_user_man, true ) );
		}
		if ( ! empty( $shield_user_man['session_lock_location'] ) && 'Y' === $shield_user_man['session_lock_location'] ) {
			return true;
		}
	}
	return false;
}

/**
 * Parse image paths for WP Offload S3.
 *
 * Adds WebP derivatives so that they can be uploaded.
 *
 * @param array $paths The image paths currently queued for upload.
 * @param int   $id The ID number of the image in the database.
 * @return array Attachment meta field.
 */
function ewww_image_optimizer_as3cf_attachment_file_paths( $paths, $id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	foreach ( $paths as $size => $path ) {
		ewwwio_debug_message( "checking $path for WebP in as3cf queue" );
		if ( is_string( $path ) && ewwwio_is_file( $path . '.webp' ) ) {
			$paths[ $size . '-webp' ] = $path . '.webp';
			ewwwio_debug_message( "added $path.webp to as3cf queue" );
		} elseif (
			// WOM(pro) is downloading from bucket to server, WebP is enabled, and the local/server file does not exist.
			! empty( $_REQUEST['action'] ) && // phpcs:ignore WordPress.Security.NonceVerification
			'download' === $_REQUEST['action'] && // phpcs:ignore WordPress.Security.NonceVerification
			ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) &&
			is_string( $path ) &&
			! ewwwio_is_file( $path )
		) {
			if ( ! isset( $optimized ) ) {
				global $wpdb;
				$optimized = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'media' AND image_size <> 0 LIMIT 1", $id ) );
			}
			if ( $optimized ) {
				$paths[ $size . '-webp' ] = $path . '.webp';
				ewwwio_debug_message( "added $path.webp to as3cf queue (for potential local copy)" );
			}
		}
	}
	return $paths;
}

/**
 * Cleanup remote storage for WP Offload S3.
 *
 * Checks for WebP derivatives so that they can be removed.
 *
 * @param array $paths The image paths currently queued for deletion.
 * @param int   $id The ID number of the image in the database.
 * @return array A list of paths to remove.
 */
function ewww_image_optimizer_as3cf_remove_attachment_file_paths( $paths, $id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	foreach ( $paths as $path ) {
		if ( is_string( $path ) ) {
			$paths[] = $path . '.webp';
			ewwwio_debug_message( "added $path.webp to as3cf deletion queue" );
		}
	}
	return $paths;
}

/**
 * Fixes the ContentType for WebP images because WP mimetype detection stinks.
 *
 * @param array $args The parameters to be used for the S3 upload.
 * @return array The same parameters with ContentType corrected.
 */
function ewww_image_optimizer_as3cf_object_meta( $args ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! empty( $args['SourceFile'] ) && ewwwio_is_file( $args['SourceFile'] ) && empty( $args['ContentType'] ) && false !== strpos( $args['SourceFile'], '.webp' ) ) {
		$args['ContentType'] = ewww_image_optimizer_quick_mimetype( $args['SourceFile'] );
	}
	return $args;
}

/**
 * Update the attachment's meta data after being converted.
 *
 * @global object $wpdb
 *
 * @param array $meta Attachment metadata.
 * @param int   $id Attachment ID number.
 */
function ewww_image_optimizer_update_attachment( $meta, $id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	// Update the file location in the post metadata based on the new path stored in the attachment metadata.
	update_attached_file( $id, $meta['file'] );
	$guid = wp_get_attachment_url( $id );
	if ( empty( $meta['real_orig_file'] ) ) {
		$old_guid = dirname( $guid ) . '/' . basename( $meta['orig_file'] );
	} else {
		$old_guid = dirname( $guid ) . '/' . basename( $meta['real_orig_file'] );
		unset( $meta['real_orig_file'] );
	}
	// Construct the new guid based on the filename from the attachment metadata.
	ewwwio_debug_message( "old guid: $old_guid" );
	ewwwio_debug_message( "new guid: $guid" );
	if ( substr( $old_guid, -1 ) === '/' || substr( $guid, -1 ) === '/' ) {
		ewwwio_debug_message( 'could not obtain full url for current and previous image, bailing' );
		return $meta;
	}
	// Retrieve any posts that link the image.
	$esql = $wpdb->prepare( "SELECT ID, post_content FROM $wpdb->posts WHERE post_content LIKE %s", '%' . $wpdb->esc_like( $old_guid ) . '%' );
	ewwwio_debug_message( "using query: $esql" );
	// While there are posts to process.
	$rows = $wpdb->get_results( $esql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	if ( ewww_image_optimizer_iterable( $rows ) ) {
		foreach ( $rows as $row ) {
			// Replace all occurences of the old guid with the new guid.
			$post_content = str_replace( $old_guid, $guid, $row['post_content'] );
			ewwwio_debug_message( "replacing $old_guid with $guid in post " . $row['ID'] );
			// Send the updated content back to the database.
			$wpdb->update(
				$wpdb->posts,
				array(
					'post_content' => $post_content,
				),
				array(
					'ID' => $row['ID'],
				)
			);
		}
	}
	if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
		// For each resized version.
		foreach ( $meta['sizes'] as $size => $data ) {
			// If the resize was converted.
			if ( isset( $data['converted'] ) ) {
				// Generate the url for the old image.
				if ( empty( $data['real_orig_file'] ) ) {
					$old_sguid = dirname( $old_guid ) . '/' . basename( $data['orig_file'] );
				} else {
					$old_sguid = dirname( $old_guid ) . '/' . basename( $data['real_orig_file'] );
					unset( $meta['sizes'][ $size ]['real_orig_file'] );
				}
				ewwwio_debug_message( "processing: $size" );
				ewwwio_debug_message( "old sguid: $old_sguid" );
				// Generate the url for the new image.
				$sguid = dirname( $old_guid ) . '/' . basename( $data['file'] );
				ewwwio_debug_message( "new sguid: $sguid" );
				if ( substr( $old_sguid, -1 ) === '/' || substr( $sguid, -1 ) === '/' ) {
					ewwwio_debug_message( 'could not obtain full url for current and previous resized image, bailing' );
					continue;
				}
				// Retrieve any posts that link the resize.
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content FROM $wpdb->posts WHERE post_content LIKE %s", '%' . $wpdb->esc_like( $old_sguid ) . '%' ), ARRAY_A );
				// While there are posts to process.
				if ( ewww_image_optimizer_iterable( $rows ) ) {
					foreach ( $rows as $row ) {
						// Replace all occurences of the old guid with the new guid.
						$post_content = str_replace( $old_sguid, $sguid, $row['post_content'] );
						ewwwio_debug_message( "replacing $old_sguid with $sguid in post " . $row['ID'] );
						// Send the updated content back to the database.
						$wpdb->update(
							$wpdb->posts,
							array(
								'post_content' => $post_content,
							),
							array(
								'ID' => $row['ID'],
							)
						);
					}
				}
			} // End if().
		} // End foreach().
	} // End if().
	if ( preg_match( '/\.jpg$/i', basename( $meta['file'] ) ) ) {
		$mime = 'image/jpeg';
	}
	if ( preg_match( '/\.png$/i', basename( $meta['file'] ) ) ) {
		$mime = 'image/png';
	}
	if ( preg_match( '/\.gif$/i', basename( $meta['file'] ) ) ) {
		$mime = 'image/gif';
	}
	if ( preg_match( '/\.svg$/i', basename( $meta['file'] ) ) ) {
		$mime = 'image/svg+xml';
	}
	// Update the attachment post with the new mimetype and id.
	wp_update_post(
		array(
			'ID'             => $id,
			'post_mime_type' => $mime,
		)
	);
	return $meta;
}

/**
 * Retrieves the path of an attachment via the $id and the $meta.
 *
 * @param array  $meta The attachment metadata.
 * @param int    $id The attachment ID number.
 * @param string $file Optional. Path relative to the uploads folder. Default ''.
 * @param bool   $refresh_cache Optional. True to flush cache prior to fetching path. Default true.
 * @return array {
 *     Information about the file.
 *
 *     @type string The full path to the image.
 *     @type string The path to the uploads folder.
 * }
 */
function ewww_image_optimizer_attachment_path( $meta, $id, $file = '', $refresh_cache = true ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );

	// Retrieve the location of the WordPress upload folder.
	$upload_dir  = wp_upload_dir( null, false, $refresh_cache );
	$upload_path = trailingslashit( $upload_dir['basedir'] );
	if ( ! $file ) {
		$file = get_post_meta( $id, '_wp_attached_file', true );
	} else {
		ewwwio_debug_message( 'using prefetched _wp_attached_file' );
	}
	$file_path          = ( 0 !== strpos( $file, '/' ) && ! preg_match( '|^.:\\\|', $file ) ? $upload_path . $file : $file );
	$filtered_file_path = apply_filters( 'get_attached_file', $file_path, $id );
	ewwwio_debug_message( "WP (filtered) thinks the file is at: $filtered_file_path" );
	if (
		(
			! ewww_image_optimizer_stream_wrapped( $filtered_file_path ) ||
			ewww_image_optimizer_stream_wrapper_exists()
		)
		&& ewwwio_is_file( $filtered_file_path )
	) {
		return array( str_replace( '//_imsgalleries/', '/_imsgalleries/', $filtered_file_path ), $upload_path );
	}
	ewwwio_debug_message( "WP (unfiltered) thinks the file is at: $file_path" );
	if (
		(
			! ewww_image_optimizer_stream_wrapped( $file_path ) ||
			ewww_image_optimizer_stream_wrapper_exists()
		)
		&& ewwwio_is_file( $file_path )
	) {
		return array( str_replace( '//_imsgalleries/', '/_imsgalleries/', $file_path ), $upload_path );
	}
	if ( 'ims_image' === get_post_type( $id ) && is_array( $meta ) && ! empty( $meta['file'] ) ) {
		ewwwio_debug_message( "finding path for IMS image: $id " );
		if ( is_dir( $file_path ) && ewwwio_is_file( $file_path . $meta['file'] ) ) {
			// Generate the absolute path.
			$file_path   = $file_path . $meta['file'];
			$upload_path = ewww_image_optimizer_upload_path( $file_path, $upload_path );
			ewwwio_debug_message( "found path for IMS image: $file_path" );
		} elseif ( ewwwio_is_file( $meta['file'] ) ) {
			$file_path   = $meta['file'];
			$upload_path = ewww_image_optimizer_upload_path( $file_path, $upload_path );
			ewwwio_debug_message( "found path for IMS image: $file_path" );
		} else {
			$upload_path = trailingslashit( WP_CONTENT_DIR );
			$file_path   = $upload_path . ltrim( $meta['file'], '/' );
			ewwwio_debug_message( "checking path for IMS image: $file_path" );
			if ( ! file_exists( $file_path ) ) {
				$file_path = '';
			}
		}
		return array( $file_path, $upload_path );
	}
	if ( is_array( $meta ) && ! empty( $meta['file'] ) ) {
		$file_path = $meta['file'];
		if ( ewww_image_optimizer_stream_wrapped( $file_path ) && ! ewww_image_optimizer_stream_wrapper_exists() ) {
			return array( '', $upload_path );
		}
		ewwwio_debug_message( "looking for file at $file_path" );
		if ( ewwwio_is_file( $file_path ) ) {
			return array( $file_path, $upload_path );
		}
		$file_path = trailingslashit( $upload_path ) . $file_path;
		ewwwio_debug_message( "that did not work, try it with the upload_dir: $file_path" );
		if ( ewwwio_is_file( $file_path ) ) {
			return array( $file_path, $upload_path );
		}
		$upload_path = trailingslashit( WP_CONTENT_DIR ) . 'uploads/';
		$file_path   = $upload_path . $meta['file'];
		ewwwio_debug_message( "one last shot, using the wp-content/ constant: $file_path" );
		if ( ewwwio_is_file( $file_path ) ) {
			return array( $file_path, $upload_path );
		}
	}
	ewwwio_memory( __FUNCTION__ );
	return array( '', $upload_path );
}

/**
 * Removes parent folders to create a relative path.
 *
 * Replaces either ABSPATH, WP_CONTENT_DIR, or EWWW_IMAGE_OPTIMIZER_RELATIVE_FOLDER with the literal
 * string name of the applicable constant. For example: /var/www/wp-content/uploads/test.jpg becomes
 * ABSPATHwp-content/uploads/test.jpg.
 *
 * @param string $file The filename to mangle.
 * @return string The filename with parent folders replaced by a constant name.
 */
function ewww_image_optimizer_relativize_path( $file ) {
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_RELATIVE' ) || ! EWWW_IMAGE_OPTIMIZER_RELATIVE ) {
		return $file;
	}
	if ( defined( 'EWWW_IMAGE_OPTIMIZER_RELATIVE_FOLDER' ) && EWWW_IMAGE_OPTIMIZER_RELATIVE_FOLDER && strpos( $file, EWWW_IMAGE_OPTIMIZER_RELATIVE_FOLDER ) === 0 ) {
		return str_replace( EWWW_IMAGE_OPTIMIZER_RELATIVE_FOLDER, 'EWWW_IMAGE_OPTIMIZER_RELATIVE_FOLDER', $file );
	}
	if ( strpos( $file, ABSPATH ) === 0 ) {
		return str_replace( ABSPATH, 'ABSPATH', $file );
	}
	if ( defined( 'WP_CONTENT_DIR' ) && WP_CONTENT_DIR && strpos( $file, WP_CONTENT_DIR ) === 0 ) {
		return str_replace( WP_CONTENT_DIR, 'WP_CONTENT_DIR', $file );
	}
	return $file;
}

/**
 * Replaces constant names with their actual values to recreate an absolute path.
 *
 * Replaces the literal strings 'ABSPATH', 'WP_CONTENT_DIR', or
 * 'EWWW_IMAGE_OPTIMIZER_RELATIVE_FOLDER' with the actual value of the constant contained within
 * the file path.
 *
 * @param string $file The filename to parse.
 * @return string The full filename with parent folders reinserted.
 */
function ewww_image_optimizer_absolutize_path( $file ) {
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_RELATIVE' ) ) {
		return $file;
	}
	if ( defined( 'EWWW_IMAGE_OPTIMIZER_RELATIVE_FOLDER' ) && EWWW_IMAGE_OPTIMIZER_RELATIVE_FOLDER && strpos( $file, 'EWWW_IMAGE_OPTIMIZER_RELATIVE_FOLDER' ) === 0 ) {
		return str_replace( 'EWWW_IMAGE_OPTIMIZER_RELATIVE_FOLDER', EWWW_IMAGE_OPTIMIZER_RELATIVE_FOLDER, $file );
	}
	if ( strpos( $file, 'ABSPATH' ) === 0 ) {
		return str_replace( 'ABSPATH', ABSPATH, $file );
	}
	if ( defined( 'WP_CONTENT_DIR' ) && WP_CONTENT_DIR && strpos( $file, 'WP_CONTENT_DIR' ) === 0 ) {
		return str_replace( 'WP_CONTENT_DIR', WP_CONTENT_DIR, $file );
	}
	return $file;
}

/**
 * Takes a file and upload folder, and makes sure that the file is within the folder.
 *
 * Used for path replacement with async/parallel processing, since security plugins can block
 * POSTing of full paths.
 *
 * @param string $file Name of the file.
 * @param string $upload_path Location of the upload directory.
 * @return string The upload path or an empty string if the file is outside the uploads folder.
 */
function ewww_image_optimizer_upload_path( $file, $upload_path ) {
	if ( strpos( $file, $upload_path ) === 0 ) {
		return $upload_path;
	} else {
		return '';
	}
}

/**
 * Takes a human-readable size, and generates an approximate byte-size.
 *
 * @param string $formatted A human-readable file size.
 * @return int The approximated filesize.
 */
function ewww_image_optimizer_size_unformat( $formatted ) {
	$size_parts = explode( '&nbsp;', $formatted );
	switch ( $size_parts[1] ) {
		case 'B':
			return intval( $size_parts[0] );
		case 'kB':
			return intval( $size_parts[0] * 1024 );
		case 'MB':
			return intval( $size_parts[0] * 1048576 );
		case 'GB':
			return intval( $size_parts[0] * 1073741824 );
		case 'TB':
			return intval( $size_parts[0] * 1099511627776 );
		default:
			return 0;
	}
}

/**
 * Generate a unique filename for a converted image.
 *
 * @param string $file The filename to test for uniqueness.
 * @param string $fileext An iterator to append to the base filename, starts empty usually.
 * @return array {
 *     Filename information.
 *
 *     @type string A unique filename for converting an image.
 *     @type int|string The iterator used for uniqueness.
 * }
 */
function ewww_image_optimizer_unique_filename( $file, $fileext ) {
	// Strip the file extension.
	$filename = preg_replace( '/\.\w+$/', '', $file );
	if ( ! ewwwio_is_file( $filename . $fileext ) ) {
		return array( $filename . $fileext, '' );
	}
	// Set the increment to 1 (but allow the user to override it).
	$filenum = apply_filters( 'ewww_image_optimizer_converted_filename_suffix', 1 );
	// But it must be only letters, numbers, or underscores.
	$filenum = ( preg_match( '/^[\w\d]+$/', $filenum ) ? $filenum : 1 );
	$suffix  = ( ! empty( $filenum ) ? '-' . $filenum : '' );
	// While a file exists with the current increment.
	while ( file_exists( $filename . $suffix . $fileext ) ) {
		// Increment the increment...
		$filenum++;
		$suffix = '-' . $filenum;
	}
	// All done, let's reconstruct the filename.
	ewwwio_memory( __FUNCTION__ );
	return array( $filename . $suffix . $fileext, $filenum );
}

/**
 * Get mimetype based on file extension instead of file contents when speed outweighs accuracy.
 *
 * @param string $path The name of the file.
 * @return string|bool The mime type based on the extension or false.
 */
function ewww_image_optimizer_quick_mimetype( $path ) {
	$pathextension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	switch ( $pathextension ) {
		case 'jpg':
		case 'jpeg':
		case 'jpe':
			return 'image/jpeg';
		case 'png':
			return 'image/png';
		case 'gif':
			return 'image/gif';
		case 'webp':
			return 'image/webp';
		case 'pdf':
			return 'application/pdf';
		case 'svg':
			return 'image/svg+xml';
		default:
			if ( empty( $pathextension ) && ! ewww_image_optimizer_stream_wrapped( $path ) && ewwwio_is_file( $path ) ) {
				return ewww_image_optimizer_mimetype( $path, 'i' );
			}
			return false;
	}
}

/**
 * Check a PNG to see if it has transparency.
 *
 * @param string $filename The name of the PNG file.
 * @return bool True if transparency is found.
 */
function ewww_image_optimizer_png_alpha( $filename ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! ewwwio_is_file( $filename ) ) {
		return false;
	}
	if ( false !== strpos( $filename, '../' ) ) {
		return false;
	}
	list( $width, $height ) = wp_getimagesize( $filename );
	ewwwio_debug_message( "image dimensions: $width x $height" );
	if ( ! ewww_image_optimizer_gd_support() || ! ewwwio_check_memory_available( ( $width * $height ) * 4.8 ) ) { // 4.8 = 24-bit or 3 bytes per pixel multiplied by a factor of 1.6 for extra wiggle room.
		global $eio_filesystem;
		ewwwio_get_filesystem();
		$file_contents = $eio_filesystem->get_contents( $filename );
		// Determine what color type is stored in the file.
		$color_type = ord( substr( $file_contents, 25, 1 ) );
		unset( $file_contents );
		ewwwio_debug_message( "color type: $color_type" );
		if ( 4 === $color_type || 6 === $color_type ) {
			ewwwio_debug_message( 'transparency found' );
			return true;
		}
	} elseif ( ewww_image_optimizer_gd_support() ) {
		$image = imagecreatefrompng( $filename );
		if ( imagecolortransparent( $image ) >= 0 ) {
			ewwwio_debug_message( 'transparency found' );
			return true;
		}
		ewwwio_debug_message( 'preparing to scan image' );
		for ( $y = 0; $y < $height; $y++ ) {
			for ( $x = 0; $x < $width; $x++ ) {
				$color = imagecolorat( $image, $x, $y );
				$rgb   = imagecolorsforindex( $image, $color );
				if ( $rgb['alpha'] > 0 ) {
					ewwwio_debug_message( 'transparency found' );
					return true;
				}
			}
		}
	}
	ewwwio_debug_message( 'no transparency' );
	ewwwio_memory( __FUNCTION__ );
	return false;
}

/**
 * Check the submitted GIF to see if it is animated.
 *
 * @param string $filename Name of the GIF to test for animation.
 * @return bool True if animation found.
 */
function ewww_image_optimizer_is_animated( $filename ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! ewwwio_is_file( $filename ) ) {
		return false;
	}
	// If we can't open the file in read-only buffered mode.
	$fh = fopen( $filename, 'rb' );
	if ( ! $fh ) {
		return false;
	}
	$count = 0;
	// We read through the file til we reach the end of the file, or we've found at least 2 frame headers.
	while ( ! feof( $fh ) && $count < 2 ) {
		$chunk  = fread( $fh, 1024 * 100 ); // Read 100kb at a time.
		$count += preg_match_all( '#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches );
	}
	fclose( $fh );
	ewwwio_debug_message( "scanned GIF and found $count frames" );
	ewwwio_memory( __FUNCTION__ );
	return $count > 1;
}

/**
 * Check the submitted PNG to see if it is animated. Thanks @GregOriol!
 *
 * @param string $filename Name of the PNG to test for animation.
 * @return bool True if animation found.
 */
function ewww_image_optimizer_is_animated_png( $filename ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$apng = false;
	if ( ! ewwwio_is_file( $filename ) ) {
		return false;
	}
	// If we can't open the file in read-only buffered mode.
	$fh = fopen( $filename, 'rb' );
	if ( ! $fh ) {
		return false;
	}
	$previousdata = '';
	// We read through the file til we reach the end of the file, or we've found an acTL or IDAT chunk.
	while ( ! feof( $fh ) ) {
		$data = fread( $fh, 1024 ); // Read 1kb at a time.
		if ( false !== strpos( $data, 'acTL' ) ) {
			ewwwio_debug_message( 'found acTL chunk (animated) in PNG' );
			$apng = true;
			break;
		} elseif ( false !== strpos( $previousdata . $data, 'acTL' ) ) {
			ewwwio_debug_message( 'found acTL chunk (animated) in PNG' );
			$apng = true;
			break;
		} elseif ( false !== strpos( $data, 'IDAT' ) ) {
			ewwwio_debug_message( 'found IDAT, but no acTL (animated) chunk in PNG' );
			break;
		} elseif ( false !== strpos( $previousdata . $data, 'IDAT' ) ) {
			ewwwio_debug_message( 'found IDAT, but no acTL (animated) chunk in PNG' );
			break;
		}
		$previousdata = $data;
	}
	fclose( $fh );
	return $apng;
}

/**
 * Check a JPG to see if it uses the CMYK color space.
 *
 * @param string $filename Name of the JPG to test.
 * @return bool True if CMYK, false otherwise.
 */
function ewww_image_optimizer_is_cmyk( $filename ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ewww_image_optimizer_imagick_support() ) {
		$image = new Imagick( $filename );
		$color = $image->getImageColorspace();
		ewwwio_debug_message( "color space is $color" );
		$image->destroy();
		if ( Imagick::COLORSPACE_CMYK === $color ) {
			return true;
		}
	} elseif ( ewww_image_optimizer_gd_support() ) {
		$info = getimagesize( $filename );
		if ( ! empty( $info['channels'] ) ) {
			ewwwio_debug_message( "channel count is {$info['channels']}" );
			if ( 4 === (int) $info['channels'] ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Count how many sizes are in the metadata, accounting for those with duplicate dimensions.
 *
 * @param array $sizes A list of resize information from an attachment.
 * @return int The number of sizes found.
 */
function ewww_image_optimizer_resize_count( $sizes ) {
	if ( empty( $sizes ) || ! is_array( $sizes ) ) {
		return 0;
	}
	$size_count = 0;
	$processed  = array();
	foreach ( $sizes as $size => $data ) {
		if ( strpos( $size, 'webp' ) === 0 ) {
			continue;
		}
		if ( empty( $data['file'] ) ) {
			continue;
		}
		// Check through all the sizes we've processed so far.
		foreach ( $processed as $proc => $scan ) {
			// If a previous resize had identical dimensions.
			if ( $scan['height'] === $data['height'] && $scan['width'] === $data['width'] ) {
				continue( 2 );
			}
		}
		// If this is a unique size.
		$size_count++;
		// Sore info on the sizes we've processed, so we can check the list for duplicate sizes.
		$processed[ $size ]['width']  = $data['width'];
		$processed[ $size ]['height'] = $data['height'];
	}
	return $size_count;
}

/**
 * Add column header for optimizer results in the media library listing.
 *
 * @param array $columns A list of columns in the media library.
 * @return array The new list of columns.
 */
function ewww_image_optimizer_columns( $columns ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$columns['ewww-image-optimizer'] = esc_html__( 'Image Optimizer', 'ewww-image-optimizer' );
	ewwwio_memory( __FUNCTION__ );
	return $columns;
}

/**
 * Print column data for optimizer results in the media library.
 *
 * @global object $wpdb
 *
 * @param string $column_name The name of the column being displayed.
 * @param int    $id The attachment ID number.
 * @param array  $meta Optional. The attachment metadata. Default null.
 * @return string The data that would normally be output directly by the custom_column function.
 */
function ewww_image_optimizer_custom_column_capture( $column_name, $id, $meta = null ) {
	ob_start();
	ewww_image_optimizer_custom_column( $column_name, $id, $meta );
	return ob_get_clean();
}

/**
 * Print column data for optimizer results in the media library.
 *
 * @global object $wpdb
 *
 * @param string $column_name The name of the column being displayed.
 * @param int    $id The attachment ID number.
 * @param array  $meta Optional. The attachment metadata. Default null.
 */
function ewww_image_optimizer_custom_column( $column_name, $id, $meta = null ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Once we get to the EWWW IO custom column.
	if ( 'ewww-image-optimizer' === $column_name ) {
		$id = (int) $id;
		if ( is_null( $meta ) ) {
			// Retrieve the metadata.
			$meta = wp_get_attachment_metadata( $id );
		}
		echo '<div id="ewww-media-status-' . (int) $id . '" class="ewww-media-status" data-id="' . (int) $id . '">';
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ewww_image_optimizer_function_exists( 'print_r' ) ) {
			$print_meta   = print_r( $meta, true );
			$print_meta   = preg_replace( array( '/ /', '/\n+/' ), array( '&nbsp;', '<br />' ), $print_meta );
			$debug_button = __( 'Show Metadata', 'ewww-image-optimizer' );
			echo "<button type='button' class='ewww-show-debug-meta button button-secondary' data-id='" . (int) $id . "'>" . esc_html( $debug_button ) . "</button><div id='ewww-debug-meta-" . (int) $id . "' style='font-size: 10px;padding: 10px;margin:3px -10px 10px;line-height: 1.1em;display: none;'>" . wp_kses_post( $print_meta ) . '</div>';
		}
		$ewww_cdn = false;
		if ( is_array( $meta ) && ! empty( $meta['file'] ) && false !== strpos( $meta['file'], 'https://images-na.ssl-images-amazon.com' ) ) {
			echo esc_html__( 'Amazon-hosted image', 'ewww-image-optimizer' ) . '</div>';
			return;
		}
		if ( is_array( $meta ) && ! empty( $meta['cloudinary'] ) ) {
			echo esc_html__( 'Cloudinary image', 'ewww-image-optimizer' ) . '</div>';
			return;
		}
		if ( is_array( $meta ) & class_exists( 'WindowsAzureStorageUtil' ) && ! empty( $meta['url'] ) ) {
			echo '<div>' . esc_html__( 'Azure Storage image', 'ewww-image-optimizer' ) . '</div>';
			$ewww_cdn = true;
		}
		if ( is_array( $meta ) && class_exists( 'Amazon_S3_And_CloudFront' ) && preg_match( '/^(http|s3|gs)\w*:/', get_attached_file( $id ) ) ) {
			echo '<div>' . esc_html__( 'Offloaded Media', 'ewww-image-optimizer' ) . '</div>';
			$ewww_cdn = true;
		}
		if ( is_array( $meta ) && class_exists( 'S3_Uploads' ) && preg_match( '/^(http|s3|gs)\w*:/', get_attached_file( $id ) ) ) {
			echo '<div>' . esc_html__( 'Amazon S3 image', 'ewww-image-optimizer' ) . '</div>';
			$ewww_cdn = true;
		}
		if ( is_array( $meta ) && class_exists( 'S3_Uploads\Plugin' ) && preg_match( '/^(http|s3|gs)\w*:/', get_attached_file( $id ) ) ) {
			echo '<div>' . esc_html__( 'Amazon S3 image', 'ewww-image-optimizer' ) . '</div>';
			$ewww_cdn = true;
		}
		if ( is_array( $meta ) & class_exists( 'wpCloud\StatelessMedia\EWWW' ) && ! empty( $meta['gs_link'] ) ) {
			echo '<div>' . esc_html__( 'WP Stateless image', 'ewww-image-optimizer' ) . '</div>';
			$ewww_cdn = true;
		}
		list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
		if ( is_array( $meta ) & function_exists( 'ilab_get_image_sizes' ) && ! empty( $meta['s3'] ) && empty( $file_path ) ) {
			echo esc_html__( 'Media Cloud image', 'ewww-image-optimizer' ) . '</div>';
			return;
		}
		// If the file does not exist.
		if ( empty( $file_path ) && ! $ewww_cdn ) {
			echo esc_html__( 'Could not retrieve file path.', 'ewww-image-optimizer' ) . '</div>';
			return;
		}
		if ( is_array( $meta ) && ( ! empty( $meta['ewww_image_optimizer'] ) || ! empty( $meta['converted'] ) ) ) {
			$meta = ewww_image_optimizer_migrate_meta_to_db( $id, $meta );
		}
		$msg          = '';
		$convert_desc = '';
		$convert_link = '';
		if ( $ewww_cdn ) {
			$type = get_post_mime_type( $id );
		} else {
			// Retrieve the mimetype of the attachment.
			$type = ewww_image_optimizer_mimetype( $file_path, 'i' );
			// Get a human readable filesize.
			$file_size = ewww_image_optimizer_size_format( filesize( $file_path ) );
		}
		if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_JPEGTRAN' ) ) {
			ewww_image_optimizer_tool_init();
			ewww_image_optimizer_notice_utils( 'quiet' );
		}
		$skip = ewww_image_optimizer_skip_tools();
		// Run the appropriate code based on the mimetype.
		switch ( $type ) {
			case 'image/jpeg':
				// If jpegtran is missing and should not be skipped.
				if ( ! $skip['jpegtran'] && defined( 'EWWW_IMAGE_OPTIMIZER_JPEGTRAN' ) && ! EWWW_IMAGE_OPTIMIZER_JPEGTRAN && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_dismiss_exec_notice' ) ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: name of a tool like jpegtran */
						__( '%s is missing', 'ewww-image-optimizer' ),
						'<em>jpegtran</em>'
					) . '</div>';
				} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: JPG, PNG, GIF, or PDF */
						__( '%s compression disabled', 'ewww-image-optimizer' ),
						'JPG'
					) . '</div>';
				} else {
					$convert_link = __( 'JPG to PNG', 'ewww-image-optimizer' );
					$convert_desc = __( 'WARNING: Removes metadata. Requires GD or ImageMagick. PNG is generally much better than JPG for logos and other images with a limited range of colors.', 'ewww-image-optimizer' );
				}
				break;
			case 'image/png':
				// If pngout and optipng are missing and should not be skipped.
				if ( ! $skip['optipng'] && ! EWWW_IMAGE_OPTIMIZER_OPTIPNG ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: name of a tool like jpegtran */
						__( '%s is missing', 'ewww-image-optimizer' ),
						'<em>optipng</em>'
					) . '</div>';
				} elseif ( ! $skip['pngout'] && ! EWWW_IMAGE_OPTIMIZER_PNGOUT ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: name of a tool like jpegtran */
						__( '%s is missing', 'ewww-image-optimizer' ),
						'<em>pngout</em>'
					) . '</div>';
				} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: JPG, PNG, GIF, or PDF */
						__( '%s compression disabled', 'ewww-image-optimizer' ),
						'PNG'
					) . '</div>';
				} else {
					$convert_link = __( 'PNG to JPG', 'ewww-image-optimizer' );
					$convert_desc = __( 'WARNING: This is not a lossless conversion and requires GD or ImageMagick. JPG is much better than PNG for photographic use because it compresses the image and discards data. Transparent images will only be converted if a background color has been set.', 'ewww-image-optimizer' );
				}
				break;
			case 'image/gif':
				// If gifsicle is missing and should not be skipped.
				if ( ! $skip['gifsicle'] && ! EWWW_IMAGE_OPTIMIZER_GIFSICLE ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: name of a tool like jpegtran */
						__( '%s is missing', 'ewww-image-optimizer' ),
						'<em>gifsicle</em>'
					) . '</div>';
				} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: JPG, PNG, GIF, or PDF */
						__( '%s compression disabled', 'ewww-image-optimizer' ),
						'GIF'
					) . '</div>';
				} else {
					$convert_link = __( 'GIF to PNG', 'ewww-image-optimizer' );
					$convert_desc = __( 'PNG is generally better than GIF, but does not support animation. Animated images will not be converted.', 'ewww-image-optimizer' );
				}
				break;
			case 'application/pdf':
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: JPG, PNG, GIF, or PDF */
						esc_html__( '%s compression disabled', 'ewww-image-optimizer' ),
						'PDF'
					) . '</div>';
				} else {
					$convert_desc = '';
				}
				break;
			case 'image/svg+xml':
				if ( ! $skip['svgcleaner'] && ! EWWW_IMAGE_OPTIMIZER_SVGCLEANER ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: name of a tool like jpegtran */
						__( '%s is missing', 'ewww-image-optimizer' ),
						'<em>svgcleaner</em>'
					) . '</div>';
				} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_svg_level' ) ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: JPG, PNG, GIF, PDF or SVG */
						__( '%s compression disabled', 'ewww-image-optimizer' ),
						'SVG'
					) . '</div>';
				}
				break;
			case 'image/webp':
				if ( ! $ewww_cdn && ewwwio_is_file( $file_path ) ) {
					$webp_size = ewww_image_optimizer_filesize( $file_path );
					// Get a human readable filesize.
					$webp_size = ewww_image_optimizer_size_format( $webp_size );
					echo '<div>WebP: ' . esc_html( $webp_size ) . '</div>';
				}
				return;
				break;
			default:
				// Not a supported mimetype.
				$msg = '<div>' . esc_html__( 'Unsupported file type', 'ewww-image-optimizer' ) . '</div>';
		} // End switch().
		$compression_level = ewww_image_optimizer_get_level( $type );
		if ( ! empty( $msg ) ) {
			if ( ewww_image_optimizer_easy_active() ) {
				echo '<div>' . esc_html__( 'Easy IO enabled', 'ewww-image-optimizer' );
				ewwwio_help_link( 'https://docs.ewww.io/article/96-easy-io-is-it-working', '5f871dd2c9e77c0016217c4e' );
				echo '</div>';
				return;
			}
			echo wp_kses_post( $msg ) . '</div>';
			return;
		}
		$ewww_manual_nonce = wp_create_nonce( 'ewww-manual' );
		global $wpdb;
		$in_progress      = false;
		$migrated         = false;
		$optimized_images = false;
		$backup_available = false;
		$file_parts       = pathinfo( $file_path );
		$basename         = $file_parts['filename'];
		$action_id        = ewww_image_optimizer_get_primary_wpml_id( $id );
		// If necessary, use get_post_meta( $post_id, '_wp_attachment_backup_sizes', true ); to only use basename for edited attachments, but that requires extra queries, so kiss for now.
		if ( $ewww_cdn ) {
			if ( ewww_image_optimizer_image_is_pending( $action_id, 'media-async' ) ) {
				echo '<div>' . esc_html__( 'In Progress', 'ewww-image-optimizer' ) . '</div>';
				$in_progress = true;
			}
			if ( ! $in_progress ) {
				$optimized_images = $wpdb->get_results( $wpdb->prepare( "SELECT image_size,orig_size,resize,converted,level,backup,updated FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'media' AND image_size <> 0 ORDER BY orig_size DESC", $id ), ARRAY_A );
				if ( ! $optimized_images ) {
					// Attempt migration, but only if the original image is in the db, $migrated will be metadata on success, false on failure.
					$migrated = ewww_image_optimizer_migrate_meta_to_db( $id, $meta, true );
				}
				if ( $migrated ) {
					$optimized_images = $wpdb->get_results( $wpdb->prepare( "SELECT image_size,orig_size,resize,converted,level,backup,updated FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'media' AND image_size <> 0 ORDER BY orig_size DESC", $id ), ARRAY_A );
				}
				if ( ! $optimized_images ) {
					list( $possible_action_id, $optimized_images ) = ewww_image_optimizer_get_wpml_results( $id );
					if ( $optimized_images ) {
						$action_id = $possible_action_id;
					}
				}
			}
			// If optimizer data exists in the db.
			if ( ! empty( $optimized_images ) ) {
				list( $detail_output, $converted, $backup_available ) = ewww_image_optimizer_custom_column_results( $action_id, $optimized_images );
				echo wp_kses_post( $detail_output );
				// Output the optimizer actions.
				if ( current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
					// Display a link to re-optimize manually.
					echo '<div>' . sprintf(
						'<a class="ewww-manual-optimize" data-id="%1$d" data-nonce="%2$s" href="%3$s">%4$s</a>',
						(int) $action_id,
						esc_attr( $ewww_manual_nonce ),
						esc_url( admin_url( "admin.php?action=ewww_image_optimizer_manual_optimize&ewww_manual_nonce=$ewww_manual_nonce&ewww_force=1&ewww_attachment_ID=$action_id" ) ),
						( ewww_image_optimizer_restore_possible( $optimized_images, $compression_level ) ? esc_html__( 'Restore & Re-optimize', 'ewww-image-optimizer' ) : esc_html__( 'Re-optimize', 'ewww-image-optimizer' ) )
					) .
					wp_kses_post( ewww_image_optimizer_variant_level_notice( $optimized_images, $compression_level ) ) .
					'</div>';
				}
				if ( $backup_available && current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
					echo '<div>' . sprintf(
						'<a class="ewww-manual-cloud-restore" data-id="%1$d" data-nonce="%2$s" href="%3$s">%4$s</a>',
						(int) $action_id,
						esc_attr( $ewww_manual_nonce ),
						esc_url( admin_url( "admin.php?action=ewww_image_optimizer_manual_cloud_restore&ewww_manual_nonce=$ewww_manual_nonce&ewww_attachment_ID=$action_id" ) ),
						esc_html__( 'Restore original', 'ewww-image-optimizer' )
					) . '</div>';
				}
			} elseif ( ! $in_progress && current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				// Give the user the option to optimize the image right now.
				if ( ewww_image_optimizer_easy_active() ) {
					echo '<div>' . esc_html__( 'Easy IO enabled', 'ewww-image-optimizer' );
					ewwwio_help_link( 'https://docs.ewww.io/article/96-easy-io-is-it-working', '5f871dd2c9e77c0016217c4e' );
					echo '</div>';
				} elseif ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
					$sizes_to_opt = ewww_image_optimizer_count_unoptimized_sizes( $meta['sizes'] ) + 1;
					if ( isset( $meta['original_image'] ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_originals' ) ) {
						$sizes_to_opt++;
					}
					echo '<div>' . sprintf(
						esc_html(
							/* translators: %d: The number of resize/thumbnail images */
							_n( '%d size to compress', '%d sizes to compress', $sizes_to_opt, 'ewww-image-optimizer' )
						),
						(int) $sizes_to_opt
					) . '</div>';
				}
				echo '<div>' . sprintf(
					'<a class="ewww-manual-optimize" data-id="%1$d" data-nonce="%2$s" href="%3$s">%4$s</a>',
					(int) $action_id,
					esc_attr( $ewww_manual_nonce ),
					esc_url( admin_url( "admin.php?action=ewww_image_optimizer_manual_optimize&ewww_manual_nonce=$ewww_manual_nonce&ewww_attachment_ID=$action_id" ) ),
					esc_html__( 'Optimize now!', 'ewww-image-optimizer' )
				) .
				'</div>';
			}
			echo '</div>';
			return;
		} // End if().
		// End of output for CDN images.
		if ( ewww_image_optimizer_image_is_pending( $action_id, 'media-async' ) ) {
			echo '<div>' . esc_html__( 'In Progress', 'ewww-image-optimizer' ) . '</div>';
			$in_progress = true;
		}
		if ( ! $in_progress ) {
			$optimized_images = $wpdb->get_results( $wpdb->prepare( "SELECT image_size,orig_size,resize,converted,level,backup,updated FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'media' AND image_size <> 0 ORDER BY orig_size DESC", $id ), ARRAY_A );
			if ( ! $optimized_images ) {
				// Attempt migration, but only if the original image is in the db, $migrated will be metadata on success, false on failure.
				$migrated = ewww_image_optimizer_migrate_meta_to_db( $id, $meta, true );
			}
			if ( $migrated ) {
				$optimized_images = $wpdb->get_results( $wpdb->prepare( "SELECT image_size,orig_size,resize,converted,level,backup,updated FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'media' AND image_size <> 0 ORDER BY orig_size DESC", $id ), ARRAY_A );
			}
			if ( ! $optimized_images ) {
				list( $possible_action_id, $optimized_images ) = ewww_image_optimizer_get_wpml_results( $id );
				if ( $optimized_images ) {
					$action_id = $possible_action_id;
				}
			}
		}
		// If optimizer data exists.
		if ( ! empty( $optimized_images ) ) {
			list( $detail_output, $converted, $backup_available ) = ewww_image_optimizer_custom_column_results( $action_id, $optimized_images );
			echo wp_kses_post( $detail_output );

			// Link to webp upgrade script.
			$oldwebpfile = preg_replace( '/\.\w+$/', '.webp', $file_path );
			if ( file_exists( $oldwebpfile ) && current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
				echo "<div><a href='" . esc_url( admin_url( 'options.php?page=ewww-image-optimizer-webp-migrate' ) ) . "'>" . esc_html__( 'Run WebP upgrade', 'ewww-image-optimizer' ) . '</a></div>';
			}

			// Determine filepath for webp.
			$webpfile  = $file_path . '.webp';
			$webp_size = ewww_image_optimizer_filesize( $webpfile );
			if ( $webp_size ) {
				// Get a human readable filesize.
				$webp_size = ewww_image_optimizer_size_format( $webp_size );
				$webpurl   = esc_url( wp_get_attachment_url( $id ) . '.webp' );
				echo '<div>WebP: <a href="' . esc_url( $webpurl ) . '">' . esc_html( $webp_size ) . '</a></div>';
			}

			if ( current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				// Output a link to re-optimize manually.
				echo '<div>' . sprintf(
					'<a class="ewww-manual-optimize" data-id="%1$d" data-nonce="%2$s" href="%3$s">%4$s</a>',
					(int) $action_id,
					esc_attr( $ewww_manual_nonce ),
					esc_url( admin_url( "admin.php?action=ewww_image_optimizer_manual_optimize&ewww_manual_nonce=$ewww_manual_nonce&ewww_force=1&ewww_attachment_ID=$action_id" ) ),
					( ewww_image_optimizer_restore_possible( $optimized_images, $compression_level ) ? esc_html__( 'Restore & Re-optimize', 'ewww-image-optimizer' ) : esc_html__( 'Re-optimize', 'ewww-image-optimizer' ) )
				);
				echo wp_kses_post( ewww_image_optimizer_variant_level_notice( $optimized_images, $compression_level ) );
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_convert_links' ) && 'ims_image' !== get_post_type( $id ) && ! empty( $convert_desc ) ) {
					printf(
						' | <a class="ewww-manual-convert" data-id="%1$d" data-nonce="%2$s" title="%3$s" href="%4$s">%5$s</a>',
						(int) $action_id,
						esc_attr( $ewww_manual_nonce ),
						esc_attr( $convert_desc ),
						esc_url( admin_url( "admin.php?action=ewww_image_optimizer_manual_optimize&ewww_manual_nonce=$ewww_manual_nonce&ewww_attachment_ID=$action_id&ewww_convert=1&ewww_force=1" ) ),
						esc_html( $convert_link )
					);
				}
				echo '</div>';
			}
			$restorable = false;
			if ( $converted && ewwwio_is_file( $converted ) ) {
				$restorable = true;
			}
			if ( $restorable && current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				echo '<div>' . sprintf(
					'<a class="ewww-manual-restore" data-id="%1$d" data-nonce="%2$s" href="%3$s">%4$s</a>',
					(int) $action_id,
					esc_attr( $ewww_manual_nonce ),
					esc_url( admin_url( "admin.php?action=ewww_image_optimizer_manual_restore&ewww_manual_nonce=$ewww_manual_nonce&ewww_attachment_ID=$action_id" ) ),
					esc_html__( 'Restore original', 'ewww-image-optimizer' )
				) . '</div>';
			} elseif ( $backup_available && current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				echo '<div>' . sprintf(
					'<a class="ewww-manual-cloud-restore" data-id="%1$d" data-nonce="%2$s" href="%3$s">%4$s</a>',
					(int) $action_id,
					esc_attr( $ewww_manual_nonce ),
					esc_url( admin_url( "admin.php?action=ewww_image_optimizer_manual_cloud_restore&ewww_manual_nonce=$ewww_manual_nonce&ewww_attachment_ID=$action_id" ) ),
					esc_html__( 'Restore original', 'ewww-image-optimizer' )
				) . '</div>';
			}
		} elseif ( ! $in_progress ) {
			// Otherwise, this must be an image we haven't processed.
			if ( ewww_image_optimizer_easy_active() ) {
				echo '<div>' . esc_html__( 'Easy IO enabled', 'ewww-image-optimizer' );
				ewwwio_help_link( 'https://docs.ewww.io/article/96-easy-io-is-it-working', '5f871dd2c9e77c0016217c4e' );
				echo '</div>';
			} elseif ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
				$sizes_to_opt = ewww_image_optimizer_count_unoptimized_sizes( $meta['sizes'] ) + 1;
				if ( isset( $meta['original_image'] ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_originals' ) ) {
					$sizes_to_opt++;
				}
				echo '<div>' . sprintf(
					esc_html(
						/* translators: %d: The number of resize/thumbnail images */
						_n( '%d size to compress', '%d sizes to compress', $sizes_to_opt, 'ewww-image-optimizer' )
					),
					(int) $sizes_to_opt
				) . '</div>';
			} else {
				echo '<div>' . esc_html__( 'Not optimized', 'ewww-image-optimizer' ) . '</div>';
			}
			// Tell them the filesize.
			echo '<div>' . sprintf(
				/* translators: %s: size of the image */
				esc_html__( 'Image Size: %s', 'ewww-image-optimizer' ),
				esc_html( $file_size )
			) . '</div>';
			// Determine filepath for webp.
			$webpfile  = $file_path . '.webp';
			$webp_size = ewww_image_optimizer_filesize( $webpfile );
			if ( $webp_size ) {
				// Get a human readable filesize.
				$webp_size = ewww_image_optimizer_size_format( $webp_size );
				$webpurl   = esc_url( wp_get_attachment_url( $id ) . '.webp' );
				echo '<div>WebP: <a href="' . esc_url( $webpurl ) . '">' . esc_html( $webp_size ) . '</a></div>';
			}
			if ( current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				// Give the user the option to optimize the image right now.
				printf(
					'<div><a class="ewww-manual-optimize" data-id="%1$d" data-nonce="%2$s" href="%3$s">%4$s</a>',
					(int) $action_id,
					esc_attr( $ewww_manual_nonce ),
					esc_url( admin_url( "admin.php?action=ewww_image_optimizer_manual_optimize&ewww_manual_nonce=$ewww_manual_nonce&ewww_attachment_ID=$action_id" ) ),
					esc_html__( 'Optimize now!', 'ewww-image-optimizer' )
				);
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_convert_links' ) && 'ims_image' !== get_post_type( $id ) && ! empty( $convert_desc ) ) {
					printf(
						' | <a class="ewww-manual-convert" data-id="%1$d" data-nonce="%2$s" title="%3$s" href="%4$s">%5$s</a>',
						(int) $action_id,
						esc_attr( $ewww_manual_nonce ),
						esc_attr( $convert_desc ),
						esc_url( admin_url( "admin.php?action=ewww_image_optimizer_manual_optimize&ewww_manual_nonce=$ewww_manual_nonce&ewww_attachment_ID=$action_id&ewww_convert=1&ewww_force=1" ) ),
						esc_html( $convert_link )
					);
				}
				echo '</div>';
			}
		} // End if().
		echo '</div>';
	} // End if().
}

/**
 * Check the stored results to see if 'restore & re-optimize' is possible and applicable.
 *
 * @param array $optimized_images The list of image records from the database.
 * @param int   $compression_level The currently active compression level.
 */
function ewww_image_optimizer_restore_possible( $optimized_images, $compression_level ) {
	if ( empty( $compression_level ) ) {
		return '';
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		return '';
	}
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	foreach ( $optimized_images as $optimized_image ) {
		if ( 'full' === $optimized_image['resize'] ) {
			ewwwio_debug_message( "comparing $compression_level (current) vs. {$optimized_image['level']} (previous)" );
			if ( $compression_level < 30 && $compression_level < $optimized_image['level'] && $optimized_image['level'] > 20 ) {
				$updated_time = strtotime( $optimized_image['updated'] );
				if ( DAY_IN_SECONDS * 30 + $updated_time > time() ) {
					return true;
				}
			}
		}
	}
	return false;
}

/**
 * Check the stored results to detect deviation from the current settings.
 *
 * @param array $optimized_images The list of image records from the database.
 * @param int   $compression_level The currently active compression level.
 */
function ewww_image_optimizer_variant_level_notice( $optimized_images, $compression_level ) {
	if ( empty( $compression_level ) ) {
		return '';
	}
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	foreach ( $optimized_images as $optimized_image ) {
		if ( 'full' === $optimized_image['resize'] ) {
			if ( is_numeric( $optimized_image['level'] ) && (int) $compression_level > (int) $optimized_image['level'] ) {
				return ' <span title="' . esc_attr__( 'Compressed at a lower level than current setting.' ) . '" class="ewww-variant-icon"><sup>!</sup></span>';
			}
		}
	}
	return '';
}

/**
 * Determine how many sizes need optimization.
 *
 * @param array $sizes The 'sizes' portion of the attachment metadata.
 * @return int The number of unoptimized sizes.
 */
function ewww_image_optimizer_count_unoptimized_sizes( $sizes ) {
	if ( ! ewww_image_optimizer_iterable( $sizes ) ) {
		ewwwio_debug_message( 'unoptimized sizes cannot be counted' );
		return 0;
	}
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$sizes_to_opt   = 0;
	$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt', false, true );

	// To keep track of the ones we have already processed.
	$processed = array();
	foreach ( $sizes as $size => $data ) {
		ewwwio_debug_message( "checking for size: $size" );
		if ( strpos( $size, 'webp' ) === 0 ) {
			continue;
		}
		if ( ! empty( $disabled_sizes[ $size ] ) ) {
			continue;
		}
		if ( ! empty( $disabled_sizes['pdf-full'] ) && 'full' === $size ) {
			continue;
		}
		if ( empty( $data['file'] ) ) {
			continue;
		}

		// Check through all the sizes we've processed so far.
		foreach ( $processed as $proc => $scan ) {
			// If a previous resize had identical dimensions...
			if ( $scan['height'] === $data['height'] && $scan['width'] === $data['width'] ) {
				// Found a duplicate size, get outta here!
				continue( 2 );
			}
		}
		$sizes_to_opt++;
		// Store info on the sizes we've processed, so we can check the list for duplicate sizes.
		$processed[ $size ]['width']  = $data['width'];
		$processed[ $size ]['height'] = $data['height'];
	} // End foreach().
	return $sizes_to_opt;
}

/**
 * Display cumulative image compression results with individual images displayed in a modal.
 *
 * @param int   $id The ID number of the attachment.
 * @param array $optimized_images A list of image records related to $id.
 * @return array {
 *     Information compiled from the database records.
 *
 *     @type string $output The image results plus a table of individual image results in a modal.
 *     @type string|bool $converted The original image if the attachment was converted or false.
 *     @type string|bool $backup_available The backup hash if available or false.
 * }
 */
function ewww_image_optimizer_custom_column_results( $id, $optimized_images ) {
	if ( empty( $id ) || empty( $optimized_images ) || ! is_array( $optimized_images ) ) {
		return array( '', false, false );
	}
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewwwio_resize_status;
	$orig_size        = 0;
	$opt_size         = 0;
	$converted        = false;
	$backup_available = false;
	$sizes_to_opt     = 0;
	$output           = '';
	$detail_output    = '<table class="striped"><tr><th>&nbsp;</th><th>' . esc_html__( 'Image Size', 'ewww-image-optimizer' ) . '</th><th>' . esc_html__( 'Savings', 'ewww-image-optimizer' ) . '</th></tr>';
	foreach ( $optimized_images as $optimized_image ) {
		if ( ! empty( $optimized_image['attachment_id'] ) ) {
			$id = $optimized_image['attachment_id'];
		}
		$orig_size += $optimized_image['orig_size'];
		$opt_size  += $optimized_image['image_size'];
		if ( 'full' === $optimized_image['resize'] ) {
			$updated_time = strtotime( $optimized_image['updated'] );
			if ( DAY_IN_SECONDS * 30 + $updated_time > time() ) {
				$backup_available = $optimized_image['backup'];
			} else {
				$backup_available = '';
			}
		}
		if ( ! empty( $optimized_image['converted'] ) ) {
			$converted = ewww_image_optimizer_absolutize_path( $optimized_image['converted'] );
		}
		$sizes_to_opt++;
		if ( ! empty( $optimized_image['resize'] ) ) {
			$display_size   = ewww_image_optimizer_size_format( $optimized_image['image_size'] );
			$detail_output .= '<tr><td><strong>' . ucfirst( $optimized_image['resize'] ) . "</strong></td><td>$display_size</td><td>" . esc_html( ewww_image_optimizer_image_results( $optimized_image['orig_size'], $optimized_image['image_size'] ) ) . '</td></tr>';
		}
	}
	$detail_output .= '</table>';

	if ( ! empty( $ewwwio_resize_status ) ) {
		$output .= '<div>' . esc_html( $ewwwio_resize_status ) . '</div>';
	}
	$output .= '<div>' . sprintf(
		esc_html(
			/* translators: %d: number of resizes/thumbnails compressed */
			_n( '%d size compressed', '%d sizes compressed', $sizes_to_opt, 'ewww-image-optimizer' )
		),
		$sizes_to_opt
	);
	$output     .= " <a href='#TB_inline?width=550&height=450&inlineId=ewww-attachment-detail-$id' class='thickbox'>(+)</a></div>";
	$results_msg = ewww_image_optimizer_image_results( $orig_size, $opt_size );
	// Output the optimizer results.
	$output      .= '<div>' . esc_html( $results_msg ) . '</div>';
	$display_size = ewww_image_optimizer_size_format( $opt_size );
	// Output the total filesize.
	$detail_output .= '<div><strong>' . sprintf(
		/* translators: %s: human-readable file size */
		esc_html__( 'Total Size: %s', 'ewww-image-optimizer' ),
		$display_size
	) . '</strong></div>';
	$output .= "<div id='ewww-attachment-detail-$id' class='ewww-attachment-detail-container'><div class='ewww-attachment-detail'>$detail_output</div></div>";
	return array( $output, $converted, $backup_available );
}

/**
 * If attachment is translated by WPML, get the primary ID number.
 *
 * @param int $id The attachment ID number to search for in the WPML tables.
 * @return int The primary/original ID number.
 */
function ewww_image_optimizer_get_primary_wpml_id( $id ) {
	if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
		return $id;
	}
	if ( get_post_meta( $id, 'wpml_media_processed', true ) ) {
		$trid = apply_filters( 'wpml_element_trid', null, $id, 'post_attachment' );
		if ( ! empty( $trid ) ) {
			$translations = apply_filters( 'wpml_get_element_translations', null, $trid, 'post_attachment' );
			if ( ewww_image_optimizer_iterable( $translations ) ) {
				$possible_ids = array();
				foreach ( $translations as $translation ) {
					if ( ! empty( $translation->element_id ) ) {
						$possible_ids[] = (int) $translation->element_id;
					}
				}
				if ( ewww_image_optimizer_iterable( $possible_ids ) ) {
					sort( $possible_ids );
					return $possible_ids[0];
				}
			}
		}
	}
	return $id;
}
/**
 * Gets results for WPML replicates.
 *
 * @param int $id The attachment ID number to search for in the WPML tables.
 * @return array The resultant attachment ID, and a list of image optimization results.
 */
function ewww_image_optimizer_get_wpml_results( $id ) {
	if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
		return array( $id, array() );
	}
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$trid = apply_filters( 'wpml_element_trid', null, $id, 'post_attachment' );
	if ( empty( $trid ) ) {
		return array( $id, array() );
	}
	$translations = apply_filters( 'wpml_get_element_translations', null, $trid, 'post_attachment' );
	if ( empty( $translations ) ) {
		return array( $id, array() );
	}
	global $wpdb;
	foreach ( $translations as $translation ) {
		if ( empty( $translation->element_id ) ) {
			continue;
		}
		ewwwio_debug_message( "checking {$translation->element_id} for results with WPML" );
		$optimized_images = $wpdb->get_results( $wpdb->prepare( "SELECT image_size,orig_size,resize,converted,level,backup,updated FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'media' AND image_size <> 0 ORDER BY orig_size DESC", $translation->element_id ), ARRAY_A );
		if ( ! empty( $optimized_images ) ) {
			return array( (int) $translation->element_id, $optimized_images );
		}
	}
	return array( $id, array() );
}
/**
 * Removes optimization from metadata, because we store it all in the images table now.
 *
 * @param array $meta The attachment metadata.
 * @return array The attachment metadata after being cleaned.
 */
function ewww_image_optimizer_clean_meta( $meta ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$changed = false;
	if ( is_array( $meta ) && ! empty( $meta['ewww_image_optimizer'] ) ) {
		unset( $meta['ewww_image_optimizer'] );
		$changed = true;
	}
	if ( is_array( $meta ) && ! empty( $meta['converted'] ) ) {
		unset( $meta['converted'] );
		$changed = true;
	}
	if ( is_array( $meta ) && ! empty( $meta['orig_file'] ) ) {
		unset( $meta['orig_file'] );
		$changed = true;
	}
	if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
		foreach ( $meta['sizes'] as $size => $data ) {
			if ( is_array( $data ) && ! empty( $data['ewww_image_optimizer'] ) ) {
				unset( $meta['sizes'][ $size ]['ewww_image_optimizer'] );
				$changed = true;
			}
			if ( is_array( $data ) && ! empty( $data['converted'] ) ) {
				unset( $meta['sizes'][ $size ]['converted'] );
				$changed = true;
			}
			if ( is_array( $data ) && ! empty( $data['orig_file'] ) ) {
				unset( $meta['sizes'][ $size ]['orig_file'] );
				$changed = true;
			}
		}
	}
	if ( $changed ) {
		update_post_meta( $id, '_wp_attachment_metadata', $meta );
	}
	return $meta;
}

/**
 * Updates a record in the images table with information from the attachment metadata.
 *
 * @global object $wpdb
 *
 * @param string $file The name of the file to update.
 * @param string $gallery Optional. Location of the image, like 'media' or 'nextgen'. Default ''.
 * @param int    $attachment_id Optional. The ID number of the image. Default 0.
 * @param string $size Optional. The name of the image size like 'medium' or 'large'. Default ''.
 * @param string $converted Optional. The name of the original file. Default ''.
 */
function ewww_image_optimizer_update_file_from_meta( $file, $gallery = '', $attachment_id = 0, $size = '', $converted = '' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$already_optimized = ewww_image_optimizer_find_already_optimized( $file );
	if ( is_array( $already_optimized ) && ! empty( $already_optimized['id'] ) ) {
		$updates = array();
		if ( $gallery && empty( $already_optimized['gallery'] ) ) {
			$updates['gallery'] = $gallery;
		}
		if ( $attachment_id && empty( $already_optimized['attachment_id'] ) ) {
			$updates['attachment_id'] = $attachment_id;
		}
		if ( $size && empty( $already_optimized['resize'] ) ) {
			$updates['resize'] = $size;
		}
		if ( $converted && empty( $already_optimized['converted'] ) ) {
			$updates['converted'] = $converted;
		}
		if ( $updates ) {
			ewwwio_debug_message( "running update for $file" );
			$updates['updated'] = $already_optimized['updated'];
			global $wpdb;
			// Update the values given for the record we found.
			$updated = $wpdb->update(
				$wpdb->ewwwio_images,
				$updates,
				array(
					'id' => $already_optimized['id'],
				)
			);
			if ( false === $updated ) {
				ewwwio_debug_message( "failed to update record for $file" );
			}
			if ( ! $updated ) {
				ewwwio_debug_message( "no records updated for $file and {$already_optimized['id']}" );
			}
			return $updated;
		}
	} // End if().
	return false;
}

/**
 * Compiles information from the metadata to be inserted into the images table.
 *
 * @param int   $id The attachment ID number.
 * @param array $meta The attachment metadata.
 * @param bool  $bail_early Optional. True to stop execution if full size didn't need migration.
 * @return array The attachment metadata, potentially cleaned after migration.
 */
function ewww_image_optimizer_migrate_meta_to_db( $id, $meta, $bail_early = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $meta ) ) {
		ewwwio_debug_message( "empty meta for $id" );
		return $meta;
	}
	list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
	if ( ! ewwwio_is_file( $file_path ) && ( class_exists( 'WindowsAzureStorageUtil' ) || class_exists( 'Amazon_S3_And_CloudFront' ) ) ) {
		// Construct a $file_path and proceed IF a supported CDN plugin is installed.
		$file_path = get_attached_file( $id );
		if ( ! $file_path ) {
			ewwwio_debug_message( 'no file found for remote attachment' );
			return ewww_image_optimizer_clean_meta( $meta );
		}
	} elseif ( ! $file_path ) {
		ewwwio_debug_message( 'no file found for attachment' );
		return ewww_image_optimizer_clean_meta( $meta );
	}
	$converted        = ( is_array( $meta ) && ! empty( $meta['converted'] ) && ! empty( $meta['orig_file'] ) ? trailingslashit( dirname( $file_path ) ) . basename( $meta['orig_file'] ) : false );
	$full_size_update = ewww_image_optimizer_update_file_from_meta( $file_path, 'media', $id, 'full', $converted );
	if ( ! $full_size_update && $bail_early ) {
		ewwwio_debug_message( "bailing early for migration of $id" );
		return false;
	}
	$retina_path = ewww_image_optimizer_get_hidpi_path( $file_path, false );
	if ( $retina_path ) {
		ewww_image_optimizer_update_file_from_meta( $retina_path, 'media', $id, 'full-retina' );
	}
	$type = ewww_image_optimizer_quick_mimetype( $file_path );
	// Resized versions, so we can continue.
	if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
		// Meta sizes don't contain a path, so we calculate one.
		if ( 'ims_image' === get_post_type( $id ) ) {
			$base_dir = trailingslashit( dirname( $file_path ) ) . '_resized/';
		} else {
			$base_dir = trailingslashit( dirname( $file_path ) );
		}
		foreach ( $meta['sizes'] as $size => $data ) {
			ewwwio_debug_message( "checking for size: $size" );
			if ( strpos( $size, 'webp' ) === 0 ) {
				continue;
			}
			if ( empty( $data ) || ! is_array( $data ) || empty( $data['file'] ) ) {
				continue;
			}
			if ( 'full' === $size && 'application/pdf' === $type ) {
				$size = 'pdf-full';
			} elseif ( 'full' === $size ) {
				continue;
			}

			$resize_path = $base_dir . wp_basename( $data['file'] );
			$converted   = ( is_array( $data ) && ! empty( $data['converted'] ) && ! empty( $data['orig_file'] ) ? trailingslashit( dirname( $resize_path ) ) . basename( $data['orig_file'] ) : false );
			ewww_image_optimizer_update_file_from_meta( $resize_path, 'media', $id, $size, $converted );
			// Search for retina images.
			if ( function_exists( 'wr2x_get_retina' ) ) {
				$retina_path = wr2x_get_retina( $resize_path );
			} else {
				$retina_path = ewww_image_optimizer_get_hidpi_path( $resize_path, false );
			}
			if ( $retina_path ) {
				ewww_image_optimizer_update_file_from_meta( $retina_path, 'media', $id, $size . '-retina' );
			}
		}
	} // End if().

	// Search sizes from a custom theme...
	if ( isset( $meta['image_meta']['resized_images'] ) && ewww_image_optimizer_iterable( $meta['image_meta']['resized_images'] ) ) {
		$imagemeta_resize_pathinfo = pathinfo( $file_path );
		$imagemeta_resize_path     = '';
		foreach ( $meta['image_meta']['resized_images'] as $index => $imagemeta_resize ) {
			$imagemeta_resize_path = $imagemeta_resize_pathinfo['dirname'] . '/' . $imagemeta_resize_pathinfo['filename'] . '-' . $imagemeta_resize . '.' . $imagemeta_resize_pathinfo['extension'];
			ewww_image_optimizer_update_file_from_meta( $imagemeta_resize_path, 'media', $id, 'resized-images-' . $index );
		}
	}

	// and another custom theme.
	if ( isset( $meta['custom_sizes'] ) && ewww_image_optimizer_iterable( $meta['custom_sizes'] ) ) {
		$custom_sizes_pathinfo = pathinfo( $file_path );
		$custom_size_path      = '';
		foreach ( $meta['custom_sizes'] as $dimensions => $custom_size ) {
			$custom_size_path = $custom_sizes_pathinfo['dirname'] . '/' . wp_basename( $custom_size['file'] );
			ewww_image_optimizer_update_file_from_meta( $custom_size_path, 'media', $id, 'custom-size-' . $dimensions );
		}
	}
	$meta = ewww_image_optimizer_clean_meta( $meta );
	if ( ewww_image_optimizer_function_exists( 'print_r' ) ) {
		ewwwio_debug_message( print_r( $meta, true ) );
	}
	return $meta;
}

/**
 * Dismisses the WC regen notice.
 */
function ewww_image_optimizer_dismiss_wc_regen() {
	ewwwio_ob_clean();
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that the user is properly authorized.
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	delete_option( 'ewww_image_optimizer_wc_regen' );
	die();
}

/**
 * Dismisses the LR sync notice.
 */
function ewww_image_optimizer_dismiss_lr_sync() {
	ewwwio_ob_clean();
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that the user is properly authorized.
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	delete_option( 'ewww_image_optimizer_lr_sync' );
	die();
}

/**
 * Disables the Media Library notice about List Mode.
 */
function ewww_image_optimizer_dismiss_media_notice() {
	ewwwio_ob_clean();
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that the user is properly authorized.
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	update_option( 'ewww_image_optimizer_dismiss_media_notice', true, false );
	update_site_option( 'ewww_image_optimizer_dismiss_media_notice', true );
	die();
}

/**
 * Disables the notice about leaving a review.
 */
function ewww_image_optimizer_dismiss_review_notice() {
	ewwwio_ob_clean();
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that the user is properly authorized.
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	update_option( 'ewww_image_optimizer_dismiss_review_notice', true, false );
	update_site_option( 'ewww_image_optimizer_dismiss_review_notice', true );
	die();
}

/**
 * Add our bulk optimize action to the bulk actions drop-down menu.
 *
 * @param array $bulk_actions A list of actions available already.
 * @return array The list of actions, with our bulk action included.
 */
function ewww_image_optimizer_add_bulk_media_actions( $bulk_actions ) {
	if ( is_array( $bulk_actions ) ) {
		$bulk_actions['bulk_optimze'] = __( 'Bulk Optimize', 'ewww-image-optimizer' );
	}
	return $bulk_actions;
}

/**
 * Handles the bulk actions POST.
 *
 * @param string $redirect_to The URL from whence we came.
 * @param string $doaction The action requested.
 * @param array  $post_ids An array of attachment ID numbers.
 * @return string The URL to go back to when we are done handling the action.
 */
function ewww_image_optimizer_bulk_action_handler( $redirect_to, $doaction, $post_ids ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $doaction || 'bulk_optimize' !== $doaction ) ) {
		return $redirect_to;
	}
	// If there is no media to optimize, do nothing.
	if ( ! ewww_image_optimizer_iterable( $post_ids ) ) {
		return $redirect_to;
	}
	check_admin_referer( 'bulk-media' );
	// Prep the attachment IDs for optimization.
	$ids = implode( ',', array_map( 'intval', $post_ids ) );
	wp_safe_redirect(
		add_query_arg(
			array(
				'page' => 'ewww-image-optimizer-bulk',
				'ids'  => $ids,
			),
			admin_url( 'upload.php' )
		)
	);
	ewwwio_memory( __FUNCTION__ );
}

/**
 * Get the current compression level based on the mime type.
 *
 * @param string $type The mime-type of the file/image.
 * @return int The compression level to be used for that image type by default.
 */
function ewww_image_optimizer_get_level( $type ) {
	if ( 'image/jpeg' === $type ) {
		return (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' );
	}
	if ( 'image/png' === $type ) {
		return (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' );
	}
	if ( 'image/gif' === $type ) {
		return (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' );
	}
	if ( 'application/pdf' === $type ) {
		return (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' );
	}
	if ( 'image/svg+xml' === $type ) {
		return (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_svg_level' );
	}
	return 0;
}

/**
 * Check old and new compression levels for an image to see if it ought to be re-optimized in smart mode.
 *
 * @param int $old The previous compression level used on an image.
 * @param int $new The current compression level for the image mime-type.
 * @return bool True if they are not matched/equivalent, false otherwise.
 */
function ewww_image_optimizer_level_mismatch( $old, $new ) {
	$old = (int) $old;
	$new = (int) $new;
	if ( empty( $old ) || empty( $new ) ) {
		return false;
	}
	if ( 20 === $old && 10 === $new ) {
		return false;
	}
	if ( $new === $old ) {
		return false;
	}
	return true;
}

/**
 * Retrieve option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting.
 *
 * Retrieves multi-site and single-site options as appropriate as well as allowing overrides with
 * same-named constant. Overrides are only available for integer and boolean options.
 *
 * @param string $option_name The name of the option to retrieve.
 * @param mixed  $default The default to use if not found/set, defaults to false, but not currently used.
 * @param bool   $single Use single-site setting regardless of multisite activation. Default is off/false.
 * @return mixed The value of the option.
 */
function ewww_image_optimizer_get_option( $option_name, $default = false, $single = false ) {
	$constant_name = strtoupper( $option_name );
	if ( defined( $constant_name ) && ( is_int( constant( $constant_name ) ) || is_bool( constant( $constant_name ) ) ) ) {
		return constant( $constant_name );
	}
	if ( 'ewww_image_optimizer_cloud_key' === $option_name && defined( $constant_name ) ) {
		$option_value = constant( $constant_name );
		if ( is_string( $option_value ) && ! empty( $option_value ) ) {
			return trim( $option_value );
		}
	}
	if ( 'ewww_image_optimizer_ll_all_things' === $option_name && defined( $constant_name ) ) {
		return sanitize_text_field( constant( $constant_name ) );
	}
	if (
		(
			'ewww_image_optimizer_exclude_paths' === $option_name ||
			'exactdn_exclude' === $option_name ||
			'ewww_image_optimizer_ll_exclude' === $option_name ||
			'ewww_image_optimizer_webp_rewrite_exclude' === $option_name
		)
		&& defined( $constant_name )
	) {
		return ewww_image_optimizer_exclude_paths_sanitize( constant( $constant_name ) );
	}
	if ( 'ewww_image_optimizer_aux_paths' === $option_name && defined( $constant_name ) ) {
		return ewww_image_optimizer_aux_paths_sanitize( constant( $constant_name ) );
	}
	if ( 'ewww_image_optimizer_webp_paths' === $option_name && defined( $constant_name ) ) {
		return ewww_image_optimizer_webp_paths_sanitize( constant( $constant_name ) );
	}
	if ( 'ewww_image_optimizer_disable_resizes' === $option_name && defined( $constant_name ) ) {
		return ewww_image_optimizer_disable_resizes_sanitize( constant( $constant_name ) );
	}
	if ( 'ewww_image_optimizer_disable_resizes_opt' === $option_name && defined( $constant_name ) ) {
		return ewww_image_optimizer_disable_resizes_sanitize( constant( $constant_name ) );
	}
	if ( 'ewww_image_optimizer_jpg_background' === $option_name && defined( $constant_name ) ) {
		return ewww_image_optimizer_jpg_background( constant( $constant_name ) );
	}
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( ! $single && is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) && ! get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) ) {
		$option_value = get_site_option( $option_name );
		if ( 'ewww_image_optimizer_exactdn' === $option_name && ! $option_value ) {
			$option_value = get_option( $option_name );
		}
	} else {
		$option_value = get_option( $option_name );
	}
	return $option_value;
}

/**
 * Set an option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting.
 *
 * @param string $option_name The name of the option to save.
 * @param mixed  $option_value The value to save for the option.
 * @return bool True if the operation was successful.
 */
function ewww_image_optimizer_set_option( $option_name, $option_value ) {
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) && ! get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) ) {
		$success = update_site_option( $option_name, $option_value );
	} else {
		$success = update_option( $option_name, $option_value );
	}
	return $success;
}

/**
 * Check for a list of attachments for which we do not rebuild meta.
 *
 * @return array {
 *     Information regarding attachments with broken metadata that could not be rebuilt.
 *
 *     @type array A list of all know 'bad' attachments.
 *     @type string The most recent 'bad' attachment.
 * }
 */
function ewww_image_optimizer_get_bad_attachments() {
	$bad_attachment = (int) get_transient( 'ewww_image_optimizer_rebuilding_attachment' );
	if ( $bad_attachment ) {
		$bad_attachments   = (array) ewww_image_optimizer_get_option( 'ewww_image_optimizer_bad_attachments' );
		$bad_attachments[] = $bad_attachment;
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_bad_attachments', $bad_attachments, false );
	} else {
		$bad_attachments = (array) ewww_image_optimizer_get_option( 'ewww_image_optimizer_bad_attachments' );
	}
	array_walk( $bad_attachments, 'intval' );
	delete_transient( 'ewww_image_optimizer_rebuilding_attachment' );
	return array( $bad_attachments, $bad_attachment );
}

/**
 * JS needed for the settings page.
 *
 * @param string $hook The hook name of the page being loaded.
 */
function ewww_image_optimizer_settings_script( $hook ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Make sure we are being called from the settings page.
	if ( 'settings_page_ewww-image-optimizer-options' !== $hook ) {
		return;
	}
	if (
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_wizard_complete' ) &&
		! is_network_admin() &&
		( ! is_multisite() || get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) || ! is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) )
	) {
		remove_all_actions( 'admin_notices' );
	}
	if ( ! empty( $_GET['rescue_mode'] ) && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' ) ) {
		remove_all_actions( 'admin_notices' );
	}
	delete_option( 'ewww_image_optimizer_exactdn_checkin' );
	global $exactdn;
	if ( has_action( 'admin_notices', 'ewww_image_optimizer_notice_exactdn_domain_mismatch' ) ) {
		delete_option( 'ewww_image_optimizer_exactdn_domain' );
		delete_option( 'ewww_image_optimizer_exactdn_local_domain' );
		delete_option( 'ewww_image_optimizer_exactdn_plan_id' );
		delete_option( 'ewww_image_optimizer_exactdn_failures' );
		delete_option( 'ewww_image_optimizer_exactdn_verified' );
		remove_action( 'admin_notices', 'ewww_image_optimizer_notice_exactdn_domain_mismatch' );
		$exactdn->setup();
	}
	$blog_ids = array();
	if ( is_multisite() && is_network_admin() ) {
		global $wpdb;
		$blog_ids = $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs WHERE site_id = %d ORDER BY blog_id DESC", $wpdb->siteid ) );
		if ( ! ewww_image_optimizer_iterable( $blog_ids ) ) {
			$blog_ids = array();
		}
		wp_enqueue_script( 'jquery-ui-progressbar' );
	}
	add_thickbox();
	wp_enqueue_script( 'ewww-beacon-script', plugins_url( '/includes/eio-beacon.js', __FILE__ ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION );
	wp_enqueue_script( 'ewww-settings-script', plugins_url( '/includes/eio-settings.js', __FILE__ ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION, true );
	wp_enqueue_style( 'jquery-ui-tooltip-custom', plugins_url( '/includes/jquery-ui-1.10.1.custom.css', __FILE__ ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
	wp_enqueue_script( 'postbox' );
	wp_enqueue_script( 'dashboard' );
	wp_localize_script(
		'ewww-settings-script',
		'ewww_vars',
		array(
			'_wpnonce'                => wp_create_nonce( 'ewww-image-optimizer-settings' ),
			'invalid_response'        => esc_html__( 'Received an invalid response from your website, please check for errors in the Developer Tools console of your browser.', 'ewww-image-optimizer' ),
			'loading_image_url'       => plugins_url( '/images/spinner.gif', __FILE__ ),
			'operation_stopped'       => esc_html__( 'Operation stopped.', 'ewww-image-optimizer' ),
			'easyio_register_warning' => esc_html__( 'This will register all your sites with the Easy IO CDN and will take some time to complete. Do you wish to proceed?', 'ewww-image-optimizer' ),
			'easyio_register_success' => esc_html__( 'Easy IO registration complete. Please wait 5-10 minutes and then activate your sites.', 'ewww-image-optimizer' ),
			'exactdn_network_warning' => esc_html__( 'This will attempt to activate Easy IO on all sites within the multi-site network. Please be sure you have registered all your site URLs before continuing.', 'ewww-image-optimizer' ),
			'exactdn_network_success' => esc_html__( 'Easy IO setup and verification is complete.', 'ewww-image-optimizer' ),
			'webp_cloud_warning'      => esc_html__( 'If you have not run the Bulk Optimizer on existing images, you will likely encounter broken image URLs. Are you ready to continue?', 'ewww-image-optimizer' ),
			'network_blog_ids'        => $blog_ids,
		)
	);
	wp_add_inline_script(
		'ewww-settings-script',
		'ewww_vars.cloud_media = ' . ( ewww_image_optimizer_cloud_based_media() ? 1 : 0 ) . ";\n" .
		'ewww_vars.save_space = ' . ( get_option( 'ewww_image_optimizer_goal_save_space' ) ? 1 : 0 ) . ";\n" .
		'ewww_vars.site_speed = ' . ( get_option( 'ewww_image_optimizer_goal_site_speed' ) ? 1 : 0 ) . ";\n"
	);
	ewwwio_memory( __FUNCTION__ );
}

/**
 * Get a total of how much space we have saved so far.
 *
 * @global object $wpdb
 *
 * @return int The total savings found, in bytes.
 */
function ewww_image_optimizer_savings() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	$total_orig    = 0;
	$total_opt     = 0;
	$total_savings = 0;
	$started       = microtime( true );
	if ( is_multisite() && is_network_admin() ) {
		$cache_savings = get_site_transient( 'ewww_image_optimizer_savings' );
		if ( ! empty( $cache_savings ) && is_array( $cache_savings ) && 2 === count( $cache_savings ) && ! empty( $cache_savings[0] ) ) {
			ewwwio_debug_message( 'savings query avoided via (multi-site) cache' );
			return $cache_savings;
		}
		ewwwio_debug_message( 'querying savings for multi-site' );

		if ( get_blog_count() > 1000 ) {
			return 0;
		}
		if ( function_exists( 'get_sites' ) ) {
			$blogs = get_sites(
				array(
					'fields' => 'ids',
					'number' => 1000,
				)
			);
		} else {
			return 0;
		}
		if ( ewww_image_optimizer_iterable( $blogs ) ) {
			foreach ( $blogs as $blog ) {
				if ( is_array( $blog ) ) {
					$blog_id = $blog['blog_id'];
				} else {
					$blog_id = $blog;
				}
				switch_to_blog( $blog_id );
				ewwwio_debug_message( "getting savings for site: $blog_id" );
				$table_name          = $wpdb->prefix . 'ewwwio_images';
				$wpdb->ewwwio_images = $table_name;
				ewwwio_debug_message( "table name is $table_name ({$wpdb->ewwwio_images})" );
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
					ewww_image_optimizer_install_table();
				}
				if ( $wpdb->ewwwio_images ) {
					$orig_size   = $wpdb->get_var( "SELECT SUM(orig_size) FROM $wpdb->ewwwio_images WHERE image_size > 0" );
					$opt_size    = $wpdb->get_var( "SELECT SUM(image_size) FROM $wpdb->ewwwio_images WHERE image_size > 0" );
					$total_orig += $orig_size;
					$total_opt  += $opt_size;
					$savings     = $orig_size - $opt_size;
					ewwwio_debug_message( "savings found for site $blog_id: $savings" );
					$total_savings += $savings;
				}
				restore_current_blog();
			}
			$wpdb->ewwwio_images = $wpdb->prefix . 'ewwwio_images';
		}
		set_site_transient( 'ewww_image_optimizer_savings', array( $total_opt, $total_orig ), DAY_IN_SECONDS );
	} else {
		$cache_savings = get_transient( 'ewww_image_optimizer_savings' );
		if ( ! empty( $cache_savings ) && is_array( $cache_savings ) && 2 === count( $cache_savings ) ) {
			ewwwio_debug_message( 'savings query avoided via (single-site) cache' );
			return $cache_savings;
		}
		ewwwio_debug_message( 'querying savings for single site' );
		$table_name = $wpdb->ewwwio_images;
		ewwwio_debug_message( "table name is $table_name" );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			ewww_image_optimizer_install_table();
		}
		$orig_size   = $wpdb->get_var( "SELECT SUM(orig_size) FROM $wpdb->ewwwio_images WHERE image_size > 0" );
		$opt_size    = $wpdb->get_var( "SELECT SUM(image_size) FROM $wpdb->ewwwio_images WHERE image_size > 0" );
		$total_orig += $orig_size;
		$total_opt  += $opt_size;

		$total_savings = $orig_size - $opt_size;
		set_transient( 'ewww_image_optimizer_savings', array( $total_opt, $total_orig ), DAY_IN_SECONDS );
	} // End if().
	$elapsed = microtime( true ) - $started;
	ewwwio_debug_message( "savings query took $elapsed seconds" );
	ewwwio_debug_message( "total original size: $total_orig" );
	ewwwio_debug_message( "total current(opt) size: $total_opt" );
	ewwwio_debug_message( "savings found: $total_savings" );
	return array( $total_opt, $total_orig );
}

/**
 * Manually verify if WebP rewriting is working.
 *
 * Requests the test.png and checks to see if it is actually of type image/webp.
 *
 * @return string Empty if the test image is WebP, error message otherwise.
 */
function ewww_image_optimizer_test_webp_mime_error() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$positive_test = false;
	$negative_test = false;
	$test_url      = plugins_url( '/images/test.png', __FILE__ ) . '?m=' . time();
	add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );

	// Run the "positive" test, which should receive a WebP in a .png wrapper.
	$test_result = wp_remote_get( $test_url, array( 'headers' => 'Accept: image/webp' ) );
	if ( is_wp_error( $test_result ) ) {
		$error_message = $test_result->get_error_message();
		ewwwio_debug_message( "webp verification request failed: $error_message" );
		return $error_message;
	} elseif ( empty( $test_result['body'] ) ) {
		ewwwio_debug_message( 'webp verification response empty' );
		return __( 'WebP response was empty', 'ewww-image-optimizer' );
	} elseif ( strlen( $test_result['body'] ) < 300 ) {
		ewwwio_debug_message( 'webp verification response too small: ' . strlen( $test_result['body'] ) );
		return __( 'WebP response was too small', 'ewww-image-optimizer' );
	} elseif ( empty( $test_result['response']['code'] ) ) {
		ewwwio_debug_message( 'webp test received unknown response code' );
		return __( 'WebP response status code missing', 'ewww-image-optimizer' );
	} elseif ( 200 !== (int) $test_result['response']['code'] ) {
		ewwwio_debug_message( 'webp test received response code: ' . $test_result['response']['code'] );
		/* translators: %d: the HTTP status code */
		return sprintf( __( 'WebP response received status %d', 'ewww-image-optimizer' ), $test_result['response']['code'] );
	} elseif ( '52494646' === bin2hex( substr( $test_result['body'], 0, 4 ) ) ) {
		ewwwio_debug_message( 'webp (real-world) verification succeeded' );
		$positive_test = true;
	} else {
		ewwwio_debug_message( 'webp mime check failed: ' . bin2hex( substr( $test_result['body'], 0, 3 ) ) );
		return __( 'WebP response failed mime-type test. Purge all caches and try again.', 'ewww-image-optimizer' );
	}

	// Run the "negative" test, which should receive the original PNG image.
	$test_result = wp_remote_get( $test_url );
	if ( is_wp_error( $test_result ) ) {
		$error_message = $test_result->get_error_message();
		ewwwio_debug_message( "png verification request failed: $error_message" );
		return $error_message;
	} elseif ( empty( $test_result['body'] ) ) {
		ewwwio_debug_message( 'png verification response empty' );
		return __( 'PNG response was empty', 'ewww-image-optimizer' );
	} elseif ( strlen( $test_result['body'] ) < 300 ) {
		ewwwio_debug_message( 'png verification response too small: ' . strlen( $test_result['body'] ) );
		return __( 'PNG response was too small', 'ewww-image-optimizer' );
	} elseif ( empty( $test_result['response']['code'] ) ) {
		ewwwio_debug_message( 'png test received unknown response code' );
		return __( 'PNG response status code missing', 'ewww-image-optimizer' );
	} elseif ( 200 !== (int) $test_result['response']['code'] ) {
		ewwwio_debug_message( 'png test received response code: ' . $test_result['response']['code'] );
		/* translators: %d: the HTTP status code */
		return sprintf( __( 'PNG response received status %d', 'ewww-image-optimizer' ), $test_result['response']['code'] );
	} elseif ( '89504e470d0a1a0a' === bin2hex( substr( $test_result['body'], 0, 8 ) ) ) {
		ewwwio_debug_message( 'png (real-world) verification succeeded' );
		$negative_test = true;
	} else {
		ewwwio_debug_message( 'png mime check failed: ' . bin2hex( substr( $test_result['body'], 0, 3 ) ) );
		return __( 'PNG response failed mime-type test', 'ewww-image-optimizer' );
	}
	if ( ! $positive_test || ! $negative_test ) {
		ewwwio_debug_message( 'no idea what happened' );
		return __( 'WebP validation failed for an unknown reason', 'ewww-image-optimizer' );
	}
	return '';
}

/**
 * Figure out where the .htaccess file should live.
 *
 * @return string The path to the .htaccess file.
 */
function ewww_image_optimizer_htaccess_path() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$htpath = get_home_path();
	if ( get_option( 'siteurl' ) !== get_option( 'home' ) ) {
		ewwwio_debug_message( 'WordPress Address and Site Address are different, possible subdir install' );
		$path_diff = str_replace( get_option( 'home' ), '', get_option( 'siteurl' ) );
		$newhtpath = trailingslashit( rtrim( $htpath, '/' ) . '/' . ltrim( $path_diff, '/' ) ) . '.htaccess';
		if ( ewwwio_is_file( $newhtpath ) ) {
			ewwwio_debug_message( 'subdir install confirmed' );
			ewwwio_debug_message( "using $newhtpath" );
			return $newhtpath;
		}
	}
	ewwwio_debug_message( "using $htpath.htaccess" );
	return $htpath . '.htaccess';
}

/**
 * Called via AJAX, adds WebP rewrite rules to the .htaccess file.
 */
function ewww_image_optimizer_webp_rewrite() {
	ewwwio_ob_clean();
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that the user is properly authorized.
	if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-settings' ) ) {
		die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	$ewww_rules = ewww_image_optimizer_webp_rewrite_verify();
	if ( $ewww_rules ) {
		if ( insert_with_markers( ewww_image_optimizer_htaccess_path(), 'EWWWIO', $ewww_rules ) && ! ewww_image_optimizer_webp_rewrite_verify() ) {
			$webp_mime_error = ewww_image_optimizer_test_webp_mime_error();
			if ( empty( $webp_mime_error ) ) {
				die( esc_html__( 'Insertion successful', 'ewww-image-optimizer' ) );
			}
			die(
				sprintf(
					/* translators: %s: an error message from the WebP self-test */
					esc_html__( 'Insertion successful, but self-test failed: %s', 'ewww-image-optimizer' ),
					esc_html( $webp_mime_error )
				)
			);
		}
		die( esc_html__( 'Insertion failed', 'ewww-image-optimizer' ) );
	}
	die( esc_html__( 'Insertion aborted', 'ewww-image-optimizer' ) );
}

/**
 * Called via AJAX, removes WebP rewrite rules from the .htaccess file.
 */
function ewww_image_optimizer_webp_unwrite() {
	ewwwio_ob_clean();
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that the user is properly authorized.
	if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-settings' ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	if ( insert_with_markers( ewww_image_optimizer_htaccess_path(), 'EWWWIO', '' ) ) {
		esc_html_e( 'Removal successful', 'ewww-image-optimizer' );
	} else {
		esc_html_e( 'Removal failed', 'ewww-image-optimizer' );
	}
	die();
}

/**
 * If rules are present, stay silent, otherwise, gives us some rules to insert!
 *
 * @return array Rules to be inserted.
 */
function ewww_image_optimizer_webp_rewrite_verify() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ewww_image_optimizer_easy_active() || ewwwio_is_cf_host() || isset( $_SERVER['cw_allowed_ip'] ) ) {
		if ( ewwwio_extract_from_markers( ewww_image_optimizer_htaccess_path(), 'EWWWIO' ) ) {
			ewwwio_debug_message( 'removing htaccess webp to prevent EasyIO/Cloudflare/Clouways problems' );
			insert_with_markers( ewww_image_optimizer_htaccess_path(), 'EWWWIO', '' );
		}
		return;
	}
	if ( ewww_image_optimizer_wpfc_webp_enabled() ) {
		return;
	}
	$current_rules = ewwwio_extract_from_markers( ewww_image_optimizer_htaccess_path(), 'EWWWIO' );
	$ewww_rules    = array(
		'<IfModule mod_rewrite.c>',
		'RewriteEngine On',
		'RewriteCond %{HTTP_ACCEPT} image/webp',
		'RewriteCond %{REQUEST_FILENAME} (.*)\.(jpe?g|png|gif)$',
		'RewriteCond %{REQUEST_FILENAME}.webp -f',
		'RewriteCond %{QUERY_STRING} !type=original',
		'RewriteRule (.+)\.(jpe?g|png|gif)$ %{REQUEST_URI}.webp [T=image/webp,L]',
		'</IfModule>',
		'<IfModule mod_headers.c>',
		'<FilesMatch "\.(jpe?g|png|gif)$">',
		'Header append Vary Accept',
		'</FilesMatch>',
		'</IfModule>',
		'AddType image/webp .webp',
	);
	if ( is_array( $current_rules ) ) {
		ewwwio_debug_message( 'current rules: ' . implode( '<br>', $current_rules ) );
	}
	if ( empty( $current_rules ) ||
		! ewww_image_optimizer_array_search( '{HTTP_ACCEPT} image/webp', $current_rules ) ||
		! ewww_image_optimizer_array_search( '{REQUEST_FILENAME}.webp', $current_rules ) ||
		! ewww_image_optimizer_array_search( 'Header append Vary Accept', $current_rules ) ||
		ewww_image_optimizer_array_search( 'Header append Vary Accept env=REDIRECT', $current_rules ) ||
		! ewww_image_optimizer_array_search( 'AddType image/webp', $current_rules )
	) {
		ewwwio_debug_message( 'missing or invalid rules' );
		return $ewww_rules;
	} else {
		ewwwio_debug_message( 'all good' );
		return;
	}
}

/**
 * Extracts strings from between the BEGIN and END markers in the .htaccess file.
 *
 * @global int $wp_version
 *
 * @param string $filename The file within which to search.
 * @param string $marker The bounary marker of the desired content.
 * @return array An array of strings from a file (.htaccess ) from between BEGIN and END markers.
 */
function ewwwio_extract_from_markers( $filename, $marker ) {
	// All because someone didn't test changes in core...
	global $wp_version;
	if ( '4.9' !== $wp_version ) {
		return extract_from_markers( $filename, $marker );
	}
	$result = array();

	if ( ! file_exists( $filename ) ) {
		return $result;
	}

	$markerdata = explode( "\n", implode( '', file( $filename ) ) );

	$state = false;
	foreach ( $markerdata as $markerline ) {
		if ( false !== strpos( $markerline, '# END ' . $marker ) ) {
			$state = false;
		}
		if ( $state ) {
			$result[] = $markerline;
		}
		if ( false !== strpos( $markerline, '# BEGIN ' . $marker ) ) {
			$state = true;
		}
	}
	return $result;
}

/**
 * Looks for a certain string within all array elements.
 *
 * @param string $needle The searched value.
 * @param array  $haystack The array to search.
 * @return bool True if the needle is found, false otherwise.
 */
function ewww_image_optimizer_array_search( $needle, $haystack ) {
	if ( ! is_array( $haystack ) ) {
		return false;
	}
	foreach ( $haystack as $straw ) {
		if ( ! is_string( $straw ) ) {
			continue;
		}
		if ( strpos( $straw, $needle ) !== false ) {
			return true;
		}
	}
	return false;
}

/**
 * Clear output buffers without throwing a fit.
 */
function ewwwio_ob_clean() {
	if ( ob_get_length() ) {
		ob_end_clean();
	}
}

/**
 * Retrieves a list of registered image sizes.
 *
 * @global array $_wp_additional_image_sizes
 *
 * @return array A list if image sizes.
 */
function ewww_image_optimizer_get_image_sizes() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $_wp_additional_image_sizes;
	$sizes       = array();
	$image_sizes = get_intermediate_image_sizes();
	if ( is_array( $image_sizes ) ) {
		ewwwio_debug_message( 'sizes: ' . implode( '<br>', $image_sizes ) );
	}
	if ( ewww_image_optimizer_iterable( $image_sizes ) ) {
		foreach ( $image_sizes as $_size ) {
			if ( in_array( $_size, array( 'thumbnail', 'medium', 'medium_large', 'large' ), true ) ) {
				$sizes[ $_size ]['width']  = get_option( $_size . '_size_w' );
				$sizes[ $_size ]['height'] = get_option( $_size . '_size_h' );
				$sizes[ $_size ]['crop']   = get_option( $_size . '_crop' );
				if ( 'medium_large' === $_size && 0 === intval( $sizes[ $_size ]['width'] ) ) {
					$sizes[ $_size ]['width'] = '768';
				}
				if ( 'medium_large' === $_size && 0 === intval( $sizes[ $_size ]['height'] ) ) {
					$sizes[ $_size ]['height'] = '9999';
				}
			} elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
				$sizes[ $_size ] = array(
					'width'  => $_wp_additional_image_sizes[ $_size ]['width'],
					'height' => $_wp_additional_image_sizes[ $_size ]['height'],
					'crop'   => $_wp_additional_image_sizes[ $_size ]['crop'],
				);
			}
		}
	}
	$sizes['pdf-full'] = array(
		'width'  => 99999,
		'height' => 99999,
		'crop'   => false,
	);

	if ( ewww_image_optimizer_function_exists( 'print_r' ) ) {
		ewwwio_debug_message( print_r( $sizes, true ) );
	}
	return $sizes;
}

/**
 * Check if a given ip is in a network. https://gist.github.com/tott/7684443
 *
 * @param  string $ip    IP to check in IPV4 format.
 * @param  string $range IP/CIDR netmask.
 * @return boolean True if the IP is in this range, false if not.
 */
function ewwwio_ip_in_range( $ip, $range ) {
	if ( false === strpos( $range, '/' ) ) {
		$range .= '/32';
	}

	list( $range, $netmask ) = explode( '/', $range, 2 );

	$range_decimal    = ip2long( $range );
	$ip_decimal       = ip2long( $ip );
	$wildcard_decimal = pow( 2, ( 32 - $netmask ) ) - 1;
	$netmask_decimal  = ~ $wildcard_decimal;
	return ( ( $ip_decimal & $netmask_decimal ) === ( $range_decimal & $netmask_decimal ) );
}

/**
 * Test if the site is protected by Cloudflare.
 *
 * @return bool True if it is, false if it ain't.
 */
function ewwwio_is_cf_host() {
	$cf_ips = array(
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
	);
	if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
		ewwwio_debug_message( 'found Cloudflare host via HTTP_CF_IPCOUNTRY' );
		return true;
	}
	if ( ! empty( $_SERVER['HTTP_CF_RAY'] ) ) {
		ewwwio_debug_message( 'found Cloudflare host via HTTP_CF_RAY' );
		return true;
	}
	if ( ! empty( $_SERVER['HTTP_CF_VISITOR'] ) ) {
		ewwwio_debug_message( 'found Cloudflare host via HTTP_CF_VISITOR' );
		return true;
	}
	if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
		ewwwio_debug_message( 'found Cloudflare host via HTTP_CF_CONNECTING_IP' );
		return true;
	}
	if ( ! empty( $_SERVER['HTTP_CF_REQUEST_ID'] ) ) {
		ewwwio_debug_message( 'found Cloudflare host via HTTP_CF_REQUEST_ID' );
		return true;
	}
	if ( ! empty( $_SERVER['HTTP_CDN_LOOP'] ) && 'cloudflare' === $_SERVER['HTTP_CDN_LOOP'] ) {
		ewwwio_debug_message( 'found Cloudflare host via HTTP_CDN_LOOP' );
		return true;
	}
	$eio_base = new EIO_Base();
	$hostname = $eio_base->parse_url( get_site_url(), PHP_URL_HOST );
	$home_ip  = gethostbyname( $hostname );
	ewwwio_debug_message( "checking $home_ip from gethostbyname" );
	foreach ( $cf_ips as $cf_range ) {
		if ( ewwwio_ip_in_range( $home_ip, $cf_range ) ) {
			ewwwio_debug_message( "found Cloudflare host: $home_ip" );
			return true;
		}
	}
	ewwwio_debug_message( "not a Cloudflare host: $home_ip" );
	return false;
	// Double-check via Cloudflare DNS. Disabled for now, we'll see if we need to cross that bridge later.
	$home_ip_lookup = wp_remote_get( 'https://cloudflare-dns.com/dns-query?name=' . urlencode( $hostname ) . '&type=A&ct=' . urlencode( 'application/dns-json' ) );
	if ( ! is_wp_error( $home_ip_lookup ) && ! empty( $home_ip_lookup['body'] ) && is_string( $home_ip_lookup['body'] ) ) {
		$home_ip_data = json_decode( $home_ip_lookup['body'], true );
		if ( is_array( $home_ip_data ) && ! empty( $home_ip_data['Answer'][0]['data'] ) && filter_var( $home_ip_data['Answer'][0]['data'], FILTER_VALIDATE_IP ) ) {
			$home_ip = $home_ip_data['Answer'][0]['data'];
			ewwwio_debug_message( "checking $home_ip from CF DoH" );
			foreach ( $cf_ips as $cf_range ) {
				if ( ewwwio_ip_in_range( $home_ip, $cf_range ) ) {
					ewwwio_debug_message( "found Cloudflare host: $home_ip" );
					return true;
				}
			}
		}
	}
}

/**
 * Send our debug information to the log/buffer for the options page (and friends).
 */
function ewwwio_debug_info() {
	global $ewwwio_upgrading;
	global $content_width;
	ewwwio_debug_version_info();
	ewwwio_debug_message( 'ABSPATH: ' . ABSPATH );
	ewwwio_debug_message( 'WP_CONTENT_DIR: ' . WP_CONTENT_DIR );
	ewwwio_debug_message( 'home url (Site URL): ' . get_home_url() );
	ewwwio_debug_message( 'site url (WordPress URL): ' . get_site_url() );
	$upload_info = wp_get_upload_dir();
	ewwwio_debug_message( 'wp_upload_dir (baseurl): ' . $upload_info['baseurl'] );
	ewwwio_debug_message( 'wp_upload_dir (basedir): ' . $upload_info['basedir'] );
	ewwwio_debug_message( "content_width: $content_width" );
	ewwwio_debug_message( 'registered stream wrappers: ' . implode( ',', stream_get_wrappers() ) );
	if ( is_multisite() ) {
		ewwwio_debug_message( 'allowing multisite override: ' . ( get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) ? 'yes' : 'no' ) );
	}
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_NOEXEC' ) ) {
		if ( defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) && EWWW_IMAGE_OPTIMIZER_CLOUD ) {
			ewww_image_optimizer_disable_tools();
		} else {
			ewww_image_optimizer_tool_init();
			ewww_image_optimizer_notice_utils( 'quiet' );
		}
	}
	if ( wp_using_ext_object_cache() ) {
		ewwwio_debug_message( 'using external object cache' );
	} else {
		ewwwio_debug_message( 'not external cache' );
	}
	ewww_image_optimizer_gd_support( false );
	ewww_image_optimizer_gmagick_support();
	ewww_image_optimizer_imagick_support();
	if ( PHP_OS !== 'WINNT' && ! ewww_image_optimizer_full_cloud() && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
		ewww_image_optimizer_find_nix_binary( 'nice', 'n' );
	}
	ewwwio_debug_message( ewww_image_optimizer_aux_images_table_count() . ' images have been optimized' );
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_noauto' ) ) {
		ewwwio_debug_message( 'automatic compression disabled' );
	} else {
		ewwwio_debug_message( 'automatic compression enabled' );
	}
	ewwwio_debug_message( 'remove metadata: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'jpg level: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) );
	ewwwio_debug_message( 'png level: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) );
	ewwwio_debug_message( 'gif level: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) );
	ewwwio_debug_message( 'pdf level: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) );
	ewwwio_debug_message( 'svg level: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_svg_level' ) );
	ewwwio_debug_message( 'bulk delay: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) );
	ewwwio_debug_message( 'backup mode: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'cloudinary upload: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_cloudinary' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'ExactDN enabled: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'ExactDN all the things: ' . ( ewww_image_optimizer_get_option( 'exactdn_all_the_things' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'ExactDN lossy: ' . intval( ewww_image_optimizer_get_option( 'exactdn_lossy' ) ) );
	ewwwio_debug_message( 'ExactDN resize existing: ' . ( ewww_image_optimizer_get_option( 'exactdn_resize_existing' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'ExactDN attachment queries: ' . ( ewww_image_optimizer_get_option( 'exactdn_prevent_db_queries' ) ? 'off' : 'on' ) );
	ewwwio_debug_message( 'Easy IO exclusions:' );
	$eio_exclude_paths = ewww_image_optimizer_get_option( 'exactdn_exclude' ) ? esc_html( implode( "\n", ewww_image_optimizer_get_option( 'exactdn_exclude' ) ) ) : '';
	ewwwio_debug_message( $eio_exclude_paths );
	ewwwio_debug_message( 'add missing dimensions: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_add_missing_dims' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'lazy load: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ? 'on' : 'off' ) );
	ewwwio_other_lazy_detected();
	ewwwio_debug_message( 'LL autoscale: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_ll_autoscale' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'LQIP: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_use_lqip' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'S(VG)IIP: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_use_siip' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'external CSS background (all things): ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_ll_all_things' ) );
	ewwwio_debug_message( 'LL exclusions:' );
	$ll_exclude_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_ll_exclude' ) ? esc_html( implode( "\n", ewww_image_optimizer_get_option( 'ewww_image_optimizer_ll_exclude' ) ) ) : '';
	ewwwio_debug_message( $ll_exclude_paths );
	if ( ! ewww_image_optimizer_full_cloud() ) {
		ewwwio_debug_message( 'optipng level: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_optipng_level' ) );
		ewwwio_debug_message( 'pngout disabled: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_pngout' ) ? 'yes' : 'no' ) );
		ewwwio_debug_message( 'pngout level: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_pngout_level' ) );
		ewwwio_debug_message( 'svgcleaner disabled: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_svgcleaner' ) ? 'yes' : 'no' ) );
	}
	ewwwio_debug_message( 'configured quality: ' . ewww_image_optimizer_set_jpg_quality( 82 ) );
	ewwwio_debug_message( 'effective quality: ' . apply_filters( 'jpeg_quality', 82, 'image_resize' ) );
	ewwwio_debug_message( 'effective WebP quality: ' . ewww_image_optimizer_set_webp_quality( 75 ) );
	ewwwio_debug_message( 'parallel optimization: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_parallel_optimization' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'background optimization: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_background_optimization' ) ? 'on' : 'off' ) );
	if ( ! $ewwwio_upgrading && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_background_optimization' ) ) {
		$admin_ajax_url = admin_url( 'admin-ajax.php' );
		if ( strpos( $admin_ajax_url, 'admin-ajax.php' ) ) {
			ewwwio_debug_message( "admin ajax url: $admin_ajax_url" );
			$admin_ajax_host = parse_url( $admin_ajax_url, PHP_URL_HOST );
			ewwwio_debug_message( "admin ajax hostname: $admin_ajax_host" );
			$resolved = gethostbyname( $admin_ajax_host . '.' );
			ewwwio_debug_message( "resolved to $resolved" );
			if ( $resolved === $admin_ajax_host . '.' ) {
				ewwwio_debug_message( 'DNS lookup failed' );
			} else {
				$admin_ajax_url = add_query_arg(
					array(
						'action' => 'wp_ewwwio_test_optimize',
						'nonce'  => wp_create_nonce( 'wp_ewwwio_test_optimize' ),
					),
					$admin_ajax_url
				);
				ewwwio_debug_message( "admin ajax POST url: $admin_ajax_url" );
				$async_post_args = array(
					'body'      => array(
						'ewwwio_test_verify' => '949c34123cf2a4e4ce2f985135830df4a1b2adc24905f53d2fd3f5df5b16293245',
					),
					'cookies'   => $_COOKIE,
					'sslverify' => false,
				);
				// Don't lock up other requests while processing.
				session_write_close();
				$async_response = wp_remote_post( esc_url_raw( $admin_ajax_url ), $async_post_args );
				if ( is_wp_error( $async_response ) ) {
					$error_message = $async_response->get_error_message();
					ewwwio_debug_message( "async test failed: $error_message" );
				} elseif ( is_array( $async_response ) && isset( $async_response['body'] ) ) {
					ewwwio_debug_message( 'async success, possibly (response should be empty): ' . esc_html( substr( $async_response['body'], 0, 100 ) ) );
					if ( ! empty( $async_response['response']['code'] ) ) {
						ewwwio_debug_message( 'async response code: ' . $async_response['response']['code'] );
					}
				} else {
					ewwwio_debug_message( 'no async error, but no body either' );
				}
			}
		} else {
			ewwwio_debug_message( "invalid admin ajax url: $admin_ajax_url" );
		}
	}
	ewwwio_debug_message( 'scheduled optimization: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_auto' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'include media library: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_media_paths' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'include originals: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_originals' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'folders to optimize:' );
	$aux_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_aux_paths' ) ? esc_html( implode( "\n", ewww_image_optimizer_get_option( 'ewww_image_optimizer_aux_paths' ) ) ) : '';
	ewwwio_debug_message( $aux_paths );
	ewwwio_debug_message( 'folders to ignore:' );
	$exclude_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_exclude_paths' ) ? esc_html( implode( "\n", ewww_image_optimizer_get_option( 'ewww_image_optimizer_exclude_paths' ) ) ) : '';
	ewwwio_debug_message( $exclude_paths );
	ewwwio_debug_message( 'skip images smaller than: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_size' ) . ' bytes' );
	ewwwio_debug_message( 'skip PNG images larger than: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) . ' bytes' );
	ewwwio_debug_message( 'exclude originals from lossy: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_lossy_skip_full' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'exclude originals from metadata removal: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_skip_full' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'use system binaries: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_bundle' ) ? 'yes' : 'no' ) );
	ewwwio_debug_message( 'resize detection: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_detection' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'max media dimensions: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' ) . ' x ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' ) );
	ewwwio_debug_message( 'max other dimensions: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherwidth' ) . ' x ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherheight' ) );
	ewwwio_debug_message( 'resize existing images: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_existing' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'resize existing (non-media) images: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_other_existing' ) ? 'on' : 'off' ) );
	$image_sizes = ewww_image_optimizer_get_image_sizes();
	ewwwio_debug_message( 'disabled resizes:' );
	$disabled_sizes     = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes', false, true );
	$disabled_sizes_opt = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt', false, true );
	foreach ( $image_sizes as $size => $dimensions ) {
		if ( empty( $dimensions['width'] ) && empty( $dimensions['height'] ) ) {
			continue;
		}
		ewwwio_debug_message( $size . ': ' . ( ! empty( $disabled_sizes_opt[ $size ] ) ? 'optimization=X ' : 'optimization=+ ' ) . ( ! empty( $disabled_sizes[ $size ] ) ? 'creation=X' : 'creation=+' ) );
	}
	ewwwio_debug_message( 'delete originals: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'jpg2png: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_to_png' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'png2jpg: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_to_jpg' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'gif2png: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_to_png' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'png2jpg fill:' );
	ewww_image_optimizer_jpg_background();
	ewwwio_debug_message( 'webp conversion: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'js webp rewriting: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'picture webp rewriting: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_picture_webp' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'WebP Rewrite exclusions:' );
	$webp_exclude_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_rewrite_exclude' ) ? esc_html( implode( "\n", ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_rewrite_exclude' ) ) ) : '';
	ewwwio_debug_message( $webp_exclude_paths );
	ewwwio_debug_message( 'webp paths:' );
	$webp_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' ) ? esc_html( implode( "\n", ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' ) ) ) : '';
	ewwwio_debug_message( $webp_paths );
	ewwwio_debug_message( 'forced webp: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ? 'on' : 'off' ) );
	if ( ewww_image_optimizer_cloud_based_media() ) {
		ewwwio_debug_message( 'cloud-based media (no local copies), force webp auto-enabled' );
	}
	ewwwio_debug_message( 'forced gif2webp: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_force_gif2webp' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'enable help beacon: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_help' ) ? 'yes' : 'no' ) );
	if ( ! empty( $_SERVER['SERVER_ADDR'] ) ) {
		ewwwio_debug_message( 'origin (SERVER_ADDR): ' . sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) ) );
	}
	if (
		! ewwwio_is_cf_host() &&
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_picture_webp' ) &&
		! ewww_image_optimizer_ce_webp_enabled() &&
		! ewww_image_optimizer_swis_webp_enabled() &&
		! ewww_image_optimizer_easy_active()
	) {
		if ( defined( 'PHP_SAPI' ) ) {
			ewwwio_debug_message( 'PHP module: ' . PHP_SAPI );
		}
		if ( ! apache_mod_loaded( 'mod_rewrite' ) ) {
			ewwwio_debug_message( 'possibly missing mod_rewrite' );
		}
		if ( ! apache_mod_loaded( 'mod_headers' ) ) {
			ewwwio_debug_message( 'possibly missing mod_headers' );
		}
		if ( ! ewww_image_optimizer_test_webp_mime_error() || ! ewww_image_optimizer_webp_rewrite_verify() ) {
			ewwwio_debug_message( 'webp .htaccess rewriting enabled' );
		} else {
			ewwwio_debug_message( 'webp .htaccess rules not detected' );
		}
	}
	if ( ewww_image_optimizer_function_exists( 'ini_get' ) ) {
		ewwwio_debug_message( 'max_execution_time: ' . ini_get( 'max_execution_time' ) );
	}
	ewww_image_optimizer_stl_check();
	ewww_image_optimizer_function_exists( 'sleep', true );
	ewwwio_check_memory_available();
}

/**
 * Displays the EWWW IO wizard/intro to assist the user in setting up the plugin initially.
 */
function ewww_image_optimizer_intro_wizard() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $exactdn;
	$display_exec_notice  = false;
	$tools_missing_notice = false;
	$current_jpeg_quality = apply_filters( 'jpeg_quality', 82, 'image_resize' );
	$wizard_complete_url  = wp_nonce_url( add_query_arg( 'complete_wizard', 1, ewww_image_optimizer_get_settings_link() ), 'ewww_image_optimizer_options-options' );
	$settings_page_url    = ewww_image_optimizer_get_settings_link();
	$loading_image_url    = plugins_url( '/images/spinner.gif', __FILE__ );
	$wizard_step          = 1;
	$show_premium         = false;
	$eio_base             = new EIO_Base();
	$easyio_site_url      = $eio_base->content_url();
	$no_tracking          = false;
	$webp_available       = ewww_image_optimizer_webp_available();
	$bulk_available       = false;
	$tools_available      = true;
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_NOEXEC' ) ) {
		if ( defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) && EWWW_IMAGE_OPTIMIZER_CLOUD ) {
			ewww_image_optimizer_disable_tools();
		} else {
			ewww_image_optimizer_tool_init();
			ewww_image_optimizer_notice_utils( 'quiet' );
		}
	}
	if (
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ||
		ewww_image_optimizer_easy_active() ||
		! empty( $_GET['show-premium'] )
	) {
		$show_premium = true;
	} elseif (
		ewww_image_optimizer_exec_check() &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_dismiss_exec_notice' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) &&
		! ewww_image_optimizer_easy_active()
	) {
		$display_exec_notice = true;
	} elseif (
		( defined( 'EWWW_IMAGE_OPTIMIZER_JPEGTRAN' ) && ! EWWW_IMAGE_OPTIMIZER_JPEGTRAN ) ||
		( defined( 'EWWW_IMAGE_OPTIMIZER_OPTIPNG' ) && ! EWWW_IMAGE_OPTIMIZER_OPTIPNG ) ||
		( defined( 'EWWW_IMAGE_OPTIMIZER_GIFSICLE' ) && ! EWWW_IMAGE_OPTIMIZER_GIFSICLE )
	) {
		$tools_missing   = array();
		$tools_available = false;
		if ( defined( 'EWWW_IMAGE_OPTIMIZER_JPEGTRAN' ) && ! EWWW_IMAGE_OPTIMIZER_JPEGTRAN ) {
			$tools_missing[] = 'jpegtran';
		} elseif ( defined( 'EWWW_IMAGE_OPTIMIZER_JPEGTRAN' ) ) {
			$tools_available = true;
		}
		if ( defined( 'EWWW_IMAGE_OPTIMIZER_OPTIPNG' ) && ! EWWW_IMAGE_OPTIMIZER_OPTIPNG ) {
			$tools_missing[] = 'optipng';
		} elseif ( defined( 'EWWW_IMAGE_OPTIMIZER_OPTIPNG' ) ) {
			$tools_available = true;
		}
		if ( defined( 'EWWW_IMAGE_OPTIMIZER_GIFSICLE' ) && ! EWWW_IMAGE_OPTIMIZER_GIFSICLE ) {
			$tools_missing[] = 'gifsicle';
		} elseif ( defined( 'EWWW_IMAGE_OPTIMIZER_GIFSICLE' ) ) {
			$tools_available = true;
		}
		$tools_missing_notice = true;
		// Expand the missing utilities list for use in the error message.
		$tools_missing_message = implode( ', ', $tools_missing );
	}
	if (
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ||
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ||
		! ewww_image_optimizer_exec_check()
	) {
		$bulk_available = true;
	}
	if (
		stristr( network_site_url( '/' ), '.local' ) !== false ||
		stristr( network_site_url( '/' ), 'dev' ) !== false ||
		stristr( network_site_url( '/' ), 'localhost' ) !== false ||
		stristr( network_site_url( '/' ), ':8888' ) !== false // This is common with MAMP on OS X.
	) {
		$no_tracking = true;
	}
	if ( ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_wizard' ) ) {
		if ( ! empty( $_POST['ewwwio_wizard_step'] ) ) {
			$wizard_step = (int) $_POST['ewwwio_wizard_step'];
		}
		if ( ! empty( $_POST['ewww_image_optimizer_goal_save_space'] ) ) {
			update_option( 'ewww_image_optimizer_goal_save_space', true, false );
		} else {
			update_option( 'ewww_image_optimizer_goal_save_space', false, false );
		}
		if ( ! empty( $_POST['ewww_image_optimizer_goal_site_speed'] ) ) {
			update_option( 'ewww_image_optimizer_goal_site_speed', true, false );
		} else {
			update_option( 'ewww_image_optimizer_goal_site_speed', false, false );
		}
		if ( ! empty( $_POST['ewww_image_optimizer_budget'] ) && 'free' === $_POST['ewww_image_optimizer_budget'] ) {
			if ( $display_exec_notice ) {
				ewww_image_optimizer_enable_free_exec();
			}
			if ( defined( 'EWWW_IMAGE_OPTIMIZER_OPTIPNG' ) && ! EWWW_IMAGE_OPTIMIZER_OPTIPNG ) {
				ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_level', 0 );
			}
			if ( defined( 'EWWW_IMAGE_OPTIMIZER_GIFSICLE' ) && ! EWWW_IMAGE_OPTIMIZER_GIFSICLE ) {
				ewww_image_optimizer_set_option( 'ewww_image_optimizer_gif_level', 0 );
			}
			if ( $tools_missing_notice && ! $tools_available ) {
				ewww_image_optimizer_enable_free_exec();
			}
		}
		if ( 3 === $wizard_step ) {
			ewww_image_optimizer_set_option( 'ewww_image_optimizer_metadata_remove', ! empty( $_POST['ewww_image_optimizer_metadata_remove'] ) );
			if ( ! empty( $_POST['ewww_image_optimizer_jpg_quality'] ) ) {
				ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_quality', (int) $_POST['ewww_image_optimizer_jpg_quality'] );
			}
			ewww_image_optimizer_set_option( 'ewww_image_optimizer_lazy_load', ! empty( $_POST['ewww_image_optimizer_lazy_load'] ) );
			ewww_image_optimizer_set_option( 'ewww_image_optimizer_webp', ! empty( $_POST['ewww_image_optimizer_webp'] ) );
			if ( ! empty( $_POST['ewww_image_optimizer_maxmediawidth'] ) ) {
				ewww_image_optimizer_set_option( 'ewww_image_optimizer_maxmediawidth', (int) $_POST['ewww_image_optimizer_maxmediawidth'] );
			}
			if ( ! empty( $_POST['ewww_image_optimizer_maxmediaheight'] ) ) {
				ewww_image_optimizer_set_option( 'ewww_image_optimizer_maxmediaheight', (int) $_POST['ewww_image_optimizer_maxmediaheight'] );
			}
			ewww_image_optimizer_set_option( 'ewww_image_optimizer_enable_help', ! empty( $_POST['ewww_image_optimizer_enable_help'] ) );
			ewww_image_optimizer_set_option( 'ewww_image_optimizer_allow_tracking', ! empty( $_POST['ewww_image_optimizer_allow_tracking'] ) );
			update_option( 'ewww_image_optimizer_wizard_complete', true, false );
			global $eio_debug;
			$debug_info = '';
			if ( ! empty( $eio_debug ) ) {
				$debug_info = $eio_debug;
			}
		}
		wp_add_inline_script(
			'ewww-settings-script',
			'ewww_vars.save_space = ' . ( ! empty( $_POST['ewww_image_optimizer_goal_save_space'] ) ? 1 : 0 ) . ";\n" .
			'ewww_vars.site_speed = ' . ( ! empty( $_POST['ewww_image_optimizer_goal_site_speed'] ) ? 1 : 0 ) . ";\n"
		);
	}
	$cloud_key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	?>
<div id='ewww-settings-wrap' class='wrap'>
	<div id='ewwwio-wizard'>
		<div id="ewwwio-wizard-header">
			<img height="95" width="167" src="<?php echo esc_url( plugins_url( '/images/ewwwio-logo.png', __FILE__ ) ); ?>">
		</div>
		<div id='ewwwio-wizard-body'>
	<?php if ( 1 === $wizard_step ) : ?>
			<form id='ewwwio-wizard-step-1' class='ewwwio-wizard-form' method='post' action=''>
				<input type='hidden' name='ewwwio_wizard_step' value='2' />
				<?php wp_nonce_field( 'ewww_image_optimizer_wizard' ); ?>
				<div class='ewwwio-intro-text'><?php esc_html_e( 'In order to recommend the best settings for your site, please select which goal(s) are most important:', 'ewww-image-optimizer' ); ?></div>
				<div class='ewwwio-wizard-form-group'>
					<input type='checkbox' id='ewww_image_optimizer_goal_site_speed' name='ewww_image_optimizer_goal_site_speed' value='true' required />
					<label for='ewww_image_optimizer_goal_site_speed'><?php esc_html_e( 'Speed up your site', 'ewww-image-optimizer' ); ?></label><br>
					<input type='checkbox' id='ewww_image_optimizer_goal_save_space' name='ewww_image_optimizer_goal_save_space' value='true' required />
					<label for='ewww_image_optimizer_goal_save_space'><?php esc_html_e( 'Save storage space', 'ewww-image-optimizer' ); ?></label>
				</div>
				<div class='ewwwio-wizard-form-group'>
					<input type='radio' id='ewww_image_optimizer_budget_pay' name='ewww_image_optimizer_budget' value='pay' required <?php checked( $show_premium ); ?>/>
					<label for='ewww_image_optimizer_budget_pay'><?php esc_html_e( 'Activate 5x more optimization and priority support', 'ewww-image-optimizer' ); ?></label><br>
					<div class="ewwwio-wizard-form-group ewwwio-premium-setup" <?php echo ( $show_premium ? "style='display:block'" : '' ); ?>>
						<?php /* translators: 1: free trial (link) 2: service (link to account) */ ?>
						<p><strong>&gt;&gt;<?php printf( esc_html__( 'Start your %1$s or activate your %2$s below', 'ewww-image-optimizer' ), "<a href='https://ewww.io/plans/' target='_blank'>" . esc_html__( 'free trial', 'ewww-image-optimizer' ) . '</a>', "<a href='https://ewww.io/manage-keys/' target='_blank'>" . esc_html__( 'service', 'ewww-image-optimizer' ) . '</a>' ); ?></strong></p>
						<div id='ewwwio-api-activation-result'></div>
						<p id='ewww_image_optimizer_cloud_key_container'>
							<label for='ewww_image_optimizer_cloud_key'><?php esc_html_e( 'Compress API Key', 'ewww-image-optimizer' ); ?></label><br>
		<?php if ( empty( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) ) : ?>
							<input type='text' id='ewww_image_optimizer_cloud_key' name='ewww_image_optimizer_cloud_key' value='' />
							<span id='ewwwio-api-activate'><a href='#' class='button-secondary'><?php esc_html_e( 'Activate', 'ewww-image-optimizer' ); ?></a></span>
							<span id='ewwwio-api-activation-processing'><img src='<?php echo esc_url( $loading_image_url ); ?>' alt='loading'/></span>
		<?php else : ?>
							<input type='text' id='ewww_image_optimizer_cloud_key' name='ewww_image_optimizer_cloud_key' value='****************<?php echo esc_attr( substr( $cloud_key, 28 ) ); ?>' readonly />
							<span class="dashicons dashicons-yes"></span>
		<?php endif; ?>
							<br>
							<span class="description"><?php esc_html_e( 'Premium compression for your local images.', 'ewww-image-optimizer' ); ?></span>
						</p>
						<div id='ewwwio-easy-activation-result'></div>
						<p class='ewwwio-easy-setup-instructions'>
							<label><?php esc_html_e( 'Easy IO', 'ewww-image-optimizer' ); ?></label><br>
							<span class="description">
								<?php
								printf(
									/* translators: %s: the string 'and more' with a link to the docs */
									esc_html__( 'An image-optimizing CDN that does not modify your local images. Includes automatic compression, scaling, WebP %s.', 'ewww-image-optimizer' ),
									'<a href="https://docs.ewww.io/article/44-introduction-to-exactdn" target="_blank" data-beacon-article="59bc5ad6042863033a1ce370">' . esc_html__( 'and more', 'ewww-image-optimizer' ) . '</a>'
								);
								?>
							</span>
		<?php if ( false !== strpos( $easyio_site_url, 'localhost' ) ) : ?>
							<br><span class="description" style="font-weight: bolder"><?php esc_html_e( 'Easy IO cannot be activated on localhost installs.', 'ewww-image-optimizer' ); ?></span>
		<?php elseif ( empty( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) ) : ?>
							<br><br>
							<a href="<?php echo esc_url( add_query_arg( 'site_url', trim( $easyio_site_url ), 'https://ewww.io/manage-sites/' ) ); ?>" target="_blank">
								<?php esc_html_e( 'First, add your Site URL to your account:', 'easy-image-optimizer' ); ?>
							</a>
							<input type='text' id='exactdn_site_url' name='exactdn_site_url' value='<?php echo esc_url( trim( $easyio_site_url ) ); ?>' readonly />
							<span id='exactdn-site-url-copy'><?php esc_html_e( 'Click to Copy', 'ewww-image-optimizer' ); ?></span>
							<span id='exactdn-site-url-copied'><?php esc_html_e( 'Copied', 'ewww-image-optimizer' ); ?></span><br>
							<a id='ewwwio-easy-activate' href='#' class='button-secondary'><?php esc_html_e( 'Activate', 'ewww-image-optimizer' ); ?></a>
							<span id='ewwwio-easy-activation-processing'><img src='<?php echo esc_url( $loading_image_url ); ?>' alt='loading'/></span>
		<?php elseif ( class_exists( 'ExactDN' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) && $exactdn->get_exactdn_domain() && $exactdn->verify_domain( $exactdn->get_exactdn_domain() ) ) : ?>
							<br><span style="color: #3eadc9; font-weight: bolder"><?php esc_html_e( 'Verified', 'ewww-image-optimizer' ); ?></span>
							<span class="dashicons dashicons-yes"></span>
		<?php endif; ?>
						</p>
					</div>
					<input type='radio' id='ewww_image_optimizer_budget_free' name='ewww_image_optimizer_budget' value='free' required />
					<label for='ewww_image_optimizer_budget_free'><?php esc_html_e( 'Stick with free mode for now', 'ewww-image-optimizer' ); ?></label>
		<?php if ( $display_exec_notice ) : ?>
					<div id='ewww-image-optimizer-warning-exec' class='ewwwio-notice notice-warning' style='display:none;'>
						<?php
						printf(
							/* translators: %s: link to 'start your premium trial' */
							esc_html__( 'Your web server does not meet the requirements for free server-based compression. You may %s for 5x more compression, PNG/GIF/PDF compression, and more. Otherwise, continue with free cloud-based JPG compression.', 'ewww-image-optimizer' ),
							"<a href='https://ewww.io/plans/'>" . esc_html__( 'start your premium trial', 'ewww-image-optimizer' ) . '</a>'
						);
						ewwwio_help_link( 'https://docs.ewww.io/article/29-what-is-exec-and-why-do-i-need-it', '592dd12d0428634b4a338c39' );
						?>
					</div>
		<?php elseif ( $tools_missing_notice && $tools_available ) : ?>
					<div id='ewww-image-optimizer-warning-opt-missing' class='ewwwio-notice notice-warning' style='display:none;'>
						<?php
						printf(
							/* translators: 1: comma-separated list of missing tools 2: Installation Instructions (link) */
							esc_html__( 'EWWW Image Optimizer uses open-source tools to enable free mode, but your server is missing these: %1$s. Please install via the %2$s to get the most out of free mode.', 'ewww-image-optimizer' ),
							esc_html( $tools_missing_message ),
							"<a href='https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something' data-beacon-article='585371e3c697912ffd6c0ba1' target='_blank'>" . esc_html__( 'Installation Instructions', 'ewww-image-optimizer' ) . '</a>'
						);
						?>
					</div>
		<?php elseif ( $tools_missing_notice ) : ?>
					<div id='ewww-image-optimizer-warning-opt-missing' class='ewwwio-notice notice-warning' style='display:none;'>
						<?php
						printf(
							/* translators: %s: comma-separated list of missing tools */
							esc_html__( 'EWWW Image Optimizer uses open-source tools to enable free mode, but your server is missing these: %s.', 'ewww-image-optimizer' ),
							esc_html( $tools_missing_message )
						);
						echo '<br><br>';
						printf(
							/* translators: %s: Installation Instructions (link) */
							esc_html__( 'You may install missing tools via the %s. Otherwise, continue with free cloud-based JPG-only compression.', 'ewww-image-optimizer' ),
							"<a href='https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something' data-beacon-article='585371e3c697912ffd6c0ba1' target='_blank'>" . esc_html__( 'Installation Instructions', 'ewww-image-optimizer' ) . '</a>'
						);
						?>
					</div>
		<?php endif; ?>
				</div>
				<div class='ewwwio-flex-space-between'>
					<p><input type='submit' class='button-primary' value='<?php esc_attr_e( 'Next', 'ewww-image-optimizer' ); ?>' /></p>
					<p><a href='<?php echo esc_url( $wizard_complete_url ); ?>'><?php esc_html_e( "I know what I'm doing, leave me alone!", 'ewww-image-optimizer' ); ?></a></p>
				</div>
			</form>
	<?php elseif ( 2 === $wizard_step ) : ?>
			<form id='ewwwio-wizard-step-2' class='ewwwio-wizard-form' method='post' action=''>
				<input type='hidden' name='ewwwio_wizard_step' value='3' />
				<?php wp_nonce_field( 'ewww_image_optimizer_wizard' ); ?>
				<div class='ewwwio-intro-text'><?php esc_html_e( 'Here are the recommended settings for your site. Please review and then save the settings.', 'ewww-image-optimizer' ); ?></div>
				<p>
					<input type='checkbox' id='ewww_image_optimizer_metadata_remove' name='ewww_image_optimizer_metadata_remove' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ); ?> />
					<label for='ewww_image_optimizer_metadata_remove'><?php esc_html_e( 'Remove Metadata', 'ewww-image-optimizer' ); ?></label>
				</p>
		<?php if ( $current_jpeg_quality > 90 || $current_jpeg_quality < 50 || ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_quality' ) ) : ?>
				<p>
					<input type='text' id='ewww_image_optimizer_jpg_quality' name='ewww_image_optimizer_jpg_quality' class='small-text' value='82' />
					<label for='ewww_image_optimizer_jpg_quality'><?php esc_html_e( 'JPG Quality Level', 'ewww-image-optimizer' ); ?></label>
				</p>
		<?php endif; ?>
		<?php if ( function_exists( 'easyio_get_option' ) && easyio_get_option( 'easyio_lazy_load' ) ) : ?>
				<p>
					<input type='checkbox' id='ewww_image_optimizer_easy_lazy' name='ewww_image_optimizer_easy_lazy' value='true' checked disabled />
					<label for='ewww_image_optimizer_easy_lazy'><?php esc_html_e( 'Lazy Load (enabled in Easy IO)', 'ewww-image-optimizer' ); ?></label><br>
				</p>
		<?php elseif ( ! ewwwio_other_lazy_detected() ) : ?>
				<p>
					<input type='checkbox' id='ewww_image_optimizer_lazy_load' name='ewww_image_optimizer_lazy_load' value='true' checked />
					<label for='ewww_image_optimizer_lazy_load'><?php esc_html_e( 'Lazy Load', 'ewww-image-optimizer' ); ?></label>
				</p>
		<?php else : ?>
				<p><strong><?php esc_html_e( 'Though you have a lazy loader already, our lazy loader includes CSS background images and auto-scaling.', 'ewww-image-optimizer' ); ?></strong></p>
				<p>
					<input type='checkbox' id='ewww_image_optimizer_lazy_load' name='ewww_image_optimizer_lazy_load' value='true' />
					<label for='ewww_image_optimizer_lazy_load'><?php esc_html_e( 'Lazy Load', 'ewww-image-optimizer' ); ?></label>
				</p>
		<?php endif; ?>
		<?php if ( ! $webp_available ) : ?>
				<p>
					<input type='checkbox' id='ewww_image_optimizer_easy_webp' name='ewww_image_optimizer_easy_webp' value='true' disabled />
					<label for='ewww_image_optimizer_easy_webp'><?php esc_html_e( 'WebP Conversion', 'ewww-image-optimizer' ); ?></label><br>
				</p>
				<p>*<?php esc_html_e( 'WebP conversion requires an API key or Easy IO subscription.', 'ewww-image-optimizer' ); ?></p>
		<?php elseif ( ewww_image_optimizer_easy_active() ) : ?>
				<p>
					<input type='checkbox' id='ewww_image_optimizer_easy_webp' name='ewww_image_optimizer_easy_webp' value='true' checked disabled />
					<label for='ewww_image_optimizer_easy_webp'><?php esc_html_e( 'WebP Conversion (included with Easy IO)', 'ewww-image-optimizer' ); ?></label><br>
				</p>
		<?php else : ?>
				<p>
					<input type='checkbox' id='ewww_image_optimizer_webp' name='ewww_image_optimizer_webp' value='true' <?php checked( ! get_option( 'ewww_image_optimizer_goal_save_space' ) ); ?>/>
					<label for='ewww_image_optimizer_webp'><?php esc_html_e( 'WebP Conversion', 'ewww-image-optimizer' ); ?></label>
				</p>
				<p id='ewwwio-webp-storage-warning'>
					<i><?php esc_html_e( 'Enabling WebP Conversion without Easy IO will increase your storage requirements. Do you want to continue?', 'ewww-image-optimizer' ); ?></i><br>
					<a id='ewwwio-cancel-webp' href='#'><?php esc_html_e( 'Nevermind', 'ewww-image-optimizer' ); ?></a><br>
					<a id='ewwwio-easyio-webp-info' href='<?php echo esc_url( $settings_page_url ) . '&show-premium=1'; ?>'><?php esc_html_e( 'Tell me more about Easy IO', 'ewww-image-optimizer' ); ?></a><br>
					<span id='ewwwio-confirm-webp' class='button-primary'><?php esc_html_e( 'Continue', 'ewww-image-optimizer' ); ?></span>
				</p>
		<?php endif; ?>
		<?php if ( ! function_exists( 'imsanity_get_max_width_height' ) ) : ?>
				<p>
					<input type='number' step='10' min='0' class='small-text' id='ewww_image_optimizer_maxmediawidth' name='ewww_image_optimizer_maxmediawidth' value='<?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' ) ? (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' ) : 1920; ?>' <?php disabled( function_exists( 'imsanity_get_max_width_height' ) ); ?> />
					<label for='ewww_image_optimizer_maxmediawidth'><?php esc_html_e( 'Max Width', 'ewww-image-optimizer' ); ?></label>
					<input type='number' step='10' min='0' class='small-text' id='ewww_image_optimizer_maxmediaheight' name='ewww_image_optimizer_maxmediaheight' value='<?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' ) ? (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' ) : 1920; ?>' <?php disabled( function_exists( 'imsanity_get_max_width_height' ) ); ?> />
					<label for='ewww_image_optimizer_maxmediaheight'><?php esc_html_e( 'Max Height', 'ewww-image-optimizer' ); ?></label><br>
					<span class='description'><?php esc_html_e( 'Resize uploaded images to these dimensions (in pixels).', 'ewww-image-optimizer' ); ?></span>
				</p>
		<?php endif; ?>
				<p>
					<input type='checkbox' id='ewww_image_optimizer_enable_help' name='ewww_image_optimizer_enable_help' value='true' checked />
					<label for='ewww_image_optimizer_enable_help'><?php esc_html_e( 'Embedded Help', 'ewww-image-optimizer' ); ?></label><br>
					<span class='description'><?php esc_html_e( 'Access documentation and support from your WordPress dashboard. Uses resources from external servers.', 'ewww-image-optimizer' ); ?></span>
				</p>
		<?php if ( ! $no_tracking ) : ?>
				<p>
					<input type='checkbox' id='ewww_image_optimizer_allow_tracking' name='ewww_image_optimizer_allow_tracking' value='true' checked />
					<label for='ewww_image_optimizer_allow_tracking'><?php esc_html_e( 'Anonymous Reporting', 'ewww-image-optimizer' ); ?></label>
					<span><?php ewwwio_help_link( 'https://docs.ewww.io/article/23-usage-tracking', '591f3a8e2c7d3a057f893d91' ); ?></span><br>
					<span class='description'><?php esc_html_e( 'Send anonymized usage data to help make the plugin better. Opt-in and get a 10% discount code.', 'ewww-image-optimizer' ); ?></span>
				</p>
		<?php endif; ?>
				<div class='ewwwio-flex-space-between'>
					<p><input type='submit' class='button-primary' value='<?php esc_attr_e( 'Save Settings', 'ewww-image-optimizer' ); ?>' /></p>
					<p class='ewwwio-wizard-back'><a href='<?php echo esc_url( $settings_page_url ); ?>' class='button-secondary'><?php esc_html_e( 'Go Back', 'ewww-image-optimizer' ); ?></a></p>
				</div>
			</form>
	<?php elseif ( 3 === $wizard_step ) : ?>
			<p>
				<?php
				printf(
					/* translators: %s: Bulk Optimize (link) */
					esc_html__( 'New uploads will be optimized automatically. Optimize existing images with the %s.', 'ewww-image-optimizer' ),
					'<a href="' . esc_url( admin_url( 'upload.php?page=ewww-image-optimizer-bulk' ) ) . '">' . esc_html__( 'Bulk Optimizer', 'ewww-image-optimizer' ) . '</a>'
				);
				ewwwio_help_link( 'https://docs.ewww.io/article/4-getting-started', '5853713bc697912ffd6c0b98' );
				?>
			</p>
			<p>
				<?php
				printf(
					/* translators: 1: List View in the Media Library 2: the WP-CLI command */
					esc_html__( 'You may also use %1$s to selectively optimize images or WP-CLI to optimize your images in bulk: %2$s', 'ewww-image-optimizer' ),
					'<a href="' . esc_url( admin_url( 'upload.php?mode=list' ) ) . '">' . esc_html__( 'List View in the Media Library', 'ewww-image-optimizer' ) . '</a>',
					'<br><code>wp help ewwwio optimize</code>'
				);
				ewwwio_help_link( 'https://docs.ewww.io/article/25-optimizing-with-wp-cli', '592da1482c7d3a074e8aeb6b' );
				?>
			</p>
			<p>
				<?php
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_allow_tracking' ) ) {
					printf(
						/* translators: 1: link to https://ewww.io/plans/ 2: discount code (yes, you may use it) */
						esc_html__( 'Use this code at %1$s: %2$s', 'ewww-image-optimizer' ),
						'<a href="https://ewww.io/plans/" target="_blank">https://ewww.io/</a>',
						'<code>SPEEDER1012</code>'
					);
				}
				?>
			</p>
			<p><a type='submit' class='button-primary' href='<?php echo esc_url( $settings_page_url ); ?>'><?php esc_attr_e( 'Done', 'ewww-image-optimizer' ); ?></a></p>
			<?php
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_help' ) ) {
				$current_user = wp_get_current_user();
				$help_email   = $current_user->user_email;
				$hs_debug     = '';
				if ( ! empty( $debug_info ) ) {
					$hs_debug = str_replace( array( "'", '<br>', '<b>', '</b>', '=>' ), array( "\'", '\n', '**', '**', '=' ), $debug_info );
				}
				?>
<script type="text/javascript">!function(e,t,n){function a(){var e=t.getElementsByTagName("script")[0],n=t.createElement("script");n.type="text/javascript",n.async=!0,n.src="https://beacon-v2.helpscout.net",e.parentNode.insertBefore(n,e)}if(e.Beacon=n=function(t,n,a){e.Beacon.readyQueue.push({method:t,options:n,data:a})},n.readyQueue=[],"complete"===t.readyState)return a();e.attachEvent?e.attachEvent("onload",a):e.addEventListener("load",a,!1)}(window,document,window.Beacon||function(){});</script>
<script type="text/javascript">
	window.Beacon('init', 'aa9c3d3b-d4bc-4e9b-b6cb-f11c9f69da87');
	Beacon( 'prefill', {
		email: '<?php echo esc_js( utf8_encode( $help_email ) ); ?>',
		text: '\n\n----------------------------------------\n<?php echo wp_kses_post( $hs_debug ); ?>',
	});
</script>
				<?php
			}
			?>
	<?php endif; ?>
		</div>
	</div>
</div>
	<?php
}

/**
 * De-activates front-end parsing functions and displays troubleshooting instructions.
 */
function ewww_image_optimizer_rescue_mode() {
	$settings_page_url  = ewww_image_optimizer_get_settings_link();
	$frontend_functions = array();
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_exactdn', '' );
		$frontend_functions[] = 'easyio';
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_lazy_load', false );
		$frontend_functions[] = 'lazyload';
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_webp_for_cdn', false );
		$frontend_functions[] = 'jswebp';
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_picture_webp' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_picture_webp', false );
		$frontend_functions[] = 'picturewebp';
	}
	global $eio_debug;
	$debug_info = '';
	if ( ! empty( $eio_debug ) ) {
		$debug_info = $eio_debug;
	}
	?>
<div id='ewww-settings-wrap' class='wrap'>
	<div id='ewwwio-rescue'>
		<div id="ewwwio-rescue-header">
			<img height="95" width="167" src="<?php echo esc_url( plugins_url( '/images/ewwwio-logo.png', __FILE__ ) ); ?>">
		</div>
		<div id='ewwwio-rescue-body'>
			<div id='ewww-image-optimizer-warning-exec' class='ewwwio-notice notice-warning'>
				<?php esc_html_e( 'All front-end functions have been disabled. Please clear all caches, and check your site to ensure it is functioning normally.', 'ewww-image-optimizer' ); ?>
				<br>
				<a class='ewww-contact-root' href='https://ewww.io/contact-us/'>
					<?php esc_html_e( 'If you continue to have problems, let us know right away!', 'ewww-image-optimizer' ); ?>
				</a>
			</div>
			<div class='ewwwio-intro-text'>
				<?php esc_html_e( 'We don\'t want you to settle for reduced functionality, so here are some troubleshooting tips:', 'ewww-image-optimizer' ); ?>
			</div>
			<ul>
	<?php if ( in_array( 'easyio', $frontend_functions, true ) ) : ?>
				<li>
					<?php esc_html_e( 'Without Easy IO, several key optimizations are no longer working. First, re-enable Easy IO, and if your site is encounters problems again, try disabling the option to Include All Resources.', 'ewww-image-optimizer' ); ?>
				</li>
	<?php endif; ?>
	<?php if ( in_array( 'lazyload', $frontend_functions, true ) || in_array( 'easyio', $frontend_functions, true ) ) : ?>
				<li>
					<?php esc_html_e( 'Third-party lazy loaders prevent Easy IO from auto-scaling some images, and may cause conflicts. We recommend disabling them and using the EWWW IO lazy loader.', 'ewww-image-optimizer' ); ?>
				</li>
	<?php endif; ?>
	<?php if ( in_array( 'lazyload', $frontend_functions, true ) ) : ?>
				<li>
					<?php /* translators: %s: Documentation (link) */ ?>
					<?php printf( esc_html__( 'The lazy loader has browser-native and auto-scaling components that may not be compatible with some themes/plugins. Instructions for disabling these can be found in the %s.', 'ewww-image-optimizer' ), "<a class='ewww-help-beacon-single' href='https://docs.ewww.io/article/74-lazy-load' data-beacon-article='5c6c36ed042863543ccd2d9b'>" . esc_html__( 'Documentation', 'ewww-image-optimizer' ) . '</a>' ); ?>
				</li>
	<?php endif; ?>
	<?php if ( in_array( 'jswebp', $frontend_functions, true ) ) : ?>
				<li>
					<?php esc_html_e( 'Enabling Lazy Load alongside JS WebP enables better compatibility with some themes/plugins. Alternatively, you may try <picture> WebP Rewriting for a JavaScript-free delivery method.', 'ewww-image-optimizer' ); ?>
				</li>
	<?php endif; ?>
	<?php if ( in_array( 'picturewebp', $frontend_functions, true ) ) : ?>
				<li>
					<?php esc_html_e( 'Some themes may not display <picture> elements properly, so try JS WebP Rewriting for WebP delivery.', 'ewww-image-optimizer' ); ?>
				</li>
	<?php endif; ?>
			</ul>
			<p>
				<a class='ewww-contact-root' href='https://ewww.io/contact-us/'>
					<?php esc_html_e( 'If you have not found a solution that works for your site, let us know! We would love to help you find a solution.', 'ewww-image-optimizer' ); ?>
				</a>
			</p>
			<p><a href='<?php echo esc_url( $settings_page_url ); ?>' class='button-secondary'><?php esc_html_e( 'Return to Settings', 'ewww-image-optimizer' ); ?></a></p>
		</div>
	</div>
</div>
	<?php
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_help' ) ) {
		$current_user = wp_get_current_user();
		$help_email   = $current_user->user_email;
		$hs_debug     = '';
		if ( ! empty( $debug_info ) ) {
			$hs_debug = str_replace( array( "'", '<br>', '<b>', '</b>', '=>' ), array( "\'", '\n', '**', '**', '=' ), $debug_info );
		}
		?>
<script type="text/javascript">!function(e,t,n){function a(){var e=t.getElementsByTagName("script")[0],n=t.createElement("script");n.type="text/javascript",n.async=!0,n.src="https://beacon-v2.helpscout.net",e.parentNode.insertBefore(n,e)}if(e.Beacon=n=function(t,n,a){e.Beacon.readyQueue.push({method:t,options:n,data:a})},n.readyQueue=[],"complete"===t.readyState)return a();e.attachEvent?e.attachEvent("onload",a):e.addEventListener("load",a,!1)}(window,document,window.Beacon||function(){});</script>
<script type="text/javascript">
	window.Beacon('init', 'aa9c3d3b-d4bc-4e9b-b6cb-f11c9f69da87');
	Beacon( 'prefill', {
		email: '<?php echo esc_js( utf8_encode( $help_email ) ); ?>',
		text: '\n\n----------------------------------------\n<?php echo wp_kses_post( $hs_debug ); ?>',
	});
</script>
		<?php
	}
}

/**
 * Wrapper that displays the EWWW IO options in the multisite network admin.
 */
function ewww_image_optimizer_network_options() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewww_image_optimizer_options( 'network-multisite' );
}

/**
 * Wrapper that displays the EWWW IO options for multisite mode on a single site.
 *
 * By default, the only options displayed are the per-site resizes list, but a network admin can
 * permit site admins to configure their own blog settings.
 */
function ewww_image_optimizer_network_singlesite_options() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewww_image_optimizer_options( 'network-singlesite' );
}

/**
 * Displays the EWWW IO options along with status information, and debugging information.
 *
 * @global string $eio_debug In memory debug log.
 * @global int $wp_version
 *
 * @param string $network Indicates which options should be shown in multisite installations.
 */
function ewww_image_optimizer_options( $network = 'singlesite' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewwwio_temp_debug;
	global $ewwwio_upgrading;
	global $eio_debug;
	global $eio_hs_beacon;
	global $exactdn;
	global $eio_alt_webp;
	global $wpdb;
	$total_savings = 0;
	if ( 'network-multisite' === $network ) {
		$total_sizes   = ewww_image_optimizer_savings();
		$total_savings = $total_sizes[1] - $total_sizes[0];
	} else {
		$total_sizes   = ewww_image_optimizer_savings();
		$total_savings = $total_sizes[1] - $total_sizes[0];
	}

	ewwwio_debug_info();
	$debug_info = $eio_debug;
	ewww_image_optimizer_temp_debug_clear();

	$exactdn_sub_folder = false;
	if ( is_multisite() && is_network_admin() ) {
		if ( defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL ) {
			update_site_option( 'exactdn_sub_folder', false );
		} else {
			$network_site_url = network_site_url();
			$sub_folder       = true;
			ewwwio_debug_message( "network site url: $network_site_url" );
			$blogs = $wpdb->get_results( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs WHERE site_id = %d LIMIT 500", $wpdb->siteid ), ARRAY_A );
			if ( ewww_image_optimizer_iterable( $blogs ) ) {
				$indices = array( 0, 1, 2 );
				if ( function_exists( 'array_key_last' ) ) {
					$indices[] = array_key_last( $blogs );
				} else {
					$indices[] = 3;
				}
				foreach ( $indices as $index ) {
					if ( ! empty( $blogs[ $index ]['blog_id'] ) ) {
						$sample_blog_id  = $blogs[ $index ]['blog_id'];
						$sample_site_url = get_site_url( $sample_blog_id );
						ewwwio_debug_message( "blog $sample_blog_id url: $sample_site_url" );
						$sample_domain = wp_parse_url( $sample_site_url, PHP_URL_HOST );
						$site_domain   = wp_parse_url( $network_site_url, PHP_URL_HOST );
						if ( $sample_domain && $site_domain && $site_domain !== $sample_domain ) {
							$sub_folder = false;
						}
					}
				}
			}
			update_site_option( 'exactdn_sub_folder', $sub_folder );
		}
	}
	$exactdn_sub_folder = (bool) get_site_option( 'exactdn_sub_folder' );
	if ( defined( 'EXACTDN_SUB_FOLDER' ) && EXACTDN_SUB_FOLDER ) {
		$exactdn_sub_folder = true;
	} elseif ( defined( 'EXACTDN_SUB_FOLDER' ) ) {
		$exactdn_sub_folder = false;
	}

	if ( empty( $network ) ) {
		$network = 'singlesite';
	}
	if ( 'network-multisite' === $network && get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) ) {
		$network = 'network-multisite-over';
	} elseif ( 'network-singlesite' === $network && get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) ) {
		$network = 'singlesite';
	}
	if ( 'singlesite' === $network && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_wizard_complete' ) ) {
		ewww_image_optimizer_intro_wizard();
		return;
	}
	if ( ! empty( $_GET['rescue_mode'] ) && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' ) ) {
		ewww_image_optimizer_rescue_mode();
		return;
	}
	$eio_hs_beacon->admin_notice( $network );
	?>

<div id='ewww-settings-wrap' class='wrap'>
	<h1 style="display:none;">EWWW Image Optimizer</h1>
	<?php
	$speed_score = 0;

	$speed_recommendations = array();

	$free_exec = false;
	if (
		defined( 'EWWW_IMAGE_OPTIMIZER_NOEXEC' ) && EWWW_IMAGE_OPTIMIZER_NOEXEC &&
		10 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) &&
		! ewww_image_optimizer_easy_active()
	) {
		$free_exec    = true;
		$speed_score += 5;
	}
	if (
		! $free_exec &&
		defined( 'EWWW_IMAGE_OPTIMIZER_JPEGTRAN' ) && ! EWWW_IMAGE_OPTIMIZER_JPEGTRAN &&
		10 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) &&
		! ewww_image_optimizer_easy_active()
	) {
		$free_exec    = true;
		$speed_score += 5;
	}
	$verify_cloud = '';
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_exceeded', 0 );
		$verify_cloud = ewww_image_optimizer_cloud_verify( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ), false );
		if ( false !== strpos( $verify_cloud, 'great' ) && ! ewww_image_optimizer_easy_active() ) {
			$speed_score += 35;
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 20 ) {
				$speed_score += 15;
			} else {
				$speed_recommendations[] = __( 'Enable premium compression for JPG images.', 'ewww-image-optimizer' );
			}
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) > 20 ) {
				$speed_score += 5;
			} else {
				$speed_recommendations[] = __( 'Enable premium compression for PNG images.', 'ewww-image-optimizer' );
			}
		}
		$disable_level = false;
	} else {
		delete_option( 'ewww_image_optimizer_cloud_key_invalid' );
		if ( ! class_exists( 'ExactDN' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
			$speed_recommendations[] = __( 'Enable premium compression with an API key or Easy IO.', 'ewww-image-optimizer' );
		}
		$disable_level = true;
	}
	$exactdn_enabled = false;
	if ( get_option( 'easyio_exactdn' ) ) {
		ewww_image_optimizer_webp_rewrite_verify();
		update_option( 'ewww_image_optimizer_exactdn', false );
		update_option( 'ewww_image_optimizer_lazy_load', false );
		update_option( 'ewww_image_optimizer_webp_for_cdn', false );
		update_option( 'ewww_image_optimizer_picture_webp', false );
		$speed_score += 55;
		if ( get_option( 'exactdn_lossy' ) ) {
			$speed_score += 20;
		} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) < 30 ) {
			$speed_recommendations[] = __( 'Enable premium compression.', 'ewww-image-optimizer' ) . ewwwio_get_help_link( 'https://docs.ewww.io/article/47-getting-more-from-exactdn', '59de6631042863379ddc953c' );
		} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 20 ) {
				$speed_score += 20;
		}
	} elseif (
		( ! class_exists( 'Jetpack' ) || ! Jetpack::is_module_active( 'photon' ) ) &&
		class_exists( 'ExactDN' ) &&
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' )
	) {
		if ( $exactdn->get_exactdn_domain() && $exactdn->verify_domain( $exactdn->get_exactdn_domain() ) ) {
			$speed_score += 55;
			if ( ewww_image_optimizer_get_option( 'exactdn_lossy' ) ) {
				$speed_score += 20;
			} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) < 30 ) {
				$speed_recommendations[] = __( 'Enable premium compression.', 'ewww-image-optimizer' ) . ewwwio_get_help_link( 'https://docs.ewww.io/article/47-getting-more-from-exactdn', '59de6631042863379ddc953c' );
			} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 20 ) {
				$speed_score += 20;
			}
			$exactdn_enabled = true;
			if ( is_multisite() && is_network_admin() && empty( $exactdn->sub_folder ) ) {
				$exactdn_savings = 0;
			} else {
				$exactdn_savings = $exactdn->savings();
			}
		}
	} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
			$speed_recommendations[] = __( 'Enable Easy IO for automatic resizing.', 'ewww-image-optimizer' ) . ewwwio_get_help_link( 'https://docs.ewww.io/article/44-introduction-to-exactdn', '59bc5ad6042863033a1ce370,5c0042892c7d3a31944e88a4' );
		}
		delete_option( 'ewww_image_optimizer_exactdn_domain' );
		delete_option( 'ewww_image_optimizer_exactdn_plan_id' );
		delete_option( 'ewww_image_optimizer_exactdn_failures' );
		delete_option( 'ewww_image_optimizer_exactdn_checkin' );
		delete_option( 'ewww_image_optimizer_exactdn_verified' );
		delete_option( 'ewww_image_optimizer_exactdn_validation' );
		delete_option( 'ewww_image_optimizer_exactdn_suspended' );
		delete_site_option( 'ewww_image_optimizer_exactdn_domain' );
		delete_site_option( 'ewww_image_optimizer_exactdn_plan_id' );
		delete_site_option( 'ewww_image_optimizer_exactdn_failures' );
		delete_site_option( 'ewww_image_optimizer_exactdn_checkin' );
		delete_site_option( 'ewww_image_optimizer_exactdn_verified' );
		delete_site_option( 'ewww_image_optimizer_exactdn_validation' );
		delete_site_option( 'ewww_image_optimizer_exactdn_suspended' );
	}
	$exactdn_network_enabled = 0;
	if ( $exactdn_enabled && is_multisite() && is_network_admin() && empty( $exactdn_sub_folder ) ) {
		$exactdn_network_enabled = ewww_image_optimizer_easyio_network_activated();
	}
	$easymode = false;
	if (
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_local_mode' )
	) {
		$easymode = true;
	}
	if (
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' ) ||
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' ) ||
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherwidth' ) ||
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherheight' )
	) {
		$speed_score += 5;
	} elseif ( defined( 'IMSANITY_VERSION' ) ) {
		$speed_score += 5;
	} else {
		$speed_recommendations[] = __( 'Configure maximum image dimensions in Resize settings.', 'ewww-image-optimizer' ) . ewwwio_get_help_link( 'https://docs.ewww.io/article/41-resize-settings', '59849911042863033a1ba5f9' );
	}
	$jpg_quality = apply_filters( 'jpeg_quality', 82, 'image_resize' );
	if ( $jpg_quality < 91 && $jpg_quality > 49 ) {
		$speed_score += 5;
	} else {
		$speed_recommendations[] = __( 'JPG quality level should be between 50 and 90 for optimal resizing.', 'ewww-image-optimizer' ) . ewwwio_get_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,58543c69c697912ffd6c19a7' );
	}
	$skip = ewww_image_optimizer_skip_tools();
	if ( ewww_image_optimizer_easy_active() ) {
		$skip['jpegtran']   = true;
		$skip['optipng']    = true;
		$skip['gifsicle']   = true;
		$skip['pngout']     = true;
		$skip['pngquant']   = true;
		$skip['webp']       = true;
		$skip['svgcleaner'] = true;
	}
	if ( ! $skip['jpegtran'] && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
		if ( EWWW_IMAGE_OPTIMIZER_JPEGTRAN ) {
			$jpegtran_installed = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_JPEGTRAN, 'j' );
			if ( ! $jpegtran_installed ) {
				$jpegtran_installed = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_JPEGTRAN, 'jb' );
			}
		}
		if ( ! empty( $jpegtran_installed ) ) {
			$speed_score += 5;
		} else {
			$speed_recommendations[] = __( 'Install jpegtran.', 'ewww-image-optimizer' ) . ewwwio_get_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
		}
	}
	if ( ! $skip['optipng'] && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
		if ( EWWW_IMAGE_OPTIMIZER_OPTIPNG ) {
			$optipng_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_OPTIPNG, 'o' );
			if ( ! $optipng_version ) {
				$optipng_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_OPTIPNG, 'ob' );
			}
		}
		if ( ! empty( $optipng_version ) ) {
			$speed_score += 5;
		} else {
			$speed_recommendations[] = __( 'Install optipng.', 'ewww-image-optimizer' ) . ewwwio_get_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
		}
	}
	if ( ! $skip['pngout'] && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
		if ( EWWW_IMAGE_OPTIMIZER_PNGOUT ) {
			$pngout_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_PNGOUT, 'p' );
			if ( ! $pngout_version ) {
				$pngout_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_PNGOUT, 'pb' );
			}
		}
		if ( ! empty( $pngout_version ) ) {
			$speed_score += 1;
		} else {
			$speed_recommendations[] = __( 'Install pngout', 'ewww-image-optimizer' ) . ': <a href="' . admin_url( 'admin.php?action=ewww_image_optimizer_install_pngout' ) . '">' . esc_html__( 'automatically', 'ewww-image-optimizer' ) . '</a> | <a href="https://docs.ewww.io/article/13-installing-pngout" data-beacon-article="5854531bc697912ffd6c1afa">' . esc_html__( 'manually', 'ewww-image-optimizer' ) . '</a>';
		}
	}
	if ( ! $skip['pngquant'] && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
		if ( EWWW_IMAGE_OPTIMIZER_PNGQUANT ) {
			$pngquant_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_PNGQUANT, 'q' );
			if ( ! $pngquant_version ) {
				$pngquant_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_PNGQUANT, 'qb' );
			}
		}
		if ( ! empty( $pngquant_version ) ) {
			$speed_score += 5;
		} else {
			$speed_recommendations[] = __( 'Install pngquant.', 'ewww-image-optimizer' ) . ewwwio_get_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
		}
	}
	if ( ! $skip['gifsicle'] && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
		if ( EWWW_IMAGE_OPTIMIZER_GIFSICLE ) {
			$gifsicle_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_GIFSICLE, 'g' );
			if ( ! $gifsicle_version ) {
				$gifsicle_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_GIFSICLE, 'gb' );
			}
		}
		if ( ! empty( $gifsicle_version ) ) {
			$speed_score += 5;
		} else {
			$speed_recommendations[] = __( 'Install gifsicle.', 'ewww-image-optimizer' ) . ewwwio_get_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
		}
	}
	if ( EWWW_IMAGE_OPTIMIZER_CWEBP && ! $skip['webp'] && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
		if ( EWWW_IMAGE_OPTIMIZER_CWEBP ) {
			$webp_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_CWEBP, 'w' );
			if ( ! $webp_version ) {
				$webp_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_CWEBP, 'wb' );
			}
		}
		if ( ! empty( $webp_version ) ) {
			$speed_score += 10;
		} else {
			$speed_recommendations[] = __( 'Install WebP.', 'ewww-image-optimizer' ) . ewwwio_get_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
		}
	}
	if ( ! $skip['svgcleaner'] && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {

		if ( EWWW_IMAGE_OPTIMIZER_SVGCLEANER ) {
			$svgcleaner_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_SVGCLEANER, 's' );
		}
		if ( empty( $svgcleaner_version ) ) {
			$speed_recommendations[] = '<a href="' . admin_url( 'admin.php?action=ewww_image_optimizer_install_svgcleaner' ) . '">' . __( 'Install svgcleaner', 'ewww-image-optimizer' ) . '</a>';
		}
	}
	if ( get_option( 'easyio_lazy_load' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ) {
		$speed_score += 10;
	} else {
		$speed_recommendations[] = __( 'Enable Lazy Loading.', 'ewww-image-optimizer' );
	}
	if ( ! ewww_image_optimizer_easy_active() && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) {
		$speed_recommendations[] = __( 'Enable WebP conversion.', 'ewww-image-optimizer' ) . ewwwio_get_help_link( 'https://docs.ewww.io/article/16-ewww-io-and-webp-images', '5854745ac697912ffd6c1c89' );
	} elseif ( ! ewww_image_optimizer_easy_active() && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) {
		$speed_score += 10;
	}
	if (
		( $free_exec || ! empty( $jpegtran_installed ) || ewww_image_optimizer_easy_active() || ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) &&
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' )
	) {
		$speed_score += 5;
	} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ) {
		$speed_recommendations[] = __( 'Remove metadata from JPG images.', 'ewww-image-optimizer' ) . ewwwio_get_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2' );
	}
	if ( ! ewww_image_optimizer_easy_active() && ! $free_exec ) {
		if ( 0 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
			$speed_recommendations[] = __( 'Enable JPG compression.', 'ewww-image-optimizer' );
		}
		if ( 0 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
			$speed_recommendations[] = __( 'Enable PNG compression.', 'ewww-image-optimizer' );
		}
		if ( 0 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) ) {
			$speed_recommendations[] = __( 'Enable GIF compression.', 'ewww-image-optimizer' );
		}
	}
	if ( defined( 'PHP_VERSION_ID' ) && PHP_VERSION_ID < 70200 ) {
		if ( defined( 'PHP_VERSION_ID' ) && PHP_VERSION_ID < 70000 && $speed_score > 20 ) {
			$speed_score -= 20;
		} elseif ( $speed_score > 10 ) {
			$speed_score -= 10;
		}
		/* translators: %s: The server PHP version. */
		$speed_recommendations[] = sprintf( __( 'Your site is running an older version of PHP (%s), which should be updated.', 'ewww-image-optimizer' ), PHP_VERSION ) . ewwwio_get_help_link( 'https://wordpress.org/support/update-php/', '' );
	}

	// Check that an image library exists for converting resizes. Originals can be done via the API, but resizes are done locally for speed.
	$toolkit_found = false;
	if ( ewww_image_optimizer_gd_support() ) {
		$toolkit_found = true;
	}
	if ( ewww_image_optimizer_gmagick_support() ) {
		$toolkit_found = true;
	}
	if ( ewww_image_optimizer_imagick_support() ) {
		$toolkit_found = true;
	}
	if ( PHP_OS !== 'WINNT' && ! ewww_image_optimizer_full_cloud() && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
		ewww_image_optimizer_find_nix_binary( 'nice', 'n' );
	}

	$allow_help_html = array(
		'a'   => array(
			'class'                => array(),
			'href'                 => array(),
			'target'               => array(),
			'data-beacon-article'  => array(),
			'data-beacon-articles' => array(),
		),
		'img' => array(
			'title' => array(),
			'src'   => array(),
		),
	);

	$allow_settings_html          = wp_kses_allowed_html( 'post' );
	$allow_settings_html['input'] = array(
		'type'     => true,
		'id'       => true,
		'name'     => true,
		'value'    => true,
		'readonly' => true,
	);

	if ( 'network-multisite-over' === $network ) {
		ob_start();
	}
	// Begin building of status inside section.
	$speed_score  = min( $speed_score, 100 );
	$stroke_class = 'ewww-green';
	if ( $speed_score < 50 ) {
		$stroke_class = 'ewww-red';
	} elseif ( $speed_score < 90 ) {
		$stroke_class = 'ewww-orange';
	}
	?>
	<div id='ewww-widgets' class='metabox-holder'>
		<div class='meta-box-sortables'>
			<div id='ewww-status' class='postbox'>
				<!--<h2 class='ewww-hndle'><?php esc_html_e( 'Optimization Status', 'ewww-image-optimizer' ); ?></h2>-->
				<div class='ewww-hndle' id="ewwwio-banner">
					<img height="95" width="167" src="<?php echo esc_url( plugins_url( '/images/ewwwio-logo.png', __FILE__ ) ); ?>">
					<div>
						<div class='ewwwio-flex-space-between'>
							<p>
								<?php esc_html_e( 'Get performance tips, exclusive discounts, and the latest news when you signup for our newsletter!', 'ewww-image-optimizer' ); ?>
							</p>
							<p id='ewww-review'>
								<a target="_blank" href="https://wordpress.org/support/plugin/ewww-image-optimizer/reviews/#new-post"><?php esc_html_e( 'Write a Review', 'ewww-image-optimizer' ); ?></a>
								<a target="_blank" href="https://wordpress.org/support/plugin/ewww-image-optimizer/reviews/#new-post">
									<span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span>
								</a>
							</p>
						</div>
						<p id='ewww-news-button'>
							<a href="https://eepurl.com/gKyU6L?TB_iframe=true&width=600&height=610" class="thickbox button-secondary"><?php esc_html_e( 'Subscribe now!', 'ewww-image-optimizer' ); ?></a>
						</p>
					</div>
				</div>
				<div class='inside'>
					<div class='ewww-row ewww-blocks'>
						<div id='ewww-score-bars' class='ewww-status-detail'>
							<div id='ewww-speed-container' class='ewww-bar-container'>
								<div id='ewww-speed-fill' data-score='<?php echo (int) $speed_score; ?>' class='<?php echo esc_attr( $stroke_class ); ?> ewww-bar-fill'></div>
							</div>
							<div id='ewww-speed-flex' class='ewww-bar-caption'>
								<p><strong><?php esc_html_e( 'Optimization Score', 'ewww-image-optimizer' ); ?></strong></p>
								<p class='ewww-bar-score'><?php echo (int) $speed_score; ?>%</p>
								<p id='ewww-show-recommendations'>
	<?php if ( $speed_score < 100 ) : ?>
									<a href='#'><?php esc_html_e( 'View recommendations', 'ewww-image-optimizer' ); ?></a>
									<a style='display:none' href='#'><?php esc_html_e( 'Hide recommendations', 'ewww-image-optimizer' ); ?></a>
	<?php endif; ?>
								</p>
							</div>
							<div class="ewww-recommend">
								<p><strong><?php echo ( (int) $speed_score < 100 ? esc_html__( 'How do I get to 100%?', 'ewww-image-optimizer' ) : esc_html__( 'You got the perfect score!', 'ewww-image-optimizer' ) ); ?></strong></p>
	<?php if ( $speed_score < 100 ) : ?>
								<ul class="ewww-tooltip">
		<?php foreach ( $speed_recommendations as $recommendation ) : ?>
									<li><?php echo wp_kses( $recommendation, $allow_help_html ); ?></li>
		<?php endforeach; ?>
								</ul>
	<?php endif; ?>
							</div><!-- end .ewww-recommend -->
	<?php if ( $total_savings > 0 ) : ?>
							<div id='ewww-savings-container' class='ewww-bar-container'>
								<div id='ewww-savings-fill' data-score='<?php echo intval( $total_savings / $total_sizes[1] * 100 ); ?>' class='ewww-bar-fill'></div>
							</div>
							<div id='ewww-savings-flex' class='ewww-bar-caption'>
								<p><strong><?php esc_html_e( 'Local Compression Savings', 'ewww-image-optimizer' ); ?></strong></p>
								<p class='ewww-bar-score'><?php echo esc_html( ewww_image_optimizer_size_format( $total_savings, 2 ) ); ?></p>
								<p>
									<a href="<?php echo esc_url( admin_url( 'tools.php?page=ewww-image-optimizer-tools' ) ); ?>"><?php esc_html_e( 'View optimized images', 'ewww-image-optimizer' ); ?></a>
								</p>
							</div>
	<?php endif; ?>
	<?php if ( $exactdn_enabled && ! empty( $exactdn_savings ) && ! empty( $exactdn_savings['original'] ) && ! empty( $exactdn_savings['savings'] ) ) : ?>
							<div id='easyio-savings-container' class='ewww-bar-container'>
								<div id='easyio-savings-fill' data-score='<?php echo intval( $exactdn_savings['savings'] / $exactdn_savings['original'] * 100 ); ?>' class='ewww-bar-fill'></div>
							</div>
							<div id='easyio-savings-flex' class='ewww-bar-caption'>
								<p>
									<strong><?php esc_html_e( 'Easy IO Savings', 'ewww-image-optimizer' ); ?></strong>
									<?php ewwwio_help_link( 'https://docs.ewww.io/article/96-easy-io-is-it-working', '5f871dd2c9e77c0016217c4e' ); ?>
								</p>
								<p class='ewww-bar-score'><?php echo esc_html( ewww_image_optimizer_size_format( $exactdn_savings['savings'], 2 ) ); ?></p>
								<p>&nbsp;</p>
							</div>
	<?php endif; ?>
						</div>
						<!-- begin notices section -->
						<div id="ewww-notices" class="ewww-status-detail">
							<p>
								<?php
								printf(
									/* translators: %s: Bulk Optimize (link) */
									esc_html__( 'New uploads will be optimized automatically. Optimize existing images with the %s.', 'ewww-image-optimizer' ),
									( 'network-multisite' === esc_attr( $network ) ?
									esc_html__( 'Media Library', 'ewww-image-optimizer' ) . ' -> ' . esc_html__( 'Bulk Optimizer', 'ewww-image-optimizer' ) :
									'<a href="' . esc_url( admin_url( 'upload.php?page=ewww-image-optimizer-bulk' ) ) . '">' . esc_html__( 'Bulk Optimizer', 'ewww-image-optimizer' ) . '</a>'
									)
								);
								ewwwio_help_link( 'https://docs.ewww.io/article/4-getting-started', '5853713bc697912ffd6c0b98' );
								echo ' ' . ( ! class_exists( 'Amazon_S3_And_CloudFront' ) ?
									'<br>' .
									sprintf(
										/* translators: %s: S3 Image Optimizer (link) */
										esc_html__( 'Optimize unlimited Amazon S3 buckets with our %s.' ),
										'<a href="https://wordpress.org/plugins/s3-image-optimizer/">' . esc_html__( 'S3 Image Optimizer', 'ewww-image-optimizer' ) . '</a>'
									) : '' );
								?>
							</p>
							<p>
								<strong><?php esc_html_e( 'Background optimization (faster uploads):', 'ewww-image-optimizer' ); ?></strong><br>
	<?php if ( defined( 'EWWW_DISABLE_ASYNC' ) && EWWW_DISABLE_ASYNC ) : ?>
								<span><?php esc_html_e( 'Disabled by administrator', 'ewww-image-optimizer' ); ?></span>
	<?php elseif ( ! ewww_image_optimizer_function_exists( 'sleep' ) ) : ?>
								<span style="color: orange; font-weight: bolder"><?php esc_html_e( 'Disabled, sleep function missing', 'ewww-image-optimizer' ); ?></span>
	<?php elseif ( $ewwwio_upgrading ) : ?>
								<span><?php esc_html_e( 'Update detected, re-testing', 'ewww-image-optimizer' ); ?></span>
	<?php elseif ( ewww_image_optimizer_detect_wpsf_location_lock() ) : ?>
								<span style="color: orange; font-weight: bolder"><?php esc_html_e( "Disabled by Shield's Lock to Location feature", 'ewww-image-optimizer' ); ?></span>
	<?php elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_background_optimization' ) ) : ?>
								<span style="color: orange; font-weight: bolder">
									<?php esc_html_e( 'Disabled automatically, async requests blocked', 'ewww-image-optimizer' ); ?>
									- <a href="<?php echo esc_url( admin_url( 'admin.php?action=ewww_image_optimizer_retest_background_optimization' ) ); ?>">
										<?php esc_html_e( 'Re-test', 'ewww-image-optimizer' ); ?>
									</a>
								</span>
								<?php ewwwio_help_link( 'https://docs.ewww.io/article/42-background-and-parallel-optimization-disabled', '598cb8be2c7d3a73488be237' ); ?>
	<?php else : ?>
								<span><?php esc_html_e( 'Enabled', 'ewww-image-optimizer' ); ?>
									- <a href="<?php echo esc_url( admin_url( 'admin.php?action=ewww_image_optimizer_retest_background_optimization' ) ); ?>">
										<?php esc_html_e( 'Re-test', 'ewww-image-optimizer' ); ?>
									</a>
								</span>
	<?php endif; ?>
							</p>
						</div><!-- end .ewww-status-detail -->
					</div><!-- end .ewww-blocks --><!-- end .ewww-row -->
				</div><!-- end .inside -->
			</div>
		</div>
	</div>

	<?php
	if ( 'network-multisite-over' === $network ) {
		ob_end_clean();
	}
	?>

	<?php if ( $exactdn_enabled && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) : ?>
	<script type='text/javascript'>
		var exactdn_enabled = true;
	</script>
	<?php else : ?>
	<script type='text/javascript'>
		var exactdn_enabled = false;
	</script>
	<?php endif; ?>
	<?php
	$frontend_functions = array();
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ) {
		$frontend_functions[] = __( 'Lazy Load', 'ewww-image-optimizer' );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) ) {
		$frontend_functions[] = __( 'JS WebP Rewriting', 'ewww-image-optimizer' );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_picture_webp' ) ) {
		$frontend_functions[] = __( '<picture> WebP Rewriting', 'ewww-image-optimizer' );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		$frontend_functions[] = __( 'Easy IO', 'ewww-image-optimizer' );
	}
	$loading_image_url    = plugins_url( '/images/spinner.gif', __FILE__ );
	$eio_base             = new EIO_Base();
	$easyio_site_url      = $eio_base->content_url();
	$exactdn_los_che      = ewww_image_optimizer_get_option( 'exactdn_lossy' ) || ( is_object( $exactdn ) && 1 === $exactdn->get_plan_id() );
	$exactdn_los_id       = ( ! $exactdn_enabled || 1 === $exactdn->get_plan_id() ? 'exactdn_lossy_disabled' : 'exactdn_lossy' );
	$exactdn_los_dis      = ! $exactdn_enabled || 1 === $exactdn->get_plan_id();
	$eio_exclude_paths    = ewww_image_optimizer_get_option( 'exactdn_exclude' ) ? implode( "\n", ewww_image_optimizer_get_option( 'exactdn_exclude' ) ) : '';
	$lqip_che             = is_object( $exactdn ) && 1 < $exactdn->get_plan_id() && ewww_image_optimizer_get_option( 'ewww_image_optimizer_use_lqip' );
	$lqip_id              = ( ! $exactdn_enabled || 1 === $exactdn->get_plan_id() ? 'ewww_image_optimizer_use_lqip_disabled' : 'ewww_image_optimizer_use_lqip' );
	$lqip_dis             = ! $exactdn_enabled || 1 === $exactdn->get_plan_id();
	$ll_exclude_paths     = ewww_image_optimizer_get_option( 'ewww_image_optimizer_ll_exclude' ) ? implode( "\n", ewww_image_optimizer_get_option( 'ewww_image_optimizer_ll_exclude' ) ) : '';
	$current_jpeg_quality = apply_filters( 'jpeg_quality', 82, 'image_resize' );
	$webp_php_rewriting   = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_picture_webp' );
	$webp_exclude_paths   = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_rewrite_exclude' ) ? implode( "\n", ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_rewrite_exclude' ) ) : '';
	$webp_paths           = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' ) ? implode( "\n", ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' ) ) : '';
	$webp_url_example     = sprintf(
		/* translators: 1: An image URL on a CDN 2: An image URL 3: An example folder URL */
		esc_html__( 'For example, with a CDN URL of %1$s and a local URL of %2$s you would enter %3$s.', 'ewww-image-optimizer' ),
		'https://cdn.example.com/<strong>files/</strong>2038/01/image.jpg',
		'https://example.com/<strong>wp-content/uploads/</strong>2038/01/image.jpg',
		'https://cdn.example.com/<strong>files/</strong>'
	);
	// Setup URLs for Ludicrous/Easy Mode.
	$enable_local_url = wp_nonce_url(
		add_query_arg(
			array(
				'page'         => 'ewww-image-optimizer-options',
				'enable-local' => 1,
			),
			null
		),
		'ewww_image_optimizer_options-options'
	);
	$enable_easy_url  = wp_nonce_url(
		add_query_arg(
			array(
				'page'         => 'ewww-image-optimizer-options',
				'enable-local' => 0,
			),
			null
		),
		'ewww_image_optimizer_options-options'
	);
	$rescue_mode_url  = wp_nonce_url(
		add_query_arg(
			array(
				'page'        => 'ewww-image-optimizer-options',
				'rescue_mode' => 1,
			),
			null
		),
		'ewww_image_optimizer_options-options'
	);

	$cloudways_host = false;
	if ( isset( $_SERVER['cw_allowed_ip'] ) ) {
		$cloudways_host = true;
	}
	// Make sure .htaccess rules are terminated when ExactDN is enabled or if Cloudflare is detected.
	$cf_host = ewwwio_is_cf_host();
	if ( ewww_image_optimizer_easy_active() || $cf_host || $cloudways_host ) {
		ewww_image_optimizer_webp_rewrite_verify();
	}
	$webp_available  = ewww_image_optimizer_webp_available();
	$test_webp_image = plugins_url( '/images/test.png.webp', __FILE__ );
	$test_png_image  = plugins_url( '/images/test.png', __FILE__ );
	?>


	<!-- 'network-multisite-over' and 'network-singlesite' get simpler settings, 'network-singlesite-over' masquerades as 'singlesite' -->
	<?php if ( ! $easymode && ( 'singlesite' === $network || 'network-multisite' === $network ) ) : ?>
	<ul class='ewww-tab-nav'>
		<li class='ewww-tab ewww-general-nav'><span><?php esc_html_e( 'Basic', 'ewww-image-optimizer' ); ?></span></li>
		<li class='ewww-tab ewww-local-nav'><span><?php esc_html_e( 'Local', 'ewww-image-optimizer' ); ?></span></li>
		<li class='ewww-tab ewww-advanced-nav'><span><?php esc_html_e( 'Advanced', 'ewww-image-optimizer' ); ?></span></li>
		<li class='ewww-tab ewww-resize-nav'><span><?php esc_html_e( 'Resize', 'ewww-image-optimizer' ); ?></span></li>
		<li class='ewww-tab ewww-conversion-nav'><span><?php esc_html_e( 'Convert', 'ewww-image-optimizer' ); ?></span></li>
		<li class='ewww-tab ewww-overrides-nav'><span><a href='https://docs.ewww.io/article/40-override-options' target='_blank'><span class='ewww-tab-hidden'><?php esc_html_e( 'Overrides', 'ewww-image-optimizer' ); ?></a></span></li>
		<li class='ewww-tab ewww-support-nav'><span><?php esc_html_e( 'Support', 'ewww-image-optimizer' ); ?></span></li>
		<li class='ewww-tab ewww-contribute-nav'><span><?php esc_html_e( 'Contribute', 'ewww-image-optimizer' ); ?></span></li>
	</ul>
	<?php elseif ( $easymode && 'network-singlesite' !== $network ) : ?>
	<ul class='ewww-tab-nav'>
		<li class='ewww-tab ewww-general-nav'><span><?php esc_html_e( 'Basic', 'ewww-image-optimizer' ); ?></span></li>
		<li class='ewww-tab ewww-support-nav'><span><?php esc_html_e( 'Support', 'ewww-image-optimizer' ); ?></span></li>
		<li class='ewww-tab ewww-contribute-nav'><span><?php esc_html_e( 'Contribute', 'ewww-image-optimizer' ); ?></span></li>
	</ul>
	<?php endif; ?>
	<?php if ( false !== strpos( $network, 'network-multisite' ) ) : ?>
	<form id='ewww-settings-form' method='post' action=''>
	<?php else : ?>
	<form id='ewww-settings-form' method='post' action='options.php'>
	<?php endif; ?>
		<input type='hidden' name='option_page' value='ewww_image_optimizer_options' />
		<input type='hidden' name='action' value='update' />
		<?php wp_nonce_field( 'ewww_image_optimizer_options-options' ); ?>
	<?php if ( 'network-singlesite' === $network ) : ?>
		<p><i class="network-singlesite"><strong>
			<?php /* translators: %s: Network Admin */ ?>
			<?php printf( esc_html__( 'Configure network-wide settings in the %s.', 'ewww-image-optimizer' ), '<a href="' . esc_url( ewww_image_optimizer_get_settings_link() ) . '">' . esc_html__( 'Network Admin', 'ewww-image-optimizer' ) . '</a>' ); ?>
		</strong></i></p>
		<?php ob_start(); ?>
	<?php endif; ?>
		<div id='ewww-general-settings'>
			<noscript><h2><?php esc_html_e( 'Basic', 'ewww-image-optimizer' ); ?></h2></noscript>
	<?php if ( $easymode ) : ?>
			<p>
				<a href='<?php echo esc_url( $enable_local_url ); ?>'>
					<?php esc_html_e( 'Enable Ludicrous Mode', 'ewww-image-optimizer' ); ?>
				</a>
			</p>
	<?php else : ?>
			<p>
				<?php /* translators: %s: Easy Mode */ ?>
				<?php printf( esc_html__( 'Switch to %s.', 'ewww-image-optimizer' ), '<a href="' . esc_url( $enable_easy_url ) . '">' . esc_html__( 'Easy Mode', 'ewww-image-optimizer' ) . '</a>' ); ?>
			</p>
	<?php endif; ?>
	<?php ob_start(); ?>
			<table class='form-table'>
	<?php if ( 'network-multisite' === $network || 'network-multisite-over' === $network ) : ?>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_allow_multisite_override'><?php esc_html_e( 'Allow Single-site Override', 'ewww-image-optimizer' ); ?></label>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_allow_multisite_override' name='ewww_image_optimizer_allow_multisite_override' value='true' <?php checked( get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) ); ?> /><?php esc_html_e( 'Allow individual sites to configure their own settings and override all network options.', 'ewww-image-optimizer' ); ?>
					</td>
				</tr>
	<?php endif; ?>
	<?php if ( 'network-multisite-over' === $network ) : ?>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_allow_tracking'><?php esc_html_e( 'Allow Usage Tracking?', 'ewww-image-optimizer' ); ?></label>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_allow_tracking' name='ewww_image_optimizer_allow_tracking' value='true' <?php checked( get_site_option( 'ewww_image_optimizer_allow_tracking' ) ); ?> />
						<?php esc_html_e( 'Allow EWWW Image Optimizer to anonymously track how this plugin is used and help us make the plugin better. Opt-in to tracking and receive a 10% discount on premium compression. No sensitive data is tracked.', 'ewww-image-optimizer' ); ?>
						<p>
							<?php
							if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_allow_tracking' ) ) {
								printf(
									/* translators: 1: link to https://ewww.io/plans/ 2: discount code (yes, you may use it) */
									esc_html__( 'Use this code at %1$s: %2$s', 'ewww-image-optimizer' ),
									'<a href="https://ewww.io/plans/" target="_blank">https://ewww.io/</a>',
									'<code>SPEEDER1012</code>'
								);
							}
							?>
						</p>
					</td>
				</tr>
			</table>
			<input type='hidden' id='ewww_image_optimizer_allow_multisite_override_active' name='ewww_image_optimizer_allow_multisite_override_active' value='0'>
		</div><!-- end container general settings -->
		<p class='submit'><input type='submit' class='button-primary' value='<?php esc_attr_e( 'Save Changes', 'ewww-image-optimizer' ); ?>' /></p>
	</form>
</div><!-- end container .wrap -->
		<?php
		return;
	endif;
	$premium_hide = '';
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		$premium_hide = ' style="display:none"';
	}
	?>
	<?php if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) : ?>
				<tr id='ewww_image_optimizer_cloud_key_container'>
					<th scope='row'>
						<label for='ewww_image_optimizer_cloud_notkey'><?php esc_html_e( 'Compress API Key', 'ewww-image-optimizer' ); ?></label>
					</th>
					<td>
						<input type='text' id='ewww_image_optimizer_cloud_notkey' name='ewww_image_optimizer_cloud_notkey' readonly='readonly' value='****************<?php echo esc_attr( substr( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ), 28 ) ); ?>' size='32' />
						<a class='button-secondary' href='<?php echo esc_url( admin_url( 'admin.php?action=ewww_image_optimizer_remove_cloud_key' ) ); ?>'>
							<?php esc_html_e( 'Remove API key', 'ewww-image-optimizer' ); ?>
						</a>
						<p>
		<?php if ( false !== strpos( $verify_cloud, 'great' ) ) : ?>
							<span style="color: #3eadc9; font-weight: bolder"><?php esc_html_e( 'Verified,', 'ewww-image-optimizer' ); ?> </span><?php echo wp_kses_post( ewww_image_optimizer_cloud_quota() ); ?>
		<?php elseif ( 'exceeded quota' === $verify_cloud ) : ?>
							<span style="color: orange; font-weight: bolder"><a href="https://docs.ewww.io/article/101-soft-quotas-on-unlimited-plans" data-beacon-article="608ddf128996210f18bd95d3" target="_blank"><?php esc_html_e( 'Soft quota reached, contact us for more', 'ewww-image-optimizer' ); ?></a></span>
		<?php elseif ( 'exceeded' === $verify_cloud ) : ?>
							<span style="color: orange; font-weight: bolder"><?php esc_html_e( 'Out of credits', 'ewww-image-optimizer' ); ?></span> - <a href="https://ewww.io/buy-credits/" target="_blank"><?php esc_html_e( 'Purchase more', 'ewww-image-optimizer' ); ?></a>
		<?php else : ?>
							<span style="color: red; font-weight: bolder"><?php esc_html_e( 'Not Verified', 'ewww-image-optimizer' ); ?></span>
		<?php endif; ?>
		<?php if ( false !== strpos( $verify_cloud, 'great' ) ) : ?>
							<a target="_blank" href="https://history.exactlywww.com/show/?api_key=<?php echo esc_attr( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ); ?>"><?php esc_html_e( 'View Usage', 'ewww-image-optimizer' ); ?></a>
		<?php endif; ?>
						</p>
					</td>
				</tr>
	<?php else : ?>
		<?php if ( ! $exactdn_enabled ) : ?>
				<tr>
					<th scope='row'>&nbsp;</th>
					<td>
						<input type='radio' id='ewww_image_optimizer_budget_pay' name='ewww_image_optimizer_budget' value='pay' required />
						<label for='ewww_image_optimizer_budget_pay'><?php esc_html_e( 'Activate Easy IO and/or the Compress API to get 5x more optimization and priority support', 'ewww-image-optimizer' ); ?></label><br>
						<input type='radio' id='ewww_image_optimizer_budget_free' name='ewww_image_optimizer_budget' value='free' required <?php checked( (bool) $premium_hide ); ?> />
						<label for='ewww_image_optimizer_budget_free'><?php esc_html_e( 'Stick with free mode for now', 'ewww-image-optimizer' ); ?></label>
						<p class="ewwwio-premium-setup-disabled-for-now" <?php echo wp_kses_post( $premium_hide ); ?>><strong><a href='https://ewww.io/plans/' target='_blank'>&gt;&gt;<?php esc_html_e( 'Start your free trial', 'ewww-image-optimizer' ); ?></a></strong></p>
					</td>
				</tr>
		<?php endif; ?>
				<tr id='ewww_image_optimizer_cloud_key_container' class='ewwwio-premium-setup' <?php echo wp_kses_post( $premium_hide ); ?>>
					<th scope='row'>
						<label for='ewww_image_optimizer_cloud_key'><?php esc_html_e( 'Compress API Key', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2,5ad0c8e7042863075092650b,5a9efec62c7d3a7549516550' ); ?>
					</th>
					<td>
						<div id='ewwwio-api-activation-result'></div>
						<input type='text' id='ewww_image_optimizer_cloud_key' name='ewww_image_optimizer_cloud_key' value='' size='32' />
						<span id='ewwwio-api-activate'><a href='#' class='button-secondary'><?php esc_html_e( 'Activate', 'ewww-image-optimizer' ); ?></a></span>
						<span id='ewwwio-api-activation-processing'><img src='<?php echo esc_url( $loading_image_url ); ?>' alt='loading'/></span>
						<p class='description'>
							<?php esc_html_e( 'Premium compression for your local images.', 'ewww-image-optimizer' ); ?>
							<?php
							printf(
								/* translators: 1: the string 'Start your free trial' with a link to the signup page 2: 'enter an existing key' linked to the account/key page */
								esc_html__( '%1$s or %2$s.', 'ewww-image-optimizer' ),
								"<a href='https://ewww.io/plans/' target='_blank'>" . esc_html__( 'Start your free trial', 'ewww-image-optimizer' ) . '</a>',
								"<a href='https://ewww.io/manage-keys/' target='_blank'>" . esc_html__( 'enter an existing key', 'ewww-image-optimizer' ) . '</a>'
							);
							?>
						</p>
					</td>
				</tr>
	<?php endif; ?>
	<?php if ( ! get_option( 'easyio_exactdn' ) ) : ?>
		<?php ob_start(); ?>
				<tr id="ewww_image_optimizer_exactdn_container" class="ewwwio-premium-setup" <?php echo wp_kses_post( $premium_hide ); ?>>
					<th scope='row'>
						<span id='ewwwio-exactdn-anchor'></span>
						<label for='ewww_image_optimizer_exactdn'><?php esc_html_e( 'Easy IO', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/44-introduction-to-exactdn', '59bc5ad6042863033a1ce370,5c0042892c7d3a31944e88a4' ); ?>
					</th>
					<td>
						<div id='ewwwio-easy-activation-result'></div>
						<p class='ewwwio-easy-description'>
							<?php
							printf(
								/* translators: %s: the string 'and more' with a link to the docs */
								esc_html__( 'An image-optimizing CDN with automatic compression, scaling, WebP conversion %s.', 'ewww-image-optimizer' ),
								'<a href="https://docs.ewww.io/article/44-introduction-to-exactdn" target="_blank" data-beacon-article="59bc5ad6042863033a1ce370">' . esc_html__( 'and more', 'ewww-image-optimizer' ) . '</a>'
							);
							?>
						</p>
		<?php if ( class_exists( 'Jetpack' ) && Jetpack::is_module_active( 'photon' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) : ?>
						<p style='color: red'><?php esc_html_e( 'Inactive, please disable the Site Accelerator option in the Jetpack settings.', 'ewww-image-optimizer' ); ?></p>
		<?php elseif ( false !== strpos( $easyio_site_url, 'localhost' ) ) : ?>
						<p class="description" style="font-weight: bolder"><?php esc_html_e( 'Easy IO cannot be activated on localhost installs.', 'ewww-image-optimizer' ); ?></p>
		<?php elseif ( 'network-multisite' === $network && empty( $exactdn_sub_folder ) ) : ?>
			<?php if ( 1 > $exactdn_network_enabled ) : ?>
						<p class="ewwwio-easy-setup-instructions">
				<?php if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) : ?>
							<?php esc_html_e( 'Enter your API key above to enable automatic Easy IO site registration.', 'ewww-image-optimizer' ); ?><br>
				<?php endif; ?>
				<?php if ( -1 === $exactdn_network_enabled ) : ?>
							<span style="color: orange; font-weight: bolder"><?php esc_html_e( 'Partially Active', 'ewww-image-optimizer' ); ?></span> - <a href="https://ewww.io/manage-sites/"><?php esc_html_e( 'Manage Sites', 'ewww-image-optimizer' ); ?></a><br>
							<span><?php esc_html_e( 'Easy IO is not active on some sites. You may activate individual sites via the plugin settings in each site dashboard, or activate all remaining sites below.', 'ewww-image-optimizer' ); ?></span><br>
				<?php else : ?>
							<strong><a href="https://ewww.io/plans/" target="_blank">
								<?php esc_html_e( 'Purchase a subscription for your sites', 'ewww-image-optimizer' ); ?>
							</a></strong><br>
							<a href="https://ewww.io/manage-sites/" target="_blank"><?php esc_html_e( 'Then, add your Site URLs to your account', 'easy-image-optimizer' ); ?></a>
				<?php endif; ?>
						</p>
						<p>
				<?php if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) : ?>
							<a id='ewwwio-easy-register-network' href='#' class='button-secondary'><?php esc_html_e( 'Register All Sites', 'ewww-image-optimizer' ); ?></a>
				<?php endif; ?>
							<a id='ewwwio-easy-activate-network' href='#' class='button-secondary'><?php esc_html_e( 'Activate All Sites', 'ewww-image-optimizer' ); ?></a>
						</p>
						<span id='ewwwio-easy-activation-processing'><img src='<?php echo esc_url( $loading_image_url ); ?>' alt='loading'/></span>
						<div id='ewwwio-easy-activation-progressbar' style='display:none;'></div>
						<a id='ewwwio-easy-cancel-network-operation' style='display:none;' href='#' class='button-secondary'><?php esc_html_e( 'Cancel', 'ewww-image-optimizer' ); ?></a>
						<div id='ewwwio-easy-activation-errors' style='display:none;'>
							<p>
								<?php
								printf(
									/* translators: %s: link to docs */
									esc_html__( 'The following errors were encountered during the bulk operation. Please see %s for troubleshooting steps.', 'ewww-image-optimizer' ),
									'<a href="https://docs.ewww.io/article/66-exactdn-not-verified" data-beacon-article="5beee9932c7d3a31944e0d33" target="_blank">https://docs.ewww.io/article/66-exactdn-not-verified</a>'
								);
								?>
							</p>
						</div>
			<?php endif; ?>
			<?php if ( 1 === $exactdn_network_enabled ) : ?>
						<span style="color: #3eadc9; font-weight: bolder"><?php esc_html_e( 'Verified', 'ewww-image-optimizer' ); ?></span> - <a href="https://ewww.io/manage-sites/"><?php esc_html_e( 'Manage Sites', 'ewww-image-optimizer' ); ?></a><br>
			<?php endif; ?>
			<?php if ( 0 !== $exactdn_network_enabled ) : ?>
						<a id='ewwwio-easy-deactivate' class='button-secondary' href='<?php echo esc_url( admin_url( 'admin.php?action=ewww_image_optimizer_network_remove_easyio' ) ); ?>'>
							<?php esc_html_e( 'De-activate All Sites', 'ewww-image-optimizer' ); ?>
						</a>
			<?php endif; ?>
		<?php elseif ( ! $exactdn_enabled ) : ?>
						<p class="ewwwio-easy-setup-instructions">
							<strong><a href="https://ewww.io/plans/" target="_blank">
								<?php esc_html_e( 'Purchase a subscription for your site.', 'ewww-image-optimizer' ); ?>
							</a></strong><br>
							<a href="https://ewww.io/manage-sites/" target="_blank"><?php esc_html_e( 'Then, add your Site URL to your account:', 'easy-image-optimizer' ); ?></a>
							<input type='text' id='exactdn_site_url' name='exactdn_site_url' value='<?php echo esc_url( trim( $easyio_site_url ) ); ?>' readonly />
							<span id='exactdn-site-url-copy'><?php esc_html_e( 'Click to Copy', 'ewww-image-optimizer' ); ?></span>
							<span id='exactdn-site-url-copied'><?php esc_html_e( 'Copied', 'ewww-image-optimizer' ); ?></span><br>
							<a id='ewwwio-easy-activate' href='#' class='button-secondary'><?php esc_html_e( 'Activate', 'ewww-image-optimizer' ); ?></a>
							<span id='ewwwio-easy-activation-processing'><img src='<?php echo esc_url( $loading_image_url ); ?>' alt='loading'/></span>
						</p>
		<?php elseif ( class_exists( 'ExactDN' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) : ?>
						<p>
				<?php if ( $exactdn->get_exactdn_domain() && $exactdn->verify_domain( $exactdn->get_exactdn_domain() ) ) : ?>
							<span style="color: #3eadc9; font-weight: bolder"><?php esc_html_e( 'Verified', 'ewww-image-optimizer' ); ?></span>
							<br><?php echo esc_html( $exactdn->get_exactdn_domain() ); ?>
				<?php elseif ( $exactdn->get_exactdn_domain() && $exactdn->get_exactdn_option( 'verified' ) ) : ?>
							<span style="color: orange; font-weight: bolder"><?php esc_html_e( 'Temporarily disabled.', 'ewww-image-optimizer' ); ?></span>
				<?php elseif ( $exactdn->get_exactdn_domain() && $exactdn->get_exactdn_option( 'suspended' ) ) : ?>
							<span style="color: orange; font-weight: bolder"><?php esc_html_e( 'Active, not yet verified.', 'ewww-image-optimizer' ); ?></span>';
				<?php else : ?>
							<span style="color: red; font-weight: bolder"><a href="https://ewww.io/manage-sites/" target="_blank"><?php esc_html_e( 'Not Verified', 'ewww-image-optimizer' ); ?></a></span>
				<?php endif; ?>
				<?php if ( function_exists( 'remove_query_strings_link' ) || function_exists( 'rmqrst_loader_src' ) || function_exists( 'qsr_remove_query_strings_1' ) ) : ?>
							<br><i><?php esc_html_e( 'Plugins that remove query strings are unnecessary with Easy IO. You may remove them at your convenience.', 'ewww-image-optimizer' ); ?></i><?php ewwwio_help_link( 'https://docs.ewww.io/article/50-exactdn-and-query-strings', '5a3d278a2c7d3a1943677b52' ); ?>
				<?php endif; ?>
							<br>
							<a id='ewwwio-easy-deactivate' class='button-secondary' href='<?php echo esc_url( admin_url( 'admin.php?action=ewww_image_optimizer_remove_easyio' ) ); ?>'>
								<?php esc_html_e( 'De-activate', 'ewww-image-optimizer' ); ?>
							</a>
						</p>
		<?php endif; ?>
					</td>
				</tr>
		<?php $exactdn_settings_row = ob_get_contents(); ?>
		<?php ob_end_flush(); ?>
	<?php endif; ?>
				<tr class='ewwwio-exactdn-options' <?php echo $exactdn_enabled ? '' : 'style="display:none;"'; ?>>
					<td>&nbsp;</td>
					<td>
						<input type='checkbox' name='exactdn_all_the_things' value='true' id='exactdn_all_the_things' <?php checked( ewww_image_optimizer_get_option( 'exactdn_all_the_things' ) ); ?> />
						<label for='exactdn_all_the_things'><strong><?php esc_html_e( 'Include All Resources', 'ewww-image-optimizer' ); ?></strong></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/47-getting-more-from-exactdn', '59de6631042863379ddc953c' ); ?>
						<p class='description'>
							<?php esc_html_e( 'Use Easy IO for all resources in wp-includes/ and wp-content/, including JavaScript, CSS, fonts, etc.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
				<tr class='ewwwio-exactdn-options' <?php echo $exactdn_enabled && ! $easymode ? '' : 'style="display:none;"'; ?>>
					<td>&nbsp;</td>
					<td>
						<input type='checkbox' name='exactdn_lossy' value='true' id='<?php echo esc_attr( $exactdn_los_id ); ?>' <?php disabled( $exactdn_los_dis ); ?> <?php checked( $exactdn_los_che ); ?> />
						<label for='exactdn_lossy'><strong><?php esc_html_e( 'Premium Compression', 'ewww-image-optimizer' ); ?></strong></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/47-getting-more-from-exactdn', '59de6631042863379ddc953c' ); ?>
						<p class='description'>
							<?php esc_html_e( 'Enable high quality premium compression for all images on Easy IO. Disable to use Pixel Perfect mode instead.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
	<?php if ( ! $exactdn_enabled || 1 === $exactdn->get_plan_id() ) : ?>
				<input type='hidden' id='exactdn_lossy' name='exactdn_lossy' <?php echo ( ewww_image_optimizer_get_option( 'exactdn_lossy' ) ? "value='1'" : "value='0'" ); ?> />
				<input type='hidden' id='ewww_image_optimizer_use_lqip' name='ewww_image_optimizer_use_lqip' <?php echo ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_use_lqip' ) ? "value='1'" : "value='0'" ); ?> />
	<?php endif; ?>
				<tr class="ewwwio-exactdn-options" <?php echo $exactdn_enabled ? '' : 'style="display:none;"'; ?>>
					<td>&nbsp;</td>
					<td>
						<label for='exactdn_exclude'><strong><?php esc_html_e( 'Exclusions', 'ewww-image-optimizer' ); ?></strong></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/68-exactdn-exclude', '5c0042892c7d3a31944e88a4' ); ?><br>
						<textarea id='exactdn_exclude' name='exactdn_exclude' rows='3' cols='60'><?php echo esc_html( $eio_exclude_paths ); ?></textarea>
						<p class='description'>
							<?php esc_html_e( 'One exclusion per line, no wildcards (*) needed. Any pattern or path provided will not be routed through Easy IO.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
	<?php if ( ! function_exists( 'swis' ) ) : ?>
				<tr id="swis_promo_container" class="ewwwio-premium-setup" <?php echo wp_kses_post( $premium_hide ); ?>>
					<th scope='row'>
						SWIS Performance
					</th>
					<td>
						<a href="https://ewww.io/swis/" target="_blank"><?php esc_html_e( 'Go beyond image optimization with the tools I use for improving site speed.', 'ewww-image-optimizer' ); ?></a>
					</td>
				</tr>
	<?php endif; ?>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_metadata_remove'><?php esc_html_e( 'Remove Metadata', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2' ); ?>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_metadata_remove' name='ewww_image_optimizer_metadata_remove' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ); ?> />
						<?php esc_html_e( 'This will remove ALL metadata: EXIF, comments, color profiles, and anything else that is not pixel data.', 'ewww-image-optimizer' ); ?>
						<p class ='description'><?php esc_html_e( 'Color profiles are preserved when using the API or Easy IO.', 'ewww-image-optimizer' ); ?></p>
					</td>
				</tr>
	<?php if ( $current_jpeg_quality > 90 || $current_jpeg_quality < 50 || ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_quality' ) ) : ?>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_jpg_quality'><?php esc_html_e( 'JPG Quality Level', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,58543c69c697912ffd6c19a7' ); ?>
					</th>
					<td>
						<input type='text' id='ewww_image_optimizer_jpg_quality' name='ewww_image_optimizer_jpg_quality' class='small-text' value='<?php echo esc_attr( ewww_image_optimizer_jpg_quality() ); ?>' />
						<?php esc_html_e( 'Valid values are 1-100.', 'ewww-image-optimizer' ); ?>
						<p class='description'>
							<?php esc_html_e( 'Use this to override the default WordPress quality level of 82. Applies to image editing, resizing, and PNG to JPG conversion. Does not affect the original uploaded image unless maximum dimensions are set and resizing occurs.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
	<?php endif; ?>
				<tr>
					<th scope='row'>
						<?php esc_html_e( 'Resize Images', 'ewww-image-optimizer' ); ?>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/41-resize-settings', '59849911042863033a1ba5f9' ); ?>
					</th>
					<td>
						<label for='ewww_image_optimizer_maxmediawidth'><?php esc_html_e( 'Max Width', 'ewww-image-optimizer' ); ?></label>
						<input type='number' step='1' min='0' class='small-text' id='ewww_image_optimizer_maxmediawidth' name='ewww_image_optimizer_maxmediawidth' value='<?php	echo (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' ); ?>' <?php disabled( function_exists( 'imsanity_get_max_width_height' ) ); ?> />
						<label for='ewww_image_optimizer_maxmediaheight'><?php esc_html_e( 'Max Height', 'ewww-image-optimizer' ); ?></label>
						<input type='number' step='1' min='0' class='small-text' id='ewww_image_optimizer_maxmediaheight' name='ewww_image_optimizer_maxmediaheight' value='<?php echo (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' ); ?>' <?php disabled( function_exists( 'imsanity_get_max_width_height' ) ); ?> />
						<?php esc_html_e( 'in pixels', 'ewww-image-optimizer' ); ?>
	<?php if ( function_exists( 'imsanity_get_max_width_height' ) ) : ?>
						<p>
							<span style="color: #3eadc9"><?php esc_html_e( '*Imsanity settings override the resize dimensions.', 'ewww-image-optimizer' ); ?></span>
						</p>
	<?php else : ?>
						<p class='description'>
							<?php esc_html_e( 'Resize uploaded images to these dimensions (in pixels).', 'ewww-image-optimizer' ); ?>
							<?php
							printf(
								/* translators: %s: Bulk Optimizer (link) */
								esc_html__( 'Use the %s for existing uploads.', 'ewww-image-optimizer' ),
								'<a href="' . esc_url( admin_url( 'upload.php?page=ewww-image-optimizer-bulk' ) ) . '">' . esc_html__( 'Bulk Optimizer', 'ewww-image-optimizer' ) . '</a>'
							);
							?>
						</p>
	<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_add_missing_dims'>
							<?php esc_html_e( 'Add Missing Dimensions', 'ewww-image-optimizer' ); ?>
						</label>
					</th>
					<td>
	<?php if ( function_exists( 'easyio_get_option' ) && easyio_get_option( 'easyio_lazy_load' ) && easyio_get_option( 'easyio_add_missing_dims' ) ) : ?>
						<p class='description'><?php esc_html_e( 'Enabled in Easy Image Optimizer.', 'ewww-image-optimizer' ); ?></p>
						<input type='hidden' id='ewww_image_optimizer_add_missing_dims' name='ewww_image_optimizer_add_missing_dims' <?php echo ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_add_missing_dims' ) ? "value='1'" : "value='0'" ); ?> />
	<?php else : ?>
						<input type='checkbox' id='ewww_image_optimizer_add_missing_dims' name='ewww_image_optimizer_add_missing_dims' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_add_missing_dims' ) ); ?> <?php disabled( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ); ?> />
						<?php esc_html_e( 'Add width/height attributes to reduce layout shifts and improve user experience.', 'ewww-image-optimizer' ); ?>
		<?php if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ) : ?>
						<p class ='description'>*<?php esc_html_e( 'Requires Lazy Load.', 'ewww-image-optimizer' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_lazy_load'><?php esc_html_e( 'Lazy Load', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/74-lazy-load', '5c6c36ed042863543ccd2d9b' ); ?>
					</th>
					<td>
	<?php if ( function_exists( 'easyio_get_option' ) && easyio_get_option( 'easyio_lazy_load' ) ) : ?>
						<p class='description'><?php esc_html_e( 'Lazy Load enabled in Easy Image Optimizer.', 'ewww-image-optimizer' ); ?></p>
	<?php else : ?>
						<input type='checkbox' id='ewww_image_optimizer_lazy_load' name='ewww_image_optimizer_lazy_load' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ); ?> />
						<?php esc_html_e( 'Improves actual and perceived loading time as images will be loaded only as they enter (or are about to enter) the viewport.', 'ewww-image-optimizer' ); ?>
		<?php if ( ewwwio_other_lazy_detected() ) : ?>
						<p><strong><?php esc_html_e( 'Though you already have a lazy loader on your site, the EWWW IO lazy loader includes auto-scaling for improved responsive images.', 'ewww-image-optimizer' ); ?></strong></p>
		<?php endif; ?>
						<p class='description'>
							<?php esc_html_e( 'The lazy loader chooses the best available image size from existing responsive markup. When used with Easy IO, all images become responsive.', 'ewww-image-optimizer' ); ?></br>
						</p>
					</td>
				</tr>
				<tr id='ewww_image_optimizer_ll_autoscale_container' <?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ? '' : ' style="display:none"'; ?>>
					<td>&nbsp;</td>
					<td>
						<input type='checkbox' name='ewww_image_optimizer_ll_autoscale' value='true' id='ewww_image_optimizer_ll_autoscale' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_ll_autoscale' ) ); ?> />
						<label for='ewww_image_optimizer_ll_autoscale'><strong><?php esc_html_e( 'Automatic Scaling', 'ewww-image-optimizer' ); ?></strong></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/74-lazy-load', '5c6c36ed042863543ccd2d9b' ); ?>
						<p class='description'>
							<?php esc_html_e( 'Automatically detect the correct image size within responsive (srcset) markup.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
				<!-- <tr id='ewww_image_optimizer_siip_container' <?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ? '' : ' style="display:none"'; ?>>
					<td>&nbsp;</td>
					<td>
						<input type='checkbox' name='ewww_image_optimizer_use_siip' value='true' id='ewww_image_optimizer_use_siip' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_use_siip' ) ); ?> />
						<label for='ewww_image_optimizer_use_siip'><strong><?php esc_html_e( 'SVG Placeholders', 'ewww-image-optimizer' ); ?></strong></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/75-lazy-load-placeholders', '5c9a7a302c7d3a1544615e47' ); ?>
						<p class='description'>
							<?php esc_html_e( 'Inline SVG placeholders use fewer HTTP requests than right-sized PNG placeholders, but may not work with some themes.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr> -->
				<tr id='ewww_image_optimizer_lqip_container' <?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ? '' : ' style="display:none"'; ?>>
					<td>&nbsp;</td>
					<td>
						<input type='checkbox' name='ewww_image_optimizer_use_lqip' value='true' id='<?php echo esc_attr( $lqip_id ); ?>' <?php disabled( $lqip_dis ); ?> <?php checked( $lqip_che ); ?> />
						<label for='ewww_image_optimizer_use_lqip'><strong>LQIP</strong></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/75-lazy-load-placeholders', '5c9a7a302c7d3a1544615e47' ); ?>
						<p class='description'>
							<?php esc_html_e( 'Use low-quality versions of your images as placeholders via Easy IO. Can improve user experience, but may be slower than blank placeholders.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
				<tr id='ewww_image_optimizer_ll_all_things_container' <?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ? '' : ' style="display:none"'; ?>>
					<td>&nbsp;</td>
					<td>
						<label for='ewww_image_optimizer_ll_all_things'><strong><?php esc_html_e( 'External Background Images', 'ewww-image-optimizer' ); ?></strong></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/74-lazy-load', '5c6c36ed042863543ccd2d9b' ); ?><br>
						<input type='text' name='ewww_image_optimizer_ll_all_things' id='ewww_image_optimizer_all_things' class='regular-text' value='<?php echo esc_attr( ewww_image_optimizer_get_option( 'ewww_image_optimizer_ll_all_things' ) ); ?>' />
						<p class='description'>
							<?php esc_html_e( 'Specify class/id values of elements with CSS background images (comma-separated).', 'ewww-image-optimizer' ); ?>
							<br>*<?php esc_html_e( 'Background images directly attached via inline style attributes will be lazy loaded by default.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
		<?php if ( true || ! $easymode ) : ?>
		<?php endif; ?>
				<tr id='ewww_image_optimizer_ll_exclude_container' <?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ? '' : ' style="display:none"'; ?>>
					<td>&nbsp;</td>
					<td>
						<label for='ewww_image_optimizer_ll_exclude'><strong><?php esc_html_e( 'Exclusions', 'ewww-image-optimizer' ); ?></strong></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/74-lazy-load', '5c6c36ed042863543ccd2d9b' ); ?><br>
						<textarea id='ewww_image_optimizer_ll_exclude' name='ewww_image_optimizer_ll_exclude' rows='3' cols='60'><?php echo esc_html( $ll_exclude_paths ); ?></textarea>
						<p class='description'>
							<?php esc_html_e( 'One exclusion per line, no wildcards (*) needed. Use any string that matches the desired element(s) or exclude entire element types like "div", "span", etc. The class "skip-lazy" and attribute "data-skip-lazy" are excluded by default.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
		<?php if ( false && $easymode ) : ?>
				<tr id='ewww_image_optimizer_more_options_container' <?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ? '' : ' style="display:none"'; ?>>
					<td>&nbsp;</td>
					<td>
						<p class='description'>
							<?php
							printf(
								/* translators: %s: Ludicrous Mode */
								'*' . esc_html__( 'More Lazy Load options are available in %s.', 'ewww-image-optimizer' ),
								"<a href='" . esc_url( $enable_local_url ) . "'>" . esc_html__( 'Ludicrous Mode', 'ewww-image-optimizer' ) . '</a>'
							);
							?>
						</p>
					</td>
				</tr>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( ! $webp_available ) : ?>
				<tr id='ewww_image_optimizer_webp_container'>
					<th scope='row'>
						<label for='ewww_image_optimizer_webp'><?php esc_html_e( 'WebP Conversion', 'ewww-image-optimizer' ); ?></label>
					</th>
					<td>
						<p class='description'><?php esc_html_e( 'Your site needs an API key or Easy IO subscription for WebP conversion.', 'ewww-image-optimizer' ); ?></p>
					</td>
				</tr>
	<?php elseif ( ! ewww_image_optimizer_easy_active() || ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) : ?>
				<tr id='ewww_image_optimizer_webp_container'>
					<th scope='row'>
						<label for='ewww_image_optimizer_webp'><?php esc_html_e( 'WebP Conversion', 'ewww-image-optimizer' ); ?></label>
						<span><?php ewwwio_help_link( 'https://docs.ewww.io/article/16-ewww-io-and-webp-images', '5854745ac697912ffd6c1c89' ); ?></span>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_webp' name='ewww_image_optimizer_webp' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ); ?> />
						<span><?php esc_html_e( 'Convert your images to the next generation format for supported browsers, while retaining originals for other browsers.', 'ewww-image-optimizer' ); ?></span>
		<?php if ( ! ewww_image_optimizer_easy_active() ) : ?>
						<p id='ewwwio-webp-storage-warning'>
							<i><?php esc_html_e( 'Enabling WebP Conversion without Easy IO will increase your storage requirements. Do you want to continue?', 'ewww-image-optimizer' ); ?></i><br>
							<a id='ewwwio-cancel-webp' href='#'><?php esc_html_e( 'Nevermind', 'ewww-image-optimizer' ); ?></a><br>
							<a id='ewwwio-easyio-webp-info' class='ewww-help-beacon-single' href='https://docs.ewww.io/article/44-introduction-to-exactdn' data-beacon-article='59bc5ad6042863033a1ce370'><?php esc_html_e( 'Tell me more about Easy IO', 'ewww-image-optimizer' ); ?></a><br>
							<?php ewwwio_help_link( 'https://docs.ewww.io/article/44-introduction-to-exactdn', '59bc5ad6042863033a1ce370,5c0042892c7d3a31944e88a4' ); ?>
							<span id='ewwwio-confirm-webp' class='button-primary'><?php esc_html_e( 'Continue', 'ewww-image-optimizer' ); ?></span>
						</p>
						<p class='description'>
							<?php esc_html_e( 'WebP images will be generated automatically for new uploads.', 'ewww-image-optimizer' ); ?>
							<?php
							printf(
								/* translators: 1: Bulk Optimizer 2: Easy IO */
								esc_html__( 'Use the %1$s for existing uploads or get %2$s for automatic WebP conversion and delivery.', 'ewww-image-optimizer' ),
								'<a href="' . esc_url( admin_url( 'upload.php?page=ewww-image-optimizer-bulk' ) ) . '">' . esc_html__( 'Bulk Optimizer', 'ewww-image-optimizer' ) . '</a>',
								'<a href="https://ewww.io/plans/">' . esc_html__( 'Easy IO', 'ewww-image-optimizer' ) . '</a>'
							);
							?>
						</p>
		<?php endif; ?>
		<?php if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) : ?>
						<p class='description'>
							*<?php esc_html_e( 'GIF to WebP conversion requires an active API key.', 'ewww-image-optimizer' ); ?>
						</p>
		<?php endif; ?>
					</td>
				</tr>
				<tr>
	<?php endif; ?>
	<?php if ( ewww_image_optimizer_easy_active() ) : ?>
				<tr id='ewww_image_optimizer_webp_easyio_container'>
					<th scope='row'>
						<label for='ewww_image_optimizer_webp'><?php esc_html_e( 'WebP Conversion', 'ewww-image-optimizer' ); ?></label>
					</th>
					<td>
						<p class='description'><?php esc_html_e( 'WebP images are served automatically by Easy IO.', 'ewww-image-optimizer' ); ?></p>
					</td>
				</tr>
	<?php elseif ( ewww_image_optimizer_ce_webp_enabled() ) : ?>
				<tr id='ewww_image_optimizer_webp_setting_container'>
					<th scope='row'>
						<?php esc_html_e( 'WebP Delivery Method', 'ewww-image-optimizer' ); ?>
					</th>
					<td>
						<p class='description'><?php esc_html_e( 'WebP images are delivered by Cache Enabler.', 'ewww-image-optimizer' ); ?></p>
					</td>
				</tr>
	<?php elseif ( ewww_image_optimizer_swis_webp_enabled() ) : ?>
				<tr id='ewww_image_optimizer_webp_setting_container'>
					<th scope='row'>
						<?php esc_html_e( 'WebP Delivery Method', 'ewww-image-optimizer' ); ?>
					</th>
					<td>
						<p class='description'><?php esc_html_e( 'WebP images are delivered by SWIS Performance.', 'ewww-image-optimizer' ); ?></p>
					</td>
				</tr>
	<?php elseif ( $webp_available ) : ?>
				<tr id='ewww_image_optimizer_webp_easyio_container' style='display:none;'>
					<th scope='row'>
						<label for='ewww_image_optimizer_webp'><?php esc_html_e( 'WebP Conversion', 'ewww-image-optimizer' ); ?></label>
					</th>
					<td>
						<p class='description'><?php esc_html_e( 'WebP images are served automatically by Easy IO.', 'ewww-image-optimizer' ); ?></p>
					</td>
				</tr>
				<tr class='ewww_image_optimizer_webp_setting_container' <?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ? '' : ' style="display:none"'; ?>>
					<th scope='row'>
						<?php esc_html_e( 'WebP Delivery Method', 'ewww-image-optimizer' ); ?>
					</th>
					<td>
						<!-- This will be handled further down now, with fully-functional rule inserter. -->
		<?php if ( false && ! $cf_host && ! $cloudways_host && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) : ?>
						<?php
						printf(
							/* translators: %s: documentation */
							esc_html__( 'The recommended delivery method is to use Apache/LiteSpeed rewrite rules. Nginx users may reference the %s for configuration instructions.', 'ewww-image-optimizer' ),
							'<a class="ewww-help-beacon-single" href="https://docs.ewww.io/article/16-ewww-io-and-webp-images" target="_blank" data-beacon-article="5854745ac697912ffd6c1c89">' . esc_html__( 'documentation', 'ewww-image-optimizer' ) . '</a>'
						);
						?>
		<?php else : ?>
		<?php endif; ?>
		<?php
		if (
			! $cf_host &&
			! $cloudways_host &&
			! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) &&
			! ewww_image_optimizer_get_option( 'ewww_image_optimizer_picture_webp' )
		) :
			$webp_mime_error     = false;
			$webp_rewrite_verify = false;
			// Only check the rules for problems if WebP is enabled, otherwise this is a blank slate.
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) :
				if ( defined( 'PHP_SAPI' ) && false === strpos( PHP_SAPI, 'apache' ) && false === strpos( PHP_SAPI, 'litespeed' ) ) {
					$false_positive_headers = esc_html( 'This may be a false positive. If so, the warning should go away once you implement the rewrite rules.' );
				}
				$header_error = '';
				if ( ! apache_mod_loaded( 'mod_rewrite' ) ) {
					/* translators: %s: mod_rewrite or mod_headers */
					$header_error = '<p class="ewww-webp-rewrite-info"><strong>' . sprintf( esc_html__( 'Your site appears to be missing %s, please contact your webhost or system administrator to enable this Apache module.', 'ewww-image-optimizer' ), 'mod_rewrite' ) . "</strong><br>$false_positive_headers</p>\n";
				}
				if ( ! apache_mod_loaded( 'mod_headers' ) ) {
					/* translators: %s: mod_rewrite or mod_headers */
					$header_error = '<p class="ewww-webp-rewrite-info"><strong>' . sprintf( esc_html__( 'Your site appears to be missing %s, please contact your webhost or system administrator to enable this Apache module.', 'ewww-image-optimizer' ), 'mod_headers' ) . "</strong><br>$false_positive_headers</p>\n";
				}

				$webp_mime_error = ewww_image_optimizer_test_webp_mime_error();
				if ( $webp_mime_error ) {
					echo wp_kses_post( $header_error );
				}

				$webp_rewrite_verify = ! (bool) ewww_image_optimizer_webp_rewrite_verify();
			endif;
			if ( $webp_mime_error && $webp_rewrite_verify ) :
				printf(
					/* translators: %s: an error message from the WebP self-test */
					'<p class="ewww-webp-rewrite-info">' . esc_html__( 'WebP rules verified, but self-test failed: %s', 'ewww-image-optimizer' ) . '</p>',
					esc_html( $webp_mime_error )
				);
			elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) && ( ! $webp_mime_error || $webp_rewrite_verify ) ) :
				?>
						<div id='ewww-webp-rewrite'>
							<img id='ewww-webp-image' src='<?php echo esc_url( $test_png_image . '?m=' . time() ); ?>'>
							<div id='ewww-webp-rewrite-status'>
								<p>
									<?php esc_html_e( 'WebP Rules verified successfully', 'ewww-image-optimizer' ); ?>
								</p>
							</div>
				<?php if ( $webp_rewrite_verify ) : ?>
							<div id='ewww-webp-rewrite-result'></div>
							<button type='button' id='ewww-webp-remove' class='button-secondary action'><?php esc_html_e( 'Remove Rewrite Rules', 'ewww-image-optimizer' ); ?></button>
				<?php endif; ?>
						</div>
			<?php else : ?>
						<p class='ewww-webp-rewrite-info'>
							<?php
							printf(
								/* translators: %s: documentation */
								esc_html__( 'The recommended delivery method is to use Apache/LiteSpeed rewrite rules. Nginx users may reference the %s for configuration instructions.', 'ewww-image-optimizer' ),
								'<a class="ewww-help-beacon-single" href="https://docs.ewww.io/article/16-ewww-io-and-webp-images" target="_blank" data-beacon-article="5854745ac697912ffd6c1c89">' . esc_html__( 'documentation', 'ewww-image-optimizer' ) . '</a>'
							);
							?>
						</p>
						<div id='ewww-webp-rewrite'>
<pre id='webp-rewrite-rules'>&lt;IfModule mod_rewrite.c&gt;
	RewriteEngine On
	RewriteCond %{HTTP_ACCEPT} image/webp
	RewriteCond %{REQUEST_FILENAME} (.*)\.(jpe?g|png|gif)$
	RewriteCond %{REQUEST_FILENAME}\.webp -f
	RewriteCond %{QUERY_STRING} !type=original
	RewriteRule (.+)\.(jpe?g|png|gif)$ %{REQUEST_URI}.webp [T=image/webp,L]
&lt;/IfModule&gt;
&lt;IfModule mod_headers.c&gt;
	&lt;FilesMatch "\.(jpe?g|png|gif)$"&gt;
		Header append Vary Accept
	&lt;/FilesMatch&gt;
&lt;/IfModule&gt;
AddType image/webp .webp</pre>
							<img id='ewww-webp-image' src='<?php echo esc_url( $test_png_image . '?m=' . time() ); ?>'>
							<p id='ewww-webp-rewrite-status'>
								<?php esc_html_e( 'The image to the right will display a WebP image with WEBP in white text, if your site is serving WebP images and your browser supports WebP.', 'ewww-image-optimizer' ); ?>
							</p>
							<div id='ewww-webp-rewrite-result'></div>
							<button type='button' id='ewww-webp-insert' class='button-secondary action'><?php esc_html_e( 'Insert Rewrite Rules', 'ewww-image-optimizer' ); ?></button>
						</div>
			<?php endif; ?>
		<?php elseif ( $cf_host && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) : ?>
			<p><?php esc_html_e( 'Your site is using Cloudflare, please use JS WebP or <picture> WebP rewriting to prevent broken images on older browsers.', 'ewww-image-optimizer' ); ?></p>
		<?php elseif ( $cloudways_host && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) : ?>
			<p><?php esc_html_e( 'Cloudways sites should use JS WebP or <picture> WebP rewriting to prevent broken images on older browsers.', 'ewww-image-optimizer' ); ?></p>
		<?php endif; ?>
					</td>
				</tr>
				<tr class='ewww_image_optimizer_webp_setting_container' <?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) ? '' : ' style="display:none"'; ?>>
					<th>&nbsp;</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_webp_for_cdn' name='ewww_image_optimizer_webp_for_cdn' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) ); ?> />
						<label for='ewww_image_optimizer_webp_for_cdn'><strong><?php esc_html_e( 'JS WebP Rewriting', 'ewww-image-optimizer' ); ?></strong></label>
						<span><?php ewwwio_help_link( 'https://docs.ewww.io/article/16-ewww-io-and-webp-images', '5854745ac697912ffd6c1c89,59443d162c7d3a0747cdf9f0' ); ?></span>
						<p class='description'>
							<?php esc_html_e( 'Uses JavaScript for CDN and cache friendly WebP delivery.', 'ewww-image-optimizer' ); ?>
							<?php esc_html_e( 'Supports CSS background images via the Lazy Load option.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
				<tr class='ewww_image_optimizer_webp_setting_container' <?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_picture_webp' ) ? '' : ' style="display:none"'; ?>>
					<th>&nbsp;</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_picture_webp' name='ewww_image_optimizer_picture_webp' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_picture_webp' ) ); ?> />
						<label for='ewww_image_optimizer_picture_webp'><strong><?php esc_html_e( '<picture> WebP Rewriting', 'ewww-image-optimizer' ); ?></strong></label>
						<span><?php ewwwio_help_link( 'https://docs.ewww.io/article/16-ewww-io-and-webp-images', '5854745ac697912ffd6c1c89,59443d162c7d3a0747cdf9f0' ); ?></span><br>
						<p class='description'>
							<?php esc_html_e( 'A JavaScript-free rewriting method using picture tags.', 'ewww-image-optimizer' ); ?>
							<?php esc_html_e( 'Some themes may not display <picture> tags properly.', 'ewww-image-optimizer' ); ?>
							<?php esc_html_e( 'May be combined with JS WebP and Lazy Load for CSS background image support.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
				<tr class='ewww_image_optimizer_webp_rewrite_setting_container' <?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) && $webp_php_rewriting ? '' : ' style="display:none"'; ?>>
					<td>&nbsp;</td>
					<td>
						<label for='ewww_image_optimizer_webp_rewrite_exclude'><strong><?php esc_html_e( 'JS WebP and <picture> Web Exclusions', 'ewww-image-optimizer' ); ?></strong></label><br>
						<textarea id='ewww_image_optimizer_webp_rewrite_exclude' name='ewww_image_optimizer_webp_rewrite_exclude' rows='3' cols='60'><?php echo esc_html( $webp_exclude_paths ); ?></textarea>
						<p class='description'><?php esc_html_e( 'One exclusion per line, no wildcards (*) needed. Use any string that matches the desired element(s) or exclude entire element types like "div", "span", etc.', 'ewww-image-optimizer' ); ?></p>
					</td>
				</tr>
		<?php if ( ! $easymode ) : ?>
				<tr class='ewww_image_optimizer_webp_rewrite_setting_container' <?php echo $webp_php_rewriting ? '' : ' style="display:none"'; ?>>
					<th>&nbsp;</th>
					<td>
						<label for='ewww_image_optimizer_webp_paths'><strong><?php esc_html_e( 'WebP URLs', 'ewww-image-optimizer' ); ?></strong></label>
						<span><?php ewwwio_help_link( 'https://docs.ewww.io/article/16-ewww-io-and-webp-images', '5854745ac697912ffd6c1c89' ); ?></span><br>
						<span><?php esc_html_e( 'Enter additional URL patterns, like a CDN URL, that should be permitted for WebP Rewriting. One URL per line, must include the domain name (cdn.example.com).', 'ewww-image-optimizer' ); ?></span>
						<p><?php esc_html_e( 'Optionally include a folder with the URL if your CDN path is different from your local path.', 'ewww-image-optimizer' ); ?></p>
						<textarea id='ewww_image_optimizer_webp_paths' name='ewww_image_optimizer_webp_paths' rows='3' cols='60'><?php echo esc_html( $webp_paths ); ?></textarea>
						<?php
						if ( ewww_image_optimizer_cloud_based_media() ) {
							$webp_domains = false;
							if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) ) {
								global $eio_alt_webp;
								if ( isset( $eio_alt_webp ) && is_object( $eio_alt_webp ) ) {
									$webp_domains = $eio_alt_webp->get_webp_domains();
								}
							} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_picture_webp' ) ) {
								global $eio_picture_webp;
								if ( isset( $eio_picture_webp ) && is_object( $eio_picture_webp ) ) {
									$webp_domains = $eio_picture_webp->get_webp_domains();
								}
							}
							if ( ! empty( $webp_domains ) ) {
								echo "<p class='description'>";
								printf(
									/* translators: %s: a comma-separated list of domain names */
									'*' . esc_html__( 'These domains have been auto-detected: %s', 'ewww-image-optimizer' ),
									esc_html( implode( ',', $webp_domains ) )
								);
								echo '</p>';
							}
						}
						?>
						<p class='description'><?php echo wp_kses_post( $webp_url_example ); ?></p>
					</td>
				</tr>
				<tr class='ewww_image_optimizer_webp_setting_container' <?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ? '' : ' style="display:none"'; ?>>
					<th scope='row'>
						<label for='ewww_image_optimizer_webp_force'><?php esc_html_e( 'Force WebP', 'ewww-image-optimizer' ); ?></label>
						<span><?php ewwwio_help_link( 'https://docs.ewww.io/article/16-ewww-io-and-webp-images', '5854745ac697912ffd6c1c89' ); ?></span>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_webp_force' name='ewww_image_optimizer_webp_force' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ); ?> />
						<span><?php esc_html_e( 'WebP images will be generated and saved for all images regardless of their size. JS and <picture> WebP rewriters will not check if a file exists, only that the domain matches the home url, or one of the provided WebP URLs.', 'ewww-image-optimizer' ); ?></span>
			<?php if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_force_gif2webp' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) : ?>
						<p>
							<a href='<?php echo esc_url( admin_url( 'admin.php?action=ewww_image_optimizer_enable_force_gif2webp' ) ); ?>'>
								<?php esc_html_e( 'Click to enable forced GIF rewriting once WebP version have been generated.', 'ewww-image-optimizer' ); ?>
							</a>
						</p>
			<?php elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_force_gif2webp' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) : ?>
						<p>
							<a href="https://ewww.io/plans/" target="_blank">
								<?php esc_html_e( 'GIF to WebP conversion requires an API key.', 'ewww-image-optimizer' ); ?>
							</a>
						</p>
			<?php endif; ?>
					</td>
				</tr>
				<tr class='ewww_image_optimizer_webp_setting_container' <?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ? '' : ' style="display:none"'; ?>>
					<th scope='row'>
						<label for='ewww_image_optimizer_webp_quality'><?php esc_html_e( 'WebP Quality Level', 'ewww-image-optimizer' ); ?></label>
					</th>
					<td>
						<input type='text' id='ewww_image_optimizer_webp_quality' name='ewww_image_optimizer_webp_quality' class='small-text' value='<?php echo esc_attr( ewww_image_optimizer_webp_quality() ); ?>' />
						<?php esc_html_e( 'Default is 75, allowed range is 50-100.', 'ewww-image-optimizer' ); ?>
					</td>
				</tr>
		<?php elseif ( ewww_image_optimizer_cloud_based_media() ) : ?>
				<tr class='ewww_image_optimizer_webp_setting_container' <?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ? '' : ' style="display:none"'; ?>>
					<th>&nbsp;</th>
					<td>
						<p class='description'>
							<?php
							printf(
								/* translators: %s: Enable Ludicrous Mode */
								'*' . esc_html__( 'It seems your images are being offloaded to cloud-based storage without retaining the local copies. Force WebP mode has been configured automatically. %s to view/change these settings.', 'ewww-image-optimizer' ),
								"<a href='" . esc_url( $enable_local_url ) . "'>" . esc_html__( 'Enable Ludicrous Mode', 'ewww-image-optimizer' ) . '</a>'
							);
							?>
						</p>
					</td>
				</tr>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( class_exists( 'Cloudinary' ) && Cloudinary::config_get( 'api_secret' ) ) : ?>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_enable_cloudinary'><?php esc_html_e( 'Automatic Cloudinary Upload', 'ewww-image-optimizer' ); ?></label>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_enable_cloudinary' name='ewww_image_optimizer_enable_cloudinary' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_cloudinary' ) ); ?> />
						<?php esc_html_e( 'When enabled, uploads to the Media Library will be transferred to Cloudinary after optimization. Cloudinary generates resizes, so only the full-size image is uploaded.', 'ewww-image-optimizer' ); ?>
					</td>
				</tr>
	<?php endif; ?>
			</table>
	<?php ob_end_flush(); ?>
		</div>

		<div id='ewww-local-settings'>
			<noscript><h2><?php esc_html_e( 'Local', 'ewww-image-optimizer' ); ?></h2></noscript>
			<p>
	<?php if ( $exactdn_enabled && 1 === $exactdn->get_plan_id() ) : ?>
				<br><i>* <?php esc_html_e( 'Upgrade to a Pro or Developer subscription to unlock additional options below.', 'ewww-image-optimizer' ); ?></i>
	<?php endif; ?>
			</p>
			<table class='form-table'>
	<?php $maybe_api_level = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ? '*' : ''; ?>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_jpg_level'><?php esc_html_e( 'JPG Optimization Level', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/102-local-compression-options', '60c24b24a6d12c2cd643e9fb' ); ?>
					</th>
					<td>
						<select id='ewww_image_optimizer_jpg_level' name='ewww_image_optimizer_jpg_level'>
							<option value='0' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ), 0 ); ?>>
								<?php esc_html_e( 'No Compression', 'ewww-image-optimizer' ); ?>
							</option>
	<?php if ( defined( 'EWWW_IMAGE_OPTIMIZER_TOOL_PATH' ) ) : ?>
							<option value='10' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ), 10 ); ?>>
								<?php esc_html_e( 'Pixel Perfect', 'ewww-image-optimizer' ); ?>
							</option>
	<?php endif; ?>
							<option <?php disabled( $disable_level ); ?> value='20' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ), 20 ); ?>>
								<?php esc_html_e( 'Pixel Perfect Plus', 'ewww-image-optimizer' ); ?> *
							</option>
							<option <?php disabled( $disable_level ); ?> value='30' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ), 30 ); ?>>
								<?php esc_html_e( 'Premium', 'ewww-image-optimizer' ); ?> *
							</option>
							<option <?php disabled( $disable_level ); ?> value='40' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ), 40 ); ?>>
								<?php esc_html_e( 'Premium Plus', 'ewww-image-optimizer' ); ?> *
							</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_png_level'><?php esc_html_e( 'PNG Optimization Level', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/102-local-compression-options', '60c24b24a6d12c2cd643e9fb' ); ?>
					</th>
					<td>
						<select id='ewww_image_optimizer_png_level' name='ewww_image_optimizer_png_level'>
							<option value='0' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ), 0 ); ?>>
								<?php esc_html_e( 'No Compression', 'ewww-image-optimizer' ); ?>
							</option>
	<?php if ( defined( 'EWWW_IMAGE_OPTIMIZER_TOOL_PATH' ) ) : ?>
							<option <?php disabled( $free_exec ); ?> value='10' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ), 10 ); ?>>
								<?php esc_html_e( 'Pixel Perfect', 'ewww-image-optimizer' ); ?>
							</option>
	<?php endif; ?>
							<option <?php disabled( $disable_level ); ?> value='20' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ), 20 ); ?><?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ), 30 ); ?>>
								<?php esc_html_e( 'Pixel Perfect Plus', 'ewww-image-optimizer' ); ?> *
							</option>
							<option <?php disabled( $free_exec ); ?> value='40' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ), 40 ); ?>>
								<?php esc_html_e( 'Premium', 'ewww-image-optimizer' ) . ' ' . $maybe_api_level; ?>
							</option>
							<option <?php disabled( $disable_level ); ?> value='50' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ), 50 ); ?>>
								<?php esc_html_e( 'Premium Plus', 'ewww-image-optimizer' ); ?> *
							</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_gif_level'><?php esc_html_e( 'GIF Optimization Level', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/102-local-compression-options', '60c24b24a6d12c2cd643e9fb' ); ?>
					</th>
					<td>
						<select id='ewww_image_optimizer_gif_level' name='ewww_image_optimizer_gif_level'>
							<option value='0' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ), 0 ); ?>>
								<?php esc_html_e( 'No Compression', 'ewww-image-optimizer' ); ?>
							</option>
							<option <?php disabled( $free_exec ); ?> value='10' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ), 10 ); ?>>
								<?php esc_html_e( 'Pixel Perfect', 'ewww-image-optimizer' ) . ' ' . $maybe_api_level; ?>
							</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_pdf_level'><?php esc_html_e( 'PDF Optimization Level', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/102-local-compression-options', '60c24b24a6d12c2cd643e9fb' ); ?>
					</th>
					<td>
						<select id='ewww_image_optimizer_pdf_level' name='ewww_image_optimizer_pdf_level'>
							<option value='0' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ), 0 ); ?>>
								<?php esc_html_e( 'No Compression', 'ewww-image-optimizer' ); ?>
							</option>
							<option <?php disabled( $disable_level ); ?> value='10' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ), 10 ); ?>>
								<?php esc_html_e( 'Pixel Perfect', 'ewww-image-optimizer' ); ?> *
							</option>
							<option <?php disabled( $disable_level ); ?> value='20' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ), 20 ); ?>>
								<?php esc_html_e( 'High Compression', 'ewww-image-optimizer' ); ?> *
							</option>
						</select>
					</td>
				</tr>
	<?php $disable_svg_level = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_svgcleaner' ) && ! ewww_image_optimizer_full_cloud(); ?>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_svg_level'><?php esc_html_e( 'SVG Optimization Level', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/102-local-compression-options', '60c24b24a6d12c2cd643e9fb' ); ?>
					</th>
					<td>
						<select id='ewww_image_optimizer_svg_level' name='ewww_image_optimizer_svg_level'>
							<option value='0' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_svg_level' ), 0 ); ?>>
								<?php esc_html_e( 'No Compression', 'ewww-image-optimizer' ); ?>
							</option>
							<option <?php disabled( $disable_svg_level ); ?> value='1' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_svg_level' ), 1 ); ?>>
								<?php esc_html_e( 'Minimal', 'ewww-image-optimizer' ); ?>
							</option>
							<option <?php disabled( $disable_svg_level ); ?> value='10' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_svg_level' ), 10 ); ?>>
								<?php esc_html_e( 'Default', 'ewww-image-optimizer' ); ?>
							</option>
						</select>
	<?php if ( $disable_svg_level || ( ! EWWW_IMAGE_OPTIMIZER_SVGCLEANER && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?action=ewww_image_optimizer_install_svgcleaner' ) ); ?>"><?php esc_html_e( 'Install svgcleaner', 'ewww-image-optimizer' ); ?></a>
	<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th>&nbsp;</th>
					<td>
	<?php if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) : ?>
						<p>
							* <strong><a href='https://ewww.io/plans/' target='_blank'>
								<?php esc_html_e( 'Get an API key to unlock these optimization levels and receive priority support. Achieve up to 80% compression to speed up your site, save storage space, and reduce server load.', 'ewww-image-optimizer' ); ?>
							</a></strong>
						</p>
	<?php else : ?>
						<p>
							* <?php esc_html_e( 'These levels use the compression API.', 'ewww-image-optimizer' ); ?>
						</p>
	<?php endif; ?>
						<p class='description'>
							<?php esc_html_e( 'All methods used by the EWWW Image Optimizer are intended to produce visually identical images.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_backup_files'><?php esc_html_e( 'Backup Originals', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/102-local-compression-options', '60c24b24a6d12c2cd643e9fb' ); ?>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_backup_files' name='ewww_image_optimizer_backup_files' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ); ?> <?php disabled( $disable_level ); ?>>
						<?php esc_html_e( 'Store a copy of your original images on our secure server for 30 days. *Requires an active API key.', 'ewww-image-optimizer' ); ?>
					</td>
				</tr>
			</table>
		</div>

	<?php
	$media_include_disable = '';
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt', false, true ) ) {
		$media_include_disable = true;
	}
	$aux_paths     = ewww_image_optimizer_get_option( 'ewww_image_optimizer_aux_paths' ) ? implode( "\n", ewww_image_optimizer_get_option( 'ewww_image_optimizer_aux_paths' ) ) : '';
	$exclude_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_exclude_paths' ) ? implode( "\n", ewww_image_optimizer_get_option( 'ewww_image_optimizer_exclude_paths' ) ) : '';
	/* translators: %s: the folder where WordPress is installed */
	$aux_paths_desc = sprintf( __( 'One path per line, must be within %s. Use full paths, not relative paths.', 'ewww-image-optimizer' ), ABSPATH );
	?>

		<div id='ewww-advanced-settings'>
			<noscript><h2><?php esc_html_e( 'Advanced', 'ewww-image-optimizer' ); ?></h2></noscript>
			<table class='form-table'>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_parallel_optimization'><?php esc_html_e( 'Parallel Optimization', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,598cb8be2c7d3a73488be237' ); ?>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_parallel_optimization' name='ewww_image_optimizer_parallel_optimization' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_parallel_optimization' ) ); ?> />
						<?php esc_html_e( 'All resizes generated from a single upload are optimized in parallel for faster optimization. If this is causing performance issues, disable parallel optimization to reduce the load on your server.', 'ewww-image-optimizer' ); ?>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_auto'><?php esc_html_e( 'Scheduled Optimization', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,5853713bc697912ffd6c0b98' ); ?>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_auto' name='ewww_image_optimizer_auto' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_auto' ) ); ?> />
						<?php esc_html_e( 'This will enable scheduled optimization of unoptimized images for your theme, buddypress, and any additional folders you have configured below. Runs hourly: wp_cron only runs when your site is visited, so it may be even longer between optimizations.', 'ewww-image-optimizer' ); ?>
					</td>
				</tr>
	<?php if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt', false, true ) ) : ?>
				<tr>
					<th>&nbsp;</th>
					<td>
						<p>
							<span style="color: #3eadc9"><?php esc_html_e( '*Include Media Library Folders has been disabled because it will cause the scanner to ignore the disabled resizes.', 'ewww-image-optimizer' ); ?></span>
						</p>
					</td>
				</tr>
	<?php endif; ?>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_include_media_paths'><?php esc_html_e( 'Include Media Folders', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,5853713bc697912ffd6c0b98' ); ?>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_include_media_paths' name='ewww_image_optimizer_include_media_paths' <?php disabled( $media_include_disable ); ?> value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_media_paths' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt', false, true ) ); ?> />
						<?php esc_html_e( 'Scan all images from the latest two folders of the Media Library during the Bulk Optimizer and Scheduled Optimization.', 'ewww-image-optimizer' ); ?>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_include_originals'><?php esc_html_e( 'Include Originals', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0' ); ?>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_include_originals' name='ewww_image_optimizer_include_originals' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_originals' ) ); ?> />
						<?php esc_html_e( 'Optimize the original version of images that have been scaled down by WordPress.', 'ewww-image-optimizer' ); ?>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_aux_paths'><?php esc_html_e( 'Folders to Optimize', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,5853713bc697912ffd6c0b98' ); ?>
					</th>
					<td>
						<?php echo esc_html( $aux_paths_desc ); ?><br>
						<textarea id='ewww_image_optimizer_aux_paths' name='ewww_image_optimizer_aux_paths' rows='3' cols='60'><?php echo esc_html( $aux_paths ); ?></textarea>
						<p class='description'>
							<?php esc_html_e( 'Provide paths containing images to be optimized using the Bulk Optimizer and Scheduled Optimization.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_exclude_paths'><?php esc_html_e( 'Folders to Ignore', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,5853713bc697912ffd6c0b98' ); ?>
					</th>
					<td>
						<?php esc_html_e( 'One path per line, partial paths allowed, but no urls.', 'ewww-image-optimizer' ); ?><br>
						<textarea id='ewww_image_optimizer_exclude_paths' name='ewww_image_optimizer_exclude_paths' rows='3' cols='60'><?php echo esc_html( $exclude_paths ); ?></textarea>
						<p class='description'>
							<?php esc_html_e( 'A file that matches any pattern or path provided will not be optimized.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

	<?php
	$image_sizes        = ewww_image_optimizer_get_image_sizes();
	$disabled_sizes     = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes', false, true );
	$disabled_sizes_opt = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt', false, true );
	?>

		<div id='ewww-resize-settings'>
			<noscript><h2><?php esc_html_e( 'Resize', 'ewww-image-optimizer' ); ?></h2></noscript>
			<table class='form-table'>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_resize_detection'><?php esc_html_e( 'Resize Detection', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/41-resize-settings', '59849911042863033a1ba5f9' ); ?>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_resize_detection' name='ewww_image_optimizer_resize_detection' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_detection' ) ); ?> />
						<?php esc_html_e( 'Highlight images that need to be resized because the browser is scaling them down. Only visible for Admin users and adds a button to the admin bar to detect scaled images that have been lazy loaded.', 'ewww-image-optimizer' ); ?>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_resize_existing'><?php esc_html_e( 'Resize Existing Images', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/41-resize-settings', '59849911042863033a1ba5f9' ); ?>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_resize_existing' name='ewww_image_optimizer_resize_existing' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_existing' ) ); ?> />
						<?php esc_html_e( 'Allow resizing of existing Media Library images.', 'ewww-image-optimizer' ); ?>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_resize_other_existing'><?php esc_html_e( 'Resize Other Images', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/41-resize-settings', '59849911042863033a1ba5f9' ); ?>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_resize_other_existing' name='ewww_image_optimizer_resize_other_existing' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_other_existing' ) ); ?> />
						<?php esc_html_e( 'Allow resizing of existing images outside the Media Library. Use this to resize images specified under the Folders to Optimize setting when running Bulk or Scheduled Optimization.', 'ewww-image-optimizer' ); ?>
					</td>
				</tr>
	<?php if ( 'network-multisite' === $network ) : ?>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Disable Resizes', 'ewww-image-optimizer' ); ?>
					</th>
					<td>
						<p>
							<span style="color: #3eadc9"><?php esc_html_e( '*Settings to disable creation and optimization of individual sizes must be configured for each individual site.', 'ewww-image-optimizer' ); ?></span>
						</p>
					</td>
				</tr>
	<?php else : ?>
		<?php if ( 'network-singlesite' === $network ) : ?>
			<?php ob_end_clean(); ?>
		<div id='ewww-resize-settings'>
			<table class='form-table'>
			<?php echo ( empty( $exactdn_sub_folder ) ? wp_kses( $exactdn_settings_row, $allow_settings_html ) : '' ); ?>
		<?php endif; ?>
				<!-- RIGHT HERE is where we begin/clear buffer for network-singlesite (non-override version). -->
				<!-- Though the buffer will need to be started right the form begins. -->
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Disable Resizes', 'ewww-image-optimizer' ); ?>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/41-resize-settings', '59849911042863033a1ba5f9,58598744c697912ffd6c3eb4' ); ?>
					</th>
					<td>
						<p>
							<?php esc_html_e( 'WordPress, your theme, and other plugins generate various image sizes for each image uploaded.', 'ewww-image-optimizer' ); ?><br>
		<?php if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) : ?>
							<i><?php esc_html_e( 'Remember that each image size will affect your API credits.', 'ewww-image-optimizer' ); ?></i>
		<?php endif; ?>
						</p>
						<table id="ewww-settings-disable-resizes">
							<tr>
								<th scope="col">
									<?php esc_html_e( 'Disable Optimization', 'ewww-image-optimizer' ); ?>
								</th>
								<th scope="col">
									<?php esc_html_e( 'Disable Creation', 'ewww-image-optimizer' ); ?>
								</th>
							</tr>
		<?php
		foreach ( $image_sizes as $size => $dimensions ) :
			if ( empty( $dimensions['width'] ) && empty( $dimensions['height'] ) ) :
				continue;
			endif;
			?>
			<?php if ( 'thumbnail' === $size ) : ?>
							<tr>
								<td>
									<input type='checkbox' id='ewww_image_optimizer_disable_resizes_opt_<?php echo esc_attr( $size ); ?>' name='ewww_image_optimizer_disable_resizes_opt[<?php echo esc_attr( $size ); ?>]' value='true' <?php checked( ! empty( $disabled_sizes_opt[ $size ] ) ); ?> />
								</td>
								<td>
									<input type='checkbox' id='ewww_image_optimizer_disable_resizes_<?php echo esc_attr( $size ); ?>' name='ewww_image_optimizer_disable_resizes[<?php echo esc_attr( $size ); ?>]' value='true' disabled />
								</td>
								<td>
									<label for='ewww_image_optimizer_disable_resizes_<?php echo esc_attr( $size ); ?>'>
										<?php echo esc_html( $size ) . ' - ' . (int) $dimensions['width'] . ' x ' . (int) $dimensions['height'] . ( empty( $dimensions['crop'] ) ? '' : ' (' . esc_html__( 'cropped', 'ewww-image-optimizer' ) . ')' ); ?>
									</label>
								</td>
							</tr>
			<?php elseif ( 'pdf-full' === $size ) : ?>
							<tr>
								<td>
									<input type='checkbox' id='ewww_image_optimizer_disable_resizes_opt_<?php echo esc_attr( $size ); ?>' name='ewww_image_optimizer_disable_resizes_opt[<?php echo esc_attr( $size ); ?>]' value='true' <?php checked( ! empty( $disabled_sizes_opt[ $size ] ) ); ?> />
								</td>
								<td>
									<input type='checkbox' id='ewww_image_optimizer_disable_resizes_<?php echo esc_attr( $size ); ?>' name='ewww_image_optimizer_disable_resizes[<?php echo esc_attr( $size ); ?>]' value='true' <?php checked( ! empty( $disabled_sizes[ $size ] ) ); ?> />
								</td>
								<td>
									<label for='ewww_image_optimizer_disable_resizes_<?php echo esc_attr( $size ); ?>'>
										<?php echo esc_html( $size ); ?> - <span class='description'><?php esc_html_e( 'Disabling creation of the full-size preview for PDF files will disable all PDF preview sizes', 'ewww-image-optimizer' ); ?></span>
									</label>
								</td>
							</tr>
			<?php else : ?>
							<tr>
								<td>
									<input type='checkbox' id='ewww_image_optimizer_disable_resizes_opt_<?php echo esc_attr( $size ); ?>' name='ewww_image_optimizer_disable_resizes_opt[<?php echo esc_attr( $size ); ?>]' value='true' <?php checked( ! empty( $disabled_sizes_opt[ $size ] ) ); ?> />
								</td>
								<td>
									<input type='checkbox' id='ewww_image_optimizer_disable_resizes_<?php echo esc_attr( $size ); ?>' name='ewww_image_optimizer_disable_resizes[<?php echo esc_attr( $size ); ?>]' value='true' <?php checked( ! empty( $disabled_sizes[ $size ] ) ); ?> />
								</td>
								<td>
									<label for='ewww_image_optimizer_disable_resizes_<?php echo esc_attr( $size ); ?>'>
										<?php echo esc_html( $size ) . ' - ' . (int) $dimensions['width'] . ' x ' . (int) $dimensions['height'] . ( empty( $dimensions['crop'] ) ? '' : ' (' . esc_html__( 'cropped', 'ewww-image-optimizer' ) . ')' ); ?>
									</label>
								</td>
							</tr>
			<?php endif; ?>
		<?php endforeach; ?>
						</table>
					</td>
				</tr>
	<?php endif; ?>
			</table>
		</div>

	<?php if ( 'network-singlesite' === $network ) : ?>
		<p class='submit'><input type='submit' class='button-primary' value='<?php esc_attr_e( 'Save Changes', 'ewww-image-optimizer' ); ?>' /></p>
	</form>
</div><!-- end container .wrap -->
		<?php
		return;
	endif;
	/* translators: 1: JPG, GIF or PNG 2: JPG or PNG */
	$jpg2png = sprintf( __( '%1$s to %2$s Conversion', 'ewww-image-optimizer' ), 'JPG', 'PNG' );
	/* translators: 1: JPG, GIF or PNG 2: JPG or PNG */
	$png2jpg = sprintf( __( '%1$s to %2$s Conversion', 'ewww-image-optimizer' ), 'PNG', 'JPG' );
	/* translators: 1: JPG, GIF or PNG 2: JPG or PNG */
	$gif2png = sprintf( __( '%1$s to %2$s Conversion', 'ewww-image-optimizer' ), 'GIF', 'PNG' );
	?>

		<div id='ewww-conversion-settings'>
			<noscript><h2><?php esc_html_e( 'Convert', 'ewww-image-optimizer' ); ?></h2></noscript>
			<p>
				<?php esc_html_e( 'Conversion is only available for images in the Media Library (except WebP). By default, all images have a link available in the Media Library for one-time conversion. Turning on individual conversion operations below will enable conversion filters any time an image is uploaded or modified.', 'ewww-image-optimizer' ); ?><br />
				<strong><?php esc_html_e( 'NOTE:', 'ewww-image-optimizer' ); ?></strong> <?php esc_html_e( 'The plugin will attempt to update image locations for any posts that contain the images. You may still need to manually update locations/urls for converted images.', 'ewww-image-optimizer' ); ?>
			</p>
			<table class='form-table'>
	<?php if ( $toolkit_found ) : ?>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_disable_convert_links'><?php esc_html_e( 'Hide Conversion Links', 'ewww-image-optimizer' ); ?></label>
						<span><?php ewwwio_help_link( 'https://docs.ewww.io/article/14-converting-images', '58545a86c697912ffd6c1b53' ); ?></span>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_disable_convert_links' name='ewww_image_optimizer_disable_convert_links' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_convert_links' ) ); ?> />
						<span><?php esc_html_e( 'Site or Network admins can use this to prevent other users from using the conversion links in the Media Library which bypass the settings below.', 'ewww-image-optimizer' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_delete_originals'><?php esc_html_e( 'Delete Originals', 'ewww-image-optimizer' ); ?></label>
						<span><?php ewwwio_help_link( 'https://docs.ewww.io/article/14-converting-images', '58545a86c697912ffd6c1b53' ); ?></span>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_delete_originals' name='ewww_image_optimizer_delete_originals' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ); ?> />
						<span><?php esc_html_e( 'This will remove the original image from the server after a successful conversion.', 'ewww-image-optimizer' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_jpg_to_png'><?php echo esc_html( $jpg2png ); ?></label>
						<span><?php ewwwio_help_link( 'https://docs.ewww.io/article/14-converting-images', '58545a86c697912ffd6c1b53' ); ?></span>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_jpg_to_png' name='ewww_image_optimizer_jpg_to_png' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_to_png' ) ); ?> />
						<span><b><?php esc_html_e( 'WARNING:', 'ewww-image-optimizer' ); ?></b> <?php	esc_html_e( 'Removes metadata and increases cpu usage dramatically.', 'ewww-image-optimizer' ); ?></span>
						<p class='description'><?php esc_html_e( 'PNG is generally much better than JPG for logos and other images with a limited range of colors. Checking this option will slow down JPG processing significantly, and you may want to enable it only temporarily.', 'ewww-image-optimizer' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_png_to_jpg'><?php echo esc_html( $png2jpg ); ?></label>
						<span><?php ewwwio_help_link( 'https://docs.ewww.io/article/14-converting-images', '58545a86c697912ffd6c1b53,58543c69c697912ffd6c19a7,58542afac697912ffd6c18c0' ); ?></span>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_png_to_jpg' name='ewww_image_optimizer_png_to_jpg' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_to_jpg' ) ); ?> />
						<span><b><?php esc_html_e( 'WARNING:', 'ewww-image-optimizer' ); ?></b> <?php esc_html_e( 'This is not a lossless conversion.', 'ewww-image-optimizer' ); ?></span>
						<p class='description'><?php esc_html_e( 'JPG is generally much better than PNG for photographic use because it compresses the image and discards data. PNGs with transparency are not converted by default.', 'ewww-image-optimizer' ); ?></p>
						<label for='ewww_image_optimizer_jpg_background'><strong><?php esc_html_e( 'JPG Background Color:', 'ewww-image-optimizer' ); ?></strong></label>
						#<input type='text' id='ewww_image_optimizer_jpg_background' name='ewww_image_optimizer_jpg_background' size='6' value='<?php echo esc_attr( ewww_image_optimizer_jpg_background() ); ?>' />
						<span style='padding-left: 12px; font-size: 12px; border: solid 1px #555555; background-color: #<?php echo esc_attr( ewww_image_optimizer_jpg_background() ); ?>'>&nbsp;</span> <?php esc_html_e( 'HEX format (#123def)', 'ewww-image-optimizer' ); ?></span>
						<p class='description'><?php esc_html_e( 'Background color is used only if the PNG has transparency. Leave this value blank to skip PNGs with transparency.', 'ewww-image-optimizer' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_gif_to_png'><?php echo esc_html( $gif2png ); ?></label>
						<span><?php ewwwio_help_link( 'https://docs.ewww.io/article/14-converting-images', '58545a86c697912ffd6c1b53' ); ?></span>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_gif_to_png' name='ewww_image_optimizer_gif_to_png' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_to_png' ) ); ?> />
						<span><?php esc_html_e( 'No warnings here, just do it.', 'ewww-image-optimizer' ); ?></span>
						<p class='description'><?php esc_html_e( 'PNG is generally better than GIF, but animated images cannot be converted.', 'ewww-image-optimizer' ); ?></p>
					</td>
				</tr>
	<?php else : ?>
				<tr>
					<th>&nbsp;</th>
					<td>
						<p>
							<span style="color: #3eadc9"><?php esc_html_e( 'Image conversion requires one of the following PHP libraries: GD, Imagick, or GMagick.', 'ewww-image-optimizer' ); ?></span>
						</p>
					</td>
				</tr>
	<?php endif; ?>
			</table>
		</div>

		<div id='ewww-webp-settings'>
			<noscript><h2><?php esc_html_e( 'WebP', 'ewww-image-optimizer' ); ?></h2></noscript>
			<table class='form-table'>
			</table>
		</div>

		<div id='ewww-support-settings'>
			<noscript><h2><?php esc_html_e( 'Support', 'ewww-image-optimizer' ); ?></h2></noscript>
			<p>
				<a class='ewww-docs-root' href='https://docs.ewww.io/'><?php esc_html_e( 'Documentation', 'ewww-image-optimizer' ); ?></a> |
				<a class='ewww-docs-root' href='https://ewww.io/contact-us/'><?php esc_html_e( 'Contact Support', 'ewww-image-optimizer' ); ?></a> |
				<a href='https://feedback.ewww.io/b/features'><?php esc_html_e( 'Submit Feedback', 'ewww-image-optimizer' ); ?></a>
			</p>
			<p style='float:right;'>
				<a href='<?php echo esc_url( wp_nonce_url( admin_url( 'options-general.php?page=ewww-image-optimizer-options&uncomplete_wizard=1' ), 'ewww_image_optimizer_options-options' ) ); ?>'>
					<?php echo '.'; ?>
				</a>
			</p>
	<?php if ( ! empty( $frontend_functions ) ) : ?>
			<p>
				<strong><?php esc_html_e( 'Having problems with a broken site or wrong-sized images?', 'ewww-image-optimizer' ); ?></strong><br>
				<?php esc_html_e( 'Try disabling each of these options to identify the problem, or use the Panic Button to disable them all at once:', 'ewww-image-optimizer' ); ?><br>
				<?php
				foreach ( $frontend_functions as $frontend_function ) {
					echo '<i>' . esc_html( $frontend_function ) . '</i><br>';
				}
				?>
				<a id='ewww-rescue-mode' class='button-secondary' href='<?php echo esc_url( $rescue_mode_url ); ?>'>
					<?php esc_html_e( 'Panic Button', 'ewww-image-optimizer' ); ?>
				</a>
			</p>
	<?php endif; ?>
			<table class='form-table'>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_enable_help'><?php esc_html_e( 'Enable Embedded Help', 'ewww-image-optimizer' ); ?></label>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_enable_help' name='ewww_image_optimizer_enable_help' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_help' ) ); ?> />
						<span><?php esc_html_e( 'Enable the support beacon, which gives you access to documentation and our support team right from your WordPress dashboard. To assist you more efficiently, we may collect the current url, IP address, browser/device information, and debugging information.', 'ewww-image-optimizer' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_debug'><?php esc_html_e( 'Debugging', 'ewww-image-optimizer' ); ?></label>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_debug' name='ewww_image_optimizer_debug' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ); ?> />
						<span><?php esc_html_e( 'Use this to provide information for support purposes, or if you feel comfortable digging around in the code to fix a problem you are experiencing.', 'ewww-image-optimizer' ); ?></span>
					</td>
				</tr>
			</table>

	<?php if ( ! empty( $debug_info ) ) : ?>
			<p class="debug-actions">
				<strong><?php esc_html_e( 'Debugging Information', 'ewww-image-optimizer' ); ?>:</strong>
				<button id="ewww-copy-debug" class="button button-secondary" type="button"><?php esc_html_e( 'Copy', 'ewww-image-optimizer' ); ?></button>
		<?php if ( ewwwio_is_file( WP_CONTENT_DIR . '/ewww/debug.log' ) ) : ?>
				&emsp;<a href='<?php echo esc_url( admin_url( 'admin.php?action=ewww_image_optimizer_view_debug_log' ) ); ?>'><?php esc_html_e( 'View Debug Log', 'ewww-image-optimizer' ); ?></a> -
				<a href='<?php echo esc_url( admin_url( 'admin.php?action=ewww_image_optimizer_delete_debug_log' ) ); ?>'><?php esc_html_e( 'Remove Debug Log', 'ewww-image-optimizer' ); ?></a>
		<?php endif; ?>
			</p>
			<div id="ewww-debug-info" contenteditable="true">
				<?php echo wp_kses_post( $debug_info ); ?>
			</div>
	<?php endif; ?>
		</div>

		<div id='ewww-contribute-settings'>
			<noscript><h2><?php esc_html_e( 'Contribute', 'ewww-image-optimizer' ); ?></h2></noscript>
			<p>
				<strong><?php esc_html_e( 'Here are some ways you can contribute to the development of this plugin:', 'ewww-image-optimizer' ); ?></strong>
			</p>
			<p>
				<a href='https://translate.wordpress.org/projects/wp-plugins/ewww-image-optimizer/'><?php esc_html_e( 'Translate EWWW I.O.', 'ewww-image-optimizer' ); ?></a> |
				<a href='https://wordpress.org/support/plugin/ewww-image-optimizer/reviews/#new-post'><?php esc_html_e( 'Write a review', 'ewww-image-optimizer' ); ?></a> |
				<a href='https://ewww.io/plans/'><?php esc_html_e( 'Upgrade to premium image optimization', 'ewww-image-optimizer' ); ?></a>
			</p>
			<table class='form-table'>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_allow_tracking'><?php esc_html_e( 'Allow Usage Tracking?', 'ewww-image-optimizer' ); ?></label>
						<span><?php ewwwio_help_link( 'https://docs.ewww.io/article/23-usage-tracking', '591f3a8e2c7d3a057f893d91' ); ?></span>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_allow_tracking' name='ewww_image_optimizer_allow_tracking' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_allow_tracking' ) ); ?> />
						<?php esc_html_e( 'Allow EWWW Image Optimizer to anonymously track how this plugin is used and help us make the plugin better. Opt-in to tracking and receive a 10% discount on premium compression. No sensitive data is tracked.', 'ewww-image-optimizer' ); ?>
						<p>
							<?php
							if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_allow_tracking' ) ) {
								printf(
									/* translators: 1: link to https://ewww.io/plans/ 2: discount code (yes, you may use it) */
									esc_html__( 'Use this code at %1$s: %2$s', 'ewww-image-optimizer' ),
									'<a href="https://ewww.io/plans/" target="_blank">https://ewww.io/</a>',
									'<code>SPEEDER1012</code>'
								);
							}
							?>
						</p>
					</td>
				</tr>
			</table>
		</div>
		<p class='submit'><input type='submit' class='button-primary' value='<?php esc_attr_e( 'Save Changes', 'ewww-image-optimizer' ); ?>' /></p>
	</form>
</div><!-- end container .wrap -->
	<?php
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_help' ) ) {
		$current_user = wp_get_current_user();
		$help_email   = $current_user->user_email;
		$hs_debug     = '';
		if ( ! empty( $debug_info ) ) {
			$hs_debug = str_replace( array( "'", '<br>', '<b>', '</b>', '=>' ), array( "\'", '\n', '**', '**', '=' ), $debug_info );
		}
		?>
<script type="text/javascript">!function(e,t,n){function a(){var e=t.getElementsByTagName("script")[0],n=t.createElement("script");n.type="text/javascript",n.async=!0,n.src="https://beacon-v2.helpscout.net",e.parentNode.insertBefore(n,e)}if(e.Beacon=n=function(t,n,a){e.Beacon.readyQueue.push({method:t,options:n,data:a})},n.readyQueue=[],"complete"===t.readyState)return a();e.attachEvent?e.attachEvent("onload",a):e.addEventListener("load",a,!1)}(window,document,window.Beacon||function(){});</script>
<script type="text/javascript">
	window.Beacon('init', 'aa9c3d3b-d4bc-4e9b-b6cb-f11c9f69da87');
	Beacon( 'prefill', {
		email: '<?php echo esc_js( utf8_encode( $help_email ) ); ?>',
		text: '\n\n----------------------------------------\n<?php echo wp_kses_post( $hs_debug ); ?>',
	});
</script>
		<?php
	}
	ewwwio_memory( __FUNCTION__ );
	ewww_image_optimizer_temp_debug_clear();
}

/**
 * Gets the HTML for a help icon linked to the docs.
 *
 * @param string $link A link to the documentation.
 * @param string $hsid The HelpScout ID for the docs article. Optional.
 * @return string An HTML hyperlink element with a help icon.
 */
function ewwwio_get_help_link( $link, $hsid = '' ) {
	ob_start();
	ewwwio_help_link( $link, $hsid );
	return ob_get_clean();
}

/**
 * Displays a help icon linked to the docs.
 *
 * @param string $link A link to the documentation.
 * @param string $hsid The HelpScout ID for the docs article. Optional.
 */
function ewwwio_help_link( $link, $hsid = '' ) {
	$help_icon   = plugins_url( '/images/question-circle.png', __FILE__ );
	$beacon_attr = '';
	$link_class  = 'ewww-help-icon';
	if ( strpos( $hsid, ',' ) ) {
		$beacon_attr = 'data-beacon-articles';
		$link_class  = 'ewww-help-beacon-multi';
	} elseif ( $hsid ) {
		$beacon_attr = 'data-beacon-article';
		$link_class  = 'ewww-help-beacon-single';
	}
	if ( empty( $hsid ) ) {
		echo '<a class="ewww-help-external" href="' . esc_url( $link ) . '" target="_blank">' .
			'<img title="' . esc_attr__( 'Help', 'ewww-image-optimizer' ) . '" src="' . esc_url( $help_icon ) . '">' .
			'</a>';
		return;
	}
	echo '<a class="' . esc_attr( $link_class ) . '" href="' . esc_url( $link ) . '" target="_blank" ' . esc_attr( $beacon_attr ) . '="' . esc_attr( $hsid ) . '">' .
		'<img title="' . esc_attr__( 'Help', 'ewww-image-optimizer' ) . '" src="' . esc_url( $help_icon ) . '">' .
		'</a>';
}

/**
 * Checks to see if ExactDN or Easy IO is active.
 *
 * @return bool True if Easy IO is active in this plugin or the standalone Easy IO plugin. False if not.
 */
function ewww_image_optimizer_easy_active() {
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) || get_option( 'easyio_exactdn' ) ) {
		return true;
	}
	return false;
}


/**
 * Checks to see if Easy IO is active for multiple sites in a network install.
 *
 * @return int 1 if all tested sites are active, -1 if only partially active, 0 for none active.
 */
function ewww_image_optimizer_easyio_network_activated() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$active   = 0;
	$inactive = 0;
	$total    = 0;
	global $wpdb;
	$blogs = $wpdb->get_results( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs WHERE site_id = %d LIMIT 500", $wpdb->siteid ), ARRAY_A );
	if ( ewww_image_optimizer_iterable( $blogs ) ) {
		foreach ( $blogs as $blog ) {
			$total++;
			$blog_id = $blog['blog_id'];
			switch_to_blog( $blog_id );
			if ( get_option( 'ewww_image_optimizer_exactdn' ) && get_option( 'ewww_image_optimizer_exactdn_verified' ) ) {
				ewwwio_debug_message( "blog $blog_id active" );
				$active++;
			} else {
				ewwwio_debug_message( "blog $blog_id inactive" );
				$inactive++;
			}
			restore_current_blog();
		}
	}
	if ( $active > 0 && $active < $total ) {
		$active = -1;
	} elseif ( $active > 0 && $active === $total ) {
		$active = 1;
	}
	return $active;
}

/**
 * Removes the API key currently installed.
 *
 * @param boolean|string $redirect Should the plugin do a silent redirect back to the referring page? Default true.
 */
function ewww_image_optimizer_remove_cloud_key( $redirect = true ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', '' );
	if ( 'none' !== $redirect && false === current_user_can( $permissions ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_key', '' );
	$default_level = 10;
	if ( defined( 'EWWW_IO_CLOUD_PLUGIN' ) && EWWW_IO_CLOUD_PLUGIN ) {
		$default_level = 0;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 10 ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_level', $default_level );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) > 10 && 40 !== (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_level', $default_level );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) > 0 ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_gif_level', $default_level );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) > 0 ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_pdf_level', 0 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_svgcleaner' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_svg_level', 0 );
	}
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_exceeded', 0 );
	delete_transient( 'ewww_image_optimizer_cloud_status' );
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_backup_files', '' );
	if ( 'none' !== $redirect ) {
		wp_safe_redirect( wp_get_referer() );
		exit;
	}
}

/**
 * De-activates Easy IO.
 */
function ewww_image_optimizer_remove_easyio() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' );
	if ( false === current_user_can( $permissions ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	update_option( 'ewww_image_optimizer_exactdn', '' );
	delete_option( 'ewww_image_optimizer_exactdn_domain' );
	delete_option( 'ewww_image_optimizer_exactdn_plan_id' );
	delete_option( 'ewww_image_optimizer_exactdn_failures' );
	delete_option( 'ewww_image_optimizer_exactdn_checkin' );
	delete_option( 'ewww_image_optimizer_exactdn_verified' );
	delete_option( 'ewww_image_optimizer_exactdn_validation' );
	delete_option( 'ewww_image_optimizer_exactdn_suspended' );
	update_site_option( 'ewww_image_optimizer_exactdn', '' );
	delete_site_option( 'ewww_image_optimizer_exactdn_domain' );
	delete_site_option( 'ewww_image_optimizer_exactdn_plan_id' );
	delete_site_option( 'ewww_image_optimizer_exactdn_failures' );
	delete_site_option( 'ewww_image_optimizer_exactdn_checkin' );
	delete_site_option( 'ewww_image_optimizer_exactdn_verified' );
	delete_site_option( 'ewww_image_optimizer_exactdn_validation' );
	delete_site_option( 'ewww_image_optimizer_exactdn_suspended' );
	global $exactdn;
	if ( isset( $exactdn ) && is_object( $exactdn ) ) {
		$exactdn->cron_setup( false );
	}
	wp_safe_redirect( wp_get_referer() );
	exit;
}

/**
 * De-activates Easy IO on entire network install.
 */
function ewww_image_optimizer_network_remove_easyio() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_network_options' );
	if ( false === current_user_can( $permissions ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	if ( ! is_multisite() ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	global $wpdb;
	$blogs = $wpdb->get_results( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs WHERE site_id = %d", $wpdb->siteid ), ARRAY_A );
	if ( ewww_image_optimizer_iterable( $blogs ) ) {
		foreach ( $blogs as $blog ) {
			switch_to_blog( $blog['blog_id'] );
			update_option( 'ewww_image_optimizer_exactdn', '' );
			delete_option( 'ewww_image_optimizer_exactdn_domain' );
			delete_option( 'ewww_image_optimizer_exactdn_plan_id' );
			delete_option( 'ewww_image_optimizer_exactdn_failures' );
			delete_option( 'ewww_image_optimizer_exactdn_checkin' );
			delete_option( 'ewww_image_optimizer_exactdn_verified' );
			delete_option( 'ewww_image_optimizer_exactdn_validation' );
			delete_option( 'ewww_image_optimizer_exactdn_suspended' );
			wp_clear_scheduled_hook( 'easyio_verification_checkin' );
			restore_current_blog();
		}
	}

	update_site_option( 'ewww_image_optimizer_exactdn', '' );
	delete_site_option( 'ewww_image_optimizer_exactdn_domain' );
	delete_site_option( 'ewww_image_optimizer_exactdn_plan_id' );
	delete_site_option( 'ewww_image_optimizer_exactdn_failures' );
	delete_site_option( 'ewww_image_optimizer_exactdn_checkin' );
	delete_site_option( 'ewww_image_optimizer_exactdn_verified' );
	delete_site_option( 'ewww_image_optimizer_exactdn_validation' );
	delete_site_option( 'ewww_image_optimizer_exactdn_suspended' );

	wp_safe_redirect( wp_get_referer() );
	exit;
}

/**
 * Enables Forced WebP for GIF images once the site is ready.
 */
function ewww_image_optimizer_enable_force_gif2webp() {
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_force_gif2webp', true );
	wp_safe_redirect( wp_get_referer() );
	exit;
}

/**
 * Loads script to detect scaled images within the page, only enabled for admins.
 */
function ewww_image_optimizer_resize_detection_script() {
	if (
		! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ||
		( ! empty( $_SERVER['SCRIPT_NAME'] ) && 'wp-login.php' === basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) )
	) {
		return;
	}
	if ( ewww_image_optimizer_is_amp() ) {
		return;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_detection' ) ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$resize_detection_script = file_get_contents( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'includes/resize-detection.js' );
		} else {
			$resize_detection_script = file_get_contents( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'includes/resize-detection.min.js' );
		}
		?>
		<style>
			#wp-admin-bar-resize-detection div.ab-empty-item {
				cursor: pointer;
			}
			#wp-admin-bar-resize-detection {
				opacity: 1;
				-webkit-transition: opacity 0.3s ease-in-out;
				-moz-transition: opacity 0.3s ease-in-out;
				-ms-transition: opacity 0.3s ease-in-out;
				-o-transition: opacity 0.3s ease-in-out;
				transition: opacity 0.3 ease-in-out;
			}
			#wp-admin-bar-resize-detection.ewww-fade {
				opacity: 0;
			}
			img.scaled-image {
				border: 3px #3eadc9 dotted;
				margin: -3px;
			}
		</style>
		<script><?php echo $resize_detection_script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
		<?php
	}
}

/**
 * Makes sure the resize detection script is excluded from Autoptimize functions.
 *
 * @param string $jsexcludes A list of exclusions from Autoptimize.
 * @param string $content The page content being parsed by Autoptimize.
 * @return string The JS excludes plus one more.
 */
function ewww_image_optimizer_autoptimize_js_exclude( $jsexcludes = '', $content = '' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( is_array( $jsexcludes ) ) {
		$jsexcludes['includes/resize_detection.js'] = '';
		return $jsexcludes;
	}
	return $jsexcludes . ', includes/resize_detection.js';
}

/**
 * Checks to see if the current page being output is an AMP page.
 *
 * @return bool True for an AMP endpoint, false otherwise.
 */
function ewww_image_optimizer_is_amp() {
	if ( ! did_action( 'parse_query' ) ) {
		return false;
	}
	if ( function_exists( 'amp_is_request' ) && amp_is_request() ) {
		return true;
	}
	if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
		return true;
	}
	if ( function_exists( 'ampforwp_is_amp_endpoint' ) && ampforwp_is_amp_endpoint() ) {
		return true;
	}
	return false;
}

/**
 * Checks if admin bar is visible, and then adds the admin_bar_menu action.
 */
function ewww_image_optimizer_admin_bar_init() {
	if (
		! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ||
		! is_admin_bar_showing() ||
		( ! empty( $_SERVER['SCRIPT_NAME'] ) && 'wp-login.php' === basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) ) ||
		is_admin()
	) {
		return;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_detection' ) ) {
		add_action( 'admin_bar_menu', 'ewww_image_optimizer_admin_bar_menu', 99 );
	}
}

/**
 * Adds a resize detection button to the wp admin bar.
 *
 * @param object $wp_admin_bar The WP Admin Bar object, passed by reference.
 */
function ewww_image_optimizer_admin_bar_menu( $wp_admin_bar ) {
	$wp_admin_bar->add_menu(
		array(
			'id'     => 'resize-detection',
			'parent' => 'top-secondary',
			'title'  => __( 'Detect Scaled Images', 'ewww-image-optimizer' ),
		)
	);
}

/**
 * Adds information to the in-memory debug log.
 *
 * @global string $eio_debug The in-memory debug log.
 *
 * @param string $message Debug information to add to the log.
 */
function ewwwio_debug_message( $message ) {
	if ( ! is_string( $message ) && ! is_int( $message ) && ! is_float( $message ) ) {
		return;
	}
	$message = "$message";
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::debug( $message );
		return;
	}
	global $ewwwio_temp_debug;
	if ( $ewwwio_temp_debug || ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
		$memory_limit = ewwwio_memory_limit();
		if ( strlen( $message ) + 4000000 + memory_get_usage( true ) <= $memory_limit ) {
			global $eio_debug;
			$message    = str_replace( "\n\n\n", '<br>', $message );
			$message    = str_replace( "\n\n", '<br>', $message );
			$message    = str_replace( "\n", '<br>', $message );
			$eio_debug .= "$message<br>";
		} else {
			global $eio_debug;
			$eio_debug = "not logging message, memory limit is $memory_limit";
		}
	}
}

/**
 * Saves the in-memory debug log to a logfile in the plugin folder.
 *
 * @global string $eio_debug The in-memory debug log.
 */
function ewww_image_optimizer_debug_log() {
	global $eio_debug;
	global $ewwwio_temp_debug;
	$debug_log = WP_CONTENT_DIR . '/ewww/debug.log';
	if ( ! is_dir( dirname( $debug_log ) ) && is_writable( WP_CONTENT_DIR ) ) {
		wp_mkdir_p( dirname( $debug_log ) );
	}
	if (
		! empty( $eio_debug ) &&
		empty( $ewwwio_temp_debug ) &&
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) &&
		is_dir( dirname( $debug_log ) ) &&
		is_writable( dirname( $debug_log ) )
	) {
		$memory_limit = ewwwio_memory_limit();
		clearstatcache();
		$timestamp = gmdate( 'Y-m-d H:i:s' ) . "\n";
		if ( ! file_exists( $debug_log ) ) {
			touch( $debug_log );
		} else {
			if ( filesize( $debug_log ) + 4000000 + memory_get_usage( true ) > $memory_limit ) {
				unlink( $debug_log );
				touch( $debug_log );
			}
		}
		if ( filesize( $debug_log ) + strlen( $eio_debug ) + 4000000 + memory_get_usage( true ) <= $memory_limit && is_writable( $debug_log ) ) {
			$eio_debug = str_replace( '<br>', "\n", $eio_debug );
			file_put_contents( $debug_log, $timestamp . $eio_debug, FILE_APPEND );
		}
		$eio_debug = '';
	} elseif ( ! is_dir( dirname( $debug_log ) ) || ! is_writable( dirname( $debug_log ) ) ) {
		$eio_debug = '';
	}
	ewwwio_memory( __FUNCTION__ );
}

/**
 * View the debug.log file from the wp-admin.
 */
function ewww_image_optimizer_view_debug_log() {
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	if ( ewwwio_is_file( WP_CONTENT_DIR . '/ewww/debug.log' ) ) {
		ewwwio_ob_clean();
		header( 'Content-Type: text/plain;charset=UTF-8' );
		readfile( WP_CONTENT_DIR . '/ewww/debug.log' );
		exit;
	}
	wp_die( esc_html__( 'The Debug Log is empty.', 'ewww-image-optimizer' ) );
}

/**
 * Removes the debug.log file from the plugin folder.
 */
function ewww_image_optimizer_delete_debug_log() {
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	if ( ewwwio_is_file( WP_CONTENT_DIR . '/ewww/debug.log' ) ) {
		unlink( WP_CONTENT_DIR . '/ewww/debug.log' );
	}
	$sendback = wp_get_referer();
	wp_safe_redirect( $sendback );
	exit;
}

/**
 * Adds version information to the in-memory debug log.
 *
 * @global string $eio_debug The in-memory debug log.
 * @global int $wp_version
 */
function ewwwio_debug_version_info() {
	global $eio_debug;
	if ( ! extension_loaded( 'suhosin' ) && function_exists( 'get_current_user' ) ) {
		$eio_debug .= get_current_user() . '<br>';
	}

	$eio_debug .= 'EWWW IO version: ' . EWWW_IMAGE_OPTIMIZER_VERSION . '<br>';

	// Check the WP version.
	global $wp_version;
	$eio_debug .= "WP version: $wp_version<br>";

	if ( defined( 'PHP_VERSION_ID' ) ) {
		$eio_debug .= 'PHP version: ' . PHP_VERSION_ID . '<br>';
	}
	if ( defined( 'LIBXML_VERSION' ) ) {
		$eio_debug .= 'libxml version: ' . LIBXML_VERSION . '<br>';
	}
	if ( ! empty( $_ENV['PANTHEON_ENVIRONMENT'] ) && in_array( $_ENV['PANTHEON_ENVIRONMENT'], array( 'test', 'live', 'dev' ), true ) ) {
		$eio_debug .= "detected pantheon env: {$_ENV['PANTHEON_ENVIRONMENT']}<br>";
	}
	if ( defined( 'EWWW_IO_CLOUD_PLUGIN' ) && EWWW_IO_CLOUD_PLUGIN ) {
		$eio_debug .= 'cloud plugin<br>';
	} else {
		$eio_debug .= 'core plugin<br>';
	}
}

/**
 * Generate and cleanup a PHP backtrace.
 *
 * @return string A serialized backtrace, suitable for database storage.
 */
function ewwwio_debug_backtrace() {
	if ( defined( 'DEBUG_BACKTRACE_IGNORE_ARGS' ) ) {
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
	} else {
		$backtrace = debug_backtrace( false );
	}
	array_shift( $backtrace );
	array_shift( $backtrace );
	return maybe_serialize( $backtrace );
}

/**
 * Make sure to clear temp debug option on shutdown.
 */
function ewww_image_optimizer_temp_debug_clear() {
	global $ewwwio_temp_debug;
	global $eio_debug;
	if ( $ewwwio_temp_debug ) {
		$eio_debug = '';
	}
	$ewwwio_temp_debug = false;
}

/**
 * Finds the current PHP memory limit or a reasonable default.
 *
 * @return int The memory limit in bytes.
 */
function ewwwio_memory_limit() {
	if ( defined( 'EWWW_MEMORY_LIMIT' ) && EWWW_MEMORY_LIMIT ) {
		$memory_limit = EWWW_MEMORY_LIMIT;
	} elseif ( function_exists( 'ini_get' ) ) {
		$memory_limit = ini_get( 'memory_limit' );
	} else {
		if ( ! defined( 'EWWW_MEMORY_LIMIT' ) ) {
			// Conservative default, current usage + 16M.
			$current_memory = memory_get_usage( true );
			$memory_limit   = round( $current_memory / ( 1024 * 1024 ) ) + 16;
			define( 'EWWW_MEMORY_LIMIT', $memory_limit );
		}
	}
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::debug( "memory limit is set at $memory_limit" );
	}
	if ( ! $memory_limit || -1 === intval( $memory_limit ) ) {
		// Unlimited, set to 32GB.
		$memory_limit = '32000M';
	}
	if ( stripos( $memory_limit, 'g' ) ) {
		$memory_limit = intval( $memory_limit ) * 1024 * 1024 * 1024;
	} else {
		$memory_limit = intval( $memory_limit ) * 1024 * 1024;
	}
	return $memory_limit;
}

/**
 * Checks if there is enough memory still available.
 *
 * Looks to see if the current usage + padding will fit within the memory_limit defined by PHP.
 *
 * @param int $padding Optional. The amount of memory needed to continue. Default 1050000.
 * @return True to proceed, false if there is not enough memory.
 */
function ewwwio_check_memory_available( $padding = 1050000 ) {
	$memory_limit = ewwwio_memory_limit();

	$current_memory = memory_get_usage( true ) + $padding;
	if ( $current_memory >= $memory_limit ) {
		ewwwio_debug_message( "detected memory limit is not enough: $memory_limit" );
		return false;
	}
	ewwwio_debug_message( "detected memory limit is: $memory_limit" );
	return true;
}

/**
 * Implode a multi-dimensional array without throwing errors. Arguments can be reverse order, same as implode().
 *
 * @param string $delimiter The character to put between the array items (the glue).
 * @param array  $data The array to output with the glue.
 * @return string The array values, separated by the delimiter.
 */
function ewwwio_implode( $delimiter, $data = '' ) {
	if ( is_array( $delimiter ) ) {
		$temp_data = $delimiter;
		$delimiter = $data;
		$data      = $temp_data;
	}
	if ( is_array( $delimiter ) ) {
		return '';
	}
	$output = '';
	foreach ( $data as $value ) {
		if ( is_string( $value ) || is_numeric( $value ) ) {
			$output .= $value . $delimiter;
		} elseif ( is_bool( $value ) ) {
			$output .= ( $value ? 'true' : 'false' ) . $delimiter;
		} elseif ( is_array( $value ) ) {
			$output .= 'Array,';
		}
	}
	return rtrim( $output, ',' );
}


/**
 * Logs memory usage stats. Disabled normally.
 *
 * @global string $ewww_memory An buffer of memory stat messages.
 *
 * @param string $function The name of the function or descriptive label.
 */
function ewwwio_memory( $function ) {
	return;
	if ( WP_DEBUG ) {
		global $ewww_memory;
		$ewww_memory .= $function . ': ' . memory_get_usage( true ) . "\n";
		ewwwio_memory_output();
	}
}

/**
 * Saves the memory stats to a log file.
 *
 * @global string $ewww_memory An buffer of memory stat messages.
 */
function ewwwio_memory_output() {
	if ( WP_DEBUG ) {
		global $ewww_memory;
		$timestamp = gmdate( 'y-m-d h:i:s.u' ) . '  ';
		if ( ! file_exists( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'memory.log' ) ) {
			touch( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'memory.log' );
		}
		file_put_contents( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'memory.log', $timestamp . $ewww_memory, FILE_APPEND );
		$ewww_memory = '';
	}
}

/**
 * Dumps data from any filter.
 *
 * @param mixed $var Could be anything, really.
 * @param mixed $var2 Default false. Could be anything, really.
 * @param mixed $var3 Default false. Could be anything, really.
 * @return mixed Whatever they gave us.
 */
function ewwwio_dump_var( $var, $var2 = false, $var3 = false ) {
	if ( ! ewww_image_optimizer_function_exists( 'print_r' ) ) {
		return $var;
	}
	ewwwio_debug_message( 'dumping var' );
	ewwwio_debug_message( print_r( $var, true ) );
	if ( $var2 ) {
		ewwwio_debug_message( 'dumping var2' );
		ewwwio_debug_message( print_r( $var2, true ) );
	}
	if ( $var3 ) {
		ewwwio_debug_message( 'dumping var3' );
		ewwwio_debug_message( print_r( $var3, true ) );
	}
	return $var;
}

/**
 * Dummy function to preserve old strings.
 */
function ewwwio_preserve_strings() {
	$string1 = __( 'Lossless Compression', 'ewww-image-optimizer' );
	$string2 = __( 'Lossy Compression', 'ewww-image-optimizer' );
	$string3 = __( 'Maximum Lossless Compression', 'ewww-image-optimizer' );
	$string4 = __( 'Maximum Lossy Compression', 'ewww-image-optimizer' );
}
?>
