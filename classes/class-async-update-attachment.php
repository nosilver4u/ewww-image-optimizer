<?php
/**
 * Class for asynchronous updating of attachments.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles an async request to update the attachment metadata after an attachment has been optimized.
 *
 * @see EWWW\Async_Request
 */
class Async_Update_Attachment extends Async_Request {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_update_attachment_async';

	/**
	 * Handles the async scan request.
	 */
	protected function handle() {
		session_write_close();
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		check_ajax_referer( $this->identifier, 'nonce' );
		if ( ! empty( $_REQUEST['attachment_id'] ) ) {
			$attachment_id = (int) $_REQUEST['attachment_id'];
			ewww_image_optimizer_post_optimize_attachment( $attachment_id );
		}
	}
}
