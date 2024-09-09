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
		public function __construct() {
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
			add_action( 'admin_enqueue_scripts', array( $this, 'ewww_ngg_bulk_script' ), 9 );
			add_action( 'wp_ajax_bulk_ngg_init', array( $this, 'ewww_ngg_bulk_init' ) );
			add_action( 'wp_ajax_bulk_ngg_loop', array( $this, 'ewww_ngg_bulk_loop' ) );
			add_action( 'wp_ajax_bulk_ngg_cleanup', array( $this, 'ewww_ngg_bulk_cleanup' ) );
			add_action( 'ngg_ajax_image_save', array( $this, 'ewww_ngg_image_save' ) );
		}

		/**
		 * Adds the Bulk Optimize page to the tools menu.
		 */
		public function ewww_ngg_bulk_menu() {
			add_submenu_page( NGGFOLDER, esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ), esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ), 'NextGEN Manage gallery', 'ewww-ngg-bulk', array( &$this, 'ewww_ngg_bulk_preview' ) );
		}

		/**
		 * Adds a newly uploaded image to the optimization queue.
		 *
		 * @param int   $gallery The gallery ID number (I think).
		 * @param array $images The list of new images.
		 */
		public function dispatch_new_images( $gallery, $images ) {
			foreach ( $images as $id ) {
				ewwwio()->background_ngg->push_to_queue( array( 'id' => $id ) );
				ewwwio_debug_message( "optimization (nextcellent) queued for $id" );
			}
			ewwwio()->background_ngg->dispatch();
		}

		/**
		 * Optimizes a new image from the queue.
		 *
		 * @global object $ewww_image Contains more information about the image currently being processed.
		 *
		 * @param int   $id The ID number of the image.
		 * @param array $meta The image metadata.
		 */
		public function ewww_added_new_image( $id, $meta ) {
			global $ewww_image;
			// Retrieve the image path.
			$file_path          = $meta->image->imagePath;
			$ewww_image         = new EWWW_Image( $id, 'nextcell', $file_path );
			$ewww_image->resize = 'full';
			// Run the optimizer on the current image.
			$fres = ewww_image_optimizer( $file_path, 2, false, false, true );
		}

		/**
		 * Optimizes a new image in foreground mode.
		 *
		 * @global object $wpdb
		 * @global object $ewww_image Contains more information about the image currently being processed.
		 *
		 * @param array $image The new image and all the related data.
		 */
		public function ewww_added_new_image_slow( $image ) {
			// Query the filesystem path of the gallery from the database.
			global $wpdb;
			global $ewww_image;
			$gallery_path = $wpdb->get_var( $wpdb->prepare( "SELECT path FROM {$wpdb->prefix}ngg_gallery WHERE gid = %d LIMIT 1", $image['galleryID'] ) );
			// If we have a path to work with.
			if ( $gallery_path ) {
				// Construct the absolute path of the current image.
				$file_path          = trailingslashit( $gallery_path ) . $image['filename'];
				$ewww_image         = new EWWW_Image( $image['id'], 'nextcell', $file_path );
				$ewww_image->resize = 'full';
				// Run the optimizer on the current image.
				$res = ewww_image_optimizer( ABSPATH . $file_path, 2, false, false, true );
			}
		}

		/**
		 * Optimizes the thumbnail generated for a new upload.
		 *
		 * @global object $ewww_image Contains more information about the image currently being processed.
		 *
		 * @param string $filename The name of the file generated.
		 */
		public function ewww_ngg_image_save( $filename ) {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			global $ewww_image;
			ewwwio_debug_message( 'nextcellent new image thumb' );
			if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'ngg-ajax' ) ) {
				ewwwio_debug_message( 'failed verification' );
				return;
			}
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ewww_image_optimizer_function_exists( 'print_r' ) ) {
				ewwwio_debug_message( print_r( $_REQUEST, true ) );
			}
			if ( file_exists( $filename ) ) {
				if ( ! empty( $_POST['id'] ) ) {
					$id = (int) $_POST['id'];
				} elseif ( ! empty( $_POST['image'] ) ) {
					$id = sanitize_key( $_POST['image'] );
				}
				$ewww_image         = new EWWW_Image( $id, 'nextcell', $filename );
				$ewww_image->resize = 'thumbnail';
				ewww_image_optimizer( $filename );
			}
			ewwwio_memory( __METHOD__ );
		}

		/**
		 * Manually process an image from the NextGEN Gallery.
		 */
		public function ewww_ngg_manual() {
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
			if ( empty( $_REQUEST['ewww_attachment_ID'] ) ) {
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
			ewwwio()->force = ! empty( $_REQUEST['ewww_force'] ) ? true : false;
			$this->ewww_ngg_optimize( $id );
			$success = $this->ewww_manage_image_custom_column( 'ewww_image_optimizer', $id, true );
			if ( ! wp_doing_ajax() ) {
				// Get the referring page, and send the user back there.
				wp_safe_redirect( wp_get_referer() );
				die;
			}
			ewwwio_ob_clean();
			wp_die( wp_json_encode( array( 'success' => $success ) ) );
		}

		/**
		 * Restore an image from the NextGEN Gallery.
		 */
		public function ewww_ngg_cloud_restore() {
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
			if ( ! isset( $_REQUEST['ewww_attachment_ID'] ) ) {
				if ( ! wp_doing_ajax() ) {
					wp_die( esc_html__( 'No attachment ID was provided.', 'ewww-image-optimizer' ) );
				}
				ewwwio_ob_clean();
				wp_die( wp_json_encode( array( 'error' => esc_html__( 'No attachment ID was provided.', 'ewww-image-optimizer' ) ) ) );
			}
			// Sanitize the attachment $id.
			$id = (int) $_REQUEST['ewww_attachment_ID'];
			if ( empty( $_REQUEST['ewww_manual_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_manual_nonce'] ), "ewww-manual-$id" ) ) {
				if ( ! wp_doing_ajax() ) {
						wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
				}
				ewwwio_ob_clean();
				wp_die( wp_json_encode( array( 'error' => esc_html__( 'Access denied.', 'ewww-image-optimizer' ) ) ) );
			}
			ewww_image_optimizer_cloud_restore_from_meta_data( $id, 'nextcell' );
			$success = $this->ewww_manage_image_custom_column( 'ewww_image_optimizer', $id, true );
			ewwwio_ob_clean();
			wp_die( wp_json_encode( array( 'success' => $success ) ) );
		}

		/**
		 * Optimize a nextcellent image by ID.
		 *
		 * @global object $ewww_image Contains more information about the image currently being processed.
		 * @global object nggdb
		 *
		 * @param int $id The ID number of the image.
		 * @return array {
		 *     The optimization results for the image.
		 *
		 *     @type array $fres The optimization results for the full-size image.
		 *     @type array $tres The optimization results for the thumbnail.
		 * }
		 */
		public function ewww_ngg_optimize( $id ) {
			global $ewww_image;
			// Need this file to work with metadata.
			require_once WP_CONTENT_DIR . '/plugins/nextcellent-gallery-nextgen-legacy/lib/meta.php';
			// Retrieve the metadata for the image.
			$meta = new nggMeta( $id );
			// Retrieve the image path.
			$file_path          = $meta->image->imagePath;
			$ewww_image         = new EWWW_Image( $id, 'nextcell', $file_path );
			$ewww_image->resize = 'full';
			// Run the optimizer on the current image.
			$fres = ewww_image_optimizer( $file_path, 2, false, false, true );
			// Get the filepath of the thumbnail image.
			$thumb_path         = $meta->image->thumbPath;
			$ewww_image         = new EWWW_Image( $id, 'nextcell', $thumb_path );
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
		public function ewww_manage_images_columns( $columns ) {
			$columns['ewww_image_optimizer'] = esc_html__( 'Image Optimizer', 'ewww-image-optimizer' );
			return $columns;
		}

		/**
		 * Displays the Image Optimizer data via the ngg_manage_image_custom_column hook.
		 *
		 * @param string $column_name The name of the current column.
		 * @param int    $id The ID number of the current image.
		 * @param bool   $return_output Return the output instead of sending it straight to the screen.
		 * @return string The output when $return is true.
		 */
		public function ewww_manage_image_custom_column( $column_name, $id, $return_output = false ) {
			// Once we've found our custom column.
			if ( 'ewww_image_optimizer' === $column_name ) {
				if ( $return_output ) {
					ob_start();
				}
				// Need this file to work with metadata.
				require_once WP_CONTENT_DIR . '/plugins/nextcellent-gallery-nextgen-legacy/lib/meta.php';
				// Get the metadata for the image.
				$meta = new nggMeta( $id );
				echo '<div id="ewww-nextcellent-status-' . (int) $id . '">';
				// Get the file path of the image.
				$file_path = $meta->image->imagePath;
				// Get the mimetype of the image.
				$type = ewww_image_optimizer_quick_mimetype( $file_path, 'i' );

				// Check to see if we have a tool to handle the mimetype detected.
				if ( ! ewwwio()->tools_initialized && ! ewwwio()->local->os_supported() ) {
					ewwwio()->local->skip_tools();
				} elseif ( ! ewwwio()->tools_initialized ) {
					ewwwio()->tool_init();
				}
				$tools = ewwwio()->local->check_all_tools();
				switch ( $type ) {
					case 'image/jpeg':
						if ( $tools['jpegtran']['enabled'] && ! $tools['jpegtran']['path'] ) {
							/* translators: %s: name of a tool like jpegtran */
							echo '<div>' . sprintf( esc_html__( '%s is missing', 'ewww-image-optimizer' ), '<em>jpegtran</em>' ) . '</div></div>';
							if ( $return_output ) {
								return ob_get_clean();
							}
							return;
						}
						break;
					case 'image/png':
						// If the PNG tools are missing, tell the user.
						if ( $tools['optipng']['enabled'] && ! $tools['optipng']['path'] ) {
							/* translators: %s: name of a tool like jpegtran */
							echo '<div>' . sprintf( esc_html__( '%s is missing', 'ewww-image-optimizer' ), '<em>optipng</em>' ) . '</div></div>';
							if ( $return_output ) {
								return ob_get_clean();
							}
							return;
						}
						break;
					case 'image/gif':
						// If gifsicle is missing, tell the user.
						if ( $tools['gifsicle']['enabled'] && ! $tools['gifsicle']['path'] ) {
							/* translators: %s: name of a tool like jpegtran */
							echo '<div>' . sprintf( esc_html__( '%s is missing', 'ewww-image-optimizer' ), '<em>gifsicle</em>' ) . '</div></div>';
							if ( $return_output ) {
								return ob_get_clean();
							}
							return;
						}
						break;
					default:
						echo '<div>' . esc_html__( 'Unsupported file type', 'ewww-image-optimizer' ) . '</div></div>';
						if ( $return_output ) {
							return ob_get_clean();
						}
						return;
				}
				if ( ! empty( $meta->image->meta_data['ewww_image_optimizer'] ) ) {
					ewww_image_optimizer_update_file_from_meta( $file_path, 'nextcell', $id, 'full' );
					$thumb_path = $meta->image->thumbPath;
					ewww_image_optimizer_update_file_from_meta( $thumb_path, 'nextcell', $id, 'thumbnail' );
				}
				$backup_available = false;
				global $wpdb;
				$optimized_images  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->ewwwio_images WHERE attachment_id = %d AND gallery = 'nextcell' AND image_size <> 0 ORDER BY orig_size DESC", $id ), ARRAY_A );
				$ewww_manual_nonce = wp_create_nonce( 'ewww-manual-' . $id );
				// If we have a valid status, display it, the image size, and give a re-optimize link.
				if ( ! empty( $optimized_images ) ) {
					list( $detail_output, $converted, $backup_available ) = ewww_image_optimizer_custom_column_results( $id, $optimized_images );
					echo wp_kses_post( $detail_output );
					if ( current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
						printf(
							'<a class="ewww-manual-optimize" data-id="%1$d" data-nonce="%2$s" href="' . esc_url( admin_url( 'admin.php?action=ewww_ngg_manual' ) ) . '&amp;ewww_manual_nonce=%2$s&amp;ewww_force=1&amp;ewww_attachment_ID=%1$d">%3$s</a>',
							(int) $id,
							esc_attr( $ewww_manual_nonce ),
							esc_html__( 'Re-optimize', 'ewww-image-optimizer' )
						);
						if ( $backup_available ) {
							printf(
								'<br><a class="ewww-manual-cloud-restore" data-id="%1$d" data-nonce="%2$s" href="' . esc_url( admin_url( 'admin.php?action=ewww_ngg_cloud_restore' ) ) . '&amp;ewww_manual_nonce=%2$s&amp;ewww_attachment_ID=%1$d">%3$s</a>',
								(int) $id,
								esc_attr( $ewww_manual_nonce ),
								esc_html__( 'Restore original', 'ewww-image-optimizer' )
							);
						}
					}
				} elseif ( ewww_image_optimizer_image_is_pending( $id, 'nextc-async' ) ) {
					echo '<div>' . esc_html__( 'In Progress', 'ewww-image-optimizer' ) . '</div>';
					// Otherwise, give the image size, and a link to optimize right now.
				} else {
					if ( current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
						printf(
							'<div><a class="ewww-manual-optimize" data-id="%1$d" data-nonce="%2$s" href="' . esc_url( admin_url( 'admin.php?action=ewww_ngg_manual' ) ) . '&amp;ewww_manual_nonce=%2$s&amp;ewww_attachment_ID=%1$d">%3$s</a></div>',
							(int) $id,
							esc_attr( $ewww_manual_nonce ),
							esc_html__( 'Optimize now!', 'ewww-image-optimizer' )
						);
					}
				}
				echo '</div>';
				if ( $return_output ) {
					return ob_get_clean();
				}
			} // End if().
		}

		/**
		 * Output the html for the bulk optimize page.
		 */
		public function ewww_ngg_bulk_preview() {
			// Retrieve the attachments array from the db.
			$attachments = get_option( 'ewww_image_optimizer_bulk_ngg_attachments' );
			// Make sure there are some attachments to process.
			if ( count( $attachments ) < 1 ) {
				echo '<p>' . esc_html__( 'You do not appear to have uploaded any images yet.', 'ewww-image-optimizer' ) . '</p>';
				return;
			}
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Bulk Optimize', 'ewww-image-optimizer' ); ?></h1>
				<?php
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
					ewww_image_optimizer_cloud_verify( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ), false );
					echo '<a id="ewww-bulk-credits-available" target="_blank" class="page-title-action" style="float:right;" href="https://ewww.io/my-account/">' . esc_html__( 'Image credits available:', 'ewww-image-optimizer' ) . ' ' . esc_html( ewww_image_optimizer_cloud_quota() ) . '</a>';
				}
				// Retrieve the value of the 'bulk resume' option and set the button text for the form to use.
				$resume = get_option( 'ewww_image_optimizer_bulk_ngg_resume' );
				if ( empty( $resume ) ) {
						$button_text = __( 'Start optimizing', 'ewww-image-optimizer' );
				} else {
					$button_text = __( 'Resume previous bulk operation', 'ewww-image-optimizer' );
				}
				/* translators: %d: number of images */
				$selected_images_text = sprintf( _n( 'There is %d image ready to optimize.', 'There are %d images ready to optimize.', count( $attachments ), 'ewww-image-optimizer' ), count( $attachments ) );
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
				<div id="ewww-bulk-forms">
				<p class="ewww-bulk-info"><?php echo esc_html( $selected_images_text ); ?><br />
			<?php esc_html_e( 'Previously optimized images will be skipped by default.', 'ewww-image-optimizer' ); ?></p>
				<form id="ewww-bulk-start" class="ewww-bulk-form" method="post" action="">
				<input type="hidden" id="ewww-delay" name="ewww-delay" value="0">
						<input type="submit" class="button-secondary action" value="<?php echo esc_attr( $button_text ); ?>" />
				</form>
				<?php
				// If there is a previous bulk operation to resume, give the user the option to reset the resume flag.
				if ( ! empty( $resume ) ) {
					?>
					<p class="ewww-bulk-info"><?php esc_html_e( 'If you would like to start over again, press the Reset Status button to reset the bulk operation status.', 'ewww-image-optimizer' ); ?></p>
					<form id="ewww-bulk-reset" class="ewww-bulk-form" method="post" action="">
						<?php wp_nonce_field( 'ewww-image-optimizer-bulk-reset', 'ewww_wpnonce' ); ?>
						<input type="hidden" name="ewww_reset" value="1">
						<input type="submit" class="button-secondary action" value="<?php esc_attr_e( 'Reset Status', 'ewww-image-optimizer' ); ?>" />
					</form>
					<?php
				}
				echo '</div></div>';
				return;
		}

		/**
		 * Prepares the javascript for a bulk operation.
		 *
		 * @global object $nggdb
		 *
		 * @param string $hook The hook identifier for the current page.
		 */
		public function ewww_ngg_bulk_script( $hook ) {
			ewwwio_debug_message( $hook );
			if ( 'galleries_page_ewww-ngg-bulk' !== $hook ) {
				return;
			}
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			$images = null;
			// See if the user wants to reset the previous bulk status.
			if ( ! empty( $_REQUEST['ewww_reset'] ) && ! empty( $_REQUEST['ewww_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-bulk-reset' ) ) {
				update_option( 'ewww_image_optimizer_bulk_ngg_resume', '' );
			}
			// See if there is a previous operation to resume.
			$resume = get_option( 'ewww_image_optimizer_bulk_ngg_resume' );
			// If we've been given a bulk action to perform.
			if ( ! empty( $resume ) ) {
				// Otherwise, if we have an operation to resume.
				ewwwio_debug_message( 'resuming a previous operation (maybe)' );
				// Get the list of attachment IDs from the db.
				$images = get_option( 'ewww_image_optimizer_bulk_ngg_attachments' );
				// Otherwise, if we are on the standard bulk page, get all the images in the db.
			} else {
				ewwwio_debug_message( 'starting from scratch, grabbing all the images' );
				global $wpdb;
				$images = $wpdb->get_col( "SELECT pid FROM $wpdb->nggpictures ORDER BY sortorder ASC" );
			} // End if().

			// Store the image IDs to process in the db.
			update_option( 'ewww_image_optimizer_bulk_ngg_attachments', $images, false );
			// Add the EWWW IO script.
			wp_enqueue_script( 'ewwwbulkscript', plugins_url( '/includes/eio-bulk.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array( 'jquery', 'jquery-ui-progressbar', 'jquery-ui-slider', 'postbox', 'dashboard' ), EWWW_IMAGE_OPTIMIZER_VERSION );
			// Replacing the built-in nextgen styling rules for progressbar.
			wp_register_style( 'ngg-jqueryui', plugins_url( '/includes/jquery-ui-1.10.1.custom.css', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ) );
			// Enqueue the progressbar styling.
			wp_enqueue_style( 'ngg-jqueryui' );
			wp_add_inline_style( 'ngg-jqueryui', '.ui-widget-header { background-color: ' . ewww_image_optimizer_admin_background() . '; }' );
			// Include all the vars we need for javascript.
			wp_localize_script(
				'ewwwbulkscript',
				'ewww_vars',
				array(
					'_wpnonce'              => wp_create_nonce( 'ewww-image-optimizer-bulk' ),
					'gallery'               => 'nextgen',
					'attachments'           => count( $images ),
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
		 * Start the bulk operation.
		 */
		public function ewww_ngg_bulk_init() {
			$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
			$output      = array();
			if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
				$output['error'] = esc_html__( 'Access denied.', 'ewww-image-optimizer' );
				ewwwio_ob_clean();
				wp_die( wp_json_encode( $output ) );
			}
			// Toggle the resume flag to indicate an operation is in progress.
			update_option( 'ewww_image_optimizer_bulk_ngg_resume', 'true' );
			// Get the list of attachments remaining from the db.
			$attachments = get_option( 'ewww_image_optimizer_bulk_ngg_attachments' );
			if ( ! is_array( $attachments ) && ! empty( $attachments ) ) {
				$attachments = unserialize( $attachments );
			}
			if ( ! is_array( $attachments ) ) {
				$output['error'] = esc_html__( 'Error retrieving list of images', 'ewww-image-optimizer' );
				ewwwio_ob_clean();
				wp_die( wp_json_encode( $output ) );
			}
			$id        = array_shift( $attachments );
			$file_name = $this->ewww_ngg_bulk_filename( $id );
			// Let the user know we are starting.
			$loading_image = plugins_url( '/images/wpspin.gif', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE );
			if ( empty( $file_name ) ) {
				$output['results'] = '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' alt='loading'/></p>";
			} else {
				$output['results'] = '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . ' <b>' . $file_name . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
			}
			ewwwio_ob_clean();
			wp_die( wp_json_encode( $output ) );
		}

		/**
		 * Retrieve the filename of the image being optimized.
		 *
		 * @param int $id The ID number of the image.
		 */
		public function ewww_ngg_bulk_filename( $id ) {
			// Need this file to work with metadata.
			require_once WP_CONTENT_DIR . '/plugins/nextcellent-gallery-nextgen-legacy/lib/meta.php';
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
		 */
		public function ewww_ngg_bulk_loop() {
			ewwwio()->defer = false;
			$output         = array();
			$permissions    = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
			if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
				$outupt['error'] = esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' );
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
			// Find out what time we started, in microseconds.
			$started = microtime( true );
			// Get the list of attachments remaining from the db.
			$attachments         = get_option( 'ewww_image_optimizer_bulk_ngg_attachments' );
			$id                  = array_shift( $attachments );
			list( $fres, $tres ) = $this->ewww_ngg_optimize( $id );
			if ( 'exceeded' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
				$output['error'] = ewww_image_optimizer_credits_exceeded();
				ewwwio_ob_clean();
				wp_die( wp_json_encode( $output ) );
			}
			if ( 'exceeded quota' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
				$output['error'] = ewww_image_optimizer_soft_quota_exceeded();
				ewwwio_ob_clean();
				wp_die( wp_json_encode( $output ) );
			}
			if ( 'exceeded subkey' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
				$output['error'] = esc_html__( 'Out of credits', 'ewww-image-optimizer' );
				ewwwio_ob_clean();
				wp_die( wp_json_encode( $output ) );
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
			$output['results']  .= sprintf( esc_html( _n( 'Elapsed: %s second', 'Elapsed: %s seconds', $elapsed, 'ewww-image-optimizer' ) ) . '</p>', number_format_i18n( $elapsed, 2 ) );
			$output['completed'] = 1;
			// Store the list back in the db.
			update_option( 'ewww_image_optimizer_bulk_ngg_attachments', $attachments, false );
			if ( ! empty( $attachments ) ) {
				$next_attachment = array_shift( $attachments );
				$next_file       = $this->ewww_ngg_bulk_filename( $next_attachment );
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
		 * Finish the bulk operation.
		 */
		public function ewww_ngg_bulk_cleanup() {
			$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
			if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
				ewwwio_ob_clean();
				wp_die( esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) );
			}
			// Reset all the bulk options in the db...
			update_option( 'ewww_image_optimizer_bulk_ngg_resume', '' );
			update_option( 'ewww_image_optimizer_bulk_ngg_attachments', '', false );
			// and let the user know we are done.
			ewwwio_ob_clean();
			wp_die( '<p><b>' . esc_html__( 'Finished Optimization!', 'ewww-image-optimizer' ) . '</b></p>' );
		}

		/**
		 * Prepare javascript for one-click actions on manage gallery page.
		 *
		 * @param string $hook The hook value for the current page.
		 */
		public function ewww_ngg_manual_actions_script( $hook ) {
			if ( 'galleries_page_nggallery-manage' !== $hook ) {
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
					'restoring'  => '<p>' . esc_html__( 'Restoring', 'ewww-image-optimizer' ) . " <img src='$loading_image' /></p>",
				)
			);
		}
	}

	global $ewwwngg;
	$ewwwngg = new EWWW_Nextcellent();
} // End if().
