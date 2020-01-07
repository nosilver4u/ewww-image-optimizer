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

// TODO: might be able to use the Custom Bulk Actions in WP 4.7 to support the bulk optimize drop-down menu.
// TODO: need to make the scheduler so it can resume without having to re-run the queue population, and then we can probably also flush the queue when scheduled opt starts, but later it would be nice to implement the bulk_loop as the aux_loop so that it could handle media properly.
// TODO: Add a custom async function for parallel mode to store image as pending and use the row ID instead of relative path.
// TODO: write some tests for AGR.
// TODO: use this: https://codex.wordpress.org/AJAX_in_Plugins#The_post-load_JavaScript_Event .
// TODO: can some of the bulk "fallbacks" be implemented for async processing?
// TODO: check to see if we can use PHP and WP core is_countable functions.
// TODO: make sure all settings (like lazy load) are in usage reporting.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EWWW_IMAGE_OPTIMIZER_VERSION', '514.0' );

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
require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'vendor/pel/autoload.php' );
use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelTag;

/*
 * Hooks
 */

// If automatic optimization is NOT disabled.
if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_noauto' ) ) {
	if ( class_exists( 'S3_Uploads' ) ) {
		// Resizes and auto-rotates images.
		add_filter( 'wp_handle_upload_prefilter', 'ewww_image_optimizer_handle_upload', 8 );
	} else {
		// Resizes and auto-rotates images.
		add_filter( 'wp_handle_upload', 'ewww_image_optimizer_handle_upload' );
	}
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
// Filters the settings page output when cloud settings are enabled.
add_filter( 'ewww_image_optimizer_settings', 'ewww_image_optimizer_filter_settings_page' );
// Processes screenshots imported with MyArcadePlugin.
add_filter( 'myarcade_filter_screenshot', 'ewww_image_optimizer_myarcade_thumbnail' );
// Processes thumbnails created by MyArcadePlugin.
add_filter( 'myarcade_filter_thumbnail', 'ewww_image_optimizer_myarcade_thumbnail' );
// This filter turns off ewwwio_image_editor during save from the actual image editor and ensures that we parse the resizes list during the image editor save function.
add_filter( 'load_image_to_edit_path', 'ewww_image_optimizer_editor_save_pre' );
// Allows the user to override the default JPG quality used by WordPress.
add_filter( 'jpeg_quality', 'ewww_image_optimizer_set_jpg_quality' );
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
// Loads the plugin translations.
add_action( 'plugins_loaded', 'ewww_image_optimizer_preinit' );
// Runs any checks that need to run everywhere and early.
add_action( 'init', 'ewww_image_optimizer_init', 9 );
// Load our front-end parsers for ExactDN and/or Alt WebP.
add_action( 'init', 'ewww_image_optimizer_parser_init', 99 );
// Initializes the plugin for admin interactions, like saving network settings and scheduling cron jobs.
add_action( 'admin_init', 'ewww_image_optimizer_admin_init' );
// Check the current screen ID to see if temp debugging should still be enabled.
add_action( 'current_screen', 'ewww_image_optimizer_current_screen', 10, 1 );
// Get admin color scheme and save it for later.
add_action( 'admin_head', 'ewww_image_optimizer_save_admin_colors' );
// Legacy (non-AJAX) action hook for manually optimizing an image.
add_action( 'admin_action_ewww_image_optimizer_manual_optimize', 'ewww_image_optimizer_manual' );
// Legacy (non-AJAX) action hook for manually restoring a converted image.
add_action( 'admin_action_ewww_image_optimizer_manual_restore', 'ewww_image_optimizer_manual' );
// Legacy (non-AJAX) action hook for manually restoring a backup from the API.
add_action( 'admin_action_ewww_image_optimizer_manual_cloud_restore', 'ewww_image_optimizer_manual' );
// Legacy (non-AJAX) action hook for manually attempting conversion on an image.
add_action( 'admin_action_ewww_image_optimizer_manual_convert', 'ewww_image_optimizer_manual' );
// Cleanup routine when an attachment is deleted.
add_action( 'delete_attachment', 'ewww_image_optimizer_delete' );
// Cleanup db records when Enable Media Replace replaces a file.
add_action( 'wp_handle_replace', 'ewww_image_optimizer_media_replace' );
// Cleanup db records when Image Regenerate & Select Crop deletes a file.
add_action( 'sirsc_image_file_deleted', 'ewww_image_optimizer_file_deleted', 10, 2 );
// Adds the EWWW IO pages to the admin menu.
add_action( 'admin_menu', 'ewww_image_optimizer_admin_menu', 60 );
// Adds the EWWW IO settings to the network admin menu.
add_action( 'network_admin_menu', 'ewww_image_optimizer_network_admin_menu' );
// Adds the hook to modify the media library bulk actions via admin_print_footer_scripts.
add_action( 'load-upload.php', 'ewww_image_optimizer_load_admin_js' );
// Non-AJAX handler to reroute selected IDs to the bulk optimizer.
add_action( 'admin_action_bulk_optimize', 'ewww_image_optimizer_bulk_action_handler' );
// Ditto, but handles the actions from the bottom of the media page.
add_action( 'admin_action_-1', 'ewww_image_optimizer_bulk_action_handler' );
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
// Non-AJAX handler to retest async/background mode.
add_action( 'admin_action_ewww_image_optimizer_retest_background_optimization', 'ewww_image_optimizer_retest_background_optimization' );
// Non-AJAX handler to view the debug log, and display it.
add_action( 'admin_action_ewww_image_optimizer_view_debug_log', 'ewww_image_optimizer_view_debug_log' );
// Non-AJAX handler to delete the debug log, and reroute back to the settings page.
add_action( 'admin_action_ewww_image_optimizer_delete_debug_log', 'ewww_image_optimizer_delete_debug_log' );
// Check if WebP option was turned off and is now enabled.
add_filter( 'pre_update_option_ewww_image_optimizer_webp', 'ewww_image_optimizer_webp_maybe_enabled', 10, 2 );
// Check if JS WebP option has just been enabled and see if Force WebP is needed for WP Offload Media.
add_filter( 'pre_update_option_ewww_image_optimizer_webp_for_cdn', 'ewww_image_optimizer_webp_cdn_check_force', 10, 2 );
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

/**
 * Setup page parsing classes after theme functions.php is loaded and plugins have run init routines.
 */
function ewww_image_optimizer_parser_init() {
	$buffer_start = false;
	// If ExactDN is enabled.
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) && empty( $_GET['exactdn_disable'] ) ) {
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
	}
	// If Alt WebP Rewriting is enabled.
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		$buffer_start = true;
		/**
		 * Page Parsing class for working with HTML content.
		 */
		require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-eio-page-parser.php' );
		/**
		 * Alt WebP class for parsing image urls and rewriting them for WebP support.
		 */
		require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-eio-alt-webp.php' );
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
 * Checks to see if the WebP option from the Cache Enabler plugin is enabled.
 *
 * @return bool True if the WebP option for CE is enabled.
 */
