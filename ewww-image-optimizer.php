<?php
/**
 * Loader for Standard EWWW IO plugin.
 *
 * This file bootstraps the rest of the EWWW IO plugin after some basic checks.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

/*
Plugin Name: EWWW Image Optimizer
Plugin URI: https://wordpress.org/plugins/ewww-image-optimizer/
Description: Smaller Images, Faster Sites, Happier Visitors. Comprehensive image optimization that doesn't require a degree in rocket science.
Author: Exactly WWW
Version: 7.9.0
Requires at least: 6.3
Requires PHP: 7.4
Author URI: https://ewww.io/
License: GPLv3
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check the PHP version.
if ( ! defined( 'PHP_VERSION_ID' ) || PHP_VERSION_ID < 70400 ) {
	add_action( 'network_admin_notices', 'ewww_image_optimizer_unsupported_php' );
	add_action( 'admin_notices', 'ewww_image_optimizer_unsupported_php' );
} elseif ( defined( 'EWWW_IMAGE_OPTIMIZER_VERSION' ) ) {
	// Prevent loading more than one EWWW IO plugin.
	add_action( 'network_admin_notices', 'ewww_image_optimizer_dual_plugin' );
	add_action( 'admin_notices', 'ewww_image_optimizer_dual_plugin' );
} elseif ( false === strpos( add_query_arg( '', '' ), 'ewwwio_disable=1' ) ) {

	define( 'EWWW_IMAGE_OPTIMIZER_VERSION', 790 );

	if ( WP_DEBUG && function_exists( 'memory_get_usage' ) ) {
		$ewww_memory = 'plugin load: ' . memory_get_usage( true ) . "\n";
	}

	/**
	 * Always use relative paths unless the user has already defined this constant.
	 *
	 * @var bool EWWW_IMAGE_OPTIMIZER_RELATIVE
	 */
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_RELATIVE' ) ) {
		define( 'EWWW_IMAGE_OPTIMIZER_RELATIVE', true );
	}
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
	define( 'EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL', plugin_basename( __FILE__ ) );
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
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_TOOL_PATH' ) ) {
		if ( ! defined( 'EWWWIO_CONTENT_DIR' ) ) {
			$ewwwio_content_dir = trailingslashit( realpath( WP_CONTENT_DIR ) ) . trailingslashit( 'ewww' );
			if ( ! is_writable( WP_CONTENT_DIR ) || ! empty( $_ENV['PANTHEON_ENVIRONMENT'] ) ) {
				$upload_dir = wp_get_upload_dir();
				if ( false === strpos( $upload_dir['basedir'], '://' ) && is_writable( $upload_dir['basedir'] ) ) {
					$ewwwio_content_dir = trailingslashit( realpath( $upload_dir['basedir'] ) ) . trailingslashit( 'ewww' );
				}
			}
			/**
			 * The folder where we store debug logs (among other things) - MUST have a trailing slash.
			 *
			 * @var string EWWWIO_CONTENT_DIR
			 */
			define( 'EWWWIO_CONTENT_DIR', $ewwwio_content_dir );
		}
		/**
		 * The folder where we install optimization tools - MUST have a trailing slash.
		 *
		 * @var string EWWW_IMAGE_OPTIMIZER_TOOL_PATH
		 */
		define( 'EWWW_IMAGE_OPTIMIZER_TOOL_PATH', EWWWIO_CONTENT_DIR );
	} elseif ( ! defined( 'EWWWIO_CONTENT_DIR' ) ) {
		define( 'EWWWIO_CONTENT_DIR', EWWW_IMAGE_OPTIMIZER_TOOL_PATH );
	}

	/**
	 * All the 'unique' functions for the core EWWW IO plugin (slowly being replaced with oop).
	 */
	require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'unique.php';
	/**
	 * All the 'common' functions for both EWWW IO plugins.
	 */
	require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'common.php';
	/**
	 * All the base functions for our plugins and classes to inherit.
	 */
	require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-base.php';
	/**
	 * The setup functions for EWWW IO.
	 */
	require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-plugin.php';
	/**
	 * Class for local optimization tool installation/valication.
	 */
	require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-local.php';
	/**
	 * The main function to return a single EWWW\Plugin object to functions elsewhere.
	 *
	 * @return object object|EWWW\Plugin The one true EWWW\Plugin instance.
	 */
	function ewwwio() {
		return EWWW\Plugin::instance();
	}
	ewwwio();
} // End if().

if ( ! function_exists( 'ewww_image_optimizer_unsupported_php' ) ) {
	/**
	 * Display a notice that the PHP version is too old.
	 */
	function ewww_image_optimizer_unsupported_php() {
		echo '<div id="ewww-image-optimizer-warning-php" class="error"><p><a href="https://docs.ewww.io/article/55-upgrading-php" target="_blank" data-beacon-article="5ab2baa6042863478ea7c2ae">' . esc_html__( 'EWWW Image Optimizer requires PHP 7.4 or greater. Newer versions of PHP are significantly faster and much more secure. If you are unsure how to upgrade to a supported version, ask your webhost for instructions.', 'ewww-image-optimizer' ) . '</a></p></div>';
	}

	/**
	 * Display a notice when both the standard and cloud plugins are active.
	 */
	function ewww_image_optimizer_dual_plugin() {
		echo "<div id='ewww-image-optimizer-warning-double-plugin' class='error'><p><strong>" . esc_html__( 'Only one version of the EWWW Image Optimizer can be active at a time. Please deactivate other copies of the plugin.', 'ewww-image-optimizer' ) . '</strong></p></div>';
	}
}
