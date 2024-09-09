<?php
/**
 * Help integration functions for embedding the HS Beacon for users that have opted in.
 *
 * @package     EIO
 * @since       3.5.1
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Embed HS Beacon to give users more help when they need it.
 *
 * @since  3.5.1
 */
class HS_Beacon extends Base {

	/**
	 * Get things going
	 */
	public function __construct() {
		parent::__construct();
		\add_action( 'admin_action_eio_opt_into_hs_beacon', array( $this, 'check_for_optin' ) );
		\add_action( 'admin_action_eio_opt_out_of_hs_beacon', array( $this, 'check_for_optout' ) );
	}

	/**
	 * Check for a new opt-in via the admin notice
	 */
	public function check_for_optin() {
		\check_admin_referer( 'eio_beacon' );
		$permissions = \apply_filters( 'easyio_admin_permissions', 'manage_options' );
		if ( ! \current_user_can( $permissions ) ) {
			\wp_die( \esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->set_option( $this->prefix . 'enable_help', 1 );
		$this->set_option( $this->prefix . 'enable_help_notice', 1 );
		\wp_safe_redirect( \remove_query_arg( 'action', \wp_get_referer() ) );
		exit;
	}

	/**
	 * Check for a new opt-out via the admin notice
	 */
	public function check_for_optout() {
		\check_admin_referer( 'eio_beacon' );
		$permissions = \apply_filters( 'easyio_admin_permissions', 'manage_options' );
		if ( ! \current_user_can( $permissions ) ) {
			\wp_die( \esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		\delete_option( $this->prefix . 'enable_help' );
		\delete_network_option( null, $this->prefix . 'enable_help' );
		$this->set_option( $this->prefix . 'enable_help_notice', 1 );
		\wp_safe_redirect( \remove_query_arg( 'action', \wp_get_referer() ) );
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
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$hide_notice = $this->get_option( $this->prefix . 'enable_help_notice' );
		if ( 'network-multisite-over' === $network_class ) {
			return;
		}
		if ( 'network-singlesite' === $network_class ) {
			return;
		}

		if ( $hide_notice ) {
			return;
		}
		if ( $this->get_option( $this->prefix . 'enable_help' ) ) {
			return;
		}

		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! \function_exists( 'is_plugin_active_for_network' ) && \is_multisite() ) {
			// Need to include the plugin library for the is_plugin_active function.
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if (
			\is_multisite() &&
			\is_plugin_active_for_network( \constant( \strtoupper( $this->prefix ) . 'PLUGIN_FILE_REL' ) ) &&
			! \current_user_can( 'manage_network_options' )
		) {
			return;
		}
		if ( \strpos( __FILE__, 'plugins/easy' ) ) {
			\easyio_notice_beacon();
		}
	}
}