function ewww_image_optimizer_ce_webp_enabled() {
	if ( class_exists( 'Cache_Enabler' ) ) {
		$ce_options = Cache_Enabler::$options;
		if ( $ce_options['webp'] ) {
			ewwwio_debug_message( 'Cache Enabler webp option enabled' );
			return true;
		}
	}
	return false;
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

if ( ! function_exists( 'boolval' ) ) {
	/**
	 * Cast a value to boolean.
	 *
	 * @param mixed $value Any value that can be cast to boolean.
	 * @return bool The boolean version of the provided value.
	 */
	function boolval( $value ) {
		return (bool) $value;
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

	$active_plugins = get_option( 'active_plugins' );
	if ( is_multisite() && is_array( $active_plugins ) ) {
		$sitewide_plugins = get_site_option( 'active_sitewide_plugins' );
		if ( is_array( $sitewide_plugins ) ) {
			$active_plugins = array_merge( $active_plugins, array_flip( $sitewide_plugins ) );
		}
	}
	if ( ewww_image_optimizer_iterable( $active_plugins ) ) {
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
	if ( defined( 'DOING_WPLR_REQUEST' ) && DOING_WPLR_REQUEST && ! defined( 'EWWWIO_WPLR_AUTO' ) ) {
		// Unhook all automatic processing, and save an option that (does not autoload) tells the user LR Sync regenerated their images and they should run the bulk optimizer.
		remove_filter( 'wp_image_editors', 'ewww_image_optimizer_load_editor', 60 );
		remove_filter( 'wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15 );
		add_filter( 'wp_generate_attachment_metadata', 'ewww_image_optimizer_lr_sync_update' );
	}
}

/**
 * Plugin upgrade function
 *
 * @global object $wpdb
 */
function ewww_image_optimizer_upgrade() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_memory( __FUNCTION__ );
	if ( get_option( 'ewww_image_optimizer_version' ) < EWWW_IMAGE_OPTIMIZER_VERSION ) {
		if ( wp_doing_ajax() ) {
			return;
		}
		ewww_image_optimizer_enable_background_optimization();
		ewww_image_optimizer_install_table();
		ewww_image_optimizer_set_defaults();
		// This will get re-enabled if things are too slow.
		ewww_image_optimizer_set_option( 'exactdn_prevent_db_queries', false );
		delete_option( 'ewww_image_optimizer_exactdn_verify_method' );
		delete_site_option( 'ewww_image_optimizer_exactdn_verify_method' );
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
		ewww_image_optimizer_debug_log();
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
	$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' );
	if ( false === current_user_can( $permissions ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	ewww_image_optimizer_enable_background_optimization();
	sleep( 10 );
	$sendback = wp_get_referer();
	wp_redirect( $sendback );
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
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		ewwwio_debug_message( 'saving network settings' );
		// Set the common network settings if they have been POSTed.
		if ( isset( $_POST['option_page'] ) && false !== strpos( $_POST['option_page'], 'ewww_image_optimizer_options' ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww_image_optimizer_options-options' ) && current_user_can( 'manage_network_options' ) && ! get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) && false === strpos( $_POST['_wp_http_referer'], 'options-general' ) ) {
			ewwwio_debug_message( 'network-wide settings, no override' );
			$_POST['ewww_image_optimizer_cloud_key'] = empty( $_POST['ewww_image_optimizer_cloud_key'] ) ? '' : $_POST['ewww_image_optimizer_cloud_key'];
			update_site_option( 'ewww_image_optimizer_cloud_key', ewww_image_optimizer_cloud_key_sanitize( $_POST['ewww_image_optimizer_cloud_key'] ) );
			$_POST['ewww_image_optimizer_debug'] = ( empty( $_POST['ewww_image_optimizer_debug'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_debug', $_POST['ewww_image_optimizer_debug'] );
			$_POST['ewww_image_optimizer_metadata_remove'] = ( empty( $_POST['ewww_image_optimizer_metadata_remove'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_metadata_remove', $_POST['ewww_image_optimizer_metadata_remove'] );
			$_POST['ewww_image_optimizer_jpg_level'] = empty( $_POST['ewww_image_optimizer_jpg_level'] ) ? '' : $_POST['ewww_image_optimizer_jpg_level'];
			update_site_option( 'ewww_image_optimizer_jpg_level', (int) $_POST['ewww_image_optimizer_jpg_level'] );
			$_POST['ewww_image_optimizer_png_level'] = empty( $_POST['ewww_image_optimizer_png_level'] ) ? '' : $_POST['ewww_image_optimizer_png_level'];
			update_site_option( 'ewww_image_optimizer_png_level', (int) $_POST['ewww_image_optimizer_png_level'] );
			$_POST['ewww_image_optimizer_gif_level'] = empty( $_POST['ewww_image_optimizer_gif_level'] ) ? '' : $_POST['ewww_image_optimizer_gif_level'];
			update_site_option( 'ewww_image_optimizer_gif_level', (int) $_POST['ewww_image_optimizer_gif_level'] );
			$_POST['ewww_image_optimizer_pdf_level'] = empty( $_POST['ewww_image_optimizer_pdf_level'] ) ? '' : $_POST['ewww_image_optimizer_pdf_level'];
			update_site_option( 'ewww_image_optimizer_pdf_level', (int) $_POST['ewww_image_optimizer_pdf_level'] );
			$_POST['ewww_image_optimizer_delete_originals'] = ( empty( $_POST['ewww_image_optimizer_delete_originals'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_delete_originals', $_POST['ewww_image_optimizer_delete_originals'] );
			$_POST['ewww_image_optimizer_jpg_to_png'] = ( empty( $_POST['ewww_image_optimizer_jpg_to_png'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_jpg_to_png', $_POST['ewww_image_optimizer_jpg_to_png'] );
			$_POST['ewww_image_optimizer_png_to_jpg'] = ( empty( $_POST['ewww_image_optimizer_png_to_jpg'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_png_to_jpg', $_POST['ewww_image_optimizer_png_to_jpg'] );
			$_POST['ewww_image_optimizer_gif_to_png'] = ( empty( $_POST['ewww_image_optimizer_gif_to_png'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_gif_to_png', $_POST['ewww_image_optimizer_gif_to_png'] );
			$_POST['ewww_image_optimizer_webp'] = ( empty( $_POST['ewww_image_optimizer_webp'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_webp', $_POST['ewww_image_optimizer_webp'] );
			$_POST['ewww_image_optimizer_jpg_background'] = empty( $_POST['ewww_image_optimizer_jpg_background'] ) ? '' : $_POST['ewww_image_optimizer_jpg_background'];
			update_site_option( 'ewww_image_optimizer_jpg_background', ewww_image_optimizer_jpg_background( $_POST['ewww_image_optimizer_jpg_background'] ) );
			$_POST['ewww_image_optimizer_jpg_quality'] = empty( $_POST['ewww_image_optimizer_jpg_quality'] ) ? '' : $_POST['ewww_image_optimizer_jpg_quality'];
			update_site_option( 'ewww_image_optimizer_jpg_quality', ewww_image_optimizer_jpg_quality( $_POST['ewww_image_optimizer_jpg_quality'] ) );
			$_POST['ewww_image_optimizer_disable_convert_links'] = ( empty( $_POST['ewww_image_optimizer_disable_convert_links'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_disable_convert_links', $_POST['ewww_image_optimizer_disable_convert_links'] );
			$_POST['ewww_image_optimizer_backup_files'] = ( empty( $_POST['ewww_image_optimizer_backup_files'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_backup_files', $_POST['ewww_image_optimizer_backup_files'] );
			$_POST['ewww_image_optimizer_auto'] = ( empty( $_POST['ewww_image_optimizer_auto'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_auto', $_POST['ewww_image_optimizer_auto'] );
			$_POST['ewww_image_optimizer_aux_paths'] = empty( $_POST['ewww_image_optimizer_aux_paths'] ) ? '' : $_POST['ewww_image_optimizer_aux_paths'];
			update_site_option( 'ewww_image_optimizer_aux_paths', ewww_image_optimizer_aux_paths_sanitize( $_POST['ewww_image_optimizer_aux_paths'] ) );
			$_POST['ewww_image_optimizer_exclude_paths'] = empty( $_POST['ewww_image_optimizer_exclude_paths'] ) ? '' : $_POST['ewww_image_optimizer_exclude_paths'];
			update_site_option( 'ewww_image_optimizer_exclude_paths', ewww_image_optimizer_exclude_paths_sanitize( $_POST['ewww_image_optimizer_exclude_paths'] ) );
			$_POST['ewww_image_optimizer_enable_cloudinary'] = ( empty( $_POST['ewww_image_optimizer_enable_cloudinary'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_enable_cloudinary', $_POST['ewww_image_optimizer_enable_cloudinary'] );
			$_POST['ewww_image_optimizer_exactdn'] = ( empty( $_POST['ewww_image_optimizer_exactdn'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_exactdn', $_POST['ewww_image_optimizer_exactdn'] );
			$_POST['exactdn_all_the_things'] = ( empty( $_POST['exactdn_all_the_things'] ) ? false : true );
			update_site_option( 'exactdn_all_the_things', $_POST['exactdn_all_the_things'] );
			$_POST['exactdn_lossy'] = ( empty( $_POST['exactdn_lossy'] ) ? false : true );
			update_site_option( 'exactdn_lossy', $_POST['exactdn_lossy'] );
			$_POST['ewww_image_optimizer_lazy_load'] = ( empty( $_POST['ewww_image_optimizer_lazy_load'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_lazy_load', $_POST['ewww_image_optimizer_lazy_load'] );
			$_POST['ewww_image_optimizer_use_lqip'] = ( empty( $_POST['ewww_image_optimizer_use_lqip'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_use_lqip', $_POST['ewww_image_optimizer_use_lqip'] );
			$_POST['ewww_image_optimizer_maxmediawidth'] = empty( $_POST['ewww_image_optimizer_maxmediawidth'] ) ? 0 : $_POST['ewww_image_optimizer_maxmediawidth'];
			update_site_option( 'ewww_image_optimizer_maxmediawidth', (int) $_POST['ewww_image_optimizer_maxmediawidth'] );
			$_POST['ewww_image_optimizer_maxmediaheight'] = empty( $_POST['ewww_image_optimizer_maxmediaheight'] ) ? 0 : $_POST['ewww_image_optimizer_maxmediaheight'];
			update_site_option( 'ewww_image_optimizer_maxmediaheight', (int) $_POST['ewww_image_optimizer_maxmediaheight'] );
			$_POST['ewww_image_optimizer_resize_detection'] = ( empty( $_POST['ewww_image_optimizer_resize_detection'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_resize_detection', $_POST['ewww_image_optimizer_resize_detection'] );
			$_POST['ewww_image_optimizer_resize_existing'] = ( empty( $_POST['ewww_image_optimizer_resize_existing'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_resize_existing', $_POST['ewww_image_optimizer_resize_existing'] );
			$_POST['ewww_image_optimizer_resize_other_existing'] = ( empty( $_POST['ewww_image_optimizer_resize_other_existing'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_resize_other_existing', $_POST['ewww_image_optimizer_resize_other_existing'] );
			$_POST['ewww_image_optimizer_parallel_optimization'] = ( empty( $_POST['ewww_image_optimizer_parallel_optimization'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_parallel_optimization', $_POST['ewww_image_optimizer_parallel_optimization'] );
			$_POST['ewww_image_optimizer_include_media_paths'] = ( empty( $_POST['ewww_image_optimizer_include_media_paths'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_include_media_paths', $_POST['ewww_image_optimizer_include_media_paths'] );
			$_POST['ewww_image_optimizer_include_originals'] = ( empty( $_POST['ewww_image_optimizer_include_originals'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_include_originals', $_POST['ewww_image_optimizer_include_originals'] );
			$_POST['ewww_image_optimizer_webp_for_cdn'] = ( empty( $_POST['ewww_image_optimizer_webp_for_cdn'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_webp_for_cdn', $_POST['ewww_image_optimizer_webp_for_cdn'] );
			$_POST['ewww_image_optimizer_webp_force'] = ( empty( $_POST['ewww_image_optimizer_webp_force'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_webp_force', $_POST['ewww_image_optimizer_webp_force'] );
			$_POST['ewww_image_optimizer_webp_paths'] = ( empty( $_POST['ewww_image_optimizer_webp_paths'] ) ? '' : $_POST['ewww_image_optimizer_webp_paths'] );
			update_site_option( 'ewww_image_optimizer_webp_paths', ewww_image_optimizer_webp_paths_sanitize( $_POST['ewww_image_optimizer_webp_paths'] ) );
			$_POST['ewww_image_optimizer_allow_multisite_override'] = empty( $_POST['ewww_image_optimizer_allow_multisite_override'] ) ? false : true;
			update_site_option( 'ewww_image_optimizer_allow_multisite_override', $_POST['ewww_image_optimizer_allow_multisite_override'] );
			$_POST['ewww_image_optimizer_enable_help'] = empty( $_POST['ewww_image_optimizer_enable_help'] ) ? false : true;
			update_site_option( 'ewww_image_optimizer_enable_help', $_POST['ewww_image_optimizer_enable_help'] );
			global $ewwwio_tracking;
			$_POST['ewww_image_optimizer_allow_tracking'] = empty( $_POST['ewww_image_optimizer_allow_tracking'] ) ? false : $ewwwio_tracking->check_for_settings_optin( $_POST['ewww_image_optimizer_allow_tracking'] );
			update_site_option( 'ewww_image_optimizer_allow_tracking', $_POST['ewww_image_optimizer_allow_tracking'] );
			add_action( 'network_admin_notices', 'ewww_image_optimizer_network_settings_saved' );
		} elseif ( isset( $_POST['ewww_image_optimizer_allow_multisite_override_active'] ) && current_user_can( 'manage_network_options' ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww_image_optimizer_options-options' ) ) {
			ewwwio_debug_message( 'network-wide settings, single-site overriding' );
			$_POST['ewww_image_optimizer_allow_multisite_override'] = empty( $_POST['ewww_image_optimizer_allow_multisite_override'] ) ? false : true;
			update_site_option( 'ewww_image_optimizer_allow_multisite_override', $_POST['ewww_image_optimizer_allow_multisite_override'] );
			global $ewwwio_tracking;
			$_POST['ewww_image_optimizer_allow_tracking'] = empty( $_POST['ewww_image_optimizer_allow_tracking'] ) ? false : $ewwwio_tracking->check_for_settings_optin( $_POST['ewww_image_optimizer_allow_tracking'] );
			update_site_option( 'ewww_image_optimizer_allow_tracking', $_POST['ewww_image_optimizer_allow_tracking'] );
			add_action( 'network_admin_notices', 'ewww_image_optimizer_network_settings_saved' );
		} // End if().
	} // End if().
	if ( is_multisite() && get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' )
	) {
		ewww_image_optimizer_set_defaults();
		update_option( 'ewww_image_optimizer_disable_pngout', true );
		update_option( 'ewww_image_optimizer_optipng_level', 2 );
		update_option( 'ewww_image_optimizer_pngout_level', 2 );
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_option( 'ewww_image_optimizer_jpg_level', '10' );
		update_option( 'ewww_image_optimizer_png_level', '10' );
		update_option( 'ewww_image_optimizer_gif_level', '10' );
	}
	// Register all the common EWWW IO settings.
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_cloud_key', 'ewww_image_optimizer_cloud_key_sanitize' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_debug', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_metadata_remove', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_level', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_png_level', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_gif_level', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_pdf_level', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_backup_files', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_enable_cloudinary', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_quality', 'ewww_image_optimizer_jpg_quality' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_parallel_optimization', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_auto', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_include_media_paths', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_include_originals', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_aux_paths', 'ewww_image_optimizer_aux_paths_sanitize' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_exclude_paths', 'ewww_image_optimizer_exclude_paths_sanitize' );
	global $ewwwio_tracking;
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_allow_tracking', array( $ewwwio_tracking, 'check_for_settings_optin' ) );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_enable_help', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_exactdn', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'exactdn_all_the_things', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'exactdn_lossy', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_lazy_load', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_use_lqip', 'boolval' );
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
	ewww_image_optimizer_exec_init();
	ewww_image_optimizer_cron_setup( 'ewww_image_optimizer_auto' );
	// Queue the function that contains custom styling for our progressbars.
	add_action( 'admin_enqueue_scripts', 'ewww_image_optimizer_progressbar_style' );
	if ( defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD_KEY' ) && get_option( 'ewww_image_optimizer_cloud_key_invalid' ) ) {
		add_action( 'network_admin_notices', 'ewww_image_optimizer_notice_invalid_key' );
		add_action( 'admin_notices', 'ewww_image_optimizer_notice_invalid_key' );
	}
	if ( get_option( 'ewww_image_optimizer_webp_enabled' ) ) {
		add_action( 'admin_notices', 'ewww_image_optimizer_notice_webp_bulk' );
	}
	// Prevent ShortPixel AIO messiness.
	remove_action( 'admin_notices', 'autoptimizeMain::notice_plug_imgopt' );
	if ( class_exists( 'autoptimizeExtra' ) ) {
		$ao_extra = get_option( 'autoptimize_extra_settings' );
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) && ! empty( $ao_extra['autoptimize_extra_checkbox_field_5'] ) ) {
			ewwwio_debug_message( 'detected ExactDN + SP conflict' );
			$ao_extra['autoptimize_extra_checkbox_field_5'] = 0;
			update_option( 'autoptimize_extra_settings', $ao_extra );
			add_action( 'admin_notices', 'ewww_image_optimizer_notice_exactdn_sp_conflict' );
		}
	}

	// Alert user if multiple re-optimizations detected.
	add_action( 'network_admin_notices', 'ewww_image_optimizer_notice_reoptimization' );
	add_action( 'admin_notices', 'ewww_image_optimizer_notice_reoptimization' );
	add_action( 'admin_notices', 'ewww_image_optimizer_notice_media_listmode' );
	if (
		is_super_admin() &&
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_review_time' ) &&
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_review_time' ) < time() &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_dismiss_review_notice' )
	) {
		add_action( 'admin_notices', 'ewww_image_optimizer_notice_review' );
		add_action( 'admin_footer', 'ewww_image_optimizer_notice_review_script' );
	}
	if ( ! empty( $_GET['page'] ) ) {
		if ( 'regenerate-thumbnails' === $_GET['page']
			|| 'force-regenerate-thumbnails' === $_GET['page']
			|| 'ajax-thumbnail-rebuild' === $_GET['page']
			|| 'regenerate_thumbnails_advanced' === $_GET['page']
			|| 'rta_generate_thumbnails' === $_GET['page']
		) {
			// Add a notice for thumb regeneration.
			add_action( 'admin_notices', 'ewww_image_optimizer_thumbnail_regen_notice' );
		}
	}
	if ( ! empty( $_GET['ewww_pngout'] ) ) {
		add_action( 'admin_notices', 'ewww_image_optimizer_pngout_installed' );
		add_action( 'network_admin_notices', 'ewww_image_optimizer_pngout_installed' );
	}
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
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
	if ( defined( 'PHP_VERSION_ID' ) && PHP_VERSION_ID < 50600 ) {
		add_action( 'network_admin_notices', 'ewww_image_optimizer_php55_warning' );
		add_action( 'admin_notices', 'ewww_image_optimizer_php55_warning' );
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
	// Check for (Force) Regenerate Thumbnails action (includes MLP regenerate).
	if ( ! empty( $_REQUEST['action'] ) ) {
		if (
			'regeneratethumbnail' === $_REQUEST['action'] ||
			'rta_regenerate_thumbnails' === $_REQUEST['action'] ||
			'meauh_save_image' === $_REQUEST['action'] ||
			'hotspot_save' === $_REQUEST['action']
		) {
			ewwwio_debug_message( 'doing regeneratethumbnail' );
			ewww_image_optimizer_image_sizes( false );
			return;
		}
		if ( 'mic_crop_image' === $_REQUEST['action'] ) {
			ewwwio_debug_message( 'doing Manual Image Crop' );
			if ( ! defined( 'EWWWIO_EDITOR_OVERWRITE' ) ) {
				define( 'EWWWIO_EDITOR_OVERWRITE', true );
			}
			return;
		}
	}
	if ( ! empty( $_REQUEST['action'] ) && false !== strpos( $_REQUEST['action'], 'wc_regenerate_images' ) ) {
		// Unhook all automatic processing, and save an option that (does not autoload) tells the user WC regenerated their images and they should run the bulk optimizer.
		remove_filter( 'wp_image_editors', 'ewww_image_optimizer_load_editor', 60 );
		remove_filter( 'wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15 );
		update_option( 'ewww_image_optimizer_wc_regen', true, false );
		return;
	}
	// Check for Image Watermark plugin.
	if ( ! empty( $_POST['iw-action'] ) ) {
		$action = $_POST['iw-action'];
		ewwwio_debug_message( "doing $action" );
		global $ewww_preempt_editor;
		$ewww_preempt_editor = true;
		if ( 'applywatermark' === $action ) {
			remove_filter( 'wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15 );
			add_action( 'iw_after_apply_watermark', 'ewww_image_optimizer_single_size_optimize', 10, 2 );
		}
		return;
	}
	// Check for other MLP actions, including multi-regen.
	if ( ! empty( $_REQUEST['action'] ) && class_exists( 'MaxGalleriaMediaLib' ) && ( 'regen_mlp_thumbnails' === $_REQUEST['action'] || 'move_media' === $_REQUEST['action'] || 'copy_media' === $_REQUEST['action'] || 'maxgalleria_rename_image' === $_REQUEST['action'] ) ) {
		ewwwio_debug_message( 'doing regen_mlp_thumbnails' );
		ewww_image_optimizer_image_sizes( false );
		return;
	}
	// Check for MLP upload.
	if ( ! empty( $_REQUEST['action'] ) && class_exists( 'MaxGalleriaMediaLib' ) && ! empty( $_REQUEST['nonce'] ) && 'upload_attachment' === $_REQUEST['action'] ) {
		ewwwio_debug_message( 'doing maxgalleria upload' );
		ewww_image_optimizer_image_sizes( false );
		return;
	}
	// Check for Image Regenerate and Select Crop (better way).
	if ( defined( 'DOING_SIRSC' ) && DOING_SIRSC ) {
		ewwwio_debug_message( 'IRSC action/regen' );
		ewww_image_optimizer_image_sizes( false );
		return;
	} elseif ( ! empty( $_REQUEST['action'] ) && 0 === strpos( $_REQUEST['action'], 'sirsc' ) ) {
		// Image Regenerate and Select Crop (old check).
		ewwwio_debug_message( 'IRSC action/regen' );
		ewww_image_optimizer_image_sizes( false );
		return;
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
		$eio_debug         = '';
	}
}

/**
 * Optimize a single image from an attachment, based on the size and ID.
 *
 * @param int    $id The attachment ID number.
 * @param string $size The slug/name of the image size.
 */
function ewww_image_optimizer_single_size_optimize( $id, $size ) {
	// TODO: may be able to bring in a meta or filename param from IW eventually.
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
		$disabled_sizes = get_option( 'ewww_image_optimizer_disable_resizes_opt' );
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
		$resize_path = $base_dir . $data['file'];
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
		return;
	}
}

/**
 * Disables all the local tools by setting their constants to false.
 */
function ewww_image_optimizer_disable_tools() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
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

	// See if the path column exists, and what collation it uses to determine the column index size.
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$wpdb->ewwwio_images'" ) === $wpdb->ewwwio_images ) {
		ewwwio_debug_message( 'upgrading table and checking collation for path, table exists' );
		// Check if the old path_image_size index exists, and drop it.
		if ( $wpdb->get_results( "SHOW INDEX FROM $wpdb->ewwwio_images WHERE Key_name = 'path_image_size'", ARRAY_A ) ) {
			ewwwio_debug_message( 'getting rid of path_image_size index' );
			$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images DROP INDEX path_image_size" );
		}
		// Make sure there are valid dates in updated column.
		$wpdb->query( "UPDATE $wpdb->ewwwio_images SET updated = '1971-01-01 00:00:00' WHERE updated < '1001-01-01 00:00:01'" );
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
		id int(14) unsigned NOT NULL AUTO_INCREMENT,
		attachment_id bigint(20) unsigned,
		gallery varchar(10),
		resize varchar(75),
		path text NOT NULL,
		converted text NOT NULL,
		results varchar(75) NOT NULL,
		image_size int(10) unsigned,
		orig_size int(10) unsigned,
		backup varchar(100),
		level int(5) unsigned,
		pending tinyint(1) NOT NULL DEFAULT 0,
		updates int(5) unsigned,
		updated timestamp DEFAULT '1971-01-01 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
		trace blob,
		UNIQUE KEY id (id),
		KEY path (path($path_index_size)),
		KEY attachment_info (gallery(3),attachment_id)
	) $db_collation;";

	// Include the upgrade library to install/upgrade a table.
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	$updates = dbDelta( $sql );
	ewwwio_debug_message( 'images db upgrade results: ' . implode( '<br>', $updates ) );

	/*
	 * Create a table with XX columns:
	 * attachment_id: the unique id within the media library, nextgen, or flag
	 * gallery: 'media', 'nextgen', 'nextcell', or 'flag',
	 * scanned: 1 if the image is queued for optimization, 0 if it still needs scanning.
	 */
	$sql = "CREATE TABLE $wpdb->ewwwio_queue (
		attachment_id bigint(20) unsigned,
		gallery varchar(10),
		scanned tinyint(1) NOT NULL DEFAULT 0,
		KEY attachment_info (gallery(3),attachment_id)
	) COLLATE utf8_general_ci;";

	// Include the upgrade library to install/upgrade a table.
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	$updates = dbDelta( $sql );
	ewwwio_debug_message( 'queue db upgrade results: ' . implode( '<br>', $updates ) );

	// Make sure some of our options are not autoloaded (since they can be huge).
	// $bulk_attachments = get_option( 'ewww_image_optimizer_bulk_attachments', '' );.
	delete_option( 'ewww_image_optimizer_bulk_attachments' );
	// add_option( 'ewww_image_optimizer_bulk_attachments', $bulk_attachments, '', 'no' );.
	$bulk_attachments = get_option( 'ewww_image_optimizer_flag_attachments', '' );
	delete_option( 'ewww_image_optimizer_flag_attachments' );
	add_option( 'ewww_image_optimizer_flag_attachments', $bulk_attachments, '', 'no' );
	$bulk_attachments = get_option( 'ewww_image_optimizer_ngg_attachments', '' );
	delete_option( 'ewww_image_optimizer_ngg_attachments' );
	add_option( 'ewww_image_optimizer_ngg_attachments', $bulk_attachments, '', 'no' );
	delete_option( 'ewww_image_optimizer_aux_attachments' );
	delete_option( 'ewww_image_optimizer_defer_attachments' );
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
	delete_option( 'ewww_image_optimizer_exactdn_checkin' );
	delete_option( 'ewww_image_optimizer_exactdn_suspended' );
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
 * Checks to see if the JS WebP Rewriting was just enabled and perhaps we should enable Force mode for S3.
 *
 * @param mixed $new_value The new value, in this case it will be boolean usually.
 * @param mixed $old_value The old value, also a boolean generally.
 * @return mixed The new value, unaltered.
 */
function ewww_image_optimizer_webp_cdn_check_force( $new_value, $old_value ) {
	if ( ! empty( $new_value ) && (bool) $new_value !== (bool) $old_value ) {
		if ( class_exists( 'Amazon_S3_And_CloudFront' ) ) {
			global $as3cf;
			if ( $as3cf->get_setting( 'serve-from-s3' ) && $as3cf->get_setting( 'remove-local-file' ) ) {
				update_option( 'ewww_image_optimizer_webp_force', true );
			}
		}
	}
	return $new_value;
}

/**
 * Display a notice that the user should run the bulk optimizer after WebP activation.
 */
function ewww_image_optimizer_notice_webp_bulk() {
	$already_done = ewww_image_optimizer_aux_images_table_count();
	if ( $already_done > 50 ) {
		$message = esc_html__( 'It looks like you already started optimizing your images, you will need to generate WebP images via the Bulk Optimizer.', 'ewww-image-optimizer' );
		echo "<div id='ewww-image-optimizer-pngout-success' class='notice notice-info'><p><a href='upload.php?page=ewww-image-optimizer-bulk&ewww_webp_only=1&ewww_force=1'>" . $message . '</a></p></div>';
	} else {
		$message = esc_html__( 'Use the Bulk Optimizer to generate WebP images for existing uploads.', 'ewww-image-optimizer' );
		echo "<div id='ewww-image-optimizer-pngout-success' class='notice notice-info'><p><a href='upload.php?page=ewww-image-optimizer-bulk'>" . $message . '</a></p></div>';
	}
	delete_option( 'ewww_image_optimizer_webp_enabled' );
}

/**
 * Display a success or failure message after PNGOUT installation.
 */
function ewww_image_optimizer_pngout_installed() {
	if ( 'success' === $_REQUEST['ewww_pngout'] ) {
		echo "<div id='ewww-image-optimizer-pngout-success' class='notice notice-success fade'>\n" .
			'<p>' . esc_html__( 'Pngout was successfully installed.', 'ewww-image-optimizer' ) . "</p>\n" .
			"</div>\n";
	}
	if ( 'failed' === $_REQUEST['ewww_pngout'] ) {
		echo "<div id='ewww-image-optimizer-pngout-failure' class='notice notice-error'>\n" .
			'<p>' . sprintf(
				/* translators: 1: An error message 2: The folder where pngout should be installed */
				esc_html__( 'Pngout was not installed: %1$s. Make sure this folder is writable: %2$s', 'ewww-image-optimizer' ),
				sanitize_text_field( $_REQUEST['ewww_error'] ),
				EWWW_IMAGE_OPTIMIZER_TOOL_PATH
			) . "</p>\n" .
			"</div>\n";
	}
}

/**
 * Display a notice that we could not activate an ExactDN domain.
 */
function ewww_image_optimizer_notice_exactdn_activation_error() {
	global $exactdn_activate_error;
	if ( empty( $exactdn_activate_error ) ) {
		$exactdn_activate_error = 'error unknown';
	}
	echo '<div id="ewww-image-optimizer-notice-exactdn-error" class="notice notice-error"><p>' .
		esc_html__( 'Could not activate Easy IO, please try again in a few minutes. If this error continues, please contact support and provide this complete error message.', 'ewww-image-optimizer' ) .
		'<br><code>' . $exactdn_activate_error . '</code>' .
		'</p></div>';
}

/**
 * Let the user know ExactDN setup was successful.
 */
function ewww_image_optimizer_notice_exactdn_activation_success() {
	echo '<div id="ewww-image-optimizer-notice-exactdn-success" class="notice notice-success"><p>' .
		esc_html__( 'Easy IO setup and verification is complete.', 'ewww-image-optimizer' ) .
		'</p></div>';
}

/**
 * Display a notice that PHP version 5.5 support is going away.
 */
function ewww_image_optimizer_php55_warning() {
	echo '<div id="ewww-image-optimizer-notice-php55" class="notice notice-info"><p><a href="https://docs.ewww.io/article/55-upgrading-php" target="_blank" data-beacon-article="5ab2baa6042863478ea7c2ae">' . esc_html__( 'The next major release of EWWW Image Optimizer will require PHP 5.6 or greater. Newer versions of PHP, like 7.1 and 7.2, are significantly faster and much more secure. If you are unsure how to upgrade to a supported version, ask your webhost for instructions.', 'ewww-image-optimizer' ) . '</a></p></div>';
}

/**
 * Inform the user that we disabled SP AIO to prevent conflicts with ExactDN.
 */
function ewww_image_optimizer_notice_exactdn_sp_conflict() {
	echo "<div id='ewww-image-optimizer-exactdn-sp' class='notice notice-warning'><p>" . esc_html__( 'ShortPixel image optimization has been disabled to prevent conflicts with Easy IO (EWWW Image Optimizer).', 'ewww-image-optimizer' ) . '</p></div>';
}

/**
 * Lets the user know their network settings have been saved.
 */
function ewww_image_optimizer_network_settings_saved() {
	echo "<div id='ewww-image-optimizer-settings-saved' class='notice notice-success updated fade'><p><strong>" . esc_html__( 'Settings saved', 'ewww-image-optimizer' ) . '.</strong></p></div>';
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
		echo "<div id='ewww-image-optimizer-media-listmode' class='notice notice-info is-dismissible'><p>" . esc_html__( 'Change the Media Library to List mode for additional image optimization information and actions.', 'ewww-image-optimizer' ) . ewwwio_help_link( 'https://docs.ewww.io/article/62-power-user-options-in-list-mode', '5b61fdd32c7d3a03f89d41c4' ) . '</p></div>';
	}
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
	$optin_url  = 'admin.php?action=eio_opt_into_hs_beacon';
	$optout_url = 'admin.php?action=eio_opt_out_of_hs_beacon';
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
	if ( ! empty( $_GET['ewww_reset_reopt_nonce'] ) && wp_verify_nonce( $_GET['ewww_reset_reopt_nonce'], 'reset_reoptimization_counters' ) ) {
		global $wpdb;
		$debug_images = $wpdb->query( "UPDATE $wpdb->ewwwio_images SET updates=1 WHERE updates > 1" );
		delete_transient( 'ewww_image_optimizer_images_reoptimized' );
	} else {
		$reoptimized = get_transient( 'ewww_image_optimizer_images_reoptimized' );
		if ( empty( $reoptimized ) ) {
			global $wpdb;
			$reoptimized = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->ewwwio_images WHERE updates > 10 AND path NOT LIKE '%wp-content/themes%' AND path NOT LIKE '%wp-content/plugins%'  LIMIT 10" );
			if ( empty( $reoptimized ) ) {
				set_transient( 'ewww_image_optimizer_images_reoptimized', 'zero', HOUR_IN_SECONDS );
			} else {
				set_transient( 'ewww_image_optimizer_images_reoptimized', $reoptimized, HOUR_IN_SECONDS );
			}
		} elseif ( 'zero' === $reoptimized ) {
			$reoptimized = 0;
		}
		// Do a check for 10+ optimizations on 5+ images.
		if ( ! empty( $reoptimized ) && $reoptimized > 5 ) {
			$debugging_page = admin_url( 'upload.php?page=ewww-image-optimizer-dynamic-debug' );
			$reset_page     = wp_nonce_url( $_SERVER['REQUEST_URI'], 'reset_reoptimization_counters', 'ewww_reset_reopt_nonce' );
			// Display an alert, and let the user reset the warning if they wish.
			echo "<div id='ewww-image-optimizer-warning-reoptimizations' class='error'><p>" .
				sprintf(
					/* translators: %s: A link to the Dynamic Image Debugging page */
					esc_html__( 'The EWWW Image Optimizer has detected excessive re-optimization of multiple images. Please turn on the Debugging setting, wait for approximately 12 hours, and then visit the %s page.', 'ewww-image-optimizer' ),
					"<a href='$debugging_page'>" . esc_html__( 'Dynamic Image Debugging', 'ewww-image-optimizer' ) . '</a>'
				) .
				" <a href='$reset_page'>" . esc_html__( 'Reset Counters' ) . '</a></p></div>';
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
	if ( ! empty( $_GET['pte-action'] ) ) {
		if ( 'confirm-images' === $_GET['pte-action'] ) {
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
		rename( $old_webp, $new_webp );
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
	$disabled_sizes = get_option( 'ewww_image_optimizer_disable_resizes' );
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
	$disabled_sizes = get_option( 'ewww_image_optimizer_disable_resizes' );
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
		if ( ( ! is_wp_error( $params ) ) && ewwwio_is_file( $file_path ) && in_array( $mime_type, array( 'image/png', 'image/gif', 'image/jpeg' ), true ) ) {
			ewww_image_optimizer_resize_upload( $file_path );
		}
	}
	clearstatcache();
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
	$upload_info = wp_upload_dir( null, false );

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
		ewww_image_optimizer_debug_log();
		return;
	}
	global $ewww_defer;
	$ewww_defer = false;
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'bulk.php' );
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'aux-optimize.php' );
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_auto' ) ) {
		ewwwio_debug_message( 'running scheduled optimization' );
		ewww_image_optimizer_aux_images_script( 'ewww-image-optimizer-auto' );
		// Generate our own unique nonce value, because wp_create_nonce() will return the same value for 12-24 hours.
		$nonce = wp_hash( time() . '|' . 'ewww-image-optimizer-auto' );
		update_option( 'ewww_image_optimizer_aux_resume', $nonce );
		$delay = ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' );
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
			EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE,      // Slug.
			'ewww_image_optimizer_network_options' // Function to call.
		);
	}
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
	echo $new_file;
	if ( ewwwio_is_file( $new_file ) ) {
		echo '<br>file already exists<br>';
	}
	$saved = $editor->save( $new_file );
}

/**
 * Adds various items to the admin menu.
 */
function ewww_image_optimizer_admin_menu() {
	// Adds bulk optimize to the media library menu.
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	add_media_page( esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ), esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ), $permissions, 'ewww-image-optimizer-bulk', 'ewww_image_optimizer_bulk_preview' );
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
			EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL,                                            // Slug.
			'ewww_image_optimizer_options'                                               // Function to call.
		);
	} else {
		// Add options page to the single-site settings menu.
		add_options_page(
			'EWWW Image Optimizer',                                                      // Page title.
			'EWWW Image Optimizer',                                                      // Menu title.
			apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' ), // Capability.
			EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL,                                            // Slug.
			'ewww_image_optimizer_network_singlesite_options'                            // Function to call.
		);
	}
	global $ewwwio_temp_debug;
	if ( ! $ewwwio_temp_debug && ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
		// Add Dynamic Image Debugging page for image regeneration issues.
		add_media_page( esc_html__( 'Dynamic Image Debugging', 'ewww-image-optimizer' ), esc_html__( 'Dynamic Image Debugging', 'ewww-image-optimizer' ), $permissions, 'ewww-image-optimizer-dynamic-debug', 'ewww_image_optimizer_dynamic_image_debug' );
		// Add Image Queue Debugging to allow clearing and checking queues.
		add_media_page( esc_html__( 'Image Queue Debugging', 'ewww-image-optimizer' ), esc_html__( 'Image Queue Debugging', 'ewww-image-optimizer' ), $permissions, 'ewww-image-optimizer-queue-debug', 'ewww_image_optimizer_image_queue_debug' );
	}
	if ( is_plugin_active( 'image-store/ImStore.php' ) || is_plugin_active_for_network( 'image-store/ImStore.php' ) ) {
		// Adds an optimize page for Image Store galleries and images.
		$ims_menu      = 'edit.php?post_type=ims_gallery';
		$ewww_ims_page = add_submenu_page( $ims_menu, esc_html__( 'Image Store Optimize', 'ewww-image-optimizer' ), esc_html__( 'Optimize', 'ewww-image-optimizer' ), 'ims_change_settings', 'ewww-ims-optimize', 'ewww_image_optimizer_ims' );
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
		rename( $webp_path, $retina_path . '.webp' );
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
 * List IMS images and optimization status.
 *
 * @global object $wpdb
 */
function ewww_image_optimizer_ims() {
	global $wpdb;
	$ims_columns = get_column_headers( 'ims_gallery' );
	echo "<div class='wrap'><h1>" . esc_html__( 'Image Store Optimization', 'ewww-image-optimizer' ) . '</h1>';
	if ( empty( $_REQUEST['ewww_gid'] ) ) {
		$galleries = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = 'ims_gallery' ORDER BY ID" );
		if ( ewww_image_optimizer_iterable( $galleries ) ) {
			$gallery_string = implode( ',', $galleries );
			echo '<p>' . esc_html__( 'Choose a gallery or', 'ewww-image-optimizer' ) . " <a href='upload.php?page=ewww-image-optimizer-bulk&ids=$gallery_string'>" . esc_html__( 'optimize all galleries', 'ewww-image-optimizer' ) . '</a></p>';
			echo '<table class="wp-list-table widefat media" cellspacing="0"><thead><tr><th>' . esc_html__( 'Gallery ID', 'ewww-image-optimizer' ) . '</th><th>' . esc_html__( 'Gallery Name', 'ewww-image-optimizer' ) . '</th><th>' . esc_html__( 'Images', 'ewww-image-optimizer' ) . '</th><th>' . esc_html__( 'Image Optimizer', 'ewww-image-optimizer' ) . '</th></tr></thead>';
			foreach ( $galleries as $gid ) {
				$image_count  = $wpdb->get_var( $wpdb->prepare( "SELECT count(ID) FROM $wpdb->posts WHERE post_type = 'ims_image' AND post_mime_type LIKE %s AND post_parent = %d", '%image%', $gid ) );
				$gallery_name = get_the_title( $gid );
				echo "<tr><td>$gid</td>";
				echo "<td><a href='edit.php?post_type=ims_gallery&page=ewww-ims-optimize&ewww_gid=$gid'>$gallery_name</a></td>";
				echo "<td>$image_count</td>";
				echo "<td><a href='upload.php?page=ewww-image-optimizer-bulk&ids=$gid'>" . esc_html__( 'Optimize Gallery', 'ewww-image-optimizer' ) . '</a></td></tr>';
			}
			echo '</table>';
		} else {
			echo '<p>' . esc_html__( 'No galleries found', 'ewww-image-optimizer' ) . '</p>';
		}
	} else {
		$gid         = (int) $_REQUEST['ewww_gid'];
		$attachments = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = 'ims_image' AND post_mime_type LIKE %s AND post_parent = %d ORDER BY ID", '%image%', $gid ) );
		if ( ewww_image_optimizer_iterable( $attachments ) ) {
			echo "<p><a href='upload.php?page=ewww-image-optimizer-bulk&ids=$gid'>" . esc_html__( 'Optimize Gallery', 'ewww-image-optimizer' ) . '</a></p>';
			echo '<table class="wp-list-table widefat media" cellspacing="0"><thead><tr><th>ID</th><th>&nbsp;</th><th>' . esc_html__( 'Title', 'ewww-image-optimizer' ) . '</th><th>' . esc_html__( 'Gallery', 'ewww-image-optimizer' ) . '</th><th>' . esc_html__( 'Image Optimizer', 'ewww-image-optimizer' ) . '</th></tr></thead>';
			$alternate = true;
			foreach ( $attachments as $id ) {
				$meta = get_metadata( 'post', $id );
				if ( empty( $meta['_wp_attachment_metadata'] ) ) {
					continue;
				}
				$meta         = maybe_unserialize( $meta['_wp_attachment_metadata'][0] );
				$image_name   = get_the_title( $id );
				$gallery_name = get_the_title( $gid );
				$image_url    = esc_url( $meta['sizes']['mini']['url'] );
				?>
				<tr
				<?php
				if ( $alternate ) {
					echo " class='alternate'";
				}
				?>
				><td><?php echo $id; ?></td>
				<?php
				echo "<td style='width:80px' class='column-icon'><img src='$image_url' /></td>";
				echo "<td class='title'>$image_name</td>";
				echo "<td>$gallery_name</td><td>";
				ewww_image_optimizer_custom_column( 'ewww-image-optimizer', $id );
				echo '</td></tr>';
				$alternate = ! $alternate;
			}
			echo '</table>';
		} else {
			echo '<p>' . esc_html__( 'No images found', 'ewww-image-optimizer' ) . '</p>';
		}
	} // End if().
	echo '</div>';
	return;
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
		wp_enqueue_script( 'ewwwmediascript', plugins_url( '/includes/media.js', __FILE__ ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION );
		wp_enqueue_style( 'jquery-ui-tooltip-custom', plugins_url( '/includes/jquery-ui-1.10.1.custom.css', __FILE__ ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
		// Submit a couple variables to the javascript to work with.
		$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
		$loading_image = plugins_url( '/images/spinner.gif', __FILE__ );
		wp_localize_script(
			'ewwwmediascript',
			'ewww_vars',
			array(
				'optimizing' => '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' /></p>",
				'restoring'  => '<p>' . esc_html__( 'Restoring', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' /></p>",
			)
		);
	}
}

/**
 * Adds a link on the Plugins page for the EWWW IO settings.
 *
 * @param array $links A list of links to display next to the plugin listing.
 * @return array The new list of links to be displayed.
 */
function ewww_image_optimizer_settings_link( $links ) {
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( ! is_array( $links ) ) {
		$links = array();
	}
	// Load the html for the settings link.
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		$settings_link = '<a href="network/settings.php?page=' . plugin_basename( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ) . '">' . esc_html__( 'Settings', 'ewww-image-optimizer' ) . '</a>';
	} else {
		$settings_link = '<a href="options-general.php?page=' . plugin_basename( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ) . '">' . esc_html__( 'Settings', 'ewww-image-optimizer' ) . '</a>';
	}
	// Load the settings link into the plugin links array.
	array_unshift( $links, $settings_link );
	// Send back the plugin links array.
	return $links;
}

/**
 * Check for GD support of both PNG and JPG.
 *
 * @return bool True if full GD support is detected.
 */
function ewww_image_optimizer_gd_support() {
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
				return true;
			}
		}
	}
	return false;
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
	$path_array  = array();
	$paths       = explode( "\n", $input );
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
			$upload_dir = apply_filters( 'ewww_image_optimizer_folder_restriction', wp_upload_dir( null, false ) );
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
			if ( is_dir( $path ) && ( ( $abspath && strpos( $path, ABSPATH ) === 0 ) || strpos( $path, $upload_path ) === 0 ) ) {
				$path_array[] = $path;
				continue;
			}
			// If they put in a relative path.
			if ( $abspath && is_dir( ABSPATH . ltrim( $path, '/' ) ) ) {
				$path_array[] = ABSPATH . ltrim( $path, '/' );
				continue;
			}
			// Or a path relative to the upload dir?
			if ( is_dir( $upload_path . ltrim( $path, '/' ) ) ) {
				$path_array[] = $upload_path . ltrim( $path, '/' );
				continue;
			}
			// What if they put in a url?
			$pathabsurl = ABSPATH . ltrim( str_replace( get_site_url(), '', $path ), '/' );
			if ( $abspath && is_dir( $pathabsurl ) ) {
				$path_array[] = $pathabsurl;
				continue;
			}
			// Or a url in the uploads folder?
			$pathupurl = $upload_path . ltrim( str_replace( $upload_dir['baseurl'], '', $path ), '/' );
			if ( is_dir( $pathupurl ) ) {
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
	$paths      = explode( "\n", $input );
	if ( ewww_image_optimizer_iterable( $paths ) ) {
		$i = 0;
		foreach ( $paths as $path ) {
			$i++;
			ewwwio_debug_message( "validating path exclusion: $path" );
			$path = sanitize_text_field( $path );
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
	$paths_entered = explode( "\n", $paths );
	$paths_saved   = array();
	if ( ewww_image_optimizer_iterable( $paths_entered ) ) {
		$i = 0;
		foreach ( $paths_entered as $path ) {
			$i++;
			$original_path = esc_html( $path );
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
						) . ' ' . esc_html__( 'Please enter a valid url including the domain name.', 'ewww-image-optimizer' )
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
function ewww_image_optimizer_adjust_big_image_threshold( $size, $imagesize, $file ) {
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
 * Check filesize, and prevent errors by ensuring file exists, and that the cache has been cleared.
 *
 * @param string $file The name of the file.
 * @return int The size of the file or zero.
 */
function ewww_image_optimizer_filesize( $file ) {
	if ( ewwwio_is_file( $file ) ) {
		// Flush the cache for filesize.
		clearstatcache();
		// Find out the size of the new PNG file.
		return filesize( $file );
	} else {
		return 0;
	}
}

/**
 * Check if file exists, and that is is local rather than using a protocol like http:// or phar://
 *
 * @param string $file The path of the file to check.
 * @return bool True if the file exists and is local, false otherwise.
 */
function ewwwio_is_file( $file ) {
	if ( false !== strpos( $file, '://' ) ) {
		return false;
	}
	if ( false !== strpos( $file, '../' ) ) {
		return false;
	}
	return is_file( $file );
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
	global $ewww_defer;
	$ewww_defer = false;
	// Check permissions of current user.
	$permissions = apply_filters( 'ewww_image_optimizer_manual_permissions', '' );
	if ( false === current_user_can( $permissions ) ) {
		// Display error message if insufficient permissions.
		if ( ! wp_doing_ajax() ) {
			wp_die( esc_html__( 'You do not have permission to optimize images.', 'ewww-image-optimizer' ) );
		}
		ewwwio_ob_clean();
		wp_die( ewwwio_json_encode( array( 'error' => esc_html__( 'You do not have permission to optimize images.', 'ewww-image-optimizer' ) ) ) );
	}
	// Make sure we didn't accidentally get to this page without an attachment to work on.
	if ( false === isset( $_REQUEST['ewww_attachment_ID'] ) ) {
		// Display an error message since we don't have anything to work on.
		if ( ! wp_doing_ajax() ) {
			wp_die( esc_html__( 'No attachment ID was provided.', 'ewww-image-optimizer' ) );
		}
		ewwwio_ob_clean();
		wp_die( ewwwio_json_encode( array( 'error' => esc_html__( 'No attachment ID was provided.', 'ewww-image-optimizer' ) ) ) );
	}
	session_write_close();
	// Store the attachment ID value.
	$attachment_id = intval( $_REQUEST['ewww_attachment_ID'] );
	if ( empty( $_REQUEST['ewww_manual_nonce'] ) || ! wp_verify_nonce( $_REQUEST['ewww_manual_nonce'], 'ewww-manual' ) ) {
		if ( ! wp_doing_ajax() ) {
			wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		ewwwio_ob_clean();
		wp_die( ewwwio_json_encode( array( 'error' => esc_html__( 'Access denied.', 'ewww-image-optimizer' ) ) ) );
	}
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
		wp_die( ewwwio_json_encode( array( 'error' => esc_html__( 'Access denied.', 'ewww-image-optimizer' ) ) ) );
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
	if ( get_transient( 'ewww_image_optimizer_cloud_status' ) === 'exceeded' || ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_exceeded' ) > time() ) {
		if ( ! wp_doing_ajax() ) {
			wp_die( esc_html__( 'License exceeded', 'ewww-image-optimizer' ) );
		}
		ewwwio_ob_clean();
		wp_die( ewwwio_json_encode( array( 'error' => esc_html__( 'License exceeded', 'ewww-image-optimizer' ) ) ) );
	}
	$success = ewww_image_optimizer_custom_column( 'ewww-image-optimizer', $attachment_id, $new_meta, true );
	ewww_image_optimizer_debug_log();
	// Do a redirect, if this was called via GET.
	if ( ! wp_doing_ajax() ) {
		// Store the referring webpage location.
		$sendback = wp_get_referer();
		// Sanitize the referring webpage location.
		$sendback = preg_replace( '|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback );
		// Send the user back where they came from.
		wp_redirect( $sendback );
		return;
	}
	ewwwio_memory( __FUNCTION__ );
	ewwwio_ob_clean();
	wp_die(
		ewwwio_json_encode(
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
			list( $width, $height ) = getimagesize( $image['path'] );
			if ( (int) $width !== (int) $meta['width'] || (int) $height !== (int) $meta['height'] ) {
				$meta['height'] = $height;
				$meta['width']  = $width;
			}
		}
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
		wp_die( ewwwio_json_encode( array( 'error' => esc_html__( 'You do not have permission to optimize images.', 'ewww-image-optimizer' ) ) ) );
	}
	// Make sure we didn't accidentally get to this page without an attachment to work on.
	if ( empty( $_REQUEST['ewww_image_id'] ) ) {
		// Display an error message since we don't have anything to work on.
		ewwwio_ob_clean();
		wp_die( ewwwio_json_encode( array( 'error' => esc_html__( 'No image ID was provided.', 'ewww-image-optimizer' ) ) ) );
	}
	if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-tools' ) ) {
		ewwwio_ob_clean();
		wp_die( ewwwio_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) ) ) );
	}
	session_write_close();
	$image = (int) $_REQUEST['ewww_image_id'];
	if ( ewww_image_optimizer_cloud_restore_single_image( $image ) ) {
		ewwwio_ob_clean();
		wp_die( ewwwio_json_encode( array( 'success' => 1 ) ) );
	}
	ewwwio_ob_clean();
	wp_die( ewwwio_json_encode( array( 'error' => esc_html__( 'Unable to restore image.', 'ewww-image-optimizer' ) ) ) );
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
		$enabled_types = array( 'image/jpeg', 'image/png', 'image/gif', 'application/pdf' );
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
			if ( rename( $image['path'] . '.tmp', $image['path'] ) ) {
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
				if ( strpos( $image['path'], WP_CONTENT_DIR ) === false ) {
					continue;
				}
				if ( ! empty( $image['path'] ) ) {
					if ( ewwwio_is_file( $image['path'] ) ) {
						unlink( $image['path'] );
					}
					if ( ewwwio_is_file( $image['path'] . '.webp' ) ) {
						unlink( $image['path'] . '.webp' );
					}
				}
				if ( ! empty( $image['converted'] ) ) {
					$image['converted'] = ewww_image_optimizer_absolutize_path( $image['converted'] );
				}
				if ( ! empty( $image['converted'] ) && ewwwio_is_file( $image['converted'] ) ) {
					unlink( $image['converted'] );
					if ( ewwwio_is_file( $image['converted'] . '.webp' ) ) {
						unlink( $image['converted'] . '.webp' );
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
		// Get the base filename.
		$filename = basename( $file_path );
		// Delete any residual webp versions.
		$webpfile    = $file_path . '.webp';
		$webpfileold = preg_replace( '/\.\w+$/', '.webp', $file_path );
		if ( ewwwio_is_file( $webpfile ) ) {
			unlink( $webpfile );
		}
		if ( ewwwio_is_file( $webpfileold ) ) {
			unlink( $webpfileold );
		}
		// Retrieve any posts that link the original image.
		$esql = "SELECT ID, post_content FROM $ewwwdb->posts WHERE post_content LIKE '%$filename%' LIMIT 1";
		$rows = $ewwwdb->get_row( $esql );
		// If the original file still exists and no posts contain links to the image.
		if ( ewwwio_is_file( $file_path ) && empty( $rows ) ) {
			unlink( $file_path );
			$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $file_path ) ) );
		}
	}
	list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
	// If the attachment has an original file set.
	if ( ! empty( $meta['original_image'] ) ) {
		// One way or another, $file_path is now set, and we can get the base folder name.
		$base_dir = dirname( $file_path ) . '/';
		// Get the original filename from the metadata.
		$orig_path = $base_dir . basename( $meta['original_image'] );
		// Delete any residual webp versions.
		$webpfile = $orig_path . '.webp';
		if ( ewwwio_is_file( $webpfile ) ) {
			unlink( $webpfile );
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
				unlink( $webpfile );
			}
			if ( ewwwio_is_file( $webpfileold ) ) {
				unlink( $webpfileold );
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
					unlink( $base_dir . $data['orig_file'] );
					$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize( $base_dir . $data['orig_file'] ) ) );
				}
			}
		}
	}
	ewwwio_memory( __FUNCTION__ );
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
				unlink( $image['converted'] );
				if ( ewwwio_is_file( $image['converted'] . '.webp' ) ) {
					unlink( $image['converted'] . '.webp' );
				}
			}
			$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'id' => $image['id'] ) );
		}
	}
	if ( ewwwio_is_file( $file . '.webp' ) ) {
		unlink( $file . '.webp' );
	}
}

/**
 * Cleans records from database when an image is being replaced.
 *
 * @param array $image An array with the attachment/image ID.
 */
function ewww_image_optimizer_media_replace( $image ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	$id = (int) $image['post_id'];
	// Finds non-meta images to remove from disk, and from db, as well as converted originals.
	$optimized_images = $ewwwdb->get_results( "SELECT path,converted FROM $ewwwdb->ewwwio_images WHERE attachment_id = $id AND gallery = 'media'", ARRAY_A );
	if ( $optimized_images ) {
		if ( ewww_image_optimizer_iterable( $optimized_images ) ) {
			foreach ( $optimized_images as $image ) {
				if ( ! empty( $image['path'] ) ) {
					$image['path'] = ewww_image_optimizer_absolutize_path( $image['path'] );
				}
				if ( strpos( $image['path'], WP_CONTENT_DIR ) === false ) {
					continue;
				}
				if ( ! empty( $image['path'] ) ) {
					if ( ewwwio_is_file( $image['path'] . '.webp' ) ) {
						unlink( $image['path'] . '.webp' );
					}
				}
				if ( ! empty( $image['converted'] ) ) {
					$image['converted'] = ewww_image_optimizer_absolutize_path( $image['converted'] );
				}
				if ( ! empty( $image['converted'] ) && ewwwio_is_file( $image['converted'] ) ) {
					unlink( $image['converted'] );
					if ( ewwwio_is_file( $image['converted'] . '.webp' ) ) {
						unlink( $image['converted'] . '.webp' );
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
			unlink( $webpfile );
		}
		if ( ewwwio_is_file( $webpfileold ) ) {
			unlink( $webpfileold );
		}
		$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $file_path ) ) );
	}
	list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
	// If the attachment has an original file set.
	if ( ! empty( $meta['original_image'] ) ) {
		// One way or another, $file_path is now set, and we can get the base folder name.
		$base_dir = dirname( $file_path ) . '/';
		// Get the original filename from the metadata.
		$orig_path = $base_dir . basename( $meta['original_image'] );
		// Delete any residual webp versions.
		$webpfile = $orig_path . '.webp';
		if ( ewwwio_is_file( $webpfile ) ) {
			unlink( $webpfile );
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
				unlink( $webpfile );
			}
			if ( ewwwio_is_file( $webpfileold ) ) {
				unlink( $webpfileold );
			}
			$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $base_dir . $data['file'] ) ) );
			// If the original resize is set, and still exists.
			if ( ! empty( $data['orig_file'] ) ) {
				// Retrieve the filename from the metadata.
				$filename = $data['orig_file'];
				$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize( $base_dir . $data['orig_file'] ) ) );
			}
		}
	}
}

/**
 * Sanitizes and verifies an API key for the cloud service.
 *
 * @param string $key An API key entered by the user.
 * @return string A sanitized and validated API key.
 */
function ewww_image_optimizer_cloud_key_sanitize( $key ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$key = trim( $key );
	if ( ewww_image_optimizer_function_exists( 'print_r' ) ) {
		/* ewwwio_debug_message( print_r( $_REQUEST, true ) ); */
	}
	if ( empty( $key ) ) {
		return '';
	}
	if ( ewww_image_optimizer_cloud_verify( false, $key ) ) {
		add_settings_error( 'ewww_image_optimizer_cloud_key', 'ewwwio-cloud-key', esc_html__( 'Successfully validated API key, happy optimizing!', 'ewww-image-optimizer' ), 'updated' );
		ewwwio_debug_message( 'sanitize (verification) successful' );
		ewwwio_memory( __FUNCTION__ );
		return $key;
	} else {
		if ( ! empty( $key ) ) {
			add_settings_error( 'ewww_image_optimizer_cloud_key', 'ewwwio-cloud-key', esc_html__( 'Could not validate API key, please copy and paste your key to ensure it is correct.', 'ewww-image-optimizer' ) );
		}
		ewwwio_debug_message( 'sanitize (verification) failed' );
		ewwwio_memory( __FUNCTION__ );
		return '';
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
 * @param bool   $cache Optional. True to return cached verification results. Default true.
 * @param string $api_key Optional. The API key to verify. Default empty string.
 * @return string|bool False if verification fails, status message otherwise: great/exceeded.
 */
function ewww_image_optimizer_cloud_verify( $cache = true, $api_key = '' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$sanitize = false;
	if ( ! empty( $api_key ) ) {
		$sanitize = true;
	}
	if ( empty( $api_key ) && ! ( ! empty( $_REQUEST['option_page'] ) && 'ewww_image_optimizer_options' === $_REQUEST['option_page'] ) ) {
		$api_key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	} elseif ( empty( $api_key ) && ! empty( $_POST['ewww_image_optimizer_cloud_key'] ) ) {
		$api_key = $_POST['ewww_image_optimizer_cloud_key'];
	}
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
		set_transient( 'ewww_image_optimizer_cloud_status', 'exceeded', 3600 );
		ewwwio_debug_message( 'license exceeded notice has not expired' );
		return 'exceeded';
	}
	add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
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
		if ( ! $sanitize && false !== strpos( $result['body'], 'expired' ) ) {
			ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_key', '' );
		}
		ewwwio_debug_message( "verification success via: $url" );
		delete_option( 'ewww_image_optimizer_cloud_key_invalid' );
	} else {
		update_option( 'ewww_image_optimizer_cloud_key_invalid', true, false );
		if ( ! $sanitize && ! empty( $result['body'] ) && false !== strpos( $result['body'], 'invalid' ) ) {
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
		set_transient( 'ewww_image_optimizer_cloud_status', $verified, 3600 );
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) < 20 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) < 20 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) < 20 && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) ) {
			ewww_image_optimizer_cloud_enable();
		}
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
	echo "<div id='ewww-image-optimizer-invalid-key' class='notice error'><p><strong>" . esc_html__( 'Could not validate EWWW Image Optimizer API key, please check your key to ensure it is correct.', 'ewww-image-optimizer' ) . '</strong></p></div>';
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
		if ( ! $quota['licensed'] && $quota['consumed'] > 0 ) {
			return esc_html(
				sprintf(
					/* translators: 1: Number of images 2: Number of days until renewal */
					_n( 'optimized %1$d images, renewal is in %2$d day.', 'optimized %1$d images, renewal is in %2$d days.', $quota['days'], 'ewww-image-optimizer' ),
					$quota['consumed'],
					$quota['days']
				)
			);
		} elseif ( ! $quota['licensed'] && $quota['consumed'] < 0 ) {
			return esc_html(
				sprintf(
					/* translators: 1: Number of images */
					_n( '%1$d image credit remaining.', '%1$d image credits remaining.', abs( $quota['consumed'] ), 'ewww-image-optimizer' ),
					abs( $quota['consumed'] )
				)
			);
		} elseif ( $quota['licensed'] > 0 && $quota['consumed'] < 0 ) {
			$real_quota = (int) $quota['licensed'] - (int) $quota['consumed'];
			return esc_html(
				sprintf(
					/* translators: 1: Number of images */
					_n( '%1$d image credit remaining.', '%1$d image credits remaining.', $real_quota, 'ewww-image-optimizer' ),
					$real_quota
				)
			);
		} elseif ( ! $quota['licensed'] && ! $quota['consumed'] && ! $quota['days'] && ! $quota['metered'] ) {
			return esc_html__( 'no credits remaining, please purchase more.', 'ewww-image-optimizer' );
		} else {
			return esc_html(
				sprintf(
					/* translators: 1: Number of image credits used 2: Number of image credits available 3: days until subscription renewal */
					_n( 'used %1$d of %2$d, usage will reset in %3$d day.', 'used %1$d of %2$d, usage will reset in %3$d days.', $quota['days'], 'ewww-image-optimizer' ),
					$quota['consumed'],
					$quota['licensed'],
					$quota['days']
				)
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
 *     @type string Set to 'exceeded' if the API key is out of credits.
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
		ewww_image_optimizer_debug_log();
	}
	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	$started     = microtime( true );
	if ( preg_match( '/exceeded/', $ewww_status ) ) {
		if ( ! ewww_image_optimizer_cloud_verify() ) {
			return array( $file, false, 'key verification failed', 0, '' );
		}
	}
	// Calculate how much time has elapsed since we started.
	$elapsed = microtime( true ) - $started;
	ewwwio_debug_message( "cloud verify took $elapsed seconds" );
	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	if ( ( ! empty( $ewww_status ) && preg_match( '/exceeded/', $ewww_status ) ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_exceeded' ) > time() ) {
		ewwwio_debug_message( 'license exceeded, image not processed' );
		return array( $file, false, 'exceeded', 0, '' );
	}
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
	if ( 'image/webp' === $newtype ) {
		$webp        = 1;
		$jpg_quality = apply_filters( 'jpeg_quality', $jpg_quality, 'image/webp' );
		if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_LOSSY_PNG2WEBP' ) || ! EWWW_IMAGE_OPTIMIZER_LOSSY_PNG2WEBP ) {
			$lossy = 0;
		}
	} else {
		$webp = 0;
	}
	if ( 30 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
		$png_compress = 1;
	} else {
		$png_compress = 0;
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
		if ( empty( $hash ) && ! empty( $_REQUEST['ewww_force'] ) ) {
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
	ewwwio_debug_message( "jpg fill: $jpg_fill" );
	ewwwio_debug_message( "jpg quality: $jpg_quality" );
	$api_key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	if ( empty( $api_key ) ) {
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
	$payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename( $file ) . '"' . "\r\n";
	$payload .= 'Content-Type: ' . $type . "\r\n";
	$payload .= "\r\n";
	$payload .= file_get_contents( $file );
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
		} elseif ( 100 > strlen( $response['body'] ) && strpos( $response['body'], 'exceeded' ) ) {
			ewwwio_debug_message( 'License Exceeded' );
			set_transient( 'ewww_image_optimizer_cloud_status', 'exceeded', 3600 );
			$msg = 'exceeded';
			unlink( $tempfile );
		} elseif ( ewww_image_optimizer_mimetype( $tempfile, 'i' ) === $type ) {
			$newsize = filesize( $tempfile );
			ewwwio_debug_message( "cloud results: $newsize (new) vs. $orig_size (original)" );
			rename( $tempfile, $file );
		} elseif ( ewww_image_optimizer_mimetype( $tempfile, 'i' ) === 'image/webp' ) {
			$newsize = filesize( $tempfile );
			ewwwio_debug_message( "cloud results: $newsize (new) vs. $orig_size (original)" );
			rename( $tempfile, $newfile );
		} elseif ( ! is_null( $newtype ) && ! is_null( $newfile ) && ewww_image_optimizer_mimetype( $tempfile, 'i' ) === $newtype ) {
			$converted = true;
			$newsize   = filesize( $tempfile );
			ewwwio_debug_message( "cloud results: $newsize (new) vs. $orig_size (original)" );
			ewwwio_debug_message( "renaming file from $tempfile to $newfile" );
			rename( $tempfile, $newfile );
			$file = $newfile;
		} else {
			unlink( $tempfile );
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
		ewww_image_optimizer_debug_log();
	}
	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	$started     = microtime( true );
	if ( preg_match( '/exceeded/', $ewww_status ) ) {
		if ( ! ewww_image_optimizer_cloud_verify() ) {
			ewwwio_debug_message( 'cloud verify failed, image not rotated' );
			return false;
		}
	}
	// Calculate how much time has elapsed since we started.
	$elapsed = microtime( true ) - $started;
	ewwwio_debug_message( "cloud verify took $elapsed seconds" );
	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	if ( ( ! empty( $ewww_status ) && preg_match( '/exceeded/', $ewww_status ) ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_exceeded' ) > time() ) {
		ewwwio_debug_message( 'license exceeded, image not rotated' );
		return false;
	}
	ewwwio_debug_message( "file: $file " );
	ewwwio_debug_message( "type: $type" );
	$api_key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	if ( empty( $api_key ) ) {
		return false;
	}
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
	$payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename( $file ) . '"' . "\r\n";
	$payload .= 'Content-Type: ' . $type . "\r\n";
	$payload .= "\r\n";
	$payload .= file_get_contents( $file );
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
			set_transient( 'ewww_image_optimizer_cloud_status', 'exceeded', 3600 );
			unlink( $tempfile );
		} elseif ( ewww_image_optimizer_mimetype( $tempfile, 'i' ) === $type ) {
			$newsize = filesize( $tempfile );
			ewwwio_debug_message( "cloud rotation success: $newsize (new) vs. $orig_size (original)" );
			rename( $tempfile, $file );
			return true;
		} else {
			unlink( $tempfile );
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
	if ( ! ewwwio_is_file( $file ) || ! is_readable( $file ) || false !== strpos( $file, '../' ) ) {
		return false;
	}
	if ( ! ewwwio_check_memory_available( filesize( $file ) * 1.1 ) ) { // 1.1 = upload buffer (filesize) multiplied by a factor of 1.1 for extra wiggle room.
		$memory_required = filesize( $file ) * 1.1;
		ewwwio_debug_message( "possibly insufficient memory for cloud (backup) operation: $memory_required" );
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			add_filter( 'image_memory_limit', 'ewww_image_optimizer_raise_memory_limit' );
			wp_raise_memory_limit( 'image' );
		}
		ewww_image_optimizer_debug_log();
	}
	if ( ! ewww_image_optimizer_cloud_verify() ) {
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
	$payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename( $file ) . '"' . "\r\n";
	$payload .= 'Content-Type: ' . ewww_image_optimizer_mimetype( $file, 'i' ) . "\r\n";
	$payload .= "\r\n";
	$payload .= file_get_contents( $file );
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
		ewww_image_optimizer_debug_log();
	}
	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	$started     = microtime( true );
	if ( false !== strpos( $ewww_status, 'exceeded' ) ) {
		if ( ! ewww_image_optimizer_cloud_verify() ) {
			ewwwio_debug_message( 'cloud verify failed, image not resized' );
			return new WP_Error( 'invalid_key', __( 'Could not verify API key', 'ewww-image-optimizer' ) );
		}
	}
	// Calculate how much time has elapsed since we started.
	$elapsed = microtime( true ) - $started;
	ewwwio_debug_message( "cloud verify took $elapsed seconds" );
	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	if ( ( ! empty( $ewww_status ) && preg_match( '/exceeded/', $ewww_status ) ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_exceeded' ) > time() ) {
		ewwwio_debug_message( 'license exceeded, image not rotated' );
		return new WP_Error( 'invalid_key', __( 'License Exceeded', 'ewww-image-optimizer' ) );
	}
	ewwwio_debug_message( "file: $file " );
	ewwwio_debug_message( "width: $dst_w" );
	ewwwio_debug_message( "height: $dst_h" );
	$api_key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	if ( empty( $api_key ) ) {
		return new WP_Error( 'invalid_key', __( 'Could not verify API key', 'ewww-image-optimizer' ) );
	}
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
	$payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename( $file ) . '"' . "\r\n";
	$payload .= 'Content-Type: ' . $type . "\r\n";
	$payload .= "\r\n";
	$payload .= file_get_contents( $file );
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
			unlink( $tempfile );
			return new WP_Error( 'image_resize_error', $response['error'] );
		} elseif ( false !== strpos( ewww_image_optimizer_mimetype( $tempfile, 'i' ), 'image' ) ) {
			$newsize = filesize( $tempfile );
			ewww_image_optimizer_is_animated( $tempfile );
			ewwwio_debug_message( "API resize success: $newsize (new) vs. $orig_size (original)" );
			unlink( $tempfile );
			return $response['body'];
		}
		unlink( $tempfile );
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
	if ( ! isset( $ewwwdb ) ) {
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

	if ( is_wp_error( $prefix ) ) {
		wp_load_translations_early();
		wp_die(
			sprintf(
				/* translators: 1: $table_prefix 2: wp-config.php */
				__( '<strong>ERROR</strong>: %1$s in %2$s can only contain numbers, letters, and underscores.' ),
				'<code>$table_prefix</code>',
				'<code>wp-config.php</code>'
			)
		);
	}
	if ( ! isset( $ewwwdb->ewwwio_images ) ) {
		$ewwwdb->ewwwio_images = $ewwwdb->prefix . 'ewwwio_images';
	}
}

/**
 * Inserts multiple records into the table at once.
 *
 * Each sub-array in $images should have the same number of items as $format.
 *
 * @global object $ewwwdb A new database connection with super powers.
 *
 * @param string $table The table to insert records into.
 * @param array  $images Can be any multi-dimensional array with records to insert.
 * @param array  $format A list of formats for the values in each record of $images.
 */
function ewww_image_optimizer_mass_insert( $table, $images, $format ) {
	if ( empty( $table ) || ! ewww_image_optimizer_iterable( $images ) || ! ewww_image_optimizer_iterable( $format ) ) {
		return false;
	}
	ewww_image_optimizer_db_init();
	global $ewwwdb;
	$ewwwdb->insert_multiple( $table, $images, $format );
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
	global $s3_uploads_image;
	global $ewww_image;
	if ( class_exists( 'S3_Uploads' ) && ! empty( $s3_uploads_image ) && $s3_uploads_image !== $file ) {
		$file = $s3_uploads_image;
		ewwwio_debug_message( "overriding check with: $file" );
	}
	$image = array();
	if ( ! is_object( $ewww_image ) || ! $ewww_image instanceof EWWW_Image || $ewww_image->file !== $file ) {
		$ewww_image = new EWWW_Image( 0, '', $file );
	}
	if ( ! empty( $ewww_image->record ) ) {
		$image = $ewww_image->record;
	} else {
		$image = false;
	}
	if ( is_array( $image ) && $image['image_size'] === $orig_size ) {
		$prev_string = ' - ' . __( 'Previously Optimized', 'ewww-image-optimizer' );
		if ( preg_match( '/' . __( 'License exceeded', 'ewww-image-optimizer' ) . '/', $image['results'] ) ) {
			return '';
		}
		$already_optimized = preg_replace( "/$prev_string/", '', $image['results'] );
		$already_optimized = $already_optimized . $prev_string;
		ewwwio_debug_message( "already optimized: {$image['path']} - $already_optimized" );
		ewwwio_memory( __FUNCTION__ );
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
		return $already_optimized;
	}
	return '';
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
		$converted         = $original;
	} else {
		global $s3_uploads_image;
		if ( class_exists( 'S3_Uploads' ) && ! empty( $s3_uploads_image ) && $s3_uploads_image !== $attachment ) {
			$attachment = $s3_uploads_image;
			ewwwio_debug_message( "overriding update with: $attachment" );
		}
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
		$updates['updated']   = gmdate( 'Y-m-d H:i:s' );
		$ewwwdb->insert( $ewwwdb->ewwwio_images, $updates );
	} else {
		if ( is_array( $already_optimized ) && empty( $already_optimized['orig_size'] ) ) {
			$updates['orig_size'] = $orig_size;
		}
		ewwwio_debug_message( "updating existing record ({$already_optimized['id']}), path: $attachment, size: $opt_size" );
		if ( $already_optimized['updates'] ) {
			$updates['updates'] = $already_optimized['updates']++;
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
	if ( ! $auto && ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) ) {
		$output['error'] = esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' );
		wp_die( ewwwio_json_encode( $output ) );
	}
	session_write_close();
	if ( ! empty( $_REQUEST['ewww_wpnonce'] ) ) {
		// Find out if our nonce is on it's last leg/tick.
		$tick = wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' );
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
	if ( ewww_image_optimizer_stl_check() && ini_get( 'max_execution_time' ) < 60 ) {
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
	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	if ( ! empty( $ewww_status ) && preg_match( '/exceeded/', $ewww_status ) ) {
		if ( ! $auto ) {
			$output['error'] = esc_html__( 'License Exceeded', 'ewww-image-optimizer' );
			echo ewwwio_json_encode( $output );
		}
		if ( $cli ) {
			WP_CLI::error( __( 'License Exceeded', 'ewww-image-optimizer' ) );
		}
		wp_die();
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
			number_format_i18n( $elapsed )
		);
		if ( get_site_option( 'ewww_image_optimizer_debug' ) ) {
			global $eio_debug;
			$output['results'] .= '<div style="background-color:#f1f1f1;">' . $eio_debug . '</div>';
		}
		$next_file = ewww_image_optimizer_absolutize_path( $wpdb->get_var( "SELECT path FROM $wpdb->ewwwio_images WHERE pending=1 LIMIT 1" ) );
		if ( ! empty( $next_file ) ) {
			$loading_image       = plugins_url( '/images/wpspin.gif', __FILE__ );
			$output['next_file'] = '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . ' <b>' . esc_html( $next_file ) . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
		}
		echo ewwwio_json_encode( $output );
		ewwwio_memory( __FUNCTION__ );
		wp_die();
	}
	if ( $cli ) {
		return $results[1];
	}
	ewwwio_memory( __FUNCTION__ );
}

/**
 * Looks for a retina version of the original file so that we can optimize that too.
 *
 * @global object $ewww_image Contains more information about the image currently being processed.
 *
 * @param string $orig_path Filename of the 'normal' image.
 * @param bool   $return_path True returns the path of the retina image and skips optimization.
 * @param bool   $validate_file True verifies the file exists.
 * @return string The filename of the retina image.
 */
function ewww_image_optimizer_hidpi_optimize( $orig_path, $return_path = false, $validate_file = true ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$hidpi_suffix = apply_filters( 'ewww_image_optimizer_hidpi_suffix', '@2x' );
	$pathinfo     = pathinfo( $orig_path );
	if ( empty( $pathinfo['dirname'] ) || empty( $pathinfo['filename'] ) || empty( $pathinfo['extension'] ) ) {
		return;
	}
	$hidpi_path = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . $hidpi_suffix . '.' . $pathinfo['extension'];
	if ( $validate_file && ! ewwwio_is_file( $hidpi_path ) ) {
		return;
	}
	if ( $return_path ) {
		ewwwio_debug_message( "found retina at $hidpi_path" );
		return $hidpi_path;
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
 * Cleanup temp file for optimizing S3 Uploads image.
 *
 * @param string $file The filename of the temp file.
 * @return string The name of the file unaltered, or the s3 filename stored in $s3_uploads_image.
 */
function ewww_image_optimizer_s3_uploads_image_cleanup( $file ) {
	global $s3_uploads_image;
	if ( ! ewww_image_optimizer_stream_wrapped( $file ) && strpos( $file, 's3-uploads' ) === false && ! empty( $s3_uploads_image ) ) {
		if ( ewwwio_is_file( $file ) ) {
			unlink( $file );
		}
		$file = $s3_uploads_image;
		unset( $s3_uploads_image );
	}
	return $file;
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
			rename( $temp_file, $filename );
		}
		// Resized versions, so we'll grab those too.
		if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
			$disabled_sizes = get_option( 'ewww_image_optimizer_disable_resizes_opt' );
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
					$resize_path = $base_dir . $data['file'];
					$resize_url  = $data['gs_link'];
					ewwwio_debug_message( "fetching $resize_url to $resize_path" );
					$temp_file = download_url( $resize_url );
					if ( ! is_wp_error( $temp_file ) ) {
						if ( ! is_dir( dirname( $resize_path ) ) ) {
							wp_mkdir_p( dirname( $resize_path ) );
						}
						rename( $temp_file, $resize_path );
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
			if ( ! is_dir( dirname( $filename ) ) ) {
				wp_mkdir_p( dirname( $filename ) );
			}
			rename( $temp_file, $filename );
		}
		// Resized versions, so we'll grab those too.
		if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
			$disabled_sizes = get_option( 'ewww_image_optimizer_disable_resizes_opt' );
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
					$resize_path = $base_dir . $data['file'];
					$resize_url  = $as3cf->get_attachment_url( $id, null, $size, $meta );
					ewwwio_debug_message( "fetching $resize_url to $resize_path" );
					$temp_file = download_url( $resize_url );
					if ( ! is_wp_error( $temp_file ) ) {
						if ( ! is_dir( dirname( $resize_path ) ) ) {
							wp_mkdir_p( dirname( $resize_path ) );
						}
						rename( $temp_file, $resize_path );
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
		ewwwio_debug_message( "azure fullsize url: $full_url" );
		ewwwio_debug_message( "fullsize path: $filename" );
		$temp_file = download_url( $full_url );
		if ( ! is_wp_error( $temp_file ) ) {
			if ( ! is_dir( dirname( $filename ) ) ) {
				wp_mkdir_p( dirname( $filename ) );
			}
			rename( $temp_file, $filename );
		}
		// Resized versions, so we'll grab those too.
		if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
			$disabled_sizes = get_option( 'ewww_image_optimizer_disable_resizes_opt' );
			ewwwio_debug_message( 'retrieving resizes' );
			// Meta sizes don't contain a path, so we calculate one.
			$base_dir = trailingslashit( dirname( $filename ) );
			$base_url = trailingslashit( dirname( $full_url ) );
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
					$resize_path = $base_dir . $data['file'];
					$resize_url  = $base_url . $data['file'];
					ewwwio_debug_message( "fetching $resize_url to $resize_path" );
					$temp_file = download_url( $resize_url );
					if ( ! is_wp_error( $temp_file ) ) {
						if ( ! is_dir( dirname( $resize_path ) ) ) {
							wp_mkdir_p( dirname( $resize_path ) );
						}
						rename( $temp_file, $resize_path );
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
		return $filename;
	} else {
		return false;
	}
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
	if ( 'image/png' !== $type ) {
		ewwwio_debug_message( 'not a PNG, no conversion needed' );
		return;
	}
	$orig_size = ewww_image_optimizer_filesize( $file );
	if ( $orig_size < apply_filters( 'ewww_image_optimizer_autoconvert_threshold', 300000 ) ) {
		ewwwio_debug_message( 'not a large PNG, skipping' );
		return;
	}
	if ( ewww_image_optimizer_png_alpha( $file ) && ! ewww_image_optimizer_jpg_background() ) {
		ewwwio_debug_message( 'alpha detected, skipping' );
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
	if ( ! $file ) {
		return false;
	}
	if ( ! empty( $_REQUEST['ewww_webp_only'] ) ) {
		return false;
	}
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'image' );
	}
	if ( ! empty( $_REQUEST['post_id'] ) || ( ! empty( $_REQUEST['action'] ) && 'upload-attachment' === $_REQUEST['action'] ) || ( ! empty( $_SERVER['HTTP_REFERER'] ) && strpos( $_SERVER['HTTP_REFERER'], 'media-new.php' ) ) ) {
		ewwwio_debug_message( 'resizing image from media library or attached to post' );
		$maxwidth  = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' );
		$maxheight = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' );
	} elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) && strpos( $_SERVER['HTTP_REFERER'], '/post.php' ) ) {
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
	if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		ewwwio_debug_message( 'uploaded from: ' . $_SERVER['HTTP_REFERER'] );
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
	// Check the file type.
	$type = ewww_image_optimizer_mimetype( $file, 'i' );
	if ( strpos( $type, 'image' ) === false ) {
		ewwwio_debug_message( 'not an image, cannot resize' );
		return false;
	}
	// Check file size (dimensions).
	list( $oldwidth, $oldheight ) = getimagesize( $file );
	if ( $oldwidth <= $maxwidth && $oldheight <= $maxheight ) {
		ewwwio_debug_message( 'image too small for resizing' );
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
	if ( ! ewwwio_check_memory_available( ( $oldwidth * $oldwidth + $newwidth * $newheight ) * 4.8 ) ) { // 4.8 = 24-bit or 3 bytes per pixel multiplied by a factor of 1.6 for extra wiggle room.
		$memory_required = ( $oldwidth * $oldwidth + $newwidth * $newheight ) * 4.8;
		ewwwio_debug_message( "possibly insufficient memory for resizing operation: $memory_required" );
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			add_filter( 'image_memory_limit', 'ewww_image_optimizer_raise_memory_limit' );
			wp_raise_memory_limit( 'image' );
		}
		ewww_image_optimizer_debug_log();
	}
	if ( ! function_exists( 'wp_get_image_editor' ) ) {
		ewwwio_debug_message( 'no image editor function' );
		return false;
	}

	// From here...
	global $ewww_preempt_editor;
	$ewww_preempt_editor = true;

	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) {
		ewwwio_debug_message( 'could not get image editor' );
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
		return false;
	}
	$new_file  = $editor->generate_filename( 'tmp' );
	$orig_size = filesize( $file );
	ewwwio_debug_message( "before resizing: $orig_size" );
	$saved = $editor->save( $new_file );
	if ( is_wp_error( $saved ) ) {
		$error_message = $saved->get_error_message();
		ewwwio_debug_message( "error saving resized image: $error_message" );
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_noauto' ) && ( ! defined( 'EWWW_IMAGE_OPTIMIZER_DISABLE_EDITOR' ) || ! EWWW_IMAGE_OPTIMIZER_DISABLE_EDITOR ) ) {
		$ewww_preempt_editor = false;
	}
	// to here is replaced by cloud/API function.
	$new_size = ewww_image_optimizer_filesize( $new_file );
	if ( apply_filters( 'ewww_image_optimizer_resize_filesize_ignore', false ) || ( $new_size && $new_size < $orig_size ) ) {
		// Use this action to perform any operations on the original file before it is overwritten with the new, smaller file.
		do_action( 'ewww_image_optimizer_image_resized', $file, $new_file );
		ewwwio_debug_message( "after resizing: $new_size" );
		// TODO: see if there is a way to just check that meta exists on the new image.
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
		rename( $new_file, $file );
		// Store info on the current image for future reference.
		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		$already_optimized = ewww_image_optimizer_find_already_optimized( $file );
		// If the original file has never been optimized, then just update the record that was created with the proper filename (because the resized file has usually been optimized).
		if ( empty( $already_optimized ) ) {
			$tmp_exists = $ewwwdb->update(
				$ewwwdb->ewwwio_images,
				array(
					'path'      => ewww_image_optimizer_relativize_path( $file ),
					'orig_size' => $orig_size,
				),
				array(
					'path' => ewww_image_optimizer_relativize_path( $new_file ),
				)
			);
			// If the tmp file didn't get optimized (and it shouldn't), then just insert a dummy record to be updated shortly.
			if ( ! $tmp_exists ) {
				$ewwwdb->insert(
					$ewwwdb->ewwwio_images,
					array(
						'path'      => ewww_image_optimizer_relativize_path( $file ),
						'orig_size' => $orig_size,
					)
				);
			}
		} else {
			// Otherwise, we delete the record created from optimizing the resized file.
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
		}
		return array( $newwidth, $newheight );
	} // End if().
	if ( ewwwio_is_file( $new_file ) ) {
		ewwwio_debug_message( "resizing did not create a smaller image: $new_size" );
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
	if ( function_exists( 'exif_read_data' ) && 'image/jpeg' === $type && is_readable( $file ) ) {
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
	if ( defined( 'EWWW_DISABLE_ASYNC' ) && EWWW_DISABLE_ASYNC ) {
		return false;
	}
	if ( ! ewww_image_optimizer_function_exists( 'sleep' ) ) {
		return false;
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_background_optimization' ) ) {
		return false;
	}
	if ( ewww_image_optimizer_detect_wpsf_location_lock() ) {
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
	if ( ! empty( $_REQUEST['ewww_convert'] ) ) {
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
 * Find image paths from an attachment's meta data and process each image.
 *
 * Called after `wp_generate_attachment_metadata` is completed, it also searches for retina images,
 * and a few custom theme resizes. When a new image is uploaded, it is added to the queue, if
 * possible, and then this same function is run in the background. Optionally, it will use parallel
 * (async) requests to speed up the process.
 *
 * @global object $wpdb
 * @global object $ewwwdb A clone of $wpdb unless it is lacking utf8 connectivity.
 * @global bool $ewww_defer True if optimization should be deferred until later.
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
	global $ewww_defer;
	global $ewww_new_image;
	global $ewww_image;
	$gallery_type = 1;
	ewwwio_debug_message( "attachment id: $id" );

	session_write_close();
	if ( ! empty( $ewww_new_image ) ) {
		ewwwio_debug_message( 'this is a newly uploaded image with no metadata yet' );
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
	if ( ! ewwwio_is_file( $file_path ) || ( ewww_image_optimizer_stream_wrapped( $file_path ) && ! class_exists( 'S3_Uploads' ) ) ) {
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
	);
	if ( ! in_array( $type, $supported_types, true ) ) {
		ewwwio_debug_message( "mimetype not supported: $id" );
		return $meta;
	}
	$fullsize_size = ewww_image_optimizer_filesize( $file_path );
	// See if this is a new image and Imsanity resized it (which means it could be already optimized).
	if ( ! empty( $new_image ) && function_exists( 'imsanity_get_max_width_height' ) && strpos( $type, 'image' ) !== false ) {
		list( $maxw, $maxh ) = imsanity_get_max_width_height( IMSANITY_SOURCE_LIBRARY );
		list( $oldw, $oldh ) = getimagesize( $file_path );
		list( $neww, $newh ) = wp_constrain_dimensions( $oldw, $oldh, $maxw, $maxh );
		$path_parts          = pathinfo( $file_path );
		$imsanity_path       = trailingslashit( $path_parts['dirname'] ) . $path_parts['filename'] . '-' . $neww . 'x' . $newh . '.' . $path_parts['extension'];
		ewwwio_debug_message( "imsanity path: $imsanity_path" );
		$image_size        = ewww_image_optimizer_filesize( $file_path );
		$already_optimized = ewww_image_optimizer_find_already_optimized( $imsanity_path );
		if ( is_array( $already_optimized ) ) {
			ewwwio_debug_message( "updating existing record, path: $file_path, size: " . $image_size );
			// Store info on the current image for future reference.
			$ewwwdb->update(
				$ewwwdb->ewwwio_images,
				array(
					'path'          => ewww_image_optimizer_relativize_path( $file_path ),
					'attachment_id' => $id,
					'resize'        => 'full',
					'gallery'       => 'media',
				),
				array(
					'id' => $already_optimized['id'],
				)
			);
		}
	}

	// Initialize an EWWW_Image object for the full-size image so that the original will be backed up before any potential resizing operations.
	$ewww_image         = new EWWW_Image( $id, 'media', $file_path );
	$ewww_image->resize = 'full';

	// Resize here so long as this is not a new image AND resize existing is enabled, and imsanity isn't enabled with a max size.
	if ( ( empty( $new_image ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_existing' ) ) && ! function_exists( 'imsanity_get_max_width_height' ) ) {
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
				'id'   => $id,
				'new'  => $new_image,
				'type' => $type,
			)
		);
		if ( 5 > $ewwwio_media_background->count_queue() ) {
			$ewwwio_media_background->save()->dispatch();
			ewwwio_debug_message( 'small queue, dispatching post-haste' );
		} else {
			ewwwio_debug_message( 'detected queued items in progress, saving without dispatch' );
			$ewwwio_media_background->save();
		}
		set_transient( 'ewwwio-background-in-progress-' . $id, true, 24 * HOUR_IN_SECONDS );
		if ( $log ) {
			ewww_image_optimizer_debug_log();
		}
		return $meta;
	}
	if ( $background_new ) {
		$new_image = true;
	}
	// Resize here if the user has used the filter to defer resizing, we have a new image OR resize existing is enabled, and imsanity isn't enabled with a max size.
	if ( apply_filters( 'ewww_image_optimizer_defer_resizing', false ) && ( ! empty( $new_image ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_existing' ) ) && ! function_exists( 'imsanity_get_max_width_height' ) ) {
		$new_dimensions = ewww_image_optimizer_resize_upload( $file_path );
		if ( is_array( $new_dimensions ) ) {
			$meta['width']  = $new_dimensions[0];
			$meta['height'] = $new_dimensions[1];
		}
	}
	// This gets a bit long, so here goes:
	// we run in parallel if we didn't detect breakage (test_parallel_opt), and there are enough resizes to make it worthwhile (or if the API is enabled).
	if ( ewww_image_optimizer_test_parallel_opt( $type, $id ) && isset( $meta['sizes'] ) && ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) || count( $meta['sizes'] ) > 5 ) ) {
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

		if ( false === $file ) {
			return $meta;
		}
		// If the file was converted.
		if ( false !== $conv ) {
			if ( $conv ) {
				$ewww_image->increment = $conv;
			}
			$ewww_image->file      = $file;
			$ewww_image->converted = $original;
			$meta['file']          = trailingslashit( dirname( $meta['file'] ) ) . basename( $file );
			$ewww_image->update_converted_attachment( $meta );
			$meta = $ewww_image->convert_sizes( $meta );
			ewwwio_debug_message( 'image was converted' );
		} else {
			remove_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment', 10 );
		}
		ewww_image_optimizer_hidpi_optimize( $file );
	}

	// See if we are forcing re-optimization per the user's request.
	if ( ! empty( $_REQUEST['ewww_force'] ) ) {
		$force = true;
	} else {
		$force = false;
	}

	// Resized versions, so we can continue.
	if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
		$disabled_sizes = get_option( 'ewww_image_optimizer_disable_resizes_opt' );
		ewwwio_debug_message( 'processing resizes' );
		// Meta sizes don't contain a path, so we calculate one.
		if ( 6 === $gallery_type ) {
			$base_ims_dir = trailingslashit( dirname( $file_path ) ) . '_resized/';
		}
		$base_dir = trailingslashit( dirname( $file_path ) );
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
					$meta['sizes'][ $size ]['file'] = $meta['sizes'][ $proc ]['file'];
					continue( 2 );
				}
			}
			// If this is a unique size.
			$resize_path = $base_dir . $data['file'];
			if ( 'application/pdf' === $type && 'full' === $size ) {
				$size = 'pdf-full';
				ewwwio_debug_message( 'processing full size pdf preview' );
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
					$async_path = str_replace( $upload_path, '', $retina_path );
					$ewwwio_async_optimize_media->data(
						array(
							'ewwwio_id'   => $id,
							'ewwwio_path' => $async_path,
							'ewwwio_size' => '',
							'ewww_force'  => $force,
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
				$async_path = str_replace( $upload_path, '', $imagemeta_resize_path );
				$ewwwio_async_optimize_media->data(
					array(
						'ewwwio_id'   => $id,
						'ewwwio_path' => $async_path,
						'ewwwio_size' => '',
						'ewww_force'  => $force,
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
				$async_path = str_replace( $upload_path, '', $custom_size_path );
				$ewwwio_async_optimize_media->data(
					array(
						'ewwwio_id'   => $id,
						'ewwwio_path' => $async_path,
						'ewwwio_size' => '',
						'ewww_force'  => $force,
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
				touch( $filename . '.processing' );
				$async_path = str_replace( $upload_path, '', $filename );
				ewwwio_debug_message( "sending off $async_path in folder $upload_path" );
				$ewwwio_async_optimize_media->data(
					array(
						'ewwwio_id'   => $id,
						'ewwwio_path' => $async_path,
						'ewwwio_size' => $size,
						'ewww_force'  => $force,
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
						unlink( $filename . '.processing' );
					}
				}
				$meta['processing'] = 1;
				if ( $log ) {
					ewww_image_optimizer_debug_log();
				}
				return $meta;
			}
			if ( $log ) {
				ewww_image_optimizer_debug_log();
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
	if ( $fullsize_opt_size && $fullsize_opt_size < $fullsize_size && class_exists( 'Amazon_S3_And_CloudFront' ) ) {
		global $as3cf;
		if ( method_exists( $as3cf, 'wp_update_attachment_metadata' ) ) {
			ewwwio_debug_message( 'deferring to normal S3 hook' );
		} elseif ( method_exists( $as3cf, 'wp_generate_attachment_metadata' ) ) {
			$as3cf->wp_generate_attachment_metadata( $meta, $id );
			ewwwio_debug_message( 'uploading to Amazon S3' );
		}
	}
	if ( $fullsize_opt_size && $fullsize_opt_size < $fullsize_size && class_exists( 'DreamSpeed_Services' ) ) {
		global $dreamspeed;
		$dreamspeed->wp_generate_attachment_metadata( $meta, $id );
		ewwwio_debug_message( 'uploading to Dreamspeed' );
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
 * @param array $meta The attachment metadata generated by WordPress.
 * @param int   $id Optional. The attachment ID number. Default null. Accepts any non-negative integer.
 * @return array $meta Send the metadata back from whence it came.
 */
function ewww_image_optimizer_lr_sync_update( $meta, $id = null ) {
	update_option( 'ewww_image_optimizer_lr_sync', true, false );
	list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
	if ( ewww_image_optimizer_stream_wrapped( $file_path ) || ! ewwwio_is_file( $file_path ) ) {
		return $meta;
	}
	ewwwio_debug_message( "retrieved file path: $file_path" );
	$type            = ewww_image_optimizer_mimetype( $file_path, 'i' );
	$supported_types = array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'application/pdf',
	);
	if ( ! in_array( $type, $supported_types, true ) ) {
		ewwwio_debug_message( "mimetype not supported: $id" );
		return $meta;
	}

	// Get a list of all the image files optimized for this attachment.
	global $wpdb;
	$optimized_images = $wpdb->get_results( $wpdb->prepare( "SELECT id,path,image_size FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'media' AND image_size <> 0 ORDER BY orig_size DESC", $id ), ARRAY_A );
	if ( ! ewww_image_optimizer_iterable( $optimized_images ) ) {
		return $meta;
	}
	foreach ( $optimized_images as $optimized_image ) {
		$file_size = ewww_image_optimizer_filesize( ewww_image_optimizer_absolutize_path( $optimized_image['path'] ) );
		if ( $file_size === (int) $optimized_image['image_size'] ) {
			continue;
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
	return $meta;
}

/**
 * Check to see if Shield's location lock option is enabled.
 *
 * @return bool True if the IP location lock is enabled.
 */
function ewww_image_optimizer_detect_wpsf_location_lock() {
	if ( class_exists( 'ICWP_Wordpress_Simple_Firewall' ) ) {
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
		if ( is_string( $path ) && ewwwio_is_file( $path . '.webp' ) ) {
			$paths[ $size . '-webp' ] = $path . '.webp';
			ewwwio_debug_message( "added $path.webp to as3cf queue" );
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
	if ( preg_match( '/.jpg$/i', basename( $meta['file'] ) ) ) {
		$mime = 'image/jpeg';
	}
	if ( preg_match( '/.png$/i', basename( $meta['file'] ) ) ) {
		$mime = 'image/png';
	}
	if ( preg_match( '/.gif$/i', basename( $meta['file'] ) ) ) {
		$mime = 'image/gif';
	}
	// Update the attachment post with the new mimetype and id.
	wp_update_post(
		array(
			'ID'             => $id,
			'post_mime_type' => $mime,
		)
	);
	ewww_image_optimizer_debug_log();
	ewwwio_memory( __FUNCTION__ );
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
		ewwwio_debug_message( "removing custom relative folder from $file" );
		return str_replace( EWWW_IMAGE_OPTIMIZER_RELATIVE_FOLDER, 'EWWW_IMAGE_OPTIMIZER_RELATIVE_FOLDER', $file );
	}
	if ( strpos( $file, ABSPATH ) === 0 ) {
		ewwwio_debug_message( "removing ABSPATH from $file" );
		return str_replace( ABSPATH, 'ABSPATH', $file );
	}
	if ( defined( 'WP_CONTENT_DIR' ) && WP_CONTENT_DIR && strpos( $file, WP_CONTENT_DIR ) === 0 ) {
		ewwwio_debug_message( "removing WP_CONTENT_DIR from $file" );
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
		ewwwio_debug_message( "replacing custom relative folder in $file" );
		return str_replace( 'EWWW_IMAGE_OPTIMIZER_RELATIVE_FOLDER', EWWW_IMAGE_OPTIMIZER_RELATIVE_FOLDER, $file );
	}
	if ( strpos( $file, 'ABSPATH' ) === 0 ) {
		ewwwio_debug_message( "replacing ABSPATH in $file" );
		return str_replace( 'ABSPATH', ABSPATH, $file );
	}
	if ( defined( 'WP_CONTENT_DIR' ) && WP_CONTENT_DIR && strpos( $file, 'WP_CONTENT_DIR' ) === 0 ) {
		ewwwio_debug_message( "replacing WP_CONTENT_DIR in $file" );
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
	// Determine what color type is stored in the file.
	$color_type = ord( file_get_contents( $filename, null, null, 25, 1 ) );
	ewwwio_debug_message( "color type: $color_type" );
	// If it is set to RGB alpha or Grayscale alpha.
	if ( 4 === $color_type || 6 === $color_type ) {
		ewwwio_debug_message( 'transparency found' );
		return true;
	} elseif ( 3 === $color_type && ewww_image_optimizer_gd_support() ) {
		$image = imagecreatefrompng( $filename );
		if ( imagecolortransparent( $image ) >= 0 ) {
			ewwwio_debug_message( 'transparency found' );
			return true;
		}
		list( $width, $height ) = getimagesize( $filename );
		ewwwio_debug_message( "image dimensions: $width x $height" );
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
 * Check the submitted GIF to see if it is animated
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
 * @param array $defaults A list of columns in the media library.
 * @return array The new list of columns.
 */
function ewww_image_optimizer_columns( $defaults ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$defaults['ewww-image-optimizer'] = esc_html__( 'Image Optimizer', 'ewww-image-optimizer' );
	ewwwio_memory( __FUNCTION__ );
	return $defaults;
}

/**
 * Print column data for optimizer results in the media library.
 *
 * @global object $wpdb
 *
 * @param string $column_name The name of the column being displayed.
 * @param int    $id The attachment ID number.
 * @param array  $meta Optional. The attachment metadata. Default null.
 * @param bool   $return_output Optional. True if output should be returned instead of output.
 * @return string If $return_output, the data that would normally be output directly.
 */
function ewww_image_optimizer_custom_column( $column_name, $id, $meta = null, $return_output = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Once we get to the EWWW IO custom column.
	if ( 'ewww-image-optimizer' === $column_name ) {
		$output = '';
		if ( is_null( $meta ) ) {
			// Retrieve the metadata.
			$meta = wp_get_attachment_metadata( $id );
		}
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ! $return_output && ewww_image_optimizer_function_exists( 'print_r' ) ) {
			$print_meta   = print_r( $meta, true );
			$print_meta   = preg_replace( array( '/ /', '/\n+/' ), array( '&nbsp;', '<br />' ), $print_meta );
			$debug_button = esc_html__( 'Show Metadata', 'ewww-image-optimizer' );
			$output      .= "<button type='button' class='ewww-show-debug-meta button button-secondary' data-id='$id'>$debug_button</button><div id='ewww-debug-meta-$id' style='font-size: 10px;padding: 10px;margin:3px -10px 10px;line-height: 1.1em;display: none;'>$print_meta</div>";
		}
		$output  .= "<div id='ewww-media-status-$id'>";
		$ewww_cdn = false;
		if ( is_array( $meta ) && ! empty( $meta['file'] ) && false !== strpos( $meta['file'], 'https://images-na.ssl-images-amazon.com' ) ) {
			$output .= esc_html__( 'Amazon-hosted image', 'ewww-image-optimizer' ) . '</div>';
			if ( $return_output ) {
				return $output;
			}
			echo $output;
			return;
		}
		if ( is_array( $meta ) && ! empty( $meta['cloudinary'] ) ) {
			$output .= esc_html__( 'Cloudinary image', 'ewww-image-optimizer' ) . '</div>';
			if ( $return_output ) {
				return $output;
			}
			echo $output;
			return;
		}
		if ( is_array( $meta ) & class_exists( 'WindowsAzureStorageUtil' ) && ! empty( $meta['url'] ) ) {
			$output  .= '<div>' . esc_html__( 'Azure Storage image', 'ewww-image-optimizer' ) . '</div>';
			$ewww_cdn = true;
		}
		if ( is_array( $meta ) && class_exists( 'Amazon_S3_And_CloudFront' ) && preg_match( '/^(http|s3|gs)\w*:/', get_attached_file( $id ) ) ) {
			$output  .= '<div>' . esc_html__( 'Offloaded Media', 'ewww-image-optimizer' ) . '</div>';
			$ewww_cdn = true;
		}
		if ( is_array( $meta ) && class_exists( 'S3_Uploads' ) && preg_match( '/^(http|s3|gs)\w*:/', get_attached_file( $id ) ) ) {
			$output  .= '<div>' . esc_html__( 'Amazon S3 image', 'ewww-image-optimizer' ) . '</div>';
			$ewww_cdn = true;
		}
		if ( is_array( $meta ) & class_exists( 'wpCloud\StatelessMedia\EWWW' ) && ! empty( $meta['gs_link'] ) ) {
			$output  .= '<div>' . esc_html__( 'WP Stateless image', 'ewww-image-optimizer' ) . '</div>';
			$ewww_cdn = true;
		}
		list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
		if ( is_array( $meta ) & function_exists( 'ilab_get_image_sizes' ) && ! empty( $meta['s3'] ) && empty( $file_path ) ) {
			$output  .= '<div>' . esc_html__( 'Media Cloud image', 'ewww-image-optimizer' ) . '</div>';
			$ewww_cdn = true;
			if ( $return_output ) {
				return $output;
			}
			echo $output;
			return;
		}
		// If the file does not exist.
		if ( empty( $file_path ) && ! $ewww_cdn ) {
			$output .= esc_html__( 'Could not retrieve file path.', 'ewww-image-optimizer' ) . '</div>';
			ewww_image_optimizer_debug_log();
			if ( $return_output ) {
				return $output;
			}
			echo $output;
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
				if ( ! $skip['jpegtran'] && defined( 'EWWW_IMAGE_OPTIMIZER_JPEGTRAN' ) && ! EWWW_IMAGE_OPTIMIZER_JPEGTRAN ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: name of a tool like jpegtran */
						esc_html__( '%s is missing', 'ewww-image-optimizer' ),
						'<em>jpegtran</em>'
					) . '</div>';
				} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: JPG, PNG, GIF, or PDF */
						esc_html__( '%s compression disabled', 'ewww-image-optimizer' ),
						'JPG'
					) . '</div>';
				} else {
					$convert_link = esc_html__( 'JPG to PNG', 'ewww-image-optimizer' );
					$convert_desc = esc_attr__( 'WARNING: Removes metadata. Requires GD or ImageMagick. PNG is generally much better than JPG for logos and other images with a limited range of colors.', 'ewww-image-optimizer' );
				}
				break;
			case 'image/png':
				// If pngout and optipng are missing and should not be skipped.
				if ( ! $skip['optipng'] && ! $skip['pngout'] && ! EWWW_IMAGE_OPTIMIZER_PNGOUT && ! EWWW_IMAGE_OPTIMIZER_OPTIPNG ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: name of a tool like jpegtran */
						esc_html__( '%s is missing', 'ewww-image-optimizer' ),
						'<em>optipng</em>'
					) . '</div>';
				} else {
					$convert_link = esc_html__( 'PNG to JPG', 'ewww-image-optimizer' );
					$convert_desc = esc_attr__( 'WARNING: This is not a lossless conversion and requires GD or ImageMagick. JPG is much better than PNG for photographic use because it compresses the image and discards data. Transparent images will only be converted if a background color has been set.', 'ewww-image-optimizer' );
				}
				break;
			case 'image/gif':
				// If gifsicle is missing and should not be skipped.
				if ( ! $skip['gifsicle'] && ! EWWW_IMAGE_OPTIMIZER_GIFSICLE ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: name of a tool like jpegtran */
						esc_html__( '%s is missing', 'ewww-image-optimizer' ),
						'<em>gifsicle</em>'
					) . '</div>';
				} else {
					$convert_link = esc_html__( 'GIF to PNG', 'ewww-image-optimizer' );
					$convert_desc = esc_attr__( 'PNG is generally better than GIF, but does not support animation. Animated images will not be converted.', 'ewww-image-optimizer' );
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
			default:
				// Not a supported mimetype.
				$msg = '<div>' . esc_html__( 'Unsupported file type', 'ewww-image-optimizer' ) . '</div>';
				ewww_image_optimizer_debug_log();
		} // End switch().
		if ( ! empty( $msg ) ) {
			if ( $return_output ) {
				return $msg;
			}
			echo $msg;
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
		// If necessary, use get_post_meta( $post_id, '_wp_attachment_backup_sizes', true ); to only use basename for edited attachments, but that requires extra queries, so kiss for now.
		if ( $ewww_cdn ) {
			if ( get_transient( 'ewwwio-background-in-progress-' . $id ) ) {
				$output     .= '<div>' . esc_html__( 'In Progress', 'ewww-image-optimizer' ) . '</div>';
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
			}
			// If optimizer data exists in the db.
			if ( ! empty( $optimized_images ) ) {
				list( $detail_output, $converted, $backup_available ) = ewww_image_optimizer_custom_column_results( $id, $optimized_images );
				$output .= $detail_output;
				// Output the optimizer actions.
				if ( current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
					// Display a link to re-optimize manually.
					$output .= '<div>' . sprintf(
						"<a class='ewww-manual-optimize' data-id='$id' data-nonce='$ewww_manual_nonce' href=\"admin.php?action=ewww_image_optimizer_manual_optimize&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_force=1&amp;ewww_attachment_ID=%d\">%s</a>",
						$id,
						esc_html__( 'Re-optimize', 'ewww-image-optimizer' )
					) . '</div>';
				}
				if ( $backup_available && current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
					$output .= '<div>' . sprintf(
						"<a class='ewww-manual-cloud-restore' data-id='$id' data-nonce='$ewww_manual_nonce' href=\"admin.php?action=ewww_image_optimizer_manual_cloud_restore&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_attachment_ID=%d\">%s</a>",
						$id,
						esc_html__( 'Restore original', 'ewww-image-optimizer' )
					) . '</div>';
				}
			} elseif ( ! $in_progress && current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				ewww_image_optimizer_migrate_meta_to_db( $id, $meta );
				// Give the user the option to optimize the image right now.
				if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
					$sizes_to_opt = ewww_image_optimizer_count_unoptimized_sizes( $meta['sizes'] ) + 1;
					if ( isset( $meta['original_image'] ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_originals' ) ) {
						$sizes_to_opt++;
					}
					$output .= '<div>' . sprintf(
						esc_html(
							/* translators: %d: The number of resize/thumbnail images */
							_n( '%d size to compress', '%d sizes to compress', $sizes_to_opt, 'ewww-image-optimizer' )
						),
						$sizes_to_opt
					) . '</div>';
				}
				$output .= '<div>' . sprintf( "<a class='ewww-manual-optimize' data-id='$id' data-nonce='$ewww_manual_nonce' href=\"admin.php?action=ewww_image_optimizer_manual_optimize&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_attachment_ID=%d\">%s</a>", $id, esc_html__( 'Optimize now!', 'ewww-image-optimizer' ) ) . '</div>';
			}
			$output .= '</div>';
			if ( $return_output ) {
				return $output;
			}
			echo $output;
			return;
		} // End if().
		// End of output for CDN images.
		if ( get_transient( 'ewwwio-background-in-progress-' . $id ) ) {
			$output     .= esc_html__( 'In Progress', 'ewww-image-optimizer' );
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
		}
		// If optimizer data exists.
		if ( ! empty( $optimized_images ) ) {
			list( $detail_output, $converted, $backup_available ) = ewww_image_optimizer_custom_column_results( $id, $optimized_images );
			$output .= $detail_output;

			// Link to webp upgrade script.
			$oldwebpfile = preg_replace( '/\.\w+$/', '.webp', $file_path );
			if ( file_exists( $oldwebpfile ) && current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
				$output .= "<div><a href='options.php?page=ewww-image-optimizer-webp-migrate'>" . esc_html__( 'Run WebP upgrade', 'ewww-image-optimizer' ) . '</a></div>';
			}

			// Determine filepath for webp.
			$webpfile  = $file_path . '.webp';
			$webp_size = ewww_image_optimizer_filesize( $webpfile );
			if ( $webp_size ) {
				// Get a human readable filesize.
				$webp_size = ewww_image_optimizer_size_format( $webp_size );
				$webpurl   = esc_url( wp_get_attachment_url( $id ) . '.webp' );
				$output   .= "<div>WebP: <a href='$webpurl'>$webp_size</a></div>";
			}

			if ( empty( $msg ) && current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				// Output a link to re-optimize manually.
				$output .= '<div>' . sprintf(
					"<a class='ewww-manual-optimize' data-id='$id' data-nonce='$ewww_manual_nonce' href=\"admin.php?action=ewww_image_optimizer_manual_optimize&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_force=1&amp;ewww_attachment_ID=%d\">%s</a>",
					$id,
					esc_html__( 'Re-optimize', 'ewww-image-optimizer' )
				);
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_convert_links' ) && 'ims_image' !== get_post_type( $id ) && ! empty( $convert_desc ) ) {
					$output .= " | <a class='ewww-manual-convert' data-id='$id' data-nonce='$ewww_manual_nonce' title='$convert_desc' href='admin.php?action=ewww_image_optimizer_manual_optimize&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_attachment_ID=$id&amp;ewww_convert=1&amp;ewww_force=1'>$convert_link</a>";
				}
				$output .= '</div>';
			} else {
				$output .= $msg;
			}
			$restorable = false;
			if ( $converted && ewwwio_is_file( $converted ) ) {
				$restorable = true;
			}
			if ( $restorable && current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				$output .= '<div>' . sprintf(
					"<a class='ewww-manual-restore' data-id='$id' data-nonce='$ewww_manual_nonce' href=\"admin.php?action=ewww_image_optimizer_manual_restore&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_attachment_ID=%d\">%s</a>",
					$id,
					esc_html__( 'Restore original', 'ewww-image-optimizer' )
				) . '</div>';
			} elseif ( $backup_available && current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				$output .= '<div>' . sprintf(
					"<a class='ewww-manual-cloud-restore' data-id='$id' data-nonce='$ewww_manual_nonce' href=\"admin.php?action=ewww_image_optimizer_manual_cloud_restore&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_attachment_ID=%d\">%s</a>",
					$id,
					esc_html__( 'Restore original', 'ewww-image-optimizer' )
				) . '</div>';
			}
		} elseif ( ! $in_progress ) {
			// Otherwise, this must be an image we haven't processed.
			if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
				$sizes_to_opt = ewww_image_optimizer_count_unoptimized_sizes( $meta['sizes'] ) + 1;
				if ( isset( $meta['original_image'] ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_originals' ) ) {
					$sizes_to_opt++;
				}
				$output .= '<div>' . sprintf(
					esc_html(
						/* translators: %d: The number of resize/thumbnail images */
						_n( '%d size to compress', '%d sizes to compress', $sizes_to_opt, 'ewww-image-optimizer' )
					),
					$sizes_to_opt
				) . '</div>';
			} else {
				$output .= '<div>' . esc_html__( 'Not optimized', 'ewww-image-optimizer' ) . '</div>';
			}
			// Tell them the filesize.
			$output .= '<div>' . sprintf(
				/* translators: %s: size of the image */
				esc_html__( 'Image Size: %s', 'ewww-image-optimizer' ),
				$file_size
			) . '</div>';
			// Determine filepath for webp.
			$webpfile  = $file_path . '.webp';
			$webp_size = ewww_image_optimizer_filesize( $webpfile );
			if ( $webp_size ) {
				// Get a human readable filesize.
				$webp_size = ewww_image_optimizer_size_format( $webp_size );
				$webpurl   = esc_url( wp_get_attachment_url( $id ) . '.webp' );
				$output   .= "<div>WebP: <a href='$webpurl'>$webp_size</a></div>";
			}
			if ( empty( $msg ) && current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				// Give the user the option to optimize the image right now.
				$output .= sprintf( "<div><a class='ewww-manual-optimize' data-id='$id' data-nonce='$ewww_manual_nonce' href=\"admin.php?action=ewww_image_optimizer_manual_optimize&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_attachment_ID=%d\">%s</a>", $id, esc_html__( 'Optimize now!', 'ewww-image-optimizer' ) );
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_convert_links' ) && 'ims_image' !== get_post_type( $id ) && ! empty( $convert_desc ) ) {
					$output .= " | <a class='ewww-manual-convert' data-id='$id' data-nonce='$ewww_manual_nonce' title='$convert_desc' href='admin.php?action=ewww_image_optimizer_manual_optimize&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_attachment_ID=$id&amp;ewww_convert=1&amp;ewww_force=1'>$convert_link</a>";
				}
				$output .= '</div>';
			} else {
				$output .= $msg;
			}
		} // End if().
		$output .= '</div>';
		if ( $return_output ) {
			ewww_image_optimizer_debug_log();
			return $output;
		}
		echo $output;
	} // End if().
	ewwwio_memory( __FUNCTION__ );
	ewww_image_optimizer_debug_log();
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
	$sizes_to_opt   = 0;
	$disabled_sizes = get_option( 'ewww_image_optimizer_disable_resizes_opt' );

	// To keep track of the ones we have already processed.
	$processed = array();
	foreach ( $sizes as $size => $data ) {
		ewwwio_debug_message( "checking for size: $size" );
		ewww_image_optimizer_debug_log();
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
	$orig_size        = 0;
	$opt_size         = 0;
	$level            = 0;
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
			$level        = $optimized_image['level'];
			$updated_time = strtotime( $optimized_image['updated'] );
			if ( DAY_IN_SECONDS * 30 + $updated_time > time() ) {
				$backup_available = $optimized_image['backup'];
			} else {
				$backup_available = '';
			}
		}
		if ( ! empty( $optimized_image['converted'] ) ) {
			$converted = $optimized_image['converted'];
		}
		$sizes_to_opt++;
		if ( ! empty( $optimized_image['resize'] ) ) {
			$display_size   = ewww_image_optimizer_size_format( $optimized_image['image_size'] );
			$detail_output .= '<tr><td><strong>' . ucfirst( $optimized_image['resize'] ) . "</strong></td><td>$display_size</td><td>" . esc_html( ewww_image_optimizer_image_results( $optimized_image['orig_size'], $optimized_image['image_size'] ) ) . '</td></tr>';
		}
	}
	$detail_output .= '</table>';

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
 * Removes optimization from metadata, because we store it all in the images table now.
 *
 * @param array $meta The attachment metadata.
 * @return array The attachment metadata after being cleaned.
 */
function ewww_image_optimizer_clean_meta( $meta ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( is_array( $meta ) && ! empty( $meta['ewww_image_optimizer'] ) ) {
		unset( $meta['ewww_image_optimizer'] );
	}
	if ( is_array( $meta ) && ! empty( $meta['converted'] ) ) {
		unset( $meta['converted'] );
	}
	if ( is_array( $meta ) && ! empty( $meta['orig_file'] ) ) {
		unset( $meta['orig_file'] );
	}
	if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
		foreach ( $meta['sizes'] as $size => $data ) {
			if ( is_array( $data ) && ! empty( $data['ewww_image_optimizer'] ) ) {
				unset( $meta['sizes'][ $size ]['ewww_image_optimizer'] );
			}
			if ( is_array( $data ) && ! empty( $data['converted'] ) ) {
				unset( $meta['sizes'][ $size ]['converted'] );
			}
			if ( is_array( $data ) && ! empty( $data['orig_file'] ) ) {
				unset( $meta['sizes'][ $size ]['orig_file'] );
			}
		}
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
			// $meta = ewww_image_optimizer_clean_meta( $meta );
			// TODO: once we've kicked the tires about a million times, and we're convinced this can't happen in error, then let's clean the meta
			return $meta;
		}
	} elseif ( ! $file_path ) {
		ewwwio_debug_message( 'no file found for attachment' );
		// $meta = ewww_image_optimizer_clean_meta( $meta );
		// TODO: ditto.
		return $meta;
	}
	$converted        = ( is_array( $meta ) && ! empty( $meta['converted'] ) && ! empty( $meta['orig_file'] ) ? trailingslashit( dirname( $file_path ) ) . basename( $meta['orig_file'] ) : false );
	$full_size_update = ewww_image_optimizer_update_file_from_meta( $file_path, 'media', $id, 'full', $converted );
	if ( ! $full_size_update && $bail_early ) {
		ewwwio_debug_message( "bailing early for migration of $id" );
		return false;
	}
	$retina_path = ewww_image_optimizer_hidpi_optimize( $file_path, true, false );
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

			$resize_path = $base_dir . $data['file'];
			$converted   = ( is_array( $data ) && ! empty( $data['converted'] ) && ! empty( $data['orig_file'] ) ? trailingslashit( dirname( $resize_path ) ) . basename( $data['orig_file'] ) : false );
			ewww_image_optimizer_update_file_from_meta( $resize_path, 'media', $id, $size, $converted );
			// Search for retina images.
			if ( function_exists( 'wr2x_get_retina' ) ) {
				$retina_path = wr2x_get_retina( $resize_path );
			} else {
				$retina_path = ewww_image_optimizer_hidpi_optimize( $resize_path, true, false );
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
			$custom_size_path = $custom_sizes_pathinfo['dirname'] . '/' . $custom_size['file'];
			ewww_image_optimizer_update_file_from_meta( $custom_size_path, 'media', $id, 'custom-size-' . $dimensions );
		}
	}
	$meta = ewww_image_optimizer_clean_meta( $meta );
	if ( ewww_image_optimizer_function_exists( 'print_r' ) ) {
		ewwwio_debug_message( print_r( $meta, true ) );
	}
	update_post_meta( $id, '_wp_attachment_metadata', $meta );
	return $meta;
}

/**
 * Dismisses the WC regen notice.
 */
function ewww_image_optimizer_dismiss_wc_regen() {
	ewwwio_ob_clean();
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that the user is properly authorized.
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	delete_option( 'ewww_image_optimizer_wc_regen' );
	wp_die();
}

/**
 * Dismisses the LR sync notice.
 */
function ewww_image_optimizer_dismiss_lr_sync() {
	ewwwio_ob_clean();
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that the user is properly authorized.
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	delete_option( 'ewww_image_optimizer_lr_sync' );
	wp_die();
}

/**
 * Disables the Media Library notice about List Mode.
 */
function ewww_image_optimizer_dismiss_media_notice() {
	ewwwio_ob_clean();
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that the user is properly authorized.
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	update_option( 'ewww_image_optimizer_dismiss_media_notice', true, false );
	update_site_option( 'ewww_image_optimizer_dismiss_media_notice', true );
	wp_die();
}

/**
 * Disables the notice about leaving a review.
 */
function ewww_image_optimizer_dismiss_review_notice() {
	ewwwio_ob_clean();
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that the user is properly authorized.
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	update_option( 'ewww_image_optimizer_dismiss_review_notice', true, false );
	update_site_option( 'ewww_image_optimizer_dismiss_review_notice', true );
	wp_die();
}

/**
 * Load JS in media library footer for bulk actions.
 */
function ewww_image_optimizer_load_admin_js() {
	add_action( 'admin_print_footer_scripts', 'ewww_image_optimizer_add_bulk_actions_via_javascript' );
}

/**
 * Adds a bulk optimize action to the drop-down on the media library page.
 */
function ewww_image_optimizer_add_bulk_actions_via_javascript() {
	// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/ .
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_bulk_permissions', '' ) ) ) {
		return;
	}
	?>
	<script type="text/javascript">
		jQuery(document).ready(function($){
			$('select[name^="action"] option:last-child').before('<option value="bulk_optimize"><?php esc_html_e( 'Bulk Optimize', 'ewww-image-optimizer' ); ?></option>');
			$('.ewww-manual-convert').tooltip();
		});
	</script>
	<?php
}

/**
 * Handles the bulk actions POST.
 */
function ewww_image_optimizer_bulk_action_handler() {
	// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/ .
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// If the requested action is blank, or not a bulk_optimize, do nothing.
	if ( ( empty( $_REQUEST['action'] ) || 'bulk_optimize' !== $_REQUEST['action'] ) && ( empty( $_REQUEST['action2'] ) || 'bulk_optimize' !== $_REQUEST['action2'] ) ) {
		return;
	}
	// If there is no media to optimize, do nothing.
	if ( empty( $_REQUEST['media'] ) || ! is_array( $_REQUEST['media'] ) ) {
		return;
	}
	// Check the referring page.
	check_admin_referer( 'bulk-media' );
	// Prep the attachment IDs for optimization.
	$ids = implode( ',', array_map( 'intval', $_REQUEST['media'] ) );
	wp_redirect(
		add_query_arg(
			array(
				'page' => 'ewww-image-optimizer-bulk',
				'ids'  => $ids,
			),
			admin_url( 'upload.php' )
		)
	);
	ewwwio_memory( __FUNCTION__ );
	exit();
}

/**
 * Retrieve option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting.
 *
 * Retrieves multi-site and single-site options as appropriate as well as allowing overrides with
 * same-named constant. Overrides are only available for integer and boolean options.
 *
 * @param string $option_name The name of the option to retrieve.
 * @return mixed The value of the option.
 */
function ewww_image_optimizer_get_option( $option_name ) {
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
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) && ! get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) ) {
		$option_value = get_site_option( $option_name );
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
	if ( strpos( $hook, 'settings_page_ewww-image-optimizer' ) !== 0 ) {
		return;
	}
	wp_enqueue_script( 'jquery-ui-tooltip' );
	wp_enqueue_script( 'ewwwbulkscript', plugins_url( '/includes/eio.js', __FILE__ ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION );
	wp_enqueue_style( 'jquery-ui-tooltip-custom', plugins_url( '/includes/jquery-ui-1.10.1.custom.css', __FILE__ ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
	wp_enqueue_script( 'postbox' );
	wp_enqueue_script( 'dashboard' );
	wp_localize_script( 'ewwwbulkscript', 'ewww_vars', array( '_wpnonce' => wp_create_nonce( 'ewww-image-optimizer-settings' ) ) );
	ewwwio_memory( __FUNCTION__ );
	return;
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
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	$total_orig    = 0;
	$total_opt     = 0;
	$total_savings = 0;
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		ewwwio_debug_message( 'querying savings for multi-site' );

		if ( get_blog_count() > 1000 ) {
			// TODO: someday do something more clever than this, maybe.
			return 0;
		}
		if ( function_exists( 'get_sites' ) ) {
			ewwwio_debug_message( 'retrieving list of sites the easy way (4.6+)' );
			$blogs = get_sites(
				array(
					'fields' => 'ids',
					'number' => 1000,
				)
			);
		} elseif ( function_exists( 'wp_get_sites' ) ) {
			ewwwio_debug_message( 'retrieving list of sites the easy way (pre 4.6)' );
			$blogs = wp_get_sites(
				array(
					'network_id' => $wpdb->siteid,
					'limit'      => 1000,
				)
			);
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
				$table_name = $wpdb->prefix . 'ewwwio_images';
				ewwwio_debug_message( "table name is $table_name" );
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
		}
	} else {
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
	} // End if().
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
 * @return bool True if the test image is WebP, false otherwise.
 */
function ewww_image_optimizer_test_webp_mime_verify() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$test_url = plugins_url( '/images/test.png', __FILE__ ) . '?m=' . time();
	add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
	$test_result = wp_remote_get( $test_url, array( 'headers' => 'Accept: image/webp' ) );
	if ( is_wp_error( $test_result ) ) {
		$error_message = $test_result->get_error_message();
		ewwwio_debug_message( "webp verification request failed: $error_message" );
		return false;
	} elseif ( ! empty( $test_result['body'] ) && strlen( $test_result['body'] ) > 300 ) {
		if (
			200 === (int) $test_result['response']['code'] &&
			'52494646' === bin2hex( substr( $test_result['body'], 0, 4 ) )
		) {
			ewwwio_debug_message( 'webp (real-world) verification succeeded' );
			return true;
		}
		ewwwio_debug_message( 'webp mime check failed: ' . bin2hex( substr( $test_result['body'], 0, 3 ) ) );
	}
	if ( ! empty( $test_result['response']['code'] ) && 200 !== (int) $test_result['response']['code'] ) {
		ewwwio_debug_message( 'received response code: ' . $test_result['response']['code'] );
	}
	return false;
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
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-settings' ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	$ewww_rules = ewww_image_optimizer_webp_rewrite_verify();
	if ( $ewww_rules ) {
		if ( insert_with_markers( ewww_image_optimizer_htaccess_path(), 'EWWWIO', $ewww_rules ) && ! ewww_image_optimizer_webp_rewrite_verify() ) {
			esc_html_e( 'Insertion successful', 'ewww-image-optimizer' );
		} else {
			esc_html_e( 'Insertion failed', 'ewww-image-optimizer' );
		}
	}
	wp_die();
}

/**
 * Called via AJAX, removes WebP rewrite rules from the .htaccess file.
 */
function ewww_image_optimizer_webp_unwrite() {
	ewwwio_ob_clean();
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that the user is properly authorized.
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-settings' ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	if ( insert_with_markers( ewww_image_optimizer_htaccess_path(), 'EWWWIO', '' ) ) {
		esc_html_e( 'Removal successful', 'ewww-image-optimizer' );
	} else {
		esc_html_e( 'Removal failed', 'ewww-image-optimizer' );
	}
	wp_die();
}

/**
 * If rules are present, stay silent, otherwise, gives us some rules to insert!
 *
 * @return array Rules to be inserted.
 */
function ewww_image_optimizer_webp_rewrite_verify() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		if ( ewwwio_extract_from_markers( ewww_image_optimizer_htaccess_path(), 'EWWWIO' ) ) {
			ewwwio_debug_message( 'removing htaccess webp to prevent ExactDN problems' );
			insert_with_markers( ewww_image_optimizer_htaccess_path(), 'EWWWIO', '' );
		}
	}
	if ( ewww_image_optimizer_wpfc_webp_enabled() ) {
		return;
	}
	$current_rules = ewwwio_extract_from_markers( ewww_image_optimizer_htaccess_path(), 'EWWWIO' );
	$ewww_rules    = array(
		'<IfModule mod_rewrite.c>',
		'RewriteEngine On',
		'RewriteCond %{HTTP_ACCEPT} image/webp',
		'RewriteCond %{REQUEST_FILENAME} (.*)\.(jpe?g|png)$',
		'RewriteCond %{REQUEST_FILENAME}.webp -f',
		'RewriteCond %{QUERY_STRING} !type=original',
		'RewriteRule (.+)\.(jpe?g|png)$ %{REQUEST_URI}.webp [T=image/webp,E=accept:1,L]',
		'</IfModule>',
		'<IfModule mod_headers.c>',
		'Header append Vary Accept env=REDIRECT_accept',
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
				);
			}
		}
	}
	$sizes['pdf-full'] = array(
		'width'  => 99999,
		'height' => 99999,
	);

	if ( ewww_image_optimizer_function_exists( 'print_r' ) ) {
		ewwwio_debug_message( print_r( $sizes, true ) );
	}
	return $sizes;
}

/**
 * Wrapper that displays the EWWW IO options in the multisite network admin.
 */
function ewww_image_optimizer_network_options() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	add_filter( 'ewww_image_optimizer_settings', 'ewww_image_optimizer_filter_network_settings_page', 9 );
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
	add_filter( 'ewww_image_optimizer_settings', 'ewww_image_optimizer_filter_network_singlesite_settings_page', 9 );
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
	global $wp_version;
	global $ewwwio_temp_debug;
	global $content_width;
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_debug_version_info();
	ewwwio_debug_message( 'ABSPATH: ' . ABSPATH );
	ewwwio_debug_message( 'WP_CONTENT_DIR: ' . WP_CONTENT_DIR );
	ewwwio_debug_message( 'home url: ' . get_home_url() );
	ewwwio_debug_message( 'site url: ' . get_site_url() );
	ewwwio_debug_message( 'content_url: ' . content_url() );
	$upload_info = wp_upload_dir( null, false );
	ewwwio_debug_message( 'upload_dir: ' . $upload_info['basedir'] );
	ewwwio_debug_message( "content_width: $content_width" );
	ewwwio_debug_message( 'registered stream wrappers: ' . implode( ',', stream_get_wrappers() ) );
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_NOEXEC' ) ) {
		if ( defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) && EWWW_IMAGE_OPTIMIZER_CLOUD ) {
			ewww_image_optimizer_disable_tools();
		} else {
			ewww_image_optimizer_tool_init();
			ewww_image_optimizer_notice_utils( 'quiet' );
		}
	}
	$network_class = $network;
	if ( empty( $network ) ) {
		$network_class = 'singlesite';
	}
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( 'debug-silent' !== $network ) {
		global $eio_hs_beacon;
		$eio_hs_beacon->admin_notice( $network_class );
	}
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) && ! get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) ) {
		$network_class = 'network-multisite';
	}
	$output   = array();
	$output[] = "<script type='text/javascript'>\n" .
		'jQuery(document).ready(function($) {$(".fade").fadeTo(5000,1).fadeOut(3000);});' . "\n" .
		"</script>\n";
	$output[] = "<style>\n" .
		".ewww-tab span { font-size: 15px; font-weight: 700; color: #555; text-decoration: none; line-height: 36px; padding: 0 10px; }\n" .
		".ewww-tab span:hover { color: #464646; }\n" .
		".ewww-tab { margin: 0 0 0 5px; padding: 0px; border-width: 1px 1px 1px; border-style: solid solid none; border-image: none; border-color: #ccc; display: inline-block; background-color: #e4e4e4; cursor: pointer }\n" .
		".ewww-tab:hover { background-color: #fff }\n" .
		".ewww-selected { background-color: #f1f1f1; margin-bottom: -1px; border-bottom: 1px solid #f1f1f1 }\n" .
		".ewww-selected span { color: #000; }\n" .
		".ewww-selected:hover { background-color: #f1f1f1; }\n" .
		".ewww-tab-nav { list-style: none; margin: 10px 0 0; padding-left: 5px; border-bottom: 1px solid #ccc; }\n" .
	"</style>\n";
	$output[] = "<div class='wrap'>\n";
	$output[] = "<h1>EWWW Image Optimizer</h1>\n";
	$output[] = "<!--<div id='ewww-container-left' style='float: left; margin-right: 225px;'>-->\n";
	if ( 'network-multisite' === $network ) {
		$bulk_link = esc_html__( 'Media Library', 'ewww-image-optimizer' ) . ' -> ' . esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' );
	} else {
		$bulk_link = '<a href="upload.php?page=ewww-image-optimizer-bulk">' . esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ) . '</a>';
	}
	$s3_link  = '<a href="https://ewww.io/downloads/s3-image-optimizer/">' . esc_html__( 'S3 Image Optimizer', 'ewww-image-optimizer' ) . '</a>';
	$output[] = '<p>' .
		sprintf(
			/* translators: %s: Bulk Optimize (link) */
			esc_html__( 'New images uploaded to the Media Library will be optimized automatically. If you have existing images you would like to optimize, you can use the %s tool.', 'ewww-image-optimizer' ),
			$bulk_link
		) . ewwwio_help_link( 'https://docs.ewww.io/article/4-getting-started', '5853713bc697912ffd6c0b98' ) . ' ' .
		( ! class_exists( 'Amazon_S3_And_CloudFront' ) ?
		sprintf(
			/* translators: %s: S3 Image Optimizer (link) */
			esc_html__( 'Images stored in an Amazon S3 bucket can be optimized using our %s.' ),
			$s3_link
		) : '' ) .
		"</p>\n";

	$compress_score = 0;
	$resize_score   = 0;
	$status_notices = '';

	$compress_recommendations = array();
	$resize_recommendations   = array();

	$status_output = "<div id='ewww-widgets' class='metabox-holder' style='max-width:1170px;'><div class='meta-box-sortables'><div id='ewww-status' class='postbox'>\n" .
		"<h2 class='ewww-hndle'>" . esc_html__( 'Optimization Status', 'ewww-image-optimizer' ) . "</h2>\n<div class='inside'>";

	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		$status_notices .= '<p><b>' . esc_html__( 'Cloud optimization API Key', 'ewww-image-optimizer' ) . ':</b> ';
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_exceeded', 0 );
		$verify_cloud = ewww_image_optimizer_cloud_verify( false );
		if ( false !== strpos( $verify_cloud, 'great' ) ) {
			$compress_score += 30;
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 20 ) {
				$compress_score += 50;
			} else {
				$compress_recommendations[] = esc_html__( 'Enable premium compression for JPG images.', 'ewww-image-optimizer' );
			}
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) > 20 ) {
				$compress_score += 20;
			} else {
				$compress_recommendations[] = esc_html__( 'Enable premium compression for PNG images.', 'ewww-image-optimizer' );
			}
			$status_notices .= '<span style="color: #3eadc9; font-weight: bolder">' . esc_html__( 'Verified,', 'ewww-image-optimizer' ) . ' </span>' . ewww_image_optimizer_cloud_quota();
		} elseif ( false !== strpos( $verify_cloud, 'exceeded' ) ) {
			$status_notices .= '<span style="color: orange; font-weight: bolder">' . esc_html__( 'Out of credits', 'ewww-image-optimizer' ) . '</span> - <a href="https://ewww.io/plans/" target="_blank">' . esc_html__( 'Purchase more', 'ewww-image-optimizer' ) . '</a>';
		} else {
			$status_notices .= '<span style="color: red; font-weight: bolder">' . esc_html__( 'Not Verified', 'ewww-image-optimizer' ) . '</span>';
		}
		if ( false !== strpos( $verify_cloud, 'great' ) ) {
			$status_notices .= ' <a target="_blank" href="https://history.exactlywww.com/show/?api_key=' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) . '">' . esc_html__( 'View Usage', 'ewww-image-optimizer' ) . '</a>';
		}
		$status_notices .= "</p>\n";
		$disable_level   = '';
	} else {
		delete_option( 'ewww_image_optimizer_cloud_key_invalid' );
		if ( ! class_exists( 'ExactDN' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
			$compress_recommendations[] = esc_html__( 'Enable premium compression with an API key or Easy IO.', 'ewww-image-optimizer' );
		}
		$disable_level = "disabled='disabled'";
	}
	$exactdn_enabled = false;
	if ( class_exists( 'Jetpack_Photon' ) && Jetpack::is_module_active( 'photon' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		$status_notices .= '<p><b>Easy IO:</b> <span style="color: red">' . esc_html__( 'Inactive, please disable the Image Performance option on the Jetpack Dashboard.', 'ewww-image-optimizer' ) . '</span></p>';
	} elseif ( get_option( 'easyio_exactdn' ) ) {
		ewww_image_optimizer_webp_rewrite_verify();
		update_option( 'ewww_image_optimizer_exactdn', false );
		update_option( 'ewww_image_optimizer_lazy_load', false );
		update_option( 'ewww_image_optimizer_webp_for_cdn', false );
		$compress_score += 80;
		$resize_score   += 50;
	} elseif ( class_exists( 'ExactDN' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		$status_notices .= '<p><b>Easy IO:</b> ';
		global $exactdn;
		if ( $exactdn->get_exactdn_domain() && $exactdn->verify_domain( $exactdn->get_exactdn_domain() ) ) {
			$status_notices .= '<span style="color: #3eadc9; font-weight: bolder">' . esc_html__( 'Verified', 'ewww-image-optimizer' ) . ' </span>';
			if ( defined( 'WP_ROCKET_VERSION' ) ) {
				$status_notices .= '<br><i>' . esc_html__( 'If you use the File Optimization options within WP Rocket, you should also enter your Easy IO CNAME in the WP Rocket CDN settings (reserved for CSS and Javascript):', 'ewww-image-optimizer' ) . ' ' . $exactdn->get_exactdn_domain() . '</i>';
			}
			if ( $compress_score < 50 ) {
				$compress_score = 50;
			}
			$resize_score += 50;
			if ( ewww_image_optimizer_get_option( 'exactdn_lossy' ) ) {
				$compress_score = 100;
			} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) < 30 ) {
				$compress_recommendations[] = esc_html__( 'Enable premium compression.', 'ewww-image-optimizer' ) . ewwwio_help_link( 'https://docs.ewww.io/article/47-getting-more-from-exactdn', '59de6631042863379ddc953c' );
			}
			$exactdn_enabled = true;
		} elseif ( $exactdn->get_exactdn_domain() && $exactdn->get_exactdn_option( 'verified' ) ) {
			$status_notices .= '<span style="color: orange; font-weight: bolder">' . esc_html__( 'Temporarily disabled.', 'ewww-image-optimizer' ) . ' </span>';
		} elseif ( $exactdn->get_exactdn_domain() && $exactdn->get_exactdn_option( 'suspended' ) ) {
			$status_notices .= '<span style="color: orange; font-weight: bolder">' . esc_html__( 'Active, not yet verified.', 'ewww-image-optimizer' ) . ' </span>';
		} else {
			ewwwio_debug_message( 'could not verify: ' . $exactdn->get_exactdn_domain() );
			$status_notices .= '<span style="color: red; font-weight: bolder"><a href="https://ewww.io/manage-sites/" target="_blank">' . esc_html__( 'Not Verified', 'ewww-image-optimizer' ) . '</a></span>';
		}
		if ( function_exists( 'remove_query_strings_link' ) || function_exists( 'rmqrst_loader_src' ) || function_exists( 'qsr_remove_query_strings_1' ) ) {
			$status_notices .= '<br><i>' . esc_html__( 'Plugins that remove query strings are unnecessary with Easy IO. You may remove them at your convenience.', 'ewww-image-optimizer' ) . '</i>' . ewwwio_help_link( 'https://docs.ewww.io/article/50-exactdn-and-query-strings', '5a3d278a2c7d3a1943677b52' );
		}
		$status_notices .= '</p>';
	} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		$status_notices          .= '<p><b>Easy IO:</b> ' . esc_html__( 'Inactive, enable to activate automatic resizing and more', 'ewww-image-optimizer' ) . '</p>';
		$resize_recommendations[] = esc_html__( 'Enable Easy IO for automatic resizing.', 'ewww-image-optimizer' ) . ewwwio_help_link( 'https://docs.ewww.io/article/44-introduction-to-exactdn', '59bc5ad6042863033a1ce370,5c0042892c7d3a31944e88a4' );
		delete_option( 'ewww_image_optimizer_exactdn_domain' );
		delete_option( 'ewww_image_optimizer_exactdn_failures' );
		delete_option( 'ewww_image_optimizer_exactdn_checkin' );
		delete_option( 'ewww_image_optimizer_exactdn_verified' );
		delete_option( 'ewww_image_optimizer_exactdn_validation' );
		delete_option( 'ewww_image_optimizer_exactdn_suspended' );
		delete_site_option( 'ewww_image_optimizer_exactdn_domain' );
		delete_site_option( 'ewww_image_optimizer_exactdn_failures' );
		delete_site_option( 'ewww_image_optimizer_exactdn_checkin' );
		delete_site_option( 'ewww_image_optimizer_exactdn_verified' );
		delete_site_option( 'ewww_image_optimizer_exactdn_validation' );
		delete_site_option( 'ewww_image_optimizer_exactdn_suspended' );
	}
	if ( $exactdn_enabled && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		$output[] = "<script type='text/javascript'>\n" .
			'var exactdn_enabled = true;' . "\n" .
			"</script>\n";
	} else {
		$output[] = "<script type='text/javascript'>\n" .
			'var exactdn_enabled = false;' . "\n" .
			"</script>\n";
	}
	if (
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' ) ||
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' ) ||
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherwidth' ) ||
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherheight' )
	) {
		$resize_score += 30;
	} elseif ( defined( 'IMSANITY_VERSION' ) ) {
		$resize_score += 30;
	} else {
		$resize_recommendations[] = esc_html__( 'Configure maximum image dimensions in Resize settings.', 'ewww-image-optimizer' ) . ewwwio_help_link( 'https://docs.ewww.io/article/41-resize-settings', '59849911042863033a1ba5f9' );
	}
	$jpg_quality = apply_filters( 'jpeg_quality', 82, 'image_resize' );
	if ( $jpg_quality < 90 && $jpg_quality > 50 ) {
		$resize_score += 20;
	} else {
		$resize_recommendations[] = esc_html__( 'JPG quality level should be between 50 and 90 for optimal resizing.', 'ewww-image-optimizer' ) . ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,58543c69c697912ffd6c19a7' );
	}
	$skip = ewww_image_optimizer_skip_tools();
	if ( ! $skip['jpegtran'] && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
		if ( EWWW_IMAGE_OPTIMIZER_JPEGTRAN ) {
			$jpegtran_installed = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_JPEGTRAN, 'j' );
			if ( ! $jpegtran_installed ) {
				$jpegtran_installed = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_JPEGTRAN, 'jb' );
			}
		}
		if ( ! empty( $jpegtran_installed ) ) {
			$compress_score += 5;
		} else {
			$compress_recommendations[] = esc_html__( 'Install jpegtran.', 'ewww-image-optimizer' ) . ewwwio_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
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
			$compress_score += 5;
		} else {
			$compress_recommendations[] = esc_html__( 'Install optipng.', 'ewww-image-optimizer' ) . ewwwio_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
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
			$compress_score += 5;
		} else {
			$compress_recommendations[] = esc_html__( 'Install pngout', 'ewww-image-optimizer' ) . ': <a href="admin.php?action=ewww_image_optimizer_install_pngout">' . esc_html__( 'automatically', 'ewww-image-optimizer' ) . '</a> | <a href="https://docs.ewww.io/article/13-installing-pngout" data-beacon-article="5854531bc697912ffd6c1afa">' . esc_html__( 'manually', 'ewww-image-optimizer' ) . '</a>';
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
			$compress_score += 5;
		} else {
			$compress_recommendations[] = esc_html__( 'Install pngquant.', 'ewww-image-optimizer' ) . ewwwio_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
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
			$compress_score += 5;
		} else {
			$compress_recommendations[] = esc_html__( 'Install gifsicle.', 'ewww-image-optimizer' ) . ewwwio_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
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
			$compress_score += 5;
		} else {
			$compress_recommendations[] = esc_html__( 'Install webp.', 'ewww-image-optimizer' ) . ewwwio_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
		}
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
	$status_notices .= "<p>\n"; // This line encloses everything up to the end of the async stuff.
	$status_notices .= '<strong>' . esc_html( 'Background and Parallel optimization (faster uploads):', 'ewww-image-optimizer' ) . '</strong><br>';
	if ( defined( 'EWWW_DISABLE_ASYNC' ) && EWWW_DISABLE_ASYNC ) {
		$status_notices .= '<span>' . esc_html__( 'Disabled by administrator', 'ewww-image-optimizer' ) . '</span>';
	} elseif ( ! ewww_image_optimizer_function_exists( 'sleep' ) ) {
		$status_notices .= '<span style="color: orange; font-weight: bolder">' . esc_html__( 'Disabled, sleep function missing', 'ewww-image-optimizer' ) . '</span>';
	} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_background_optimization' ) ) {
		$status_notices .= '<span style="color: orange; font-weight: bolder">' . esc_html__( 'Disabled automatically, async requests blocked', 'ewww-image-optimizer' ) . " - <a href='admin.php?action=ewww_image_optimizer_retest_background_optimization'>" . esc_html__( 'Re-test', 'ewww-image-optimizer' ) . '</a><span>';
	} elseif ( ewww_image_optimizer_detect_wpsf_location_lock() ) {
		$status_notices .= '<span style="color: orange; font-weight: bolder">' . esc_html__( "Disabled by Shield's Lock to Location feature", 'ewww-image-optimizer' ) . '</span>';
	} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_parallel_optimization' ) ) {
		$status_notices .= '<span>' . esc_html__( 'Background mode active, enable Parallel Optimization in Advanced Settings', 'ewww-image-optimizer' ) . '</span>';
	} else {
		$status_notices .= '<span style="color: #3eadc9; font-weight: bolder">' . esc_html__( 'Fully Enabled', 'ewww-image-optimizer' ) . '</span>';
	}
	$status_notices .= "</p>\n";
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		$status_notices .= "<p><a href='https://ewww.io/plans' target='_blank' class='button button-primary' style='background:#3eadc9'>" . esc_html__( 'Premium Upgrades', 'ewww-image-optimizer' ) . "</a></p>\n";
	}

	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ) {
		$compress_score += 5;
	} else {
		$compress_recommendations[] = esc_html__( 'Remove metadata from JPG images.', 'ewww-image-optimizer' ) . ewwwio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2' );
	}

	// Begin building of status inside section.
	$status_output .= '<div class="ewww-row"><ul class="ewww-blocks">';
	$compress_score = min( $compress_score, 100 );
	$resize_score   = min( $resize_score, 100 );

	$guage_stroke_dasharray     = 2 * pi() * 54;
	$compress_stroke_dashoffset = $guage_stroke_dasharray * ( 1 - $compress_score / 100 );
	$resize_stroke_dashoffset   = $guage_stroke_dasharray * ( 1 - $resize_score / 100 );

	$status_output .= '<li><div id="ewww-compress" class="ewww-status-detail">';
	$compress_guage = '<div id="ewww-compress-guage" class="ewww-guage" data-score="' . $compress_score . '">' .
		'<svg width="120" height="120">' .
		'<circle class="ewww-inactive" r="54" cy="60" cx="60" stroke-width="12"/>' .
		'<circle class="ewww-active" r="54" cy="60" cx="60" stroke-width="12" style="stroke-dasharray: ' . $guage_stroke_dasharray . 'px; stroke-dashoffset: ' . $compress_stroke_dashoffset . 'px;"/>' .
		'</svg>' .
		'<div class="ewww-score">' . $compress_score . '%</div>' .
		'</div><!-- end .ewww-guage -->';
	$status_output .= $compress_guage;
	$status_output .= '<div id="ewww-compress-recommend" class="ewww-recommend"><strong>' . ( $compress_score < 100 ? esc_html__( 'How do I get to 100%?', 'ewww-image-optimizer' ) : esc_html__( 'You got the perfect score!', 'ewww-image-optimizer' ) ) . '</strong>';
	if ( $compress_score < 100 ) {
		$status_output .= '<ul class="ewww-tooltip">';
		foreach ( $compress_recommendations as $c_recommend ) {
			$status_output .= "<li>$c_recommend</li>";
		}
		$status_output .= '</ul>';
	}
	$status_output .= '</div><!-- end .ewww-recommend -->';
	$status_output .= '<p><strong>' . esc_html__( 'Compress', 'ewww-image-optimizer' ) . '</strong></p>';
	$status_output .= '<p>' . esc_html__( 'Reduce the file size of your images without affecting quality.', 'ewww-image-optimizer' ) . '</p>';
	$status_output .= '</div><!-- end .ewww-status-detail --></li>';

	$status_output .= '<li><div id="ewww-resize" class="ewww-status-detail">';
	$resize_guage   = '<div id="ewww-resize-guage" class="ewww-guage" data-score="' . $resize_score . '">' .
		'<svg width="120" height="120">' .
		'<circle class="ewww-inactive" r="54" cy="60" cx="60" stroke-width="12"/>' .
		'<circle class="ewww-active" r="54" cy="60" cx="60" stroke-width="12" style="stroke-dasharray: ' . $guage_stroke_dasharray . 'px; stroke-dashoffset: ' . $resize_stroke_dashoffset . 'px;"/>' .
		'</svg>' .
		'<div class="ewww-score">' . $resize_score . '%</div>' .
		'</div><!-- end .ewww-guage -->';
	$status_output .= $resize_guage;
	$status_output .= '<div id="ewww-resize-recommend" class="ewww-recommend"><strong>' . ( $resize_score < 100 ? esc_html__( 'How do I get to 100%?', 'ewww-image-optimizer' ) : esc_html__( 'You got the perfect score!', 'ewww-image-optimizer' ) ) . '</strong>';
	if ( $resize_score < 100 ) {
		$status_output .= '<ul class="ewww-tooltip">';
		foreach ( $resize_recommendations as $r_recommend ) {
			$status_output .= "<li>$r_recommend</li>";
		}
		$status_output .= '</ul>';
	}
	$status_output .= '</div><!-- end .ewww-recommend -->';
	$status_output .= '<p><strong>' . esc_html__( 'Resize', 'ewww-image-optimizer' ) . '</strong></p>';
	$status_output .= '<p>' . esc_html__( 'Scale or reduce the dimensions of your images for more savings.', 'ewww-image-optimizer' ) . '</p>';
	$status_output .= '</div><!-- end .ewww-status-detail --></li>';

	$total_sizes   = ewww_image_optimizer_savings();
	$total_savings = $total_sizes[1] - $total_sizes[0];
	if ( $total_savings > 0 ) {
		$savings_stroke_dashoffset = $guage_stroke_dasharray * ( 1 - $total_savings / $total_sizes[1] );

		$status_output .= '<li><div id="ewww-compress" class="ewww-status-detail">';
		$savings_guage  = '<div id="ewww-savings-guage" class="ewww-guage" data-score="' . $total_savings / $total_sizes[1] . '">' .
			'<svg width="120" height="120">' .
			'<title>' . round( $total_savings / $total_sizes[1], 3 ) * 100 . '%</title>' .
			'<circle class="ewww-inactive" r="54" cy="60" cx="60" stroke-width="12"/>' .
			'<circle class="ewww-active" r="54" cy="60" cx="60" stroke-width="12" style="stroke-dasharray: ' . $guage_stroke_dasharray . 'px; stroke-dashoffset: ' . $savings_stroke_dashoffset . 'px;"/>' .
			'</svg>' .
			'<div class="ewww-score">' . ewww_image_optimizer_size_format( $total_savings, 2 ) . '</div>' .
			'</div><!-- end .ewww-guage -->';
		$status_output .= $savings_guage;
		$status_output .= '<p style="text-align:center"><strong>' . esc_html__( 'Savings', 'ewww-image-optimizer' ) . '</strong></p>';
		if ( 'network-multisite' !== $network ) {
			$status_output .= '<p><a href="tools.php?page=ewww-image-optimizer-tools">' . esc_html__( 'View optimized images.', 'ewww-image-optimizer' ) . '</a></p>';
		}
		$status_output .= '</div><!-- end .ewww-status-detail --></li>';
	}
	ewwwio_debug_message( ewww_image_optimizer_aux_images_table_count() . ' images have been optimized' );

	$status_output .= '<li><div class="ewww-status-detail"><div id="ewww-notices">' . $status_notices . '</div></div></li>';

	$status_output .= '</ul><!-- end .ewww-blocks --></div><!-- end .ewww-row -->';
	$status_output .= '</div><!-- end .inside -->';
	$status_output .= "</div></div></div>\n";

	// End status section.
	$output[] = $status_output;

	if ( ( 'network-multisite' !== $network || ! get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) ) && // Display tabs so long as this isn't the network admin OR single-site override is disabled.
		! ( 'network-singlesite' === $network && ! get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) ) ) { // Also make sure that this isn't single site without override mode.
		$output[] = "<ul class='ewww-tab-nav'>\n" .
			"<li class='ewww-tab ewww-general-nav'><span>" . esc_html__( 'Basic', 'ewww-image-optimizer' ) . "</span></li>\n" .
			( get_option( 'easyio_exactdn' ) ? '' : "<li class='ewww-tab ewww-exactdn-nav'><span>" . esc_html__( 'Easy Mode', 'ewww-image-optimizer' ) . "</span></li>\n" ) .
			"<li class='ewww-tab ewww-optimization-nav'><span>" . esc_html__( 'Advanced', 'ewww-image-optimizer' ) . "</span></li>\n" .
			"<li class='ewww-tab ewww-resize-nav'><span>" . esc_html__( 'Resize', 'ewww-image-optimizer' ) . "</span></li>\n" .
			"<li class='ewww-tab ewww-conversion-nav'><span>" . esc_html__( 'Convert', 'ewww-image-optimizer' ) . "</span></li>\n" .
			"<li class='ewww-tab ewww-webp-nav'><span>" . esc_html__( 'WebP', 'ewww-image-optimizer' ) . "</span></li>\n" .
			"<li class='ewww-tab ewww-overrides-nav'><span><a href='https://docs.ewww.io/article/40-override-options' target='_blank'><span class='ewww-tab-hidden'>" . esc_html__( 'Overrides', 'ewww-image-optimizer' ) . "</a></span></li>\n" .
			"<li class='ewww-tab ewww-support-nav'><span>" . esc_html__( 'Support', 'ewww-image-optimizer' ) . "</span></li>\n" .
			"<li class='ewww-tab ewww-contribute-nav'><span>" . esc_html__( 'Contribute', 'ewww-image-optimizer' ) . "</span></li>\n" .
		"</ul>\n";
	}
	if ( 'network-multisite' === $network ) {
		$output[] = "<form method='post' action=''>\n";
	} else {
		$output[] = "<form method='post' action='options.php'>\n";
	}
	$output[] = "<input type='hidden' name='option_page' value='ewww_image_optimizer_options' />\n";
	$output[] = "<input type='hidden' name='action' value='update' />\n";
	$output[] = wp_nonce_field( 'ewww_image_optimizer_options-options', '_wpnonce', true, false ) . "\n";
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) && ! get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) ) {
		$output[] = '<i class="network-singlesite"><strong>' . esc_html__( 'Configure network-wide settings in the Network Admin.', 'ewww-image-optimizer' ) . "</strong></i>\n";
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_noauto' ) ) {
		ewwwio_debug_message( 'automatic compression disabled' );
	} else {
		ewwwio_debug_message( 'automatic compression enabled' );
	}
	$output[] = "<div id='ewww-general-settings'>\n";
	$output[] = '<noscript><h2>' . esc_html__( 'Basic', 'ewww-image-optimizer' ) . '</h2></noscript>';
	if ( $exactdn_enabled && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		$output[] = '<p>' . esc_html__( 'Easy IO copies your images to our CDN for optimization and does not affect the local images stored on your server. The Basic settings are not necessary for performance while Easy IO is active, but can help you to save server storage space.', 'ewww-image-optimizer' ) . "</p>\n";
	}
	$output[] = "<table class='form-table'>\n";
	if ( is_multisite() ) {
		if ( is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
			$output[] = "<tr class='network-only'><th scope='row'><label for='ewww_image_optimizer_allow_multisite_override'>" . esc_html__( 'Allow Single-site Override', 'ewww-image-optimizer' ) . "</label></th><td><input type='checkbox' id='ewww_image_optimizer_allow_multisite_override' name='ewww_image_optimizer_allow_multisite_override' value='true' " . ( get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) ? "checked='true'" : '' ) . ' /> ' . esc_html__( 'Allow individual sites to configure their own settings and override all network options.', 'ewww-image-optimizer' ) . "</td></tr>\n";
		}
		if ( 'network-multisite' === $network && get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) ) {
			$output[] = "<tr><th scope='row'><label for='ewww_image_optimizer_allow_tracking'>" . esc_html__( 'Allow Usage Tracking?', 'ewww-image-optimizer' ) . "</label></th><td><input type='checkbox' id='ewww_image_optimizer_allow_tracking' name='ewww_image_optimizer_allow_tracking' value='true' " . ( get_site_option( 'ewww_image_optimizer_allow_tracking' ) ? "checked='true'" : '' ) . ' /> ' .
				esc_html__( 'Allow EWWW Image Optimizer to anonymously track how this plugin is used and help us make the plugin better. Opt-in to tracking and receive 500 API image credits free. No sensitive data is tracked.', 'ewww-image-optimizer' ) . "</td></tr>\n";
			$output[] = "<input type='hidden' id='ewww_image_optimizer_allow_multisite_override_active' name='ewww_image_optimizer_allow_multisite_override_active' value='0'>";
			if ( get_site_option( 'ewww_image_optimizer_cloud_key' ) ) {
				$output[] = "<input type='hidden' id='ewww_image_optimizer_cloud_key' name='ewww_image_optimizer_cloud_key' value='" . get_site_option( 'ewww_image_optimizer_cloud_key' ) . "' />\n";
			}
			foreach ( $output as $line ) {
				echo $line;
			}
			echo '</table></div><!-- end container general settings -->';
			echo "<p class='submit'><input type='submit' class='button-primary' value='" . esc_attr__( 'Save Changes', 'ewww-image-optimizer' ) . "' /></p>\n";
			echo '</form></div><!-- end container wrap -->';
			ewww_image_optimizer_temp_debug_clear();
			return;
		}
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_cloud_notkey'>" . esc_html__( 'Optimization API Key', 'ewww-image-optimizer' ) . "</label></th><td><input type='text' id='ewww_image_optimizer_cloud_notkey' name='ewww_image_optimizer_cloud_notkey' readonly='readonly' value='****************************" . substr( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ), 28 ) . "' size='32' /> <a href='admin.php?action=ewww_image_optimizer_remove_cloud_key'>" . esc_html__( 'Remove API key', 'ewww-image-optimizer' ) . "</a></td></tr>\n";
		$output[] = "<input type='hidden' id='ewww_image_optimizer_cloud_key' name='ewww_image_optimizer_cloud_key' value='" . ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) . "' />\n";
	} else {
		$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_cloud_key'>" . esc_html__( 'Optimization API Key', 'ewww-image-optimizer' ) . '</label>' . ewwwio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2,5ad0c8e7042863075092650b,5a9efec62c7d3a7549516550' ) . "</th><td><input type='text' id='ewww_image_optimizer_cloud_key' name='ewww_image_optimizer_cloud_key' value='' size='32' /> " . esc_html__( 'API Key will be validated when you save your settings.', 'ewww-image-optimizer' ) . " <a href='https://ewww.io/plans/' target='_blank'>" . esc_html__( 'Purchase an API key.', 'ewww-image-optimizer' ) . "</a></td></tr>\n";
	}
	$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_metadata_remove'>" . esc_html__( 'Remove Metadata', 'ewww-image-optimizer' ) . '</label>' . ewwwio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2' ) . "</th>\n" .
		"<td><input type='checkbox' id='ewww_image_optimizer_metadata_remove' name='ewww_image_optimizer_metadata_remove' value='true' " . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ? "checked='true'" : '' ) . ' /> ' . esc_html__( 'This will remove ALL metadata: EXIF, comments, color profiles, and anything else that is not pixel data.', 'ewww-image-optimizer' ) .
		"<p class ='description'>" . esc_html__( 'Color profiles are preserved when using the API or Easy IO.', 'ewww-image-optimizer' ) . "</p></td></tr>\n";
	ewwwio_debug_message( 'remove metadata: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ? 'on' : 'off' ) );

	$maybe_api_level = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ? '*' : '';

	$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_jpg_level'>" . esc_html__( 'JPG Optimization Level', 'ewww-image-optimizer' ) . '</label>' . ewwwio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2' ) . "</th>\n" .
		"<td><span><select id='ewww_image_optimizer_jpg_level' name='ewww_image_optimizer_jpg_level'>\n" .
		"<option value='0'" . selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ), 0, false ) . '>' . esc_html__( 'No Compression', 'ewww-image-optimizer' ) . "</option>\n";
	if ( defined( 'EWWW_IMAGE_OPTIMIZER_TOOL_PATH' ) ) {
		$output[] = "<option class='$network_class' value='10'" . selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ), 10, false ) . '>' . esc_html__( 'Pixel Perfect', 'ewww-image-optimizer' ) . "</option>\n";
	}
	$output[] = "<option class='$network_class' $disable_level value='20'" . selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ), 20, false ) . '>' . esc_html__( 'Pixel Perfect Plus', 'ewww-image-optimizer' ) . " *</option>\n" .
		"<option $disable_level value='30'" . selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ), 30, false ) . '>' . esc_html__( 'Premium', 'ewww-image-optimizer' ) . " *</option>\n" .
		"<option $disable_level value='40'" . selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ), 40, false ) . '>' . esc_html__( 'Premium Plus', 'ewww-image-optimizer' ) . " *</option>\n" .
		"</select></td></tr>\n";
	ewwwio_debug_message( 'jpg level: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) );
	$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_png_level'>" . esc_html__( 'PNG Optimization Level', 'ewww-image-optimizer' ) . '</label>' . ewwwio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2,5854531bc697912ffd6c1afa' ) . "</th>\n" .
		"<td><span><select id='ewww_image_optimizer_png_level' name='ewww_image_optimizer_png_level'>\n" .
		"<option value='0'" . selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ), 0, false ) . '>' . esc_html__( 'No Compression', 'ewww-image-optimizer' ) . "</option>\n";
	if ( defined( 'EWWW_IMAGE_OPTIMIZER_TOOL_PATH' ) ) {
		$output[] = "<option class='$network_class' value='10'" . selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ), 10, false ) . '>' . esc_html__( 'Pixel Perfect', 'ewww-image-optimizer' ) . "</option>\n";
	}
	$output[] = "<option class='$network_class' $disable_level value='20' " . selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ), 20, false ) .
		selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ), 30, false ) . '>' . esc_html__( 'Pixel Perfect Plus', 'ewww-image-optimizer' ) . " *</option>\n" .
		"<option value='40'" . selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ), 40, false ) . '>' . esc_html__( 'Premium', 'ewww-image-optimizer' ) . " $maybe_api_level</option>\n" .
		"<option $disable_level value='50'" . selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ), 50, false ) . '>' . esc_html__( 'Premium Plus', 'ewww-image-optimizer' ) . " *</option>\n" .
		"</select></td></tr>\n";
	ewwwio_debug_message( 'png level: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) );
	$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_gif_level'>" . esc_html__( 'GIF Optimization Level', 'ewww-image-optimizer' ) . '</label>' . ewwwio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2' ) . "</th>\n" .
		"<td><span><select id='ewww_image_optimizer_gif_level' name='ewww_image_optimizer_gif_level'>\n" .
		"<option value='0'" . selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ), 0, false ) . '>' . esc_html__( 'No Compression', 'ewww-image-optimizer' ) . "</option>\n" .
		"<option value='10'" . selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ), 10, false ) . '>' . esc_html__( 'Pixel Perfect', 'ewww-image-optimizer' ) . " $maybe_api_level</option>\n" .
		"</select></td></tr>\n";
	ewwwio_debug_message( 'gif level: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) );
	$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_pdf_level'>" . esc_html__( 'PDF Optimization Level', 'ewww-image-optimizer' ) . '</label>' . ewwwio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2' ) . "</th>\n" .
		"<td><span><select id='ewww_image_optimizer_pdf_level' name='ewww_image_optimizer_pdf_level'>\n" .
		"<option value='0'" . selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ), 0, false ) . '>' . esc_html__( 'No Compression', 'ewww-image-optimizer' ) . "</option>\n" .
		"<option $disable_level value='10'" . selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ), 10, false ) . '>' . esc_html__( 'Pixel Perfect', 'ewww-image-optimizer' ) . " *</option>\n" .
		"<option $disable_level value='20'" . selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ), 20, false ) . '>' . esc_html__( 'High Compression', 'ewww-image-optimizer' ) . " *</option>\n" .
		"</select></td></tr>\n";
	ewwwio_debug_message( 'pdf level: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) );
	$output[] = "<tr class='$network_class'><th>&nbsp;</th><td>" .
		( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ? "<p class='$network_class nocloud'>* <strong><a href='https://ewww.io/plans/' target='_blank'>" . esc_html__( 'Purchase an API key to unlock these optimization levels. Achieve up to 80% compression and see the quality for yourself.', 'ewww-image-optimizer' ) . "</a></strong></p>\n" :
		'<p>* ' . esc_html__( 'These levels use the compression API.', 'ewww-image-optimizer' ) ) .
		"<p class='$network_class description'>" . esc_html__( 'All methods used by the EWWW Image Optimizer are intended to produce visually identical images.', 'ewww-image-optimizer' ) . "</p>\n" .
		"</td></tr>\n";
	ewwwio_debug_message( 'bulk delay: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) );
	$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_backup_files'>" . esc_html__( 'Backup Originals', 'ewww-image-optimizer' ) . '</label>' . ewwwio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2' ) . '</th>' .
		"<td><input type='checkbox' id='ewww_image_optimizer_backup_files' name='ewww_image_optimizer_backup_files' value='true' " .
		( ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ? "checked='true'" : '' ) . " $disable_level > " . esc_html__( 'Store a copy of your original images on our secure server for 30 days. *Requires an active API key.', 'ewww-image-optimizer' ) . "</td></tr>\n";
	ewwwio_debug_message( 'backup mode: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ? 'on' : 'off' ) );
	if ( class_exists( 'Cloudinary' ) && Cloudinary::config_get( 'api_secret' ) ) {
		$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_enable_cloudinary'>" .
			esc_html__( 'Automatic Cloudinary Upload', 'ewww-image-optimizer' ) .
			"</label></th><td><input type='checkbox' id='ewww_image_optimizer_enable_cloudinary' name='ewww_image_optimizer_enable_cloudinary' value='true' " .
			( ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_cloudinary' ) ? "checked='true'" : '' ) . ' /> ' .
			esc_html__( 'When enabled, uploads to the Media Library will be transferred to Cloudinary after optimization. Cloudinary generates resizes, so only the full-size image is uploaded.', 'ewww-image-optimizer' ) .
			"</td></tr>\n";
		ewwwio_debug_message( 'cloudinary upload: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_cloudinary' ) ? 'on' : 'off' ) );
	}
	$output[] = "</table>\n</div>\n";
	$output[] = "<div id='ewww-exactdn-settings'>\n";
	$output[] = '<noscript><h2>' . esc_html__( 'Easy Mode', 'ewww-image-optimizer' ) . '</h2></noscript>';
	$output[] = '<p>' . esc_html__( 'Having problems? Try disabling Lazy Load and Include All Resources. Finally, disable Easy IO if problems remain.', 'ewww-image-optimizer' ) . "<br>\n" .
		"<a class='ewww-docs-root' href='https://ewww.io/contact-us/'>" . esc_html__( 'Then, let us know so we can find a fix for the problem.', 'ewww-image-optimizer' ) . "</a></p>\n";
	$output[] = "<table class='form-table'>\n";
	$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_exactdn'>" . esc_html__( 'Easy IO', 'ewww-image-optimizer' ) .
		'</label>' . ewwwio_help_link( 'https://docs.ewww.io/article/44-introduction-to-exactdn', '59bc5ad6042863033a1ce370,5c0042892c7d3a31944e88a4' ) . "</th><td><input type='checkbox' id='ewww_image_optimizer_exactdn' name='ewww_image_optimizer_exactdn' value='true' " .
		( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'Enables CDN and automatic image resizing to fit your pages.', 'ewww-image-optimizer' ) .
		' <a href="https://ewww.io/resize/" target="_blank">' . esc_html__( 'Purchase a subscription for your site.', 'ewww-image-optimizer' ) . '</a>' .
		'<p class="description">' .
		esc_html__( 'WebP Conversion', 'ewww-image-optimizer' ) . ewwwio_help_link( 'https://docs.ewww.io/article/16-ewww-io-and-webp-images', '5854745ac697912ffd6c1c89' ) . '<br>' .
		esc_html__( 'Retina Support, use with WP Retina 2x for best results', 'ewww-image-optimizer' ) . '<br>' .
		esc_html__( 'Premium Compression', 'ewww-image-optimizer' ) . '<br>' .
		esc_html__( 'Adjustable Quality', 'ewww-image-optimizer' ) . '<br>' .
		esc_html__( 'JS/CSS Minification and Compression', 'ewww-image-optimizer' ) . '<br>' .
		'<a href="https://docs.ewww.io/article/44-introduction-to-exactdn" target="_blank" data-beacon-article="59bc5ad6042863033a1ce370">' . esc_html__( 'Learn more about Easy IO', 'ewww-image-optimizer' ) . '</a>' .
		"</p></td></tr>\n";
	ewwwio_debug_message( 'ExactDN enabled: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ? 'on' : 'off' ) );
	$output[] = "<tr class='$network_class'><td>&nbsp;</td>" .
		"<td><input type='checkbox' id='exactdn_all_the_things' name='exactdn_all_the_things' value='true' " .
		( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ? ' disabled ' : '' ) .
		( ewww_image_optimizer_get_option( 'exactdn_all_the_things' ) ? "checked='true'" : '' ) . '> ' .
		"<label for='exactdn_all_the_things'><strong>" . esc_html__( 'Include All Resources', 'ewww-image-optimizer' ) . '</strong></label>' . ewwwio_help_link( 'https://docs.ewww.io/article/47-getting-more-from-exactdn', '59de6631042863379ddc953c' ) .
		"<p class='description'>" . esc_html__( 'Use Easy IO for all resources in wp-includes/ and wp-content/, including JavaScript, CSS, fonts, etc.', 'ewww-image-optimizer' ) . '</p>' .
		"</td></tr>\n";
	ewwwio_debug_message( 'ExactDN all the things: ' . ( ewww_image_optimizer_get_option( 'exactdn_all_the_things' ) ? 'on' : 'off' ) );
	$output[] = "<tr class='$network_class'><td>&nbsp;</td>" .
		"<td><input type='checkbox' id='exactdn_lossy' name='exactdn_lossy' value='true' " .
		( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ? ' disabled ' : '' ) .
		( ewww_image_optimizer_get_option( 'exactdn_lossy' ) ? "checked='true'" : '' ) . '> ' .
		"<label for='exactdn_lossy'><strong>" . esc_html__( 'Premium Compression', 'ewww-image-optimizer' ) . '</strong></label>' . ewwwio_help_link( 'https://docs.ewww.io/article/47-getting-more-from-exactdn', '59de6631042863379ddc953c' ) .
		"<p class='description'>" . esc_html__( 'Enable high quality premium compression for all images. Disable to use Pixel Perfect mode instead.', 'ewww-image-optimizer' ) . '</p>' .
		"</td></tr>\n";
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		$output[] = "<input type='hidden' id='exactdn_all_the_things' name='exactdn_all_the_things' " .
			( ewww_image_optimizer_get_option( 'exactdn_all_the_things' ) ? "value='1'" : "value='0'" ) . ">\n";
		$output[] = "<input type='hidden' id='exactdn_lossy' name='exactdn_lossy' " .
			( ewww_image_optimizer_get_option( 'exactdn_lossy' ) ? "value='1'" : "value='0'" ) . ">\n";
		$output[] = "<input type='hidden' id='ewww_image_optimizer_use_lqip' name='ewww_image_optimizer_use_lqip' " .
			( ewww_image_optimizer_get_option( 'ewww_image_optimizer_use_lqip' ) ? "value='1'" : "value='0'" ) . ">\n";
	}
	$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_lazy_load'>" . esc_html__( 'Lazy Load', 'ewww-image-optimizer' ) . '</label>' .
		ewwwio_help_link( 'https://docs.ewww.io/article/74-lazy-load', '5c6c36ed042863543ccd2d9b' ) .
		"</th><td><input type='checkbox' id='ewww_image_optimizer_lazy_load' name='ewww_image_optimizer_lazy_load' value='true' " .
		( ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'Improves actual and perceived loading time as images will be loaded only as they enter (or are about to enter) the viewport.', 'ewww-image-optimizer' ) .
		"<p class='description'>" . esc_html__( 'When used with Easy IO and/or JS WebP Rewriting, the plugin will load the best available image size and format for each device.', 'ewww-image-optimizer' ) . "</p>\n" .
		"</td></tr>\n";
	$output[] = "<tr class='$network_class'><td>&nbsp;</td><td><input type='checkbox' id='ewww_image_optimizer_use_lqip' name='ewww_image_optimizer_use_lqip' value='true' " .
		( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ? ' disabled ' : '' ) .
		( ewww_image_optimizer_get_option( 'ewww_image_optimizer_use_lqip' ) ? "checked='true'" : '' ) . ' /> ' .
		"<label for='ewww_image_optimizer_use_lqip'><strong>LQIP</strong></label>" .
		ewwwio_help_link( 'https://docs.ewww.io/article/75-lazy-load-placeholders', '5c9a7a302c7d3a1544615e47' ) . "\n" .
		"<p class='description'>" . esc_html__( 'Use low-quality versions of your images as placeholders via Easy IO. Can improve user experience, but may be slower than blank placeholders.', 'ewww-image-optimizer' ) . '</p>' .
		"</td></tr>\n";
	ewwwio_debug_message( 'ExactDN lossy: ' . intval( ewww_image_optimizer_get_option( 'exactdn_lossy' ) ) );
	ewwwio_debug_message( 'ExactDN resize existing: ' . ( ewww_image_optimizer_get_option( 'exactdn_resize_existing' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'ExactDN attachment queries: ' . ( ewww_image_optimizer_get_option( 'exactdn_prevent_db_queries' ) ? 'off' : 'on' ) );
	ewwwio_debug_message( 'lazy load: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'LQIP: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_use_lqip' ) ? 'on' : 'off' ) );
	if ( defined( 'EXACTDN_EXCLUDE' ) && EXACTDN_EXCLUDE ) {
		$exactdn_user_exclusions = EXACTDN_EXCLUDE;
		if ( is_array( $exactdn_user_exclusions ) ) {
			ewwwio_debug_message( 'ExactDN user exclusions : ' . implode( ',', $exactdn_user_exclusions ) );
		} elseif ( is_string( $exactdn_user_exclusions ) ) {
			ewwwio_debug_message( 'ExactDN user exclusions : ' . $exactdn_user_exclusions );
		} else {
			ewwwio_debug_message( 'ExactDN user exclusions invalid data type' );
		}
	}
	$output[] = "</table>\n</div>\n";
	$output[] = "<div id='ewww-optimization-settings'>\n";
	$output[] = '<noscript><h2>' . esc_html__( 'Advanced', 'ewww-image-optimizer' ) . '</h2></noscript>';
	$output[] = "<table class='form-table'>\n";
	if ( ! ewww_image_optimizer_full_cloud() ) {
		ewwwio_debug_message( 'optipng level: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_optipng_level' ) );
		ewwwio_debug_message( 'pngout disabled: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_pngout' ) ? 'yes' : 'no' ) );
		ewwwio_debug_message( 'pngout level: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_pngout_level' ) );
	}
	$output[] = "<tr class='$network_class'><th scope='row'><span><label for='ewww_image_optimizer_jpg_quality'>" . esc_html__( 'JPG Quality Level:', 'ewww-image-optimizer' ) . '</label>' . ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,58543c69c697912ffd6c19a7' ) . "</th><td><input type='text' id='ewww_image_optimizer_jpg_quality' name='ewww_image_optimizer_jpg_quality' class='small-text' value='" . ewww_image_optimizer_jpg_quality() . "' /> " . esc_html__( 'Valid values are 1-100.', 'ewww-image-optimizer' ) . "\n<p class='description'>" . esc_html__( 'Use this to override the default WordPress quality level of 82. Applies to image editing, resizing, PNG to JPG conversion, and JPG to WebP conversion. Does not affect the original uploaded image unless maximum dimensions are set and resizing occurs.', 'ewww-image-optimizer' ) . "</p></td></tr>\n";
	ewwwio_debug_message( 'effective quality: ' . ewww_image_optimizer_set_jpg_quality( 82 ) );
	$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_parallel_optimization'>" . esc_html__( 'Parallel Optimization', 'ewww-image-optimizer' ) . '</label>' . ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,598cb8be2c7d3a73488be237' ) . "</th><td><input type='checkbox' id='ewww_image_optimizer_parallel_optimization' name='ewww_image_optimizer_parallel_optimization' value='true' " . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_parallel_optimization' ) ? "checked='true'" : '' ) . ' /> ' . esc_html__( 'All resizes generated from a single upload are optimized in parallel for faster optimization. If this is causing performance issues, disable parallel optimization to reduce the load on your server.', 'ewww-image-optimizer' ) . "</td></tr>\n";
	ewwwio_debug_message( 'parallel optimization: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_parallel_optimization' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'background optimization: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_background_optimization' ) ? 'on' : 'off' ) );
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_background_optimization' ) ) {
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
	$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_auto'>" . esc_html__( 'Scheduled Optimization', 'ewww-image-optimizer' ) . '</label>' .
		ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,5853713bc697912ffd6c0b98' ) .
		"</th><td><input type='checkbox' id='ewww_image_optimizer_auto' name='ewww_image_optimizer_auto' value='true' " .
		( ewww_image_optimizer_get_option( 'ewww_image_optimizer_auto' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'This will enable scheduled optimization of unoptimized images for your theme, buddypress, and any additional folders you have configured below. Runs hourly: wp_cron only runs when your site is visited, so it may be even longer between optimizations.', 'ewww-image-optimizer' ) .
		"</td></tr>\n";
	ewwwio_debug_message( 'scheduled optimization: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_auto' ) ? 'on' : 'off' ) );
	$media_include_disable = '';
	if ( get_option( 'ewww_image_optimizer_disable_resizes_opt' ) ) {
		$media_include_disable = 'disabled="disabled"';
		$output[]              = "<tr class='$network_class'><th>&nbsp;</th><td>" .
			'<p><span style="color: #3eadc9">' . esc_html__( '*Include Media Library Folders has been disabled because it will cause the scanner to ignore the disabled resizes.', 'ewww-image-optimizer' ) . "</span></p></td></tr>\n";
	}
	$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_include_media_paths'>" .
		esc_html__( 'Include Media Folders', 'ewww-image-optimizer' ) . '</label>' .
		ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,5853713bc697912ffd6c0b98' ) .
		"</th><td><input type='checkbox' id='ewww_image_optimizer_include_media_paths' name='ewww_image_optimizer_include_media_paths' $media_include_disable value='true' " .
		( ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_media_paths' ) && ! get_option( 'ewww_image_optimizer_disable_resizes_opt' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'Scan all images from the latest two folders of the Media Library during the Bulk Optimizer and Scheduled Optimization.', 'ewww-image-optimizer' ) . "</td></tr>\n";
	ewwwio_debug_message( 'include media library: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_media_paths' ) ? 'on' : 'off' ) );
	$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_include_originals'>" .
		esc_html__( 'Include Originals', 'ewww-image-optimizer' ) . '</label>' .
		ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0' ) .
		"</th><td><input type='checkbox' id='ewww_image_optimizer_include_originals' name='ewww_image_optimizer_include_originals' value='true' " .
		( ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_originals' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'Optimize the original version of images that have been scaled down by WordPress.', 'ewww-image-optimizer' ) . "</td></tr>\n";
	ewwwio_debug_message( 'include originals: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_originals' ) ? 'on' : 'off' ) );
	$aux_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_aux_paths' ) ? esc_html( implode( "\n", ewww_image_optimizer_get_option( 'ewww_image_optimizer_aux_paths' ) ) ) : '';
	$output[]  = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_aux_paths'>" . esc_html__( 'Folders to Optimize', 'ewww-image-optimizer' ) . '</label>'
		. ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,5853713bc697912ffd6c0b98' ) . '</th><td>' .
		/* translators: %s: the folder where WordPress is installed */
		sprintf( esc_html__( 'One path per line, must be within %s. Use full paths, not relative paths.', 'ewww-image-optimizer' ), ABSPATH ) . "<br>\n" .
		"<textarea id='ewww_image_optimizer_aux_paths' name='ewww_image_optimizer_aux_paths' rows='3' cols='60'>$aux_paths</textarea>\n" .
		"<p class='description'>" . esc_html__( 'Provide paths containing images to be optimized using the Bulk Optimizer and Scheduled Optimization.', 'ewww-image-optimizer' ) . "</p></td></tr>\n";
	ewwwio_debug_message( 'folders to optimize:' );
	ewwwio_debug_message( $aux_paths );

	$exclude_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_exclude_paths' ) ? esc_html( implode( "\n", ewww_image_optimizer_get_option( 'ewww_image_optimizer_exclude_paths' ) ) ) : '';
	$output[]      = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_exclude_paths'>" . esc_html__( 'Folders to Ignore', 'ewww-image-optimizer' ) . '</label>' . ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,5853713bc697912ffd6c0b98' ) . '</th><td>' . esc_html__( 'One path per line, partial paths allowed, but no urls.', 'ewww-image-optimizer' ) . "<br>\n" .
		"<textarea id='ewww_image_optimizer_exclude_paths' name='ewww_image_optimizer_exclude_paths' rows='3' cols='60'>$exclude_paths</textarea>\n" .
		"<p class='description'>" . esc_html__( 'A file that matches any pattern or path provided will not be optimized.', 'ewww-image-optimizer' ) . "</p></td></tr>\n";
	ewwwio_debug_message( 'folders to ignore:' );
	ewwwio_debug_message( $exclude_paths );
	ewwwio_debug_message( 'skip images smaller than: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_size' ) . ' bytes' );
	ewwwio_debug_message( 'skip PNG images larger than: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) . ' bytes' );
	ewwwio_debug_message( 'exclude originals from lossy: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_lossy_skip_full' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'exclude originals from metadata removal: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_skip_full' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'use system binaries: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_bundle' ) ? 'yes' : 'no' ) );
	$output[] = "</table>\n</div>\n";

	$output[] = "<div id='ewww-resize-settings'>\n";
	$output[] = '<noscript><h2>' . esc_html__( 'Resize', 'ewww-image-optimizer' ) . '</h2></noscript>';
	$output[] = "<table class='form-table'>\n";
	$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_resize_detection'>" . esc_html__( 'Resize Detection', 'ewww-image-optimizer' ) . '</label>' .
		ewwwio_help_link( 'https://docs.ewww.io/article/41-resize-settings', '59849911042863033a1ba5f9' ) .
		"</th><td><input type='checkbox' id='ewww_image_optimizer_resize_detection' name='ewww_image_optimizer_resize_detection' value='true' " .
		( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_detection' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'Highlight images that need to be resized because the browser is scaling them down. Only visible for Admin users and adds a button to the admin bar to detect scaled images that have been lazy loaded.', 'ewww-image-optimizer' ) .
		"</td></tr>\n";
	ewwwio_debug_message( 'resize detection: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_detection' ) ? 'on' : 'off' ) );
	if ( function_exists( 'imsanity_get_max_width_height' ) ) {
		$output[] = "<tr class='$network_class'><th>&nbsp;</th><td>" .
			'<p><span style="color: #3eadc9">' . esc_html__( '*Imsanity settings override the EWWW resize dimensions.', 'ewww-image-optimizer' ) . "</span></p></td></tr>\n";
	}
	$output[] = "<tr class='$network_class'><th scope='row'>" . esc_html__( 'Resize Images', 'ewww-image-optimizer' ) . ewwwio_help_link( 'https://docs.ewww.io/article/41-resize-settings', '59849911042863033a1ba5f9' ) .
		"</th><td><label for='ewww_image_optimizer_maxmediawidth'>" . esc_html__( 'Max Width', 'ewww-image-optimizer' ) .
		"</label> <input type='number' step='1' min='0' class='small-text' id='ewww_image_optimizer_maxmediawidth' name='ewww_image_optimizer_maxmediawidth' value='" .
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' ) . ( function_exists( 'imsanity_get_max_width_height' ) ? "' disabled='disabled" : '' ) .
		"' /> <label for='ewww_image_optimizer_maxmediaheight'>" . esc_html__( 'Max Height', 'ewww-image-optimizer' ) .
		"</label> <input type='number' step='1' min='0' class='small-text' id='ewww_image_optimizer_maxmediaheight' name='ewww_image_optimizer_maxmediaheight' value='" .
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' ) . ( function_exists( 'imsanity_get_max_width_height' ) ? "' disabled='disabled" : '' ) . "' /> " .
		esc_html__( 'in pixels', 'ewww-image-optimizer' ) . "\n" . "<p class='description'>" . esc_html__( 'Resize uploaded images to these dimensions.', 'ewww-image-optimizer' ) .
		"</td></tr>\n";
	ewwwio_debug_message( 'max media dimensions: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' ) . ' x ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' ) );
	ewwwio_debug_message( 'max other dimensions: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherwidth' ) . ' x ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherheight' ) );
	$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_resize_existing'>" . esc_html__( 'Resize Existing Images', 'ewww-image-optimizer' ) . '</label>' .
		ewwwio_help_link( 'https://docs.ewww.io/article/41-resize-settings', '59849911042863033a1ba5f9' ) .
		"</th><td><input type='checkbox' id='ewww_image_optimizer_resize_existing' name='ewww_image_optimizer_resize_existing' value='true' " .
		( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_existing' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'Allow resizing of existing Media Library images.', 'ewww-image-optimizer' ) . "</td></tr>\n";
	ewwwio_debug_message( 'resize existing images: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_existing' ) ? 'on' : 'off' ) );
	$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_resize_other_existing'>" . esc_html__( 'Resize Other Images', 'ewww-image-optimizer' ) . '</label>' .
		ewwwio_help_link( 'https://docs.ewww.io/article/41-resize-settings', '59849911042863033a1ba5f9' ) .
		"</th><td><input type='checkbox' id='ewww_image_optimizer_resize_other_existing' name='ewww_image_optimizer_resize_other_existing' value='true' " .
		( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_other_existing' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'Allow resizing of existing images outside the Media Library. Use this to resize images specified under the Folders to Optimize setting when running Bulk or Scheduled Optimization.', 'ewww-image-optimizer' ) . "</td></tr>\n";
	ewwwio_debug_message( 'resize existing (non-media) images: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_other_existing' ) ? 'on' : 'off' ) );

	$output[]           = '<tr class="network-singlesite"><th scope="row">' . esc_html__( 'Disable Resizes', 'ewww-image-optimizer' ) .
		ewwwio_help_link( 'https://docs.ewww.io/article/41-resize-settings', '59849911042863033a1ba5f9,58598744c697912ffd6c3eb4' ) . '</th><td><p>' .
		esc_html__( 'WordPress, your theme, and other plugins generate various image sizes. You may disable optimization for certain sizes, or completely prevent those sizes from being created.', 'ewww-image-optimizer' ) . '<br>' .
		'<i>' . esc_html__( 'Remember that each image size will affect your API credits.', 'ewww-image-optimizer' ) . "</i></p>\n";
	$image_sizes        = ewww_image_optimizer_get_image_sizes();
	$disabled_sizes     = get_option( 'ewww_image_optimizer_disable_resizes' );
	$disabled_sizes_opt = get_option( 'ewww_image_optimizer_disable_resizes_opt' );
	$output[]           = '<table><tr class="network-singlesite"><th scope="col">' . esc_html__( 'Disable Optimization', 'ewww-image-optimizer' ) . '</th><th scope="col">' . esc_html__( 'Disable Creation', 'ewww-image-optimizer' ) . "</th></tr>\n";
	ewwwio_debug_message( 'disabled resizes:' );
	foreach ( $image_sizes as $size => $dimensions ) {
		if ( empty( $dimensions['width'] ) && empty( $dimensions['height'] ) ) {
			continue;
		}
		if ( 'thumbnail' === $size ) {
			$output[] = "<tr class='network-singlesite'><td><input type='checkbox' id='ewww_image_optimizer_disable_resizes_opt_$size' name='ewww_image_optimizer_disable_resizes_opt[$size]' value='true' " . ( ! empty( $disabled_sizes_opt[ $size ] ) ? "checked='true'" : '' ) . " /></td><td><input type='checkbox' id='ewww_image_optimizer_disable_resizes_$size' name='ewww_image_optimizer_disable_resizes[$size]' value='true' disabled /></td><td><label for='ewww_image_optimizer_disable_resizes_$size'>$size - {$dimensions['width']}x{$dimensions['height']}</label></td></tr>\n";
		} elseif ( 'pdf-full' === $size ) {
			$output[] = "<tr class='network-singlesite'><td><input type='checkbox' id='ewww_image_optimizer_disable_resizes_opt_$size' name='ewww_image_optimizer_disable_resizes_opt[$size]' value='true' " . ( ! empty( $disabled_sizes_opt[ $size ] ) ? "checked='true'" : '' ) . " /></td><td><input type='checkbox' id='ewww_image_optimizer_disable_resizes_$size' name='ewww_image_optimizer_disable_resizes[$size]' value='true' " . ( ! empty( $disabled_sizes[ $size ] ) ? "checked='true'" : '' ) . " /></td><td><label for='ewww_image_optimizer_disable_resizes_$size'>$size - <span class='description'>" . esc_html__( 'Disabling creation of the full-size preview for PDF files will disable all PDF preview sizes', 'ewww-image-optimizer' ) . "</span></label></td></tr>\n";
		} else {
			$output[] = "<tr class='network-singlesite'><td><input type='checkbox' id='ewww_image_optimizer_disable_resizes_opt_$size' name='ewww_image_optimizer_disable_resizes_opt[$size]' value='true' " . ( ! empty( $disabled_sizes_opt[ $size ] ) ? "checked='true'" : '' ) . " /></td><td><input type='checkbox' id='ewww_image_optimizer_disable_resizes_$size' name='ewww_image_optimizer_disable_resizes[$size]' value='true' " . ( ! empty( $disabled_sizes[ $size ] ) ? "checked='true'" : '' ) . " /></td><td><label for='ewww_image_optimizer_disable_resizes_$size'>$size - {$dimensions['width']}x{$dimensions['height']}</label></td></tr>\n";
		}
		ewwwio_debug_message( $size . ': ' . ( ! empty( $disabled_sizes_opt[ $size ] ) ? 'optimization=disabled ' : 'optimization=enabled ' ) . ( ! empty( $disabled_sizes[ $size ] ) ? 'creation=disabled' : 'creation=enabled' ) );
	}
	if ( 'network-multisite' !== $network ) {
		$output[] = "</table>\n";
		$output[] = "</td></tr>\n";
	} else {
		$output[] = '<tr><th scope="row">' . esc_html__( 'Disable Resizes', 'ewww-image-optimizer' ) . '</th><td>';
		$output[] = '<p><span style="color: #3eadc9">' . esc_html__( '*Settings to disable creation and optimization of individual sizes must be configured for each individual site.', 'ewww-image-optimizer' ) . "</span></p></td></tr>\n";
	}
	$output[] = "</table>\n</div>\n";

	$output[] = "<div id='ewww-conversion-settings'>\n";
	$output[] = '<noscript><h2>' . esc_html__( 'Convert', 'ewww-image-optimizer' ) . '</h2></noscript>';
	$output[] = '<p>' . esc_html__( 'Conversion is only available for images in the Media Library (except WebP). By default, all images have a link available in the Media Library for one-time conversion. Turning on individual conversion operations below will enable conversion filters any time an image is uploaded or modified.', 'ewww-image-optimizer' ) . "<br />\n" .
		'<b>' . esc_html__( 'NOTE:', 'ewww-image-optimizer' ) . '</b> ' . esc_html__( 'The plugin will attempt to update image locations for any posts that contain the images. You may still need to manually update locations/urls for converted images.', 'ewww-image-optimizer' ) . "\n" .
		"</p>\n";
	$output[] = "<table class='form-table'>\n";
	if ( $toolkit_found ) {
		$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_disable_convert_links'>" . esc_html__( 'Hide Conversion Links', 'ewww-image-optimizer' ) .
			'</label>' . ewwwio_help_link( 'https://docs.ewww.io/article/14-converting-images', '58545a86c697912ffd6c1b53' ) .
			"</th><td><input type='checkbox' id='ewww_image_optimizer_disable_convert_links' name='ewww_image_optimizer_disable_convert_links' " .
			( ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_convert_links' ) ? "checked='true'" : '' ) . ' /> ' .
			esc_html__( 'Site or Network admins can use this to prevent other users from using the conversion links in the Media Library which bypass the settings below.', 'ewww-image-optimizer' ) .
			"</td></tr>\n";
		$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_delete_originals'>" . esc_html__( 'Delete Originals', 'ewww-image-optimizer' ) . '</label>' .
			ewwwio_help_link( 'https://docs.ewww.io/article/14-converting-images', '58545a86c697912ffd6c1b53' ) .
			"</th><td><input type='checkbox' id='ewww_image_optimizer_delete_originals' name='ewww_image_optimizer_delete_originals' " .
			( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ? "checked='true'" : '' ) . ' /> ' .
			esc_html__( 'This will remove the original image from the server after a successful conversion.', 'ewww-image-optimizer' ) . "</td></tr>\n";
		ewwwio_debug_message( 'delete originals: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ? 'on' : 'off' ) );
		$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_jpg_to_png'>" .
			/* translators: 1: JPG, GIF or PNG 2: JPG or PNG */
			sprintf( esc_html__( '%1$s to %2$s Conversion', 'ewww-image-optimizer' ), 'JPG', 'PNG' ) .
			'</label>' . ewwwio_help_link( 'https://docs.ewww.io/article/14-converting-images', '58545a86c697912ffd6c1b53' ) .
			"</th><td><span><input type='checkbox' id='ewww_image_optimizer_jpg_to_png' name='ewww_image_optimizer_jpg_to_png' " .
			( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_to_png' ) ? "checked='true'" : '' ) . ' /> <b>' . esc_html__( 'WARNING:', 'ewww-image-optimizer' ) . '</b> ' .
			esc_html__( 'Removes metadata and increases cpu usage dramatically.', 'ewww-image-optimizer' ) . "</span>\n" .
			"<p class='description'>" . esc_html__( 'PNG is generally much better than JPG for logos and other images with a limited range of colors. Checking this option will slow down JPG processing significantly, and you may want to enable it only temporarily.', 'ewww-image-optimizer' ) .
			"</p></td></tr>\n";
		ewwwio_debug_message( 'jpg2png: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_to_png' ) ? 'on' : 'off' ) );
		$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_png_to_jpg'>" .
			/* translators: 1: JPG, GIF or PNG 2: JPG or PNG */
			sprintf( esc_html__( '%1$s to %2$s Conversion', 'ewww-image-optimizer' ), 'PNG', 'JPG' ) .
			'</label>' . ewwwio_help_link( 'https://docs.ewww.io/article/14-converting-images', '58545a86c697912ffd6c1b53,58543c69c697912ffd6c19a7,58542afac697912ffd6c18c0' ) .
			"</th><td><span><input type='checkbox' id='ewww_image_optimizer_png_to_jpg' name='ewww_image_optimizer_png_to_jpg' " .
			( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_to_jpg' ) ? "checked='true'" : '' ) . ' /> <b>' . esc_html__( 'WARNING:', 'ewww-image-optimizer' ) . '</b> ' .
			esc_html__( 'This is not a lossless conversion.', 'ewww-image-optimizer' ) . "</span>\n" .
			"<p class='description'>" . esc_html__( 'JPG is generally much better than PNG for photographic use because it compresses the image and discards data. PNGs with transparency are not converted by default.', 'ewww-image-optimizer' ) . "</p>\n" .
			"<span><label for='ewww_image_optimizer_jpg_background'> " . esc_html__( 'JPG Background Color:', 'ewww-image-optimizer' ) . "</label> #<input type='text' id='ewww_image_optimizer_jpg_background' name='ewww_image_optimizer_jpg_background' size='6' value='" . ewww_image_optimizer_jpg_background() . "' /> <span style='padding-left: 12px; font-size: 12px; border: solid 1px #555555; background-color: #" . ewww_image_optimizer_jpg_background() . "'>&nbsp;</span> " . esc_html__( 'HEX format (#123def)', 'ewww-image-optimizer' ) . ".</span>\n" .
			"<p class='description'>" . esc_html__( 'Background color is used only if the PNG has transparency. Leave this value blank to skip PNGs with transparency.', 'ewww-image-optimizer' ) . "</p></td></tr>\n";
		ewwwio_debug_message( 'png2jpg: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_to_jpg' ) ? 'on' : 'off' ) );
		$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_gif_to_png'>" .
			/* translators: 1: JPG, GIF or PNG 2: JPG or PNG */
			sprintf( esc_html__( '%1$s to %2$s Conversion', 'ewww-image-optimizer' ), 'GIF', 'PNG' ) .
			'</label>' . ewwwio_help_link( 'https://docs.ewww.io/article/14-converting-images', '58545a86c697912ffd6c1b53' ) .
			"</th><td><span><input type='checkbox' id='ewww_image_optimizer_gif_to_png' name='ewww_image_optimizer_gif_to_png' " .
			( ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_to_png' ) ? "checked='true'" : '' ) . ' /> ' .
			esc_html__( 'No warnings here, just do it.', 'ewww-image-optimizer' ) . "</span>\n" .
			"<p class='description'> " . esc_html__( 'PNG is generally better than GIF, but animated images cannot be converted.', 'ewww-image-optimizer' ) . "</p></td></tr>\n";
		ewwwio_debug_message( 'gif2png: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_to_png' ) ? 'on' : 'off' ) );
	} else {
		$output[] = "<tr class='$network_class'><th>&nbsp;</th><td>" .
			'<p><span style="color: #3eadc9">' . esc_html__( 'Image conversion requires one of the following PHP libraries: GD, Imagick, or GMagick.', 'ewww-image-optimizer' ) . "</span></p></td></tr>\n";
	}
	$output[] = "</table>\n</div>\n";

	$output[] = "<div id='ewww-webp-settings'>\n";
	$output[] = '<noscript><h2>' . esc_html__( 'WebP', 'ewww-image-optimizer' ) . '</h2></noscript>';
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) && ! ewww_image_optimizer_easy_active() ) {
		$output[] = '<p>' . esc_html__( 'Once JPG/PNG to WebP is enabled, WebP images will be generated for new uploads, but you will need to use the Bulk Optimizer for existing uploads.', 'ewww-image-optimizer' ) . "<br>\n" .
		esc_html__( 'See Easy Mode for automatic on-demand WebP conversion instead.', 'ewww-image-optimizer' ) . "</p>\n";
	}
	$output[] = "<table class='form-table'>\n";
	if ( ! ewww_image_optimizer_easy_active() || ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) {
		$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_webp'>" . esc_html__( 'JPG/PNG to WebP', 'ewww-image-optimizer' ) . '</label>' .
			ewwwio_help_link( 'https://docs.ewww.io/article/16-ewww-io-and-webp-images', '5854745ac697912ffd6c1c89' ) .
			"</th><td><span><input type='checkbox' id='ewww_image_optimizer_webp' name='ewww_image_optimizer_webp' value='true' " .
			( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ? "checked='true'" : '' ) . ' /> ' .
			esc_html__( 'JPG to WebP conversion is lossy, but quality loss is minimal. PNG to WebP conversion is lossless.', 'ewww-image-optimizer' ) .
			"</span>\n<p class='description'>" . esc_html__( 'Originals are never deleted, and WebP images should only be served to supported browsers.', 'ewww-image-optimizer' ) .
			" <a href='#ewww-webp-rewrite'>" . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) ? esc_html__( 'You can use the rewrite rules below to serve WebP images with Apache.', 'ewww-image-optimizer' ) : '' ) . "</a></td></tr>\n";
		ewwwio_debug_message( 'webp conversion: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ? 'on' : 'off' ) );
	}
	if ( ! ewww_image_optimizer_ce_webp_enabled() && ! ewww_image_optimizer_easy_active() ) {
		$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_webp_for_cdn'>" .
			esc_html__( 'JS WebP Rewriting', 'ewww-image-optimizer' ) .
			'</label>' . ewwwio_help_link( 'https://docs.ewww.io/article/16-ewww-io-and-webp-images', '5854745ac697912ffd6c1c89,59443d162c7d3a0747cdf9f0' ) .
			"</th><td><input type='checkbox' id='ewww_image_optimizer_webp_for_cdn' name='ewww_image_optimizer_webp_for_cdn' value='true' " .
			( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) ? "checked='true'" : '' ) . ' /> ' .
			esc_html__( 'Use this if the Apache rewrite rules do not work, or if your images are served from a CDN.', 'ewww-image-optimizer' ) . ' ' .
			'</td></tr>';
		ewwwio_debug_message( 'alt webp rewriting: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) ? 'on' : 'off' ) );
		$webp_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' ) ? esc_html( implode( "\n", ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' ) ) ) : '';
		$output[]   = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_webp_paths'>" . esc_html__( 'WebP URLs', 'ewww-image-optimizer' ) . '</label>' .
			ewwwio_help_link( 'https://docs.ewww.io/article/16-ewww-io-and-webp-images', '5854745ac697912ffd6c1c89' ) . '</th><td><span>' .
			esc_html__( 'Enter additional URL patterns, like a CDN URL, that should be permitted for JS WebP Rewriting. One URL per line, must include the domain name (cdn.example.com).', 'ewww-image-optimizer' ) . '</span>' .
			'<p>' . esc_html__( 'Optionally include a folder with the URL if your CDN path is different from your local path.', 'ewww-image-optimizer' ) . '</p>' .
			"<textarea id='ewww_image_optimizer_webp_paths' name='ewww_image_optimizer_webp_paths' rows='3' cols='60'>$webp_paths</textarea>" .
			"<p class='description'>" . sprintf(
				/* translators: 1: An image URL on a CDN 2: An image URL 3: An example folder URL */
				esc_html__( 'For example, with a CDN URL of %1$s and a local URL of %2$s you would enter %3$s.', 'ewww-image-optimizer' ),
				'https://cdn.example.com/<strong>files/</strong>2038/01/image.jpg',
				'https://example.com/<strong>wp-content/uploads/</strong>2038/01/image.jpg',
				'https://cdn.example.com/<strong>files/</strong>'
			) . '</p>' .
			"</td></tr>\n";
		ewwwio_debug_message( 'webp paths:' );
		ewwwio_debug_message( $webp_paths );
	} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		$output[] = "<tr class='$network_class'><th>&nbsp;</th><td><p class='description'>" . esc_html__( 'WebP images are served automatically by Easy IO.', 'ewww-image-optimizer' ) . '</p></td></tr>';
	} elseif ( get_option( 'easyio_exactdn' ) ) {
		$output[] = "<tr class='$network_class'><th>&nbsp;</th><td><p class='description'>" . esc_html__( 'WebP images are served automatically by Easy Image Optimizer.', 'ewww-image-optimizer' ) . '</p></td></tr>';
	}
	if ( ! ewww_image_optimizer_easy_active() || ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) {
		$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_webp_force'>" . esc_html__( 'Force WebP', 'ewww-image-optimizer' ) . '</label>' .
			ewwwio_help_link( 'https://docs.ewww.io/article/16-ewww-io-and-webp-images', '5854745ac697912ffd6c1c89' ) .
			"</th><td><span><input type='checkbox' id='ewww_image_optimizer_webp_force' name='ewww_image_optimizer_webp_force' value='true' " .
			( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ? "checked='true'" : '' ) . ' /> ' .
			esc_html__( 'WebP images will be generated and saved for all JPG/PNG images regardless of their size. The JS WebP Rewriting will not check if a file exists, only that the domain matches the home url, or one of the provided WebP URLs.', 'ewww-image-optimizer' ) . "</span></td></tr>\n";
		ewwwio_debug_message( 'forced webp: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ? 'on' : 'off' ) );
	}
	$output[] = "</table>\n</div>\n";

	$output[] = "<div id='ewww-support-settings'>\n";
	$output[] = '<noscript><h2>' . esc_html__( 'Support', 'ewww-image-optimizer' ) . '</h2></noscript>';
	$output[] = "<p><a class='ewww-docs-root' href='https://docs.ewww.io/'>" . esc_html__( 'Documentation', 'ewww-image-optimizer' ) . '</a> | ' .
		"<a class='ewww-docs-root' href='https://ewww.io/contact-us/'>" . esc_html__( 'Plugin Support', 'ewww-image-optimizer' ) . '</a> | ' .
		"<a href='https://ewww.io/status/'>" . esc_html__( 'Server Status', 'ewww-image-optimizer' ) . '</a>' .
		"</p>\n";
	$output[] = "<table class='form-table'>\n";
	$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_enable_help'>" . esc_html__( 'Enable Embedded Help', 'ewww-image-optimizer' ) .
		"</label></th><td><input type='checkbox' id='ewww_image_optimizer_enable_help' name='ewww_image_optimizer_enable_help' value='true' " .
		( ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_help' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'Enable the support beacon, which gives you access to documentation and our support team right from your WordPress dashboard. To assist you more efficiently, we may collect the current url, IP address, browser/device information, and debugging information.', 'ewww-image-optimizer' ) .
		"</td></tr>\n";
	ewwwio_debug_message( 'enable help beacon: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_help' ) ? 'yes' : 'no' ) );
	$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_debug'>" . esc_html__( 'Debugging', 'ewww-image-optimizer' ) . '</label>' .
		ewwwio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2' ) . '</th>' .
		"<td><input type='checkbox' id='ewww_image_optimizer_debug' name='ewww_image_optimizer_debug' value='true' " .
		( ! $ewwwio_temp_debug && ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'Use this to provide information for support purposes, or if you feel comfortable digging around in the code to fix a problem you are experiencing.', 'ewww-image-optimizer' ) .
		"</td></tr>\n";
	$output[] = "</table>\n";

	$output[] = 'DEBUG_PLACEHOLDER';

	$output[] = "</div>\n";

	$output[] = "<div id='ewww-contribute-settings'>\n";
	$output[] = '<noscript><h2>' . esc_html__( 'Contribute', 'ewww-image-optimizer' ) . '</h2></noscript>';
	$output[] = '<p><strong>' . esc_html__( 'Here are some ways you can contribute to the development of this plugin:', 'ewww-image-optimizer' ) . "</strong></p>\n";
	$output[] = "<p><a href='https://translate.wordpress.org/projects/wp-plugins/ewww-image-optimizer/'>" . esc_html__( 'Translate EWWW I.O.', 'ewww-image-optimizer' ) . '</a> | ' .
		"<a href='https://wordpress.org/support/view/plugin-reviews/ewww-image-optimizer#postform'>" . esc_html__( 'Write a review', 'ewww-image-optimizer' ) . '</a> | ' .
		"<a href='https://ewww.io/plans/'>" . esc_html__( 'Upgrade to premium image optimization', 'ewww-image-optimizer' ) . "</a></p>\n";
	$output[] = "<table class='form-table'>\n";
	$output[] = "<tr class='$network_class'><th scope='row'><label for='ewww_image_optimizer_allow_tracking'>" . esc_html__( 'Allow Usage Tracking?', 'ewww-image-optimizer' ) . '</label>' .
		ewwwio_help_link( 'https://docs.ewww.io/article/23-usage-tracking', '591f3a8e2c7d3a057f893d91' ) .
		"</th><td><input type='checkbox' id='ewww_image_optimizer_allow_tracking' name='ewww_image_optimizer_allow_tracking' value='true' " .
		( ewww_image_optimizer_get_option( 'ewww_image_optimizer_allow_tracking' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'Allow EWWW Image Optimizer to anonymously track how this plugin is used and help us make the plugin better. Opt-in to tracking and receive 500 API image credits free. No sensitive data is tracked.', 'ewww-image-optimizer' ) .
		"</td></tr>\n";
	$output[] = "</table>\n";
	$output[] = "</div>\n";

	$output[] = "<p class='submit'><input type='submit' class='button-primary' value='" . esc_attr__( 'Save Changes', 'ewww-image-optimizer' ) . "' /></p>\n";
	$output[] = "</form>\n";
	// Make sure .htaccess rules are terminated when ExactDN is enabled.
	if ( ewww_image_optimizer_easy_active() ) {
		ewww_image_optimizer_webp_rewrite_verify();
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) && ! ewww_image_optimizer_ce_webp_enabled() && ! ewww_image_optimizer_easy_active() ) {
		if ( defined( 'PHP_SAPI' ) && false === strpos( PHP_SAPI, 'apache' ) && false === strpos( PHP_SAPI, 'litespeed' ) ) {
			ewwwio_debug_message( 'PHP module: ' . PHP_SAPI );
			$false_positive_headers = esc_html( 'This may be a false positive. If so, the warning should go away once you implement the rewrite rules.' );
		}
		$header_error = '';
		if ( ! apache_mod_loaded( 'mod_rewrite' ) ) {
			ewwwio_debug_message( 'webp missing mod_rewrite' );
			/* translators: %s: mod_rewrite or mod_headers */
			$header_error = '<p><strong>' . sprintf( esc_html( 'Your site appears to be missing %s, please contact your webhost or system administrator to enable this Apache module.' ), 'mod_rewrite' ) . "</strong><br>$false_positive_headers</p>\n";
		}
		if ( ! apache_mod_loaded( 'mod_headers' ) ) {
			/* translators: %s: mod_rewrite or mod_headers */
			$header_error = '<p><strong>' . sprintf( esc_html( 'Your site appears to be missing %s, please contact your webhost or system administrator to enable this Apache module.' ), 'mod_headers' ) . "</strong><br>$false_positive_headers</p>\n";
			ewwwio_debug_message( 'webp missing mod_headers' );
		}

		$webp_verified = ewww_image_optimizer_test_webp_mime_verify();
		if ( ! $webp_verified ) {
			$output[] = $header_error;
		}

		$output[] = "<form id='ewww-webp-rewrite'>\n";
		$output[] = '<p>' . esc_html__( 'There are many ways to serve WebP images to visitors with supported browsers. You may choose any you wish, but it is recommended to serve them with an .htaccess file using mod_rewrite and mod_headers. The plugin can insert the rules for you if the file is writable, or you can edit .htaccess yourself.', 'ewww-image-optimizer' ) . "</p>\n";

		if ( $webp_verified || ! ewww_image_optimizer_webp_rewrite_verify() ) {
			$output[] = "<img id='webp-image' src='" . plugins_url( '/images/test.png', __FILE__ ) . '?m=' . time() . "' style='float: right; padding: 0 0 10px 10px;'>\n" .
				"<p id='ewww-webp-rewrite-status'><b>" . esc_html__( 'Rules verified successfully', 'ewww-image-optimizer' ) . "</b></p>\n" .
				"<button type='button' id='ewww-webp-remove' class='button-secondary action'>" . esc_html__( 'Remove Rewrite Rules', 'ewww-image-optimizer' ) . "</button>\n";
			ewwwio_debug_message( 'webp .htaccess rewriting enabled' );
		} else {
			$output[] = "<pre id='webp-rewrite-rules' style='background: white; font-color: black; border: 1px solid black; clear: both; padding: 10px;'>\n" .
				"&lt;IfModule mod_rewrite.c&gt;\n" .
				"RewriteEngine On\n" .
				"RewriteCond %{HTTP_ACCEPT} image/webp\n" .
				"RewriteCond %{REQUEST_FILENAME} (.*)\.(jpe?g|png)$\n" .
				"RewriteCond %{REQUEST_FILENAME}\.webp -f\n" .
				"RewriteCond %{QUERY_STRING} !type=original\n" .
				"RewriteRule (.+)\.(jpe?g|png)$ %{REQUEST_FILENAME}.webp [T=image/webp,E=accept:1,L]\n" .
				"&lt;/IfModule&gt;\n" .
				"&lt;IfModule mod_headers.c&gt;\n" .
				"Header append Vary Accept env=REDIRECT_accept\n" .
				"&lt;/IfModule&gt;\n" .
				"AddType image/webp .webp</pre>\n" .
				"<img id='webp-image' src='" . plugins_url( '/images/test.png', __FILE__ ) . '?m=' . time() . "' style='float: right; padding-left: 10px;'>\n" .
				"<p id='ewww-webp-rewrite-status'>" . esc_html__( 'The image to the right will display a WebP image with WEBP in white text, if your site is serving WebP images and your browser supports WebP.', 'ewww-image-optimizer' ) . "</p>\n" .
				"<button type='button' id='ewww-webp-insert' class='button-secondary action'>" . esc_html__( 'Insert Rewrite Rules', 'ewww-image-optimizer' ) . "</button>\n";
			ewwwio_debug_message( 'webp .htaccess rules not detected' );
		}
		$output[] = "</form>\n";
	} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) && ! ewww_image_optimizer_ce_webp_enabled() ) {
		$test_webp_image = plugins_url( '/images/test.png.webp', __FILE__ );
		$test_png_image  = plugins_url( '/images/test.png', __FILE__ );
		$output[]        = "<noscript  data-img='$test_png_image' data-webp='$test_webp_image' data-style='float: right; padding: 0 0 10px 10px;' class='ewww_webp'><img src='$test_png_image' style='float: right; padding: 0 0 10px 10px;'></noscript>\n";
	}
	$output[] = "</div><!-- end container wrap -->\n";
	ewwwio_debug_message( 'max_execution_time: ' . ini_get( 'max_execution_time' ) );
	ewww_image_optimizer_stl_check();
	ewww_image_optimizer_function_exists( 'sleep', true );
	ewwwio_check_memory_available();
	if ( 'debug-silent' === $network ) {
		ewww_image_optimizer_temp_debug_clear();
		return;
	}
	$output = apply_filters( 'ewww_image_optimizer_settings', $output );
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) && ! ewww_image_optimizer_ce_webp_enabled() && ! ewww_image_optimizer_easy_active() ) {
		global $eio_alt_webp;
		$eio_alt_webp->inline_script();
	}

	global $eio_debug;
	if ( ! empty( $eio_debug ) ) {
		$debug_output = '<p style="clear:both"><b>' . esc_html__( 'Debugging Information', 'ewww-image-optimizer' ) . ':</b> <button id="ewww-copy-debug" class="button button-secondary" type="button">' . esc_html__( 'Copy', 'ewww-image-optimizer' ) . '</button>';
		if ( ewwwio_is_file( WP_CONTENT_DIR . '/ewww/debug.log' ) ) {
			$debug_output .= "&emsp;<a href='admin.php?action=ewww_image_optimizer_view_debug_log'>" . esc_html( 'View Debug Log', 'ewww-image-optimizer' ) . "</a> - <a href='admin.php?action=ewww_image_optimizer_delete_debug_log'>" . esc_html( 'Remove Debug Log', 'ewww-image-optimizer' ) . '</a>';
		}
		$debug_output .= '</p>';
		$debug_output .= '<div id="ewww-debug-info" style="border:1px solid #e5e5e5;background:#fff;overflow:auto;height:300px;width:800px;" contenteditable="true">' . $eio_debug . '</div>';

		$output = str_replace( 'DEBUG_PLACEHOLDER', $debug_output, $output );
	} else {
		$output = str_replace( 'DEBUG_PLACEHOLDER', '', $output );
	}

	echo $output;

	if ( false && ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_help' ) ) {
		$current_user = wp_get_current_user();
		$help_email   = $current_user->user_email;
		if ( ! empty( $eio_debug ) ) {
			$hs_debug = str_replace( array( "'", '<br>' ), array( '', '\n' ), $eio_debug );
		}
		?>
<script type="text/javascript">!function(e,t,n){function a(){var e=t.getElementsByTagName("script")[0],n=t.createElement("script");n.type="text/javascript",n.async=!0,n.src="https://beacon-v2.helpscout.net",e.parentNode.insertBefore(n,e)}if(e.Beacon=n=function(t,n,a){e.Beacon.readyQueue.push({method:t,options:n,data:a})},n.readyQueue=[],"complete"===t.readyState)return a();e.attachEvent?e.attachEvent("onload",a):e.addEventListener("load",a,!1)}(window,document,window.Beacon||function(){});</script>
<script type="text/javascript">
	window.Beacon('init', 'aa9c3d3b-d4bc-4e9b-b6cb-f11c9f69da87');
	Beacon( 'prefill', {
		email: '<?php echo utf8_encode( $help_email ); ?>',
	});
	Beacon( 'session-data', {
		'Debug Info': '<?php echo $hs_debug; ?>',
	});
</script>
		<?php
	}
	$help_instructions = esc_html__( 'Debugging information will be included with your message automatically.', 'ewww-image-optimizer' );
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_help' ) ) {
		$current_user = wp_get_current_user();
		$help_email   = $current_user->user_email;
		$hs_config    = array(
			'color'             => '#3eadc9',
			'icon'              => 'buoy',
			'instructions'      => $help_instructions,
			'poweredBy'         => false,
			'showContactFields' => true,
			'showSubject'       => true,
			'topArticles'       => true,
			'zIndex'            => 100000,
		);
		$hs_identify  = array(
			'email' => utf8_encode( $help_email ),
		);
		if ( ! empty( $eio_debug ) ) {
			$eio_debug_array = explode( '<br>', $eio_debug );
			$eio_debug_i     = 0;
			foreach ( $eio_debug_array as $eio_debug_line ) {
				$hs_identify[ 'debug_info_' . $eio_debug_i ] = $eio_debug_line;
				$eio_debug_i++;
			}
		}
		?>
