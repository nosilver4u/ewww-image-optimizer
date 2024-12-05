<?php
/**
 * Common functions for Standard plugin.
 *
 * This file contains functions that are shared by both EWWW IO plugin(s), back when we had a
 * Cloud version. Functions that differed between the two are stored in unique.php.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelTag;

/*
 * Hooks
 */

// Runs other checks that need to run on 'init'.
add_action( 'init', 'ewww_image_optimizer_init', 9 );
// Load our front-end parsers for ExactDN, Lazy Load and WebP.
add_action( 'init', 'ewww_image_optimizer_parser_init', 99 );
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
	// Processes an attachment after Crop Thumbnails plugin has modified the images.
	add_filter( 'crop_thumbnails_before_update_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15, 2 );
	// Process BuddyPress uploads from Vikinger theme.
	add_action( 'vikinger_file_uploaded', 'ewww_image_optimizer' );
	// Process image after resize by Imsanity.
	add_action( 'imsanity_post_process_attachment', 'ewww_image_optimizer_optimize_by_id', 10, 2 );
}

// Ensures we update the filesize data in the meta.
// add_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_update_filesize_metadata', 9, 2 );
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
// Disable core WebP generation since we already do that.
add_filter( 'wp_upload_image_mime_transforms', '__return_empty_array' );
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
// Allows the user to override the default AVIF quality used by EWWW IO.
add_filter( 'avif_quality', 'ewww_image_optimizer_set_avif_quality' );
// Prevent WP from over-riding EWWW IO's resize settings.
add_filter( 'big_image_size_threshold', 'ewww_image_optimizer_adjust_big_image_threshold', 10, 3 );
// Makes sure the plugin bypasses any files affected by the Folders to Ignore setting.
add_filter( 'ewww_image_optimizer_bypass', 'ewww_image_optimizer_ignore_file', 10, 2 );
// Makes sure the plugin bypasses any WebP files generated by WebP Conversion.
add_filter( 'ewww_image_optimizer_bypass', 'ewww_image_optimizer_ignore_converted_webp_images', 10, 2 );
// Ensure we populate the queue with webp images for WP Offload S3.
add_filter( 'as3cf_attachment_file_paths', 'ewww_image_optimizer_as3cf_attachment_file_paths', 10, 2 );
// Make sure to remove webp images from remote storage when an attachment is deleted.
add_filter( 'as3cf_remove_source_files_from_provider', 'ewww_image_optimizer_as3cf_remove_source_files' );
// Fix the ContentType for WP Offload S3 on WebP images.
add_filter( 'as3cf_object_meta', 'ewww_image_optimizer_as3cf_object_meta' );
// Built-in check for whitelabel constant.
add_filter( 'ewwwio_whitelabel', 'ewwwio_is_whitelabel' );
// Get admin color scheme and save it for later.
add_action( 'admin_head', 'ewww_image_optimizer_save_admin_colors' );
// Legacy (non-AJAX) action hook for manually optimizing an image.
add_action( 'admin_action_ewww_image_optimizer_manual_optimize', 'ewww_image_optimizer_manual' );
// Legacy (non-AJAX) action hook for manually restoring a converted image.
add_action( 'admin_action_ewww_image_optimizer_manual_restore', 'ewww_image_optimizer_manual' );
// Legacy (non-AJAX) action hook for manually restoring a backup from the API.
add_action( 'admin_action_ewww_image_optimizer_manual_image_restore', 'ewww_image_optimizer_manual' );
// Cleanup routine when an attachment is deleted.
add_action( 'delete_attachment', 'ewww_image_optimizer_delete', 21 );
// Cleanup db records when Enable Media Replace replaces a file.
add_action( 'wp_handle_replace', 'ewww_image_optimizer_media_replace' );
// Cleanup db records when Phoenix Media Rename is finished.
add_action( 'pmr_renaming_successful', 'ewww_image_optimizer_media_rename', 10, 2 );
// Cleanup db records when Image Regenerate & Select Crop deletes a file.
add_action( 'sirsc_image_file_deleted', 'ewww_image_optimizer_irsc_file_deleted', 10, 2 );
// Cleanup db records when Force Regenerate Thumbnails deletes a file.
add_action( 'regenerate_thumbs_post_delete', 'ewww_image_optimizer_file_deleted' );
// Adds the EWWW IO pages to the admin menu.
add_action( 'admin_menu', 'ewww_image_optimizer_admin_menu', 60 );
// Adds the EWWW IO settings to the network admin menu.
add_action( 'network_admin_menu', 'ewww_image_optimizer_network_admin_menu' );
// Handle the bulk actions from the media library.
add_filter( 'handle_bulk_actions-upload', 'ewww_image_optimizer_bulk_action_handler', 10, 3 );
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
// AJAX action hook to remove a specific site from Easy IO.
add_action( 'wp_ajax_ewww_exactdn_deregister_site', 'ewww_image_optimizer_exactdn_deregister_site_ajax' );
// AJAX action hook for inserting WebP rewrite rules into .htaccess.
add_action( 'wp_ajax_ewww_webp_rewrite', 'ewww_image_optimizer_webp_rewrite' );
// AJAX action hook for removing WebP rewrite rules from .htaccess.
add_action( 'wp_ajax_ewww_webp_unwrite', 'ewww_image_optimizer_webp_unwrite' );
// AJAX action hook for manually optimizing/converting an image.
add_action( 'wp_ajax_ewww_manual_optimize', 'ewww_image_optimizer_manual' );
// AJAX action hook for manually restoring a converted image.
add_action( 'wp_ajax_ewww_manual_restore', 'ewww_image_optimizer_manual' );
// AJAX action hook for manually restoring an attachment from local/cloud backups.
add_action( 'wp_ajax_ewww_manual_image_restore', 'ewww_image_optimizer_manual' );
// AJAX action hook for fetching the optimization status of an image attachment.
add_action( 'wp_ajax_ewww_manual_get_status', 'ewww_image_optimizer_ajax_get_attachment_status' );
// AJAX action hook to dismiss the WooCommerce regen notice.
add_action( 'wp_ajax_ewww_dismiss_wc_regen', 'ewww_image_optimizer_dismiss_wc_regen' );
// AJAX action hook to dismiss the WP/LR Sync regen notice.
add_action( 'wp_ajax_ewww_dismiss_lr_sync', 'ewww_image_optimizer_dismiss_lr_sync' );
// AJAX action hook to disable the media library notice.
add_action( 'wp_ajax_ewww_dismiss_media_notice', 'ewww_image_optimizer_dismiss_media_notice' );
// AJAX action hook to disable the 'review request' notice.
add_action( 'wp_ajax_ewww_dismiss_review_notice', 'ewww_image_optimizer_dismiss_review_notice' );
// AJAX action hook to disable the newsletter signup banner.
add_action( 'wp_ajax_ewww_dismiss_newsletter', 'ewww_image_optimizer_dismiss_newsletter_signup' );
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
// Non-AJAX handler to disable debugging mode.
add_action( 'admin_action_ewww_image_optimizer_disable_debugging', 'ewww_image_optimizer_disable_debugging' );
// Non-AJAX handler to view the debug log, and display it.
add_action( 'admin_action_ewww_image_optimizer_view_debug_log', 'ewww_image_optimizer_view_debug_log' );
// Non-AJAX handler to delete the debug log, and reroute back to the settings page.
add_action( 'admin_action_ewww_image_optimizer_delete_debug_log', 'ewww_image_optimizer_delete_debug_log' );
// Non-AJAX handler to download the debug log.
add_action( 'admin_action_ewww_image_optimizer_download_debug_log', 'ewww_image_optimizer_download_debug_log' );
// Non-AJAX handler to apply 6.2 current_timestamp db upgrade.
add_action( 'admin_action_ewww_image_optimizer_620_upgrade', 'ewww_image_optimizer_620_upgrade' );
// Check if WebP option was turned off and is now enabled.
add_action( 'update_option_ewww_image_optimizer_webp', 'ewww_image_optimizer_webp_maybe_enabled', 10, 2 );
// Check Scheduled Opt option has just been disabled and clear the queues/stop the process.
add_action( 'update_option_ewww_image_optimizer_auto', 'ewww_image_optimizer_scheduled_optimization_changed', 10, 2 );
// Check if image resize dimensions have been changed.
add_action( 'update_option_ewww_image_optimizer_maxmediawidth', 'ewww_image_optimizer_resize_dimensions_changed', 10, 2 );
add_action( 'update_option_ewww_image_optimizer_maxmediaheight', 'ewww_image_optimizer_resize_dimensions_changed', 10, 2 );
// Makes sure to flush out any scheduled jobs on deactivation.
register_deactivation_hook( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE, 'ewww_image_optimizer_network_deactivate' );
// add_action( 'shutdown', 'ewwwio_memory_output' );.
// Makes sure we flush the debug info to the log on shutdown.
add_action( 'shutdown', 'ewww_image_optimizer_debug_log' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewwwio-cli.php';
}

/**
 * Setup page parsing classes early, but after theme functions.php is loaded and plugins have loaded.
 */
function ewww_image_optimizer_parser_init() {
	$buffer_start = false;
	// If ExactDN is enabled.
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) && false === strpos( add_query_arg( '', '' ), 'exactdn_disable=1' ) ) {
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
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-page-parser.php';
		/**
		 * ExactDN class for parsing image urls and rewriting them.
		 */
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-exactdn.php';
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
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-page-parser.php';
		/**
		 * Lazy Load class for parsing image urls and rewriting them to defer off-screen images.
		 */
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-lazy-load.php';

		global $eio_lazy_load;
		$eio_lazy_load = new EWWW\Lazy_Load();
	}
	// If JS WebP Rewriting is enabled.
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		$buffer_start = true;
		/**
		 * Page Parsing class for working with HTML content.
		 */
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-page-parser.php';
		/**
		 * JS WebP class for parsing image urls and rewriting them for WebP support.
		 */
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-js-webp.php';
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_picture_webp' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		$buffer_start = true;
		/**
		 * Page Parsing class for working with HTML content.
		 */
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-page-parser.php';
		/**
		 * Picture WebP class for parsing img elements and rewriting them with WebP URLs.
		 */
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-picture-webp.php';
	}
	if ( $buffer_start ) {
		// Start an output buffer before any output starts.
		add_action( 'template_redirect', 'ewww_image_optimizer_buffer_start', 0 );
		if ( wp_doing_ajax() && apply_filters( 'eio_filter_admin_ajax_response', false ) ) {
			add_action( 'admin_init', 'ewww_image_optimizer_buffer_start', 0 );
		}
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
 * Skips optimization of WebP images that were created by EWWW IO (or possibly another IO plugin).
 *
 * @param bool   $skip Defaults to false.
 * @param string $filename The name of the file about to be optimized.
 * @return bool True if the file is a WebP image EWWW IO created, unaltered otherwise.
 */
function ewww_image_optimizer_ignore_converted_webp_images( $skip, $filename ) {
	if ( apply_filters( 'ewwwio_optimize_converted_webp_images', false ) ) {
		return $skip;
	}
	if ( preg_match( '/\.(jpe?g|png|gif)\.webp$/i', $filename ) ) {
		ewwwio_debug_message( "skipping converted WebP: $filename" );
		return true;
	}
	if ( str_ends_with( $filename, '.webp' ) ) {
		$extensionless       = ewwwio()->remove_from_end( $filename, '.webp' );
		$original_image_path = '';
		$original_extensions = array(
			'jpg',
			'jpeg',
			'png',
			'gif',
		);
		foreach ( $original_extensions as $original_extension ) {
			if ( is_file( $extensionless . '.' . $original_extension ) ) {
				ewwwio_debug_message( "skipping converted WebP: $filename" );
				return true;
			}
			if ( is_file( $extensionless . '.' . strtoupper( $original_extension ) ) ) {
				ewwwio_debug_message( "skipping converted WebP: $filename" );
				return true;
			}
		}
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
		if ( \get_option( 'jetpack_boost_status_lazy-images' ) ) {
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
		! ewwwio()->local->exec_check() &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) &&
		! ewwwio()->imagick_supports_webp() &&
		! ewwwio()->gd_supports_webp()
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

if ( ! function_exists( 'str_ends_with' ) ) {
	/**
	 * Polyfill for `str_ends_with()` function added in WP 5.9 or PHP 8.0.
	 *
	 * Performs a case-sensitive check indicating if
	 * the haystack ends with needle.
	 *
	 * @since 6.8.1
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for in the `$haystack`.
	 * @return bool True if `$haystack` ends with `$needle`, otherwise false.
	 */
	function str_ends_with( $haystack, $needle ) {
		if ( '' === $haystack && '' !== $needle ) {
			return false;
		}

		$len = strlen( $needle );

		return 0 === substr_compare( $haystack, $needle, -$len, $len );
	}
}

/**
 * Find out if set_time_limit() is allowed.
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
	return ewwwio()->function_exists( '\set_time_limit' );
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
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
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
			$ewww_image_optimizer_webp_level = empty( $_POST['ewww_image_optimizer_webp_level'] ) ? '' : (int) $_POST['ewww_image_optimizer_webp_level'];
			update_site_option( 'ewww_image_optimizer_webp_level', $ewww_image_optimizer_webp_level );
			$ewww_image_optimizer_delete_originals = ( empty( $_POST['ewww_image_optimizer_delete_originals'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_delete_originals', $ewww_image_optimizer_delete_originals );
			$ewww_image_optimizer_jpg_to_png = ( empty( $_POST['ewww_image_optimizer_jpg_to_png'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_jpg_to_png', $ewww_image_optimizer_jpg_to_png );
			$ewww_image_optimizer_png_to_jpg = ( empty( $_POST['ewww_image_optimizer_png_to_jpg'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_png_to_jpg', $ewww_image_optimizer_png_to_jpg );
			$ewww_image_optimizer_gif_to_png = ( empty( $_POST['ewww_image_optimizer_gif_to_png'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_gif_to_png', $ewww_image_optimizer_gif_to_png );
			$ewww_image_optimizer_bmp_convert = ( empty( $_POST['ewww_image_optimizer_bmp_convert'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_bmp_convert', $ewww_image_optimizer_bmp_convert );
			$ewww_image_optimizer_webp = ( empty( $_POST['ewww_image_optimizer_webp'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_webp', $ewww_image_optimizer_webp );
			$ewww_image_optimizer_jpg_background = empty( $_POST['ewww_image_optimizer_jpg_background'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['ewww_image_optimizer_jpg_background'] ) );
			update_site_option( 'ewww_image_optimizer_jpg_background', ewww_image_optimizer_jpg_background( $ewww_image_optimizer_jpg_background ) );
			$ewww_image_optimizer_sharpen = empty( $_POST['ewww_image_optimizer_sharpen'] ) ? false : true;
			update_site_option( 'ewww_image_optimizer_sharpen', $ewww_image_optimizer_sharpen );
			$ewww_image_optimizer_jpg_quality = empty( $_POST['ewww_image_optimizer_jpg_quality'] ) ? '' : (int) $_POST['ewww_image_optimizer_jpg_quality'];
			update_site_option( 'ewww_image_optimizer_jpg_quality', ewww_image_optimizer_jpg_quality( $ewww_image_optimizer_jpg_quality ) );
			$ewww_image_optimizer_webp_quality = empty( $_POST['ewww_image_optimizer_webp_quality'] ) ? '' : (int) $_POST['ewww_image_optimizer_webp_quality'];
			update_site_option( 'ewww_image_optimizer_webp_quality', ewww_image_optimizer_webp_quality( $ewww_image_optimizer_webp_quality ) );
			$ewww_image_optimizer_avif_quality = empty( $_POST['ewww_image_optimizer_avif_quality'] ) ? '' : (int) $_POST['ewww_image_optimizer_avif_quality'];
			update_site_option( 'ewww_image_optimizer_avif_quality', ewww_image_optimizer_avif_quality( $ewww_image_optimizer_avif_quality ) );
			$ewww_image_optimizer_disable_convert_links = ( empty( $_POST['ewww_image_optimizer_disable_convert_links'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_disable_convert_links', $ewww_image_optimizer_disable_convert_links );
			$ewww_image_optimizer_backup_files = ( empty( $_POST['ewww_image_optimizer_backup_files'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['ewww_image_optimizer_backup_files'] ) ) );
			update_site_option( 'ewww_image_optimizer_backup_files', $ewww_image_optimizer_backup_files );
			$ewww_image_optimizer_auto = ( empty( $_POST['ewww_image_optimizer_auto'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_auto', $ewww_image_optimizer_auto );
			$ewww_image_optimizer_aux_paths = empty( $_POST['ewww_image_optimizer_aux_paths'] ) ? '' : sanitize_textarea_field( wp_unslash( $_POST['ewww_image_optimizer_aux_paths'] ) );
			update_site_option( 'ewww_image_optimizer_aux_paths', ewww_image_optimizer_aux_paths_sanitize( $ewww_image_optimizer_aux_paths ) );
			$ewww_image_optimizer_exclude_paths = empty( $_POST['ewww_image_optimizer_exclude_paths'] ) ? '' : sanitize_textarea_field( wp_unslash( $_POST['ewww_image_optimizer_exclude_paths'] ) );
			update_site_option( 'ewww_image_optimizer_exclude_paths', ewww_image_optimizer_exclude_paths_sanitize( $ewww_image_optimizer_exclude_paths ) );
			$exactdn_all_the_things = ( empty( $_POST['exactdn_all_the_things'] ) ? false : true );
			update_site_option( 'exactdn_all_the_things', $exactdn_all_the_things );
			$exactdn_lossy = ( empty( $_POST['exactdn_lossy'] ) ? false : true );
			update_site_option( 'exactdn_lossy', $exactdn_lossy );
			$exactdn_hidpi = ( empty( $_POST['exactdn_hidpi'] ) ? false : true );
			update_site_option( 'exactdn_hidpi', $exactdn_hidpi );
			$exactdn_exclude = empty( $_POST['exactdn_exclude'] ) ? '' : sanitize_textarea_field( wp_unslash( $_POST['exactdn_exclude'] ) );
			update_site_option( 'exactdn_exclude', ewww_image_optimizer_exclude_paths_sanitize( $exactdn_exclude ) );
			$ewww_image_optimizer_add_missing_dims = ( empty( $_POST['ewww_image_optimizer_add_missing_dims'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_add_missing_dims', $ewww_image_optimizer_add_missing_dims );
			$ewww_image_optimizer_lazy_load = ( empty( $_POST['ewww_image_optimizer_lazy_load'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_lazy_load', $ewww_image_optimizer_lazy_load );
			$ewww_image_optimizer_ll_autoscale = ( empty( $_POST['ewww_image_optimizer_ll_autoscale'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_ll_autoscale', $ewww_image_optimizer_ll_autoscale );
			$ewww_image_optimizer_ll_abovethefold = ( empty( $_POST['ewww_image_optimizer_ll_abovethefold'] ) ? 0 : (int) $_POST['ewww_image_optimizer_ll_abovethefold'] );
			update_site_option( 'ewww_image_optimizer_ll_abovethefold', $ewww_image_optimizer_ll_abovethefold );
			$ewww_image_optimizer_use_lqip = ( empty( $_POST['ewww_image_optimizer_use_lqip'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_use_lqip', $ewww_image_optimizer_use_lqip );
			$ewww_image_optimizer_use_dcip = ( empty( $_POST['ewww_image_optimizer_use_dcip'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_use_dcip', $ewww_image_optimizer_use_dcip );
			// Using sanitize_text_field instead of textarea on purpose.
			$ewww_image_optimizer_ll_all_things = empty( $_POST['ewww_image_optimizer_ll_all_things'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['ewww_image_optimizer_ll_all_things'] ) );
			update_site_option( 'ewww_image_optimizer_ll_all_things', $ewww_image_optimizer_ll_all_things );
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
			$ewww_image_optimizer_allow_tracking = empty( $_POST['ewww_image_optimizer_allow_tracking'] ) ? false : ewwwio()->tracking->check_for_settings_optin( (bool) $_POST['ewww_image_optimizer_allow_tracking'] );
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
			$ewww_image_optimizer_allow_tracking = empty( $_POST['ewww_image_optimizer_allow_tracking'] ) ? false : ewwwio()->tracking->check_for_settings_optin( (bool) $_POST['ewww_image_optimizer_allow_tracking'] );
			update_site_option( 'ewww_image_optimizer_allow_tracking', $ewww_image_optimizer_allow_tracking );
			add_action( 'network_admin_notices', 'ewww_image_optimizer_network_settings_saved' );
		} // End if().
	} // End if().
	if ( is_multisite() && get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_svg_level' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_level' )
	) {
		ewwwio()->set_defaults();
		update_option( 'ewww_image_optimizer_disable_pngout', true );
		update_option( 'ewww_image_optimizer_disable_svgcleaner', true );
		update_option( 'ewww_image_optimizer_optipng_level', 2 );
		update_option( 'ewww_image_optimizer_pngout_level', 2 );
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_option( 'ewww_image_optimizer_jpg_level', '10' );
		update_option( 'ewww_image_optimizer_png_level', '10' );
		update_option( 'ewww_image_optimizer_gif_level', '10' );
		update_option( 'ewww_image_optimizer_svg_level', 0 );
		update_option( 'ewww_image_optimizer_webp_level', 0 );
	}
}

/**
 * Runs early for checks that need to happen on init before anything else.
 */
function ewww_image_optimizer_init() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );

	// For the settings page, check for the enable-local param and take appropriate action.
	if ( ! empty( $_GET['enable-local'] ) && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' ) ) {
		update_option( 'ewww_image_optimizer_ludicrous_mode', true );
		update_site_option( 'ewww_image_optimizer_ludicrous_mode', true );
	} elseif ( isset( $_GET['enable-local'] ) && ! (bool) $_GET['enable-local'] && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' ) ) {
		delete_option( 'ewww_image_optimizer_ludicrous_mode' );
		delete_site_option( 'ewww_image_optimizer_ludicrous_mode' );
	}
	if ( ! empty( $_GET['complete_wizard'] ) && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' ) ) {
		update_option( 'ewww_image_optimizer_wizard_complete', true, false );
	}
	if ( ! empty( $_GET['uncomplete_wizard'] ) && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' ) ) {
		update_option( 'ewww_image_optimizer_wizard_complete', false, false );
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
		if ( wp_doing_ajax() && ! empty( $_POST['ewwwio_test_verify'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$ewwwio_upgrading = true;
		ewww_image_optimizer_install_table();
		ewwwio()->set_defaults();
		ewww_image_optimizer_enable_background_optimization();
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
		if (
			get_option( 'ewww_image_optimizer_version' ) < 661 &&
			get_option( 'ewww_image_optimizer_exactdn' ) &&
			! ewww_image_optimizer_get_option( 'ewww_image_optimizer_ludicrous_mode' ) &&
			! ewww_image_optimizer_get_option( 'exactdn_lossy' )
		) {
			ewww_image_optimizer_set_option( 'exactdn_lossy', true );
		}
		if (
			get_option( 'ewww_image_optimizer_version' ) <= 670 &&
			ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' )
		) {
			$backup_mode = ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' );
			if ( 'local' !== $backup_mode && 'cloud' !== $backup_mode ) {
				ewww_image_optimizer_set_option( 'ewww_image_optimizer_backup_files', 'cloud' );
			}
		}
		if ( get_option( 'ewww_image_optimizer_local_mode' ) || get_site_option( 'ewww_image_optimizer_local_mode' ) ) {
			update_option( 'ewww_image_optimizer_ludicrous_mode', true );
			update_site_option( 'ewww_image_optimizer_ludicrous_mode', true );
			delete_option( 'ewww_image_optimizer_local_mode' );
			delete_site_option( 'ewww_image_optimizer_local_mode' );
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
		// Nuke the (legacy) bulk queue during an upgrade, if nothing is running.
		if ( ! get_option( 'ewww_image_optimizer_bulk_resume' ) && ! get_option( 'ewww_image_optimizer_aux_resume' ) ) {
			ewww_image_optimizer_delete_queue_images();
		}
		if ( is_file( EWWWIO_CONTENT_DIR . 'debug.log' ) && is_writable( EWWWIO_CONTENT_DIR . 'debug.log' ) ) {
			unlink( EWWWIO_CONTENT_DIR . 'debug.log' );
		}
		ewww_image_optimizer_remove_obsolete_settings();
		update_option( 'ewww_image_optimizer_version', EWWW_IMAGE_OPTIMIZER_VERSION );
	}
	ewwwio_memory( __FUNCTION__ );
}

/**
 * Tests background optimization.
 *
 * Send a known packet to admin-ajax.php via the EWWW\Async_Test_Request class.
 */
function ewww_image_optimizer_enable_background_optimization() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ewww_image_optimizer_detect_wpsf_location_lock() ) {
		return;
	}
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_background_optimization', false );
	ewwwio_debug_message( 'running test async handler' );
	ewwwio()->async_test_request->data( array( 'ewwwio_test_verify' => '949c34123cf2a4e4ce2f985135830df4a1b2adc24905f53d2fd3f5df5b162932' ) )->dispatch();
	ewww_image_optimizer_debug_log();
}

/**
 * Re-tests background optimization at a user's request.
 */
function ewww_image_optimizer_retest_background_optimization() {
	check_admin_referer( 'ewww_image_optimizer_options-options' );
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
	check_admin_referer( 'ewww_image_optimizer_options-options' );
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
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
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
	$content  = '<p class="privacy-policy-tutorial">';
	$content .= wp_kses_post( __( 'By default, the EWWW Image Optimizer does not store any personal data nor share it with anyone.', 'ewww-image-optimizer' ) ) . '</p><p>';
	$content .= wp_kses_post( __( 'If you accept user-submitted images and use the API or Easy IO, those images may be transmitted to third-party servers in foreign countries. If Backup Originals is enabled, images are stored for 30 days. Otherwise, no images are stored on the API for longer than 30 minutes.', 'ewww-image-optimizer' ) ) . '</p>';
	$content .= '<p><strong>' . esc_html__( 'Suggested API Text:', 'ewww-image-optimizer' ) . '</strong> <i>' . esc_html__( 'User-submitted images may be transmitted to image compression servers in the United States and stored there for up to 30 days.', 'ewww-image-optimizer' ) . '</i></p>';
	$content .= '<p><strong>' . esc_html__( 'Suggested Easy IO Text:', 'ewww-image-optimizer' ) . '</strong> <i>' . esc_html__( 'User-submitted images that are displayed on this site will be transmitted and stored on a global network of third-party servers (a CDN).', 'ewww-image-optimizer' ) . '</i></p>';
	wp_add_privacy_policy_content( 'EWWW Image Optimizer', $content );
}

/**
 * Check the current screen, currently used to temporarily enable debugging on settings page.
 *
 * @param object $screen Information about the page/screen currently being loaded.
 */
function ewww_image_optimizer_current_screen( $screen ) {
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
		return;
	}
	if ( false !== strpos( $screen->id, 'settings_page_ewww-image-optimizer' ) ) {
		return;
	}
	if ( false !== strpos( $screen->id, 'media_page_ewww-image-optimizer-bulk' ) ) {
		return;
	}
	EWWW\Base::$debug_data = '';
	EWWW\Base::$temp_debug = false;
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
	list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
	ewwwio_debug_message( "retrieved file path: $file_path" );
	$supported_types = ewwwio()->get_supported_types();
	$type            = ewww_image_optimizer_mimetype( $file_path );
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
		if ( get_option( 'ewww_image_optimizer_version' ) < 340 ) {
			// Make sure there are valid dates in updated column.
			$wpdb->query( "UPDATE $wpdb->ewwwio_images SET updated = '1971-01-01 00:00:00' WHERE updated < '1001-01-01 00:00:01'" );
		}
		// Get the current table layout.
		$suppress    = $wpdb->suppress_errors();
		$tablefields = $wpdb->get_results( "DESCRIBE {$wpdb->ewwwio_images};" );
		$wpdb->suppress_errors( $suppress );
		$timestamp_upgrade_needed = false;
		if ( ewww_image_optimizer_iterable( $tablefields ) ) {
			foreach ( $tablefields as $tablefield ) {
				if (
					'updated' === $tablefield->Field && // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					false === stripos( $tablefield->Default, 'current_timestamp' ) && // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					false === stripos( $tablefield->Default, 'now' ) // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				) {
					$timestamp_upgrade_needed = true;
					ewwwio_debug_message( 'updated timestamp upgrade needed' );
				}
				if ( 'results' === $tablefield->Field ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images DROP COLUMN results" );
				}
			}
		}
		if (
			(
				false !== strpos( $mysql_version, '5.6.' ) ||
				false !== strpos( $mysql_version, '5.7.' ) ||
				false !== strpos( $mysql_version, '10.1.' )
			) &&
			$timestamp_upgrade_needed
		) {
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->ewwwio_images" );
			if ( is_multisite() || $count < 10000 ) {
				// Do the upgrade in real-time for multi-site and sites with less than 10k image records.
				$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images MODIFY updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP" );
			} else {
				// Do it later via user interaction.
				set_transient( 'ewww_image_optimizer_620_upgrade_needed', true );
			}
		} elseif ( $timestamp_upgrade_needed ) {
			$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images ALTER updated SET DEFAULT (CURRENT_TIMESTAMP)" );
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
		image_size int unsigned,
		orig_size int unsigned,
		backup varchar(100),
		retrieve varchar(100),
		level int unsigned,
		resized_width smallint unsigned,
		resized_height smallint unsigned,
		resize_error tinyint unsigned,
		webp_size int unsigned,
		webp_error tinyint unsigned,
		pending tinyint NOT NULL DEFAULT 0,
		updates int unsigned,
		updated timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		trace blob,
		$primary_key_definition
		KEY path (path($path_index_size)),
		KEY attachment_info (gallery(3),attachment_id)
	) $db_collation;";

	// Include the upgrade library to install/upgrade a table.
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
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
		convert_once tinyint NOT NULL DEFAULT 0,
		force_reopt tinyint NOT NULL DEFAULT 0,
		force_smart tinyint NOT NULL DEFAULT 0,
		webp_only tinyint NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY attachment_info (gallery(3),attachment_id)
	) COLLATE utf8_general_ci;";

	// Include the upgrade library to install/upgrade a table.
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
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
					ewwwio()->background_media->push_to_queue(
						array(
							'id'  => $item['id'],
							'new' => 0,
						)
					);
				}
			}
			ewwwio()->background_media->dispatch();
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
 * @param mixed $old_value The old value, also a boolean generally.
 * @param mixed $new_value The new value, in this case it will be boolean usually.
 */
function ewww_image_optimizer_webp_maybe_enabled( $old_value, $new_value ) {
	if ( ! empty( $new_value ) && (bool) $new_value !== (bool) $old_value ) {
		update_option( 'ewww_image_optimizer_webp_enabled', true );
	}
}

/**
 * Checks to see if Scheduled Optimization was just disabled.
 *
 * @param mixed $old_value The old value, also a boolean generally.
 * @param mixed $new_value The new value, in this case it will be boolean usually.
 */
function ewww_image_optimizer_scheduled_optimization_changed( $old_value, $new_value ) {
	if ( empty( $new_value ) && (bool) $new_value !== (bool) $old_value ) {
		ewwwio()->background_image->cancel_process();
		update_option( 'ewwwio_stop_scheduled_scan', true, false );
	}
}

/**
 * Checks to see if Resize Images dimensions have been modified.
 *
 * If resize dimensions are modified, then we clear out the legacy settings,
 * as it is highly likely the admin wants something different now.
 *
 * @param mixed $old_value The old value, also a boolean generally.
 * @param mixed $new_value The new value, in this case it will be boolean usually.
 */
function ewww_image_optimizer_resize_dimensions_changed( $old_value, $new_value ) {
	if ( (int) $new_value !== (int) $old_value ) {
		update_option( 'ewww_image_optimizer_maxotherwidth', 0 );
		update_option( 'ewww_image_optimizer_maxotherheight', 0 );
		update_site_option( 'ewww_image_optimizer_maxotherwidth', 0 );
		update_site_option( 'ewww_image_optimizer_maxotherheight', 0 );
	}
}

/**
 * Display a notice that the user should run the bulk optimizer after WebP activation.
 */
function ewww_image_optimizer_notice_webp_bulk() {
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		return;
	}
	$already_done = ewww_image_optimizer_aux_images_table_count();
	if ( ewww_image_optimizer_background_mode_enabled() ) {
		$bulk_link = admin_url( 'options-general.php?page=ewww-image-optimizer-options&bulk_optimize=1' );
	} else {
		$bulk_link = admin_url( 'upload.php?page=ewww-image-optimizer-bulk' );
	}
	if ( $already_done > 50 ) {
		$bulk_link = add_query_arg(
			array(
				'ewww_webp_only' => 1,
				'ewww_force'     => 1,
			),
			$bulk_link
		);
		echo "<div id='ewww-image-optimizer-pngout-success' class='notice notice-info'><p><a href='" .
			esc_url( $bulk_link ) .
			"'>" . esc_html__( 'It looks like you already started optimizing your images, you will need to generate WebP images via the Bulk Optimizer.', 'ewww-image-optimizer' ) . '</a></p></div>';
	} else {
		echo "<div id='ewww-image-optimizer-pngout-success' class='notice notice-info'><p><a href='" .
			esc_url( $bulk_link ) .
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
				( ! empty( $_REQUEST['ewww_error'] ) ? esc_html( sanitize_text_field( wp_unslash( $_REQUEST['ewww_error'] ) ) ) : esc_html__( 'unknown error', 'ewww-image-optimizer' ) ), // phpcs:ignore WordPress.Security.NonceVerification
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
				( ! empty( $_REQUEST['ewww_error'] ) ? esc_html( sanitize_text_field( wp_unslash( $_REQUEST['ewww_error'] ) ) ) : esc_html__( 'unknown error', 'ewww-image-optimizer' ) ), // phpcs:ignore WordPress.Security.NonceVerification
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
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		return;
	}
	global $exactdn;
	if ( ! isset( $exactdn->upload_domain ) ) {
		return;
	}
	$stored_local_domain = $exactdn->get_exactdn_option( 'local_domain' );
	if ( empty( $stored_local_domain ) ) {
		return;
	}
	if ( false === strpos( $stored_local_domain, '.' ) ) {
		$stored_local_domain = base64_decode( $stored_local_domain );
	}
	?>
	<div id="ewww-image-optimizer-notice-exactdn-domain-mismatch" class="notice notice-warning">
		<p>
	<?php
			printf(
				/* translators: 1: old domain name, 2: current domain name */
				esc_html__( 'Easy IO detected that the Site URL has changed since the initial activation (previously %1$s, currently %2$s).', 'ewww-image-optimizer' ),
				'<strong>' . esc_html( $stored_local_domain ) . '</strong>',
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
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		return;
	}
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
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		return;
	}
	echo "<div id='ewww-image-optimizer-exactdn-sp' class='notice notice-warning'><p>" . esc_html__( 'ShortPixel image optimization has been disabled to prevent conflicts with Easy IO (EWWW Image Optimizer).', 'ewww-image-optimizer' ) . '</p></div>';
}

