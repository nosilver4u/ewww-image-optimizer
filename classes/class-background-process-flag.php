<?php
/**
 * Class for Background processing of FlaGallery images.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes FlaGallery uploads in background/async mode.
 *
 * @see EWWW\Background_Process
 */
class Background_Process_Flag extends Background_Process {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_flag_optimize';

	/**
	 * The queue name for this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $active_queue = 'flag-async';

	/**
	 * Runs task for an item from the FlaGallery queue.
	 *
	 * Makes sure an image upload has finished processing and has been stored in the database.
	 * Then runs the usual flag optimization routine on the specified item.
	 *
	 * @access protected
	 * @global bool $ewwwflag
	 *
	 * @param array $item The id of the upload, and how many attempts have been made so far.
	 * @return bool|array If the item is not complete, return it. False indicates completion.
	 */
	protected function task( $item ) {
		session_write_close();
		if ( empty( $item['attempts'] ) ) {
			$item['attempts'] = 0;
		}
		$attachment_id = $item['attachment_id'];
		ewwwio_debug_message( "background processing flagallery: $attachment_id" );
		if ( ! class_exists( 'flagMeta' ) ) {
			if ( defined( 'FLAG_ABSPATH' ) && ewwwio_is_file( FLAG_ABSPATH . 'lib/meta.php' ) ) {
				require_once FLAG_ABSPATH . 'lib/meta.php';
			} else {
				return false;
			}
		}
		// Retrieve the metadata for the image.
		$meta = new flagMeta( $attachment_id );
		if ( empty( $meta ) ) {
			++$item['attempts'];
			sleep( 4 );
			ewwwio_debug_message( "could not retrieve meta, requeueing {$item['attempts']}" );
			return $item;
		}
		global $ewwwflag;
		$ewwwflag->ewww_added_new_image( $attachment_id, $meta );
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
		if ( empty( $item['attachment_id'] ) ) {
			return;
		}
		if ( ! class_exists( 'flagMeta' ) ) {
			if ( defined( 'FLAG_ABSPATH' ) && ewwwio_is_file( FLAG_ABSPATH . 'lib/meta.php' ) ) {
				require_once FLAG_ABSPATH . 'lib/meta.php';
			} else {
				return;
			}
		}
		// Retrieve the metadata for the image.
		$meta = new flagMeta( $item['attachment_id'] );
		if ( ! empty( $meta ) && isset( $meta->image->imagePath ) ) {
			ewww_image_optimizer_add_file_exclusion( $meta->image->imagePath );
		}
	}
}
