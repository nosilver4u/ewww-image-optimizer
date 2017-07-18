<?php
/**
 * Class and methods to integrate EWWW IO and Nextcellent Gallery.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'EWWW_Nextcellent' ) ) {
	/**
	 * Allows EWWW to integrate with the Nextcellent Gallery plugin.
	 *
	 * Adds automatic optimization on upload, a bulk optimizer, and compression details when
	 * managing galleries.
	 */
	class EWWW_Nextcellent {
		/**
		 * Initializes the nextcellent integration functions.
		 */
		function __construct() {
			add_filter( 'ngg_manage_images_columns', array( $this, 'ewww_manage_images_columns' ) );
			add_action( 'ngg_manage_image_custom_column', array( $this, 'ewww_manage_image_custom_column' ), 10, 2 );
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_background_optimization' ) ) {
				add_action( 'ngg_after_new_images_added', array( $this, 'dispatch_new_images' ), 10, 2 );
			} else {
				add_action( 'ngg_added_new_image', array( $this, 'ewww_added_new_image_slow' ) );
			}
			add_action( 'admin_enqueue_scripts', array( $this, 'ewww_ngg_manual_actions_script' ) );
			add_action( 'wp_ajax_ewww_ngg_manual', array( $this, 'ewww_ngg_manual' ) );
			add_action( 'wp_ajax_ewww_ngg_cloud_restore', array( $this, 'ewww_ngg_cloud_restore' ) );
			add_action( 'admin_action_ewww_ngg_manual', array( $this, 'ewww_ngg_manual' ) );
			add_action( 'admin_menu', array( $this, 'ewww_ngg_bulk_menu' ) );
			add_action( 'admin_head-galleries_page_nggallery-manage-gallery', array( $this, 'ewww_ngg_bulk_actions_script' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'ewww_ngg_bulk_script' ), 9 );
			add_action( 'wp_ajax_bulk_ngg_preview', array( $this, 'ewww_ngg_bulk_preview' ) );
			add_action( 'wp_ajax_bulk_ngg_init', array( $this, 'ewww_ngg_bulk_init' ) );
			add_action( 'wp_ajax_bulk_ngg_filename', array( $this, 'ewww_ngg_bulk_filename' ) );
			add_action( 'wp_ajax_bulk_ngg_loop', array( $this, 'ewww_ngg_bulk_loop' ) );
			add_action( 'wp_ajax_bulk_ngg_cleanup', array( $this, 'ewww_ngg_bulk_cleanup' ) );
			add_action( 'ngg_ajax_image_save', array( $this, 'ewww_ngg_image_save' ) );
		}

		/**
		 * Adds the Bulk Optimize page to the tools menu.
		 */
		function ewww_ngg_bulk_menu() {
			add_submenu_page( NGGFOLDER, esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ), esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ), 'NextGEN Manage gallery', 'ewww-ngg-bulk', array( &$this, 'ewww_ngg_bulk_preview' ) );
		}

		/**
		 * Adds a newly uploaded image to the optimization queue.
		 *
		 * @param int   $gallery The gallery ID number (I think).
		 * @param array $images The list of new images.
		 */
		function dispatch_new_images( $gallery, $images ) {
			global $ewwwio_ngg_background;
			if ( ! class_exists( 'WP_Background_Process' ) ) {
				require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
			}
			if ( ! is_object( $ewwwio_ngg_background ) ) {
				$ewwwio_ngg_background = new EWWWIO_Ngg_Background_Process();
			}
			foreach ( $images as $id ) {
				$ewwwio_ngg_background->push_to_queue( array(
					'id' => $id,
				) );
				set_transient( 'ewwwio-background-in-progress-ngg-' . $id, true, 24 * HOUR_IN_SECONDS );
				ewwwio_debug_message( "optimization (nextcellent) queued for $id" );
			}
			$ewwwio_ngg_background->save()->dispatch();
			ewww_image_optimizer_debug_log();
		}

		/**
		 * Optimizes a new image from the queue.
		 *
		 * @global object $ewww_image Contains more information about the image currently being processed.
		 * @global object $nggdb
		 *
		 * @param int   $id The ID number of the image.
		 * @param array $meta The image metadata.
		 */
		function ewww_added_new_image( $id, $meta ) {
			global $ewww_image;
			// Retrieve the image path.
			$file_path = $meta->image->imagePath;
			$ewww_image = new EWWW_Image( $id, 'nextcell', $file_path );
			$ewww_image->resize = 'full';
			// Run the optimizer on the current image.
			$fres = ewww_image_optimizer( $file_path, 2, false, false, true );
			// Update the metadata for the optimized image.
			global $nggdb;
			$nggdb->update_image_meta( $id, array(
				'ewww_image_optimizer' => $fres[1],
			) );
		}

		/**
		 * Optimizes a new image in foreground mode.
		 *
		 * @global bool $ewww_defer Set to false to avoid deferring image optimization.
		 * @global object $wpdb
		 * @global object $ewww_image Contains more information about the image currently being processed.
		 *
		 * @param array $image The new image and all the related data.
		 */
		function ewww_added_new_image_slow( $image ) {
			// Query the filesystem path of the gallery from the database.
			global $ewww_defer;
			global $wpdb;
			global $ewww_image;
			$gallery_path = $wpdb->get_var( $wpdb->prepare( "SELECT path FROM {$wpdb->prefix}ngg_gallery WHERE gid = %d LIMIT 1", $image['galleryID'] ) );
			// If we have a path to work with.
			if ( $gallery_path ) {
				// Construct the absolute path of the current image.
				$file_path = trailingslashit( $gallery_path ) . $image['filename'];
				$ewww_image = new EWWW_Image( $image['id'], 'nextcell', $file_path );
				$ewww_image->resize = 'full';
				// Run the optimizer on the current image.
				$res = ewww_image_optimizer( ABSPATH . $file_path, 2, false, false, true );
				// Update the metadata for the optimized image.
				nggdb::update_image_meta( $image['id'], array(
					'ewww_image_optimizer' => $res[1],
				) );
			}
		}

		/**
		 * Optimizes the thumbnail generated for a new upload.
		 *
		 * @global bool $ewww_defer Set to false to avoid deferring image optimization.
		 * @global object $ewww_image Contains more information about the image currently being processed.
		 *
		 * @param string $filename The name of the file generated.
		 */
		function ewww_ngg_image_save( $filename ) {
			ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
			global $ewww_defer;
			global $ewww_image;
			if ( file_exists( $filename ) ) {
				if ( ! empty( $_POST['id'] ) ) {
					$id = (int) $_POST['id'];
				} elseif ( ! empty( $_POST['image'] ) && is_numeric( $_POST['image'] ) ) {
					$id = (int) $_POST['image'];
				}
				$ewww_image = new EWWW_Image( $id, 'nextcell', $filename );
				$ewww_image->resize = 'thumbnail';
				ewww_image_optimizer( $filename );
			}
			ewww_image_optimizer_debug_log();
			ewwwio_memory( __FUNCTION__ );
		}

		/**
		 * Manually process an image from the NextGEN Gallery.
		 */
		function ewww_ngg_manual() {
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
			if ( empty( $_REQUEST['ewww_attachment_ID'] ) ) {
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
			$this->ewww_ngg_optimize( $id );
			$success = $this->ewww_manage_image_custom_column( 'ewww_image_optimizer', $id, true );
			ewww_image_optimizer_debug_log();
			if ( ! wp_doing_ajax() ) {
				// Get the referring page, and send the user back there.
				$sendback = wp_get_referer();
				$sendback = preg_replace( '|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback );
				wp_redirect( $sendback );
				die;
			}
			die( json_encode( array(
				'success' => $success,
			) ) );
		}

		/**
		 * Restore an image from the NextGEN Gallery.
		 */
		function ewww_ngg_cloud_restore() {
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
			// Sanitize the attachment $id.
			$id = intval( $_REQUEST['ewww_attachment_ID'] );
			if ( empty( $_REQUEST['ewww_manual_nonce'] ) || ! wp_verify_nonce( $_REQUEST['ewww_manual_nonce'], "ewww-manual-$id" ) ) {
				if ( ! wp_doing_ajax() ) {
						wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
				}
				wp_die( json_encode( array(
					'error' => esc_html__( 'Access denied.', 'ewww-image-optimizer' ),
				) ) );
			}
			ewww_image_optimizer_cloud_restore_from_meta_data( $id, 'nextcell' );
			$success = $this->ewww_manage_image_custom_column( 'ewww_image_optimizer', $id, true );
			die( json_encode( array(
				'success' => $success,
			) ) );
		}

		/**
		 * Optimize a nextcellent image by ID.
		 *
		 * @global object $ewww_image Contains more information about the image currently being processed.
		 * @global object nggdb
		 *
		 * @param int $id The ID number of the image.
		 * @return array {
		 *	The optimization results for the image.
		 *
		 *	@type array $fres The optimization results for the full-size image.
		 *	@type array $tres The optimization results for the thumbnail.
		 * }
		 */
		function ewww_ngg_optimize( $id ) {
			global $ewww_image;
			// Need this file to work with metadata.
			require_once( WP_CONTENT_DIR . '/plugins/nextcellent-gallery-nextgen-legacy/lib/meta.php' );
			// Retrieve the metadata for the image.
			$meta = new nggMeta( $id );
			// Retrieve the image path.
			$file_path = $meta->image->imagePath;
			$ewww_image = new EWWW_Image( $id, 'nextcell', $file_path );
			$ewww_image->resize = 'full';
			// Run the optimizer on the current image.
			$fres = ewww_image_optimizer( $file_path, 2, false, false, true );
			// Update the metadata for the optimized image.
			global $nggdb;
			$nggdb->update_image_meta( $id, array(
				'ewww_image_optimizer' => $fres[1],
			) );
			// Get the filepath of the thumbnail image.
			$thumb_path = $meta->image->thumbPath;
			$ewww_image = new EWWW_Image( $id, 'nextcell', $thumb_path );
			$ewww_image->resize = 'thumbnail';
			// Run the optimization on the thumbnail.
			$tres = ewww_image_optimizer( $thumb_path, 2, false, true );
			return array( $fres, $tres );
		}

		/**
		 * Adds the Image Optimizer column via the ngg_manage_images_columns hook.
		 *
		 * @param array $columns A list of columns to display in the images table.
		 * @return array The updated list of columns.
		 */
		function ewww_manage_images_columns( $columns ) {
			$columns['ewww_image_optimizer'] = esc_html__( 'Image Optimizer', 'ewww-image-optimizer' );
			return $columns;
		}

		/**
		 * Displays the Image Optimizer data via the ngg_manage_image_custom_column hook.
		 *
		 * @param string $column_name The name of the current column.
		 * @param int    $id The ID number of the current image.
		 * @param bool   $return Return the output instead of sending it straight to the screen.
		 * @return string The output when $return is true.
		 */
		function ewww_manage_image_custom_column( $column_name, $id, $return = false ) {
			// Once we've found our custom column.
			if ( 'ewww_image_optimizer' == $column_name ) {
				// Need this file to work with metadata.
				require_once( WP_CONTENT_DIR . '/plugins/nextcellent-gallery-nextgen-legacy/lib/meta.php' );
				// Get the metadata for the image.
				$meta = new nggMeta( $id );
				// Get the optimization status for the image.
				$status = $meta->get_META( 'ewww_image_optimizer' );
				$output = "<div id='ewww-nextcellent-status-$id'>";
				$msg = '';
				// Get the file path of the image.
				$file_path = $meta->image->imagePath;
				// Get the mimetype of the image.
				$type = ewww_image_optimizer_quick_mimetype( $file_path, 'i' );

				// Check to see if we have a tool to handle the mimetype detected.
				if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_JPEGTRAN' ) ) {
					ewww_image_optimizer_tool_init();
					ewww_image_optimizer_notice_utils( 'quiet' );
				}
				$skip = ewww_image_optimizer_skip_tools();
				switch ( $type ) {
					case 'image/jpeg':
						// If jpegtran is missing, tell the user.
						if ( ! EWWW_IMAGE_OPTIMIZER_JPEGTRAN && ! $skip['jpegtran'] ) {
							/* translators: %s: name of a tool like jpegtran */
							$msg = '<div>' . sprintf( esc_html__( '%s is missing', 'ewww-image-optimizer' ), '<em>jpegtran</em>' ) . '</div>';
						}
						break;
					case 'image/png':
						// If the PNG tools are missing, tell the user.
						if ( ! EWWW_IMAGE_OPTIMIZER_PNGOUT && ! EWWW_IMAGE_OPTIMIZER_OPTIPNG && ! $skip['optipng'] && ! $skip['pngout'] ) {
							/* translators: %s: name of a tool like jpegtran */
							$msg = '<div>' . sprintf( esc_html__( '%s is missing', 'ewww-image-optimizer' ), '<em>optipng/pngout</em>' ) . '</div>';
						}
						break;
					case 'image/gif':
						// If gifsicle is missing, tell the user.
						if ( ! EWWW_IMAGE_OPTIMIZER_GIFSICLE && ! $skip['gifsicle'] ) {
							/* translators: %s: name of a tool like jpegtran */
							$msg = '<div>' . sprintf( esc_html__( '%s is missing', 'ewww-image-optimizer' ), '<em>gifsicle</em>' ) . '</div>';
						}
						break;
					default:
						$msg = '<div>' . esc_html__( 'Unsupported file type', 'ewww-image-optimizer' ) . '</div>';
				}
				// File isn't in a format we can work with, we don't work with strangers.
				if ( $msg ) {
					if ( $return ) {
						return $msg;
					}
					echo $msg;
					return;
				}
				if ( ! empty( $status ) ) {
					ewww_image_optimizer_update_file_from_meta( $file_path, 'nextcell', $id, 'full' );
					$thumb_path = $meta->image->thumbPath;
					ewww_image_optimizer_update_file_from_meta( $thumb_path, 'nextcell', $id, 'thumbnail' );
				}
				$backup_available = false;
				global $wpdb;
				$optimized_images = $wpdb->get_results( $wpdb->prepare( "SELECT image_size,orig_size,resize,converted,level,backup,updated FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'nextcell' AND image_size <> 0 ORDER BY orig_size DESC", $id ), ARRAY_A );
				$ewww_manual_nonce = wp_create_nonce( 'ewww-manual-' . $id );
				// If we have a valid status, display it, the image size, and give a re-optimize link.
				if ( ! empty( $optimized_images ) ) {
					list( $detail_output, $converted, $backup_available ) = ewww_image_optimizer_custom_column_results( $id, $optimized_images );
					$output .= $detail_output;
					if ( current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
						$output .= sprintf( '<a class="ewww-manual-optimize" data-id="%1$d" data-nonce="%2$s" href="admin.php?action=ewww_ngg_manual&amp;ewww_manual_nonce=%2$s&amp;ewww_force=1&amp;ewww_attachment_ID=%1$d">%3$s</a>',
							$id,
							$ewww_manual_nonce,
							esc_html__( 'Re-optimize', 'ewww-image-optimizer' )
						);
						if ( $backup_available ) {
							$output .= sprintf( '<br><a class="ewww-manual-cloud-restore" data-id="%1$d" data-nonce="%2$s" href="admin.php?action=ewww_ngg_cloud_restore&amp;ewww_manual_nonce=%2$s&amp;ewww_attachment_ID=%1$d">%3$s</a>',
								$id,
								$ewww_manual_nonce,
								esc_html__( 'Restore original', 'ewww-image-optimizer' )
							);
						}
					}
				} elseif ( get_transient( 'ewwwio-background-in-progress-ngg-' . $id ) ) {
					$output .= esc_html__( 'In Progress', 'ewww-image-optimizer' );
					// Otherwise, give the image size, and a link to optimize right now.
				} else {
					if ( current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
						$output .= sprintf( '<a class="ewww-manual-optimize" data-id="%1$d" data-nonce="%2$s" href="admin.php?action=ewww_ngg_manual&amp;ewww_manual_nonce=%2$s&amp;ewww_attachment_ID=%1$d">%3$s</a>',
							$id,
							$ewww_manual_nonce,
							esc_html__( 'Optimize now!', 'ewww-image-optimizer' )
						);
					}
				}
				$output .= '</div>';
				if ( $return ) {
					return $output;
				}
				echo $output;
			} // End if().
		}

		/**
		 * Output the html for the bulk optimize page.
		 */
		function ewww_ngg_bulk_preview() {
			if ( ! empty( $_REQUEST['doaction'] ) ) {
				// If there is no requested bulk action, do nothing.
				if ( empty( $_REQUEST['bulkaction'] ) ) {
					return;
				}
				// If there is no media to optimize, do nothing.
				if ( empty( $_REQUEST['doaction'] ) || ! is_array( $_REQUEST['doaction'] ) ) {
					return;
				}
			}
			// Retrieve the attachments array from the db.
			$attachments = get_option( 'ewww_image_optimizer_bulk_ngg_attachments' );
			// Make sure there are some attachments to process.
			if ( count( $attachments ) < 1 ) {
				echo '<p>' . esc_html__( 'You do not appear to have uploaded any images yet.', 'ewww-image-optimizer' ) . '</p>';
				return;
			}
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Bulk Optimize', 'ewww-image-optimizer' ); ?></h1><?php
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
					ewww_image_optimizer_cloud_verify();
					echo '<a id="ewww-bulk-credits-available" target="_blank" class="page-title-action" style="float:right;" href="https://ewww.io/my-account/">' . esc_html__( 'Image credits available:', 'ewww-image-optimizer' ) . ' ' . ewww_image_optimizer_cloud_quota() . '</a>';
				}
				// Retrieve the value of the 'bulk resume' option and set the button text for the form to use.
				$resume = get_option( 'ewww_image_optimizer_bulk_ngg_resume' );
				if ( empty( $resume ) ) {
						$button_text = esc_attr__( 'Start optimizing', 'ewww-image-optimizer' );
				} else {
					$button_text = esc_attr__( 'Resume previous bulk operation', 'ewww-image-optimizer' );
				}
				/* translators: %d: number of images */
				$selected_images_text = sprintf( esc_html( _n( 'There is %d image ready to optimize.', 'There are %d images ready to optimize.', count( $attachments ), 'ewww-image-optimizer' ) ), count( $attachments ) );
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
				<div id="ewww-bulk-forms">
				<p class="ewww-bulk-info"><?php echo $selected_images_text; ?><br />
			<?php esc_html_e( 'Previously optimized images will be skipped by default.', 'ewww-image-optimizer' ); ?></p>
				<form id="ewww-bulk-start" class="ewww-bulk-form" method="post" action="">
				<input type="hidden" id="ewww-delay" name="ewww-delay" value="0">
						<input type="submit" class="button-secondary action" value="<?php echo $button_text; ?>" />
				</form>
				<?php
				// If there is a previous bulk operation to resume, give the user the option to reset the resume flag.
				if ( ! empty( $resume ) ) { ?>
						<p class="ewww-bulk-info"><?php esc_html_e( 'If you would like to start over again, press the Reset Status button to reset the bulk operation status.', 'ewww-image-optimizer' ); ?></p>
						<form id="ewww-bulk-reset" class="ewww-bulk-form" method="post" action="">
								<?php wp_nonce_field( 'ewww-image-optimizer-bulk-reset', 'ewww_wpnonce' ); ?>
								<input type="hidden" name="ewww_reset" value="1">
								<input type="submit" class="button-secondary action" value="<?php esc_attr_e( 'Reset Status', 'ewww-image-optimizer' ); ?>" />
						</form>
<?php				}
				echo '</div></div>';
				if ( ! empty( $_REQUEST['ewww_inline'] ) ) {
					die();
				}
				return;
		}

		/**
		 * Prepares the javascript for a bulk operation.
		 *
		 * @global object $nggdb
		 *
		 * @param string $hook The hook identifier for the current page.
		 */
		function ewww_ngg_bulk_script( $hook ) {
			ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
			$i18ngg = strtolower( __( 'Galleries', 'nggallery' ) );
			ewwwio_debug_message( "i18n string for galleries: $i18ngg" );
			// Make sure we are on a legitimate page and that we have the proper POST variables if necessary.
			if ( $i18ngg . '_page_ewww-ngg-bulk' != $hook && $i18ngg . '_page_nggallery-manage-gallery' != $hook ) {
				return;
			}
			if ( $i18ngg . '_page_nggallery-manage-gallery' == $hook && ( empty( $_REQUEST['bulkaction'] ) || 'bulk_optimize' != $_REQUEST['bulkaction'] ) ) {
				return;
			}
			if ( $i18ngg . '_page_nggallery-manage-gallery' == $hook && ( empty( $_REQUEST['doaction'] ) || ! is_array( $_REQUEST['doaction'] ) ) ) {
				return;
			}
			$images = null;
			// See if the user wants to reset the previous bulk status.
			if ( ! empty( $_REQUEST['ewww_reset'] ) && wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk-reset' ) ) {
				update_option( 'ewww_image_optimizer_bulk_ngg_resume', '' );
			}
			// See if there is a previous operation to resume.
			$resume = get_option( 'ewww_image_optimizer_bulk_ngg_resume' );
			// If we've been given a bulk action to perform.
			if ( ! empty( $_REQUEST['doaction'] ) ) {
				// If we are optimizing a specific group of images.
				if ( 'manage-images' == $_REQUEST['page'] && 'bulk_optimize' == $_REQUEST['bulkaction'] ) {
					ewwwio_debug_message( 'optimizing a group of images' );
					check_admin_referer( 'ngg_updategallery' );
					// Reset the resume status, not allowed here.
					update_option( 'ewww_image_optimizer_bulk_ngg_resume', '' );
					// Retrieve the image IDs from POST.
					$images = array_map( 'intval', $_REQUEST['doaction'] );
				}
				// If we are optimizing a specific group of galleries.
				if ( 'manage-galleries' == $_REQUEST['page'] && 'bulk_optimize' == $_REQUEST['bulkaction'] ) {
					ewwwio_debug_message( 'optimizing a group of galleries' );
					check_admin_referer( 'ngg_bulkgallery' );
					global $nggdb;
					// Reset the resume status, not allowed here.
					update_option( 'ewww_image_optimizer_bulk_ngg_resume', '' );
					$ids = array();
					$gids = array_map( 'intval', $_REQUEST['doaction'] );
					// For each gallery we are given.
					foreach ( $gids as $gid ) {
						// Get a list of IDs.
						$gallery_list = $nggdb->get_gallery( $gid );
						// For each ID.
						foreach ( $gallery_list as $image ) {
							// Add it to the array.
							$images[] = $image->pid;
						}
					}
				}
			} elseif ( ! empty( $resume ) ) {
				// Otherwise, if we have an operation to resume.
				ewwwio_debug_message( 'resuming a previous operation (maybe)' );
				// Get the list of attachment IDs from the db.
				$images = get_option( 'ewww_image_optimizer_bulk_ngg_attachments' );
				// Otherwise, if we are on the standard bulk page, get all the images in the db.
			} elseif ( $hook == $i18ngg . '_page_ewww-ngg-bulk' ) {
				ewwwio_debug_message( 'starting from scratch, grabbing all the images' );
				global $wpdb;
				$images = $wpdb->get_col( "SELECT pid FROM $wpdb->nggpictures ORDER BY sortorder ASC" );
			} else {
				ewwwio_debug_message( $hook );
			} // End if().

			// Store the image IDs to process in the db.
			update_option( 'ewww_image_optimizer_bulk_ngg_attachments', $images, false );
			// Add the EWWW IO script.
			wp_enqueue_script( 'ewwwbulkscript', plugins_url( '/includes/eio.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array( 'jquery', 'jquery-ui-progressbar', 'jquery-ui-slider', 'postbox', 'dashboard' ), EWWW_IMAGE_OPTIMIZER_VERSION );
			// Replacing the built-in nextgen styling rules for progressbar.
			wp_register_style( 'ngg-jqueryui', plugins_url( '/includes/jquery-ui-1.10.1.custom.css', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ) );
			// Enqueue the progressbar styling.
			wp_enqueue_style( 'ngg-jqueryui' );
			// Include all the vars we need for javascript.
			wp_localize_script( 'ewwwbulkscript', 'ewww_vars', array(
				'_wpnonce' => wp_create_nonce( 'ewww-image-optimizer-bulk' ),
				'gallery' => 'nextgen',
				'attachments' => count( $images ),
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
		 * Start the bulk operation.
		 */
		function ewww_ngg_bulk_init() {
			$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
			if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
				wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
			}
			$output = array();
			// Toggle the resume flag to indicate an operation is in progress.
			update_option( 'ewww_image_optimizer_bulk_ngg_resume', 'true' );
			// Get the list of attachments remaining from the db.
			$attachments = get_option( 'ewww_image_optimizer_bulk_ngg_attachments' );
			if ( ! is_array( $attachments ) && ! empty( $attachments ) ) {
				$attachments = unserialize( $attachments );
			}
			if ( ! is_array( $attachments ) ) {
				$output['error'] = esc_html__( 'Error retrieving list of images' );
				echo json_encode( $output );
				die();
			}
			$id = array_shift( $attachments );
			$file_name = $this->ewww_ngg_bulk_filename( $id );
			// Let the user know we are starting.
			$loading_image = plugins_url( '/images/wpspin.gif', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE );
			if ( empty( $file_name ) ) {
				$output['results'] = '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' alt='loading'/></p>";
			} else {
				$output['results'] = '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . ' <b>' . $file_name . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
			}
			echo json_encode( $output );
			die();
		}

		/**
		 * Retrieve the filename of the image being optimized.
		 *
		 * @param int $id The ID number of the image.
		 */
		function ewww_ngg_bulk_filename( $id ) {
			// Need this file to work with metadata.
			require_once( WP_CONTENT_DIR . '/plugins/nextcellent-gallery-nextgen-legacy/lib/meta.php' );
			// Get the meta for the image.
			$meta = new nggMeta( $id );
			// Get the filename for the image, and output our current status.
			$file_name = esc_html( $meta->image->filename );
			if ( $file_name ) {
				return $file_name;
			} else {
				return false;
			}
		}

		/**
		 * Process each image in the bulk queue.
		 *
		 * @global bool $ewww_defer Set to false to avoid deferring image optimization.
		 */
		function ewww_ngg_bulk_loop() {
			global $ewww_defer;
			$ewww_defer = false;
			$output = array();
			$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
			if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
				$outupt['error'] = esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' );
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
			// Need this file to work with metadata.
			require_once( WP_CONTENT_DIR . '/plugins/nextcellent-gallery-nextgen-legacy/lib/meta.php' );
			// Find out what time we started, in microseconds.
			$started = microtime( true );
			// Get the list of attachments remaining from the db.
			$attachments = get_option( 'ewww_image_optimizer_bulk_ngg_attachments' );
			$id = array_shift( $attachments );
			list( $fres, $tres ) = $this->ewww_ngg_optimize( $id );
			$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
			if ( ! empty( $ewww_status ) && preg_match( '/exceeded/', $ewww_status ) ) {
				$output['error'] = esc_html__( 'License Exceeded', 'ewww-image-optimizer' );
				echo json_encode( $output );
				die();
			}
			// Output the results of the optimization.
			if ( $fres[0] ) {
				$output['results'] = sprintf( '<p>' . esc_html__( 'Optimized image:', 'ewww-image-optimizer' ) . ' <strong>%s</strong><br>', esc_html( $fres[0] ) );
			}
			/* Translators: %s: The compression results/savings */
			$output['results'] .= sprintf( esc_html__( 'Full size - %s', 'ewww-image-optimizer' ) . '<br>', esc_html( $fres[1] ) );
			// Output the results of the thumb optimization.
			/* Translators: %s: The compression results/savings */
			$output['results'] .= sprintf( esc_html__( 'Thumbnail - %s', 'ewww-image-optimizer' ) . '<br>', esc_html( $tres[1] ) );
			// Output how much time we spent.
			$elapsed = microtime( true ) - $started;
			/* Translators: %s: localized number of seconds */
			$output['results'] .= sprintf( esc_html( _n( 'Elapsed: %s second', 'Elapsed: %s seconds', $elapsed, 'ewww-image-optimizer' ) ) . '</p>', number_format_i18n( $elapsed ) );
			$output['completed'] = 1;
			// Store the list back in the db.
			update_option( 'ewww_image_optimizer_bulk_ngg_attachments', $attachments, false );
			if ( ! empty( $attachments ) ) {
				$next_attachment = array_shift( $attachments );
				$next_file = $this->ewww_ngg_bulk_filename( $next_attachment );
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
		 * Finish the bulk operation.
		 */
		function ewww_ngg_bulk_cleanup() {
			$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
			if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
				wp_die( esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) );
			}
			// Reset all the bulk options in the db...
			update_option( 'ewww_image_optimizer_bulk_ngg_resume', '' );
			update_option( 'ewww_image_optimizer_bulk_ngg_attachments', '', false );
			// and let the user know we are done.
			echo '<p><b>' . esc_html__( 'Finished Optimization!', 'ewww-image-optimizer' ) . '</b></p>';
			die();
		}

		/**
		 * Prepare javascript for one-click actions on manage gallery page.
		 *
		 * @param string $hook The hook value for the current page.
		 */
		function ewww_ngg_manual_actions_script( $hook ) {
			if ( 'galleries_page_nggallery-manage-gallery' != $hook ) {
				return;
			}
			if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				return;
			}
			add_thickbox();
			wp_enqueue_script( 'ewwwnextcellentscript', plugins_url( '/includes/nextcellent.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION );
			wp_enqueue_style( 'jquery-ui-tooltip-custom', plugins_url( '/includes/jquery-ui-1.10.1.custom.css', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
			// Submit a couple variables needed for javascript functions.
			$loading_image = plugins_url( '/images/spinner.gif', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE );
			wp_localize_script(
				'ewwwnextcellentscript',
				'ewww_vars',
				array(
					'optimizing' => '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . " <img src='$loading_image' /></p>",
					'restoring' => '<p>' . esc_html__( 'Restoring', 'ewww-image-optimizer' ) . " <img src='$loading_image' /></p>",
				)
			);
		}

		/**
		 * Insert a bulk optimize option in the actions list for the gallery and image management pages (via javascript, since we have no hooks).
		 */
		function ewww_ngg_bulk_actions_script() {
			if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_bulk_permissions', '' ) ) ) {
				return;
			}
	?>		<script type="text/javascript">
			jQuery(document).ready(function($){
				$('select[name^="bulkaction"] option:last-child').after('<option value="bulk_optimize">Bulk Optimize</option>');
			});
		</script>
	<?php	}
	}

	global $ewwwngg;
	$ewwwngg = new EWWW_Nextcellent();
} // End if().