/**
 * Display a notice that debugging mode is enabled.
 */
function ewww_image_optimizer_debug_enabled_notice() {
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		return;
	}
	?>
	<div id="ewww-image-optimizer-notice-debug" class="notice notice-info">
		<p>
			<?php esc_html_e( 'Debug mode is enabled in the EWWW Image Optimizer settings. Please be sure to turn Debugging off when you are done troubleshooting.', 'ewww-image-optimizer' ); ?>
			<a class='button button-secondary' href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_disable_debugging' ), 'ewww_image_optimizer_options-options' ) ); ?>'>
				<?php esc_html_e( 'Disable Debugging', 'ewww-image-optimizer' ); ?>
			</a>
		</p>
	</div>
	<?php
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
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		return;
	}
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
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		return;
	}
	echo "<div id='ewww-image-optimizer-wc-regen' class='notice notice-info is-dismissible'><p>" . esc_html__( 'EWWW Image Optimizer has detected a WooCommerce thumbnail regeneration. To optimize new thumbnails, you may run the Bulk Optimizer from the Media menu. This notice may be dismissed after the regeneration is complete.', 'ewww-image-optimizer' ) . '</p></div>';
}

/**
 * Loads the inline script to dismiss the WC regen notice.
 */
function ewww_image_optimizer_wc_regen_script() {
	?>
	<script>
		jQuery(document).on('click', '#ewww-image-optimizer-wc-regen .notice-dismiss', function() {
			var ewww_dismiss_wc_regen_data = {
				action: 'ewww_dismiss_wc_regen',
				_wpnonce: <?php echo wp_json_encode( wp_create_nonce( 'ewww-image-optimizer-notice' ) ); ?>,
			};
			jQuery.post(ajaxurl, ewww_dismiss_wc_regen_data, function(response) {
				if (response) {
					console.log(response);
				}
			});
		});
	</script>
	<?php
}

/**
 * Lets the user know LR Sync has regenerated thumbnails and that they need to take action.
 */
function ewww_image_optimizer_notice_lr_sync() {
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		return;
	}
	echo "<div id='ewww-image-optimizer-lr-sync' class='notice notice-info is-dismissible'><p>" . esc_html__( 'EWWW Image Optimizer has detected a WP/LR Sync process. To optimize new thumbnails, you may run the Bulk Optimizer from the Media menu. This notice may be dismissed after the Sync process is complete.', 'ewww-image-optimizer' ) . '</p></div>';
}

/**
 * Loads the inline script to dismiss the LR sync notice.
 */
function ewww_image_optimizer_lr_sync_script() {
	?>
	<script>
		jQuery(document).on('click', '#ewww-image-optimizer-lr-sync .notice-dismiss', function() {
			var ewww_dismiss_lr_sync_data = {
				action: 'ewww_dismiss_lr_sync',
				_wpnonce: <?php echo wp_json_encode( wp_create_nonce( 'ewww-image-optimizer-notice' ) ); ?>,
			};
			jQuery.post(ajaxurl, ewww_dismiss_lr_sync_data, function(response) {
				if (response) {
					console.log(response);
				}
			});
		});
	</script>
	<?php
}
/**
 * Requires the removal of Animated Gif Resize plugin.
 */
function ewww_image_optimizer_notice_agr() {
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		return;
	}
	?>
	<div id="ewww-image-optimizer-notice-agr" class="notice notice-warning">
		<p>
			<?php esc_html_e( 'GIF animations are preserved by EWWW Image Optimizer automatically. Please remove the Animated GIF Resize plugin.', 'ewww-image-optimizer' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Let the user know they can view more options and stats in the Media Library's list mode.
 */
function ewww_image_optimizer_notice_media_listmode() {
	$current_screen = get_current_screen();
	if ( 'upload' === $current_screen->id && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_dismiss_media_notice' ) ) {
		if ( 'list' === get_user_option( 'media_library_mode', get_current_user_id() ) ) {
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
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		return;
	}
	echo "<div id='ewww-image-optimizer-upgrade-notice' class='notice notice-info'><p>" .
		esc_html__( 'EWWW Image Optimizer needs to upgrade the image log table.', 'ewww-image-optimizer' ) . '<br>' .
		'<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_620_upgrade' ), 'ewww_image_optimizer_options-options' ) ) . '" class="button-secondary">' .
		esc_html__( 'Upgrade', 'ewww-image-optimizer' ) . '</a>' .
		'</p></div>';
}

/**
 * Ask the user to leave a review for the plugin on wp.org.
 */
function ewww_image_optimizer_notice_review() {
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		return;
	}
	echo "<div id='ewww-image-optimizer-review' class='notice notice-info is-dismissible'><p>" .
		esc_html__( "Hi, you've been using the EWWW Image Optimizer for a while, and we hope it has been a big help for you.", 'ewww-image-optimizer' ) . '<br>' .
		esc_html__( 'If you could take a few moments to rate it on WordPress.org, we would really appreciate your help making the plugin better. Thanks!', 'ewww-image-optimizer' ) .
		'<br><a target="_blank" href="https://wordpress.org/support/plugin/ewww-image-optimizer/reviews/#new-post" class="button-secondary">' . esc_html__( 'Post Review', 'ewww-image-optimizer' ) . '</a>' .
		'</p></div>';
}

/**
 * Add review link to the footer on our pages.
 *
 * @param string $footer_text The existing footer text.
 * @return string The modified footer text.
 */
function ewww_image_optimizer_footer_review_text( $footer_text ) {
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_dismiss_review_notice' ) ) {
		return $footer_text;
	}
	$review_text = esc_html__( 'Thank you for using EWWW Image Optimizer!', 'ewww-image-optimizer' ) . ' <a target="_blank" href="https://wordpress.org/support/plugin/ewww-image-optimizer/reviews/#new-post">' . esc_html__( 'Please rate us on WordPress.org', 'ewww-image-optimizer' ) . '</a>';
	return str_replace( '</span>', '', $footer_text ) . ' | ' . $review_text . '</span>';
}

/**
 * Loads the inline script to dismiss the review notice.
 */
function ewww_image_optimizer_notice_review_script() {
	?>
	<script>
		jQuery(document).on('click', '#ewww-image-optimizer-review .notice-dismiss', function() {
			var ewww_dismiss_review_data = {
				action: 'ewww_dismiss_review_notice',
				_wpnonce: <?php echo wp_json_encode( wp_create_nonce( 'ewww-image-optimizer-notice' ) ); ?>,
			};
			jQuery.post(ajaxurl, ewww_dismiss_review_data, function(response) {
				if (response) {
					console.log(response);
				}
			});
		});
	</script>
	<?php
}

/**
 * Inform the user of our beacon function so that they can opt-in.
 */
function ewww_image_optimizer_notice_beacon() {
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		return;
	}
	echo '<div id="ewww-image-optimizer-hs-beacon" class="notice notice-info"><p>' .
		esc_html__( 'Enable the EWWW I.O. support beacon, which gives you access to documentation and our support team right from your WordPress dashboard. To assist you more efficiently, we collect the current url, IP address, browser/device information, and debugging information.', 'ewww-image-optimizer' ) .
		'<br><a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?action=eio_opt_into_hs_beacon' ), 'ewww_image_optimizer_options-options' ) ) . '" class="button-secondary">' . esc_html__( 'Allow', 'ewww-image-optimizer' ) . '</a>' .
		'&nbsp;<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?action=eio_opt_out_of_hs_beacon' ), 'ewww_image_optimizer_options-options' ) ) . '" class="button-secondary">' . esc_html__( 'Do not allow', 'ewww-image-optimizer' ) . '</a>' .
		'</p></div>';
}

/**
 * Alert the user when 5 images have been re-optimized more than 10 times.
 *
 * @global object $wpdb
 */
function ewww_image_optimizer_notice_reoptimization() {
	return; // This is already disabled at the admin_notice hook registration above, but just to be sure.
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		return;
	}
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
			$reset_page     = wp_nonce_url( add_query_arg( '', '' ), 'reset_reoptimization_counters', 'ewww_reset_reopt_nonce' );
			// Display an alert, and let the user reset the warning if they wish.
			echo "<div id='ewww-image-optimizer-warning-reoptimizations' class='error'><p>" .
				sprintf(
					/* translators: %s: A link to the EWWW IO Tools page */
					esc_html__( 'The EWWW Image Optimizer has detected excessive re-optimization of multiple images. Please use the %s page to Show Re-Optimized Images.', 'ewww-image-optimizer' ),
					"<a href='" . esc_url( $debugging_page ) . "'>" . esc_html__( 'Tools', 'ewww-image-optimizer' ) . '</a>'
				) .
				" <a href='" . esc_url( $reset_page ) . "'>" . esc_html__( 'Reset Counters', 'ewww-image-optimizer' ) . '</a></p></div>';
		}
	}
}

/**
 * Checks if a plugin is offloading media to cloud storage and removing local copies.
 *
 * @return bool True if a plugin is removing local files, false otherwise.
 */
function ewww_image_optimizer_cloud_based_media() {
	if ( class_exists( 'Amazon_S3_And_CloudFront' ) ) {
		global $as3cf;
		if ( is_object( $as3cf ) && $as3cf->get_setting( 'serve-from-s3' ) && $as3cf->get_setting( 'remove-local-file' ) ) {
			return true;
		}
	}
	if ( ewww_image_optimizer_s3_uploads_enabled() ) {
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
 * Loads the class to extend WP_Image_Editor for automatic optimization of generated images.
 *
 * @param array $editors List of image editors available to WordPress.
 * @return array Modified list of editors, with our custom class added at the top.
 */
function ewww_image_optimizer_load_editor( $editors ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( class_exists( 'S3_Uploads\Image_Editor_Imagick', false ) ) {
		return $editors;
	}
	if ( ! class_exists( 'EWWWIO_GD_Editor' ) && class_exists( 'WP_Image_Editor_GD' ) ) {
		require_once plugin_dir_path( __FILE__ ) . '/classes/class-ewwwio-gd-editor.php';
	}
	if ( ! class_exists( 'EWWWIO_Imagick_Editor' ) && class_exists( 'WP_Image_Editor_Imagick' ) ) {
		require_once plugin_dir_path( __FILE__ ) . '/classes/class-ewwwio-imagick-editor.php';
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_sharpen' ) ) {
			ewww_image_optimizer_sharpen_filters();
		}
	}
	if ( ! class_exists( 'EWWWIO_Gmagick_Editor' ) && class_exists( 'WP_Image_Editor_Gmagick' ) ) {
		require_once plugin_dir_path( __FILE__ ) . '/classes/class-ewwwio-gmagick-editor.php';
	}
	if ( class_exists( 'EWWWIO_GD_Editor' ) && ! in_array( 'EWWWIO_GD_Editor', $editors, true ) ) {
		array_unshift( $editors, 'EWWWIO_GD_Editor' );
	}
	if ( class_exists( 'EWWWIO_Imagick_Editor' ) && ! in_array( 'EWWWIO_Imagick_Editor', $editors, true ) ) {
		array_unshift( $editors, 'EWWWIO_Imagick_Editor' );
	}
	if ( class_exists( 'EWWWIO_Gmagick_Editor' ) && ! in_array( 'EWWWIO_Gmagick_Editor', $editors, true ) ) {
		array_unshift( $editors, 'EWWWIO_Gmagick_Editor' );
	}
	if ( is_array( $editors ) ) {
		ewwwio_debug_message( 'loading image editors: ' . implode( '<br>', $editors ) );
	}
	return $editors;
}

/**
 * Override the default resizing filter.
 *
 * @param string $filter_name The name of the constant for the specified filter.
 * @param int    $dst_w The width (in pixels) to which an image is being resized.
 * @param int    $dst_h The height (in pixels) to which an image is being resized.
 * @return string The Lanczos filter by default, unless changed by the user.
 */
function eio_change_image_resize_filter( $filter_name, $dst_w, $dst_h ) {
	/*
	 * Valid options:
	 * 'FILTER_POINT',
	 * 'FILTER_BOX',
	 * 'FILTER_TRIANGLE',
	 * 'FILTER_HERMITE',
	 * 'FILTER_HANNING',
	 * 'FILTER_HAMMING',
	 * 'FILTER_BLACKMAN',
	 * 'FILTER_GAUSSIAN',
	 * 'FILTER_QUADRATIC',
	 * 'FILTER_CUBIC',
	 * 'FILTER_CATROM',
	 * 'FILTER_MITCHELL',
	 * 'FILTER_LANCZOS',
	 * 'FILTER_BESSEL',
	 * 'FILTER_SINC'
	 */
	if ( $dst_w * $dst_h > apply_filters( 'eio_image_resize_threshold', 2000000 ) ) {
		return $filter_name;
	}
	return 'FILTER_LANCZOS';
}

/**
 * Enables the use of the adaptiveSharpenImage() function.
 *
 * @param bool $use_adaptive Whether to use the adaptiveSharpenImage() function, false by default.
 * @param int  $dst_w The width (in pixels) to which an image is being resized.
 * @param int  $dst_h The height (in pixels) to which an image is being resized.
 * @return bool True if the new image will be under 1.5 MP, false otherwise.
 */
function eio_enable_adaptive_sharpen( $use_adaptive, $dst_w, $dst_h ) {
	if ( $dst_w * $dst_h > apply_filters( 'eio_adaptive_sharpen_threshold', 1500000 ) ) {
		return false;
	}
	return true;
}

/**
 * Adjusts the sigma value for the adaptiveSharpenImage() function.
 *
 * @param float $param The sigma value for adaptiveSharpenImage().
 * @param int   $dst_w The width (in pixels) to which an image is being resized.
 * @param int   $dst_h The height (in pixels) to which an image is being resized.
 * @return float The new sigma value.
 */
function eio_adjust_adaptive_sharpen_sigma( $param, $dst_w, $dst_h ) {
	if ( $dst_w * $dst_h > apply_filters( 'eio_adaptive_sharpen_sigma_threshold', 250000 ) ) {
		return 0.5;
	}
	return $param;
}

/**
 * Add filters to improve image resizing with ImageMagick.
 */
function ewww_image_optimizer_sharpen_filters() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );

	add_filter( 'eio_image_resize_filter', 'eio_change_image_resize_filter', 9, 3 );

	add_filter( 'eio_use_adaptive_sharpen', 'eio_enable_adaptive_sharpen', 9, 3 );

	add_filter( 'eio_adaptive_sharpen_sigma', 'eio_adjust_adaptive_sharpen_sigma', 9, 3 );
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
	if ( class_exists( 'Meow_WR2X_Engine' ) ) {
		global $wr2x_engine;
		if ( is_object( $wr2x_engine ) && method_exists( $wr2x_engine, 'wp_generate_attachment_metadata' ) ) {
			ewwwio_debug_message( 'retina (engine) object found' );
			remove_filter( 'wp_generate_attachment_metadata', array( $wr2x_engine, 'wp_generate_attachment_metadata' ) );
			add_filter( 'wp_generate_attachment_metadata', 'ewww_image_optimizer_retina_wrapper' );
		}
	} elseif ( class_exists( 'Meow_WR2X_Core' ) ) {
		global $wr2x_core;
		if ( is_object( $wr2x_core ) && method_exists( $wr2x_core, 'wp_generate_attachment_metadata' ) ) {
			ewwwio_debug_message( 'retina (core) object found' );
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
		ewwwio_debug_message( "$old_filepath changed to $new_filepath" );
		// Replace the 'temp' path in the database with the real path.
		$wpdb->update(
			$wpdb->ewwwio_images,
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
	if ( class_exists( 'Meow_WR2X_Engine' ) ) {
		global $wr2x_engine;
		if ( is_object( $wr2x_engine ) && method_exists( $wr2x_engine, 'wp_generate_attachment_metadata' ) ) {
			$meta = $wr2x_engine->wp_generate_attachment_metadata( $meta );
		}
	} elseif ( class_exists( 'Meow_WR2X_Core' ) ) {
		global $wr2x_core;
		if ( is_object( $wr2x_core ) && method_exists( $wr2x_core, 'wp_generate_attachment_metadata' ) ) {
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
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ewww_image_optimizer_function_exists( 'print_r' ) ) {
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
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ewww_image_optimizer_function_exists( 'print_r' ) ) {
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
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ewww_image_optimizer_function_exists( 'print_r' ) ) {
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
		if ( ! empty( $params['url'] ) && wp_basename( $file_path ) === wp_basename( $params['url'] ) ) {
			$params['url'] = trailingslashit( dirname( $params['url'] ) ) . wp_basename( $new_image );
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
		$already_optimized = ewww_image_optimizer_find_already_optimized( $file_path );
		if ( empty( $already_optimized ) ) {
			// If the file didn't already get optimized (and it shouldn't), then just insert a dummy record to be updated shortly.
			ewwwio_debug_message( 'creating new record' );
			$dbinserted = $wpdb->insert(
				$wpdb->ewwwio_images,
				array(
					'path'      => ewww_image_optimizer_relativize_path( $file_path ),
					'converted' => '',
					'orig_size' => $orig_size,
				)
			);
			if ( $dbinserted ) {
				ewwwio_debug_message( 'insert success' );
			}
		} else {
			// Update the existing record.
			ewwwio_debug_message( 'updating existing record' );
			$dbupdated = $wpdb->update(
				$wpdb->ewwwio_images,
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
 */
function ewww_image_optimizer_auto() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( get_transient( 'ewww_image_optimizer_no_scheduled_optimization' ) ) {
		ewwwio_debug_message( 'detected bulk operation in progress, bailing' );
		return;
	}
	ewwwio()->defer = false;
	require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'bulk.php';
	require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'aux-optimize.php';
	if ( ewwwio()->background_image->is_process_running() || ewwwio()->background_image->count_queue() ) {
		return;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_auto' ) ) {
		ewwwio_debug_message( 'running scheduled optimization' );
		ewwwio()->async_scan->data(
			array(
				'ewww_scan' => 'scheduled',
			)
		)->dispatch();
	} // End if().
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
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
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
		ewwwio()->local->exec_check()
	) {
		add_media_page( esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ), esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ), $permissions, 'ewww-image-optimizer-bulk', 'ewww_image_optimizer_bulk_preview' );
		// Adds Bulk Optimize to the media library bulk actions.
		add_filter( 'bulk_actions-upload', 'ewww_image_optimizer_add_bulk_media_actions' );
	}
	add_submenu_page( '', esc_html__( 'Migrate WebP Images', 'ewww-image-optimizer' ), esc_html__( 'Migrate WebP Images', 'ewww-image-optimizer' ), $permissions, 'ewww-image-optimizer-webp-migrate', 'ewww_image_optimizer_webp_migrate_preview' );

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
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
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
		// Replace the 'temp' path in the database with the real path.
		$wpdb->update(
			$wpdb->ewwwio_images,
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
		if ( ! get_option( 'ewww_image_optimizer_bulk_resume' ) && ! get_option( 'ewww_image_optimizer_aux_resume' ) ) {
			ewww_image_optimizer_delete_queue_images();
		}
		update_option( 'ewww_image_optimizer_aux_resume', '' );
		add_thickbox();
		wp_enqueue_script( 'jquery-ui-tooltip' );
		wp_enqueue_script( 'ewww-media-script', plugins_url( '/includes/media.js', __FILE__ ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION );
		wp_enqueue_style( 'jquery-ui-tooltip-custom', plugins_url( '/includes/jquery-ui-1.10.1.custom.css', __FILE__ ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
		// Submit a couple variables to the javascript to work with.
		$loading_image = plugins_url( '/images/spinner.gif', __FILE__ );
		$async_allowed = (int) ewww_image_optimizer_test_background_opt();
		wp_localize_script(
			'ewww-media-script',
			'ewww_vars',
			array(
				'notice_nonce'  => wp_create_nonce( 'ewww-image-optimizer-notice' ),
				'optimizing'    => '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' /></p>",
				'restoring'     => '<p>' . esc_html__( 'Restoring', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' /></p>",
				'loading_img'   => "<img src='$loading_image' />",
				'async_allowed' => $async_allowed,
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
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
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
	return ewwwio()->gd_support();
}

/**
 * Check for GD support of WebP format.
 *
 * @return bool True if proper WebP support is detected.
 */
function ewww_image_optimizer_gd_supports_webp() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	return ewwwio()->gd_supports_webp();
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
	return ewwwio()->imagick_support();
}

/**
 * Check for IMagick support of WebP.
 *
 * @return bool True if WebP support is detected.
 */
function ewww_image_optimizer_imagick_supports_webp() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	return ewwwio()->imagick_supports_webp();
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
	if ( empty( $sharp_yuv ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_sharpen' ) ) {
		$sharp_yuv = true;
	}
	$quality = (int) apply_filters( 'webp_quality', 75, 'image/webp' );
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
	return ewwwio()->gmagick_support();
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
			++$i;
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
			++$i;
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
	if ( is_string( $background ) && preg_match( '/^\#*([0-9a-fA-F]){6}$/', $background ) ) {
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
		return '';
	}
}

/**
 * Retrieves/sanitizes the jpg quality setting for png2jpg conversion or returns null.
 *
 * @param int $quality The JPG quality level as set by the user.
 * @return int The sanitized JPG quality level.
 */
function ewww_image_optimizer_jpg_quality( $quality = null ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( is_null( $quality ) ) {
		// Retrieve the user-supplied value for jpg quality.
		$quality = ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_quality' );
	}
	// Verify that the quality level is an integer, 1-100.
	if ( is_numeric( $quality ) && preg_match( '/^(100|[1-9][0-9]?)$/', $quality ) ) {
		ewwwio_debug_message( "quality: $quality" );
		// Send back the valid quality level.
		ewwwio_memory( __FUNCTION__ );
		return $quality;
	} else {
		if ( ! empty( $quality ) ) {
			add_settings_error( 'ewww_image_optimizer_jpg_quality', 'ewwwio-jpg-quality', esc_html__( 'Could not save the JPG quality, please enter an integer between 1 and 100.', 'ewww-image-optimizer' ) );
		}
		// Send back nothing.
		return '';
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
 * @return int The sanitized WebP quality level.
 */
function ewww_image_optimizer_webp_quality( $quality = null ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( is_null( $quality ) ) {
		// Retrieve the user-supplied value for WebP quality.
		$quality = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_quality' );
	}
	// Verify that the quality level is an integer, 1-100.
	if ( is_numeric( $quality ) && preg_match( '/^(100|[1-9][0-9]?)$/', $quality ) ) {
		ewwwio_debug_message( "webp quality: $quality" );
		// Send back the valid quality level.
		return $quality;
	} else {
		if ( ! empty( $quality ) ) {
			add_settings_error( 'ewww_image_optimizer_webp_quality', 'ewwwio-webp-quality', esc_html__( 'Could not save the WebP quality, please enter an integer between 50 and 100.', 'ewww-image-optimizer' ) );
		}
		// Send back nothing.
		return '';
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
 * Retrieves/sanitizes the AVIF quality setting or returns null.
 *
 * @param int $quality The AVIF quality level as set by the user.
 * @return int The sanitized AVIF quality level.
 */
function ewww_image_optimizer_avif_quality( $quality = null ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( is_null( $quality ) ) {
		// Retrieve the user-supplied value for AVIF quality.
		$quality = ewww_image_optimizer_get_option( 'ewww_image_optimizer_avif_quality' );
	}
	// Verify that the quality level is an integer, 1-100.
	if ( is_numeric( $quality ) && preg_match( '/^(100|[1-9][0-9]?)$/', $quality ) ) {
		ewwwio_debug_message( "avif quality: $quality" );
		// Send back the valid quality level.
		return $quality;
	} else {
		if ( ! empty( $quality ) ) {
			add_settings_error( 'ewww_image_optimizer_avif_quality', 'ewwwio-avif-quality', esc_html__( 'Could not save the AVIF quality, please enter an integer between 50 and 100.', 'ewww-image-optimizer' ) );
		}
		// Send back nothing.
		return '';
	}
}

/**
 * Overrides the default AVIF quality (if a user-defined value is set).
 *
 * @param int $quality The default AVIF quality level.
 * @return int The default quality, or the user configured level.
 */
function ewww_image_optimizer_set_avif_quality( $quality ) {
	$new_quality = ewww_image_optimizer_avif_quality();
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
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
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
	if ( defined( 'EWWW_IMAGE_OPTIMIZER_TOOL_PATH' ) ) {
		$tool_dir = realpath( EWWW_IMAGE_OPTIMIZER_TOOL_PATH );
		$tool_dir = dirname( $tool_dir );
	}
	if ( empty( $tool_dir ) ) {
		$tool_dir = $content_dir;
	}
	if ( defined( 'EWWWIO_CONTENT_DIR' ) ) {
		$eio_content_dir = realpath( EWWWIO_CONTENT_DIR );
	}
	if ( empty( $eio_content_dir ) ) {
		$eio_content_dir = $content_dir;
	}
	$plugin_dir = realpath( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH );
	if (
		false === strpos( $dir, $upload_dir ) &&
		false === strpos( $dir, $content_dir ) &&
		false === strpos( $dir, $wp_dir ) &&
		false === strpos( $dir, $plugin_dir ) &&
		false === strpos( $dir, $tool_dir ) &&
		false === strpos( $dir, $eio_content_dir )
	) {
		return false;
	}
	return $eio_filesystem->is_dir( $dir );
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
 * Manually process an image from the Media Library
 */
function ewww_image_optimizer_manual() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewww_convert;
	ewwwio()->defer = false;
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
	$attachment_id  = (int) $_REQUEST['ewww_attachment_ID'];
	ewwwio()->force = ! empty( $_REQUEST['ewww_force'] ) ? true : false;
	$ewww_convert   = ! empty( $_REQUEST['ewww_convert'] ) ? true : false;
	// Retrieve the existing attachment metadata.
	$original_meta = wp_get_attachment_metadata( $attachment_id );
	// If the call was to optimize...
	if ( 'ewww_image_optimizer_manual_optimize' === $_REQUEST['action'] || 'ewww_manual_optimize' === $_REQUEST['action'] ) {
		ewwwio()->defer = true;
		if ( ewww_image_optimizer_test_background_opt() ) {
			add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
			ewww_image_optimizer_add_attachment_to_queue( $attachment_id, false, $ewww_convert, ewwwio()->force );
			$new_meta = $original_meta;
		} else {
			// Call the optimize from metadata function and store the resulting new metadata.
			$new_meta = ewww_image_optimizer_resize_from_meta_data( $original_meta, $attachment_id );
		}
	} elseif ( 'ewww_image_optimizer_manual_restore' === $_REQUEST['action'] || 'ewww_manual_restore' === $_REQUEST['action'] ) {
		$new_meta = ewww_image_optimizer_restore_from_meta_data( $original_meta, $attachment_id );
	} elseif ( 'ewww_image_optimizer_manual_image_restore' === $_REQUEST['action'] || 'ewww_manual_image_restore' === $_REQUEST['action'] ) {
		global $eio_backup;
		$new_meta = $eio_backup->restore_backup_from_meta_data( $attachment_id, 'media', $original_meta );
	} else {
		if ( ! wp_doing_ajax() ) {
			wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		ewwwio_ob_clean();
		wp_die( wp_json_encode( array( 'error' => esc_html__( 'Access denied.', 'ewww-image-optimizer' ) ) ) );
	}
	$basename = '';
	if ( is_array( $new_meta ) && ! empty( $new_meta['file'] ) ) {
		$basename = wp_basename( $new_meta['file'] );
	}
	// Update the attachment metadata in the database.
	$meta_saved = wp_update_attachment_metadata( $attachment_id, $new_meta );
	if ( ! $meta_saved ) {
		ewwwio_debug_message( 'failed to save meta, or no changes' );
	}
	if ( 'exceeded' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
		if ( ! wp_doing_ajax() ) {
			wp_die( wp_kses( ewww_image_optimizer_credits_exceeded() ) );
		}
		ewwwio_ob_clean();
		wp_die(
			wp_json_encode(
				array(
					'error' => ewww_image_optimizer_credits_exceeded(),
				)
			)
		);
	} elseif ( 'exceeded subkey' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
		if ( ! wp_doing_ajax() ) {
			wp_die( wp_kses( __( 'Out of credits', 'ewww-image-optimizer' ) ) );
		}
		ewwwio_ob_clean();
		wp_die(
			wp_json_encode(
				array(
					'error' => esc_html__( 'Out of credits', 'ewww-image-optimizer' ),
				)
			)
		);
	} elseif ( 'exceeded quota' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
		if ( ! wp_doing_ajax() ) {
			wp_die( wp_kses( ewww_image_optimizer_soft_quota_exceeded() ) );
		}
		ewwwio_ob_clean();
		wp_die(
			wp_json_encode(
				array(
					'error' => ewww_image_optimizer_soft_quota_exceeded(),
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
 * Retrieve results (via AJAX) for an image from the Media Library
 */
function ewww_image_optimizer_ajax_get_attachment_status() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	session_write_close();
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
	if ( empty( $_REQUEST['ewww_manual_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_manual_nonce'] ), 'ewww-manual' ) ) {
		if ( ! wp_doing_ajax() ) {
			wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		ewwwio_ob_clean();
		wp_die( wp_json_encode( array( 'error' => esc_html__( 'Access denied.', 'ewww-image-optimizer' ) ) ) );
	}
	// Store the attachment ID value.
	$attachment_id = (int) $_REQUEST['ewww_attachment_ID'];
	// Retrieve the existing attachment metadata.
	$meta     = wp_get_attachment_metadata( $attachment_id );
	$output   = ewww_image_optimizer_custom_column_capture( 'ewww-image-optimizer', $attachment_id, $meta );
	$basename = wp_basename( $meta['file'] );
	$pending  = ewww_image_optimizer_attachment_has_pending_sizes( $attachment_id );
	if ( ! $pending && ewww_image_optimizer_image_is_pending( $attachment_id, 'media-async' ) ) {
		$pending = 1;
	}
	ewwwio_ob_clean();
	wp_die(
		wp_json_encode(
			array(
				'output'   => $output,
				'basename' => $basename,
				'pending'  => (int) $pending,
			)
		)
	);
}

/**
 * Manually restore a converted image.
 *
 * @global object $wpdb
 *
 * @param array $meta The attachment metadata.
 * @param int   $id The attachment id number.
 * @return array The attachment metadata.
 */
function ewww_image_optimizer_restore_from_meta_data( $meta, $id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	$db_image = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id,path,converted FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'media' AND resize = 'full'",
			$id
		),
		ARRAY_A
	);
	if ( empty( $db_image ) || ! is_array( $db_image ) || empty( $db_image['path'] ) ) {
		// Get the filepath based on the meta and id.
		list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );

		$db_image = ewww_image_optimizer_find_already_optimized( $file_path );
		if ( empty( $db_image ) || ! is_array( $db_image ) || empty( $db_image['path'] ) ) {
			return $meta;
		}
	}
	$ewww_image = new EWWW_Image( $id, 'media', ewww_image_optimizer_absolutize_path( $db_image['path'] ) );
	remove_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_update_filesize_metadata', 9 );
	return ewww_image_optimizer_update_filesize_metadata( $ewww_image->restore_with_meta( $meta ), $id, $ewww_image->file );
}

/**
 * Manually restore an attachment from the API
 *
 * @global object $wpdb
 *
 * @param int    $id The attachment id number.
 * @param string $gallery Optional. The gallery from whence we came. Default 'media'.
 * @param array  $meta Optional. The image metadata from the postmeta table.
 * @return array The altered meta (if size differs), or the original value passed along.
 */
function ewww_image_optimizer_cloud_restore_from_meta_data( $id, $gallery = 'media', $meta = array() ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	$images = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id,path,resize,backup FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = %s",
			$id,
			$gallery
		),
		ARRAY_A
	);
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
	remove_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_update_filesize_metadata', 9 );
	$meta = ewww_image_optimizer_update_filesize_metadata( $meta, $id );
	if ( ewww_image_optimizer_s3_uploads_enabled() ) {
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
 *
 * @param int|array $image The db record/ID of the image to restore.
 * @return bool True if the image was restored successfully.
 */
function ewww_image_optimizer_cloud_restore_single_image( $image ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $eio_backup;
	global $wpdb;
	if ( ! is_array( $image ) && ! empty( $image ) && is_numeric( $image ) ) {
		$image = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id,path,backup FROM $wpdb->ewwwio_images WHERE id = %d",
				$image
			),
			ARRAY_A
		);
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
	ewwwio_debug_message( "attempted restore of {$image['path']} for $domain with {$image['backup']}" );
	if ( is_wp_error( $result ) ) {
		$error_message = $result->get_error_message();
		ewwwio_debug_message( "restore request failed: $error_message" );
		ewwwio_memory( __FUNCTION__ );
		/* translators: %s: An HTTP error message */
		$eio_backup->throw_error( sprintf( __( 'Restore failed with HTTP error: %s', 'ewww-image-optimizer' ), $error_message ) );
		return false;
	} elseif ( ! empty( $result['body'] ) && strpos( $result['body'], 'missing' ) === false ) {
		$supported_types = ewwwio()->get_supported_types( 'all' );
		if ( ! is_dir( dirname( $image['path'] ) ) ) {
			wp_mkdir_p( dirname( $image['path'] ) );
		}
		file_put_contents( $image['path'] . '.tmp', $result['body'] );
		$new_type = ewww_image_optimizer_mimetype( $image['path'] . '.tmp', 'i' );
		$old_type = '';
		if ( ewwwio_is_file( $image['path'] ) ) {
			$old_type = ewww_image_optimizer_mimetype( $image['path'], 'i' );
		}
		if ( ! in_array( $new_type, $supported_types, true ) ) {
			ewwwio_debug_message( "retrieved file had wrong type: $new_type" );
			/* translators: %s: An image filename */
			$eio_backup->throw_error( sprintf( __( 'Backup file for %s has the wrong mime type.', 'ewww-image-optimizer' ), $image['path'] ) );
			return false;
		}
		if ( empty( $old_type ) || $old_type === $new_type ) {
			ewwwio_debug_message( "appears to have valid type of $new_type, attempting to overwrite" );
			if ( ewwwio_rename( $image['path'] . '.tmp', $image['path'] ) ) {
				ewwwio_debug_message( "{$image['path']} was restored, removing .webp version and resetting db record" );
				if ( ewwwio_is_file( $image['path'] . '.webp' ) && is_writable( $image['path'] . '.webp' ) ) {
					unlink( $image['path'] . '.webp' );
				}
				// Set the results to nothing.
				$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->ewwwio_images SET image_size = 0, updates = 0, updated=updated, level = 0, resized_width = 0, resized_height = 0, resize_error = 0, webp_size = 0, webp_error = 0 WHERE id = %d", $image['id'] ) );
				return true;
			}
		}
	}
	/* translators: %s: An image filename */
	$eio_backup->throw_error( sprintf( __( 'Backup could not be retrieved for %s.', 'ewww-image-optimizer' ), $image['path'] ) );
	return false;
}

/**
 * Cleans up when an attachment is being deleted.
 *
 * Removes any .webp images, backups from conversion, and removes related database records.
 *
 * @global object $wpdb
 *
 * @param int $id The id number for the attachment being deleted.
 */
function ewww_image_optimizer_delete( $id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $eio_backup;
	global $wpdb;
	$id = (int) $id;
	// Finds non-meta images to remove from disk, and from db, as well as converted originals.
	$optimized_images = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT path,converted FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'media'",
			$id
		),
		ARRAY_A
	);
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
					$eio_backup->delete_local_backup( $image['path'] );
				}
				if ( ! empty( $image['converted'] ) ) {
					$image['converted'] = ewww_image_optimizer_absolutize_path( $image['converted'] );
					$eio_backup->delete_local_backup( $image['converted'] );
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
		ewwwio_debug_message( "removing all db records for attachment $id" );
		$wpdb->delete( $wpdb->ewwwio_images, array( 'attachment_id' => $id ) );
	}
	$s3_path = false;
	$s3_dir  = false;
	if ( ewww_image_optimizer_s3_uploads_enabled() && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) {
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
		$rows = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, post_content FROM $wpdb->posts WHERE post_content LIKE %s LIMIT 1",
				'%' . $wpdb->esc_like( $filename ) . '%'
			)
		);
		// If the original file still exists and no posts contain links to the image.
		if ( ewwwio_is_file( $file_path ) && empty( $rows ) ) {
			ewwwio_debug_message( 'removing: ' . $file_path );
			ewwwio_delete_file( $file_path );
			$eio_backup->delete_local_backup( $file_path );
			ewwwio_debug_message( "removing all db records for $file_path" );
			$wpdb->delete( $wpdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $file_path ) ) );
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
		$eio_backup->delete_local_backup( $orig_path );
		ewwwio_debug_message( "removing all db records for $orig_path" );
		$wpdb->delete( $wpdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $orig_path ) ) );
	}
	// Remove the regular image from the ewwwio_images tables.
	ewwwio_debug_message( "removing all db records for $file_path" );
	$wpdb->delete( $wpdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $file_path ) ) );
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
			$eio_backup->delete_local_backup( $base_dir . wp_basename( $data['file'] ) );
			ewwwio_debug_message( "removing all db records for {$data['file']}" );
			$wpdb->delete( $wpdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $base_dir . $data['file'] ) ) );
			// If the original resize is set, and still exists.
			if ( ! empty( $data['orig_file'] ) && ewwwio_is_file( $base_dir . $data['orig_file'] ) ) {
				unset( $srows );
				// Retrieve the filename from the metadata.
				$filename = $data['orig_file'];
				// Retrieve any posts that link the image.
				$srows = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT ID, post_content FROM $wpdb->posts WHERE post_content LIKE %s LIMIT 1",
						'%' . $wpdb->esc_like( $filename ) . '%'
					)
				);
				// If there are no posts containing links to the original, delete it.
				if ( empty( $srows ) ) {
					ewwwio_debug_message( 'removing: ' . $base_dir . $data['orig_file'] );
					ewwwio_delete_file( $base_dir . $data['orig_file'] );
					$eio_backup->delete_local_backup( $base_dir . $data['orig_file'] );
					ewwwio_debug_message( "removing all db records for {$data['orig_file']}" );
					$wpdb->delete( $wpdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $base_dir . $data['orig_file'] ) ) );
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
	$eio_backup->delete_local_backup( $file_path );
}

/**
 * Cleans up when a file has been deleted by the IRSC plugin
 *
 * Wrapper for ewww_image_optimizer_file_deleted(), which only needs a path.
 *
 * @param int    $id The id number for the attachment being deleted.
 * @param string $file The file being deleted.
 */
function ewww_image_optimizer_irsc_file_deleted( $id, $file ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewww_image_optimizer_file_deleted( $file );
}

/**
 * Cleans up when a file has been deleted.
 *
 * Removes any .webp images, backups from conversion, and removes related database records.
 *
 * @global object $wpdb
 *
 * @param string $file The file being deleted.
 */
function ewww_image_optimizer_file_deleted( $file ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $eio_backup;
	global $wpdb;
	ewwwio_debug_message( "$file was removed" );
	// Finds non-meta images to remove from disk, and from db, as well as converted originals.
	$maybe_relative_path = ewww_image_optimizer_relativize_path( $file );
	$optimized_images    = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $wpdb->ewwwio_images WHERE path = %s",
			$maybe_relative_path
		),
		ARRAY_A
	);
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
			$eio_backup->delete_local_backup( $image['converted'] );
			$wpdb->delete( $wpdb->ewwwio_images, array( 'id' => $image['id'] ) );
		}
	}
	if ( ewwwio_is_file( $file . '.webp' ) ) {
		ewwwio_delete_file( $file . '.webp' );
	}
	$eio_backup->delete_local_backup( $file );
}