<script type='text/javascript'>
	!function(e,o,n){window.HSCW=o,window.HS=n,n.beacon=n.beacon||{};var t=n.beacon;t.userConfig={},t.readyQueue=[],t.config=function(e){this.userConfig=e},t.ready=function(e){this.readyQueue.push(e)},o.config={docs:{enabled:!0,baseUrl:"//ewwwio.helpscoutdocs.com/"},contact:{enabled:!0,formId:"af75cf17-310a-11e7-9841-0ab63ef01522"}};var r=e.getElementsByTagName("script")[0],c=e.createElement("script");c.type="text/javascript",c.async=!0,c.src="https://djtflbt20bdde.cloudfront.net/",r.parentNode.insertBefore(c,r)}(document,window.HSCW||{},window.HS||{});
	HS.beacon.config(<?php echo json_encode( $hs_config ); ?>);
	HS.beacon.ready(function() {
		HS.beacon.identify(
			<?php echo json_encode( $hs_identify ); ?>
		);
	});
</script>
		<?php
	}
	ewwwio_memory( __FUNCTION__ );
	ewww_image_optimizer_temp_debug_clear();
}

/**
 * Filters through the settings page to remove unneeded settings for the -cloud plugin.
 *
 * @param array $input The output of the settings page, broken up into an array.
 * @return string The filtered output for the settings page.
 */
