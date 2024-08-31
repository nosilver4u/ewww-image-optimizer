<?php
/**
 * Functions for reporting plugin usage to EWWW IO for users that have opted in.
 *
 * @package     EWWW_Image_Optimizer
 * @copyright   Copyright (c) 2017, Pippin Williamson and Shane Bishop
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.3.2
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Usage tracking so we can make informed decisions.
 *
 * @since  3.3.2
 */
class Tracking {

	/**
	 * The data to send to the API
	 *
	 * @access private
	 * @var array $data
	 */
	private $data;

	/**
	 * Get things going
	 */
	public function __construct() {
		\add_action( 'admin_init', array( $this, 'schedule_send' ) );
		\add_action( 'ewww_image_optimizer_site_report', array( $this, 'send_checkin' ) );
		\add_action( 'admin_action_ewww_opt_into_tracking', array( $this, 'check_for_optin' ) );
		\add_action( 'admin_action_ewww_opt_out_of_tracking', array( $this, 'check_for_optout' ) );
		\add_action( 'admin_notices', array( $this, 'admin_notice' ) );
		\add_action( 'network_admin_notices', array( $this, 'admin_notice' ) );
		\register_deactivation_hook( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE, array( $this, 'unschedule_send' ) );
	}

	/**
	 * Check if the user has opted into tracking
	 *
	 * @access private
	 * @return bool
	 */
	private function tracking_allowed() {
		return (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_allow_tracking' );
	}

	/**
	 * Setup the data that is going to be tracked
	 *
	 * @access private
	 * @return void
	 */
	private function setup_data() {
		\ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		$data = array();

		// Retrieve current theme info.
		$theme_data      = \wp_get_theme();
		$theme           = $theme_data->Name . ' ' . $theme_data->Version; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$data['site_id'] = \md5( \home_url() );
		if (
			\strlen( \ewww_image_optimizer_get_option( 'ewww_image_optimizer_tracking_site_id' ) ) === 32 &&
			\ctype_alnum( \ewww_image_optimizer_get_option( 'ewww_image_optimizer_tracking_site_id' ) )
		) {
			\ewwwio_debug_message( 'using pre-existing site_id' );
			$data['site_id'] = \ewww_image_optimizer_get_option( 'ewww_image_optimizer_tracking_site_id' );
		} else {
			\ewww_image_optimizer_set_option( 'ewww_image_optimizer_tracking_site_id', $data['site_id'] );
		}
		$data['ewwwio_version'] = EWWW_IMAGE_OPTIMIZER_VERSION;
		$data['wp_version']     = \get_bloginfo( 'version' );
		$data['php_version']    = PHP_VERSION_ID;
		$data['server']         = isset( $_SERVER['SERVER_SOFTWARE'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
		$data['multisite']      = \is_multisite();
		$data['theme']          = $theme;

		// Retrieve current plugin information.
		if ( ! \function_exists( '\get_plugins' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		$plugins        = \array_keys( \get_plugins() );
		$active_plugins = \get_option( 'active_plugins', array() );

		foreach ( $plugins as $key => $plugin ) {
			if ( \in_array( $plugin, $active_plugins, true ) ) {
				// Remove active plugins from list so we can show active and inactive separately.
				unset( $plugins[ $key ] );
			}
		}

		$data['active_plugins']   = $active_plugins;
		$data['inactive_plugins'] = $plugins;
		$data['locale']           = ( $data['wp_version'] >= 4.7 ) ? \get_user_locale() : \get_locale();
		if ( ! \function_exists( '\ewww_image_optimizer_aux_images_table_count_pending' ) ) {
			require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'aux-optimize.php';
		}
		if (
			\ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ||
			! \ewwwio()->local->os_supported() ||
			! \ewwwio()->local->exec_check()
		) {
			$total_images  = 0;
			$total_savings = 0;
		} else {
			$total_images  = \ewww_image_optimizer_aux_images_table_count();
			$total_sizes   = \ewww_image_optimizer_savings();
			$total_savings = $total_sizes[1] - $total_sizes[0];
		}

		$data['images_optimized'] = $total_images;
		$data['bytes_saved']      = $total_savings;

		$data['nextgen']          = \class_exists( '\EWWW_Nextgen' ) ? true : false;
		$data['nextcellent']      = \class_exists( '\EWWW_Nextcellent' ) ? true : false;
		$data['flagallery']       = \class_exists( '\EWWW_Flag' ) ? true : false;
		$data['memory_limit']     = \ewwwio_memory_limit();
		$data['time_limit']       = (int) \ini_get( 'max_execution_time' );
		$data['operating_system'] = \ewwwio()->function_exists( '\php_uname' ) ? \php_uname( 's' ) : '';

		$data['image_library'] = '';
		if ( \ewwwio()->gmagick_support() ) {
			$data['image_library'] = 'gmagick';
		} elseif ( \ewwwio()->imagick_support() ) {
			$data['image_library'] = 'imagick';
		} elseif ( \ewwwio()->gd_support() ) {
			$data['image_library'] = 'gd';
		}

		$data['cloud_api']     = \ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ? true : false;
		$data['keep_metadata'] = \ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ? false : true;
		$data['jpg_level']     = (int) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' );
		$data['png_level']     = (int) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' );
		$data['gif_level']     = (int) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' );
		$data['pdf_level']     = (int) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' );
		$data['bulk_delay']    = (int) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' );
		$data['backups']       = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' );

		if ( \ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) && \class_exists( __NAMESPACE__ . '\ExactDN' ) ) {
			global $exactdn;
			if ( $exactdn->get_exactdn_domain() ) {
				$data['exactdn_lossy']               = (int) \ewww_image_optimizer_get_option( 'exactdn_lossy' );
				$data['exactdn_all_the_things']      = (bool) \ewww_image_optimizer_get_option( 'exactdn_all_the_things' );
				$data['exactdn_resize_existing']     = (bool) \ewww_image_optimizer_get_option( 'exactdn_resize_existing' );
				$data['exactdn_prevent_db_queries']  = (bool) \ewww_image_optimizer_get_option( 'exactdn_prevent_db_queries' );
				$data['exactdn_prevent_srcset_fill'] = (bool) \ewww_image_optimizer_get_option( 'exactdn_prevent_srcset_fill' );
			}
		}

		$data['lazyload']               = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' );
		$data['optipng_level']          = \ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ? 0 : (int) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_optipng_level' );
		$data['disable_pngout']         = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_pngout' );
		$data['pngout_level']           = \ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ? 9 : (int) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_pngout_level' );
		$data['jpg_quality']            = (int) \apply_filters( 'jpeg_quality', 82 );
		$data['background_opt']         = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_background_optimization' );
		$data['scheduled_opt']          = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_auto' );
		$data['include_media_folders']  = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_media_paths' );
		$data['include_originals']      = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_originals' );
		$data['folders_to_opt']         = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_aux_paths' );
		$data['folders_to_ignore']      = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_exclude_paths' );
		$data['resize_media_width']     = (int) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' );
		$data['resize_media_height']    = (int) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' );
		$data['resize_indirect_width']  = (int) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherwidth' );
		$data['resize_indirect_height'] = (int) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherheight' );
		$data['resize_existing']        = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_existing' );
		$data['resize_other']           = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_other_existing' );
		$data['total_sizes']            = (int) \count( \ewww_image_optimizer_get_image_sizes() );
		$data['disabled_opt_sizes']     = \is_array( \get_option( 'ewww_image_optimizer_disable_resizes_opt' ) ) ? \count( \get_option( 'ewww_image_optimizer_disable_resizes_opt' ) ) : 0;
		$data['disabled_create_sizes']  = \is_array( \get_option( 'ewww_image_optimizer_disable_resizes' ) ) ? \count( \get_option( 'ewww_image_optimizer_disable_resizes' ) ) : 0;
		$data['skip_small_images']      = (int) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_size' );
		$data['skip_large_pngs']        = (int) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' );
		$data['exclude_full_lossy']     = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_lossy_skip_full' );
		$data['exclude_full_meta']      = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_skip_full' );
		$data['system_paths']           = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_bundle' );

		$data['hide_conversion']  = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_convert_links' );
		$data['delete_originals'] = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' );
		$data['jpg2png']          = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_to_png' );
		$data['png2jpg']          = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_to_jpg' );
		$data['gif2png']          = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_to_png' );
		$data['bmpconvert']       = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_bmp_convert' );
		$data['fill_color']       = \is_null( \ewww_image_optimizer_jpg_background() ) ? '' : \ewww_image_optimizer_jpg_background();

		$data['webp_create'] = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' );
		$data['webp_force']  = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' );
		$data['webp_urls']   = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' );
		$data['alt_webp']    = (bool) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' );

		$this->data = $data;
	}

	/**
	 * Send the data to the EDD server
	 *
	 * @access private
	 *
	 * @param bool $override Optional. Force check-in regardless of stored option. Default false.
	 * @param bool $ignore_last_checkin Optional. Force check-in regardless of last attempted time. Default false.
	 *
	 * @return bool Was data sent.
	 */
	public function send_checkin( $override = false, $ignore_last_checkin = false ) {
		if ( ! $this->tracking_allowed() && ! $override ) {
			return false;
		}
		\ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );

		// Send a maximum of once per week.
		$last_send = $this->get_last_send();
		if ( \is_numeric( $last_send ) && $last_send > \strtotime( '-1 week' ) && ! $ignore_last_checkin ) {
			\ewwwio_debug_message( 'has not been a week since we last reported' );
			return false;
		}

		$this->setup_data();
		\ewwwio_debug_message( 'sending site data' );
		$request = \wp_remote_post(
			'https://optimize.exactlywww.com/stats/report.php',
			array(
				'timeout'    => 5,
				'body'       => $this->data,
				'user-agent' => 'EWWW/' . EWWW_IMAGE_OPTIMIZER_VERSION . '; ' . \get_bloginfo( 'url' ),
			)
		);

		\ewwwio_debug_message( 'finished reporting' );
		if ( \is_wp_error( $request ) ) {
			$error_message = $request->get_error_message();
			\ewwwio_debug_message( "check-in failed: $error_message" );
			return $request;
		}

		\ewwwio_debug_message( 'no error, recording time sent' );
		\ewww_image_optimizer_set_option( 'ewww_image_optimizer_tracking_last_send', \time() );

		return true;
	}

	/**
	 * Check for a new opt-in on settings save
	 *
	 * @param bool $input The tracking setting.
	 * @return bool The unaltered setting.
	 */
	public function check_for_settings_optin( $input ) {
		\ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Send an intial check in on settings save.
		if ( $input ) {
			$this->send_checkin( true );
			\ewww_image_optimizer_set_option( 'ewww_image_optimizer_tracking_notice', 1 );
		}
		return (bool) $input;
	}

	/**
	 * Check for a new opt-in via the admin notice
	 */
	public function check_for_optin() {
		\check_admin_referer( 'ewww_image_optimizer_options-options' );
		$permissions = \apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' );
		if ( ! \current_user_can( $permissions ) ) {
			\wp_die( \esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		\ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		\ewww_image_optimizer_set_option( 'ewww_image_optimizer_allow_tracking', 1 );
		$this->send_checkin( true );
		\ewww_image_optimizer_set_option( 'ewww_image_optimizer_tracking_notice', 1 );
		\wp_safe_redirect( \remove_query_arg( 'action', \wp_get_referer() ) );
		exit;
	}

	/**
	 * Check for a new opt-out via the admin notice
	 */
	public function check_for_optout() {
		\check_admin_referer( 'ewww_image_optimizer_options-options' );
		$permissions = \apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' );
		if ( ! \current_user_can( $permissions ) ) {
			\wp_die( \esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		\ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		\delete_option( 'ewww_image_optimizer_allow_tracking' );
		\delete_network_option( null, 'ewww_image_optimizer_allow_tracking' );
		\ewww_image_optimizer_set_option( 'ewww_image_optimizer_tracking_notice', 1 );
		\wp_safe_redirect( \remove_query_arg( 'action', \wp_get_referer() ) );
		exit;
	}

	/**
	 * Get the last time a checkin was sent
	 *
	 * @access private
	 * @return false|string
	 */
	private function get_last_send() {
		\ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		return \ewww_image_optimizer_get_option( 'ewww_image_optimizer_tracking_last_send' );
	}

	/**
	 * Schedule a weekly checkin.
	 */
	public function schedule_send() {
		\ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( \is_multisite() ) {
			if ( ! \function_exists( '\is_plugin_active_for_network' ) ) {
				// Need to include the plugin library for the is_plugin_active function.
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if ( \is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) && \get_current_blog_id() > 1 ) {
				return;
			}
		}
		// We send once a week (while tracking is allowed) to check in, which can be used to determine active sites.
		if ( \ewww_image_optimizer_get_option( 'ewww_image_optimizer_allow_tracking' ) && ! \wp_next_scheduled( 'ewww_image_optimizer_site_report' ) ) {
			\ewwwio_debug_message( 'scheduling checkin' );
			\wp_schedule_event( \time(), \apply_filters( 'ewww_image_optimizer_schedule', 'daily', 'ewww_image_optimizer_site_report' ), 'ewww_image_optimizer_site_report' );
		} elseif ( \ewww_image_optimizer_get_option( 'ewww_image_optimizer_allow_tracking' ) ) {
			\ewwwio_debug_message( 'checkin already scheduled: ' . \wp_next_scheduled( 'ewww_image_optimizer_site_report' ) );
		} elseif ( \wp_next_scheduled( 'ewww_image_optimizer_site_report' ) ) {
			\ewwwio_debug_message( 'un-scheduling checkin' );
			\wp_clear_scheduled_hook( 'ewww_image_optimizer_site_report' );
			if ( ! \function_exists( '\is_plugin_active_for_network' ) && \is_multisite() ) {
				// Need to include the plugin library for the is_plugin_active function.
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if ( \is_multisite() && get_current_blog_id() > 1 && \is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
				\switch_to_blog( 1 );
				\wp_clear_scheduled_hook( 'ewww_image_optimizer_site_report' );
				\restore_current_blog();
			}
		}
	}

	/**
	 * Un-schedule the weekly checkin.
	 */
	public function unschedule_send() {
		\wp_clear_scheduled_hook( 'ewww_image_optimizer_site_report' );
	}

	/**
	 * Display the admin notice to users that have not opted-in or out
	 *
	 * @access public
	 * @return void
	 */
	public function admin_notice() {
		return;
		// If network-active and network notice is hidden, or single-active and single site notice has been hidden, don't show it again.
		$hide_notice = ewww_image_optimizer_get_option( 'ewww_image_optimizer_tracking_notice' );
		// But what if they allow overrides? Then the above was checking single-site settings, so we need to check the network admin.
		if ( is_multisite() && get_site_option( 'ewww_image_optimizer_allow_multisite_override' ) && get_site_option( 'ewww_image_optimizer_tracking_notice' ) ) {
			$hide_notice = true;
		}

		if ( $hide_notice ) {
			return;
		}
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_allow_tracking' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );

		if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
			// Need to include the plugin library for the is_plugin_active function.
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) && ! current_user_can( 'manage_network_options' ) ) {
			return;
		}
		if ( is_multisite() && ! is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) && is_network_admin() ) {
			return;
		}
		if (
			stristr( network_site_url( '/' ), '.local' ) !== false ||
			stristr( network_site_url( '/' ), 'dev' ) !== false ||
			stristr( network_site_url( '/' ), 'localhost' ) !== false ||
			stristr( network_site_url( '/' ), ':8888' ) !== false // This is common with MAMP on OS X.
		) {
			ewww_image_optimizer_set_option( 'ewww_image_optimizer_tracking_notice', 1 );
		} else {
			$admin_email = '<strong>' . get_bloginfo( 'admin_email' ) . '</strong>';
			echo '<div class="updated"><p>';
				/* translators: %s: admin email as configured in settings */
				printf( esc_html__( 'Allow EWWW Image Optimizer to track plugin usage? Opt-in to tracking and receive 500 free image credits in your admin email: %s. No sensitive data is tracked.', 'ewww-image-optimizer' ), wp_kses_post( $admin_email ) );
				echo '&nbsp;<a href="https://docs.ewww.io/article/23-usage-tracking" target="_blank" data-beacon-article="591f3a8e2c7d3a057f893d91">' . esc_html__( 'Learn more.', 'ewww-image-optimizer' ) . '</a>';
				echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_opt_into_tracking' ), 'ewww_image_optimizer_options-options' ) ) . '" class="button-secondary" style="margin-left:5px;">' . esc_html__( 'Allow', 'ewww-image-optimizer' ) . '</a>';
				echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_opt_out_of_tracking' ), 'ewww_image_optimizer_options-options' ) ) . '" class="button-secondary" style="margin-left:5px">' . esc_html__( 'Do not allow', 'ewww-image-optimizer' ) . '</a>';
			echo '</p></div>';
		}
	}
}
