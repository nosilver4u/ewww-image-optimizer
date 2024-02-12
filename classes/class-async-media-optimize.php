<?php
/**
 * Class for async optimization of Media Library images.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles an async request used to optimize a Media Library image.
 *
 * Used to optimize a single image, like a resize, retina, or the original upload for a
 * Media Library attachment. Done in parallel to increase processing capability.
 *
 * @see EWWW\Async_Request
 */
class Async_Media_Optimize extends Async_Request {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_async_media_optimize';

	/**
	 * Handles the async media image optimization request.
	 *
	 * Called via a POST to optimize an image from a Media Library attachment using parallel optimization.
	 *
	 * @global object $ewww_image Tracks attributes of the image currently being optimized.
	 */
	protected function handle() {
		session_write_close();
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		check_ajax_referer( $this->identifier, 'nonce' );
		if ( empty( $_POST['ewwwio_size'] ) ) {
			$size = '';
		} else {
			$size = sanitize_key( $_POST['ewwwio_size'] );
		}
		if ( empty( $_POST['ewwwio_attachment_id'] ) ) {
			$id = 0;
		} else {
			$id = (int) $_POST['ewwwio_attachment_id'];
		}
		if ( empty( $_POST['ewwwio_id'] ) ) {
			return;
		}
		ewwwio()->force = ! empty( $_REQUEST['ewww_force'] ) ? true : false;
		$ewwwio_id      = (int) $_POST['ewwwio_id'];
		global $ewww_image;
		$file_path = ewww_image_optimizer_find_file_by_id( $ewwwio_id );
		if ( $file_path && 'full' === $size ) {
			ewwwio_debug_message( "processing async optimization request for $file_path" );
			$ewww_image         = new EWWW_Image( $id, 'media', $file_path );
			$ewww_image->resize = 'full';

			list( $file, $msg, $conv, $original ) = ewww_image_optimizer( $file_path, 1, false, false, true );
		} elseif ( $file_path ) {
			ewwwio_debug_message( "processing async optimization request for $file_path" );
			$ewww_image         = new EWWW_Image( $id, 'media', $file_path );
			$ewww_image->resize = ( empty( $size ) ? null : $size );

			list( $file, $msg, $conv, $original ) = ewww_image_optimizer( $file_path );
		} else {
			if ( $ewwwio_id && ! $file_path ) {
				ewwwio_debug_message( "could not find file to process async optimization request for $ewwwio_id" );
			} else {
				ewwwio_debug_message( 'ignored async optimization request' );
			}
			return;
		}
		ewww_image_optimizer_hidpi_optimize( $file_path );
		ewwwio_debug_message( 'checking for: ' . $file_path . '.processing' );
		if ( ewwwio_is_file( $file_path . '.processing' ) ) {
			ewwwio_debug_message( 'removing ' . $file_path . '.processing' );
			$upload_path = wp_get_upload_dir();
			ewwwio_delete_file( $file_path . '.processing' );
		}
	}
}