function ewww_image_optimizer_filter_settings_page( $input ) {
	$output = '';
	foreach ( $input as $line ) {
		if ( ewww_image_optimizer_full_cloud() && preg_match( '/nocloud/', $line ) ) {
			continue;
		} else {
			$output .= $line;
		}
	}
	ewwwio_memory( __FUNCTION__ );
	return $output;
}

/**
 * Filters through the multisite settings page to remove unneeded settings in multisite mode.
 *
 * @param array $input The output of the settings page, broken up into an array.
 * @return string The filtered output for the settings page.
 */
function ewww_image_optimizer_filter_network_settings_page( $input ) {
	$output = array();
	foreach ( $input as $line ) {
		if ( strpos( $line, 'network-singlesite' ) ) {
			continue;
		} else {
			$output[] = $line;
		}
	}
	ewwwio_memory( __FUNCTION__ );
	return $output;
}

/**
 * Filters through the single-site settings page to remove unneeded settings in multisite mode.
 *
 * @param array $input The output of the settings page, broken up into an array.
 * @return string The filtered output for the settings page.
 */
function ewww_image_optimizer_filter_network_singlesite_settings_page( $input ) {
	$output = array();
	foreach ( $input as $line ) {
		if ( ! get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) && strpos( $line, 'network-multisite' ) ) {
			continue;
		} elseif ( strpos( $line, 'network-only' ) ) {
			continue;
		} else {
			$output[] = $line;
		}
	}
	ewwwio_memory( __FUNCTION__ );
	return $output;
}

