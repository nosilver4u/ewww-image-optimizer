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
		function __construct() {
			add_filter( 'flag_manage_images_columns', array( $this, 'ewww_manage_images_columns' ) );
			add_action( 'flag_manage_gallery_custom_column', array( $this, 'ewww_manage_image_custom_column_wrapper' ), 10, 2 );
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
			add_action( 'wp_ajax_ewww_flag_cloud_restore', array( $this, 'ewww_flag_cloud_restore' ) );
			add_action( 'admin_action_ewww_flag_manual', array( $this, 'ewww_flag_manual' ) );
			add_action( 'admin_menu', array( $this, 'ewww_flag_bulk_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'ewww_flag_bulk_script' ) );
			add_action( 'wp_ajax_bulk_flag_init', array( $this, 'ewww_flag_bulk_init' ) );
			add_action( 'wp_ajax_bulk_flag_filename', array( $this, 'ewww_flag_bulk_filename' ) );
			add_action( 'wp_ajax_bulk_flag_loop', array( $this, 'ewww_flag_bulk_loop' ) );
			add_action( 'wp_ajax_bulk_flag_cleanup', array( $this, 'ewww_flag_bulk_cleanup' ) );
		}

		/**
		 * Adds the Bulk Optimize page to the menu.
		 */
		function ewww_flag_bulk_menu() {
			add_submenu_page( 'flag-overview', esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ), esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ), 'FlAG Manage gallery', 'flag-bulk-optimize', array( &$this, 'ewww_flag_bulk' ) );
		}

		/**
		 * Add bulk optimize action to image management page.
		 */
		function ewww_manage_images_bulkaction() {
			echo '<option value="bulk_optimize_images">' . esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ) . '</option>';
		}

		/**
		 * Add bulk optimize action to gallery management page.
		 */
		function ewww_manage_galleries_bulkaction() {
			echo '<option value="bulk_optimize_galleries">' . esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ) . '</option>';
		}

		/**
		 * Displays the bulk optimiizer html output.
		 */
		function ewww_flag_bulk() {
			// If there is POST data, make sure bulkaction and doaction are the values we want.
			if ( ! empty( $_POST ) && empty( $_REQUEST['ewww_reset'] ) ) {
				// If there is no requested bulk action, do nothing.
				if ( empty( $_REQUEST['bulkaction'] ) ) {
					return;
				}
				// If there is no media to optimize, do nothing.
				if ( empty( $_REQUEST['doaction'] ) || ! is_array( $_REQUEST['doaction'] ) ) {
					return;
				}
				if ( ! preg_match( '/^bulk_optimize/', $_REQUEST['bulkaction'] ) ) {
					return;
				}
			}
			list( $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ) = ewww_image_optimizer_count_optimized( 'flag' );
			// Bail-out if there aren't any images to optimize.
			if ( $fullsize_count < 1 ) {
				echo '<p>' . esc_html__( 'You do not appear to have uploaded any images yet.', 'ewww-image-optimizer' ) . '</p>';
				return;
			}
			?>
			<div class="wrap"><h1>GRAND FlAGallery <?php esc_html_e( 'Bulk Optimize', 'ewww-image-optimizer' ); ?></h1><?php
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
				ewww_image_optimizer_cloud_verify();
				echo '<a id="ewww-bulk-credits-available" target="_blank" class="page-title-action" style="float:right;" href="https://ewww.io/my-account/">' . esc_html__( 'Image credits available:', 'ewww-image-optimizer' ) . ' ' . ewww_image_optimizer_cloud_quota() . '</a>';
			}
			// Retrieve the value of the 'bulk resume' option and set the button text for the form to use.
			$resume = get_option( 'ewww_image_optimizer_bulk_flag_resume' );
			if ( empty( $resume ) ) {
				$button_text = esc_attr__( 'Start optimizing', 'ewww-image-optimizer' );
			} else {
				$button_text = esc_attr__( 'Resume previous bulk operation', 'ewww-image-optimizer' );
			}
			$delay = ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) ? ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) : 0;
			/* translators: 1-4: number(s) of images */
			$selected_images_text = sprintf( esc_html__( '%1$d images have been selected (%2$d unoptimized), with %3$d resizes (%4$d unoptimized).', 'ewww-image-optimizer' ), $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count );
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
						<button type="button" class="handlediv button-link" aria-expanded="true">
							<span class="screen-reader-text"><?php esc_html_e( 'Click to toggle', 'ewww-image-optimizer' ) ?></span>
							<span class="toggle-indicator" aria-hidden="true"></span>
						</button>
						<h2 class="hndle"><span><?php esc_html_e( 'Last Image Optimized', 'ewww-image-optimizer' ) ?></span></h2>
						<div class="inside"></div>
					</div>
				</div>
				<div class="meta-box-sortables">
					<div id="ewww-bulk-status" class="postbox">
						<button type="button" class="handlediv button-link" aria-expanded="true">
							<span class="screen-reader-text"><?php esc_html_e( 'Click to toggle', 'ewww-image-optimizer' ) ?></span>
							<span class="toggle-indicator" aria-hidden="true"></span>
						</button>
						<h2 class="hndle"><span><?php esc_html_e( 'Optimization Log', 'ewww-image-optimizer' ) ?></span></h2>
						<div class="inside"></div>
					</div>
				</div>
			</div>
			<form class="ewww-bulk-form">
				<p><label for="ewww-force" style="font-weight: bold"><?php esc_html_e( 'Force re-optimize', 'ewww-image-optimizer' ); ?></label>&emsp;<input type="checkbox" id="ewww-force" name="ewww-force"></p>
				<p><label for="ewww-delay" style="font-weight: bold"><?php esc_html_e( 'Choose how long to pause between images (in seconds, 0 = disabled)', 'ewww-image-optimizer' ); ?></label>&emsp;<input type="text" id="ewww-delay" name="ewww-delay" value="<?php echo $delay; ?>"></p>
				<div id="ewww-delay-slider" style="width:50%"></div>
			</form>
			<div id="ewww-bulk-forms">
			<p class="ewww-bulk-info"><?php echo $selected_images_text; ?><br />
			<?php esc_html_e( 'Previously optimized images will be skipped by default.', 'ewww-image-optimizer' ); ?></p>
			<form id="ewww-bulk-start" class="ewww-bulk-form" method="post" action="">
				<input type="submit" class="button-secondary action" value="<?php echo $button_text; ?>" />
			</form>
			<?php
			// If there was a previous operation, offer the option to reset the option in the db.
			if ( ! empty( $resume ) ) :
			?>
			<p class="ewww-bulk-info"><?php esc_html_e( 'If you would like to start over again, press the Reset Status button to reset the bulk operation status.', 'ewww-image-optimizer' ); ?></p>
			<form method="post" class="ewww-bulk-form" action="">
				<?php wp_nonce_field( 'ewww-image-optimizer-bulk', 'ewww_wpnonce' ); ?>
				<input type="hidden" name="ewww_reset" value="1">
				<button id="bulk-reset" type="submit" class="button-secondary action"><?php esc_html_e( 'Reset Status', 'ewww-image-optimizer' ); ?></button>
			</form>
			<?php
			endif;
			echo '</div></div>';
		}

		/**
		 * Prepares the bulk operation and includes the necessary javascript files.
		 *
		 * @global object $flagdb
		 * @global object $wpdb
		 *
		 * @param string $hook The hook value for the current page.
		 */
		function ewww_flag_bulk_script( $hook ) {
			// Make sure we are being hooked from a valid location.
			if ( 'flagallery_page_flag-bulk-optimize' != $hook && 'flagallery_page_flag-manage-gallery' != $hook ) {
				return;
			}
			// If there is no requested bulk action, do nothing.
			if ( 'flagallery_page_flag-manage-gallery' == $hook && ( empty( $_REQUEST['bulkaction'] ) || ! preg_match( '/^bulk_optimize/', $_REQUEST['bulkaction'] )) ) {
				return;
			}
			// If there is no media to optimize, do nothing.
			if ( 'flagallery_page_flag-manage-gallery' == $hook && ( empty( $_REQUEST['doaction'] ) || ! is_array( $_REQUEST['doaction'] )) ) {
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
				// See if the bulk operation requested is from the manage images page.
				if ( 'manage-images' == $_REQUEST['page'] && 'bulk_optimize_images' == $_REQUEST['bulkaction'] ) {
					// Check the referring page and nonce.
					check_admin_referer( 'flag_updategallery' );
					// We don't allow previous operations to resume if the user is asking to optimize specific images.
					update_option( 'ewww_image_optimizer_bulk_flag_resume', '' );
					// Retrieve the image IDs from POST.
					$ids = array_map( 'intval', $_REQUEST['doaction'] );
				}
				// See if the bulk operation requested is from the manage galleries page.
				if ( 'manage-galleries' == $_REQUEST['page'] && 'bulk_optimize_galleries' == $_REQUEST['bulkaction'] ) {
					// Check the referring page and nonce.
					check_admin_referer( 'flag_bulkgallery' );
					global $flagdb;
					// We don't allow previous operations to resume if the user is asking to optimize specific galleries.
					update_option( 'ewww_image_optimizer_bulk_flag_resume', '' );
					$ids = array();
					$gids = array_map( 'intval', $_REQUEST['doaction'] );
					// For each gallery ID, retrieve the image IDs within.
					foreach ( $gids as $gid ) {
						$gallery_list = $flagdb->get_gallery( $gid );
						// For each image ID found, put it onto the $ids array.
						foreach ( $gallery_list as $image ) {
							$ids[] = $image->pid;
						}
					}
				}
			} elseif ( ! empty( $resume ) ) {
				// If there is an operation to resume, get those IDs from the db.
				$ids = get_option( 'ewww_image_optimizer_bulk_flag_attachments' );
			} elseif ( 'flagallery_page_flag-bulk-optimize' == $hook ) {
				// Otherwise, if we are on the main bulk optimize page, just get all the IDs available.
				global $wpdb;
				$ids = $wpdb->get_col( "SELECT pid FROM $wpdb->flagpictures ORDER BY sortorder ASC" );
			} // End if().
			// Store the IDs to optimize in the options table of the db.
			update_option( 'ewww_image_optimizer_bulk_flag_attachments', $ids );
			// Add the EWWW IO javascript.
			wp_enqueue_script( 'ewwwbulkscript', plugins_url( '/includes/eio.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array( 'jquery', 'jquery-ui-progressbar', 'jquery-ui-slider', 'postbox', 'dashboard' ), EWWW_IMAGE_OPTIMIZER_VERSION );
			// Add the styling for the progressbar.
			wp_enqueue_style( 'jquery-ui-progressbar', plugins_url( '/includes/jquery-ui-1.10.1.custom.css', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ) );
			// Prepare a few variables to be used by the javascript code.
			wp_localize_script('ewwwbulkscript', 'ewww_vars', array(
				'_wpnonce' => wp_create_nonce( 'ewww-image-optimizer-bulk' ),
				'gallery' => 'flag',
				'attachments' => count( $ids ),
				'scan_fail' => esc_html__( 'Operation timed out, you may need to increase the max_execution_time for PHP', 'ewww-image-optimizer' ),
				'operation_stopped' => esc_html__( 'Optimization stopped, reload page to resume.', 'ewww-image-optimizer' ),
				'operation_interrupted' => esc_html__( 'Operation Interrupted', 'ewww-image-optimizer' ),
				'temporary_failure' => esc_html__( 'Temporary failure, seconds left to retry:', 'ewww-image-optimizer' ),
				'remove_failed' => esc_html__( 'Could not remove image from table.', 'ewww-image-optimizer' ),
				'optimized' => esc_html__( 'Optimized', 'ewww-image-optimizer' ),
				)
			);
		}

		/**
		 * Adds a newly uploaded image to the background queue.
		 *
		 * @param object $image A Flag_Image object for the new upload.
		 */
		function queue_new_image( $image ) {
			ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
			$image_id = $image->pid;
			global $ewwwio_flag_background;
			if ( ! class_exists( 'WP_Background_Process' ) ) {
				require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
			}
			if ( ! is_object( $ewwwio_flag_background ) ) {
				$ewwwio_flag_background = new EWWWIO_Flag_Background_Process();
			}
			ewwwio_debug_message( "optimization (flagallery) queued for $image_id" );
			$ewwwio_flag_background->push_to_queue( array(
				'id' => $image_id,
			) );
			$ewwwio_flag_background->save()->dispatch();
			set_transient( 'ewwwio-background-in-progress-flag-' . $image_id, true, 24 * HOUR_IN_SECONDS );
			ewww_image_optimizer_debug_log();
		}

		/**
		 * Optimizes newly uploaded images from the queue.
		 *
		 * @param int    $id The ID number for the new image.
		 * @param object $image A Flag_Image object for the new upload.
		 */
		function ewww_added_new_image( $id, $image ) {
			ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
			global $ewww_defer;
			global $ewww_image;
			// Make sure the image path is set.
			if ( ! isset( $image->image->imagePath ) ) {
				return;
			}
			$ewww_image = new EWWW_Image( $id, 'flag', $image->image->imagePath );
			$ewww_image->resize = 'full';
			// Optimize the full size.
			$res = ewww_image_optimizer( $image->image->imagePath, 3, false, false, true );
			$ewww_image = new EWWW_Image( $id, 'flag', $image->image->webimagePath );
			$ewww_image->resize = 'webview';
			// Optimize the web optimized version.
			$wres = ewww_image_optimizer( $image->image->webimagePath, 3, false, true );
			$ewww_image = new EWWW_Image( $id, 'flag', $image->image->thumbPath );
			$ewww_image->resize = 'thumbnail';
			// Optimize the thumbnail.
			$tres = ewww_image_optimizer( $image->image->thumbPath, 3, false, true );

			ewww_image_optimizer_debug_log();
		}

		/**
		 * Optimizes newly uploaded images immediately on upload (no background opt).
		 *
		 * @param object $image A Flag_Image object for the new upload.
		 */
		function ewww_added_new_image_slow( $image ) {
			ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
			// Make sure the image path is set.
			if ( isset( $image->imagePath ) ) {
				global $ewww_image;
				$ewww_image = new EWWW_Image( $image->pid, 'flag', $image->imagePath );
				$ewww_image->resize = 'full';
				// Optimize the full size.
				$res = ewww_image_optimizer( $image->imagePath, 3, false, false, true );
				$ewww_image = new EWWW_Image( $image->pid, 'flag', $image->webimagePath );
				$ewww_image->resize = 'webview';
				// Optimize the web optimized version.
				$wres = ewww_image_optimizer( $image->webimagePath, 3, false, true );
				$ewww_image = new EWWW_Image( $image->pid, 'flag', $image->thumbPath );
				$ewww_image->resize = 'thumbnail';
				// Optimize the thumbnail.
				$tres = ewww_image_optimizer( $image->thumbPath, 3, false, true );
				if ( ! class_exists( 'flagMeta' ) ) {
					require_once( FLAG_ABSPATH . 'lib/meta.php' );
				}
				// Retrieve the metadata for the image ID.
				$pid = $image->pid;
				$meta = new flagMeta( $pid );
			}
			ewww_image_optimizer_debug_log();
		}

		/**
		 * Remove the image editor filter during upload, and add a new filter that will restore it later.
		 */
		function ewww_remove_image_editor() {
			remove_filter( 'wp_image_editors', 'ewww_image_optimizer_load_editor', 60 );
			add_action( 'flag_image_optimized', 'ewww_image_optimizer_restore_editor_hooks' );
		}

		/**
		 * Manually process an image from the gallery.
		 */
		function ewww_flag_manual() {
			// Make sure the current user has appropriate permissions.
			$permissions = apply_filters( 'ewww_image_optimizer_manual_permissions', '' );
			if ( false === current_user_can( $permissions ) ) {
				if ( ! wp_doing_ajax() ) {
					wp_die( esc_html__( 'You do not have permission to optimize images.', 'ewww-image-optimizer' ) );
				}
				wp_die( json_encode( array(
					'error' => esc_html__( 'You do not have permission to optimize images.', 'ewww-image-optimizer' ),
				) ) );
			}
			// Make sure we have an attachment ID.
			if ( empty( $_REQUEST['ewww_attachment_ID'] ) ) {
				if ( ! wp_doing_ajax() ) {
					wp_die( esc_html__( 'No attachment ID was provided.', 'ewww-image-optimizer' ) );
				}
				wp_die( json_encode( array(
					'error' => esc_html__( 'No attachment ID was provided.', 'ewww-image-optimizer' ),
				) ) );
			}
			$id = intval( $_REQUEST['ewww_attachment_ID'] );
			if ( empty( $_REQUEST['ewww_manual_nonce'] ) || ! wp_verify_nonce( $_REQUEST['ewww_manual_nonce'], "ewww-manual-$id" ) ) {
				if ( ! wp_doing_ajax() ) {
					wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
				}
				wp_die( json_encode( array(
					'error' => esc_html__( 'Access denied.', 'ewww-image-optimizer' ),
				) ) );
			}
			global $ewww_image;
			if ( ! class_exists( 'flagMeta' ) ) {
				require_once( FLAG_ABSPATH . 'lib/meta.php' );
			}
			// Retrieve the metadata for the image ID.
			$meta = new flagMeta( $id );
			// Determine the path of the image.
			$file_path = $meta->image->imagePath;
			$ewww_image = new EWWW_Image( $id, 'flag', $file_path );
			$ewww_image->resize = 'full';
			// Optimize the full size.
			$res = ewww_image_optimizer( $file_path, 3, false, false, true );
			if ( ! empty( $meta->image->meta_data['webview'] ) ) {
				// Determine path of the webview.
				$web_path = $meta->image->webimagePath;
				$ewww_image = new EWWW_Image( $id, 'flag', $web_path );
				$ewww_image->resize = 'webview';
				$wres = ewww_image_optimizer( $web_path, 3, false, true );
			}
			// Determine the path of the thumbnail.
			$thumb_path = $meta->image->thumbPath;
			$ewww_image = new EWWW_Image( $id, 'flag', $thumb_path );
			$ewww_image->resize = 'thumbnail';
			// Optimize the thumbnail.
			$tres = ewww_image_optimizer( $thumb_path, 3, false, true );
			if ( ! wp_doing_ajax() ) {
				// Get the referring page...
				$sendback = wp_get_referer();
				// and clean it up a bit.
				$sendback = preg_replace( '|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback );
				// Send the user back where they came from.
				wp_redirect( $sendback );
				die;
			}
			$success = $this->ewww_manage_image_custom_column( $id );
			wp_die( json_encode( array(
				'success' => $success,
			) ) );
		}

		/**
		 * Restore an image from the API.
		 */
		function ewww_flag_cloud_restore() {
			// Check permission of current user.
			$permissions = apply_filters( 'ewww_image_optimizer_manual_permissions', '' );
			if ( false === current_user_can( $permissions ) ) {
				if ( ! wp_doing_ajax() ) {
					wp_die( esc_html__( 'You do not have permission to optimize images.', 'ewww-image-optimizer' ) );
				}
				wp_die( json_encode( array(
					'error' => esc_html__( 'You do not have permission to optimize images.', 'ewww-image-optimizer' ),
				) ) );
			}
			// Make sure function wasn't called without an attachment to work with.
			if ( false === isset( $_REQUEST['ewww_attachment_ID'] ) ) {
				if ( ! wp_doing_ajax() ) {
					wp_die( esc_html__( 'No attachment ID was provided.', 'ewww-image-optimizer' ) );
				}
				wp_die( json_encode( array(
					'error' => esc_html__( 'No attachment ID was provided.', 'ewww-image-optimizer' ),
				) ) );
			}
			// Store the attachment $id.
			$id = intval( $_REQUEST['ewww_attachment_ID'] );
			if ( empty( $_REQUEST['ewww_manual_nonce'] ) || ! wp_verify_nonce( $_REQUEST['ewww_manual_nonce'], "ewww-manual-$id" ) ) {
				if ( ! wp_doing_ajax() ) {
						wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
				}
				wp_die( json_encode( array(
					'error' => esc_html__( 'Access denied.', 'ewww-image-optimizer' ),
				) ) );
			}
			if ( ! class_exists( 'flagMeta' ) ) {
				require_once( FLAG_ABSPATH . 'lib/meta.php' );
			}
			ewww_image_optimizer_cloud_restore_from_meta_data( $id, 'flag' );
			$success = $this->ewww_manage_image_custom_column( $id );
			die( json_encode( array(
				'success' => $success,
			) ) );
		}

		/**
		 * Initialize the bulk operation.
		 */
		function ewww_flag_bulk_init() {
			$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
			if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
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
				echo json_encode( $output );
				die();
			}
			$id = array_shift( $attachments );
			$file_name = $this->ewww_flag_bulk_filename( $id );
			$loading_image = plugins_url( '/images/wpspin.gif', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE );
			// Output the initial message letting the user know we are starting.
			if ( empty( $file_name ) ) {
				$output['results'] = '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' alt='loading'/></p>";
			} else {
				$output['results'] = '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . ' <b>' . $file_name . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
			}
			echo json_encode( $output );
			die();
		}

		/**
		 * Get the filename of the currently optimizing image.
		 *
		 * @param int $id The ID number for the image.
		 * @return string|bool The name of the first image in the queue, or false.
		 */
		function ewww_flag_bulk_filename( $id ) {
			// Need this file to work with flag meta.
			require_once( WP_CONTENT_DIR . '/plugins/flash-album-gallery/lib/meta.php' );
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
		function ewww_flag_bulk_loop() {
			global $ewww_defer;
			$ewww_defer = false;
			$output = array();
			$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
			if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
				$output['error'] = esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' );
				echo json_encode( $output );
				die();
			}
			session_write_close();
			// Find out if our nonce is on it's last leg/tick.
			$tick = wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' );
			if ( 2 === $tick ) {
				$output['new_nonce'] = wp_create_nonce( 'ewww-image-optimizer-bulk' );
			} else {
				$output['new_nonce'] = '';
			}
			global $ewww_image;
			// Need this file to work with flag meta.
			require_once( WP_CONTENT_DIR . '/plugins/flash-album-gallery/lib/meta.php' );
			// Record the starting time for the current image (in microseconds).
			$started = microtime( true );
			// Retrieve the list of attachments left to work on.
			$attachments = get_option( 'ewww_image_optimizer_bulk_flag_attachments' );
			$id = array_shift( $attachments );
			// Get the image meta for the current ID.
			$meta = new flagMeta( $id );
			$file_path = $meta->image->imagePath;
			$ewww_image = new EWWW_Image( $id, 'flag', $file_path );
			$ewww_image->resize = 'full';
			// Optimize the full-size version.
			$fres = ewww_image_optimizer( $file_path, 3, false, false, true );
			$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
			if ( ! empty( $ewww_status ) && preg_match( '/exceeded/', $ewww_status ) ) {
				$output['error'] = esc_html__( 'License Exceeded', 'ewww-image-optimizer' );
				echo json_encode( $output );
				die();
			}
			// Let the user know what happened.
			$output['results'] = sprintf( '<p>' . esc_html__( 'Optimized image:', 'ewww-image-optimizer' ) . ' <strong>%s</strong><br>', esc_html( $meta->image->filename ) );
			/* Translators: %s: The compression results/savings */
			$output['results'] .= sprintf( esc_html__( 'Full size – %s', 'ewww-image-optimizer' ) . '<br>', esc_html( $fres[1] ) );
			if ( ! empty( $meta->image->meta_data['webview'] ) ) {
				// Determine path of the webview.
				$web_path = $meta->image->webimagePath;
				$ewww_image = new EWWW_Image( $id, 'flag', $web_path );
				$ewww_image->resize = 'webview';
				$wres = ewww_image_optimizer( $web_path, 3, false, true );
				/* Translators: %s: The compression results/savings */
				$output['results'] .= sprintf( esc_html__( 'Optimized size – %s', 'ewww-image-optimizer' ) . '<br>', esc_html( $wres[1] ) );
			}
			$thumb_path = $meta->image->thumbPath;
			$ewww_image = new EWWW_Image( $id, 'flag', $thumb_path );
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
			$output['results'] .= sprintf( esc_html( _n( 'Elapsed: %s second', 'Elapsed: %s seconds', $elapsed, 'ewww-image-optimizer' ) ) . '</p>', number_format_i18n( $elapsed ) );
			$output['completed'] = 1;
			// Send the list back to the db.
			update_option( 'ewww_image_optimizer_bulk_flag_attachments', $attachments, false );
			if ( ! empty( $attachments ) ) {
				$next_attachment = array_shift( $attachments );
				$next_file = $this->ewww_flag_bulk_filename( $next_attachment );
				$loading_image = plugins_url( '/images/wpspin.gif', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE );
				if ( $next_file ) {
					$output['next_file'] = '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . " <b>$next_file</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
				} else {
					$output['next_file'] = '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' alt='loading'/></p>";
				}
			} else {
				$output['done'] = 1;
			}
				die( json_encode( $output ) );
		}

		/**
		 * Finish the bulk operation, and clear out the bulk_flag options.
		 */
		function ewww_flag_bulk_cleanup() {
			$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
			if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
				wp_die( esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) );
			}
			// Reset the bulk flags in the db.
			update_option( 'ewww_image_optimizer_bulk_flag_resume', '' );
			update_option( 'ewww_image_optimizer_bulk_flag_attachments', '', false );
			// And let the user know we are done.
			echo '<p><b>' . esc_html__( 'Finished Optimization!', 'ewww-image-optimizer' ) . '</b></p>';
			die();
		}

		/**
		 * Prepare javascript for one-click actions on manage gallery page.
		 *
		 * @param string $hook The hook value for the current page.
		 */
		function ewww_flag_manual_actions_script( $hook ) {
			if ( 'flagallery_page_flag-manage-gallery' != $hook ) {
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
					'restoring' => '<p>' . esc_html__( 'Restoring', 'ewww-image-optimizer' ) . " <img src='$loading_image' /></p>",
				)
			);
		}

		/**
		 * Add a column on the gallery display.
		 *
		 * @param array $columns A list of columns displayed on the manage gallery page.
		 * @return array The list of columns, with EWWW's custom column added.
		 */
		function ewww_manage_images_columns( $columns ) {
			$columns['ewww_image_optimizer'] = esc_html__( 'Image Optimizer', 'ewww-image-optimizer' );
			return $columns;
		}

		/**
		 * Output the EWWW IO information on the gallery display.
		 *
		 * @param int $id The ID number of the image being displayed.
		 */
		function ewww_manage_image_custom_column( $id ) {
			$output = "<div id='ewww-flag-status-$id'>";
			// Get the metadata.
			$meta = new flagMeta( $id );
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ewww_image_optimizer_function_exists( 'print_r' ) ) {
				$print_meta = print_r( $meta->image->meta_data, true );
				$print_meta = preg_replace( array( '/ /', '/\n+/' ), array( '&nbsp;', '<br />' ), esc_html( $print_meta ) );
				echo '<div style="background-color:#ffff99;font-size: 10px;padding: 10px;margin:-10px -10px 10px;line-height: 1.1em">' . $print_meta . '</div>';
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
			$type = ewww_image_optimizer_mimetype( $file_path, 'i' );
			$valid = true;
			// If we don't have a valid tool for the image type, output the appropriate message.
			$skip = ewww_image_optimizer_skip_tools();
			switch ( $type ) {
				case 'image/jpeg':
					if ( ! EWWW_IMAGE_OPTIMIZER_JPEGTRAN && ! $skip['jpegtran'] ) {
						/* translators: %s: name of a tool like jpegtran */
						$msg = '<div>' . sprintf( esc_html__( '%s is missing', 'ewww-image-optimizer' ), '<em>jpegtran</em>' ) . '</div>';
					}
					break;
				case 'image/png':
					if ( ! EWWW_IMAGE_OPTIMIZER_PNGOUT && ! EWWW_IMAGE_OPTIMIZER_OPTIPNG && ! $skip['optipng'] && ! $skip['pngout'] ) {
						/* translators: %s: name of a tool like jpegtran */
						$msg = '<div>' . sprintf( esc_html__( '%s is missing', 'ewww-image-optimizer' ), '<em>optipng/pngout</em>' ) . '</div>';
					}
					break;
				case 'image/gif':
					if ( ! EWWW_IMAGE_OPTIMIZER_GIFSICLE && ! $skip['gifsicle'] ) {
						/* translators: %s: name of a tool like jpegtran */
						$msg = '<div>' . sprintf( esc_html__( '%s is missing', 'ewww-image-optimizer' ), '<em>gifsicle</em>' ) . '</div>';
					}
					break;
				default:
					$msg = '<div>' . esc_html__( 'Unsupported file type', 'ewww-image-optimizer' ) . '</div>';
			}
			// Let user know if the file type is unsupported.
			if ( $msg ) {
				return $msg;
			}
			$backup_available = false;
			global $wpdb;
			$optimized_images = $wpdb->get_results( $wpdb->prepare( "SELECT image_size,orig_size,resize,converted,level,backup,updated FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'flag' AND image_size <> 0 ORDER BY orig_size DESC", $id ), ARRAY_A );
			$ewww_manual_nonce = wp_create_nonce( 'ewww-manual-' . $id );
			if ( ! empty( $optimized_images ) ) {
				list( $detail_output, $converted, $backup_available ) = ewww_image_optimizer_custom_column_results( $id, $optimized_images );
				$output .= $detail_output;
				if ( current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
					$output .= sprintf( '<a class="ewww-manual-optimize" data-id="%1$d" data-nonce="%2$s" href="admin.php?action=ewww_flag_manual&amp;ewww_manual_nonce=%2$s&amp;ewww_force=1&amp;ewww_attachment_ID=%1$d">%3$s</a>',
						$id,
						$ewww_manual_nonce,
						esc_html__( 'Re-optimize', 'ewww-image-optimizer' )
					);
					if ( $backup_available ) {
						$output .= sprintf( '<br><a class="ewww-manual-cloud-restore" data-id="%1$d" data-nonce="%2$s" href="admin.php?action=ewww_flag_cloud_restore&amp;ewww_manual_nonce=%2$s&amp;ewww_attachment_ID=%1$d">%3$s</a>',
							$id,
							$ewww_manual_nonce,
							esc_html__( 'Restore original', 'ewww-image-optimizer' )
						);
					}
				}
			} elseif ( get_transient( 'ewwwio-background-in-progress-flag-' . $id ) ) {
				$output .= esc_html__( 'In Progress', 'ewww-image-optimizer' );
				// Otherwise, tell the user that they can optimize the image now.
			} else {
				if ( current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
					$output .= sprintf( '<a class="ewww-manual-optimize" data-id="%1$d" data-nonce="%2$s" href="admin.php?action=ewww_flag_manual&amp;ewww_manual_nonce=%2$s&amp;ewww_attachment_ID=%1$d">%3$s</a>',
						$id,
						$ewww_manual_nonce,
						esc_html__( 'Optimize now!', 'ewww-image-optimizer' )
					);
				}
			}
			$output .= '</div>';
			return $output;
		}

		/**
		 * Wrapper around the custom column display when being called normally (no AJAX).
		 *
		 * @param string $column_name The name of the current column.
		 * @param int    $id The ID number of the image to display.
		 */
		function ewww_manage_image_custom_column_wrapper( $column_name, $id ) {
			// Check to make sure we're outputing our custom column.
			if ( 'ewww_image_optimizer' == $column_name ) {
				echo $this->ewww_manage_image_custom_column( $id );
			}
		}
	}

	global $ewwwflag;
	$ewwwflag = new EWWW_Flag();
} // End if().