/**
 * Cleans records from database when an image is about to be replaced.
 *
 * @param array $attachment An array with the attachment/image ID.
 */
function ewww_image_optimizer_media_replace( $attachment ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	$id = (int) $attachment['post_id'];
	// Finds non-meta images to remove from disk, and from db, as well as converted originals.
	$optimized_images = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT path,converted FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'media'",
			$id
		),
		ARRAY_A
	);
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
		ewwwio_debug_message( "removing all db records for attachment $id" );
		$wpdb->delete( $wpdb->ewwwio_images, array( 'attachment_id' => $id ) );
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
		$wpdb->delete( $wpdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $file_path ) ) );
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
		$wpdb->delete( $wpdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $orig_path ) ) );
	}
	// Remove the regular image from the ewwwio_images tables.
	$wpdb->delete( $wpdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $file_path ) ) );
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
			$wpdb->delete( $wpdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $base_dir . $data['file'] ) ) );
			// If the original resize is set, and still exists.
			if ( ! empty( $data['orig_file'] ) ) {
				// Retrieve the filename from the metadata.
				$filename = $data['orig_file'];
				$wpdb->delete( $wpdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $base_dir . $data['orig_file'] ) ) );
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
	if ( ! check_ajax_referer( 'phoenix_media_rename', '_wpnonce', false ) || empty( $_REQUEST['post_id'] ) ) {
		return;
	}
	$id = (int) $_REQUEST['post_id'];
	ewwwio_debug_message( "image renamed from $old_name to $new_name, looking for old records (id $id)" );
	// Finds images to remove from disk, and from db, as well as converted originals.
	$optimized_images = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id,path,resize,converted FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'media'",
			$id
		),
		ARRAY_A
	);
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
					$wpdb->update(
						$wpdb->ewwwio_images,
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
			$wpdb->delete( $wpdb->ewwwio_images, array( 'id' => $image['id'] ) );
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
		$wpdb->delete( $wpdb->ewwwio_images, array( 'path' => ewww_image_optimizer_relativize_path( $file_path ) ) );
	}
	ewww_image_optimizer_resize_from_meta_data( $meta, $id );
}

/**
 * Activates Easy IO via AJAX.
 */
function ewww_image_optimizer_exactdn_activate_ajax() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( false === current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		// Display error message if insufficient permissions.
		ewwwio_ob_clean();
		wp_die( wp_json_encode( array( 'error' => esc_html__( 'Access denied.', 'ewww-image-optimizer' ) ) ) );
	}
	// Make sure we didn't accidentally get to this page without an attachment to work on.
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
	if ( ! class_exists( 'EWWW\ExactDN' ) ) {
		/**
		 * Page Parsing class for working with HTML content.
		 */
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-page-parser.php';
		/**
		 * ExactDN class for parsing image urls and rewriting them.
		 */
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-exactdn.php';
	}
	global $exactdn;
	if ( $exactdn->get_exactdn_domain() ) {
		die( wp_json_encode( array( 'success' => esc_html__( 'Easy IO setup and verification is complete.', 'ewww-image-optimizer' ) ) ) );
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
	if ( false === current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		// Display error message if insufficient permissions.
		ewwwio_ob_clean();
		wp_die( wp_json_encode( array( 'error' => esc_html__( 'Access denied.', 'ewww-image-optimizer' ) ) ) );
	}
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
	if ( ! class_exists( 'EWWW\ExactDN' ) ) {
		/**
		 * Page Parsing class for working with HTML content.
		 */
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-page-parser.php';
		/**
		 * ExactDN class for parsing image urls and rewriting them.
		 */
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-exactdn.php';
	} elseif ( is_object( $exactdn ) ) {
		unset( $GLOBALS['exactdn'] );
		$exactdn = new EWWW\ExactDN();
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
	if ( false === current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		// Display error message if insufficient permissions.
		ewwwio_ob_clean();
		wp_die( wp_json_encode( array( 'error' => esc_html__( 'Access denied.', 'ewww-image-optimizer' ) ) ) );
	}
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
	$site_url = ewwwio()->content_url();

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
				'api_key'  => $key,
				'site_url' => $site_url,
			),
		)
	);
	return $result;
}

/**
 * Removes site from Easy IO via AJAX for a given site ID.
 */
function ewww_image_optimizer_exactdn_deregister_site_ajax() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( false === current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		// Display error message if insufficient permissions.
		ewwwio_ob_clean();
		wp_die( wp_json_encode( array( 'error' => esc_html__( 'Access denied.', 'ewww-image-optimizer' ) ) ) );
	}
	if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-settings' ) ) {
		die( wp_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) ) ) );
	}
	if ( empty( $_REQUEST['site_id'] ) ) {
		die( wp_json_encode( array( 'error' => esc_html__( 'Site ID unknown.', 'ewww-image-optimizer' ) ) ) );
	}
	$site_id = (int) $_REQUEST['site_id'];
	ewwwio_debug_message( "deregistering site $site_id" );

	$result = ewww_image_optimizer_deregister_site_post( $site_id );
	if ( is_wp_error( $result ) ) {
		$error_message = $result->get_error_message();
		ewwwio_debug_message( "de-registration failed: $error_message" );
		die(
			wp_json_encode(
				array(
					'error' => sprintf(
						/* translators: %s: an HTTP error message */
						esc_html__( 'Could not de-register site, HTTP error: %s', 'ewww-image-optimizer' ),
						$error_message
					),
				)
			)
		);
	} elseif ( ! empty( $result['body'] ) ) {
		$response = json_decode( $result['body'], true );
		if ( ! empty( $response['success'] ) ) {
			$response['success'] = esc_html__( 'Successfully removed site from Easy IO.', 'ewww-image-optimizer' );
		}
		die( wp_json_encode( $response ) );
	}
	die(
		wp_json_encode(
			array(
				'error' => esc_html__( 'Could not remove site from Easy IO: error unknown.', 'ewww-image-optimizer' ),
			)
		)
	);
}

/**
 * POSTs the site URL to the API for Easy IO registration.
 *
 * @param int $site_id The site ID for the Easy IO zone.
 * @return array The results of the http POST request.
 */
function ewww_image_optimizer_deregister_site_post( $site_id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );

	$key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	if ( empty( $key ) ) {
		return new WP_Error( 'missing_key', __( 'No API key for Easy IO removal', 'ewww-image-optimizer' ) );
	}
	ewwwio_debug_message( "removing site $site_id from Easy IO" );
	$url = 'https://optimize.exactlywww.com/exactdn/remove.php';
	add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
	$result = wp_remote_post(
		$url,
		array(
			'timeout'   => 60,
			'sslverify' => false,
			'body'      => array(
				'api_key' => $key,
				'site_id' => (int) $site_id,
			),
		)
	);
	return $result;
}

/**
 * Checks to see if a site is registered with Easy IO.
 *
 * @param string $site_url The site URL to check for an Easy IO record.
 * @return bool True if the site is already registered, false otherwise.
 */
function ewww_image_optimizer_easy_site_registered( $site_url ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $easyio_site_registered;
	global $easyio_site_id;
	if ( isset( $easyio_site_registered ) ) {
		return $easyio_site_registered;
	}
	$easyio_site_registered = false;
	$easyio_site_id         = 0;

	$cloud_key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	if ( ! empty( $cloud_key ) ) {
		ewwwio_debug_message( "checking $site_url with Easy IO" );
		$url = 'https://optimize.exactlywww.com/exactdn/show.php';
		add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
		$result = wp_remote_post(
			$url,
			array(
				'timeout'   => 30,
				'sslverify' => false,
				'body'      => array(
					'api_key'  => $cloud_key,
					'site_url' => $site_url,
				),
			)
		);
		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			ewwwio_debug_message( "register check via /show.php failed: $error_message" );
		} else {
			$easy_info = json_decode( $result['body'], true );
			if (
				is_array( $easy_info ) && ! empty( $easy_info['sites'][0]['site_url'] ) &&
				trailingslashit( $site_url ) === $easy_info['sites'][0]['site_url']
			) {
				$easyio_site_registered = true;
				$easyio_site_id         = (int) $easy_info['sites'][0]['site_id'];
				return true;
			}
			if ( is_array( $easy_info ) && ! empty( $easy_info['sites'][0]['site_url'] ) ) {
				ewwwio_debug_message( 'found (maybe different) site 0: ' . $easy_info['sites'][0]['site_url'] );
			}
		}
	}
	return false;
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
	if ( false === current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		// Display error message if insufficient permissions.
		ewwwio_ob_clean();
		wp_die( wp_json_encode( array( 'error' => esc_html__( 'Access denied.', 'ewww-image-optimizer' ) ) ) );
	}
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
		if ( preg_match( '/banned/', $verified ) ) {
			die( wp_json_encode( array( 'error' => esc_html__( 'Domain or IP address blocked, contact support for assistance.', 'ewww-image-optimizer' ) ) ) );
		}
		if ( preg_match( '/exceeded/', $verified ) ) {
			die( wp_json_encode( array( 'error' => esc_html__( 'No credits remaining for API key.', 'ewww-image-optimizer' ) ) ) );
		}
		ewwwio_debug_message( "verification success via: $url" );
		delete_option( 'ewww_image_optimizer_cloud_key_invalid' );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_key', $api_key );
		set_transient( 'ewww_image_optimizer_cloud_status', $verified, HOUR_IN_SECONDS );
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) < 20 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) < 20 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) < 20 && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_level' ) ) {
			ewww_image_optimizer_cloud_enable();
		}
		ewwwio_debug_message( "verification body contents: {$result['body']}" );
		die( wp_json_encode( array( 'success' => esc_html__( 'Successfully validated API key, happy optimizing!', 'ewww-image-optimizer' ) ) ) );
	} else {
		ewwwio_debug_message( "verification failed via: $url" );
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ewww_image_optimizer_function_exists( 'print_r' ) ) {
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
	return ewwwio()->cloud_mode;
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 10 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) > 10 ) {
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
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_webp_level', 20 );
	if ( 'local' !== ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_backup_files', 'cloud' );
	}
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
 * Submits the API key for verification.
 *
 * @param string $api_key The API key to verify.
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
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_level' ) > 0 ) {
			update_site_option( 'ewww_image_optimizer_webp_level', 0 );
			update_option( 'ewww_image_optimizer_webp_level', 0 );
		}
		if ( 'local' !== ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ) {
			update_site_option( 'ewww_image_optimizer_backup_files', '' );
			update_option( 'ewww_image_optimizer_backup_files', '' );
		}
		return false;
	}
	$ewww_cloud_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_exceeded' ) > time() && $cache ) {
		if ( empty( $ewww_cloud_status ) ) {
			set_transient( 'ewww_image_optimizer_cloud_status', 'exceeded', HOUR_IN_SECONDS );
			$ewww_cloud_status = 'exceeded';
		} else {
			set_transient( 'ewww_image_optimizer_cloud_status', $ewww_cloud_status, HOUR_IN_SECONDS );
		}
		ewwwio_debug_message( 'license exceeded notice has not expired' );
		return $ewww_cloud_status;
	}
	if ( ! ewww_image_optimizer_detect_wpsf_location_lock() && $cache && preg_match( '/great/', $ewww_cloud_status ) ) {
		ewwwio_debug_message( 'using cached verification' );
		ewwwio()->async_key_verify->dispatch();
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
	$verified = '';
	if ( is_wp_error( $result ) ) {
		$error_message = $result->get_error_message();
		ewwwio_debug_message( "verification failed via $url: $error_message" );
	} elseif ( ! empty( $result['body'] ) && 0 === strpos( $result['body'], '{' ) ) { // A non-empty response that appears to be JSON-encoded.
		$decoded    = json_decode( $result['body'], true );
		$key_status = ! empty( $decoded['status'] ) ? $decoded['status'] : '';
		// While the API may return an 'error' property/key, it has been standardized to always return a 'status'.
		// The status may be any of the following: great, exceeded, exceeded quota, exceeded subkey, invalid, expired.
		$valid_statuses = array( 'great', 'exceeded', 'exceeded quota', 'exceeded subkey' );
		ewwwio_debug_message( "key status is $verified ($url)" );
		if ( in_array( $key_status, $valid_statuses, true ) ) {
			$verified = $key_status;
			if ( false !== strpos( $verified, 'exceeded' ) ) {
				ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_exceeded', time() + 300 );
			}
			delete_option( 'ewww_image_optimizer_cloud_key_invalid' );
		} else {
			update_option( 'ewww_image_optimizer_cloud_key_invalid', true, false );
			ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_key', '' );
		}
	} else {
		update_option( 'ewww_image_optimizer_cloud_key_invalid', true, false );
		ewwwio_debug_message( "verification failed via: $url" );
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ewww_image_optimizer_function_exists( 'print_r' ) ) {
			ewwwio_debug_message( print_r( $result, true ) );
		}
	}
	if ( $verified ) {
		set_transient( 'ewww_image_optimizer_cloud_status', $verified, HOUR_IN_SECONDS );
		ewwwio_debug_message( "verification body contents: $verified" );
	}
	return $verified;
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
 * Display credits exceeded message.
 */
function ewww_image_optimizer_credits_exceeded() {
	if ( apply_filters( 'ewwwio_whitelabel', false ) ) {
		return esc_html__( 'Out of credits', 'ewww-image-optimizer' );
	}
	return '<a href="https://ewww.io/buy-credits/" target="_blank">' . esc_html__( 'License Exceeded', 'ewww-image-optimizer' ) . '</a>';
}

/**
 * Display soft quota message when exceeded.
 */