/**
 * Displays a help icon linked to the docs.
 *
 * @param string $link A link to the documentation.
 * @param string $hsid The HelpScout ID for the docs article. Optional.
 * @return string An HTML hyperlink element with a help icon.
 */
function ewwwio_help_link( $link, $hsid = '' ) {
	$help_icon   = plugins_url( '/images/question-circle.png', __FILE__ );
	$beacon_attr = '';
	$link_class  = 'ewww-help-icon';
	if ( strpos( $hsid, ',' ) ) {
		$beacon_attr = " data-beacon-articles='$hsid'";
		$link_class  = 'ewww-help-beacon-multi';
	} elseif ( $hsid ) {
		$beacon_attr = " data-beacon-article='$hsid'";
		$link_class  = 'ewww-help-beacon-single';
	}
	return "<a class='$link_class' href='$link' target='_blank' style='margin: 3px'$beacon_attr><img title='" . esc_attr__( 'Help', 'ewww-image-optimizer' ) . "' src='$help_icon'></a>";
}

/**
 * Checks to see if ExactDN or Easy I.O. is active.
 */
function ewww_image_optimizer_easy_active() {
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) || get_option( 'easyio_exactdn' ) ) {
		return true;
	}
	return false;
}

/**
 * Removes the API key currently installed.
 *
 * @param boolean|string $redirect Should the plugin do a silent redirect back to the referring page? Default true.
 */
