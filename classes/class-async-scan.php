<?php
/**
 * Class for Background of Media Library images.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles an async request to scan for unoptimized images. Subsequent calls will resume from the previous request.
 *
 * @see EWWW\Async_Request
 */
class Async_Scan extends Async_Request {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_scan_async';

	/**
	 * Handles the async scan request.
	 */
	protected function handle() {
		session_write_close();
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		check_ajax_referer( $this->identifier, 'nonce' );
		global $ewww_scan;
		$ewww_scan = empty( $_REQUEST['ewww_scan'] ) ? '' : sanitize_key( $_REQUEST['ewww_scan'] );
		ewww_image_optimizer_aux_images_script( 'ewww-image-optimizer-auto' );
	}
}
