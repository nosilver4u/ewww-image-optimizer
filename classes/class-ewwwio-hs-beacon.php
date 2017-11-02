<?php
/**
 * Help integration functions for embedding the HS Beacon for users that have opted in.
 *
 * @package     EWWW_Image_Optimizer
 * @since       3.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Embed HS Beacon to give users more help when they need it.
 *
 * @since  3.5.1
 */
class EWWWIO_HS_Beacon {

	/**
	 * Get things going
	 */
	public function __construct() {
		add_action( 'admin_action_ewww_opt_into_hs_beacon', array( $this, 'check_for_optin' ) );
		add_action( 'admin_action_ewww_opt_out_of_hs_beacon', array( $this, 'check_for_optout' ) );
	}

	/**
	 * Check for a new opt-in on settings save
	 *
	 * @param bool $input The enable_help setting.
	 * @return bool The unaltered setting.
	 */
	public function check_for_settings_optin( $input ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		if ( isset( $_POST['ewww_image_optimizer_enable_help'] ) && $_POST['ewww_image_optimizer_enable_help'] ) {
			ewww_image_optimizer_set_option( 'ewww_image_optimizer_tracking_notice', 1 );
		}
		return $input;
	}

	/**
	 * Check for a new opt-in via the admin notice
	 */
	public function check_for_optin() {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_enable_help', 1 );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_enable_help_notice', 1 );
		wp_redirect( remove_query_arg( 'action', wp_get_referer() ) );
		exit;
	}

	/**
	 * Check for a new opt-out via the admin notice
	 */
	public function check_for_optout() {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		delete_option( 'ewww_image_optimizer_enable_help' );
		delete_network_option( null, 'ewww_image_optimizer_enable_help' );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_enable_help_notice', 1 );
		wp_redirect( remove_query_arg( 'action', wp_get_referer() ) );
		exit;
	}

	/**
	 * Display the admin notice to users that have not opted-in or out
	 *
	 * @access public
	 * @param string $network_class A string that indicates where this is being displayed.
	 * @return void
	 */
	public function admin_notice( $network_class = '' ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		$hide_notice = ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_help_notice' );
		if ( 'network-multisite' == $network_class && get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) ) {
			return;
		}
		if ( 'network-singlesite' == $network_class && ! get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) ) {
			return;
		}

		if ( $hide_notice ) {
			return;
		}
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_help' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
			// Need to include the plugin library for the is_plugin_active function.
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) && ! current_user_can( 'manage_network_options' ) ) {
			return;
		}
		$optin_url  = 'admin.php?action=ewww_opt_into_hs_beacon';
		$optout_url = 'admin.php?action=ewww_opt_out_of_hs_beacon';
		echo '<div class="updated"><p>';
		esc_html_e( 'Enable the support beacon, which gives you access to documentation and our support team right from your WordPress dashboard. To assist you more efficiently, we may collect the current url, IP address, browser/device information, and debugging information.', 'ewww-image-optimizer' );
		echo '&nbsp;<a href="' . esc_url( $optin_url ) . '" class="button-secondary">' . esc_html__( 'Allow', 'ewww-image-optimizer' ) . '</a>';
		echo '&nbsp;<a href="' . esc_url( $optout_url ) . '" class="button-secondary">' . esc_html__( 'Do not allow', 'ewww-image-optimizer' ) . '</a>';
		echo '</p></div>';
	}

}
$ewwwio_hs_beacon = new EWWWIO_HS_Beacon;