function ewww_image_optimizer_remove_cloud_key( $redirect = true ) {
	$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' );
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
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_exceeded', 0 );
	delete_transient( 'ewww_image_optimizer_cloud_status' );
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_backup_files', '' );
	if ( 'none' !== $redirect ) {
		$sendback = wp_get_referer();
		wp_redirect( esc_url_raw( $sendback ) );
		exit;
	}
}

/**
 * Loads script to detect scaled images within the page, only enabled for admins.
 */
function ewww_image_optimizer_resize_detection_script() {
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', 'edit_others_posts' ) ) || 'wp-login.php' === basename( $_SERVER['SCRIPT_NAME'] ) ) {
		return;
	}
	if ( ewww_image_optimizer_is_amp() ) {
		return;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_detection' ) ) {
		$resize_detection_script = file_get_contents( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'includes/resize_detection.js' );
		echo "<style>\n" .
			"img.scaled-image {\n" .
			"\tborder: 3px #3eadc9 dotted;\n" .
			"\tmargin: -3px;\n" .
			"}\n" .
			"</style>\n";
		echo '<script type="text/javascript">' . $resize_detection_script . '</script>';
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
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', 'edit_others_posts' ) ) || ! is_admin_bar_showing() || 'wp-login.php' === basename( $_SERVER['SCRIPT_NAME'] ) || is_admin() ) {
		return;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_detection' ) ) {
		add_action( 'admin_bar_menu', 'ewww_image_optimizer_admin_bar_menu', 99 );
	}
}