function ewww_image_optimizer_soft_quota_exceeded() {
	if ( apply_filters( 'ewwwio_whitelabel', false ) ) {
		return esc_html__( 'Out of credits', 'ewww-image-optimizer' );
	}
	return '<a href="https://docs.ewww.io/article/101-soft-quotas-on-unlimited-plans" data-beacon-article="608ddf128996210f18bd95d3" target="_blank">' . esc_html__( 'Soft quota reached, contact us for more', 'ewww-image-optimizer' ) . '</a>';
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
		$quota = json_decode( $result['body'], true );
		if ( ! is_array( $quota ) ) {
			return '';
		}
		if ( ! empty( $quota['status'] ) && 'expired' === $quota['status'] ) {
			return '';
		}
		ewwwio_memory( __FUNCTION__ );
		if ( $raw ) {
			return $quota;
		}
		if ( ! empty( $quota['unlimited'] ) && $quota['consumed'] >= 0 && isset( $quota['soft_cap'] ) ) {
			$consumed  = (int) $quota['consumed'];
			$soft_cap  = '<a title="Help" data-beacon-article="608ddf128996210f18bd95d3" href="https://docs.ewww.io/article/101-soft-quotas-on-unlimited-plans">' . (int) $quota['soft_cap'] . '</a>';
			$soft_cap .= ewwwio_get_help_link( 'https://docs.ewww.io/article/101-soft-quotas-on-unlimited-plans', '608ddf128996210f18bd95d3' );
			if ( apply_filters( 'ewwwio_whitelabel', false ) ) {
				$soft_cap = (int) $quota['soft_cap'];
			}
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
			if ( ! empty( $quota['soft_cap'] ) ) {
				$quota['consumed'] = $quota['soft_cap'] - $quota['consumed'];
			}
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
 *     @type string Set to 'exceeded' if the API key is out of credits, or 'exceeded quota' if soft quota is reached (among other errors).
 *     @type int File size of the (new) image.
 *     @type string Hash key for API backup.
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
	if ( false !== strpos( get_transient( 'ewww_image_optimizer_cloud_status' ), 'exceeded' ) ) {
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
	if ( 'exceeded subkey' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
		ewwwio_debug_message( 'license exceeded, image not processed' );
		return array( $file, false, 'exceeded subkey', 0, '' );
	}
	if ( 'exceeded' === get_transient( 'ewww_image_optimizer_cloud_status' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_exceeded' ) > time() ) {
		ewwwio_debug_message( 'license exceeded, image not processed' );
		return array( $file, false, 'exceeded', 0, '' );
	}

	global $ewww_image;
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
	} elseif ( 'image/webp' === $type && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_level' ) > 0 ) {
		$lossy = 1;
	} elseif ( 'application/pdf' === $type && 20 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) ) {
		$lossy = 1;
	} else {
		$lossy = 0;
	}
	if ( strpos( $file, '/wp-admin/' ) || strpos( $file, '/wp-includes/' ) || strpos( $file, '/wp-content/themes/' ) || strpos( $file, '/wp-content/plugins/' ) ) {
		$lossy      = 0;
		$lossy_fast = 0;
		if ( 'image/webp' === $type ) {
			return array( $file, false, '', filesize( $file ), '' );
		}
	}
	$sharp_yuv = defined( 'EIO_WEBP_SHARP_YUV' ) && EIO_WEBP_SHARP_YUV ? 1 : 0;
	if ( empty( $sharp_yuv ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_sharpen' ) ) {
		$sharp_yuv = 1;
	}
	$webp           = 0;
	$webp_width     = 0;
	$webp_height    = 0;
	$webp_crop      = 0;
	$fullsize_image = '';
	if ( 'image/webp' === $newtype ) {
		$webp        = 1;
		$jpg_quality = apply_filters( 'webp_quality', 75, 'image/webp' );
		if ( 'image/png' === $type && ( ! defined( 'EWWW_IMAGE_OPTIMIZER_LOSSY_PNG2WEBP' ) || ! EWWW_IMAGE_OPTIMIZER_LOSSY_PNG2WEBP ) ) {
			$lossy = 0;
		}
		if ( 'image/gif' === $type && defined( 'EWWW_IMAGE_OPTIMIZER_LOSSY_GIF2WEBP' ) && ! EWWW_IMAGE_OPTIMIZER_LOSSY_GIF2WEBP ) {
			$lossy = 1;
		}
		if ( 'image/jpeg' === $type ) {
			list( $webp_width, $webp_height, $webp_crop, $fullsize_image ) = ewww_image_optimizer_cloud_get_webp_params( $file );
		}
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
	$backup_exclusions = array(
		EWWWIO_CONTENT_DIR,
		'/wp-admin/',
		'/wp-includes/',
		'/wp-content/themes/',
		'/wp-content/plugins/',
		'/cache/',
		'/dynamic/', // Nextgen dynamic images.
	);
	$backup_exclusions = apply_filters( 'ewww_image_optimizer_backup_exclusions', $backup_exclusions );
	$backup_excluded   = false;
	foreach ( $backup_exclusions as $backup_exclusion ) {
		if ( false !== strpos( $file, $backup_exclusion ) ) {
			$backup_excluded = true;
		}
	}
	if ( ! $webp && 'cloud' === ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) && ! $backup_excluded ) {
		if ( is_object( $ewww_image ) && $ewww_image->file === $file && ! empty( $ewww_image->backup ) ) {
			$hash = $ewww_image->backup;
		}
		if ( empty( $hash ) && ( ! empty( ewwwio()->force ) || ! empty( ewwwio()->force_smart ) ) ) {
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
	$async = 0;
	// Make sure we already have this image pending in the db, no conversion, no webp, and processing through an allowed async method.
	if ( $api_key && is_object( $ewww_image ) && ! $convert && ! $webp && ewwwio()->cloud_async_allowed ) {
		$async = 1;
	}
	ewwwio_debug_message( "file: $file " );
	ewwwio_debug_message( "type: $type" );
	ewwwio_debug_message( "convert: $convert" );
	ewwwio_debug_message( "newfile: $newfile" );
	ewwwio_debug_message( "newtype: $newtype" );
	ewwwio_debug_message( "webp: $webp" );
	if ( $webp && $fullsize_image ) {
		ewwwio_debug_message( "fullsize: $fullsize_image" );
		ewwwio_debug_message( "width: $webp_width" );
		ewwwio_debug_message( "height: $webp_height" );
		ewwwio_debug_message( "webp crop $webp_crop" );
	}
	ewwwio_debug_message( "sharp_yuv: $sharp_yuv" );
	ewwwio_debug_message( "jpg fill: $jpg_fill" );
	ewwwio_debug_message( "jpg quality: $jpg_quality" );
	ewwwio_debug_message( "async_mode: $async" );
	$free_exec = ! ewwwio()->local->exec_check() && 'image/jpeg' === $type;
	if (
		! $free_exec &&
		! ewwwio()->local->get_path( 'jpegtran' ) &&
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
	$url = 'http://optimize.exactlywww.com/v3/optimize/';
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

	$upload_file = ! empty( $fullsize_image ) ? $fullsize_image : $file;
	$post_fields = array(
		'filename'   => $upload_file,
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
		'async'      => $async,
		'backup'     => $hash,
		'domain'     => $domain,
		// 'force_async' => 1, // for testing out async mode.
	);

	if ( $webp && $fullsize_image && $webp_width && $webp_height ) {
		$post_fields['width']  = $webp_width;
		$post_fields['height'] = $webp_height;
		$post_fields['crop']   = $webp_crop;
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
	$payload .= 'Content-Disposition: form-data; name="file"; filename="' . wp_basename( $upload_file ) . '"' . "\r\n";
	$payload .= 'Content-Type: ' . $type . "\r\n";
	$payload .= "\r\n";
	$payload .= $eio_filesystem->get_contents( $upload_file );
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
			ewwwio_delete_file( $tempfile );
		} elseif ( 100 > strlen( $response['body'] ) && strpos( $response['body'], 'exceeded quota' ) ) {
			ewwwio_debug_message( 'Soft quota Exceeded' );
			set_transient( 'ewww_image_optimizer_cloud_status', 'exceeded quota', HOUR_IN_SECONDS );
			$msg = 'exceeded quota';
			ewwwio_delete_file( $tempfile );
		} elseif ( 100 > strlen( $response['body'] ) && strpos( $response['body'], 'exceeded subkey' ) ) {
			ewwwio_debug_message( 'License Exceeded' );
			set_transient( 'ewww_image_optimizer_cloud_status', 'exceeded subkey', HOUR_IN_SECONDS );
			$msg = 'exceeded subkey';
			ewwwio_delete_file( $tempfile );
		} elseif ( 100 > strlen( $response['body'] ) && strpos( $response['body'], 'exceeded' ) ) {
			ewwwio_debug_message( 'License Exceeded' );
			set_transient( 'ewww_image_optimizer_cloud_status', 'exceeded', HOUR_IN_SECONDS );
			$msg = 'exceeded';
			ewwwio_delete_file( $tempfile );
		} elseif ( str_starts_with( $response['body'], '{' ) && strpos( $response['body'], 'location' ) ) {
			ewwwio_debug_message( 'optimization pending' );
			ewwwio_debug_message( $response['body'] );
			$api_response = json_decode( $response['body'], true );
			if ( ! empty( $api_response['id'] ) ) {
				$retrieve_id = $api_response['id'];
				ewwwio_debug_message( "received retrieval id: $retrieve_id" );
				if ( is_object( $ewww_image ) && $ewww_image->file === $file ) {
					ewwwio_debug_message( 'stashing retrieval id' );
					$ewww_image->retrieve = $retrieve_id;
					if ( empty( $ewww_image->backup ) ) {
						$ewww_image->backup = $hash;
					}
					if ( defined( 'WP_CLI' ) && WP_CLI ) {
						ewwwio_debug_message( 'retrieving pending image (and waiting) for wp-cli' );
						if ( ewww_image_optimizer_cloud_retrieve_pending_image( $ewww_image, true ) ) {
							clearstatcache();
							$newsize = filesize( $file );
							ewwwio_debug_message( "cloud results (async): $newsize (new) vs. $orig_size (original)" );
						}
					} else {
						// An image opt is pending, so store the retrieve ID, the opt level, and the backup hash.
						ewww_image_optimizer_cloud_stash_pending_image( $ewww_image );
						$msg     = 'pending';
						$newsize = 0;
					}
				}
			}
		} elseif ( ewww_image_optimizer_mimetype( $tempfile, 'i' ) === $type ) {
			$newsize = filesize( $tempfile );
			ewwwio_debug_message( "cloud results: $newsize (new) vs. $orig_size (original)" );
			ewwwio_rename( $tempfile, $file );
		} elseif ( ewww_image_optimizer_mimetype( $tempfile, 'i' ) === 'image/webp' ) {
			$newsize = filesize( $tempfile );
			ewwwio_debug_message( "cloud results (webp): $newsize (new) vs. $orig_size (original)" );
			ewwwio_rename( $tempfile, $newfile );
		} elseif ( ! is_null( $newtype ) && ! is_null( $newfile ) && ewww_image_optimizer_mimetype( $tempfile, 'i' ) === $newtype ) {
			ewwwio_debug_message( "renaming file from $tempfile to $newfile" );
			if ( ewwwio_rename( $tempfile, $newfile ) ) {
				$converted = true;
				$newsize   = filesize( $newfile );
				$file      = $newfile;
				ewwwio_debug_message( "cloud results (converted): $newsize (new) vs. $orig_size (original)" );
			}
		}
		clearstatcache();
		if ( ewwwio_is_file( $tempfile ) ) {
			ewwwio_delete_file( $tempfile );
		}
		ewwwio_memory( __FUNCTION__ );
		return array( $file, $converted, $msg, $newsize, $hash );
	} // End if().
}

/**
 * Get the dimensions for creating a WebP image from the full-size image.
 *
 * @since 8.0.0
 *
 * @global object $ewww_image
 *
 * @param string $file The filename of the existing (JPG) thumbnail image.
 * @return array {
 *     Information for resizing the file.
 *
 *     @type int The desired width of the image.
 *     @type int The desired height of the image.
 *     @type int 1 to crop the image, 0 to scale.
 * }
 */
function ewww_image_optimizer_cloud_get_webp_params( $file ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewww_image;
	$crop   = 0;
	$params = array( 0, 0, $crop );
	if ( empty( $ewww_image->resize ) || empty( $ewww_image->gallery ) ) {
		ewwwio_debug_message( 'size or gallery data missing' );
		return $params;
	}
	if ( 'full' === $ewww_image->resize || 'media' !== $ewww_image->gallery ) {
		ewwwio_debug_message( "$file not a thumb or not from media lib" );
		return $params;
	}
	list( $thumb_width, $thumb_height ) = wp_getimagesize( $file );
	if ( empty( $thumb_width ) || empty( $thumb_height ) ) {
		ewwwio_debug_message( "no dims for $file" );
		return $params;
	}

	$attachment_id = 0;
	if ( ! empty( $ewww_image->attachment_id ) ) {
		$attachment_id = $ewww_image->attachment_id;
		ewwwio_debug_message( "found attachment ID: $attachment_id" );
	}

	$original_image = ewwwio_get_original_image_path_from_thumb( $file, $attachment_id );
	if ( $original_image && ewwwio_is_file( $original_image ) ) {
		list( $full_width, $full_height ) = wp_getimagesize( $original_image );
		if ( empty( $full_width ) || empty( $full_height ) ) {
			ewwwio_debug_message( "no dims for $original_image" );
			return $params;
		}
		// Then we do a calculation with the dimensions to see if the thumb was cropped.
		$original_height_calc = $full_width / $thumb_width * $thumb_height;
		if ( abs( $original_height_calc - $full_height ) > 5 ) {
			$crop = 1;
		}
		return array( (int) $thumb_width, (int) $thumb_height, $crop, $original_image );
	}
	return $params;
}

/**
 * Retrieve a pending image from the API.
 *
 * @global object $wpdb
 *
 * @param object $ewww_image The db record/object of the image to stash.
 * @return bool True if the image db was updated successfully.
 */
function ewww_image_optimizer_cloud_stash_pending_image( $ewww_image ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $ewww_image->id ) ) {
		ewwwio_debug_message( 'no id, no good' );
		return false;
	}
	global $wpdb;
	ewwwio_debug_message( "storing $ewww_image->retrieve with level $ewww_image->level for record $ewww_image->id" );
	$stashed = $wpdb->update(
		$wpdb->ewwwio_images,
		array(
			'retrieve' => $ewww_image->retrieve,
			'level'    => $ewww_image->level,
			'backup'   => $ewww_image->backup,
		),
		array(
			'id' => $ewww_image->id,
		)
	);
	if ( $stashed ) {
		ewwwio_debug_message( 'success!' );
	} else {
		ewwwio_debug_message( 'no good...' );
	}
	return $stashed;
}

/**
 * Retrieve a pending image from the API.
 *
 * @param int|array $image The db record/ID of the image to retrieve.
 * @param bool      $wait True to wait until the max time, false to try only once.
 * @return bool True if the image was retrieved successfully.
 */
function ewww_image_optimizer_cloud_retrieve_pending_image( $image, $wait = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$start_time = time();
	$max_time   = $wait ? 300 : 20;
	if ( ! is_object( $image ) && ! empty( $image ) && is_numeric( $image ) ) {
		$image = new EWWW_Image( $image );
	}
	if ( empty( $image->retrieve ) ) {
		ewwwio_debug_message( 'no ID to retrieve' );
		return false;
	}
	$api_key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	$url     = 'http://optimize.exactlywww.com/v3/retrieve/?id=' . $image->retrieve;
	$ssl     = wp_http_supports( array( 'ssl' ) );
	if ( $ssl ) {
		$url = set_url_scheme( $url, 'https' );
	}
	ewwwio_debug_message( "fetching result via $url" );
	while ( $start_time + $max_time > time() ) {
		sleep( apply_filters( 'ewww_image_optimizer_api_retrieve_delay', 6 ) );
		$result = wp_remote_get(
			$url,
			array(
				'timeout'   => 20,
				'sslverify' => false,
			)
		);
		if ( 404 === (int) wp_remote_retrieve_response_code( $result ) ) {
			ewwwio_debug_message( 'result not ready yet' );
			continue;
		} elseif ( 204 === (int) wp_remote_retrieve_response_code( $result ) ) {
			ewwwio_debug_message( "no savings for {$image->file}" );
			$image_size = ewww_image_optimizer_filesize( $image->file );
			if ( ! $wait ) {
				global $wpdb;
				$wpdb->update(
					$wpdb->ewwwio_images,
					array(
						'retrieve'   => '',
						'image_size' => $image_size,
						'pending'    => 0,
					),
					array(
						'id' => $image->id,
					)
				);
			}
			return true;
		} elseif ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			ewwwio_debug_message( "retrieve request failed: $error_message" );
			return false;
		} elseif ( ! empty( $result['body'] ) ) {
			$supported_types = ewwwio()->get_supported_types( 'all' );
			if ( ! is_dir( dirname( $image->file ) ) ) {
				wp_mkdir_p( dirname( $image->file ) );
			}
			file_put_contents( $image->file . '.tmp', $result['body'] );
			$new_type = ewww_image_optimizer_mimetype( $image->file . '.tmp', 'i' );
			$old_type = '';
			if ( ewwwio_is_file( $image->file ) ) {
				$old_type = ewww_image_optimizer_mimetype( $image->file, 'i' );
			}
			if ( ! in_array( $new_type, $supported_types, true ) ) {
				/* translators: %s: An image filename */
				ewwwio_debug_message( "result file for {$image->file} has an unacceptable mime type: $new_type" );
				return false;
			}
			ewwwio_debug_message( "$image->file is $old_type, new one is $new_type" );
			if ( empty( $old_type ) || $old_type === $new_type ) {
				if ( ewwwio_rename( $image->file . '.tmp', $image->file ) ) {
					ewwwio_debug_message( "optimized image for {$image->file} was retrieved" );
					$image_size = ewww_image_optimizer_filesize( $image->file );
					if ( ! $wait ) {
						global $wpdb;
						$wpdb->update(
							$wpdb->ewwwio_images,
							array(
								'retrieve'   => '',
								'image_size' => $image_size,
								'pending'    => 0,
							),
							array(
								'id' => $image->id,
							)
						);
					}
					return true;
				}
				ewwwio_debug_message( 'failed to replace' );
			}
		}
	}
	return false;
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
	if ( false !== strpos( get_transient( 'ewww_image_optimizer_cloud_status' ), 'exceeded' ) ) {
		if ( ! ewww_image_optimizer_cloud_verify( $api_key ) ) {
			ewwwio_debug_message( 'cloud verify failed, image not rotated' );
			return false;
		}
	}
	// Calculate how much time has elapsed since we started.
	$elapsed = microtime( true ) - $started;
	ewwwio_debug_message( "cloud verify took $elapsed seconds" );
	if ( false !== strpos( get_transient( 'ewww_image_optimizer_cloud_status' ), 'exceeded' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_exceeded' ) > time() ) {
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
 * Converts PNG image to PNG8 encoding via API service.
 *
 * @since 7.7.0
 *
 * @param string $file Name of the file to fix.
 * @param int    $colors Maximum number of colors allowed.
 * @return bool True if the operation was successful.
 */
function ewww_image_optimizer_cloud_reduce_png( $file, $colors ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! ewwwio_check_memory_available( filesize( $file ) * 2.2 ) ) { // 2.2 = upload buffer + download buffer (2) multiplied by a factor of 1.1 for extra wiggle room.
		$memory_required = filesize( $file ) * 2.2;
		ewwwio_debug_message( "possibly insufficient memory for cloud (PNG reduction) operation: $memory_required" );
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
	if ( false !== strpos( get_transient( 'ewww_image_optimizer_cloud_status' ), 'exceeded' ) ) {
		if ( ! ewww_image_optimizer_cloud_verify( $api_key ) ) {
			ewwwio_debug_message( 'cloud verify failed, image not converted' );
			return false;
		}
	}
	// Calculate how much time has elapsed since we started.
	$elapsed = microtime( true ) - $started;
	ewwwio_debug_message( "cloud verify took $elapsed seconds" );
	if ( false !== strpos( get_transient( 'ewww_image_optimizer_cloud_status' ), 'exceeded' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_exceeded' ) > time() ) {
		ewwwio_debug_message( 'license exceeded, image not converted' );
		return false;
	}
	global $eio_filesystem;
	ewwwio_get_filesystem();
	ewwwio_debug_message( "file: $file " );
	$url = 'http://optimize.exactlywww.com/reduce-png/';
	$ssl = wp_http_supports( array( 'ssl' ) );
	if ( $ssl ) {
		$url = set_url_scheme( $url, 'https' );
	}
	$boundary = wp_generate_password( 24, false );

	$headers = array(
		'content-type' => 'multipart/form-data; boundary=' . $boundary,
		'timeout'      => 30,
		'httpversion'  => '1.0',
		'blocking'     => true,
	);

	$post_fields = array(
		'filename' => $file,
		'api_key'  => $api_key,
		'colors'   => (int) $colors,
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
	$payload .= "Content-Type: image/png\r\n";
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
			'timeout'   => 30,
			'headers'   => $headers,
			'sslverify' => false,
			'body'      => $payload,
		)
	);
	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		ewwwio_debug_message( "PNG reduction failed: $error_message" );
		return false;
	} elseif ( ! empty( $response['body'] ) ) {
		$tempfile = $file . '.tmp';
		file_put_contents( $tempfile, $response['body'] );
		$orig_size = filesize( $file );
		$newsize   = $orig_size;
		if ( preg_match( '/exceeded/', $response['body'] ) ) {
			ewwwio_debug_message( 'License Exceeded' );
			set_transient( 'ewww_image_optimizer_cloud_status', 'exceeded', HOUR_IN_SECONDS );
		} elseif ( ewww_image_optimizer_mimetype( $tempfile, 'i' ) === 'image/png' ) {
			$newsize = filesize( $tempfile );
			ewwwio_debug_message( "cloud PNG reduction success: $newsize (new) vs. $orig_size (original)" );
			ewwwio_rename( $tempfile, $file );
			return true;
		}
		ewwwio_delete_file( $tempfile );
	}
	return false;
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
	if ( 'cloud' !== ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ) {
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
	if ( false !== strpos( get_transient( 'ewww_image_optimizer_cloud_status' ), 'exceeded' ) ) {
		if ( ! ewww_image_optimizer_cloud_verify( $api_key ) ) {
			ewwwio_debug_message( 'cloud verify failed, image not resized' );
			return new WP_Error( 'invalid_key', __( 'Could not verify API key', 'ewww-image-optimizer' ) );
		}
	}
	// Calculate how much time has elapsed since we started.
	$elapsed = microtime( true ) - $started;
	ewwwio_debug_message( "cloud verify took $elapsed seconds" );
	if ( false !== strpos( get_transient( 'ewww_image_optimizer_cloud_status' ), 'exceeded' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_exceeded' ) > time() ) {
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
 * Inserts a single record into the table as pending, or marks it pending if it exists.
 *
 * @param string $path The filename of the image.
 * @param string $gallery The type (origin) of the image.
 * @param int    $attachment_id The attachment ID, if there is one.
 * @param string $size The name of the resize for the image.
 * @return int The row ID of the record updated/inserted.
 */
function ewww_image_optimizer_single_insert( $path, $gallery = '', $attachment_id = '', $size = '' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;

	$already_optimized = ewww_image_optimizer_find_already_optimized( $path );
	if ( is_array( $already_optimized ) && ! empty( $already_optimized ) ) {
		if ( ! empty( $already_optimized['pending'] ) ) {
			ewwwio_debug_message( "already pending record for $path - {$already_optimized['id']}" );
			return $already_optimized['id'];
		}
		$wpdb->update(
			$wpdb->ewwwio_images,
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
			$utf8_file_path = mb_convert_encoding( $path, 'UTF-8' );
		}
		$to_insert = array(
			'path'      => $utf8_file_path,
			'converted' => '',
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
		$wpdb->insert( $wpdb->ewwwio_images, $to_insert );
		ewwwio_debug_message( "inserted pending record for $path - {$wpdb->insert_id}" );
		return $wpdb->insert_id;
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
	$id   = (int) $id;
	$file = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT path FROM $wpdb->ewwwio_images WHERE id = %d",
			$id
		)
	);
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
 * @global object $wpdb
 *
 * @param string $table The table to insert records into.
 * @param array  $data Can be any multi-dimensional array with records to insert. All values must be int/string data.
 * @return int|bool Number of rows inserted. Boolean false on error.
 */
function ewww_image_optimizer_mass_insert( $table, $data ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $table ) || ! ewww_image_optimizer_iterable( $data ) ) {
		return false;
	}
	global $wpdb;

	/**
	 * Set a maximum for a query, 1k less than WPE's 16k limit, just to be safe.
	 *
	 * @param int 15000 The maximum query length.
	 */
	$max_query_length = apply_filters( 'ewww_image_optimizer_max_query_length', 15000 );

	$first_record   = reset( $data );
	$unsafe_fields  = array_keys( $first_record );
	$escaped_fields = array();
	foreach ( $unsafe_fields as $unsafe_field ) {
		$escaped_fields[] = $wpdb->quote_identifier( $unsafe_field );
	}
	$escaped_table  = $wpdb->quote_identifier( $table );
	$escaped_fields = implode( ',', $escaped_fields );

	$record_count   = count( $data );
	$total_inserted = 0;
	ewwwio_debug_message( "inserting $record_count records" );
	$escaped_values = '';
	foreach ( $data as $record ) {
		if ( ! ewww_image_optimizer_iterable( $record ) ) {
			continue;
		}
		$values = array();
		foreach ( $record as $value ) {
			if ( is_int( $value ) ) {
				$values[] = $value;
			} elseif ( is_string( $value ) ) {
				$values[] = "'" . esc_sql( $value ) . "'";
			} else {
				$values[] = "''";
			}
		}
		if ( strlen( $escaped_values ) > $max_query_length ) {
			$escaped_values = rtrim( $escaped_values, ',' );
			// Only int and string values allowed (escaped and validated above). All values, field names and table names are now escaped.
			$inserted = $wpdb->query( "INSERT INTO $escaped_table ($escaped_fields) VALUES $escaped_values" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $inserted ) {
				$total_inserted += $inserted;
			} else {
				ewwwio_debug_message( 'db error inserting: ' . $wpdb->last_error );
				return $inserted;
			}
			$escaped_values = '';
		}
		$escaped_values .= '(' . implode( ',', $values ) . '),';
	}

	$escaped_values = rtrim( $escaped_values, ',' );

	// Only int and string values allowed (escaped and validated above). All values, field names and table names are now escaped.
	$inserted = $wpdb->query( "INSERT INTO $escaped_table ($escaped_fields) VALUES $escaped_values" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $inserted ) {
		$total_inserted += $inserted;
		ewwwio_debug_message( "inserted $total_inserted rows" );
		return $total_inserted;
	} else {
		ewwwio_debug_message( 'db error inserting: ' . $wpdb->last_error );
		return $inserted;
	}
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
		$prev_string       = ' - ' . __( 'Previously Optimized', 'ewww-image-optimizer' );
		$already_optimized = ewww_image_optimizer_image_results( $image['orig_size'], $image['image_size'], $prev_string );
		ewwwio_debug_message( "already optimized: {$image['path']} - $already_optimized" );
		ewwwio_memory( __FUNCTION__ );
		// Make sure the image isn't pending.
		if ( $image['pending'] && empty( ewwwio()->force_smart ) ) {
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
	global $ewww_image;
	// First check if the image was converted, so we don't orphan records.
	if ( $original && $original !== $attachment ) {
		$already_optimized = ewww_image_optimizer_find_already_optimized( $original );
		$converted         = ewww_image_optimizer_relativize_path( $original );
		if ( empty( $already_optimized ) ) {
			$already_optimized = ewww_image_optimizer_find_already_optimized( $attachment );
		}
	} else {
		$already_optimized = ewww_image_optimizer_find_already_optimized( $attachment );
		$converted         = '';
		if ( is_array( $already_optimized ) && ! empty( $already_optimized['converted'] ) ) {
			$converted = $already_optimized['converted'];
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
		'updates'    => 1,
		'backup'     => preg_replace( '/[^\w]/', '', $backup_hash ),
	);
	if ( ! seems_utf8( $updates['path'] ) ) {
		$updates['path'] = mb_convert_encoding( $updates['path'], 'UTF-8' );
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
		$wpdb->insert( $wpdb->ewwwio_images, $updates );
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
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ewww_image_optimizer_function_exists( 'print_r' ) ) {
			ewwwio_debug_message( print_r( $updates, true ) );
		}
		// Update information for the image.
		$record_updated = $wpdb->update(
			$wpdb->ewwwio_images,
			$updates,
			array(
				'id' => $already_optimized['id'],
			)
		);
		if ( false === $record_updated ) {
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ewww_image_optimizer_function_exists( 'print_r' ) ) {
				ewwwio_debug_message( 'db error: ' . print_r( $wpdb->last_error, true ) );
			}
		} else {
			ewwwio_debug_message( "updated $record_updated records successfully" );
		}
	} // End if().
	$wpdb->flush();
	return $results_msg;
}

/**
 * Updates WebP results for an image record in the database.
 *
 * @see ewww_image_optimizer_webp_error_message() Converts WebP error codes to messages.
 * @global object $wpdb
 * @global object $ewww_image Contains more information about the image currently being processed.
 *
 * @param string $attachment The filename of the original image.
 * @param int    $webp_size The filesize of the WebP image.
 * @param int    $webp_error Optional. An error code for the WebP conversion.
 */
function ewww_image_optimizer_update_webp_results( $attachment, $webp_size, $webp_error = 0 ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	global $ewww_image;
	$already_optimized = ewww_image_optimizer_find_already_optimized( $attachment );
	ewwwio_debug_message( "webp conversion yielded size $webp_size (error=$webp_error)" );

	$updates = array(
		'path'       => ewww_image_optimizer_relativize_path( $attachment ),
		'webp_size'  => (int) $webp_size,
		'webp_error' => (int) $webp_error,
	);
	if ( ! seems_utf8( $updates['path'] ) ) {
		$updates['path'] = mb_convert_encoding( $updates['path'], 'UTF-8' );
	}
	if ( is_object( $ewww_image ) && $ewww_image instanceof EWWW_Image && $attachment === $ewww_image->file ) {
		$ewww_image->webp_size  = (int) $webp_size;
		$ewww_image->webp_error = (int) $webp_error;
	}
	// Store info on the current image for future reference.
	if ( empty( $already_optimized ) || ! is_array( $already_optimized ) ) {
		ewwwio_debug_message( "creating new record for $attachment" );
		$updates['converted'] = '';
		$wpdb->insert( $wpdb->ewwwio_images, $updates );
	} else {
		ewwwio_debug_message( "updating existing record ({$already_optimized['id']}), path: $attachment" );
		// Update information for the image.
		$record_updated = $wpdb->update(
			$wpdb->ewwwio_images,
			$updates,
			array(
				'id' => $already_optimized['id'],
			)
		);
		if ( false === $record_updated ) {
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ewww_image_optimizer_function_exists( 'print_r' ) ) {
				ewwwio_debug_message( 'db error: ' . print_r( $wpdb->last_error, true ) );
			}
		} else {
			ewwwio_debug_message( "updated $record_updated records successfully" );
		}
	} // End if().
	$wpdb->flush();
}

/**
 * Updates resize results for an image record in the database.
 *
 * @global object $wpdb
 * @global object $ewww_image Contains more information about the image currently being processed.
 *
 * @param string $attachment The filename of the image.
 * @param int    $resized_width The current width setting when the image was resized.
 * @param int    $resized_height The current height setting when the image was resized.
 * @param int    $resize_error Optional. An error code for the error category (see EWWW_Image class for details).
 */
function ewww_image_optimizer_update_resize_results( $attachment, $resized_width, $resized_height, $resize_error = 0 ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	global $ewww_image;
	$already_optimized = ewww_image_optimizer_find_already_optimized( $attachment );
	ewwwio_debug_message( "resize attempted at: $resized_width (w) vs. $resized_height (h), code $resize_error" );

	$updates = array(
		'path'           => ewww_image_optimizer_relativize_path( $attachment ),
		'resized_width'  => (int) $resized_width,
		'resized_height' => (int) $resized_height,
		'resize_error'   => (int) $resize_error,
	);
	if ( ! seems_utf8( $updates['path'] ) ) {
		$updates['path'] = mb_convert_encoding( $updates['path'], 'UTF-8' );
	}
	if ( is_object( $ewww_image ) && $ewww_image instanceof EWWW_Image && $attachment === $ewww_image->file ) {
		$ewww_image->resized_width  = (int) $resized_width;
		$ewww_image->resized_height = (int) $resized_height;
		$ewww_image->resize_error   = (int) $resize_error;
	}
	// Store info on the current image for future reference.
	if ( empty( $already_optimized ) || ! is_array( $already_optimized ) ) {
		ewwwio_debug_message( "creating new record for $attachment" );
		$updates['converted'] = '';
		$wpdb->insert( $wpdb->ewwwio_images, $updates );
	} else {
		ewwwio_debug_message( "updating existing record ({$already_optimized['id']}), path: $attachment" );
		// Update information for the image.
		$record_updated = $wpdb->update(
			$wpdb->ewwwio_images,
			$updates,
			array(
				'id' => $already_optimized['id'],
			)
		);
		if ( false === $record_updated ) {
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ewww_image_optimizer_function_exists( 'print_r' ) ) {
				ewwwio_debug_message( 'db error: ' . print_r( $wpdb->last_error, true ) );
			}
		} else {
			ewwwio_debug_message( "updated $record_updated records successfully" );
		}
	} // End if().
	$wpdb->flush();
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
	ewwwio()->defer = false;
	$output         = array();
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
		list( $id, $attachment ) = $wpdb->get_row( "SELECT id,path FROM $wpdb->ewwwio_images WHERE pending=1 LIMIT 1", ARRAY_N );
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

	if ( 'exceeded' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
		if ( ! $auto ) {
			$output['error'] = ewww_image_optimizer_credits_exceeded();
			echo wp_json_encode( $output );
		}
		if ( $cli ) {
			WP_CLI::error( __( 'License Exceeded', 'ewww-image-optimizer' ) );
		}
		die();
	}
	if ( 'exceeded quota' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
		if ( ! $auto ) {
			$output['error'] = ewww_image_optimizer_soft_quota_exceeded();
			echo wp_json_encode( $output );
		}
		if ( $cli ) {
			WP_CLI::error( __( 'Soft quota reached, contact us for more', 'ewww-image-optimizer' ) );
		}
		die();
	}
	if ( 'exceeded subkey' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
		if ( ! $auto ) {
			$output['error'] = esc_html__( 'Out of credits', 'ewww-image-optimizer' );
			echo wp_json_encode( $output );
		}
		if ( $cli ) {
			WP_CLI::error( __( 'Out of credits', 'ewww-image-optimizer' ) );
		}
		die();
	}

	if ( ! $results[0] && $id && is_numeric( $id ) ) {
		ewww_image_optimizer_delete_pending_image( $id );
	}
	if ( true === $results[0] && $id && is_numeric( $id ) ) {
		ewww_image_optimizer_toggle_pending_image( $id );
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
			$output['results'] .= '<div style="background-color:#f1f1f1;">' . EWWW\Base::$debug_data . '</div>';
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
	if ( ewww_image_optimizer_s3_uploads_enabled() && ! empty( $meta['file'] ) ) {
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
		require_once ABSPATH . '/wp-admin/includes/file.php';
	}
	$filename = false;
	if ( ewww_image_optimizer_s3_uploads_enabled() && ! empty( $meta['file'] ) ) {
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
	if ( function_exists( 'as3cf_get_attachment_url' ) ) {
		global $as3cf;
		$full_url = get_attached_file( $id );
		if ( ewww_image_optimizer_stream_wrapped( $full_url ) ) {
			$full_url = as3cf_get_attachment_url( $id );
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
					$resize_url  = as3cf_get_attachment_url( $id, $size );
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
		ewwwio_debug_message( "found $s3_path in db" );
		// When we find a match by the s3 path, we need to find out if there are already records for the local path.
		$found_local_image = ewww_image_optimizer_find_already_optimized( $local_path );
		ewwwio_debug_message( "looking for $local_path" );
		// If we found records for both local and s3 paths, we delete the s3 record, but store the original size in the local record.
		if ( ! empty( $found_local_image ) && is_array( $found_local_image ) ) {
			ewwwio_debug_message( "found $local_path in db" );
			$wpdb->delete(
				$wpdb->ewwwio_images,
				array(
					'id' => $s3_image['id'],
				),
				array(
					'%d',
				)
			);
			if ( $s3_image['orig_size'] > $found_local_image['orig_size'] ) {
				$wpdb->update(
					$wpdb->ewwwio_images,
					array(
						'orig_size' => $s3_image['orig_size'],
					),
					array(
						'id' => $found_local_image['id'],
					)
				);
			}
		} else {
			// If we just found an s3 path and no local match, then we just update the path in the table to the local path.
			ewwwio_debug_message( 'just updating s3 to local' );
			$wpdb->update(
				$wpdb->ewwwio_images,
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
		return new WP_Error( 'invalid_image', __( 'File is not an image.', 'ewww-image-optimizer' ), $file );
	}
	if ( 'image/gif' === $type && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && function_exists( 'ewww_image_optimizer_gifsicle_resize' ) ) {
		return ewww_image_optimizer_gifsicle_resize( $file, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h );
	}
	return ewww_image_optimizer_cloud_resize( $file, $type, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h );
}

/**
 * Checks if pngquant, local or via API, is available to reduce the pallete of a PNG image.
 *
 * @since 7.7.0
 *
 * @return bool True if reduction can be run via pngquant or API, false otherwise.
 */
function ewww_image_optimizer_pngquant_reduce_available() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! defined( 'EWWWIO_PNGQUANT_REDUCE' ) || ! EWWWIO_PNGQUANT_REDUCE ) {
		return false;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		return true;
	}
	$pngquant_path = ewwwio()->local->get_path( 'pngquant', true );
	if ( ! empty( $pngquant_path ) ) {
		return true;
	}
	return false;
}

/**
 * Uses pngquant or the API to reduce the palette of a PNG image to bit depth 8 (or less).
 *
 * @since 7.7.0
 *
 * @param string $file A PNG image file.
 * @param int    $max_colors The maximum number of colors.
 */
function ewww_image_optimizer_reduce_palette( $file, $max_colors ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! apply_filters( 'ewww_image_optimizer_reduce_palette', true ) ) {
		ewwwio_debug_message( 'palette reduction disabled' );
		return;
	}
	if ( ! defined( 'EWWWIO_PNGQUANT_REDUCE' ) || ! EWWWIO_PNGQUANT_REDUCE ) {
		return false;
	}
	ewwwio_debug_message( "reducing $file to $max_colors colors" );
	if ( ! ewwwio_is_file( $file ) ) {
		return;
	}
	$type = ewww_image_optimizer_mimetype( $file, 'i' );
	if ( false === strpos( $type, 'image' ) ) {
		ewwwio_debug_message( "not an image, no conversion possible: $type" );
		return;
	}
	if ( 'image/png' !== $type ) {
		return;
	}
	if ( ! ewww_image_optimizer_pngquant_reduce_png( $file, $max_colors ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		ewww_image_optimizer_cloud_reduce_png( $file, $max_colors );
	}
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
		ewwwio_debug_message( 'no rotation needed' );
		return;
	}
	ewwwio_debug_message( "current orientation: $orientation" );
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
		} catch ( PelException $pelerror ) {
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
		ewwwio_debug_message( 'not a PNG or GIF, no conversion needed' );
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
	if ( 'image/png' === $type ) {
		$newfile = ewww_image_optimizer_unique_filename( $file, '.jpg' );
	} elseif ( 'image/gif' === $type ) {
		$newfile = ewww_image_optimizer_unique_filename( $file, '.png' );
	}
	$ewww_image = new EWWW_Image( 0, '', $file );
	// Pass the filename, false for db search/replace, and true for filesize comparison.
	return $ewww_image->convert( $file, false, true, $newfile );
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
 *
 * @param string $file The file to check for rotation.
 * @return array|bool The new height and width, or false if no resizing was done.
 */
function ewww_image_optimizer_resize_upload( $file ) {
	// Parts adapted from Imsanity (THANKS Jason!).
	// Errors will be stored in the ewwwio_images table:
	// 0 = no error
	// 1 = WP_Image_Editor error
	// 2 = Scaled image filesize too large (bigger than the original)
	// 3 = All other errors.
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewwwio_resize_status;
	$ewwwio_resize_status = '';
	if ( ! $file ) {
		return false;
	}
	if ( ! empty( ewwwio()->webp_only ) ) {
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
		ewww_image_optimizer_update_resize_results( $file, $maxwidth, $maxheight, 3 );
		return false;
	}
	// Check file size (dimensions).
	list( $oldwidth, $oldheight ) = wp_getimagesize( $file );
	if ( $oldwidth <= $maxwidth && $oldheight <= $maxheight ) {
		// NOTE: For now, we aren't storing this condition in the db. But if needed, we can store this as error=0.
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
		ewww_image_optimizer_update_resize_results( $file, $maxwidth, $maxheight, 1 );
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
		ewww_image_optimizer_update_resize_results( $file, $maxwidth, $maxheight, 1 );
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
		ewww_image_optimizer_update_resize_results( $file, $maxwidth, $maxheight, 1 );
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
		if (
			'image/jpeg' === $type &&
			( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_skip_full' ) || ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ) &&
			! ewwwio()->imagick_support()
		) {
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
			} catch ( PelException $pelerror ) {
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
			if ( ! is_null( $new_jpeg ) ) {
				$new_jpeg->saveFile( $new_file );
			}
		}
		// Backup the file before we replace the original.
		global $eio_backup;
		if ( ! apply_filters( 'ewww_image_optimizer_backup_post_resize', false ) ) {
			$eio_backup->backup_file( $file );
		}
		// ewww_image_optimizer_cloud_backup( $file );.
		$new_type = (string) ewww_image_optimizer_mimetype( $new_file, 'i' );
		if ( $type === $new_type ) {
			ewwwio_rename( $new_file, $file );
		} else {
			ewwwio_debug_message( "resizing did not create a valid image: $new_type" );
			/* translators: %s: the mime type of the new file */
			$ewwwio_resize_status = sprintf( __( 'Resizing resulted in an invalid file type: %s', 'ewww-image-optimizer' ), $new_type );
			unlink( $new_file );
			ewww_image_optimizer_update_resize_results( $file, $maxwidth, $maxheight, 3 );
			return false;
		}
		// Store info on the current image for future reference.
		global $wpdb;
		// Delete the record created from optimizing the resized file (if it exists, which it shouldn't).
		$temp_optimized = ewww_image_optimizer_find_already_optimized( $new_file );
		if ( is_array( $temp_optimized ) && ! empty( $temp_optimized['id'] ) ) {
			$wpdb->delete(
				$wpdb->ewwwio_images,
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
		ewww_image_optimizer_update_resize_results( $file, $maxwidth, $maxheight, 0 );
		return array( $newwidth, $newheight );
	} // End if().
	if ( ewwwio_is_file( $new_file ) ) {
		ewwwio_debug_message( "resizing did not create a smaller image: $new_size" );
		$ewwwio_resize_status = __( 'Resizing did not reduce the file size, result discarded', 'ewww-image-optimizer' );
		unlink( $new_file );
		ewww_image_optimizer_update_resize_results( $file, $maxwidth, $maxheight, 2 );
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
		$exif = @exif_read_data( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
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
 *
 * @param string $attachment The name of the file.
 * @return array|bool If found, information about the image, false otherwise.
 */
function ewww_image_optimizer_find_already_optimized( $attachment ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	$maybe_return_image  = false;
	$maybe_relative_path = ewww_image_optimizer_relativize_path( $attachment );
	$optimized_query     = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $wpdb->ewwwio_images WHERE path = %s",
			$maybe_relative_path
		),
		ARRAY_A
	);
	if ( empty( $optimized_query ) && $attachment !== $maybe_relative_path ) {
		$optimized_query = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->ewwwio_images WHERE path = %s",
				$attachment
			),
			ARRAY_A
		);
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
	if ( empty( ewwwio()->force ) ) {
		return $meta;
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ) {
		return $meta;
	}
	if ( 'image/jpeg' === $type && (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 20 ) {
		return $meta;
	}
	if ( 'image/png' === $type && (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) > 20 ) {
		return $meta;
	}
	if ( 'image/webp' === $type ) {
		return $meta;
	}
	if ( 'application/pdf' === $type && 10 !== (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) ) {
		return $meta;
	}
	$compression_level = ewww_image_optimizer_get_level( $type );
	// Retrieve any records for this image.
	global $wpdb;
	$optimized_images = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'media' AND image_size <> 0 ORDER BY orig_size DESC", $id ), ARRAY_A );
	foreach ( $optimized_images as $optimized_image ) {
		if ( 'full' === $optimized_image['resize'] && $compression_level < $optimized_image['level'] ) {
			global $eio_backup;
			if ( $eio_backup->is_backup_available( $optimized_image['path'], $optimized_image ) ) {
				return $eio_backup->restore_backup_from_meta_data( $id, 'media', $meta );
			}
		}
	}
	return $meta;
}

/**
 * Merge duplicate records from the images table and remove any extras.
 *
 * @global object $wpdb
 *
 * @param array $duplicates An array of records referencing the same image.
 * @return array|bool A single image record or false if something unexpected happens.
 */
function ewww_image_optimizer_remove_duplicate_records( $duplicates ) {
	if ( empty( $duplicates ) ) {
		return false;
	}
	global $wpdb;

	if ( ! is_array( $duplicates[0] ) ) {
		// Retrieve records for the ID #s passed.
		$duplicate_results = array();
		foreach ( $duplicates as $duplicate ) {
			if ( empty( $duplicate ) ) {
				continue;
			}
			$duplicate_result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $wpdb->ewwwio_images WHERE id = %d",
					$duplicate['id']
				),
				ARRAY_A
			);
			if ( is_array( $duplicate_result ) && ! empty( $duplicate_result['id'] ) ) {
				$duplicate_results[] = $duplicate_result;
			}
		}
		$duplicates = $duplicate_results;
	}
	if ( ! is_array( $duplicates ) || empty( $duplicates ) || ! is_array( $duplicates[0] ) ) {
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
		$update_keeper = false;
		foreach ( $discard as $record ) {
			foreach ( $record as $key => $value ) {
				if ( empty( $keeper[ $key ] ) && ! empty( $value ) ) {
					$keeper[ $key ] = $value;
					$update_keeper  = true;
				}
			}
			$wpdb->delete(
				$wpdb->ewwwio_images,
				array(
					'id' => $record['id'],
				),
				'%d'
			);
		}
		if ( $update_keeper ) {
			$update_keeper = $keeper;
			unset( $update_keeper['id'] );
			$wpdb->update(
				$wpdb->ewwwio_images,
				$update_keeper,
				array(
					'id' => $keeper['id'],
				)
			);
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
 * @param string $type Optional. Mime type of image being processed. Default ''.
 * @return bool True if background mode should be used.
 */
function ewww_image_optimizer_test_background_opt( $type = '' ) {
	if ( ! ewww_image_optimizer_background_mode_enabled() ) {
		return false;
	}
	if ( 'image/jpeg' === $type && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_to_png' ) ) {
		return apply_filters( 'ewww_image_optimizer_defer_conversion', false );
	}
	if ( 'image/png' === $type && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_to_jpg' ) ) {
		return apply_filters( 'ewww_image_optimizer_defer_conversion', false );
	}
	if ( 'image/gif' === $type && ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_to_png' ) ) {
		return apply_filters( 'ewww_image_optimizer_defer_conversion', false );
	}
	if ( 'image/bmp' === $type && ewww_image_optimizer_get_option( 'ewww_image_optimizer_bmp_convert' ) ) {
		return apply_filters( 'ewww_image_optimizer_defer_conversion', false );
	}
	return (bool) apply_filters( 'ewww_image_optimizer_background_optimization', ewwwio()->defer );
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
 * Find the path to the original image from the path of a thumb.
 *
 * @since 8.0.0
 *
 * @param string $image_file The path to a scaled image file.
 * @param int    $id The attachment ID number.
 * @return string True on success, false on failure.
 */
function ewwwio_get_original_image_path_from_thumb( $image_file, $id = 0 ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$id             = (int) $id;
	$original_image = '';
	if ( ! empty( $id ) ) {
		$original_image = wp_get_original_image_path( $id, true );
		ewwwio_debug_message( "possible original at $original_image" );
		if ( $original_image && ewwwio_is_file( $original_image ) ) {
			return $original_image;
		}
	}

	// If core was no good, try stripping the dimensions and '-scaled' from the filename.
	$original_unscaled_image = preg_replace( '#(?:-scaled)?-\d+x\d+(\.jpe?g)#i', '$1', $file );
	ewwwio_debug_message( "possible unscaled original at $original_unscaled_image" );
	if ( ewwwio_is_file( $original_unscaled_image ) ) {
		ewwwio_debug_message( 'got em' );
		return $original_unscaled_image;
	}
	$original_scaled_image = preg_replace( '#(?:-scaled)?-\d+x\d+(\.jpe?g)#i', '-scaled$1', $file );
	ewwwio_debug_message( "possible scaled original at $original_scaled_image" );
	if ( ewwwio_is_file( $original_scaled_image ) ) {
		ewwwio_debug_message( 'got em' );
		return $original_scaled_image;
	}
	ewwwio_debug_message( 'no original to be found!' );
	return $original_image;
}

/**
 * Find the path to a backed-up original (not the full-size version like the core WP function).
 *
 * @param int    $id The attachment ID number.
 * @param string $image_file The path to a scaled image file.
 * @param array  $meta The attachment metadata. Optional, default to null.
 * @return string|bool File path on success, false on failure.
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
 * Queues an image attachment for async processing.
 *
 * @param int  $id The attachment ID number.
 * @param bool $new_image Optional. True indicates this is a new upload.
 * @param bool $convert_once Optional. True to do a one-off conversion.
 * @param bool $force_reopt Optional. True to force re-optimization.
 * @param bool $force_smart Optional. True to re-optimize "smartly". That is, only if the image was compressed at a different level previously.
 * @param bool $webp_only Optional. True to only attempt WebP conversion, no compression of the original.
 */
function ewww_image_optimizer_add_attachment_to_queue( $id, $new_image = false, $convert_once = false, $force_reopt = false, $force_smart = false, $webp_only = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_debug_message( "backgrounding optimization for $id" );
	ewwwio()->background_media->push_to_queue(
		array(
			'id'           => $id,
			'new'          => $new_image,
			'convert_once' => $convert_once,
			'force_reopt'  => $force_reopt,
			'force_smart'  => $force_smart,
			'webp_only'    => $webp_only,
		)
	);
	if ( ! ewwwio()->background_media->is_process_running() ) {
		ewwwio_debug_message( 'media process idle, dispatching post-haste' );
		ewwwio()->background_media->dispatch();
	}
}

/**
 * Find image paths from an attachment's meta data and process each image.
 *
 * Called after `wp_generate_attachment_metadata` is completed, it also searches for retina images,
 * and a few custom theme resizes. When a new image is uploaded, it is added to the queue, if
 * possible, and then this same function is run in the background.
 *
 * @global object $wpdb
 * @global bool $ewww_new_image True if this is a newly uploaded image.
 * @global object $ewww_image Contains more information about the image currently being processed.
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
	global $ewww_new_image;
	global $ewww_image;
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
	$supported_types = ewwwio()->get_supported_types();
	$type            = ewww_image_optimizer_mimetype( $file_path, 'i' );
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
		ewwwio_debug_message( 's3 upload deferred' );
		add_filter( 'as3cf_pre_update_attachment_metadata', '__return_true' );
		ewww_image_optimizer_add_attachment_to_queue( $id, $new_image );
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
	ewwwio_debug_message( 'running in sequence' );
	// Run the optimization and store the results.
	list( $file, $msg, $conv, $original ) = ewww_image_optimizer( $file_path, $gallery_type, false, $new_image, true );

	// If the file was converted.
	if ( false !== $conv && $file ) {
		$ewww_image->file      = $file;
		$ewww_image->converted = $original;
		$meta['file']          = _wp_relative_upload_path( $file );
		$ewww_image->update_converted_attachment( $meta );
		$meta = $ewww_image->convert_sizes( $meta );
		ewwwio_debug_message( 'image was converted' );
	}
	ewww_image_optimizer_hidpi_optimize( $file );

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
						$wpdb->update(
							$wpdb->ewwwio_images,
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
			if ( 'application/pdf' === $type && 'full' === $size ) {
				$size = 'pdf-full';
				ewwwio_debug_message( 'processing full size pdf preview' );
			}
			// Because some SVG plugins populate the resizes with the original path (since SVG is "scalable", of course).
			// Though it could happen for other types perhaps...
			if ( $resize_path === $file_path ) {
				continue;
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

		$ewww_image         = new EWWW_Image( $id, 'media', $resize_path );
		$ewww_image->resize = 'original_image';

		// Run the optimization and store the results (gallery type 5 and fullsize=true to obey lossy/metadata exclusions).
		ewww_image_optimizer( $resize_path, 5, false, false, true );
	} // End if().

	// Process size from a custom theme.
	if ( isset( $meta['image_meta']['resized_images'] ) && ewww_image_optimizer_iterable( $meta['image_meta']['resized_images'] ) ) {
		$imagemeta_resize_pathinfo = pathinfo( $file_path );
		$imagemeta_resize_path     = '';
		foreach ( $meta['image_meta']['resized_images'] as $imagemeta_resize ) {
			$imagemeta_resize_path = $imagemeta_resize_pathinfo['dirname'] . '/' . $imagemeta_resize_pathinfo['filename'] . '-' . $imagemeta_resize . '.' . $imagemeta_resize_pathinfo['extension'];

			$ewww_image = new EWWW_Image( $id, 'media', $imagemeta_resize_path );
			ewww_image_optimizer( $imagemeta_resize_path );
		}
	}

	// And another custom theme.
	if ( isset( $meta['custom_sizes'] ) && ewww_image_optimizer_iterable( $meta['custom_sizes'] ) ) {
		$custom_sizes_pathinfo = pathinfo( $file_path );
		$custom_size_path      = '';
		foreach ( $meta['custom_sizes'] as $custom_size ) {
			$custom_size_path = $custom_sizes_pathinfo['dirname'] . '/' . $custom_size['file'];

			$ewww_image = new EWWW_Image( $id, 'media', $custom_size_path );
			ewww_image_optimizer( $custom_size_path );
		}
	}

	global $ewww_attachment;
	$ewww_attachment['id']   = $id;
	$ewww_attachment['meta'] = $meta;
	add_filter( 'w3tc_cdn_update_attachment_metadata', 'ewww_image_optimizer_w3tc_update_files' );

	remove_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_update_filesize_metadata', 9 );
	$meta = ewww_image_optimizer_update_filesize_metadata( $meta, $id, $file );

	// Done optimizing, do whatever you need with the attachment from here.
	do_action( 'ewww_image_optimizer_after_optimize_attachment', $id, $meta );

	if ( class_exists( 'Amazon_S3_And_CloudFront' ) ) {
		global $as3cf;
		if ( method_exists( $as3cf, 'wp_update_attachment_metadata' ) ) {
			ewwwio_debug_message( 'deferring to normal S3 hook' );
		} elseif ( method_exists( $as3cf, 'wp_generate_attachment_metadata' ) ) {
			$as3cf->wp_generate_attachment_metadata( $meta, $id );
			ewwwio_debug_message( 'uploading to Amazon S3' );
		}
	}
	if ( ewww_image_optimizer_s3_uploads_enabled() ) {
		ewww_image_optimizer_remote_push( $meta, $id );
		ewwwio_debug_message( 're-uploading to S3(_Uploads)' );
	}
	if ( class_exists( 'Windows_Azure_Helper' ) && function_exists( 'windows_azure_storage_wp_generate_attachment_metadata' ) ) {
		$meta = windows_azure_storage_wp_generate_attachment_metadata( $meta, $id );
		if ( Windows_Azure_Helper::delete_local_file() && function_exists( 'windows_azure_storage_delete_local_files' ) ) {
			windows_azure_storage_delete_local_files( $meta, $id );
		}
	}
	ewwwio_debug_message( 'optimize from meta complete' );
	if ( $log ) {
		ewww_image_optimizer_debug_log();
	}
	ewwwio_memory( __FUNCTION__ );
	// Send back the updated metadata.
	return $meta;
}

/**
 * Optimize by attachment ID with optional meta.
 *
 * Proxy for ewww_image_optimizer_resize_from_meta_data(), used by Imsanity.
 *
 * @param int   $id The attachment ID number.
 * @param array $meta The attachment metadata generated by WordPress. Optional.
 */
function ewww_image_optimizer_optimize_by_id( $id, $meta = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $id ) ) {
		return;
	}
	if ( ! ewww_image_optimizer_iterable( $meta ) ) {
		$meta = wp_get_attachment_metadata( $id );
	}

	$meta = ewww_image_optimizer_resize_from_meta_data( $meta, $id );
	wp_update_attachment_metadata( $id, $meta );
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
	$supported_types = ewwwio()->get_supported_types();
	if ( ! in_array( $type, $supported_types, true ) ) {
		ewwwio_debug_message( "mimetype not supported: $id" );
		return;
	}

	// Get a list of all the image files optimized for this attachment.
	global $wpdb;
	$optimized_images = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'media' AND image_size <> 0 ORDER BY orig_size DESC", $id ), ARRAY_A );
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
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ewww_image_optimizer_function_exists( 'print_r' ) ) {
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
	$as3cf_action = false;
	if ( ! empty( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$as3cf_action = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	global $ewww_new_image;
	if ( ! empty( $ewww_new_image ) ) {
		// This is so we can detect new uploads for conversion checking.
		$as3cf_action = 'media_upload';
	}
	foreach ( $paths as $size => $path ) {
		if ( ! is_string( $path ) ) {
			continue;
		}
		if ( false !== strpos( $size, '-webp' ) || str_ends_with( $path, '.webp' ) ) {
			continue;
		}
		if ( $as3cf_action ) {
			ewwwio_debug_message( "checking $path for WebP or converted images in as3cf $as3cf_action queue" );
		}
		if ( ewwwio_is_file( $path . '.webp' ) ) {
			$paths[ $size . '-webp' ] = $path . '.webp';
			ewwwio_debug_message( "added $path.webp to as3cf queue" );
		} elseif (
			// WOM(pro) is downloading from bucket to server, WebP is enabled, and the local/server file does not exist.
			'download' === $as3cf_action &&
			ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) &&
			! ewwwio_is_file( $path )
		) {
			global $wpdb;
			$optimized = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'media' AND image_size <> 0 LIMIT 1", $id ) );
			if ( $optimized ) {
				$paths[ $size . '-webp' ] = $path . '.webp';
				ewwwio_debug_message( "added $path.webp to as3cf queue (for potential local copy)" );
			}
		}
		if ( ! is_admin() ) {
			continue;
		}
		$conversion_actions = array(
			'bulk_loop',
			'copy',
			'download',
			'ewww_bulk_update_meta',
			'ewww_image_optimizer_manual_image_restore',
			'ewww_image_optimizer_manual_optimize',
			'ewww_image_optimizer_manual_restore',
			'media_upload',
			'wp_ewwwio_image_optimize',
			'wp_ewwwio_media_optimize',
			'remove_local',
		);
		// If we're not deleting originals, then they should be re-uploaded to S3.
		// We'd check if conversion options are enabled, but folks can convert via the Media Library without them enabled, so we need to account for that.
		if ( in_array( $as3cf_action, $conversion_actions, true ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
			$opt_image = ewww_image_optimizer_find_already_optimized( $path );
			if ( ! empty( $opt_image['path'] ) && ! empty( $opt_image['converted'] ) ) {
				$orig_path = ewww_image_optimizer_absolutize_path( $opt_image['converted'] );
				// If WOM(pro) is downloading from bucket to server or a local original exists.
				if ( 'download' === $as3cf_action || ewwwio_is_file( $orig_path ) ) {
					$paths[ $size . '-orig' ] = $orig_path;
					ewwwio_debug_message( "added {$orig_path} to as3cf queue" );
				}
			}
		}
	}
	return $paths;
}

/**
 * Cleanup remote storage for WP Offload S3.
 *
 * Checks for WebP derivatives and pre-converted originals so that they can be removed.
 *
 * @param array $paths The image paths currently queued for deletion.
 * @return array A list of paths to remove.
 */
function ewww_image_optimizer_as3cf_remove_source_files( $paths ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	foreach ( $paths as $size => $path ) {
		if ( ! is_string( $path ) ) {
			continue;
		}
		if ( false !== strpos( $size, '-webp' ) || str_ends_with( $path, '.webp' ) ) {
			continue;
		}
		$paths[ $size . '-webp' ] = $path . '.webp';
		ewwwio_debug_message( "added $path.webp to as3cf deletion queue" );
		$ewww_image = ewww_image_optimizer_find_already_optimized( $path );
		if ( ! empty( $ewww_image['path'] ) ) {
			$local_path = ewww_image_optimizer_absolutize_path( $ewww_image['path'] );
			ewwwio_debug_message( "found optimized $local_path, validating and checking for pre-converted original" );
			if ( ! empty( $ewww_image['converted'] ) ) {
				$orig_path                = ewww_image_optimizer_absolutize_path( $ewww_image['converted'] );
				$paths[ $size . '-orig' ] = $orig_path;
				ewwwio_debug_message( "added {$orig_path} to as3cf deletion queue" );
			}
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
 * Update file sizes for an attachment and all thumbs.
 *
 * @param array  $meta Attachment metadata.
 * @param int    $id Attachment ID number.
 * @param string $file_path Optional. The full path to the full-size image.
 * @return array The updated attachment metadata.
 */
function ewww_image_optimizer_update_filesize_metadata( $meta, $id, $file_path = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( ! $file_path || ! is_string( $file_path ) ) {
		list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
	}
	if ( ! $file_path ) {
		return $meta;
	}
	$use_db = false;
	if ( ewww_image_optimizer_stream_wrapped( $file_path ) || ! ewwwio_is_file( $file_path ) ) {
		$use_db = true;
	}
	if ( $use_db ) {
		$full_filesize = $wpdb->get_var( $wpdb->prepare( "SELECT image_size FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'media' AND resize = %s", $id, 'full' ) );
		if ( empty( $full_filesize ) ) {
			return $meta;
		}
	} else {
		$full_filesize = ewww_image_optimizer_filesize( $file_path );
	}
	if ( $full_filesize ) {
		ewwwio_debug_message( "updating full to $full_filesize" );
		$meta['filesize'] = (int) $full_filesize;
	}
	if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
		foreach ( $meta['sizes'] as $size => $data ) {
			if ( $use_db && ! empty( $size ) ) {
				$scaled_filesize = $wpdb->get_var( $wpdb->prepare( "SELECT image_size FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'media' AND resize = %s", $id, $size ) );
				if ( ! $scaled_filesize ) {
					ewwwio_debug_message( 'checking other thumbs for filesize' );
					// Check through all the other sizes.
					foreach ( $meta['sizes'] as $index => $item ) {
						// If a different resize had identical dimensions.
						if (
							$item['height'] === $data['height'] &&
							$item['width'] === $data['width'] &&
							! empty( $item['filesize'] ) &&
							( ! isset( $data['filesize'] ) || (int) $item['filesize'] !== (int) $data['filesize'] )
						) {
							ewwwio_debug_message( "using $index filesize for $size" );
							$scaled_filesize = $item['filesize'];
							break;
						}
					}
				}
			} else {
				$resize_path     = path_join( dirname( $file_path ), $data['file'] );
				$scaled_filesize = ewww_image_optimizer_filesize( $resize_path );
			}
			if ( $scaled_filesize ) {
				ewwwio_debug_message( "updating $size to $scaled_filesize" );
				$meta['sizes'][ $size ]['filesize'] = $scaled_filesize;
			}
		}
	}
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
	if ( strpos( $file, trailingslashit( ABSPATH ) ) === 0 ) {
		return str_replace( trailingslashit( ABSPATH ), 'ABSPATH', $file );
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
		return str_replace( 'ABSPATH', trailingslashit( ABSPATH ), $file );
	}
	if ( defined( 'WP_CONTENT_DIR' ) && WP_CONTENT_DIR && strpos( $file, 'WP_CONTENT_DIR' ) === 0 ) {
		return str_replace( 'WP_CONTENT_DIR', WP_CONTENT_DIR, $file );
	}
	return $file;
}

/**
 * Takes a file and upload folder, and makes sure that the file is within the folder.
 *
 * Used for path replacement with async processing, since security plugins can block
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
 * @param string $fileext The extension to replace the existing file extension.
 * @return string A unique filename.
 */
function ewww_image_optimizer_unique_filename( $file, $fileext ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Change the file extension.
	$fileinfo = pathinfo( $file );
	if ( empty( $fileinfo['filename'] ) || empty( $fileinfo['dirname'] ) ) {
		// NOTE: This should never happen, but if it does, we'll be prepared, sort of!
		return preg_replace( '/\.\w+$/', '-' . uniqid() . $fileext, $file );
	}
	$filename = $fileinfo['filename'] . $fileext;
	$filenum  = '';
	add_filter( 'wp_unique_filename', 'ewww_image_optimizer_get_unique_filename_iterator', 99, 6 );
	$newname = wp_unique_filename( $fileinfo['dirname'], $filename );
	remove_filter( 'wp_unique_filename', 'ewww_image_optimizer_get_unique_filename_iterator', 99 );
	return trailingslashit( $fileinfo['dirname'] ) . $newname;
}

/**
 * Retrieve the unique filename iterator/number from wp_unique_filename().
 *
 * @param string        $filename                 Unique file name.
 * @param string        $ext                      File extension. Example: ".png".
 * @param string        $dir                      Directory path.
 * @param callable|null $unique_filename_callback Callback function that generates the unique file name.
 * @param string[]      $alt_filenames            Array of alternate file names that were checked for collisions.
 * @param int|string    $number                   The highest number that was used to make the file name unique
 *                                                or an empty string if unused.
 */
function ewww_image_optimizer_get_unique_filename_iterator( $filename, $ext, $dir, $unique_filename_callback, $alt_filenames = array(), $number = '' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! empty( $number ) ) {
		ewwwio_debug_message( "collision avoidance iterator: $number" );
		global $ewww_image;
		if ( isset( $ewww_image ) && is_object( $ewww_image ) ) {
			ewwwio_debug_message( 'storing in increment property' );
			$ewww_image->increment = $number;
		}
	}
	return $filename;
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
	if ( ! ewwwio()->gd_support() || ! ewwwio_check_memory_available( ( $width * $height ) * 4.8 ) ) { // 4.8 = 24-bit or 3 bytes per pixel multiplied by a factor of 1.6 for extra wiggle room.
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
	} elseif ( ewwwio()->gd_support() ) {
		$image = imagecreatefrompng( $filename );
		if ( ! $image ) {
			ewwwio_debug_message( 'could not load image' );
			return false;
		}
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
	if ( ewwwio()->imagick_support() ) {
		$image = new Imagick( $filename );
		$color = $image->getImageColorspace();
		ewwwio_debug_message( "color space is $color" );
		$image->destroy();
		if ( Imagick::COLORSPACE_CMYK === $color ) {
			return true;
		}
	} elseif ( ewwwio()->gd_support() ) {
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
		++$size_count;
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
		if ( is_array( $meta ) && ewww_image_optimizer_s3_uploads_enabled() && preg_match( '/^(http|s3|gs)\w*:/', get_attached_file( $id ) ) ) {
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
		$supported_types = ewwwio()->get_supported_types( 'all' );
		if ( ! in_array( $type, $supported_types, true ) ) {
			return;
		}

		if ( ! ewwwio()->tools_initialized && ! ewwwio()->local->os_supported() ) {
			ewwwio()->local->skip_tools();
		} elseif ( ! ewwwio()->tools_initialized ) {
			ewwwio()->tool_init();
		}
		$tools = ewwwio()->local->check_all_tools();

		// Run the appropriate code based on the mimetype.
		switch ( $type ) {
			case 'image/jpeg':
				// If jpegtran is missing and should not be skipped.
				if ( $tools['jpegtran']['enabled'] && ! $tools['jpegtran']['path'] && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_dismiss_exec_notice' ) ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: name of a tool like jpegtran */
						__( '%s is missing', 'ewww-image-optimizer' ),
						'<em>jpegtran</em>'
					) . '</div>';
				} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: JPG, PNG, GIF, WebP or PDF */
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
				if ( $tools['optipng']['enabled'] && ! $tools['optipng']['path'] ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: name of a tool like jpegtran */
						__( '%s is missing', 'ewww-image-optimizer' ),
						'<em>optipng</em>'
					) . '</div>';
				} elseif ( $tools['pngout']['enabled'] && ! $tools['pngout']['path'] ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: name of a tool like jpegtran */
						__( '%s is missing', 'ewww-image-optimizer' ),
						'<em>pngout</em>'
					) . '</div>';
				} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: JPG, PNG, GIF, WebP or PDF */
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
				if ( $tools['gifsicle']['enabled'] && ! $tools['gifsicle']['path'] ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: name of a tool like jpegtran */
						__( '%s is missing', 'ewww-image-optimizer' ),
						'<em>gifsicle</em>'
					) . '</div>';
				} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: JPG, PNG, GIF, WebP or PDF */
						__( '%s compression disabled', 'ewww-image-optimizer' ),
						'GIF'
					) . '</div>';
				} else {
					$convert_link = __( 'GIF to PNG', 'ewww-image-optimizer' );
					$convert_desc = __( 'PNG is generally better than GIF, but does not support animation. Animated images will not be converted.', 'ewww-image-optimizer' );
				}
				break;
			case 'image/bmp':
				// If jpegtran is missing and should not be skipped.
				if ( $tools['jpegtran']['enabled'] && ! $tools['jpegtran']['path'] && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_dismiss_exec_notice' ) ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: name of a tool like jpegtran */
						__( '%s is missing', 'ewww-image-optimizer' ),
						'<em>jpegtran</em>'
					) . '</div>';
				} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: JPG, PNG, GIF, WebP or PDF */
						__( '%s compression disabled', 'ewww-image-optimizer' ),
						'JPG'
					) . '</div>';
				} else {
					$convert_link = __( 'BMP to JPG', 'ewww-image-optimizer' );
					$convert_desc = __( 'Convert BMP image to the JPG format to save space.', 'ewww-image-optimizer' );
				}
				break;
			case 'application/pdf':
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: JPG, PNG, GIF, WebP or PDF */
						esc_html__( '%s compression disabled', 'ewww-image-optimizer' ),
						'PDF'
					) . '</div>';
				}
				break;
			case 'image/svg+xml':
				if ( $tools['svgcleaner']['enabled'] && ! $tools['svgcleaner']['path'] ) {
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
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_level' ) ) {
					$msg = '<div>' . sprintf(
						/* translators: %s: JPG, PNG, GIF, WebP or PDF */
						esc_html__( '%s compression disabled', 'ewww-image-optimizer' ),
						'WebP'
					) . '</div>';
				}
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
		$in_progress       = false;
		$migrated          = false;
		$optimized_images  = false;
		$backup_available  = false;
		$file_parts        = pathinfo( $file_path );
		$basename          = $file_parts['filename'];
		$action_id         = ewww_image_optimizer_get_primary_translated_media_id( $id );

		if ( $ewww_cdn ) {
			if ( ewww_image_optimizer_image_is_pending( $action_id, 'media-async' ) ) {
				echo '<div>' . esc_html__( 'In Progress', 'ewww-image-optimizer' ) . '</div>';
				$in_progress = true;
			} else {
				$sizes_pending = ewww_image_optimizer_attachment_has_pending_sizes( $id );
				if ( $sizes_pending ) {
					$in_progress     = true;
					$optimized_sizes = ewww_image_optimizer_get_optimized_sizes( $id, 'media', $meta );
					$total_sizes     = $sizes_pending + count( $optimized_sizes );
					echo '<div>' . sprintf(
						esc_html(
							/* translators: %1$d: The number of resize/thumbnail images */
							_n( '%1$d/%2$d compressed', '%1$d/%2$d sizes compressed', $total_sizes, 'ewww-image-optimizer' )
						),
						(int) count( $optimized_sizes ),
						(int) $total_sizes
					) . '</div>';
				}
			}
			if ( ! $in_progress ) {
				$optimized_images = ewww_image_optimizer_get_optimized_sizes( $id, 'media', $meta );
				if ( ! $optimized_images ) {
					// Attempt migration, but only if the original image is in the db, $migrated will be metadata on success, false on failure.
					$migrated = ewww_image_optimizer_migrate_meta_to_db( $id, $meta, true );
				}
				if ( $migrated ) {
					$optimized_images = ewww_image_optimizer_get_optimized_sizes( $id, 'media', $meta );
				}
				if ( ! $optimized_images ) {
					list( $possible_action_id, $optimized_images ) = ewww_image_optimizer_get_translated_media_results( $id );
					if ( $optimized_images ) {
						$action_id = $possible_action_id;
					}
				}
			}
			// If optimizer data exists in the db.
			if ( ! empty( $optimized_images ) ) {
				list( $detail_output, $converted, $backup_available ) = ewww_image_optimizer_custom_column_results( $action_id, $optimized_images );
				echo wp_kses_post( $detail_output );

				// Check for WebP results.
				$webp_size  = 0;
				$webp_error = '';
				foreach ( $optimized_images as $optimized_image ) {
					if ( 'full' === $optimized_image['resize'] ) {
						if ( ! empty( $optimized_image['webp_size'] ) ) {
							$webp_size = $optimized_image['webp_size'];
						} elseif ( ! empty( $optimized_image['webp_error'] ) && 2 !== (int) $optimized_image['webp_error'] ) {
							$webp_error = ewww_image_optimizer_webp_error_message( $optimized_image['webp_error'] );
						}
						break;
					}
				}
				if ( $webp_size ) {
					// Get a human readable filesize.
					$webp_size = ewww_image_optimizer_size_format( $webp_size );
					$webpurl   = esc_url( wp_get_attachment_url( $id ) . '.webp' );
					echo '<div>WebP: <a href="' . esc_url( $webpurl ) . '">' . esc_html( $webp_size ) . '</a></div>';
				} elseif ( $webp_error ) {
					echo '<div>' . esc_html( $webp_error ) . '</div>';
				}

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
						'<a class="ewww-manual-image-restore" data-id="%1$d" data-nonce="%2$s" href="%3$s">%4$s</a>',
						(int) $action_id,
						esc_attr( $ewww_manual_nonce ),
						esc_url( admin_url( "admin.php?action=ewww_image_optimizer_manual_image_restore&ewww_manual_nonce=$ewww_manual_nonce&ewww_attachment_ID=$action_id" ) ),
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
						++$sizes_to_opt;
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
		} else {
			$sizes_pending = ewww_image_optimizer_attachment_has_pending_sizes( $id );
			if ( $sizes_pending ) {
				$in_progress     = true;
				$optimized_sizes = ewww_image_optimizer_get_optimized_sizes( $id, 'media', $meta );
				$total_sizes     = $sizes_pending + count( $optimized_sizes );
				echo '<div>' . sprintf(
					esc_html(
						/* translators: %1$d: The number of resize/thumbnail images */
						_n( '%1$d/%2$d compressed', '%1$d/%2$d sizes compressed', $total_sizes, 'ewww-image-optimizer' )
					),
					(int) count( $optimized_sizes ),
					(int) $total_sizes
				) . '</div>';
			}
		}
		if ( ! $in_progress ) {
			$optimized_images = ewww_image_optimizer_get_optimized_sizes( $id, 'media', $meta );
			if ( ! $optimized_images ) {
				// Attempt migration, but only if the original image is in the db, $migrated will be metadata on success, false on failure.
				$migrated = ewww_image_optimizer_migrate_meta_to_db( $id, $meta, true );
			}
			if ( $migrated ) {
				$optimized_images = ewww_image_optimizer_get_optimized_sizes( $id, 'media', $meta );
			}
			if ( ! $optimized_images ) {
				list( $possible_action_id, $optimized_images ) = ewww_image_optimizer_get_translated_media_results( $id );
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
			if ( ! str_ends_with( $file_path, '.webp' ) && ! ewww_image_optimizer_easy_active() && ewwwio_is_file( $oldwebpfile ) && current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
				echo "<div><a href='" . esc_url( admin_url( 'options.php?page=ewww-image-optimizer-webp-migrate' ) ) . "'>" . esc_html__( 'Run WebP upgrade', 'ewww-image-optimizer' ) . '</a></div>';
			}

			// Check for WebP results.
			$webpfile   = $file_path . '.webp';
			$webp_size  = ewww_image_optimizer_filesize( $webpfile );
			$webp_error = '';
			if ( ! $webp_size ) {
				foreach ( $optimized_images as $optimized_image ) {
					if ( 'full' === $optimized_image['resize'] ) {
						if ( ! empty( $optimized_image['webp_size'] ) ) {
							$webp_size = $optimized_image['webp_size'];
						} elseif ( ! empty( $optimized_image['webp_error'] ) && 2 !== (int) $optimized_image['webp_error'] ) {
							$webp_error = ewww_image_optimizer_webp_error_message( $optimized_image['webp_error'] );
						}
						break;
					}
				}
			}
			if ( $webp_size ) {
				// Get a human readable filesize.
				$webp_size = ewww_image_optimizer_size_format( $webp_size );
				$webpurl   = esc_url( wp_get_attachment_url( $id ) . '.webp' );
				echo '<div>WebP: <a href="' . esc_url( $webpurl ) . '">' . esc_html( $webp_size ) . '</a></div>';
			} elseif ( $webp_error ) {
				echo '<div>' . esc_html( $webp_error ) . '</div>';
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
					'<a class="ewww-manual-image-restore" data-id="%1$d" data-nonce="%2$s" href="%3$s">%4$s</a>',
					(int) $action_id,
					esc_attr( $ewww_manual_nonce ),
					esc_url( admin_url( "admin.php?action=ewww_image_optimizer_manual_image_restore&ewww_manual_nonce=$ewww_manual_nonce&ewww_attachment_ID=$action_id" ) ),
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
					++$sizes_to_opt;
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
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ) {
		return '';
	}
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	foreach ( $optimized_images as $optimized_image ) {
		if ( 'full' === $optimized_image['resize'] ) {
			ewwwio_debug_message( "comparing $compression_level (current) vs. {$optimized_image['level']} (previous)" );
			if ( $compression_level < 30 && $compression_level < $optimized_image['level'] && $optimized_image['level'] > 20 ) {
				global $eio_backup;
				return $eio_backup->is_backup_available( $optimized_image['path'], $optimized_image );
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
				return ' <span title="' . esc_attr__( 'Compressed at a lower level than current setting.', 'ewww-image-optimizer' ) . '" class="ewww-variant-icon"><sup>!</sup></span>';
			}
		}
	}
	return '';
}

/**
 * Get a list of optimized sizes for a given attachment ID.
 *
 * @param int    $id The ID of the attachment/image.
 * @param string $gallery The type of image to look for. Optional, default is 'media'.
 * @param array  $meta The attachment metadata. Optional.
 * @return array A list of db records connected to the attachment.
 */
function ewww_image_optimizer_get_optimized_sizes( $id, $gallery = 'media', $meta = array() ) {
	global $wpdb;

	$optimized_sizes     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = %s AND image_size > 0 AND pending = 0 ORDER BY orig_size DESC", $id, $gallery ), ARRAY_A );
	$hide_inactive_sizes = false;

	if ( $optimized_sizes && ! empty( $meta['file'] ) && isset( $meta['sizes'] ) ) {
		if ( preg_match( '/-e[0-9]{13}\./', $meta['file'] ) ) {
			$hide_inactive_sizes = true;
		}
		foreach ( $optimized_sizes as $optimized_size ) {
			if ( ! empty( $optimized_size['path'] ) && preg_match( '/-e[0-9]{13}\./', $optimized_size['path'] ) ) {
				$hide_inactive_sizes = true;
				break;
			}
		}
		if ( $hide_inactive_sizes ) {
			$valid_sizes = array();
			foreach ( $optimized_sizes as $optimized_size ) {
				if ( wp_basename( $meta['file'] ) === wp_basename( $optimized_size['path'] ) ) {
					$valid_sizes[] = $optimized_size;
					continue;
				}
				if ( ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
					foreach ( $meta['sizes'] as $size ) {
						if ( ! empty( $size['file'] ) && wp_basename( $size['file'] ) === wp_basename( $optimized_size['path'] ) ) {
							$valid_sizes[] = $optimized_size;
							continue 2;
						}
					}
				}
			}
			if ( ! empty( $valid_sizes ) ) {
				$optimized_sizes = $valid_sizes;
			}
		}
	}

	return $optimized_sizes;
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
		++$sizes_to_opt;
		// Store info on the sizes we've processed, so we can check the list for duplicate sizes.
		$processed[ $size ]['width']  = $data['width'];
		$processed[ $size ]['height'] = $data['height'];
	} // End foreach().
	return $sizes_to_opt;
}

/**
 * Get a result message for the given resize status code.
 *
 * @param string $file The name of the file.
 * @param int    $resize_code The error code for image resizing/scaling.
 * @return string The human readable message for the given status code.
 */
function ewww_image_optimizer_resize_results_message( $file, $resize_code = 0 ) {
	if ( is_null( $resize_code ) ) {
		return '';
	}
	switch ( $resize_code ) {
		case 0:
			$full_path = ewww_image_optimizer_absolutize_path( $file );
			if ( ewwwio_is_file( $full_path ) ) {
				list( $width, $height ) = wp_getimagesize( $full_path );
				/* translators: 1: width in pixels 2: height in pixels */
				return sprintf( __( 'Resized to %1$s(w) x %2$s(h)', 'ewww-image-optimizer' ), $width, $height );
			}
			return __( 'Resized successfully', 'ewww-image-optimizer' );
		case 1:
			return __( 'Encountered a WP image editing error while attempting resize', 'ewww-image-optimizer' );
		case 2:
			return __( 'Resizing did not reduce the file size, result discarded', 'ewww-image-optimizer' );
		case 3:
			return __( 'Encountered an error while attempting resize', 'ewww-image-optimizer' );
		default:
			return '';
	}
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
		$resize_status = '';
		if ( ! empty( $optimized_image['attachment_id'] ) ) {
			$id = $optimized_image['attachment_id'];
		}
		$orig_size += $optimized_image['orig_size'];
		$opt_size  += $optimized_image['image_size'];
		if ( 'full' === $optimized_image['resize'] ) {
			$updated_time = strtotime( $optimized_image['updated'] );
			global $eio_backup;
			$backup_available = $eio_backup->is_backup_available( $optimized_image['path'], $optimized_image );
			if ( empty( $ewwwio_resize_status ) && ! is_null( $optimized_image['resize_error'] ) ) {
				ewwwio_debug_message( "resize results found: {$optimized_image['resize_error']}, {$optimized_image['resized_width']} x {$optimized_image['resized_height']}" );
				$resize_status = ewww_image_optimizer_resize_results_message( $optimized_image['path'], $optimized_image['resize_error'] );
			}
		}
		if ( ! empty( $optimized_image['converted'] ) ) {
			$converted = ewww_image_optimizer_absolutize_path( $optimized_image['converted'] );
		}
		++$sizes_to_opt;
		if ( ! empty( $ewwwio_resize_status ) ) {
			$resize_status = $ewwwio_resize_status;
		}
		if ( ! empty( $optimized_image['resize'] ) ) {
			$display_size   = ewww_image_optimizer_size_format( $optimized_image['image_size'] );
			$detail_output .= '<tr><td><strong>' . ucfirst( $optimized_image['resize'] ) . "</strong></td><td>$display_size</td><td>" . esc_html( ewww_image_optimizer_image_results( $optimized_image['orig_size'], $optimized_image['image_size'] ) ) . ( ! empty( $resize_status ) ? '<br>' . $resize_status : '' ) . '</td></tr>';
		}
	}
	$detail_output .= '</table>';

	if ( ! empty( $ewwwio_resize_status ) ) {
		/* $output .= '<div>' . esc_html( $ewwwio_resize_status ) . '</div>'; */
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
 * If attachment is translated (and duplicated), get the primary ID number.
 *
 * @param int $id The attachment ID number to search for in the translation plugin table(s).
 * @return int The primary/original ID number.
 */
function ewww_image_optimizer_get_primary_translated_media_id( $id ) {
	if ( $id && defined( 'ICL_SITEPRESS_VERSION' ) && get_post_meta( $id, 'wpml_media_processed', true ) ) {
		$possible_ids = ewww_image_optimizer_get_translated_media_ids( $id );
		if ( ewww_image_optimizer_iterable( $possible_ids ) ) {
			sort( $possible_ids );
			return $possible_ids[0];
		}
	}
	return apply_filters( 'ewwwio_primary_translated_media_id', $id );
}

/**
 * Get attachment IDs for translation (WPML) replicates.
 *
 * @param int $id The attachment ID number to search for in the translation tables/data.
 * @return array The resultant attachment IDs.
 */
function ewww_image_optimizer_get_translated_media_ids( $id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$translations = array();
	if ( $id && defined( 'ICL_SITEPRESS_VERSION' ) ) {
		$trid = apply_filters( 'wpml_element_trid', null, $id, 'post_attachment' );
		if ( ! empty( $trid ) ) {
			$translations = apply_filters( 'wpml_get_element_translations', null, $trid, 'post_attachment' );
			if ( ! empty( $translations ) ) {
				$translations = wp_list_pluck( $translations, 'element_id' );
				$translations = array_filter( $translations );
			}
		}
	}
	return apply_filters( 'ewwwio_translated_media_ids', $translations, $id );
}

/**
 * Gets results for translation (WPML) replicates.
 *
 * @param int $id The attachment ID number to search for in the translation tables/data.
 * @return array The resultant attachment ID, and a list of image optimization results.
 */
function ewww_image_optimizer_get_translated_media_results( $id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$translations = ewww_image_optimizer_get_translated_media_ids( $id );
	if ( ewww_image_optimizer_iterable( $translations ) ) {
		global $wpdb;
		foreach ( $translations as $translation ) {
			ewwwio_debug_message( "checking {$translation} for results with WPML (or another translation plugin)" );
			$optimized_images = ewww_image_optimizer_get_optimized_sizes( $translation );
			if ( ! empty( $optimized_images ) ) {
				return array( (int) $translation, $optimized_images );
			}
		}
	}
	return array( $id, array() );
}

/**
 * Removes optimization data from metadata, because we store it all in the images table now.
 *
 * @param array $meta The attachment metadata.
 * @param int   $id The attachment ID number.
 * @return array The attachment metadata after being cleaned.
 */
function ewww_image_optimizer_clean_meta( $meta, $id ) {
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
			return ewww_image_optimizer_clean_meta( $meta, $id );
		}
	} elseif ( ! $file_path ) {
		ewwwio_debug_message( 'no file found for attachment' );
		return ewww_image_optimizer_clean_meta( $meta, $id );
	}
	$converted        = ( is_array( $meta ) && ! empty( $meta['converted'] ) && ! empty( $meta['orig_file'] ) ? trailingslashit( dirname( $file_path ) ) . wp_basename( $meta['orig_file'] ) : false );
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
		$base_dir = trailingslashit( dirname( $file_path ) );
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
			$converted   = ( is_array( $data ) && ! empty( $data['converted'] ) && ! empty( $data['orig_file'] ) ? trailingslashit( dirname( $resize_path ) ) . wp_basename( $data['orig_file'] ) : false );
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
	$meta = ewww_image_optimizer_clean_meta( $meta, $id );
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ewww_image_optimizer_function_exists( 'print_r' ) ) {
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
	check_ajax_referer( 'ewww-image-optimizer-notice' );
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
	check_ajax_referer( 'ewww-image-optimizer-notice' );
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
	check_ajax_referer( 'ewww-image-optimizer-notice' );
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
	check_ajax_referer( 'ewww-image-optimizer-notice' );
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	update_option( 'ewww_image_optimizer_dismiss_review_notice', true, false );
	update_site_option( 'ewww_image_optimizer_dismiss_review_notice', true );
	die();
}

/**
 * Disables the newsletter signup banner.
 */
function ewww_image_optimizer_dismiss_newsletter_signup() {
	ewwwio_ob_clean();
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that the user is properly authorized.
	check_ajax_referer( 'ewww-image-optimizer-settings' );
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	update_option( 'ewww_image_optimizer_hide_newsletter_signup', true, false );
	update_site_option( 'ewww_image_optimizer_hide_newsletter_signup', true );
	die( 'done' );
}

/**
 * Add our bulk optimize action to the bulk actions drop-down menu.
 *
 * @param array $bulk_actions A list of actions available already.
 * @return array The list of actions, with our bulk action included.
 */
function ewww_image_optimizer_add_bulk_media_actions( $bulk_actions ) {
	if ( is_array( $bulk_actions ) ) {
		$bulk_actions['bulk_optimize'] = __( 'Bulk Optimize', 'ewww-image-optimizer' );
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
	if ( empty( $doaction ) || 'bulk_optimize' !== $doaction ) {
		return $redirect_to;
	}
	// If there is no media to optimize, do nothing.
	if ( ! ewww_image_optimizer_iterable( $post_ids ) ) {
		return $redirect_to;
	}
	check_admin_referer( 'bulk-media' );
	// Prep the attachment IDs for optimization.
	$ids = implode( ',', array_map( 'intval', $post_ids ) );
	return add_query_arg(
		array(
			'page' => 'ewww-image-optimizer-bulk',
			'ids'  => $ids,
		),
		admin_url( 'upload.php' )
	);
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
	if ( 'image/webp' === $type ) {
		return (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_level' );
	}
	return 0;
}

/**
 * Check old and new compression levels for an image to see if it ought to be re-optimized in smart mode.
 *
 * @param int $old_level The previous compression level used on an image.
 * @param int $new_level The current compression level for the image mime-type.
 * @return bool True if they are not matched/equivalent, false otherwise.
 */
function ewww_image_optimizer_level_mismatch( $old_level, $new_level ) {
	$old_level = (int) $old_level;
	$new_level = (int) $new_level;
	if ( empty( $old_level ) || empty( $new_level ) ) {
		return false;
	}
	if ( 20 === $old_level && 10 === $new_level ) {
		return false;
	}
	if ( $new_level === $old_level ) {
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
 * @param mixed  $default_value The default to use if not found/set, defaults to false, but not currently used.
 * @param bool   $single Use single-site setting regardless of multisite activation. Default is off/false.
 * @return mixed The value of the option.
 */
function ewww_image_optimizer_get_option( $option_name, $default_value = false, $single = false ) {
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
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
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
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
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
	global $easyio_site_registered;
	global $easyio_site_id;

	if ( ! get_option( 'ewww_image_optimizer_bulk_resume' ) && ! get_option( 'ewww_image_optimizer_aux_resume' ) ) {
		ewww_image_optimizer_delete_queue_images();
	}

	add_filter( 'admin_footer_text', 'ewww_image_optimizer_footer_review_text' );
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
	if ( is_object( $exactdn ) && has_action( 'admin_notices', 'ewww_image_optimizer_notice_exactdn_domain_mismatch' ) ) {
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

	// Check options like Force Re-opt, Smart Re-opt, or WebP Only.
	ewww_image_optimizer_check_bulk_options( $_REQUEST );

	// Number of images in the ewwwio_table (previously optimized images).
	$image_count     = ewww_image_optimizer_aux_images_table_count();
	$easyio_site_url = ewwwio()->content_url();
	$loading_image   = plugins_url( '/images/spinner.gif', __FILE__ );
	ewww_image_optimizer_easy_site_registered( $easyio_site_url );
	add_thickbox();
	wp_enqueue_script( 'ewww-beacon-script', plugins_url( '/includes/eio-beacon.js', __FILE__ ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION, true );
	wp_enqueue_script( 'ewww-settings-script', plugins_url( '/includes/eio-settings.js', __FILE__ ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION, true );
	wp_enqueue_script( 'ewww-bulk-table-script', plugins_url( '/includes/eio-bulk-table.js', __FILE__ ), array( 'jquery', 'jquery-ui-slider' ), EWWW_IMAGE_OPTIMIZER_VERSION, true );
	wp_enqueue_style( 'jquery-ui-tooltip-custom', plugins_url( '/includes/jquery-ui-1.10.1.custom.css', __FILE__ ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
	wp_localize_script(
		'ewww-settings-script',
		'ewww_vars',
		array(
			'_wpnonce'                  => wp_create_nonce( 'ewww-image-optimizer-settings' ),
			'invalid_response'          => esc_html__( 'Received an invalid response from your website, please check for errors in the Developer Tools console of your browser.', 'ewww-image-optimizer' ),
			'loading_image_url'         => plugins_url( '/images/spinner.gif', __FILE__ ),
			'backup_warning'            => esc_html__( 'Please be sure to backup your site before proceeding. Do you wish to continue?', 'ewww-image-optimizer' ),
			'operation_stopped'         => esc_html__( 'Operation stopped.', 'ewww-image-optimizer' ),
			'bulk_refresh_error'        => esc_html__( 'Failed to refresh queue status, please manually refresh the page for further updates.', 'ewww-image-optimizer' ),
			'remove_failed'             => esc_html__( 'Could not remove image from table.', 'ewww-image-optimizer' ),
			'original_restored'         => esc_html__( 'Original Restored', 'ewww-image-optimizer' ),
			'restoring'                 => '<p>' . esc_html__( 'Restoring', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' /></p>",
			'easyio_register_warning'   => esc_html__( 'This will register all your sites with the Easy IO CDN and will take some time to complete. Do you wish to proceed?', 'ewww-image-optimizer' ),
			'easyio_register_success'   => esc_html__( 'Easy IO registration complete. Please wait 5-10 minutes and then activate your sites.', 'ewww-image-optimizer' ),
			'exactdn_network_warning'   => esc_html__( 'This will attempt to activate Easy IO on all sites within the multi-site network. Please be sure you have registered all your site URLs before continuing.', 'ewww-image-optimizer' ),
			'easyio_deregister_warning' => esc_html__( 'You are about to remove this site from your account. Do you wish to proceed?', 'ewww-image-optimizer' ),
			'exactdn_network_success'   => esc_html__( 'Easy IO setup and verification is complete.', 'ewww-image-optimizer' ),
			'webp_cloud_warning'        => esc_html__( 'If you have not run the Bulk Optimizer on existing images, you will likely encounter broken image URLs. Are you ready to continue?', 'ewww-image-optimizer' ),
			'network_blog_ids'          => $blog_ids,
			'blog_id'                   => (int) get_current_blog_id(),
			'easy_autoreg'              => ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ? true : false,
			'easyio_site_id'            => (int) $easyio_site_id,
			'easyio_site_registered'    => (bool) $easyio_site_registered,
			'easymode'                  => ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_ludicrous_mode' ),
			/* translators: %d: number of images */
			'count_string'              => sprintf( esc_html__( '%d total images', 'ewww-image-optimizer' ), $image_count ),
			'image_count'               => (int) $image_count,
			'scan_only_mode'            => get_option( 'ewww_image_optimizer_pause_image_queue' ) ? true : false,
			'bulk_init'                 => ! empty( $_GET['bulk_optimize'] ) ? true : false,
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
	if ( ewww_image_optimizer_easy_active() || ewwwio_is_cf_host() ) {
		if ( ewwwio_extract_from_markers( ewww_image_optimizer_htaccess_path(), 'EWWWIO' ) ) {
			ewwwio_debug_message( 'removing htaccess webp to prevent EasyIO/Cloudflare/Cloudways problems' );
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
		ewwwio_debug_message( 'current rules: ' . implode( '<br>', (array) $current_rules ) );
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

	$hostname = ewwwio()->parse_url( get_site_url(), PHP_URL_HOST );
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
	// Note, unless it's connected to Easy IO directly, this could be seen as an unauthorized external API call, even though its a DNS lookup, so double-check with the plugins team before enabling.
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
	ewwwio_debug_message( 'EWWWIO_CONTENT_DIR: ' . EWWWIO_CONTENT_DIR );
	ewwwio_debug_message( 'home url (Site URL): ' . get_home_url() );
	ewwwio_debug_message( 'site url (WordPress URL): ' . get_site_url() );
	$upload_info = wp_get_upload_dir();
	ewwwio_debug_message( 'wp_upload_dir (baseurl): ' . $upload_info['baseurl'] );
	ewwwio_debug_message( 'wp_upload_dir (basedir): ' . $upload_info['basedir'] );
	ewwwio_debug_message( "content_width: $content_width" );
	ewwwio_debug_message( 'registered stream wrappers: ' . implode( ',', stream_get_wrappers() ) );

	ewwwio_debug_message( 'items in media queue: ' . ewwwio()->background_media->count_queue() );
	ewwwio_debug_message( 'items in (single) image queue: ' . ewwwio()->background_image->count_queue() );
	ewwwio_debug_message( 'items in attachment update queue: ' . ewwwio()->background_attachment_update->count_queue() );

	if ( is_multisite() ) {
		ewwwio_debug_message( 'allowing multisite override: ' . ( get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) ? 'yes' : 'no' ) );
	}
	if ( ! ewwwio()->tools_initialized ) {
		if ( ewwwio()->cloud_mode ) {
			ewwwio()->local->skip_tools();
		} elseif ( ! ewwwio()->local->os_supported() ) {
			ewwwio()->local->skip_tools();
		} else {
			ewwwio()->tool_init();
			ewwwio()->notice_utils( 'quiet' );
		}
	}
	if ( wp_using_ext_object_cache() ) {
		ewwwio_debug_message( 'using external object cache' );
	} else {
		ewwwio_debug_message( 'not external cache' );
	}
	ewwwio()->gd_support();
	ewwwio()->gmagick_support();
	ewwwio()->imagick_support();
	ewwwio()->gd_supports_webp();
	ewwwio()->imagick_supports_webp();
	if ( PHP_OS !== 'WINNT' && ! ewwwio()->cloud_mode && ewwwio()->local->exec_check() ) {
		ewwwio()->local->find_nix_binary( 'nice' );
	}
	ewwwio_debug_message( ewww_image_optimizer_aux_images_table_count( true ) . ' images have been optimized' );
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
	ewwwio_debug_message( 'webp level: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_level' ) );
	ewwwio_debug_message( 'bulk delay: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) );
	ewwwio_debug_message( 'backup mode: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) );
	ewwwio_debug_message( 'ExactDN enabled: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'ExactDN all the things: ' . ( ewww_image_optimizer_get_option( 'exactdn_all_the_things' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'ExactDN lossy: ' . intval( ewww_image_optimizer_get_option( 'exactdn_lossy' ) ) );
	ewwwio_debug_message( 'ExactDN hidpi: ' . intval( ewww_image_optimizer_get_option( 'exactdn_hidpi' ) ) );
	ewwwio_debug_message( 'ExactDN resize existing: ' . ( ewww_image_optimizer_get_option( 'exactdn_resize_existing' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'ExactDN attachment queries: ' . ( ewww_image_optimizer_get_option( 'exactdn_prevent_db_queries' ) ? 'off' : 'on' ) );
	ewwwio_debug_message( 'Easy IO exclusions:' );
	$eio_exclude_paths = ewww_image_optimizer_get_option( 'exactdn_exclude' ) ? esc_html( implode( "\n", (array) ewww_image_optimizer_get_option( 'exactdn_exclude' ) ) ) : '';
	ewwwio_debug_message( $eio_exclude_paths );
	ewwwio_debug_message( 'add missing dimensions: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_add_missing_dims' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'lazy load: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ? 'on' : 'off' ) );
	ewwwio_other_lazy_detected();
	ewwwio_debug_message( 'LL autoscale: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_ll_autoscale' ) ? 'on' : 'off' ) );
	if ( defined( 'EIO_LAZY_FOLD' ) ) {
		ewwwio_debug_message( 'LL above-the-fold: ' . EIO_LAZY_FOLD );
	} else {
		ewwwio_debug_message( 'LL above-the-fold: ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_ll_abovethefold' ) );
	}
	ewwwio_debug_message( 'LQIP: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_use_lqip' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'DCIP: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_use_dcip' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'S(VG)IIP: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_use_siip' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'external CSS background (all things): ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_ll_all_things' ) );
	ewwwio_debug_message( 'LL exclusions:' );
	$ll_exclude_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_ll_exclude' ) ? esc_html( implode( "\n", (array) ewww_image_optimizer_get_option( 'ewww_image_optimizer_ll_exclude' ) ) ) : '';
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
	ewwwio_debug_message( 'sharpen: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_sharpen' ) ? 'yes' : 'no' ) );
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
					'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
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
	$aux_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_aux_paths' ) ? esc_html( implode( "\n", (array) ewww_image_optimizer_get_option( 'ewww_image_optimizer_aux_paths' ) ) ) : '';
	ewwwio_debug_message( $aux_paths );
	ewwwio_debug_message( 'folders to ignore:' );
	$exclude_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_exclude_paths' ) ? esc_html( implode( "\n", (array) ewww_image_optimizer_get_option( 'ewww_image_optimizer_exclude_paths' ) ) ) : '';
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
	ewwwio_debug_message( 'disabled sizes:' );
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
	ewwwio_debug_message( 'bmpconvert: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_bmp_convert' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'bmp2png: ' . ( defined( 'EWWW_IMAGE_OPTIMIZER_BMP_TO_PNG' ) && ! EWWW_IMAGE_OPTIMIZER_BMP_TO_PNG ? 'off' : 'on' ) );
	ewwwio_debug_message( 'png2jpg fill:' );
	ewww_image_optimizer_jpg_background();
	ewwwio_debug_message( 'webp conversion: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'js webp rewriting: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'picture webp rewriting: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_picture_webp' ) ? 'on' : 'off' ) );
	ewwwio_debug_message( 'WebP Rewrite exclusions:' );
	$webp_exclude_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_rewrite_exclude' ) ? esc_html( implode( "\n", (array) ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_rewrite_exclude' ) ) ) : '';
	ewwwio_debug_message( $webp_exclude_paths );
	ewwwio_debug_message( 'webp paths:' );
	$webp_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' ) ? esc_html( implode( "\n", (array) ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' ) ) ) : '';
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
	if ( ewwwio()->function_exists( '\ini_get' ) ) {
		ewwwio_debug_message( 'max_execution_time: ' . ini_get( 'max_execution_time' ) );
	}
	if ( ewww_image_optimizer_stl_check() ) {
		ewwwio_debug_message( 'set_time_limit allowed' );
	}
	if ( ewwwio()->function_exists( '\sleep', true ) ) {
		ewwwio_debug_message( 'sleep allowed' );
	}
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
	$easyio_site_url      = ewwwio()->content_url();
	$no_tracking          = false;
	$webp_available       = ewww_image_optimizer_webp_available();
	$bulk_available       = false;
	$tools_available      = true;
	$backup_mode          = 'local';
	if ( ewww_image_optimizer_background_mode_enabled() ) {
		$bulk_link = admin_url( 'options-general.php?page=ewww-image-optimizer-options&bulk_optimize=1' );
	} else {
		$bulk_link = admin_url( 'upload.php?page=ewww-image-optimizer-bulk' );
	}
	if ( ! ewwwio()->tools_initialized ) {
		if ( ewwwio()->cloud_mode ) {
			ewwwio()->local->skip_tools();
		} elseif ( ! ewwwio()->local->os_supported() ) {
			ewwwio()->local->skip_tools();
		} else {
			ewwwio()->tool_init();
			ewwwio()->notice_utils( 'quiet' );
		}
	}

	$tools = ewwwio()->local->check_all_tools();
	if (
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ||
		ewww_image_optimizer_easy_active() ||
		! empty( $_GET['show-premium'] )
	) {
		$show_premium = true;
	} elseif (
		( ! ewwwio()->local->exec_check() || ! ewwwio()->local->os_supported() ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_dismiss_exec_notice' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) &&
		! ewww_image_optimizer_easy_active()
	) {
		$display_exec_notice = true;
	} elseif (
		( $tools['jpegtran']['enabled'] && ! $tools['jpegtran']['path'] ) ||
		( $tools['optipng']['enabled'] && ! $tools['optipng']['path'] ) ||
		( $tools['gifsicle']['enabled'] && ! $tools['gifsicle']['path'] )
	) {
		$tools_missing   = array();
		$tools_available = false;
		if ( $tools['jpegtran']['enabled'] && ! $tools['jpegtran']['path'] ) {
			$tools_missing[] = 'jpegtran';
		} elseif ( $tools['jpegtran']['path'] ) {
			$tools_available = true;
		}
		if ( $tools['optipng']['enabled'] && ! $tools['optipng']['path'] ) {
			$tools_missing[] = 'optipng';
		} elseif ( $tools['optipng']['path'] ) {
			$tools_available = true;
		}
		if ( $tools['gifsicle']['enabled'] && ! $tools['gifsicle']['path'] ) {
			$tools_missing[] = 'gifsicle';
		} elseif ( $tools['gifsicle']['path'] ) {
			$tools_available = true;
		}
		$tools_missing_notice = true;
		// Expand the missing utilities list for use in the error message.
		$tools_missing_message = implode( ', ', $tools_missing );
	}
	if (
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ||
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ||
		ewwwio()->local->exec_check()
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
		if ( 2 === $wizard_step ) {
			if ( ! empty( $_POST['ewww_image_optimizer_goal_save_space'] ) ) {
				ewwwio_debug_message( 'wants to save space' );
				update_option( 'ewww_image_optimizer_goal_save_space', true, false );
			} else {
				ewwwio_debug_message( 'storage space? who cares!' );
				update_option( 'ewww_image_optimizer_goal_save_space', false, false );
			}
			if ( ! empty( $_POST['ewww_image_optimizer_goal_site_speed'] ) ) {
				ewwwio_debug_message( 'hurray for speed!' );
				update_option( 'ewww_image_optimizer_goal_site_speed', true, false );
			} else {
				ewwwio_debug_message( "I'm not slow, you're slow!" );
				update_option( 'ewww_image_optimizer_goal_site_speed', false, false );
			}
		}
		if ( ! empty( $_POST['ewww_image_optimizer_budget'] ) && 'free' === $_POST['ewww_image_optimizer_budget'] ) {
			if ( $display_exec_notice ) {
				ewwwio()->enable_free_exec();
			}
			if ( empty( $tools['optipng']['path'] ) ) {
				ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_level', 0 );
			}
			if ( empty( $tools['gifsicle']['path'] ) ) {
				ewww_image_optimizer_set_option( 'ewww_image_optimizer_gif_level', 0 );
			}
			if ( $tools_missing_notice && ! $tools_available ) {
				ewwwio()->enable_free_exec();
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
			if ( ! empty( $_POST['ewww_image_optimizer_backup_files'] ) && 'local' === $_POST['ewww_image_optimizer_backup_files'] ) {
				ewww_image_optimizer_set_option( 'ewww_image_optimizer_backup_files', 'local' );
			} elseif ( ! empty( $_POST['ewww_image_optimizer_backup_files'] ) && 'cloud' === $_POST['ewww_image_optimizer_backup_files'] ) {
				ewww_image_optimizer_set_option( 'ewww_image_optimizer_backup_files', 'cloud' );
			}
			update_option( 'ewww_image_optimizer_wizard_complete', true, false );
			$debug_info = EWWW\Base::$debug_data;
			ewwwio()->temp_debug_end();
		}
		wp_add_inline_script(
			'ewww-settings-script',
			'ewww_vars.save_space = ' . ( ! empty( $_POST['ewww_image_optimizer_goal_save_space'] ) ? 1 : 0 ) . ";\n" .
			'ewww_vars.site_speed = ' . ( ! empty( $_POST['ewww_image_optimizer_goal_site_speed'] ) ? 1 : 0 ) . ";\n"
		);
	}
	$cloud_key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	if ( $cloud_key && 'local' !== ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ) {
		$backup_mode = 'cloud';
	}
	if (
		'local' === $backup_mode &&
		get_option( 'ewww_image_optimizer_goal_save_space' ) &&
		'local' !== ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' )
	) {
		$backup_mode = '';
	}
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
		<?php if ( get_option( 'easyio_exactdn' ) ) : ?>
						<script>var exactdn_registered = true;</script>
		<?php else : ?>
						<div id='ewwwio-easy-activation-result'></div>
						<div class='ewwwio-easy-setup-instructions'>
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
				<?php if ( ! ewww_image_optimizer_easy_site_registered( $easyio_site_url ) ) : ?>
					<?php if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) : ?>
							<a class='easyio-cloud-key-ui' href="<?php echo esc_url( add_query_arg( 'site_url', trim( $easyio_site_url ), 'https://ewww.io/manage-sites/' ) ); ?>" target="_blank"><?php esc_html_e( 'Verify that this site is added to your account:', 'ewww-image-optimizer' ); ?></a>
					<?php else : ?>
							<a class='easyio-cloud-key-ui' style='display:none;' href="<?php echo esc_url( add_query_arg( 'site_url', trim( $easyio_site_url ), 'https://ewww.io/manage-sites/' ) ); ?>" target="_blank"><?php esc_html_e( 'Verify that this site is added to your account:', 'ewww-image-optimizer' ); ?></a>
							<a class='easyio-manual-ui' href="<?php echo esc_url( add_query_arg( 'site_url', trim( $easyio_site_url ), 'https://ewww.io/manage-sites/' ) ); ?>" target="_blank"><?php esc_html_e( 'First, add your Site URL to your account:', 'ewww-image-optimizer' ); ?></a>
					<?php endif; ?>
							<input type='text' id='exactdn_site_url' name='exactdn_site_url' value='<?php echo esc_url( trim( $easyio_site_url ) ); ?>' readonly />
							<span id='exactdn-site-url-copy'><?php esc_html_e( 'Click to Copy', 'ewww-image-optimizer' ); ?></span>
							<span id='exactdn-site-url-copied'><?php esc_html_e( 'Copied', 'ewww-image-optimizer' ); ?></span><br>
							<script>var exactdn_registered = false;</script>
				<?php else : ?>
							<script>var exactdn_registered = true;</script>
				<?php endif; ?>
							<a id='ewwwio-easy-activate' href='#' class='button-secondary'><?php esc_html_e( 'Activate', 'ewww-image-optimizer' ); ?></a>
							<span id='ewwwio-easy-activation-processing'><img src='<?php echo esc_url( $loading_image_url ); ?>' alt='loading'/></span>
			<?php elseif ( class_exists( 'EWWW\ExactDN' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) && $exactdn->get_exactdn_domain() && $exactdn->verify_domain( $exactdn->get_exactdn_domain() ) ) : ?>
							<br><span style="color: #3eadc9; font-weight: bolder"><?php esc_html_e( 'Verified', 'ewww-image-optimizer' ); ?></span>
							<span class="dashicons dashicons-yes"></span>
			<?php endif; ?>
						</div>
		<?php endif; ?>
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
					<select id='ewww_image_optimizer_backup_files' name='ewww_image_optimizer_backup_files'>
						<option value=''>
							<?php esc_html_e( 'Disabled', 'ewww-image-optimizer' ); ?>
						</option>
						<option value='local' <?php selected( $backup_mode, 'local' ); ?>>
							<?php esc_html_e( 'Local', 'ewww-image-optimizer' ); ?>
						</option>
						<option <?php disabled( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ); ?> value='cloud' <?php selected( $backup_mode, 'cloud' ); ?>>
							<?php esc_html_e( 'Cloud', 'ewww-image-optimizer' ); ?>
						</option>
					</select>
					<span class='description'><?php esc_html_e( 'Image Backups', 'ewww-image-optimizer' ); ?></span>
				</p>
				<p>
					<input type='checkbox' id='ewww_image_optimizer_enable_help' name='ewww_image_optimizer_enable_help' value='true' />
					<label for='ewww_image_optimizer_enable_help'><?php esc_html_e( 'Embedded Help', 'ewww-image-optimizer' ); ?></label><br>
					<span class='description'><?php esc_html_e( 'Access documentation and support from your WordPress dashboard. Uses resources from external servers.', 'ewww-image-optimizer' ); ?></span>
				</p>
		<?php if ( ! $no_tracking ) : ?>
				<p>
					<input type='checkbox' id='ewww_image_optimizer_allow_tracking' name='ewww_image_optimizer_allow_tracking' value='true' />
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
					( 'network-multisite' === esc_attr( $network ) ?
					esc_html__( 'Media Library', 'ewww-image-optimizer' ) . ' -> ' . esc_html__( 'Bulk Optimizer', 'ewww-image-optimizer' ) :
					'<a href="' . esc_url( $bulk_link ) . '">' . esc_html__( 'Bulk Optimizer', 'ewww-image-optimizer' ) . '</a>'
					)
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
						/* translators: 1: link to https://ewww.io/plans/ 2: discount code (yes, you as a translator may use it) */
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
		email: '<?php echo esc_js( $help_email ); ?>',
		text: '\n\n---------------------------------------\n<?php echo wp_kses_post( $hs_debug ); ?>',
	});
</script>
				<?php
			}
			?>
	<?php endif; ?>
		</div>
	</div><!-- end #ewwwio-wizard -->
</div><!-- end #ewww-settings-wrap -->
<script> var ewww_autopoll = false;</script>
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
	$debug_info = EWWW\Base::$debug_data;
	ewwwio()->temp_debug_end();
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
					<?php esc_html_e( 'Enabling Lazy Load alongside JS WebP enables better compatibility with some themes/plugins. Alternatively, you may try Picture WebP Rewriting for a JavaScript-free delivery method.', 'ewww-image-optimizer' ); ?>
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
		email: '<?php echo esc_js( $help_email ); ?>',
		text: '\n\n---------------------------------------\n<?php echo wp_kses_post( $hs_debug ); ?>',
	});
</script>
<script> var ewww_autopoll = false;</script>
		<?php
	}
}

/**
 * Display the status of the bulk async process.
 */
function ewww_image_optimizer_bulk_async_show_status() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$loading_image = plugins_url( '/images/spinner.gif', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE );

	$media_queue_running = false;
	if ( ewwwio()->background_media->is_process_running() ) {
		$media_queue_running = true;
	}
	$image_queue_running = false;
	if ( ewwwio()->background_image->is_process_running() ) {
		$image_queue_running = true;
	}

	$media_queue_count = ewwwio()->background_media->count_queue();
	$image_queue_count = ewwwio()->background_image->count_queue();

	if ( $media_queue_count && ! $media_queue_running ) {
		ewwwio_debug_message( 'rebooting media queue' );
		ewwwio()->background_media->dispatch();
	} elseif ( $image_queue_count && ! $image_queue_running && ! get_option( 'ewww_image_optimizer_pause_image_queue' ) ) {
		ewwwio_debug_message( 'rebooting image queue' );
		ewwwio()->background_image->dispatch();
	} elseif ( 'scanning' === get_option( 'ewww_image_optimizer_aux_resume' ) && ! $media_queue_count ) {
		if ( ! get_transient( 'ewww_image_optimizer_aux_lock' ) ) {
			ewwwio_debug_message( 'running scheduled optimization' );
			ewwwio()->async_scan->data(
				array(
					'ewww_scan' => 'scheduled',
				)
			)->dispatch();
		}
	}

	$queue_message = '';
	$autopoll      = false;
	if ( $media_queue_count ) {
		/* translators: %s: number of images/uploads */
		$queue_message = sprintf( __( '%s media uploads left to scan', 'ewww-image-optimizer' ), number_format_i18n( $media_queue_count ) );
	} elseif ( $image_queue_count ) {
		/* translators: %s: number of images */
		$queue_message = sprintf( __( '%s images left to optimize', 'ewww-image-optimizer' ), number_format_i18n( $image_queue_count ) );
	} elseif ( 'scanning' === get_option( 'ewww_image_optimizer_aux_resume' ) ) {
		$queue_message = __( 'Searching for images to optimize...', 'ewww-image-optimizer' );
	}

	$hide_queue_controls = 'display:none;';
	if ( $queue_message ) {
		$hide_queue_controls = '';
		?>
		<div class='ewww-queue-status'>
			<div id="ewww-optimize-local-images">
				<?php echo esc_html( $queue_message ); ?>
			</div>
		<?php
		// If scan-only mode is active, and one of the scanners is active.
		if (
			! get_option( 'ewww_image_optimizer_pause_queues' ) &&
			get_option( 'ewww_image_optimizer_pause_image_queue' ) &&
			(
				'scanning' === get_option( 'ewww_image_optimizer_bulk_resume' ) ||
				'scanning' === get_option( 'ewww_image_optimizer_aux_resume' )
			)
		) {
			$autopoll = true;
			?>
			<img class='ewww-bulk-spinner' src='<?php echo esc_url( $loading_image ); ?>' />
			<?php
		} elseif ( ! get_option( 'ewww_image_optimizer_pause_queues' ) && ! get_option( 'ewww_image_optimizer_pause_image_queue' ) ) {
			$autopoll = true;
			?>
			<img class='ewww-bulk-spinner' src='<?php echo esc_url( $loading_image ); ?>' />
			<?php
		} else {
			?>
			<img class='ewww-bulk-spinner' src='<?php echo esc_url( $loading_image ); ?>' style='display: none;'/>
			<?php
		}
		?>
		</div>
		<?php
	} else {
		?>
		<div class='ewww-queue-status'>
			<div id='ewww-optimize-local-images'>
				<a class='button-primary' href='#'>
					<?php esc_html_e( 'Optimize Local Images', 'ewww-image-optimizer' ); ?>
				</a>
			</div>
			<img class='ewww-bulk-spinner' src='<?php echo esc_url( $loading_image ); ?>' style='display: none;'/>
		</div>
		<?php
	}
	if ( get_option( 'ewww_image_optimizer_pause_queues' ) ) {
		?>
		<a class='ewww-queue-controls ewww-resume-optimization button-secondary' style='<?php echo esc_attr( $hide_queue_controls ); ?>' href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_resume_queue' ), 'ewww_image_optimizer_clear_queue', 'ewww_nonce' ) ); ?>'>
			<?php esc_html_e( 'Resume Optimization', 'ewww-image-optimizer' ); ?>
		</a>
		<?php
	} elseif ( get_option( 'ewww_image_optimizer_pause_image_queue' ) ) {
		?>
		<a class='ewww-queue-controls ewww-start-optimization button-secondary' style='<?php echo esc_attr( $hide_queue_controls ); ?>' href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_resume_queue' ), 'ewww_image_optimizer_clear_queue', 'ewww_nonce' ) ); ?>'>
			<?php esc_html_e( 'Start optimizing', 'ewww-image-optimizer' ); ?>
		</a>
		<?php
	} else {
		?>
		<a class='ewww-queue-controls ewww-start-optimization button-secondary' style='display: none;' href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_resume_queue' ), 'ewww_image_optimizer_clear_queue', 'ewww_nonce' ) ); ?>'>
			<?php esc_html_e( 'Start optimizing', 'ewww-image-optimizer' ); ?>
		</a>
		<a class='ewww-queue-controls ewww-pause-optimization button-secondary' style='<?php echo esc_attr( $hide_queue_controls ); ?>' href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_pause_queue' ), 'ewww_image_optimizer_clear_queue', 'ewww_nonce' ) ); ?>'>
			<?php esc_html_e( 'Pause Optimization', 'ewww-image-optimizer' ); ?>
		</a>
		<a class='ewww-queue-controls ewww-resume-optimization button-secondary' style='display: none;' href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_resume_queue' ), 'ewww_image_optimizer_clear_queue', 'ewww_nonce' ) ); ?>'>
			<?php esc_html_e( 'Resume Optimization', 'ewww-image-optimizer' ); ?>
		</a>
		<?php
	}
	?>
	<a class='ewww-queue-controls ewww-clear-queue button-secondary' style='<?php echo esc_attr( $hide_queue_controls ); ?>' href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_clear_queue' ), 'ewww_image_optimizer_clear_queue', 'ewww_nonce' ) ); ?>'>
		<?php esc_html_e( 'Clear Queue', 'ewww-image-optimizer' ); ?>
	</a>
	<script>
		var ewww_autopoll = <?php echo ( $autopoll ) ? 'true' : 'false'; ?>;
	</script>
	<?php
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
 * @global int $wp_version
 *
 * @param string $network Indicates which options should be shown in multisite installations.
 */
function ewww_image_optimizer_options( $network = 'singlesite' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewwwio_upgrading;
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
	$debug_info = EWWW\Base::$debug_data;

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
	ewwwio()->temp_debug_end();
	ewwwio()->hs_beacon->admin_notice( $network );
	?>

<div id='ewww-settings-wrap' class='wrap'>
	<h1 style="display:none;">EWWW Image Optimizer</h1>
	<?php
	$speed_score = 0;

	$speed_recommendations = array();

	$free_exec = false;
	if (
		! ewwwio()->local->exec_check() &&
		10 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) &&
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) &&
		! ewww_image_optimizer_easy_active()
	) {
		$free_exec    = true;
		$speed_score += 5;
	}
	if (
		! $free_exec &&
		! ewwwio()->local->get_path( 'jpegtran' ) &&
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
		if ( ! class_exists( 'EWWW\ExactDN' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
			$speed_recommendations[] = __( 'Enable premium compression with an API key or Easy IO.', 'ewww-image-optimizer' );
		}
		$disable_level = true;
	}
	$exactdn_enabled = false;
	if ( get_option( 'easyio_exactdn' ) || ewwwio()->perfect_images_easyio_domain() ) {
		ewww_image_optimizer_webp_rewrite_verify();
		update_option( 'ewww_image_optimizer_exactdn', false );
		if ( get_option( 'easyio_exactdn' ) ) {
			update_option( 'ewww_image_optimizer_lazy_load', false );
		}
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
		( ! class_exists( 'Jetpack' ) || ! method_exists( 'Jetpack', 'is_module_active' ) || ! Jetpack::is_module_active( 'photon' ) ) &&
		class_exists( 'EWWW\ExactDN' ) &&
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
		} elseif ( $exactdn->get_exactdn_domain() ) {
			update_option( 'ewww_image_optimizer_exactdn', false );
			update_site_option( 'ewww_image_optimizer_exactdn', false );
			delete_option( 'ewww_image_optimizer_exactdn_domain' );
			delete_site_option( 'ewww_image_optimizer_exactdn_domain' );
		}
	} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
			$speed_recommendations[] = __( 'Enable Easy IO for automatic resizing.', 'ewww-image-optimizer' ) . ewwwio_get_help_link( 'https://docs.ewww.io/article/44-introduction-to-exactdn', '59bc5ad6042863033a1ce370,5c0042892c7d3a31944e88a4' );
		}
	}
	$exactdn_network_enabled = 0;
	if ( $exactdn_enabled && is_multisite() && is_network_admin() && empty( $exactdn_sub_folder ) ) {
		$exactdn_network_enabled = ewww_image_optimizer_easyio_network_activated();
	}
	$easymode = false;
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_ludicrous_mode' ) ) {
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
	$tools = ewwwio()->local->check_all_tools();
	if ( ! ewww_image_optimizer_easy_active() ) {
		if ( $tools['jpegtran']['enabled'] && ewwwio()->local->exec_check() ) {
			if ( ! empty( $tools['jpegtran']['path'] ) ) {
				$speed_score += 5;
			} else {
				$speed_recommendations[] = __( 'Install jpegtran.', 'ewww-image-optimizer' ) . ewwwio_get_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
			}
		}
		if ( $tools['optipng']['enabled'] && ewwwio()->local->exec_check() ) {
			if ( ! empty( $tools['optipng']['path'] ) ) {
				$speed_score += 5;
			} else {
				$speed_recommendations[] = __( 'Install optipng.', 'ewww-image-optimizer' ) . ewwwio_get_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
			}
		}
		if ( $tools['pngout']['enabled'] && ewwwio()->local->exec_check() ) {
			if ( ! empty( $tools['pngout']['path'] ) ) {
				$speed_score += 1;
			} else {
				$speed_recommendations[] = __( 'Install pngout', 'ewww-image-optimizer' ) . ': <a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_install_pngout' ), 'ewww_image_optimizer_options-options' ) ) . '">' . esc_html__( 'automatically', 'ewww-image-optimizer' ) . '</a> | <a href="https://docs.ewww.io/article/13-installing-pngout" data-beacon-article="5854531bc697912ffd6c1afa">' . esc_html__( 'manually', 'ewww-image-optimizer' ) . '</a>';
			}
		}
		if ( $tools['pngquant']['enabled'] && ewwwio()->local->exec_check() ) {
			if ( ! empty( $tools['pngquant']['path'] ) ) {
				$speed_score += 5;
			} else {
				$speed_recommendations[] = __( 'Install pngquant.', 'ewww-image-optimizer' ) . ewwwio_get_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
			}
		}
		if ( $tools['gifsicle']['enabled'] && ewwwio()->local->exec_check() ) {
			if ( ! empty( $tools['gifsicle']['path'] ) ) {
				$speed_score += 5;
			} else {
				$speed_recommendations[] = __( 'Install gifsicle.', 'ewww-image-optimizer' ) . ewwwio_get_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
			}
		}
		// NOTE: we don't check cwebp here, because we allow WebP for pretty much everyone, with a fallback to the API if all else fails.
		if ( $tools['svgcleaner']['enabled'] && ewww_image_optimizer_svgcleaner_installer_available() ) {
			if ( empty( $tools['svgcleaner']['path'] ) ) {
				$speed_recommendations[] = '<a href="' . admin_url( 'admin.php?action=ewww_image_optimizer_install_svgcleaner' ) . '">' . __( 'Install svgcleaner', 'ewww-image-optimizer' ) . '</a>';
			}
		}
	}
	if ( get_option( 'easyio_lazy_load' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ) {
		$speed_score += 10;
	} else {
		$speed_recommendations[] = __( 'Enable Lazy Loading.', 'ewww-image-optimizer' );
	}
	if ( ! ewww_image_optimizer_easy_active() && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) {
		$speed_recommendations[] = __( 'Enable WebP conversion.', 'ewww-image-optimizer' ) . ewwwio_get_help_link( 'https://docs.ewww.io/article/16-ewww-io-and-webp-images', '5854745ac697912ffd6c1c89' );
	} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) {
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
	if ( defined( 'PHP_VERSION_ID' ) && PHP_VERSION_ID < 70400 ) {
		if ( defined( 'PHP_VERSION_ID' ) && PHP_VERSION_ID < 70200 && $speed_score > 20 ) {
			$speed_score -= 20;
		} elseif ( $speed_score > 10 ) {
			$speed_score -= 10;
		}
		/* translators: %s: The server PHP version. */
		$speed_recommendations[] = sprintf( __( 'Your site is running an older version of PHP (%s), which should be updated.', 'ewww-image-optimizer' ), PHP_VERSION ) . ewwwio_get_help_link( 'https://wordpress.org/support/update-php/', '' );
	}

	// Check that an image library exists for converting resizes. Originals can be done via the API, but resizes are done locally for speed.
	$toolkit_found = false;
	if ( ewwwio()->gd_support() ) {
		$toolkit_found = true;
	}
	if ( ewwwio()->gmagick_support() ) {
		$toolkit_found = true;
	}
	if ( ewwwio()->imagick_support() ) {
		$toolkit_found = true;
	}

	$image_sizes = ewww_image_optimizer_get_image_sizes();
	if ( is_array( $image_sizes ) ) {
		$resize_count = count( $image_sizes );
	}
	$resize_count   = ( ! empty( $resize_count ) && $resize_count > 1 ? $resize_count : 6 );
	$fullsize_count = ewww_image_optimizer_count_all_attachments();

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
	if ( ewww_image_optimizer_background_mode_enabled() ) {
		$bulk_link = admin_url( 'options-general.php?page=ewww-image-optimizer-options&bulk_optimize=1' );
	} else {
		$bulk_link = admin_url( 'upload.php?page=ewww-image-optimizer-bulk' );
	}
	?>
	<div id='ewww-widgets' class='metabox-holder'>
		<div class='meta-box-sortables'>
			<div id='ewww-status' class='postbox'>
				<div class='ewww-hndle' id="ewwwio-banner">
					<img height="95" width="167" src="<?php echo esc_url( plugins_url( '/images/ewwwio-logo.png', __FILE__ ) ); ?>">
	<?php if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_hide_newsletter_signup' ) && ! apply_filters( 'ewwwio_whitelabel', false ) ) : ?>
					<div id="ewww-newsletter-banner">
						<div class='ewwwio-flex-space-between'>
							<p>
								<?php esc_html_e( 'Get performance tips, exclusive discounts, and the latest news when you signup for our newsletter!', 'ewww-image-optimizer' ); ?>
							</p>
						</div>
						<p id='ewww-news-button'>
							<a href="https://ewww.io/connect/" class="button-secondary"><?php esc_html_e( 'Subscribe now!', 'ewww-image-optimizer' ); ?></a>
							&emsp;<a id="ewww-news-dismiss-link" href="#"><?php esc_html_e( 'No Thanks', 'ewww-image-optimizer' ); ?></a>
						</p>
					</div>
	<?php endif; ?>
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
								<p>&nbsp;</p>
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
									'<a href="' . esc_url( $bulk_link ) . '">' . esc_html__( 'Bulk Optimizer', 'ewww-image-optimizer' ) . '</a>'
									)
								);
								ewwwio_help_link( 'https://docs.ewww.io/article/4-getting-started', '5853713bc697912ffd6c0b98' );
								if ( ! apply_filters( 'ewwwio_whitelabel', false ) ) {
									echo ' ' . ( ! class_exists( 'Amazon_S3_And_CloudFront' ) ?
										'<br>' .
										sprintf(
											/* translators: %s: S3 Image Optimizer (link) */
											esc_html__( 'Optimize unlimited Amazon S3 buckets with our %s.', 'ewww-image-optimizer' ),
											'<a href="https://wordpress.org/plugins/s3-image-optimizer/">' . esc_html__( 'S3 Image Optimizer', 'ewww-image-optimizer' ) . '</a>'
										) : '' );
								}
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
									- <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_retest_background_optimization' ), 'ewww_image_optimizer_options-options' ) ); ?>">
										<?php esc_html_e( 'Re-test', 'ewww-image-optimizer' ); ?>
									</a>
								</span>
								<?php ewwwio_help_link( 'https://docs.ewww.io/article/42-background-and-parallel-optimization-disabled', '598cb8be2c7d3a73488be237' ); ?>
	<?php else : ?>
								<span><?php esc_html_e( 'Enabled', 'ewww-image-optimizer' ); ?>
									- <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_retest_background_optimization' ), 'ewww_image_optimizer_options-options' ) ); ?>">
										<?php esc_html_e( 'Re-test', 'ewww-image-optimizer' ); ?>
									</a>
								</span>
	<?php endif; ?>
							</p>
						</div><!-- end .ewww-status-detail -->
					</div><!-- end .ewww-blocks --><!-- end .ewww-row -->
	<?php if ( 'singlesite' === $network || 'network-singlesite' === $network ) : ?>
					<div class="ewww-blocks ewwwio-flex-space-between ewww-status-actions">
						<div class="ewww-action-container ewwwio-flex-space-between ewww-bulk-actions">
							<?php ewww_image_optimizer_bulk_async_show_status(); ?>
						</div>
						<div class="ewww-action-container">
							<a id="ewww-show-table" class='button-secondary' href='#'>
								<?php esc_html_e( 'View Optimized Images', 'ewww-image-optimizer' ); ?>
							</a>
							<a id="ewww-hide-table" class="dashicons dashicons-arrow-up" style="display: none;" href="#">
								&nbsp;
							</a>
						</div>
					</div><!-- end .ewww-blocks --><!-- end .ewwwio-flex-space-between -->
					<div id="ewww-bulk-results" class="ewwwio-flex-space-between ewww-status-actions" style="display: none;">
						<div id="ewww-bulk-queue-images">
		<?php if ( ! ewww_image_optimizer_background_mode_enabled() ) : ?>
							<div id="ewww-bulk-warning" class="ewww-bulk-info ewwwio-notice notice-warning">
								<a href="#ewww-notices"><?php esc_html_e( 'Background Optimization is disabled, but you may use the legacy Bulk Optimizer.', 'ewww-image-optimizer' ); ?></a>
								<?php ewwwio_help_link( 'https://docs.ewww.io/article/42-background-and-parallel-optimization-disabled', '598cb8be2c7d3a73488be237' ); ?>
							</div>
		<?php elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_easy_active() && ! ewwwio()->local->exec_check() ) : ?>
							<div id="ewww-bulk-warning" class="ewww-bulk-info ewwwio-notice notice-info">
								<?php esc_html_e( 'Easy IO is already optimizing your images! If you need to save storage space, you may enter your API key below to compress the local images on your site.', 'ewww-image-optimizer' ); ?>
								<?php ewwwio_help_link( 'https://docs.ewww.io/article/46-exactdn-with-the-ewww-io-api-cloud', '59c44349042863033a1d06d3' ); ?>
							</div>
		<?php elseif ( $fullsize_count < 1 ) : ?>
								<div id="ewww-bulk-warning" class="ewww-bulk-info ewwwio-notice notice-info">
									<?php esc_html_e( 'You do not appear to have uploaded any images yet.', 'ewww-image-optimizer' ); ?>
								</div>
		<?php else : ?>
							<?php if ( ewww_image_optimizer_easy_active() ) : ?>
								<div id="ewww-bulk-warning" class="ewww-bulk-info ewwwio-notice notice-warning">
									<?php esc_html_e( 'Easy IO is already optimizing your images! Optimization of local images is not necessary unless you wish to save storage space.', 'ewww-image-optimizer' ); ?>
								</div>
							<?php endif; ?>
							<p class="ewww-media-info ewww-bulk-info">
								<?php /* translators: 1: number of images 2: number of registered image sizes */ ?>
								<?php echo esc_html( sprintf( _n( '%1$s uploaded item in the Media Library will be optimized with up to %2$d image files per upload.', '%1$s uploaded items in the Media Library have been selected with up to %2$d image files per upload.', $fullsize_count, 'ewww-image-optimizer' ), number_format_i18n( $fullsize_count ), $resize_count ) ); ?>
								<?php ewww_image_optimizer_bulk_resize_warning_message(); ?>
							</p>
							<a id="ewww-bulk-start-optimizing" class='button-primary' href='#'>
								<?php esc_html_e( 'Start optimizing', 'ewww-image-optimizer' ); ?>
							</a>
						</div>
						<form id="ewww-bulk-controls" class="ewww-bulk-form">
							<p><label for="ewww-force" style="font-weight: bold"><?php esc_html_e( 'Force re-optimize', 'ewww-image-optimizer' ); ?></label><?php ewwwio_help_link( 'https://docs.ewww.io/article/65-force-re-optimization', '5bb640a7042863158cc711cd' ); ?>
								&emsp;<input type="checkbox" id="ewww-force" name="ewww-force"<?php echo ( get_transient( 'ewww_image_optimizer_force_reopt' ) || ! empty( ewwwio()->force ) ) ? ' checked' : ''; ?>>
								&nbsp;<?php esc_html_e( 'Previously optimized images will be skipped by default, check this box before scanning to override.', 'ewww-image-optimizer' ); ?>
							</p>
							<?php ewww_image_optimizer_bulk_variant_option(); ?>
							<?php ewww_image_optimizer_bulk_scan_only(); ?>
							<?php ewww_image_optimizer_bulk_webp_only(); ?>
							<?php $delay = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ); ?>
							<p>
								<label for="ewww-delay" style="font-weight: bold"><?php esc_html_e( 'Pause between images', 'ewww-image-optimizer' ); ?></label>&emsp;<input type="text" id="ewww-delay" name="ewww-delay" value="<?php echo (int) $delay; ?>"> <?php esc_html_e( 'in seconds', 'ewww-image-optimizer' ); ?>
							</p>
							<div id="ewww-delay-slider"></div>
						</form>
						<div id="ewww-bulk-queue-confirm" style="display: none;">
							<div class="ewww-bulk-confirm-info">
								<?php esc_html_e( 'Optimization will alter your original images and cannot be undone. Please be sure you have a backup of your images before proceeding.', 'ewww-image-optimizer' ); ?>
							</div>
							<a id="ewww-bulk-confirm-optimizing" class='button-primary' href='#'>
								<?php esc_html_e( "Let's go!", 'ewww-image-optimizer' ); ?>
							</a>
							<div id="ewww-bulk-async-notice" style="display: none;">
								<?php esc_html_e( 'Optimization will continue in the background. You may close this page without interrupting optimization.', 'ewww-image-optimizer' ); ?>
							</div>
		<?php endif; ?>
						</div>
						<div id="ewww-bulk-table-wrapper">
							<?php ewwwio_table_nav_controls( 'top' ); ?>
							<div id="ewww-bulk-table" class="ewww-aux-table"></div>
							<?php ewwwio_table_nav_controls( 'bottom' ); ?>
						</div>
					</div><!-- end #ewww-bulk-results -->
	<?php endif; ?>
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
		$frontend_functions[] = __( 'Picture WebP Rewriting', 'ewww-image-optimizer' );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		$frontend_functions[] = __( 'Easy IO', 'ewww-image-optimizer' );
	}
	$loading_image_url    = plugins_url( '/images/spinner.gif', __FILE__ );
	$easyio_site_url      = ewwwio()->content_url();
	$exactdn_los_che      = ewww_image_optimizer_get_option( 'exactdn_lossy' );
	$exactdn_los_id       = $exactdn_enabled ? 'exactdn_lossy_disabled' : 'exactdn_lossy';
	$exactdn_los_dis      = false;
	$exactdn_hidpi_che    = ewww_image_optimizer_get_option( 'exactdn_hidpi' );
	$eio_exclude_paths    = ewww_image_optimizer_get_option( 'exactdn_exclude' ) ? implode( "\n", (array) ewww_image_optimizer_get_option( 'exactdn_exclude' ) ) : '';
	$lqip_che             = ( ( is_multisite() && is_network_admin() ) || is_object( $exactdn ) ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_use_lqip' );
	$lqip_id              = ! is_network_admin() && ! $exactdn_enabled ? 'ewww_image_optimizer_use_lqip_disabled' : 'ewww_image_optimizer_use_lqip';
	$lqip_dis             = ! is_network_admin() && ! $exactdn_enabled;
	$dcip_che             = ( ( is_multisite() && is_network_admin() ) || is_object( $exactdn ) ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_use_dcip' );
	$dcip_id              = ! is_network_admin() && ! $exactdn_enabled ? 'ewww_image_optimizer_use_dcip_disabled' : 'ewww_image_optimizer_use_dcip';
	$dcip_dis             = $lqip_dis;
	$ll_exclude_paths     = ewww_image_optimizer_get_option( 'ewww_image_optimizer_ll_exclude' ) ? implode( "\n", (array) ewww_image_optimizer_get_option( 'ewww_image_optimizer_ll_exclude' ) ) : '';
	$current_jpeg_quality = apply_filters( 'jpeg_quality', 82, 'image_resize' );
	$webp_php_rewriting   = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_picture_webp' );
	$webp_exclude_paths   = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_rewrite_exclude' ) ? implode( "\n", (array) ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_rewrite_exclude' ) ) : '';
	$webp_paths           = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' ) ? implode( "\n", (array) ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' ) ) : '';
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
			''
		),
		'ewww_image_optimizer_options-options'
	);
	$enable_easy_url  = wp_nonce_url(
		add_query_arg(
			array(
				'page'         => 'ewww-image-optimizer-options',
				'enable-local' => 0,
			),
			''
		),
		'ewww_image_optimizer_options-options'
	);
	$rescue_mode_url  = wp_nonce_url(
		add_query_arg(
			array(
				'page'        => 'ewww-image-optimizer-options',
				'rescue_mode' => 1,
			),
			''
		),
		'ewww_image_optimizer_options-options'
	);

	$cloudways_host = false;
	if ( isset( $_SERVER['cw_allowed_ip'] ) ) {
		$cloudways_host = true;
	}
	// Make sure .htaccess rules are terminated when ExactDN is enabled or if Cloudflare is detected.
	$cf_host = ewwwio_is_cf_host();
	if ( ewww_image_optimizer_easy_active() || $cf_host ) {
		ewww_image_optimizer_webp_rewrite_verify();
	}
	$webp_available  = ewww_image_optimizer_webp_available();
	$test_webp_image = plugins_url( '/images/test.png.webp', __FILE__ );
	$test_png_image  = plugins_url( '/images/test.png', __FILE__ );
	?>


	<!-- 'network-multisite-over' and 'network-singlesite' get simpler settings, 'network-singlesite-over' masquerades as 'singlesite' -->
	<?php if ( ! $easymode && ( 'singlesite' === $network || 'network-multisite' === $network ) ) : ?>
	<ul class='ewww-tab-nav'>
		<li class='ewww-tab ewww-general-nav'><span><?php esc_html_e( 'Essential', 'ewww-image-optimizer' ); ?></span></li>
		<li class='ewww-tab ewww-local-nav'><span><?php esc_html_e( 'Local', 'ewww-image-optimizer' ); ?></span></li>
		<li class='ewww-tab ewww-advanced-nav'><span><?php esc_html_e( 'Advanced', 'ewww-image-optimizer' ); ?></span></li>
		<li class='ewww-tab ewww-resize-nav'><span><?php esc_html_e( 'Resize', 'ewww-image-optimizer' ); ?></span></li>
		<li class='ewww-tab ewww-conversion-nav'><span><?php esc_html_e( 'Convert', 'ewww-image-optimizer' ); ?></span></li>
		<?php if ( ! apply_filters( 'ewwwio_whitelabel', false ) ) : ?>
		<li class='ewww-tab ewww-overrides-nav'><span><a href='https://docs.ewww.io/article/40-override-options' target='_blank'><span class='ewww-tab-hidden'><?php esc_html_e( 'Overrides', 'ewww-image-optimizer' ); ?></a></span></li>
		<li class='ewww-tab ewww-support-nav'><span><?php esc_html_e( 'Support', 'ewww-image-optimizer' ); ?></span></li>
		<li class='ewww-tab ewww-contribute-nav'><span><?php esc_html_e( 'Contribute', 'ewww-image-optimizer' ); ?></span></li>
		<?php endif; ?>
	</ul>
	<?php elseif ( $easymode && 'network-singlesite' !== $network && ! apply_filters( 'ewwwio_whitelabel', false ) ) : ?>
	<ul class='ewww-tab-nav'>
		<li class='ewww-tab ewww-general-nav'><span><?php esc_html_e( 'Essential', 'ewww-image-optimizer' ); ?></span></li>
		<li class='ewww-tab ewww-support-nav'><span><?php esc_html_e( 'Support', 'ewww-image-optimizer' ); ?></span></li>
		<li class='ewww-tab ewww-contribute-nav'><span><?php esc_html_e( 'Contribute', 'ewww-image-optimizer' ); ?></span></li>
	</ul>
	<?php endif; ?>
	<?php if ( ! ewww_image_optimizer_easy_site_registered( $easyio_site_url ) ) : ?>
		<script>var exactdn_registered = false;</script>
	<?php else : ?>
		<script>var exactdn_registered = true;</script>
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
			<noscript><h2><?php esc_html_e( 'Essential', 'ewww-image-optimizer' ); ?></h2></noscript>
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
									/* translators: 1: link to https://ewww.io/plans/ 2: discount code (yes, you as a translator may use it) */
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
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) || ewww_image_optimizer_easy_active() ) {
		$premium_hide = ' style="display:none"';
	}
	?>
				<tr>
					<th scope='row'>
	<?php if ( $easymode ) : ?>
						<p>
							<a class='button-primary ewww-orange-button' href='<?php echo esc_url( $enable_local_url ); ?>'>
								<?php /* translators: Ludicrous is a reference to Ludicrous Speed in the movie Spaceballs. May be translated literally, or with a suitable replacement for your locale. */ ?>
								<?php esc_html_e( 'Ludicrous Mode', 'ewww-image-optimizer' ); ?>
							</a>
						</p>
					</th>
					<td>
						<?php /* translators: plaid is what happens when they go to Ludicrous Speed in the movie Spaceballs. May be translated literally, ignored, or with a suitable replacement for your locale. */ ?>
						<p><?php esc_html_e( 'Show every option possible, even plaid.', 'ewww-image-optimizer' ); ?></p>
	<?php else : ?>
						<p>
							<a class='button-primary ewww-orange-button' href='<?php echo esc_url( $enable_easy_url ); ?>'>
								<?php esc_html_e( 'Easy Mode', 'ewww-image-optimizer' ); ?>
							</a>
						</p>
					</th>
					<td>
						<p><?php esc_html_e( 'Go back to the basics.', 'ewww-image-optimizer' ); ?></p>
	<?php endif; ?>
					</td>
				</tr>
	<?php if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) : ?>
				<tr id='ewww_image_optimizer_cloud_key_container'>
					<th scope='row'>
						<label for='ewww_image_optimizer_cloud_notkey'><?php esc_html_e( 'Compress API Key', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2,5ad0c8e7042863075092650b,5a9efec62c7d3a7549516550' ); ?>
					</th>
					<td>
						<input type='text' id='ewww_image_optimizer_cloud_notkey' name='ewww_image_optimizer_cloud_notkey' readonly='readonly' value='****************<?php echo esc_attr( substr( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ), 28 ) ); ?>' size='32' />
						<a class='button-secondary' href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_remove_cloud_key' ), 'ewww_image_optimizer_options-options' ) ); ?>'>
							<?php esc_html_e( 'Remove API key', 'ewww-image-optimizer' ); ?>
						</a>
						<p>
		<?php if ( false !== strpos( $verify_cloud, 'great' ) ) : ?>
							<span style="color: #3eadc9; font-weight: bolder"><?php esc_html_e( 'Verified,', 'ewww-image-optimizer' ); ?> </span><?php echo wp_kses_post( ewww_image_optimizer_cloud_quota() ); ?>
		<?php elseif ( apply_filters( 'ewwwio_whitelabel', false ) && false !== strpos( $verify_cloud, 'exceeded' ) ) : ?>
							<span style="color: orange; font-weight: bolder"><?php esc_html_e( 'Out of credits', 'ewww-image-optimizer' ); ?></span>
		<?php elseif ( false !== strpos( $verify_cloud, 'exceeded subkey' ) ) : ?>
							<span style="color: orange; font-weight: bolder"><?php esc_html_e( 'Out of credits', 'ewww-image-optimizer' ); ?></span>					
		<?php elseif ( false !== strpos( $verify_cloud, 'exceeded quota' ) ) : ?>
							<span style="color: orange; font-weight: bolder"><a href="https://docs.ewww.io/article/101-soft-quotas-on-unlimited-plans" data-beacon-article="608ddf128996210f18bd95d3" target="_blank"><?php esc_html_e( 'Soft quota reached, contact us for more', 'ewww-image-optimizer' ); ?></a></span>
		<?php elseif ( false !== strpos( $verify_cloud, 'exceeded' ) ) : ?>
							<span style="color: orange; font-weight: bolder"><?php esc_html_e( 'Out of credits', 'ewww-image-optimizer' ); ?></span> - <a href="https://ewww.io/buy-credits/" target="_blank"><?php esc_html_e( 'Purchase more', 'ewww-image-optimizer' ); ?></a>
		<?php else : ?>
							<span style="color: red; font-weight: bolder"><?php esc_html_e( 'Not Verified', 'ewww-image-optimizer' ); ?></span>
		<?php endif; ?>
		<?php if ( false !== strpos( $verify_cloud, 'great' ) ) : ?>
			<?php if ( ! apply_filters( 'ewwwio_whitelabel', false ) ) : ?>
							<a target="_blank" href="https://ewww.io/manage-keys/"><?php esc_html_e( 'View Usage', 'ewww-image-optimizer' ); ?></a>
			<?php endif; ?>
		<?php endif; ?>
						</p>
					</td>
				</tr>
	<?php else : ?>
		<?php if ( ! $exactdn_enabled && ! apply_filters( 'ewwwio_whitelabel', false ) ) : ?>
				<tr<?php echo wp_kses_post( $premium_hide ); ?>>
					<th scope='row'>
						<p><a type='submit' class='button-primary ewww-upgrade' href='https://ewww.io/trial/'><?php esc_attr_e( 'Start Premium Trial', 'ewww-image-optimizer' ); ?></a></p>
					</th>
					<td>
						<p><?php esc_html_e( 'Get 5x more compression with a premium plan.', 'ewww-image-optimizer' ); ?></p>
					</td>
				</tr>
		<?php endif; ?>
				<tr id='ewww_image_optimizer_cloud_key_container' class='ewwwio-premium-setup'>
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
		<?php if ( ! apply_filters( 'ewwwio_whitelabel', false ) ) : ?>
							<a href='https://ewww.io/manage-keys/' target='_blank'><?php esc_html_e( 'Manage your API keys', 'ewww-image-optimizer' ); ?></a>
		<?php endif; ?>

						</p>
					</td>
				</tr>
	<?php endif; ?>
	<?php if ( ewwwio()->perfect_images_easyio_domain() ) : ?>
				<tr id="ewww_image_optimizer_exactdn_container" class="ewwwio-premium-setup">
					<th scope='row'>
						<span id='ewwwio-exactdn-anchor'></span>
						<?php esc_html_e( 'Easy IO', 'ewww-image-optimizer' ); ?>
					</th>
					<td>
						<p class='ewwwio-easy-description'>
							<span style="color: #3eadc9"><?php esc_html_e( 'Easy IO is already active in the Perfect Images plugin.', 'ewww-image-optimizer' ); ?></span>
						</p>
					</td>
				</tr>
	<?php elseif ( ! get_option( 'easyio_exactdn' ) ) : ?>
		<?php ob_start(); ?>
				<tr id="ewww_image_optimizer_exactdn_container" class="ewwwio-premium-setup">
					<th scope='row'>
						<span id='ewwwio-exactdn-anchor'></span>
						<?php esc_html_e( 'Easy IO', 'ewww-image-optimizer' ); ?>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/44-introduction-to-exactdn', '59bc5ad6042863033a1ce370,5c0042892c7d3a31944e88a4' ); ?>
					</th>
					<td>
						<div id='ewwwio-easy-activation-result'></div>
						<p class='ewwwio-easy-description'>
							<?php
							if ( ! apply_filters( 'ewwwio_whitelabel', false ) ) {
								printf(
									/* translators: %s: the string 'and more' with a link to the docs */
									esc_html__( 'An image-optimizing CDN with automatic compression, scaling, WebP conversion %s.', 'ewww-image-optimizer' ),
									'<a href="https://docs.ewww.io/article/44-introduction-to-exactdn" target="_blank" data-beacon-article="59bc5ad6042863033a1ce370">' . esc_html__( 'and more', 'ewww-image-optimizer' ) . '</a>'
								);
							}
							?>
						</p>
		<?php if ( class_exists( 'Jetpack' ) && method_exists( 'Jetpack', 'is_module_active' ) && Jetpack::is_module_active( 'photon' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) : ?>
						<p style='color: red'><?php esc_html_e( 'Inactive, please disable the Site Accelerator option in the Jetpack settings.', 'ewww-image-optimizer' ); ?></p>
		<?php elseif ( false !== strpos( $easyio_site_url, 'localhost' ) ) : ?>
						<p class="description" style="font-weight: bolder"><?php esc_html_e( 'Easy IO cannot be activated on localhost installs.', 'ewww-image-optimizer' ); ?></p>
		<?php elseif ( 'network-multisite' === $network && empty( $exactdn_sub_folder ) ) : ?>
			<?php if ( 1 > $exactdn_network_enabled ) : ?>
						<div class="ewwwio-easy-setup-instructions">
				<?php if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) : ?>
							<?php esc_html_e( 'Enter your API key above to enable automatic Easy IO site registration.', 'ewww-image-optimizer' ); ?><br>
				<?php endif; ?>
				<?php if ( -1 === $exactdn_network_enabled ) : ?>
							<span style="color: orange; font-weight: bolder"><?php esc_html_e( 'Partially Active', 'ewww-image-optimizer' ); ?></span> - <a href="https://ewww.io/manage-sites/"><?php esc_html_e( 'Manage Sites', 'ewww-image-optimizer' ); ?></a><br>
							<span><?php esc_html_e( 'Easy IO is not active on some sites. You may activate individual sites via the plugin settings in each site dashboard, or activate all remaining sites below.', 'ewww-image-optimizer' ); ?></span><br>
				<?php else : ?>
							<a href="https://ewww.io/manage-sites/" target="_blank"><?php esc_html_e( 'Add your Site URLs to your account', 'ewww-image-optimizer' ); ?></a>
				<?php endif; ?>
						</div>
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
						<a id='ewwwio-easy-deactivate' class='button-secondary' href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_network_remove_easyio' ), 'ewww_image_optimizer_options-options' ) ); ?>'>
							<?php esc_html_e( 'De-activate All Sites', 'ewww-image-optimizer' ); ?>
						</a>
			<?php endif; ?>
		<?php elseif ( ! $exactdn_enabled ) : ?>
						<div class="ewwwio-easy-setup-instructions">
			<?php if ( ! ewww_image_optimizer_easy_site_registered( $easyio_site_url ) && ! apply_filters( 'ewwwio_whitelabel', false ) ) : ?>
				<?php if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) : ?>
							<a class='easyio-cloud-key-ui' href="<?php echo esc_url( add_query_arg( 'site_url', trim( $easyio_site_url ), 'https://ewww.io/manage-sites/' ) ); ?>" target="_blank"><?php esc_html_e( 'Verify that this site is added to your account:', 'ewww-image-optimizer' ); ?></a>
				<?php else : ?>
							<a class='easyio-cloud-key-ui' style='display:none;' href="<?php echo esc_url( add_query_arg( 'site_url', trim( $easyio_site_url ), 'https://ewww.io/manage-sites/' ) ); ?>" target="_blank"><?php esc_html_e( 'Verify that this site is added to your account:', 'ewww-image-optimizer' ); ?></a>
							<a class='easyio-manual-ui' href="<?php echo esc_url( add_query_arg( 'site_url', trim( $easyio_site_url ), 'https://ewww.io/manage-sites/' ) ); ?>" target="_blank"><?php esc_html_e( 'Add your Site URL to your account:', 'ewww-image-optimizer' ); ?></a>
				<?php endif; ?>
							<input type='text' id='exactdn_site_url' name='exactdn_site_url' value='<?php echo esc_url( trim( $easyio_site_url ) ); ?>' readonly />
							<span id='exactdn-site-url-copy'><?php esc_html_e( 'Click to Copy', 'ewww-image-optimizer' ); ?></span>
							<span id='exactdn-site-url-copied'><?php esc_html_e( 'Copied', 'ewww-image-optimizer' ); ?></span><br>
			<?php endif; ?>
							<a id='ewwwio-easy-activate' href='#' class='button-secondary'><?php esc_html_e( 'Activate', 'ewww-image-optimizer' ); ?></a>
							<span id='ewwwio-easy-activation-processing'><img src='<?php echo esc_url( $loading_image_url ); ?>' alt='loading'/></span>
			<?php if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_easy_site_registered( $easyio_site_url ) ) : ?>
							<a id='ewwwio-easy-deregister' href='#'><?php esc_html_e( 'De-register Site', 'ewww-image-optimizer' ); ?></a>
			<?php endif; ?>
						</div>
		<?php elseif ( class_exists( 'EWWW\ExactDN' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) : ?>
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
							<a id='ewwwio-easy-deactivate' class='button-secondary' href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_remove_easyio' ), 'ewww_image_optimizer_options-options' ) ); ?>'>
								<?php esc_html_e( 'De-activate', 'ewww-image-optimizer' ); ?>
							</a>
						</p>
		<?php endif; ?>
					</td>
				</tr>
		<?php $exactdn_settings_row = ob_get_contents(); ?>
		<?php ob_end_flush(); ?>
	<?php endif; ?>
				<tr class='ewwwio-exactdn-options exactdn-easy-options' <?php echo $exactdn_enabled ? '' : 'style="display:none;"'; ?>>
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
						<input type='checkbox' name='exactdn_lossy' value='true' id='<?php echo esc_attr( $exactdn_los_id ); ?>' <?php checked( $exactdn_los_che ); ?> />
						<label for='exactdn_lossy'><strong><?php esc_html_e( 'Premium Compression', 'ewww-image-optimizer' ); ?></strong></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/47-getting-more-from-exactdn', '59de6631042863379ddc953c' ); ?>
						<p class='description'>
							<?php esc_html_e( 'Enable high quality compression and WebP/AVIF conversion for all images on Easy IO. Disable to use Pixel Perfect mode instead.', 'ewww-image-optimizer' ); ?><br>
	<?php if ( ! apply_filters( 'ewwwio_whitelabel', false ) ) : ?>
							<a href='https://ewww.io/manage-sites/' target='_blank'>
								<?php esc_html_e( 'Manage WebP/AVIF in the site settings at ewww.io.', 'ewww-image-optimizer' ); ?>
							</a>
	<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr class='ewwwio-exactdn-options' <?php echo $exactdn_enabled && ! $easymode ? '' : 'style="display:none;"'; ?>>
					<td>&nbsp;</td>
					<td>
						<input type='checkbox' name='exactdn_hidpi' value='true' id='exactdn_hidpi' <?php checked( $exactdn_hidpi_che ); ?> />
						<label for='exactdn_hidpi'><strong><?php esc_html_e( 'High-DPI', 'ewww-image-optimizer' ); ?></strong></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/47-getting-more-from-exactdn', '59de6631042863379ddc953c' ); ?>
						<p class='description'>
							<?php esc_html_e( 'Enable higher resolution images for devices with High-DPI screens. This will increase image file sizes and load times.', 'ewww-image-optimizer' ); ?><br>
						</p>
					</td>
				</tr>
	<?php if ( ! $exactdn_enabled ) : ?>
				<input type='hidden' id='ewww_image_optimizer_use_lqip' name='ewww_image_optimizer_use_lqip' <?php echo ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_use_lqip' ) ? "value='1'" : "value='0'" ); ?> />
				<input type='hidden' id='ewww_image_optimizer_use_dcip' name='ewww_image_optimizer_use_dcip' <?php echo ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_use_dcip' ) ? "value='1'" : "value='0'" ); ?> />
	<?php endif; ?>
				<tr class="ewwwio-exactdn-options exactdn-easy-options" <?php echo $exactdn_enabled ? '' : 'style="display:none;"'; ?>>
					<td>&nbsp;</td>
					<td>
						<label for='exactdn_exclude'><strong><?php esc_html_e( 'Exclusions', 'ewww-image-optimizer' ); ?></strong></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/68-exactdn-exclude', '5c0042892c7d3a31944e88a4' ); ?><br>
						<textarea id='exactdn_exclude' name='exactdn_exclude' rows='3' cols='60'><?php echo esc_html( $eio_exclude_paths ); ?></textarea>
						<p class='description'>
							<?php esc_html_e( 'One exclusion per line, no wildcards (*) needed. Any pattern or path provided will not be routed through Easy IO.', 'ewww-image-optimizer' ); ?>
							<?php esc_html_e( 'Exclude entire pages with page:/xyz/ syntax.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
	<?php if ( ! function_exists( 'swis' ) ) : ?>
				<tr id="swis_promo_container" class="ewwwio-premium-setup">
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
								'<a href="' . esc_url( $bulk_link ) . '">' . esc_html__( 'Bulk Optimizer', 'ewww-image-optimizer' ) . '</a>'
							);
							?>
						</p>
		<?php if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherwidth' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherheight' ) ) : ?>
						<p>
							<?php
							printf(
								/* translators: 1: width in pixels 2: height in pixels */
								esc_html__( '*Legacy resize options are in effect: %1$d x %2$d. These will be used for bulk operations and for images uploaded outside the post/page editor.', 'ewww-image-optimizer' ),
								(int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherwidth' ),
								(int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherheight' )
							);
							?>
							<br>
							<?php esc_html_e( 'The legacy settings will be removed if the above width/height settings are modified.', 'ewww-image-optimizer' ); ?>
						</p>
		<?php endif; ?>
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
	<?php if ( function_exists( 'easyio_get_option' ) && easyio_get_option( 'easyio_lazy_load' ) ) : ?>
						<p class='description'><?php esc_html_e( 'Setting managed in Easy Image Optimizer.', 'ewww-image-optimizer' ); ?></p>
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
							<?php esc_html_e( 'Can automatically scale images based on display size.', 'ewww-image-optimizer' ); ?>
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
							<?php esc_html_e( 'When used with Easy IO, all images become responsive.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
				<tr id='ewww_image_optimizer_ll_abovethefold_container' <?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ? '' : ' style="display:none"'; ?>>
					<td>&nbsp;</td>
					<td>
						<label for='ewww_image_optimizer_ll_abovethefold'><strong><?php esc_html_e( 'Above the Fold', 'ewww-image-optimizer' ); ?></strong></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/74-lazy-load', '5c6c36ed042863543ccd2d9b' ); ?><br>
						<input type='number' step='1' min='0' class='small-text' id='ewww_image_optimizer_ll_abovethefold' name='ewww_image_optimizer_ll_abovethefold' value='<?php	echo defined( 'EIO_LAZY_FOLD' ) ? (int) constant( 'EIO_LAZY_FOLD' ) : (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_ll_abovethefold' ); ?>' <?php disabled( defined( 'EIO_LAZY_FOLD' ) ); ?> />
						<?php esc_html_e( 'Skip this many images from lazy loading so that above the fold images load more quickly.', 'ewww-image-optimizer' ); ?>
						<p class='description'>
							<?php esc_html_e( 'This will exclude images from auto-scaling, which may decrease performance if those images are not properly sized.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
				<tr id='ewww_image_optimizer_lqip_container' <?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ? '' : ' style="display:none"'; ?>>
					<td>&nbsp;</td>
					<td>
						<input type='checkbox' name='ewww_image_optimizer_use_lqip' value='true' id='<?php echo esc_attr( $lqip_id ); ?>' <?php disabled( $lqip_dis ); ?> <?php checked( $lqip_che ); ?> />
						<label for='<?php echo esc_attr( $lqip_id ); ?>'><strong>LQIP</strong></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/75-lazy-load-placeholders', '5c9a7a302c7d3a1544615e47' ); ?>
						<p class='description'>
							<?php esc_html_e( 'Use low-quality versions of your images as placeholders via Easy IO. Can improve user experience, but may be slower than blank placeholders.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
				<tr id='ewww_image_optimizer_dcip_container' <?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ? '' : ' style="display:none"'; ?>>
					<td>&nbsp;</td>
					<td>
						<input type='checkbox' name='ewww_image_optimizer_use_dcip' value='true' id='<?php echo esc_attr( $dcip_id ); ?>' <?php disabled( $dcip_dis ); ?> <?php checked( $dcip_che ); ?> />
						<label for='<?php echo esc_attr( $dcip_id ); ?>'><strong>DCIP</strong></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/75-lazy-load-placeholders', '5c9a7a302c7d3a1544615e47' ); ?>
						<p class='description'>
							<?php esc_html_e( 'Use dominant-color versions of your images as placeholders via Easy IO. Can improve user experience, but may be slower than blank placeholders.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
				<tr id='ewww_image_optimizer_ll_all_things_container' <?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ? '' : ' style="display:none"'; ?>>
					<td>&nbsp;</td>
					<td>
						<label for='ewww_image_optimizer_ll_all_things'><strong><?php esc_html_e( 'External Background Images', 'ewww-image-optimizer' ); ?></strong></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/74-lazy-load', '5c6c36ed042863543ccd2d9b' ); ?><br>
						<textarea id='ewww_image_optimizer_ll_all_things' name='ewww_image_optimizer_ll_all_things' rows='3' cols='60'><?php echo esc_html( ewww_image_optimizer_get_option( 'ewww_image_optimizer_ll_all_things' ) ); ?></textarea>
						<p class='description'>
							<?php esc_html_e( 'Specify class/id values of elements with CSS background images (comma-separated).', 'ewww-image-optimizer' ); ?>
							<?php esc_html_e( 'Can match any text within the target element, like elementor-widget-container or et_pb_column.', 'ewww-image-optimizer' ); ?>
							<br>*<?php esc_html_e( 'Background images directly attached via inline style attributes will be lazy loaded by default.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
				<tr id='ewww_image_optimizer_ll_exclude_container' <?php echo ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ? '' : ' style="display:none"'; ?>>
					<td>&nbsp;</td>
					<td>
						<label for='ewww_image_optimizer_ll_exclude'><strong><?php esc_html_e( 'Exclusions', 'ewww-image-optimizer' ); ?></strong></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/74-lazy-load', '5c6c36ed042863543ccd2d9b' ); ?><br>
						<textarea id='ewww_image_optimizer_ll_exclude' name='ewww_image_optimizer_ll_exclude' rows='3' cols='60'><?php echo esc_html( $ll_exclude_paths ); ?></textarea>
						<p class='description'>
							<?php esc_html_e( 'One exclusion per line, no wildcards (*) needed. Use any string that matches the desired element(s) or exclude entire element types like "div", "span", etc. The class "skip-lazy" and attribute "data-skip-lazy" are excluded by default.', 'ewww-image-optimizer' ); ?>
							<?php esc_html_e( 'Exclude entire pages with page:/xyz/ syntax.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
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
							if ( ! apply_filters( 'ewwwio_whitelabel', false ) ) {
								printf(
									/* translators: 1: Bulk Optimizer 2: Easy IO */
									esc_html__( 'Use the %1$s for existing uploads or get %2$s for automatic WebP conversion and delivery.', 'ewww-image-optimizer' ),
									'<a href="' . esc_url( $bulk_link ) . '">' . esc_html__( 'Bulk Optimizer', 'ewww-image-optimizer' ) . '</a>',
									'<a href="https://ewww.io/plans/">' . esc_html__( 'Easy IO', 'ewww-image-optimizer' ) . '</a>'
								);
							}
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
		<?php
		if (
			! $cf_host &&
			! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) &&
			! ewww_image_optimizer_get_option( 'ewww_image_optimizer_picture_webp' )
		) :
			$webp_mime_error     = false;
			$webp_rewrite_verify = false;
			// Only check the rules for problems if WebP is enabled, otherwise this is a blank slate.
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) :
				$false_positive_headers = '';
				$header_error           = '';
				if ( $cloudways_host ) :
					$header_error = '<p class="ewww-webp-rewrite-info"><strong>' . esc_html__( 'In order to use server-based delivery, Cloudways sites must have WebP Redirection enabled in their Application Settings.', 'ewww-image-optimizer' ) . "</strong></p>\n";
				else :
					if ( defined( 'PHP_SAPI' ) && false === strpos( PHP_SAPI, 'apache' ) && false === strpos( PHP_SAPI, 'litespeed' ) ) {
						$false_positive_headers = esc_html__( 'This may be a false positive. If so, the warning should go away once you implement the rewrite rules.', 'ewww-image-optimizer' );
					}
					if ( ! apache_mod_loaded( 'mod_rewrite' ) ) {
						/* translators: %s: mod_rewrite or mod_headers */
						$header_error = '<p class="ewww-webp-rewrite-info"><strong>' . sprintf( esc_html__( 'Your site appears to be missing %s, please contact your webhost or system administrator to enable this Apache module.', 'ewww-image-optimizer' ), 'mod_rewrite' ) . "</strong><br>$false_positive_headers</p>\n";
					}
					if ( ! apache_mod_loaded( 'mod_headers' ) ) {
						/* translators: %s: mod_rewrite or mod_headers */
						$header_error = '<p class="ewww-webp-rewrite-info"><strong>' . sprintf( esc_html__( 'Your site appears to be missing %s, please contact your webhost or system administrator to enable this Apache module.', 'ewww-image-optimizer' ), 'mod_headers' ) . "</strong><br>$false_positive_headers</p>\n";
					}
				endif;
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
			<p><?php esc_html_e( 'Your site is using Cloudflare, please use JS WebP or Picture WebP rewriting to prevent broken images on older browsers.', 'ewww-image-optimizer' ); ?></p>
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
						<label for='ewww_image_optimizer_picture_webp'><strong><?php esc_html_e( 'Picture WebP Rewriting', 'ewww-image-optimizer' ); ?></strong></label>
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
						<label for='ewww_image_optimizer_webp_rewrite_exclude'><strong><?php esc_html_e( 'JS WebP and Picture Web Exclusions', 'ewww-image-optimizer' ); ?></strong></label><br>
						<textarea id='ewww_image_optimizer_webp_rewrite_exclude' name='ewww_image_optimizer_webp_rewrite_exclude' rows='3' cols='60'><?php echo esc_html( $webp_exclude_paths ); ?></textarea>
						<p class='description'>
							<?php esc_html_e( 'One exclusion per line, no wildcards (*) needed. Use any string that matches the desired element(s) or exclude entire element types like "div", "span", etc.', 'ewww-image-optimizer' ); ?>
							<?php esc_html_e( 'Exclude entire pages with page:/xyz/ syntax.', 'ewww-image-optimizer' ); ?>
						</p>
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
						<span><?php esc_html_e( 'WebP images will be generated and saved for all images regardless of their size. JS and Picture WebP rewriters will not check if a file exists, only that the domain matches the home url, or one of the provided WebP URLs.', 'ewww-image-optimizer' ); ?></span>
			<?php if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_force_gif2webp' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) : ?>
						<p>
							<a href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_enable_force_gif2webp' ), 'ewww_image_optimizer_options-options' ) ); ?>'>
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

			</table>
	<?php ob_end_flush(); ?>
		</div>

		<div id='ewww-local-settings'>
			<noscript><h2><?php esc_html_e( 'Local', 'ewww-image-optimizer' ); ?></h2></noscript>
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
	<?php if ( $disable_svg_level || ( empty( $tools['svgcleaner']['path'] ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) ) : ?>
		<?php if ( ewww_image_optimizer_svgcleaner_installer_available() ) : ?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_install_svgcleaner' ), 'ewww_image_optimizer_options-options' ) ); ?>"><?php esc_html_e( 'Install svgcleaner', 'ewww-image-optimizer' ); ?></a>
		<?php endif; ?>
	<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_webp_level'><?php esc_html_e( 'WebP Optimization Level', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/102-local-compression-options', '60c24b24a6d12c2cd643e9fb' ); ?>
					</th>
					<td>
						<select id='ewww_image_optimizer_webp_level' name='ewww_image_optimizer_webp_level'>
							<option value='0' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_level' ), 0 ); ?>>
								<?php esc_html_e( 'No Compression', 'ewww-image-optimizer' ); ?>
							</option>
							<option <?php disabled( $disable_level ); ?> value='20' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_level' ), 20 ); ?>>
								<?php esc_html_e( 'Premium', 'ewww-image-optimizer' ); ?> *
							</option>
						</select>
					</td>
				</tr>
				<tr>
					<th>&nbsp;</th>
					<td>
	<?php if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ! apply_filters( 'ewwwio_whitelabel', false ) ) : ?>
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
						<select id='ewww_image_optimizer_backup_files' name='ewww_image_optimizer_backup_files'>
							<option value='' <?php selected( (string) ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ), '' ); ?>>
								<?php esc_html_e( 'Disabled', 'ewww-image-optimizer' ); ?>
							</option>
							<option value='local' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ), 'local' ); ?>>
								<?php esc_html_e( 'Local', 'ewww-image-optimizer' ); ?>
							</option>
							<option <?php disabled( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ); ?> value='cloud' <?php selected( ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ), 'cloud' ); ?>>
								<?php esc_html_e( 'Cloud', 'ewww-image-optimizer' ); ?>
							</option>
						</select>
						<p class='description'>
							<?php esc_html_e( 'Local mode stores image backups on your server. With an active API key you may store image backups on our secure cloud storage for 30 days.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

	<?php
	$media_include_disable = '';
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt', false, true ) ) {
		$media_include_disable = true;
	}
	$aux_paths     = ewww_image_optimizer_get_option( 'ewww_image_optimizer_aux_paths' ) ? implode( "\n", (array) ewww_image_optimizer_get_option( 'ewww_image_optimizer_aux_paths' ) ) : '';
	$exclude_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_exclude_paths' ) ? implode( "\n", (array) ewww_image_optimizer_get_option( 'ewww_image_optimizer_exclude_paths' ) ) : '';
	/* translators: %s: the folder where WordPress is installed */
	$aux_paths_desc = sprintf( __( 'One path per line, must be within %s. Use full paths, not relative paths.', 'ewww-image-optimizer' ), ABSPATH );
	?>

		<div id='ewww-advanced-settings'>
			<noscript><h2><?php esc_html_e( 'Advanced', 'ewww-image-optimizer' ); ?></h2></noscript>
			<table class='form-table'>
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
						<label for='ewww_image_optimizer_exclude_paths'><?php esc_html_e( 'Exclude Images', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,5853713bc697912ffd6c0b98' ); ?>
					</th>
					<td>
						<?php esc_html_e( 'One exclusion per line, no wildcards (*) needed.', 'ewww-image-optimizer' ); ?><br>
						<textarea id='ewww_image_optimizer_exclude_paths' name='ewww_image_optimizer_exclude_paths' rows='3' cols='60'><?php echo esc_html( $exclude_paths ); ?></textarea>
						<p class='description'>
							<?php esc_html_e( 'Applies to optimization of local files, rather than front-end operations like Lazy Load or Easy IO. Thus exclusions must match filesystem paths instead of URLs.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<hr>
			<table class='form-table'>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_sharpen'><?php esc_html_e( 'Sharpen Images', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/124-sharpening-images', '629a6dfa92cb8c175b469bb3' ); ?>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_sharpen' name='ewww_image_optimizer_sharpen' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_sharpen' ) ); ?> />
	<?php if ( ewwwio()->imagick_support() ) : ?>
						<?php esc_html_e( 'Apply improved sharpening to JPG resizing operations and WebP Conversion.', 'ewww-image-optimizer' ); ?><br>
						<p class='description'>
							<?php esc_html_e( 'Uses additional CPU resources and may cause thumbnail generation for large images to fail.', 'ewww-image-optimizer' ); ?>
						</p>
	<?php else : ?>
						<?php esc_html_e( 'Apply improved sharpening during WebP Conversion.', 'ewww-image-optimizer' ); ?><br>
						<p class='description'>
							<?php esc_html_e( 'Improve JPG thumbnail generation by enabling the ImageMagick module for PHP.', 'ewww-image-optimizer' ); ?>
						</p>
	<?php endif; ?>
					</td>
				</tr>
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
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_webp_quality'><?php esc_html_e( 'WebP Quality Level', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0' ); ?>
					</th>
					<td>
						<input type='text' id='ewww_image_optimizer_webp_quality' name='ewww_image_optimizer_webp_quality' class='small-text' value='<?php echo esc_attr( ewww_image_optimizer_webp_quality() ); ?>' />
						<?php esc_html_e( 'Default is 75, allowed range is 50-100.', 'ewww-image-optimizer' ); ?>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_avif_quality'><?php esc_html_e( 'AVIF Quality Level', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0' ); ?>
					</th>
					<td>
						<input type='text' id='ewww_image_optimizer_avif_quality' name='ewww_image_optimizer_avif_quality' class='small-text' value='<?php echo esc_attr( ewww_image_optimizer_avif_quality() ); ?>' <?php disabled( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ); ?> />
						<?php esc_html_e( 'Default is 60, recommended range is 45-80.', 'ewww-image-optimizer' ); ?>
						<p class='description'>
							<?php esc_html_e( 'AVIF conversion is enabled via the Easy IO CDN.', 'ewww-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope='row'>
						<label for='ewww_image_optimizer_plaid'><?php esc_html_e( 'Plaid', 'ewww-image-optimizer' ); ?></label>
						<?php ewwwio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0' ); ?>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_plaid' name='ewww_image_optimizer_plaid' value='true' <?php checked( random_int( 0, 1 ) === 1 ); ?> />
						<?php esc_html_e( 'What happens when you enable Ludicrous Mode.', 'ewww-image-optimizer' ); ?>
					</td>
				</tr>
			</table>
		</div>

	<?php
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
	$bmpconvert = __( 'BMP to JPG Conversion', 'ewww-image-optimizer' );
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
						<label for='ewww_image_optimizer_gif_to_png'><?php echo esc_html( $gif2png ); ?></label>
						<span><?php ewwwio_help_link( 'https://docs.ewww.io/article/14-converting-images', '58545a86c697912ffd6c1b53' ); ?></span>
					</th>
					<td>
						<input type='checkbox' id='ewww_image_optimizer_gif_to_png' name='ewww_image_optimizer_gif_to_png' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_to_png' ) ); ?> />
						<span><?php esc_html_e( 'No warnings here, just do it.', 'ewww-image-optimizer' ); ?></span>
						<p class='description'><?php esc_html_e( 'PNG is generally better than GIF, but animated images cannot be converted.', 'ewww-image-optimizer' ); ?></p>
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
						<label for='ewww_image_optimizer_bmp_convert'><?php echo esc_html( $bmpconvert ); ?></label>
						<span><?php ewwwio_help_link( 'https://docs.ewww.io/article/14-converting-images', '58545a86c697912ffd6c1b53' ); ?></span>
					</th>
					<td>
		<?php if ( false && ewwwio()->imagick_support() ) : ?>
						<input type='checkbox' id='ewww_image_optimizer_bmp_convert' name='ewww_image_optimizer_bmp_convert' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_bmp_convert' ) ); ?> />
						<span><?php esc_html_e( 'Intelligently convert BMP images to the JPG or PNG formats, depending on the content of the image.', 'ewww-image-optimizer' ); ?></span>
		<?php else : ?>
						<input type='checkbox' id='ewww_image_optimizer_bmp_convert' name='ewww_image_optimizer_bmp_convert' value='true' <?php checked( ewww_image_optimizer_get_option( 'ewww_image_optimizer_bmp_convert' ) ); ?> />
						<span><?php esc_html_e( 'Convert BMP images to the JPG format.', 'ewww-image-optimizer' ); ?></span>
						<p class='description'><?php esc_html_e( 'WordPress already generates JPG thumbnails for BMP images, but converting the original can help you save disk space.', 'ewww-image-optimizer' ); ?></p>
		<?php endif; ?>
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
				<a style='float:right;' href='<?php echo esc_url( wp_nonce_url( admin_url( 'options-general.php?page=ewww-image-optimizer-options&uncomplete_wizard=1' ), 'ewww_image_optimizer_options-options' ) ); ?>'>
					<?php echo '.'; ?>
				</a>
			</p>
			<table class='form-table'>
	<?php if ( ! empty( $frontend_functions ) ) : ?>
				<tr>
					<th scope='row'>
						<p>
							<a id='ewww-rescue-mode' class='button-primary ewww-orange-button' href='<?php echo esc_url( $rescue_mode_url ); ?>'>
								<?php esc_html_e( 'Panic', 'ewww-image-optimizer' ); ?>
							</a>
						</p>
					</th>
					<td>
						<p>
							<strong><?php esc_html_e( 'Having problems with a broken site or wrong-sized images?', 'ewww-image-optimizer' ); ?></strong><br>
							<?php esc_html_e( 'Try disabling each of these options to identify the problem, or use the Panic Button to disable them all at once:', 'ewww-image-optimizer' ); ?><br>
							<?php
							foreach ( $frontend_functions as $frontend_function ) {
								echo '<i>' . esc_html( $frontend_function ) . '</i><br>';
							}
							?>
						</p>
					</td>
				</tr>
	<?php endif; ?>
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
	<?php if ( ewwwio_is_file( ewwwio()->debug_log_path() ) ) : ?>
				<tr>
					<th scope='row'>
						<?php esc_html_e( 'Debug Log', 'ewww-image-optimizer' ); ?>
					</th>
					<td>
						<p>
							<a target='_blank' href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_view_debug_log' ), 'ewww_image_optimizer_options-options' ) ); ?>'><?php esc_html_e( 'View Log', 'ewww-image-optimizer' ); ?></a> -
							<a href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_delete_debug_log' ), 'ewww_image_optimizer_options-options' ) ); ?>'><?php esc_html_e( 'Clear Log', 'ewww-image-optimizer' ); ?></a>
						</p>
						<p><a class='button button-secondary' target='_blank' href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_download_debug_log' ), 'ewww_image_optimizer_options-options' ) ); ?>'><?php esc_html_e( 'Download Log', 'ewww-image-optimizer' ); ?></a></p>
					</td>
				</tr>
	<?php endif; ?>
	<?php if ( ! empty( $debug_info ) ) : ?>
				<tr>
					<th scope='row'>
						<?php esc_html_e( 'System Info', 'ewww-image-optimizer' ); ?>
					</th>
					<td>
						<p class="debug-actions">
							<button id="ewww-copy-debug" class="button button-secondary" type="button"><?php esc_html_e( 'Copy', 'ewww-image-optimizer' ); ?></button>
						</p>
						<div id="ewww-debug-info" contenteditable="true">
							<?php echo wp_kses_post( $debug_info ); ?>
						</div>
					</td>
				</tr>
	<?php endif; ?>
			</table>

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
		<table class='form-table'>
		</table>
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
		email: '<?php echo esc_js( $help_email ); ?>',
		text: '\n\n---------------------------------------\n<?php echo wp_kses_post( $hs_debug ); ?>',
	});
