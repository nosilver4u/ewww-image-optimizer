<?php
/**
 * Uninstaller for plugin.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}
if ( ! function_exists( 'ewww_image_optimizer_htaccess_path' ) ) {
	/**
	 * Figure out where the .htaccess file should live.
	 *
	 * @return string The path to the .htaccess file.
	 */
	function ewww_image_optimizer_htaccess_path() {
		$htpath = get_home_path();
		if ( get_option( 'siteurl' ) !== get_option( 'home' ) ) {
			$path_diff = str_replace( get_option( 'home' ), '', get_option( 'siteurl' ) );
			$newhtpath = trailingslashit( rtrim( $htpath, '/' ) . '/' . ltrim( $path_diff, '/' ) ) . '.htaccess';
			if ( is_file( $newhtpath ) ) {
				return $newhtpath;
			}
		}
		return $htpath . '.htaccess';
	}
}

if ( current_user_can( 'delete_plugins' ) ) {
	if ( extract_from_markers( ewww_image_optimizer_htaccess_path(), 'EWWWIO' ) ) {
		insert_with_markers( ewww_image_optimizer_htaccess_path(), 'EWWWIO', '' );
	}
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE from $wpdb->ewwwio_queue WHERE gallery = %s", 'media' ) );
	$wpdb->query( $wpdb->prepare( "DELETE from $wpdb->ewwwio_queue WHERE gallery = %s", 'flag' ) );
	$wpdb->query( $wpdb->prepare( "DELETE from $wpdb->ewwwio_queue WHERE gallery = %s", 'nextgen' ) );
	$wpdb->query( $wpdb->prepare( "DELETE from $wpdb->ewwwio_queue WHERE gallery = %s", 'nextcell' ) );
}
