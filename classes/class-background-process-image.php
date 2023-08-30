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
	protected $max_attempts = 5;

	/**
	 * Runs optimization for a file from the image queue.
	 *
	 * @access protected
	 *
	 * @param string $item The filename of the attachment.
	 * @return bool False indicates completion.
	 */
	protected function task( $item ) {
		session_write_close();
		$id = (int) $item['id'];
		ewwwio_debug_message( "background processing $id" );
		$file_path = ewww_image_optimizer_find_file_by_id( $id );
		if ( $file_path ) {
			$attachment = array(
				'id'   => $id,
				'path' => $file_path,
			);
			ewwwio_debug_message( "processing background optimization request for $file_path" );
			ewww_image_optimizer_aux_images_loop( $attachment, true );
		} else {
			ewwwio_debug_message( "could not find file to process background optimization request for $id" );
			return false;
		}
		$delay = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' );
		if ( $delay && ewww_image_optimizer_function_exists( 'sleep' ) ) {
			sleep( $delay );
		}
		return false;
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
		global $wpdb;
		$file_path = ewww_image_optimizer_find_file_by_id( $item['id'] );
		if ( $file_path ) {
			ewww_image_optimizer_add_file_exclusion( $file_path );
		}
		$wpdb->query( $wpdb->prepare( "DELETE from $wpdb->ewwwio_images WHERE id=%d pending=1 AND (image_size IS NULL OR image_size = 0)", $item['id'] ) );
	}
}
