<?php
/**
 * Implements basic page parsing functions.
 *
 * @link https://ewww.io
 * @package EIO
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTML element and attribute parsing, replacing, etc.
 */
final class Admin_Notices extends Base {

	/**
	 * Register the loader for the admin notices.
	 */
	public function __construct() {
		parent::__construct();

		\add_action( 'load-index.php', array( $this, 'load_notices' ) );
		\add_action( 'load-upload.php', array( $this, 'load_notices' ), 9 );
		\add_action( 'load-media-new.php', array( $this, 'load_notices' ) );
		\add_action( 'load-media_page_ewww-image-optimizer-bulk', array( $this, 'load_notices' ) );
		\add_action( 'load-plugins.php', array( $this, 'load_notices' ) );
		\add_action( 'admin_notices', array( $this, 'thumbnail_regen_notice' ) );
		\add_action( 'network_admin_notices', array( $this, 'easyio_site_initialized_notice' ) );

		// Prevent Autoptimize from displaying its image optimization notice.
		\remove_action( 'admin_notices', 'autoptimizeMain::notice_plug_imgopt' );

		$this->register_action_handlers();
	}

	/**
	 * Register the admin notices, and add the actions to display them.
	 */
	public function load_notices() {
		\add_action( 'network_admin_notices', array( $this, 'display_notices' ) );
		\add_action( 'admin_notices', array( $this, 'display_notices' ) );
	}

	/**
	 * Register the handlers for dismissing notices.
	 */
	public function register_action_handlers() {
		// AJAX action hook to dismiss the UTF-8 notice.
		\add_action( 'wp_ajax_ewww_dismiss_utf8_notice', array( $this, 'dismiss_utf8_notice' ) );
		// AJAX action hook to dismiss the exec notice and other related notices.
		\add_action( 'wp_ajax_ewww_dismiss_exec_notice', array( $this, 'dismiss_exec_notice' ) );
		// AJAX action hook to dismiss the WooCommerce regen notice.
		\add_action( 'wp_ajax_ewww_dismiss_wc_regen', array( $this, 'dismiss_wc_regen_notice' ) );
		// AJAX action hook to dismiss the WP/LR Sync regen notice.
		\add_action( 'wp_ajax_ewww_dismiss_lr_sync', array( $this, 'dismiss_lightroom_sync_notice' ) );
		// AJAX action hook to disable the media library notice.
		\add_action( 'wp_ajax_ewww_dismiss_media_notice', array( $this, 'dismiss_media_notice' ) );
		// AJAX action hook to disable the 'review request' notice.
		\add_action( 'wp_ajax_ewww_dismiss_review_notice', array( $this, 'dismiss_review_notice' ) );
		// AJAX action hook to disable the newsletter signup banner.
		\add_action( 'wp_ajax_ewww_dismiss_newsletter', array( $this, 'dismiss_newsletter_signup_notice' ) );
		// Non-AJAX handler to disable debugging mode.
		\add_action( 'admin_action_ewww_disable_debugging', array( $this, 'disable_debugging' ) );
		// Non-AJAX handler to disable debugging mode.
		\add_action( 'admin_action_ewww_disable_test_mode', array( $this, 'disable_test_mode' ) );
	}

	/**
	 * Hook to display notices on the admin pages.
	 */
	public function display_notices() {
		$admin_permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', '' );
		if ( current_user_can( $admin_permissions ) ) {
			$this->test_mode_notice();
			$this->debug_mode_notice();
			$this->utils_notice();
			$this->os_notice();
			$this->hosting_requires_api_notice();
			$this->utf8_db_notice();
			$this->upgrade_620_notice();
			$this->invalid_key_notice();
			$this->schedule_noasync_notice();
			$this->php_notice();
			$this->exactdn_hmwp_notice();
			$this->review_appreciated_notice();
			$this->pngout_installed_notice();
			$this->svgcleaner_installed_notice();
			$this->agr_notice();
			if ( is_network_admin() ) {
				// If we are on the network admin, display network notices.
				\do_action( 'ewwwio_network_admin_notices' );
			} else {
				// Otherwise, display regular admin notices.
				$this->webp_bulk_notice();
				$this->exactdn_sp_conflict_notice();
				$this->media_listmode_notice();
				$this->wc_regen_notice();
				$this->lightroom_sync_notice();
				\do_action( 'ewwwio_admin_notices' );
			}
		}
	}

