<?php
/**
 * Class for Background optimization of individual images, used by scheduled optimization.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes a single image in background/async mode.
 *
 * @see EWWW\Background_Process
 */
class Background_Process_Image extends Background_Process {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_image_optimize';

	/**
	 * The queue name for this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $active_queue = 'single-async';

	/**
	 * Attempts limit, shorter for scheduled opt.
	 *
	 * @var int
	 * @access protected
	 */
	protected $max_attempts = 10;

	/**
	 * The number of images completed so far.
	 *
	 * @var int
	 * @access protected
	 */
	protected $completed = 0;

	/**
	 * Handle
	 *
	 * Wrapper around parent::handle() to verify that background processing isn't paused.
	 */
	protected function handle() {
		if ( \get_option( 'ewww_image_optimizer_pause_queues' ) ) {
			\ewwwio_debug_message( 'all queues paused' );
			return;
		}
		if ( \get_option( 'ewww_image_optimizer_pause_image_queue' ) ) {
			\ewwwio_debug_message( 'this queue paused' );
			return;
		}
		parent::handle();
	}

	/**
	 * Runs optimization for a file from the image queue.
	 *
	 * @access protected
	 *
	 * @param string $item The filename of the attachment.
	 * @return bool False indicates completion.
	 */
	protected function old_task( $item ) {
		\session_write_close();
		$id = (int) $item['id'];
		\ewwwio_debug_message( "background processing $id" );
		$file_path = \ewww_image_optimizer_find_file_by_id( $id );
		if ( $file_path ) {
			$attachment = array(
				'id'   => $id,
				'path' => $file_path,
			);
			\ewwwio_debug_message( "processing background optimization request for $file_path" );
			\ewww_image_optimizer_aux_images_loop( $attachment, true );
		} else {
			\ewwwio_debug_message( "could not find file to process background optimization request for $id" );
			return false;
		}
		$delay = (int) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' );
		if ( $delay && \ewww_image_optimizer_function_exists( 'sleep' ) ) {
			sleep( $delay );
		}
		return false;
	}

	/**
	 * Runs optimization for a file from the image queue.
	 *
	 * @access protected
	 *
	 * @param array $item The id of the db record for an image, how many attempts have been made to process
	 *                    the item, along with any other optimization parameters.
	 * @return bool False indicates completion.
	 */
	protected function task( $item ) {
		\session_write_close();
		global $ewww_convert;
		$id = (int) $item['id'];
		\ewwwio_debug_message( "background processing $id" );

		if ( ! $this->is_key_valid() ) {
			// There is another process running.
			\ewwwio_debug_message( "this key is different than the stored key: {$this->lock_key}" );
			die;
		}
		\ewwwio_debug_message( 'this key is still active: ' . $this->lock_key );

		$image = new \EWWW_Image( $id );
		// Force the process to re-spawn if we don't have enough time remaining for this image.
		$time_estimate = $image->time_estimate();
		if ( empty( $image->retrieve ) && $this->completed && time() + $time_estimate > $this->start_time + \apply_filters( $this->identifier . '_default_time_limit', $this->time_limit ) ) {
			\ewwwio_debug_message( 'not enough time left, respawning' );
			\add_filter( $this->identifier . '_time_exceeded', '__return_true' );
			return $item;
		}

		if ( $image->file ) {
			$image->new            = $item['new'];
			$ewww_convert          = $item['convert_once'];
			\ewwwio()->force       = $item['force_reopt'];
			\ewwwio()->force_smart = $item['force_smart'];
			\ewwwio()->webp_only   = $item['webp_only'];
			\ewwwio_debug_message( "processing background optimization request for $image->file, converting: $ewww_convert" );
			$pending = $this->process_image( $image, $item['attempts'] );
			if ( $pending ) {
				\ewwwio_debug_message( "requeueing $id" );
				return $item;
			}
		} else {
			\ewwwio_debug_message( "could not find file to process background optimization request for $id" );
			return false;
		}
		$delay = (int) \ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' );
		if ( $delay && \ewww_image_optimizer_function_exists( 'sleep' ) ) {
			\ewwwio_debug_message( "pausing for $delay seconds" );
			sleep( $delay );
		}
		++$this->completed;
		return false;
	}

