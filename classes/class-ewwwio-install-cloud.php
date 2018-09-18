<?php
/**
 * Functions to install the Cloud version of the plugin.
 *
 * Portions thanks to TGMPA: http://tgmpluginactivation.com/
 *
 * @package     EWWW_Image_Optimizer
 * @since       4.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installs the Cloud version of the plugin.
 *
 * @since  3.5.1
 */
class EWWWIO_Install_Cloud {

	/**
	 * URL for Cloud plugin.
	 *
	 * @access private
	 * @var string $plugin_url
	 */
	private $plugin_url = '';

	/**
	 * Slug for Cloud plugin.
	 *
	 * @access private
	 * @var string $plugin_slug
	 */
	private $plugin_slug = 'ewww-image-optimizer-cloud';

	/**
	 * Slug for Core plugin.
	 *
	 * @access private
	 * @var string $core_slug
	 */
	private $core_slug = 'ewww-image-optimizer';

	/**
	 * Folder where plugins are installed.
	 *
	 * @access private
	 * @var string $plugins_folder
	 */
	private $plugins_folder = '';

	/**
	 * Get things going.
	 */
	public function __construct() {
		// Installation routine for cloud plugin.
		add_action( 'admin_action_ewwwio_install_cloud_plugin', array( $this, 'install_cloud_plugin' ) );

		$this->plugins_folder = trailingslashit( dirname( dirname( __DIR__ ) ) );

		$this->plugin_file_rel = $this->plugin_slug . '/' . $this->plugin_slug . '.php';
		$this->plugin_file     = $this->plugins_folder . $this->plugin_file_rel;

		$this->core_plugin_file_rel = $this->core_slug . '/' . $this->core_slug . '.php';
		$this->core_plugin_file     = $this->plugins_folder . $this->core_plugin_file_rel;
	}

	/**
	 * Run the installer.
	 */
	public function install_cloud_plugin() {
		$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', 'install_plugins' );
		if ( false === current_user_can( $permissions ) ) {
			wp_die( esc_html__( 'You do not have permission to install image optimizer utilities.', 'ewww-image-optimizer' ) );
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		if ( ! is_readable( $this->plugins_folder ) || ! is_writable( $this->plugins_folder ) ) {
			wp_die( esc_html__( 'The plugins folder is not writable, you may install the EWWW Image Optimizer Cloud manually.', 'ewww-image-optimizer' ) );
			return;
		}
		if ( ! is_file( $this->plugin_file ) ) {
			if ( is_dir( dirname( $this->plugin_file ) ) ) {
				wp_die( esc_html__( 'A partial installation already exists. Please remove it and try again.', 'ewww-image-optimizer' ) );
			}
			$this->plugin_url = $this->get_download_url();
			if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
				require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
			}
			$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
			$upgraded = $upgrader->install( $this->plugin_url );
			if ( is_wp_error( $upgraded ) ) {
				wp_die( esc_html( $upgraded->get_error_message() ) );
			}
		}

		if ( is_file( $this->plugin_file ) ) {
			$core_active = $this->is_plugin_active( $this->core_plugin_file_rel );
			// If not plugin active.
			if ( ! $this->is_plugin_active( $this->plugin_file_rel ) ) {
				$network_wide = false;
				if ( 'multi' === $core_active ) {
					$network_wide = true;
				}
				if ( $core_active ) {
					deactivate_plugins( $this->core_plugin_file_rel, true, $network_wide );
				}
				$activate = activate_plugin( $this->plugin_file_rel, '', $network_wide );
				if ( is_wp_error( $activate ) ) {
					wp_die( esc_html( $activate->get_error_message() ) );
				}
				if ( $network_wide ) {
					$redirect_url = admin_url( 'network/plugins.php?ewwwio_cloud_activated=1' );
				} else {
					$redirect_url = admin_url( 'plugins.php?ewwwio_cloud_activated=1' );
				}
				wp_redirect( $redirect_url );
				exit( 0 );
			} else {
				wp_redirect( admin_url( 'plugins.php' ) );
				exit( 0 );
			}
		}
		wp_redirect( admin_url( 'plugins.php' ) );
		exit( 0 );
	}

	/**
	 * Get the download URL from WP.org.
	 *
	 * @return string The url for the latest version.
	 */
	protected function get_download_url() {
		$url = '';
		if ( ! function_exists( 'plugins_api' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
		}
		$response = plugins_api(
			'plugin_information',
			array(
				'slug'   => $this->plugin_slug,
				'fields' => array(
					'sections'          => false,
					'short_description' => false,
					'downloaded'        => false,
					'rating'            => false,
					'ratings'           => false,
					'tags'              => false,
					'homepage'          => false,
					'donate_link'       => false,
					'added'             => false,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			wp_die( esc_html( $response->get_error_message() ) );
		} else {
			$url = $response->download_link;
		}
		return $url;
	}

	/**
	 * Check if a plugin is active.
	 *
	 * @param string $file Plugin file basename.
	 * @return bool|string True or 'multi' if active, false otherwise.
	 */
	function is_plugin_active( $file ) {
		if ( is_multisite() && is_plugin_active_for_network( $file ) ) {
			return 'multi';
		}
		if ( is_plugin_active( $file ) ) {
			return true;
		}
		return false;
	}
}

$ewwwio_install_cloud = new EWWWIO_Install_Cloud;