	/**
	 * Display admin notice for test mode.
	 */
	protected function test_mode_notice() {
		if ( ! $this->get_option( 'ewww_image_optimizer_test_mode' ) ) {
			return;
		}
		?>
		<div class="notice notice-info">
			<p>
				<?php esc_html_e( 'EWWW Image Optimizer is currently in Test Mode. Please be sure to disable Test Mode when you are done troubleshooting.', 'ewww-image-optimizer' ); ?>
				<a class='button button-secondary' href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_disable_test_mode' ), 'ewww_image_optimizer_options-options' ) ); ?>'>
					<?php esc_html_e( 'Disable Test Mode', 'ewww-image-optimizer' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Display a notice that debugging mode is enabled.
	 */
	protected function debug_mode_notice() {
		if ( ! $this->get_option( 'ewww_image_optimizer_debug' ) ) {
			return;
		}
		?>
		<div id="ewww-image-optimizer-notice-debug" class="notice notice-info">
			<p>
				<?php esc_html_e( 'Debug mode is enabled in the EWWW Image Optimizer settings. Please be sure to turn Debugging off when you are done troubleshooting.', 'ewww-image-optimizer' ); ?>
				<a class='button button-secondary' href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_disable_debugging' ), 'ewww_image_optimizer_options-options' ) ); ?>'>
					<?php esc_html_e( 'Disable Debugging', 'ewww-image-optimizer' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Disables the debugging option.
	 */
	public function disable_debugging() {
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
	 * Disables the test mode option.
	 */
	public function disable_test_mode() {
		if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		update_option( 'ewww_image_optimizer_test_mode', false );
		update_site_option( 'ewww_image_optimizer_test_mode', false );
		$sendback = wp_get_referer();
		wp_safe_redirect( $sendback );
		exit;
	}

	/**
	 * Alert the user when the database connection does not appear to be using utf8.
	 */
	public function utf8_db_notice() {
		global $wpdb;
		if ( $this->get_option( 'ewww_image_optimizer_dismiss_utf8' ) || str_contains( $wpdb->charset, 'utf8' ) ) {
			if ( ! $this->get_option( 'ewww_image_optimizer_dismiss_utf8' ) && str_contains( $wpdb->charset, 'utf8' ) ) {
				$this->set_option( 'ewww_image_optimizer_dismiss_utf8', true );
			}
			return;
		}
		?>
		<div id='ewww-image-optimizer-warning-utf8-db-connection' class='notice notice-warning is-dismissible'>
			<p>
				<strong><?php \esc_html_e( 'The database connection for your site does not appear to be using UTF-8.', 'ewww-image-optimizer' ); ?></strong>
				<?php \esc_html_e( 'EWWW Image Optimizer may not properly process images with non-English filenames.', 'ewww-image-optimizer' ); ?>
			</p>
		</div>
		<?php
		$this->display_utf8_dismiss_script();
	}

	/**
	 * Outputs the script to dismiss the 'utf8' notice.
	 */
	protected function display_utf8_dismiss_script() {
		?>
		<script>
			jQuery(document).on('click', '#ewww-image-optimizer-warning-utf8-db-connection .notice-dismiss', function() {
				var ewww_dismiss_utf8_data = {
					action: 'ewww_dismiss_utf8_notice',
					_wpnonce: <?php echo wp_json_encode( wp_create_nonce( 'ewww-image-optimizer-notice' ) ); ?>,
				};
				jQuery.post(ajaxurl, ewww_dismiss_utf8_data, function(response) {
					if (response) {
						console.log(response);
					}
				});
			});
		</script>
		<?php
	}

	/**
	 * Instruct the user to run the db upgrade.
	 */
	public function upgrade_620_notice() {
		// Let the admin know a db upgrade is needed.
		if ( ! \is_super_admin() || ! \get_transient( 'ewww_image_optimizer_620_upgrade_needed' ) ) {
			return;
		}
		echo "<div id='ewww-image-optimizer-upgrade-notice' class='notice notice-info'><p>" .
			esc_html__( 'EWWW Image Optimizer needs to upgrade the image log table.', 'ewww-image-optimizer' ) . '<br>' .
			'<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_620_upgrade' ), 'ewww_image_optimizer_options-options' ) ) . '" class="button-secondary">' .
			esc_html__( 'Upgrade', 'ewww-image-optimizer' ) . '</a>' .
			'</p></div>';
	}

	/**
	 * Let the user know their key is invalid.
	 */
	public function invalid_key_notice() {
		if ( ! \defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD_KEY' ) || ! \get_option( 'ewww_image_optimizer_cloud_key_invalid' ) ) {
			return;
		}
		echo "<div id='ewww-image-optimizer-invalid-key' class='notice notice-error'><p><strong>" . esc_html__( 'Could not validate EWWW Image Optimizer API key, please check your key to ensure it is correct.', 'ewww-image-optimizer' ) . '</strong></p></div>';
	}

	/**
	 * Display a notice that the user should run the bulk optimizer immediately after WebP activation.
	 */
	public function webp_bulk_notice() {
		if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			return;
		}
		if ( ! $this->get_option( 'ewww_image_optimizer_webp_enabled' ) ) {
			return;
		}
		if ( \ewww_image_optimizer_cloud_based_media() ) {
			\ewww_image_optimizer_set_option( 'ewww_image_optimizer_webp_force', true );
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
				),
				$bulk_link
			);
			$bulk_link = wp_nonce_url( $bulk_link, 'ewww_image_optimizer_options-options' );
			echo "<div id='ewww-image-optimizer-webp-generate' class='notice notice-info is-dismissible'><p><a href='" .
				esc_url( $bulk_link ) .
				"'>" . esc_html__( 'It looks like you already started optimizing your images, you will need to generate WebP images via the Bulk Optimizer.', 'ewww-image-optimizer' ) . '</a></p></div>';
		} else {
			echo "<div id='ewww-image-optimizer-webp-generate' class='notice notice-info is-dismissible'><p><a href='" .
				esc_url( $bulk_link ) .
				"'>" . esc_html__( 'Use the Bulk Optimizer to generate WebP images for existing uploads.', 'ewww-image-optimizer' ) . '</a></p></div>';
		}
		delete_option( 'ewww_image_optimizer_webp_enabled' );
	}

	/**
	 * Warn the user that scheduled optimization will no longer work without background/async mode.
	 */
	public function schedule_noasync_notice() {
		if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			return;
		}
		if ( ! $this->get_option( 'ewww_image_optimizer_auto' ) || \ewww_image_optimizer_background_mode_enabled() ) {
			return;
		}
		global $ewwwio_upgrading;
		if ( $ewwwio_upgrading ) {
			return;
		}
		echo "<div id='ewww-image-optimizer-schedule-noasync' class='notice notice-warning'><p>" . esc_html__( 'Scheduled Optimization will not work without background/async ability. See the EWWW Image Optimizer Settings for further instructions.', 'ewww-image-optimizer' ) . '</p></div>';
	}

	/**
	 * Inform the user that we disabled SP AIO to prevent conflicts with ExactDN.
	 */
	public function exactdn_sp_conflict_notice() {
		if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			return;
		}
		// Prevent ShortPixel AIO messiness.
		if ( ! \class_exists( '\autoptimizeExtra' ) && ! \defined( 'AUTOPTIMIZE_PLUGIN_VERSION' ) ) {
			return;
		}
		$ao_extra = \get_option( 'autoptimize_imgopt_settings' );
		if ( ! $this->get_option( 'ewww_image_optimizer_exactdn' ) || empty( $ao_extra['autoptimize_imgopt_checkbox_field_1'] ) ) {
			return;
		}

		$this->debug_message( 'detected ExactDN + SP conflict' );
		$ao_extra['autoptimize_imgopt_checkbox_field_1'] = 0;
		\update_option( 'autoptimize_imgopt_settings', $ao_extra );
		echo "<div id='ewww-image-optimizer-exactdn-sp' class='notice notice-warning'><p>" . esc_html__( 'ShortPixel image optimization has been disabled to prevent conflicts with Easy IO (EWWW Image Optimizer).', 'ewww-image-optimizer' ) . '</p></div>';
	}

	/**
	 * Display a notice that PHP version 8.1 will be required in a future version.
	 */
	public function php_notice() {
		if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			return;
		}
		// Increase the version when the next bump is coming.
		if ( ! \defined( 'PHP_VERSION_ID' ) || PHP_VERSION_ID >= 70400 ) {
			return;
		}
		echo '<div id="ewww-image-optimizer-notice-php" class="notice notice-info"><p><a href="https://docs.ewww.io/article/55-upgrading-php" target="_blank" data-beacon-article="5ab2baa6042863478ea7c2ae">' . esc_html__( 'The next release of EWWW Image Optimizer will require PHP 8.1 or greater. Newer versions of PHP are significantly faster and much more secure. If you are unsure how to upgrade to a supported version, ask your webhost for instructions.', 'ewww-image-optimizer' ) . '</a></p></div>';
	}

	/**
	 * Tell the user to disable Hide my WP function that removes query strings.
	 */
	public function exactdn_hmwp_notice() {
		if (
			! \method_exists( '\HMWP_Classes_Tools', 'getOption' ) ||
			! $this->get_option( 'ewww_image_optimizer_exactdn' ) ||
			! \HMWP_Classes_Tools::getOption( 'hmwp_hide_version' ) ||
			\HMWP_Classes_Tools::getOption( 'hmwp_hide_version_random' )
		) {
			return;
		}
		$this->debug_message( 'detected HMWP Hide Version' );
		?>
		<div id='ewww-image-optimizer-warning-hmwp-hide-version' class='notice notice-warning'>
			<p>
				<?php \esc_html_e( 'Please enable the Random Static Number option in Hide My WP to ensure compatibility with Easy IO or disable the Hide Version option for best performance.', 'ewww-image-optimizer' ); ?>
				<?php \ewwwio_help_link( 'https://docs.ewww.io/article/50-exactdn-and-query-strings', '5a3d278a2c7d3a1943677b52' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Let the user know they can view more options and stats in the Media Library's list mode.
	 */
	public function media_listmode_notice() {
		if (
			! $this->get_option( 'ewww_image_optimizer_ludicrous_mode' ) &&
			! $this->get_option( 'ewww_image_optimizer_cloud_key' ) &&
			\ewww_image_optimizer_easy_active()
		) {
			return;
		}
		$current_screen = get_current_screen();
		if ( 'upload' === $current_screen->id && ! $this->get_option( 'ewww_image_optimizer_dismiss_media_notice' ) ) {
			if ( 'list' === get_user_option( 'media_library_mode', get_current_user_id() ) ) {
				\update_option( 'ewww_image_optimizer_dismiss_media_notice', true, false );
				\update_site_option( 'ewww_image_optimizer_dismiss_media_notice', true );
				return;
			}
			?>
			<div id='ewww-image-optimizer-media-listmode' class='notice notice-info is-dismissible'>
				<p>
					<?php \esc_html_e( 'Change the Media Library to List mode for additional image optimization information and actions.', 'ewww-image-optimizer' ); ?>
					<?php \ewwwio_help_link( 'https://docs.ewww.io/article/62-power-user-options-in-list-mode', '5b61fdd32c7d3a03f89d41c4' ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Ask the user to leave a review for the plugin on wp.org.
	 */
	public function review_appreciated_notice() {
		if (
			! \is_super_admin() ||
			! $this->get_option( 'ewww_image_optimizer_review_time' ) ||
			$this->get_option( 'ewww_image_optimizer_review_time' ) > \time() ||
			$this->get_option( 'ewww_image_optimizer_dismiss_review_notice' )
		) {
			return;
		}
		?>
		<div id='ewww-image-optimizer-review' class='notice notice-info is-dismissible'>
			<p>
				<?php \esc_html_e( "Hi, you've been using the EWWW Image Optimizer for a while, and we hope it has been a big help for you.", 'ewww-image-optimizer' ); ?><br>
				<?php \esc_html_e( 'If you could take a few moments to rate it on WordPress.org, we would really appreciate your help making the plugin better. Thanks!', 'ewww-image-optimizer' ); ?><br>
				<a target="_blank" href="https://wordpress.org/support/plugin/ewww-image-optimizer/reviews/#new-post" class="button-secondary">
					<?php \esc_html_e( 'Post Review', 'ewww-image-optimizer' ); ?>
				</a>
			</p>
		</div>
		<?php
		$this->review_appreciated_notice_script();
	}

	/**
	 * Loads the inline script to dismiss the review notice.
	 */
	public function review_appreciated_notice_script() {
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
	 * Informs the user about optimization during thumbnail regeneration.
	 */
	public function thumbnail_regen_notice() {
		if ( empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( 'regenerate-thumbnails' !== $_GET['page'] && // phpcs:ignore WordPress.Security.NonceVerification
			'force-regenerate-thumbnails' !== $_GET['page'] && // phpcs:ignore WordPress.Security.NonceVerification
			'ajax-thumbnail-rebuild' !== $_GET['page'] && // phpcs:ignore WordPress.Security.NonceVerification
			'regenerate_thumbnails_advanced' !== $_GET['page'] && // phpcs:ignore WordPress.Security.NonceVerification
			'rta_generate_thumbnails' !== $_GET['page'] // phpcs:ignore WordPress.Security.NonceVerification
		) {
			return;
		}
		?>
		<div id='ewww-image-optimizer-thumb-regen-notice' class='notice notice-info is-dismissible'>
			<p>
				<strong><?php \esc_html_e( 'New thumbnails will be optimized by the EWWW Image Optimizer as they are generated. You may wish to disable the plugin and run a bulk optimize later to speed up the process.', 'ewww-image-optimizer' ); ?></strong>
				<a href="https://docs.ewww.io/article/49-regenerate-thumbnails" target="_blank" data-beacon-article="5a0f84ed2c7d3a272c0dc801">
					<?php \esc_html_e( 'Learn more.', 'ewww-image-optimizer' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Display a success or failure message after PNGOUT installation.
	 */
	public function pngout_installed_notice() {
		if ( empty( $_GET['ewww_pngout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( 'success' === $_GET['ewww_pngout'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			?>
			<div id='ewww-image-optimizer-pngout-success' class='notice notice-success fade'>
				<p>
					<?php \esc_html_e( 'Pngout was successfully installed.', 'ewww-image-optimizer' ); ?>
				</p>
			</div>
			<?php
		}
		if ( 'failed' === $_GET['ewww_pngout'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			?>
			<div id='ewww-image-optimizer-pngout-failure' class='notice notice-error'>
				<p>
					<?php
					\printf(
						/* translators: 1: An error message 2: The folder where pngout should be installed */
						\esc_html__( 'Pngout was not installed: %1$s. Make sure this folder is writable: %2$s', 'ewww-image-optimizer' ),
						( ! empty( $_GET['ewww_error'] ) ? \esc_html( \sanitize_text_field( \wp_unslash( $_GET['ewww_error'] ) ) ) : \esc_html__( 'unknown error', 'ewww-image-optimizer' ) ), // phpcs:ignore WordPress.Security.NonceVerification
						\esc_html( EWWW_IMAGE_OPTIMIZER_TOOL_PATH )
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Display a success or failure message after SVGCLEANER installation.
	 */
	public function svgcleaner_installed_notice() {
		if ( empty( $_GET['ewww_svgcleaner'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( 'success' === $_GET['ewww_svgcleaner'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			?>
			<div id='ewww-image-optimizer-pngout-success' class='notice notice-success fade'>
				<p>
					<?php \esc_html_e( 'Svgcleaner was successfully installed.', 'ewww-image-optimizer' ); ?>
				</p>
			</div>
			<?php
		}
		if ( 'failed' === $_GET['ewww_svgcleaner'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			?>
			<div id='ewww-image-optimizer-pngout-failure' class='notice notice-error'>
				<p>
					<?php
					\printf(
						/* translators: 1: An error message 2: The folder where svgcleaner should be installed */
						\esc_html__( 'Svgcleaner was not installed: %1$s. Make sure this folder is writable: %2$s', 'ewww-image-optimizer' ),
						( ! empty( $_GET['ewww_error'] ) ? \esc_html( \sanitize_text_field( \wp_unslash( $_GET['ewww_error'] ) ) ) : \esc_html__( 'unknown error', 'ewww-image-optimizer' ) ), // phpcs:ignore WordPress.Security.NonceVerification
						\esc_html( EWWW_IMAGE_OPTIMIZER_TOOL_PATH )
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Remind network admin to activate Easy IO on the new site.
	 */
	public function easyio_site_initialized_notice() {
		if (
			! \str_contains( \add_query_arg( '', '' ), 'site-new.php' ) ||
			empty( $_GET['id'] )// phpcs:ignore WordPress.Security.NonceVerification
		) {
			return;
		}
		if ( defined( 'EASYIO_NEW_SITE_AUTOREG' ) && EASYIO_NEW_SITE_AUTOREG && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
			?>
			<div id="ewww-image-optimizer-notice-exactdn-success" class="notice notice-info"><p>
				<?php esc_html_e( 'Easy IO registration is complete. Visit the plugin settings to activate your new site.', 'ewww-image-optimizer' ); ?>
			</div>
			<?php
		} elseif ( get_option( 'ewww_image_optimizer_exactdn' ) ) {
			?>
			<div id="ewww-image-optimizer-notice-exactdn-success" class="notice notice-info"><p>
				<?php esc_html_e( 'Please visit the EWWW Image Optimizer plugin settings to activate Easy IO on your new site.', 'ewww-image-optimizer' ); ?>
			</div>
			<?php
		}
	}

	/**
	 * Lets the user know WooCommerce has regenerated thumbnails and that they need to take action.
	 */
	public function wc_regen_notice() {
		if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			return;
		}
		if ( ! \class_exists( '\WooCommerce' ) || ! $this->get_option( 'ewww_image_optimizer_wc_regen' ) ) {
			return;
		}
		?>
		<div id='ewww-image-optimizer-wc-regen' class='notice notice-info is-dismissible'>
			<p>
				<?php \esc_html_e( 'EWWW Image Optimizer has detected a WooCommerce thumbnail regeneration. To optimize new thumbnails, you may run the Bulk Optimizer from the Media menu. This notice may be dismissed after the regeneration is complete.', 'ewww-image-optimizer' ); ?>
			</p>
		</div>
		<?php
		$this->wc_regen_script();
	}

	/**
	 * Loads the inline script to dismiss the WC regen notice.
	 */
	public function wc_regen_script() {
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
	public function lightroom_sync_notice() {
		if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			return;
		}
		if ( ! \class_exists( '\Meow_WPLR_Sync_Core' ) || ! $this->get_option( 'ewww_image_optimizer_lr_sync' ) ) {
			return;
		}
		?>
		<div id='ewww-image-optimizer-lr-sync' class='notice notice-info is-dismissible'>
			<p>
				<?php \esc_html_e( 'EWWW Image Optimizer has detected a WP/LR Sync process. To optimize new thumbnails, you may run the Bulk Optimizer from the Media menu. This notice may be dismissed after the Sync process is complete.', 'ewww-image-optimizer' ); ?>
			</p>
		</div>
		<?php
		$this->lightroom_sync_script();
	}

	/**
	 * Loads the inline script to dismiss the LR sync notice.
	 */
	public function lightroom_sync_script() {
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
	public function agr_notice() {
		if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			return;
		}
		if ( ! \class_exists( '\Bbpp_Animated_Gif' ) ) {
			return;
		}
		?>
		<div id="ewww-image-optimizer-notice-agr" class="notice notice-warning">
			<p>
				<?php \esc_html_e( 'GIF animations are preserved by EWWW Image Optimizer automatically. Please remove the Animated GIF Resize plugin.', 'ewww-image-optimizer' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Checks for exec() and availability of local optimizers, then displays an error if needed.
	 *
	 * @param string $quiet Optional. Use 'quiet' to suppress output.
	 */
	public function utils_notice( $quiet = null ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ewwwio()->cloud_mode ) {
			return;
		}
		if ( ewwwio()->local->hosting_requires_api() ) {
			return;
		}
		if ( ! ewwwio()->local->os_supported() ) {
			return;
		}
		// Check if exec is disabled.
		if ( ! ewwwio()->local->exec_check() ) {
			// Don't bother if we're in quiet mode, or they already dismissed the notice.
			if ( 'quiet' !== $quiet && ! $this->get_option( 'ewww_image_optimizer_dismiss_exec_notice' ) ) {
				\ob_start();
				// Display a warning if exec() is disabled, can't run local tools without it.
				if ( \ewww_image_optimizer_easy_active() ) {
					echo "<div id='ewww-image-optimizer-warning-exec' class='notice notice-info is-dismissible'><p>";
					\esc_html_e( 'Compression of local images cannot be done on your site without an API key. Since Easy IO is already automatically optimizing your site, you may dismiss this notice unless you need to save storage space.', 'ewww-image-optimizer' );
				} else {
					echo "<div id='ewww-image-optimizer-warning-exec' class='notice notice-warning is-dismissible'><p>";
					\printf(
						/* translators: %s: link to 'start your premium trial' */
						\esc_html__( 'Your web server does not meet the requirements for free server-based compression with EWWW Image Optimizer. You may %s for 5x more compression, PNG/GIF/PDF compression, and more. Otherwise, continue with free cloud-based JPG compression.', 'ewww-image-optimizer' ),
						"<a href='https://ewww.io/plans/'>" . \esc_html__( 'start your premium trial', 'ewww-image-optimizer' ) . '</a>'
					);
				}
				echo '&nbsp;';
				\ewwwio_help_link( 'https://docs.ewww.io/article/29-what-is-exec-and-why-do-i-need-it', '592dd12d0428634b4a338c39' );
				echo '</p></div>';
				$this->display_exec_dismiss_script();
				if (
					\ewww_image_optimizer_easy_active() &&
					! $this->get_option( 'ewww_image_optimizer_ludicrous_mode' )
				) {
					\ob_end_clean();
				} else {
					\ob_end_flush();
				}
				$this->debug_message( 'exec disabled, alerting user' );
			}
			return;
		}

		$tools   = ewwwio()->local->check_all_tools();
		$missing = array();
		// Go through each of the required tools.
		foreach ( $tools as $tool => $info ) {
			// If a tool is needed, but wasn't found, add it to the $missing so we can display that info to the user.
			if ( $info['enabled'] && empty( $info['path'] ) ) {
				if ( 'cwebp' === $tool && ( $this->imagick_supports_webp() || $this->gd_supports_webp() ) ) {
					continue;
				}
				ewwwio()->local->tools_missing = true;
				$missing[]                     = $tool;
			}
		}
		// If there is a message, display the warning.
		if ( ! empty( $missing ) && 'quiet' !== $quiet ) {
			if ( ! \is_dir( $this->content_dir ) ) {
				$this->tool_folder_notice();
			} elseif ( ! \is_writable( $this->content_dir ) || ! is_readable( $this->content_dir ) ) {
				$this->tool_folder_permissions_notice();
			} elseif ( ! \is_executable( $this->content_dir ) && PHP_OS !== 'WINNT' ) {
				$this->tool_folder_permissions_notice();
			}
			if ( \in_array( 'pngout', $missing, true ) ) {
				// Display a separate notice for pngout with an install option, and then suppress it from the latter notice.
				$key = \array_search( 'pngout', $missing, true );
				if ( false !== $key ) {
					unset( $missing[ $key ] );
				}
				echo "<div id='ewww-image-optimizer-warning-opt-missing' class='notice notice-warning'><p>" .
				\sprintf(
					/* translators: 1: automatically (link) 2: manually (link) */
					\esc_html__( 'EWWW Image Optimizer is missing pngout. Install %1$s or %2$s.', 'ewww-image-optimizer' ),
					"<a href='" . \esc_url( \wp_nonce_url( \admin_url( 'admin.php?action=ewww_image_optimizer_install_pngout' ), 'ewww_image_optimizer_options-options' ) ) . "'>" . \esc_html__( 'automatically', 'ewww-image-optimizer' ) . '</a>',
					'<a href="https://docs.ewww.io/article/13-installing-pngout" data-beacon-article="5854531bc697912ffd6c1afa">' . \esc_html__( 'manually', 'ewww-image-optimizer' ) . '</a>'
				) .
				'</p></div>';
			}
			if ( \in_array( 'svgcleaner', $missing, true ) ) {
				$key = array_search( 'svgcleaner', $missing, true );
				if ( false !== $key ) {
					unset( $missing[ $key ] );
				}
				echo "<div id='ewww-image-optimizer-warning-opt-missing' class='notice notice-warning'><p>" .
				\sprintf(
					/* translators: 1: automatically (link) 2: manually (link) */
					\esc_html__( 'EWWW Image Optimizer is missing svgleaner. Install %1$s or %2$s.', 'ewww-image-optimizer' ),
					"<a href='" . \esc_url( \wp_nonce_url( \admin_url( 'admin.php?action=ewww_image_optimizer_install_svgcleaner' ), 'ewww_image_optimizer_options-options' ) ) . "'>" . \esc_html__( 'automatically', 'ewww-image-optimizer' ) . '</a>',
					'<a href="https://docs.ewww.io/article/95-installing-svgcleaner" data-beacon-article="5f7921c9cff47e001a58adbc">' . \esc_html__( 'manually', 'ewww-image-optimizer' ) . '</a>'
				) .
				'</p></div>';
			}
			if ( ! empty( $missing ) && ! $this->get_option( 'ewww_image_optimizer_dismiss_exec_notice' ) ) {
				$dismissible = false;
				// If all the tools are missing, make it dismissible.
				if (
					\in_array( 'jpegtran', $missing, true ) &&
					\in_array( 'optipng', $missing, true ) &&
					\in_array( 'gifsicle', $missing, true )
				) {
					$dismissible = true;
				}
				// If they are missing tools, but not jpegtran, make it dismissible, since they can effectively do locally what we would offer in free-cloud mode.
				if ( ! \in_array( 'jpegtran', $missing, true ) ) {
					$dismissible = true;
				}
				// Expand the missing utilities list for use in the error message.
				$msg = \implode( ', ', $missing );
				echo "<div id='ewww-image-optimizer-warning-opt-missing' class='notice notice-warning" . ( $dismissible ? ' is-dismissible' : '' ) . "'><p>" .
				\sprintf(
					/* translators: 1: comma-separated list of missing tools 2: Installation Instructions (link) */
					\esc_html__( 'EWWW Image Optimizer uses open-source tools to enable free mode, but your server is missing these: %1$s. Please install via the %2$s to continue in free mode.', 'ewww-image-optimizer' ),
					\esc_html( $msg ),
					"<a href='https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something' data-beacon-article='585371e3c697912ffd6c0ba1' target='_blank'>" . \esc_html__( 'Installation Instructions', 'ewww-image-optimizer' ) . '</a>'
				) .
				'</p></div>';
				?>
	<script>
		jQuery(document).on('click', '#ewww-image-optimizer-warning-opt-missing .notice-dismiss', function() {
			var ewww_dismiss_exec_data = {
				action: 'ewww_dismiss_exec_notice',
			};
			jQuery.post(ajaxurl, ewww_dismiss_exec_data, function(response) {
				if (response) {
					console.log(response);
				}
			});
		});
	</script>
				<?php
			}
		}
	}

	/**
	 * Outputs the script to dismiss the 'exec' notice.
	 */
	public function display_exec_dismiss_script() {
		?>
		<script>
			jQuery(document).on('click', '#ewww-image-optimizer-warning-exec .notice-dismiss', function() {
				var ewww_dismiss_exec_data = {
					action: 'ewww_dismiss_exec_notice',
					_wpnonce: <?php echo wp_json_encode( wp_create_nonce( 'ewww-image-optimizer-notice' ) ); ?>,
				};
				jQuery.post(ajaxurl, ewww_dismiss_exec_data, function(response) {
					if (response) {
						console.log(response);
					}
				});
			});
		</script>
		<?php
	}

	/**
	 * Alert the user when the tool folder could not be created.
	 */
	public function tool_folder_notice() {
		echo "<div id='ewww-image-optimizer-warning-tool-folder-create' class='notice notice-error'><p><strong>" . \esc_html__( 'EWWW Image Optimizer could not create the tool folder', 'ewww-image-optimizer' ) . ': ' . \esc_html( $this->content_dir ) . '.</strong> ' . \esc_html__( 'Please adjust permissions or create the folder', 'ewww-image-optimizer' ) . '.</p></div>';
	}

	/**
	 * Alert the user when permissions on the tool folder are insufficient.
	 */
	public function tool_folder_permissions_notice() {
		echo "<div id='ewww-image-optimizer-warning-tool-folder-permissions' class='notice notice-error'><p><strong>" .
			/* translators: %s: Folder location where executables should be installed */
			\sprintf( \esc_html__( 'EWWW Image Optimizer could not install tools in %s', 'ewww-image-optimizer' ), \esc_html( $this->content_dir ) ) . '.</strong> ' .
			\esc_html__( 'Please adjust permissions on the folder. If you have installed the tools elsewhere, use the override to skip the bundled tools.', 'ewww-image-optimizer' ) . ' ' .
			/* translators: s: Installation Instructions (link) */
			\sprintf( \esc_html__( 'For more details, see the %s.', 'ewww-image-optimizer' ), "<a href='https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something'>" . \esc_html__( 'Installation Instructions', 'ewww-image-optimizer' ) . '</a>' ) . '</p></div>';
	}

	/**
	 * Let the user know the plugin requires API/ExactDN to operate at their webhost.
	 */
	public function hosting_requires_api_notice() {
		if ( ! ewwwio()->local->hosting_requires_api() ) {
			return;
		}
		if (
			$this->get_option( 'ewww_image_optimizer_cloud_key' ) ||
			\ewww_image_optimizer_easy_active()
		) {
			return;
		}
		if ( \defined( 'WPCOMSH_VERSION' ) ) {
			$webhost = 'WordPress.com';
		} elseif ( ! empty( $_ENV['PANTHEON_ENVIRONMENT'] ) ) {
			$webhost = 'Pantheon';
		} elseif ( \defined( 'WPE_PLUGIN_VERSION' ) ) {
			$webhost = 'WP Engine';
		} elseif ( \defined( 'FLYWHEEL_CONFIG_DIR' ) ) {
			$webhost = 'Flywheel';
		} elseif ( \defined( 'KINSTAMU_VERSION' ) ) {
			$webhost = 'Kinsta';
		} elseif ( \defined( 'WPNET_INIT_PLUGIN_VERSION' ) ) {
			$webhost = 'WP NET';
		} else {
			return;
		}
		if ( $this->get_option( 'ewww_image_optimizer_dismiss_exec_notice' ) ) {
			return;
		}
		echo "<div id='ewww-image-optimizer-warning-exec' class='notice notice-warning is-dismissible'><p>" .
			/* translators: %s: Name of a web host, like WordPress.com or Pantheon. */
			\sprintf( \esc_html__( 'EWWW Image Optimizer cannot use server-based optimization on %s sites. Activate our premium service for 5x more compression, PNG/GIF/PDF compression, and image backups.', 'ewww-image-optimizer' ), \esc_html( $webhost ) ) .
			'<br><strong>' .
			/* translators: %s: link to 'start your free trial' */
			\sprintf( \esc_html__( 'Dismiss this notice to continue with free cloud-based JPG compression or %s.', 'ewww-image-optimizer' ), "<a href='https://ewww.io/plans/'>" . \esc_html__( 'start your premium trial', 'ewww-image-optimizer' ) . '</a>' );
		echo '&nbsp;';
		\ewwwio_help_link( 'https://docs.ewww.io/article/29-what-is-exec-and-why-do-i-need-it', '592dd12d0428634b4a338c39' );
		echo '</strong></p></div>';
		$this->display_exec_dismiss_script();
	}

	/**
	 * Tells the user they are on an unsupported operating system.
	 */
	public function os_notice() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( $this->get_option( 'ewww_image_optimizer_dismiss_exec_notice' ) ) {
			return;
		}
		if ( ewwwio()->local->os_supported() ) {
			return;
		}
		// If they are already using our services, or haven't gone through the wizard, exit now!
		if (
			$this->get_option( 'ewww_image_optimizer_cloud_key' ) ||
			\ewww_image_optimizer_easy_active()
		) {
			return;
		}
		echo "<div id='ewww-image-optimizer-warning-exec' class='notice notice-warning is-dismissible'><p>" .
			\esc_html__( 'Free server-based compression with EWWW Image Optimizer is only supported on Linux, FreeBSD, Mac OSX, and Windows.', 'ewww-image-optimizer' ) .
			'<br><strong>' .
			/* translators: %s: link to 'start your free trial' */
			\sprintf( \esc_html__( 'Dismiss this notice to continue with free cloud-based JPG compression or %s.', 'ewww-image-optimizer' ), "<a href='https://ewww.io/plans/'>" . \esc_html__( 'start your premium trial', 'ewww-image-optimizer' ) . '</a>' );
		echo '&nbsp;';
		\ewwwio_help_link( 'https://docs.ewww.io/article/29-what-is-exec-and-why-do-i-need-it', '592dd12d0428634b4a338c39' );
		echo '</strong></p></div>';
		$this->display_exec_dismiss_script();
	}

	/**
	 * Disables UTF-8 notice after being dismissed.
	 */
	public function dismiss_utf8_notice() {
		$this->ob_clean();
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		\check_ajax_referer( 'ewww-image-optimizer-notice' );
		// Verify that the user is properly authorized.
		if ( ! \current_user_can( \apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			\wp_die( \esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		\update_option( 'ewww_image_optimizer_dismiss_utf8', 1 );
		\update_site_option( 'ewww_image_optimizer_dismiss_utf8', 1 );
		exit;
	}

	/**
	 * Disables local compression when exec notice is dismissed.
	 */
	public function dismiss_exec_notice() {
		$this->ob_clean();
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		\check_ajax_referer( 'ewww-image-optimizer-notice' );
		// Verify that the user is properly authorized.
		if ( ! \current_user_can( \apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			\wp_die( \esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		$this->enable_free_exec();
		exit;
	}

	/**
	 * Dismisses the WC regen notice.
	 */
	public function dismiss_wc_regen_notice() {
		$this->ob_clean();
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Verify that the user is properly authorized.
		\check_ajax_referer( 'ewww-image-optimizer-notice' );
		if ( ! \current_user_can( \apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			\wp_die( \esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		\delete_option( 'ewww_image_optimizer_wc_regen' );
		die();
	}

	/**
	 * Dismisses the LR sync notice.
	 */
	public function dismiss_lightroom_sync_notice() {
		$this->ob_clean();
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Verify that the user is properly authorized.
		\check_ajax_referer( 'ewww-image-optimizer-notice' );
		if ( ! \current_user_can( \apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			\wp_die( \esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		\delete_option( 'ewww_image_optimizer_lr_sync' );
		die();
	}

	/**
	 * Disables the Media Library notice about List Mode.
	 */
	public function dismiss_media_notice() {
		$this->ob_clean();
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Verify that the user is properly authorized.
		\check_ajax_referer( 'ewww-image-optimizer-notice' );
		if ( ! \current_user_can( \apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			\wp_die( \esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		\update_option( 'ewww_image_optimizer_dismiss_media_notice', true, false );
		\update_site_option( 'ewww_image_optimizer_dismiss_media_notice', true );
		die();
	}

	/**
	 * Disables the notice about leaving a review.
	 */
	public function dismiss_review_notice() {
		$this->ob_clean();
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Verify that the user is properly authorized.
		\check_ajax_referer( 'ewww-image-optimizer-notice' );
		if ( ! \current_user_can( \apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			\wp_die( \esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		\update_option( 'ewww_image_optimizer_dismiss_review_notice', true, false );
		\update_site_option( 'ewww_image_optimizer_dismiss_review_notice', true );
		die();
	}

	/**
	 * Disables the newsletter signup banner.
	 */
	public function dismiss_newsletter_signup_notice() {
		$this->ob_clean();
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Verify that the user is properly authorized.
		\check_ajax_referer( 'ewww-image-optimizer-settings' );
		if ( ! \current_user_can( \apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			\wp_die( \esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		\update_option( 'ewww_image_optimizer_hide_newsletter_signup', true, false );
		\update_site_option( 'ewww_image_optimizer_hide_newsletter_signup', true );
		die( 'done' );
	}

	/**
	 * Put site in "free exec" mode with JPG-only API compression, and suppress the exec() notice.
	 */
	public function enable_free_exec() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		\update_option( 'ewww_image_optimizer_jpg_level', 10 );
		\update_option( 'ewww_image_optimizer_png_level', 0 );
		\update_option( 'ewww_image_optimizer_gif_level', 0 );
		\update_option( 'ewww_image_optimizer_pdf_level', 0 );
		\update_option( 'ewww_image_optimizer_svg_level', 0 );
		\update_option( 'ewww_image_optimizer_webp_level', 0 );
		\update_option( 'ewww_image_optimizer_dismiss_exec_notice', 1 );
		\update_site_option( 'ewww_image_optimizer_dismiss_exec_notice', 1 );
	}
}
