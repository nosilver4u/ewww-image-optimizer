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
 * Handles an async request used to test the viability of using async requests
 * elsewhere.
 *
 * During a plugin update, an async request is sent with a specific string
 * value to validate that nothing is blocking admin-ajax.php requests from
 * the server to itself. Once verified, full background/parallel processing
 * can be used.
 *
 * @see EWWW\Async_Request
 */
class Async_Test_Request extends Async_Request {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_test_async';

	/**
	 * Handles the test async request.
	 *
	 * Called via a POST request to verify that nothing is blocking or altering requests from the server to itself.
	 */
	protected function handle() {
		session_write_close();
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		check_ajax_referer( $this->identifier, 'nonce' );
		if ( empty( $_POST['ewwwio_test_verify'] ) ) {
			return;
		}
		$item = sanitize_key( $_POST['ewwwio_test_verify'] );
		ewwwio_debug_message( "testing async handling, received $item" );
		if ( ewww_image_optimizer_detect_wpsf_location_lock() ) {
			ewwwio_debug_message( 'detected location lock, not enabling background opt' );
			return;
		}
		if ( '949c34123cf2a4e4ce2f985135830df4a1b2adc24905f53d2fd3f5df5b162932' !== $item ) {
			ewwwio_debug_message( 'wrong item received, not enabling background opt' );
			return;
		}
		ewwwio_debug_message( 'setting background option to true' );
		$success = ewww_image_optimizer_set_option( 'ewww_image_optimizer_background_optimization', true );
		if ( $success ) {
			ewwwio_debug_message( 'hurrah, async enabled!' );
		}
	}
}