</script>
		<?php
	}
	ewwwio_memory( __FUNCTION__ );
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
	if ( ! apply_filters( 'ewwwio_whitelabel', false ) ) {
		echo '<a class="' . esc_attr( $link_class ) . '" href="' . esc_url( $link ) . '" target="_blank" ' . esc_attr( $beacon_attr ) . '="' . esc_attr( $hsid ) . '">' .
			'<img title="' . esc_attr__( 'Help', 'ewww-image-optimizer' ) . '" src="' . esc_url( $help_icon ) . '">' .
			'</a>';
	}
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
			++$total;
			$blog_id = $blog['blog_id'];
			switch_to_blog( $blog_id );
			if ( get_option( 'ewww_image_optimizer_exactdn' ) && get_option( 'ewww_image_optimizer_exactdn_verified' ) ) {
				ewwwio_debug_message( "blog $blog_id active" );
				++$active;
			} else {
				ewwwio_debug_message( "blog $blog_id inactive" );
				++$inactive;
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
	check_admin_referer( 'ewww_image_optimizer_options-options' );
	$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', '' );
	if ( 'none' !== $redirect && false === current_user_can( $permissions ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_key', '' );
	$default_level = 10;
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
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_level' ) > 0 ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_webp_level', 0 );
	}
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_exceeded', 0 );
	delete_transient( 'ewww_image_optimizer_cloud_status' );
	if ( 'local' !== ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_backup_files', '' );
	}
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
	check_admin_referer( 'ewww_image_optimizer_options-options' );
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
	check_admin_referer( 'ewww_image_optimizer_options-options' );
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
	check_admin_referer( 'ewww_image_optimizer_options-options' );
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
		( ! empty( $_SERVER['SCRIPT_NAME'] ) && 'wp-login.php' === wp_basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) )
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
		<script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1"><?php echo $resize_detection_script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
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
		( ! empty( $_SERVER['SCRIPT_NAME'] ) && 'wp-login.php' === wp_basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) ) ||
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
 * Function to implement whitelabel, removing links to EWWW.IO.
 *
 * @param bool $whitelabeled True if Whitelabel mode is enabled.  Defaults to false.
 */
