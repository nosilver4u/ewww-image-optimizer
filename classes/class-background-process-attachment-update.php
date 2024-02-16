<?php
/**
 * Class for asynchronous/background updating of Media Library attachments.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes an update of the attachment metadata after an attachment has been optimized.
 *
 * @see EWWW\Background_Process
 */
class Background_Process_Attachment_Update extends Background_Process {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_attachment_update';

	/**
	 * The queue name for this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $active_queue = 'attachment-update';

	/**
	 * Runs the attachment update.
	 *
	 * @access protected
	 *
	 * @param array $item The id of the attachment, and how many attempts have been made to process the item.
	 * @return bool False indicates completion.
	 */
	protected function task( $item ) {
		session_write_close();
		\ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( empty( $item['id'] ) ) {
			return false;
		}
		$id = (int) $item['id'];
		\ewwwio_debug_message( "updating attachment $id" );
		\ewww_image_optimizer_post_optimize_attachment( $id );
		return false;
	}

	/**
	 * Runs failure routine for an item from the queue.
	 *
	 * @access protected
	 *
	 * @param array $item The id of the attachment, and how many attempts have been made to process the item.
	 */
	protected function failure( $item ) {
		\ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		\ewwwio_debug_message( 'really?' );
		return;
	}
}
