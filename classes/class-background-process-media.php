<?php
/**
 * Class for Background processing of Media Library images.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes media uploads in background/async mode.
 *
 * Uses a db queue system to track uploads to be optimized, handling them one at a time.
 *
 * @see EWWW\Background_Process
 */
class Background_Process_Media extends Background_Process {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_media_optimize';

	/**
	 * The queue name for this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $active_queue = 'media-async';

	/**
	 * Runs task for an item from the Media Library queue.
	 *
	 * Makes sure an image upload has finished processing and has been stored in the database.
	 * Then runs the usual media optimization routine on the specified item.
	 *
	 * @access protected
	 * @global bool $ewww_defer True to defer optimization, false otherwise.
	 *
	 * @param array $item The id of the attachment, how many attempts have been made to process
	 *                    the item, the type of attachment, and whether it is a new upload.
	 * @return bool|array If the item is not complete, return it. False indicates completion.
	 */
	protected function task( $item ) {
		session_write_close();
		global $ewww_defer;
		$ewww_defer   = false;
		$max_attempts = 15;
		$id           = $item['id'];
		if ( empty( $item['attempts'] ) ) {
			ewwwio_debug_message( 'first attempt, going to sleep for a bit' );
			$item['attempts'] = 0;
			sleep( 1 ); // On the first attempt, hold off and wait for the db to catch up.
		}
		$type = get_post_mime_type( $id );
		if ( empty( $type ) ) {
			ewwwio_debug_message( "mime is missing, requeueing {$item['attempts']}" );
			sleep( 4 );
			return $item;
		}
		ewwwio_debug_message( "background processing $id, type: " . $type );
		$image_types = array(
			'image/jpeg',
			'image/png',
			'image/gif',
		);

		if ( in_array( $type, $image_types, true ) && $item['new'] && class_exists( 'wpCloud\StatelessMedia\EWWW' ) ) {
			$meta = wp_get_attachment_metadata( $id );
		} else {
			// This is unfiltered for performance, because we don't often need filtered meta.
			$meta = wp_get_attachment_metadata( $id, true );
		}
		if ( in_array( $type, $image_types, true ) && empty( $meta ) ) {
			ewwwio_debug_message( "metadata is missing, requeueing {$item['attempts']}" );
			sleep( 4 );
			return $item;
		}
		$meta = ewww_image_optimizer_resize_from_meta_data( $meta, $id, true, $item['new'] );
		if ( ! empty( $meta['processing'] ) ) {
			ewwwio_debug_message( 'image not finished, try again' );
			return $item;
		}
		if ( class_exists( 'wpCloud\StatelessMedia\EWWW' ) ) {
			ewwwio_debug_message( 'async optimize complete, triggering wp_update_attachment_metadata filter with existing meta' );
			$meta = apply_filters( 'wp_update_attachment_metadata', wp_get_attachment_metadata( $image->attachment_id ), $image->attachment_id );
		} else {
			ewwwio_debug_message( 'async optimize complete, running wp_update_attachment_metadata()' );
			wp_update_attachment_metadata( $id, $meta );
		}
		return false;
	}

	/**
	 * Runs failure routine for an item from the Media Library queue.
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
		$file_path = false;
		$meta      = wp_get_attachment_metadata( $item['id'] );
		if ( ! empty( $meta ) ) {
			list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $item['id'] );
		}

		if ( $file_path ) {
			ewww_image_optimizer_add_file_exclusion( $file_path );
		}
	}
}