function ewwwio_is_whitelabel( $whitelabeled ) {
	if ( defined( 'EWWWIO_WHITELABEL' ) && EWWWIO_WHITELABEL ) {
		return true;
	}
	return $whitelabeled;
}

/**
 * Disables the debugging option.
 */
function ewww_image_optimizer_disable_debugging() {
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	update_option( 'ewww_image_optimizer_debug', false );
	update_site_option( 'ewww_image_optimizer_debug', false );
	$sendback = wp_get_referer();
	wp_safe_redirect( $sendback );
	exit;
}

/**
 * View the debug log file from the wp-admin.
 */
function ewww_image_optimizer_view_debug_log() {
	check_admin_referer( 'ewww_image_optimizer_options-options' );
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	$debug_log = ewwwio()->debug_log_path();
	if ( ewwwio_is_file( $debug_log ) ) {
		ewwwio_ob_clean();
		header( 'Content-Type: text/plain;charset=UTF-8' );
		readfile( $debug_log );
		exit;
	}
	wp_die( esc_html__( 'The Debug Log is empty.', 'ewww-image-optimizer' ) );
}

/**
 * Removes the debug log file.
 */
function ewww_image_optimizer_delete_debug_log() {
	check_admin_referer( 'ewww_image_optimizer_options-options' );
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	$debug_log = ewwwio()->debug_log_path();
	if ( ewwwio_is_file( $debug_log ) && is_writable( $debug_log ) ) {
		unlink( $debug_log );
	}
	$sendback = wp_get_referer();
	if ( empty( $sendback ) ) {
		$sendback = ewww_image_optimizer_get_settings_link();
	}
	wp_safe_redirect( $sendback );
	exit;
}

