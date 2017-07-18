<?php
/**
 * Loader for Standard EWWW I.O. plugin.
 *
 * This file bootstraps the rest of the EWWW IO plugin after some basic checks.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

/*
Plugin Name: EWWW Image Optimizer
Plugin URI: https://wordpress.org/plugins/ewww-image-optimizer/
Description: Reduce file sizes for images within WordPress including NextGEN Gallery and GRAND FlAGallery. Uses jpegtran, optipng/pngout, and gifsicle.
Author: Shane Bishop
Text Domain: ewww-image-optimizer
Version: 3.5.1
Author URI: https://ewww.io/
License: GPLv3
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_TOOL_PATH' ) ) {
	/**
	 * The folder where we install optimization tools - MUST have a trailing slash.
	 *
	 * @var string EWWW_IMAGE_OPTIMIZER_TOOL_PATH
	 */
	define( 'EWWW_IMAGE_OPTIMIZER_TOOL_PATH', WP_CONTENT_DIR . '/ewww/' );
}

// Check the PHP version.
if ( ! defined( 'PHP_VERSION_ID' ) || PHP_VERSION_ID < 50300 ) {
	/**
	 * This is the full system path to the plugin folder.
	 *
	 * @var string EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH
	 */
	define( 'EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
	add_action( 'network_admin_notices', 'ewww_image_optimizer_unsupported_php' );
	add_action( 'admin_notices', 'ewww_image_optimizer_unsupported_php' );
	// Loads the plugin translations.
	add_action( 'plugins_loaded', 'ewww_image_optimizer_false_init' );
} elseif ( defined( 'EWWW_IMAGE_OPTIMIZER_VERSION' ) ) {
	// Prevent loading both EWWW IO plugins.
	add_action( 'network_admin_notices', 'ewww_image_optimizer_dual_plugin' );
	add_action( 'admin_notices', 'ewww_image_optimizer_dual_plugin' );
	// Loads the plugin translations.
	add_action( 'plugins_loaded', 'ewww_image_optimizer_false_init' );
} else {
	/**
	 * The full path of the plugin file (this file).
	 *
	 * @var string EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE
	 */
	define( 'EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE', __FILE__ );
	/**
	 * The path of the plugin file relative to the plugins/ folder.
	 *
	 * @var string EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL
	 */
	define( 'EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL', 'ewww-image-optimizer/ewww-image-optimizer.php' );
	/**
	 * This is the full system path to the plugin folder.
	 *
	 * @var string EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH
	 */
	define( 'EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
	/**
	 * This is the full system path to the bundled binaries.
	 *
	 * @var string EWWW_IMAGE_OPTIMIZER_BINARY_PATH
	 */
	define( 'EWWW_IMAGE_OPTIMIZER_BINARY_PATH', plugin_dir_path( __FILE__ ) . 'binaries/' );
	/**
	 * This is the full system path to the plugin images for testing.
	 *
	 * @var string EWWW_IMAGE_OPTIMIZER_IMAGES_PATH
	 */
	define( 'EWWW_IMAGE_OPTIMIZER_IMAGES_PATH', plugin_dir_path( __FILE__ ) . 'images/' );

	/**
	 * All the 'unique' functions for the core EWWW I.O. plugin.
	 */
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'unique.php' );
	/**
	 * All the 'common' functions for both EWWW I.O. functions.
	 */
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'common.php' );
	/**
	 * The various class extensions for parallel and background optimization.
	 */
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
	/**
	 * EWWW_Image class for working with queued images and image records from the database.
	 */
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewww-image.php' );
	/**
	 * EWWWIO_Tracking class for reporting anonymous site data.
	 */
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewwwio-tracking.php' );
	/**
	 * EWWWIO_HS_Beacon class for embedding the HelpScout Beacon.
	 */
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewwwio-hs-beacon.php' );
} // End if().

if ( ! function_exists( 'ewww_image_optimizer_unsupported_php' ) ) {
	/**
	 * Display a notice that the PHP version is too old.
	 */
	function ewww_image_optimizer_unsupported_php() {
		echo "<div id='ewww-image-optimizer-warning-php' class='error'><p><strong>" . esc_html__( 'EWWW Image Optimizer requires PHP 5.3 or greater. Newer versions of PHP, like 5.6, 7.0 and 7.1, are significantly faster and much more secure, as PHP 5.2 has been unsupported for several years. If you are unsure how to upgrade to a supported version, ask your webhost for instructions.', 'ewww-image-optimizer' ) . '</strong></p></div>';
	}

	/**
	 * Display a notice when both the standard and cloud plugins are active.
	 */
	function ewww_image_optimizer_dual_plugin() {
		echo "<div id='ewww-image-optimizer-warning-double-plugin' class='error'><p><strong>" . esc_html__( 'Only one version of the EWWW Image Optimizer can be active at a time. Please deactivate other copies of the plugin.', 'ewww-image-optimizer' ) . '</strong></p></div>';
	}

	/**
	 * Runs on 'plugins_loaded' to load the language files when EWWW is not loading.
	 */
	function ewww_image_optimizer_false_init() {
		load_plugin_textdomain( 'ewww-image-optimizer', false, EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'languages/' );
	}
}
