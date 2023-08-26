<?php
/**
 * Class and methods to integrate EWWW IO and GRAND FlaGallery.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// TODO: may be able to make things more efficient by not getting a new flagMeta, but get a new flagImage instead, but still probably need it to migrate data to ewwwio_images.
if ( ! class_exists( 'EWWW_Flag' ) ) {
	/**
	 * Allows EWWW to integrate with the GRAND FlaGallery plugin.
	 *
	 * Adds automatic optimization on upload, a bulk optimizer, and compression details when
	 * managing galleries.
	 */
	class EWWW_Flag {
		/**
		 * Initializes the flagallery integration.
		 */
		public function __construct() {
			add_filter( 'flag_manage_images_columns', array( $this, 'ewww_manage_images_columns' ) );
			add_action( 'flag_manage_gallery_custom_column', array( $this, 'ewww_manage_image_custom_column' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( $this, 'ewww_flag_manual_actions_script' ), 21 );
			if ( current_user_can( apply_filters( 'ewww_image_optimizer_bulk_permissions', '' ) ) ) {
				add_action( 'flag_manage_images_bulkaction', array( $this, 'ewww_manage_images_bulkaction' ) );
				add_action( 'flag_manage_galleries_bulkaction', array( $this, 'ewww_manage_galleries_bulkaction' ) );
				add_action( 'flag_manage_post_processor_images', array( $this, 'ewww_flag_bulk' ) );
				add_action( 'flag_manage_post_processor_galleries', array( $this, 'ewww_flag_bulk' ) );
			}
			if ( ewww_image_optimizer_test_background_opt() ) {
				add_action( 'flag_image_optimized', array( $this, 'queue_new_image' ) );
				add_action( 'flag_image_resized', array( $this, 'queue_new_image' ) );
			} else {
				add_action( 'flag_image_optimized', array( $this, 'ewww_added_new_image_slow' ) );
				add_action( 'flag_image_resized', array( $this, 'ewww_added_new_image_slow' ) );
			}
			// To prevent webview from being prematurely optimized.
			add_action( 'flag_thumbnail_created', array( $this, 'ewww_remove_image_editor' ) );
			add_action( 'wp_ajax_ewww_flag_manual', array( $this, 'ewww_flag_manual' ) );
			add_action( 'wp_ajax_ewww_flag_image_restore', array( $this, 'ewww_flag_image_restore' ) );
			add_action( 'admin_action_ewww_flag_manual', array( $this, 'ewww_flag_manual' ) );
			add_action( 'admin_menu', array( $this, 'ewww_flag_bulk_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'ewww_flag_bulk_script' ), PHP_INT_MAX );
			add_action( 'wp_ajax_bulk_flag_init', array( $this, 'ewww_flag_bulk_init' ) );
			add_action( 'wp_ajax_bulk_flag_filename', array( $this, 'ewww_flag_bulk_filename' ) );
			add_action( 'wp_ajax_bulk_flag_loop', array( $this, 'ewww_flag_bulk_loop' ) );
			add_action( 'wp_ajax_bulk_flag_cleanup', array( $this, 'ewww_flag_bulk_cleanup' ) );
		}

		/**
		 * Adds the Bulk Optimize page to the menu.
		 */
		public function ewww_flag_bulk_menu() {
			add_submenu_page( 'flag-overview', esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ), esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ), 'FlAG Manage gallery', 'flag-bulk-optimize', array( &$this, 'ewww_flag_bulk' ) );
		}

		/**
		 * Add bulk optimize action to image management page.
		 */
		public function ewww_manage_images_bulkaction() {
			echo '<option value="bulk_optimize_images">' . esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ) . '</option>';
		}

		/**
		 * Add bulk optimize action to gallery management page.
		 */
		public function ewww_manage_galleries_bulkaction() {
			echo '<option value="bulk_optimize_galleries">' . esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ) . '</option>';
		}

		/**
		 * Displays the bulk optimiizer html output.
		 */
		public function ewww_flag_bulk() {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			// If there is POST data, make sure bulkaction and doaction are the values we want.
			if ( ! empty( $_POST ) && empty( $_REQUEST['ewww_reset'] ) ) {
				if (
					empty( $_REQUEST['_wpnonce'] ) ||
					(
						! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'flag_bulkgallery' ) &&
						! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'flag_updategallery' )
					)
				) {
					wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
				}
				// If there is no requested bulk action, do nothing.
				if ( empty( $_REQUEST['bulkaction'] ) ) {
					return;
				}
				// If there is no media to optimize, do nothing.
				if ( empty( $_REQUEST['doaction'] ) || ! is_array( $_REQUEST['doaction'] ) ) {
					return;
				}
				if ( ! preg_match( '/^bulk_optimize/', sanitize_key( $_REQUEST['bulkaction'] ) ) ) {
					return;
				}
			}
			list( $fullsize_count, $resize_count ) = ewww_image_optimizer_count_optimized( 'flag' );
			// Bail-out if there aren't any images to optimize.
			if ( $fullsize_count < 1 ) {
				echo '<p>' . esc_html__( 'You do not appear to have uploaded any images yet.', 'ewww-image-optimizer' ) . '</p>';
				return;
			}
			?>
			<div class="wrap"><h1>GRAND FlAGallery <?php esc_html_e( 'Bulk Optimize', 'ewww-image-optimizer' ); ?></h1>
			<?php
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
				ewww_image_optimizer_cloud_verify( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) );
				echo '<a id="ewww-bulk-credits-available" target="_blank" class="page-title-action" style="float:right;" href="https://ewww.io/my-account/">' . esc_html__( 'Image credits available:', 'ewww-image-optimizer' ) . ' ' . esc_html( ewww_image_optimizer_cloud_quota() ) . '</a>';
			}
			if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ) {
				echo '<div id="ewww-bulk-warning" class="ewww-bulk-info notice notice-warning"><p>' . esc_html__( 'Bulk Optimization will alter your original images and cannot be undone. Please be sure you have a backup of your images before proceeding.', 'ewww-image-optimizer' ) . '</p></div>';
			}
			// Retrieve the value of the 'bulk resume' option and set the button text for the form to use.
			$resume = get_option( 'ewww_image_optimizer_bulk_flag_resume' );
			if ( empty( $resume ) ) {
				$button_text = __( 'Start optimizing', 'ewww-image-optimizer' );
			} else {
				$button_text = __( 'Resume previous optimization', 'ewww-image-optimizer' );
			}
			$delay = ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) ? ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) : 0;
			/* translators: 1-4: number(s) of images */
			$selected_images_text = sprintf( __( '%1$d images have been selected, with %2$d resized versions.', 'ewww-image-optimizer' ), $fullsize_count, $resize_count );
			?>
			<div id="ewww-bulk-loading"></div>
			<div id="ewww-bulk-progressbar"></div>
			<div id="ewww-bulk-counter"></div>
			<form id="ewww-bulk-stop" style="display:none;" method="post" action="">
				<br /><input type="submit" class="button-secondary action" value="<?php esc_attr_e( 'Stop Optimizing', 'ewww-image-optimizer' ); ?>" />
			</form>
			<div id="ewww-bulk-widgets" class="metabox-holder" style="display:none">
				<div class="meta-box-sortables">
					<div id="ewww-bulk-last" class="postbox">
						<button type="button" class="ewww-handlediv button-link" aria-expanded="true">
							<span class="screen-reader-text"><?php esc_html_e( 'Click to toggle', 'ewww-image-optimizer' ); ?></span>
							<span class="toggle-indicator" aria-hidden="true"></span>
						</button>
						<h2 class="ewww-hndle"><span><?php esc_html_e( 'Last Image Optimized', 'ewww-image-optimizer' ); ?></span></h2>
						<div class="inside"></div>
					</div>
				</div>
				<div class="meta-box-sortables">
					<div id="ewww-bulk-status" class="postbox">
						<button type="button" class="ewww-handlediv button-link" aria-expanded="true">
							<span class="screen-reader-text"><?php esc_html_e( 'Click to toggle', 'ewww-image-optimizer' ); ?></span>
							<span class="toggle-indicator" aria-hidden="true"></span>
						</button>
						<h2 class="ewww-hndle"><span><?php esc_html_e( 'Optimization Log', 'ewww-image-optimizer' ); ?></span></h2>
						<div class="inside"></div>
					</div>
				</div>
			</div>
			<form id="ewww-bulk-controls" class="ewww-bulk-form">
				<p><label for="ewww-force" style="font-weight: bold"><?php esc_html_e( 'Force re-optimize', 'ewww-image-optimizer' ); ?></label><?php ewwwio_help_link( 'https://docs.ewww.io/article/65-force-re-optimization', '5bb640a7042863158cc711cd' ); ?>
					&emsp;<input type="checkbox" idp="ewww-force" name="ewww-force">
					&nbsp;<?php esc_html_e( 'Previously optimized images will be skipped by default, check this box before scanning to override.', 'ewww-image-optimizer' ); ?>
					&nbsp;<a href="tools.php?page=ewww-image-optimizer-tools"><?php esc_html_e( 'View optimization history.', 'ewww-image-optimizer' ); ?></a>
				</p>
				<p>
					<label for="ewww-delay" style="font-weight: bold"><?php esc_html_e( 'Pause between images', 'ewww-image-optimizer' ); ?></label>&emsp;<input type="text" id="ewww-delay" name="ewww-delay" value="<?php echo (int) $delay; ?>"> <?php esc_html_e( 'in seconds, 0 = disabled', 'ewww-image-optimizer' ); ?>
				</p>
				<div id="ewww-delay-slider"></div>
			</form>
			<div id="ewww-bulk-forms" style="float:none;">
			<p class="ewww-bulk-info"><?php echo esc_html( $selected_images_text ); ?></p>
			<form id="ewww-bulk-start" class="ewww-bulk-form" method="post" action="">
				<input type="submit" class="button-primary action" value="<?php echo esc_attr( $button_text ); ?>" />
			</form>
			<?php
			// If there was a previous operation, offer the option to reset the option in the db.
			if ( ! empty( $resume ) ) :
				?>
			<p class="ewww-bulk-info" style="margin-top:3em;"><?php esc_html_e( 'Would you like clear the queue and start over?', 'ewww-image-optimizer' ); ?></p>
			<form method="post" class="ewww-bulk-form" action="">
				<?php wp_nonce_field( 'ewww-image-optimizer-bulk', 'ewww_wpnonce' ); ?>
				<input type="hidden" name="ewww_reset" value="1">
				<button id="bulk-reset" type="submit" class="button-secondary action"><?php esc_html_e( 'Clear Queue', 'ewww-image-optimizer' ); ?></button>
			</form>
				<?php
			endif;
			echo '</div></div>';
		}

		/**
		 * Checks the hook suffix to see if this is the individual gallery management page.
		 *
		 * @param string $hook The hook suffix of the page.
		 * @returns boolean True for the gallery page, false anywhere else.
		 */
		public function is_gallery_page( $hook ) {
			if ( 'flagallery_page_flag-manage-gallery' === $hook ) {
				return true;
			}
			if ( false !== strpos( $hook, 'page_flag-manage-gallery' ) ) {
				return true;
			}
			return false;
		}

		/**
		 * Checks the hook suffix to see if this is the bulk optimize page.
		 *
		 * @param string $hook The hook suffix of the page.
		 * @returns boolean True for the bulk page, false anywhere else.
		 */
		public function is_bulk_page( $hook ) {
			if ( 'flagallery_page_flag-bulk-optimize' === $hook ) {
				return true;
			}
			if ( false !== strpos( $hook, 'page_flag-bulk-optimize' ) ) {
				return true;
			}
			return false;
		}

		/**
		 * Prepares the bulk operation and includes the necessary javascript files.
		 *
		 * @global object $flagdb
		 * @global object $wpdb
		 *
		 * @param string $hook The hook value for the current page.
		 */
		public function ewww_flag_bulk_script( $hook ) {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			// Make sure we are being hooked from a valid location.
			if ( ! $this->is_bulk_page( $hook ) && ! $this->is_gallery_page( $hook ) ) {
				return;
			}
			$nonce_verified = false;
			if ( ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'flag_bulkgallery' ) ) {
				$nonce_verified = true;
			}
			if ( ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'flag_updategallery' ) ) {
				$nonce_verified = true;
			}
			// If there is no requested bulk action, do nothing.
			if ( $this->is_gallery_page( $hook ) && ( empty( $_REQUEST['bulkaction'] ) || false === strpos( sanitize_key( $_REQUEST['bulkaction'] ), 'bulk_optimize' ) ) ) {
				return;
			}
			// If there is no media to optimize, do nothing.
			if ( $this->is_gallery_page( $hook ) && ( empty( $_REQUEST['doaction'] ) || ! is_array( $_REQUEST['doaction'] ) ) ) {
				return;
			}
			$ids = null;
			// Reset the resume flag if the user requested it.
			if ( ! empty( $_REQUEST['ewww_reset'] ) ) {
				update_option( 'ewww_image_optimizer_bulk_flag_resume', '' );
			}
			// Get the resume flag from the db.
			$resume = get_option( 'ewww_image_optimizer_bulk_flag_resume' );
			// Check if we are being asked to optimize galleries or images rather than a full bulk optimize.
			if ( ! empty( $_REQUEST['doaction'] ) ) {
				ewwwio_debug_message( 'possible batch image request' );
				// See if the bulk operation requested is from the manage images page.
				if ( ! empty( $_REQUEST['page'] ) ) {
					ewwwio_debug_message( sanitize_key( $_REQUEST['page'] ) );
				}
				if ( ! empty( $_REQUEST['page'] ) && 'flag-manage-gallery' === $_REQUEST['page'] && ! empty( $_REQUEST['bulkaction'] ) && 'bulk_optimize_images' === $_REQUEST['bulkaction'] ) {
					// Check the referring page and nonce.
					check_admin_referer( 'flag_updategallery' );
					// We don't allow previous operations to resume if the user is asking to optimize specific images.
					update_option( 'ewww_image_optimizer_bulk_flag_resume', '' );
					// Retrieve the image IDs from POST.
					$ids = array_map( 'intval', $_REQUEST['doaction'] );
					ewwwio_debug_message( 'batch image request from image list' );
				}
				// See if the bulk operation requested is from the manage galleries page.
				if ( ! empty( $_REQUEST['page'] ) && 'flag-manage-gallery' === $_REQUEST['page'] && ! empty( $_REQUEST['bulkaction'] ) && 'bulk_optimize_galleries' === $_REQUEST['bulkaction'] ) {
					// Check the referring page and nonce.
					check_admin_referer( 'flag_bulkgallery' );
					global $flagdb;
					// We don't allow previous operations to resume if the user is asking to optimize specific galleries.
					update_option( 'ewww_image_optimizer_bulk_flag_resume', '' );
					$ids  = array();
					$gids = array_map( 'intval', $_REQUEST['doaction'] );
					// For each gallery ID, retrieve the image IDs within.
					foreach ( $gids as $gid ) {
						$gallery_list = $flagdb->get_gallery( $gid );
						// For each image ID found, put it onto the $ids array.
						foreach ( $gallery_list as $image ) {
							$ids[] = $image->pid;
						}
					}
					ewwwio_debug_message( 'batch image request from gallery list' );
				}
			} elseif ( ! empty( $resume ) ) {
				// If there is an operation to resume, get those IDs from the db.
				$ids = get_option( 'ewww_image_optimizer_bulk_flag_attachments' );
			} elseif ( $this->is_bulk_page( $hook ) ) {
				// Otherwise, if we are on the main bulk optimize page, just get all the IDs available.
				global $wpdb;
				$ids = $wpdb->get_col( "SELECT pid FROM $wpdb->flagpictures ORDER BY sortorder ASC" );
			} // End if().
			// Store the IDs to optimize in the options table of the db.
			update_option( 'ewww_image_optimizer_bulk_flag_attachments', $ids );
			// Add the EWWW IO javascript.
			wp_enqueue_script( 'ewwwbulkscript', plugins_url( '/includes/eio-bulk.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array( 'jquery', 'jquery-ui-progressbar', 'jquery-ui-slider', 'postbox', 'dashboard' ), EWWW_IMAGE_OPTIMIZER_VERSION );
			// Add the styling for the progressbar.
			wp_enqueue_style( 'jquery-ui-progressbar', plugins_url( '/includes/jquery-ui-1.10.1.custom.css', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ) );
			// Prepare a few variables to be used by the javascript code.
			wp_localize_script(
				'ewwwbulkscript',
				'ewww_vars',
				array(
					'_wpnonce'              => wp_create_nonce( 'ewww-image-optimizer-bulk' ),
					'gallery'               => 'flag',
					'attachments'           => count( $ids ),
					'scan_fail'             => esc_html__( 'Operation timed out, you may need to increase the max_execution_time for PHP', 'ewww-image-optimizer' ),
					'operation_stopped'     => esc_html__( 'Optimization stopped, reload page to resume.', 'ewww-image-optimizer' ),
					'operation_interrupted' => esc_html__( 'Operation Interrupted', 'ewww-image-optimizer' ),
					'temporary_failure'     => esc_html__( 'Temporary failure, seconds left to retry:', 'ewww-image-optimizer' ),
					'remove_failed'         => esc_html__( 'Could not remove image from table.', 'ewww-image-optimizer' ),
					'optimized'             => esc_html__( 'Optimized', 'ewww-image-optimizer' ),
				)
			);
		}

		/**
		 * Adds a newly uploaded image to the background queue.
		 *
		 * @param object $image A Flag_Image object for the new upload.
		 */
		public function queue_new_image( $image ) {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			$image_id = $image->pid;
			ewwwio_debug_message( "optimization (flagallery) queued for $image_id" );
			ewwwio()->background_flag->push_to_queue( array( 'id' => $image_id ) );
			ewwwio()->background_flag->dispatch();
		}

		/**
		 * Optimizes newly uploaded images from the queue.
		 *
		 * @param int    $id The ID number for the new image.
		 * @param object $image A Flag_Image object for the new upload.
		 */
		public function ewww_added_new_image( $id, $image ) {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			global $ewww_defer;
			global $ewww_image;
			// Make sure the image path is set.
			if ( ! isset( $image->image->imagePath ) ) {
				return;
			}
			$ewww_image         = new EWWW_Image( $id, 'flag', $image->image->imagePath );
			$ewww_image->resize = 'full';
			// Optimize the full size.
			$res                = ewww_image_optimizer( $image->image->imagePath, 3, false, false, true );
			$ewww_image         = new EWWW_Image( $id, 'flag', $image->image->webimagePath );
			$ewww_image->resize = 'webview';
			// Optimize the web optimized version.
			$wres               = ewww_image_optimizer( $image->image->webimagePath, 3, false, true );
			$ewww_image         = new EWWW_Image( $id, 'flag', $image->image->thumbPath );
			$ewww_image->resize = 'thumbnail';
			// Optimize the thumbnail.
			$tres = ewww_image_optimizer( $image->image->thumbPath, 3, false, true );
		}

		/**
		 * Optimizes newly uploaded images immediately on upload (no background opt).
		 *
		 * @param object $image A Flag_Image object for the new upload.
		 */
		public function ewww_added_new_image_slow( $image ) {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			// Make sure the image path is set.
			if ( isset( $image->imagePath ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				global $ewww_image;
				$ewww_image         = new EWWW_Image( $image->pid, 'flag', $image->imagePath ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$ewww_image->resize = 'full';
				// Optimize the full size.
				$res                = ewww_image_optimizer( $image->imagePath, 3, false, false, true ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$ewww_image         = new EWWW_Image( $image->pid, 'flag', $image->webimagePath ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$ewww_image->resize = 'webview';
				// Optimize the web optimized version.
				$wres               = ewww_image_optimizer( $image->webimagePath, 3, false, true ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$ewww_image         = new EWWW_Image( $image->pid, 'flag', $image->thumbPath ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$ewww_image->resize = 'thumbnail';
				// Optimize the thumbnail.
				$tres = ewww_image_optimizer( $image->thumbPath, 3, false, true ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				if ( ! class_exists( 'flagMeta' ) ) {
					require_once FLAG_ABSPATH . 'lib/meta.php';
				}
				// Retrieve the metadata for the image ID.
				$pid  = $image->pid;
				$meta = new flagMeta( $pid );
			}
		}

		/**
		 * Remove the image editor filter during upload, and add a new filter that will restore it later.
		 */
		public function ewww_remove_image_editor() {
			global $ewww_preempt_editor;
			$ewww_preempt_editor = true;
			/* remove_filter( 'wp_image_editors', 'ewww_image_optimizer_load_editor', 60 ); */
			add_action( 'flag_image_optimized', 'ewww_image_optimizer_restore_editor_hooks' );
		}

		/**
		 * Manually process an image from the gallery.
		 */
		public function ewww_flag_manual() {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			// Make sure the current user has appropriate permissions.
			$permissions = apply_filters( 'ewww_image_optimizer_manual_permissions', '' );
			if ( false === current_user_can( $permissions ) ) {
				if ( ! wp_doing_ajax() ) {
					wp_die( esc_html__( 'You do not have permission to optimize images.', 'ewww-image-optimizer' ) );
				}
				ewwwio_ob_clean();
				wp_die( wp_json_encode( array( 'error' => esc_html__( 'You do not have permission to optimize images.', 'ewww-image-optimizer' ) ) ) );
			}
			// Make sure we have an attachment ID.
			if ( empty( $_REQUEST['ewww_attachment_ID'] ) ) {
				if ( ! wp_doing_ajax() ) {
					wp_die( esc_html__( 'No attachment ID was provided.', 'ewww-image-optimizer' ) );
				}
				ewwwio_ob_clean();
				wp_die( wp_json_encode( array( 'error' => esc_html__( 'No attachment ID was provided.', 'ewww-image-optimizer' ) ) ) );
			}
			$id = intval( $_REQUEST['ewww_attachment_ID'] );
			if ( empty( $_REQUEST['ewww_manual_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_manual_nonce'] ), "ewww-manual-$id" ) ) {
				if ( ! wp_doing_ajax() ) {
					wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
				}
				ewwwio_ob_clean();
				wp_die( wp_json_encode( array( 'error' => esc_html__( 'Access denied.', 'ewww-image-optimizer' ) ) ) );
			}
			global $ewww_image;
			global $ewww_force;
			$ewww_force = ! empty( $_REQUEST['ewww_force'] ) ? true : false;
			if ( ! class_exists( 'flagMeta' ) ) {
				require_once FLAG_ABSPATH . 'lib/meta.php';
			}
			// Retrieve the metadata for the image ID.
			$meta = new flagMeta( $id );
			// Determine the path of the image.
			$file_path          = $meta->image->imagePath;
			$ewww_image         = new EWWW_Image( $id, 'flag', $file_path );
			$ewww_image->resize = 'full';
			// Optimize the full size.
			$res = ewww_image_optimizer( $file_path, 3, false, false, true );
			if ( ! empty( $meta->image->meta_data['webview'] ) ) {
				// Determine path of the webview.
				$web_path           = $meta->image->webimagePath;
				$ewww_image         = new EWWW_Image( $id, 'flag', $web_path );
				$ewww_image->resize = 'webview';
				$wres               = ewww_image_optimizer( $web_path, 3, false, true );
			}
			// Determine the path of the thumbnail.
			$thumb_path         = $meta->image->thumbPath;
			$ewww_image         = new EWWW_Image( $id, 'flag', $thumb_path );
			$ewww_image->resize = 'thumbnail';
			// Optimize the thumbnail.
			$tres = ewww_image_optimizer( $thumb_path, 3, false, true );
			if ( ! wp_doing_ajax() ) {
				// Get the referring page...
				$sendback = wp_get_referer();
				// Send the user back where they came from.
				wp_safe_redirect( $sendback );
				die;
			}
			$success = $this->ewww_manage_image_custom_column_capture( $id );
			ewwwio_ob_clean();
			wp_die( wp_json_encode( array( 'success' => $success ) ) );
		}

		/**
		 * Restore an image from the API.
		 */
		public function ewww_flag_image_restore() {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			// Check permission of current user.
			$permissions = apply_filters( 'ewww_image_optimizer_manual_permissions', '' );
			if ( false === current_user_can( $permissions ) ) {
				if ( ! wp_doing_ajax() ) {
					wp_die( esc_html__( 'You do not have permission to optimize images.', 'ewww-image-optimizer' ) );
				}
				ewwwio_ob_clean();
				wp_die( wp_json_encode( array( 'error' => esc_html__( 'You do not have permission to optimize images.', 'ewww-image-optimizer' ) ) ) );
			}
			// Make sure function wasn't called without an attachment to work with.
			if ( false === isset( $_REQUEST['ewww_attachment_ID'] ) ) {
				if ( ! wp_doing_ajax() ) {
					wp_die( esc_html__( 'No attachment ID was provided.', 'ewww-image-optimizer' ) );
				}
				ewwwio_ob_clean();
				wp_die( wp_json_encode( array( 'error' => esc_html__( 'No attachment ID was provided.', 'ewww-image-optimizer' ) ) ) );
			}
			// Store the attachment $id.
			$id = intval( $_REQUEST['ewww_attachment_ID'] );
			if ( empty( $_REQUEST['ewww_manual_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_manual_nonce'] ), "ewww-manual-$id" ) ) {
				if ( ! wp_doing_ajax() ) {
						wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
				}
				ewwwio_ob_clean();
				wp_die( wp_json_encode( array( 'error' => esc_html__( 'Access denied.', 'ewww-image-optimizer' ) ) ) );
			}
			if ( ! class_exists( 'flagMeta' ) ) {
				require_once FLAG_ABSPATH . 'lib/meta.php';
			}
			global $eio_backup;
			$eio_backup->restore_backup_from_meta_data( $id, 'flag' );
			$success = $this->ewww_manage_image_custom_column_capture( $id );
			ewwwio_ob_clean();
			wp_die( wp_json_encode( array( 'success' => $success ) ) );
		}

		/**
		 * Initialize the bulk operation.
		 */
		public function ewww_flag_bulk_init() {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
			if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
				ewwwio_ob_clean();
				wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
			}
			$output = array();
			// Set the resume flag to indicate the bulk operation is in progress.
			update_option( 'ewww_image_optimizer_bulk_flag_resume', 'true' );
			// Retrieve the list of attachments left to work on.
			$attachments = get_option( 'ewww_image_optimizer_bulk_flag_attachments' );
			if ( ! is_array( $attachments ) && ! empty( $attachments ) ) {
				$attachments = unserialize( $attachments );
			}
			if ( ! is_array( $attachments ) ) {
				$output['error'] = esc_html__( 'Error retrieving list of images' );
				ewwwio_ob_clean();
				wp_die( wp_json_encode( $output ) );
			}
			$id            = array_shift( $attachments );
			$file_name     = $this->ewww_flag_bulk_filename( $id );
			$loading_image = plugins_url( '/images/wpspin.gif', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE );
			// Output the initial message letting the user know we are starting.
			if ( empty( $file_name ) ) {
				$output['results'] = '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' alt='loading'/></p>";
			} else {
				$output['results'] = '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . ' <b>' . $file_name . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
			}
			ewwwio_ob_clean();
			wp_die( wp_json_encode( $output ) );
		}

		/**
		 * Get the filename of the currently optimizing image.
		 *
		 * @param int $id The ID number for the image.
		 * @return string|bool The name of the first image in the queue, or false.
		 */
		public function ewww_flag_bulk_filename( $id ) {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			// Need this file to work with flag meta.
			require_once FLAG_ABSPATH . 'lib/meta.php';
			// Retrieve the meta for the current ID.
			$meta = new flagMeta( $id );
			// Retrieve the filename for the current image ID.
			$file_name = esc_html( $meta->image->filename );
			if ( ! empty( $file_name ) ) {
				return $file_name;
			} else {
				return false;
			}
		}

		/**
		 * Process each image during the bulk operation.
		 */
		public function ewww_flag_bulk_loop() {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			global $ewww_defer;
			$ewww_defer  = false;
			$output      = array();
			$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
			if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
				$output['error'] = esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' );
				ewwwio_ob_clean();
				wp_die( wp_json_encode( $output ) );
			}
			session_write_close();
			// Find out if our nonce is on it's last leg/tick.
			$tick = wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-bulk' );
			if ( 2 === $tick ) {
				$output['new_nonce'] = wp_create_nonce( 'ewww-image-optimizer-bulk' );
			} else {
				$output['new_nonce'] = '';
			}
			global $ewww_image;
			// Need this file to work with flag meta.
			require_once FLAG_ABSPATH . 'lib/meta.php';
			// Record the starting time for the current image (in microseconds).
			$started = microtime( true );
			// Retrieve the list of attachments left to work on.
			$attachments = get_option( 'ewww_image_optimizer_bulk_flag_attachments' );
			$id          = array_shift( $attachments );
			// Get the image meta for the current ID.
			$meta               = new flagMeta( $id );
			$file_path          = $meta->image->imagePath;
			$ewww_image         = new EWWW_Image( $id, 'flag', $file_path );
			$ewww_image->resize = 'full';
			// Optimize the full-size version.
			$fres = ewww_image_optimizer( $file_path, 3, false, false, true );
			if ( 'exceeded' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
				$output['error'] = '<a href="https://ewww.io/buy-credits/" target="_blank">' . esc_html__( 'License Exceeded', 'ewww-image-optimizer' ) . '</a>';
				ewwwio_ob_clean();
				wp_die( wp_json_encode( $output ) );
			}
			if ( 'exceeded quota' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
				$output['error'] = '<a href="https://docs.ewww.io/article/101-soft-quotas-on-unlimited-plans" target="_blank">' . esc_html__( 'Soft quota reached, contact us for more', 'ewww-image-optimizer' ) . '</a>';
				ewwwio_ob_clean();
				wp_die( wp_json_encode( $output ) );
			}
			// Let the user know what happened.
			$output['results'] = sprintf( '<p>' . esc_html__( 'Optimized image:', 'ewww-image-optimizer' ) . ' <strong>%s</strong><br>', esc_html( $meta->image->filename ) );
			/* Translators: %s: The compression results/savings */
			$output['results'] .= sprintf( esc_html__( 'Full size – %s', 'ewww-image-optimizer' ) . '<br>', esc_html( $fres[1] ) );
			if ( ! empty( $meta->image->meta_data['webview'] ) ) {
				// Determine path of the webview.
				$web_path           = $meta->image->webimagePath;
				$ewww_image         = new EWWW_Image( $id, 'flag', $web_path );
				$ewww_image->resize = 'webview';
				$wres               = ewww_image_optimizer( $web_path, 3, false, true );
				/* Translators: %s: The compression results/savings */
				$output['results'] .= sprintf( esc_html__( 'Optimized size – %s', 'ewww-image-optimizer' ) . '<br>', esc_html( $wres[1] ) );
			}
			$thumb_path         = $meta->image->thumbPath;
			$ewww_image         = new EWWW_Image( $id, 'flag', $thumb_path );
			$ewww_image->resize = 'thumbnail';
			// Optimize the thumbnail.
			$tres = ewww_image_optimizer( $thumb_path, 3, false, true );
			// And let the user know the results.
			/* Translators: %s: The compression results/savings */
			$output['results'] .= sprintf( esc_html__( 'Thumbnail – %s', 'ewww-image-optimizer' ) . '<br>', esc_html( $tres[1] ) );
			// Determine how much time the image took to process.
			$elapsed = microtime( true ) - $started;
			// And output it to the user.
			/* Translators: %s: number of seconds, localized */
			$output['results']  .= sprintf( esc_html( _n( 'Elapsed: %s second', 'Elapsed: %s seconds', $elapsed, 'ewww-image-optimizer' ) ) . '</p>', number_format_i18n( $elapsed, 2 ) );
			$output['completed'] = 1;
			// Send the list back to the db.
			update_option( 'ewww_image_optimizer_bulk_flag_attachments', $attachments, false );
			if ( ! empty( $attachments ) ) {
				$next_attachment = array_shift( $attachments );
				$next_file       = $this->ewww_flag_bulk_filename( $next_attachment );
				$loading_image   = plugins_url( '/images/wpspin.gif', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE );
				if ( $next_file ) {
					$output['next_file'] = '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . " <b>$next_file</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
				} else {
					$output['next_file'] = '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' alt='loading'/></p>";
				}
			} else {
				$output['done'] = 1;
			}
			ewwwio_ob_clean();
			wp_die( wp_json_encode( $output ) );
		}

		/**
		 * Finish the bulk operation, and clear out the bulk_flag options.
		 */
		public function ewww_flag_bulk_cleanup() {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
			if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
				ewwwio_ob_clean();
				wp_die( esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) );
			}
			// Reset the bulk flags in the db.
			update_option( 'ewww_image_optimizer_bulk_flag_resume', '' );
			update_option( 'ewww_image_optimizer_bulk_flag_attachments', '', false );
			ewwwio_ob_clean();
			// And let the user know we are done.
			wp_die( '<p><b>' . esc_html__( 'Finished Optimization!', 'ewww-image-optimizer' ) . '</b></p>' );
		}

		/**
		 * Prepare javascript for one-click actions on manage gallery page.
		 *
		 * @param string $hook The hook value for the current page.
		 */
		public function ewww_flag_manual_actions_script( $hook ) {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			if ( ! $this->is_gallery_page( $hook ) ) {
				return;
			}
			if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				return;
			}
			add_thickbox();
			wp_enqueue_script( 'ewwwflagscript', plugins_url( '/includes/flag.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION );
			wp_enqueue_style( 'jquery-ui-tooltip-custom', plugins_url( '/includes/jquery-ui-1.10.1.custom.css', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
			// Submit a couple variables needed for javascript functions.
			$loading_image = plugins_url( '/images/spinner.gif', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE );
			wp_localize_script(
				'ewwwflagscript',
				'ewww_vars',
				array(
					'optimizing' => '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . " <img src='$loading_image' /></p>",
					'restoring'  => '<p>' . esc_html__( 'Restoring', 'ewww-image-optimizer' ) . " <img src='$loading_image' /></p>",
				)
			);
		}

		/**
		 * Add a column on the gallery display.
		 *
		 * @param array $columns A list of columns displayed on the manage gallery page.
		 * @return array The list of columns, with EWWW's custom column added.
		 */
		public function ewww_manage_images_columns( $columns ) {
			$columns['ewww_image_optimizer'] = esc_html__( 'Image Optimizer', 'ewww-image-optimizer' );
			return $columns;
		}

		/**
		 * Output the EWWW IO information on the gallery display.
		 *
		 * @param string $column_name The name of the current column.
		 * @param int    $id The ID number of the image being displayed.
		 */
		public function ewww_manage_image_custom_column( $column_name, $id ) {
			if ( 'ewww_image_optimizer' !== $column_name ) {
				return;
			}
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			echo '<div id="ewww-flag-status-' . (int) $id . '">';
			// Get the metadata.
			$meta = new flagMeta( $id );
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ewww_image_optimizer_function_exists( 'print_r' ) ) {
				$print_meta = print_r( $meta->image->meta_data, true );
				echo '<button type="button" class="ewww-show-debug-meta button button-secondary" data-id="' . (int) $id . '">' .
					esc_html__( 'Show Metadata', 'ewww-image-optimizer' ) .
					'</button><div id="ewww-debug-meta-' . (int) $id .
					'" style="font-size: 10px;padding: 10px;margin:3px -10px 10px;line-height: 1.1em;display: none;">' .
					wp_kses_post( preg_replace( array( '/ /', '/\n+/' ), array( '&nbsp;', '<br />' ), $print_meta ) ) .
					'</div>';
			}
			// Get the image path from the meta.
			$file_path = $meta->image->imagePath;
			// Grab the image status from the meta.
			if ( empty( $meta->image->meta_data['ewww_image_optimizer'] ) ) {
				$status = '';
			} else {
				// Run db import here.
				if ( $file_path ) {
					ewww_image_optimizer_update_file_from_meta( $file_path, 'flag', $id, 'full' );
				}
				if ( $meta->image->webimagePath ) {
					ewww_image_optimizer_update_file_from_meta( $meta->image->webimagePath, 'flag', $id, 'webview' );
				}
				if ( $meta->image->thumbPath ) {
					ewww_image_optimizer_update_file_from_meta( $meta->image->thumbPath, 'flag', $id, 'thumbnail' );
				}
			}
			$msg = '';
			// Get the mimetype.
			$type  = ewww_image_optimizer_mimetype( $file_path, 'i' );
			$valid = true;

			if ( ! ewwwio()->tools_initialized && ! ewwwio()->local->os_supported() ) {
				ewwwio()->local->skip_tools();
			} elseif ( ! ewwwio()->tools_initialized ) {
				ewwwio()->tool_init();
			}
			$tools = ewwwio()->local->check_all_tools();
			// If we don't have a valid tool for the image type, output the appropriate message.
			switch ( $type ) {
				case 'image/jpeg':
					if ( $tools['jpegtran']['enabled'] && ! $tools['jpegtran']['path'] ) {
						/* translators: %s: name of a tool like jpegtran */
						echo '<div>' . sprintf( esc_html__( '%s is missing', 'ewww-image-optimizer' ), '<em>jpegtran</em>' ) . '</div></div>';
						return;
					}
					break;
				case 'image/png':
					if ( $tools['optipng']['enabled'] && ! $tools['optipng']['path'] ) {
						/* translators: %s: name of a tool like jpegtran */
						echo '<div>' . sprintf( esc_html__( '%s is missing', 'ewww-image-optimizer' ), '<em>optipng</em>' ) . '</div></div>';
						return;
					}
					if ( $tools['pngout']['enabled'] && ! $tools['pngout']['path'] ) {
						/* translators: %s: name of a tool like jpegtran */
						echo '<div>' . sprintf( esc_html__( '%s is missing', 'ewww-image-optimizer' ), '<em>pngout</em>' ) . '</div></div>';
						return;
					}
					break;
				case 'image/gif':
					if ( $tools['gifsicle']['enabled'] && ! $tools['gifsicle']['path'] ) {
						/* translators: %s: name of a tool like jpegtran */
						echo '<div>' . sprintf( esc_html__( '%s is missing', 'ewww-image-optimizer' ), '<em>gifsicle</em>' ) . '</div></div>';
						return;
					}
					break;
				default:
					echo '<div>' . esc_html__( 'Unsupported file type', 'ewww-image-optimizer' ) . '</div></div>';
					return;
			}
			$backup_available = false;
			global $wpdb;
			$optimized_images  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'flag' AND image_size <> 0 ORDER BY orig_size DESC", $id ), ARRAY_A );
			$ewww_manual_nonce = wp_create_nonce( 'ewww-manual-' . $id );
			if ( ! empty( $optimized_images ) ) {
				list( $detail_output, $converted, $backup_available ) = ewww_image_optimizer_custom_column_results( $id, $optimized_images );
				echo wp_kses_post( $detail_output );
				if ( current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
					printf(
						'<a class="ewww-manual-optimize" data-id="%1$d" data-nonce="%2$s" href="' . esc_url( admin_url( 'admin.php?action=ewww_flag_manual' ) ) . '&amp;ewww_manual_nonce=%2$s&amp;ewww_force=1&amp;ewww_attachment_ID=%1$d">%3$s</a>',
						(int) $id,
						esc_attr( $ewww_manual_nonce ),
						esc_html__( 'Re-optimize', 'ewww-image-optimizer' )
					);
					if ( $backup_available ) {
						printf(
							'<br><a class="ewww-manual-image-restore" data-id="%1$d" data-nonce="%2$s" href="' . esc_url( admin_url( 'admin.php?action=ewww_flag_image_restore' ) ) . '&amp;ewww_manual_nonce=%2$s&amp;ewww_attachment_ID=%1$d">%3$s</a>',
							(int) $id,
							esc_attr( $ewww_manual_nonce ),
							esc_html__( 'Restore original', 'ewww-image-optimizer' )
						);
					}
				}
			} elseif ( ewww_image_optimizer_image_is_pending( $id, 'flag-async' ) ) {
				echo '<div>' . esc_html__( 'In Progress', 'ewww-image-optimizer' ) . '</div>';
				// Otherwise, tell the user that they can optimize the image now.
			} else {
				if ( current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
					printf(
						'<div><a class="ewww-manual-optimize" data-id="%1$d" data-nonce="%2$s" href="' . esc_url( admin_url( 'admin.php?action=ewww_flag_manual' ) ) . '&amp;ewww_manual_nonce=%2$s&amp;ewww_attachment_ID=%1$d">%3$s</a></div>',
						(int) $id,
						esc_attr( $ewww_manual_nonce ),
						esc_html__( 'Optimize now!', 'ewww-image-optimizer' )
					);
				}
			}
			echo '</div>';
		}

		/**
		 * Wrapper around the custom column display to capture and return the output, usually for AJAX.
		 *
		 * @param int $id The ID number of the image to display.
		 */
		public function ewww_manage_image_custom_column_capture( $id ) {
			ob_start();
			$this->ewww_manage_image_custom_column( 'ewww_image_optimizer', $id );
			return ob_get_clean();
		}
	}

	global $ewwwflag;
	$ewwwflag = new EWWW_Flag();
} // End if().