/**
 * Download the debug log file from the wp-admin.
 */
function ewww_image_optimizer_download_debug_log() {
	check_admin_referer( 'ewww_image_optimizer_options-options' );
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	$debug_log = ewwwio()->debug_log_path();
	if ( ewwwio_is_file( $debug_log ) ) {
		ewwwio_ob_clean();
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: text/plain;charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename=ewwwio-debug-log-' . gmdate( 'Ymd-His' ) . '.txt' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $debug_log ) );
		readfile( $debug_log );
		exit;
	}
	wp_die( esc_html__( 'The Debug Log is empty.', 'ewww-image-optimizer' ) );
}

/**
 * Adds version information to the in-memory debug log.
 *
 * @global int $wp_version
 */
function ewwwio_debug_version_info() {
	$eio_debug = '';
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
	if ( ! empty( $_ENV['PANTHEON_ENVIRONMENT'] ) ) {
		$pantheon_env = sanitize_text_field( $_ENV['PANTHEON_ENVIRONMENT'] );
		if ( in_array( $pantheon_env, array( 'test', 'live', 'dev' ), true ) ) {
			$eio_debug .= "detected pantheon env: $pantheon_env<br>";
		}
	}
	$eio_debug .= 'core plugin<br>';

	EWWW\Base::$debug_data .= $eio_debug;
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
 * @param string $function_name The name of the function or descriptive label.
 */
function ewwwio_memory( $function_name ) {
	return;
	if ( WP_DEBUG ) {
		global $ewww_memory;
		$ewww_memory .= $function_name . ': ' . memory_get_usage( true ) . "\n";
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
 * @param mixed $var1 Could be anything, really.
 * @param mixed $var2 Default false. Could be anything, really.
 * @param mixed $var3 Default false. Could be anything, really.
 * @param mixed $var4 Default false. Could be anything, really.
 * @return mixed Whatever they gave us.
 */
function ewwwio_dump_var( $var1, $var2 = false, $var3 = false, $var4 = false ) {
	if ( ! ewww_image_optimizer_function_exists( 'print_r' ) ) {
		return $var1;
	}
	ewwwio_debug_message( 'current filter: ' . current_filter() );
	ewwwio_debug_message( 'dumping var' );
	ewwwio_debug_message( print_r( $var1, true ) );
	if ( $var2 ) {
		ewwwio_debug_message( 'dumping var2' );
		ewwwio_debug_message( print_r( $var2, true ) );
	}
	if ( $var3 ) {
		ewwwio_debug_message( 'dumping var3' );
		ewwwio_debug_message( print_r( $var3, true ) );
	}
	if ( $var4 ) {
		ewwwio_debug_message( 'dumping var4' );
		ewwwio_debug_message( print_r( $var4, true ) );
	}
	return $var1;
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