/**
 * Adds a resize detection button to the wp admin bar.
 */
function ewww_image_optimizer_admin_bar_menu() {
	global $wp_admin_bar;
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
	if ( ! empty( $eio_debug ) && empty( $ewwwio_temp_debug ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && is_writable( WP_CONTENT_DIR . '/ewww/' ) ) {
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
	}
	$eio_debug = '';
	ewwwio_memory( __FUNCTION__ );
}

/**
 * View the debug.log file from the wp-admin.
 */
function ewww_image_optimizer_view_debug_log() {
	$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' );
	if ( false === current_user_can( $permissions ) ) {
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
	$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' );
	if ( false === current_user_can( $permissions ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	if ( ewwwio_is_file( WP_CONTENT_DIR . '/ewww/debug.log' ) ) {
		unlink( WP_CONTENT_DIR . '/ewww/debug.log' );
	}
	$sendback = wp_get_referer();
	wp_redirect( esc_url_raw( $sendback ) );
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
 * Displays backtraces and information on images that have been optimized multiple times.
 *
 * @global object $wpdb
 * @global object $ewwwdb A clone of $wpdb unless it is lacking utf8 connectivity.
 */
function ewww_image_optimizer_dynamic_image_debug() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	echo '<div class="wrap"><h1>' . esc_html__( 'Dynamic Image Debugging', 'ewww-image-optimizer' ) . '</h1>';
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	$debug_images = $ewwwdb->get_results( "SELECT path,updates,updated,trace FROM $ewwwdb->ewwwio_images WHERE trace IS NOT NULL ORDER BY updates DESC LIMIT 100" );
	if ( ewww_image_optimizer_iterable( $debug_images ) ) {
		foreach ( $debug_images as $image ) {
			$trace = unserialize( $image->trace );
			echo '<p><b>' . esc_html__( 'File path', 'ewww-image-optimizer' ) . ': ' . ewww_image_optimizer_absolutize_path( $image->path ) . '</b><br>';
			echo esc_html__( 'Number of attempted optimizations', 'ewww-image-optimizer' ) . ': ' . $image->updates . '<br>';
			echo esc_html__( 'Last attempted', 'ewww-image-optimizer' ) . ': ' . $image->updated . '<br>';
			echo esc_html__( 'PHP trace', 'ewww-image-optimizer' ) . ':<br>';
			$i = 0;
			if ( is_array( $trace ) ) {
				foreach ( $trace as $function ) {
					if ( ! empty( $function['file'] ) && ! empty( $function['line'] ) ) {
						echo "#$i {$function['function']}() called at {$function['file']}:{$function['line']}<br>";
					} else {
						echo "#$i {$function['function']}() called<br>";
					}
					$i++;
				}
			} else {
				esc_html_e( 'Cannot display trace', 'ewww-image-optimizer' );
			}
			echo '</p>';
		}
	}
	echo '</div>';
}

/**
 * Displays images that are in the optimization queue.
 *
 * Allows viewing the image queues for debugging, and lets the user clear all queues.
 *
 * @global object $wpdb
 * @global object $ewwwio_media_background Background optimization class object.
 */
function ewww_image_optimizer_image_queue_debug() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewwwio_media_background;
	if ( ! class_exists( 'WP_Background_Process' ) ) {
		require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
	}
	if ( ! is_object( $ewwwio_media_background ) ) {
		$ewwwio_media_background = new EWWWIO_Media_Background_Process();
	}
	// Let user clear a queue, or all queues.
	if ( isset( $_POST['ewww_image_optimizer_clear_queue'] ) && current_user_can( 'manage_options' ) && wp_verify_nonce( $_POST['ewww_nonce'], 'ewww_image_optimizer_clear_queue' ) ) {
		if ( is_numeric( $_POST['ewww_image_optimizer_clear_queue'] ) ) {
			$queues = (int) $_POST['ewww_image_optimizer_clear_queue'];
			while ( $queues ) {
				$ewwwio_media_background->cancel_process();
				$queues--;
			}
			if ( ! empty( $_POST['ids'] ) && preg_match( '/^[\d,]+$/', $_POST['ids'], $request_ids ) ) {
				$ids = explode( ',', $request_ids[0] );
				foreach ( $ids as $id ) {
					delete_transient( 'ewwwio-background-in-progress-' . $id );
				}
			}
		} else {
			delete_site_option( sanitize_text_field( $_POST['ewww_image_optimizer_clear_queue'] ) );
			if ( ! empty( $_POST['ids'] ) && preg_match( '/^[\d,]+$/', $_POST['ids'], $request_ids ) ) {
				$ids = explode( ',', $request_ids[0] );
				foreach ( $ids as $id ) {
					delete_transient( 'ewwwio-background-in-progress-' . $id );
				}
			}
		}
	}
	echo "<div class='wrap'><h1>" . esc_html__( 'Image Queue Debugging', 'ewww-image-optimizer' ) . '</h1>';
	global $wpdb;

	$table = $wpdb->options;

	$key    = 'wp_ewwwio_media_optimize_batch_%';
	$queues = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT *
			FROM $wpdb->options
			WHERE option_name LIKE %s AND option_value != ''
			ORDER BY option_id ASC",
			$key
		),
		ARRAY_A
	);

	$nonce = wp_create_nonce( 'ewww_image_optimizer_clear_queue' );
	if ( empty( $queues ) ) {
		esc_html_e( 'Nothing to see here, go upload some images!', 'ewww-image-optimizer' );
	} else {
		$queue_count = $ewwwio_media_background->count_queue();
		echo "<p><strong>$queue_count</strong> items in all queues</p>";
		$all_ids = array();
		foreach ( $queues as $queue ) {
			$ids = array();
			echo "<strong>{$queue['option_id']}</strong> - {$queue['option_name']}<br>";
			$items = maybe_unserialize( $queue['option_value'] );
			foreach ( $items as $item ) {
				echo "{$item['id']} - {$item['type']}<br>";
				$all_ids[] = $item['id'];
				$ids[]     = $item['id'];
			}
			$queue_count = count( $ids );
			echo "<strong>$queue_count</strong> items in queue<br>";
			$ids = implode( ',', $ids );
			?>
		<form id="ewww-queue-clear-<?php echo $queue['option_id']; ?>" method="post" style="margin-bottom: 1.5em;" action="">
			<input type="hidden" id="ewww_nonce" name="ewww_nonce" value="<?php echo $nonce; ?>">
			<input type="hidden" name="ewww_image_optimizer_clear_queue" value="<?php echo $queue['option_name']; ?>">
			<input type="hidden" name="ids" value="<?php echo $ids; ?>">
			<button type="submit" class="button-secondary action"><?php esc_html_e( 'Clear this queue', 'ewww-image-optimizer' ); ?></button>
		</form>
			<?php
		}
		$all_ids = implode( ',', $all_ids );
		?>
		<form id="ewww-queue-clear-all" method="post" style="margin: 2em 0;" action="">
			<input type="hidden" id="ewww_nonce" name="ewww_nonce" value="<?php echo $nonce; ?>">
			<input type="hidden" name="ewww_image_optimizer_clear_queue" value="<?php echo count( $queues ); ?>">
			<input type="hidden" name="ids" value="<?php echo $all_ids; ?>">
			<button type="submit" class="button-secondary action"><?php esc_html_e( 'Clear all queues', 'ewww-image-optimizer' ); ?></button>
		</form>
<?php	}
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
	if ( strpos( $memory_limit, 'G' ) ) {
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