	/**
	 * Process an image, whether media or not, from async handler.
	 *
	 * Will potentially integrate other "gallery types" in the future.
	 *
	 * @access protected
	 *
	 * @param array $image An EWWW_Image() object with all the pertinent details.
	 * @param int   $attempts How many previous attempts have been made. Optional, default 0.
	 * @return bool Similar to task(), false indicates completion, true to re-queue image.
	 */
	protected function process_image( $image, $attempts = 0 ) {
		\ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		$output          = array();
		$time_adjustment = 0;

		// Prevents the 'updates' column from increasing, because this is intentional, usually.
		// And even if it isn't, we probably have no way of tracking the source.
		\add_filter( 'ewww_image_optimizer_allowed_reopt', '__return_true' );
		\ewwwio()->cloud_async_allowed = true;

		$meta = false;
		\ewwwio_debug_message( "processing {$image->id}: {$image->file}, previous attempts: $attempts" );
		// See if the image needs fetching from a CDN.
		if ( ! \ewwwio_is_file( $image->file ) ) {
			$meta      = \wp_get_attachment_metadata( $image->attachment_id );
			$file_path = \ewww_image_optimizer_remote_fetch( $image->attachment_id, $meta );
			// Nuke the meta, otherwise this will trigger unnecessary metadata updates,
			// which should be reserved for conversion/resize operations on the full-size image only.
			unset( $meta );
			if ( ! $file_path ) {
				\ewwwio_debug_message( 'could not retrieve path' );
				return false;
			}
		}

		if ( \ewww_image_optimizer_stl_check() && \ewww_image_optimizer_function_exists( 'ini_get' ) && \ini_get( 'max_execution_time' ) < 60 ) {
			\set_time_limit( 0 );
		}

		global $ewww_image;
		$ewww_image = $image;

		if ( $attempts && empty( $image->retrieve ) ) {
			$countermeasures = \ewww_image_optimizer_bulk_counter_measures( $image );
			if ( $countermeasures ) {
				\add_filter( $this->identifier . '_time_exceeded', '__return_true' );
			}
		}

		\set_transient( 'ewww_image_optimizer_bulk_current_image', $image->file, 600 );

		if ( ewww_image_optimizer_should_resize( $image->file, 'media' === $image->gallery ) ) {
			if (
				'media' === $image->gallery &&
				'full' === $image->resize &&
				! function_exists( 'imsanity_get_max_width_height' ) &&
				(
					( ! $image->new && \ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_existing' ) ) ||
					( $image->new && \apply_filters( 'ewww_image_optimizer_defer_resizing', false ) )
				)
			) {
				if ( empty( $meta ) || ! \is_array( $meta ) ) {
					$meta = \wp_get_attachment_metadata( $image->attachment_id );
				}
				$new_dimensions = \ewww_image_optimizer_resize_upload( $image->file );
				if ( ! empty( $new_dimensions ) && \is_array( $new_dimensions ) ) {
					$meta['width']  = $new_dimensions[0];
					$meta['height'] = $new_dimensions[1];
				}
			} elseif ( empty( $image->resize ) ) {
				$new_dimensions = \ewww_image_optimizer_resize_upload( $image->file );
			}
		}

		// Check for a pending 'retrieve' id, and use the cloud_retrieve() function instead.
		if ( ! empty( $image->retrieve ) ) {
			if ( ! ewww_image_optimizer_cloud_retrieve_pending_image( $image ) ) {
				\delete_transient( 'ewww_image_optimizer_bulk_counter_measures' );
				\delete_transient( 'ewww_image_optimizer_bulk_current_image' );
				\ewwwio_debug_message( 'API optimization still pending, waiting a little longer' );
				return true;
			}
			$image->retrieve      = '';
			$ewww_image->retrieve = '';
			$file                 = $image->file;
			$converted            = false;
		} else {
			$gallery_id = \ewwwio()->gallery_name_to_id( $image->gallery );
			// The 'original_image' needs special handling for full-size lossy/metadata overrides.
			if ( 'original_image' === $image->resize ) {
				$gallery_id = 5;
			}
			list( $file, $msg, $converted, $original ) = \ewww_image_optimizer( $image->file, $gallery_id, false, $image->new, 'full' === $image->resize || 'original_image' === $image->resize );
		}

		// Afterward, check for an API retrieve param/property,
		// and return true to requeue if retrieval is still pending.
		if ( ! empty( $ewww_image->retrieve ) ) {
			\ewwwio_debug_message( 'API optimization pending, ending current cycle' );
			// End this batch, so that the retrieve function will have the full request time to attempt a retrieval.
			\add_filter( $this->identifier . '_time_exceeded', '__return_true' );
			\delete_transient( 'ewww_image_optimizer_bulk_counter_measures' );
			\delete_transient( 'ewww_image_optimizer_bulk_current_image' );
			return true;
		}

		// Gotta make sure we don't delete a pending record if the license is exceeded, so the license check goes first.
		if ( \ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
			if ( false !== \strpos( \get_transient( 'ewww_image_optimizer_cloud_status' ), 'exceeded' ) ) {
				\delete_transient( 'ewww_image_optimizer_bulk_counter_measures' );
				\delete_transient( 'ewww_image_optimizer_bulk_current_image' );
				\update_option( 'ewww_image_optimizer_pause_image_queue', true, false );
				\update_option( 'ewww_image_optimizer_pause_queues', true, false );
				\ewwwio_debug_message( 'API quota has been reached, async handler bailing' );
				die;
			}
		}

		// Delete a pending record if the optimization failed.
		if ( ! $file && $image->id ) {
			\ewww_image_optimizer_delete_pending_image( $image->id );
		}
		// Toggle a pending record if the optimization was webp-only.
		if ( true === $file && $image->id ) {
			\ewww_image_optimizer_toggle_pending_image( $image->id );
		}
		// If this is a full size image and it was converted.
		if ( 'full' === $image->resize && false !== $converted ) {
			if ( empty( $meta ) || ! \is_array( $meta ) ) {
				$meta = \wp_get_attachment_metadata( $image->attachment_id );
			}
			$image->file      = $file;
			$image->converted = $original;
			$meta['file']     = \_wp_relative_upload_path( $file );
			$image->update_converted_attachment( $meta );
			$meta = $image->convert_sizes( $meta );
		}

		// Do metadata update after full-size is processed, usually because of conversion or resizing.
		if ( 'full' === $image->resize && $image->attachment_id ) {
			if ( ! empty( $meta ) && \is_array( $meta ) ) {
				\ewwwio_debug_message( 'saving meta for ' . $image->attachment_id );
				\clearstatcache();
				if ( ! empty( $image->file ) && \is_file( $image->file ) ) {
					$meta['filesize'] = \filesize( $image->file );
				}
				\add_filter( 'as3cf_pre_update_attachment_metadata', '__return_true' );
				$meta_saved = \wp_update_attachment_metadata( $image->attachment_id, $meta );
				if ( ! $meta_saved ) {
					\ewwwio_debug_message( 'failed to save meta, or unchanged' );
				}
			}
		}

		// When we finish all the sizes, we fire off a metadata update for plugins that might need to take action when an image is updated.
		// The call to wp_get_attachment_metadata() is done in an async request for better reliability, giving it the full request time to complete.
		if ( $image->attachment_id && $image->gallery ) {
			$another_image = \ewww_image_optimizer_attachment_has_pending_sizes( $image->attachment_id, $image->gallery );
			if ( empty( $another_image ) ) {
				\ewwwio_debug_message( "queueing async metadata update for $image->attachment_id" );
				ewwwio()->background_attachment_update->push_to_queue(
					array(
						'id' => $image->attachment_id,
					)
				);
				if ( ! ewwwio()->background_attachment_update->is_process_running() ) {
					ewwwio_debug_message( 'attachment update process idle, dispatching post-haste' );
					ewwwio()->background_attachment_update->dispatch();
				}
			}
		}

		\ewww_image_optimizer_debug_log();
		\delete_transient( 'ewww_image_optimizer_bulk_counter_measures' );
		\delete_transient( 'ewww_image_optimizer_bulk_current_image' );
		return false; // All done with this image, next!
	}

	/**
	 * Runs failure routine for an item from the queue.
	 *
	 * @access protected
	 *
	 * @param array $item The id of the attachment, how many attempts have been made to process
	 *                    the item and whether it is a new upload.
	 */
	protected function failure( $item ) {
		if ( empty( $item['id'] ) ) {
			return;
		}
		$file_path = \ewww_image_optimizer_find_file_by_id( $item['id'] );
		if ( $file_path ) {
			\ewww_image_optimizer_add_file_exclusion( $file_path );
		}
		\ewww_image_optimizer_delete_pending_image( $item['id'] );
	}
}
